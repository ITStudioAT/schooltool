import { createRouter, createWebHistory } from 'vue-router'
import Index from '@/pages/homepage/index/Index.vue'
import Impressum from '@/pages/homepage/index/Impressum.vue'
import Register from '@/pages/homepage/register/Register.vue'
import Register_Part2 from '@/pages/homepage/register/RegisterPart2.vue'
import Application_Error from '@/pages/homepage/error/Error.vue'
import Tutoring from '@/pages/homepage/tutoring/Tutoring.vue'
import Cashier from '@/pages/homepage/cashier/Cashier.vue'

const routes = [
    { path: '/', component: Index },
    { path: '/homepage/impressum', component: Impressum },
    { path: '/homepage/register', component: Register },
    { path: '/homepage/register2', component: Register_Part2 },
    { path: '/homepage/error', component: Application_Error },
    { path: '/homepage/tutoring', component: Tutoring },
    { path: '/homepage/cashier', component: Cashier },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach(async (to, from, next) => {
    next()
    return
})

export default router
