<template>
    <v-col cols="12" class="pb-1">
        <div class="teaching-overview-toolbar-width">
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


    <v-col cols="12" md="6" lg="7" xl="4" v-if="(show_students || show_infos || show_dates || show_curriculum || show_works || show_print) && action != 'teaching_course_new_or_edit'" :style="contentLockStyle">
        <v-row v-if="show_students">
            <v-col>
                <CourseDates compact-student-view />
            </v-col>
        </v-row>

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

        <v-row v-if="show_curriculum" class="mt-n6">
            <v-col>
                <v-card variant="outlined" data-testid="teaching-curriculum-card">
                    <v-card-title class="text-subtitle-2 d-flex align-center ga-2 flex-wrap">
                        <v-icon size="18">mdi-book-open-variant</v-icon>
                        Curriculum
                        <v-spacer />
                        <v-btn
                            size="small"
                            variant="tonal"
                            prepend-icon="mdi-pencil"
                            :disabled="curriculumEditMode || curriculumSaveLoading"
                            @click="startCurriculumEdit">
                            Bearbeiten
                        </v-btn>
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="d-flex flex-column ga-3">
                        <div class="text-caption text-medium-emphasis">
                            Wähle ein Curriculum für dieses Fach aus oder entferne die aktuelle Zuweisung.
                        </div>

                        <v-select
                            v-model="curriculumSelectionId"
                            :items="curriculumOptions"
                            :loading="curriculumLoading"
                            :disabled="!curriculumEditMode || curriculumSaveLoading"
                            item-title="title"
                            item-value="value"
                            label="Curriculum auswählen"
                            variant="outlined"
                            density="comfortable"
                            :clearable="curriculumEditMode"
                            hide-details="auto"
                            no-data-text="Keine Curricula verfügbar" />

                        <div v-if="selectedCourseCurriculum" class="text-caption text-medium-emphasis">
                            Aktuell zugewiesen: <strong>{{ selectedCourseCurriculum.title }}</strong>
                            <span v-if="selectedCourseCurriculum.description"> · {{ selectedCourseCurriculum.description }}</span>
                        </div>
                        <div v-else class="text-caption text-medium-emphasis">
                            Aktuell ist kein Curriculum zugewiesen.
                        </div>

                        <div v-if="curriculumEditMode" class="d-flex flex-wrap ga-2">
                            <v-btn
                                color="primary"
                                variant="tonal"
                                :loading="curriculumSaveLoading"
                                :disabled="!hasCurriculumSelectionChanges || curriculumSaveLoading"
                                @click="saveCurriculumAssignment">
                                Zuweisung speichern
                            </v-btn>
                            <v-btn
                                color="warning"
                                variant="tonal"
                                :disabled="!selectedCourseCurriculum || curriculumSaveLoading"
                                @click="removeCurriculumAssignment">
                                Zuweisung entfernen
                            </v-btn>
                            <v-btn
                                variant="text"
                                :disabled="curriculumSaveLoading"
                                @click="cancelCurriculumEdit">
                                Abbrechen
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row v-if="show_print" class="mt-n6">
            <v-col>
                <CoursePrint />
            </v-col>
        </v-row>
    </v-col>

    <v-col cols="12" v-if="(show_attendance || show_performances || show_performances_plus) && action != 'teaching_course_new_or_edit'" :style="contentLockStyle">
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
        <v-row v-if="show_performances_plus" :class="show_attendance ? 'mt-n6' : ''">
            <v-col>
                <PerformancesPlusDummy
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
import { useCurriculumStore } from '@/stores/admin/teaching/CurriculumStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import MyCourses from './components/MyCourses.vue'
import CourseStudents from './components/CourseStudents.vue'
import CourseStudent from './components/CourseStudent.vue'
import CourseInfos from './components/CourseInfos.vue'
import CourseDates from './components/CourseDates.vue'
import CourseWorks from './components/CourseWorks.vue'
import CoursePrint from './components/CoursePrint.vue'
import MyTimetable from './components/MyTimetable.vue'
import AttendanceMatrix from '../more/components/AttendanceMatrix.vue'
import PerformancesDummy from '../more/components/PerformancesDummy.vue'
import PerformancesPlusDummy from '../more/components/PerformancesPlusDummy.vue'

