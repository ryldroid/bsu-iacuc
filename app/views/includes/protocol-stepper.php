<?php

/** @var string $stageSlug  filter slug of the protocol's current stage */
/** @var array  $statusMeta stage slug => ['label', 'icon'] */

$stepperSlugs = [$stageSlug === 'returned-for-revision' ? 'returned-for-revision' : 'to-review', 'reviewed', 'endorsed', 'approved'];
$stepperClass = ['to-review' => 'under-review', 'returned-for-revision' => 'needs-revision'];
$currentStep  = max(0, (int) array_search($stageSlug, $stepperSlugs, true));

// Short, role-neutral tooltip text, shared by the researcher and personnel lists
$stepperTips = [
    'to-review'             => 'Waiting for the reviewer to assess the protocol and training certificate.',
    'returned-for-revision' => 'Sent back with feedback. Waiting for the researcher to resubmit.',
    'reviewed'              => 'Review is done. Clearance fee payment and endorsement come next.',
    'endorsed'              => 'Endorsed to DA-RFO-CAR. Waiting for the clearance to be released.',
    'approved'              => 'Clearance issued. The protocol is fully approved.',
];
?>
<ol class="history-stepper protocol-stepper" aria-label="Protocol progress">
    <?php foreach ($stepperSlugs as $index => $slug):
        $stepState = $index < $currentStep ? 'done' : ($index === $currentStep ? 'current' : 'upcoming'); ?>
        <li class="history-step history-step--<?= $stepperClass[$slug] ?? $slug ?> <?= $stepState === 'current' ? 'is-selected' : 'is-' . $stepState ?>" <?= $stepState === 'current' ? 'aria-current="step"' : '' ?>>
            <div class="history-step-button" tabindex="0" data-tooltip="<?= htmlspecialchars($stepperTips[$slug], ENT_QUOTES, 'UTF-8') ?>">
                <span class="payment-event-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" focusable="false">
                        <use href="#<?= $statusMeta[$slug]['icon'] ?>" />
                    </svg>
                </span>
                <span class="history-step-label"><?= htmlspecialchars($statusMeta[$slug]['label'], ENT_QUOTES, 'UTF-8') ?><span class="sr-only"> (<?= $stepState ?>). <?= htmlspecialchars($stepperTips[$slug], ENT_QUOTES, 'UTF-8') ?></span></span>
            </div>
        </li>
    <?php endforeach; ?>
</ol>