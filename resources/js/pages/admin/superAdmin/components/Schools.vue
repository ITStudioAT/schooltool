<template>
    <v-col cols="12" :xl="schoolsMainXlCols">
        <section class="schools-shell admin-card ai-glass-panel" :class="{ 'is-disabled': action != '' }">
            <div class="admin-card-head schools-head">
                <div>
                    <div class="admin-card-eyebrow">Verwaltung</div>
                    <h2 class="admin-card-title schools-title">Schulen</h2>
                </div>

                <div class="admin-kpi-grid schools-kpis">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Gesamt</div>
                        <div class="kpi-value">{{ totalSchoolsCount }}</div>
                    </div>
                </div>
            </div>

            <div class="schools-content-grid">
                <section class="admin-card ai-glass-panel schools-main-card">
                    <div class="schools-toolbar">
                        <div class="empty-state schools-search-panel">
                            <SearchField :store="schoolStore" selected_field="selected_schools" />
                        </div>

                        <div class="schools-bulk-actions" :disabled="action != ''">
                            <v-btn color="primary" variant="tonal" rounded="lg" class="text-caption" @click="selectAll">
                                Alle auswählen [{{ Math.max(0, schools.length - selected_schools.length) }}]
                            </v-btn>
                            <v-btn color="primary" variant="text" rounded="lg" class="text-caption" @click="unselectAll">
                                Alle abwählen [{{ selected_schools.length }}]
                            </v-btn>
                        </div>
                    </div>

                    <div class="empty-state schools-list-shell" v-if="schools.length === 0">Keine Schulen gefunden.</div>
                    <div class="empty-state schools-list-shell" v-else>
                        <v-list
                            dense
                            variant="flat"
                            class="schools-list"
                            select-strategy="leaf"
                            v-model:selected="selected_schools"
                            color="success-lighten-2">
                            <v-list-item
                                v-for="item in schools"
                                :key="item.id"
                                :value="item.id"
                                class="schools-list-item"
                                :class="{ 'is-selected': isSelectedSchool(item.id) }">
                                <template #title>
                                    <div class="person-row schools-item-row">
                                        <div class="schools-item-main">
                                            <div class="person-body schools-item-copy">
                                                <div class="person-name">{{ item.long_name }}</div>
                                                <div class="person-roles">{{ item.short_name || '-' }}</div>
                                                <div class="person-email schools-item-email">{{ item.email || 'Keine E-Mail hinterlegt' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                    </div>

                    <div class="empty-state schools-pagination">
                        <Pagination :meta="safeMeta" :store="schoolStore" selected_field="selected_schools" />
                    </div>
                </section>

                <aside class="schools-side-stack">
                    <section class="admin-card ai-glass-panel">
                        <div class="admin-card-head schools-card-head">
                            <div>
                                <div class="admin-card-eyebrow">Aktionen</div>
                                <h3 class="admin-card-title">Schulen verwalten</h3>
                            </div>
                        </div>
                        <div class="kpi-sub schools-card-copy">Verfügbare Schritte für die aktuelle Auswahl.</div>

                        <div class="schools-action-list">
                            <v-btn block color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="createSchool">
                                Hinzufügen
                            </v-btn>

                            <v-btn
                                v-if="selected_schools.length == 1"
                                block
                                color="primary"
                                variant="tonal"
                                rounded="lg"
                                prepend-icon="mdi-pencil"
                                @click="editSchool(selected_schools[0])">
                                Ändern
                            </v-btn>

                            <v-btn
                                v-if="selected_schools.length >= 1"
                                block
                                color="warning"
                                variant="tonal"
                                rounded="lg"
                                prepend-icon="mdi-delete"
                                @click="deleteSchool">
                                Löschen
                            </v-btn>
                        </div>
                    </section>

                </aside>
            </div>
        </section>
    </v-col>

    <v-dialog v-model="schoolDialogOpen" persistent :max-width="schoolDialogMaxWidth" scrollable>
        <v-card class="schools-dialog-card ai-glass-panel">
            <div class="schools-dialog-head">
                <div>
                    <div class="admin-card-eyebrow" :class="{ 'schools-delete-eyebrow': action == 'delete_school' }">
                        {{ action == 'delete_school' ? 'Achtung' : 'Schule' }}
                    </div>
                    <div class="admin-card-title schools-dialog-title">{{ schoolDialogTitle }}</div>
                </div>

                <v-btn icon="mdi-close" variant="text" rounded="lg" @click="abort" />
            </div>

            <v-card-text class="schools-dialog-body">
                <template v-if="action == 'create_school' || action == 'edit_school'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="saveSchool(data)" class="mb-2 schools-editor-form">
                        <div class="empty-state schools-form-section">
                            <v-row dense>
                                <v-col cols="12">
                                    <v-text-field autofocus v-model="data.long_name" label="Schule (langer Name)" :rules="[required(), maxLength(255)]" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="data.short_name" label="Schule (kurzer Name)" :rules="[required(), maxLength(255)]" />
                                </v-col>

                                <v-col cols="12">
                                    <v-text-field v-model="data.email" label="E-Mail" :rules="[required(), mail(), maxLength(255)]" />
                                </v-col>

                                <v-col cols="12">
                                    <v-checkbox hide-details v-model="data.is_selectable" label="Auswählbar" />
                                </v-col>
                            </v-row>
                        </div>

                        <div class="empty-state schools-form-section mt-3" v-if="data.id">
                            <div class="admin-card-eyebrow">Logo</div>
                            <div class="schools-logo-block mt-2">
                                <div v-if="data.upload_file" class="schools-logo-preview">
                                    <div class="schools-logo-canvas">
                                        <img :src="`${data.upload_file}`" alt="Logo" height="60px" class="schools-logo-image" />
                                    </div>
                                </div>

                                <div v-if="data.logo && !data.upload_file" class="d-flex flex-row align-center justify-space-between ga-2 schools-logo-preview">
                                    <div class="schools-logo-canvas">
                                        <img :src="'/storage/images/logos/' + data.logo + '?t=' + Date.now()" alt="Logo" height="60px" class="schools-logo-image" />
                                    </div>
                                    <v-btn
                                        color="error"
                                        variant="tonal"
                                        rounded="lg"
                                        class="text-caption"
                                        prepend-icon="mdi-delete"
                                        @click="removeLogo">
                                        Löschen
                                    </v-btn>
                                </div>

                                <div class="kpi-sub" v-if="!data.logo && !data.upload_file">Kein Logo hochgeladen</div>

                                <FileUpload path="/api/admin/schools_upload/uploadLogo" @fileUploadFinished="fileUploadFinished" @uploadStart="onUploadStart" class="mt-2" />
                            </div>
                        </div>

                        <div class="schools-form-actions d-flex flex-row align-center justify-space-between mt-4" :disabled="is_uploading">
                            <v-btn color="warning" variant="text" rounded="lg" @click="abort">Abbruch</v-btn>
                            <v-btn color="success" variant="flat" rounded="lg" type="submit" :loading="is_uploading">Speichern</v-btn>
                        </div>
                    </v-form>
                </template>

                <template v-else-if="action == 'delete_school'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteSchools(selected_schools)" class="schools-delete-form">
                        <div class="empty-state schools-delete-alert">
                            <div class="admin-card-eyebrow schools-delete-eyebrow">Achtung</div>
                            <div class="admin-card-title schools-delete-title">Schule löschen</div>
                            <div class="kpi-sub mt-2" v-if="selected_schools.length == 1">
                                Es soll eine Schule gelöscht werden. Sind Sie sicher, dass Sie die markierte Schule löschen möchten?
                            </div>
                            <div class="kpi-sub mt-2" v-if="selected_schools.length > 1">
                                Es sollen {{ selected_schools.length }} Schulen gelöscht werden. Sind Sie sicher, dass Sie die markierten Schulen löschen möchten?
                            </div>
                        </div>

                        <div class="schools-form-actions d-flex flex-row align-center justify-space-between mt-4">
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
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'
import FileUpload from '@/pages/components/FileUpload.vue'

// SPECIFIC

import { useSchoolStore } from '@/stores/admin/SchoolStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField, FileUpload },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolStore = useSchoolStore()
        await this.schoolStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolStore: null,
            is_valid: false,
            upload_file: null,
            is_uploading: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useSchoolStore, ['schools', 'meta', 'selected_schools', 'data', 'answer']),
        schoolDialogOpen: {
            get() {
                return ['create_school', 'edit_school', 'delete_school'].includes(this.action)
            },
            set(value) {
                if (!value) this.action = ''
            },
        },
        schoolDialogMaxWidth() {
            return this.action == 'delete_school' ? 640 : 760
        },
        schoolDialogTitle() {
            if (this.action == 'create_school') return 'Neue Schule'
            if (this.action == 'edit_school') return 'Schule ändern'
            if (this.action == 'delete_school') return 'Löschen bestätigen'
            return 'Schule'
        },
        schoolsMainXlCols() {
            return 11
        },
        totalSchoolsCount() {
            const total = Number(this.meta?.total)
            return Number.isFinite(total) && total >= 0 ? total : this.schools.length
        },
        safeMeta() {
            return {
                from: this.meta?.from ?? 0,
                to: this.meta?.to ?? 0,
                total: this.meta?.total ?? this.schools.length,
                current_page: this.meta?.current_page ?? 1,
                last_page: this.meta?.last_page ?? 1,
            }
        },
    },

    methods: {
        onUploadStart() {
            this.is_uploading = true
        },

        fileUploadFinished(file) {
            this.is_uploading = false
            this.data.upload_file = '/storage/temp/' + file.name + '?t=' + Date.now()
        },

        removeLogo() {
            this.data.upload_file = null
            this.data.logo = null
        },

        async saveSchool(data) {
            if (this.is_uploading) return
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            if (data.id) {
                if (!(await this.schoolStore.update(data))) return
            } else {
                if (!(await this.schoolStore.store(data))) return
            }

            this.selected_schools = []
            // await this.adminStore.loadConfig()
            await this.schoolStore.index()
            this.data = {}
            this.action = ''
        },

        createSchool() {
            this.data = { is_selectable: true }
            this.action = 'create_school'
        },

        deleteSchool() {
            this.action = 'delete_school'
        },

        async doDeleteSchools(data) {
            if (!(await this.schoolStore.deleteSchools(data))) return
            this.selected_schools = []
            await this.schoolStore.index()
            this.action = ''
        },

        editSchool(school_id) {
            const school = this.schools.find((s) => s.id === school_id)
            this.data = JSON.parse(JSON.stringify(school))
            this.action = 'edit_school'
        },
        abort() {
            this.action = ''
        },

        selectAll() {
            this.selected_schools = this.schools.map((item) => item.id)
        },
        unselectAll() {
            this.selected_schools = []
        },
        isSelectedSchool(id) {
            return this.selected_schools.includes(id)
        },
    },
}
</script>

