<template>
    <ItsGridBox
        variant="overview"
        color="primary"
        icon="mdi-clipboard-text"
        class="w-100"
        v-if="selected_course"
        :disabled="action != '' && action != 'new_course_work' && action != 'edit_course_work'">
        <template #title>
            <div>Arbeiten – {{ selected_course.title }} ({{ selectedCourseClasses }})</div>
        </template>
        <template #header-actions>
            <v-btn v-if="action !== 'new_course_work' && action !== 'edit_course_work'" icon="mdi-plus" size="small" variant="tonal" @click="newWork" :disabled="!hasStudents" />
            <v-btn v-if="action === 'new_course_work' || action === 'edit_course_work'" icon="mdi-close" size="small" color="warning" variant="tonal" @click="abortEdit" :disabled="is_saving" />
            <v-btn v-if="action === 'new_course_work' || action === 'edit_course_work'" icon="mdi-content-save" size="small" color="success" variant="tonal" @click="saveWork(false)" :disabled="is_saving" />
            <v-btn icon="mdi-eye-off-outline" size="small" variant="tonal" title="Ausblenden" @click="show_works = false" />
        </template>
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <div v-if="semesterCount === 2" class="d-flex flex-wrap align-center ga-2 mt-2">
                    <v-btn-toggle v-model="activeSemester" mandatory density="compact" color="primary">
                        <v-btn :value="1" size="small">1. Sem</v-btn>
                        <v-btn :value="2" size="small">2. Sem</v-btn>
                        <v-btn :value="3" size="small">1+2</v-btn>
                    </v-btn-toggle>
                </div>
            </v-card-text>
        </v-card>

        <!-- Arbeiten (Anzeige) -->
        <v-card variant="outlined" class="mt-4" v-if="filteredCourseWorks?.length && action !== 'new_course_work' && action !== 'edit_course_work'">
            <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                <v-icon size="18">mdi-clipboard-text</v-icon>
                Arbeiten
                <v-chip size="x-small" color="primary" variant="tonal">
                    {{ filteredCourseWorks.length }}
                </v-chip>
                <v-chip v-if="courseWorks?.length && courseWorks.length !== filteredCourseWorks.length" size="x-small" color="secondary" variant="outlined">
                    Gesamt {{ courseWorks.length }}
                </v-chip>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-0">
                <v-list density="compact">
                    <v-list-item v-for="work in filteredCourseWorks" :key="work.id" class="cursor-pointer" @click="editWork(work)">
                        <div class="work-row d-flex align-center ga-2 w-100">
                            <v-chip v-if="workListDate(work)" size="x-small" variant="tonal" :color="workListDateColor(work)" class="work-date-chip">
                                {{ formatDate(workListDate(work)) }}
                            </v-chip>
                            <v-chip v-else size="x-small" variant="outlined" class="work-date-chip">ohne Datum</v-chip>
                            <div class="work-title flex-grow-1">
                                <div class="text-caption text-medium-emphasis work-type-first-line">
                                    <strong v-if="work.type">{{ workTypeLabel(work.type) }}</strong>
                                    <span v-else class="text-medium-emphasis">eine Arbeit</span>
                                </div>
                                <div v-if="work.title || work.description" class="text-body-2 work-title-second-line" :class="workHasAllGrades(work) ? 'text-success' : ''">
                                    {{ work.title || work.description }}
                                </div>
                            </div>
                            <div class="work-actions d-flex align-center ga-1">
                                <v-btn v-if="delete_work_id !== work.id" icon="mdi-delete" size="x-small" color="warning" variant="tonal" @click.stop="delete_work_id = work.id" />
                                <v-btn v-if="delete_work_id === work.id" icon="mdi-delete-off" size="x-small" color="success" variant="tonal" @click.stop="delete_work_id = null" />
                                <v-btn v-if="delete_work_id === work.id" icon="mdi-delete" size="x-small" color="error" variant="tonal" @click.stop="deleteWork(work)" />
                            </div>
                        </div>
                    </v-list-item>
                </v-list>
            </v-card-text>
        </v-card>
        <div v-if="!filteredCourseWorks?.length && hasStudents && !courseWorks?.length && action !== 'new_course_work' && action !== 'edit_course_work'" class="text-caption text-medium-emphasis mt-2">
            Keine Arbeiten vorhanden
        </div>
        <div v-if="!filteredCourseWorks?.length && hasStudents && courseWorks?.length && action !== 'new_course_work' && action !== 'edit_course_work'" class="text-caption text-warning mt-2">
            Es gibt {{ courseWorks.length }} Arbeit(en), aktuell durch den Semester-Filter ausgeblendet.
        </div>
        <div v-if="!hasStudents && action !== 'new_course_work' && action !== 'edit_course_work'" class="text-caption text-warning mt-2">
            Keine Schüler:innen im Kurs. Bitte zuerst Schüler:innen hinzufügen.
        </div>

        <!-- NEUE/BEARBEITEN ARBEIT (TEMPLATE) -->
        <v-card tile flat color="transparent" class="w-100" v-if="action === 'new_course_work' || action === 'edit_course_work'">
            <v-form ref="form" v-model="is_valid" @submit.prevent class="mb-4">
                <v-card-text>
                    <div class="text-caption text-medium-emphasis mb-1">Typ</div>
                    <div class="d-flex flex-wrap ga-1 mb-1">
                        <v-btn
                            v-for="item in workTypeItems"
                            :key="item.value"
                            :variant="work_form.type === item.value ? 'flat' : 'tonal'"
                            :color="work_form.type === item.value ? 'primary' : 'default'"
                            size="small"
                            @click="work_form.type = work_form.type === item.value ? null : item.value">
                            {{ item.value }}
                        </v-btn>
                    </div>
                    <div class="text-caption text-primary mb-4" style="min-height: 1.2em;">
                        {{ workTypeItems.find(i => i.value === work_form.type)?.title ?? '' }}
                    </div>
                    <v-text-field v-model="work_form.title" label="Titel" />
                    <v-date-input v-model="work_form.date_for_all_groups" label="Datum (für alle Gruppen)" />
                    <div class="d-flex flex-wrap ga-1 mt-1" v-if="nextDates.length">
                        <v-chip v-for="date in nextDates" :key="date.id" size="x-small" variant="outlined" class="cursor-pointer" @click="selectDate(date.date)">
                            {{ formatDateWithWeekday(date.date) }}
                        </v-chip>
                    </div>
                    <v-textarea v-model="work_form.description" label="Beschreibung" rows="3" class="mt-4" />
                    <div class="d-flex flex-wrap align-center ga-2 mt-2">
                        <div class="text-caption text-medium-emphasis">Sortierung Schüler:innen</div>
                        <v-btn-toggle v-model="students_sort_mode" mandatory density="compact" color="primary">
                            <v-btn size="small" value="class_last_name">Klasse, Name</v-btn>
                            <v-btn size="small" value="last_name_first_name">Name</v-btn>
                        </v-btn-toggle>
                    </div>

                    <v-switch
                        :key="group_work_switch_key"
                        :model-value="work_form.is_group_work"
                        :color="work_form.is_group_work ? 'success' : ''"
                        label="Gruppenarbeit"
                        inset
                        @update:model-value="setGroupWork" />
                    <div v-if="pending_group_work !== null" class="d-flex align-center ga-2 mt-2">
                        <div class="text-body-2">
                            {{ pending_group_work ? 'Beim Wechsel zur Gruppenarbeit gehen vorhandene Noten/Kommentare verloren.' : 'Beim Wechsel zur Einzelarbeit gehen vorhandene Gruppen und Noten/Kommentare verloren.' }}
                        </div>
                        <v-btn color="warning" flat tile @click="cancelGroupWorkChange">Abbruch</v-btn>
                        <v-btn color="success" flat tile @click="confirmGroupWorkChange">Wechseln</v-btn>
                    </div>

                    <div :style="pending_group_work !== null ? 'pointer-events:none; opacity:0.6' : ''">
                        <div v-if="work_form.is_group_work">
                            <div class="d-flex flex-column ga-2">
                                <v-text-field v-model.number="work_form.group_size" type="number" min="2" label="Gruppengröße" />
                                <v-switch v-model="work_form.is_random_groups" :color="work_form.is_random_groups ? 'success' : ''" label="Gruppen zufällig erstellen" inset />
                                <v-btn
                                    v-if="work_form.is_random_groups && !pending_random_groups"
                                    color="primary"
                                    variant="tonal"
                                    size="small"
                                    :disabled="!work_form.group_size || work_form.group_size < 2"
                                    @click="requestRandomGroups">
                                    Gruppen zufällig erstellen
                                </v-btn>
                                <div v-if="pending_random_groups" class="d-flex align-center ga-2 mt-2">
                                    <div class="text-body-2">
                                        Beim Neuerstellen der Gruppen gehen vorhandene Gruppen und Noten/Kommentare verloren.
                                    </div>
                                    <v-btn color="warning" flat tile @click="cancelRandomGroups">Abbruch</v-btn>
                                    <v-btn color="success" flat tile @click="confirmRandomGroups">Neu erstellen</v-btn>
                                </div>
                                <v-btn v-if="hasUnassignedStudents" color="primary" variant="tonal" size="small" @click="addGroup">Gruppe hinzufügen</v-btn>
                            </div>

                            <v-expansion-panels variant="accordion" class="mt-3">
                                <v-expansion-panel v-for="(group, index) in work_form.groups" :key="`group-${index}`">
                                    <v-expansion-panel-title>
                                        <div class="d-flex flex-column w-100 ga-1">
                                            <v-alert
                                                v-if="groupSizeHint(group)"
                                                density="compact"
                                                variant="tonal"
                                                color="warning"
                                                icon="mdi-alert"
                                                class="text-caption py-1">
                                                {{ groupSizeHint(group) }}
                                            </v-alert>
                                            <div class="d-flex align-center flex-wrap ga-2 w-100">
                                                <div class="text-caption text-medium-emphasis">Gruppe {{ index + 1 }}</div>
                                                <v-chip v-if="group.date" size="x-small" variant="tonal" :color="workDateColor(group.date)">
                                                    {{ formatDate(group.date) }}
                                                </v-chip>
                                                <v-chip v-for="studentId in sortedGroupStudentIds(group)" :key="`g-${index}-s-${studentId}`" size="x-small" variant="tonal">
                                                    {{ studentNameById(studentId) }}
                                                </v-chip>
                                                <v-spacer />
                                                <v-btn
                                                    v-if="isGroupEmpty(group)"
                                                    icon="mdi-delete"
                                                    size="x-small"
                                                    color="error"
                                                    variant="tonal"
                                                    @click.stop="removeGroup(index)" />
                                            </div>
                                        </div>
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text>
                                        <div class="d-flex flex-column ga-2">
                                            <v-autocomplete
                                                :model-value="sortedGroupStudentIds(group)"
                                                @update:model-value="updateGroupStudents(group, $event)"
                                                :items="availableStudentItems(index)"
                                                item-title="title"
                                                item-value="value"
                                                multiple
                                                chips
                                                label="Schüler:innen" />
                                            <v-date-input v-model="group.date" label="Datum" />
                                            <v-switch v-model="group.use_individual_grades" :color="group.use_individual_grades ? 'success' : ''" label="Einzelnoten pro Schüler:in" inset />
                                            <v-select
                                                v-if="!group.use_individual_grades"
                                                v-model="group.grade"
                                                :items="gradeItemsForType"
                                                item-title="title"
                                                item-value="value"
                                                label="Note (für alle)"
                                                clearable />
                                            <v-textarea
                                                v-if="!group.use_individual_grades"
                                                v-model="group.comment"
                                                label="Kommentar (für alle)"
                                                rows="2"
                                                :counter="1024"
                                                :maxlength="1024" />
                                            <div v-else class="d-flex flex-column ga-2">
                                                <div v-for="studentId in sortedGroupStudentIds(group)" :key="`grade-comment-${index}-${studentId}`" class="d-flex flex-column ga-2">
                                                    <div class="text-caption text-medium-emphasis">
                                                        {{ studentNameById(studentId) }}
                                                    </div>
                                                    <div class="d-flex align-center flex-wrap ga-2">
                                                        <v-select
                                                            v-model="group.grades[studentId]"
                                                            :items="gradeItemsForType"
                                                            item-title="title"
                                                            item-value="value"
                                                            label="Note"
                                                            clearable
                                                            style="min-width: 140px" />
                                                        <v-textarea
                                                            v-model="group.comments[studentId]"
                                                            label="Kommentar"
                                                            rows="2"
                                                            :counter="1024"
                                                            :maxlength="1024"
                                                            style="min-width: 240px; flex: 1 1 240px" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </div>
                        <div v-else>
                            <div class="d-flex align-center justify-space-between ga-2">
                                <div class="d-flex align-center ga-2">
                                    <v-btn
                                        size="x-small"
                                        :variant="show_bulk_action ? 'flat' : 'outlined'"
                                        :color="show_bulk_action ? 'warning' : 'primary'"
                                        :disabled="!(work_form.groups || []).length"
                                        @click="toggleBulkAction">
                                        {{ show_bulk_action ? 'Sammelaktion schließen' : 'Sammelaktion' }}
                                    </v-btn>
                                    <v-btn
                                        size="x-small"
                                        :variant="show_chip_grading_view ? 'flat' : 'outlined'"
                                        :color="show_chip_grading_view ? 'secondary' : 'primary'"
                                        @click="toggleChipGradingView">
                                        {{ show_chip_grading_view ? 'Chip-Ansicht schließen' : 'Chip-Ansicht' }}
                                    </v-btn>
                                    <v-btn
                                        v-if="selectedTypeSupportsPoints"
                                        size="x-small"
                                        :variant="show_points_grading_view ? 'flat' : 'outlined'"
                                        :color="show_points_grading_view ? 'success' : 'primary'"
                                        @click="togglePointsGradingView">
                                        {{ show_points_grading_view ? 'Punkte schließen' : 'Punkte' }}
                                    </v-btn>
                                </div>
                                <v-btn size="x-small" variant="tonal" color="primary" @click="toggleAllSinglePanels">
                                    {{ allSinglePanelsOpen ? 'Alle schließen' : 'Alle öffnen' }}
                                </v-btn>
                            </div>
                            <!-- Bulk action panel -->
                            <v-card v-if="show_bulk_action" variant="outlined" class="mt-3 pa-3">
                                <div class="text-caption text-medium-emphasis mb-2">Note und/oder Kommentar für mehrere Schüler:innen setzen</div>
                                <div class="d-flex flex-column ga-2">
                                    <v-select
                                        v-model="bulk_grade"
                                        :items="gradeItemsForType"
                                        item-title="title"
                                        item-value="value"
                                        label="Note"
                                        density="compact"
                                        hide-details
                                        clearable />
                                    <v-textarea
                                        v-model="bulk_comment"
                                        label="Kommentar"
                                        density="compact"
                                        hide-details
                                        rows="2"
                                        :maxlength="1024" />
                                    <div class="d-flex align-center justify-space-between mt-2">
                                        <div class="d-flex align-center ga-2">
                                            <v-btn size="small" variant="text" @click="selectAllStudents">Alle auswählen</v-btn>
                                            <v-btn size="small" variant="text" @click="deselectAllStudents">Keine auswählen</v-btn>
                                        </div>
                                        <v-btn
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            :disabled="!bulk_grade && !bulk_comment"
                                            @click="applyBulkAction">
                                            {{ selected_student_ids.length ? `Auf ${selected_student_ids.length} Schüler:in(nen) anwenden` : 'Auf alle anwenden' }}
                                        </v-btn>
                                    </div>
                                </div>
                            </v-card>
                            <div v-if="show_points_grading_view" class="mt-3 d-flex flex-column ga-2">
                                <v-card
                                    v-for="row in pointsViewRows"
                                    :key="`points-${row.groupIndex}-${row.studentId}`"
                                    variant="outlined"
                                    class="pa-3">
                                    <div class="d-flex align-center ga-2 flex-wrap">
                                        <v-chip v-if="row.classLabel" size="x-small" variant="tonal" color="primary">
                                            {{ row.classLabel }}
                                        </v-chip>
                                        <div class="text-body-2 font-weight-medium">
                                            {{ row.studentLabel }}
                                        </div>
                                        <v-spacer />
                                        <v-chip
                                            size="x-small"
                                            :color="row.gradeValue ? 'success' : 'default'"
                                            :variant="row.gradeValue ? 'flat' : 'outlined'">
                                            {{ row.gradeValue || 'Keine Note' }}
                                        </v-chip>
                                    </div>
                                    <div class="d-flex flex-wrap align-start ga-3 mt-3">
                                        <v-text-field
                                            :model-value="row.pointsValue"
                                            label="Punkte"
                                            density="compact"
                                            hide-details
                                            inputmode="decimal"
                                            style="width: 160px; flex: 0 0 160px"
                                            @update:model-value="setStudentPoints(row.groupIndex, row.studentId, $event)" />
                                        <v-textarea
                                            :model-value="row.commentValue"
                                            label="Kommentar"
                                            density="compact"
                                            hide-details
                                            rows="2"
                                            auto-grow
                                            :maxlength="1024"
                                            style="min-width: 240px; flex: 1 1 240px"
                                            @update:model-value="setStudentComment(row.groupIndex, row.studentId, $event)" />
                                    </div>
                                </v-card>
                            </div>
                            <!-- Chip grading view -->
                            <div v-else-if="show_chip_grading_view" class="mt-3 d-flex flex-column ga-2">
                                <v-card
                                    v-for="row in chipViewRows"
                                    :key="`chip-${row.groupIndex}-${row.studentId}`"
                                    variant="outlined"
                                    class="pa-2">
                                    <div class="d-flex align-center ga-2 flex-wrap">
                                        <v-chip v-if="row.classLabel" size="x-small" variant="tonal" color="primary">
                                            {{ row.classLabel }}
                                        </v-chip>
                                        <div class="text-body-2 font-weight-medium">
                                            {{ row.studentLabel }}
                                        </div>
                                        <v-spacer />
                                        <v-btn
                                            size="x-small"
                                            variant="tonal"
                                            :color="row.commentValue ? 'warning' : 'primary'"
                                            icon="mdi-pencil"
                                            @click="openCommentDialog(row.groupIndex, row.studentId)" />
                                    </div>
                                    <div class="d-flex flex-wrap ga-1 mt-2">
                                        <v-chip
                                            size="x-small"
                                            :variant="row.gradeValue ? 'outlined' : 'flat'"
                                            :color="row.gradeValue ? 'default' : 'success'"
                                            @click="setStudentGrade(row.groupIndex, row.studentId, '')">
                                            —
                                        </v-chip>
                                        <v-chip
                                            v-for="grade in gradeItemsForType"
                                            :key="`chip-grade-${row.groupIndex}-${row.studentId}-${grade.value}`"
                                            size="x-small"
                                            :variant="row.gradeValue === grade.value ? 'flat' : 'tonal'"
                                            :color="row.gradeValue === grade.value ? 'success' : 'default'"
                                            @click="setStudentGrade(row.groupIndex, row.studentId, grade.value)">
                                            {{ grade.value }}
                                        </v-chip>
                                    </div>
                                    <div v-if="row.commentPreview" class="text-caption text-medium-emphasis mt-1">
                                        {{ row.commentPreview }}
                                    </div>
                                </v-card>
                            </div>
                            <!-- Compact view when all open -->
                            <div v-else-if="allSinglePanelsOpen" class="mt-3 d-flex flex-column ga-2">
                                <v-card
                                    v-for="(group, index) in work_form.groups"
                                    :key="`compact-${index}`"
                                    variant="outlined"
                                    class="pa-2">
                                    <div v-for="studentId in sortedGroupStudentIds(group)" :key="`compact-grade-${index}-${studentId}`" class="d-flex flex-column ga-1">
                                        <div class="d-flex align-center ga-2">
                                            <v-checkbox
                                                v-if="show_bulk_action"
                                                v-model="selected_student_ids"
                                                :value="studentId"
                                                density="compact"
                                                hide-details
                                                class="flex-grow-0" />
                                            <div class="text-body-2 font-weight-medium" style="min-width: 180px">
                                                {{ studentNameById(studentId) }}
                                            </div>
                                            <v-select
                                                v-model="group.grades[studentId]"
                                                :items="gradeItemsForType"
                                                item-title="title"
                                                item-value="value"
                                                label="Note"
                                                density="compact"
                                                hide-details
                                                clearable
                                                style="width: 200px; flex: 0 0 200px" />
                                        </div>
                                        <v-textarea
                                            v-model="group.comments[studentId]"
                                            label="Kommentar"
                                            density="compact"
                                            hide-details
                                            rows="1"
                                            auto-grow
                                            :maxlength="1024" />
                                    </div>
                                </v-card>
                            </div>
                            <!-- Expansion panels view when collapsed -->
                            <v-expansion-panels v-else v-model="singlePanels" multiple class="mt-3">
                                <v-expansion-panel v-for="(group, index) in work_form.groups" :key="`single-${index}`">
                                    <v-expansion-panel-title>
                                        <div class="d-flex align-center flex-wrap ga-2 w-100">
                                            <div class="text-caption text-medium-emphasis">Schüler:in</div>
                                            <v-chip
                                                v-for="studentId in sortedGroupStudentIds(group)"
                                                :key="`single-chip-${index}-${studentId}`"
                                                size="x-small"
                                                variant="tonal">
                                                {{ studentNameById(studentId) }}
                                            </v-chip>
                                        </div>
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text>
                                        <div v-for="studentId in sortedGroupStudentIds(group)" :key="`single-grade-${index}-${studentId}`" class="d-flex flex-column ga-2">
                                            <div class="text-caption text-medium-emphasis">
                                                {{ studentNameById(studentId) }}
                                            </div>
                                            <div class="d-flex align-center flex-wrap ga-2">
                                                <v-select
                                                    v-model="group.grades[studentId]"
                                                    :items="gradeItemsForType"
                                                    item-title="title"
                                                    item-value="value"
                                                    label="Note"
                                                    clearable
                                                    style="min-width: 140px" />
                                                <v-textarea
                                                    v-model="group.comments[studentId]"
                                                    label="Kommentar"
                                                    rows="2"
                                                    :counter="1024"
                                                    :maxlength="1024"
                                                    style="min-width: 240px; flex: 1 1 240px" />
                                            </div>
                                        </div>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </div>

                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" flat tile @click="abortEdit" :disabled="is_saving">Abbruch</v-btn>
                            <div class="d-flex ga-2">
                                <v-btn color="primary" flat tile @click="saveWork(true)" :disabled="is_saving" :loading="is_saving">Speichern</v-btn>
                                <v-btn color="success" flat tile @click="saveWork(false)" :disabled="is_saving" prepend-icon="mdi-content-save">Ende</v-btn>
                            </div>
                        </div>
                    </div>
                </v-card-text>
            </v-form>
        </v-card>

        <v-dialog v-model="comment_dialog_open" persistent max-width="620">
            <v-card>
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                    <v-icon size="18">mdi-pencil</v-icon>
                    Kommentar bearbeiten
                    <v-spacer />
                    <v-chip v-if="commentDialogStudentLabel" size="x-small" variant="tonal" color="primary">
                        {{ commentDialogStudentLabel }}
                    </v-chip>
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <v-textarea
                        v-model="comment_dialog_value"
                        label="Kommentar"
                        rows="4"
                        auto-grow
                        :counter="1024"
                        :maxlength="1024" />
                </v-card-text>
                <v-divider />
                <v-card-actions>
                    <v-btn color="warning" variant="tonal" @click="closeCommentDialog">Abbruch</v-btn>
                    <v-spacer />
                    <v-btn color="success" variant="tonal" @click="saveCommentDialog">Speichern</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </ItsGridBox>
