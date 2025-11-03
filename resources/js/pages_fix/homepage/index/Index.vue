<template>
    <v-container fluid class="index-bg d-flex flex-column align-center justify-center" v-if="config">
        <v-card class="mx-auto w-100" max-width="600" tile flat color="primary">
            <v-form ref="form" class="mb-4" @submit.prevent="redirect(school, licence)">
                <v-card-title class="d-flex flex-row align-center">
                    <img :src="`/storage/images/${school?.logo}`" alt="Logo" class="logo" v-if="school?.logo" />
                    <div class="ml-2"></div>
                </v-card-title>
                <!-- ANZEIGE DER SCHULE-->
                <v-card-subtitle class="d-flex flex-row align-center justify-space-between" v-if="school?.long_name">
                    <div>
                        {{ school?.long_name }}
                    </div>
                    <v-btn flat size="small" icon color="primary" @click="abortSchool" v-if="config.selectableSchools.length > 1">
                        <v-icon icon="mdi-close" />
                    </v-btn>
                </v-card-subtitle>

                <!-- AUSWAHL DER SCHULE (wenn nicht ausgewählt)-->
                <v-card-text v-if="!school">
                    <div class="text-h6">Bitte die Schule auswählen</div>
                    <v-autocomplete v-model="selected_school_id" :items="config.selectableSchools" item-title="long_name" item-value="id" label="Auswahl Schule" />
                </v-card-text>

                <!-- AUSWAHL DER LICENCE (wenn nicht ausgewählt)-->
                <v-card-text v-if="school && !licence && config.schoolLicences.length > 0">
                    <div class="text-h6">Bitte die App auswählen</div>
                    <v-autocomplete v-model="selected_licence_id" :items="config?.schoolLicences" item-title="long_name" item-value="id" label="Auswahl App" />
                </v-card-text>

                <!-- ANZEIGE DER LICENCE -->
                <v-card-text v-if="licence">
                    <div class="d-flex flex-row align-center justify-space-between">
                        <div class="text-body-1 font-weight-medium">
                            {{ licence.long_name }}
                        </div>
                        <v-btn flat size="small" icon color="primary" @click="selected_licence_id = null" v-if="config.schoolLicences.length > 1">
                            <v-icon icon="mdi-close" />
                        </v-btn>
                    </div>
                    <div>App</div>
                </v-card-text>

                <!-- MENÜ -->
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
        // this.startParticles()
        // window.addEventListener('resize', this.resizeParticles)
    },
    unmounted() {
        // cancelAnimationFrame(this._raf)
        // window.removeEventListener('resize', this.resizeParticles)
    },

    data() {
        return {
            homepageStore: null,
            is_more_content: false,

            school_name: '',
            app_name: '',
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
            // selected_school_id = null
        },
        redirect(school, licence) {
            if (!school || !licence) return

            switch (licence.name) {
                case 'Anmeldetool':
                    window.location.href = '/homepage/register?school=' + school.short_name
                    break
            }

            /*
            :href="'/homepage/register?school=' + school.short_name + '&licence=' + licence.name"
            */
        },

        startParticles() {
            const c = this.$refs.fx
            const ctx = (this._ctx = c.getContext('2d'))
            const DPR = window.devicePixelRatio || 1
            const resize = () => {
                c.width = innerWidth * DPR
                c.height = innerHeight * DPR
                c.style.width = innerWidth + 'px'
                c.style.height = innerHeight + 'px'
                ctx.setTransform(DPR, 0, 0, DPR, 0, 0)
            }
            this._resizeFn = resize
            resize()

            // Partikel erzeugen
            const N = 100,
                P = []
            for (let i = 0; i < N; i++) {
                P.push({
                    x: Math.random() * innerWidth,
                    y: Math.random() * innerHeight,
                    vx: (Math.random() - 0.5) * 0.6,
                    vy: (Math.random() - 0.5) * 0.6,
                    r: 1 + Math.random() * 2,
                })
            }

            const step = () => {
                ctx.clearRect(0, 0, innerWidth, innerHeight)

                // Verbindungslinien (Netz), subtil
                for (let i = 0; i < N; i++) {
                    const a = P[i]
                    a.x += a.vx
                    a.y += a.vy
                    if (a.x < 0 || a.x > innerWidth) a.vx *= -1
                    if (a.y < 0 || a.y > innerHeight) a.vy *= -1

                    // Punkte
                    ctx.beginPath()
                    ctx.arc(a.x, a.y, a.r, 0, Math.PI * 2)
                    ctx.fillStyle = 'rgba(255,255,255,0.8)'
                    ctx.fill()

                    // Linien zu nahen Punkten
                    for (let j = i + 1; j < N; j++) {
                        const b = P[j]
                        const dx = a.x - b.x,
                            dy = a.y - b.y,
                            d = dx * dx + dy * dy
                        if (d < 140 * 140) {
                            ctx.strokeStyle = 'rgba(255,255,255,0.12)'
                            ctx.lineWidth = 1
                            ctx.beginPath()
                            ctx.moveTo(a.x, a.y)
                            ctx.lineTo(b.x, b.y)
                            ctx.stroke()
                        }
                    }
                }

                this._raf = requestAnimationFrame(step)
            }
            step()
        },
        resizeParticles() {
            if (this._resizeFn) this._resizeFn()
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

.fx-wrap {
    position: relative;
    min-height: 100vh;
    overflow: hidden;
}

.fx-canvas {
    position: fixed;
    inset: 0;
    z-index: 0;
    background: linear-gradient(135deg, #1e88e5, #5c6bc0);
}

/* 🆕 Halbtransparente Schicht über dem Canvas */
.fx-overlay {
    position: fixed;
    inset: 0;
    z-index: 0;
    background: rgba(255, 255, 255, 0.25); /* heller Schleier */
    backdrop-filter: brightness(0.95) blur(2px);
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
    backdrop-filter: blur(8px);
    background-color: rgba(48, 63, 159, 0.9);
    box-shadow: 0 10px 35px rgba(48, 63, 159, 0.35);
    border-radius: 12px;
}
</style>
