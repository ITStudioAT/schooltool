<template>
    <div class="password-section">
        <!-- Section Header -->
        <div class="section-header">
            <div class="header-icon">
                <v-icon size="28" color="white">mdi-lock-reset</v-icon>
            </div>
            <div class="header-text">
                <h2 class="section-title">Kennwort ändern</h2>
                <p class="section-subtitle">Ändere Dein Passwort für mehr Sicherheit</p>
            </div>
        </div>

        <!-- Password Form Card -->
        <div class="form-card">
            <v-form ref="form" v-model="is_valid" @submit.prevent="updatePassword(data)">
                <div class="form-content" :class="{ 'form-disabled': data.status == 'CONFIRM_PASSWORD' || data.status == 'RE_CONFIRM_PASSWORD' }">
                    <!-- Password Section -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <v-icon size="20" class="mr-2">mdi-form-textbox-password</v-icon>
                            Neues Kennwort
                        </div>

                        <div class="password-requirements">
                            <v-icon size="14" class="mr-1">mdi-information-outline</v-icon>
                            Mindestens 8 Zeichen erforderlich
                        </div>

                        <v-text-field
                            autofocus
                            v-model="data.password"
                            label="Neues Kennwort"
                            :append-inner-icon="is_password_visible ? 'mdi-eye' : 'mdi-eye-off'"
                            :type="is_password_visible ? 'text' : 'password'"
                            @click:append-inner="is_password_visible = !is_password_visible"
                            :rules="[required(), minLength(8), maxLength(255)]"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-lock"
                            class="mt-3"
                        />

                        <v-text-field
                            v-model="data.password_confirm"
                            label="Kennwort wiederholen"
                            :append-inner-icon="is_password_visible_confirm ? 'mdi-eye' : 'mdi-eye-off'"
                            :type="is_password_visible_confirm ? 'text' : 'password'"
                            @click:append-inner="is_password_visible_confirm = !is_password_visible_confirm"
                            :rules="[required(), minLength(8), maxLength(255), passwordMatch(data.password)]"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-lock-check"
                        />
                    </div>
                </div>

                <!-- Email Confirmation -->
                <div class="confirmation-section" v-if="data.status == 'CONFIRM_PASSWORD'">
                    <div class="confirmation-card">
                        <div class="confirmation-icon">
                            <v-icon size="32" color="primary">mdi-email-check</v-icon>
                        </div>
                        <h4>Bestätigungscode eingeben</h4>
                        <p>Wir haben Dir eine E-Mail mit einem Code geschickt. Bitte gib den Code hier ein.</p>
                        <v-otp-input autofocus v-model="data.token_2fa" class="mt-4" />
                    </div>
                </div>

                <!-- Email Re-Confirmation -->
                <div class="confirmation-section" v-if="data.status == 'RE_CONFIRM_PASSWORD'">
                    <div class="confirmation-card confirmation-warning">
                        <div class="confirmation-icon">
                            <v-icon size="32" color="warning">mdi-email-alert</v-icon>
                        </div>
                        <h4>Code ungültig</h4>
                        <p>Der Code war falsch oder abgelaufen. Wir haben Dir erneut eine E-Mail mit einem neuen Code geschickt.</p>
                        <v-otp-input autofocus v-model="data.token_2fa" class="mt-4" />
                    </div>
                </div>

                <!-- Error Alert -->
                <div class="error-section" v-if="error">
                    <v-alert type="error" variant="tonal" rounded="lg">
                        {{ error?.response?.data?.message + ' (' + error?.response?.status + ')' }}
                    </v-alert>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <v-btn
                        variant="outlined"
                        color="grey"
                        size="large"
                        rounded="lg"
                        @click="action = ''"
                    >
                        <v-icon start>mdi-close</v-icon>
                        Abbrechen
                    </v-btn>
                    <v-btn
                        variant="flat"
                        color="success"
                        size="large"
                        rounded="lg"
                        @click="updatePassword(data)"
                    >
                        <v-icon start>mdi-content-save</v-icon>
                        Speichern
                    </v-btn>
                </div>
            </v-form>
        </div>
    </div>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import { useUserStore } from '@/stores/tutoring/UserStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },
    components: {},

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.userStore = useUserStore()
        await this.tutoringStore.loadAuth()
        this.editPassword()
    },

    data() {
        return {
            tutoringStore: null,
            userStore: null,
            is_valid: false,
            is_password_visible: false,
            is_password_visible_confirm: false,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['auth', 'action']),
        ...mapWritableState(useUserStore, ['error', 'data']),
    },

    methods: {
        async updatePassword(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            if (!(await this.userStore.updatePassword(data))) return

            if (this.data.status != 'OK') return

            await this.tutoringStore.loadAuth()
            this.action = ''
        },

        editPassword() {
            this.error = null
            this.data = {
                id: this.auth.auth_user.id,
            }
        },
    },
}
</script>

<style scoped>
.password-section {
    max-width: 500px;
    margin: 0 auto;
}

/* Section Header */
.section-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
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
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    line-height: 1.2;
}

.section-subtitle {
    font-size: 0.95rem;
    opacity: 0.9;
    margin: 4px 0 0 0;
}

/* Form Card */
.form-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
}

.form-content {
    transition: opacity 0.3s ease;
}

.form-disabled {
    opacity: 0.5;
    pointer-events: none;
}

/* Form Sections */
.form-section {
    margin-bottom: 24px;
}

.form-section-title {
    display: flex;
    align-items: center;
    font-size: 0.95rem;
    font-weight: 600;
    color: #37474F;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e0e0e0;
}

.password-requirements {
    display: flex;
    align-items: center;
    font-size: 0.8rem;
    color: #78909C;
    background: #f5f5f5;
    padding: 8px 12px;
    border-radius: 8px;
}

/* Confirmation Section */
.confirmation-section {
    margin-top: 24px;
}

.confirmation-card {
    text-align: center;
    padding: 28px;
    background: linear-gradient(135deg, rgba(33, 150, 243, 0.08), rgba(33, 150, 243, 0.02));
    border: 2px solid rgba(33, 150, 243, 0.2);
    border-radius: 16px;
}

.confirmation-warning {
    background: linear-gradient(135deg, rgba(255, 152, 0, 0.08), rgba(255, 152, 0, 0.02));
    border-color: rgba(255, 152, 0, 0.3);
}

.confirmation-icon {
    margin-bottom: 12px;
}

.confirmation-card h4 {
    font-size: 1.1rem;
    font-weight: 700;
    color: #263238;
    margin: 0 0 8px 0;
}

.confirmation-card p {
    font-size: 0.9rem;
    color: #607D8B;
    line-height: 1.5;
    margin: 0;
}

/* Error Section */
.error-section {
    margin-top: 20px;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid #e0e0e0;
}

.form-actions .v-btn {
    flex: 1;
    max-width: 180px;
}

/* Responsive */
@media (max-width: 600px) {
    .section-header {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }

    .form-card {
        padding: 20px;
    }

    .form-actions {
        flex-direction: column;
    }

    .form-actions .v-btn {
        max-width: none;
    }
}
</style>
