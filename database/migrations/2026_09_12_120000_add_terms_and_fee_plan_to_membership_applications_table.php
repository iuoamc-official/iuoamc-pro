<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table): void {
            $table->string('membership_category_code', 40)->nullable()->after('membership_id');
            $table->unsignedSmallInteger('membership_term_years')->nullable()->after('membership_category_code');
            $table->unsignedInteger('standard_fee_pence')->nullable()->after('membership_term_years');
            $table->unsignedInteger('discount_pence')->nullable()->after('standard_fee_pence');
            $table->unsignedInteger('payable_fee_pence')->nullable()->after('discount_pence');
            $table->char('fee_currency', 3)->nullable()->after('payable_fee_pence');
            $table->string('terms_version', 100)->nullable()->after('fee_currency');
            $table->string('privacy_version', 100)->nullable()->after('terms_version');
            $table->timestamp('terms_accepted_at')->nullable()->after('privacy_version');
            $table->boolean('immediate_service_requested')->nullable()->after('terms_accepted_at');
            $table->timestamp('service_start_at')->nullable()->after('immediate_service_requested');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Membership application commercial consent records require a controlled restore.');
    }
};
