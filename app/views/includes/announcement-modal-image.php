<?php

/**
 * Announcement modal image. Clicking it opens the zoom lightbox.
 *
 * @var string $annImgUrl  image URL
 * @var string $annHeading announcement heading, used as the alt text
 */
$annAlt = htmlspecialchars($annHeading, ENT_QUOTES);
?>
<div class="announcement-modal-image-wrap">
  <img class="announcement-modal-image" src="<?= $annImgUrl ?>" alt="<?= $annAlt ?>"
    data-zoom-src="<?= $annImgUrl ?>" data-zoom-alt="<?= $annAlt ?>">
</div>