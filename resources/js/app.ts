import '../css/app.css';
import { createInertiaApp,router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';



const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// TikTok Pixel: Track Inertia page navigation
let isFirstNavigation = true;

router.on('navigate', () => {
    if (isFirstNavigation) {
        isFirstNavigation = false;
        return;
    }

    if (typeof window.ttq !== 'undefined') {
        window.ttq.page();
    }
});

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,

    // wsHost: import.meta.env.VITE_REVERB_HOST,
    wsHost:'rukhsanapackaging.online',

    wsPort: 80,
    wssPort: 443,

    forceTLS: true,

    enabledTransports: ['ws', 'wss'],
});



// window.Echo = new Echo({
//     broadcaster: 'reverb',
//     key: import.meta.env.VITE_REVERB_APP_KEY,

//     wsHost: import.meta.env.VITE_REVERB_HOST,
//     wsPort: Number(import.meta.env.VITE_REVERB_PORT),

//     forceTLS: false,

//     enabledTransports: ['ws'],
// });
// This will set light / dark mode on page load...
initializeTheme();
