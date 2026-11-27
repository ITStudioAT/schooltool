<template>
    <div v-if="studentEntryReady" :class="showStudentEntryDesign ? 'school-entry student-entry' : 'lernportal-page student-workspace'">
        <SchoolEntryHeader v-if="showStudentEntryDesign">
            <button type="button" class="school-entry-back" @click="$router.push('/')">
                <v-icon icon="mdi-arrow-left" size="19" aria-hidden="true" />
                Zur Startseite
            </button>
        </SchoolEntryHeader>

        <div :class="studentEntryContentClass">
            <section
                v-if="showStudentEntryDesign"
                class="school-entry-hero school-entry-hero--compact student-entry-hero"
                aria-labelledby="student-entry-title">
                <h1 id="student-entry-title">Unterricht</h1>
                <p>Überblick bewahren. Inhalte kennen. Noten erfahren.</p>
                <div v-if="school" class="student-entry-school">
                    <v-icon icon="mdi-school-outline" size="20" aria-hidden="true" />
                    <strong>{{ school.long_name }}</strong>
                </div>
                <p class="student-entry-date">{{ weekdayLabel }}, {{ dateLabel }}</p>
            </section>

            <section v-else class="hero">
                <div class="hero-card">
                    <div class="hero-topline">
                        <v-btn class="back-btn" variant="text" prepend-icon="mdi-arrow-left" @click="$router.push('/')">Zur Startseite</v-btn>
                    </div>

                    <h1 class="hero-title">Unterricht</h1>
                    <p class="hero-subtitle">Überblick bewahren. Inhalte kennen. Noten erfahren.</p>

                    <div class="hero-badges">
                        <span class="hero-badge">{{ weekdayLabel }}</span>
                        <span class="hero-badge dark">{{ dateLabel }}</span>
                        <span v-if="school" class="hero-badge">{{ school.long_name }}</span>
                    </div>
                </div>
            </section>

            <section v-if="!school && showStudentEntryDesign" class="school-entry-selection student-entry-selection" aria-labelledby="student-schools-title">
                <div class="student-entry-schools-head">
                    <h2 id="student-schools-title">Verfügbare Schulen</h2>
                    <span class="student-entry-school-count">{{ schools?.length || 0 }}</span>
                </div>
                <p>Diese Schulen sind aktuell für den Unterrichtsbereich verfügbar.</p>

                <template v-if="hasManySchools">
                    <v-form ref="schoolSearchForm" v-model="is_school_search_valid" class="student-entry-search">
                        <v-text-field
                            v-model="school_search"
                            label="Schule suchen (Name oder Kürzel)"
                            variant="outlined"
                            prepend-inner-icon="mdi-magnify"
                            clearable
                            :rules="[maxLength(255)]"
                            hide-details="auto"
                            @update:modelValue="validateSchoolSearchForm" />
                    </v-form>
                    <p v-if="!school_search?.trim()" class="student-entry-search-message">
                        Bitte Suchtext eingeben. Es wird in Name und Kürzel gesucht.
                    </p>
                    <p v-else-if="!filteredSchoolsForSearch.length" class="student-entry-search-message">
                        Keine passende Schule gefunden.
                    </p>
                </template>

                <ul v-if="showSchoolsGrid || filteredSchoolsForSearch.length" class="school-entry-list">
                    <li v-for="schoolItem in (showSchoolsGrid ? schools : filteredSchoolsForSearch)" :key="schoolItem.id">
                        <button type="button" class="school-entry-card" @click="selected_school_id = schoolItem.id">
                            <span class="school-entry-logo">
                                <v-icon icon="mdi-school-outline" size="27" aria-hidden="true" />
                            </span>
                            <span class="school-entry-school">
                                <strong>{{ schoolItem.long_name }}</strong>
                                <span v-if="schoolItem.short_name">{{ schoolItem.short_name }}</span>
                            </span>
                            <span class="school-entry-arrow" aria-hidden="true">
                                <v-icon icon="mdi-arrow-right" size="20" />
                            </span>
                        </button>
                    </li>
                </ul>

                <p v-else-if="schools?.length && !hasManySchools" class="student-entry-search-message">Bitte eine Schule auswählen.</p>
                <p v-else-if="!schools?.length" class="student-entry-search-message">Keine verfügbaren Schulen gefunden.</p>
            </section>

            <section v-else-if="!school" class="schools-cover">
                <div class="schools-card">
                    <div class="schools-head">
                        <v-icon size="24">mdi-school-outline</v-icon>
                        <h2>Verfügbare Schulen</h2>
                        <v-chip class="schools-count" size="small">{{ schools?.length || 0 }}</v-chip>
                    </div>

                    <p class="schools-copy">Diese Schulen sind aktuell für den Unterrichtsbereich verfügbar.</p>

                    <div v-if="showSchoolsGrid" class="schools-grid">
                        <button
                            v-for="schoolItem in schools"
                            :key="schoolItem.id"
                            type="button"
                            class="school-item"
                            :class="{ 'is-active': Number(selected_school_id) === Number(schoolItem.id) }"
                            @click="selected_school_id = schoolItem.id">
                            <div class="school-main">
                                <span class="school-long">{{ schoolItem.long_name }}</span>
                                <span class="school-short">{{ schoolItem.short_name }}</span>
                            </div>
                        </button>
                    </div>

                    <template v-else-if="hasManySchools">
                        <v-form ref="schoolSearchForm" v-model="is_school_search_valid">
                            <v-text-field
                                v-model="school_search"
                                label="Schule suchen (Name oder Kürzel)"
                                variant="outlined"
                                prepend-inner-icon="mdi-magnify"
                                class="school-search-field"
                                clearable
                                :rules="[maxLength(255)]"
                                hide-details="auto"
                                @update:modelValue="validateSchoolSearchForm" />
                        </v-form>

                        <div v-if="!school_search?.trim()" class="schools-hint">
                            <v-icon size="22">mdi-information-outline</v-icon>
                            <span>Bitte Suchtext eingeben. Es wird in Name und Kürzel gesucht.</span>
                        </div>

                        <div v-else-if="filteredSchoolsForSearch.length" class="schools-grid">
                            <button
                                v-for="schoolItem in filteredSchoolsForSearch"
                                :key="schoolItem.id"
                                type="button"
                                class="school-item"
                                :class="{ 'is-active': Number(selected_school_id) === Number(schoolItem.id) }"
                                @click="selected_school_id = schoolItem.id">
                                <div class="school-main">
                                    <span class="school-long">{{ schoolItem.long_name }}</span>
                                    <span class="school-short">{{ schoolItem.short_name }}</span>
                                </div>
                            </button>
                        </div>

                        <div v-else class="schools-empty">
                            <v-icon size="22">mdi-alert-circle-outline</v-icon>
                            <span>Keine passende Schule gefunden.</span>
                        </div>
                    </template>

                    <div v-else-if="schools?.length" class="schools-hint">
                        <v-icon size="22">mdi-information-outline</v-icon>
                        <span>Bitte eine Schule auswählen.</span>
                    </div>

                    <div v-else class="schools-empty">
                        <v-icon size="22">mdi-alert-circle-outline</v-icon>
                        <span>Keine verfügbaren Schulen gefunden.</span>
                    </div>
                </div>
            </section>

            <!-- Email Eingabe -->
            <section v-if="school && login_step === 'email'" class="login-cover" aria-labelledby="student-entry-email-title">
                <div class="login-card">
                    <div v-if="showStudentEntryDesign" class="login-head student-entry-login-head">
                        <v-icon icon="mdi-account-school-outline" size="26" aria-hidden="true" />
                        <h2 id="student-entry-email-title">Login-Bereich</h2>
                        <button type="button" class="school-entry-back student-entry-change-school" @click="changeSchool">
                            <v-icon icon="mdi-swap-horizontal" size="19" aria-hidden="true" />
                            Schule ändern
                        </button>
                    </div>
                    <template v-else>
                        <div class="login-head">
                            <v-icon size="26">mdi-account-school</v-icon>
                            <h2 id="student-entry-email-title">Login-Bereich</h2>
                        </div>
                        <div class="school-selected">
                            <div class="school-selected-info">
                                <v-icon size="22" color="success">mdi-school</v-icon>
                                <span class="school-selected-name">{{ school.long_name }}</span>
                            </div>
                            <v-btn
                                variant="tonal"
                                size="small"
                                color="warning"
                                rounded="pill"
                                prepend-icon="mdi-swap-horizontal"
                                @click="changeSchool">
                                Schule ändern
                            </v-btn>
                        </div>
                    </template>
                    <p class="login-copy">Gib jetzt deine E-Mail-Adresse ein.</p>

                    <v-form ref="loginForm" v-model="is_login_email_valid" @submit.prevent>
                        <div class="login-fields">
                            <v-text-field
                                v-model="login_email"
                                data-testid="student-login-email"
                                id="student-login-email"
                                autocomplete="username"
                                inputmode="email"
                                label="E-Mail"
                                variant="outlined"
                                density="comfortable"
                                prepend-inner-icon="mdi-email-outline"
                                :rules="[required(), mail(), maxLength(255)]"
                                hide-details="auto" />
                        </div>
                        <div class="login-actions">
                            <v-btn
                                data-testid="student-login-continue-password"
                                type="button"
                                class="student-entry-primary"
                                :color="showStudentEntryDesign ? undefined : 'warning'"
                                :rounded="showStudentEntryDesign ? undefined : 'pill'"
                                variant="flat"
                                :disabled="!canContinueWithEmail"
                                @click="continueWithPassword">
                                Weiter mit Kennwort
                            </v-btn>
                            <v-btn
                                data-testid="student-login-continue-code"
                                type="button"
                                class="student-entry-secondary"
                                :color="showStudentEntryDesign ? undefined : 'primary'"
                                :rounded="showStudentEntryDesign ? undefined : 'pill'"
                                variant="outlined"
                                :disabled="!canContinueWithEmail || !isCodeLoginAvailable"
                                @click="continueWithoutPassword">
                                Weiter ohne Kennwort
                            </v-btn>
                        </div>
                        <v-alert v-if="!isCodeLoginAvailable" class="mt-4" density="compact" type="warning" variant="tonal">
                            !Login mit Code derzeit nicht möglich
                        </v-alert>
                    </v-form>
                </div>
            </section>

            <!-- Code Eingabe (6-stellig) -->
            <section v-if="school && login_step === 'code_sent'" class="login-cover" aria-labelledby="student-entry-code-title">
                <div class="login-card">
                    <div class="login-head">
                        <v-icon size="26">mdi-key-variant</v-icon>
                        <h2 id="student-entry-code-title">Code eingeben</h2>
                    </div>
                    <p class="login-copy">
                        Ein 6-stelliger Code wurde an
                        <strong>{{ login_email }}</strong>
                        gesendet. Bitte gib den Code ein.
                    </p>

                    <v-form ref="codeForm" v-model="is_code_valid" @submit.prevent="submitCode">
                        <div class="login-fields">
                            <v-otp-input v-model="data.login_code" :length="6" variant="outlined" />
                        </div>
                        <div class="login-actions">
                            <v-btn
                                type="button"
                                class="student-entry-secondary"
                                :color="showStudentEntryDesign ? undefined : 'warning'"
                                :rounded="showStudentEntryDesign ? undefined : 'pill'"
                                variant="text"
                                @click="backToEmail">Zurück</v-btn>
                            <v-btn
                                type="submit"
                                class="student-entry-primary"
                                :color="showStudentEntryDesign ? undefined : 'success'"
                                :rounded="showStudentEntryDesign ? undefined : 'pill'"
                                variant="flat"
                                :disabled="!canSubmitCode || !isCodeLoginAvailable">
                                Code bestätigen
                            </v-btn>
                        </div>
                        <v-alert v-if="!isCodeLoginAvailable" class="mt-4" density="compact" type="warning" variant="tonal">
                            !Login mit Code derzeit nicht möglich
                        </v-alert>
                    </v-form>
                </div>
            </section>

            <!-- Kind auswählen (Elternzugang) -->
            <section v-if="school && login_step === 'select_student'" class="login-cover">
                <div class="login-card">
                    <div class="login-head">
                        <v-icon size="26">mdi-account-child-circle</v-icon>
                        <h2>Kind auswählen</h2>
                    </div>
                    <p class="login-copy">
                        Wähle das Kind aus, dessen Unterrichtsbereich du ansehen möchtest. Der Elternzugang ist schreibgeschützt.
                    </p>

                    <v-radio-group
                        v-model="selected_parent_student_id"
                        data-testid="parent-student-selection"
                        color="primary"
                        hide-details>
                        <div class="student-selection-list">
                            <div
                                v-for="student in data.students || []"
                                :key="student.id"
                                class="student-selection-item"
                                :class="{ 'is-active': Number(selected_parent_student_id) === Number(student.id) }">
                                <v-radio :value="student.id">
                                    <template #label>
                                        <span class="student-selection-label">
                                            <strong>{{ student.name }}</strong>
                                            <span>{{ student.schoolclass || 'Keine Klasse' }} · {{ student.age }} Jahre</span>
                                        </span>
                                    </template>
                                </v-radio>
                            </div>
                        </div>
                    </v-radio-group>

                    <div class="login-actions">
                        <v-btn type="button" color="warning" variant="text" rounded="pill" @click="handleParentStudentBack">
                            {{ isChangingParentStudent ? 'Abbrechen' : 'Zurück' }}
                        </v-btn>
                        <v-btn
                            data-testid="parent-student-submit"
                            type="button"
                            color="success"
                            variant="flat"
                            rounded="pill"
                            :disabled="!selected_parent_student_id"
                            @click="submitParentStudent">
                            Unterrichtsbereich öffnen
                        </v-btn>
                    </div>
                </div>
            </section>

            <!-- Passwort Eingabe -->
            <section v-if="school && login_step === 'enter_password'" class="login-cover" aria-labelledby="student-entry-password-title">
                <div class="login-card">
                    <div class="login-head">
                        <v-icon size="26">mdi-lock</v-icon>
                        <h2 id="student-entry-password-title">Passwort eingeben</h2>
                    </div>
                    <p class="login-copy">
                        Bitte gib dein Passwort für
                        <strong>{{ login_email }}</strong>
                        ein, um dich anzumelden.
                    </p>

                    <v-form ref="passwordForm" v-model="is_password_valid" @submit.prevent="submitPassword">
                        <div class="login-fields">
                            <v-text-field
                                v-model="data.password"
                                data-testid="student-login-password"
                                id="student-login-password"
                                autocomplete="current-password"
                                label="Passwort"
                                variant="outlined"
                                density="comfortable"
                                :type="show_password ? 'text' : 'password'"
                                prepend-inner-icon="mdi-lock-outline"
                                :append-inner-icon="show_password ? 'mdi-eye-off' : 'mdi-eye'"
                                :rules="[required(), minLength(8), maxLength(255)]"
                                hide-details="auto"
                                @click:append-inner="show_password = !show_password" />
                        </div>
                        <div class="login-actions">
                            <v-btn
                                type="button"
                                class="student-entry-secondary"
                                :color="showStudentEntryDesign ? undefined : 'warning'"
                                :rounded="showStudentEntryDesign ? undefined : 'pill'"
                                variant="text"
                                @click="backToEmail">Zurück</v-btn>
                            <v-btn
                                data-testid="student-login-submit-password"
                                type="submit"
                                class="student-entry-primary"
                                :color="showStudentEntryDesign ? undefined : 'success'"
                                :rounded="showStudentEntryDesign ? undefined : 'pill'"
                                variant="flat"
                                :disabled="!canSubmitPassword">
                                Anmelden
                            </v-btn>
                        </div>
                    </v-form>
                </div>
            </section>
        </div>

        <!-- Error Dialog -->
        <v-dialog v-model="show_error_dialog" max-width="500">
            <v-card :class="{ 'student-entry-error': showStudentEntryDesign }">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon color="error" icon="mdi-alert-circle" />
                    <span>Anmeldefehler</span>
                </v-card-title>
                <v-card-text class="pt-4">
                    {{ login_error }}
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" @click="closeErrorDialog">OK</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useStudentStore } from '@/stores/student/StudentStore'
import SchoolEntryHeader from '../index/SchoolEntryHeader.vue'
import { studentLoginReturn } from './studentTasksNavigation'
import '../../../../css/student.css'

