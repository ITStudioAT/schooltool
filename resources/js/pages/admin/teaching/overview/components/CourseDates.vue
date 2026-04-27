<template>
    <ItsGridBox
        variant="overview"
        color="primary"
        icon="mdi-calendar"
        class="w-100"
        v-if="selected_course"
        :disabled="isGridDisabled">
        <template #title>
            <div>Termine – {{ selected_course.title }} ({{ selectedCourseClasses }})</div>
        </template>
        <template #header-actions>
            <v-btn icon="mdi-plus" size="small" variant="tonal" @click="newDates" :disabled="isEditingContent || isSavingContent || action === 'new_course_dates'" />
            <v-btn icon="mdi-eye-off-outline" size="small" variant="tonal" title="Ausblenden" @click="show_dates = false" />
        </template>
        <v-card tile flat color="transparent" class="w-100" :disabled="action != '' || isSavingContent">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <div class="d-flex flex-wrap align-center ga-2 mt-2 w-100">
                    <v-btn-toggle v-if="!compactStudentView && semesterCount === 2" v-model="activeSemester" mandatory density="compact" color="primary">
                        <v-btn :value="1" size="small">1. Sem</v-btn>
                        <v-btn :value="2" size="small">2. Sem</v-btn>
                        <v-btn :value="3" size="small">1+2</v-btn>
                    </v-btn-toggle>
                    <div class="ml-auto d-flex">
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
                <v-list density="compact">
                    <v-list-item
                        v-for="courseDate in displayedCourseDates"
                        :key="courseDate.id"
                        :disabled="(isEditingContent && editing_content_id !== courseDate.id) || isSavingContent"
                        :class="courseDateRowClass(courseDate)"
                        :style="courseDateHighlightStyle(courseDate)">
                        <div class="d-flex flex-column ga-2 w-100 cursor-pointer" @click="selectCourseDate(courseDate)">
                            <div class="course-date-row d-flex align-center ga-2 w-100">
                                <v-chip
                                    v-if="highlightedDateId === courseDate.id"
                                    size="x-small"
                                    :color="isDateToday(courseDate) ? 'success' : 'primary'"
                                    variant="flat"
                                    class="course-date-icon font-weight-bold px-2">
                                    {{ isDateToday(courseDate) ? 'Heute' : 'Nächster' }}
                                </v-chip>
                                <v-chip
                                    size="x-small"
                                    variant="tonal"
                                    :color="selected_courseDate?.id === courseDate.id ? 'secondary' : (highlightedDateId === courseDate.id ? 'success' : 'primary')"
                                    class="course-date-chip cursor-pointer"
                                    @click.stop="selectCourseDate(courseDate)">
                                    {{ getWeekday(courseDate.date) }}
                                </v-chip>
                                <v-chip
                                    size="x-small"
                                    :variant="selected_courseDate?.id === courseDate.id ? 'flat' : (highlightedDateId === courseDate.id ? 'flat' : 'outlined')"
                                    :color="selected_courseDate?.id === courseDate.id ? 'secondary' : (highlightedDateId === courseDate.id ? 'success' : undefined)"
                                    class="course-date-chip cursor-pointer"
                                    @click.stop="selectCourseDate(courseDate)">
                                    {{ formatDate(courseDate.date) }}
                                </v-chip>
                                <div class="course-date-hours text-body-2 flex-grow-1">
                                    <v-chip v-for="h in courseDate.hours" :key="h" size="x-small" variant="tonal" class="mr-1">{{ h }}. Std</v-chip>
                                    <v-chip v-if="hasStatus(courseDate, 'free') && courseDate.free_reason" size="x-small" color="success" variant="outlined">
                                        {{ courseDate.free_reason }}
                                    </v-chip>
                                </div>
                                <div class="course-date-actions d-flex align-center ga-1" @click.stop>
                                    <v-icon
                                        v-if="isAttendanceChecked(courseDate)"
                                        size="18"
                                        color="success"
                                        title="Anwesenheit geprüft">
                                        mdi-check-circle
                                    </v-icon>
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
                                v-if="courseDateInlineContent(courseDate) || curriculumEntriesForCourseDate(courseDate).length"
                                class="course-date-curriculum-inline pl-1 pr-2"
                                @click.stop>
                                <div class="course-date-curriculum-stack">
                                    <div v-if="courseDateInlineContent(courseDate)" class="course-date-curriculum-stack__content">
                                        {{ courseDateInlineContent(courseDate) }}
                                    </div>
                                    <div v-if="courseDateInlineContent(courseDate) && curriculumEntriesForCourseDate(courseDate).length" class="course-date-curriculum-divider">
                                        <span class="course-date-curriculum-divider__label">CURRICULUM</span>
                                    </div>
                                    <div
                                        v-for="(entry, entryIndex) in curriculumEntriesForCourseDate(courseDate)"
                                        :key="`${courseDate.id}-inline-${entryIndex}`"
                                        class="course-date-curriculum-stack__entry">
                                        <v-icon v-if="entry.hasMaterials" size="14" class="mr-1 cursor-pointer course-date-material-icon" @click.stop="openMaterialOverview(entry)" title="Materialien anzeigen">mdi-paperclip</v-icon>{{ entry.label }}
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
                                        <div class="d-flex ga-1">
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
    </ItsGridBox>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { parseLocalDate } from '@/helpers/date'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
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
        this.teachingStore = useTeachingStore()
        this.curriculumStore = useCurriculumStore()
        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
        }
        await this.loadSelectedCourseCurriculumDetail()
        this.activeSemester = this.config?.user?.teaching_active_semester || 1
    },

    unmounted() {
        this.courseDateStore.clearDates()
    },

    data() {
        return {
            adminStore: null,
            courseStore: null,
            courseDateStore: null,
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
        ...mapWritableState(useCourseStore, ['selected_course', 'show_dates']),
        ...mapWritableState(useCourseDateStore, ['courseDates', 'selected_courseDate']),
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
            if (!dates.length) return []

            const activeCourseDateId = this.selected_courseDate?.id || this.highlightedDateId
            let activeIndex = activeCourseDateId
                ? dates.findIndex((courseDate) => String(courseDate.id) === String(activeCourseDateId))
                : -1

            if (activeIndex < 0 && this.selected_courseDate?.date) {
                activeIndex = dates.findIndex((courseDate) => courseDate.date === this.selected_courseDate.date)
            }

            if (activeIndex < 0) {
                activeIndex = 0
            }

            const selectedRanges = Array.isArray(this.dateRangeSelection) && this.dateRangeSelection.length ? this.dateRangeSelection : ['today']
            const includeBefore = selectedRanges.includes('before')
            const includeToday = selectedRanges.includes('today')
            const includeAfter = selectedRanges.includes('after')
            const visibleDates = []

            if (this.compactStudentView) {
                if (includeBefore) {
                    visibleDates.push(...dates.slice(0, Math.max(0, activeIndex - 1)))
                }
                if (activeIndex > 0) visibleDates.push(dates[activeIndex - 1])
                if (dates[activeIndex]) visibleDates.push(dates[activeIndex])
                if (activeIndex < dates.length - 1) visibleDates.push(dates[activeIndex + 1])
                if (includeAfter) {
                    visibleDates.push(...dates.slice(activeIndex + 2))
                }
            } else {
                if (includeBefore) {
                    visibleDates.push(...dates.slice(0, activeIndex))
                } else if (activeIndex > 0) {
                    visibleDates.push(dates[activeIndex - 1])
                }
                if (includeToday && dates[activeIndex]) {
                    visibleDates.push(dates[activeIndex])
                }
                if (includeAfter) {
                    visibleDates.push(...dates.slice(activeIndex + 1))
                } else if (activeIndex < dates.length - 1) {
                    visibleDates.push(dates[activeIndex + 1])
                }
            }

            return visibleDates.filter((courseDate, index, array) => array.findIndex((item) => String(item.id) === String(courseDate.id)) === index)
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
        highlightedDateId() {
            const dates = this.selected_course?.course_dates || []
            if (!dates.length) return null

            const today = new Date()
            today.setHours(0, 0, 0, 0)
            const todayStr = this.toDateString(today)

            // Check if today exists
            const todayDate = dates.find((d) => d.date === todayStr)
            if (todayDate) return todayDate.id

            // Find next upcoming date (first date >= today)
            const upcomingDate = dates.find((d) => {
                const dateObj = parseLocalDate(d.date)
                dateObj.setHours(0, 0, 0, 0)
                return dateObj >= today
            })

            return upcomingDate?.id || null
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
        selected_course: {
            immediate: true,
            async handler(course) {
                if (!course) {
                    this.selectedCourseCurriculumDetail = null
                    this.selectedCourseCurriculumDetailLoadingId = null
                    return
                }

                await this.loadSelectedCourseCurriculumDetail()
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
            return d.toLocaleDateString('de-DE', { weekday: 'long' })
        },
        formatDate(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        contentHtml(text) {
            if (!text) return ''
            if (text.includes('<p>') || text.includes('<br')) return text
            return text
                .split('\n')
                .map((line) => `<p>${line || '<br>'}</p>`)
                .join('')
        },
        courseDateInlineContent(courseDate) {
            const content = String(courseDate?.content || '').trim()
            if (!content) {
                return ''
            }

            return content
                .replace(/<br\s*\/?>/gi, '\n')
                .replace(/<\/p>/gi, '\n')
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
        isAttendanceChecked(courseDate) {
            if (typeof courseDate?.attendance_checked === 'boolean') return courseDate.attendance_checked
            const status = Array.isArray(courseDate?.status) ? courseDate.status : []
            return status.includes('att_checked:1')
        },
        isDateToday(courseDate) {
            if (!courseDate?.date) return false
            const today = new Date()
            today.setHours(0, 0, 0, 0)
            return courseDate.date === this.toDateString(today)
        },
        courseDateRowClass(courseDate) {
            if (this.hasStatus(courseDate, 'pruefung')) return 'course-date-row--exam'
            if (this.hasStatus(courseDate, 'free')) return 'course-date-row--free'
            if (this.hasStatus(courseDate, 'entfaellt')) return 'course-date-row--entfaellt'
            return ''
        },
        courseDateHighlightStyle(courseDate) {
            if (this.selected_courseDate?.id === courseDate.id) {
                return { backgroundColor: '#fff3e0', borderLeft: '5px solid #e65100' }
            }
            if (this.highlightedDateId !== courseDate.id) return {}
            if (this.isDateToday(courseDate)) {
                return { backgroundColor: '#bbdefb', borderLeft: '5px solid #1565c0' }
            }
            return { backgroundColor: '#e3f2fd', borderLeft: '3px solid #1976d2' }
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

.course-date-icon {
    flex: 0 0 auto;
}

.course-date-chip {
    flex: 0 0 auto;
}

.course-date-hours {
    min-width: 120px;
}

.course-date-actions {
    margin-left: auto;
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
    color: rgba(var(--v-theme-on-surface), 0.78);
    font-size: 0.78rem;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.course-date-curriculum-stack__entry {
    border-left: 2px solid rgba(var(--v-theme-primary), 0.35);
    color: rgb(var(--v-theme-primary));
    font-size: 0.76rem;
    line-height: 1.25;
    padding-left: 8px;
}

@media (max-width: 700px) {
    .course-date-hours {
        flex-basis: 100%;
        min-width: 100%;
        margin-top: 2px;
        order: 2;
    }

    .course-date-actions {
        order: 1;
    }

    .course-date-curriculum-chip {
        flex: 1 1 100%;
        margin-top: 4px;
    }
}

.course-date-row--exam {
    background-color: #ffebee !important;
    border-left: 4px solid #ff5722;
}

.course-date-row--free {
    background-color: #c8e6c9 !important;
    border-left: 4px solid #4caf50;
}

.course-date-row--entfaellt {
    background-color: #c8e6c9 !important;
    border-left: 4px solid #4caf50;
}

.course-date-row--today {
    background-color: #c8e6c9 !important;
    border-left: 5px solid #2e7d32;
}

.course-date-row--next {
    background-color: #e3f2fd !important;
    border-left: 5px solid #1565c0;
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

.material-overview-attachment {
    border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
    border-radius: 8px;
}
</style>
