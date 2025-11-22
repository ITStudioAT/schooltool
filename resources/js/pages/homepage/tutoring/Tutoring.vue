<template>
    <v-container fluid class="ma-0 w-100 h-100 pa-2 d-flex align-center justify-center bg-tutoring_background text-tutoring_text">
        <div class="d-flex flex-row border-md pa-0">
            <img src="/storage/images/books.jpg" width="300" v-if="$vuetify.display.smAndUp" />

            <v-card flat tile width="300" class="flex-grow-1 d-flex flex-column" color="transparent">
                <v-card-title class="bg-tutoring_secondary text-uppercase text-h4">Tutoring</v-card-title>

                <!-- Schulen auswählen, wenn sie nicht im Aufruf mitgeliefert wurde, z. B. ?school=cdgym -->
                <div class="mt-4" v-if="!school">
                    <div class="text-body-1 font-weight-bold ml-2">Bitte die Schule auswählen</div>
                    <div>
                        <v-autocomplete dense hide-details v-model="selected_school_id" :items="schools" item-title="long_name" item-value="id" label="Auswahl Schule" />
                    </div>
                </div>

                <v-card-text v-if="school">
                    <div>
                        <img :src="`/storage/images/${school?.logo}`" alt="Logo" class="logo" v-if="school?.logo" height="100" />
                    </div>
                </v-card-text>

                <!-- E-Mail muss eingegeben werden -->
                <v-card-text v-if="school && !data.status">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="checkEmail(data)" class="mb-4">
                        <v-text-field autofocus v-model="data.email" label="Deine E-Mail-Adresse" :rules="[required(), mail()]" tabindex="1" />
                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" slim flat rounded="0" to="/">Zurück</v-btn>
                            <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.email" tabindex="2">Weiter</v-btn>
                        </div>
                    </v-form>
                </v-card-text>

                <!-- Neuer Benutzer: Name muss eingegeben werden -->
                <v-card-text v-if="data.status == 'NEW_USER'">
                    <div class="text-h6 font-weight-medium">Neuer Benutzer</div>
                    <div class="text-body-2">E-Mail: {{ data.email }}</div>
                    <v-form ref="form" v-model="is_valid" @submit.prevent="createUser(data)" class="my-4">
                        <v-text-field autofocus v-model="data.last_name" label="Dein Nachname" :rules="[required(), maxLength(255)]" tabindex="1" />
                        <v-text-field v-model="data.first_name" label="Dein Vorname" :rules="[maxLength(255)]" tabindex="1" />
                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" slim flat rounded="0" @click="data.status = ''">Zurück</v-btn>
                            <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.email" tabindex="2">Weiter</v-btn>
                        </div>
                    </v-form>
                </v-card-text>
            </v-card>
        </div>
    </v-container>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/homepage/TutoringStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },
    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.school_name = this.$route.query.school
        await this.tutoringStore.loadConfig()
    },

    async mounted() {},

    unmounted() {},

    data() {
        return {
            tutoringStore: null,
            school_name: null,

            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['schools', 'selected_school_id', 'school', 'data']),
    },

    watch: {
        selected_school_id: {
            handler(newId) {
                this.school = this.schools.find((school) => school.id === newId) || null
            },
            immediate: true,
        },
        schools: {
            handler(newSchools) {
                if (newSchools.length > 0 && this.school_name) {
                    const foundSchool = newSchools.find((school) => school.short_name.toLowerCase() === this.school_name.toLowerCase())
                    if (foundSchool) {
                        this.school = foundSchool
                        this.selected_school_id = foundSchool.id
                    }
                }
            },
            immediate: true,
        },
    },

    methods: {
        async checkEmail(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            data.school_id = this.school?.id ?? null
            if (!(await this.tutoringStore.checkEmail(data))) return
        },

        async createUser(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            data.school_id = this.school?.id ?? null
            if (!(await this.tutoringStore.createUser(data))) return
        },
    },
}
</script>