export default {
    components: { SchoolEntryHeader },

    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.studentStore = useStudentStore()
        const isAuthenticated = await this.studentStore.getCurrentUser()
        if (isAuthenticated && this.user) {
            if (this.isChangingParentStudent && await this.openParentStudentSelection()) {
                this.studentEntryReady = true
                return
            }

            this.$router.replace(studentLoginReturn(this.$route?.query))
            return
        }
        await this.studentStore.loadConfig()
        this.selectSchool()
        this.studentEntryReady = true
    },

    data() {
        return {
            studentStore: null,
            studentEntryReady: false,
            school_search: '',
            login_email: '',
            login_code: '',
            login_password: '',
            login_step: 'email', // 'email', 'code_sent', 'select_student', 'enter_password'
            selected_parent_student_id: null,
            show_password: false,
            show_error_dialog: false,
            login_error: '',
            is_school_search_valid: true,
            is_login_email_valid: false,
            is_code_valid: false,
            is_password_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useStudentStore, ['config', 'schools', 'selected_school_id', 'school', 'data', 'user', 'viewer_type']),

        showStudentEntryDesign() {
            return this.config?.homepage_design_enabled === true && this.login_step !== 'select_student'
        },

        studentEntryContentClass() {
            if (!this.showStudentEntryDesign) return undefined

            return [
                'school-entry-shell',
                'school-entry-content',
                'student-entry-content',
                { 'student-entry-content--login': this.school },
            ]
        },

        isChangingParentStudent() {
            return this.viewer_type === 'parent' && String(this.$route.query.select_child || '') === '1'
        },

        maxSchoolsShown() {
            const raw = this.config?.config?.schooltool?.teaching_max_schools_shown
            const parsed = Number(raw)
            return Number.isFinite(parsed) && parsed > 0 ? parsed : 20
        },
        showSchoolsGrid() {
            return Array.isArray(this.schools) && this.schools.length > 0 && this.schools.length <= this.maxSchoolsShown
        },
        hasManySchools() {
            return Array.isArray(this.schools) && this.schools.length > this.maxSchoolsShown
        },
        filteredSchoolsForSearch() {
            if (!this.hasManySchools) return []

            const query = (this.school_search || '').trim().toLocaleLowerCase('de')
            if (!query) return []

            return this.schools.filter((item) => {
                const longName = String(item.long_name || '').toLocaleLowerCase('de')
                const shortName = String(item.short_name || '').toLocaleLowerCase('de')
                return longName.includes(query) || shortName.includes(query)
            })
        },
        canContinueWithEmail() {
            const value = String(this.login_email || '').trim()
            if (!value) return false
            return this.required()(value) === true && this.mail()(value) === true && this.maxLength(255)(value) === true
        },
        isCodeLoginAvailable() {
            return this.config?.health?.queue_working !== false
        },
        canSubmitCode() {
            const value = String(this.data.login_code || '').trim()
            return value.length === 6
        },
        canSubmitPassword() {
            const value = String(this.data.password || '').trim()
            if (!value) return false
            return this.required()(value) === true && this.minLength(8)(value) === true && this.maxLength(255)(value) === true
        },
        weekdayLabel() {
            return new Intl.DateTimeFormat('de-AT', { weekday: 'long' }).format(new Date())
        },
        dateLabel() {
            return new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date())
        },
    },

    watch: {
        selected_school_id(newId) {
            this.school = this.schools?.find((item) => Number(item.id) === Number(newId)) || null
        },
    },

    methods: {
        selectSchool(fallbackSchoolId = null) {
            const schoolShortName = String(this.$route.query.school || '').trim()
            const selectedSchoolFromUrl = schoolShortName
                ? this.schools?.find((item) => item.short_name === schoolShortName)
                : null

            if (selectedSchoolFromUrl) {
                this.selected_school_id = selectedSchoolFromUrl.id
                this.school = selectedSchoolFromUrl
            } else if (fallbackSchoolId) {
                this.selected_school_id = fallbackSchoolId
                this.school = this.schools?.find((item) => Number(item.id) === Number(fallbackSchoolId)) || null
            } else if (this.selected_school_id) {
                this.school = this.schools?.find((item) => Number(item.id) === Number(this.selected_school_id)) || null
            } else if (this.schools?.length === 1) {
                this.selected_school_id = this.schools[0].id
                this.school = this.schools[0]
            }
        },

        async openParentStudentSelection() {
            if (!(await this.studentStore.loadParentStudents())) return false
            if (!(await this.studentStore.loadConfig())) return false

            this.selectSchool(this.studentStore.data?.school_id)
            if (!this.school) return false

            this.selected_parent_student_id = this.studentStore.data?.selected_student_import_id ?? null
            this.login_step = 'select_student'

            return true
        },

        handleParentStudentBack() {
            if (this.isChangingParentStudent) {
                this.$router.push('/student/overview')
                return
            }

            this.backToEmail()
        },

        async validateSchoolSearchForm() {
            if (!this.$refs.schoolSearchForm) return
            this.is_school_search_valid = false
            await this.$refs.schoolSearchForm.validate()
        },

        async validateLoginForm() {
            this.is_login_email_valid = false
            await this.$refs.loginForm.validate()
            return this.is_login_email_valid
        },

        async continueWithPassword() {
            const isValid = await this.validateLoginForm()
            if (!isValid) return

            const data = {
                type: 'login_with_password',
                school_id: this.selected_school_id,
                email: this.login_email.trim(),
            }

            if (await this.studentStore.loginStepEmail(data)) {
                const status = this.studentStore.data?.status
                if (status === 'code_sent') {
                    this.login_step = 'code_sent'
                    this.login_code = ''
                } else if (status === 'enter_password') {
                    this.login_step = 'enter_password'
                    this.login_password = ''
                    this.show_password = false
                }
            }
        },

        async continueWithoutPassword() {
            if (!this.isCodeLoginAvailable) return

            const isValid = await this.validateLoginForm()
            if (!isValid) return

            const data = {
                type: 'login_without_password',
                school_id: this.selected_school_id,
                email: this.login_email.trim(),
            }

            if (await this.studentStore.loginStepEmail(data)) {
                const status = this.studentStore.data?.status
                if (status === 'code_sent') {
                    this.login_step = 'code_sent'
                    this.login_code = ''
                } else if (status === 'enter_password') {
                    this.login_step = 'enter_password'
                    this.login_password = ''
                    this.show_password = false
                }
            }
        },

        async validatePasswordForm() {
            this.is_password_valid = false
            await this.$refs.passwordForm.validate()
            return this.is_password_valid
        },

        async submitCode() {
            if (!this.isCodeLoginAvailable) return
            if (!this.canSubmitCode) return

            this.data.login_code.trim()

            if (await this.studentStore.loginStepCode(this.data)) {
                const status = this.studentStore.data?.status

                if (status === 'code_not_valid') {
                    this.login_error = 'Der eingegebene Code ist ungültig. Bitte versuche es erneut.'
                    this.show_error_dialog = true
                } else if (status === 'select_student') {
                    this.selected_parent_student_id = null
                    this.login_step = 'select_student'
                } else if (status === 'login_ok') {
                    // Erfolgreicher Login - Weiterleitung zum Unterrichtsbereich
                    this.$router.push(studentLoginReturn(this.$route?.query))
                }
            }
        },

        async submitParentStudent() {
            if (!this.selected_parent_student_id) return

            if (await this.studentStore.loginStepParentStudent(this.selected_parent_student_id)) {
                if (this.studentStore.data?.status === 'login_ok') {
                    this.$router.push(studentLoginReturn(this.$route?.query))
                }
            }
        },

        async submitPassword() {
            const isValid = await this.validatePasswordForm()
            if (!isValid) return

            if (await this.studentStore.loginStepPassword(this.data)) {
                const status = this.studentStore.data?.status

                if (status === 'password_not_valid') {
                    this.login_error = 'Das eingegebene Passwort ist ungültig. Bitte versuche es erneut.'
                    this.show_error_dialog = true
                } else if (status === 'login_ok') {
                    // Erfolgreicher Login - Weiterleitung zum Unterrichtsbereich
                    this.$router.push(studentLoginReturn(this.$route?.query))
                }
            }
        },

        closeErrorDialog() {
            this.show_error_dialog = false
            this.login_error = ''
            this.backToEmail()
        },

        backToEmail() {
            this.login_step = 'email'
            this.login_code = ''
            this.login_password = ''
            this.selected_parent_student_id = null
            this.show_password = false
        },

        changeSchool() {
            this.school = null
            this.selected_school_id = null
            this.login_step = 'email'
            this.login_email = ''
            this.login_code = ''
            this.login_password = ''
            this.selected_parent_student_id = null
            this.show_password = false
        },
    },
}
</script>

