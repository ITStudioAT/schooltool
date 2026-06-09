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
                                    v-if="selectionItemEditable(item) && !showEvaluationSettings"
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
                        v-if="hasSelectionOverride && !showEvaluationSettings"
                        class="overview-selection__restore"
                        icon="mdi-restore"
                        variant="tonal"
                        color="primary"
                        density="comfortable"
                        title="Auswahl wiederherstellen"
                        aria-label="Auswahl wiederherstellen"
                        @click="restoreSelectionDefaults" />
                </div>

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
                    v-if="!showEvaluationSettings"
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

                <div v-if="!showEvaluationSettings" class="timetable-actions">
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
                        prepend-icon="mdi-calendar-edit">
                        Manueller Stundenplan
                    </v-btn>
                </div>

                <StudentTimetableEvaluationSettings
                    v-if="showEvaluationSettings"
                    :initial-step="automaticTimetableStep"
                    :initial-selected-course-keys="automaticTimetableCourseKeys"
                    :initial-selected-quality-criterion-keys="automaticTimetableQualityCriterionKeys"
                    :default-quality-criterion-selection="!automaticTimetableQualityCriteriaSelectionExplicit"
                    :proposed-courses="automaticTimetableSelectableCourses"
                    :course-summary="automaticTimetableCourseSummary"
                    :course-sections="automaticTimetableCourseSections"
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
            expandedCourseSections: [],
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
        automaticTimetableCourseSections() {
            return this.courseSections
                .filter(section => ['Fehlende Kurse', 'Vorgesehene Kurse'].includes(section.title))
        },
        automaticTimetableSelectableCourses() {
            return this.automaticTimetableCourseSections
                .flatMap(section => Array.isArray(section.items) ? section.items : [])
        },
        automaticTimetableCourseSummary() {
            const relevantSections = this.automaticTimetableCourseSections
            const total = relevantSections
                .reduce((courseCount, section) => courseCount + (Array.isArray(section.items) ? section.items.length : 0), 0)

            return {
                title: 'Fehlende Kurse + Vorgesehene Kurse',
                total,
            }
        },
    },

    watch: {
        '$route.query.automatic_timetable': {
            immediate: true,
            handler(step) {
                this.showEvaluationSettings = this.isAutomaticTimetableStep(step)
            },
        },
    },

    methods: {
        async loadOverview() {
            await this.studentTimetablesStore.loadOverview()
            this.syncSelectionOverrideFromOverview()
        },
        async handleLogout() {
            this.showDrawer = false
            await this.studentTimetablesStore.logout()
            this.$router.push('/homepage/students-timetables')
        },
        openAutomaticTimetable() {
            this.showEvaluationSettings = true
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

            this.$router.push({
                path: this.$route.path,
                query: {
                    ...this.$route.query,
                    automatic_timetable: step,
                },
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

    .overview-selected-cards {
        grid-template-columns: 1fr;
    }

    .overview-selection {
        flex-direction: column;
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
