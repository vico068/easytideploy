import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Read config from meta tags (injected at runtime by PHP) with fallback to Vite build-time vars
const getMeta = (name, fallback = '') =>
    document.head.querySelector(`meta[name="${name}"]`)?.content ?? fallback;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: getMeta('reverb-key', import.meta.env.VITE_REVERB_APP_KEY ?? ''),
    wsHost: getMeta('reverb-host', import.meta.env.VITE_REVERB_HOST ?? window.location.hostname),
    wsPort: parseInt(getMeta('reverb-port', import.meta.env.VITE_REVERB_PORT ?? '80')),
    wssPort: parseInt(getMeta('reverb-port', import.meta.env.VITE_REVERB_PORT ?? '443')),
    forceTLS: (getMeta('reverb-scheme', import.meta.env.VITE_REVERB_SCHEME ?? 'https')) === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.head.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
    },
});
