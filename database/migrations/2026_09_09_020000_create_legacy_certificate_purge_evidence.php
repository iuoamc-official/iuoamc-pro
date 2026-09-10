<?php
declare(strict_types=1);

use App\Services\LegacyCertificatePurge;
use App\Services\LegacyCertificateRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->targetOnly();
        if (! Schema::hasTable('legacy_certificate_imports') || ! Schema::hasTable('legacy_certificates')) {
            throw new RuntimeException('LEGACY_PURGE_ORIGINAL_REGISTRY_REQUIRED');
        }
        if (! Schema::hasTable('legacy_certificate_purge_proofs')) {
            Schema::create('legacy_certificate_purge_proofs', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('snapshot_id', 80)->unique();
                $table->foreignId('import_id')->unique()->constrained('legacy_certificate_imports')->restrictOnDelete();
                $table->json('payload');
                $table->char('payload_sha256', 64)->unique();
                $table->foreignId('audit_id')->unique()->constrained('audit_logs')->restrictOnDelete();
            });
        }
        $this->assertTable('legacy_certificate_purge_proofs');
        if (! Schema::hasTable('legacy_certificate_exclusions')) {
            Schema::create('legacy_certificate_exclusions', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('purge_id')->constrained('legacy_certificate_purge_proofs')->restrictOnDelete();
                $table->string('snapshot_id', 80);
                $table->string('source_database', 64);
                $table->string('source_table', 64);
                $table->string('source_id', 20)->collation('utf8mb4_bin');
                $table->unsignedBigInteger('local_id')->unique();
                $table->char('source_row_sha256', 64);
                // Snapshot is deliberately NOT in this key: source identity stays excluded in later captures.
                $table->unique(['source_database', 'source_table', 'source_id'], 'lcp_exclusion_source_identity');
            });
        }
        $this->assertTable('legacy_certificate_exclusions');
        foreach (LegacyCertificatePurge::triggerDefinitions() as $name => [$table, $event, $body]) {
            $exists = DB::selectOne('SELECT TRIGGER_NAME AS name FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?', [$name]);
            if ($exists === null) {
                DB::unprepared("CREATE TRIGGER `$name` BEFORE $event ON `$table` FOR EACH ROW $body");
            }
        }
        $this->assertSchema();
    }

    public function assertSchema(): void
    {
        $this->assertTable('legacy_certificate_purge_proofs');
        $this->assertTable('legacy_certificate_exclusions');
        app(LegacyCertificatePurge::class)->assertGuards();
    }

    public function down(): void
    {
        $this->targetOnly();
        foreach (['legacy_certificate_purge_proofs', 'legacy_certificate_exclusions'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('LEGACY_PURGE_EVIDENCE_CANNOT_BE_ROLLED_BACK');
            }
        }
        foreach (array_keys(LegacyCertificatePurge::triggerDefinitions()) as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS `$name`");
        }
        Schema::dropIfExists('legacy_certificate_exclusions');
        Schema::dropIfExists('legacy_certificate_purge_proofs');
    }

    private function targetOnly(): void
    {
        $connection = DB::connection();
        if (PHP_SAPI !== 'cli' || ! app()->runningInConsole() || DB::transactionLevel() !== 0
            || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== LegacyCertificateRegistry::TARGET_DATABASE
            || $connection->getTablePrefix() !== ''
            || ! in_array($connection->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) {
            throw new RuntimeException('LEGACY_PURGE_DDL_TARGET_GUARD');
        }
    }

    private function assertTable(string $name): void
    {
        $proof = ['id' => 'bigint unsigned', 'snapshot_id' => 'varchar(80)',
            'import_id' => 'bigint unsigned', 'payload' => 'longtext',
            'payload_sha256' => 'char(64)', 'audit_id' => 'bigint unsigned'];
        $exclusions = ['id' => 'bigint unsigned', 'purge_id' => 'bigint unsigned',
            'snapshot_id' => 'varchar(80)', 'source_database' => 'varchar(64)',
            'source_table' => 'varchar(64)', 'source_id' => 'varchar(20)',
            'local_id' => 'bigint unsigned', 'source_row_sha256' => 'char(64)'];
        $expected = $name === 'legacy_certificate_purge_proofs' ? $proof : $exclusions;
        $table = DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$name]);
        if ($table === null || strtolower((string) $table->engine) !== 'innodb') { $this->badSchema(); }
        $columns = DB::select('SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type_name, IS_NULLABLE AS nullable, COLLATION_NAME AS collation_name, EXTRA AS extra FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$name]);
        if (count($columns) !== count($expected)) { $this->badSchema(); }
        foreach ($columns as $column) {
            $type = preg_replace('/\b(bigint|int)\([0-9]+\)/', '$1', strtolower($column->type_name));
            if ($type === 'json') { $type = 'longtext'; }
            if (($expected[$column->name] ?? null) !== $type || $column->nullable !== 'NO'
                || ($column->name === 'id' && ! str_contains(strtolower($column->extra), 'auto_increment'))
                || ($column->name === 'source_id' && $column->collation_name !== 'utf8mb4_bin')) { $this->badSchema(); }
        }
        $indexes = DB::select('SELECT INDEX_NAME AS name, NON_UNIQUE AS non_unique, COLUMN_NAME AS column_name, SUB_PART AS sub_part FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX', [$name]);
        $unique = [];
        foreach ($indexes as $index) {
            if ((int) $index->non_unique !== 0) { continue; }
            if ($index->sub_part !== null) { $this->badSchema(); }
            $unique[$index->name][] = $index->column_name;
        }
        $required = $name === 'legacy_certificate_purge_proofs'
            ? [['id'], ['snapshot_id'], ['import_id'], ['payload_sha256'], ['audit_id']]
            : [['id'], ['local_id'], ['source_database', 'source_table', 'source_id']];
        if (count($required) !== count($unique)) { $this->badSchema(); }
        foreach ($required as $index) { if (! in_array($index, $unique, true)) { $this->badSchema(); } }
        $fks = DB::select('SELECT COLUMN_NAME AS name, REFERENCED_TABLE_NAME AS target, REFERENCED_COLUMN_NAME AS target_column, REFERENCED_TABLE_SCHEMA AS target_schema FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL', [$name]);
        $expectedFks = $name === 'legacy_certificate_purge_proofs'
            ? ['import_id' => 'legacy_certificate_imports', 'audit_id' => 'audit_logs']
            : ['purge_id' => 'legacy_certificate_purge_proofs'];
        if (count($fks) !== count($expectedFks)) { $this->badSchema(); }
        foreach ($fks as $fk) {
            if (($expectedFks[$fk->name] ?? null) !== $fk->target || $fk->target_column !== 'id'
                || $fk->target_schema !== DB::connection()->getDatabaseName()) { $this->badSchema(); }
        }
    }

    private function badSchema(): never { throw new RuntimeException('LEGACY_PURGE_EVIDENCE_SCHEMA_MISMATCH'); }
};
