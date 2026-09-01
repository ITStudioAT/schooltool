<template>
    <v-col cols="12" md="10" lg="8" xl="7">
        <section class="crud-shell admin-card ai-glass-panel">
            <div class="admin-card-head crud-head mb-4">
                <div>
                    <div class="admin-card-eyebrow">Schülerstundenpläne</div>
                    <h2 class="admin-card-title crud-title">Lehrerliste</h2>
                </div>

                <div class="admin-kpi-grid crud-kpis">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Gesamt</div>
                        <div class="kpi-value">{{ totalTeachersCount }}</div>
                    </div>
                </div>
            </div>

            <div class="crud-content-grid teachers-read-only-grid">
                <section class="admin-card ai-glass-panel crud-main-card pa-3">
                    <div class="empty-state crud-search-panel mb-3">
                        <SearchField :store="teachersListStore" selected_field="selected_teachers" />
                    </div>

                    <div class="teacher-bulk-actions mb-3">
                        <v-btn
                            color="primary"
                            variant="flat"
                            prepend-icon="mdi-plus"
                            size="small"
                            density="comfortable"
                            rounded="lg"
                            @click="openCreateDialog">
                            Hinzufügen
                        </v-btn>
                        <v-btn
                            color="primary"
                            variant="tonal"
                            prepend-icon="mdi-import"
                            size="small"
                            density="comfortable"
                            rounded="lg"
                            @click="openImportDialog">
                            Importieren
                        </v-btn>
                        <v-btn
                            color="success"
                            variant="tonal"
                            prepend-icon="mdi-lock-open-outline"
                            :loading="isBulkActionPending(true)"
                            :disabled="isBulkActionRunning && !isBulkActionPending(true)"
                            size="small"
                            density="comfortable"
                            rounded="lg"
                            @click="setAllTeachersActive(true)">
                            Alle aktiv
                        </v-btn>
                        <v-btn
                            color="error"
                            variant="outlined"
                            prepend-icon="mdi-lock-outline"
                            :loading="isBulkActionPending(false)"
                            :disabled="isBulkActionRunning && !isBulkActionPending(false)"
                            size="small"
                            density="comfortable"
                            rounded="lg"
                            @click="setAllTeachersActive(false)">
                            Alle inaktiv
                        </v-btn>
                    </div>

                    <div v-if="teachers.length === 0" class="empty-state pa-2">Keine Lehrer gefunden.</div>
                    <div v-else class="empty-state pa-2">
                        <v-list density="compact" variant="flat" class="crud-list">
                            <v-list-item
                                v-for="teacher in teachers"
                                :key="teacher.id"
                                class="crud-list-item">
                                <template #title>
                                    <div class="person-row crud-item-row teacher-row">
                                        <div class="person-body" style="min-width: 0">
                                            <div class="person-name d-flex align-center ga-1">
                                                <span>
                                                    {{ teacher.last_name }} {{ teacher.first_name }}<span v-if="teacher.short"> ({{ teacher.short }})</span>
                                                </span>
                                                <v-btn
                                                    icon="mdi-pencil-outline"
                                                    color="primary"
                                                    variant="text"
                                                    density="compact"
                                                    size="x-small"
                                                    class="teacher-name-edit"
                                                    :loading="isTeacherActionPending(teacher, 'edit')"
                                                    :aria-label="`Lehrkraft bearbeiten: ${teacher.last_name} ${teacher.first_name}`"
                                                    :title="`Lehrkraft bearbeiten: ${teacher.last_name} ${teacher.first_name}`"
                                                    @click.stop="openEditDialog(teacher)" />
                                            </div>
                                            <div class="person-email-row">
                                                <span class="person-email">{{ teacher.email || '-' }}</span>
                                                <v-btn
                                                    v-if="teacher.email"
                                                    :icon="copiedEmailId === teacher.id ? 'mdi-check' : 'mdi-content-copy'"
                                                    :color="copiedEmailId === teacher.id ? 'success' : undefined"
                                                    variant="text"
                                                    density="compact"
                                                    size="x-small"
                                                    class="person-email-copy"
                                                    :aria-label="copiedEmailId === teacher.id ? `E-Mail-Adresse kopiert: ${teacher.email}` : `E-Mail-Adresse kopieren: ${teacher.email}`"
                                                    :title="copiedEmailId === teacher.id ? 'Kopiert!' : `E-Mail-Adresse kopieren: ${teacher.email}`"
                                                    @click.stop="copyEmail(teacher)" />
                                                <v-chip
                                                    v-if="copiedEmailId === teacher.id"
                                                    size="x-small"
                                                    color="success"
                                                    variant="tonal"
                                                    class="person-email-copied-chip">
                                                    Kopiert
                                                </v-chip>
                                            </div>
                                        </div>

                                        <div class="teacher-row-actions" @click.stop>
                                            <div class="teacher-action-line">
                                                <v-btn
                                                    :color="teacher.is_active ? 'success' : 'error'"
                                                    :variant="teacher.is_active ? 'tonal' : 'outlined'"
                                                    :prepend-icon="teacher.is_active ? 'mdi-lock-open-outline' : 'mdi-lock-outline'"
                                                    :loading="isTeacherActionPending(teacher, 'status')"
                                                    :readonly="teacher.user_id === null"
                                                    :disabled="teacher.user_id !== null && !teacher.can_toggle_active"
                                                    size="small"
                                                    density="comfortable"
                                                    rounded="lg"
                                                    @click.stop="toggleTeacherActive(teacher)">
                                                    {{ teacher.is_active ? 'Aktiv' : 'Inaktiv' }}
                                                </v-btn>
                                            </div>

                                            <template v-if="teacher.is_active">
                                                <div class="teacher-action-line">
                                                    <v-btn
                                                        :color="isSelectedTimetableRole(teacher, 'studentstimetables_admin') ? 'primary' : undefined"
                                                        :variant="isSelectedTimetableRole(teacher, 'studentstimetables_admin') ? 'flat' : 'outlined'"
                                                        prepend-icon="mdi-shield-crown-outline"
                                                        :loading="isTeacherActionPending(teacher, 'studentstimetables_admin')"
                                                        :disabled="!teacher.can_manage_timetable_role"
                                                        size="small"
                                                        density="comfortable"
                                                        rounded="lg"
                                                        @click.stop="assignTimetableRole(teacher, 'studentstimetables_admin')">
                                                        TT-Admin
                                                    </v-btn>
                                                    <v-btn
                                                        :color="isSelectedTimetableRole(teacher, 'studentstimetables_moderator') ? 'primary' : undefined"
                                                        :variant="isSelectedTimetableRole(teacher, 'studentstimetables_moderator') ? 'flat' : 'outlined'"
                                                        prepend-icon="mdi-shield-account-outline"
                                                        :loading="isTeacherActionPending(teacher, 'studentstimetables_moderator')"
                                                        :disabled="!teacher.can_manage_timetable_role"
                                                        size="small"
                                                        density="comfortable"
                                                        rounded="lg"
                                                        @click.stop="assignTimetableRole(teacher, 'studentstimetables_moderator')">
                                                        TT-Moderator
                                                    </v-btn>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                    </div>

                    <div class="empty-state crud-pagination mt-3 pa-3">
                        <Pagination
                            :meta="meta"
                            :store="teachersListStore"
                            selected_field="selected_teachers" />
                    </div>
                </section>
            </div>
        </section>

        <v-dialog v-model="importDialog" max-width="720" persistent>
            <v-card rounded="xl">
                <v-card-title class="d-flex align-center ga-2 pa-5 pb-2">
                    <v-icon icon="mdi-database-import-outline" color="primary" />
                    Lehrer:innen importieren
                </v-card-title>

                <v-card-text class="pa-5 pt-3">
                    <div class="empty-state crud-form-section mb-4">
                        <div class="kpi-sub">
                            Es muss sich um eine Excel- oder CSV-Datei (*.xlsx, *.xls, *.csv) handeln.
                            Benötigte Spalten:
                            <strong>Kurz/Kürzel, Nachname/Familienname, Vorname, Email/EMail</strong>
                        </div>
                    </div>

                    <FileUpload
                        v-if="!importUploadFinished"
                        :path="teachersListUploadPath"
                        file-label
                        @uploadStart="importStarted"
                        @fileUploadFinished="fileUploadFinished"
                        @error="importUploadFailed" />

                    <v-alert
                        v-if="importRunning"
                        type="info"
                        variant="tonal"
                        rounded="lg"
                        title="Import läuft"
                        class="mt-4">
                        Die Lehrerliste wird importiert. Bitte warten Sie, bis die Verarbeitung abgeschlossen ist.
                        <v-progress-linear color="primary" indeterminate rounded height="6" class="mt-3" />
                    </v-alert>

                    <v-alert
                        v-else-if="importFinished"
                        :type="importStatus === 200 ? 'success' : 'error'"
                        variant="tonal"
                        rounded="lg"
                        :title="importStatus === 200 ? 'Import abgeschlossen' : 'Import fehlgeschlagen'"
                        class="mt-4">
                        {{ importMessage }}
                    </v-alert>
                </v-card-text>

                <v-card-actions class="justify-end px-5 pb-5">
                    <v-btn
                        v-if="!importFinished"
                        variant="text"
                        :disabled="importRunning"
                        @click="closeImportDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        v-else
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-check"
                        @click="finishImport">
                        Fertig
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="editDialog" max-width="640" persistent>
            <v-card rounded="xl">
                <v-card-title class="d-flex align-center ga-2 pa-5 pb-2">
                    <v-icon :icon="editingTeacher ? 'mdi-account-edit-outline' : 'mdi-account-plus-outline'" color="primary" />
                    {{ editingTeacher ? 'Lehrkraft bearbeiten' : 'Lehrkraft hinzufügen' }}
                </v-card-title>

                <v-card-text class="pa-5 pt-3">
                    <v-form @submit.prevent="saveTeacher">
                        <v-row>
                            <v-col cols="12" sm="4">
                                <v-text-field
                                    :model-value="editForm.short"
                                    label="Kürzel"
                                    maxlength="10"
                                    :error-messages="action_errors.short"
                                    variant="outlined"
                                    density="comfortable"
                                    @update:model-value="updateTeacherShort" />
                            </v-col>
                            <v-col cols="12" sm="8">
                                <v-text-field
                                    v-model="editForm.last_name"
                                    label="Nachname"
                                    :error-messages="action_errors.last_name"
                                    variant="outlined"
                                    density="comfortable"
                                    required />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="editForm.first_name"
                                    label="Vorname"
                                    :error-messages="action_errors.first_name"
                                    variant="outlined"
                                    density="comfortable" />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="editForm.email"
                                    label="E-Mail"
                                    type="email"
                                    :error-messages="action_errors.email"
                                    variant="outlined"
                                    density="comfortable"
                                    required />
                            </v-col>
                        </v-row>

                        <div class="d-flex flex-wrap justify-end ga-2 mt-2">
                            <v-btn
                                variant="text"
                                :disabled="isSavePending"
                                @click="closeEditDialog">
                                Abbrechen
                            </v-btn>
                            <v-btn
                                color="primary"
                                variant="flat"
                                prepend-icon="mdi-content-save-outline"
                                type="submit"
                                :loading="isSavePending"
                                :disabled="!canSaveTeacher">
                                Speichern
                            </v-btn>
                        </div>
                    </v-form>
                </v-card-text>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { teachersListApi } from '@/domains/teachersList/api'
