<?php

class Seeder
{
    private mysqli $connection;

    // ===== SETUP =====
    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    // ===== DEFAULT CONTENT SEEDS =====
    public function seedSiteSettings(): void
    {
        $c      = $this->connection;
        $result = $c->query("SELECT COUNT(*) AS n FROM `site_settings`");
        $row    = $result ? $result->fetch_assoc() : null;
        if ($row && (int) $row['n'] > 0) {
            return;
        }

        $defaults = [
            'banner_title' => 'Benguet State University - Institutional Animal Care and Use Committee',
            'about_paragraph_1' => 'The Institutional Animal Care and Use Committee (IACUC) is mandated with the responsibility for ensuring adherence to appropriate university, national, and international policies and regulations. The IACUC, under the Office of the Research and Extension, specifically the Cordillera Center for Animal Research and Development (CCARD), serves as the oversight committee in the care and use of live animals in research and teaching activities in Benguet State University (BSU).',
            'about_paragraph_2' => 'IACUC protocols must be reviewed by the IACUC and endorsed for the issuance of an animal research clearance (ARC) by the Bureau of Animal Industry (BAI).',
        ];

        $stmt = $c->prepare("INSERT INTO `site_settings` (setting_key, setting_value) VALUES (?, ?)");
        foreach ($defaults as $key => $value) {
            $stmt->bind_param('ss', $key, $value);
            $stmt->execute();
        }
    }

    // ===== ONE-TIME UPDATE: the about paragraphs (and the legal paragraph) become one editable text =====
    public const CHED_MEMO_CLAUSE = ', known as the Rules and Regulations on the Conduct of Scientific Procedures Using Animals, which the Commission on Higher Education (CHED) has reinforced through its [memorandum dated April 23, 2015](https://www.facebook.com/bai.gov.ph/posts/commission-on-higher-education-ched-has-issued-memoranda-as-part-of-its-complian/400983116759988/)';

