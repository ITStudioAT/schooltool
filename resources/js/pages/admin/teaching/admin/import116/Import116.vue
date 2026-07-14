<template>
    <v-col cols="12" class="teaching-admin-import-col">
        <ItsGridBox variant="overview" color="primary" title="Import Sokrates 116" subtitle="Schüler- und Elterndaten synchronisieren" icon="mdi-import" class="w-100">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-alert type="info" class="import116-info-alert">
                        <div class="mb-4">Hier können die Schüler- und Elterndaten aus Sokrates-Bund übernommen werden.</div>
                        <div class="text-decoration-underline">Folgende Datei ist zu importieren:</div>
                        <div>Sokrates Bund ➜ Auswertungen ➜ Dynamische Suche ➜ Name der Abfrage: 116 ➜</div>
                        <div>Alle auswählen > Ausführen ➜ Exportieren (XLSX)</div>
                    </v-alert>

                    <div class="text-caption">Es muss sich um eine Excel-Datei (*.xlsx) handeln.</div>
                    <div v-if="lastImportDisplay" class="text-caption">
                        Letzter Import: {{ lastImportDisplay }}
                    </div>

                    <div v-if="!is_upload_finished">
                        <FileUpload
                            :path="uploadPath"
                            fileLabel
                            :allowedFileTypes="['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']"
                            :refreshFilePond="refresh_file_pond"
                            class="mt-2"
                            @fileUploadFinished="fileUploadFinished"
                            @uploadStart="onUploadStart"
                            @error="uploadError" />
                    </div>

                    <v-alert v-if="is_importing" type="info" class="mt-2 import116-progress-alert">
                        <div class="d-flex flex-row align-center ga-2">
                            <v-progress-circular indeterminate size="26" width="3" color="primary" />
                            <div>Datei hochgeladen. Die Verarbeitung läuft – Sie erhalten eine Meldung, sobald der Import abgeschlossen ist.</div>
                        </div>
                    </v-alert>
                    <v-alert v-if="is_upload_error" type="error" class="mt-2">Upload fehlgeschlagen.</v-alert>
                    <v-btn v-if="is_upload_finished || is_upload_error" color="warning" flat tile class="mt-2" :disabled="is_importing" @click="resetUpload">Neu hochladen</v-btn>

                    <v-divider class="my-4" />

                    <div class="d-flex flex-row align-center justify-space-between ga-2">
                        <div class="text-subtitle-2">Importe</div>
                        <v-btn size="small" variant="flat" color="secondary" :loading="is_loading_runs" @click="loadRuns">Aktualisieren</v-btn>
                    </div>

                    <v-alert v-if="run_action_message" type="success" class="mt-2" density="compact">{{ run_action_message }}</v-alert>
                    <v-alert v-if="run_action_error" type="error" class="mt-2" density="compact">{{ run_action_error }}</v-alert>
                    <v-alert v-if="run_tracking_error" type="warning" class="mt-2" density="compact">{{ run_tracking_error }}</v-alert>

                    <div v-if="!run_tracking_error" class="mt-2">
                        <div class="d-flex flex-row align-end ga-2">
                            <v-btn
                                color="warning"
                                variant="flat"
                                :disabled="!canRestoreSelection"
                                :loading="is_resetting_runs"
                                @click="resetRecentRuns">
                                Import zurücksetzen
                            </v-btn>
                        </div>
                        <div class="text-caption mt-1">
                            Maximal {{ runs_meta.reset_max_runs || 0 }} Importdateien gespeichert. Verfügbar: {{ runs_meta.available_reset_runs || 0 }}
                        </div>
                        <div class="text-caption" v-if="selected_restore_target_id">
                            Ziel: Import #{{ selected_restore_target_id }}
                        </div>
                        <div class="text-caption" v-else>
                            Klicken Sie auf einen Import in der Liste, um ihn als Ziel für das Zurücksetzen auszuwählen.
                        </div>
                    </div>

                    <div v-if="!is_loading_runs && !run_tracking_error && runs.length === 0" class="text-caption mt-3">
                        Noch keine Importe protokolliert.
                    </div>

                    <div class="mt-3 d-flex flex-column ga-2 import116-runs-list" v-if="runs.length > 0">
                        <v-card
                            v-for="run in runs"
                            :key="run.id"
                            variant="outlined"
                            class="import116-run-card"
                            :class="{ 'import116-selected-card': isSelectedRestoreTarget(run) }"
                            @click="selectRestoreTarget(run)">
                            <v-card-text class="pa-3">
                                <div class="d-flex flex-row flex-wrap align-center justify-space-between ga-2">
                                    <div>
                                        <div class="text-subtitle-2 d-flex align-center ga-2">
                                            <span>Import #{{ run.id }}</span>
                                            <v-chip v-if="isSelectedRestoreTarget(run)" size="x-small" color="warning" variant="flat">Ziel</v-chip>
                                            <v-chip
                                                v-if="canSelectAsRestoreTarget(run)"
                                                size="x-small"
                                                color="success"
                                                variant="flat">
                                                zurücksetzbar
                                            </v-chip>
                                            <v-chip
                                                v-else-if="isActiveImport(run)"
                                                size="x-small"
                                                color="warning"
                                                variant="flat">
                                                nicht zurücksetzbar
                                            </v-chip>
                                        </div>
                                        <div class="text-caption">
                                            {{ formatDateTime(run.finished_at || run.started_at) }}
                                            <span v-if="run.undone_at"> | zurückgesetzt</span>
                                            <span v-else-if="run.status"> | {{ run.status }}</span>
                                        </div>
                                        <div class="text-caption" v-if="run.source_name">
                                            Datei: {{ run.source_name }}
                                        </div>
                                    </div>
                                    <div class="d-flex flex-row flex-wrap ga-1">
                                        <v-chip size="x-small" color="success" variant="tonal">+ {{ run.counts?.inserted || 0 }}</v-chip>
                                        <v-chip size="x-small" color="info" variant="tonal">~ {{ run.counts?.updated || 0 }}</v-chip>
                                        <v-chip size="x-small" color="error" variant="tonal">- {{ run.counts?.deleted || 0 }}</v-chip>
                                    </div>
                                </div>

                                <div class="text-caption mt-2">
                                    Zeilen: {{ run.counts?.processed_rows || 0 }}, Änderungen gesamt: {{ run.counts?.changes_total || 0 }}
                                </div>

                                <div class="d-flex flex-row ga-2 mt-2">
                                    <v-btn size="small" variant="text" @click.stop="toggleRunDetails(run.id)">
                                        {{ expanded_run_ids[run.id] ? 'Details ausblenden' : 'Details anzeigen' }}
                                    </v-btn>
                                    <v-btn
                                        v-if="canDeleteImport(run)"
                                        size="small"
                                        color="error"
                                        variant="text"
                                        :loading="deleting_import_id === run.id"
                                        @click.stop="deleteImport(run)">
                                        Import löschen
                                    </v-btn>
                                </div>

                                <v-progress-linear v-if="loading_run_id === run.id" indeterminate class="mt-2" />

                                <div v-if="expanded_run_ids[run.id]" class="mt-2">
                                    <div v-if="!run_details[run.id]" class="text-caption">Details werden geladen ...</div>
                                    <div v-else class="d-flex flex-column ga-3">
                                        <div v-for="type in changeTypes" :key="`${run.id}-${type}`">
                                            <div class="d-flex align-center justify-space-between ga-2">
                                                <div class="text-body-2 font-weight-medium">
                                                    {{ changeTypeLabel(type) }} ({{ run_details[run.id]?.changes?.[type]?.length || 0 }})
                                                </div>
                                                <v-btn
                                                    size="x-small"
                                                    variant="text"
                                                    @click.stop="toggleChangeGroup(run.id, type)">
                                                    {{ isChangeGroupOpen(run.id, type) ? 'Schließen' : 'Öffnen' }}
                                                </v-btn>
                                            </div>
                                            <template v-if="isChangeGroupOpen(run.id, type)">
                                                <v-list density="compact" class="py-0" v-if="(run_details[run.id]?.changes?.[type] || []).length > 0">
                                                    <v-list-item v-for="item in run_details[run.id].changes[type]" :key="`${run.id}-${type}-${item.id}`" class="px-0">
                                                        <v-list-item-title>
                                                            {{ item.name || '-' }}
                                                            <span class="text-caption">({{ item.student_code }}<span v-if="item.class">, {{ item.class }}</span>)</span>
                                                        </v-list-item-title>
                                                    </v-list-item>
                                                </v-list>
                                                <div v-else class="text-caption">Keine</div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </v-card-text>
                        </v-card>
                    </div>
                </v-card>
            </div>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import FileUpload from '@/pages/components/FileUpload.vue'

