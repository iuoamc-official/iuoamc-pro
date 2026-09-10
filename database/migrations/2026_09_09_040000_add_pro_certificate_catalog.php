<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('pro_certificates') || ! Schema::hasTable('pro_certificate_sequences')) { $this->mismatch(); }
        if (! Schema::hasTable('pro_certificate_types')) {
            Schema::create('pro_certificate_types', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
                $table->string('code', 20)->collation('utf8mb4_bin');
                $table->string('number_prefix', 40)->collation('utf8mb4_bin')->unique();
                foreach (['ar', 'en', 'fr'] as $locale) { $table->string('name_'.$locale, 120); }
                $table->string('category', 24);
                $table->string('layout', 16);
                foreach (['ar', 'en', 'fr'] as $locale) { $table->string('title_'.$locale, 120); }
                foreach (['ar', 'en', 'fr'] as $locale) { $table->text('statement_'.$locale); }
                $table->string('signatory_name', 120);
                $table->string('signatory_title', 120);
                $table->boolean('active')->default(true);
                $table->unsignedInteger('lock_version')->default(1);
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
                $table->char('record_hash', 64)->nullable();
                $table->foreignId('integrity_audit_id')->nullable()->constrained('audit_logs')->restrictOnDelete();
                $table->timestamps();
                $table->unique(['organization_id', 'code']);
                $table->index(['organization_id', 'active']);
            });
        }
        if (! Schema::hasTable('pro_certificate_type_sequences')) {
            Schema::create('pro_certificate_type_sequences', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->foreignId('type_id')->constrained('pro_certificate_types')->restrictOnDelete();
                $table->unsignedSmallInteger('year');
                $table->unsignedBigInteger('last_number')->default(0);
                $table->primary(['type_id', 'year']);
            });
        }
        // One additive DDL step per column supports a restart after an interrupted ALTER.
        // No existing certificate row is updated or re-signed.
        if (! Schema::hasColumn('pro_certificates', 'schema_version')) {
            Schema::table('pro_certificates', fn (Blueprint $table) => $table->unsignedInteger('schema_version')->default(1));
        }
        if (! Schema::hasColumn('pro_certificates', 'catalog_type_id')) {
            Schema::table('pro_certificates', fn (Blueprint $table) => $table->foreignId('catalog_type_id')->nullable()
                ->constrained('pro_certificate_types')->restrictOnDelete());
        }
        if (! Schema::hasColumn('pro_certificates', 'catalog_snapshot')) {
            Schema::table('pro_certificates', fn (Blueprint $table) => $table->json('catalog_snapshot')->nullable());
        }
        if (! Schema::hasColumn('pro_certificates', 'specialization')) {
            Schema::table('pro_certificates', fn (Blueprint $table) => $table->string('specialization', 200)->nullable());
        }
        $this->assertSchema();
    }

    public function down(): void
    {
        throw new RuntimeException('Certificate catalog, sealed snapshots and issued sequences cannot be automatically deleted.');
    }

    public function assertSchema(): void
    {
        $types = ['id' => ['bigint unsigned', false], 'organization_id' => ['bigint unsigned', false],
            'code' => ['varchar(20)', false], 'number_prefix' => ['varchar(40)', false],
            'category' => ['varchar(24)', false], 'layout' => ['varchar(16)', false],
            'signatory_name' => ['varchar(120)', false], 'signatory_title' => ['varchar(120)', false],
            'active' => ['tinyint', false], 'lock_version' => ['int unsigned', false],
            'created_by' => ['bigint unsigned', false], 'updated_by' => ['bigint unsigned', false],
            'record_hash' => ['char(64)', true], 'integrity_audit_id' => ['bigint unsigned', true],
            'created_at' => ['timestamp', true], 'updated_at' => ['timestamp', true]];
        foreach (['ar', 'en', 'fr'] as $locale) {
            $types['name_'.$locale] = ['varchar(120)', false];
            $types['title_'.$locale] = ['varchar(120)', false];
            $types['statement_'.$locale] = ['text', false];
        }
        $this->assertColumns('pro_certificate_types', $types, true, ['code', 'number_prefix']);
        $this->assertColumns('pro_certificate_type_sequences', ['type_id' => ['bigint unsigned', false],
            'year' => ['smallint unsigned', false], 'last_number' => ['bigint unsigned', false]], true);
        $this->assertColumns('pro_certificates', ['schema_version' => ['int unsigned', false],
            'catalog_type_id' => ['bigint unsigned', true], 'catalog_snapshot' => ['longtext', true],
            'specialization' => ['varchar(200)', true]], false);
        $default = DB::selectOne('SELECT COLUMN_DEFAULT AS default_value FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', ['pro_certificates', 'schema_version']);
        if ((string) ($default->default_value ?? '') !== '1') { $this->mismatch(); }
        $this->assertIndexes('pro_certificate_types', [['id'], ['number_prefix'], ['organization_id', 'code']], [['organization_id', 'active']]);
        $this->assertIndexes('pro_certificate_type_sequences', [['type_id', 'year']], []);
        $this->assertForeign('pro_certificate_types', ['organization_id' => 'organizations', 'created_by' => 'users',
            'updated_by' => 'users', 'integrity_audit_id' => 'audit_logs'], true);
        $this->assertForeign('pro_certificate_type_sequences', ['type_id' => 'pro_certificate_types'], true);
        $this->assertForeign('pro_certificates', ['catalog_type_id' => 'pro_certificate_types'], false);
    }

    private function assertColumns(string $name, array $expected, bool $exact, array $binary = []): void
    {
        $info = DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$name]);
        if (! $info || strtolower((string) $info->engine) !== 'innodb') { $this->mismatch(); }
        $columns = DB::select('SELECT COLUMN_NAME AS column_name, COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable, COLLATION_NAME AS collation_name, EXTRA AS extra FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$name]);
        if ($exact && count($columns) !== count($expected)) { $this->mismatch(); }
        $seen = [];
        foreach ($columns as $column) {
            if (! isset($expected[$column->column_name])) { continue; }
            $type = preg_replace('/\b(bigint|smallint|int|tinyint)\([0-9]+\)/', '$1', strtolower((string) $column->column_type));
            if ($type === 'json') { $type = 'longtext'; }
            if ($expected[$column->column_name] !== [$type, $column->is_nullable === 'YES']
                || (in_array($column->column_name, $binary, true) && $column->collation_name !== 'utf8mb4_bin')
                || ($column->column_name === 'id' && ! str_contains(strtolower((string) $column->extra), 'auto_increment'))) { $this->mismatch(); }
            $seen[] = $column->column_name;
        }
        if (count($seen) !== count($expected)) { $this->mismatch(); }
    }

    private function assertIndexes(string $name, array $requiredUnique, array $requiredOther): void
    {
        $indexes = DB::select('SELECT INDEX_NAME AS index_name, NON_UNIQUE AS non_unique, COLUMN_NAME AS column_name, SUB_PART AS sub_part FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX', [$name]);
        $unique = $all = [];
        foreach ($indexes as $index) {
            if ($index->sub_part !== null) { $this->mismatch(); }
            $all[$index->index_name][] = $index->column_name;
            if ((int) $index->non_unique === 0) { $unique[$index->index_name][] = $index->column_name; }
        }
        if (count($unique) !== count($requiredUnique)) { $this->mismatch(); }
        foreach ($requiredUnique as $key) { if (! in_array($key, $unique, true)) { $this->mismatch(); } }
        foreach ($requiredOther as $key) { if (! in_array($key, $all, true)) { $this->mismatch(); } }
    }

    private function assertForeign(string $name, array $expected, bool $exact): void
    {
        $foreign = DB::select('SELECT k.COLUMN_NAME AS column_name, k.REFERENCED_TABLE_SCHEMA AS target_schema, k.REFERENCED_TABLE_NAME AS target_table, k.REFERENCED_COLUMN_NAME AS target_column, r.DELETE_RULE AS delete_rule FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL', [$name]);
        if ($exact && count($foreign) !== count($expected)) { $this->mismatch(); }
        $seen = [];
        foreach ($foreign as $key) {
            if (! isset($expected[$key->column_name])) { continue; }
            if ($expected[$key->column_name] !== $key->target_table || $key->target_column !== 'id'
                || $key->target_schema !== DB::connection()->getDatabaseName()
                || strtoupper((string) $key->delete_rule) !== 'RESTRICT') { $this->mismatch(); }
            $seen[] = $key->column_name;
        }
        if (count($seen) !== count($expected)) { $this->mismatch(); }
    }

    private function mismatch(): never { throw new RuntimeException('PRO_CERTIFICATE_CATALOG_SCHEMA_MISMATCH'); }
};
