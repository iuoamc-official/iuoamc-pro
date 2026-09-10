<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('public_pages')
            ->where('slug', 'leadership')
            ->update([
                'title' => json_encode($this->copy('title'), JSON_UNESCAPED_UNICODE),
                'summary' => json_encode($this->copy('summary'), JSON_UNESCAPED_UNICODE),
                'body' => json_encode($this->copy('body'), JSON_UNESCAPED_UNICODE),
                'seo_title' => json_encode($this->copy('seo_title'), JSON_UNESCAPED_UNICODE),
                'seo_description' => json_encode($this->copy('seo_description'), JSON_UNESCAPED_UNICODE),
                'revision' => DB::raw('revision + 1'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('public_pages')
            ->where('slug', 'leadership')
            ->update([
                'title' => json_encode($this->previousCopy('title'), JSON_UNESCAPED_UNICODE),
                'summary' => json_encode($this->previousCopy('summary'), JSON_UNESCAPED_UNICODE),
                'body' => json_encode($this->previousCopy('body'), JSON_UNESCAPED_UNICODE),
                'seo_title' => json_encode($this->previousCopy('seo_title'), JSON_UNESCAPED_UNICODE),
                'seo_description' => json_encode($this->previousCopy('seo_description'), JSON_UNESCAPED_UNICODE),
                'revision' => DB::raw('CASE WHEN revision > 1 THEN revision - 1 ELSE 1 END'),
                'updated_at' => now(),
            ]);
    }

    /** @return array{ar: string, en: string, fr: string} */
    private function copy(string $field): array
    {
        return [
            'title' => ['ar' => 'المهندس وماستر شيف أحمد المعدراني', 'en' => 'Engineer & Master Chef Ahmad Maadarani', 'fr' => 'Ingénieur & Master Chef Ahmad Maadarani'],
            'summary' => ['ar' => 'المؤسس والمقنّن المؤسسي لمجال التحكيم الدولي في فنون الطهي والذواقة، والرئيس العام والمفوّض بالتوقيع لـ IUOAMC، وصاحب أطر ونظريات وإصدارات علمية ومهنية في التذوق والتحكيم.', 'en' => 'Institutional founder and principal codifier of International Arbitration in Culinary Arts and Gastronomy, IUOAMC President General and Authorised Signatory, and author of professional theories, frameworks and scientific publications on tasting and arbitration.', 'fr' => 'Fondateur institutionnel et principal codificateur de l’arbitrage international dans les arts culinaires et la gastronomie, président général et signataire autorisé d’IUOAMC, auteur de théories, cadres professionnels et publications scientifiques sur la dégustation et l’arbitrage.'],
            'body' => ['ar' => "يقود المهندس وماستر شيف أحمد المعدراني مشروعاً مؤسسياً يهدف إلى تثبيت التحكيم الدولي في فنون الطهي والذواقة بوصفه مجالاً مهنياً مستقلاً له مصطلحاته وأطره ومعاييره وإجراءاته. ووفق السجل المؤسسي لمنظومة IUOAMC، فهو صاحب المبادرة الأولى لتقنين هذا المجال وبناء مرجعيته المهنية ضمن منظومة متعددة الكيانات تربط الاختصاص بالحوكمة والتوثيق والتحقق العام.\n\nبصفته مؤسساً ومقنّناً للمجال، طوّر أُطراً ونظريات مهنية تتناول التذوق والتحليل الحسي والحكم المهني وتوثيق النتائج وتسوية المنازعات المتخصصة. وتشمل إصداراته العلمية والمهنية عملاً منشوراً بعنوان «الإشارات الـ17 للنكهة – الإطار العلمي الكامل للتذوّق»، إلى جانب مواد مرجعية وتطويرية تخدم بناء المعرفة والمعايير في فنون الطهي والذواقة والتحكيم الدولي المتخصص.\n\nويتولى بصفته الرئيس العام والمفوّض بالتوقيع الإشراف على التوجه المؤسسي لـ IUOAMC وبرامجها وكياناتها المتخصصة، مع ترسيخ سلامة السجلات، وحماية حقوق المستفيدين، وربط الشهادات والملفات المهنية بالتحقق الرقمي العام.", 'en' => "Engineer and Master Chef Ahmad Maadarani leads an institutional programme to establish International Arbitration in Culinary Arts and Gastronomy as an independent professional field with defined terminology, frameworks, standards and procedures. According to the IUOAMC institutional record, he originated the first initiative to codify the field and build its professional reference system within a multi-entity structure connecting specialist mandates with governance, documentation and public verification.\n\nAs the field’s founder and principal codifier, he has developed professional theories and frameworks addressing tasting, sensory analysis, professional judgement, result documentation and specialised dispute resolution. His scientific and professional publications include “The 17 Signals of Flavour — The Complete Scientific Framework for Tasting”, alongside reference and development materials supporting knowledge and standards in culinary arts, gastronomy and international specialist arbitration.\n\nAs President General and Authorised Signatory, he oversees IUOAMC’s institutional direction, programmes and specialist entities, with a focus on record integrity, recipient rights and digitally verifiable professional credentials.", 'fr' => "L’ingénieur et Master Chef Ahmad Maadarani dirige un programme institutionnel visant à établir l’arbitrage international dans les arts culinaires et la gastronomie comme un domaine professionnel indépendant, doté d’une terminologie, de cadres, de normes et de procédures définis. Selon le registre institutionnel d’IUOAMC, il est à l’origine de la première initiative de codification de ce domaine et de la construction de son référentiel professionnel au sein d’un système multi-entités reliant compétences, gouvernance, documentation et vérification publique.\n\nFondateur et principal codificateur du domaine, il a développé des théories et cadres professionnels consacrés à la dégustation, à l’analyse sensorielle, au jugement professionnel, à la documentation des résultats et au règlement spécialisé des différends. Ses publications scientifiques et professionnelles comprennent « Les 17 signaux de la saveur — Cadre scientifique complet de la dégustation », ainsi que des documents de référence et de développement au service des connaissances et des normes culinaires, gastronomiques et de l’arbitrage international spécialisé.\n\nEn qualité de président général et signataire autorisé, il supervise l’orientation institutionnelle, les programmes et les entités spécialisées d’IUOAMC, en privilégiant l’intégrité des registres, les droits des bénéficiaires et la vérification numérique des titres professionnels."],
            'seo_title' => ['ar' => 'أحمد المعدراني | مؤسس ومقنّن التحكيم الدولي في فنون الطهي والذواقة', 'en' => 'Ahmad Maadarani | Founder & Codifier of International Arbitration in Culinary Arts and Gastronomy', 'fr' => 'Ahmad Maadarani | Fondateur et codificateur de l’arbitrage international culinaire et gastronomique'],
            'seo_description' => ['ar' => 'الملف الرسمي للمهندس وماستر شيف أحمد المعدراني، مؤسس ومقنّن مجال التحكيم الدولي في فنون الطهي والذواقة والرئيس العام لـ IUOAMC.', 'en' => 'Official profile of Engineer and Master Chef Ahmad Maadarani, founder and principal codifier of International Arbitration in Culinary Arts and Gastronomy and IUOAMC President General.', 'fr' => 'Profil officiel de l’ingénieur et Master Chef Ahmad Maadarani, fondateur et principal codificateur de l’arbitrage international dans les arts culinaires et la gastronomie et président général d’IUOAMC.'],
        ][$field];
    }

    /** @return array{ar: string, en: string, fr: string} */
    private function previousCopy(string $field): array
    {
        return [
            'title' => ['ar' => 'ماستر شيف أحمد المعدراني', 'en' => 'Master Chef Ahmad Maadarani', 'fr' => 'Master Chef Ahmad Maadarani'],
            'summary' => ['ar' => 'الرئيس العام والمفوّض بالتوقيع للاتحاد الدولي للطهاة العرب المحترفين، يقود تطوير منظومة IUOAMC المهنية والرقمية.', 'en' => 'President General and Authorised Signatory of the International Union of Arab Master Chefs, leading the development of the IUOAMC professional and digital system.', 'fr' => 'Président général et signataire autorisé de l’Union internationale des maîtres cuisiniers arabes, il dirige le développement du système professionnel et numérique IUOAMC.'],
            'body' => ['ar' => 'الملف القيادي السابق.', 'en' => 'Previous leadership profile.', 'fr' => 'Profil de direction précédent.'],
            'seo_title' => ['ar' => 'أحمد المعدراني | الرئيس العام لـ IUOAMC', 'en' => 'Ahmad Maadarani | IUOAMC President General', 'fr' => 'Ahmad Maadarani | Président général d’IUOAMC'],
            'seo_description' => ['ar' => 'الملف القيادي الرسمي لماستر شيف أحمد المعدراني، الرئيس العام والمفوّض بالتوقيع للاتحاد الدولي للطهاة العرب المحترفين.', 'en' => 'Official leadership profile of Master Chef Ahmad Maadarani, President General and Authorised Signatory of IUOAMC.', 'fr' => 'Profil officiel de Master Chef Ahmad Maadarani, président général et signataire autorisé d’IUOAMC.'],
        ][$field];
    }
};
