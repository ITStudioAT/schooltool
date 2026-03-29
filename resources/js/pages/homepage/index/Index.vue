<template>
    <div class="intro-page">
        <!-- Animated Background -->
        <div class="animated-bg">
            <div class="hero-bg-image st-cloudflare-bg-image"></div>
        </div>

        <!-- Hero Section -->
        <section class="hero-section" v-if="step === ''">
            <div class="hero-content st-shell-1440">
                <header class="cloud-header">
                    <div class="cloud-header-left">
                        <div class="logo-wrapper logo-wrapper-nav" aria-hidden="true">
                            <span class="logo-icon">
                                <v-icon size="20" color="white">mdi-school</v-icon>
                            </span>
                        </div>
                        <div class="cloud-brand">
                            <h1 class="cloud-brand-title">
                                <span class="brand-school">School</span>
                                <span class="brand-tool">Tool</span>
                            </h1>
                        </div>
                        <nav class="cloud-header-nav" aria-label="Dummy Navigation">
                            <router-link to="/homepage/products" class="cloud-nav-item">Produkte</router-link>
                        </nav>
                    </div>
                </header>

                <div class="cloud-hero-copy">
                    <h2 class="cloud-hero-title">
                        <div>Mehr Zeit für das Wesentliche.</div>
                        <div class="mt-4">
                            Schulalltag?
                            <span class="text-white">Organisiert!</span>
                        </div>
                    </h2>
                    <p class="cloud-hero-description mt-8">
                        Wir machen Schulprozesse schneller, einfacher und übersichtlicher. Unsere modulare Plattform bringt Ordnung in den Schulalltag, und SchoolTool ist der beste
                        Ort, um Schule digital zu organisieren.
                    </p>
                </div>

                <!-- Feature Cards -->
                <div class="tools-container">
                    <h2 class="section-title">Wählen Sie Ihr Werkzeug</h2>

                    <div class="tools-grid">
                        <!-- Anmeldetool Card -->
                        <div
                            class="tool-card card-register"
                            :class="{ 'card-disabled': isRegisterDisabled }"
                            @click="handleToolCardClick('Anmeldetool')"
                            v-if="canShowRegister">
                            <div class="card-glow"></div>
                            <div class="card-content">
                                <div class="card-icon">
                                    <v-icon size="40">mdi-calendar-check</v-icon>
                                </div>
                                <h3 class="card-title">Anmeldetool</h3>
                                <p class="card-description">Einfache Anmeldung zu Schulveranstaltungen, Elternabenden und Events.</p>
                                <div class="card-action">
                                    <span class="action-text">Starten</span>
                                    <v-icon size="20">mdi-arrow-right</v-icon>
                                </div>
                                <div class="card-badge" v-if="registerBadge">
                                    <v-icon size="16">{{ registerBadge.icon }}</v-icon>
                                    <span>{{ registerBadge.label }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Nachhilfetool Card -->
                        <div
                            class="tool-card card-tutoring"
                            :class="{ 'card-disabled': isTutoringDisabled }"
                            @click="handleToolCardClick('Nachhilfetool')"
                            v-if="canShowTutoring">
                            <div class="card-glow"></div>
                            <div class="card-content">
                                <div class="card-icon">
                                    <v-icon size="40">mdi-account-group</v-icon>
                                </div>
                                <h3 class="card-title">{{ tutoringDisplayName }}</h3>
                                <p class="card-description">Nachhilfe von Schülern für Schüler. Gemeinsam zum Erfolg.</p>
                                <div class="card-action">
                                    <span class="action-text">Starten</span>
                                    <v-icon size="20">mdi-arrow-right</v-icon>
                                </div>
                                <div class="card-badge" v-if="tutoringBadge">
                                    <v-icon size="16">{{ tutoringBadge.icon }}</v-icon>
                                    <span>{{ tutoringBadge.label }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Unterricht Card -->
                        <div class="tool-card card-lernportal" :class="{ 'card-disabled': isTeachingDisabled }" @click="openUnterricht()" v-if="canShowTeaching">
                            <div class="card-glow"></div>
                            <div class="card-content">
                                <div class="card-icon">
                                    <v-icon size="40">mdi-rocket-launch-outline</v-icon>
                                </div>
                                <h3 class="card-title">Unterricht</h3>
                                <p class="card-description">Einstieg in den Login-Bereich für Schüler.</p>
                                <div class="card-action">
                                    <span class="action-text">Starten</span>
                                    <v-icon size="20">mdi-arrow-right</v-icon>
                                </div>
                                <div class="card-badge" v-if="teachingBadge">
                                    <v-icon size="16">{{ teachingBadge.icon }}</v-icon>
                                    <span>{{ teachingBadge.label }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Mittagsmenüs Card -->
                        <div
                            class="tool-card card-lunch"
                            :class="{ 'card-disabled': isRestaurantDisabled }"
                            @click="openRestaurant()"
                            v-if="canShowRestaurant">
                            <div class="card-glow"></div>
                            <div class="card-content">
                                <div class="card-icon">
                                    <v-icon size="40">mdi-food</v-icon>
                                </div>
                                <h3 class="card-title">Restaurant</h3>
                                <p class="card-description">Speisepläne, Bestellungen und Abholinfos an einem Ort. Direkt in SchoolTool.</p>
                                <div class="card-action">
                                    <span class="action-text">Zum Restaurant</span>
                                    <v-icon size="20">mdi-arrow-right</v-icon>
                                </div>
                                <div class="card-badge" v-if="restaurantBadge">
                                    <v-icon size="16">{{ restaurantBadge.icon }}</v-icon>
                                    <span>{{ restaurantBadge.label }}</span>
                                </div>
                            </div>
                        </div>
                        <a v-if="false" href="/homepage/restaurant" class="tool-card card-lunch">
                            <div class="card-glow"></div>
                            <div class="card-content">
                                <div class="card-icon">
                                    <v-icon size="40">mdi-food</v-icon>
                                </div>
                                <h3 class="card-title">Mittagsmenüs</h3>
                                <p class="card-description">Online-Bestellung für das Schulbuffet. Schnell und unkompliziert.</p>
                                <div class="card-action">
                                    <span class="action-text">Zum Buffet</span>
                                    <v-icon size="20">mdi-open-in-new</v-icon>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Documentation Link 
                <div class="docs-section">
                    <a href="/documentation" class="docs-link">
                        <v-icon size="24">mdi-book-open-page-variant</v-icon>
                        <span>Handbuch & Dokumentation</span>
                    </a>
                </div>
                -->
            </div>
        </section>

        <!-- School Selection Step (temporarily hidden) -->
        <section class="selection-section" v-if="step === 'selectSchool'">
            <div class="selection-container">
                <v-card class="selection-card" elevation="12">
                    <div class="selection-header" :class="licence?.name === 'Anmeldetool' ? 'header-green' : 'header-orange'">
                        <v-btn icon variant="text" class="back-btn" @click="abort('')">
                            <v-icon>mdi-arrow-left</v-icon>
                        </v-btn>
                        <div class="header-content">
                            <v-icon size="32" class="header-icon">
                                {{ licence?.name === 'Anmeldetool' ? 'mdi-calendar-check' : 'mdi-account-group' }}
                            </v-icon>
                            <h2 class="header-title">{{ licence?.name }}</h2>
                            <p class="header-subtitle">{{ licence?.long_name }}</p>
                        </div>
                    </div>

                    <v-card-text class="selection-body">
                        <!-- School not yet selected -->
                        <div v-if="!selected_school" class="school-picker">
                            <div class="picker-icon">
                                <v-icon size="64" color="grey-lighten-1">mdi-domain</v-icon>
                            </div>
                            <h3 class="picker-title">Wählen Sie Ihre Schule</h3>

                            <v-autocomplete
                                v-if="schools.length > 0"
                                v-model="selected_school_id"
                                :items="schools"
                                item-title="long_name"
                                item-value="id"
                                label="Schule auswählen"
                                variant="outlined"
                                prepend-inner-icon="mdi-magnify"
                                class="school-autocomplete"
                                hide-details />

                            <div v-else class="no-schools">
                                <v-icon size="48" color="warning">mdi-alert-circle-outline</v-icon>
                                <p>Keine Schulen verfügbar</p>
                            </div>
                        </div>

                        <!-- School selected - confirmation -->
                        <div v-else class="school-confirmation">
                            <div class="school-info">
                                <div class="school-logo" v-if="selected_school.logo">
                                    <img :src="'/storage/images/' + selected_school.logo" alt="Schullogo" />
                                </div>
                                <div class="school-icon" v-else>
                                    <v-icon size="64" color="primary">mdi-school</v-icon>
                                </div>
                                <h3 class="school-name">{{ selected_school.long_name }}</h3>
                                <p class="school-short">{{ selected_school.short_name }}</p>
                            </div>

                            <div>
                                <v-form class="confirmation-actions" @submit.prevent="moveTo(licence, selected_school)">
                                    <v-btn variant="outlined" color="grey" size="large" @click="abort('selectSchool')">
                                        <v-icon start>mdi-pencil</v-icon>
                                        Andere Schule
                                    </v-btn>
                                    <v-btn ref="submitBtn" variant="flat" :color="licence?.name === 'Anmeldetool' ? 'success' : 'warning'" size="large" type="submit">
                                        Weiter
                                        <v-icon end>mdi-arrow-right</v-icon>
                                    </v-btn>
                                </v-form>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </div>
        </section>

        <!-- Floating particles for ambiance (temporarily hidden) -->
        <div class="particles" v-if="false">
            <div class="particle" v-for="n in 20" :key="n" :style="getParticleStyle(n)"></div>
        </div>
    </div>
</template>

<script>
import { nextTick } from 'vue'
import { mapWritableState } from 'pinia'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export default {
    components: {},

    async beforeMount() {
        this.homepageStore = useHomepageStore()
        this.school_name = this.$route.query.school
        this.app_name = this.$route.query.app
        if (!this.school) {
            await this.homepageStore.loadConfig(this.school_name, this.app_name)
        }
        this.selected_school = null
        this.selected_school_id = null
    },

    mounted() {},

    unmounted() {},

    data() {
        return {
            homepageStore: null,
            step: '',
        }
    },

    computed: {
        ...mapWritableState(useHomepageStore, ['config', 'is_loading', 'schools', 'licence', 'selected_school', 'selected_school_id']),
        toolStatuses() {
            return this.config?.tool_licence_statuses || {}
        },
        moduleStatuses() {
            return this.config?.tool_module_statuses || {}
        },
        registerModuleStatus() {
            return this.moduleStatuses.register || 'active'
        },
        registerStatus() {
            return this.toolStatuses['Anmeldetool'] || 'missing'
        },
        tutoringModuleStatus() {
            return this.moduleStatuses.tutoring || 'inactive'
        },
        tutoringStatus() {
            return this.toolStatuses['Nachhilfetool'] || 'missing'
        },
        tutoringDisplayName() {
            return 'Schüler helfen Schülern'
        },
        teachingModuleStatus() {
            return this.moduleStatuses.teaching || 'inactive'
        },
        teachingStatus() {
            return this.toolStatuses['Lehrertool'] || 'missing'
        },
        restaurantModuleStatus() {
            return this.moduleStatuses.restaurant || 'inactive'
        },
        canShowRegister() {
            return this.isModuleVisible(this.registerModuleStatus) && this.registerStatus !== 'missing'
        },
        canShowTutoring() {
            return this.isModuleVisible(this.tutoringModuleStatus) && this.tutoringStatus !== 'missing'
        },
        canShowTeaching() {
            return this.isModuleVisible(this.teachingModuleStatus) && this.teachingStatus !== 'missing'
        },
        canShowRestaurant() {
            return this.isModuleVisible(this.restaurantModuleStatus)
        },
        isRegisterDisabled() {
            return this.registerStatus === 'expired' || !this.moduleAllowsAccess(this.registerModuleStatus)
        },
        isTutoringDisabled() {
            return this.tutoringStatus === 'expired' || !this.moduleAllowsAccess(this.tutoringModuleStatus)
        },
        isTeachingDisabled() {
            return this.teachingStatus === 'expired' || !this.moduleAllowsAccess(this.teachingModuleStatus)
        },
        isRestaurantDisabled() {
            return !this.moduleAllowsAccess(this.restaurantModuleStatus)
        },
        registerBadge() {
            return this.buildBadge(this.registerStatus, this.registerModuleStatus)
        },
        tutoringBadge() {
            return this.buildBadge(this.tutoringStatus, this.tutoringModuleStatus)
        },
        teachingBadge() {
            return this.buildBadge(this.teachingStatus, this.teachingModuleStatus)
        },
        restaurantBadge() {
            return this.buildBadge('active', this.restaurantModuleStatus)
        },
    },

    watch: {
        selected_school_id() {
            this.selected_school = this.schools.find((item) => item.id == this.selected_school_id)
            nextTick(() => {
                this.$refs.submitBtn?.$el?.focus()
            })
        },
    },

    methods: {
        doAlert() {
            alert('1')
        },
        scrollToProducts() {
            this.$router.push('/homepage/products')
        },
        openUnterricht() {
            if (!this.canShowTeaching || this.teachingStatus !== 'active' || !this.moduleAllowsAccess(this.teachingModuleStatus)) {
                this.notifyToolUnavailable('Lehrertool', this.teachingStatus)
                return
            }
            this.$router.push('/homepage/student')
        },
        openRestaurant() {
            if (!this.canShowRestaurant || !this.moduleAllowsAccess(this.restaurantModuleStatus)) {
                this.notifyToolUnavailable('Restaurant', 'active', this.restaurantModuleStatus)
                return
            }

            this.$router.push('/homepage/restaurant')
        },
        isModuleVisible(status) {
            return status !== 'inactive'
        },
        moduleAllowsAccess(status) {
            return ['active', 'test_modus'].includes(status)
        },
        buildBadge(licenceStatus, moduleStatus) {
            if (licenceStatus === 'expired') {
                return { icon: 'mdi-clock-alert-outline', label: 'Lizenz abgelaufen' }
            }

            if (moduleStatus === 'test_modus') {
                return { icon: 'mdi-flask-outline', label: 'Testmodus' }
            }

            if (moduleStatus === 'comming_soon') {
                return { icon: 'mdi-progress-clock', label: 'Kommt bald' }
            }

            return null
        },
        notifyToolUnavailable(tool, status, moduleStatus = null) {
            const notification = useNotificationStore()
            const toolLabel = {
                Anmeldetool: 'Anmeldetool',
                Nachhilfetool: this.tutoringDisplayName,
                Lehrertool: 'Unterricht',
                Restaurant: 'Restaurant',
            }[tool] || tool

            const message =
                moduleStatus === 'comming_soon'
                    ? `${toolLabel}: kommt bald.`
                    : status === 'expired'
                    ? `${toolLabel}: Lizenz abgelaufen.`
                    : `${toolLabel}: Lizenz nicht vorhanden.`

            notification.notify({
                status: 403,
                message,
                type: 'warning',
                timeout: 3500,
            })
        },
        async handleToolCardClick(tool) {
            const status = this.toolStatuses?.[tool] || 'missing'
            const moduleStatus = {
                Anmeldetool: this.registerModuleStatus,
                Nachhilfetool: this.tutoringModuleStatus,
                Lehrertool: this.teachingModuleStatus,
            }[tool] || 'inactive'

            if (!this.moduleAllowsAccess(moduleStatus) || status !== 'active') {
                this.notifyToolUnavailable(tool, status, moduleStatus)
                return
            }

            const ok = await this.homepageStore.loadSchoolsForTool(tool)
            if (ok === false) return
            this.step = 'selectSchool'
        },
        moveTo(licence, school) {
            console.log('moveTo')
            var path = '/homepage/'
            switch (licence.name) {
                case 'Anmeldetool':
                    path += 'register'
                    break
                case 'Nachhilfetool':
                    path += 'tutoring_overview/'
                    break
            }
            path += '?school=' + school.short_name

            this.$router.push(path)
        },

        abort(step) {
            this.selected_school = null
            this.selected_school_id = null
            this.step = step
        },

        async loadSchoolsForTool(tool) {
            const ok = await this.homepageStore.loadSchoolsForTool(tool)
            if (ok === false) return
            this.step = 'selectSchool'
        },

        getParticleStyle(n) {
            const random = (min, max) => Math.random() * (max - min) + min
            return {
                left: `${random(0, 100)}%`,
                top: `${random(0, 100)}%`,
                width: `${random(4, 12)}px`,
                height: `${random(4, 12)}px`,
                animationDelay: `${random(0, 15)}s`,
                animationDuration: `${random(15, 30)}s`,
            }
        },
    },
}
</script>

<style scoped>
/* Base Layout */
.intro-page {
    min-height: 100vh;
    position: relative;
    overflow: hidden;
    background: #f7901e;
}

/* Animated Background */
.animated-bg {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    z-index: 0;
    pointer-events: none;
}

.hero-bg-image {
    position: absolute;
    inset: 0;
}

.hero-bg-image::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(650px 220px at 50% 16%, rgba(255, 220, 145, 0.16), transparent 70%), linear-gradient(180deg, rgba(255, 170, 68, 0.05), rgba(232, 103, 28, 0.05));
}

.cf-grid {
    position: absolute;
    inset: 0;
    opacity: 0.25;
    background-image: linear-gradient(rgba(18, 44, 68, 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(18, 44, 68, 0.05) 1px, transparent 1px);
    background-size: 48px 48px;
    mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.9), rgba(0, 0, 0, 0.4) 55%, transparent 100%);
}

.cf-sun-glow {
    position: absolute;
    left: 50%;
    top: -8%;
    width: min(1100px, 95vw);
    height: min(520px, 44vh);
    transform: translateX(-50%);
    background: radial-gradient(closest-side, rgba(255, 180, 72, 0.9), rgba(245, 129, 32, 0.5) 48%, rgba(245, 129, 32, 0.08) 72%, transparent 82%);
    filter: blur(12px);
    opacity: 0.9;
    animation: cfPulse 18s ease-in-out infinite;
}

.cf-sun-core {
    position: absolute;
    left: 50%;
    top: 6%;
    width: min(440px, 58vw);
    height: min(210px, 22vh);
    transform: translateX(-50%);
    border-radius: 999px 999px 0 0;
    background: radial-gradient(140% 120% at 50% 90%, rgba(255, 228, 166, 0.95), rgba(255, 180, 72, 0.95) 45%, rgba(243, 129, 29, 0.9) 72%, rgba(243, 129, 29, 0.1) 100%);
    box-shadow:
        0 0 0 1px rgba(255, 214, 140, 0.35) inset,
        0 0 120px rgba(245, 129, 32, 0.35);
    opacity: 0.95;
}

.cf-wave {
    position: absolute;
    left: 50%;
    width: 150%;
    border-radius: 50% 50% 0 0 / 100% 100% 0 0;
    transform: translateX(-50%);
    filter: blur(0.2px);
}

.wave-back {
    bottom: 24%;
    height: 24%;
    background: radial-gradient(120% 160% at 50% 0%, rgba(255, 196, 105, 0.45), rgba(255, 196, 105, 0) 48%), linear-gradient(180deg, rgba(32, 88, 125, 0.3), rgba(18, 54, 81, 0.8));
    border-top: 1px solid rgba(255, 222, 174, 0.35);
    opacity: 0.7;
    animation: waveShift 26s ease-in-out infinite;
}

.wave-mid {
    bottom: 12%;
    height: 28%;
    background:
        radial-gradient(100% 170% at 50% -10%, rgba(255, 189, 89, 0.35), rgba(255, 189, 89, 0) 50%), linear-gradient(180deg, rgba(22, 69, 102, 0.45), rgba(12, 33, 53, 0.92));
    border-top: 1px solid rgba(255, 204, 132, 0.28);
    opacity: 0.9;
    animation: waveShift 20s ease-in-out infinite reverse;
}

.wave-front {
    bottom: -2%;
    height: 24%;
    background: linear-gradient(180deg, rgba(13, 39, 60, 0.25), rgba(9, 24, 38, 0.96)), radial-gradient(90% 200% at 50% 0%, rgba(255, 160, 61, 0.15), rgba(255, 160, 61, 0) 60%);
    border-top: 1px solid rgba(255, 190, 111, 0.16);
    animation: waveShift 16s ease-in-out infinite;
}

.cf-haze {
    position: absolute;
    width: min(560px, 72vw);
    height: min(260px, 24vh);
    border-radius: 50%;
    filter: blur(50px);
    opacity: 0.35;
}

.haze-left {
    left: -10%;
    top: 28%;
    background: radial-gradient(circle, rgba(255, 183, 94, 0.6), rgba(255, 183, 94, 0) 70%);
    animation: float 22s ease-in-out infinite;
}

.haze-right {
    right: -8%;
    top: 34%;
    background: radial-gradient(circle, rgba(84, 143, 191, 0.55), rgba(84, 143, 191, 0) 70%);
    animation: float 26s ease-in-out infinite reverse;
}

@keyframes float {
    0%,
    100% {
        transform: translate(0, 0) scale(1);
    }
    25% {
        transform: translate(30px, -30px) scale(1.05);
    }
    50% {
        transform: translate(-20px, 20px) scale(0.95);
    }
    75% {
        transform: translate(-30px, -20px) scale(1.02);
    }
}

@keyframes waveShift {
    0%,
    100% {
        transform: translateX(-50%) translateY(0);
    }
    50% {
        transform: translateX(calc(-50% + 18px)) translateY(-8px);
    }
}

@keyframes cfPulse {
    0%,
    100% {
        opacity: 0.82;
        transform: translateX(-50%) scale(1);
    }
    50% {
        opacity: 0.96;
        transform: translateX(-50%) scale(1.03);
    }
}

/* Hero Section */
.hero-section {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 24px 20px 40px;
}

.hero-content {
    display: flex;
    flex-direction: column;
}

.cloud-header {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 12px;
    padding: 6px 4px;
    animation: fadeInDown 0.6s ease-out;
}

.cloud-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    flex-wrap: wrap;
}

