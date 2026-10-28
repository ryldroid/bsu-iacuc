<?php

$title = "Contact";

include "includes/header.php";

$offices = $offices ?? [];

// Offices with a director lead the page as wide cards; the rest follow as partner offices
$featuredOffices = array_values(array_filter($offices, fn($office) => !empty($office['director_name'])));
$otherOffices = array_values(array_filter($offices, fn($office) => empty($office['director_name'])));
$offices = array_merge($featuredOffices, $otherOffices);

// Small icon buttons; every argument is already escaped
$buttonIcon = fn(string $icon) => '<svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#' . $icon . '" /></svg>';

$linkButton = fn(string $href, string $icon, string $label, string $attributes = '') => '<a href="' . $href . '" class="contact-button" aria-label="' . $label . '" title="' . $label . '"' . $attributes . '>' . $buttonIcon($icon) . '</a>';

// The address itself is the link; $email is plain, $text is already escaped
$emailLink = fn(string $email, string $text) => '<a ' . email_link_attributes($email) . ' class="underlined">' . $text . '</a>';

$copyButton = fn(string $text) => '<button type="button" class="contact-button" data-copy="' . $text . '" aria-label="Copy ' . $text . '" title="Copy">' . $buttonIcon('copy-icon') . '</button>';

// One label/value row; $values are plain strings. Phones get a call button, emails become links, both get a copy button
// $skipFirstCall drops the call button from the first phone, which the card's Call us button already covers
$contactLines = function (array $values, string $type = '', bool $skipFirstCall = false) use ($linkButton, $emailLink, $copyButton) {
    foreach (array_values($values) as $index => $value) {
        $text    = htmlspecialchars($type === 'phone' ? preg_replace('/^\+63\s*/', '0', $value) : $value, ENT_QUOTES);
        $number  = preg_replace('/[^\d+]/', '', $value);
        $buttons = '';

        if ($type === 'phone' && strlen($number) >= 7) {
            $buttons = ($skipFirstCall && $index === 0 ? '' : $linkButton('tel:' . $number, 'phone-icon', 'Call ' . $text)) . $copyButton($text);
        } elseif ($type === 'email') {
            $buttons = $copyButton($text);
            $text    = $emailLink($value, $text);
        }

        echo '<span class="contact-line">' . $text . $buttons . '</span>';
    }
};

// A label with a button that opens its page in a new tab
$openLine = function (string $url, string $label) use ($linkButton) {
    $label = htmlspecialchars($label, ENT_QUOTES);
    echo '<span class="contact-line">' . $label . $linkButton(htmlspecialchars($url, ENT_QUOTES), 'external-link-icon', 'Open ' . $label, ' target="_blank" rel="noopener noreferrer"') . '</span>';
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
                                    <?php $directorEmail = htmlspecialchars($office['director_email'], ENT_QUOTES); ?>
                                    <span class="contact-line">
                                        <a <?= email_link_attributes($office['director_email']) ?> class="person-email underlined">
                                            <svg class="row-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <use href="#email-icon" />
                                            </svg><?= $directorEmail ?>
                                        </a>
                                        <?= $copyButton($directorEmail) ?>
                                    </span>
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
                                    <dd><?php $contactLines($phones, 'phone', $isFeatured); ?></dd>
                                </div>
                            <?php endif; ?>

                            <?php if ($emails): ?>
                                <div class="contact-row">
                                    <dt>
                                        <svg class="row-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#email-icon" />
                                        </svg>Email
                                    </dt>
                                    <dd><?php $contactLines($emails, 'email'); ?></dd>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($office['facebook_url'])): ?>
                                <div class="contact-row">
                                    <dt>
                                        <svg class="row-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#facebook-icon" />
                                        </svg>Facebook
                                    </dt>
                                    <dd><?php $openLine($office['facebook_url'], $office['facebook_label'] ?: $office['facebook_url']); ?></dd>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($office['website_url'])): ?>
                                <div class="contact-row">
                                    <dt>
                                        <svg class="row-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#website-icon" />
                                        </svg>Website
                                    </dt>
                                    <dd><?php $openLine($office['website_url'], $office['website_label'] ?: $office['website_url']); ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>

                        <?php if ($isFeatured && ($firstEmail !== '' || strlen($firstPhone) >= 7)): ?>
                            <div class="contact-actions">
                                <?php if ($firstEmail !== ''): ?>
                                    <a <?= email_link_attributes($firstEmail) ?> class="button contact-action">
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

<script>
    // ===== Copy email address or phone number =====
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-copy]');
        if (!button) return;

        const icon = button.querySelector('use');
        const showIcon = (name) => {
            icon.setAttribute('href', '#' + name);
            setTimeout(() => icon.setAttribute('href', '#copy-icon'), 2000);
        };

        navigator.clipboard.writeText(button.dataset.copy)
            .then(() => showIcon('check-icon'))
            .catch(() => showIcon('alert-triangle-icon'));
    });
</script>

<!-- ===== Footer ===== -->
<?php include "includes/footer.php"; ?>