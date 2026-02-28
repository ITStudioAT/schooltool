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
                    <!-- route item -->
                    <v-list-item
                        v-if="item.to"
                        :exact="false"
                        :title="item.title"
                        :prepend-icon="item.icon"
                        :to="item.to"
                        :disabled="isMenuInteractionDisabled || !item.is_active"
                        @click.capture="startNavigationLock(item.to)">
                        <template v-if="item.status_icon" #append>
                            <v-icon :icon="item.status_icon" :color="item.status_color || 'warning'" :title="item.status_title || ''" size="small" />
                        </template>
                    </v-list-item>
                    <!-- click item -->
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
                    :src="'/storage/images/logos/' + config?.selected_school?.logo + '?t=' + Date.now()"
                    alt="Logo"
                    height="60px"
                    class="pl-2"
                    v-if="config?.selected_school?.logo" />
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
            <v-overlay :model-value="is_loading > 0" class="align-center justify-center" contained opacity="0.1">
                <div class="loading-squares">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
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
import { useAdminStore } from '@/stores/admin/AdminStore'
import { mapWritableState } from 'pinia'

export default {
    components: {
        ItsNotification,
    },

    data() {
        return {
            adminStore: null,
            admins: ['super_admin', 'admin', 'register_admin', 'tutoring_admin', 'teaching_admin', 'materials_admin', 'materials_moderator', 'teacher', 'lunch_admin'],
            is_route_navigation_pending: false,
            removeRouteAfterEachHook: null,
            removeRouteErrorHook: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'is_loading', 'show_navigation_drawer', 'is_navigation_locked', 'load_config']),
        isMenuInteractionDisabled() {
            return this.is_navigation_locked || this.is_loading > 0 || this.is_route_navigation_pending
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
        await axios.get('/sanctum/csrf-cookie')
        this.adminStore = useAdminStore()
        this.adminStore.is_loading++
        this.adminStore.initialize(this.$router)
        await this.adminStore.loadConfig()
        this.adminStore.is_loading--
    },
    unmounted() {
        if (typeof this.removeRouteAfterEachHook === 'function') this.removeRouteAfterEachHook()
        if (typeof this.removeRouteErrorHook === 'function') this.removeRouteErrorHook()
    },

    methods: {
        registerRouteNavigationHooks() {
            if (!this.$router) return
            this.removeRouteAfterEachHook = this.$router.afterEach(() => {
                this.is_route_navigation_pending = false
            })
            this.removeRouteErrorHook = this.$router.onError(() => {
                this.is_route_navigation_pending = false
            })
        },
        startNavigationLock(target) {
            if (this.isMenuInteractionDisabled) return
            const resolvedTarget = this.$router?.resolve(target)?.fullPath || ''
            const currentRoute = this.$route?.fullPath || ''
            if (!resolvedTarget || resolvedTarget === currentRoute) return
            this.is_route_navigation_pending = true
        },
        async logout() {
            await this.adminStore.executeLogout()
            await this.$nextTick()
            this.$router.replace('/admin/login')
        },
        async stopImpersonationAndReturn() {
            if (!(await this.adminStore.stopImpersonation())) return
            await this.$nextTick()
            this.$router.replace('/admin/super_admin')
        },
        callItemClick(item) {
            const fnName = item.click
            if (fnName && typeof this[fnName] === 'function') {
                this[fnName]()
            } else {
                console.warn('menu item click not found:', fnName)
            }
        },
    },
}
</script>

<style>
.loading-squares {
    display: flex;
    gap: 8px;
}
.loading-squares span {
    width: 12px;
    height: 12px;
    animation: pulse 1.4s infinite ease-in-out both;
}
.loading-squares span:nth-child(1) {
    background: #f39200;
    animation-delay: -0.32s;
}
.loading-squares span:nth-child(2) {
    background: #3aaa35;
    animation-delay: -0.16s;
}
.loading-squares span:nth-child(3) {
    background: #37474f;
    animation-delay: 0s;
}

@keyframes pulse {
    0%,
    80%,
    100% {
        transform: scale(0);
        opacity: 0.5;
    }
    40% {
        transform: scale(1);
        opacity: 1;
    }
}
</style>
