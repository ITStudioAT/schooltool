<template>
    <div class="lernportal-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <StudentTimetablesNavigationDrawer v-model="showDrawer" current-route="overview" />

        <section class="hero">
            <div class="hero-card">
                <div class="hero-topline">
                    <v-btn class="back-btn" variant="text" prepend-icon="mdi-arrow-left" @click="$router.push('/')">Zur Startseite</v-btn>
                    <v-btn class="menu-btn" variant="text" icon="mdi-menu" @click="showDrawer = true" />
                </div>

                <h1 class="hero-title">Schülerstundenpläne</h1>
                <p class="hero-subtitle">Ihr Stundenplanbereich.</p>

                <div class="hero-badges">
                    <span class="hero-badge">{{ weekdayLabel }}</span>
                    <span class="hero-badge dark">{{ dateLabel }}</span>
                    <span v-if="user" class="hero-badge">{{ user.first_name }} {{ user.last_name }}</span>
                    <span v-if="user?.schoolclass" class="hero-badge dark">{{ user.schoolclass }}</span>
                </div>

                <div class="hero-logout-row">
                    <v-btn class="logout-btn" variant="text" prepend-icon="mdi-logout" @click="handleLogout">Abmelden</v-btn>
                </div>
            </div>
        </section>

        <section class="content-cover">
            <div class="content-card">
                <div class="content-head">
                    <v-icon size="26">mdi-calendar-clock-outline</v-icon>
                    <h2>Ihre Daten</h2>
                </div>

                <div class="overview-selection">
                    <div class="overview-selected-cards">
                        <div
                            v-for="item in selectionItems"
                            :key="item.key"
                            class="overview-selected-card"
                            :class="{ 'overview-selected-card--overridden': selectionItemOverridden(item) }">
                            <div v-if="item.meta" class="overview-selected-card__meta">{{ item.meta }}</div>
                            <div class="overview-selected-card__top">
                                <div class="overview-selected-card__label">{{ item.label }}</div>
                                <v-btn
                                    v-if="selectionItemEditable(item) && !showEvaluationSettings && !showManualTimetable"
                                    icon="mdi-pencil"
                                    variant="text"
                                    color="primary"
                                    density="comfortable"
                                    size="x-small"
                                    :title="`${item.label} bearbeiten`"
                                    :aria-label="`${item.label} bearbeiten`"
                                    @click="openSelectionDialog(item)" />
                            </div>
                            <div class="overview-selected-card__value">{{ item.value || '-' }}</div>
                        </div>
                    </div>
                    <v-btn
                        v-if="hasSelectionOverride && !showEvaluationSettings && !showManualTimetable"
                        class="overview-selection__restore"
                        icon="mdi-restore"
                        variant="tonal"
                        color="primary"
                        density="comfortable"
                        title="Auswahl wiederherstellen"
                        aria-label="Auswahl wiederherstellen"
                        @click="restoreSelectionDefaults" />
                </div>

                <v-expansion-panels
                    v-if="showManualTimetable && courseSections.length"
                    v-model="expandedManualOverviewCoursePanels"
                    class="manual-overview-course-card"
                    flat>
                    <v-expansion-panel value="courses" class="manual-overview-course-card__panel">
                        <v-expansion-panel-title class="manual-overview-course-card__title">
                            <v-icon icon="mdi-book-open-page-variant-outline" size="22" />
                            <h3>Kurse</h3>
                            <v-chip size="small" variant="flat" color="primary">
                                {{ courseSectionTotalCount }}
                            </v-chip>
                        </v-expansion-panel-title>

                        <v-expansion-panel-text>
                            <v-expansion-panels
                                v-model="expandedCourseSections"
                                class="summary-grid summary-panels manual-overview-course-card__sections"
                                multiple
                                flat>
                                <v-expansion-panel
                                    v-for="section in courseSections"
                                    :key="section.key"
                                    :value="section.key"
                                    class="summary-panel">
                                    <v-expansion-panel-title class="summary-head">
                                        <v-icon size="22">{{ section.icon }}</v-icon>
                                        <h3>{{ section.title }}</h3>
                                        <v-chip size="small" variant="flat" :color="section.color">{{ section.items.length }}</v-chip>
                                    </v-expansion-panel-title>

                                    <v-expansion-panel-text>
                                        <div v-if="section.items.length" class="course-list">
                                            <div v-for="course in section.items" :key="courseKey(section.key, course)" class="course-row">
                                                <div>
                                                    <strong>{{ course.code || course.name || '-' }}</strong>
                                                    <span v-if="course.name && course.name !== course.code">{{ course.name }}</span>
                                                </div>
                                                <div class="course-meta">
                                                    <v-chip v-if="courseHoursLabel(course)" size="x-small" color="primary" variant="tonal">
                                                        {{ courseHoursLabel(course) }}
                                                    </v-chip>
                                                    <v-chip v-if="course.semester" size="x-small" variant="tonal">S{{ course.semester }}</v-chip>
                                                    <v-chip v-if="course.grade" size="x-small" color="success" variant="tonal">{{ course.grade }}</v-chip>
                                                    <v-chip v-if="course.branch && course.branch !== 'common'" size="x-small" variant="tonal">{{ course.branch }}</v-chip>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-else class="empty-state">
                                            <v-icon size="20">mdi-information-outline</v-icon>
                                            <span>{{ section.empty }}</span>
                                        </div>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                </v-expansion-panels>

                <v-dialog v-model="selectionDialogOpen" max-width="420">
                    <v-card>
                        <v-card-title>{{ selectionDraftLabel }} bearbeiten</v-card-title>
                        <v-card-text>
                            <v-select
                                v-model="selectionDraftValue"
                                :items="selectionDraftOptions"
                                item-title="title"
                                item-value="value"
                                :label="selectionDraftLabel"
                                variant="outlined"
                                density="comfortable"
                                hide-details />
                        </v-card-text>
                        <v-card-actions>
                            <v-spacer />
                            <v-btn variant="text" @click="closeSelectionDialog">Abbrechen</v-btn>
                            <v-btn color="primary" variant="flat" @click="saveSelectionDialog">Speichern</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

                <v-expansion-panels
                    v-if="!showEvaluationSettings && !showManualTimetable"
                    v-model="expandedCourseSections"
                    class="summary-grid summary-panels"
                    multiple
                    flat>
                    <v-expansion-panel v-for="section in courseSections" :key="section.key" :value="section.key" class="summary-panel">
                        <v-expansion-panel-title class="summary-head">
                            <v-icon size="22">{{ section.icon }}</v-icon>
                            <h3>{{ section.title }}</h3>
                            <v-chip size="small" variant="flat" :color="section.color">{{ section.items.length }}</v-chip>
                        </v-expansion-panel-title>

                        <v-expansion-panel-text>
                            <div v-if="section.items.length" class="course-list">
                                <div v-for="course in section.items" :key="courseKey(section.key, course)" class="course-row">
                                    <div>
                                        <strong>{{ course.code || course.name || '-' }}</strong>
                                        <span v-if="course.name && course.name !== course.code">{{ course.name }}</span>
                                    </div>
                                    <div class="course-meta">
                                        <v-chip v-if="courseHoursLabel(course)" size="x-small" color="primary" variant="tonal">
                                            {{ courseHoursLabel(course) }}
                                        </v-chip>
                                        <v-chip v-if="course.semester" size="x-small" variant="tonal">S{{ course.semester }}</v-chip>
                                        <v-chip v-if="course.grade" size="x-small" color="success" variant="tonal">{{ course.grade }}</v-chip>
                                        <v-chip v-if="course.branch && course.branch !== 'common'" size="x-small" variant="tonal">{{ course.branch }}</v-chip>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="empty-state">
                                <v-icon size="20">mdi-information-outline</v-icon>
                                <span>{{ section.empty }}</span>
                            </div>
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                </v-expansion-panels>

                <div v-if="!showEvaluationSettings && !showManualTimetable" class="timetable-actions">
                    <v-btn
                        class="timetable-actions__button timetable-actions__automatic"
                        variant="flat"
                        prepend-icon="mdi-auto-fix"
                        @click="openAutomaticTimetable">
                        <span>Automatischer Stundenplan</span>
                        <span class="timetable-actions__stars" aria-hidden="true">
                            <v-icon icon="mdi-star-four-points" size="10" class="timetable-star timetable-star--1" />
                            <v-icon icon="mdi-star-four-points" size="14" class="timetable-star timetable-star--2" />
                            <v-icon icon="mdi-star-four-points" size="8" class="timetable-star timetable-star--3" />
                        </span>
                    </v-btn>
                    <v-btn
                        class="timetable-actions__button timetable-actions__manual"
                        variant="flat"
                        prepend-icon="mdi-calendar-edit"
                        @click="openManualTimetable">
                        Manueller Stundenplan
                    </v-btn>
                    <v-btn
                        v-if="hasPublishedTimetable"
                        class="timetable-actions__button timetable-actions__published"
                        variant="flat"
                        prepend-icon="mdi-calendar-check"
                        @click="openPublishedTimetable">
                        Gespeicherter Stundenplan
                    </v-btn>
                </div>

                <section v-if="showManualTimetable" class="student-manual-timetable">
                    <div class="student-manual-timetable__toolbar">
                        <div>
                            <p class="student-manual-timetable__eyebrow">Manuell</p>
                            <h3>{{ manualTimetableTitle }}</h3>
                        </div>
                        <v-btn variant="text" prepend-icon="mdi-arrow-left" @click="closeManualTimetable">Zurück</v-btn>
                    </div>

                    <div v-if="publishedTimetableVisible" class="student-published-timetable">
                        <div v-if="publishedTimetableSubtitle" class="student-published-timetable__meta">
                            {{ publishedTimetableSubtitle }}
                        </div>

                        <div
                            v-for="semester in publishedTimetableSemesters"
                            :key="publishedTimetableSemesterKey(semester)"
                            class="student-published-timetable__semester">
                            <div class="student-published-timetable__semester-head">
                                <h4>{{ semester.label || 'Semester' }}</h4>
                                <span v-if="semester.date_range">{{ semester.date_range }}</span>
                            </div>

                            <div
                                v-for="week in publishedTimetableSemesterWeeks(semester)"
                                :key="publishedTimetableWeekKey(semester, week)"
                                class="student-published-timetable__week">
                                <div v-if="week.label" class="student-published-timetable__week-label">{{ week.label }}</div>
                                <div
                                    class="student-published-timetable__grid"
                                    :style="{ '--published-timetable-weekday-count': publishedTimetableWeekdays.length }">
                                    <div class="student-published-timetable__corner">Std.</div>
                                    <div
                                        v-for="weekday in publishedTimetableWeekdays"
                                        :key="weekday.label"
                                        class="student-published-timetable__weekday">
                                        {{ weekday.label }}
                                    </div>

                                    <template
                                        v-for="hour in publishedTimetableWeekHours(week)"
                                        :key="publishedTimetableHourKey(week, hour)">
                                        <div class="student-published-timetable__hour">
                                            <strong>{{ hour.hour }}.</strong>
                                            <span v-if="hour.from || hour.until">{{ hour.from }} - {{ hour.until }}</span>
                                        </div>
                                        <div
                                            v-for="(cell, cellIndex) in publishedTimetableHourCells(hour)"
                                            :key="`${publishedTimetableHourKey(week, hour)}-${cellIndex}`"
                                            class="student-published-timetable__cell"
                                            :class="publishedTimetableCellClasses(cell)">
                                            <div
                                                v-for="course in publishedTimetableCellCourses(cell)"
                                                :key="publishedTimetableCourseKey(course)"
                                                class="student-published-timetable__block">
                                                <strong>{{ course.label || '-' }}</strong>
                                                <span v-if="course.details">{{ course.details }}</span>
                                                <small v-if="course.student_course_badge">{{ course.student_course_badge }}</small>
                                            </div>

                                            <div
                                                v-for="marker in publishedTimetableCellMarkers(cell)"
                                                :key="publishedTimetableMarkerKey(marker)"
                                                class="student-published-timetable__marker">
                                                {{ marker.label || marker.title || '-' }}
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="student-manual-timetable__layout">
                        <div class="student-manual-timetable__courses">
                            <v-expansion-panels v-model="manualExpandedCourseSections" multiple flat>
                                <v-expansion-panel
                                    v-for="section in manualTimetableCourseSections"
                                    :key="section.key"
                                    :value="section.key"
                                    class="student-manual-timetable__course-section">
                                    <v-expansion-panel-title class="student-manual-timetable__course-section-title">
                                        <v-icon size="20">{{ section.icon }}</v-icon>
                                        <span>{{ section.title }}</span>
                                        <v-chip size="x-small" variant="flat" :color="section.color">{{ section.items.length }}</v-chip>
                                    </v-expansion-panel-title>

                                    <v-expansion-panel-text>
                                        <div v-if="section.items.length" class="student-manual-timetable__course-list">
                                            <label
                                                v-for="course in section.items"
                                                :key="manualCourseKey(course)"
                                                class="student-manual-timetable__course"
                                                :class="{ 'student-manual-timetable__course--disabled': !manualCourseGroups(course).length }">
                                                <v-checkbox-btn
                                                    :model-value="manualCourseSelected(course)"
                                                    :disabled="!manualCourseGroups(course).length"
                                                    density="compact"
                                                    color="primary"
                                                    @update:model-value="setManualCourseSelected(course, $event)" />
                                                <span>
                                                    <strong>{{ course.code || course.name || '-' }}</strong>
                                                    <small>{{ manualCourseMeta(course) }}</small>
                                                </span>
                                            </label>
                                        </div>

                                        <div v-else class="empty-state">
                                            <v-icon size="20">mdi-information-outline</v-icon>
                                            <span>{{ section.empty }}</span>
                                        </div>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </div>

                        <div class="student-manual-timetable__preview">
                            <div v-if="manualSelectedCourseGroups.length" class="student-manual-timetable__grid-wrap">
                                <div
                                    class="student-manual-timetable__grid"
                                    :style="{ '--manual-timetable-weekday-count': manualTimetableWeekdays.length }">
                                    <div class="student-manual-timetable__corner">Std.</div>
                                    <div
                                        v-for="weekday in manualTimetableWeekdays"
                                        :key="weekday.value"
                                        class="student-manual-timetable__weekday">
                                        {{ weekday.label }}
                                    </div>

                                    <template v-for="hour in manualTimetableHours" :key="hour.value">
                                        <div class="student-manual-timetable__hour">
                                            <strong>{{ hour.hourLabel }}</strong>
                                            <span v-if="hour.timeFrom || hour.timeUntil">{{ hour.timeFrom }} - {{ hour.timeUntil }}</span>
                                        </div>
                                        <div
                                            v-for="weekday in manualTimetableWeekdays"
                                            :key="`${weekday.value}-${hour.value}`"
                                            class="student-manual-timetable__cell"
                                            :class="{ 'student-manual-timetable__cell--conflict': manualGroupsForCell(weekday.value, hour.value).length > 1 }">
                                            <div
                                                v-for="group in manualGroupsForCell(weekday.value, hour.value)"
                                                :key="manualCourseGroupKey(group)"
                                                class="student-manual-timetable__block">
                                                <strong>{{ manualCourseGroupLabel(group) }}</strong>
                                                <span>{{ manualCourseGroupDetails(group) }}</span>
                                                <small v-if="manualCourseGroupWeekMarker(group)">{{ manualCourseGroupWeekMarker(group) }}</small>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div v-else class="student-manual-timetable__empty">
                                <v-icon size="24">mdi-calendar-search</v-icon>
                                <span>Keine passenden Kurstermine gefunden.</span>
                            </div>
                        </div>
                    </div>
                </section>

                <StudentTimetableEvaluationSettings
                    v-if="showEvaluationSettings"
                    :initial-step="automaticTimetableStep"
                    :initial-selected-course-keys="automaticTimetableCourseKeys"
                    :initial-selected-quality-criterion-keys="automaticTimetableQualityCriterionKeys"
                    :default-quality-criterion-selection="!automaticTimetableQualityCriteriaSelectionExplicit"
                    :proposed-courses="automaticTimetableSelectableCourses"
                    :course-summary="automaticTimetableCourseSummary"
                    :course-sections="automaticTimetableAllCourseSections"
                    :selection-override="selectionOverridePayload() || {}"
                    @close="closeAutomaticTimetable"
                    @course-selection-change="setAutomaticTimetableCourseKeys"
                    @courses-selected="finishAutomaticTimetable"
                    @quality-criteria-selection-change="setAutomaticTimetableQualityCriterionKeys"
                    @step-change="setAutomaticTimetableStep" />
            </div>
        </section>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useStudentTimetablesUserStore } from '@/stores/studentsTimetables/StudentTimetablesUserStore'
