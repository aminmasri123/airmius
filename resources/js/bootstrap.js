import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

window.Pusher = Pusher;

const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbUsesTls = (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https';
const pusherUsesTls = (import.meta.env.VITE_PUSHER_SCHEME || 'https') === 'https';
const isLocalHost = ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);
const echoEnabled = import.meta.env.VITE_ECHO_ENABLED === 'true' || (!isLocalHost && import.meta.env.VITE_ECHO_ENABLED !== 'false');

if (echoEnabled && reverbKey) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
        forceTLS: reverbUsesTls,
        enabledTransports: reverbUsesTls ? ['wss'] : ['ws'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
        },
    });
} else if (echoEnabled && pusherKey) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
        wsHost: import.meta.env.VITE_PUSHER_HOST || `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1'}.pusher.com`,
        wsPort: Number(import.meta.env.VITE_PUSHER_PORT || 80),
        wssPort: Number(import.meta.env.VITE_PUSHER_PORT || 443),
        forceTLS: pusherUsesTls,
        enabledTransports: pusherUsesTls ? ['wss'] : ['ws'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
        },
    });
}