</template>

<script>
import { mapWritableState } from 'pinia'
import { parseLocalDate } from '@/helpers/date'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.courseWorkStore = useCourseWorkStore()
        this.teachingStore = useTeachingStore()
        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
        }
        this.activeSemester = this.config?.user?.teaching_active_semester || 1
        await this.refreshWorks()
    },

    unmounted() {
        this.courseWorkStore.clearWorks()
    },

    data() {
        return {
            adminStore: null,
            courseStore: null,
            courseWorkStore: null,
            teachingStore: null,
            activeSemester: null,
            is_valid: false,
            delete_work_id: null,
            work_form: this.emptyWorkForm(),
            is_initializing_form: false,
            singlePanels: [],
            pending_group_work: null,
            group_work_switch_key: 0,
            pending_random_groups: false,
            show_bulk_action: false,
            bulk_grade: null,
            bulk_comment: '',
            selected_student_ids: [],
            students_sort_mode: 'last_name_first_name',
            show_chip_grading_view: false,
            show_points_grading_view: false,
            comment_dialog_open: false,
            comment_dialog_group_index: null,
            comment_dialog_student_id: null,
            comment_dialog_value: '',
            is_saving: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, ['selected_course', 'selected_course_student', 'show_infos', 'show_dates', 'show_works']),
        ...mapWritableState(useCourseWorkStore, ['courseWorks', 'selected_courseWork']),
        ...mapWritableState(useTeachingStore, ['settings']),
        selectedCourseClasses() {
            if (!this.selected_course?.classes?.length) return ''
            if (typeof this.selected_course.classes === 'string') {
                return this.selected_course.classes.replace(/,/g, ', ')
            }
            return this.selected_course.classes.join(', ')
        },
        selectedCourseSchema() {
            const courseSchema = this.selected_course?.teacher_teaching_schema
            if (courseSchema?.id) {
                return courseSchema
            }

            const schemaId = this.selected_course?.teaching_schema_id
            return schemaId ? this.teachingStore?.schemaById(schemaId) : null
        },
        teachingWorks() {
            return this.selectedCourseSchema?.works || []
        },
        activeCourseStudents() {
            const list = this.selected_course?.students_info || []
            const seen = new Set()
            return list.filter((student) => {
                if (!student?.id) return false
                if (this.isStudentInactive(student)) return false
                const key = String(student.id)
                if (seen.has(key)) return false
                seen.add(key)
                return true
            })
        },
        hasStudents() {
            return this.activeCourseStudents.length > 0
        },
        semesterCount() {
            const grading = this.selectedCourseSchema?.grading || {}
            return grading?.semester_count || 1
        },
        sem2StartDate() {
            const raw = this.config?.selected_schoolyear?.sem_2_start || this.config?.user?.teaching_count_for_semester_2_date || null
            return this.normalizeDateString(raw) || null
        },
        filteredCourseWorks() {
            if (this.semesterCount === 1) return this.courseWorks || []
            const semester = this.activeSemester
            if (!semester || semester === 3) return this.courseWorks || []
            if (!this.sem2StartDate) return this.courseWorks || []
            return (this.courseWorks || []).filter((work) => {
                if (!work.date_for_all_groups) return true
                if (semester === 1) return work.date_for_all_groups < this.sem2StartDate
                if (semester === 2) return work.date_for_all_groups >= this.sem2StartDate
                return true
            })
        },
        workTypeItems() {
            return this.teachingWorks.map((work) => ({
                title: `${work.short_name} - ${work.name}`,
                value: work.short_name,
            }))
        },
        gradeItemsForType() {
            const selectedType = this.work_form.type
            if (!selectedType) return []
            const work = this.teachingWorks.find((w) => w.short_name === selectedType)
            const grades = work?.grades || []
            return grades.map((grade) => ({
                title: grade.name ? `${grade.grade} (${grade.name})` : grade.grade,
                value: grade.grade,
            }))
        },
        selectedTypeWork() {
            return this.workConfigForType(this.work_form.type)
        },
        selectedTypeSupportsPoints() {
            if (this.work_form.is_group_work) return false
            return this.workSupportsPoints(this.selectedTypeWork)
        },
        studentItems() {
            return this.activeCourseStudents
                .map((student) => ({
                    title: this.studentLabel(student),
                    value: student.id,
                    _class: (student.schoolclass || student.class || '').toString(),
                    _last: (student.last_name || '').toString(),
                    _first: (student.first_name || '').toString(),
                }))
                .sort((a, b) => this.compareStudentsBySelectedSort(a, b))
        },
        nextDates() {
            const dates = this.selected_course?.course_dates || []
            if (!dates.length) return []

            const today = new Date()
            today.setHours(0, 0, 0, 0)

            return dates
                .map((d) => ({
                    ...d,
                    _dateObj: parseLocalDate(d.date),
                }))
                .filter((d) => !isNaN(d._dateObj.getTime()) && d._dateObj >= today)
                .sort((a, b) => a._dateObj - b._dateObj)
                .slice(0, 3)
        },
        allSinglePanelsOpen() {
            return this.singlePanels.length === this.work_form.groups.length && this.work_form.groups.length > 0
        },
        chipViewRows() {
            if (this.work_form.is_group_work) return []
            const byId = new Map(this.activeCourseStudents.map((student) => [String(student.id), student]))
            const rows = []

            ;(this.work_form.groups || []).forEach((group, groupIndex) => {
                this.sortedGroupStudentIds(group).forEach((studentId) => {
                    const student = byId.get(String(studentId))
                    if (!student) return
                    const gradeValue = this.getGroupStudentGrade(group, studentId)
                    const commentValue = this.getGroupStudentComment(group, studentId)
                    const commentPreview = (commentValue || '').toString().trim().slice(0, 120)
                    rows.push({
                        groupIndex,
                        studentId,
                        student,
                        classLabel: this.studentClassValue(student),
                        studentLabel: this.studentLabel(student),
                        gradeValue,
                        commentValue,
                        commentPreview,
                    })
                })
            })

            return rows.sort((a, b) => this.compareStudentsBySelectedSort(a.student, b.student))
        },
        pointsViewRows() {
            if (this.work_form.is_group_work || !this.selectedTypeSupportsPoints) return []
            const byId = new Map(this.activeCourseStudents.map((student) => [String(student.id), student]))
            const rows = []

            ;(this.work_form.groups || []).forEach((group, groupIndex) => {
                this.sortedGroupStudentIds(group).forEach((studentId) => {
                    const student = byId.get(String(studentId))
                    if (!student) return
                    rows.push({
                        groupIndex,
                        studentId,
                        student,
                        classLabel: this.studentClassValue(student),
                        studentLabel: this.studentLabel(student),
                        pointsValue: this.getGroupStudentPoints(group, studentId),
                        commentValue: this.getGroupStudentComment(group, studentId),
                        gradeValue: this.getGroupStudentGrade(group, studentId),
                    })
                })
            })

            return rows.sort((a, b) => this.compareStudentsBySelectedSort(a.student, b.student))
        },
        commentDialogStudentLabel() {
            if (this.comment_dialog_student_id == null) return ''
            return this.studentNameById(this.comment_dialog_student_id)
        },
        hasUnassignedStudents() {
            const allStudentIds = this.activeCourseStudents.map((s) => String(s.id))
            const assignedIds = new Set()
            ;(this.work_form.groups || []).forEach((group) => {
                ;(group.student_ids || []).forEach((id) => assignedIds.add(String(id)))
            })
            return allStudentIds.some((id) => !assignedIds.has(id))
        },
    },

    watch: {
        activeSemester(val) {
            if (val !== this.config?.user?.teaching_active_semester) {
                this.teachingStore.saveActiveSemester(val)
            }
        },
        'config.user.teaching_active_semester'(val) {
            if (val) this.activeSemester = val
        },
        selected_course: {
            handler() {
                this.refreshWorks()
            },
            deep: true,
        },
        selected_courseWork: {
            handler(work) {
                if (!work?.id) return
                if (this.selected_course?.id && work.teaching_course_id !== this.selected_course.id) return
                this.editWork(work)
            },
            deep: false,
        },
        'work_form.date_for_all_groups'(val) {
            if (val && val instanceof Date) {
                this.work_form.date_for_all_groups = this.toDateString(val)
                return
            }
            // Don't update group dates during form initialization (e.g., when loading for edit)
            if (this.is_initializing_form) return
            // Update all group dates when the main date changes
            if (val && this.work_form.groups?.length) {
                this.work_form.groups.forEach((group) => {
                    group.date = val
                })
            }
        },
        'work_form.is_group_work'(val, oldVal) {
            if (this.is_initializing_form) return
            if (!val) {
                this.work_form.is_random_groups = false
                this.work_form.group_size = null
                this.work_form.groups = this.buildIndividualGroups()
                return
            }
            if (val && !oldVal) {
                if (!this.work_form.group_size || this.work_form.group_size < 2) {
                    this.work_form.group_size = 2
                }
                if (this.work_form.is_random_groups) {
                    this.generateRandomGroups()
                } else {
                    this.work_form.groups = []
                }
            }
        },
        'work_form.type'() {
            if (!this.selectedTypeSupportsPoints) {
                this.show_points_grading_view = false
            }
        },
        students_sort_mode() {
            if (this.work_form?.is_group_work) return
            this.work_form.groups = this.sortGroupsByStudent(this.work_form.groups || [])
        },
    },

    methods: {
        isStudentInactive(student) {
            return !!student?.canceled_at || !!student?.deleted_at
        },
        normalizeNumericInput(value) {
            const sanitized = String(value ?? '')
                .replace('.', ',')
                .replace(/\s+/g, '')

            let normalized = ''
            let hasDecimalSeparator = false

            for (let index = 0; index < sanitized.length; index += 1) {
                const char = sanitized[index]

                if (char >= '0' && char <= '9') {
                    normalized += char
                    continue
                }

                if (char === '-' && normalized === '') {
                    normalized += char
                    continue
                }

                if (char === ',' && !hasDecimalSeparator) {
                    normalized += char
                    hasDecimalSeparator = true
                }
            }

            return normalized
        },
        normalizePointsNumber(value) {
            const normalized = this.normalizeNumericInput(value)
            if (normalized === '' || normalized === '-') return null

            const parsed = parseFloat(normalized.replace(',', '.'))
            return Number.isNaN(parsed) ? null : parsed
        },
        displayPointsValue(value) {
            return this.normalizeNumericInput(value)
        },
        workConfigForType(type) {
            if (!type) return null
            return this.teachingWorks.find((work) => work.short_name === type) || null
        },
        workSupportsPoints(work) {
            if (!work) return false

            return Boolean(
                work.points_note_enabled
                || (Array.isArray(work.points_table) && work.points_table.length > 0)
                || String(work.points_sonst_grade || '').trim() !== ''
            )
        },
        gradeFromPointsForWork(work, points) {
            if (!this.workSupportsPoints(work) || points == null) return ''

            const table = Array.isArray(work.points_table) ? work.points_table : []
            const fallbackGrade = String(work.points_sonst_grade || '').trim()

            if (!table.length) {
                return fallbackGrade
            }

            const sorted = [...table].sort((left, right) => (right.min_points ?? 0) - (left.min_points ?? 0))
            const found = sorted.find((row) => points >= (row.min_points ?? 0))

            return String(found?.grade || fallbackGrade || '').trim()
        },
        effectiveGradeForType(type, rawGrade) {
            const direct = (rawGrade || '').toString().trim()
            if (direct) return direct

            const work = this.workConfigForType(type)
            const defaultGrade = (work?.default_grade || '').toString().trim()
            if (!defaultGrade) return ''

            const exists = (work?.grades || []).some((grade) => (grade?.grade || '').toString().trim().toUpperCase() === defaultGrade.toUpperCase())
            return exists ? defaultGrade : ''
        },
        getGroupStudentGrade(group, studentId) {
            if (!group || typeof group !== 'object') return ''
            const raw = group.grades?.[studentId] ?? group.grades?.[String(studentId)] ?? ''
            return this.effectiveGradeForType(this.work_form.type, raw)
        },
        getGroupStudentComment(group, studentId) {
            if (!group || typeof group !== 'object') return ''
            return group.comments?.[studentId] ?? group.comments?.[String(studentId)] ?? ''
        },
        getGroupStudentPoints(group, studentId) {
            if (!group || typeof group !== 'object') return ''
            const raw = group.points?.[studentId] ?? group.points?.[String(studentId)] ?? ''
            return this.displayPointsValue(raw)
        },
        setStudentGrade(groupIndex, studentId, value) {
            const group = this.work_form.groups?.[groupIndex]
            if (!group) return
            if (!group.grades || typeof group.grades !== 'object') {
                group.grades = {}
            }
            group.grades[studentId] = value ?? ''
        },
        setStudentComment(groupIndex, studentId, value) {
            const group = this.work_form.groups?.[groupIndex]
            if (!group) return
            if (!group.comments || typeof group.comments !== 'object') {
                group.comments = {}
            }
            group.comments[studentId] = (value || '').toString()
        },
        setStudentPoints(groupIndex, studentId, value) {
            const group = this.work_form.groups?.[groupIndex]
            if (!group) return
            if (!group.points || typeof group.points !== 'object') {
                group.points = {}
            }
            if (!group.grades || typeof group.grades !== 'object') {
                group.grades = {}
            }

            const normalizedPoints = this.normalizeNumericInput(value)
            group.points[studentId] = normalizedPoints

            const numericPoints = this.normalizePointsNumber(normalizedPoints)
            group.grades[studentId] = this.gradeFromPointsForWork(this.selectedTypeWork, numericPoints)
        },
        openCommentDialog(groupIndex, studentId) {
            const group = this.work_form.groups?.[groupIndex]
            if (!group) return
            this.comment_dialog_group_index = groupIndex
            this.comment_dialog_student_id = studentId
            this.comment_dialog_value = this.getGroupStudentComment(group, studentId)
            this.comment_dialog_open = true
        },
        closeCommentDialog() {
            this.comment_dialog_open = false
            this.comment_dialog_group_index = null
            this.comment_dialog_student_id = null
            this.comment_dialog_value = ''
        },
        saveCommentDialog() {
            const groupIndex = this.comment_dialog_group_index
            const studentId = this.comment_dialog_student_id
            if (groupIndex == null || studentId == null) {
                this.closeCommentDialog()
                return
            }
            const group = this.work_form.groups?.[groupIndex]
            if (!group) {
                this.closeCommentDialog()
                return
            }
            if (!group.comments || typeof group.comments !== 'object') {
                group.comments = {}
            }
            group.comments[studentId] = (this.comment_dialog_value || '').toString()
            this.closeCommentDialog()
        },
        serializeGroupPoints(group) {
            return (group.student_ids || [])
                .map((id) => {
                    const numericPoints = this.normalizePointsNumber(group.points?.[id] ?? group.points?.[String(id)] ?? '')

                    if (numericPoints == null) {
                        return null
                    }

                    return {
                        student_id: id,
                        points: numericPoints,
                    }
                })
                .filter(Boolean)
        },
        toggleChipGradingView() {
            this.show_points_grading_view = false
            this.show_chip_grading_view = !this.show_chip_grading_view
        },
        togglePointsGradingView() {
            if (!this.selectedTypeSupportsPoints) return

            this.show_bulk_action = false
            this.selected_student_ids = []
            this.show_chip_grading_view = false
            this.show_points_grading_view = !this.show_points_grading_view
        },
        setGroupWork(value) {
            const nextVal = !!value
            const current = !!this.work_form.is_group_work
            // Switching to group work: check for individual entries
            if (nextVal && !current && this.hasIndividualEntries()) {
                this.pending_group_work = nextVal
                return
            }
            // Switching to individual work: check for group entries
            if (!nextVal && current && this.hasGroupEntries()) {
                this.pending_group_work = nextVal
                return
            }
            this.work_form.is_group_work = nextVal
        },
        confirmGroupWorkChange() {
            if (this.pending_group_work === null) return
            this.work_form.is_group_work = this.pending_group_work
            this.pending_group_work = null
            this.group_work_switch_key++
        },
        cancelGroupWorkChange() {
            // Restore the opposite of pending (i.e., keep the current value)
            this.work_form.is_group_work = !this.pending_group_work
            this.pending_group_work = null
            this.group_work_switch_key++
        },
        requestRandomGroups() {
            if (this.hasGroupEntries()) {
                this.pending_random_groups = true
                return
            }
            this.generateRandomGroups()
        },
        confirmRandomGroups() {
            this.pending_random_groups = false
            this.generateRandomGroups()
        },
        cancelRandomGroups() {
            this.pending_random_groups = false
        },
        hasGroupEntries() {
            const groups = this.work_form.groups || []
            if (!groups.length) return false
            return groups.some((group) => {
                const hasStudents = Array.isArray(group?.student_ids) && group.student_ids.length > 0
                const gradeVals = group?.grades ? Object.values(group.grades) : []
                const commentVals = group?.comments ? Object.values(group.comments) : []
                const pointsVals = group?.points ? Object.values(group.points) : []
                const hasGrade = gradeVals.some((v) => (v ?? '').toString().trim() !== '')
                const hasComment = commentVals.some((v) => (v ?? '').toString().trim() !== '')
                const hasPoints = pointsVals.some((v) => (v ?? '').toString().trim() !== '')
                const groupGrade = (group?.grade ?? '').toString().trim() !== ''
                const groupComment = (group?.comment ?? '').toString().trim() !== ''
                return hasStudents || hasGrade || hasComment || hasPoints || groupGrade || groupComment
            })
        },
        hasIndividualEntries() {
            const groups = this.work_form.groups || []
            return groups.some((group) => {
                const gradeVals = group?.grades ? Object.values(group.grades) : []
                const commentVals = group?.comments ? Object.values(group.comments) : []
                const pointsVals = group?.points ? Object.values(group.points) : []
                const hasGrade = gradeVals.some((v) => (v ?? '').toString().trim() !== '')
                const hasComment = commentVals.some((v) => (v ?? '').toString().trim() !== '')
                const hasPoints = pointsVals.some((v) => (v ?? '').toString().trim() !== '')
                const groupGrade = (group?.grade ?? '').toString().trim() !== ''
                const groupComment = (group?.comment ?? '').toString().trim() !== ''
                return hasGrade || hasComment || hasPoints || groupGrade || groupComment
            })
        },
        emptyWorkForm() {
            return {
                id: null,
                teaching_course_id: null,
                type: '',
                title: '',
                description: '',
                is_group_work: false,
                group_size: null,
                is_random_groups: false,
                date_for_all_groups: '',
                groups: [],
                status: [],
            }
        },
        async refreshWorks() {
            if (!this.selected_course?.id) {
                this.courseWorkStore.clearWorks()
                return
            }
            await this.courseWorkStore.index(this.selected_course.id)
        },
        newWork() {
            this.is_initializing_form = true
            this.work_form = this.emptyWorkForm()
            this.work_form.teaching_course_id = this.selected_course?.id || null
            this.work_form.groups = this.buildIndividualGroups()
            this.singlePanels = []
            this.pending_random_groups = false
            this.show_bulk_action = false
            this.bulk_grade = null
            this.bulk_comment = ''
            this.selected_student_ids = []
            this.show_points_grading_view = false
            this.closeCommentDialog()
            this.action = 'new_course_work'
            this.$nextTick(() => {
                this.is_initializing_form = false
            })
        },
        editWork(work) {
            this.is_initializing_form = true
            const activeStudentIdSet = new Set(this.activeCourseStudents.map((student) => String(student.id)))
            this.work_form = {
                ...this.emptyWorkForm(),
                ...work,
            }
            // Clear the type if it is no longer a valid short_name in the current schema
            // (e.g. orphaned UUID from a deleted schema). Forces the user to pick a valid type.
            if (this.work_form.type && !this.teachingWorks.some((w) => w.short_name === this.work_form.type)) {
                this.work_form.type = ''
            }
            // Normalize the main date (server may return ISO format)
            this.work_form.date_for_all_groups = this.normalizeDateString(this.work_form.date_for_all_groups)
            this.work_form.groups = (this.work_form.groups || []).map((group) => {
                const gradesArray = Array.isArray(group.grades) ? group.grades : []
                const grades = gradesArray.reduce((acc, item) => {
                    if (item?.student_id) acc[item.student_id] = item.grade ?? ''
                    return acc
                }, {})
                const commentsArray = Array.isArray(group.comments) ? group.comments : []
                const comments = commentsArray.reduce((acc, item) => {
                    if (item?.student_id) acc[item.student_id] = item.comment ?? ''
                    return acc
                }, {})
                const pointsArray = Array.isArray(group.points) ? group.points : []
                const points = pointsArray.reduce((acc, item) => {
                    if (item?.student_id) acc[item.student_id] = this.displayPointsValue(item.points ?? '')
                    return acc
                }, {})
                const studentIds = Array.isArray(group?.student_ids)
                    ? group.student_ids.filter((id) => activeStudentIdSet.has(String(id)))
                    : []
                const filteredGrades = Object.fromEntries(
                    Object.entries(grades).filter(([id]) => activeStudentIdSet.has(String(id)))
                )
                const filteredComments = Object.fromEntries(
                    Object.entries(comments).filter(([id]) => activeStudentIdSet.has(String(id)))
                )
                const filteredPoints = Object.fromEntries(
                    Object.entries(points).filter(([id]) => activeStudentIdSet.has(String(id)))
                )
                return {
                    ...group,
                    student_ids: studentIds,
                    // Normalize group date (server may return ISO format)
                    date: this.normalizeDateString(group.date),
                    grades: filteredGrades,
                    comments: filteredComments,
                    points: filteredPoints,
                    use_individual_grades: Object.keys(filteredGrades).length > 0,
                }
            })
            // Ignore persisted empty groups (legacy/corrupt data) to avoid blank student rows in edit UI.
            this.work_form.groups = this.work_form.groups.filter((group) => Array.isArray(group?.student_ids) && group.student_ids.length > 0)
            if (!this.work_form.is_group_work) {
                this.work_form.groups = this.work_form.groups.map((group) => ({
                    ...group,
                    use_individual_grades: true,
                }))
                // Sort groups by student class, then last_name for non-group works
                this.work_form.groups = this.sortGroupsByStudent(this.work_form.groups)
            }
            this.singlePanels = []
            this.pending_random_groups = false
            this.show_bulk_action = false
            this.bulk_grade = null
            this.bulk_comment = ''
            this.selected_student_ids = []
            this.show_points_grading_view = false
            this.closeCommentDialog()
            this.action = 'edit_course_work'
            this.$nextTick(() => {
                this.is_initializing_form = false
            })
        },
        abortEdit() {
            this.action = ''
            this.selected_courseWork = null
            this.work_form = this.emptyWorkForm()
            this.pending_random_groups = false
            this.show_bulk_action = false
            this.bulk_grade = null
            this.bulk_comment = ''
            this.selected_student_ids = []
            this.show_points_grading_view = false
            this.closeCommentDialog()

            // If we came from student detail, return to it
            if (this.courseStore.previous_selected_student) {
                this.selected_course_student = this.courseStore.previous_selected_student
                this.action_2 = 'course_student_view'

                // Restore the visibility status of Infos and Termine
                if (this.courseStore.previous_show_infos !== null) {
                    this.show_infos = this.courseStore.previous_show_infos
                }
                if (this.courseStore.previous_show_dates !== null) {
                    this.show_dates = this.courseStore.previous_show_dates
                }

                // Clear the saved state
                this.courseStore.previous_selected_student = null
                this.courseStore.previous_show_infos = null
                this.courseStore.previous_show_dates = null
            }
        },
        async saveWork(stayOnPage = false) {
            // Prevent multiple saves while one is in progress
            if (this.is_saving) return
            this.is_saving = true

            try {
                if (!this.work_form.is_group_work) {
                    if (!this.work_form.groups?.length) {
                        this.work_form.groups = this.buildIndividualGroups()
                    }
                } else if (this.work_form.is_random_groups && !this.work_form.groups?.length) {
                    this.generateRandomGroups()
                }

                this.work_form.groups = (this.work_form.groups || []).map((group) => {
                    // Convert date to YYYY-MM-DD string format
                    const date = this.normalizeDateString(group.date)
                    if (!group.use_individual_grades) {
                        return { ...group, date: date || null, grades: [], points: [], comments: [] }
                    }
                    const grades = (group.student_ids || []).map((id) => ({
                        student_id: id,
                        grade: group.grades?.[id] ?? '',
                    }))
                    const comments = (group.student_ids || []).map((id) => ({
                        student_id: id,
                        comment: group.comments?.[id] ?? '',
                    }))
                    const points = this.serializeGroupPoints(group)
                    return { ...group, date: date || null, grade: '', comment: '', grades, comments, points }
                })

                // Convert date_for_all_groups to YYYY-MM-DD string format
                const dateForAllGroups = this.normalizeDateString(this.work_form.date_for_all_groups)

                const payload = {
                    ...this.work_form,
                    type: this.work_form.type || null,
                    date_for_all_groups: dateForAllGroups || null,
                    teaching_course_id: this.selected_course?.id || this.work_form.teaching_course_id,
                }

                let ok = false
                let savedWorkId = this.work_form.id
                if (this.work_form.id) {
                    // Update existing work
                    ok = await this.courseWorkStore.update(payload)
                } else {
                    // Create new work
                    const result = await this.courseWorkStore.store(payload)
                    ok = !!result
                    if (ok && result?.data?.id) {
                        // Capture the new ID from the response
                        savedWorkId = result.data.id
                        // Update form ID so subsequent saves use update instead of store
                        this.work_form.id = savedWorkId
                        // Switch to edit mode since the work now exists
                        this.action = 'edit_course_work'
                    }
                }

                if (ok) {
                    await this.refreshWorks()
                    // If the saved work is hidden by semester filter, switch to 1+2 so it is immediately visible.
                    if (savedWorkId && this.semesterCount === 2 && !this.filteredCourseWorks.find((w) => w.id === savedWorkId)) {
                        this.activeSemester = 3
                    }
                    if (stayOnPage) {
                        // Find the saved work and reload it for editing
                        const savedWork = this.courseWorks.find((w) => w.id === savedWorkId)
                        if (savedWork) {
                            this.editWork(savedWork)
                        }
                    } else {
                        this.abortEdit()
                    }
                }
            } finally {
                this.is_saving = false
            }
        },
        async deleteWork(work) {
            const ok = await this.courseWorkStore.destroy(work.id)
            if (ok) {
                await this.refreshWorks()
            }
            this.delete_work_id = null
        },
        buildIndividualGroups() {
            const students = this.activeCourseStudents
            return students.map((student) => ({
                student_ids: [student.id],
                date: this.work_form.date_for_all_groups || '',
                comment: '',
                grade: '',
                grades: { [student.id]: '' },
                comments: { [student.id]: '' },
                points: {},
                use_individual_grades: true,
            }))
        },
        addGroup() {
            if (!Array.isArray(this.work_form.groups)) this.work_form.groups = []
            this.work_form.groups.push({
                student_ids: [],
                date: this.work_form.date_for_all_groups || '',
                comment: '',
                grade: '',
                grades: {},
                comments: {},
                points: {},
                use_individual_grades: false,
            })
        },
        removeGroup(index) {
            if (!Array.isArray(this.work_form.groups)) return
            this.work_form.groups.splice(index, 1)
        },
        isGroupEmpty(group) {
            return !Array.isArray(group?.student_ids) || group.student_ids.length === 0
        },
        generateRandomGroups() {
            const students = this.activeCourseStudents.map((s) => s.id)
            if (!students.length) {
                this.work_form.groups = []
                return
            }
            const size = parseInt(this.work_form.group_size, 10)
            if (!size || size < 2) return

            const shuffled = [...students]
            for (let i = shuffled.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1))
                ;[shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]]
            }

            const groups = []
            for (let i = 0; i < shuffled.length; i += size) {
                groups.push(shuffled.slice(i, i + size))
            }

            if (groups.length > 1 && groups[groups.length - 1].length === 1) {
                const single = groups.pop()
                groups.forEach((group) => {
                    if (single.length === 0) return
                    if (group.length < size + 1) {
                        group.push(single.shift())
                    }
                })
                if (single.length) {
                    groups[groups.length - 1].push(...single)
                }
            }

            this.work_form.groups = groups.map((ids) => ({
                student_ids: ids,
                date: this.work_form.date_for_all_groups || '',
                comment: '',
                grade: '',
                grades: {},
                comments: {},
                points: {},
                use_individual_grades: false,
            }))
            this.work_form.is_random_groups = false
        },
        studentLabel(student) {
            const cls = student.schoolclass ? `${student.schoolclass} ` : ''
            const name = `${student.last_name || ''}, ${student.first_name || ''}`.trim()
            return `${cls}${name}`.trim()
        },
        studentClassValue(student) {
            return (student?.schoolclass || student?.class || '').toString()
        },
        compareStudentsBySelectedSort(a, b) {
            const lastA = (a?.last_name ?? a?._last ?? '').toString()
            const lastB = (b?.last_name ?? b?._last ?? '').toString()
            const firstA = (a?.first_name ?? a?._first ?? '').toString()
            const firstB = (b?.first_name ?? b?._first ?? '').toString()
            const classA = (a?._class ?? this.studentClassValue(a)).toString()
            const classB = (b?._class ?? this.studentClassValue(b)).toString()

            if (this.students_sort_mode === 'last_name_first_name') {
                const lastCmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
                if (lastCmp !== 0) return lastCmp
                const firstCmp = firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
                if (firstCmp !== 0) return firstCmp
                return classA.localeCompare(classB, 'de', { numeric: true, sensitivity: 'base' })
            }

            const classCmp = classA.localeCompare(classB, 'de', { numeric: true, sensitivity: 'base' })
            if (classCmp !== 0) return classCmp
            const lastCmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
            if (lastCmp !== 0) return lastCmp
            return firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
        },
        availableStudentItems(groupIndex) {
            const groups = this.work_form.groups || []
            const currentIds = new Set(groups[groupIndex]?.student_ids || [])
            const taken = new Set()

            groups.forEach((group, idx) => {
                if (idx === groupIndex) return
                ;(group.student_ids || []).forEach((id) => taken.add(id))
            })

            return this.studentItems.filter((item) => !taken.has(item.value) || currentIds.has(item.value))
        },
        updateGroupStudents(group, ids) {
            if (!group) return
            group.student_ids = Array.isArray(ids) ? ids : []
            group.student_ids = this.sortedGroupStudentIds(group)
        },
        toggleAllSinglePanels() {
            if (this.allSinglePanelsOpen) {
                this.singlePanels = []
                this.show_bulk_action = false
                this.selected_student_ids = []
                return
            }
            this.singlePanels = this.work_form.groups.map((_, idx) => idx)
        },
        toggleBulkAction() {
            if (this.show_bulk_action) {
                this.show_bulk_action = false
                this.selected_student_ids = []
                return
            }
            this.show_points_grading_view = false
            this.show_chip_grading_view = false
            if (!this.allSinglePanelsOpen) {
                this.singlePanels = this.work_form.groups.map((_, idx) => idx)
            }
            this.show_bulk_action = true
        },
        studentNameById(studentId) {
            const student = this.activeCourseStudents.find((s) => String(s.id) === String(studentId))
            if (!student) return String(studentId || '')
            return this.studentLabel(student)
        },
        workHasAllGrades(work) {
            const groups = Array.isArray(work?.groups) ? work.groups : []
            if (!groups.length) return false
            const nonEmptyGroups = groups.filter((group) => Array.isArray(group?.student_ids) && group.student_ids.length > 0)
            if (!nonEmptyGroups.length) return false

            const hasGrade = (value) => this.effectiveGradeForType(work?.type, value) !== ''

            // For group-work: complete when every group has either
            // 1) one shared grade, or
            // 2) individual grades for all students in that group.
            if (work?.is_group_work) {
                return nonEmptyGroups.every((group) => {
                    const ids = Array.isArray(group?.student_ids) ? group.student_ids : []
                    if (!ids.length) return false

                    const groupGrade = group?.grade
                    if (hasGrade(groupGrade)) return true

                    const gradesArray = Array.isArray(group?.grades) ? group.grades : []
                    if (gradesArray.length) {
                        const gradesByStudent = new Map()
                        gradesArray.forEach((item) => {
                            if (!item?.student_id) return
                            if (hasGrade(item.grade)) gradesByStudent.set(String(item.student_id), true)
                        })
                        return ids.every((id) => gradesByStudent.has(String(id)))
                    }

                    const gradesObj = group?.grades && typeof group.grades === 'object' && !Array.isArray(group.grades) ? group.grades : null
                    if (gradesObj) {
                        return ids.every((id) => hasGrade(gradesObj[id]) || hasGrade(gradesObj[String(id)]))
                    }

                    return false
                })
            }

            // For non-group work: all students that are part of this work need a grade.
            const students = [...new Set(nonEmptyGroups.flatMap((group) => (Array.isArray(group?.student_ids) ? group.student_ids : [])))]
            if (!students.length) return false

            const gradesByStudent = new Map()
            nonEmptyGroups.forEach((group) => {
                const ids = Array.isArray(group?.student_ids) ? group.student_ids : []
                const groupGrade = (group?.grade ?? '').toString().trim()
                const gradesArray = Array.isArray(group?.grades) ? group.grades : []

                if (gradesArray.length) {
                    gradesArray.forEach((item) => {
                        if (!item?.student_id) return
                        const val = this.effectiveGradeForType(work?.type, item.grade)
                        if (val !== '') {
                            gradesByStudent.set(String(item.student_id), val)
                        }
                    })
                    return
                }

                if (groupGrade !== '' && ids.length) {
                    ids.forEach((id) => gradesByStudent.set(String(id), groupGrade))
                }
            })

            return students.every((id) => gradesByStudent.has(String(id)))
        },
        groupSizeHint(group) {
            if (!this.work_form.is_group_work) return ''
            const size = parseInt(this.work_form.group_size, 10)
            if (!size || size < 2) return ''
            const count = Array.isArray(group?.student_ids) ? group.student_ids.length : 0
            if (count <= size) return ''
            return `Hinweis: Diese Gruppe hat ${count} Mitglieder (geplant: ${size}). Bitte prüfen.`
        },
        sortedGroupStudentIds(group) {
            const ids = Array.isArray(group?.student_ids) ? [...group.student_ids] : []
            const byId = new Map(this.activeCourseStudents.map((s) => [String(s.id), s]))
            const filteredIds = ids.filter((id) => byId.has(String(id)))

            return filteredIds.sort((a, b) => {
                const sa = byId.get(String(a))
                const sb = byId.get(String(b))
                return this.compareStudentsBySelectedSort(sa, sb)
            })
        },
        sortGroupsByStudent(groups) {
            if (!Array.isArray(groups)) return []
            const byId = new Map(this.activeCourseStudents.map((s) => [String(s.id), s]))

            return [...groups].sort((a, b) => {
                // For non-group works, each group has one student
                const studentIdA = a.student_ids?.[0]
                const studentIdB = b.student_ids?.[0]
                const sa = byId.get(String(studentIdA))
                const sb = byId.get(String(studentIdB))
                return this.compareStudentsBySelectedSort(sa, sb)
            })
        },
        selectDate(dateStr) {
            if (!dateStr) return
            this.work_form.date_for_all_groups = this.toDateString(dateStr)
        },
        formatDate(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        formatDateWithWeekday(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            const weekday = d.toLocaleDateString('de-DE', { weekday: 'short' })
            const formatted = this.formatDate(d)
            return `${weekday} ${formatted}`
        },
        workDateColor(date) {
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return 'primary'
            d.setHours(0, 0, 0, 0)
            const today = new Date()
            today.setHours(0, 0, 0, 0)
            return d <= today ? 'error' : 'primary'
        },
        workListDate(work) {
            const groups = Array.isArray(work?.groups) ? work.groups : []
            const groupDates = groups
                .map((group) => this.normalizeDateString(group?.date))
                .filter((date) => !!date)
                .filter((date) => {
                    const parsed = parseLocalDate(date)
                    return !isNaN(parsed.getTime())
                })
                .sort((a, b) => a.localeCompare(b))
            if (groupDates.length) return groupDates[0]
            return this.normalizeDateString(work?.date_for_all_groups)
        },
        workListDateColor(work) {
            const date = this.workListDate(work)
            if (!date) return 'primary'
            if (this.workHasAllGrades(work)) return 'primary'
            return this.workDateColor(date)
        },
        workTypeLabel(type) {
            if (!type) return ''
            const found = this.teachingWorks.find((w) => w.short_name === type)
            if (!found) return 'Unbekannter Typ'
            return `${found.short_name} - ${found.name}`
        },
        toDateString(date) {
            const d = parseLocalDate(date)
            const year = d.getFullYear()
            const month = String(d.getMonth() + 1).padStart(2, '0')
            const day = String(d.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
        normalizeDateString(date) {
            if (!date) return ''
            // If it's a Date object, convert to string
            if (date instanceof Date) {
                return this.toDateString(date)
            }
            // If it's a string with ISO format (contains 'T'), extract just the date part
            if (typeof date === 'string' && date.includes('T')) {
                return date.split('T')[0]
            }
            // Otherwise return as-is (should be YYYY-MM-DD format already)
            return date
        },
        selectAllStudents() {
            const allIds = []
            ;(this.work_form.groups || []).forEach((group) => {
                ;(group.student_ids || []).forEach((id) => allIds.push(id))
            })
            this.selected_student_ids = allIds
        },
        deselectAllStudents() {
            this.selected_student_ids = []
        },
        applyBulkAction() {
            const targetIds = this.selected_student_ids.length > 0 ? new Set(this.selected_student_ids) : null
            ;(this.work_form.groups || []).forEach((group) => {
                ;(group.student_ids || []).forEach((studentId) => {
                    if (targetIds && !targetIds.has(studentId)) return
                    if (this.bulk_grade) {
                        group.grades[studentId] = this.bulk_grade
                    }
                    if (this.bulk_comment) {
                        group.comments[studentId] = this.bulk_comment
                    }
                })
            })
            // Reset bulk fields after applying
            this.bulk_grade = null
            this.bulk_comment = ''
            this.selected_student_ids = []
        },
    },
}
</script>

<style scoped>
.work-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
}

.work-date-chip {
    flex: 0 0 auto;
}

.work-title {
    min-width: 140px;
}

.work-type-first-line {
    padding-left: 1px;
    line-height: 1.35;
}

.work-title-second-line {
    line-height: 1.35;
}

.work-actions {
    margin-left: auto;
    flex: 0 0 auto;
}

@media (max-width: 700px) {
    .work-title {
        flex-basis: 100%;
        min-width: 100%;
        margin-top: 2px;
        order: 2;
    }

    .work-actions {
        order: 1;
    }
}
</style>
