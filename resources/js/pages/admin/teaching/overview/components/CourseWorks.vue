<template>
    <ItsGridBox
        color="primary"
        title="Arbeiten"
        icon="mdi-clipboard-text"
        class="w-100"
        v-if="selected_course"
        :disabled="action != '' && action != 'new_course_work' && action != 'edit_course_work'">
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
                    <v-btn icon="mdi-plus" size="small" color="primary" variant="tonal" @click="newWork" :disabled="action === 'edit_course_work'" />
                </v-card>
            </v-card-text>
        </v-card>

        <!-- Arbeiten (Anzeige) -->
        <v-card variant="outlined" class="mt-4" v-if="action !== 'new_course_work' && action !== 'edit_course_work'">
            <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                <v-icon size="18">mdi-clipboard-text</v-icon>
                Arbeiten
                <v-chip v-if="courseWorks?.length" size="x-small" color="primary" variant="tonal">
                    {{ courseWorks.length }}
                </v-chip>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-0">
                <v-list density="compact">
                    <v-list-item v-for="work in courseWorks" :key="work.id">
                        <div class="d-flex flex-column ga-2 w-100">
                            <div class="d-flex align-center ga-2 w-100">
                                <v-chip v-if="work.date_for_all_groups" size="x-small" variant="tonal" color="primary">
                                    {{ formatDate(work.date_for_all_groups) }}
                                </v-chip>
                                <v-chip v-else size="x-small" variant="outlined">ohne Datum</v-chip>
                    <div class="text-body-2 flex-grow-1">
                        <strong v-if="work.type">{{ workTypeLabel(work.type) }}</strong>
                        <span v-else class="text-medium-emphasis">eine Arbeit</span>
                        <span v-if="work.description"> – {{ work.description }}</span>
                    </div>
                                <div class="d-flex align-center ga-1">
                                    <v-btn icon="mdi-pencil" size="x-small" color="primary" variant="tonal" @click="editWork(work)" />
                                    <v-btn
                                        v-if="delete_work_id !== work.id"
                                        icon="mdi-delete"
                                        size="x-small"
                                        color="warning"
                                        variant="tonal"
                                        @click="delete_work_id = work.id" />
                                    <v-btn
                                        v-if="delete_work_id === work.id"
                                        icon="mdi-delete-off"
                                        size="x-small"
                                        color="success"
                                        variant="tonal"
                                        @click="delete_work_id = null" />
                                    <v-btn
                                        v-if="delete_work_id === work.id"
                                        icon="mdi-delete"
                                        size="x-small"
                                        color="error"
                                        variant="tonal"
                                        @click="deleteWork(work)" />
                                </div>
                            </div>
                        </div>
                    </v-list-item>
                    <v-list-item v-if="!courseWorks?.length">
                        <v-list-item-title class="text-caption text-medium-emphasis">Keine Arbeiten vorhanden.</v-list-item-title>
                    </v-list-item>
                </v-list>
            </v-card-text>
        </v-card>

        <!-- NEUE/BEARBEITEN ARBEIT (TEMPLATE) -->
        <v-card tile flat color="transparent" class="w-100" v-if="action === 'new_course_work' || action === 'edit_course_work'">
            <v-form ref="form" v-model="is_valid" @submit.prevent="saveWork" class="mb-4">
                <v-card-text>
                    <v-select
                        v-model="work_form.type"
                        label="Typ"
                        :items="workTypeItems"
                        item-title="title"
                        item-value="value"
                        clearable />
                    <v-date-input v-model="work_form.date_for_all_groups" label="Datum (für alle Gruppen)" />
                    <div class="d-flex flex-wrap ga-1 mt-1" v-if="nextDates.length">
                        <v-chip
                            v-for="date in nextDates"
                            :key="date.id"
                            size="x-small"
                            variant="outlined"
                            class="cursor-pointer"
                            @click="selectDate(date.date)">
                            {{ formatDateWithWeekday(date.date) }}
                        </v-chip>
                    </div>
                    <v-textarea v-model="work_form.description" label="Beschreibung" rows="3" />

                    <v-switch v-model="work_form.is_group_work" label="Gruppenarbeit" inset />
                    <div v-if="work_form.is_group_work">
                        <div class="d-flex flex-column ga-2">
                            <v-text-field v-model.number="work_form.group_size" type="number" min="2" label="Gruppengröße" />
                            <v-switch v-model="work_form.is_random_groups" label="Gruppen zufällig erstellen" inset />
                            <v-btn
                                v-if="work_form.is_random_groups"
                                color="primary"
                                variant="tonal"
                                size="small"
                                :disabled="!work_form.group_size || work_form.group_size < 2"
                                @click="generateRandomGroups">
                                Gruppen zufällig erstellen
                            </v-btn>
                            <v-btn color="primary" variant="tonal" size="small" @click="addGroup">
                                Gruppe hinzufügen
                            </v-btn>
                        </div>

                        <v-expansion-panels variant="accordion" class="mt-3">
                            <v-expansion-panel v-for="(group, index) in work_form.groups" :key="`group-${index}`">
                                <v-expansion-panel-title>
                                    <div class="d-flex align-center flex-wrap ga-2 w-100">
                                        <div class="text-caption text-medium-emphasis">Gruppe {{ index + 1 }}</div>
                                        <v-chip
                                            v-for="studentId in sortedGroupStudentIds(group)"
                                            :key="`g-${index}-s-${studentId}`"
                                            size="x-small"
                                            variant="tonal">
                                            {{ studentNameById(studentId) }}
                                        </v-chip>
                                    </div>
                                </v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <div class="d-flex flex-column ga-2">
                                        <div class="d-flex align-center justify-space-between">
                                            <div class="text-caption text-medium-emphasis">Mitglieder</div>
                                            <v-btn icon="mdi-delete" size="x-small" color="warning" variant="tonal" @click="removeGroup(index)" />
                                        </div>
                                        <v-autocomplete
                                            v-model="group.student_ids"
                                            :items="availableStudentItems(index)"
                                            item-title="title"
                                            item-value="value"
                                            multiple
                                            chips
                                            label="Schüler:innen"
                                            :model-value="sortedGroupStudentIds(group)"
                                            @update:model-value="sortGroupStudents(group)" />
                                        <v-switch v-model="group.use_individual_grades" label="Einzelnoten pro Schüler:in" inset />
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
                    <div v-else class="text-caption text-medium-emphasis">
                        Für Einzelarbeiten werden pro Schüler:in automatisch Gruppen mit Kommentar/Note erstellt.
                    </div>

                    <div class="text-caption text-medium-emphasis mt-2">
                        Gruppen/Status folgen im nächsten Schritt.
                    </div>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="warning" flat tile @click="abortEdit">Abbruch</v-btn>
                        <v-btn color="success" flat tile type="submit">Speichern</v-btn>
                    </div>
                </v-card-text>
            </v-form>
        </v-card>
    </ItsGridBox>
