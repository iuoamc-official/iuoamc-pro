<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('wicp_records')) { $this->assertSchema(); return; }
        Schema::create('wicp_records', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->char('record_uuid', 36)->collation('utf8mb4_bin')->unique();
            $table->string('reference', 64)->collation('utf8mb4_bin')->unique();
            $table->char('public_token', 64)->collation('utf8mb4_bin')->unique();
            $table->string('kind', 16);
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('authority_organization_id')->constrained('organizations')->restrictOnDelete();
            $table->json('authority_snapshot');
            $table->foreignId('parent_id')->nullable()->constrained('wicp_records')->restrictOnDelete();
            $table->foreignId('certificate_id')->nullable()->unique()->constrained('pro_certificates')->restrictOnDelete();
            // Certificate rows use NULL in these columns and bind the selected program in registration_payload.
            $table->string('program_code', 80)->nullable()->collation('utf8mb4_bin');
            $table->string('program_title', 255)->nullable();
            $table->string('program_version', 80)->nullable()->collation('utf8mb4_bin');
            $table->char('source_record_uuid', 36)->nullable()->collation('utf8mb4_bin')->unique();
            $table->string('source_number', 80)->nullable();
            $table->char('source_payload_sha256', 64)->nullable();
            $table->char('source_pdf_sha256', 64)->nullable();
            $table->json('registration_payload');
            $table->char('binding_sha256', 64);
            $table->string('status', 16);
            $table->unsignedInteger('lock_version')->default(1);
            $table->dateTime('registered_at');
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('last_reason')->nullable();
            $table->json('signed_payload')->nullable();
            $table->char('payload_sha256', 64)->nullable();
            $table->string('signature', 88)->nullable();
            $table->string('signing_key_id', 96)->nullable();
            $table->char('record_hash', 64)->nullable();
            $table->foreignId('integrity_audit_id')->nullable()->constrained('audit_logs')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'program_code', 'program_version'], 'wicp_program_identity_unique');
            $table->index(['organization_id', 'kind', 'status'], 'wicp_owner_kind_status');
        });
        $this->assertSchema();
    }

    public function down(): void
    {
        throw new RuntimeException('WICP registered history requires a controlled restore; destructive migration rollback is disabled.');
    }

    public function assertSchema(): void
    {
        $name = 'wicp_records';
        $expected = [
            'id' => ['bigint unsigned', false], 'record_uuid' => ['char(36)', false],
            'reference' => ['varchar(64)', false], 'public_token' => ['char(64)', false], 'kind' => ['varchar(16)', false],
            'organization_id' => ['bigint unsigned', false], 'authority_organization_id' => ['bigint unsigned', false],
            'authority_snapshot' => ['longtext', false], 'parent_id' => ['bigint unsigned', true], 'certificate_id' => ['bigint unsigned', true],
            'program_code' => ['varchar(80)', true], 'program_title' => ['varchar(255)', true], 'program_version' => ['varchar(80)', true],
            'source_record_uuid' => ['char(36)', true], 'source_number' => ['varchar(80)', true],
            'source_payload_sha256' => ['char(64)', true], 'source_pdf_sha256' => ['char(64)', true],
            'registration_payload' => ['longtext', false], 'binding_sha256' => ['char(64)', false],
            'status' => ['varchar(16)', false], 'lock_version' => ['int unsigned', false],
            'registered_at' => ['datetime', false], 'revoked_at' => ['datetime', true],
            'created_by' => ['bigint unsigned', false], 'updated_by' => ['bigint unsigned', false], 'revoked_by' => ['bigint unsigned', true],
            'last_reason' => ['text', true], 'signed_payload' => ['longtext', true], 'payload_sha256' => ['char(64)', true],
            'signature' => ['varchar(88)', true], 'signing_key_id' => ['varchar(96)', true], 'record_hash' => ['char(64)', true],
            'integrity_audit_id' => ['bigint unsigned', true], 'created_at' => ['timestamp', true], 'updated_at' => ['timestamp', true],
        ];
        $table = DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$name]);
        if (! $table || strtolower((string) $table->engine) !== 'innodb') { $this->mismatch(); }
        $columns = DB::select('SELECT COLUMN_NAME AS column_name, COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable, COLLATION_NAME AS collation_name, EXTRA AS extra FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$name]);
        if (count($columns) !== count($expected)) { $this->mismatch(); }
        foreach ($columns as $column) {
            $type = preg_replace('/\b(bigint|smallint|int|tinyint)\([0-9]+\)/', '$1', strtolower((string) $column->column_type));
            if ($type === 'json') { $type = 'longtext'; }
            if (! isset($expected[$column->column_name]) || $expected[$column->column_name] !== [$type, $column->is_nullable === 'YES']
                || (in_array($column->column_name, ['record_uuid', 'public_token', 'reference', 'program_code', 'program_version', 'source_record_uuid'], true) && $column->collation_name !== 'utf8mb4_bin')
                || ($column->column_name === 'id' && ! str_contains(strtolower((string) $column->extra), 'auto_increment'))) { $this->mismatch(); }
        }
        $indexes = DB::select('SELECT INDEX_NAME AS index_name, NON_UNIQUE AS non_unique, COLUMN_NAME AS column_name, SUB_PART AS sub_part FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX', [$name]);
        $unique = []; $all = [];
        foreach ($indexes as $index) {
            if ($index->sub_part !== null) { $this->mismatch(); }
            $all[$index->index_name][] = $index->column_name;
            if ((int) $index->non_unique === 0) { $unique[$index->index_name][] = $index->column_name; }
        }
        $required = [['id'], ['record_uuid'], ['reference'], ['public_token'], ['certificate_id'], ['source_record_uuid'], ['organization_id', 'program_code', 'program_version']];
        if (count($unique) !== count($required)) { $this->mismatch(); }
        foreach ($required as $index) { if (! in_array($index, $unique, true)) { $this->mismatch(); } }
        if (! in_array(['organization_id', 'kind', 'status'], $all, true)) { $this->mismatch(); }
        $expectedForeign = ['organization_id' => 'organizations', 'authority_organization_id' => 'organizations', 'parent_id' => 'wicp_records',
            'certificate_id' => 'pro_certificates', 'created_by' => 'users', 'updated_by' => 'users', 'revoked_by' => 'users', 'integrity_audit_id' => 'audit_logs'];
        $foreign = DB::select('SELECT k.COLUMN_NAME AS column_name, k.REFERENCED_TABLE_SCHEMA AS target_schema, k.REFERENCED_TABLE_NAME AS target_table, k.REFERENCED_COLUMN_NAME AS target_column, r.DELETE_RULE AS delete_rule FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL', [$name]);
        if (count($foreign) !== count($expectedForeign)) { $this->mismatch(); }
        foreach ($foreign as $key) {
            if (($expectedForeign[$key->column_name] ?? null) !== $key->target_table || $key->target_column !== 'id'
                || $key->target_schema !== DB::connection()->getDatabaseName() || strtoupper((string) $key->delete_rule) !== 'RESTRICT') { $this->mismatch(); }
        }
    }

    private function mismatch(): never { throw new RuntimeException('WICP_EXISTING_SCHEMA_MISMATCH'); }
};