    public function migrateAboutText(): void
    {
        $c        = $this->connection;
        $settings = [];
        $result   = $c->query("SELECT setting_key, setting_value FROM `site_settings`");
        foreach ($result ? $result->fetch_all(MYSQLI_ASSOC) : [] as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        if (isset($settings['about_text'])) {
            return;
        }

        $paragraphs = isset($settings['about_paragraphs'])
            ? (json_decode($settings['about_paragraphs'], true) ?: [])
            : array_values(array_filter([$settings['about_paragraph_1'] ?? '', $settings['about_paragraph_2'] ?? ''], 'strlen'));
        if (! isset($settings['legal_paragraph_migrated'])) {
            $paragraphs[] = 'The IACUC reviews and endorses animal research protocols in accordance with [Republic Act No. 8485](https://www.lawphil.net/statutes/repacts/ra1998/ra_8485_1998.html) (Animal Welfare Act of 1998), as amended by [Republic Act No. 10631](https://www.lawphil.net/statutes/repacts/ra2013/ra_10631_2013.html), and the requirements of [Department of Agriculture Administrative Order No. 40, Series of 1998](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/10/39491) (AO 40)' . self::CHED_MEMO_CLAUSE . '. Researchers conducting scientific procedures involving animals are required to obtain the necessary IACUC approval and ARC before commencing their studies.';
        }

        $save = $c->prepare("INSERT INTO `site_settings` (setting_key, setting_value) VALUES ('about_text', ?)");
        if (! $save) return;
        $text = implode("\n\n", $paragraphs);
        $save->bind_param('s', $text);
        $save->execute();
    }

    public function seedContactOffices(): void
    {
        $c      = $this->connection;
        $result = $c->query("SELECT COUNT(*) AS n FROM `contact_offices`");
        $row    = $result ? $result->fetch_assoc() : null;
        if ($row && (int) $row['n'] > 0) {
            return;
        }

        $offices = [
            [
                0,
                'Cordillera Center for Research and Development',
                'ccard.webp',
                'CCARD Bldg., CVM Compound, KM.5, La Trinidad, Benguet, 2601 Philipppines',
                '+63 998 281 8950',
                'ccard@bsu.edu.ph',
                'https://www.facebook.com/profile.php?id=100083273710247',
                'BSU - Cordillera Center for Animal Research & Development',
                'Dr. Ana Mendoza',
                'Director, BSU-CCARD',
                'ab.mendoza@gmail.com',
            ],
            [
                1,
                'Office of the Vice President for Research and Extension',
                'ovpre.webp',
                "Km. 6, La Trinidad, Benguet\n2601 Philippines",
                '63.74.665.7645',
                'vp.re@bsu.edu.ph',
                'https://www.facebook.com/bsuovpre/',
                'BSU - Office of the Vice President for Research and Extension',
                null,
                null,
                null,
            ],
            [
                2,
                'Benguet State University - La Trinidad Campus',
                'bsu.webp',
                'La Trinidad, Benguet, 2601 Philippines',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
            ],
            [
                3,
                'Department of Agriculture – Regional Field Office Cordillera Administrative Region (DA-RFO CAR) Regulatory Division',
                'da.webp',
                'BPI Compound, Guisad, Baguio City, Benguet',
                "(074) 444-9872\n+63 956 659 5110",
                "regulatorydivision.car@gmail.com\nlivestock.cordillera@gmail.com",
                null,
                null,
                null,
                null,
                null,
            ],
            [
                4,
                'Bureau of Animal Industry - Central Office',
                'bai.webp',
                'BAI Compound, Visayas Avenue, Diliman, Quezon City, Metro Manila',
                '8528 2240',
                'strategiccommunications@bai.gov.ph',
                'https://www.facebook.com/bai.gov.ph',
                'Bureau of Animal Industry',
                null,
                null,
                null,
            ],
        ];

        $stmt = $c->prepare(
            "INSERT INTO `contact_offices`
                (sort_order, name, logo_path, address, phone, email, facebook_url, facebook_label, director_name, director_role, director_email)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $types = 'i' . str_repeat('s', 10);
        foreach ($offices as $o) {
            [$sortOrder, $name, $logoPath, $address, $phone, $email, $fbUrl, $fbLabel, $dName, $dRole, $dEmail] = $o;
            $stmt->bind_param($types, $sortOrder, $name, $logoPath, $address, $phone, $email, $fbUrl, $fbLabel, $dName, $dRole, $dEmail);
            $stmt->execute();
        }
    }

    // ===== ONE-TIME UPDATE: contact offices (DA-RFO CAR, BAI Central, OVPRE + new order) =====
    // Runs once per database (flag in site_settings), so later edits in Site Content are kept.
    public function migrateContactOffices(): void
    {
        $c       = $this->connection;
        $flagKey = 'contacts_migrated_v2';

        $chk = $c->prepare("SELECT 1 FROM `site_settings` WHERE setting_key = ?");
        if (! $chk) return;
        $chk->bind_param('s', $flagKey);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            return;
        }

        // 1) Old "Bureau of Animal Industry" card -> DA-RFO CAR Regulatory Division
        $daName = 'Department of Agriculture – Regional Field Office Cordillera Administrative Region (DA-RFO CAR) Regulatory Division';
        $daLogo = 'da.webp';
        $daAddr = 'BPI Compound, Guisad, Baguio City, Benguet';
        $old    = 'Bureau of Animal Industry';
        $upd = $c->prepare("UPDATE `contact_offices` SET name = ?, logo_path = ?, address = ? WHERE name = ?");
        if ($upd) {
            $upd->bind_param('ssss', $daName, $daLogo, $daAddr, $old);
            $upd->execute();
        }

        // 2) Add any missing offices
        $ins = $c->prepare(
            "INSERT INTO `contact_offices`
                (sort_order, name, logo_path, address, phone, email, facebook_url, facebook_label)
             SELECT ?, ?, ?, ?, ?, ?, ?, ?
             FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM `contact_offices` WHERE name = ?)"
        );
        if ($ins) {
            $new = [
                [
                    1,
                    'Office of the Vice President for Research and Extension',
                    'ovpre.webp',
                    "Km. 6, La Trinidad, Benguet\n2601 Philippines",
                    '63.74.665.7645',
                    'vp.re@bsu.edu.ph',
                    'https://www.facebook.com/bsuovpre/',
                    'BSU - Office of the Vice President for Research and Extension'
                ],
                [
                    4,
                    'Bureau of Animal Industry - Central Office',
                    'bai.webp',
                    'BAI Compound, Visayas Avenue, Diliman, Quezon City, Metro Manila',
                    '8528 2240',
                    'strategiccommunications@bai.gov.ph',
                    'https://www.facebook.com/bai.gov.ph',
                    'Bureau of Animal Industry'
                ],
            ];
            foreach ($new as [$so, $nm, $lg, $ad, $ph, $em, $fb, $fl]) {
                $ins->bind_param('issssssss', $so, $nm, $lg, $ad, $ph, $em, $fb, $fl, $nm);
                $ins->execute();
            }
        }

        // 3) Order: CCARD > OVPRE > BSU > DA > BAI
        $order = [
            'Cordillera Center for Research and Development'            => 0,
            'Office of the Vice President for Research and Extension'   => 1,
            'Benguet State University - La Trinidad Campus'             => 2,
            $daName                                                     => 3,
            'Bureau of Animal Industry - Central Office'                                        => 4,
        ];
        $ord = $c->prepare("UPDATE `contact_offices` SET sort_order = ? WHERE name = ?");
        if ($ord) {
            foreach ($order as $nm => $so) {
                $ord->bind_param('is', $so, $nm);
                $ord->execute();
            }
        }

        // 4) Mark done
        $flagVal = '1';
        $flag = $c->prepare("INSERT INTO `site_settings` (setting_key, setting_value) VALUES (?, ?)");
        if ($flag) {
            $flag->bind_param('ss', $flagKey, $flagVal);
            $flag->execute();
        }
    }

