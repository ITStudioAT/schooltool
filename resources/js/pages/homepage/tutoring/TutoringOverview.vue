<template>
    <div class="schooltool-background"></div>
    <div class="h-100 w-100 d-flex flex-column" style="max-width: 1024px; margin: auto">
        <div class="h-100 w-100 d-flex flex-column align-center justify-center" v-if="is_init && !school_name && schools.length == 0">
            <v-card tile flat color="transparent">
                <v-alert type="error" title="Das Nachhilfetool kann derzeit nicht verwendet werden." />
            </v-card>
        </div>

        <!-- Überschrift -->
        <div v-if="is_loaded">
            <v-card flat tile>
                <div class="d-flex flex-row justify-center">
                    <div class="text-h6 text-md-h5 text-lg-h4 text-xl-h2">
                        <span class="text-secondary">NACH</span>
                        <span class="text-third font-weight-bold">HILFE</span>
                        <span class="text-secondary">TOOL</span>
                    </div>
                </div>
            </v-card>

            <v-card flat tile color="transparent" class="mt-4" v-if="offer_config.school">
                <div class="d-flex justify-center">
                    <div class="d-flex flex-column align-center">
                        <div class="text-caption">{{ offer_config.school.long_name }}</div>
                        <div style="width: 96px; height: 48px" class="bg-primary-lighten-4" v-if="offer_config.school.logo">
                            <img :src="'/storage/images/' + offer_config.school.logo" alt="Logo" style="width: 100%; height: 100%; object-fit: contain" />
                        </div>
                    </div>
                </div>
            </v-card>
        </div>

        <v-card tile flat class="mt-4" color="transparent" v-if="is_loaded && !is_login">
            <!-- Menü -->
            <v-card flat color="primary" class="border-md" v-if="action == ''">
                <div class="d-flex flex-wrap justify-center justify-md-start ga-2">
                    <ItsCard
                        :title="offer_config?.auth?.user?.last_name + ' ' + offer_config?.auth?.user?.first_name"
                        text="Hier gelangst Du zu Deinem persönlichen Bereich. Dort kannst Du auch Angebote erstellen."
                        color="success"
                        button="Mein Bereich"
                        @clickCard="moveToTutoring"
                        v-if="offer_config.auth.is_auth" />

                    <ItsCard
                        title="Erhaltene Anfragen"
                        text="Hier kannst Du nachschauen, welche Anfragen Du erhalten hast."
                        color="success"
                        button="Erhaltene Anfragen"
                        @clickCard="action = 'received_requests'"
                        v-if="offer_config.auth.is_auth" />

                    <ItsCard
                        title="Deine Anfragen"
                        text="Hier kannst Du nachschauen, welche Anfragen Du bereits gestellt hast."
                        color="success"
                        button="Meine Anfragen"
                        @clickCard="action = 'my_requests'"
                        v-if="offer_config.auth.is_auth" />

                    <ItsCard
                        title="Mich abmelden"
                        text="Hier kannst Du Dich vom System ausloggen."
                        color="warning"
                        button="Abmelden"
                        @clickCard="logout"
                        v-if="offer_config.auth.is_auth" />

                    <ItsCard
                        title="Anmelden"
                        text="Melde Dich kostenlos an, um mehr zu können."
                        color="secondary"
                        button="Los"
                        @clickCard="startLogin"
                        v-if="!offer_config.auth.is_auth" />
                </div>
            </v-card>

            <!-- Menü für MEINE ANFRAGEN -->
            <v-card flat color="primary" class="border-md d-flex flex-row align-start flex-wrap ga-2" v-if="action == 'my_requests'">
                <div class="d-flex flex-wrap justify-center ga-2">
                    <ItsCard title="Zurück" text="Zurück zur Übersicht." color="success" button="Zurück" @clickCard="action = ''" />
                </div>
                <div class="d-flex flex-wrap justify-center ga-2">
                    <ItsCard title="Archiv" text="Zeige alle archivierten Anfragen an." color="success" button="Zum Archiv" @clickCard="" />
                </div>
            </v-card>

            <!-- Menü für ERHALTENE ANFRAGEN -->
            <v-card flat color="primary" class="border-md d-flex flex-row align-start flex-wrap ga-2" v-if="action == 'received_requests'">
                <div class="d-flex flex-wrap justify-center ga-2">
                    <ItsCard title="Zurück" text="Zurück zur Übersicht." color="success" button="Zurück" @clickCard="action = ''" />
                </div>
            </v-card>
        </v-card>

        <!-- SCHULE AUSWÄHLEN & BENUTZER MANAGEMENT -->
        <SchoolAndUser
            :is_init="is_init"
            :is_login="is_login"
            :school_name="school_name"
            :school="school"
            @cancel-login="is_login = false"
            @login-success="afterLogin"
            @logout="logout" />

        <!-- SUCHLEISTE -->
        <v-expansion-panels v-model="search_panel" color="primary" v-if="offer_config && offer_config.auth.is_auth && action == ''" class="mt-4">
            <v-expansion-panel>
                <v-expansion-panel-title class="text-body-1 font-weight-medium">Suche</v-expansion-panel-title>
                <v-expansion-panel-text>
                    <v-form @submit.prevent="searchNow">
                        <v-checkbox-btn color="success" v-model="search.only_in_my_school" label="Nur in Deiner Schule suchen" />

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

                        <div class="d-flex flex-row align-center ga-2">
                            <v-checkbox-btn color="pink" v-model="search.only_girls" label="Nachhilfe nur von Mädchen" />
                            <v-checkbox-btn color="blue" v-model="search.only_boys" label="Nachhilfe nur von Burschen" />
                        </div>
                        <v-text-field
                            label="Suchtext"
                            v-model="search_string"
                            hide-details
                            class="large-text mt-4"
                            append-icon="mdi-magnify"
                            clearable
                            @keyup.enter="searchNow"
                            @click:clear="searchNow" />
                        <div class="text-right mt-4">
                            <v-btn size="large" tile flat color="success" type="submit">Jetzt suchen</v-btn>
                        </div>
                    </v-form>
                </v-expansion-panel-text>
            </v-expansion-panel>
        </v-expansion-panels>

        <!-- Angebote -->
        <v-card tile flat border-md color="transparent" v-if="is_loaded && !is_login && action == ''">
            <!-- Alle Angebote anzeigen -->

            <v-card flat color="transparent" class="border-md mt-4 pa-2" v-if="offers && offers.length > 0">
                <div class="d-flex flex-row flex-wrap ga-2 justify-center">
                    <div style="width: 300px" v-for="offer in offers" :key="offer.id">
                        <ItsCard
                            class="h-100"
                            :title="offer.school.short_name"
                            :subtitle="offer.subject.short_name + ': ' + offer.subject.long_name"
                            :text="offer.title"
                            :description="offer.description"
                            color="success"
                            button="Anschauen"
                            @clickCard="showOffersDetail(offer)"
                            :is_mark="offer.is_own_offer"
                            :key="offer.id" />
                    </div>
                </div>
                <v-card-text class="d-flex flex-row align-center justify-space-between">
                    <v-btn
                        tile
                        flat
                        size="x-large"
                        prepend-icon="mdi-arrow-left"
                        color="primary"
                        :disabled="meta.current_page == 1"
                        @click="offerStore.loadOffers(school_name, meta.current_page - 1)">
                        Vorherige
                    </v-btn>
                    <v-btn
                        tile
                        flat
                        size="x-large"
                        append-icon="mdi-arrow-right"
                        color="primary"
                        :disabled="meta.current_page == meta.last_page"
                        @click="offerStore.loadOffers(school_name, meta.current_page + 1)">
                        Nächste
                    </v-btn>
                </v-card-text>
            </v-card>

            <!-- KEINE ANGEBOT VORHANDEN-->
            <v-card v-else class="border-md mt-4">
                <v-alert
                    type="info"
                    color="secondary"
                    title="Aktuell sind keine Angebote vorhanden!"
                    text="Melde Dich an und lege selbst ein Angebot an."
                    v-if="!offer_config.auth.is_auth">
                    <v-btn size="small" tile flat color="primary" variant="text" class="ml-4" @click="startLogin">Los</v-btn>
                    <v-btn size="small" tile flat color="primary" variant="text" class="ml-4" @click="moveToTutoring" v-if="offer_config.auth.is_auth">Los</v-btn>
                </v-alert>
                <v-alert type="info" color="secondary" title="Aktuell sind keine Angebote vorhanden!" text="Erstelle Dein eigenes Angebot." v-if="offer_config.auth.is_auth">
                    <v-btn size="small" tile flat color="primary" variant="text" class="ml-4" @click="moveToTutoring" v-if="offer_config.auth.is_auth">Los</v-btn>
                </v-alert>
            </v-card>
        </v-card>

        <!-- ANEGBOT IM DETAIL -->
        <OffersDetail :offer="selected_offer" :config="offer_config" v-if="is_offer_dialog" />

        <!-- MEINE ANFRAGEN -->
        <MyRequests v-if="action == 'my_requests'" />

        <!-- ERHALTENE ANFRAGEN -->
        <ReceivedRequests v-if="action == 'received_requests'" />
    </div>

    <div class="h-100 w-100 d-flex flex-column justify-center align-center" style="max-width: 1024px; margin: auto" v-if="error">
        <!-- Fehlermeldung -->
        <v-card class="mt-4">
            <v-alert type="error" :title="error.response.data.message + ' (' + error.response.status + ')'" />
        </v-card>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import { useOfferStore } from '@/stores/tutoring/OfferStore'
import { useUserStore } from '@/stores/tutoring/UserStore'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'
import ItsCard from '@/pages/components/ItsCard.vue'
import SchoolAndUser from '@/pages/homepage/tutoring/components/TutoringOverview/SchoolAndUser.vue'
import OffersDetail from '@/pages/homepage/tutoring/components/TutoringOverview/OffersDetail.vue'
import MyRequests from '@/pages/homepage/tutoring/components/MyRequests.vue'
import ReceivedRequests from '@/pages/homepage/tutoring/components/ReceivedRequests.vue'

export default {
    components: { ItsCard, SchoolAndUser, SchoolAndUser, OffersDetail, MyRequests, ReceivedRequests },

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.offerStore = useOfferStore()
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
        }

        this.is_init = true
    },

    mounted() {},

    unmounted() {},

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

        availableSchools() {
            return this.schools.filter((school) => {
                // Exclude user's own school
                if (school.id === this.offer_config?.school?.id) return false

                // Exclude already selected schools
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
            // TODO
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
.schooltool-background {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: url('/storage/images/students.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    opacity: 0.2;
    pointer-events: none;
    z-index: -1;
}

.large-text :deep(input) {
    font-size: 28px !important;
}
</style>
