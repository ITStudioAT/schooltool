<template>
    <!-- WORKS AND GRADES OVERVIEW -->
    <ItsGridBox color="primary" title="Arbeiten und Bewertungen" icon="mdi-test-tube" class="w-100" :disabled="action != ''">
        <!-- HEADER ACTIONS -->
        <div class="d-flex flex-row align-center justify-end mt-2 ga-2">
            <v-btn v-if="!is_editing" flat tile size="small" color="primary" prepend-icon="mdi-pencil" @click="is_editing = true">Bearbeiten</v-btn>
            <v-btn v-if="is_editing" flat tile size="small" color="success" prepend-icon="mdi-check" @click="exitEditMode">Fertig</v-btn>
        </div>

        <!-- NEUE ARBEIT ANLEGEN -->
        <v-card v-if="is_editing" tile flat color="transparent" class="mt-4">
            <ItsMenuButton title="Arbeit" subtitle="anlegen" icon="mdi-plus-circle-multiple" color="primary" @click="newWork" />
        </v-card>

        <!-- ALLE ARBEITEN MIT NOTEN ANZEIGEN -->
        <v-list density="compact" class="bg-transparent">
            <v-list-item v-for="(work, index) in teaching_works" :key="index" class="px-0">
                <div class="d-flex flex-column w-100">
                    <div class="d-flex flex-row align-center justify-space-between">
                        <div class="text-body-1 font-weight-medium">{{ work.short_name }} - {{ work.name }}</div>
                        <div v-if="is_editing" class="d-flex flex-row align-center ga-1">
                            <v-btn flat tile size="x-small" color="warning" icon="mdi-delete" @click="startDelete(index)" v-if="delete_index !== index" />
                            <v-btn flat tile size="x-small" color="success" icon="mdi-delete-off" @click="delete_index = null" v-if="delete_index === index" />
                            <v-btn flat tile size="x-small" color="error" icon="mdi-delete" @click="deleteWork(index)" v-if="delete_index === index" />
                            <v-btn flat tile size="x-small" color="primary" icon="mdi-pencil" @click="editWork(index)" v-if="delete_index !== index" />
                        </div>
                    </div>
                    <!-- Calculation method display -->
                    <div class="d-flex align-center ga-2 mt-1">
                        <v-chip size="x-small" :color="work.calculation === 'points' ? 'primary' : 'teal'" variant="flat">
                            <v-icon start size="x-small">{{ work.calculation === 'points' ? 'mdi-sigma' : 'mdi-calculator' }}</v-icon>
                            {{ work.calculation === 'points' ? 'Punkte-Tabelle' : 'Durchschnitt' }}
                        </v-chip>
                    </div>
                    <div v-if="work.grades?.length" class="mt-1">
                        <v-chip v-for="(grade, idx) in sortedGrades(work.grades)" :key="idx" size="x-small" variant="outlined" class="mr-1 mb-1">
                            {{ grade.grade }}<span v-if="grade.name" class="ml-1">({{ grade.name }})</span><span v-if="grade.value" class="ml-1">= {{ grade.value }}</span>
                        </v-chip>
                    </div>
                    <!-- Points table preview -->
                    <div v-if="work.calculation === 'points' && work.points_table?.length" class="mt-1">
                        <span class="text-caption text-medium-emphasis">Punkte-Tabelle: </span>
                        <v-chip v-for="(pt, idx) in work.points_table" :key="idx" size="x-small" variant="outlined" color="primary" class="mr-1 mb-1">
                            ≥{{ pt.min_points }} → {{ pt.grade }}
                        </v-chip>
                    </div>
                </div>
                <v-divider class="mt-2" />
            </v-list-item>
        </v-list>
    </ItsGridBox>

    <!-- EDIT/NEW WORK FORM -->
    <ItsGridBox color="primary" :title="edit_index !== null ? 'Arbeit ändern' : 'Neue Arbeit'" icon="mdi-test-tube" class="w-100 mt-4" v-if="action == 'teaching_work_new_or_edit'">
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text>
                <v-form ref="form" v-model="is_valid" @submit.prevent="save" class="mb-4">
                    <div class="text-caption text-text">Bitte geben Sie die Felder ein (* = Pflichtfeld)</div>
                    <v-text-field autofocus v-model="data.short_name" label="Kurzzeichen (z. B. SA für Schularbeit)*" :rules="[required(), maxLength(10)]" />
                    <v-text-field v-model="data.name" label="Bezeichnung *" :rules="[required(), maxLength(255)]" />

                    <!-- Noten -->
                    <div class="text-caption text-text mt-4">Noten</div>
                    <div class="d-flex flex-row align-center ga-2">
                        <v-text-field v-model="new_grade.grade" label="Note *" density="compact" hide-details style="max-width: 80px" />
                        <v-text-field v-model="new_grade.name" label="Bezeichnung" density="compact" hide-details />
                        <v-text-field v-model="new_grade.value" label="Wert" density="compact" hide-details style="max-width: 80px" @keyup.enter="addGrade" />
                        <v-btn color="primary" icon="mdi-plus" size="small" @click="addGrade" :disabled="!new_grade.grade" />
                    </div>
                    <v-chip-group class="mt-2" column>
                        <v-chip v-for="(grade, idx) in data.grades" :key="`${idx}-${grade.grade}-${grade.value}`" closable @click:close="removeGrade(idx)">
                            {{ grade.grade }}<span v-if="grade.name" class="ml-1 text-medium-emphasis">({{ grade.name }})</span><span v-if="grade.value" class="ml-1 text-medium-emphasis">= {{ grade.value }}</span>
                        </v-chip>
                    </v-chip-group>

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
                            <v-btn icon="mdi-delete" size="x-small" color="error" variant="text" @click="removePointsTableEntry(idx)" />
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
                            <v-btn icon="mdi-plus" size="small" color="primary" @click="addPointsTableEntry" :disabled="new_points_entry.min_points === '' || !new_points_entry.grade" />
                        </div>

                        <!-- Standard-Tabelle vorschlagen -->
                        <v-btn v-if="!data.points_table?.length" variant="outlined" size="small" color="primary" class="mt-3" @click="useDefaultPointsTable">
                            <v-icon start>mdi-table-plus</v-icon>
                            Standard-Tabelle verwenden
                        </v-btn>
                    </div>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="warning" flat tile @click="abortNewWork">Abbruch</v-btn>
                        <v-btn color="success" flat tile type="submit" :disabled="!is_valid">Speichern</v-btn>
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
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox, ItsMenuButton },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.teachingStore = useTeachingStore()
        await this.teachingStore.loadSettings()
    },

    data() {
        return {
            adminStore: null,
            teachingStore: null,
            is_valid: false,
            is_editing: false,
            data: {
                short_name: '',
                name: '',
                grades: [],
                calculation: 'average',
                points_table: [],
            },
            new_grade: { grade: '', name: '', value: '' },
            new_points_entry: { min_points: '', grade: '' },
            edit_index: null,
            delete_index: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useTeachingStore, ['settings']),
        teaching_works() {
            const works = this.settings?.teaching_works || []
            return [...works].sort((a, b) => (a.short_name || '').localeCompare(b.short_name || '', 'de'))
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
            this.data = { short_name: '', name: '', grades: [], calculation: 'average', points_table: [] }
            this.new_grade = { grade: '', name: '', value: '' }
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
            }
            this.new_grade = { grade: '', name: '', value: '' }
            this.new_points_entry = { min_points: '', grade: '' }
            this.edit_index = index
            this.action = 'teaching_work_new_or_edit'
        },

        addGrade() {
            if (!this.new_grade.grade) return
            if (!this.data.grades) this.data.grades = []
            this.data.grades.push({
                grade: this.new_grade.grade.trim(),
                name: this.new_grade.name?.trim() || '',
                value: this.new_grade.value?.trim() || '',
            })
            this.new_grade = { grade: '', name: '', value: '' }
        },

        removeGrade(index) {
            const newGrades = [...this.data.grades]
            newGrades.splice(index, 1)
            this.data = { ...this.data, grades: newGrades }
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

        exitEditMode() {
            this.is_editing = false
            this.delete_index = null
        },

        startDelete(index) {
            this.delete_index = index
        },

        async save() {
            const works = [...this.teaching_works]

            // Sort grades by value before saving
            const sortedGrades = this.sortedGrades(this.data.grades || [])
            const workData = { ...this.data, grades: sortedGrades }

            if (this.edit_index !== null) {
                works[this.edit_index] = workData
            } else {
                works.push(workData)
            }

            await this.teachingStore.saveSettings({ teaching_works: works })
            this.action = ''
            this.edit_index = null
            this.is_editing = false
        },

        async deleteWork(index) {
            const works = [...this.teaching_works]
            works.splice(index, 1)

            await this.teachingStore.saveSettings({ teaching_works: works })
            this.delete_index = null
        },
    },
}
</script>
