import { createRouter, createWebHistory } from 'vue-router'
import Application_Error from '@/pages/application/Error.vue'




const routes = [
    { path: '/application/error', component: Application_Error },


];

const router = createRouter({
    history: createWebHistory(),
    routes
});


router.beforeEach(async (to, from, next) => {

    next();
    return;



})


export default router;