.logo-wrapper-nav {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    margin-bottom: 0;
    box-shadow: 0 3px 8px rgba(243, 146, 0, 0.12);
    animation: none;
}

.cloud-brand-title {
    display: flex;
    align-items: baseline;
    gap: 2px;
    font-size: 1.28rem;
    font-weight: 800;
    letter-spacing: -0.35px;
    margin: 0;
    line-height: 1;
    white-space: nowrap;
}

.cloud-header-nav {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-left: 34px;
}

.cloud-nav-item {
    display: inline-flex;
    align-items: center;
    gap: 0;
    border: 0;
    background: transparent;
    color: #233645;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 0.98rem;
    font-weight: 400;
    cursor: default;
    box-shadow: none;
    opacity: 0.95;
}

.cloud-nav-item:hover {
    background: rgba(255, 255, 255, 0.18);
}

.cloud-hero-copy {
    margin-top: clamp(72px, 12vw, 138px);
    max-width: 1120px;
    color: #14293b;
    animation: fadeInUp 0.8s ease-out 0.1s both;
}

.cloud-hero-title {
    margin: 0;
    font-size: clamp(2.1rem, 4.8vw, 4.1rem);
    line-height: 0.98;
    letter-spacing: 0.015em;
    font-weight: 700;
    color: #10263a;
    max-width: 1080px;
}

