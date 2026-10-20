import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        chunkSizeWarningLimit: 1000,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    // WP-60 — GrapesJS + grapesjs-mjml (bundles mjml-browser): only reached
                    // through the lazy EmailBuilder chunk (docs/46-editor-spike.md §7).
                    if (/node_modules\/(grapesjs|grapesjs-mjml|backbone|backbone-undo|underscore)\//.test(id)) {
                        return 'grapesjs';
                    }
                    if (id.includes('node_modules/@fluentui/') || id.includes('node_modules/@griffel/')) {
                        return 'fluentui';
                    }
                    if (
                        id.includes('node_modules/react/') ||
                        id.includes('node_modules/react-dom/') ||
                        id.includes('node_modules/@inertiajs/')
                    ) {
                        return 'vendor';
                    }
                },
            },
        },
    },
});