    // ===== Facebook labels: replace URL-style text with page names, keeps later edits =====
    public function fixFacebookLabels(): void
    {
        $fixes = [
            'facebook.com/bsuovpre'    => 'BSU - Office of the Vice President for Research and Extension',
            'facebook.com/bai.gov.ph'  => 'Bureau of Animal Industry',
        ];

        $stmt = $this->connection->prepare("UPDATE `contact_offices` SET facebook_label = ? WHERE facebook_label = ?");
        if (! $stmt) return;
        foreach ($fixes as $old => $new) {
            $stmt->bind_param('ss', $new, $old);
            $stmt->execute();
        }
    }

    public function seedFaqs(): void
    {
        $c      = $this->connection;
        $result = $c->query("SELECT COUNT(*) AS n FROM `faqs`");
        $row    = $result ? $result->fetch_assoc() : null;
        if ($row && (int) $row['n'] > 0) {
            return;
        }

        $faqs = [
            'Who may avail?' => 'Students and researchers from BSU and other institutions within the Cordillera Administrative Region.',
            'What are the requirements?' => 'Researchers (formally called Principal Investigators) must have prior IACUC training in order to apply for protocol review.',
            'When working in groups, should each member apply for an IACUC protocol review?' => 'No, only the Principal Investigator (PI) may submit the IACUC protocol for the group. Multiple submissions of the same protocol will be rejected.',
            'What kind of IACUC training is required?' => 'Everyone working with animals must receive lecture and laboratory animal handling training. The training does not have to be from CCARD. However, please refer to the announcements page or inquire at the CCARD office to be updated with the scheduled trainings.',
            'What type of experiments need IACUC review?' => 'IACUC review is needed for all work involving direct interaction with live animals only.',
            'Do I need an IACUC protocol to use dead animals or animal parts?' => 'If you are obtaining animals or tissue that were already dead (e.g., rat livers from another laboratory, steaks from the supermarket, and tissues from a slaughterhouse) then you do not need an IACUC protocol. However, all work with wild mammal tissue need an approval from the Department of Environment and Natural Resources (DENR).',
            'How long does it take to get an IACUC review?' => 'Protocols are reviewed as soon as protocols are submitted. However, it may take 1-8 weeks for IACUC review and the issuance of the animal research clearance by BAI.',
            'Can the investigator begin animal work before receiving IACUC review?' => 'No. The IACUC review shall be part of the thesis proposal when using live animals.',
            'How much do I pay for an IACUC Protocol Review?' => "There is no fee for CCARD's IACUC review. However, BAI requires a payment of Php 100.00 for the animal research clearance, to be paid upon submission of the reviewed IACUC protocol.",
            'What if I amend my IACUC protocol to add/change procedures / personnel / animals?' => 'All revisions MUST be communicated with CCARD through the portal. Please note that even the most minor changes must be revised and reviewed for approval.',
            'Will my account become inactive after my clearance expires?' => 'Yes. Your account is marked inactive once your clearance expires and you have no pending protocols. You may reactivate it at any time by logging in to the portal.',
            'What if my training certificate is rejected?' => 'Your review is on hold until you resubmit it. Press Resubmit Certificate on My Protocols or on your protocol and upload a new certificate.',
            'Who do I contact if I have questions regarding the animal care and use program or the IACUC?' => 'Feel free to reach and inquire with us. You may contact ccard@bsu.edu.ph or refer to the contact page for more information.',
            'Who do I contact if I found an issue or have comments or suggestions about the portal?' => 'You may contact the developers at [meryl.arnobit@gmail.com](https://mail.google.com/mail/?view=cm&fs=1&to=meryl.arnobit%40gmail.com) or [malipe.sheldon01@gmail.com](https://mail.google.com/mail/?view=cm&fs=1&to=malipe.sheldon01%40gmail.com).',
        ];

        $stmt      = $c->prepare("INSERT INTO `faqs` (sort_order, question, answer) VALUES (?, ?, ?)");
        $sortOrder = 0;
        foreach ($faqs as $question => $answer) {
            $stmt->bind_param('iss', $sortOrder, $question, $answer);
            $stmt->execute();
            $sortOrder++;
        }
    }
}
