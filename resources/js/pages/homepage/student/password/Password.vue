<template>
    <div class="lernportal-page student-workspace">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <!-- Navigation Drawer -->
        <StudentNavigationDrawer v-model="showDrawer" current-route="password" />

        <section class="hero">
            <div class="hero-card">
                <div class="hero-topline">
                    <v-btn class="back-btn" variant="text" prepend-icon="mdi-arrow-left" @click="$router.push('/student/overview')">Zurück</v-btn>
                    <div class="chip-brand">Passwort ändern</div>
                    <v-btn class="menu-btn" data-testid="student-password-open-menu" variant="text" icon="mdi-menu" @click="showDrawer = true" />
                </div>

                <h1 class="hero-title">Passwort ändern</h1>
                <p class="hero-subtitle">Erhöhe die Sicherheit deines Kontos mit einem neuen Passwort.</p>

                <div class="hero-badges">
                    <span class="hero-badge">{{ weekdayLabel }}</span>
                    <span class="hero-badge dark">{{ dateLabel }}</span>
                    <span v-if="user" class="hero-badge">{{ user.first_name }} {{ user.last_name }}</span>
                </div>
            </div>
        </section>

        <section class="content-cover">
            <div class="content-card">
                <div class="content-head">
                    <v-icon size="26">mdi-lock-reset</v-icon>
                    <h2>Neues Passwort festlegen</h2>
                </div>
                <p class="content-copy">Wähle ein neues, sicheres Passwort für dein Konto.</p>

                <v-form ref="passwordForm" v-model="isFormValid" @submit.prevent="handleSubmit">
                    <div class="password-fields">
                        <!-- New Password -->
                        <v-text-field
                            v-model="newPassword"
                            label="Neues Passwort (mindestens 8 Zeichen)"
                            variant="outlined"
                            density="comfortable"
                            :type="showNewPassword ? 'text' : 'password'"
                            prepend-inner-icon="mdi-lock-plus-outline"
                            :append-inner-icon="showNewPassword ? 'mdi-eye-off' : 'mdi-eye'"
                            :rules="[required(), minLength(8), maxLength(255)]"
                            hide-details="auto"
                            data-testid="student-password-new"
                            @click:append-inner="showNewPassword = !showNewPassword" />

                        <!-- Confirm Password -->
                        <v-text-field
                            v-model="confirmPassword"
                            label="Neues Passwort bestätigen"
                            variant="outlined"
                            density="comfortable"
                            :type="showConfirmPassword ? 'text' : 'password'"
                            prepend-inner-icon="mdi-lock-check-outline"
                            :append-inner-icon="showConfirmPassword ? 'mdi-eye-off' : 'mdi-eye'"
                            :rules="[required(), minLength(8), maxLength(255), passwordMatch]"
                            hide-details="auto"
                            data-testid="student-password-confirm"
                            @click:append-inner="showConfirmPassword = !showConfirmPassword"
                            @keyup.enter="handleSubmit" />
                    </div>

                    <!-- Password Strength Indicator -->
                    <div v-if="newPassword" class="password-strength">
                        <div class="strength-label">
                            <span>Passwortstärke:</span>
                            <span :class="['strength-text', `strength-${passwordStrength.level}`]">{{ passwordStrength.text }}</span>
                        </div>
                        <v-progress-linear
                            :model-value="passwordStrength.value"
                            :color="passwordStrength.color"
                            height="6"
                            rounded />
                        <div class="password-requirements">
                            <div class="requirement" :class="{ met: newPassword.length >= 8 }">
                                <v-icon size="16">{{ newPassword.length >= 8 ? 'mdi-check-circle' : 'mdi-circle-outline' }}</v-icon>
                                Mindestens 8 Zeichen
                            </div>
                            <div class="requirement" :class="{ met: /[A-Z]/.test(newPassword) }">
                                <v-icon size="16">{{ /[A-Z]/.test(newPassword) ? 'mdi-check-circle' : 'mdi-circle-outline' }}</v-icon>
                                Mindestens ein Großbuchstabe
                            </div>
                            <div class="requirement" :class="{ met: /[a-z]/.test(newPassword) }">
                                <v-icon size="16">{{ /[a-z]/.test(newPassword) ? 'mdi-check-circle' : 'mdi-circle-outline' }}</v-icon>
                                Mindestens ein Kleinbuchstabe
                            </div>
                            <div class="requirement" :class="{ met: /[0-9]/.test(newPassword) }">
                                <v-icon size="16">{{ /[0-9]/.test(newPassword) ? 'mdi-check-circle' : 'mdi-circle-outline' }}</v-icon>
                                Mindestens eine Zahl
                            </div>
                        </div>
                    </div>

                    <div class="password-actions">
                        <v-btn color="secondary" variant="outlined" rounded="pill" @click="$router.push('/student/overview')">Abbrechen</v-btn>
                        <v-btn color="success" variant="flat" rounded="pill" type="submit" data-testid="student-password-submit" :disabled="!canSubmit">Passwort ändern</v-btn>
                    </div>
                </v-form>
            </div>
        </section>

        <!-- Success Dialog -->
        <v-dialog v-model="showSuccessDialog" data-testid="student-password-success-dialog" max-width="400">
            <v-card>
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon color="success" icon="mdi-check-circle" />
                    <span>Erfolgreich geändert</span>
                </v-card-title>
                <v-card-text class="pt-4">
                    Dein Passwort wurde erfolgreich geändert. Du kannst dich jetzt mit deinem neuen Passwort anmelden.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn color="success" variant="flat" data-testid="student-password-success-ok" @click="handleSuccessClose">OK</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useStudentStore } from '@/stores/student/StudentStore'
