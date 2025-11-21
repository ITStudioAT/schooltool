<template>
    <v-container fluid class="h-100 w-100 d-flex flex-column align-center justify-center" v-if="config">
        <v-card class="mx-auto w-100" max-width="600" tile flat color="primary">
            <v-card-title class="d-flex flex-row align-center">
                <img :src="`/storage/images/${config?.school?.logo}`" alt="Logo" class="logo" v-if="config?.school?.logo" />
                <div class="ml-2"></div>
            </v-card-title>

            <!-- ANZEIGE DER SCHULE-->
            <v-card-subtitle class="d-flex flex-row align-center justify-space-between">
                <div>
                    {{ config?.school?.long_name }}
                </div>
            </v-card-subtitle>
            <v-card-text class="text-body-1 font-weight-bold" v-if="registers.length == 0">
                <div>Keine Anmeldung aktiv!</div>
                <div class="d-flex flex-row align-center justify-space-between mt-4">
                    <v-btn color="warning" slim flat rounded="0" to="/" tabindex="3">Zurück</v-btn>
                </div>
            </v-card-text>
        </v-card>

        <!-- Registrierung muss ausgewählt werden, weil es mehr als eine gibt und noch keine active_register vorhanden ist -->
        <v-card class="mx-auto w-100" max-width="600" tile flat color="primary" v-if="data.step == 'SELECT_REGISTER'">
            <v-form ref="form" class="mb-4" @submit.prevent="selectRegister">
                <v-card-title class="d-flex flex-row align-center justify-space-between">
                    <div>Anmeldetool</div>
                </v-card-title>
                <v-card-text>
                    <div class="text-h6">Bitte die Registrierung auswählen</div>
                    <v-autocomplete v-model="selected_register_id" :items="registers" item-title="name" item-value="id" label="Auswahl Registrierung" />
                    <v-btn block color="success" slim flat rounded="0" @click="selectRegister" v-if="selected_register_id">Weiter</v-btn>
                </v-card-text>
            </v-form>
        </v-card>

        <!-- active_register vorhanden -->
        <v-card class="mx-auto w-100" max-width="600" tile flat color="primary" v-if="active_register">
            <v-card-title class="d-flex flex-row align-center justify-space-between">
                <div>{{ active_register.name }}</div>
            </v-card-title>

            <!-- description_on_website anzeigen -->
            <v-alert closable tile color="primary" border="start" border-color="secondary" class="mt-4" v-if="active_register.description_on_website">
                <div style="white-space: pre-line">
                    {{ active_register.description_on_website }}
                </div>
            </v-alert>
        </v-card>

        <!-- E-Mail erfassen -->
        <v-card class="mx-auto w-100" max-width="600" tile flat color="primary" v-if="active_register && data.step == 'EMAIL'">
            <v-card-title class="d-flex flex-row align-center justify-space-between">Ihre E-Mail-Adresse</v-card-title>

            <v-card-text>
                <v-form ref="form" v-model="is_valid" @submit.prevent="checkEmail(data)" class="mb-4">
                    <v-text-field autofocus v-model="data.email" label="Ihre E-Mail-Adresse" :rules="[required(), mail()]" tabindex="1" />
                    <div class="d-flex flex-row align-center justify-space-between">
                        <v-btn color="warning" slim flat rounded="0" to="/" tabindex="3">Zurück</v-btn>
                        <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.email" tabindex="2">Weiter</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>

        <!-- Code zur E-Mail-Bestätigung bei neuem Benutzer -->
        <v-card class="mx-auto w-100" max-width="600" tile flat color="primary" v-if="active_register && data.step == 'EMAIL_TOKEN'">
            <v-card-title class="d-flex flex-row align-center justify-space-between">Ihre E-Mail-Adresse bestätigen</v-card-title>
            <v-card-subtitle class="d-flex flex-row align-center justify-space-between">
                {{ data.email }}
            </v-card-subtitle>
            <v-card-text>
                <v-form ref="form" v-model="is_valid" @submit.prevent="confirmEmail(data)" class="mb-4">
                    <v-alert closable color="success" type="info" text="Bitte prüfen Sie Ihre E-Mails" />
                    <div class="text-body-1 mt-2">Bitte den Code laut E-Mail eingeben:</div>
                    <v-otp-input autofocus v-model="data.token_2fa" />

                    <div class="d-flex flex-row align-center justify-space-between">
                        <v-btn color="warning" slim flat rounded="0" @click="startRegister">Neustart</v-btn>
                        <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.email">Weiter</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>

        <!-- Namen des Elternteils erfassen -->
        <v-card class="mx-auto w-100" max-width="600" tile flat color="primary" v-if="active_register && (data.step == 'ENTER_USER_DATA' || data.step == 'OK')">
            <v-card-title class="d-flex flex-row align-center justify-space-between">Ihr Name (nicht vom Kind!)</v-card-title>

            <v-card-text>
                <v-form ref="form" v-model="is_valid" @submit.prevent="saveUserData(data)" class="mb-4">
                    <v-text-field autofocus v-model="data.last_name" label="Ihr Nachname" :rules="[required(), maxLength(255)]" />
                    <v-text-field v-model="data.first_name" label="Ihr Vorname" :rules="[maxLength(255)]" />
                    <v-text-field
                        v-model="data.phone"
                        label="Ihre Telefonnummer"
                        :rules="[active_register.must_phone ? required() : () => true, minLength(8), maxLength(255)]"
                        v-if="active_register.show_phone" />
                    <div class="d-flex flex-row align-center justify-space-between">
                        <v-btn color="warning" slim flat rounded="0" @click="startRegister">Neustart</v-btn>
                        <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.email">Weiter</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>

        <!-- Code für Login -->
        <v-card class="mx-auto w-100" max-width="600" tile flat color="primary" v-if="active_register && data.step == 'LOGIN_TOKEN'">
            <v-card-title class="d-flex flex-row align-center justify-space-between">Code für Login</v-card-title>
            <v-card-subtitle class="d-flex flex-row align-center justify-space-between">
                {{ data.email }}
            </v-card-subtitle>
            <v-card-text>
                <v-form ref="form" v-model="is_valid" @submit.prevent="loginToken(data)" class="mb-4">
                    <v-alert closable color="success" type="info" text="Bitte prüfen Sie Ihre E-Mails" />
                    <div class="text-body-1 mt-2">Bitte den Code laut E-Mail eingeben:</div>
                    <v-otp-input autofocus v-model="data.token_2fa" />

                    <div class="d-flex flex-row align-center justify-space-between">
                        <v-btn color="warning" slim flat rounded="0" @click="startRegister">Neustart</v-btn>
                        <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.email">Weiter</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </v-container>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useRegisterStore } from '@/stores/homepage/RegisterStore'
