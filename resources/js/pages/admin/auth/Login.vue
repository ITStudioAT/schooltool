<template>
    <div class="login-page">
        <!-- Animated Background (same as homepage) -->
        <div class="animated-bg">
            <div class="hero-bg-image"></div>
        </div>

        <div class="login-layout" v-if="config">
            <div class="login-shell">
                <!-- Header -->
                <header class="cloud-header">
                    <div class="cloud-header-left">
                        <div class="login-brand-link">
                            <img :src="'/storage/images/schooltool/schooltool-wordmark.svg'" alt="SchoolTool" class="st-header-logo" />
                        </div>

                    </div>
                </header>

                <!-- Login Card -->
                <div class="login-main">
                    <div class="login-hero-copy" aria-hidden="true">
                        <h2 class="login-hero-title">
                            <div>Mehr Zeit für das Wesentliche.</div>
                            <div class="mt-4"><span class="login-hero-title-accent">Admin-Bereich</span></div>
                        </h2>
                    </div>

                    <div class="login-card-wrap">
                        <div class="login-card">
                    <!-- Logo -->
                    <div class="school-logo-area">
                        <img :src="'/storage/images/schooltool/schooltool-mark.svg'" alt="SchoolTool" class="st-card-mark hover" @click="homepage" />
                    </div>

                    <!-- Step: Enter Email -->
                    <div class="card-body" v-if="step == 'LOGIN_ENTER_EMAIL'">
                        <h2 class="step-title">Willkommen zurück</h2>
                        <p class="step-hint">Bitte die E-Mail-Adresse eingeben</p>
                        <v-form ref="form" v-model="is_valid" @submit.prevent="loginStepEmail()" class="mb-3">
                            <v-text-field
                                autofocus
                                v-model="data.email"
                                label="E-Mail"
                                variant="outlined"
                                density="comfortable"
                                :rules="[required(), mail()]"
                                data-testid="admin-login-email"
                                id="admin-login-email" />
                        </v-form>
                        <v-btn block color="success" flat size="large" data-testid="admin-login-continue-password" @click="loginStepEmail()" class="mb-4">Weiter</v-btn>
                        <div class="alt-actions">
                            <v-btn variant="text" size="small" color="#14293b" data-testid="admin-login-unknown-password" :disabled="!isPasswordUnknownAvailable" @click="passwordUnknown">Kennwort unbekannt</v-btn>
                            <template v-if="config.register_admin_allowed">
                                <span class="alt-sep">·</span>
                                <v-btn variant="text" size="small" color="success" data-testid="admin-login-register" @click="register">Neu registrieren</v-btn>
                            </template>
                        </div>
                        <v-alert
                            v-if="!isPasswordUnknownAvailable"
                            class="queue-down-hint-alert"
                            density="comfortable"
                            type="warning"
                            variant="tonal"
                            border="start">
                            !Anmelden ohne Kennwort derzeit nicht möglich
                        </v-alert>
                    </div>

                    <!-- Step: Select School -->
                    <div class="card-body" v-if="step == 'LOGIN_SELECT_SCHOOL'">
                        <h2 class="step-title">Schule auswählen</h2>
                        <p class="step-hint">Bitte die Schule auswählen</p>
                        <v-autocomplete
                            v-model="selected_school_id"
                            :items="data.schools"
                            item-title="long_name"
                            item-value="id"
                            label="Auswahl Schule"
                            variant="outlined"
                            density="comfortable"
                            data-testid="admin-login-school-select"
                            class="mb-3" />
                        <v-btn block color="success" flat size="large" data-testid="admin-login-continue-school" @click="loginStepSchool()" v-if="school" class="mb-3">Weiter</v-btn>
                        <v-btn block variant="text" color="warning" data-testid="admin-login-back-from-school" @click="restartLogin">Zurück</v-btn>
                    </div>

                    <!-- Step: Enter Password -->
                    <div class="card-body" v-if="step == 'LOGIN_ENTER_PASSWORD'">
                        <h2 class="step-title">Kennwort eingeben</h2>
                        <p class="step-hint school-name">{{ data?.school?.long_name }}</p>
                        <v-form ref="form" v-model="is_valid" @submit.prevent="loginStep2()" class="mb-3">
                            <v-text-field
                                autofocus
                                label="Kennwort"
                                variant="outlined"
                                density="comfortable"
                                :append-icon="is_password_visible ? 'mdi-eye' : 'mdi-eye-off'"
                                :type="is_password_visible ? 'text' : 'password'"
                                @click:append="() => (is_password_visible = !is_password_visible)"
                                :rules="[required(), minLength(8), maxLength(255)]"
                                v-model="data.password"
                                data-testid="admin-login-password"
                                id="admin-login-password" />
                            <v-checkbox
                                v-model="data.remember"
                                label="Angemeldet bleiben"
                                color="primary"
                                density="comfortable"
                                hide-details
                                class="mt-1"
                                data-testid="admin-login-remember"
                            />
                        </v-form>
                        <v-btn block color="success" flat size="large" data-testid="admin-login-submit-password" @click="loginStep2()" class="mb-3">Anmelden</v-btn>
                        <v-btn block variant="text" color="warning" data-testid="admin-login-back-from-password" @click="restartLogin">Zurück</v-btn>
                    </div>

                    <!-- Step: Login Processing -->
                    <div class="card-body" v-if="step == 'LOGIN_PROCESSING'" data-testid="login-processing">
                        <div class="login-processing">
                            <v-progress-circular indeterminate color="success" size="48" width="4" class="mb-5" />
                            <h2 class="step-title text-center">Login wird durchgeführt</h2>
                            <p class="step-hint text-center mb-0">Bitte einen Moment Geduld …</p>
                        </div>
                    </div>

                    <!-- Step: Logout Processing -->
                    <div class="card-body" v-if="step == 'LOGOUT_PROCESSING'" data-testid="logout-processing">
                        <div class="login-processing">
                            <v-progress-circular indeterminate color="warning" size="48" width="4" class="mb-5" />
                            <h2 class="step-title text-center">Abmeldung wird durchgeführt</h2>
                            <p class="step-hint text-center mb-0">Bitte einen Moment Geduld …</p>
                        </div>
                    </div>

                    <!-- Step: Enter 2FA Token -->
                    <div class="card-body" v-if="step == 'LOGIN_ENTER_TOKEN'">
                        <h2 class="step-title">Zwei-Faktor-Code</h2>
                        <p class="step-hint school-name">{{ data?.school?.long_name }}</p>
                        <v-form ref="form" v-model="is_valid" @submit.prevent="loginStep3()">
                            <v-alert closable color="success" type="info" density="compact" text="Bitte prüfen Sie Ihre E-Mails" class="mb-3" />
                            <p class="step-hint">Bitte den Code laut E-Mail eingeben</p>
                            <v-otp-input autofocus v-model="data.token_2fa" data-testid="admin-login-token" id="admin-login-token" class="mb-3" />
                        </v-form>
                        <v-btn block color="success" flat size="large" data-testid="admin-login-submit-token" @click="loginStep3()" class="mb-3">Anmelden</v-btn>
                        <v-btn block variant="text" color="warning" data-testid="admin-login-back-from-token" @click="restartLogin">Zurück</v-btn>
                    </div>

                    <!-- Step: Enter authenticator or recovery code -->
                    <div class="card-body" v-if="step == 'LOGIN_ENTER_TWO_FACTOR'">
                        <h2 class="step-title">Zwei-Faktor-Authentifizierung</h2>
                        <p class="step-hint school-name">{{ data?.school?.long_name }}</p>
                        <v-btn-toggle v-model="twoFactorMode" mandatory color="primary" variant="outlined" divided class="mb-4 w-100">
                            <v-btn value="code" class="flex-1-1">Authenticator-Code</v-btn>
                            <v-btn value="recovery" class="flex-1-1">Wiederherstellungscode</v-btn>
                        </v-btn-toggle>

                        <v-form v-if="twoFactorMode === 'code'" @submit.prevent="submitTwoFactorChallenge">
                            <p class="step-hint">Geben Sie den sechsstelligen Code aus Ihrer Authenticator-App ein.</p>
                            <v-otp-input
                                v-model="twoFactorCode"
                                autofocus
                                length="6"
                                type="number"
                                data-testid="admin-login-two-factor-code"
                                class="mb-3"
                                @finish="submitTwoFactorChallenge" />
                        </v-form>

                        <v-form v-else @submit.prevent="submitTwoFactorChallenge">
                            <v-text-field
                                v-model="recoveryCode"
                                autofocus
                                label="Wiederherstellungscode"
                                variant="outlined"
                                autocomplete="one-time-code"
                                data-testid="admin-login-recovery-code"
                                class="mb-3" />
                        </v-form>

                        <v-btn block color="success" flat size="large" data-testid="admin-login-submit-two-factor" @click="submitTwoFactorChallenge" class="mb-3">
                            Anmeldung abschließen
                        </v-btn>
                        <v-btn block variant="text" color="warning" @click="restartLogin">Zurück</v-btn>
                    </div>

                    <!-- New Teacher: Select School -->
                    <div class="card-body" v-if="step == 'NEW_TEACHER_SELECT_SCHOOL'">
                        <h2 class="step-title">Neue:r Lehrer:in</h2>
                        <p class="step-hint">{{ data.email }}</p>
                        <p class="step-hint">Bitte die Schule auswählen</p>
                        <v-autocomplete
                            v-model="selected_school_id"
                            :items="data.schools"
                            item-title="long_name"
                            item-value="id"
                            label="Auswahl Schule"
                            variant="outlined"
                            density="comfortable"
                            class="mb-3" />
                        <v-btn block color="success" flat size="large" @click="newTeacherStepSchool()" v-if="school" class="mb-3">Weiter</v-btn>
                        <v-btn block variant="text" color="warning" @click="restartLogin">Zurück</v-btn>
                    </div>

                    <!-- New Teacher: Enter Code -->
                    <div class="card-body" v-if="step == 'NEW_TEACHER_INPUT_CODE' || step == 'NEW_TEACHER_TOKEN_WRONG'">
                        <h2 class="step-title">Neue:r Lehrer:in</h2>
                        <p class="step-hint">{{ data.email }}</p>
                        <p class="step-hint school-name">{{ data?.school?.long_name }}</p>
                        <v-alert v-if="step == 'NEW_TEACHER_TOKEN_WRONG'" closable type="error" density="compact" text="Das Token war falsch oder abgelaufen. Versuchen Sie es erneut." class="mb-3" />
                        <v-form ref="form" v-model="is_valid" @submit.prevent="newTeacherStepCode()">
                            <v-alert closable color="success" type="info" density="compact" text="Sie wurden als Lehrer:in erkannt. Bitte prüfen Sie Ihre E-Mails" class="mb-3" />
                            <p class="step-hint">Bitte den Code laut E-Mail eingeben</p>
                            <v-otp-input autofocus v-model="data.token" class="mb-3" />
                            <v-btn block color="success" flat size="large" type="submit" @click="submit">Anmelden</v-btn>
                        </v-form>
                        <v-btn block variant="text" color="warning" @click="restartLogin" class="mt-3">Zurück</v-btn>
                    </div>

                    <!-- New Teacher: Success -->
                    <div class="card-body" v-if="step == 'NEW_TEACHER_OK'">
                        <h2 class="step-title">Neue:r Lehrer:in</h2>
                        <p class="step-hint">{{ data.email }}</p>
                        <p class="step-hint school-name">{{ data?.school?.long_name }}</p>
                        <v-alert type="success" class="mb-4">
                            <div>Sie wurden am System registriert und eingeloggt.</div>
                            <div>Herzliche Gratulation!</div>
                        </v-alert>
                        <v-btn block color="success" flat size="large" @click="moveAdmin">Weiter</v-btn>
                    </div>
                        </div>

                        <div class="card-version">{{ config.version }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: {},

    async beforeMount() {
        this.adminStore = useAdminStore()

        if (this.$route.query.logout === '1') {
            this.step = 'LOGOUT_PROCESSING'
            await this.adminStore.executeLogout()
            this.$router.replace({ path: '/admin/login', query: {} })
            this.restartLogin()
            return
        }

        if (!this.config?.is_auth) await this.adminStore.executeLogout()
        this.restartLogin()
    },

    data() {
        return {
            adminStore: null,
            is_valid: false,
            step: null,
            is_password_visible: false,
            twoFactorMode: 'code',
            twoFactorCode: '',
            recoveryCode: '',
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'is_loading', 'error', 'api_response', 'load_config', 'school', 'selected_school_id', 'data']),
        isPasswordUnknownAvailable() {
            return this.config?.health?.queue_working !== false
        },
    },

    watch: {
        selected_school_id() {
            if (this.selected_school_id) {
                this.school = this.data.schools.find((s) => s.id === this.selected_school_id)
                this.data.school = this.school
            } else {
                this.school = null
                this.data.school = null
            }
        },
    },

    methods: {
        moveAdmin() {
            window.location.href = '/admin'
        },
        async newTeacherStepEmail() {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            this.data['step'] = 'NEW_TEACHER'
            if (!(await this.adminStore.newTeacherStepEmail(this.data))) return

            this.step = this.data['step']
        },

        async newTeacherStepSchool() {
            if (!this.selected_school_id) return
            this.data.school_id = this.selected_school_id
            if (!(await this.adminStore.newTeacherStepSchool(this.data))) return
            this.step = this.data['step']
        },

        async newTeacherStepCode() {
            if (this.data?.token?.length != 6) return
            this.data.step = 'NEW_TEACHER_INPUT_CODE'

            if (!(await this.adminStore.newTeacherStepCode(this.data))) return

            await this.$nextTick()

            window.location.href = '/admin'
            return
            await this.adminStore.loadConfig()
            this.step = this.data['step']
        },
        homepage() {
            window.location.href = '/'
        },

        passwordUnknown() {
            this.$router.push('/admin/unknown_password')
        },

        register() {
            this.$router.push('/admin/register')
        },

        restartLogin() {
            this.data.password = null
            this.data.token_2fa = null
            this.twoFactorCode = ''
            this.recoveryCode = ''
            this.twoFactorMode = 'code'
            this.data.remember = true
            this.step = 'LOGIN_ENTER_EMAIL'
        },

        loginStepSchool() {
            if (!this.school) return
            this.step = 'LOGIN_ENTER_PASSWORD'
        },

        async loginStepEmail() {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            const remember = typeof this.data?.remember === 'boolean' ? this.data.remember : true
            this.data.step = 'LOGIN_ENTER_EMAIL'
            if (!(await this.adminStore.loginStepEmail(this.data))) return

            if (!this.data.school) this.selected_school_id = null
            this.data.remember = remember
            this.step = this.data.step
        },

        async loginStep2() {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            this.data.step = 'LOGIN_ENTER_PASSWORD'
            this.data.remember = !!this.data.remember
            this.step = 'LOGIN_PROCESSING'

            if (!(await this.adminStore.loginStep2(this.data))) {
                this.step = 'LOGIN_ENTER_PASSWORD'
                return
            }

            if (this.data.step == 'LOGIN_SUCCESS') {
                await this.adminStore.loadConfig()
                this.$router.push('/admin')
            } else if (this.data.step == 'LOGIN_ENTER_TWO_FACTOR') {
                this.data.password = null
                this.step = 'LOGIN_ENTER_TWO_FACTOR'
            } else {
                this.step = 'LOGIN_ENTER_TOKEN'
            }
        },

        async submitTwoFactorChallenge() {
            const payload = this.twoFactorMode === 'recovery'
                ? { recovery_code: String(this.recoveryCode || '').trim() }
                : { code: String(this.twoFactorCode || '').replace(/\s+/g, '') }

            if (this.twoFactorMode === 'code' && !/^\d{6}$/.test(payload.code)) return
            if (this.twoFactorMode === 'recovery' && !payload.recovery_code) return

            this.step = 'LOGIN_PROCESSING'
            const response = await this.adminStore.loginTwoFactorChallenge(payload)
            this.twoFactorCode = ''
            this.recoveryCode = ''

            if (!response) {
                this.step = 'LOGIN_ENTER_TWO_FACTOR'
                return
            }

            window.location.replace('/admin')
        },

        async loginStep3() {
            if (this.data.token_2fa.length != 6) return
            this.data.step = 'LOGIN_ENTER_TOKEN'
            this.data.remember = !!this.data.remember
            this.step = 'LOGIN_PROCESSING'
            if (!(await this.adminStore.loginStep3(this.data))) {
                this.step = 'LOGIN_ENTER_TOKEN'
                return
            }

            if (this.data.step == 'LOGIN_SUCCESS') {
                window.location.replace('/admin')
            } else {
                this.step = 'LOGIN_ENTER_TOKEN'
            }
        },
    },
}
</script>

