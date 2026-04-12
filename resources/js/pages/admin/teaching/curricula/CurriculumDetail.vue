<template>
    <div class="curriculum-detail">
        <div class="curriculum-detail__header mb-4">
            <v-btn
                variant="tonal"
                color="secondary"
                size="small"
                rounded="xl"
                prepend-icon="mdi-arrow-left"
                class="text-none mb-3"
                :disabled="isPageActionLocked"
                @click="$emit('back')">
                Zurück zur Übersicht
            </v-btn>
            <div class="curriculum-detail__title-row">
                <div>
                    <h2 class="curriculum-detail__title">{{ curriculum.title }}</h2>
                    <p v-if="curriculum.description" class="curriculum-detail__desc">{{ curriculum.description }}</p>
                </div>
                <div class="curriculum-detail__meta d-flex align-center ga-2">
                    <v-chip size="small" color="primary" variant="tonal" class="font-weight-bold">
                        {{ curriculum.semester_count ?? 2 }} Semester
                    </v-chip>
                    <v-chip size="small" color="warning" variant="tonal" class="font-weight-bold">
                        {{ freeWeeksCount }} freie Wochen
                    </v-chip>
                    <div class="curriculum-detail__year-picker d-flex align-center ga-1">
                        <v-btn
                            icon="mdi-chevron-left"
                            variant="tonal"
                            color="secondary"
                            size="x-small"
                            :disabled="isPageActionLocked"
                            @click="selectedYear--" />
                        <v-chip
                            size="small"
                            variant="tonal"
                            color="primary"
                            class="font-weight-bold px-3">
                            {{ selectedYear }}/{{ selectedYear + 1 }}
                        </v-chip>
                        <v-btn
                            icon="mdi-chevron-right"
                            variant="tonal"
                            color="secondary"
                            size="x-small"
                            :disabled="isPageActionLocked"
                            @click="selectedYear++" />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="(curriculum.semester_count ?? 2) === 1" class="curriculum-detail__semester-picker mb-4">
            <v-sheet rounded="xl" class="curriculum-detail__picker-sheet pa-3">
                <div class="text-body-2 font-weight-medium mb-2" style="color: #cbd5e1">
                    Welches Semester anzeigen?
                </div>
                <v-btn-toggle
                    v-model="selectedHalf"
                    mandatory
                    color="primary"
                    density="comfortable"
                    rounded="lg"
                    :disabled="isPageActionLocked"
                    class="semester-toggle">
                    <v-btn value="first" variant="outlined" class="text-none px-5 semester-toggle__btn">
                        <v-icon size="16" class="mr-1">mdi-weather-snowy</v-icon>
                        Wintersemester (Sep – Feb)
                    </v-btn>
                    <v-btn value="second" variant="outlined" class="text-none px-5 semester-toggle__btn">
                        <v-icon size="16" class="mr-1">mdi-white-balance-sunny</v-icon>
                        Sommersemester (Feb – Jul)
                    </v-btn>
                </v-btn-toggle>
            </v-sheet>
        </div>

        <div class="curriculum-detail__body">
            <div ref="calendarCol" class="curriculum-detail__calendar">
                <v-alert
                    type="info"
                    variant="tonal"
                    density="compact"
                    rounded="xl"
                    class="curriculum-detail__free-week-hint">
                    {{ calendarHint }}
                </v-alert>
                <div
                    v-for="(month, idx) in visibleMonths"
                    :key="month.key"
                    class="curriculum-detail__month"
                    :style="{ '--month-hue': monthHue(idx) }">
                    <div class="curriculum-detail__month-header">
                        <div class="curriculum-detail__month-name">{{ month.name }}</div>
                        <div class="curriculum-detail__month-year">{{ month.year }}</div>
                    </div>
                    <div class="curriculum-detail__weeks">
                        <div
                            v-for="(week, wIdx) in month.weeks"
                            :key="wIdx"
                            class="curriculum-detail__week"
                            :class="{
                                'curriculum-detail__week--current': week.isCurrent,
                                'curriculum-detail__week--free': isFreeWeek(week.weekKey),
                                'curriculum-detail__week--topic-selectable': isWeekSelectionActive,
                                'curriculum-detail__week--topic-selected': isWeekAssignedToActiveTopic(week.weekKey),
                            }"
                            @click="handleWeekClick(week.weekKey)">
                            <div class="curriculum-detail__week-number">
                                <span class="curriculum-detail__week-kw">KW</span>
                                <span class="curriculum-detail__week-num">{{ week.kw }}</span>
                            </div>
                            <div class="curriculum-detail__week-days">
                                <div
                                    v-for="day in week.days"
                                    :key="day.date"
                                    class="curriculum-detail__day"
                                    :class="{
                                        'curriculum-detail__day--today': day.isToday,
                                        'curriculum-detail__day--outside': day.outsideMonth,
                                    }">
                                    <span class="curriculum-detail__day-name">{{ day.dayName }}</span>
                                    <span class="curriculum-detail__day-num">{{ day.dayNum }}</span>
                                </div>
                            </div>
                            <div class="curriculum-detail__week-range">
                                {{ week.rangeLabel }}
                            </div>
                            <div class="curriculum-detail__week-actions">
                                <v-btn
                                    v-if="isWeekSelectionActive"
                                    :icon="isWeekAssignedToActiveTopic(week.weekKey) ? 'mdi-check-circle' : 'mdi-circle-outline'"
                                    variant="text"
                                    color="primary"
                                    size="x-small"
                                    :disabled="topicSaving || isEditingTopic"
                                    :title="isWeekAssignedToActiveTopic(week.weekKey) ? 'Woche vom Thema entfernen' : 'Woche dem Thema zuordnen'"
                                    @click.stop="handleWeekClick(week.weekKey)" />
                                <v-chip
                                    v-if="isFreeWeek(week.weekKey)"
                                    size="x-small"
                                    color="warning"
                                    variant="flat"
                                    class="curriculum-detail__week-chip">
                                    frei
                                </v-chip>
                                <v-btn
                                    :icon="isFreeWeek(week.weekKey) ? 'mdi-calendar-remove-outline' : 'mdi-calendar-plus-outline'"
                                    variant="tonal"
                                    color="warning"
                                    size="x-small"
                                    :disabled="isPageActionLocked"
                                    :loading="isWeekSaving(week.weekKey)"
                                    :title="isFreeWeek(week.weekKey) ? 'Freie Woche entfernen' : 'Woche als frei markieren'"
                                    @click.stop="toggleFreeWeek(week.weekKey)" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="curriculum-detail__side-card curriculum-detail__side-card--content" :style="{ minWidth: Math.round(calendarWidth * 1.5) + 'px' }">
                <div class="curriculum-detail__side-card-inner">
                    <div class="curriculum-detail__side-card-header">
                        <v-icon size="20" color="#a5b4fc" class="mr-2">mdi-text-box-outline</v-icon>
                        Inhalte
                    </div>
                    <div class="curriculum-detail__side-card-body">
                        <div class="curriculum-detail__content-toolbar">
                            <div>
                                <div class="curriculum-detail__content-count">{{ curriculumTopics.length }} Themen</div>
                                <div class="curriculum-detail__content-hint">
                                    Themen können ohne Zuordnung, für das ganze Jahr, einzelne Monate oder Wochen geplant werden.
                                </div>
                            </div>
                            <v-btn
                                variant="flat"
                                color="primary"
                                size="small"
                                rounded="lg"
                                prepend-icon="mdi-plus"
                                class="text-none curriculum-detail__content-add-btn"
                                :disabled="isPageActionLocked"
                                @click="openTopicForm()">
                                Thema
                            </v-btn>
                        </div>

                        <div v-if="showTopicForm" class="curriculum-detail__topic-form mt-4">
                            <v-text-field
                                v-model="topicForm.title"
                                label="Thema"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-3" />
                            <div v-if="topicFormError" class="curriculum-detail__topic-form-error mb-3">
                                {{ topicFormError }}
                            </div>
                            <div class="curriculum-detail__topic-form-actions">
                                <v-btn
                                    variant="flat"
                                    color="primary"
                                    size="small"
                                    rounded="lg"
                                    class="text-none curriculum-detail__topic-save-btn"
                                    :loading="topicSaving"
                                    @click="saveTopic">
                                    {{ topicForm.id ? 'Thema speichern' : 'Thema anlegen' }}
                                </v-btn>
                                <v-btn
                                    variant="text"
                                    color="secondary"
                                    size="small"
                                    class="text-none"
                                    :disabled="topicSaving"
                                    @click="cancelTopicForm">
                                    Abbrechen
                                </v-btn>
                            </div>
                        </div>

                        <div v-if="curriculumTopics.length" class="curriculum-detail__topic-list mt-4">
                            <div
                                v-for="topic in curriculumTopics"
                                :key="topic.id"
                                class="curriculum-detail__topic-item">
                                <div class="curriculum-detail__topic-row">
                                    <div class="curriculum-detail__topic-main">
                                        <div class="curriculum-detail__topic-title-row">
                                            <div class="curriculum-detail__topic-title">{{ topic.title }}</div>
                                            <v-chip
                                                size="x-small"
                                                color="primary"
                                                variant="tonal"
                                                class="curriculum-detail__topic-summary-chip">
                                                {{ topicAssignmentSummary(topic) }}
                                            </v-chip>
                                        </div>
                                        <div
                                            v-if="topic.assignment_type === 'month' && topic.month_keys.length"
                                            class="curriculum-detail__topic-meta-chips">
                                            <v-chip
                                                v-for="monthKey in topic.month_keys"
                                                :key="monthKey"
                                                size="x-small"
                                                color="primary"
                                                variant="flat">
                                                {{ monthChipLabel(monthKey) }}
                                            </v-chip>
                                        </div>
                                        <div
                                            v-else-if="topic.assignment_type === 'weeks' && topic.week_keys.length"
                                            class="curriculum-detail__topic-meta-chips">
                                            <v-chip
                                                v-for="weekKey in topic.week_keys"
                                                :key="weekKey"
                                                size="x-small"
                                                color="primary"
                                                variant="flat">
                                                {{ weekChipLabel(weekKey) }}
                                            </v-chip>
                                        </div>
                                    </div>
                                    <div class="curriculum-detail__topic-actions">
                                        <v-btn
                                            icon="mdi-calendar-range-outline"
                                            variant="text"
                                            color="primary"
                                            size="x-small"
                                            :disabled="topicSaving || isEditingTopic || (isEditingTopicDates && !isTopicAssignmentEditorOpen(topic.id))"
                                            :title="isTopicAssignmentEditorOpen(topic.id) ? 'Datumszuordnung schließen' : 'Datumszuordnung bearbeiten'"
                                            @click="toggleTopicAssignmentEditor(topic)" />
                                        <v-btn
                                            icon="mdi-pencil-outline"
                                            variant="text"
                                            color="primary"
                                            size="x-small"
                                            :disabled="topicSaving || isPageActionLocked"
                                            title="Thema bearbeiten"
                                            @click="openTopicForm(topic)" />
                                        <v-btn
                                            icon="mdi-delete-outline"
                                            variant="text"
                                            color="error"
                                            size="x-small"
                                            :disabled="topicSaving || isPageActionLocked"
                                            title="Thema löschen"
                                            @click="promptDeleteTopic(topic)" />
                                    </div>
                                </div>

                                <div
                                    v-if="isTopicAssignmentEditorOpen(topic.id)"
                                    class="curriculum-detail__topic-assignment-panel">
                                    <div class="curriculum-detail__topic-assignment-options">
                                        <v-btn
                                            variant="tonal"
                                            size="x-small"
                                            rounded="lg"
                                            class="text-none"
                                            :color="activeTopicAssignmentType === 'none' ? 'primary' : 'secondary'"
                                            :disabled="topicSaving || isEditingTopic"
                                            @click="activateTopicAssignmentMode(topic, 'none')">
                                            Keine Zuordnung
                                        </v-btn>
                                        <v-btn
                                            variant="tonal"
                                            size="x-small"
                                            rounded="lg"
                                            class="text-none"
                                            :color="activeTopicAssignmentType === 'all_weeks' ? 'primary' : 'secondary'"
                                            :disabled="topicSaving || isEditingTopic"
                                            @click="activateTopicAssignmentMode(topic, 'all_weeks')">
                                            {{ curriculumScopeLabel }}
                                        </v-btn>
                                        <v-btn
                                            variant="tonal"
                                            size="x-small"
                                            rounded="lg"
                                            class="text-none"
                                            :color="activeTopicAssignmentType === 'month' ? 'primary' : 'secondary'"
                                            :disabled="topicSaving || isEditingTopic"
                                            @click="activateTopicAssignmentMode(topic, 'month')">
                                            Monate
                                        </v-btn>
                                        <v-btn
                                            variant="tonal"
                                            size="x-small"
                                            rounded="lg"
                                            class="text-none"
                                            :color="activeTopicAssignmentType === 'weeks' ? 'primary' : 'secondary'"
                                            :disabled="topicSaving || isEditingTopic"
                                            @click="activateTopicAssignmentMode(topic, 'weeks')">
                                            Wochen
                                        </v-btn>
                                        <v-btn
                                            variant="text"
                                            size="x-small"
                                            color="secondary"
                                            class="text-none ml-auto"
                                            :disabled="topicSaving || isEditingTopic"
                                            @click="closeTopicAssignmentEditor">
                                            Schließen
                                        </v-btn>
                                    </div>

                                    <div
                                        v-if="activeTopicAssignmentType === 'month'"
                                        class="curriculum-detail__topic-assignment-months">
                                        <v-chip
                                            v-for="month in assignableMonths"
                                            :key="month.assignmentKey"
                                            size="small"
                                            :color="topic.month_keys.includes(month.assignmentKey) ? 'primary' : 'secondary'"
                                            :variant="topic.month_keys.includes(month.assignmentKey) ? 'flat' : 'outlined'"
                                            class="curriculum-detail__assignment-chip"
                                            @click="toggleTopicMonthAssignment(topic, month.assignmentKey)">
                                            {{ month.name }}
                                        </v-chip>
                                    </div>

                                    <div
                                        v-else-if="activeTopicAssignmentType === 'weeks'"
                                        class="curriculum-detail__topic-assignment-weeks">
                                        <div class="curriculum-detail__topic-assignment-hint">
                                            Wochen links im Kalender anklicken, um sie diesem Thema zuzuordnen.
                                        </div>
                                        <div
                                            v-if="topic.week_keys.length"
                                            class="curriculum-detail__topic-meta-chips">
                                            <v-chip
                                                v-for="weekKey in topic.week_keys"
                                                :key="weekKey"
                                                size="x-small"
                                                color="primary"
                                                variant="flat">
                                                {{ weekChipLabel(weekKey) }}
                                            </v-chip>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="curriculum-detail__topic-empty mt-4">
                            Noch keine Themen definiert.
                        </div>
                    </div>
                </div>
            </div>

            <div class="curriculum-detail__side-card">
                <div class="curriculum-detail__side-card-inner">
                    <div class="curriculum-detail__side-card-header">
                        <v-icon size="20" color="#a5b4fc" class="mr-2">mdi-book-open-page-variant-outline</v-icon>
                        Lehrpläne
                    </div>
                    <div class="curriculum-detail__side-card-body">
                        <div v-if="docsLoading && !documents.length" class="text-center py-4">
                            <v-progress-circular indeterminate color="primary" size="24" />
                        </div>

                        <div v-if="documents.length" class="lehrplaene__list mb-3">
                            <div
                                v-for="doc in documents"
                                :key="doc.id"
                                class="lehrplaene__item"
                                :class="{ 'lehrplaene__item--active': previewDoc?.id === doc.id, 'lehrplaene__item--clickable': doc.source_type === 'upload' }"
                                @click="selectPreview(doc)">
                                <v-icon
                                    size="16"
                                    :color="doc.source_type === 'material' ? '#818cf8' : '#94a3b8'"
                                    class="mr-2 flex-shrink-0">
                                    {{ doc.source_type === 'material' ? 'mdi-package-variant-closed' : 'mdi-file-document-outline' }}
                                </v-icon>
                                <span class="lehrplaene__item-name text-truncate">{{ doc.name }}</span>
                                <v-chip
                                    v-if="doc.source_type === 'material'"
                                    size="x-small"
                                    color="primary"
                                    variant="tonal"
                                    class="ml-1 flex-shrink-0">
                                    Material
                                </v-chip>
                                <v-btn
                                    icon="mdi-close"
                                    variant="text"
                                    size="x-small"
                                    color="error"
                                    class="ml-auto flex-shrink-0"
                                    :disabled="isPageActionLocked"
                                    title="Entfernen"
                                    @click.stop="removeDocument(doc)" />
                            </div>
                        </div>

                        <div v-if="previewDoc" class="lehrplaene__preview mb-3">
                            <div class="lehrplaene__preview-header">
                                <span class="text-truncate">{{ previewDoc.name }}</span>
                                <v-btn
                                    icon="mdi-close"
                                    variant="text"
                                    size="x-small"
                                    color="secondary"
                                    :disabled="isPageActionLocked"
                                    @click="previewDoc = null" />
                            </div>
                            <div class="lehrplaene__preview-body">
                                <iframe
                                    v-if="previewIsPdf"
                                    :src="previewUrl"
                                    class="lehrplaene__preview-iframe" />
                                <img
                                    v-else-if="previewIsImage"
                                    :src="previewUrl"
                                    class="lehrplaene__preview-image" />
                                <div v-else class="text-center py-6">
                                    <v-icon size="40" color="#475569" class="mb-2">mdi-file-document-outline</v-icon>
                                    <div class="text-caption" style="color: #64748b">Vorschau nicht verfügbar.</div>
                                    <v-btn
                                        variant="tonal"
                                        color="primary"
                                        size="small"
                                        rounded="lg"
                                        class="text-none mt-2"
                                        :disabled="isPageActionLocked"
                                        :href="previewUrl"
                                        target="_blank">
                                        Herunterladen
                                    </v-btn>
                                </div>
                            </div>
                        </div>

                        <div v-if="!documents.length && !docsLoading" class="text-center py-3">
                            <v-icon size="32" color="#475569" class="mb-1">mdi-file-plus-outline</v-icon>
                            <div class="text-caption" style="color: #64748b">Noch keine Lehrpläne hinzugefügt.</div>
                        </div>

                        <v-divider class="my-2" style="border-color: rgba(148,163,184,0.12)" />

                        <div class="lehrplaene__actions">
                            <v-btn
                                v-if="!showUploadOptions"
                                variant="tonal"
                                color="primary"
                                size="small"
                                rounded="lg"
                                block
                                prepend-icon="mdi-plus"
                                class="text-none"
                                :disabled="isPageActionLocked"
                                @click="showUploadOptions = true">
                                Hinzufügen
                            </v-btn>

                            <div v-if="showUploadOptions" class="lehrplaene__upload-options">
                                <FileUpload
                                    :path="`/api/admin/teaching/curricula/${curriculum.id}/documents/upload`"
                                    :file-label="true"
                                    :short-label="true"
                                    :allowed-file-types="['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/*']"
                                    @fileUploadFinished="onFileUploaded"
                                    @uploadStart="docsLoading = true" />

                                <v-btn
                                    v-if="hasMaterialsAccess"
                                    variant="tonal"
                                    color="primary"
                                    size="small"
                                    rounded="lg"
                                    block
                                    prepend-icon="mdi-package-variant-closed"
                                    class="text-none mt-2"
                                    :disabled="isPageActionLocked"
                                    @click="materialDialogOpen = true">
                                    Aus Materialien wählen
                                </v-btn>

                                <v-btn
                                    variant="text"
                                    size="x-small"
                                    color="secondary"
                                    class="text-none mt-1"
                                    block
                                    :disabled="isPageActionLocked"
                                    @click="showUploadOptions = false">
                                    Abbrechen
                                </v-btn>
                            </div>
                        </div>
                    </div>
                </div>

                <v-dialog v-model="topicDeleteDialogOpen" max-width="420" persistent>
                    <v-card rounded="xl">
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                            <v-icon color="error" size="20">mdi-delete-outline</v-icon>
                            Thema löschen
                        </v-card-title>
                        <v-card-text class="px-4 pb-2">
                            <div class="text-body-2" style="color: #475569">
                                Soll das Thema
                                <strong>{{ topicToDelete?.title }}</strong>
                                wirklich gelöscht werden?
                            </div>
                        </v-card-text>
                        <v-card-actions class="px-4 pb-4">
                            <v-spacer />
                            <v-btn
                                variant="text"
                                color="secondary"
                                :disabled="topicSaving"
                                @click="closeTopicDeleteDialog">
                                Abbrechen
                            </v-btn>
                            <v-btn
                                color="error"
                                variant="flat"
                                :loading="topicSaving"
                                @click="confirmTopicDelete">
                                Löschen
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

                <v-dialog v-model="materialDialogOpen" max-width="560" persistent>
                    <v-card rounded="xl">
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                            <v-icon color="primary" size="22">mdi-package-variant-closed</v-icon>
                            Material auswählen
                        </v-card-title>
                        <v-card-text class="px-4">
                            <v-text-field
                                v-model="materialSearch"
                                label="Material suchen..."
                                variant="outlined"
                                density="compact"
                                hide-details
                                clearable
                                prepend-inner-icon="mdi-magnify"
                                class="mb-3"
                                @update:modelValue="searchMaterials" />
                            <div v-if="materialsLoading" class="text-center py-4">
                                <v-progress-circular indeterminate color="primary" size="24" />
                            </div>
                            <v-list v-else-if="materialResults.length" bg-color="transparent" density="compact" class="py-0" style="max-height: 320px; overflow-y: auto">
                                <v-list-item
                                    v-for="card in materialResults"
                                    :key="card.id"
                                    class="lehrplaene__material-item mb-1 px-3"
                                    rounded="lg"
                                    @click="attachMaterial(card)">
                                    <template #prepend>
                                        <v-icon size="18" color="#a5b4fc" class="mr-2">mdi-package-variant-closed</v-icon>
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ card.title }}</v-list-item-title>
                                    <v-list-item-subtitle v-if="card.subject" class="text-caption">{{ card.subject }}</v-list-item-subtitle>
                                </v-list-item>
                            </v-list>
                            <div v-else class="text-center py-4 text-caption" style="color: #64748b">
                                Keine Materialien gefunden.
                            </div>
                        </v-card-text>
                        <v-card-actions class="px-4 pb-4">
                            <v-btn variant="tonal" :disabled="isPageActionLocked" @click="materialDialogOpen = false">Schließen</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>
            </div>
        </div>
    </div>