import { useParticles } from '@/composables/useParticles'
export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: {},

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

    mounted() {
        // Particle System initialisieren
        const particleSystem = useParticles({
            count: 50,
            lineOpacity: 0.15,
            connectionDistance: 120,
            speed: 0.3,
        })

        this.$nextTick(() => {
            particleSystem.init(this.$refs.particleCanvas)
        })

        window.addEventListener('resize', particleSystem.resizeCanvas)

        // Cleanup speichern
        this._particleCleanup = particleSystem.cleanup
    },

    unmounted() {
        if (this._particleCleanup) {
            this._particleCleanup()
        }
    },

    data() {
        return {
            homepageStore: null,
            registerStore: null,
            school_name: '',
            app_name: '',
            is_valid: false,
            _particleCleanup: null,
        }
    },

    computed: {
        ...mapWritableState(useRegisterStore, ['config', 'registers', 'active_register', 'selected_register_id', 'data']),
    },

    watch: {},
    methods: {
        async confirmEmail(data) {
            if (!this.registerStore.confirmEmail(data)) return
        },

        async loginToken(data) {
            if (!(await this.registerStore.loginToken(data))) return

            this.$router.push('/homepage/register2')
        },

        async saveUserData(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            if (!(await this.registerStore.saveUserData(data))) return

            this.$router.push('/homepage/register2')
        },

        async checkEmail(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            this.data.register_id = this.active_register.id
            if (!(await this.registerStore.checkEmail(data))) return
        },
        startRegister() {
            this.data = {}
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
.logo {
    display: block;
    max-height: 90px; /* or 2em, relative to font size */
    height: auto;
    width: auto;
    object-fit: contain;
}
</style>
