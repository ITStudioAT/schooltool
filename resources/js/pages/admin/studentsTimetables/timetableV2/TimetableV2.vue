<template>
    <div class="students-timetable-v2-page">
        <v-row dense align="stretch">
            <v-col v-if="startCardVisible" cols="12" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-start-card">
                    <v-card-title>Start</v-card-title>
                    <v-card-text>
                        <div class="students-timetable-v2-start-card__actions">
                            <v-btn
                                color="primary"
                                variant="flat"
                                size="x-large"
                                class="students-timetable-v2-start-card__button"
                                prepend-icon="mdi-account-school-outline"
                                @click="startWithStudent">
                                Mit Studierenden
                            </v-btn>
                            <v-btn
                                color="secondary"
                                variant="tonal"
                                size="x-large"
                                class="students-timetable-v2-start-card__button"
                                prepend-icon="mdi-account-off-outline"
                                @click="startWithoutStudent">
                                Ohne Studierenden
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="studentCardVisible" cols="12" md="6" class="students-timetable-v2-card-column">
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

            <v-col v-if="withoutStudentBackgroundVisible" cols="12" md="6" class="students-timetable-v2-without-student-background-column" aria-hidden="true">
                <div class="students-timetable-v2-without-student-watermark">
                    <v-icon icon="mdi-account-off-outline" class="students-timetable-v2-without-student-watermark__icon" />
                    <span>Ohne Studierenden</span>
                </div>
            </v-col>

            <v-col v-if="selectionCardVisible" cols="12" md="6" :offset-md="selectionCardOffsetMd" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card">
                    <v-card-title>Auswahl</v-card-title>
                    <v-card-text>
                        <div v-if="selectionCardVisible" class="students-timetable-v2-selection">
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

            <div v-if="courseCardsVisible" class="students-timetable-v2-row-break" aria-hidden="true"></div>

            <v-col v-if="missingCourseCardVisible" cols="12" :md="courseCardMdColumns" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card">
                    <v-card-title class="students-timetable-v2-course-card-title">
                        <span>Fehlende Kurse</span>
                        <span class="students-timetable-v2-course-card-title__chips">
                            <v-chip size="x-small" color="error" variant="tonal">
                                {{ storedMissingCourseCardSummary.countLabel }}
                            </v-chip>
                            <v-chip size="x-small" color="error" variant="tonal">
                                {{ storedMissingCourseCardSummary.hoursLabel }}
                            </v-chip>
                        </span>
                    </v-card-title>
                    <v-card-text>
                        <v-progress-linear v-if="studentCompletedCoursesLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                        <v-progress-linear v-else-if="subjectRowsLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                        <v-alert v-else-if="studentCompletedCoursesError || subjectRowsError" type="error" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                            {{ studentCompletedCoursesError || subjectRowsError }}
                        </v-alert>
                        <div v-else class="students-timetable-v2-completed-courses__list">
                            <v-chip
                                v-for="course in storedMissingCourseCardItems"
                                :key="course.key"
                                :color="courseItemSelected(course, 'missing') ? 'error' : 'secondary'"
                                :variant="courseItemSelected(course, 'missing') ? 'tonal' : 'outlined'"
                                class="students-timetable-v2-completed-courses__item students-timetable-v2-completed-courses__item--missing students-timetable-v2-completed-courses__item--toggle"
                                :class="{ 'students-timetable-v2-completed-courses__item--deselected': !courseItemSelected(course, 'missing') }"
                                role="button"
                                :aria-pressed="courseItemSelected(course, 'missing') ? 'true' : 'false'"
                                @click="toggleCourseItem(course, 'missing')">
                                <v-icon v-if="courseItemSelected(course, 'missing')" icon="mdi-check" size="14" />
                                <span>{{ course.label }}</span>
                                <span v-if="course.hoursMeta" class="students-timetable-v2-completed-courses__item-meta">
                                    {{ course.hoursMeta }}
                                </span>
                            </v-chip>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="courseCardsVisible" cols="12" :md="courseCardMdColumns" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card">
                    <v-card-title class="students-timetable-v2-course-card-title">
                        <span>Vorgesehene Kurse</span>
                        <span class="students-timetable-v2-course-card-title__chips">
                            <v-chip size="x-small" color="success" variant="tonal">
                                {{ storedPlannedCourseSummary.countLabel }}
                            </v-chip>
                            <v-chip size="x-small" color="success" variant="tonal">
                                {{ storedPlannedCourseSummary.hoursLabel }}
                            </v-chip>
                        </span>
                    </v-card-title>
                    <v-card-text>
                        <v-progress-linear v-if="studentCompletedCoursesLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                        <v-progress-linear v-else-if="subjectRowsLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                        <v-alert v-else-if="studentCompletedCoursesError || subjectRowsError" type="error" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                            {{ studentCompletedCoursesError || subjectRowsError }}
                        </v-alert>
                        <div v-else-if="courseCardsVisible && storedPlannedCourseItems.length" class="students-timetable-v2-completed-courses__list">
                            <v-chip
                                v-for="course in storedPlannedCourseItems"
                                :key="course.key"
                                :color="courseItemSelected(course, 'planned') ? 'success' : 'secondary'"
                                :variant="courseItemSelected(course, 'planned') ? 'tonal' : 'outlined'"
                                class="students-timetable-v2-completed-courses__item students-timetable-v2-completed-courses__item--planned students-timetable-v2-completed-courses__item--toggle"
                                :class="{ 'students-timetable-v2-completed-courses__item--deselected': !courseItemSelected(course, 'planned') }"
                                role="button"
                                :aria-pressed="courseItemSelected(course, 'planned') ? 'true' : 'false'"
                                @click="toggleCourseItem(course, 'planned')">
                                <v-icon v-if="courseItemSelected(course, 'planned')" icon="mdi-check" size="14" />
                                <span>{{ course.label }}</span>
                                <span v-if="course.meta" class="students-timetable-v2-completed-courses__item-meta">
                                    {{ course.meta }}
                                </span>
                            </v-chip>
                        </div>
                        <v-alert v-else-if="courseCardsVisible" type="info" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                            Keine vorgesehenen Kurse gefunden.
                        </v-alert>
                        <div v-else class="students-timetable-v2-selection__empty">Kein Student ausgewählt</div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="courseCardsVisible" cols="12" :md="courseCardMdColumns" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card">
                    <v-card-title class="students-timetable-v2-course-card-title">
                        <span>Zusätzliche Kurse</span>
                        <span class="students-timetable-v2-course-card-title__chips">
                            <v-chip size="x-small" color="info" variant="tonal">
                                {{ storedAdditionalCourseSummary.countLabel }}
                            </v-chip>
                            <v-chip size="x-small" color="info" variant="tonal">
                                {{ storedAdditionalCourseSummary.hoursLabel }}
                            </v-chip>
                        </span>
                    </v-card-title>
                    <v-card-text>
                        <v-progress-linear v-if="studentCompletedCoursesLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                        <v-progress-linear v-else-if="subjectRowsLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                        <v-alert v-else-if="studentCompletedCoursesError || subjectRowsError" type="error" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                            {{ studentCompletedCoursesError || subjectRowsError }}
                        </v-alert>
                        <div v-else-if="courseCardsVisible && storedAdditionalCourseItems.length" class="students-timetable-v2-completed-courses__list">
                            <v-chip
                                v-for="course in storedAdditionalCourseItems"
                                :key="course.key"
                                :color="courseItemSelected(course, 'additional') ? 'info' : 'secondary'"
                                :variant="courseItemSelected(course, 'additional') ? 'tonal' : 'outlined'"
                                class="students-timetable-v2-completed-courses__item students-timetable-v2-completed-courses__item--additional students-timetable-v2-completed-courses__item--toggle"
                                :class="{ 'students-timetable-v2-completed-courses__item--deselected': !courseItemSelected(course, 'additional') }"
                                role="button"
                                :aria-pressed="courseItemSelected(course, 'additional') ? 'true' : 'false'"
                                @click="toggleCourseItem(course, 'additional')">
                                <v-icon v-if="courseItemSelected(course, 'additional')" icon="mdi-check" size="14" />
                                <span>{{ course.label }}</span>
                                <span v-if="course.meta" class="students-timetable-v2-completed-courses__item-meta">
                                    {{ course.meta }}
                                </span>
                            </v-chip>
                        </div>
                        <v-alert v-else-if="courseCardsVisible" type="info" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                            Keine zusätzlichen Kurse gefunden.
                        </v-alert>
                        <div v-else class="students-timetable-v2-selection__empty">Kein Student ausgewählt</div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="restartCardVisible" cols="12" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-restart-card">
                    <v-card-text class="students-timetable-v2-restart-card__content">
                        <v-btn
                            color="error"
                            variant="tonal"
                            size="large"
                            prepend-icon="mdi-restart"
                            @click="restartTimetableV2">
                            Neustart
                        </v-btn>
                        <v-btn
                            color="success"
                            variant="tonal"
                            size="large"
                            class="students-timetable-v2-restart-card__automatic-button"
                            append-icon="mdi-arrow-right"
                            @click="openAutomaticTimetable">
                            Weiter
                        </v-btn>
                    </v-card-text>
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
            subjectRows: [],
            subjectRowsError: '',
            subjectRowsLoading: false,
            timetableStartMode: '',
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        selectedSchoolyear() {
            return this.config?.selected_schoolyear || {}
        },
        startCardVisible() {
            return !this.storedTimetableStudentContext && this.timetableStartMode === ''
        },
        studentCardVisible() {
            return Boolean(this.storedTimetableStudentContext) || this.timetableStartMode === 'student'
        },
        courseCardsVisible() {
            return Boolean(this.storedTimetableStudentContext) || (this.timetableStartMode === 'without-student' && Boolean(this.noStudentSelectedSemester))
        },
        missingCourseCardVisible() {
            return this.courseCardsVisible && this.storedMissingCourseCardItems.length > 0
        },
        courseCardMdColumns() {
            return this.missingCourseCardVisible ? 4 : 6
        },
        selectionCardVisible() {
            return Boolean(this.storedTimetableStudentContext) || this.timetableStartMode === 'without-student'
        },
        withoutStudentBackgroundVisible() {
            return !this.storedTimetableStudentContext && this.timetableStartMode === 'without-student'
        },
        selectionCardOffsetMd() {
            return 0
        },
        restartCardVisible() {
            return Boolean(this.storedTimetableStudentContext) || this.timetableStartMode !== ''
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
        storedMissingCourseCardItems() {
            const courses = this.storedTimetableStudentContext?.courses || {}
            const overviewCoursesByCode = new Map(
                this.normalizedOverviewCourseItems([
                    ...(courses.missing || []),
                    ...(courses.planned || []),
                    ...(courses.additional || []),
                ]).map((course) => [this.normalizedCourseCode(course.code || course.label), course])
            )

            return this.storedMissingCourseItems.map((course) => {
                const overviewCourse = overviewCoursesByCode.get(this.normalizedCourseCode(course.code || course.label))
                const hours = this.courseHoursNumber(overviewCourse) || this.subjectRowHoursForCourseCode(course.code || course.label)

                return {
                    ...course,
                    hours,
                    hoursMeta: hours ? `${this.formatHours(hours)} Std.` : '',
                }
            })
        },
        storedPlannedCourseItems() {
            if (!this.storedTimetableStudentContext) {
                return this.noStudentPlannedCourseItems
            }

            const courses = this.storedTimetableStudentContext?.courses || {}

            return this.uniqueCourseItems([...this.normalizedOverviewCourseItems(courses.missing || []), ...this.normalizedOverviewCourseItems(courses.planned || [])])
        },
        storedAdditionalCourseItems() {
            if (!this.storedTimetableStudentContext) {
                return this.noStudentAdditionalCourseItems
            }

            const courses = this.storedTimetableStudentContext?.courses || {}

            return this.sortedCourseItems(this.uniqueCourseItems(this.normalizedOverviewCourseItems(courses.additional || [])))
        },
        selectedPlannedCourseItems() {
            return this.storedPlannedCourseItems.filter((course) => this.courseItemSelected(course, 'planned'))
        },
        selectedMissingCourseCardItems() {
            return this.storedMissingCourseCardItems.filter((course) => this.courseItemSelected(course, 'missing'))
        },
        selectedAdditionalCourseItems() {
            return this.storedAdditionalCourseItems.filter((course) => this.courseItemSelected(course, 'additional'))
        },
        storedMissingCourseCardSummary() {
            return this.courseItemsSummary(this.selectedMissingCourseCardItems)
        },
        storedPlannedCourseSummary() {
            return this.courseItemsSummary(this.selectedPlannedCourseItems)
        },
        storedAdditionalCourseSummary() {
            return this.courseItemsSummary(this.selectedAdditionalCourseItems)
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
        courseSelectionOverrides() {
            const courseSelections = this.storedTimetableV2Selection.courseSelections

            return courseSelections && typeof courseSelections === 'object' && !Array.isArray(courseSelections)
                ? courseSelections
                : {}
        },
        noStudentSelectedSemester() {
            const semester = Number(this.storedTimetableV2Selection.semester || 0)

            return Number.isFinite(semester) && semester > 0 ? semester : null
        },
        effectiveTimetableV2Selection() {
            return {
                ...this.defaultNoStudentTimetableV2Selection(),
                ...this.storedTimetableV2Selection,
            }
        },
        noStudentPlannedCourseItems() {
            if (!this.noStudentSelectedSemester) return []

            return this.normalizedOverviewCourseItems(this.noStudentPlannedCourses())
        },
        noStudentAdditionalCourseItems() {
            if (!this.noStudentSelectedSemester) return []

            return this.sortedCourseItems(this.uniqueCourseItems(this.normalizedOverviewCourseItems(this.noStudentAdditionalCourses())))
        },
        storedTimetableSelectionSummary() {
            const semesterLabel = this.storedTimetableStudentContext?.student?.semesterLabel
            const semesterValue = this.storedTimetableStudentContext
                ? this.semesterValueFromLabel(semesterLabel)
                : this.storedTimetableV2Selection.semester

            return [
                {
                    key: 'semester',
                    label: 'Semester',
                    value: this.knownSelectionValue(semesterLabel || semesterValue),
                    known: this.selectionValueIsKnown(semesterLabel || semesterValue),
                    options: this.storedTimetableStudentContext ? [] : this.semesterOptions(),
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
        startWithStudent() {
            this.timetableStartMode = 'student'
        },
        startWithoutStudent() {
            this.timetableStartMode = 'without-student'
            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Selection: this.defaultNoStudentTimetableV2Selection(),
                transferredStudentContext: null,
            })
            this.loadSubjectRows()
        },
        restartTimetableV2() {
            this.timetableStartMode = ''
            this.studentDialogOpen = false
            this.studentSearch = ''
            this.studentSelectionDraft = {
                studentCode: null,
            }
            this.studentCompletedCoursesError = ''
            this.studentCompletedCoursesLoading = false
            this.subjectRowsError = ''
            this.studentOverviewActiveRequestKey = ''
            this.studentOverviewLoadedRequestKey = ''

            this.saveStoredTimetableState(this.defaultStoredTimetableState())
        },
        openAutomaticTimetable() {
            this.$router.push({ path: '/admin/students-timetables/timetable/overview/automatic' })
        },
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
        async loadSubjectRows() {
            if (this.subjectRows.length || this.subjectRowsLoading) return

            this.subjectRowsLoading = true
            this.subjectRowsError = ''

            try {
                const response = await axios.get('/api/admin/students-timetables/subjects-overview-settings')

                this.subjectRows = response.data?.data?.subjects || []
            } catch {
                this.subjectRows = []
                this.subjectRowsError = 'Die Kurse konnten nicht geladen werden.'
            } finally {
                this.subjectRowsLoading = false
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
                        strict_selection: 1,
                        ...(Object.keys(selection).length ? { selection } : {}),
                    },
                })

                if (requestId !== this.studentCompletedCoursesRequestId) return
                if (this.storedTimetableStudentCode !== normalizedStudentCode) return

                const overviewSummary = response.data?.data || {}
                const courseHistory = this.overviewStudentCourseHistoryFromSummary(overviewSummary)
                const storedState = this.storedTimetableStateForSaving()
                const timetableV2Selection = this.timetableV2SelectionWithCourseDefaults(storedState?.timetableV2Selection || {}, courseHistory.completed)
                const nextSelection = this.studentOverviewSelectionPayloadForSelection(timetableV2Selection)
                const shouldReloadWithCourseDefaults = this.studentOverviewRequestKey(normalizedStudentCode, nextSelection) !== requestKey

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
                this.loadSubjectRows()

                if (shouldReloadWithCourseDefaults) {
                    this.loadStoredStudentOverview(normalizedStudentCode)
                }
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
            this.timetableStartMode = selectedStudent ? 'student' : ''
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
            this.timetableStartMode = ''
            this.studentOverviewActiveRequestKey = ''
            this.studentOverviewLoadedRequestKey = ''
            this.studentCompletedCoursesLoading = false
        },
        selectTimetableSelectionOption(key, value) {
            if (!this.selectionCardVisible) return

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
            if (this.storedTimetableStudentContext) {
                this.loadStoredStudentOverview(this.storedTimetableStudentCode)
            } else if (key === 'semester') {
                this.loadSubjectRows()
            }
        },
        selectionOptionSelected(item, option) {
            return String(this.storedTimetableV2Selection?.[item.key] || '') === String(option.value)
        },
        courseItemSelected(course, courseGroup) {
            const selectionKey = this.courseSelectionKey(course, courseGroup)
            if (!selectionKey) return true

            return this.courseSelectionOverrides[selectionKey] !== false
        },
        toggleCourseItem(course, courseGroup) {
            const selectionKey = this.courseSelectionKey(course, courseGroup)
            if (!selectionKey) return

            const timetableV2Selection = { ...this.storedTimetableV2Selection }
            const courseSelections = { ...this.courseSelectionOverrides }

            if (this.courseItemSelected(course, courseGroup)) {
                courseSelections[selectionKey] = false
            } else {
                delete courseSelections[selectionKey]
            }

            if (Object.keys(courseSelections).length) {
                timetableV2Selection.courseSelections = courseSelections
            } else {
                delete timetableV2Selection.courseSelections
            }

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Selection,
                transferredStudentContext: this.storedTimetableStudentContext,
            })
        },
        courseSelectionKey(course, courseGroup) {
            const courseKey = this.normalizedCourseCode(course?.code || '')
                || String(course?.key || course?.label || '').trim()

            return courseKey ? `${courseGroup}:${courseKey}` : ''
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
        defaultNoStudentTimetableV2Selection() {
            return {
                semester: 1,
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
                        hours,
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
                const key = String(course?.code || course?.key || course?.label || '').trim()
                if (!key || courseItemsByKey.has(key)) return

                courseItemsByKey.set(key, course)
            })

            return [...courseItemsByKey.values()]
        },
        sortedCourseItems(courses) {
            return [...(Array.isArray(courses) ? courses : [])]
                .sort((firstCourse, secondCourse) => this.courseItemSortValue(firstCourse)
                    .localeCompare(this.courseItemSortValue(secondCourse), 'de-AT', {
                        numeric: true,
                        sensitivity: 'base',
                    }))
        },
        courseItemSortValue(course) {
            return String(course?.code || course?.label || course?.name || '').trim()
        },
        courseItemsSummary(courses) {
            const courseItems = Array.isArray(courses) ? courses : []
            const hours = courseItems.reduce((sum, course) => sum + this.courseHoursNumber(course), 0)

            return {
                count: courseItems.length,
                hours,
                countLabel: `${this.formatNumber(courseItems.length)} ${courseItems.length === 1 ? 'Kurs' : 'Kurse'}`,
                hoursLabel: `${this.formatHours(hours)} Std.`,
            }
        },
        courseHoursNumber(course) {
            const numericHours = Number(course?.hours ?? course?.hours_per_week ?? 0)

            if (Number.isFinite(numericHours) && numericHours > 0) return numericHours

            const hoursMatch = String(course?.meta || course?.hours_label || '').match(/(\d+(?:[,.]\d+)?)\s*Std/iu)

            return hoursMatch ? Number(hoursMatch[1].replace(',', '.')) : 0
        },
        subjectRowHoursForCourseCode(code) {
            const normalizedCode = this.normalizedCourseCode(code)
            if (!normalizedCode) return 0

            const matchingSubject = (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .filter((subject) => subject?.is_active !== false)
                .flatMap((subject) => this.subjectCourseVariants(subject))
                .map((subject) => ({
                    code: this.normalizedCourseCode(subject?.json_code || subject?.json_subject || subject?.name),
                    hours: Number(subject?.hours_per_week || 0),
                }))
                .find((subject) => subject.code === normalizedCode)

            return Number.isFinite(matchingSubject?.hours) && matchingSubject.hours > 0 ? matchingSubject.hours : 0
        },
        noStudentPlannedCourses() {
            return this.coursesForSemester(this.noStudentSelectedSemester)
        },
        noStudentAdditionalCourses() {
            const plannedCourses = this.noStudentPlannedCourses()
            const plannedCourseCodes = this.courseCodeSet(plannedCourses)

            return this.coursesAfterSemester(this.noStudentSelectedSemester)
                .filter((course) => !this.courseCodeSetContainsCourse(plannedCourseCodes, course))
                .filter((course) => this.coursePossibleAsAdditionalCourse(course, plannedCourseCodes))
                .filter((course, index, courses) =>
                    courses.findIndex((candidate) => this.courseUniqueKey(candidate) === this.courseUniqueKey(course)) === index)
        },
        coursesForSemester(semester) {
            return this.sortedCourseItems((Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .filter((subject) => subject?.is_active !== false)
                .filter((subject) => Number(subject?.semester) === Number(semester))
                .filter((subject) => this.subjectMatchesSelectedBranch(subject))
                .filter((subject) => this.subjectMatchesSelectedChoices(subject))
                .flatMap((subject) => this.selectedCoursesFromSubject(subject))
                .filter((course, index, courses) =>
                    courses.findIndex((candidate) => this.courseUniqueKey(candidate) === this.courseUniqueKey(course)) === index))
        },
        coursesAfterSemester(semester) {
            return (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .map((subject) => Number(subject?.semester))
                .filter((subjectSemester) => Number.isFinite(subjectSemester) && subjectSemester > Number(semester))
                .filter((subjectSemester, index, subjectSemesters) => subjectSemesters.indexOf(subjectSemester) === index)
                .sort((firstSemester, secondSemester) => firstSemester - secondSemester)
                .flatMap((subjectSemester) => this.coursesForSemester(subjectSemester))
        },
        subjectMatchesSelectedBranch(subject) {
            if (this.isArtsSubject(subject)) return true

            const branch = String(subject?.branch || '').trim()

            return !branch || branch === 'common' || branch === this.effectiveTimetableV2Selection.branch
        },
        subjectMatchesSelectedChoices(subject) {
            if (this.isReligionSubject(subject)) return Boolean(this.effectiveTimetableV2Selection.religion)
            if (this.isArtsSubject(subject)) return this.subjectBaseKey(subject) === this.effectiveTimetableV2Selection.artsSubject
            if (this.isLanguageSubject(subject)) return this.languageSubjectMatchesSelection(subject)

            return true
        },
        languageSubjectMatchesSelection(subject) {
            const selectedLanguage = String(this.effectiveTimetableV2Selection.language || '').trim()
            if (!selectedLanguage) return false

            const languageCode = this.languageSubjectCode(subject)

            return !languageCode || languageCode === selectedLanguage
        },
        languageSubjectCode(subject) {
            const rawBaseKey = this.subjectBaseKey(subject)
            const baseKey = this.normalizedCourseCode(rawBaseKey)
            if (['L', 'F', 'S'].includes(baseKey)) return baseKey
            if (rawBaseKey !== 'L/F/S') return ''

            const jsonCodeParts = this.courseCodeAliasParts(this.courseCodeWithoutModule(subject?.json_code))
                .map((value) => this.normalizedCourseCode(value))

            return jsonCodeParts.length === 1 && ['L', 'F', 'S'].includes(jsonCodeParts[0])
                ? jsonCodeParts[0]
                : ''
        },
        selectedCoursesFromSubject(subject) {
            return this.subjectCourseVariants(subject)
                .map((courseSubject) => this.selectedCourseFromSubject(courseSubject))
        },
        subjectCourseVariants(subject) {
            if (this.isReligionSubject(subject) || this.isLanguageSubject(subject)) return [subject]

            const courseCodes = this.courseCodeAliasParts(subject?.json_code)
            if (courseCodes.length <= 1) return [subject]

            const splitHours = Number(subject?.hours_per_week || 0) / courseCodes.length

            return courseCodes.map((courseCode) => ({
                ...subject,
                json_code: courseCode,
                hours_per_week: Number.isFinite(splitHours) ? splitHours : subject?.hours_per_week,
            }))
        },
        selectedCourseFromSubject(subject) {
            const code = this.selectedCourseCode(subject)

            return {
                key: [
                    subject?.id || subject?.local_id || '',
                    subject?.semester || '',
                    subject?.branch || 'common',
                    subject?.json_code || '',
                    subject?.json_subject || '',
                    subject?.name || '',
                    code,
                ].join('|'),
                code,
                name: this.selectedCourseName(subject),
                hours: Number(subject?.hours_per_week || 0),
            }
        },
        selectedCourseCode(subject) {
            if (this.isReligionSubject(subject)) return `${this.effectiveTimetableV2Selection.religion}${this.subjectModuleNumber(subject)}`
            if (this.isLanguageSubject(subject)) return `${this.effectiveTimetableV2Selection.language}${this.subjectModuleNumber(subject)}`

            return this.alternativeDisplay(subject?.json_code || subject?.json_subject || subject?.name)
        },
        selectedCourseName(subject) {
            const moduleNumber = this.subjectModuleNumber(subject)

            if (this.isReligionSubject(subject)) {
                return `${this.selectedOptionDescription(this.religionOptions(), this.effectiveTimetableV2Selection.religion)} ${moduleNumber}`.trim()
            }

            if (this.isLanguageSubject(subject)) {
                return `${this.selectedOptionDescription(this.languageOptions(), this.effectiveTimetableV2Selection.language)} ${moduleNumber}`.trim()
            }

            if (this.isArtsSubject(subject)) {
                return `${this.selectedOptionDescription(this.artsSubjectOptions(), this.effectiveTimetableV2Selection.artsSubject)} ${moduleNumber}`.trim()
            }

            return subject?.name || subject?.json_subject || subject?.json_code || '-'
        },
        selectedOptionDescription(options, value) {
            return String(this.selectedOptionTitle(options, value)).split(' - ').pop()
        },
        selectedOptionTitle(options, value) {
            return (Array.isArray(options) ? options : []).find((option) => String(option.value) === String(value))?.title || value || ''
        },
        subjectBaseKey(subject) {
            const jsonSubject = String(subject?.json_subject || '').trim()

            return jsonSubject || String(subject?.json_code || '').replace(/\d+$/u, '')
        },
        subjectModuleNumber(subject) {
            return String(subject?.json_code || '').match(/(\d+)$/u)?.[1] || ''
        },
        isReligionSubject(subject) {
            return this.subjectBaseKey(subject) === 'R/ET'
        },
        isLanguageSubject(subject) {
            const rawBaseKey = this.subjectBaseKey(subject)
            const baseKey = this.normalizedCourseCode(rawBaseKey)

            return rawBaseKey === 'L/F/S' || ['L', 'F', 'S'].includes(baseKey)
        },
        isArtsSubject(subject) {
            return ['ME', 'BE'].includes(this.subjectBaseKey(subject))
        },
        coursePossibleAsAdditionalCourse(course, plannedCourseCodes) {
            return this.courseModuleParts(course)
                .some((parts) => this.courseModulePrerequisiteMet(parts, plannedCourseCodes))
        },
        courseModuleParts(course) {
            return this.courseCodeAliasParts(course?.code)
                .map((courseCode) => this.courseCodeModuleParts(courseCode))
                .filter((parts) => parts.module)
                .filter((parts, index, allParts) =>
                    allParts.findIndex((candidate) => candidate.base === parts.base && candidate.module === parts.module) === index)
        },
        courseModulePrerequisiteMet(parts, plannedCourseCodes) {
            const moduleNumber = Number(parts.module)
            if (!Number.isInteger(moduleNumber)) return false
            if (moduleNumber === 1) return true

            const baseAliases = this.courseBaseAliases(parts.base)
            if (moduleNumber === 2) {
                return baseAliases.some((baseAlias) => plannedCourseCodes.has(`${baseAlias}1`))
            }

            return false
        },
        courseCodeSet(courses) {
            return new Set((Array.isArray(courses) ? courses : [])
                .flatMap((course) => this.courseCodeAliases(course))
                .filter(Boolean))
        },
        courseCodeSetContainsCourse(courseCodes, course) {
            return this.courseCodeAliases(course)
                .some((courseCode) => courseCodes.has(courseCode))
        },
        courseCodeAliases(course) {
            return this.courseCodeAliasParts(course?.code)
                .map((courseCode) => this.normalizedCourseCode(courseCode))
                .flatMap((courseCode) => {
                    const { base, module } = this.courseCodeModuleParts(courseCode)

                    return this.courseBaseAliases(base).map((baseAlias) => `${baseAlias}${module}`)
                })
                .filter(Boolean)
        },
        courseBaseAliases(base) {
            const normalizedBase = this.normalizedCourseCode(base)
            const mappedAliases = {
                ET: ['ETH', 'R', 'RK'],
                ETH: ['ET', 'R', 'RK'],
                GPB: ['GS'],
                GS: ['GPB'],
                GW: ['GWB'],
                GWB: ['GW'],
                LPT: ['LET'],
                LET: ['LPT'],
                ME: ['MU'],
                MU: ['ME'],
                R: ['RK', 'ET', 'ETH'],
                RK: ['R', 'ET', 'ETH'],
                S: ['SPA'],
                SPA: ['S'],
            }

            return this.uniqueValues([normalizedBase, ...(mappedAliases[normalizedBase] || [])].filter(Boolean))
        },
        courseUniqueKey(course) {
            return this.normalizedCourseCode(course?.code || '') || String(course?.key || '')
        },
        alternativeDisplay(value) {
            const normalizedValue = String(value || '').trim()
            if (!normalizedValue.includes('/')) return normalizedValue || '-'

            return normalizedValue
                .split('/')
                .map((part) => part.trim())
                .filter(Boolean)
                .join(' / ')
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
                || selection.semester

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
        courseCodeAliasParts(value) {
            return String(value || '')
                .split('/')
                .map((part) => part.trim())
                .filter(Boolean)
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
        semesterOptions() {
            return Array.from({ length: 8 }, (_, index) => {
                const semester = index + 1

                return {
                    title: `Semester ${semester}`,
                    value: semester,
                }
            })
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
    display: flex;
    flex: 1 1 auto;
    width: 100%;
    height: 100%;
    flex-direction: column;
    border: 1px solid rgba(37, 99, 235, 0.12);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
}

.students-timetable-v2-card :deep(.v-card-text) {
    flex: 1 1 auto;
}

.students-timetable-v2-card-column {
    display: flex !important;
    align-self: stretch;
    min-width: 0;
}

.students-timetable-v2-row-break {
    flex-basis: 100%;
    width: 0;
    height: 0;
    padding: 0;
}

.students-timetable-v2-without-student-background-column {
    display: none;
    min-width: 0;
    align-self: stretch;
    pointer-events: none;
    user-select: none;
}

.students-timetable-v2-without-student-watermark {
    display: grid;
    width: 100%;
    align-content: center;
    justify-items: center;
    gap: 10px;
    padding: 24px;
    color: rgba(15, 23, 42, 0.12);
    font-size: 2.8rem;
    font-weight: 900;
    line-height: 1.05;
    text-align: center;
}

.students-timetable-v2-without-student-watermark__icon {
    font-size: 6.5rem;
}

.students-timetable-v2-course-card-title {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-course-card-title__chips {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.students-timetable-v2-card--empty {
    min-height: 120px;
}

.students-timetable-v2-start-card__actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.students-timetable-v2-start-card__button {
    min-height: 96px;
    font-weight: 800;
}

.students-timetable-v2-restart-card__content {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: space-between;
}

.students-timetable-v2-restart-card__automatic-button {
    margin-left: auto;
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

.students-timetable-v2-completed-courses__item--additional {
    border-color: rgba(2, 136, 209, 0.18);
    background: rgba(240, 249, 255, 0.78);
}

.students-timetable-v2-completed-courses__item--toggle {
    cursor: pointer;
}

.students-timetable-v2-completed-courses__item--deselected {
    opacity: 0.56;
}

.students-timetable-v2-completed-courses__item--deselected span:first-child {
    text-decoration: line-through;
}

.students-timetable-v2-completed-courses__item-meta {
    border-radius: 999px;
    padding: 1px 6px;
    background: rgba(255, 255, 255, 0.72);
    font-size: 0.7rem;
    font-weight: 800;
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

@media (max-width: 640px) {
    .students-timetable-v2-start-card__actions {
        grid-template-columns: 1fr;
    }
}

@media (min-width: 960px) {
    .students-timetable-v2-without-student-background-column {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 170px;
    }
}
</style>
