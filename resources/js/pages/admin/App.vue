<template>
    <v-app>
        <v-navigation-drawer v-model="show_navigation_drawer" color="primary" v-if="config && config.is_auth && config.roles.some((item) => admins.includes(item))">
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
                    <v-list-item v-if="item.to" :exact="false" :title="item.title" :prepend-icon="item.icon" :to="item.to" />
                    <!-- click item -->
                    <v-list-item v-else-if="item.click" :exact="false" :title="item.title" :prepend-icon="item.icon" @click="callItemClick(item)" />
                </template>
            </v-list>
        </v-navigation-drawer>

        <v-app-bar flat color="primary" v-if="config && config.is_auth && config.roles.some((item) => admins.includes(item))">
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
        </v-main>

        <v-footer app>
            <v-row justify="center" no-gutters>
                <v-col cols="12" class="text-center">Fußzeile</v-col>
            </v-row>
        </v-footer>

        <!-- loading overlay -->
        <div class="d-flex justify-center align-center" style="position: fixed; inset: 0; background-color: rgba(255, 255, 255, 0.8); z-index: 9999" v-if="is_loading > 0">
            <v-progress-circular indeterminate size="70" width="7" />
        </div>
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
            admins: ['super_admin', 'admin', 'register_admin', 'tutoring_admin', 'teacher', 'lunch_admin'],
        }
    },

    computed: {
        // these will become this.config, this.is_loading, ...
        ...mapWritableState(useAdminStore, ['config', 'is_loading', 'show_navigation_drawer', 'load_config']),
    },

    async beforeMount() {
        await axios.get('/sanctum/csrf-cookie')

        // get pinia store and keep it on this
        this.adminStore = useAdminStore()
        this.adminStore.initialize(this.$router)

        await this.adminStore.loadConfig()
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
