import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';
import path from 'path';

export default defineConfig({
    server: {
        host: 'localhost',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
    },


    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),  // Resolves the @ to resources/js
        },
        dedupe: ['vuetify'],
    },

    plugins: [
        laravel({
            input: [

                'resources/js/apps/homepage.js',
                'resources/js/apps/admin.js',
                'resources/js/apps/application.js',

            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        vuetify({
            autoImport: true, // ✅ Das verhindert die Fehlerhaften Importe wie vuetify/components/VDialog
        }),
    ],

    build: {
        sourcemap: true,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    // Falls du hier Custom Chunks brauchst, sonst entfernen
                },
            },
        },
        chunkSizeWarningLimit: 500,
    }
});