<template>
    <div class="students-timetable-v2-page">
        <v-row dense>
            <v-col cols="12" md="6">
                <v-card rounded="lg" class="students-timetable-v2-card">
                    <v-card-title>Studierende</v-card-title>
                    <v-card-text>
                        <div class="students-timetable-v2-student-context">
                            <div class="students-timetable-v2-student-context__title">
                                <v-icon icon="mdi-account-school-outline" size="18" color="primary" />
                                <span class="students-timetable-v2-student-context__student-label">
                                    {{ storedTimetableStudentLabel }}
                                </span>
                                <span class="students-timetable-v2-student-actions">
                                    <v-btn
                                        icon="mdi-pencil"
                                        variant="tonal"
                                        color="primary"
                                        density="comfortable"
                                        size="small"
                                        title="Student bearbeiten"
                                        aria-label="Student bearbeiten"
                                        @click.stop="openStudentDialog" />
                                    <v-btn
                                        v-if="storedTimetableStudentContext"
                                        icon="mdi-close-circle-outline"
                                        variant="text"
                                        color="error"
                                        density="comfortable"
                                        size="small"
                                        title="Student löschen"
                                        aria-label="Student löschen"
                                        @click.stop="clearStoredTimetableStudent" />
                                </span>
                            </div>
                        </div>
                        <div v-if="storedTimetableStudentContext" class="students-timetable-v2-completed-courses">
                            <div class="students-timetable-v2-completed-courses__title">
                                <v-icon icon="mdi-school-outline" size="18" color="primary" />
                                <span>Abgeschlossene Kurse</span>
                                <v-chip size="x-small" color="primary" variant="tonal">
                                    {{ storedCompletedCourseItems.length }}
                                </v-chip>
                            </div>
                            <v-progress-linear v-if="studentCompletedCoursesLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                            <v-alert v-else-if="studentCompletedCoursesError" type="error" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                                {{ studentCompletedCoursesError }}
                            </v-alert>
                            <div v-else-if="storedCompletedCourseItems.length" class="students-timetable-v2-completed-courses__list">
                                <div v-for="course in storedCompletedCourseItems" :key="course.key" class="students-timetable-v2-completed-courses__item">
                                    <span>{{ course.label }}</span>
                                    <v-chip v-if="course.meta" size="x-small" color="primary" variant="tonal">
                                        {{ course.meta }}
                                    </v-chip>
                                </div>
                            </div>
                            <v-alert v-else type="info" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                                Keine abgeschlossenen Kurse gefunden.
                            </v-alert>
                        </div>
                        <div v-if="storedTimetableStudentContext" class="students-timetable-v2-completed-courses">
                            <div class="students-timetable-v2-completed-courses__title">
                                <v-icon icon="mdi-alert-circle-outline" size="18" color="error" />
                                <span>Fehlende Kurse</span>
                                <v-chip size="x-small" color="error" variant="tonal">
                                    {{ storedMissingCourseItems.length }}
                                </v-chip>
                            </div>
                            <v-progress-linear v-if="studentCompletedCoursesLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                            <v-alert v-else-if="studentCompletedCoursesError" type="error" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                                {{ studentCompletedCoursesError }}
                            </v-alert>
                            <div v-else-if="storedMissingCourseItems.length" class="students-timetable-v2-completed-courses__list">
                                <div
                                    v-for="course in storedMissingCourseItems"
                                    :key="course.key"
                                    class="students-timetable-v2-completed-courses__item students-timetable-v2-completed-courses__item--missing">
                                    <span>{{ course.label }}</span>
                                    <v-chip v-if="course.meta" size="x-small" color="error" variant="tonal">
                                        {{ course.meta }}
                                    </v-chip>
                                </div>
                            </div>
                            <v-alert v-else type="info" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                                Keine fehlenden Kurse gefunden.
                            </v-alert>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" md="6">
                <v-card rounded="lg" class="students-timetable-v2-card">
                    <v-card-title>Auswahl</v-card-title>
                    <v-card-text>
                        <div v-if="storedTimetableStudentContext" class="students-timetable-v2-selection">
                            <div v-for="item in storedTimetableSelectionSummary" :key="item.key" class="students-timetable-v2-selection__item">
                                <span>{{ item.label }}</span>
                                <div v-if="item.options?.length" class="students-timetable-v2-selection__chips">
                                    <v-chip
                                        v-for="option in item.options"
                                        :key="option.value"
                                        size="small"
                                        :color="selectionOptionSelected(item, option) ? 'success' : 'secondary'"
                                        :variant="selectionOptionSelected(item, option) ? 'flat' : 'outlined'"
                                        class="students-timetable-v2-selection__chip"
                                        :aria-pressed="selectionOptionSelected(item, option) ? 'true' : 'false'"
                                        @click="selectTimetableSelectionOption(item.key, option.value)">
                                        {{ option.title }}
                                    </v-chip>
                                </div>
                                <strong v-else class="students-timetable-v2-selection__value" :class="{ 'students-timetable-v2-selection__value--unknown': !item.known }">
                                    {{ item.value }}
                                </strong>
                            </div>
                        </div>
                        <div v-else class="students-timetable-v2-selection__empty">Kein Student ausgewählt</div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" md="6">
                <v-card rounded="lg" class="students-timetable-v2-card">
                    <v-card-title>Vorgesehene Kurse</v-card-title>
                    <v-card-text>
                        <v-progress-linear v-if="studentCompletedCoursesLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                        <v-alert v-else-if="studentCompletedCoursesError" type="error" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                            {{ studentCompletedCoursesError }}
                        </v-alert>
                        <div v-else-if="storedTimetableStudentContext && storedPlannedCourseItems.length" class="students-timetable-v2-completed-courses__list">
                            <div
                                v-for="course in storedPlannedCourseItems"
                                :key="course.key"
                                class="students-timetable-v2-completed-courses__item students-timetable-v2-completed-courses__item--planned">
                                <span>{{ course.label }}</span>
                                <v-chip v-if="course.meta" size="x-small" color="success" variant="tonal">
                                    {{ course.meta }}
                                </v-chip>
                            </div>
                        </div>
                        <v-alert v-else-if="storedTimetableStudentContext" type="info" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                            Keine vorgesehenen Kurse gefunden.
                        </v-alert>
                        <div v-else class="students-timetable-v2-selection__empty">Kein Student ausgewählt</div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" md="6">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-card--empty">
                    <v-card-title>Zusätzliche Kurse</v-card-title>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="studentDialogOpen" persistent max-width="560">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-account-school-outline" />
                    Student bearbeiten
                </v-card-title>
                <v-card-text>
                    <div class="students-timetable-v2-student-dialog__meta">
                        {{ studentTotalCountLabel }}
                    </div>
                    <v-text-field
                        ref="studentSearchField"
                        v-model="studentSearch"
                        label="Student suchen"
                        variant="outlined"
                        density="compact"
                        :loading="studentOptionsLoading"
                        clearable
                        hide-details="auto"
                        @keydown.enter.prevent="submitStudentSearch" />
                    <div class="students-timetable-v2-student-search-results">
                        <v-btn
                            size="small"
                            variant="tonal"
                            :color="studentSelectionDraft.studentCode === null ? 'primary' : 'secondary'"
                            class="students-timetable-v2-student-search-results__item"
                            block
                            @click="selectStudentDraft(null)">
                            Kein Student
                        </v-btn>
                        <template v-if="studentSearchReady">
                            <v-btn
                                v-for="student in filteredStudentResults"
                                :key="student.student_code"
                                size="small"
                                variant="tonal"
                                :color="String(studentSelectionDraft.studentCode) === String(student.student_code) ? 'primary' : 'secondary'"
                                class="students-timetable-v2-student-search-results__item"
                                block
                                @click="selectStudentDraft(student.student_code)">
                                {{ studentOptionTitle(student) }}
                            </v-btn>
                            <div v-if="!filteredStudentResults.length" class="students-timetable-v2-student-search-results__empty">Keine Schüler gefunden</div>
                        </template>
                        <div v-else class="students-timetable-v2-student-search-results__empty">Mindestens 2 Zeichen eingeben</div>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeStudentDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" @click="updateStudentSelection">Aktualisieren</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

