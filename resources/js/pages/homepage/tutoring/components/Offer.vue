<template>
    <div class="offer-wizard" v-if="is_loaded">
        <!-- Section Header -->
        <div class="section-header">
            <div class="header-icon">
                <v-icon size="28" color="white">mdi-book-plus</v-icon>
            </div>
            <div class="header-text">
                <h2 class="section-title">{{ data.id ? 'Angebot bearbeiten' : 'Nachhilfe anbieten' }}</h2>
                <p class="section-subtitle">{{ getStepDescription() }}</p>
            </div>
        </div>

        <!-- Progress Indicator -->
        <div class="progress-section" v-if="step < 8">
            <div class="progress-bar">
                <div class="progress-fill" :style="{ width: ((step + 1) / 8) * 100 + '%' }"></div>
            </div>
            <div class="progress-text">Schritt {{ step + 1 }} von 8</div>
        </div>

        <!-- Offer Summary Card (visible from step 1) -->
        <div class="summary-card" v-if="step >= 1">
            <div class="summary-header">
                <v-icon size="20" class="mr-2">mdi-clipboard-list</v-icon>
                Zusammenfassung
            </div>
            <div class="summary-content">
                <div class="summary-item" v-if="step >= 1">
                    <span class="summary-label">Fach:</span>
                    <span class="summary-value">{{ selectedSubject.long_name }} ({{ selectedSubject.short_name }})</span>
                </div>
                <div class="summary-item" v-if="step >= 2">
                    <span class="summary-label">Titel:</span>
                    <span class="summary-value">{{ data.title }}</span>
                </div>
                <div class="summary-item" v-if="step >= 2 && data.description">
                    <span class="summary-label">Beschreibung:</span>
                    <span class="summary-value summary-description">{{ data.description }}</span>
                </div>
                <div class="summary-item" v-if="step >= 3 && (unterstufeClasses.length > 0 || oberstufeClasses.length > 0)">
                    <span class="summary-label">Klassen:</span>
                    <div class="summary-chips">
                        <span class="chip chip-green" v-for="cls in unterstufeClasses" :key="cls">{{ cls }}</span>
                        <span class="chip chip-orange" v-for="cls in oberstufeClasses" :key="cls">{{ cls }}</span>
                    </div>
                </div>
                <div class="summary-item" v-if="step >= 4">
                    <span class="summary-label">Gültig bis:</span>
                    <span class="summary-value">{{ data.active_until ? formattedActiveUntil : 'Unbegrenzt' }}</span>
                </div>
                <div class="summary-item" v-if="step >= 5">
                    <span class="summary-label">Art:</span>
                    <span class="summary-value">
                        {{ data.is_group ? 'Gruppenunterricht (max. ' + data.max_group_members + ')' : 'Einzelunterricht' }}
                    </span>
                </div>
                <div class="summary-item" v-if="step >= 5">
                    <span class="summary-label">Preis:</span>
                    <span class="summary-value">{{ data.price_per_hour }} Euro/Stunde</span>
                </div>
                <div class="summary-item" v-if="step >= 6 && selectedSubject.must_be_accepted">
                    <span class="summary-label">Bestätigung durch:</span>
                    <span class="summary-value">{{ data.email_mentor }}</span>
                </div>
                <div class="summary-item" v-if="step >= 7">
                    <span class="summary-label">Sichtbarkeit:</span>
                    <span class="summary-value" :class="data.visible_for_other_schools ? 'text-success' : 'text-grey'">
                        <v-icon size="16" class="mr-1">{{ data.visible_for_other_schools ? 'mdi-check-circle' : 'mdi-close-circle' }}</v-icon>
                        {{ data.visible_for_other_schools ? 'Für andere Schulen sichtbar' : 'Nur eigene Schule' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Wizard Form -->
        <v-form ref="form" v-model="is_valid" @submit.prevent="doCreateOffer(data)">
            <!-- Step 0: Subject Selection -->
            <div class="step-card" v-if="step == 0">
                <div class="step-header">
                    <div class="step-icon">
                        <v-icon size="24">mdi-book-open-variant</v-icon>
                    </div>
                    <div class="step-title">Wähle Dein Fach</div>
                </div>
                <p class="step-description">In welchem Fach möchtest Du Nachhilfe anbieten?</p>

                <div class="subject-grid">
                    <div
                        v-for="subject in subjects"
                        :key="subject.id"
                        class="subject-chip"
                        :class="{ 'subject-selected': selectedSubjectId === subject.id }"
                        @click="selectedSubjectId = subject.id"
                    >
                        <span class="subject-short">{{ subject.short_name }}</span>
                        <span class="subject-name">{{ subject.long_name }}</span>
                    </div>
                </div>

                <div class="step-actions">
                    <div></div>
                    <v-btn color="primary" variant="flat" size="large" rounded="lg" @click="nextStep('subject')" :disabled="!selectedSubject">
                        Weiter
                        <v-icon end>mdi-arrow-right</v-icon>
                    </v-btn>
                </div>
            </div>

            <!-- Step 1: Title & Description -->
            <div class="step-card" v-if="step == 1">
                <div class="step-header">
                    <div class="step-icon">
                        <v-icon size="24">mdi-text-box</v-icon>
                    </div>
                    <div class="step-title">Titel & Beschreibung</div>
                </div>
                <p class="step-description">Gib Deinem Angebot einen aussagekräftigen Titel und eine Beschreibung.</p>

                <v-text-field
                    autofocus
                    v-model="data.title"
                    label="Titel"
                    :rules="[required(), maxLength(255)]"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-format-title"
                />
                <v-textarea
                    v-model="data.description"
                    label="Beschreibung (optional)"
                    :rules="[maxLength(1024)]"
                    variant="outlined"
                    rows="4"
                    prepend-inner-icon="mdi-text"
                    hint="Beschreibe, was Du anbietest und wie Du helfen kannst"
                />

                <v-alert type="warning" variant="tonal" rounded="lg" v-if="message[1]" class="mt-3">{{ message[1] }}</v-alert>

                <div class="step-actions">
                    <v-btn variant="outlined" color="grey" size="large" rounded="lg" @click="step--">
                        <v-icon start>mdi-arrow-left</v-icon>
                        Zurück
                    </v-btn>
                    <v-btn color="primary" variant="flat" size="large" rounded="lg" @click="nextStep('title')">
                        Weiter
                        <v-icon end>mdi-arrow-right</v-icon>
                    </v-btn>
                </div>
            </div>

            <!-- Step 2: Classes -->
            <div class="step-card" v-if="step == 2">
                <div class="step-header">
                    <div class="step-icon">
                        <v-icon size="24">mdi-account-school</v-icon>
                    </div>
                    <div class="step-title">Zielgruppe</div>
                </div>
                <p class="step-description">Für welche Klassen ist Deine Nachhilfe gedacht?</p>

                <div class="classes-section">
                    <div class="class-group">
                        <div class="class-group-title">
                            <span class="group-badge badge-green">Unterstufe</span>
                        </div>
                        <div class="class-options">
                            <div
                                v-for="classNumber in 4"
                                :key="classNumber"
                                class="class-option"
                                :class="{ 'class-selected': data.classes[classNumber] }"
                                @click="data.classes[classNumber] = !data.classes[classNumber]"
                            >
                                <v-icon size="18" v-if="data.classes[classNumber]">mdi-check</v-icon>
                                <span>{{ classNumber }}. Klasse</span>
                            </div>
                        </div>
                    </div>

                    <div class="class-group">
                        <div class="class-group-title">
                            <span class="group-badge badge-orange">Oberstufe</span>
                        </div>
                        <div class="class-options">
                            <div
                                v-for="classNumber in 5"
                                :key="classNumber + 4"
                                class="class-option"
                                :class="{ 'class-selected': data.classes[classNumber + 4] }"
                                @click="data.classes[classNumber + 4] = !data.classes[classNumber + 4]"
                            >
                                <v-icon size="18" v-if="data.classes[classNumber + 4]">mdi-check</v-icon>
                                <span>{{ classNumber + 4 }}. Klasse</span>
                            </div>
                        </div>
                    </div>
                </div>

                <v-alert type="warning" variant="tonal" rounded="lg" v-if="message[2]" class="mt-3">{{ message[2] }}</v-alert>

                <div class="step-actions">
                    <v-btn variant="outlined" color="grey" size="large" rounded="lg" @click="step--">
                        <v-icon start>mdi-arrow-left</v-icon>
                        Zurück
                    </v-btn>
                    <v-btn color="primary" variant="flat" size="large" rounded="lg" @click="nextStep('classes')">
                        Weiter
                        <v-icon end>mdi-arrow-right</v-icon>
                    </v-btn>
                </div>
            </div>

            <!-- Step 3: Validity -->
            <div class="step-card" v-if="step == 3">
                <div class="step-header">
                    <div class="step-icon">
                        <v-icon size="24">mdi-calendar-clock</v-icon>
                    </div>
                    <div class="step-title">Gültigkeit</div>
                </div>
                <p class="step-description">Wie lange soll Dein Angebot verfügbar sein?</p>

                <div class="validity-toggle">
                    <div
                        class="toggle-option"
                        :class="{ 'toggle-selected': !data.is_active_until }"
                        @click="data.is_active_until = false"
                    >
                        <v-icon size="24">mdi-infinity</v-icon>
                        <span>Unbegrenzt</span>
                    </div>
                    <div
                        class="toggle-option"
                        :class="{ 'toggle-selected': data.is_active_until }"
                        @click="data.is_active_until = true"
                    >
                        <v-icon size="24">mdi-calendar-end</v-icon>
                        <span>Bis Datum</span>
                    </div>
                </div>

                <div class="date-picker-wrapper" v-if="data.is_active_until">
                    <v-date-picker
                        color="primary"
                        v-model="dateOnly"
                        :min="new Date().toISOString().split('T')[0]"
                    />
                </div>

                <v-alert type="warning" variant="tonal" rounded="lg" v-if="message[3]" class="mt-3">{{ message[3] }}</v-alert>

                <div class="step-actions">
                    <v-btn variant="outlined" color="grey" size="large" rounded="lg" @click="step--">
                        <v-icon start>mdi-arrow-left</v-icon>
                        Zurück
                    </v-btn>
                    <v-btn color="primary" variant="flat" size="large" rounded="lg" @click="nextStep('active_until')">
                        Weiter
                        <v-icon end>mdi-arrow-right</v-icon>
                    </v-btn>
                </div>
            </div>

            <!-- Step 4: Group & Price -->
            <div class="step-card" v-if="step == 4">
                <div class="step-header">
                    <div class="step-icon">
                        <v-icon size="24">mdi-account-group</v-icon>
                    </div>
                    <div class="step-title">Details</div>
                </div>
                <p class="step-description">Ein paar letzte Angaben zu Deinem Angebot.</p>

                <div class="detail-section">
                    <div class="detail-label">Unterrichtsart</div>
                    <div class="type-toggle">
                        <div
                            class="toggle-option"
                            :class="{ 'toggle-selected': !data.is_group }"
                            @click="data.is_group = false"
                        >
                            <v-icon size="24">mdi-account</v-icon>
                            <span>Einzelunterricht</span>
                        </div>
                        <div
                            class="toggle-option"
                            :class="{ 'toggle-selected': data.is_group }"
                            @click="data.is_group = true"
                        >
                            <v-icon size="24">mdi-account-group</v-icon>
                            <span>Gruppenunterricht</span>
                        </div>
                    </div>

                    <div class="group-size" v-if="data.is_group">
                        <div class="detail-label">Maximale Gruppengröße</div>
                        <v-number-input
                            :reverse="false"
                            controlVariant="split"
                            :hideInput="false"
                            :inset="false"
                            :min="2"
                            :max="5"
                            v-model="data.max_group_members"
                            variant="outlined"
                        />
                    </div>
                </div>

                <div class="detail-section">
                    <div class="detail-label">Preis pro Stunde (Euro)</div>
                    <v-number-input
                        :reverse="false"
                        controlVariant="split"
                        :hideInput="false"
                        :inset="false"
                        :min="0"
                        :max="50"
                        v-model="data.price_per_hour"
                        variant="outlined"
                    />
                    <div class="price-hint" v-if="data.price_per_hour === 0">
                        <v-icon size="16" class="mr-1">mdi-heart</v-icon>
                        Kostenlos - super!
                    </div>
                </div>

                <div class="step-actions">
                    <v-btn variant="outlined" color="grey" size="large" rounded="lg" @click="step--">
                        <v-icon start>mdi-arrow-left</v-icon>
                        Zurück
                    </v-btn>
                    <v-btn color="primary" variant="flat" size="large" rounded="lg" @click="nextStep('more_infos')">
                        Weiter
                        <v-icon end>mdi-arrow-right</v-icon>
                    </v-btn>
                </div>
            </div>

            <!-- Step 5: Teacher Approval -->
            <div class="step-card" v-if="step == 5">
                <div class="step-header">
                    <div class="step-icon">
                        <v-icon size="24">mdi-account-check</v-icon>
                    </div>
                    <div class="step-title">Bestätigung</div>
                </div>
                <p class="step-description">Wähle eine Lehrkraft, die Dein Angebot bestätigen soll.</p>

                <v-autocomplete
                    label="Lehrer:in auswählen"
                    :items="selectedSubject.email_mentors"
                    v-model="data.email_mentor"
                    :rules="[required()]"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-account-tie"
                />

                <v-alert type="warning" variant="tonal" rounded="lg" v-if="message[5]" class="mt-3">{{ message[5] }}</v-alert>

                <div class="step-actions">
                    <v-btn variant="outlined" color="grey" size="large" rounded="lg" @click="step--">
                        <v-icon start>mdi-arrow-left</v-icon>
                        Zurück
                    </v-btn>
                    <v-btn color="primary" variant="flat" size="large" rounded="lg" @click="nextStep('mentor')">
                        Weiter
                        <v-icon end>mdi-arrow-right</v-icon>
                    </v-btn>
                </div>
            </div>

            <!-- Step 6: Visibility -->
            <div class="step-card" v-if="step == 6">
                <div class="step-header">
                    <div class="step-icon">
                        <v-icon size="24">mdi-eye</v-icon>
                    </div>
                    <div class="step-title">Sichtbarkeit</div>
                </div>
                <p class="step-description">
                    {{ auth.school_tool.may_visible_for_other_schools
                        ? 'Soll Dein Angebot auch für Schüler:innen anderer Schulen sichtbar sein?'
                        : 'Dieses Angebot gilt nur innerhalb Deiner Schule.'
                    }}
                </p>

                <div class="visibility-toggle" v-if="auth.school_tool.may_visible_for_other_schools">
                    <div
                        class="toggle-option toggle-wide"
                        :class="{ 'toggle-selected': !data.visible_for_other_schools }"
                        @click="data.visible_for_other_schools = false"
                    >
                        <v-icon size="28">mdi-school</v-icon>
                        <div class="toggle-text">
                            <span class="toggle-title">Nur meine Schule</span>
                            <span class="toggle-desc">Nur Schüler:innen Deiner Schule sehen das Angebot</span>
                        </div>
                    </div>
                    <div
                        class="toggle-option toggle-wide"
                        :class="{ 'toggle-selected': data.visible_for_other_schools }"
                        @click="data.visible_for_other_schools = true"
                    >
                        <v-icon size="28">mdi-earth</v-icon>
                        <div class="toggle-text">
                            <span class="toggle-title">Alle Schulen</span>
                            <span class="toggle-desc">Auch Schüler:innen anderer Schulen können Dich kontaktieren</span>
                        </div>
                    </div>
                </div>

                <div class="info-card" v-if="!auth.school_tool.may_visible_for_other_schools">
                    <v-icon size="24" class="mr-3">mdi-information</v-icon>
                    <span>Deine Schule erlaubt keine schulübergreifenden Angebote.</span>
                </div>

                <v-alert type="warning" variant="tonal" rounded="lg" v-if="message[6]" class="mt-3">{{ message[6] }}</v-alert>

                <div class="step-actions">
                    <v-btn variant="outlined" color="grey" size="large" rounded="lg" @click="step--">
                        <v-icon start>mdi-arrow-left</v-icon>
                        Zurück
                    </v-btn>
                    <v-btn color="primary" variant="flat" size="large" rounded="lg" @click="nextStep('visible_for_other_schools')">
                        Weiter
                        <v-icon end>mdi-arrow-right</v-icon>
                    </v-btn>
                </div>
            </div>

            <!-- Step 7: Confirm & Submit -->
            <div class="step-card" v-if="step == 7">
                <div class="step-header">
                    <div class="step-icon step-icon-success">
                        <v-icon size="24">mdi-check-all</v-icon>
                    </div>
                    <div class="step-title">Fertig!</div>
                </div>
                <p class="step-description">Überprüfe Deine Angaben in der Zusammenfassung oben und erstelle Dein Angebot.</p>

                <div class="submit-info">
                    <v-icon size="20" class="mr-2" color="primary">mdi-information</v-icon>
                    <span v-if="selectedSubject.must_be_accepted">Nach dem Erstellen wird Dein Angebot zur Bestätigung an die Lehrkraft gesendet.</span>
                    <span v-else>Dein Angebot wird sofort freigeschaltet und kann jederzeit online gestellt werden.</span>
                </div>
            </div>

            <!-- Step 8: Success -->
            <div class="step-card success-card" v-if="step == 8">
                <div class="success-icon">
                    <v-icon size="64" color="success">mdi-check-circle</v-icon>
                </div>
                <h3 class="success-title">{{ data.id ? 'Angebot aktualisiert!' : 'Angebot erstellt!' }}</h3>
                <p class="success-message" v-if="selectedSubject.must_be_accepted">
                    Bitte warte nun auf die Freigabe durch die Lehrkraft. Du bekommst eine Benachrichtigung!
                </p>
                <p class="success-message" v-else>
                    Dein Angebot wurde erfolgreich {{ data.id ? 'aktualisiert' : 'erstellt' }}. Es kann jederzeit online gestellt werden!
                </p>
                <v-btn color="success" variant="flat" size="large" rounded="lg" @click="finished" class="mt-4">
                    <v-icon start>mdi-check</v-icon>
                    Fertig
                </v-btn>
            </div>

            <!-- Error -->
            <div class="error-section" v-if="error">
                <v-alert type="error" variant="tonal" rounded="lg">
                    {{ error?.response?.data?.message + ' (' + error?.response?.status + ')' }}
                </v-alert>
            </div>

            <!-- Bottom Actions (visible in step 7) -->
            <div class="bottom-actions" v-if="step == 7">
                <v-btn variant="outlined" color="grey" size="large" rounded="lg" @click="finished">
                    <v-icon start>mdi-close</v-icon>
                    Abbrechen
                </v-btn>
                <v-btn color="success" variant="flat" size="x-large" rounded="lg" @click="doCreateOffer(data)">
                    <v-icon start>mdi-check</v-icon>
                    {{ data.id ? 'Speichern' : 'Angebot erstellen' }}
                </v-btn>
            </div>

            <!-- Cancel Button (always visible except step 8) -->
            <div class="cancel-section" v-if="step < 7">
                <v-btn variant="text" color="grey" @click="finished">
                    <v-icon start>mdi-close</v-icon>
                    Abbrechen
                </v-btn>
            </div>
        </v-form>
    </div>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import { useUserStore } from '@/stores/tutoring/UserStore'
import { useSubjectStore } from '@/stores/tutoring/SubjectStore'
import { useOfferStore } from '@/stores/tutoring/OfferStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },
    props: ['offer'],
    emits: ['finished'],
    components: {},

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
        getStepDescription() {
            const descriptions = [
                'Schritt 1: Fach auswählen',
                'Schritt 2: Titel & Beschreibung',
                'Schritt 3: Zielgruppe festlegen',
                'Schritt 4: Gültigkeitsdauer',
                'Schritt 5: Details angeben',
                'Schritt 6: Lehrkraft wählen',
                'Schritt 7: Sichtbarkeit',
                'Schritt 8: Überprüfen & Erstellen',
                'Erfolgreich erstellt!'
            ]
            return descriptions[this.step] || ''
        },

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
                this.step = 0
                return
            }

            this.data = {
                id: offer.id,
                title: offer.title ?? '',
                description: offer.description ?? '',
                subject_id: offer.subject?.id ?? null,
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
            this.step = 0
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

<style scoped>
.offer-wizard {
    max-width: 700px;
    margin: 0 auto;
}

/* Section Header */
.section-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    padding: 20px 24px;
    background: linear-gradient(135deg, #3AAA35 0%, #2d8a2a 100%);
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
    font-size: 1.4rem;
    font-weight: 700;
    margin: 0;
    line-height: 1.2;
}

.section-subtitle {
    font-size: 0.9rem;
    opacity: 0.9;
    margin: 4px 0 0 0;
}

/* Progress */
.progress-section {
    margin-bottom: 20px;
}

.progress-bar {
    height: 6px;
    background: #e0e0e0;
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #3AAA35, #4BC044);
    transition: width 0.4s ease;
}

.progress-text {
    text-align: center;
    font-size: 0.8rem;
    color: #78909C;
    margin-top: 6px;
}

/* Summary Card */
.summary-card {
    background: rgba(58, 170, 53, 0.06);
    border: 1px solid rgba(58, 170, 53, 0.15);
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 20px;
}

.summary-header {
    display: flex;
    align-items: center;
    font-weight: 600;
    font-size: 0.9rem;
    color: #2E7D32;
    margin-bottom: 12px;
}

.summary-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.summary-item {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 0.85rem;
}

.summary-label {
    color: #607D8B;
    min-width: 100px;
}

.summary-value {
    color: #263238;
    font-weight: 500;
}

.summary-description {
    white-space: pre-line;
}

.summary-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.chip {
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 500;
}

.chip-green {
    background: rgba(58, 170, 53, 0.15);
    color: #2E7D32;
}

.chip-orange {
    background: rgba(243, 146, 0, 0.15);
    color: #E65100;
}

/* Step Card */
.step-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    margin-bottom: 16px;
}

.step-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 8px;
}

