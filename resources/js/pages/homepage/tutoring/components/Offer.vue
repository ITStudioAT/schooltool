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

                            <v-card-text class="text-body-1" v-if="step >= 3">
                                <div v-if="unterstufeClasses.length > 0" class="mb-2">
                                    <strong>Unterstufe:</strong>
                                    {{ unterstufeClasses.join(', ') }}
                                </div>
                                <div v-if="oberstufeClasses.length > 0">
                                    <strong>Oberstufe:</strong>
                                    {{ oberstufeClasses.join(', ') }}
                                </div>
                            </v-card-text>

                            <v-card-text v-if="step >= 4">
                                <div class="text-body-1 font-weight-bold">
                                    Gültig bis:
                                    <span v-if="data.active_until">{{ formattedActiveUntil }}</span>
                                    <span v-else>unendlich</span>
                                </div>
                            </v-card-text>

                            <v-card-text v-if="step >= 5">
                                <div class="text-body-1 font-weight-bold">
                                    <div v-if="!data.is_group">Einzelunterricht</div>
                                    <div v-else>Gruppenunterricht</div>
                                    <div v-if="data.is_group">Maximal {{ data.max_group_members }} Teilnehmer in der Gruppe</div>
                                    <div>Kosten pro Stunde: {{ data.price_per_hour }} Euro</div>
                                </div>
                            </v-card-text>

                            <v-card-text v-if="step >= 6">
                                <div class="text-body-1 font-weight-bold">
                                    <div v-if="selectedSubject.must_be_accepted">Lehrer:in zur Bestätigung: {{ data.email_mentor }}</div>
                                    <div v-else>Das Angebot wird sofort freigeschaltet</div>
                                </div>
                            </v-card-text>

                            <v-card-text v-if="step >= 7">
                                <div class="text-body-1 font-weight-bold d-flex flex-row align-center ga-2">
                                    <div>Für andere Schulen sichtbar:</div>
                                    <div v-if="data.visible_for_other_schools">
                                        <v-icon icon="mdi-check" color="green" />
                                        JA
                                    </div>
                                    <div v-else>
                                        <v-icon icon="mdi-close" color="error" />
                                        NEIN
                                    </div>
                                </div>
                            </v-card-text>
                        </v-card>
                        <!-- OFFER STEP 0: Auswahl Fach -->
                        <v-card v-if="step == 0">
                            <v-card-text>
                                <label class="text-subtitle-2 mb-2 d-block">Wähle das Fach aus, in dem Du Nachhilfe anbieten möchtest:</label>
                                <v-chip-group selected-class="text-success" column color="primary" v-model="selectedSubjectId">
                                    <v-chip color="primary" v-for="subject in subjects" :key="subject.id" :value="subject.id" @click="selectSubject(subject)">
                                        {{ subject.long_name + ' (' + subject.short_name + ')' }}
                                    </v-chip>
                                </v-chip-group>
                                <div class="mt-4 d-flex flex-row align-center justify-space-between">
                                    <div></div>
                                    <v-btn tile flat color="primary" @click="nextStep('subject')" v-if="selectedSubject">Weiter</v-btn>
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- OFFER STEP 1: Titel und Beschreibung -->
                        <v-card class="mt-4" v-if="step == 1">
                            <v-card-text>
                                <v-text-field autofocus flat rounded="0" v-model="data.title" label="Titel" :rules="[required(), maxLength(255)]" />
                                <v-textarea flat rounded="0" v-model="data.description" label="Beschreibe Deine Nachhilfe im Detail" :rules="[maxLength(1024)]" />
                                <v-alert type="warning" v-if="message[1]">{{ message[1] }}</v-alert>
                                <div class="mt-4 d-flex flex-row align-center justify-space-between">
                                    <v-btn tile flat color="warning" @click="step--">Zurück</v-btn>
                                    <v-btn tile flat color="primary" @click="nextStep('title')">Weiter</v-btn>
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- OFFER STEP 2: Klassen -->
                        <v-card class="mt-4" v-if="step == 2">
                            <v-card-text>
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
                            </v-card-text>
                        </v-card>

                        <!-- OFFER STEP 3: Gültig bis -->
                        <v-card class="mt-4" v-if="step == 3">
                            <v-card-text>
                                <label class="text-subtitle-2 mb-2 d-block">Wie lange soll dein Angebot gültig sein:</label>
                                <v-checkbox label="Es gibt ein Ende-Datum für das Angebot" color="success" v-model="data.is_active_until" />
                                <v-date-picker color="primary" border="md" title="Gültig bis" v-model="dateOnly" v-if="data.is_active_until" />
                                <v-alert type="warning" v-if="message[3]">{{ message[3] }}</v-alert>
                                <div class="mt-4 d-flex flex-row align-center justify-space-between">
                                    <v-btn tile flat color="warning" @click="step--">Zurück</v-btn>
                                    <v-btn tile flat color="primary" @click="nextStep('active_until')">Weiter</v-btn>
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- OFFER STEP 4: Gültig bis -->
                        <v-card class="mt-4" v-if="step == 4">
                            <v-card-text>
                                <label class="text-subtitle-2 mb-2 d-block">Ein paar Angaben noch:</label>
                                <v-checkbox label="Möchtest du eine ganze Gruppe gleichzeitig unterrichten?" color="success" v-model="data.is_group" />
                                <v-number-input
                                    :reverse="false"
                                    controlVariant="split"
                                    label=""
                                    :hideInput="false"
                                    :inset="false"
                                    :min="2"
                                    :max="5"
                                    v-model="data.max_group_members"
                                    v-if="data.is_group" />

                                <label class="text-subtitle-2 mb-2 d-block">Was kostet die Nachhilfe pro Stunde in Euro:</label>
                                <v-number-input
                                    label="Was kostet die Nachhilfe"
                                    :reverse="false"
                                    controlVariant="split"
                                    :hideInput="false"
                                    :inset="false"
                                    :min="0"
                                    :max="50"
                                    v-model="data.price_per_hour" />
                                <div class="mt-4 d-flex flex-row align-center justify-space-between">
                                    <v-btn tile flat color="warning" @click="step--">Zurück</v-btn>
                                    <v-btn tile flat color="primary" @click="nextStep('more_infos')">Weiter</v-btn>
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- OFFER STEP 5: Lehrer:in auswähen für Bestätigung -->
                        <v-card class="mt-4" v-if="step == 5">
                            <v-card-text>
                                <label class="text-subtitle-2 mb-2 d-block">Bitte wähle Deine/n Lehrer:in aus, dir/der Dein Angebot bestätigt:</label>
                                <v-autocomplete label="Bitte Lehrer:in auswählen" :items="selectedSubject.email_mentors" v-model="data.email_mentor" :rules="[required()]" />
                                <v-alert type="warning" v-if="message[5]">{{ message[5] }}</v-alert>
                                <div class="mt-4 d-flex flex-row align-center justify-space-between">
                                    <v-btn tile flat color="warning" @click="step--">Zurück</v-btn>
                                    <v-btn tile flat color="primary" @click="nextStep('mentor')">Weiter</v-btn>
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- OFFER STEP 6: Angebot auch für andere Schulen sichtbar -->
                        <v-card class="mt-4" v-if="step == 6">
                            <v-card-text>
                                <label class="text-subtitle-2 mb-2 d-block">Soll dieses Angebot auch für Schüler:innen anderer Schulen sichtbar sein?</label>
                                <v-checkbox v-model="data.visible_for_other_schools" label="Für andere Schulen sichtbar" hide-details />
                                <v-alert type="warning" v-if="message[6]">{{ message[6] }}</v-alert>
                                <div class="mt-4 d-flex flex-row align-center justify-space-between">
                                    <v-btn tile flat color="warning" @click="step--">Zurück</v-btn>
                                    <v-btn tile flat color="primary" @click="nextStep('visible_for_other_schools')">Weiter</v-btn>
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- OFFER STEP 8: Fertig, Bestätigung abwarten -->
                        <v-card class="mt-4" v-if="step == 8 && selectedSubject.must_be_accepted">
                            <v-card-text>
                                <v-alert type="success">
                                    <div v-if="data.id">Das Angebot für Nachhilfe wurde geändert.</div>
                                    <div v-if="!data.id">Das Angebot für Nachhilfe wurde erstellt.</div>
                                    <div>Bitte warte nun auf die Freigabe durch den/die Lehrer:in. Du bekommst Bescheid!</div>
                                </v-alert>
                                <div class="mt-4 d-flex flex-row align-center justify-space-between">
                                    <div></div>
                                    <v-btn tile flat color="primary" @click="finished">Fertig</v-btn>
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- OFFER STEP 8: Fertig, keine Bestätigung nötig -->
                        <v-card class="mt-4" v-if="step == 8 && !selectedSubject.must_be_accepted">
                            <v-card-text>
                                <v-alert type="success">
                                    <div v-if="data.id">Das Angebot für Nachhilfe wurde geändert.</div>
                                    <div v-if="!data.id">Das Angebot für Nachhilfe wurde erstellt.</div>
                                    <div v-if="!selectedSubject.is_active">Es kann jederzeit online gestellt werden!</div>
                                </v-alert>
                                <div class="mt-4 d-flex flex-row align-center justify-space-between">
                                    <div></div>
                                    <v-btn tile flat color="primary" @click="finished">Fertig</v-btn>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-card>
                </v-card-text>
                <v-card-text>
                    {{ offer }}
                </v-card-text>

                <!-- ERROR-->
                <v-card-text v-if="error">
                    <v-alert type="error">{{ error?.response?.data?.message + ' (' + error?.response?.status + ')' }}</v-alert>
                </v-card-text>

                <!-- OFFER ALWAYS AND STEP 7: SCHLIESSEN/SPEICHERN-->
                <v-card-actions>
                    <div class="d-flex flex-row align-center justify-space-between w-100">
                        <its-menu-button subtitle="Abbruch" icon="mdi-close" color="warning" @click="finished" v-if="step <= 7" />
                        <its-menu-button :subtitle="data.id ? 'Speichern' : 'Erstellen'" icon="mdi-check" color="success" @click="doCreateOffer(data)" v-if="step == 7" />
                    </div>
                </v-card-actions>
            </v-form>
        </v-card>
    </v-card-text>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import { useUserStore } from '@/stores/tutoring/UserStore'
