<?php

use E9li\Consent\Consent;

/**
 * Permanent withdrawal entry point — Art. 7 (3) GDPR requires revoking consent
 * to be as easy as giving it, so this belongs in the footer of every page.
 *
 * snippet('consent/settings-link', ['class' => 'underline hover:no-underline'])
 */

$strings = Consent::strings();
?>
<button
    type="button"
    data-cc="show-preferencesModal"
    class="<?= esc($class ?? '') ?>"
><?= esc($strings['ui']['settingsLink']) ?></button>
