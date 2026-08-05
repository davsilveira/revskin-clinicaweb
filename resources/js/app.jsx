import './bootstrap';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';

// Import explícito: garante a página no bundle mesmo se o glob/eager falhar no build.
import ImportacaoClw2 from './Pages/Tools/ImportacaoClw2';

const appName = import.meta.env.VITE_APP_NAME || 'ClinicaWeb';

const explicitPages = {
    'Tools/ImportacaoClw2': ImportacaoClw2,
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        const page = pages[`./Pages/${name}.jsx`] ?? explicitPages[name];
        if (!page) {
            throw new Error(`Página Inertia não encontrada: ${name}`);
        }
        return page.default ? page : { default: page };
    },
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#3b82f6',
    },
});

