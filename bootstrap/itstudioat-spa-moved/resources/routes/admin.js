import { createRouter, createWebHistory } from 'vue-router'
import Index from '@/pages/admin/index/Index.vue'
import Auth_Login from '@/pages/admin/auth/Login.vue'
import Auth_UnknownPassword from '@/pages/admin/auth/UnknownPassword.vue'
import Auth_Register from '@/pages/admin/auth/Register.vue'
import Auth_EmailVerification from '@/pages/admin/auth/EmailVerification.vue'
import Dashboard from '@/pages/admin/dashboard/Dashboard.vue'
import Profile from '@/pages/admin/profile/Profile.vue'
import Users from '@/pages/admin/users/Users.vue'
import Users_AllUsers from '@/pages/admin/users/AllUsers/Items.vue'
import Users_Roles from '@/pages/admin/users/Roles/Items.vue'
import Users_UsersWithRoles from '@/pages/admin/users/UsersWithRoles/Items.vue'

const routes = [
    { path: '/admin', component: Index, meta: { capability: 'home' } },
    { path: '/admin/login', component: Auth_Login, meta: { public: true } },
    { path: '/admin/unknown_password', component: Auth_UnknownPassword, meta: { public: true } },
    { path: '/admin/register', component: Auth_Register, meta: { public: true } },
    { path: '/admin/email_verification', component: Auth_EmailVerification, meta: { public: true } },
    { path: '/admin/dashboard', component: Dashboard, meta: { capability: 'dashboard' } },
    { path: '/admin/profile', component: Profile, meta: { capability: 'profile' } },
    { path: '/admin/users', component: Users, meta: { capability: 'users' } },
    { path: '/admin/users/all_users', component: Users_AllUsers, meta: { capability: 'users' } },
    { path: '/admin/users/roles', component: Users_Roles, meta: { capability: 'users' } },
    { path: '/admin/users/users_with_roles', component: Users_UsersWithRoles, meta: { capability: 'users' } },
]

export function resolveAdminRouteAccess(path) {
    const normalizedPath = typeof path === 'string' ? path.trim() : ''
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

const router = createRouter({
    history: createWebHistory(),
    routes
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
