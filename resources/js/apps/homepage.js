import "../bootstrap.js";
import "../../css/homepage.css";

import { createApp } from "vue";
import { createPinia } from "pinia";

import App from "../Pages/homepage/App.vue";

import vuetify from "../../plugins/homepage.js";
import router from "../../routes/homepage.js";
const pinia = createPinia();
var app = createApp(App).use(vuetify).use(pinia).use(router);
router.isReady().then(() => {
    app.mount("#app");
});
