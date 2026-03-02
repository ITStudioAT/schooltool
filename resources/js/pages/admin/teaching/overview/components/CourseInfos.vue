<template>
    <ItsGridBox
        variant="overview"
        color="primary"
        :title="selected_course.title + ' (' + selectedCourseClasses + ')'"
        icon="mdi-information-box"
        class="w-100"
        v-if="selected_course"
        :disabled="action != '' && action != 'edit_description'">
        <template #header-actions>
            <v-btn v-if="action !== 'edit_description'" icon="mdi-pencil" size="small" variant="tonal" @click="editDescription" />
            <v-btn icon="mdi-eye-off-outline" size="small" variant="tonal" title="Ausblenden" @click="show_infos = false" />
        </template>
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2" v-if="action != 'edit_description'">
                <v-card variant="outlined" class="mt-2" v-if="openNotifications.length">
                    <v-card-title class="text-subtitle-2 d-flex align-center ga-2 flex-wrap">
                        <v-icon size="18">mdi-bell-alert</v-icon>
                        Offene Verständigungen
                        <v-chip size="x-small" color="warning" variant="flat">{{ openNotifications.length }}</v-chip>
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item v-for="entry in openNotifications" :key="entry.id">
                                <div class="notification-row d-flex flex-wrap align-start ga-2 w-100">
                                    <v-chip v-if="entry.date" size="x-small" variant="tonal" color="primary">{{ formatDate(entry.date) }}</v-chip>
                                    <v-chip v-if="entry.due_date" size="x-small" variant="tonal" :color="dueDateColor(entry.due_date)">Fällig bis {{ formatDate(entry.due_date) }}</v-chip>
                                    <v-chip v-if="entry.type" size="x-small" variant="outlined" color="secondary" class="chip-truncate">{{ notificationTypeLabel(entry.type) }}</v-chip>
                                    <v-chip size="x-small" variant="outlined" class="chip-truncate">{{ studentLabel(entry.user_id) }}</v-chip>
                                    <v-btn
                                        icon="mdi-check"
                                        size="x-small"
                                        color="success"
                                        variant="tonal"
                                        class="notification-action"
                                        @click.stop="completeNotification(entry)" />
                                    <div v-if="entry.description" class="notification-description text-caption w-100">{{ entry.description }}</div>
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!openNotifications.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine offenen Verständigungen.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-2">
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item>
                                <div class="d-flex align-center justify-space-between ga-2 w-100">
                                    <div class="text-caption text-medium-emphasis">{{ representativeCountdownLabel }}</div>
                                    <div :class="representativeCountdownValueClass">{{ representativeCountdownValue }}</div>
                                </div>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
                <div class="text-caption text-medium-emphasis">{{ schemaName }}</div>
                <div class="text-body-2 course-description" v-if="selected_course.description" v-html="descriptionHtml"></div>
                <div class="text-body-2" v-else>Keine Fachinfos vorhanden.</div>

            </v-card-text>
            <v-card-text v-if="action == 'edit_description'">
                <v-form ref="form" @submit.prevent="saveDescription">
                    <div class="mb-4">
                        <label class="text-caption text-medium-emphasis">Fachinfos</label>
                        <its-rich-text-editor v-model="edit_description" />
                    </div>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="warning" flat tile @click="abortEditDescription">Abbruch</v-btn>
                        <v-btn color="success" flat tile type="submit">Speichern</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { parseLocalDate } from '@/helpers/date'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsRichTextEditor from '@/components/ItsRichTextEditor.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox, ItsMenuButton, ItsRichTextEditor },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.behaviourEntryStore = useCourseBehaviourEntryStore()
        this.schoolHourStore = useSchoolHourStore()
        this.teachingStore = useTeachingStore()
        if (!Array.isArray(this.school_hours) || this.school_hours.length === 0) {
            await this.schoolHourStore.index()
        }
        if (this.selected_course?.id) {
            await this.behaviourEntryStore.indexByCourse(this.selected_course.id)
        }
    },

    mounted() {
        this.nowTimer = setInterval(() => {
            this.nowTs = Date.now()
        }, 1000)
    },
    unmounted() {
        if (this.nowTimer) clearInterval(this.nowTimer)
    },

    data() {
        return {
            adminStore: null,
            courseStore: null,
            behaviourEntryStore: null,
            schoolHourStore: null,
            teachingStore: null,
            is_valid: false,
            data: {
                selected_classes: [],
            },
            edit_description: '',
            delete_level: 0,
            nowTs: Date.now(),
            nowTimer: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, ['courses', 'classes', 'selected_course', 'show_infos']),
        ...mapWritableState(useSchoolHourStore, ['school_hours']),

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
        nextCourseStartAt() {
            const now = new Date(this.nowTs)
            const dates = Array.isArray(this.selected_course?.course_dates) ? this.selected_course.course_dates : []
            let nearestStartTs = null

            dates.forEach((courseDate) => {
                const date = (courseDate?.date || '').toString().slice(0, 10)
                const hours = Array.isArray(courseDate?.hours)
                    ? [...courseDate.hours]
                        .map((hour) => Number(hour))
                        .filter((hour) => Number.isFinite(hour))
                        .sort((a, b) => a - b)
                    : []

                hours.forEach((hour) => {
                    const start = this.lessonStartFromHour(date, hour)
                    if (!start || start.getTime() <= now.getTime()) {
                        return
                    }

                    if (nearestStartTs === null || start.getTime() < nearestStartTs) {
                        nearestStartTs = start.getTime()
                    }
                })
            })

            return nearestStartTs ? new Date(nearestStartTs) : null
        },
        activeCourseEndAt() {
            const now = new Date(this.nowTs)
            const dates = Array.isArray(this.selected_course?.course_dates) ? this.selected_course.course_dates : []
            let nearestEndTs = null

            dates.forEach((courseDate) => {
                const date = (courseDate?.date || '').toString().slice(0, 10)
                const hours = Array.isArray(courseDate?.hours)
                    ? [...courseDate.hours]
                        .map((hour) => Number(hour))
                        .filter((hour) => Number.isFinite(hour))
                        .sort((a, b) => a - b)
                    : []

                hours.forEach((hour) => {
                    const start = this.lessonStartFromHour(date, hour)
                    const end = this.lessonEndFromHour(date, hour)
                    if (!start || !end) {
                        return
                    }

                    if (now.getTime() < start.getTime() || now.getTime() >= end.getTime()) {
                        return
                    }

                    if (nearestEndTs === null || end.getTime() < nearestEndTs) {
                        nearestEndTs = end.getTime()
                    }
                })
            })

            return nearestEndTs ? new Date(nearestEndTs) : null
        },
        startsInLabel() {
            if (!this.nextCourseStartAt) {
                return '–'
            }

            const diffSeconds = Math.max(0, Math.floor((this.nextCourseStartAt.getTime() - this.nowTs) / 1000))
            const days = Math.floor(diffSeconds / 86400)
            const hours = Math.floor((diffSeconds % 86400) / 3600)
            const minutes = Math.floor((diffSeconds % 3600) / 60)

            const parts = []
            if (days > 0) {
                parts.push(`${days}d`)
            }
            if (hours > 0) {
                parts.push(`${this.padTwo(hours)}h`)
            }
            parts.push(`${this.padTwo(minutes)}m`)

            return parts.join(' ')
        },
        endsInLabel() {
            if (!this.activeCourseEndAt) {
                return '–'
            }

            const diffSeconds = Math.max(0, Math.floor((this.activeCourseEndAt.getTime() - this.nowTs) / 1000))
            const hours = Math.floor(diffSeconds / 3600)
            const minutes = Math.floor((diffSeconds % 3600) / 60)

            if (hours > 0) {
                return `${this.padTwo(hours)}h ${this.padTwo(minutes)}m`
            }

            return `${this.padTwo(minutes)}m`
        },
        isCourseActiveNow() {
            return !!this.activeCourseEndAt
        },
        representativeCountdownLabel() {
            return this.isCourseActiveNow ? 'Endet in:' : 'Findet statt in:'
        },
        representativeCountdownValue() {
            return this.isCourseActiveNow ? this.endsInLabel : this.startsInLabel
        },
        representativeCountdownValueClass() {
            if (this.isCourseActiveNow) {
                return 'text-body-1 font-weight-medium text-primary'
            }

            return 'text-body-2 font-weight-medium'
        },

        schemaName() {
            const schemaId = this.selected_course?.teaching_schema_id
            if (!schemaId) return 'Kein Schema zugewiesen'
            const schema = this.teachingStore?.schemaById(schemaId)
            return schema ? `Schema: ${schema.name}` : 'Kein Schema zugewiesen'
        },
        selectedCourseClasses() {
            if (!this.selected_course?.classes?.length) return ''
            // If it's already a string with commas
            if (typeof this.selected_course.classes === 'string') {
                return this.selected_course.classes.replace(/,/g, ', ')
            }
            return this.selected_course.classes.join(', ')
        },

        descriptionHtml() {
            const text = this.selected_course?.description
            if (!text) return ''
            // If already HTML, return as-is
            if (text.includes('<p>') || text.includes('<br')) return text
            // Convert plain text line breaks to HTML paragraphs
            return text
                .split('\n')
                .map((line) => `<p>${line || '<br>'}</p>`)
                .join('')
        },
        openNotifications() {
            const entries = this.behaviourEntryStore?.courseEntries || []
            return entries
                .filter((entry) => entry.kind === 'notification' && !!entry.due_date && !entry.done_date)
                .sort((a, b) => {
                    const dueA = a.due_date || '9999-12-31'
                    const dueB = b.due_date || '9999-12-31'
                    if (dueA !== dueB) return dueA.localeCompare(dueB)
                    const dateA = a.date || '9999-12-31'
                    const dateB = b.date || '9999-12-31'
                    return dateA.localeCompare(dateB)
                })
        },
        notificationTypesByShort() {
            const map = new Map()
            const list = this.teachingStore?.settings?.teaching_notifications || []
            list.forEach((item) => {
                if (item?.short_name) map.set(item.short_name, item.name || '')
            })
            return map
        },
    },

    watch: {
        selected_course: {
            async handler(course) {
                if (!course?.id) {
                    if (this.behaviourEntryStore) this.behaviourEntryStore.courseEntries = []
                    return
                }
                await this.behaviourEntryStore?.indexByCourse(course.id)
            },
        },
    },

    methods: {
        editDescription() {
            this.edit_description = this.selected_course.description || ''
            this.action = 'edit_description'
        },
        abortEditDescription() {
            this.action = ''
            this.edit_description = ''
        },
        async saveDescription() {
            const data = {
                ...this.selected_course,
                description: this.edit_description,
            }
            if (await this.courseStore.update(data)) {
                this.selected_course.description = this.edit_description
                this.action = ''
                this.edit_description = ''
            }
        },
        notificationTypeLabel(type) {
            if (!type) return ''
            const name = this.notificationTypesByShort.get(type)
            return name ? `${type} - ${name}` : type
        },
        studentLabel(userId) {
            const student = (this.selected_course?.students_info || []).find((s) => s.id === userId)
            if (!student) return 'Schüler:in'
            return `${student.last_name || ''}, ${student.first_name || ''}`.trim().replace(/^,\s*/, '')
        },
        formatDate(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        dueDateColor(date) {
            const due = parseLocalDate(date)
            if (isNaN(due.getTime())) return 'warning'
            due.setHours(0, 0, 0, 0)
            const today = new Date()
            today.setHours(0, 0, 0, 0)
            return due <= today ? 'error' : 'warning'
        },
        toDateString(date) {
            const d = parseLocalDate(date)
            const year = d.getFullYear()
            const month = String(d.getMonth() + 1).padStart(2, '0')
            const day = String(d.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
        lessonStartFromHour(dateStr, hour) {
            if (!dateStr) return null

            const schoolHour = this.schoolHoursByHour[Number(hour)]
            const fromValue = (schoolHour?.from || '').toString().trim()
            if (!fromValue) return null

            const date = parseLocalDate(dateStr)
            if (isNaN(date.getTime())) return null

            const parts = fromValue.split(':').map((part) => Number(part))
            if (!Number.isFinite(parts[0]) || !Number.isFinite(parts[1])) return null

            date.setHours(parts[0], parts[1], Number.isFinite(parts[2]) ? parts[2] : 0, 0)
            return date
        },
        lessonEndFromHour(dateStr, hour) {
            if (!dateStr) return null

            const schoolHour = this.schoolHoursByHour[Number(hour)]
            const untilValue = (schoolHour?.until || '').toString().trim()
            if (!untilValue) return null

            const date = parseLocalDate(dateStr)
            if (isNaN(date.getTime())) return null

            const parts = untilValue.split(':').map((part) => Number(part))
            if (!Number.isFinite(parts[0]) || !Number.isFinite(parts[1])) return null

            date.setHours(parts[0], parts[1], Number.isFinite(parts[2]) ? parts[2] : 0, 0)
            return date
        },
        padTwo(value) {
            return String(value).padStart(2, '0')
        },
        async completeNotification(entry) {
            if (!entry?.id || !entry?.type) return
            const payload = {
                id: entry.id,
                kind: 'notification',
                type: entry.type,
                date: entry.date || null,
                description: entry.description || null,
                is_due: !!entry.due_date,
                due_date: entry.due_date || null,
                is_done: true,
                done_date: this.toDateString(new Date()),
            }
            await this.behaviourEntryStore.update(payload)
        },
    },
}
</script>

<style scoped>
.course-description :deep(p) {
    margin: 0;
    min-height: 1.2em;
}

.notification-row {
    min-width: 0;
}

.chip-truncate {
    max-width: 100%;
}

.chip-truncate :deep(.v-chip__content) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.notification-description {
    word-break: break-word;
}

.notification-action {
    margin-left: auto;
}
</style>
