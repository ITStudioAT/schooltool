<template>
    <v-col cols="12" xl="10">
        <v-card rounded="lg" border>
            <v-card-title class="d-flex flex-wrap align-center ga-2">
                <v-icon icon="mdi-calendar-clock" />
                Stundenplan
                <v-chip v-if="schoolyearName" size="small" variant="tonal" color="light-blue">
                    {{ schoolyearName }}
                </v-chip>
                <v-spacer />
                <v-tooltip v-if="hasHiddenSaturdayCourses" text="Es gibt Einträge am Samstag. Sa aktivieren, um sie zu sehen.">
                    <template #activator="{ props }">
                        <v-btn
                            v-bind="props"
                            color="error"
                            variant="flat"
                            size="large"
                            class="hidden-saturday-warning"
                            aria-label="Samstag hat ausgeblendete Einträge"
                            @click="handleShowSaturdayClick">
                            !
                        </v-btn>
                    </template>
                </v-tooltip>
                <v-btn
                    color="grey-darken-1"
                    variant="outlined"
                    size="small"
                    prepend-icon="mdi-restore"
                    :disabled="loading || timetableUpdatePending"
                    @click="resetSavedTimetable">
                    Zurücksetzen
                </v-btn>
                <v-switch
                    :model-value="showSaturday"
                    color="primary"
                    density="compact"
                    hide-details
                    inset
                    label="Sa"
                    class="timetable-saturday-switch"
                    @update:model-value="handleShowSaturdayUpdate" />
            </v-card-title>
            <v-card-text class="timetable-card-body">
                <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-3" />

                <v-alert v-if="!loading && !configuredSchoolHours.length" type="info" variant="tonal" class="mb-3">
                    Es sind noch keine Schulstunden hinterlegt. Die Übersicht zeigt vorläufig 10 Stunden.
                </v-alert>

                <div class="overview-student-selection">
                    <div
                        class="transferred-student-context"
                        :class="{ 'transferred-student-context--collapsed': !transferredStudentContextExpanded }">
                        <div
                            class="transferred-student-context__header"
                            :role="transferredStudentContext ? 'button' : undefined"
                            :tabindex="transferredStudentContext ? 0 : undefined"
                            @click="toggleTransferredStudentContext"
                            @keydown.enter.prevent="toggleTransferredStudentContext"
                            @keydown.space.prevent="toggleTransferredStudentContext">
                            <div class="transferred-student-context__title">
                                <v-icon icon="mdi-account-school-outline" size="18" color="primary" />
                                <span>{{ transferredStudentLabel }}</span>
                                <span class="overview-student-inline-actions">
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
                                        v-if="transferredStudentContext"
                                        icon="mdi-close-circle-outline"
                                        variant="text"
                                        color="error"
                                        density="comfortable"
                                        size="small"
                                        title="Student löschen"
                                        aria-label="Student löschen"
                                        @click.stop="clearTransferredStudentSelection" />
                                </span>
                            </div>
                            <div class="transferred-student-context__header-actions">
                                <v-btn
                                    v-if="transferredStudentContext"
                                    :icon="transferredStudentContextExpanded ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                                    variant="text"
                                    density="comfortable"
                                    color="primary"
                                    :aria-label="transferredStudentContextExpanded ? 'Studentenkurse einklappen' : 'Studentenkurse ausklappen'"
                                    @click.stop="toggleTransferredStudentContext" />
                            </div>
                        </div>
                        <v-expand-transition>
                            <div
                                v-if="transferredStudentContext && transferredStudentContextExpanded"
                                class="transferred-student-context__sections">
                                <section
                                    v-for="section in visibleTransferredStudentCourseSections"
                                    :key="section.key"
                                    class="transferred-student-course-section">
                                    <div class="transferred-student-course-section__title">
                                        <span>{{ section.title }}</span>
                                        <v-chip size="x-small" :color="section.color" variant="tonal">
                                            {{ section.items.length }}
                                        </v-chip>
                                    </div>
                                    <div
                                        v-if="section.items.length"
                                        class="transferred-student-course-section__chips">
                                        <v-chip
                                            v-for="course in section.items"
                                            :key="course.key"
                                            size="x-small"
                                            :color="section.color"
                                            variant="tonal"
                                            class="transferred-student-course-chip">
                                            <span>{{ course.label }}</span>
                                            <span v-if="course.meta" class="transferred-student-course-chip__meta">
                                                {{ course.meta }}
                                            </span>
                                        </v-chip>
                                    </div>
                                    <div v-else class="transferred-student-course-section__empty">Keine</div>
                                </section>
                            </div>
                        </v-expand-transition>
                    </div>
                </div>

                <div class="overview-selection">
                    <div class="overview-selected-cards">
                        <div
                            v-for="item in selectedSummary"
                            :key="item.key"
                            class="overview-selected-card">
                            <div class="overview-selected-card__label">{{ item.label }}</div>
                            <div class="overview-selected-card__value">{{ item.value }}</div>
                        </div>
                    </div>
                    <v-btn
                        icon="mdi-pencil"
                        variant="tonal"
                        color="primary"
                        title="Auswahl bearbeiten"
                        @click="openSelectionDialog" />
                </div>

                <div class="course-choice-panel">
                    <div class="course-choice-panel__header">
                        <div class="course-choice-panel__title">
                            <v-icon icon="mdi-format-list-checks" size="18" color="primary" />
                            <span>Kursauswahl</span>
                            <v-tooltip text="Kurs wählen">
                                <template #activator="{ props }">
                                    <v-btn
                                        v-bind="props"
                                        icon="mdi-plus"
                                        color="primary"
                                        variant="flat"
                                        size="small"
                                        aria-label="Kurs wählen"
                                        @click="openCourseMenuDialog" />
                                </template>
                            </v-tooltip>
                        </div>
                        <div class="course-choice-panel__actions">
                            <v-switch
                                :model-value="restrictCourseChoiceBySelection"
                                color="primary"
                                density="compact"
                                hide-details
                                inset
                                label="Nach Auswahl einschränken"
                                class="course-choice-restriction-switch"
                                @update:model-value="handleCourseChoiceRestrictionUpdate" />
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ selectedCourseCount }} ausgewählt
                            </v-chip>
                            <v-btn
                                v-if="selectedCourseCount > 0"
                                size="x-small"
                                variant="tonal"
                                color="error"
                                prepend-icon="mdi-close-circle-outline"
                                @click="deselectAllCourses">
                                Alle abwählen
                            </v-btn>
                        </div>
                    </div>

                    <div v-if="selectedCourseFilterChipsAll.length" class="selected-course-filter-chips">
                        <v-chip
                            v-for="filterChip in selectedCourseFilterChipsAll"
                            :key="filterChip.key"
                            size="small"
                            :color="filterChip.hasOverlap ? 'error' : 'success'"
                            variant="tonal"
                            closable
                            class="selected-course-filter-chip"
                            @click:close="handleCourseMenuEntryFilterClick(filterChip.entry)">
                            {{ filterChip.label }}
                        </v-chip>
                    </div>
                </div>

                <v-alert
                    v-if="!visibleTimetableSemesters.length"
                    type="info"
                    variant="tonal"
                    class="mb-0">
                    Wähle oben Kurse aus, um den Stundenplan anzuzeigen.
                </v-alert>

                <div v-else class="semester-grid">
                    <section v-for="semester in visibleTimetableSemesters" :key="semester.value" class="semester-timetable">
                        <div class="semester-timetable__header">
                            <div class="d-flex flex-wrap align-center ga-2">
                                <v-icon icon="mdi-calendar-range" size="18" color="primary" />
                                <span class="text-subtitle-2 font-weight-bold">{{ semester.label }}</span>
                                <span class="semester-timetable__dates">{{ semester.dateRangeLabel }}</span>
                            </div>
                            <v-chip size="x-small" color="primary" variant="tonal">{{ weekdayRangeLabel }}</v-chip>
                        </div>

                        <div v-if="recurrenceWeekOptions(semester.value).length > 1" class="recurrence-week-selector">
                            <v-btn-toggle
                                v-if="!areRecurrenceWeeksExpanded(semester.value)"
                                :model-value="selectedTimetableOptionValue(semester.value)"
                                mandatory
                                density="compact"
                                color="primary"
                                variant="outlined"
                                divided
                                @update:model-value="handleSelectedTimetableOptionValueUpdate(semester.value, $event)">
                                <v-btn
                                    v-for="weekOption in timetableSelectorOptions(semester.value)"
                                    :key="weekOption.value"
                                    :value="weekOption.value"
                                    size="small"
                                    class="recurrence-week-selector__btn">
                                    {{ weekOption.label }}
                                </v-btn>
                            </v-btn-toggle>
                            <v-btn
                                size="small"
                                color="grey-darken-1"
                                variant="outlined"
                                prepend-icon="mdi-table-multiple"
                                class="recurrence-week-selector__action-btn"
                                @click="handleToggleRecurrenceWeeksClick(semester.value)">
                                {{ areRecurrenceWeeksExpanded(semester.value) ? 'Eine Woche anzeigen' : 'Wochen anzeigen' }}
                            </v-btn>
                        </div>

                        <div
                            v-for="timetableWeek in visibleTimetableWeeks(semester.value)"
                            :key="timetableWeek.key"
                            class="timetable-week">
                            <div v-if="timetableWeek.showLabel" class="timetable-week__title">
                                {{ timetableWeek.label }}
                            </div>

                            <div
                                class="timetable-generated-grid"
                                :style="{ '--overview-timetable-weekdays': displayedWeekdays.length }">
                                <div class="timetable-generated-cell timetable-generated-cell--header">Std.</div>
                                <div
                                    v-for="weekday in displayedWeekdays"
                                    :key="`${semester.value}-${timetableWeek.key}-${weekday.key}`"
                                    class="timetable-generated-cell timetable-generated-cell--header">
                                    {{ weekday.label }}
                                </div>

                                <template
                                    v-for="hour in timetableHoursForSemester(semester.value, timetableWeek)"
                                    :key="`${semester.value}-${timetableWeek.key}-${hour.hour}`">
                                    <div class="timetable-generated-cell timetable-generated-cell--time">
                                        <div class="timetable-hour-num">{{ hour.hour }}.</div>
                                        <div v-if="hour.from || hour.until" class="timetable-hour-time">
                                            {{ hour.from }}<br>{{ hour.until }}
                                        </div>
                                    </div>
                                    <div
                                        v-for="weekday in displayedWeekdays"
                                        :key="`${semester.value}-${timetableWeek.key}-${weekday.key}-${hour.hour}`"
                                        class="timetable-generated-cell"
                                        :class="{
                                            'timetable-generated-cell--filled': displayCourseGroupsForCell(semester.value, weekday.value, hour.hour, timetableWeek).length,
                                            'timetable-generated-cell--conflict': cellHasOverlap(semester.value, weekday.value, hour.hour, timetableWeek),
                                            'timetable-generated-cell--related-overlap': cellHasRelatedOverlap(semester.value, weekday.value, hour.hour, timetableWeek),
                                        }">
                                        <div
                                            v-for="courseGroup in displayCourseGroupsForCell(semester.value, weekday.value, hour.hour, timetableWeek)"
                                            :key="courseGroup.key"
                                            class="timetable-generated-cell__content"
                                            role="button"
                                            tabindex="0"
                                            @click="openCourseGroupDialog(courseGroup)"
                                            @keydown.enter="openCourseGroupDialog(courseGroup)">
                                            <div class="timetable-generated-cell__code">
                                                {{ courseGroup.display_label || courseGroup.title }}
                                            </div>
                                            <div v-if="courseGroupRelatedOverlapMarker(courseGroup)" class="timetable-generated-cell__details timetable-generated-cell__details--warning">
                                                {{ courseGroupRelatedOverlapMarker(courseGroup) }}
                                            </div>
                                            <div v-if="courseGroup.recurrence_label || courseGroup.is_block" class="timetable-generated-cell__details">
                                                <span v-if="courseGroup.recurrence_label">{{ courseGroup.recurrence_label }}</span>
                                                <span v-if="courseGroup.recurrence_label && courseGroup.is_block"> · </span>
                                                <span v-if="courseGroup.is_block">{{ courseGroupBlockLabel(courseGroup) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <div
                                    v-if="!timetableHoursForSemester(semester.value, timetableWeek).length"
                                    class="timetable-generated-cell timetable-generated-cell--empty">
                                    Keine Einträge in den sichtbaren Wochentagen.
                                </div>
                            </div>

                            <div v-if="shouldShowExtraDatesNotice(semester.value, timetableWeek)" class="extra-dates-notice">
                                <span class="extra-dates-notice__text">Zusatzwochen vorhanden</span>
                                <v-checkbox
                                    :model-value="showExtraDatesInSelectedWeek(semester.value)"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                    label="Im aktuellen Stundenplan anzeigen"
                                    @update:model-value="handleShowExtraDatesUpdate(semester.value, $event)" />
                            </div>
                        </div>
                    </section>
                </div>

                <template v-if="timetableUpdatePending">
                    <div class="timetable-update-blocker" aria-hidden="true"></div>
                    <div class="timetable-update-indicator" role="status" aria-live="polite">
                        <LoadingAnimation class="timetable-update-indicator__dots" />
                        <span>Stundenplan wird aktualisiert...</span>
                    </div>
                </template>
            </v-card-text>
        </v-card>

        <v-dialog v-model="selectionDialogOpen" persistent max-width="640">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-pencil-outline" />
                    Auswahl bearbeiten
                </v-card-title>
                <v-card-text>
                    <div class="overview-selection-dialog-grid">
                        <v-select
                            v-model="selectionDraft.semester"
                            :items="semesterOptions"
                            item-title="title"
                            item-value="value"
                            label="Semester"
                            variant="outlined"
                            density="compact"
                            hide-details="auto" />
                        <v-select
                            v-model="selectionDraft.religion"
                            :items="religionOptions"
                            item-title="title"
                            item-value="value"
                            label="Ethik / Religion"
                            variant="outlined"
                            density="compact"
                            hide-details="auto" />
                        <v-select
                            v-model="selectionDraft.language"
                            :items="languageOptions"
                            item-title="title"
                            item-value="value"
                            label="Sprache"
                            variant="outlined"
                            density="compact"
                            hide-details="auto" />
                        <v-select
                            v-model="selectionDraft.branch"
                            :items="branchOptions"
                            item-title="title"
                            item-value="value"
                            label="Zweig"
                            variant="outlined"
                            density="compact"
                            hide-details="auto" />
                        <v-select
                            v-model="selectionDraft.artsSubject"
                            :items="artsSubjectOptions"
                            item-title="title"
                            item-value="value"
                            label="ME / BE"
                            variant="outlined"
                            density="compact"
                            hide-details="auto" />
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeSelectionDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" @click="updateSelection">Aktualisieren</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="studentDialogOpen" persistent max-width="560">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-account-school-outline" />
                    Student bearbeiten
                </v-card-title>
                <v-card-text>
                    <div class="overview-student-dialog__meta">
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
                    <div class="overview-student-search-results">
                        <v-btn
                            size="small"
                            variant="tonal"
                            :color="studentSelectionDraft.studentCode === null ? 'primary' : 'secondary'"
                            class="overview-student-search-results__item"
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
                                class="overview-student-search-results__item"
                                block
                                @click="selectStudentDraft(student.student_code)">
                                {{ studentOptionTitle(student) }}
                            </v-btn>
                            <div v-if="!filteredStudentResults.length" class="overview-student-search-results__empty">
                                Keine Schüler gefunden
                            </div>
                        </template>
                        <div v-else class="overview-student-search-results__empty">
                            Mindestens 2 Zeichen eingeben
                        </div>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeStudentDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" @click="updateStudentSelection">Aktualisieren</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="courseMenuDialog" persistent max-width="760">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-plus-circle" color="primary" />
                    Kurs wählen
                    <v-spacer />
                    <v-btn icon="mdi-close" variant="text" size="small" @click="courseMenuDialog = false" />
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <div class="course-menu-dialog-chips">
                        <v-chip
                            v-for="courseMenu in allCourseChoiceMenus"
                            :key="courseMenu.key"
                            size="small"
                            :color="courseMenuHasActiveSelection(courseMenu) ? 'success' : 'primary'"
                            :variant="selectedCourseMenuKey === courseMenu.key ? 'flat' : 'tonal'"
                            class="course-menu-dialog-chip"
                            @click="selectCourseMenu(courseMenu)">
                            {{ courseMenu.label }}
                        </v-chip>
                    </div>

                    <div v-if="selectedCourseMenu" class="course-choice-panel__semesters course-menu-dialog-items">
                        <section
                            :key="selectedCourseMenu.key"
                            class="course-choice-semester">
                            <div class="course-choice-semester__label">
                                <span>{{ selectedCourseMenu.semesterLabel }}</span>
                                <span class="course-choice-semester__dates">{{ selectedCourseMenu.semesterDateRangeLabel }}</span>
                                <v-chip size="x-small" color="primary" variant="tonal">
                                    {{ selectedCourseMenu.label }}
                                </v-chip>
                            </div>
                            <div class="course-item-chips">
                                <v-chip
                                    v-for="entry in selectedCourseMenu.entries"
                                    :key="entry.key"
                                    size="small"
                                    :color="courseMenuEntryHasOverlap(entry, selectedCourseMenu.semesterValue) ? 'error' : isCourseMenuEntryFilterActive(entry) ? 'success' : 'primary'"
                                    :variant="isCourseMenuEntryFilterActive(entry) ? 'flat' : 'tonal'"
                                    class="course-item-chip"
                                    @click="handleCourseMenuEntryFilterClick(entry)">
                                    <v-icon
                                        :icon="isCourseMenuEntryFilterActive(entry) ? 'mdi-check' : 'mdi-calendar-blank'"
                                        size="16"
                                        start />
                                    <span>{{ entry.label }}</span>
                                    <span v-if="entry.scheduleLabel" class="course-item-chip__schedule">
                                        {{ entry.scheduleLabel }}
                                    </span>
                                </v-chip>
                            </div>
                        </section>
                    </div>
                </v-card-text>
            </v-card>
        </v-dialog>

        <v-dialog v-model="courseGroupDialog" persistent max-width="520">
            <v-card>
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-calendar-multiselect" color="primary" />
                    {{ selectedCourseGroupLabel }}
                    <v-spacer />
                    <v-btn icon="mdi-close" variant="text" size="small" @click="closeCourseGroupDialog" />
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <div class="d-flex flex-wrap ga-2 mb-3">
                        <v-chip v-if="selectedCourseGroup?.recurrence_label" size="small" color="primary" variant="tonal">
                            {{ selectedCourseGroup.recurrence_label }}
                        </v-chip>
                        <v-chip v-if="selectedCourseGroup?.is_block" size="small" color="warning" variant="tonal">
                            {{ courseGroupBlockLabel(selectedCourseGroup) }}
                        </v-chip>
                        <v-chip v-if="selectedCourseGroup?.dates_count" size="small" variant="outlined">
                            {{ selectedCourseGroup.dates_count }} Termine
                        </v-chip>
                    </div>

                    <div class="course-date-list">
                        <v-chip
                            v-for="dateItem in selectedCourseGroupDateItems"
                            :key="dateItem.key"
                            size="small"
                            variant="tonal"
                            color="secondary"
                            class="course-date-chip">
                            <span>{{ dateItem.dateLabel }}</span>
                            <span v-if="dateItem.timeRangeLabel" class="course-date-chip__separator">·</span>
                            <span v-if="dateItem.timeRangeLabel" class="course-date-chip__time">
                                {{ dateItem.timeRangeLabel }}
                            </span>
                        </v-chip>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" @click="closeCourseGroupDialog">Schließen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import { parseLocalDate } from '@/helpers/date'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import LoadingAnimation from '@/pages/components/LoadingAnimation.vue'

const FALLBACK_HOUR_COUNT = 10
const ALL_DATES_OPTION_VALUE = 'all_dates'
const TIMETABLE_STORAGE_KEY_PREFIX = 'students-timetables:overview:last-timetable'

export default {
    name: 'StudentsTimetablesOverview',
    components: {
        LoadingAnimation,
    },

    data() {
        return {
            loading: false,
            schoolHours: [],
            courseGroups: [],
            courseMenuDialog: false,
            selectedCourseMenuKey: '',
            courseGroupDialog: false,
            selectedCourseGroup: null,
            timetableUpdatePending: false,
            studentDialogOpen: false,
            studentOptionsLoading: false,
            studentSearch: '',
            robotStudents: [],
            subjectRows: [],
            studentSelectionDraft: {
                studentCode: null,
            },
            studentCompletedCoursesRequestId: 0,
            activeCourseGroupFilterKeys: [],
            selectedRecurrenceWeeks: {
                1: ALL_DATES_OPTION_VALUE,
                2: ALL_DATES_OPTION_VALUE,
            },
            expandedRecurrenceWeeks: {
                1: false,
                2: false,
            },
            showExtraDatesInSelectedWeeks: {
                1: false,
                2: false,
            },
            showSaturday: false,
            restrictCourseChoiceBySelection: false,
            transferredStudentContext: null,
            transferredStudentContextExpanded: false,
            selectionDialogOpen: false,
            selection: {
                semester: 1,
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            selectionDraft: {
                semester: 1,
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            weekdays: [
                { key: 'mo', label: 'Mo', value: 1 },
                { key: 'tu', label: 'Di', value: 2 },
                { key: 'we', label: 'Mi', value: 3 },
                { key: 'th', label: 'Do', value: 4 },
                { key: 'fr', label: 'Fr', value: 5 },
                { key: 'sa', label: 'Sa', value: 6 },
            ],
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        selectedSchoolyear() {
            return this.config?.selected_schoolyear || {}
        },
        schoolyearName() {
            return this.selectedSchoolyear?.name || ''
        },
        semesters() {
            return [
                {
                    value: 1,
                    label: 'Semester',
                    dateRangeLabel: this.semesterDateRange(1),
                },
                {
                    value: 2,
                    label: 'Semester',
                    dateRangeLabel: this.semesterDateRange(2),
                },
            ]
        },
        configuredSchoolHours() {
            return Array.isArray(this.schoolHours) ? this.schoolHours : []
        },
        configuredCourseGroups() {
            return Array.isArray(this.courseGroups) ? this.courseGroups : []
        },
        displayedWeekdays() {
            if (this.showSaturday) {
                return this.weekdays
            }

            return this.weekdays.filter((weekday) => weekday.value !== 6)
        },
        weekdayRangeLabel() {
            return this.showSaturday ? 'Mo-Sa' : 'Mo-Fr'
        },
        hasHiddenSaturdayCourses() {
            return !this.showSaturday
                && this.configuredCourseGroups.some((courseGroup) => (
                    Number(courseGroup?.weekday) === 6
                    && this.activeCourseGroupFilterKeys.includes(courseGroup?.key)
                ))
        },
        timetableHours() {
            const courseHours = this.configuredCourseGroups
                .map((courseGroup) => Number(courseGroup.hour))
                .filter((hour) => Number.isFinite(hour))

            if (this.configuredSchoolHours.length) {
                const schoolHours = this.configuredSchoolHours
                    .map((schoolHour) => ({
                        hour: Number(schoolHour.hour),
                        from: this.formatTimeValue(schoolHour.from),
                        until: this.formatTimeValue(schoolHour.until),
                    }))
                    .filter((schoolHour) => Number.isFinite(schoolHour.hour))
                    .sort((a, b) => a.hour - b.hour)

                const configuredHours = new Set(schoolHours.map((schoolHour) => schoolHour.hour))
                courseHours
                    .filter((hour) => !configuredHours.has(hour))
                    .forEach((hour) => schoolHours.push({ hour, from: '', until: '' }))

                return schoolHours.sort((a, b) => a.hour - b.hour)
            }

            const fallbackHourCount = Math.max(FALLBACK_HOUR_COUNT, ...courseHours, 0)

            return Array.from({ length: fallbackHourCount }, (item, index) => ({
                hour: index + 1,
                from: '',
                until: '',
            }))
        },
        courseGroupsByCell() {
            return this.configuredCourseGroups.reduce((groups, courseGroup) => {
                const key = this.courseCellKey(courseGroup.semester, courseGroup.weekday, courseGroup.hour)
                if (!groups[key]) {
                    groups[key] = []
                }

                groups[key].push(courseGroup)

                return groups
            }, {})
        },
        activeCourseGroupFilterKeySet() {
            return new Set(this.activeCourseGroupFilterKeys.filter(Boolean))
        },
        semesterCourseMenusBySemester() {
            return {
                1: this.buildSemesterCourseMenus(1),
                2: this.buildSemesterCourseMenus(2),
            }
        },
        courseChoiceCourseGroups() {
            if (!this.restrictCourseChoiceBySelection) return this.configuredCourseGroups

            return this.configuredCourseGroups
                .filter(courseGroup => this.courseGroupMatchesCourseChoiceRestriction(courseGroup))
        },
        selectionCourseChoiceCodes() {
            const semester = Number(this.selection?.semester || 0)
            if (!Number.isFinite(semester) || semester <= 0 || !Array.isArray(this.subjectRows) || !this.subjectRows.length) {
                return new Set()
            }

            return new Set(this.coursesForSemester(semester)
                .flatMap(course => this.courseAliasesFromValues([
                    course?.code,
                    course?.label,
                    course?.name,
                ])))
        },
        restrictedStudentCourseCodes() {
            const courses = this.transferredStudentContext?.courses || {}
            const selectionCourseChoiceCodes = this.selectionCourseChoiceCodes instanceof Set
                ? this.selectionCourseChoiceCodes
                : new Set()

            return new Set([
                ...selectionCourseChoiceCodes,
                ...(courses.missing || []),
                ...(courses.planned || []),
                ...(courses.additional || []),
            ]
                .flatMap(course => (
                    typeof course === 'string'
                        ? [course]
                        : this.courseAliasesFromValues([
                            course?.code,
                            course?.label,
                            course?.name,
                        ])
                ))
                .filter(Boolean))
        },
        selectedCourseMenuEntriesBySemester() {
            return {
                1: this.semesterCourseMenus(1)
                    .flatMap((courseMenu) => courseMenu.entries)
                    .filter((entry) => this.isCourseMenuEntryFilterActive(entry)),
                2: this.semesterCourseMenus(2)
                    .flatMap((courseMenu) => courseMenu.entries)
                    .filter((entry) => this.isCourseMenuEntryFilterActive(entry)),
            }
        },
        selectedCourseFilterChipsAll() {
            return this.semesters.flatMap((semester) => this.selectedCourseFilterChips(semester.value))
        },
        selectedCourseCount() {
            return this.selectedCourseMenuEntriesBySemester[1].length
                + this.selectedCourseMenuEntriesBySemester[2].length
        },
        semesterOptions() {
            return Array.from({ length: 8 }, (value, index) => ({
                title: `Semester ${index + 1}`,
                value: index + 1,
            }))
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
        languageOptions() {
            return [
                { title: 'L - Latein', value: 'L' },
                { title: 'F - Französisch', value: 'F' },
                { title: 'S - Spanisch', value: 'S' },
            ]
        },
        selectedSummary() {
            return [
                { key: 'semester', label: 'Semester', value: this.selectedOptionTitle(this.semesterOptions, this.selection.semester) },
                { key: 'religion', label: 'Ethik / Religion', value: this.selectedOptionTitle(this.religionOptions, this.selection.religion) },
                { key: 'language', label: 'Sprache', value: this.selectedOptionTitle(this.languageOptions, this.selection.language) },
                { key: 'branch', label: 'Zweig', value: this.selectedOptionTitle(this.branchOptions, this.selection.branch) },
                { key: 'artsSubject', label: 'ME / BE', value: this.selectedOptionTitle(this.artsSubjectOptions, this.selection.artsSubject) },
            ]
        },
        studentSearchReady() {
            return this.normalizedStudentSearch.length >= 2
        },
        normalizedStudentSearch() {
            return String(this.studentSearch || '').trim().toLowerCase()
        },
        filteredStudentResults() {
            if (!this.studentSearchReady) return []

            return this.robotStudents
                .filter(student => this.studentOptionTitle(student).toLowerCase().includes(this.normalizedStudentSearch))
        },
        studentTotalCountLabel() {
            const count = Array.isArray(this.robotStudents) ? this.robotStudents.length : 0

            return `${this.formatNumber(count)} Studenten gesamt`
        },
        transferredStudentLabel() {
            return this.transferredStudentContext?.student?.label || 'Kein Student'
        },
        transferredStudentCourseSections() {
            const courses = this.transferredStudentContext?.courses || {}

            return [
                { key: 'completed', title: 'Abgeschlossene Kurse', color: 'primary', items: courses.completed || [] },
                { key: 'missing', title: 'Fehlende Kurse', color: 'error', items: courses.missing || [] },
                { key: 'planned', title: 'Vorgesehene Kurse', color: 'info', items: courses.planned || [] },
                { key: 'additional', title: 'Zusätzliche Kurse', color: 'success', items: courses.additional || [] },
            ]
        },
        visibleTransferredStudentCourseSections() {
            return this.transferredStudentCourseSections
        },
        visibleCourseChoiceSemesters() {
            return this.semesters.filter((semester) => this.semesterCourseMenus(semester.value).length > 0)
        },
        allCourseChoiceMenus() {
            return this.visibleCourseChoiceSemesters.flatMap((semester) => (
                this.semesterCourseMenus(semester.value).map((courseMenu) => ({
                    ...courseMenu,
                    semesterValue: semester.value,
                    semesterLabel: semester.label,
                    semesterDateRangeLabel: semester.dateRangeLabel,
                }))
            ))
        },
        selectedCourseMenu() {
            return this.allCourseChoiceMenus.find((courseMenu) => courseMenu.key === this.selectedCourseMenuKey) || null
        },
        visibleTimetableSemesters() {
            return this.semesters.filter((semester) => this.selectedCourseMenuEntries(semester.value).length > 0)
        },
        recurrenceWeekOptionsBySemester() {
            return {
                1: this.buildRecurrenceWeekOptions(1),
                2: this.buildRecurrenceWeekOptions(2),
            }
        },
        extraDatesOptionsBySemester() {
            return {
                1: this.buildExtraDatesOptions(1),
                2: this.buildExtraDatesOptions(2),
            }
        },
        selectedCourseGroupLabel() {
            return this.selectedCourseGroup?.display_label || this.selectedCourseGroup?.title || 'Termine'
        },
        selectedCourseGroupDates() {
            return Array.isArray(this.selectedCourseGroup?.dates) ? this.selectedCourseGroup.dates : []
        },
        selectedCourseGroupDateItems() {
            const timeRangeLabel = this.courseGroupTimeRangeLabel(this.selectedCourseGroup)

            return this.selectedCourseGroupDates.map((date) => ({
                key: `${date}-${timeRangeLabel}`,
                dateLabel: this.formatDateValue(date),
                timeRangeLabel,
            }))
        },
    },

    watch: {
        'config.selected_schoolyear.id'() {
            this.restoreLastTimetableState()
            this.loadData()
        },
    },

    mounted() {
        this.restoreLastTimetableState()
        this.loadData()
    },

    methods: {
        async loadData() {
            this.loading = true
            try {
                const [schoolHoursResponse, courseGroupsResponse] = await Promise.all([
                    axios.get('/api/admin/students-timetables/school-hours'),
                    axios.get('/api/admin/students-timetables/course-groups'),
                ])

                this.schoolHours = schoolHoursResponse.data?.data || []
                this.courseGroups = courseGroupsResponse.data?.data || []
            } catch {
                this.schoolHours = []
                this.courseGroups = []
                this.activeCourseGroupFilterKeys = []
            } finally {
                this.loading = false
            }

            this.studentOptionsLoading = true
            try {
                const studentsResponse = await axios.get('/api/admin/students-timetables/robot/students')

                this.robotStudents = studentsResponse.data?.data || []
            } catch {
                this.robotStudents = []
            } finally {
                this.studentOptionsLoading = false
            }

            try {
                const settingsResponse = await axios.get('/api/admin/students-timetables/subjects-overview-settings')

                this.subjectRows = settingsResponse.data?.data?.subjects || []
            } catch {
                this.subjectRows = []
            }

            if (this.transferredStudentContext?.student?.studentCode) {
                await this.loadTransferredStudentCompletedCourses(this.transferredStudentContext.student.studentCode)
            } else {
                this.refreshTransferredStudentCourseHistory()
            }
        },
        formatTimeValue(value) {
            const raw = (value || '').toString().trim()
            if (!raw) return ''

            return raw.slice(0, 5)
        },
        runTimetableUpdate(action) {
            if (this.timetableUpdatePending) {
                return
            }

            this.timetableUpdatePending = true

            const updateWindow = typeof window !== 'undefined' ? window : null
            const timerTarget = updateWindow || globalThis
            const runAction = () => {
                action()

                this.$nextTick(() => {
                    timerTarget.setTimeout(() => {
                        this.timetableUpdatePending = false
                    }, 0)
                })
            }
            const scheduleActionAfterPaint = () => {
                if (updateWindow && typeof updateWindow.requestAnimationFrame === 'function') {
                    updateWindow.requestAnimationFrame(() => {
                        timerTarget.setTimeout(runAction, 0)
                    })

                    return
                }

                timerTarget.setTimeout(runAction, 0)
            }

            this.$nextTick(scheduleActionAfterPaint)
        },
        handleShowSaturdayClick() {
            this.runTimetableUpdate(() => {
                this.showSaturday = true
                this.saveLastTimetableState()
            })
        },
        handleShowSaturdayUpdate(value) {
            this.runTimetableUpdate(() => {
                this.showSaturday = Boolean(value)
                this.saveLastTimetableState()
            })
        },
        handleCourseMenuEntryFilterClick(entry) {
            this.runTimetableUpdate(() => {
                this.toggleCourseMenuEntryFilter(entry)
            })
        },
        handleSelectedTimetableOptionValueUpdate(semester, value) {
            this.runTimetableUpdate(() => {
                this.setSelectedTimetableOptionValue(semester, value)
            })
        },
        handleToggleRecurrenceWeeksClick(semester) {
            this.runTimetableUpdate(() => {
                this.toggleRecurrenceWeeks(semester)
            })
        },
        handleShowExtraDatesUpdate(semester, value) {
            this.runTimetableUpdate(() => {
                this.setShowExtraDatesInSelectedWeek(semester, value)
            })
        },
        handleCourseChoiceRestrictionUpdate(value) {
            this.runTimetableUpdate(() => {
                this.restrictCourseChoiceBySelection = Boolean(value)
                this.selectedCourseMenuKey = ''
                this.persistTimetableState()
            })
        },
        openStudentDialog() {
            this.studentSelectionDraft = {
                studentCode: this.normalizedStudentCode(this.transferredStudentContext?.student?.studentCode),
            }
            this.studentSearch = ''
            this.studentDialogOpen = true
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
            this.applyTransferredStudentSelection(this.studentSelectionDraft.studentCode)
        },
        clearTransferredStudentSelection() {
            if (!this.transferredStudentContext) return

            this.studentSelectionDraft = {
                studentCode: null,
            }
            this.applyTransferredStudentSelection(null)
        },
        applyTransferredStudentSelection(studentCode) {
            const nextStudentCode = this.normalizedStudentCode(studentCode)
            const selectedStudent = nextStudentCode
                ? this.robotStudents.find(student => String(student.student_code) === nextStudentCode)
                : null

            this.runTimetableUpdate(() => {
                if (!selectedStudent) {
                    this.transferredStudentContext = null
                    this.transferredStudentContextExpanded = false
                    this.studentDialogOpen = false
                    this.studentSearch = ''
                    this.persistTimetableState()

                    return
                }

                const semester = this.studentSemester(selectedStudent)

                if (semester) {
                    this.selection = this.normalizedSelection({
                        ...this.selection,
                        semester,
                    })
                    this.selectionDraft = { ...this.selection }
                }

                this.transferredStudentContext = this.normalizedTransferredStudentContext({
                    student: {
                        studentCode: nextStudentCode,
                        label: this.studentOptionTitle(selectedStudent),
                        semesterLabel: this.studentSemesterLabel(selectedStudent),
                    },
                    courses: {
                        completed: [],
                        missing: [],
                        planned: [],
                        additional: [],
                    },
                })
                this.transferredStudentContextExpanded = false
                this.studentDialogOpen = false
                this.studentSearch = ''
                this.persistTimetableState()
                this.loadTransferredStudentCompletedCourses(nextStudentCode)
            })
        },
        openSelectionDialog() {
            this.selectionDraft = { ...this.selection }
            this.selectionDialogOpen = true
        },
        closeSelectionDialog() {
            this.selectionDialogOpen = false
        },
        updateSelection() {
            this.runTimetableUpdate(() => {
                this.selection = this.normalizedSelection(this.selectionDraft)
                this.refreshTransferredStudentCourseHistory()

                if (!this.transferredStudentContext) {
                    this.persistTimetableState()
                }
            })
            this.selectionDialogOpen = false
        },
        toggleTransferredStudentContext() {
            if (!this.transferredStudentContext) return

            this.transferredStudentContextExpanded = !this.transferredStudentContextExpanded
            this.persistTimetableState()
        },
        deselectAllCourses() {
            this.runTimetableUpdate(() => {
                this.activeCourseGroupFilterKeys = []
                this.persistTimetableState()
            })
        },
        resetSavedTimetable() {
            this.runTimetableUpdate(() => {
                this.removeSavedTimetableState()
                this.applyTimetableState(this.defaultTimetableState())
            })
        },
        defaultTimetableState() {
            return {
                activeCourseGroupFilterKeys: [],
                selectedRecurrenceWeeks: {
                    1: ALL_DATES_OPTION_VALUE,
                    2: ALL_DATES_OPTION_VALUE,
                },
                expandedRecurrenceWeeks: {
                    1: false,
                    2: false,
                },
                showExtraDatesInSelectedWeeks: {
                    1: false,
                    2: false,
                },
                showSaturday: false,
                restrictCourseChoiceBySelection: false,
                selection: this.defaultSelection(),
                transferredStudentContext: null,
                transferredStudentContextExpanded: false,
            }
        },
        defaultSelection() {
            return {
                semester: 1,
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            }
        },
        currentTimetableState() {
            const defaults = this.defaultTimetableState()

            return {
                activeCourseGroupFilterKeys: [...new Set(this.activeCourseGroupFilterKeys || [])],
                selectedRecurrenceWeeks: {
                    ...defaults.selectedRecurrenceWeeks,
                    ...(this.selectedRecurrenceWeeks || {}),
                },
                expandedRecurrenceWeeks: {
                    ...defaults.expandedRecurrenceWeeks,
                    ...(this.expandedRecurrenceWeeks || {}),
                },
                showExtraDatesInSelectedWeeks: {
                    ...defaults.showExtraDatesInSelectedWeeks,
                    ...(this.showExtraDatesInSelectedWeeks || {}),
                },
                showSaturday: Boolean(this.showSaturday),
                restrictCourseChoiceBySelection: Boolean(this.restrictCourseChoiceBySelection),
                selection: this.normalizedSelection(this.selection || defaults.selection),
                transferredStudentContext: this.normalizedTransferredStudentContext(this.transferredStudentContext),
                transferredStudentContextExpanded: Boolean(this.transferredStudentContextExpanded),
            }
        },
        applyTimetableState(state) {
            const defaults = this.defaultTimetableState()

            this.activeCourseGroupFilterKeys = Array.isArray(state?.activeCourseGroupFilterKeys)
                ? [...new Set(state.activeCourseGroupFilterKeys.filter(Boolean))]
                : defaults.activeCourseGroupFilterKeys
            this.selectedRecurrenceWeeks = {
                ...defaults.selectedRecurrenceWeeks,
                ...(state?.selectedRecurrenceWeeks || {}),
            }
            this.expandedRecurrenceWeeks = {
                ...defaults.expandedRecurrenceWeeks,
                ...(state?.expandedRecurrenceWeeks || {}),
            }
            this.showExtraDatesInSelectedWeeks = {
                ...defaults.showExtraDatesInSelectedWeeks,
                ...(state?.showExtraDatesInSelectedWeeks || {}),
            }
            this.showSaturday = Boolean(state?.showSaturday ?? defaults.showSaturday)
            this.restrictCourseChoiceBySelection = Boolean(
                state?.restrictCourseChoiceBySelection ?? defaults.restrictCourseChoiceBySelection,
            )
            this.selection = this.normalizedSelection(state?.selection || defaults.selection)
            this.selectionDraft = { ...this.selection }
            this.transferredStudentContext = this.normalizedTransferredStudentContext(
                state?.transferredStudentContext ?? defaults.transferredStudentContext,
            )
            this.transferredStudentContextExpanded = Boolean(
                state?.transferredStudentContextExpanded ?? defaults.transferredStudentContextExpanded,
            )
        },
        async loadTransferredStudentCompletedCourses(studentCode) {
            const normalizedStudentCode = this.normalizedStudentCode(studentCode)
            const requestId = this.studentCompletedCoursesRequestId + 1
            this.studentCompletedCoursesRequestId = requestId

            if (!normalizedStudentCode) return

            try {
                const response = await axios.get('/api/admin/students-timetables/robot/student-completed-courses', {
                    params: {
                        student_code: normalizedStudentCode,
                    },
                })

                if (requestId !== this.studentCompletedCoursesRequestId) return
                if (this.normalizedStudentCode(this.transferredStudentContext?.student?.studentCode) !== normalizedStudentCode) return

                const completedCourses = response.data?.data || []

                this.transferredStudentContext = this.normalizedTransferredStudentContext({
                    ...this.transferredStudentContext,
                    courses: this.overviewStudentCourseHistory(completedCourses),
                })
                this.persistTimetableState()
            } catch {
                if (requestId !== this.studentCompletedCoursesRequestId) return
            }
        },
        normalizedSelection(selection) {
            const defaults = this.defaultSelection()
            const semester = Number(selection?.semester ?? defaults.semester)

            return {
                semester: Number.isFinite(semester) ? semester : defaults.semester,
                religion: selection?.religion || defaults.religion,
                language: selection?.language || defaults.language,
                branch: selection?.branch || defaults.branch,
                artsSubject: selection?.artsSubject || defaults.artsSubject,
            }
        },
        selectedOptionTitle(options, value) {
            return options.find(option => option.value === value)?.title || String(value || '')
        },
        normalizedStudentCode(value) {
            const studentCode = value === null || value === undefined ? '' : String(value).trim()

            return studentCode === '' ? null : studentCode
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
        studentSemesterLabel(student) {
            const semester = this.studentSemester(student)

            return semester ? `Semester ${semester}` : ''
        },
        studentSemester(student) {
            const schoolLevel = this.studentSchoolLevelKey(student)

            return this.studentSemesterBySchoolLevel()[schoolLevel] || null
        },
        studentSchoolLevelKey(student) {
            const importedSchoolLevel = this.normalizedStudentSchoolLevel(
                student?.school_level,
                student?.attendance_year,
            )
            if (importedSchoolLevel) return importedSchoolLevel

            return this.normalizedStudentSchoolLevel(student?.class)
        },
        normalizedStudentSchoolLevel(schoolLevel, attendanceYear = null) {
            const normalizedAttendanceYear = String(attendanceYear || '').trim()
            if (normalizedAttendanceYear !== '') {
                const normalizedSchoolLevel = this.normalizedStudentSchoolLevelToken(schoolLevel)
                if (normalizedSchoolLevel && ['1', '2'].includes(normalizedAttendanceYear)) {
                    return `${normalizedSchoolLevel}_${normalizedAttendanceYear}`
                }
            }

            const value = String(schoolLevel || '').trim()
            if (!value) return ''

            const normalizedValue = value.replace(/[.\-\s]+/g, '_')
            const schoolLevelMatch = normalizedValue.match(/(?:^|[^0-9])(0?9|1[0-2])_?([12])(?:$|[^0-9])/)
            if (!schoolLevelMatch) return ''

            return `${schoolLevelMatch[1].padStart(2, '0')}_${schoolLevelMatch[2]}`
        },
        normalizedStudentSchoolLevelToken(value) {
            const schoolLevelMatch = String(value || '').trim().match(/^(0?9|1[0-2])$/)

            return schoolLevelMatch ? schoolLevelMatch[1].padStart(2, '0') : ''
        },
        studentOptionTitle(student) {
            const schoolClass = String(student?.class || '').trim()
            const lastName = String(student?.last_name || '').trim()
            const firstName = String(student?.first_name || '').trim()
            const semester = this.studentSemesterLabel(student)
            const name = [lastName, firstName].filter(Boolean).join(' ')

            return [schoolClass, name, semester].filter(Boolean).join(' · ')
        },
        formatNumber(value) {
            return new Intl.NumberFormat('de-AT').format(Number(value || 0))
        },
        overviewCompletedCourseItems(courses) {
            return (Array.isArray(courses) ? courses : [])
                .map((course, index) => {
                    const code = String(course?.subject || course?.code || '').trim()
                    const grade = String(course?.grade || '').trim()

                    return {
                        key: `completed-${code || index}-${grade || index}`,
                        code,
                        label: code,
                        meta: grade,
                    }
                })
                .filter(course => course.label)
        },
        overviewStudentCourseHistory(completedCourses) {
            const completedCourseItems = Array.isArray(completedCourses) ? completedCourses : []
            const semester = Number(this.selection?.semester || 0)
            const completedCourseCodes = this.studentCompletedCourseCodes(completedCourseItems)
            const visitedCourseCodes = this.studentVisitedCourseCodes(completedCourseItems)
            const missingCourses = semester
                ? this.pendingStudentCoursesBeforeSemester(semester, completedCourseCodes, visitedCourseCodes)
                : []
            const plannedCourses = semester
                ? this.studentPlannedCoursesForSemester(semester, completedCourseCodes)
                : []
            const unavailableAdditionalCourseCodes = this.studentUnavailableAdditionalCourseCodes(completedCourseCodes, [
                ...missingCourses,
                ...plannedCourses,
            ])
            const additionalCourses = semester
                ? this.coursesAfterSemester(semester)
                    .filter(course => !this.courseCompletedForStudentPlanning(course, unavailableAdditionalCourseCodes))
                    .filter(course => this.coursePossibleAsStudentAdditional(course, completedCourseCodes, visitedCourseCodes))
                : []

            return {
                completed: this.overviewCompletedCourseItems(completedCourseItems),
                missing: this.overviewCourseItems(missingCourses),
                planned: this.overviewCourseItems(plannedCourses),
                additional: this.overviewCourseItems(additionalCourses),
            }
        },
        refreshTransferredStudentCourseHistory() {
            if (!this.transferredStudentContext) return

            const completedCourses = (this.transferredStudentContext.courses?.completed || [])
                .map(course => ({
                    subject: course?.code || course?.label,
                    code: course?.code || course?.label,
                    grade: course?.grade || course?.meta,
                }))

            this.transferredStudentContext = this.normalizedTransferredStudentContext({
                ...this.transferredStudentContext,
                courses: this.overviewStudentCourseHistory(completedCourses),
            })
            this.persistTimetableState()
        },
        overviewCourseItems(courses) {
            return (Array.isArray(courses) ? courses : [])
                .map((course, index) => {
                    const code = String(course?.code || course?.subject || '').trim()
                    const name = String(course?.name || course?.title || '').trim()
                    const hours = Number(course?.hours || 0)

                    return {
                        key: course?.key || `${code || index}-${index}`,
                        code,
                        name,
                        label: code || name,
                        meta: hours ? `${this.formatHours(hours)} Std.` : '',
                    }
                })
                .filter(course => course.label)
        },
        coursesForSemester(semester) {
            return (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .filter(subject => subject?.is_active !== false)
                .filter(subject => Number(subject?.semester) === Number(semester))
                .filter(subject => this.subjectMatchesSelectedBranch(subject))
                .filter(subject => this.subjectMatchesSelectedChoices(subject))
                .flatMap(subject => this.selectedCoursesFromSubject(subject))
                .sort((firstCourse, secondCourse) => this.compareCourses(firstCourse, secondCourse))
        },
        coursesAfterSemester(semester) {
            return (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .map(subject => Number(subject?.semester))
                .filter(subjectSemester => Number.isFinite(subjectSemester) && subjectSemester > Number(semester))
                .filter((subjectSemester, index, subjectSemesters) => subjectSemesters.indexOf(subjectSemester) === index)
                .sort((firstSemester, secondSemester) => firstSemester - secondSemester)
                .flatMap(subjectSemester => this.coursesForSemester(subjectSemester))
        },
        studentPlannedCoursesForSemester(semester, completedCourseCodes) {
            return this.coursesForSemester(semester)
                .filter(course => !this.courseCompletedForStudentPlanning(course, completedCourseCodes))
                .filter((course, index, courses) =>
                    courses.findIndex(candidate => candidate.key === course.key) === index,
                )
                .sort((firstCourse, secondCourse) => this.compareCourses(firstCourse, secondCourse))
        },
        pendingStudentCoursesBeforeSemester(semester, completedCourseCodes, visitedCourseCodes) {
            return (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .map(subject => Number(subject?.semester))
                .filter(subjectSemester => Number.isFinite(subjectSemester) && subjectSemester < Number(semester))
                .filter((subjectSemester, index, subjectSemesters) => subjectSemesters.indexOf(subjectSemester) === index)
                .sort((firstSemester, secondSemester) => firstSemester - secondSemester)
                .flatMap(subjectSemester => this.coursesForSemester(subjectSemester))
                .filter(course => !this.courseCompletedForStudentPlanning(course, completedCourseCodes))
                .filter(course => this.coursePossibleAsStudentMissing(course, completedCourseCodes, visitedCourseCodes))
                .filter((course, index, courses) =>
                    courses.findIndex(candidate => candidate.key === course.key) === index,
                )
                .sort((firstCourse, secondCourse) => this.compareCourses(firstCourse, secondCourse))
        },
        studentCompletedCourseCodes(completedCourses) {
            return new Set((Array.isArray(completedCourses) ? completedCourses : [])
                .filter(course => this.completedCourseCountsAsDone(course?.grade))
                .flatMap(course => this.courseCodeAliasParts(course?.subject || course?.code))
                .map(courseCode => this.normalizedCourseCode(courseCode))
                .filter(Boolean))
        },
        studentVisitedCourseCodes(completedCourses) {
            return new Set((Array.isArray(completedCourses) ? completedCourses : [])
                .flatMap(course => this.courseCodeAliasParts(course?.subject || course?.code))
                .map(courseCode => this.normalizedCourseCode(courseCode))
                .filter(Boolean))
        },
        studentUnavailableAdditionalCourseCodes(completedCourseCodes, plannedCourses) {
            const unavailableCourseCodes = new Set(completedCourseCodes)

            ;(Array.isArray(plannedCourses) ? plannedCourses : [])
                .flatMap(course => this.courseCodeAliases(course))
                .forEach(courseCode => unavailableCourseCodes.add(courseCode))

            return unavailableCourseCodes
        },
        completedCourseCountsAsDone(grade) {
            const normalizedGrade = String(grade || '').trim().toLocaleUpperCase('de-AT')

            return normalizedGrade === 'B' || ['1', '2', '3', '4'].includes(normalizedGrade)
        },
        courseCompletedForStudentPlanning(course, completedCourseCodes) {
            if (!(completedCourseCodes instanceof Set) || !completedCourseCodes.size) return false

            return this.courseCodeAliases(course)
                .some(courseCode => completedCourseCodes.has(courseCode))
        },
        coursePossibleAsStudentAdditional(course, completedCourseCodes, visitedCourseCodes) {
            return this.courseModulePartsForStudentPlanning(course)
                .some(parts => this.courseModulePrerequisiteMet(parts, completedCourseCodes, visitedCourseCodes))
        },
        coursePossibleAsStudentMissing(course, completedCourseCodes, visitedCourseCodes) {
            return this.courseModulePartsForStudentPlanning(course)
                .some(parts => this.courseModulePrerequisiteMet(parts, completedCourseCodes, visitedCourseCodes, {
                    allowInitialModules: true,
                }))
        },
        courseModulePartsForStudentPlanning(course) {
            return this.courseCodeAliases(course)
                .map(courseCode => this.courseCodeModuleParts(courseCode))
                .filter(parts => parts.module)
                .filter(parts => this.courseBaseEligibleForStudentAdditional(parts.base))
                .filter((parts, index, allParts) =>
                    allParts.findIndex(candidate => candidate.base === parts.base && candidate.module === parts.module) === index,
                )
        },
        courseBaseEligibleForStudentAdditional(base) {
            const baseAliases = this.studentCourseBaseAliases(base)
            const subjectRows = Array.isArray(this.subjectRows) ? this.subjectRows : []

            if (!subjectRows.length) return true

            return subjectRows
                .filter(subject => subject?.is_active !== false)
                .some(subject => this.subjectRowMatchesStudentCourseBase(subject, baseAliases))
        },
        subjectRowMatchesStudentCourseBase(subject, baseAliases) {
            return [
                subject?.json_code,
                subject?.json_subject,
            ]
                .flatMap(value => this.courseCodeAliasParts(value))
                .map(value => this.courseCodeModuleParts(value).base)
                .flatMap(base => this.studentCourseBaseAliases(base))
                .some(baseAlias => baseAliases.includes(baseAlias))
        },
        courseModulePrerequisiteMet(parts, completedCourseCodes, visitedCourseCodes, options = {}) {
            const moduleNumber = Number(parts.module)
            if (!Number.isInteger(moduleNumber)) return false

            const baseAliases = this.studentCourseBaseAliases(parts.base)
            if (moduleNumber === 1) {
                if (options?.allowInitialModules) {
                    return !this.courseBaseHasVisitedLaterModule(baseAliases, visitedCourseCodes, moduleNumber)
                }

                return baseAliases.some(baseAlias => ['ME', 'MU', 'BE'].includes(baseAlias))
            }

            if (moduleNumber === 2) return true

            const prerequisiteModuleNumber = moduleNumber - 2
            const hasPositivePrerequisite = baseAliases
                .some(baseAlias => completedCourseCodes.has(`${baseAlias}${prerequisiteModuleNumber}`))

            if (!hasPositivePrerequisite) return false

            if (['E', 'M'].includes(this.normalizedCourseCode(parts.base)) && moduleNumber === 8) {
                return baseAliases.some(baseAlias => visitedCourseCodes.has(`${baseAlias}7`))
            }

            return true
        },
        courseBaseHasVisitedLaterModule(baseAliases, visitedCourseCodes, moduleNumber) {
            if (!(visitedCourseCodes instanceof Set) || !visitedCourseCodes.size) return false

            return [...visitedCourseCodes]
                .map(courseCode => this.courseCodeModuleParts(courseCode))
                .some(parts => {
                    const visitedModuleNumber = Number(parts.module)

                    return baseAliases.includes(parts.base)
                        && Number.isInteger(visitedModuleNumber)
                        && visitedModuleNumber > moduleNumber
                })
        },
        studentCourseBaseAliases(base) {
            const normalizedBase = this.normalizedCourseCode(base)
            const mappedAliases = {
                ET: ['ETH'],
                ETH: ['ET'],
                GPB: ['GS'],
                GS: ['GPB'],
                GW: ['GWB'],
                GWB: ['GW'],
                LPT: ['LET'],
                LET: ['LPT'],
                ME: ['MU'],
                MU: ['ME'],
                R: ['RK'],
                RK: ['R'],
                S: ['SPA'],
                SPA: ['S'],
            }

            return this.uniqueValues([
                normalizedBase,
                ...(mappedAliases[normalizedBase] || []),
            ])
        },
        subjectMatchesSelectedBranch(subject) {
            if (this.isArtsSubject(subject)) {
                return subject.branch === 'gymnasial' && this.selection.branch === 'gymnasial'
            }

            return !subject.branch || subject.branch === 'common' || subject.branch === this.selection.branch
        },
        subjectMatchesSelectedChoices(subject) {
            if (this.isArtsSubject(subject)) return this.subjectBaseKey(subject) === this.selection.artsSubject
            if (this.isLanguageSubject(subject)) return this.languageSubjectMatchesSelection(subject)

            return true
        },
        languageSubjectMatchesSelection(subject) {
            const languageCode = this.languageSubjectCode(subject)

            return !languageCode || languageCode === this.selection.language
        },
        languageSubjectCode(subject) {
            const baseKey = this.normalizedCourseCode(this.subjectBaseKey(subject))
            if (['L', 'F', 'S'].includes(baseKey)) return baseKey
            if (baseKey !== 'L/F/S') return ''

            const jsonCodeParts = this.courseCodeAliasParts(this.courseCodeWithoutModule(subject.json_code))
                .map(value => this.normalizedCourseCode(value))

            return jsonCodeParts.length === 1 && ['L', 'F', 'S'].includes(jsonCodeParts[0])
                ? jsonCodeParts[0]
                : ''
        },
        selectedCoursesFromSubject(subject) {
            return this.subjectCourseVariants(subject)
                .map(courseSubject => this.selectedCourseFromSubject(courseSubject))
        },
        subjectCourseVariants(subject) {
            if (this.isReligionSubject(subject) || this.isLanguageSubject(subject)) return [subject]

            const courseCodes = this.courseCodeAliasParts(subject.json_code)
            if (courseCodes.length <= 1) return [subject]

            const splitHours = Number(subject.hours_per_week || 0) / courseCodes.length

            return courseCodes.map(courseCode => ({
                ...subject,
                json_code: courseCode,
                hours_per_week: Number.isFinite(splitHours) ? splitHours : subject.hours_per_week,
            }))
        },
        selectedCourseFromSubject(subject) {
            const selectedCourseCode = this.selectedCourseCode(subject)

            return {
                key: [
                    subject.id || subject.local_id || '',
                    subject.semester || '',
                    subject.branch || 'common',
                    subject.json_code || '',
                    subject.json_subject || '',
                    subject.name || '',
                    selectedCourseCode,
                ].join('|'),
                code: selectedCourseCode,
                name: this.selectedCourseName(subject),
                branch: this.selectedCourseBranch(subject),
                hours: Number(subject.hours_per_week || 0),
            }
        },
        selectedCourseCode(subject) {
            if (this.isReligionSubject(subject)) return `${this.selection.religion}${this.subjectModuleNumber(subject)}`
            if (this.isLanguageSubject(subject)) return `${this.selection.language}${this.subjectModuleNumber(subject)}`

            return this.alternativeDisplay(subject.json_code || subject.json_subject || subject.name)
        },
        selectedCourseName(subject) {
            const moduleNumber = this.subjectModuleNumber(subject)

            if (this.isReligionSubject(subject)) {
                return `${this.selectedOptionDescription(this.religionOptions, this.selection.religion)} ${moduleNumber}`.trim()
            }

            if (this.isLanguageSubject(subject)) {
                return `${this.selectedOptionDescription(this.languageOptions, this.selection.language)} ${moduleNumber}`.trim()
            }

            if (this.isArtsSubject(subject)) {
                return `${this.selectedOptionDescription(this.artsSubjectOptions, this.selection.artsSubject)} ${moduleNumber}`.trim()
            }

            return subject.name || subject.json_subject || subject.json_code || '-'
        },
        selectedOptionDescription(options, value) {
            return String(this.selectedOptionTitle(options, value)).split(' - ').pop()
        },
        selectedCourseBranch(subject) {
            if (!subject.branch || subject.branch === 'common') return 'alle'

            return this.selectedOptionTitle(this.branchOptions, subject.branch)
        },
        subjectBaseKey(subject) {
            const jsonSubject = String(subject?.json_subject || '').trim()

            return jsonSubject || String(subject?.json_code || '').replace(/\d+$/u, '')
        },
        subjectModuleNumber(subject) {
            const moduleMatch = String(subject?.json_code || '').match(/(\d+)$/u)

            return moduleMatch?.[1] || ''
        },
        isReligionSubject(subject) {
            return this.subjectBaseKey(subject) === 'R/ET'
        },
        isLanguageSubject(subject) {
            const baseKey = this.normalizedCourseCode(this.subjectBaseKey(subject))

            return baseKey === 'L/F/S' || ['L', 'F', 'S'].includes(baseKey)
        },
        isArtsSubject(subject) {
            return ['ME', 'BE'].includes(this.subjectBaseKey(subject))
        },
        alternativeDisplay(value) {
            const normalizedValue = String(value || '').trim()
            if (!normalizedValue.includes('/')) return normalizedValue || '-'

            return normalizedValue
                .split('/')
                .map(part => part.trim())
                .filter(Boolean)
                .join(' / ')
        },
        compareCourses(firstCourse, secondCourse) {
            return String(firstCourse?.code || '').localeCompare(String(secondCourse?.code || ''), 'de-AT', {
                numeric: true,
                sensitivity: 'base',
            })
        },
        formatHours(value) {
            const hours = Number(value || 0)

            if (Number.isInteger(hours)) return String(hours)

            return String(hours).replace('.', ',')
        },
        normalizedTransferredStudentContext(context) {
            if (!context?.student) return null

            return {
                student: {
                    studentCode: String(context.student?.studentCode || '').trim(),
                    label: String(context.student?.label || '').trim(),
                    semesterLabel: String(context.student?.semesterLabel || '').trim(),
                },
                courses: {
                    completed: this.normalizedTransferredStudentCourses(context.courses?.completed),
                    missing: this.normalizedTransferredStudentCourses(context.courses?.missing),
                    planned: this.normalizedTransferredStudentCourses(context.courses?.planned),
                    additional: this.normalizedTransferredStudentCourses(context.courses?.additional),
                },
            }
        },
        normalizedTransferredStudentCourses(courses) {
            return (Array.isArray(courses) ? courses : [])
                .map((course, index) => {
                    const label = String(course?.label || course?.code || course?.subject || course?.name || '').trim()

                    return {
                        key: String(course?.key || `${label || 'course'}-${index}`).trim(),
                        code: String(course?.code || course?.subject || '').trim(),
                        name: String(course?.name || '').trim(),
                        label,
                        meta: String(course?.meta || course?.grade || '').trim(),
                    }
                })
                .filter(course => course.label)
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
        saveLastTimetableState() {
            try {
                this.timetableStorage()?.setItem(
                    this.timetableStorageKey(),
                    JSON.stringify(this.currentTimetableState()),
                )
            } catch {
                // Ignore unavailable or full browser storage.
            }
        },
        restoreLastTimetableState() {
            try {
                const storedState = this.timetableStorage()?.getItem(this.timetableStorageKey())
                if (!storedState) {
                    this.applyTimetableState(this.defaultTimetableState())

                    return
                }

                this.applyTimetableState(JSON.parse(storedState))
            } catch {
                this.applyTimetableState(this.defaultTimetableState())
            }
        },
        removeSavedTimetableState() {
            try {
                this.timetableStorage()?.removeItem(this.timetableStorageKey())
            } catch {
                // Ignore unavailable browser storage.
            }
        },
        persistTimetableState() {
            this.saveLastTimetableState()
        },
        courseGroupsForCell(semester, weekday, hour, recurrenceWeek = null) {
            const activeCourseGroupFilterKeySet = this.activeCourseGroupFilterKeySet instanceof Set
                ? this.activeCourseGroupFilterKeySet
                : new Set(this.activeCourseGroupFilterKeys || [])

            return [...(this.courseGroupsByCell[this.courseCellKey(semester, weekday, hour)] || [])]
                .filter((courseGroup) => (
                    activeCourseGroupFilterKeySet.has(courseGroup?.key)
                    && this.courseGroupMatchesSelectedRecurrenceWeek(courseGroup, semester, recurrenceWeek)
                ))
                .sort((left, right) => this.courseGroupSortLabel(left).localeCompare(
                    this.courseGroupSortLabel(right),
                    'de',
                    { sensitivity: 'base' },
                ))
        },
        displayCourseGroupsForCell(semester, weekday, hour, recurrenceWeek = null) {
            const directCourseGroups = this.courseGroupsForCell(semester, weekday, hour, recurrenceWeek)
            if (!directCourseGroups.length) return []

            const directCourseGroupKeys = directCourseGroups
                .map(courseGroup => courseGroup?.key)
                .filter(Boolean)
            const activeCourseGroupFilterKeySet = this.activeCourseGroupFilterKeySet instanceof Set
                ? this.activeCourseGroupFilterKeySet
                : new Set(this.activeCourseGroupFilterKeys || [])
            const overlappingCourseGroups = this.configuredCourseGroups
                .filter(courseGroup => activeCourseGroupFilterKeySet.has(courseGroup?.key))
                .filter(courseGroup => !directCourseGroupKeys.includes(courseGroup?.key))
                .filter(courseGroup => this.courseGroupMatchesSelectedRecurrenceWeek(courseGroup, semester, recurrenceWeek))
                .filter(courseGroup => directCourseGroups.some(directCourseGroup =>
                    this.courseGroupsOverlap(directCourseGroup, courseGroup),
                ))

            return this.uniqueCourseGroupsByKey([
                ...directCourseGroups,
                ...overlappingCourseGroups,
            ]).sort((left, right) => this.courseGroupSortLabel(left).localeCompare(
                this.courseGroupSortLabel(right),
                'de',
                { sensitivity: 'base' },
            ))
        },
        uniqueCourseGroupsByKey(courseGroups) {
            return Object.values((Array.isArray(courseGroups) ? courseGroups : []).reduce((groups, courseGroup) => {
                const key = String(courseGroup?.key || '').trim()
                if (!key) return groups

                groups[key] ??= courseGroup

                return groups
            }, {}))
        },
        courseGroupSortLabel(courseGroup) {
            return (courseGroup?.display_label || courseGroup?.title || '').toString()
        },
        semesterCourseMenus(semester) {
            if (this.semesterCourseMenusBySemester) {
                return this.semesterCourseMenusBySemester[Number(semester)] || []
            }

            return this.buildSemesterCourseMenus(semester)
        },
        openCourseMenuDialog() {
            this.selectedCourseMenuKey = ''
            this.courseMenuDialog = true
        },
        selectCourseMenu(courseMenu) {
            this.selectedCourseMenuKey = courseMenu?.key || ''
        },
        courseMenuHasActiveSelection(courseMenu) {
            return (courseMenu?.entries || []).some((entry) => this.isCourseMenuEntryFilterActive(entry))
        },
        buildSemesterCourseMenus(semester) {
            const sourceCourseGroups = Array.isArray(this.courseChoiceCourseGroups)
                ? this.courseChoiceCourseGroups
                : this.configuredCourseGroups
            const courseMenusByLabel = sourceCourseGroups
                .filter((courseGroup) => Number(courseGroup?.semester) === Number(semester))
                .reduce((courseMenus, courseGroup) => {
                    const label = this.mainCourseLabel(courseGroup)
                    if (!label) {
                        return courseMenus
                    }

                    const normalizedLabel = label.toLocaleUpperCase('de-AT')
                    if (!courseMenus.has(normalizedLabel)) {
                        courseMenus.set(normalizedLabel, {
                            key: `semester-${semester}-${normalizedLabel}`,
                            label,
                            entriesByLabel: new Map(),
                        })
                    }

                    const courseMenu = courseMenus.get(normalizedLabel)
                    const entryLabel = this.courseGroupMenuLabel(courseGroup)
                    const normalizedEntryLabel = entryLabel.toLocaleUpperCase('de-AT')
                    if (!courseMenu.entriesByLabel.has(normalizedEntryLabel)) {
                        courseMenu.entriesByLabel.set(normalizedEntryLabel, {
                            key: `semester-${semester}-${normalizedLabel}-${normalizedEntryLabel}`,
                            label: entryLabel,
                            courseGroups: [],
                            courseGroupKeys: [],
                        })
                    }

                    const entry = courseMenu.entriesByLabel.get(normalizedEntryLabel)
                    entry.courseGroups.push(courseGroup)
                    if (courseGroup?.key) {
                        entry.courseGroupKeys.push(courseGroup.key)
                    }

                    return courseMenus
                }, new Map())

            return [...courseMenusByLabel.values()]
                .map((courseMenu) => ({
                    key: courseMenu.key,
                    label: courseMenu.label,
                    entries: [...courseMenu.entriesByLabel.values()]
                        .map((entry) => ({
                            ...entry,
                            courseGroupKeys: [...new Set(entry.courseGroupKeys)],
                            scheduleLabel: this.courseMenuEntryScheduleLabelWithFrequency(entry),
                        }))
                        .sort((left, right) => (
                            left.label.localeCompare(right.label, 'de', { sensitivity: 'base' })
                        )),
                }))
                .sort((left, right) => left.label.localeCompare(right.label, 'de', { sensitivity: 'base' }))
        },
        courseGroupMatchesCourseChoiceRestriction(courseGroup) {
            if (
                this.shouldRestrictCourseChoiceByTimetableSemester()
                && !this.courseGroupMatchesSelectedTimetableSemester(courseGroup)
            ) return false
            if (!this.courseGroupMatchesSelectedChoiceOptions(courseGroup)) return false

            const studentCourseCodes = this.restrictedStudentCourseCodes instanceof Set
                ? this.restrictedStudentCourseCodes
                : new Set()
            if (!studentCourseCodes.size) return true

            return this.courseGroupCodes(courseGroup)
                .some(courseCode => studentCourseCodes.has(courseCode))
        },
        shouldRestrictCourseChoiceByTimetableSemester() {
            const selectedTimetableSemester = this.selectedTimetableSemesterForSelection()
            if (!selectedTimetableSemester) return false

            return (Array.isArray(this.configuredCourseGroups) ? this.configuredCourseGroups : [])
                .some(courseGroup => Number(courseGroup?.semester) === selectedTimetableSemester)
        },
        selectedTimetableSemesterForSelection() {
            const selectedSemester = Number(this.selection?.semester || 0)
            if (!Number.isFinite(selectedSemester) || selectedSemester <= 0) return null

            return selectedSemester % 2 === 0 ? 2 : 1
        },
        courseGroupMatchesSelectedTimetableSemester(courseGroup) {
            const timetableSemester = this.selectedTimetableSemesterForSelection()
            if (!timetableSemester) return true

            return Number(courseGroup?.semester) === timetableSemester
        },
        courseGroupMatchesSelectedChoiceOptions(courseGroup) {
            const courseBases = this.courseGroupCodes(courseGroup)
                .map(courseCode => this.courseCodeWithoutModule(courseCode))
                .filter(Boolean)

            return this.courseBasesMatchSelectedOption(courseBases, this.religionCourseBases(), this.selection?.religion)
                && this.courseBasesMatchSelectedOption(courseBases, this.languageCourseBases(), this.selection?.language)
                && this.courseBasesMatchSelectedOption(courseBases, this.artsCourseBases(), this.selection?.artsSubject)
        },
        courseBasesMatchSelectedOption(courseBases, optionBases, selectedOption) {
            const matchingOptionBases = courseBases
                .filter(courseBase => optionBases.includes(courseBase))

            if (!matchingOptionBases.length) return true

            const selectedAliases = this.selectionCourseAliases(selectedOption)
                .map(alias => this.courseCodeWithoutModule(alias))

            return matchingOptionBases.some(courseBase => selectedAliases.includes(courseBase))
        },
        religionCourseBases() {
            return this.uniqueValues(this.religionOptions
                .flatMap(option => this.selectionCourseAliases(option.value))
                .map(alias => this.courseCodeWithoutModule(alias)))
        },
        languageCourseBases() {
            return this.uniqueValues([
                ...this.languageOptions.flatMap(option => this.selectionCourseAliases(option.value)),
                'SPA',
                'LET',
                'LPT',
            ].map(alias => this.courseCodeWithoutModule(alias)))
        },
        artsCourseBases() {
            return this.uniqueValues([
                ...this.artsSubjectOptions.flatMap(option => this.selectionCourseAliases(option.value)),
                'MU',
            ].map(alias => this.courseCodeWithoutModule(alias)))
        },
        selectionCourseAliases(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const aliases = {
                L: ['L', 'LET', 'LPT'],
                LPT: ['L', 'LET', 'LPT'],
                LET: ['L', 'LET', 'LPT'],
                ME: ['ME', 'MU'],
                MU: ['ME', 'MU'],
                S: ['S', 'SPA'],
                SPA: ['S', 'SPA'],
            }

            return this.uniqueValues([
                normalizedValue,
                ...(aliases[normalizedValue] || []),
                this.defaultTimetableCodeAlias(normalizedValue),
            ].filter(Boolean))
        },
        courseGroupCodes(courseGroup) {
            return this.courseAliasesFromValues([
                courseGroup?.course,
                courseGroup?.title,
                courseGroup?.display_label,
                courseGroup?.subject,
            ])
        },
        courseAliasesFromValues(values) {
            return this.uniqueValues((Array.isArray(values) ? values : [])
                .flatMap(value => this.courseCodeTokensFromValue(value))
                .flatMap(value => [
                    value,
                    this.defaultTimetableCodeAlias(value),
                ])
                .map(value => this.normalizedCourseCode(value))
                .filter(Boolean))
        },
        courseCodeAliases(course) {
            return this.uniqueValues([
                course?.code,
                course?.ttCode,
                ...(Array.isArray(course?.ttCodes) ? course.ttCodes : []),
                course?.subject,
            ]
                .flatMap(value => this.courseCodeAliasParts(value))
                .flatMap(value => [
                    value,
                    this.defaultTimetableCodeAlias(value),
                ])
                .map(value => this.normalizedCourseCode(value))
                .filter(Boolean))
        },
        courseCodeAliasParts(value) {
            return String(value || '')
                .split('/')
                .map(part => part.trim())
                .filter(Boolean)
        },
        courseCodeModuleParts(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const moduleMatch = normalizedValue.match(/^([A-ZÄÖÜ]+)(\d+)$/u)

            if (!moduleMatch) {
                return {
                    base: this.courseCodeWithoutModule(normalizedValue),
                    module: '',
                }
            }

            return {
                base: this.courseCodeWithoutModule(moduleMatch[1]),
                module: moduleMatch[2],
            }
        },
        defaultTimetableCodeAlias(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const match = normalizedValue.match(/^([A-ZÄÖÜ]+)(\d*)$/u)
            if (!match) return ''

            const aliases = {
                GS: 'GPB',
                GW: 'GWB',
                LPT: 'LET',
                LET: 'LPT',
                ME: 'MU',
                MU: 'ME',
                S: 'SPA',
                SPA: 'S',
            }
            const mappedBase = aliases[match[1]]

            return mappedBase ? `${mappedBase}${match[2] || ''}` : ''
        },
        courseCodeTokensFromValue(value) {
            return [...String(value || '').matchAll(/[A-Za-zÄÖÜäöüß]+[0-9]*/gu)]
                .map(match => match[0])
        },
        courseCodeWithoutModule(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const match = normalizedValue.match(/^([A-ZÄÖÜ]+)\d*$/u)

            return match?.[1] || normalizedValue
        },
        normalizedCourseCode(value) {
            return String(value || '')
                .trim()
                .toLocaleUpperCase('de-AT')
                .replace(/\s+/gu, '')
        },
        uniqueValues(values) {
            return [...new Set((Array.isArray(values) ? values : []).filter(Boolean))]
        },
        semesterCourseChips(semester) {
            return this.semesterCourseMenus(semester).map((courseMenu) => ({
                key: courseMenu.key,
                label: courseMenu.label,
            }))
        },
        selectedCourseFilterChips(semester) {
            return this.semesterCourseMenus(semester)
                .flatMap((courseMenu) => courseMenu.entries)
                .filter((entry) => this.isCourseMenuEntryFilterActive(entry))
                .map((entry) => ({
                    key: `selected-${entry.key}`,
                    label: entry.scheduleLabel ? `${entry.label} · ${entry.scheduleLabel}` : entry.label,
                    hasOverlap: this.courseMenuEntryHasOverlap(entry, semester),
                    entry,
                }))
        },
        selectedCourseMenuEntries(semester) {
            if (this.selectedCourseMenuEntriesBySemester) {
                return this.selectedCourseMenuEntriesBySemester[Number(semester)] || []
            }

            return this.buildSelectedCourseMenuEntries(semester)
        },
        buildSelectedCourseMenuEntries(semester) {
            return this.semesterCourseMenus(semester)
                .flatMap((courseMenu) => courseMenu.entries)
                .filter((entry) => this.isCourseMenuEntryFilterActive(entry))
        },
        courseGroupMenuLabel(courseGroup) {
            return (
                courseGroup?.display_label
                || courseGroup?.title
                || courseGroup?.course
                || courseGroup?.subject
                || 'Ohne Bezeichnung'
            ).toString()
        },
        courseMenuEntryScheduleLabel(entry) {
            const mergeGapMinutes = 15
            const timeLabelToMinutes = (value) => {
                const match = String(value || '').match(/^(\d{1,2}):(\d{2})$/u)
                if (!match) return null

                return Number(match[1]) * 60 + Number(match[2])
            }
            const schedulesByWeekday = (entry?.courseGroups || []).reduce((schedules, courseGroup) => {
                const weekday = this.weekdayForCourseGroup(courseGroup)
                const timeRange = this.courseGroupTimeRangeParts(courseGroup)
                if (!weekday.label && !timeRange.label) {
                    return schedules
                }

                const key = weekday.value ? `weekday-${weekday.value}` : `time-${timeRange.label}`
                if (!schedules.has(key)) {
                    schedules.set(key, {
                        weekdayOrder: weekday.value || 99,
                        weekdayLabel: weekday.label,
                        ranges: [],
                        fallbackLabels: new Set(),
                    })
                }

                const schedule = schedules.get(key)
                if (timeRange.from && timeRange.until) {
                    schedule.ranges.push({
                        from: timeRange.from,
                        until: timeRange.until,
                        fromMinutes: timeLabelToMinutes(timeRange.from),
                        untilMinutes: timeLabelToMinutes(timeRange.until),
                    })

                    return schedules
                }

                schedule.fallbackLabels.add([weekday.label, timeRange.label].filter(Boolean).join(' '))

                return schedules
            }, new Map())

            return [...schedulesByWeekday.values()]
                .sort((left, right) => left.weekdayOrder - right.weekdayOrder)
                .flatMap((schedule) => {
                    const mergedRanges = schedule.ranges
                        .sort((left, right) => (left.fromMinutes ?? 0) - (right.fromMinutes ?? 0))
                        .reduce((ranges, range) => {
                            const lastRange = ranges[ranges.length - 1]
                            const gapMinutes = lastRange && lastRange.untilMinutes !== null && range.fromMinutes !== null
                                ? range.fromMinutes - lastRange.untilMinutes
                                : null

                            if (lastRange && gapMinutes !== null && gapMinutes <= mergeGapMinutes) {
                                if ((range.untilMinutes ?? 0) > (lastRange.untilMinutes ?? 0)) {
                                    lastRange.until = range.until
                                    lastRange.untilMinutes = range.untilMinutes
                                }

                                return ranges
                            }

                            ranges.push({ ...range })

                            return ranges
                        }, [])
                        .map((range) => [schedule.weekdayLabel, `${range.from} - ${range.until}`].filter(Boolean).join(' '))

                    return [
                        ...mergedRanges,
                        ...schedule.fallbackLabels,
                    ]
                })
                .filter(Boolean)
                .join(', ')
        },
        courseMenuEntryScheduleLabelWithFrequency(entry) {
            const scheduleLabel = this.courseMenuEntryScheduleLabel(entry)
            const frequencyLabel = this.courseMenuEntryFrequencyLabel(entry)

            return [
                scheduleLabel,
                frequencyLabel ? `(${frequencyLabel})` : '',
            ]
                .filter(Boolean)
                .join(' ')
        },
        courseMenuEntryFrequencyLabel(entry) {
            const courseGroups = Array.isArray(entry?.courseGroups) ? entry.courseGroups : []
            const recurrenceLabels = [...new Set(courseGroups
                .map((courseGroup) => Number(courseGroup?.recurrence_interval))
                .filter((interval) => [1, 2, 3, 4].includes(interval))
                .sort((left, right) => left - right)
                .map((interval) => `${interval}w`))]

            const singleDateCount = this.courseMenuEntrySingleDateCount(courseGroups)
            const labels = singleDateCount > 0
                ? [...recurrenceLabels, `${singleDateCount}x`]
                : recurrenceLabels

            return labels.join(', ')
        },
        courseMenuEntrySingleDateCount(courseGroups) {
            const singleDateGroups = courseGroups.filter((courseGroup) => (
                ![1, 2, 3, 4].includes(Number(courseGroup?.recurrence_interval))
            ))

            const dates = singleDateGroups
                .flatMap((courseGroup) => (Array.isArray(courseGroup?.dates) ? courseGroup.dates : []))
                .filter(Boolean)

            if (dates.length) {
                return new Set(dates).size
            }

            return singleDateGroups
                .map((courseGroup) => Number(courseGroup?.dates_count))
                .filter((datesCount) => Number.isFinite(datesCount) && datesCount > 0)
                .reduce((total, datesCount) => total + datesCount, 0)
        },
        recurrenceWeekOptions(semester) {
            if (this.recurrenceWeekOptionsBySemester) {
                return this.recurrenceWeekOptionsBySemester[Number(semester)] || []
            }

            return this.buildRecurrenceWeekOptions(semester)
        },
        buildRecurrenceWeekOptions(semester) {
            const maxInterval = this.selectedCourseMenuEntries(semester)
                .flatMap((entry) => entry.courseGroups || [])
                .map((courseGroup) => Number(courseGroup?.recurrence_interval))
                .filter((interval) => [2, 3, 4].includes(interval))
                .reduce((highestInterval, interval) => Math.max(highestInterval, interval), 1)

            return Array.from({ length: maxInterval }, (item, index) => ({
                value: index + 1,
                label: `Woche ${index + 1}`,
            }))
        },
        timetableSelectorOptions(semester) {
            return [
                this.allDatesOption(semester),
                ...this.recurrenceWeekOptions(semester),
                ...this.extraDatesOptions(semester),
            ]
        },
        allDatesOption(semester) {
            return {
                key: `semester-${semester}-all-dates`,
                type: 'all_dates',
                value: ALL_DATES_OPTION_VALUE,
                label: 'Alle Termine',
                showLabel: false,
            }
        },
        selectedRecurrenceWeek(semester) {
            const selectedWeek = Number(this.selectedRecurrenceWeeks?.[Number(semester)])
            const maxWeek = this.recurrenceWeekOptions(semester).length

            if (!Number.isFinite(selectedWeek) || selectedWeek < 1 || selectedWeek > maxWeek) {
                return 1
            }

            return selectedWeek
        },
        selectedTimetableOptionValue(semester) {
            const selectedValue = this.selectedRecurrenceWeeks?.[Number(semester)]
            if (selectedValue === ALL_DATES_OPTION_VALUE) {
                return selectedValue
            }

            if (selectedValue === 'extra_dates' && this.extraDatesOptions(semester).length) {
                return selectedValue
            }

            if (Number.isFinite(Number(selectedValue))) {
                return this.selectedRecurrenceWeek(semester)
            }

            return ALL_DATES_OPTION_VALUE
        },
        setSelectedTimetableOptionValue(semester, value) {
            if ([ALL_DATES_OPTION_VALUE, 'extra_dates'].includes(value)) {
                this.selectedRecurrenceWeeks = {
                    ...this.selectedRecurrenceWeeks,
                    [Number(semester)]: value,
                }
                this.saveLastTimetableState?.()

                return
            }

            this.setSelectedRecurrenceWeek(semester, value)
        },
        setSelectedRecurrenceWeek(semester, week) {
            const selectedWeek = Number(week)
            if (!Number.isFinite(selectedWeek)) {
                return
            }

            this.selectedRecurrenceWeeks = {
                ...this.selectedRecurrenceWeeks,
                [Number(semester)]: selectedWeek,
            }
            this.saveLastTimetableState?.()
        },
        areRecurrenceWeeksExpanded(semester) {
            return Boolean(this.expandedRecurrenceWeeks?.[Number(semester)])
        },
        toggleRecurrenceWeeks(semester) {
            const semesterNumber = Number(semester)

            this.expandedRecurrenceWeeks = {
                ...this.expandedRecurrenceWeeks,
                [semesterNumber]: !this.areRecurrenceWeeksExpanded(semesterNumber),
            }
            this.saveLastTimetableState?.()
        },
        visibleTimetableWeeks(semester) {
            const options = this.recurrenceWeekOptions(semester)

            if (options.length <= 1) {
                return [{
                    key: `semester-${semester}-single`,
                    type: 'single',
                    value: null,
                    label: '',
                    showLabel: false,
                }]
            }

            if (this.areRecurrenceWeeksExpanded(semester)) {
                return [
                    ...options.map((option) => ({
                        ...option,
                        key: `semester-${semester}-week-${option.value}`,
                        type: 'recurrence',
                        showLabel: true,
                    })),
                    ...this.extraDatesOptions(semester),
                ]
            }

            const selectedWeek = this.selectedRecurrenceWeek(semester)
            const selectedOption = options.find((option) => option.value === selectedWeek) || options[0]
            const selectedTimetableOption = this.selectedTimetableOptionValue(semester)

            if (selectedTimetableOption === ALL_DATES_OPTION_VALUE) {
                return [this.allDatesOption(semester)]
            }

            if (selectedTimetableOption === 'extra_dates') {
                return this.extraDatesOptions(semester)
            }

            return [{
                ...selectedOption,
                key: `semester-${semester}-week-${selectedOption.value}`,
                type: 'recurrence',
                showLabel: false,
            }]
        },
        extraDatesOptions(semester) {
            if (this.extraDatesOptionsBySemester) {
                return this.extraDatesOptionsBySemester[Number(semester)] || []
            }

            return this.buildExtraDatesOptions(semester)
        },
        buildExtraDatesOptions(semester) {
            const hasExtraDates = this.selectedCourseMenuEntries(semester)
                .flatMap((entry) => entry.courseGroups || [])
                .filter((courseGroup) => this.courseGroupHasExtraDateWeek(courseGroup))
                .length > 0

            if (!hasExtraDates) {
                return []
            }

            return [{
                key: `semester-${semester}-extra-dates`,
                type: 'extra_dates',
                value: 'extra_dates',
                label: 'Zusatzwochen',
                showLabel: true,
            }]
        },
        shouldShowExtraDatesNotice(semester, timetableWeek) {
            return ['all_dates', 'recurrence'].includes(timetableWeek?.type)
                && !this.areRecurrenceWeeksExpanded(semester)
                && this.extraDatesOptions(semester).length > 0
        },
        showExtraDatesInSelectedWeek(semester) {
            return Boolean(this.showExtraDatesInSelectedWeeks?.[Number(semester)])
        },
        setShowExtraDatesInSelectedWeek(semester, value) {
            this.showExtraDatesInSelectedWeeks = {
                ...this.showExtraDatesInSelectedWeeks,
                [Number(semester)]: Boolean(value),
            }
            this.saveLastTimetableState?.()
        },
        shouldIncludeExtraDatesInRegularWeek(semester) {
            return this.selectedTimetableOptionValue(semester) !== 'extra_dates'
                && !this.areRecurrenceWeeksExpanded(semester)
                && this.extraDatesOptions(semester).length > 0
                && this.showExtraDatesInSelectedWeek(semester)
        },
        courseGroupHasRegularRecurrence(courseGroup) {
            return [1, 2, 3, 4].includes(Number(courseGroup?.recurrence_interval))
        },
        courseGroupHasExtraDateWeek(courseGroup) {
            return !this.courseGroupHasRegularRecurrence(courseGroup)
        },
        courseGroupMatchesSelectedRecurrenceWeek(courseGroup, semester, recurrenceWeek = null) {
            if (recurrenceWeek?.type === 'extra_dates') {
                return this.courseGroupHasExtraDateWeek(courseGroup)
            }

            const selectedTimetableOption = this.selectedTimetableOptionValue(semester)
            if (recurrenceWeek?.type === 'all_dates' || (!recurrenceWeek && selectedTimetableOption === ALL_DATES_OPTION_VALUE)) {
                if (this.courseGroupHasExtraDateWeek(courseGroup)) {
                    return this.shouldIncludeExtraDatesInRegularWeek(semester)
                }

                return true
            }

            const options = this.recurrenceWeekOptions(semester)
            if (options.length <= 1) {
                return true
            }

            const interval = Number(courseGroup?.recurrence_interval)
            if (this.courseGroupHasRegularRecurrence(courseGroup) && interval === 1) {
                if (recurrenceWeek?.type === 'recurrence' || Number.isFinite(Number(recurrenceWeek))) {
                    return true
                }

                return selectedTimetableOption !== 'extra_dates'
            }

            if (!this.courseGroupHasRegularRecurrence(courseGroup) || ![2, 3, 4].includes(interval)) {
                if (recurrenceWeek?.type === 'recurrence' || Number.isFinite(Number(recurrenceWeek))) {
                    return this.shouldIncludeExtraDatesInRegularWeek(semester)
                }

                return selectedTimetableOption === 'extra_dates'
            }

            const targetWeek = recurrenceWeek?.type === 'recurrence'
                ? Number(recurrenceWeek.value)
                : recurrenceWeek !== null && recurrenceWeek !== undefined && Number.isFinite(Number(recurrenceWeek))
                    ? Number(recurrenceWeek)
                    : this.selectedRecurrenceWeek(semester)

            const startWeek = this.courseGroupRecurrenceWeek(courseGroup, semester)
            const weekOffset = ((targetWeek - startWeek) % interval + interval) % interval

            return weekOffset === 0
        },
        courseGroupRecurrenceWeek(courseGroup, semester) {
            const interval = Number(courseGroup?.recurrence_interval)
            if (![2, 3, 4].includes(interval)) {
                return 1
            }

            const firstDate = this.normalizeDate(courseGroup?.first_date || courseGroup?.dates?.[0])
            const semesterStart = this.semesterStartDate(semester)
            if (!firstDate || !semesterStart) {
                return 1
            }

            const dayDifference = Math.floor((firstDate.getTime() - semesterStart.getTime()) / 86400000)
            const weekOffset = Math.floor(dayDifference / 7)
            const normalizedOffset = ((weekOffset % interval) + interval) % interval

            return normalizedOffset + 1
        },
        semesterStartDate(semester) {
            const configuredStart = Number(semester) === 1
                ? this.normalizeDate(this.selectedSchoolyear?.from)
                : this.normalizeDate(this.selectedSchoolyear?.sem_2_start)

            if (configuredStart) {
                return configuredStart
            }

            return this.configuredCourseGroups
                .filter((courseGroup) => Number(courseGroup?.semester) === Number(semester))
                .flatMap((courseGroup) => [courseGroup?.first_date, ...(courseGroup?.dates || [])])
                .map((date) => this.normalizeDate(date))
                .filter(Boolean)
                .sort((left, right) => left.getTime() - right.getTime())[0] || null
        },
        courseGroupScheduleLabel(courseGroup) {
            const weekdayLabel = this.weekdayForCourseGroup(courseGroup).label
            const timeRangeLabel = this.courseGroupTimeRangeLabel(courseGroup)

            return [weekdayLabel, timeRangeLabel]
                .filter(Boolean)
                .join(' ')
        },
        courseMenuEntryHasOverlap(entry, semester) {
            return this.selectedCourseMenuEntries(semester)
                .some((selectedEntry) => (
                    selectedEntry.key !== entry?.key
                    && this.courseMenuEntriesOverlap(entry, selectedEntry)
                ))
        },
        courseGroupHasOverlap(courseGroup) {
            const semester = Number(courseGroup?.semester)
            if (!Number.isFinite(semester)) {
                return false
            }

            const containingEntry = this.selectedCourseMenuEntries(semester)
                .find((entry) => this.courseMenuEntryKeys(entry).includes(courseGroup?.key))

            if (!containingEntry) {
                return false
            }

            return this.selectedCourseMenuEntries(semester)
                .filter((selectedEntry) => selectedEntry.key !== containingEntry.key)
                .some((selectedEntry) => (
                    (selectedEntry?.courseGroups || []).some((selectedCourseGroup) => (
                        this.courseGroupsOverlap(courseGroup, selectedCourseGroup)
                    ))
                ))
        },
        courseGroupHasRelatedOverlap(courseGroup) {
            if (this.courseGroupHasOverlap(courseGroup)) {
                return false
            }

            const semester = Number(courseGroup?.semester)
            if (!Number.isFinite(semester)) {
                return false
            }

            const containingEntry = this.selectedCourseMenuEntries(semester)
                .find((entry) => this.courseMenuEntryKeys(entry).includes(courseGroup?.key))

            return Boolean(containingEntry && this.courseMenuEntryHasOverlap(containingEntry, semester))
        },
        cellHasOverlap(semester, weekday, hour, recurrenceWeek = null) {
            return this.courseGroupsForCell(semester, weekday, hour, recurrenceWeek)
                .some((courseGroup) => this.courseGroupHasOverlap(courseGroup))
        },
        cellHasRelatedOverlap(semester, weekday, hour, recurrenceWeek = null) {
            return this.courseGroupsForCell(semester, weekday, hour, recurrenceWeek)
                .some((courseGroup) => this.courseGroupHasRelatedOverlap(courseGroup))
        },
        courseGroupRelatedOverlapMarker(courseGroup) {
            return this.courseGroupHasRelatedOverlap(courseGroup) ? '⚠ Mitbetroffen' : ''
        },
        courseMenuEntriesOverlap(leftEntry, rightEntry) {
            return (leftEntry?.courseGroups || []).some((leftCourseGroup) => (
                (rightEntry?.courseGroups || []).some((rightCourseGroup) => (
                    this.courseGroupsOverlap(leftCourseGroup, rightCourseGroup)
                ))
            ))
        },
        courseGroupsOverlap(leftCourseGroup, rightCourseGroup) {
            if (leftCourseGroup?.key && leftCourseGroup.key === rightCourseGroup?.key) {
                return false
            }

            if (
                Number(leftCourseGroup?.semester) !== Number(rightCourseGroup?.semester)
                || Number(leftCourseGroup?.weekday) !== Number(rightCourseGroup?.weekday)
            ) {
                return false
            }

            const leftTimeRange = this.courseGroupTimeRangeParts(leftCourseGroup)
            const rightTimeRange = this.courseGroupTimeRangeParts(rightCourseGroup)
            if (leftTimeRange.from && leftTimeRange.until && rightTimeRange.from && rightTimeRange.until) {
                return leftTimeRange.from < rightTimeRange.until && rightTimeRange.from < leftTimeRange.until
            }

            return Number(leftCourseGroup?.hour) === Number(rightCourseGroup?.hour)
        },
        weekdayForCourseGroup(courseGroup) {
            const weekday = (this.weekdays || [])
                .find((configuredWeekday) => Number(configuredWeekday.value) === Number(courseGroup?.weekday))

            return {
                label: weekday?.label || '',
                value: Number(weekday?.value) || null,
            }
        },
        mainCourseLabel(courseGroup) {
            const source = this.courseGroupCourseSource(courseGroup)
            if (!source) return ''

            const firstSegment = source.split(/\s+-\s+/u)[0]?.trim() || ''
            if (!firstSegment || this.isTimeOnlyValue(firstSegment)) return ''

            const match = firstSegment.match(/^([^\d\s-]+)\d*/u)

            return (match?.[1] || firstSegment).trim()
        },
        courseGroupCourseSource(courseGroup) {
            return [
                courseGroup?.course,
                courseGroup?.title,
                courseGroup?.display_label,
                courseGroup?.subject,
            ]
                .map((value) => (value || '').toString().trim())
                .find((value) => value !== '' && !this.isTimeOnlyValue(value)) || ''
        },
        isTimeOnlyValue(value) {
            return /^\d{1,2}:\d{2}$/.test((value || '').toString().trim())
        },
        courseGroupTimeRangeLabel(courseGroup) {
            return this.courseGroupTimeRangeParts(courseGroup).label
        },
        courseGroupBlockLabel(courseGroup) {
            return this.courseGroupDateRangeLabel(courseGroup) || courseGroup?.block_label || 'Block'
        },
        courseGroupDateRangeLabel(courseGroup) {
            const dates = Array.isArray(courseGroup?.dates)
                ? [...courseGroup.dates].filter(Boolean).sort()
                : []
            const from = courseGroup?.first_date || dates[0] || null
            const until = courseGroup?.last_date || dates[dates.length - 1] || from
            const fromLabel = this.formatCompactDateValue(from)
            const untilLabel = this.formatCompactDateValue(until)

            if (fromLabel && untilLabel && fromLabel !== untilLabel) {
                return `${fromLabel} - ${untilLabel}`
            }

            return fromLabel || untilLabel || ''
        },
        courseGroupTimeRangeParts(courseGroup) {
            const importedTimeRange = this.importedCourseGroupTimeRange(courseGroup)
            const schoolHourTimeRange = this.schoolHourTimeRange(courseGroup)
            const from = importedTimeRange.from || schoolHourTimeRange.from
            const until = importedTimeRange.until || schoolHourTimeRange.until

            if (from && until) {
                return {
                    from,
                    until,
                    label: `${from} - ${until}`,
                }
            }

            return {
                from,
                until,
                label: from || until || '',
            }
        },
        importedCourseGroupTimeRange(courseGroup) {
            const from = this.isTimeOnlyValue(courseGroup?.subject)
                ? this.formatTimeValue(courseGroup.subject)
                : ''
            const until = this.isTimeOnlyValue(courseGroup?.teacher)
                ? this.formatTimeValue(courseGroup.teacher)
                : ''

            return { from, until }
        },
        schoolHourTimeRange(courseGroup) {
            const schoolHour = this.configuredSchoolHours.find((configuredSchoolHour) => (
                Number(configuredSchoolHour?.hour) === Number(courseGroup?.hour)
            ))

            return {
                from: this.formatTimeValue(schoolHour?.from),
                until: this.formatTimeValue(schoolHour?.until),
            }
        },
        toggleCourseMenuEntryFilter(entry) {
            const courseGroupKeys = this.courseMenuEntryKeys(entry)
            if (!courseGroupKeys.length) {
                return
            }

            if (this.isCourseMenuEntryFilterActive(entry)) {
                this.activeCourseGroupFilterKeys = this.activeCourseGroupFilterKeys
                    .filter((activeKey) => !courseGroupKeys.includes(activeKey))
                this.persistTimetableState?.()

                return
            }

            this.activeCourseGroupFilterKeys = [
                ...new Set([
                    ...this.activeCourseGroupFilterKeys,
                    ...courseGroupKeys,
                ]),
            ]

            if ((entry?.courseGroups || []).some((courseGroup) => Number(courseGroup?.weekday) === 6)) {
                this.showSaturday = true
            }
            this.persistTimetableState?.()
        },
        isCourseMenuEntryFilterActive(entry) {
            const courseGroupKeys = this.courseMenuEntryKeys(entry)
            const activeCourseGroupFilterKeySet = this.activeCourseGroupFilterKeySet instanceof Set
                ? this.activeCourseGroupFilterKeySet
                : new Set(this.activeCourseGroupFilterKeys || [])

            return courseGroupKeys.length > 0
                && courseGroupKeys.every((courseGroupKey) => activeCourseGroupFilterKeySet.has(courseGroupKey))
        },
        courseMenuEntryKeys(entry) {
            if (Array.isArray(entry?.courseGroupKeys)) {
                return entry.courseGroupKeys.filter(Boolean)
            }

            return entry?.courseGroup?.key ? [entry.courseGroup.key] : []
        },
        toggleCourseGroupFilter(courseGroup) {
            const courseGroupKey = courseGroup?.key || null
            if (!courseGroupKey) {
                return
            }

            if (this.isCourseGroupFilterActive(courseGroup)) {
                this.activeCourseGroupFilterKeys = this.activeCourseGroupFilterKeys
                    .filter((activeKey) => activeKey !== courseGroupKey)
                this.persistTimetableState?.()

                return
            }

            this.activeCourseGroupFilterKeys = [
                ...this.activeCourseGroupFilterKeys,
                courseGroupKey,
            ]
            if (Number(courseGroup?.weekday) === 6) {
                this.showSaturday = true
            }
            this.persistTimetableState?.()
        },
        isCourseGroupFilterActive(courseGroup) {
            return this.activeCourseGroupFilterKeys.includes(courseGroup?.key)
        },
        openCourseGroupDialog(courseGroup) {
            this.selectedCourseGroup = courseGroup
            this.courseGroupDialog = true
        },
        closeCourseGroupDialog() {
            this.courseGroupDialog = false
            this.selectedCourseGroup = null
        },
        timetableHoursForSemester(semester, recurrenceWeek = null) {
            return this.timetableHours.filter((hour) => (
                this.displayedWeekdays.some((weekday) => (
                    this.courseGroupsForCell(semester, weekday.value, hour.hour, recurrenceWeek).length > 0
                ))
            ))
        },
        courseCellKey(semester, weekday, hour) {
            return `${Number(semester)}-${Number(weekday)}-${Number(hour)}`
        },
        semesterDateRange(semester) {
            const from = this.normalizeDate(this.selectedSchoolyear?.from)
            const until = this.normalizeDate(this.selectedSchoolyear?.until)
            const semester2Start = this.normalizeDate(this.selectedSchoolyear?.sem_2_start)

            if (semester === 1) {
                const semester1Until = semester2Start ? this.previousDay(semester2Start) : null

                return this.formatDateRange(from, semester1Until)
            }

            return this.formatDateRange(semester2Start, until)
        },
        formatDateRange(from, until) {
            const fromLabel = this.formatDate(from)
            const untilLabel = this.formatDate(until)

            if (fromLabel && untilLabel) {
                return `${fromLabel} - ${untilLabel}`
            }

            return 'Datum nicht vollständig gesetzt'
        },
        normalizeDate(value) {
            if (!value) return null

            const date = parseLocalDate(value)
            if (Number.isNaN(date.getTime())) return null

            date.setHours(0, 0, 0, 0)

            return date
        },
        previousDay(date) {
            const previous = new Date(date)
            previous.setDate(previous.getDate() - 1)

            return previous
        },
        formatDate(date) {
            if (!date) return ''

            return date.toLocaleDateString('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        formatDateValue(value) {
            const date = this.normalizeDate(value)
            if (!date) return value || ''

            return this.formatDate(date)
        },
        formatCompactDateValue(value) {
            const date = this.normalizeDate(value)
            if (!date) return value || ''

            const day = date.getDate().toString().padStart(2, '0')
            const month = (date.getMonth() + 1).toString().padStart(2, '0')

            return `${day}.${month}.`
        },
    },
}
</script>

<style scoped>
.timetable-card-body {
    position: relative;
}

.timetable-update-blocker {
    position: fixed;
    inset: 0;
    z-index: 90;
    background: rgba(255, 255, 255, 0.28);
    cursor: progress;
}

.timetable-update-indicator {
    position: fixed;
    top: 50%;
    left: 50%;
    z-index: 100;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    min-width: 220px;
    padding: 18px 22px;
    transform: translate(-50%, -50%);
    border: 1px solid rgba(25, 118, 210, 0.22);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.18);
    color: #0d47a1;
    font-size: 0.82rem;
    font-weight: 700;
    text-align: center;
}

.timetable-update-indicator__dots {
    padding: 0;
}

.timetable-update-indicator__dots :deep(.loading-animation) {
    padding: 0;
}

.timetable-update-indicator__dots :deep(.loading-dots) {
    gap: 6px;
}

.timetable-update-indicator__dots :deep(.dot) {
    width: 8px;
    height: 8px;
}

.semester-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 18px;
}

.course-choice-panel {
    display: grid;
    gap: 10px;
    margin-bottom: 16px;
    padding: 10px 12px;
    border: 1px solid rgba(57, 73, 171, 0.16);
    border-radius: 8px;
    background: #f8fafc;
}

.overview-selection {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}

.overview-selected-cards {
    display: grid;
    grid-template-columns: repeat(5, minmax(128px, 1fr));
    gap: 8px;
    flex: 1 1 auto;
    min-width: 0;
}

.overview-selected-card {
    min-width: 0;
    padding: 9px 11px;
    border: 1px solid rgba(var(--v-theme-primary), 0.26);
    border-radius: 6px;
    background: #f8fafc;
}

.overview-selected-card__label {
    color: #172554;
    font-size: 0.7rem;
    font-weight: 850;
}

.overview-selected-card__value {
    margin-top: 4px;
    color: #020617;
    font-size: 0.78rem;
    font-weight: 800;
    line-height: 1.15;
    overflow-wrap: anywhere;
}

.overview-selection-dialog-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.course-choice-panel__header,
.course-choice-panel__title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.course-choice-panel__header {
    justify-content: space-between;
}

.course-choice-panel__actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.course-choice-restriction-switch {
    flex: 0 0 auto;
}

.course-choice-panel__title {
    color: #172554;
    font-size: 0.86rem;
    font-weight: 800;
}

.course-choice-panel__semesters {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 10px;
}

.course-choice-semester {
    display: grid;
    gap: 6px;
    min-width: 0;
}

.course-choice-semester__label {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 800;
}

.course-choice-semester__dates {
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-weight: 700;
}

.course-item-chips,
.course-menu-dialog-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.course-menu-dialog-items .course-item-chips {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    align-items: start;
}

.course-menu-dialog-items .course-item-chip {
    justify-content: flex-start;
    width: 100%;
}

.course-item-chip,
.course-menu-dialog-chip {
    font-weight: 650;
}

.course-item-chip__schedule {
    margin-left: 6px;
    color: rgba(var(--v-theme-on-surface), 0.68);
    font-size: 0.72rem;
    font-weight: 700;
}

.course-menu-dialog-chips {
    max-height: 52vh;
    overflow-y: auto;
}

.course-menu-dialog-items {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid rgba(57, 73, 171, 0.16);
}

.semester-timetable {
    overflow: hidden;
}

.semester-timetable__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 0 0 8px;
}

.semester-timetable__dates {
    font-size: 0.75rem;
    color: rgba(0, 0, 0, 0.6);
    white-space: nowrap;
}

.selected-course-filter-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding-top: 2px;
}

.selected-course-filter-chip {
    font-weight: 650;
}

.overview-student-selection {
    margin-bottom: 14px;
}

.overview-student-selection .transferred-student-context {
    margin-bottom: 0;
}

.overview-student-inline-actions {
    display: flex;
    gap: 6px;
    align-items: center;
    flex: 0 0 auto;
}

.overview-student-dialog__meta {
    margin-bottom: 10px;
    color: rgba(var(--v-theme-on-surface), 0.68);
    font-size: 0.78rem;
    font-weight: 700;
}

.overview-student-search-results {
    display: grid;
    gap: 6px;
    margin-top: 10px;
    max-height: 260px;
    overflow-y: auto;
}

.overview-student-search-results__item {
    justify-content: flex-start;
    min-height: 32px;
}

.overview-student-search-results__empty {
    padding: 8px 2px;
    color: rgba(var(--v-theme-on-surface), 0.58);
    font-size: 0.78rem;
}

.transferred-student-context {
    display: grid;
    gap: 10px;
    margin-bottom: 14px;
    padding: 10px;
    border: 1px solid rgba(14, 165, 233, 0.22);
    border-radius: 8px;
    background: #f0f9ff;
}

.transferred-student-context--collapsed {
    gap: 0;
}

.transferred-student-context__header,
.transferred-student-context__title,
.transferred-student-context__header-actions,
.transferred-student-course-section,
.transferred-student-course-section__title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.transferred-student-context__header {
    justify-content: space-between;
    flex-wrap: wrap;
    cursor: pointer;
    outline: none;
}

.transferred-student-context__header:focus-visible {
    box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.22);
    border-radius: 6px;
}

.transferred-student-context__title {
    min-width: 0;
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 800;
}

.transferred-student-context__header-actions {
    flex: 0 0 auto;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.transferred-student-context__sections {
    display: grid;
    gap: 7px;
    padding-top: 2px;
}

.transferred-student-course-section {
    align-items: flex-start;
    min-width: 0;
    padding-top: 7px;
    border-top: 1px solid rgba(14, 165, 233, 0.18);
}

.transferred-student-course-section__title {
    flex: 0 0 164px;
    justify-content: flex-start;
    color: rgba(var(--v-theme-on-surface), 0.74);
    font-size: 0.7rem;
    font-weight: 800;
}

.transferred-student-course-section__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    min-width: 0;
}

.transferred-student-course-chip {
    max-width: 100%;
    font-weight: 750;
}

.transferred-student-course-chip__meta {
    margin-left: 5px;
    opacity: 0.78;
}

.transferred-student-course-section__empty {
    color: rgba(var(--v-theme-on-surface), 0.54);
    font-size: 0.72rem;
    font-weight: 650;
}

.recurrence-week-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 0 0 8px;
}

.recurrence-week-selector__btn {
    min-width: 76px;
}

.recurrence-week-selector__action-btn {
    background: #ffffff;
}

.timetable-week + .timetable-week {
    margin-top: 12px;
}

.timetable-week__title {
    padding: 6px 0;
    font-size: 0.78rem;
    font-weight: 700;
    color: rgba(0, 0, 0, 0.72);
}

.extra-dates-notice {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px 14px;
    padding: 8px 12px;
    border-top: 1px solid rgba(0, 0, 0, 0.08);
    background: #ffffff;
}

.extra-dates-notice__text {
    font-size: 0.78rem;
    font-weight: 700;
    color: rgba(0, 0, 0, 0.68);
}

.timetable-saturday-switch {
    flex: 0 0 auto;
}

.hidden-saturday-warning {
    min-width: 44px;
    height: 44px;
    font-size: 1.7rem;
    font-weight: 900;
    line-height: 1;
}

.timetable-hour-num {
    font-weight: 750;
    font-size: 0.76rem;
}

.timetable-hour-time {
    margin-top: 1px;
    font-size: 0.66rem;
    font-weight: 400;
    line-height: 1.12;
    opacity: 0.78;
}

.timetable-generated-grid {
    display: grid;
    grid-template-columns: 88px repeat(var(--overview-timetable-weekdays, 5), minmax(72px, 1fr));
    grid-template-rows: 34px;
    grid-auto-rows: minmax(58px, auto);
    gap: 2px;
    overflow-x: auto;
}

.timetable-generated-cell {
    position: relative;
    min-height: 0;
    padding: 5px;
    border-radius: 5px;
    background: #eef2f7;
    color: #0f172a;
    font-size: 0.76rem;
    line-height: 1.15;
    text-align: center;
    overflow: hidden;
}

.timetable-generated-cell--header,
.timetable-generated-cell--time {
    background: #dbeafe;
    color: #1e3a8a;
    font-weight: 750;
}

.timetable-generated-cell--filled {
    background: #bbf7d0;
    color: #052e16;
}

.timetable-generated-cell--conflict {
    background: #fecaca;
    color: #7f1d1d;
}

.timetable-generated-cell--related-overlap {
    background: #fed7aa;
    color: #7c2d12;
}

.timetable-generated-cell--empty {
    grid-column: 1 / -1;
    color: #475569;
    font-weight: 650;
}

.timetable-generated-cell__content {
    display: grid;
    gap: 2px;
    align-content: center;
    min-height: 48px;
    cursor: pointer;
    outline: none;
    overflow: hidden;
}

.timetable-generated-cell__content + .timetable-generated-cell__content {
    margin-top: 6px;
    padding-top: 5px;
    border-top: 1px solid rgba(15, 23, 42, 0.14);
}

.timetable-generated-cell__content:hover .timetable-generated-cell__code,
.timetable-generated-cell__content:focus-visible .timetable-generated-cell__code {
    text-decoration: underline;
}

.timetable-generated-cell__code {
    display: inline-flex;
    justify-content: center;
    align-items: flex-start;
    gap: 2px;
    font-weight: 750;
    overflow-wrap: anywhere;
}

.timetable-generated-cell__details {
    margin-top: 2px;
    font-size: 0.66rem;
    font-weight: 400;
    line-height: 1.12;
    opacity: 0.78;
    overflow-wrap: anywhere;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.timetable-generated-cell__details--warning {
    color: #7c2d12;
    font-weight: 750;
    opacity: 1;
}

.course-date-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.course-date-chip {
    gap: 6px;
}

.course-date-chip__separator {
    margin: 0 6px;
    opacity: 0.65;
}

.course-date-chip__time {
    font-weight: 700;
}

@media (max-width: 900px) {
    .overview-selection {
        align-items: stretch;
    }

    .overview-selected-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .overview-selection {
        flex-direction: column;
    }

    .overview-selected-cards,
    .overview-selection-dialog-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .transferred-student-course-section {
        display: grid;
        gap: 6px;
    }

    .transferred-student-course-section__title {
        flex: initial;
    }
}
</style>