const TIMETABLE_STORAGE_KEY_PREFIX = 'students-timetables:overview:last-timetable'

export default {
    data() {
        return {
            robotStudents: [],
            storageRevision: 0,
            studentDialogOpen: false,
            studentCompletedCoursesError: '',
            studentCompletedCoursesLoading: false,
            studentOverviewActiveRequestKey: '',
            studentOverviewLoadedRequestKey: '',
            studentCompletedCoursesRequestId: 0,
            studentOptionsLoading: false,
            studentSearch: '',
            studentSelectionDraft: {
                studentCode: null,
            },
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        selectedSchoolyear() {
            return this.config?.selected_schoolyear || {}
        },
        storedTimetableStudentLabel() {
            const label = String(this.storedTimetableStudentContext?.student?.label || '').trim()
            const religion = String(this.storedTimetableStudentContext?.student?.religion || '').trim()

            if (!label) return 'Kein Student'

            return [label, religion].filter(Boolean).join(' · ')
        },
        storedTimetableStudentContext() {
            return this.storedTimetableState?.transferredStudentContext || null
        },
        storedTimetableStudentCode() {
            return this.normalizedStudentCode(this.storedTimetableStudentContext?.student?.studentCode)
        },
        storedCompletedCourseItems() {
            return this.normalizedCompletedCourseItems(this.storedTimetableStudentContext?.courses?.completed || [])
        },
        storedMissingCourseItems() {
            return this.normalizedMissingCourseItems(this.storedTimetableStudentContext?.courses?.failed || [])
        },
        storedPlannedCourseItems() {
            const courses = this.storedTimetableStudentContext?.courses || {}

            return this.uniqueCourseItems([...this.normalizedOverviewCourseItems(courses.missing || []), ...this.normalizedOverviewCourseItems(courses.planned || [])])
        },
        storedTimetableState() {
            this.storageRevision

            const storage = this.timetableStorage()
            if (!storage) return null

            return (
                this.timetableStorageKeys()
                    .map((key) => this.parseStoredTimetableState(storage.getItem(key)))
                    .find((state) => state !== null) || null
            )
        },
        normalizedStudentSearch() {
            return String(this.studentSearch || '')
                .trim()
                .toLowerCase()
        },
        studentSearchReady() {
            return this.normalizedStudentSearch.length >= 2
        },
        filteredStudentResults() {
            if (!this.studentSearchReady) return []

            return this.robotStudents.filter((student) => this.studentOptionTitle(student).toLowerCase().includes(this.normalizedStudentSearch))
        },
        studentTotalCountLabel() {
            const count = Array.isArray(this.robotStudents) ? this.robotStudents.length : 0

            return `${this.formatNumber(count)} Studenten gesamt`
        },
        storedTimetableV2Selection() {
            return this.storedTimetableState?.timetableV2Selection || {}
        },
        storedTimetableSelectionSummary() {
            const semesterLabel = this.storedTimetableStudentContext?.student?.semesterLabel

            return [
                {
                    key: 'semester',
                    label: 'Semester',
                    value: this.knownSelectionValue(semesterLabel),
                    known: this.selectionValueIsKnown(semesterLabel),
                },
                {
                    key: 'religion',
                    label: 'Ethik / Religion',
                    options: this.religionOptionsForSelectedStudent(),
                },
                {
                    key: 'language',
                    label: 'Sprache',
                    options: this.languageOptions(),
                },
                {
                    key: 'branch',
                    label: 'Zweig',
                    options: this.branchOptions(),
                },
                {
                    key: 'artsSubject',
                    label: 'ME / BE',
                    options: this.artsSubjectOptions(),
                },
            ]
        },
    },

    mounted() {
        this.loadStoredStudentOverview(this.storedTimetableStudentCode)
    },

    methods: {
        async openStudentDialog() {
            this.studentSelectionDraft = {
                studentCode: this.normalizedStudentCode(this.storedTimetableStudentContext?.student?.studentCode),
            }
            this.studentSearch = ''
            this.studentDialogOpen = true
            await this.loadRobotStudents()
            this.$nextTick(() => this.focusStudentSearchField())
        },
        closeStudentDialog() {
            this.studentDialogOpen = false
            this.studentSearch = ''
        },
        focusStudentSearchField() {
            const searchField = this.$refs.studentSearchField

            searchField?.focus?.()
            searchField?.$el?.querySelector?.('input')?.focus?.()
        },
        async loadRobotStudents() {
            if (this.robotStudents.length || this.studentOptionsLoading) return

            this.studentOptionsLoading = true
            try {
                const response = await axios.get('/api/admin/students-timetables/robot/students')

                this.robotStudents = response.data?.data || []
            } catch {
                this.robotStudents = []
            } finally {
                this.studentOptionsLoading = false
            }
        },
        async loadStoredStudentOverview(studentCode) {
            const normalizedStudentCode = this.normalizedStudentCode(studentCode)
            const selection = this.studentOverviewSelectionPayload()
            const requestKey = this.studentOverviewRequestKey(normalizedStudentCode, selection)

            if (!normalizedStudentCode) {
                this.studentCompletedCoursesError = ''
                this.studentCompletedCoursesLoading = false
                this.studentOverviewActiveRequestKey = ''
                this.studentOverviewLoadedRequestKey = ''

                return
            }

            if (requestKey && requestKey === this.studentOverviewActiveRequestKey) return

            if (requestKey && requestKey === this.studentOverviewLoadedRequestKey) {
                this.studentCompletedCoursesLoading = false

                return
            }

            const requestId = this.studentCompletedCoursesRequestId + 1
            this.studentCompletedCoursesRequestId = requestId

            this.studentOverviewActiveRequestKey = requestKey
            this.studentCompletedCoursesLoading = true
            this.studentCompletedCoursesError = ''

            try {
                const response = await axios.get('/api/admin/students-timetables/robot/student-overview', {
                    params: {
                        student_code: normalizedStudentCode,
                        ...(Object.keys(selection).length ? { selection } : {}),
                    },
                })

                if (requestId !== this.studentCompletedCoursesRequestId) return
                if (this.storedTimetableStudentCode !== normalizedStudentCode) return

                const overviewSummary = response.data?.data || {}
                const courseHistory = this.overviewStudentCourseHistoryFromSummary(overviewSummary)
                const storedState = this.storedTimetableStateForSaving()
                const timetableV2Selection = this.timetableV2SelectionWithCourseDefaults(storedState?.timetableV2Selection || {}, courseHistory.completed)

                this.saveStoredTimetableState({
                    ...this.defaultStoredTimetableState(),
                    ...storedState,
                    timetableV2Selection,
                    transferredStudentContext: {
                        ...this.storedTimetableStudentContext,
                        student: {
                            ...this.storedTimetableStudentContext?.student,
                            religion: overviewSummary?.student?.religion ?? this.storedTimetableStudentContext?.student?.religion,
                        },
                        courses: {
                            ...this.storedTimetableStudentContext?.courses,
                            ...courseHistory,
                        },
                    },
                })
                this.studentOverviewLoadedRequestKey = requestKey
            } catch {
                if (requestId !== this.studentCompletedCoursesRequestId) return

                this.studentCompletedCoursesError = 'Die abgeschlossenen Kurse konnten nicht geladen werden.'
            } finally {
                if (requestId === this.studentCompletedCoursesRequestId) {
                    this.studentOverviewActiveRequestKey = ''
                    this.studentCompletedCoursesLoading = false
                }
            }
        },
        selectStudentDraft(studentCode) {
            this.studentSelectionDraft.studentCode = this.normalizedStudentCode(studentCode)
        },
        submitStudentSearch() {
            if (this.studentSearchReady && this.filteredStudentResults.length === 1) {
                this.selectStudentDraft(this.filteredStudentResults[0].student_code)
            }

            this.updateStudentSelection()
        },
        updateStudentSelection() {
            const studentCode = this.normalizedStudentCode(this.studentSelectionDraft.studentCode)
            const selectedStudent = studentCode ? this.robotStudents.find((student) => String(student.student_code) === studentCode) : null

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                selection: this.updatedStoredSelection(selectedStudent),
                timetableV2Selection: {},
                transferredStudentContext: selectedStudent ? this.transferredStudentContextFromRobotStudent(selectedStudent) : null,
            })
            this.closeStudentDialog()
            this.studentOverviewLoadedRequestKey = ''
            this.loadStoredStudentOverview(studentCode)
        },
        clearStoredTimetableStudent() {
            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Selection: {},
                transferredStudentContext: null,
            })
            this.studentOverviewActiveRequestKey = ''
            this.studentOverviewLoadedRequestKey = ''
            this.studentCompletedCoursesLoading = false
        },
        selectTimetableSelectionOption(key, value) {
            if (!this.storedTimetableStudentContext) return

            const timetableV2Selection = { ...this.storedTimetableV2Selection }
            if (String(timetableV2Selection[key] || '') === String(value)) {
                timetableV2Selection[key] = null
            } else {
                timetableV2Selection[key] = value
            }

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Selection,
                transferredStudentContext: this.storedTimetableStudentContext,
            })
            this.studentOverviewLoadedRequestKey = ''
            this.loadStoredStudentOverview(this.storedTimetableStudentCode)
        },
        selectionOptionSelected(item, option) {
            return String(this.storedTimetableV2Selection?.[item.key] || '') === String(option.value)
        },
        updatedStoredSelection(selectedStudent) {
            const currentSelection = this.storedTimetableStateForSaving()?.selection || {}
            const semester = this.studentSemester(selectedStudent)

            if (!semester) return currentSelection

            return {
                ...currentSelection,
                semester,
            }
        },
        storedTimetableStateForSaving() {
            return this.storedTimetableState || this.parseStoredTimetableState(this.timetableStorage()?.getItem(this.timetableStorageKey())) || null
        },
        saveStoredTimetableState(state) {
            try {
                this.timetableStorage()?.setItem(this.timetableStorageKey(), JSON.stringify(state))
            } catch {
                // Ignore unavailable or full browser storage.
            }

            this.storageRevision++
        },
        defaultStoredTimetableState() {
            return {
                selection: {
                    semester: 1,
                    religion: 'ETH',
                    language: 'L',
                    branch: 'wirtschaftskundlich',
                    artsSubject: 'ME',
                },
                timetableV2Selection: {},
                transferredStudentContext: null,
            }
        },
        knownSelectionValue(value = null) {
            const normalizedValue = String(value || '').trim()

            return normalizedValue || '--'
        },
        selectionValueIsKnown(value) {
            return String(value || '').trim() !== ''
        },
        completedCourseItemsFromApi(courses) {
            return this.normalizedCompletedCourseItems(courses)
        },
        missingCourseItemsFromApi(courses) {
            return this.normalizedMissingCourseItems(courses)
        },
        overviewStudentCourseHistoryFromSummary(overviewSummary) {
            const automaticCourseSections = Array.isArray(overviewSummary?.automatic_course_selection?.sections) ? overviewSummary.automatic_course_selection.sections : []
            const automaticMissingCourses = automaticCourseSections.find((section) => section?.key === 'missing')?.items
            const automaticPlannedCourses = automaticCourseSections.find((section) => section?.key === 'proposed')?.items

            return {
                completed: this.completedCourseItemsFromApi(overviewSummary?.completed_courses || []),
                failed: this.missingCourseItemsFromApi(overviewSummary?.completed_courses || []),
                missing: this.normalizedOverviewCourseItems(automaticMissingCourses || overviewSummary?.missing_courses || []),
                planned: this.normalizedOverviewCourseItems(automaticPlannedCourses || overviewSummary?.proposed_courses || []),
                additional: this.normalizedOverviewCourseItems(overviewSummary?.additional_courses || []),
            }
        },
        normalizedCompletedCourseItems(courses) {
            return (Array.isArray(courses) ? courses : [])
                .map((course, index) => {
                    const code = String(course?.code || course?.subject || '').trim()
                    const label = String(course?.label || code || course?.name || '').trim()
                    const grade = String(course?.meta || course?.grade || '').trim()

                    return {
                        key: String(course?.key || `completed-${label || index}-${grade || index}`).trim(),
                        code,
                        label,
                        meta: grade,
                    }
                })
                .filter((course) => course.label)
                .filter((course) => this.completedCourseGradeIsAccepted(course.meta))
        },
        normalizedMissingCourseItems(courses) {
            return (Array.isArray(courses) ? courses : [])
                .map((course, index) => {
                    const code = String(course?.code || course?.subject || '').trim()
                    const label = String(course?.label || code || course?.name || '').trim()
                    const grade = String(course?.meta || course?.grade || '').trim()

                    return {
                        key: String(course?.key || `missing-${label || index}-${grade || index}`).trim(),
                        code,
                        label,
                        meta: grade,
                    }
                })
                .filter((course) => course.label)
                .filter((course) => this.missingCourseGradeIsAccepted(course.meta))
        },
        normalizedOverviewCourseItems(courses) {
            return (Array.isArray(courses) ? courses : [])
                .map((course, index) => {
                    const code = String(course?.code || course?.subject || '').trim()
                    const name = String(course?.name || course?.title || '').trim()
                    const label = String(course?.label || code || name).trim()
                    const hours = Number(course?.hours || course?.hours_per_week || 0)
                    const meta = String(course?.meta || course?.hours_label || (hours ? `${this.formatHours(hours)} Std.` : '')).trim()

                    return {
                        key: String(course?.key || `${code || label || index}-${index}`).trim(),
                        code,
                        name,
                        label,
                        meta,
                    }
                })
                .filter((course) => course.label)
        },
        uniqueCourseItems(courses) {
            const courseItemsByKey = new Map()
            const courseItems = Array.isArray(courses) ? courses : []

            courseItems.forEach((course) => {
                const key = String(course?.key || course?.code || course?.label || '').trim()
                if (!key || courseItemsByKey.has(key)) return

                courseItemsByKey.set(key, course)
            })

            return [...courseItemsByKey.values()]
        },
        completedCourseGradeIsAccepted(grade) {
            const normalizedGrade = String(grade || '')
                .trim()
                .toLocaleUpperCase('de-AT')

            return normalizedGrade !== '' && !['5', 'N'].includes(normalizedGrade)
        },
        missingCourseGradeIsAccepted(grade) {
            const normalizedGrade = String(grade || '')
                .trim()
                .toLocaleUpperCase('de-AT')

            return ['5', 'N'].includes(normalizedGrade)
        },
        timetableV2SelectionWithCourseDefaults(selection, completedCourses) {
            const courseDefaults = this.selectedStudentCourseHistoryDefaults(completedCourses)

            return Object.entries(courseDefaults).reduce(
                (nextSelection, [key, value]) => {
                    if (!Object.prototype.hasOwnProperty.call(nextSelection, key)) {
                        nextSelection[key] = value
                    }

                    return nextSelection
                },
                { ...(selection || {}) }
            )
        },
        studentOverviewSelectionPayload() {
            return this.studentOverviewSelectionPayloadForSelection(this.storedTimetableV2Selection || {})
        },
        studentOverviewSelectionPayloadForSelection(selection) {
            const semester = this.semesterValueFromLabel(this.storedTimetableStudentContext?.student?.semesterLabel)

            return Object.fromEntries(
                [
                    ['semester', semester],
                    ['religion', selection.religion],
                    ['language', selection.language],
                    ['branch', selection.branch],
                    ['artsSubject', selection.artsSubject],
                ].filter(([, value]) => String(value || '').trim() !== '')
            )
        },
        studentOverviewRequestKey(studentCode, selection) {
            return JSON.stringify({
                studentCode: this.normalizedStudentCode(studentCode),
                selection,
            })
        },
        selectedStudentCourseHistoryDefaults(completedCourses) {
            const completedCourseCodes = this.studentCourseCodeSet(completedCourses)

            return Object.fromEntries(
                [
                    ['religion', this.inferredSelectionOptionFromCourseCodes(this.religionOptions(), completedCourseCodes)],
                    ['language', this.inferredSelectionOptionFromCourseCodes(this.languageOptions(), completedCourseCodes)],
                    ['branch', this.inferredBranchFromCourseCodes(completedCourseCodes)],
                    ['artsSubject', this.inferredSelectionOptionFromCourseCodes(this.artsSubjectOptions(), completedCourseCodes)],
                ].filter(([, value]) => Boolean(value))
            )
        },
        studentCourseCodeSet(courses) {
            return new Set(
                (Array.isArray(courses) ? courses : [])
                    .flatMap((course) => this.courseCodesFromCourse(course))
                    .map((courseCode) => this.normalizedCourseCode(courseCode))
                    .filter(Boolean)
            )
        },
        courseCodesFromCourse(course) {
            return this.uniqueValues([course?.code, course?.label, course?.name].flatMap((value) => this.courseCodeTokensFromValue(value)))
        },
        courseCodeTokensFromValue(value) {
            return (
                String(value || '')
                    .toLocaleUpperCase('de-AT')
                    .match(/[A-ZÄÖÜ]+[0-9]*/gu) || []
            )
        },
        inferredSelectionOptionFromCourseCodes(options, courseCodes) {
            if (!(courseCodes instanceof Set) || !courseCodes.size) return ''

            return (
                (Array.isArray(options) ? options : [])
                    .map((option, optionIndex) => ({
                        option,
                        optionIndex,
                        match: this.bestSelectionOptionCourseCodeMatch(option?.value, courseCodes),
                    }))
                    .filter(({ match }) => match)
                    .sort(
                        (firstOption, secondOption) =>
                            secondOption.match.module - firstOption.match.module ||
                            firstOption.optionIndex - secondOption.optionIndex ||
                            secondOption.match.courseIndex - firstOption.match.courseIndex
                    )[0]?.option?.value || ''
            )
        },
        bestSelectionOptionCourseCodeMatch(value, courseCodes) {
            const optionAliases = this.selectionCourseAliases(value).map((alias) => this.courseCodeWithoutModule(alias))
            if (!optionAliases.length) return null

            return (
                [...courseCodes]
                    .map((courseCode, courseIndex) => ({
                        ...this.courseCodeModuleParts(courseCode),
                        courseIndex,
                    }))
                    .filter((parts) => optionAliases.includes(parts.base))
                    .map((parts) => ({
                        module: Number(parts.module || 0),
                        courseIndex: parts.courseIndex,
                    }))
                    .sort((firstMatch, secondMatch) => secondMatch.module - firstMatch.module || secondMatch.courseIndex - firstMatch.courseIndex)[0] || null
            )
        },
        inferredBranchFromCourseCodes(courseCodes) {
            if (!(courseCodes instanceof Set) || !courseCodes.size) return ''

            const branchAliases = [
                {
                    value: 'wirtschaftskundlich',
                    aliases: ['INF', 'OKO', 'OEKO', 'OEK', 'WIKU', 'BWL', 'RW', 'WR'],
                },
            ]
            const matchingBranch = branchAliases
                .map((branch, branchIndex) => ({
                    branch,
                    branchIndex,
                    match: this.bestSelectionAliasesCourseCodeMatch(branch.aliases, courseCodes),
                }))
                .filter(({ match }) => match)
                .sort(
                    (firstBranch, secondBranch) =>
                        secondBranch.match.module - firstBranch.match.module ||
                        firstBranch.branchIndex - secondBranch.branchIndex ||
                        secondBranch.match.courseIndex - firstBranch.match.courseIndex
                )[0]

            return matchingBranch?.branch?.value || ''
        },
        bestSelectionAliasesCourseCodeMatch(aliases, courseCodes) {
            const optionAliases = (Array.isArray(aliases) ? aliases : []).flatMap((alias) => this.selectionCourseAliases(alias)).map((alias) => this.courseCodeWithoutModule(alias))
            if (!optionAliases.length) return null

            return (
                [...courseCodes]
                    .map((courseCode, courseIndex) => ({
                        ...this.courseCodeModuleParts(courseCode),
                        courseIndex,
                    }))
                    .filter((parts) => optionAliases.includes(parts.base))
                    .map((parts) => ({
                        module: Number(parts.module || 0),
                        courseIndex: parts.courseIndex,
                    }))
                    .sort((firstMatch, secondMatch) => secondMatch.module - firstMatch.module || secondMatch.courseIndex - firstMatch.courseIndex)[0] || null
            )
        },
        selectionCourseAliases(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const aliases = {
                BE: ['BE'],
                ET: ['ETH', 'ET'],
                ETH: ['ETH', 'ET'],
                F: ['F', 'FR', 'FRA', 'FRZ'],
                INF: ['INF'],
                L: ['L', 'LET', 'LPT'],
                LET: ['L', 'LET', 'LPT'],
                LPT: ['L', 'LET', 'LPT'],
                ME: ['ME', 'MU'],
                MU: ['ME', 'MU'],
                R: ['RK', 'R'],
                REV: ['REV', 'EV', 'EVANG'],
                RIS: ['RIS', 'ISLAM'],
                RK: ['RK', 'R'],
                RKATH: ['RK', 'R'],
                ROR: ['ROR', 'ORTH'],
                S: ['S', 'SPA'],
                SPA: ['S', 'SPA'],
            }

            return this.uniqueValues([normalizedValue, ...(aliases[normalizedValue] || [])].filter(Boolean))
        },
        courseCodeWithoutModule(value) {
            return this.courseCodeModuleParts(value).base
        },
        courseCodeModuleParts(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const match = normalizedValue.match(/^([A-Z]+)([0-9]*)$/u)

            if (!match) {
                return {
                    base: normalizedValue,
                    module: '',
                }
            }

            return {
                base: match[1],
                module: match[2] || '',
            }
        },
        normalizedCourseCode(value) {
            return String(value || '')
                .trim()
                .toLocaleUpperCase('de-AT')
                .replace(/Ä/gu, 'AE')
                .replace(/Ö/gu, 'OE')
                .replace(/Ü/gu, 'UE')
                .replace(/ß/gu, 'SS')
                .replace(/[^A-Z0-9]/gu, '')
        },
        uniqueValues(values) {
            return (Array.isArray(values) ? values : []).filter((value, index, allValues) => allValues.indexOf(value) === index)
        },
        religionOptions() {
            return [
                { title: 'ETH - Ethik', value: 'ETH' },
                { title: 'Rev - Religion evangelisch', value: 'Rev' },
                { title: 'Ris - Religion Islam', value: 'Ris' },
                { title: 'Rk - Religion katholisch', value: 'Rk' },
                { title: 'Ror - Religion orthodox', value: 'Ror' },
            ]
        },
        religionOptionsForSelectedStudent() {
            const options = this.religionOptions()
            const religion = this.storedTimetableStudentReligion()

            if (!religion || this.studentReligionMatchesNoConfession(religion)) return options

            const allowedValues = ['ETH', this.studentReligionOptionValue(religion)].filter(Boolean)

            return options.filter((option) => allowedValues.includes(option.value))
        },
        storedTimetableStudentReligion() {
            return String(this.storedTimetableStudentContext?.student?.religion || '').trim()
        },
        normalizedStudentReligion(value) {
            return String(value || '')
                .trim()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/gu, '')
                .replace(/ß/gu, 'ss')
                .replace(/[^a-z0-9]/gu, '')
        },
        studentReligionMatchesNoConfession(religion) {
            const normalizedReligion = this.normalizedStudentReligion(religion)

            return ['ob', 'ohnebekenntnis', 'ohnebekenntniss', 'keinbekenntnis', 'keinbekenntniss', 'konfessionslos'].includes(normalizedReligion)
        },
        studentReligionOptionValue(religion) {
            const normalizedReligion = this.normalizedStudentReligion(religion)
            const matchingReligionOption = this.studentReligionOptionAliases().find((option) =>
                option.aliases.some(
                    (alias) =>
                        normalizedReligion === alias ||
                        (alias.length >= 4 && normalizedReligion.includes(alias)) ||
                        (normalizedReligion.length >= 4 && alias.includes(normalizedReligion))
                )
            )

            return matchingReligionOption?.value || null
        },
        studentReligionOptionAliases() {
            return [
                {
                    value: 'Rev',
                    aliases: ['rev', 'ev', 'evang', 'evangelisch', 'evangab', 'evangelischab'],
                },
                {
                    value: 'Ris',
                    aliases: ['ris', 'islam', 'islamisch', 'muslim', 'moslem'],
                },
                {
                    value: 'Rk',
                    aliases: ['rk', 'kath', 'katholisch', 'romkath', 'roemkath', 'roemischkatholisch'],
                },
                {
                    value: 'Ror',
                    aliases: ['ror', 'orth', 'orthodox', 'griechorth', 'griechischorthodox'],
                },
            ]
        },
        languageOptions() {
            return [
                { title: 'L - Latein', value: 'L' },
                { title: 'F - Französisch', value: 'F' },
                { title: 'S - Spanisch', value: 'S' },
            ]
        },
        branchOptions() {
            return [
                { title: 'Wirtschaftskundlicher Zweig', value: 'wirtschaftskundlich' },
                { title: 'Gymnasialer Zweig', value: 'gymnasial' },
            ]
        },
        artsSubjectOptions() {
            return [
                { title: 'ME - Musikerziehung', value: 'ME' },
                { title: 'BE - Bildnerische Erziehung', value: 'BE' },
            ]
        },
        timetableStorageKeys() {
            const schoolyearId = this.selectedSchoolyear?.id || 'default'

            return [`${TIMETABLE_STORAGE_KEY_PREFIX}:${schoolyearId}`, `${TIMETABLE_STORAGE_KEY_PREFIX}:default`].filter((key, index, keys) => keys.indexOf(key) === index)
        },
        timetableStorageKey() {
            return `${TIMETABLE_STORAGE_KEY_PREFIX}:${this.selectedSchoolyear?.id || 'default'}`
        },
        timetableStorage() {
            if (typeof window === 'undefined' || !window.localStorage) {
                return null
            }

            return window.localStorage
        },
        parseStoredTimetableState(value) {
            if (!value) return null

            try {
                return JSON.parse(value)
            } catch {
                return null
            }
        },
        transferredStudentContextFromRobotStudent(student) {
            if (!student) return null

            return {
                student: {
                    studentCode: this.normalizedStudentCode(student.student_code),
                    label: this.studentOptionTitle(student),
                    semesterLabel: this.studentSemesterLabel(student),
                    religion: String(student.religion || '').trim(),
                    email: String(student.email || '').trim(),
                },
                courses: {
                    completed: [],
                    missing: [],
                    planned: [],
                    additional: [],
                },
            }
        },
        studentOptionTitle(student) {
            const schoolClass = String(student?.class || '').trim()
            const lastName = String(student?.last_name || '').trim()
            const firstName = String(student?.first_name || '').trim()
            const semester = this.studentSemesterLabel(student)
            const name = [lastName, firstName].filter(Boolean).join(' ')

            return [schoolClass, name, semester].filter(Boolean).join(' · ')
        },
        studentSemesterLabel(student) {
            const semester = this.studentSemester(student)

            return semester ? `Semester ${semester}` : ''
        },
        semesterValueFromLabel(value) {
            const semesterMatch = String(value || '').match(/\d+/u)
            const semester = Number(semesterMatch?.[0] || 0)

            return Number.isFinite(semester) && semester > 0 ? semester : null
        },
        studentSemester(student) {
            const schoolLevel = this.studentSchoolLevelKey(student)

            return this.studentSemesterBySchoolLevel()[schoolLevel] || null
        },
        studentSemesterBySchoolLevel() {
            return {
                '09_1': 1,
                '09_2': 2,
                '10_1': 3,
                '10_2': 4,
                '11_1': 5,
                '11_2': 6,
                '12_1': 7,
                '12_2': 8,
            }
        },
        studentSchoolLevelKey(student) {
            const importedSchoolLevel = this.normalizedStudentSchoolLevel(student?.school_level, student?.attendance_year)

            if (importedSchoolLevel) return importedSchoolLevel

            const schoolClass = String(student?.class || '').trim()
            const schoolLevelMatch = schoolClass.match(/^(\d+)[._-]?([12])?/u)
            if (!schoolLevelMatch) return ''

            return `${schoolLevelMatch[1].padStart(2, '0')}_${schoolLevelMatch[2] || '1'}`
        },
        normalizedStudentSchoolLevel(schoolLevel, attendanceYear) {
            const normalizedSchoolLevel = String(schoolLevel || '').trim()

            if (normalizedSchoolLevel) return normalizedSchoolLevel.replace('.', '_')

            const normalizedAttendanceYear = String(attendanceYear || '').trim()
            const attendanceYearMatch = normalizedAttendanceYear.match(/^(\d+)[._-]?([12])?$/u)

            if (!attendanceYearMatch) return ''

            return `${attendanceYearMatch[1].padStart(2, '0')}_${attendanceYearMatch[2] || '1'}`
        },
        normalizedStudentCode(value) {
            const studentCode = value === null || value === undefined ? '' : String(value).trim()

            return studentCode === '' ? null : studentCode
        },
        formatNumber(value) {
            return Number(value || 0).toLocaleString('de-AT')
        },
        formatHours(value) {
            const hours = Number(value || 0)

            if (!Number.isFinite(hours)) return '0'

            return Number.isInteger(hours) ? String(hours) : hours.toLocaleString('de-AT', { maximumFractionDigits: 2 })
        },
    },
}
</script>

