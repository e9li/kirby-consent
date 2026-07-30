<?php

use E9li\Consent\Consent;

/**
 * Consent gate for third-party embeds — maps, videos, 3D viewers.
 *
 * The embed markup is parked in a <template>, which browsers keep inert: no
 * iframe is fetched, no script runs, until the visitor either accepts the
 * category or loads this one embed. Prefer the slot form:
 *
 *   <?php snippet('consent/placeholder', ['service' => 'Vimeo'], slots: true) ?>
 *       <iframe src="…"></iframe>
 *   <?php endsnippet() ?>
 *
 * Or pass ready-made markup as `html`. Options:
 *   category  default 'external'
 *   service   provider name shown to the visitor
 *   class     extra classes on the wrapper
 */

$embed = (string) ($html ?? $slot ?? '');

if (trim($embed) === '') {
    return;
}

$strings = Consent::strings();
$category = $category ?? 'external';
$service = $service ?? $strings['ui']['placeholderService'];

// If the category is not configured, gating it would hide the embed forever.
if (in_array($category, Consent::categories(), true) === false) {
    echo $embed;
    return;
}

$title = str_replace('{service}', $service, $strings['ui']['placeholderTitle']);
$text = str_replace('{service}', $service, $strings['ui']['placeholderText']);
$privacyUrl = Consent::pageUrl('privacyPage');
?>
<div
    class="cc-embed<?= ($class ?? '') !== '' ? ' ' . esc($class) : '' ?>"
    data-cc-embed
    data-cc-embed-category="<?= esc($category) ?>"
>
    <template data-cc-embed-content><?= $embed ?></template>
    <div class="cc-embed__notice" data-cc-embed-notice>
        <p class="cc-embed__title"><?= esc($title) ?></p>
        <p class="cc-embed__text"><?= esc($text) ?></p>
        <div class="cc-embed__actions">
            <button type="button" class="cc-embed__btn" data-cc-embed-load>
                <?= esc($strings['ui']['placeholderLoad']) ?>
            </button>
            <button type="button" class="cc-embed__btn" data-cc-embed-always>
                <?= esc($strings['ui']['placeholderAlways']) ?>
            </button>
        </div>
        <?php if ($privacyUrl !== null): ?>
            <a class="cc-embed__link" href="<?= $privacyUrl ?>">
                <?= esc($strings['ui']['privacyLabel']) ?>
            </a>
        <?php endif; ?>
    </div>
</div>
