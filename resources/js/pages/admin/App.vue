<template>
    <v-app>
        <admin-navigation-drawer
            v-model="show_navigation_drawer"
            :is-visible="isAdminShellVisible"
            :config="config"
            :is-loading="is_loading"
            :is-menu-interaction-disabled="isMenuInteractionDisabled"
            @navigate-menu-route="navigateMenuRoute"
            @call-item-click="callItemClick" />

        <admin-app-bar
            v-model="show_navigation_drawer"
            :is-visible="isAdminShellVisible"
            :selected-school-logo-src="selectedSchoolLogoSrc"
            :schoolwide-active-schoolyear="config?.schoolwide_active_schoolyear"
            :selected-schoolyear="config?.selected_schoolyear"
            :can-manage-schoolwide-schoolyear="canManageSchoolwideSchoolyear"
            :title="config?.selected_school?.long_name || ''" />

        <v-main class="bg-background" v-if="config">
            <v-progress-linear
                :active="is_loading > 0"
                absolute
                color="primary"
                height="3"
                indeterminate
                location="top"
                aria-label="Seite wird geladen" />
            <admin-import-completion-listener
                v-if="config.is_auth && config.user?.id"
                :key="config.user.id"
                :user-id="config.user.id" />
            <admin-impersonation-alert
                :is-impersonating="isImpersonating"
                :current-impersonated-user-label="currentImpersonatedUserLabel"
                :impersonator-label="impersonatorLabel"
                @stop="stopImpersonationAndReturn" />
            <router-view></router-view>
            <its-notification />
        </v-main>

        <v-footer app>
            <v-row justify="center" no-gutters>
                <v-col cols="12" class="text-center">Fußzeile</v-col>
            </v-row>
        </v-footer>
    </v-app>
</template>

<script>
import AdminAppBar from '@/pages/admin/components/AdminAppBar.vue'
import AdminImportCompletionListener from '@/pages/admin/components/AdminImportCompletionListener.vue'
import AdminImpersonationAlert from '@/pages/admin/components/AdminImpersonationAlert.vue'
import AdminNavigationDrawer from '@/pages/admin/components/AdminNavigationDrawer.vue'
import ItsNotification from '@/pages/components/ItsNotification.vue'
import { useAdminRouteNavigation } from '@/composables/useAdminRouteNavigation'
import { resolveSelectedSchoolLogoSrc } from '@/helpers/adminSchoolLogo'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolStore } from '@/stores/admin/SchoolStore'
import { resolveAdminRouteAccess } from '../../../routes/admin.js'
import { mapWritableState } from 'pinia'

