<template>
    <div class="schooltool-background"></div>
    <div class="h-100 w-100 d-flex flex-column" style="max-width: 1024px; margin: auto">
        <div class="h-100 w-100 d-flex flex-column align-center justify-center" v-if="is_init && !school_name && schools.length == 0">
            <v-card tile flat color="transparent">
                <v-alert type="error" title="Das Nachhilfetool kann derzeit nicht verwendet werden." />
            </v-card>
        </div>

        <div class="h-100 w-100 d-flex flex-column align-center justify-center" v-if="is_init && !school_name && schools.length > 1">
            <v-card tile flat color="transparent">
                <div class="text-body-1 font-weight-bold ml-2">Bitte die Schule auswählen</div>
                <div>
                    <v-autocomplete dense hide-details v-model="selected_school_id" :items="schools" item-title="long_name" item-value="id" label="Auswahl Schule" />
                </div>
            </v-card>
        </div>

        <v-card tile flat color="transparent" v-if="is_loaded">
            <!-- Menü -->
            <v-card flat color="primary" class="border-md">
                <div class="d-flex flex-wrap justify-center justify-lg-start ga-4">
                    <ItsCard
                        :title="offer_config?.auth?.user?.last_name + ' ' + offer_config?.auth?.user?.first_name"
                        text="Hier gelangst Du zu Deinem persönlichen Bereich."
                        color="success"
                        button="Mein Bereich"
                        @clickCard=""
                        v-if="offer_config.auth.is_auth" />

                    <ItsCard
                        title="Abmelden"
                        text="Hier kannst Du Dich vom System ausloggen."
                        color="success"
                        button="Abmelden"
                        @clickCard="logout"
                        v-if="offer_config.auth.is_auth" />

                    <ItsCard
                        title="Anmelden"
                        text="Hier gelangst Du zu Deinem persönlichen Bereich."
                        color="secondary"
                        button="Los"
                        @clickCard=""
                        v-if="!offer_config.auth.is_auth" />
                </div>
            </v-card>

            <!-- Überschrift -->
            <v-card flat color="transparent" class="mt-4">
                <div class="d-flex flex-row justify-center">
                    <div class="text-h6 text-md-h5 text-lg-h4 text-xl-h2">
                        <span class="text-secondary">NACH</span>
                        <span class="text-third font-weight-bold">HILFE</span>
                        <span class="text-secondary">TOOL</span>
                    </div>
                </div>
            </v-card>

            <v-card flat color="transparent" class="mt-4" v-if="offer_config.school">
                <div class="d-flex justify-center">
                    <div class="d-flex flex-column align-center">
                        <div class="text-caption">{{ offer_config.school.long_name }}</div>
                        <div style="width: 96px; height: 48px" class="bg-primary-lighten-4">
                            <img :src="'/storage/images/' + offer_config.school.logo" alt="Logo" style="width: 100%; height: 100%; object-fit: contain" />
                        </div>
                    </div>
                </div>
            </v-card>

            <!-- Suchzeile -->
            <v-card flat color=" bg-primary" class="border-md mt-4" v-if="offers && offers.length > 0">
                <v-text-field label="Suche" hide-details class="large-text" append-icon="mdi-magnify" clearable />
            </v-card>

            <!-- Angebote -->
            <v-card flat color=" bg-primary" class="border-md mt-4" v-if="offers && offers.length > 0">
                <ItsCard :title="offer.subject.short_name" :text="offer.title" color="secondary" button="Los" @clickCard="" v-for="offer in offers" :key="offer.id" />
            </v-card>
            <!-- KEINE ANGEBOT VORHANDEN-->
            <v-card v-else class="border-md mt-4">
                <v-alert type="info" title="Aktuell sind keine Angebote vorhanden!" />
            </v-card>
        </v-card>
    </div>

    <div class="h-100 w-100 d-flex flex-column justify-center align-center" style="max-width: 1024px; margin: auto" v-if="error">
        <!-- Fehlermeldung -->
        <v-card class="mt-4">
            <v-alert type="error" :title="error.response.data.message + ' (' + error.response.status + ')'" />
        </v-card>
    </div>

    <v-card flat color=" bg-primary" class="border-md mt-4">
        OFFERS:
        {{ offers }}
    </v-card>
    <v-card flat color=" bg-primary" class="border-md mt-4">
        SCHOOLS:
        {{ schools }}
    </v-card>
    <v-card flat color=" bg-primary" class="border-md mt-4">
        OFFER_CONFIG:
        {{ offer_config }}
    </v-card>

    <v-card flat color=" bg-primary" class="border-md mt-4">
        SCHOOL_NAME:
        {{ school_name }}
    </v-card>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import { useOfferStore } from '@/stores/tutoring/OfferStore'
import { useUserStore } from '@/stores/tutoring/UserStore'
import ItsCard from '@/pages/components/ItsCard.vue'

export default {
    components: { ItsCard },

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.offerStore = useOfferStore()
        this.userStore = useUserStore()

        this.school_name = this.$route.query.school

        if (!this.school_name) {
            await this.initWithoutSchool()
        } else {
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
            school_name: '',
            is_loaded: false,
            is_init: false,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['config', 'schools', 'selected_school_id']),
        ...mapWritableState(useOfferStore, ['offer_config', 'error', 'offers']),
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
    },

    methods: {
        async initWithSchool() {
            await this.offerStore.loadOfferConfig(this.school_name)
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
            await this.offerStore.index()
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
    font-size: 42px !important;
}
</style>
