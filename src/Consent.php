<?php

namespace E9li\Consent;

use Kirby\Cms\App;
use Kirby\Toolkit\A;

/**
 * Builds the runtime configuration for the consent banner.
 *
 * Everything the JS bundle needs is assembled here and handed over as a single
 * JSON blob by snippets/consent/banner.php. Nothing is fetched at runtime, so
 * the banner is available as soon as the bundle has parsed.
 */
class Consent
{
    public const PLUGIN = 'e9li/kirby-consent';

    /**
     * Google Consent Mode v2 signals controlled by each category.
     *
     * `necessary` maps to security_storage, which is granted by default and
     * therefore never updated.
     */
    public const SIGNALS = [
        'analytics' => ['analytics_storage'],
        'marketing' => ['ad_storage', 'ad_user_data', 'ad_personalization'],
        'external' => ['functionality_storage', 'personalization_storage'],
    ];

    public static function option(string $key, mixed $default = null): mixed
    {
        return App::instance()->option('e9li.kirby-consent.' . $key, $default);
    }

    /**
     * 'optin'  — EU/DE: nothing non-essential runs before an explicit choice.
     * 'optout' — CH-only sites: non-essential categories start enabled.
     */
    public static function mode(): string
    {
        return static::option('mode') === 'optout' ? 'optout' : 'optin';
    }

    public static function isOptin(): bool
    {
        return static::mode() === 'optin';
    }

    /**
     * Configured optional categories, in display order. `necessary` is implicit
     * and always first.
     */
    public static function categories(): array
    {
        $configured = static::option('categories', []);
        $known = array_keys(static::SIGNALS);

        return array_values(array_intersect($known, (array) $configured));
    }

    /**
     * Resolve a configured page id to a URL in the current language.
     * Returns null when the option is empty or the page does not exist.
     */
    public static function pageUrl(string $option): ?string
    {
        $id = static::option($option);

        if (empty($id)) {
            return null;
        }

        return App::instance()->page($id)?->url();
    }

    public static function assetUrl(string $filename): ?string
    {
        return App::instance()->plugin(static::PLUGIN)?->asset($filename)?->url();
    }

    public static function languageCode(): string
    {
        return App::instance()->language()?->code() ?? 'en';
    }

    /**
     * Pick the translation file for the current language. German falls back to
     * the Swiss variant when `orthography` says so; anything unknown falls back
     * to English.
     */
    protected static function translationFile(string $code): string
    {
        $dir = dirname(__DIR__) . '/translations';

        $candidates = [];

        if ($code === 'de' && static::option('orthography') === 'de-ch') {
            $candidates[] = 'de-ch';
        }

        $candidates[] = $code;
        $candidates[] = 'en';

        foreach ($candidates as $candidate) {
            $file = $dir . '/' . $candidate . '.php';

            if (is_file($file) === true) {
                return $file;
            }
        }

        throw new \Exception('kirby-consent: no translation file found');
    }

    /**
     * Plugin strings for the current language, with per-site overrides from
     * `e9li.kirby-consent.translations.<code>` merged in.
     */
    public static function strings(): array
    {
        $code = static::languageCode();
        $strings = require static::translationFile($code);

        $overrides = static::option('translations.' . $code);

        if (is_array($overrides) === true) {
            $strings = A::merge($strings, $overrides, A::MERGE_REPLACE);
        }

        return $strings;
    }

