import { createRouter, createWebHistory } from 'vue-router'

const Index = () => import('@/pages/admin/index/Index.vue')
const Auth_Login = () => import('@/pages/admin/auth/Login.vue')
const Auth_UnknownPassword = () => import('@/pages/admin/auth/UnknownPassword.vue')
const Auth_Register = () => import('@/pages/admin/auth/Register.vue')
const Auth_EmailVerification = () => import('@/pages/admin/auth/EmailVerification.vue')
const Profile = () => import('@/pages/admin/profile/Profile.vue')
const Users = () => import('@/pages/admin/users/Users.vue')
const Users_AllUsers = () => import('@/pages/admin/users/AllUsers/Items.vue')
const Users_Roles = () => import('@/pages/admin/users/Roles/Items.vue')
const Users_UsersWithRoles = () => import('@/pages/admin/users/UsersWithRoles/Items.vue')

const RegisterSystem = () => import('@/pages/admin/registerSystem/RegisterSystem.vue')
const RegisterSystem_Details = () => import('@/pages/admin/registerSystem/RegisterDetails.vue')

const SuperAdmin = () => import('@/pages/admin/superAdmin/SuperAdmin.vue')

const Tutoring = () => import('@/pages/admin/tutoring/Tutoring.vue')

const Teaching = () => import('@/pages/admin/teaching/Teaching.vue')
const Materials = () => import('@/pages/admin/materials/Materials.vue')
const MaterialsSubjectsOverview = () => import('@/pages/admin/materials/MaterialsSubjectsOverview.vue')
const Groups = () => import('@/pages/admin/groups/Groups.vue')
const Restaurant = () => import('@/pages/admin/restaurant/Restaurant.vue')
const Aba = () => import('@/pages/admin/aba/Aba.vue')
const AbaResults = () => import('@/pages/admin/aba/AbaAnalysisResults.vue')
const AbaAiSettings = () => import('@/pages/admin/aba/AbaAiSettings.vue')
const AbaSeedReport = () => import('@/pages/admin/aba/AbaSeedReport.vue')

const routes = [
    { path: '/admin', component: Index },
    { path: '/admin/login', component: Auth_Login },
    { path: '/admin/unknown_password', component: Auth_UnknownPassword },
    { path: '/admin/register', component: Auth_Register },
    { path: '/admin/email_verification', component: Auth_EmailVerification },
    { path: '/admin/profile', component: Profile },
    { path: '/admin/users', component: Users },
    { path: '/admin/users/all_users', component: Users_AllUsers },
    { path: '/admin/users/roles', component: Users_Roles },
    { path: '/admin/users/users_with_roles', component: Users_UsersWithRoles },
    { path: '/admin/register_system', component: RegisterSystem },
    { path: '/admin/register_system/details', component: RegisterSystem_Details },
    { path: '/admin/super_admin', component: SuperAdmin },
    { path: '/admin/tutoring', component: Tutoring },
    { path: '/admin/teaching', component: Teaching },
    { path: '/admin/materials', component: Materials },
    { path: '/admin/groups', component: Groups },
    { path: '/admin/materials/subjects-overview', component: MaterialsSubjectsOverview },
    { path: '/admin/restaurant', component: Restaurant },
    { path: '/admin/aba', component: Aba },
    { path: '/admin/aba/results/:abaId', component: AbaResults },
    { path: '/admin/aba/ai-settings', component: AbaAiSettings },
    { path: '/admin/aba/ai-settings/seed-report', component: AbaSeedReport },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach(async (to, from, next) => {
    // /application wird nicht wieter geprüft
    if (to.path.startsWith('/application')) {
        next()
        return
    }

    const matched = to.matched[0]

    // Extract base path (remove everything from first `/:` onward)
    const basePath = matched?.path.replace(/\/:.*/g, '')

    // Find route whose path starts with the base path and contains dynamic params
    const matchingRoute = routes.find((route) => {
        const staticRoutePath = route.path.replace(/\/:.*/g, '')
        return staticRoutePath === basePath
    })

    const data = {
        route: 'admin',
        from: from?.path ?? null,
        to: to?.path ?? null,
        matching_path: matchingRoute?.path ?? null,
        base_path: basePath ?? null,
    }

    const answer = await isRouteAllowed(data)
    if (answer) {
        next()
    } else {
        next(false)
    }
})

async function isRouteAllowed(data) {
    try {
        const answer = await axios.post('/api/routes/is_route_allowed', { data })
        return true
    } catch (error) {
        const redirectUrl = '/application/error?status=' + error.response.status + '&message=' + encodeURIComponent(error.response.data.message) + '&type=' + error
        window.location.href = redirectUrl
        return false
    } finally {
    }
}

export default router