<style scoped src="../../../../../css/admin-index-page.css"></style>

<style scoped>
.schools-shell.is-disabled {
    opacity: 0.78;
}

.schools-head {
    align-items: flex-start;
    margin-bottom: 14px;
}

.schools-title {
    font-size: 1.35rem;
}

.schools-subtitle {
    margin-top: 8px;
    max-width: 60ch;
}

.schools-kpis {
    margin-top: 0;
    min-width: min(180px, 100%);
    grid-template-columns: minmax(140px, 220px);
    gap: 8px;
}

.schools-kpis .kpi-card {
    border-radius: 14px;
    padding: 10px 12px;
}

.schools-content-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 220px;
    gap: 12px;
    align-items: start;
}

.schools-main-card {
    padding: 12px;
}

.schools-toolbar {
    display: grid;
    gap: 10px;
    margin-bottom: 10px;
}

.schools-search-panel {
    padding: 10px 10px 2px;
}

.schools-bulk-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.schools-list-shell {
    padding: 6px;
}

.schools-list {
    background: transparent !important;
}

.schools-list-item {
    border-radius: 12px !important;
    margin-bottom: 4px;
    border: 1px solid transparent;
    transition: background-color 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
}

.schools-list-item:hover {
    background: rgba(46, 104, 171, 0.045);
    border-color: rgba(46, 104, 171, 0.1);
    transform: translateY(-1px);
}

