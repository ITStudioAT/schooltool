<template>
    <v-app>
        <v-layout>
            <v-main>
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
                <router-view />
                <ItsNotification />
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
                    <v-col cols="12" class="text-center">
                        <v-btn text variant="text" to="/homepage/impressum">Impressum</v-btn>
                    </v-col>
                </v-row>
            </v-footer>
        </v-layout>
    </v-app>
</template>

<script setup>
import ItsNotification from '@/pages/components/ItsNotification.vue'
</script>

<script>
import { mapWritableState } from 'pinia'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'

export default {
    components: {},
    async beforeMount() {
        this.homepageStore = useHomepageStore()
        await this.homepageStore.loadImpersonationStatus()
    },
    unmounted() {},
    data() {
        return {
            homepageStore: null,
        }
    },
    computed: {
        ...mapWritableState(useHomepageStore, ['config', 'error', 'school', 'licence', 'is_loading', 'impersonation']),
        isImpersonating() {
            return !!this.impersonation?.is_impersonating
        },
        impersonatorLabel() {
            const impersonator = this.impersonation?.impersonator
            if (!impersonator) return 'meinem Benutzer'
            const name = `${impersonator.last_name || ''} ${impersonator.first_name || ''}`.trim()
            const displayName = name || impersonator.email || 'meinem Benutzer'
            const email = impersonator.email ? String(impersonator.email).trim() : ''
            const schoolName = impersonator.school_name || ''
            const detailParts = [email, schoolName].filter((item) => !!String(item || '').trim())
            return detailParts.length >= 1 ? `${displayName} (${detailParts.join(' | ')})` : displayName
        },
        currentImpersonatedUserLabel() {
            const user = this.impersonation?.current_user || this.config?.auth?.user || this.config?.user || null
            if (!user) return 'Benutzer'
            const name = `${user.last_name || ''} ${user.first_name || ''}`.trim()
            const displayName = name || user.email || 'Benutzer'
            const email = user.email ? String(user.email).trim() : ''
            const schoolName =
                user.school_name || this.config?.school?.long_name || this.config?.school?.short_name || this.config?.selected_school?.long_name || this.config?.selected_school?.short_name || ''
            const detailParts = [email, schoolName].filter((item) => !!String(item || '').trim())
            return detailParts.length >= 1 ? `${displayName} (${detailParts.join(' | ')})` : displayName
        },
    },
    methods: {
        async stopImpersonationAndReturn() {
            if (!(await this.homepageStore.stopImpersonation())) return
            window.location.href = '/admin'
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