.cloud-hero-description {
    margin: 22px 0 0;
    max-width: 720px;
    font-size: clamp(1rem, 1.45vw, 1.28rem);
    line-height: 1.45;
    font-weight: 400;
    color: rgba(16, 38, 58, 0.9);
}

/* Brand */
.brand-container {
    text-align: center;
    margin-bottom: 60px;
    animation: fadeInDown 0.8s ease-out;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.logo-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #3aaa35 0%, #f39200 100%);
    border: solid 1px #efc382;
    border-radius: 20px;
    margin-bottom: 20px;
    box-shadow: 0 10px 40px rgba(58, 170, 53, 0.3);
    animation: pulse-glow 3s ease-in-out infinite;
}

@keyframes pulse-glow {
    0%,
    100% {
        box-shadow: 0 10px 40px rgba(58, 170, 53, 0.3);
    }
    50% {
        box-shadow: 0 10px 60px rgba(243, 146, 0, 0.4);
    }
}

.brand-title {
    font-size: clamp(2.5rem, 8vw, 4.5rem);
    font-weight: 800;
    letter-spacing: -2px;
    margin: 0;
    line-height: 1;
}

.brand-school {
    color: #3aaa35;
}

.brand-tool {
    color: #37474f;
}

.brand-tagline {
    font-size: clamp(1rem, 3vw, 1.4rem);
    color: #546e7a;
    margin-top: 12px;
    font-weight: 400;
}

