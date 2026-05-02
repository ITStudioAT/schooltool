<template>
    <v-app>
        <v-navigation-drawer v-model="show_navigation_drawer" color="primary" v-if="isAdminShellVisible">
            <v-toolbar color="appbar">
                <v-toolbar-title>
                    <img :src="'/storage/images/' + config?.logo" alt="Logo" class="logo" height="24" />
                </v-toolbar-title>
                <v-spacer></v-spacer>
                <v-btn icon="mdi-menu-close" @click="show_navigation_drawer = false" v-if="show_navigation_drawer" />
            </v-toolbar>
            <v-progress-linear v-if="is_loading > 0" indeterminate color="light-blue-lighten-3" />
            <v-list>
                <template v-for="(item, i) in config.menu" :key="i">
                    <v-menu
                        v-if="Array.isArray(item.children) && item.children.length > 0"
                        location="end top"
                        offset="8"
                        :close-on-content-click="true">
                        <template #activator="{ props: hopperMenuActivatorProps }">
                            <v-list-item
                                v-bind="hopperMenuActivatorProps"
                                :title="item.title"
                                :prepend-icon="item.icon"
                                :disabled="isMenuInteractionDisabled || !item.is_active" />
                        </template>
                        <v-list density="comfortable" style="min-width: 280px;">
                            <template v-for="(child, childIndex) in item.children" :key="`${i}-${childIndex}`">
                                <v-list-item
                                    v-if="child.to"
                                    :exact="false"
                                    :title="child.title"
                                    :subtitle="child.subtitle"
                                    :prepend-icon="child.icon"
                                    :to="child.to"
                                    v-bind="routeItemBindings(child)"
                                    :disabled="isMenuInteractionDisabled || !child.is_active"
                                    @click="startNavigationLock(child.to)">
                                    <template v-if="child.status_icon" #append>
                                        <v-icon :icon="child.status_icon" :color="child.status_color || 'warning'" :title="child.status_title || ''" size="small" />
                                    </template>
                                </v-list-item>
                                <v-list-item
                                    v-else-if="child.click"
                                    :exact="false"
                                    :title="child.title"
                                    :subtitle="child.subtitle"
                                    :prepend-icon="child.icon"
                                    :disabled="isMenuInteractionDisabled || !child.is_active"
                                    @click="callItemClick(child)" />
                            </template>
                        </v-list>
                    </v-menu>
                    <v-list-item
                        v-else-if="item.href"
                        :exact="false"
                        :title="item.title"
                        :prepend-icon="item.icon"
                        :href="item.href"
                        target="_blank"
                        :disabled="isMenuInteractionDisabled || !item.is_active" />
                    <v-list-item
                        v-else-if="item.to"
                        :exact="false"
                        :title="item.title"
                        :prepend-icon="item.icon"
                        :to="item.to"
                        v-bind="routeItemBindings(item)"
                        :disabled="isMenuInteractionDisabled || !item.is_active"
                        @click="startNavigationLock(item.to)">
                        <template v-if="item.status_icon" #append>
                            <v-icon :icon="item.status_icon" :color="item.status_color || 'warning'" :title="item.status_title || ''" size="small" />
                        </template>
                    </v-list-item>
                    <v-list-item
                        v-else-if="item.click"
                        :exact="false"
                        :title="item.title"
                        :prepend-icon="item.icon"
                        :disabled="isMenuInteractionDisabled"
                        @click="callItemClick(item)" />
                </template>
            </v-list>
        </v-navigation-drawer>

        <v-app-bar flat color="primary" v-if="isAdminShellVisible">
            <template #prepend>
                <v-btn icon="mdi-menu-open" v-if="!show_navigation_drawer" @click="show_navigation_drawer = true" />
                <img
                    :src="`${selectedSchoolLogoSrc}?t=${Date.now()}`"
                    alt="Logo"
                    height="60px"
                    class="pl-2"
                    v-if="selectedSchoolLogoSrc" />
            </template>
            <template #title>
                {{ config?.selected_school?.long_name }}
            </template>
        </v-app-bar>

        <v-main class="bg-background" v-if="config">
            <v-alert type="warning" variant="tonal" class="ma-2" v-if="isImpersonating">
                <div class="d-flex flex-row flex-wrap align-center justify-space-between ga-2">
                    <div>
                        Benutzer-Übernahme aktiv:
                        <strong>{{ currentImpersonatedUserLabel }}</strong>
                        <div class="text-caption mt-1">
                            Ursprünglicher Benutzer: <strong>{{ impersonatorLabel }}</strong>. Mit "Zurück" wechseln Sie zu diesem Benutzer.
                        </div>
                    </div>
                    <v-btn color="warning" flat tile @click="stopImpersonationAndReturn">Zurück</v-btn>
                </div>
            </v-alert>
            <router-view></router-view>
            <its-notification />
            <v-overlay :model-value="is_loading > 0" class="align-center justify-center" opacity="0.1">
                <LoadingAnimation />
            </v-overlay>
        </v-main>

        <v-footer app>
            <v-row justify="center" no-gutters>
                <v-col cols="12" class="text-center">Fußzeile</v-col>
            </v-row>
        </v-footer>
    </v-app>
