<template>
    <v-col cols="12" :xl="schoolyearsMainXlCols">
        <section class="schoolyears-shell admin-card ai-glass-panel" :class="{ 'is-disabled': action != '' }">
            <div class="admin-card-head schoolyears-head">
                <div>
                    <div class="admin-card-eyebrow">Verwaltung</div>
                    <h2 class="admin-card-title schoolyears-title">Schuljahre</h2>
                </div>

                <div class="admin-kpi-grid schoolyears-kpis">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Gesamt</div>
                        <div class="kpi-value">{{ totalSchoolyearsCount }}</div>
                    </div>
                </div>
            </div>

            <div class="schoolyears-content-grid">
                <section class="admin-card ai-glass-panel schoolyears-main-card">
                    <div class="schoolyears-toolbar">
                        <div class="empty-state schoolyears-search-panel">
                            <SearchField20 :store="schoolyearStore" index_method="indexPaginate" selected_field="selected_schoolyear" />
                        </div>

                        <div class="schoolyears-bulk-actions" :disabled="action != ''">
                            <v-btn color="primary" variant="tonal" rounded="lg" class="text-caption" @click="selectAll">
                                Alle auswählen [{{ Math.max(0, schoolyears.length - selected_schoolyears.length) }}]
                            </v-btn>
                            <v-btn color="primary" variant="text" rounded="lg" class="text-caption" @click="unselectAll">
                                Alle abwählen [{{ selected_schoolyears.length }}]
                            </v-btn>
                        </div>
                    </div>

                    <div class="empty-state schoolyears-list-shell" v-if="schoolyears.length === 0">Keine Schuljahre gefunden.</div>
                    <div class="empty-state schoolyears-list-shell" v-else>
                        <v-list
                            dense
                            variant="flat"
                            class="schoolyears-list"
                            select-strategy="leaf"
                            v-model:selected="selected_schoolyears"
                            color="success-lighten-2">
                            <v-list-item
                                v-for="item in schoolyears"
                                :key="item.id"
                                :value="item.id"
                                class="schoolyears-list-item"
                                :class="{
                                    'is-selected': isSelectedSchoolyear(item.id),
                                    'is-active': isActiveSchoolyear(item.id),
                                }">
                                <template #title>
                                    <div class="person-row schoolyears-item-row">
                                        <div class="schoolyears-item-main">
                                            <div class="person-body schoolyears-item-copy">
                                                <div class="person-name">{{ item.name }}</div>
                                                <div v-if="item.from || item.until" class="person-email schoolyears-item-range">
                                                    {{ formatRange(item) }}
                                                </div>
                                                <div v-else class="person-email schoolyears-item-range">Kein Zeitraum hinterlegt</div>
                                            </div>
                                        </div>

                                        <div class="schoolyears-item-meta" v-if="isActiveSchoolyear(item.id)">
                                            <div class="status-badge is-success">
                                                <v-icon size="14" icon="mdi-check-circle" />
                                                <span>aktiv</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                    </div>

                    <div class="empty-state schoolyears-pagination">
                        <Pagination20 :meta="safeMeta" :store="schoolyearStore" index_method="indexPaginate" selected_field="selected_schoolyear" />
                    </div>
                </section>

                <aside class="schoolyears-side-stack">
                    <section class="admin-card ai-glass-panel">
                        <div class="admin-card-head schoolyears-card-head">
                            <div>
                                <div class="admin-card-eyebrow">Aktionen</div>
                                <h3 class="admin-card-title">Schuljahre verwalten</h3>
                            </div>
                        </div>
                        <div class="kpi-sub schoolyears-card-copy">Anlegen, ändern, aktiv setzen oder löschen.</div>

                        <div class="schoolyears-action-list">
                            <v-btn block color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="createSchoolyear">
                                Hinzufügen
                            </v-btn>

                            <v-btn
                                v-if="selected_schoolyears.length == 1"
                                block
                                color="primary"
                                variant="tonal"
                                rounded="lg"
                                prepend-icon="mdi-pencil"
                                @click="editSchoolyear(selected_schoolyears[0])">
                                Ändern
                            </v-btn>

                            <v-btn
                                v-if="selected_schoolyears.length == 1"
                                block
                                color="success"
                                variant="tonal"
                                rounded="lg"
                                prepend-icon="mdi-check-circle"
                                @click="setActiveSchoolyear">
                                Aktiv setzen
                            </v-btn>

                            <v-btn
                                v-if="selected_schoolyears.length >= 1"
                                block
                                color="warning"
                                variant="tonal"
                                rounded="lg"
                                prepend-icon="mdi-delete"
                                @click="deleteSchoolyear">
                                Löschen
                            </v-btn>
                        </div>
                    </section>
                </aside>
            </div>
        </section>
    </v-col>

    <v-dialog v-model="schoolyearDialogOpen" persistent :max-width="schoolyearDialogMaxWidth" scrollable>
        <v-card class="schoolyears-dialog-card ai-glass-panel">
            <div class="schoolyears-dialog-head">
                <div>
                    <div class="admin-card-eyebrow" :class="{ 'schoolyears-delete-eyebrow': action == 'delete_schoolyear' }">
                        {{ action == 'delete_schoolyear' ? 'Achtung' : 'Schuljahr' }}
                    </div>
                    <div class="admin-card-title schoolyears-dialog-title">{{ schoolyearDialogTitle }}</div>
                </div>

                <v-btn icon="mdi-close" variant="text" rounded="lg" @click="abort" />
            </div>

            <v-card-text class="schoolyears-dialog-body">
                <template v-if="action == 'create_schoolyear' || action == 'edit_schoolyear'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="saveSchoolyear(data)" class="mb-2 schoolyears-editor-form">
                        <div class="empty-state schoolyears-form-section">
                            <v-row dense>
                                <v-col cols="12">
                                    <v-text-field autofocus v-model="data.name" label="Bezeichnung" :rules="[required(), maxLength(255)]" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="data.concerns" label="Schuljahr (z.B. 2025/26)" :rules="[maxLength(255)]" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="data.from" label="Beginn des Schuljahres (jjjj-mm-tt)" :rules="[dateOrNull()]" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="data.until" label="Ende des Schuljahres (jjjj-mm-tt)" :rules="[dateOrNull()]" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="data.sem_2_start" label="Beginn des 2. Semesters (jjjj-mm-tt)" :rules="[dateOrNull()]" />
                                </v-col>
                            </v-row>
                        </div>

                        <div class="schoolyears-form-actions d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" variant="text" rounded="lg" @click="abort">Abbruch</v-btn>
                            <v-btn color="success" variant="flat" rounded="lg" type="submit">Speichern</v-btn>
                        </div>
                    </v-form>
                </template>

                <template v-else-if="action == 'delete_schoolyear'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteSchoolyears(selected_schoolyears)" class="schoolyears-delete-form">
                        <div class="empty-state schoolyears-delete-alert">
                            <div class="admin-card-eyebrow schoolyears-delete-eyebrow">Achtung</div>
                            <div class="admin-card-title schoolyears-delete-title">Schuljahr löschen</div>
                            <div class="kpi-sub mt-2" v-if="selected_schoolyears.length == 1">
                                Es soll ein Schuljahr gelöscht werden. Sind Sie sicher, dass Sie das markierte Schuljahr löschen möchten?
                            </div>
                            <div class="kpi-sub mt-2" v-if="selected_schoolyears.length > 1">
                                Es sollen {{ selected_schoolyears.length }} Schuljahre gelöscht werden. Sind Sie sicher, dass Sie die markierten Schuljahre löschen möchten?
                            </div>
                        </div>

                        <div class="schoolyears-form-actions d-flex flex-row align-center justify-space-between mt-4">
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

