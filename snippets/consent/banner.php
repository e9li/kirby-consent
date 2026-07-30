<?php

use E9li\Consent\Consent;

/**
 * Goes at the end of <body>.
 *
 * The whole configuration travels as a JSON script tag, so no escaping games
 * and no extra request for translations.
 */

$js = Consent::assetUrl('consent.js');

if ($js === null) {
    return;
}

$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
?>
<script type="application/json" id="kirby-consent-config"><?= json_encode(Consent::config(), $flags) ?></script>
<script src="<?= $js ?>" defer></script>
