import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import 'line-awesome/dist/line-awesome/css/line-awesome.min.css';

import { useTheme } from './services/useTheme';
import { activateApplicationLocale, registerLocaleActivator } from './services/localeRuntime';
import { createI18n } from 'vue-i18n';

import sports from './lang/sports';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const rtlLocales = ['ar'];
const supportedLocales = ['de', 'en', 'fr', 'ar'];
const localeMessageLoaders = import.meta.glob('./lang/*.json');
const autoLocaleMessageLoaders = import.meta.glob('./lang/auto/*.json');
const loadedLocales = new Set();
const loadedAutoLocales = new Set();
const localeLoadPromises = new Map();
const autoLocaleLoadPromises = new Map();
const autoTranslatedTextNodes = new WeakMap();
const autoTranslatedAttributes = new WeakMap();
const autoTranslateAttributeNames = [
    'placeholder',
    'title',
    'aria-label',
    'alt',
    'value',
    'aria-placeholder',
    'aria-expanded',
];
let autoTranslationTouched = false;

const normalizeLocale = (locale) => {
    const language = String(locale || '')
        .trim()
        .toLowerCase()
        .replace('_', '-')
        .split('-')[0];

    return supportedLocales.includes(language) ? language : 'de';
};

const sportsMessagesFor = (locale) => ({
    sports: sports[locale] || sports.de,
    sport_categories: sports.categories?.[locale] || sports.categories?.de || {},
});

const mergeLocaleMessages = (i18n, locale, messages) => {
    const existing = i18n.global.getLocaleMessage(locale) || {};

    i18n.global.setLocaleMessage(locale, {
        ...existing,
        ...messages,
    });
};

const loadLocaleMessages = (i18n, locale) => {
    const normalizedLocale = normalizeLocale(locale);

    if (loadedLocales.has(normalizedLocale)) {
        return Promise.resolve(normalizedLocale);
    }

    if (localeLoadPromises.has(normalizedLocale)) {
        return localeLoadPromises.get(normalizedLocale);
    }

    const promise = (async () => {
        const loader = localeMessageLoaders[`./lang/${normalizedLocale}.json`];

        try {
            if (!loader) {
                throw new Error(`Missing locale catalog: ${normalizedLocale}`);
            }

            const module = await loader();

            mergeLocaleMessages(i18n, normalizedLocale, {
                ...(module.default || module),
                ...sportsMessagesFor(normalizedLocale),
            });
            loadedLocales.add(normalizedLocale);

            return normalizedLocale;
        } catch (error) {
            console.error(`[Airmius i18n] Could not load ${normalizedLocale}.`, error);

            if (normalizedLocale !== 'de') {
                return loadLocaleMessages(i18n, 'de');
            }

            mergeLocaleMessages(i18n, 'de', sportsMessagesFor('de'));
            loadedLocales.add('de');

            return 'de';
        }
    })().finally(() => {
        localeLoadPromises.delete(normalizedLocale);
    });

    localeLoadPromises.set(normalizedLocale, promise);

    return promise;
};

const loadAutoLocaleMessages = (i18n, locale) => {
    const normalizedLocale = normalizeLocale(locale);

    // German is the source text. Its automatic dictionary would only repeat
    // the already rendered strings, so it never needs to cross the network.
    if (normalizedLocale === 'de' || loadedAutoLocales.has(normalizedLocale)) {
        return Promise.resolve(normalizedLocale);
    }

    if (autoLocaleLoadPromises.has(normalizedLocale)) {
        return autoLocaleLoadPromises.get(normalizedLocale);
    }

    const promise = (async () => {
        const loader = autoLocaleMessageLoaders[`./lang/auto/${normalizedLocale}.json`];

        try {
            if (!loader) {
                throw new Error(`Missing automatic locale catalog: ${normalizedLocale}`);
            }

            const module = await loader();
            mergeLocaleMessages(i18n, normalizedLocale, module.default || module);
        } catch (error) {
            // Automatic source-text translation is progressive enhancement.
            // Semantic keys remain available when this optional chunk fails.
            console.warn(`[Airmius i18n] Could not load automatic ${normalizedLocale} translations.`, error);
        }

        loadedAutoLocales.add(normalizedLocale);

        return normalizedLocale;
    })().finally(() => {
        autoLocaleLoadPromises.delete(normalizedLocale);
    });

    autoLocaleLoadPromises.set(normalizedLocale, promise);

    return promise;
};