.step-icon {
    width: 44px;
    height: 44px;
    background: rgba(58, 170, 53, 0.1);
    color: #3AAA35;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.step-icon-success {
    background: rgba(58, 170, 53, 0.15);
    color: #2E7D32;
}

.step-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #263238;
}

.step-description {
    font-size: 0.9rem;
    color: #607D8B;
    margin: 0 0 20px 0;
}

.step-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

/* Subject Grid */
.subject-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 10px;
}

.subject-chip {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 14px 10px;
    background: #f5f5f5;
    border: 2px solid transparent;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
}

.subject-chip:hover {
    background: #e8f5e9;
    border-color: #81C784;
}

.subject-selected {
    background: #e8f5e9;
    border-color: #3AAA35;
    box-shadow: 0 4px 12px rgba(58, 170, 53, 0.2);
}

.subject-short {
    font-weight: 700;
    font-size: 1rem;
    color: #3AAA35;
}

.subject-name {
    font-size: 0.75rem;
    color: #607D8B;
    margin-top: 2px;
}

/* Classes */
.classes-section {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.class-group-title {
    margin-bottom: 10px;
}

.group-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-green {
    background: rgba(58, 170, 53, 0.15);
    color: #2E7D32;
}

.badge-orange {
    background: rgba(243, 146, 0, 0.15);
    color: #E65100;
}

.class-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.class-option {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    background: #f5f5f5;
    border: 2px solid transparent;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.class-option:hover {
    background: #e3f2fd;
}

.class-selected {
    background: #e8f5e9;
    border-color: #3AAA35;
    color: #2E7D32;
    font-weight: 600;
}

/* Toggle Options */
.validity-toggle, .type-toggle, .visibility-toggle {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.toggle-option {
    flex: 1;
    min-width: 140px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 20px 16px;
    background: #f5f5f5;
    border: 2px solid transparent;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
}

.toggle-option:hover {
    background: #e8f5e9;
}

.toggle-selected {
    background: #e8f5e9;
    border-color: #3AAA35;
    color: #2E7D32;
}

.toggle-wide {
    flex-direction: row;
    text-align: left;
    gap: 16px;
    padding: 16px 20px;
}

.toggle-text {
    display: flex;
    flex-direction: column;
}

.toggle-title {
    font-weight: 600;
    font-size: 0.95rem;
}

.toggle-desc {
    font-size: 0.8rem;
    color: #78909C;
}

.toggle-selected .toggle-desc {
    color: #4CAF50;
}

/* Date Picker */
.date-picker-wrapper {
    display: flex;
    justify-content: center;
    margin-top: 16px;
}

/* Detail Section */
.detail-section {
    margin-bottom: 24px;
}

.detail-label {
    font-weight: 600;
    font-size: 0.9rem;
    color: #37474F;
    margin-bottom: 10px;
}

.group-size {
    margin-top: 16px;
}

.price-hint {
    display: flex;
    align-items: center;
    margin-top: 8px;
    font-size: 0.85rem;
    color: #E91E63;
}

/* Info Card */
.info-card {
    display: flex;
    align-items: center;
    padding: 16px;
    background: rgba(33, 150, 243, 0.08);
    border-radius: 12px;
    color: #1565C0;
    font-size: 0.9rem;
}

/* Submit Info */
.submit-info {
    display: flex;
    align-items: flex-start;
    padding: 16px;
    background: #f5f5f5;
    border-radius: 12px;
    font-size: 0.9rem;
    color: #546E7A;
}

/* Success Card */
.success-card {
    text-align: center;
    padding: 48px 28px;
}

.success-icon {
    margin-bottom: 16px;
}

.success-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2E7D32;
    margin: 0 0 12px 0;
}

.success-message {
    font-size: 1rem;
    color: #607D8B;
    margin: 0;
}

/* Bottom Actions */
.bottom-actions {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-top: 16px;
}

/* Error Section */
.error-section {
    margin-top: 16px;
}

/* Cancel Section */
.cancel-section {
    text-align: center;
    margin-top: 16px;
}

/* Responsive */
@media (max-width: 600px) {
    .section-header {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }

    .step-card {
        padding: 20px;
    }

    .subject-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .toggle-option {
        min-width: 100%;
    }

    .toggle-wide {
        flex-direction: column;
        text-align: center;
    }

    .step-actions, .bottom-actions {
        flex-direction: column;
        gap: 12px;
    }

    .step-actions .v-btn, .bottom-actions .v-btn {
        width: 100%;
    }
}
</style>
