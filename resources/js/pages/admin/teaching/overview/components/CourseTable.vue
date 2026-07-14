<template>
    <ItsGridBox
        v-if="selected_course"
        variant="overview"
        color="primary"
        icon="mdi-table-large"
        class="w-100"
        :disabled="action != ''">
        <template #title>
            <div>Tabelle - {{ selected_course.title }}</div>
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
            class="course-table-view-card mb-3"
            color="primary"
            data-testid="course-table-view-card"
            variant="tonal">
            <v-card-text class="d-flex align-center flex-wrap ga-3">
                <v-tabs
                    v-model="tableView"
                    class="course-table-view-tabs"
                    color="primary"
                    density="compact"
                    @update:model-value="changeTableView">
                    <v-tab value="attendance" prepend-icon="mdi-account-check">
                        Anwesenheit
                    </v-tab>
                    <v-tab value="entries" prepend-icon="mdi-format-list-bulleted">
                        Einträge
                    </v-tab>
                </v-tabs>
            </v-card-text>
        </v-card>

        <v-card variant="outlined" class="course-table-card">
            <v-card-text class="pa-0">
                <div ref="courseTableScroll" class="course-table-scroll">
                    <table class="course-table" data-testid="course-table">
                        <thead>
                            <tr class="course-table-title-row">
                                <th class="course-table-student-col">
                                    <div class="course-table-header-label">Schüler:in</div>
                                </th>
                                <th
                                    v-for="courseDate in sortedCourseDates"
                                    :key="`date-head-${courseDate.id || courseDate.date}`"
                                    :data-course-date-key="courseDateScrollKey(courseDate)"
                                    class="course-table-date-col"
                                    :class="{ 'course-table-date-col--free': isFreeCourseDate(courseDate) }">
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
                                    :class="{
                                        'course-table-work-cell--free': isFreeCourseDate(courseDate),
                                        'course-table-work-cell--selected': isWorkDialogCellSelected(courseDate),
                                    }"
                                    role="button"
                                    tabindex="0"
                                    :title="`${compactCourseDateTitle(courseDate)}: Arbeiten öffnen`"
                                    @click="openWorkDialog(courseDate)"
                                    @keydown.enter.prevent="openWorkDialog(courseDate)"
                                    @keydown.space.prevent="openWorkDialog(courseDate)">
                                    <div class="course-table-work-list">
                                        <v-icon
                                            v-if="!courseWorksForDate(courseDate).length"
                                            class="course-table-work-empty-icon"
                                            color="primary"
                                            size="18">
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
                                            </div>
                                            <strong class="course-table-work-summary-title">
                                                {{ work.work.title || work.label }}
                                            </strong>
                                        </div>
                                    </div>
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
                                </th>
                                <template
                                    v-for="courseDate in sortedCourseDates"
                                    :key="`student-${student.id}-date-${courseDate.id || courseDate.date}`">
                                    <td
                                        v-if="!isFreeCourseDate(courseDate)"
                                        class="course-table-entry-cell"
                                        :class="{
                                            'course-table-entry-cell--interactive': tableView === 'entries',
                                            'course-table-entry-cell--absent': tableView === 'entries' && !isStudentPresentForCourseDate(student, courseDate),
                                            'course-table-entry-cell--selected': isEntryDialogCellSelected(student, courseDate),
                                        }"
                                        :role="tableView === 'entries' ? 'button' : undefined"
                                        :tabindex="tableView === 'entries' ? 0 : undefined"
                                        @click="openEntryDialog(student, courseDate)"
                                        @keydown.enter.prevent="openEntryDialog(student, courseDate)"
                                        @keydown.space.prevent="openEntryDialog(student, courseDate)">
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
                                                    <v-chip
                                                        v-for="entry in compactPerformanceEntriesForCell(student, courseDate)"
                                                        :key="entry.uid"
                                                        class="course-table-entry-cell-badge"
                                                        size="x-small"
                                                        :color="cellEntryColor(entry)"
                                                        variant="tonal"
                                                        :title="entry.description || cellEntryTypeLabel(entry)">
                                                        {{ compactCellEntryLabel(entry) }}
                                                    </v-chip>
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
                                    </div>
                                    <div class="text-caption text-medium-emphasis mt-1">{{ assignment.label }}</div>
                                    <div v-if="assignment.work.description" class="text-caption mt-1">
                                        {{ assignment.work.description }}
                                    </div>
                                </div>
                                <div class="d-flex ga-1">
                                    <v-btn
                                        :data-testid="`course-table-date-edit-work-${assignment.id}`"
                                        density="compact"
                                        icon="mdi-pencil"
                                        size="x-small"
                                        title="Arbeit bearbeiten"
                                        variant="text"
                                        @click.stop="startEditingDateWork(assignment.work)" />
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
                                <v-tab v-if="workDialogForm.is_group_work" value="groups">Gruppen</v-tab>
                            </v-tabs>
                            <v-window v-model="workDialogTab">
                                <v-window-item value="work">
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
                                </v-window-item>
                                <v-window-item v-if="workDialogForm.is_group_work" value="groups">
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
                                                    class="course-table-work-group-date-input"
                                                    :data-testid="`course-table-date-work-group-date-input-${groupIndex}`"
                                                    density="compact"
                                                    hide-details
                                                    label="Gruppendatum"
                                                    variant="outlined"
                                                    :disabled="workSaving"
                                                    @click.stop
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
                                </v-window-item>
                            </v-window>
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
                                    <v-select
                                        v-if="availableWorkGradeItems.length"
                                        :model-value="row.grade"
                                        :items="availableWorkGradeItems"
                                        clearable
                                        density="compact"
                                        hide-details
                                        label="Note"
                                        variant="outlined"
                                        :disabled="workSaving"
                                        @update:model-value="setWorkGroupStudentGrade(workDialogGroupDetails.groupIndex, row.studentId, $event)" />
                                    <v-text-field
                                        v-else
                                        :model-value="row.grade"
                                        density="compact"
                                        hide-details
                                        label="Note"
                                        :maxlength="50"
                                        variant="outlined"
                                        :disabled="workSaving"
                                        @update:model-value="setWorkGroupStudentGrade(workDialogGroupDetails.groupIndex, row.studentId, $event)" />
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

        <v-dialog v-model="entryDialog.open" persistent max-width="680">
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
                                v-for="entry in cellEntries"
                                :key="entry.uid"
                                class="course-table-cell-entry">
                                <div class="d-flex align-center flex-wrap ga-2">
                                    <v-chip size="x-small" :color="cellEntryColor(entry)" variant="tonal">
                                        {{ cellEntryKindLabel(entry) }}
                                    </v-chip>
                                    <strong class="text-body-2">{{ cellEntryTypeLabel(entry) }}</strong>
                                    <v-chip
                                        v-if="entry.kind === 'assessment'"
                                        size="x-small"
                                        color="success"
                                        variant="tonal">
                                        {{ entry.effective_grade || entry.grade || 'offen' }}
                                    </v-chip>
                                    <v-chip v-if="entry.source === 'course_work'" size="x-small" color="info" variant="outlined">
                                        Aus Arbeit
                                    </v-chip>
                                    <v-spacer />
                                    <div v-if="canModifyCellEntry(entry)" class="d-flex ga-1">
                                        <v-btn
                                            :data-testid="`course-table-cell-edit-entry-${entry.uid}`"
                                            density="compact"
                                            icon="mdi-pencil"
                                            size="x-small"
                                            title="Eintrag bearbeiten"
                                            variant="text"
                                            @click="startEditingCellEntry(entry)" />
                                        <v-btn
                                            :data-testid="`course-table-cell-delete-entry-${entry.uid}`"
                                            color="error"
                                            density="compact"
                                            icon="mdi-delete"
                                            size="x-small"
                                            title="Eintrag löschen"
                                            variant="text"
                                            @click="openDeleteEntryDialog(entry)" />
                                    </div>
                                </div>
                                <div v-if="entry.description" class="text-caption text-medium-emphasis mt-1">
                                    {{ entry.description }}
                                </div>
                            </div>
                        </div>
                        <v-alert v-else type="info" variant="tonal" density="compact">
                            In dieser Zelle sind noch keine Einträge vorhanden.
                        </v-alert>
                    </section>

                    <v-divider />

                    <section>
                        <div class="text-caption font-weight-bold text-medium-emphasis mb-2">Mögliche Aktionen</div>
                        <v-btn
                            v-if="!entryFormOpen"
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
                                {{ entryForm.id ? 'Eintrag bearbeiten' : 'Neuen Eintrag anlegen' }}
                            </div>
                            <div class="text-caption text-medium-emphasis mb-1">Typ</div>
                            <div class="d-flex flex-wrap ga-1 mb-3">
                                <v-btn
                                    v-for="item in availableEntryTypes"
                                    :key="item.value"
                                    size="small"
                                    :variant="entryForm.type === item.value ? 'flat' : 'tonal'"
                                    :color="entryForm.type === item.value ? 'primary' : 'default'"
                                    @click="selectCellEntryType(item.value)">
                                    {{ item.title }}
                                </v-btn>
                            </div>

                            <template v-if="entryForm.kind === 'assessment'">
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
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="flat" color="primary" :disabled="entrySaving" @click="closeEntryDialog">Schließen</v-btn>
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
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'