const applyDocumentLocale = (locale) => {
    const normalizedLocale = normalizeLocale(locale || 'de');
    const direction = rtlLocales.includes(normalizedLocale) ? 'rtl' : 'ltr';

    document.documentElement.lang = normalizedLocale;
    document.documentElement.dir = direction;
    document.documentElement.classList.toggle('is-rtl', direction === 'rtl');
    document.documentElement.classList.toggle('is-ltr', direction === 'ltr');
};

const autoDictionaryFor = (i18n, locale) => {
    const messages = i18n.global.messages.value?.[locale] || {};

    return messages.auto || {};
};

const autoPatternsFor = (i18n, locale) => {
    const messages = i18n.global.messages.value?.[locale] || {};

    return Array.isArray(messages.auto_patterns) ? messages.auto_patterns : [];
};

const translateAutoPattern = (i18n, locale, source) => {
    const patterns = autoPatternsFor(i18n, locale);

    for (const pattern of patterns) {
        if (!pattern?.source || !pattern?.target) {
            continue;
        }

        try {
            const regex = new RegExp(pattern.source, pattern.flags || '');

            if (regex.test(source)) {
                return source.replace(regex, pattern.target);
            }
        } catch (error) {
            // Ignore invalid optional auto-translation patterns.
        }
    }

    return null;
};

const translateAutoText = (i18n, locale, text) => {
    const source = String(text || '').trim();

    if (!source || locale === 'de') {
        return source;
    }

    const dictionary = autoDictionaryFor(i18n, locale);
    const autoTranslation = dictionary[source];

    if (autoTranslation) {
        return autoTranslation;
    }

    const trailingPunctuation = source.match(/([.!?])$/)?.[1];
    if (trailingPunctuation) {
        const withoutTrailingPunctuation = source.slice(0, -trailingPunctuation.length).trim();
        const normalizedTranslation = dictionary[withoutTrailingPunctuation];

        if (normalizedTranslation) {
            return `${normalizedTranslation}${trailingPunctuation}`;
        }
    }

    const leadingPunctuation = source.match(/^([.!?])/)?.[1];
    if (leadingPunctuation) {
        const withoutLeadingPunctuation = source.slice(leadingPunctuation.length).trim();
        const normalizedTranslation = dictionary[withoutLeadingPunctuation];

        if (normalizedTranslation) {
            return normalizedTranslation;
        }
    }

    const patternTranslation = translateAutoPattern(i18n, locale, source);

    if (patternTranslation) {
        return patternTranslation;
    }

    return i18n.global.te(source, locale) ? i18n.global.t(source) : source;
};

const preserveOuterWhitespace = (original, translated) => {
    const leading = original.match(/^\s*/)?.[0] || '';
    const trailing = original.match(/\s*$/)?.[0] || '';

    return `${leading}${translated}${trailing}`;
};

