<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ($this->entities() as $slug => $copy) {
            DB::table('public_pages')->updateOrInsert(
                ['slug' => 'entity-'.$slug],
                [
                    'template' => 'entity',
                    'navigation_label' => json_encode($copy['name'], JSON_UNESCAPED_UNICODE),
                    'eyebrow' => json_encode($copy['eyebrow'], JSON_UNESCAPED_UNICODE),
                    'title' => json_encode($copy['name'], JSON_UNESCAPED_UNICODE),
                    'summary' => json_encode($copy['summary'], JSON_UNESCAPED_UNICODE),
                    'body' => json_encode($copy['body'], JSON_UNESCAPED_UNICODE),
                    'seo_title' => json_encode($copy['seo_title'], JSON_UNESCAPED_UNICODE),
                    'seo_description' => json_encode($copy['summary'], JSON_UNESCAPED_UNICODE),
                    'navigation_order' => 500,
                    'show_in_navigation' => false,
                    'status' => 'published',
                    'revision' => 1,
                    'published_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('public_pages')->whereIn('slug', array_map(
            static fn (string $slug): string => 'entity-'.$slug,
            array_keys($this->entities()),
        ))->delete();
    }

    /** @return array<string, array<string, array{ar: string, en: string, fr: string}>> */
    private function entities(): array
    {
        return [
            'iuoamc' => [
                'name' => ['ar' => 'الاتحاد الدولي للطهاة العرب المحترفين', 'en' => 'International Union of Arab Master Chefs', 'fr' => 'Union internationale des maîtres cuisiniers arabes'],
                'eyebrow' => ['ar' => 'الجهة الأم للمنظومة', 'en' => 'SYSTEM LEAD ENTITY', 'fr' => 'ENTITÉ PILOTE DU SYSTÈME'],
                'summary' => ['ar' => 'المظلة المهنية التي تنسّق التعليم والتطوير والتوثيق المؤسسي ضمن منظومة IUOAMC.', 'en' => 'The professional umbrella coordinating education, development and institutional documentation across the IUOAMC system.', 'fr' => 'L’organisation professionnelle qui coordonne la formation, le développement et la documentation institutionnelle du système IUOAMC.'],
                'body' => ['ar' => 'تقود IUOAMC البنية المهنية العامة للمنظومة، وتربط البرامج والجهات المتخصصة ضمن معايير حوكمة وتوثيق موحدة. تعرض هذه الصفحة بيانات التسجيل العامة المصرح بنشرها، بينما تظل بيانات الأعضاء والمستفيدين محمية.', 'en' => 'IUOAMC leads the system’s professional architecture and connects programmes and specialist entities under shared governance and documentation standards. This page presents authorised public registry details while member and recipient data remains protected.', 'fr' => 'IUOAMC dirige l’architecture professionnelle et relie les programmes et entités spécialisées selon des normes communes de gouvernance et de documentation. Cette page présente les données publiques autorisées, tandis que les données personnelles restent protégées.'],
                'seo_title' => ['ar' => 'IUOAMC | الاتحاد الدولي للطهاة العرب المحترفين', 'en' => 'IUOAMC | International Union of Arab Master Chefs', 'fr' => 'IUOAMC | Union internationale des maîtres cuisiniers arabes'],
            ],
            'icga' => [
                'name' => ['ar' => 'الأكاديمية الدولية للتحكيم في الطهي وفنون الطعام', 'en' => 'International Culinary & Gastronomy Arbitration', 'fr' => 'Arbitrage international culinaire et gastronomique'],
                'eyebrow' => ['ar' => 'التحكيم المهني', 'en' => 'PROFESSIONAL ARBITRATION', 'fr' => 'ARBITRAGE PROFESSIONNEL'],
                'summary' => ['ar' => 'جهة متخصصة في التحكيم المهني في مجالات الطهي وفنون الطعام ضمن اختصاص مؤسسي واضح.', 'en' => 'A specialist entity for professional arbitration in culinary and gastronomy fields under an explicit institutional mandate.', 'fr' => 'Une entité spécialisée dans l’arbitrage professionnel culinaire et gastronomique selon un mandat institutionnel explicite.'],
                'body' => ['ar' => 'تدعم ICGA® التقييم والتحكيم المهني وتوثيق المراجع ذات الصلة بقطاع الطهي وفنون الطعام. تظهر العلامة المسجلة وبياناتها الرسمية هنا بوصفها هوية مستقلة داخل المنظومة.', 'en' => 'ICGA® supports professional assessment, arbitration and related documentation in the culinary and gastronomy sector. Its registered mark and official registry details are presented here as an independent identity within the system.', 'fr' => 'ICGA® soutient l’évaluation, l’arbitrage professionnel et la documentation associée dans le secteur culinaire et gastronomique. Sa marque enregistrée et ses références officielles sont présentées ici comme une identité indépendante.'],
                'seo_title' => ['ar' => 'ICGA® | التحكيم المهني في الطهي', 'en' => 'ICGA® | Culinary & Gastronomy Arbitration', 'fr' => 'ICGA® | Arbitrage culinaire et gastronomique'],
            ],
            'wsa-ca' => [
                'name' => ['ar' => 'السلطة العالمية العليا للتحكيم في الطهي', 'en' => 'World Supreme Authority for Culinary Arbitration', 'fr' => 'Autorité mondiale suprême pour l’arbitrage culinaire'],
                'eyebrow' => ['ar' => 'سلطة التحكيم', 'en' => 'ARBITRATION AUTHORITY', 'fr' => 'AUTORITÉ D’ARBITRAGE'],
                'summary' => ['ar' => 'هوية مؤسسية متخصصة في مرجعية التحكيم المهني في قطاع الطهي.', 'en' => 'An institutional identity specialised in professional culinary arbitration reference.', 'fr' => 'Une identité institutionnelle spécialisée dans la référence en arbitrage culinaire professionnel.'],
                'body' => ['ar' => 'تمثل WSA-CA اختصاص التحكيم المهني داخل البنية متعددة الكيانات. يحافظ عرضها المستقل على وضوح الدور والهوية والسجل العام.', 'en' => 'WSA-CA represents the professional arbitration mandate within the multi-entity architecture. Its independent presentation keeps the role, identity and public registry clear.', 'fr' => 'WSA-CA représente le mandat d’arbitrage professionnel dans l’architecture multi-entités. Sa présentation indépendante clarifie son rôle, son identité et son registre public.'],
                'seo_title' => ['ar' => 'WSA-CA | سلطة التحكيم في الطهي', 'en' => 'WSA-CA | Culinary Arbitration Authority', 'fr' => 'WSA-CA | Autorité d’arbitrage culinaire'],
            ],
            'wsact' => [
                'name' => ['ar' => 'السلطة العالمية العليا للألقاب المهنية في الطهي', 'en' => 'World Supreme Authority for Culinary Titles', 'fr' => 'Autorité mondiale suprême des titres culinaires'],
                'eyebrow' => ['ar' => 'الألقاب المهنية', 'en' => 'PROFESSIONAL TITLES', 'fr' => 'TITRES PROFESSIONNELS'],
                'summary' => ['ar' => 'جهة متخصصة في مرجعية الألقاب المهنية المرتبطة بقطاع الطهي.', 'en' => 'A specialist identity for professional title reference in the culinary sector.', 'fr' => 'Une identité spécialisée dans la référence des titres professionnels du secteur culinaire.'],
                'body' => ['ar' => 'تُعرض WSACT كاختصاص مستقل للألقاب المهنية، مع فصل واضح بينها وبين التعليم وإصدار الشهادات والتحكيم وحماية الملكية الفكرية.', 'en' => 'WSACT is presented as an independent professional-title mandate, clearly separated from education, credential issuance, arbitration and intellectual-property protection.', 'fr' => 'WSACT est présentée comme un mandat indépendant relatif aux titres professionnels, distinct de la formation, de la certification, de l’arbitrage et de la propriété intellectuelle.'],
                'seo_title' => ['ar' => 'WSACT | سلطة الألقاب المهنية', 'en' => 'WSACT | Culinary Titles Authority', 'fr' => 'WSACT | Autorité des titres culinaires'],
            ],
            'iuoamc-tv' => [
                'name' => ['ar' => 'IUOAMC TV | المنصة الإعلامية', 'en' => 'IUOAMC TV | Media Division', 'fr' => 'IUOAMC TV | Division médias'],
                'eyebrow' => ['ar' => 'الإعلام والمعرفة', 'en' => 'MEDIA & KNOWLEDGE', 'fr' => 'MÉDIAS ET CONNAISSANCE'],
                'summary' => ['ar' => 'الذراع الإعلامية المخصصة لنشر المعرفة والأنشطة والمحتوى المهني للمنظومة.', 'en' => 'The media division dedicated to communicating the system’s knowledge, activities and professional content.', 'fr' => 'La division médias dédiée à la diffusion des connaissances, activités et contenus professionnels du système.'],
                'body' => ['ar' => 'تخدم IUOAMC TV الحضور الإعلامي والتثقيفي للمنظومة. ترتبط بالجهة الأم مع احتفاظها بهوية بصرية ووظيفة تحريرية واضحتين.', 'en' => 'IUOAMC TV serves the system’s media and educational presence. It is connected to the parent entity while retaining a clear visual identity and editorial function.', 'fr' => 'IUOAMC TV assure la présence médiatique et éducative du système. Elle est rattachée à l’entité mère tout en conservant une identité visuelle et une fonction éditoriale claires.'],
                'seo_title' => ['ar' => 'IUOAMC TV | المنصة الإعلامية', 'en' => 'IUOAMC TV | Media Division', 'fr' => 'IUOAMC TV | Division médias'],
            ],
            'wicp' => [
                'name' => ['ar' => 'المركز العالمي لحماية الملكية الفكرية', 'en' => 'World Centre for Intellectual Protection', 'fr' => 'Centre mondial de protection intellectuelle'],
                'eyebrow' => ['ar' => 'التوثيق والحماية', 'en' => 'DOCUMENTATION & PROTECTION', 'fr' => 'DOCUMENTATION ET PROTECTION'],
                'summary' => ['ar' => 'جهة متخصصة في توثيق البرامج والمصنفات والمراجع المرتبطة بالملكية الفكرية.', 'en' => 'A specialist entity for documenting programmes, works and intellectual-property references.', 'fr' => 'Une entité spécialisée dans la documentation des programmes, œuvres et références de propriété intellectuelle.'],
                'body' => ['ar' => 'يوفر WICP مساراً منظماً لتوثيق البرامج والمصنفات وربطها بمراجع قابلة للتتبع. لا يحل التوثيق محل الحقوق أو التسجيلات الحكومية، بل يقدم سجلاً مؤسسياً واضحاً ضمن نطاق الخدمة.', 'en' => 'WICP provides a governed path to document programmes and works and link them to traceable references. This documentation does not replace statutory rights or government registration; it provides a clear institutional record within the service scope.', 'fr' => 'WICP fournit un parcours gouverné pour documenter programmes et œuvres et les relier à des références traçables. Cette documentation ne remplace ni les droits légaux ni les enregistrements publics ; elle constitue un registre institutionnel dans le périmètre du service.'],
                'seo_title' => ['ar' => 'WICP | حماية الملكية الفكرية', 'en' => 'WICP | Intellectual Protection', 'fr' => 'WICP | Protection intellectuelle'],
            ],
        ];
    }
};
