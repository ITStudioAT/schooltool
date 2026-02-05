<template>
    <ItsGridBox color="primary" title="Schüler:in" icon="mdi-account" class="w-100" v-if="selected_course_student" :disabled="action != ''">
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between">
                    <div>
                        <div class="text-body-1 font-weight-medium">
                            {{ selected_course_student.last_name }}, {{ selected_course_student.first_name }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ selected_course_student.schoolclass || selected_course_student.class || '–' }}
                        </div>
                    </div>
                    <v-btn color="warning" flat tile @click="closeStudent">Zurück</v-btn>
                </v-card>

                <v-card variant="outlined" class="mt-4">
                    <v-card-text v-if="!is_editing">
                        <div class="text-body-2 course-comment" v-if="selected_comment" v-html="commentHtml"></div>
                        <div class="text-body-2" v-else>Kein Kommentar vorhanden.</div>
                        <div class="w-100 text-right">
                            <v-btn flat tile size="small" color="primary" icon="mdi-pencil" @click="editComment" />
                        </div>
                    </v-card-text>
                    <v-card-text v-else>
                        <v-form ref="form" @submit.prevent="saveComment">
                            <div class="mb-4">
                                <label class="text-caption text-medium-emphasis">Kommentar</label>
                                <ItsRichTextEditor v-model="edit_comment" />
                            </div>

                            <div class="d-flex flex-row align-center justify-space-between mt-4">
                                <v-btn color="warning" flat tile @click="abortEdit">Abbruch</v-btn>
                                <v-btn color="success" flat tile type="submit">Speichern</v-btn>
                            </div>
                        </v-form>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-4">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                        <v-icon size="18">mdi-clipboard-text</v-icon>
                        Einträge
                        <v-chip v-if="entries?.length" size="x-small" color="primary" variant="tonal">
                            {{ entries.length }}
                        </v-chip>
                        <v-chip
                            v-for="summary in pointsTotals"
                            :key="`points-${summary.type}`"
                            size="x-small"
                            color="primary"
                            variant="tonal">
                            Σ {{ summary.type }}: {{ summary.total }}
                        </v-chip>
                        <v-spacer />
                        <v-btn size="small" variant="tonal" color="primary" @click="toggleSortByType">
                            {{ sort_by_type ? 'Sortierung: Typ' : 'Sortierung: Datum' }}
                        </v-btn>
                        <v-btn icon="mdi-plus" size="small" color="primary" variant="tonal" @click="newEntry" />
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item v-for="entry in sortedEntries" :key="entry.id">
                                <div class="d-flex align-center ga-2 w-100">
                                    <v-chip v-if="entry.date" size="x-small" variant="tonal" color="primary">
                                        {{ formatDate(entry.date) }}
                                    </v-chip>
                                    <v-chip v-if="entry.type" size="x-small" variant="outlined">
                                        {{ workTypeLabel(entry.type) }}
                                    </v-chip>
                                    <v-chip v-if="entry.grade" size="x-small" variant="tonal" color="success">
                                        {{ entry.grade }}
                                    </v-chip>
                                    <div class="text-body-2 flex-grow-1">
                                        {{ entry.description || '' }}
                                    </div>
                                    <v-btn icon="mdi-pencil" size="x-small" color="primary" variant="tonal" @click="editEntry(entry)" />
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!entries?.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine Einträge vorhanden.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-4" v-if="show_entry_form">
                    <v-card-text>
                        <v-form ref="entryForm" @submit.prevent="saveEntry">
                            <v-select
                                v-model="entry_form.type"
                                label="Typ"
                                :items="workTypeItems"
                                item-title="title"
                                item-value="value"
                                clearable />
                            <v-select
                                v-model="entry_form.grade"
                                label="Note"
                                :items="gradeItemsForType"
                                item-title="title"
                                item-value="value"
                                clearable />
                            <v-date-input v-model="entry_form.date" label="Datum" />
                            <v-textarea v-model="entry_form.description" label="Beschreibung" rows="3" :counter="1024" :maxlength="1024" />

                            <div class="d-flex flex-row align-center justify-space-between mt-4">
                                <v-btn color="warning" flat tile @click="abortEntry">Abbruch</v-btn>
                                <v-btn color="success" flat tile type="submit">{{ entry_form.id ? 'Aktualisieren' : 'Speichern' }}</v-btn>
                            </div>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsRichTextEditor from '@/components/ItsRichTextEditor.vue'

