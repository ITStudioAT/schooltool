<template>

    <v-app>
        <!-- Alle Dinge sind geladen -->
        <v-layout v-if="is_loading == 0" class="bg-background">
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
                <router-view></router-view>
                <ItsNotification />
            </v-main>

            <v-footer app>
                <v-row justify="center" no-gutters>
                    <v-col cols="12" class="text-center">
                        <v-btn text variant="text">Impressum</v-btn>
                    </v-col>
                </v-row>

            </v-footer>
        </v-layout>

        <!-- Es wird aktuell etwas geladen-->
        <v-container class="d-flex justify-center align-center" style="height: 100vh;" v-if="is_loading > 0">
            <v-progress-circular indeterminate size="70" width="7"></v-progress-circular>
        </v-container>
    </v-app>



</template>

<script setup>
import ItsNotification from "@/pages/components/ItsNotification.vue";
</script>

<script>
import axios from "axios";
import { mapWritableState } from "pinia";
import { useApplicationStore } from "@/stores/application/ApplicationStore";


export default {

    components: {},

    async beforeMount() {
        this.applicationStore = useApplicationStore();
        this.applicationStore.initialize(this.$router);
        await this.loadImpersonationStatus();
    },

    unmounted() {
    },

    data() {
        return {
            applicationStore: null,
            impersonation: {
                is_impersonating: false,
                impersonator: null,
                current_user: null,
            },
        };
    },

    computed: {
        ...mapWritableState(useApplicationStore, ['is_loading', 'error']),
        isImpersonating() {
            return !!this.impersonation?.is_impersonating;
        },
        impersonatorLabel() {
            const impersonator = this.impersonation?.impersonator;
            if (!impersonator) return 'meinem Benutzer';
            const name = `${impersonator.last_name || ''} ${impersonator.first_name || ''}`.trim();
            const displayName = name || impersonator.email || 'meinem Benutzer';
            const email = impersonator.email ? String(impersonator.email).trim() : '';
            const schoolName = impersonator.school_name || '';
            const detailParts = [email, schoolName].filter((item) => !!String(item || '').trim());
            return detailParts.length >= 1 ? `${displayName} (${detailParts.join(' | ')})` : displayName;
        },
        currentImpersonatedUserLabel() {
            const user = this.impersonation?.current_user || null;
            if (!user) return 'Benutzer';
            const name = `${user.last_name || ''} ${user.first_name || ''}`.trim();
            const displayName = name || user.email || 'Benutzer';
            const email = user.email ? String(user.email).trim() : '';
            const schoolName = user.school_name || '';
            const detailParts = [email, schoolName].filter((item) => !!String(item || '').trim());
            return detailParts.length >= 1 ? `${displayName} (${detailParts.join(' | ')})` : displayName;
        },

    },

    methods: {
        async loadImpersonationStatus() {
            try {
                const response = await axios.get('/api/admin/impersonation/status');
                this.impersonation = {
                    is_impersonating: !!response.data?.is_impersonating,
                    impersonator: response.data?.impersonator || null,
                    current_user: response.data?.current_user || null,
                };
            } catch (error) {
                this.impersonation = {
                    is_impersonating: false,
                    impersonator: null,
                    current_user: null,
                };
            }
        },
        async stopImpersonationAndReturn() {
            try {
                await axios.post('/api/admin/impersonation/stop');
                this.impersonation = {
                    is_impersonating: false,
                    impersonator: null,
                    current_user: null,
                };
                window.location.href = '/admin';
            } catch (error) {
                // do nothing
            }
        },

    }

}
</script>