import StudentTimetableEvaluationSettings from '../components/StudentTimetableEvaluationSettings.vue'
import StudentTimetablesNavigationDrawer from '../components/StudentTimetablesNavigationDrawer.vue'
import '../../../../../css/student.css'

const noAutomaticTimetableQualityCriteriaValue = '__none'
const studentOverviewEditableSelectionKeys = ['religion', 'language', 'branch', 'arts_subject']
const studentOverviewSelectionOptionValues = {
    religion: ['ETH', 'Rev', 'Ris', 'Rk', 'Ror'],
    language: ['L', 'F', 'S'],
    branch: ['wirtschaftskundlich', 'gymnasial'],
    arts_subject: ['ME', 'BE'],
}

export default {
    components: {
        StudentTimetableEvaluationSettings,
        StudentTimetablesNavigationDrawer,
    },

    async beforeMount() {
        this.studentTimetablesStore = useStudentTimetablesUserStore()
        const isAuthenticated = await this.studentTimetablesStore.getCurrentUser()

        if (!isAuthenticated || !this.user) {
            this.$router.push('/homepage/students-timetables')
            return
        }

        await this.loadOverview()
    },

    data() {
        return {
            studentTimetablesStore: null,
            showDrawer: false,
            showEvaluationSettings: false,
            showManualTimetable: false,
            expandedCourseSections: [],
            expandedManualOverviewCoursePanels: [],
            manualExpandedCourseSections: ['missing', 'proposed'],
            manualSelectedCourseKeys: [],
            manualSelectedCourseGroupKeys: [],
            manualTimetableMode: 'manual',
            selectionOverride: {},
            selectionDialogOpen: false,
            selectionDraftKey: '',
            selectionDraftLabel: '',
            selectionDraftValue: null,
        }
    },

    computed: {
        ...mapWritableState(useStudentTimetablesUserStore, ['user', 'overview']),

        automaticTimetableStep() {
            const step = String(this.$route.query.automatic_timetable || '')

            return this.isAutomaticTimetableStep(step) ? step : 'criteria'
        },
        automaticTimetableCourseKeys() {
            const courseKeys = this.$route.query.automatic_timetable_courses

            return (Array.isArray(courseKeys) ? courseKeys : [courseKeys])
                .map(courseKey => String(courseKey || ''))
                .filter(Boolean)
        },
        automaticTimetableQualityCriterionKeys() {
            const criterionKeys = this.$route.query.automatic_timetable_criteria
            const criterionKeyList = Array.isArray(criterionKeys) ? criterionKeys : [criterionKeys]

            if (criterionKeyList.includes(noAutomaticTimetableQualityCriteriaValue)) {
                return []
            }

            return criterionKeyList
                .map(criterionKey => String(criterionKey || ''))
                .filter(criterionKey => criterionKey !== noAutomaticTimetableQualityCriteriaValue)
                .filter(Boolean)
        },
        automaticTimetableQualityCriteriaSelectionExplicit() {
            return Object.prototype.hasOwnProperty.call(this.$route.query, 'automatic_timetable_criteria')
        },
        weekdayLabel() {
            return new Intl.DateTimeFormat('de-AT', { weekday: 'long' }).format(new Date())
        },
        dateLabel() {
            return new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date())
        },
        selectionItems() {
            return Array.isArray(this.overview?.selection_items) ? this.overview.selection_items : []
        },
        selectionDraftOptions() {
            return this.selectionOptionsForKey(this.selectionDraftKey)
        },
        hasSelectionOverride() {
            return Object.keys(this.selectionOverride || {}).length > 0
        },
        courseSections() {
            return Array.isArray(this.overview?.course_sections) ? this.overview.course_sections : []
        },
        courseSectionTotalCount() {
            return this.courseSections.reduce((courseCount, section) => (
                courseCount + (Array.isArray(section?.items) ? section.items.length : 0)
            ), 0)
        },
        automaticTimetableCourseSelection() {
            const courseSelection = this.overview?.automatic_course_selection

            return courseSelection && typeof courseSelection === 'object' ? courseSelection : {}
        },
        automaticTimetableCourseSections() {
            return Array.isArray(this.automaticTimetableCourseSelection.sections)
                ? this.automaticTimetableCourseSelection.sections
                : []
        },
        automaticTimetableAllCourseSections() {
            const automaticSectionKeys = this.automaticTimetableCourseSections
                .map(section => String(section?.key || ''))
                .filter(Boolean)
            const additionalCourseSections = this.courseSections
                .filter(section => String(section?.key || '') === 'additional')
                .filter(section => !automaticSectionKeys.includes(String(section?.key || '')))
            const additionalCourses = Array.isArray(this.overview?.additional_courses)
                ? this.overview.additional_courses
                : []
            const syntheticAdditionalCourseSections = additionalCourseSections.length || !additionalCourses.length
                ? []
                : [{
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    icon: 'mdi-plus-circle-outline',
                    color: 'warning',
                    items: additionalCourses,
                    empty: 'Keine zusätzlichen Kurse erkannt.',
                }]

            return [
                ...this.automaticTimetableCourseSections,
                ...additionalCourseSections,
                ...syntheticAdditionalCourseSections,
            ]
        },
        automaticTimetableSelectableCourses() {
            return Array.isArray(this.automaticTimetableCourseSelection.courses)
                ? this.automaticTimetableCourseSelection.courses
                : []
        },
        automaticTimetableCourseSummary() {
            return this.automaticTimetableCourseSelection
        },
        manualTimetableSelection() {
            const manualTimetable = this.overview?.manual_timetable

            return manualTimetable && typeof manualTimetable === 'object' ? manualTimetable : {}
        },
        manualTimetableTitle() {
            if (this.manualTimetableMode === 'published') {
                return 'Gespeicherter Stundenplan'
            }

            return this.manualTimetableSelection.title || 'Manueller Stundenplan'
        },
        publishedTimetableSelection() {
            const publishedTimetable = this.overview?.published_timetable

            return publishedTimetable && typeof publishedTimetable === 'object' ? publishedTimetable : {}
        },
        hasPublishedTimetable() {
            return Boolean(this.publishedTimetableSelection.id)
        },
        publishedTimetableCourseGroupKeys() {
            const courseGroupKeys = Array.isArray(this.publishedTimetableSelection.active_course_group_keys)
                ? this.publishedTimetableSelection.active_course_group_keys
                : this.publishedTimetableSelection.state?.activeCourseGroupFilterKeys

            return (Array.isArray(courseGroupKeys) ? courseGroupKeys : [])
                .map(courseGroupKey => String(courseGroupKey || ''))
                .filter(Boolean)
        },
        publishedTimetablePayload() {
            const timetable = this.publishedTimetableSelection.timetable

            return timetable && typeof timetable === 'object' ? timetable : {}
        },
        publishedTimetableVisible() {
            return this.manualTimetableMode === 'published' && this.publishedTimetableSemesters.length > 0
        },
        publishedTimetableSubtitle() {
            return [
                this.publishedTimetablePayload.student,
                this.publishedTimetablePayload.schoolyear,
                this.publishedTimetablePayload.generated_at,
            ]
                .filter(Boolean)
                .join(' · ')
        },
        publishedTimetableWeekdays() {
            const weekdays = Array.isArray(this.publishedTimetablePayload.weekdays)
                ? this.publishedTimetablePayload.weekdays
                : []

            return weekdays
                .map((weekday, index) => ({
                    label: String(weekday?.label || index + 1),
                }))
                .filter(weekday => weekday.label)
        },
        publishedTimetableSemesters() {
            return Array.isArray(this.publishedTimetablePayload.semesters)
                ? this.publishedTimetablePayload.semesters
                : []
        },
        manualTimetableCourseSections() {
            return Array.isArray(this.manualTimetableSelection.sections)
                ? this.manualTimetableSelection.sections
                : []
        },
        manualTimetableCourses() {
            return this.manualTimetableCourseSections
                .flatMap(section => Array.isArray(section.items) ? section.items : [])
        },
        manualSelectedCourses() {
            const selectedCourseKeys = new Set(this.manualSelectedCourseKeys)

            return this.manualTimetableCourses
                .filter(course => selectedCourseKeys.has(this.manualCourseKey(course)))
        },
        manualSelectedCourseGroups() {
            const courseGroups = new Map()
            const selectedCourseGroupKeys = new Set(this.manualSelectedCourseGroupKeys)
            const shouldFilterCourseGroups = selectedCourseGroupKeys.size > 0

            this.manualSelectedCourses.forEach((course) => {
                this.manualCourseGroups(course).forEach((courseGroup) => {
                    const courseGroupKey = this.manualCourseGroupKey(courseGroup)

                    if (shouldFilterCourseGroups && !selectedCourseGroupKeys.has(courseGroupKey)) {
                        return
                    }

                    if (!courseGroups.has(courseGroupKey)) {
                        courseGroups.set(courseGroupKey, courseGroup)
                    }
                })
            })

            return Array.from(courseGroups.values())
        },
        manualTimetableWeekdays() {
            const selectedWeekdays = new Set(
                this.manualSelectedCourseGroups
                    .map(courseGroup => Number(courseGroup?.weekday || 0))
                    .filter(weekday => weekday >= 1 && weekday <= 6),
            )
            const weekdayLabels = [
                { value: 1, label: 'Mo' },
                { value: 2, label: 'Di' },
                { value: 3, label: 'Mi' },
                { value: 4, label: 'Do' },
                { value: 5, label: 'Fr' },
                { value: 6, label: 'Sa' },
            ]

            return weekdayLabels.filter(weekday => weekday.value < 6 || selectedWeekdays.has(6))
        },
        manualTimetableHours() {
            const hours = new Map()

            this.manualSelectedCourseGroups.forEach((courseGroup) => {
                const hour = Number(courseGroup?.hour || 0)

                if (!Number.isFinite(hour) || hour <= 0) {
                    return
                }

                if (!hours.has(hour)) {
                    hours.set(hour, {
                        value: hour,
                        hourLabel: `${hour}.`,
                        timeFrom: this.manualTimetableHourTimeFrom(courseGroup, hour),
                        timeUntil: this.manualTimetableHourTimeUntil(courseGroup, hour),
                    })
                }
            })

            return Array.from(hours.values()).sort((firstHour, secondHour) => firstHour.value - secondHour.value)
        },
    },

    watch: {
        '$route.query.automatic_timetable': {
            immediate: true,
            handler(step) {
                this.showEvaluationSettings = this.isAutomaticTimetableStep(step)
            },
        },
        '$route.query.manual_timetable': {
            immediate: true,
            handler(value) {
                this.applyManualTimetableRoute(value)
            },
        },
    },

    methods: {
        async loadOverview() {
            await this.studentTimetablesStore.loadOverview()
            this.syncSelectionOverrideFromOverview()
            this.applyCurrentManualTimetableSelection()
        },
        async handleLogout() {
            this.showDrawer = false
            await this.studentTimetablesStore.logout()
            this.$router.push('/homepage/students-timetables')
        },
        openAutomaticTimetable() {
            this.showEvaluationSettings = true
            this.showManualTimetable = false
            this.setAutomaticTimetableStep('criteria')
        },
        closeAutomaticTimetable() {
            this.showEvaluationSettings = false
            this.clearAutomaticTimetableStep()
        },
        finishAutomaticTimetable() {
            this.showEvaluationSettings = false
            this.clearAutomaticTimetableStep()
        },
        setAutomaticTimetableStep(step) {
            if (!this.isAutomaticTimetableStep(step)) {
                return
            }

            if (this.$route.query.automatic_timetable === step) {
                return
            }

            const query = { ...this.$route.query }
            delete query.manual_timetable
            query.automatic_timetable = step

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        setAutomaticTimetableCourseKeys(courseKeys) {
            const selectedCourseKeys = Array.isArray(courseKeys)
                ? courseKeys.map(courseKey => String(courseKey || '')).filter(Boolean)
                : []

            const currentCourseKeys = this.automaticTimetableCourseKeys
            if (JSON.stringify(currentCourseKeys) === JSON.stringify(selectedCourseKeys)) {
                return
            }

            const query = { ...this.$route.query }

            if (selectedCourseKeys.length) {
                query.automatic_timetable_courses = selectedCourseKeys
            } else {
                delete query.automatic_timetable_courses
            }

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        setAutomaticTimetableQualityCriterionKeys(criterionKeys) {
            const selectedCriterionKeys = Array.isArray(criterionKeys)
                ? criterionKeys.map(criterionKey => String(criterionKey || '')).filter(Boolean)
                : []

            const currentCriterionKeys = this.automaticTimetableQualityCriterionKeys
            if (
                JSON.stringify(currentCriterionKeys) === JSON.stringify(selectedCriterionKeys)
                && (selectedCriterionKeys.length || this.automaticTimetableQualityCriteriaSelectionExplicit)
            ) {
                return
            }

            const query = { ...this.$route.query }

            if (selectedCriterionKeys.length) {
                query.automatic_timetable_criteria = selectedCriterionKeys
            } else {
                query.automatic_timetable_criteria = noAutomaticTimetableQualityCriteriaValue
            }

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        clearAutomaticTimetableStep() {
            if (
                !this.$route.query.automatic_timetable
                && !this.$route.query.automatic_timetable_courses
                && !this.$route.query.automatic_timetable_criteria
            ) {
                return
            }

            const query = { ...this.$route.query }
            delete query.automatic_timetable
            delete query.automatic_timetable_courses
            delete query.automatic_timetable_criteria

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        isAutomaticTimetableStep(step) {
            return ['criteria', 'courses', 'result'].includes(String(step || ''))
        },
        openManualTimetable() {
            this.showManualTimetable = true
            this.showEvaluationSettings = false
            this.manualTimetableMode = 'manual'
            this.manualSelectedCourseKeys = []
            this.manualSelectedCourseGroupKeys = []
            this.ensureManualTimetableDefaultSelection()

            const query = { ...this.$route.query }
            delete query.automatic_timetable
            delete query.automatic_timetable_courses
            delete query.automatic_timetable_criteria
            query.manual_timetable = '1'

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        openPublishedTimetable() {
            if (!this.hasPublishedTimetable) {
                return
            }

            this.showManualTimetable = true
            this.showEvaluationSettings = false
            this.manualTimetableMode = 'published'
            this.applyPublishedTimetableSelection()

            const query = { ...this.$route.query }
            delete query.automatic_timetable
            delete query.automatic_timetable_courses
            delete query.automatic_timetable_criteria
            query.manual_timetable = 'published'

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        closeManualTimetable() {
            this.showManualTimetable = false
            this.clearManualTimetableState()
        },
        clearManualTimetableState() {
            if (!this.$route.query.manual_timetable) {
                return
            }

            const query = { ...this.$route.query }
            delete query.manual_timetable

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        applyManualTimetableRoute(value) {
            const manualTimetableRoute = String(value || '')

            this.showManualTimetable = ['1', 'published'].includes(manualTimetableRoute)
            this.manualTimetableMode = manualTimetableRoute === 'published' ? 'published' : 'manual'

            this.applyCurrentManualTimetableSelection()
        },
        applyCurrentManualTimetableSelection() {
            if (!this.showManualTimetable) {
                return
            }

            if (this.manualTimetableMode === 'published') {
                this.applyPublishedTimetableSelection()

                return
            }

            this.ensureManualTimetableDefaultSelection()
        },
        applyPublishedTimetableSelection() {
            const publishedCourseGroupKeys = this.publishedTimetableCourseGroupKeys
            const publishedCourseGroupKeySet = new Set(publishedCourseGroupKeys)

            this.manualSelectedCourseGroupKeys = publishedCourseGroupKeys
            this.manualSelectedCourseKeys = this.manualTimetableCourses
                .filter(course => this.manualCourseGroups(course)
                    .some(courseGroup => publishedCourseGroupKeySet.has(this.manualCourseGroupKey(courseGroup))))
                .map(course => this.manualCourseKey(course))

            if (!this.manualSelectedCourseKeys.length) {
                this.ensureManualTimetableDefaultSelection()
            }
        },
        publishedTimetableSemesterKey(semester) {
            return [
                semester?.label || '',
                semester?.date_range || '',
            ].join('|')
        },
        publishedTimetableSemesterWeeks(semester) {
            return Array.isArray(semester?.weeks) ? semester.weeks : []
        },
        publishedTimetableWeekKey(semester, week) {
            return [
                this.publishedTimetableSemesterKey(semester),
                week?.label || '',
                this.publishedTimetableWeekHours(week)
                    .map(hour => hour.hour)
                    .join(','),
            ].join('|')
        },
        publishedTimetableWeekHours(week) {
            return Array.isArray(week?.hours) ? week.hours : []
        },
        publishedTimetableHourKey(week, hour) {
            return [
                week?.label || '',
                hour?.hour || '',
                hour?.from || '',
                hour?.until || '',
            ].join('|')
        },
        publishedTimetableHourCells(hour) {
            const cells = Array.isArray(hour?.cells) ? hour.cells : []
            const weekdayCount = Math.max(this.publishedTimetableWeekdays.length, cells.length)

            return Array.from({ length: weekdayCount }, (_, index) => cells[index] || {})
        },
        publishedTimetableCellCourses(cell) {
            return Array.isArray(cell?.courses) ? cell.courses : []
        },
        publishedTimetableCellMarkers(cell) {
            return Array.isArray(cell?.markers) ? cell.markers : []
        },
        publishedTimetableCellClasses(cell) {
            return {
                'student-published-timetable__cell--filled': this.publishedTimetableCellCourses(cell).length > 0,
                'student-published-timetable__cell--warning': String(cell?.status || '') === 'warning',
                'student-published-timetable__cell--conflict': String(cell?.status || '') === 'conflict',
                'student-published-timetable__cell--related': String(cell?.status || '') === 'related',
            }
        },
        publishedTimetableCourseKey(course) {
            return [
                course?.label || '',
                course?.details || '',
                Array.isArray(course?.dates) ? course.dates.join(',') : '',
            ].join('|')
        },
        publishedTimetableMarkerKey(marker) {
            return [
                marker?.label || '',
                marker?.title || '',
            ].join('|')
        },
        ensureManualTimetableDefaultSelection() {
            if (!this.showManualTimetable || this.manualSelectedCourseKeys.length) {
                return
            }

            this.manualSelectedCourseKeys = this.manualTimetableCourseSections
                .filter(section => ['missing', 'proposed'].includes(String(section?.key || '')))
                .flatMap(section => Array.isArray(section.items) ? section.items : [])
                .filter(course => this.manualCourseGroups(course).length)
                .map(course => this.manualCourseKey(course))
        },
        manualCourseKey(course) {
            return String(course?.key || [course?.code || '', course?.name || '', course?.semester || ''].join('|'))
        },
        manualCourseGroups(course) {
            return Array.isArray(course?.course_groups) ? course.course_groups : []
        },
        manualCourseSelected(course) {
            return this.manualSelectedCourseKeys.includes(this.manualCourseKey(course))
        },
        setManualCourseSelected(course, selected) {
            const courseKey = this.manualCourseKey(course)

            if (!courseKey) {
                return
            }

            this.manualSelectedCourseGroupKeys = []
            this.manualTimetableMode = 'manual'

            if (selected === true && !this.manualSelectedCourseKeys.includes(courseKey)) {
                this.manualSelectedCourseKeys = [...this.manualSelectedCourseKeys, courseKey]
                return
            }

            if (selected !== true) {
                this.manualSelectedCourseKeys = this.manualSelectedCourseKeys
                    .filter(selectedCourseKey => selectedCourseKey !== courseKey)
            }
        },
        manualCourseMeta(course) {
            const courseGroupsCount = this.manualCourseGroups(course).length
            const hoursLabel = this.courseHoursLabel(course)
            const groupLabel = courseGroupsCount === 1 ? '1 Termin' : `${courseGroupsCount} Termine`

            return [hoursLabel, groupLabel]
                .filter(Boolean)
                .join(' · ')
        },
        manualGroupsForCell(weekday, hour) {
            return this.manualSelectedCourseGroups
                .filter(courseGroup => Number(courseGroup?.weekday || 0) === Number(weekday))
                .filter(courseGroup => Number(courseGroup?.hour || 0) === Number(hour))
        },
        manualCourseGroupKey(courseGroup) {
            return String(courseGroup?.key || [
                courseGroup?.course || courseGroup?.subject || courseGroup?.title || '',
                courseGroup?.weekday || '',
                courseGroup?.hour || '',
                courseGroup?.teacher || '',
            ].join('|'))
        },
        manualCourseGroupLabel(courseGroup) {
            return courseGroup?.course || courseGroup?.subject || courseGroup?.title || courseGroup?.display_label || '-'
        },
        manualCourseGroupDetails(courseGroup) {
            const rooms = Array.isArray(courseGroup?.rooms) ? courseGroup.rooms.join(', ') : ''

            return [
                courseGroup?.display_label,
                courseGroup?.teacher,
                rooms,
            ]
                .filter(Boolean)
                .join(' · ')
        },
        manualCourseGroupWeekMarker(courseGroup) {
            const recurrenceInterval = Number(courseGroup?.recurrence_interval || 0)

            if (recurrenceInterval > 1) {
                return `${recurrenceInterval}-w`
            }

            if (courseGroup?.block_label) {
                return courseGroup.block_label
            }

            return ''
        },
        manualTimetableHourTimeFrom(courseGroup, hour = null) {
            return this.formatTimeValue(
                courseGroup?.time_from
                || courseGroup?.from
                || this.configuredSchoolHour(hour ?? courseGroup?.hour)?.from,
            )
        },
        manualTimetableHourTimeUntil(courseGroup, hour = null) {
            return this.formatTimeValue(
                courseGroup?.time_until
                || courseGroup?.until
                || this.configuredSchoolHour(hour ?? courseGroup?.hour)?.until,
            )
        },
        configuredSchoolHour(hour) {
            const schoolHours = Array.isArray(this.overview?.school_hours) ? this.overview.school_hours : []

            return schoolHours.find(schoolHour => Number(schoolHour?.hour) === Number(hour)) || {}
        },
        formatTimeValue(value) {
            const timeValue = String(value || '').trim()

            return timeValue ? timeValue.slice(0, 5) : ''
        },
        selectionItemEditable(item) {
            return studentOverviewEditableSelectionKeys.includes(String(item?.key || ''))
        },
        selectionItemOverridden(item) {
            const key = String(item?.key || '')

            return Object.prototype.hasOwnProperty.call(this.selectionOverride || {}, key)
        },
        selectionOptionsForKey(key) {
            const optionKey = String(key || '')
            const options = this.overview?.selection_options?.[optionKey]

            return Array.isArray(options) ? options : []
        },
        openSelectionDialog(item) {
            if (!this.selectionItemEditable(item)) {
                return
            }

            this.selectionDraftKey = item.key
            this.selectionDraftLabel = item.label
            this.selectionDraftValue = this.currentSelectionValue(item.key)
            this.selectionDialogOpen = true
        },
        closeSelectionDialog() {
            this.selectionDialogOpen = false
            this.selectionDraftKey = ''
            this.selectionDraftLabel = ''
            this.selectionDraftValue = null
        },
        async saveSelectionDialog() {
            const key = String(this.selectionDraftKey || '')

            if (!studentOverviewEditableSelectionKeys.includes(key)) {
                this.closeSelectionDialog()

                return
            }

            const value = String(this.selectionDraftValue || '')
            const allowedValues = studentOverviewSelectionOptionValues[key] || []

            if (!allowedValues.includes(value)) {
                return
            }

            this.selectionOverride = {
                ...this.selectionOverride,
                [key]: value,
            }
            this.closeSelectionDialog()

            if (!await this.studentTimetablesStore.updateProfileSelection(this.selectionOverridePayload() || {})) {
                return
            }

            this.syncSelectionOverrideFromOverview()
        },
        async restoreSelectionDefaults() {
            if (!await this.studentTimetablesStore.restoreProfileSelection()) {
                return
            }

            this.syncSelectionOverrideFromOverview()
        },
        currentSelectionValue(key) {
            const selectionKey = String(key || '')

            return this.selectionOverride?.[selectionKey] || this.overview?.selection?.[selectionKey] || null
        },
        syncSelectionOverrideFromOverview() {
            this.selectionOverride = this.normalizedSelectionOverride(this.overview?.selection_override || {})
        },
        selectionOverridePayload() {
            const selection = this.normalizedSelectionOverride(this.selectionOverride)

            return Object.keys(selection).length ? selection : null
        },
        normalizedSelectionOverride(selection) {
            const normalizedSelection = {}

            studentOverviewEditableSelectionKeys.forEach((key) => {
                const value = String(selection?.[key] || '').trim()

                if ((studentOverviewSelectionOptionValues[key] || []).includes(value)) {
                    normalizedSelection[key] = value
                }
            })

            return normalizedSelection
        },
        courseKey(sectionKey, course) {
            return [sectionKey, course.code || '', course.name || '', course.semester || '', course.grade || ''].join('|')
        },
        courseHoursLabel(course) {
            const hours = course.hours ?? course.hours_per_week

            if (hours === null || hours === undefined || hours === '') {
                return null
            }

            const numericHours = Number(hours)

            if (!Number.isFinite(numericHours)) {
                return null
            }

            return `${new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 }).format(numericHours)} Std.`
        },
    },
}
</script>

<style scoped>
.logout-btn {
    color: var(--charcoal);
    font-weight: 700;
    border-radius: 999px;
}

.hero-badge {
    background: rgba(219, 234, 254, 0.82);
    border-color: rgba(37, 99, 235, 0.28);
    color: #1e3a8a;
}

.hero-badge.dark {
    color: #1d4ed8;
    background: rgba(37, 99, 235, 0.2);
    border-color: rgba(37, 99, 235, 0.5);
}

.hero-logout-row {
    margin-top: 12px;
    display: flex;
    justify-content: flex-end;
}

.overview-selection {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 20px;
}

.overview-selected-cards {
    display: grid;
    grid-template-columns: repeat(5, minmax(128px, 1fr));
    gap: 8px;
    min-width: 0;
}

.overview-selected-card {
    min-width: 0;
    padding: 9px 11px;
    border: 1px solid rgba(var(--v-theme-primary), 0.26);
    border-radius: 6px;
    background: #f8fafc;
}

.overview-selected-card--overridden {
    border-color: rgba(var(--v-theme-primary), 0.52);
    background: #eef6ff;
}

.overview-selected-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    min-height: 24px;
}

.overview-selected-card__label {
    color: #172554;
    font-size: 0.7rem;
    font-weight: 850;
}

.overview-selected-card__meta {
    margin-bottom: 3px;
    color: #64748b;
    font-size: 0.64rem;
    font-weight: 850;
    line-height: 1.1;
}

.overview-selected-card__value {
    margin-top: 4px;
    color: #020617;
    font-size: 0.78rem;
    font-weight: 800;
    line-height: 1.15;
    overflow-wrap: anywhere;
}

.overview-selection__restore {
    flex: 0 0 auto;
}

.manual-overview-course-card {
    margin: -4px 0 18px;
}

.manual-overview-course-card :deep(.v-expansion-panel) {
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px !important;
    background: rgba(255, 255, 255, 0.84) !important;
    overflow: hidden;
}

.manual-overview-course-card__title {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 56px;
    padding: 14px;
}

.manual-overview-course-card__title h3 {
    flex: 1;
    margin: 0;
    color: #10263a;
    font-size: 1rem;
}

.manual-overview-course-card :deep(.v-expansion-panel-text__wrapper) {
    padding: 0;
}

.manual-overview-course-card__sections {
    margin: 0;
    padding: 12px;
}

.timetable-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 0 0 20px;
}

.timetable-actions .v-btn {
    min-height: 44px;
    flex: 1 1 240px;
}

.timetable-actions__button {
    overflow: hidden;
    border-radius: 8px;
    color: #ffffff;
    font-weight: 850;
    letter-spacing: 0;
    text-transform: none;
}

.timetable-actions__automatic {
    background: linear-gradient(135deg, #1d4ed8 0%, #6366f1 100%);
    box-shadow: 0 0 8px rgba(37, 99, 235, 0.4), 0 0 20px rgba(37, 99, 235, 0.2);
    animation: timetable-automatic-glow 2s ease-in-out infinite;
}

.timetable-actions__automatic:hover {
    animation: none;
    box-shadow: 0 0 12px rgba(37, 99, 235, 0.6), 0 0 28px rgba(37, 99, 235, 0.35);
}

.timetable-actions__manual {
    background: linear-gradient(135deg, #0f766e 0%, #16a34a 100%);
    box-shadow: 0 0 8px rgba(15, 118, 110, 0.28), 0 0 18px rgba(22, 163, 74, 0.16);
}

.timetable-actions__manual:hover {
    box-shadow: 0 0 12px rgba(15, 118, 110, 0.42), 0 0 24px rgba(22, 163, 74, 0.25);
}

.timetable-actions__published {
    background: linear-gradient(135deg, #7c2d12 0%, #ea580c 100%);
    box-shadow: 0 0 8px rgba(234, 88, 12, 0.28), 0 0 18px rgba(124, 45, 18, 0.14);
}

.timetable-actions__published:hover {
    box-shadow: 0 0 12px rgba(234, 88, 12, 0.42), 0 0 24px rgba(124, 45, 18, 0.24);
}

.timetable-actions__stars {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-left: 2px;
}

.timetable-star {
    color: rgba(255, 255, 255, 0.9);
    animation: timetable-star-twinkle 1.8s ease-in-out infinite;
}

.timetable-star--1 {
    animation-delay: 0s;
}

.timetable-star--2 {
    animation-delay: 0.5s;
}

.timetable-star--3 {
    animation-delay: 1.1s;
}

.student-manual-timetable {
    display: grid;
    gap: 16px;
    margin-bottom: 20px;
}

.student-manual-timetable__toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(16, 38, 58, 0.08);
}

.student-manual-timetable__toolbar h3,
.student-manual-timetable__eyebrow {
    margin: 0;
}

.student-manual-timetable__toolbar h3 {
    color: #10263a;
    font-size: 1.18rem;
    line-height: 1.2;
}

.student-manual-timetable__eyebrow {
    color: #0f766e;
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0;
    text-transform: uppercase;
}

.student-manual-timetable__layout {
    display: grid;
    grid-template-columns: minmax(260px, 330px) minmax(0, 1fr);
    gap: 16px;
    align-items: start;
}

.student-manual-timetable__courses {
    min-width: 0;
}

.student-manual-timetable__course-section {
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px !important;
    background: #ffffff !important;
    overflow: hidden;
}

.student-manual-timetable__course-section-title {
    min-height: 50px;
    padding: 12px;
}

.student-manual-timetable__course-section-title :deep(.v-expansion-panel-title__overlay) {
    display: none;
}

.student-manual-timetable__course-section-title span {
    flex: 1;
    min-width: 0;
    color: #10263a;
    font-size: 0.9rem;
    font-weight: 850;
}

.student-manual-timetable :deep(.v-expansion-panel-text__wrapper) {
    padding: 0;
}

.student-manual-timetable__course-list {
    display: grid;
}

.student-manual-timetable__course {
    display: grid;
    grid-template-columns: 32px minmax(0, 1fr);
    gap: 8px;
    align-items: center;
    padding: 10px 12px;
    border-top: 1px solid rgba(16, 38, 58, 0.06);
}

.student-manual-timetable__course--disabled {
    opacity: 0.58;
}

.student-manual-timetable__course strong,
.student-manual-timetable__course small {
    display: block;
}

.student-manual-timetable__course strong {
    color: #172d40;
    font-size: 0.9rem;
    line-height: 1.18;
}

.student-manual-timetable__course small {
    margin-top: 2px;
    color: rgba(23, 45, 64, 0.64);
    font-size: 0.72rem;
    font-weight: 700;
}

.student-manual-timetable__preview {
    min-width: 0;
}

.student-manual-timetable__grid-wrap {
    width: 100%;
    overflow-x: auto;
}

.student-manual-timetable__grid {
    --manual-timetable-weekday-count: 5;
    display: grid;
    grid-template-columns: 82px repeat(var(--manual-timetable-weekday-count), minmax(112px, 1fr));
    width: 100%;
    min-width: calc(82px + (112px * var(--manual-timetable-weekday-count)));
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px;
    background: #ffffff;
    overflow: hidden;
}

.student-manual-timetable__corner,
.student-manual-timetable__weekday,
.student-manual-timetable__hour,
.student-manual-timetable__cell {
    border-right: 1px solid rgba(16, 38, 58, 0.07);
    border-bottom: 1px solid rgba(16, 38, 58, 0.07);
}

.student-manual-timetable__corner,
.student-manual-timetable__weekday,
.student-manual-timetable__hour {
    background: #f8fafc;
}

.student-manual-timetable__corner,
.student-manual-timetable__weekday {
    padding: 10px 8px;
    color: #10263a;
    font-size: 0.78rem;
    font-weight: 900;
    text-align: center;
}

.student-manual-timetable__hour {
    display: grid;
    align-content: center;
    gap: 2px;
    min-height: 78px;
    padding: 8px;
    color: #10263a;
}

.student-manual-timetable__hour strong,
.student-manual-timetable__hour span {
    display: block;
}

.student-manual-timetable__hour strong {
    font-size: 0.78rem;
}

.student-manual-timetable__hour span {
    color: rgba(23, 45, 64, 0.62);
    font-size: 0.66rem;
    font-weight: 750;
    line-height: 1.15;
}

.student-manual-timetable__cell {
    display: grid;
    align-content: start;
    gap: 5px;
    min-height: 78px;
    padding: 6px;
    background: #ffffff;
}

.student-manual-timetable__cell--conflict {
    background: #fef2f2;
}

.student-manual-timetable__block {
    display: grid;
    gap: 2px;
    padding: 6px;
    border: 1px solid rgba(15, 118, 110, 0.18);
    border-radius: 6px;
    background: #ecfdf5;
}

.student-manual-timetable__block strong,
.student-manual-timetable__block span,
.student-manual-timetable__block small {
    min-width: 0;
    overflow-wrap: anywhere;
}

.student-manual-timetable__block strong {
    color: #065f46;
    font-size: 0.76rem;
    line-height: 1.12;
}

.student-manual-timetable__block span {
    color: #134e4a;
    font-size: 0.66rem;
    font-weight: 720;
    line-height: 1.16;
}

.student-manual-timetable__block small {
    color: #0f766e;
    font-size: 0.62rem;
    font-weight: 900;
}

.student-manual-timetable__empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 180px;
    padding: 20px;
    border: 1px dashed rgba(16, 38, 58, 0.16);
    border-radius: 8px;
    color: rgba(23, 45, 64, 0.68);
    background: #f8fafc;
    font-size: 0.9rem;
    font-weight: 750;
}

.student-published-timetable {
    display: grid;
    gap: 16px;
    min-width: 0;
}

.student-published-timetable__meta {
    color: rgba(23, 45, 64, 0.72);
    font-size: 0.82rem;
    font-weight: 800;
}

.student-published-timetable__semester,
.student-published-timetable__week {
    display: grid;
    gap: 10px;
    min-width: 0;
}

.student-published-timetable__semester-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
}

.student-published-timetable__semester-head h4 {
    margin: 0;
    color: #10263a;
    font-size: 1rem;
}

.student-published-timetable__semester-head span,
.student-published-timetable__week-label {
    color: rgba(23, 45, 64, 0.64);
    font-size: 0.74rem;
    font-weight: 800;
}

.student-published-timetable__grid {
    --published-timetable-weekday-count: 5;
    display: grid;
    grid-template-columns: 82px repeat(var(--published-timetable-weekday-count), minmax(112px, 1fr));
    width: 100%;
    overflow-x: auto;
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px;
    background: #ffffff;
}

.student-published-timetable__corner,
.student-published-timetable__weekday,
.student-published-timetable__hour,
.student-published-timetable__cell {
    border-right: 1px solid rgba(16, 38, 58, 0.07);
    border-bottom: 1px solid rgba(16, 38, 58, 0.07);
}

.student-published-timetable__corner,
.student-published-timetable__weekday,
.student-published-timetable__hour {
    background: #f8fafc;
}

.student-published-timetable__corner,
.student-published-timetable__weekday {
    padding: 10px 8px;
    color: #10263a;
    font-size: 0.78rem;
    font-weight: 900;
    text-align: center;
}

.student-published-timetable__hour {
    display: grid;
    align-content: center;
    gap: 2px;
    min-height: 78px;
    padding: 8px;
    color: #10263a;
}

.student-published-timetable__hour strong,
.student-published-timetable__hour span {
    display: block;
}

.student-published-timetable__hour strong {
    font-size: 0.78rem;
}

.student-published-timetable__hour span {
    color: rgba(23, 45, 64, 0.62);
    font-size: 0.66rem;
    font-weight: 750;
    line-height: 1.15;
}

.student-published-timetable__cell {
    display: grid;
    align-content: start;
    gap: 5px;
    min-height: 78px;
    padding: 6px;
    background: #ffffff;
}

.student-published-timetable__cell--warning {
    background: #fff7ed;
}

.student-published-timetable__cell--conflict {
    background: #fef2f2;
}

.student-published-timetable__cell--related {
    background: #eff6ff;
}

.student-published-timetable__block,
.student-published-timetable__marker {
    display: grid;
    gap: 2px;
    padding: 6px;
    border: 1px solid rgba(15, 118, 110, 0.18);
    border-radius: 6px;
    background: #ecfdf5;
}

.student-published-timetable__marker {
    border-color: rgba(234, 88, 12, 0.22);
    color: #9a3412;
    background: #ffedd5;
    font-size: 0.7rem;
    font-weight: 850;
}

.student-published-timetable__block strong,
.student-published-timetable__block span,
.student-published-timetable__block small {
    min-width: 0;
    overflow-wrap: anywhere;
}

.student-published-timetable__block strong {
    color: #065f46;
    font-size: 0.76rem;
    line-height: 1.12;
}

.student-published-timetable__block span {
    color: #134e4a;
    font-size: 0.66rem;
    font-weight: 720;
    line-height: 1.16;
}

.student-published-timetable__block small {
    color: #0f766e;
    font-size: 0.62rem;
    font-weight: 900;
}

@keyframes timetable-star-twinkle {
    0%,
    100% {
        opacity: 0.35;
        transform: scale(0.8);
    }

    50% {
        opacity: 1;
        transform: scale(1.2);
    }
}

@keyframes timetable-automatic-glow {
    0%,
    100% {
        box-shadow: 0 0 8px rgba(37, 99, 235, 0.4), 0 0 20px rgba(37, 99, 235, 0.2);
    }

    50% {
        box-shadow: 0 0 16px rgba(37, 99, 235, 0.7), 0 0 36px rgba(37, 99, 235, 0.35);
    }
}

.summary-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
    align-items: start;
    margin-bottom: 20px;
}

@media (min-width: 900px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 520px) {
    .timetable-actions .v-btn {
        width: 100%;
    }

    .student-manual-timetable__toolbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .overview-selected-cards {
        grid-template-columns: 1fr;
    }

    .overview-selection {
        flex-direction: column;
    }
}

@media (max-width: 980px) {
    .student-manual-timetable__layout {
        grid-template-columns: 1fr;
    }
}

@media (min-width: 521px) and (max-width: 900px) {
    .overview-selected-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.summary-panels :deep(.v-expansion-panel) {
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px !important;
    background: rgba(255, 255, 255, 0.84) !important;
    margin-top: 0 !important;
    overflow: hidden;
}

.summary-head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px;
    border-bottom: 1px solid rgba(16, 38, 58, 0.07);
    min-height: 58px;
}

.summary-head h3 {
    flex: 1;
    margin: 0;
    font-size: 1rem;
    color: #10263a;
}

.summary-panels :deep(.v-expansion-panel-text__wrapper) {
    padding: 0;
}

.course-list {
    display: grid;
}

.course-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(16, 38, 58, 0.06);
}

.course-row:last-child {
    border-bottom: 0;
}

.course-row strong,
.course-row span {
    display: block;
}

.course-row strong {
    color: #172d40;
}

.course-row span {
    margin-top: 2px;
    color: rgba(23, 45, 64, 0.72);
    font-size: 0.86rem;
}

.course-meta {
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
    gap: 6px;
    flex-wrap: wrap;
    min-width: 72px;
}

.empty-state {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 18px 14px;
    color: rgba(23, 45, 64, 0.68);
    font-size: 0.92rem;
}
</style>
