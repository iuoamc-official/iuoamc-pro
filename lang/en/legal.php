<?php

return [
    'effective_date' => 'Effective date:',
    'fees_title' => 'Membership term fees',
    'fees_intro' => 'These are application and membership-service fees for the selected term. Payment or submission does not itself grant membership.',
    'term' => 'Requested term',
    'total_fee' => 'Total fee',
    'years' => '{1} One year|[2,*] :count years',
    'allocation_title' => 'Fee allocation by service stage',
    'allocation_intro' => 'The percentages show the portion allocated to each stage. A refund deduction is made only for stages actually performed and documented external costs permitted by law. If a discount was granted, each stage is calculated from the published standard fee before discount and then deducted from the amount actually paid.',
    'stage' => 'Service stage',
    'percentage' => 'Percentage of fee',
    'allocations' => [
        'file_review' => 'File opening and initial review',
        'identity_verification' => 'Identity and document verification',
        'eligibility_assessment' => 'Professional eligibility assessment',
        'registration' => 'Registration and membership number after approval',
        'credentials' => 'Card and membership certificate production after issue',
        'account_support' => 'Account service and communications after activation',
    ],
    'back_to_application' => 'Back to membership application',
    'documents' => [
        'membership_terms' => [
            'title' => 'Membership Terms, Fees and Refund Policy',
            'intro' => 'Terms governing an IUOAMC membership application, review, approval and credentials.',
            'sections' => [
                ['title' => '1. Contracting entity and scope', 'paragraphs' => [
                    'INTERNATIONAL UNION OF ARAB MASTER CHEFS LTD, UK company number 16649793 (IUOAMC), administers membership applications covered by these terms. These terms apply to online applications unless a specific written offer states otherwise.',
                    'These terms supplement and do not exclude or restrict an applicant’s mandatory statutory rights.',
                ]],
                ['title' => '2. Membership categories and eligibility', 'paragraphs' => [
                    'An applicant may request General, Professional, Elite or International Expert Membership. The selection is a requested category, not an acquired status, and identity, evidence and experience remain subject to review.',
                    'If another category is more suitable, IUOAMC will make a revised offer and will not impose a material change without the applicant’s agreement. Honorary Membership is granted only by a separate administrative decision and cannot be purchased or requested through the public form.',
                ]],
                ['title' => '3. No automatic grant of membership', 'paragraphs' => [
                    'Creating an account, submitting an application or paying a fee does not create membership, a title, accreditation, licence or authority to represent IUOAMC. Membership begins only after written approval, issue of a membership number and entry of the validity period in the official register.',
                    'IUOAMC may request further evidence and may accept or refuse an application under its applicable eligibility, integrity and governance criteria, with the decision recorded.',
                ]],
                ['title' => '4. Payment and start of processing', 'paragraphs' => [
                    'The complete price is shown before payment. No undisclosed mandatory charge is added. An applicant may expressly request immediate review during the cancellation period or choose processing to begin after that period.',
                    'Where immediate performance is requested, the applicant agrees to pay a proportionate amount for services actually supplied before cancellation. The 35% allocation is not a penalty: it becomes non-refundable consideration for file opening and initial review only after that stage starts and is performed, and only to the extent permitted by law.',
                ]],
                ['title' => '5. Distance-contract cancellation rights', 'paragraphs' => [
                    'Where the applicant is a consumer and distance-contract rules apply, the applicant will normally have 14 days from conclusion of the service contract to cancel without giving a reason. Any mandatory rights in the applicant’s country of residence continue to apply.',
                    'If the applicant expressly requests performance during that period and then cancels, IUOAMC may retain a proportionate amount for work actually performed. If the service is fully performed following an express request and acknowledgement that cancellation rights will be lost on completion, the right may end where the law permits.',
                ]],
                ['title' => '6. Refused applications and refunds', 'paragraphs' => [
                    'If an application is refused, the unearned balance is returned to the original payment method within 14 days after the final decision or receipt of information needed to make the refund, whichever is later. The applicant receives a statement of completed stages and deductions.',
                ], 'items' => [
                    'Before any service starts: a full refund, subject only to a valid and disclosed legal exception.',
                    'After file opening and initial review are completed: 35% of the fee may be retained.',
                    'Verification and assessment stages are deducted only when actually performed.',
                    'Registration, credential production and post-activation support are not deducted when an application is refused before those stages occur.',
                    'Actual, documented and non-recoverable third-party costs may be deducted only when disclosed and lawful; total deductions can never exceed the amount paid.',
                    'Where a discount applies, completed-stage percentages are calculated from the standard fee before discount. The refund is the amount actually paid less completed stages and permitted external costs; it cannot be negative or exceed the amount paid.',
                ]],
                ['title' => '7. Withdrawal and inaccurate information', 'paragraphs' => [
                    'If an applicant withdraws after work begins, the same completed-stage calculation applies. If an application cannot be assessed because information is missing, a reasonable opportunity to complete it will be given before closure.',
                    'An application may be refused or membership revoked for forged or materially misleading information. This does not remove any refund for an unperformed service or any mandatory consumer right.',
                ]],
                ['title' => '8. Term and renewal', 'paragraphs' => [
                    'The approved term starts on the date recorded in the approval decision, not the application or payment date. Renewal is not automatic unless separate express consent confirms the price and term. Expiry ends future benefits but does not erase the historical verification record.',
                ]],
                ['title' => '9. Conduct, suspension and revocation', 'paragraphs' => [
                    'Members must keep their details accurate and must not misuse names, marks, cards or certificates or claim authority not granted. Membership may be suspended or revoked following a fair review and recorded reason, subject to statutory rights and the portion of the membership term already used.',
                ]],
                ['title' => '10. Complaints and governing law', 'paragraphs' => [
                    'Cancellation requests and complaints should be sent to info@iuoamc.uk with the application reference. These terms are governed by the law of England and Wales, without depriving consumers of mandatory protection in their country of residence. Courts have jurisdiction as determined by applicable law.',
                ]],
            ],
        ],
        'membership_privacy' => [
            'title' => 'Membership Application Privacy Notice',
            'intro' => 'How membership applicant data is collected, used, protected and retained.',
            'sections' => [
                ['title' => '1. Data controller', 'paragraphs' => ['INTERNATIONAL UNION OF ARAB MASTER CHEFS LTD, company number 16649793, is the controller for IUOAMC membership applications. Its ICO data-protection registration reference is ZB971358. Contact: info@iuoamc.uk.']],
                ['title' => '2. Data collected', 'items' => ['Account, contact, bilingual name and professional-profile information.', 'Date of birth, nationality, address, residence and identity-document details.', 'Qualifications, experience, portrait photograph, application information and correspondence.', 'Selected fee plan, payments, consent timestamps, security events and audit records.']],
                ['title' => '3. Purposes and legal bases', 'paragraphs' => ['Data is used to take requested pre-contract steps, perform an approved membership contract, comply with legal duties, and prevent fraud and protect the register under legitimate interests. Consent is used where the law requires it.', 'The portrait is not used for automated facial recognition, and no membership decision is made solely by automated processing.']],
                ['title' => '4. Access and sharing', 'paragraphs' => ['Access is limited to the account holder and authorised personnel according to role. The minimum necessary data may be shared with contracted hosting, mail, payment, signature and verification providers, advisers and public authorities where a lawful basis and suitable safeguards exist. Personal data is not sold.']],
                ['title' => '5. International transfers and security', 'paragraphs' => ['Where operating the service requires a transfer outside the UK, a lawful transfer mechanism and suitable safeguards are used. Identity data and photographs are held in private storage with access controls, audit records and encryption where appropriate; no electronic method can be guaranteed entirely risk-free.']],
                ['title' => '6. Retention', 'paragraphs' => ['Application data is kept during review and membership, then only as long as needed for legal and financial duties, dispute handling and register integrity. Data is deleted or anonymised when no lawful or operational need remains, while the minimum issue and audit record may remain to prevent fraud.']],
                ['title' => '7. Your rights', 'paragraphs' => ['Subject to applicable law, you may request access, correction, erasure, restriction, objection or portability and withdraw consent where consent is the basis, without retrospective effect. You may complain to the ICO or the competent local data-protection authority.']],
                ['title' => '8. Contact', 'paragraphs' => ['Send privacy requests to info@iuoamc.uk from the email linked to the account. Appropriate identity verification may be required before data is disclosed or changed.']],
            ],
        ],
    ],
];