<style scoped>
.schools-cover button.school-item {
    text-align: start;
}

.student-entry-content--login {
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    align-items: start;
    gap: clamp(16px, 3vw, 32px);
}

.student-entry-content--login .student-entry-hero {
    min-height: 322px;
}

.student-entry-content--login .student-entry-hero::after {
    inset-inline-end: -40%;
}

.student-entry-hero .student-entry-date {
    margin-top: 22px;
    color: #61330f;
    font-size: 0.875rem;
}

.student-entry-school {
    display: flex;
    align-items: center;
    gap: 8px;
    justify-self: start;
    margin-top: 20px;
    padding: 8px 10px;
    border: 1px solid #10263a33;
    border-radius: 8px;
    background: #fff3ddbf;
    font-size: 0.9375rem;
    overflow-wrap: anywhere;
}

.student-entry-schools-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.student-entry .student-entry-schools-head h2 {
    margin: 0;
}

.student-entry-school-count {
    padding: 2px 9px;
    border-radius: 7px;
    background: #e8edf1;
    color: var(--entry-muted);
    font-weight: 650;
}

.student-entry-selection > p {
    margin-bottom: 16px;
    color: var(--entry-muted);
}

.student-entry-search {
    max-width: 480px;
    margin-block: 16px;
}

.student-entry-search-message {
    padding: 10px 12px;
    border: 1px solid var(--entry-border);
    border-radius: 8px;
    background: #fff;
}

