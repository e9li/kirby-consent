<?php

use E9li\Consent\Consent;

/**
 * Goes as early as possible into <head> — before any tag loader.
 *
 * Emits the Google Consent Mode v2 default state and the banner stylesheet.
 * The stylesheet is loaded here rather than at the end of <body> so the banner
 * does not flash unstyled.
 */

$css = Consent::assetUrl('consent.css');

if ($css !== null): ?>
<link rel="stylesheet" href="<?= $css ?>">
<?php endif; ?>
<?php if (Consent::option('consentMode') === true):
    $signals = Consent::defaultSignals();
    $signals['wait_for_update'] = 500;
    ?>
<script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
        dataLayer.push(arguments);
    }

    gtag('consent', 'default', <?= json_encode($signals, JSON_UNESCAPED_SLASHES) ?>);
    gtag('set', 'ads_data_redaction', true);
    gtag('set', 'url_passthrough', true);
</script>
<?php endif; ?>
