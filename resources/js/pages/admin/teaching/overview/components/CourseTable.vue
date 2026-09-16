<template>
    <ItsGridBox
        v-if="selected_course"
        variant="overview"
        color="primary"
        :icon="tableView === 'attendance' ? 'mdi-account-check' : 'mdi-table-large'"
        class="w-100"
        :disabled="action != ''">
        <template #title>
            <div>{{ tableView === 'attendance' ? 'Anwesenheiten' : 'Tabelle' }} - {{ selected_course.title }}</div>
        </template>
        <template #header-actions>
            <div class="d-flex align-center ga-1 flex-wrap justify-end">
                <v-chip size="small" color="primary" variant="tonal" prepend-icon="mdi-calendar-clock-outline">
                    {{ sortedCourseDates.length }} Termine
                </v-chip>
                <v-chip size="small" color="success" variant="tonal" prepend-icon="mdi-account-group">
                    {{ sortedSelectedStudents.length }} Schüler:innen
                </v-chip>
            </div>
        </template>

        <v-card
            class="mt-3 mb-3"
            color="primary"
            data-testid="course-table-semester-selection"
            variant="tonal">
            <v-card-text class="d-flex align-center flex-wrap ga-3 px-3 py-2">
                <span class="text-caption text-medium-emphasis font-weight-medium">Zeitraum:</span>
                <v-btn-toggle v-model="selectedSemester" mandatory density="compact" color="primary" variant="tonal">
                    <v-btn :value="1" size="small">1. Sem</v-btn>
                    <v-btn :value="2" size="small">2. Sem</v-btn>
                    <v-btn :value="3" size="small">Sem 1+2</v-btn>
                </v-btn-toggle>
                <v-btn
                    class="ml-auto"
                    color="error"
                    data-testid="course-table-overview-pdf-button"
                    density="comfortable"
                    prepend-icon="mdi-file-pdf-box"
                    size="small"
                    title="Kursübersicht als PDF öffnen"
                    variant="flat"
                    @click="openCourseOverviewPdf">
                    PDF
                </v-btn>
            </v-card-text>
        </v-card>

        <div
            v-if="entryTransfer"
            class="course-table-transfer-bar d-flex align-center flex-wrap ga-3 pa-3 mb-3"
            data-testid="course-table-transfer-bar"
            role="region"
            aria-label="Eintrag übertragen">
            <div class="flex-grow-1">
                <strong aria-live="polite">{{ entryTransfer.userIds.length }} ausgewählt</strong>
                <div class="text-body-2">{{ cellEntryTypeLabel(entryTransfer.entry) }} · {{ compactCourseDateTitle(entryTransfer.courseDate) }}</div>
                <div class="text-caption">Zellen anderer Schüler:innen in diesem Termin auswählen. Erneuter Klick hebt die Auswahl auf.</div>
                <div v-if="entryTransferError" role="alert" class="text-error">{{ entryTransferError }}</div>
            </div>
            <v-btn
                color="primary"
                variant="flat"
                :disabled="!entryTransfer.userIds.length || entryTransferSaving"
                :loading="entryTransferSaving"
                data-testid="course-table-transfer-confirm"
                @click="confirmEntryTransfer">Übertragen</v-btn>
            <v-btn
                variant="outlined"
                :disabled="entryTransferSaving"
                data-testid="course-table-transfer-cancel"
                @click="cancelEntryTransfer">Abbrechen</v-btn>
        </div>

        <v-card variant="outlined" class="course-table-card">
            <v-card-text class="pa-0">
                <div ref="courseTableScroll" class="course-table-scroll">
                    <table class="course-table" data-testid="course-table">
                        <thead>
                            <tr class="course-table-title-row">
                                <th class="course-table-student-col">
                                    <div class="course-table-header-label">
                                        {{ sortedSelectedStudents.length }} Schüler:innen
                                    </div>
                                </th>
                                <th
                                    v-for="courseDate in sortedCourseDates"
                                    :key="`date-head-${courseDate.id || courseDate.date}`"
                                    :data-course-date-key="courseDateScrollKey(courseDate)"
                                    class="course-table-date-col"
                                    :class="[
                                        { 'course-table-date-col--free': isFreeCourseDate(courseDate) },
                                        courseDateColumnMarkingClass(courseDate),
                                    ]">
                                    <div class="course-table-date-header">
                                        <div class="course-table-date-weekday">{{ courseDateWeekday(courseDate) }}</div>
                                        <div class="course-table-date-title">{{ courseDateDateLabel(courseDate) }}</div>
                                        <div v-if="courseDateHoursLabel(courseDate)" class="course-table-date-hours">
                                            {{ courseDateHoursLabel(courseDate) }}
                                        </div>
                                        <div
                                            v-if="tableView === 'attendance' && isAttendanceToggleable(courseDate)"
                                            class="course-table-date-attendance-actions">
                                            <v-btn
                                                class="course-table-date-attendance-action"
                                                color="success"
                                                density="compact"
                                                icon="mdi-check"
                                                size="x-small"
                                                :title="`${compactCourseDateTitle(courseDate)}: alle anwesend setzen`"
                                                variant="tonal"
                                                @click.stop="openBulkAttendanceDialog(courseDate, true)" />
                                            <v-btn
                                                class="course-table-date-attendance-action"
                                                color="error"
                                                density="compact"
                                                icon="mdi-close"
                                                size="x-small"
                                                :title="`${compactCourseDateTitle(courseDate)}: alle abwesend setzen`"
                                                variant="tonal"
                                                @click.stop="openBulkAttendanceDialog(courseDate, false)" />
                                            <v-btn
                                                class="course-table-date-attendance-action"
                                                density="compact"
                                                icon="mdi-eraser"
                                                size="x-small"
                                                :title="`${compactCourseDateTitle(courseDate)}: Anwesenheiten der Spalte zurücksetzen`"
                                                variant="tonal"
                                                @click.stop="openBulkAttendanceDialog(courseDate, null)" />
                                        </div>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="tableView === 'entries'" class="course-table-work-row">
                                <th scope="row" class="course-table-work-label">
                                    <div class="course-table-work-label-content">
                                        <v-icon color="primary" size="18">mdi-clipboard-text</v-icon>
                                        <span>Arbeiten</span>
                                    </div>
                                </th>
                                <td
                                    v-for="courseDate in sortedCourseDates"
                                    :key="`date-work-${courseDate.id || courseDate.date}`"
                                    class="course-table-work-cell"
                                    :class="[
                                        {
                                            'course-table-work-cell--free': isFreeCourseDate(courseDate),
                                            'course-table-work-cell--selected': isWorkDialogCellSelected(courseDate),
                                        },
                                        courseDateColumnMarkingClass(courseDate),
                                    ]"
                                    role="button"
                                    tabindex="0"
                                    :aria-label="`${compactCourseDateTitle(courseDate)}: Arbeiten öffnen`"
                                    @blur="clearHoveredCourseWorkTimelines"
                                    @click="openWorkDialog(courseDate)"
                                    @focus="activateCourseWorkTimelinesForDate(courseDate)"
                                    @keydown.enter.prevent="openWorkDialog(courseDate)"
                                    @keydown.space.prevent="openWorkDialog(courseDate)"
                                    @mouseenter="activateCourseWorkTimelinesForDate(courseDate)"
                                    @mouseleave="clearHoveredCourseWorkTimelines">
                                    <div
                                        class="course-table-work-list"
                                        :class="{ 'course-table-work-list--has-work': courseWorksForDate(courseDate).length > 0 }">
                                        <div
                                            v-if="courseWorkTimelinesForDate(courseDate).length"
                                            class="course-table-work-timelines"
                                            aria-hidden="true">
                                            <div
                                                v-for="timeline in courseWorkTimelinesForDate(courseDate)"
                                                :key="timeline.key"
                                                class="course-table-work-timeline"
                                                :style="{ gridRow: timeline.destinationIndex + 1 }"
                                                :class="{
                                                    'course-table-work-timeline--start': timeline.isStart,
                                                    'course-table-work-timeline--middle': timeline.isMiddle,
                                                    'course-table-work-timeline--arrow': timeline.isArrow,
                                                    'course-table-work-timeline--visible': isCourseWorkTimelineVisible(timeline),
                                                }">
                                                <span class="course-table-work-timeline-line" />
                                                <span
                                                    v-if="timeline.isStart"
                                                    class="course-table-work-timeline-card"
                                                    :class="{
                                                        'course-table-work-timeline-card--group': timeline.work.is_group_work,
                                                    }" />
                                                <span v-if="timeline.isArrow" class="course-table-work-timeline-arrow" />
                                            </div>
                                        </div>
                                        <v-icon
                                            class="course-table-work-empty-icon"
                                            color="primary"
                                            role="button"
                                            size="18"
                                            tabindex="0"
                                            :aria-label="`${compactCourseDateTitle(courseDate)}: neue Arbeit anlegen`"
                                            @click.stop="openNewWorkDialog(courseDate)"
                                            @keydown.enter.stop.prevent="openNewWorkDialog(courseDate)"
                                            @keydown.space.stop.prevent="openNewWorkDialog(courseDate)">
                                            mdi-plus-circle-outline
                                        </v-icon>
                                        <div
                                            v-for="work in courseWorksForDate(courseDate)"
                                            :key="work.key"
                                            class="course-table-work-summary"
                                            :class="{ 'course-table-work-summary--group': work.isGroupWork }"
                                            :title="work.title">
                                            <div class="course-table-work-summary-meta">
                                                <v-icon size="13">mdi-clipboard-text</v-icon>
                                                <span>{{ work.work.type || 'Arbeit' }}</span>
                                                <span
                                                    class="course-table-work-summary-count"
                                                    :aria-label="`${work.affectedStudentCount} betroffene Schüler:innen`">
                                                    <v-icon size="12">mdi-account-multiple</v-icon>
                                                    {{ work.affectedStudentCount }}
                                                </span>
                                            </div>
                                            <span class="course-table-work-summary-title">
                                                {{ work.work.title || work.label }}
                                            </span>
                                        </div>
                                    </div>
                                    <v-tooltip
                                        activator="parent"
                                        content-class="course-table-work-tooltip"
                                        location="top"
                                        :max-width="420"
                                        :open-delay="250">
                                        <div class="font-weight-bold mb-2">
                                            {{ compactCourseDateTitle(courseDate) }}
                                        </div>
                                        <div
                                            v-for="assignment in courseWorksForDate(courseDate)"
                                            :key="`tooltip-${assignment.key}`"
                                            class="course-table-work-tooltip-item">
                                            <div class="d-flex align-center flex-wrap ga-2">
                                                <v-chip
                                                    class="course-table-work-tooltip-type"
                                                    size="x-small"
                                                    color="grey-lighten-2"
                                                    variant="outlined">
                                                    {{ assignment.work.type || 'Arbeit' }}
                                                </v-chip>
                                                <strong>{{ assignment.work.title || 'Ohne Titel' }}</strong>
                                                <v-chip size="x-small" color="secondary" variant="outlined">
                                                    {{ assignment.scope }}
                                                </v-chip>
                                            </div>
                                            <div v-if="assignment.work.description" class="mt-1 text-body-2">
                                                {{ assignment.work.description }}
                                            </div>
                                        </div>
                                        <div v-if="!courseWorksForDate(courseDate).length" class="text-body-2">
                                            Keine Arbeit eingetragen. Klicken, um eine Arbeit anzulegen.
                                        </div>
                                    </v-tooltip>
                                </td>
                            </tr>
                            <tr
                                v-if="tableView === 'entries'"
                                class="course-table-curriculum-row">
                                <th scope="row" class="course-table-curriculum-label cursor-pointer"
                                    @click="$emit('manage-curriculum')">
                                    <button type="button" class="course-table-curriculum-label-content cursor-pointer"
                                        aria-label="Curriculum zuweisen oder Zuordnung entfernen"
                                        @click.stop="$emit('manage-curriculum')">
                                        <v-icon color="deep-purple" size="18">mdi-book-education-outline</v-icon>
                                        <span>
                                            <span>Curriculum</span>
                                            <template v-if="hasAssignedCurriculum">
                                                <br>
                                                <span>{{ assignedCurriculumTitle }}</span>
                                            </template>
                                        </span>
                                    </button>
                                </th>
                                <td
                                    v-for="courseDate in sortedCourseDates"
                                    :key="`date-curriculum-${courseDate.id || courseDate.date}`"
                                    class="course-table-curriculum-cell"
                                    :class="[
                                        { 'course-table-curriculum-cell--free': isFreeCourseDate(courseDate) },
                                        courseDateColumnMarkingClass(courseDate),
                                    ]"
                                    role="button"
                                    tabindex="0"
                                    :aria-label="`${compactCourseDateTitle(courseDate)}: Curriculum öffnen`"
                                    @click="openCurriculumDialog(courseDate)"
                                    @keydown.enter.prevent="openCurriculumDialog(courseDate)"
                                    @keydown.space.prevent="openCurriculumDialog(courseDate)">
                                    <div
                                        v-if="curriculumContentForCourseDate(courseDate).length"
                                        class="course-table-curriculum-content">
                                        <div
                                            v-for="(content, contentIndex) in displayedCurriculumContentForCourseDate(courseDate)"
                                            :key="content"
                                            class="course-table-curriculum-content-item">
                                            <span
                                                v-for="(segment, segmentIndex) in curriculumContentSegments(content)"
                                                :key="`${content}-${segmentIndex}`"
                                                class="course-table-curriculum-content-segment"
                                                :class="{
                                                    'course-table-curriculum-content-segment--separator': segment.endsWithSeparator,
                                                }">
                                                {{ segment.text }}
                                            </span>
                                            <span
                                                v-if="contentIndex === 2 && hasAdditionalCurriculumContent(courseDate)"
                                                class="course-table-curriculum-content-more"
                                                aria-label="Weitere Curriculum-Einträge">
                                                …
                                            </span>
                                        </div>
                                    </div>
                                    <span v-else class="course-table-curriculum-empty" aria-label="Kein Curriculum-Inhalt">
                                        &mdash;
                                    </span>
                                    <v-tooltip
                                        v-if="curriculumContentForCourseDate(courseDate).length"
                                        activator="parent"
                                        content-class="course-table-student-tooltip"
                                        location="top"
                                        :max-width="420"
                                        :open-delay="250">
                                        <div class="course-table-student-tooltip-name">
                                            {{ compactCourseDateTitle(courseDate) }}
                                        </div>
                                        <div class="course-table-curriculum-tooltip-title">Curriculum</div>
                                        <div
                                            v-for="content in curriculumContentForCourseDate(courseDate)"
                                            :key="`tooltip-${content}`"
                                            class="course-table-curriculum-tooltip-item">
                                            {{ content }}
                                        </div>
                                    </v-tooltip>
                                </td>
                            </tr>
                            <tr v-if="tableView === 'entries'" class="course-table-content-row">
                                <th scope="row" class="course-table-content-label">
                                    <div class="course-table-content-label-content">
                                        <v-icon color="primary" size="18">mdi-text-box-outline</v-icon>
                                        <span>Stoff</span>
                                    </div>
                                </th>
                                <td
                                    v-for="courseDate in sortedCourseDates"
                                    :key="`date-content-${courseDate.id || courseDate.date}`"
                                    class="course-table-content-cell"
                                    :class="[
                                        {
                                            'course-table-content-cell--free': isFreeCourseDate(courseDate),
                                            'course-table-content-cell--selected': isContentDialogCellSelected(courseDate),
                                        },
                                        courseDateColumnMarkingClass(courseDate),
                                    ]"
                                    role="button"
                                    tabindex="0"
                                    :aria-label="`${compactCourseDateTitle(courseDate)}: Inhalt öffnen`"
                                    @click="openContentDialog(courseDate)"
                                    @keydown.enter.prevent="openContentDialog(courseDate)"
                                    @keydown.space.prevent="openContentDialog(courseDate)">
                                    <div
                                        v-if="courseDateContentPreview(courseDate)"
                                        class="course-table-content-cell-preview">
                                        {{ courseDateContentPreview(courseDate) }}
                                    </div>
                                    <v-icon v-else class="course-table-content-cell-icon" color="primary" size="18">
                                        mdi-text-box-plus-outline
                                    </v-icon>
                                    <v-tooltip
                                        v-if="courseDateContentPreview(courseDate)"
                                        activator="parent"
                                        content-class="course-table-student-tooltip"
                                        location="right"
                                        :max-width="420"
                                        :open-delay="250">
                                        <div class="course-table-student-tooltip-name">
                                            {{ compactCourseDateTitle(courseDate) }}
                                        </div>
                                        <div class="course-table-student-tooltip-comment">
                                            <span>Inhalt</span>
                                            <div
                                                class="course-table-content-tooltip-html"
                                                v-html="courseDateFormattedContent(courseDate)" />
                                        </div>
                                    </v-tooltip>
                                </td>
                            </tr>
                            <tr
                                v-for="(student, studentIndex) in sortedSelectedStudents"
                                :key="`student-${student.id}`"
                                class="course-table-row">
                                <th scope="row" class="course-table-student-cell cursor-pointer" @click="$refs.studentNotes.open(student)">
                                    <div class="course-table-main-text">
                                        <span class="course-table-name-badges d-inline-flex align-center ga-1 flex-nowrap">
                                            <button type="button" class="course-table-student-name text-left cursor-pointer"
                                                :aria-label="`Informationen zu ${studentName(student)} öffnen`"
                                                @click.stop="$refs.studentNotes.open(student)">{{ studentLastName(student) }}</button>
                                            <CourseStudentIndicators :student="student" :course-id="selected_course.id" stars-only
                                                @select="$refs.studentNotes.open(student, $event)" />
                                        </span>
                                        <span
                                            v-if="studentHasPendingNotificationConfirmation(student)"
                                            class="course-table-student-confirmation-warning"
                                            aria-label="E-Mail-Bestätigung ausständig"
                                            title="Mindestens eine E-Mail-Bestätigung ist noch ausständig.">
                                            !
                                        </span>
                                        <span
                                            v-if="tableView === 'attendance' && studentPresencePercentage(student) !== null"
                                            class="course-table-presence-percentage">
                                            {{ studentPresencePercentage(student) }} %
                                        </span>
                                    </div>
                                    <div class="course-table-student-subline">
                                        <v-icon
                                            v-if="studentSexIcon(student)"
                                            size="14"
                                            :color="studentSexColor(student)"
                                            :title="studentSexTitle(student)">
                                            {{ studentSexIcon(student) }}
                                        </v-icon>
                                        <span v-if="studentFirstName(student)">{{ studentFirstName(student) }}</span>
                                        <span v-if="studentClassValue(student)" class="course-table-student-class">
                                            {{ studentClassValue(student) }}
                                        </span>
                                    </div>
                                    <CourseStudentIndicators :student="student" :course-id="selected_course.id"
                                        @select="$refs.studentNotes.open(student, $event)" />
                                    <v-tooltip
                                        activator="parent"
                                        content-class="course-table-student-tooltip"
                                        location="right"
                                        :max-width="420"
                                        :open-delay="250">
                                        <div class="course-table-student-tooltip-name">
                                            {{ studentName(student) }}
                                        </div>
                                        <div
                                            v-for="detail in studentTooltipDetails(student)"
                                            :key="detail.label"
                                            class="course-table-student-tooltip-detail">
                                            <span>{{ detail.label }}</span>
                                            <strong>{{ detail.value }}</strong>
                                        </div>
                                        <div v-if="studentComment(student)" class="course-table-student-tooltip-comment">
                                            <span>Kommentar</span>
                                            <div>{{ studentComment(student) }}</div>
                                        </div>
                                        <div
                                            v-if="!studentTooltipDetails(student).length && !studentComment(student)"
                                            class="text-body-2">
                                            Keine weiteren Angaben.
                                        </div>
                                    </v-tooltip>
                                </th>
                                <template
                                    v-for="courseDate in sortedCourseDates"
                                    :key="`student-${student.id}-date-${courseDate.id || courseDate.date}`">
                                    <td
                                        v-if="!isFreeCourseDate(courseDate)"
                                        class="course-table-entry-cell"
                                        :class="[
                                            {
                                                'course-table-entry-cell--interactive': true,
                                                'course-table-entry-cell--absent': tableView === 'entries' && studentAttendanceState(student, courseDate) === false,
                                                'course-table-entry-cell--selected': isEntryDialogCellSelected(student, courseDate),
                                                'course-table-entry-cell--transfer-selected': isEntryTransferCellSelected(student, courseDate),
                                                'course-table-entry-cell--transfer-unavailable': entryTransfer && !isEntryTransferTarget(student, courseDate),
                                            },
                                            courseDateColumnMarkingClass(courseDate),
                                        ]"
                                        role="button"
                                        tabindex="0"
                                        :aria-label="entryTransfer ? `${studentName(student)} - ${compactCourseDateTitle(courseDate)}` : tableView === 'attendance' ? attendanceMarkerTitle(student, courseDate) : undefined"
                                        :aria-pressed="entryTransfer ? isEntryTransferCellSelected(student, courseDate) : undefined"
                                        :aria-disabled="entryTransfer ? entryTransferSaving || !isEntryTransferTarget(student, courseDate) : tableView === 'attendance' && isAttendanceCellSaving(student, courseDate)"
                                        @click="activateStudentCell(student, courseDate)"
                                        @keydown.enter.self.prevent="activateStudentCell(student, courseDate)"
                                        @keydown.space.self.prevent="activateStudentCell(student, courseDate)">
                                        <span v-if="isEntryTransferCellSelected(student, courseDate)" class="course-table-transfer-marker">✓ Ausgewählt</span>
                                        <v-icon
                                            v-if="tableView === 'entries' && studentAttendanceState(student, courseDate) !== null"
                                            class="course-table-entry-cell-attendance-marker"
                                            :color="studentAttendanceState(student, courseDate) ? 'success' : 'error'"
                                            size="14"
                                            :aria-label="studentAttendanceState(student, courseDate) ? 'Anwesend' : 'Abwesend'"
                                            :title="studentAttendanceState(student, courseDate) ? 'Anwesend' : 'Abwesend'">
                                            {{ studentAttendanceState(student, courseDate) ? 'mdi-check' : 'mdi-close' }}
                                        </v-icon>
                                        <div class="course-table-entry-cell-content">
                                            <div
                                                v-if="tableView === 'entries' && entriesForCell(student, courseDate).length"
                                                class="course-table-entry-cell-badges"
                                                data-testid="course-table-entry-cell-badges">
                                                <div
                                                    v-if="supplementaryEntriesForCell(student, courseDate).length"
                                                    class="course-table-entry-cell-badge-row"
                                                    data-testid="course-table-entry-cell-supplementary-row">
                                                    <v-chip
                                                        v-for="entry in supplementaryEntriesForCell(student, courseDate)"
                                                        :key="entry.uid"
                                                        class="course-table-entry-cell-badge"
                                                        size="x-small"
                                                        :color="cellEntryColor(entry)"
                                                        variant="tonal"
                                                        :title="entry.description || cellEntryTypeLabel(entry)">
                                                        {{ compactCellEntryType(entry) }}
                                                    </v-chip>
                                                </div>
                                                <div
                                                    v-if="compactPerformanceEntriesForCell(student, courseDate).length"
                                                    class="course-table-entry-cell-badge-row"
                                                    data-testid="course-table-entry-cell-performance-row">
                                                    <div
                                                        v-for="entry in compactPerformanceEntriesForCell(student, courseDate)"
                                                        :key="entry.uid"
                                                        class="course-table-entry-cell-performance">
                                                        <v-chip
                                                            class="course-table-entry-cell-badge"
                                                            size="x-small"
                                                            :color="cellEntryColor(entry)"
                                                            variant="tonal"
                                                            :title="entry.description || cellEntryTypeLabel(entry)">
                                                            {{ compactCellEntryType(entry) }}<template v-if="entryTypeHasProperties(entry)">:&nbsp;
                                                                <span
                                                                    :class="{
                                                                        'font-weight-bold': compactCellEntryGrade(entry),
                                                                        'course-table-entry-cell-grade--missing': !compactCellEntryGrade(entry),
                                                                    }">
                                                                    {{ compactCellEntryGrade(entry) || 'N/A' }}
                                                                </span>
                                                            </template>
                                                        </v-chip>
                                                    </div>
                                                </div>
                                            </div>
                                            <span
                                                v-if="tableView === 'attendance' && isAttendanceToggleable(courseDate)"
                                                class="course-table-attendance-marker"
                                                :title="attendanceMarkerTitle(student, courseDate)"
                                                aria-hidden="true">
                                                <v-progress-circular v-if="isAttendanceCellSaving(student, courseDate)" indeterminate size="18" width="2" />
                                                <v-icon
                                                    v-else-if="studentAttendanceState(student, courseDate) !== null"
                                                    :color="studentAttendanceState(student, courseDate) ? 'success' : 'error'"
                                                    size="18">
                                                    {{ studentAttendanceState(student, courseDate) ? 'mdi-check' : 'mdi-close' }}
                                                </v-icon>
                                            </span>
                                        </div>
                                        <v-tooltip
                                            v-if="tableView === 'entries' && entriesForCell(student, courseDate).length"
                                            activator="parent"
                                            content-class="course-table-entry-tooltip"
                                            location="top"
                                            :max-width="440"
                                            :open-delay="250">
                                            <div class="course-table-entry-tooltip-header">
                                                <strong>{{ studentName(student) }}</strong>
                                                <span>{{ compactCourseDateTitle(courseDate) }}</span>
                                            </div>
                                            <div
                                                v-for="detail in cellEntryHoverItems(student, courseDate)"
                                                :key="detail.uid"
                                                class="course-table-entry-tooltip-item">
                                                <div class="course-table-entry-tooltip-meta">
                                                    <span>{{ detail.kind }}</span>
                                                    <strong>{{ detail.type }}</strong>
                                                    <span v-if="detail.grade">Note: {{ detail.grade }}</span>
                                                </div>
                                                <div v-if="detail.title" class="course-table-entry-tooltip-title">
                                                    {{ detail.title }}
                                                </div>
                                                <div v-if="detail.description" class="course-table-entry-tooltip-text">
                                                    <span>Aufgabe:</span> {{ detail.description }}
                                                </div>
                                                <div v-if="detail.comment" class="course-table-entry-tooltip-text">
                                                    <span>Kommentar:</span> {{ detail.comment }}
                                                </div>
                                            </div>
                                        </v-tooltip>
                                    </td>
                                    <td
                                        v-else-if="studentIndex === 0"
                                        class="course-table-entry-cell course-table-entry-cell--free course-table-entry-cell--free-reason"
                                        :rowspan="sortedSelectedStudents.length">
                                        <span class="course-table-free-reason">{{ freeCourseDateReason(courseDate) }}</span>
                                    </td>
                                </template>
                            </tr>
                            <tr v-if="!sortedSelectedStudents.length" class="course-table-empty-row">
                                <td :colspan="tableColumnCount">Keine Schüler:innen ausgewählt.</td>
                            </tr>
                            <tr v-else-if="!sortedCourseDates.length" class="course-table-empty-row">
                                <td :colspan="tableColumnCount">Keine Termine vorhanden.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </v-card-text>
        </v-card>

        <v-dialog v-model="curriculumDialog.open" persistent scrollable max-width="720">
            <v-card class="course-table-curriculum-dialog-card" data-testid="course-table-curriculum-dialog">
                <v-card-title class="course-table-curriculum-dialog-title">
                    <v-icon color="deep-purple" size="22">mdi-book-education-outline</v-icon>
                    <div>
                        <div class="course-table-curriculum-date" data-testid="curriculum-dialog-date">
                            {{ curriculumDialogCourseDateTitle }}
                        </div>
                        <div class="text-subtitle-2 text-medium-emphasis">{{ curriculumDialogTitle }}</div>
                    </div>
                </v-card-title>
                <v-divider />
                <v-card-text class="course-table-curriculum-dialog-content pa-0">
                    <div v-if="curriculumFileError" role="alert" class="text-error text-body-2 pa-3">
                        {{ curriculumFileError }}
                    </div>
                    <div v-if="curriculumDialog.loading" class="course-table-curriculum-dialog-loading">
                        <v-progress-circular color="deep-purple" indeterminate />
                        <span>Curriculum wird geladen …</span>
                    </div>
                    <v-list
                        v-else-if="curriculumDialogTopics.length"
                        class="course-table-curriculum-dialog-list"
                        density="compact">
                        <section v-for="topic in curriculumDialogTopics" :key="topic.key"
                            class="course-table-curriculum-chapter" :aria-label="topic.title">
                            <v-list-subheader :title="topic.title" color="deep-purple" />
                            <v-list-item
                                v-for="unit in topic.units"
                                :key="unit.key"
                                class="course-table-curriculum-dialog-unit"
                                :class="{
                                    'course-table-curriculum-dialog-unit--linked': isCurriculumUnitLinkedToDialogDate(topic, unit),
                                }"
                                :title="unit.title">
                                <template #title>
                                    <div class="course-table-curriculum-unit-heading">
                                        <v-icon v-if="unit.isExam" color="error" size="17" role="img" aria-label="Prüfung">mdi-file-document-edit-outline</v-icon>
                                        <span class="course-table-curriculum-unit-title" :class="{ 'text-error': unit.isExam }">{{ unit.title }}</span>
                                    </div>
                                </template>
                                <div v-if="curriculumUnitPlannedDates(topic, unit).length"
                                    class="course-table-curriculum-planning" data-testid="curriculum-unit-planning">
                                    <span>Im Kurs geplant:</span>
                                    <span v-for="plannedDate in curriculumUnitPlannedDates(topic, unit)" :key="plannedDate.id"
                                        class="course-table-curriculum-planned-date"
                                        :class="{ 'course-table-curriculum-planned-date--current': Number(plannedDate.id) === Number(curriculumDialog.courseDate?.id) }">
                                        {{ compactCourseDateTitle(plannedDate) }}
                                        <template v-if="courseDateHoursLabel(plannedDate)"> · {{ courseDateHoursLabel(plannedDate) }}</template>
                                        <template v-if="Number(plannedDate.id) === Number(curriculumDialog.courseDate?.id)"> · dieser Termin</template>
                                    </span>
                                </div>
                                <div v-else class="course-table-curriculum-planning text-medium-emphasis">Noch nicht geplant</div>
                                <div v-if="unit.files.length" class="course-table-curriculum-files" data-testid="course-table-curriculum-files">
                                    <div v-for="file in unit.files" :key="file.id" class="course-table-curriculum-file">
                                        <v-icon size="17" color="primary">{{ curriculumFileIcon(file) }}</v-icon>
                                        <button
                                            v-if="file.preview_url || file.download_url"
                                            type="button"
                                            class="course-table-curriculum-file-name"
                                            :disabled="Boolean(curriculumFilePending)"
                                            :title="file.preview_url ? `${file.name} – Vorschau` : `${file.name} – Herunterladen`"
                                            @click.stop="openCurriculumFile(file, Boolean(file.preview_url))">{{ file.name }}</button>
                                        <span v-else class="course-table-curriculum-file-name">{{ file.name }}</span>
                                        <div class="course-table-curriculum-file-actions">
                                            <v-btn
                                                v-if="file.download_url"
                                                icon="mdi-download-outline"
                                                :title="`${file.name} herunterladen`"
                                                size="x-small"
                                                variant="text"
                                                :disabled="Boolean(curriculumFilePending)"
                                                :loading="curriculumFilePending === file.id"
                                                @click.stop="openCurriculumFile(file)" />
                                            <button
                                                type="button"
                                                role="switch"
                                                :aria-checked="curriculumFileIsStudentVisible(file)"
                                                :data-testid="`curriculum-file-visibility-${file.id}`"
                                                :aria-label="`${file.name}: Für Schüler:innen sichtbar`"
                                                :title="curriculumFileIsStudentVisible(file) ? 'Für Schüler:innen verbergen' : 'Für Schüler:innen an diesem Termin freigeben'"
                                                class="course-table-curriculum-file-visibility"
                                                :class="{ 'course-table-curriculum-file-visibility--active': curriculumFileIsStudentVisible(file) }"
                                                :disabled="Boolean(curriculumUnitActionKey)"
                                                @click.stop="setCurriculumFileVisibility(file, !curriculumFileIsStudentVisible(file))">
                                                <v-icon size="15">{{ curriculumFileIsStudentVisible(file) ? 'mdi-eye-outline' : 'mdi-eye-off-outline' }}</v-icon>
                                                <span>{{ curriculumFileIsStudentVisible(file) ? 'Sichtbar' : 'Verborgen' }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <template #append>
                                    <v-btn
                                        v-if="isCurriculumUnitLinkedToDialogDate(topic, unit)"
                                        color="error"
                                        density="compact"
                                        prepend-icon="mdi-link-variant-off"
                                        size="x-small"
                                        variant="tonal"
                                        class="course-table-curriculum-unit-action"
                                        :disabled="Boolean(curriculumUnitActionKey)"
                                        :loading="isCurriculumUnitActionPending(topic, unit)"
                                        @click.stop="unlinkCurriculumUnit(topic, unit)">
                                        Lösen
                                    </v-btn>
                                    <v-btn
                                        v-else
                                        color="success"
                                        density="compact"
                                        prepend-icon="mdi-link-variant"
                                        size="x-small"
                                        variant="tonal"
                                        class="course-table-curriculum-unit-action"
                                        :disabled="Boolean(curriculumUnitActionKey)"
                                        :loading="isCurriculumUnitActionPending(topic, unit)"
                                        @click.stop="linkCurriculumUnit(topic, unit)">
                                        Verknüpfen
                                    </v-btn>
                                </template>
                            </v-list-item>
                            <div v-if="!topic.units.length" class="course-table-curriculum-dialog-empty-topic">
                                Keine Einheiten
                            </div>
                        </section>
                    </v-list>
                    <div v-else class="course-table-curriculum-dialog-empty">
                        Dieses Curriculum enthält keine Themen.
                    </div>
                    <v-list v-if="!curriculumDialog.loading && unmatchedCurriculumTitles.length"
                        class="course-table-curriculum-dialog-list" density="compact"
                        data-testid="course-table-old-curriculum-links">
                        <section class="course-table-curriculum-chapter" aria-label="Weitere verknüpfte Inhalte">
                            <v-list-subheader title="Weitere verknüpfte Inhalte" />
                            <v-list-item v-for="title in unmatchedCurriculumTitles" :key="title" :title="title"
                                class="course-table-curriculum-dialog-unit">
                                <template #append>
                                    <v-btn color="error" density="compact" prepend-icon="mdi-link-variant-off"
                                        size="x-small" variant="tonal" class="course-table-curriculum-unit-action"
                                        :disabled="Boolean(curriculumUnitActionKey)"
                                        :loading="isCurriculumUnitActionPending({}, { title })"
                                        @click.stop="unlinkCurriculumUnit({}, { title })">Lösen</v-btn>
                                </template>
                            </v-list-item>
                        </section>
                    </v-list>
                </v-card-text>
                <v-divider />
                <v-card-actions class="course-table-curriculum-dialog-actions">
                    <v-spacer />
                    <v-btn
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-close"
                        :disabled="Boolean(curriculumUnitActionKey)"
                        @click="closeCurriculumDialog">
                        Schließen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog :model-value="Boolean(curriculumFilePreview)" max-width="1000" @update:model-value="closeCurriculumFilePreview">
            <v-card v-if="curriculumFilePreview">
                <v-card-title class="text-subtitle-1 text-wrap">{{ curriculumFilePreview.file.name }}</v-card-title>
                <v-card-text class="pa-0">
                    <CurriculumPdfPreview
                        v-if="curriculumFilePreview.isPdf"
                        :document-id="curriculumFilePreview.file.id"
                        :src="curriculumFilePreview.url"
                        class="course-table-file-preview" />
                    <iframe v-else :src="curriculumFilePreview.url" :title="curriculumFilePreview.file.name"
                        sandbox="" referrerpolicy="no-referrer" class="course-table-file-preview" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeCurriculumFilePreview">Schließen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="contentDialog.open" persistent max-width="720">
            <v-card data-testid="course-table-content-dialog">
                <v-card-title class="text-subtitle-1 font-weight-bold d-flex align-center ga-2">
                    <v-icon size="20">mdi-text-box-outline</v-icon>
                    Inhalt bearbeiten
                </v-card-title>
                <v-divider />
                <v-card-text class="d-flex flex-column ga-3">
                    <div class="d-flex align-center flex-wrap ga-2">
                        <div class="text-body-2">
                            <strong>Termin:</strong> {{ compactCourseDateTitle(contentDialog.courseDate) }}
                        </div>
                        <div class="d-flex ga-2 ml-auto">
                            <v-btn
                                color="primary"
                                data-testid="course-table-content-dialog-previous-date"
                                prepend-icon="mdi-chevron-left"
                                size="small"
                                variant="tonal"
                                :disabled="contentSaving || !hasPreviousContentDialogDate"
                                @click="navigateContentDialogDate(-1)">
                                Vorheriger
                            </v-btn>
                            <v-btn
                                color="primary"
                                data-testid="course-table-content-dialog-next-date"
                                append-icon="mdi-chevron-right"
                                size="small"
                                variant="tonal"
                                :disabled="contentSaving || !hasNextContentDialogDate"
                                @click="navigateContentDialogDate(1)">
                                Nächster
                            </v-btn>
                        </div>
                    </div>
                    <v-card
                        color="deep-purple"
                        data-testid="course-table-content-dialog-curriculum"
                        variant="tonal">
                        <v-card-text class="d-flex flex-column ga-2 pa-3">
                            <div class="d-flex align-center justify-space-between ga-3">
                                <strong class="text-body-2">Curriculum-Inhalt</strong>
                                <v-btn
                                    color="deep-purple"
                                    data-testid="course-table-content-dialog-curriculum-apply"
                                    prepend-icon="mdi-content-copy"
                                    size="default"
                                    variant="flat"
                                    :disabled="contentSaving || !contentDialogCurriculumContent.length"
                                    @click="applyCurriculumContentToContentDialog">
                                    In Stoff übernehmen
                                </v-btn>
                            </div>
                            <ul
                                v-if="contentDialogCurriculumContent.length"
                                class="course-table-content-dialog-curriculum-list text-body-2">
                                <li
                                    v-for="content in contentDialogCurriculumContent"
                                    :key="content">
                                    {{ content }}
                                </li>
                            </ul>
                            <div v-else class="text-body-2 text-medium-emphasis">
                                Für diesen Termin ist kein Curriculum-Inhalt verknüpft.
                            </div>
                        </v-card-text>
                    </v-card>
                    <ItsRichTextEditor v-model="contentDialog.content" :disabled="contentSaving" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn
                        color="warning"
                        variant="flat"
                        prepend-icon="mdi-close"
                        :disabled="contentSaving"
                        @click="closeContentDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="success"
                        variant="flat"
                        prepend-icon="mdi-content-save"
                        :disabled="contentSaving"
                        :loading="contentSaving"
                        @click="saveContentDialog()">
                        Speichern
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="workDialog.open" persistent max-width="720">
            <v-card>
                <v-card-title class="text-subtitle-1 font-weight-bold d-flex align-center ga-2">
                    <v-icon size="20">mdi-clipboard-text</v-icon>
                    Arbeiten
                    <v-date-input
                        v-if="workDialogFormOpen && workDialogDateEditing"
                        v-model="workDialogForm.date_for_all_groups"
                        class="course-table-date-work-date-input"
                        data-testid="course-table-date-work-date-input"
                        density="compact"
                        hide-details
                        label="Datum"
                        variant="outlined"
                        @update:model-value="applyWorkDialogDate">
                        <template #day="{ item, props }">
                            <v-btn
                                v-bind="props"
                                :color="workDialogCourseDateKeys.includes(item.isoDate) ? 'primary' : props.color"
                                :variant="workDialogCourseDateKeys.includes(item.isoDate) && !item.isSelected ? 'tonal' : props.variant">
                                {{ item.localized }}
                            </v-btn>
                        </template>
                    </v-date-input>
                    <template v-else>
                        <v-chip
                            data-testid="course-table-date-edit-work-date"
                            color="primary"
                            size="x-small"
                            variant="tonal"
                            :class="{ 'cursor-pointer': workDialogFormOpen && !workSaving }"
                            :role="workDialogFormOpen ? 'button' : undefined"
                            :tabindex="workDialogFormOpen && !workSaving ? 0 : undefined"
                            @click="beginWorkDialogDateEditing"
                            @keydown.enter.prevent="beginWorkDialogDateEditing"
                            @keydown.space.prevent="beginWorkDialogDateEditing">
                            {{ workDialogDateTitle }}
                        </v-chip>
                    </template>
                    <v-chip
                        v-if="workDialogDurationLabel && !workDialogDateEditing && !workDialogFinishDateEditing"
                        color="secondary"
                        data-testid="course-table-date-work-duration"
                        prepend-icon="mdi-timer-sand"
                        size="x-small"
                        variant="outlined">
                        {{ workDialogDurationLabel }}
                    </v-chip>
                    <v-date-input
                        v-if="workDialogFormOpen && workDialogFinishDateEditing"
                        v-model="workDialogForm.finish_until_date"
                        clearable
                        class="course-table-date-work-date-input"
                        data-testid="course-table-date-work-finish-until-input"
                        density="compact"
                        hide-details
                        label="Fertig bis"
                        variant="outlined"
                        :disabled="workSaving"
                        @update:model-value="applyWorkDialogFinishDate" />
                    <v-chip
                        v-else-if="workDialogFormOpen"
                        data-testid="course-table-date-edit-work-finish-until"
                        color="warning"
                        prepend-icon="mdi-calendar-check"
                        size="x-small"
                        variant="tonal"
                        :class="{ 'cursor-pointer': !workSaving }"
                        role="button"
                        :tabindex="workSaving ? undefined : 0"
                        @click="beginWorkDialogFinishDateEditing"
                        @keydown.enter.prevent="beginWorkDialogFinishDateEditing"
                        @keydown.space.prevent="beginWorkDialogFinishDateEditing">
                        Fertig bis {{ workDialogFinishDateTitle }}
                    </v-chip>
                </v-card-title>
                <v-divider />
                <v-card-text class="d-flex flex-column ga-4">
                    <section v-if="!workDialogFormOpen">
                        <div class="d-flex align-center ga-2 mb-2">
                            <div class="text-caption font-weight-bold text-medium-emphasis">Arbeiten an diesem Termin</div>
                            <v-chip size="x-small" color="primary" variant="tonal">{{ dateWorkAssignments.length }}</v-chip>
                        </div>
                        <div v-if="dateWorkAssignments.length" class="course-table-date-work-list">
                            <div
                                v-for="assignment in dateWorkAssignments"
                                :key="assignment.key"
                                class="course-table-date-work-item cursor-pointer"
                                role="button"
                                tabindex="0"
                                @click="startEditingDateWork(assignment.work)"
                                @keydown.enter.self.prevent="startEditingDateWork(assignment.work)"
                                @keydown.space.self.prevent="startEditingDateWork(assignment.work)">
                                <div class="course-table-date-work-main">
                                    <div class="d-flex align-center flex-wrap ga-2">
                                        <v-chip
                                            size="x-small"
                                            :color="assignment.isGroupWork ? 'success' : 'primary'"
                                            variant="tonal">
                                            {{ assignment.work.type || 'Arbeit' }}
                                        </v-chip>
                                        <strong class="text-body-2">{{ assignment.work.title || assignment.label }}</strong>
                                        <v-chip
                                            :data-testid="`course-table-date-work-mode-${assignment.work.id}`"
                                            size="x-small"
                                            :color="assignment.isGroupWork ? 'deep-purple' : 'primary'"
                                            :prepend-icon="assignment.isGroupWork ? 'mdi-account-group' : 'mdi-account-outline'"
                                            variant="tonal">
                                            {{ assignment.isGroupWork ? 'Gruppenarbeit' : 'Einzelarbeit' }}
                                        </v-chip>
                                        <v-chip
                                            v-if="assignment.isGroupWork && assignment.scope !== 'Gruppenarbeit'"
                                            size="x-small"
                                            color="secondary"
                                            variant="outlined">
                                            {{ assignment.scope }}
                                        </v-chip>
                                        <v-chip
                                            v-if="assignment.work.date_for_all_groups"
                                            :data-testid="`course-table-date-work-start-${assignment.work.id}`"
                                            prepend-icon="mdi-calendar-start"
                                            size="x-small"
                                            color="primary"
                                            variant="tonal">
                                            Start
                                            {{ compactCourseDateTitle({ date: assignment.work.date_for_all_groups }) }}
                                        </v-chip>
                                        <v-chip
                                            v-if="assignment.work.finish_until_date"
                                            size="x-small"
                                            color="warning"
                                            variant="tonal">
                                            Fertig bis
                                            {{ compactCourseDateTitle({ date: assignment.work.finish_until_date }) }}
                                        </v-chip>
                                        <v-chip
                                            v-if="courseWorkDurationLabel(assignment.work)"
                                            color="secondary"
                                            :data-testid="`course-table-date-work-duration-${assignment.work.id}`"
                                            prepend-icon="mdi-timer-sand"
                                            size="x-small"
                                            variant="outlined">
                                            {{ courseWorkDurationLabel(assignment.work) }}
                                        </v-chip>
                                    </div>
                                    <div v-if="assignment.work.description" class="text-caption mt-1">
                                        {{ assignment.work.description }}
                                    </div>
                                </div>
                                <div class="d-flex ga-1">
                                    <v-btn
                                        :data-testid="`course-table-date-delete-work-${assignment.id}`"
                                        color="error"
                                        density="compact"
                                        icon="mdi-delete"
                                        size="x-small"
                                        title="Arbeit löschen"
                                        variant="text"
                                        @click.stop="openDeleteWorkDialog(assignment.work)" />
                                </div>
                            </div>
                        </div>
                        <v-alert v-else type="info" variant="tonal" density="compact">
                            Für diesen Termin ist noch keine Arbeit vorhanden.
                        </v-alert>
                    </section>

                    <section v-if="workDialogFormOpen">
                        <div class="course-table-date-work-form">
                            <v-tabs
                                v-model="workDialogTab"
                                color="primary"
                                density="compact"
                                class="mb-3"
                                :disabled="workDialogTypeEditing || workDialogModeEditing">
                                <v-tab value="work">Arbeit</v-tab>
                                <v-tab v-if="!workDialogForm.is_group_work" value="students">Schüler:innen</v-tab>
                                <v-tab v-if="workDialogForm.is_group_work" value="groups">Gruppen</v-tab>
                            </v-tabs>
                            <div class="course-table-work-tab-panels">
                                <div v-show="workDialogTab === 'work'">
                                    <div
                                        class="course-table-work-meta-row mb-3"
                                        :class="{
                                            'course-table-work-meta-row--single':
                                                workDialogTypeEditing || workDialogModeEditing,
                                        }">
                                        <div v-if="!workDialogModeEditing">
                                            <div class="text-caption text-medium-emphasis mb-1">Typ</div>
                                            <div v-if="workDialogForm.id && !workDialogTypeEditing" class="d-flex align-center ga-1">
                                                <v-chip
                                                    data-testid="course-table-date-edit-work-type"
                                                    title="Arbeitstyp bearbeiten"
                                                    color="primary"
                                                    variant="tonal"
                                                    :disabled="workSaving || workDialogModeEditing"
                                                    @click="beginDateWorkTypeEditing">
                                                    {{ selectedDateWorkTypeTitle }}
                                                </v-chip>
                                            </div>
                                            <div v-else class="d-flex flex-wrap ga-1">
                                                <v-btn
                                                    v-for="item in availableWorkTypes"
                                                    :key="item.value"
                                                    size="small"
                                                    :variant="selectedDateWorkTypeValue === item.value ? 'flat' : 'tonal'"
                                                    :color="selectedDateWorkTypeValue === item.value ? 'primary' : 'default'"
                                                    @click="selectDateWorkType(item.value)">
                                                    {{ item.title }}
                                                </v-btn>
                                            </div>
                                            <div v-if="workDialogForm.id && workDialogTypeEditing" class="d-flex justify-end ga-2 mt-2">
                                                <v-btn variant="text" :disabled="workSaving" @click="cancelDateWorkTypeEditing">Abbrechen</v-btn>
                                                <v-btn color="primary" variant="flat" :disabled="!canConfirmDateWorkType" @click="confirmDateWorkTypeEditing">OK</v-btn>
                                            </div>
                                            <v-alert v-if="!availableWorkTypes.length" type="warning" variant="tonal" density="compact" class="mt-2">
                                                Für diesen Kurs sind keine Arbeitstypen konfiguriert.
                                            </v-alert>
                                        </div>
                                        <div v-if="!workDialogTypeEditing">
                                            <div class="text-caption text-medium-emphasis mb-1">Arbeitsform</div>
                                            <v-btn-toggle
                                                v-if="!workDialogForm.id"
                                                v-model="workDialogForm.is_group_work"
                                                data-testid="course-table-date-create-work-mode"
                                                color="primary"
                                                density="compact"
                                                mandatory
                                                variant="outlined"
                                                :disabled="workSaving">
                                                <v-btn :value="false">Einzelarbeit</v-btn>
                                                <v-btn :value="true">Gruppenarbeit</v-btn>
                                            </v-btn-toggle>
                                            <div v-else-if="!workDialogModeEditing" class="d-flex align-center ga-1">
                                                <v-chip
                                                    data-testid="course-table-date-edit-work-mode"
                                                    title="Arbeitsform bearbeiten"
                                                    color="primary"
                                                    variant="tonal"
                                                    :disabled="workSaving || workDialogTypeEditing"
                                                    @click="beginDateWorkModeEditing">
                                                    {{ selectedDateWorkModeTitle }}
                                                </v-chip>
                                            </div>
                                            <div v-else>
                                                <v-btn-toggle
                                                    v-model="workDialogModeDraft"
                                                    color="primary"
                                                    density="compact"
                                                    mandatory
                                                    variant="outlined"
                                                    :disabled="workSaving">
                                                    <v-btn :value="false">Einzelarbeit</v-btn>
                                                    <v-btn :value="true">Gruppenarbeit</v-btn>
                                                </v-btn-toggle>
                                                <div class="d-flex justify-end ga-2 mt-2">
                                                    <v-btn variant="text" :disabled="workSaving" @click="cancelDateWorkModeEditing">Abbrechen</v-btn>
                                                    <v-btn color="primary" variant="flat" :disabled="!canConfirmDateWorkMode" @click="confirmDateWorkModeEditing">OK</v-btn>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <v-text-field
                                        v-model="workDialogForm.title"
                                        label="Titel"
                                        maxlength="255"
                                        :disabled="workSaving" />
                                    <v-text-field v-if="dateWorkRequiresMaximumPlus" v-model="workDialogForm.maximum_plus"
                                        label="Maximale Plusanzahl" type="text" inputmode="numeric" variant="outlined"
                                        hint="So viele Plus sind bei dieser Arbeit insgesamt möglich." persistent-hint
                                        :error-messages="dateWorkMaximumPlusError" :disabled="workSaving" />
                                    <v-textarea
                                        v-model="workDialogForm.description"
                                        label="Beschreibung"
                                        rows="3"
                                        maxlength="1024"
                                        :disabled="workSaving" />
                                </div>
                                <div
                                    v-if="!workDialogForm.is_group_work"
                                    data-testid="course-table-work-students-panel"
                                    v-show="workDialogTab === 'students'">
                                    <div
                                        v-if="individualWorkGradeRows.length"
                                        class="d-flex flex-column ga-2">
                                        <v-card
                                            v-for="row in individualWorkGradeRows"
                                            :key="`individual-work-grade-${row.studentId}`"
                                            class="pa-2"
                                            variant="outlined">
                                            <div class="course-table-work-group-grade-grid">
                                                <div class="text-body-2 font-weight-medium">{{ row.studentName }}</div>
                                                <v-text-field
                                                    :model-value="row.comment"
                                                    density="compact"
                                                    hide-details
                                                    label="Kommentar"
                                                    :maxlength="1024"
                                                    variant="outlined"
                                                    :disabled="workSaving"
                                                    @update:model-value="setIndividualWorkStudentComment(row.studentId, $event)" />
                                                <div
                                                    v-if="availableWorkGradeInputMode === 'fixed'"
                                                    class="course-table-work-grade-choice-field">
                                                    <div class="text-caption text-medium-emphasis mb-1">Note</div>
                                                    <div class="d-flex flex-wrap ga-1">
                                                        <v-chip
                                                            v-for="item in availableWorkGradeItems"
                                                            :key="`individual-work-grade-${row.studentId}-${item.value}`"
                                                            size="small"
                                                            :title="item.title"
                                                            :variant="row.grade === item.value ? 'flat' : 'tonal'"
                                                            :color="row.grade === item.value ? 'success' : 'default'"
                                                            :disabled="workSaving"
                                                            @click="setIndividualWorkStudentGrade(
                                                                row.studentId,
                                                                toggledCourseWorkGrade(row.grade, item.value),
                                                            )">
                                                            {{ item.value }}
                                                        </v-chip>
                                                    </div>
                                                </div>
                                                <v-text-field
                                                    v-else-if="['free', 'plus', 'plus_minus', 'points'].includes(availableWorkGradeInputMode)"
                                                    :model-value="row.grade"
                                                    density="compact"
                                                    hide-details="auto"
                                                    :hint="gradeInputHint(availableWorkGradeInputMode, workDialogForm.type)"
                                                    :inputmode="availableWorkGradeInputMode === 'points' ? 'decimal' : undefined"
                                                    persistent-hint
                                                    :rules="[value => gradeInputValidation(value, availableWorkGradeInputMode, workDialogForm.type)]"
                                                    label="Note"
                                                    :maxlength="50"
                                                    variant="outlined"
                                                    :disabled="workSaving"
                                                    @update:model-value="setIndividualWorkStudentGrade(row.studentId, $event)" />
                                                <div v-else class="text-caption text-medium-emphasis">
                                                    Keine Bewertung vorgesehen.
                                                </div>
                                                <div v-if="specialGradeItemsForType(workDialogForm.type).length" class="d-flex flex-wrap ga-1 mt-2">
                                                    <v-chip
                                                        v-for="item in specialGradeItemsForType(workDialogForm.type)"
                                                        :key="item.value"
                                                        size="small"
                                                        :variant="row.grade === item.value ? 'flat' : 'outlined'"
                                                        :color="row.grade === item.value ? 'primary' : undefined"
                                                        @click="setIndividualWorkStudentGrade(row.studentId, toggledCourseWorkGrade(row.grade, item.value))">
                                                        {{ item.title }}
                                                    </v-chip>
                                                </div>
                                            </div>
                                        </v-card>
                                    </div>
                                    <v-alert v-else type="info" variant="tonal" density="compact">
                                        Für diesen Kurs sind keine Schüler:innen vorhanden.
                                    </v-alert>
                                </div>
                                <div
                                    v-if="workDialogForm.is_group_work"
                                    data-testid="course-table-work-groups-panel"
                                    v-show="workDialogTab === 'groups'">
                                    <div class="d-flex align-center justify-space-between flex-wrap ga-2 mb-3">
                                        <div
                                            v-if="hasSavedRandomGroupConfiguration"
                                            class="d-flex align-center flex-wrap ga-1">
                                            <span class="text-body-2 font-weight-bold">Zufällige Gruppen</span>
                                            <v-chip color="primary" variant="tonal">
                                                {{ workDialogForm.group_size }} Mitglieder pro Gruppe
                                            </v-chip>
                                        </div>
                                        <v-btn
                                            v-else
                                            data-testid="course-table-date-random-groups"
                                            prepend-icon="mdi-shuffle-variant"
                                            variant="tonal"
                                            :disabled="workSaving || maxRandomGroupSize < 2"
                                            @click="openRandomGroupsDialog">
                                            Zufällige Gruppen
                                        </v-btn>
                                        <v-btn
                                            v-if="hasUnassignedWorkGroupStudents"
                                            color="primary"
                                            prepend-icon="mdi-plus"
                                            variant="tonal"
                                            :disabled="workSaving"
                                            @click="addWorkDialogGroup">
                                            Neue Gruppe
                                        </v-btn>
                                        <span v-else class="text-body-2 font-weight-medium text-success">Alles Ok</span>
                                    </div>
                                    <v-card
                                        v-if="unassignedWorkGroupStudents.length"
                                        class="pa-3 mb-3"
                                        variant="outlined">
                                        <div class="d-flex align-center flex-wrap ga-2 mb-2">
                                            <strong class="text-body-2">Nicht zugeordnet</strong>
                                            <v-chip color="warning" size="x-small" variant="tonal">
                                                {{ unassignedWorkGroupStudents.length }} offen
                                            </v-chip>
                                        </div>
                                        <div class="d-flex flex-wrap ga-1">
                                            <v-chip
                                                v-for="student in unassignedWorkGroupStudents"
                                                :key="`unassigned-work-group-student-${student.value}`"
                                                class="course-table-work-group-student-chip"
                                                color="warning"
                                                size="small"
                                                variant="tonal"
                                                :disabled="workSaving"
                                                :draggable="!workSaving"
                                                @dragstart="startUnassignedWorkGroupStudentDrag($event, student.value)"
                                                @dragend="finishWorkGroupStudentDrag">
                                                {{ student.title }}
                                            </v-chip>
                                        </div>
                                    </v-card>
                                    <div v-if="workDialogGroups.length" class="d-flex flex-column ga-3">
                                        <v-card
                                            v-for="(group, groupIndex) in workDialogGroups"
                                            :key="`work-dialog-group-${groupIndex}`"
                                            :data-testid="`course-table-work-group-card-${groupIndex}`"
                                            variant="outlined"
                                            class="pa-3 course-table-work-group-card cursor-pointer"
                                            :class="{
                                                'course-table-work-group-card--drop-target':
                                                    workDialogStudentDrag.targetGroupIndex === groupIndex,
                                            }"
                                            role="button"
                                            tabindex="0"
                                            @click="openWorkDialogGroupDetails(groupIndex)"
                                            @keydown.enter.self.prevent="openWorkDialogGroupDetails(groupIndex)"
                                            @keydown.space.self.prevent="openWorkDialogGroupDetails(groupIndex)"
                                            @dragenter.prevent="setWorkGroupStudentDropTarget(groupIndex)"
                                            @dragover.prevent
                                            @drop.prevent="dropWorkGroupStudent(groupIndex)">
                                            <div class="d-flex align-center flex-wrap ga-2 mb-2">
                                                <strong class="text-body-2">{{ group.name || `Gruppe ${groupIndex + 1}` }}</strong>
                                                <v-chip size="x-small" variant="tonal">
                                                    {{ workGroupMemberCountTitle(group) }}
                                                </v-chip>
                                                <v-spacer />
                                                <v-date-input
                                                    v-if="workDialogGroupDateEditingIndex === groupIndex"
                                                    v-model="group.date"
                                                    v-model:menu="workDialogGroupDateMenuOpen"
                                                    class="course-table-work-group-date-input"
                                                    :data-testid="`course-table-date-work-group-date-input-${groupIndex}`"
                                                    density="compact"
                                                    hide-details
                                                    label="Gruppendatum"
                                                    variant="outlined"
                                                    :disabled="workSaving"
                                                    @click.stop
                                                    @update:menu="handleWorkDialogGroupDateMenu"
                                                    @update:model-value="applyWorkDialogGroupDate(groupIndex, $event)">
                                                    <template #day="{ item, props }">
                                                        <v-btn
                                                            v-bind="props"
                                                            :color="workDialogCourseDateKeys.includes(item.isoDate) ? 'primary' : props.color"
                                                            :variant="workDialogCourseDateKeys.includes(item.isoDate) && !item.isSelected ? 'tonal' : props.variant">
                                                            {{ item.localized }}
                                                        </v-btn>
                                                    </template>
                                                </v-date-input>
                                                <v-chip
                                                    v-else
                                                    :data-testid="`course-table-date-edit-work-group-date-${groupIndex}`"
                                                    color="primary"
                                                    prepend-icon="mdi-calendar-edit"
                                                    size="x-small"
                                                    variant="tonal"
                                                    :class="{ 'cursor-pointer': !workSaving }"
                                                    :disabled="workSaving"
                                                    @click.stop="beginWorkDialogGroupDateEditing(groupIndex)"
                                                    @keydown.enter.prevent="beginWorkDialogGroupDateEditing(groupIndex)"
                                                    @keydown.space.prevent="beginWorkDialogGroupDateEditing(groupIndex)">
                                                    {{ group.date ? formatDateShort(group.date) : 'Ohne Datum' }}
                                                </v-chip>
                                                <v-btn
                                                    v-if="group._is_new"
                                                    color="error"
                                                    density="compact"
                                                    icon="mdi-delete"
                                                    size="x-small"
                                                    title="Neue Gruppe entfernen"
                                                    variant="text"
                                                    :disabled="workSaving"
                                                    @click.stop="removeWorkDialogGroup(groupIndex)" />
                                                <v-icon size="18">mdi-chevron-right</v-icon>
                                            </div>
                                            <v-autocomplete
                                                v-if="group._is_new"
                                                v-model="group.student_ids"
                                                :items="workGroupStudentItems(groupIndex)"
                                                chips
                                                closable-chips
                                                density="compact"
                                                label="Schüler:innen"
                                                multiple
                                                variant="outlined"
                                                hint="Mindestens eine Person auswählen."
                                                no-data-text="Keine weiteren Schüler:innen verfügbar."
                                                persistent-hint
                                                :disabled="workSaving"
                                                @click.stop>
                                                <template #chip="{ internalItem }">
                                                    <v-chip
                                                        class="course-table-work-group-student-chip"
                                                        closable
                                                        :disabled="workSaving"
                                                        :draggable="!workSaving"
                                                        @click.stop
                                                        @dragstart.stop="startWorkGroupStudentDrag($event, groupIndex, internalItem.value)"
                                                        @dragend="finishWorkGroupStudentDrag"
                                                        @click:close="removeWorkGroupStudent(groupIndex, internalItem.value)">
                                                        {{ internalItem.title }}
                                                    </v-chip>
                                                </template>
                                            </v-autocomplete>
                                            <div v-else-if="workGroupStudentNames(group).length" class="d-flex flex-wrap ga-1">
                                                <v-chip
                                                    v-for="(studentNameValue, studentIndex) in workGroupStudentNames(group)"
                                                    :key="`${studentIndex}-${studentNameValue}`"
                                                    class="course-table-work-group-student-chip"
                                                    size="small"
                                                    color="primary"
                                                    closable
                                                    :disabled="workSaving"
                                                    :draggable="!workSaving"
                                                    variant="tonal"
                                                    @click.stop
                                                    @dragstart="startWorkGroupStudentDrag($event, groupIndex, group.student_ids[studentIndex])"
                                                    @dragend="finishWorkGroupStudentDrag"
                                                    @click:close="removeWorkGroupStudent(groupIndex, group.student_ids[studentIndex])">
                                                    {{ studentNameValue }}
                                                </v-chip>
                                            </div>
                                            <div v-else class="text-caption text-medium-emphasis">Keine Schüler:innen zugeordnet.</div>
                                        </v-card>
                                    </div>
                                    <v-alert v-else type="info" variant="tonal" density="compact">
                                        Für diese Gruppenarbeit sind noch keine Gruppen definiert.
                                    </v-alert>
                                </div>
                            </div>
                            <div class="d-flex justify-end ga-2 mt-4">
                                <v-btn
                                    variant="text"
                                    :disabled="workSaving || workDialogTypeEditing || workDialogModeEditing"
                                    @click="cancelDateWorkForm">
                                    Abbrechen
                                </v-btn>
                                <v-btn
                                    color="success"
                                    variant="flat"
                                    :loading="workSaving"
                                    :disabled="!canSaveDateWork"
                                    @click="saveDateWork">
                                    Speichern
                                </v-btn>
                            </div>
                        </div>
                    </section>
                </v-card-text>
                <v-card-actions v-if="!workDialogFormOpen">
                    <v-spacer />
                    <v-btn
                        color="primary"
                        variant="flat"
                        :disabled="workSaving || workDeleting"
                        @click="closeWorkDialog">
                        Schließen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="workDialogGroupDetails.open" persistent max-width="720">
            <v-card v-if="workDialogGroupDetailsGroup">
                <v-card-title class="d-flex align-center ga-2 text-subtitle-1 font-weight-bold">
                    <v-icon size="20">mdi-account-group</v-icon>
                    {{ workDialogGroupDetailsGroup.name || `Gruppe ${workDialogGroupDetails.groupIndex + 1}` }}
                </v-card-title>
                <v-divider />
                <v-card-text class="d-flex flex-column ga-4">
                    <v-textarea
                        v-model="workDialogGroupDetailsGroup.comment"
                        label="Beschreibung"
                        rows="3"
                        :counter="1024"
                        :maxlength="1024"
                        variant="outlined"
                        :disabled="workSaving" />
                    <div>
                        <div class="text-subtitle-2 font-weight-bold mb-2">Benotung</div>
                        <div
                            v-if="workGroupGradeRows(workDialogGroupDetailsGroup).length"
                            class="d-flex flex-column ga-2">
                            <v-card
                                v-for="row in workGroupGradeRows(workDialogGroupDetailsGroup)"
                                :key="`work-group-grade-${workDialogGroupDetails.groupIndex}-${row.studentId}`"
                                class="pa-2"
                                variant="outlined">
                                <div class="course-table-work-group-grade-grid">
                                    <div class="text-body-2 font-weight-medium">{{ row.studentName }}</div>
                                    <v-text-field
                                        :model-value="row.comment"
                                        density="compact"
                                        hide-details
                                        label="Kommentar"
                                        :maxlength="1024"
                                        variant="outlined"
                                        :disabled="workSaving"
                                        @update:model-value="setWorkGroupStudentComment(workDialogGroupDetails.groupIndex, row.studentId, $event)" />
                                    <div
                                        v-if="availableWorkGradeInputMode === 'fixed'"
                                        class="course-table-work-grade-choice-field">
                                        <div class="text-caption text-medium-emphasis mb-1">Note</div>
                                        <div class="d-flex flex-wrap ga-1">
                                            <v-chip
                                                v-for="item in availableWorkGradeItems"
                                                :key="`group-work-grade-${workDialogGroupDetails.groupIndex}-${row.studentId}-${item.value}`"
                                                size="small"
                                                :title="item.title"
                                                :variant="row.grade === item.value ? 'flat' : 'tonal'"
                                                :color="row.grade === item.value ? 'success' : 'default'"
                                                :disabled="workSaving"
                                                @click="setWorkGroupStudentGrade(
                                                    workDialogGroupDetails.groupIndex,
                                                    row.studentId,
                                                    toggledCourseWorkGrade(row.grade, item.value),
                                                )">
                                                {{ item.value }}
                                            </v-chip>
                                        </div>
                                    </div>
                                    <v-text-field
                                        v-else-if="['free', 'plus', 'plus_minus', 'points'].includes(availableWorkGradeInputMode)"
                                        :model-value="row.grade"
                                        density="compact"
                                        hide-details="auto"
                                        :hint="gradeInputHint(availableWorkGradeInputMode, workDialogForm.type)"
                                        :inputmode="availableWorkGradeInputMode === 'points' ? 'decimal' : undefined"
                                        persistent-hint
                                        :rules="[value => gradeInputValidation(value, availableWorkGradeInputMode, workDialogForm.type)]"
                                        label="Note"
                                        :maxlength="50"
                                        variant="outlined"
                                        :disabled="workSaving"
                                        @update:model-value="setWorkGroupStudentGrade(workDialogGroupDetails.groupIndex, row.studentId, $event)" />
                                    <div v-else class="text-caption text-medium-emphasis">
                                        Keine Bewertung vorgesehen.
                                    </div>
                                    <div v-if="specialGradeItemsForType(workDialogForm.type).length" class="d-flex flex-wrap ga-1 mt-2">
                                        <v-chip
                                            v-for="item in specialGradeItemsForType(workDialogForm.type)"
                                            :key="item.value"
                                            size="small"
                                            :variant="row.grade === item.value ? 'flat' : 'outlined'"
                                            :color="row.grade === item.value ? 'primary' : undefined"
                                            @click="setWorkGroupStudentGrade(workDialogGroupDetails.groupIndex, row.studentId, toggledCourseWorkGrade(row.grade, item.value))">
                                            {{ item.title }}
                                        </v-chip>
                                    </div>
                                </div>
                            </v-card>
                        </div>
                        <div v-else class="text-body-2 text-medium-emphasis">
                            Dieser Gruppe sind keine Schüler:innen zugeordnet.
                        </div>
                    </div>
                </v-card-text>
                <v-divider />
                <v-card-actions>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" :disabled="workSaving" @click="closeWorkDialogGroupDetails">
                        Schließen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="randomGroupsDialog.open" persistent max-width="420">
            <v-card>
                <v-card-title class="text-subtitle-1 font-weight-bold">Zufällige Gruppen</v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model.number="randomGroupsDialog.groupSize"
                        autofocus
                        data-testid="course-table-random-group-size"
                        type="number"
                        min="2"
                        :max="maxRandomGroupSize"
                        step="1"
                        label="Mitglieder pro Gruppe"
                        :hint="`Mindestens 2, höchstens ${maxRandomGroupSize} Mitglieder.`"
                        persistent-hint
                        @keydown.enter.prevent="confirmRandomGroupSize" />
                    <div v-if="randomGroupSizePreview" class="text-body-2 mt-3">
                        <span class="font-weight-bold">Gruppengrößen:</span>
                        {{ randomGroupSizePreview }}
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeRandomGroupsDialog">Abbrechen</v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        :disabled="!canConfirmRandomGroupSize"
                        @click="confirmRandomGroupSize">
                        Erstellen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteWorkDialog.open" persistent max-width="460">
            <v-card>
                <v-card-title class="text-subtitle-1 font-weight-bold">Arbeit löschen</v-card-title>
                <v-card-text>
                    Die Arbeit <strong>{{ deleteWorkLabel }}</strong> und ihre verknüpften Einträge wirklich löschen?
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn :disabled="workDeleting" variant="text" @click="closeDeleteWorkDialog">Abbrechen</v-btn>
                    <v-btn color="error" :loading="workDeleting" variant="flat" @click="confirmDeleteDateWork">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="bulkAttendanceDialog.open" persistent max-width="460">
            <v-card>
                <v-card-title class="text-subtitle-1 font-weight-bold">
                    {{ bulkAttendanceDialog.present === null ? 'Anwesenheitsspalte zurücksetzen' : 'Anwesenheit setzen' }}
                </v-card-title>
                <v-card-text>
                    Alle Schüler:innen für
                    <strong>{{ bulkAttendanceCourseDateTitle }}</strong>
                    als
                    <strong>{{ bulkAttendanceDialog.present === null ? 'ungeprüft' : bulkAttendanceDialog.present ? 'anwesend' : 'abwesend' }}</strong>
                    markieren?
                    <div v-if="bulkAttendanceDialog.present === null" class="mt-2">
                        Nur die Anwesenheitseinträge und der Anwesenheitsprüfstatus dieses Termins werden zurückgesetzt.
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn
                        :disabled="bulkAttendanceSaving"
                        variant="text"
                        @click="closeBulkAttendanceDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        :color="bulkAttendanceDialog.present === null ? 'primary' : bulkAttendanceDialog.present ? 'success' : 'error'"
                        :loading="bulkAttendanceSaving"
                        variant="flat"
                        @click="confirmBulkAttendance">
                        {{ bulkAttendanceDialog.present === null ? 'Zurücksetzen' : 'Bestätigen' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="entryDialog.open" persistent max-width="860">
            <v-card>
                <v-card-title class="text-subtitle-1 font-weight-bold d-flex align-center ga-2">
                    <v-icon size="20">mdi-format-list-bulleted</v-icon>
                    Einträge
                    <v-chip size="x-small" color="primary" variant="tonal">{{ cellEntries.length }}</v-chip>
                </v-card-title>
                <v-divider />
                <v-card-text class="d-flex flex-column ga-3">
                    <div class="d-flex flex-wrap align-center ga-3 text-body-2" data-testid="course-table-entry-dialog-meta">
                        <div><strong>Schüler:in:</strong> {{ studentName(entryDialog.student) }}</div>
                        <div><strong>Termin:</strong> {{ compactCourseDateTitle(entryDialog.courseDate) }}</div>
                        <div
                            v-if="isAttendanceToggleable(entryDialog.courseDate)"
                            class="d-flex flex-wrap align-center ga-1"
                            role="group"
                            aria-label="Anwesenheit auswählen">
                            <strong class="mr-1">Anwesenheit:</strong>
                            <v-chip
                                v-if="studentAttendanceState(entryDialog.student, entryDialog.courseDate) !== null"
                                class="font-weight-bold mr-2"
                                :color="isStudentPresentForCourseDate(entryDialog.student, entryDialog.courseDate) ? 'success' : 'error'"
                                data-testid="course-table-entry-attendance-status"
                                :prepend-icon="isStudentPresentForCourseDate(entryDialog.student, entryDialog.courseDate) ? 'mdi-check-circle' : 'mdi-close-circle'"
                                role="status"
                                size="small"
                                variant="flat">
                                Aktuell {{ isStudentPresentForCourseDate(entryDialog.student, entryDialog.courseDate)
                                    ? 'anwesend'
                                    : 'abwesend' }}
                            </v-chip>
                            <span class="text-caption text-medium-emphasis mr-1">Ändern:</span>
                            <v-btn
                                color="success"
                                density="compact"
                                prepend-icon="mdi-check"
                                size="small"
                                :variant="studentAttendanceState(entryDialog.student, entryDialog.courseDate) === true ? 'flat' : 'outlined'"
                                :aria-pressed="studentAttendanceState(entryDialog.student, entryDialog.courseDate) === true"
                                :disabled="isAttendanceCellSaving(entryDialog.student, entryDialog.courseDate)"
                                @click="setEntryDialogAttendance(true)">
                                Anwesend
                            </v-btn>
                            <v-btn
                                color="error"
                                density="compact"
                                prepend-icon="mdi-close"
                                size="small"
                                :variant="studentAttendanceState(entryDialog.student, entryDialog.courseDate) === false ? 'flat' : 'outlined'"
                                :aria-pressed="studentAttendanceState(entryDialog.student, entryDialog.courseDate) === false"
                                :disabled="isAttendanceCellSaving(entryDialog.student, entryDialog.courseDate)"
                                @click="setEntryDialogAttendance(false)">
                                Abwesend
                            </v-btn>
                            <v-btn
                                density="compact"
                                size="small"
                                :variant="studentAttendanceState(entryDialog.student, entryDialog.courseDate) === null ? 'flat' : 'outlined'"
                                :aria-pressed="studentAttendanceState(entryDialog.student, entryDialog.courseDate) === null"
                                :disabled="isAttendanceCellSaving(entryDialog.student, entryDialog.courseDate)"
                                @click="setEntryDialogAttendance(null)">
                                Ungeprüft
                            </v-btn>
                        </div>
                    </div>

                    <section>
                        <div class="text-caption font-weight-bold text-medium-emphasis mb-2">Vorhandene Einträge</div>
                        <div v-if="cellEntries.length" class="course-table-cell-entry-list" data-testid="course-table-cell-entry-list">
                            <div
                                v-for="entry in visibleCellEntries"
                                :key="entry.uid"
                                class="course-table-cell-entry course-table-cell-entry--selectable"
                                :class="{
                                    'course-table-cell-entry--selected': selectedCellEntryUid === entry.uid,
                                }"
                                :data-testid="`course-table-cell-entry-card-${entry.uid}`"
                                :aria-expanded="isCellEntryExpanded(entry)"
                                :role="isCellEntryExpanded(entry) ? undefined : 'button'"
                                :tabindex="isCellEntryExpanded(entry) ? undefined : 0"
                                @click="toggleCellEntry(entry)"
                                @keydown.enter.self.prevent="toggleCellEntry(entry)"
                                @keydown.space.self.prevent="toggleCellEntry(entry)">
                                <div
                                    class="course-table-cell-entry-summary d-flex align-center flex-wrap ga-2">
                                    <v-chip size="x-small" :color="cellEntryColor(entry)" variant="tonal">
                                        {{ cellEntryKindLabel(entry) }}
                                    </v-chip>
                                    <strong class="text-body-2">{{ cellEntryTypeLabel(entry) }}</strong>
                                    <v-chip
                                        v-if="entryExpectsProperty(entry)"
                                        size="x-small"
                                        color="success"
                                        variant="tonal">
                                        {{ entry.effective_grade || entry.grade || 'offen' }}
                                    </v-chip>
                                    <v-chip v-if="entry.source === 'course_work'" size="x-small" color="info" variant="outlined">
                                        Aus Arbeit
                                    </v-chip>
                                    <v-chip
                                        v-if="entry.source === 'course_work' && courseWorkForCellEntry(entry)"
                                        size="x-small"
                                        :color="courseWorkForCellEntry(entry).is_group_work ? 'deep-purple' : 'primary'"
                                        :prepend-icon="courseWorkForCellEntry(entry).is_group_work ? 'mdi-account-group' : 'mdi-account-outline'"
                                        variant="tonal">
                                        {{ courseWorkEntryModeTitle(entry) }}
                                    </v-chip>
                                    <span class="ml-auto" @click.stop @keydown.stop>
                                        <v-btn
                                            v-if="canTransferCellEntry(entry)"
                                            color="primary"
                                            size="small"
                                            variant="text"
                                            prepend-icon="mdi-content-copy"
                                            :disabled="entrySaving || entryDeleting || entryNotificationSending || entryTransferSaving || Boolean(courseWorkEntrySavingUid)"
                                            :data-testid="`course-table-transfer-entry-${entry.uid}`"
                                            title="Gespeicherten Eintrag auf weitere Schüler:innen übertragen"
                                            @click.stop="startEntryTransfer(entry)">Auf weitere übertragen</v-btn>
                                        <v-btn
                                            :aria-label="canModifyCellEntry(entry) ? 'Eintrag löschen' : 'Eintrag kann hier nicht gelöscht werden'"
                                            color="error"
                                            :data-testid="`course-table-cell-delete-entry-${entry.uid}`"
                                            density="compact"
                                            icon="mdi-delete"
                                            size="small"
                                            :title="canModifyCellEntry(entry)
                                                ? 'Eintrag löschen'
                                                : 'Einträge aus Arbeiten können nur über die Arbeit gelöscht werden.'"
                                            variant="text"
                                            :disabled="!canModifyCellEntry(entry) || entryDeleting"
                                            @click.stop="openDeleteEntryDialog(entry)" />
                                    </span>
                                </div>
                                <div
                                    v-if="entry.source === 'course_work' && courseWorkEntryPeriod(entry)"
                                    class="d-flex flex-wrap ga-3 mt-2 text-caption text-medium-emphasis"
                                    :data-testid="`course-table-cell-entry-work-period-${entry.uid}`">
                                    <div v-if="courseWorkEntryPeriod(entry).isSameDate">
                                        <strong>Beginn/Ende:</strong> {{ courseWorkEntryPeriod(entry).startDateTitle }}
                                    </div>
                                    <div v-else>
                                        <strong>Beginn:</strong> {{ courseWorkEntryPeriod(entry).startDateTitle }}
                                    </div>
                                    <div
                                        v-if="!courseWorkEntryPeriod(entry).isSameDate && courseWorkEntryPeriod(entry).finishDateTitle">
                                        <strong>Ende:</strong> {{ courseWorkEntryPeriod(entry).finishDateTitle }}
                                    </div>
                                    <div
                                        v-if="!courseWorkEntryPeriod(entry).isSameDate && courseWorkEntryPeriod(entry).durationLabel">
                                        <strong>Dauer:</strong> {{ courseWorkEntryPeriod(entry).durationLabel }}
                                    </div>
                                </div>
                                <div
                                    v-if="cellEntryListComment(entry)"
                                    class="course-table-cell-entry-list-comment d-flex align-start ga-1 mt-2 text-caption text-medium-emphasis">
                                    <v-icon icon="mdi-comment-text-outline" size="14" class="mt-1" />
                                    <span>{{ cellEntryListComment(entry) }}</span>
                                </div>
                                <div
                                    v-if="isCellEntryExpanded(entry) && entry.source === 'course_work' && courseWorkForCellEntry(entry)"
                                    class="course-table-cell-work-entry mt-3"
                                    :data-testid="`course-table-cell-work-entry-${entry.uid}`"
                                    @click.stop>
                                    <div class="course-table-cell-work-info-grid">
                                        <div>
                                            <div class="text-caption text-medium-emphasis">Titel</div>
                                            <div class="text-body-2 font-weight-bold">
                                                {{ courseWorkForCellEntry(entry).title || 'Ohne Titel' }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-caption text-medium-emphasis">Datum</div>
                                            <div class="text-body-2">{{ courseWorkEntryDateTitle(entry) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-caption text-medium-emphasis">Modus</div>
                                            <div class="text-body-2">{{ courseWorkEntryModeTitle(entry) }}</div>
                                        </div>
                                        <div v-if="courseWorkForCellEntry(entry).is_group_work">
                                            <div class="text-caption text-medium-emphasis">Zuordnung</div>
                                            <div class="text-body-2">{{ courseWorkEntryAssignmentTitle(entry) }}</div>
                                            <div
                                                v-if="courseWorkEntryOtherGroupMembers(entry).length"
                                                class="mt-2">
                                                <div class="text-caption text-medium-emphasis">Weitere Gruppenmitglieder</div>
                                                <div class="d-flex flex-wrap ga-1 mt-1">
                                                    <v-tooltip
                                                        v-for="member in courseWorkEntryOtherGroupMembers(entry)"
                                                        :key="member.id"
                                                        content-class="course-table-group-member-tooltip"
                                                        location="top"
                                                        :max-width="360"
                                                        :open-delay="200">
                                                        <template #activator="{ props }">
                                                            <v-chip
                                                                v-bind="props"
                                                                class="course-table-group-member-chip"
                                                                size="x-small"
                                                                variant="tonal">
                                                                {{ member.name }}
                                                            </v-chip>
                                                        </template>
                                                        <div class="course-table-group-member-tooltip-name">
                                                            {{ member.name }}
                                                        </div>
                                                        <div class="course-table-group-member-tooltip-grade">
                                                            <span>Note</span>
                                                            <strong>{{ member.grade || 'Keine Note' }}</strong>
                                                        </div>
                                                        <div class="course-table-group-member-tooltip-comment">
                                                            <span>Kommentar</span>
                                                            <div>{{ member.comment || 'Kein Kommentar' }}</div>
                                                        </div>
                                                    </v-tooltip>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="courseWorkForCellEntry(entry).description" class="mt-3">
                                        <div class="text-caption text-medium-emphasis">Beschreibung der Arbeit</div>
                                        <div class="text-body-2 course-table-cell-work-description">
                                            {{ courseWorkForCellEntry(entry).description }}
                                        </div>
                                    </div>
                                    <v-divider class="my-3" />
                                    <div class="text-subtitle-2 font-weight-bold mb-2">Bewertung für diese:n Schüler:in</div>
                                    <div class="course-table-cell-work-entry-fields">
                                        <v-textarea
                                            :model-value="courseWorkEntryDraft(entry).comment"
                                            auto-grow
                                            :data-testid="`course-table-cell-work-comment-${entry.uid}`"
                                            density="compact"
                                            hide-details
                                            label="Kommentar"
                                            :maxlength="1024"
                                            max-rows="4"
                                            rows="2"
                                            variant="outlined"
                                            :disabled="Boolean(courseWorkEntrySavingUid)"
                                            @update:model-value="updateCourseWorkEntryDraft(entry, 'comment', $event)" />
                                        <div
                                            v-if="courseWorkEntryGradeInputMode(entry) === 'fixed'"
                                            :data-testid="`course-table-cell-work-grade-${entry.uid}`"
                                            class="course-table-work-grade-choice-field">
                                            <div class="text-caption text-medium-emphasis mb-1">Note</div>
                                            <div class="d-flex flex-wrap ga-1">
                                                <v-chip
                                                    v-for="item in courseWorkEntryGradeItems(entry)"
                                                    :key="`cell-work-grade-${entry.uid}-${item.value}`"
                                                    size="small"
                                                    :title="item.title"
                                                    :variant="courseWorkEntryDraft(entry).grade === item.value ? 'flat' : 'tonal'"
                                                    :color="courseWorkEntryDraft(entry).grade === item.value ? 'success' : 'default'"
                                                    :disabled="Boolean(courseWorkEntrySavingUid)"
                                                    @click="updateCourseWorkEntryDraft(
                                                        entry,
                                                        'grade',
                                                        toggledCourseWorkGrade(courseWorkEntryDraft(entry).grade, item.value),
                                                    )">
                                                    {{ item.value }}
                                                </v-chip>
                                            </div>
                                        </div>
                                        <v-text-field
                                            v-else-if="['free', 'plus', 'plus_minus', 'points'].includes(courseWorkEntryGradeInputMode(entry))"
                                            :model-value="courseWorkEntryDraft(entry).grade"
                                            clearable
                                            :data-testid="`course-table-cell-work-grade-${entry.uid}`"
                                            density="compact"
                                            hide-details="auto"
                                            :hint="gradeInputHint(courseWorkEntryGradeInputMode(entry), courseWorkForCellEntry(entry)?.type)"
                                            :inputmode="courseWorkEntryGradeInputMode(entry) === 'points' ? 'decimal' : undefined"
                                            persistent-hint
                                            :rules="[value => gradeInputValidation(value, courseWorkEntryGradeInputMode(entry), courseWorkForCellEntry(entry)?.type)]"
                                            label="Note"
                                            :maxlength="50"
                                            variant="outlined"
                                            :disabled="Boolean(courseWorkEntrySavingUid)"
                                            @update:model-value="updateCourseWorkEntryDraft(entry, 'grade', $event)" />
                                        <div v-else class="text-caption text-medium-emphasis align-self-center">
                                            Für diesen Eintragstyp ist keine Bewertung vorgesehen.
                                        </div>
                                        <div v-if="specialGradeItemsForType(courseWorkForCellEntry(entry)?.type).length" class="d-flex flex-wrap ga-1 mt-2">
                                            <v-chip
                                                v-for="item in specialGradeItemsForType(courseWorkForCellEntry(entry)?.type)"
                                                :key="item.value"
                                                size="small"
                                                :variant="courseWorkEntryDraft(entry).grade === item.value ? 'flat' : 'outlined'"
                                                :color="courseWorkEntryDraft(entry).grade === item.value ? 'primary' : undefined"
                                                @click="updateCourseWorkEntryDraft(entry, 'grade', toggledCourseWorkGrade(courseWorkEntryDraft(entry).grade, item.value))">
                                                {{ item.title }}
                                            </v-chip>
                                        </div>
                                    </div>
                                    <div class="d-flex align-center flex-wrap ga-2 mt-3">
                                        <v-btn
                                            :data-testid="`course-table-cell-work-open-${entry.uid}`"
                                            prepend-icon="mdi-arrow-right-circle-outline"
                                            variant="tonal"
                                            :disabled="Boolean(courseWorkEntrySavingUid)"
                                            @click="openCourseWorkFromCellEntry(entry)">
                                            Zur Arbeit
                                        </v-btn>
                                        <v-spacer />
                                        <v-btn
                                            variant="text"
                                            :disabled="Boolean(courseWorkEntrySavingUid)"
                                            @click="toggleCellEntry(entry)">
                                            Abbrechen
                                        </v-btn>
                                        <v-btn
                                            color="success"
                                            :data-testid="`course-table-cell-work-save-${entry.uid}`"
                                            prepend-icon="mdi-content-save"
                                            variant="flat"
                                            :loading="courseWorkEntrySavingUid === entry.uid"
                                            :disabled="Boolean(courseWorkEntrySavingUid) || !courseWorkEntryHasChanges(entry)"
                                            @click="saveCourseWorkCellEntry(entry)">
                                            Bewertung speichern
                                        </v-btn>
                                    </div>
                                </div>
                                <div
                                    v-if="entryFormOpen && entryForm.uid === entry.uid"
                                    class="course-table-cell-entry-form mt-3"
                                    :data-testid="`course-table-cell-entry-edit-form-${entry.uid}`"
                                    @click.stop>
                                    <div class="text-subtitle-2 font-weight-bold mb-3">Eintrag bearbeiten</div>
                                    <div class="text-caption text-medium-emphasis mb-1">Typ</div>
                                    <div class="mb-3" data-testid="course-table-cell-entry-edit-type">
                                        <v-chip size="small" :color="cellEntryColor(entry)" variant="tonal">
                                            {{ cellEntryTypeLabel(entry) }}
                                        </v-chip>
                                    </div>

                                    <template v-if="selectedEntryTypeCategory === 'Benotung'">
                                        <div class="text-caption text-medium-emphasis mb-1">Note</div>
                                        <div v-if="availableEntryGrades.length" class="d-flex flex-wrap ga-1 mb-3">
                                            <v-btn
                                                v-for="item in availableEntryGrades"
                                                :key="item.value"
                                                size="small"
                                                :variant="entryForm.grade === item.value ? 'flat' : 'tonal'"
                                                :color="entryForm.grade === item.value ? 'success' : 'default'"
                                                @click="entryForm.grade = entryForm.grade === item.value ? '' : item.value">
                                                {{ item.title }}
                                            </v-btn>
                                        </div>
                                        <v-text-field
                                            v-else-if="['free', 'plus', 'plus_minus', 'points'].includes(availableEntryGradeInputMode)"
                                            v-model="entryForm.grade"
                                            label="Wert"
                                            :maxlength="50"
                                            :hint="gradeInputHint(availableEntryGradeInputMode, entryForm.type)"
                                            :inputmode="availableEntryGradeInputMode === 'points' ? 'decimal' : undefined"
                                            persistent-hint
                                            :rules="[value => gradeInputValidation(value, availableEntryGradeInputMode, entryForm.type)]" />
                                        <div v-else class="text-caption text-medium-emphasis mb-3">Keine Bewertung vorgesehen.</div>
                                <div v-if="specialGradeItemsForType(entryForm.type).length" class="d-flex flex-wrap ga-1 mt-2">
                                    <v-chip
                                        v-for="item in specialGradeItemsForType(entryForm.type)"
                                        :key="item.value"
                                        size="small"
                                        :variant="entryForm.grade === item.value ? 'flat' : 'outlined'"
                                        :color="entryForm.grade === item.value ? 'primary' : undefined"
                                        @click="entryForm.grade = toggledCourseWorkGrade(entryForm.grade, item.value)">
                                        {{ item.title }}
                                    </v-chip>
                                </div>
                                        <div v-if="specialGradeItemsForType(entryForm.type).length" class="d-flex flex-wrap ga-1 mt-2">
                                            <v-chip
                                                v-for="item in specialGradeItemsForType(entryForm.type)"
                                                :key="item.value"
                                                size="small"
                                                :variant="entryForm.grade === item.value ? 'flat' : 'outlined'"
                                                :color="entryForm.grade === item.value ? 'primary' : undefined"
                                                @click="entryForm.grade = toggledCourseWorkGrade(entryForm.grade, item.value)">
                                                {{ item.title }}
                                            </v-chip>
                                        </div>
                                    </template>

                                    <v-textarea
                                        v-model="entryForm.description"
                                        label="Beschreibung"
                                        rows="2"
                                        :counter="1024"
                                        :maxlength="1024" />

                                    <section
                                        v-if="entryHasNotificationWorkflow(entry)"
                                        class="course-table-entry-notifications mb-4"
                                        :data-testid="`course-table-entry-notifications-${entry.id}`">
                                        <v-divider class="mb-3" />
                                        <div class="d-flex align-center ga-2 mb-2">
                                            <v-icon icon="mdi-email-check-outline" color="primary" size="20" />
                                            <div class="text-subtitle-2 font-weight-bold">Verständigungen</div>
                                            <v-spacer />
                                            <v-btn
                                                icon="mdi-refresh"
                                                size="x-small"
                                                variant="text"
                                                title="Status aktualisieren"
                                                :loading="entryNotificationLoading"
                                                :disabled="entryNotificationSending"
                                                @click="loadEntryNotificationRecipients(entry)" />
                                        </div>
                                        <div class="text-caption text-medium-emphasis mb-2">
                                            Alle verfügbaren Personen sind standardmäßig ausgewählt.
                                        </div>

                                        <v-progress-linear v-if="entryNotificationLoading" indeterminate color="primary" class="mb-3" />
                                        <v-alert
                                            v-else-if="!entryNotificationRecipients.length"
                                            type="warning"
                                            variant="tonal"
                                            density="compact">
                                            Es wurden keine Empfänger:innen gefunden.
                                        </v-alert>
                                        <div v-else class="d-flex flex-column ga-1">
                                            <div
                                                v-for="recipient in entryNotificationRecipients"
                                                :key="recipient.key"
                                                class="d-flex align-start ga-2">
                                                <v-checkbox
                                                    v-model="selectedEntryNotificationRecipientKeys"
                                                    class="flex-grow-1"
                                                    :value="recipient.key"
                                                    multiple
                                                    color="primary"
                                                    density="compact"
                                                    hide-details
                                                    :disabled="!recipient.available || Boolean(recipient.informed_at) || entryNotificationSending">
                                                    <template #label>
                                                        <div class="course-table-entry-notification-label py-1">
                                                            <div class="d-flex align-center flex-wrap ga-2">
                                                                <strong>{{ recipient.group_label }}</strong>
                                                                <span>{{ recipient.recipient_label }}</span>
                                                                <span v-if="recipient.email" class="text-medium-emphasis">{{ recipient.email }}</span>
                                                            </div>
                                                            <div class="d-flex align-center flex-wrap ga-1 mt-1">
                                                                <v-chip
                                                                    v-if="recipient.informed_at"
                                                                    size="x-small"
                                                                    color="info"
                                                                    variant="tonal">
                                                                    Informiert: {{ formatNotificationDateTime(recipient.informed_at) }}
                                                                </v-chip>
                                                                <v-chip v-else size="x-small" variant="tonal">
                                                                    Noch nicht informiert
                                                                </v-chip>
                                                                <v-chip
                                                                    v-if="recipient.opened_at"
                                                                    size="x-small"
                                                                    color="success"
                                                                    variant="tonal">
                                                                    E-Mail geöffnet: {{ formatNotificationDateTime(recipient.opened_at) }}
                                                                </v-chip>
                                                                <v-chip
                                                                    v-if="recipient.confirmed_at"
                                                                    size="x-small"
                                                                color="success"
                                                                variant="tonal">
                                                                {{ notificationConfirmationLabel(recipient) }}:
                                                                {{ formatNotificationDateTime(recipient.confirmed_at) }}
                                                                </v-chip>
                                                                <v-chip
                                                                    v-else-if="recipient.informed_at"
                                                                    size="x-small"
                                                                    color="warning"
                                                                    variant="tonal">
                                                                    Noch nicht bestätigt
                                                                </v-chip>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </v-checkbox>
                                                <v-btn
                                                    v-if="recipient.notification_id && recipient.informed_at && !recipient.confirmed_at"
                                                    class="mt-2"
                                                    color="success"
                                                    prepend-icon="mdi-check"
                                                    size="x-small"
                                                    variant="tonal"
                                                    :loading="entryNotificationConfirmingId === recipient.notification_id"
                                                    :disabled="entryNotificationSending || (
                                                        entryNotificationConfirmingId !== null
                                                        && entryNotificationConfirmingId !== recipient.notification_id
                                                    )"
                                                    @click="confirmEntryNotificationManually(entry, recipient)">
                                                    Manuell bestätigen
                                                </v-btn>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-end mt-2">
                                            <v-btn
                                                color="primary"
                                                prepend-icon="mdi-email-send-outline"
                                                variant="tonal"
                                                :loading="entryNotificationSending"
                                                :disabled="entryNotificationLoading || !selectedEntryNotificationRecipientKeys.length"
                                                @click="sendEntryNotificationEmails(entry)">
                                                Per E-Mail informieren
                                            </v-btn>
                                        </div>
                                    </section>

                                    <div class="d-flex align-center ga-2">
                                        <v-spacer />
                                        <v-btn variant="text" :disabled="entrySaving" @click="toggleCellEntry(entry)">Abbrechen</v-btn>
                                        <v-btn
                                            color="success"
                                            variant="flat"
                                            :loading="entrySaving"
                                            :disabled="!canSaveCellEntry"
                                            @click="saveCellEntry">
                                            Speichern
                                        </v-btn>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <v-alert v-else type="info" variant="tonal" density="compact">
                            In dieser Zelle sind noch keine Einträge vorhanden.
                        </v-alert>
                    </section>

                    <v-divider v-if="!entryForm.id" />

                    <section v-if="!entryForm.id">
                        <div class="text-caption font-weight-bold text-medium-emphasis mb-2">Mögliche Aktionen</div>
                        <v-btn
                            v-if="!entryFormOpen && !selectedCellEntryUid"
                            data-testid="course-table-cell-add-entry"
                            color="primary"
                            prepend-icon="mdi-plus"
                            variant="tonal"
                            :disabled="!canCreateCellEntry"
                            @click="startNewCellEntry">
                            Neuen Eintrag hinzufügen
                        </v-btn>

                        <v-alert v-if="!registeredEntryStudentId" type="warning" variant="tonal" density="compact" class="mt-2">
                            Für importierte Schüler:innen ohne Benutzerkonto können keine Einträge angelegt werden.
                        </v-alert>
                        <v-alert v-else-if="!availableEntryTypes.length" type="warning" variant="tonal" density="compact" class="mt-2">
                            Für dieses Fach sind keine Eintragstypen konfiguriert.
                        </v-alert>

                        <div v-if="entryFormOpen" class="course-table-cell-entry-form" data-testid="course-table-cell-entry-form">
                            <div class="text-subtitle-2 font-weight-bold mb-3">
                                Neuen Eintrag anlegen
                            </div>
                            <div class="text-caption text-medium-emphasis mb-1">Typ</div>
                            <div class="course-table-entry-type-rows mb-3">
                                <div
                                    v-for="group in visibleEntryTypeGroups"
                                    :key="group.category"
                                    class="course-table-entry-type-row"
                                    :data-category="group.category">
                                    <div class="course-table-entry-type-category">{{ group.category }}</div>
                                    <div class="d-flex flex-wrap ga-1">
                                        <v-btn
                                            v-for="item in group.items"
                                            :key="item.value"
                                            size="small"
                                            :variant="entryForm.type === item.value ? 'flat' : 'tonal'"
                                            :color="entryTypeCategoryColor(group.category)"
                                            @click="selectCellEntryType(item.value)">
                                            {{ item.title }}
                                        </v-btn>
                                    </div>
                                </div>
                            </div>

                            <template v-if="selectedEntryTypeCategory === 'Benotung'">
                                <div class="text-caption text-medium-emphasis mb-1">Note</div>
                                <div v-if="availableEntryGrades.length" class="d-flex flex-wrap ga-1 mb-3">
                                    <v-btn
                                        v-for="item in availableEntryGrades"
                                        :key="item.value"
                                        size="small"
                                        :variant="entryForm.grade === item.value ? 'flat' : 'tonal'"
                                        :color="entryForm.grade === item.value ? 'success' : 'default'"
                                        @click="entryForm.grade = entryForm.grade === item.value ? '' : item.value">
                                        {{ item.title }}
                                    </v-btn>
                                </div>
                                <v-text-field
                                    v-else-if="['free', 'plus', 'plus_minus', 'points'].includes(availableEntryGradeInputMode)"
                                    v-model="entryForm.grade"
                                    label="Wert"
                                    :maxlength="50"
                                    :hint="gradeInputHint(availableEntryGradeInputMode, entryForm.type)"
                                    :inputmode="availableEntryGradeInputMode === 'points' ? 'decimal' : undefined"
                                    persistent-hint
                                    :rules="[value => gradeInputValidation(value, availableEntryGradeInputMode, entryForm.type)]" />
                                <div v-else class="text-caption text-medium-emphasis mb-3">Keine Bewertung vorgesehen.</div>
                            </template>

                            <v-textarea
                                v-model="entryForm.description"
                                label="Beschreibung"
                                rows="2"
                                :counter="1024"
                                :maxlength="1024" />

                            <section
                                v-if="entryHasNotificationWorkflow(entryForm)"
                                class="course-table-entry-notifications mb-4"
                                data-testid="course-table-new-entry-notification-recipients">
                                <v-divider class="mb-3" />
                                <div class="d-flex align-center ga-2 mb-2">
                                    <v-icon icon="mdi-email-check-outline" color="primary" size="20" />
                                    <div class="text-subtitle-2 font-weight-bold">Jetzt per E-Mail informieren</div>
                                </div>
                                <div class="text-caption text-medium-emphasis mb-2">
                                    Wähle die gewünschten Personen aus. Beim Speichern werden die E-Mails direkt versendet.
                                </div>

                                <v-progress-linear v-if="entryNotificationLoading" indeterminate color="primary" class="mb-3" />
                                <v-alert
                                    v-else-if="!entryNotificationRecipients.length"
                                    type="warning"
                                    variant="tonal"
                                    density="compact">
                                    Es wurden keine Empfänger:innen gefunden.
                                </v-alert>
                                <div v-else class="d-flex flex-column ga-1">
                                    <v-checkbox
                                        v-for="recipient in entryNotificationRecipients"
                                        :key="recipient.key"
                                        v-model="selectedEntryNotificationRecipientKeys"
                                        :value="recipient.key"
                                        multiple
                                        color="primary"
                                        density="compact"
                                        hide-details
                                        :disabled="!recipient.available || entrySaving">
                                        <template #label>
                                            <div class="course-table-entry-notification-label py-1">
                                                <div class="d-flex align-center flex-wrap ga-2">
                                                    <strong>{{ recipient.group_label }}</strong>
                                                    <span>{{ recipient.recipient_label }}</span>
                                                    <span v-if="recipient.email" class="text-medium-emphasis">
                                                        {{ recipient.email }}
                                                    </span>
                                                </div>
                                            </div>
                                        </template>
                                    </v-checkbox>
                                </div>
                            </section>

                            <div class="d-flex justify-end ga-2">
                                <v-btn variant="text" :disabled="entrySaving" @click="cancelNewCellEntry">Abbrechen</v-btn>
                                <v-btn
                                    color="success"
                                    variant="flat"
                                    :loading="entrySaving"
                                    :disabled="!canSaveCellEntry || entryNotificationLoading"
                                    @click="saveCellEntry">
                                    {{ selectedEntryNotificationRecipientKeys.length
                                        ? 'Speichern & E-Mail senden'
                                        : 'Speichern' }}
                                </v-btn>
                            </div>
                        </div>
                    </section>
                </v-card-text>
                <v-card-actions v-if="!entryFormOpen && !selectedCellEntryUid">
                    <v-spacer />
                    <v-btn
                        variant="flat"
                        color="primary"
                        :disabled="entrySaving || Boolean(courseWorkEntrySavingUid)"
                        @click="closeEntryDialog">
                        Schließen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteEntryDialog.open" persistent max-width="460">
            <v-card>
                <v-card-title class="text-subtitle-1 font-weight-bold">Eintrag löschen</v-card-title>
                <v-card-text>
                    Den Eintrag <strong>{{ deleteEntryLabel }}</strong> wirklich löschen?
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn :disabled="entryDeleting" variant="text" @click="closeDeleteEntryDialog">Abbrechen</v-btn>
                    <v-btn
                        color="error"
                        :loading="entryDeleting"
                        variant="flat"
                        @click="confirmDeleteCellEntry">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <CourseStudentNotes ref="studentNotes" :course="selected_course" />
    </ItsGridBox>
</template>

<script>
import { defineAsyncComponent } from 'vue'
import CourseStudentNotes from './CourseStudentNotes.vue'
import CourseStudentIndicators from './CourseStudentIndicators.vue'
import axios from 'axios'
import { mapWritableState } from 'pinia'
import { courseOverviewPdf } from '@/actions/App/Http/Controllers/Admin/Teaching/TeachingCourseController'
import { setCurriculumFileVisibility as curriculumFileVisibility } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseDateController'
import { parseLocalDate } from '@/helpers/date'
import { isInTeachingSemester } from '@/helpers/teachingSemester'
import { requiresWorkMaximumPlus, workMaximumPlusError } from '@/helpers/teachingWorkMaximum'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'
import { useCurriculumStore } from '@/stores/admin/teaching/CurriculumStore'

const tableMarkingColors = new Set(['blue', 'green', 'orange', 'purple', 'red'])
const ItsRichTextEditor = defineAsyncComponent(() => import('@/components/ItsRichTextEditor.vue'))
const CurriculumPdfPreview = defineAsyncComponent(() => import('@/pages/admin/teaching/curricula/CurriculumPdfPreview.vue'))
const courseContentAllowedTags = new Set([
    'b', 'blockquote', 'br', 'code', 'del', 'div', 'em', 'h1', 'h2', 'h3', 'hr', 'i', 'li', 'ol', 'p', 'pre',
    's', 'strike', 'strong', 'sub', 'sup', 'u', 'ul',
])
const courseContentBlockedTags = new Set([
    'button', 'embed', 'form', 'iframe', 'input', 'link', 'math', 'meta', 'object', 'script', 'select', 'style',
    'svg', 'textarea',
])

export default {
    components: { ItsRichTextEditor, CourseStudentNotes, CourseStudentIndicators, CurriculumPdfPreview },

    emits: ['update:activeSemester', 'manage-curriculum'],

    props: {
        activeSemester: {
            type: Number,
            default: 3,
            validator: (value) => [1, 2, 3].includes(value),
        },
        semesterTwoStartDate: {
            type: String,
            default: null,
        },
        view: {
            type: String,
            default: 'entries',
            validator: (value) => ['attendance', 'entries'].includes(value),
        },
    },

    data() {
        return {
            bulkAttendanceDialog: {
                courseDate: null,
                open: false,
                present: true,
            },
            bulkAttendanceSaving: false,
            behaviourEntryStore: null,
            courseDateStore: null,
            courseStore: null,
            curriculumDialog: {
                courseDate: null,
                curriculum: null,
                loading: false,
                open: false,
            },
            curriculumDialogRequestId: 0,
            curriculumFileError: '',
            curriculumFilePending: null,
            curriculumFilePreview: null,
            curriculumStore: null,
            curriculumUnitActionKey: null,
            courseEntriesRequestPromise: null,
            courseTableDataCourseId: null,
            courseTableDataRequestCourseId: null,
            courseTableDataRequestPromise: null,
            courseWorkStore: null,
            courseWorkEntryDrafts: {},
            courseWorkEntrySavingUid: null,
            courseWorksRequestPromise: null,
            contentDialog: {
                content: '',
                courseDate: null,
                open: false,
            },
            contentSaving: false,
            deleteEntryDialog: {
                entry: null,
                open: false,
            },
            deleteWorkDialog: {
                open: false,
                work: null,
            },
            entryDialog: {
                courseDate: null,
                open: false,
                student: null,
            },
            entryForm: {
                description: '',
                doneDate: null,
                grade: '',
                id: null,
                kind: 'assessment',
                dueDate: null,
                type: '',
                uid: null,
            },
            entryFormOpen: false,
            entryDeleting: false,
            entrySaving: false,
            entryTransfer: null,
            entryTransferSaving: false,
            entryTransferError: '',
            entryTransferDisposed: false,
            entryStore: null,
            entryNotificationConfirmingId: null,
            entryNotificationLoading: false,
            entryNotificationRequestId: 0,
            entryNotificationRecipients: [],
            entryNotificationSending: false,
            selectedCellEntryUid: null,
            selectedEntryNotificationRecipientKeys: [],
            randomGroupsDialog: {
                groupSize: 2,
                open: false,
            },
            savingAttendanceCells: {},
            tableView: 'entries',
            hoveredCourseWorkTimelineKeys: [],
            workDeleting: false,
            workDialog: {
                courseDate: null,
                open: false,
            },
            workDialogDateEditing: false,
            workDialogFinishDateEditing: false,
            workDialogForm: {
                maximum_plus: null,
                date_for_all_groups: '',
                description: '',
                finish_until_date: '',
                groups: [],
                group_size: null,
                id: null,
                is_group_work: false,
                is_random_groups: false,
                status: [],
                teaching_course_id: null,
                title: '',
                type: '',
            },
            workDialogFormOpen: false,
            workDialogGroupDetails: {
                groupIndex: null,
                open: false,
            },
            workDialogGroupDateEditingIndex: null,
            workDialogGroupDateMenuOpen: false,
            workDialogModeDraft: false,
            workDialogModeEditing: false,
            workDialogStudentDrag: {
                sourceGroupIndex: null,
                studentId: null,
                targetGroupIndex: null,
            },
            workDialogTab: 'work',
            workDialogTypeDraft: '',
            workDialogTypeEditing: false,
            workSaving: false,
        }
    },

    beforeMount() {
        this.restoreTableView(this.view)
        this.behaviourEntryStore = useCourseBehaviourEntryStore()
        this.courseStore = useCourseStore()
        this.curriculumStore = useCurriculumStore()
        this.entryStore = useCourseStudentEntryStore()
        this.courseWorkStore = useCourseWorkStore()
    },

    async mounted() {
        this.courseDateStore = useCourseDateStore()
        if (this.tableView === 'entries') {
            await this.loadCourseTableData()
        } else {
            await this.loadCourseWorks()
        }
        this.scrollToInitialCourseDate()
    },

    beforeUnmount() {
        this.entryTransferDisposed = true
        this.closeCurriculumFilePreview()
    },

    watch: {
        async view(view) {
            this.entryTransfer = null
            this.restoreTableView(view)
            if (this.tableView === 'entries') {
                await this.loadCourseTableData()
            } else {
                await this.loadCourseWorks()
            }
        },
        courseDateScrollSignature() {
            this.scrollToInitialCourseDate()
        },
        activeSemester() {
            this.entryTransfer = null
        },
        'selected_course.id'() {
            this.entryTransfer = null
        },
        selected_course(course) {
            this.closeCurriculumDialog()
            if (course?.id && this.tableView === 'entries') {
                this.loadCourseTableData(course.id)
            } else if (course?.id) {
                this.loadCourseWorks(course.id)
            }
        },
        'workDialogForm.is_group_work'(isGroupWork, wasGroupWork) {
            if (
                isGroupWork
                && !wasGroupWork
                && this.isGeneratedEmptyIndividualWorkGroups(this.workDialogForm.groups)
            ) {
                this.workDialogForm.groups = []
            }

            if (
                (isGroupWork && this.workDialogTab === 'students')
                || (!isGroupWork && this.workDialogTab === 'groups')
            ) {
                this.workDialogTab = 'work'
            }
        },
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action']),
        ...mapWritableState(useCourseDateStore, ['selected_courseDate']),
        ...mapWritableState(useCourseStore, ['selected_course', 'students_sort_mode', 'uses_entry_areas_for_grading_schema']),
        ...mapWritableState(useCourseWorkStore, ['courseWorks']),
        selectedSemester: {
            get() {
                return this.activeSemester
            },
            set(value) {
                this.$emit('update:activeSemester', Number(value))
            },
        },
        courseDateScrollSignature() {
            const courseId = this.selected_course?.id || ''
            const courseDates = this.sortedCourseDates
                .map((courseDate) => `${courseDate?.id || ''}:${this.normalizeDateKey(courseDate?.date) || ''}`)

            return [courseId, ...courseDates].join('|')
        },
        sortedCourseDates() {
            const dates = Array.isArray(this.selected_course?.course_dates) ? [...this.selected_course.course_dates] : []
            const sortedDates = dates.sort((first, second) => {
                const firstDate = String(first?.date || '')
                const secondDate = String(second?.date || '')
                const dateComparison = firstDate.localeCompare(secondDate)
                if (dateComparison !== 0) return dateComparison

                return Number(first?.id || 0) - Number(second?.id || 0)
            })
            return sortedDates.filter((courseDate) => isInTeachingSemester(courseDate?.date, this.activeSemester, this.semesterTwoStartDate))
        },
        hasAssignedCurriculum() {
            return this.assignedCurriculumId !== null
        },
        assignedCurriculumId() {
            const curriculumId = Number(
                this.selected_course?.teaching_curriculum_id
                || this.selected_course?.teaching_curriculum?.id,
            )

            return Number.isFinite(curriculumId) && curriculumId > 0 ? curriculumId : null
        },
        assignedCurriculumTitle() {
            return this.selected_course?.teaching_curriculum?.title || 'Curriculum'
        },
        curriculumDialogTitle() {
            return this.curriculumDialog.curriculum?.title
                || this.selected_course?.teaching_curriculum?.title
                || 'Curriculum'
        },
        curriculumDialogCourseDateTitle() {
            const value = this.curriculumDialog.courseDate?.date
            if (!value) return ''
            const date = parseLocalDate(value)
            if (Number.isNaN(date.getTime())) return ''

            return date.toLocaleDateString('de-AT', { weekday: 'long', day: 'numeric', month: 'numeric', year: 'numeric' })
        },
        contentDialogCurriculumContent() {
            return this.curriculumContentForCourseDate(this.contentDialog.courseDate)
        },
        contentDialogCourseDateIndex() {
            if (!this.contentDialog.courseDate) return -1

            const selectedCourseDateKey = this.courseDateScrollKey(this.contentDialog.courseDate)

            return this.sortedCourseDates.findIndex(
                (courseDate) => this.courseDateScrollKey(courseDate) === selectedCourseDateKey
            )
        },
        hasPreviousContentDialogDate() {
            return this.contentDialogCourseDateIndex > 0
        },
        hasNextContentDialogDate() {
            const currentIndex = this.contentDialogCourseDateIndex

            return currentIndex >= 0 && currentIndex < this.sortedCourseDates.length - 1
        },
        courseWorkTimelineDestinationIndexes() {
            const destinationIndexes = new Map()

            this.sortedCourseDates.forEach((courseDate) => {
                this.courseWorksForDate(courseDate).forEach((assignment, destinationIndex) => {
                    destinationIndexes.set(String(assignment.id), destinationIndex)
                })
            })

            return destinationIndexes
        },
        unmatchedCurriculumTitles() {
            const currentTitles = new Set(this.curriculumDialogTopics.flatMap((topic) =>
                topic.units.map((unit) => this.curriculumUnitTitle(topic, unit)),
            ))
            const materials = this.curriculumDialog.courseDate?.adopted_materials

            return [...new Set((Array.isArray(materials) ? materials : [])
                .map((material) => String(material?.title || '').trim())
                .filter((title) => title && !currentTitles.has(title)))]
        },
        curriculumPlannedDatesByTitle() {
            const plannedDates = new Map()
            const dates = Array.isArray(this.selected_course?.course_dates) ? [...this.selected_course.course_dates] : []
            dates.sort((first, second) => String(first.date || '').localeCompare(String(second.date || ''))
                || Number(first.id) - Number(second.id))

            dates.forEach((courseDate) => {
                const materials = Array.isArray(courseDate.adopted_materials) ? courseDate.adopted_materials : []
                const titles = new Set(materials.map((material) => String(material?.title || '').trim()).filter(Boolean))
                titles.forEach((title) => {
                    if (!plannedDates.has(title)) plannedDates.set(title, [])
                    plannedDates.get(title).push(courseDate)
                })
            })

            return plannedDates
        },
        curriculumDialogTopics() {
            const topics = Array.isArray(this.curriculumDialog.curriculum?.topics)
                ? this.curriculumDialog.curriculum.topics
                : []

            return topics.map((topic, topicIndex) => {
                const topicMaterials = Array.isArray(topic?.materials) ? topic.materials : []

                return {
                    key: topic?.id || `topic-${topicIndex}`,
                    title: String(topic?.title || '').trim() || `Thema ${topicIndex + 1}`,
                    units: (Array.isArray(topic?.units) ? topic.units : []).map((unit, unitIndex) => ({
                        files: Array.isArray(this.curriculumDialog.curriculum?.unit_files?.[topic?.id]?.[unit?.id])
                            ? this.curriculumDialog.curriculum.unit_files[topic.id][unit.id]
                            : [],
                        isExam: Boolean(unit?.is_exam),
                        key: unit?.id || `unit-${topicIndex}-${unitIndex}`,
                        materials: [...topicMaterials, ...(Array.isArray(unit?.materials) ? unit.materials : [])]
                            .filter((material, materialIndex, materials) => (
                                material?.id
                                && materials.findIndex((candidate) => Number(candidate?.id) === Number(material.id)) === materialIndex
                            )),
                        title: String(unit?.title || '').trim() || `Einheit ${unitIndex + 1}`,
                    })),
                }
            })
        },
        sortedSelectedStudents() {
            const students = Array.isArray(this.selected_course?.students_info) ? [...this.selected_course.students_info] : []

            return students
                .filter((student) => !this.isStudentCanceled(student))
                .sort((first, second) => this.compareStudentsBySelectedSort(first, second))
        },
        tableColumnCount() {
            return this.sortedCourseDates.length + 1
        },
        bulkAttendanceCourseDateTitle() {
            return this.bulkAttendanceDialog.courseDate
                ? this.compactCourseDateTitle(this.bulkAttendanceDialog.courseDate)
                : ''
        },
        cellEntries() {
            return this.entriesForCell(this.entryDialog.student, this.entryDialog.courseDate)
        },
        visibleCellEntries() {
            if (!this.selectedCellEntryUid) return this.cellEntries

            const selectedEntry = this.cellEntries.find((entry) => entry.uid === this.selectedCellEntryUid)

            return selectedEntry ? [selectedEntry] : this.cellEntries
        },
        selectedTeachingSchema() {
            return this.selected_course?.teacher_teaching_schema || null
        },
        availableEntryTypes() {
            return this.availableEntryTypeGroups.flatMap((group) => group.items)
        },
        availableEntryTypeGroups() {
            if (this.entryForm.kind !== 'assessment') {
                const definitions = this.entryForm.kind === 'notification'
                    ? this.selected_course?.teacher_teaching_notifications
                    : this.selected_course?.teacher_teaching_behaviour
                const category = this.entryForm.kind === 'notification' ? 'Verständigung' : 'Verhalten'
                const items = (Array.isArray(definitions) ? definitions : [])
                    .filter((definition) => definition?.short_name)
                    .map((definition) => ({
                        title: definition.name ? `${definition.short_name} - ${definition.name}` : definition.short_name,
                        value: definition.short_name,
                    }))

                return items.length ? [{ category, items }] : []
            }

            const assignedEntryArea = this.selected_course?.teaching_entry_area || null
            if (!this.uses_entry_areas_for_grading_schema || !assignedEntryArea?.id) {
                return this.availableWorkTypes.length
                    ? [{ category: 'Benotung', items: this.availableWorkTypes }]
                    : []
            }

            const definitions = Array.isArray(assignedEntryArea.entry_definitions)
                ? assignedEntryArea.entry_definitions
                : []

            return ['Benotung', 'Verhalten', 'Weitere']
                .map((category) => ({
                    category,
                    items: definitions
                        .filter((definition) => definition?.category === category && definition?.short_name)
                        .map((definition) => ({
                            title: definition.name
                                ? `${definition.short_name} - ${definition.name}`
                                : definition.short_name,
                            value: definition.short_name,
                        })),
                }))
                .filter((group) => group.items.length)
        },
        selectedEntryTypeCategory() {
            const selectedGroup = this.availableEntryTypeGroups
                .find((group) => group.items.some((item) => item.value === this.entryForm.type))

            return selectedGroup?.category || null
        },
        visibleEntryTypeGroups() {
            if (!this.entryForm.type || !this.selectedEntryTypeCategory) {
                return this.availableEntryTypeGroups
            }

            return this.availableEntryTypeGroups
                .filter((group) => group.category === this.selectedEntryTypeCategory)
        },
        availableEntryGrades() {
            if (this.selectedEntryTypeCategory !== 'Benotung') return []

            return this.courseWorkGradeConfigurationForType(this.entryForm.type).items
        },
        availableEntryGradeInputMode() {
            if (this.selectedEntryTypeCategory !== 'Benotung') return 'none'
            return this.courseWorkGradeInputModeForType(this.entryForm.type)
        },
        availableWorkTypes() {
            const assignedEntryArea = this.selected_course?.teaching_entry_area || null
            const entryDefinitions = Array.isArray(assignedEntryArea?.entry_definitions)
                ? assignedEntryArea.entry_definitions.filter((entry) => entry?.category === 'Benotung')
                : []
            const works = this.uses_entry_areas_for_grading_schema && assignedEntryArea?.id
                ? entryDefinitions
                : Array.isArray(this.selectedTeachingSchema?.works) ? this.selectedTeachingSchema.works : []

            return works
                .filter((work) => work?.short_name)
                .map((work) => ({
                    title: work.name ? `${work.short_name} - ${work.name}` : work.short_name,
                    value: work.short_name,
                }))
        },
        availableWorkGradeItems() {
            return this.courseWorkGradeConfigurationForType(this.workDialogForm.type).items
        },
        availableWorkGradeInputMode() {
            return this.courseWorkGradeConfigurationForType(this.workDialogForm.type).mode
        },
        dateWorkAssignments() {
            return this.courseWorksForDate(this.workDialog.courseDate)
        },
        workDialogGroups() {
            return Array.isArray(this.workDialogForm.groups) ? this.workDialogForm.groups : []
        },
        individualWorkGradeRows() {
            return this.sortedSelectedStudents.flatMap((student) => {
                const studentId = this.registeredStudentUserId(student) || student?.id
                if (!studentId) return []

                const group = this.workDialogGroups.find((workGroup) => (
                    Array.isArray(workGroup?.student_ids)
                    && workGroup.student_ids.some((groupStudentId) => String(groupStudentId) === String(studentId))
                ))

                return [{
                    comment: group ? this.workGroupStudentComment(group, studentId) : '',
                    grade: group ? this.workGroupStudentGrade(group, studentId) : '',
                    studentId,
                    studentName: this.studentName(student),
                }]
            })
        },
        workDialogGroupDetailsGroup() {
            const groupIndex = this.workDialogGroupDetails.groupIndex

            return Number.isInteger(groupIndex) ? this.workDialogGroups[groupIndex] || null : null
        },
        workDialogCourseDateKeys() {
            return this.sortedCourseDates
                .filter((courseDate) => courseDate?.date)
                .map((courseDate) => this.normalizeDateKey(courseDate?.date))
                .filter(Boolean)
        },
        workDialogDateTitle() {
            const date = this.workDialogFormOpen
                ? this.normalizeDateKey(this.workDialogForm.date_for_all_groups)
                : this.normalizeDateKey(this.workDialog.courseDate?.date)

            return date ? this.compactCourseDateTitle({ date }) : 'Ohne Datum'
        },
        workDialogFinishDateTitle() {
            const date = this.normalizeDateKey(this.workDialogForm.finish_until_date)

            return date ? this.compactCourseDateTitle({ date }) : 'Ohne Frist'
        },
        workDialogDurationDays() {
            if (!this.workDialogFormOpen) return null

            return this.calendarDaysBetween(
                this.workDialogForm.date_for_all_groups,
                this.workDialogForm.finish_until_date,
            )
        },
        workDialogDurationLabel() {
            if (!Number.isInteger(this.workDialogDurationDays)) return ''

            return this.workDialogDurationDays === 1
                ? '1 Tag'
                : `${this.workDialogDurationDays} Tage`
        },
        dateWorkRequiresMaximumPlus() {
            return requiresWorkMaximumPlus(this.selected_course, this.workDialogForm.type)
        },
        dateWorkMaximumPlusError() {
            return requiresWorkMaximumPlus(this.selected_course, this.workDialogForm.type) ? workMaximumPlusError(this.workDialogForm.maximum_plus) : ''
        },
        canSaveDateWork() {
            const hasAvailableType = this.availableWorkTypes.some((item) => item.value === this.workDialogForm.type)
            const hasIncompleteNewGroup = this.workDialogForm.is_group_work
                && this.workDialogGroups.some((group) => group?._is_new && !group.student_ids?.length)

            return Boolean(
                hasAvailableType
                && !this.dateWorkMaximumPlusError
                && !hasIncompleteNewGroup
                && !this.workSaving
                && !this.workDialogTypeEditing
                && !this.workDialogModeEditing
            )
        },
        canConfirmDateWorkMode() {
            return Boolean(!this.workSaving && typeof this.workDialogModeDraft === 'boolean')
        },
        canConfirmDateWorkType() {
            return Boolean(
                !this.workSaving
                && this.availableWorkTypes.some((item) => item.value === this.workDialogTypeDraft)
            )
        },
        canConfirmRandomGroupSize() {
            const groupSize = Number(this.randomGroupsDialog.groupSize)

            return Number.isInteger(groupSize) && groupSize >= 2 && groupSize <= this.maxRandomGroupSize
        },
        maxRandomGroupSize() {
            return this.sortedSelectedStudents.length
        },
        hasSavedRandomGroupConfiguration() {
            const groupSize = Number(this.workDialogForm.group_size)

            return Boolean(
                this.workDialogForm.id
                && this.workDialogForm.is_random_groups
                && Number.isInteger(groupSize)
                && groupSize >= 2
            )
        },
        unassignedWorkGroupStudents() {
            const assignedStudentIds = new Set(
                this.workDialogGroups.flatMap((group) => (
                    Array.isArray(group?.student_ids) ? group.student_ids.map((studentId) => String(studentId)) : []
                ))
            )
            const listedStudentIds = new Set()

            return this.sortedSelectedStudents.flatMap((student) => {
                const studentId = this.registeredStudentUserId(student) || student?.id
                if (
                    !studentId
                    || assignedStudentIds.has(String(studentId))
                    || listedStudentIds.has(String(studentId))
                ) {
                    return []
                }

                listedStudentIds.add(String(studentId))

                return [{
                    title: this.studentName(student),
                    value: studentId,
                }]
            })
        },
        hasUnassignedWorkGroupStudents() {
            return this.unassignedWorkGroupStudents.length > 0
        },
        randomGroupSizePreview() {
            if (!this.canConfirmRandomGroupSize) return ''

            return this.randomGroupSizes(
                this.maxRandomGroupSize,
                Number(this.randomGroupsDialog.groupSize)
            ).join(' | ')
        },
        selectedDateWorkTypeValue() {
            if (this.workDialogForm.id && this.workDialogTypeEditing) {
                return this.workDialogTypeDraft
            }

            return this.workDialogForm.type
        },
        selectedDateWorkTypeTitle() {
            const selectedType = this.availableWorkTypes.find((item) => item.value === this.workDialogForm.type)

            return selectedType?.title || this.workDialogForm.type || 'Kein Typ'
        },
        selectedDateWorkModeTitle() {
            return this.workDialogForm.is_group_work ? 'Gruppenarbeit' : 'Einzelarbeit'
        },
        deleteWorkLabel() {
            const work = this.deleteWorkDialog.work
            if (!work) return 'Arbeit'

            return work.title || work.type || 'Arbeit'
        },
        registeredEntryStudentId() {
            return this.registeredStudentUserId(this.entryDialog.student)
        },
        canCreateCellEntry() {
            return Boolean(this.registeredEntryStudentId && this.availableEntryTypes.length)
        },
        canSaveCellEntry() {
            const hasAvailableType = this.availableEntryTypes.some((item) => item.value === this.entryForm.type)
            if ((['plus', 'plus_minus', 'points'].includes(this.availableEntryGradeInputMode) || ['NA', 'VL', 'F'].includes(String(this.entryForm.grade ?? '').trim()))
                && this.gradeInputValidation(this.entryForm.grade, this.availableEntryGradeInputMode, this.entryForm.type) !== true) return false

            return Boolean(this.registeredEntryStudentId && hasAvailableType && !this.entrySaving)
        },
        deleteEntryLabel() {
            return this.deleteEntryDialog.entry ? this.cellEntryTypeLabel(this.deleteEntryDialog.entry) : 'Eintrag'
        },
    },

    methods: {
        openCourseOverviewPdf() {
            if (!this.selected_course?.id) return

            window.open(courseOverviewPdf.url(this.selected_course.id), '_blank', 'noopener')
        },
        async openCurriculumDialog(courseDate) {
            this.curriculumFileError = ''
            const curriculumId = this.assignedCurriculumId
            if (!curriculumId && !courseDate?.adopted_materials?.length) {
                this.$emit('manage-curriculum')
                return
            }
            if (curriculumId && !this.curriculumStore?.show) return

            const requestId = ++this.curriculumDialogRequestId
            this.curriculumDialog = {
                courseDate: courseDate || null,
                curriculum: null,
                loading: true,
                open: true,
            }

            try {
                const curriculum = curriculumId ? await this.curriculumStore.show(curriculumId) : null
                if (requestId !== this.curriculumDialogRequestId) return

                this.curriculumDialog.curriculum = curriculum || null
            } finally {
                if (requestId === this.curriculumDialogRequestId) {
                    this.curriculumDialog.loading = false
                }
            }
        },
        closeCurriculumDialog() {
            if (this.curriculumUnitActionKey) return

            this.curriculumDialogRequestId++
            this.curriculumDialog = {
                courseDate: null,
                curriculum: null,
                loading: false,
                open: false,
            }
        },
        curriculumUnitTitle(topic, unit) {
            return [topic?.title, unit?.title]
                .map((title) => String(title || '').trim())
                .filter(Boolean)
                .join(': ')
        },
        curriculumFileIcon(file) {
            const mimeType = String(file?.mime_type || '').toLowerCase()
            if (mimeType === 'application/pdf') return 'mdi-file-pdf-box'
            if (mimeType.startsWith('image/')) return 'mdi-file-image-outline'

            return 'mdi-file-document-outline'
        },
        async openCurriculumFile(file, preview = false) {
            if (this.curriculumFilePending) return

            this.curriculumFileError = ''
            this.curriculumFilePending = file.id

            try {
                const fileUrl = new URL(preview ? file.preview_url : file.download_url, window.location.origin)
                if (fileUrl.origin !== window.location.origin) throw new Error('Invalid file origin')

                const response = await axios.get(fileUrl.href, {
                    responseType: 'blob',
                    withCredentials: true,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                const blob = response.data

                if (preview) {
                    this.closeCurriculumFilePreview()
                    const contentType = String(response.headers?.['content-type'] || blob.type).split(';')[0].trim()
                    this.curriculumFilePreview = {
                        file,
                        isPdf: contentType === 'application/pdf',
                        url: URL.createObjectURL(new Blob([blob], { type: contentType })),
                    }
                    return
                }

                const objectUrl = URL.createObjectURL(blob)
                const link = document.createElement('a')
                link.href = objectUrl
                link.download = file.name || 'Datei'
                document.body.appendChild(link)
                link.click()
                link.remove()
                window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000)
            } catch (error) {
                const status = error?.response?.status
                this.curriculumFileError = status === 401
                    ? 'Die Sitzung ist abgelaufen. Bitte erneut anmelden.'
                    : status === 403
                        ? 'Keine Berechtigung für diese Datei.'
                        : preview
                            ? 'Die Vorschau konnte nicht geöffnet werden. Bitte versuche den Download.'
                            : 'Die Datei konnte nicht heruntergeladen werden.'
            } finally {
                this.curriculumFilePending = null
            }
        },
        closeCurriculumFilePreview() {
            if (this.curriculumFilePreview) URL.revokeObjectURL(this.curriculumFilePreview.url)
            this.curriculumFilePreview = null
        },
        curriculumDialogUnitActionKey(topic, unit) {
            return `${topic?.key || topic?.title || 'topic'}:${unit?.key || unit?.title || 'unit'}`
        },
        curriculumFileIsStudentVisible(file) {
            return (this.curriculumDialog.courseDate?.adopted_materials || []).some((material) =>
                (material.attachments || []).some((attachment) =>
                    Number(attachment.source_teaching_curriculum_document_id) === Number(file.id)
                    && attachment.student_visible === true,
                ),
            )
        },
        async setCurriculumFileVisibility(file, visible) {
            const courseDate = this.curriculumDialog.courseDate
            if (this.curriculumUnitActionKey || !courseDate?.id || !file?.id) return

            this.curriculumUnitActionKey = `visibility:${file.id}`
            this.curriculumFileError = ''
            try {
                const response = await axios.put(curriculumFileVisibility.url({ course_date: courseDate.id, file: file.id }), {
                    student_visible: visible === true,
                })
                this.applyCurriculumDialogCourseDate(response.data?.data)
            } catch (error) {
                this.curriculumFileError = error?.response?.data?.message || 'Die Freigabe konnte nicht gespeichert werden.'
            } finally {
                this.curriculumUnitActionKey = null
            }
        },
        isCurriculumUnitActionPending(topic, unit) {
            return this.curriculumUnitActionKey === this.curriculumDialogUnitActionKey(topic, unit)
        },
        curriculumUnitPlannedDates(topic, unit) {
            return this.curriculumPlannedDatesByTitle.get(this.curriculumUnitTitle(topic, unit)) || []
        },
        curriculumUnitAdoptedMaterials(topic, unit) {
            const adoptedMaterials = Array.isArray(this.curriculumDialog.courseDate?.adopted_materials)
                ? this.curriculumDialog.courseDate.adopted_materials
                : []
            const curriculumUnitTitle = this.curriculumUnitTitle(topic, unit)

            return adoptedMaterials.filter(
                (material) => String(material?.title || '').trim() === curriculumUnitTitle,
            )
        },
        applyCurriculumDialogCourseDate(updatedCourseDate) {
            if (!updatedCourseDate?.id) return

            this.applyUpdatedCourseDate(updatedCourseDate)
            this.curriculumDialog.courseDate = (Array.isArray(this.selected_course?.course_dates)
                ? this.selected_course.course_dates
                : [])
                .find((courseDate) => Number(courseDate?.id) === Number(updatedCourseDate.id))
                || updatedCourseDate
        },
        async linkCurriculumUnit(topic, unit) {
            const courseDate = this.curriculumDialog.courseDate
            const curriculumUnitTitle = this.curriculumUnitTitle(topic, unit)
            if (
                this.curriculumUnitActionKey
                || !courseDate?.id
                || !curriculumUnitTitle
                || this.isCurriculumUnitLinkedToDialogDate(topic, unit)
            ) {
                return
            }

            this.curriculumUnitActionKey = this.curriculumDialogUnitActionKey(topic, unit)
            try {
                const materialCardIds = [...new Set(
                    (Array.isArray(unit?.materials) ? unit.materials : [])
                        .map((material) => Number(material?.id))
                        .filter((materialId) => Number.isFinite(materialId) && materialId > 0),
                )]
                const endpoint = `/api/admin/teaching/course_dates/${courseDate.id}/adopt-curriculum-content`
                let response = await axios.post(endpoint, {
                    content: curriculumUnitTitle,
                    material_card_ids: materialCardIds,
                })

                if (materialCardIds.length && !response.data?.adopted_materials?.length) {
                    response = await axios.post(endpoint, {
                        content: curriculumUnitTitle,
                        material_card_ids: [],
                    })
                }

                this.applyCurriculumDialogCourseDate(response.data?.data)
            } catch {
                // handled by the global axios interceptor
            } finally {
                this.curriculumUnitActionKey = null
            }
        },
        async unlinkCurriculumUnit(topic, unit) {
            const courseDate = this.curriculumDialog.courseDate
            const adoptedMaterials = this.curriculumUnitAdoptedMaterials(topic, unit)
            if (this.curriculumUnitActionKey || !courseDate?.id || !adoptedMaterials.length) return

            this.curriculumUnitActionKey = this.curriculumDialogUnitActionKey(topic, unit)
            try {
                for (const adoptedMaterial of adoptedMaterials) {
                    if (adoptedMaterial?.id) {
                        await axios.delete(`/api/admin/teaching/course_date_materials/${adoptedMaterial.id}`)
                    }
                }

                const courseId = this.selected_course?.id
                if (courseId && this.courseDateStore?.index) {
                    await this.courseDateStore.index(courseId)
                }

                const refreshedCourseDate = (Array.isArray(this.courseDateStore?.courseDates)
                    ? this.courseDateStore.courseDates
                    : [])
                    .find((candidate) => Number(candidate?.id) === Number(courseDate.id))
                const updatedCourseDate = refreshedCourseDate || {
                    ...courseDate,
                    adopted_materials: (Array.isArray(courseDate.adopted_materials) ? courseDate.adopted_materials : [])
                        .filter((material) => !adoptedMaterials.some(
                            (removedMaterial) => Number(removedMaterial?.id) === Number(material?.id),
                        )),
                }

                this.applyCurriculumDialogCourseDate(updatedCourseDate)
            } catch {
                // handled by the global axios interceptor
            } finally {
                this.curriculumUnitActionKey = null
            }
        },
        curriculumContentForCourseDate(courseDate) {
            const adoptedMaterials = Array.isArray(courseDate?.adopted_materials)
                ? courseDate.adopted_materials
                : []

            return [...new Set(
                adoptedMaterials
                    .map((material) => {
                        const curriculumContent = String(material?.title || '').trim()
                        const topicSeparatorPosition = curriculumContent.indexOf(': ')

                        return topicSeparatorPosition === -1
                            ? curriculumContent
                            : curriculumContent.slice(topicSeparatorPosition + 2).trim()
                    })
                    .filter(Boolean),
            )]
        },
        displayedCurriculumContentForCourseDate(courseDate) {
            return this.curriculumContentForCourseDate(courseDate).slice(0, 3)
        },
        hasAdditionalCurriculumContent(courseDate) {
            return this.curriculumContentForCourseDate(courseDate).length > 3
        },
        isCurriculumUnitLinkedToDialogDate(topic, unit) {
            return this.curriculumUnitAdoptedMaterials(topic, unit).length > 0
        },
        curriculumContentSegments(content) {
            const segments = String(content || '').match(/[^\s/]+\/?/gu) || []

            return segments.map((segment) => ({
                endsWithSeparator: segment.endsWith('/'),
                text: segment,
            }))
        },
        compactCourseDateTitle(courseDate) {
            return [this.courseDateWeekday(courseDate), this.courseDateDateLabel(courseDate)].filter(Boolean).join(', ') || 'Ohne Datum'
        },
        courseDateWeekday(courseDate) {
            return this.getWeekdayShort(courseDate?.date)
        },
        courseDateDateLabel(courseDate) {
            return this.formatDateShort(courseDate?.date) || 'Ohne Datum'
        },
        courseDateHoursLabel(courseDate) {
            const hours = Array.isArray(courseDate?.hours) ? courseDate.hours : []
            const sortedHours = [...new Set(hours.map((hour) => Number(hour)).filter((hour) => Number.isFinite(hour)))]
                .sort((first, second) => first - second)

            if (!sortedHours.length) return ''

            return `${sortedHours.map((hour) => `${hour}.`).join(', ')} Std`
        },
        isFreeCourseDate(courseDate) {
            const statuses = Array.isArray(courseDate?.status) ? courseDate.status : []

            return statuses.some((status) => ['free', 'entfaellt'].includes(status))
        },
        freeCourseDateReason(courseDate) {
            const reason = String(courseDate?.free_reason || '').trim()
            if (reason) return reason

            return courseDate?.status?.includes('entfaellt') ? 'Entfällt' : 'Frei'
        },
        restoreTableView(view) {
            this.tableView = view === 'attendance' ? 'attendance' : 'entries'
        },
        openEntryDialog(student, courseDate) {
            if (this.tableView !== 'entries') return

            this.cancelNewCellEntry()
            this.selectedCellEntryUid = null
            this.entryDialog = {
                courseDate,
                open: true,
                student,
            }
            this.resetCourseWorkEntryDrafts()
        },
        closeEntryDialog() {
            if (this.entrySaving || this.courseWorkEntrySavingUid) return

            this.cancelNewCellEntry()
            this.courseWorkEntryDrafts = {}
            this.selectedCellEntryUid = null
            this.entryDialog = {
                courseDate: null,
                open: false,
                student: null,
            }
        },
        openCourseWorkFromCellEntry(entry) {
            if (this.courseWorkEntrySavingUid) return

            const work = this.courseWorkForCellEntry(entry)
            const courseDate = this.entryDialog.courseDate
            if (!work?.id || !courseDate) return

            this.closeEntryDialog()
            this.openWorkDialog(courseDate)
            this.startEditingDateWork(work)
        },
        async loadCourseEntries(courseId = this.selected_course?.id) {
            if (!courseId || !this.entryStore || !this.behaviourEntryStore) return
            if (this.courseEntriesRequestPromise) return this.courseEntriesRequestPromise

            const requestPromise = Promise.all([
                this.entryStore.indexByCourse(courseId),
                this.behaviourEntryStore.indexByCourse(courseId),
            ])
            this.courseEntriesRequestPromise = requestPromise

            try {
                return await requestPromise
            } finally {
                this.courseEntriesRequestPromise = null
            }
        },
        async loadCourseTableData(courseId = this.selected_course?.id, forceReload = false) {
            if (!courseId || !this.entryStore?.indexTableData || !this.behaviourEntryStore || !this.courseWorkStore) return
            if (!forceReload && String(this.courseTableDataCourseId || '') === String(courseId)) return true
            if (
                this.courseTableDataRequestPromise
                && String(this.courseTableDataRequestCourseId || '') === String(courseId)
            ) {
                if (!forceReload) return this.courseTableDataRequestPromise

                await this.courseTableDataRequestPromise
                if (String(this.selected_course?.id || '') !== String(courseId)) return false
            }

            const requestPromise = this.entryStore.indexTableData(courseId)
            this.courseTableDataRequestCourseId = courseId
            this.courseTableDataRequestPromise = requestPromise

            try {
                const response = await requestPromise
                if (!response || String(this.selected_course?.id || '') !== String(courseId)) return false

                this.entryStore.courseEntries = Array.isArray(response.data) ? response.data : []
                this.behaviourEntryStore.courseEntries = Array.isArray(response.behaviour_entries)
                    ? response.behaviour_entries
                    : []
                this.courseWorkStore.courseWorks = Array.isArray(response.course_works) ? response.course_works : []
                this.courseTableDataCourseId = courseId

                return true
            } finally {
                if (this.courseTableDataRequestPromise === requestPromise) {
                    this.courseTableDataRequestCourseId = null
                    this.courseTableDataRequestPromise = null
                }
            }
        },
        async loadCourseWorks(courseId = this.selected_course?.id) {
            if (!courseId || !this.courseWorkStore?.index) return
            if (this.courseWorksRequestPromise) return this.courseWorksRequestPromise

            const requestPromise = this.courseWorkStore.index(courseId)
            this.courseWorksRequestPromise = requestPromise

            try {
                return await requestPromise
            } finally {
                this.courseWorksRequestPromise = null
            }
        },
        applySavedCourseWork(response) {
            const savedWork = response?.data
            if (!savedWork?.id) return false

            const courseWorks = Array.isArray(this.courseWorks) ? [...this.courseWorks] : []
            const savedWorkIndex = courseWorks.findIndex((work) => String(work?.id) === String(savedWork.id))
            if (savedWorkIndex >= 0) {
                courseWorks.splice(savedWorkIndex, 1, savedWork)
            } else {
                courseWorks.push(savedWork)
            }

            this.courseWorks = courseWorks.sort((first, second) => {
                const dateComparison = String(second?.date_for_all_groups || '')
                    .localeCompare(String(first?.date_for_all_groups || ''))
                if (dateComparison !== 0) return dateComparison

                return Number(second?.id || 0) - Number(first?.id || 0)
            })

            return true
        },
        courseWorksForDate(courseDate) {
            const date = this.normalizeDateKey(courseDate?.date)
            if (!date) return []

            return (this.courseWorks || [])
                .map((work) => {
                    const timeline = this.courseWorkTimeline(work)
                    if (!timeline) {
                        return this.courseWorkAssignmentForDate(work, date)
                    }

                    return timeline.finishDate === date
                        ? this.courseWorkCompletionAssignment(work)
                        : null
                })
                .filter(Boolean)
        },
        courseWorkTimeline(work) {
            const startDate = this.normalizeDateKey(work?.date_for_all_groups)
            const finishDate = this.normalizeDateKey(work?.finish_until_date)
            if (!work?.id || !startDate || !finishDate || finishDate <= startDate) return null

            const visibleDateKeys = (this.sortedCourseDates || [])
                .map((courseDate) => this.normalizeDateKey(courseDate?.date))
                .filter(Boolean)

            if (!visibleDateKeys.includes(startDate) || !visibleDateKeys.includes(finishDate)) return null
            const finishDateIndex = visibleDateKeys.indexOf(finishDate)

            return {
                arrowDate: visibleDateKeys[finishDateIndex - 1],
                finishDate,
                key: `work-timeline-${work.id}`,
                startDate,
                work,
            }
        },
        courseWorkTimelinesForDate(courseDate) {
            const date = this.normalizeDateKey(courseDate?.date)
            if (!date) return []

            return (this.courseWorks || [])
                .map((work) => this.courseWorkTimeline(work))
                .filter((timeline) => (
                    timeline
                    && timeline.startDate <= date
                    && timeline.finishDate > date
                ))
                .map((timeline) => ({
                    ...timeline,
                    destinationIndex: this.courseWorkTimelineDestinationIndexes?.get(String(timeline.work.id)) ?? 0,
                    isArrow: timeline.arrowDate === date,
                    isMiddle: timeline.startDate < date && timeline.arrowDate !== date,
                    isStart: timeline.startDate === date,
                }))
        },
        activateCourseWorkTimelinesForDate(courseDate) {
            const date = this.normalizeDateKey(courseDate?.date)
            if (!date) {
                this.clearHoveredCourseWorkTimelines()
                return
            }

            this.hoveredCourseWorkTimelineKeys = (this.courseWorks || [])
                .map((work) => this.courseWorkTimeline(work))
                .filter((timeline) => (
                    timeline
                    && (timeline.startDate === date || timeline.finishDate === date)
                ))
                .map((timeline) => timeline.key)
        },
        clearHoveredCourseWorkTimelines() {
            this.hoveredCourseWorkTimelineKeys = []
        },
        isCourseWorkTimelineVisible(timeline) {
            return this.hoveredCourseWorkTimelineKeys.includes(timeline?.key)
        },
        courseWorkCompletionAssignment(work) {
            const groups = Array.isArray(work?.groups) ? work.groups : []
            const isGroupWork = Boolean(work?.is_group_work)

            return this.courseWorkAssignmentPayload(
                work,
                isGroupWork ? 'Gruppenarbeit' : 'Einzelarbeit',
                isGroupWork,
                this.courseWorkAffectedStudentCount(groups, !isGroupWork && groups.length === 0)
            )
        },
        courseWorkAssignmentForDate(work, date) {
            if (!work?.id || !date) return null

            if (work.is_group_work) {
                return this.groupWorkAssignmentForDate(work, date)
            }

            const groups = Array.isArray(work.groups) ? work.groups : []
            const fallbackDate = this.normalizeDateKey(work.date_for_all_groups)
            const matchingGroups = groups.filter((group) => (
                (this.normalizeDateKey(group?.date) || fallbackDate) === date
            ))
            const groupDates = groups
                .map((group) => this.normalizeDateKey(group?.date))
                .filter(Boolean)

            if (fallbackDate !== date && !groupDates.includes(date)) {
                return null
            }

            return this.courseWorkAssignmentPayload(
                work,
                'Einzelarbeit',
                false,
                this.courseWorkAffectedStudentCount(matchingGroups, groups.length === 0)
            )
        },
        groupWorkAssignmentForDate(work, date) {
            const groups = Array.isArray(work.groups) ? work.groups : []
            const matchingGroupIndexes = []
            const matchingGroups = []
            const fallbackDate = this.normalizeDateKey(work.date_for_all_groups)
            const hasExplicitGroupDates = groups.some((group) => this.normalizeDateKey(group?.date))

            groups.forEach((group, index) => {
                const groupDate = this.normalizeDateKey(group?.date) || fallbackDate
                if (groupDate === date) {
                    matchingGroupIndexes.push(index + 1)
                    matchingGroups.push(group)
                }
            })

            if (!matchingGroupIndexes.length && (hasExplicitGroupDates || fallbackDate !== date)) {
                return null
            }

            const totalGroups = groups.length
            const suffix = matchingGroupIndexes.length && matchingGroupIndexes.length < totalGroups
                ? `Gr. ${matchingGroupIndexes.join(', ')}`
                : 'Gruppenarbeit'

            return this.courseWorkAssignmentPayload(
                work,
                suffix,
                true,
                this.courseWorkAffectedStudentCount(matchingGroups)
            )
        },
        courseWorkAffectedStudentCount(groups, fallbackToCourse = false) {
            const assignedStudentIds = new Set(
                (Array.isArray(groups) ? groups : []).flatMap((group) => (
                    Array.isArray(group?.student_ids)
                        ? group.student_ids.filter(Boolean).map((studentId) => String(studentId))
                        : []
                ))
            )
            if (assignedStudentIds.size || !fallbackToCourse) return assignedStudentIds.size

            return Array.isArray(this.sortedSelectedStudents) ? this.sortedSelectedStudents.length : 0
        },
        courseWorkAssignmentPayload(work, suffix, isGroupWork = false, affectedStudentCount = 0) {
            const type = String(work.type || 'Arbeit').trim()
            const title = String(work.title || '').trim()
            const baseLabel = title ? `${type}: ${title}` : type
            const label = suffix ? `${baseLabel} (${suffix})` : baseLabel

            return {
                affectedStudentCount,
                id: work.id,
                isGroupWork,
                key: `${work.id}-${suffix || 'work'}`,
                label,
                scope: suffix || (isGroupWork ? 'Gruppenarbeit' : 'Einzelarbeit'),
                title: label,
                work,
            }
        },
        courseDateColumnMarkingColor(courseDate) {
            if (!this.uses_entry_areas_for_grading_schema) return null

            const entryDefinitions = this.selected_course?.teaching_entry_area?.entry_definitions
            if (!Array.isArray(entryDefinitions)) return null

            const markedDefinitionsByType = new Map(
                entryDefinitions
                    .filter((definition) => (
                        definition?.category === 'Benotung'
                        && definition.has_table_marking
                        && tableMarkingColors.has(definition.table_marking_color)
                    ))
                    .map((definition) => [String(definition.short_name || ''), definition.table_marking_color])
            )

            const markedAssignment = this.courseWorksForDate(courseDate)
                .find((assignment) => markedDefinitionsByType.has(String(assignment?.work?.type || '')))

            return markedAssignment
                ? markedDefinitionsByType.get(String(markedAssignment.work.type))
                : null
        },
        courseDateColumnMarkingClass(courseDate) {
            const color = this.courseDateColumnMarkingColor(courseDate)

            return color ? `course-table-column--marked-${color}` : null
        },
        courseDateContentPreview(courseDate) {
            const content = String(courseDate?.content || '').trim()
            if (!content) return ''

            const contentWithSpacing = content
                .replace(/<br\s*\/?>/gi, ' ')
                .replace(/<\/(?:div|h[1-6]|li|p)>/gi, ' ')

            if (typeof DOMParser === 'undefined') {
                return contentWithSpacing
                    .replace(/<[^>]*>/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim()
            }

            const document = new DOMParser().parseFromString(contentWithSpacing, 'text/html')

            return String(document.body.textContent || '')
                .replace(/\s+/g, ' ')
                .trim()
        },
        courseDateFormattedContent(courseDate) {
            const content = String(courseDate?.content || '').trim()
            if (!content) return ''

            if (typeof DOMParser === 'undefined') {
                const characterEntities = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;',
                }

                return content.replace(/[&<>"']/g, (character) => characterEntities[character])
            }

            const document = new DOMParser().parseFromString(content, 'text/html')
            const elements = Array.from(document.body.querySelectorAll('*'))

            elements.forEach((element) => {
                const tagName = element.tagName.toLowerCase()

                if (courseContentBlockedTags.has(tagName)) {
                    element.remove()
                    return
                }

                if (!courseContentAllowedTags.has(tagName)) {
                    element.replaceWith(...element.childNodes)
                    return
                }

                Array.from(element.attributes).forEach((attribute) => element.removeAttribute(attribute.name))
            })

            return document.body.innerHTML
        },
        openContentDialog(courseDate) {
            this.contentDialog = {
                content: courseDate?.content || '',
                courseDate,
                open: true,
            }
        },
        applyCurriculumContentToContentDialog() {
            if (this.contentSaving || !this.contentDialogCurriculumContent.length) return

            const curriculumContent = this.contentDialogCurriculumContent
                .map((content) => `<p>${this.escapeCourseContentText(content)}</p>`)
                .join('')
            const currentContent = String(this.contentDialog.content || '').trim()

            this.contentDialog.content = currentContent
                ? `${currentContent}${curriculumContent}`
                : curriculumContent
        },
        escapeCourseContentText(content) {
            const characterEntities = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }

            return String(content || '').replace(/[&<>"']/g, (character) => characterEntities[character])
        },
        closeContentDialog() {
            if (this.contentSaving) return

            this.contentDialog = {
                content: '',
                courseDate: null,
                open: false,
            }
        },
        async navigateContentDialogDate(offset) {
            if (this.contentSaving || ![-1, 1].includes(Number(offset))) return

            const targetCourseDate = this.sortedCourseDates[this.contentDialogCourseDateIndex + Number(offset)]
            if (!targetCourseDate) return

            const currentContent = String(this.contentDialog.content ?? '')
            const savedContent = String(this.contentDialog.courseDate?.content ?? '')

            if (currentContent !== savedContent) {
                const wasSaved = await this.saveContentDialog(false)
                if (!wasSaved) return
            }

            this.openContentDialog(targetCourseDate)
        },
        async saveContentDialog(closeAfterSave = true) {
            const courseDate = this.contentDialog.courseDate
            if (this.contentSaving || !courseDate?.id || !this.courseDateStore || !this.courseStore) return false

            this.contentSaving = true
            let wasSaved = false

            try {
                const response = await this.courseDateStore.update({
                    id: courseDate.id,
                    date: courseDate.date,
                    content: this.contentDialog.content ?? '',
                })
                if (!response) return false

                wasSaved = true
                await this.courseStore.index()
            } finally {
                this.contentSaving = false
            }

            if (wasSaved && closeAfterSave) this.closeContentDialog()

            return wasSaved
        },
        isContentDialogCellSelected(courseDate) {
            if (!this.contentDialog.open) return false

            return this.courseDateScrollKey(this.contentDialog.courseDate) === this.courseDateScrollKey(courseDate)
        },
        openWorkDialog(courseDate) {
            const shouldStartCreating = this.courseWorksForDate(courseDate).length === 0

            this.cancelDateWorkForm()
            this.workDialog = {
                courseDate,
                open: true,
            }
            if (shouldStartCreating) {
                this.startCreatingDateWork()
            }
        },
        openNewWorkDialog(courseDate) {
            this.cancelDateWorkForm()
            this.workDialog = {
                courseDate,
                open: true,
            }
            this.startCreatingDateWork()
        },
        closeWorkDialog() {
            if (this.workSaving || this.workDeleting) return

            this.cancelDateWorkForm()
            this.closeDeleteWorkDialog()
            this.workDialog = {
                courseDate: null,
                open: false,
            }
        },
        isWorkDialogCellSelected(courseDate) {
            if (!this.workDialog.open) return false

            return this.courseDateScrollKey(this.workDialog.courseDate) === this.courseDateScrollKey(courseDate)
        },
        emptyDateWorkForm() {
            const workDate = this.normalizeDateKey(this.workDialog.courseDate?.date)

            return {
                maximum_plus: null,
                date_for_all_groups: workDate,
                description: '',
                finish_until_date: workDate,
                groups: [],
                group_size: null,
                id: null,
                is_group_work: false,
                is_random_groups: false,
                status: [],
                teaching_course_id: this.selected_course?.id || null,
                title: '',
                type: '',
            }
        },
        startCreatingDateWork() {
            this.workDialogForm = this.emptyDateWorkForm()
            this.workDialogDateEditing = false
            this.workDialogFinishDateEditing = false
            this.workDialogModeDraft = false
            this.workDialogModeEditing = false
            this.workDialogTab = 'work'
            this.workDialogTypeDraft = ''
            this.workDialogTypeEditing = false
            this.workDialogFormOpen = true
        },
        startEditingDateWork(work) {
            if (!work?.id) return

            const groups = this.cloneDateWorkGroups(work.groups)

            this.workDialogForm = {
                ...this.emptyDateWorkForm(),
                ...work,
                date_for_all_groups: this.normalizeDateKey(work.date_for_all_groups),
                description: String(work.description || ''),
                finish_until_date: this.normalizeDateKey(work.finish_until_date),
                groups: work.is_group_work && this.isGeneratedEmptyIndividualWorkGroups(groups) ? [] : groups,
                is_group_work: !!work.is_group_work,
                status: Array.isArray(work.status) ? work.status : [],
                title: String(work.title || ''),
                type: String(work.type || ''),
            }
            this.workDialogDateEditing = false
            this.workDialogFinishDateEditing = false
            this.workDialogModeDraft = false
            this.workDialogModeEditing = false
            this.workDialogTab = 'work'
            this.workDialogTypeDraft = ''
            this.workDialogTypeEditing = false
            this.workDialogFormOpen = true
        },
        cloneDateWorkGroups(groups) {
            const cloneStudentValues = (values) => {
                if (Array.isArray(values)) {
                    return values.map((value) => (
                        value && typeof value === 'object' ? { ...value } : value
                    ))
                }
                if (values && typeof values === 'object') {
                    return Object.fromEntries(Object.entries(values).map(([studentId, value]) => [
                        studentId,
                        value && typeof value === 'object' ? { ...value } : value,
                    ]))
                }

                return []
            }

            return (Array.isArray(groups) ? groups : []).map((group) => ({
                ...group,
                comments: cloneStudentValues(group?.comments),
                grades: cloneStudentValues(group?.grades),
                points: cloneStudentValues(group?.points),
                student_ids: Array.isArray(group?.student_ids) ? [...group.student_ids] : [],
            }))
        },
        beginDateWorkTypeEditing() {
            this.workDialogTypeDraft = this.workDialogForm.type
            this.workDialogTypeEditing = true
        },
        selectDateWorkType(type) {
            if (this.workDialogForm.id && this.workDialogTypeEditing) {
                this.workDialogTypeDraft = type

                return
            }

            this.workDialogForm.type = type
        },
        cancelDateWorkTypeEditing() {
            this.workDialogTypeDraft = ''
            this.workDialogTypeEditing = false
        },
        confirmDateWorkTypeEditing() {
            if (!this.canConfirmDateWorkType) return

            this.workDialogForm.type = this.workDialogTypeDraft
            this.workDialogTypeDraft = ''
            this.workDialogTypeEditing = false
        },
        beginDateWorkModeEditing() {
            this.workDialogModeDraft = !!this.workDialogForm.is_group_work
            this.workDialogModeEditing = true
        },
        cancelDateWorkModeEditing() {
            this.workDialogModeDraft = false
            this.workDialogModeEditing = false
        },
        confirmDateWorkModeEditing() {
            if (!this.canConfirmDateWorkMode) return

            this.workDialogForm.is_group_work = this.workDialogModeDraft
            this.workDialogModeDraft = false
            this.workDialogModeEditing = false
        },
        beginWorkDialogDateEditing() {
            if (!this.workDialogFormOpen || this.workSaving) return

            this.workDialogFinishDateEditing = false
            this.workDialogDateEditing = true
        },
        applyWorkDialogDate(value) {
            const date = this.normalizeDateKey(value)
            if (!date) return

            const courseDate = this.sortedCourseDates.find((item) => this.normalizeDateKey(item?.date) === date) || { date }
            this.workDialogForm.date_for_all_groups = date
            if (Array.isArray(this.workDialogForm.groups)) {
                this.workDialogForm.groups.forEach((group) => {
                    group.date = date
                })
            }
            this.workDialog = {
                ...this.workDialog,
                courseDate,
            }
            this.workDialogDateEditing = false
        },
        beginWorkDialogFinishDateEditing() {
            if (!this.workDialogFormOpen || this.workSaving) return

            this.workDialogDateEditing = false
            this.workDialogFinishDateEditing = true
        },
        applyWorkDialogFinishDate(value) {
            this.workDialogForm.finish_until_date = this.normalizeDateKey(value) || null
            this.workDialogFinishDateEditing = false
        },
        beginWorkDialogGroupDateEditing(groupIndex) {
            if (this.workSaving || !this.workDialogGroups[groupIndex]) return

            this.workDialogGroupDateEditingIndex = groupIndex
            this.workDialogGroupDateMenuOpen = true
        },
        handleWorkDialogGroupDateMenu(isOpen) {
            this.workDialogGroupDateMenuOpen = isOpen
            if (!isOpen) {
                this.workDialogGroupDateEditingIndex = null
            }
        },
        applyWorkDialogGroupDate(groupIndex, value) {
            const date = this.normalizeDateKey(value)
            const group = this.workDialogGroups[groupIndex]
            if (!date || !group) return

            group.date = date
            this.workDialogGroupDateMenuOpen = false
            this.workDialogGroupDateEditingIndex = null
        },
        openRandomGroupsDialog() {
            const savedGroupSize = Number(this.workDialogForm.group_size)
            const isSavedGroupSizeValid = Number.isInteger(savedGroupSize)
                && savedGroupSize >= 2
                && savedGroupSize <= this.maxRandomGroupSize

            this.randomGroupsDialog = {
                groupSize: isSavedGroupSizeValid ? savedGroupSize : 2,
                open: true,
            }
        },
        closeRandomGroupsDialog() {
            this.randomGroupsDialog.open = false
        },
        confirmRandomGroupSize() {
            if (!this.canConfirmRandomGroupSize) return

            const groupSize = Number(this.randomGroupsDialog.groupSize)

            this.workDialogForm.group_size = groupSize
            this.workDialogForm.is_random_groups = true
            this.generateRandomWorkDialogGroups(groupSize)
            this.closeRandomGroupsDialog()
        },
        generateRandomWorkDialogGroups(groupSize) {
            const studentIds = this.sortedSelectedStudents
                .map((student) => this.registeredStudentUserId(student) || student?.id)
                .filter(Boolean)
            const shuffledStudentIds = [...new Set(studentIds)]

            for (let currentIndex = shuffledStudentIds.length - 1; currentIndex > 0; currentIndex -= 1) {
                const randomIndex = Math.floor(Math.random() * (currentIndex + 1))
                ;[shuffledStudentIds[currentIndex], shuffledStudentIds[randomIndex]] = [
                    shuffledStudentIds[randomIndex],
                    shuffledStudentIds[currentIndex],
                ]
            }

            let startIndex = 0
            const studentGroups = this.randomGroupSizes(shuffledStudentIds.length, groupSize).map((size) => {
                const studentGroup = shuffledStudentIds.slice(startIndex, startIndex + size)
                startIndex += size

                return studentGroup
            })

            this.workDialogForm.groups = studentGroups.map((groupStudentIds) => ({
                _is_new: true,
                student_ids: groupStudentIds,
                date: this.workDialogForm.date_for_all_groups || '',
                comment: '',
                grade: '',
                grades: {},
                comments: {},
                points: {},
                use_individual_grades: false,
            }))
        },
        randomGroupSizes(studentCount, groupSize) {
            const groupSizes = []

            for (let remainingStudents = studentCount; remainingStudents > 0; remainingStudents -= groupSize) {
                groupSizes.push(Math.min(groupSize, remainingStudents))
            }

            if (groupSizes.length > 1 && groupSizes.at(-1) === 1) {
                groupSizes[0] += 1
                groupSizes.pop()
            }

            return groupSizes
        },
        cancelDateWorkForm() {
            this.closeRandomGroupsDialog()
            this.workDialogForm = this.emptyDateWorkForm()
            this.workDialogDateEditing = false
            this.workDialogFinishDateEditing = false
            this.workDialogGroupDetails = {
                groupIndex: null,
                open: false,
            }
            this.workDialogGroupDateEditingIndex = null
            this.workDialogGroupDateMenuOpen = false
            this.workDialogModeDraft = false
            this.workDialogModeEditing = false
            this.workDialogTab = 'work'
            this.workDialogTypeDraft = ''
            this.workDialogTypeEditing = false
            this.workDialogFormOpen = false
        },
        async saveDateWork() {
            if (!this.canSaveDateWork) return
            const requiresMaximum = requiresWorkMaximumPlus(this.selected_course, this.workDialogForm.type)
            if (requiresMaximum && workMaximumPlusError(this.workDialogForm.maximum_plus)) return
            const mode = this.courseWorkGradeInputModeForType(this.workDialogForm.type)
            if (this.workDialogForm.groups.some((group) => {
                const grades = group.use_individual_grades ? (group.grades || []).map((item) => item.grade) : [group.grade]
                return grades.some((grade) => (
                    (['plus', 'plus_minus', 'points'].includes(mode) || ['NA', 'VL', 'F'].includes(String(grade ?? '').trim()))
                    && this.gradeInputValidation(grade, mode, this.workDialogForm.type) !== true
                ))
            })) return

            this.workSaving = true
            try {
                const gradeInputMode = this.courseWorkGradeInputModeForType(this.workDialogForm.type)
                const payload = {
                    ...this.workDialogForm,
                    date_for_all_groups: this.normalizeDateKey(this.workDialogForm.date_for_all_groups)
                        || this.normalizeDateKey(this.workDialog.courseDate?.date)
                        || null,
                    description: String(this.workDialogForm.description || '').trim() || null,
                    finish_until_date: this.normalizeDateKey(this.workDialogForm.finish_until_date) || null,
                    groups: this.courseWorkGroupsForGradeInputMode(this.workDialogForm.groups, gradeInputMode),
                    teaching_course_id: this.selected_course?.id || this.workDialogForm.teaching_course_id,
                    title: String(this.workDialogForm.title || '').trim() || null,
                    type: this.workDialogForm.type || null,
                }
                if (requiresMaximum) payload.maximum_plus = Number(this.workDialogForm.maximum_plus)
                else delete payload.maximum_plus
                const response = payload.id
                    ? await this.courseWorkStore.update(payload)
                    : await this.courseWorkStore.store(payload)

                if (!response) return

                this.applySavedCourseWork(response)
                await this.loadCourseTableData(this.selected_course?.id, true)
                this.cancelDateWorkForm()
            } finally {
                this.workSaving = false
            }
        },
        openDeleteWorkDialog(work) {
            if (!work?.id) return

            this.deleteWorkDialog = {
                open: true,
                work,
            }
        },
        closeDeleteWorkDialog() {
            if (this.workDeleting) return

            this.deleteWorkDialog = {
                open: false,
                work: null,
            }
        },
        async confirmDeleteDateWork() {
            const work = this.deleteWorkDialog.work
            if (!work?.id || this.workDeleting) return

            this.workDeleting = true
            try {
                const deleted = await this.courseWorkStore.destroy(work.id)
                if (!deleted) return

                if (String(this.workDialogForm.id) === String(work.id)) {
                    this.cancelDateWorkForm()
                }
                await this.loadCourseTableData(this.selected_course?.id, true)
                this.deleteWorkDialog = {
                    open: false,
                    work: null,
                }
            } finally {
                this.workDeleting = false
            }
        },
        registeredStudentUserId(student) {
            if (!student) return null
            if (Object.prototype.hasOwnProperty.call(student, 'user_id')) {
                return student.user_id || null
            }

            return student.id || null
        },
        isGeneratedEmptyIndividualWorkGroups(groups) {
            if (!Array.isArray(groups) || groups.length < 2) return false

            const assignedStudentIds = new Set()
            const hasValue = (value) => value !== null && value !== undefined && String(value).trim() !== ''
            const collectionHasValue = (collection, property) => {
                const items = Array.isArray(collection)
                    ? collection
                    : collection && typeof collection === 'object' ? Object.values(collection) : []

                return items.some((item) => hasValue(
                    item && typeof item === 'object' ? item[property] : item
                ))
            }
            const collectionMatchesStudent = (collection, studentId) => {
                if (!Array.isArray(collection)) return true

                return collection.every((item) => !item?.student_id || String(item.student_id) === studentId)
            }

            return groups.every((group) => {
                const studentIds = Array.isArray(group?.student_ids) ? group.student_ids : []
                if (studentIds.length !== 1) return false

                const studentId = String(studentIds[0] || '')
                if (!studentId || assignedStudentIds.has(studentId)) return false
                if (hasValue(group?.name) || hasValue(group?.grade) || hasValue(group?.comment)) return false
                if (collectionHasValue(group?.grades, 'grade')) return false
                if (collectionHasValue(group?.comments, 'comment')) return false
                if (collectionHasValue(group?.points, 'points')) return false
                if (!collectionMatchesStudent(group?.grades, studentId)) return false
                if (!collectionMatchesStudent(group?.comments, studentId)) return false
                if (!collectionMatchesStudent(group?.points, studentId)) return false

                assignedStudentIds.add(studentId)

                return true
            })
        },
        workGroupStudentName(studentId) {
            const student = this.sortedSelectedStudents.find((courseStudent) => [
                courseStudent?.id,
                courseStudent?.import116_id,
                this.registeredStudentUserId(courseStudent),
            ].some((candidateId) => String(candidateId || '') === String(studentId)))

            return student ? this.studentName(student) : `Schüler:in #${studentId}`
        },
        workGroupStudentNames(group) {
            const studentIds = Array.isArray(group?.student_ids) ? group.student_ids : []

            return studentIds.map((studentId) => this.workGroupStudentName(studentId))
        },
        workGroupStudentGrade(group, studentId) {
            const grades = Array.isArray(group?.grades)
                ? group.grades
                : group?.grades && typeof group.grades === 'object'
                    ? Object.entries(group.grades).map(([savedStudentId, savedGrade]) => ({
                        student_id: savedStudentId,
                        grade: savedGrade && typeof savedGrade === 'object' ? savedGrade.grade : savedGrade,
                    }))
                    : []
            const individualGrade = grades.find(
                (grade) => String(grade?.student_id) === String(studentId)
            )

            return String(individualGrade?.grade ?? group?.grade ?? '')
        },
        workGroupStudentComment(group, studentId) {
            const comments = Array.isArray(group?.comments)
                ? group.comments
                : group?.comments && typeof group.comments === 'object'
                    ? Object.entries(group.comments).map(([savedStudentId, savedComment]) => ({
                        student_id: savedStudentId,
                        comment: savedComment && typeof savedComment === 'object' ? savedComment.comment : savedComment,
                    }))
                    : []
            const individualComment = comments.find(
                (comment) => String(comment?.student_id) === String(studentId)
            )

            return String(individualComment?.comment ?? '')
        },
        workGroupGradeRows(group) {
            const studentIds = Array.isArray(group?.student_ids) ? group.student_ids : []

            return studentIds.map((studentId) => ({
                comment: this.workGroupStudentComment(group, studentId),
                grade: this.workGroupStudentGrade(group, studentId),
                studentId,
                studentName: this.workGroupStudentName(studentId),
            }))
        },
        ensureIndividualWorkStudentGroup(studentId) {
            if (!Array.isArray(this.workDialogForm.groups)) {
                this.workDialogForm.groups = []
            }

            const existingGroupIndex = this.workDialogForm.groups.findIndex((group) => (
                Array.isArray(group?.student_ids)
                && group.student_ids.some((groupStudentId) => String(groupStudentId) === String(studentId))
            ))
            if (existingGroupIndex >= 0) return existingGroupIndex

            this.workDialogForm.groups.push({
                student_ids: [studentId],
                date: this.workDialogForm.date_for_all_groups || '',
                comment: null,
                grade: null,
                grades: [{ student_id: studentId, grade: '' }],
                comments: [{ student_id: studentId, comment: '' }],
                points: [],
            })

            return this.workDialogForm.groups.length - 1
        },
        setIndividualWorkStudentGrade(studentId, value) {
            const groupIndex = this.ensureIndividualWorkStudentGroup(studentId)

            this.setWorkGroupStudentGrade(groupIndex, studentId, value)
        },
        setIndividualWorkStudentComment(studentId, value) {
            const groupIndex = this.ensureIndividualWorkStudentGroup(studentId)

            this.setWorkGroupStudentComment(groupIndex, studentId, value)
        },
        setWorkGroupStudentGrade(groupIndex, studentId, value) {
            const group = this.workDialogGroups[groupIndex]
            if (!group) return

            const grades = (Array.isArray(group.student_ids) ? group.student_ids : []).map((groupStudentId) => ({
                student_id: groupStudentId,
                grade: this.workGroupStudentGrade(group, groupStudentId),
            }))
            const studentGrade = grades.find(
                (grade) => String(grade.student_id) === String(studentId)
            )
            if (!studentGrade) return

            studentGrade.grade = String(value ?? '')
            group.grade = ''
            group.grades = grades
            group.use_individual_grades = true
        },
        setWorkGroupStudentComment(groupIndex, studentId, value) {
            const group = this.workDialogGroups[groupIndex]
            if (!group) return

            const studentIds = Array.isArray(group.student_ids) ? group.student_ids : []
            const comments = studentIds.map((groupStudentId) => ({
                student_id: groupStudentId,
                comment: this.workGroupStudentComment(group, groupStudentId),
            }))
            const studentComment = comments.find(
                (comment) => String(comment.student_id) === String(studentId)
            )
            if (!studentComment) return

            studentComment.comment = String(value ?? '')
            const grades = studentIds.map((groupStudentId) => ({
                student_id: groupStudentId,
                grade: this.workGroupStudentGrade(group, groupStudentId),
            }))
            group.grade = ''
            group.grades = grades
            group.comments = comments
            group.use_individual_grades = true
        },
        openWorkDialogGroupDetails(groupIndex) {
            if (this.workSaving || !this.workDialogGroups[groupIndex]) return

            this.workDialogGroupDetails = {
                groupIndex,
                open: true,
            }
        },
        closeWorkDialogGroupDetails() {
            if (this.workSaving) return

            this.workDialogGroupDetails = {
                groupIndex: null,
                open: false,
            }
        },
        workGroupMemberCountTitle(group) {
            const memberCount = Array.isArray(group?.student_ids) ? group.student_ids.length : 0

            return `${memberCount} ${memberCount === 1 ? 'Mitglied' : 'Mitglieder'}`
        },
        workGroupStudentItems(groupIndex) {
            const currentGroupIds = new Set(
                (this.workDialogGroups[groupIndex]?.student_ids || []).map((studentId) => String(studentId))
            )
            const assignedToOtherGroups = new Set()

            this.workDialogGroups.forEach((group, index) => {
                if (index === groupIndex) return

                ;(group?.student_ids || []).forEach((studentId) => assignedToOtherGroups.add(String(studentId)))
            })

            return this.sortedSelectedStudents
                .map((student) => ({
                    title: this.studentName(student),
                    value: this.registeredStudentUserId(student) || student?.id,
                }))
                .filter((item) => item.value && (
                    currentGroupIds.has(String(item.value)) || !assignedToOtherGroups.has(String(item.value))
                ))
        },
        startWorkGroupStudentDrag(event, sourceGroupIndex, studentId) {
            const sourceGroup = this.workDialogGroups[sourceGroupIndex]
            const hasStudent = Array.isArray(sourceGroup?.student_ids)
                && sourceGroup.student_ids.some((groupStudentId) => String(groupStudentId) === String(studentId))

            if (this.workSaving || !hasStudent) {
                event?.preventDefault?.()

                return
            }

            this.workDialogStudentDrag = {
                sourceGroupIndex,
                studentId,
                targetGroupIndex: null,
            }
            if (event?.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move'
                event.dataTransfer.setData('text/plain', String(studentId))
            }
        },
        startUnassignedWorkGroupStudentDrag(event, studentId) {
            const hasStudent = this.unassignedWorkGroupStudents.some(
                (student) => String(student.value) === String(studentId)
            )
            if (this.workSaving || !hasStudent) {
                event?.preventDefault?.()

                return
            }

            this.workDialogStudentDrag = {
                sourceGroupIndex: -1,
                studentId,
                targetGroupIndex: null,
            }
            if (event?.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move'
                event.dataTransfer.setData('text/plain', String(studentId))
            }
        },
        setWorkGroupStudentDropTarget(targetGroupIndex) {
            const sourceGroupIndex = this.workDialogStudentDrag.sourceGroupIndex
            if (!Number.isInteger(sourceGroupIndex) || sourceGroupIndex === targetGroupIndex) return

            if (this.workDialogStudentDrag.targetGroupIndex === targetGroupIndex) return

            this.workDialogStudentDrag = {
                ...this.workDialogStudentDrag,
                targetGroupIndex,
            }
        },
        dropWorkGroupStudent(targetGroupIndex) {
            const { sourceGroupIndex, studentId } = this.workDialogStudentDrag
            if (sourceGroupIndex === -1 && studentId !== null) {
                this.assignUnassignedWorkGroupStudent(targetGroupIndex, studentId)
            } else if (Number.isInteger(sourceGroupIndex) && studentId !== null) {
                this.moveWorkGroupStudentToGroup(sourceGroupIndex, targetGroupIndex, studentId)
            }

            this.finishWorkGroupStudentDrag()
        },
        moveWorkGroupStudentToGroup(sourceGroupIndex, targetGroupIndex, studentId) {
            if (sourceGroupIndex === targetGroupIndex) return false

            const sourceGroup = this.workDialogGroups[sourceGroupIndex]
            const targetGroup = this.workDialogGroups[targetGroupIndex]
            if (!sourceGroup || !targetGroup || !Array.isArray(sourceGroup.student_ids)) return false

            const sourceStudentIndex = sourceGroup.student_ids.findIndex(
                (groupStudentId) => String(groupStudentId) === String(studentId)
            )
            if (sourceStudentIndex < 0) return false

            if (!Array.isArray(targetGroup.student_ids)) {
                targetGroup.student_ids = []
            }
            if (targetGroup.student_ids.some((groupStudentId) => String(groupStudentId) === String(studentId))) {
                return false
            }

            const [movedStudentId] = sourceGroup.student_ids.splice(sourceStudentIndex, 1)
            targetGroup.student_ids.push(movedStudentId)

            const studentValueProperties = ['grades', 'comments', 'points']
            studentValueProperties.forEach((property) => {
                const sourceItems = Array.isArray(sourceGroup[property])
                    ? sourceGroup[property]
                    : sourceGroup[property] && typeof sourceGroup[property] === 'object'
                        ? Object.values(sourceGroup[property])
                        : []
                const targetItems = Array.isArray(targetGroup[property])
                    ? targetGroup[property]
                    : targetGroup[property] && typeof targetGroup[property] === 'object'
                        ? Object.values(targetGroup[property])
                        : []
                const movedItems = sourceItems.filter(
                    (item) => String(item?.student_id) === String(studentId)
                )

                sourceGroup[property] = sourceItems.filter(
                    (item) => String(item?.student_id) !== String(studentId)
                )
                targetGroup[property] = [
                    ...targetItems.filter((item) => String(item?.student_id) !== String(studentId)),
                    ...movedItems,
                ]
            })

            return true
        },
        assignUnassignedWorkGroupStudent(targetGroupIndex, studentId) {
            const targetGroup = this.workDialogGroups[targetGroupIndex]
            const student = this.unassignedWorkGroupStudents.find(
                (unassignedStudent) => String(unassignedStudent.value) === String(studentId)
            )
            if (!targetGroup || !student) return false

            if (!Array.isArray(targetGroup.student_ids)) {
                targetGroup.student_ids = []
            }
            if (targetGroup.student_ids.some((groupStudentId) => String(groupStudentId) === String(student.value))) {
                return false
            }

            targetGroup.student_ids.push(student.value)

            return true
        },
        finishWorkGroupStudentDrag() {
            this.workDialogStudentDrag = {
                sourceGroupIndex: null,
                studentId: null,
                targetGroupIndex: null,
            }
        },
        removeWorkGroupStudent(groupIndex, studentId) {
            if (this.workSaving) return false

            const group = this.workDialogGroups[groupIndex]
            if (!group || !Array.isArray(group.student_ids)) return false

            const studentIndex = group.student_ids.findIndex(
                (groupStudentId) => String(groupStudentId) === String(studentId)
            )
            if (studentIndex < 0) return false

            group.student_ids.splice(studentIndex, 1)
            const studentValueProperties = ['grades', 'comments', 'points']
            studentValueProperties.forEach((property) => {
                const values = Array.isArray(group[property])
                    ? group[property]
                    : group[property] && typeof group[property] === 'object'
                        ? Object.values(group[property])
                        : []

                group[property] = values.filter(
                    (item) => String(item?.student_id) !== String(studentId)
                )
            })

            return true
        },
        addWorkDialogGroup() {
            if (!Array.isArray(this.workDialogForm.groups)) {
                this.workDialogForm.groups = []
            }

            this.workDialogForm.groups.push({
                _is_new: true,
                student_ids: [],
                date: this.workDialogForm.date_for_all_groups || '',
                comment: '',
                grade: '',
                grades: {},
                comments: {},
                points: {},
                use_individual_grades: false,
            })
        },
        removeWorkDialogGroup(groupIndex) {
            if (!this.workDialogForm.groups?.[groupIndex]?._is_new) return

            this.workDialogForm.groups.splice(groupIndex, 1)
            if (this.workDialogGroupDetails.groupIndex === groupIndex) {
                this.closeWorkDialogGroupDetails()
            } else if (this.workDialogGroupDetails.groupIndex > groupIndex) {
                this.workDialogGroupDetails.groupIndex -= 1
            }
        },
        entriesForCell(student, courseDate) {
            const userId = this.registeredStudentUserId(student)
            const dateKey = this.normalizeDateKey(courseDate?.date)
            if (!userId || !dateKey) return []

            const assessmentEntries = (this.entryStore?.courseEntries || [])
                .filter((entry) => (
                    String(entry?.user_id) === String(userId)
                    && this.studentCellEntryDateKey(entry) === dateKey
                ))
                .map((entry) => ({
                    ...entry,
                    kind: 'assessment',
                    uid: `assessment-${entry.id}`,
                }))
            const behaviourEntries = (this.behaviourEntryStore?.courseEntries || [])
                .filter((entry) => String(entry?.user_id) === String(userId) && this.normalizeDateKey(entry?.date) === dateKey)
                .map((entry) => ({
                    ...entry,
                    kind: entry.kind || 'behaviour',
                    uid: `behaviour-${entry.id}`,
                }))

            return [...assessmentEntries, ...behaviourEntries].sort((first, second) => {
                const kindComparison = String(first.kind || '').localeCompare(String(second.kind || ''))
                if (kindComparison !== 0) return kindComparison

                return String(first.type || '').localeCompare(String(second.type || ''), 'de', { sensitivity: 'base' })
            })
        },
        studentHasPendingNotificationConfirmation(student) {
            const userId = this.registeredStudentUserId(student)
            if (!userId) return false

            return (this.entryStore?.courseEntries || []).some((entry) => (
                String(entry?.user_id) === String(userId)
                && entry?.has_pending_notification_confirmation === true
            ))
        },
        studentCellEntryDateKey(entry) {
            const entryDateKey = this.normalizeDateKey(entry?.date)
            if (entry?.source !== 'course_work') return entryDateKey

            const work = this.courseWorkForCellEntry(entry)
            const finishDateKey = work?.finish_until_date
                ? this.normalizeDateKey(work.finish_until_date)
                : ''

            return finishDateKey || entryDateKey
        },
        supplementaryEntriesForCell(student, courseDate) {
            return this.entriesForCell(student, courseDate)
                .filter((entry) => entry.kind !== 'assessment' || this.entryDefinitionCategory(entry) !== 'Benotung')
        },
        performanceEntriesForCell(student, courseDate) {
            return this.entriesForCell(student, courseDate)
                .filter((entry) => entry.kind === 'assessment' && this.entryDefinitionCategory(entry) === 'Benotung')
        },
        cellEntryHoverItems(student, courseDate) {
            const studentId = this.registeredStudentUserId(student)

            return this.entriesForCell(student, courseDate).map((entry) => {
                const work = entry?.source === 'course_work' ? this.courseWorkForCellEntry(entry) : null
                const comment = work
                    ? this.courseWorkStudentComment(entry, studentId)
                    : String(entry?.description || '').trim()
                const workDescription = String(work?.description || '').trim()

                return {
                    comment,
                    description: workDescription && workDescription !== comment ? workDescription : '',
                    grade: work
                        ? this.courseWorkStudentGrade(entry, studentId)
                        : String(entry?.effective_grade || entry?.grade || '').trim(),
                    kind: this.cellEntryKindLabel(entry),
                    title: String(work?.title || '').trim(),
                    type: this.cellEntryTypeLabel(entry),
                    uid: entry.uid,
                }
            })
        },
        compactPerformanceEntriesForCell(student, courseDate) {
            const entriesByType = new Map()
            const studentId = this.registeredStudentUserId(student)

            this.performanceEntriesForCell(student, courseDate).forEach((entry) => {
                const type = String(entry?.type || '').trim()
                const typeKey = type ? type.toLocaleLowerCase('de-AT') : entry.uid
                const grade = String(entry?.effective_grade || entry?.grade || '').trim()
                const comment = entry?.source === 'course_work'
                    ? this.courseWorkStudentComment(entry, studentId)
                    : String(entry?.description || '').trim()

                if (!entriesByType.has(typeKey)) {
                    entriesByType.set(typeKey, {
                        entry: {
                            ...entry,
                            description: null,
                            effective_grade: '',
                            grade: '',
                            uid: `assessment-group-${typeKey}`,
                        },
                        comments: [],
                        grades: [],
                    })
                }

                if (comment && !entriesByType.get(typeKey).comments.includes(comment)) {
                    entriesByType.get(typeKey).comments.push(comment)
                }
                if (grade) {
                    entriesByType.get(typeKey).grades.push(grade)
                }
            })

            return [...entriesByType.values()].map(({ entry, comments, grades }) => ({
                ...entry,
                description: comments.join(' · '),
                effective_grade: this.sortedCompactEntryGrades(grades).join(', '),
            }))
        },
        sortedCompactEntryGrades(grades) {
            const gradePriority = new Map([
                ['++', 0],
                ['+', 1],
                ['+/-', 2],
                ['±', 2],
                ['-', 3],
                ['--', 4],
            ])

            return [...new Set(grades)].sort((first, second) => {
                const firstPriority = gradePriority.get(first)
                const secondPriority = gradePriority.get(second)

                if (firstPriority !== undefined || secondPriority !== undefined) {
                    return (firstPriority ?? Number.MAX_SAFE_INTEGER) - (secondPriority ?? Number.MAX_SAFE_INTEGER)
                }

                return first.localeCompare(second, 'de', { numeric: true, sensitivity: 'base' })
            })
        },
        cellEntryKindLabel(entry) {
            if (entry?.kind === 'assessment') {
                const category = this.entryDefinitionCategory?.(entry) || 'Benotung'

                return category === 'Benotung' ? 'Bewertung' : category
            }
            if (entry?.kind === 'notification') return 'Verständigung'

            return 'Verhalten'
        },
        cellEntryColor(entry) {
            if (entry?.has_pending_notification_confirmation === true) return 'error'

            if (entry?.kind === 'assessment') {
                const category = this.entryDefinitionCategory?.(entry) || 'Benotung'
                if (category === 'Verhalten') return 'warning'
                if (category === 'Weitere') return 'info'

                return null
            }
            if (entry?.kind === 'notification') return 'secondary'

            return 'warning'
        },
        entryDefinitionCategory(entry) {
            if (entry?.kind !== 'assessment') return null

            const definitions = this.selected_course?.teaching_entry_area?.entry_definitions
            if (!this.uses_entry_areas_for_grading_schema || !Array.isArray(definitions)) return 'Benotung'

            const definition = definitions.find((item) => item?.short_name === entry?.type)

            return definition?.category || 'Benotung'
        },
        entryDefinition(entry) {
            if (entry?.kind !== 'assessment') return null

            const definitions = this.selected_course?.teaching_entry_area?.entry_definitions
            if (!Array.isArray(definitions)) return null

            return definitions.find((definition) => definition?.short_name === entry?.type) || null
        },
        entryHasNotificationWorkflow(entry) {
            const definition = this.entryDefinition(entry)

            return Boolean(definition?.has_notifications && definition.notification_recipients?.length)
        },
        resetEntryNotificationState() {
            this.entryNotificationRequestId += 1
            this.entryNotificationConfirmingId = null
            this.entryNotificationLoading = false
            this.entryNotificationRecipients = []
            this.entryNotificationSending = false
            this.selectedEntryNotificationRecipientKeys = []
        },
        async loadEntryNotificationRecipients(entry) {
            if (!this.entryHasNotificationWorkflow(entry) || !entry?.id) return

            const requestId = ++this.entryNotificationRequestId
            this.entryNotificationLoading = true
            try {
                const recipients = await this.entryStore.notificationRecipients(entry.id)
                if (
                    !recipients
                    || requestId !== this.entryNotificationRequestId
                    || this.entryForm.id !== entry.id
                ) return

                this.entryNotificationRecipients = recipients
                this.selectedEntryNotificationRecipientKeys = recipients
                    .filter((recipient) => recipient.available || recipient.informed_at)
                    .map((recipient) => recipient.key)
            } finally {
                if (requestId === this.entryNotificationRequestId) {
                    this.entryNotificationLoading = false
                }
            }
        },
        async loadDraftEntryNotificationRecipients() {
            const requestId = ++this.entryNotificationRequestId
            const courseId = this.selected_course?.id
            const studentId = this.registeredEntryStudentId
            const type = String(this.entryForm.type || '')
            const draftEntry = { kind: 'assessment', type }

            this.entryNotificationLoading = false
            this.entryNotificationRecipients = []
            this.selectedEntryNotificationRecipientKeys = []

            if (!courseId || !studentId || !type || !this.entryHasNotificationWorkflow(draftEntry)) return

            this.entryNotificationLoading = true
            try {
                const recipients = await this.entryStore.previewNotificationRecipients(courseId, studentId, type)
                if (
                    !recipients
                    || requestId !== this.entryNotificationRequestId
                    || this.entryForm.id
                    || this.entryForm.type !== type
                ) return

                this.entryNotificationRecipients = recipients
                this.selectedEntryNotificationRecipientKeys = recipients
                    .filter((recipient) => recipient.available || recipient.informed_at)
                    .map((recipient) => recipient.key)
            } finally {
                if (requestId === this.entryNotificationRequestId) {
                    this.entryNotificationLoading = false
                }
            }
        },
        async sendEntryNotificationEmails(entry) {
            if (!entry?.id || !this.selectedEntryNotificationRecipientKeys.length) return

            this.entryNotificationSending = true
            try {
                const recipients = await this.entryStore.sendNotifications(
                    entry.id,
                    this.selectedEntryNotificationRecipientKeys,
                )
                if (!recipients || this.entryForm.id !== entry.id) return

                this.entryNotificationRecipients = recipients
                this.selectedEntryNotificationRecipientKeys = recipients
                    .filter((recipient) => recipient.available)
                    .map((recipient) => recipient.key)
            } finally {
                this.entryNotificationSending = false
            }
        },
        async confirmEntryNotificationManually(entry, recipient) {
            if (!entry?.id || !recipient?.notification_id || recipient.confirmed_at) return

            this.entryNotificationConfirmingId = recipient.notification_id
            try {
                const recipients = await this.entryStore.confirmNotification(entry.id, recipient.notification_id)
                if (!recipients || this.entryForm.id !== entry.id) return

                this.entryNotificationRecipients = recipients
                this.selectedEntryNotificationRecipientKeys = recipients
                    .filter((item) => item.available || item.informed_at)
                    .map((item) => item.key)
            } finally {
                this.entryNotificationConfirmingId = null
            }
        },
        notificationConfirmationLabel(recipient) {
            if (recipient?.confirmation_method === 'manual') {
                return recipient.confirmed_by
                    ? `Manuell bestätigt von ${recipient.confirmed_by}`
                    : 'Manuell bestätigt'
            }

            if (recipient?.confirmation_method === 'email') {
                return recipient.confirmed_by
                    ? `Per E-Mail bestätigt von ${recipient.confirmed_by}`
                    : 'Per E-Mail bestätigt'
            }

            return 'Bestätigt (Art nicht erfasst)'
        },
        formatNotificationDateTime(value) {
            if (!value) return '–'

            const date = new Date(value)
            if (Number.isNaN(date.getTime())) return String(value)

            return new Intl.DateTimeFormat('de-AT', {
                dateStyle: 'short',
                timeStyle: 'short',
            }).format(date)
        },
        cellEntryTypeLabel(entry) {
            const type = String(entry?.type || '').trim()
            if (!type) return 'Eintrag'

            if (entry?.kind === 'assessment') {
                const entryDefinitions = this.selected_course?.teaching_entry_area?.entry_definitions
                const entryDefinition = (Array.isArray(entryDefinitions) ? entryDefinitions : [])
                    .find((item) => item?.short_name === type)
                if (entryDefinition) {
                    return entryDefinition.name ? `${type} - ${entryDefinition.name}` : type
                }

                const works = Array.isArray(this.selectedTeachingSchema?.works) ? this.selectedTeachingSchema.works : []
                const work = works.find((item) => item?.short_name === type)
                return work?.name ? `${type} - ${work.name}` : type
            }

            const definitions = entry?.kind === 'notification'
                ? this.selected_course?.teacher_teaching_notifications
                : this.selected_course?.teacher_teaching_behaviour
            const definition = (Array.isArray(definitions) ? definitions : []).find((item) => item?.short_name === type)

            return definition?.name ? `${type} - ${definition.name}` : type
        },
        cellEntryListComment(entry) {
            if (entry?.source === 'course_work') {
                return String(this.courseWorkEntryStudentComment(entry) || '').trim()
            }

            return String(entry?.description || '').trim()
        },
        compactCellEntryLabel(entry) {
            const type = String(entry?.type || '').trim() || 'Eintrag'
            if (entry?.kind !== 'assessment') return type
            if (this.entryTypeHasProperties && !this.entryTypeHasProperties(entry)) return type

            const grade = String(entry?.effective_grade || entry?.grade || '').trim()

            return `${type}: ${grade || 'NA'}`
        },
        entryTypeHasProperties(entry) {
            if (entry?.kind !== 'assessment') return false

            const assignedEntryArea = this.selected_course?.teaching_entry_area || null
            const definitions = assignedEntryArea?.entry_definitions
            const usesEntryDefinitions = Boolean(
                this.uses_entry_areas_for_grading_schema
                && assignedEntryArea?.id
                && Array.isArray(definitions)
            )
            if (!usesEntryDefinitions) return true

            const definition = definitions.find((item) => item?.short_name === entry?.type)

            return definition ? Boolean(definition.has_properties) : true
        },
        entryExpectsProperty(entry) {
            return entry?.kind === 'assessment'
                && this.entryDefinitionCategory(entry) === 'Benotung'
                && this.entryTypeHasProperties(entry)
        },
        compactCellEntryType(entry) {
            return String(entry?.type || '').trim() || 'Eintrag'
        },
        compactCellEntryGrade(entry) {
            return String(entry?.effective_grade || entry?.grade || '').trim()
        },
        courseWorkForCellEntry(entry) {
            const workId = entry?.teaching_course_work_id
            if (!workId) return null

            return (this.courseWorks || []).find((work) => String(work?.id) === String(workId)) || null
        },
        courseWorkGroupIndexForStudent(work, studentId = this.registeredEntryStudentId) {
            if (!work || !studentId) return -1

            return (Array.isArray(work.groups) ? work.groups : []).findIndex((group) => (
                Array.isArray(group?.student_ids)
                && group.student_ids.some((groupStudentId) => String(groupStudentId) === String(studentId))
            ))
        },
        courseWorkGroupForCellEntry(entry, studentId = this.registeredEntryStudentId) {
            const work = this.courseWorkForCellEntry(entry)
            const groupIndex = this.courseWorkGroupIndexForStudent(work, studentId)

            return groupIndex >= 0 ? work.groups[groupIndex] : null
        },
        courseWorkEntryDateTitle(entry) {
            const work = this.courseWorkForCellEntry(entry)
            const group = this.courseWorkGroupForCellEntry(entry)
            const date = group?.date || work?.date_for_all_groups

            return date ? this.compactCourseDateTitle({ date }) : 'Ohne Datum'
        },
        courseWorkEntryPeriod(entry) {
            const work = this.courseWorkForCellEntry(entry)
            if (!work) return null

            const group = this.courseWorkGroupForCellEntry(entry)
            const startDate = group?.date || work.date_for_all_groups || entry?.date
            const finishDate = work.finish_until_date || ''
            const isSameDate = Boolean(
                startDate
                && finishDate
                && this.normalizeDateKey(startDate) === this.normalizeDateKey(finishDate)
            )

            return {
                durationLabel: finishDate && !isSameDate
                    ? this.courseWorkDurationLabel({
                        date_for_all_groups: startDate,
                        finish_until_date: finishDate,
                    })
                    : '',
                finishDateTitle: finishDate ? this.compactCourseDateTitle({ date: finishDate }) : '',
                isSameDate,
                startDateTitle: startDate ? this.compactCourseDateTitle({ date: startDate }) : 'Ohne Datum',
            }
        },
        courseWorkEntryModeTitle(entry) {
            const work = this.courseWorkForCellEntry(entry)
            if (!work) return 'Unbekannt'

            return work.is_group_work ? 'Gruppenarbeit' : 'Einzelarbeit'
        },
        courseWorkEntryAssignmentTitle(entry) {
            const work = this.courseWorkForCellEntry(entry)
            if (!work?.is_group_work) return ''

            const groupIndex = this.courseWorkGroupIndexForStudent(work)
            const group = groupIndex >= 0 ? work.groups[groupIndex] : null
            if (!group) return 'Keine Gruppe gefunden'

            const memberCount = Array.isArray(group.student_ids) ? group.student_ids.length : 0
            const groupName = String(group.name || '').trim() || `Gruppe ${groupIndex + 1}`

            return `${groupName} · ${memberCount} ${memberCount === 1 ? 'Mitglied' : 'Mitglieder'}`
        },
        courseWorkEntryOtherGroupMembers(entry) {
            const work = this.courseWorkForCellEntry(entry)
            if (!work?.is_group_work) return []

            const group = this.courseWorkGroupForCellEntry(entry)
            const currentStudentId = String(this.registeredEntryStudentId || '')
            const studentIds = Array.isArray(group?.student_ids) ? group.student_ids : []

            return studentIds
                .filter((studentId) => String(studentId) !== currentStudentId)
                .map((studentId) => ({
                    comment: this.courseWorkStudentComment(entry, studentId),
                    grade: this.courseWorkStudentGrade(entry, studentId),
                    id: String(studentId),
                    name: this.workGroupStudentName(studentId),
                }))
        },
        courseWorkEntryGradeItems(entry) {
            const work = this.courseWorkForCellEntry(entry)

            return this.courseWorkGradeConfigurationForType(work?.type).items
        },
        courseWorkEntryGradeInputMode(entry) {
            const work = this.courseWorkForCellEntry(entry)

            return this.courseWorkGradeInputModeForType(work?.type)
        },
        courseWorkGradeInputModeForType(type) {
            return this.courseWorkGradeConfigurationForType(type).mode
        },
        courseWorkGradeConfigurationForType(type) {
            const assignedEntryArea = this.selected_course?.teaching_entry_area || null
            const entryDefinitions = Array.isArray(assignedEntryArea?.entry_definitions)
                ? assignedEntryArea.entry_definitions.filter((definition) => definition?.category === 'Benotung')
                : []
            const usesEntryDefinitions = Boolean(this.uses_entry_areas_for_grading_schema && assignedEntryArea?.id)
            const workDefinitions = usesEntryDefinitions
                ? entryDefinitions
                : Array.isArray(this.selectedTeachingSchema?.works) ? this.selectedTeachingSchema.works : []
            const workDefinition = workDefinitions.find((definition) => definition?.short_name === type)

            if (usesEntryDefinitions) {
                if (!workDefinition?.has_properties) {
                    return { items: [], mode: 'none' }
                }

                if (workDefinition.properties_mode !== 'fixed') {
                    const mode = ['plus', 'plus_minus', 'points'].includes(workDefinition.properties_mode)
                        ? workDefinition.properties_mode : 'free'
                    return { items: [], mode }
                }

                const items = (Array.isArray(workDefinition.fixed_properties) ? workDefinition.fixed_properties : [])
                    .map((property) => String(property || '').trim())
                    .filter((property) => property && !['NA', 'VL', 'F'].includes(property))
                    .map((property) => ({
                        title: property,
                        value: property,
                    }))

                return { items, mode: 'fixed' }
            }

            const grades = Array.isArray(workDefinition?.grades) ? workDefinition.grades : []
            const items = grades
                .filter((grade) => grade?.grade)
                .map((grade) => ({
                    title: grade.name ? `${grade.grade} (${grade.name})` : grade.grade,
                    value: grade.grade,
                }))

            return {
                items,
                mode: items.length ? 'fixed' : 'free',
            }
        },
        courseWorkGroupsForGradeInputMode(groups, gradeInputMode) {
            const workGroups = Array.isArray(groups) ? groups : []
            if (gradeInputMode === 'points') {
                return workGroups.map((group) => ({
                    ...group,
                    grade: this.normalizedPointsGrade(group.grade),
                    grades: (group.grades || []).map((item) => ({ ...item, grade: this.normalizedPointsGrade(item.grade) })),
                }))
            }
            if (gradeInputMode !== 'none') return workGroups

            return workGroups.map((group) => ({
                ...group,
                grade: null,
                grades: [],
            }))
        },
        normalizedPointsGrade(value) {
            if (value === null || value === undefined) return value
            return String(value).trim().replace(',', '.')
        },
        maximumPointsForType(type) {
            const definition = this.selected_course?.teaching_entry_area?.entry_definitions
                ?.find((entry) => entry.category === 'Benotung' && entry.short_name === type)
            return Number(definition?.maximum_points)
        },
        gradeInputHint(mode, type) {
            if (mode === 'points') return `Punkte von 0 bis ${this.maximumPointsForType(type)}; Dezimalstellen sind möglich.`
            if (mode === 'plus') return 'Nur Pluszeichen, z. B. +, ++, +++ (max. 50).'
            if (mode === 'plus_minus') return 'Nur Pluszeichen oder nur Minuszeichen, z. B. +++, -- (max. 50).'
            return ''
        },
        specialGradeItemsForType(type) {
            if (!this.uses_entry_areas_for_grading_schema) return []
            const definition = this.selected_course?.teaching_entry_area?.entry_definitions
                ?.find((entry) => entry.category === 'Benotung' && entry.short_name === type)
            if (!definition?.has_properties) return []
            const enabled = definition.enabled_special_properties ?? ['NA', 'VL', 'F']
            return [
                { title: 'NA · Nicht angetreten', value: 'NA' },
                { title: 'VL · Vorgetäuschte Leistung', value: 'VL' },
                { title: 'F · Gefehlt', value: 'F' },
            ].filter((item) => enabled.includes(item.value))
        },
        gradeInputValidation(value, mode, type) {
            const grade = String(value ?? '').trim()
            if (this.uses_entry_areas_for_grading_schema && ['NA', 'VL', 'F'].includes(grade)) {
                return this.specialGradeItemsForType(type).some((item) => item.value === grade)
                    || 'Diese Zusatzeigenschaft ist für diesen Eintrag nicht aktiviert.'
            }
            if (!['plus', 'plus_minus', 'points'].includes(mode)) return true
            if (!grade) return true
            if (mode === 'points') {
                const normalized = this.normalizedPointsGrade(grade)
                const maximum = this.maximumPointsForType(type)
                return (/^\d+(?:\.\d+)?$/.test(normalized) && normalized.length <= 50
                    && Number.isFinite(maximum) && maximum > 0 && Number(normalized) <= maximum)
                    || this.gradeInputHint(mode, type)
            }
            const pattern = mode === 'plus' ? /^\+{1,50}$/ : /^(?:\+{1,50}|-{1,50})$/
            return pattern.test(grade) || this.gradeInputHint(mode)
        },
        toggledCourseWorkGrade(currentGrade, selectedGrade) {
            const selectedValue = String(selectedGrade ?? '')

            return String(currentGrade ?? '') === selectedValue ? '' : selectedValue
        },
        courseWorkEntryStudentComment(entry) {
            return this.courseWorkStudentComment(entry, this.registeredEntryStudentId)
        },
        courseWorkStudentComment(entry, studentId) {
            const group = this.courseWorkGroupForCellEntry(entry, studentId)
            if (!group) return ''

            return this.workGroupStudentComment(group, studentId)
                || String(group.comment || '')
        },
        courseWorkEntryStudentGrade(entry) {
            return this.courseWorkStudentGrade(entry, this.registeredEntryStudentId)
        },
        courseWorkStudentGrade(entry, studentId) {
            const group = this.courseWorkGroupForCellEntry(entry, studentId)

            return group ? this.workGroupStudentGrade(group, studentId) : ''
        },
        resetCourseWorkEntryDrafts() {
            this.courseWorkEntryDrafts = this.cellEntries
                .filter((entry) => entry.source === 'course_work' && this.courseWorkForCellEntry(entry))
                .reduce((drafts, entry) => {
                    const comment = this.courseWorkEntryStudentComment(entry)
                    const grade = this.courseWorkEntryStudentGrade(entry)

                    drafts[entry.uid] = {
                        comment,
                        grade,
                        originalComment: comment,
                        originalGrade: grade,
                    }

                    return drafts
                }, {})
        },
        courseWorkEntryDraft(entry) {
            return this.courseWorkEntryDrafts[entry?.uid] || {
                comment: '',
                grade: '',
                originalComment: '',
                originalGrade: '',
            }
        },
        updateCourseWorkEntryDraft(entry, field, value) {
            if (!['comment', 'grade'].includes(field) || !entry?.uid) return

            const currentDraft = this.courseWorkEntryDraft(entry)
            this.courseWorkEntryDrafts = {
                ...this.courseWorkEntryDrafts,
                [entry.uid]: {
                    ...currentDraft,
                    [field]: String(value ?? ''),
                },
            }
        },
        courseWorkEntryHasChanges(entry) {
            const draft = this.courseWorkEntryDraft(entry)

            return draft.comment !== draft.originalComment || draft.grade !== draft.originalGrade
        },
        courseWorkWithStudentEvaluation(work, studentId, draft) {
            if (!work?.id || !studentId) return null

            const updatedWork = {
                ...work,
                groups: this.cloneDateWorkGroups(work?.groups),
            }
            let groupIndex = this.courseWorkGroupIndexForStudent(updatedWork, studentId)
            if (groupIndex < 0 && !updatedWork.is_group_work) {
                updatedWork.groups.push({
                    student_ids: [studentId],
                    date: updatedWork.date_for_all_groups || '',
                    comment: null,
                    grade: null,
                    grades: [],
                    comments: [],
                    points: [],
                })
                groupIndex = updatedWork.groups.length - 1
            }
            if (groupIndex < 0) return null

            const group = updatedWork.groups[groupIndex]
            const studentIds = Array.isArray(group.student_ids) ? group.student_ids : []
            const grades = studentIds.map((groupStudentId) => ({
                student_id: groupStudentId,
                grade: String(groupStudentId) === String(studentId)
                    ? String(draft.grade || '')
                    : this.workGroupStudentGrade(group, groupStudentId),
            }))
            const comments = studentIds.map((groupStudentId) => ({
                student_id: groupStudentId,
                comment: String(groupStudentId) === String(studentId)
                    ? String(draft.comment || '')
                    : this.workGroupStudentComment(group, groupStudentId) || String(group.comment || ''),
            }))

            group.grade = null
            group.grades = grades
            group.comments = comments
            group.use_individual_grades = true

            return updatedWork
        },
        async saveCourseWorkCellEntry(entry) {
            if (!entry?.uid || this.courseWorkEntrySavingUid) return

            const work = this.courseWorkForCellEntry(entry)
            const draft = this.courseWorkEntryDraft(entry)
            const gradeInputMode = this.courseWorkEntryGradeInputMode(entry)
            if ((['plus', 'plus_minus', 'points'].includes(gradeInputMode) || ['NA', 'VL', 'F'].includes(String(draft.grade ?? '').trim()))
                && this.gradeInputValidation(draft.grade, gradeInputMode, work?.type) !== true) return
            const updatedWork = this.courseWorkWithStudentEvaluation(work, this.registeredEntryStudentId, {
                ...draft,
                grade: gradeInputMode === 'none' ? '' : gradeInputMode === 'points' ? this.normalizedPointsGrade(draft.grade) : draft.grade,
            })
            if (!updatedWork) return

            this.courseWorkEntrySavingUid = entry.uid
            try {
                const response = await this.courseWorkStore.update(updatedWork)
                if (!response) return

                this.applySavedCourseWork(response)
                await this.loadCourseTableData(this.selected_course?.id, true)
                this.resetCourseWorkEntryDrafts()
                this.selectedCellEntryUid = null
            } finally {
                this.courseWorkEntrySavingUid = null
            }
        },
        startNewCellEntry() {
            if (!this.canCreateCellEntry) return

            this.selectedCellEntryUid = null
            this.entryForm = {
                description: '',
                doneDate: null,
                grade: '',
                id: null,
                kind: 'assessment',
                dueDate: null,
                type: '',
                uid: null,
            }
            this.entryFormOpen = true
        },
        startEditingCellEntry(entry) {
            if (!this.canModifyCellEntry(entry)) return

            this.selectedCellEntryUid = entry.uid
            this.entryForm = {
                description: String(entry.description || ''),
                doneDate: entry.done_date || null,
                grade: entry.kind === 'assessment' ? String(entry.grade || '') : '',
                id: entry.id,
                kind: entry.kind || 'behaviour',
                dueDate: entry.due_date || null,
                type: String(entry.type || ''),
                uid: entry.uid,
            }
            this.entryFormOpen = true

            if (this.entryHasNotificationWorkflow?.(entry)) {
                this.loadEntryNotificationRecipients?.(entry)
            }
        },
        cancelNewCellEntry() {
            this.entryFormOpen = false
            this.resetEntryNotificationState?.()
            this.entryForm = {
                description: '',
                doneDate: null,
                grade: '',
                id: null,
                kind: 'assessment',
                dueDate: null,
                type: '',
                uid: null,
            }
        },
        canModifyCellEntry(entry) {
            return Boolean(entry?.id && entry?.source !== 'course_work')
        },
        canTransferCellEntry(entry) {
            return this.canModifyCellEntry(entry)
                && Boolean(entry.type && this.entryDialog.courseDate?.id)
                && !this.isFreeCourseDate(this.entryDialog.courseDate)
        },
        startEntryTransfer(entry) {
            if (!this.canTransferCellEntry(entry) || this.entrySaving || this.entryDeleting || this.entryNotificationSending || this.entryTransferSaving || this.courseWorkEntrySavingUid) return

            const courseDate = this.entryDialog.courseDate
            this.entryTransfer = {
                courseId: this.selected_course.id,
                courseDate,
                entry: { ...entry },
                userIds: [],
            }
            this.entryTransferError = ''
            this.closeEntryDialog()
        },
        isEntryTransferTarget(student, courseDate) {
            const transfer = this.entryTransfer
            const userId = this.registeredStudentUserId(student)

            return Boolean(transfer && userId
                && String(transfer.courseId) === String(this.selected_course?.id)
                && String(courseDate?.id) === String(transfer.courseDate.id)
                && String(userId) !== String(transfer.entry.user_id)
                && !this.isStudentCanceled(student)
                && !this.isFreeCourseDate(courseDate))
        },
        isEntryTransferCellSelected(student, courseDate) {
            return this.isEntryTransferTarget(student, courseDate)
                && this.entryTransfer.userIds.includes(String(this.registeredStudentUserId(student)))
        },
        toggleEntryTransferTarget(student, courseDate) {
            if (this.entryTransferSaving || !this.isEntryTransferTarget(student, courseDate)) return

            const userId = String(this.registeredStudentUserId(student))
            const selected = this.entryTransfer.userIds
            this.entryTransfer.userIds = selected.includes(userId)
                ? selected.filter((id) => id !== userId)
                : [...selected, userId]
            this.entryTransferError = ''
        },
        cancelEntryTransfer() {
            if (this.entryTransferSaving) return

            this.entryTransfer = null
            this.entryTransferError = ''
        },
        async confirmEntryTransfer() {
            const transfer = this.entryTransfer
            if (!transfer?.userIds.length || this.entryTransferSaving) return

            this.entryTransferSaving = true
            this.entryTransferError = ''
            try {
                const response = await this.entryStore.transfer(transfer.entry.id, {
                    course_date_id: transfer.courseDate.id,
                    user_ids: [...transfer.userIds],
                }, transfer.entry.kind)
                if (this.entryTransferDisposed || String(this.selected_course?.id) !== String(transfer.courseId)) return
                if (!response) {
                    if (this.entryTransfer === transfer) {
                        this.entryTransferError = 'Übertragen fehlgeschlagen. Die Auswahl bleibt erhalten.'
                    }
                    return
                }

                const store = transfer.entry.kind === 'assessment' ? this.entryStore : this.behaviourEntryStore
                store.courseEntries = [...response.data, ...(store.courseEntries || [])]
                if (this.entryTransfer === transfer) this.entryTransfer = null
            } finally {
                this.entryTransferSaving = false
            }
        },
        isCellEntryExpanded(entry) {
            return this.selectedCellEntryUid === entry?.uid
        },
        selectCellEntry(entry) {
            if (!entry?.uid) return

            this.cancelNewCellEntry()
            this.selectedCellEntryUid = entry.uid

            if (this.canModifyCellEntry(entry)) {
                this.startEditingCellEntry(entry)
            }
        },
        toggleCellEntry(entry) {
            if (!entry?.uid) return

            if (this.selectedCellEntryUid === entry.uid) {
                if (this.entryForm.uid === entry.uid) {
                    this.cancelNewCellEntry()
                }

                this.selectedCellEntryUid = null

                return
            }

            this.selectCellEntry(entry)
        },
        async selectCellEntryType(type) {
            if (this.entryForm.id) return

            this.entryForm.type = this.entryForm.type === type ? '' : type
            this.entryForm.grade = ''

            if (!this.entryForm.id) {
                await this.loadDraftEntryNotificationRecipients()
            }
        },
        entryTypeCategoryColor(category) {
            return {
                Benotung: 'primary',
                Verhalten: 'warning',
                Weitere: 'error',
                Verständigung: 'info',
            }[category] || 'default'
        },
        async saveCellEntry() {
            if (!this.canSaveCellEntry || this.entryNotificationLoading) return

            this.entrySaving = true
            try {
                const date = this.normalizeDateKey(this.entryDialog.courseDate?.date) || null
                const description = String(this.entryForm.description || '').trim() || null
                const isCreatingEntry = !this.entryForm.id
                const notificationRecipientKeys = isCreatingEntry
                    && Array.isArray(this.selectedEntryNotificationRecipientKeys)
                    ? [...this.selectedEntryNotificationRecipientKeys]
                    : []
                let response

                if (isCreatingEntry) {
                    response = await this.entryStore.store({
                        teaching_course_id: this.selected_course.id,
                        user_id: this.registeredEntryStudentId,
                        type: this.entryForm.type,
                        grade: this.selectedEntryTypeCategory === 'Benotung'
                            ? this.availableEntryGradeInputMode === 'points'
                                ? this.normalizedPointsGrade(this.entryForm.grade) || null
                                : this.entryForm.grade || null
                            : null,
                        date,
                        description,
                    })
                } else if (this.entryForm.kind === 'assessment') {
                    response = await this.entryStore.update({
                        id: this.entryForm.id,
                        type: this.entryForm.type,
                        grade: this.selectedEntryTypeCategory === 'Benotung'
                            ? this.availableEntryGradeInputMode === 'points'
                                ? this.normalizedPointsGrade(this.entryForm.grade) || null
                                : this.entryForm.grade || null
                            : null,
                        date,
                        description,
                    })
                } else {
                    response = await this.behaviourEntryStore.update({
                        id: this.entryForm.id,
                        kind: this.entryForm.kind,
                        type: this.entryForm.type,
                        date,
                        description,
                        is_due: Boolean(this.entryForm.dueDate),
                        due_date: this.entryForm.dueDate,
                        is_done: Boolean(this.entryForm.doneDate),
                        done_date: this.entryForm.doneDate,
                    })
                }

                if (response && isCreatingEntry && notificationRecipientKeys.length) {
                    const createdEntryId = response.data?.id

                    if (createdEntryId) {
                        await this.entryStore.sendNotifications(createdEntryId, notificationRecipientKeys)
                    }
                }

                if (response) {
                    this.cancelNewCellEntry()
                    this.selectedCellEntryUid = null
                }
            } finally {
                this.entrySaving = false
            }
        },
        openDeleteEntryDialog(entry) {
            if (!this.canModifyCellEntry(entry)) return

            this.deleteEntryDialog = {
                entry,
                open: true,
            }
        },
        closeDeleteEntryDialog() {
            if (this.entryDeleting) return

            this.deleteEntryDialog = {
                entry: null,
                open: false,
            }
        },
        async confirmDeleteCellEntry() {
            const entry = this.deleteEntryDialog.entry
            if (!this.canModifyCellEntry(entry) || this.entryDeleting) return

            this.entryDeleting = true
            try {
                const store = entry.kind === 'assessment' ? this.entryStore : this.behaviourEntryStore
                const deleted = await store.destroy(entry.id)
                if (deleted) {
                    if (this.selectedCellEntryUid === entry.uid) {
                        this.selectedCellEntryUid = null
                    }
                    if (this.entryForm.uid === entry.uid) {
                        this.cancelNewCellEntry()
                    }
                    this.deleteEntryDialog = {
                        entry: null,
                        open: false,
                    }
                }
            } finally {
                this.entryDeleting = false
            }
        },
        isEntryDialogCellSelected(student, courseDate) {
            if (!this.entryDialog.open) return false

            return String(this.entryDialog.student?.id) === String(student?.id)
                && String(this.entryDialog.courseDate?.id) === String(courseDate?.id)
        },
        openBulkAttendanceDialog(courseDate, present) {
            if (!this.isAttendanceToggleable(courseDate)) return

            this.bulkAttendanceDialog = {
                courseDate,
                open: true,
                present,
            }
        },
        closeBulkAttendanceDialog() {
            if (this.bulkAttendanceSaving) return

            this.bulkAttendanceDialog = {
                courseDate: null,
                open: false,
                present: true,
            }
        },
        bulkAttendanceMap(present) {
            if (present === null) return {}

            return this.sortedSelectedStudents.reduce((attendance, student) => {
                if (student?.id) {
                    attendance[String(student.id)] = present
                }

                return attendance
            }, {})
        },
        async confirmBulkAttendance() {
            const courseDate = this.bulkAttendanceDialog.courseDate
            if (!courseDate?.id || this.bulkAttendanceSaving) return

            this.bulkAttendanceSaving = true

            const previousDate = { ...courseDate, attendance: { ...(courseDate.attendance || {}) } }
            const nextAttendance = this.bulkAttendanceMap(this.bulkAttendanceDialog.present)
            const attendanceChecked = this.bulkAttendanceDialog.present === null ? false : this.isAttendanceChecked(courseDate)
            const optimisticDate = { ...courseDate, attendance: nextAttendance, attendance_checked: attendanceChecked }
            this.applyUpdatedCourseDate(optimisticDate)

            try {
                const response = await this.courseDateStore?.updateStatus(courseDate.id, {
                    attendance: nextAttendance,
                    attendance_checked: attendanceChecked,
                })
                if (response) {
                    this.applyUpdatedCourseDate({
                        ...response,
                        attendance: this.getAttendanceMap(response),
                    })
                    this.bulkAttendanceDialog = {
                        courseDate: null,
                        open: false,
                        present: true,
                    }
                } else {
                    this.applyUpdatedCourseDate(previousDate)
                }
            } finally {
                this.bulkAttendanceSaving = false
            }
        },
        isAttendanceToggleable(courseDate) {
            if (!courseDate?.id) return false

            return !this.isFreeCourseDate(courseDate)
        },
        attendanceCellKey(student, courseDate) {
            return `${courseDate?.id || 'date'}:${student?.id || 'student'}`
        },
        isAttendanceCellSaving(student, courseDate) {
            return Boolean(this.savingAttendanceCells[this.attendanceCellKey(student, courseDate)])
        },
        attendanceMarkerTitle(student, courseDate) {
            const state = this.studentAttendanceState(student, courseDate)
            const current = state === null ? 'ungeprüft' : state ? 'anwesend' : 'abwesend'
            const next = state === null ? 'abwesend' : state ? 'ungeprüft' : 'anwesend'

            return `${this.studentName(student)} - ${this.compactCourseDateTitle(courseDate)}: ${current}. Klicken: ${next}`
        },
        activateStudentCell(student, courseDate) {
            if (this.entryTransfer) {
                return this.toggleEntryTransferTarget(student, courseDate)
            }
            if (this.tableView === 'attendance') {
                return this.toggleStudentAttendance(student, courseDate)
            }

            return this.openEntryDialog(student, courseDate)
        },
        isStudentPresentForCourseDate(student, courseDate) {
            if (!student?.id || !courseDate) return false
            const attendance = this.getAttendanceMap(courseDate)

            return this.isAttendancePresentValue(attendance[String(student.id)])
        },
        studentAttendanceState(student, courseDate) {
            if (!student?.id || !courseDate) return null
            const attendance = this.getAttendanceMap(courseDate)
            const studentId = String(student.id)
            if (Object.prototype.hasOwnProperty.call(attendance, studentId)) return attendance[studentId]

            return this.isAttendanceChecked(courseDate) ? true : null
        },
        studentPresencePercentage(student) {
            const attendanceStates = this.sortedCourseDates
                .filter((courseDate) => this.isAttendanceToggleable(courseDate))
                .map((courseDate) => this.studentAttendanceState(student, courseDate))
                .filter((state) => state !== null)
            if (!attendanceStates.length) return null

            const presentDates = attendanceStates.filter((state) => state === true)

            return Math.round((presentDates.length / attendanceStates.length) * 100)
        },
        getAttendanceMap(courseDate) {
            if (!courseDate) return {}
            if (courseDate.attendance && typeof courseDate.attendance === 'object') {
                const attendance = {}
                Object.entries(courseDate.attendance).forEach(([studentId, value]) => {
                    const key = String(studentId || '').startsWith('s_')
                        ? String(studentId).substring(2)
                        : String(studentId || '').trim()
                    if (!key) return
                    attendance[key] = value
                })

                return this.sanitizeAttendanceMap(attendance)
            }

            const attendance = {}
            const status = Array.isArray(courseDate?.status) ? courseDate.status : []
            status.forEach((item) => {
                if (typeof item !== 'string' || !item.startsWith('att:')) return
                const parts = item.split(':')
                const studentId = String(parts[1] || '').trim()
                const present = String(parts[2] || '').trim()
                if (!studentId) return
                attendance[studentId] = present === 'null' ? null : ['1', 'true'].includes(present)
            })

            return this.sanitizeAttendanceMap(attendance)
        },
        sanitizeAttendanceMap(attendanceLike) {
            const input = attendanceLike && typeof attendanceLike === 'object' ? attendanceLike : {}
            const sanitized = {}

            Object.entries(input).forEach(([studentId, value]) => {
                const key = String(studentId || '').trim()
                if (!key) return
                sanitized[key] = value === null ? null : this.isAttendancePresentValue(value)
            })

            return sanitized
        },
        isAttendancePresentValue(value) {
            if ([true, 1, '1', 'true'].includes(value)) return true
            if (value === false) return false
            if (value === 0) return false
            if (value === '0') return false
            if (value === 'false') return false

            return null
        },
        isAttendanceChecked(courseDate) {
            if (typeof courseDate?.attendance_checked === 'boolean') return courseDate.attendance_checked
            const status = Array.isArray(courseDate?.status) ? courseDate.status : []

            return status.includes('att_checked:1')
        },
        applyUpdatedCourseDate(updatedDate) {
            if (!updatedDate?.id || !this.selected_course?.id) return

            const dates = Array.isArray(this.selected_course.course_dates) ? [...this.selected_course.course_dates] : []
            const index = dates.findIndex((date) => String(date?.id) === String(updatedDate.id))
            if (index >= 0) {
                dates.splice(index, 1, { ...dates[index], ...updatedDate })
            } else {
                dates.push(updatedDate)
            }
            this.selected_course.course_dates = dates

            if (String(this.selected_courseDate?.id) === String(updatedDate.id)) {
                this.selected_courseDate = { ...this.selected_courseDate, ...updatedDate }
            }

            if (String(this.entryDialog?.courseDate?.id) === String(updatedDate.id)) {
                this.entryDialog = {
                    ...this.entryDialog,
                    courseDate: { ...this.entryDialog.courseDate, ...updatedDate },
                }
            }
        },
        async setEntryDialogAttendance(present) {
            const { student, courseDate } = this.entryDialog
            if (!student || !courseDate) return

            await this.toggleStudentAttendance(student, courseDate, present)
        },
        async toggleStudentAttendance(student, courseDate, requestedState = undefined) {
            if (!student?.id || !this.isAttendanceToggleable(courseDate) || this.isAttendanceCellSaving(student, courseDate)) return

            const cellKey = this.attendanceCellKey(student, courseDate)
            this.savingAttendanceCells = { ...this.savingAttendanceCells, [cellKey]: true }

            const previousDate = { ...courseDate, attendance: { ...(courseDate.attendance || {}) } }
            const attendance = this.getAttendanceMap(courseDate)
            const studentId = String(student.id)
            const currentState = Object.prototype.hasOwnProperty.call(attendance, studentId)
                ? attendance[studentId]
                : this.isAttendanceChecked(courseDate) ? true : null
            const nextState = requestedState === undefined
                ? currentState === null ? false : currentState === false ? true : null
                : requestedState
            attendance[studentId] = nextState

            const nextAttendance = this.sanitizeAttendanceMap(attendance)
            const optimisticDate = { ...courseDate, attendance: nextAttendance }
            this.applyUpdatedCourseDate(optimisticDate)

            try {
                const response = await this.courseDateStore?.updateStatus(courseDate.id, {
                    toggle_student_id: student.id,
                    attendance_state: nextState,
                })
                if (response) {
                    this.applyUpdatedCourseDate({
                        ...response,
                        attendance: this.getAttendanceMap(response),
                    })
                } else {
                    this.applyUpdatedCourseDate(previousDate)
                }
            } finally {
                const remainingCells = { ...this.savingAttendanceCells }
                delete remainingCells[cellKey]
                this.savingAttendanceCells = remainingCells
            }
        },
        targetInitialScrollCourseDate(referenceDate = new Date()) {
            const todayKey = this.dateKey(referenceDate)
            const datedCourseDates = this.sortedCourseDates.filter((courseDate) => this.normalizeDateKey(courseDate?.date))
            if (!datedCourseDates.length) return null

            const todayCourseDate = datedCourseDates.find((courseDate) => this.normalizeDateKey(courseDate.date) === todayKey)
            if (todayCourseDate) return todayCourseDate

            return datedCourseDates.find((courseDate) => this.normalizeDateKey(courseDate.date) > todayKey)
                || datedCourseDates[datedCourseDates.length - 1]
        },
        scrollToInitialCourseDate() {
            this.$nextTick(() => {
                const scrollContainer = this.$refs.courseTableScroll
                const targetCourseDate = this.targetInitialScrollCourseDate()
                if (!scrollContainer || !targetCourseDate) return

                const targetKey = this.courseDateScrollKey(targetCourseDate)
                const targetColumn = Array.from(scrollContainer.querySelectorAll('[data-course-date-key]'))
                    .find((element) => element.dataset.courseDateKey === targetKey)
                if (!targetColumn) return

                scrollContainer.scrollLeft = Math.max(
                    targetColumn.offsetLeft - (scrollContainer.clientWidth / 2) + (targetColumn.offsetWidth / 2),
                    0,
                )
            })
        },
        courseDateScrollKey(courseDate) {
            return `course-date-${courseDate?.id || this.normalizeDateKey(courseDate?.date) || 'unknown'}`
        },
        studentName(student) {
            return [student?.last_name, student?.first_name]
                .map((value) => String(value || '').trim())
                .filter(Boolean)
                .join(', ') || 'Schüler:in ohne Namen'
        },
        studentLastName(student) {
            return String(student?.last_name || '').trim() || 'Schüler:in'
        },
        studentFirstName(student) {
            return String(student?.first_name || '').trim()
        },
        studentClassValue(student) {
            return (student?.schoolclass || student?.class || '').toString()
        },
        studentComment(student) {
            const comment = String(student?.comment || '').trim()
            if (!comment) return ''

            const spacedComment = comment
                .replace(/<br\s*\/?>/gi, ' ')
                .replace(/<\/(?:p|div|li|h[1-6])>/gi, ' ')
            if (typeof DOMParser !== 'undefined') {
                const document = new DOMParser().parseFromString(spacedComment, 'text/html')

                return (document.body?.textContent || '').replace(/\s+/g, ' ').trim()
            }

            return spacedComment.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
        },
        studentTooltipDetails(student) {
            const details = [
                ['Klasse', this.studentClassValue(student)],
                ['Geschlecht', this.studentSexTitle(student)],
                ['E-Mail', student?.email_is_placeholder ? '' : String(student?.email || '').trim()],
                ['Telefon', String(student?.phone || '').trim()],
                ['1. Semester', String(student?.sem_1_grade || '').trim()],
                ['2. Semester', String(student?.sem_2_grade || '').trim()],
                ['Jahresnote', String(student?.sem_grade || '').trim()],
                ['Verhalten 1. Semester', String(student?.behaviour_1_grade || '').trim()],
                ['Verhalten 2. Semester', String(student?.behaviour_2_grade || '').trim()],
                ['Verhalten gesamt', String(student?.behaviour_grade || '').trim()],
            ]

            return details
                .filter(([, value]) => value)
                .map(([label, value]) => ({ label, value }))
        },
        normalizedStudentSex(student) {
            return String(student?.sex || '').trim().toLowerCase()
        },
        studentSexIcon(student) {
            const sex = this.normalizedStudentSex(student)
            if (sex === 'm') return 'mdi-gender-male'
            if (sex === 'w' || sex === 'f') return 'mdi-gender-female'
            if (sex === 'd') return 'mdi-gender-non-binary'

            return ''
        },
        studentSexColor(student) {
            const sex = this.normalizedStudentSex(student)
            if (sex === 'm') return 'blue'
            if (sex === 'w' || sex === 'f') return 'pink'
            if (sex === 'd') return 'amber-darken-2'

            return undefined
        },
        studentSexTitle(student) {
            const sex = this.normalizedStudentSex(student)
            if (sex === 'm') return 'männlich'
            if (sex === 'w' || sex === 'f') return 'weiblich'
            if (sex === 'd') return 'divers'

            return ''
        },
        isStudentCanceled(student) {
            return !!student?.canceled_at || !!student?.deleted_at
        },
        compareStudentsBySelectedSort(first, second) {
            const firstLastName = (first?.last_name || '').toString()
            const secondLastName = (second?.last_name || '').toString()
            const firstFirstName = (first?.first_name || '').toString()
            const secondFirstName = (second?.first_name || '').toString()
            const firstClass = this.studentClassValue(first)
            const secondClass = this.studentClassValue(second)

            if (this.students_sort_mode === 'last_name_first_name') {
                const lastNameComparison = firstLastName.localeCompare(secondLastName, 'de', { sensitivity: 'base' })
                if (lastNameComparison !== 0) return lastNameComparison

                const firstNameComparison = firstFirstName.localeCompare(secondFirstName, 'de', { sensitivity: 'base' })
                if (firstNameComparison !== 0) return firstNameComparison

                return firstClass.localeCompare(secondClass, 'de', { numeric: true, sensitivity: 'base' })
            }

            const classComparison = firstClass.localeCompare(secondClass, 'de', { numeric: true, sensitivity: 'base' })
            if (classComparison !== 0) return classComparison

            const lastNameComparison = firstLastName.localeCompare(secondLastName, 'de', { sensitivity: 'base' })
            if (lastNameComparison !== 0) return lastNameComparison

            return firstFirstName.localeCompare(secondFirstName, 'de', { sensitivity: 'base' })
        },
        getWeekdayShort(date) {
            if (!date) return ''
            const parsedDate = parseLocalDate(date)
            if (isNaN(parsedDate.getTime())) return ''

            return parsedDate.toLocaleDateString('de-DE', { weekday: 'short' })
        },
        formatDateShort(date) {
            if (!date) return ''
            const parsedDate = parseLocalDate(date)
            if (isNaN(parsedDate.getTime())) return ''

            return parsedDate.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
        },
        normalizeDateKey(date) {
            if (date === null || date === undefined || date === '') return ''

            const parsedDate = parseLocalDate(date)
            if (isNaN(parsedDate.getTime())) return ''

            return this.dateKey(parsedDate)
        },
        calendarDaysBetween(startDate, finishDate) {
            const startDateKey = this.normalizeDateKey(startDate)
            const finishDateKey = this.normalizeDateKey(finishDate)
            if (!startDateKey || !finishDateKey) return null

            const utcTimestamp = (dateKey) => {
                const [year, month, day] = dateKey.split('-').map(Number)

                return Date.UTC(year, month - 1, day)
            }
            const millisecondsPerDay = 24 * 60 * 60 * 1000

            return Math.round(
                (utcTimestamp(finishDateKey) - utcTimestamp(startDateKey)) / millisecondsPerDay,
            )
        },
        courseWorkDurationLabel(work) {
            const durationDays = this.calendarDaysBetween(
                work?.date_for_all_groups,
                work?.finish_until_date,
            )
            if (!Number.isInteger(durationDays)) return ''

            return durationDays === 1 ? '1 Tag' : `${durationDays} Tage`
        },
        dateKey(date) {
            const parsedDate = parseLocalDate(date)
            if (isNaN(parsedDate.getTime())) return ''

            const year = parsedDate.getFullYear()
            const month = String(parsedDate.getMonth() + 1).padStart(2, '0')
            const day = String(parsedDate.getDate()).padStart(2, '0')

            return `${year}-${month}-${day}`
        },
    },
}
</script>

<style scoped>
.course-table-card {
    border: 1px solid rgba(37, 99, 235, 0.16);
    overflow: hidden;
}

.course-table-scroll {
    max-width: 100%;
    overflow: auto;
}

.course-table {
    --course-table-free-cell-background: #e8f5e9;

    border-collapse: separate;
    border-spacing: 0;
    min-width: 760px;
    table-layout: auto;
    width: max-content;
}

.course-table th,
.course-table td {
    border-bottom: 1px solid rgba(148, 163, 184, 0.18);
    border-right: 1px solid rgba(148, 163, 184, 0.14);
    padding: 8px 7px;
    vertical-align: middle;
}

.course-table thead th {
    background: #eff6ff;
    color: #1e3a8a;
    font-size: 0.74rem;
    font-weight: 800;
    position: sticky;
    text-align: left;
    top: 0;
    z-index: 3;
}

.course-table-student-col,
.course-table-student-cell {
    left: 0;
    min-width: 132px;
    position: sticky;
    width: 1%;
    white-space: nowrap;
}

.course-table-student-col {
    z-index: 4 !important;
}

.course-table-student-cell {
    background: linear-gradient(180deg, #ffffff 0%, #fbfefc 100%);
    text-align: left;
    z-index: 2;
}

.course-table-date-col {
    min-width: 88px;
    width: 88px;
}

.course-table-header-label {
    font-size: 0.78rem;
    font-weight: 850;
}

.course-table-date-header {
    align-items: center;
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-height: 44px;
    text-align: center;
}

.course-table-date-attendance-actions {
    align-items: center;
    display: flex;
    gap: 4px;
    justify-content: center;
}

.course-table-date-attendance-action {
    height: 22px;
    min-width: 22px;
    width: 22px;
}

.course-table-date-title {
    color: #172033;
    font-size: 0.82rem;
    font-weight: 850;
    line-height: 1.2;
}

.course-table-date-weekday {
    color: #1e3a8a;
    font-size: 0.76rem;
    font-weight: 700;
    line-height: 1.1;
}

.course-table-date-hours,
.course-table-sub-text {
    color: #64748b;
    font-size: 0.74rem;
    line-height: 1.25;
}

.course-table-work-label,
.course-table-work-cell {
    --course-table-work-card-height: 66px;

    background: linear-gradient(180deg, #eff6ff 0%, #e8f1ff 100%);
    border-bottom: 2px solid rgba(37, 99, 235, 0.38) !important;
    border-top: 2px solid rgba(37, 99, 235, 0.38);
    height: 58px;
}

.course-table-work-label {
    color: #1d4ed8;
    font-size: 0.78rem;
    font-weight: 850;
    left: 0;
    position: sticky;
    z-index: 2;
}

.course-table-work-label-content {
    align-items: center;
    display: flex;
    gap: 6px;
}

.course-table-work-cell--free {
    background: var(--course-table-free-cell-background);
}

.course-table-work-cell {
    cursor: pointer;
    outline: none;
    position: relative;
    transition: background-color 0.15s ease, box-shadow 0.15s ease;
}

.course-table-work-cell:hover,
.course-table-work-cell:focus-visible {
    background: #dbeafe;
    box-shadow: inset 0 0 0 2px #3b82f6;
}

.course-table-work-cell--selected {
    background: #bfdbfe;
    box-shadow: inset 0 0 0 3px #1d4ed8;
}

.course-table-content-label,
.course-table-content-cell {
    background: #f8fafc;
    height: 44px;
}

.course-table-curriculum-label,
.course-table-curriculum-cell {
    background: #faf5ff;
    border-bottom: 1px solid rgba(126, 34, 206, 0.22) !important;
    height: 52px;
}

.course-table-curriculum-label {
    color: #6b21a8;
    font-size: 0.78rem;
    font-weight: 850;
    left: 0;
    position: sticky;
    z-index: 2;
}

.course-table-curriculum-label-content {
    align-items: center;
    display: flex;
    gap: 6px;
}

.course-table-curriculum-cell {
    color: #581c87;
    cursor: pointer;
    outline: none;
    text-align: left;
    transition: background-color 0.15s ease, box-shadow 0.15s ease;
}

.course-table-curriculum-cell:hover,
.course-table-curriculum-cell:focus-visible {
    background: #ede9fe;
    box-shadow: inset 0 0 0 2px #8b5cf6;
}

.course-table-curriculum-cell--free {
    background: var(--course-table-free-cell-background);
}

.course-table-curriculum-content {
    display: flex;
    flex-direction: column;
    gap: 3px;
    max-width: 74px;
    min-width: 0;
}

.course-table-curriculum-content-item {
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    display: -webkit-box;
    font-size: 0.68rem;
    font-weight: 650;
    line-height: 13px;
    max-height: 39px;
    overflow: hidden;
}

.course-table-curriculum-content-segment {
    display: inline-block;
    margin-right: 0.25em;
    max-width: 100%;
    overflow-wrap: anywhere;
    vertical-align: bottom;
    white-space: normal;
}

.course-table-curriculum-content-segment--separator,
.course-table-curriculum-content-segment:last-child {
    margin-right: 0;
}

.course-table-curriculum-content-more {
    white-space: nowrap;
}

.course-table-curriculum-empty {
    color: #a78bfa;
    display: block;
    text-align: center;
}

.course-table-curriculum-tooltip-title {
    color: #ddd6fe;
    font-size: 0.72rem;
    font-weight: 800;
    margin-bottom: 4px;
    text-transform: uppercase;
}

.course-table-curriculum-tooltip-item {
    line-height: 1.35;
    padding: 3px 0;
}

.course-table-curriculum-tooltip-item + .course-table-curriculum-tooltip-item {
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    margin-top: 3px;
    padding-top: 6px;
}

.course-table-curriculum-dialog-card {
    max-height: min(82vh, 760px);
}

.course-table-curriculum-dialog-title {
    align-items: center;
    display: flex;
    gap: 10px;
    white-space: normal;
}

.course-table-curriculum-dialog-content {
    overflow-y: auto;
}

.course-table-curriculum-date {
    font-size: clamp(1.2rem, 3.5vw, 1.55rem);
    font-weight: 750;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.course-table-curriculum-dialog-list {
    padding: 12px;
}

.course-table-curriculum-chapter {
    background: rgba(var(--v-theme-on-surface), 0.025);
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    border-radius: 10px;
    padding: 4px 0;
    overflow: hidden;
}

.course-table-curriculum-chapter > .v-list-subheader {
    background: rgba(var(--v-theme-on-surface), 0.04);
    font-weight: 650;
}

.course-table-curriculum-chapter + .course-table-curriculum-chapter {
    margin-top: 38px;
}

.course-table-curriculum-unit-heading {
    display: flex;
    align-items: center;
    gap: 6px;
}

.course-table-curriculum-planning {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 6px;
    margin: 6px 0;
    font-size: 0.75rem;
    font-weight: 400;
}

.course-table-curriculum-planned-date {
    padding: 2px 7px;
    border-radius: 8px;
    background: rgba(var(--v-theme-primary), 0.1);
    color: rgb(var(--v-theme-primary));
    overflow-wrap: anywhere;
}

.course-table-curriculum-planned-date--current {
    background: rgba(var(--v-theme-success), 0.14);
    color: rgb(var(--v-theme-success));
    font-weight: 700;
}

.course-table-curriculum-dialog-unit + .course-table-curriculum-dialog-unit {
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}

.course-table-curriculum-unit-action {
    width: 104px;
    min-width: 104px;
    height: 30px;
    margin-left: 12px;
}

.course-table-file-preview {
    display: block;
    width: 100%;
    height: 68vh;
    border: 0;
}

.course-table-curriculum-dialog-loading,
.course-table-curriculum-dialog-empty {
    align-items: center;
    color: #64748b;
    display: flex;
    gap: 12px;
    justify-content: center;
    min-height: 180px;
    padding: 24px;
    text-align: center;
}

.course-table-curriculum-dialog-empty-topic {
    color: #64748b;
    font-size: 0.8rem;
    font-style: italic;
    padding: 0 24px 14px 56px;
}

:deep(.course-table-curriculum-dialog-list .v-list-subheader) {
    min-height: 30px;
    padding-inline: 16px;
}

:deep(.course-table-curriculum-dialog-unit) {
    min-height: 32px !important;
    padding-bottom: 1px;
    padding-top: 1px;
}

:deep(.course-table-curriculum-dialog-unit .v-list-item__prepend > .v-icon) {
    margin-inline-end: 10px;
}

:deep(.course-table-curriculum-dialog-unit .v-list-item-title) {
    font-size: 0.84rem;
    line-height: 1.2;
    white-space: normal;
    overflow-wrap: anywhere;
}

:deep(.course-table-curriculum-dialog-unit .v-btn) {
    min-height: 30px;
}

:deep(.course-table-curriculum-dialog-unit--linked) {
    background: rgba(34, 197, 94, 0.12);
    box-shadow: inset 3px 0 0 #22c55e;
    color: #166534;
    font-weight: 750;
}

.course-table-curriculum-dialog-actions .v-btn {
    min-height: 44px;
}

@media (max-width: 600px) {
    .course-table-curriculum-dialog-card {
        max-height: 88vh;
    }

    .course-table-curriculum-dialog-actions {
        padding: 12px 16px;
    }

    .course-table-curriculum-dialog-actions .v-spacer {
        display: none;
    }

    .course-table-curriculum-dialog-actions .v-btn {
        width: 100%;
    }
}

.course-table-content-label {
    color: #334155;
    font-size: 0.78rem;
    font-weight: 850;
    left: 0;
    position: sticky;
    z-index: 2;
}

.course-table-content-label-content {
    align-items: center;
    display: flex;
    gap: 6px;
}

.course-table-content-cell {
    cursor: pointer;
    outline: none;
    text-align: center;
    transition: background-color 0.15s ease, box-shadow 0.15s ease;
}

.course-table-content-cell:hover,
.course-table-content-cell:focus-visible {
    background: #e0e7ff;
    box-shadow: inset 0 0 0 2px #6366f1;
}

.course-table-content-cell--free {
    background: var(--course-table-free-cell-background);
}

.course-table-content-cell--selected {
    background: #c7d2fe;
    box-shadow: inset 0 0 0 3px #4f46e5;
}

.course-table-content-cell-icon {
    opacity: 0.55;
    transition: opacity 0.15s ease, scale 0.15s ease;
}

.course-table-content-cell-preview {
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    display: -webkit-box;
    font-size: 0.68rem;
    line-height: 13px;
    max-height: 39px;
    overflow: hidden;
    padding: 0 5px;
    text-align: left;
    word-break: break-word;
}

.course-table-content-cell:hover .course-table-content-cell-icon,
.course-table-content-cell:focus-visible .course-table-content-cell-icon {
    opacity: 1;
    scale: 1.12;
}

:deep(.course-table-content-tooltip-html) {
    white-space: normal;
}

:deep(.course-table-content-tooltip-html p) {
    margin: 0.25rem 0;
}

:deep(.course-table-content-tooltip-html h1),
:deep(.course-table-content-tooltip-html h2),
:deep(.course-table-content-tooltip-html h3) {
    font-weight: 800;
    line-height: 1.2;
    margin: 0.45rem 0 0.25rem;
}

:deep(.course-table-content-tooltip-html h1) {
    font-size: 1.25rem;
}

:deep(.course-table-content-tooltip-html h2) {
    font-size: 1.1rem;
}

:deep(.course-table-content-tooltip-html h3) {
    font-size: 1rem;
}

:deep(.course-table-content-tooltip-html ul),
:deep(.course-table-content-tooltip-html ol) {
    margin: 0.45rem 0;
    padding-left: 1.25rem;
}

:deep(.course-table-content-tooltip-html ul) {
    list-style-type: disc;
}

:deep(.course-table-content-tooltip-html ol) {
    list-style-type: decimal;
}

:deep(.course-table-content-tooltip-html blockquote) {
    background: rgba(255, 255, 255, 0.1);
    border-left: 3px solid rgba(255, 255, 255, 0.7);
    margin: 0.5rem 0;
    padding: 0.35rem 0.6rem;
}

:deep(.course-table-content-tooltip-html pre),
:deep(.course-table-content-tooltip-html code) {
    background: rgba(0, 0, 0, 0.24);
    border-radius: 4px;
    font-family: monospace;
}

:deep(.course-table-content-tooltip-html pre) {
    overflow-x: auto;
    padding: 0.5rem;
    white-space: pre-wrap;
}

:deep(.course-table-content-tooltip-html code) {
    padding: 0.08rem 0.2rem;
}

:deep(.course-table-content-tooltip-html pre code) {
    background: transparent;
    padding: 0;
}

:deep(.course-table-content-tooltip-html hr) {
    border-color: rgba(255, 255, 255, 0.35);
    margin: 0.55rem 0;
}

:deep(.course-table-work-tooltip) {
    line-height: 1.35;
    padding: 12px 14px;
}

:deep(.course-table-work-tooltip-item) {
    padding: 4px 0;
}

:deep(.course-table-work-tooltip-item + .course-table-work-tooltip-item) {
    border-top: 1px solid rgba(255, 255, 255, 0.24);
    margin-top: 6px;
    padding-top: 10px;
}

.course-table-group-member-chip {
    cursor: help;
}

:deep(.course-table-group-member-tooltip) {
    line-height: 1.35;
    padding: 12px 14px;
}

.course-table-group-member-tooltip-name {
    font-size: 0.9rem;
    font-weight: 800;
    margin-bottom: 8px;
}

.course-table-group-member-tooltip-grade {
    display: flex;
    gap: 12px;
    justify-content: space-between;
}

.course-table-group-member-tooltip-grade > span,
.course-table-group-member-tooltip-comment > span {
    color: #d1d5db;
}

.course-table-group-member-tooltip-comment {
    border-top: 1px solid rgba(255, 255, 255, 0.24);
    margin-top: 8px;
    padding-top: 8px;
}

.course-table-group-member-tooltip-comment > div {
    margin-top: 3px;
    white-space: pre-wrap;
}

.course-table-work-list {
    align-items: center;
    display: flex;
    flex-direction: column;
    gap: 3px;
    justify-content: center;
    max-width: 118px;
    min-height: 40px;
    width: 100%;
}

.course-table-work-timelines {
    display: grid;
    grid-auto-rows: var(--course-table-work-card-height);
    left: 0;
    pointer-events: none;
    position: absolute;
    right: 0;
    row-gap: 3px;
    top: 30px;
    z-index: 1;
}

.course-table-work-timeline {
    height: var(--course-table-work-card-height);
    position: relative;
    width: 100%;
}

.course-table-work-timeline-line {
    background: linear-gradient(90deg, #2563eb, #60a5fa);
    box-shadow: 0 0 4px rgba(37, 99, 235, 0.48);
    height: 2px;
    left: -1px;
    opacity: 0;
    position: absolute;
    top: calc(var(--course-table-work-card-height) / 2 - 1px);
    transition: opacity 0.15s ease;
}

.course-table-work-timeline--start .course-table-work-timeline-line {
    left: calc(50% + 6px);
    right: -1px;
}

.course-table-work-timeline--middle .course-table-work-timeline-line {
    left: -1px;
    right: -1px;
}

.course-table-work-timeline--arrow .course-table-work-timeline-line {
    right: 8px;
}

.course-table-work-timeline-card {
    background: rgba(37, 99, 235, 0.2);
    border: 1px solid rgba(37, 99, 235, 0.72);
    border-radius: 5px;
    box-shadow: 0 1px 4px rgba(37, 99, 235, 0.38);
    height: var(--course-table-work-card-height);
    left: 50%;
    position: absolute;
    top: 0;
    translate: -50% 0;
    width: 12px;
}

.course-table-work-timeline-card--group {
    background: rgba(22, 163, 74, 0.2);
    border-color: rgba(22, 163, 74, 0.72);
    box-shadow: 0 1px 4px rgba(22, 163, 74, 0.38);
}

.course-table-work-timeline-arrow {
    border-bottom: 6px solid transparent;
    border-left: 9px solid #2563eb;
    border-top: 6px solid transparent;
    filter: drop-shadow(0 0 3px rgba(37, 99, 235, 0.65));
    opacity: 0;
    position: absolute;
    right: -1px;
    top: calc(var(--course-table-work-card-height) / 2 - 6px);
    transition: opacity 0.15s ease;
}

.course-table-work-timeline--visible .course-table-work-timeline-line,
.course-table-work-timeline--visible .course-table-work-timeline-arrow {
    opacity: 1;
}

.course-table-work-empty-icon {
    left: 50%;
    opacity: 0.45;
    position: absolute;
    top: 5px;
    transition: opacity 0.15s ease, scale 0.15s ease;
    translate: -50% 0;
    z-index: 2;
}

.course-table-work-list--has-work {
    padding-top: 30px;
}

.course-table-work-cell:hover .course-table-work-empty-icon,
.course-table-work-cell:focus-visible .course-table-work-empty-icon {
    opacity: 1;
    scale: 1.12;
}

.course-table-work-summary {
    background: rgba(37, 99, 235, 0.12);
    border: 1px solid rgba(37, 99, 235, 0.3);
    border-radius: 7px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
    box-sizing: border-box;
    color: #1e3a8a;
    height: var(--course-table-work-card-height);
    max-width: 112px;
    min-width: 112px;
    overflow: hidden;
    padding: 5px 6px;
    position: relative;
    text-align: left;
    z-index: 2;
}

.course-table-work-summary--group {
    background: rgba(22, 163, 74, 0.12);
    border-color: rgba(22, 163, 74, 0.32);
    color: #166534;
}

.course-table-work-summary-meta {
    align-items: center;
    display: flex;
    font-size: 0.62rem;
    font-weight: 800;
    gap: 3px;
    line-height: 1;
    margin-bottom: 4px;
    text-transform: uppercase;
}

.course-table-work-summary-count {
    align-items: center;
    display: inline-flex;
    gap: 2px;
    margin-left: auto;
}

.course-table-work-summary-title {
    display: -webkit-box;
    font-size: 0.7rem;
    font-weight: 400;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 4;
    line-height: 1.25;
    overflow: hidden;
    overflow-wrap: anywhere;
}

.course-table-date-work-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.course-table-date-work-date-input {
    flex: 0 1 220px;
    min-width: 180px;
}

.course-table-date-work-item {
    align-items: flex-start;
    background: #f8fafc;
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 8px;
    display: flex;
    gap: 12px;
    justify-content: space-between;
    padding: 10px 12px;
}

.course-table-date-work-main {
    min-width: 0;
}

.course-table-date-work-form {
    background: #f8fafc;
    border: 1px solid rgba(37, 99, 235, 0.2);
    border-radius: 10px;
    padding: 12px;
}

.course-table-work-meta-row {
    align-items: start;
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.course-table-work-meta-row--single {
    grid-template-columns: minmax(0, 1fr);
}

.course-table-work-group-card {
    transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}

.course-table-work-group-card--drop-target {
    background: rgba(37, 99, 235, 0.08);
    border-color: #2563eb !important;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.18);
}

.course-table-work-group-date-input {
    flex: 0 1 210px;
    min-width: 180px;
}

.course-table-work-group-student-chip {
    cursor: grab;
}

.course-table-work-group-student-chip:active {
    cursor: grabbing;
}

.course-table-work-group-grade-grid {
    align-items: center;
    display: grid;
    gap: 12px;
    grid-template-columns: minmax(0, 1fr) minmax(240px, 2fr) minmax(100px, 130px);
}

@media (max-width: 600px) {
    .course-table-work-meta-row {
        grid-template-columns: minmax(0, 1fr);
    }

    .course-table-work-group-grade-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

.course-table-row {
    background: #ffffff;
    transition: background 0.15s ease, box-shadow 0.15s ease;
}

.course-table-row:nth-child(even) td,
.course-table-row:nth-child(even) .course-table-student-cell {
    background: #f8fbff;
}

.course-table-row:hover td,
.course-table-row:hover .course-table-student-cell {
    background: #f8fafc;
}

.course-table-main-text {
    align-items: center;
    color: #172033;
    display: flex;
    font-weight: 500;
    justify-content: space-between;
    line-height: 1.12;
    min-width: 0;
    width: 100%;
}

.course-table-name-badges {
    flex: 0 0 auto;
    white-space: nowrap;
}

.course-table-student-name {
    min-width: 0;
    overflow-wrap: anywhere;
}

.course-table-student-confirmation-warning {
    align-items: center;
    background: #dc2626;
    border-radius: 999px;
    color: #ffffff;
    display: inline-flex;
    flex: 0 0 auto;
    font-size: 0.68rem;
    font-weight: 800;
    height: 16px;
    justify-content: center;
    line-height: 1;
    min-width: 16px;
}

.course-table-presence-percentage {
    color: #166534;
    flex-shrink: 0;
    font-size: 0.76rem;
    font-weight: 750;
    margin-left: 12px;
    text-align: right;
}

.course-table-student-subline {
    align-items: center;
    color: #64748b;
    display: flex;
    flex-wrap: wrap;
    font-size: 0.76rem;
    font-weight: 500;
    gap: 5px;
    line-height: 1.08;
    min-width: 0;
}

.course-table-student-class {
    color: #334155;
    font-weight: 650;
}

.course-table-student-comment {
    align-items: flex-start;
    color: #64748b;
    display: flex;
    font-size: 0.7rem;
    font-weight: 450;
    gap: 4px;
    line-height: 1.2;
    margin-top: 5px;
    min-width: 0;
}

.course-table-student-comment-text {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
    overflow-wrap: anywhere;
}

:deep(.course-table-student-tooltip) {
    line-height: 1.35;
    padding: 12px 14px;
}

.course-table-student-tooltip-name {
    font-size: 0.95rem;
    font-weight: 800;
    margin-bottom: 8px;
}

.course-table-student-tooltip-detail {
    display: grid;
    gap: 12px;
    grid-template-columns: minmax(110px, auto) minmax(0, 1fr);
    padding: 2px 0;
}

.course-table-student-tooltip-detail > span,
.course-table-student-tooltip-comment > span {
    color: #d1d5db;
}

.course-table-student-tooltip-comment {
    border-top: 1px solid rgba(255, 255, 255, 0.24);
    margin-top: 8px;
    padding-top: 8px;
}

.course-table-student-tooltip-comment > div {
    margin-top: 3px;
    white-space: normal;
}

.course-table-entry-cell {
    position: relative;
    background: #ffffff;
    text-align: center;
}

.course-table-entry-cell--interactive {
    cursor: pointer;
    outline: none;
    transition: background-color 0.15s ease, box-shadow 0.15s ease;
}

.course-table-entry-cell--absent {
    position: relative;
}

.course-table-entry-cell-attendance-marker {
    pointer-events: none;
    position: absolute;
    right: 3px;
    top: 3px;
    z-index: 1;
}

.course-table-entry-cell--interactive:hover,
.course-table-entry-cell--interactive:focus-visible {
    background: #dbeafe !important;
    box-shadow: inset 0 0 0 2px #3b82f6;
}

.course-table-entry-cell--selected {
    background: #bfdbfe !important;
    box-shadow: inset 0 0 0 3px #1d4ed8;
}

.course-table-transfer-bar {
    background: rgb(var(--v-theme-surface));
    border: 2px solid rgb(var(--v-theme-primary));
    border-radius: 8px;
    position: sticky;
    top: 0;
    z-index: 3;
}

.course-table-entry-cell--transfer-selected {
    background: #dbeafe !important;
    box-shadow: inset 0 0 0 3px #1d4ed8 !important;
}

.course-table-transfer-marker {
    color: #1e3a8a;
    display: block;
    font-size: 11px;
    font-weight: 700;
}

.course-table-entry-cell--transfer-unavailable {
    cursor: not-allowed;
    opacity: 0.5;
}

.course-table-entry-type-rows {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.course-table-entry-type-row {
    align-items: start;
    background: rgba(var(--v-theme-surface-variant), 0.22);
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    border-left-width: 4px;
    border-radius: 10px;
    display: grid;
    gap: 12px;
    grid-template-columns: 94px minmax(0, 1fr);
    padding: 10px 12px;
}

.course-table-entry-type-category {
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 800;
    padding: 5px 8px;
    text-align: center;
}

.course-table-entry-type-row[data-category='Benotung'] {
    background: rgba(var(--v-theme-primary), 0.055);
    border-color: rgba(var(--v-theme-primary), 0.28);
}

.course-table-entry-type-row[data-category='Benotung'] .course-table-entry-type-category {
    background: rgba(var(--v-theme-primary), 0.14);
    color: rgb(var(--v-theme-primary));
}

.course-table-entry-type-row[data-category='Verhalten'] {
    background: rgba(var(--v-theme-warning), 0.07);
    border-color: rgba(var(--v-theme-warning), 0.35);
}

.course-table-entry-type-row[data-category='Verhalten'] .course-table-entry-type-category {
    background: rgba(var(--v-theme-warning), 0.18);
    color: rgb(var(--v-theme-warning));
}

.course-table-entry-type-row[data-category='Weitere'] {
    background: rgba(var(--v-theme-error), 0.065);
    border-color: rgba(var(--v-theme-error), 0.3);
    color: rgb(var(--v-theme-error));
}

.course-table-entry-type-row[data-category='Weitere'] .course-table-entry-type-category {
    background: rgba(var(--v-theme-error), 0.16);
    color: rgb(var(--v-theme-error));
}

@media (max-width: 600px) {
    .course-table-entry-type-row {
        grid-template-columns: 1fr;
    }

    .course-table-entry-type-category {
        justify-self: start;
    }
}

.course-table-column--marked-blue {
    background: #dbeafe !important;
}

.course-table-column--marked-green {
    background: #dcfce7 !important;
}

.course-table-column--marked-orange {
    background: #ffedd5 !important;
}

.course-table-column--marked-purple {
    background: #ede9fe !important;
}

.course-table-column--marked-red {
    background: #fee2e2 !important;
}

.course-table-entry-cell-content {
    align-items: center;
    display: flex;
    flex-direction: column;
    gap: 6px;
    justify-content: center;
}

.course-table-entry-cell-badges {
    align-items: center;
    display: flex;
    flex-direction: column;
    gap: 3px;
    justify-content: center;
    max-width: 100%;
}

.course-table-entry-cell-badge-row {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
    justify-content: center;
    max-width: 100%;
}

.course-table-entry-cell-badge {
    font-size: 0.66rem;
    font-weight: 400;
    max-width: 82px;
}

.course-table-entry-cell-grade--missing {
    color: rgb(var(--v-theme-error));
    font-weight: 400;
}

.course-table-entry-cell-performance {
    align-items: center;
    display: flex;
    flex-direction: column;
    gap: 3px;
    max-width: 92px;
}

:deep(.course-table-entry-tooltip) {
    line-height: 1.35;
    padding: 12px 14px;
}

.course-table-entry-tooltip-header {
    align-items: center;
    display: flex;
    gap: 16px;
    justify-content: space-between;
    margin-bottom: 8px;
}

.course-table-entry-tooltip-header > span,
.course-table-entry-tooltip-meta > span:first-child,
.course-table-entry-tooltip-text > span {
    color: #d1d5db;
}

.course-table-entry-tooltip-item {
    padding: 5px 0;
}

.course-table-entry-tooltip-item + .course-table-entry-tooltip-item {
    border-top: 1px solid rgba(255, 255, 255, 0.24);
    margin-top: 6px;
    padding-top: 10px;
}

.course-table-entry-tooltip-meta {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.course-table-entry-tooltip-meta > span:last-child {
    border: 1px solid rgba(255, 255, 255, 0.48);
    border-radius: 999px;
    padding: 1px 7px;
}

.course-table-entry-tooltip-title {
    font-weight: 700;
    margin-top: 4px;
}

.course-table-entry-tooltip-text {
    margin-top: 4px;
    white-space: pre-wrap;
}

.course-table-cell-entry-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.course-table-cell-entry {
    background: #f8fafc;
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 8px;
    padding: 10px 12px;
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.course-table-cell-entry--selectable,
.course-table-cell-entry--selected .course-table-cell-entry-summary {
    cursor: pointer;
}

.course-table-cell-entry--selectable:hover,
.course-table-cell-entry--selected {
    border-color: rgba(var(--v-theme-primary), 0.55);
    box-shadow: 0 3px 12px rgba(var(--v-theme-primary), 0.1);
}

.course-table-cell-entry:focus-visible {
    border-radius: 6px;
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 3px;
}

.course-table-cell-entry-list-comment {
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}

.course-table-cell-work-entry {
    background: #ffffff;
    border: 1px solid rgba(var(--v-theme-primary), 0.2);
    border-radius: 8px;
    padding: 12px;
}

.course-table-cell-work-info-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.course-table-cell-work-description {
    white-space: pre-wrap;
}

.course-table-cell-work-entry-fields {
    align-items: start;
    display: grid;
    gap: 12px;
    grid-template-columns: minmax(0, 1fr) minmax(140px, 200px);
}

@media (max-width: 600px) {
    .course-table-cell-work-info-grid,
    .course-table-cell-work-entry-fields {
        grid-template-columns: minmax(0, 1fr);
    }
}

.course-table-cell-entry-form {
    background: #f8fafc;
    border: 1px solid rgba(37, 99, 235, 0.2);
    border-radius: 10px;
    padding: 12px;
}

.course-table-curriculum-files {
    margin-top: 4px;
}

.course-table-curriculum-file {
    align-items: center;
    display: grid;
    gap: 6px;
    grid-template-columns: 18px minmax(0, 1fr) auto;
    margin-top: 3px;
}

.course-table-curriculum-file-actions {
    display: flex;
    align-items: center;
    gap: 4px;
}

.course-table-curriculum-file-visibility {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    min-height: 28px;
    padding: 3px 9px;
    border: 1px solid rgba(var(--v-theme-on-surface), 0.16);
    border-radius: 999px;
    background: rgba(var(--v-theme-surface), 0.75);
    color: rgba(var(--v-theme-on-surface), 0.65);
    font-size: 0.7rem;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
    transition: background 150ms ease, border-color 150ms ease;
}

.course-table-curriculum-file-visibility--active {
    border-color: rgba(var(--v-theme-success), 0.3);
    background: rgba(var(--v-theme-success), 0.12);
    color: rgb(var(--v-theme-success));
}

.course-table-curriculum-file-visibility:hover:not(:disabled) {
    border-color: currentColor;
    background: rgba(var(--v-theme-primary), 0.08);
}

.course-table-curriculum-file-visibility:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
}

.course-table-curriculum-file-visibility:disabled {
    opacity: 0.55;
    cursor: progress;
}

@media (max-width: 480px) {
    .course-table-curriculum-file-actions {
        grid-column: 2 / -1;
    }
}

.course-table-curriculum-file-name {
    color: rgb(var(--v-theme-primary));
    font-size: 0.78rem;
    overflow-wrap: anywhere;
    white-space: normal;
    text-align: left;
    text-decoration: underline;
}

button.course-table-curriculum-file-name:disabled {
    opacity: 0.6;
}

.course-table-attendance-marker {
    align-items: center;
    display: inline-flex;
    justify-content: center;
    height: 24px;
    min-width: 24px;
    width: 24px;
}

.course-table thead th.course-table-date-col--free,
.course-table-row td.course-table-entry-cell--free,
.course-table-row:nth-child(even) td.course-table-entry-cell--free {
    background: var(--course-table-free-cell-background);
}

.course-table-entry-cell--free-reason {
    overflow: hidden;
    position: relative;
    text-align: center;
    vertical-align: top;
    width: 88px;
}

.course-table-free-reason {
    color: #1b5e20;
    display: inline-block;
    font-size: 1.24rem;
    font-weight: 750;
    letter-spacing: 0.08em;
    left: 50%;
    line-height: 1.2;
    max-height: calc(100% - 16px);
    opacity: 0.42;
    overflow: hidden;
    position: absolute;
    text-orientation: mixed;
    top: 8px;
    translate: -50% 0;
    white-space: normal;
    writing-mode: vertical-rl;
}

.course-table-empty-row td {
    color: #64748b;
    font-size: 0.86rem;
    padding: 14px 12px;
}

@media (max-width: 700px) {
    .course-table {
        min-width: 680px;
    }

    .course-table-student-col,
    .course-table-student-cell {
        min-width: 120px;
        width: 1%;
    }

    .course-table-date-col {
        min-width: 82px;
        width: 82px;
    }

    .course-table-entry-cell--free-reason {
        width: 82px;
    }

    .course-table th,
    .course-table td {
        padding: 8px 6px;
    }
}

</style>