    /**
     * Assemble the translation object in the shape vanilla-cookieconsent
     * expects, including only the categories this site actually uses.
     */
    protected static function libraryTranslations(array $strings): array
    {
        $privacyUrl = static::pageUrl('privacyPage');
        $imprintUrl = static::pageUrl('imprintPage');

        $links = [];

        if ($privacyUrl !== null) {
            $links[] = '<a href="' . $privacyUrl . '">' . $strings['ui']['privacyLabel'] . '</a>';
        }

        if ($imprintUrl !== null) {
            $links[] = '<a href="' . $imprintUrl . '">' . $strings['ui']['imprintLabel'] . '</a>';
        }

        $sections = [
            [
                'title' => $strings['preferencesModal']['intro']['title'],
                'description' => $strings['preferencesModal']['intro']['description'],
            ],
            [
                'title' => $strings['categories']['necessary']['title'],
                'description' => $strings['categories']['necessary']['description'],
                'linkedCategory' => 'necessary',
            ],
        ];

        foreach (static::categories() as $category) {
            $sections[] = [
                'title' => $strings['categories'][$category]['title'],
                'description' => $strings['categories'][$category]['description'],
                'linkedCategory' => $category,
            ];
        }

        $moreInfo = $strings['preferencesModal']['moreInfo']['description'];

        if ($privacyUrl !== null) {
            // A separate label: "privacyLabel" is a nav title, which does not
            // read as German inside a sentence ("in unserer Datenschutz").
            $moreInfo = str_replace(
                '{privacy}',
                '<a href="' . $privacyUrl . '">' . $strings['ui']['privacyProse'] . '</a>',
                $moreInfo
            );
        } else {
            // Drop the sentence containing the placeholder rather than render a
            // dangling link.
            $moreInfo = trim(preg_replace('/[^.!?]*\{privacy\}[^.!?]*[.!?]\s*/u', '', $moreInfo));
        }

        $sections[] = [
            'title' => $strings['preferencesModal']['moreInfo']['title'],
            'description' => $moreInfo,
        ];

        // Name the categories this site actually uses instead of shipping a
        // description that promises a marketing section which is not there.
        $titles = array_map(
            fn (string $category): string => $strings['categories'][$category]['title'],
            static::categories()
        );

        // {{revisionMessage}} is the library's own placeholder and resolves to an
        // empty string unless the revision changed. Appended here so a site that
        // overrides the description cannot accidentally drop it.
        $description = str_replace(
            '{categories}',
            static::joinList($titles, $strings['ui']['listConjunction']),
            $strings['consentModal']['description']
        ) . ' {{revisionMessage}}';

        $consentModal = [
            'title' => $strings['consentModal']['title'],
            'description' => $description,
            'acceptAllBtn' => $strings['consentModal']['acceptAllBtn'],
            'acceptNecessaryBtn' => $strings['consentModal']['acceptNecessaryBtn'],
            'showPreferencesBtn' => $strings['consentModal']['showPreferencesBtn'],
            'revisionMessage' => $strings['consentModal']['revisionMessage'],
        ];

        if ($links !== []) {
            $consentModal['footer'] = implode('', $links);
        }

        return [
            'consentModal' => $consentModal,
            'preferencesModal' => [
                'title' => $strings['preferencesModal']['title'],
                'acceptAllBtn' => $strings['preferencesModal']['acceptAllBtn'],
                'acceptNecessaryBtn' => $strings['preferencesModal']['acceptNecessaryBtn'],
                'savePreferencesBtn' => $strings['preferencesModal']['savePreferencesBtn'],
                'closeIconLabel' => $strings['preferencesModal']['closeIconLabel'],
                'serviceCounterLabel' => $strings['preferencesModal']['serviceCounterLabel'],
                'sections' => $sections,
            ],
        ];
    }

    /**
     * "a, b und c" — used to list the active categories in the banner text.
     */
    protected static function joinList(array $items, string $conjunction): string
    {
        if (count($items) < 2) {
            return implode('', $items);
        }

        $last = array_pop($items);

        return implode(', ', $items) . ' ' . $conjunction . ' ' . $last;
    }

    /**
     * Turn the friendly `_ga*` glob syntax from the config into the
     * {name, regex} pairs the JS side converts to RegExp objects.
     */
    protected static function autoClear(string $category): array
    {
        $patterns = static::option('autoClear.' . $category, []);
        $cookies = [];

        foreach ((array) $patterns as $pattern) {
            $cookies[] = str_contains($pattern, '*')
                ? ['name' => '^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$', 'regex' => true]
                : ['name' => $pattern, 'regex' => false];
        }

        return $cookies;
    }

