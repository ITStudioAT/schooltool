<template>
    <!-- GRADE ADD/EDIT DIALOG -->
    <v-dialog v-model="grade_dialog" persistent max-width="400">
        <v-card>
            <v-card-title class="text-h6">{{ grade_dialog_mode === 'edit' ? 'Note bearbeiten' : 'Note hinzufügen' }}</v-card-title>
            <v-card-text>
                <v-text-field v-model="grade_dialog_data.grade" label="Note *" autofocus class="mt-1" />
                <v-text-field v-model="grade_dialog_data.name" label="Bezeichnung" />
                <v-text-field v-model="grade_dialog_data.value" label="Wert" hide-details />
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn color="secondary" variant="text" @click="grade_dialog = false">Abbrechen</v-btn>
                <v-btn color="primary" variant="flat" :disabled="!grade_dialog_data.grade" @click="saveGradeDialog">Speichern</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <!-- GRADE DELETE DIALOG -->
    <v-dialog v-model="grade_delete_dialog" persistent max-width="400">
        <v-card>
            <v-card-title class="text-h6">Note löschen</v-card-title>
            <v-card-text>
                Soll die Note <strong>{{ grade_delete_item?.grade }}</strong> wirklich gelöscht werden?
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn color="secondary" variant="text" @click="grade_delete_dialog = false">Abbrechen</v-btn>
                <v-btn color="error" variant="flat" prepend-icon="mdi-delete" @click="confirmDeleteGrade">Löschen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <!-- DELETE CONFIRMATION DIALOG -->
    <v-dialog v-model="delete_dialog" persistent max-width="400">
        <v-card>
            <v-card-title class="text-h6">Arbeit löschen</v-card-title>
            <v-card-text>
                Soll <strong>{{ delete_dialog_work?.short_name }} – {{ delete_dialog_work?.name }}</strong> wirklich gelöscht werden? Diese Aktion kann nicht rückgängig gemacht werden.
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn color="secondary" variant="text" @click="delete_dialog = false">Abbrechen</v-btn>
                <v-btn color="error" variant="flat" prepend-icon="mdi-delete" @click="confirmDelete">Löschen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <!-- WORKS AND GRADES OVERVIEW -->
    <ItsGridBox variant="overview" v-if="action !== 'teaching_work_new_or_edit'" color="primary" title="Arbeiten und Bewertungen" icon="mdi-test-tube" class="w-100" :disabled="action != ''">
        <template #header-actions>
            <v-btn icon="mdi-plus" size="small" color="primary" variant="tonal" :disabled="any_dialog_open" @click="newWork" />
        </template>

        <v-list density="compact" class="bg-transparent">
            <v-list-item v-for="(work, index) in teaching_works" :key="index" class="px-0">
                <div class="d-flex flex-row align-center justify-space-between">
                    <div class="text-body-1 font-weight-medium">{{ work.short_name }} – {{ work.name }}</div>
                    <div class="d-flex flex-row align-center ga-1">
                        <v-btn flat tile size="x-small" color="primary" icon="mdi-pencil" :disabled="any_dialog_open" @click="editWork(index)" />
                        <v-btn flat tile size="x-small" color="warning" icon="mdi-delete" :disabled="any_dialog_open" @click="startDelete(index)" />
                    </div>
                </div>
                <v-divider class="mt-2" />
            </v-list-item>
        </v-list>
    </ItsGridBox>

    <!-- EDIT/NEW WORK FORM -->
    <ItsGridBox variant="overview" color="primary" :title="edit_index !== null ? 'Arbeit ändern' : 'Neue Arbeit'" icon="mdi-test-tube" class="w-100 mt-4" v-if="action == 'teaching_work_new_or_edit'">
        <div class="d-flex flex-row align-center justify-end mt-2 ga-2">
            <v-btn icon="mdi-check" size="x-small" color="success" variant="flat" :disabled="!is_valid || any_dialog_open" @click="save" />
            <v-btn icon="mdi-close" size="x-small" color="warning" variant="flat" :disabled="any_dialog_open" @click="abortNewWork" />
        </div>
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text>
                <v-form ref="form" v-model="is_valid" @submit.prevent="save" class="mb-4">
                    <div class="text-caption text-text">Bitte geben Sie die Felder ein (* = Pflichtfeld)</div>
                    <div class="form-group-box mt-3">
                        <v-text-field autofocus v-model="data.short_name" label="Kurzzeichen (z. B. SA für Schularbeit)*" :rules="[required(), maxLength(10)]" />
                        <v-text-field v-model="data.name" label="Bezeichnung *" :rules="[required(), maxLength(255)]" hide-details />
                    </div>

                    <!-- Noten -->
                    <div class="form-group-box mt-4">
                        <div class="d-flex flex-row align-center justify-space-between mb-1">
                            <div class="text-caption text-text">Noten</div>
                            <v-btn icon="mdi-plus" size="x-small" color="primary" variant="tonal" :disabled="any_dialog_open" @click="openAddGradeDialog" />
                        </div>
                        <v-list density="compact" class="bg-transparent pa-0">
                            <v-list-item v-for="(grade, idx) in sortedGrades(data.grades)" :key="`${idx}-${grade.grade}`" class="px-0">
                                <div class="d-flex flex-row align-center justify-space-between">
                                    <div class="d-flex flex-row align-center ga-2">
                                        <v-btn
                                            :icon="data.default_grade === grade.grade ? 'mdi-star' : 'mdi-star-outline'"
                                            size="x-small"
                                            :color="data.default_grade === grade.grade ? 'amber' : 'grey'"
                                            variant="text"
                                            :title="data.default_grade === grade.grade ? 'Standardnote (klicken zum Entfernen)' : 'Als Standardnote setzen'"
                                            :disabled="any_dialog_open"
                                            @click="data.default_grade = data.default_grade === grade.grade ? '' : grade.grade" />
                                        <div class="text-body-2">
                                            {{ grade.grade }}<span v-if="grade.name" class="ml-1 text-medium-emphasis">({{ grade.name }})</span><span v-if="grade.value" class="ml-1 text-medium-emphasis">= {{ grade.value }}</span>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-row align-center ga-1">
                                        <v-btn flat tile size="x-small" color="primary" icon="mdi-pencil" :disabled="any_dialog_open" @click="openEditGradeDialog(idx)" />
                                        <v-btn flat tile size="x-small" color="warning" icon="mdi-delete" :disabled="any_dialog_open" @click="openDeleteGradeDialog(idx)" />
                                    </div>
                                </div>
                                <v-divider class="mt-1" />
                            </v-list-item>
                        </v-list>
                    </div>

                    <!-- Berechnungsmethode -->
                    <v-divider class="my-4" />
                    <div class="text-caption text-text">Berechnungsmethode</div>
                    <v-btn-toggle v-model="data.calculation" mandatory color="primary" class="mt-2">
                        <v-btn value="average" size="small">
                            <v-icon start>mdi-calculator</v-icon>
                            Durchschnitt
                        </v-btn>
                        <v-btn value="points" size="small">
                            <v-icon start>mdi-sigma</v-icon>
                            Punkte-Tabelle
                        </v-btn>
                    </v-btn-toggle>

                    <!-- Erklärung -->
                    <v-alert v-if="data.calculation === 'average'" color="teal" density="compact" variant="tonal" class="mt-3">
                        <strong>Durchschnitt:</strong> Der Mittelwert aller Noten-Werte ergibt die Note.
                        <br><span class="text-caption">z.B. Werte 3, 4, 5 → Durchschnitt 4.0 → Note 4</span>
                    </v-alert>
                    <v-alert v-if="data.calculation === 'points'" color="primary" density="compact" variant="tonal" class="mt-3">
                        <strong>Punkte-Tabelle:</strong> Die Punkte werden summiert und über eine Tabelle in eine Note umgewandelt.
                        <br><span class="text-caption">z.B. +1, +1, 0, -1 → Summe 1 → Note laut Tabelle</span>
                    </v-alert>

                    <!-- Punkte-Tabelle Konfiguration -->
                    <div v-if="data.calculation === 'points'" class="mt-4">
                        <div class="text-caption text-text mb-2">Punkte-Tabelle (von hoch nach niedrig)</div>

                        <!-- Bestehende Einträge -->
                        <div v-for="(pt, idx) in sortedPointsTable" :key="idx" class="d-flex align-center ga-2 mb-2">
                            <v-text-field
                                :model-value="pt.min_points"
                                @update:model-value="updatePointsTableEntry(idx, 'min_points', $event)"
                                label="Ab Punkte ≥"
                                density="compact"
                                hide-details
                                type="number"
                                style="max-width: 120px" />
                            <v-icon>mdi-arrow-right</v-icon>
                            <v-text-field
                                :model-value="pt.grade"
                                @update:model-value="updatePointsTableEntry(idx, 'grade', $event)"
                                label="Note"
                                density="compact"
                                hide-details
                                style="max-width: 80px" />
                            <v-btn icon="mdi-delete" size="x-small" color="error" variant="text" :disabled="any_dialog_open" @click="removePointsTableEntry(idx)" />
                        </div>

                        <!-- Neuer Eintrag -->
                        <div class="d-flex align-center ga-2">
                            <v-text-field
                                v-model="new_points_entry.min_points"
                                label="Ab Punkte ≥"
                                density="compact"
                                hide-details
                                type="number"
                                style="max-width: 120px" />
                            <v-icon>mdi-arrow-right</v-icon>
                            <v-text-field
                                v-model="new_points_entry.grade"
                                label="Note"
                                density="compact"
                                hide-details
                                style="max-width: 80px"
                                @keyup.enter="addPointsTableEntry" />
                            <v-btn icon="mdi-plus" size="small" color="primary" @click="addPointsTableEntry" :disabled="any_dialog_open || new_points_entry.min_points === '' || !new_points_entry.grade" />
                        </div>

                        <!-- Standard-Tabelle vorschlagen -->
                        <v-btn v-if="!data.points_table?.length" variant="outlined" size="small" color="primary" class="mt-3" :disabled="any_dialog_open" @click="useDefaultPointsTable">
                            <v-icon start>mdi-table-plus</v-icon>
                            Standard-Tabelle verwenden
                        </v-btn>
                    </div>

                </v-form>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox },

    props: {
        schemaId: {
            type: String,
            required: true,
        },
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.teachingStore = useTeachingStore()
        await this.teachingStore.loadSettings()
    },

    unmounted() {
        if (this.action === 'teaching_work_new_or_edit') {
            this.action = ''
        }
    },

    data() {
        return {
            adminStore: null,
            teachingStore: null,
            is_valid: false,
            data: {
                short_name: '',
                name: '',
                grades: [],
                calculation: 'average',
                points_table: [],
                default_grade: '',
            },
            new_points_entry: { min_points: '', grade: '' },
            edit_index: null,
            delete_dialog: false,
            delete_dialog_index: null,
            delete_dialog_work: null,
            grade_dialog: false,
            grade_dialog_mode: 'add',
            grade_dialog_data: { grade: '', name: '', value: '' },
            grade_dialog_original_grade: null,
            grade_delete_dialog: false,
            grade_delete_item: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useTeachingStore, ['settings']),
        teaching_works() {
            const schema = (this.settings?.teaching_schemas || []).find((s) => s.id === this.schemaId)
            const works = schema?.works || []
            return [...works].sort((a, b) => (a.short_name || '').localeCompare(b.short_name || '', 'de'))
        },
        any_dialog_open() {
            return this.delete_dialog || this.grade_dialog || this.grade_delete_dialog
        },
        sortedPointsTable() {
            if (!this.data.points_table?.length) return []
            return [...this.data.points_table].sort((a, b) => {
                const valA = parseFloat(a.min_points) || 0
                const valB = parseFloat(b.min_points) || 0
                return valB - valA // Sort descending (highest first)
            })
        },
    },

    watch: {
        'data.short_name'(val) {
            if (val && val !== val.toUpperCase()) {
                this.data.short_name = val.toUpperCase()
            }
        },
    },

    methods: {
        sortedGrades(grades) {
            if (!grades?.length) return []
            return [...grades].sort((a, b) => {
                const valA = parseFloat(String(a.value || '').replace(',', '.')) || 0
                const valB = parseFloat(String(b.value || '').replace(',', '.')) || 0
                if (valA === 0 && valB === 0) return 0
                if (valA === 0) return 1
                if (valB === 0) return -1
                return valA - valB
            })
        },

        newWork() {
            this.data = { short_name: '', name: '', grades: [], calculation: 'average', points_table: [], default_grade: '' }
            this.new_points_entry = { min_points: '', grade: '' }
            this.edit_index = null
            this.action = 'teaching_work_new_or_edit'
        },

        editWork(index) {
            const work = this.teaching_works[index]
            this.data = {
                ...work,
                grades: (work.grades || []).map((g) => ({ ...g })),
                calculation: work.calculation || 'average',
                points_table: (work.points_table || []).map((pt) => ({ ...pt })),
                default_grade: (work.default_grade || '').toString(),
            }
            this.ensureValidDefaultGrade(this.data)
            this.new_points_entry = { min_points: '', grade: '' }
            this.edit_index = index
            this.action = 'teaching_work_new_or_edit'
        },

        openAddGradeDialog() {
            this.grade_dialog_mode = 'add'
            this.grade_dialog_data = { grade: '', name: '', value: '' }
            this.grade_dialog_original_grade = null
            this.grade_dialog = true
        },

        openEditGradeDialog(sortedIdx) {
            const grade = this.sortedGrades(this.data.grades)[sortedIdx]
            this.grade_dialog_mode = 'edit'
            this.grade_dialog_data = { ...grade }
            this.grade_dialog_original_grade = grade.grade
            this.grade_dialog = true
        },

        saveGradeDialog() {
            if (!this.grade_dialog_data.grade) return
            const newGrades = [...(this.data.grades || [])]
            const entry = {
                grade: this.grade_dialog_data.grade.trim(),
                name: this.grade_dialog_data.name?.trim() || '',
                value: this.grade_dialog_data.value?.trim() || '',
            }
            if (this.grade_dialog_mode === 'edit') {
                const idx = newGrades.findIndex((g) => g.grade === this.grade_dialog_original_grade)
                if (idx >= 0) {
                    newGrades[idx] = entry
                }
            } else {
                newGrades.push(entry)
            }
            this.data.grades = newGrades
            this.ensureValidDefaultGrade(this.data)
            this.grade_dialog = false
        },

        openDeleteGradeDialog(sortedIdx) {
            this.grade_delete_item = this.sortedGrades(this.data.grades)[sortedIdx]
            this.grade_delete_dialog = true
        },

        confirmDeleteGrade() {
            const idx = (this.data.grades || []).findIndex((g) => g.grade === this.grade_delete_item?.grade)
            if (idx >= 0) {
                const newGrades = [...this.data.grades]
                newGrades.splice(idx, 1)
                this.data.grades = newGrades
                this.ensureValidDefaultGrade(this.data)
            }
            this.grade_delete_dialog = false
            this.grade_delete_item = null
        },

        addPointsTableEntry() {
            if (this.new_points_entry.min_points === '' || !this.new_points_entry.grade) return
            if (!this.data.points_table) this.data.points_table = []
            this.data.points_table.push({
                min_points: parseFloat(this.new_points_entry.min_points) || 0,
                grade: this.new_points_entry.grade.trim(),
            })
            this.new_points_entry = { min_points: '', grade: '' }
        },

        removePointsTableEntry(index) {
            // Find the actual index in the unsorted array
            const sortedEntry = this.sortedPointsTable[index]
            const actualIndex = this.data.points_table.findIndex(
                (pt) => pt.min_points === sortedEntry.min_points && pt.grade === sortedEntry.grade
            )
            if (actualIndex >= 0) {
                const newTable = [...this.data.points_table]
                newTable.splice(actualIndex, 1)
                this.data = { ...this.data, points_table: newTable }
            }
        },

        updatePointsTableEntry(sortedIndex, field, value) {
            const sortedEntry = this.sortedPointsTable[sortedIndex]
            const actualIndex = this.data.points_table.findIndex(
                (pt) => pt.min_points === sortedEntry.min_points && pt.grade === sortedEntry.grade
            )
            if (actualIndex >= 0) {
                const newTable = [...this.data.points_table]
                newTable[actualIndex] = {
                    ...newTable[actualIndex],
                    [field]: field === 'min_points' ? (parseFloat(value) || 0) : value,
                }
                this.data = { ...this.data, points_table: newTable }
            }
        },

        useDefaultPointsTable() {
            this.data.points_table = [
                { min_points: 5, grade: '1' },
                { min_points: 3, grade: '2' },
                { min_points: 1, grade: '3' },
                { min_points: 0, grade: '4' },
                { min_points: -999, grade: '5' },
            ]
        },

        abortNewWork() {
            this.action = ''
            this.edit_index = null
        },

        startDelete(index) {
            this.delete_dialog_index = index
            this.delete_dialog_work = this.teaching_works[index]
            this.delete_dialog = true
        },

        async confirmDelete() {
            await this.deleteWork(this.delete_dialog_index)
            this.delete_dialog = false
            this.delete_dialog_index = null
            this.delete_dialog_work = null
        },

        async save() {
            const schemas = [...(this.settings?.teaching_schemas || [])]
            const schemaIndex = schemas.findIndex((s) => s.id === this.schemaId)
            if (schemaIndex === -1) return

            const works = [...this.teaching_works]
            const workData = { ...this.data, grades: [...(this.data.grades || [])] }
            const sortedGrades = this.sortedGrades(workData.grades || [])
            workData.grades = sortedGrades
            this.ensureValidDefaultGrade(workData)

            if (this.edit_index !== null) {
                works[this.edit_index] = workData
            } else {
                works.push(workData)
            }

            schemas[schemaIndex] = { ...schemas[schemaIndex], works }
            await this.teachingStore.saveSettings({ teaching_schemas: schemas })
            this.action = ''
            this.edit_index = null
        },

        async deleteWork(index) {
            const schemas = [...(this.settings?.teaching_schemas || [])]
            const schemaIndex = schemas.findIndex((s) => s.id === this.schemaId)
            if (schemaIndex === -1) return

            const works = [...this.teaching_works]
            works.splice(index, 1)

            schemas[schemaIndex] = { ...schemas[schemaIndex], works }
            await this.teachingStore.saveSettings({ teaching_schemas: schemas })
            this.delete_index = null
        },
        normalizeGradeKey(gradeKey) {
            return String(gradeKey || '').trim().toUpperCase()
        },
        ensureValidDefaultGrade(targetWork = this.data) {
            if (!targetWork) return
            const selected = this.normalizeGradeKey(targetWork.default_grade)
            if (!selected) {
                targetWork.default_grade = ''
                return
            }

            const grades = Array.isArray(targetWork.grades) ? targetWork.grades : []
            const match = grades.find((grade) => this.normalizeGradeKey(grade?.grade) === selected)
            targetWork.default_grade = match?.grade || ''
        },
    },
}
</script>

<style scoped>
.form-group-box {
    border: 1px solid rgba(99, 102, 241, 0.25);
    border-radius: 10px;
    background: rgba(99, 102, 241, 0.05);
    padding: 12px 14px 4px;
}
</style>
