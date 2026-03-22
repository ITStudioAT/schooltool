<template>
    <v-card variant="outlined" class="performances-card mt-2" data-testid="teaching-performances-card">
        <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
            <v-icon size="18">mdi-chart-line</v-icon>
            Leistungen
        </v-card-title>
        <v-divider />
        <v-card-text>
            <div class="d-flex align-center ga-2 mb-3">
                <v-btn-toggle v-model="sortMode" mandatory density="compact" color="primary">
                    <v-btn value="class_last_name" size="small">Klasse, Name</v-btn>
                    <v-btn value="last_name_first_name" size="small">Name</v-btn>
                </v-btn-toggle>
            </div>
            <v-alert v-if="!students.length" type="info" variant="tonal">
                Keine Schüler:innen im Kurs vorhanden.
            </v-alert>
            <div v-else class="performances-table-wrap">
                <table class="performances-table" data-testid="teaching-performances-table">
                    <thead>
                        <tr>
                            <th>Schüler:in</th>
                            <th v-for="column in typeColumns" :key="`performance-head-${column.key}`">
                                {{ column.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="`performance-row-${row.student_id}`" :class="{ 'performance-row--canceled': row.is_canceled }">
                            <td class="student-cell" :class="{ 'student-cell--canceled': row.is_canceled }">{{ row.student_label }}</td>
                            <td v-for="column in typeColumns" :key="`performance-cell-${row.student_id}-${column.key}`">
                                <div v-if="row.byType[column.type]?.length" class="performance-items">
                                    <div v-for="(item, index) in row.byType[column.type]" :key="`item-${row.student_id}-${column.type}-${index}`" class="performance-item">
                                        {{ item }}
                                    </div>
                                </div>
                                <div v-if="categoryEvaluationCategoriesForType(column.type).length" class="performance-category-evaluations">
                                    <div
                                        v-for="category in categoryEvaluationCategoriesForType(column.type)"
                                        :key="`category-evaluation-${row.student_id}-${column.type}-${category.name}`"
                                        class="performance-category-evaluation">
                                        <div class="performance-category-label">
                                            {{ category.name }}
                                        </div>
                                        <div class="performance-category-options">
                                            <v-chip
                                                v-for="valueItem in categoryEvaluationValueItems"
                                                :key="`category-evaluation-option-${row.student_id}-${category.name}-${valueItem.value}`"
                                                size="small"
                                                :color="categoryEvaluationValueColor(valueItem.value)"
                                                :variant="categoryEvaluationValue(row.student_id, category.name) === valueItem.value ? 'flat' : 'outlined'"
                                                class="performance-category-chip"
                                                :disabled="categoryEvaluationSaving(row.student_id, category.name)"
                                                @click="saveCategoryEvaluation(row.student_id, category.name, valueItem.value)">
                                                {{ valueItem.value }}
                                            </v-chip>
                                        </div>
                                    </div>
                                </div>
                                <span v-if="!row.byType[column.type]?.length && !categoryEvaluationCategoriesForType(column.type).length" class="text-medium-emphasis">–</span>
                            </td>
                        </tr>
                        <tr v-if="!filteredEntries.length">
                            <td :colspan="1 + typeColumns.length" class="text-center text-medium-emphasis">
                                Keine Leistungen im gewählten Zeitraum vorhanden. Schüler:innen werden trotzdem angezeigt.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </v-card-text>
    </v-card>
</template>

<script>
import { parseLocalDate } from '@/helpers/date'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'
import { useCourseStudentCategoryEvaluationStore } from '@/stores/admin/teaching/CourseStudentCategoryEvaluationStore'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import {
    normalizeTeachingCategoryEvaluationValueItems,
    teachingCategoryEvaluationColorForValue,
    teachingCategoryEvaluationValueLabels,
} from '@/helpers/teachingCategoryEvaluation'

export default {
    props: {
        selectedCourse: {
            type: Object,
            default: null,
        },
        activeSemester: {
            type: Number,
            default: 1,
        },
        semesterCount: {
            type: Number,
            default: 1,
        },
        sem2StartDate: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            entryStore: null,
            categoryEvaluationStore: null,
            courseWorkStore: null,
            teachingStore: null,
            sortMode: 'last_name_first_name',
            localCategoryEvaluationValues: {},
            savingCategoryEvaluationKeys: {},
        }
    },
    async beforeMount() {
        this.entryStore = useCourseStudentEntryStore()
        this.categoryEvaluationStore = useCourseStudentCategoryEvaluationStore()
        this.courseWorkStore = useCourseWorkStore()
        this.teachingStore = useTeachingStore()

        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
        }

        await this.loadDataForSelectedCourse()
    },
    computed: {
        students() {
            const list = Array.isArray(this.selectedCourse?.students_info) ? [...this.selectedCourse.students_info] : []
            return list
                .filter((student) => !!student?.id)
                .sort((a, b) => {
                    const canceledA = this.isStudentCanceled(a) ? 1 : 0
                    const canceledB = this.isStudentCanceled(b) ? 1 : 0
                    if (canceledA !== canceledB) return canceledA - canceledB
                    return this.compareStudentsBySort(a, b)
                })
        },
        studentsById() {
            return this.students.reduce((carry, student) => {
                carry[String(student.id)] = student
                return carry
            }, {})
        },
        worksByType() {
            const schemaId = this.selectedCourse?.teaching_schema_id
            const works = schemaId ? this.teachingStore?.worksForSchema(schemaId) || [] : []
            return works.reduce((carry, work) => {
                const shortName = String(work?.short_name || '').trim()
                if (!shortName) {
                    return carry
                }
                carry[shortName] = String(work?.name || '').trim()
                return carry
            }, {})
        },
        workColumnsFromSchema() {
            const schemaId = this.selectedCourse?.teaching_schema_id
            const works = schemaId ? this.teachingStore?.worksForSchema(schemaId) || [] : []
            const allWorkColumns = works
                .map((work) => {
                    const type = String(work?.short_name || '').trim()
                    if (!type) {
                        return null
                    }
                    const name = String(work?.name || '').trim()
                    return {
                        type,
                        label: name ? `${type} - ${name}` : type,
                    }
                })
                .filter((item) => !!item)

            if (!this.schemaCategoryTypes.length) {
                return allWorkColumns
            }

            const workColumnsByType = new Map(allWorkColumns.map((column) => [column.type, column]))
            const columnsFromCategories = this.schemaCategoryTypes.map((type) => {
                const existingColumn = workColumnsByType.get(type)
                if (existingColumn) {
                    return existingColumn
                }

                return {
                    type,
                    label: this.typeLabel(type),
                }
            })

            return columnsFromCategories.filter((item) => !!item)
        },
        gradingConfig() {
            const schemaId = this.selectedCourse?.teaching_schema_id
            return schemaId ? this.teachingStore?.gradingForSchema(schemaId) || {} : {}
        },
        enabledCategoryEvaluationCategories() {
            const categories = Array.isArray(this.gradingConfig?.categories) ? this.gradingConfig.categories : []
            return categories
                .filter((category) => !!category?.category_evaluation_enabled && String(category?.name || '').trim() !== '')
                .map((category) => ({
                    name: String(category.name).trim(),
                    works: Array.isArray(category?.works) ? category.works : [],
                }))
        },
        categoryEvaluationValueItems() {
            if (!this.selectedCourse?.teaching_schema_id) {
                return []
            }

            return normalizeTeachingCategoryEvaluationValueItems(this.gradingConfig?.category_evaluation_values)
        },
        categoryEvaluationValues() {
            return teachingCategoryEvaluationValueLabels(this.categoryEvaluationValueItems)
        },
        defaultCategoryEvaluationValue() {
            const defaultValue = String(this.gradingConfig?.default_category_evaluation_value || '').trim()
            if (defaultValue && this.categoryEvaluationValues.includes(defaultValue)) {
                return defaultValue
            }

            return this.categoryEvaluationValues[0] || ''
        },
        schemaCategoryTypes() {
            const schemaId = this.selectedCourse?.teaching_schema_id
            const grading = schemaId ? this.teachingStore?.gradingForSchema(schemaId) || {} : {}
            const categories = Array.isArray(grading?.categories) ? grading.categories : []

            const types = categories.flatMap((category) => {
                const works = Array.isArray(category?.works) ? category.works : []
                return works
                    .map((work) => {
                        if (typeof work === 'string') {
                            return String(work).trim()
                        }

                        return String(work?.short_name || '').trim()
                    })
                    .filter((type) => !!type)
            })

            return [...new Set(types)]
        },
        hasSchemaWorkColumns() {
            return this.workColumnsFromSchema.length > 0
        },
        typeColumns() {
            const seen = new Set()
            const columns = []

            this.workColumnsFromSchema.forEach((column) => {
                if (seen.has(column.type)) {
                    return
                }
                seen.add(column.type)
                columns.push(column)
            })

            if (!this.hasSchemaWorkColumns) {
                this.filteredEntries.forEach((entry) => {
                    const type = String(entry?.type || '').trim()
                    if (!type || seen.has(type)) {
                        return
                    }
                    seen.add(type)
                    columns.push({
                        type,
                        label: this.typeLabel(type),
                    })
                })
            }

            if (!columns.length) {
                columns.push({
                    type: '__none__',
                    label: 'Leistungen',
                    key: 'type-__none__',
                    kind: 'type',
                })
            }

            return columns.map((column) => ({
                ...column,
                key: column.key || `type-${column.type}`,
                kind: 'type',
            }))
        },
        courseWorksById() {
            const works = Array.isArray(this.courseWorkStore?.courseWorks) ? this.courseWorkStore.courseWorks : []
            return works.reduce((carry, work) => {
                if (!work?.id) {
                    return carry
                }
                carry[String(work.id)] = work
                return carry
            }, {})
        },
        filteredEntries() {
            const courseId = this.selectedCourse?.id
            const entries = Array.isArray(this.entryStore?.courseEntries) ? this.entryStore.courseEntries : []
            return entries.filter((entry) => {
                if (!entry?.id) {
                    return false
                }
                if (courseId && String(entry.teaching_course_id) !== String(courseId)) {
                    return false
                }
                return this.matchesSemester(entry.date)
            })
        },
        rows() {
            const grouped = {}

            this.filteredEntries.forEach((entry) => {
                const studentId = String(entry?.user_id || '')
                const type = String(entry?.type || '').trim() || '__none__'
                if (!studentId) {
                    return
                }

                if (!grouped[studentId]) {
                    grouped[studentId] = {}
                }
                if (!grouped[studentId][type]) {
                    grouped[studentId][type] = []
                }

                grouped[studentId][type].push({
                    text: this.entryItemLabel(entry),
                    sortDate: this.normalizeDateKey(entry.date),
                })
            })

            Object.values(grouped).forEach((byType) => {
                Object.keys(byType).forEach((type) => {
                    byType[type] = byType[type]
                        .sort((a, b) => String(b.sortDate || '').localeCompare(String(a.sortDate || '')))
                        .map((item) => item.text)
                })
            })

            return this.students.map((student) => {
                const studentId = String(student.id)
                return {
                    student_id: studentId,
                    student_label: this.studentLabelWithClass(student),
                    is_canceled: this.isStudentCanceled(student),
                    byType: grouped[studentId] || {},
                }
            })
        },
    },
    watch: {
        selectedCourse: {
            handler() {
                this.loadDataForSelectedCourse()
            },
            deep: false,
        },
        activeSemester() {
            this.loadCategoryEvaluationsForSelectedCourse()
        },
    },
    methods: {
        isStudentCanceled(student) {
            return !!student?.canceled_at || !!student?.deleted_at
        },
        compareStudentsBySort(a, b) {
            const lastA = String(a?.last_name || '')
            const lastB = String(b?.last_name || '')
            const firstA = String(a?.first_name || '')
            const firstB = String(b?.first_name || '')
            const classA = String(a?.schoolclass || a?.class || '')
            const classB = String(b?.schoolclass || b?.class || '')
            if (this.sortMode === 'last_name_first_name') {
                const cmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
                if (cmp !== 0) return cmp
                return firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
            }
            const classCmp = classA.localeCompare(classB, 'de', { numeric: true, sensitivity: 'base' })
            if (classCmp !== 0) return classCmp
            const lastCmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
            if (lastCmp !== 0) return lastCmp
            return firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
        },
        async loadDataForSelectedCourse() {
            if (!this.selectedCourse?.id || !this.entryStore || !this.courseWorkStore || !this.categoryEvaluationStore) {
                return
            }
            await Promise.allSettled([
                this.entryStore.indexByCourse(this.selectedCourse.id),
                this.courseWorkStore.index(this.selectedCourse.id),
                this.loadCategoryEvaluationsForSelectedCourse(),
            ])
        },
        async loadCategoryEvaluationsForSelectedCourse() {
            this.localCategoryEvaluationValues = {}

            if (!this.selectedCourse?.id || !this.categoryEvaluationStore || !this.enabledCategoryEvaluationCategories.length) {
                if (this.categoryEvaluationStore) {
                    this.categoryEvaluationStore.evaluations = []
                }
                return
            }

            await this.categoryEvaluationStore.indexByCourse(this.selectedCourse.id, this.activeEvaluationSemester())
        },
        activeEvaluationSemester() {
            if (this.semesterCount === 2) {
                return Number(this.activeSemester) || 1
            }

            return 1
        },
        categoryEvaluationCategoriesForType(type) {
            const normalizedType = String(type || '').trim()
            if (!normalizedType) {
                return []
            }

            return this.enabledCategoryEvaluationCategories.filter((category) => {
                return category.works.some((work) => {
                    if (typeof work === 'string') {
                        return String(work).trim() === normalizedType
                    }

                    return String(work?.short_name || '').trim() === normalizedType
                })
            })
        },
        normalizeDateKey(date) {
            if (!date) {
                return ''
            }
            if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
                return date.slice(0, 10)
            }
            const parsed = parseLocalDate(date)
            if (Number.isNaN(parsed.getTime())) {
                return ''
            }
            const year = parsed.getFullYear()
            const month = String(parsed.getMonth() + 1).padStart(2, '0')
            const day = String(parsed.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
        matchesSemester(date) {
            if (this.semesterCount !== 2 || this.activeSemester === 3) {
                return true
            }
            const boundary = this.normalizeDateKey(this.sem2StartDate)
            if (!boundary) {
                return true
            }
            const dateKey = this.normalizeDateKey(date)
            if (!dateKey) {
                return true
            }
            if (this.activeSemester === 1) {
                return dateKey < boundary
            }
            if (this.activeSemester === 2) {
                return dateKey >= boundary
            }
            return true
        },
        studentLabelWithClass(student) {
            const lastName = String(student?.last_name || '').trim()
            const firstName = String(student?.first_name || '').trim()
            const className = String(student?.schoolclass || student?.class || '').trim()
            const name = `${lastName}, ${firstName}`.replace(/^,\s*/, '').trim() || '–'
            return className ? `${name} (${className})` : name
        },
        entryItemLabel(entry) {
            const workId = entry?.teaching_course_work_id ? String(entry.teaching_course_work_id) : ''
            const concreteName = workId ? String(this.courseWorksById[workId]?.title || '').trim() : ''
            const fallbackName = this.typeLabel(entry?.type)
            const labelName = concreteName || fallbackName || 'Leistung'
            const grade = String(entry?.effective_grade || entry?.grade || '').trim()
            const dateLabel = this.entryDateLabel(entry?.date)

            if (!grade) {
                return dateLabel ? `${dateLabel} - ${labelName}` : labelName
            }
            if (!labelName || labelName === '–') {
                return dateLabel ? `${dateLabel} - ${grade}` : grade
            }
            const valueLabel = `${labelName}: ${grade}`
            return dateLabel ? `${dateLabel} - ${valueLabel}` : valueLabel
        },
        entryDateLabel(date) {
            const dateKey = this.normalizeDateKey(date)
            if (!dateKey) {
                return ''
            }
            const parsed = parseLocalDate(dateKey)
            if (Number.isNaN(parsed.getTime())) {
                return ''
            }
            return parsed.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        typeLabel(type) {
            const shortType = String(type || '').trim()
            if (!shortType) {
                return '–'
            }
            const name = this.worksByType[shortType]
            if (!name) {
                return shortType
            }
            return `${shortType} - ${name}`
        },
        categoryEvaluationKey(studentId, categoryName) {
            return `${studentId}|${this.activeEvaluationSemester()}|${categoryName}`
        },
        storedCategoryEvaluationValue(studentId, categoryName) {
            const found = (this.categoryEvaluationStore?.evaluations || []).find((item) =>
                String(item.user_id) === String(studentId)
                && String(item.semester) === String(this.activeEvaluationSemester())
                && String(item.category_name) === String(categoryName)
            )

            return found?.value || ''
        },
        categoryEvaluationValue(studentId, categoryName) {
            const key = this.categoryEvaluationKey(studentId, categoryName)
            if (Object.prototype.hasOwnProperty.call(this.localCategoryEvaluationValues, key)) {
                return this.localCategoryEvaluationValues[key]
            }

            return this.storedCategoryEvaluationValue(studentId, categoryName) || this.defaultCategoryEvaluationValue
        },
        categoryEvaluationSaving(studentId, categoryName) {
            return Boolean(this.savingCategoryEvaluationKeys[this.categoryEvaluationKey(studentId, categoryName)])
        },
        categoryEvaluationValueColor(value) {
            return teachingCategoryEvaluationColorForValue(this.categoryEvaluationValueItems, value, '#4f6fb3')
        },
        async saveCategoryEvaluation(studentId, categoryName, value) {
            if (!value || !this.selectedCourse?.id || !this.categoryEvaluationStore) {
                return
            }

            if (this.categoryEvaluationSaving(studentId, categoryName)) {
                return
            }

            if (this.categoryEvaluationValue(studentId, categoryName) === value) {
                return
            }

            const key = this.categoryEvaluationKey(studentId, categoryName)
            const hadLocalValue = Object.prototype.hasOwnProperty.call(this.localCategoryEvaluationValues, key)
            const previousLocalValue = this.localCategoryEvaluationValues[key]

            this.localCategoryEvaluationValues = {
                ...this.localCategoryEvaluationValues,
                [key]: value,
            }
            this.savingCategoryEvaluationKeys = {
                ...this.savingCategoryEvaluationKeys,
                [key]: true,
            }

            const result = await this.categoryEvaluationStore.store({
                teaching_course_id: this.selectedCourse.id,
                user_id: studentId,
                semester: this.activeEvaluationSemester(),
                category_name: categoryName,
                value,
            })

            this.savingCategoryEvaluationKeys = Object.fromEntries(
                Object.entries(this.savingCategoryEvaluationKeys).filter(([entryKey]) => entryKey !== key)
            )

            if (result) {
                return
            }

            if (hadLocalValue) {
                this.localCategoryEvaluationValues = {
                    ...this.localCategoryEvaluationValues,
                    [key]: previousLocalValue,
                }

                return
            }

            this.localCategoryEvaluationValues = Object.fromEntries(
                Object.entries(this.localCategoryEvaluationValues).filter(([entryKey]) => entryKey !== key)
            )
        },
    },
}
</script>

<style scoped>
.performances-card {
    border: 1px dashed rgba(16, 38, 58, 0.2);
    background: rgba(255, 255, 255, 0.9);
}

.performances-table-wrap {
    overflow-x: auto;
}

.performances-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-width: 980px;
}

.performances-table th,
.performances-table td {
    border: 1px solid rgba(16, 38, 58, 0.1);
    text-align: left;
    padding: 8px 10px;
    vertical-align: top;
}

.performances-table th {
    background: rgba(15, 23, 42, 0.06);
    font-weight: 600;
    white-space: nowrap;
}

.student-cell {
    width: 260px;
    min-width: 260px;
    max-width: 260px;
    font-weight: 500;
}

.performance-items {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.performance-item {
    white-space: normal;
    word-break: break-word;
}

.performance-category-evaluations {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid rgba(16, 38, 58, 0.12);
}

.performance-category-evaluation {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.performance-category-label {
    font-size: 0.75rem;
    line-height: 1.2;
    font-weight: 600;
    color: rgba(16, 38, 58, 0.72);
}

.performance-category-options {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.performance-category-chip {
    cursor: pointer;
}

.performance-category-chip.v-chip--disabled {
    cursor: default;
}

.student-cell--canceled {
    text-decoration: line-through;
    opacity: 0.75;
}

.performance-row--canceled td {
    opacity: 0.6;
}

.performance-row--canceled .student-cell--canceled {
    opacity: 0.75;
}
</style>
