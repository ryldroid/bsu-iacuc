<?php

$title = "Contact";

include "includes/header.php";

$offices = $offices ?? [];
?>

<link rel="stylesheet" href="<?= asset_css('contact.css') ?>">

<div class="body">
    <?php include "includes/navigation.php"; ?>

    <main class="main-content" id="main-content" tabindex="-1">
        <h1>Contact Us</h1>

        <div class="contact-grid">
            <?php foreach ($offices as $office):
                $phones = array_filter(array_map('trim', explode("\n", $office['phone'] ?? '')));
                $emails = array_filter(array_map('trim', explode("\n", $office['email'] ?? '')));
            ?>
                <div class="contact-card">
                    <div class="card-header">
                        <?php if (!empty($office['logo_path'])): ?>
                            <img src="<?= IMGPATH ?>/<?= htmlspecialchars($office['logo_path'], ENT_QUOTES) ?>" alt="" class="card-logo">
                        <?php endif; ?>
                        <h2><?= htmlspecialchars($office['name'], ENT_QUOTES) ?></h2>
                    </div>

                    <dl class="contact-details">
                        <?php if (!empty($office['address'])): ?>
                            <div class="contact-row">
                                <dt>
                                    <svg class="row-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#location-icon" />
                                    </svg>Address
                                </dt>
                                <dd><?= nl2br(htmlspecialchars($office['address'], ENT_QUOTES)) ?></dd>
                            </div>
                        <?php endif; ?>

                        <?php if ($phones): ?>
                            <div class="contact-row">
                                <dt>
                                    <svg class="row-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#phone-icon" />
                                    </svg>Phone
                                </dt>
                                <div>
                                    <?php foreach ($phones as $phone): ?>
                                        <dd><?= htmlspecialchars($phone, ENT_QUOTES) ?></dd>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($emails): ?>
                            <div class="contact-row">
                                <dt>
                                    <svg class="row-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#email-icon" />
                                    </svg>Email
                                </dt>
                                <div>
                                    <?php foreach ($emails as $email): ?>
                                        <dd><a href="mailto:<?= htmlspecialchars($email, ENT_QUOTES) ?>" class="underlined"><?= htmlspecialchars($email, ENT_QUOTES) ?></a></dd>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($office['facebook_url'])): ?>
                            <div class="contact-row">
                                <dt>
                                    <svg class="row-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#facebook-icon" />
                                    </svg>Facebook
                                </dt>
                                <dd><a href="<?= htmlspecialchars($office['facebook_url'], ENT_QUOTES) ?>" target="_blank" class="underlined"><?= htmlspecialchars($office['facebook_label'] ?: $office['facebook_url'], ENT_QUOTES) ?></a></dd>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($office['director_name'])): ?>
                            <div class="notable-people" id="director-contact">
                                <div>
                                    <span class="person-name"><?= htmlspecialchars($office['director_name'], ENT_QUOTES) ?></span>
                                    <?php if (!empty($office['director_role'])): ?>
                                        <span class="person-role"><?= htmlspecialchars($office['director_role'], ENT_QUOTES) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($office['director_email'])): ?>
                                    <dt>
                                        <svg class="row-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#email-icon" />
                                        </svg> <a href="mailto:<?= htmlspecialchars($office['director_email'], ENT_QUOTES) ?>" class="underlined"><?= htmlspecialchars($office['director_email'], ENT_QUOTES) ?></a>
                                    </dt>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<?php include "includes/footer.php"; ?>