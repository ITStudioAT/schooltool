import '../bootstrap.js';
import '../../css/admin.css';

import { createApp } from "vue"
import { createPinia } from 'pinia'

import App from "../pages/application/App.vue"

import vuetify from "../../plugins/application.js"
import router from '../../routes/application.js'
const pinia = createPinia()
var app = createApp(App).use(vuetify).use(pinia).use(router);
app.mount('#app')