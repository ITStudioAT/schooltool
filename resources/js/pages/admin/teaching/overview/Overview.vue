<template>
    <v-col cols="12" class="pb-1">
        <div class="teaching-overview-toolbar-width" :class="toolbarWidthClass">
            <section class="teaching-overview-toolbar" :class="{ 'is-locked': isControlLocked }">
                <v-btn-toggle
                    v-model="functionalPanelSelection"
                    class="teaching-overview-panel-switcher"
                    color="primary">
                    <v-btn
                        v-for="panel in functionalPanels"
                        :key="panel.id"
                        :data-testid="`teaching-overview-panel-${panel.id}`"
                        class="teaching-overview-toolbar-btn"
                        :value="panel.id"
                        :disabled="isControlLocked"
                        :prepend-icon="panel.icon">
                        {{ panel.label }}
                    </v-btn>
                </v-btn-toggle>
            </section>
        </div>
    </v-col>

    <v-col cols="12" md="6" lg="7" xl="4" v-if="show_students || (show_timetable && !selected_course)">
        <v-row v-if="show_timetable && !selected_course && action != 'teaching_course_new_or_edit'" :style="contentLockStyle">
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


    <v-col cols="12" md="6" lg="7" xl="4" v-if="(show_infos || show_dates || show_works) && action != 'teaching_course_new_or_edit'" :style="contentLockStyle">
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

    <v-col cols="12" v-if="(show_attendance || show_performances) && action != 'teaching_course_new_or_edit'" :style="contentLockStyle">
        <div v-if="semesterCount === 2" class="d-flex align-center ga-2 mb-2">
            <v-btn-toggle v-model="activeSemester" mandatory density="compact" color="primary">
                <v-btn :value="1" size="small">Sem 1</v-btn>
                <v-btn :value="2" size="small">Sem 2</v-btn>
                <v-btn :value="3" size="small">Sem 1+2</v-btn>
            </v-btn-toggle>
        </div>
        <v-row v-if="show_attendance">
            <v-col>
                <AttendanceMatrix
                    :selected-course="selected_course"
                    :active-semester="activeSemester"
                    :semester-count="semesterCount"
                    :sem2-start-date="sem2StartDate" />
            </v-col>
        </v-row>
        <v-row v-if="show_performances" :class="show_attendance ? 'mt-n6' : ''">
            <v-col>
                <PerformancesDummy
                    :selected-course="selected_course"
                    :active-semester="activeSemester"
                    :semester-count="semesterCount"
                    :sem2-start-date="sem2StartDate" />
            </v-col>
        </v-row>
    </v-col>

    <!-- MyCourses nur für den Neu/Bearbeiten-Dialog -->
    <div style="display:none">
        <MyCourses />
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import MyCourses from './components/MyCourses.vue'
import CourseStudents from './components/CourseStudents.vue'
import CourseStudent from './components/CourseStudent.vue'
import CourseInfos from './components/CourseInfos.vue'
import CourseDates from './components/CourseDates.vue'
import CourseWorks from './components/CourseWorks.vue'
import MyTimetable from './components/MyTimetable.vue'
import AttendanceMatrix from '../more/components/AttendanceMatrix.vue'
import PerformancesDummy from '../more/components/PerformancesDummy.vue'

