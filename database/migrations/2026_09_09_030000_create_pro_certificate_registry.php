<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A MariaDB DDL interruption can leave a completed first table. Validate before reusing it.
        foreach (['pro_certificate_sequences', 'pro_certificates'] as $name) {
            if (Schema::hasTable($name)) { $this->assertTable($name); }
        }

        if (! Schema::hasTable('pro_certificate_sequences')) {
        Schema::create('pro_certificate_sequences', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
        }

        if (! Schema::hasTable('pro_certificates')) {
        Schema::create('pro_certificates', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->char('record_uuid', 36)->collation('utf8mb4_bin')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('recipient_name', 180);
            $table->string('public_name', 120);
            $table->string('program_title', 200);
            $table->string('certificate_title', 120);
            $table->string('certificate_type', 24);
            $table->string('language', 2);
            $table->date('achievement_date');
            $table->date('expires_on')->nullable();
            $table->text('statement');
            $table->string('signatory_name', 120);
            $table->string('signatory_title', 120);
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('certificate_number', 80)->nullable()->unique();
            $table->char('public_token', 64)->nullable()->collation('utf8mb4_bin')->unique();
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('last_reason')->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->json('issued_payload')->nullable();
            $table->char('payload_sha256', 64)->nullable();
            $table->string('signature', 88)->nullable();
            $table->string('signing_key_id', 96)->nullable();
            $table->char('record_hash', 64)->nullable();
            $table->foreignId('integrity_audit_id')->nullable()->constrained('audit_logs')->restrictOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'public_name']);
            $table->index(['status', 'expires_on']);
        });
        }
        $this->assertSchema();
    }

    public function down(): void
    {
        throw new RuntimeException('Certificate history and issued numbers require a controlled restore; automatic destructive rollback is disabled.');
    }

    public function assertSchema(): void
    {
        $this->assertTable('pro_certificate_sequences');
        $this->assertTable('pro_certificates');
    }

    private function assertTable(string $name): void
    {
        $expected = $name === 'pro_certificate_sequences' ? [
            'year' => ['smallint unsigned', false], 'last_number' => ['bigint unsigned', false],
        ] : [
            'id' => ['bigint unsigned', false], 'record_uuid' => ['char(36)', false],
            'organization_id' => ['bigint unsigned', false], 'recipient_name' => ['varchar(180)', false],
            'public_name' => ['varchar(120)', false], 'program_title' => ['varchar(200)', false],
            'certificate_title' => ['varchar(120)', false], 'certificate_type' => ['varchar(24)', false],
            'language' => ['varchar(2)', false], 'achievement_date' => ['date', false], 'expires_on' => ['date', true],
            'statement' => ['text', false], 'signatory_name' => ['varchar(120)', false],
            'signatory_title' => ['varchar(120)', false], 'status' => ['varchar(16)', false],
            'lock_version' => ['int unsigned', false], 'certificate_number' => ['varchar(80)', true],
            'public_token' => ['char(64)', true], 'issued_at' => ['datetime', true],
            'approved_at' => ['datetime', true], 'revoked_at' => ['datetime', true],
            'created_by' => ['bigint unsigned', false], 'updated_by' => ['bigint unsigned', false],
            'approved_by' => ['bigint unsigned', true], 'issued_by' => ['bigint unsigned', true],
            'revoked_by' => ['bigint unsigned', true], 'last_reason' => ['text', true],
            'pdf_path' => ['varchar(255)', true], 'pdf_sha256' => ['char(64)', true],
            'issued_payload' => ['longtext', true], 'payload_sha256' => ['char(64)', true],
            'signature' => ['varchar(88)', true], 'signing_key_id' => ['varchar(96)', true],
            'record_hash' => ['char(64)', true], 'integrity_audit_id' => ['bigint unsigned', true],
            'created_at' => ['timestamp', true], 'updated_at' => ['timestamp', true],
        ];
        $table = DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$name]);
        if (! $table || strtolower((string) $table->engine) !== 'innodb') { $this->mismatch(); }
        $columns = DB::select('SELECT COLUMN_NAME AS column_name, COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable, COLLATION_NAME AS collation_name, EXTRA AS extra FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$name]);
        if (count($columns) !== count($expected)) { $this->mismatch(); }
        foreach ($columns as $column) {
            $type = preg_replace('/\b(bigint|smallint|int|tinyint)\([0-9]+\)/', '$1', strtolower((string) $column->column_type));
            if ($type === 'json') { $type = 'longtext'; }
            if (! isset($expected[$column->column_name])
                || $expected[$column->column_name] !== [$type, $column->is_nullable === 'YES']
                || (in_array($column->column_name, ['public_token', 'record_uuid'], true) && $column->collation_name !== 'utf8mb4_bin')
                || ($column->column_name === 'id' && ! str_contains(strtolower((string) $column->extra), 'auto_increment'))) {
                $this->mismatch();
            }
        }
        $indexes = DB::select('SELECT INDEX_NAME AS index_name, NON_UNIQUE AS non_unique, COLUMN_NAME AS column_name, SUB_PART AS sub_part FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX', [$name]);
        $unique = [];
        $all = [];
        foreach ($indexes as $index) {
            if ($index->sub_part !== null) { $this->mismatch(); }
            $all[$index->index_name][] = $index->column_name;
            if ((int) $index->non_unique === 0) { $unique[$index->index_name][] = $index->column_name; }
        }
        $requiredUnique = $name === 'pro_certificate_sequences' ? [['year']]
            : [['id'], ['record_uuid'], ['certificate_number'], ['public_token']];
        if (count($unique) !== count($requiredUnique)) { $this->mismatch(); }
        foreach ($requiredUnique as $index) {
            if (! in_array($index, $unique, true)) { $this->mismatch(); }
        }
        if ($name === 'pro_certificates') {
            foreach ([['organization_id', 'status'], ['organization_id', 'public_name'], ['status', 'expires_on']] as $index) {
                if (! in_array($index, $all, true)) { $this->mismatch(); }
            }
        }
        $expectedForeign = $name === 'pro_certificate_sequences' ? [] : [
            'organization_id' => 'organizations', 'created_by' => 'users', 'updated_by' => 'users',
            'approved_by' => 'users', 'issued_by' => 'users', 'revoked_by' => 'users', 'integrity_audit_id' => 'audit_logs',
        ];
        $foreign = DB::select('SELECT k.COLUMN_NAME AS column_name, k.REFERENCED_TABLE_SCHEMA AS target_schema, k.REFERENCED_TABLE_NAME AS target_table, k.REFERENCED_COLUMN_NAME AS target_column, r.DELETE_RULE AS delete_rule FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL', [$name]);
        if (count($foreign) !== count($expectedForeign)) { $this->mismatch(); }
        foreach ($foreign as $key) {
            if (($expectedForeign[$key->column_name] ?? null) !== $key->target_table
                || $key->target_column !== 'id' || $key->target_schema !== DB::connection()->getDatabaseName()
                || strtoupper((string) $key->delete_rule) !== 'RESTRICT') { $this->mismatch(); }
        }
    }

    private function mismatch(): never
    {
        throw new RuntimeException('PRO_CERTIFICATE_EXISTING_SCHEMA_MISMATCH');
    }
};