<style scoped>
/* Base */
.login-page {
    min-height: 100vh;
    position: relative;
    background: #f7901e;
}

/* Animated Background */
.animated-bg {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
    pointer-events: none;
}

.hero-bg-image {
    position: absolute;
    inset: 0;
    background-image: url('../../../../images/backgrounds/cloudflare-hero-orange.svg');
    background-repeat: no-repeat;
    background-position: center top;
    background-size: cover;
}

.hero-bg-image::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(650px 220px at 50% 16%, rgba(255, 220, 145, 0.16), transparent 70%),
        linear-gradient(180deg, rgba(255, 170, 68, 0.05), rgba(232, 103, 28, 0.05));
}

/* Layout */
.login-layout {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    padding: 28px 20px 48px;
}

.login-shell {
    width: 100%;
    max-width: 1440px;
    min-height: calc(100vh - 76px);
    margin: 0 auto;
    display: flex;
    flex-direction: column;
}

/* Header (same as homepage) */
.cloud-header {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    width: 100%;
    gap: 12px;
    padding: 6px 4px;
    margin-bottom: 18px;
    animation: fadeInDown 0.6s ease-out;
}

.cloud-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    flex-wrap: wrap;
}

.login-brand-link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 0;
    border: 0;
    background: transparent;
    cursor: default;
}