export default {
    components: { MyCourses, CourseStudents, CourseStudent, CourseInfos, CourseDates, CourseWorks, MyTimetable, AttendanceMatrix, PerformancesDummy },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.schoolHourStore = useSchoolHourStore()
        this.teachingStore = useTeachingStore()
        this.selected_course_student = null
        this.action_2 = ''
        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
        }
        this.activeSemester = Number(this.config?.user?.teaching_active_semester) || 1
        await this.refreshOverviewData()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            courseStore: null,
            schoolHourStore: null,
            teachingStore: null,
            activeSemester: 1,
            _urlPanelRestored: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, [
            'selected_course',
            'selected_course_id',
            'show_timetable',
            'show_students',
            'show_infos',
            'show_works',
            'show_dates',
            'show_attendance',
            'show_performances',
            'selected_course_student',
        ]),
        isControlLocked() {
            return this.action != '' || this.action_2 != ''
        },
        contentLockStyle() {
            return this.action_2 == 'course_student_view' ? 'pointer-events:none; opacity:0.6' : ''
        },
        hasLeftOverviewColumn() {
            return this.show_students || (this.show_timetable && !this.selected_course)
        },
        hasMiddleOverviewColumn() {
            return false
        },
        hasRightOverviewColumn() {
            return (this.show_infos || this.show_dates || this.show_works) && this.action != 'teaching_course_new_or_edit'
        },
        toolbarWidthClass() {
            if (this.hasLeftOverviewColumn && this.hasMiddleOverviewColumn && this.hasRightOverviewColumn) {
                return 'toolbar-width-xl-12'
            }
            if (this.hasLeftOverviewColumn && this.hasRightOverviewColumn) {
                return 'toolbar-width-xl-8'
            }
            if (this.hasLeftOverviewColumn && this.hasMiddleOverviewColumn) {
                return 'toolbar-width-xl-7'
            }
            if (this.hasMiddleOverviewColumn && this.hasRightOverviewColumn) {
                return 'toolbar-width-xl-8'
            }
            if (this.hasLeftOverviewColumn) {
                return 'toolbar-width-xl-4'
            }
            if (this.hasRightOverviewColumn) {
                return 'toolbar-width-xl-4'
            }
            if (this.hasMiddleOverviewColumn) {
                return 'toolbar-width-xl-3'
            }
            return 'toolbar-width-xl-12'
        },
        teachingSchemas() {
            return this.config?.user?.teaching_schemas || this.teachingStore?.settings?.teaching_schemas || []
        },
        selectedSchema() {
            const schemaId = this.selected_course?.teaching_schema_id
            if (!schemaId) return null
            return this.teachingSchemas.find((s) => String(s.id) === String(schemaId)) || null
        },
        semesterCount() {
            return Number(this.selectedSchema?.grading?.semester_count) || 1
        },
        sem2StartDate() {
            return this.config?.selected_schoolyear?.sem_2_start || this.config?.user?.teaching_count_for_semester_2_date || null
        },
        functionalPanels() {
            const panels = []
            if (this.selected_course) {
                panels.push({ id: 'students', label: 'Schüler:innen', icon: 'mdi-account-group' })
                panels.push({ id: 'infos', label: 'Infos', icon: 'mdi-information-outline' })
                panels.push({ id: 'works', label: 'Arbeiten', icon: 'mdi-file-document-edit-outline' })
                panels.push({ id: 'dates', label: 'Termine', icon: 'mdi-calendar-clock-outline' })
                panels.push({ id: 'attendance', label: 'Anwesenheit', icon: 'mdi-table' })
                panels.push({ id: 'performances', label: 'Leistungen', icon: 'mdi-chart-line' })
            }
            return panels
        },
        functionalPanelSelection: {
            get() {
                if (!this.selected_course) return undefined
                if (this.show_students) return 'students'
                if (this.show_infos) return 'infos'
                if (this.show_works) return 'works'
                if (this.show_dates) return 'dates'
                if (this.show_attendance) return 'attendance'
                if (this.show_performances) return 'performances'
                return undefined
            },
            set(value) {
                this.show_students = value === 'students'
                this.show_infos = value === 'infos'
                this.show_works = value === 'works'
                this.show_dates = value === 'dates'
                this.show_attendance = value === 'attendance'
                this.show_performances = value === 'performances'
                if (!value) {
                    this.action_2 = ''
                    this.selected_course_student = null
                }
                if (value && this.$route && this.$router) {
                    this.$router.replace({ path: this.$route.path, query: { ...this.$route.query, panel: value } }).catch(() => {})
                }
            },
        },
    },

    watch: {
        selected_course(newCourse) {
            if (newCourse && !this._urlPanelRestored) {
                const urlPanel = this.$route?.query?.panel
                const validPanels = ['students', 'infos', 'works', 'dates', 'attendance', 'performances']
                this._urlPanelRestored = true
                if (urlPanel && validPanels.includes(urlPanel)) {
                    this.show_students = urlPanel === 'students'
                    this.show_infos = urlPanel === 'infos'
                    this.show_works = urlPanel === 'works'
                    this.show_dates = urlPanel === 'dates'
                    this.show_attendance = urlPanel === 'attendance'
                    this.show_performances = urlPanel === 'performances'
                    return
                }
            }
            this._urlPanelRestored = true
            this.show_students = true
            this.show_infos = false
            this.show_works = false
            this.show_dates = false
            this.show_attendance = false
            this.show_performances = false
        },
        activeSemester(val) {
            if (val !== this.config?.user?.teaching_active_semester) {
                this.teachingStore?.saveActiveSemester(val)
            }
        },
        'config.user.teaching_active_semester'(val) {
            if (val) this.activeSemester = Number(val) || 1
        },
    },

    methods: {
        async refreshOverviewData() {
            await this.courseStore.index()
            await this.schoolHourStore.index()
        },
    },
}
</script>

<style scoped>
.teaching-overview-toolbar {
    width: 100%;
    display: flex;
    justify-content: flex-start;
    gap: 8px;
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
}

.teaching-overview-panel-switcher {
    flex-wrap: wrap;
    row-gap: 6px;
    height: auto !important;
}

.teaching-overview-toolbar-btn {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
    height: 40px !important;
}

.teaching-overview-toolbar.is-locked {
    opacity: 0.68;
}

.teaching-overview-toolbar-width {
    width: 100%;
}

@media (min-width: 1920px) {
    .teaching-overview-toolbar-width.toolbar-width-xl-12 {
        width: 100%;
    }
    .teaching-overview-toolbar-width.toolbar-width-xl-9 {
        width: 75%;
    }
    .teaching-overview-toolbar-width.toolbar-width-xl-8 {
        width: 66.667%;
    }
    .teaching-overview-toolbar-width.toolbar-width-xl-7 {
        width: 58.333%;
    }
    .teaching-overview-toolbar-width.toolbar-width-xl-5 {
        width: 41.667%;
    }
    .teaching-overview-toolbar-width.toolbar-width-xl-4 {
        width: 33.333%;
    }
    .teaching-overview-toolbar-width.toolbar-width-xl-3 {
        width: 25%;
    }
}
</style>
