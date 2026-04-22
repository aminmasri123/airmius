import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import 'line-awesome/dist/line-awesome/css/line-awesome.min.css';

// 👉 NEU
import { createI18n } from 'vue-i18n'

// 👉 Sprachdateien importieren
import de from './lang/de.json'
import en from './lang/en.json'
import fr from './lang/fr.json'


const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// 👉 Sprache aus localStorage holen
const savedLang = localStorage.getItem('lang') || 'de'

// 👉 i18n erstellen
const i18n = createI18n({
    legacy: false,
    locale: savedLang,
    fallbackLocale: 'de',
    messages: {
        de,
        en,
        fr
    }
})

window.addEventListener('storage', (event) => {
    if (event.key === 'logout') {
        window.location.href = route('welcome');
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n) // 👉 HIER EINBAUEN
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
