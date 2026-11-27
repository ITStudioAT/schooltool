<template>
    <div class="intro-page" :class="{ 'intro-page--starting': !step }">
        <!-- Animated Background -->
        <div v-if="step && (step === 'login' || !homepageDesignEnabled)" class="animated-bg">
            <div class="hero-bg-image st-cloudflare-bg-image"></div>
        </div>

        <!-- Step: Select School -->
        <section v-if="step === 'selectSchool' && homepageDesignEnabled" class="school-entry" aria-labelledby="school-entry-title">
            <SchoolEntryHeader show-documentation />

            <div class="school-entry-shell school-entry-content">
                <div class="school-entry-hero school-entry-hero--with-info">
                    <div>
                        <h1 id="school-entry-title">Mehr Zeit für das <span>Wesentliche.</span></h1>
                        <p>Wir machen Schulprozesse schneller, einfacher und übersichtlicher.</p>
                    </div>
                    <router-link
                        :to="{ name: 'schooltool-introduction' }"
                        class="school-entry-info-link"
                        :class="{ 'school-entry-info-link--local-motion': previewDiscoveryMotion }">
                        <span class="school-entry-info-link-smoke" aria-hidden="true"></span>
                        <span class="school-entry-info-link-dial" aria-hidden="true">
                            <svg viewBox="0 0 100 100">
                                <path
                                    d="M50 5.5v4 M94.5 50h-4 M50 94.5v-4 M5.5 50h4"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-opacity="0.76"
                                    stroke-width="1.05"
                                    stroke-linecap="round" />
                            </svg>
                            <span ref="discoveryClock" class="school-entry-info-link-point"></span>
                        </span>
                        <span class="school-entry-info-link-label">Schooltool entdecken</span>
                    </router-link>
                </div>

                <section class="school-entry-selection" aria-labelledby="school-entry-selection-title">
                    <h2 id="school-entry-selection-title">Wählen Sie Ihre Schule</h2>

                    <div v-if="schoolListLoadFailed" class="school-entry-load-error">
                        <p role="alert">Die Schulauswahl konnte nicht geladen werden.</p>
                        <button type="button" class="school-entry-back" :disabled="isInitializing" @click="initializeHomepage">
                            Erneut laden
                        </button>
                    </div>
                    <p v-else-if="login_schools.length === 0" class="school-entry-empty">Derzeit steht keine Schule zur Auswahl.</p>
                    <ul v-else-if="login_schools.length <= 6" class="school-entry-list">
                        <li v-for="school in login_schools" :key="school.id">
                            <button
                                type="button"
                                class="school-entry-card homepage-school-card"
                                :aria-label="`${school.long_name} auswählen`"
                                :aria-describedby="school.short_name ? `school-entry-short-${school.id}` : undefined"
                                :disabled="isSelectingSchool"
                                @click="selectLoginSchool(school)">
                                <span
                                    class="school-entry-logo"
                                    :class="{
                                        'school-entry-logo--dark': schoolLogos[school.id]?.dark,
                                        'school-entry-logo--wide': schoolLogos[school.id]?.wide,
                                    }">
                                    <img
                                        v-if="school.logo"
                                        :src="schoolLogoSrc(school)"
                                        alt=""
                                        crossorigin="anonymous"
                                        @load="analyzeSchoolLogo($event, school.id)" />
                                    <v-icon v-else icon="mdi-school-outline" size="27" aria-hidden="true" />
                                </span>
                                <span class="school-entry-school">
                                    <strong>{{ school.long_name }}</strong>
                                    <span v-if="school.short_name" :id="`school-entry-short-${school.id}`">
                                        {{ school.short_name }}
                                    </span>
                                </span>
                                <span class="school-entry-arrow" aria-hidden="true">
                                    <v-icon icon="mdi-arrow-right" size="20" />
                                </span>
                            </button>
                        </li>
                    </ul>

                    <div v-else class="school-entry-search">
                        <v-autocomplete
                            v-model="selected_login_school_id"
                            :items="login_schools"
                            item-title="long_name"
                            item-value="id"
                            label="Schule suchen …"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-magnify"
                            auto-select-first
                            bg-color="white"
                            hide-details
                            :disabled="isSelectingSchool"
                            class="school-entry-search-field"
                        />
                        <v-btn
                            variant="flat"
                            size="large"
                            class="school-entry-continue"
                            :disabled="!selected_login_school_id || isSelectingSchool"
                            @click="selectLoginSchoolById(selected_login_school_id)"
                        >Weiter</v-btn>
                    </div>
                </section>
            </div>
        </section>

        <section v-else-if="step === 'selectSchool'" class="hero-section legacy-homepage">
            <div class="hero-content st-shell-1440">
                <header class="cloud-header">
                    <div class="cloud-header-left">
                        <img src="/storage/images/schooltool/schooltool-wordmark.svg" alt="SchoolTool" class="st-header-logo" />
                        <nav class="cloud-header-nav" aria-label="Navigation">
                            <a href="/documentation" target="_blank" rel="noopener noreferrer" class="cloud-nav-item">Dokumentation</a>
                        </nav>
                    </div>
                </header>

                <div class="cloud-hero-copy">
                    <h2 class="cloud-hero-title">
                        <div>
                            Mehr Zeit für das
                            <span class="text-white">Wesentliche.</span>
                        </div>
                    </h2>
                    <p class="cloud-hero-description mt-8">
                        Wir machen Schulprozesse schneller, einfacher und übersichtlicher.
                    </p>
                </div>

                <div class="tools-container">
                    <h2 class="section-title">Wählen Sie Ihre Schule</h2>

                    <div v-if="schoolListLoadFailed" class="school-entry-load-error">
                        <p role="alert">Die Schulauswahl konnte nicht geladen werden.</p>
                        <button type="button" class="school-entry-back" :disabled="isInitializing" @click="initializeHomepage">
                            Erneut laden
                        </button>
                    </div>
                    <p v-else-if="login_schools.length === 0">Derzeit steht keine Schule zur Auswahl.</p>
                    <div v-else-if="login_schools.length <= 6" class="tools-grid">
                        <button
                            v-for="school in login_schools"
                            :key="school.id"
                            type="button"
                            class="tool-card"
                            :aria-label="`${school.long_name} auswählen`"
                            :disabled="isSelectingSchool"
                            @click="selectLoginSchool(school)">
                            <div class="card-glow"></div>
                            <div class="card-content">
                                <div class="card-icon school-select-icon" :class="[school.logo ? 'school-select-icon--has-logo' : 'school-select-icon--no-logo', schoolLogos[school.id]?.dark ? 'school-select-icon--dark-logo' : '']">
                                    <img v-if="school.logo" :src="schoolLogoSrc(school)" :alt="school.long_name" class="school-select-logo-img" crossorigin="anonymous" @load="analyzeSchoolLogo($event, school.id)" />
                                    <v-icon v-else size="40">mdi-school</v-icon>
                                </div>
                                <h3 class="card-title school-card-title">{{ school.long_name }}</h3>
                                <p class="card-description school-card-short">{{ school.short_name || '\u00a0' }}</p>
                                <div class="card-action">
                                    <span class="action-text">Auswählen</span>
                                    <v-icon size="20">mdi-arrow-right</v-icon>
                                </div>
                            </div>
                        </button>
                    </div>

                    <!-- Search mode (more than 6 schools) -->
                    <div v-else class="school-search-wrap">
                        <v-autocomplete
                            v-model="selected_login_school_id"
                            :items="login_schools"
                            item-title="long_name"
                            item-value="id"
                            label="Schule suchen …"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-magnify"
                            auto-select-first
                            bg-color="white"
                            :disabled="isSelectingSchool"
                            class="school-search-field"
                        />
                        <v-btn
                            color="success"
                            flat
                            size="large"
                            :disabled="!selected_login_school_id || isSelectingSchool"
                            @click="selectLoginSchoolById(selected_login_school_id)"
                        >Weiter</v-btn>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="step === 'schoolSelected' && homepageDesignEnabled" class="school-entry school-tools" aria-labelledby="school-tools-title">
            <SchoolEntryHeader show-documentation />

            <div class="school-entry-shell school-entry-content">
                <button type="button" class="school-entry-back" @click="backToSchoolSelection">
                    <v-icon icon="mdi-arrow-left" size="19" aria-hidden="true" />
                    Andere Schule
                </button>

                <div
                    class="school-entry-hero school-entry-hero--compact school-tools-hero"
                    :class="{ 'school-tools-hero--with-logo': selected_login_school?.logo }">
                    <div
                        v-if="selected_login_school?.logo"
                        class="school-entry-logo school-tools-logo"
                        :class="{
                            'school-entry-logo--dark': schoolLogos[selected_login_school.id]?.dark,
                            'school-entry-logo--wide': schoolLogos[selected_login_school.id]?.wide,
                        }">
                        <img
                            :src="schoolLogoSrc(selected_login_school)"
                            alt=""
                            crossorigin="anonymous"
                            @load="analyzeSchoolLogo($event, selected_login_school.id)" />
                    </div>
                    <div class="school-tools-heading">
                        <h1 id="school-tools-title">{{ selected_login_school?.long_name }}</h1>
                        <p><strong>Mehr Zeit für das Wesentliche.</strong> Wir machen Schulprozesse schneller, einfacher und übersichtlicher.</p>
                    </div>
                </div>

                <section class="school-entry-selection" aria-labelledby="school-tools-list-title">
                    <h2 id="school-tools-list-title">Anwendungen</h2>
                    <ul class="school-tools-list">
                        <li v-for="tool in schoolToolCards" :key="tool.key">
                            <button
                                type="button"
                                class="school-entry-card school-tool-card"
                                :class="{ 'school-tool-card--teaching': tool.key === 'Lehrertool' }"
                                :aria-disabled="tool.disabled || undefined"
                                @click="openToolForSchool(tool.key)">
                                <span class="school-entry-logo school-tool-icon">
                                    <v-icon :icon="tool.icon" size="26" aria-hidden="true" />
                                </span>
                                <span class="school-tool-copy">
                                    <strong>{{ tool.title }}</strong>
                                    <span>{{ tool.description }}</span>
                                    <span v-if="tool.badge" class="school-tool-status">
                                        <v-icon :icon="tool.badge.icon" size="16" aria-hidden="true" />
                                        {{ tool.badge.label }}
                                    </span>
                                </span>
                                <span class="school-entry-arrow" aria-hidden="true">
                                    <v-icon icon="mdi-arrow-right" size="20" />
                                </span>
                            </button>
                        </li>
                    </ul>
                </section>
            </div>
        </section>

        <!-- Step: School Selected — show licensed tools -->
        <section v-else-if="step === 'schoolSelected'" class="hero-section legacy-homepage">
            <div class="hero-content st-shell-1440">
                <header class="cloud-header">
                    <div class="cloud-header-left">
                        <img src="/storage/images/schooltool/schooltool-wordmark.svg" alt="SchoolTool" class="st-header-logo" />
                        <nav class="cloud-header-nav" aria-label="Navigation">
                            <a href="/documentation" target="_blank" rel="noopener noreferrer" class="cloud-nav-item">Dokumentation</a>
                        </nav>
                    </div>
                </header>

                <div class="cloud-hero-copy">
                    <h2 class="cloud-hero-title">
                        <div>
                            Mehr Zeit für das
                            <span class="text-white">Wesentliche.</span>
                        </div>
                    </h2>
                    <p class="cloud-hero-description mt-8">
                        Wir machen Schulprozesse schneller, einfacher und übersichtlicher.
                    </p>
                </div>

                <div class="school-logo-banner" v-if="selected_login_school?.logo">
                    <div class="school-logo-box" :class="{ 'school-logo-box--dark': schoolLogos[selected_login_school.id]?.dark }">
                        <img
                            :src="schoolLogoSrc(selected_login_school)"
                            :alt="selected_login_school.long_name"
                            class="school-logo-img"
                            crossorigin="anonymous"
                            @load="analyzeSchoolLogo($event, selected_login_school.id, true)"
                        />
                    </div>
                </div>

                <div class="tools-container">
                    <div class="tools-header">
                        <v-btn variant="text" size="small" class="back-to-schools" @click="backToSchoolSelection">
                            <v-icon start size="18">mdi-arrow-left</v-icon>
                            Andere Schule
                        </v-btn>
                        <h2 class="section-title mb-0">{{ selected_login_school?.long_name }}</h2>
                    </div>

                    <div class="tools-grid">
                        <button
                            v-for="tool in schoolToolCards"
                            :key="tool.key"
                            type="button"
                            class="tool-card"
                            :class="[tool.legacyClass, { 'card-disabled': tool.disabled }]"
                            :aria-disabled="tool.disabled || undefined"
                            @click="openToolForSchool(tool.key)">
                            <div class="card-glow"></div>
                            <div class="card-content">
                                <div class="card-icon">
                                    <v-icon :icon="tool.icon" size="40" aria-hidden="true" />
                                </div>
                                <h3 class="card-title">{{ tool.title }}</h3>
                                <p class="card-description">{{ tool.description }}</p>
                                <div class="card-action" aria-hidden="true">
                                    <span>{{ tool.key === 'Restaurant' ? 'Zum Restaurant' : 'Starten' }}</span>
                                    <v-icon size="20">mdi-arrow-right</v-icon>
                                </div>
                                <div v-if="tool.badge" class="card-badge">
                                    <v-icon :icon="tool.badge.icon" size="16" aria-hidden="true" />
                                    <span>{{ tool.badge.label }}</span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Step: Login -->
        <section class="hero-section" v-if="step === 'login'">
            <div class="hero-content st-shell-1440">
                <header class="cloud-header">
                    <div class="cloud-header-left">
                        <img src="/storage/images/schooltool/schooltool-wordmark.svg" alt="SchoolTool" class="st-header-logo" />
                    </div>
                </header>

                <div class="login-hero-header">
                    <v-icon :size="64" class="login-hero-icon">{{ loginToolIcon }}</v-icon>
                    <h1 class="login-hero-tool">{{ loginToolLabel }}</h1>
                    <p class="login-hero-school">{{ selected_login_school?.long_name }}</p>
                </div>

                <div class="login-center">
                    <div class="login-card">
                        <div class="login-card-logo">
                            <img src="/storage/images/schooltool/schooltool-mark.svg" alt="SchoolTool" class="login-mark" />
                        </div>

                        <!-- Sub-step: Enter Email -->
                        <div class="login-card-body" v-if="loginStep === 'EMAIL'">
                            <h2 class="login-step-title">Anmelden</h2>
                            <p class="login-step-hint">Bitte die E-Mail-Adresse eingeben</p>
                            <v-form ref="loginForm" v-model="loginFormValid" @submit.prevent="doLoginStepEmail()">
                                <v-text-field
                                    autofocus
                                    v-model="loginData.email"
                                    label="E-Mail"
                                    variant="outlined"
                                    density="comfortable"
                                    :rules="[required(), mail()]"
                                />
                            </v-form>
                            <v-btn block color="success" flat size="large" @click="doLoginStepEmail()" class="mb-3">Weiter</v-btn>
                            <v-btn block variant="text" color="warning" @click="backToTools">Zurück</v-btn>
                        </div>

                        <!-- Sub-step: Enter Password -->
                        <div class="login-card-body" v-if="loginStep === 'PASSWORD'">
                            <h2 class="login-step-title">Kennwort eingeben</h2>
                            <v-form ref="loginForm" v-model="loginFormValid" @submit.prevent="doLoginStepPassword()">
                                <v-text-field
                                    autofocus
                                    v-model="loginData.password"
                                    label="Kennwort"
                                    variant="outlined"
                                    density="comfortable"
                                    :append-icon="loginPasswordVisible ? 'mdi-eye' : 'mdi-eye-off'"
                                    :type="loginPasswordVisible ? 'text' : 'password'"
                                    @click:append="loginPasswordVisible = !loginPasswordVisible"
                                    :rules="[required(), minLength(8), maxLength(255)]"
                                />
                                <v-checkbox
                                    v-model="loginData.remember"
                                    label="Angemeldet bleiben"
                                    color="primary"
                                    density="comfortable"
                                    hide-details
                                    class="mt-1"
                                />
                            </v-form>
                            <v-btn block color="success" flat size="large" @click="doLoginStepPassword()" class="mb-3">Anmelden</v-btn>
                            <v-btn block variant="text" color="warning" @click="loginStep = 'EMAIL'">Zurück</v-btn>
                        </div>

                        <!-- Sub-step: Enter 2FA Token -->
                        <div class="login-card-body" v-if="loginStep === 'TOKEN'">
                            <h2 class="login-step-title">Zwei-Faktor-Code</h2>
                            <v-alert closable color="success" type="info" density="compact" text="Bitte prüfen Sie Ihre E-Mails" class="mb-3" />
                            <p class="login-step-hint">Bitte den Code laut E-Mail eingeben</p>
                            <v-form ref="loginForm" v-model="loginFormValid" @submit.prevent="doLoginStep2fa()">
                                <v-otp-input autofocus v-model="loginData.token_2fa" class="mb-3" />
                            </v-form>
                            <v-btn block color="success" flat size="large" @click="doLoginStep2fa()" class="mb-3">Anmelden</v-btn>
                            <v-btn block variant="text" color="warning" @click="loginStep = 'EMAIL'">Zurück</v-btn>
                        </div>

                        <!-- Sub-step: Processing -->
                        <div class="login-card-body" v-if="loginStep === 'PROCESSING'">
                            <div class="login-processing">
                                <v-progress-circular indeterminate color="success" size="48" width="4" class="mb-5" />
                                <h2 class="login-step-title text-center">Login wird durchgeführt</h2>
                                <p class="login-step-hint text-center mb-0">Bitte einen Moment Geduld …</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Floating particles for ambiance (temporarily hidden) -->
        <div class="particles" v-if="false">
            <div class="particle" v-for="n in 20" :key="n" :style="getParticleStyle(n)"></div>
        </div>
    </div>
