<template>
    <ItsGridBox variant="overview" color="primary" :title="activeStudentsCount + ' Schüler:innen'" icon="mdi-invoice-list" class="w-100" v-if="selected_course" :disabled="action != ''">
        <template #header-actions>
            <div v-if="selectedCourseDateForCourse" class="students-date-navigation d-flex align-center ga-1">
                <v-btn icon="mdi-chevron-left" size="x-small" color="primary" variant="tonal" :disabled="!hasPrevCourseDate" @click="selectPrevCourseDate" />
                <v-chip size="small" color="primary" variant="outlined">{{ selectedCourseDateLabel }}</v-chip>
                <v-btn icon="mdi-chevron-right" size="x-small" color="primary" variant="tonal" :disabled="!hasNextCourseDate" @click="selectNextCourseDate" />
            </div>
            <div v-else class="text-caption text-medium-emphasis">Kein Datum</div>
        </template>
        <v-card class="mt-3 mb-3" color="primary" variant="tonal" data-testid="course-students-semester-selection">
            <v-card-text class="d-flex align-center flex-wrap ga-3 px-3 py-2">
                <span class="text-caption text-medium-emphasis font-weight-medium">Zeitraum:</span>
                <v-btn-toggle v-model="activeSemester" mandatory density="compact" color="primary" variant="tonal">
                    <v-btn :value="1" size="small">1. Sem</v-btn>
                    <v-btn :value="2" size="small">2. Sem</v-btn>
                    <v-btn :value="3" size="small">Sem 1+2</v-btn>
                </v-btn-toggle>
            </v-card-text>
        </v-card>
        <div class="students-action-bar d-flex align-center ga-2 mx-3 mt-2 flex-wrap">
            <v-btn
                v-if="canEditAttendance && selectedCourseDateForCourse && !isDayOverviewMode && !show_bulk_entry"
                size="small"
                class="students-attendance-check-btn"
                :variant="'flat'"
                :color="attendanceCheckedForSelectedDate && !hasUnsavedAttendanceChanges ? 'success' : 'warning'"
                :class="attendanceCheckedForSelectedDate && !hasUnsavedAttendanceChanges ? '' : 'students-attendance-check-btn--open'"
                :loading="savingAttendance"
                @click="toggleAttendanceChecked">
                <v-icon start>{{ attendanceCheckedForSelectedDate && !hasUnsavedAttendanceChanges ? 'mdi-check-circle' : 'mdi-content-save' }}</v-icon>
                {{ attendanceCheckedForSelectedDate && !hasUnsavedAttendanceChanges ? 'Anwesenheit geprüft' : 'Anwesenheit prüfen' }}
            </v-btn>
            <v-btn
                v-if="selectedCourseDateForCourse && !show_bulk_entry"
                size="small"
                class="students-overview-toggle-btn"
                :variant="isDayOverviewMode ? 'flat' : 'outlined'"
                :color="isDayOverviewMode ? 'warning' : 'primary'"
                @click="toggleStudentsViewMode">
                <v-icon start>{{ isDayOverviewMode ? 'mdi-close' : 'mdi-view-list' }}</v-icon>
                {{ isDayOverviewMode ? 'Heute schließen' : 'Heute' }}
            </v-btn>
            <v-btn
                v-if="!isDayOverviewMode"
                size="small"
                class="students-bulk-btn"
                :variant="show_bulk_entry ? 'flat' : 'outlined'"
                :color="show_bulk_entry ? 'warning' : 'primary'"
                @click="toggleBulkEntry">
                <v-icon v-if="show_bulk_entry" start>mdi-close</v-icon>
                {{ show_bulk_entry ? 'Sammelaktion schließen' : 'Sammelaktion' }}
            </v-btn>
            <v-spacer />
            <v-btn-toggle
                v-if="!isDayOverviewMode"
                v-model="students_sort_mode"
                mandatory
                density="compact"
                color="primary"
                class="students-sort-toggle">
                <v-btn size="small" value="class_last_name" class="students-sort-toggle-btn">Klasse, Name</v-btn>
                <v-btn size="small" value="last_name_first_name" class="students-sort-toggle-btn">Name</v-btn>
            </v-btn-toggle>
        </div>
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <!-- Ausgewählte Schülerinnen (Anzeige) -->
                <v-card variant="outlined" v-if="selected_course">
                    <v-card-text v-if="show_bulk_entry && !isDayOverviewMode" class="pt-0">
                        <v-card variant="outlined" class="pa-3">
                            <div class="text-caption text-medium-emphasis mb-2">Eintrag für mehrere Schüler:innen</div>
                            <v-form ref="bulkEntryForm" @submit.prevent="saveBulkEntry">
                                <div class="d-flex align-start ga-4 flex-wrap mb-3">
                                    <div class="d-flex flex-column ga-1">
                                        <div class="text-caption text-medium-emphasis">Typ</div>
                                        <div class="d-flex flex-wrap ga-1">
                                            <v-chip
                                                size="small"
                                                :variant="bulk_entry_form.type ? 'outlined' : 'flat'"
                                                :color="bulk_entry_form.type ? 'default' : 'success'"
                                                @click="bulk_entry_form.type = null; bulk_entry_form.grade = null">
                                                —
                                            </v-chip>
                                            <v-chip
                                                v-for="type in workTypeItems"
                                                :key="`bulk-type-${type.value}`"
                                                size="small"
                                                :title="type.title"
                                                :variant="bulk_entry_form.type === type.value ? 'flat' : 'tonal'"
                                                :color="bulk_entry_form.type === type.value ? 'success' : 'default'"
                                                @click="bulk_entry_form.type = type.value; bulk_entry_form.grade = null">
                                                {{ type.value }}
                                            </v-chip>
                                        </div>
                                        <div v-if="selectedBulkTypeName" class="text-caption text-medium-emphasis">
                                            {{ selectedBulkTypeName }}
                                        </div>
                                    </div>
                                    <v-spacer />
                                    <div
                                        v-if="bulk_entry_form.type && bulkGradeInputMode !== 'none'"
                                        class="d-flex flex-column ga-1 align-end">
                                        <div class="text-caption text-medium-emphasis">{{ bulkGradeLabel }}</div>
                                        <v-text-field
                                            v-if="bulkGradeInputMode === 'free'"
                                            v-model="bulk_entry_form.grade"
                                            density="compact"
                                            hide-details
                                            label="Wert"
                                            maxlength="255" />
                                        <div v-else class="d-flex flex-wrap ga-1 justify-end">
                                            <v-chip
                                                size="small"
                                                :variant="bulk_entry_form.grade ? 'outlined' : 'flat'"
                                                :color="bulk_entry_form.grade ? 'default' : 'success'"
                                                @click="bulk_entry_form.grade = null">
                                                —
                                            </v-chip>
                                            <v-chip
                                                v-for="grade in gradeItemsForType"
                                                :key="`bulk-grade-${grade.value}`"
                                                size="small"
                                                :title="grade.title"
                                                :disabled="!bulk_entry_form.type"
                                                :variant="bulk_entry_form.grade === grade.value ? 'flat' : 'tonal'"
                                                :color="bulk_entry_form.grade === grade.value ? 'success' : 'default'"
                                                @click="bulk_entry_form.grade = grade.value">
                                                {{ grade.value }}
                                            </v-chip>
                                        </div>
                                    </div>
                                </div>
                                <v-date-input v-model="bulk_entry_form.date" label="Datum" />
                                <v-textarea v-model="bulk_entry_form.description" label="Beschreibung" rows="3" :counter="1024" :maxlength="1024" />

                                <div class="bulk-entry-footer d-flex align-center justify-space-between mt-2">
                                    <div class="bulk-entry-selection-actions d-flex align-center ga-2">
                                        <v-btn size="small" variant="text" @click="selectAllBulkStudents">Alle auswählen</v-btn>
                                        <v-btn size="small" variant="text" @click="clearBulkStudents">Keine auswählen</v-btn>
                                    </div>
                                    <v-btn
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        :loading="bulk_entry_saving"
                                        :disabled="bulk_entry_saving || !bulkEntryEnabled"
                                        class="bulk-entry-submit"
                                        type="submit">
                                        {{ bulk_entry_saving ? 'Wird angewendet...' : (bulk_entry_form.student_ids.length ? `Auf ${bulk_entry_form.student_ids.length} Schüler:in(nen) anwenden` : 'Auf ausgewählte Schüler:innen anwenden') }}
                                    </v-btn>
                                </div>
                            </v-form>
                        </v-card>
                    </v-card-text>
                    <v-divider />
                    <v-card-text class="pa-0" v-if="!isDayOverviewMode">
                        <v-list density="compact" class="students-grid">
                            <v-list-item
                                v-for="student in sortedSelectedStudents"
                                :key="student.id"
                                :link="!show_bulk_entry"
                                :class="{ 'student-list-item--clickable': !show_bulk_entry }"
                                @click="openStudentFromCard(student)">
                                <div class="student-row d-flex flex-wrap align-center ga-2 w-100" :class="{ 'student-row--canceled': isStudentCanceled(student) }">
                                    <v-checkbox
                                        v-if="show_bulk_entry"
                                        v-model="bulk_entry_form.student_ids"
                                        :value="student.id"
                                        density="compact"
                                        hide-details
                                        class="flex-grow-0" />
                                    <div class="student-identity d-flex align-center ga-2">
                                        <div class="student-presence student-presence--left">
                                            <v-btn
                                                v-if="canEditAttendance && selectedCourseDateForCourse"
                                                :key="`presence-${student.id}-${isStudentPresentForSelectedDate(student.id) ? '1' : '0'}`"
                                                size="x-small"
                                                :color="studentAttendanceStateForSelectedDate(student.id) === null ? undefined : studentAttendanceStateForSelectedDate(student.id) ? 'success' : 'error'"
                                                :variant="studentAttendanceStateForSelectedDate(student.id) === null ? 'text' : 'tonal'"
                                                aria-label="Anwesenheit ändern"
                                                @click.stop="toggleStudentPresence(student)">
                                                <v-icon v-if="studentAttendanceStateForSelectedDate(student.id) !== null" size="16">
                                                    {{ isStudentPresentForSelectedDate(student.id) ? 'mdi-check' : 'mdi-close' }}
                                                </v-icon>
                                            </v-btn>
                                        </div>
                                        <v-chip v-if="student.schoolclass || student.class" size="x-small" variant="tonal" color="primary">
                                            {{ student.schoolclass || student.class }}
                                        </v-chip>
                                        <div
                                            class="student-name"
                                            :class="studentNameClass(student)">
                                            <div class="student-name-line">
                                                <span class="student-name-text">
                                                    {{ student.last_name }}, {{ student.first_name }}
                                                </span>
                                                <CourseStudentIndicators :student="studentForSelectedSemester(student)" :course-id="selected_course.id" stars-only show-empty-stars
                                                    @select="$refs.studentNotes.open(student, $event)" />
                                                <v-icon
                                                    v-if="studentSexIcon(student)"
                                                    size="15"
                                                    :color="studentSexColor(student)"
                                                    :title="studentSexTitle(student)">
                                                    {{ studentSexIcon(student) }}
                                                </v-icon>
                                                <CourseStudentIndicators :student="studentForSelectedSemester(student)" :course-id="selected_course.id"
                                                    :active-semester="activeSemester" :semester-two-start-date="countSem2StartDate"
                                                    :schoolyear="config.selected_schoolyear"
                                                    @select="$refs.studentNotes.open(student, $event)" />
                                            </div>
                                            <div v-if="studentEmailText(student)" class="student-meta-line text-caption text-medium-emphasis d-flex align-center ga-1">
                                                {{ studentEmailText(student) }}
                                                <v-icon
                                                    size="13"
                                                    class="cursor-pointer"
                                                    :color="copiedEmailId === student.id ? 'success' : undefined"
                                                    :title="copiedEmailId === student.id ? 'Kopiert!' : 'E-Mail kopieren'"
                                                    @click.stop="copyEmail(student)">
                                                    {{ copiedEmailId === student.id ? 'mdi-check' : 'mdi-content-copy' }}
                                                </v-icon>
                                            </div>
                                            <div
                                                v-if="selected_course?.teaching_show_student_age && studentBirthDetails(student)"
                                                class="student-meta-line text-caption text-medium-emphasis d-flex align-center ga-1">
                                                <v-icon size="13">mdi-cake-variant-outline</v-icon>
                                                {{ studentBirthDetails(student) }}
                                            </div>
                                            <div
                                                v-if="selected_course?.teaching_show_student_last_login && studentLastLoginText(student)"
                                                class="student-meta-line text-caption text-medium-emphasis d-flex align-center ga-1">
                                                <v-icon size="13">mdi-login-variant</v-icon>
                                                Last Login: {{ studentLastLoginText(student) }}
                                            </div>
                                        </div>
                                        <v-chip v-if="isStudentCanceled(student)" size="x-small" variant="tonal" color="warning">
                                            Storniert{{ student.canceled_at ? `: ${formatCanceledAt(student.canceled_at)}` : '' }}
                                        </v-chip>
                                    </div>
                                    <CourseStudentPerformance
                                        v-if="!show_bulk_entry"
                                        :student="student"
                                        :course="selected_course"
                                        :entries="performanceData.entries"
                                        :behaviour-entries="performanceData.behaviourEntries"
                                        :works="performanceData.works"
                                        :evaluations="performanceData.evaluations"
                                        :schema="selectedCourseSchema"
                                        :uses-entry-areas="usesNewBulkEntryDefinitions"
                                        :active-semester="activeSemester"
                                        :semester-two-start-date="countSem2StartDate"
                                        :semester-count="semesterCount"
                                        :schoolyear="config.selected_schoolyear"
                                        :loading="performanceLoading"
                                        :load-failed="performanceLoadFailed" />
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!selected_course?.students_info?.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine Schülerinnen ausgewählt.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                    <v-card-text class="pa-0" v-else>
                        <v-list density="compact">
                            <v-list-item
                                v-for="item in dayOverviewStudents"
                                :key="`day-overview-${item.student.id}`"
                                link
                                class="student-list-item--clickable"
                                @click="openStudent(item.student)">
                                <div class="d-flex flex-column ga-2 w-100 py-1">
                                    <div class="d-flex align-center ga-2 flex-wrap">
                                        <v-chip v-if="item.student.schoolclass || item.student.class" size="x-small" variant="tonal" color="primary">
                                            {{ item.student.schoolclass || item.student.class }}
                                        </v-chip>
                                        <div class="text-body-2 font-weight-medium">
                                            {{ item.student.last_name }}, {{ item.student.first_name }}
                                        </div>
                                        <CourseStudentIndicators :student="studentForSelectedSemester(item.student)" :course-id="selected_course.id"
                                            :active-semester="activeSemester" :semester-two-start-date="countSem2StartDate"
                                            :schoolyear="config.selected_schoolyear"
                                            @select="$refs.studentNotes.open(item.student, $event)" />
                                        <v-chip size="x-small" variant="tonal" color="secondary">{{ item.entries.length }} Eintrag{{ item.entries.length === 1 ? '' : 'e' }}</v-chip>
                                    </div>
                                    <div class="d-flex flex-wrap ga-1">
                                        <v-chip
                                            v-for="entry in item.entries"
                                            :key="entry.uid"
                                            size="x-small"
                                            :color="dayEntryColor(entry)"
                                            variant="tonal">
                                            {{ dayEntryLabel(entry) }}
                                        </v-chip>
                                    </div>
                                    <div class="d-flex flex-column ga-1" v-if="item.entries.some((entry) => !!entry.description)">
                                        <div class="text-caption text-medium-emphasis" v-for="entry in item.entries.filter((e) => !!e.description)" :key="`${entry.uid}-description`">
                                            <strong>{{ dayEntryShortLabel(entry) }}:</strong> {{ entry.description }}
                                        </div>
                                    </div>
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!selectedCourseDateForCourse">
                                <v-list-item-title class="text-caption text-medium-emphasis">Bitte zuerst ein Datum auswählen.</v-list-item-title>
                            </v-list-item>
                            <v-list-item v-else-if="!dayOverviewStudents.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine Einträge für den ausgewählten Tag.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-card-text>
        </v-card>
        <CourseStudentNotes ref="studentNotes" :course="selected_course" />
    </ItsGridBox>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { parseLocalDate } from '@/helpers/date'