.schools-list-item.is-selected {
    background: rgba(57, 73, 171, 0.08);
    border-color: rgba(57, 73, 171, 0.2);
}

.schools-item-row {
    margin: 1px 0;
}

.schools-item-main {
    display: flex;
    align-items: flex-start;
    min-width: 0;
}

.schools-item-copy {
    min-width: 0;
}

.schools-item-email {
    max-width: 280px;
}

.schools-pagination {
    margin-top: 10px;
    padding: 10px;
}

.schools-side-stack {
    display: grid;
    gap: 10px;
}

.schools-card-head {
    margin-bottom: 8px;
}

.schools-card-copy {
    margin-top: -2px;
}

.schools-action-list {
    margin-top: 10px;
    display: grid;
    gap: 8px;
}

.schools-dialog-card {
    border-radius: 20px !important;
    border: 1px solid rgba(16, 38, 58, 0.08) !important;
    box-shadow: 0 18px 48px rgba(16, 38, 58, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.65) !important;
    overflow: hidden;
}

.schools-dialog-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px 12px;
    border-bottom: 1px solid rgba(16, 38, 58, 0.08);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.86), rgba(255, 255, 255, 0.76));
}

.schools-dialog-title {
    margin-top: 4px;
}

.schools-dialog-body {
    padding: 14px !important;
    background: linear-gradient(180deg, rgba(245, 248, 254, 0.95), rgba(239, 244, 250, 0.95)) !important;
}

