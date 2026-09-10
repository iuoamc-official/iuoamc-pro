<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('template', 40)->default('standard');
            $table->json('navigation_label');
            $table->json('eyebrow')->nullable();
            $table->json('title');
            $table->json('summary');
            $table->json('body');
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->unsignedSmallInteger('navigation_order')->default(0)->index();
            $table->boolean('show_in_navigation')->default(true)->index();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'navigation_order']);
        });

        $now = now();
        $pages = [
            ['home', 'home', 0, false],
            ['about', 'standard', 10, true],
            ['governance', 'standard', 20, true],
            ['entities', 'entities', 30, true],
            ['programmes', 'standard', 40, true],
            ['contact', 'contact', 50, true],
        ];

        foreach ($pages as [$slug, $template, $order, $show]) {
            DB::table('public_pages')->insert([
                'slug' => $slug,
                'template' => $template,
                'navigation_label' => json_encode($this->copy($slug, 'navigation'), JSON_UNESCAPED_UNICODE),
                'eyebrow' => json_encode($this->copy($slug, 'eyebrow'), JSON_UNESCAPED_UNICODE),
                'title' => json_encode($this->copy($slug, 'title'), JSON_UNESCAPED_UNICODE),
                'summary' => json_encode($this->copy($slug, 'summary'), JSON_UNESCAPED_UNICODE),
                'body' => json_encode($this->copy($slug, 'body'), JSON_UNESCAPED_UNICODE),
                'seo_title' => json_encode($this->copy($slug, 'seo_title'), JSON_UNESCAPED_UNICODE),
                'seo_description' => json_encode($this->copy($slug, 'summary'), JSON_UNESCAPED_UNICODE),
                'navigation_order' => $order,
                'show_in_navigation' => $show,
                'status' => 'published',
                'revision' => 1,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $superAdminId = DB::table('roles')->where('slug', 'super-admin')->value('id');
        foreach ([
            ['Public Content Manage', 'public-content.manage', 'Create and edit IUOAMC public institutional content.'],
            ['Public Content Publish', 'public-content.publish', 'Publish or withdraw IUOAMC public institutional content.'],
        ] as [$name, $code, $description]) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => $name,
                'code' => $code,
                'module' => 'public-content',
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($superAdminId !== null) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $superAdminId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('code', ['public-content.manage', 'public-content.publish'])->pluck('id');
        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }

        Schema::dropIfExists('public_pages');
    }

    /** @return array{ar: string, en: string, fr: string} */
    private function copy(string $page, string $field): array
    {
        $copy = [
            'home' => [
                'navigation' => ['الرئيسية', 'Home', 'Accueil'],
                'eyebrow' => ['منظومة IUOAMC العالمية', 'IUOAMC GLOBAL SYSTEM', 'SYSTÈME MONDIAL IUOAMC'],
                'title' => ['منظومة مؤسسية عالمية للتعليم والاعتماد والتحقق والتحكيم المهني', 'A global institutional system for education, credentialing, verification and professional arbitration', 'Un système institutionnel mondial pour la formation, la certification, la vérification et l’arbitrage professionnel'],
                'summary' => ['نربط التعليم المهني بالتوثيق الرقمي والتحقق العام وحماية الملكية الفكرية ضمن منظومة واحدة قابلة للتدقيق.', 'We connect professional education, digital credentials, public verification and intellectual-property protection in one auditable system.', 'Nous réunissons formation professionnelle, titres numériques, vérification publique et protection de la propriété intellectuelle dans un système auditable.'],
                'body' => ['من التعليم إلى التحقق، صُممت منظومة IUOAMC لتمنح المؤسسات والمهنيين سجلاً واضحاً وآمناً وقابلاً للتحقق.', 'From education to verification, IUOAMC is designed to give institutions and professionals a clear, secure and verifiable record.', 'De la formation à la vérification, IUOAMC offre aux institutions et aux professionnels un dossier clair, sécurisé et vérifiable.'],
                'seo_title' => ['IUOAMC | المنظومة المؤسسية العالمية', 'IUOAMC | Global Institutional System', 'IUOAMC | Système institutionnel mondial'],
            ],
            'about' => [
                'navigation' => ['عن المنظومة', 'About', 'À propos'],
                'eyebrow' => ['الهوية المؤسسية', 'INSTITUTIONAL IDENTITY', 'IDENTITÉ INSTITUTIONNELLE'],
                'title' => ['اتحاد مهني بمنظومة رقمية موحدة', 'A professional union with a unified digital system', 'Une union professionnelle dotée d’un système numérique unifié'],
                'summary' => ['تجمع IUOAMC التعليم والتوثيق المهني والتحقق وحماية السجلات في بنية مؤسسية واحدة.', 'IUOAMC brings education, professional documentation, verification and record protection into one institutional architecture.', 'IUOAMC réunit formation, documentation professionnelle, vérification et protection des registres dans une architecture unique.'],
                'body' => ['تأسست الرؤية لتطوير المعايير المهنية وخدمة الطهاة والمؤسسات عبر أدوات تعليم وتوثيق حديثة. توسعت المنظومة تدريجياً من بناء الشبكة المهنية إلى التحقق الرقمي متعدد الكيانات وربط QR وNFC بالسجلات الموثقة.\n\nنعمل بمنهج الفصل بين إدارة العمليات الداخلية والخدمات العامة، وبمبدأ أقل إفصاح ممكن لحماية بيانات الأعضاء والمستفيدين.', 'The vision was established to advance professional standards and serve chefs and institutions through modern education and documentation tools. The system evolved from a professional network into multi-entity digital verification with QR and NFC linked to governed records.\n\nInternal operations are separated from public services, with data minimisation protecting members and recipients.', 'La vision vise à faire progresser les normes professionnelles et à servir chefs et institutions grâce à des outils modernes de formation et de documentation. Le système a évolué vers une vérification numérique multi-entités reliant QR et NFC à des registres gouvernés.\n\nLes opérations internes sont séparées des services publics et la minimisation des données protège membres et bénéficiaires.'],
                'seo_title' => ['عن IUOAMC', 'About IUOAMC', 'À propos d’IUOAMC'],
            ],
            'governance' => [
                'navigation' => ['الحوكمة', 'Governance', 'Gouvernance'],
                'eyebrow' => ['الثقة والمساءلة', 'TRUST & ACCOUNTABILITY', 'CONFIANCE ET RESPONSABILITÉ'],
                'title' => ['حوكمة يمكن تتبعها وليست مجرد وعود', 'Governance that can be traced, not merely claimed', 'Une gouvernance traçable, au-delà des déclarations'],
                'summary' => ['صلاحيات مفصّلة وسجلات تدقيق وسير اعتماد تفصل بين الإعداد والمراجعة والإصدار.', 'Granular permissions, audit records and approval workflows separate preparation, review and issuance.', 'Des autorisations granulaires, des journaux d’audit et des circuits d’approbation séparent préparation, contrôle et émission.'],
                'body' => ['تعتمد المنظومة أدواراً وصلاحيات محددة، وتحفظ الأحداث الحساسة في سجل تدقيق مترابط. تخضع الشهادات لدورة عمل واضحة قبل الإصدار، وتظل النسخ التاريخية قابلة للتتبع عند إنشاء طبعة مصححة.\n\nتلتزم صفحات التحقق العامة بمبدأ الخصوصية حسب التصميم، فلا تعرض بيانات شخصية تفوق ما يلزم لإثبات صحة السجل.', 'The system uses explicit roles and permissions and records sensitive events in a chained audit trail. Credentials follow a controlled lifecycle before issuance, while historical editions remain traceable when a correction is created.\n\nPublic verification follows privacy by design and exposes no more personal information than required to establish validity.', 'Le système applique des rôles et autorisations explicites et inscrit les événements sensibles dans une piste d’audit chaînée. Les titres suivent un cycle contrôlé avant émission et les versions historiques restent traçables.\n\nLa vérification publique applique la protection des données dès la conception.'],
                'seo_title' => ['حوكمة IUOAMC', 'IUOAMC Governance', 'Gouvernance IUOAMC'],
            ],
            'entities' => [
                'navigation' => ['الكيانات', 'Entities', 'Entités'],
                'eyebrow' => ['منظومة متعددة الكيانات', 'MULTI-ENTITY SYSTEM', 'SYSTÈME MULTI-ENTITÉS'],
                'title' => ['اختصاصات مستقلة ضمن معيار مؤسسي واحد', 'Distinct mandates under one institutional standard', 'Des mandats distincts sous une norme institutionnelle commune'],
                'summary' => ['تتكامل وحدات الاتحاد والأكاديمية والتحكيم والاعتماد وحماية الملكية الفكرية من دون خلط الاختصاصات.', 'The union, academy, arbitration, credentialing and intellectual-property functions work together without blurring their mandates.', 'Union, académie, arbitrage, certification et propriété intellectuelle coopèrent sans confondre leurs mandats.'],
                'body' => ['IUOAMC تقود المنظومة المهنية، وتدعمها وحدات متخصصة في التعليم والتدريب والتحكيم المهني وتوثيق البرامج وحماية العناوين والمحتوى. تظهر كل جهة في السياق الذي تمثله فقط، مع مصدر موحد للتحقق والحوكمة.', 'IUOAMC leads the professional system, supported by specialist functions for education, training, professional arbitration, programme documentation and title protection. Each entity appears only in the context it represents, backed by shared governance and verification.', 'IUOAMC dirige le système professionnel, soutenu par des fonctions spécialisées en formation, arbitrage professionnel, documentation des programmes et protection des titres. Chaque entité intervient uniquement dans son mandat.'],
                'seo_title' => ['كيانات IUOAMC', 'IUOAMC Entities', 'Entités IUOAMC'],
            ],
            'programmes' => [
                'navigation' => ['البرامج', 'Programmes', 'Programmes'],
                'eyebrow' => ['التعليم والتطوير المهني', 'EDUCATION & PROFESSIONAL DEVELOPMENT', 'FORMATION ET DÉVELOPPEMENT PROFESSIONNEL'],
                'title' => ['مسار واضح من التعلّم إلى السجل القابل للتحقق', 'A clear path from learning to a verifiable record', 'Un parcours clair de l’apprentissage au titre vérifiable'],
                'summary' => ['برامج مهنية ترتبط بمتطلبات موثقة وتقييم وسجل رقمي يمكن التحقق منه عند استيفاء شروط الإصدار.', 'Professional programmes connect documented requirements, assessment and a verifiable digital record when issuance conditions are met.', 'Les programmes professionnels relient exigences documentées, évaluation et titre numérique vérifiable lorsque les conditions sont remplies.'],
                'body' => ['تبدأ الدورة بتعريف البرنامج ومتطلباته، ثم تسجيل المشاركين ومراجعة البيانات والنتائج، ولا تنتقل إلى الإصدار إلا بعد اكتمال الموافقات. يمكن لكل شهادة صادرة أن تحمل تحقق QR وإثبات سلامة رقمي وربطاً ببرنامج موثق.', 'A programme begins with defined requirements, followed by participant intake and review of data and results. Issuance occurs only after approvals are complete. Each issued credential can include QR verification, digital integrity evidence and a documented programme link.', 'Chaque programme commence par des exigences définies, puis l’inscription et la vérification des données et résultats. L’émission n’intervient qu’après approbation. Chaque titre peut intégrer QR, preuve d’intégrité numérique et lien au programme documenté.'],
                'seo_title' => ['برامج IUOAMC المهنية', 'IUOAMC Professional Programmes', 'Programmes professionnels IUOAMC'],
            ],
            'contact' => [
                'navigation' => ['تواصل', 'Contact', 'Contact'],
                'eyebrow' => ['قناة مؤسسية', 'INSTITUTIONAL CONTACT', 'CONTACT INSTITUTIONNEL'],
                'title' => ['ابدأ من القناة الصحيحة', 'Start with the right channel', 'Commencez par le bon canal'],
                'summary' => ['للاستفسارات المؤسسية أو التحقق أو البرامج، حدّد موضوع الطلب لتوجيهه إلى الوحدة المختصة.', 'For institutional, verification or programme enquiries, identify the subject so it reaches the responsible unit.', 'Pour toute demande institutionnelle, de vérification ou de programme, indiquez le sujet afin de l’orienter vers l’unité compétente.'],
                'body' => ['قبل مشاركة أي بيانات شخصية، استخدم صفحة التحقق العامة للشهادات أو تواصل عبر القنوات الرسمية المنشورة. لن تطلب IUOAMC مفتاحاً خاصاً أو كلمة مرور أو رمز دخول ضمن رسالة دعم.', 'Before sharing personal data, use the public certificate verification service or an official published channel. IUOAMC will never request a private key, password or sign-in code in a support message.', 'Avant de partager des données personnelles, utilisez le service public de vérification ou un canal officiel publié. IUOAMC ne demandera jamais de clé privée, mot de passe ou code de connexion dans un message d’assistance.'],
                'seo_title' => ['تواصل مع IUOAMC', 'Contact IUOAMC', 'Contacter IUOAMC'],
            ],
        ];

        $values = $copy[$page];

        return [
            'ar' => $values[$field][0],
            'en' => $values[$field][1],
            'fr' => $values[$field][2],
        ];
    }
};
