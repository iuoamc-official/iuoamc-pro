<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('account_record_links', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('record_type', 40);
            $table->unsignedBigInteger('record_id');
            $table->char('email_hmac', 64);
            $table->timestamp('matched_at');
            $table->timestamps();
            $table->unique(['user_id', 'record_type', 'record_id'], 'account_record_link_unique');
            $table->index(['record_type', 'record_id']);
            $table->index(['user_id', 'email_hmac']);
        });

        Schema::create('account_documents', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('category', 20);
            $table->string('title', 180);
            $table->string('reference', 100)->nullable();
            $table->longText('recipient_email');
            $table->char('email_hmac', 64)->index();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('pdf_path', 500);
            $table->char('pdf_sha256', 64);
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->char('record_hash', 64)->nullable();
            $table->foreignId('integrity_audit_id')->nullable()->constrained('audit_logs')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['organization_id', 'category', 'issued_at']);
        });

        foreach ([
            ['account-documents.view', 'View issued account documents'],
            ['account-documents.manage', 'Issue PDF documents, invoices and receipts'],
        ] as [$code, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => ucwords(str_replace(['.', '-'], ' ', $code)),
                    'module' => 'accounting',
                    'description' => $description,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $permission = DB::table('permissions')->where('code', $code)->first();
            $role = DB::table('roles')->where('slug', 'super-admin')->first();
            if ($permission !== null && $role !== null) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permission->id,
                    'role_id' => $role->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        throw new \RuntimeException('Account portal records require a controlled restore.');
    }
};