import FileUpload from '@/pages/components/FileUpload.vue'
import Pagination from '@/pages/components/Pagination.vue'
import SearchField from '@/pages/components/SearchField.vue'
import { useStudentsTimetablesTeachersListStore } from '@/stores/admin/studentsTimetables/TeachersListStore'

export default {
    components: { FileUpload, Pagination, SearchField },

    async beforeMount() {
        this.teachersListStore = useStudentsTimetablesTeachersListStore()
        await this.teachersListStore.index()
    },

    data() {
        return {
            teachersListStore: null,
            importDialog: false,
            importUploadFinished: false,
            importRunning: false,
            importFinished: false,
            importStatus: null,
            importMessage: '',
            importStatusPollTimer: null,
            importStatusPollInFlight: false,
            editDialog: false,
            editingTeacher: null,
            copiedEmailId: null,
            copyEmailResetTimeout: null,
            editForm: {
                short: '',
                last_name: '',
                first_name: '',
                email: '',
            },
        }
    },

    beforeUnmount() {
        window.removeEventListener('teachers-list-import-finished', this.handleImportFinished)
        this.stopImportStatusPolling()

        if (this.copyEmailResetTimeout) {
            clearTimeout(this.copyEmailResetTimeout)
        }
    },

    mounted() {
        window.addEventListener('teachers-list-import-finished', this.handleImportFinished)
    },

    computed: {
        ...mapWritableState(useStudentsTimetablesTeachersListStore, ['teachers', 'meta', 'pending_action', 'action_errors']),
        totalTeachersCount() {
            const total = Number(this.meta?.total)

            return Number.isFinite(total) && total >= 0 ? total : this.teachers.length
        },
        isBulkActionRunning() {
            return this.pending_action?.startsWith('bulk:') ?? false
        },
        isSavePending() {
            const actionKey = this.editingTeacher === null ? 'create' : `edit:${this.editingTeacher.id}`

            return this.pending_action === actionKey
        },
        canSaveTeacher() {
            return this.editForm.last_name.trim() !== '' && this.editForm.email.trim() !== ''
        },
        teachersListUploadPath() {
            return teachersListApi.upload()
        },
    },

    methods: {
        openImportDialog() {
            this.resetImportState()
            this.importDialog = true
        },

        closeImportDialog() {
            if (this.importRunning) { return }

            this.resetImportState()
            this.importDialog = false
        },

        importStarted() {
            this.stopImportStatusPolling()
            this.importRunning = true
            this.importFinished = false
            this.importStatus = null
            this.importMessage = ''
        },

        fileUploadFinished() {
            this.importUploadFinished = true
            this.startImportStatusPolling()
        },

        importUploadFailed() {
            this.stopImportStatusPolling()
            this.importRunning = false
            this.importFinished = false
        },

        async handleImportFinished(event) {
            await this.applyImportCompletion(event.detail || {})
        },

        startImportStatusPolling() {
            this.stopImportStatusPolling()

            if (!this.importRunning) { return }

            this.pollImportStatus()
            this.importStatusPollTimer = window.setInterval(() => this.pollImportStatus(), 1000)
        },

        stopImportStatusPolling() {
            if (this.importStatusPollTimer !== null) {
                window.clearInterval(this.importStatusPollTimer)
            }

            this.importStatusPollTimer = null
            this.importStatusPollInFlight = false
        },

        async pollImportStatus() {
            if (!this.importRunning || this.importStatusPollInFlight) { return }

            this.importStatusPollInFlight = true

            try {
                const response = await axios.get(teachersListApi.importStatus())

                if (response.data?.state === 'finished') {
                    await this.applyImportCompletion(response.data)
                }
            } catch {
                return
            } finally {
                this.importStatusPollInFlight = false
            }
        },

        async applyImportCompletion(payload) {
            if (!this.importRunning) { return }

            this.stopImportStatusPolling()
            this.importUploadFinished = true
            this.importRunning = false
            this.importFinished = true
            this.importStatus = Number(payload.status)
            this.importMessage = payload.message || 'Die Verarbeitung der Lehrerliste wurde abgeschlossen.'

            if (this.importStatus === 200) {
                await this.teachersListStore.index()
            }
        },

        finishImport() {
            this.resetImportState()
            this.importDialog = false
        },

        resetImportState() {
            this.stopImportStatusPolling()
            this.importUploadFinished = false
            this.importRunning = false
            this.importFinished = false
            this.importStatus = null
            this.importMessage = ''
        },

        hasRole(teacher, role) {
            return Array.isArray(teacher.roles) && teacher.roles.includes(role)
        },

        isSelectedTimetableRole(teacher, role) {
            return this.hasRole(teacher, role)
        },

        isTeacherActionPending(teacher, action) {
            let actionKey = `tt-role:${teacher.id}:${action}`

            if (action === 'status') {
                actionKey = `status:${teacher.id}`
            }

            if (action === 'edit') {
                actionKey = `edit:${teacher.id}`
            }

            return this.pending_action === actionKey
        },

        isBulkActionPending(isActive) {
            return this.pending_action === `bulk:${isActive ? 'active' : 'inactive'}`
        },

        async setAllTeachersActive(isActive) {
            await this.teachersListStore.setAllActive(isActive)
        },

        async copyEmail(teacher) {
            const emailAddress = (teacher?.email || '').toString().trim()
            if (!emailAddress) { return false }

            const copied = await this.copyTextToClipboard(emailAddress)
            if (!copied) { return false }

            this.copiedEmailId = teacher.id

            if (this.copyEmailResetTimeout) {
                clearTimeout(this.copyEmailResetTimeout)
            }

            this.copyEmailResetTimeout = setTimeout(() => {
                this.copiedEmailId = null
                this.copyEmailResetTimeout = null
            }, 1500)

            return true
        },

        async copyTextToClipboard(text) {
            if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(text)

                return true
            }

            if (typeof document === 'undefined') { return false }

            const textArea = document.createElement('textarea')
            textArea.value = text
            textArea.setAttribute('readonly', '')
            textArea.style.position = 'fixed'
            textArea.style.opacity = '0'
            document.body.appendChild(textArea)
            textArea.select()
            const copied = document.execCommand('copy')
            document.body.removeChild(textArea)

            return copied
        },

        async toggleTeacherActive(teacher) {
            if (teacher.user_id !== null) {
                await this.teachersListStore.toggleActive(teacher.user_id, teacher.id)

                return
            }

            await this.teachersListStore.activateTeacher(teacher.teacher_id, teacher.id)
        },

        async assignTimetableRole(teacher, role) {
            const nextRole = this.isSelectedTimetableRole(teacher, role) ? null : role
            let userId = teacher.user_id

            if (userId === null) {
                const activatedTeacher = await this.teachersListStore.activateTeacher(teacher.teacher_id, teacher.id)

                if (!activatedTeacher?.user_id) { return }

                userId = activatedTeacher.user_id
            }

            await this.teachersListStore.setRole(userId, nextRole, teacher.id, role)
        },

        openCreateDialog() {
            this.editingTeacher = null
            this.editForm = {
                short: '',
                last_name: '',
                first_name: '',
                email: '',
            }
            this.action_errors = {}
            this.editDialog = true
        },

        openEditDialog(teacher) {
            this.editingTeacher = teacher
            this.editForm = {
                short: (teacher.short ?? '').toString().toUpperCase(),
                last_name: teacher.last_name ?? '',
                first_name: teacher.first_name ?? '',
                email: teacher.email ?? '',
            }
            this.action_errors = {}
            this.editDialog = true
        },

        updateTeacherShort(value) {
            this.editForm.short = (value ?? '').toString().toUpperCase()
        },

        closeEditDialog() {
            this.editDialog = false
            this.editingTeacher = null
            this.action_errors = {}
        },

        async saveTeacher() {
            if (!this.canSaveTeacher) { return }

            const values = {
                short: this.editForm.short.trim().toUpperCase(),
                last_name: this.editForm.last_name,
                first_name: this.editForm.first_name,
                email: this.editForm.email,
            }
            const savedTeacher = this.editingTeacher
                ? await this.teachersListStore.updateTeacher(this.editingTeacher, values)
                : await this.teachersListStore.createTeacher(values)

            if (savedTeacher) {
                this.closeEditDialog()
            }
        },
    },
}
</script>

<style scoped src="@/../css/admin-index-page.css"></style>
<style scoped src="@/../css/admin-crud-panel.css"></style>
<style scoped>
.teachers-read-only-grid {
    grid-template-columns: minmax(0, 1fr);
}

.teacher-row {
    align-items: flex-start;
    gap: 12px;
    justify-content: space-between;
}

.teacher-bulk-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

.teacher-row-actions {
    display: flex;
    flex: 0 0 auto;
    flex-direction: column;
    gap: 8px;
    align-items: flex-end;
}

.teacher-action-line {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

.teacher-name-edit,
.person-email-copy {
    flex: 0 0 auto;
    opacity: 0.72;
}

.teacher-name-edit:hover,
.person-email-copy:hover {
    opacity: 1;
}

.person-email-row {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    min-width: 0;
}

.person-email {
    min-width: 0;
    overflow-wrap: anywhere;
}

.person-email-copied-chip {
    flex: 0 0 auto;
}

@media (max-width: 720px) {
    .teacher-row {
        flex-direction: column;
    }

    .teacher-row-actions {
        align-items: flex-start;
        width: 100%;
    }

    .teacher-action-line {
        justify-content: flex-start;
    }
}
</style>