import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import { useSchoolToolStore } from '@/stores/admin/SchoolToolStore'
import Pagination20 from '@/pages/components/Pagination20.vue'
import SearchField20 from '@/pages/components/SearchField20.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination20, SearchField20 },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolyearStore = useSchoolyearStore()
        this.schoolToolStore = useSchoolToolStore()
        await this.schoolyearStore.indexPaginate()
        await this.schoolToolStore.loadConfig()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolyearStore: null,
            schoolToolStore: null,
            is_valid: false,
            selected_schoolyears: [],
            data: {},
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useSchoolyearStore, ['schoolyears', 'meta']),
        schoolyearDialogOpen: {
            get() {
                return ['create_schoolyear', 'edit_schoolyear', 'delete_schoolyear'].includes(this.action)
            },
            set(value) {
                if (!value) this.action = ''
            },
        },
        schoolyearDialogMaxWidth() {
            return this.action == 'delete_schoolyear' ? 640 : 760
        },
        schoolyearDialogTitle() {
            if (this.action == 'create_schoolyear') return 'Neues Schuljahr'
            if (this.action == 'edit_schoolyear') return 'Schuljahr ändern'
            if (this.action == 'delete_schoolyear') return 'Löschen bestätigen'
            return 'Schuljahr'
        },
        schoolyearsMainXlCols() {
            return 11
        },
        totalSchoolyearsCount() {
            const total = Number(this.meta?.total)
            return Number.isFinite(total) && total >= 0 ? total : this.schoolyears.length
        },
        safeMeta() {
            return {
                from: this.meta?.from ?? 0,
                to: this.meta?.to ?? 0,
                total: this.meta?.total ?? this.schoolyears.length,
                current_page: this.meta?.current_page ?? 1,
                last_page: this.meta?.last_page ?? 1,
            }
        },
    },

    methods: {
        formatRange(item) {
            const from = item.from || ''
            const until = item.until || ''
            if (from && until) return `${from} - ${until}`
            if (from) return `ab ${from}`
            if (until) return `bis ${until}`
            return ''
        },

        async saveSchoolyear(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            if (data.id) {
                if (!(await this.schoolyearStore.update(data))) return
            } else {
                if (!(await this.schoolyearStore.store(data))) return
            }

            await this.schoolyearStore.index()
            this.data = {}
            this.action = ''
        },

        createSchoolyear() {
            this.data = {}
            this.action = 'create_schoolyear'
        },

        deleteSchoolyear() {
            this.action = 'delete_schoolyear'
        },

        async doDeleteSchoolyears(data) {
            for (const schoolyear_id of data) {
                const schoolyear = this.schoolyears.find((s) => s.id === schoolyear_id)
                if (!schoolyear) continue
                this.schoolyearStore.selected_schoolyear = schoolyear
                if (!(await this.schoolyearStore.destroy(schoolyear))) return
            }
            this.selected_schoolyears = []
            await this.schoolyearStore.index()
            this.action = ''
        },

        editSchoolyear(schoolyear_id) {
            const schoolyear = this.schoolyears.find((s) => s.id === schoolyear_id)
            this.data = JSON.parse(JSON.stringify(schoolyear))
            this.action = 'edit_schoolyear'
        },

        abort() {
            this.action = ''
        },

        selectAll() {
            this.selected_schoolyears = this.schoolyears.map((item) => item.id)
        },
        unselectAll() {
            this.selected_schoolyears = []
        },
        isSelectedSchoolyear(id) {
            return this.selected_schoolyears.includes(id)
        },

        isActiveSchoolyear(schoolyear_id) {
            return this.schoolToolStore?.data?.active_schoolyear_id === schoolyear_id
        },

        async setActiveSchoolyear() {
            if (this.selected_schoolyears.length !== 1) return

            const schoolyear_id = this.selected_schoolyears[0]
            if (await this.schoolyearStore.setActiveSchoolyearInSchoolTool(schoolyear_id)) {
                await this.schoolToolStore.loadConfig()
            }
        },
    },
}
</script>

