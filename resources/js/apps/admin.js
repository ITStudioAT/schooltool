import '../bootstrap.js'
import '../../css/admin.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { configureEcho } from '@laravel/echo-vue'

import App from '../pages/admin/App.vue'

import vuetify from '../../plugins/admin.js'
import router from '../../routes/admin.js'
import { resolveAdminEchoConfig } from './adminEchoConfig.js'

configureEcho(resolveAdminEchoConfig(window.schooltoolEchoEnvironment ?? {}))

const pinia = createPinia()
const app = createApp(App).use(vuetify).use(pinia).use(router)

app.mount('#app')