const autoTranslateVisibleText = (root, i18n) => {
    if (!root || typeof document === 'undefined') {
        return;
    }

    const locale = i18n.global.locale.value || 'de';

    if (locale === 'de' && !autoTranslationTouched) {
        return;
    }

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            const parent = node.parentElement;

            if (!parent || !node.nodeValue.trim()) {
                return NodeFilter.FILTER_REJECT;
            }

            if (['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEXTAREA'].includes(parent.tagName)) {
                return NodeFilter.FILTER_REJECT;
            }

            if (parent.closest('[data-no-auto-translate]')) {
                return NodeFilter.FILTER_REJECT;
            }

            return NodeFilter.FILTER_ACCEPT;
        },
    });

    const textNodes = [];
    while (walker.nextNode()) {
        textNodes.push(walker.currentNode);
    }

    textNodes.forEach((node) => {
        const cached = autoTranslatedTextNodes.get(node);
        let original = cached?.original || node.nodeValue;

        if (cached && locale !== 'de' && node.nodeValue !== cached.translated) {
            original = node.nodeValue;
        }

        const translated = translateAutoText(i18n, locale, original);
        const nextValue = locale === 'de' ? original : preserveOuterWhitespace(original, translated);

        autoTranslatedTextNodes.set(node, { original, translated: nextValue });

        if (node.nodeValue !== nextValue) {
            node.nodeValue = nextValue;
        }
    });

    root.querySelectorAll?.('*').forEach((element) => {
        const hasTranslatableAttr = Array
            .from(element.attributes || [])
            .some((attribute) => autoTranslateAttributeNames.includes(attribute.name));

        if (!hasTranslatableAttr) {
            return;
        }
        if (element.closest('[data-no-auto-translate]')) {
            return;
        }

        let originals = autoTranslatedAttributes.get(element);

        if (!originals) {
            originals = {};
            autoTranslatedAttributes.set(element, originals);
        }

        autoTranslateAttributeNames.forEach((attribute) => {
            if (!element.hasAttribute(attribute)) {
                return;
            }

            const currentValue = element.getAttribute(attribute);
            const cached = originals[attribute];
            let original = cached?.original || currentValue;

            if (cached && locale !== 'de' && currentValue !== cached.translated) {
                original = currentValue;
            }

            const translated = translateAutoText(i18n, locale, original);
            const nextValue = locale === 'de' ? original : translated;

            originals[attribute] = { original, translated: nextValue };

            if (element.getAttribute(attribute) !== nextValue) {
                element.setAttribute(attribute, nextValue);
            }
        });
    });

    autoTranslationTouched = locale !== 'de';
};

const installAutoTranslation = (root, i18n) => {
    let pending = false;
    let fullScanRequested = false;
    const pendingRoots = new Set();

    const normalizedRoot = (node) => {
        if (node?.nodeType === Node.TEXT_NODE) {
            return node.parentElement;
        }

        return node?.nodeType === Node.ELEMENT_NODE ? node : null;
    };

    const queueRoot = (node) => {
        const candidate = normalizedRoot(node);

        if (!candidate || (candidate !== root && !root.contains(candidate))) {
            return;
        }

        for (const queued of pendingRoots) {
            if (queued === candidate || queued.contains(candidate)) {
                return;
            }

            if (candidate.contains(queued)) {
                pendingRoots.delete(queued);
            }
        }

        pendingRoots.add(candidate);
    };

    const schedule = () => {
        const locale = i18n.global.locale.value || 'de';

        if (locale === 'de' && !autoTranslationTouched) {
            pendingRoots.clear();
            fullScanRequested = false;
            return;
        }

        if (pending) {
            return;
        }

        pending = true;
        window.requestAnimationFrame(() => {
            pending = false;
            const targets = fullScanRequested ? [root] : Array.from(pendingRoots);

            fullScanRequested = false;
            pendingRoots.clear();
            targets.forEach((target) => autoTranslateVisibleText(target, i18n));
        });
    };

    const run = () => {
        fullScanRequested = true;
        pendingRoots.clear();
        schedule();
    };

    const observer = new MutationObserver((records) => {
        const locale = i18n.global.locale.value || 'de';

        if (locale === 'de' && !autoTranslationTouched) {
            return;
        }

        records.forEach((record) => {
            if (record.type === 'childList') {
                record.addedNodes.forEach(queueRoot);
            } else {
                queueRoot(record.target);
            }
        });

        if (pendingRoots.size > 0) {
            schedule();
        }
    });
    observer.observe(root, {
        childList: true,
        subtree: true,
        characterData: true,
        attributes: true,
        attributeFilter: autoTranslateAttributeNames,
    });
    run();
    window.setTimeout(run, 0);

    return run;
};

