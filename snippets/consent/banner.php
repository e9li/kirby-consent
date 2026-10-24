<?php

use E9li\Consent\Consent;

/**
 * Goes at the end of <body>.
 *
 * The whole configuration travels as a JSON script tag, so no escaping games
 * and no extra request for translations.
 */

if (Consent::hasOptionalCategories() === false) {
    return;
}

$js = Consent::assetUrl('consent.js');

if ($js === null) {
    return;
}

$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$nonce = Consent::cspNonce();
$nonceAttribute = $nonce !== null ? ' nonce="' . esc($nonce) . '"' : '';
?>
<script type="application/json" id="kirby-consent-config"<?= $nonceAttribute ?>><?= json_encode(Consent::config(), $flags) ?></script>
<script src="<?= esc($js) ?>"<?= $nonceAttribute ?> defer></script>
