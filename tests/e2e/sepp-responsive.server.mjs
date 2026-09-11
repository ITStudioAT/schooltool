import { resolve } from 'node:path'
import { createServer } from 'vite'
import vue from '@vitejs/plugin-vue'
import vuetify from 'vite-plugin-vuetify'

// A browser component fixture needs no Laravel server, login, or database.
const server = await createServer({
    configFile: false,
    cacheDir: 'node_modules/.vite-sepp-responsive',
    optimizeDeps: { entries: ['tests/e2e/sepp-responsive.harness.js'] },
    root: process.cwd(),
    plugins: [vue(), vuetify({ autoImport: true }), {
        name: 'sepp-responsive-fixture',
        configureServer(vite) {
            vite.middlewares.use((request, response, next) => {
                if (request.url?.startsWith('/wide-logo.svg')) {
                    response.setHeader('Content-Type', 'image/svg+xml')
                    response.end('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="60"><rect width="600" height="60" fill="gray"/></svg>')
                    return
                }
                if (!request.url?.startsWith('/?') && request.url !== '/') return next()
                response.setHeader('Content-Type', 'text/html; charset=utf-8')
                response.end(`<!doctype html><html lang="de"><head><meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css" rel="stylesheet">
                    <link href="https://fonts.bunny.net/css?family=roboto:100,300,400,500,700,900" rel="stylesheet"></head>
                    <body><div id="app"></div><script type="module" src="/tests/e2e/sepp-responsive.harness.js"></script></body></html>`)
            })
        },
    }],
    resolve: { alias: { '@': resolve('resources/js') } },
    server: { host: '127.0.0.1', port: 5183, strictPort: true, hmr: false },
})

await server.listen()