<style scoped src="../../../../../css/admin-index-page.css"></style>

<style scoped>
.schoolyears-shell.is-disabled {
    opacity: 0.78;
}

.schoolyears-head {
    align-items: flex-start;
    margin-bottom: 14px;
}

.schoolyears-title {
    font-size: 1.35rem;
}

.schoolyears-kpis {
    margin-top: 0;
    min-width: min(180px, 100%);
    grid-template-columns: minmax(140px, 220px);
    gap: 8px;
}

.schoolyears-kpis .kpi-card {
    border-radius: 14px;
    padding: 10px 12px;
}

.schoolyears-content-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 220px;
    gap: 12px;
    align-items: start;
}

.schoolyears-main-card {
    padding: 12px;
}

.schoolyears-toolbar {
    display: grid;
    gap: 10px;
    margin-bottom: 10px;
}

.schoolyears-search-panel {
    padding: 10px 10px 2px;
}

.schoolyears-bulk-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.schoolyears-list-shell {
    padding: 6px;
}

.schoolyears-list {
    background: transparent !important;
}

.schoolyears-list-item {
    border-radius: 12px !important;
    margin-bottom: 4px;
    border: 1px solid transparent;
    transition: background-color 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
}

.schoolyears-list-item:hover {
    background: rgba(46, 104, 171, 0.045);
    border-color: rgba(46, 104, 171, 0.1);
    transform: translateY(-1px);
}