/* Tools Section */
.tools-container {
    animation: fadeInUp 0.8s ease-out 0.2s both;
    margin-top: 34px;
    width: 100%;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.section-title {
    text-align: left;
    font-size: 1.5rem;
    font-weight: 600;
    color: #37474f;
    margin-bottom: 32px;
}

.tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 320px));
    gap: 18px;
    width: 100%;
    margin: 0;
    justify-content: start;
}

/* Tool Cards */
.tool-card {
    position: relative;
    background: white;
    border-radius: 20px;
    padding: 22px;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    text-decoration: none;
    color: inherit;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    display: flex;
    flex-direction: column;
}

.tool-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.tools-grid > .tool-card:nth-child(odd) {
    border-color: rgba(58, 170, 53, 0.35);
}

.tools-grid > .tool-card:nth-child(even) {
    border-color: rgba(243, 146, 0, 0.35);
}

.tools-grid > .tool-card:nth-child(odd):hover {
    border-color: rgba(58, 170, 53, 0.65);
}

.tools-grid > .tool-card:nth-child(even):hover {
    border-color: rgba(243, 146, 0, 0.65);
}

.card-glow {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    border-radius: 20px 20px 0 0;
    transition: height 0.3s ease;
}

.tool-card:hover .card-glow {
    height: 6px;
}