.schools-editor-form,
.schools-delete-form {
    color: #112536;
}

.schools-form-section,
.schools-delete-alert {
    border-style: solid;
}

.schools-form-section {
    padding: 12px;
}

.schools-form-section :deep(.v-field) {
    border-radius: 12px !important;
    background: rgba(255, 255, 255, 0.8);
}

.schools-form-section :deep(.v-selection-control) {
    min-height: 36px;
}

.schools-logo-block {
    display: grid;
    gap: 8px;
}

.schools-logo-preview {
    border-radius: 12px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.78);
    padding: 10px;
}

.schools-logo-canvas {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 72px;
    min-width: 120px;
    border-radius: 10px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    background-color: #eef2f6;
    background-image:
        linear-gradient(45deg, rgba(16, 38, 58, 0.05) 25%, transparent 25%),
        linear-gradient(-45deg, rgba(16, 38, 58, 0.05) 25%, transparent 25%),
        linear-gradient(45deg, transparent 75%, rgba(16, 38, 58, 0.05) 75%),
        linear-gradient(-45deg, transparent 75%, rgba(16, 38, 58, 0.05) 75%);
    background-size: 16px 16px;
    background-position: 0 0, 0 8px, 8px -8px, -8px 0;
    padding: 6px 10px;
}

.schools-logo-image {
    display: block;
    max-width: 220px;
    object-fit: contain;
}

.schools-form-actions {
    border-top: 1px solid rgba(16, 38, 58, 0.08);
    padding-top: 12px;
}

.schools-delete-alert {
    padding: 12px;
    border-color: rgba(220, 53, 69, 0.18);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.78), rgba(255, 244, 246, 0.78));
}

.schools-delete-eyebrow {
    color: #932f3c;
}

.schools-delete-title {
    margin-top: 4px;
    font-size: 1rem;
}

.schools-search-panel :deep(.v-text-field),
.schools-pagination :deep(.v-btn) {
    font-size: 0.85rem;
}

.schools-search-panel :deep(.v-input__control),
.schools-search-panel :deep(.v-field) {
    border-radius: 12px !important;
}

.schools-pagination :deep(.v-btn) {
    border-radius: 10px !important;
    min-width: 34px;
}

@media (max-width: 1260px) {
    .schools-content-grid {
        grid-template-columns: 1fr;
    }

    .schools-side-stack {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .schools-head {
        flex-direction: column;
    }

    .schools-kpis {
        width: 100%;
        min-width: 0;
    }

    .schools-item-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .schools-side-stack {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .schools-shell {
        border-radius: 16px;
        padding: 10px;
    }

    .schools-main-card,
    .schools-side-stack > .admin-card {
        border-radius: 14px;
        padding: 10px;
    }

    .schools-dialog-head {
        padding: 12px;
    }

    .schools-dialog-body {
        padding: 10px !important;
    }

    .schools-kpis {
        grid-template-columns: 1fr;
    }
}
</style>