import { teachingCourseMatchesSchoolyear, teachingPerformanceDateScope, teachingStarDateScope } from '@/helpers/teachingSemester'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useImport116Store } from '@/stores/admin/teaching/Import116Store'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import CourseStudentNotes from './CourseStudentNotes.vue'
import CourseStudentIndicators from './CourseStudentIndicators.vue'
import CourseStudentPerformance from './CourseStudentPerformance.vue'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'
import { useCourseStudentCategoryEvaluationStore } from '@/stores/admin/teaching/CourseStudentCategoryEvaluationStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox, ItsMenuButton, CourseStudentNotes, CourseStudentIndicators, CourseStudentPerformance },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.import116Store = useImport116Store()
        this.courseStore = useCourseStore()
        this.students_sort_mode = this.courseStore?.students_sort_mode || this.students_sort_mode
        this.courseDateStore = useCourseDateStore()
        this.entryStore = useCourseStudentEntryStore()
        this.behaviourEntryStore = useCourseBehaviourEntryStore()
        this.courseWorkStore = useCourseWorkStore()
        this.categoryEvaluationStore = useCourseStudentCategoryEvaluationStore()
        this.schoolHourStore = useSchoolHourStore()
        this.teachingStore = useTeachingStore()
        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
        }
        if (!Array.isArray(this.school_hours) || this.school_hours.length === 0) {
            await this.schoolHourStore.index()
        }
        this.activeSemester = Number(this.config?.user?.teaching_active_semester) || 1
        if (this.selected_course?.id) {
            await this.loadStudentPerformance(this.selected_course.id)
        }
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            import116Store: null,
            courseStore: null,
            courseDateStore: null,
            entryStore: null,
            behaviourEntryStore: null,
            courseWorkStore: null,
            categoryEvaluationStore: null,
            performanceLoading: true,
            performanceLoadFailed: false,
            performanceRequestId: 0,
            performanceData: { entries: [], behaviourEntries: [], works: [], evaluations: [] },
            schoolHourStore: null,
            teachingStore: null,
            is_valid: false,
            data: {
                selected_classes: [],
            },

            delete_level: 0,
            show_bulk_entry: false,
            students_view_mode: 'students',
            bulk_entry_form: this.emptyBulkEntryForm(),
            bulk_entry_saving: false,
            activeSemester: null,
            presence_date_id: null,
            presence_by_student: {},
            savingAttendance: false,
            hasUnsavedAttendanceChanges: false,
            students_sort_mode: 'last_name_first_name',
            copiedEmailId: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useImport116Store, ['import116_students']),
        ...mapWritableState(useCourseStore, ['courses', 'classes', 'selected_course', 'selected_course_id', 'selected_course_student', 'show_students', 'uses_entry_areas_for_grading_schema']),
        ...mapWritableState(useCourseDateStore, ['selected_courseDate']),
        ...mapWritableState(useSchoolHourStore, ['school_hours']),
        ...mapWritableState(useTeachingStore, ['settings']),
        schoolHoursByHour() {
            const entries = Array.isArray(this.school_hours) ? this.school_hours : []

            return entries.reduce((carry, item) => {
                const hour = Number(item?.hour)
                if (!Number.isFinite(hour)) {
                    return carry
                }

                carry[hour] = item
                return carry
            }, {})
        },
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
        semesterCount() {
            const grading = this.selectedCourseSchema?.grading || {}
            return Number(grading.semester_count) || 1
        },
        teachingWorks() {
            const assignedEntryArea = this.selected_course?.teaching_entry_area || null
            if (this.uses_entry_areas_for_grading_schema && assignedEntryArea?.id) {
                return (Array.isArray(assignedEntryArea.entry_definitions) ? assignedEntryArea.entry_definitions : [])
                    .filter((definition) => definition?.category === 'Benotung' && definition?.short_name)
            }

            return Array.isArray(this.selectedCourseSchema?.works) ? this.selectedCourseSchema.works : []
        },
        workTypeItems() {
            return this.teachingWorks.map((work) => ({
                title: work.name ? `${work.short_name} - ${work.name}` : work.short_name,
                value: work.short_name,
            }))
        },
        usesNewBulkEntryDefinitions() {
            return Boolean(this.uses_entry_areas_for_grading_schema && this.selected_course?.teaching_entry_area?.id)
        },
        selectedBulkTypeName() {
            const selected = this.bulk_entry_form.type
            if (!selected) return ''
            const work = this.teachingWorks.find((w) => w.short_name === selected)
            return work?.name || ''
        },
        gradeItemsForType() {
            const selectedType = this.bulk_entry_form.type
            if (!selectedType) return []
            const work = this.teachingWorks.find((w) => w.short_name === selectedType)

            if (this.usesNewBulkEntryDefinitions) {
                if (!work?.has_properties || work.properties_mode !== 'fixed') return []

                return (Array.isArray(work.fixed_properties) ? work.fixed_properties : [])
                    .map((property) => String(property || '').trim())
                    .filter(Boolean)
                    .map((property) => ({ title: property, value: property }))
            }

            const grades = work?.grades || []
            return grades.map((grade) => ({
                title: grade.name ? `${grade.grade} (${grade.name})` : grade.grade,
                value: grade.grade,
            }))
        },
        bulkGradeInputMode() {
            if (!this.bulk_entry_form.type) return 'none'
            if (!this.usesNewBulkEntryDefinitions) return 'fixed'

            const definition = this.teachingWorks.find((work) => work.short_name === this.bulk_entry_form.type)
            if (!definition?.has_properties) return 'none'

            return definition.properties_mode === 'fixed' ? 'fixed' : 'free'
        },
        bulkGradeLabel() {
            return this.usesNewBulkEntryDefinitions ? 'Eigenschaft' : 'Note'
        },
        studentItems() {
            const list = this.selected_course?.students_info || []
            return [...list]
                .sort((a, b) => this.compareStudentsBySelectedSort(a, b))
                .map((student) => ({
                    title: this.studentLabel(student),
                    value: student.id,
                }))
        },
        hasStudents() {
            return (this.selected_course?.students_info || []).length > 0
        },
        bulkEntryEnabled() {
            if (!this.hasStudents) return false
            if (!this.bulk_entry_form.student_ids?.length) return false
            const type = (this.bulk_entry_form.type || '').toString().trim()
            const grade = (this.bulk_entry_form.grade || '').toString().trim()
            const desc = (this.bulk_entry_form.description || '').toString().trim()
            if (type && this.bulkGradeInputMode !== 'none' && !grade) return false
            return !!(type || desc)
        },
        filteredImport116Students() {
            const list = this.import116_students || []
            const selected = this.selected_course?.students_info || []
            if (!selected.length) return list

            const selectedEmails = new Set(selected.map((student) => (student.email || '').toString().trim().toLowerCase()).filter((email) => email))

            if (!selectedEmails.size) return list

            return list.filter((student) => {
                const email = (student.email || '').toString().trim().toLowerCase()
                return !email || !selectedEmails.has(email)
            })
        },
        schoolSem2StartDate() {
            return this.config?.selected_schoolyear?.sem_2_start || null
        },
        canEditAttendance() {
            const schoolyearLabel = this.config?.selected_schoolyear?.concerns
                || this.config?.selected_schoolyear?.name
                || ''
            const schoolyearMatch = String(schoolyearLabel).match(/(\d{4})\/(\d{2}|\d{4})/)

            return !schoolyearMatch || Number(schoolyearMatch[1]) < 2026
        },
        countSem2StartDate() {
            return this.schoolSem2StartDate || this.config?.user?.teaching_count_for_semester_2_date || null
        },
        studentBehaviourCounts() {
            let entries = (this.behaviourEntryStore?.courseEntries || []).filter((entry) => (entry.kind || 'behaviour') === 'behaviour')
            if (this.semesterCount === 2 && this.activeSemester !== 3) {
                const boundary = this.normalizeDateKey(this.schoolSem2StartDate || this.countSem2StartDate)
                if (boundary) {
                    entries = entries.filter((e) => {
                        if (!e.date) return true
                        const d = this.normalizeDateKey(e.date)
                        if (!d) return true
                        if (this.activeSemester === 1) return d < boundary
                        if (this.activeSemester === 2) return d >= boundary
                        return true
                    })
                }
            }
            const result = {}
            entries.forEach((entry) => {
                if (!result[entry.user_id]) result[entry.user_id] = {}
                const type = entry.type || '?'
                result[entry.user_id][type] = (result[entry.user_id][type] || 0) + 1
            })
            return result
        },
        studentOpenNotificationCounts() {
            let entries = (this.behaviourEntryStore?.courseEntries || []).filter((entry) => entry.kind === 'notification' && !!entry.due_date && !entry.done_date)
            if (this.semesterCount === 2 && this.activeSemester !== 3) {
                const boundary = this.normalizeDateKey(this.schoolSem2StartDate || this.countSem2StartDate)
                if (boundary) {
                    entries = entries.filter((e) => {
                        if (!e.date) return true
                        const d = this.normalizeDateKey(e.date)
                        if (!d) return true
                        if (this.activeSemester === 1) return d < boundary
                        if (this.activeSemester === 2) return d >= boundary
                        return true
                    })
                }
            }
            const result = {}
            entries.forEach((entry) => {
                result[entry.user_id] = (result[entry.user_id] || 0) + 1
            })
            return result
        },
        studentOpenNotificationDueDates() {
            let entries = (this.behaviourEntryStore?.courseEntries || []).filter((entry) => entry.kind === 'notification' && !!entry.due_date && !entry.done_date)
            if (this.semesterCount === 2 && this.activeSemester !== 3) {
                const boundary = this.normalizeDateKey(this.schoolSem2StartDate || this.countSem2StartDate)
                if (boundary) {
                    entries = entries.filter((e) => {
                        if (!e.date) return true
                        const d = this.normalizeDateKey(e.date)
                        if (!d) return true
                        if (this.activeSemester === 1) return d < boundary
                        if (this.activeSemester === 2) return d >= boundary
                        return true
                    })
                }
            }

            const result = {}
            entries.forEach((entry) => {
                const due = this.normalizeDateKey(entry.due_date)
                if (!due) return
                if (!result[entry.user_id] || due < result[entry.user_id]) {
                    result[entry.user_id] = due
                }
            })
            return result
        },
        sortedSelectedStudents() {
            const list = this.selected_course?.students_info || []
            return [...list]
                .filter((student) => !this.isStudentCanceled(student))
                .sort((a, b) => this.compareStudentsBySelectedSort(a, b))
        },
        activeStudentsCount() {
            const list = this.selected_course?.students_info || []
            const activeIds = new Set()
            list.forEach((student) => {
                if (!student?.id) return
                if (this.isStudentCanceled(student)) return
                if (student?.deleted_at) return
                activeIds.add(String(student.id))
            })
            return activeIds.size
        },
        isDayOverviewMode() {
            return this.students_view_mode === 'day_overview'
        },
        selectedCourseDateForCourse() {
            const selectedDate = this.selected_courseDate
            const selectedCourse = this.selected_course
            if (!selectedDate?.id || !selectedCourse?.id) return null
            const courseDates = selectedCourse.course_dates || []
            // If course_dates is populated, validate membership; otherwise trust selected_courseDate
            if (courseDates.length) {
                const exists = courseDates.some((d) => String(d?.id) === String(selectedDate.id))
                return exists ? selectedDate : null
            }
            return selectedDate
        },
        selectedCourseDateKey() {
            return this.normalizeDateKey(this.selectedCourseDateForCourse?.date)
        },
        dayOverviewStudents() {
            if (!teachingCourseMatchesSchoolyear(this.selected_course, this.config?.selected_schoolyear)) return []
            const dateKey = this.selectedCourseDateKey
            if (!dateKey) return []
            if (teachingPerformanceDateScope(dateKey, this.activeSemester, this.countSem2StartDate, this.config?.selected_schoolyear) !== 'included') return []

            const entriesByStudent = {}
            const studentEntries = this.entryStore?.courseEntries || []
            const behaviourEntries = this.behaviourEntryStore?.courseEntries || []

            studentEntries.forEach((entry) => {
                if (!entry?.user_id || this.normalizeDateKey(entry.date) !== dateKey) return
                if (!entriesByStudent[entry.user_id]) entriesByStudent[entry.user_id] = []
                entriesByStudent[entry.user_id].push({
                    uid: `se-${entry.id}`,
                    kind: 'student_entry',
                    type: entry.type,
                    grade: entry.grade,
                    effective_grade: entry.effective_grade,
                    description: entry.description,
                    source: entry.source || 'manual',
                })
            })

            behaviourEntries.forEach((entry) => {
                if (!entry?.user_id || this.normalizeDateKey(entry.date) !== dateKey) return
                if (!entriesByStudent[entry.user_id]) entriesByStudent[entry.user_id] = []
                entriesByStudent[entry.user_id].push({
                    uid: `be-${entry.id}`,
                    kind: entry.kind || 'behaviour',
                    type: entry.type,
                    description: entry.description,
                    due_date: entry.due_date,
                    done_date: entry.done_date,
                })
            })

            return this.sortedSelectedStudents
                .map((student) => ({
                    student,
                    entries: (entriesByStudent[student.id] || []).sort((a, b) => {
                        const kindA = a.kind || ''
                        const kindB = b.kind || ''
                        if (kindA !== kindB) return kindA.localeCompare(kindB)
                        const typeA = (a.type || '').toString()
                        const typeB = (b.type || '').toString()
                        return typeA.localeCompare(typeB, 'de', { sensitivity: 'base' })
                    }),
                }))
                .filter((item) => item.entries.length > 0)
        },
        sortedCourseDates() {
            const dates = Array.isArray(this.selected_course?.course_dates) ? [...this.selected_course.course_dates] : []
            return dates
                .filter((d) => !!this.normalizeDateKey(d?.date))
                .sort((a, b) => {
                    const da = this.normalizeDateKey(a?.date)
                    const db = this.normalizeDateKey(b?.date)
                    if (da !== db) return da.localeCompare(db)
                    const ha = Array.isArray(a?.hours) ? Math.min(...a.hours.map((h) => Number(h)).filter((h) => Number.isFinite(h))) : 999
                    const hb = Array.isArray(b?.hours) ? Math.min(...b.hours.map((h) => Number(h)).filter((h) => Number.isFinite(h))) : 999
                    return ha - hb
                })
        },
        selectedCourseDateIndex() {
            if (!this.selectedCourseDateForCourse?.id) return -1
            return this.sortedCourseDates.findIndex((d) => String(d?.id) === String(this.selectedCourseDateForCourse.id))
        },
        hasPrevCourseDate() {
            return this.selectedCourseDateIndex > 0
        },
        hasNextCourseDate() {
            return this.selectedCourseDateIndex >= 0 && this.selectedCourseDateIndex < this.sortedCourseDates.length - 1
        },
        attendanceCheckedForSelectedDate() {
            const courseDate = this.selectedCourseDateForCourse
            if (!courseDate) return false
            if (typeof courseDate.attendance_checked === 'boolean') return courseDate.attendance_checked
            const status = Array.isArray(courseDate.status) ? courseDate.status : []
            return status.includes('att_checked:1')
        },
        selectedCourseDateLabel() {
            const courseDate = this.selectedCourseDateForCourse
            if (!courseDate) return ''
            const weekday = this.getWeekdayShort(courseDate.date)
            const date = this.formatDateShort(courseDate.date)
            const hours = this.formatCourseDateHoursCompact(courseDate.hours)
            const timeRange = this.formatCourseDateTimeRange(courseDate.hours)
            const base = weekday && date && hours
                ? `${weekday}, ${date} - ${hours}`
                : (date && hours ? `${date} - ${hours}` : (date || hours || ''))

            if (base && timeRange) {
                return `${base} (${timeRange})`
            }

            return base
        },
    },

    watch: {
        'config.selected_schoolyear.id'(value, previous) {
            if (value === previous) return
            this.performanceRequestId++
            this.performanceData = { entries: [], behaviourEntries: [], works: [], evaluations: [] }
            this.performanceLoading = true
            this.courseStore?.index()
        },
        show_bulk_entry(value, previous) {
            if (previous && !value && this.selected_course?.id) {
                this.loadStudentPerformance(this.selected_course.id)
            }
        },
        activeSemester(val) {
            if (val !== this.config?.user?.teaching_active_semester) {
                this.teachingStore.saveActiveSemester(val)
            }
        },
        'config.user.teaching_active_semester'(val) {
            if (val) this.activeSemester = Number(val) || 1
        },
        selected_course: {
            handler(course) {
                if (course?.id) {
                    this.loadStudentPerformance(course.id)
                    if (this.selected_courseDate?.id) {
                        const dates = Array.isArray(course.course_dates) ? [...course.course_dates] : []
                        const idx = dates.findIndex((d) => String(d?.id) === String(this.selected_courseDate.id))
                        if (idx !== -1) {
                            const fresh = dates[idx] || null
                            this.selected_courseDate = fresh
                            if (fresh) {
                                this.syncPresenceMapFromDate(fresh)
                            }
                        }
                        // If not found in course_dates, keep the existing selected_courseDate —
                        // course_dates may not be fully loaded yet (URL restoration timing)
                    }
                } else {
                    this.behaviourEntryStore.courseEntries = []
                    this.entryStore.courseEntries = []
                    this.selected_courseDate = null
                    this.presence_date_id = null
                    this.presence_by_student = {}
                    this.students_view_mode = 'students'
                }
            },
        },
        selected_courseDate: {
            handler(val, oldVal) {
                if (!val?.id) {
                    this.students_view_mode = 'students'
                    return
                }
                this.syncPresenceMapFromDate(val)
                // Clear unsaved flag only when switching to a different date
                if (String(val.id) !== String(oldVal?.id)) {
                    this.hasUnsavedAttendanceChanges = false
                }
            },
            deep: false,
        },
        'data.classes': {
            handler(newClasses) {
                if (this.action !== 'teaching_course_new_or_edit') return
                if (!Array.isArray(newClasses)) return
                this.selectStudents(newClasses)
            },
            deep: true,
        },
        'bulk_entry_form.date'(val) {
            if (val && val instanceof Date) {
                this.bulk_entry_form.date = this.toDateString(val)
            }
        },
        students_sort_mode(val) {
            if (!this.courseStore) {
                return
            }

            this.courseStore.students_sort_mode = val || 'last_name_first_name'
        },
    },

    methods: {
        studentForSelectedSemester(student) {
            const schoolyear = this.config?.selected_schoolyear
            const matchesSchoolyear = teachingCourseMatchesSchoolyear(this.selected_course, schoolyear)
            return {
                ...student,
                user_id: matchesSchoolyear ? student.user_id : null,
                stars: matchesSchoolyear ? (student.stars || []).filter((star) => teachingStarDateScope(star.date, this.activeSemester, this.countSem2StartDate) === 'included') : [],
            }
        },
        async loadStudentPerformance(courseId) {
            const requestId = ++this.performanceRequestId
            this.performanceLoading = true
            this.performanceLoadFailed = false
            const results = await Promise.allSettled([
                this.behaviourEntryStore.indexByCourse(courseId).then((success) => ({ success, data: this.behaviourEntryStore.courseEntries || [] })),
                this.entryStore.indexByCourse(courseId).then((success) => ({ success, data: this.entryStore.courseEntries || [] })),
                this.courseWorkStore.index(courseId).then((success) => ({ success, data: this.courseWorkStore.courseWorks || [] })),
                this.categoryEvaluationStore.indexByCourse(courseId).then((success) => ({ success, data: this.categoryEvaluationStore.evaluations || [] })),
            ])
            if (requestId !== this.performanceRequestId) return
            this.performanceLoadFailed = results.some((result) => result.status === 'rejected' || !result.value.success)
            if (!this.performanceLoadFailed) {
                const [behaviourEntries, entries, works, evaluations] = results.map((result) => result.value.data)
                this.performanceData = { behaviourEntries, entries, works, evaluations }
            }
            this.performanceLoading = false
        },
        async copyEmail(student) {
            const email = this.studentEmailText(student)
            if (!email) return
            try {
                await navigator.clipboard.writeText(email)
                this.copiedEmailId = student.id
                setTimeout(() => { this.copiedEmailId = null }, 1500)
            } catch {
            }
        },
        normalizeDateKey(date) {
            if (!date) return ''
            // Keep pure date strings as-is; parse date-time strings in local time.
            if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
                return date.slice(0, 10)
            }
            const parsed = parseLocalDate(date)
            if (Number.isNaN(parsed.getTime())) return ''
            return this.toDateString(parsed)
        },
        emptyBulkEntryForm() {
            return {
                student_ids: [],
                type: '',
                grade: '',
                date: this.toDateString(new Date()),
                description: '',
            }
        },
        toggleBulkEntry() {
            this.show_bulk_entry = !this.show_bulk_entry
            if (this.show_bulk_entry) {
                this.bulk_entry_form = this.emptyBulkEntryForm()
            }
        },
        toggleStudentsViewMode() {
            if (!this.selectedCourseDateForCourse) {
                this.students_view_mode = 'students'
                return
            }
            this.students_view_mode = this.isDayOverviewMode ? 'students' : 'day_overview'
            if (this.students_view_mode === 'day_overview') {
                this.show_bulk_entry = false
            }
        },
        dayEntryShortLabel(entry) {
            if (!entry) return 'Eintrag'
            if (entry.kind === 'student_entry') return 'Leistung'
            if (entry.kind === 'notification') return 'Erinnerung'
            if (entry.kind === 'behaviour') return 'Verhalten'
            return 'Eintrag'
        },
        dayEntryLabel(entry) {
            if (!entry) return ''
            const base = entry.type ? `${this.dayEntryShortLabel(entry)}: ${entry.type}` : this.dayEntryShortLabel(entry)

            if (entry.kind === 'student_entry') {
                const grade = (entry.effective_grade || entry.grade || '').toString().trim()
                if (grade) {
                    return `${base} (${grade})`
                }
            }

            if (entry.kind === 'notification') {
                const due = this.normalizeDateKey(entry.due_date)
                const done = this.normalizeDateKey(entry.done_date)
                if (done) return `${base} (erledigt)`
                if (due) return `${base} (fällig ${this.formatDate(due)})`
            }

            return base
        },
        dayEntryColor(entry) {
            if (!entry) return 'primary'
            if (entry.kind === 'student_entry') return 'success'
            if (entry.kind === 'notification') return entry.done_date ? 'success' : 'warning'
            if (entry.kind === 'behaviour') return 'warning'
            return 'primary'
        },
        cancelBulkEntry() {
            this.show_bulk_entry = false
            this.bulk_entry_form = this.emptyBulkEntryForm()
        },
        async saveBulkEntry() {
            if (this.bulk_entry_saving || !this.selected_course) return
            const allIds = (this.selected_course?.students_info || []).map((s) => s.id)
            const targetIds = this.bulk_entry_form.student_ids.length ? this.bulk_entry_form.student_ids : allIds
            if (!targetIds.length) return

            this.bulk_entry_saving = true

            try {
                await this.$nextTick?.()

                const date = this.bulk_entry_form.date instanceof Date ? this.toDateString(this.bulk_entry_form.date) : this.bulk_entry_form.date
                const basePayload = {
                    teaching_course_id: this.selected_course.id,
                    type: this.bulk_entry_form.type,
                    grade: this.bulk_entry_form.grade,
                    date,
                    description: this.bulk_entry_form.description,
                }

                let ok = true
                for (const userId of targetIds) {
                    const result = await this.entryStore.store({ ...basePayload, user_id: userId })
                    if (!result) ok = false
                }

                if (ok) {
                    this.cancelBulkEntry()
                }
            } finally {
                this.bulk_entry_saving = false
            }
        },
        selectAllBulkStudents() {
            this.bulk_entry_form.student_ids = (this.selected_course?.students_info || []).map((s) => s.id)
        },
        clearBulkStudents() {
            this.bulk_entry_form.student_ids = []
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
            const lastA = (a?.last_name || '').toString()
            const lastB = (b?.last_name || '').toString()
            const firstA = (a?.first_name || '').toString()
            const firstB = (b?.first_name || '').toString()
            const classA = this.studentClassValue(a)
            const classB = this.studentClassValue(b)

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
        toDateString(date) {
            const d = parseLocalDate(date)
            const year = d.getFullYear()
            const month = String(d.getMonth() + 1).padStart(2, '0')
            const day = String(d.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
        formatDate(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        formatCanceledAt(value) {
            if (!value) return ''
            const d = parseLocalDate(value)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        isStudentCanceled(student) {
            return !!student?.canceled_at || !!student?.deleted_at
        },
        studentNameClass(student) {
            return this.isStudentCanceled(student) ? 'student-name--canceled' : ''
        },
        studentEmailText(student) {
            return typeof student?.email === 'string' && student.email.trim() !== '' ? student.email.trim() : ''
        },
        studentLastLoginText(student) {
            return typeof student?.login_at === 'string' && student.login_at.trim() !== ''
                ? student.login_at.trim()
                : ''
        },
        studentBirthDetails(student) {
            const birthDate = this.formatDate(student?.birth_date)
            if (!birthDate) return ''

            const hasAge = student?.age !== null && student?.age !== undefined && student?.age !== ''
            const age = hasAge ? Number(student.age) : null

            return Number.isFinite(age) && age >= 0
                ? `${birthDate} · ${age} Jahre`
                : birthDate
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
        getWeekday(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { weekday: 'long' })
        },
        getWeekdayShort(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            const label = d.toLocaleDateString('de-DE', { weekday: 'short' })
            return label.replace(/\.$/, '')
        },
        formatDateShort(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
        },
        formatCourseDateHoursCompact(hours) {
            const sortedHours = Array.isArray(hours)
                ? [...hours]
                    .map((h) => Number(h))
                    .filter((h) => Number.isFinite(h))
                    .sort((a, b) => a - b)
                : []
            if (!sortedHours.length) return ''

            const labels = []
            let rangeStart = sortedHours[0]
            let rangeEnd = sortedHours[0]

            const pushRange = () => {
                if (rangeStart === rangeEnd) {
                    labels.push(`${rangeStart}. Std`)
                    return
                }

                labels.push(`${rangeStart}.-${rangeEnd}. Std`)
            }

            for (let i = 1; i < sortedHours.length; i++) {
                const current = sortedHours[i]
                if (current <= rangeEnd + 1) {
                    rangeEnd = current
                    continue
                }

                pushRange()
                rangeStart = current
                rangeEnd = current
            }

            pushRange()

            return labels.join(', ')
        },
        formatCourseDateTimeRange(hours) {
            const sortedHours = Array.isArray(hours)
                ? [...hours]
                    .map((h) => Number(h))
                    .filter((h) => Number.isFinite(h))
                    .sort((a, b) => a - b)
                : []
            if (!sortedHours.length) return ''

            const firstHourConfig = this.schoolHoursByHour[sortedHours[0]]
            const lastHourConfig = this.schoolHoursByHour[sortedHours[sortedHours.length - 1]]
            const from = this.formatTimeShort(firstHourConfig?.from)
            const until = this.formatTimeShort(lastHourConfig?.until)
            if (!from || !until) return ''

            return `${from}-${until}`
        },
        formatTimeShort(value) {
            const raw = (value || '').toString().trim()
            if (!raw) return ''
            return raw.slice(0, 5)
        },
        normalizeCourseDatePayload(response) {
            if (!response) return null
            return response?.data || response
        },
        applyUpdatedCourseDate(updatedDate) {
            if (!updatedDate?.id) return
            this.selected_courseDate = updatedDate
            this.syncPresenceMapFromDate(updatedDate)
            if (this.selected_course?.id) {
                const dates = Array.isArray(this.selected_course.course_dates) ? [...this.selected_course.course_dates] : []
                const idx = dates.findIndex((d) => String(d?.id) === String(updatedDate.id))
                if (idx >= 0) {
                    dates.splice(idx, 1, { ...dates[idx], ...updatedDate })
                } else {
                    dates.push(updatedDate)
                }
                this.selected_course.course_dates = dates

                const courseIdx = (this.courses || []).findIndex((course) => String(course?.id) === String(this.selected_course.id))
                if (courseIdx >= 0) {
                    const course = { ...this.courses[courseIdx] }
                    const courseDates = Array.isArray(course.course_dates) ? [...course.course_dates] : []
                    const dateIdx = courseDates.findIndex((d) => String(d?.id) === String(updatedDate.id))
                    if (dateIdx >= 0) {
                        courseDates.splice(dateIdx, 1, { ...courseDates[dateIdx], ...updatedDate })
                    } else {
                        courseDates.push(updatedDate)
                    }
                    course.course_dates = courseDates
                    this.courses.splice(courseIdx, 1, course)
                    if (String(this.selected_course_id) === String(course.id)) {
                        this.selected_course = course
                    }
                }
            }
        },
        studentAttendanceStateForSelectedDate(studentId) {
            if (!this.selectedCourseDateForCourse) return null
            const attendance = this.getAttendanceMap(this.selectedCourseDateForCourse)
            const key = String(studentId)
            if (Object.prototype.hasOwnProperty.call(attendance, key)) return attendance[key]

            return this.attendanceCheckedForSelectedDate ? true : null
        },
        isStudentPresentForSelectedDate(studentId) {
            return this.studentAttendanceStateForSelectedDate(studentId) === true
        },
        syncPresenceMapFromDate(courseDate) {
            if (!courseDate?.id) return
            const dateId = String(courseDate.id)
            const attendance = this.getAttendanceMap(courseDate)
            const map = {}
            Object.entries(attendance).forEach(([id, value]) => {
                const key = String(id || '').trim()
                if (!key) return
                map[key] = value
            })
            this.presence_date_id = dateId
            this.presence_by_student = map
        },
        getAttendanceMap(courseDate) {
            if (!courseDate) return {}
            if (courseDate.attendance && typeof courseDate.attendance === 'object') {
                const normalized = this.normalizeIndexedAttendance(courseDate.attendance)
                const unprefixed = {}
                // Strip 's_' prefix from keys
                Object.entries(normalized).forEach(([key, value]) => {
                    const cleanKey = key.startsWith('s_') ? key.substring(2) : key
                    unprefixed[cleanKey] = value
                })
                return this.sanitizeAttendanceMap(unprefixed)
            }
            const status = Array.isArray(courseDate.status) ? courseDate.status : []
            const attendance = {}
            status.forEach((item) => {
                if (typeof item !== 'string' || !item.startsWith('att:')) return
                const parts = item.split(':')
                if (parts.length < 3) return
                const studentId = (parts[1] || '').toString().trim()
                const present = (parts[2] || '').toString().trim()
                if (!studentId) return
                attendance[studentId] = present === 'null' ? null : ['1', 'true'].includes(present)
            })
            return this.sanitizeAttendanceMap(attendance)
        },
        normalizeIndexedAttendance(attendanceLike) {
            const input = attendanceLike && typeof attendanceLike === 'object' ? attendanceLike : {}
            const entries = Object.entries(input)
            if (!entries.length) return {}

            // If attendance arrives as indexed keys (0,1,2,...) remap to real student ids by course student order.
            const hasOnlySmallIndexes = entries.every(([k]) => /^\d+$/.test(String(k)) && Number(k) >= 0 && Number(k) <= 200)
            if (!hasOnlySmallIndexes) return input

            const studentIds = Array.isArray(this.selected_course?.students)
                ? this.selected_course.students.map((id) => String(id)).filter((id) => id && id !== 'undefined' && id !== 'null')
                : []
            if (!studentIds.length) return input

            const remapped = {}
            entries.forEach(([idxRaw, value]) => {
                const idx = Number(idxRaw)
                const studentId = studentIds[idx]
                if (!studentId) return
                remapped[String(studentId)] = value
            })
            return remapped
        },
        sanitizeAttendanceMap(attendanceLike) {
            const input = attendanceLike && typeof attendanceLike === 'object' ? attendanceLike : {}
            const validIds = new Set(
                (this.selected_course?.students_info || [])
                    .flatMap((s) => [s?.id, s?.user_id, s?.import116_id])
                    .map((id) => String(id))
                    .filter((id) => id && id !== 'undefined' && id !== 'null')
            )
            const hasKnownStudents = validIds.size > 0
            const sanitized = {}
            Object.entries(input).forEach(([studentId, value]) => {
                const key = String(studentId || '').trim()
                if (!key) return
                const isNumeric = /^\d+$/.test(key)
                const numericKey = isNumeric ? Number(key) : null
                if (hasKnownStudents) {
                    if (!validIds.has(key)) {
                        // Keep large numeric IDs and non-numeric IDs to avoid dropping valid students from partial course payloads.
                        if (isNumeric && Number.isFinite(numericKey) && numericKey >= 1000) {
                            // keep
                        } else if (!isNumeric) {
                            // keep
                        } else {
                            return
                        }
                    }
                } else {
                    // Ignore obvious positional array indexes from legacy payloads.
                    if (isNumeric && Number.isFinite(numericKey) && numericKey >= 0 && numericKey <= 60) return
                }
                sanitized[key] = value === null ? null : this.isAttendancePresentValue(value)
            })
            return sanitized
        },
        buildStatusWithAttendanceMeta(baseStatus, attendance, attendanceChecked) {
            const publicStatus = Array.isArray(baseStatus)
                ? [...baseStatus].filter((item) => ['free', 'pruefung'].includes(item))
                : []
            const merged = [...publicStatus]
            Object.entries(attendance || {}).forEach(([studentId, present]) => {
                const key = String(studentId || '').trim()
                if (!key) return
                merged.push(`att:${key}:${present === null ? 'null' : this.isAttendancePresentValue(present) ? '1' : '0'}`)
            })
            if (attendanceChecked) merged.push('att_checked:1')
            return [...new Set(merged)]
        },
        isAttendancePresentValue(value) {
            if ([true, 1, '1', 'true'].includes(value)) return true
            if (value === false) return false
            if (value === 0) return false
            if (value === '0') return false
            if (value === 'false') return false
            return null
        },
        async persistAttendance(attendance, attendanceChecked) {
            const date = this.selectedCourseDateForCourse
            if (!date?.id) return false
            const cleanAttendance = this.sanitizeAttendanceMap(attendance)
            const payload = {
                attendance: cleanAttendance,
                attendance_checked: !!attendanceChecked,
            }
            let response = await this.courseDateStore.updateStatus(date.id, payload)
            let updatedDate = this.normalizeCourseDatePayload(response)
            if (!updatedDate?.id) {
                // Backend compatibility fallback: persist attendance via status field on update endpoint.
                const mergedStatus = this.buildStatusWithAttendanceMeta(date.status, cleanAttendance, attendanceChecked)
                response = await this.courseDateStore.update({
                    id: date.id,
                    date: date.date,
                    status: mergedStatus,
                })
                updatedDate = this.normalizeCourseDatePayload(response)
            }
            if (updatedDate?.id) {
                const normalizedStatus = Array.isArray(updatedDate.status)
                    ? updatedDate.status.filter((item) => typeof item === 'string' && ['free', 'pruefung'].includes(item))
                    : []
                this.applyUpdatedCourseDate({
                    ...updatedDate,
                    status: normalizedStatus,
                    attendance: this.getAttendanceMap(updatedDate),
                    attendance_checked: typeof updatedDate.attendance_checked === 'boolean'
                        ? updatedDate.attendance_checked
                        : !!(Array.isArray(updatedDate.status) && updatedDate.status.includes('att_checked:1')),
                })
                return updatedDate
            }
            return false
        },
        toggleStudentPresence(student) {
            if (!this.canEditAttendance || !student?.id || !this.selectedCourseDateForCourse) return
            const date = this.selectedCourseDateForCourse
            const attendance = this.getAttendanceMap(date)
            const key = String(student.id)
            const currentState = this.studentAttendanceStateForSelectedDate(student.id)

            // Toggle locally only - no API call
            attendance[key] = currentState === null ? false : currentState === false ? true : null

            // Update only selected_courseDate (minimal state change, no watchers triggered)
            this.selected_courseDate = {
                ...date,
                attendance: attendance,
            }

            // Mark as unsaved
            this.hasUnsavedAttendanceChanges = true
        },
        async toggleAttendanceChecked() {
            if (!this.canEditAttendance || !this.selectedCourseDateForCourse || this.savingAttendance) return
            const date = this.selectedCourseDateForCourse
            const attendance = { ...this.getAttendanceMap(date) }

            // If there are unsaved changes, always save and mark as checked
            // If no unsaved changes, toggle the checked state
            const nextChecked = this.hasUnsavedAttendanceChanges ? true : !this.attendanceCheckedForSelectedDate

            this.savingAttendance = true
            try {
                await this.persistAttendance(attendance, nextChecked)
                this.hasUnsavedAttendanceChanges = false
            } finally {
                this.savingAttendance = false
            }
        },
        dueDateColor(date) {
            const due = parseLocalDate(date)
            if (isNaN(due.getTime())) return 'warning'
            due.setHours(0, 0, 0, 0)
            const today = new Date()
            today.setHours(0, 0, 0, 0)
            return due <= today ? 'error' : 'warning'
        },
        async selectStudents(classes) {
            if (!Array.isArray(classes) || !classes.length) {
                this.import116_students = []
                return
            }
            await this.import116Store.loadClassStudents(classes)
        },

        addAllStudents() {
            if (!this.selected_course) return
            if (!Array.isArray(this.import116_students) || !this.import116_students.length) return

            this.import116_students.slice().forEach((student) => {
                this.addStudent(student)
            })
        },

        removeAllStudents() {
            if (!this.selected_course) return
            this.courseStore.ensureCourseStudentCollections(this.selected_course)

            if (!Array.isArray(this.selected_course.students_info) || !this.selected_course.students_info.length) return

            this.selected_course.students_info.slice().forEach((student) => {
                this.removeStudent(student)
            })
        },

        addStudent(student) {
            if (!this.selected_course) return

            this.courseStore.ensureCourseStudentCollections(this.selected_course)

            const email = (student.email || '').toString().trim().toLowerCase()

            // Check for duplicates by ID (with type conversion) and by email
            const isDuplicateById = this.selected_course.students.some(id => String(id) === String(student.id))
            const isDuplicateByEmail = email && this.selected_course.students_info.some(s => {
                const existingEmail = (s.email || '').toString().trim().toLowerCase()
                return existingEmail && existingEmail === email
            })

            if (!isDuplicateById && !isDuplicateByEmail) {
                this.selected_course.students.push(student.id)
                this.selected_course.students_info.push(student)
            }

            if (this.selected_course.students_deleted.length) {
                if (email) {
                    const remaining = []
                    this.selected_course.students_deleted_info = this.selected_course.students_deleted_info.filter((s) => {
                        const deletedEmail = (s.email || '').toString().trim().toLowerCase()
                        const keep = !deletedEmail || deletedEmail !== email
                        if (keep && s.id) remaining.push(s.id)
                        return keep
                    })
                    this.selected_course.students_deleted = remaining.length ? remaining : this.selected_course.students_deleted.filter((id) => id !== student.id)
                } else {
                    const deletedIndex = this.selected_course.students_deleted.indexOf(student.id)
                    if (deletedIndex !== -1) {
                        this.selected_course.students_deleted.splice(deletedIndex, 1)
                        this.selected_course.students_deleted_info = this.selected_course.students_deleted_info.filter((s) => s.id !== student.id)
                    }
                }
            }

            this.import116_students = this.import116_students.filter((s) => s.id !== student.id)
        },

        isStudentSelected(student) {
            if (!this.selected_course) return false
            this.courseStore.ensureCourseStudentCollections(this.selected_course)
            return this.selected_course.students.includes(student.id)
        },

        removeStudent(student) {
            if (!this.selected_course) return
            this.courseStore.ensureCourseStudentCollections(this.selected_course)

            const index = this.selected_course.students.indexOf(student.id)
            if (index !== -1) {
                this.selected_course.students.splice(index, 1)
                this.selected_course.students_info = this.selected_course.students_info.filter((s) => s.id !== student.id)
            }

            if (!this.selected_course.students_deleted.includes(student.id)) {
                this.selected_course.students_deleted.push(student.id)
                this.selected_course.students_deleted_info.push(student)
            }

            const email = (student.email || '').toString().trim().toLowerCase()
            const exists = this.import116_students.some((s) => {
                if (email) {
                    return (s.email || '').toString().trim().toLowerCase() === email
                }
                return s.id === student.id
            })

            if (!exists) {
                this.import116_students.push(student)
            }
        },

        async save(data) {
            const source = this.selected_course && data.id && this.selected_course.id === data.id ? this.selected_course : data
            this.courseStore.ensureCourseStudentCollections(source)

            const payload = {
                ...data,
                students: source.students || [],
                students_deleted: source.students_deleted || [],
            }

            if (data.id) {
                await this.courseStore.update(payload)
            } else {
                await this.courseStore.store(payload)
            }
            await this.courseStore.index()
            this.selected_course = this.courses.find((c) => c.id === data.id) || null
            this.selected_course = null
            this.action = ''
        },

        selectCourse(course) {
            if (this.action === 'teaching_course_new_or_edit') {
                return
            }
            if (this.selected_course != course) {
                this.courseStore.ensureCourseStudentCollections(course)
                this.selected_course = course
                this.delete_level = 0
            }
        },

        newCourse() {
            this.data = {}
            this.action = 'teaching_course_new_or_edit'
        },
        editCourse(course) {
            this.data = { ...course }
            this.selected_course = course
            this.courseStore.ensureCourseStudentCollections(this.selected_course)
            this.action = 'teaching_course_new_or_edit'
            this.selectStudents(this.data.classes || [])
        },
        abortNewCourse() {
            this.selected_course = null
            this.action = ''
        },
        async deleteCourse(course) {
            if (!(await this.courseStore.destroy(course.id))) {
                this.delete_level = 0
                return
            }
            await this.courseStore.index()
            this.selected_course = null
            this.delete_level = 0
        },
        openStudent(student) {
            if (!student) return
            this.action = ''
            this.selected_course_student = student
            this.action_2 = 'course_student_view'
        },
        openStudentFromCard(student) {
            if (this.show_bulk_entry) return

            this.openStudent(student)
        },
        selectPrevCourseDate() {
            if (!this.hasPrevCourseDate) return
            const date = this.sortedCourseDates[this.selectedCourseDateIndex - 1] || null
            this.selected_courseDate = date
            this.syncDateToUrl(date)
        },
        selectNextCourseDate() {
            if (!this.hasNextCourseDate) return
            const date = this.sortedCourseDates[this.selectedCourseDateIndex + 1] || null
            this.selected_courseDate = date
            this.syncDateToUrl(date)
        },
        syncDateToUrl(date) {
            const query = { ...this.$route.query }
            if (date?.id) {
                query.date = String(date.id)
            } else {
                delete query.date
            }
            this.$router.replace({ query }).catch(() => {})
        },
    },
}
</script>

<style scoped>
.students-grid {
    padding: 8px;
    gap: 8px;
}

.student-list-item--clickable {
    cursor: pointer;
}

.student-list-item--clickable:focus-visible {
    outline: none;
}

.student-list-item--clickable:focus-visible .student-row {
    border-color: rgba(37, 99, 235, 0.5);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2), 0 4px 12px rgba(15, 23, 42, 0.08);
}

@media (min-width: 900px) {
    .students-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 680px), 1fr));
    }
}

