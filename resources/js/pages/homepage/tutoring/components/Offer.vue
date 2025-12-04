<template>
    <!-- NEUES ANGEBOT  -->
    <v-card-text v-if="is_loaded">
        <v-card tile flat color="tutoring_card" max-width="600">
            <v-form ref="form" v-model="is_valid" @submit.prevent="doCreateOffer(data)">
                <v-card-title class="bg-tutoring_card_title mb-2">Nachhilfe anbieten</v-card-title>
                <!-- Nachanme, Vorname, E-Mail eingeben-->
                <v-card-text>
                    <v-card tile flat color="transparent">
                        <!-- ANZEIGE DES FERTIGEN OFFERS -->
                        <v-card color="primary">
                            <v-card-text class="text-body-2" v-if="step >= 1">
                                {{ selectedSubject.long_name + ' (' + selectedSubject.short_name + ')' }}
                            </v-card-text>

                            <v-card-text v-if="step >= 2">
                                <div class="text-body-1 font-weight-bold">{{ data.title }}</div>
                                <div class="mt-2" style="white-space: pre-line" v-if="data.description">{{ data.description }}</div>
                            </v-card-text>

                            <v-card-text v-if="step >= 3">
                                <div v-if="unterstufeClasses.length > 0" class="mb-2">
                                    <strong>Unterstufe:</strong>
                                    {{ unterstufeClasses.join(', ') }}
                                </div>
                                <div v-if="oberstufeClasses.length > 0">
                                    <strong>Oberstufe:</strong>
                                    {{ oberstufeClasses.join(', ') }}
                                </div>
                            </v-card-text>
                        </v-card>
                        <!-- OFFER STEP 0: Auswahl Fach -->
                        <v-card tile flat color="transparent" v-if="step == 0">
                            <label class="text-subtitle-2 mb-2 d-block">Wähle das Fach aus, in dem Du Nachhilfe anbieten möchtest:</label>
                            <v-chip-group selected-class="text-success" column color="primary">
                                <v-chip color="primary" v-for="subject in subjects" :key="subject.id" @click="selectSubject(subject)">
                                    {{ subject.long_name + ' (' + subject.short_name + ')' }}
                                </v-chip>
                            </v-chip-group>
                            <div class="mt-4 d-flex flex-row align-center justify-space-between">
                                <div></div>
                                <v-btn tile flat color="primary" @click="nextStep('subject')" v-if="selectedSubject">Weiter</v-btn>
                            </div>
                        </v-card>

                        <!-- OFFER STEP 1: Titel und Beschreibung -->
                        <v-card class="mt-4" v-if="step == 1">
                            <v-text-field autofocus flat rounded="0" v-model="data.title" label="Titel" :rules="[required(), maxLength(255)]" />
                            <v-textarea flat rounded="0" v-model="data.description" label="Beschreibe Deine Nachhilfe im Detail" :rules="[maxLength(1024)]" />
                            <v-alert type="warning" v-if="message[1]">{{ message[1] }}</v-alert>
                            <div class="mt-4 d-flex flex-row align-center justify-space-between">
                                <v-btn tile flat color="warning" @click="step--">Zurück</v-btn>
                                <v-btn tile flat color="primary" @click="nextStep('title')">Weiter</v-btn>
                            </div>
                        </v-card>

                        <!-- OFFER STEP 2: Klassen -->
                        <v-card class="mt-4" v-if="step == 2">
                            <label class="text-subtitle-2 mb-2 d-block">Für folgende Klassen ist Deine Nachhilfe gedacht:</label>

                            <div class="mb-3">
                                <div class="text-caption text-medium-emphasis mb-1">Unterstufe</div>
                                <v-row dense>
                                    <v-col v-for="classNumber in 4" :key="classNumber" cols="12" sm="6" md="3">
                                        <v-checkbox v-model="data.classes[classNumber]" :label="`${classNumber}. Klasse`" hide-details />
                                    </v-col>
                                </v-row>
                            </div>

                            <div>
                                <div class="text-caption text-medium-emphasis mb-1">Oberstufe</div>
                                <v-row dense>
                                    <v-col v-for="classNumber in 5" :key="classNumber + 4" cols="12" sm="6" md="3">
                                        <v-checkbox v-model="data.classes[classNumber + 4]" :label="`${classNumber + 4}. Klasse`" hide-details />
                                    </v-col>
                                </v-row>
                            </div>

                            <v-alert type="warning" v-if="message[2]">{{ message[2] }}</v-alert>
                            <div class="mt-4 d-flex flex-row align-center justify-space-between">
                                <v-btn tile flat color="warning" @click="step--">Zurück</v-btn>
                                <v-btn tile flat color="primary" @click="nextStep('classes')">Weiter</v-btn>
                            </div>
                        </v-card>

                        <!-- OFFER STEP 3: Bestätigung -->
                        <v-card class="mt-4" v-if="step == 2">
                            <label class="text-subtitle-2 mb-2 d-block">Für folgende Klassen ist Deine Nachhilfe gedacht:</label>
                        </v-card>
                    </v-card>
                </v-card-text>

                <!-- ERROR-->
                <v-card-text v-if="error">
                    <v-alert type="error">{{ error?.response?.data?.message + ' (' + error?.response?.status + ')' }}</v-alert>
                </v-card-text>
                <!-- SCHLIESSEN/SPEICHERN-->
                <v-card-actions v-if="step >= 5">
                    <div class="d-flex flex-row align-center justify-space-between w-100">
                        <its-menu-button subtitle="Abbruch" icon="mdi-close" color="warning" @click="action = ''" />
                        <its-menu-button subtitle="Speichern" icon="mdi-content-save" color="success" @click="updateProfile(data)" />
                    </div>
                </v-card-actions>
            </v-form>
        </v-card>
    </v-card-text>
    <v-card-text>
        {{ subjects }}
    </v-card-text>

    <v-card-text>
        {{ config }}
    </v-card-text>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import { useUserStore } from '@/stores/tutoring/UserStore'
