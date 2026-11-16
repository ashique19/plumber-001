import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/error-pages.css',
                'resources/js/app.js',
                'resources/css/receipt.css',
                'resources/css/invoice-print.css',
                'resources/css/hub-document-table.css',
                'resources/css/print-template-modern.css',
                'resources/css/print-template-classic.css',
                'resources/js/receipt.js',
                'resources/css/analytics-dashboard.css',
                'resources/css/reports-print.css',
                'resources/js/analytics-dashboard.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
