<template>
    <v-col cols="12" xl="11" v-if="teachers">
        <section class="crud-shell admin-card ai-glass-panel" :class="{ 'is-disabled': action != '' }">
            <div class="admin-card-head crud-head mb-4">
                <div>
                    <div class="admin-card-eyebrow">Verwaltung</div>
                    <h2 class="admin-card-title crud-title">Lehrerliste</h2>
                </div>

                <div class="admin-kpi-grid crud-kpis">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Gesamt</div>
                        <div class="kpi-value">{{ totalTeachersCount }}</div>
                    </div>
                </div>
            </div>

            <div class="crud-content-grid">
                <section class="admin-card ai-glass-panel crud-main-card pa-3">
                    <!-- Upload mode -->
                    <template v-if="is_upload">
                        <div class="empty-state crud-form-section mb-3">
                            <div class="admin-card-eyebrow">Import</div>
                            <h3 class="admin-card-title" style="margin-top: 4px">Lehrer-Liste importieren</h3>
                            <div class="kpi-sub mt-2">
                                Es muss sich um eine Excel- oder CSV-Datei (*.xlsx, *.xls, *.csv) handeln. Benötigte Spalten:
                                <strong>Kurz/Kürzel, Nachname/Familienname, Vorname, Email/EMail</strong>
                            </div>
                        </div>

                        <template v-if="!is_upload_finished">
                            <FileUpload
                                :path="teachersListUploadPath"
                                fileLabel
                                @uploadStart="importStarted"
                                @fileUploadFinished="fileUploadFinished"
                                @error="importUploadFailed"
                                class="mt-2" />
                            <v-alert
                                v-if="is_import_running"
                                type="info"
                                variant="tonal"
                                rounded="lg"
                                title="Import läuft"
                                class="mt-4">
                                Die Lehrerliste wird hochgeladen und anschließend importiert. Bitte warten Sie.
                                <v-progress-linear color="primary" indeterminate rounded height="6" class="mt-3" />
                            </v-alert>
                            <div class="mt-4">
                                <v-btn
                                    color="warning"
                                    variant="tonal"
                                    rounded="lg"
                                    prepend-icon="mdi-close"
                                    :disabled="is_import_running"
                                    @click="is_upload = false">
                                    Abbruch
                                </v-btn>
                            </div>
                        </template>

                        <template v-else>
                            <v-alert
                                v-if="is_import_running"
                                type="info"
                                variant="tonal"
                                rounded="lg"
                                title="Import läuft">
                                Die Lehrerliste wird importiert. Bitte warten Sie, bis die Verarbeitung abgeschlossen ist.
                                <v-progress-linear color="primary" indeterminate rounded height="6" class="mt-3" />
                            </v-alert>

                            <v-alert
                                v-else-if="is_import_finished"
                                :type="import_status === 200 ? 'success' : 'error'"
                                variant="tonal"
                                rounded="lg"
                                :title="import_status === 200 ? 'Import abgeschlossen' : 'Import fehlgeschlagen'">
                                {{ import_message }}
                            </v-alert>

                            <div class="mt-4" v-if="is_import_finished">
                                <v-btn color="success" variant="flat" rounded="lg" prepend-icon="mdi-check" @click="uploadFinished">
                                    Fertig
                                </v-btn>
                            </div>
                        </template>
                    </template>

                    <!-- List mode -->
                    <template v-else>
                        <div class="empty-state crud-form-section mb-3">
                            <div class="kpi-sub">
                                Diese Liste legt fest, welche Lehrer:innen berechtigt sind, sich am System zu registrieren.
                                Das entspricht nicht unbedingt den tatsächlich registrierten Benutzeraccounts.
                            </div>
                        </div>

                        <div class="d-grid ga-3 mb-3">
                            <div class="empty-state crud-search-panel">
                                <SearchField :store="teachersListStore" selected_field="selected_teachers" />
                            </div>

                            <div class="d-flex flex-wrap ga-2">
                                <v-btn color="primary" variant="tonal" rounded="lg" class="text-caption" @click="selectAll">
                                    Alle auswählen [{{ Math.max(0, teachers.length - selected_teachers.length) }}]
                                </v-btn>
                                <v-btn color="primary" variant="text" rounded="lg" class="text-caption" @click="unselectAll">
                                    Alle abwählen [{{ selected_teachers.length }}]
                                </v-btn>
                            </div>
                        </div>

                        <div class="empty-state pa-2" v-if="teachers.length === 0">
                            <v-alert type="info" variant="tonal" rounded="lg" text="Die Liste ist leer. Sie können jederzeit eine Liste importieren." />
                        </div>
                        <div class="empty-state pa-2" v-else>
                            <v-list
                                dense
                                variant="flat"
                                class="crud-list"
                                select-strategy="leaf"
                                v-model:selected="selected_teachers"
                                color="success-lighten-2">
                                <v-list-item
                                    v-for="item in teachers"
                                    :key="item.id"
                                    :value="item.id"
                                    class="crud-list-item"
                                    :class="{ 'is-selected': isSelectedTeacher(item.id) }">
                                    <template #title>
                                        <div class="person-row crud-item-row">
                                            <div class="d-flex align-start" style="min-width: 0">
                                                <div class="person-body" style="min-width: 0">
                                                    <div class="person-name">
                                                        {{ item.last_name }} {{ item.first_name }}<span v-if="item.short"> ({{ item.short }})</span>
                                                    </div>
                                                    <div class="person-roles">{{ item.email || '-' }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </div>
                    <div class="empty-state crud-pagination mt-3 pa-3">
                        <Pagination :meta="meta" :store="teachersListStore" selected_field="selected_teachers" />
                    </div>
                    </template>
                </section>

                <aside class="crud-side-stack">
                    <section class="admin-card ai-glass-panel">
                        <div class="admin-card-head mb-2">
                            <div>
                                <div class="admin-card-eyebrow">Aktionen</div>
                                <h3 class="admin-card-title">Liste verwalten</h3>
                            </div>
                        </div>
                        <div class="kpi-sub" style="margin-top: -2px">Verfügbare Schritte für die aktuelle Auswahl.</div>

                        <template v-if="!is_upload">
                            <div class="crud-actions-primary">
                                <v-btn block color="primary" variant="flat" rounded="lg" prepend-icon="mdi-refresh" @click="refresh">
                                    Aktualisieren
                                </v-btn>
                                <v-btn block color="primary" variant="tonal" rounded="lg" class="crud-action-btn-offset" prepend-icon="mdi-import" @click="is_upload = true">
                                    Importieren
                                </v-btn>
                                <v-btn block color="primary" variant="tonal" rounded="lg" class="crud-action-btn-offset" prepend-icon="mdi-plus" @click="createTeacher">
                                    Hinzufügen
                                </v-btn>
                            </div>

                            <template v-if="selected_teachers.length >= 1">
                                <v-divider class="crud-actions-divider" />
                                <div class="crud-actions-secondary">
                                    <v-btn
                                        v-if="selected_teachers.length == 1"
                                        block
                                        color="primary"
                                        variant="tonal"
                                        rounded="lg"
                                        prepend-icon="mdi-pencil"
                                        @click="editTeacher(selected_teachers[0])">
                                        Ändern
                                    </v-btn>

                                    <v-btn
                                        block
                                        color="warning"
                                        variant="tonal"
                                        rounded="lg"
                                        class="crud-action-btn-offset"
                                        prepend-icon="mdi-delete"
                                        @click="deleteTeacher">
                                        Löschen
                                    </v-btn>
                                </div>
                            </template>
                        </template>

                        <template v-if="!hideBackButton">
                            <v-divider class="crud-actions-divider" />
                            <div class="crud-actions-secondary">
                                <v-btn block color="secondary" variant="tonal" rounded="lg" prepend-icon="mdi-arrow-left" @click="abortReturn">
                                    Zur Übersicht
                                </v-btn>
                            </div>
                        </template>
                    </section>
                </aside>
            </div>
        </section>
    </v-col>

    <v-dialog v-model="teacherDialogOpen" persistent :max-width="teacherDialogMaxWidth" scrollable>
        <v-card class="crud-dialog-card ai-glass-panel">
            <div class="crud-dialog-head">
                <div>
                    <div class="admin-card-eyebrow" :class="{ 'crud-delete-eyebrow': action == 'delete_teacher' }">
                        {{ action == 'delete_teacher' ? 'Achtung' : 'Lehrer:in' }}
                    </div>
                    <div class="admin-card-title" style="margin-top: 4px">{{ teacherDialogTitle }}</div>
                </div>

                <v-btn icon="mdi-close" variant="text" rounded="lg" @click="abort" />
            </div>

            <v-card-text class="crud-dialog-body">
                <template v-if="action == 'create_teacher' || action == 'edit_teacher'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="saveTeacher(data)" class="mb-2 crud-form">
                        <div class="empty-state crud-form-section">
                            <v-row dense>
                                <v-col cols="12">
                                    <v-text-field autofocus v-model="data.short" label="Kurzname" :rules="[required(), maxLength(10)]" @input="data.short = data.short?.toUpperCase()" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="data.last_name" label="Nachname" :rules="[required(), maxLength(255)]" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="data.first_name" label="Vorname" :rules="[maxLength(255)]" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="data.email" label="E-Mail" :rules="[required(), mail(), maxLength(255)]" />
                                </v-col>
                            </v-row>
                        </div>

                        <div class="crud-form-actions d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" variant="text" rounded="lg" @click="abort">Abbruch</v-btn>
                            <v-btn color="success" variant="flat" rounded="lg" type="submit">Speichern</v-btn>
                        </div>
                    </v-form>
                </template>

                <template v-else-if="action == 'delete_teacher'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteTeachers(selected_teachers)" class="crud-form">
                        <div class="empty-state crud-delete-alert">
                            <div class="admin-card-eyebrow crud-delete-eyebrow">Achtung</div>
                            <div class="admin-card-title crud-delete-title">Lehrer:in löschen</div>
                            <div class="kpi-sub mt-2" v-if="selected_teachers.length == 1">
                                Es soll ein:e Lehrer:in gelöscht werden. Sind Sie sicher?
                            </div>
                            <div class="kpi-sub mt-2" v-if="selected_teachers.length > 1">
                                Es sollen {{ selected_teachers.length }} Lehrer:innen gelöscht werden. Sind Sie sicher?
                            </div>
                        </div>

                        <div class="crud-form-actions d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="success" variant="text" rounded="lg" @click="abort">Abbruch</v-btn>
                            <v-btn color="error" variant="flat" rounded="lg" type="submit" prepend-icon="mdi-delete">Löschen</v-btn>
                        </div>
                    </v-form>
                </template>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<script>
import { teachersListApi } from '@/domains/teachersList/api'
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import FileUpload from '@/pages/components/FileUpload.vue'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'
import { useTeachersListStore } from '@/stores/admin/TeachersListStore'

export default {
    props: {
        hideBackButton: {
            type: Boolean,
            default: false,
        },
    },

    setup() {
        return useValidationRulesSetup()
    },

    components: { FileUpload, SearchField, Pagination },

    async beforeMount() {
        this.teachersListStore = useTeachersListStore()
        await this.teachersListStore.index()
    },

    mounted() {
        window.addEventListener('teachers-list-import-finished', this.handleImportFinished)
    },

    beforeUnmount() {
        window.removeEventListener('teachers-list-import-finished', this.handleImportFinished)
    },

    data() {
        return {
            teachersListStore: null,
            is_valid: false,
            is_upload: false,
            is_upload_finished: false,
            is_import_running: false,
            is_import_finished: false,
            import_status: null,
            import_message: '',
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'main_action']),
        ...mapWritableState(useTeachersListStore, ['teachers', 'meta', 'selected_teachers', 'search_string', 'data', 'answer']),
        teachersListUploadPath() {
            return teachersListApi.upload()
        },
        teacherDialogOpen: {
            get() {
                return ['create_teacher', 'edit_teacher', 'delete_teacher'].includes(this.action)
            },
            set(value) {
                if (!value) { this.action = '' }
            },
        },
        teacherDialogMaxWidth() {
            return this.action == 'delete_teacher' ? 640 : 640
        },
        teacherDialogTitle() {
            if (this.action == 'create_teacher') { return 'Neue:r Lehrer:in' }
            if (this.action == 'edit_teacher') { return 'Lehrer:in ändern' }
            if (this.action == 'delete_teacher') { return 'Löschen bestätigen' }
            return 'Lehrer:in'
        },
        totalTeachersCount() {
            const total = Number(this.meta?.total)
            return Number.isFinite(total) && total >= 0 ? total : this.teachers.length
        },
    },

    methods: {
        async refresh() {
            await this.teachersListStore.index()
        },

        importStarted() {
            this.is_import_running = true
            this.is_import_finished = false
            this.import_status = null
            this.import_message = ''
        },

        fileUploadFinished() {
            this.is_upload_finished = true
        },

        importUploadFailed() {
            this.is_import_running = false
            this.is_import_finished = false
        },

        async handleImportFinished(event) {
            if (!this.is_import_running) { return }

            const payload = event.detail || {}
            this.is_import_running = false
            this.is_import_finished = true
            this.import_status = Number(payload.status)
            this.import_message = payload.message || 'Die Verarbeitung der Lehrerliste wurde abgeschlossen.'

            if (this.import_status === 200) {
                await this.teachersListStore.index()
            }
        },

        uploadFinished() {
            this.is_upload_finished = false
            this.is_import_running = false
            this.is_import_finished = false
            this.import_status = null
            this.import_message = ''
            this.is_upload = false
        },

        abortReturn() {
            this.main_action = 'teachers'
        },

        selectAll() {
            this.selected_teachers = this.teachers.map((item) => item.id)
        },

        unselectAll() {
            this.selected_teachers = []
        },

        isSelectedTeacher(id) {
            return this.selected_teachers.includes(id)
        },

        async saveTeacher(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) { return }

            if (data.id) {
                if (!(await this.teachersListStore.update(data))) { return }
            } else {
                if (!(await this.teachersListStore.store(data))) { return }
            }

            this.selected_teachers = []
            await this.teachersListStore.index()
            this.data = {}
            this.action = ''
        },

        createTeacher() {
            this.data = { is_selectable: true }
            this.action = 'create_teacher'
        },

        editTeacher(teacher_id) {
            const teacher = this.teachers.find((s) => s.id === teacher_id)
            this.data = JSON.parse(JSON.stringify(teacher))
            this.action = 'edit_teacher'
        },

        abort() {
            this.action = ''
        },

        deleteTeacher() {
            this.action = 'delete_teacher'
        },

        async doDeleteTeachers(data) {
            if (!(await this.teachersListStore.deleteTeachers(data))) { return }
            this.selected_teachers = []
            await this.teachersListStore.index()
            this.action = ''
        },
    },
}
</script>

<style scoped src="../../../../../css/admin-index-page.css"></style>
<style scoped src="../../../../../css/admin-crud-panel.css"></style>