</template>

<script>
import { nextTick, ref, watch } from 'vue'
import { resolveSelectedSchoolLogoSrc } from '@/helpers/adminSchoolLogo'
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { homepageStartupRequestTimeout, useHomepageStore } from '@/stores/homepage/HomepageStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import SchoolEntryHeader from './SchoolEntryHeader.vue'

const schoolLogosForLightBackgrounds = {
    BGZ: {
        // Positive school mark from the BG Zaunergasse homepage.
        current: '/storage/images/logos/logo_2.png',
        positive: '/images/school-logo-bgz-positive.jpg',
    },
    CDGym: {
        // Original image embedded in the school's Info-Folder_SRG_CD-Gym_US.pdf.
        current: '/storage/images/logos/logo_1.png',
        positive: '/images/school-logo-cdgym-positive.png',
    },
}

export default {
    setup() {
        const discoveryClock = ref(null)
        const previewDiscoveryMotion = import.meta.env.DEV && window.location.hostname === 'localhost'

        watch(discoveryClock, (point, _, onCleanup) => {
            if (!point) return

            const link = point.closest('a')
            const motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)')
            const canAnimate = () => previewDiscoveryMotion || !motionPreference.matches
            let nextSecondTimeout
            let displayedSecond = 0
            let hoveringClock = false

            function setSecond(second) {
                displayedSecond = second
                point.style.offsetDistance = `${second / 60 * 100 - 25}%`
                point.classList.toggle('school-entry-info-link-point--held', hoveringClock && second === 0)
            }

            function syncClock() {
                window.clearTimeout(nextSecondTimeout)
                hoveringClock = false
                if (document.hidden) return

                const now = Date.now()
                setSecond(new Date(now).getSeconds())

                if (canAnimate()) {
                    nextSecondTimeout = window.setTimeout(syncClock, 1000 - now % 1000)
                }
            }

            function moveToTwelve(event) {
                if (event.pointerType !== 'mouse' || !canAnimate() || document.hidden) return

                window.clearTimeout(nextSecondTimeout)
                hoveringClock = true
                if (displayedSecond === 0) {
                    setSecond(0)
                    return
                }

                const clockwise = displayedSecond >= 30
                const direction = clockwise ? 1 : -1
                const steps = clockwise ? 60 - displayedSecond : displayedSecond
                const interval = Math.min(100, 900 / steps)

                function advance() {
                    setSecond((displayedSecond + direction + 60) % 60)
                    if (displayedSecond !== 0) {
                        nextSecondTimeout = window.setTimeout(advance, interval)
                    }
                }

                nextSecondTimeout = window.setTimeout(advance, interval)
            }

            function leaveClock(event) {
                if (event.pointerType === 'mouse' && hoveringClock) syncClock()
            }

            syncClock()
            link?.addEventListener('pointerenter', moveToTwelve)
            link?.addEventListener('pointerleave', leaveClock)
            link?.addEventListener('pointercancel', leaveClock)
            document.addEventListener('visibilitychange', syncClock)
            window.addEventListener('pageshow', syncClock)
            motionPreference.addEventListener('change', syncClock)
            onCleanup(() => {
                window.clearTimeout(nextSecondTimeout)
                link?.removeEventListener('pointerenter', moveToTwelve)
                link?.removeEventListener('pointerleave', leaveClock)
                link?.removeEventListener('pointercancel', leaveClock)
                document.removeEventListener('visibilitychange', syncClock)
                window.removeEventListener('pageshow', syncClock)
                motionPreference.removeEventListener('change', syncClock)
            })
        }, { flush: 'post' })

        return { ...useValidationRulesSetup(), discoveryClock, previewDiscoveryMotion }
    },

    components: { SchoolEntryHeader },

    async beforeMount() {
        this.homepageStore = useHomepageStore()
        await this.initializeHomepage()
    },

    mounted() {},

    unmounted() {},

    data() {
        return {
            homepageStore: null,
            step: null,
            homepageDesignEnabled: false,
            isInitializing: false,
            schoolListLoadFailed: false,
            isSelectingSchool: false,
            login_schools: [],
            selected_login_school: null,
            selected_login_school_id: null,
            selected_tool: null,
            schoolLogos: {},
            loginStep: 'EMAIL',
            loginFormValid: false,
            loginPasswordVisible: false,
            loginData: {
                email: '',
                password: '',
                remember: true,
                token_2fa: '',
            },
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
        teachingModuleStatus() {
            return this.moduleStatuses.teaching || 'inactive'
        },
        teachingStatus() {
            return this.toolStatuses['Lehrertool'] || 'missing'
        },
        studentsTimetablesModuleStatus() {
            return this.moduleStatuses.students_timetables || 'inactive'
        },
        studentsTimetablesStatus() {
            return this.toolStatuses.StudentsTimetables || 'missing'
        },
        restaurantModuleStatus() {
            return this.moduleStatuses.restaurant || 'inactive'
        },
        hasRestaurantHomepageAccess() {
            return this.moduleAllowsAccess(this.restaurantModuleStatus)
        },
        schoolLicenceNames() {
            return (this.config?.schoolLicences || []).map((l) => l.name)
        },
        loginToolLabel() {
            const labels = {
                Anmeldetool: 'Anmeldetool',
                Lehrertool: 'Unterricht',
                StudentsTimetables: 'SEPP',
                Restaurant: 'Restaurant',
            }
            return labels[this.selected_tool] || this.selected_tool || ''
        },
        loginToolIcon() {
            const icons = {
                Anmeldetool: 'mdi-calendar-check',
                Lehrertool: 'mdi-rocket-launch-outline',
                StudentsTimetables: 'mdi-calendar-clock-outline',
                Restaurant: 'mdi-food',
            }
            return icons[this.selected_tool] || 'mdi-tools'
        },
        canShowRegister() {
            return this.isModuleVisible(this.registerModuleStatus) && this.schoolLicenceNames.includes('Anmeldetool')
        },
        canShowTeaching() {
            return this.isModuleVisible(this.teachingModuleStatus) && this.schoolLicenceNames.includes('Lehrertool')
        },
        canShowStudentsTimetables() {
            return this.isModuleVisible(this.studentsTimetablesModuleStatus) && this.schoolLicenceNames.includes('StudentsTimetables')
        },
        canShowRestaurant() {
            return this.isModuleVisible(this.restaurantModuleStatus) && this.schoolLicenceNames.includes('Restaurant')
        },
        isRegisterDisabled() {
            return this.registerStatus !== 'active' || !this.moduleAllowsAccess(this.registerModuleStatus)
        },
        isTeachingDisabled() {
            return this.teachingStatus !== 'active' || !this.moduleAllowsAccess(this.teachingModuleStatus)
        },
        isStudentsTimetablesDisabled() {
            return this.studentsTimetablesStatus !== 'active' || !this.moduleAllowsAccess(this.studentsTimetablesModuleStatus)
        },
        isRestaurantDisabled() {
            return !this.hasRestaurantHomepageAccess
        },
        registerBadge() {
            return this.buildBadge(this.registerStatus, this.registerModuleStatus)
        },
        teachingBadge() {
            return this.buildBadge(this.teachingStatus, this.teachingModuleStatus)
        },
        studentsTimetablesBadge() {
            return this.buildBadge(this.studentsTimetablesStatus, this.studentsTimetablesModuleStatus)
        },
        restaurantBadge() {
            return this.buildBadge('active', this.restaurantModuleStatus)
        },
        schoolToolCards() {
            return [
                {
                    key: 'Anmeldetool',
                    legacyClass: 'card-register',
                    title: 'Anmeldetool',
                    description: 'Einfache Anmeldung zu Schulveranstaltungen, Elternabenden und Events.',
                    icon: 'mdi-calendar-check',
                    visible: this.canShowRegister,
                    disabled: this.isRegisterDisabled,
                    badge: this.registerBadge,
                },
                {
                    key: 'Lehrertool',
                    legacyClass: 'card-lernportal',
                    title: 'Unterricht',
                    description: 'Einstieg in den Login-Bereich für Schüler.',
                    icon: 'mdi-rocket-launch-outline',
                    visible: this.canShowTeaching,
                    disabled: this.isTeachingDisabled,
                    badge: this.teachingBadge,
                },
                {
                    key: 'StudentsTimetables',
                    legacyClass: 'card-students-timetables',
                    title: 'SEPP',
                    description: 'Stundenplanerstellungs- und -planungsprogramm',
                    icon: 'mdi-calendar-clock-outline',
                    visible: this.canShowStudentsTimetables,
                    disabled: this.isStudentsTimetablesDisabled,
                    badge: this.studentsTimetablesBadge,
                },
                {
                    key: 'Restaurant',
                    legacyClass: 'card-lunch',
                    title: 'Restaurant',
                    description: 'Speisepläne, Bestellungen und Abholinfos an einem Ort. Direkt in SchoolTool.',
                    icon: 'mdi-food',
                    visible: this.canShowRestaurant,
                    disabled: this.isRestaurantDisabled,
                    badge: this.restaurantBadge,
                },
            ].filter((tool) => tool.visible)
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
        async initializeHomepage() {
            this.isInitializing = true

            try {
                const { school, app, tool } = this.$route.query
                this.school_name = school
                this.app_name = app
                if (!this.school) {
                    await this.homepageStore.loadConfig(school, app, { timeout: homepageStartupRequestTimeout })
                }
                this.selected_school = null
                this.selected_school_id = null

                const result = await this.homepageStore.loadLoginSchools()
                this.schoolListLoadFailed = !result
                this.login_schools = result?.schools || []
                this.homepageDesignEnabled = result?.homepage_design_enabled === true
                this.step = 'selectSchool'

                const match = school && this.login_schools.find((entry) => entry.short_name === school)
                if (match && await this.selectLoginSchool(match) && tool) {
                    this.openToolForSchool(tool)
                }
            } finally {
                this.isInitializing = false
            }
        },
        schoolLogoSrc(school) {
            const current = resolveSelectedSchoolLogoSrc(school.logo)
            const variant = schoolLogosForLightBackgrounds[school.short_name]

            return this.homepageDesignEnabled && variant?.current === current ? variant.positive : current
        },
        async selectLoginSchool(school) {
            if (this.isSelectingSchool || this.$route.path !== '/') return false

            this.isSelectingSchool = true
            try {
                const result = await this.homepageStore.loadConfig(school.short_name, null, { timeout: homepageStartupRequestTimeout })
                if (result === false || this.$route.path !== '/') return false

                this.selected_login_school = school
                if (this.$route.query.school !== school.short_name) {
                    this.$router.replace({ query: { school: school.short_name } })
                }
                this.step = 'schoolSelected'
                return true
            } finally {
                this.isSelectingSchool = false
            }
        },

        selectLoginSchoolById(id) {
            const school = this.login_schools.find((s) => s.id === id)
            if (school) this.selectLoginSchool(school)
        },

        backToSchoolSelection() {
            this.selected_login_school = null
            this.selected_login_school_id = null
            this.$router.replace({ query: {} })
            this.step = 'selectSchool'
        },

        openToolForSchool(tool) {
            const school = this.selected_login_school
            if (!school) return

            const status = this.toolStatuses?.[tool] || 'missing'
            const moduleStatus = {
                Anmeldetool: this.registerModuleStatus,
                Lehrertool: this.teachingModuleStatus,
                StudentsTimetables: this.studentsTimetablesModuleStatus,
                Restaurant: this.restaurantModuleStatus,
            }[tool] || 'inactive'

            if (tool !== 'Restaurant' && (!this.moduleAllowsAccess(moduleStatus) || status !== 'active')) {
                this.notifyToolUnavailable(tool, status, moduleStatus)
                return
            }

            if (tool === 'Restaurant' && !this.hasRestaurantHomepageAccess) {
                this.notifyToolUnavailable('Restaurant', 'active', this.restaurantModuleStatus)
                return
            }

            const publicToolRoute = this.publicToolRoute(tool, school)
            if (publicToolRoute) {
                this.$router.push(publicToolRoute)

                return
            }
        },

        navigateToTool() {
            const school = this.selected_login_school
            const toolRoutes = {
                Anmeldetool: '/homepage/register',
                Lehrertool: '/homepage/student',
                StudentsTimetables: '/homepage/students-timetables',
                Restaurant: '/homepage/restaurant',
            }
            const path = toolRoutes[this.selected_tool]
            if (path && school) {
                this.$router.push(path + '?school=' + school.short_name)
            }
        },

        publicToolRoute(tool, school) {
            const toolRoutes = {
                Anmeldetool: '/homepage/register',
                Lehrertool: '/homepage/student',
                StudentsTimetables: '/homepage/students-timetables',
                Restaurant: '/homepage/restaurant',
            }
            const path = toolRoutes[tool]
            if (!path || !school?.short_name) return null

            return {
                path,
                query: {
                    school: school.short_name,
                },
            }
        },

        async doLoginStepEmail() {
            this.loginFormValid = false
            await this.$refs.loginForm.validate()
            if (!this.loginFormValid) return

            const result = await this.homepageStore.homepageLoginStepEmail(
                this.loginData.email,
                this.selected_login_school.id
            )
            if (!result) return
            this.loginStep = result.step === 'LOGIN_ENTER_PASSWORD' ? 'PASSWORD' : 'EMAIL'
        },

        async doLoginStepPassword() {
            this.loginFormValid = false
            await this.$refs.loginForm.validate()
            if (!this.loginFormValid) return

            this.loginStep = 'PROCESSING'

            const result = await this.homepageStore.homepageLoginStepPassword(
                this.loginData.email,
                this.selected_login_school.id,
                this.loginData.password,
                this.loginData.remember
            )
            if (!result) {
                this.loginStep = 'PASSWORD'
                return
            }
            if (result.step === 'LOGIN_ENTER_TOKEN') {
                this.loginStep = 'TOKEN'
                return
            }
            this.navigateToTool()
        },

        async doLoginStep2fa() {
            if (!this.loginData.token_2fa || this.loginData.token_2fa.length !== 6) return
            this.loginStep = 'PROCESSING'

            const result = await this.homepageStore.homepageLoginStep2fa(
                this.loginData.email,
                this.selected_login_school.id,
                this.loginData.token_2fa,
                this.loginData.remember
            )
            if (!result) {
                this.loginStep = 'TOKEN'
                return
            }
            this.navigateToTool()
        },

        backToTools() {
            this.selected_tool = null
            this.loginData = { email: '', password: '', remember: true, token_2fa: '' }
            const { tool, ...query } = this.$route.query
            this.$router.replace({ query })
            this.step = 'schoolSelected'
        },

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
            if (!this.canShowRestaurant || !this.hasRestaurantHomepageAccess) {
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
                return { icon: 'mdi-progress-clock', label: 'Demnächst verfügbar' }
            }

            return null
        },
        notifyToolUnavailable(tool, status, moduleStatus = null) {
            const notification = useNotificationStore()
            const toolLabel = {
                Anmeldetool: 'Anmeldetool',
                Lehrertool: 'Unterricht',
                StudentsTimetables: 'SEPP',
                Restaurant: 'Restaurant',
            }[tool] || tool

            const message =
                moduleStatus === 'comming_soon'
                    ? `${toolLabel}: Demnächst verfügbar.`
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
                Lehrertool: this.teachingModuleStatus,
                StudentsTimetables: this.studentsTimetablesModuleStatus,
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
            let path = '/homepage/'
            switch (licence.name) {
                case 'Anmeldetool':
                    path += 'register'
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

        isLogoBright(img, isSchoolBanner) {
            const canvas = document.createElement('canvas')
            const ctx = canvas.getContext('2d')
            canvas.width = img.naturalWidth
            canvas.height = img.naturalHeight
            ctx.drawImage(img, 0, 0)
            const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data
            let opaquePixels = 0
            let brightPixels = 0
            let veryBrightPixels = 0
            let transparentPixels = 0
            for (let i = 0; i < data.length; i += 16) {
                const r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3]
                if (a < 30) {
                    transparentPixels++

                    continue
                }

                opaquePixels++
                const luminance = 0.299 * r + 0.587 * g + 0.114 * b
                if (luminance > 180) brightPixels++
                if (luminance > 225) veryBrightPixels++
            }

            if (!opaquePixels) {
                return true
            }

            const veryBrightRatio = veryBrightPixels / opaquePixels
            const transparentRatio = transparentPixels / (transparentPixels + opaquePixels)

            if (this.homepageDesignEnabled) return transparentRatio > 0.18 && veryBrightRatio > 0.1

            return brightPixels / opaquePixels > 0.45
                || (!isSchoolBanner && (
                    (transparentRatio > 0.2 && veryBrightRatio > 0.04)
                    || (transparentRatio > 0.35 && brightPixels / opaquePixels > 0.14)
                ))
        },

        analyzeSchoolLogo(event, schoolId, isSchoolBanner = false) {
            const logo = event.target
            const wide = logo.naturalWidth >= 1.6 * logo.naturalHeight
            let dark = true
            try {
                dark = this.isLogoBright(logo, isSchoolBanner)
            } catch {
                // Keep a dark backdrop when the image cannot be sampled.
            }

            this.schoolLogos[schoolId] = { wide, dark }
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

.intro-page--starting {
    background: #fafafa;
}

.school-entry-load-error {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 16px;
    color: var(--entry-ink);
}

.school-entry-load-error .school-entry-back {
    border: 1px solid var(--entry-border);
    background: #fff;
}

.school-entry-load-error .school-entry-back:disabled {
    opacity: 0.6;
    cursor: wait;
}
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

.st-header-logo {
    height: 28px;
    width: auto;
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

.intro-page:has(.school-entry) {
    min-height: 0;
}

.school-entry-hero--with-info {
    grid-template-columns: minmax(0, 1fr) clamp(9rem, 24vw, 20rem);
    align-items: center;
    gap: clamp(24px, 4vw, 56px);
    padding-block: clamp(22px, 3.7vw, 44px);
}

.school-entry-info-link {
    position: relative;
    isolation: isolate;
    display: grid;
    width: clamp(9rem, 13vw, 11.5rem);
    aspect-ratio: 1;
    align-content: center;
    justify-items: center;
    justify-self: center;
    border: 1px solid rgb(16 38 58 / 28%);
    border-radius: 50%;
    background: rgb(255 250 240 / 50%);
    color: var(--entry-ink);
    font-size: clamp(0.875rem, 1.25vw, 1.03125rem);
    font-weight: 600;
    line-height: 1.28;
    text-align: center;
    text-decoration: none;
}

.school-entry-info-link::before {
    position: absolute;
    inset: -7px;
    border: 1px solid rgb(16 38 58 / 38%);
    border-radius: inherit;
    content: '';
    pointer-events: none;
}

.school-entry-info-link-smoke {
    position: absolute;
    inset: 0;
    overflow: hidden;
    border-radius: inherit;
    opacity: 0;
    transition: opacity 700ms ease-out;
    pointer-events: none;
}

.school-entry-info-link-smoke::before,
.school-entry-info-link-smoke::after {
    position: absolute;
    width: 39%;
    height: 58%;
    border-radius: 50%;
    opacity: 0;
    filter: blur(8px);
    content: '';
    animation: schooltool-smoke-rise 3s ease-in-out 250ms infinite both paused;
}

.school-entry-info-link-smoke::before {
    --info-smoke-drift: -14%;
    --info-smoke-tilt: -20deg;
    right: 41%;
    bottom: 5%;
    background: radial-gradient(ellipse at 64% 58%, rgb(248 128 62 / 85%), transparent 71%);
}

.school-entry-info-link-smoke::after {
    --info-smoke-drift: 15%;
    --info-smoke-tilt: 18deg;
    right: 18%;
    bottom: 1%;
    background: radial-gradient(ellipse at 37% 52%, rgb(220 99 149 / 74%), transparent 72%);
    animation-delay: 1550ms;
}

.school-entry-info-link-dial {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.school-entry-info-link-dial svg {
    display: block;
    width: 100%;
    height: 100%;
}

.school-entry-info-link-point {
    position: absolute;
    top: 0;
    left: 0;
    width: 6px;
    height: 6px;
    offset-path: circle(33% at 50% 50%);
    offset-distance: -25%;
    offset-rotate: 0deg;
}

.school-entry-info-link-point::before {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: var(--entry-orange);
    content: '';
}

.school-entry-info-link-point--held::before {
    transform: scale(3);
    transition: transform 220ms ease-out;
}

.school-entry-info-link-label {
    z-index: 1;
    width: min-content;
}

.school-entry-info-link:focus-visible {
    outline: 3px solid var(--entry-ink);
    outline-offset: 7px;
}

@media (max-width: 700px) {
    .school-entry-hero--with-info {
        grid-template-columns: minmax(0, 1fr);
        gap: 24px;
    }

    .school-entry-info-link {
        justify-self: start;
        margin-inline-start: 7px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .school-entry-info-link:not(.school-entry-info-link--local-motion) .school-entry-info-link-smoke {
        transition: none;
    }

    .school-entry-info-link:not(.school-entry-info-link--local-motion) .school-entry-info-link-smoke::before,
    .school-entry-info-link:not(.school-entry-info-link--local-motion) .school-entry-info-link-smoke::after {
        animation: none;
        opacity: 0.32;
    }
}

@media (hover: hover) {
    .school-entry-info-link:hover .school-entry-info-link-smoke {
        opacity: 1;
    }

    .school-entry-info-link:hover .school-entry-info-link-smoke::before,
    .school-entry-info-link:hover .school-entry-info-link-smoke::after {
        animation-play-state: running;
    }
}

@keyframes schooltool-smoke-rise {
    from {
        opacity: 0;
        transform: translateY(13%) rotate(var(--info-smoke-tilt)) scale(0.78);
    }

    22% {
        opacity: 0.16;
    }

    49% {
        opacity: 0.76;
    }

    76% {
        opacity: 0.36;
    }

    to {
        opacity: 0;
        transform: translate(var(--info-smoke-drift), -48%) rotate(var(--info-smoke-tilt)) scale(1.16);
    }
}

.homepage-school-card {
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-rows: 88px 1fr;
    align-items: start;
    gap: 14px;
    padding: 16px 18px;
}

.homepage-school-card .school-entry-logo {
    grid-column: 1 / -1;
    justify-self: center;
    width: min(100%, 200px);
    height: 100%;
    padding: 0;
    background: transparent;
}

.homepage-school-card .school-entry-logo--dark {
    width: 76px;
    height: 76px;
    align-self: center;
    padding: 5px 8px;
    background: #183044;
}

.homepage-school-card .school-entry-logo--wide.school-entry-logo--dark {
    width: min(100%, 188px);
}

.school-entry-logo--wide.school-entry-logo--dark {
    background: #142131;
}

.homepage-school-card .school-entry-logo img {
    max-width: 68px;
    max-height: 68px;
}

.homepage-school-card .school-entry-logo--wide img {
    max-width: 200px;
    max-height: 88px;
}

.homepage-school-card .school-entry-school strong {
    font-size: 1.05rem;
}

.school-tools .school-entry-back {
    margin: -8px 0 12px -10px;
}

.school-tools-hero {
    align-items: center;
    gap: clamp(14px, 3vw, 28px);
}

.school-tools-hero--with-logo {
    grid-template-columns: auto minmax(0, 1fr);
}

.school-tools-heading p {
    max-width: 58ch;
}

.school-tools-logo {
    width: clamp(62px, 8vw, 108px);
    height: clamp(62px, 8vw, 108px);
    padding: 10px;
    border-radius: 12px;
}

.school-tools-logo:not(.school-entry-logo--dark) {
    background: #fff;
}

.school-tools-logo.school-entry-logo--wide {
    width: clamp(176px, 19vw, 216px);
    height: auto;
    padding: 8px;
    aspect-ratio: 2 / 1;
}

.school-tools-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    padding: 0;
    list-style: none;
}

.school-tool-card {
    gap: clamp(10px, 2vw, 18px);
}

.school-tool-card--teaching:not([aria-disabled='true']) {
    border-color: #e4ab79;
}

.school-tool-card--teaching .school-tool-icon {
    background: #fff1df;
    color: var(--entry-accent);
}

.school-tool-card[aria-disabled='true'] {
    background: #f3f5f7;
    cursor: not-allowed;
}

.school-tool-card[aria-disabled='true'] .school-entry-arrow {
    background: #e9edf1;
    color: var(--entry-muted);
}

.school-tool-copy {
    display: grid;
    gap: 4px;
    color: var(--entry-muted);
    font-size: 0.875rem;
    overflow-wrap: anywhere;
}

.school-tool-copy > strong {
    color: var(--entry-ink);
    font-size: 1.1rem;
}

.school-tool-status {
    display: inline-flex;
    justify-self: start;
    align-items: center;
    gap: 5px;
    padding: 3px 7px;
    border-radius: 6px;
    background: #fff1df;
    color: #803908;
    font-weight: 600;
}

@media (max-width: 639px) {
    .homepage-school-card {
        padding: 12px 14px;
    }

    .school-tools-list {
        grid-template-columns: minmax(0, 1fr);
        gap: 8px;
    }

    .school-tools-hero--with-logo {
        grid-template-columns: minmax(0, 1fr);
    }
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

.legacy-homepage {
    --entry-ink: #10263a;
    --entry-border: #dce3e8;
}

.legacy-homepage .tool-card {
    text-align: start;
}

.legacy-homepage .tool-card:focus-visible {
    outline: 3px solid var(--entry-ink);
    outline-offset: 3px;
}

/* School Selection */
.school-select-icon {
    color: #f39200 !important;
}

.school-select-icon--no-logo {
    background: linear-gradient(135deg, rgba(243, 146, 0, 0.12), rgba(243, 146, 0, 0.04)) !important;
}

.school-select-icon--has-logo {
    border-radius: 14px;
    padding: 8px;
}

.school-select-icon--dark-logo {
    background: #2d4a5e !important;
}

.school-select-logo-img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.school-card-title {
    min-height: 3.4em;
    display: flex;
    align-items: flex-start;
}

.school-card-short {
    margin-bottom: 8px !important;
}

.school-search-wrap {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    max-width: 560px;
}

.school-search-field {
    flex: 1;
}

/* Tools Section */
.tools-container {
    animation: fadeInUp 0.8s ease-out 0.2s both;
    margin-top: 34px;
    width: 100%;
}

.tools-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 28px;
}

.school-logo-banner {
    display: flex;
    justify-content: flex-start;
    margin-top: 12px;
    animation: fadeInUp 0.6s ease-out;
}

.school-logo-box {
    width: 120px;
    height: 120px;
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.school-logo-box--dark {
    background: #2d4a5e;
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.15);
}

.school-logo-img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.back-to-schools {
    color: rgba(255, 255, 255, 0.85) !important;
    font-weight: 500;
    letter-spacing: 0.01em;
    text-transform: none;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1) !important;
    backdrop-filter: blur(4px);
    transition: all 0.2s ease;
}

.back-to-schools:hover {
    background: rgba(255, 255, 255, 0.2) !important;
    color: #fff !important;
}

.section-title {
    text-align: left;
    font-size: 1.5rem;
    font-weight: 600;
    color: #37474f;
    margin-bottom: 32px;
}

.tools-header .section-title {
    color: #10263a;
    font-size: 1.35rem;
    font-weight: 700;
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

@media (max-width: 768px) {
    .legacy-homepage .tools-grid {
        gap: 16px;
    }

    .legacy-homepage .tool-card {
        padding: 20px;
    }

    .legacy-homepage .card-icon {
        width: 52px;
        height: 52px;
    }

    .legacy-homepage .card-title,
    .legacy-homepage .section-title {
        font-size: 1.25rem;
    }

    .legacy-homepage .section-title {
        margin-bottom: 24px;
    }

    .legacy-homepage .tools-header {
        flex-wrap: wrap;
    }
}

@media (max-width: 480px) {
    .legacy-homepage .card-description {
        font-size: 0.9rem;
    }

    .legacy-homepage .school-search-wrap {
        flex-wrap: wrap;
    }
}

@media (prefers-reduced-motion: reduce) {
    .legacy-homepage .tool-card,
    .legacy-homepage .tool-card:hover,
    .legacy-homepage .tool-card:hover .card-icon {
        transform: none;
        transition: none;
    }
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

    .st-header-logo {
        height: 22px;
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
}

/* Login Hero Header */
.login-hero-header {
    text-align: center;
    margin-top: clamp(10px, 3vw, 24px);
    margin-bottom: clamp(40px, 6vw, 72px);
    animation: fadeInUp 0.6s ease-out;
}

.login-hero-icon {
    color: rgba(255, 255, 255, 0.85);
    margin-bottom: 16px;
    filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.15));
}

.login-hero-tool {
    font-size: clamp(2.2rem, 5vw, 3.8rem);
    font-weight: 800;
    color: #fff;
    margin: 0;
    line-height: 1.05;
    letter-spacing: -0.01em;
    text-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.login-hero-school {
    font-size: clamp(1rem, 2vw, 1.4rem);
    font-weight: 500;
    color: rgba(16, 38, 58, 0.85);
    margin: 12px 0 0;
    line-height: 1.3;
}

/* Login Card */
.login-center {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: -40px;
}

.login-card {
    width: 100%;
    max-width: 460px;
    background: white;
    border-radius: 24px;
    box-shadow:
        0 24px 60px rgba(68, 33, 4, 0.2),
        0 4px 16px rgba(68, 33, 4, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.5);
    overflow: hidden;
    animation: scaleIn 0.4s ease-out;
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.96); }
    to { opacity: 1; transform: scale(1); }
}

.login-card-logo {
    padding: 24px 28px 0;
    display: flex;
    align-items: center;
}

.login-mark {
    width: 48px;
    height: 48px;
}

.login-card-body {
    padding: 24px 28px 28px;
}

.login-step-title {
    font-size: 1.45rem;
    font-weight: 700;
    color: #10263a;
    margin: 0 0 6px 0;
    line-height: 1.2;
}

.login-step-school {
    font-size: 0.92rem;
    font-weight: 600;
    color: #10263a;
    margin: 0 0 16px 0;
    line-height: 1.4;
}

.login-step-hint {
    font-size: 0.92rem;
    color: rgba(16, 38, 58, 0.65);
    margin: 0 0 16px 0;
    line-height: 1.4;
}

.login-processing {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 32px 0 16px;
}

/* Accessibility - Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    .cf-sun-glow,
    .cf-wave,
    .cf-haze,
    .particle,
    .logo-wrapper {
        animation: none;
    }
}
</style>