.tools-grid > .tool-card:nth-child(odd) .card-glow {
    background: linear-gradient(90deg, #3aaa35, #4bc044);
}

.tools-grid > .tool-card:nth-child(even) .card-glow {
    background: linear-gradient(90deg, #f39200, #ffb74d);
}

.card-content {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.card-icon {
    width: 58px;
    height: 58px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    transition: transform 0.3s ease;
}

.tool-card:hover .card-icon {
    transform: scale(1.1);
}

.card-register .card-icon {
    background: linear-gradient(135deg, rgba(58, 170, 53, 0.15), rgba(58, 170, 53, 0.05));
    color: #3aaa35;
}

.card-tutoring .card-icon {
    background: linear-gradient(135deg, rgba(243, 146, 0, 0.15), rgba(243, 146, 0, 0.05));
    color: #f39200;
}

.card-lernportal .card-icon {
    background: linear-gradient(135deg, rgba(253, 128, 46, 0.2), rgba(35, 61, 76, 0.14));
    color: #233d4c;
}

.card-lunch .card-icon {
    background: linear-gradient(135deg, rgba(58, 170, 53, 0.15), rgba(58, 170, 53, 0.05));
    color: #3aaa35;
}

.card-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #263238;
    margin: 0 0 12px 0;
}

.card-description {
    font-size: 1rem;
    color: #607d8b;
    line-height: 1.6;
    margin: 0 0 24px 0;
}

.card-action {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    transition: gap 0.3s ease;
    margin-top: auto;
}

.tools-grid > .tool-card:nth-child(odd) .card-action {
    color: #3aaa35;
}

.tools-grid > .tool-card:nth-child(even) .card-action {
    color: #f39200;
}

.tool-card:hover .card-action {
    gap: 12px;
}

/* Disabled State */
.card-disabled {
    cursor: not-allowed;
    opacity: 0.7;
}

.card-disabled:hover {
    transform: none;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.card-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(243, 146, 0, 0.1);
    color: #f39200;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    margin-top: 12px;
}

/* Documentation Section */
.docs-section {
    text-align: center;
    margin-top: 48px;
    animation: fadeInUp 0.8s ease-out 0.4s both;
}

.docs-link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #546e7a;
    text-decoration: none;
    font-size: 1rem;
    font-weight: 500;
    padding: 12px 24px;
    border-radius: 30px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.docs-link:hover {
    background: white;
    color: #37474f;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

/* Products Showcase */
.products-showcase {
    position: relative;
    z-index: 1;
    padding: 36px 20px 80px;
}

.products-shell {
    display: grid;
    gap: 20px;
}

.products-intro {
    background: linear-gradient(180deg, rgba(255, 249, 239, 0.86), rgba(255, 243, 224, 0.72));
    border: 1px solid rgba(255, 255, 255, 0.55);
    border-radius: 24px;
    padding: 28px 28px 24px;
    backdrop-filter: blur(8px);
    box-shadow: 0 14px 40px rgba(132, 65, 13, 0.08);
}

.products-eyebrow {
    display: inline-flex;
    align-items: center;
    padding: 5px 10px;
    border-radius: 999px;
    background: rgba(245, 129, 32, 0.12);
    color: #b45812;
    font-size: 0.84rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.products-heading {
    margin: 14px 0 10px;
    color: #10263a;
    font-size: clamp(1.4rem, 2.2vw, 2.1rem);
    line-height: 1.05;
    letter-spacing: -0.01em;
}

.products-lead {
    margin: 0;
    max-width: 860px;
    color: rgba(16, 38, 58, 0.88);
    font-size: 1rem;
    line-height: 1.5;
}

.product-section {
    position: relative;
    display: grid;
    grid-template-columns: minmax(260px, 1.1fr) minmax(220px, 500px);
    gap: 20px;
    align-items: stretch;
    padding: 22px;
    border-radius: 24px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.45);
    box-shadow: 0 16px 48px rgba(95, 47, 9, 0.1);
    backdrop-filter: blur(10px);
}

.product-section::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(120deg, rgba(255, 255, 255, 0.22), rgba(255, 255, 255, 0) 45%);
}

.product-section--register {
    background: linear-gradient(140deg, rgba(255, 250, 241, 0.94), rgba(255, 238, 212, 0.8));
}

.product-section--teaching {
    background: linear-gradient(140deg, rgba(248, 252, 255, 0.92), rgba(230, 241, 250, 0.82));
}

.product-section--tutoring {
    background: linear-gradient(140deg, rgba(255, 248, 236, 0.92), rgba(255, 234, 210, 0.8));
}

.product-section--materials {
    background: linear-gradient(140deg, rgba(242, 251, 247, 0.94), rgba(227, 245, 236, 0.82));
}

.product-section--admin {
    background: linear-gradient(140deg, rgba(245, 249, 253, 0.92), rgba(228, 237, 246, 0.84));
}

.product-copy {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.product-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #233d4c;
    font-weight: 700;
    font-size: 0.92rem;
}

.product-title {
    margin: 12px 0 10px;
    color: #10263a;
    font-size: clamp(1.15rem, 1.6vw, 1.8rem);
    line-height: 1.08;
    letter-spacing: -0.01em;
}

.product-text {
    margin: 0;
    color: rgba(16, 38, 58, 0.86);
    line-height: 1.45;
    font-size: 0.98rem;
    max-width: 56ch;
}

.product-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
}

.product-tags span {
    border-radius: 999px;
    padding: 6px 10px;
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(16, 38, 58, 0.08);
    color: #243748;
    font-size: 0.82rem;
    font-weight: 600;
}

.product-visual {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 500px;
    justify-self: end;
    min-height: 200px;
    border-radius: 18px;
    border: 1px solid rgba(255, 255, 255, 0.65);
    background: rgba(255, 255, 255, 0.5);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px;
}

.visual-register {
    justify-content: flex-start;
    background: rgba(255, 255, 255, 0.88);
}

.product-illustration {
    display: block;
    width: 100%;
    height: auto;
    object-fit: contain;
}

.product-illustration-register {
    max-width: 440px;
    max-height: 240px;
    filter: drop-shadow(0 14px 26px rgba(18, 32, 46, 0.08));
}

.product-illustration-teaching {
    max-width: 430px;
    max-height: 245px;
    filter: drop-shadow(0 14px 26px rgba(18, 32, 46, 0.08));
}

.product-illustration-tutoring {
    max-width: 420px;
    max-height: 240px;
    filter: drop-shadow(0 14px 26px rgba(18, 32, 46, 0.08));
}

.product-illustration-materials {
    max-width: 430px;
    max-height: 235px;
    filter: drop-shadow(0 14px 26px rgba(18, 32, 46, 0.08));
}

.product-illustration-lunch {
    max-width: 410px;
    max-height: 235px;
    filter: drop-shadow(0 14px 26px rgba(18, 32, 46, 0.08));
}

.mock-panel {
    width: 100%;
    max-width: 430px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(16, 38, 58, 0.08);
    box-shadow: 0 16px 30px rgba(41, 23, 9, 0.08);
    overflow: hidden;
}

.mock-panel-top {
    display: flex;
    gap: 6px;
    padding: 10px 12px;
    border-bottom: 1px solid rgba(16, 38, 58, 0.06);
}

.mock-panel-top span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: rgba(16, 38, 58, 0.16);
}

