<template>
    <div class="register-page">
        <!-- Animated Background -->
        <div class="animated-bg">
            <div class="gradient-orb orb-1"></div>
            <div class="gradient-orb orb-2"></div>
        </div>

        <!-- Floating Particles -->
        <div class="particles">
            <div class="particle" v-for="n in 15" :key="n" :style="getParticleStyle(n)"></div>
        </div>

        <!-- Main Content -->
        <div class="register-container" v-if="config">
            <!-- School Header Card -->
            <div class="school-header-card">
                <div class="school-header-content">
                    <div class="school-logo-wrapper" v-if="config?.school?.logo">
                        <img :src="`/storage/images/logos/${config?.school?.logo}`" alt="Logo" class="school-logo" />
                    </div>
                    <div class="school-icon-wrapper" v-else>
                        <v-icon size="48" color="white">mdi-school</v-icon>
                    </div>
                    <div class="school-info">
                        <h1 class="school-name">{{ config?.school?.long_name }}</h1>
                        <p class="school-subtitle">Anmeldesystem</p>
                    </div>
                </div>
            </div>

            <!-- No Active Register Alert -->
            <div class="content-card" v-if="registers.length == 0">
                <div class="empty-state">
                    <div class="empty-icon">
                        <v-icon size="72" color="grey-lighten-1">mdi-calendar-remove</v-icon>
                    </div>
                    <h3 class="empty-title">Keine Anmeldung aktiv</h3>
                    <p class="empty-text">Derzeit sind keine Anmeldetermine verfügbar.</p>
                    <v-btn color="primary" variant="flat" size="large" rounded="lg" to="/" class="mt-4">
                        <v-icon start>mdi-arrow-left</v-icon>
                        Zurück zur Startseite
                    </v-btn>
                </div>
            </div>

            <!-- Step: Select Register -->
            <div class="content-card" v-if="data.step == 'SELECT_REGISTER'">
                <div class="card-glow"></div>
                <div class="card-inner">
                    <div class="step-header">
                        <div class="step-icon">
                            <v-icon size="28" color="primary">mdi-format-list-bulleted</v-icon>
                        </div>
                        <div class="step-info">
                            <h2 class="step-title">Anmeldung auswählen</h2>
                            <p class="step-subtitle">Bitte wählen Sie die gewünschte Registrierung</p>
                        </div>
                    </div>

                    <v-form ref="form" class="step-form" @submit.prevent="selectRegister">
                        <v-autocomplete
                            v-model="selected_register_id"
                            :items="registers"
                            item-title="name"
                            item-value="id"
                            label="Registrierung auswählen"
                            variant="outlined"
                            prepend-inner-icon="mdi-magnify"
                            @keydown.enter.prevent="selected_register_id && selectRegister()"
                            hide-details
                            class="mb-6" />
                        <div class="form-actions">
                            <v-btn type="button" color="grey" variant="tonal" size="large" rounded="lg" to="/">
                                <v-icon start>mdi-arrow-left</v-icon>
                                Zurück
                            </v-btn>
                            <v-btn color="success" variant="flat" size="large" rounded="lg" type="submit" :disabled="!selected_register_id">
                                Weiter
                                <v-icon end>mdi-arrow-right</v-icon>
                            </v-btn>
                        </div>
                    </v-form>
                </div>
            </div>

            <!-- Active Register Info -->
            <div class="register-info-card" v-if="active_register">
                <div class="register-info-header">
                    <v-icon size="24" class="mr-2">mdi-calendar-check</v-icon>
                    <span class="register-name">{{ active_register.name }}</span>
                </div>
                <v-expand-transition>
                    <div v-if="active_register.description_on_website" class="register-description">
                        <div v-html="active_register.description_on_website"></div>
                    </div>
                </v-expand-transition>
            </div>

            <!-- Step: Email Input -->
            <div class="content-card" v-if="active_register && data.step == 'EMAIL'" data-testid="register-step-email">
                <div class="card-glow"></div>
                <div class="card-inner">
                    <div class="step-header">
                        <div class="step-icon">
                            <v-icon size="28" color="primary">mdi-email-outline</v-icon>
                        </div>
                        <div class="step-info">
                            <h2 class="step-title">Ihre E-Mail-Adresse</h2>
                            <p class="step-subtitle">Bitte geben Sie Ihre E-Mail-Adresse ein</p>
                        </div>
                    </div>
                    <div>
                        <v-form ref="form" v-model="is_valid" @submit.prevent="checkEmail(data)" class="step-form">
                            <v-text-field
                                autofocus
                                v-model="data.email"
                                label="E-Mail-Adresse"
                                placeholder="ihre.email@beispiel.at"
                                variant="outlined"
                                prepend-inner-icon="mdi-email"
                                :rules="[required(), mail()]"
                                class="mb-6"
                                tabindex="1"
                                data-testid="register-email"
                                id="register-email" />
                            <div class="form-actions">
                                <v-btn type="button" color="grey" variant="tonal" size="large" rounded="lg" to="/">
                                    <v-icon start>mdi-arrow-left</v-icon>
                                    Zurück
                                </v-btn>
                                <v-btn
                                    color="success"
                                    variant="flat"
                                    size="large"
                                    rounded="lg"
                                    type="submit"
                                    :disabled="!data.email"
                                    tabindex="2"
                                    data-testid="register-email-continue">
                                    Weiter
                                    <v-icon end>mdi-arrow-right</v-icon>
                                </v-btn>
                            </div>
                        </v-form>
                    </div>
                </div>
            </div>

            <!-- Step: Email Token Verification (New User) -->
            <div class="content-card" v-if="active_register && data.step == 'EMAIL_TOKEN'" data-testid="register-step-email-token">
                <div class="card-glow"></div>
                <div class="card-inner">
                    <div class="step-header">
                        <div class="step-icon step-icon-info">
                            <v-icon size="28" color="info">mdi-email-check</v-icon>
                        </div>
                        <div class="step-info">
                            <h2 class="step-title">E-Mail bestätigen</h2>
                            <p class="step-subtitle">{{ data.email }}</p>
                        </div>
                    </div>

                    <v-alert type="info" variant="tonal" class="mb-6" border="start">
                        <div class="d-flex align-center">
                            <v-icon class="mr-2">mdi-email-fast</v-icon>
                            <span>Wir haben Ihnen einen Bestätigungscode per E-Mail gesendet.</span>
                        </div>
                    </v-alert>

                    <v-form ref="form" v-model="is_valid" @submit.prevent="confirmEmail(data)" class="step-form">
                        <div class="otp-label">Bitte den Code eingeben:</div>
                        <v-otp-input autofocus v-model="data.token_2fa" class="otp-input mb-6" data-testid="register-email-token-input" />
                        <div class="form-actions">
                            <v-btn type="button" color="grey" variant="tonal" size="large" rounded="lg" @click="startRegister">
                                <v-icon start>mdi-refresh</v-icon>
                                Neustart
                            </v-btn>
                            <v-btn color="success" variant="flat" size="large" rounded="lg" type="submit" data-testid="register-email-token-submit" :disabled="!data.email">
                                Bestätigen
                                <v-icon end>mdi-check</v-icon>
                            </v-btn>
                        </div>
                    </v-form>
                </div>
            </div>

            <!-- Step: Enter User Data -->
            <div class="content-card" v-if="active_register && (data.step == 'ENTER_USER_DATA' || data.step == 'OK')">
                <div class="card-glow"></div>
                <div class="card-inner">
                    <div class="step-header">
                        <div class="step-icon">
                            <v-icon size="28" color="primary">mdi-account-edit</v-icon>
                        </div>
                        <div class="step-info">
                            <h2 class="step-title">Ihre Daten</h2>
                            <p class="step-subtitle">Bitte geben Sie Ihren Namen ein (nicht vom Kind!)</p>
                        </div>
                    </div>

                    <v-form ref="form" v-model="is_valid" @submit.prevent="saveUserData(data)" class="step-form">
                        <v-text-field
                            autofocus
                            v-model="data.last_name"
                            label="Ihr Nachname"
                            variant="outlined"
                            prepend-inner-icon="mdi-account"
                            :rules="[required(), maxLength(255)]"
                            class="mb-4" />
                        <v-text-field
                            v-model="data.first_name"
                            label="Ihr Vorname"
                            variant="outlined"
                            prepend-inner-icon="mdi-account-outline"
                            :rules="[maxLength(255)]"
                            class="mb-4" />
                        <v-text-field
                            v-if="active_register.show_phone"
                            v-model="data.phone"
                            label="Ihre Telefonnummer"
                            variant="outlined"
                            prepend-inner-icon="mdi-phone"
                            :rules="[active_register.must_phone ? required() : () => true, minLength(8), maxLength(255)]"
                            class="mb-6" />
                        <div class="form-actions">
                            <v-btn type="button" color="grey" variant="tonal" size="large" rounded="lg" @click="startRegister">
                                <v-icon start>mdi-refresh</v-icon>
                                Neustart
                            </v-btn>
                            <v-btn color="success" variant="flat" size="large" rounded="lg" type="submit" :disabled="!data.email">
                                Weiter
                                <v-icon end>mdi-arrow-right</v-icon>
                            </v-btn>
                        </div>
                    </v-form>
                </div>
            </div>

            <!-- Step: Login Token -->
            <div class="content-card" v-if="active_register && data.step == 'LOGIN_TOKEN'" data-testid="register-step-login-token">
                <div class="card-glow"></div>
                <div class="card-inner">
                    <div class="step-header">
                        <div class="step-icon step-icon-success">
                            <v-icon size="28" color="success">mdi-login</v-icon>
                        </div>
                        <div class="step-info">
                            <h2 class="step-title">Anmeldung</h2>
                            <p class="step-subtitle">{{ data.email }}</p>
                        </div>
                    </div>

                    <v-alert type="success" variant="tonal" class="mb-6" border="start">
                        <div class="d-flex align-center">
                            <v-icon class="mr-2">mdi-email-fast</v-icon>
                            <span>Wir haben Ihnen einen Anmeldecode per E-Mail gesendet.</span>
                        </div>
                    </v-alert>

                    <v-form ref="form" v-model="is_valid" @submit.prevent="loginToken(data)" class="step-form">
                        <div class="otp-label">Bitte den Code eingeben:</div>
                        <v-otp-input autofocus v-model="data.token_2fa" class="otp-input mb-6" data-testid="register-login-token-input" />
                        <div class="form-actions">
                            <v-btn type="button" color="grey" variant="tonal" size="large" rounded="lg" @click="startRegister">
                                <v-icon start>mdi-refresh</v-icon>
                                Neustart
                            </v-btn>
                            <v-btn color="success" variant="flat" size="large" rounded="lg" type="submit" data-testid="register-login-token-submit" :disabled="!data.email">
                                Anmelden
                                <v-icon end>mdi-login</v-icon>
                            </v-btn>
                        </div>
                    </v-form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useRegisterStore } from '@/stores/homepage/RegisterStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.registerStore = useRegisterStore()
        this.school_name = this.$route.query.school

        if (!this.school_name) {
            this.$router.push('/')
            return
        }

        await this.registerStore.loadConfig(this.school_name)

        if (!this.config?.school) {
            this.$router.push('/')
            return
        }

        this.startRegister()
    },

    data() {
        return {
            registerStore: null,
            school_name: '',
            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useRegisterStore, ['config', 'registers', 'active_register', 'selected_register_id', 'data']),
    },

    methods: {
        getParticleStyle(n) {
            const random = (min, max) => Math.random() * (max - min) + min
            return {
                left: `${random(0, 100)}%`,
                top: `${random(0, 100)}%`,
                width: `${random(4, 10)}px`,
                height: `${random(4, 10)}px`,
                animationDelay: `${random(0, 15)}s`,
                animationDuration: `${random(15, 25)}s`,
            }
        },

        async confirmEmail(data) {
            if (!(await this.registerStore.confirmEmail(data))) return
        },

        async loginToken(data) {
            if (!(await this.registerStore.loginToken(data))) return
            this.$router.push('/homepage/register2')
        },

        async saveUserData(data) {
            const { valid } = await this.$refs.form.validate()
            if (!valid) return
            if (!(await this.registerStore.saveUserData(data))) return
            this.$router.push('/homepage/register2')
        },

        async checkEmail(data) {
            const { valid } = await this.$refs.form.validate()
            if (!valid) return
            if (!this.active_register) {
                console.error('active_register is null')
                return
            }
            this.data.register_id = this.active_register.id
            if (!(await this.registerStore.checkEmail(data))) return
        },

        startRegister() {
            Object.keys(this.data).forEach((key) => delete this.data[key])
            this.data.school_id = this.config?.school?.id
            if (this.registers.length == 1) this.data.step = 'EMAIL'
            if (this.registers.length > 1) {
                this.active_register = null
                this.data.step = 'SELECT_REGISTER'
            }
        },

        async selectRegister() {
            this.active_register = this.registers.find((r) => r.id === this.selected_register_id)
            this.data.step = 'EMAIL'
        },
    },
}
</script>