export default {
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
            courseEntriesRequestPromise: null,
            courseWorkStore: null,
            courseWorksRequestPromise: null,
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
            randomGroupsDialog: {
                groupSize: 2,
                open: false,
            },
            savingAttendanceCells: {},
            tableView: 'attendance',
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
        this.behaviourEntryStore = useCourseBehaviourEntryStore()
        this.entryStore = useCourseStudentEntryStore()
        this.courseWorkStore = useCourseWorkStore()
    },

    async mounted() {
        this.courseDateStore = useCourseDateStore()
        this.restoreTableView(this.$route?.query?.view)
        if (this.tableView === 'entries') {
            await this.loadCourseEntries()
        }
        await this.loadCourseWorks()
        this.scrollToInitialCourseDate()
    },

    watch: {
        async '$route.query.view'(view) {
            this.restoreTableView(view)
            if (this.tableView === 'entries') {
                await this.loadCourseEntries()
            }
        },
        courseDateScrollSignature() {
            this.scrollToInitialCourseDate()
        },
        selected_course(course) {
            if (course?.id && this.tableView === 'entries') {
                this.loadCourseEntries(course.id)
            }
            if (course?.id) {
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

            if (!isGroupWork) {
                this.workDialogTab = 'work'
            }
        },
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action']),
        ...mapWritableState(useCourseDateStore, ['selected_courseDate']),
        ...mapWritableState(useCourseStore, ['selected_course', 'students_sort_mode', 'uses_entry_areas_for_grading_schema']),
        ...mapWritableState(useCourseWorkStore, ['courseWorks']),
        courseDateScrollSignature() {
            const courseId = this.selected_course?.id || ''
            const courseDates = this.sortedCourseDates
                .map((courseDate) => `${courseDate?.id || ''}:${this.normalizeDateKey(courseDate?.date) || ''}`)

            return [courseId, ...courseDates].join('|')
        },
        sortedCourseDates() {
            const dates = Array.isArray(this.selected_course?.course_dates) ? [...this.selected_course.course_dates] : []

            return dates.sort((first, second) => {
                const firstDate = String(first?.date || '')
                const secondDate = String(second?.date || '')
                const dateComparison = firstDate.localeCompare(secondDate)
                if (dateComparison !== 0) return dateComparison

                return Number(first?.id || 0) - Number(second?.id || 0)
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
        selectedTeachingSchema() {
            return this.selected_course?.teacher_teaching_schema || null
        },
        availableEntryTypes() {
            if (this.entryForm.kind !== 'assessment') {
                const definitions = this.entryForm.kind === 'notification'
                    ? this.selected_course?.teacher_teaching_notifications
                    : this.selected_course?.teacher_teaching_behaviour

                return (Array.isArray(definitions) ? definitions : [])
                    .filter((definition) => definition?.short_name)
                    .map((definition) => ({
                        title: definition.name ? `${definition.short_name} - ${definition.name}` : definition.short_name,
                        value: definition.short_name,
                    }))
            }

            const works = Array.isArray(this.selectedTeachingSchema?.works) ? this.selectedTeachingSchema.works : []

            return works
                .filter((work) => work?.short_name)
                .map((work) => ({
                    title: work.name ? `${work.short_name} - ${work.name}` : work.short_name,
                    value: work.short_name,
                }))
        },
        availableEntryGrades() {
            if (this.entryForm.kind !== 'assessment') return []

            const works = Array.isArray(this.selectedTeachingSchema?.works) ? this.selectedTeachingSchema.works : []
            const work = works.find((item) => item?.short_name === this.entryForm.type)
            const grades = Array.isArray(work?.grades) ? work.grades : []

            return grades
                .filter((grade) => grade?.grade)
                .map((grade) => ({
                    title: grade.name ? `${grade.grade} (${grade.name})` : grade.grade,
                    value: grade.grade,
                }))
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
            const assignedEntryArea = this.selected_course?.teaching_entry_area || null
            const entryDefinitions = Array.isArray(assignedEntryArea?.entry_definitions)
                ? assignedEntryArea.entry_definitions.filter((entry) => entry?.category === 'Benotung')
                : []
            const works = this.uses_entry_areas_for_grading_schema && assignedEntryArea?.id
                ? entryDefinitions
                : Array.isArray(this.selectedTeachingSchema?.works) ? this.selectedTeachingSchema.works : []
            const selectedWork = works.find((work) => work?.short_name === this.workDialogForm.type)
            const grades = Array.isArray(selectedWork?.grades) ? selectedWork.grades : []

            return grades
                .filter((grade) => grade?.grade)
                .map((grade) => ({
                    title: grade.name ? `${grade.grade} (${grade.name})` : grade.grade,
                    value: grade.grade,
                }))
        },
        dateWorkAssignments() {
            return this.courseWorksForDate(this.workDialog.courseDate)
        },
        workDialogGroups() {
            return Array.isArray(this.workDialogForm.groups) ? this.workDialogForm.groups : []
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
            this.tableView = ['entries', 'plain'].includes(view) ? 'entries' : 'attendance'
        },
        changeTableView(view) {
            const nextView = view === 'entries' ? 'entries' : 'attendance'
            this.tableView = nextView

            if (nextView === 'entries') {
                this.loadCourseEntries()
            }

            if (!this.$route || !this.$router) return

            this.$router.replace({
                path: this.$route.path,
                query: {
                    ...this.$route.query,
                    view: nextView,
                },
            }).catch(() => {})
        },
        openEntryDialog(student, courseDate) {
            if (this.tableView !== 'entries') return

            this.cancelNewCellEntry()
            this.entryDialog = {
                courseDate,
                open: true,
                student,
            }
        },
        closeEntryDialog() {
            if (this.entrySaving) return

            this.cancelNewCellEntry()
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

            const groupDates = (Array.isArray(work.groups) ? work.groups : [])
                .map((group) => this.normalizeDateKey(group?.date))
                .filter(Boolean)

            if (this.normalizeDateKey(work.date_for_all_groups) !== date && !groupDates.includes(date)) {
                return null
            }

            return this.courseWorkAssignmentPayload(work, 'Einzelarbeit')
        },
        groupWorkAssignmentForDate(work, date) {
            const groups = Array.isArray(work.groups) ? work.groups : []
            const matchingGroupIndexes = []
            const fallbackDate = this.normalizeDateKey(work.date_for_all_groups)
            const hasExplicitGroupDates = groups.some((group) => this.normalizeDateKey(group?.date))

            groups.forEach((group, index) => {
                const groupDate = this.normalizeDateKey(group?.date) || fallbackDate
                if (groupDate === date) {
                    matchingGroupIndexes.push(index + 1)
                }
            })

            if (!matchingGroupIndexes.length && (hasExplicitGroupDates || fallbackDate !== date)) {
                return null
            }

            const totalGroups = groups.length
            const suffix = matchingGroupIndexes.length && matchingGroupIndexes.length < totalGroups
                ? `Gr. ${matchingGroupIndexes.join(', ')}`
                : 'Gruppenarbeit'

            return this.courseWorkAssignmentPayload(work, suffix, true)
        },
        courseWorkAssignmentPayload(work, suffix, isGroupWork = false) {
            const type = String(work.type || 'Arbeit').trim()
            const title = String(work.title || '').trim()
            const baseLabel = title ? `${type}: ${title}` : type
            const label = suffix ? `${baseLabel} (${suffix})` : baseLabel

            return {
                id: work.id,
                isGroupWork,
                key: `${work.id}-${suffix || 'work'}`,
                label,
                title: label,
                work,
            }
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
        },
        applyWorkDialogGroupDate(groupIndex, value) {
            const date = this.normalizeDateKey(value)
            const group = this.workDialogGroups[groupIndex]
            if (!date || !group) return

            group.date = date
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
                const payload = {
                    ...this.workDialogForm,
                    date_for_all_groups: this.normalizeDateKey(this.workDialogForm.date_for_all_groups)
                        || this.normalizeDateKey(this.workDialog.courseDate?.date)
                        || null,
                    description: String(this.workDialogForm.description || '').trim() || null,
                    teaching_course_id: this.selected_course?.id || this.workDialogForm.teaching_course_id,
                    title: String(this.workDialogForm.title || '').trim() || null,
                    type: this.workDialogForm.type || null,
                }
                const response = payload.id
                    ? await this.courseWorkStore.update(payload)
                    : await this.courseWorkStore.store(payload)

                if (!response) return

                await this.loadCourseWorks()
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
        workGroupGradeRows(group) {
            const studentIds = Array.isArray(group?.student_ids) ? group.student_ids : []

            return studentIds.map((studentId) => ({
                grade: this.workGroupStudentGrade(group, studentId),
                studentId,
                studentName: this.workGroupStudentName(studentId),
            }))
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
                .filter((entry) => entry.kind !== 'assessment')
        },
        performanceEntriesForCell(student, courseDate) {
            return this.entriesForCell(student, courseDate)
                .filter((entry) => entry.kind === 'assessment')
        },
        compactPerformanceEntriesForCell(student, courseDate) {
            const entriesByType = new Map()

            this.performanceEntriesForCell(student, courseDate).forEach((entry) => {
                const type = String(entry?.type || '').trim()
                const typeKey = type ? type.toLocaleLowerCase('de-AT') : entry.uid
                const grade = String(entry?.effective_grade || entry?.grade || '').trim()

                if (!entriesByType.has(typeKey)) {
                    entriesByType.set(typeKey, {
                        entry: {
                            ...entry,
                            description: null,
                            effective_grade: '',
                            grade: '',
                            uid: `assessment-group-${typeKey}`,
                        },
                        grades: [],
                    })
                }

                if (grade) {
                    entriesByType.get(typeKey).grades.push(grade)
                }
            })

            return [...entriesByType.values()].map(({ entry, grades }) => ({
                ...entry,
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
            if (entry?.kind === 'assessment') return 'Bewertung'
            if (entry?.kind === 'notification') return 'Verständigung'

            return 'Verhalten'
        },
        cellEntryColor(entry) {
            if (entry?.kind === 'assessment') return 'primary'
            if (entry?.kind === 'notification') return 'secondary'

            return 'warning'
        },
        cellEntryTypeLabel(entry) {
            const type = String(entry?.type || '').trim()
            if (!type) return 'Eintrag'

            if (entry?.kind === 'assessment') {
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
        compactCellEntryLabel(entry) {
            const type = String(entry?.type || '').trim() || 'Eintrag'
            if (entry?.kind !== 'assessment') return type

            const grade = String(entry?.effective_grade || entry?.grade || '').trim()

            return grade ? `${type}: ${grade}` : type
        },
        startNewCellEntry() {
            if (!this.canCreateCellEntry) return

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
        selectCellEntryType(type) {
            this.entryForm.type = this.entryForm.type === type ? '' : type
            this.entryForm.grade = ''
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
                        grade: this.entryForm.grade || null,
                        date,
                        description,
                    })
                } else if (this.entryForm.kind === 'assessment') {
                    response = await this.entryStore.update({
                        id: this.entryForm.id,
                        type: this.entryForm.type,
                        grade: this.entryForm.grade || null,
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
.course-table-view-card {
    border: 1px solid rgba(37, 99, 235, 0.22);
}

.course-table-view-tabs {
    flex-shrink: 0;
}

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

.course-table-work-summary-title {
    display: -webkit-box;
    font-size: 0.7rem;
    font-weight: 800;
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
    grid-template-columns: minmax(0, 1fr) minmax(180px, 240px);
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

.course-table-entry-cell {
    background: #ffffff;
    text-align: center;
}

@media (max-width: 600px) {
    .course-table-view-tabs {
        width: 100%;
    }

    .course-table-view-tabs :deep(.v-tab) {
        flex: 1;
    }
}

.course-table-entry-cell--interactive {
    cursor: pointer;
    outline: none;
    transition: background-color 0.15s ease, box-shadow 0.15s ease;
}

.course-table-entry-cell--absent {
    background: rgba(var(--v-theme-error), 0.14) !important;
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
