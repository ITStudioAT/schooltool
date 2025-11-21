<template>
    <v-container fluid class="h-100 w-100 d-flex flex-column align-center justify-center bg-background">
        <v-card class="mx-auto w-100" max-width="600" tile flat color="primary">
            <!-- ÜBERSCHRIFT -->
            <v-card-title class="d-flex flex-row align-center">
                <img src="/storage/images/schooltool_white.png" alt="Logo" class="logo" />
                <div class="ml-2">Impressum</div>
            </v-card-title>

            <v-card-subtitle class="d-flex flex-row align-center justify-space-between">
                <div>ITStudio.at Dipl.-Ing. Günther Kron</div>
            </v-card-subtitle>

            <!-- IMPRESSUM -->
            <v-card-text class="text-body-1">
                <div>Diese App wurde im Auftrag mehrerer österreichischer Schulen entwickelt.</div>
                <div>Sie wird von Dipl.-Ing. Günther Kron bereitgestellt.</div>
            </v-card-text>
            <v-card-text class="text-body-1">
                <div>
                    <span class="text-decoration-underline">Kontakt</span>
                    :
                </div>
                <div>ITStudio.at</div>
                <div>Dipl.-Ing. Günther Kron</div>
                <div>5110 Oberndorf bei Salzburg</div>
                <div>Austria</div>
                <div>E-Mail: hallo@itstudio.at</div>
            </v-card-text>

            <!-- INFOS ZUR APP -->
            <v-card-text class="text-body-1">
                <div>
                    <span class="text-decoration-underline">Web-App</span>
                    : SchoolTool
                </div>
                <div>
                    <span class="text-decoration-underline">Aktuelle Version</span>
                    : {{ config?.version }}
                </div>
            </v-card-text>

            <!-- COPYRIGHT -->
            <v-card-text class="text-body-2">
                {{ config?.copyright }}
            </v-card-text>

            <!-- MENÜ -->
            <v-card-actions class="d-flex flex-column justify-center text-body-1 font-weight-medium">
                <v-btn tile flat variant="outlined" @click="back">Startseite</v-btn>
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
    methods: {
        back() {
            this.$router.back()
        },
    },
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
