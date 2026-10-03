import '../css/app.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Toast from './Components/Toast.vue';

const appName = import.meta.env.VITE_APP_NAME || 'NABAAD Bank';

// A 419 means the session/CSRF token expired mid-form (e.g. a long KYC
// registration left open). Rather than showing Inertia's raw error-page
// modal, tell the user plainly and reload so their next attempt gets a
// fresh token — better than a dead screen, even though the form's inputs
// are lost either way once the token itself is invalid.
router.on('invalid', (event) => {
    if (event.detail.response?.status === 419) {
        event.preventDefault();
        alert('Your session timed out for security. The page will reload — please try again.');
        window.location.reload();
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Toast is rendered as a sibling of Inertia's own <App>, not inside
        // any page/layout — it mounts once and persists across every
        // navigation, so flash messages surface even on bare auth pages
        // (Login, Register, ...) that never wrap themselves in AdminLayout
        // or PortalLayout.
        return createApp({ render: () => h('div', [h(App, props), h(Toast)]) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#0B2447',
    },
});
