<template>
    <v-col cols="12" :xl="schoolsMainXlCols">
        <section class="crud-shell admin-card ai-glass-panel" :class="{ 'is-disabled': action != '' }">
            <div class="admin-card-head crud-head mb-4">
                <div>
                    <div class="admin-card-eyebrow">Verwaltung</div>
                    <h2 class="admin-card-title crud-title">{{ schoolsHeading }}</h2>
                </div>

                <div class="admin-kpi-grid crud-kpis">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Gesamt</div>
                        <div class="kpi-value">{{ totalSchoolsCount }}</div>
                    </div>
                </div>
            </div>

            <div class="crud-content-grid">
                <section class="admin-card ai-glass-panel crud-main-card pa-3">
                    <div v-if="canManageAllSchools" class="d-grid ga-3 mb-3">
                        <div class="empty-state crud-search-panel">
                            <SearchField :store="schoolStore" selected_field="selected_schools" />
                        </div>

                        <div class="d-flex flex-wrap ga-2" :disabled="action != ''">
                            <v-btn color="primary" variant="tonal" rounded="lg" class="text-caption" @click="selectAll">
                                Alle auswählen [{{ Math.max(0, schools.length - selected_schools.length) }}]
                            </v-btn>
                            <v-btn color="primary" variant="text" rounded="lg" class="text-caption" @click="unselectAll">
                                Alle abwählen [{{ selected_schools.length }}]
                            </v-btn>
                        </div>
                    </div>

                    <div class="empty-state pa-2" v-if="schools.length === 0">Keine Schulen gefunden.</div>
                    <div class="empty-state pa-2" v-else>
                        <v-list
                            dense
                            variant="flat"
                            class="crud-list"
                            select-strategy="leaf"
                            v-model:selected="selected_schools"
                            color="success-lighten-2">
                            <v-list-item
                                v-for="item in schools"
                                :key="item.id"
                                :value="item.id"
                                class="crud-list-item"
                                :class="{ 'is-selected': isSelectedSchool(item.id) }">
                                <template #title>
                                    <div class="person-row crud-item-row">
                                        <div class="d-flex align-center ga-3" style="min-width: 0">
                                            <div v-if="item.logo" class="school-list-logo">
                                                <img
                                                    :src="'/storage/images/logos/' + item.logo"
                                                    :alt="`Logo von ${item.long_name}`" />
                                            </div>
                                            <div class="person-body" style="min-width: 0">
                                                <div class="d-flex align-center ga-2">
                                                    <span class="school-color-swatch" :style="{ backgroundColor: item.color }" aria-hidden="true"></span>
                                                    <div class="person-name">{{ item.long_name }}</div>
                                                </div>
                                                <div class="person-roles">{{ item.short_name || '-' }}</div>
                                                <div class="person-email" style="max-width: 280px">{{ item.email || 'Keine E-Mail hinterlegt' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                    </div>

                    <div v-if="canManageAllSchools" class="empty-state crud-pagination mt-3 pa-3">
                        <Pagination :meta="safeMeta" :store="schoolStore" selected_field="selected_schools" />
                    </div>
                </section>

                <aside class="crud-side-stack">
                    <section class="admin-card ai-glass-panel">
                        <div class="admin-card-head mb-2">
                            <div>
                                <div class="admin-card-eyebrow">Aktionen</div>
                                <h3 class="admin-card-title">{{ schoolActionsHeading }}</h3>
                            </div>
                        </div>
                        <div class="kpi-sub" style="margin-top: -2px">Verfügbare Schritte für die aktuelle Auswahl.</div>

                        <div v-if="canManageAllSchools" class="crud-actions-primary">
                            <v-btn block color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="createSchool">
                                Hinzufügen
                            </v-btn>
                        </div>

                        <template v-if="selected_schools.length >= 1">
                            <v-divider class="crud-actions-divider" />
                            <div class="crud-actions-secondary">
                                <v-btn
                                    v-if="selected_schools.length === 1"
                                    block
                                    color="primary"
                                    variant="tonal"
                                    rounded="lg"
                                    prepend-icon="mdi-pencil"
                                    @click="editSchool(selected_schools[0])">
                                    {{ canManageAllSchools ? 'Ändern' : 'Farbe ändern' }}
                                </v-btn>

                                <v-btn
                                    v-if="canManageAllSchools"
                                    block
                                    color="info"
                                    variant="tonal"
                                    rounded="lg"
                                    class="crud-action-btn-offset"
                                    prepend-icon="mdi-cloud-sync-outline"
                                    @click="openCloudwaysSyncDialog">
                                    Synchronisieren
                                </v-btn>

                                <v-btn
                                    v-if="canManageAllSchools"
                                    block
                                    color="warning"
                                    variant="tonal"
                                    rounded="lg"
                                    class="crud-action-btn-offset"
                                    prepend-icon="mdi-delete"
                                    @click="deleteSchool">
                                    Löschen
                                </v-btn>
                            </div>
                        </template>
                    </section>

                </aside>
            </div>
        </section>
    </v-col>

    <v-dialog v-model="schoolDialogOpen" persistent :max-width="schoolDialogMaxWidth" scrollable>
        <v-card class="crud-dialog-card ai-glass-panel">
            <div class="crud-dialog-head">
                <div>
                    <div class="admin-card-eyebrow" :class="{ 'crud-delete-eyebrow': action == 'delete_school' }">
                        {{ action == 'delete_school' ? 'Achtung' : 'Schule' }}
                    </div>
                    <div class="admin-card-title" style="margin-top: 4px">{{ schoolDialogTitle }}</div>
                </div>

                <v-btn icon="mdi-close" variant="text" rounded="lg" @click="abort" />
            </div>

            <v-card-text class="crud-dialog-body">
                <template v-if="action == 'create_school' || action == 'edit_school'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="saveSchool(data)" class="mb-2 crud-form">
                        <div class="empty-state crud-form-section">
                            <v-row v-if="canManageAllSchools" dense>
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

                            <div class="school-color-field" :class="{ 'mt-4': canManageAllSchools }">
                                <div class="admin-card-eyebrow">Schulfarbe</div>
                                <div class="kpi-sub mt-1">Diese Farbe kennzeichnet die Schule in der Anwendung.</div>
                                <div class="school-color-picker-wrap mt-3">
                                    <v-color-picker
                                        v-model="data.color"
                                        mode="hex"
                                        :modes="['hex']"
                                        :swatches="schoolColorSwatches"
                                        show-swatches
                                        hide-eye-dropper
                                        width="100%" />
                                </div>
                            </div>
                        </div>

                        <div class="empty-state crud-form-section mt-3" v-if="canManageAllSchools && data.id">
                            <div class="admin-card-eyebrow">Logo</div>
                            <div class="d-grid ga-2 mt-2">
                                <div v-if="data.upload_file" class="schools-logo-preview">
                                    <div class="schools-logo-canvas">
                                        <img :src="`${data.upload_file}`" alt="Logo" height="60px" class="d-block" style="max-width: 220px; object-fit: contain" />
                                    </div>
                                </div>

                                <div v-if="data.logo && !data.upload_file" class="d-flex flex-row align-center justify-space-between ga-2 schools-logo-preview">
                                    <div class="schools-logo-canvas">
                                        <img :src="'/storage/images/logos/' + data.logo + '?t=' + Date.now()" alt="Logo" height="60px" class="d-block" style="max-width: 220px; object-fit: contain" />
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

                        <div class="crud-form-actions d-flex flex-row align-center justify-space-between mt-4" :disabled="is_uploading">
                            <v-btn color="warning" variant="text" rounded="lg" @click="abort">Abbruch</v-btn>
                            <v-btn color="success" variant="flat" rounded="lg" type="submit" :loading="is_uploading">Speichern</v-btn>
                        </div>
                    </v-form>
                </template>

                <template v-else-if="action == 'delete_school'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteSchools(selected_schools)" class="crud-form">
                        <div class="empty-state crud-delete-alert">
                            <div class="admin-card-eyebrow crud-delete-eyebrow">Achtung</div>
                            <div class="admin-card-title crud-delete-title">Schule löschen</div>
                            <div class="kpi-sub mt-2" v-if="selected_schools.length == 1">
                                Es soll eine Schule gelöscht werden. Sind Sie sicher, dass Sie die markierte Schule löschen möchten?
                            </div>
                            <div class="kpi-sub mt-2" v-if="selected_schools.length > 1">
                                Es sollen {{ selected_schools.length }} Schulen gelöscht werden. Sind Sie sicher, dass Sie die markierten Schulen löschen möchten?
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

    <v-dialog v-model="cloudways_sync_dialog" persistent max-width="760" scrollable>
        <v-card class="crud-dialog-card ai-glass-panel">
            <div class="crud-dialog-head">
                <div>
                    <div class="admin-card-eyebrow crud-delete-eyebrow">Lokale Daten ersetzen</div>
                    <div class="admin-card-title" style="margin-top: 4px">Mit Cloudways synchronisieren</div>
                </div>

                <v-btn
                    icon="mdi-close"
                    variant="text"
                    rounded="lg"
                    :disabled="is_synchronizing"
                    @click="closeCloudwaysSyncDialog" />
            </div>

            <v-card-text class="crud-dialog-body">
                <template v-if="!cloudways_sync_result">
                    <v-alert type="warning" variant="tonal" prominent class="mb-4">
                        Alle bestehenden lokalen Daten der ausgewählten Schule werden vollständig durch die Cloudways-Daten ersetzt.
                        Andere lokale Schulen bleiben unverändert.
                    </v-alert>

                    <div class="empty-state crud-form-section">
                        <div class="admin-card-title">{{ selectedSchool?.long_name }}</div>
                        <div class="kpi-sub mt-2">
                            {{ cloudways_sync_preview?.rows || 0 }} Datensätze aus
                            {{ cloudways_sync_preview?.tables || 0 }} Tabellen wurden auf Cloudways gefunden.
                        </div>
                        <div class="kpi-sub mt-2">
                            Es werden ausschließlich Datenbankeinträge synchronisiert. Dateien, Anhänge, Bilder, Logos, PDFs und Importdateien
                            werden nicht heruntergeladen. Sitzungen, API-Tokens, Passwort-Reset-Tokens, Queue- und Cache-Daten werden nicht übernommen.
                        </div>
                        <div class="kpi-sub mt-2">
                            Vor dem Ersetzen wird eine verschlüsselte lokale Sicherung der bisherigen Schuldaten erstellt. Bei einem Fehler wird die
                            Datenbanktransaktion vollständig zurückgerollt.
                        </div>
                    </div>

                    <v-text-field
                        v-model="cloudways_sync_confirmation"
                        class="mt-4"
                        :label="`Zur Bestätigung exakt eingeben: ${selectedSchool?.long_name || ''}`"
                        :disabled="is_synchronizing"
                        autocomplete="off" />
                </template>

                <v-alert v-else type="success" variant="tonal" prominent>
                    <div class="font-weight-bold">Synchronisierung abgeschlossen</div>
                    <div class="mt-2">
                        {{ cloudways_sync_result.rows }} Datensätze aus {{ cloudways_sync_result.tables }} Tabellen wurden übernommen.
                    </div>
                    <div class="mt-2 text-caption">Sicherung: {{ cloudways_sync_result.backup_path }}</div>
                </v-alert>
            </v-card-text>

            <v-card-actions class="d-flex justify-space-between pa-4">
                <v-btn color="secondary" variant="text" :disabled="is_synchronizing" @click="closeCloudwaysSyncDialog">
                    {{ cloudways_sync_result ? 'Schließen' : 'Abbrechen' }}
                </v-btn>
                <v-btn
                    v-if="!cloudways_sync_result"
                    color="error"
                    variant="flat"
                    prepend-icon="mdi-cloud-sync-outline"
                    :loading="is_synchronizing"
                    :disabled="!cloudwaysSyncConfirmationMatches || is_synchronizing"
                    @click="synchronizeCloudwaysSchool">
                    Lokale Schuldaten ersetzen
                </v-btn>
            </v-card-actions>
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

        if (this.canManageAllSchools) {
            await this.schoolStore.index()
            return
        }

        this.loadOwnSchool()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolStore: null,
            is_valid: false,
            upload_file: null,
            is_uploading: false,
            cloudways_sync_dialog: false,
            cloudways_sync_confirmation: '',
            cloudways_sync_preview: null,
            cloudways_sync_result: null,
            is_synchronizing: false,
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
            if (this.action == 'edit_school') return this.canManageAllSchools ? 'Schule ändern' : 'Schulfarbe ändern'
            if (this.action == 'delete_school') return 'Löschen bestätigen'
            return 'Schule'
        },
        configuredRoleNames() {
            return Array.isArray(this.config?.roles) ? this.config.roles : []
        },
        canManageAllSchools() {
            return this.configuredRoleNames.includes('super_admin')
        },
        schoolsHeading() {
            return this.canManageAllSchools ? 'Schulen' : 'Schule'
        },
        schoolActionsHeading() {
            return this.canManageAllSchools ? 'Schulen verwalten' : 'Schulfarbe verwalten'
        },
        schoolColorSwatches() {
            return [
                ['#1976D2', '#673AB7', '#E91E63', '#D32F2F'],
                ['#F57C00', '#FBC02D', '#388E3C', '#00796B'],
                ['#0288D1', '#455A64', '#5D4037', '#7B1FA2'],
            ]
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
        selectedSchool() {
            if (this.selected_schools.length !== 1) return null

            return this.schools.find((school) => school.id === this.selected_schools[0]) || null
        },
        cloudwaysSyncConfirmationMatches() {
            return this.cloudways_sync_confirmation === (this.selectedSchool?.long_name || '')
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

            data.color = typeof data.color === 'string' ? data.color.toUpperCase() : '#1976D2'

            if (data.id) {
                if (!(await this.schoolStore.update(data))) return
            } else {
                if (!(await this.schoolStore.store(data))) return
            }

            this.selected_schools = []

            if (this.canManageAllSchools) {
                await this.schoolStore.index()
            }

            await this.adminStore.loadConfig()

            if (!this.canManageAllSchools) {
                this.loadOwnSchool()
            }

            this.data = {}
            this.action = ''
        },

        createSchool() {
            this.data = { color: '#1976D2', is_selectable: true }
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
        loadOwnSchool() {
            const school = this.config?.selected_school
            this.schools = school ? [JSON.parse(JSON.stringify(school))] : []
            this.selected_schools = school ? [school.id] : []
            this.meta = {
                from: school ? 1 : 0,
                to: school ? 1 : 0,
                total: school ? 1 : 0,
                current_page: 1,
                last_page: 1,
            }
        },
        async openCloudwaysSyncDialog() {
            if (!this.selectedSchool) return

            const preview = await this.schoolStore.previewCloudwaysSchoolSynchronization(this.selectedSchool.id)
            if (!preview) return

            this.cloudways_sync_confirmation = ''
            this.cloudways_sync_preview = preview
            this.cloudways_sync_result = null
            this.cloudways_sync_dialog = true
        },
        closeCloudwaysSyncDialog() {
            if (this.is_synchronizing) return

            this.cloudways_sync_dialog = false
            this.cloudways_sync_confirmation = ''
            this.cloudways_sync_preview = null
            this.cloudways_sync_result = null
        },
        async synchronizeCloudwaysSchool() {
            if (!this.selectedSchool || !this.cloudwaysSyncConfirmationMatches || this.is_synchronizing) return

            this.is_synchronizing = true

            try {
                const result = await this.schoolStore.synchronizeCloudwaysSchool(
                    this.selectedSchool.id,
                    this.cloudways_sync_confirmation,
                )
                if (!result) return

                this.cloudways_sync_result = result
                await this.schoolStore.index()
            } finally {
                this.is_synchronizing = false
            }
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
<style scoped src="../../../../../css/admin-crud-panel.css"></style>

<style scoped>
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

.school-color-swatch {
    display: inline-block;
    width: 18px;
    height: 18px;
    flex: 0 0 18px;
    border: 2px solid rgba(255, 255, 255, 0.9);
    border-radius: 50%;
    box-shadow: 0 0 0 1px rgba(16, 38, 58, 0.18);
}

.school-list-logo {
    display: flex;
    width: 54px;
    height: 44px;
    flex: 0 0 54px;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.86);
    padding: 4px;
}

.school-list-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.school-color-picker-wrap {
    max-width: 420px;
    overflow: hidden;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 14px;
}
</style>
