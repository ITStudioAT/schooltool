<template>
    <ItsGridBox
        color="primary"
        icon="mdi-clipboard-text"
        class="w-100"
        v-if="selected_course"
        :disabled="action != '' && action != 'new_course_work' && action != 'edit_course_work'">
        <template #title>
            <div class="d-flex align-center ga-2 flex-grow-1">
                <div>Arbeiten</div>
                <v-spacer />
                <v-btn v-if="action === 'new_course_work' || action === 'edit_course_work'" icon="mdi-close" size="x-small" color="warning" variant="flat" @click="abortEdit" :disabled="is_saving" />
                <v-btn v-if="action === 'new_course_work' || action === 'edit_course_work'" icon="mdi-content-save" size="x-small" color="success" variant="flat" @click="saveWork(false)" :disabled="is_saving" />
            </div>
        </template>
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <!-- Anzeige ausgewählter Kurs -->
                <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between">
                    <div>
                        <div class="text-body-1 font-weight-medium">{{ selected_course.title }}</div>
                        <div class="d-flex flex-wrap ga-1 mt-1">
                            <v-chip v-for="cls in selected_course.classes" :key="cls" size="small" variant="tonal">
                                {{ cls }}
                            </v-chip>
                        </div>
                    </div>
                    <v-btn icon="mdi-plus" size="small" color="primary" variant="tonal" @click="newWork" :disabled="action === 'edit_course_work' || !hasStudents" />
                </v-card>

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
        <v-card variant="outlined" class="mt-4" v-if="action !== 'new_course_work' && action !== 'edit_course_work'">
            <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                <v-icon size="18">mdi-clipboard-text</v-icon>
                Arbeiten
                <v-chip v-if="filteredCourseWorks?.length" size="x-small" color="primary" variant="tonal">
                    {{ filteredCourseWorks.length }}
                </v-chip>
                <v-chip v-if="courseWorks?.length && courseWorks.length !== (filteredCourseWorks?.length || 0)" size="x-small" color="secondary" variant="outlined">
                    Gesamt {{ courseWorks.length }}
                </v-chip>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-0">
                <v-list density="compact">
                    <v-list-item v-for="work in filteredCourseWorks" :key="work.id" class="cursor-pointer" @click="editWork(work)">
                        <div class="d-flex flex-column ga-2 w-100">
                            <div class="d-flex align-center ga-2 w-100">
                                <v-chip v-if="work.date_for_all_groups" size="x-small" variant="tonal" color="primary">
                                    {{ formatDate(work.date_for_all_groups) }}
                                </v-chip>
                                <v-chip v-else size="x-small" variant="outlined">ohne Datum</v-chip>
                                <div class="text-body-2 flex-grow-1" :class="workHasAllGrades(work) ? 'text-success' : ''">
                                    <strong v-if="work.type">{{ workTypeLabel(work.type) }}</strong>
                                    <span v-else class="text-medium-emphasis">eine Arbeit</span>
                                    <span v-if="work.description">– {{ work.description }}</span>
                                </div>
                                <div class="d-flex align-center ga-1">
                                    <v-btn v-if="delete_work_id !== work.id" icon="mdi-delete" size="x-small" color="warning" variant="tonal" @click.stop="delete_work_id = work.id" />
                                    <v-btn v-if="delete_work_id === work.id" icon="mdi-delete-off" size="x-small" color="success" variant="tonal" @click.stop="delete_work_id = null" />
                                    <v-btn v-if="delete_work_id === work.id" icon="mdi-delete" size="x-small" color="error" variant="tonal" @click.stop="deleteWork(work)" />
                                </div>
                            </div>
                        </div>
                    </v-list-item>
                    <v-list-item v-if="!filteredCourseWorks?.length && hasStudents && !courseWorks?.length">
                        <v-list-item-title class="text-caption text-medium-emphasis">Keine Arbeiten vorhanden.</v-list-item-title>
                    </v-list-item>
                    <v-list-item v-if="!filteredCourseWorks?.length && hasStudents && courseWorks?.length">
                        <v-list-item-title class="text-caption text-warning">
                            Es gibt {{ courseWorks.length }} Arbeit(en), sie sind aktuell durch den Semester-Filter ausgeblendet.
                        </v-list-item-title>
                    </v-list-item>
                    <v-list-item v-if="!hasStudents">
                        <v-list-item-title class="text-caption text-warning">Keine Schüler:innen im Kurs. Bitte zuerst Schüler:innen hinzufügen.</v-list-item-title>
                    </v-list-item>
                </v-list>
            </v-card-text>
        </v-card>

        <!-- NEUE/BEARBEITEN ARBEIT (TEMPLATE) -->
        <v-card tile flat color="transparent" class="w-100" v-if="action === 'new_course_work' || action === 'edit_course_work'">
            <v-form ref="form" v-model="is_valid" @submit.prevent class="mb-4">
                <v-card-text>
                    <v-select v-model="work_form.type" label="Typ" :items="workTypeItems" item-title="title" item-value="value" clearable />
                    <v-date-input v-model="work_form.date_for_all_groups" label="Datum (für alle Gruppen)" />
                    <div class="d-flex flex-wrap ga-1 mt-1" v-if="nextDates.length">
                        <v-chip v-for="date in nextDates" :key="date.id" size="x-small" variant="outlined" class="cursor-pointer" @click="selectDate(date.date)">
                            {{ formatDateWithWeekday(date.date) }}
                        </v-chip>
                    </div>
                    <v-textarea v-model="work_form.description" label="Beschreibung" rows="3" class="mt-4" />

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
                                                <v-chip v-if="group.date" size="x-small" variant="tonal" :color="group.date !== work_form.date_for_all_groups ? 'error' : 'primary'">
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
                                <v-btn
                                    v-if="allSinglePanelsOpen"
                                    size="x-small"
                                    :variant="show_bulk_action ? 'flat' : 'outlined'"
                                    :color="show_bulk_action ? 'warning' : 'primary'"
                                    @click="show_bulk_action = !show_bulk_action">
                                    {{ show_bulk_action ? 'Sammelaktion schließen' : 'Sammelaktion' }}
                                </v-btn>
                                <v-spacer v-else />
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
                            <!-- Compact view when all open -->
                            <div v-if="allSinglePanelsOpen" class="mt-3 d-flex flex-column ga-2">
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
            is_saving: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, ['selected_course']),
        ...mapWritableState(useCourseWorkStore, ['courseWorks', 'selected_courseWork']),
        ...mapWritableState(useTeachingStore, ['settings']),
        teachingWorks() {
            const schemaId = this.selected_course?.teaching_schema_id
            if (!schemaId) return []
            return this.teachingStore.worksForSchema(schemaId)
        },
        hasStudents() {
            return (this.selected_course?.students_info || []).length > 0
        },
        semesterCount() {
            const schemaId = this.selected_course?.teaching_schema_id
            const grading = schemaId ? this.teachingStore?.gradingForSchema(schemaId) : {}
            return grading?.semester_count || 1
        },
        sem2StartDate() {
            const raw = this.config?.user?.teaching_count_for_semester_2_date || this.config?.selected_schoolyear?.sem_2_start || null
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
        studentItems() {
            const students = this.selected_course?.students_info || []
            return students
                .map((student) => ({
                    title: this.studentLabel(student),
                    value: student.id,
                    _class: (student.schoolclass || student.class || '').toString(),
                    _last: (student.last_name || '').toString(),
                    _first: (student.first_name || '').toString(),
                }))
                .sort((a, b) => {
                    const classCmp = a._class.localeCompare(b._class, 'de', { numeric: true, sensitivity: 'base' })
                    if (classCmp !== 0) return classCmp
                    const lastCmp = a._last.localeCompare(b._last, 'de', { sensitivity: 'base' })
                    if (lastCmp !== 0) return lastCmp
                    return a._first.localeCompare(b._first, 'de', { sensitivity: 'base' })
                })
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
        hasUnassignedStudents() {
            const allStudentIds = (this.selected_course?.students_info || []).map((s) => s.id)
            const assignedIds = new Set()
            ;(this.work_form.groups || []).forEach((group) => {
                ;(group.student_ids || []).forEach((id) => assignedIds.add(id))
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
    },

    methods: {
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
                const hasGrade = gradeVals.some((v) => (v ?? '').toString().trim() !== '')
                const hasComment = commentVals.some((v) => (v ?? '').toString().trim() !== '')
                const groupGrade = (group?.grade ?? '').toString().trim() !== ''
                const groupComment = (group?.comment ?? '').toString().trim() !== ''
                return hasStudents || hasGrade || hasComment || groupGrade || groupComment
            })
        },
        hasIndividualEntries() {
            const groups = this.work_form.groups || []
            return groups.some((group) => {
                const gradeVals = group?.grades ? Object.values(group.grades) : []
                const commentVals = group?.comments ? Object.values(group.comments) : []
                const hasGrade = gradeVals.some((v) => (v ?? '').toString().trim() !== '')
                const hasComment = commentVals.some((v) => (v ?? '').toString().trim() !== '')
                const groupGrade = (group?.grade ?? '').toString().trim() !== ''
                const groupComment = (group?.comment ?? '').toString().trim() !== ''
                return hasGrade || hasComment || groupGrade || groupComment
            })
        },
        emptyWorkForm() {
            return {
                id: null,
                teaching_course_id: null,
                type: '',
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
            this.action = 'new_course_work'
            this.$nextTick(() => {
                this.is_initializing_form = false
            })
        },
        editWork(work) {
            this.is_initializing_form = true
            this.work_form = {
                ...this.emptyWorkForm(),
                ...work,
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
                return {
                    ...group,
                    // Normalize group date (server may return ISO format)
                    date: this.normalizeDateString(group.date),
                    grades,
                    comments,
                    use_individual_grades: Object.keys(grades).length > 0,
                }
            })
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
            this.action = 'edit_course_work'
            this.$nextTick(() => {
                this.is_initializing_form = false
            })
        },
        abortEdit() {
            this.action = ''
            this.work_form = this.emptyWorkForm()
            this.pending_random_groups = false
            this.show_bulk_action = false
            this.bulk_grade = null
            this.bulk_comment = ''
            this.selected_student_ids = []
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
                        return { ...group, date: date || null, grades: [], comments: [] }
                    }
                    const grades = (group.student_ids || []).map((id) => ({
                        student_id: id,
                        grade: group.grades?.[id] ?? '',
                    }))
                    const comments = (group.student_ids || []).map((id) => ({
                        student_id: id,
                        comment: group.comments?.[id] ?? '',
                    }))
                    return { ...group, date: date || null, grade: '', comment: '', grades, comments }
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
            const students = this.selected_course?.students_info || []
            return students.map((student) => ({
                student_ids: [student.id],
                date: this.work_form.date_for_all_groups || '',
                comment: '',
                grade: '',
                grades: { [student.id]: '' },
                comments: { [student.id]: '' },
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
            const students = (this.selected_course?.students_info || []).map((s) => s.id)
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
                use_individual_grades: false,
            }))
            this.work_form.is_random_groups = false
        },
        studentLabel(student) {
            const cls = student.schoolclass ? `${student.schoolclass} ` : ''
            const name = `${student.last_name || ''}, ${student.first_name || ''}`.trim()
            return `${cls}${name}`.trim()
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
        studentNameById(studentId) {
            const student = (this.selected_course?.students_info || []).find((s) => s.id === studentId)
            if (!student) return String(studentId || '')
            return this.studentLabel(student)
        },
        workHasAllGrades(work) {
            const groups = Array.isArray(work?.groups) ? work.groups : []
            if (!groups.length) return false
            const nonEmptyGroups = groups.filter((group) => Array.isArray(group?.student_ids) && group.student_ids.length > 0)
            if (!nonEmptyGroups.length) return false

            const hasGrade = (value) => (value ?? '').toString().trim() !== ''

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

            // For non-group work: all students in the selected course need a grade.
            const students = (this.selected_course?.students_info || []).map((s) => s.id)
            if (!students.length) return false

            const gradesByStudent = new Map()
            nonEmptyGroups.forEach((group) => {
                const ids = Array.isArray(group?.student_ids) ? group.student_ids : []
                const groupGrade = (group?.grade ?? '').toString().trim()
                const gradesArray = Array.isArray(group?.grades) ? group.grades : []

                if (gradesArray.length) {
                    gradesArray.forEach((item) => {
                        if (!item?.student_id) return
                        const val = (item.grade ?? '').toString().trim()
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
            const students = this.selected_course?.students_info || []
            const byId = new Map(students.map((s) => [s.id, s]))

            return ids.sort((a, b) => {
                const sa = byId.get(a) || {}
                const sb = byId.get(b) || {}
                const classA = (sa.schoolclass || sa.class || '').toString()
                const classB = (sb.schoolclass || sb.class || '').toString()
                const classCmp = classA.localeCompare(classB, 'de', { numeric: true, sensitivity: 'base' })
                if (classCmp !== 0) return classCmp
                const lastA = (sa.last_name || '').toString()
                const lastB = (sb.last_name || '').toString()
                const lastCmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
                if (lastCmp !== 0) return lastCmp
                const firstA = (sa.first_name || '').toString()
                const firstB = (sb.first_name || '').toString()
                return firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
            })
        },
        sortGroupsByStudent(groups) {
            if (!Array.isArray(groups)) return []
            const students = this.selected_course?.students_info || []
            const byId = new Map(students.map((s) => [s.id, s]))

            return [...groups].sort((a, b) => {
                // For non-group works, each group has one student
                const studentIdA = a.student_ids?.[0]
                const studentIdB = b.student_ids?.[0]
                const sa = byId.get(studentIdA) || {}
                const sb = byId.get(studentIdB) || {}
                const classA = (sa.schoolclass || sa.class || '').toString()
                const classB = (sb.schoolclass || sb.class || '').toString()
                const classCmp = classA.localeCompare(classB, 'de', { numeric: true, sensitivity: 'base' })
                if (classCmp !== 0) return classCmp
                const lastA = (sa.last_name || '').toString()
                const lastB = (sb.last_name || '').toString()
                const lastCmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
                if (lastCmp !== 0) return lastCmp
                const firstA = (sa.first_name || '').toString()
                const firstB = (sb.first_name || '').toString()
                return firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
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
        workTypeLabel(type) {
            if (!type) return ''
            const found = this.teachingWorks.find((w) => w.short_name === type)
            if (!found) return type
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
