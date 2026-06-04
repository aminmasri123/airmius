import './bootstrap';
import '../css/app.css';

import { createApp, h, watch } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import 'line-awesome/dist/line-awesome/css/line-awesome.min.css';

import { useTheme } from './services/useTheme';
import { createI18n } from 'vue-i18n';

import sports from './lang/sports';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const rtlLocales = ['ar'];
const supportedLocales = ['de', 'en', 'fr', 'ar'];
const localeMessageLoaders = import.meta.glob('./lang/*.json');
const loadedLocales = new Set();
const autoTranslatedTextNodes = new WeakMap();
const autoTranslatedAttributes = new WeakMap();
const autoTranslateAttributes = ['placeholder', 'title', 'aria-label', 'alt'];
let autoTranslationTouched = false;

const normalizeLocale = (locale) => {
    return supportedLocales.includes(locale) ? locale : 'de';
};

const sportsMessagesFor = (locale) => ({
    sports: sports[locale] || sports.de,
    sport_categories: sports.categories?.[locale] || sports.categories?.de || {},
});

const loadLocaleMessages = async (i18n, locale) => {
    const normalizedLocale = normalizeLocale(locale);

    if (loadedLocales.has(normalizedLocale)) {
        return normalizedLocale;
    }

    const loader = localeMessageLoaders[`./lang/${normalizedLocale}.json`] || localeMessageLoaders['./lang/de.json'];
    const module = await loader();

    i18n.global.setLocaleMessage(normalizedLocale, {
        ...(module.default || module),
        ...sportsMessagesFor(normalizedLocale),
    });
    loadedLocales.add(normalizedLocale);

    return normalizedLocale;
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

    root.querySelectorAll?.('[placeholder], [title], [aria-label], img[alt]').forEach((element) => {
        if (element.closest('[data-no-auto-translate]')) {
            return;
        }

        let originals = autoTranslatedAttributes.get(element);

        if (!originals) {
            originals = {};
            autoTranslatedAttributes.set(element, originals);
        }

        autoTranslateAttributes.forEach((attribute) => {
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
    const run = () => {
        const locale = i18n.global.locale.value || 'de';

        if (locale === 'de' && !autoTranslationTouched) {
            return;
        }

        if (pending) {
            return;
        }

        pending = true;
        window.requestAnimationFrame(() => {
            pending = false;
            autoTranslateVisibleText(root, i18n);
        });
    };

    const observer = new MutationObserver(run);
    observer.observe(root, {
        childList: true,
        subtree: true,
        characterData: true,
        attributes: true,
        attributeFilter: autoTranslateAttributes,
    });
    run();
    window.setTimeout(run, 0);
    window.setTimeout(run, 250);

    return run;
};

window.addEventListener('storage', (event) => {
    if (event.key === 'logout') {
        window.location.href = route('welcome');
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    async setup({ el, App, props, plugin }) {
        // Theme früh laden
        const theme = localStorage.getItem('theme')
        || props.initialPage.props.auth?.user?.theme
        || 'dark';

        const { initTheme } = useTheme()
        initTheme(theme)

        // i18n erst HIER erstellen, damit 'props' verfügbar ist
        const initialLocale = normalizeLocale(props.initialPage.props.locale || localStorage.getItem('lang') || 'de');

        const i18n = createI18n({
            legacy: false,
            // PRIORITÄT: 1. Server-Prop (DB), 2. LocalStorage, 3. Fallback 'de'
            locale: initialLocale,
            fallbackLocale: 'en',
            messages: {},
        });

        await loadLocaleMessages(i18n, initialLocale);
        applyDocumentLocale(i18n.global.locale.value);
        const runAutoTranslation = installAutoTranslation(el, i18n);

        watch(i18n.global.locale, async (locale) => {
            const normalizedLocale = await loadLocaleMessages(i18n, locale);
            if (locale !== normalizedLocale) {
                i18n.global.locale.value = normalizedLocale;
            }
            applyDocumentLocale(normalizedLocale);
            runAutoTranslation();
        });

        router.on('success', async (event) => {
            const locale = normalizeLocale(event.detail.page.props.locale);

            if (locale) {
                await loadLocaleMessages(i18n, locale);
                i18n.global.locale.value = locale;
                applyDocumentLocale(locale);
                runAutoTranslation();
            }
        });

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: 'var(--progress)',
    },
});