.st-header-logo {
    height: 28px;
    width: auto;
}

.cloud-header-nav {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-left: 34px;
}

.cloud-nav-item {
    display: inline-flex;
    align-items: center;
    gap: 0;
    border: 0;
    background: transparent;
    color: #233645;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 0.98rem;
    font-weight: 400;
    cursor: default;
    box-shadow: none;
    opacity: 0.95;
}

.cloud-nav-item:hover {
    background: rgba(255, 255, 255, 0.18);
}

@keyframes pulse-glow {
    0%,
    100% {
        box-shadow: 0 10px 40px rgba(58, 170, 53, 0.3);
    }
    50% {
        box-shadow: 0 10px 60px rgba(243, 146, 0, 0.4);
    }
}

/* Card Wrap */
.login-main {
    flex: 1;
    width: 100%;
    max-width: 1120px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 26px;
}

.login-card-wrap {
    width: 100%;
    max-width: 480px;
    animation: scaleIn 0.4s ease-out;
    flex-shrink: 0;
}

.login-hero-copy {
    position: relative;
    top: -150px;
    width: 100%;
    max-width: 900px;
    color: #14293b;
    text-align: center;
    margin-bottom: -150px;
    animation: fadeInUp 0.8s ease-out 0.1s both;
}

.login-hero-title {
    margin: 0;
    font-size: clamp(2.1rem, 4.8vw, 4.1rem);
    line-height: 0.98;
    letter-spacing: 0.015em;
    font-weight: 700;
    color: #10263a;
    text-shadow: 0 8px 22px rgba(27, 15, 6, 0.12);
}

