<template>
    <v-col cols="12" :xl="schoolyearsMainXlCols">
        <section class="crud-shell admin-card ai-glass-panel" :class="{ 'is-disabled': action != '' }">
            <div class="admin-card-head crud-head mb-4">
                <div>
                    <div class="admin-card-eyebrow">Verwaltung</div>
                    <h2 class="admin-card-title crud-title">Schuljahre</h2>
                </div>

                <div class="admin-kpi-grid crud-kpis">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Gesamt</div>
                        <div class="kpi-value">{{ totalSchoolyearsCount }}</div>
                    </div>
                </div>
            </div>

            <div class="crud-content-grid">
                <section class="admin-card ai-glass-panel crud-main-card pa-3">
                    <div class="d-grid ga-3 mb-3">
                        <div class="empty-state crud-search-panel">
                            <SearchField20 :store="schoolyearStore" index_method="indexPaginate" selected_field="selected_schoolyear" />
                        </div>

                        <div class="d-flex flex-wrap ga-2" :disabled="action != ''">
                            <v-btn color="primary" variant="tonal" rounded="lg" class="text-caption" @click="selectAll">
                                Alle auswählen [{{ Math.max(0, schoolyears.length - selected_schoolyears.length) }}]
                            </v-btn>
                            <v-btn color="primary" variant="text" rounded="lg" class="text-caption" @click="unselectAll">
                                Alle abwählen [{{ selected_schoolyears.length }}]
                            </v-btn>
                        </div>
                    </div>

                    <div class="empty-state pa-2" v-if="schoolyears.length === 0">Keine Schuljahre gefunden.</div>
                    <div class="empty-state pa-2" v-else>
                        <v-list
                            dense
                            variant="flat"
                            class="crud-list"
                            select-strategy="leaf"
                            v-model:selected="selected_schoolyears"
                            color="success-lighten-2">
                            <v-list-item
                                v-for="item in schoolyears"
                                :key="item.id"
                                :value="item.id"
                                class="crud-list-item"
                                :class="{
                                    'is-selected': isSelectedSchoolyear(item.id),
                                    'is-active': isActiveSchoolyear(item.id),
                                }">
                                <template #title>
                                    <div class="person-row crud-item-row">
                                        <div class="d-flex align-start" style="min-width: 0">
                                            <div class="person-body" style="min-width: 0">
                                                <div class="person-name">{{ item.name }}</div>
                                                <div v-if="item.from || item.until" class="person-email" style="max-width: 320px">
                                                    {{ formatRange(item) }}
                                                </div>
                                                <div v-else class="person-email" style="max-width: 320px">Kein Zeitraum hinterlegt</div>
                                            </div>
                                        </div>

                                        <div class="d-flex align-center ga-2 flex-wrap flex-shrink-0" v-if="isActiveSchoolyear(item.id)">
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

                    <div class="empty-state crud-pagination mt-3 pa-3">
                        <Pagination20 :meta="safeMeta" :store="schoolyearStore" index_method="indexPaginate" selected_field="selected_schoolyear" />
                    </div>
                </section>

                <aside class="crud-side-stack">
                    <section class="admin-card ai-glass-panel">
                        <div class="admin-card-head mb-2">
                            <div>
                                <div class="admin-card-eyebrow">Aktionen</div>
                                <h3 class="admin-card-title">Schuljahre verwalten</h3>
                            </div>
                        </div>
                        <div class="kpi-sub" style="margin-top: -2px">Anlegen, ändern, aktiv setzen oder löschen.</div>

                        <div class="d-grid ga-2 mt-3">
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
        <v-card class="crud-dialog-card ai-glass-panel">
            <div class="crud-dialog-head">
                <div>
                    <div class="admin-card-eyebrow" :class="{ 'crud-delete-eyebrow': action == 'delete_schoolyear' }">
                        {{ action == 'delete_schoolyear' ? 'Achtung' : 'Schuljahr' }}
                    </div>
                    <div class="admin-card-title" style="margin-top: 4px">{{ schoolyearDialogTitle }}</div>
                </div>

                <v-btn icon="mdi-close" variant="text" rounded="lg" @click="abort" />
            </div>

            <v-card-text class="crud-dialog-body">
                <template v-if="action == 'create_schoolyear' || action == 'edit_schoolyear'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="saveSchoolyear(data)" class="mb-2 crud-form">
                        <div class="empty-state crud-form-section">
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

                        <div class="crud-form-actions d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" variant="text" rounded="lg" @click="abort">Abbruch</v-btn>
                            <v-btn color="success" variant="flat" rounded="lg" type="submit">Speichern</v-btn>
                        </div>
                    </v-form>
                </template>

                <template v-else-if="action == 'delete_schoolyear'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteSchoolyears(selected_schoolyears)" class="crud-form">
                        <div class="empty-state crud-delete-alert">
                            <div class="admin-card-eyebrow crud-delete-eyebrow">Achtung</div>
                            <div class="admin-card-title crud-delete-title">Schuljahr löschen</div>
                            <div class="kpi-sub mt-2" v-if="selected_schoolyears.length == 1">
                                Es soll ein Schuljahr gelöscht werden. Sind Sie sicher, dass Sie das markierte Schuljahr löschen möchten?
                            </div>
                            <div class="kpi-sub mt-2" v-if="selected_schoolyears.length > 1">
                                Es sollen {{ selected_schoolyears.length }} Schuljahre gelöscht werden. Sind Sie sicher, dass Sie die markierten Schuljahre löschen möchten?
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
<style scoped src="../../../../../css/admin-crud-panel.css"></style>