<style scoped>
.students-timetable-v2-card {
    border: 1px solid rgba(37, 99, 235, 0.12);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
}

.students-timetable-v2-card--empty {
    min-height: 120px;
}

.students-timetable-v2-student-context {
    display: grid;
    gap: 10px;
    border: 1px solid rgba(14, 165, 233, 0.22);
    border-radius: 8px;
    padding: 10px;
    background: #f0f9ff;
}

.students-timetable-v2-student-context__title {
    display: flex;
    align-items: center;
    min-width: 0;
    flex-wrap: wrap;
    gap: 8px;
    color: #0f172a;
    font-weight: 800;
}

.students-timetable-v2-student-context__student-label {
    font-size: 1.08rem;
    line-height: 1.3;
}

.students-timetable-v2-student-actions {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 6px;
}

.students-timetable-v2-completed-courses {
    display: grid;
    gap: 8px;
    margin-top: 12px;
}

.students-timetable-v2-completed-courses__title {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #0f172a;
    font-size: 0.92rem;
    font-weight: 800;
}

.students-timetable-v2-completed-courses__list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.students-timetable-v2-completed-courses__item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(14, 165, 233, 0.18);
    border-radius: 8px;
    padding: 5px 7px;
    background: rgba(248, 250, 252, 0.88);
    color: #0f172a;
    font-size: 0.78rem;
    font-weight: 700;
}