.login-hero-title-accent {
    color: #fff;
}

/* Card */
.login-card {
    background: white;
    border-radius: 24px;
    box-shadow:
        0 24px 60px rgba(68, 33, 4, 0.2),
        0 4px 16px rgba(68, 33, 4, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.5);
    overflow: hidden;
}

/* School Logo */
.school-logo-area {
    padding: 24px 28px 0;
    display: flex;
    align-items: center;
}

.school-logo {
    max-height: 56px;
    max-width: 180px;
    object-fit: contain;
}

.st-card-mark {
    width: 48px;
    height: 48px;
}

/* Card Body */
.card-body {
    padding: 24px 28px 28px;
}

/* Step Title & Hint */
.step-title {
    font-size: 1.45rem;
    font-weight: 700;
    color: #10263a;
    margin: 0 0 6px 0;
    line-height: 1.2;
}

.step-hint {
    font-size: 0.92rem;
    color: rgba(16, 38, 58, 0.65);
    margin: 0 0 16px 0;
    line-height: 1.4;
}

.step-hint.school-name {
    font-weight: 600;
    color: #10263a;
}

/* Alt Actions */
.alt-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 2px;
}

.alt-sep {
    color: rgba(16, 38, 58, 0.3);
    font-size: 0.9rem;
    line-height: 1;
}

