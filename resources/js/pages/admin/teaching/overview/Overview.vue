<template>
    <v-col cols="12" class="pb-1">
        <v-sheet rounded="xl" class="overview-header" :class="{ 'is-locked': isControlLocked }">
            <section class="overview-dummy-submenu">
                <v-btn-toggle
                    v-model="functionalPanelSelection"
                    multiple
                    class="overview-dummy-panel-switcher"
                    color="primary"
                    divided>
                    <v-btn
                        v-for="panel in functionalPanels"
                        :key="panel.id"
                        class="overview-dummy-toolbar-btn"
                        :value="panel.id"
                        :disabled="isControlLocked"
                        :prepend-icon="panel.icon">
                        {{ panel.label }}
                    </v-btn>
                </v-btn-toggle>
            </section>
        </v-sheet>
    </v-col>

    <v-col cols="12" md="6" lg="7" xl="4" v-if="show_my_courses || show_students">
        <v-row v-if="show_my_courses" :style="contentLockStyle">
            <v-col>
                <MyCourses />
            </v-col>
        </v-row>

        <v-row v-if="show_my_courses && show_timetable && !selected_course && action != 'teaching_course_new_or_edit'" :style="contentLockStyle" class="mt-n6">
            <v-col>
                <MyTimetable />
            </v-col>
        </v-row>

        <v-row v-if="action != 'teaching_course_new_or_edit' && show_students && action_2 != 'course_student_view'">
            <v-col>
                <CourseStudents />
            </v-col>
        </v-row>

        <v-row v-if="action_2 == 'course_student_view' && action != 'teaching_course_new_or_edit'">
            <v-col>
                <CourseStudent />
            </v-col>
        </v-row>
    </v-col>

    <v-col cols="12" md="6" lg="5" xl="3" v-if="show_my_courses && show_my_infos && !selected_course && action != 'teaching_course_new_or_edit'">
        <v-row>
            <v-col>
                <MyInfos />
            </v-col>
        </v-row>
    </v-col>

    <v-col cols="12" md="6" lg="12" xl="5" v-if="(show_infos || show_dates || show_works) && action != 'teaching_course_new_or_edit'" :style="contentLockStyle">
        <v-row v-if="show_infos">
            <v-col>
                <CourseInfos />
            </v-col>
        </v-row>

        <v-row v-if="show_works" class="mt-n6">
            <v-col>
                <CourseWorks />
            </v-col>
        </v-row>

        <v-row v-if="show_dates" class="mt-n6">
            <v-col>
                <CourseDates />
            </v-col>
        </v-row>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import MyCourses from './components/MyCourses.vue'
import CourseStudents from './components/CourseStudents.vue'
import CourseStudent from './components/CourseStudent.vue'
import CourseInfos from './components/CourseInfos.vue'
import CourseDates from './components/CourseDates.vue'
import CourseWorks from './components/CourseWorks.vue'
import MyInfos from './components/MyInfos.vue'
import MyTimetable from './components/MyTimetable.vue'