.student-row {
    min-width: 0;
    min-height: 64px;
    padding: 10px 12px;
    background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
    border: 1px solid rgba(37, 99, 235, 0.12);
    border-radius: 12px;
    transition: box-shadow 0.15s ease, border-color 0.15s ease;
}

.student-row:hover {
    border-color: rgba(37, 99, 235, 0.28);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
}

.student-row--canceled {
    background: linear-gradient(180deg, #fefce8 0%, #fef9c3 100%);
    border-color: rgba(234, 179, 8, 0.2);
}

.student-name--canceled {
    text-decoration: line-through;
    opacity: 0.75;
}

.selected-course-date-chip {
    min-width: 320px;
    max-width: 60%;
    min-height: 44px;
    margin-left: 12px;
    font-weight: 800;
    background-color: #ffffff !important;
    color: #0d1b2a !important;
    border-color: #2f4ea1 !important;
}

.selected-course-date-chip :deep(.v-chip__content) {
    font-size: 1rem;
    line-height: 1.4;
    white-space: normal;
    overflow-wrap: anywhere;
}

.student-name-line {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    min-width: 0;
}

.student-name-text {
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.3;
    color: #1e293b;
}

.student-stars-chip {
    flex: 0 0 auto;
}

.student-name {
    min-width: 0;
    flex: 1 1 auto;
    word-break: break-word;
}

.student-identity {
    flex: 0 1 290px;
    min-width: 0;
    max-width: 100%;
}

.student-meta-line {
    line-height: 1.4;
}

.student-metrics {
    min-width: 0;
    margin-left: 0;
    justify-content: flex-end;
    flex: 0 1 auto;
    max-width: 55%;
}

.student-presence {
    flex: 0 0 auto;
}

.student-presence--left {
    margin-left: 0;
}

@media (max-width: 600px) {
    .students-date-navigation {
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .students-date-navigation :deep(.v-btn) {
        min-height: 44px;
        min-width: 44px;
    }

    .selected-course-date-chip {
        margin-left: 0;
        min-width: 0;
        width: 100%;
    }

    .bulk-entry-footer {
        align-items: stretch !important;
        flex-direction: column;
        gap: 8px;
    }

    .bulk-entry-selection-actions {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        width: 100%;
    }

    .bulk-entry-selection-actions :deep(.v-btn),
    .bulk-entry-submit {
        min-height: 44px;
        width: 100%;
    }

    .students-header-spacer {
        display: none;
    }

    .students-sort-toggle {
        width: 100%;
        margin-top: 6px;
    }

    .students-sort-toggle-btn {
        flex: 1 1 0;
    }

    .students-bulk-btn {
        width: 100%;
        margin-top: 6px;
    }

    .students-attendance-check-btn {
        width: 100%;
        margin-top: 6px;
    }
}

.students-sort-toggle-btn {
    text-transform: none;
    letter-spacing: 0;
}

.students-attendance-check-btn--open {
    color: #1f1300 !important;
    font-weight: 700;
}

@media (min-width: 601px) {
    .student-metrics {
        width: auto;
    }
}

@media (max-width: 900px) {
    .selected-course-date-chip {
        max-width: 100%;
    }

    .student-name {
        max-width: 100%;
    }

    .student-metrics {
        max-width: 100%;
    }

    .students-action-bar {
        flex-direction: column;
        align-items: stretch !important;
        gap: 6px !important;
    }

    .students-action-bar .v-btn,
    .students-action-bar .v-btn-toggle {
        width: 100%;
        margin: 0 !important;
    }
}
</style>
