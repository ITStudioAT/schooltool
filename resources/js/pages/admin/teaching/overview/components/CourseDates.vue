<template>
    <ItsGridBox
        color="primary"
        title="Termine"
        icon="mdi-calendar"
        class="w-100"
        v-if="selected_course"
        :disabled="action != '' && action != 'new_course_dates' && action != 'edit_course_date_content'">
        <v-card tile flat color="transparent" class="w-100" :disabled="action != ''">
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
                    <v-btn icon="mdi-plus" size="small" color="primary" variant="tonal" @click="newDates" :disabled="isEditingContent" />
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

        <!-- Termine (Anzeige) -->
        <v-card variant="outlined" class="mt-4" v-if="selected_course && action != 'new_course_dates'">
            <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                <v-icon size="18">mdi-calendar-check</v-icon>
                Termine
                <v-chip v-if="filteredCourseDates?.length" size="x-small" color="primary" variant="tonal">
                    {{ filteredCourseDates.length }}
                </v-chip>
                <v-spacer />
                <v-btn
                    :icon="show_contents ? 'mdi-eye' : 'mdi-eye-off'"
                    size="x-small"
                    variant="tonal"
                    color="primary"
                    :disabled="isEditingContent"
                    @click="toggleContents"
                    :title="show_contents ? 'Inhalte ausblenden' : 'Inhalte anzeigen'" />
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-0">
                <v-list density="compact">
                    <v-list-item
                        v-for="courseDate in filteredCourseDates"
                        :key="courseDate.id"
                        :disabled="isEditingContent && editing_content_id !== courseDate.id"
                        :class="courseDateRowClass(courseDate)">
                        <div class="d-flex flex-column ga-2 w-100">
                            <div class="course-date-row d-flex align-center ga-2 w-100">
                                <v-icon v-if="highlightedDateId === courseDate.id" size="x-small" color="success" class="course-date-icon">mdi-arrow-right-bold</v-icon>
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
                                </div>
                                <div class="course-date-actions d-flex align-center ga-1">
                                    <v-btn
                                        size="x-small"
                                        :color="hasStatus(courseDate, 'free') ? 'success' : 'default'"
                                        :variant="hasStatus(courseDate, 'free') ? 'flat' : 'outlined'"
                                        :disabled="isEditingContent"
                                        @click="toggleStatus(courseDate, 'free')">
                                        E
                                    </v-btn>
                                    <v-btn
                                        size="x-small"
                                        :color="hasStatus(courseDate, 'pruefung') ? 'warning' : 'default'"
                                        :variant="hasStatus(courseDate, 'pruefung') ? 'flat' : 'outlined'"
                                        :disabled="isEditingContent"
                                        @click="toggleStatus(courseDate, 'pruefung')">
                                        P
                                    </v-btn>
                                    <v-btn
                                        :icon="isContentVisible(courseDate.id) ? 'mdi-eye' : 'mdi-eye-off'"
                                        size="x-small"
                                        color="primary"
                                        variant="tonal"
                                        :disabled="isEditingContent"
                                        @click="toggleContentLine(courseDate.id)" />
                                    <v-btn
                                        icon="mdi-pencil"
                                        size="x-small"
                                        color="primary"
                                        variant="tonal"
                                        :disabled="isEditingContent"
                                        @click="startEditContent(courseDate)" />
                                    <v-btn
                                        v-if="delete_date_id !== courseDate.id"
                                        icon="mdi-delete"
                                        size="x-small"
                                        color="warning"
                                        variant="tonal"
                                        :disabled="isEditingContent"
                                        @click="delete_date_id = courseDate.id" />
                                    <v-btn
                                        v-if="delete_date_id === courseDate.id"
                                        icon="mdi-delete-off"
                                        size="x-small"
                                        color="success"
                                        variant="tonal"
                                        :disabled="isEditingContent"
                                        @click="delete_date_id = null" />
                                    <v-btn
                                        v-if="delete_date_id === courseDate.id"
                                        icon="mdi-delete"
                                        size="x-small"
                                        color="error"
                                        variant="tonal"
                                        :disabled="isEditingContent"
                                        @click="deleteDate(courseDate)" />
                                </div>
                            </div>
                            <div v-if="isContentVisible(courseDate.id)" class="pl-6 pr-2 pb-2">
                                <div v-if="editing_content_id !== courseDate.id">
                                    <div v-if="courseDate.content" class="text-caption content-readonly" v-html="contentHtml(courseDate.content)"></div>
                                </div>
                                <div v-else class="d-flex flex-column ga-2">
                                    <ItsRichTextEditor
                                        v-model="content_drafts[courseDate.id]"
                                        :ref="`contentField-${courseDate.id}`" />
                                    <div class="d-flex align-center ga-2">
                                        <v-btn size="x-small" color="warning" variant="flat" @click="cancelEditContent(courseDate)" icon="mdi-close" />
                                        <v-btn size="x-small" color="success" variant="flat" @click="saveContent(courseDate)" icon="mdi-content-save" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </v-list-item>
                    <v-list-item v-if="!filteredCourseDates?.length">
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
                        <v-btn color="warning" flat tile @click="abortNewCourseDates">Abbruch</v-btn>
                        <v-btn color="success" flat tile type="submit" v-if="data.hours.length >= 1">Erstellen</v-btn>
                    </div>
                </v-card-text>
            </v-form>
        </v-card>
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
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsRichTextEditor from '@/components/ItsRichTextEditor.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox, ItsRichTextEditor },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.courseDateStore = useCourseDateStore()
        this.teachingStore = useTeachingStore()
        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
        }
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
            activeSemester: null,
            is_valid: false,
            delete_date_id: null,
            show_contents: false,
            collapsed_content_ids: [],
            expanded_content_ids: [],
            editing_content_id: null,
            content_drafts: {},
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
        ...mapWritableState(useCourseStore, ['selected_course']),
        ...mapWritableState(useCourseDateStore, ['courseDates', 'selected_courseDate']),
        semesterCount() {
            const schemaId = this.selected_course?.teaching_schema_id
            const grading = schemaId ? this.teachingStore?.gradingForSchema(schemaId) : {}
            return grading?.semester_count || 1
        },
        sem2StartDate() {
            return this.config?.user?.teaching_count_for_semester_2_date || this.config?.selected_schoolyear?.sem_2_start || null
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
            this.action = ''
        },
        async createDates(data) {
            if (!this.$refs.form.validate()) return

            data.course_id = this.selected_course.id
            await this.courseDateStore.store(data)
            await this.courseStore.index()

            this.data = { from: '', until: '', interval: 1, hours: [] }
            this.action = ''
        },
        async deleteDate(courseDate) {
            await this.courseDateStore.destroy(courseDate.id)
            await this.courseStore.index()
            if (this.selected_courseDate?.id === courseDate.id) {
                this.selected_courseDate = null
            }
            this.delete_date_id = null
        },
        hasStatus(courseDate, status) {
            return Array.isArray(courseDate.status) && courseDate.status.includes(status)
        },
        courseDateRowClass(courseDate) {
            if (this.selected_courseDate?.id === courseDate.id) return 'bg-secondary-lighten-5'
            if (this.hasStatus(courseDate, 'pruefung')) return 'bg-warning-lighten-4'
            if (this.hasStatus(courseDate, 'free')) return 'bg-success-lighten-2'
            if (this.highlightedDateId === courseDate.id) return 'bg-primary-lighten-4'
            return ''
        },
        selectCourseDate(courseDate) {
            if (!courseDate) return
            this.selected_courseDate = courseDate
        },
        async toggleStatus(courseDate, status) {
            const currentStatus = Array.isArray(courseDate.status) ? [...courseDate.status] : []
            const index = currentStatus.indexOf(status)

            if (index === -1) {
                currentStatus.push(status)
            } else {
                currentStatus.splice(index, 1)
            }

            await this.courseDateStore.updateStatus(courseDate.id, currentStatus)
            await this.courseStore.index()
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
            this.editing_content_id = null
            this.content_drafts = {
                ...this.content_drafts,
                [courseDate.id]: courseDate.content || '',
            }
            this.action = ''
        },
        async saveContent(courseDate) {
            const content = this.content_drafts[courseDate.id] ?? ''
            const payload = {
                id: courseDate.id,
                date: courseDate.date,
                content,
            }
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
    },
}
</script>

<style scoped>
.content-readonly :deep(textarea),
.content-readonly :deep(.v-field__input) {
    pointer-events: none;
    cursor: default;
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
}
</style>
