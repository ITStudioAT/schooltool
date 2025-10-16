import { createRouter, createWebHistory } from 'vue-router'
import Index from '@/pages/homepage/index/Index.vue'
import Application_Error from '@/pages/application/Error.vue'


const routes = [
    { path: '/', component: Index },
    { path: '/homepage/error', component: Application_Error },


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