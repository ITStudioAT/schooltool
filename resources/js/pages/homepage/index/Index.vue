<template>
    <canvas ref="particleCanvas" class="particle-canvas"></canvas>

    <v-container fluid class="index-bg modern-bg d-flex flex-column align-center justify-center" v-if="config">
        <!-- Canvas für die Fäden -->

        <!-- Gradient Orbs -->
        <div class="gradient-orb orb-1"></div>
        <div class="gradient-orb orb-2"></div>
        <div class="gradient-orb orb-3"></div>

        <!-- Grid Pattern -->
        <div class="grid-pattern"></div>

        <v-card class="mx-auto w-100" max-width="600" tile flat color="primary">
            <v-form ref="form" class="mb-4" @submit.prevent="redirect(school, licence)">
                <v-card-title class="d-flex flex-row align-center" v-if="school">
                    <img :src="`/storage/images/${school?.logo}`" alt="Logo" class="logo" v-if="school?.logo" />
                    <div class="ml-2"></div>
                </v-card-title>

                <v-card-title v-if="!school" class="mb-4 d-flex flex-row align-center">
                    <div style="height: 28px; width: 28px" class="mr-4">
                        <v-img :src="`/storage/images/${config?.schooltool_logo}`" alt="Logo" />
                    </div>
                    <div>SchoolTool</div>
                </v-card-title>

                <v-card-subtitle class="d-flex flex-row align-center justify-space-between" v-if="school?.long_name">
                    <div>{{ school?.long_name }}</div>
                    <v-btn flat size="small" icon color="primary" @click="abortSchool" v-if="config.selectableSchools.length > 1">
                        <v-icon icon="mdi-close" />
                    </v-btn>
                </v-card-subtitle>

                <v-card-text v-if="!school">
                    <div class="text-body-1 font-weight-bold">Bitte die Schule auswählen</div>
                    <v-autocomplete v-model="selected_school_id" :items="config.selectableSchools" item-title="long_name" item-value="id" label="Auswahl Schule" />
                </v-card-text>

                <v-card-text v-if="school && !licence && config.schoolLicences.length > 0">
                    <div class="text-body-1 font-weight-bold">Bitte die App auswählen</div>
                    <v-autocomplete v-model="selected_licence_id" :items="config?.schoolLicences" item-title="long_name" item-value="id" label="Auswahl App" />
                </v-card-text>

                <v-card-text v-if="licence">
                    <div class="d-flex flex-row align-center justify-space-between">
                        <div class="text-body-1 font-weight-medium">{{ licence.long_name }}</div>
                        <v-btn flat size="small" icon color="primary" @click="selected_licence_id = null" v-if="config.schoolLicences.length > 1">
                            <v-icon icon="mdi-close" />
                        </v-btn>
                    </div>
                    <div>App</div>
                </v-card-text>

                <v-card-actions class="d-flex flex-column justify-center text-body-1 font-weight-medium">
                    <v-btn autofocus tile flat variant="outlined" v-if="selected_school_id && selected_licence_id" type="submit">Weiter</v-btn>
                    <div v-if="config?.selectableSchools?.length == 0">Es kann keine Schule ausgewählt werden!</div>
                    <div v-if="selected_school_id && config?.schoolLicences?.length == 0">Es gibt keine Apps zum Auswählen!</div>
                </v-card-actions>
            </v-form>
        </v-card>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'
import { useParticles } from '@/composables/useParticles'

export default {
    components: {},

    async beforeMount() {
        this.homepageStore = useHomepageStore()
        this.school_name = this.$route.query.school
        this.app_name = this.$route.query.app
        if (!this.school) {
            await this.homepageStore.loadConfig(this.school_name, this.app_name)
        }
    },

    mounted() {
        // Particle System initialisieren
        const particleSystem = useParticles({
            count: 50,
            lineOpacity: 0.15,
            connectionDistance: 120,
            speed: 0.3,
        })

        this.$nextTick(() => {
            particleSystem.init(this.$refs.particleCanvas)
        })

        window.addEventListener('resize', particleSystem.resizeCanvas)

        // Cleanup speichern
        this._particleCleanup = particleSystem.cleanup
    },

    unmounted() {
        if (this._particleCleanup) {
            this._particleCleanup()
        }
    },

    data() {
        return {
            homepageStore: null,
            is_more_content: false,
            school_name: '',
            app_name: '',
            ctx: null, // <-- Wichtig: ctx hier definieren

            // Particle system
            _particleCleanup: null,
        }
    },

    computed: {
        ...mapWritableState(useHomepageStore, ['config', 'is_loading', 'error', 'school', 'licence', 'selected_school_id', 'selected_licence_id']),
    },

    watch: {
        async selected_school_id() {
            if (this.selected_school_id) {
                this.school = this.config?.selectableSchools.find((s) => s.id === this.selected_school_id)
                await this.homepageStore.loadConfig(this.school?.short_name, this.app_name)
            }
        },
        async selected_licence_id() {
            if (this.selected_licence_id) {
                this.licence = this.config?.schoolLicences.find((s) => s.id === this.selected_licence_id)
                await this.homepageStore.loadConfig(this.school?.short_name, this.licence.name)
            } else {
                this.licence = null
            }
        },
    },

    methods: {
        abortSchool() {
            this.school = null
            this.licence = null
        },

        redirect(school, licence) {
            if (!school || !licence) return

            console.log(licence)

            switch (licence.name) {
                case 'Anmeldetool':
                    window.location.href = '/homepage/register?school=' + school.short_name
                    break

                case 'Tutoring':
                    window.location.href = '/homepage/tutoring'
                    break
            }
        },
    },
}
</script>

<style scoped>
.logo {
    display: block;
    max-height: 90px;
    height: auto;
    width: auto;
    object-fit: contain;
}

.index-bg {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
}

:deep(.v-card) {
    position: relative;
    z-index: 10; /* Card bleibt ganz oben */
    backdrop-filter: blur(8px);
    background-color: rgba(48, 63, 159, 0.9);
    box-shadow: 0 10px 35px rgba(48, 63, 159, 0.35);
    border-radius: 12px;
}
</style>
