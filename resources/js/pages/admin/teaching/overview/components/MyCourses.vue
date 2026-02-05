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

    <ItsGridBox color="primary" :title="data.id ? 'Fach ändern' : 'Neues Fach'" icon="mdi-invoice-list" class="w-100" v-if="action == 'teaching_course_new_or_edit'">
        <!-- NEUER/EDIT KURS-->
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text>
                <v-form ref="form" v-model="is_valid" @submit.prevent="save(data)" class="mb-4">
                    <div class="text-caption text-text">Bitte geben Sie die Felder ein (* = Pflichtfeld)</div>
                    <v-text-field autofocus v-model="data.title" label="Bezeichnung *" :rules="[required(), maxLength(255)]" />

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
                            <v-chip v-if="selected_course?.students_info?.length" size="x-small" color="primary" variant="tonal">
                                {{ selected_course.students_info.length }}
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
                                <v-list-item v-if="!selected_course?.students_info?.length">
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
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useImport116Store } from '@/stores/admin/teaching/Import116Store'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
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
        // await this.courseStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            import116Store: null,
            courseStore: null,
            is_valid: false,
            data: {
                selected_classes: [],
            },

            delete_level: 0,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useImport116Store, ['import116_students']),
        ...mapWritableState(useCourseStore, ['courses', 'classes', 'selected_course', 'selected_course_id']),
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
            this.action = ''
        },

        selectCourse(course) {
            if (course && this.selected_course?.id !== course.id) {
                this.courseStore.ensureCourseStudentCollections(course)
                this.selected_course = course
                this.delete_level = 0
            } else {
                this.selected_course = null
            }
        },

        newCourse() {
            this.data = {}
            // Create a fresh empty course object for the new course
            this.selected_course = {
                id: null,
                title: '',
                classes: [],
                students: [],
                students_info: [],
                students_deleted: [],
                students_deleted_info: [],
            }
            this.selected_course_id = null
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
    },
}
</script>
