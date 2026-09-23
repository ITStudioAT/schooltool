<template>
    <v-container fluid class="profile-page ma-0 w-100 pa-2" :class="{ 'profile-page--embedded': embedded }" v-if="config && config.user">

        <AdminPageHeader
            v-if="!embedded"
            class="mb-3"
            location="Profil"
            :section="step === '' ? '' : activeSection.label" />

        <v-sheet class="profile-nav mb-3">
            <div class="profile-nav__buttons" role="group" aria-label="Profilbereiche">
                <v-btn
                    :color="step === '' ? 'primary' : undefined"
                    variant="flat"
                    :aria-pressed="step === ''"
                    :class="['profile-nav__button', { 'v-btn--active': step === '', 'profile-nav__button--selected': step === '', 'profile-nav__button--idle': step !== '' }]"
                    @click="abort">
                    <v-icon size="18" icon="mdi-account-outline" class="mr-2" />
                    <span class="profile-nav__button-copy">
                        <span class="profile-nav__button-title">Profildaten</span>
                        <span class="profile-nav__button-meta">Name & E-Mail</span>
                    </span>
                </v-btn>

                <v-btn
                    :color="step === 'APPEARANCE' ? 'primary' : undefined"
                    variant="flat"
                    :aria-pressed="step === 'APPEARANCE'"
                    :class="['profile-nav__button', { 'v-btn--active': step === 'APPEARANCE', 'profile-nav__button--selected': step === 'APPEARANCE', 'profile-nav__button--idle': step !== 'APPEARANCE' }]"
                    @click="openAppearance">
                    <v-icon size="18" icon="mdi-palette-outline" class="mr-2" />
                    <span class="profile-nav__button-copy">
                        <span class="profile-nav__button-title">Darstellung</span>
                        <span class="profile-nav__button-meta">{{ usesSchoolColorForAdminUi ? 'Schulfarbe' : 'Standard (Primary)' }}</span>
                    </span>
                </v-btn>

                <v-btn
                    :color="step === 'CHANGE_PASSWORD' || step === 'PASSWORD_ENTER_TOKEN' ? 'primary' : undefined"
                    variant="flat"
                    :aria-pressed="step === 'CHANGE_PASSWORD' || step === 'PASSWORD_ENTER_TOKEN'"
                    :class="['profile-nav__button', { 'v-btn--active': step === 'CHANGE_PASSWORD' || step === 'PASSWORD_ENTER_TOKEN', 'profile-nav__button--selected': step === 'CHANGE_PASSWORD' || step === 'PASSWORD_ENTER_TOKEN', 'profile-nav__button--idle': !(step === 'CHANGE_PASSWORD' || step === 'PASSWORD_ENTER_TOKEN') }]"
                    @click="wantToChangePassword">
                    <v-icon size="18" icon="mdi-form-textbox-password" class="mr-2" />
                    <span class="profile-nav__button-copy">
                        <span class="profile-nav__button-title">Kennwort</span>
                        <span class="profile-nav__button-meta">Passwort ändern</span>
                    </span>
                </v-btn>

                <v-btn
                    :color="is2FaStep ? 'primary' : undefined"
                    variant="flat"
                    :aria-pressed="is2FaStep"
                    :class="['profile-nav__button', { 'v-btn--active': is2FaStep, 'profile-nav__button--selected': is2FaStep, 'profile-nav__button--idle': !is2FaStep }]"
                    @click="wantToChange2Fa">
                    <v-icon size="18" icon="mdi-two-factor-authentication" class="mr-2" />
                    <span class="profile-nav__button-copy">
                        <span class="profile-nav__button-title">2-Faktor-Auth</span>
                        <span class="profile-nav__button-meta">{{ data.two_factor_enabled ? 'Aktiviert' : data.two_factor_pending ? 'Einrichtung offen' : 'Deaktiviert' }}</span>
                    </span>
                </v-btn>

                <v-btn
                    :color="step === 'HOPPER_SCHOOLS' ? 'primary' : undefined"
                    variant="flat"
                    :aria-pressed="step === 'HOPPER_SCHOOLS'"
                    :class="['profile-nav__button', { 'v-btn--active': step === 'HOPPER_SCHOOLS', 'profile-nav__button--selected': step === 'HOPPER_SCHOOLS', 'profile-nav__button--idle': step !== 'HOPPER_SCHOOLS' }]"
                    @click="openHopperSchools">
                    <v-icon size="18" icon="mdi-account-switch-outline" class="mr-2" />
                    <span class="profile-nav__button-copy">
                        <span class="profile-nav__button-title">Hopper Schulen</span>
                        <span class="profile-nav__button-meta">Schnellwechsel</span>
                    </span>
                </v-btn>
            </div>
        </v-sheet>

        <v-row class="w-100" dense>
            <v-col cols="12" :sm="is2FaStep ? 12 : 9" :md="is2FaStep ? 10 : 7" :lg="is2FaStep ? 8 : 5" :xl="is2FaStep ? 7 : 4">

                <!-- PROFILDATEN ÄNDERN -->
                <v-card v-if="step === ''" rounded="xl" class="profile-card" flat>
                    <v-card-text class="pa-5">
                        <div class="profile-card__header mb-5">
                            <div class="profile-card__header-icon-wrap">
                                <v-icon size="20" icon="mdi-account-circle-outline" />
                            </div>
                            <div>
                                <div class="profile-card__header-title">Profildaten</div>
                                <div class="profile-card__header-sub">{{ config.user.last_name }} {{ config.user.first_name }}</div>
                            </div>
                        </div>

                        <v-form ref="form" v-model="is_valid" @submit.prevent="save(data)" :disabled="!is_edit">
                            <v-text-field
                                autofocus
                                variant="outlined"
                                density="comfortable"
                                rounded="lg"
                                v-model="data.last_name"
                                label="Nachname"
                                :rules="[required(), maxLength(255)]"
                                class="mb-1" />

                            <v-text-field
                                variant="outlined"
                                density="comfortable"
                                rounded="lg"
                                v-model="data.first_name"
                                label="Vorname"
                                :rules="[required(), maxLength(255)]"
                                class="mb-1" />

                            <v-text-field
                                variant="outlined"
                                density="comfortable"
                                rounded="lg"
                                v-model="data.email"
                                label="E-Mail"
                                :rules="[required(), mail(), maxLength(255)]"
                                class="mb-1" />

                            <v-switch
                                true-icon="mdi-check"
                                :model-value="data.two_factor_enabled"
                                label="Zwei-Faktor-Authentifizierung"
                                hide-details
                                color="success"
                                disabled
                                class="mb-2" />
                        </v-form>
                    </v-card-text>

                    <v-card-actions class="pa-5 pt-0 ga-2">
                        <template v-if="!is_edit">
                            <v-btn block color="primary" variant="flat" rounded="lg" @click="is_edit = true">
                                <v-icon size="16" class="mr-1">mdi-pencil-outline</v-icon>
                                Ändern
                            </v-btn>
                        </template>
                        <template v-else>
                            <v-btn color="success" variant="flat" rounded="lg" @click="save(data)" class="flex-1-1">
                                <v-icon size="16" class="mr-1">mdi-check</v-icon>
                                Speichern
                            </v-btn>
                            <v-btn color="warning" variant="text" rounded="lg" @click="abort" class="flex-1-1">
                                Abbruch
                            </v-btn>
                        </template>
                    </v-card-actions>
                </v-card>

                <!-- CODE BESTÄTIGEN - BEI GEÄNDERTER E-MAIL -->
                <v-card v-if="step === 'INPUT_CODE'" rounded="xl" class="profile-card" flat>
                    <v-card-text class="pa-5">
                        <div class="profile-card__header mb-5">
                            <div class="profile-card__header-icon-wrap">
                                <v-icon size="20" icon="mdi-email-check-outline" />
                            </div>
                            <div>
                                <div class="profile-card__header-title">E-Mail bestätigen</div>
                                <div class="profile-card__header-sub">{{ config.user.last_name }} {{ config.user.first_name }}</div>
                            </div>
                        </div>

                        <div class="profile-card__info-block mb-4">
                            <div class="profile-card__info-row">
                                <span class="profile-card__info-label">Bisher</span>
                                <span class="profile-card__info-value">{{ api_answer.email }}</span>
                            </div>
                            <div class="profile-card__info-row">
                                <span class="profile-card__info-label">Neu</span>
                                <span class="profile-card__info-value profile-card__info-value--new">{{ api_answer.email_new }}</span>
                            </div>
                        </div>

                        <v-alert type="info" variant="tonal" rounded="lg" class="mb-4" density="compact">
                            Bitte prüfen Sie Ihre E-Mails
                        </v-alert>

                        <v-form ref="form" v-model="is_valid" @submit.prevent="updateWithCode(data)">
                            <div class="profile-card__otp-label mb-2">Code aus der E-Mail eingeben</div>
                            <v-otp-input autofocus v-model="data.token_2fa" />
                        </v-form>
                    </v-card-text>

                    <v-card-actions class="pa-5 pt-0 ga-2">
                        <v-btn color="success" variant="flat" rounded="lg" @click="updateWithCode(data)" class="flex-1-1">
                            <v-icon size="16" class="mr-1">mdi-check</v-icon>
                            Bestätigen
                        </v-btn>
                        <v-btn color="warning" variant="text" rounded="lg" @click="abort" class="flex-1-1">
                            Abbruch
                        </v-btn>
                    </v-card-actions>
                </v-card>

                <v-card v-if="step === 'APPEARANCE'" rounded="xl" class="profile-card" flat>
                    <v-card-text class="pa-5">
                        <div class="profile-card__header mb-5">
                            <div class="profile-card__header-icon-wrap">
                                <v-icon size="20" icon="mdi-palette-outline" />
                            </div>
                            <div>
                                <div class="profile-card__header-title">Farbdarstellung</div>
                                <div class="profile-card__header-sub">Wählen Sie Schulfarbe oder Standardfarbe (Primary). Diese Auswahl gilt nur für Ihr Benutzerkonto.</div>
                            </div>
                        </div>

                        <div class="d-grid ga-3">
                            <v-btn
                                block
                                rounded="lg"
                                size="large"
                                prepend-icon="mdi-school-outline"
                                :append-icon="usesSchoolColorForAdminUi ? 'mdi-check-circle' : undefined"
                                :color="schoolAppearanceColor"
                                variant="flat"
                                :loading="appearanceSaving"
                                :aria-pressed="usesSchoolColorForAdminUi"
                                :style="schoolAppearanceTextColor ? { color: schoolAppearanceTextColor } : undefined"
                                :class="[
                                    'appearance-color-option text-none justify-start',
                                    { 'appearance-color-option--selected': usesSchoolColorForAdminUi },
                                ]"
                                @click="saveAppearance(true)">
                                Schulfarbe verwenden
                            </v-btn>

                            <v-btn
                                block
                                rounded="lg"
                                size="large"
                                prepend-icon="mdi-palette-outline"
                                :append-icon="!usesSchoolColorForAdminUi ? 'mdi-check-circle' : undefined"
                                color="primary"
                                variant="flat"
                                :loading="appearanceSaving"
                                :aria-pressed="!usesSchoolColorForAdminUi"
                                :class="[
                                    'appearance-color-option text-none justify-start',
                                    { 'appearance-color-option--selected': !usesSchoolColorForAdminUi },
                                ]"
                                @click="saveAppearance(false)">
                                Standardfarbe (Primary) verwenden
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- KENNWORT ÄNDERN -->
                <v-card v-if="step === 'CHANGE_PASSWORD'" rounded="xl" class="profile-card" flat>
                    <v-card-text class="pa-5">
                        <div class="profile-card__header mb-5">
                            <div class="profile-card__header-icon-wrap">
                                <v-icon size="20" icon="mdi-lock-outline" />
                            </div>
                            <div>
                                <div class="profile-card__header-title">Kennwort ändern</div>
                                <div class="profile-card__header-sub">{{ config.user.last_name }} {{ config.user.first_name }}</div>
                            </div>
                        </div>

                        <v-form ref="form" @submit.prevent="savePassword(data)" v-model="is_valid">
                            <v-text-field
                                autofocus
                                variant="outlined"
                                density="comfortable"
                                rounded="lg"
                                label="Neues Kennwort"
                                :append-inner-icon="is_password_visible ? 'mdi-eye' : 'mdi-eye-off'"
                                :type="is_password_visible ? 'text' : 'password'"
                                @click:append-inner="() => (is_password_visible = !is_password_visible)"
                                :rules="[required(), minLength(8), maxLength(255)]"
                                v-model="data.password"
                                class="mb-1" />

                            <v-text-field
                                variant="outlined"
                                density="comfortable"
                                rounded="lg"
                                label="Kennwort wiederholen"
                                :append-inner-icon="is_password_visible_repeat ? 'mdi-eye' : 'mdi-eye-off'"
                                :type="is_password_visible_repeat ? 'text' : 'password'"
                                @click:append-inner="() => (is_password_visible_repeat = !is_password_visible_repeat)"
                                :rules="[required(), minLength(8), maxLength(255), passwordMatch(data.password)]"
                                v-model="data.password_repeat" />
                        </v-form>
                    </v-card-text>

                    <v-card-actions class="pa-5 pt-0 ga-2">
                        <v-btn color="success" variant="flat" rounded="lg" @click="savePassword(data)" class="flex-1-1">
                            <v-icon size="16" class="mr-1">mdi-arrow-right</v-icon>
                            Weiter
                        </v-btn>
                        <v-btn color="warning" variant="text" rounded="lg" @click="abort" class="flex-1-1">
                            Abbruch
                        </v-btn>
                    </v-card-actions>
                </v-card>

                <!-- KENNWORT ÄNDERN - TOKEN -->
                <v-card v-if="step === 'PASSWORD_ENTER_TOKEN'" rounded="xl" class="profile-card" flat>
                    <v-card-text class="pa-5">
                        <div class="profile-card__header mb-5">
                            <div class="profile-card__header-icon-wrap">
                                <v-icon size="20" icon="mdi-lock-check-outline" />
                            </div>
                            <div>
                                <div class="profile-card__header-title">Kennwort bestätigen</div>
                                <div class="profile-card__header-sub">{{ config.user.last_name }} {{ config.user.first_name }}</div>
                            </div>
                        </div>

                        <v-alert type="info" variant="tonal" rounded="lg" class="mb-4" density="compact">
                            Bitte prüfen Sie Ihre E-Mails
                        </v-alert>

                        <v-form ref="form" v-model="is_valid" @submit.prevent="savePasswordWithCode(data)">
                            <div class="profile-card__otp-label mb-2">Code aus der E-Mail eingeben</div>
                            <v-otp-input autofocus v-model="data.token_2fa" />
                        </v-form>
                    </v-card-text>

                    <v-card-actions class="pa-5 pt-0 ga-2">
                        <v-btn color="success" variant="flat" rounded="lg" @click="savePasswordWithCode(data)" class="flex-1-1">
                            <v-icon size="16" class="mr-1">mdi-arrow-right</v-icon>
                            Weiter
                        </v-btn>
                        <v-btn color="warning" variant="text" rounded="lg" @click="abort" class="flex-1-1">
                            Abbruch
                        </v-btn>
                    </v-card-actions>
                </v-card>

                <TwoFactorAuthentication v-if="step === 'CHANGE_2FA'" @updated="updateTwoFactorStatus" />

                <HopperSchools v-if="step === 'HOPPER_SCHOOLS'" />

            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import { resolveAdminShellColor, resolveAdminShellTextColor } from '@/helpers/adminShellTheme'
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useUserStore } from '@/stores/admin/UserStore'
import { useNavigationStore } from '@/stores/admin/NavigationStore'
import AdminPageHeader from '@/pages/admin/components/AdminPageHeader.vue'
import HopperSchools from '@/pages/admin/profile/components/HopperSchools.vue'
import TwoFactorAuthentication from '@/pages/admin/profile/components/TwoFactorAuthentication.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { AdminPageHeader, HopperSchools, TwoFactorAuthentication },

    props: {
        embedded: { type: Boolean, default: false },
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.adminStore.initialize(this.$router)
        this.userStore = useUserStore()
        this.userStore.initialize(this.$router)

        this.navigationStore = useNavigationStore()
        await this.navigationStore.loadMenu('profile_menu')

        await this.userStore.show(this.config.user.id)
        if (this.item) this.data = JSON.parse(JSON.stringify(this.item))
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            userStore: null,
            navigationStore: null,
            is_valid: false,
            data: {},
            is_edit: false,
            step: '',
            is_password_visible: false,
            is_password_visible_repeat: false,
            appearanceSaving: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'is_loading', 'show_navigation_drawer', 'load_config']),
        ...mapWritableState(useUserStore, ['item', 'api_answer']),

        is2FaStep() {
            return this.step === 'CHANGE_2FA'
        },

        usesSchoolColorForAdminUi() {
            return this.config?.user?.use_school_color_for_admin_ui === true
        },

        schoolAppearanceColor() {
            return resolveAdminShellColor(this.config?.selected_school, {
                use_school_color_for_admin_ui: true,
            })
        },

        schoolAppearanceTextColor() {
            return resolveAdminShellTextColor(this.schoolAppearanceColor)
        },

        activeSection() {
            const sections = {
                '': { icon: 'mdi-account-outline', label: 'Profildaten', note: 'Name und E-Mail-Adresse bearbeiten.' },
                INPUT_CODE: { icon: 'mdi-email-check-outline', label: 'E-Mail bestätigen', note: 'Änderung per Code verifizieren.' },
                CHANGE_PASSWORD: { icon: 'mdi-lock-outline', label: 'Kennwort ändern', note: 'Neues Kennwort festlegen.' },
                PASSWORD_ENTER_TOKEN: { icon: 'mdi-lock-check-outline', label: 'Kennwort bestätigen', note: 'Änderung per Code verifizieren.' },
                APPEARANCE: { icon: 'mdi-palette-outline', label: 'Darstellung', note: 'Persönliche Farben für Kopfzeile und Menü wählen.' },
                HOPPER_SCHOOLS: { icon: 'mdi-account-switch-outline', label: 'Hopper Schulen', note: 'Gespeicherte Konten für den Schnellwechsel verwalten.' },
                CHANGE_2FA: { icon: 'mdi-shield-key-outline', label: '2-Faktor-Auth', note: 'Zwei-Faktor-Authentifizierung konfigurieren.' },
            }
            return sections[this.step] || sections['']
        },
    },

    methods: {
        abort() {
            this.is_edit = false
            this.step = ''
            this.data = JSON.parse(JSON.stringify(this.item))
        },

        wantToChangePassword() {
            this.abort()
            this.data = {}
            this.step = 'CHANGE_PASSWORD'
        },

        openAppearance() {
            this.abort()
            this.step = 'APPEARANCE'
        },

        async saveAppearance(useSchoolColorForAdminUi) {
            if (this.appearanceSaving || useSchoolColorForAdminUi === this.usesSchoolColorForAdminUi) return

            this.appearanceSaving = true

            try {
                await this.adminStore.saveAdminShellColorPreference(useSchoolColorForAdminUi)
            } finally {
                this.appearanceSaving = false
            }
        },

        wantToChange2Fa() {
            this.abort()
            this.step = 'CHANGE_2FA'
        },

        openHopperSchools() {
            this.abort()
            this.step = 'HOPPER_SCHOOLS'
        },

        async save(data) {
            await this.$refs.form.validate()
            if (!this.is_valid) return

            this.userStore.api_answer = null
            if (!(await this.userStore.updateProfile(data))) return

            this.is_edit = false
            if (this.api_answer?.answer == 'INPUT_CODE') {
                this.step = 'INPUT_CODE'
            } else {
                this.data = JSON.parse(JSON.stringify(this.item))
                await this.adminStore.loadConfig()
                await this.userStore.show(this.config.user.id)
                this.abort()
            }
        },

        updateTwoFactorStatus(status) {
            this.data.two_factor_enabled = status.enabled
            this.data.two_factor_pending = status.pending
            this.data.two_factor_confirmed_at = status.confirmed_at
        },

        async updateWithCode(data) {
            if (this.data.token_2fa.length != 6) return
            if (await this.userStore.updateWithCode(data)) {
                await this.adminStore.loadConfig()
                this.abort()
            }
        },

        async savePassword(data) {
            await this.$refs.form.validate()
            if (!this.is_valid) return
            const answer = await this.userStore.savePassword(data)
            if (answer) {
                this.step = answer
            } else {
                this.abort()
            }
        },

        async savePasswordWithCode(data) {
            if (this.data.token_2fa.length != 6) return
            if (await this.userStore.savePasswordWithCode(data)) {
                await this.adminStore.loadConfig()
                this.abort()
            }
        },

        runAction(methodName) {
            if (typeof this[methodName] === 'function') {
                this[methodName]()
            }
        },
    },
}
</script>