export default {
    components: {
        AdminAppBar,
        AdminImportCompletionListener,
        AdminImpersonationAlert,
        AdminNavigationDrawer,
        ItsNotification,
    },

    data() {
        return {
            adminStore: null,
            schoolStore: null,
            routeNavigation: null,
            admins: ['super_admin', 'admin', 'register_admin', 'tutoring_admin', 'teaching_admin', 'materials_admin', 'materials_moderator', 'teacher', 'lunch_admin', 'aba_teacher', 'studentstimetables_admin', 'studentstimetables_moderator'],
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'is_loading', 'show_navigation_drawer', 'is_navigation_locked', 'is_struktur_modus', 'load_config']),
        selectedSchoolLogoSrc() {
            return resolveSelectedSchoolLogoSrc(this.config?.selected_school?.logo)
        },
        canManageSchoolwideSchoolyear() {
            const roles = this.config?.roles || []

            return roles.includes('super_admin') || roles.includes('admin')
        },
        isMenuInteractionDisabled() {
            return this.is_navigation_locked || this.isRouteNavigationPending || this.is_struktur_modus
        },
        isRouteNavigationPending() {
            return this.routeNavigation?.state?.isRouteNavigationPending || false
        },
        isImpersonating() {
            return !!this.config?.impersonation?.is_impersonating
        },
        isAdminShellVisible() {
            if (!this.config?.is_auth) return false
            if (this.$route.path === '/admin/login') return false
            const roles = this.config?.roles || []
            return roles.some((item) => this.admins.includes(item)) || this.isImpersonating
        },
        impersonatorLabel() {
            const impersonator = this.config?.impersonation?.impersonator
            if (!impersonator) return 'meinem Benutzer'
            const name = `${impersonator.last_name || ''} ${impersonator.first_name || ''}`.trim()
            const displayName = name || impersonator.email || 'meinem Benutzer'
            const email = impersonator.email ? String(impersonator.email).trim() : ''
            const schoolName = impersonator.school_name || ''
            const detailParts = [email, schoolName].filter((item) => !!String(item || '').trim())
            return detailParts.length >= 1 ? `${displayName} (${detailParts.join(' | ')})` : displayName
        },
        currentImpersonatedUserLabel() {
            const user = this.config?.user || {}
            const name = `${user.last_name || ''} ${user.first_name || ''}`.trim()
            const displayName = name || user.email || 'Benutzer'
            const email = user.email ? String(user.email).trim() : ''
            const schoolName = this.config?.selected_school?.long_name || this.config?.selected_school?.short_name || ''
            const detailParts = [email, schoolName].filter((item) => !!String(item || '').trim())
            return detailParts.length >= 1 ? `${displayName} (${detailParts.join(' | ')})` : displayName
        },
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolStore = useSchoolStore()
        this.routeNavigation = useAdminRouteNavigation({
            router: this.$router,
            getCurrentRoute: () => this.$route,
            adminStore: this.adminStore,
        })
        this.routeNavigation.registerRouteNavigationHooks()
        this.adminStore.is_loading++
        try {
            this.adminStore.initialize(this.$router)
            if (!this.adminStore.config) {
                const isAdminHomeRoute = this.isAdminHomeRoute()
                await this.adminStore.loadConfig({
                    includeSchoolInfos: isAdminHomeRoute,
                    includeEnvironmentVersions: isAdminHomeRoute,
                })
            }
        } finally {
            this.adminStore.is_loading = Math.max(0, Number(this.adminStore.is_loading || 0) - 1)
        }
    },
    unmounted() {
        this.routeNavigation?.unregisterRouteNavigationHooks()
    },

    methods: {
        isAdminHomeRoute() {
            return (this.$route?.path || '').replace(/\/+$/, '') === '/admin'
        },

        async navigateMenuRoute(target) {
            await this.routeNavigation?.navigateMenuRoute(target, this.isMenuInteractionDisabled)
        },
        async logout() {
            this.$router.replace({ path: '/admin/login', query: { logout: '1' } })
            await this.$nextTick()
            await this.adminStore.executeLogout()
        },
        async stopImpersonationAndReturn() {
            if (!(await this.adminStore.stopImpersonation())) return
            await this.$nextTick()
            this.$router.replace('/admin')
        },
        async switchHopperAccount(item) {
            const targetUserId = Number(item?.target_user_id)
            const currentRouteTarget = this.$route?.fullPath || '/admin'

            if (!Number.isInteger(targetUserId) || targetUserId <= 0 || this.isMenuInteractionDisabled) {
                return
            }

            if (!(await this.schoolStore.switchHopperAccount(targetUserId))) {
                return
            }

            await this.adminStore.loadConfig()
            await this.$nextTick()
            this.redirectToPostHopTarget(currentRouteTarget)
        },
        resolvePostHopTarget(target) {
            if (typeof target !== 'string' || target.trim() === '') {
                return '/admin'
            }

            const resolvedRoute = this.$router?.resolve(target)
            const normalizedPath = resolvedRoute?.path || target
            const routeAccess = resolveAdminRouteAccess(normalizedPath)

            if (!routeAccess) {
                return '/admin'
            }

            if (routeAccess.public || !routeAccess.capability) {
                return target
            }

            return this.adminStore?.config?.capabilities?.[routeAccess.capability] === true ? target : '/admin'
        },
        redirectToPostHopTarget(target) {
            window.location.assign(this.resolvePostHopTarget(target))
        },
        callItemClick(item) {
            const fnName = item.click
            if (fnName && typeof this[fnName] === 'function') {
                this[fnName](item)
            } else {
                console.warn('menu item click not found:', fnName)
            }
        },
    },
}
</script>
