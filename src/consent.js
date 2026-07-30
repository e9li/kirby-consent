/**
 * Cookie consent runtime.
 *
 * Reads the configuration PHP handed over as a JSON script tag, boots
 * vanilla-cookieconsent, keeps Google Consent Mode v2 in sync and activates
 * embeds parked in <template> by snippets/consent/placeholder.php.
 */
import * as CookieConsent from "vanilla-cookieconsent";

const configEl = document.getElementById("kirby-consent-config");

if (configEl) {
    boot(JSON.parse(configEl.textContent));
}

function boot(cfg) {
    if (cfg.darkmode) {
        document.documentElement.classList.add("cc--darkmode");
    }

    const categories = {
        necessary: { enabled: true, readOnly: true },
    };

    for (const [name, def] of Object.entries(cfg.categories)) {
        categories[name] = {
            enabled: def.enabled,
            readOnly: false,
        };

        if (def.autoClear?.length) {
            categories[name].autoClear = {
                cookies: def.autoClear.map((cookie) => ({
                    name: cookie.regex ? new RegExp(cookie.name) : cookie.name,
                })),
            };
        }
    }

    const sync = () => {
        updateConsentMode(cfg);
        activateAcceptedEmbeds();
    };

    CookieConsent.run({
        revision: cfg.revision,
        cookie: {
            expiresAfterDays: cfg.expiresAfterDays,
        },
        guiOptions: {
            consentModal: {
                layout: cfg.layout,
                position: cfg.position,
                // Accept and reject must be visually equivalent — DSK
                // Orientierungshilfe Telemedien, VG Hannover 03/2025.
                equalWeightButtons: true,
                flipButtons: false,
            },
            preferencesModal: {
                layout: cfg.layout,
                equalWeightButtons: true,
            },
        },
        categories,
        language: {
            default: cfg.language,
            translations: {
                [cfg.language]: cfg.translations,
            },
        },
        onFirstConsent: sync,
        onConsent: sync,
        onChange: ({ changedCategories }) => {
            updateConsentMode(cfg);

            // A withdrawn category must not leave a live third-party embed on
            // the page, and there is no way to un-embed an iframe that already
            // phoned home. Reload instead.
            const withdrawn = changedCategories.filter(
                (category) => !CookieConsent.acceptedCategory(category)
            );

            if (withdrawn.some(hasLoadedEmbed)) {
                window.location.reload();
                return;
            }

            activateAcceptedEmbeds();
        },
    });

    document.addEventListener("click", (event) => {
        const once = event.target.closest("[data-cc-embed-load]");

        if (once) {
            activate(once.closest("[data-cc-embed]"));
            return;
        }

        const always = event.target.closest("[data-cc-embed-always]");

        if (always) {
            const wrapper = always.closest("[data-cc-embed]");
            acceptAdditionally(wrapper?.dataset.ccEmbedCategory);
        }
    });

    // Let host code reach the API without bundling the library again.
    window.KirbyConsent = CookieConsent;
}

function gtag() {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(arguments);
}

/**
 * Map the accepted categories onto the Consent Mode v2 signals. Only signals
 * belonging to a configured category are touched, so a site without a marketing
 * category leaves ad_* at the denied default.
 */
function updateConsentMode(cfg) {
    if (!cfg.consentMode) {
        return;
    }

    const update = {};

    for (const [category, signals] of Object.entries(cfg.signals)) {
        if (!(category in cfg.categories)) {
            continue;
        }

        const state = CookieConsent.acceptedCategory(category) ? "granted" : "denied";

        for (const signal of signals) {
            update[signal] = state;
        }
    }

    if (Object.keys(update).length > 0) {
        gtag("consent", "update", update);
    }
}

/**
 * Add a category to the current selection instead of replacing it —
 * acceptCategory() overwrites whatever was accepted before.
 */
function acceptAdditionally(category) {
    if (!category) {
        return;
    }

    const accepted = CookieConsent.getUserPreferences().acceptedCategories || [];

    CookieConsent.acceptCategory([...new Set([...accepted, category])]);
}

function embeds(category) {
    const selector = category
        ? `[data-cc-embed][data-cc-embed-category="${category}"]`
        : "[data-cc-embed]";

    return document.querySelectorAll(selector);
}

function hasLoadedEmbed(category) {
    for (const wrapper of embeds(category)) {
        if (wrapper.hasAttribute("data-cc-embed-loaded")) {
            return true;
        }
    }

    return false;
}

function activateAcceptedEmbeds() {
    for (const wrapper of embeds()) {
        if (
            !wrapper.hasAttribute("data-cc-embed-loaded") &&
            CookieConsent.acceptedCategory(wrapper.dataset.ccEmbedCategory)
        ) {
            activate(wrapper);
        }
    }
}

function activate(wrapper) {
    if (!wrapper || wrapper.hasAttribute("data-cc-embed-loaded")) {
        return;
    }

    const template = wrapper.querySelector("[data-cc-embed-content]");

    if (!template) {
        return;
    }

    const fragment = template.content.cloneNode(true);

    // Scripts cloned out of a <template> are inert; rebuild them so an embed
    // that ships its own loader still works.
    for (const original of fragment.querySelectorAll("script")) {
        const script = document.createElement("script");

        for (const { name, value } of original.attributes) {
            script.setAttribute(name, value);
        }

        script.textContent = original.textContent;
        original.replaceWith(script);
    }

    wrapper.querySelector("[data-cc-embed-notice]")?.remove();
    template.remove();
    wrapper.appendChild(fragment);
    wrapper.setAttribute("data-cc-embed-loaded", "");
}
