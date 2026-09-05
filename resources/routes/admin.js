import { createRouter, createWebHistory } from 'vue-router'
import { useAdminStore } from '@/stores/admin/AdminStore'

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

const Tutoring = () => import('@/pages/admin/tutoring/Tutoring.vue')

const Teaching = () => import('@/pages/admin/teaching/Teaching.vue')
const Materials = () => import('@/pages/admin/materials/Materials.vue')
const MaterialsSubjectsOverview = () => import('@/pages/admin/materials/MaterialsSubjectsOverview.vue')
const MaterialsV2 = () => import('@/pages/admin/materialsV2/MaterialsV2.vue')
const Restaurant = () => import('@/pages/admin/restaurant/Restaurant.vue')
const MenuPlansEntry = () => import('@/pages/admin/restaurant/components/MenuPlansEntry.vue')
const StudentsTimetables = () => import('@/pages/admin/studentsTimetables/StudentsTimetables.vue')
const Settings = () => import('@/pages/admin/settings/Settings.vue')
const Profile = () => import('@/pages/admin/profile/Profile.vue')
const Aba = () => import('@/pages/admin/aba/Aba.vue')
const AbaDetails = () => import('@/pages/admin/aba/AbaDetails.vue')

export const routes = [
    { path: '/admin', component: Index, meta: { capability: 'home' } },
    { path: '/admin/settings', component: Settings, meta: { capability: 'settings' } },
    { path: '/admin/login', component: Auth_Login, meta: { public: true } },
    { path: '/admin/unknown_password', component: Auth_UnknownPassword, meta: { public: true } },
    { path: '/admin/register', component: Auth_Register, meta: { public: true } },
    { path: '/admin/email_verification', component: Auth_EmailVerification, meta: { public: true } },
    { path: '/admin/profile', component: Profile, meta: { capability: 'profile' } },
    { path: '/admin/users', component: Users, meta: { capability: 'users' } },
    { path: '/admin/users/all_users', component: Users_AllUsers, meta: { capability: 'users' } },
    { path: '/admin/users/roles', component: Users_Roles, meta: { capability: 'user_roles' } },
    { path: '/admin/users/users_with_roles', component: Users_UsersWithRoles, meta: { capability: 'users' } },
    { path: '/admin/register_system', component: RegisterSystem, meta: { capability: 'register_system' } },
    { path: '/admin/register_system/details', component: RegisterSystem_Details, meta: { capability: 'register_system' } },
    { path: '/admin/super_admin/:section?', redirect: '/admin' },
    { path: '/admin/tutoring', component: Tutoring, meta: { capability: 'tutoring' } },
    { path: '/admin/teaching/:section?', component: Teaching, meta: { capability: 'teaching' } },
    { path: '/admin/materials', component: Materials, meta: { capability: 'materials' } },
    { path: '/admin/materials/subjects-overview', component: MaterialsSubjectsOverview, meta: { capability: 'materials' } },
    { path: '/admin/materials-v2', component: MaterialsV2, meta: { capability: 'materials_v2' } },
    { path: '/admin/restaurant/:section?', component: Restaurant, meta: { capability: 'restaurant' } },
    { path: '/admin/menu-plans', component: MenuPlansEntry, meta: { capability: 'restaurant' } },
    { path: '/admin/students-timetables/:section?/:subsection?/:detail?/:action?', component: StudentsTimetables, meta: { capability: 'students_timetables' } },
    { path: '/admin/aba', component: Aba, meta: { capability: 'aba' } },
    { path: '/admin/aba/details/:abaId', component: AbaDetails, meta: { capability: 'aba' } },
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

    const config = await loadAdminConfigForRouteGuard(to)
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

async function loadAdminConfigForRouteGuard(to) {
    try {
        const adminStore = useAdminStore()
        if (adminStore.config) {
            return adminStore.config
        }

        return await adminStore.loadConfig({ includeSchoolInfos: isAdminHomeRoute(to) })
    } catch (error) {
        redirectToApplicationError(error.response?.status || 500, error.response?.data?.message || 'Fehler passiert.')
        return null
    }
}

function isAdminHomeRoute(to) {
    return (to.path || '').replace(/\/+$/, '') === '/admin'
}

function redirectToApplicationError(status, message) {
    const redirectUrl = `/application/error?status=${status}&message=${encodeURIComponent(message)}&type=error`
    window.location.href = redirectUrl
}

export default router
