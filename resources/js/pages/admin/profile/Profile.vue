<template>
    <v-container fluid class="profile-page ma-0 w-100 pa-2" :class="{ 'profile-page--embedded': embedded }" v-if="config && config.user">

        <AdminSectionHero
            v-if="!embedded"
            class="mb-3"
            eyebrow="Konto"
            title="Benutzerprofil & Sicherheit"
            :active-section="activeSection"
            :chips="heroChips"
            :show-current-user-chip="true"
            user-chip-prefix="Angemeldet als"
            primary-color="#1e1b4b"
            secondary-color="#4338ca"
            left-orb-color="#818cf8"
            right-orb-color="#c7d2fe" />

        <v-sheet rounded="xl" class="profile-nav mb-3">
            <div class="profile-nav__buttons">
                <v-btn
                    rounded="xl"
                    :color="step === '' ? 'primary' : 'secondary'"
                    :variant="step === '' ? 'flat' : 'tonal'"
                    class="profile-nav__button"
                    @click="abort">
                    <v-icon size="18" icon="mdi-account-outline" class="mr-2" />
                    <span class="profile-nav__button-copy">
                        <span class="profile-nav__button-title">Profildaten</span>
                        <span class="profile-nav__button-meta">Name & E-Mail</span>
                    </span>
                </v-btn>

                <v-btn
                    rounded="xl"
                    :color="step === 'CHANGE_PASSWORD' || step === 'PASSWORD_ENTER_TOKEN' ? 'primary' : 'secondary'"
                    :variant="step === 'CHANGE_PASSWORD' || step === 'PASSWORD_ENTER_TOKEN' ? 'flat' : 'tonal'"
                    class="profile-nav__button"
                    @click="wantToChangePassword">
                    <v-icon size="18" icon="mdi-form-textbox-password" class="mr-2" />
                    <span class="profile-nav__button-copy">
                        <span class="profile-nav__button-title">Kennwort</span>
                        <span class="profile-nav__button-meta">Passwort ändern</span>
                    </span>
                </v-btn>

                <v-btn
                    rounded="xl"
                    :color="is2FaStep ? 'primary' : 'secondary'"
                    :variant="is2FaStep ? 'flat' : 'tonal'"
                    class="profile-nav__button"
                    @click="wantToChange2Fa">
                    <v-icon size="18" icon="mdi-two-factor-authentication" class="mr-2" />
                    <span class="profile-nav__button-copy">
                        <span class="profile-nav__button-title">2-Faktor-Auth</span>
                        <span class="profile-nav__button-meta">{{ data.is_2fa ? 'Aktiviert' : 'Deaktiviert' }}</span>
                    </span>
                </v-btn>
            </div>
        </v-sheet>

        <v-row class="w-100" dense>
            <v-col cols="12" sm="9" md="7" lg="5" xl="4">

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
                                v-model="data.is_2fa"
                                label="2-Faktoren-Authentifizierung"
                                hide-details
                                color="success"
                                :base-color="is_edit ? 'error' : ''"
                                disabled
                                class="mb-2" />

                            <v-text-field
                                v-if="data.is_2fa"
                                variant="outlined"
                                density="comfortable"
                                rounded="lg"
                                v-model="data.email_2fa"
                                label="E-Mail 2-FA"
                                disabled />
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
                            <v-btn color="error" variant="tonal" rounded="lg" @click="abort" class="flex-1-1">
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
                        <v-btn color="error" variant="tonal" rounded="lg" @click="abort" class="flex-1-1">
                            Abbruch
                        </v-btn>
                    </v-card-actions>
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
                        <v-btn color="error" variant="tonal" rounded="lg" @click="abort" class="flex-1-1">
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
                        <v-btn color="error" variant="tonal" rounded="lg" @click="abort" class="flex-1-1">
                            Abbruch
                        </v-btn>
                    </v-card-actions>
                </v-card>

                <!-- 2FA ÄNDERN -->
                <v-card v-if="step === 'CHANGE_2FA'" rounded="xl" class="profile-card" flat>
                    <v-card-text class="pa-5">
                        <div class="profile-card__header mb-5">
                            <div class="profile-card__header-icon-wrap">
                                <v-icon size="20" icon="mdi-shield-key-outline" />
                            </div>
                            <div>
                                <div class="profile-card__header-title">2-Faktoren-Authentifizierung</div>
                                <div class="profile-card__header-sub">{{ config.user.last_name }} {{ config.user.first_name }}</div>
                            </div>
                        </div>

                        <v-form ref="form" @submit.prevent="save2Fa(data)" v-model="is_valid">
                            <v-switch
                                true-icon="mdi-check"
                                v-model="data.is_2fa"
                                label="2-Faktoren-Authentifizierung aktivieren"
                                hide-details
                                color="success"
                                :base-color="is_edit ? 'error' : ''"
                                class="mb-4" />

                            <v-text-field
                                v-if="data.is_2fa"
                                variant="outlined"
                                density="comfortable"
                                rounded="lg"
                                v-model="data.email_2fa"
                                label="E-Mail für 2-Faktor-Codes"
                                :rules="[required(), mail(), maxLength(255)]" />
                        </v-form>
                    </v-card-text>

                    <v-card-actions class="pa-5 pt-0 ga-2">
                        <v-btn color="success" variant="flat" rounded="lg" @click="save2Fa(data)" class="flex-1-1">
                            <v-icon size="16" class="mr-1">mdi-check</v-icon>
                            Speichern
                        </v-btn>
                        <v-btn color="error" variant="tonal" rounded="lg" @click="abort2Fa" class="flex-1-1">
                            Abbruch
                        </v-btn>
                    </v-card-actions>
                </v-card>

                <!-- 2FA abgeschaltet -->
                <v-card v-if="step === 'TWO_FA_DELETE'" rounded="xl" class="profile-card" flat>
                    <v-card-text class="pa-5">
                        <div class="profile-card__header mb-5">
                            <div class="profile-card__header-icon-wrap profile-card__header-icon-wrap--warning">
                                <v-icon size="20" icon="mdi-shield-off-outline" />
                            </div>
                            <div>
                                <div class="profile-card__header-title">2-Faktor-Auth deaktiviert</div>
                                <div class="profile-card__header-sub">{{ config.user.last_name }} {{ config.user.first_name }}</div>
                            </div>
                        </div>
                        <v-alert type="success" variant="tonal" rounded="lg" density="compact">
                            Die Zwei-Faktoren-Authentifizierung wurde ausgeschaltet.
                        </v-alert>
                    </v-card-text>
                    <v-card-actions class="pa-5 pt-0">
                        <v-btn block color="primary" variant="flat" rounded="lg" @click="abort2Fa">Fertig</v-btn>
                    </v-card-actions>
                </v-card>

                <!-- 2FA Code erfassen -->
                <v-card v-if="step === 'TWO_FA_EMAIL_IS_NEW' || step === 'TWO_FA_EMAIL_MUST_BE_VERIFIED'" rounded="xl" class="profile-card" flat>
                    <v-card-text class="pa-5">
                        <div class="profile-card__header mb-5">
                            <div class="profile-card__header-icon-wrap">
                                <v-icon size="20" icon="mdi-shield-check-outline" />
                            </div>
                            <div>
                                <div class="profile-card__header-title">2-FA E-Mail bestätigen</div>
                                <div class="profile-card__header-sub">{{ config.user.last_name }} {{ config.user.first_name }}</div>
                            </div>
                        </div>

                        <v-alert type="info" variant="tonal" rounded="lg" class="mb-4" density="compact">
                            Bitte prüfen Sie Ihre E-Mails: {{ data.email_2fa }}
                        </v-alert>

                        <v-form ref="form" v-model="is_valid">
                            <div class="profile-card__otp-label mb-2">Code aus der E-Mail eingeben</div>
                            <v-otp-input autofocus v-model="data.token_2fa" />
                        </v-form>
                    </v-card-text>

                    <v-card-actions class="pa-5 pt-0 ga-2">
                        <v-btn color="success" variant="flat" rounded="lg" @click="save2FaWithCode(data)" class="flex-1-1">
                            <v-icon size="16" class="mr-1">mdi-arrow-right</v-icon>
                            Weiter
                        </v-btn>
                        <v-btn color="error" variant="tonal" rounded="lg" @click="abort2Fa" class="flex-1-1">
                            Abbruch
                        </v-btn>
                    </v-card-actions>
                </v-card>

                <!-- 2FA aktiviert -->
                <v-card v-if="step === 'TWO_FA_SET' || step === 'TWO_FA_OK'" rounded="xl" class="profile-card" flat>
                    <v-card-text class="pa-5">
                        <div class="profile-card__header mb-5">
                            <div class="profile-card__header-icon-wrap profile-card__header-icon-wrap--success">
                                <v-icon size="20" icon="mdi-shield-check" />
                            </div>
                            <div>
                                <div class="profile-card__header-title">2-Faktor-Auth aktiviert</div>
                                <div class="profile-card__header-sub">{{ config.user.last_name }} {{ config.user.first_name }}</div>
                            </div>
                        </div>
                        <v-alert type="success" variant="tonal" rounded="lg" density="compact">
                            Die Zwei-Faktoren-Authentifizierung wurde eingeschaltet.
                        </v-alert>
                    </v-card-text>
                    <v-card-actions class="pa-5 pt-0">
                        <v-btn block color="primary" variant="flat" rounded="lg" @click="abort2Fa">Fertig</v-btn>
                    </v-card-actions>
                </v-card>

            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useUserStore } from '@/stores/admin/UserStore'
