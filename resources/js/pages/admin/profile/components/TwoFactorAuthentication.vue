<template>
    <v-card rounded="xl" class="profile-card" flat>
        <v-card-text class="pa-5">
            <div class="d-flex align-center ga-3 mb-5">
                <v-avatar color="primary" variant="tonal" rounded="lg">
                    <v-icon icon="mdi-shield-key-outline" />
                </v-avatar>
                <div>
                    <div class="text-h6 font-weight-bold">Two-factor authentication</div>
                    <div class="text-body-2 text-medium-emphasis">Zusätzlicher Schutz für Ihr Benutzerkonto</div>
                </div>
                <v-chip :color="status.enabled ? 'success' : status.pending ? 'warning' : 'default'" class="ml-auto" size="small">
                    {{ status.enabled ? 'Aktiviert' : status.pending ? 'Einrichtung offen' : 'Deaktiviert' }}
                </v-chip>
            </div>

            <v-alert v-if="errorMessage" type="error" variant="tonal" rounded="lg" class="mb-4" role="alert">
                {{ errorMessage }}
            </v-alert>
            <v-alert v-if="successMessage" type="success" variant="tonal" rounded="lg" class="mb-4" role="status">
                {{ successMessage }}
            </v-alert>

            <template v-if="setup.qrCodeSvg">
                <p class="text-body-1 mb-4">
                    Scannen Sie den QR-Code mit Ihrer Authenticator-App und geben Sie anschließend den angezeigten sechsstelligen Code ein.
                </p>
                <v-row dense>
                    <v-col cols="12" md="5">
                        <div
                            class="two-factor-qr pa-4 rounded-lg border"
                            role="img"
                            aria-label="QR-Code für die Authenticator-App"
                            v-html="setup.qrCodeSvg" />
                    </v-col>
                    <v-col cols="12" md="7">
                        <v-alert type="info" variant="tonal" density="comfortable" rounded="lg" class="mb-4">
                            Sie können zum Beispiel Microsoft Authenticator, Google Authenticator oder 2FAS Authenticator verwenden.
                            Auch andere Apps für zeitbasierte Einmalcodes (TOTP) sind kompatibel.
                        </v-alert>
                        <div class="text-caption text-medium-emphasis mb-1">Manueller Einrichtungsschlüssel</div>
                        <code class="two-factor-key d-block pa-3 rounded-lg mb-4">{{ setup.manualKey }}</code>
                        <label class="text-body-2 font-weight-medium" for="two-factor-confirmation-code">Sechsstelliger Code</label>
                        <v-otp-input
                            id="two-factor-confirmation-code"
                            v-model="confirmationCode"
                            :error="Boolean(fieldError)"
                            :disabled="submitting"
                            length="6"
                            type="number"
                            autofocus
                            class="mt-2"
                            @finish="confirmSetup" />
                        <div v-if="fieldError" class="text-error text-caption mt-1" role="alert">{{ fieldError }}</div>
                    </v-col>
                </v-row>
                <div class="d-flex flex-wrap ga-2 mt-4">
                    <v-btn color="success" variant="flat" :loading="submitting" @click="confirmSetup">
                        Aktivierung bestätigen
                    </v-btn>
                    <v-btn color="warning" variant="text" :disabled="submitting" @click="requestPassword('cancel')">
                        Einrichtung abbrechen
                    </v-btn>
                </div>
            </template>

            <template v-else-if="recoveryCodes.length">
                <v-alert type="warning" variant="tonal" rounded="lg" class="mb-4">
                    Bewahren Sie diese Wiederherstellungscodes sicher auf. Jeder Code kann nur einmal verwendet werden.
                </v-alert>
                <div class="two-factor-codes pa-4 rounded-lg border mb-4" aria-label="Wiederherstellungscodes">
                    <code v-for="code in recoveryCodes" :key="code">{{ code }}</code>
                </div>
                <div class="d-flex flex-wrap ga-2">
                    <v-btn variant="tonal" prepend-icon="mdi-content-copy" @click="copyRecoveryCodes">Codes kopieren</v-btn>
                    <v-btn variant="tonal" prepend-icon="mdi-download" @click="downloadRecoveryCodes">Codes herunterladen</v-btn>
                    <v-btn color="primary" variant="text" @click="closeRecoveryCodes">Fertig</v-btn>
                </div>
            </template>

            <template v-else-if="status.enabled">
                <v-alert type="success" variant="tonal" rounded="lg" class="mb-4">
                    Die Zwei-Faktor-Authentifizierung ist für Ihr Benutzerkonto aktiviert.
                    <span v-if="formattedConfirmedAt"> Aktiviert am {{ formattedConfirmedAt }}.</span>
                </v-alert>
                <div class="d-flex flex-wrap ga-2">
                    <v-btn color="primary" variant="tonal" @click="requestPassword('viewRecovery')">Wiederherstellungscodes anzeigen</v-btn>
                    <v-btn color="primary" variant="text" @click="requestPassword('regenerate')">Codes neu erzeugen</v-btn>
                    <v-btn color="error" variant="text" @click="requestPassword('disable')">2FA deaktivieren</v-btn>
                </div>
            </template>

            <template v-else>
                <p class="text-body-1 mb-3">
                    Schützen Sie Ihr Benutzerkonto mit einem Code aus einer Authenticator-App. Nach dem Kennwort geben Sie zusätzlich einen sechsstelligen Code ein.
                </p>
                <p class="text-body-2 text-medium-emphasis mb-2">
                    Sie können zum Beispiel eine der folgenden Authenticator-Apps verwenden:
                </p>
                <ul class="text-body-2 text-medium-emphasis pl-6 mb-3">
                    <li>Microsoft Authenticator</li>
                    <li>Google Authenticator</li>
                    <li>2FAS Authenticator</li>
                </ul>
                <p class="text-body-2 text-medium-emphasis mb-5">
                    Auch andere Apps für zeitbasierte Einmalcodes (TOTP) sind kompatibel. SMS wird nicht verwendet.
                </p>
                <v-btn color="success" variant="flat" prepend-icon="mdi-shield-plus-outline" @click="requestPassword('enable')">
                    Zwei-Faktor-Authentifizierung aktivieren
                </v-btn>
            </template>
        </v-card-text>

        <v-dialog v-model="passwordDialog" persistent max-width="460">
            <v-card rounded="xl">
                <v-card-title class="pt-5 px-5">Kennwort bestätigen</v-card-title>
                <v-card-text class="px-5">
                    <p class="text-body-2 text-medium-emphasis mb-4">Bestätigen Sie aus Sicherheitsgründen Ihr aktuelles Kennwort.</p>
                    <v-form @submit.prevent="confirmPassword">
                        <v-text-field
                            v-model="password"
                            label="Aktuelles Kennwort"
                            type="password"
                            variant="outlined"
                            autocomplete="current-password"
                            autofocus
                            :error-messages="passwordError ? [passwordError] : []" />
                    </v-form>
                </v-card-text>
                <v-card-actions class="px-5 pb-5">
                    <v-spacer />
                    <v-btn variant="text" :disabled="submitting" @click="closePasswordDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" :loading="submitting" @click="confirmPassword">Bestätigen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-card>
