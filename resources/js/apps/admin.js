import '../bootstrap.js';
import '../../css/admin.css';

import { createApp } from "vue"
import { createPinia } from 'pinia'

import App from "../pages/admin/App.vue"

import vuetify from "../../plugins/admin.js"
import router from '../../routes/admin.js'

const pinia = createPinia()
var app = createApp(App).use(vuetify).use(pinia).use(router);

app.mount('#app')