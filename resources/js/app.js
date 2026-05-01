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

const applyDocumentLocale = (locale) => {
    const normalizedLocale = locale || 'de';
    const direction = rtlLocales.includes(normalizedLocale) ? 'rtl' : 'ltr';

    document.documentElement.lang = normalizedLocale;
    document.documentElement.dir = direction;
    document.documentElement.classList.toggle('is-rtl', direction === 'rtl');
    document.documentElement.classList.toggle('is-ltr', direction === 'ltr');
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

        watch(i18n.global.locale, (locale) => {
            applyDocumentLocale(locale);
        });

        router.on('success', (event) => {
            const locale = event.detail.page.props.locale;

            if (locale) {
                i18n.global.locale.value = locale;
                applyDocumentLocale(locale);
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
