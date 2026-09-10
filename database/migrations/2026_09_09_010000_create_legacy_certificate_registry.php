<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('legacy_certificate_imports')) {
            Schema::create('legacy_certificate_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('snapshot_id', 80)->unique();
            $table->char('payload_sha256', 64)->unique();
            $table->string('captured_at', 64);
            $table->string('imported_at', 32);
            $table->string('source_database', 64);
            $table->string('source_table', 64);
            $table->unsignedInteger('record_count');
            $table->json('summary');
            $table->longText('provenance');
            $table->foreignId('audit_id')->constrained('audit_logs')->restrictOnDelete();
            $table->char('manifest_hmac', 64);
            });
        }
        $this->assertTable('legacy_certificate_imports');
        if (! Schema::hasTable('legacy_certificates')) {
            Schema::create('legacy_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_id')->constrained('legacy_certificate_imports')->restrictOnDelete();
            $table->string('source_database', 64);
            $table->string('source_table', 64);
            $table->string('source_id', 20)->collation('utf8mb4_bin');
            $table->string('snapshot_id', 80)->index();
            $table->text('holder_name')->nullable();
            $table->text('certificate_title')->nullable();
            $table->text('registration_number')->nullable();
            $table->text('barcode')->nullable();
            $table->string('barcode_kind', 8);
            $table->char('barcode_sha256', 64)->nullable()->index();
            $table->string('stored_status', 255)->nullable()->index();
            $table->text('source_country')->nullable();
            $table->text('valid_from')->nullable();
            $table->text('valid_until')->nullable();
            $table->text('legacy_type')->nullable();
            $table->text('legacy_design')->nullable();
            $table->char('source_row_sha256', 64);
            $table->longText('original_fields');
            $table->json('sql_candidate_source_ids');
            $table->unsignedInteger('candidate_count');
            $table->boolean('is_ambiguous')->index();
            $table->char('projection_hash', 64);
            $table->char('projection_hmac', 64);
            $table->unique(['source_database', 'source_table', 'source_id'], 'legacy_certificate_source_identity');
            });
        }
        $this->assertTable('legacy_certificates');
    }

    public function down(): void
    {
        // An installer rollback must never silently discard imported original records.
        if (Schema::hasTable('legacy_certificates') && \Illuminate\Support\Facades\DB::table('legacy_certificates')->exists()) {
            throw new \RuntimeException('LEGACY_CERTIFICATE_RESTORE_REQUIRES_EXPLICIT_DATABASE_RECOVERY');
        }
        Schema::dropIfExists('legacy_certificates');
        Schema::dropIfExists('legacy_certificate_imports');
    }

    public function assertSchema(): void
    {
        // Verification never repairs or creates a missing table.
        $this->assertTable('legacy_certificate_imports');
        $this->assertTable('legacy_certificates');
    }

    private function assertTable(string $name): void
    {
        $imports = [
            'id' => ['bigint unsigned', false], 'snapshot_id' => ['varchar(80)', false],
            'payload_sha256' => ['char(64)', false], 'captured_at' => ['varchar(64)', false],
            'imported_at' => ['varchar(32)', false], 'source_database' => ['varchar(64)', false],
            'source_table' => ['varchar(64)', false], 'record_count' => ['int unsigned', false],
            'summary' => ['longtext', false], 'provenance' => ['longtext', false],
            'audit_id' => ['bigint unsigned', false], 'manifest_hmac' => ['char(64)', false],
        ];
        $records = ['id' => ['bigint unsigned', false], 'import_id' => ['bigint unsigned', false],
            'source_database' => ['varchar(64)', false], 'source_table' => ['varchar(64)', false],
            'source_id' => ['varchar(20)', false], 'snapshot_id' => ['varchar(80)', false]];
        foreach (['holder_name', 'certificate_title', 'registration_number', 'barcode', 'source_country',
            'valid_from', 'valid_until', 'legacy_type', 'legacy_design'] as $column) { $records[$column] = ['text', true]; }
        $records += ['barcode_kind' => ['varchar(8)', false], 'barcode_sha256' => ['char(64)', true],
            'stored_status' => ['varchar(255)', true], 'source_row_sha256' => ['char(64)', false],
            'original_fields' => ['longtext', false], 'sql_candidate_source_ids' => ['longtext', false],
            'candidate_count' => ['int unsigned', false], 'is_ambiguous' => ['tinyint', false],
            'projection_hash' => ['char(64)', false], 'projection_hmac' => ['char(64)', false]];
        $expected = $name === 'legacy_certificate_imports' ? $imports : $records;
        $table = DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$name]);
        if (! $table || strtolower((string) $table->engine) !== 'innodb') {
            throw new \RuntimeException('LEGACY_REGISTRY_EXISTING_SCHEMA_MISMATCH');
        }
        $columns = DB::select('SELECT COLUMN_NAME AS column_name, COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable, COLLATION_NAME AS collation_name, EXTRA AS extra FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$name]);
        if (count($columns) !== count($expected)) { throw new \RuntimeException('LEGACY_REGISTRY_EXISTING_SCHEMA_MISMATCH'); }
        foreach ($columns as $column) {
            $type = strtolower((string) $column->column_type);
            $type = preg_replace('/\b(bigint|int|tinyint)\([0-9]+\)/', '$1', $type);
            if ($type === 'json') { $type = 'longtext'; }
            if (! isset($expected[$column->column_name])
                || $expected[$column->column_name] !== [$type, $column->is_nullable === 'YES']
                || ($column->column_name === 'source_id' && $column->collation_name !== 'utf8mb4_bin')
                || ($column->column_name === 'id' && ! str_contains(strtolower($column->extra), 'auto_increment'))) {
                throw new \RuntimeException('LEGACY_REGISTRY_EXISTING_SCHEMA_MISMATCH');
            }
        }
        $indexRows = DB::select('SELECT INDEX_NAME AS index_name, NON_UNIQUE AS non_unique, COLUMN_NAME AS column_name, SEQ_IN_INDEX AS sequence_number, SUB_PART AS sub_part FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX', [$name]);
        $unique = [];
        foreach ($indexRows as $index) {
            if ((int) $index->non_unique === 0) {
                if ($index->sub_part !== null) { throw new \RuntimeException('LEGACY_REGISTRY_EXISTING_SCHEMA_MISMATCH'); }
                $unique[$index->index_name][] = $index->column_name;
            }
        }
        $required = $name === 'legacy_certificate_imports'
            ? [['id'], ['snapshot_id'], ['payload_sha256']]
            : [['id'], ['source_database', 'source_table', 'source_id']];
        if (count($unique) !== count($required)) { throw new \RuntimeException('LEGACY_REGISTRY_EXISTING_SCHEMA_MISMATCH'); }
        foreach ($required as $index) {
            if (! in_array($index, $unique, true)) { throw new \RuntimeException('LEGACY_REGISTRY_EXISTING_SCHEMA_MISMATCH'); }
        }
        $fkColumn = $name === 'legacy_certificate_imports' ? 'audit_id' : 'import_id';
        $fkTable = $name === 'legacy_certificate_imports' ? 'audit_logs' : 'legacy_certificate_imports';
        $foreign = DB::select('SELECT COLUMN_NAME AS column_name, REFERENCED_TABLE_SCHEMA AS target_schema, REFERENCED_TABLE_NAME AS target_table, REFERENCED_COLUMN_NAME AS target_column FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL', [$name]);
        if (count($foreign) !== 1 || $foreign[0]->column_name !== $fkColumn || $foreign[0]->target_table !== $fkTable
            || $foreign[0]->target_column !== 'id' || $foreign[0]->target_schema !== DB::connection()->getDatabaseName()) {
            throw new \RuntimeException('LEGACY_REGISTRY_EXISTING_SCHEMA_MISMATCH');
        }
    }
};
