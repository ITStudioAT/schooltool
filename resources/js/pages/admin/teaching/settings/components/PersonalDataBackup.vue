<template>
    <ItsGridBox variant="overview" color="primary" title="Datensicherung" icon="mdi-database-check-outline" class="w-100">
        <p class="text-body-2 mb-3">
            Sichern Sie Ihren persönlichen Unterrichtsstand für alle Schuljahre dieser Schule: Kurse,
            Schülerzuordnungen, Beurteilungen, Einträge, Anwesenheit, Lehrpläne mit eigenen Dateien,
            Einstellungen und eigene freie Tage.
        </p>
        <p class="text-caption mb-3">
            Größenlimits: insgesamt 25 MiB eigene Dateien, 50 MiB Sicherungsinhalt; E-Mail-Anhang bis 10 MiB.
            Größere E-Mail-Anhänge bleiben zum Download verfügbar. Beim Überschreiten des Sicherungslimits wird keine unvollständige Sicherung erstellt.
        </p>
        <p class="text-body-2 mb-3">
            Neue Sicherungen werden automatisch an Ihre hinterlegte E-Mail-Adresse gesendet.
            Sie können jede Sicherung herunterladen und später hier wieder hochladen.
            Die verschlüsselte Datei ist nur mit Ihrem Konto und dem passenden Anwendungsschlüssel dieser Schooltool-Installation verwendbar.
        </p>
        <v-alert type="info" variant="tonal" class="mb-3">
            Gemeinsame Schülerkonten, zentrale Schülerstammdaten und verknüpfte Materialkarten bleiben unverändert.
            Benötigte fachliche Schülerstammdaten werden mitgesichert. Wurden zentrale Konten gelöscht,
            muss die Administration die gespeicherten Schüler vor dem Einspielen wieder zuordnen.
        </v-alert>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-3" role="alert">{{ error }}</v-alert>
        <v-alert v-if="message" type="success" variant="tonal" class="mb-3" role="status">{{ message }}</v-alert>

        <div class="d-flex flex-wrap ga-2 mb-4">
            <v-btn color="primary" prepend-icon="mdi-database-plus-outline" :loading="action === 'create'" :disabled="busy" @click="createBackup">
                Sicherung erstellen
            </v-btn>
            <v-btn variant="text" :disabled="busy" @click="loadBackups">Aktualisieren</v-btn>
        </div>

        <v-file-input v-model="file" accept=".schooltool" label="Sicherungsdatei auswählen" :disabled="busy" hide-details="auto" />
        <v-btn class="mt-2 mb-4" variant="tonal" :disabled="busy || !selectedFile" :loading="action === 'import'" @click="importBackup">
            Sicherungsdatei hochladen
        </v-btn>

        <p v-if="!loading && !backups.length" class="text-body-2">Noch keine Sicherungen vorhanden.</p>
        <p v-if="loading" role="status">Sicherungen werden geladen …</p>
        <article v-for="backup in backups" :key="backup.id" class="mb-4 pa-3 border rounded">
            <div class="text-subtitle-2">Sicherung vom {{ formatDate(backup.created_at) }}</div>
            <div class="text-caption mb-2">Alle Schuljahre · {{ backup.summary?.courses ?? 0 }} Kurse</div>
            <p v-if="backup.mail_message" class="text-body-2 mb-2">{{ backup.mail_message }}</p>
            <div class="d-flex flex-wrap ga-2">
                <v-btn variant="text" :href="downloadUrl(backup)" :disabled="busy" prepend-icon="mdi-download">
                    Herunterladen
                </v-btn>
                <v-btn variant="tonal" color="warning" :disabled="busy" @click="openRestore(backup)">
                    Wiederherstellen
                </v-btn>
                <v-btn variant="text" :disabled="busy" :loading="action === `recovery:${backup.id}`" @click="requestRecovery(backup)">
                    Schülerzuordnung anfragen
                </v-btn>
            </div>
            <p v-if="backup.recovery_requested_at" class="text-caption mt-2">
                Die Administration kann die benötigten Schülerzuordnungen für diese Sicherung prüfen.
            </p>
        </article>

        <PersonalBackupRecovery v-if="canReviewRecovery" />

        <v-dialog v-model="restoreDialog" persistent max-width="620">
            <v-card>
                <v-card-title class="text-wrap">Persönlichen Unterrichtsstand wiederherstellen?</v-card-title>
                <v-card-text>
                    <p class="mb-3">Sicherung vom {{ formatDate(selectedBackup?.created_at) }}</p>
                    <p class="mb-3">
                        Ihre persönlichen Unterrichtsdaten in allen Schuljahren dieser Schule werden auf diesen Stand zurückgesetzt.
                        Spätere Änderungen werden überschrieben und später hinzugefügte persönliche Unterrichtsdaten entfernt.
                        Inzwischen gelöschte persönliche Datensätze werden wiederhergestellt.
                    </p>
                    <p>Erstellen Sie bei Bedarf vorher eine Sicherung des aktuellen Standes. Anschließend wird die Seite neu geladen.</p>
                    <v-alert v-if="error" type="error" variant="tonal" class="mt-3" role="alert">{{ error }}</v-alert>
                </v-card-text>
                <v-card-actions class="flex-wrap justify-end">
                    <v-btn :disabled="action === 'restore'" @click="cancelRestore">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" :loading="action === 'restore'" :disabled="busy" @click="restoreBackup">
                        Jetzt ersetzen und wiederherstellen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </ItsGridBox>
