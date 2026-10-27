<?php

$title = "Contact";

include "includes/header.php";

$offices = $offices ?? [];

// Offices with a director lead the page as wide cards; the rest follow as partner offices
$featuredOffices = array_values(array_filter($offices, fn($office) => !empty($office['director_name'])));
$otherOffices = array_values(array_filter($offices, fn($office) => empty($office['director_name'])));
$offices = array_merge($featuredOffices, $otherOffices);

// One label/value row; $values are plain strings, $linkPrefix turns them into mailto:/tel: links
$contactLines = function (array $values, string $linkPrefix = '') {
    foreach ($values as $value) {
        $text = htmlspecialchars($value, ENT_QUOTES);
        if ($linkPrefix === 'tel:') {
            $number = preg_replace('/[^\d+]/', '', $value);
            echo strlen($number) >= 7
                ? '<span><a href="tel:' . $number . '" class="underlined">' . $text . '</a></span>'
                : '<span>' . $text . '</span>';
        } elseif ($linkPrefix !== '') {
            echo '<span><a href="' . $linkPrefix . $text . '" class="underlined">' . $text . '</a></span>';
        } else {
            echo '<span>' . $text . '</span>';
        }
    }
};
?>

<link rel="stylesheet" href="<?= asset_css('contact.css') ?>">

<div class="body">
    <?php include "includes/navigation.php"; ?>

    <!-- ===== Contact page ===== -->
    <main class="main-content" id="main-content" tabindex="-1">
        <div class="page-banner">
            <div class="page-banner-text">
                <h1>Contact Us</h1>
                <p class="page-subtitle">The offices involved in your protocol and clearance</p>
            </div>
        </div>

        <!-- ===== Office cards ===== -->
        <div class="contact-grid">
            <?php foreach ($offices as $officeIndex => $office):
                $phones = array_filter(array_map('trim', explode("\n", $office['phone'] ?? '')));
                $emails = array_filter(array_map('trim', explode("\n", $office['email'] ?? '')));
                $isFeatured = !empty($office['director_name']);
                $firstEmail = reset($emails) ?: '';
                $firstPhone = preg_replace('/[^\d+]/', '', reset($phones) ?: '');
            ?>
                <?php if ($featuredOffices && $officeIndex === count($featuredOffices)): ?>
                    <h2 class="contact-grid-heading">Partner Offices</h2>
                <?php endif; ?>
                <article class="contact-card<?= $isFeatured ? ' contact-card--featured' : '' ?>">
                    <div class="card-identity">
                        <div class="card-header">
                            <?php if (!empty($office['logo_path'])): ?>
                                <img src="<?= IMGPATH ?>/<?= htmlspecialchars($office['logo_path'], ENT_QUOTES) ?>" alt="" class="card-logo">
                            <?php endif; ?>
                            <h2><?= htmlspecialchars($office['name'], ENT_QUOTES) ?></h2>
                        </div>

                        <?php if (!empty($office['director_name'])): ?>
                            <div class="contact-person" id="director-contact">
                                <div>
                                    <span class="person-name"><?= htmlspecialchars($office['director_name'], ENT_QUOTES) ?></span>
                                    <?php if (!empty($office['director_role'])): ?>
                                        <span class="person-role"><?= htmlspecialchars($office['director_role'], ENT_QUOTES) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($office['director_email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($office['director_email'], ENT_QUOTES) ?>" class="underlined person-email">
                                        <svg class="row-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#email-icon" />
                                        </svg><?= htmlspecialchars($office['director_email'], ENT_QUOTES) ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-body">
                        <dl class="contact-details">
                            <?php if (!empty($office['address'])): ?>
                                <div class="contact-row">
                                    <dt>
                                        <svg class="row-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#location-icon" />
                                        </svg>Address
                                    </dt>
                                    <dd><?= nl2br(htmlspecialchars($office['address'], ENT_QUOTES)) ?></dd>
                                </div>
                            <?php endif; ?>

                            <?php if ($phones): ?>
                                <div class="contact-row">
                                    <dt>
                                        <svg class="row-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#phone-icon" />
                                        </svg>Phone
                                    </dt>
                                    <dd><?php $contactLines($phones, 'tel:'); ?></dd>
                                </div>
                            <?php endif; ?>

                            <?php if ($emails): ?>
                                <div class="contact-row">
                                    <dt>
                                        <svg class="row-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#email-icon" />
                                        </svg>Email
                                    </dt>
                                    <dd><?php $contactLines($emails, 'mailto:'); ?></dd>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($office['facebook_url'])): ?>
                                <div class="contact-row">
                                    <dt>
                                        <svg class="row-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#facebook-icon" />
                                        </svg>Facebook
                                    </dt>
                                    <dd><a href="<?= htmlspecialchars($office['facebook_url'], ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer" class="underlined"><?= htmlspecialchars($office['facebook_label'] ?: $office['facebook_url'], ENT_QUOTES) ?></a></dd>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($office['website_url'])): ?>
                                <div class="contact-row">
                                    <dt>
                                        <svg class="row-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#website-icon" />
                                        </svg>Website
                                    </dt>
                                    <dd><a href="<?= htmlspecialchars($office['website_url'], ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer" class="underlined"><?= htmlspecialchars($office['website_label'] ?: $office['website_url'], ENT_QUOTES) ?></a></dd>
                                </div>
                            <?php endif; ?>
                        </dl>

                        <?php if ($isFeatured && ($firstEmail !== '' || strlen($firstPhone) >= 7)): ?>
                            <div class="contact-actions">
                                <?php if ($firstEmail !== ''): ?>
                                    <a href="mailto:<?= htmlspecialchars($firstEmail, ENT_QUOTES) ?>" class="button contact-action">
                                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#email-icon" />
                                        </svg>Email us
                                    </a>
                                <?php endif; ?>
                                <?php if (strlen($firstPhone) >= 7): ?>
                                    <a href="tel:<?= $firstPhone ?>" class="button contact-action contact-action--secondary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#phone-icon" />
                                        </svg>Call us
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<!-- ===== Footer ===== -->
<?php include "includes/footer.php"; ?>