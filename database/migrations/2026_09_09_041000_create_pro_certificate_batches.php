<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('pro_certificate_types') || ! Schema::hasTable('pro_certificates')) { $this->mismatch(); }
        if (! Schema::hasTable('pro_certificate_batches')) {
        Schema::create('pro_certificate_batches', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('catalog_type_id')->constrained('pro_certificate_types')->restrictOnDelete();
            $table->string('label', 120);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('request_key')->unique();
            $table->char('input_digest', 64);
            $table->json('member_ids');
            $table->char('record_hash', 64)->nullable();
            $table->foreignId('integrity_audit_id')->nullable()->constrained('audit_logs')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['organization_id', 'created_at'], 'pro_certificate_batches_org_created');
        });
        }
        if (! Schema::hasTable('pro_certificate_batch_runs')) {
        Schema::create('pro_certificate_batch_runs', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->foreignId('batch_id')->constrained('pro_certificate_batches')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 12);
            $table->char('reviewed_fingerprint', 64);
            $table->json('plan');
            $table->json('results');
            $table->string('status', 12);
            $table->string('stop_reason', 40)->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->char('record_hash', 64)->nullable();
            $table->foreignId('integrity_audit_id')->nullable()->constrained('audit_logs')->restrictOnDelete();
            $table->timestamps();
            $table->index(['batch_id', 'status'], 'pro_certificate_batch_runs_batch_status');
        });
        }
        $this->assertSchema();
    }

    public function down(): void
    {
        throw new LogicException('Signed certificate batch history requires an explicitly reviewed migration.');
    }
    public function assertSchema(): void
    {
        $this->assertColumns('pro_certificate_batches', [
            'id' => ['bigint unsigned', false], 'record_uuid' => ['char(36)', false],
            'organization_id' => ['bigint unsigned', false], 'catalog_type_id' => ['bigint unsigned', false],
            'label' => ['varchar(120)', false], 'created_by' => ['bigint unsigned', false],
            'request_key' => ['char(36)', false], 'input_digest' => ['char(64)', false],
            'member_ids' => ['longtext', false], 'record_hash' => ['char(64)', true],
            'integrity_audit_id' => ['bigint unsigned', true], 'created_at' => ['timestamp', false],
        ], true);
        $this->assertColumns('pro_certificate_batch_runs', [
            'id' => ['bigint unsigned', false], 'record_uuid' => ['char(36)', false],
            'batch_id' => ['bigint unsigned', false], 'actor_id' => ['bigint unsigned', false],
            'action' => ['varchar(12)', false], 'reviewed_fingerprint' => ['char(64)', false],
            'plan' => ['longtext', false], 'results' => ['longtext', false], 'status' => ['varchar(12)', false],
            'stop_reason' => ['varchar(40)', true], 'lock_version' => ['int unsigned', false],
            'record_hash' => ['char(64)', true], 'integrity_audit_id' => ['bigint unsigned', true],
            'created_at' => ['timestamp', true], 'updated_at' => ['timestamp', true],
        ], true);
        $this->assertIndexes('pro_certificate_batches', [['id'], ['record_uuid'], ['request_key']], [['organization_id', 'created_at']]);
        $this->assertIndexes('pro_certificate_batch_runs', [['id'], ['record_uuid']], [['batch_id', 'status']]);
        $this->assertForeign('pro_certificate_batches', ['organization_id' => 'organizations',
            'catalog_type_id' => 'pro_certificate_types', 'created_by' => 'users', 'integrity_audit_id' => 'audit_logs'], true);
        $this->assertForeign('pro_certificate_batch_runs', ['batch_id' => 'pro_certificate_batches',
            'actor_id' => 'users', 'integrity_audit_id' => 'audit_logs'], true);
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

    private function mismatch(): never { throw new RuntimeException('PRO_CERTIFICATE_BATCH_SCHEMA_MISMATCH'); }
};