import { useSubjectStore } from '@/stores/tutoring/SubjectStore'
import { useOfferStore } from '@/stores/tutoring/OfferStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },
    props: ['offer'],
    emits: ['finished'],

    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.userStore = useUserStore()
        this.subjectStore = useSubjectStore()
        this.offerStore = useOfferStore()
        await this.tutoringStore.loadAuth()
        await this.subjectStore.index()
        this.createOffer(this.offer)
        this.error = null
        this.is_loaded = true
    },

    async mounted() {},

    unmounted() {},

    data() {
        return {
            tutoringStore: null,
            userStore: null,
            subjectStore: null,
            offerStore: null,
            is_valid: false,
            is_password_visible: false,
            is_password_visible_confirm: false,
            selectedSubject: null,
            selectedSubjectId: null,
            step: 0,
            is_loaded: false,
            message: [],
            dateOnly: null,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['auth', 'action', 'config']),
        ...mapWritableState(useUserStore, ['data']),
        ...mapWritableState(useSubjectStore, ['subjects']),
        ...mapWritableState(useOfferStore, ['error']),

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

        dateOnly: {
            get() {
                if (!this.data.active_until) return null
                // Erstelle Date ohne Zeitzone-Probleme
                const [year, month, day] = this.data.active_until.split('-')
                return new Date(year, month - 1, day)
            },
            set(value) {
                if (value) {
                    const year = value.getFullYear()
                    const month = String(value.getMonth() + 1).padStart(2, '0')
                    const day = String(value.getDate()).padStart(2, '0')
                    this.data.active_until = `${year}-${month}-${day}`
                } else {
                    this.data.active_until = null
                }
            },
        },

        formattedActiveUntil() {
            if (!this.data.active_until) return ''

            const date = new Date(this.data.active_until)
            const weekdays = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag']
            const weekday = weekdays[date.getDay()]

            const day = String(date.getDate()).padStart(2, '0')
            const month = String(date.getMonth() + 1).padStart(2, '0')
            const year = date.getFullYear()

            return `${day}.${month}.${year} (${weekday})`
        },
    },

    watch: {
        selectedSubjectId() {
            if (this.selectedSubjectId) {
                this.selectedSubject = this.subjects.find((subject) => subject.id == this.selectedSubjectId)
                this.data.title = 'Biete ' + this.selectedSubject.long_name + ' Nachhilfe'
            } else {
                this.selectedSubject = null
            }
        },
    },

    methods: {
        finished() {
            this.$emit('finished')
            this.action = ''
        },

        async nextStep(item) {
            switch (item) {
                case 'subject':
                    if (!this.selectedSubject) return
                    this.data.subject_id = this.selectedSubject.id
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
                case 'active_until':
                    if (this.data.is_active_until) {
                        if (!this.data.active_until) {
                            this.message[this.step] = 'Bitte wähle ein Datum aus, bis zu dem das Angebot gültig ist.'
                            return
                        }

                        const selectedDate = new Date(this.data.active_until)
                        const today = new Date()
                        today.setHours(0, 0, 0, 0)
                        selectedDate.setHours(0, 0, 0, 0)

                        if (selectedDate < today) {
                            this.message[this.step] = 'Das gewählte Datum liegt in der Vergangenheit. Bitte wähle ein zukünftiges Datum.'
                            return
                        }
                    } else {
                        this.data.active_until = null
                    }

                    this.message = []
                    this.step++
                    break
                case 'more_infos':
                    this.message = []
                    if (!this.selectedSubject.must_be_accepted) {
                        this.step += 2
                        return
                    }
                    this.step++
                    break
                case 'mentor':
                    if (!this.data.email_mentor) {
                        this.message[this.step] = 'Bitte eine/n Lehrer:in zur Bestätigung auswählen.'
                        return
                    }
                    this.message = []
                    this.step++
                    break
                case 'visible_for_other_schools':
                    this.message = []
                    this.step++
                    break
            }
        },
        selectSubject(subject) {
            /*
            if (!this.selectedSubject) {
                this.selectedSubject = subject
                this.selectedSubjectId = subject.id
                this.data.title = 'Biete ' + subject.long_name + ' Nachhilfe'
            } else {
                this.selectedSubject = null
                this.selectedSubjectId = null
            }
                */
        },
        createOffer(offer) {
            if (!offer) {
                this.data = {
                    title: '',
                    description: '',
                    subject_id: null,
                    classes: { 1: false, 2: false, 3: false, 4: false, 5: false, 6: false, 7: false, 8: false, 9: false },
                    active_until: null,
                    is_active_until: false,
                    is_group: false,
                    max_group_members: 2,
                    price_per_hour: 0,
                    email_mentor: '',
                }
                this.selectedSubject = null
                this.selectedSubjectId = null
                this.step = 0 // Start bei Step 0 für neues Angebot
                return
            }

            // Offer vorhanden - bearbeiten
            this.data = {
                id: offer.id,
                title: offer.title ?? '',
                description: offer.description ?? '',
                subject_id: offer.subject?.id ?? null, // ← Auch hier sicher mit ?.
                classes: offer.classes ?? { 1: false, 2: false, 3: false, 4: false, 5: false, 6: false, 7: false, 8: false, 9: false },
                active_until: offer.active_until ?? null,
                is_active_until: offer.active_until ? true : false,
                is_group: offer.is_group ?? false,
                max_group_members: offer.max_group_members ?? 2,
                price_per_hour: parseFloat(offer.price_per_hour) ?? 0,
                email_mentor: offer.email_mentor ?? '',
            }

            this.selectedSubject = this.subjects.find((subject) => subject.id == offer.subject?.id)
            this.selectedSubjectId = offer.subject?.id
            this.step = 0 // Start bei Step 6 für Bearbeitung (Übersicht)
        },

        async doCreateOffer(data) {
            if (data.id) {
                if (!(await this.offerStore.update(data))) return
            } else {
                if (!(await this.offerStore.store(data))) return
            }

            this.step++
        },
    },
}
</script>
