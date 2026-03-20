import '../bootstrap.js';
import '../../css/homepage.css';

import { createApp } from "vue"
import { createPinia } from 'pinia'

import App from "../pages/homepage/App.vue"

import vuetify from "../../plugins/homepage.js"
import router from '../../routes/homepage.js'
const pinia = createPinia()
var app = createApp(App).use(vuetify).use(pinia).use(router);
app.mount('#app')