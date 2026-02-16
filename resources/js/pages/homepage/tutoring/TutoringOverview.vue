<template>
    <div class="tutoring-page">
        <!-- Animated Background -->
        <div class="animated-bg">
            <div class="gradient-orb orb-1"></div>
            <div class="gradient-orb orb-2"></div>
            <div class="gradient-orb orb-3"></div>
        </div>

        <!-- Floating particles -->
        <div class="particles">
            <div class="particle" v-for="n in 15" :key="n" :style="getParticleStyle(n)"></div>
        </div>

        <!-- Error State -->
        <div class="error-container" v-if="is_init && !school_name && schools.length == 0">
            <v-card class="error-card" elevation="12">
                <v-alert type="error" title="Das Nachhilfetool kann derzeit nicht verwendet werden." />
            </v-card>
        </div>

        <!-- Main Content -->
        <div class="main-container" v-if="!error">
            <!-- Header -->
            <div class="header-section" v-if="is_loaded">
                <div class="brand-container">
                    <div class="logo-wrapper">
                        <v-icon size="40" color="white">mdi-account-group</v-icon>
                    </div>
                    <h1 class="brand-title">
                        <span class="brand-nach">Nach</span>
                        <span class="brand-hilfe">hilfe</span>
                        <span class="brand-tool">Tool</span>
                    </h1>
                    <p class="brand-tagline">Schüler helfen Schülern</p>
                </div>

                <!-- School Info -->
                <div class="school-info" v-if="offer_config.school">
                    <div class="school-badge">
                        <div class="school-logo" v-if="offer_config.school.logo">
                            <img :src="'/storage/images/' + offer_config.school.logo" alt="Logo" />
                        </div>
                        <v-icon v-else size="32" color="primary">mdi-school</v-icon>
                        <span class="school-name">{{ offer_config.school.long_name }}</span>
                    </div>
                </div>
            </div>

            <div class="overview-nav" v-if="is_loaded && !is_login && action == ''">
                <v-btn v-if="offer_config?.auth?.is_auth" class="overview-nav-btn" variant="text" size="small" prepend-icon="mdi-arrow-left" @click="goToMainMenu">
                    Zurück zum Hauptmenü
                </v-btn>
            </div>

            <!-- Menu Cards -->
            <div class="menu-section" v-if="is_loaded && !is_login">
                <!-- Main Menu -->
                <div class="menu-grid" v-if="action == ''">
                    <!-- Authenticated User Cards -->
                    <div class="menu-card card-user" data-testid="tutoring-overview-go-personal-area" @click="moveToTutoring" v-if="offer_config.auth.is_auth">
                        <div class="card-glow"></div>
                        <div class="card-content">
                            <div class="card-icon">
                                <v-icon size="36">mdi-account-circle</v-icon>
                            </div>
                            <h3 class="card-title">{{ offer_config?.auth?.user?.first_name }} {{ offer_config?.auth?.user?.last_name }}</h3>
                            <p class="card-description">Dein persönlicher Bereich. Angebote erstellen und verwalten.</p>
                            <div class="card-action">
                                <span>Mein Bereich</span>
                                <v-icon size="18">mdi-arrow-right</v-icon>
                            </div>
                        </div>
                    </div>

                    <div class="menu-card card-requests" data-testid="tutoring-overview-received-requests" @click="action = 'received_requests'" v-if="offer_config.auth.is_auth">
                        <div class="card-glow"></div>
                        <div class="card-content">
                            <div class="card-icon">
                                <v-icon size="36">mdi-inbox-arrow-down</v-icon>
                            </div>
                            <h3 class="card-title">Erhaltene Anfragen</h3>
                            <p class="card-description">Anfragen, die Du von anderen Schülern erhalten hast.</p>
                            <div class="card-action">
                                <span>Anzeigen</span>
                                <v-icon size="18">mdi-arrow-right</v-icon>
                            </div>
                        </div>
                    </div>

                    <div class="menu-card card-my-requests" data-testid="tutoring-overview-my-requests" @click="action = 'my_requests'" v-if="offer_config.auth.is_auth">
                        <div class="card-glow"></div>
                        <div class="card-content">
                            <div class="card-icon">
                                <v-icon size="36">mdi-send</v-icon>
                            </div>
                            <h3 class="card-title">Deine Anfragen</h3>
                            <p class="card-description">Anfragen, die Du an andere Schüler gestellt hast.</p>
                            <div class="card-action">
                                <span>Anzeigen</span>
                                <v-icon size="18">mdi-arrow-right</v-icon>
                            </div>
                        </div>
                    </div>

                    <div class="menu-card card-logout" data-testid="tutoring-overview-logout" @click="logout" v-if="offer_config.auth.is_auth">
                        <div class="card-glow"></div>
                        <div class="card-content">
                            <div class="card-icon">
                                <v-icon size="36">mdi-logout</v-icon>
                            </div>
                            <h3 class="card-title">Abmelden</h3>
                            <p class="card-description">Vom System ausloggen.</p>
                            <div class="card-action">
                                <span>Abmelden</span>
                                <v-icon size="18">mdi-arrow-right</v-icon>
                            </div>
                        </div>
                    </div>

                    <!-- Login Card for non-authenticated -->
                    <div class="menu-card card-login" data-testid="tutoring-overview-start-login" @click="startLogin" v-if="!offer_config.auth.is_auth">
                        <div class="card-glow"></div>
                        <div class="card-content">
                            <v-btn icon variant="flat" class="login-back-icon-btn" aria-label="Zurück zum Hauptmenü" @click.stop="goToMainMenu">
                                <v-icon size="30">mdi-arrow-left</v-icon>
                            </v-btn>
                            <h3 class="card-title">Anmelden</h3>
                            <p class="card-description">Melde Dich kostenlos an, um mehr Funktionen nutzen zu können.</p>
                            <div class="card-action">
                                <span>Anmelden</span>
                                <v-icon size="18">mdi-arrow-right</v-icon>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Requests Menu -->
                <div class="sub-menu" v-if="action == 'my_requests'">
                    <div class="menu-card card-back" data-testid="tutoring-overview-my-requests-back" @click="action = ''">
                        <div class="card-glow"></div>
                        <div class="card-content">
                            <div class="card-icon">
                                <v-icon size="36">mdi-arrow-left</v-icon>
                            </div>
                            <h3 class="card-title">Zurück</h3>
                            <p class="card-description">Zurück zur Übersicht.</p>
                        </div>
                    </div>
                    <div class="menu-card card-archive" data-testid="tutoring-overview-my-requests-toggle-archive" @click="toArchive(!show_archived)">
                        <div class="card-glow"></div>
                        <div class="card-content">
                            <div class="card-icon">
                                <v-icon size="36">mdi-archive</v-icon>
                            </div>
                            <h3 class="card-title">{{ show_archived ? 'Aktive Anfragen' : 'Archiv' }}</h3>
                            <p class="card-description">{{ show_archived ? 'Zeige aktive Anfragen an.' : 'Zeige archivierte Anfragen an.' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Received Requests Menu -->
                <div class="sub-menu" v-if="action == 'received_requests'">
                    <div class="menu-card card-back" data-testid="tutoring-overview-received-requests-back" @click="action = ''">
                        <div class="card-glow"></div>
                        <div class="card-content">
                            <div class="card-icon">
                                <v-icon size="36">mdi-arrow-left</v-icon>
                            </div>
                            <h3 class="card-title">Zurück</h3>
                            <p class="card-description">Zurück zur Übersicht.</p>
                        </div>
                    </div>
                    <div class="menu-card card-archive" data-testid="tutoring-overview-received-requests-toggle-archive" @click="toToUserArchive(!show_to_user_archived)">
                        <div class="card-glow"></div>
                        <div class="card-content">
                            <div class="card-icon">
                                <v-icon size="36">mdi-archive</v-icon>
                            </div>
                            <h3 class="card-title">{{ show_to_user_archived ? 'Aktive Anfragen' : 'Archiv' }}</h3>
                            <p class="card-description">{{ show_to_user_archived ? 'Zeige aktive Anfragen an.' : 'Zeige archivierte Anfragen an.' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="login-nav" v-if="is_loaded && is_login">
                <v-btn class="login-nav-btn" variant="text" prepend-icon="mdi-arrow-left" @click="goToMainMenu">
                    Zurück zum Hauptmenü
                </v-btn>
            </div>

            <!-- School & User Selection -->
            <SchoolAndUser
                :is_init="is_init"
                :is_login="is_login"
                :school_name="school_name"
                :school="school"
                @cancel-login="is_login = false"
                @login-success="afterLogin"
                @logout="logout" />

            <!-- Search Panel -->
            <div class="search-section" v-if="offer_config && offer_config.auth.is_auth && action == ''">
                <v-expansion-panels v-model="search_panel" class="search-panel">
                    <v-expansion-panel>
                        <v-expansion-panel-title class="search-title">
                            <v-icon class="mr-2">mdi-magnify</v-icon>
                            Angebote suchen
                        </v-expansion-panel-title>
                        <v-expansion-panel-text>
                            <v-form @submit.prevent="searchNow">
                                <v-checkbox-btn color="success" v-model="search.only_in_my_school" label="Nur in Deiner Schule suchen" :disabled="availableSchools.length == 0" />

                                <div class="my-4">
                                    <div class="text-body-1 font-weight-medium" v-if="search.only_in_my_school">Jetzt kannst Du nach Angeboten in Deiner Schule suchen.</div>
                                    <div class="text-body-1 font-weight-medium" v-if="!search.only_in_my_school">
                                        Jetzt kannst Du auch in anderen Schulen, die Du dir aussuchst, nach Angeboten suchen.
                                    </div>
                                    <div v-if="search.only_in_my_school === false" class="d-flex flex-row align-center ga-2 mt-2">
                                        <v-autocomplete
                                            v-model="add_school_id"
                                            :items="availableSchools"
                                            item-title="long_name"
                                            item-value="id"
                                            label="Schule auswählen"
                                            variant="outlined"
                                            clearable
                                            hide-details />
                                        <v-btn icon="mdi-plus" color="success" @click="addSchool(add_school_id)" />
                                    </div>

                                    <div v-if="search.schools && search.schools.length > 0 && !search.only_in_my_school" class="d-flex flex-row align-center flex-wrap ga-2 mt-2">
                                        <div class="text-body-1 font-weight-medium">Ausgewählte Schulen:</div>
                                        <v-chip
                                            v-for="school in search.schools"
                                            :key="school.id"
                                            color="secondary"
                                            closable
                                            @click:close="search.schools = search.schools.filter((s) => s.id !== school.id)">
                                            {{ school.school.long_name }}
                                        </v-chip>
                                    </div>
                                </div>

                                <div class="d-flex flex-row align-center ga-2 flex-wrap">
                                    <v-checkbox-btn color="pink" v-model="search.only_girls" label="Nur von Schülerinnen" />
                                    <v-checkbox-btn color="blue" v-model="search.only_boys" label="Nur von Schülern" />
                                </div>
                                <v-text-field
                                    label="Suchbegriff eingeben..."
                                    v-model="search_string"
                                    hide-details
                                    variant="outlined"
                                    class="mt-4"
                                    prepend-inner-icon="mdi-magnify"
                                    clearable
                                    @keyup.enter="searchNow"
                                    @click:clear="searchNow" />
                                <div class="text-right mt-4">
                                    <v-btn size="large" color="success" type="submit" variant="flat" rounded>
                                        <v-icon start>mdi-magnify</v-icon>
                                        Jetzt suchen
                                    </v-btn>
                                </div>
                            </v-form>
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                </v-expansion-panels>
            </div>

            <!-- Offers Section -->
            <div class="offers-section" v-if="is_loaded && !is_login && action == ''">
                <!-- Section Header -->
                <div class="section-header">
                    <div class="header-icon">
                        <v-icon size="28" color="white">mdi-book-open-page-variant</v-icon>
                    </div>
                    <div class="header-text">
                        <h2 class="section-title">Verfügbare Angebote</h2>
                        <p class="section-subtitle">{{ meta?.total || 0 }} Angebote gefunden</p>
                    </div>
                </div>

                <!-- Offers Grid -->
                <div class="offers-grid" data-testid="tutoring-overview-offers-grid" v-if="offers && offers.length > 0">
                    <OfferCard
                        v-for="offer in offers"
                        :key="offer.id"
                        :offer_id="offer.id"
                        :school_short_name="offer.school.short_name"
                        :school_long_name="offer.school.long_name"
                        :subject="offer.subject.short_name + ': ' + offer.subject.long_name"
                        :title="offer.title"
                        :description="offer.description"
                        :my_request="offer.my_request"
                        button="Anschauen"
                        @clickCard="showOffersDetail(offer)"
                        :is_mark="offer.is_own_offer" />
                </div>

                <!-- Pagination -->
                <div class="pagination-card" v-if="offers && offers.length > 0 && meta.last_page > 1">
                    <v-btn
                        variant="tonal"
                        color="primary"
                        size="large"
                        rounded="lg"
                        :disabled="meta.current_page == 1"
                        @click="offerStore.loadOffers(school_name, meta.current_page - 1)">
                        <v-icon start>mdi-arrow-left</v-icon>
                        Vorherige
                    </v-btn>
                    <div class="page-indicator">
                        <span class="page-current">{{ meta.current_page }}</span>
                        <span class="page-separator">von</span>
                        <span class="page-total">{{ meta.last_page }}</span>
                    </div>
                    <v-btn
                        variant="tonal"
                        color="primary"
                        size="large"
                        rounded="lg"
                        :disabled="meta.current_page == meta.last_page"
                        @click="offerStore.loadOffers(school_name, meta.current_page + 1)">
                        Nächste
                        <v-icon end>mdi-arrow-right</v-icon>
                    </v-btn>
                </div>

                <!-- No Offers -->
                <div class="no-offers" v-if="!offers || offers.length === 0">
                    <div class="no-offers-card">
                        <div class="no-offers-icon">
                            <v-icon size="72" color="grey-lighten-1">mdi-book-search-outline</v-icon>
                        </div>
                        <h3 class="no-offers-title">Keine Angebote gefunden</h3>
                        <p class="no-offers-text" v-if="!offer_config.auth.is_auth">Melde Dich an und lege selbst ein Angebot an.</p>
                        <p class="no-offers-text" v-else>Erstelle Dein eigenes Angebot und hilf anderen Schülern.</p>
                        <v-btn v-if="!offer_config.auth.is_auth" color="orange" variant="flat" size="large" rounded="lg" @click="startLogin" class="mt-4">
                            <v-icon start>mdi-login</v-icon>
                            Jetzt anmelden
                        </v-btn>
                        <v-btn v-else color="success" variant="flat" size="large" rounded="lg" @click="moveToTutoring" class="mt-4">
                            <v-icon start>mdi-plus</v-icon>
                            Angebot erstellen
                        </v-btn>
                    </div>
                </div>
            </div>

            <!-- Offer Detail Dialog -->
            <OffersDetail :offer="selected_offer" :config="offer_config" v-if="is_offer_dialog" />

            <!-- My Requests -->
            <MyRequests v-if="action == 'my_requests'" />

            <!-- Received Requests -->
            <ReceivedRequests v-if="action == 'received_requests'" />
        </div>

        <!-- Error Display -->
        <div class="error-container" v-if="error">
            <v-card class="error-card" elevation="12">
                <v-alert type="error" :title="error.response.data.message + ' (' + error.response.status + ')'" />
            </v-card>
        </div>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import { useOfferStore } from '@/stores/tutoring/OfferStore'
import { useRequestStore } from '@/stores/tutoring/RequestStore'
import { useUserStore } from '@/stores/tutoring/UserStore'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'
import OfferCard from '@/pages/homepage/tutoring/components/OfferCard.vue'
import SchoolAndUser from '@/pages/homepage/tutoring/components/TutoringOverview/SchoolAndUser.vue'
import OffersDetail from '@/pages/homepage/tutoring/components/TutoringOverview/OffersDetail.vue'
import MyRequests from '@/pages/homepage/tutoring/components/MyRequests.vue'
import ReceivedRequests from '@/pages/homepage/tutoring/components/ReceivedRequests.vue'

export default {
    components: { SchoolAndUser, OffersDetail, MyRequests, ReceivedRequests, OfferCard },

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.offerStore = useOfferStore()
        this.requestStore = useRequestStore()
        this.userStore = useUserStore()
        this.homepageStore = useHomepageStore()

        await this.homepageStore.loadSchoolsForTool('Nachhilfetool')
        await this.offerStore.loadOfferConfig(this.school_name)

        if (!this.offer_config?.auth?.is_auth) {
            this.school_name = this.$route.query.school
            if (!this.school_name) {
                await this.initWithoutSchool()
            } else {
                await this.initWithSchool()
            }
        } else {
            this.search = this.offer_config.auth.user.tutoring_filter
            this.school = this.offer_config.school
            this.school_name = this.school.short_name
            await this.initWithSchool()

            if (this.$route.query.received_requests == 'true') this.action = 'received_requests'
        }

        this.is_init = true
    },

    data() {
        return {
            tutoringStore: null,
            offerStore: null,
            userStore: null,
            homepageStore: null,
            school: null,
            school_name: '',
            is_loaded: false,
            is_init: false,
            is_login: false,
            search: {},
            search_panel: null,
            selected_offer: null,
            add_school_id: null,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['selected_school_id', 'data', 'action']),
        ...mapWritableState(useOfferStore, ['offer_config', 'error', 'offers', 'is_offer_dialog', 'meta', 'search_string']),
        ...mapWritableState(useHomepageStore, ['schools']),
        ...mapWritableState(useRequestStore, ['show_archived', 'show_to_user_archived']),

        availableSchools() {
            return this.schools.filter((school) => {
                if (school.id === this.offer_config?.school?.id) return false
                if (this.search.schools?.some((s) => s.id === school.id)) return false
                return true
            })
        },
    },

    watch: {
        selected_school_id() {
            if (this.selected_school_id) {
                const school = this.schools.find((s) => s.id === this.selected_school_id)
                if (school) {
                    const params = new URLSearchParams(window.location.search)
                    params.set('school', school.short_name)
                    window.location.href = window.location.pathname + '?' + params.toString()
                }
            }
        },
        'search.only_girls'(newValue) {
            if (newValue) this.search.only_boys = false
        },
        'search.only_boys'(newValue) {
            if (newValue) this.search.only_girls = false
        },
        search: {
            async handler(newValue) {
                await this.offerStore.setUserSearchCriteria(newValue)
                await this.searchNow()
                await this.offerStore.loadOfferConfig(this.school_name)
            },
            deep: true,
            flush: 'post',
        },
    },

    methods: {
        getParticleStyle(n) {
            const random = (min, max) => Math.random() * (max - min) + min
            return {
                left: `${random(0, 100)}%`,
                top: `${random(0, 100)}%`,
                width: `${random(4, 10)}px`,
                height: `${random(4, 10)}px`,
                animationDelay: `${random(0, 15)}s`,
                animationDuration: `${random(15, 25)}s`,
            }
        },

        toArchive(status) {
            this.show_archived = status
        },

        toToUserArchive(status) {
            this.show_to_user_archived = status
        },

        async addSchool(school_id) {
            if (!school_id) return
            if (!this.search.schools) {
                this.search.schools = []
            }
            this.search.schools.push({
                id: school_id,
                school: this.schools.find((s) => s.id === school_id),
            })
            this.add_school_id = null
        },

        async searchNow() {
            await this.offerStore.loadOffers(this.school_name)
        },

        async showOffersDetail(offer) {
            await this.offerStore.clickCount(offer.id)
            this.selected_offer = offer
            this.is_offer_dialog = true
        },

        moveToTutoring() {
            this.$router.push('/homepage/tutoring')
        },

        async afterLogin() {
            await axios.get('/sanctum/csrf-cookie')
            await this.homepageStore.loadSchoolsForTool('Nachhilfetool')
            await this.offerStore.loadOfferConfig(this.school_name)
            await this.initWithSchool()
            this.search = this.offer_config.auth.user.tutoring_filter
            this.school = this.offer_config.school
            this.is_login = false
        },

        startLogin() {
            this.data = {}
            this.data.school_id = this.school.id
            this.is_login = true
        },
        goToMainMenu() {
            this.$router.push('/')
        },

        async initWithSchool() {
            await this.offerStore.loadOfferConfig(this.school_name)
            this.school = this.offer_config.school
            this.data.school_id = this.school.id
            await this.offerStore.loadOffers(this.school_name)
            this.is_loaded = true
        },

        async initWithoutSchool() {
            await this.tutoringStore.loadConfig()
            if (this.schools.length == 1) {
                const school_name = this.schools[0].short_name
                const params = new URLSearchParams(window.location.search)
                params.set('school', school_name)
                window.location.href = window.location.pathname + '?' + params.toString()
            }
        },

        async logout() {
            await this.userStore.logout()
            await this.offerStore.loadOfferConfig(this.school_name)
            await this.offerStore.loadOffers(this.school_name)
        },
    },
}
</script>

<style scoped>
/* Base Layout */
.tutoring-page {
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
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
    width: 500px;
    height: 500px;
    background: linear-gradient(135deg, #f39200 0%, #d67f00 100%);
    top: -150px;
    left: -150px;
    animation-delay: 0s;
}

.orb-2 {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, #3aaa35 0%, #2d8a2a 100%);
    bottom: -100px;
    right: -100px;
    animation-delay: -7s;
}

.orb-3 {
    width: 350px;
    height: 350px;
    background: linear-gradient(135deg, #37474f 0%, #263238 100%);
    top: 60%;
    left: 30%;
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

/* Particles */
.particles {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
}

.particle {
    position: absolute;
    background: rgba(243, 146, 0, 0.3);
    border-radius: 50%;
    animation: drift 20s ease-in-out infinite;
}

.particle:nth-child(even) {
    background: rgba(58, 170, 53, 0.3);
}

.particle:nth-child(3n) {
    background: rgba(55, 71, 79, 0.2);
}

@keyframes drift {
    0%,
    100% {
        transform: translate(0, 0);
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    90% {
        opacity: 1;
    }
    100% {
        transform: translate(80px, -80px);
        opacity: 0;
    }
}

/* Main Container */
.main-container {
    position: relative;
    z-index: 1;
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
}

/* Header */
.header-section {
    text-align: center;
    margin-bottom: 40px;
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

.brand-container {
    margin-bottom: 20px;
}

.logo-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    background: linear-gradient(135deg, #f39200 0%, #3aaa35 100%);
    border-radius: 18px;
    margin-bottom: 16px;
    box-shadow: 0 10px 40px rgba(243, 146, 0, 0.3);
    animation: pulse-glow 3s ease-in-out infinite;
}

@keyframes pulse-glow {
    0%,
    100% {
        box-shadow: 0 10px 40px rgba(243, 146, 0, 0.3);
    }
    50% {
        box-shadow: 0 10px 60px rgba(58, 170, 53, 0.4);
    }
}

.brand-title {
    font-size: clamp(2rem, 6vw, 3.5rem);
    font-weight: 800;
    letter-spacing: -1px;
    margin: 0;
    line-height: 1;
}

.brand-nach {
    color: #f39200;
}
.brand-hilfe {
    color: #3aaa35;
}
.brand-tool {
    color: #37474f;
}

.brand-tagline {
    font-size: 1.1rem;
    color: #546e7a;
    margin-top: 8px;
}

/* School Info */
.school-info {
    margin-top: 16px;
}

.school-badge {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    padding: 10px 20px;
    border-radius: 30px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.school-logo img {
    height: 32px;
    width: auto;
    object-fit: contain;
}

.school-name {
    font-weight: 500;
    color: #37474f;
}

/* Menu Section */
.menu-section {
    margin-bottom: 32px;
    animation: fadeInUp 0.8s ease-out 0.2s both;
}

.overview-nav {
    display: flex;
    justify-content: center;
    margin: 8px 0 0;
}

.overview-nav-btn {
    font-weight: 600;
    color: #37474f;
}

.login-nav {
    display: flex;
    justify-content: center;
    margin: 8px 0 0;
}

.login-nav-btn {
    font-weight: 600;
    color: #37474f;
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

.menu-grid,
.sub-menu {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 20px;
}

/* Menu Cards */
.menu-card {
    position: relative;
    background: white;
    border-radius: 16px;
    padding: 24px;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    display: flex;
    flex-direction: column;
}

.menu-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.card-glow {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    transition: height 0.3s ease;
}

.menu-card:hover .card-glow {
    height: 5px;
}

.card-user .card-glow {
    background: linear-gradient(90deg, #3aaa35, #4bc044);
}
.card-user .card-icon {
    background: rgba(58, 170, 53, 0.1);
    color: #3aaa35;
}
.card-user .card-action {
    color: #3aaa35;
}

.card-requests .card-glow {
    background: linear-gradient(90deg, #3aaa35, #4bc044);
}
.card-requests .card-icon {
    background: rgba(58, 170, 53, 0.1);
    color: #3aaa35;
}
.card-requests .card-action {
    color: #3aaa35;
}

.card-my-requests .card-glow {
    background: linear-gradient(90deg, #3aaa35, #4bc044);
}
.card-my-requests .card-icon {
    background: rgba(58, 170, 53, 0.1);
    color: #3aaa35;
}
.card-my-requests .card-action {
    color: #3aaa35;
}

.card-logout .card-glow {
    background: linear-gradient(90deg, #f39200, #ffb74d);
}
.card-logout .card-icon {
    background: rgba(243, 146, 0, 0.1);
    color: #f39200;
}
.card-logout .card-action {
    color: #f39200;
}

.card-login .card-glow {
    background: linear-gradient(90deg, #f39200, #ffb74d);
}
.card-login .card-icon {
    background: rgba(243, 146, 0, 0.1);
    color: #f39200;
}
.card-login .card-action {
    color: #f39200;
}

.card-back .card-glow {
    background: linear-gradient(90deg, #78909c, #90a4ae);
}
.card-back .card-icon {
    background: rgba(120, 144, 156, 0.1);
    color: #78909c;
}

.card-archive .card-glow {
    background: linear-gradient(90deg, #3aaa35, #4bc044);
}
.card-archive .card-icon {
    background: rgba(58, 170, 53, 0.1);
    color: #3aaa35;
}

.card-content {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    flex: 1;
}

.card-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
    transition: transform 0.3s ease;
}

.menu-card:hover .card-icon {
    transform: scale(1.1);
}

.card-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #263238;
    margin: 0 0 8px 0;
}

.card-description {
    font-size: 0.9rem;
    color: #607d8b;
    line-height: 1.5;
    margin: 0 0 16px 0;
}

.card-action {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: gap 0.3s ease;
    margin-top: auto;
}

.menu-card:hover .card-action {
    gap: 10px;
}

.login-back-icon-btn {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: #efe6d8 !important;
    color: #f39200 !important;
    box-shadow: none !important;
    margin-bottom: 16px;
}

/* Search Section */
.search-section {
    margin-bottom: 32px;
}

.search-panel {
    border-radius: 16px !important;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.search-title {
    font-weight: 600;
}

/* Offers Section */
.offers-section {
    animation: fadeInUp 0.8s ease-out 0.3s both;
}

/* Section Header */
.section-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    padding: 20px 24px;
    background: linear-gradient(135deg, #3aaa35 0%, #2d8a2a 100%);
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(58, 170, 53, 0.25);
}

.header-icon {
    width: 56px;
    height: 56px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.header-text {
    color: white;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    line-height: 1.2;
}

.section-subtitle {
    font-size: 0.95rem;
    opacity: 0.9;
    margin: 4px 0 0 0;
}

.offers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

/* Pagination */
.pagination-card {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 24px;
    padding: 20px;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    flex-wrap: wrap;
}

.page-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
}

.page-current {
    font-size: 1.5rem;
    font-weight: 700;
    color: #3aaa35;
}

.page-separator {
    font-size: 0.9rem;
    color: #90a4ae;
}

.page-total {
    font-size: 1.1rem;
    font-weight: 600;
    color: #546e7a;
}

/* No Offers */
.no-offers {
    display: flex;
    justify-content: center;
    padding: 20px 0;
}

.no-offers-card {
    text-align: center;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 48px 40px;
    border-radius: 20px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    max-width: 420px;
}

.no-offers-icon {
    margin-bottom: 20px;
}

.no-offers-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #263238;
    margin: 0 0 12px 0;
}

.no-offers-text {
    font-size: 1rem;
    color: #607d8b;
    line-height: 1.6;
    margin: 0;
}

/* Error Container */
.error-container {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 50vh;
    padding: 20px;
}

.error-card {
    border-radius: 16px !important;
}

/* Responsive */
@media (max-width: 768px) {
    .main-container {
        padding: 20px 16px;
    }

    .brand-title {
        font-size: 2rem;
    }

    .menu-grid,
    .sub-menu {
        grid-template-columns: 1fr;
    }

    .menu-card {
        padding: 20px;
    }

    .section-header {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }

    .section-title {
        font-size: 1.25rem;
    }

    .offers-grid {
        grid-template-columns: 1fr;
    }

    .pagination-card {
        flex-direction: column;
        gap: 16px;
        padding: 16px;
    }

    .no-offers-card {
        padding: 32px 24px;
    }
}

/* Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    .gradient-orb,
    .particle,
    .menu-card,
    .logo-wrapper {
        animation: none;
    }
    .menu-card:hover {
        transform: none;
    }
}
</style>
