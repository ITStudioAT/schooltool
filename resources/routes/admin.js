import { createRouter, createWebHistory } from 'vue-router'

const Index = () => import('@/pages/admin/index/Index.vue')
const Auth_Login = () => import('@/pages/admin/auth/Login.vue')
const Auth_UnknownPassword = () => import('@/pages/admin/auth/UnknownPassword.vue')
const Auth_Register = () => import('@/pages/admin/auth/Register.vue')
const Auth_EmailVerification = () => import('@/pages/admin/auth/EmailVerification.vue')
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
const Settings = () => import('@/pages/admin/settings/Settings.vue')
const Aba = () => import('@/pages/admin/aba/Aba.vue')
const AbaResults = () => import('@/pages/admin/aba/AbaAnalysisResults.vue')
const AbaAiSettings = () => import('@/pages/admin/aba/AbaAiSettings.vue')
const AbaPandocDebug = () => import('@/pages/admin/aba/AbaPandocDebug.vue')
const AbaSeedReport = () => import('@/pages/admin/aba/AbaSeedReport.vue')
const AbaSeedReview = () => import('@/pages/admin/aba/AbaSeedReview.vue')

export const routes = [
    { path: '/admin', component: Index, meta: { capability: 'home' } },
    { path: '/admin/settings', component: Settings, meta: { capability: 'settings' } },
    { path: '/admin/login', component: Auth_Login, meta: { public: true } },
    { path: '/admin/unknown_password', component: Auth_UnknownPassword, meta: { public: true } },
    { path: '/admin/register', component: Auth_Register, meta: { public: true } },
    { path: '/admin/email_verification', component: Auth_EmailVerification, meta: { public: true } },
    { path: '/admin/profile', redirect: '/admin/settings?tab=profile' },
    { path: '/admin/users', component: Users, meta: { capability: 'users' } },
    { path: '/admin/users/all_users', component: Users_AllUsers, meta: { capability: 'users' } },
    { path: '/admin/users/roles', component: Users_Roles, meta: { capability: 'user_roles' } },
    { path: '/admin/users/users_with_roles', component: Users_UsersWithRoles, meta: { capability: 'users' } },
    { path: '/admin/register_system', component: RegisterSystem, meta: { capability: 'register_system' } },
    { path: '/admin/register_system/details', component: RegisterSystem_Details, meta: { capability: 'register_system' } },
    { path: '/admin/super_admin/:section?', component: SuperAdmin, meta: { capability: 'super_admin' } },
    { path: '/admin/tutoring', component: Tutoring, meta: { capability: 'tutoring' } },
    { path: '/admin/teaching/:section?', component: Teaching, meta: { capability: 'teaching' } },
    { path: '/admin/materials', component: Materials, meta: { capability: 'materials' } },
    { path: '/admin/groups', component: Groups, meta: { capability: 'groups' } },
    { path: '/admin/materials/subjects-overview', component: MaterialsSubjectsOverview, meta: { capability: 'materials' } },
    { path: '/admin/restaurant', component: Restaurant, meta: { capability: 'restaurant' } },
    { path: '/admin/aba', component: Aba, meta: { capability: 'aba' } },
    { path: '/admin/aba/results/:abaId', component: AbaResults, meta: { capability: 'aba' } },
    { path: '/admin/aba/ai-settings', component: AbaAiSettings, meta: { capability: 'aba' } },
    { path: '/admin/aba/ai-settings/pandoc-debug', component: AbaPandocDebug, meta: { capability: 'aba' } },
    { path: '/admin/aba/ai-settings/seed-report', component: AbaSeedReport, meta: { capability: 'aba' } },
    { path: '/admin/aba/ai-settings/seed-report/review', component: AbaSeedReview, meta: { capability: 'aba' } },
]

export function resolveAdminRouteAccess(path) {
    const normalizedPath = normalizeAdminPath(path)
    if (normalizedPath === '') {
        return null
    }

    const exactRoute = routes.find((route) => !route.path.includes('/:') && route.path === normalizedPath)
    if (exactRoute) {
        return {
            public: exactRoute.meta?.public === true,
            capability: typeof exactRoute.meta?.capability === 'string' ? exactRoute.meta.capability : null,
        }
    }

    const dynamicRoute = routes.find((route) => {
        if (!route.path.includes('/:')) {
            return false
        }

        const staticPrefix = route.path.replace(/\/:.*/g, '')
        return normalizedPath === staticPrefix || normalizedPath.startsWith(`${staticPrefix}/`)
    })

    if (!dynamicRoute) {
        return null
    }

    return {
        public: dynamicRoute.meta?.public === true,
        capability: typeof dynamicRoute.meta?.capability === 'string' ? dynamicRoute.meta.capability : null,
    }
}

function normalizeAdminPath(path) {
    if (typeof path !== 'string') {
        return ''
    }

    const trimmedPath = path.trim()
    if (trimmedPath.length > 1 && trimmedPath.endsWith('/')) {
        return trimmedPath.replace(/\/+$/, '')
    }

    return trimmedPath
}

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach(async (to, from, next) => {
    const routeAccess = resolveAdminRouteAccess(to.path)
    if (!routeAccess) {
        redirectToApplicationError(404, 'Die Seite konnte nicht gefunden werden')
        next(false)
        return
    }

    if (routeAccess.public || !routeAccess.capability) {
        next()
        return
    }

    const config = await loadAdminConfigForRouteGuard()
    if (!config) {
        next(false)
        return
    }

    if (config.is_auth !== true) {
        next('/admin/login')
        return
    }

    if (config.capabilities?.[routeAccess.capability] === true) {
        next()
        return
    }

    redirectToApplicationError(403, 'Sie können auf diese Seite nicht zugreifen')
    next(false)
})

async function loadAdminConfigForRouteGuard() {
    try {
        const response = await axios.get('/api/admin/config')
        return response.data
    } catch (error) {
        redirectToApplicationError(error.response?.status || 500, error.response?.data?.message || 'Fehler passiert.')
        return null
    }
}

function redirectToApplicationError(status, message) {
    const redirectUrl = `/application/error?status=${status}&message=${encodeURIComponent(message)}&type=error`
    window.location.href = redirectUrl
}

export default router
