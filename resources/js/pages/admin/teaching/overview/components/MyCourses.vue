<template>
    <ItsGridBox color="primary" title="Meine Fächer" icon="mdi-invoice-list" class="w-100" :disabled="action != ''">
        <!-- KURS ANLEGEN -->
        <v-card tile flat color="transparent" class="mt-4">
            <its-menu-button title="Fach" subtitle="anlegen" icon="mdi-plus-circle-multiple" color="primary" @click="newCourse" />
        </v-card>

        <!-- ALLE KURSE ANZEIGEN -->
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap ga-2 align-center w-100">
                    <v-chip-group v-model="selected_course_id" column>
                        <v-chip v-for="course in courses" :key="course.id" :value="course.id" :color="selected_course?.id === course.id ? 'primary' : 'secondary'">
                            {{ course.title }} ({{ course.classes.join(', ') }})
                        </v-chip>
                    </v-chip-group>

                    <div class="w-100 d-flex flex-row justify-end" v-if="selected_course">
                        <div class="d-flex flex-row align-center ga-2">
                            <v-btn flat tile size="small" color="warning" icon="mdi-delete" @click="delete_level++" v-if="delete_level == 0" />
                            <v-btn flat tile size="small" color="success" icon="mdi-delete-off" @click="delete_level = 0" v-if="delete_level == 1" />
                            <v-btn flat tile size="small" color="error" icon="mdi-delete" @click="deleteCourse(selected_course)" v-if="delete_level == 1" />
                            <v-btn flat tile size="small" color="primary" icon="mdi-pencil" @click="editCourse(selected_course)" v-if="delete_level == 0" />
                        </div>
                    </div>
                </v-card>
            </v-card-text>
        </v-card>
    </ItsGridBox>

    <ItsGridBox color="primary" icon="mdi-invoice-list" class="w-100" v-if="action == 'teaching_course_new_or_edit'">
        <template #title>
            <div class="d-flex align-center ga-2 flex-grow-1">
                <div>{{ data.id ? 'Fach ändern' : 'Neues Fach' }}</div>
                <v-spacer />
                <v-btn icon="mdi-close" size="x-small" color="warning" variant="flat" @click="abortNewCourse" />
                <v-btn icon="mdi-content-save" size="x-small" color="success" variant="flat" @click="$refs.form.validate().then(v => { if (v.valid) save(data) })" v-if="data.title && data?.classes?.length > 0" />
            </div>
        </template>
        <!-- NEUER/EDIT KURS-->
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text>
                <v-form ref="form" v-model="is_valid" @submit.prevent="save(data)" class="mb-4">
                    <div class="text-caption text-text">Bitte geben Sie die Felder ein (* = Pflichtfeld)</div>
                    <v-text-field autofocus v-model="data.title" label="Bezeichnung *" :rules="[required(), maxLength(255)]" />
                    <v-select
                        v-model="data.teaching_schema_id"
                        :items="schemaItems"
                        label="Benotungsschema"
                        clearable
                        density="compact"
                        hide-details
                        class="mt-2" />

                    <div class="text-caption text-text mt-4">Klassen auswählen</div>
                    <v-chip-group v-model="data.classes" multiple column>
                        <v-chip v-for="cls in classes" :key="cls" :value="cls" filter variant="outlined">
                            {{ cls }}
                        </v-chip>
                    </v-chip-group>
                    <div class="text-caption text-text mt-1" v-if="data?.classes?.length">Ausgewählt: {{ data.classes.join(', ') }}</div>

                    <!-- Schülerinnen -->
                    <v-card variant="outlined" class="mt-4">
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                            <v-icon size="18">mdi-account-check</v-icon>
                            Ausgewählte Schülerinnen
                            <v-chip v-if="sortedSelectedStudents.length" size="x-small" color="primary" variant="tonal">
                                {{ sortedSelectedStudents.length }}
                            </v-chip>
                            <v-spacer />
                            <v-btn size="x-small" color="error" variant="tonal" prepend-icon="mdi-minus" @click="removeAllStudents">Alle entfernen</v-btn>
                            <v-btn size="x-small" color="primary" variant="tonal" prepend-icon="mdi-plus" @click="addAllStudents">Alle hinzufügen</v-btn>
                        </v-card-title>
                        <v-divider />
                        <v-card-text class="pa-0">
                            <v-list density="compact">
                                <v-list-item v-for="student in sortedSelectedStudents" :key="student.id">
                                    <div class="d-flex align-center ga-2 w-100">
                                        <v-chip v-if="student.schoolclass || student.class" size="x-small" variant="tonal" color="primary">
                                            {{ student.schoolclass || student.class }}
                                        </v-chip>
                                        <div class="text-body-2">{{ student.last_name }}, {{ student.first_name }}</div>
                                        <v-spacer />
                                        <v-btn size="x-small" color="error" variant="tonal" prepend-icon="mdi-minus" @click="removeStudent(student)">Entfernen</v-btn>
                                    </div>
                                </v-list-item>
                                <v-list-item v-if="!sortedSelectedStudents.length">
                                    <v-list-item-title class="text-caption text-medium-emphasis">Keine Schülerinnen ausgewählt.</v-list-item-title>
                                </v-list-item>
                            </v-list>
                        </v-card-text>
                    </v-card>

                    <!-- Neue Schülerinnen -->
                    <v-card variant="outlined" class="mt-4">
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                            <v-icon size="18">mdi-account-school</v-icon>
                            Schülerinnen (Import116)
                            <v-chip v-if="filteredImport116Students.length" size="x-small" color="primary" variant="tonal">
                                {{ filteredImport116Students.length }}
                            </v-chip>
                        </v-card-title>
                        <v-divider />
                        <v-card-text class="pa-0">
                            <v-list density="compact">
                                <v-list-item v-for="student in filteredImport116Students" :key="student.id">
                                    <div class="d-flex align-center ga-2 w-100">
                                        <v-chip v-if="student.class" size="x-small" variant="tonal" color="primary">
                                            {{ student.class }}
                                        </v-chip>
                                        <div class="text-body-2">{{ student.last_name }}, {{ student.first_name }}</div>
                                        <v-spacer />
                                        <v-btn
                                            size="x-small"
                                            color="primary"
                                            variant="tonal"
                                            prepend-icon="mdi-plus"
                                            :disabled="isStudentSelected(student)"
                                            @click="addStudent(student)">
                                            Hinzufügen
                                        </v-btn>
                                    </div>
                                </v-list-item>
                                <v-list-item v-if="!filteredImport116Students.length">
                                    <v-list-item-title class="text-caption text-medium-emphasis">Keine Schülerinnen geladen.</v-list-item-title>
                                </v-list-item>
                            </v-list>
                        </v-card-text>
                    </v-card>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="warning" flat tile @click="abortNewCourse">Abbruch</v-btn>
                        <v-btn color="success" flat tile type="submit" v-if="data.title && data?.classes?.length > 0">Speichern</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { parseLocalDate } from '@/helpers/date'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
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
        this.courseStore = useCourseStore()
        this.teachingStore = useTeachingStore()
        await this.teachingStore.loadSettings()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            import116_students_local: [],
            courseStore: null,
            teachingStore: null,
            is_valid: false,
            data: {
                selected_classes: [],
            },

            delete_level: 0,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, ['courses', 'classes', 'selected_course', 'selected_course_id', 'selected_course_student']),
        schemaItems() {
            return (this.teachingStore?.schemas || [])
                .map((s) => ({ title: s.name, value: s.id }))
                .sort((a, b) => a.title.localeCompare(b.title))
        },
        filteredImport116Students() {
            const list = this.import116_students_local || []
            const selected = this.data?.students_info || []
            if (!selected.length) return list

            const selectedEmails = new Set(selected.map((student) => (student.email || '').toString().trim().toLowerCase()).filter((email) => email))

            if (!selectedEmails.size) return list

            return list.filter((student) => {
                const email = (student.email || '').toString().trim().toLowerCase()
                return !email || !selectedEmails.has(email)
            })
        },
        sortedSelectedStudents() {
            const list = this.data?.students_info || []
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
        selected_course_id(newId) {
            const course = this.courses.find((c) => c.id === newId) || null
            this.selectCourse(course)
        },
        'data.classes': {
            handler(newClasses) {
                if (this.action !== 'teaching_course_new_or_edit') return
                if (!Array.isArray(newClasses)) return
                this.selectStudents(newClasses)
            },
            deep: true,
        },
    },

    methods: {
        async selectStudents(classes) {
                if (!Array.isArray(classes) || !classes.length) {
                this.import116_students_local = []
                return
            }
            const response = await axios.get(`/api/admin/teaching/import116/load_class_students`, {
                params: { schoolclasses: classes },
            })
            this.import116_students_local = response?.data?.data || []
        },

        addAllStudents() {
            if (!this.data) return
            this.courseStore.ensureCourseStudentCollections(this.data)
            if (!Array.isArray(this.import116_students_local) || !this.import116_students_local.length) return

            this.import116_students_local.slice().forEach((student) => {
                this.addStudent(student)
            })
        },

        removeAllStudents() {
            if (!this.data) return
            this.courseStore.ensureCourseStudentCollections(this.data)

            if (!Array.isArray(this.data.students_info) || !this.data.students_info.length) return

            this.data.students_info.slice().forEach((student) => {
                this.removeStudent(student)
            })
        },

        addStudent(student) {
            if (!this.data) return
            this.courseStore.ensureCourseStudentCollections(this.data)

            const email = (student.email || '').toString().trim().toLowerCase()

            if (!this.data.students.includes(student.id)) {
                this.data.students.push(student.id)
                this.data.students_info.push(student)
            }

            if (this.data.students_deleted.length) {
                if (email) {
                    const remaining = []
                    this.data.students_deleted_info = this.data.students_deleted_info.filter((s) => {
                        const deletedEmail = (s.email || '').toString().trim().toLowerCase()
                        const keep = !deletedEmail || deletedEmail !== email
                        if (keep && s.id) remaining.push(s.id)
                        return keep
                    })
                    this.data.students_deleted = remaining.length ? remaining : this.data.students_deleted.filter((id) => id !== student.id)
                } else {
                    const deletedIndex = this.data.students_deleted.indexOf(student.id)
                    if (deletedIndex !== -1) {
                        this.data.students_deleted.splice(deletedIndex, 1)
                        this.data.students_deleted_info = this.data.students_deleted_info.filter((s) => s.id !== student.id)
                    }
                }
            }

            this.import116_students_local = this.import116_students_local.filter((s) => s.id !== student.id)
        },

        isStudentSelected(student) {
            if (!this.data) return false
            this.courseStore.ensureCourseStudentCollections(this.data)
            return this.data.students.includes(student.id)
        },

        removeStudent(student) {
            if (!this.data) return
            this.courseStore.ensureCourseStudentCollections(this.data)

            const index = this.data.students.indexOf(student.id)
            if (index !== -1) {
                this.data.students.splice(index, 1)
                this.data.students_info = this.data.students_info.filter((s) => s.id !== student.id)
            }

            if (!this.data.students_deleted.includes(student.id)) {
                this.data.students_deleted.push(student.id)
                this.data.students_deleted_info.push(student)
            }

            const email = (student.email || '').toString().trim().toLowerCase()
            const exists = this.import116_students_local.some((s) => {
                if (email) {
                    return (s.email || '').toString().trim().toLowerCase() === email
                }
                return s.id === student.id
            })

            if (!exists) {
                this.import116_students_local.push(student)
            }
        },

        async save(data) {
            const source = data
            this.courseStore.ensureCourseStudentCollections(source)
            if ((!source.students || source.students.length === 0) && Array.isArray(source.students_info) && source.students_info.length > 0) {
                source.students = source.students_info.map((s) => s.id).filter((id) => id != null)
            }

            const payload = {
                ...data,
                students: source.students || [],
                students_deleted: source.students_deleted || [],
            }

            let result = null
            if (data.id) {
                result = await this.courseStore.update(payload)
            } else {
                result = await this.courseStore.store(payload)
            }
            await this.courseStore.index()
            const savedId = result?.data?.id || result?.id || data.id || null
            this.selected_course = savedId ? this.courses.find((c) => c.id === savedId) || null : null
            this.selected_course_id = this.selected_course?.id || null
            this.selected_course_student = null
            this.action_2 = ''
            this.action = ''
        },

        selectCourse(course) {
            if (this.action === 'teaching_course_new_or_edit') {
                return
            }
            if (!course) {
                this.selected_course = null
                this.selected_course_id = null
                this.selected_course_student = null
                this.action_2 = ''
                this.delete_level = 0
                return
            }

            this.courseStore.ensureCourseStudentCollections(course)
            this.selected_course = course
            this.selected_course_id = course.id
            this.selected_course_student = null
            this.action_2 = ''
            this.delete_level = 0

            // Auto-select course date
            this.autoSelectCourseDate(course)
        },

        autoSelectCourseDate(course) {
            const courseDateStore = useCourseDateStore()
            const dates = Array.isArray(course?.course_dates) ? course.course_dates : []

            if (!dates.length) {
                courseDateStore.selected_courseDate = null
                return
            }

            const today = new Date()
            today.setHours(0, 0, 0, 0)

            // Parse and sort dates
            const parsedDates = dates
                .map((d) => ({
                    original: d,
                    date: parseLocalDate(d.date),
                    dateStr: d.date,
                }))
                .filter((d) => !isNaN(d.date.getTime()))
                .sort((a, b) => a.date - b.date)

            if (!parsedDates.length) {
                courseDateStore.selected_courseDate = null
                return
            }

            // 1. Try today's date
            const todayDate = parsedDates.find((d) => {
                const dDate = new Date(d.date)
                dDate.setHours(0, 0, 0, 0)
                return dDate.getTime() === today.getTime()
            })
            if (todayDate) {
                courseDateStore.selected_courseDate = todayDate.original
                return
            }

            // 2. Try last (most recent past) date
            const pastDates = parsedDates.filter((d) => d.date < today)
            if (pastDates.length) {
                courseDateStore.selected_courseDate = pastDates[pastDates.length - 1].original
                return
            }

            // 3. Try next (upcoming future) date
            const futureDates = parsedDates.filter((d) => d.date > today)
            if (futureDates.length) {
                courseDateStore.selected_courseDate = futureDates[0].original
                return
            }

            // 4. No suitable date found
            courseDateStore.selected_courseDate = null
        },

        newCourse() {
            this.selected_course_student = null
            this.action_2 = ''
            this.import116_students_local = []
            this.data = {
                id: null,
                title: '',
                classes: [],
                students: [],
                students_info: [],
                students_deleted: [],
                students_deleted_info: [],
            }
            this.selected_course = null
            this.selected_course_id = null
            this.action = 'teaching_course_new_or_edit'
        },
        editCourse(course) {
            this.selected_course_student = null
            this.action_2 = ''
            this.import116_students_local = []
            this.selected_course = course
            this.courseStore.ensureCourseStudentCollections(this.selected_course)
            this.data = JSON.parse(JSON.stringify(this.selected_course))
            this.courseStore.ensureCourseStudentCollections(this.data)
            this.action = 'teaching_course_new_or_edit'
            this.selectStudents(this.data.classes || [])
        },
        async abortNewCourse() {
            const courseId = this.data?.id || null
            if (courseId) {
                await this.courseStore.index()
                this.selected_course = this.courses.find((c) => c.id === courseId) || null
                this.selected_course_id = this.selected_course?.id || null
            } else {
                this.selected_course = null
                this.selected_course_id = null
            }
            this.selected_course_student = null
            this.action_2 = ''
            this.import116_students_local = []
            this.action = ''
        },
        async deleteCourse(course) {
            if (!(await this.courseStore.destroy(course.id))) {
                this.delete_level = 0
                return
            }
            await this.courseStore.index()
            this.selected_course = null
            this.selected_course_id = null
            this.selected_course_student = null
            this.action_2 = ''
            this.delete_level = 0
        },
    },
}
</script>
