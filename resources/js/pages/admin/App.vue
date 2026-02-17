<template>
    <v-app>
        <v-navigation-drawer
            v-model="show_navigation_drawer"
            color="primary"
            v-if="config && config.is_auth && config.roles.some((item) => admins.includes(item)) && $route.path != '/admin/login'">
            <v-toolbar color="appbar">
                <v-toolbar-title>
                    <img :src="'/storage/images/' + config?.logo" alt="Logo" class="logo" height="24" />
                </v-toolbar-title>
                <v-spacer></v-spacer>
                <v-btn icon="mdi-menu-close" @click="show_navigation_drawer = false" v-if="show_navigation_drawer" />
            </v-toolbar>
            <v-list>
                <template v-for="(item, i) in config.menu" :key="i">
                    <!-- route item -->
                    <v-list-item v-if="item.to" :exact="false" :title="item.title" :prepend-icon="item.icon" :to="item.to" :disabled="is_navigation_locked || !item.is_active">
                        <template v-if="item.status_icon" #append>
                            <v-icon :icon="item.status_icon" :color="item.status_color || 'warning'" :title="item.status_title || ''" size="small" />
                        </template>
                    </v-list-item>
                    <!-- click item -->
                    <v-list-item v-else-if="item.click" :exact="false" :title="item.title" :prepend-icon="item.icon" :disabled="is_navigation_locked" @click="callItemClick(item)" />
                </template>
            </v-list>
        </v-navigation-drawer>

        <v-app-bar flat color="primary" v-if="config && config.is_auth && config.roles.some((item) => admins.includes(item)) && $route.path != '/admin/login'">
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
            <router-view></router-view>
            <its-notification />
            <v-overlay :model-value="is_loading > 0" class="align-center justify-center" contained opacity="0.1">
                <!-- <v-progress-circular indeterminate size="70" width="7" /> -->
                <!--
                    <v-progress-circular indeterminate size="small" />
                    -->
                <!--
                    <v-progress-linear indeterminate stream buffer-value="0" color="primary" />
                    -->

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
            admins: ['super_admin', 'admin', 'register_admin', 'tutoring_admin', 'teaching_admin', 'materials_admin', 'teacher', 'lunch_admin'],
        }
    },

    computed: {
        // these will become this.config, this.is_loading, ...
        ...mapWritableState(useAdminStore, ['config', 'is_loading', 'show_navigation_drawer', 'is_navigation_locked', 'load_config']),
    },

    async beforeMount() {
        await axios.get('/sanctum/csrf-cookie')

        // get pinia store and keep it on this
        this.adminStore = useAdminStore()
        this.adminStore.is_loading++
        this.adminStore.initialize(this.$router)
        await this.adminStore.loadConfig()
        this.adminStore.is_loading--
    },

    methods: {
        async logout() {
            // whatever your backend sequence is
            // this.$router.push('/admin')
            await this.adminStore.executeLogout()
            //await this.adminStore.loadConfig()
            await this.$nextTick()
            this.$router.replace('/admin/login')
        },

        callItemClick(item) {
            // item.click should be a string like "logout"
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
    background: #f39200; /* rot */
    animation-delay: -0.32s;
}
.loading-squares span:nth-child(2) {
    background: #3aaa35; /* grün */
    animation-delay: -0.16s;
}
.loading-squares span:nth-child(3) {
    background: #37474f; /* blau */
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