</template>

<script>
import axios from 'axios'
import ItsNotification from '@/pages/components/ItsNotification.vue'
import LoadingAnimation from '@/pages/components/LoadingAnimation.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolStore } from '@/stores/admin/SchoolStore'
import { resolveAdminRouteAccess } from '../../../routes/admin.js'
import { mapWritableState } from 'pinia'

export default {
    components: {
        ItsNotification,
        LoadingAnimation,
    },

    data() {
        return {
            adminStore: null,
            schoolStore: null,
            admins: ['super_admin', 'admin', 'register_admin', 'tutoring_admin', 'teaching_admin', 'materials_admin', 'materials_moderator', 'teacher', 'lunch_admin', 'aba_teacher'],
            is_route_navigation_pending: false,
            removeRouteBeforeEachHook: null,
            removeRouteAfterEachHook: null,
            removeRouteErrorHook: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'is_loading', 'show_navigation_drawer', 'is_navigation_locked', 'is_struktur_modus', 'load_config']),
        selectedSchoolLogoSrc() {
            const logo = this.config?.selected_school?.logo
            if (!logo) return null

            const rawLogo = String(logo).trim().replace(/\\/g, '/')
            if (!rawLogo) return null

            if (rawLogo.startsWith('http://') || rawLogo.startsWith('https://') || rawLogo.startsWith('/storage/')) {
                return rawLogo
            }

            const normalizedLogo = rawLogo.replace(/^\/+/, '')
            if (!normalizedLogo) return null

            if (normalizedLogo.startsWith('storage/')) {
                return `/${normalizedLogo}`
            }

            if (normalizedLogo.startsWith('images/')) {
                return `/storage/${normalizedLogo}`
            }

            if (normalizedLogo.startsWith('logos/')) {
                return `/storage/images/${normalizedLogo}`
            }

            return `/storage/images/${normalizedLogo}`
        },
        isMenuInteractionDisabled() {
            return this.is_navigation_locked || this.is_loading > 0 || this.is_route_navigation_pending || this.is_struktur_modus
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
        this.registerRouteNavigationHooks()
        this.adminStore = useAdminStore()
        this.schoolStore = useSchoolStore()
        this.adminStore.is_loading++
        this.adminStore.initialize(this.$router)
        await this.adminStore.loadConfig()
        this.adminStore.is_loading--
    },
    unmounted() {
        if (typeof this.removeRouteBeforeEachHook === 'function') this.removeRouteBeforeEachHook()
        if (typeof this.removeRouteAfterEachHook === 'function') this.removeRouteAfterEachHook()
        if (typeof this.removeRouteErrorHook === 'function') this.removeRouteErrorHook()
    },

    methods: {
        registerRouteNavigationHooks() {
            if (!this.$router) return
            this.removeRouteBeforeEachHook = this.$router.beforeEach((to, from, next) => {
                if (to.fullPath !== from.fullPath) {
                    this.adminStore.is_loading++
                }
                next()
            })
            this.removeRouteAfterEachHook = this.$router.afterEach(() => {
                this.is_route_navigation_pending = false
                this.$nextTick(() => {
                    this.adminStore.is_loading--
                })
            })
            this.removeRouteErrorHook = this.$router.onError(() => {
                this.is_route_navigation_pending = false
                this.$nextTick(() => {
                    this.adminStore.is_loading--
                })
            })
        },
        startNavigationLock(target) {
            if (this.isMenuInteractionDisabled) return
            const resolvedTarget = this.$router?.resolve(target)?.fullPath || ''
            const currentRoute = this.$route?.fullPath || ''
            if (!resolvedTarget || resolvedTarget === currentRoute) return
            this.is_route_navigation_pending = true
        },
        routeItemBindings(item) {
            return Array.isArray(item?.active_paths) && item.active_paths.length > 0
                ? { active: this.isMenuItemActive(item) }
                : {}
        },
        isMenuItemActive(item) {
            const activePaths = Array.isArray(item?.active_paths) ? item.active_paths : []

            if (!activePaths.length) {
                return false
            }

            const currentPath = this.normalizeAdminPath(this.$route?.path)

            return activePaths.some((activePath) => {
                const normalizedActivePath = this.normalizeAdminPath(activePath)

                return currentPath === normalizedActivePath || currentPath.startsWith(`${normalizedActivePath}/`)
            })
        },
        normalizeAdminPath(path) {
            if (typeof path !== 'string') {
                return ''
            }

            return path.replace(/\/+$/, '')
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
