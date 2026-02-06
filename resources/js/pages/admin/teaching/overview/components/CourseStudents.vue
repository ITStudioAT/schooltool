<template>
    <ItsGridBox color="primary" title="Schüler:innen" icon="mdi-invoice-list" class="w-100" v-if="selected_course" :disabled="action != ''">
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <!-- Anzeige ausgewählter Kurs -->
                <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between" v-if="selected_course">
                    <div>
                        <div class="text-body-1 font-weight-medium">{{ selected_course.title }}</div>
                        <div class="d-flex flex-wrap ga-1 mt-1">
                            <v-chip v-for="cls in selected_course.classes" :key="cls" size="small" variant="tonal">
                                {{ cls }}
                            </v-chip>
                        </div>
                    </div>
                </v-card>

                <!-- Ausgewählte Schülerinnen (Anzeige) -->
                <v-card variant="outlined" class="mt-4" v-if="selected_course">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                        <v-icon size="18">mdi-account-check</v-icon>
                        Schüler:innen
                        <v-chip v-if="selected_course?.students_info?.length" size="x-small" color="primary" variant="tonal">
                            {{ selected_course.students_info.length }}
                        </v-chip>
                        <v-spacer />
                        <v-btn
                            size="small"
                            :variant="show_bulk_entry ? 'flat' : 'outlined'"
                            :color="show_bulk_entry ? 'warning' : 'primary'"
                            @click="toggleBulkEntry">
                            {{ show_bulk_entry ? 'Sammelaktion schließen' : 'Sammelaktion' }}
                        </v-btn>
                    </v-card-title>
                    <v-card-text v-if="show_bulk_entry" class="pt-0">
                        <v-card variant="outlined" class="pa-3">
                            <div class="text-caption text-medium-emphasis mb-2">Eintrag für mehrere Schüler:innen</div>
                            <v-form ref="bulkEntryForm" @submit.prevent="saveBulkEntry">
                                <v-select v-model="bulk_entry_form.type" label="Typ" :items="workTypeItems" item-title="title" item-value="value" clearable />
                                <v-select v-model="bulk_entry_form.grade" label="Note" :items="gradeItemsForType" item-title="title" item-value="value" clearable />
                                <v-date-input v-model="bulk_entry_form.date" label="Datum" />
                                <v-textarea v-model="bulk_entry_form.description" label="Beschreibung" rows="3" :counter="1024" :maxlength="1024" />

                                <div class="d-flex align-center justify-space-between mt-2">
                                    <div class="d-flex align-center ga-2">
                                        <v-btn size="small" variant="text" @click="selectAllBulkStudents">Alle auswählen</v-btn>
                                        <v-btn size="small" variant="text" @click="clearBulkStudents">Keine auswählen</v-btn>
                                    </div>
                                    <v-btn
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        :disabled="!bulkEntryEnabled"
                                        type="submit">
                                        {{ bulk_entry_form.student_ids.length ? `Auf ${bulk_entry_form.student_ids.length} Schüler:in(nen) anwenden` : 'Auf alle anwenden' }}
                                    </v-btn>
                                </div>
                            </v-form>
                        </v-card>
                    </v-card-text>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item
                                v-for="student in sortedSelectedStudents"
                                :key="student.id"
                                :class="show_bulk_entry ? '' : 'cursor-pointer'"
                                @click="show_bulk_entry ? null : openStudent(student)">
                                <div class="d-flex align-center ga-2 w-100">
                                    <v-checkbox
                                        v-if="show_bulk_entry"
                                        v-model="bulk_entry_form.student_ids"
                                        :value="student.id"
                                        density="compact"
                                        hide-details
                                        class="flex-grow-0" />
                                    <v-chip v-if="student.schoolclass || student.class" size="x-small" variant="tonal" color="primary">
                                        {{ student.schoolclass || student.class }}
                                    </v-chip>
                                    <div class="text-body-2">{{ student.last_name }}, {{ student.first_name }}</div>
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!selected_course?.students_info?.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine Schülerinnen ausgewählt.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { parseLocalDate } from '@/helpers/date'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useImport116Store } from '@/stores/admin/teaching/Import116Store'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'
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
        this.import116Store = useImport116Store()
        this.courseStore = useCourseStore()
        this.entryStore = useCourseStudentEntryStore()
        this.teachingStore = useTeachingStore()
        this.courseStore.index()
        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
        }
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            import116Store: null,
            courseStore: null,
            entryStore: null,
            teachingStore: null,
            is_valid: false,
            data: {
                selected_classes: [],
            },

            delete_level: 0,
            show_bulk_entry: false,
            bulk_entry_form: this.emptyBulkEntryForm(),
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useImport116Store, ['import116_students']),
        ...mapWritableState(useCourseStore, ['courses', 'classes', 'selected_course', 'selected_course_student']),
        ...mapWritableState(useTeachingStore, ['settings']),
        teachingWorks() {
            const schemaId = this.selected_course?.teaching_schema_id
            if (!schemaId) return []
            return this.teachingStore.worksForSchema(schemaId)
        },
        workTypeItems() {
            return this.teachingWorks.map((work) => ({
                title: `${work.short_name} - ${work.name}`,
                value: work.short_name,
            }))
        },
        gradeItemsForType() {
            const selectedType = this.bulk_entry_form.type
            if (!selectedType) return []
            const work = this.teachingWorks.find((w) => w.short_name === selectedType)
            const grades = work?.grades || []
            return grades.map((grade) => ({
                title: grade.name ? `${grade.grade} (${grade.name})` : grade.grade,
                value: grade.grade,
            }))
        },
        studentItems() {
            const list = this.selected_course?.students_info || []
            return [...list]
                .sort((a, b) => {
                    const classA = (a.schoolclass || a.class || '').toString()
                    const classB = (b.schoolclass || b.class || '').toString()
                    const classCmp = classA.localeCompare(classB, 'de', { numeric: true, sensitivity: 'base' })
                    if (classCmp !== 0) return classCmp

                    const lastA = (a.last_name || '').toString()
                    const lastB = (b.last_name || '').toString()
                    const lastCmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
                    if (lastCmp !== 0) return lastCmp

                    const firstA = (a.first_name || '').toString()
                    const firstB = (b.first_name || '').toString()
                    return firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
                })
                .map((student) => ({
                    title: this.studentLabel(student),
                    value: student.id,
                }))
        },
        hasStudents() {
            return (this.selected_course?.students_info || []).length > 0
        },
        bulkEntryEnabled() {
            if (!this.hasStudents) return false
            const type = (this.bulk_entry_form.type || '').toString().trim()
            const grade = (this.bulk_entry_form.grade || '').toString().trim()
            const desc = (this.bulk_entry_form.description || '').toString().trim()
            return !!(type || grade || desc)
        },
        filteredImport116Students() {
            const list = this.import116_students || []
            const selected = this.selected_course?.students_info || []
            if (!selected.length) return list

            const selectedEmails = new Set(selected.map((student) => (student.email || '').toString().trim().toLowerCase()).filter((email) => email))

            if (!selectedEmails.size) return list

            return list.filter((student) => {
                const email = (student.email || '').toString().trim().toLowerCase()
                return !email || !selectedEmails.has(email)
            })
        },
        sortedSelectedStudents() {
            const list = this.selected_course?.students_info || []
            return [...list].sort((a, b) => {
                const classA = (a.schoolclass || a.class || '').toString()
                const classB = (b.schoolclass || b.class || '').toString()
                const classCmp = classA.localeCompare(classB, 'de', { numeric: true, sensitivity: 'base' })
                if (classCmp !== 0) return classCmp

                const lastA = (a.last_name || '').toString()
                const lastB = (b.last_name || '').toString()
                const lastCmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
                if (lastCmp !== 0) return lastCmp

                const firstA = (a.first_name || '').toString()
                const firstB = (b.first_name || '').toString()
                return firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
            })
        },
    },

    watch: {
        'data.classes': {
            handler(newClasses) {
                if (this.action !== 'teaching_course_new_or_edit') return
                if (!Array.isArray(newClasses)) return
                this.selectStudents(newClasses)
            },
            deep: true,
        },
        'bulk_entry_form.date'(val) {
            if (val && val instanceof Date) {
                this.bulk_entry_form.date = this.toDateString(val)
            }
        },
    },

    methods: {
        emptyBulkEntryForm() {
            return {
                student_ids: [],
                type: '',
                grade: '',
                date: this.toDateString(new Date()),
                description: '',
            }
        },
        toggleBulkEntry() {
            this.show_bulk_entry = !this.show_bulk_entry
            if (this.show_bulk_entry) {
                this.bulk_entry_form = this.emptyBulkEntryForm()
            }
        },
        cancelBulkEntry() {
            this.show_bulk_entry = false
            this.bulk_entry_form = this.emptyBulkEntryForm()
        },
        async saveBulkEntry() {
            if (!this.selected_course) return
            const allIds = (this.selected_course?.students_info || []).map((s) => s.id)
            const targetIds = this.bulk_entry_form.student_ids.length ? this.bulk_entry_form.student_ids : allIds
            if (!targetIds.length) return

            const date = this.bulk_entry_form.date instanceof Date ? this.toDateString(this.bulk_entry_form.date) : this.bulk_entry_form.date
            const basePayload = {
                teaching_course_id: this.selected_course.id,
                type: this.bulk_entry_form.type,
                grade: this.bulk_entry_form.grade,
                date,
                description: this.bulk_entry_form.description,
            }

            let ok = true
            for (const userId of targetIds) {
                const result = await this.entryStore.store({ ...basePayload, user_id: userId })
                if (!result) ok = false
            }

            if (ok) {
                this.cancelBulkEntry()
            }
        },
        selectAllBulkStudents() {
            this.bulk_entry_form.student_ids = (this.selected_course?.students_info || []).map((s) => s.id)
        },
        clearBulkStudents() {
            this.bulk_entry_form.student_ids = []
        },
        studentLabel(student) {
            const cls = student.schoolclass ? `${student.schoolclass} ` : ''
            const name = `${student.last_name || ''}, ${student.first_name || ''}`.trim()
            return `${cls}${name}`.trim()
        },
        toDateString(date) {
            const d = parseLocalDate(date)
            const year = d.getFullYear()
            const month = String(d.getMonth() + 1).padStart(2, '0')
            const day = String(d.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
        async selectStudents(classes) {
            if (!Array.isArray(classes) || !classes.length) {
                this.import116_students = []
                return
            }
            await this.import116Store.loadClassStudents(classes)
        },

        addAllStudents() {
            if (!this.selected_course) return
            if (!Array.isArray(this.import116_students) || !this.import116_students.length) return

            this.import116_students.slice().forEach((student) => {
                this.addStudent(student)
            })
        },

        removeAllStudents() {
            if (!this.selected_course) return
            this.courseStore.ensureCourseStudentCollections(this.selected_course)

            if (!Array.isArray(this.selected_course.students_info) || !this.selected_course.students_info.length) return

            this.selected_course.students_info.slice().forEach((student) => {
                this.removeStudent(student)
            })
        },

        addStudent(student) {
            if (!this.selected_course) return

            this.courseStore.ensureCourseStudentCollections(this.selected_course)

            const email = (student.email || '').toString().trim().toLowerCase()

            if (!this.selected_course.students.includes(student.id)) {
                this.selected_course.students.push(student.id)
                this.selected_course.students_info.push(student)
            }

            if (this.selected_course.students_deleted.length) {
                if (email) {
                    const remaining = []
                    this.selected_course.students_deleted_info = this.selected_course.students_deleted_info.filter((s) => {
                        const deletedEmail = (s.email || '').toString().trim().toLowerCase()
                        const keep = !deletedEmail || deletedEmail !== email
                        if (keep && s.id) remaining.push(s.id)
                        return keep
                    })
                    this.selected_course.students_deleted = remaining.length ? remaining : this.selected_course.students_deleted.filter((id) => id !== student.id)
                } else {
                    const deletedIndex = this.selected_course.students_deleted.indexOf(student.id)
                    if (deletedIndex !== -1) {
                        this.selected_course.students_deleted.splice(deletedIndex, 1)
                        this.selected_course.students_deleted_info = this.selected_course.students_deleted_info.filter((s) => s.id !== student.id)
                    }
                }
            }

            this.import116_students = this.import116_students.filter((s) => s.id !== student.id)
        },

        isStudentSelected(student) {
            if (!this.selected_course) return false
            this.courseStore.ensureCourseStudentCollections(this.selected_course)
            return this.selected_course.students.includes(student.id)
        },

        removeStudent(student) {
            if (!this.selected_course) return
            this.courseStore.ensureCourseStudentCollections(this.selected_course)

            const index = this.selected_course.students.indexOf(student.id)
            if (index !== -1) {
                this.selected_course.students.splice(index, 1)
                this.selected_course.students_info = this.selected_course.students_info.filter((s) => s.id !== student.id)
            }

            if (!this.selected_course.students_deleted.includes(student.id)) {
                this.selected_course.students_deleted.push(student.id)
                this.selected_course.students_deleted_info.push(student)
            }

            const email = (student.email || '').toString().trim().toLowerCase()
            const exists = this.import116_students.some((s) => {
                if (email) {
                    return (s.email || '').toString().trim().toLowerCase() === email
                }
                return s.id === student.id
            })

            if (!exists) {
                this.import116_students.push(student)
            }
        },

        async save(data) {
            const source = this.selected_course && data.id && this.selected_course.id === data.id ? this.selected_course : data
            this.courseStore.ensureCourseStudentCollections(source)

            const payload = {
                ...data,
                students: source.students || [],
                students_deleted: source.students_deleted || [],
            }

            if (data.id) {
                await this.courseStore.update(payload)
            } else {
                await this.courseStore.store(payload)
            }
            await this.courseStore.index()
            this.selected_course = this.courses.find((c) => c.id === data.id) || null
            this.selected_course = null
            this.action = ''
        },

        selectCourse(course) {
            if (this.selected_course != course) {
                this.courseStore.ensureCourseStudentCollections(course)
                this.selected_course = course
                this.delete_level = 0
            } else {
                this.selected_course = null
            }
        },

        newCourse() {
            this.data = {}
            this.action = 'teaching_course_new_or_edit'
        },
        editCourse(course) {
            this.data = { ...course }
            this.selected_course = course
            this.courseStore.ensureCourseStudentCollections(this.selected_course)
            this.action = 'teaching_course_new_or_edit'
            this.selectStudents(this.data.classes || [])
        },
        abortNewCourse() {
            this.selected_course = null
            this.action = ''
        },
        async deleteCourse(course) {
            if (!(await this.courseStore.destroy(course.id))) {
                this.delete_level = 0
                return
            }
            await this.courseStore.index()
            this.selected_course = null
            this.delete_level = 0
        },
        openStudent(student) {
            if (!student) return
            this.selected_course_student = student
            this.action_2 = 'course_student_view'
        },
    },
}
</script>