.slot-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    padding: 12px;
}

.slot-card {
    border-radius: 10px;
    padding: 10px;
    background: rgba(242, 247, 251, 0.9);
    border: 1px solid rgba(16, 38, 58, 0.05);
    color: #243748;
    font-size: 0.8rem;
    line-height: 1.2;
}

.slot-card-active {
    background: rgba(245, 129, 32, 0.12);
    border-color: rgba(245, 129, 32, 0.25);
}

.slot-card-muted {
    opacity: 0.65;
}

.visual-teaching {
    background: radial-gradient(250px 130px at 15% 20%, rgba(92, 158, 214, 0.2), transparent 70%), rgba(255, 255, 255, 0.55);
    display: grid;
    grid-template-columns: 1.15fr 0.85fr;
    gap: 12px;
}

.lesson-board,
.task-card {
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.86);
    border: 1px solid rgba(16, 38, 58, 0.08);
    box-shadow: 0 10px 24px rgba(16, 38, 58, 0.06);
}

.lesson-board {
    padding: 14px;
}

.lesson-board-title,
.task-card-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #162f45;
    margin-bottom: 10px;
}

.lesson-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid rgba(16, 38, 58, 0.05);
    font-size: 0.86rem;
    color: #294156;
}

.lesson-row:last-child {
    border-bottom: 0;
}

.task-card {
    padding: 14px;
    align-self: center;
}

.task-pill-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.task-pill {
    padding: 6px 9px;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 600;
    background: rgba(16, 38, 58, 0.06);
    color: #25394c;
}

.task-pill-hot {
    background: rgba(245, 129, 32, 0.14);
    color: #a95414;
}