window.addEventListener('storage', (event) => {
    if (event.key === 'logout') {
        window.location.href = route('welcome');
    }
});

createInertiaApp({
    title: (title) => {
        const pageTitle = String(title || '').trim();

        if (!pageTitle) {
            return appName;
        }

        return pageTitle.toLowerCase().includes(appName.toLowerCase())
            ? pageTitle
            : `${pageTitle} - ${appName}`;
    },
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    async setup({ el, App, props, plugin }) {
        // Theme früh laden
        const theme = localStorage.getItem('theme')
        || props.initialPage.props.auth?.user?.theme
        || 'air';

        const { initTheme } = useTheme()
        initTheme(theme)

        // i18n erst HIER erstellen, damit 'props' verfügbar ist
        const initialLocale = normalizeLocale(props.initialPage.props.locale || localStorage.getItem('lang') || 'de');

        const i18n = createI18n({
            legacy: false,
            // PRIORITÄT: 1. Server-Prop (DB), 2. LocalStorage, 3. Fallback 'de'
            locale: initialLocale,
            fallbackLocale: 'de',
            messages: {},
        });

        const activeInitialLocale = await loadLocaleMessages(i18n, initialLocale);

        i18n.global.locale.value = activeInitialLocale;
        localStorage.setItem('lang', activeInitialLocale);
        applyDocumentLocale(activeInitialLocale);
        const runAutoTranslation = installAutoTranslation(el, i18n);
        let localeActivationSequence = 0;
        let pendingLocaleActivation = null;

        const scheduleAutoLocale = (locale, sequence) => {
            if (locale === 'de') {
                return;
            }

            const load = () => {
                if (sequence !== localeActivationSequence || i18n.global.locale.value !== locale) {
                    return;
                }

                void loadAutoLocaleMessages(i18n, locale).then(() => {
                    if (sequence === localeActivationSequence && i18n.global.locale.value === locale) {
                        runAutoTranslation();
                    }
                });
            };

            if ('requestIdleCallback' in window) {
                window.requestIdleCallback(load, { timeout: 250 });
            } else {
                window.setTimeout(load, 0);
            }
        };

        const activateLocale = (locale) => {
            const requestedLocale = normalizeLocale(locale);

            if (pendingLocaleActivation?.locale === requestedLocale) {
                return pendingLocaleActivation.promise;
            }

            const sequence = ++localeActivationSequence;
            let promise;

            promise = (async () => {
                const loadedLocale = await loadLocaleMessages(i18n, requestedLocale);

                if (sequence !== localeActivationSequence) {
                    return i18n.global.locale.value;
                }

                i18n.global.locale.value = loadedLocale;
                localStorage.setItem('lang', loadedLocale);
                applyDocumentLocale(loadedLocale);
                runAutoTranslation();
                scheduleAutoLocale(loadedLocale, sequence);

                return loadedLocale;
            })().finally(() => {
                if (pendingLocaleActivation?.promise === promise) {
                    pendingLocaleActivation = null;
                }
            });

            pendingLocaleActivation = { locale: requestedLocale, promise };

            return promise;
        };

        registerLocaleActivator(activateLocale);

        router.on('success', (event) => {
            const locale = event.detail.page.props.locale;

            if (typeof locale === 'string' && locale.trim() !== '') {
                void activateApplicationLocale(locale);
            }
        });

        const mountedApp = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);

        scheduleAutoLocale(activeInitialLocale, localeActivationSequence);

        return mountedApp;
    },
    progress: {
        color: 'var(--progress)',
    },
});