.schoolyears-list-item.is-selected {
    background: rgba(57, 73, 171, 0.08);
    border-color: rgba(57, 73, 171, 0.2);
}

.schoolyears-list-item.is-active {
    box-shadow: inset 0 0 0 1px rgba(46, 164, 79, 0.2);
}

.schoolyears-item-row {
    margin: 1px 0;
}

.schoolyears-item-main {
    display: flex;
    align-items: flex-start;
    min-width: 0;
}

.schoolyears-item-copy {
    min-width: 0;
}

.schoolyears-item-range {
    max-width: 320px;
}

.schoolyears-item-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.schoolyears-pagination {
    margin-top: 10px;
    padding: 10px;
}

.schoolyears-side-stack {
    display: grid;
    gap: 10px;
}

.schoolyears-card-head {
    margin-bottom: 8px;
}

.schoolyears-card-copy {
    margin-top: -2px;
}

.schoolyears-action-list {
    margin-top: 10px;
    display: grid;
    gap: 8px;
}

.schoolyears-dialog-card {
    border-radius: 20px !important;
    border: 1px solid rgba(16, 38, 58, 0.08) !important;
    box-shadow: 0 18px 48px rgba(16, 38, 58, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.65) !important;
    overflow: hidden;
}

.schoolyears-dialog-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px 12px;
    border-bottom: 1px solid rgba(16, 38, 58, 0.08);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.86), rgba(255, 255, 255, 0.76));
}

.schoolyears-dialog-title {
    margin-top: 4px;
}

.schoolyears-dialog-body {
    padding: 14px !important;
    background: linear-gradient(180deg, rgba(245, 248, 254, 0.95), rgba(239, 244, 250, 0.95)) !important;
}

.schoolyears-editor-form,
.schoolyears-delete-form {
    color: #112536;
}

.schoolyears-form-section,
.schoolyears-delete-alert {
    border-style: solid;
}

.schoolyears-form-section {
    padding: 12px;
}

.schoolyears-form-section :deep(.v-field) {
    border-radius: 12px !important;
    background: rgba(255, 255, 255, 0.8);
}

.schoolyears-form-actions {
    border-top: 1px solid rgba(16, 38, 58, 0.08);
    padding-top: 12px;
}

.schoolyears-delete-alert {
    padding: 12px;
    border-color: rgba(220, 53, 69, 0.18);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.78), rgba(255, 244, 246, 0.78));
}

.schoolyears-delete-eyebrow {
    color: #932f3c;
}

.schoolyears-delete-title {
    margin-top: 4px;
    font-size: 1rem;
}

.schoolyears-search-panel :deep(.v-text-field),
.schoolyears-pagination :deep(.v-btn) {
    font-size: 0.85rem;
}

.schoolyears-search-panel :deep(.v-input__control),
.schoolyears-search-panel :deep(.v-field) {
    border-radius: 12px !important;
}

.schoolyears-pagination :deep(.v-btn) {
    border-radius: 10px !important;
    min-width: 34px;
}

@media (max-width: 1260px) {
    .schoolyears-content-grid {
        grid-template-columns: 1fr;
    }

    .schoolyears-side-stack {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .schoolyears-head {
        flex-direction: column;
    }

    .schoolyears-kpis {
        width: 100%;
        min-width: 0;
    }

    .schoolyears-item-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .schoolyears-item-meta {
        justify-content: flex-start;
    }

    .schoolyears-side-stack {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .schoolyears-shell {
        border-radius: 16px;
        padding: 10px;
    }

    .schoolyears-main-card,
    .schoolyears-side-stack > .admin-card {
        border-radius: 14px;
        padding: 10px;
    }

    .schoolyears-dialog-head {
        padding: 12px;
    }

    .schoolyears-dialog-body {
        padding: 10px !important;
    }

    .schoolyears-kpis {
        grid-template-columns: 1fr;
    }
}
</style>