import { useNavigationStore } from '@/stores/admin/NavigationStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { AdminSectionHero },

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
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'is_loading', 'show_navigation_drawer', 'load_config']),
        ...mapWritableState(useUserStore, ['item', 'api_answer']),

        is2FaStep() {
            return ['CHANGE_2FA', 'TWO_FA_DELETE', 'TWO_FA_EMAIL_IS_NEW', 'TWO_FA_EMAIL_MUST_BE_VERIFIED', 'TWO_FA_SET', 'TWO_FA_OK'].includes(this.step)
        },

        heroChips() {
            const chips = []
            if (this.data?.email) {
                chips.push({ key: 'email', text: this.data.email, icon: 'mdi-email-outline' })
            }
            chips.push({
                key: '2fa',
                text: this.data?.is_2fa ? '2-FA aktiv' : '2-FA inaktiv',
                icon: this.data?.is_2fa ? 'mdi-shield-check-outline' : 'mdi-shield-off-outline',
                color: this.data?.is_2fa ? 'success' : 'white',
                variant: this.data?.is_2fa ? 'flat' : 'tonal',
            })
            return chips
        },

        activeSection() {
            const sections = {
                '': { icon: 'mdi-account-outline', label: 'Profildaten', note: 'Name und E-Mail-Adresse bearbeiten.' },
                INPUT_CODE: { icon: 'mdi-email-check-outline', label: 'E-Mail bestätigen', note: 'Änderung per Code verifizieren.' },
                CHANGE_PASSWORD: { icon: 'mdi-lock-outline', label: 'Kennwort ändern', note: 'Neues Kennwort festlegen.' },
                PASSWORD_ENTER_TOKEN: { icon: 'mdi-lock-check-outline', label: 'Kennwort bestätigen', note: 'Änderung per Code verifizieren.' },
                CHANGE_2FA: { icon: 'mdi-shield-key-outline', label: '2-Faktor-Auth', note: 'Zwei-Faktor-Authentifizierung konfigurieren.' },
                TWO_FA_DELETE: { icon: 'mdi-shield-off-outline', label: '2-FA deaktiviert', note: 'Zwei-Faktor-Authentifizierung ausgeschaltet.' },
                TWO_FA_EMAIL_IS_NEW: { icon: 'mdi-shield-check-outline', label: '2-FA E-Mail bestätigen', note: 'Code aus der E-Mail eingeben.' },
                TWO_FA_EMAIL_MUST_BE_VERIFIED: { icon: 'mdi-shield-check-outline', label: '2-FA E-Mail bestätigen', note: 'Code aus der E-Mail eingeben.' },
                TWO_FA_SET: { icon: 'mdi-shield-check', label: '2-FA aktiviert', note: 'Zwei-Faktor-Authentifizierung eingeschaltet.' },
                TWO_FA_OK: { icon: 'mdi-shield-check', label: '2-FA aktiviert', note: 'Zwei-Faktor-Authentifizierung eingeschaltet.' },
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

        abort2Fa() {
            this.step = ''
            this.data = JSON.parse(JSON.stringify(this.item))
        },

        wantToChangePassword() {
            this.abort()
            this.data = {}
            this.step = 'CHANGE_PASSWORD'
        },

        wantToChange2Fa() {
            this.abort()
            this.step = 'CHANGE_2FA'
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

        async save2Fa(data) {
            await this.$refs.form.validate()
            if (!this.is_valid) return
            const result = await this.userStore.save2Fa(data)
            if (result) {
                this.step = result
            }
        },

        async save2FaWithCode(data) {
            await this.$refs.form.validate()
            if (!this.is_valid) return
            const result = await this.userStore.save2FaWithCode(data)
            if (result) {
                this.step = result
            }
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
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
}

.profile-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.profile-nav__button {
    height: 40px !important;
    padding: 0 14px;
    text-transform: none;
    letter-spacing: 0;
    justify-content: flex-start;
}

.profile-nav__button-copy {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.profile-nav__button-title {
    font-weight: 650;
    font-size: 0.92rem;
}

.profile-nav__button-meta {
    font-size: 0.72rem;
    opacity: 0.85;
}

.profile-card {
    border: 1px solid rgba(148, 163, 184, 0.14);
    background: rgba(30, 41, 59, 0.82) !important;
    backdrop-filter: blur(4px);
    color: #e2e8f0 !important;
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
    background: rgba(99, 102, 241, 0.18);
    color: #818cf8;
    flex-shrink: 0;
}

.profile-card__header-icon-wrap--success {
    background: rgba(34, 197, 94, 0.16);
    color: #4ade80;
}

.profile-card__header-icon-wrap--warning {
    background: rgba(251, 146, 60, 0.16);
    color: #fb923c;
}

.profile-card__header-title {
    font-size: 1rem;
    font-weight: 700;
    color: #f1f5f9;
    line-height: 1.2;
}

.profile-card__header-sub {
    font-size: 0.78rem;
    color: #94a3b8;
    margin-top: 2px;
}

.profile-card__info-block {
    border: 1px solid rgba(148, 163, 184, 0.14);
    border-radius: 10px;
    padding: 12px 14px;
    background: rgba(15, 23, 42, 0.5);
}

.profile-card__info-row {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.88rem;
    color: #cbd5e1;
    padding: 3px 0;
}

.profile-card__info-label {
    font-size: 0.76rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #64748b;
    min-width: 44px;
}

.profile-card__info-value--new {
    color: #818cf8;
    font-weight: 600;
}

.profile-card__otp-label {
    font-size: 0.78rem;
    color: #94a3b8;
    letter-spacing: 0.04em;
}
</style>