.student-entry .login-cover {
    width: 100%;
    max-width: none;
    min-width: 0;
    margin: 0;
}

.student-entry .login-card {
    padding: clamp(16px, 3.35vw, 40px);
    border: 1px solid var(--entry-border);
    border-radius: 16px;
    background: #fff;
    box-shadow: none;
}

.student-entry .login-head {
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
    color: var(--entry-ink);
}

.student-entry .login-head > .v-icon {
    color: var(--entry-accent);
}

.student-entry .login-head h2 {
    font-size: clamp(1.25rem, 2.4vw, 1.65rem);
    line-height: 1.2;
    letter-spacing: -0.02em;
}

.student-entry-login-head .student-entry-change-school {
    margin-inline-start: auto;
    color: var(--entry-accent);
}

.student-entry .login-copy {
    color: var(--entry-muted);
    font-weight: 400;
    overflow-wrap: anywhere;
}

.student-entry .login-copy strong {
    color: var(--entry-ink);
}

.student-entry .login-fields {
    grid-template-columns: minmax(0, 1fr);
}

.student-entry .login-fields :deep(.v-otp-input) {
    justify-content: start;
    padding: 0;
}

.student-entry .login-fields :deep(.v-otp-input__content) {
    width: 100%;
    gap: clamp(4px, 1.25vw, 8px);
    padding: 0;
}

