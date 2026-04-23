<template>
    <!-- GRADE ADD/EDIT DIALOG -->
    <v-dialog v-model="grade_dialog" persistent max-width="400">
        <v-card>
            <v-card-title class="text-h6">{{ grade_dialog_mode === 'edit' ? 'Note bearbeiten' : 'Note hinzufügen' }}</v-card-title>
            <v-card-text>
                <v-text-field v-model="grade_dialog_data.grade" label="Note *" autofocus class="mt-1" />
                <v-text-field v-model="grade_dialog_data.name" label="Bezeichnung" />
                <v-text-field
                    :model-value="grade_dialog_data.value"
                    label="Wert"
                    hide-details="auto"
                    :error-messages="gradeDialogValueError"
                    @update:model-value="updateGradeDialogValue" />
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn color="secondary" variant="text" @click="grade_dialog = false">Abbrechen</v-btn>
                <v-btn color="primary" variant="flat" :disabled="!grade_dialog_data.grade || !!gradeDialogValueError" @click="saveGradeDialog">Speichern</v-btn>
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
                        <v-text-field autofocus v-model="data.short_name" label="Kurzzeichen (z. B. SA für Schularbeit)*" :rules="[required(), maxLength(10), shortNameUniqueRule]" />
                        <v-text-field v-model="data.name" label="Bezeichnung *" :rules="[required(), maxLength(255)]" hide-details />
                    </div>

                    <!-- Noten -->
                    <div class="form-group-box mt-4">
                        <div class="d-flex flex-row align-center justify-space-between mb-1">
                            <div class="text-caption text-text">Noten</div>
                            <div class="d-flex flex-row align-center ga-1">
                                <v-btn size="x-small" color="secondary" variant="tonal" :disabled="any_dialog_open" @click="useDefaultGrades">Standardnoten</v-btn>
                                <v-btn icon="mdi-plus" size="x-small" color="primary" variant="tonal" :disabled="any_dialog_open" @click="openAddGradeDialog" />
                            </div>
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

                    <!-- Berechnung der Semesternote -->
                    <div class="form-group-box mt-4">
                        <div class="d-flex align-center ga-1">
                            <v-checkbox v-model="data.calculation_enabled" hide-details density="compact" class="flex-grow-0" />
                            <div class="text-caption text-text">Berechnung der Semesternote (optional)</div>
                        </div>
                        <v-btn-toggle v-if="data.calculation_enabled" v-model="data.calculation" mandatory color="primary" class="mt-2">
                            <v-btn value="average" size="small">
                                <v-icon start>mdi-calculator</v-icon>
                                Durchschnitt
                            </v-btn>
                            <v-btn value="points" size="small">
                                <v-icon start>mdi-sigma</v-icon>
                                Punkte
                            </v-btn>
                        </v-btn-toggle>

                        <!-- Erklärung -->
                        <v-alert v-if="data.calculation_enabled && data.calculation === 'average'" color="teal" density="compact" variant="tonal" class="mt-3">
                            <strong>Durchschnitt:</strong> Es wird der Mittelwert all dieser Leistungen ermittelt.
                        </v-alert>
                        <v-alert v-if="data.calculation_enabled && data.calculation === 'points'" color="primary" density="compact" variant="tonal" class="mt-3">
                            <strong>Punkte:</strong> Die Punkte werden über das Semester summiert und ergeben eine Note.
                        </v-alert>

                        <div v-if="data.calculation_enabled && data.calculation === 'points'" class="mt-3 semester-points-box">
                            <div class="d-flex flex-wrap align-start justify-space-between ga-2 mb-3">
                                <div>
                                    <div class="text-caption text-text">Punkte-Notenschlüssel für die Semestersumme</div>
                                    <div class="text-body-2 text-medium-emphasis">
                                        Diese Tabelle gilt für die Summe der Bewertungswerte dieses Arbeitstyps im Semester.
                                    </div>
                                </div>
                                <v-btn variant="outlined" size="small" color="primary" :disabled="any_dialog_open" @click="useDefaultSemesterPointsTable">
                                    <v-icon start>mdi-table-plus</v-icon>
                                    Standardwerte
                                </v-btn>
                            </div>

                            <div class="points-note-list">
                                <div v-for="(grade, index) in semesterPointsGrades" :key="grade" class="points-note-row">
                                    <div class="points-note-label">Note {{ grade }}</div>
                                    <v-text-field
                                        :model-value="semesterPointsThresholdValue(grade)"
                                        :label="index === semesterPointsGrades.length - 1 ? 'Ab Punkte (optional)' : 'Ab Punkte'"
                                        density="compact"
                                        hide-details
                                        type="text"
                                        inputmode="decimal"
                                        style="max-width: 180px"
                                        @update:model-value="updateSemesterPointsThreshold(grade, index, $event)" />
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Punkte-Note -->
                    <div class="form-group-box mt-4 points-note-box">
                        <div class="d-flex align-center ga-1">
                            <v-checkbox v-model="data.points_note_enabled" hide-details density="compact" class="flex-grow-0" />
                            <div class="text-caption text-text">Punkte-Note-Tabelle pro Arbeit (optional)</div>
                        </div>

                        <div v-if="data.points_note_enabled" class="mt-3">
                            <div class="d-flex flex-wrap align-start justify-space-between ga-2 mb-3">
                                <div class="text-body-2 text-medium-emphasis">
                                    Für jede Note gibt es ein Feld für "ab Punkte". Bei der letzten Note kann dieses Feld leer bleiben.
                                </div>
                                <v-btn variant="outlined" size="small" color="primary" :disabled="any_dialog_open" @click="useDefaultPointsTable">
                                    <v-icon start>mdi-table-plus</v-icon>
                                    Standardwerte
                                </v-btn>
                            </div>

                            <div class="points-note-list">
                                <div v-for="(grade, index) in pointsNoteGrades" :key="grade" class="points-note-row">
                                    <div class="points-note-label">Note {{ grade }}</div>
                                    <v-text-field
                                        :model-value="pointsThresholdValue(grade)"
                                        :label="index === pointsNoteGrades.length - 1 ? 'Ab Punkte (optional)' : 'Ab Punkte'"
                                        density="compact"
                                        hide-details
                                        type="text"
                                        inputmode="decimal"
                                        style="max-width: 180px"
                                        @update:model-value="updatePointsThreshold(grade, index, $event)" />
                                </div>
                            </div>
                        </div>
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
                calculation_enabled: false,
                points_note_enabled: false,
                points_table: [],
                points_sonst_grade: '',
                semester_points_table: [],
                semester_points_sonst_grade: '',
                default_grade: '',
            },
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
            is_saving_settings: false,
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
            return this.delete_dialog || this.grade_dialog || this.grade_delete_dialog || this.is_saving_settings
        },
        pointsNoteGrades() {
            return this.pointsNoteGradesFor(this.data.grades || [])
        },
        semesterPointsGrades() {
            return ['1', '2', '3', '4', '5']
        },
        shortNameUniqueRule() {
            const existing = this.teaching_works
                .filter((_, i) => i !== this.edit_index)
                .map((w) => (w.short_name || '').toUpperCase())
            return (v) => !existing.includes((v || '').toUpperCase()) || 'Kurzzeichen bereits vorhanden'
        },
        gradeDialogValueError() {
            return this.isNumericGradeValue(this.grade_dialog_data.value) ? '' : 'Wert muss numerisch sein, z. B. 1,2'
        },
    },

    watch: {
        'data.short_name'(val) {
            if (val && val !== val.toUpperCase()) {
                this.data.short_name = val.toUpperCase()
            }
        },
        'grade_dialog_data.value'(value) {
            const normalizedValue = this.normalizeNumericInput(value)
            if (value !== normalizedValue) {
                this.grade_dialog_data.value = normalizedValue
            }
        },
    },

    methods: {
        normalizeNumericInput(value) {
            const sanitized = String(value ?? '')
                .replace('.', ',')
                .replace(/\s+/g, '')

            let normalized = ''
            let hasDecimalSeparator = false

            for (let index = 0; index < sanitized.length; index += 1) {
                const char = sanitized[index]

                if (char >= '0' && char <= '9') {
                    normalized += char
                    continue
                }

                if (char === '-' && normalized === '') {
                    normalized += char
                    continue
                }

                if (char === ',' && !hasDecimalSeparator) {
                    normalized += char
                    hasDecimalSeparator = true
                }
            }

            return normalized
        },

        isNumericGradeValue(value) {
            if (value == null || value === '') {
                return true
            }

            return /^-?\d+(,\d+)?$/.test(String(value))
        },

        normalizeGradeValue(value) {
            const normalized = this.normalizeNumericInput(value)

            if (!this.isNumericGradeValue(normalized)) {
                return null
            }

            return normalized.replace(',', '.')
        },

        updateGradeDialogValue(value) {
            this.grade_dialog_data.value = this.normalizeNumericInput(value)
        },

        displayGradeValue(value) {
            return this.normalizeNumericInput(value)
        },

        pointsNoteGradesFor(grades) {
            const seen = new Set()

            return this.sortedGrades(grades || [])
                .filter((grade) => {
                    const normalizedValue = this.normalizeGradeValue(grade?.value ?? '')
                    return normalizedValue !== null && normalizedValue !== ''
                })
                .map((grade) => String(grade?.grade || '').trim())
                .filter((grade) => {
                    const normalizedGrade = this.normalizeGradeKey(grade)
                    if (!normalizedGrade || seen.has(normalizedGrade)) {
                        return false
                    }

                    seen.add(normalizedGrade)
                    return true
                })
        },

        syncPointsNoteConfiguration(targetWork = this.data) {
            if (!targetWork) return targetWork

            const availableGrades = this.pointsNoteGradesFor(targetWork.grades || [])
            const allowedGrades = new Set(availableGrades.map((grade) => this.normalizeGradeKey(grade)))
            const fallbackGrade = availableGrades[availableGrades.length - 1] || ''
            const hasPointsConfiguration = !!(targetWork.points_note_enabled || (targetWork.points_table || []).length || targetWork.points_sonst_grade)

            targetWork.points_table = (targetWork.points_table || []).filter((entry) => {
                const normalizedGrade = this.normalizeGradeKey(entry?.grade)
                return normalizedGrade && allowedGrades.has(normalizedGrade)
            })

            if (!hasPointsConfiguration) {
                targetWork.points_sonst_grade = ''
                return targetWork
            }

            const currentFallback = this.normalizeGradeKey(targetWork.points_sonst_grade)
            targetWork.points_sonst_grade = currentFallback && allowedGrades.has(currentFallback)
                ? availableGrades.find((grade) => this.normalizeGradeKey(grade) === currentFallback) || ''
                : fallbackGrade

            targetWork.points_table = targetWork.points_table.filter((entry) => {
                return this.normalizeGradeKey(entry?.grade) !== this.normalizeGradeKey(targetWork.points_sonst_grade)
            })

            return targetWork
        },

        normalizedGradesForSave(grades) {
            const normalizedGrades = []

            for (const grade of grades || []) {
                const normalizedValue = this.normalizeGradeValue(grade?.value ?? '')

                if (normalizedValue === null) {
                    return null
                }

                normalizedGrades.push({
                    ...grade,
                    value: normalizedValue,
                })
            }

            return normalizedGrades
        },

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
            this.data = { short_name: '', name: '', grades: [], calculation: 'average', calculation_enabled: false, points_note_enabled: false, points_table: [], points_sonst_grade: '', semester_points_table: [], semester_points_sonst_grade: '', default_grade: '' }
            this.edit_index = null
            this.action = 'teaching_work_new_or_edit'
        },

        editWork(index) {
            const work = this.teaching_works[index]
            this.data = this.normalizePointsConfiguration({
                ...work,
                grades: (work.grades || []).map((g) => ({
                    ...g,
                    value: this.displayGradeValue(g?.value ?? ''),
                })),
                calculation: work.calculation || 'average',
                calculation_enabled: !!(work.calculation),
                points_note_enabled: Boolean(work.points_note_enabled),
                points_table: (work.points_table || []).map((pt) => ({ ...pt })),
                points_sonst_grade: work.points_sonst_grade || '',
                semester_points_table: (work.semester_points_table || []).map((pt) => ({ ...pt })),
                semester_points_sonst_grade: work.semester_points_sonst_grade || '',
                default_grade: (work.default_grade || '').toString(),
            })
            this.syncPointsNoteConfiguration(this.data)
            this.ensureValidDefaultGrade(this.data)
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
            this.grade_dialog_data = {
                ...grade,
                value: this.displayGradeValue(grade?.value ?? ''),
            }
            this.grade_dialog_original_grade = grade.grade
            this.grade_dialog = true
        },

        saveGradeDialog() {
            if (!this.grade_dialog_data.grade) return
            const normalizedValue = this.normalizeGradeValue(this.grade_dialog_data.value)
            if (normalizedValue === null) return
            const newGrades = [...(this.data.grades || [])]
            const entry = {
                grade: this.grade_dialog_data.grade.trim(),
                name: this.grade_dialog_data.name?.trim() || '',
                value: this.displayGradeValue(normalizedValue),
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
            this.syncPointsNoteConfiguration(this.data)
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
                this.syncPointsNoteConfiguration(this.data)
                this.ensureValidDefaultGrade(this.data)
            }
            this.grade_delete_dialog = false
            this.grade_delete_item = null
        },

        normalizePointsConfiguration(work) {
            if (!work) return work

            const pointsNoteGrades = this.pointsNoteGradesFor(work.grades || [])
            const fallbackGrade = pointsNoteGrades[pointsNoteGrades.length - 1] || ''
            const pointsTable = Array.isArray(work.points_table) ? work.points_table.map((entry) => ({ ...entry })) : []
            let pointsSonstGrade = work.points_sonst_grade || ''
            const semesterPointsTable = Array.isArray(work.semester_points_table) && work.semester_points_table.length
                ? work.semester_points_table.map((entry) => ({ ...entry }))
                : pointsTable.map((entry) => ({ ...entry }))
            let semesterPointsSonstGrade = work.semester_points_sonst_grade || ''

            if (!pointsSonstGrade) {
                const fallbackIndex = pointsTable.findIndex((entry) => {
                    return this.normalizeGradeKey(entry?.grade) === this.normalizeGradeKey(fallbackGrade) && Number(entry?.min_points) <= -999
                })

                if (fallbackIndex >= 0) {
                    pointsSonstGrade = pointsTable[fallbackIndex].grade
                    pointsTable.splice(fallbackIndex, 1)
                }
            }

            if (!semesterPointsSonstGrade) {
                const fallbackIndex = semesterPointsTable.findIndex((entry) => Number(entry?.min_points) <= -999)

                if (fallbackIndex >= 0) {
                    semesterPointsSonstGrade = semesterPointsTable[fallbackIndex].grade
                    semesterPointsTable.splice(fallbackIndex, 1)
                }
            }

            return {
                ...work,
                points_table: pointsTable,
                points_sonst_grade: pointsSonstGrade,
                semester_points_table: semesterPointsTable,
                semester_points_sonst_grade: semesterPointsSonstGrade,
            }
        },

        pointsThresholdValue(grade) {
            const pointsEntry = (this.data.points_table || []).find((entry) => this.normalizeGradeKey(entry?.grade) === this.normalizeGradeKey(grade))
            if (!pointsEntry) return ''
            return pointsEntry.min_points
        },

        updatePointsThreshold(grade, index, value) {
            const normalizedGrade = String(grade || '').trim()
            const stringValue = String(value ?? '').trim()
            const pointsTable = [...(this.data.points_table || [])]
            const entryIndex = pointsTable.findIndex((entry) => this.normalizeGradeKey(entry?.grade) === this.normalizeGradeKey(normalizedGrade))

            if (stringValue === '') {
                if (entryIndex >= 0) {
                    pointsTable.splice(entryIndex, 1)
                }

                this.data = {
                    ...this.data,
                    points_table: pointsTable,
                    points_sonst_grade: this.isLastPointsNote(index) ? normalizedGrade : this.data.points_sonst_grade,
                }
                return
            }

            const numericValue = parseFloat(stringValue.replace(',', '.'))
            const nextEntry = {
                grade: normalizedGrade,
                min_points: Number.isNaN(numericValue) ? 0 : numericValue,
            }

            if (entryIndex >= 0) {
                pointsTable[entryIndex] = nextEntry
            } else {
                pointsTable.push(nextEntry)
            }

            this.data = {
                ...this.data,
                points_table: pointsTable,
                points_sonst_grade: this.normalizeGradeKey(this.data.points_sonst_grade) === this.normalizeGradeKey(normalizedGrade) ? '' : this.data.points_sonst_grade,
            }
        },

        isLastPointsNote(index) {
            return index === this.pointsNoteGrades.length - 1
        },

        normalizedPointsTableForSave() {
            const fallbackGrade = this.normalizeGradeKey(this.data.points_sonst_grade)

            return (this.data.points_table || [])
                .map((entry) => ({
                    grade: String(entry?.grade || '').trim(),
                    min_points: typeof entry?.min_points === 'number'
                        ? entry.min_points
                        : parseFloat(String(entry?.min_points ?? '').replace(',', '.')),
                }))
                .filter((entry) => entry.grade && !Number.isNaN(entry.min_points))
                .filter((entry) => this.normalizeGradeKey(entry.grade) !== fallbackGrade)
                .sort((a, b) => b.min_points - a.min_points)
        },

        semesterPointsThresholdValue(grade) {
            const pointsEntry = (this.data.semester_points_table || []).find((entry) => this.normalizeGradeKey(entry?.grade) === this.normalizeGradeKey(grade))
            if (!pointsEntry) return ''
            return pointsEntry.min_points
        },

        updateSemesterPointsThreshold(grade, index, value) {
            const normalizedGrade = String(grade || '').trim()
            const stringValue = String(value ?? '').trim()
            const pointsTable = [...(this.data.semester_points_table || [])]
            const entryIndex = pointsTable.findIndex((entry) => this.normalizeGradeKey(entry?.grade) === this.normalizeGradeKey(normalizedGrade))

            if (stringValue === '') {
                if (entryIndex >= 0) {
                    pointsTable.splice(entryIndex, 1)
                }

                this.data = {
                    ...this.data,
                    semester_points_table: pointsTable,
                    semester_points_sonst_grade: this.isLastSemesterPointsNote(index) ? normalizedGrade : this.data.semester_points_sonst_grade,
                }
                return
            }

            const numericValue = parseFloat(stringValue.replace(',', '.'))
            const nextEntry = {
                grade: normalizedGrade,
                min_points: Number.isNaN(numericValue) ? 0 : numericValue,
            }

            if (entryIndex >= 0) {
                pointsTable[entryIndex] = nextEntry
            } else {
                pointsTable.push(nextEntry)
            }

            this.data = {
                ...this.data,
                semester_points_table: pointsTable,
                semester_points_sonst_grade: this.normalizeGradeKey(this.data.semester_points_sonst_grade) === this.normalizeGradeKey(normalizedGrade) ? '' : this.data.semester_points_sonst_grade,
            }
        },

        isLastSemesterPointsNote(index) {
            return index === this.semesterPointsGrades.length - 1
        },

        normalizedSemesterPointsTableForSave() {
            const fallbackGrade = this.normalizeGradeKey(this.data.semester_points_sonst_grade)

            return (this.data.semester_points_table || [])
                .map((entry) => ({
                    grade: String(entry?.grade || '').trim(),
                    min_points: typeof entry?.min_points === 'number'
                        ? entry.min_points
                        : parseFloat(String(entry?.min_points ?? '').replace(',', '.')),
                }))
                .filter((entry) => entry.grade && !Number.isNaN(entry.min_points))
                .filter((entry) => this.normalizeGradeKey(entry.grade) !== fallbackGrade)
                .sort((a, b) => b.min_points - a.min_points)
        },

        useDefaultGrades() {
            this.data.grades = [
                { grade: '1', name: 'Sehr gut', value: '1' },
                { grade: '2', name: 'Gut', value: '2' },
                { grade: '3', name: 'Befriedigend', value: '3' },
                { grade: '4', name: 'Genügend', value: '4' },
                { grade: '5', name: 'Nicht genügend', value: '5' },
            ]
            this.syncPointsNoteConfiguration(this.data)
            this.ensureValidDefaultGrade(this.data)
        },

        useDefaultPointsTable() {
            const defaultMinPoints = [5, 3, 1, 0]
            const pointsNoteGrades = this.pointsNoteGrades

            this.data.points_note_enabled = true
            this.data.points_table = pointsNoteGrades.slice(0, -1).map((grade, index) => ({
                grade,
                min_points: defaultMinPoints[index] ?? (defaultMinPoints[defaultMinPoints.length - 1] - (index - defaultMinPoints.length + 1)),
            }))
            this.data.points_sonst_grade = pointsNoteGrades[pointsNoteGrades.length - 1] || ''
        },

        useDefaultSemesterPointsTable() {
            const defaultMinPoints = [5, 3, 1, 0]

            this.data.semester_points_table = this.semesterPointsGrades.slice(0, -1).map((grade, index) => ({
                grade,
                min_points: defaultMinPoints[index] ?? (defaultMinPoints[defaultMinPoints.length - 1] - (index - defaultMinPoints.length + 1)),
            }))
            this.data.semester_points_sonst_grade = this.semesterPointsGrades[this.semesterPointsGrades.length - 1] || ''
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
            if (this.is_saving_settings) return
            this.is_saving_settings = true
            await this.$nextTick()

            try {
            const schemas = [...(this.settings?.teaching_schemas || [])]
            const schemaIndex = schemas.findIndex((s) => s.id === this.schemaId)
            if (schemaIndex === -1) return
            const normalizedGrades = this.normalizedGradesForSave(this.data.grades || [])
            if (normalizedGrades === null) return
            const pointsNoteEnabled = !!this.data.points_note_enabled
            const semesterPointsEnabled = !!(this.data.calculation_enabled && this.data.calculation === 'points')

            const works = [...this.teaching_works]
            const { calculation_enabled, points_note_enabled, ...dataWithoutFlag } = this.data
            const workData = {
                ...dataWithoutFlag,
                grades: normalizedGrades,
                calculation: calculation_enabled ? (this.data.calculation || 'average') : null,
                points_note_enabled: !!this.data.points_note_enabled,
                points_table: pointsNoteEnabled ? this.normalizedPointsTableForSave() : [],
                points_sonst_grade: pointsNoteEnabled ? (this.data.points_sonst_grade || '') : '',
                semester_points_table: semesterPointsEnabled ? this.normalizedSemesterPointsTableForSave() : [],
                semester_points_sonst_grade: semesterPointsEnabled ? (this.data.semester_points_sonst_grade || '') : '',
            }
            if (pointsNoteEnabled) {
                this.syncPointsNoteConfiguration(workData)
            }
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
            } finally {
                this.is_saving_settings = false
            }
        },

        async deleteWork(index) {
            if (this.is_saving_settings) return
            this.is_saving_settings = true
            await this.$nextTick()

            try {
            const schemas = [...(this.settings?.teaching_schemas || [])]
            const schemaIndex = schemas.findIndex((s) => s.id === this.schemaId)
            if (schemaIndex === -1) return

            const works = [...this.teaching_works]
            works.splice(index, 1)

            schemas[schemaIndex] = { ...schemas[schemaIndex], works }
            await this.teachingStore.saveSettings({ teaching_schemas: schemas })
            this.delete_index = null
            } finally {
                this.is_saving_settings = false
            }
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

.points-note-box {
    padding-bottom: 14px;
}

.points-note-list {
    display: grid;
    gap: 10px;
}

.points-note-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.points-note-label {
    min-width: 80px;
    font-size: 0.95rem;
    font-weight: 600;
}
</style>