</template>

<script>
import { useUserStore } from '@/stores/admin/UserStore'

export default {
    emits: ['updated'],

    data() {
        return {
            userStore: null,
            status: { enabled: false, pending: false, confirmed_at: null },
            setup: { qrCodeSvg: '', manualKey: '' },
            recoveryCodes: [],
            confirmationCode: '',
            password: '',
            pendingAction: null,
            passwordDialog: false,
            submitting: false,
            errorMessage: '',
            fieldError: '',
            passwordError: '',
            successMessage: '',
        }
    },

    computed: {
        formattedConfirmedAt() {
            if (!this.status.confirmed_at) return ''

            return new Intl.DateTimeFormat('de-AT', { dateStyle: 'medium' }).format(new Date(this.status.confirmed_at))
        },
    },

    async mounted() {
        this.userStore = useUserStore()
        await this.loadStatus()
    },

    unmounted() {
        this.clearSensitiveState()
    },

    methods: {
        async loadStatus() {
            try {
                this.applyStatus(await this.userStore.twoFactorStatus())
            } catch (error) {
                this.handleError(error)
            }
        },

        requestPassword(action) {
            this.errorMessage = ''
            this.passwordError = ''
            this.password = ''
            this.pendingAction = action
            this.passwordDialog = true
        },

        closePasswordDialog() {
            this.password = ''
            this.passwordError = ''
            this.pendingAction = null
            this.passwordDialog = false
        },

        async confirmPassword() {
            if (!this.password || this.submitting) return

            this.submitting = true
            this.passwordError = ''
            const action = this.pendingAction

            try {
                await this.userStore.confirmCurrentPassword(this.password)
                this.password = ''
                this.passwordDialog = false
                this.submitting = false
                if (action === 'confirmSetup') {
                    await this.confirmSetup()
                } else {
                    await this.performAction(action)
                }
            } catch (error) {
                this.passwordError = this.validationMessage(error, 'password')
                if (!this.passwordError) this.handleError(error)
            } finally {
                this.submitting = false
            }
        },

        async performAction(action) {
            this.errorMessage = ''
            this.successMessage = ''

            if (action === 'enable') {
                const response = await this.userStore.enableTwoFactorAuthentication()
                this.applyStatus(response)
                this.setup = { qrCodeSvg: response.qr_code_svg, manualKey: response.manual_key }
            } else if (action === 'cancel') {
                this.applyStatus(await this.userStore.cancelTwoFactorSetup())
                this.clearSensitiveState()
                this.successMessage = 'Die unvollständige Einrichtung wurde abgebrochen.'
            } else if (action === 'viewRecovery') {
                const response = await this.userStore.loadTwoFactorRecoveryCodes()
                this.recoveryCodes = response.recovery_codes || []
            } else if (action === 'regenerate') {
                const response = await this.userStore.regenerateTwoFactorRecoveryCodes()
                this.recoveryCodes = response.recovery_codes || []
                this.successMessage = 'Neue Wiederherstellungscodes wurden erzeugt. Die bisherigen Codes sind ungültig.'
            } else if (action === 'disable') {
                this.applyStatus(await this.userStore.disableTwoFactorAuthentication())
                this.clearSensitiveState()
                this.successMessage = 'Die Zwei-Faktor-Authentifizierung wurde deaktiviert.'
            }

            this.pendingAction = null
        },

        async confirmSetup() {
            const code = String(this.confirmationCode || '').replace(/\s+/g, '')
            if (!/^\d{6}$/.test(code) || this.submitting) {
                this.fieldError = 'Bitte geben Sie einen sechsstelligen Code ein.'
                return
            }

            this.submitting = true
            this.fieldError = ''
            this.errorMessage = ''

            try {
                const response = await this.userStore.confirmTwoFactorAuthentication(code)
                this.applyStatus(response)
                this.setup = { qrCodeSvg: '', manualKey: '' }
                this.confirmationCode = ''
                this.recoveryCodes = response.recovery_codes || []
                this.successMessage = 'Die Zwei-Faktor-Authentifizierung wurde aktiviert.'
            } catch (error) {
                if (error.response?.status === 423) {
                    this.requestPassword('confirmSetup')
                } else {
                    this.fieldError = this.validationMessage(error, 'code') || 'Der eingegebene Code ist ungültig.'
                }
            } finally {
                this.submitting = false
            }
        },

        async copyRecoveryCodes() {
            await navigator.clipboard.writeText(this.recoveryCodes.join('\n'))
            this.successMessage = 'Die Wiederherstellungscodes wurden kopiert.'
        },

        downloadRecoveryCodes() {
            const blob = new Blob([this.recoveryCodes.join('\n') + '\n'], { type: 'text/plain;charset=utf-8' })
            const url = URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = url
            link.download = 'schooltool-wiederherstellungscodes.txt'
            link.click()
            URL.revokeObjectURL(url)
        },

        closeRecoveryCodes() {
            this.recoveryCodes = []
            this.successMessage = ''
        },

        applyStatus(response) {
            this.status = {
                enabled: Boolean(response.enabled),
                pending: Boolean(response.pending),
                confirmed_at: response.confirmed_at || null,
            }
            this.$emit('updated', this.status)
        },

        validationMessage(error, field) {
            return error.response?.data?.errors?.[field]?.[0] || ''
        },

        handleError(error) {
            const status = error.response?.status
            this.errorMessage = status === 429
                ? 'Zu viele Versuche. Bitte warten Sie kurz und versuchen Sie es erneut.'
                : error.response?.data?.message || 'Die Aktion konnte nicht durchgeführt werden.'
        },

        clearSensitiveState() {
            this.password = ''
            this.confirmationCode = ''
            this.setup = { qrCodeSvg: '', manualKey: '' }
            this.recoveryCodes = []
        },
    },
}
</script>

<style scoped>
.two-factor-qr {
    display: grid;
    place-items: center;
    background: white;
}

.two-factor-qr :deep(svg) {
    display: block;
    width: min(100%, 220px);
    height: auto;
}

.two-factor-key {
    overflow-wrap: anywhere;
    background: rgba(var(--v-theme-on-surface), 0.06);
    letter-spacing: 0.08em;
}

.two-factor-codes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 10px;
    background: rgba(var(--v-theme-on-surface), 0.04);
}
</style>
