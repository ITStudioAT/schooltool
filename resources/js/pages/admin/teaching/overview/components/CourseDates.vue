<template>
    <ItsGridBox
        variant="overview"
        color="primary"
        icon="mdi-calendar"
        class="w-100"
        v-if="selected_course"
        :disabled="isGridDisabled">
        <template #title>
            <div class="course-dates-title-row d-flex align-center flex-wrap ga-3">
                <div>Termine – {{ selected_course.title }} ({{ selectedCourseClasses }})</div>
                <div v-if="semesterCount === 2" class="course-date-semester-selection d-flex justify-start">
                    <v-btn-toggle v-model="activeSemester" mandatory density="compact" color="primary">
                        <v-btn :value="1" size="small">1. Sem</v-btn>
                        <v-btn :value="2" size="small">2. Sem</v-btn>
                        <v-btn :value="3" size="small">Sem 1+2</v-btn>
                    </v-btn-toggle>
                </div>
            </div>
        </template>
        <template #header-actions>
            <v-btn icon="mdi-plus" size="small" variant="tonal" @click="newDates" :disabled="isEditingContent || isSavingContent || action === 'new_course_dates'" />
        </template>
        <v-card v-if="compactStudentView" tile flat color="transparent" class="w-100" :disabled="action != '' || isSavingContent">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <div class="d-flex flex-wrap align-center ga-2 mt-2 w-100">
                    <div v-if="compactStudentView" class="ml-auto d-flex">
                        <v-btn-toggle
                            v-model="dateRangeSelection"
                            multiple
                            mandatory
                            density="compact"
                            color="primary">
                            <v-btn value="before" size="small">Vorher</v-btn>
                            <v-btn value="today" size="small">Heute</v-btn>
                            <v-btn value="after" size="small">Später</v-btn>
                        </v-btn-toggle>
                    </div>
                </div>
            </v-card-text>
        </v-card>

        <!-- Termine (Anzeige) -->
        <v-card variant="outlined" class="mt-4" v-if="selected_course && action != 'new_course_dates'">
            <v-card-title class="text-subtitle-1 d-flex align-center ga-2 flex-wrap">
                <v-icon size="18">mdi-calendar-check</v-icon>
                Termine
                <v-chip v-if="displayedCourseDates?.length" size="x-small" color="primary" variant="tonal">
                    {{ displayedCourseDatesCount }}
                </v-chip>
                <v-spacer />
                <v-chip
                    v-if="selectedCourseCurriculumTitle"
                    size="small"
                    color="primary"
                    variant="tonal"
                    class="course-date-curriculum-chip"
                    prepend-icon="mdi-book-open-variant"
                    title="Zugewiesenes Curriculum">
                    {{ selectedCourseCurriculumTitle }}
                </v-chip>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-0">
                <v-list density="compact" class="course-dates-grid">
                    <template v-for="(courseDate, cdIdx) in displayedCourseDates" :key="courseDate.id">
                    <v-list-item
                        :ref="highlightedDateId === courseDate.id ? 'highlightedDateItem' : undefined"
                        :disabled="(isEditingContent && editing_content_id !== courseDate.id) || isSavingContent"
                        :class="courseDateRowClass(courseDate)"
                        :style="courseDateHighlightStyle(courseDate, cdIdx)">
                        <div class="d-flex flex-column ga-2 w-100 h-100 cursor-pointer" @click="selectCourseDate(courseDate)">
                            <div class="course-date-header d-flex align-start ga-2 w-100">
                                <div class="course-date-left d-flex flex-column">
                                    <div class="d-flex align-center flex-wrap ga-2">
                                        <div class="course-date-title">
                                            {{ getWeekday(courseDate.date) }}, {{ formatDate(courseDate.date) }}
                                        </div>
                                        <v-chip v-for="h in courseDate.hours" :key="h" size="x-small" variant="tonal">{{ h }}. Std</v-chip>
                                        <v-chip
                                            v-if="highlightedDateId === courseDate.id"
                                            size="x-small"
                                            :color="isDateToday(courseDate) ? 'success' : 'primary'"
                                            variant="flat"
                                            class="course-date-icon font-weight-bold px-2">
                                            {{ isDateToday(courseDate) ? 'Heute' : 'Nächster' }}
                                        </v-chip>
                                        <v-chip
                                            v-if="hasStatus(courseDate, 'free') && courseDate.free_reason"
                                            size="x-small"
                                            color="success"
                                            variant="outlined"
                                            class="course-date-free-reason">
                                            {{ courseDate.free_reason }}
                                        </v-chip>
                                        <v-chip
                                            v-for="work in courseWorksForDate(courseDate)"
                                            :key="`course-date-${courseDate.id}-work-${work.key}`"
                                            size="x-small"
                                            variant="tonal"
                                            :color="work.isGroupWork ? 'success' : 'primary'"
                                            prepend-icon="mdi-clipboard-text"
                                            class="course-date-work-chip cursor-pointer"
                                            :title="work.title"
                                            @click.stop="openCourseWork(work)">
                                            {{ work.label }}
                                        </v-chip>
                                        <span
                                            v-if="courseDateHasContent(courseDate)"
                                            class="course-date-inline-content text-body-2 text-medium-emphasis">
                                            {{ courseDateInlineContent(courseDate) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="course-date-actions d-flex align-center ga-1 ml-auto flex-shrink-0" @click.stop>
                                    <v-btn
                                        icon="mdi-account-group"
                                        size="x-small"
                                        color="indigo"
                                        variant="tonal"
                                        title="Schülerliste anzeigen"
                                        @click="switchToStudents(courseDate)" />
                                    <v-chip
                                        v-if="hasStatus(courseDate, 'free')"
                                        size="x-small"
                                        color="success"
                                        variant="flat"
                                        title="Systemverwaltet (Ferien/Freier Tag)">
                                        E
                                    </v-chip>
                                    <v-btn
                                        v-else
                                        size="x-small"
                                        :color="hasStatus(courseDate, 'entfaellt') ? 'success' : 'default'"
                                        :variant="hasStatus(courseDate, 'entfaellt') ? 'flat' : 'outlined'"
                                        :disabled="isBusyDateUi"
                                        :loading="isDateMutationPending('toggle-status', courseDate.id)"
                                        title="Entfällt (kursspezifisch)"
                                        @click="toggleStatus(courseDate, 'entfaellt')">
                                        E
                                    </v-btn>
                                    <v-btn
                                        size="x-small"
                                        :color="hasStatus(courseDate, 'pruefung') ? 'warning' : 'default'"
                                        :variant="hasStatus(courseDate, 'pruefung') ? 'flat' : 'outlined'"
                                        :disabled="isBusyDateUi"
                                        :loading="isDateMutationPending('toggle-status', courseDate.id)"
                                        @click="toggleStatus(courseDate, 'pruefung')">
                                        P
                                    </v-btn>
                                    <v-btn
                                        icon="mdi-pencil"
                                        size="x-small"
                                        color="primary"
                                        variant="tonal"
                                        :disabled="isEditingContent || isSavingContent"
                                        @click="startEditContent(courseDate)" />
                                    <v-btn
                                        v-if="delete_date_id !== courseDate.id"
                                        icon="mdi-delete"
                                        size="x-small"
                                        color="warning"
                                        variant="tonal"
                                        :disabled="isEditingContent || isSavingContent"
                                        @click="delete_date_id = courseDate.id" />
                                    <v-btn
                                        v-if="delete_date_id === courseDate.id"
                                        icon="mdi-delete-off"
                                        size="x-small"
                                        color="success"
                                        variant="tonal"
                                        :disabled="isEditingContent || isSavingContent"
                                        @click="delete_date_id = null" />
                                    <v-btn
                                        v-if="delete_date_id === courseDate.id"
                                        icon="mdi-delete"
                                        size="x-small"
                                        color="error"
                                        variant="tonal"
                                        :disabled="isBusyDateUi"
                                        :loading="isDateMutationPending('delete-date', courseDate.id)"
                                        @click="deleteDate(courseDate)" />
                                    </div>
                                </div>
                            <div
                                v-if="courseDateAdoptedMaterials(courseDate).length"
                                class="course-date-curriculum-inline pl-1 pr-2"
                                @click.stop>
                                <div class="course-date-curriculum-stack">
                                    <div
                                        v-for="group in courseDateAdoptedMaterialGroups(courseDate)"
                                        :key="`${courseDate.id}-adopted-group-${group.key}`"
                                        class="course-date-curriculum-stack__adopted-group">
                                        <div class="course-date-curriculum-stack__adopted-title d-flex align-center">
                                            <v-icon size="14" color="success" class="mr-1">mdi-check-circle-outline</v-icon>
                                            <span class="flex-grow-1">{{ group.title }}</span>
                                            <template v-if="group.duplicateSingleMaterial">
                                                <template v-if="group.materials[0].attachments && group.materials[0].attachments.length">
                                                    <v-icon size="14" class="mr-1 cursor-pointer course-date-material-icon" @click.stop="openAdoptedMaterialOverview(group.materials[0])" title="Materialien anzeigen">mdi-paperclip</v-icon>
                                                    <v-icon
                                                        size="12"
                                                        class="mr-1 cursor-pointer"
                                                        :color="adoptedAttachmentVisibilityColor(group.materials[0])"
                                                        :title="adoptedAttachmentVisibilityTitle(group.materials[0])"
                                                        @click.stop="openAdoptedMaterialOverview(group.materials[0])">
                                                        {{ adoptedAttachmentVisibilityIcon(group.materials[0]) }}
                                                    </v-icon>
                                                </template>
                                                <v-chip v-if="group.materials[0].type" size="x-small" variant="tonal" color="primary" class="ml-1">{{ group.materials[0].type }}</v-chip>
                                                <v-btn
                                                    icon="mdi-close"
                                                    size="x-small"
                                                    variant="text"
                                                    color="error"
                                                    class="course-date-adopt-btn ml-1"
                                                    title="Übernommenes Material entfernen"
                                                    :disabled="isEditingContent || isSavingContent || adoptSaving || deletingAdoptedId === group.materials[0].id"
                                                    :loading="deletingAdoptedId === group.materials[0].id"
                                                    @click.stop="deleteAdoptedMaterialGroup(group.materials[0])" />
                                            </template>
                                        </div>
                                        <div
                                            v-for="material in group.materials"
                                            v-show="!group.duplicateSingleMaterial"
                                            :key="`${courseDate.id}-adopted-material-${material.id}`"
                                            class="course-date-curriculum-stack__adopted-material">
                                            <div class="d-flex align-center">
                                                <template v-if="material.attachments && material.attachments.length">
                                                    <v-icon size="14" class="mr-1 cursor-pointer course-date-material-icon" @click.stop="openAdoptedMaterialOverview(material)" title="Materialien anzeigen">mdi-paperclip</v-icon>
                                                    <v-icon
                                                        size="12"
                                                        class="mr-1 cursor-pointer"
                                                        :color="adoptedAttachmentVisibilityColor(material)"
                                                        :title="adoptedAttachmentVisibilityTitle(material)"
                                                        @click.stop="openAdoptedMaterialOverview(material)">
                                                        {{ adoptedAttachmentVisibilityIcon(material) }}
                                                    </v-icon>
                                                </template>
                                                <span class="flex-grow-1">{{ material.title }}</span>
                                                <v-chip v-if="material.type" size="x-small" variant="tonal" color="primary" class="ml-1">{{ material.type }}</v-chip>
                                                <v-btn
                                                    icon="mdi-close"
                                                    size="x-small"
                                                    variant="text"
                                                    color="error"
                                                    class="course-date-adopt-btn ml-1"
                                                    title="Übernommenes Material entfernen"
                                                    :disabled="isEditingContent || isSavingContent || adoptSaving || deletingAdoptedId === material.id"
                                                    :loading="deletingAdoptedId === material.id"
                                                    @click.stop="deleteAdoptedMaterialGroup(material)" />
                                            </div>
                                            <div v-if="material.attachments && material.attachments.length" class="course-date-curriculum-stack__adopted-attachments">
                                                <div
                                                    v-for="attachment in material.attachments"
                                                    :key="`${courseDate.id}-adopted-material-${material.id}-attachment-${attachment.id}`"
                                                    class="course-date-curriculum-stack__adopted-attachment d-flex align-center"
                                                    @click.stop="openAdoptedMaterialOverview(material)">
                                                    <v-icon size="13" class="mr-1">{{ attachmentIcon(attachment) }}</v-icon>
                                                    <span class="flex-grow-1">{{ attachment.name }}</span>
                                                    <span class="text-medium-emphasis ml-1">{{ attachment.mime_type || 'Datei' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="curriculumEntriesForCourseDate(courseDate).length"
                                class="course-date-curriculum-bottom pl-1 pr-2 mt-auto"
                                @click.stop>
                                <div class="course-date-curriculum-divider">
                                    <span class="course-date-curriculum-divider__label">CURRICULUM</span>
                                </div>
                                <div class="course-date-curriculum-stack">
                                    <div
                                        v-for="(entry, entryIndex) in curriculumEntriesForCourseDate(courseDate)"
                                        :key="`${courseDate.id}-inline-${entryIndex}`"
                                        class="course-date-curriculum-stack__entry d-flex align-center">
                                        <v-icon v-if="entry.hasMaterials" size="14" class="mr-1 cursor-pointer course-date-material-icon" @click.stop="openMaterialOverview(entry)" title="Materialien anzeigen">mdi-paperclip</v-icon>
                                        <span class="flex-grow-1" :class="{ 'text-medium-emphasis': isCurriculumEntryFullyAdopted(courseDate, entry) }">{{ entry.label }}</span>
                                        <v-btn
                                            v-if="isCurriculumEntryFullyAdopted(courseDate, entry)"
                                            icon="mdi-check-circle"
                                            size="x-small"
                                            variant="text"
                                            color="success"
                                            class="course-date-adopt-btn ml-1"
                                            style="opacity: 1;"
                                            title="Bereits übernommen"
                                            disabled />
                                        <v-btn
                                            v-else
                                            icon="mdi-arrow-down-bold-circle-outline"
                                            size="x-small"
                                            variant="text"
                                            color="primary"
                                            class="course-date-adopt-btn ml-1"
                                            title="In Termin-Inhalt übernehmen"
                                            :disabled="isEditingContent || isSavingContent || adoptSaving"
                                            @click.stop="openAdoptDialog(courseDate, entry)" />
                                    </div>
                                </div>
                            </div>
                            <div v-if="editing_content_id === courseDate.id" class="pl-6 pr-2 pb-2" @click.stop>
                                <div class="d-flex flex-column ga-2">
                                    <ItsRichTextEditor
                                        v-model="content_drafts[courseDate.id]"
                                        :disabled="isSavingContent"
                                        :ref="`contentField-${courseDate.id}`" />
                                    <div class="d-flex align-center ga-2">
                                        <v-btn size="x-small" color="warning" variant="flat" @click="cancelEditContent(courseDate)" icon="mdi-close" :disabled="isSavingContent" />
                                        <v-btn
                                            size="x-small"
                                            color="success"
                                            variant="flat"
                                            @click="saveContent(courseDate)"
                                            icon="mdi-content-save"
                                            :disabled="isSavingContent"
                                            :loading="saving_content_id === courseDate.id" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </v-list-item>
                    </template>
                    <v-list-item v-if="!displayedCourseDates?.length">
                        <v-list-item-title class="text-caption text-medium-emphasis">Keine Termine vorhanden.</v-list-item-title>
                    </v-list-item>
                </v-list>
            </v-card-text>
        </v-card>

        <!-- NEUE TERMINE ANLEGEN -->
        <v-card tile flat color="transparent" class="w-100" v-if="action == 'new_course_dates'">
            <v-form ref="form" v-model="is_valid" @submit.prevent="createDates(data)" class="mb-4">
                <v-card-text>
                    <div class="d-flex align-center ga-2">
                        <v-date-input v-model="data.from" label="(Start-)Datum" class="flex-grow-1" />
                        <v-chip v-if="data.from" color="primary" variant="tonal" size="small">{{ getWeekday(data.from) }}</v-chip>
                    </div>
                    <div class="d-flex flex-wrap ga-1 mt-1">
                        <v-chip size="x-small" variant="outlined" class="cursor-pointer" @click="setFromDate(new Date())">Heute: {{ formatDate(new Date()) }}</v-chip>
                        <v-chip
                            size="x-small"
                            variant="outlined"
                            class="cursor-pointer"
                            @click="setFromDate(config.selected_schoolyear.from)"
                            v-if="config?.selected_schoolyear?.from">
                            Schuljahr: {{ formatDate(config.selected_schoolyear.from) }}
                        </v-chip>
                        <v-chip
                            size="x-small"
                            variant="outlined"
                            class="cursor-pointer"
                            @click="setFromDate(config.selected_schoolyear.sem_2_start)"
                            v-if="config.selected_schoolyear.sem_2_start">
                            2. Sem: {{ formatDate(config.selected_schoolyear.sem_2_start) }}
                        </v-chip>
                    </div>
                    <div class="d-flex align-center ga-2 mt-2">
                        <v-date-input v-model="data.until" label="Ende-Datum (darf leer bleiben)" class="flex-grow-1" />
                        <v-chip v-if="data.until" color="primary" variant="tonal" size="small">{{ getWeekday(data.until) }}</v-chip>
                    </div>
                    <div class="d-flex flex-wrap ga-1 mt-1" v-if="config?.selected_schoolyear">
                        <v-chip size="x-small" variant="outlined" class="cursor-pointer" @click="setUntilDate(getSem1End())" v-if="config?.selected_schoolyear?.sem_2_start">
                            Ende 1. Sem: {{ formatDate(getSem1End()) }}
                        </v-chip>
                        <v-chip
                            size="x-small"
                            variant="outlined"
                            class="cursor-pointer"
                            @click="setUntilDate(config.selected_schoolyear.until)"
                            v-if="config.selected_schoolyear.until">
                            Schuljahresende: {{ formatDate(config.selected_schoolyear.until) }}
                        </v-chip>
                    </div>
                    <div v-if="data.from">
                        <div class="mt-4">
                            <div class="text-caption text-medium-emphasis mb-2">Wiederholung</div>
                            <v-chip-group v-model="data.interval" mandatory selected-class="bg-primary" column>
                                <v-chip :value="1" filter variant="outlined">1 Woche</v-chip>
                                <v-chip :value="2" filter variant="outlined">2 Wochen</v-chip>
                                <v-chip :value="3" filter variant="outlined">3 Wochen</v-chip>
                                <v-chip :value="4" filter variant="outlined">4 Wochen</v-chip>
                            </v-chip-group>
                        </div>

                        <div class="mt-4" v-if="generatedDates.length">
                            <div class="text-caption text-medium-emphasis mb-2">Termine ({{ generatedDates.length }})</div>
                            <div class="d-flex flex-wrap ga-1">
                                <v-chip v-for="(date, index) in generatedDates" :key="index" size="small" variant="tonal" color="primary">
                                    {{ formatDate(date) }} ({{ getWeekday(date) }})
                                </v-chip>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="text-caption text-medium-emphasis mb-2">Stunden</div>
                            <v-chip-group v-model="data.hours" multiple selected-class="bg-primary" column>
                                <v-chip v-for="h in 20" :key="h" :value="h" filter variant="outlined" size="small">{{ h }}</v-chip>
                            </v-chip-group>
                        </div>
                    </div>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="warning" flat tile :disabled="isBusyDateUi" @click="abortNewCourseDates">Abbruch</v-btn>
                        <v-btn color="success" flat tile type="submit" :loading="isDateMutationPending('create-dates')" :disabled="isBusyDateUi" v-if="data.hours.length >= 1">Erstellen</v-btn>
                    </div>
                </v-card-text>
            </v-form>
        </v-card>

        <!-- Material Overview Dialog -->
        <v-dialog v-model="materialOverlayOpen" max-width="640" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                    <v-icon color="primary" size="22">mdi-paperclip</v-icon>
                    Materialien
                </v-card-title>
                <v-card-subtitle v-if="materialOverlayEntry" class="px-4 pb-1">
                    {{ materialOverlayEntry.label }}
                </v-card-subtitle>
                <v-card-text class="px-4 pb-2">
                    <div v-if="materialOverlayLoading" class="text-center py-6">
                        <v-progress-circular indeterminate color="primary" size="24" />
                    </div>
                    <template v-else-if="materialOverlayCards.length">
                        <div v-for="card in materialOverlayCards" :key="`mo-card-${card.id}`" class="material-overview-card mb-3">
                            <div class="material-overview-card__header d-flex align-center ga-2">
                                <v-icon size="18" color="primary">mdi-package-variant-closed</v-icon>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-center ga-2 flex-wrap">
                                        <span class="text-body-2 font-weight-medium">{{ card.title }}</span>
                                        <v-chip v-if="card.type" size="x-small" variant="tonal" color="primary">{{ card.type }}</v-chip>
                                        <v-chip v-if="materialStatusDisplay(card.status)" size="x-small" variant="tonal" :color="materialStatusDisplay(card.status).color">{{ materialStatusDisplay(card.status).label }}</v-chip>
                                    </div>
                                    <div v-if="materialSubtitle(card)" class="text-caption text-medium-emphasis">{{ materialSubtitle(card) }}</div>
                                </div>
                            </div>
                            <v-list v-if="card.attachments && card.attachments.length" bg-color="transparent" density="compact" class="py-0 mt-2">
                                <v-list-item
                                    v-for="attachment in card.attachments"
                                    :key="`mo-att-${attachment.id}`"
                                    class="material-overview-attachment mb-1 px-3"
                                    rounded="lg">
                                    <template #prepend>
                                        <v-icon size="18" color="#a5b4fc" class="mr-2">
                                            {{ attachmentIcon(attachment) }}
                                        </v-icon>
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ attachment.name }}</v-list-item-title>
                                    <v-list-item-subtitle class="text-caption">
                                        {{ attachment.mime_type || 'Datei' }}<span v-if="attachment.size_bytes"> · {{ formatFileSize(attachment.size_bytes) }}</span>
                                    </v-list-item-subtitle>
                                    <template #append>
                                        <div class="d-flex align-center ga-1">
                                            <v-btn
                                                v-if="materialOverlayIsAdopted"
                                                :icon="attachment.student_visible ? 'mdi-eye' : 'mdi-eye-off'"
                                                size="x-small"
                                                variant="text"
                                                :color="attachment.student_visible ? 'success' : 'grey'"
                                                :title="attachment.student_visible ? 'Für Schüler sichtbar' : 'Für Schüler nicht sichtbar'"
                                                :loading="togglingVisibilityId === attachment.id"
                                                :disabled="togglingVisibilityId === attachment.id"
                                                @click.stop="toggleAttachmentVisibility(attachment)" />
                                            <v-btn
                                                v-if="attachment.preview_url"
                                                variant="text"
                                                color="primary"
                                                size="x-small"
                                                class="text-none"
                                                :href="attachment.preview_url"
                                                target="_blank">
                                                Vorschau
                                            </v-btn>
                                            <v-btn
                                                v-if="attachment.download_url"
                                                variant="text"
                                                color="primary"
                                                size="x-small"
                                                class="text-none"
                                                :href="attachment.download_url"
                                                target="_blank">
                                                Download
                                            </v-btn>
                                        </div>
                                    </template>
                                </v-list-item>
                            </v-list>
                            <div v-else class="text-caption text-medium-emphasis py-2 pl-1">
                                Keine Anhänge vorhanden.
                            </div>
                        </div>
                    </template>
                    <div v-else class="text-center py-6 text-caption text-medium-emphasis">
                        Keine Materialien gefunden.
                    </div>
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-btn variant="tonal" @click="closeMaterialOverview">Schließen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Adopt Curriculum Content Dialog -->
        <v-dialog v-model="adoptDialogOpen" max-width="600" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                    <v-icon color="primary" size="22">mdi-arrow-down-bold-circle-outline</v-icon>
                    Inhalt übernehmen
                </v-card-title>
                <v-card-text class="px-4 pb-2">
                    <div class="text-caption text-medium-emphasis mb-3">
                        Text bearbeiten und in den Termin-Inhalt übernehmen.
                    </div>
                    <v-textarea
                        v-model="adoptDialogText"
                        label="Inhalt"
                        variant="outlined"
                        rows="3"
                        auto-grow
                        :disabled="adoptSaving" />
                    <div v-if="adoptDialogEntry?.hasMaterials && adoptDialogEntry?.materials?.length" class="mt-2">
                        <div class="text-caption text-medium-emphasis mb-1">
                            <v-icon size="14" class="mr-1">mdi-paperclip</v-icon>
                            Materialien als unabhängige Kopie übernehmen:
                        </div>
                        <div
                            v-for="mat in adoptDialogEntry.materials"
                            :key="`adopt-mat-${mat.id}`"
                            class="ml-1">
                            <v-checkbox
                                v-model="adoptDialogSelectedMaterialIds"
                                :value="mat.id"
                                :disabled="adoptSaving || isAdoptDialogMaterialFullyAdopted(mat)"
                                density="compact"
                                hide-details>
                                <template #label>
                                    <div class="d-flex align-center ga-2">
                                        <v-icon size="16" :color="isAdoptDialogMaterialFullyAdopted(mat) ? 'success' : 'primary'">
                                            {{ isAdoptDialogMaterialFullyAdopted(mat) ? 'mdi-check-circle' : 'mdi-package-variant-closed' }}
                                        </v-icon>
                                        <span class="text-body-2" :class="{ 'text-medium-emphasis': isAdoptDialogMaterialFullyAdopted(mat) }">{{ mat.title }}</span>
                                        <v-chip v-if="mat.type" size="x-small" variant="tonal" color="primary">{{ mat.type }}</v-chip>
                                        <v-chip v-if="isAdoptDialogMaterialFullyAdopted(mat)" size="x-small" variant="tonal" color="success">vollständig übernommen</v-chip>
                                    </div>
                                </template>
                            </v-checkbox>
                            <div
                                v-if="isAdoptDialogMaterialSelected(mat.id) && adoptDialogMaterialAttachments(mat).length"
                                class="adopt-material-attachments ml-7 mt-n1 mb-2">
                                <v-checkbox
                                    v-for="attachment in adoptDialogMaterialAttachments(mat)"
                                    :key="`adopt-mat-${mat.id}-att-${attachment.id}`"
                                    v-model="adoptDialogSelectedAttachmentIdsByMaterial[mat.id]"
                                    :value="attachment.id"
                                    :disabled="adoptSaving"
                                    density="compact"
                                    hide-details>
                                    <template #label>
                                        <div class="d-flex align-center ga-2">
                                            <v-icon size="14">{{ attachmentIcon(attachment) }}</v-icon>
                                            <span class="text-caption">{{ attachment.name }}</span>
                                            <span class="text-caption text-medium-emphasis">
                                                {{ attachment.mime_type || 'Datei' }}<span v-if="attachment.size_bytes"> · {{ formatFileSize(attachment.size_bytes) }}</span>
                                            </span>
                                        </div>
                                    </template>
                                </v-checkbox>
                            </div>
                            <div
                                v-else-if="isAdoptDialogMaterialSelected(mat.id) && adoptDialogMaterialLoading"
                                class="ml-7 mt-n1 mb-2 text-caption text-medium-emphasis">
                                Anhänge werden geladen...
                            </div>
                        </div>
                    </div>
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-btn variant="tonal" :disabled="adoptSaving" @click="closeAdoptDialog">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" :loading="adoptSaving" :disabled="!adoptDialogText.trim()" @click="confirmAdopt">Übernehmen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </ItsGridBox>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { parseLocalDate } from '@/helpers/date'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import { useCurriculumStore } from '@/stores/admin/teaching/CurriculumStore'
import axios from 'axios'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsRichTextEditor from '@/components/ItsRichTextEditor.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    props: {
        compactStudentView: {
            type: Boolean,
            default: false,
        },
    },

    components: { ItsGridBox, ItsRichTextEditor },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.courseDateStore = useCourseDateStore()
        this.courseWorkStore = useCourseWorkStore()
        this.teachingStore = useTeachingStore()
        this.curriculumStore = useCurriculumStore()
        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
        }
        await this.loadSelectedCourseCurriculumDetail()
        await this.loadCourseWorks()
        this.activeSemester = this.config?.user?.teaching_active_semester || 1
    },

    mounted() {
        this.scrollToHighlightedDate()
    },

    unmounted() {
        this.courseDateStore.clearDates()
    },

    data() {
        return {
            adminStore: null,
            courseStore: null,
            courseDateStore: null,
            courseWorkStore: null,
            teachingStore: null,
            curriculumStore: null,
            selectedCourseCurriculumDetail: null,
            selectedCourseCurriculumDetailLoadingId: null,
            activeSemester: null,
            is_valid: false,
            delete_date_id: null,
            show_contents: true,
            collapsed_content_ids: [],
            expanded_content_ids: [],
            editing_content_id: null,
            saving_content_id: null,
            pending_date_mutation_action: null,
            pending_date_mutation_id: null,
            content_drafts: {},
            materialOverlayOpen: false,
            materialOverlayEntry: null,
            materialOverlayLoading: false,
            materialOverlayCards: [],
            materialOverlayIsAdopted: false,
            adoptDialogOpen: false,
            adoptDialogCourseDate: null,
            adoptDialogEntry: null,
            adoptDialogText: '',
            adoptDialogSelectedMaterialIds: [],
            adoptDialogMaterialCards: [],
            adoptDialogMaterialLoading: false,
            adoptDialogSelectedAttachmentIdsByMaterial: {},
            adoptSaving: false,
            deletingAdoptedId: null,
            togglingVisibilityId: null,
            dateRangeSelection: ['today'],
            data: {
                from: '',
                until: '',
                interval: 1,
                hours: [],
            },
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useCourseStore, ['selected_course', 'show_dates', 'show_students', 'show_works']),
        ...mapWritableState(useCourseDateStore, ['courseDates', 'selected_courseDate']),
        ...mapWritableState(useCourseWorkStore, ['courseWorks', 'selected_courseWork']),
        selectedCourseClasses() {
            const classes = this.selected_course?.classes
            if (!classes?.length) return ''
            return classes.join(', ')
        },
        selectedCourseSchema() {
            const courseSchema = this.selected_course?.teacher_teaching_schema
            if (courseSchema?.id) {
                return courseSchema
            }

            const schemaId = this.selected_course?.teaching_schema_id
            return schemaId ? this.teachingStore?.schemaById(schemaId) : null
        },
        selectedCourseCurriculumId() {
            const rawCandidates = [
                this.selected_course?.teaching_curriculum_id,
                this.selected_course?.teaching_curriculum?.id,
                this.$route?.query?.curriculum,
            ]

            for (const candidate of rawCandidates) {
                const normalized = Number(candidate)
                if (Number.isFinite(normalized) && normalized > 0) {
                    return normalized
                }
            }

            return null
        },
        selectedCourseCurriculumForContent() {
            const selectedCurriculumId = this.selectedCourseCurriculumId
            if (
                selectedCurriculumId
                && Number(this.selectedCourseCurriculumDetail?.id) === Number(selectedCurriculumId)
            ) {
                return this.selectedCourseCurriculumDetail
            }

            return this.selected_course?.teaching_curriculum || null
        },
        selectedCourseCurriculumTitle() {
            const curriculum = this.selectedCourseCurriculumForContent || this.selected_course?.teaching_curriculum
            if (!curriculum?.id) return ''

            return curriculum.title || `Curriculum #${curriculum.id}`
        },
        semesterCount() {
            const grading = this.selectedCourseSchema?.grading || {}
            return grading?.semester_count || 1
        },
        sem2StartDate() {
            return this.config?.selected_schoolyear?.sem_2_start || this.config?.user?.teaching_count_for_semester_2_date || null
        },
        filteredCourseDates() {
            const dates = this.selected_course?.course_dates || []
            if (this.semesterCount === 1) return dates
            const semester = this.activeSemester
            if (!semester || semester === 3) return dates
            if (!this.sem2StartDate) return dates
            return dates.filter((d) => {
                if (!d.date) return true
                if (semester === 1) return d.date < this.sem2StartDate
                if (semester === 2) return d.date >= this.sem2StartDate
                return true
            })
        },
        displayedCourseDates() {
            const dates = this.filteredCourseDates || []
            return dates
        },
        displayedCourseDatesCount() {
            return this.displayedCourseDates.length
        },
        generatedDates() {
            if (!this.data.from) return []

            const fromDate = parseLocalDate(this.data.from)
            if (isNaN(fromDate.getTime())) return []

            const untilDate = this.data.until ? parseLocalDate(this.data.until) : fromDate

            if (isNaN(untilDate.getTime())) return [fromDate]

            const dates = []
            const intervalDays = (this.data.interval || 1) * 7
            let current = new Date(fromDate)

            while (current <= untilDate) {
                dates.push(new Date(current))
                current.setDate(current.getDate() + intervalDays)
            }

            return dates
        },
        highlightedCourseDate() {
            const dates = [...(this.displayedCourseDates || [])]
                .filter((date) => date?.date)
                .sort((first, second) => String(first.date).localeCompare(String(second.date)))
            if (!dates.length) return null

            const today = new Date()
            today.setHours(0, 0, 0, 0)
            const todayStr = this.toDateString(today)

            const todayDate = dates.find((date) => this.normalizeDateString(date.date) === todayStr)
            if (todayDate) return todayDate

            return dates.find((date) => {
                const dateObj = parseLocalDate(date.date)
                dateObj.setHours(0, 0, 0, 0)
                return dateObj >= today
            }) || null
        },
        highlightedDateId() {
            return this.highlightedCourseDate?.id || null
        },
        isEditingContent() {
            return this.action === 'edit_course_date_content'
        },
        isSavingContent() {
            return this.saving_content_id !== null
        },
        isSavingDateMutation() {
            return this.pending_date_mutation_action !== null
        },
        isBusyDateUi() {
            return this.isSavingContent || this.isSavingDateMutation
        },
        isGridDisabled() {
            return this.isBusyDateUi || (this.action != '' && this.action != 'new_course_dates' && this.action != 'edit_course_date_content')
        },
    },

    watch: {
        show_dates(visible) {
            if (visible) this.scrollToHighlightedDate()
        },
        selected_course: {
            immediate: true,
            async handler(course) {
                if (!course) {
                    this.selectedCourseCurriculumDetail = null
                    this.selectedCourseCurriculumDetailLoadingId = null
                    return
                }

                await this.loadSelectedCourseCurriculumDetail()
                await this.loadCourseWorks()
                this.scrollToHighlightedDate()
                if (this.selected_courseDate) return
                if (this.$route.query.date) return
                const highlightedId = this.highlightedDateId
                if (!highlightedId) return
                const date = (course.course_dates || []).find((d) => d.id === highlightedId)
                if (date) this.selectCourseDate(date)
            },
        },
        selectedCourseCurriculumId: {
            immediate: true,
            async handler() {
                await this.loadSelectedCourseCurriculumDetail()
            },
        },
        activeSemester(val) {
            if (val !== this.config?.user?.teaching_active_semester) {
                this.teachingStore.saveActiveSemester(val)
            }
            this.scrollToHighlightedDate()
        },
        'config.user.teaching_active_semester'(val) {
            if (val) this.activeSemester = val
        },
        dateRangeSelection(val) {
            if (Array.isArray(val) && val.length) return
            this.dateRangeSelection = ['today']
        },
        'data.from'(val) {
            if (val && val instanceof Date) {
                this.data.from = this.toDateString(val)
            }
        },
        'data.until'(val) {
            if (val && val instanceof Date) {
                this.data.until = this.toDateString(val)
            }
        },
    },

    methods: {
        scrollToHighlightedDate() {
            this.$nextTick(() => {
                setTimeout(() => {
                    const ref = this.$refs.highlightedDateItem
                    const el = this.scrollElementFromRef(ref)
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' })
                }, 150)
            })
        },
        scrollElementFromRef(ref) {
            const target = Array.isArray(ref) ? ref[0] : ref
            if (!target) return null

            return target.$el || target
        },
        async loadSelectedCourseCurriculumDetail() {
            const curriculumId = this.selectedCourseCurriculumId
            if (!curriculumId || !this.curriculumStore) {
                this.selectedCourseCurriculumDetail = null
                this.selectedCourseCurriculumDetailLoadingId = null
                return
            }

            if (
                Number(this.selectedCourseCurriculumDetail?.id) === Number(curriculumId)
                && Array.isArray(this.selectedCourseCurriculumDetail?.topics)
            ) {
                return
            }

            if (Number(this.selectedCourseCurriculumDetailLoadingId) === Number(curriculumId)) {
                return
            }

            const inlineCurriculum = this.selected_course?.teaching_curriculum
            if (Number(inlineCurriculum?.id) === Number(curriculumId) && Array.isArray(inlineCurriculum?.topics)) {
                this.selectedCourseCurriculumDetail = inlineCurriculum
                this.selectedCourseCurriculumDetailLoadingId = null
                return
            }

            this.selectedCourseCurriculumDetailLoadingId = curriculumId
            try {
                const curriculum = await this.curriculumStore.show(curriculumId)
                if (Number(this.selectedCourseCurriculumId) === Number(curriculumId)) {
                    this.selectedCourseCurriculumDetail = curriculum
                }
            } finally {
                this.selectedCourseCurriculumDetailLoadingId = null
            }
        },
        weekStartKey(date) {
            if (!date) {
                return null
            }

            const parsedDate = new Date(parseLocalDate(date))
            if (Number.isNaN(parsedDate.getTime())) {
                return null
            }

            const day = parsedDate.getDay()
            const offset = day === 0 ? -6 : 1 - day
            parsedDate.setDate(parsedDate.getDate() + offset)

            return this.toDateString(parsedDate)
        },
        schoolyearWeekKeys() {
            const from = this.config?.selected_schoolyear?.from
            const until = this.config?.selected_schoolyear?.until
            if (!from || !until) {
                return []
            }

            const start = new Date(parseLocalDate(from))
            const end = new Date(parseLocalDate(until))
            if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
                return []
            }

            const cursor = new Date(parseLocalDate(start))
            const day = cursor.getDay()
            const offset = day === 0 ? -6 : 1 - day
            cursor.setDate(cursor.getDate() + offset)

            const keys = []
            while (cursor <= end) {
                keys.push(this.toDateString(cursor))
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
            const cursor = new Date(firstDay)

            const day = cursor.getDay()
            const offset = day === 0 ? -6 : 1 - day
            cursor.setDate(cursor.getDate() + offset)

            const keys = []
            while (cursor <= lastDay || cursor.getDay() !== 1) {
                const weekStart = new Date(cursor)
                let weekHasMonthDay = false

                for (let dayIndex = 0; dayIndex < 7; dayIndex++) {
                    const date = new Date(cursor)
                    if (date.getFullYear() === year && date.getMonth() === monthIndex) {
                        weekHasMonthDay = true
                    }
                    cursor.setDate(cursor.getDate() + 1)
                }

                if (!weekHasMonthDay) {
                    break
                }
                keys.push(this.toDateString(weekStart))
            }

            return keys
        },
        assignmentWeekKeys(assignment, allWeekKeys) {
            if (!assignment) return []

            if (assignment.assignment_type === 'all_weeks') {
                return [...allWeekKeys]
            }

            if (assignment.assignment_type === 'weeks') {
                return [...new Set(
                    (Array.isArray(assignment.week_keys) ? assignment.week_keys : [])
                        .filter(Boolean)
                        .map((weekKey) => String(weekKey).trim())
                )]
            }

            if (assignment.assignment_type === 'month') {
                const monthKeys = (Array.isArray(assignment.month_keys) ? assignment.month_keys : [assignment.month_key])
                    .filter(Boolean)
                    .map((monthKey) => String(monthKey).trim())

                return [...new Set(monthKeys.flatMap((monthKey) => this.weekKeysForMonth(monthKey)))]
            }

            return []
        },
        curriculumEntriesForCourseDate(courseDate) {
            const weekKey = this.weekStartKey(courseDate?.date)
            const curriculum = this.selectedCourseCurriculumForContent
            if (!weekKey || !curriculum) {
                return []
            }

            const freeWeekKeys = [...new Set(
                (Array.isArray(curriculum.free_weeks) ? curriculum.free_weeks : [])
                    .filter(Boolean)
                    .map((entryWeekKey) => String(entryWeekKey).trim())
            )]
            if (freeWeekKeys.includes(weekKey)) {
                return [{ label: 'Frei', hasMaterials: false }]
            }

            const allWeekKeys = this.schoolyearWeekKeys()
            const entries = []
            const seenLabels = new Set()

            ;(Array.isArray(curriculum.topics) ? curriculum.topics : []).forEach((topic) => {
                const topicWeekKeys = this.assignmentWeekKeys(topic, allWeekKeys)
                if (topic?.title && ['all_weeks', 'month', 'weeks'].includes(topic.assignment_type) && topicWeekKeys.includes(weekKey)) {
                    const label = String(topic.title)
                    if (!seenLabels.has(label)) {
                        seenLabels.add(label)
                        entries.push({ label, hasMaterials: Array.isArray(topic.materials) && topic.materials.length > 0, materials: Array.isArray(topic.materials) ? topic.materials : [] })
                    }
                }

                ;(Array.isArray(topic?.units) ? topic.units : []).forEach((unit) => {
                    const unitWeekKeys = unit?.assignment_type === 'none'
                        ? topicWeekKeys
                        : this.assignmentWeekKeys(unit, allWeekKeys)
                    if (!unitWeekKeys.includes(weekKey) || !unit?.title) {
                        return
                    }

                    const topicPrefix = topic?.title ? `${topic.title}: ` : ''
                    const label = `${topicPrefix}${unit.title}`
                    if (!seenLabels.has(label)) {
                        seenLabels.add(label)
                        const unitHasMaterials = Array.isArray(unit.materials) && unit.materials.length > 0
                        const topicHasMaterials = Array.isArray(topic.materials) && topic.materials.length > 0
                        const materials = [
                            ...(Array.isArray(topic.materials) ? topic.materials : []),
                            ...(Array.isArray(unit.materials) ? unit.materials : []),
                        ].filter((m, i, arr) => m?.id && arr.findIndex((x) => x.id === m.id) === i)
                        entries.push({ label, hasMaterials: unitHasMaterials || topicHasMaterials, materials })
                    }
                })
            })

            return entries
        },
        async runDateMutation(action, callback, courseDateId = null) {
            if (this.isBusyDateUi) {
                return false
            }

            this.pending_date_mutation_action = action
            this.pending_date_mutation_id = courseDateId
            await this.$nextTick()

            try {
                return await callback()
            } finally {
                this.pending_date_mutation_action = null
                this.pending_date_mutation_id = null
            }
        },
        isDateMutationPending(action, courseDateId = null) {
            if (this.pending_date_mutation_action !== action) {
                return false
            }

            if (courseDateId === null) {
                return true
            }

            return String(this.pending_date_mutation_id) === String(courseDateId)
        },
        getWeekday(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { weekday: 'short' })
        },
        formatDate(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        async loadCourseWorks() {
            const courseId = this.selected_course?.id
            if (!courseId || !this.courseWorkStore?.index) return

            await this.courseWorkStore.index(courseId)
        },
        contentHtml(text) {
            if (!text) return ''
            if (text.includes('<p>') || text.includes('<br')) return text
            return text
                .split('\n')
                .map((line) => `<p>${line || '<br>'}</p>`)
                .join('')
        },
        courseDateHasContent(courseDate) {
            return !!String(courseDate?.content || '').trim()
        },
        courseDateDisplayHtml(courseDate) {
            const content = String(courseDate?.content || '').trim()
            if (!content) return ''
            const allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code', 'a', 'span', 'div', 'sub', 'sup']
            const tagPattern = allowedTags.map(t => `${t}(\\s[^>]*)?`).join('|')
            const regex = new RegExp(`<(?!\\/?(${tagPattern})\\s*\\/?>)[^>]+>`, 'gi')
            return content.replace(regex, '')
        },
        courseDateInlineContent(courseDate) {
            const content = String(courseDate?.content || '').trim()
            if (!content) {
                return ''
            }

            return content
                .replace(/<br\s*\/?>/gi, ' ')
                .replace(/<\/p>/gi, ' ')
                .replace(/<[^>]+>/g, ' ')
                .replace(/&nbsp;/gi, ' ')
                .replace(/\s+/g, ' ')
                .trim()
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

            if (date instanceof Date) {
                return this.toDateString(date)
            }

            const value = String(date)
            const datePart = value.includes('T') ? value.split('T')[0] : value.slice(0, 10)
            if (/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
                return datePart
            }

            const parsedDate = parseLocalDate(value)
            if (isNaN(parsedDate.getTime())) return ''

            return this.toDateString(parsedDate)
        },
        courseWorksForDate(courseDate) {
            const date = this.normalizeDateString(courseDate?.date)
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
                .map((group) => this.normalizeDateString(group?.date))
                .filter(Boolean)

            if (this.normalizeDateString(work.date_for_all_groups) !== date && !groupDates.includes(date)) {
                return null
            }

            return this.courseWorkAssignmentPayload(work, 'Einzelarbeit')
        },
        groupWorkAssignmentForDate(work, date) {
            const groups = Array.isArray(work.groups) ? work.groups : []
            const matchingGroupIndexes = []
            const fallbackDate = this.normalizeDateString(work.date_for_all_groups)
            const hasExplicitGroupDates = groups.some((group) => this.normalizeDateString(group?.date))

            groups.forEach((group, index) => {
                const groupDate = this.normalizeDateString(group?.date) || fallbackDate
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
                : 'alle Gruppen'

            return this.courseWorkAssignmentPayload(work, suffix, true)
        },
        courseWorkAssignmentPayload(work, suffix, isGroupWork = false) {
            const type = String(work.type || 'Arbeit').trim()
            const title = String(work.title || '').trim()
            const baseLabel = title ? `${type}: ${title}` : type
            const label = suffix ? `${baseLabel} (${suffix})` : baseLabel

            return {
                id: work.id,
                key: `${work.id}-${suffix || 'work'}`,
                label,
                title: label,
                isGroupWork,
            }
        },
        openCourseWork(workAssignment) {
            const work = (this.courseWorks || []).find((courseWork) => String(courseWork.id) === String(workAssignment?.id))
            if (!work) return

            this.selected_courseWork = work
            this.show_works = true
            this.show_dates = false

            const query = {
                ...this.$route.query,
                panel: 'works',
                work: String(work.id),
                return_panel: 'dates',
            }
            this.$router.replace({ query }).catch(() => {})
        },
        setFromDate(dateStr) {
            if (!dateStr) return
            this.data.from = this.toDateString(dateStr)
        },
        setUntilDate(dateStr) {
            if (!dateStr) return
            this.data.until = this.toDateString(dateStr)
        },
        getSem1End() {
            if (!this.config?.selected_schoolyear?.sem_2_start) return null
            const sem2Start = parseLocalDate(this.config.selected_schoolyear.sem_2_start)
            sem2Start.setDate(sem2Start.getDate() - 8)
            return sem2Start
        },
        newDates() {
            this.action = 'new_course_dates'
        },
        abortNewCourseDates() {
            if (this.isBusyDateUi) return
            this.action = ''
        },
        async createDates(data) {
            if (!this.$refs.form.validate()) return

            await this.runDateMutation('create-dates', async () => {
                data.course_id = this.selected_course.id
                await this.courseDateStore.store(data)
                await this.courseStore.index()

                this.data = { from: '', until: '', interval: 1, hours: [] }
                this.action = ''
            })
        },
        async deleteDate(courseDate) {
            await this.runDateMutation('delete-date', async () => {
                await this.courseDateStore.destroy(courseDate.id)
                await this.courseStore.index()
                if (this.selected_courseDate?.id === courseDate.id) {
                    this.selected_courseDate = null
                }
                this.delete_date_id = null
            }, courseDate.id)
        },
        hasStatus(courseDate, status) {
            return Array.isArray(courseDate.status) && courseDate.status.includes(status)
        },
        isDateToday(courseDate) {
            if (!courseDate?.date) return false
            const today = new Date()
            today.setHours(0, 0, 0, 0)
            return courseDate.date === this.toDateString(today)
        },
        courseDateRowClass(courseDate) {
            const classes = []
            if (this.hasStatus(courseDate, 'pruefung')) classes.push('course-date-row--exam')
            if (this.hasStatus(courseDate, 'free')) classes.push('course-date-row--free')
            if (this.hasStatus(courseDate, 'entfaellt')) classes.push('course-date-row--entfaellt')
            if (this.highlightedDateId === courseDate.id) {
                classes.push(this.isDateToday(courseDate) ? 'course-date-row--today' : 'course-date-row--next')
            }
            return classes
        },
        courseDateHighlightStyle(courseDate, index) {
            if (this.selected_courseDate?.id === courseDate.id) {
                return { backgroundColor: '#e8eaf6', borderColor: '#3f51b5' }
            }
            if (this.highlightedDateId === courseDate.id) {
                if (this.isDateToday(courseDate)) {
                    return { backgroundColor: '#bbdefb', borderColor: '#1565c0' }
                }
                return { backgroundColor: '#e3f2fd', borderColor: '#1976d2' }
            }
            if (index % 2 === 1) {
                return { backgroundColor: '#f5f5f5' }
            }
            return {}
        },
        switchToStudents(courseDate) {
            this.selected_courseDate = courseDate
            this.show_students = true
            this.show_dates = false
            const query = { ...this.$route.query, date: String(courseDate.id), panel: 'students' }
            this.$router.replace({ query }).catch(() => {})
        },
        selectCourseDate(courseDate) {
            if (this.isBusyDateUi) return
            if (!courseDate) return
            if (this.selected_courseDate?.id === courseDate.id) {
                this.selected_courseDate = null
                const query = { ...this.$route.query }
                delete query.date
                this.$router.replace({ query }).catch(() => {})
                return
            }
            this.selected_courseDate = courseDate
            const query = { ...this.$route.query, date: String(courseDate.id) }
            this.$router.replace({ query }).catch(() => {})
        },
        async toggleStatus(courseDate, status) {
            const userStatuses = ['pruefung', 'entfaellt']
            if (!userStatuses.includes(status)) return
            const currentStatus = Array.isArray(courseDate.status) ? [...courseDate.status] : []
            const newStatus = currentStatus.filter((item) => userStatuses.includes(item))
            const index = newStatus.indexOf(status)
            if (index === -1) {
                newStatus.push(status)
            } else {
                newStatus.splice(index, 1)
            }
            await this.runDateMutation('toggle-status', async () => {
                await this.courseDateStore.updateStatus(courseDate.id, newStatus)
                await this.courseStore.index()
            }, courseDate.id)
        },
        toggleContents() {
            this.show_contents = !this.show_contents
            if (!this.show_contents) {
                this.editing_content_id = null
            }
            if (this.show_contents) {
                this.expanded_content_ids = []
            } else {
                this.collapsed_content_ids = []
            }
        },
        toggleContentLine(courseDateId) {
            if (this.show_contents) {
                if (this.collapsed_content_ids.includes(courseDateId)) {
                    this.collapsed_content_ids = this.collapsed_content_ids.filter((id) => id !== courseDateId)
                } else {
                    this.collapsed_content_ids = [...this.collapsed_content_ids, courseDateId]
                }
                return
            }
            if (this.expanded_content_ids.includes(courseDateId)) {
                this.expanded_content_ids = this.expanded_content_ids.filter((id) => id !== courseDateId)
            } else {
                this.expanded_content_ids = [...this.expanded_content_ids, courseDateId]
            }
        },
        isContentVisible(courseDateId) {
            if (this.editing_content_id === courseDateId) return true
            if (this.show_contents) {
                return !this.collapsed_content_ids.includes(courseDateId)
            }
            return this.expanded_content_ids.includes(courseDateId)
        },
        startEditContent(courseDate) {
            if (this.isSavingContent) return
            if (this.action && this.action !== 'edit_course_date_content') return
            this.action = 'edit_course_date_content'
            this.editing_content_id = courseDate.id
            this.content_drafts = {
                ...this.content_drafts,
                [courseDate.id]: courseDate.content || '',
            }
            this.$nextTick(() => {
                this.focusContentField(courseDate.id)
            })
        },
        cancelEditContent(courseDate) {
            if (this.isSavingContent) return
            this.editing_content_id = null
            this.content_drafts = {
                ...this.content_drafts,
                [courseDate.id]: courseDate.content || '',
            }
            this.action = ''
        },
        async saveContent(courseDate) {
            if (this.isBusyDateUi) return
            this.saving_content_id = courseDate.id
            await this.$nextTick()

            const content = this.content_drafts[courseDate.id] ?? ''
            const payload = {
                id: courseDate.id,
                date: courseDate.date,
                content,
            }

            try {
                const result = await this.courseDateStore.update(payload)
                if (result) {
                    await this.courseStore.index()
                    this.editing_content_id = null
                    if (this.show_contents) {
                        this.collapsed_content_ids = this.collapsed_content_ids.filter((id) => id !== courseDate.id)
                    } else if (!this.expanded_content_ids.includes(courseDate.id)) {
                        this.expanded_content_ids = [...this.expanded_content_ids, courseDate.id]
                    }
                    this.action = ''
                }
            } finally {
                this.saving_content_id = null
            }
        },
        focusContentField(courseDateId) {
            const ref = this.$refs[`contentField-${courseDateId}`]
            const field = Array.isArray(ref) ? ref[0] : ref
            if (field?.focus) {
                field.focus()
                return
            }
            const el = field?.$el || field
            const prose = el?.querySelector?.('.ProseMirror')
            if (prose) {
                prose.focus()
                return
            }
            const textarea = el?.querySelector?.('textarea')
            if (textarea) textarea.focus()
        },
        async toggleAttachmentVisibility(attachment) {
            if (!attachment?.id || this.togglingVisibilityId) return
            this.togglingVisibilityId = attachment.id
            try {
                const { data } = await axios.post(`/api/admin/teaching/course_date_materials/attachments/${attachment.id}/toggle-visibility`)
                attachment.student_visible = data.student_visible
            } catch {
            } finally {
                this.togglingVisibilityId = null
            }
        },
        async deleteAdoptedMaterial(adopted) {
            if (!adopted?.id || this.deletingAdoptedId) return
            this.deletingAdoptedId = adopted.id
            try {
                await axios.delete(`/api/admin/teaching/course_date_materials/${adopted.id}`)
                await this.courseStore.index()
            } catch {
                // handled by axios interceptor
            } finally {
                this.deletingAdoptedId = null
            }
        },
        async deleteAdoptedMaterialGroup(materialGroup) {
            const adoptedMaterials = Array.isArray(materialGroup?.items) ? materialGroup.items : [materialGroup].filter(Boolean)
            if (!adoptedMaterials.length || this.deletingAdoptedId) return
            if (adoptedMaterials.length === 1) {
                await this.deleteAdoptedMaterial(adoptedMaterials[0])
                return
            }

            this.deletingAdoptedId = materialGroup.id
            try {
                for (const adopted of adoptedMaterials) {
                    if (adopted?.id) {
                        await axios.delete(`/api/admin/teaching/course_date_materials/${adopted.id}`)
                    }
                }
                await this.courseStore.index()
            } catch {
                // handled by axios interceptor
            } finally {
                this.deletingAdoptedId = null
            }
        },
        adoptedAttachmentVisibilityCounts(adopted) {
            const atts = adopted?.attachments || []
            const total = atts.length
            const visible = atts.filter((a) => a.student_visible).length
            return { total, visible }
        },
        adoptedAttachmentVisibilityIcon(adopted) {
            const { total, visible } = this.adoptedAttachmentVisibilityCounts(adopted)
            if (visible === 0) return 'mdi-eye-off'
            if (visible === total) return 'mdi-eye'
            return 'mdi-eye-outline'
        },
        adoptedAttachmentVisibilityColor(adopted) {
            const { total, visible } = this.adoptedAttachmentVisibilityCounts(adopted)
            if (visible === 0) return 'grey'
            if (visible === total) return 'success'
            return 'warning'
        },
        adoptedAttachmentVisibilityTitle(adopted) {
            const { total, visible } = this.adoptedAttachmentVisibilityCounts(adopted)
            if (visible === 0) return `Keine Anhänge freigegeben (${total})`
            if (visible === total) return `Alle ${total} Anhänge freigegeben`
            return `${visible} von ${total} Anhängen freigegeben`
        },
        courseDateAdoptedMaterials(courseDate) {
            return Array.isArray(courseDate?.adopted_materials) ? courseDate.adopted_materials : []
        },
        adoptedMaterialDisplayTitle(adopted) {
            return adopted?.material_title
                || [adopted?.subject, adopted?.area, adopted?.unit].filter(Boolean).join(' - ')
                || adopted?.unit
                || adopted?.type
                || adopted?.title
                || 'Material'
        },
        courseDateAdoptedMaterialGroups(courseDate) {
            const groups = []
            const groupMap = new Map()

            this.courseDateAdoptedMaterials(courseDate).forEach((adopted, adoptedIndex) => {
                const curriculumTitle = adopted?.title || 'Übernommen'
                const curriculumKey = curriculumTitle || `curriculum-${adoptedIndex}`
                let group = groupMap.get(curriculumKey)
                if (!group) {
                    group = {
                        key: String(curriculumKey),
                        title: curriculumTitle,
                        materials: [],
                        materialMap: new Map(),
                    }
                    groupMap.set(curriculumKey, group)
                    groups.push(group)
                }

                const materialTitle = this.adoptedMaterialDisplayTitle(adopted)
                const materialKey = adopted?.source_material_card_id
                    ? `source-${adopted.source_material_card_id}`
                    : `adopted-${adopted?.id || adoptedIndex}`
                let material = group.materialMap.get(materialKey)
                if (!material) {
                    material = {
                        id: materialKey,
                        title: materialTitle,
                        curriculum_title: curriculumTitle,
                        type: adopted?.type,
                        items: [],
                        attachments: [],
                    }
                    group.materialMap.set(materialKey, material)
                    group.materials.push(material)
                }

                material.items.push(adopted)
                material.attachments.push(...(Array.isArray(adopted?.attachments) ? adopted.attachments : []))
            })

            return groups.map((group) => ({
                key: group.key,
                title: group.title,
                duplicateSingleMaterial: group.materials.length === 1 && group.materials[0].title === group.title,
                materials: group.materials,
            }))
        },
        isCurriculumEntryFullyAdopted(courseDate, entry) {
            const adopted = this.courseDateAdoptedMaterials(courseDate)
            if (!adopted.some((a) => a.title === entry.label)) return false
            const entryMaterials = (entry.materials || []).filter((m) => m?.id)
            if (!entryMaterials.length) return true
            return entryMaterials.every((material) => this.isMaterialFullyAdopted(courseDate, material))
        },
        adoptedSourceMaterialIds(courseDate) {
            return new Set(this.courseDateAdoptedMaterials(courseDate)
                .map((a) => Number(a.source_material_card_id))
                .filter((id) => Number.isFinite(id) && id > 0))
        },
        adoptedSourceAttachmentIds(courseDate, materialId = null) {
            const normalizedMaterialId = materialId === null || materialId === undefined ? null : Number(materialId)

            return new Set(this.courseDateAdoptedMaterials(courseDate)
                .filter((material) => normalizedMaterialId === null || Number(material?.source_material_card_id) === normalizedMaterialId)
                .flatMap((material) => Array.isArray(material?.attachments) ? material.attachments : [])
                .map((attachment) => Number(attachment?.source_material_card_attachment_id))
                .filter((id) => Number.isFinite(id) && id > 0))
        },
        adoptedAttachmentCountForSource(courseDate, materialId) {
            const normalizedMaterialId = Number(materialId)

            return this.courseDateAdoptedMaterials(courseDate)
                .filter((material) => Number(material?.source_material_card_id) === normalizedMaterialId)
                .reduce((count, material) => count + (Array.isArray(material?.attachments) ? material.attachments.length : 0), 0)
        },
        requiredAttachmentIdsForMaterial(material) {
            const card = this.adoptDialogMaterialCard?.(material?.id) || material
            if (Array.isArray(card?.attachments)) {
                return this.adoptableAttachmentIds(card).map((id) => Number(id))
            }

            const expectedAttachmentCount = Number(material?.attachments_count || 0)
            return expectedAttachmentCount > 0 ? null : []
        },
        missingAdoptableAttachmentIds(courseDate, material) {
            const requiredAttachmentIds = this.requiredAttachmentIdsForMaterial(material)
            if (!Array.isArray(requiredAttachmentIds)) return null

            const adoptedAttachmentIds = this.adoptedSourceAttachmentIds(courseDate, material?.id)
            return requiredAttachmentIds.filter((id) => !adoptedAttachmentIds.has(Number(id)))
        },
        isMaterialFullyAdopted(courseDate, material) {
            const materialId = Number(material?.id)
            if (!Number.isFinite(materialId) || materialId <= 0) return false
            if (!this.adoptedSourceMaterialIds(courseDate).has(materialId)) return false

            const missingAttachmentIds = this.missingAdoptableAttachmentIds(courseDate, material)
            if (Array.isArray(missingAttachmentIds)) return missingAttachmentIds.length === 0

            const expectedAttachmentCount = Number(material?.attachments_count || 0)
            if (!Number.isFinite(expectedAttachmentCount) || expectedAttachmentCount <= 0) return true

            const knownAdoptedAttachmentCount = this.adoptedSourceAttachmentIds(courseDate, materialId).size
            if (knownAdoptedAttachmentCount > 0) return knownAdoptedAttachmentCount >= expectedAttachmentCount

            return this.adoptedAttachmentCountForSource(courseDate, materialId) >= expectedAttachmentCount
        },
        openAdoptedMaterialOverview(adopted) {
            if (!adopted?.attachments?.length) return
            this.materialOverlayEntry = { label: adopted.curriculum_title || adopted.title }
            this.materialOverlayCards = [adopted]
            this.materialOverlayLoading = false
            this.materialOverlayIsAdopted = true
            this.materialOverlayOpen = true
        },
        async openAdoptDialog(courseDate, entry) {
            this.adoptDialogCourseDate = courseDate
            this.adoptDialogEntry = entry
            this.adoptDialogText = entry.label || ''
            this.adoptDialogMaterialCards = []
            this.adoptDialogSelectedAttachmentIdsByMaterial = {}
            this.adoptDialogSelectedMaterialIds = (entry.materials || [])
                .filter((material) => material?.id && !this.isMaterialFullyAdopted(courseDate, material))
                .map((material) => material.id)
            this.adoptDialogOpen = true
            await this.loadAdoptDialogMaterialCards()
        },
        closeAdoptDialog() {
            this.adoptDialogOpen = false
            this.adoptDialogCourseDate = null
            this.adoptDialogEntry = null
            this.adoptDialogText = ''
            this.adoptDialogSelectedMaterialIds = []
            this.adoptDialogMaterialCards = []
            this.adoptDialogMaterialLoading = false
            this.adoptDialogSelectedAttachmentIdsByMaterial = {}
        },
        async loadAdoptDialogMaterialCards() {
            const materials = Array.isArray(this.adoptDialogEntry?.materials) ? this.adoptDialogEntry.materials : []
            const curriculumId = this.selectedCourseCurriculumId
            if (!materials.length || !curriculumId) return

            this.adoptDialogMaterialLoading = true
            try {
                const cards = []
                for (const material of materials) {
                    try {
                        const res = await axios.get(`/api/admin/teaching/curricula/${curriculumId}/materials/cards/${material.id}`)
                        const card = res.data?.data
                        if (card) {
                            cards.push(card)
                        }
                    } catch {
                        cards.push({ ...material })
                    }
                }
                this.adoptDialogMaterialCards = cards
                materials.forEach((material) => {
                    const detailedMaterial = this.adoptDialogMaterialCard(material.id) || material
                    const missingAttachmentIds = this.missingAdoptableAttachmentIds(this.adoptDialogCourseDate, detailedMaterial)
                    this.adoptDialogSelectedAttachmentIdsByMaterial[material.id] = Array.isArray(missingAttachmentIds)
                        ? missingAttachmentIds
                        : this.adoptableAttachmentIds(detailedMaterial)
                })
                this.adoptDialogSelectedMaterialIds = materials
                    .filter((material) => material?.id && !this.isMaterialFullyAdopted(this.adoptDialogCourseDate, this.adoptDialogMaterialCard(material.id) || material))
                    .map((material) => material.id)
            } finally {
                this.adoptDialogMaterialLoading = false
            }
        },
        adoptDialogMaterialCard(materialId) {
            return this.adoptDialogMaterialCards.find((card) => Number(card?.id) === Number(materialId)) || null
        },
        adoptDialogMaterialAttachments(material) {
            const card = this.adoptDialogMaterialCard(material?.id)

            return (Array.isArray(card?.attachments) ? card.attachments : [])
                .filter((attachment) => attachment?.attachment_type === 'file')
        },
        adoptableAttachmentIds(material) {
            return (Array.isArray(material?.attachments) ? material.attachments : [])
                .filter((attachment) => attachment?.attachment_type === 'file' && attachment?.id)
                .map((attachment) => attachment.id)
        },
        isAdoptDialogMaterialSelected(materialId) {
            return this.adoptDialogSelectedMaterialIds.some((id) => Number(id) === Number(materialId))
        },
        isAdoptDialogMaterialFullyAdopted(material) {
            return this.isMaterialFullyAdopted(this.adoptDialogCourseDate, this.adoptDialogMaterialCard(material?.id) || material)
        },
        async confirmAdopt() {
            const courseDate = this.adoptDialogCourseDate
            const entry = this.adoptDialogEntry
            const text = this.adoptDialogText.trim()
            if (!courseDate?.id || !text) return

            this.adoptSaving = true
            try {
                const materialCardIds = this.adoptDialogSelectedMaterialIds
                    .filter((id) => id && Number(id) > 0)
                const materialAttachmentIds = materialCardIds.reduce((selectedAttachments, materialId) => {
                    if (Object.prototype.hasOwnProperty.call(this.adoptDialogSelectedAttachmentIdsByMaterial, materialId)) {
                        selectedAttachments[materialId] = (this.adoptDialogSelectedAttachmentIdsByMaterial[materialId] || [])
                            .filter((id) => id && Number(id) > 0)
                    }

                    return selectedAttachments
                }, {})

                await axios.post(`/api/admin/teaching/course_dates/${courseDate.id}/adopt-curriculum-content`, {
                    content: text,
                    material_card_ids: materialCardIds,
                    material_attachment_ids: materialAttachmentIds,
                })
                await this.courseStore.index()
                this.closeAdoptDialog()
            } catch {
                // error is handled by axios interceptor
            } finally {
                this.adoptSaving = false
            }
        },
        async openMaterialOverview(entry) {
            if (!entry?.materials?.length) return
            this.materialOverlayEntry = entry
            this.materialOverlayCards = []
            this.materialOverlayLoading = true
            this.materialOverlayOpen = true

            const curriculumId = this.selectedCourseCurriculumId
            if (!curriculumId) {
                this.materialOverlayCards = entry.materials.map((m) => ({ ...m, attachments: [] }))
                this.materialOverlayLoading = false
                return
            }

            const cards = []
            for (const material of entry.materials) {
                try {
                    const res = await axios.get(`/api/admin/teaching/curricula/${curriculumId}/materials/cards/${material.id}`)
                    const card = res.data?.data
                    if (card) {
                        cards.push(card)
                    }
                } catch {
                    cards.push({ ...material, attachments: [] })
                }
            }

            this.materialOverlayCards = cards
            this.materialOverlayLoading = false
        },
        closeMaterialOverview() {
            this.materialOverlayOpen = false
            this.materialOverlayEntry = null
            this.materialOverlayCards = []
            this.materialOverlayLoading = false
            this.materialOverlayIsAdopted = false
        },
        materialSubtitle(card) {
            return [card.subject, card.topic, card.unit, card.type]
                .map((v) => (typeof v === 'string' ? v.trim() : ''))
                .filter(Boolean)
                .join(' · ')
        },
        materialStatusDisplay(status) {
            const map = {
                inbox: { label: 'Neu/Idee', color: '#607d8b' },
                in_progress: { label: 'In Arbeit', color: '#f9a825' },
                done: { label: 'ok', color: '#2e7d32' },
                update_needed: { label: 'Änderung nötig', color: '#c62828' },
            }
            return map[status] || null
        },
        attachmentIcon(attachment) {
            const mimeType = String(attachment?.mime_type || '').toLowerCase()
            return mimeType.startsWith('image/') ? 'mdi-file-image-outline' : 'mdi-file-document-outline'
        },
        formatFileSize(bytes) {
            const size = Number(bytes)
            if (!Number.isFinite(size) || size <= 0) return ''
            if (size < 1024) return `${size} B`
            const units = ['KB', 'MB', 'GB']
            let value = size / 1024
            let unitIndex = 0
            while (value >= 1024 && unitIndex < units.length - 1) {
                value /= 1024
                unitIndex += 1
            }
            return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[unitIndex]}`
        },
    },
}
</script>

<style scoped>
.course-dates-title-row {
    min-width: 0;
}

.course-dates-grid {
    padding: 8px;
    gap: 8px;
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    align-items: stretch;
}

.v-list-item {
    background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
    border: 1px solid rgba(37, 99, 235, 0.12);
    border-radius: 12px;
    transition: box-shadow 0.15s ease, border-color 0.15s ease;
    display: flex;
    align-items: flex-start;
    min-width: 0;
    width: 100%;
}

.v-list-item :deep(.v-list-item__content) {
    width: 100%;
    height: 100%;
}

.v-list-item:hover {
    border-color: rgba(37, 99, 235, 0.28);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
}

.content-readonly :deep(textarea),
.content-readonly :deep(.v-field__input) {
    pointer-events: none;
    cursor: default;
}

.content-readonly,
.content-readonly :deep(p),
.content-readonly :deep(li),
.content-readonly :deep(span) {
    font-weight: 400;
}

.course-date-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
}

.course-date-title {
    font-size: 0.85rem;
    font-weight: 700;
    line-height: 1.3;
    color: #1e293b;
}

@media (min-width: 600px) {
    .course-date-title {
        font-size: 1rem;
    }
}

.course-date-icon {
    flex: 0 0 auto;
}

.course-date-inline-content {
    flex: 1 1 240px;
    min-width: 0;
    overflow-wrap: anywhere;
}

.course-date-header {
    min-width: 0;
}

.course-date-left {
    flex: 1 1 auto;
    max-width: 100%;
    min-width: 0;
}

.course-date-free-reason {
    min-width: 0;
}

.course-date-work-chip {
    flex: 0 1 auto;
    font-weight: 600;
    height: auto;
    max-width: 100%;
    min-height: 22px;
    min-width: 0;
}

.course-date-work-chip :deep(.v-chip__content) {
    line-height: 1.25;
    overflow: hidden;
    padding-bottom: 1px;
    padding-top: 1px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.course-date-actions {
    margin-left: auto;
    align-self: flex-start;
    flex: 0 0 auto;
}

.course-date-curriculum-chip {
    font-weight: 500;
    height: auto;
    max-width: min(100%, 360px);
    min-height: 26px;
    white-space: normal;
}

.course-date-curriculum-chip :deep(.v-chip__content) {
    line-height: 1.3;
    padding-bottom: 2px;
    padding-top: 2px;
    white-space: normal;
    overflow-wrap: anywhere;
}

.course-date-curriculum-inline {
    margin-top: -2px;
}

.course-date-curriculum-divider {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 4px 0 2px;
}

.course-date-curriculum-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(var(--v-theme-primary), 0.25);
}

.course-date-curriculum-divider__label {
    font-size: 0.6rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    color: rgba(var(--v-theme-primary), 0.5);
    line-height: 1;
    flex-shrink: 0;
}

.course-date-curriculum-stack {
    display: flex;
    flex-direction: column;
    gap: 2px;
    max-width: 100%;
}

.course-date-curriculum-stack__content {
    color: rgba(var(--v-theme-on-surface), 0.85);
    font-size: 0.88rem;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.course-date-curriculum-stack__content :deep(p) {
    margin: 0 0 0.25em;
}

.course-date-curriculum-stack__content :deep(ul),
.course-date-curriculum-stack__content :deep(ol) {
    margin: 0 0 0.25em;
    padding-left: 1.5em;
}

.course-date-curriculum-stack__content :deep(h1),
.course-date-curriculum-stack__content :deep(h2),
.course-date-curriculum-stack__content :deep(h3),
.course-date-curriculum-stack__content :deep(h4) {
    font-size: 0.92rem;
    font-weight: 700;
    margin: 0 0 0.2em;
}

.course-date-curriculum-stack__content :deep(blockquote) {
    border-left: 3px solid rgba(var(--v-theme-primary), 0.3);
    padding-left: 8px;
    margin: 0.25em 0;
}

.course-date-curriculum-stack__adopted-group {
    border-left: 2px solid rgba(var(--v-theme-success), 0.4);
    color: rgba(var(--v-theme-on-surface), 0.85);
    font-size: 0.76rem;
    line-height: 1.25;
    padding-left: 8px;
}

.course-date-curriculum-stack__adopted-title {
    font-weight: 600;
    overflow-wrap: anywhere;
}

.course-date-curriculum-stack__adopted-material {
    margin-top: 2px;
    padding-left: 18px;
}

.course-date-curriculum-stack__adopted-attachments {
    display: flex;
    flex-direction: column;
    gap: 1px;
    margin-top: 2px;
    padding-left: 18px;
}

.course-date-curriculum-stack__adopted-attachment {
    cursor: pointer;
    font-size: 0.72rem;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.course-date-curriculum-stack__entry {
    border-left: 2px solid rgba(var(--v-theme-primary), 0.35);
    color: rgb(var(--v-theme-primary));
    font-size: 0.76rem;
    line-height: 1.25;
    padding-left: 8px;
}

@media (max-width: 599px) {
    .course-date-header {
        flex-wrap: wrap;
    }

    .course-date-actions {
        flex-basis: 100%;
        margin-left: 0;
    }

    .course-date-curriculum-chip {
        flex: 1 1 100%;
        margin-top: 4px;
    }
}

.course-date-row--exam {
    background: linear-gradient(180deg, #fff3e0 0%, #ffe0b2 100%) !important;
    border-left: 4px solid #ff9800;
}

.course-date-row--free {
    background: linear-gradient(180deg, #e8f5e9 0%, #c8e6c9 100%) !important;
    border-left: 4px solid #4caf50;
}

.course-date-row--entfaellt {
    background: linear-gradient(180deg, #e8f5e9 0%, #c8e6c9 100%) !important;
    border-left: 4px solid #4caf50;
}

.course-date-row--today {
    background: linear-gradient(180deg, #bbdefb 0%, #90caf9 100%) !important;
    border: 2px solid #1565c0 !important;
    box-shadow: 0 0 12px rgba(21, 101, 192, 0.35);
}

.course-date-row--next {
    background: linear-gradient(180deg, #c5cae9 0%, #9fa8da 100%) !important;
    border: 2px solid #3f51b5 !important;
    box-shadow: 0 0 12px rgba(63, 81, 181, 0.35);
}

.course-date-material-icon {
    opacity: 0.7;
    transition: opacity 0.15s, transform 0.15s;
}

.course-date-material-icon:hover {
    opacity: 1;
    transform: scale(1.2);
}

.material-overview-card {
    border: 1px solid rgba(var(--v-theme-primary), 0.15);
    border-radius: 12px;
    padding: 12px;
}

.course-date-adopt-btn {
    opacity: 0.4;
    transition: opacity 0.15s;
    flex-shrink: 0;
}

.course-date-curriculum-stack__entry:hover .course-date-adopt-btn {
    opacity: 1;
}

.material-overview-attachment {
    border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
    border-radius: 8px;
}
</style>
