<template>
    <ItsGridBox variant="overview" color="primary" title="Meine Fächer" icon="mdi-invoice-list" class="w-100" :disabled="action != '' || isSavingCourse">
        <template #header-actions>
            <div class="my-courses-header-actions">
                <v-btn v-if="action !== 'teaching_course_new_or_edit'" class="my-courses-header-action--new" icon="mdi-plus" size="small" variant="tonal" title="Fach anlegen" :disabled="isSavingCourse" @click="newCourse" />
                <v-btn
                    v-if="action !== 'teaching_course_new_or_edit'"
                    :icon="courses_view_variant === 'v1' ? 'mdi-view-grid-outline' : 'mdi-format-list-bulleted'"
                    size="small"
                    variant="tonal"
                    :disabled="isSavingCourse"
                    :title="courses_view_variant === 'v1' ? 'Neue Kartenansicht aktivieren' : 'Klassische Chip-Ansicht aktivieren'"
                    @click="toggleCoursesViewVariant" />
                <v-btn icon="mdi-eye-off-outline" size="small" variant="tonal" title="Ausblenden" :disabled="isSavingCourse" @click="show_my_courses = false" />
            </div>
        </template>
        <!-- ALLE KURSE ANZEIGEN -->
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <v-card tile flat color="transparent" class="w-100">
                    <div
                        class="d-flex flex-row flex-wrap ga-2 align-center w-100 my-courses-v1-wrap"
                        :class="{ 'my-courses-v1-group--disabled': isStudentDetailActive }"
                        v-if="courses_view_variant === 'v1'">
                        <v-chip-group v-model="selected_course_id" column class="my-courses-v1-chip-group">
                            <v-chip
                                v-for="course in courses"
                                :key="course.id"
                                :value="course.id"
                                :color="selected_course?.id === course.id ? 'primary' : 'secondary'"
                                :disabled="isStudentDetailActive"
                                class="my-courses-v1-chip">
                                {{ course.title }} ({{ courseClassesText(course) }})
                            </v-chip>
                        </v-chip-group>
                        <div class="my-courses-v1-mobile-grid">
                            <v-btn
                                v-for="course in courses"
                                :key="`mobile-course-${course.id}`"
                                class="my-courses-v1-mobile-button"
                                size="small"
                                :color="selected_course?.id === course.id ? 'primary' : 'secondary'"
                                :variant="selected_course?.id === course.id ? 'flat' : 'tonal'"
                                :disabled="isStudentDetailActive"
                                @click="selectCourse(course)">
                                {{ course.title }} ({{ courseClassesText(course) }})
                            </v-btn>
                        </div>
                    </div>

                    <div v-else class="my-courses-v2-grid" :class="{ 'my-courses-v2-grid--disabled': isStudentDetailActive }">
                        <button
                            v-for="course in courses"
                            :key="course.id"
                            type="button"
                            class="my-courses-v2-card"
                            :class="{ 'is-selected': selected_course?.id === course.id }"
                            :disabled="isStudentDetailActive"
                            @click="selectCourse(course)">
                            <div class="my-courses-v2-card__top">
                                <span class="my-courses-v2-card__primary-class">{{ primaryCourseClass(course) }}</span>
                                <span class="my-courses-v2-card__class-count">{{ courseClassesCountLabel(course) }}</span>
                            </div>
                            <div class="my-courses-v2-card__title">{{ course.title }}</div>
                            <div class="my-courses-v2-card__classes" v-if="secondaryCourseClasses(course).length">
                                <span
                                    v-for="classItem in secondaryCourseClasses(course)"
                                    :key="`course-${course.id}-class-${classItem}`"
                                    class="my-courses-v2-card__class-chip">
                                    {{ classItem }}
                                </span>
                            </div>
                        </button>
                    </div>

                    <div v-if="!courses.length" class="text-caption text-medium-emphasis">
                        Noch keine Fächer vorhanden.
                    </div>
                </v-card>

                <div class="w-100 d-flex flex-row justify-end my-courses-course-actions" v-if="action !== 'teaching_course_new_or_edit' || selected_course">
                    <div class="d-flex flex-row align-center ga-2 my-courses-course-actions__buttons">
                        <template v-if="selected_course">
                            <v-btn flat tile size="small" color="primary" icon="mdi-pencil" :disabled="isSavingCourse" @click="editCourse(selected_course)" v-if="delete_level == 0" />
                            <v-btn flat tile size="small" color="warning" icon="mdi-delete" :disabled="isSavingCourse" @click="delete_level++" v-if="delete_level == 0" />
                            <v-btn flat tile size="small" color="success" icon="mdi-delete-off" :disabled="isSavingCourse" @click="delete_level = 0" v-if="delete_level == 1" />
                            <v-btn flat tile size="small" color="error" icon="mdi-delete" :loading="isDeletingCourse" :disabled="isSavingCourse" @click="deleteCourse(selected_course)" v-if="delete_level == 1" />
                        </template>
                        <v-btn class="my-courses-course-actions__new" icon="mdi-plus" size="small" variant="tonal" title="Fach anlegen" :disabled="isSavingCourse" @click="newCourse" v-if="action !== 'teaching_course_new_or_edit'" />
                        <v-btn v-if="selected_course" size="small" variant="outlined" color="white" icon="mdi-close" title="Auswahl aufheben" :disabled="isSavingCourse" @click="clearSelectedCourse" />
                    </div>
                </div>
            </v-card-text>
        </v-card>
    </ItsGridBox>

    <!-- NEUER/EDIT KURS Dialog -->
    <v-dialog :model-value="action === 'teaching_course_new_or_edit'" persistent max-width="700">
        <v-card>
            <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                <v-icon size="18">mdi-invoice-list</v-icon>
                {{ data.id ? 'Fach ändern' : 'Neues Fach' }}
                <v-spacer />
                <v-btn icon="mdi-close" size="x-small" variant="text" :disabled="isSavingCourse" @click="abortNewCourse" />
            </v-card-title>
            <v-divider />
            <v-card-text style="max-height: 75vh; overflow-y: auto;" :style="isSavingCourse ? 'pointer-events:none; opacity:0.6' : ''">
                <v-form ref="form" v-model="is_valid" class="mb-4">
                    <div class="text-caption text-medium-emphasis mb-2">Bitte geben Sie die Felder ein (* = Pflichtfeld)</div>
                    <v-text-field autofocus v-model="data.title" label="Bezeichnung *" :rules="[required(), maxLength(255)]" />
                    <div class="text-caption text-medium-emphasis mt-3 mb-1">Benotungsschema *</div>
                    <div class="d-flex flex-wrap ga-1">
                        <v-btn
                            v-for="item in schemaItems"
                            :key="item.value"
                            :variant="data.teaching_schema_id === item.value ? 'flat' : 'tonal'"
                            :color="data.teaching_schema_id === item.value ? 'primary' : 'default'"
                            size="small"
                            @click="data.teaching_schema_id = data.teaching_schema_id === item.value ? null : item.value">
                            {{ item.title }}
                        </v-btn>
                    </div>

                    <div class="text-caption text-medium-emphasis mt-4 mb-1">Klassen auswählen</div>
                    <v-chip-group v-model="data.classes" multiple column>
                        <v-chip v-for="cls in classes" :key="cls" :value="cls" filter variant="outlined">
                            {{ cls }}
                        </v-chip>
                    </v-chip-group>
                    <div class="text-caption text-medium-emphasis mt-1" v-if="data?.classes?.length">Ausgewählt: {{ data.classes.join(', ') }}</div>
                </v-form>

                <!-- Schülerinnen -->
                <v-card variant="outlined" class="mt-2">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2 flex-wrap">
                        <v-icon size="18">mdi-account-check</v-icon>
                        Ausgewählte Schülerinnen
                        <v-chip v-if="activeSelectedStudentsCount" size="x-small" color="primary" variant="tonal">
                            {{ activeSelectedStudentsCount }}
                        </v-chip>
                        <div class="w-100 d-flex flex-wrap align-center justify-end ga-2 mt-1 course-edit-students-actions-row">
                            <v-btn-toggle
                                v-model="students_sort_mode"
                                mandatory
                                density="compact"
                                color="primary"
                                class="course-edit-students-sort-toggle">
                                <v-btn size="x-small" value="class_last_name" class="course-edit-students-sort-toggle-btn">Klasse, Name</v-btn>
                                <v-btn size="x-small" value="last_name_first_name" class="course-edit-students-sort-toggle-btn">Name</v-btn>
                            </v-btn-toggle>
                            <v-btn size="x-small" color="error" variant="tonal" prepend-icon="mdi-minus" @click="removeAllStudents">Alle entfernen</v-btn>
                            <v-btn size="x-small" color="primary" variant="tonal" prepend-icon="mdi-plus" @click="addAllStudents">Alle hinzufügen</v-btn>
                        </div>
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item v-for="student in sortedSelectedStudents" :key="student.id">
                                <div class="d-flex align-center ga-2 w-100 flex-wrap">
                                    <v-chip v-if="student.schoolclass || student.class" size="x-small" variant="tonal" color="primary">
                                        {{ student.schoolclass || student.class }}
                                    </v-chip>
                                    <div class="text-body-2" :class="studentNameClass(student)">{{ student.last_name }}, {{ student.first_name }}</div>
                                    <v-chip v-if="isStudentCanceled(student)" size="x-small" variant="tonal" color="warning">
                                        Storniert: {{ formatDateTime(student.canceled_at) }}
                                    </v-chip>
                                    <div v-if="!isStudentRemovable(student)" class="text-caption text-warning">
                                        {{ student.remove_block_reason || 'Entfernen nicht möglich, bitte stornieren.' }}
                                    </div>
                                    <v-spacer />
                                    <v-btn
                                        v-if="isStudentRemovable(student)"
                                        size="x-small"
                                        color="error"
                                        variant="tonal"
                                        prepend-icon="mdi-minus"
                                        @click="removeStudent(student)">
                                        Entfernen
                                    </v-btn>
                                    <v-btn
                                        v-else-if="isStudentCanceled(student)"
                                        size="x-small"
                                        color="success"
                                        variant="tonal"
                                        prepend-icon="mdi-undo"
                                        @click="uncancelStudent(student)">
                                        Storno aufheben
                                    </v-btn>
                                    <v-btn
                                        v-else
                                        size="x-small"
                                        color="warning"
                                        variant="tonal"
                                        prepend-icon="mdi-cancel"
                                        @click="cancelStudent(student)">
                                        Stornieren
                                    </v-btn>
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!sortedSelectedStudents.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine Schülerinnen ausgewählt.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <!-- Schülerinnen suchen (alle Klassen) -->
                <v-card variant="outlined" class="mt-4">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                        <v-icon size="18">mdi-account-search</v-icon>
                        Schülerinnen suchen
                        <v-chip v-if="student_search_results.length" size="x-small" color="secondary" variant="tonal">
                            {{ student_search_results.length }}
                        </v-chip>
                    </v-card-title>
                    <v-divider />
                    <v-card-text>
                        <div class="d-flex ga-2">
                            <v-text-field
                                v-model="student_search_string"
                                label="Name, Klasse oder E-Mail"
                                density="compact"
                                hide-details
                                variant="outlined"
                                clearable
                                @keyup.enter="searchStudents"
                                @click:clear="clearStudentSearch" />
                            <v-btn
                                color="primary"
                                variant="tonal"
                                icon="mdi-magnify"
                                :loading="student_search_loading"
                                @click="searchStudents" />
                        </div>
                        <v-list density="compact" class="mt-1 pa-0" v-if="student_search_results.length">
                            <v-list-item v-for="student in student_search_results" :key="student.id">
                                <div class="d-flex align-center ga-2 w-100">
                                    <v-chip v-if="student.class" size="x-small" variant="tonal" color="secondary">
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
                                        @click="addStudentFromSearch(student)">
                                        Hinzufügen
                                    </v-btn>
                                </div>
                            </v-list-item>
                        </v-list>
                        <div v-else-if="student_search_done" class="text-caption text-medium-emphasis mt-2">Keine Schülerinnen gefunden.</div>
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
                        <v-spacer />
                        <v-btn-toggle
                            v-model="students_sort_mode"
                            mandatory
                            density="compact"
                            color="primary"
                            class="course-edit-students-sort-toggle">
                            <v-btn size="x-small" value="class_last_name" class="course-edit-students-sort-toggle-btn">Klasse, Name</v-btn>
                            <v-btn size="x-small" value="last_name_first_name" class="course-edit-students-sort-toggle-btn">Name</v-btn>
                        </v-btn-toggle>
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
            </v-card-text>
            <v-divider />
            <v-card-actions>
                <v-btn color="warning" variant="tonal" :disabled="isSavingCourse" @click="abortNewCourse">Abbruch</v-btn>
                <v-spacer />
                <v-btn
                    color="success"
                    variant="tonal"
                    :loading="isSavingCourseDetails"
                    :disabled="isSavingCourse"
                    v-if="data.title && data?.classes?.length > 0 && data.teaching_schema_id"
                    @click="$refs.form.validate().then((v) => { if (v.valid) save(data) })">
                    {{ data.id ? 'Aktualisieren' : 'Speichern' }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { parseLocalDate } from '@/helpers/date'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
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
        this.loadCoursesViewVariant()
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
            courses_view_variant: 'v1',
            students_sort_mode: 'last_name_first_name',
            student_search_string: '',
            student_search_results: [],
            student_search_loading: false,
            student_search_done: false,
            saving_course_action: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, ['courses', 'classes', 'selected_course', 'selected_course_id', 'selected_course_student', 'show_my_courses']),
        schemaItems() {
            return (this.teachingStore?.schemas || []).map((s) => ({ title: s.name, value: s.id })).sort((a, b) => a.title.localeCompare(b.title))
        },
        filteredImport116Students() {
            const list = this.import116_students_local || []
            const selected = this.data?.students_info || []
            if (!selected.length) return [...list].sort((a, b) => this.compareStudentsBySelectedSort(a, b))

            const selectedEmails = new Set(selected.map((student) => (student.email || '').toString().trim().toLowerCase()).filter((email) => email))

            if (!selectedEmails.size) return [...list].sort((a, b) => this.compareStudentsBySelectedSort(a, b))

            return list.filter((student) => {
                const email = (student.email || '').toString().trim().toLowerCase()
                return !email || !selectedEmails.has(email)
            }).sort((a, b) => this.compareStudentsBySelectedSort(a, b))
        },
        sortedSelectedStudents() {
            const list = this.data?.students_info || []
            return [...list].sort((a, b) => {
                const canceledA = this.isStudentCanceled(a) ? 1 : 0
                const canceledB = this.isStudentCanceled(b) ? 1 : 0
                if (canceledA !== canceledB) return canceledA - canceledB

                return this.compareStudentsBySelectedSort(a, b)
            })
        },
        activeSelectedStudentsCount() {
            const list = this.data?.students_info || []
            const activeIds = new Set()
            list.forEach((student) => {
                if (!student?.id) return
                if (this.isStudentCanceled(student)) return
                if (student?.deleted_at) return
                activeIds.add(String(student.id))
            })
            return activeIds.size
        },
        isStudentDetailActive() {
            return !!this.selected_course_student
        },
        isSavingCourse() {
            return this.saving_course_action !== null
        },
        isSavingCourseDetails() {
            return this.saving_course_action === 'save'
        },
        isDeletingCourse() {
            return this.saving_course_action === 'delete'
        },
    },

    watch: {
        selected_course_id(newId) {
            const course = this.courses.find((c) => c.id === newId) || null
            this.selectCourse(course)
        },
        'courseStore.pending_new_course_token'(token) {
            if (!token) return
            this.newCourse()
        },
        async 'courseStore.pending_edit_course_id'(id) {
            if (!id) return
            const course = this.courses.find((c) => c.id === id) || null
            this.courseStore.pending_edit_course_id = null
            if (course) {
                await this.editCourse(course)
            }
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
        async runCourseMutation(action, callback) {
            if (this.isSavingCourse) {
                return false
            }

            this.saving_course_action = action
            await this.$nextTick()

            try {
                return await callback()
            } finally {
                this.saving_course_action = null
            }
        },
        clearSelectedCourse() {
            this.selectCourse(null)
            const courseDateStore = useCourseDateStore()
            courseDateStore.selected_courseDate = null
        },
        loadCoursesViewVariant() {
            const storage = this.getBrowserStorage()
            if (!storage) return

            const savedVariant = storage.getItem('teaching_my_courses_view_variant')
            if (savedVariant === 'v1' || savedVariant === 'v2') {
                this.courses_view_variant = savedVariant
            }
        },
        getBrowserStorage() {
            if (typeof window === 'undefined') return null
            if (!window.localStorage) return null
            return window.localStorage
        },
        setCoursesViewVariant(variant) {
            this.courses_view_variant = variant === 'v2' ? 'v2' : 'v1'
            const storage = this.getBrowserStorage()
            if (!storage) return
            storage.setItem('teaching_my_courses_view_variant', this.courses_view_variant)
        },
        toggleCoursesViewVariant() {
            this.setCoursesViewVariant(this.courses_view_variant === 'v1' ? 'v2' : 'v1')
        },
        courseClasses(course) {
            const classes = Array.isArray(course?.classes) ? course.classes : []
            return classes.map((item) => String(item || '').trim()).filter((item) => item !== '')
        },
        courseClassesText(course) {
            const classes = this.courseClasses(course)
            return classes.length ? classes.join(', ') : 'Keine Klasse'
        },
        primaryCourseClass(course) {
            const classes = this.courseClasses(course)
            return classes[0] || 'Ohne Klasse'
        },
        secondaryCourseClasses(course) {
            const classes = this.courseClasses(course)
            return classes.slice(1)
        },
        courseClassesCountLabel(course) {
            const count = this.courseClasses(course).length
            if (count === 1) {
                return '1 Klasse'
            }

            return `${count} Klassen`
        },
        async searchStudents() {
            if (!this.student_search_string?.trim()) return
            this.student_search_loading = true
            this.student_search_done = false
            try {
                const response = await axios.get('/api/admin/teaching/search116', {
                    params: { search_string: this.student_search_string.trim() },
                })
                this.student_search_results = response?.data?.data || []
                this.student_search_done = true
            } finally {
                this.student_search_loading = false
            }
        },

        clearStudentSearch() {
            this.student_search_string = ''
            this.student_search_results = []
            this.student_search_done = false
        },

        addStudentFromSearch(student) {
            this.addStudent(student)
            // Remove from search results so the list stays clean
            this.student_search_results = this.student_search_results.filter((s) => s.id !== student.id)
        },

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
            const toAdd = this.filteredImport116Students
            if (!toAdd.length) return

            toAdd.slice().forEach((student) => {
                this.addStudent(student)
            })
        },

        removeAllStudents() {
            if (!this.data) return
            this.courseStore.ensureCourseStudentCollections(this.data)

            if (!Array.isArray(this.data.students_info) || !this.data.students_info.length) return

            let canceledCount = 0
            this.data.students_info.slice().forEach((student) => {
                if (this.isStudentRemovable(student)) {
                    this.removeStudent(student)
                    return
                }

                if (!this.isStudentCanceled(student)) {
                    this.cancelStudent(student)
                    canceledCount++
                }
            })

            if (canceledCount > 0) {
                this.notifyWarning(`${canceledCount} Schüler:innen konnten nicht entfernt werden und wurden stattdessen storniert.`)
            }
        },

        addStudent(student) {
            if (!this.data) return
            this.courseStore.ensureCourseStudentCollections(this.data)

            const email = (student.email || '').toString().trim().toLowerCase()

            // Check for duplicates by ID and by email
            const isDuplicateById = this.data.students.some(id => String(id) === String(student.id))
            const isDuplicateByEmail = email && this.data.students_info.some(s => {
                const existingEmail = (s.email || '').toString().trim().toLowerCase()
                return existingEmail && existingEmail === email
            })

            if (!isDuplicateById && !isDuplicateByEmail) {
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
            if (this.data.students.includes(student.id)) return true
            const email = (student.email || '').toString().trim().toLowerCase()
            if (!email) return false
            return this.data.students_info.some(s => {
                const existingEmail = (s.email || '').toString().trim().toLowerCase()
                return existingEmail && existingEmail === email
            })
        },

        removeStudent(student) {
            if (!this.data) return
            this.courseStore.ensureCourseStudentCollections(this.data)
            if (!this.isStudentRemovable(student)) {
                this.notifyWarning(student.remove_block_reason || 'Entfernen ist nicht möglich. Bitte stattdessen stornieren.')
                return
            }

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

        cancelStudent(student) {
            if (!this.data) return
            this.courseStore.ensureCourseStudentCollections(this.data)

            const idx = this.data.students_info.findIndex((s) => String(s.id) === String(student.id))
            if (idx === -1) return

            this.data.students_info[idx].canceled_at = new Date().toISOString()
        },

        uncancelStudent(student) {
            if (!this.data) return
            this.courseStore.ensureCourseStudentCollections(this.data)

            const idx = this.data.students_info.findIndex((s) => String(s.id) === String(student.id))
            if (idx === -1) return

            this.data.students_info[idx].canceled_at = null
        },

        isStudentRemovable(student) {
            if (!student) return true
            return student.is_removable !== false
        },

        isStudentCanceled(student) {
            if (!student) return false
            return !!student?.canceled_at || !!student?.deleted_at
        },
        studentNameClass(student) {
            return this.isStudentCanceled(student) ? 'student-name--canceled' : ''
        },
        studentClassValue(student) {
            return (student?.schoolclass || student?.class || '').toString()
        },
        compareStudentsBySelectedSort(a, b) {
            const lastA = (a?.last_name || '').toString()
            const lastB = (b?.last_name || '').toString()
            const firstA = (a?.first_name || '').toString()
            const firstB = (b?.first_name || '').toString()
            const classA = this.studentClassValue(a)
            const classB = this.studentClassValue(b)

            if (this.students_sort_mode === 'last_name_first_name') {
                const lastCmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
                if (lastCmp !== 0) return lastCmp
                const firstCmp = firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
                if (firstCmp !== 0) return firstCmp
                return classA.localeCompare(classB, 'de', { numeric: true, sensitivity: 'base' })
            }

            const classCmp = classA.localeCompare(classB, 'de', { numeric: true, sensitivity: 'base' })
            if (classCmp !== 0) return classCmp
            const lastCmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
            if (lastCmp !== 0) return lastCmp
            return firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
        },

        formatDateTime(value) {
            if (!value) return '-'
            const date = new Date(value)
            if (Number.isNaN(date.getTime())) return value
            return date.toLocaleDateString('de-DE')
        },

        notifyWarning(message) {
            const notification = useNotificationStore()
            notification.notify({
                status: 409,
                message,
                type: 'warning',
                timeout: 3000,
            })
        },

        async save(data) {
            if (!data?.teaching_schema_id) return

            await this.runCourseMutation('save', async () => {
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
            })
        },

        selectCourse(course) {
            if (this.action === 'teaching_course_new_or_edit') {
                return
            }
            if (this.isStudentDetailActive) {
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

            // Check if there's already a selected date that belongs to this course
            const currentSelectedDate = courseDateStore.selected_courseDate
            if (currentSelectedDate?.id) {
                const dateExistsInCourse = dates.some((d) => d?.id === currentSelectedDate.id)
                if (dateExistsInCourse) {
                    // Keep the currently selected date since it belongs to this course
                    return
                }
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
            this.clearStudentSearch()
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
        async editCourse(course) {
            this.selected_course_student = null
            this.action_2 = ''
            this.import116_students_local = []
            this.clearStudentSearch()

            let freshCourse = course
            if (course?.id) {
                const refreshed = await this.courseStore.refreshCourseById(course.id)
                if (refreshed) {
                    freshCourse = refreshed
                }
            }

            this.selected_course = freshCourse
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
            this.clearStudentSearch()
            this.action = ''
        },
        async deleteCourse(course) {
            await this.runCourseMutation('delete', async () => {
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
            })
        },
    },
}
</script>

<style scoped>
.student-name--canceled {
    text-decoration: line-through;
    opacity: 0.75;
}

.my-courses-header-actions,
.my-courses-course-actions__buttons {
    display: flex;
    align-items: center;
    gap: 8px;
}

.my-courses-course-actions__new {
    display: inline-flex;
}

.my-courses-v1-chip-group {
    display: none;
}

.my-courses-v1-mobile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    width: 100%;
}

.my-courses-v1-mobile-button {
    min-width: 0 !important;
    width: 100%;
}

.my-courses-v1-mobile-button :deep(.v-btn__content) {
    display: block;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.my-courses-course-actions {
    justify-content: center !important;
    padding-top: 4px;
}

.my-courses-course-actions__buttons {
    justify-content: center;
    width: 100%;
}

.my-courses-v2-grid {
    width: 100%;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 10px;
}

.my-courses-v2-card {
    border: 1px solid rgba(37, 99, 235, 0.26);
    border-radius: 14px;
    background: linear-gradient(160deg, rgba(237, 244, 255, 0.96), rgba(220, 235, 255, 0.92));
    padding: 12px;
    text-align: left;
    cursor: pointer;
    transition: transform 0.12s ease, box-shadow 0.12s ease, border-color 0.12s ease;
}

.my-courses-v2-card:disabled {
    cursor: not-allowed;
    opacity: 0.55;
    transform: none;
    box-shadow: none;
}

.my-courses-v1-group--disabled,
.my-courses-v2-grid--disabled {
    pointer-events: none;
}

.my-courses-v1-chip:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

.my-courses-v2-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(30, 64, 175, 0.14);
    border-color: rgba(37, 99, 235, 0.42);
}

.my-courses-v2-card.is-selected {
    border-color: rgba(29, 78, 216, 0.78);
    box-shadow: 0 14px 26px rgba(29, 78, 216, 0.22);
    background: linear-gradient(160deg, rgba(219, 234, 254, 0.98), rgba(191, 219, 254, 0.96));
}

.my-courses-v2-card__top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.my-courses-v2-card__primary-class {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px 9px;
    border-radius: 999px;
    background: rgba(30, 64, 175, 0.16);
    color: #1e3a8a;
    font-weight: 800;
    font-size: 0.76rem;
    letter-spacing: 0.04em;
}

.my-courses-v2-card__class-count {
    font-size: 0.72rem;
    color: rgba(30, 58, 138, 0.86);
    font-weight: 700;
}

.my-courses-v2-card__title {
    margin-top: 8px;
    color: #0f172a;
    font-size: 0.98rem;
    font-weight: 700;
    line-height: 1.25;
}

.my-courses-v2-card__classes {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.my-courses-v2-card__class-chip {
    display: inline-flex;
    padding: 2px 8px;
    border-radius: 999px;
    background: rgba(59, 130, 246, 0.16);
    border: 1px solid rgba(37, 99, 235, 0.3);
    color: #1e40af;
    font-weight: 650;
    font-size: 0.74rem;
    line-height: 1.2;
}

.course-edit-students-sort-toggle-btn {
    text-transform: none;
    letter-spacing: 0;
}

@media (max-width: 900px) {
    .course-edit-students-sort-toggle {
        width: 100%;
        margin-top: 4px;
    }

    .course-edit-students-sort-toggle-btn {
        flex: 1 1 0;
    }
}

@media (max-width: 700px) {
    .my-courses-header-action--new {
        display: none;
    }

    .my-courses-v1-wrap,
    .my-courses-v1-chip-group {
        width: 100%;
    }

    .my-courses-v2-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