.student-entry .login-fields :deep(.v-otp-input__content > .v-field) {
    flex: 1 1 0;
    min-width: 0;
}

.student-entry .login-actions {
    margin-top: 16px;
}

.student-entry .login-actions > .v-btn {
    min-height: 44px;
    border-radius: 8px;
    letter-spacing: normal;
    text-transform: none;
}

.student-entry .student-entry-primary {
    background: var(--entry-ink);
    color: #fff;
}

.student-entry .student-entry-secondary {
    color: var(--entry-ink);
}

.student-entry .login-actions > .v-btn:focus-visible,
.student-entry .login-fields :deep([role='button']:focus-visible) {
    outline: 3px solid var(--entry-accent);
    outline-offset: 3px;
}

.student-entry-error.v-card {
    border-radius: 16px;
    color: #10263a;
}

@media (max-width: 819px) {
    .student-entry-content--login {
        grid-template-columns: minmax(0, 1fr);
    }

    .student-entry-content--login .student-entry-hero {
        min-height: 0;
    }

    .student-entry-content--login .student-entry-hero::after {
        inset-inline-end: -49%;
    }
}

@media (max-width: 419px) {
    .student-entry-login-head .student-entry-change-school {
        margin-inline-start: 0;
    }

    .student-entry .login-actions > .v-btn {
        flex-grow: 1;
    }
}
</style>
