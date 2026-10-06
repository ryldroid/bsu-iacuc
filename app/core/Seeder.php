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
                'facebook.com/bsuovpre',
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
                'Department of Agriculture-Cordillera Administrative Region Field Unit (DA-CARFU) Regulatory Division',
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
                'BAI Central Office',
                'bai.webp',
                'BAI Compound, Visayas Avenue, Diliman, Quezon City, Metro Manila',
                '8528 2240',
                'strategiccommunications@bai.gov.ph',
                'https://www.facebook.com/bai.gov.ph',
                'facebook.com/bai.gov.ph',
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

    // ===== ONE-TIME UPDATE: contact offices (DA-CARFU, BAI Central, OVPRE + new order) =====
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

        // 1) Old "Bureau of Animal Industry" card -> DA-CARFU Regulatory Division
        $daName = 'Department of Agriculture-Cordillera Administrative Region Field Unit (DA-CARFU) Regulatory Division';
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
                    'facebook.com/bsuovpre'
                ],
                [
                    4,
                    'BAI Central Office',
                    'bai.webp',
                    'BAI Compound, Visayas Avenue, Diliman, Quezon City, Metro Manila',
                    '8528 2240',
                    'strategiccommunications@bai.gov.ph',
                    'https://www.facebook.com/bai.gov.ph',
                    'facebook.com/bai.gov.ph'
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
            'BAI Central Office'                                        => 4,
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
            'What are the requirements?' => 'Researchers (or Principal Investigators) must have prior IACUC training in order to apply for protocol review.',
            'When working in groups, should each member apply for an IACUC protocol review?' => 'No, only the Principal Investigator (PI) may submit the IACUC protocol for the group.',
            'What kind of IACUC training is required?' => 'Everyone working with animals must receive lecture and laboratory animal handling training. Please refer to the announcements page or inquire at the CCARD office to be updated with the scheduled trainings.',
            'What type of experiments need IACUC review?' => 'IACUC review is needed for all work involving direct interaction with live animals only.',
            'Do I need an IACUC protocol to use dead animals or animal parts?' => 'If you are obtaining animals or tissue that were already dead (rat livers from another laboratory, steaks from the supermarket, tissues from a slaughterhouse) then you do not need an IACUC protocol. However, all work with wild mammal tissue need an approval from the Department of Environment and Natural Resources (DENR).',
            'How long does it take to get an IACUC review?' => 'Protocols are reviewed as soon as protocols are submitted. However, it may take 1-8 weeks for IACUC review and the issuance of the animal research clearance by BAI.',
            'Can the investigator begin animal work before receiving IACUC review?' => 'No. The IACUC review shall be part of the thesis proposal when using live animals.',
            'How much do I pay for an IACUC Protocol Review?' => "There is no fee for CCARD's IACUC review. However, BAI requires a payment of Php 100.00 for the Animal Research Clearance, to be paid upon submission of the reviewed IACUC protocol.",
            'What if I amend my IACUC protocol to add/change procedures / personnel / animals?' => 'All revision must be communicated with the IACUC through the portal. Please note that even the most minor changes must be revised and reviewed for approval.',
            'Who do I contact if I have questions regarding the animal care and use program or the IACUC?' => 'In BSU, you may visit the CCARD office. You may also refer to the contact page for additional contact information.',
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
