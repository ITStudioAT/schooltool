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

        <v-card variant="outlined" class="course-table-card">
            <v-card-text class="pa-0">
                <div ref="courseTableScroll" class="course-table-scroll">
                    <table class="course-table" data-testid="course-table">
                        <thead>
                            <tr class="course-table-command-row">
                                <th
                                    class="course-table-command-cell"
                                    :colspan="tableColumnCount"
                                    scope="colgroup">
                                    <div class="course-table-command-bar">
                                        <v-btn
                                            class="course-table-command-button"
                                            :color="showAttendanceMarkers ? 'success' : undefined"
                                            density="comfortable"
                                            :icon="showAttendanceMarkers ? 'mdi-account-check' : 'mdi-account-off-outline'"
                                            size="small"
                                            :title="showAttendanceMarkers ? 'Anwesenheit ausblenden' : 'Anwesenheit anzeigen'"
                                            variant="tonal"
                                            @click="toggleAttendanceMarkers" />
                                    </div>
                                </th>
                            </tr>
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
                                            v-if="showAttendanceMarkers && isAttendanceToggleable(courseDate)"
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
                            <tr
                                v-for="(student, studentIndex) in sortedSelectedStudents"
                                :key="`student-${student.id}`"
                                class="course-table-row">
                                <th scope="row" class="course-table-student-cell">
                                    <div class="course-table-main-text">
                                        <span class="course-table-student-name">{{ studentLastName(student) }}</span>
                                        <span
                                            v-if="showAttendanceMarkers && studentPresencePercentage(student) !== null"
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
                                        class="course-table-entry-cell course-table-entry-cell--interactive"
                                        :class="{ 'course-table-entry-cell--selected': isEntryDialogCellSelected(student, courseDate) }"
                                        role="button"
                                        tabindex="0"
                                        @click="openEntryDialog(student, courseDate)"
                                        @keydown.enter.prevent="openEntryDialog(student, courseDate)"
                                        @keydown.space.prevent="openEntryDialog(student, courseDate)">
                                        <div class="course-table-entry-cell-content">
                                            <div
                                                v-if="entriesForCell(student, courseDate).length"
                                                class="course-table-entry-cell-badges"
                                                data-testid="course-table-entry-cell-badges">
                                                <v-chip
                                                    v-for="entry in entriesForCell(student, courseDate)"
                                                    :key="entry.uid"
                                                    class="course-table-entry-cell-badge"
                                                    size="x-small"
                                                    :color="cellEntryColor(entry)"
                                                    variant="tonal"
                                                    :title="entry.description || cellEntryTypeLabel(entry)">
                                                    {{ compactCellEntryLabel(entry) }}
                                                </v-chip>
                                            </div>
                                            <v-btn
                                                v-if="showAttendanceMarkers && isAttendanceToggleable(courseDate)"
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
            entryDialog: {
                courseDate: null,
                open: false,
                student: null,
            },
            entryForm: {
                description: '',
                grade: '',
                type: '',
            },
            entryFormOpen: false,
            entrySaving: false,
            entryStore: null,
            savingAttendanceCells: {},
            showAttendanceMarkers: true,
        }
    },

    beforeMount() {
        this.behaviourEntryStore = useCourseBehaviourEntryStore()
        this.entryStore = useCourseStudentEntryStore()
    },

    async mounted() {
        this.courseDateStore = useCourseDateStore()
        this.restoreAttendanceView(this.$route?.query?.view)
        await this.loadCourseEntries()
        this.scrollToInitialCourseDate()
    },

    watch: {
        '$route.query.view'(view) {
            this.restoreAttendanceView(view)
        },
        sortedCourseDates() {
            this.scrollToInitialCourseDate()
        },
        selected_course(course) {
            if (course?.id) {
                this.loadCourseEntries(course.id)
            }
        },
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action']),
        ...mapWritableState(useCourseDateStore, ['selected_courseDate']),
        ...mapWritableState(useCourseStore, ['selected_course', 'students_sort_mode']),
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
            const works = Array.isArray(this.selectedTeachingSchema?.works) ? this.selectedTeachingSchema.works : []

            return works
                .filter((work) => work?.short_name)
                .map((work) => ({
                    title: work.name ? `${work.short_name} - ${work.name}` : work.short_name,
                    value: work.short_name,
                }))
        },
        availableEntryGrades() {
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
        registeredEntryStudentId() {
            return this.registeredStudentUserId(this.entryDialog.student)
        },
        canCreateCellEntry() {
            return Boolean(this.registeredEntryStudentId && this.availableEntryTypes.length)
        },
        canSaveCellEntry() {
            return Boolean(this.canCreateCellEntry && this.entryForm.type && !this.entrySaving)
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
            return Array.isArray(courseDate?.status) && courseDate.status.includes('free')
        },
        freeCourseDateReason(courseDate) {
            return String(courseDate?.free_reason || '').trim() || 'Frei'
        },
        restoreAttendanceView(view) {
            this.showAttendanceMarkers = view !== 'plain'
        },
        toggleAttendanceMarkers() {
            this.showAttendanceMarkers = !this.showAttendanceMarkers

            if (!this.$route || !this.$router) return

            this.$router.replace({
                path: this.$route.path,
                query: {
                    ...this.$route.query,
                    view: this.showAttendanceMarkers ? 'attendance' : 'plain',
                },
            }).catch(() => {})
        },
        openEntryDialog(student, courseDate) {
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

            await Promise.all([
                this.entryStore.indexByCourse(courseId),
                this.behaviourEntryStore.indexByCourse(courseId),
            ])
        },
        registeredStudentUserId(student) {
            if (!student) return null
            if (Object.prototype.hasOwnProperty.call(student, 'user_id')) {
                return student.user_id || null
            }

            return student.id || null
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
                grade: '',
                type: '',
            }
            this.entryFormOpen = true
        },
        cancelNewCellEntry() {
            this.entryFormOpen = false
            this.entryForm = {
                description: '',
                grade: '',
                type: '',
            }
        },
        selectCellEntryType(type) {
            this.entryForm.type = this.entryForm.type === type ? '' : type
            this.entryForm.grade = ''
        },
        async saveCellEntry() {
            if (!this.canSaveCellEntry) return

            this.entrySaving = true
            try {
                const response = await this.entryStore.store({
                    teaching_course_id: this.selected_course.id,
                    user_id: this.registeredEntryStudentId,
                    type: this.entryForm.type,
                    grade: this.entryForm.grade || null,
                    date: this.normalizeDateKey(this.entryDialog.courseDate?.date) || null,
                    description: String(this.entryForm.description || '').trim() || null,
                })
                if (response) {
                    this.cancelNewCellEntry()
                }
            } finally {
                this.entrySaving = false
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
            if (this.isFreeCourseDate(courseDate)) return false

            const dateKey = this.normalizeDateKey(courseDate?.date)
            if (!dateKey) return false

            return dateKey <= this.dateKey(new Date())
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
                    attendance: nextAttendance,
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

.course-table-command-cell {
    height: 36px;
    padding: 5px 7px;
    top: 0;
    z-index: 5 !important;
}

.course-table-command-bar {
    align-items: center;
    display: flex;
    gap: 6px;
    justify-content: flex-start;
    left: 7px;
    min-height: 24px;
    position: sticky;
    width: fit-content;
}

.course-table-command-button {
    height: 28px;
    min-width: 28px;
    width: 28px;
}

.course-table-title-row th {
    top: 36px;
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

.course-table-entry-cell--interactive {
    cursor: pointer;
    outline: none;
    transition: background-color 0.15s ease, box-shadow 0.15s ease;
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
