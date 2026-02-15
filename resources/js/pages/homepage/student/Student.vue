<template>
    <div class="lernportal-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <section class="hero">
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

        <section v-if="!school" class="schools-cover">
            <div class="schools-card">
                <div class="schools-head">
                    <v-icon size="24">mdi-school-outline</v-icon>
                    <h2>Verfügbare Schulen</h2>
                    <v-chip class="schools-count" size="small">{{ schools?.length || 0 }}</v-chip>
                </div>

                <p class="schools-copy">Diese Schulen sind aktuell für den Unterrichtsbereich verfügbar.</p>

                <div v-if="showSchoolsGrid" class="schools-grid">
                    <div
                        v-for="schoolItem in schools"
                        :key="schoolItem.id"
                        class="school-item"
                        :class="{ 'is-active': Number(selected_school_id) === Number(schoolItem.id) }"
                        @click="selected_school_id = schoolItem.id">
                        <div class="school-main">
                            <span class="school-long">{{ schoolItem.long_name }}</span>
                            <span class="school-short">{{ schoolItem.short_name }}</span>
                        </div>
                    </div>
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
                        <div
                            v-for="schoolItem in filteredSchoolsForSearch"
                            :key="schoolItem.id"
                            class="school-item"
                            :class="{ 'is-active': Number(selected_school_id) === Number(schoolItem.id) }"
                            @click="selected_school_id = schoolItem.id">
                            <div class="school-main">
                                <span class="school-long">{{ schoolItem.long_name }}</span>
                                <span class="school-short">{{ schoolItem.short_name }}</span>
                            </div>
                        </div>
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
        <section v-if="school && login_step === 'email'" class="login-cover">
            <div class="login-card">
                <div class="login-head">
                    <v-icon size="26">mdi-account-school</v-icon>
                    <h2>Login-Bereich</h2>
                </div>
                <p class="login-copy">Die Schule ist ausgewählt. Du kannst jetzt deine E-Mail eingeben.</p>

                <v-form ref="loginForm" v-model="is_login_email_valid" @submit.prevent>
                    <div class="login-fields">
                        <v-text-field
                            v-model="login_email"
                            data-testid="student-login-email"
                            id="student-login-email"
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
                            color="warning"
                            variant="flat"
                            rounded="pill"
                            :disabled="!canContinueWithEmail"
                            @click="continueWithPassword">
                            Weiter mit Kennwort
                        </v-btn>
                        <v-btn
                            data-testid="student-login-continue-code"
                            type="button"
                            color="primary"
                            variant="outlined"
                            rounded="pill"
                            :disabled="!canContinueWithEmail"
                            @click="continueWithoutPassword">
                            Weiter ohne Kennwort
                        </v-btn>
                    </div>
                </v-form>
            </div>
        </section>

        <!-- Code Eingabe (6-stellig) -->
        <section v-if="school && login_step === 'code_sent'" class="login-cover">
            <div class="login-card">
                <div class="login-head">
                    <v-icon size="26">mdi-key-variant</v-icon>
                    <h2>Code eingeben</h2>
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
                        <v-btn type="button" color="warning" variant="text" rounded="pill" @click="backToEmail">Zurück</v-btn>
                        <v-btn type="submit" color="success" variant="flat" rounded="pill" :disabled="!canSubmitCode">Code bestätigen</v-btn>
                    </div>
                </v-form>
            </div>
        </section>

        <!-- Passwort Eingabe -->
        <section v-if="school && login_step === 'enter_password'" class="login-cover">
            <div class="login-card">
                <div class="login-head">
                    <v-icon size="26">mdi-lock</v-icon>
                    <h2>Passwort eingeben</h2>
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
                        <v-btn type="button" color="warning" variant="text" rounded="pill" @click="backToEmail">Zurück</v-btn>
                        <v-btn
                            data-testid="student-login-submit-password"
                            type="submit"
                            color="success"
                            variant="flat"
                            rounded="pill"
                            :disabled="!canSubmitPassword">
                            Anmelden
                        </v-btn>
                    </div>
                </v-form>
            </div>
        </section>

        <!-- Error Dialog -->
        <v-dialog v-model="show_error_dialog" max-width="500">
            <v-card>
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
import '../../../../css/student.css'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.studentStore = useStudentStore()
        const isAuthenticated = await this.studentStore.getCurrentUser()
        if (isAuthenticated && this.user) {
            this.$router.replace('/student/overview')
            return
        }
        await this.studentStore.loadConfig()
        if (this.selected_school_id) {
            this.school = this.schools?.find((item) => Number(item.id) === Number(this.selected_school_id)) || null
        } else if (this.schools?.length === 1) {
            this.selected_school_id = this.schools[0].id
        }
    },

    data() {
        return {
            studentStore: null,
            school_search: '',
            login_email: '',
            login_code: '',
            login_password: '',
            login_step: 'email', // 'email', 'code_sent', 'enter_password'
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
        ...mapWritableState(useStudentStore, ['config', 'schools', 'selected_school_id', 'school', 'data', 'user']),

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
            if (!this.canSubmitCode) return

            this.data.login_code.trim()

            if (await this.studentStore.loginStepCode(this.data)) {
                const status = this.studentStore.data?.status

                if (status === 'code_not_valid') {
                    this.login_error = 'Der eingegebene Code ist ungültig. Bitte versuche es erneut.'
                    this.show_error_dialog = true
                } else if (status === 'login_ok') {
                    // Erfolgreicher Login - Weiterleitung zum Unterrichtsbereich
                    this.$router.push('/student/overview')
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
                    this.$router.push('/student/overview')
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
            this.show_password = false
        },
    },
}
</script>
