<?php

declare(strict_types=1);

namespace App\Services;

final class PdrtseCertificateDefinition
{
    public const CODE = 'PDRTSE';

    public const NUMBER_PREFIX = 'ICGA-PDRTSE';

    public const DESIGNATION = 'Certified Restaurant Tasting and Sensory Evaluation Specialist';

    public const TITLE_EN = 'Professional Diploma in Restaurant Tasting and Sensory Evaluation';

    public const DISCLAIMER_EN = 'Professional credential - Not an academic degree or regulated qualification.';

    public const STATEMENT_EN = 'This certificate is awarded to [RECIPIENT NAME], who has successfully completed the four progressive levels of the 20-hour professional training programme delivered over one month and has passed the final professional assessment. Accordingly, the holder is awarded the professional designation: Certified Restaurant Tasting and Sensory Evaluation Specialist.';

    /** @return array<string, mixed> */
    public function typeProfile(int $organizationId): array
    {
        return [
            'organization_id' => $organizationId,
            'code' => self::CODE,
            'number_prefix' => self::NUMBER_PREFIX,
            'name_ar' => 'الدبلوم المهني في تذوق المطاعم والتقييم الحسي',
            'name_en' => self::TITLE_EN,
            'name_fr' => 'Diplôme professionnel en dégustation de restaurant et évaluation sensorielle',
            'category' => 'diploma',
            'layout' => 'diploma',
            'title_ar' => 'الدبلوم المهني في تذوق المطاعم والتقييم الحسي',
            'title_en' => self::TITLE_EN,
            'title_fr' => 'Diplôme professionnel en dégustation de restaurant et évaluation sensorielle',
            'statement_ar' => 'تُمنح هذه الشهادة إلى [RECIPIENT NAME]، بعد أن أتم بنجاح المستويات الأربعة المتتابعة للبرنامج التدريبي المهني البالغ 20 ساعة والمنفذ على مدى شهر واحد، واجتاز التقييم المهني النهائي. وبناءً عليه، يُمنح حاملها المسمّى المهني: أخصائي معتمد في تذوق المطاعم والتقييم الحسي.'
                ."\n".'وثيقة مهنية - ليست درجة أكاديمية أو مؤهلًا منظّمًا.',
            'statement_en' => $this->englishStatement(),
            'statement_fr' => 'Ce certificat est décerné à [RECIPIENT NAME], qui a suivi avec succès les quatre niveaux progressifs du programme de formation professionnelle de 20 heures dispensé sur un mois et a réussi l’évaluation professionnelle finale. En conséquence, le titulaire reçoit le titre professionnel de Certified Restaurant Tasting and Sensory Evaluation Specialist.'
                ."\n".'Titre professionnel - ne constitue ni un diplôme universitaire ni une qualification réglementée.',
            'signatory_name' => 'Master Chef Ahmad Maadarani',
            'signatory_title' => 'President General & Authorised Signatory',
            'active' => true,
        ];
    }

    public function englishStatement(): string
    {
        return self::STATEMENT_EN."\n".self::DISCLAIMER_EN;
    }
}