</template>

<script>
import { mapWritableState } from 'pinia'
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
            is_valid: false,
            delete_work_id: null,
            work_form: this.emptyWorkForm(),
            is_initializing_form: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, ['selected_course']),
        ...mapWritableState(useCourseWorkStore, ['courseWorks', 'selected_courseWork']),
        ...mapWritableState(useTeachingStore, ['settings']),
        teachingWorks() {
            return this.settings?.teaching_works || []
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
                    _last: (student.last_name || '').toString(),
                    _first: (student.first_name || '').toString(),
                }))
                .sort((a, b) => {
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
                    _dateObj: new Date(d.date),
                }))
                .filter((d) => !isNaN(d._dateObj.getTime()) && d._dateObj >= today)
                .sort((a, b) => a._dateObj - b._dateObj)
                .slice(0, 3)
        },
    },

    watch: {
        selected_course: {
            handler() {
                this.refreshWorks()
            },
            deep: true,
        },
        'work_form.date_for_all_groups'(val) {
            if (val && val instanceof Date) {
                this.work_form.date_for_all_groups = this.toDateString(val)
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
                if (this.work_form.is_random_groups) {
                    this.generateRandomGroups()
                } else {
                    this.work_form.groups = []
                }
            }
        },
    },

    methods: {
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
                    grades,
                    comments,
                    use_individual_grades: Object.keys(grades).length > 0,
                }
            })
            if (!this.work_form.is_group_work) {
                this.work_form.groups = this.buildIndividualGroups()
            }
            this.action = 'edit_course_work'
            this.$nextTick(() => {
                this.is_initializing_form = false
            })
        },
        abortEdit() {
            this.action = ''
            this.work_form = this.emptyWorkForm()
        },
        async saveWork() {
            if (!this.work_form.is_group_work) {
                this.work_form.groups = this.buildIndividualGroups()
            } else if (this.work_form.is_random_groups && !this.work_form.groups?.length) {
                this.generateRandomGroups()
            }

            this.work_form.groups = (this.work_form.groups || []).map((group) => {
                if (!group.use_individual_grades) {
                    return { ...group, grades: [], comments: [] }
                }
                const grades = (group.student_ids || []).map((id) => ({
                    student_id: id,
                    grade: group.grades?.[id] ?? '',
                }))
                const comments = (group.student_ids || []).map((id) => ({
                    student_id: id,
                    comment: group.comments?.[id] ?? '',
                }))
                return { ...group, grade: '', comment: '', grades, comments }
            })

            const payload = {
                ...this.work_form,
                teaching_course_id: this.selected_course?.id || this.work_form.teaching_course_id,
            }

            let ok = false
            if (this.action === 'edit_course_work') {
                ok = await this.courseWorkStore.update(payload)
            } else {
                ok = await this.courseWorkStore.store(payload)
            }

            if (ok) {
                await this.refreshWorks()
                this.abortEdit()
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
                comment: '',
                grade: '',
                grades: {},
                comments: {},
                use_individual_grades: false,
            }))
        },
        addGroup() {
            if (!Array.isArray(this.work_form.groups)) this.work_form.groups = []
            this.work_form.groups.push({
                student_ids: [],
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
                const [single] = groups.pop()
                groups.forEach((group, idx) => {
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
                comment: '',
                grade: '',
                grades: {},
                comments: {},
                use_individual_grades: false,
            }))
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
        sortGroupStudents(group) {
            if (!group || !Array.isArray(group.student_ids)) return
            group.student_ids = this.sortedGroupStudentIds(group)
        },
        studentNameById(studentId) {
            const student = (this.selected_course?.students_info || []).find((s) => s.id === studentId)
            if (!student) return String(studentId || '')
            return this.studentLabel(student)
        },
        sortedGroupStudentIds(group) {
            const ids = Array.isArray(group?.student_ids) ? [...group.student_ids] : []
            const students = this.selected_course?.students_info || []
            const byId = new Map(students.map((s) => [s.id, s]))

            return ids.sort((a, b) => {
                const sa = byId.get(a) || {}
                const sb = byId.get(b) || {}
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
            const d = date instanceof Date ? date : new Date(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        formatDateWithWeekday(date) {
            if (!date) return ''
            const d = date instanceof Date ? date : new Date(date)
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
            const d = date instanceof Date ? date : new Date(date)
            const year = d.getFullYear()
            const month = String(d.getMonth() + 1).padStart(2, '0')
            const day = String(d.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
    },
}
</script>