<style scoped>
.profile-page {
    background: #0f172a;
    min-height: 100vh;
}

.profile-page--embedded {
    background: transparent;
    min-height: 0;
}

.profile-nav {
    border: 1px solid var(--admin-page-border, #e7e9ef);
    border-radius: 16px;
    background: var(--admin-page-surface, #ffffff);
    box-shadow: var(--admin-page-shadow);
    padding: 10px;
}

.profile-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 0;
}

.profile-nav__button {
    min-height: 56px !important;
    height: auto !important;
    min-width: 0;
    padding: 8px 16px;
    border-radius: 0;
    border-inline-end: 1px solid rgba(0, 0, 0, 0.12);
    text-transform: none;
    letter-spacing: 0;
    justify-content: flex-start;
}

.profile-nav__button--selected {
    color: #fff;
}

.profile-nav__button--selected :deep(.v-btn__overlay) {
    opacity: 0.16;
}

.profile-nav__button:focus-visible {
    outline: 2px solid #25332c;
    outline-offset: 2px;
}

.profile-nav__button--idle {
    color: #10263a;
}

.profile-nav__button-copy {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
    line-height: 1.15;
    gap: 4px;
}

.profile-nav__button-title {
    font-weight: 650;
    font-size: 0.875rem;
}

.profile-nav__button-meta {
    max-width: 100%;
    padding: 2px 8px;
    border: 1px solid var(--admin-page-border, #e7e9ef);
    border-radius: 999px;
    background: #f2f4f7;
    font-size: 0.76rem;
    font-weight: 700;
    white-space: normal;
    overflow-wrap: anywhere;
}

.profile-nav__button--selected .profile-nav__button-title {
    color: #fff;
}

.profile-nav__button--selected .profile-nav__button-meta {
    color: var(--admin-page-muted, #65716c);
}

.profile-nav__button--idle .profile-nav__button-title {
    color: #10263a;
}

.profile-nav__button--idle .profile-nav__button-meta {
    color: var(--admin-page-muted, #65716c);
}

.profile-nav__button:first-child { border-radius: 4px 0 0 4px; }
.profile-nav__button:last-child { border-inline-end: 0; border-radius: 0 4px 4px 0; }

@media (max-width: 700px) {
    .profile-nav__buttons { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .profile-nav__button { width: 100%; justify-content: center; }
    .profile-nav__button :deep(.v-btn__content) { min-width: 0; white-space: normal; }
}

@media (max-width: 480px) {
    .profile-nav__buttons { grid-template-columns: 1fr; }
    .profile-nav__button { border-inline-end: 0; }
}

.profile-card {
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.68)) !important;
    box-shadow: 0 18px 48px rgba(16, 38, 58, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.65);
    backdrop-filter: blur(10px);
    color: #112536 !important;
}

.appearance-color-option {
    min-height: 52px;
    height: auto !important;
    padding-block: 10px;
    letter-spacing: 0;
    border: 2px solid rgba(255, 255, 255, 0.46);
    box-shadow: 0 7px 18px rgba(16, 38, 58, 0.18);
}

.appearance-color-option :deep(.v-btn__content) {
    min-width: 0;
    white-space: normal;
    text-align: left;
}

.appearance-color-option--selected {
    border-color: rgba(255, 255, 255, 0.96);
    box-shadow: 0 0 0 2px rgba(16, 38, 58, 0.34), 0 9px 22px rgba(16, 38, 58, 0.24);
}

.profile-card__header {
    display: flex;
    align-items: center;
    gap: 14px;
}

.profile-card__header-icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(180deg, #4f88b8, #2f628c);
    color: #fff;
    box-shadow: 0 8px 18px rgba(36, 76, 109, 0.22);
    flex-shrink: 0;
}

.profile-card__header-icon-wrap--success {
    background: rgba(46, 164, 79, 0.12);
    color: #1a7f37;
    box-shadow: none;
}

.profile-card__header-icon-wrap--warning {
    background: rgba(245, 129, 32, 0.11);
    color: #9c5317;
    box-shadow: none;
}

.profile-card__header-title {
    font-size: 1rem;
    font-weight: 700;
    color: #10263a;
    line-height: 1.2;
}

.profile-card__header-sub {
    font-size: 0.78rem;
    color: rgba(16, 38, 58, 0.86);
    margin-top: 2px;
}

.profile-card__info-block {
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 14px;
    padding: 12px 14px;
    background: rgba(255, 255, 255, 0.78);
}

.profile-card__info-row {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.88rem;
    color: rgba(16, 38, 58, 0.92);
    padding: 3px 0;
}

.profile-card__info-label {
    font-size: 0.76rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(16, 38, 58, 0.72);
    min-width: 44px;
}

.profile-card__info-value--new {
    color: #3049b5;
    font-weight: 600;
}

.profile-card__otp-label {
    font-size: 0.78rem;
    color: rgba(16, 38, 58, 0.86);
    letter-spacing: 0.04em;
}

.profile-card :deep(.v-card-actions) {
    border-top: 1px solid rgba(16, 38, 58, 0.08);
}

.profile-card :deep(.v-field) {
    border-radius: 12px !important;
    background: rgba(255, 255, 255, 0.8);
}

.profile-card :deep(.v-field__input),
.profile-card :deep(.v-label),
.profile-card :deep(.v-selection-control .v-label) {
    color: #112536 !important;
}

.profile-card :deep(.v-otp-input .v-field) {
    background: rgba(255, 255, 255, 0.82);
}
</style>