import StudentNavigationDrawer from '../components/StudentNavigationDrawer.vue'
import '../../../../../css/student.css'

export default {
    components: {
        StudentNavigationDrawer,
    },

    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.studentStore = useStudentStore()

        // Try to fetch current authenticated user
        const isAuthenticated = await this.studentStore.getCurrentUser()

        // Prüfen ob User eingeloggt ist
        if (!isAuthenticated || !this.user || this.viewer_type === 'parent') {
            this.$router.push('/student')
        }
    },

    data() {
        return {
            studentStore: null,
            showDrawer: false,
            showSuccessDialog: false,
            isFormValid: false,
            newPassword: '',
            confirmPassword: '',
            showNewPassword: false,
            showConfirmPassword: false,
        }
    },

    computed: {
        ...mapWritableState(useStudentStore, ['user', 'viewer_type']),

        weekdayLabel() {
            return new Intl.DateTimeFormat('de-AT', { weekday: 'long' }).format(new Date())
        },
        dateLabel() {
            return new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date())
        },
        passwordMatch() {
            return () => {
                if (this.newPassword !== this.confirmPassword) {
                    return 'Die Passwörter stimmen nicht überein'
                }
                return true
            }
        },
        passwordStrength() {
            const password = this.newPassword
            let strength = 0
            const checks = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password),
            }

            if (checks.length) strength += 20
            if (checks.uppercase) strength += 20
            if (checks.lowercase) strength += 20
            if (checks.number) strength += 20
            if (checks.special) strength += 20

            let level = 'weak'
            let text = 'Schwach'
            let color = 'error'

            if (strength >= 80) {
                level = 'strong'
                text = 'Stark'
                color = 'success'
            } else if (strength >= 60) {
                level = 'good'
                text = 'Gut'
                color = 'warning'
            } else if (strength >= 40) {
                level = 'medium'
                text = 'Mittel'
                color = 'orange'
            }

            return { value: strength, level, text, color }
        },
        canSubmit() {
            return (
                this.isFormValid &&
                this.newPassword &&
                this.confirmPassword &&
                this.newPassword === this.confirmPassword &&
                this.newPassword.length >= 8
            )
        },
    },

    methods: {
        async handleSubmit() {
            if (!this.canSubmit) return

            // Validate form
            const { valid } = await this.$refs.passwordForm.validate()
            if (!valid) return

            // Call API to change password
            const success = await this.studentStore.changePassword(this.newPassword, this.confirmPassword)

            if (success) {
                this.showSuccessDialog = true
                this.newPassword = ''
                this.confirmPassword = ''
                this.$refs.passwordForm.reset()
            }
        },

        handleSuccessClose() {
            this.showSuccessDialog = false
            this.$router.push('/student/overview')
        },
    },
}
</script>