</template>

<script>
import { mapState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import FileUpload from '@/pages/components/FileUpload.vue'

const DAY_NAMES_SHORT = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']
const MONTH_NAMES = [
    'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni',
    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
]
export default {
    name: 'CurriculumDetail',
    components: { FileUpload },
    props: {
        curriculum: { type: Object, required: true },
    },
    emits: ['back', 'updated'],

    mounted() {
        this.$nextTick(() => {
            const el = this.$refs.calendarCol
            if (!el) return
            this.calendarWidth = el.offsetWidth
            this._resizeObserver = new ResizeObserver(() => {
                this.calendarWidth = el.offsetWidth
            })
            this._resizeObserver.observe(el)
        })
        this.loadDocuments()
    },

    beforeUnmount() {
        if (this._resizeObserver) {
            this._resizeObserver.disconnect()
        }
    },

    data() {
        const adminStore = useAdminStore()
        const sy = adminStore.config?.selected_schoolyear
        let initYear
        if (sy?.from) {
            const y = parseInt(sy.from.substring(0, 4), 10)
            initYear = Number.isFinite(y) ? y : null
        }
        if (!initYear) {
            const now = new Date()
            initYear = now.getMonth() >= 8 ? now.getFullYear() : now.getFullYear() - 1
        }
        return {
            selectedHalf: 'first',
            selectedYear: initYear,
            calendarWidth: 0,
            _resizeObserver: null,
            documents: [],
            docsLoading: false,
            materialDialogOpen: false,
            materialSearch: '',
            materialResults: [],
            materialsLoading: false,
            _materialSearchTimer: null,
            showUploadOptions: false,
            previewDoc: null,
            savingWeekKeys: [],
            topicSaving: false,
            topicFormError: null,
            showTopicForm: false,
            activeTopicAssignmentId: null,
            activeTopicAssignmentType: null,
            topicDeleteDialogOpen: false,
            topicToDelete: null,
            topicForm: {
                id: null,
                title: '',
            },
        }
    },

    computed: {
        ...mapState(useAdminStore, ['config']),

        hasMaterialsAccess() {
            const roles = Array.isArray(this.config?.roles) ? this.config.roles : []
            return ['super_admin', 'admin', 'materials_admin', 'materials_moderator'].some((r) => roles.includes(r))
        },

        previewUrl() {
            if (!this.previewDoc) return null
            return `/api/admin/teaching/curricula/${this.curriculum.id}/documents/${this.previewDoc.id}/download`
        },

        previewIsPdf() {
            if (!this.previewDoc) return false
            const mime = (this.previewDoc.mime_type || '').toLowerCase()
            return mime === 'application/pdf'
        },

        previewIsImage() {
            if (!this.previewDoc) return false
            const mime = (this.previewDoc.mime_type || '').toLowerCase()
            return mime.startsWith('image/')
        },

        freeWeekKeys() {
            return Array.isArray(this.curriculum.free_weeks) ? this.curriculum.free_weeks : []
        },

        freeWeeksCount() {
            return this.freeWeekKeys.length
        },

        curriculumTopics() {
            return (Array.isArray(this.curriculum.topics) ? this.curriculum.topics : [])
                .map((topic, index) => this.normalizeTopic(topic, index))
        },

        curriculumScopeLabel() {
            return (this.curriculum.semester_count ?? 2) === 1 ? 'Ganzes Semester' : 'Ganzes Jahr'
        },

        calendarHint() {
            if (this.isWeekSelectionActive && this.activeTopicAssignmentTopic) {
                return `Thema "${this.activeTopicAssignmentTopic.title}" ist im Wochenmodus aktiv. Wochen können links direkt ausgewählt werden.`
            }

            return 'Wochen können direkt per Kalender-Icon als unterrichtsfrei markiert werden.'
        },

        assignableMonths() {
            return this.visibleMonths.map((month) => ({
                assignmentKey: month.assignmentKey,
                name: `${month.name} ${month.year}`,
            }))
        },

        isEditingTopic() {
            return this.showTopicForm && this.topicForm.id !== null
        },

        isEditingTopicDates() {
            return this.activeTopicAssignmentId !== null
        },

        isPageActionLocked() {
            return this.isEditingTopic || this.isEditingTopicDates
        },

        activeTopicAssignmentTopic() {
            if (!this.activeTopicAssignmentId) return null

            return this.curriculumTopics.find((topic) => topic.id === this.activeTopicAssignmentId) ?? null
        },

        isWeekSelectionActive() {
            return this.activeTopicAssignmentType === 'weeks' && this.activeTopicAssignmentTopic !== null
        },

        allMonths() {
            const months = []
            const startYear = this.selectedYear
            // September (8) to July (6) next year
            const monthSequence = [
                { m: 8, y: startYear },     // Sep
                { m: 9, y: startYear },      // Okt
                { m: 10, y: startYear },     // Nov
                { m: 11, y: startYear },     // Dez
                { m: 0, y: startYear + 1 },  // Jan
                { m: 1, y: startYear + 1 },  // Feb
                { m: 2, y: startYear + 1 },  // Mar
                { m: 3, y: startYear + 1 },  // Apr
                { m: 4, y: startYear + 1 },  // Mai
                { m: 5, y: startYear + 1 },  // Jun
                { m: 6, y: startYear + 1 },  // Jul
            ]

            const today = new Date()
            today.setHours(0, 0, 0, 0)

            for (const entry of monthSequence) {
                const weeks = this.buildWeeks(entry.y, entry.m, today)
                months.push({
                    key: `${entry.y}-${entry.m}`,
                    assignmentKey: this.formatMonthKey(entry.y, entry.m),
                    name: MONTH_NAMES[entry.m],
                    year: entry.y,
                    month: entry.m,
                    weeks,
                })
            }
            return months
        },

        visibleMonths() {
            const semCount = this.curriculum.semester_count ?? 2
            if (semCount === 2) return this.allMonths

            // 1 semester: first half = Sep-Feb (indices 0-5), second half = Feb-Jul (indices 5-10)
            if (this.selectedHalf === 'first') {
                return this.allMonths.slice(0, 6)
            }
            return this.allMonths.slice(5, 11)
        },

    },

    methods: {
        newTopicForm(topic = null) {
            return {
                id: topic?.id || null,
                title: topic?.title || '',
            }
        },

        normalizeTopic(topic = null, index = 0) {
            const normalizedTopic = topic && typeof topic === 'object' ? topic : {}
            const assignmentType = ['none', 'all_weeks', 'month', 'weeks'].includes(normalizedTopic.assignment_type)
                ? normalizedTopic.assignment_type
                : 'none'
            const monthKeys = [...new Set(
                (
                    Array.isArray(normalizedTopic.month_keys)
                        ? normalizedTopic.month_keys
                        : [normalizedTopic.month_key]
                )
                    .filter(Boolean)
                    .map((monthKey) => String(monthKey).trim())
            )].sort()
            const weekKeys = [...new Set(
                (Array.isArray(normalizedTopic.week_keys) ? normalizedTopic.week_keys : [])
                    .filter(Boolean)
                    .map((weekKey) => String(weekKey).trim())
            )].sort()

            return {
                id: normalizedTopic.id || `topic-${index}`,
                title: typeof normalizedTopic.title === 'string' ? normalizedTopic.title.trim() : '',
                assignment_type: assignmentType,
                month_key: assignmentType === 'month' ? (monthKeys[0] ?? null) : null,
                month_keys: assignmentType === 'month' ? monthKeys : [],
                week_keys: assignmentType === 'weeks' ? weekKeys : [],
            }
        },

        buildTopicPayload(topic = null, overrides = {}) {
            return this.normalizeTopic({
                ...(topic && typeof topic === 'object' ? topic : {}),
                ...overrides,
            })
        },

        findTopic(topicId) {
            return this.curriculumTopics.find((topic) => topic.id === topicId) ?? null
        },

        isTopicAssignmentEditorOpen(topicId) {
            return this.activeTopicAssignmentId === topicId
        },

        toggleTopicAssignmentEditor(topic) {
            if (this.isTopicAssignmentEditorOpen(topic.id)) {
                this.closeTopicAssignmentEditor()
                return
            }

            this.activeTopicAssignmentId = topic.id
            this.activeTopicAssignmentType = topic.assignment_type
        },

        promptDeleteTopic(topic) {
            if (this.isPageActionLocked || this.topicSaving) return

            this.topicToDelete = topic
            this.topicDeleteDialogOpen = true
        },

        closeTopicDeleteDialog() {
            if (this.topicSaving) return

            this.topicDeleteDialogOpen = false
            this.topicToDelete = null
        },

        async confirmTopicDelete() {
            if (!this.topicToDelete) return

            const deleted = await this.deleteTopic(this.topicToDelete.id)

            if (deleted) {
                this.closeTopicDeleteDialog()
            }
        },

        closeTopicAssignmentEditor() {
            if (this.topicSaving) return

            this.activeTopicAssignmentId = null
            this.activeTopicAssignmentType = null
        },

        monthChipLabel(monthKey) {
            const [year, month] = String(monthKey).split('-')
            const monthIndex = Number.parseInt(month, 10) - 1
            const monthName = MONTH_NAMES[monthIndex] ?? monthKey

            return `${monthName} ${year}`
        },

        weekChipLabel(weekKey) {
            const weekStart = new Date(`${weekKey}T00:00:00`)
            if (Number.isNaN(weekStart.getTime())) return weekKey

            const friday = new Date(weekStart)
            friday.setDate(friday.getDate() + 4)

            return `KW ${this.getISOWeek(weekStart)} · ${weekStart.getDate()}.${weekStart.getMonth() + 1}.–${friday.getDate()}.${friday.getMonth() + 1}.`
        },

        topicAssignmentSummary(topic) {
            if (topic.assignment_type === 'none') {
                return 'Keine Zuordnung'
            }

            if (topic.assignment_type === 'month') {
                if (topic.month_keys.length === 1) {
                    return this.monthChipLabel(topic.month_keys[0])
                }

                return `${topic.month_keys.length} Monate`
            }

            if (topic.assignment_type === 'weeks') {
                if (topic.week_keys.length === 1) {
                    return this.weekChipLabel(topic.week_keys[0])
                }

                return `${topic.week_keys.length} Wochen`
            }

            return this.curriculumScopeLabel
        },

        isWeekAssignedToActiveTopic(weekKey) {
            return this.activeTopicAssignmentTopic?.week_keys?.includes(weekKey) ?? false
        },

        buildWeeks(year, month, today) {
            const weeks = []
            const firstDay = new Date(year, month, 1)
            const lastDay = new Date(year, month + 1, 0)

            // Find Monday of the week containing the 1st
            let cursor = new Date(firstDay)
            const dow = cursor.getDay()
            const mondayOffset = dow === 0 ? -6 : 1 - dow
            cursor.setDate(cursor.getDate() + mondayOffset)

            while (cursor <= lastDay || cursor.getDay() !== 1) {
                const days = []
                let weekHasMonthDay = false
                const weekStart = new Date(cursor)

                for (let d = 0; d < 7; d++) {
                    const date = new Date(cursor)
                    const inMonth = date.getMonth() === month && date.getFullYear() === year
                    if (inMonth) weekHasMonthDay = true

                    if (d < 5) {
                        days.push({
                            date: date.toISOString().slice(0, 10),
                            dayNum: date.getDate(),
                            dayName: DAY_NAMES_SHORT[d],
                            isToday: date.getTime() === today.getTime(),
                            outsideMonth: !inMonth,
                        })
                    }
                    cursor.setDate(cursor.getDate() + 1)
                }

                if (!weekHasMonthDay) break

                const friday = new Date(weekStart)
                friday.setDate(friday.getDate() + 4)

                const kw = this.getISOWeek(weekStart)
                const isCurrent = today >= weekStart && today <= friday
                const weekKey = this.formatDateKey(weekStart)

                weeks.push({
                    kw,
                    days,
                    isCurrent,
                    weekKey,
                    rangeLabel: `${weekStart.getDate()}.${weekStart.getMonth() + 1}. – ${friday.getDate()}.${friday.getMonth() + 1}.`,
                })
            }

            return weeks
        },

        formatDateKey(date) {
            const year = date.getFullYear()
            const month = String(date.getMonth() + 1).padStart(2, '0')
            const day = String(date.getDate()).padStart(2, '0')

            return `${year}-${month}-${day}`
        },

        formatMonthKey(year, monthIndex) {
            return `${year}-${String(monthIndex + 1).padStart(2, '0')}`
        },

        getISOWeek(date) {
            const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
            d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7))
            const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1))
            return Math.ceil((((d - yearStart) / 86400000) + 1) / 7)
        },

        isFreeWeek(weekKey) {
            return this.freeWeekKeys.includes(weekKey)
        },

        isWeekSaving(weekKey) {
            return this.savingWeekKeys.includes(weekKey)
        },

        monthHue(idx) {
            const base = 220
            return (base + idx * 28) % 360
        },

        buildCurriculumPayload(overrides = {}) {
            return {
                title: this.curriculum.title,
                description: this.curriculum.description,
                semester_count: this.curriculum.semester_count ?? 2,
                free_weeks: this.freeWeekKeys,
                topics: this.curriculumTopics,
                ...overrides,
            }
        },

        async persistCurriculum(overrides, fallbackMessage) {
            try {
                const response = await axios.put(
                    `/api/admin/teaching/curricula/${this.curriculum.id}`,
                    this.buildCurriculumPayload(overrides),
                )

                const updatedCurriculum = response.data?.data || { ...this.curriculum, ...overrides }
                this.$emit('updated', updatedCurriculum)

                return updatedCurriculum
            } catch (error) {
                useNotificationStore().notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || fallbackMessage,
                    type: 'error',
                    timeout: 3000,
                })

                return null
            }
        },

        async persistTopicAssignment(topicId, overrides, fallbackMessage) {
            const nextTopics = this.curriculumTopics.map((topic) => (
                topic.id === topicId
                    ? this.buildTopicPayload(topic, overrides)
                    : this.buildTopicPayload(topic)
            ))

            this.topicSaving = true

            try {
                return await this.persistCurriculum({
                    topics: nextTopics,
                }, fallbackMessage)
            } finally {
                this.topicSaving = false
            }
        },

        async toggleFreeWeek(weekKey) {
            if (this.isPageActionLocked) return
            if (this.isWeekSaving(weekKey)) return

            const nextFreeWeeks = this.isFreeWeek(weekKey)
                ? this.freeWeekKeys.filter((value) => value !== weekKey)
                : [...this.freeWeekKeys, weekKey].sort()

            this.savingWeekKeys = [...this.savingWeekKeys, weekKey]

            try {
                await this.persistCurriculum({
                    free_weeks: nextFreeWeeks,
                }, 'Freie Woche konnte nicht gespeichert werden.')
            } finally {
                this.savingWeekKeys = this.savingWeekKeys.filter((value) => value !== weekKey)
            }
        },

        openTopicForm(topic = null) {
            this.topicFormError = null
            this.showTopicForm = true
            this.topicForm = this.newTopicForm(topic)
        },

        cancelTopicForm() {
            if (this.topicSaving) return

            this.topicFormError = null
            this.showTopicForm = false
            this.topicForm = this.newTopicForm()
        },

        async activateTopicAssignmentMode(topic, assignmentType) {
            if (this.isEditingTopic) return
            if (this.topicSaving) return

            this.activeTopicAssignmentId = topic.id
            this.activeTopicAssignmentType = assignmentType

            if (!['none', 'all_weeks'].includes(assignmentType)) {
                return
            }

            await this.persistTopicAssignment(topic.id, {
                assignment_type: assignmentType,
                month_keys: [],
                week_keys: [],
            }, 'Datumszuordnung konnte nicht gespeichert werden.')
        },

        async toggleTopicMonthAssignment(topic, monthKey) {
            if (this.isEditingTopic) return
            if (this.topicSaving) return

            this.activeTopicAssignmentId = topic.id
            this.activeTopicAssignmentType = 'month'

            const monthKeys = topic.assignment_type === 'month'
                ? [...topic.month_keys]
                : []

            const nextMonthKeys = monthKeys.includes(monthKey)
                ? (monthKeys.length === 1 ? monthKeys : monthKeys.filter((value) => value !== monthKey))
                : [...monthKeys, monthKey].sort()

            await this.persistTopicAssignment(topic.id, {
                assignment_type: 'month',
                month_keys: nextMonthKeys,
                week_keys: [],
            }, 'Monatszuordnung konnte nicht gespeichert werden.')
        },

        async handleWeekClick(weekKey) {
            if (this.isEditingTopic) return
            if (!this.isWeekSelectionActive || !this.activeTopicAssignmentTopic || this.topicSaving) return

            const weekKeys = this.activeTopicAssignmentTopic.assignment_type === 'weeks'
                ? [...this.activeTopicAssignmentTopic.week_keys]
                : []

            const nextWeekKeys = weekKeys.includes(weekKey)
                ? (weekKeys.length === 1 ? weekKeys : weekKeys.filter((value) => value !== weekKey))
                : [...weekKeys, weekKey].sort()

            await this.persistTopicAssignment(this.activeTopicAssignmentTopic.id, {
                assignment_type: 'weeks',
                month_keys: [],
                week_keys: nextWeekKeys,
            }, 'Wochenauswahl konnte nicht gespeichert werden.')
        },

        async saveTopic() {
            this.topicFormError = null

            const title = this.topicForm.title.trim()
            if (!title) {
                this.topicFormError = 'Bitte einen Thementitel eingeben.'
                return
            }

            const topicId = this.topicForm.id || `topic-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
            const existingTopic = this.findTopic(topicId)
            const normalizedTopic = this.buildTopicPayload(existingTopic, {
                id: topicId,
                title,
                assignment_type: existingTopic?.assignment_type ?? 'none',
            })

            const nextTopics = this.curriculumTopics.some((topic) => topic.id === topicId)
                ? this.curriculumTopics.map((topic) => (
                    topic.id === topicId ? normalizedTopic : this.buildTopicPayload(topic)
                ))
                : [...this.curriculumTopics.map((topic) => this.buildTopicPayload(topic)), normalizedTopic]

            this.topicSaving = true

            try {
                const updatedCurriculum = await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Thema konnte nicht gespeichert werden.')

                if (updatedCurriculum) {
                    this.showTopicForm = false
                    this.topicForm = this.newTopicForm()
                }
            } finally {
                this.topicSaving = false
            }
        },

        async deleteTopic(topicId) {
            const nextTopics = this.curriculumTopics.filter((topic) => topic.id !== topicId)

            this.topicSaving = true

            try {
                const updatedCurriculum = await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Thema konnte nicht entfernt werden.')

                if (updatedCurriculum && this.topicForm.id === topicId) {
                    this.showTopicForm = false
                    this.topicForm = this.newTopicForm()
                }

                if (updatedCurriculum && this.activeTopicAssignmentId === topicId) {
                    this.activeTopicAssignmentId = null
                    this.activeTopicAssignmentType = null
                }

                return updatedCurriculum !== null
            } finally {
                this.topicSaving = false
            }
        },

        async loadDocuments() {
            this.docsLoading = true
            try {
                const res = await axios.get(`/api/admin/teaching/curricula/${this.curriculum.id}/documents`)
                this.documents = res.data?.data || []
            } catch {
                this.documents = []
            } finally {
                this.docsLoading = false
            }
        },

        async onFileUploaded() {
            this.showUploadOptions = false
            await this.loadDocuments()
        },

        selectPreview(doc) {
            if (this.isPageActionLocked) return
            if (doc.source_type !== 'upload') return
            this.previewDoc = this.previewDoc?.id === doc.id ? null : doc
        },

        async removeDocument(doc) {
            if (this.isPageActionLocked) return
            try {
                await axios.delete(`/api/admin/teaching/curricula/${this.curriculum.id}/documents/${doc.id}`)
                if (this.previewDoc?.id === doc.id) this.previewDoc = null
                this.documents = this.documents.filter((d) => d.id !== doc.id)
            } catch {
                // silent
            }
        },

        searchMaterials(value) {
            if (this._materialSearchTimer) clearTimeout(this._materialSearchTimer)
            this._materialSearchTimer = setTimeout(() => {
                this.doSearchMaterials(value || '')
            }, 300)
        },

        async doSearchMaterials(search) {
            this.materialsLoading = true
            try {
                const res = await axios.get('/api/admin/materials/cards', {
                    params: { search, per_page: 20 },
                })
                this.materialResults = res.data?.data || []
            } catch {
                this.materialResults = []
            } finally {
                this.materialsLoading = false
            }
        },

        async attachMaterial(card) {
            if (this.isPageActionLocked) return
            try {
                await axios.post(`/api/admin/teaching/curricula/${this.curriculum.id}/documents/attach-material`, {
                    material_card_id: card.id,
                })
                this.materialDialogOpen = false
                this.showUploadOptions = false
                await this.loadDocuments()
            } catch {
                // silent
            }
        },
    },
}
</script>

<style scoped>
.curriculum-detail {
    width: 100%;
}

.curriculum-detail__title-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.curriculum-detail__title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #e2e8f0;
    margin: 0;
}

.curriculum-detail__desc {
    font-size: 0.88rem;
    color: #94a3b8;
    margin: 4px 0 0;
}

.curriculum-detail__picker-sheet {
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: rgba(15, 23, 42, 0.7);
}

.semester-toggle__btn {
    color: #c7d2fe !important;
    border-color: rgba(148, 163, 184, 0.35) !important;
}

.semester-toggle .v-btn--active.semester-toggle__btn {
    color: #fff !important;
}

/* ---------- Body layout ---------- */
.curriculum-detail__body {
    display: grid;
    grid-template-columns: auto auto 1fr;
    align-items: start;
    gap: 16px;
}

/* ---------- Calendar ---------- */
.curriculum-detail__calendar {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    flex-shrink: 0;
}

.curriculum-detail__free-week-hint {
    width: 100%;
    max-width: 420px;
}

.curriculum-detail__month {
    --accent: hsl(var(--month-hue), 65%, 68%);
    --accent-dim: hsl(var(--month-hue), 45%, 22%);
    --accent-glow: hsl(var(--month-hue), 70%, 50%);
    border-radius: 20px;
    border: 1px solid hsl(var(--month-hue), 50%, 30%, 0.35);
    background: linear-gradient(
        135deg,
        hsl(var(--month-hue), 35%, 12%, 0.85) 0%,
        rgba(15, 23, 42, 0.85) 100%
    );
    overflow: hidden;
    backdrop-filter: blur(12px);
    width: fit-content;
}

.curriculum-detail__month-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    padding: 14px 20px 8px;
    border-bottom: 1px solid hsl(var(--month-hue), 50%, 30%, 0.25);
}

.curriculum-detail__month-name {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--accent);
    letter-spacing: 0.02em;
}

.curriculum-detail__month-year {
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748b;
}

/* ---------- Weeks ---------- */
.curriculum-detail__weeks {
    padding: 10px 12px 14px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
}

.curriculum-detail__week {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 6px 10px;
    border-radius: 12px;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(148, 163, 184, 0.08);
    transition: background 0.2s, border-color 0.2s;
}

.curriculum-detail__week:hover {
    background: rgba(30, 41, 59, 0.7);
    border-color: rgba(148, 163, 184, 0.18);
}

.curriculum-detail__week--topic-selectable {
    cursor: pointer;
    border-color: rgba(129, 140, 248, 0.28);
}

.curriculum-detail__week--topic-selectable:hover {
    background: rgba(49, 46, 129, 0.18);
    border-color: rgba(129, 140, 248, 0.42);
}

.curriculum-detail__week--topic-selected {
    background: rgba(99, 102, 241, 0.18) !important;
    border-color: rgba(129, 140, 248, 0.52) !important;
    box-shadow: 0 0 0 1px rgba(165, 180, 252, 0.16), 0 0 18px rgba(99, 102, 241, 0.16);
}

.curriculum-detail__week--current {
    background: rgba(99, 102, 241, 0.12) !important;
    border-color: rgba(99, 102, 241, 0.35) !important;
    box-shadow: 0 0 16px rgba(99, 102, 241, 0.15);
}

.curriculum-detail__week--free {
    background: rgba(245, 158, 11, 0.12) !important;
    border-color: rgba(245, 158, 11, 0.35) !important;
    box-shadow: 0 0 16px rgba(245, 158, 11, 0.1);
}

/* ---------- Week number ---------- */
.curriculum-detail__week-number {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 36px;
    flex-shrink: 0;
}

.curriculum-detail__week-kw {
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.08em;
}

.curriculum-detail__week-num {
    font-size: 1rem;
    font-weight: 800;
    color: var(--accent);
    line-height: 1;
}

/* ---------- Days grid ---------- */
.curriculum-detail__week-days {
    display: flex;
    gap: 3px;
    flex: 1;
}

.curriculum-detail__day {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(148, 163, 184, 0.06);
    transition: all 0.15s;
}

.curriculum-detail__day--outside {
    opacity: 0.2;
}

.curriculum-detail__day--today {
    background: linear-gradient(135deg, #4f46e5, #7c3aed) !important;
    border-color: #818cf8 !important;
    opacity: 1 !important;
    box-shadow: 0 0 14px rgba(99, 102, 241, 0.5);
}

.curriculum-detail__day-name {
    font-size: 0.58rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    line-height: 1;
}

.curriculum-detail__day--today .curriculum-detail__day-name {
    color: #c7d2fe;
}

.curriculum-detail__day-num {
    font-size: 0.82rem;
    font-weight: 700;
    color: #cbd5e1;
    line-height: 1.2;
}

.curriculum-detail__day--today .curriculum-detail__day-num {
    color: #fff;
}

/* ---------- Week range label ---------- */
.curriculum-detail__week-range {
    font-size: 0.7rem;
    color: #475569;
    font-weight: 500;
    white-space: nowrap;
    min-width: 80px;
    text-align: right;
}

.curriculum-detail__week--free .curriculum-detail__week-range {
    color: #fcd34d;
}

.curriculum-detail__week-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.curriculum-detail__week-chip {
    font-weight: 700;
    letter-spacing: 0.02em;
}

/* ---------- Side cards ---------- */
.curriculum-detail__side-card {
    position: sticky;
    top: 12px;
}

.curriculum-detail__side-card-inner {
    border-radius: 20px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.85) 0%, rgba(15, 23, 42, 0.85) 100%);
    backdrop-filter: blur(12px);
    min-height: 200px;
}

.curriculum-detail__side-card-header {
    display: flex;
    align-items: center;
    padding: 14px 20px 10px;
    border-bottom: 1px solid rgba(148, 163, 184, 0.12);
    font-size: 1.15rem;
    font-weight: 700;
    color: #e2e8f0;
}

.curriculum-detail__side-card-body {
    padding: 14px 20px;
}

.curriculum-detail__side-card--content .curriculum-detail__side-card-inner {
    border-color: rgba(129, 140, 248, 0.28);
    background:
        radial-gradient(circle at top right, rgba(96, 165, 250, 0.14), transparent 34%),
        linear-gradient(135deg, rgba(30, 41, 59, 0.96) 0%, rgba(17, 24, 39, 0.98) 100%);
    box-shadow: 0 16px 36px rgba(2, 6, 23, 0.34);
}

.curriculum-detail__side-card--content .curriculum-detail__side-card-body {
    color: #e5eefc;
}

.curriculum-detail__content-toolbar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.curriculum-detail__content-count {
    font-size: 0.9rem;
    font-weight: 700;
    color: #e2e8f0;
}

.curriculum-detail__content-hint {
    font-size: 0.8rem;
    color: #bfdbfe;
    line-height: 1.45;
    margin-top: 4px;
}

.curriculum-detail__topic-form {
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.22);
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.92), rgba(15, 23, 42, 0.84));
    padding: 14px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
}

.curriculum-detail__topic-form-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.curriculum-detail__topic-form-error {
    font-size: 0.8rem;
    color: #fecaca;
}

.curriculum-detail__topic-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.curriculum-detail__topic-item {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 12px;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.78));
}

.curriculum-detail__topic-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.curriculum-detail__topic-main {
    min-width: 0;
    flex: 1;
}

.curriculum-detail__topic-title-row {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    flex-wrap: wrap;
}

.curriculum-detail__topic-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #e2e8f0;
    line-height: 1.35;
}

.curriculum-detail__topic-summary-chip {
    font-weight: 600;
}

.curriculum-detail__topic-meta-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}

.curriculum-detail__topic-meta-text {
    margin-top: 8px;
    font-size: 0.8rem;
    color: #bfdbfe;
}

.curriculum-detail__topic-actions {
    display: flex;
    align-items: center;
    gap: 2px;
    flex-shrink: 0;
}

.curriculum-detail__topic-assignment-panel {
    border-top: 1px solid rgba(148, 163, 184, 0.14);
    padding-top: 10px;
}

.curriculum-detail__topic-assignment-options {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.curriculum-detail__topic-assignment-months,
.curriculum-detail__topic-assignment-weeks {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.curriculum-detail__topic-assignment-weeks {
    flex-direction: column;
    align-items: flex-start;
}

.curriculum-detail__topic-assignment-hint {
    font-size: 0.8rem;
    color: #bfdbfe;
}

.curriculum-detail__assignment-chip {
    cursor: pointer;
}

.curriculum-detail__topic-empty {
    font-size: 0.84rem;
    color: #cbd5e1;
}

.curriculum-detail__content-add-btn,
.curriculum-detail__topic-save-btn {
    color: #eff6ff !important;
    font-weight: 700;
    box-shadow: 0 10px 24px rgba(79, 70, 229, 0.26);
}

.curriculum-detail__side-card--content :deep(.v-field) {
    background: rgba(248, 250, 252, 0.97);
    border-radius: 12px;
}

.curriculum-detail__side-card--content :deep(.v-field__overlay) {
    background: transparent;
}

.curriculum-detail__side-card--content :deep(.v-field__outline) {
    --v-field-border-opacity: 1;
    color: rgba(148, 163, 184, 0.38);
}

.curriculum-detail__side-card--content :deep(.v-label),
.curriculum-detail__side-card--content :deep(.v-field-label) {
    color: #475569 !important;
    opacity: 1;
}

.curriculum-detail__side-card--content :deep(input),
.curriculum-detail__side-card--content :deep(textarea),
.curriculum-detail__side-card--content :deep(.v-field__input),
.curriculum-detail__side-card--content :deep(.v-select__selection-text),
.curriculum-detail__side-card--content :deep(.v-autocomplete__selection) {
    color: #0f172a !important;
}

.curriculum-detail__side-card--content :deep(.v-field__append-inner .v-icon),
.curriculum-detail__side-card--content :deep(.v-field__clearable .v-icon),
.curriculum-detail__side-card--content :deep(.v-select__menu-icon) {
    color: #475569 !important;
}

.curriculum-detail__side-card--content :deep(.v-chip.v-chip--size-small),
.curriculum-detail__side-card--content :deep(.v-chip.v-chip--size-x-small) {
    color: #e2e8f0;
}

/* ---------- Responsive ---------- */
/* ---------- Lehrpläne list ---------- */
.lehrplaene__list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.lehrplaene__item {
    display: flex;
    align-items: center;
    padding: 6px 8px;
    border-radius: 8px;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(148, 163, 184, 0.08);
}

.lehrplaene__item-name {
    font-size: 0.82rem;
    color: #cbd5e1;
    font-weight: 500;
}

.lehrplaene__item--clickable {
    cursor: pointer;
}

.lehrplaene__item--clickable:hover {
    background: rgba(99, 102, 241, 0.08);
    border-color: rgba(99, 102, 241, 0.2);
}

.lehrplaene__item--active {
    background: rgba(99, 102, 241, 0.15) !important;
    border-color: rgba(99, 102, 241, 0.35) !important;
}

/* ---------- Preview ---------- */
.lehrplaene__preview {
    border-radius: 12px;
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: rgba(15, 23, 42, 0.6);
    overflow: hidden;
}

.lehrplaene__preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 10px;
    border-bottom: 1px solid rgba(148, 163, 184, 0.1);
    font-size: 0.78rem;
    font-weight: 600;
    color: #a5b4fc;
}

.lehrplaene__preview-body {
    min-height: 200px;
}

.lehrplaene__preview-iframe {
    width: 100%;
    aspect-ratio: 1 / 1.4142;
    border: none;
    background: #fff;
}

.lehrplaene__preview-image {
    width: 100%;
    aspect-ratio: 1 / 1.4142;
    object-fit: contain;
    display: block;
    background: rgba(15, 23, 42, 0.8);
}

.lehrplaene__material-item {
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(99, 102, 241, 0.15);
    cursor: pointer;
}

.lehrplaene__material-item:hover {
    background: rgba(99, 102, 241, 0.12);
}

@media (max-width: 900px) {
    .curriculum-detail__body {
        grid-template-columns: 1fr;
        width: auto;
    }

    .curriculum-detail__side-card {
        position: static;
    }
}

@media (max-width: 700px) {
    .curriculum-detail__day {
        width: 34px;
        height: 36px;
    }

    .curriculum-detail__week-range {
        display: none;
    }

    .curriculum-detail__month-header {
        padding: 10px 14px 6px;
    }

    .curriculum-detail__week-actions {
        margin-left: auto;
    }

    .curriculum-detail__content-toolbar,
    .curriculum-detail__topic-row,
    .curriculum-detail__topic-assignment-options {
        flex-direction: column;
    }
}
</style>
