import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { reverbConfig } from './reverbConfig';

window.Pusher = Pusher;

// Read at page load from the shell's data-reverb, not baked in at build time.
const reverb = reverbConfig();

export const echo = new Echo({
    broadcaster: 'reverb',
    key: reverb.key,
    wsHost: reverb.host,
    wsPort: reverb.port,
    wssPort: reverb.port,
    forceTLS: reverb.forceTLS,
    enabledTransports: ['ws', 'wss'],
});
