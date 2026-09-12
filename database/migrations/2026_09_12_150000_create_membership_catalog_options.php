<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_professional_titles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name_ar', 160);
            $table->string('name_en', 160);
            $table->string('name_fr', 160);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('membership_subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('years')->unique();
            $table->unsignedInteger('fee_pence');
            $table->char('currency', 3)->default('GBP');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('membership_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name_ar', 160);
            $table->string('name_en', 160);
            $table->string('name_fr', 160);
            $table->boolean('requires_waiver_reason')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::table('membership_applications', function (Blueprint $table): void {
            $table->string('member_title_code', 100)->nullable()->after('membership_category_code');
            $table->string('requested_payment_method_code', 100)->nullable()->after('fee_currency');
            $table->text('fee_waiver_reason')->nullable()->after('requested_payment_method_code');
        });

        $now = now();
        DB::table('membership_professional_titles')->insert([
            ['code' => 'master-chef', 'name_ar' => 'ماستر شيف', 'name_en' => 'Master Chef', 'name_fr' => 'Maître Chef', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'executive-chef', 'name_ar' => 'شيف تنفيذي', 'name_en' => 'Executive Chef', 'name_fr' => 'Chef exécutif', 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'chef', 'name_ar' => 'شيف', 'name_en' => 'Chef', 'name_fr' => 'Chef', 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'pastry-chef', 'name_ar' => 'شيف حلويات', 'name_en' => 'Pastry Chef', 'name_fr' => 'Chef pâtissier', 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'culinary-judge', 'name_ar' => 'محكّم فنون طهي', 'name_en' => 'Culinary Judge', 'name_fr' => 'Juge culinaire', 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'culinary-trainer', 'name_ar' => 'مدرّب فنون طهي', 'name_en' => 'Culinary Trainer', 'name_fr' => 'Formateur culinaire', 'sort_order' => 60, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'culinary-researcher', 'name_ar' => 'باحث في فنون الطهي', 'name_en' => 'Culinary Researcher', 'name_fr' => 'Chercheur culinaire', 'sort_order' => 70, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'food-safety-specialist', 'name_ar' => 'اختصاصي سلامة غذاء', 'name_en' => 'Food Safety Specialist', 'name_fr' => 'Spécialiste de la sécurité alimentaire', 'sort_order' => 80, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'hospitality-professional', 'name_ar' => 'متخصص ضيافة', 'name_en' => 'Hospitality Professional', 'name_fr' => 'Professionnel de l’hospitalité', 'sort_order' => 90, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'gastronomy-specialist', 'name_ar' => 'اختصاصي فنون الذوّاقة', 'name_en' => 'Gastronomy Specialist', 'name_fr' => 'Spécialiste en gastronomie', 'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('membership_subscription_plans')->insert([
            ['years' => 1, 'fee_pence' => 25000, 'currency' => 'GBP', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['years' => 2, 'fee_pence' => 45000, 'currency' => 'GBP', 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['years' => 5, 'fee_pence' => 110000, 'currency' => 'GBP', 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['years' => 10, 'fee_pence' => 210000, 'currency' => 'GBP', 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('membership_payment_methods')->insert([
            ['code' => 'stripe', 'name_ar' => 'سترايب (بطاقة)', 'name_en' => 'Stripe (card)', 'name_fr' => 'Stripe (carte)', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'wise', 'name_ar' => 'وايز', 'name_en' => 'Wise', 'name_fr' => 'Wise', 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'cash', 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'name_fr' => 'Espèces', 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'bank-transfer', 'name_ar' => 'تحويل بنكي', 'name_en' => 'Bank transfer', 'name_fr' => 'Virement bancaire', 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'western-union', 'name_ar' => 'ويسترن يونيون', 'name_en' => 'Western Union', 'name_fr' => 'Western Union', 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'complimentary-request', 'name_ar' => 'طلب إعفاء من الرسوم (مجاني)', 'name_en' => 'Complimentary fee-waiver request', 'name_fr' => 'Demande d’exonération des frais', 'requires_waiver_reason' => true, 'sort_order' => 60, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table): void {
            $table->dropColumn(['member_title_code', 'requested_payment_method_code', 'fee_waiver_reason']);
        });
        Schema::dropIfExists('membership_payment_methods');
        Schema::dropIfExists('membership_subscription_plans');
        Schema::dropIfExists('membership_professional_titles');
    }
};