</template>

<script>
import axios from 'axios'
import { index, store, importMethod, restore, download } from '@/actions/App/Http/Controllers/Admin/Teaching/PersonalTeachingBackupController'
import { requestRecovery as requestStudentRecovery } from '@/actions/App/Http/Controllers/Admin/Teaching/PersonalTeachingBackupRecoveryController'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import PersonalBackupRecovery from './PersonalBackupRecovery.vue'

export default {
    components: { ItsGridBox, PersonalBackupRecovery },
    props: {
        locked: { type: Boolean, default: false },
        canReviewRecovery: { type: Boolean, default: false },
    },
    data() {
        return {
            backups: [],
            loading: false,
            action: null,
            file: null,
            selectedBackup: null,
            restoreDialog: false,
            error: '',
            message: '',
        }
    },
    computed: {
        busy() {
            return this.loading || this.action !== null || this.locked
        },
        selectedFile() {
            return Array.isArray(this.file) ? this.file[0] : this.file
        },
    },
    mounted() {
        this.loadBackups()
    },
    methods: {
        async loadBackups() {
            if (this.busy) return
            this.loading = true
            this.error = ''
            try {
                const response = await axios.get(index.url())
                this.backups = response.data.data
            } catch (error) {
                this.error = this.errorMessage(error)
            } finally {
                this.loading = false
            }
        },
        async createBackup() {
            if (this.busy) return
            this.action = 'create'
            this.error = ''
            this.message = ''
            try {
                const response = await axios.post(store.url())
                this.backups.unshift(response.data.data)
                this.message = 'Ihre Sicherung wurde erstellt. Den Versandstatus finden Sie beim Eintrag.'
            } catch (error) {
                this.error = this.errorMessage(error)
            } finally {
                this.action = null
            }
        },
        async importBackup() {
            if (this.busy || !this.selectedFile) return
            this.action = 'import'
            this.error = ''
            this.message = ''
            const form = new FormData()
            form.append('backup', this.selectedFile)
            try {
                const response = await axios.post(importMethod.url(), form)
                this.backups.unshift(response.data.data)
                this.file = null
                this.message = 'Sicherungsdatei importiert. Mit „Wiederherstellen“ können Sie diesen Stand einspielen.'
            } catch (error) {
                this.error = this.errorMessage(error)
            } finally {
                this.action = null
            }
        },
        async requestRecovery(backup) {
            if (this.busy) return
            this.action = `recovery:${backup.id}`
            this.error = ''
            this.message = ''
            try {
                await axios.post(requestStudentRecovery.url(backup.id))
                backup.recovery_requested_at = new Date().toISOString()
                this.message = 'Die Sicherung steht der Administration zur Prüfung fehlender Schülerzuordnungen bereit.'
            } catch (error) {
                this.error = this.errorMessage(error)
            } finally {
                this.action = null
            }
        },
        openRestore(backup) {
            if (this.busy) return
            this.selectedBackup = backup
            this.error = ''
            this.restoreDialog = true
        },
        cancelRestore() {
            if (this.action === 'restore') return
            this.restoreDialog = false
            this.selectedBackup = null
            this.error = ''
        },
        async restoreBackup() {
            if (this.busy || !this.restoreDialog || !this.selectedBackup) return
            this.action = 'restore'
            this.error = ''
            try {
                await axios.post(restore.url(this.selectedBackup.id), { confirm_restore: true })
                this.$router.go(0)
            } catch (error) {
                this.error = this.errorMessage(error)
                this.action = null
            }
        },
        downloadUrl(backup) {
            return download.url(backup.id)
        },
        formatDate(value) {
            return value ? new Date(value).toLocaleString('de-AT') : ''
        },
        errorMessage(error) {
            const validationErrors = error.response?.data?.errors
            return Object.values(validationErrors || {}).flat()[0]
                || error.response?.data?.message
                || 'Die Aktion konnte nicht abgeschlossen werden. Bitte versuchen Sie es erneut.'
        },
    },
}
</script>
