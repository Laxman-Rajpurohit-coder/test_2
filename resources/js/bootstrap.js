import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Disable verbose pusher console logging in development
Pusher.logToConsole = false;

try {
    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
    if (reverbKey) {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
            disabledTransports: ['sockjs', 'xhr_streaming', 'xhr_polling'],
        });
    } else {
        window.Echo = {
            channel: () => ({ listen: () => ({ stopListening: () => {} }), stopListening: () => {} }),
            private: () => ({ listen: () => ({ stopListening: () => {} }), stopListening: () => {} }),
        };
    }
} catch (e) {
    console.warn('Echo initialization skipped:', e);
}