<style scoped>
/* Base Layout */
.register-page {
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 24px 16px;
}

/* Animated Background */
.animated-bg {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    z-index: 0;
    pointer-events: none;
}

.gradient-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(100px);
    opacity: 0.4;
    animation: float 25s ease-in-out infinite;
}

.orb-1 {
    width: 500px;
    height: 500px;
    background: linear-gradient(135deg, #3aaa35 0%, #2d8a2a 100%);
    top: -150px;
    right: -150px;
    animation-delay: 0s;
}

.orb-2 {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, #37474f 0%, #263238 100%);
    bottom: -100px;
    left: -100px;
    animation-delay: -12s;
    opacity: 0.25;
}

@keyframes float {
    0%,
    100% {
        transform: translate(0, 0) scale(1);
    }
    33% {
        transform: translate(30px, -30px) scale(1.05);
    }
    66% {
        transform: translate(-20px, 20px) scale(0.95);
    }
}

/* Particles */
.particles {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
    overflow: hidden;
}

.particle {
    position: absolute;
    background: rgba(58, 170, 53, 0.25);
    border-radius: 50%;
    animation: drift 20s ease-in-out infinite;
}

.particle:nth-child(even) {
    background: rgba(55, 71, 79, 0.2);
}

@keyframes drift {
    0%,
    100% {
        transform: translate(0, 0);
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    90% {
        opacity: 1;
    }
    100% {
        transform: translate(80px, -80px);
        opacity: 0;
    }
}

/* Container */
.register-container {
    position: relative;
    z-index: 1;
    max-width: 560px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* School Header Card */
.school-header-card {
    background: linear-gradient(135deg, #37474f 0%, #263238 100%);
    border-radius: 20px;
    padding: 28px 24px;
    box-shadow: 0 10px 40px rgba(55, 71, 79, 0.3);
}

.school-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}

.school-logo-wrapper {
    width: 96px;
    height: 96px;
    /* background: white; */
    background: rgba(255, 255, 255, 0.25);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
    padding: 8px;
}

.school-logo {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.school-icon-wrapper {
    width: 72px;
    height: 72px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.school-info {
    flex: 1;
    min-width: 0;
}

.school-name {
    font-size: 1.35rem;
    font-weight: 700;
    color: white;
    margin: 0;
    line-height: 1.3;
}

.school-subtitle {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.75);
    margin: 6px 0 0 0;
}

/* Register Info Card */
.register-info-card {
    background: linear-gradient(135deg, #3aaa35 0%, #2d8a2a 100%);
    border-radius: 16px;
    padding: 16px 20px;
    color: white;
    box-shadow: 0 6px 25px rgba(58, 170, 53, 0.25);
}

.register-info-header {
    display: flex;
    align-items: center;
    font-weight: 600;
    font-size: 1.05rem;
}

.register-name {
    flex: 1;
}

.register-description {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    font-size: 0.9rem;
    line-height: 1.6;
    opacity: 0.95;
}

/* Content Card */
.content-card {
    position: relative;
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

.content-card:hover {
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
}

.card-glow {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3aaa35, #4bc044);
}

.card-inner {
    padding: 28px 24px;
}

/* Step Header */
.step-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.step-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(58, 170, 53, 0.1);
    flex-shrink: 0;
}

.step-icon-info {
    background: rgba(33, 150, 243, 0.1);
}

.step-icon-success {
    background: rgba(76, 175, 80, 0.1);
}

.step-info {
    flex: 1;
    min-width: 0;
}

.step-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #263238;
    margin: 0;
    line-height: 1.3;
}

.step-subtitle {
    font-size: 0.9rem;
    color: #607d8b;
    margin: 4px 0 0 0;
}

/* Form Styles */
.step-form {
    display: flex;
    flex-direction: column;
}

.form-actions {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.form-actions .v-btn {
    flex: 1;
    min-width: 140px;
}

/* OTP Input */
.otp-label {
    font-size: 0.95rem;
    font-weight: 500;
    color: #546e7a;
    margin-bottom: 12px;
}

.otp-input {
    justify-content: center;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 48px 24px;
}

.empty-icon {
    margin-bottom: 20px;
}

.empty-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #263238;
    margin: 0 0 12px 0;
}

.empty-text {
    font-size: 1rem;
    color: #607d8b;
    margin: 0;
    line-height: 1.6;
}

/* Responsive */
@media (max-width: 600px) {
    .register-page {
        padding: 16px 12px;
    }

    .school-header-card {
        padding: 20px 16px;
    }

    .school-header-content {
        flex-direction: column;
        text-align: center;
        gap: 16px;
    }

    .school-name {
        font-size: 1.15rem;
    }

    .card-inner {
        padding: 24px 20px;
    }

    .step-header {
        flex-direction: column;
        text-align: center;
        gap: 12px;
    }

    .step-title {
        font-size: 1.1rem;
    }

    .form-actions {
        flex-direction: column;
    }

    .form-actions .v-btn {
        width: 100%;
    }
}

/* Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    .gradient-orb,
    .particle,
    .register-container {
        animation: none;
    }

    .content-card:hover {
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    }
}
</style>