.visual-tutoring {
    background: radial-gradient(180px 100px at 80% 18%, rgba(245, 129, 32, 0.16), transparent 70%), rgba(255, 255, 255, 0.54);
}

.match-network {
    position: relative;
    width: 100%;
    height: 230px;
    max-width: 420px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.86);
    border: 1px solid rgba(16, 38, 58, 0.08);
    overflow: hidden;
}

.network-center,
.network-node {
    position: absolute;
    border-radius: 999px;
    padding: 7px 11px;
    font-size: 0.78rem;
    font-weight: 700;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.94);
    color: #233a4c;
    z-index: 2;
}

.network-center {
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    background: rgba(245, 129, 32, 0.16);
    border-color: rgba(245, 129, 32, 0.28);
    color: #a65212;
}

.network-node-a {
    left: 18px;
    top: 24px;
}

.network-node-b {
    right: 22px;
    top: 36px;
}

.network-node-c {
    left: 24px;
    bottom: 30px;
}

.network-node-d {
    right: 18px;
    bottom: 22px;
}

.network-link {
    position: absolute;
    height: 2px;
    background: linear-gradient(90deg, rgba(16, 38, 58, 0.18), rgba(245, 129, 32, 0.24));
    transform-origin: left center;
    z-index: 1;
}

.network-link-a {
    left: 98px;
    top: 53px;
    width: 132px;
    transform: rotate(28deg);
}

.network-link-b {
    left: 220px;
    top: 116px;
    width: 120px;
    transform: rotate(-34deg);
}

.network-link-c {
    left: 102px;
    top: 174px;
    width: 138px;
    transform: rotate(-29deg);
}

.network-link-d {
    left: 220px;
    top: 114px;
    width: 144px;
    transform: rotate(34deg);
}

.visual-admin {
    background: radial-gradient(180px 100px at 22% 18%, rgba(95, 158, 214, 0.16), transparent 70%), rgba(255, 255, 255, 0.54);
}

.visual-materials {
    background: radial-gradient(180px 100px at 20% 15%, rgba(74, 176, 122, 0.18), transparent 70%), rgba(255, 255, 255, 0.54);
}

.materials-board {
    width: 100%;
    max-width: 430px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(16, 38, 58, 0.08);
    box-shadow: 0 12px 26px rgba(16, 38, 58, 0.06);
    overflow: hidden;
}

.materials-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 12px;
    border-bottom: 1px solid rgba(16, 38, 58, 0.06);
    background: rgba(247, 251, 248, 0.85);
}

.materials-chip {
    border-radius: 999px;
    padding: 5px 10px;
    font-size: 0.74rem;
    font-weight: 700;
    color: #315146;
    background: rgba(74, 176, 122, 0.08);
    border: 1px solid rgba(74, 176, 122, 0.14);
}

.materials-chip-active {
    background: rgba(74, 176, 122, 0.14);
    border-color: rgba(74, 176, 122, 0.24);
    color: #24573f;
}

.materials-list {
    padding: 10px;
    display: grid;
    gap: 8px;
}

.materials-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid rgba(16, 38, 58, 0.05);
    background: rgba(247, 250, 252, 0.82);
}

.materials-item > div {
    min-width: 0;
}

.materials-item strong {
    display: block;
    color: #1f3748;
    font-size: 0.82rem;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.materials-item small {
    display: block;
    margin-top: 2px;
    color: rgba(31, 55, 72, 0.72);
    font-size: 0.72rem;
}

.materials-item > span {
    flex-shrink: 0;
    border-radius: 999px;
    padding: 4px 8px;
    font-size: 0.68rem;
    font-weight: 700;
    background: rgba(16, 38, 58, 0.06);
    color: #2f4759;
}

.materials-item-folder {
    background: rgba(255, 251, 239, 0.86);
    border-color: rgba(245, 173, 63, 0.16);
}

.materials-item-folder > span {
    background: rgba(245, 173, 63, 0.12);
    color: #8b5713;
}

.materials-item-active {
    background: rgba(74, 176, 122, 0.1);
    border-color: rgba(74, 176, 122, 0.18);
}

.materials-item-active > span {
    background: rgba(74, 176, 122, 0.14);
    color: #24573f;
}

.admin-stack {
    width: 100%;
    max-width: 410px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(16, 38, 58, 0.08);
    padding: 14px;
    box-shadow: 0 12px 26px rgba(16, 38, 58, 0.06);
}

.admin-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid rgba(16, 38, 58, 0.05);
    font-size: 0.85rem;
    color: #30475b;
}

.admin-line:last-of-type {
    border-bottom: 0;
}

.admin-bars {
    margin-top: 14px;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    align-items: end;
    gap: 8px;
    height: 86px;
}

.admin-bar {
    border-radius: 8px 8px 4px 4px;
    background: linear-gradient(180deg, rgba(95, 158, 214, 0.9), rgba(44, 105, 157, 0.9));
}

.admin-bar-1 {
    height: 34%;
}

.admin-bar-2 {
    height: 68%;
}

.admin-bar-3 {
    height: 50%;
}

.admin-bar-4 {
    height: 88%;
    background: linear-gradient(180deg, rgba(245, 129, 32, 0.92), rgba(216, 104, 21, 0.92));
}

/* Selection Section */
.selection-section {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}

