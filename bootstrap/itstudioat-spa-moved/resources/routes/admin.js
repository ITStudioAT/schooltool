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
    { path: '/admin', component: Index },
    { path: '/admin/login', component: Auth_Login },
    { path: '/admin/unknown_password', component: Auth_UnknownPassword },
    { path: '/admin/register', component: Auth_Register },
    { path: '/admin/email_verification', component: Auth_EmailVerification },
    { path: '/admin/dashboard', component: Dashboard },
    { path: '/admin/profile', component: Profile },
    { path: '/admin/users', component: Users },
    { path: '/admin/users/all_users', component: Users_AllUsers },
    { path: '/admin/users/roles', component: Users_Roles },
    { path: '/admin/users/users_with_roles', component: Users_UsersWithRoles },
];

const router = createRouter({
    history: createWebHistory(),
    routes
});


router.beforeEach(async (to, from, next) => {

    // /application wird nicht wieter geprüft
    if (to.path.startsWith('/application')) {
        next();
        return;
    }

    const matched = to.matched[0];

    // Extract base path (remove everything from first `/:` onward)
    const basePath = matched?.path.replace(/\/:.*/g, '');

    // Find route whose path starts with the base path and contains dynamic params
    const matchingRoute = routes.find(route => {
        const staticRoutePath = route.path.replace(/\/:.*/g, '');
        return staticRoutePath === basePath;
    });

    const data = {
        route: 'admin',
        from: from?.path ?? null,
        to: to?.path ?? null,
        matching_path: matchingRoute?.path ?? null,
        base_path: basePath ?? null
    };

    const answer = await isRouteAllowed(data);
    if (answer) { next(); } else { next(false); }

})

async function isRouteAllowed(data) {
    try {
        const answer = await axios.post("/api/routes/is_route_allowed", { data });
        return true;
    } catch (error) {
        const redirectUrl = '/application/error?status=' + error.response.status + '&message=' + encodeURIComponent(error.response.data.message) + '&type=' + error;
        window.location.href = redirectUrl;
        return false;
    } finally {

    }
}

export default router;