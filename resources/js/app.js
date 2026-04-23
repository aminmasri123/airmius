import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import 'line-awesome/dist/line-awesome/css/line-awesome.min.css';

import { createI18n } from 'vue-i18n';

// Sprachdateien importieren
import de from './lang/de.json';
import en from './lang/en.json';
import fr from './lang/fr.json';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

window.addEventListener('storage', (event) => {
    if (event.key === 'logout') {
        window.location.href = route('welcome');
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        // i18n erst HIER erstellen, damit 'props' verfügbar ist
            const i18n = createI18n({
            legacy: false,
            // PRIORITÄT: 1. Server-Prop (DB), 2. LocalStorage, 3. Fallback 'de'
            locale: props.initialPage.props.locale || localStorage.getItem('lang') || 'de',
            fallbackLocale: 'en',
            messages: { de, en, fr }
        });

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
