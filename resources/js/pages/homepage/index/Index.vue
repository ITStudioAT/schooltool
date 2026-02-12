<template>
    <div class="intro-page">
        <!-- Animated Background -->
        <div class="animated-bg">
            <div class="gradient-orb orb-1"></div>
            <div class="gradient-orb orb-2"></div>
            <div class="gradient-orb orb-3"></div>
        </div>

        <!-- Hero Section -->
        <section class="hero-section" v-if="step === ''">
            <div class="hero-content">
                <!-- Logo/Brand -->
                <div class="brand-container">
                    <div class="logo-wrapper">
                        <span class="logo-icon">
                            <v-icon size="48" color="white">mdi-school</v-icon>
                        </span>
                    </div>
                    <h1 class="brand-title">
                        <span class="brand-school">School</span>
                        <span class="brand-tool">Tool</span>
                    </h1>
                    <p class="brand-tagline">Digitale Werkzeuge für moderne Schulen</p>
                </div>

                <!-- Feature Cards -->
                <div class="tools-container">
                    <h2 class="section-title">Wählen Sie Ihr Werkzeug</h2>

                    <div class="tools-grid">
                        <!-- Anmeldetool Card -->
                        <div class="tool-card card-register" @click="loadSchoolsForTool('Anmeldetool')">
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
                            </div>
                        </div>

                        <!-- Nachhilfetool Card -->
                        <div
                            class="tool-card card-tutoring"
                            :class="{ 'card-disabled': !config?.tutoring_active }"
                            @click="config?.tutoring_active && loadSchoolsForTool('Nachhilfetool')">
                            <div class="card-glow"></div>
                            <div class="card-content">
                                <div class="card-icon">
                                    <v-icon size="40">mdi-account-group</v-icon>
                                </div>
                                <h3 class="card-title">Schüler helfen Schülern</h3>
                                <p class="card-description">Nachhilfe von Schülern für Schüler. Gemeinsam zum Erfolg.</p>
                                <div class="card-action" v-if="config?.tutoring_active">
                                    <span class="action-text">Starten</span>
                                    <v-icon size="20">mdi-arrow-right</v-icon>
                                </div>
                                <div class="card-badge" v-else>
                                    <v-icon size="16">mdi-wrench</v-icon>
                                    <span>In Entwicklung</span>
                                </div>
                            </div>
                        </div>

                        <!-- Unterricht Card -->
                        <div class="tool-card card-lernportal" @click="openUnterricht">
                            <div class="card-glow"></div>
                            <div class="card-content">
                                <div class="card-icon">
                                    <v-icon size="40">mdi-rocket-launch-outline</v-icon>
                                </div>
                                <h3 class="card-title">Unterricht</h3>
                                <p class="card-description">Dein neuer Lernbereich mit modernem Look. Einstieg in den Login-Bereich fuer Schueler.</p>
                                <div class="card-action">
                                    <span class="action-text">Starten</span>
                                    <v-icon size="20">mdi-arrow-right</v-icon>
                                </div>
                            </div>
                        </div>

                        <!-- Mittagsmenüs Card -->
                        <a href="https://cdgym.info/lunch" target="_blank" class="tool-card card-lunch">
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

        <!-- School Selection Step -->
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

        <!-- Floating particles for ambiance -->
        <div class="particles">
            <div class="particle" v-for="n in 20" :key="n" :style="getParticleStyle(n)"></div>
        </div>
    </div>
</template>

<script>
import { nextTick } from 'vue'
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
        openUnterricht() {
            this.$router.push('/homepage/student')
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
            await this.homepageStore.loadSchoolsForTool(tool)
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
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
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

.gradient-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.5;
    animation: float 20s ease-in-out infinite;
}

.orb-1 {
    width: 600px;
    height: 600px;
    background: linear-gradient(135deg, #3aaa35 0%, #2d8a2a 100%);
    top: -200px;
    left: -200px;
    animation-delay: 0s;
}

.orb-2 {
    width: 500px;
    height: 500px;
    background: linear-gradient(135deg, #f39200 0%, #d67f00 100%);
    bottom: -150px;
    right: -150px;
    animation-delay: -7s;
}

.orb-3 {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, #37474f 0%, #263238 100%);
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    animation-delay: -14s;
    opacity: 0.3;
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

/* Hero Section */
.hero-section {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}

.hero-content {
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
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
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #3aaa35 0%, #f39200 100%);
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
    text-align: center;
    font-size: 1.5rem;
    font-weight: 600;
    color: #37474f;
    margin-bottom: 32px;
}

.tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    max-width: 1000px;
    margin: 0 auto;
}

/* Tool Cards */
.tool-card {
    position: relative;
    background: white;
    border-radius: 20px;
    padding: 32px;
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

.card-register .card-glow {
    background: linear-gradient(90deg, #3aaa35, #4bc044);
}

.card-tutoring .card-glow {
    background: linear-gradient(90deg, #f39200, #ffb74d);
}

.card-lernportal .card-glow {
    background: linear-gradient(90deg, #3aaa35, #4bc044);
}

.card-lernportal {
    border: 1px solid rgba(58, 170, 53, 0.35);
}

.card-lernportal:hover {
    border-color: rgba(58, 170, 53, 0.6);
}

.card-lunch .card-glow {
    background: linear-gradient(90deg, #3aaa35, #4bc044);
}

.card-content {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.card-icon {
    width: 72px;
    height: 72px;
    border-radius: 16px;
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

.card-register .card-action {
    color: #3aaa35;
}

.card-tutoring .card-action {
    color: #f39200;
}

.card-lernportal .card-action {
    color: #3aaa35;
}

.card-lunch .card-action {
    color: #3aaa35;
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
    margin-top: auto;
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
    background: rgba(58, 170, 53, 0.3);
    border-radius: 50%;
    animation: drift 20s ease-in-out infinite;
}

.particle:nth-child(even) {
    background: rgba(243, 146, 0, 0.3);
}

.particle:nth-child(3n) {
    background: rgba(55, 71, 79, 0.2);
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
    .hero-section {
        padding: 20px 16px;
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
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .tool-card {
        padding: 24px;
    }

    .card-icon {
        width: 60px;
        height: 60px;
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
    .gradient-orb,
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