export default {
    components: { MyCourses, CourseStudents, CourseStudent, CourseInfos, CourseDates, CourseWorks, CoursePrint, MyTimetable, AttendanceMatrix, PerformancesDummy, PerformancesPlusDummy },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.curriculumStore = useCurriculumStore()
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
            curriculumStore: null,
            schoolHourStore: null,
            teachingStore: null,
            activeSemester: 1,
            _urlPanelRestored: false,
            curriculumSelectionId: null,
            curriculumLoading: false,
            curriculumSaveLoading: false,
            curriculumEditMode: false,
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
            'show_curriculum',
            'show_print',
            'show_attendance',
            'show_performances',
            'selected_course_student',
            'show_performances_plus',
        ]),
        isControlLocked() {
            return this.action != '' || this.isStudentDetailActive
        },
        contentLockStyle() {
            return this.isStudentDetailActive ? 'pointer-events:none; opacity:0.6' : ''
        },
        isStudentDetailActive() {
            return this.action_2 === 'course_student_view' || !!this.selected_course_student
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
        selectedCourseCurriculum() {
            return this.selected_course?.teaching_curriculum || null
        },
        selectedCourseCurriculumId() {
            const value = this.selected_course?.teaching_curriculum_id ?? this.selectedCourseCurriculum?.id ?? null
            const normalized = Number(value)
            return Number.isFinite(normalized) && normalized > 0 ? normalized : null
        },
        normalizedCurriculumSelectionId() {
            const normalized = Number(this.curriculumSelectionId)
            return Number.isFinite(normalized) && normalized > 0 ? normalized : null
        },
        hasCurriculumSelectionChanges() {
            return this.selectedCourseCurriculumId !== this.normalizedCurriculumSelectionId
        },
        curriculumOptions() {
            const curricula = Array.isArray(this.curriculumStore?.curricula) ? this.curriculumStore.curricula : []
            const options = curricula
                .filter((curriculum) => curriculum && curriculum.id)
                .map((curriculum) => ({
                    value: Number(curriculum.id),
                    title: curriculum.title || `Curriculum #${curriculum.id}`,
                }))

            if (
                this.selectedCourseCurriculum
                && !options.some((option) => option.value === Number(this.selectedCourseCurriculum.id))
            ) {
                options.unshift({
                    value: Number(this.selectedCourseCurriculum.id),
                    title: this.selectedCourseCurriculum.title || `Curriculum #${this.selectedCourseCurriculum.id}`,
                })
            }

            return options
        },
        functionalPanels() {
            const panels = []
            if (this.selected_course) {
                panels.push({ id: 'students', label: 'Schüler:innen', icon: 'mdi-account-group' })
                panels.push({ id: 'infos', label: 'Infos', icon: 'mdi-information-outline' })
                panels.push({ id: 'works', label: 'Arbeiten', icon: 'mdi-file-document-edit-outline' })
                panels.push({ id: 'dates', label: 'Termine', icon: 'mdi-calendar-clock-outline' })
                panels.push({ id: 'curriculum', label: 'Curriculum', icon: 'mdi-book-open-variant' })
                panels.push({ id: 'attendance', label: 'Anwesenheit', icon: 'mdi-table' })
                panels.push({ id: 'performances', label: 'Leistungen', icon: 'mdi-chart-line' })
                panels.push({ id: 'performances_plus', label: 'Leistungen Plus', icon: 'mdi-chart-bar' })
                panels.push({ id: 'print', label: 'Druck', icon: 'mdi-printer-outline' })
            }
            return panels
        },
        functionalPanelSelection: {
            get() {
                if (!this.selected_course) return undefined
                if (this.show_students) return 'students'
                if (this.show_infos) return 'infos'
                if (this.show_works) return 'works'
                if (this.show_print) return 'print'
                if (this.show_dates) return 'dates'
                if (this.show_curriculum) return 'curriculum'
                if (this.show_attendance) return 'attendance'
                if (this.show_performances) return 'performances'
                if (this.show_performances_plus) return 'performances_plus'
                return undefined
            },
            set(value) {
                this.show_students = value === 'students'
                this.show_infos = value === 'infos'
                this.show_works = value === 'works'
                this.show_print = value === 'print'
                this.show_dates = value === 'dates'
                this.show_curriculum = value === 'curriculum'
                this.show_attendance = value === 'attendance'
                this.show_performances = value === 'performances'
                this.show_performances_plus = value === 'performances_plus'
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
        selected_course: {
            immediate: true,
            handler(newCourse, oldCourse) {
            if (!newCourse) {
                this._lastCourseId = null
                this.curriculumSelectionId = null
                this.curriculumEditMode = false
                return
            }
            this.curriculumSelectionId = this.selectedCourseCurriculumId
            this.curriculumEditMode = false
            if (!this._urlPanelRestored) {
                const urlPanel = this.$route?.query?.panel
                const validPanels = ['students', 'infos', 'works', 'print', 'dates', 'curriculum', 'attendance', 'performances', 'performances_plus']
                this._urlPanelRestored = true
                this._lastCourseId = newCourse.id
                if (urlPanel && validPanels.includes(urlPanel)) {
                    this.show_students = urlPanel === 'students'
                    this.show_infos = urlPanel === 'infos'
                    this.show_works = urlPanel === 'works'
                    this.show_print = urlPanel === 'print'
                    this.show_dates = urlPanel === 'dates'
                    this.show_curriculum = urlPanel === 'curriculum'
                    this.show_attendance = urlPanel === 'attendance'
                    this.show_performances = urlPanel === 'performances'
                    this.show_performances_plus = urlPanel === 'performances_plus'
                    return
                }
            }
            // Same course refreshed (e.g. after status toggle) – keep current panel
            if (this._lastCourseId === newCourse.id) {
                return
            }
            this._lastCourseId = newCourse.id
            this._urlPanelRestored = true
            this.show_students = true
            this.show_infos = false
            this.show_works = false
            this.show_print = false
            this.show_dates = false
            this.show_curriculum = false
            this.show_attendance = false
            this.show_performances = false
            this.show_performances_plus = false
            },
        },
        show_curriculum: {
            immediate: true,
            async handler(value) {
                if (!value || !this.selected_course?.id) {
                    return
                }

                await this.loadCurricula()
            },
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
        async loadCurricula() {
            if (this.curriculumLoading || !this.curriculumStore) {
                return
            }

            this.curriculumLoading = true
            try {
                await this.curriculumStore.index({
                    page: 1,
                    perPage: 250,
                    search: '',
                })
            } finally {
                this.curriculumLoading = false
            }
        },
        async saveCurriculumAssignment() {
            if (!this.selected_course?.id) {
                return
            }

            this.curriculumSaveLoading = true
            try {
                const payload = {
                    ...this.selected_course,
                    teaching_curriculum_id: this.normalizedCurriculumSelectionId,
                }

                const updated = await this.courseStore.update(payload)
                if (!updated) {
                    return
                }

                await this.courseStore.refreshCourseById(this.selected_course.id)
                this.curriculumSelectionId = this.selectedCourseCurriculumId
                this.curriculumEditMode = false
            } finally {
                this.curriculumSaveLoading = false
            }
        },
        async removeCurriculumAssignment() {
            this.curriculumSelectionId = null
            await this.saveCurriculumAssignment()
        },
        async startCurriculumEdit() {
            await this.loadCurricula()
            this.curriculumEditMode = true
            this.curriculumSelectionId = this.selectedCourseCurriculumId
        },
        cancelCurriculumEdit() {
            this.curriculumEditMode = false
            this.curriculumSelectionId = this.selectedCourseCurriculumId
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
    border: 1px solid rgba(37, 99, 235, 0.16);
    background: rgba(255, 255, 255, 0.86);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.07);
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
    border: 1px solid rgba(37, 99, 235, 0.16) !important;
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.98) 0%, rgba(219, 234, 254, 0.92) 100%) !important;
    color: #1e3a8a !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.86), 0 6px 14px rgba(148, 163, 184, 0.12) !important;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background 0.18s ease !important;
}

.teaching-overview-toolbar-btn:hover {
    background: linear-gradient(180deg, rgba(239, 246, 255, 1) 0%, rgba(191, 219, 254, 0.98) 100%) !important;
    color: #1d4ed8 !important;
    border-color: rgba(37, 99, 235, 0.28) !important;
}

.teaching-overview-toolbar-btn.v-btn--selected {
    background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%) !important;
    color: #ffffff !important;
    border-color: rgba(30, 64, 175, 0.5) !important;
    box-shadow: 0 12px 22px rgba(37, 99, 235, 0.24) !important;
}

.teaching-overview-toolbar.is-locked {
    opacity: 0.68;
}

.teaching-overview-toolbar-width {
    width: 100%;
}
</style>
