import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';
import path from 'path';

const shouldGenerateSourceMaps = process.env.VITE_SOURCEMAP === 'true';

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
        sourcemap: shouldGenerateSourceMaps,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) return;

                    if (id.includes('vuetify')) {
                        return 'vendor-vuetify';
                    }

                    if (
                        id.includes('/vue/') ||
                        id.includes('\\vue\\') ||
                        id.includes('@vue') ||
                        id.includes('vue-router') ||
                        id.includes('pinia')
                    ) {
                        return 'vendor-vue';
                    }

                    if (id.includes('@tiptap')) {
                        return 'vendor-tiptap';
                    }

                    if (id.includes('axios')) {
                        return 'vendor-axios';
                    }
                },
            },
        },
        // After route-level code splitting, the remaining large chunk is mostly Vuetify vendor code.
        chunkSizeWarningLimit: 550,
    }
});