export default {
    components: { MyCourses, CourseStudents, CourseStudent, CourseInfos, CourseDates, CourseWorks, MyInfos, MyTimetable },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.selected_course = null
        this.selected_course_id = null
        this.selected_course_student = null
        this.action_2 = ''
        this.show_my_courses = true
        await this.courseStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            courseStore: null,
            selected_course_old: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, [
            'selected_course',
            'selected_course_id',
            'show_my_courses',
            'show_timetable',
            'show_my_infos',
            'show_students',
            'show_infos',
            'show_works',
            'show_dates',
            'selected_course_student',
        ]),
        isControlLocked() {
            return this.action != '' || this.action_2 != ''
        },
        contentLockStyle() {
            return this.action_2 == 'course_student_view' ? 'pointer-events:none; opacity:0.6' : ''
        },
        functionalPanels() {
            const panels = [{ id: 'my_courses', label: 'Meine Fächer', icon: 'mdi-book-open-variant' }]
            if (this.selected_course) {
                panels.push({ id: 'students', label: 'Schüler:innen', icon: 'mdi-account-group' })
                panels.push({ id: 'infos', label: 'Infos', icon: 'mdi-information-outline' })
                panels.push({ id: 'works', label: 'Arbeiten', icon: 'mdi-file-document-edit-outline' })
                panels.push({ id: 'dates', label: 'Termine', icon: 'mdi-calendar-clock-outline' })
            }
            return panels
        },
        functionalPanelSelection: {
            get() {
                const activePanels = []
                if (this.show_my_courses) {
                    activePanels.push('my_courses')
                }
                if (this.selected_course && this.show_students) {
                    activePanels.push('students')
                }
                if (this.selected_course && this.show_infos) {
                    activePanels.push('infos')
                }
                if (this.selected_course && this.show_works) {
                    activePanels.push('works')
                }
                if (this.selected_course && this.show_dates) {
                    activePanels.push('dates')
                }
                return activePanels
            },
            set(value) {
                const selectedPanels = Array.isArray(value) ? value : []
                this.syncFunctionalPanelSelection(selectedPanels)
            },
        },
    },

    watch: {
        selected_course(newCourse) {
            if (newCourse) {
                this.show_my_courses = false
                this.show_infos = true
            }
        },
    },

    methods: {
        toggleMyCourses() {
            const next = !this.show_my_courses
            this.show_my_courses = next
            if (next) {
                this.action_2 = ''
                this.selected_course = null
                this.selected_course_id = null
                this.selected_course_student = null
                this.show_infos = false
            }
        },
        toggleFunctionalPanel(panel) {
            if (this.isControlLocked) {
                return
            }

            if (panel === 'my_courses') {
                this.toggleMyCourses()
                return
            }
            if (panel === 'students' && this.selected_course) {
                this.show_students = !this.show_students
                return
            }
            if (panel === 'infos' && this.selected_course) {
                this.show_infos = !this.show_infos
                return
            }
            if (panel === 'works' && this.selected_course) {
                this.show_works = !this.show_works
                return
            }
            if (panel === 'dates' && this.selected_course) {
                this.show_dates = !this.show_dates
            }
        },
        syncFunctionalPanelSelection(nextSelection) {
            const requestedPanels = new Set(nextSelection)
            const currentPanels = new Set(this.functionalPanelSelection)
            this.functionalPanels.forEach((panel) => {
                const isActive = currentPanels.has(panel.id)
                const shouldBeActive = requestedPanels.has(panel.id)
                if (isActive !== shouldBeActive) {
                    this.toggleFunctionalPanel(panel.id)
                }
            })
        },
        toggleShowMyCourses() {
            if (this.show_my_courses) {
                this.selected_course_old = { ...this.selected_course }
            }
            this.show_my_courses = !this.show_my_courses
            if (this.show_my_courses) {
                this.selected_course = { ...this.selected_course_old }
            }
        },
    },
}
</script>

<style scoped>
.overview-header {
    border: 1px solid rgba(148, 163, 184, 0.33);
    background:
        radial-gradient(circle at top right, rgba(125, 211, 252, 0.24), transparent 45%),
        linear-gradient(132deg, rgba(248, 250, 252, 0.97), rgba(240, 249, 255, 0.95));
    padding: 14px;
}

.overview-dummy-submenu {
    display: flex;
    justify-content: flex-start;
    gap: 8px;
    border-radius: 16px;
    border: 1px solid rgba(16, 38, 58, 0.09);
    background: rgba(255, 255, 255, 0.78);
    padding: 10px;
    margin-top: 12px;
}

.overview-dummy-panel-switcher {
    flex-wrap: wrap;
    row-gap: 8px;
}

.overview-dummy-toolbar-btn {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
}

.overview-header.is-locked {
    opacity: 0.68;
}
</style>
