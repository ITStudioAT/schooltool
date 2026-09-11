<template>
    <div class="lernportal-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <StudentTimetablesNavigationDrawer v-model="showDrawer" current-route="password" />

        <section class="hero">
            <div class="hero-card">
                <div class="hero-topline">
                    <v-btn class="back-btn" variant="text" prepend-icon="mdi-arrow-left" @click="$router.push('/students-timetables/overview')">Zurück</v-btn>
                    <div class="chip-brand">Passwort ändern</div>
                    <v-btn class="menu-btn" variant="text" icon="mdi-menu" @click="showDrawer = true" />
                </div>

                <h1 class="hero-title">Passwort ändern</h1>
                <p class="hero-subtitle">Legen Sie ein neues Passwort für Ihr Konto fest.</p>

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
                    <h2>Neues Passwort</h2>
                </div>

                <v-form ref="passwordForm" v-model="isFormValid" @submit.prevent="handleSubmit">
                    <div class="password-fields">
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
                            @click:append-inner="showNewPassword = !showNewPassword" />

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
                            @click:append-inner="showConfirmPassword = !showConfirmPassword" />
                    </div>

                    <div class="password-actions">
                        <v-btn color="secondary" variant="outlined" rounded="pill" @click="$router.push('/students-timetables/overview')">Abbrechen</v-btn>
                        <v-btn color="success" variant="flat" rounded="pill" type="submit" :disabled="!canSubmit">Passwort ändern</v-btn>
                    </div>
                </v-form>
            </div>
        </section>

        <v-dialog v-model="showSuccessDialog" max-width="400">
            <v-card>
                <v-card-title class="student-password-dialog-title d-flex align-center ga-2">
                    <v-icon color="success" icon="mdi-check-circle" />
                    <span>Erfolgreich geändert</span>
                </v-card-title>
                <v-card-text class="pt-4">Ihr Passwort wurde erfolgreich geändert.</v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn color="success" variant="flat" @click="handleSuccessClose">OK</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useStudentTimetablesUserStore } from '@/stores/studentsTimetables/StudentTimetablesUserStore'
import StudentTimetablesNavigationDrawer from '../components/StudentTimetablesNavigationDrawer.vue'
import '../../../../../css/student.css'

export default {
    components: {
        StudentTimetablesNavigationDrawer,
    },

    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.studentTimetablesStore = useStudentTimetablesUserStore()
        const isAuthenticated = await this.studentTimetablesStore.getCurrentUser()

        if (!isAuthenticated || !this.user) {
            this.$router.push('/homepage/students-timetables')
        }
    },

    data() {
        return {
            studentTimetablesStore: null,
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
        ...mapWritableState(useStudentTimetablesUserStore, ['user']),

        weekdayLabel() {
            return new Intl.DateTimeFormat('de-AT', { weekday: 'long' }).format(new Date())
        },
        dateLabel() {
            return new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date())
        },
        passwordMatch() {
            return () => this.newPassword === this.confirmPassword || 'Die Passwörter stimmen nicht überein'
        },
        canSubmit() {
            return this.isFormValid && this.newPassword === this.confirmPassword && this.newPassword.length >= 8
        },
    },

    methods: {
        async handleSubmit() {
            if (!this.canSubmit) return

            const { valid } = await this.$refs.passwordForm.validate()
            if (!valid) return

            const success = await this.studentTimetablesStore.changePassword(this.newPassword, this.confirmPassword)

            if (success) {
                this.showSuccessDialog = true
                this.newPassword = ''
                this.confirmPassword = ''
                this.$refs.passwordForm.reset()
            }
        },

        handleSuccessClose() {
            this.showSuccessDialog = false
            this.$router.push('/students-timetables/overview')
        },
    },
}
</script>

<style scoped>
.hero-title,
.hero-badge,
.student-password-dialog-title {
    white-space: normal;
    overflow-wrap: anywhere;
}

.hero-badge {
    max-width: 100%;
}
</style>
