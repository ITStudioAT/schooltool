<template>
    <v-container fluid class="h-100 w-100 d-flex flex-column align-center justify-center bg-background">
        <v-card class="mx-auto w-100" max-width="600" tile flat color="error">
            <!-- ÜBERSCHRIFT -->
            <v-card-title class="d-flex flex-row align-center">
                <img src="/storage/images/schooltool_white.png" alt="Logo" class="logo" />
                <div class="ml-2 text-h6">Fehler</div>
            </v-card-title>

            <v-card-subtitle class="d-flex flex-row align-center justify-space-between">
                <div>ITStudio.at Dipl.-Ing. Günther Kron</div>
            </v-card-subtitle>

            <!-- IMPRESSUM -->
            <v-card-text class="text-body-1">
                <div>Es tut uns leid, es ist folgender Fehler aufgetreten:</div>
                <div class="font-weight-bold mt-2">{{ $route.query.msg }}</div>
            </v-card-text>
            <v-card-text class="text-body-1">
                <div class="d-flex flex-row">
                    <span class="text-decoration-underline">Kontakt</span>
                    <span>:</span>
                </div>

                <div>E-Mail: hallo@itstudio.at</div>
            </v-card-text>

            <!-- INFOS ZUR APP -->
            <v-card-text class="text-body-1">
                <div class="d-flex flex-row">
                    <span class="text-decoration-underline">Web-App</span>
                    <span>: Schooltool</span>
                </div>
                <div class="d-flex flex-row">
                    <span class="text-decoration-underline">Version</span>
                    <span>: {{ config?.version }}</span>
                </div>
            </v-card-text>

            <!-- COPYRIGHT -->
            <v-card-text class="text-body-2">
                {{ config?.copyright }}
            </v-card-text>

            <!-- MENÜ -->
            <v-card-actions class="d-flex flex-column justify-center text-body-1 font-weight-medium">
                <v-btn tile flat variant="outlined" to="/">Startseite</v-btn>
            </v-card-actions>
        </v-card>
    </v-container>
</template>
<script>
import { mapWritableState } from 'pinia'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'
export default {
    components: {},

    async beforeMount() {
        this.homepageStore = useHomepageStore()
        if (!this.config) {
            await this.homepageStore.loadConfig()
        }
    },

    unmounted() {},

    data() {
        return {
            homepageStore: null,
        }
    },

    computed: {
        ...mapWritableState(useHomepageStore, ['config', 'is_loading', 'error', 'school', 'licence', 'selected_school_id', 'selected_licence_id']),
    },

    watch: {},
    methods: {},
}
</script>
<style scoped>
.logo {
    display: block;
    max-height: 90px; /* or 2em, relative to font size */
    height: auto;
    width: auto;
    object-fit: contain;
}
</style>
