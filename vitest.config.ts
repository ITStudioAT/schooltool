import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { stripTypeScriptTypes } from 'node:module'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

function stripUiTestTypesPlugin() {
    return {
        name: 'strip-ui-test-types',
        enforce: 'pre',
        transform(code, id) {
            const [filepath] = String(id || '').split('?')
            const normalizedPath = filepath.replace(/\\/g, '/')

            if (!normalizedPath.endsWith('.ts') && !normalizedPath.endsWith('.tsx')) {
                return null
            }

            if (!normalizedPath.includes('/tests/ui/')) {
                return null
            }

            return {
                code: stripTypeScriptTypes(code),
                map: null,
            }
        },
    }
}

export default defineConfig({
    plugins: [stripUiTestTypesPlugin(), vue()],
    esbuild: false,
    test: {
        environment: 'happy-dom',
        globals: true,
        pool: 'threads',
        setupFiles: ['./tests/ui/setup.ts'],
        include: ['tests/ui/unit/**/*.test.ts'],
        clearMocks: true,
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
})
