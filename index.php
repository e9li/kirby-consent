<?php

use Kirby\Cms\App;

load([
    'E9li\\Consent\\Consent' => __DIR__ . '/src/Consent.php',
]);

App::plugin('e9li/kirby-consent', [
    'options' => [
        // 'optin'  — EU/DE (§ 25 TDDDG): nothing non-essential before consent.
        // 'optout' — CH-only sites: optional categories start enabled.
        //            Not lawful for any site addressing the EU.
        'mode' => 'optin',

        // Optional categories, in display order.
        // Available: analytics, marketing, external
        'categories' => ['analytics', 'marketing', 'external'],

        // Page ids; the current language's slug is resolved automatically.
        'privacyPage' => 'datenschutz',
        'imprintPage' => 'impressum',

        // Consent lifetime. 12 months is the supervisory authorities' rule of
        // thumb, after which the visitor has to be asked again.
        'expiresAfterDays' => 365,

        // Bump to invalidate existing consent after changing copy or services.
        'revision' => 1,

        'layout' => 'box',
        'position' => 'bottom right',
        'darkmode' => false,

        // German orthography: 'de-de' or 'de-ch' (ss instead of ß).
        'orthography' => 'de-de',

        // Emit Google Consent Mode v2 defaults in <head>.
        'consentMode' => true,

        // Cookies removed when a category is withdrawn. `*` is a wildcard.
        'autoClear' => [
            'analytics' => ['_ga*', '_gid', '_gat*'],
            'marketing' => ['_gcl*', '_fbp', '_fbc', '_uet*', 'IDE', 'test_cookie'],
            'external' => [],
        ],

        // Wrap third-party iframes in editor content (kirbytext) in the consent
        // gate. Covers embeds that are already live in production, which no
        // template change can reach.
        'gateKirbytext' => true,
        'gateKirbytextCategory' => 'external',

        // Per-language string overrides, deep-merged over the plugin defaults:
        // 'translations' => ['de' => ['consentModal' => ['title' => '…']]]
        'translations' => [],
    ],
    'hooks' => [
        'kirbytext:after' => function (string $text) {
            if (E9li\Consent\Consent::option('gateKirbytext') !== true) {
                return $text;
            }

            return E9li\Consent\Consent::gateIframes($text);
        },
    ],
    'snippets' => [
        'consent/head' => __DIR__ . '/snippets/consent/head.php',
        'consent/banner' => __DIR__ . '/snippets/consent/banner.php',
        'consent/settings-link' => __DIR__ . '/snippets/consent/settings-link.php',
        'consent/placeholder' => __DIR__ . '/snippets/consent/placeholder.php',
    ],
]);
