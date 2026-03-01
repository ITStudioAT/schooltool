<template>
    <v-col cols="12" class="pb-1">
        <v-sheet rounded="xl" class="overview-header" :class="{ 'is-locked': isControlLocked }">
            <div class="overview-header__top">
                <div>
                    <div class="overview-header__eyebrow">Übersicht</div>
                    <h2 class="overview-header__title">Kurs- und Leistungsansicht</h2>
                    <p class="overview-header__subtitle">
                        {{ selectedCourseDisplay }}
                    </p>
                </div>
                <div class="overview-header__status">
                    <v-chip size="small" variant="flat" color="primary" prepend-icon="mdi-account-school" v-if="selected_course">
                        Kurs aktiv
                    </v-chip>
                    <v-chip size="small" variant="tonal" color="secondary" prepend-icon="mdi-view-dashboard-outline" v-else>
                        Bitte Fach auswählen
                    </v-chip>
                    <v-chip size="small" variant="tonal" :color="action_2 === 'course_student_view' ? 'warning' : 'success'" prepend-icon="mdi-eye">
                        {{ action_2 === 'course_student_view' ? 'Schüler:innen-Detail aktiv' : 'Listenansicht aktiv' }}
                    </v-chip>
                </div>
            </div>

            <div class="overview-header__controls">
                <v-btn
                    rounded="lg"
                    class="overview-toggle-btn"
                    :color="show_my_courses ? 'success' : 'secondary'"
                    :variant="show_my_courses ? 'flat' : 'tonal'"
                    :disabled="isControlLocked"
                    prepend-icon="mdi-book-open-variant"
                    @click="toggleMyCourses">
                    Meine Fächer
                    <v-icon size="16" :icon="toggleIcon(show_my_courses)" class="ml-2" />
                </v-btn>

                <v-btn
                    v-if="selected_course"
                    rounded="lg"
                    class="overview-toggle-btn"
                    :color="show_students ? 'success' : 'secondary'"
                    :variant="show_students ? 'flat' : 'tonal'"
                    :disabled="isControlLocked"
                    prepend-icon="mdi-account-group"
                    @click="show_students = !show_students">
                    Schüler:innen
                    <v-icon size="16" :icon="toggleIcon(show_students)" class="ml-2" />
                </v-btn>

                <v-btn
                    v-if="selected_course"
                    rounded="lg"
                    class="overview-toggle-btn"
                    :color="show_infos ? 'success' : 'secondary'"
                    :variant="show_infos ? 'flat' : 'tonal'"
                    :disabled="isControlLocked"
                    prepend-icon="mdi-information-outline"
                    @click="show_infos = !show_infos">
                    Infos
                    <v-icon size="16" :icon="toggleIcon(show_infos)" class="ml-2" />
                </v-btn>

                <v-btn
                    v-if="selected_course"
                    rounded="lg"
                    class="overview-toggle-btn"
                    :color="show_works ? 'success' : 'secondary'"
                    :variant="show_works ? 'flat' : 'tonal'"
                    :disabled="isControlLocked"
                    prepend-icon="mdi-file-document-edit-outline"
                    @click="show_works = !show_works">
                    Arbeiten
                    <v-icon size="16" :icon="toggleIcon(show_works)" class="ml-2" />
                </v-btn>

                <v-btn
                    v-if="selected_course"
                    rounded="lg"
                    class="overview-toggle-btn"
                    :color="show_dates ? 'success' : 'secondary'"
                    :variant="show_dates ? 'flat' : 'tonal'"
                    :disabled="isControlLocked"
                    prepend-icon="mdi-calendar-clock-outline"
                    @click="show_dates = !show_dates">
                    Termine
                    <v-icon size="16" :icon="toggleIcon(show_dates)" class="ml-2" />
                </v-btn>
            </div>
        </v-sheet>
    </v-col>

    <v-col cols="12" md="6" lg="7" xl="4" v-if="show_my_courses || show_students">
        <v-row v-if="show_my_courses" :style="contentLockStyle">
            <v-col>
                <MyCourses />
            </v-col>
        </v-row>

        <v-row v-if="show_my_courses && show_timetable && !selected_course && action != 'teaching_course_new_or_edit'" :style="contentLockStyle">
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

        <v-row v-if="show_works">
            <v-col>
                <CourseWorks />
            </v-col>
        </v-row>

        <v-row v-if="show_dates">
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
        selectedCourseDisplay() {
            const course = this.selected_course
            if (!course) {
                return 'Kein Fach ausgewählt. Wählen Sie ein Fach, um Schüler:innen, Termine und Arbeiten einzublenden.'
            }

            const title = course.title || 'Fach'
            const classes = Array.isArray(course.classes) && course.classes.length ? ` (${course.classes.join(', ')})` : ''
            return `${title}${classes}`
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
        toggleIcon(isVisible) {
            return isVisible ? 'mdi-eye' : 'mdi-eye-off'
        },
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

.overview-header__top {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: space-between;
    align-items: flex-start;
}

.overview-header__eyebrow {
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(30, 41, 59, 0.72);
}

.overview-header__title {
    margin-top: 4px;
    font-size: clamp(1rem, 1.9vw, 1.22rem);
    line-height: 1.2;
    font-weight: 700;
    color: #0f172a;
}

.overview-header__subtitle {
    margin-top: 6px;
    max-width: 78ch;
    font-size: 0.86rem;
    color: rgba(30, 41, 59, 0.86);
}

.overview-header__status {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

.overview-header__controls {
    margin-top: 12px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.overview-toggle-btn {
    min-height: 44px;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 600;
}

.overview-header.is-locked {
    opacity: 0.68;
}

@media (max-width: 960px) {
    .overview-toggle-btn {
        flex: 1 1 calc(50% - 8px);
    }
}

@media (max-width: 620px) {
    .overview-toggle-btn {
        flex-basis: 100%;
    }
}
</style>