import { useSubjectStore } from '@/stores/tutoring/SubjectStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },
    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.userStore = useUserStore()
        this.subjectStore = useSubjectStore()
        await this.tutoringStore.loadAuth()
        await this.subjectStore.index()
        this.createOffer()
        this.is_loaded = true
    },

    async mounted() {},

    unmounted() {},

    data() {
        return {
            tutoringStore: null,
            userStore: null,
            subjectStore: null,
            is_valid: false,
            is_password_visible: false,
            is_password_visible_confirm: false,
            selectedSubject: null,
            step: 0,
            is_loaded: false,
            message: [],
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['auth', 'action', 'config']),
        ...mapWritableState(useUserStore, ['error', 'data']),
        ...mapWritableState(useSubjectStore, ['subjects']),
        selectedClasses() {
            return Object.entries(this.data.classes || {})
                .filter(([key, value]) => value === true)
                .map(([key]) => parseInt(key))
                .sort((a, b) => a - b)
        },

        selectedClassesText() {
            const selected = this.selectedClasses.map((num) => `${num}. Klasse`).join(', ')

            return selected || 'Keine Klassen ausgewählt'
        },

        unterstufeClasses() {
            return this.selectedClasses.filter((num) => num <= 4).map((num) => `${num}. Klasse`)
        },

        oberstufeClasses() {
            return this.selectedClasses.filter((num) => num > 4).map((num) => `${num}. Klasse`)
        },
    },

    watch: {},

    methods: {
        nextStep(item) {
            switch (item) {
                case 'subject':
                    if (!this.selectedSubject) return
                    this.message = []
                    this.step++
                    break
                case 'title':
                    if (!this.data.title) {
                        this.message = 'Bitte gib einen Titel für Dein Angebot ein.'
                        return
                    }
                    if (!this.data.description) {
                        this.message[this.step] = 'Bitte gib eine kurze Beschreibung Deiner Nachhilfe ein.'
                        return
                    }
                    this.message = []
                    this.step++
                    break
                case 'classes':
                    if (!Object.values(this.data.classes).some((isSelected) => isSelected === true)) {
                        this.message[this.step] = 'Bitte wähle mindestens eine Klasse aus, für die Deine Nachhilfe gedacht ist.'
                        return
                    }
                    this.message = []
                    this.step++
                    break
            }
        },
        selectSubject(subject) {
            this.selectedSubject = subject
            this.data.title = 'Biete ' + subject.long_name + ' Nachhilfe'
        },
        createOffer() {
            this.data = {
                title: '',
                description: '',
                classes: { 1: false, 2: false, 3: false, 4: false, 5: false, 6: false, 7: false, 8: false, 9: false },
            }
        },

        async doCreateOffer(data) {},
    },
}
</script>
