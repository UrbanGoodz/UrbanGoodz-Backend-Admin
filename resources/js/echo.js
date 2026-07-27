import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const broadcastConnection =
    process.env.MIX_BROADCAST_CONNECTION
    ?? process.env.MIX_BROADCAST_DRIVER
    ?? 'log';

const pusherKey = process.env.MIX_PUSHER_APP_KEY;
const reverbKey = process.env.MIX_REVERB_APP_KEY;

const connectionOptions = broadcastConnection === 'reverb'
    ? {
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: process.env.MIX_REVERB_HOST,
        wsPort: process.env.MIX_REVERB_PORT ?? 80,
        wssPort: process.env.MIX_REVERB_PORT ?? 443,
        forceTLS: (process.env.MIX_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    }
    : {
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: process.env.MIX_PUSHER_APP_CLUSTER ?? 'us2',
        forceTLS: true,
        authEndpoint: '/broadcasting/auth',
        enabledTransports: ['ws', 'wss'],
    };

if (
    (broadcastConnection === 'pusher' && pusherKey)
    || (broadcastConnection === 'reverb' && reverbKey)
) {
    window.Echo = new Echo(connectionOptions);
}
