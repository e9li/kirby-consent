# Kirby Consent

Cookie consent banner for Kirby 5 with **real script gating**, third-party embed
placeholders and **Google Consent Mode v2** — built on
[vanilla-cookieconsent](https://github.com/orestbida/cookieconsent) (MIT),
self-hosted, no subscription, no external CMP service.

Consent-first defaults:

- equal-weight *Accept all* / *Reject all* on the first layer, no colour nudging
- scripts and embeds explicitly marked as non-essential stay inert before consent
- granular categories, nothing pre-ticked in opt-in mode
- permanent settings link for withdrawal, `revision` support for re-consent
- 12-month consent lifetime by default
- consent record (id, timestamp, per-category choice, revision) in the browser
  cookie; no server-side consent log

> Not legal advice. The shipped copy is written to the criteria of the German
> DSK *Orientierungshilfe Telemedien* and VG Hannover (03/2025); have the final
> privacy-policy wording reviewed.

## Installation

Requires Kirby 5.2+ and PHP 8.2–8.5.

```
composer require e9li/kirby-consent
```

Installs to `site/plugins/consent/`. The plugin ships prebuilt assets, so there
is nothing to add to your Vite/webpack build.

## Setup

Three snippets, one config block.

**1. `<head>`, as early as possible — before any tag loader:**

```php
<?php snippet('consent/head') ?>
```

**2. End of `<body>`:**

```php
<?php snippet('consent/banner') ?>
```

**3. Footer, on every page:**

```php
<?php snippet('consent/settings-link', ['class' => 'underline']) ?>
```

**4. `site/config/config.php`:**

```php
'e9li.kirby-consent' => [
    'mode'        => 'optin',
    'categories'  => ['analytics', 'marketing', 'external'],
    'privacyPage' => 'datenschutz',
    'imprintPage' => 'impressum',
],
```

## Gating scripts

Any script that stores or reads something on the device needs a category. Add
`type="text/plain"` and `data-category`; the library recreates the tag once that
category is accepted. This works for inline scripts and for `src` scripts.

```php
<script
    type="text/plain"
    data-category="analytics"
    data-service="Google Analytics"
    src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXX"
></script>
<script type="text/plain" data-category="analytics">
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-XXXXXXX');
</script>
```

`snippet('consent/head')` has already declared the `gtag()` bridge and set every
Consent Mode signal to `denied`, so you do not need to repeat that.

## Gating embeds

Wrap iframes and third-party widgets. The markup is parked in a `<template>`,
which browsers keep inert — no request is made until the visitor accepts the
category or loads that one embed.

```php
<?php snippet('consent/placeholder', ['service' => 'Vimeo'], slots: true) ?>
    <iframe src="https://player.vimeo.com/video/123456" loading="lazy"></iframe>
<?php endsnippet() ?>
```

Or pass markup instead of using a slot:

```php
<?= snippet('consent/placeholder', [
    'service' => 'Google Maps',
    'category' => 'external',
    'html' => $embedHtml,
]) ?>
```

The visitor gets *Load content once* and *Always load external content*. If any
optional category is later withdrawn, the page reloads. This is necessary for
both iframes and scripts: an iframe that already connected cannot be un-embedded,
and JavaScript that already ran cannot be reliably unloaded.

The placeholder `category` must be listed in your `categories` option. Invalid or
unavailable categories throw a configuration exception instead of silently
loading third-party content without consent.

### Iframes in editor content

`gateKirbytext` is enabled by default. The `kirbytext:after` hook wraps external
iframes that editors paste into content, including uppercase tags, unquoted
`src` attributes and `srcdoc` iframes. Same-origin and relative `src` iframes are
left alone unless they also use `srcdoc`.

The gate uses `gateKirbytextCategory` (default `external`), which must be enabled
in `categories`. To disable automatic editor-content gating, set
`gateKirbytext` to `false`. Templates and dynamically inserted content still
need the placeholder snippet or their own consent integration.

## Options

All under `e9li.kirby-consent`:

| Option | Default | |
|---|---|---|
| `mode` | `'optin'` | `'optin'` = EU/DE strict. `'optout'` = CH-only sites: optional categories start enabled and the Consent Mode defaults start granted. **Not lawful for any site addressing the EU.** |
| `categories` | `['analytics', 'marketing', 'external']` | Optional categories in display order. `necessary` is implicit. Unknown names throw an exception; duplicates are removed. With an empty list, the banner, settings link and embed gate are disabled. |
| `privacyPage` | `'datenschutz'` | Page id; the current language's slug is resolved automatically. |
| `imprintPage` | `'impressum'` | Page id, or `null` to omit. |
| `expiresAfterDays` | `365` | Consent lifetime. |
| `revision` | `1` | Bump to invalidate existing consent. |
| `layout` / `position` | `'box'` / `'bottom right'` | |
| `darkmode` | `false` | |
| `orthography` | `'de-de'` | `'de-ch'` switches German to Swiss spelling (`ss` instead of `ß`). |
| `consentMode` | `true` | Emit Google Consent Mode v2 defaults. |
| `cspNonce` | `null` | Optional per-request nonce added to the plugin's script tags. |
| `autoClear` | `_ga*`, `_gcl*`, … | Cookies deleted when a category is withdrawn. `*` is a wildcard. |
| `gateKirbytext` | `true` | Gate external iframes in editor content. |
| `gateKirbytextCategory` | `'external'` | Consent category for those iframes; must be enabled in `categories`. |
| `translations` | `[]` | Per-language string overrides, deep-merged. |

### Categories and Consent Mode signals

| Category | Signals |
|---|---|
| `necessary` | `security_storage` (always granted) |
| `analytics` | `analytics_storage` |
| `marketing` | `ad_storage`, `ad_user_data`, `ad_personalization` |
| `external` | `functionality_storage`, `personalization_storage` |

### Overriding copy

```php
'e9li.kirby-consent' => [
    'translations' => [
        'de' => [
            'consentModal' => ['title' => 'Cookies auf unserer Website'],
            'categories' => [
                'analytics' => ['description' => 'Wir nutzen Matomo, …'],
            ],
        ],
    ],
],
```

Shipped languages: `de`, `de-ch`, `en`, `fr`, `it`, `ru`. Language codes are
matched case-insensitively, `_` and `-` are equivalent, and regional variants
fall back to their base language (for example `en-GB` → `en`). Unknown languages
fall back to English. Set `orthography` to `de-ch` to use Swiss spelling for any
German language.

## Content Security Policy

If your site's CSP requires nonces, set `cspNonce` to the same cryptographically
random, per-request nonce used in the `Content-Security-Policy` header. The head
bootstrap, JSON configuration and external bundle script then receive that
nonce. Never reuse a fixed nonce across requests. Gated scripts and embed scripts
also need to retain a valid nonce when your application creates them.

## Theming

Override the library's CSS custom properties on `#cc-main`, and the embed
placeholder's `--cc-embed-*`:

```css
#cc-main {
    --cc-btn-primary-bg: var(--color-primary-500);
    --cc-btn-primary-color: var(--color-secondary-500);
    --cc-btn-border-radius: 0.25rem;
    --cc-toggle-on-bg: var(--cc-btn-primary-bg);
}
```

Only style the **primary** button variables. The plugin ties the secondary
button to the primary one so *Reject all* stays visually equivalent; setting
`--cc-btn-secondary-*` yourself reintroduces the dark pattern.

## If you use Google Tag Manager

The banner sends the consent signals, but **GTM decides whether a tag fires**.
Without the container-side setup the banner does not block anything. In every
container:

1. Container settings → enable the consent overview ("Einwilligung zulassen").
2. Per tag → Advanced settings → Consent settings → *Additional consent
   required*:
   - GA4 tags → `analytics_storage`
   - Google Ads conversion/remarketing → `ad_storage`, `ad_user_data`,
     `ad_personalization`
   - Meta/Facebook pixel → couple the trigger to consent; Consent Mode does not
     control Meta natively
3. Test with GTM preview → *Consent* tab: before any choice every type must be
   `denied` and the tags must show as blocked; after *Accept all* they switch to
   `granted` and fire.

Note that the GTM container script itself is a third-party request. On an
EU-facing site, gate the container loader too:

```php
<script type="text/plain" data-category="analytics" src="https://www.googletagmanager.com/gtm.js?id=GTM-XXXX"></script>
```

## Development

```
npm install
npm run build     # → assets/consent.{js,css}
npm run watch
```

`assets/` is committed — it is what consuming sites load.

## Support this project

[![Sponsor](https://img.shields.io/badge/Sponsor-%E2%9D%A4-ea4aaa)](https://github.com/sponsors/e9li)

This project is free and open source, including for commercial use. If it creates value for you or your business, please consider [sponsoring its development](https://github.com/sponsors/e9li). Sponsorship helps fund ongoing maintenance and future improvements.

## License

MIT — see `LICENSE`. The bundled vanilla-cookieconsent license is included in
`THIRD_PARTY_NOTICES.md`.