.students-timetable-v2-completed-courses__item--missing {
    border-color: rgba(220, 38, 38, 0.18);
    background: rgba(254, 242, 242, 0.78);
}

.students-timetable-v2-completed-courses__loading,
.students-timetable-v2-completed-courses__alert {
    margin-top: 2px;
}

.students-timetable-v2-student-dialog__meta {
    margin-bottom: 10px;
    color: rgba(var(--v-theme-on-surface), 0.68);
    font-size: 0.78rem;
    font-weight: 700;
}

.students-timetable-v2-student-search-results {
    display: grid;
    gap: 6px;
    max-height: 260px;
    margin-top: 10px;
    overflow-y: auto;
}

.students-timetable-v2-student-search-results__item {
    justify-content: flex-start;
    min-height: 32px;
}

.students-timetable-v2-student-search-results__empty {
    padding: 8px 2px;
    color: rgba(var(--v-theme-on-surface), 0.58);
    font-size: 0.78rem;
}

.students-timetable-v2-selection {
    display: grid;
    gap: 10px;
}

.students-timetable-v2-selection__item {
    display: grid;
    gap: 2px;
}

.students-timetable-v2-selection__item span {
    color: rgba(0, 0, 0, 0.6);
    font-size: 0.78rem;
}

.students-timetable-v2-selection__item strong {
    font-size: 0.92rem;
    font-weight: 600;
}

.students-timetable-v2-selection__value {
    width: fit-content;
}

.students-timetable-v2-selection__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.students-timetable-v2-selection__chip {
    cursor: pointer;
    font-weight: 700;
}

.students-timetable-v2-selection__value--unknown {
    border: 1px solid rgba(100, 116, 139, 0.24);
    border-radius: 999px;
    padding: 1px 8px 2px;
    background: rgba(148, 163, 184, 0.14);
    color: rgba(71, 85, 105, 0.72);
    font-size: 0.82rem;
    line-height: 1.2;
    letter-spacing: 0;
}

.students-timetable-v2-selection__empty {
    color: rgba(0, 0, 0, 0.58);
    font-size: 0.86rem;
    font-weight: 600;
}
</style>
