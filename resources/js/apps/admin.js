import '../bootstrap.js'
import '../../css/admin.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

/*
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
*/

import App from '../pages/admin/App.vue'

import vuetify from '../../plugins/admin.js'
import router from '../../routes/admin.js'

/* window.Pusher = Pusher */

/*
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
})

*/

const pinia = createPinia()
var app = createApp(App).use(vuetify).use(pinia).use(router)

app.mount('#app')