.selection-container {
    width: 100%;
    max-width: 500px;
    animation: scaleIn 0.4s ease-out;
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.selection-card {
    border-radius: 24px !important;
    overflow: hidden;
}

.selection-header {
    padding: 32px;
    color: white;
    position: relative;
}

.header-green {
    background: linear-gradient(135deg, #3aaa35 0%, #2d8a2a 100%);
}

.header-orange {
    background: linear-gradient(135deg, #f39200 0%, #d67f00 100%);
}

.back-btn {
    position: absolute;
    top: 16px;
    left: 16px;
    color: white !important;
}

.header-content {
    text-align: center;
    padding-top: 8px;
}

.header-icon {
    margin-bottom: 12px;
    opacity: 0.9;
}

.header-title {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.header-subtitle {
    font-size: 1rem;
    opacity: 0.9;
    margin: 0;
}

.selection-body {
    padding: 32px !important;
}

/* School Picker */
.school-picker {
    text-align: center;
}

.picker-icon {
    margin-bottom: 16px;
}

.picker-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #37474f;
    margin: 0 0 24px 0;
}

.school-autocomplete {
    max-width: 100%;
}

.no-schools {
    text-align: center;
    padding: 24px;
    color: #78909c;
}

.no-schools p {
    margin-top: 12px;
}

/* School Confirmation */
.school-confirmation {
    text-align: center;
}

.school-info {
    margin-bottom: 32px;
}

.school-logo img {
    max-height: 80px;
    max-width: 200px;
    margin-bottom: 16px;
}

.school-icon {
    margin-bottom: 16px;
}

.school-name {
    font-size: 1.5rem;
    font-weight: 700;
    color: #263238;
    margin: 0 0 4px 0;
}

.school-short {
    font-size: 1rem;
    color: #78909c;
    margin: 0;
}

.confirmation-actions {
    display: flex;
    gap: 16px;
    justify-content: center;
    flex-wrap: wrap;
}

/* Particles */
.particles {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
    overflow: hidden;
}

.particle {
    position: absolute;
    background: rgba(245, 129, 32, 0.2);
    border-radius: 50%;
    animation: drift 20s ease-in-out infinite;
}

.particle:nth-child(even) {
    background: rgba(255, 183, 94, 0.22);
}

.particle:nth-child(3n) {
    background: rgba(68, 123, 168, 0.18);
}

@keyframes drift {
    0%,
    100% {
        transform: translate(0, 0) rotate(0deg);
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    90% {
        opacity: 1;
    }
    100% {
        transform: translate(100px, -100px) rotate(360deg);
        opacity: 0;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .cf-sun-core {
        top: 8%;
        width: min(360px, 72vw);
        height: min(180px, 18vh);
    }

    .wave-back {
        bottom: 22%;
        height: 22%;
    }

    .wave-mid {
        bottom: 11%;
        height: 27%;
    }

    .wave-front {
        height: 26%;
    }

    .products-showcase {
        padding: 28px 16px 56px;
    }

    .products-intro {
        padding: 20px 18px;
        border-radius: 18px;
    }

    .product-section {
        grid-template-columns: 1fr;
        gap: 14px;
        padding: 16px;
        border-radius: 18px;
    }

    .product-visual {
        max-width: 100%;
        justify-self: stretch;
        min-height: 190px;
        border-radius: 14px;
        padding: 12px;
    }

    .visual-teaching {
        grid-template-columns: 1fr;
    }

    .match-network {
        height: 210px;
    }

    .hero-section {
        padding: 20px 16px;
    }

    .cloud-header {
        gap: 10px;
        padding: 4px 2px;
    }

    .cloud-header-nav {
        gap: 10px;
        margin-left: 18px;
    }

    .cloud-nav-item {
        padding: 4px 6px;
        font-size: 0.9rem;
    }

    .cloud-brand-title {
        font-size: 1.12rem;
    }

    .logo-wrapper-nav {
        width: 20px;
        height: 20px;
        border-radius: 5px;
    }

    .cloud-hero-copy {
        margin-top: 66px;
        max-width: 100%;
    }

    .cloud-hero-description {
        margin-top: 16px;
        max-width: 92%;
        line-height: 1.4;
    }

    .brand-container {
        margin-bottom: 40px;
    }

    .logo-wrapper {
        width: 64px;
        height: 64px;
        border-radius: 16px;
    }

    .tools-grid {
        gap: 16px;
        max-width: 100%;
    }

    .tool-card {
        padding: 20px;
    }

    .card-icon {
        width: 52px;
        height: 52px;
    }

    .card-title {
        font-size: 1.25rem;
    }

    .section-title {
        font-size: 1.25rem;
        margin-bottom: 24px;
    }

    .docs-section {
        margin-top: 32px;
    }

    .selection-header {
        padding: 24px;
    }

    .selection-body {
        padding: 24px !important;
    }

    .confirmation-actions {
        flex-direction: column;
    }

    .confirmation-actions .v-btn {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .products-shell {
        gap: 14px;
    }

    .products-heading {
        margin-top: 10px;
    }

    .product-title {
        font-size: 1.08rem;
    }

    .product-text {
        font-size: 0.92rem;
    }

    .product-tags {
        gap: 6px;
    }

    .slot-grid {
        grid-template-columns: 1fr;
    }

    .match-network {
        height: 190px;
    }

    .network-center,
    .network-node {
        font-size: 0.72rem;
        padding: 6px 9px;
    }

    .cloud-header {
        align-items: center;
        flex-direction: row;
    }

    .cloud-header-nav {
        width: auto;
        justify-content: flex-start;
        flex-wrap: nowrap;
        gap: 8px;
        margin-left: 14px;
    }

    .cloud-hero-copy {
        margin-top: 51px;
    }

    .cloud-hero-title {
        font-size: 1.7rem;
        line-height: 1.02;
        max-width: 95%;
    }

    .cloud-hero-description {
        font-size: 0.98rem;
        max-width: 100%;
    }

    .brand-title {
        font-size: 2.2rem;
    }

    .brand-tagline {
        font-size: 0.95rem;
    }

    .tool-card {
        padding: 20px;
    }

    .card-description {
        font-size: 0.9rem;
    }
}

/* Accessibility - Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    .cf-sun-glow,
    .cf-wave,
    .cf-haze,
    .particle,
    .tool-card,
    .logo-wrapper {
        animation: none;
    }

    .tool-card:hover {
        transform: none;
    }

    .tool-card:hover .card-icon {
        transform: none;
    }
}
</style>
