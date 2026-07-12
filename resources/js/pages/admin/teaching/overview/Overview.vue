<template>
    <v-col v-if="functionalPanels.length" cols="12" class="pb-1">
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

    <v-col
        cols="12"
        md="8"
        lg="7"
        xl="6"
        class="teaching-overview-card-col"
        v-if="show_students || !selected_course">
        <v-row v-if="!selected_course && action != 'teaching_course_new_or_edit'" :style="contentLockStyle">
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


    <v-col
        cols="12"
        :md="['dates', 'table'].includes(secondaryOverviewPanelSelection) ? 12 : 8"
        :lg="['dates', 'table'].includes(secondaryOverviewPanelSelection) ? 12 : 7"
        :xl="['dates', 'table'].includes(secondaryOverviewPanelSelection) ? 12 : 6"
        class="teaching-overview-card-col"
        v-if="selected_course && secondaryOverviewPanelSelection && action != 'teaching_course_new_or_edit'"
        :style="contentLockStyle">
        <v-row v-if="secondaryOverviewPanelSelection === 'infos'">
            <v-col>
                <CourseInfos />
            </v-col>
        </v-row>

        <v-row v-if="secondaryOverviewPanelSelection === 'works'" class="mt-n6">
            <v-col>
                <CourseWorks />
            </v-col>
        </v-row>

        <v-row v-if="secondaryOverviewPanelSelection === 'dates'" class="mt-n6">
            <v-col>
                <CourseDates />
            </v-col>
        </v-row>

        <v-row v-if="secondaryOverviewPanelSelection === 'table'" class="mt-n6">
            <v-col>
                <CourseTable />
            </v-col>
        </v-row>

        <v-row v-if="secondaryOverviewPanelSelection === 'curriculum'" class="mt-n6">
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

                        <v-card v-if="selectedCourseCurriculum" variant="tonal" class="curriculum-sync-card">
                            <v-card-title class="text-subtitle-2 d-flex align-center ga-2 flex-wrap">
                                <v-icon size="18">mdi-calendar-sync</v-icon>
                                Termine synchronisieren
                                <v-chip size="x-small" color="primary" variant="flat">
                                    {{ curriculumSyncRows.length }}
                                </v-chip>
                            </v-card-title>
                            <v-divider />
                            <v-card-text class="pa-0">
                                <div class="curriculum-sync-grid curriculum-sync-grid--header">
                                    <div>Kurstermine</div>
                                    <div>Curriculum</div>
                                </div>
                                <div
                                    v-for="row in curriculumSyncRows"
                                    :key="row.weekKey"
                                    class="curriculum-sync-grid curriculum-sync-row">
                                    <div class="curriculum-sync-cell">
                                        <div class="curriculum-sync-week">{{ row.weekLabel }}</div>
                                        <div v-if="row.courseDates.length" class="d-flex flex-column ga-1">
                                            <v-chip
                                                v-for="courseDate in row.courseDates"
                                                :key="courseDate.id || `${row.weekKey}-${courseDate.date}`"
                                                size="small"
                                                :color="courseDateSyncColor(courseDate)"
                                                class="curriculum-sync-chip"
                                                :variant="courseDateSyncVariant(courseDate)">
                                                <span class="curriculum-sync-chip__text">
                                                    <strong>{{ courseDateLabel(courseDate) }}</strong>
                                                    <span v-if="isFreeCourseDateForSync(courseDate)" class="curriculum-sync-chip__free-label">
                                                        · Frei
                                                    </span>
                                                    <span v-if="courseDateContentText(courseDate)" class="curriculum-sync-chip__content">
                                                        {{ courseDateContentText(courseDate) }}
                                                    </span>
                                                </span>
                                            </v-chip>
                                        </div>
                                        <div v-else class="text-caption text-medium-emphasis">Kein Kurstermin</div>
                                    </div>
                                    <div class="curriculum-sync-cell curriculum-sync-cell--curriculum">
                                        <div v-if="row.curriculumEntries.length" class="d-flex flex-column ga-1">
                                            <div
                                                v-for="entry in row.curriculumEntries"
                                                :key="entry.key"
                                                class="curriculum-sync-entry">
                                                <v-chip
                                                    size="small"
                                                    :color="entry.color"
                                                    class="curriculum-sync-chip"
                                                    :variant="entry.variant">
                                                    <template v-if="entry.topicLabel && entry.unitLabel">
                                                        <span class="curriculum-sync-chip__text">
                                                            <v-icon
                                                                v-if="entry.isExam"
                                                                class="curriculum-sync-chip__exam-icon"
                                                                size="14"
                                                                icon="mdi-file-alert-outline" />
                                                            <strong class="curriculum-sync-chip__topic">{{ entry.topicLabel }}</strong>: {{ entry.unitLabel }}
                                                        </span>
                                                    </template>
                                                    <template v-else>
                                                        {{ entry.label }}
                                                    </template>
                                                </v-chip>
                                                <div v-if="entry.movable" class="curriculum-sync-entry-actions">
                                                    <v-btn
                                                        icon="mdi-arrow-up"
                                                        size="x-small"
                                                        variant="text"
                                                        density="comfortable"
                                                        title="Allein eine Woche früher"
                                                        :disabled="curriculumMoveSaving || !canMoveCurriculumEntry(entry, -1, false)"
                                                        @click.stop="moveCurriculumSyncEntry(entry, -1, false)" />
                                                    <v-btn
                                                        icon="mdi-arrow-down"
                                                        size="x-small"
                                                        variant="text"
                                                        density="comfortable"
                                                        title="Allein eine Woche später"
                                                        :disabled="curriculumMoveSaving || !canMoveCurriculumEntry(entry, 1, false)"
                                                        @click.stop="moveCurriculumSyncEntry(entry, 1, false)" />
                                                    <v-btn
                                                        icon="mdi-arrow-up-bold-box-outline"
                                                        size="x-small"
                                                        variant="text"
                                                        density="comfortable"
                                                        title="Mit folgenden eine Woche früher"
                                                        :disabled="curriculumMoveSaving || !canMoveCurriculumEntry(entry, -1, true)"
                                                        @click.stop="moveCurriculumSyncEntry(entry, -1, true)" />
                                                    <v-btn
                                                        icon="mdi-arrow-down-bold-box-outline"
                                                        size="x-small"
                                                        variant="text"
                                                        density="comfortable"
                                                        title="Mit folgenden eine Woche später"
                                                        :disabled="curriculumMoveSaving || !canMoveCurriculumEntry(entry, 1, true)"
                                                        @click.stop="moveCurriculumSyncEntry(entry, 1, true)" />
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-caption text-medium-emphasis">Keine Curriculum-Zuordnung</div>
                                    </div>
                                </div>
                                <div v-if="!curriculumSyncRows.length" class="pa-4 text-caption text-medium-emphasis">
                                    Keine Termine oder Curriculum-Zuordnungen vorhanden.
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row v-if="secondaryOverviewPanelSelection === 'print'" class="mt-n6">
            <v-col>
                <CoursePrint />
            </v-col>
        </v-row>
    </v-col>

    <v-col cols="12" v-if="selected_course && (show_attendance || show_performances || show_performances_plus) && action != 'teaching_course_new_or_edit'" :style="contentLockStyle">
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
import CourseTable from './components/CourseTable.vue'
import CourseWorks from './components/CourseWorks.vue'
import CoursePrint from './components/CoursePrint.vue'
import MyTimetable from './components/MyTimetable.vue'
import AttendanceMatrix from '../more/components/AttendanceMatrix.vue'
import PerformancesDummy from '../more/components/PerformancesDummy.vue'
import PerformancesPlusDummy from '../more/components/PerformancesPlusDummy.vue'