    /**
     * Consent Mode v2 default state — everything denied except security_storage.
     * Emitted in <head> before any tag loader.
     */
    public static function defaultSignals(): array
    {
        $signals = [
            'ad_storage' => 'denied',
            'ad_user_data' => 'denied',
            'ad_personalization' => 'denied',
            'analytics_storage' => 'denied',
            'functionality_storage' => 'denied',
            'personalization_storage' => 'denied',
            'security_storage' => 'granted',
        ];

        // In opt-out mode the categories start enabled, so their signals must
        // start granted too — otherwise tags stay blocked until the visitor
        // interacts with a banner they are not required to interact with.
        if (static::isOptin() === false) {
            foreach (static::categories() as $category) {
                foreach (static::SIGNALS[$category] as $signal) {
                    $signals[$signal] = 'granted';
                }
            }
        }

        return $signals;
    }

    /**
     * Host → display name for the embed placeholder. Anything unknown falls
     * back to the bare host.
     */
    public const SERVICES = [
        'google.com/maps' => 'Google Maps',
        'maps.google.com' => 'Google Maps',
        'youtube.com' => 'YouTube',
        'youtube-nocookie.com' => 'YouTube',
        'youtu.be' => 'YouTube',
        'vimeo.com' => 'Vimeo',
        'open.spotify.com' => 'Spotify',
        'soundcloud.com' => 'SoundCloud',
        'facebook.com' => 'Meta',
        'instagram.com' => 'Instagram',
        'twitter.com' => 'X',
        'x.com' => 'X',
        'calendly.com' => 'Calendly',
    ];

    public static function serviceName(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $needle = $host . $path;

        foreach (static::SERVICES as $match => $name) {
            if (str_contains($needle, $match) === true) {
                return $name;
            }
        }

        return preg_replace('/^www\./', '', $host) ?: '';
    }

    /**
     * Wrap third-party iframes in the consent gate.
     *
     * Registered on `kirbytext:after`, so iframes editors paste into content are
     * gated without touching the content files — the only way to cover embeds
     * that are already live in production.
     */
    public static function gateIframes(string $html): string
    {
        if (str_contains($html, '<iframe') === false) {
            return $html;
        }

        $category = static::option('gateKirbytextCategory', 'external');

        if (in_array($category, static::categories(), true) === false) {
            return $html;
        }

        $siteHost = parse_url(App::instance()->url(), PHP_URL_HOST);

        return preg_replace_callback(
            '#<iframe\b[^>]*>(?:.*?</iframe>)?#is',
            function (array $match) use ($category, $siteHost): string {
                $iframe = $match[0];

                if (preg_match('#\bsrc\s*=\s*["\']([^"\']+)["\']#i', $iframe, $src) !== 1) {
                    return $iframe;
                }

                $host = parse_url($src[1], PHP_URL_HOST);

                // Same-origin and relative embeds are ours to serve.
                if ($host === null || $host === $siteHost) {
                    return $iframe;
                }

                return snippet('consent/placeholder', [
                    'category' => $category,
                    'service' => static::serviceName($src[1]),
                    'html' => $iframe,
                ], return: true);
            },
            $html
        );
    }

    /**
     * Everything the JS bundle needs, ready to be JSON-encoded.
     */
    public static function config(): array
    {
        $strings = static::strings();
        $categories = [];

        foreach (static::categories() as $category) {
            $categories[$category] = [
                'enabled' => static::isOptin() === false,
                'readOnly' => false,
                'autoClear' => static::autoClear($category),
            ];
        }

        return [
            'mode' => static::mode(),
            'language' => static::languageCode(),
            'revision' => (int) static::option('revision', 0),
            'expiresAfterDays' => (int) static::option('expiresAfterDays', 365),
            'layout' => static::option('layout', 'box'),
            'position' => static::option('position', 'bottom right'),
            'darkmode' => (bool) static::option('darkmode', false),
            'consentMode' => (bool) static::option('consentMode', true),
            'signals' => static::SIGNALS,
            'categories' => $categories,
            'translations' => static::libraryTranslations($strings),
            'ui' => $strings['ui'],
        ];
    }
}
