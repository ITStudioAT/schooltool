<template>
    <div class="profile-section" v-if="action == 'profile'">
        <!-- Section Header -->
        <div class="section-header">
            <div class="header-icon">
                <v-icon size="28" color="white">mdi-account-edit</v-icon>
            </div>
            <div class="header-text">
                <h2 class="section-title">Profil ändern</h2>
                <p class="section-subtitle">Bearbeite Deine persönlichen Daten</p>
            </div>
        </div>

        <!-- Profile Form Card -->
        <div class="form-card">
            <v-form ref="form" v-model="is_valid" @submit.prevent="updateProfile(data)">
                <div class="form-content" :class="{ 'form-disabled': data.status == 'CONFIRM_EMAIL' || data.status == 'RE_CONFIRM_EMAIL' }">
                    <!-- Personal Info Section -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <v-icon size="20" class="mr-2">mdi-card-account-details</v-icon>
                            Persönliche Daten
                        </div>

                        <div class="form-grid">
                            <v-text-field
                                autofocus
                                v-model="data.last_name"
                                label="Nachname"
                                :rules="[required(), maxLength(255)]"
                                variant="outlined"
                                density="comfortable"
                                prepend-inner-icon="mdi-account"
                            />
                            <v-text-field
                                v-model="data.first_name"
                                label="Vorname"
                                :rules="[maxLength(255)]"
                                variant="outlined"
                                density="comfortable"
                                prepend-inner-icon="mdi-account-outline"
                            />
                        </div>

                        <v-text-field
                            v-model="data.schoolclass"
                            label="Schulklasse"
                            :rules="[required(), maxLength(10)]"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-school"
                            class="mt-2"
                        />
                    </div>

                    <!-- Gender Section -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <v-icon size="20" class="mr-2">mdi-gender-male-female</v-icon>
                            Geschlecht
                        </div>

                        <div class="gender-selection">
                            <div
                                class="gender-option"
                                :class="{ 'gender-selected': data.sex === 'm', 'gender-male': data.sex === 'm' }"
                                @click="data.sex = 'm'"
                            >
                                <v-icon size="24">mdi-gender-male</v-icon>
                                <span>Männlich</span>
                            </div>
                            <div
                                class="gender-option"
                                :class="{ 'gender-selected': data.sex === 'f', 'gender-female': data.sex === 'f' }"
                                @click="data.sex = 'f'"
                            >
                                <v-icon size="24">mdi-gender-female</v-icon>
                                <span>Weiblich</span>
                            </div>
                            <div
                                class="gender-option"
                                :class="{ 'gender-selected': data.sex === 'd', 'gender-diverse': data.sex === 'd' }"
                                @click="data.sex = 'd'"
                            >
                                <v-icon size="24">mdi-gender-non-binary</v-icon>
                                <span>Divers</span>
                            </div>
                        </div>
                    </div>

                    <!-- Email Section -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <v-icon size="20" class="mr-2">mdi-email</v-icon>
                            E-Mail Adresse
                        </div>

                        <v-text-field
                            v-model="data.email"
                            label="E-Mail"
                            :rules="[required(), mail(), maxLength(255)]"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-at"
                        />
                    </div>
                </div>

                <!-- Email Confirmation -->
                <div class="confirmation-section" v-if="data.status == 'CONFIRM_EMAIL'">
                    <div class="confirmation-card">
                        <div class="confirmation-icon">
                            <v-icon size="32" color="primary">mdi-email-check</v-icon>
                        </div>
                        <h4>E-Mail Bestätigung</h4>
                        <p>Wir haben Dir eine E-Mail an Deine neue E-Mail-Adresse mit einem Code geschickt. Bitte gib den Code hier ein.</p>
                        <v-otp-input autofocus v-model="data.token_2fa" class="mt-4" />
                    </div>
                </div>

                <!-- Email Re-Confirmation -->
                <div class="confirmation-section" v-if="data.status == 'RE_CONFIRM_EMAIL'">
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
                        @click="updateProfile(data)"
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
    props: [],
    components: {},

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.userStore = useUserStore()
        await this.tutoringStore.loadAuth()
        this.editProfile()
    },

    data() {
        return {
            tutoringStore: null,
            userStore: null,
            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['auth', 'action']),
        ...mapWritableState(useUserStore, ['error', 'data']),
    },

    methods: {
        async updateProfile(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            if (!(await this.userStore.update(data))) return

            if (this.data.status != 'OK') return

            await this.tutoringStore.loadAuth()
            this.action = ''
        },
        editProfile() {
            this.error = null
            this.data = {
                id: this.auth.auth_user.id,
                last_name: this.auth.auth_user.last_name,
                first_name: this.auth.auth_user.first_name,
                email: this.auth.auth_user.email,
                schoolclass: this.auth.auth_user.schoolclass,
                sex: this.auth.auth_user.sex,
            }
        },
    },
}
</script>

<style scoped>
.profile-section {
    max-width: 600px;
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
    margin-bottom: 28px;
}

.form-section-title {
    display: flex;
    align-items: center;
    font-size: 0.95rem;
    font-weight: 600;
    color: #37474F;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e0e0e0;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

/* Gender Selection */
.gender-selection {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.gender-option {
    flex: 1;
    min-width: 100px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 16px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
}

.gender-option:hover {
    border-color: #90A4AE;
    background: #f5f5f5;
}

.gender-option span {
    font-size: 0.85rem;
    font-weight: 500;
    color: #607D8B;
}

.gender-selected {
    transform: scale(1.02);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.gender-male.gender-selected {
    border-color: #2196F3;
    background: rgba(33, 150, 243, 0.08);
}

.gender-male.gender-selected .v-icon {
    color: #2196F3;
}

.gender-male.gender-selected span {
    color: #2196F3;
}

.gender-female.gender-selected {
    border-color: #E91E63;
    background: rgba(233, 30, 99, 0.08);
}

.gender-female.gender-selected .v-icon {
    color: #E91E63;
}

.gender-female.gender-selected span {
    color: #E91E63;
}

.gender-diverse.gender-selected {
    border-color: #FF9800;
    background: rgba(255, 152, 0, 0.08);
}

.gender-diverse.gender-selected .v-icon {
    color: #FF9800;
}

.gender-diverse.gender-selected span {
    color: #FF9800;
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
    max-width: 200px;
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

    .form-grid {
        grid-template-columns: 1fr;
    }

    .gender-selection {
        flex-direction: column;
    }

    .gender-option {
        flex-direction: row;
        justify-content: center;
    }

    .form-actions {
        flex-direction: column;
    }

    .form-actions .v-btn {
        max-width: none;
    }
}
</style>
