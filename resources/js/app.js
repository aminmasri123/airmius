import './bootstrap';
import '../css/app.css';

import { createApp, h, watch } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import 'line-awesome/dist/line-awesome/css/line-awesome.min.css';

import { useTheme } from './services/useTheme';
import { createI18n } from 'vue-i18n';

// Sprachdateien importieren
import de from './lang/de.json';
import en from './lang/en.json';
import fr from './lang/fr.json';
import ar from './lang/ar.json';
import sports from './lang/sports';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const rtlLocales = ['ar'];
const autoTranslatedTextNodes = new WeakMap();
const autoTranslatedAttributes = new WeakMap();
const autoTranslateAttributes = ['placeholder', 'title', 'aria-label', 'alt'];
let autoTranslationTouched = false;

const applyDocumentLocale = (locale) => {
    const normalizedLocale = locale || 'de';
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

const translateAutoText = (i18n, locale, text) => {
    const source = String(text || '').trim();

    if (!source || locale === 'de') {
        return source;
    }

    const autoTranslation = autoDictionaryFor(i18n, locale)[source];

    if (autoTranslation) {
        return autoTranslation;
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

            return NodeFilter.FILTER_ACCEPT;
        },
    });

    const textNodes = [];
    while (walker.nextNode()) {
        textNodes.push(walker.currentNode);
    }

    textNodes.forEach((node) => {
        const original = autoTranslatedTextNodes.get(node) || node.nodeValue;
        const translated = translateAutoText(i18n, locale, original);

        autoTranslatedTextNodes.set(node, original);
        node.nodeValue = locale === 'de' ? original : preserveOuterWhitespace(original, translated);
    });

    root.querySelectorAll?.('[placeholder], [title], [aria-label], img[alt]').forEach((element) => {
        let originals = autoTranslatedAttributes.get(element);

        if (!originals) {
            originals = {};
            autoTranslatedAttributes.set(element, originals);
        }

        autoTranslateAttributes.forEach((attribute) => {
            if (!element.hasAttribute(attribute)) {
                return;
            }

            originals[attribute] = originals[attribute] || element.getAttribute(attribute);
            const translated = translateAutoText(i18n, locale, originals[attribute]);
            element.setAttribute(attribute, locale === 'de' ? originals[attribute] : translated);
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
    observer.observe(root, { childList: true, subtree: true });
    run();

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
    setup({ el, App, props, plugin }) {
        // Theme früh laden
        const theme = localStorage.getItem('theme')
        || props.initialPage.props.auth?.user?.theme
        || 'dark';

        const { initTheme } = useTheme()
        initTheme(theme)

        // i18n erst HIER erstellen, damit 'props' verfügbar ist
            const i18n = createI18n({
            legacy: false,
            // PRIORITÄT: 1. Server-Prop (DB), 2. LocalStorage, 3. Fallback 'de'
            locale: props.initialPage.props.locale || localStorage.getItem('lang') || 'de',
            fallbackLocale: 'en',
            messages: {
                de: { ...de, sports: sports.de, sport_categories: sports.categories.de },
                en: { ...en, sports: sports.en, sport_categories: sports.categories.en },
                fr: { ...fr, sports: sports.fr, sport_categories: sports.categories.fr },
                ar: { ...ar, sports: sports.ar, sport_categories: sports.categories.ar },
            }
        });

        applyDocumentLocale(i18n.global.locale.value);
        const runAutoTranslation = installAutoTranslation(el, i18n);

        watch(i18n.global.locale, (locale) => {
            applyDocumentLocale(locale);
            runAutoTranslation();
        });

        router.on('success', (event) => {
            const locale = event.detail.page.props.locale;

            if (locale) {
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