export default {
    components: { MyCourses, CourseStudents, CourseStudent, CourseInfos, CourseDates, CourseTable, CourseWorks, CoursePrint, MyTimetable, AttendanceMatrix, PerformancesDummy, PerformancesPlusDummy },

    beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.curriculumStore = useCurriculumStore()
        this.schoolHourStore = useSchoolHourStore()
        this.teachingStore = useTeachingStore()
        this.selected_course_student = null
        this.action_2 = ''
        this.activeSemester = Number(this.config?.user?.teaching_active_semester) || 1
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
            curriculumMoveSaving: false,
            curriculumEditMode: false,
            selectedCurriculumDetail: null,
            selectedCurriculumDetailLoadingId: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, [
            'selected_course',
            'selected_course_id',
            'show_students',
            'show_infos',
            'show_works',
            'show_dates',
            'show_table',
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
        secondaryOverviewPanelSelection() {
            const secondaryPanels = ['infos', 'dates', 'table', 'works', 'print', 'curriculum']
            const selectedPanel = this.functionalPanelSelection

            return secondaryPanels.includes(selectedPanel) ? selectedPanel : null
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
        selectedCourseCurriculumForSync() {
            if (
                this.selectedCurriculumDetail
                && Number(this.selectedCurriculumDetail.id) === Number(this.selectedCourseCurriculumId)
            ) {
                return this.selectedCurriculumDetail
            }

            return this.selectedCourseCurriculum
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
        curriculumSyncRows() {
            const rows = new Map()

            const ensureRow = (weekKey) => {
                if (!weekKey) return null
                if (!rows.has(weekKey)) {
                    rows.set(weekKey, {
                        weekKey,
                        weekLabel: this.weekLabel(weekKey),
                        courseDates: [],
                        curriculumEntries: [],
                    })
                }

                return rows.get(weekKey)
            }

            ;(Array.isArray(this.selected_course?.course_dates) ? this.selected_course.course_dates : [])
                .filter((courseDate) => courseDate?.date)
                .forEach((courseDate) => {
                    ensureRow(this.weekStartKey(courseDate.date))?.courseDates.push(courseDate)
                })

            this.curriculumWeekEntries.forEach((entry) => {
                ensureRow(entry.weekKey)?.curriculumEntries.push(entry)
            })

            return [...rows.values()]
                .map((row) => ({
                    ...row,
                    courseDates: row.courseDates
                        .slice()
                        .sort((first, second) => String(first.date).localeCompare(String(second.date))),
                    curriculumEntries: this.visibleCurriculumEntriesForSyncRow(row.curriculumEntries),
                }))
                .sort((first, second) => first.weekKey.localeCompare(second.weekKey))
        },
        curriculumWeekEntries() {
            const curriculum = this.selectedCourseCurriculumForSync
            if (!curriculum) return []

            const entries = []
            const allWeekKeys = this.schoolyearWeekKeys()

            ;(Array.isArray(curriculum.free_weeks) ? curriculum.free_weeks : []).forEach((weekKey) => {
                entries.push({
                    key: `free-${weekKey}`,
                    weekKey,
                    label: 'Frei',
                    isFree: true,
                    color: 'success',
                    variant: 'flat',
                })
            })

            ;(Array.isArray(curriculum.topics) ? curriculum.topics : []).forEach((topic, topicIndex) => {
                const topicWeekKeys = this.assignmentWeekKeys(topic, allWeekKeys)

                if (topic?.title && ['all_weeks', 'month', 'weeks'].includes(topic.assignment_type)) {
                    topicWeekKeys.forEach((weekKey) => {
                        entries.push({
                            key: `topic-${topic.id || topicIndex}-${weekKey}`,
                            sourceKey: `topic-${topic.id || topicIndex}`,
                            sourceType: 'topic',
                            topicId: topic.id,
                            topicIndex,
                            assignmentType: topic.assignment_type,
                            weekKey,
                            label: topic.title,
                            movable: topic.assignment_type === 'weeks',
                            color: 'secondary',
                            variant: 'tonal',
                        })
                    })
                }

                ;(Array.isArray(topic?.units) ? topic.units : []).forEach((unit, unitIndex) => {
                    const unitWeekKeys = unit?.assignment_type === 'none'
                        ? topicWeekKeys
                        : this.assignmentWeekKeys(unit, allWeekKeys)

                    unitWeekKeys.forEach((weekKey) => {
                        entries.push({
                            key: `unit-${topic.id || topicIndex}-${unit.id || unitIndex}-${weekKey}`,
                            sourceKey: `unit-${topic.id || topicIndex}-${unit.id || unitIndex}`,
                            sourceType: 'unit',
                            topicId: topic.id,
                            topicIndex,
                            unitId: unit.id,
                            unitIndex,
                            assignmentType: unit.assignment_type,
                            weekKey,
                            label: `${topic.title ? `${topic.title}: ` : ''}${unit.title}`,
                            topicLabel: topic.title || '',
                            unitLabel: unit.title || '',
                            isExam: Boolean(unit.is_exam),
                            movable: unit.assignment_type === 'weeks',
                            color: unit.is_exam ? 'warning' : 'primary',
                            variant: unit.is_exam ? 'flat' : 'tonal',
                        })
                    })
                })
            })

            return entries
        },
        functionalPanels() {
            const panels = []
            if (this.selected_course) {
                panels.push({ id: 'students', label: 'Schüler:innen', icon: 'mdi-account-group' })
                panels.push({ id: 'dates', label: 'Termine', icon: 'mdi-calendar-clock-outline' })
                panels.push({ id: 'table', label: 'Tabelle', icon: 'mdi-table-large' })
                panels.push({ id: 'infos', label: 'Infos', icon: 'mdi-information-outline' })
                panels.push({ id: 'works', label: 'Arbeiten', icon: 'mdi-file-document-edit-outline' })
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
                if (this.show_table) return 'table'
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
                this.show_table = value === 'table'
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
                const urlGrades = this.$route?.query?.grades
                const validPanels = ['students', 'dates', 'table', 'infos', 'works', 'print', 'curriculum', 'attendance', 'performances', 'performances_plus']
                this._urlPanelRestored = true
                this._lastCourseId = newCourse.id
                if (urlPanel && validPanels.includes(urlPanel)) {
                    this.show_students = urlPanel === 'students'
                    this.show_infos = urlPanel === 'infos'
                    this.show_works = urlPanel === 'works'
                    this.show_print = urlPanel === 'print'
                    this.show_dates = urlPanel === 'dates'
                    this.show_table = urlPanel === 'table'
                    this.show_curriculum = urlPanel === 'curriculum'
                    this.show_attendance = urlPanel === 'attendance'
                    this.show_performances = urlPanel === 'performances'
                    this.show_performances_plus = urlPanel === 'performances_plus'
                    return
                }
                if (urlGrades) {
                    this.show_students = true
                    this.show_infos = false
                    this.show_works = false
                    this.show_print = false
                    this.show_dates = false
                    this.show_table = false
                    this.show_curriculum = false
                    this.show_attendance = false
                    this.show_performances = false
                    this.show_performances_plus = false
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
            this.show_table = false
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
                await this.loadSelectedCurriculumDetail()
            },
        },
        selectedCourseCurriculumId: {
            immediate: true,
            async handler() {
                if (!this.show_curriculum) {
                    return
                }

                await this.loadSelectedCurriculumDetail()
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
            await Promise.all([this.courseStore.index(), this.schoolHourStore.index()])
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
        async loadSelectedCurriculumDetail() {
            const curriculumId = this.selectedCourseCurriculumId
            if (!curriculumId || !this.curriculumStore) {
                this.selectedCurriculumDetail = null
                return
            }

            if (Number(this.selectedCurriculumDetail?.id) === Number(curriculumId)) {
                return
            }

            if (Number(this.selectedCurriculumDetailLoadingId) === Number(curriculumId)) {
                return
            }

            this.selectedCurriculumDetailLoadingId = curriculumId
            try {
                this.selectedCurriculumDetail = await this.curriculumStore.show(curriculumId)
            } finally {
                this.selectedCurriculumDetailLoadingId = null
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
        assignmentWeekKeys(item, allWeekKeys) {
            if (!item) return []
            if (item.assignment_type === 'all_weeks') return allWeekKeys
            if (item.assignment_type === 'weeks') return Array.isArray(item.week_keys) ? item.week_keys.filter(Boolean) : []
            if (item.assignment_type === 'month') {
                return (Array.isArray(item.month_keys) ? item.month_keys : [item.month_key])
                    .filter(Boolean)
                    .flatMap((monthKey) => this.weekKeysForMonth(monthKey))
                    .filter((weekKey, index, keys) => keys.indexOf(weekKey) === index)
            }

            return []
        },
        visibleCurriculumEntriesForSyncRow(entries) {
            const uniqueEntries = (Array.isArray(entries) ? entries : [])
                .filter((entry, index, items) => items.findIndex((candidate) => candidate.key === entry.key) === index)

            if (uniqueEntries.some((entry) => entry.isFree)) {
                return uniqueEntries.filter((entry) => entry.isFree)
            }

            return uniqueEntries
        },
        curriculumEntriesToMove(entry, direction, includeFollowing) {
            if (!entry?.movable || !entry.sourceKey || !entry.weekKey) return []

            const entries = (Array.isArray(this.curriculumWeekEntries) ? this.curriculumWeekEntries : [])
                .filter((candidate) => candidate?.movable && candidate.sourceKey && candidate.weekKey)
                .sort((first, second) => {
                    const weekComparison = first.weekKey.localeCompare(second.weekKey)
                    if (weekComparison !== 0) return weekComparison

                    const sourceComparison = String(first.sourceKey).localeCompare(String(second.sourceKey))
                    if (sourceComparison !== 0) return sourceComparison

                    return String(first.key).localeCompare(String(second.key))
                })

            if (!includeFollowing) {
                return entries.filter((candidate) => candidate.key === entry.key)
            }

            const selectedIndex = entries.findIndex((candidate) => candidate.key === entry.key)
            if (selectedIndex < 0) return []

            return entries.slice(selectedIndex)
        },
        shiftedWeekKey(weekKey, direction) {
            const weekKeys = this.schoolyearWeekKeys()
            const index = weekKeys.indexOf(weekKey)
            if (index < 0) return null

            return weekKeys[index + direction] || null
        },
        shiftedCurriculumMoveWeekKey(weekKey, direction) {
            if (!weekKey || !Number.isInteger(direction) || direction === 0) {
                return null
            }

            let targetWeekKey = this.shiftedWeekKey(weekKey, direction)
            while (targetWeekKey && this.isCurriculumWeekFree(targetWeekKey)) {
                targetWeekKey = this.shiftedWeekKey(targetWeekKey, direction)
            }

            return targetWeekKey
        },
        isCurriculumWeekFree(weekKey) {
            const curriculum = this.selectedCourseCurriculumForSync

            return (Array.isArray(curriculum?.free_weeks) ? curriculum.free_weeks : []).includes(weekKey)
        },
        canMoveCurriculumEntry(entry, direction, includeFollowing) {
            const entries = this.curriculumEntriesToMove(entry, direction, includeFollowing)
            if (!entries.length) return false

            const movingEntryKeys = new Set(entries.map((candidate) => candidate.key))
            const occupiedWeeklyEntries = (Array.isArray(this.curriculumWeekEntries) ? this.curriculumWeekEntries : [])
                .filter((candidate) => this.isBlockingCurriculumMoveEntry(candidate))

            return entries.every((candidate) => {
                const targetWeekKey = this.shiftedCurriculumMoveWeekKey(candidate.weekKey, direction)
                const isOccupiedByStationaryEntry = occupiedWeeklyEntries.some((occupiedEntry) => (
                    occupiedEntry.weekKey === targetWeekKey
                    && !movingEntryKeys.has(occupiedEntry.key)
                ))

                return Boolean(targetWeekKey) && !this.isCurriculumWeekFree(targetWeekKey) && !isOccupiedByStationaryEntry
            })
        },
        isBlockingCurriculumMoveEntry(entry) {
            return Boolean(entry?.movable && entry.assignmentType === 'weeks' && entry.sourceKey && entry.weekKey)
        },
        async moveCurriculumSyncEntry(entry, direction, includeFollowing) {
            if (this.curriculumMoveSaving || !this.canMoveCurriculumEntry(entry, direction, includeFollowing)) {
                return
            }

            const movingEntries = this.curriculumEntriesToMove(entry, direction, includeFollowing)
            const topics = this.shiftCurriculumTopics(movingEntries, direction)
            if (!topics) {
                return
            }

            await this.persistCurriculumTopicShift(topics)
        },
        shiftCurriculumTopics(entries, direction) {
            const curriculum = this.selectedCourseCurriculumForSync
            if (!curriculum || !Array.isArray(curriculum.topics)) return null

            const movingWeekKeysBySource = entries.reduce((sources, entry) => {
                if (!sources.has(entry.sourceKey)) {
                    sources.set(entry.sourceKey, new Set())
                }
                sources.get(entry.sourceKey).add(entry.weekKey)

                return sources
            }, new Map())

            return curriculum.topics.map((topic, topicIndex) => {
                const topicSourceKey = `topic-${topic.id || topicIndex}`
                const nextTopic = { ...topic }

                if (movingWeekKeysBySource.has(topicSourceKey) && topic.assignment_type === 'weeks') {
                    nextTopic.week_keys = this.shiftSelectedWeekKeys(topicSourceKey, topic.week_keys, direction, movingWeekKeysBySource)
                }

                if (Array.isArray(topic.units)) {
                    nextTopic.units = topic.units.map((unit, unitIndex) => {
                        const unitSourceKey = `unit-${topic.id || topicIndex}-${unit.id || unitIndex}`
                        if (!movingWeekKeysBySource.has(unitSourceKey) || unit.assignment_type !== 'weeks') {
                            return unit
                        }

                        return {
                            ...unit,
                            week_keys: this.shiftSelectedWeekKeys(unitSourceKey, unit.week_keys, direction, movingWeekKeysBySource),
                        }
                    })
                }

                return nextTopic
            })
        },
        shiftWeekKeys(weekKeys, direction) {
            return (Array.isArray(weekKeys) ? weekKeys : [])
                .map((weekKey) => this.shiftedCurriculumMoveWeekKey(weekKey, direction))
                .filter(Boolean)
                .filter((weekKey, index, keys) => keys.indexOf(weekKey) === index)
        },
        shiftSelectedWeekKeys(sourceKey, weekKeys, direction, movingWeekKeysBySource) {
            const movingWeekKeys = movingWeekKeysBySource.get(sourceKey) || new Set()

            return (Array.isArray(weekKeys) ? weekKeys : [])
                .map((weekKey) => (
                    movingWeekKeys.has(weekKey)
                        ? this.shiftedCurriculumMoveWeekKey(weekKey, direction)
                        : weekKey
                ))
                .filter(Boolean)
                .filter((weekKey, index, keys) => keys.indexOf(weekKey) === index)
                .sort((first, second) => first.localeCompare(second))
        },
        async persistCurriculumTopicShift(topics) {
            const curriculum = this.selectedCourseCurriculumForSync
            if (!curriculum?.id || !this.curriculumStore) {
                return
            }

            this.curriculumMoveSaving = true
            try {
                const updatedCurriculum = await this.curriculumStore.update(curriculum.id, {
                    title: curriculum.title || '',
                    description: curriculum.description || null,
                    semester_count: curriculum.semester_count ?? 2,
                    free_weeks: Array.isArray(curriculum.free_weeks) ? curriculum.free_weeks : [],
                    topics,
                })

                if (updatedCurriculum) {
                    this.selectedCurriculumDetail = updatedCurriculum
                }
            } finally {
                this.curriculumMoveSaving = false
            }
        },
        schoolyearWeekKeys() {
            const from = this.config?.selected_schoolyear?.from
            const until = this.config?.selected_schoolyear?.until
            if (!from || !until) return []

            const start = this.parseDate(from)
            const end = this.parseDate(until)
            if (!start || !end) return []

            const cursor = this.weekStartDate(start)
            const keys = []
            while (cursor <= end) {
                keys.push(this.dateKey(cursor))
                cursor.setDate(cursor.getDate() + 7)
            }

            return keys
        },
        weekKeysForMonth(monthKey) {
            const match = String(monthKey).match(/^(\d{4})-(\d{2})$/)
            if (!match) return []

            const year = Number(match[1])
            const monthIndex = Number(match[2]) - 1
            const firstDay = new Date(year, monthIndex, 1)
            const lastDay = new Date(year, monthIndex + 1, 0)
            const cursor = this.weekStartDate(firstDay)
            const keys = []

            while (cursor <= lastDay || cursor.getDay() !== 1) {
                const weekStart = new Date(cursor)
                let weekHasMonthDay = false
                for (let day = 0; day < 7; day++) {
                    const date = new Date(cursor)
                    if (date.getFullYear() === year && date.getMonth() === monthIndex) {
                        weekHasMonthDay = true
                    }
                    cursor.setDate(cursor.getDate() + 1)
                }
                if (!weekHasMonthDay) break
                keys.push(this.dateKey(weekStart))
            }

            return keys
        },
        parseDate(value) {
            const date = new Date(`${String(value).slice(0, 10)}T00:00:00`)
            return Number.isNaN(date.getTime()) ? null : date
        },
        weekStartDate(value) {
            const date = value instanceof Date ? new Date(value) : this.parseDate(value)
            if (!date) return null

            const day = date.getDay()
            const offset = day === 0 ? -6 : 1 - day
            date.setDate(date.getDate() + offset)
            date.setHours(0, 0, 0, 0)

            return date
        },
        weekStartKey(value) {
            const date = this.weekStartDate(value)
            return date ? this.dateKey(date) : null
        },
        dateKey(date) {
            const year = date.getFullYear()
            const month = String(date.getMonth() + 1).padStart(2, '0')
            const day = String(date.getDate()).padStart(2, '0')

            return `${year}-${month}-${day}`
        },
        weekLabel(weekKey) {
            const weekStart = this.parseDate(weekKey)
            if (!weekStart) return weekKey

            const weekEnd = new Date(weekStart)
            weekEnd.setDate(weekEnd.getDate() + 4)

            return `${this.formatShortDate(weekStart)} - ${this.formatShortDate(weekEnd)}`
        },
        formatShortDate(value) {
            const date = value instanceof Date ? value : this.parseDate(value)
            if (!date) return ''

            return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
        },
        courseDateLabel(courseDate) {
            const date = this.parseDate(courseDate?.date)
            const dateLabel = date
                ? date.toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' })
                : courseDate?.date
            const hours = Array.isArray(courseDate?.hours) && courseDate.hours.length
                ? ` · ${courseDate.hours.join(', ')}. Std`
                : ''

            return `${dateLabel}${hours}`
        },
        isFreeCourseDateForSync(courseDate) {
            const status = Array.isArray(courseDate?.status) ? courseDate.status : []

            return status.includes('free') || status.includes('entfaellt')
        },
        courseDateSyncColor(courseDate) {
            return this.isFreeCourseDateForSync(courseDate) ? 'success' : 'primary'
        },
        courseDateSyncVariant(courseDate) {
            return this.isFreeCourseDateForSync(courseDate) ? 'flat' : 'tonal'
        },
        courseDateContentText(courseDate) {
            const content = String(courseDate?.content || '').trim()
            if (!content) return ''

            if (typeof DOMParser !== 'undefined') {
                const document = new DOMParser().parseFromString(content, 'text/html')
                return (document.body?.textContent || '').replace(/\s+/g, ' ').trim()
            }

            return content.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
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

.teaching-overview-card-col {
    flex-grow: 0;
}

@media (max-width: 700px) {
    .teaching-overview-panel-switcher {
        display: grid !important;
        gap: 6px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        width: 100%;
    }

    .teaching-overview-toolbar-btn {
        justify-content: center;
        min-width: 0 !important;
        width: 100%;
    }

    .teaching-overview-toolbar-btn :deep(.v-btn__content) {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
}

.curriculum-sync-card {
    border: 1px solid rgba(37, 99, 235, 0.16);
    overflow: hidden;
}

.curriculum-sync-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
}

.curriculum-sync-grid--header {
    background: rgba(30, 64, 175, 0.08);
    color: #1e3a8a;
    font-size: 0.78rem;
    font-weight: 750;
    padding: 8px 12px;
}

.curriculum-sync-row {
    border-top: 1px solid rgba(148, 163, 184, 0.22);
}

.curriculum-sync-cell {
    min-width: 0;
    padding: 10px 12px;
}

.curriculum-sync-cell--curriculum {
    border-left: 1px solid rgba(148, 163, 184, 0.22);
    background: rgba(248, 250, 252, 0.72);
}

.curriculum-sync-week {
    color: #475569;
    font-size: 0.72rem;
    font-weight: 700;
    margin-bottom: 6px;
}

.curriculum-sync-chip {
    align-items: center;
    border-radius: 8px !important;
    height: auto;
    max-width: 100%;
    min-height: 28px;
    white-space: normal;
}

.curriculum-sync-chip :deep(.v-chip__underlay),
.curriculum-sync-chip :deep(.v-chip__overlay) {
    border-radius: 8px !important;
}

.curriculum-sync-chip :deep(.v-chip__content) {
    display: inline;
    line-height: 1.25;
    overflow-wrap: anywhere;
    padding-bottom: 4px;
    padding-top: 4px;
    white-space: normal;
}

.curriculum-sync-chip__text {
    display: inline;
}

.curriculum-sync-chip__content {
    display: block;
    font-weight: 500;
    margin-top: 2px;
}

.curriculum-sync-chip__exam-icon {
    display: inline-block;
    height: 14px;
    line-height: 14px;
    margin-right: 4px;
    min-width: 14px;
    vertical-align: -1px;
    width: 14px;
}

.curriculum-sync-chip__topic {
    font-weight: 800;
}

.curriculum-sync-chip__free-label {
    font-weight: 800;
}

.curriculum-sync-entry {
    align-items: center;
    display: grid;
    gap: 4px;
    grid-template-columns: minmax(0, 1fr) auto;
}

.curriculum-sync-entry-actions {
    align-items: center;
    display: flex;
    flex: 0 0 auto;
    gap: 2px;
}

.curriculum-sync-entry-actions :deep(.v-btn) {
    height: 24px;
    width: 24px;
}

@media (max-width: 700px) {
    .curriculum-sync-grid {
        grid-template-columns: 1fr;
    }

    .curriculum-sync-grid--header {
        display: none;
    }

    .curriculum-sync-cell--curriculum {
        border-left: 0;
        border-top: 1px dashed rgba(148, 163, 184, 0.3);
    }

    .curriculum-sync-entry {
        grid-template-columns: 1fr;
    }

    .curriculum-sync-entry-actions {
        justify-content: flex-start;
    }
}
</style>
