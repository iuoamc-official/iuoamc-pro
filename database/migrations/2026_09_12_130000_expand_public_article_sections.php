<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private array $addedSlugs = [
        'recipes-techniques',
        'chef-success-stories',
        'competitions-achievements',
        'culinary-heritage',
        'training-career',
        'health-nutrition',
    ];

    public function up(): void
    {
        $now = now();
        $actorId = DB::table('users')->orderBy('id')->value('id');

        foreach ($this->sections() as $section) {
            DB::table('content_sections')->updateOrInsert(
                ['slug' => $section['slug']],
                [
                    'name' => json_encode($section['name'], JSON_UNESCAPED_UNICODE),
                    'description' => json_encode($section['description'], JSON_UNESCAPED_UNICODE),
                    'status' => 'active',
                    'sort_order' => $section['sort_order'],
                    'updated_by' => $actorId,
                    'updated_at' => $now,
                ] + (in_array($section['slug'], $this->addedSlugs, true) ? [
                    'created_by' => $actorId,
                    'created_at' => $now,
                ] : [])
            );
        }
    }

    public function down(): void
    {
        DB::table('content_sections')->whereIn('slug', $this->addedSlugs)->delete();
        foreach ([
            ['news', 10, ['ar' => 'الأخبار والإعلانات', 'en' => 'News & Announcements', 'fr' => 'Actualités et annonces']],
            ['culinary-knowledge', 20, ['ar' => 'معرفة الطهي', 'en' => 'Culinary Knowledge', 'fr' => 'Savoirs culinaires']],
            ['restaurant-insights', 30, ['ar' => 'رؤى المطاعم', 'en' => 'Restaurant Insights', 'fr' => 'Regards sur la restauration']],
            ['interviews', 40, ['ar' => 'حوارات وشخصيات', 'en' => 'Interviews & People', 'fr' => 'Entretiens et personnalités']],
            ['events', 50, ['ar' => 'فعاليات ومؤتمرات', 'en' => 'Events & Conferences', 'fr' => 'Événements et conférences']],
            ['opinions', 60, ['ar' => 'آراء وتحليلات', 'en' => 'Opinion & Analysis', 'fr' => 'Opinions et analyses']],
        ] as [$slug, $sortOrder, $name]) {
            DB::table('content_sections')->where('slug', $slug)->update([
                'name' => json_encode($name, JSON_UNESCAPED_UNICODE),
                'description' => json_encode($name, JSON_UNESCAPED_UNICODE),
                'sort_order' => $sortOrder,
                'updated_at' => now(),
            ]);
        }
    }

    /** @return list<array{slug:string,sort_order:int,name:array<string,string>,description:array<string,string>}> */
    private function sections(): array
    {
        return [
            [
                'slug' => 'news', 'sort_order' => 10,
                'name' => ['ar' => 'أخبار الاتحاد والإعلانات', 'en' => 'Union News & Announcements', 'fr' => 'Actualités et annonces de l’Union'],
                'description' => [
                    'ar' => 'الأخبار الرسمية، القرارات، الشراكات، الإطلاقات والتحديثات المؤسسية الصادرة عن IUOAMC وكياناتها.',
                    'en' => 'Official news, decisions, partnerships, launches and institutional updates from IUOAMC and its entities.',
                    'fr' => 'Actualités officielles, décisions, partenariats, lancements et mises à jour institutionnelles de l’IUOAMC et de ses entités.',
                ],
            ],
            [
                'slug' => 'recipes-techniques', 'sort_order' => 20,
                'name' => ['ar' => 'وصفات وتقنيات الطهي', 'en' => 'Recipes & Culinary Techniques', 'fr' => 'Recettes et techniques culinaires'],
                'description' => [
                    'ar' => 'وصفات موثقة، تقنيات احترافية، طرق تحضير، ضبط الجودة وأسرار التنفيذ المقدمة من طهاة مختصين.',
                    'en' => 'Documented recipes, professional techniques, preparation methods, quality controls and expert execution insights.',
                    'fr' => 'Recettes documentées, techniques professionnelles, méthodes de préparation, contrôle qualité et conseils d’experts.',
                ],
            ],
            [
                'slug' => 'chef-success-stories', 'sort_order' => 30,
                'name' => ['ar' => 'قصص نجاح الطهاة', 'en' => 'Chef Success Stories', 'fr' => 'Parcours de réussite des chefs'],
                'description' => [
                    'ar' => 'قصص مهنية ملهمة توثق رحلة الطهاة، التحديات، التحولات، الإنجازات والدروس المستفادة.',
                    'en' => 'Inspiring professional stories documenting chefs’ journeys, challenges, turning points, achievements and lessons learned.',
                    'fr' => 'Récits professionnels inspirants sur les parcours, défis, tournants, réussites et enseignements des chefs.',
                ],
            ],
            [
                'slug' => 'competitions-achievements', 'sort_order' => 40,
                'name' => ['ar' => 'المسابقات والإنجازات', 'en' => 'Competitions & Achievements', 'fr' => 'Concours et distinctions'],
                'description' => [
                    'ar' => 'تغطية المسابقات المهنية والجوائز والإنجازات والنتائج الموثقة للطهاة والفرق والمؤسسات.',
                    'en' => 'Coverage of professional competitions, awards, achievements and verified results for chefs, teams and institutions.',
                    'fr' => 'Couverture des concours professionnels, prix, distinctions et résultats vérifiés des chefs, équipes et institutions.',
                ],
            ],
            [
                'slug' => 'culinary-knowledge', 'sort_order' => 50,
                'name' => ['ar' => 'معرفة الطهي والذواقة', 'en' => 'Culinary & Gastronomy Knowledge', 'fr' => 'Savoirs culinaires et gastronomiques'],
                'description' => [
                    'ar' => 'محتوى معرفي مبسط في علوم الطهي والذواقة والمكونات والمذاق والتحليل الحسي.',
                    'en' => 'Accessible knowledge covering culinary arts, gastronomy, ingredients, flavour and sensory analysis.',
                    'fr' => 'Connaissances accessibles sur les arts culinaires, la gastronomie, les ingrédients, les saveurs et l’analyse sensorielle.',
                ],
            ],
            [
                'slug' => 'culinary-heritage', 'sort_order' => 60,
                'name' => ['ar' => 'التراث والهوية الطهوية', 'en' => 'Culinary Heritage & Identity', 'fr' => 'Patrimoine et identité culinaires'],
                'description' => [
                    'ar' => 'توثيق الأطباق والممارسات والمكونات والحكايات التي تشكل الذاكرة والهوية الطهوية العربية والعالمية.',
                    'en' => 'Documentation of dishes, practices, ingredients and stories shaping Arab and global culinary memory and identity.',
                    'fr' => 'Documentation des plats, pratiques, ingrédients et récits qui façonnent la mémoire et l’identité culinaires arabes et mondiales.',
                ],
            ],
            [
                'slug' => 'restaurant-insights', 'sort_order' => 70,
                'name' => ['ar' => 'إدارة المطاعم والضيافة', 'en' => 'Restaurant & Hospitality Insights', 'fr' => 'Restauration et hospitalité'],
                'description' => [
                    'ar' => 'رؤى عملية في التشغيل، تجربة الضيف، تطوير القوائم، سلامة الغذاء، الجودة والاستدامة المالية.',
                    'en' => 'Practical insights into operations, guest experience, menu development, food safety, quality and financial sustainability.',
                    'fr' => 'Conseils pratiques sur les opérations, l’expérience client, les menus, la sécurité alimentaire, la qualité et la pérennité financière.',
                ],
            ],
            [
                'slug' => 'training-career', 'sort_order' => 80,
                'name' => ['ar' => 'التدريب والمسار المهني', 'en' => 'Training & Culinary Careers', 'fr' => 'Formation et carrières culinaires'],
                'description' => [
                    'ar' => 'إرشاد مهني وفرص تعلم وتطوير مهارات ومسارات تقدم للطهاة والعاملين في الضيافة.',
                    'en' => 'Career guidance, learning opportunities, skill development and progression pathways for culinary and hospitality professionals.',
                    'fr' => 'Orientation, possibilités de formation, développement des compétences et parcours d’évolution en cuisine et hospitalité.',
                ],
            ],
            [
                'slug' => 'interviews', 'sort_order' => 90,
                'name' => ['ar' => 'حوارات وشخصيات', 'en' => 'Interviews & Culinary Leaders', 'fr' => 'Entretiens et personnalités culinaires'],
                'description' => [
                    'ar' => 'حوارات معمقة مع الطهاة والخبراء والقادة والمؤثرين في صناعة الطهي والضيافة.',
                    'en' => 'In-depth conversations with chefs, experts, leaders and changemakers across culinary arts and hospitality.',
                    'fr' => 'Entretiens approfondis avec des chefs, experts, dirigeants et acteurs du changement culinaire et hôtelier.',
                ],
            ],
            [
                'slug' => 'health-nutrition', 'sort_order' => 100,
                'name' => ['ar' => 'الصحة والتغذية', 'en' => 'Health & Nutrition', 'fr' => 'Santé et nutrition'],
                'description' => [
                    'ar' => 'محتوى توعوي منضبط عن التغذية، الحساسية، التوازن الغذائي والطهي المسؤول دون ادعاءات طبية غير موثقة.',
                    'en' => 'Responsible educational content on nutrition, allergens, dietary balance and cooking without unsupported medical claims.',
                    'fr' => 'Contenu éducatif responsable sur la nutrition, les allergènes, l’équilibre alimentaire et la cuisine, sans allégations médicales non fondées.',
                ],
            ],
            [
                'slug' => 'events', 'sort_order' => 110,
                'name' => ['ar' => 'فعاليات ومؤتمرات', 'en' => 'Events & Conferences', 'fr' => 'Événements et conférences'],
                'description' => [
                    'ar' => 'الإعلانات والتغطيات والتقارير الخاصة بالفعاليات والمؤتمرات والمعارض والملتقيات المهنية.',
                    'en' => 'Announcements, coverage and reports from events, conferences, exhibitions and professional gatherings.',
                    'fr' => 'Annonces, couvertures et comptes rendus d’événements, conférences, salons et rencontres professionnelles.',
                ],
            ],
            [
                'slug' => 'opinions', 'sort_order' => 120,
                'name' => ['ar' => 'آراء وتحليلات مهنية', 'en' => 'Professional Opinion & Analysis', 'fr' => 'Opinions et analyses professionnelles'],
                'description' => [
                    'ar' => 'وجهات نظر وتحليلات موقعة تعبر عن أصحابها وتبقى مصنفة بوضوح كمحتوى تحريري غير محكّم.',
                    'en' => 'Signed perspectives and analysis representing their authors, clearly identified as non-peer-reviewed editorial content.',
                    'fr' => 'Points de vue et analyses signés engageant leurs auteurs, clairement identifiés comme contenus éditoriaux non évalués.',
                ],
            ],
        ];
    }
};
