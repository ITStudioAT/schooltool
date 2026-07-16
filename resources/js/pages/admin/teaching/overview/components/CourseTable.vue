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
            </v-card-text>
        </v-card>

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
                                    @click="openWorkDialog(courseDate)"
                                    @keydown.enter.prevent="openWorkDialog(courseDate)"
                                    @keydown.space.prevent="openWorkDialog(courseDate)">
                                    <div class="course-table-work-list">
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
                                <th scope="row" class="course-table-student-cell">
                                    <div class="course-table-main-text">
                                        <span class="course-table-student-name">{{ studentLastName(student) }}</span>
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
                                    <div
                                        v-if="studentComment(student)"
                                        class="course-table-student-comment"
                                        :title="studentComment(student)">
                                        <v-icon aria-hidden="true" size="12">mdi-comment-text-outline</v-icon>
                                        <span class="course-table-student-comment-text">
                                            {{ studentComment(student) }}
                                        </span>
                                    </div>
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
                                                'course-table-entry-cell--interactive': tableView === 'entries',
                                                'course-table-entry-cell--absent': tableView === 'entries' && !isStudentPresentForCourseDate(student, courseDate),
                                                'course-table-entry-cell--selected': isEntryDialogCellSelected(student, courseDate),
                                            },
                                            courseDateColumnMarkingClass(courseDate),
                                        ]"
                                        :role="tableView === 'entries' ? 'button' : undefined"
                                        :tabindex="tableView === 'entries' ? 0 : undefined"
                                        @click="openEntryDialog(student, courseDate)"
                                        @keydown.enter.prevent="openEntryDialog(student, courseDate)"
                                        @keydown.space.prevent="openEntryDialog(student, courseDate)">
                                        <v-icon
                                            v-if="tableView === 'entries' && !isStudentPresentForCourseDate(student, courseDate)"
                                            class="course-table-entry-cell-absent-marker"
                                            color="error"
                                            size="14"
                                            aria-label="Abwesend"
                                            title="Abwesend">
                                            mdi-close
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
                                                        {{ compactCellEntryLabel(entry) }}
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
                                                                        'course-table-entry-cell-grade--missing': !compactCellEntryGrade(entry),
                                                                    }">
                                                                    {{ compactCellEntryGrade(entry) || 'NA' }}
                                                                </span>
                                                            </template>
                                                        </v-chip>
                                                        <div
                                                            v-if="entry.description"
                                                            class="course-table-entry-cell-comment"
                                                            :title="entry.description">
                                                            {{ entry.description }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <v-btn
                                                v-if="tableView === 'attendance' && isAttendanceToggleable(courseDate)"
                                                class="course-table-attendance-marker"
                                                :color="isStudentPresentForCourseDate(student, courseDate) ? 'success' : 'error'"
                                                density="compact"
                                                :icon="isStudentPresentForCourseDate(student, courseDate) ? 'mdi-check' : 'mdi-close'"
                                                :loading="isAttendanceCellSaving(student, courseDate)"
                                                size="x-small"
                                                :title="attendanceMarkerTitle(student, courseDate)"
                                                variant="tonal"
                                                @click.stop="toggleStudentAttendance(student, courseDate)" />
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

        <v-dialog v-model="contentDialog.open" persistent max-width="720">
            <v-card data-testid="course-table-content-dialog">
                <v-card-title class="text-subtitle-1 font-weight-bold d-flex align-center ga-2">
                    <v-icon size="20">mdi-text-box-outline</v-icon>
                    Inhalt bearbeiten
                </v-card-title>
                <v-divider />
                <v-card-text class="d-flex flex-column ga-3">
                    <div class="text-body-2">
                        <strong>Termin:</strong> {{ compactCourseDateTitle(contentDialog.courseDate) }}
                    </div>
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
                        @click="saveContentDialog">
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
                                        <v-chip size="x-small" color="secondary" variant="outlined">
                                            {{ assignment.scope }}
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

                    <v-divider />

                    <section>
                        <v-btn
                            v-if="!workDialogFormOpen"
                            data-testid="course-table-date-create-work"
                            color="primary"
                            prepend-icon="mdi-plus"
                            variant="tonal"
                            @click="startCreatingDateWork">
                            Neue Arbeit
                        </v-btn>

                        <div v-if="workDialogFormOpen" class="course-table-date-work-form">
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
                                                    v-else-if="availableWorkGradeInputMode === 'free'"
                                                    :model-value="row.grade"
                                                    density="compact"
                                                    hide-details
                                                    label="Note"
                                                    :maxlength="50"
                                                    variant="outlined"
                                                    :disabled="workSaving"
                                                    @update:model-value="setIndividualWorkStudentGrade(row.studentId, $event)" />
                                                <div v-else class="text-caption text-medium-emphasis">
                                                    Keine Bewertung vorgesehen.
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
                                        v-else-if="availableWorkGradeInputMode === 'free'"
                                        :model-value="row.grade"
                                        density="compact"
                                        hide-details
                                        label="Note"
                                        :maxlength="50"
                                        variant="outlined"
                                        :disabled="workSaving"
                                        @update:model-value="setWorkGroupStudentGrade(workDialogGroupDetails.groupIndex, row.studentId, $event)" />
                                    <div v-else class="text-caption text-medium-emphasis">
                                        Keine Bewertung vorgesehen.
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
                    Anwesenheit setzen
                </v-card-title>
                <v-card-text>
                    Alle Schüler:innen für
                    <strong>{{ bulkAttendanceCourseDateTitle }}</strong>
                    als
                    <strong>{{ bulkAttendanceDialog.present ? 'anwesend' : 'abwesend' }}</strong>
                    markieren?
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
                        :color="bulkAttendanceDialog.present ? 'success' : 'error'"
                        :loading="bulkAttendanceSaving"
                        variant="flat"
                        @click="confirmBulkAttendance">
                        Bestätigen
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
                    <div class="d-flex flex-wrap ga-3 text-body-2">
                        <div><strong>Schüler:in:</strong> {{ studentName(entryDialog.student) }}</div>
                        <div><strong>Termin:</strong> {{ compactCourseDateTitle(entryDialog.courseDate) }}</div>
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
                                            v-else-if="courseWorkEntryGradeInputMode(entry) === 'free'"
                                            :model-value="courseWorkEntryDraft(entry).grade"
                                            clearable
                                            :data-testid="`course-table-cell-work-grade-${entry.uid}`"
                                            density="compact"
                                            hide-details
                                            label="Note"
                                            :maxlength="50"
                                            variant="outlined"
                                            :disabled="Boolean(courseWorkEntrySavingUid)"
                                            @update:model-value="updateCourseWorkEntryDraft(entry, 'grade', $event)" />
                                        <div v-else class="text-caption text-medium-emphasis align-self-center">
                                            Für diesen Eintragstyp ist keine Bewertung vorgesehen.
                                        </div>
                                    </div>
                                    <div class="d-flex justify-end ga-2 mt-3">
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
                                        <div v-else class="text-caption text-medium-emphasis mb-3">Bitte zuerst einen Typ wählen.</div>
                                    </template>

                                    <v-textarea
                                        v-model="entryForm.description"
                                        label="Beschreibung"
                                        rows="2"
                                        :counter="1024"
                                        :maxlength="1024" />

                                    <div class="d-flex align-center ga-2">
                                        <v-btn
                                            :data-testid="`course-table-cell-delete-entry-${entry.uid}`"
                                            color="error"
                                            prepend-icon="mdi-delete"
                                            variant="text"
                                            :disabled="entrySaving"
                                            @click="openDeleteEntryDialog(entry)">
                                            Löschen
                                        </v-btn>
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
                                <div v-else class="text-caption text-medium-emphasis mb-3">Bitte zuerst einen Typ wählen.</div>
                            </template>

                            <v-textarea
                                v-model="entryForm.description"
                                label="Beschreibung"
                                rows="2"
                                :counter="1024"
                                :maxlength="1024" />

                            <div class="d-flex justify-end ga-2">
                                <v-btn variant="text" :disabled="entrySaving" @click="cancelNewCellEntry">Abbrechen</v-btn>
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
    </ItsGridBox>
</template>

<script>
import { mapWritableState } from 'pinia'
import { parseLocalDate } from '@/helpers/date'
import ItsRichTextEditor from '@/components/ItsRichTextEditor.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'

const tableMarkingColors = new Set(['blue', 'green', 'orange', 'purple', 'red'])
const courseContentAllowedTags = new Set([
    'b', 'blockquote', 'br', 'code', 'del', 'div', 'em', 'h1', 'h2', 'h3', 'hr', 'i', 'li', 'ol', 'p', 'pre',
    's', 'strike', 'strong', 'sub', 'sup', 'u', 'ul',
])
const courseContentBlockedTags = new Set([
    'button', 'embed', 'form', 'iframe', 'input', 'link', 'math', 'meta', 'object', 'script', 'select', 'style',
    'svg', 'textarea',
])

export default {
    components: { ItsRichTextEditor },

    emits: ['update:activeSemester'],

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
            entryStore: null,
            selectedCellEntryUid: null,
            randomGroupsDialog: {
                groupSize: 2,
                open: false,
            },
            savingAttendanceCells: {},
            tableView: 'entries',
            workDeleting: false,
            workDialog: {
                courseDate: null,
                open: false,
            },
            workDialogDateEditing: false,
            workDialogForm: {
                date_for_all_groups: '',
                description: '',
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

    watch: {
        async view(view) {
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
        selected_course(course) {
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
            const selectedSemester = Number(this.activeSemester)
            const semesterTwoStartDate = String(this.semesterTwoStartDate || '').slice(0, 10)

            if (![1, 2].includes(selectedSemester) || !/^\d{4}-\d{2}-\d{2}$/.test(semesterTwoStartDate)) {
                return sortedDates
            }

            return sortedDates.filter((courseDate) => {
                const date = String(courseDate?.date || '').slice(0, 10)
                if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return true

                return selectedSemester === 1
                    ? date < semesterTwoStartDate
                    : date >= semesterTwoStartDate
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
        canSaveDateWork() {
            const hasAvailableType = this.availableWorkTypes.some((item) => item.value === this.workDialogForm.type)
            const hasIncompleteNewGroup = this.workDialogForm.is_group_work
                && this.workDialogGroups.some((group) => group?._is_new && !group.student_ids?.length)

            return Boolean(
                hasAvailableType
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

            return Boolean(this.registeredEntryStudentId && hasAvailableType && !this.entrySaving)
        },
        deleteEntryLabel() {
            return this.deleteEntryDialog.entry ? this.cellEntryTypeLabel(this.deleteEntryDialog.entry) : 'Eintrag'
        },
    },

    methods: {
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
                .map((work) => this.courseWorkAssignmentForDate(work, date))
                .filter(Boolean)
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
            if (this.tableView !== 'entries' || !this.uses_entry_areas_for_grading_schema) return null

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
        closeContentDialog() {
            if (this.contentSaving) return

            this.contentDialog = {
                content: '',
                courseDate: null,
                open: false,
            }
        },
        async saveContentDialog() {
            const courseDate = this.contentDialog.courseDate
            if (this.contentSaving || !courseDate?.id || !this.courseDateStore || !this.courseStore) return

            this.contentSaving = true
            let wasSaved = false

            try {
                const response = await this.courseDateStore.update({
                    id: courseDate.id,
                    date: courseDate.date,
                    content: this.contentDialog.content ?? '',
                })
                if (!response) return

                wasSaved = true
                await this.courseStore.index()
            } finally {
                this.contentSaving = false
            }

            if (wasSaved) this.closeContentDialog()
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
            return {
                date_for_all_groups: this.normalizeDateKey(this.workDialog.courseDate?.date),
                description: '',
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
                groups: work.is_group_work && this.isGeneratedEmptyIndividualWorkGroups(groups) ? [] : groups,
                is_group_work: !!work.is_group_work,
                status: Array.isArray(work.status) ? work.status : [],
                title: String(work.title || ''),
                type: String(work.type || ''),
            }
            this.workDialogDateEditing = false
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

            this.workSaving = true
            try {
                const gradeInputMode = this.courseWorkGradeInputModeForType(this.workDialogForm.type)
                const payload = {
                    ...this.workDialogForm,
                    date_for_all_groups: this.normalizeDateKey(this.workDialogForm.date_for_all_groups)
                        || this.normalizeDateKey(this.workDialog.courseDate?.date)
                        || null,
                    description: String(this.workDialogForm.description || '').trim() || null,
                    groups: this.courseWorkGroupsForGradeInputMode(this.workDialogForm.groups, gradeInputMode),
                    teaching_course_id: this.selected_course?.id || this.workDialogForm.teaching_course_id,
                    title: String(this.workDialogForm.title || '').trim() || null,
                    type: this.workDialogForm.type || null,
                }
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
                await this.loadCourseWorks()
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
                .filter((entry) => String(entry?.user_id) === String(userId) && this.normalizeDateKey(entry?.date) === dateKey)
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
                    return { items: [], mode: 'free' }
                }

                const items = (Array.isArray(workDefinition.fixed_properties) ? workDefinition.fixed_properties : [])
                    .map((property) => String(property || '').trim())
                    .filter(Boolean)
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
            if (gradeInputMode !== 'none') return workGroups

            return workGroups.map((group) => ({
                ...group,
                grade: null,
                grades: [],
            }))
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
            const updatedWork = this.courseWorkWithStudentEvaluation(work, this.registeredEntryStudentId, {
                ...draft,
                grade: gradeInputMode === 'none' ? '' : draft.grade,
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
        },
        cancelNewCellEntry() {
            this.entryFormOpen = false
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
        selectCellEntryType(type) {
            this.entryForm.type = this.entryForm.type === type ? '' : type
            this.entryForm.grade = ''
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
            if (!this.canSaveCellEntry) return

            this.entrySaving = true
            try {
                const date = this.normalizeDateKey(this.entryDialog.courseDate?.date) || null
                const description = String(this.entryForm.description || '').trim() || null
                let response

                if (!this.entryForm.id) {
                    response = await this.entryStore.store({
                        teaching_course_id: this.selected_course.id,
                        user_id: this.registeredEntryStudentId,
                        type: this.entryForm.type,
                        grade: this.selectedEntryTypeCategory === 'Benotung' ? this.entryForm.grade || null : null,
                        date,
                        description,
                    })
                } else if (this.entryForm.kind === 'assessment') {
                    response = await this.entryStore.update({
                        id: this.entryForm.id,
                        type: this.entryForm.type,
                        grade: this.selectedEntryTypeCategory === 'Benotung' ? this.entryForm.grade || null : null,
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
            if (present) return {}

            return this.sortedSelectedStudents.reduce((attendance, student) => {
                if (student?.id) {
                    attendance[String(student.id)] = false
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
            const optimisticDate = { ...courseDate, attendance: nextAttendance }
            this.applyUpdatedCourseDate(optimisticDate)

            try {
                const response = await this.courseDateStore?.updateStatus(courseDate.id, {
                    attendance: nextAttendance,
                    attendance_checked: this.isAttendanceChecked(courseDate),
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
            return `${this.studentName(student)} - ${this.compactCourseDateTitle(courseDate)}`
        },
        isStudentPresentForCourseDate(student, courseDate) {
            if (!student?.id || !courseDate) return false
            const attendance = this.getAttendanceMap(courseDate)

            return this.isAttendancePresentValue(attendance[String(student.id)])
        },
        studentPresencePercentage(student) {
            const attendanceDates = this.sortedCourseDates.filter((courseDate) => this.isAttendanceToggleable(courseDate))
            if (!attendanceDates.length) return null

            const presentDates = attendanceDates.filter((courseDate) => this.isStudentPresentForCourseDate(student, courseDate))

            return Math.round((presentDates.length / attendanceDates.length) * 100)
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
                attendance[studentId] = ['1', 'true'].includes(present)
            })

            return this.sanitizeAttendanceMap(attendance)
        },
        sanitizeAttendanceMap(attendanceLike) {
            const input = attendanceLike && typeof attendanceLike === 'object' ? attendanceLike : {}
            const sanitized = {}

            Object.entries(input).forEach(([studentId, value]) => {
                const key = String(studentId || '').trim()
                if (!key) return
                if (!this.isAttendancePresentValue(value)) {
                    sanitized[key] = false
                }
            })

            return sanitized
        },
        isAttendancePresentValue(value) {
            if (value === false) return false
            if (value === 0) return false
            if (value === '0') return false
            if (value === 'false') return false

            return true
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
        },
        async toggleStudentAttendance(student, courseDate) {
            if (!student?.id || !this.isAttendanceToggleable(courseDate) || this.isAttendanceCellSaving(student, courseDate)) return

            const cellKey = this.attendanceCellKey(student, courseDate)
            this.savingAttendanceCells = { ...this.savingAttendanceCells, [cellKey]: true }

            const previousDate = { ...courseDate, attendance: { ...(courseDate.attendance || {}) } }
            const attendance = this.getAttendanceMap(courseDate)
            const studentId = String(student.id)
            if (this.isAttendancePresentValue(attendance[studentId])) {
                attendance[studentId] = false
            } else {
                delete attendance[studentId]
            }

            const nextAttendance = this.sanitizeAttendanceMap(attendance)
            const optimisticDate = { ...courseDate, attendance: nextAttendance }
            this.applyUpdatedCourseDate(optimisticDate)

            try {
                const response = await this.courseDateStore?.updateStatus(courseDate.id, {
                    toggle_student_id: student.id,
                    attendance_checked: this.isAttendanceChecked(courseDate),
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
            const parsedDate = parseLocalDate(date)
            if (isNaN(parsedDate.getTime())) return ''

            return this.dateKey(parsedDate)
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
    background: linear-gradient(180deg, #ecfdf3 0%, #dcfce7 100%);
}

.course-table-work-cell {
    cursor: pointer;
    outline: none;
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
    background: #ecfdf3;
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
}

.course-table-work-empty-icon {
    opacity: 0.45;
    transition: opacity 0.15s ease, scale 0.15s ease;
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
    max-width: 112px;
    min-width: 112px;
    padding: 5px 6px;
    text-align: left;
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

.course-table-student-name {
    min-width: 0;
    overflow-wrap: anywhere;
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

.course-table-entry-cell-absent-marker {
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
    font-weight: 700;
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

.course-table-entry-cell-comment {
    color: #475569;
    display: -webkit-box;
    font-size: 0.66rem;
    font-weight: 500;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-height: 1.15;
    max-width: 92px;
    overflow: hidden;
    overflow-wrap: anywhere;
    text-align: center;
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

.course-table-attendance-marker {
    height: 24px;
    min-width: 24px;
    width: 24px;
}

.course-table thead th.course-table-date-col--free,
.course-table-row td.course-table-entry-cell--free,
.course-table-row:nth-child(even) td.course-table-entry-cell--free {
    background: #e8f5e9;
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