export default {
    components: { ItsGridBox, ItsRichTextEditor },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.entryStore = useCourseStudentEntryStore()
        this.teachingStore = useTeachingStore()
        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
        }
        await this.loadEntries()
    },

    data() {
        return {
            adminStore: null,
            courseStore: null,
            entryStore: null,
            teachingStore: null,
            is_editing: false,
            edit_comment: '',
            show_entry_form: false,
            entry_form: this.emptyEntryForm(),
            sort_by_type: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2']),
        ...mapWritableState(useCourseStore, ['selected_course', 'selected_course_student']),
        ...mapWritableState(useCourseStudentEntryStore, ['entries']),
        ...mapWritableState(useTeachingStore, ['settings']),
        selected_comment() {
            return this.selected_course_student?.comment || ''
        },
        commentHtml() {
            const text = this.selected_comment
            if (!text) return ''
            if (text.includes('<p>') || text.includes('<br')) return text
            return text
                .split('\n')
                .map((line) => `<p>${line || '<br>'}</p>`)
                .join('')
        },
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
            const selectedType = this.entry_form.type
            if (!selectedType) return []
            const work = this.teachingWorks.find((w) => w.short_name === selectedType)
            const grades = work?.grades || []
            return grades.map((grade) => ({
                title: grade.name ? `${grade.grade} (${grade.name})` : grade.grade,
                value: grade.grade,
            }))
        },
        pointsTotals() {
            const totals = {}
            const list = this.entries || []

            list.forEach((entry) => {
                const work = this.teachingWorks.find((w) => w.short_name === entry.type)
                if (!work || work.calculation !== 'points') return
                const value = this.gradeValueForWork(work, entry.grade)
                if (value === null) return
                totals[entry.type] = (totals[entry.type] || 0) + value
            })

            return Object.entries(totals).map(([type, total]) => ({
                type,
                total: Number.isInteger(total) ? total : Number(total.toFixed(2)),
            }))
        },
        sortedEntries() {
            const list = this.entries || []
            if (!this.sort_by_type) return list
            return [...list].sort((a, b) => {
                const typeA = (a.type || '').toString()
                const typeB = (b.type || '').toString()
                const typeCmp = typeA.localeCompare(typeB, 'de', { sensitivity: 'base' })
                if (typeCmp !== 0) return typeCmp
                const dateA = a.date ? new Date(a.date).getTime() : 0
                const dateB = b.date ? new Date(b.date).getTime() : 0
                return dateB - dateA
            })
        },
    },

    watch: {
        selected_course_student: {
            handler() {
                this.show_entry_form = false
                this.entry_form = this.emptyEntryForm()
                this.loadEntries()
            },
        },
        'entry_form.date'(val) {
            if (val && val instanceof Date) {
                this.entry_form.date = this.toDateString(val)
            }
        },
    },

    methods: {
        emptyEntryForm() {
            return {
                id: null,
                type: '',
                grade: '',
                date: this.toDateString(new Date()),
                description: '',
            }
        },
        async loadEntries() {
            if (!this.selected_course?.id || !this.selected_course_student?.id) {
                this.entryStore?.clear()
                return
            }
            await this.entryStore.index(this.selected_course.id, this.selected_course_student.id)
        },
        closeStudent() {
            this.selected_course_student = null
            this.action_2 = ''
            this.entryStore?.clear()
        },
        editComment() {
            this.edit_comment = this.selected_comment || ''
            this.is_editing = true
        },
        abortEdit() {
            this.is_editing = false
            this.edit_comment = ''
        },
        async saveComment() {
            if (!this.selected_course) return
            const studentsInfo = (this.selected_course.students_info || []).map((student) => {
                if (student.id === this.selected_course_student.id) {
                    return { ...student, comment: this.edit_comment }
                }
                return student
            })

            const payload = {
                ...this.selected_course,
                students: studentsInfo,
                students_deleted: this.selected_course.students_deleted || [],
            }

            const ok = await this.courseStore.update(payload)
            if (ok) {
                this.selected_course.students_info = studentsInfo
                this.selected_course_student = studentsInfo.find((s) => s.id === this.selected_course_student.id) || this.selected_course_student
                this.abortEdit()
            }
        },
        newEntry() {
            this.entry_form = this.emptyEntryForm()
            this.show_entry_form = true
        },
        editEntry(entry) {
            if (!entry) return
            this.entry_form = {
                id: entry.id,
                type: entry.type || '',
                grade: entry.grade || '',
                date: entry.date || '',
                description: entry.description || '',
            }
            this.show_entry_form = true
        },
        abortEntry() {
            this.show_entry_form = false
            this.entry_form = this.emptyEntryForm()
        },
        async saveEntry() {
            if (!this.selected_course || !this.selected_course_student) return
            const payload = {
                id: this.entry_form.id,
                teaching_course_id: this.selected_course.id,
                user_id: this.selected_course_student.id,
                type: this.entry_form.type,
                grade: this.entry_form.grade,
                date: this.entry_form.date instanceof Date ? this.toDateString(this.entry_form.date) : this.entry_form.date,
                description: this.entry_form.description,
            }
            const ok = this.entry_form.id ? await this.entryStore.update(payload) : await this.entryStore.store(payload)
            if (ok) {
                await this.loadEntries()
                this.abortEntry()
            }
        },
        toggleSortByType() {
            this.sort_by_type = !this.sort_by_type
        },
        formatDate(date) {
            if (!date) return ''
            const d = date instanceof Date ? date : new Date(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        workTypeLabel(type) {
            if (!type) return ''
            const found = this.teachingWorks.find((w) => w.short_name === type)
            if (!found) return type
            return `${found.short_name} - ${found.name}`
        },
        gradeValueForWork(work, gradeKey) {
            if (!work || !gradeKey) return null
            const grade = (work.grades || []).find((g) => g.grade === gradeKey)
            if (!grade || grade.value == null) return null
            const num = parseFloat(String(grade.value).replace(',', '.'))
            return Number.isNaN(num) ? null : num
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

<style scoped>
.course-comment :deep(p) {
    margin: 0;
    min-height: 1.2em;
}
</style>
