<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('public_pages')->updateOrInsert(
            ['slug' => 'leadership'],
            [
                'template' => 'leadership',
                'navigation_label' => json_encode($this->copy('navigation'), JSON_UNESCAPED_UNICODE),
                'eyebrow' => json_encode($this->copy('eyebrow'), JSON_UNESCAPED_UNICODE),
                'title' => json_encode($this->copy('title'), JSON_UNESCAPED_UNICODE),
                'summary' => json_encode($this->copy('summary'), JSON_UNESCAPED_UNICODE),
                'body' => json_encode($this->copy('body'), JSON_UNESCAPED_UNICODE),
                'seo_title' => json_encode($this->copy('seo_title'), JSON_UNESCAPED_UNICODE),
                'seo_description' => json_encode($this->copy('seo_description'), JSON_UNESCAPED_UNICODE),
                'navigation_order' => 15,
                'show_in_navigation' => true,
                'status' => 'published',
                'revision' => 1,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('public_pages')->where('slug', 'leadership')->delete();
    }

    /** @return array{ar: string, en: string, fr: string} */
    private function copy(string $field): array
    {
        return [
            'navigation' => ['ar' => 'القيادة', 'en' => 'Leadership', 'fr' => 'Direction'],
            'eyebrow' => ['ar' => 'القيادة المؤسسية', 'en' => 'INSTITUTIONAL LEADERSHIP', 'fr' => 'DIRECTION INSTITUTIONNELLE'],
            'title' => ['ar' => 'ماستر شيف أحمد المعدراني', 'en' => 'Master Chef Ahmad Maadarani', 'fr' => 'Master Chef Ahmad Maadarani'],
            'summary' => ['ar' => 'الرئيس العام والمفوّض بالتوقيع للاتحاد الدولي للطهاة العرب المحترفين، يقود تطوير منظومة IUOAMC المهنية والرقمية.', 'en' => 'President General and Authorised Signatory of the International Union of Arab Master Chefs, leading the development of the IUOAMC professional and digital system.', 'fr' => 'Président général et signataire autorisé de l’Union internationale des maîtres cuisiniers arabes, il dirige le développement du système professionnel et numérique IUOAMC.'],
            'body' => ['ar' => "يجمع ماستر شيف أحمد المعدراني بين الخبرة المهنية في قطاع الضيافة والخلفية التقنية في هندسة الاتصالات. تخرّج في هندسة الاتصالات عام 2002 وفي إدارة الفندقة عام 2005، وحصل على لقب ماستر شيف عام 2015 ولقب محكّم دولي عام 2017.\n\nبصفته الرئيس العام والمفوّض بالتوقيع، يشرف على التوجه المؤسسي للاتحاد وتطوير برامجه وكياناته المتخصصة. وقاد اعتماد التحقق الإلكتروني للشهادات والسجلات عبر رموز QR منذ عام 2019، ضمن رؤية تربط التعليم المهني بالتوثيق الرقمي والحوكمة والتحقق العام.\n\nتتمثل مهمته القيادية في بناء منظومة مهنية متعددة الكيانات تحافظ على وضوح الاختصاص، وسلامة السجلات، وحقوق المستفيدين، والتطوير المستمر للمعايير في مجالات الطهي وفنون الطعام.", 'en' => "Master Chef Ahmad Maadarani combines professional hospitality experience with a technical background in telecommunications engineering. He graduated in telecommunications engineering in 2002 and hotel management in 2005, received the Master Chef title in 2015 and became an International Judge in 2017.\n\nAs President General and Authorised Signatory, he oversees the Union’s institutional direction and the development of its programmes and specialist entities. He led the adoption of electronic verification for credentials and records using QR codes from 2019, connecting professional education with digital documentation, governance and public verification.\n\nHis leadership mission is to build a multi-entity professional system that protects clear mandates, record integrity, recipient rights and the continuous development of culinary and gastronomy standards.", 'fr' => "Master Chef Ahmad Maadarani associe une expérience professionnelle dans l’hôtellerie à une formation technique en ingénierie des télécommunications. Diplômé en ingénierie des télécommunications en 2002 et en gestion hôtelière en 2005, il a obtenu le titre de Master Chef en 2015 et celui de juge international en 2017.\n\nEn qualité de président général et signataire autorisé, il supervise l’orientation institutionnelle de l’Union ainsi que le développement de ses programmes et entités spécialisées. Il a conduit l’adoption, dès 2019, de la vérification électronique des titres et registres par codes QR, reliant formation professionnelle, documentation numérique, gouvernance et vérification publique.\n\nSa mission est de développer un système professionnel multi-entités qui protège la clarté des mandats, l’intégrité des registres, les droits des bénéficiaires et l’évolution continue des normes culinaires et gastronomiques."],
            'seo_title' => ['ar' => 'أحمد المعدراني | الرئيس العام لـ IUOAMC', 'en' => 'Ahmad Maadarani | IUOAMC President General', 'fr' => 'Ahmad Maadarani | Président général d’IUOAMC'],
            'seo_description' => ['ar' => 'الملف القيادي الرسمي لماستر شيف أحمد المعدراني، الرئيس العام والمفوّض بالتوقيع للاتحاد الدولي للطهاة العرب المحترفين.', 'en' => 'Official leadership profile of Master Chef Ahmad Maadarani, President General and Authorised Signatory of IUOAMC.', 'fr' => 'Profil officiel de Master Chef Ahmad Maadarani, président général et signataire autorisé d’IUOAMC.'],
        ][$field];
    }
};