const importStatusPollDelay = 1500

export default {
    components: { ItsGridBox, FileUpload },

    props: {
        uploadPath: { type: String, default: '/api/admin/teaching_upload/116' },
        apiBasePath: { type: String, default: '/api/admin/teaching/import116' },
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
        await this.loadRuns()
    },

    mounted() {
        window.addEventListener('import116-finished', this.handleImportFinished)
    },

    unmounted() {
        this.stopImportStatusPolling()
        window.removeEventListener('import116-finished', this.handleImportFinished)
    },

    data() {
        return {
            adminStore: null,
            is_upload_finished: false,
            is_upload_error: false,
            refresh_file_pond: false,
            is_importing: false,
            import_poll_timeout_id: null,
            import_poll_generation: 0,
            import_run_baseline_id: 0,
            active_import_run_id: null,
            last_import_116_at: null,
            runs: [],
            runs_meta: { reset_max_runs: 0, available_reset_runs: 0, history_limit: 0 },
            run_details: {},
            expanded_run_ids: {},
            expanded_change_groups: {},
            is_loading_runs: false,
            loading_run_id: null,
            is_resetting_runs: false,
            deleting_import_id: null,
            selected_restore_target_id: null,
            run_tracking_error: '',
            run_action_message: '',
            run_action_error: '',
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        changeTypes() {
            return ['inserted', 'updated', 'deleted']
        },
        restorableImports() {
            const active = (this.runs || []).filter((item) => this.isActiveImport(item))
            const max = Number(this.runs_meta?.reset_max_runs || 0)
            return max > 0 ? active.slice(0, max) : []
        },
        selected_restore_depth() {
            const targetId = Number(this.selected_restore_target_id || 0)
            if (!targetId) return 0
            const idx = this.restorableImports.findIndex((item) => Number(item?.id) === targetId)
            return idx >= 0 ? idx + 1 : 0
        },
        lastImportDisplay() {
            const value = this.last_import_116_at || this.config?.teaching?.last_import_116_at
            if (!value) return null
            const date = new Date(value)
            if (Number.isNaN(date.getTime())) return value
            return new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
        },
        canRestoreSelection() {
            if (this.run_tracking_error) return false
            if (!this.selected_restore_target_id) return false
            if (this.selected_restore_depth <= 0) return false
            return this.selected_restore_depth <= Number(this.runs_meta?.reset_max_runs || 0)
        },
    },

    methods: {
        isActiveImport(run) {
            return !!run && run.status === 'completed' && !run.undone_at
        },
        canSelectAsRestoreTarget(run) {
            if (!this.isActiveImport(run)) return false
            return this.restorableImports.some((item) => Number(item?.id) === Number(run?.id))
        },
        canDeleteImport(run) {
            return !!run && !!run.id
        },
        async handleImportFinished(event) {
            const detail = event?.detail || {}
            const payload = detail?.data || {}
            const runId = Number(payload?.run_id || 0)

            if (runId && runId <= Number(this.import_run_baseline_id || 0)) return
            if (runId && this.active_import_run_id && runId !== Number(this.active_import_run_id)) return

            this.reconcileImportRun({
                id: runId || this.active_import_run_id,
                status: Number(detail?.status) === 200 ? 'completed' : 'failed',
                finished_at: new Date().toISOString(),
                counts: payload?.counts || payload,
                error_message: detail?.message || 'Import 116 fehlgeschlagen.',
            })
            await this.loadRuns()
        },
        startImportStatusPolling() {
            this.stopImportStatusPolling()
            const generation = this.import_poll_generation
            this.scheduleImportStatusPoll(generation)
        },
        stopImportStatusPolling() {
            if (this.import_poll_timeout_id !== null) {
                window.clearTimeout(this.import_poll_timeout_id)
                this.import_poll_timeout_id = null
            }
            this.import_poll_generation += 1
        },
        scheduleImportStatusPoll(generation) {
            if (!this.is_importing || generation !== this.import_poll_generation) return
            this.import_poll_timeout_id = window.setTimeout(() => {
                this.import_poll_timeout_id = null
                this.pollImportStatus(generation)
            }, importStatusPollDelay)
        },
        async pollImportStatus(generation) {
            if (!this.is_importing || generation !== this.import_poll_generation) return

            await this.loadRuns()
            if (!this.is_importing || generation !== this.import_poll_generation) return

            const currentUserId = Number(this.config?.user?.id || 0)
            let run = this.active_import_run_id
                ? this.runs.find((item) => Number(item?.id) === Number(this.active_import_run_id))
                : null

            if (!run) {
                run = (this.runs || [])
                    .filter((item) => {
                        const belongsToCurrentUpload = Number(item?.id || 0) > Number(this.import_run_baseline_id || 0)
                        const belongsToCurrentUser = !item?.user_id || !currentUserId || Number(item.user_id) === currentUserId
                        return belongsToCurrentUpload && belongsToCurrentUser
                    })
                    .sort((left, right) => Number(left.id) - Number(right.id))[0]
            }

            if (run) {
                this.active_import_run_id = Number(run.id)
                if (run.status === 'completed' || run.status === 'failed') {
                    this.reconcileImportRun(run)
                    return
                }
            }

            this.scheduleImportStatusPoll(generation)
        },
        reconcileImportRun(run) {
            this.stopImportStatusPolling()
            this.is_importing = false

            if (run?.status === 'completed') {
                const counts = run?.counts || {}
                this.last_import_116_at = run?.finished_at || new Date().toISOString()
                this.run_action_error = ''
                this.run_action_message = `Import abgeschlossen: +${counts.inserted ?? counts.created ?? 0} / ~${counts.updated ?? 0} / -${counts.deleted ?? 0}`
                return
            }

            this.run_action_message = ''
            this.run_action_error = run?.error_message || 'Import 116 fehlgeschlagen.'
        },
        async loadRuns() {
            this.is_loading_runs = true
            this.run_tracking_error = ''
            this.run_action_error = ''
            try {
                const response = await axios.get(`${this.apiBasePath}/runs`)
                this.runs = response?.data?.data || []
                this.runs_meta = response?.data?.meta || { reset_max_runs: 0, available_reset_runs: 0, history_limit: 0 }
                if (!this.restorableImports.some((item) => Number(item?.id) === Number(this.selected_restore_target_id || 0))) {
                    this.selected_restore_target_id = null
                }
            } catch (error) {
                this.runs = []
                this.runs_meta = { reset_max_runs: 0, available_reset_runs: 0, history_limit: 0 }
                this.run_tracking_error = error?.response?.data?.message || 'Import-Protokolle konnten nicht geladen werden.'
            } finally {
                this.is_loading_runs = false
            }
        },
        selectRestoreTarget(run) {
            if (!this.canSelectAsRestoreTarget(run)) return
            const id = Number(run.id || 0)
            if (!id) return
            this.selected_restore_target_id = this.selected_restore_target_id === id ? null : id
            this.run_action_error = ''
            this.run_action_message = ''
        },
        isSelectedRestoreTarget(run) {
            return Number(this.selected_restore_target_id || 0) > 0 && Number(run?.id || 0) === Number(this.selected_restore_target_id)
        },
        async toggleRunDetails(runId) {
            const isOpen = !!this.expanded_run_ids[runId]
            if (isOpen) {
                this.expanded_run_ids = { ...this.expanded_run_ids, [runId]: false }
                return
            }

            this.expanded_run_ids = { ...this.expanded_run_ids, [runId]: true }
            if (this.run_details[runId]) return

            this.loading_run_id = runId
            this.run_action_error = ''
            try {
                const response = await axios.get(`${this.apiBasePath}/runs/${runId}`)
                this.run_details = {
                    ...this.run_details,
                    [runId]: response?.data || null,
                }
            } catch (error) {
                this.run_action_error = error?.response?.data?.message || 'Import-Details konnten nicht geladen werden.'
                this.expanded_run_ids = { ...this.expanded_run_ids, [runId]: false }
            } finally {
                this.loading_run_id = null
            }
        },
        changeGroupKey(runId, type) {
            return `${runId}:${type}`
        },
        isChangeGroupOpen(runId, type) {
            return !!this.expanded_change_groups[this.changeGroupKey(runId, type)]
        },
        toggleChangeGroup(runId, type) {
            const key = this.changeGroupKey(runId, type)
            this.expanded_change_groups = {
                ...this.expanded_change_groups,
                [key]: !this.expanded_change_groups[key],
            }
        },
        async resetRecentRuns() {
            if (!this.canRestoreSelection) return
            this.is_resetting_runs = true
            this.run_action_message = ''
            this.run_action_error = ''
            try {
                const response = await axios.post(`${this.apiBasePath}/runs/reset`, { target_import_id: this.selected_restore_target_id })
                this.run_action_message = response?.data?.message || 'Importe wurden zurückgesetzt.'
                this.run_details = {}
                this.expanded_run_ids = {}
                this.expanded_change_groups = {}
                this.selected_restore_target_id = null
                await this.loadRuns()
            } catch (error) {
                this.run_action_error = error?.response?.data?.message || 'Import konnte nicht zurückgesetzt werden.'
            } finally {
                this.is_resetting_runs = false
            }
        },
        async deleteImport(run) {
            if (!this.canDeleteImport(run)) return
            const id = Number(run?.id || 0)
            if (!id) return

            this.deleting_import_id = id
            this.run_action_message = ''
            this.run_action_error = ''
            try {
                const response = await axios.delete(`${this.apiBasePath}/runs/${id}`)
                this.run_action_message = response?.data?.message || `Import #${id} wurde gelöscht.`
                if (Number(this.selected_restore_target_id || 0) === id) {
                    this.selected_restore_target_id = null
                }
                if (this.run_details[id]) {
                    const copy = { ...this.run_details }
                    delete copy[id]
                    this.run_details = copy
                }
                this.expanded_change_groups = Object.fromEntries(
                    Object.entries(this.expanded_change_groups).filter(([key]) => !key.startsWith(`${id}:`))
                )
                await this.loadRuns()
            } catch (error) {
                this.run_action_error = error?.response?.data?.message || 'Import konnte nicht gelöscht werden.'
            } finally {
                this.deleting_import_id = null
            }
        },
        formatDateTime(value) {
            if (!value) return '-'
            const date = new Date(value)
            if (Number.isNaN(date.getTime())) return String(value)
            return new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
        },
        changeTypeLabel(type) {
            if (type === 'inserted') return 'Eingefügt'
            if (type === 'updated') return 'Aktualisiert'
            if (type === 'deleted') return 'Gelöscht'
            return type
        },
        onUploadStart() {
            this.stopImportStatusPolling()
            this.is_upload_finished = false
            this.is_upload_error = false
            this.run_action_message = ''
            this.run_action_error = ''
            this.import_run_baseline_id = Math.max(0, ...(this.runs || []).map((run) => Number(run?.id || 0)))
            this.active_import_run_id = null
            if (this.config?.is_auth) this.adminStore.initializeEcho()
            this.is_importing = true
        },
        fileUploadFinished() {
            this.is_upload_finished = true
            this.startImportStatusPolling()
        },
        uploadError() {
            this.stopImportStatusPolling()
            this.is_upload_error = true
            this.refresh_file_pond = !this.refresh_file_pond
            this.is_importing = false
        },
        resetUpload() {
            this.stopImportStatusPolling()
            this.is_upload_finished = false
            this.is_upload_error = false
            this.refresh_file_pond = !this.refresh_file_pond
            this.is_importing = false
        },
    },
}
</script>
<style scoped>
.import116-info-alert :deep(.v-alert__content) {
    line-height: 1.4;
}

.teaching-admin-import-col {
    max-width: 980px;
}

.import116-progress-alert {
    border: 1px solid rgba(37, 99, 235, 0.2);
}

.import116-runs-list {
    max-height: 62vh;
    overflow: auto;
    padding-right: 2px;
}

.import116-run-card {
    border-color: rgba(15, 23, 42, 0.12) !important;
    background: rgba(255, 255, 255, 0.84);
    transition: border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
}

.import116-run-card:hover {
    border-color: rgba(59, 130, 246, 0.3) !important;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.09);
    transform: translateY(-1px);
}

.import116-selected-card {
    background: #fff3e0;
    border-color: #ef6c00 !important;
}

@media (max-width: 1903px) {
    .teaching-admin-import-col {
        max-width: none;
    }
}
</style>