.queue-down-hint-alert {
    margin: 10px 0 0;
}

.login-processing {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 32px 0 16px;
}

/* Version */
.card-version {
    text-align: center;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 14px;
    margin-bottom: 2px;
}

/* Animations */
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.96);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(24px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 1400px) {
    .login-hero-copy {
        top: -120px;
        margin-bottom: -120px;
        max-width: 780px;
    }
}

@media (max-width: 1200px) {
    .login-hero-copy {
        top: -80px;
        margin-bottom: -80px;
        max-width: 720px;
    }

    .login-hero-title {
        font-size: clamp(1.9rem, 4.1vw, 3.2rem);
    }
}

@media (max-width: 1100px) {
    .login-main {
        justify-content: center;
        align-items: center;
    }

    .login-hero-copy {
        top: -40px;
        margin-bottom: -40px;
        max-width: 640px;
    }

    .login-hero-title {
        font-size: clamp(1.7rem, 3.8vw, 2.6rem);
    }
}

@media (max-width: 860px) {
    .login-main {
        gap: 16px;
    }

    .login-hero-copy {
        display: block;
        top: -18px;
        margin-bottom: -18px;
        max-width: 560px;
    }

    .login-hero-title {
        font-size: clamp(1.35rem, 4.8vw, 2rem);
        line-height: 1.02;
    }
}

/* Responsive */
@media (max-width: 600px) {
    .login-layout {
        padding: 20px 16px 40px;
    }

    .login-shell {
        min-height: calc(100vh - 60px);
    }

    .login-main {
        max-width: 100%;
        gap: 12px;
    }

    .login-hero-copy {
        top: 0;
        margin-bottom: 2px;
        max-width: 100%;
    }

    .login-hero-title {
        font-size: 1.35rem;
        line-height: 1.05;
        letter-spacing: 0.01em;
    }

    .cloud-header {
        margin-bottom: 12px;
    }

    .cloud-header-nav {
        margin-left: 14px;
        gap: 10px;
    }

    .cloud-nav-item {
        font-size: 0.92rem;
    }

    .school-logo-area {
        padding: 20px 20px 0;
    }

    .card-body {
        padding: 20px;
    }

    .login-card {
        border-radius: 20px;
    }
}
</style>
