<template>
    <ItsGridBox
        variant="overview"
        color="primary"
        :title="selected_course.title + ' (' + selectedCourseClasses + ')'"
        icon="mdi-information-box"
        class="w-100"
        v-if="selected_course"
        :disabled="isInfoLocked">
        <template #header-actions>
            <v-btn v-if="action !== 'edit_description'" icon="mdi-pencil" size="small" variant="tonal" :disabled="isSavingInfo" @click="editDescription" />
        </template>
        <v-card tile flat color="transparent" class="w-100" :disabled="isSavingInfo">
            <v-card-text class="text-body-1 d-flex flex-column ga-2" v-if="action != 'edit_description'">
                <div class="course-infos-grid mt-2">
                    <v-card variant="outlined" v-if="openNotifications.length" class="course-info-card">
                        <v-card-title class="text-subtitle-2 d-flex align-center ga-2 flex-wrap">
                            <v-icon size="18">mdi-bell-alert</v-icon>
                            Offene Erinnerungen und Verständigungen
                            <v-chip size="x-small" color="warning" variant="flat">{{ openNotifications.length }}</v-chip>
                        </v-card-title>
                        <v-divider />
                        <v-card-text class="pa-0">
                            <v-list density="compact" class="open-notifications-list">
                                <v-list-item v-for="entry in openNotifications" :key="entry.id" class="open-notification-item">
                                    <div class="notification-row open-notification-block d-flex flex-wrap align-start ga-2 w-100" :class="notificationEntryBackgroundClass(entry)">
                                        <div class="open-notification-student-name">
                                            {{ studentLabel(entry.user_id) }}
                                        </div>
                                        <v-chip v-if="entry.date" size="x-small" variant="tonal" color="primary">{{ formatDate(entry.date) }}</v-chip>
                                        <v-chip v-if="entry.due_date" size="x-small" variant="tonal" :color="dueDateColor(entry.due_date)">{{ entry.type ? 'Fällig bis' : 'Erinnern am' }} {{ formatDate(entry.due_date) }}<span v-if="entry.due_time">&nbsp;um {{ entry.due_time.slice(0, 5) }}</span></v-chip>
                                        <v-chip v-if="entry.type" size="x-small" variant="outlined" color="secondary" class="chip-truncate">{{ notificationTypeLabel(entry.type) }}</v-chip>
                                        <v-btn
                                            icon="mdi-check"
                                            size="x-small"
                                            color="success"
                                            variant="tonal"
                                            :disabled="isSavingInfo"
                                            :loading="saving_notification_id === entry.id"
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

                    <v-card variant="outlined" class="course-info-card course-info-summary-card">
                        <v-card-text class="course-info-summary-card__content">
                            <div class="course-info-block course-info-block--countdown">
                                <div class="text-caption text-medium-emphasis">{{ representativeCountdownLabel }}</div>
                                <div :class="representativeCountdownValueClass">{{ representativeCountdownValue }}</div>
                            </div>
                            <div class="course-info-block course-info-block--schema">
                                <div class="text-caption text-medium-emphasis">Benotungsschema</div>
                                <div class="text-body-2">{{ schemaName }}</div>
                            </div>
                            <div v-if="selected_course.description" class="course-info-block course-info-block--description">
                                <div class="text-caption text-medium-emphasis mb-1">Fachinfos</div>
                                <div class="text-body-2 course-description" v-html="descriptionHtml"></div>
                            </div>
                        </v-card-text>
                    </v-card>
                </div>

                <v-card variant="outlined" class="mt-3 course-student-display-card">
                    <v-card-title class="text-subtitle-2 d-flex align-center ga-2">
                        <v-icon size="18">mdi-account-eye-outline</v-icon>
                        Schüler:innen-Anzeige
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="d-flex flex-wrap ga-3 py-2">
                        <v-checkbox
                            :model-value="student_display_show_age"
                            label="Alter"
                            density="compact"
                            hide-details
                            :disabled="isSavingInfo"
                            @update:model-value="saveStudentDisplaySetting('teaching_show_student_age', $event)" />
                        <v-checkbox
                            :model-value="student_display_show_last_login"
                            label="Last Login"
                            density="compact"
                            hide-details
                            :disabled="isSavingInfo"
                            @update:model-value="saveStudentDisplaySetting('teaching_show_student_last_login', $event)" />
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-3 course-student-grades-card">
                    <v-card-title class="text-subtitle-2 d-flex align-center ga-2 flex-wrap">
                        <v-icon size="18">mdi-eye-outline</v-icon>
                        Punktefortschritt auf der Schüler:innen-Seite
                    </v-card-title>
                    <v-divider />
                    <v-card-text>
                        <div class="text-body-2 text-medium-emphasis mb-2">
                            Zeige die vorhandenen Punktebereiche dieses Kurses. Änderungen werden sofort gespeichert.
                        </div>
                        <div class="student-grade-switches">
                            <v-switch
                                :model-value="studentPointsProgressVisible"
                                label="Punktefortschritt für Schüler:innen anzeigen"
                                color="primary"
                                inset
                                hide-details
                                :disabled="isSavingInfo"
                                :loading="saving_info_action === 'show_points_progress'"
                                @update:model-value="saveAssignedGradeVisibility('show_points_progress', $event)" />
                            <v-switch
                                :model-value="studentStarsVisible"
                                label="Sterne für Schüler:innen anzeigen"
                                color="primary"
                                inset
                                hide-details
                                :disabled="isSavingInfo"
                                :loading="saving_info_action === 'show_stars'"
                                @update:model-value="saveAssignedGradeVisibility('show_stars', $event)" />
                        </div>
                        <v-divider class="my-4" />
                        <div class="text-subtitle-2 mb-2">Vergebene Noten auf der Schüler:innen-Seite</div>
                        <div class="student-grade-switches">
                            <v-switch
                                :model-value="studentSemesterGradeVisible"
                                label="Semesternote anzeigen"
                                color="primary"
                                inset
                                hide-details
                                :disabled="isSavingInfo"
                                :loading="saving_info_action === 'show_semester_grade'"
                                @update:model-value="saveAssignedGradeVisibility('show_semester_grade', $event)" />
                            <v-switch
                                :model-value="studentBehaviourGradeVisible"
                                label="Verhaltensnote anzeigen"
                                color="primary"
                                inset
                                hide-details
                                :disabled="isSavingInfo"
                                :loading="saving_info_action === 'show_behaviour_grade'"
                                @update:model-value="saveAssignedGradeVisibility('show_behaviour_grade', $event)" />
                        </div>
                    </v-card-text>
                </v-card>

            </v-card-text>
            <v-card-text v-if="action == 'edit_description'">
                <v-form ref="form" @submit.prevent="saveDescription">
                    <div class="mb-4">
                        <label class="text-caption text-medium-emphasis">Fachinfos</label>
                        <its-rich-text-editor v-model="edit_description" :disabled="isSavingDescription" />
                    </div>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="warning" flat tile :disabled="isSavingDescription" @click="abortEditDescription">Abbruch</v-btn>
                        <v-btn color="success" flat tile type="submit" :loading="isSavingDescription" :disabled="isSavingDescription">Speichern</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>
<script>
import { defineAsyncComponent } from 'vue'
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

const ItsRichTextEditor = defineAsyncComponent(() => import('@/components/ItsRichTextEditor.vue'))

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
        this.restoreStudentDisplaySettings(this.selected_course)
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
            saving_info_action: null,
            saving_notification_id: null,
            student_display_show_age: false,
            student_display_show_last_login: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, [
            'courses',
            'classes',
            'selected_course',
        ]),
        ...mapWritableState(useSchoolHourStore, ['school_hours']),
        isSavingInfo() {
            return this.saving_info_action !== null
        },
        isSavingDescription() {
            return this.saving_info_action === 'save-description'
        },
        isInfoLocked() {
            return this.isSavingInfo || (this.action != '' && this.action != 'edit_description')
        },

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
                if (!hours.length) {
                    return
                }

                const start = this.lessonStartFromHour(date, hours[0])
                if (!start || start.getTime() <= now.getTime()) {
                    return
                }

                if (nearestStartTs === null || start.getTime() < nearestStartTs) {
                    nearestStartTs = start.getTime()
                }
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
                if (!hours.length) {
                    return
                }

                const start = this.lessonStartFromHour(date, hours[0])
                const end = this.lessonEndFromHour(date, hours[hours.length - 1])
                if (!start || !end || end.getTime() <= start.getTime()) {
                    return
                }

                if (now.getTime() < start.getTime() || now.getTime() >= end.getTime()) {
                    return
                }

                if (nearestEndTs === null || end.getTime() < nearestEndTs) {
                    nearestEndTs = end.getTime()
                }
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
        selectedCourseSchema() {
            const courseSchema = this.selected_course?.teacher_teaching_schema
            if (courseSchema?.id) {
                return courseSchema
            }

            const schemaId = this.selected_course?.teaching_schema_id
            return schemaId ? this.teachingStore?.schemaById(schemaId) : null
        },
        selectedCourseEntryArea() {
            const entryArea = this.selected_course?.teaching_entry_area

            return entryArea?.id ? entryArea : null
        },
        schemaName() {
            if (this.selectedCourseEntryArea) {
                return this.selectedCourseEntryArea.name || 'Kein Schema zugewiesen'
            }

            const schema = this.selectedCourseSchema
            return schema ? schema.name : 'Kein Schema zugewiesen'
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
            const list = this.selected_course?.teacher_teaching_notifications || this.teachingStore?.settings?.teaching_notifications || []
            list.forEach((item) => {
                if (item?.short_name) map.set(item.short_name, item.name || '')
            })
            return map
        },
        persistedStudentGradeColumns() {
            return this.selected_course?.teaching_student_grade_columns
                || {}
        },
        studentSemesterGradeVisible() {
            return this.selected_course?.teaching_student_grade_columns?.show_semester_grade !== false
        },
        studentBehaviourGradeVisible() {
            return this.selected_course?.teaching_student_grade_columns?.show_behaviour_grade !== false
        },
        studentPointsProgressVisible() {
            return this.selected_course?.teaching_student_grade_columns?.show_points_progress === true
        },
        studentStarsVisible() {
            return this.selected_course?.teaching_student_grade_columns?.show_stars !== false
        },
    },

    watch: {
        selected_course: {
            async handler(course) {
                if (!course?.id) {
                    if (this.behaviourEntryStore) this.behaviourEntryStore.courseEntries = []
                    this.restoreStudentDisplaySettings(null)
                    return
                }
                this.courseStore?.ensureCourseStudentCollections?.(course)
                this.restoreStudentDisplaySettings(course)
                await this.behaviourEntryStore?.indexByCourse(course.id)
            },
        },
    },

    methods: {
        async runInfoMutation(action, callback, options = {}) {
            if (this.isSavingInfo) {
                return false
            }

            this.saving_info_action = action

            if (!options.skipTick) {
                await this.$nextTick()
            }

            try {
                return await callback()
            } finally {
                this.saving_info_action = null
            }
        },
        restoreStudentDisplaySettings(course) {
            this.student_display_show_age = Boolean(course?.teaching_show_student_age)
            this.student_display_show_last_login = Boolean(course?.teaching_show_student_last_login)
        },
        async saveStudentDisplaySetting(attribute, value) {
            if (!this.selected_course?.id || this.isSavingInfo) {
                return false
            }

            const localAttribute = attribute === 'teaching_show_student_age'
                ? 'student_display_show_age'
                : 'student_display_show_last_login'
            const previousValue = Boolean(this.selected_course?.[attribute])
            const normalizedValue = Boolean(value)

            this[localAttribute] = normalizedValue

            return this.runInfoMutation('save-student-display', async () => {
                const saved = await this.courseStore.update({
                    ...this.selected_course,
                    [attribute]: normalizedValue,
                })

                if (!saved) {
                    this[localAttribute] = previousValue
                    return false
                }

                const refreshedCourse = await this.courseStore.refreshCourseById(this.selected_course.id)
                if (refreshedCourse) {
                    this.restoreStudentDisplaySettings(refreshedCourse)
                }

                return true
            })
        },
        async saveAssignedGradeVisibility(attribute, value) {
            if (!this.teachingStore || !this.selected_course?.id || this.isSavingInfo) {
                return false
            }

            const course = this.selected_course
            const columns = {
                ...this.persistedStudentGradeColumns,
                [attribute]: Boolean(value),
            }

            return this.runInfoMutation(attribute, async () => {
                const saved = await this.teachingStore.saveSettings({
                    teaching_course_id: course.id,
                    teaching_student_grade_columns: { [attribute]: Boolean(value) },
                }, {
                    notifySuccess: false,
                })

                if (saved) {
                    course.teaching_student_grade_columns = columns
                }

                return saved
            })
        },
        editDescription() {
            if (this.isSavingInfo) {
                return
            }
            this.edit_description = this.selected_course.description || ''
            this.action = 'edit_description'
        },
        abortEditDescription() {
            if (this.isSavingDescription) {
                return
            }
            this.action = ''
            this.edit_description = ''
        },
        async saveDescription() {
            await this.runInfoMutation('save-description', async () => {
                const data = {
                    ...this.selected_course,
                    description: this.edit_description,
                }
                if (await this.courseStore.update(data)) {
                    this.selected_course.description = this.edit_description
                    this.action = ''
                    this.edit_description = ''
                }
            })
        },
        notificationTypeLabel(type) {
            if (!type) return ''
            const name = this.notificationTypesByShort.get(type)
            return name ? `${type} - ${name}` : type
        },
        notificationEntryBackgroundClass(entry) {
            const type = String(entry?.type || '').trim()
            if (type === '') {
                return 'open-notification-block--type-empty'
            }

            let hash = 0
            for (const character of type) {
                hash = (hash + character.charCodeAt(0)) % 6
            }

            return `open-notification-block--type-${hash + 1}`
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
            if (!entry?.id) return
            this.saving_notification_id = entry.id

            try {
                await this.runInfoMutation('complete-notification', async () => {
                    const payload = {
                        id: entry.id,
                        kind: 'notification',
                        type: entry.type,
                        date: entry.date || null,
                        description: entry.description || null,
                        is_due: !!entry.due_date,
                        due_date: entry.due_date?.slice(0, 10) || null,
                        due_time: entry.due_time?.slice(0, 5) || null,
                        is_done: true,
                        done_date: this.toDateString(new Date()),
                    }
                    await this.behaviourEntryStore.update(payload)
                })
            } finally {
                this.saving_notification_id = null
            }
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

.open-notification-item {
    align-items: stretch;
}

.open-notification-item :deep(.v-list-item__content) {
    width: 100%;
}

.open-notification-block {
    border: 1px solid transparent;
    border-radius: 8px;
    box-sizing: border-box;
    min-height: 100%;
    padding: 8px;
}

.open-notification-student-name {
    color: #0f172a;
    flex-basis: 100%;
    font-size: 0.9rem;
    font-weight: 750;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.open-notification-block--type-empty {
    background-color: #ffffff !important;
    border-color: rgba(148, 163, 184, 0.18);
}

.open-notification-block--type-1 {
    background-color: #eef6ff !important;
    border-color: rgba(37, 99, 235, 0.16);
}

.open-notification-block--type-2 {
    background-color: #f0fdf4 !important;
    border-color: rgba(22, 163, 74, 0.16);
}

.open-notification-block--type-3 {
    background-color: #fff7ed !important;
    border-color: rgba(234, 88, 12, 0.16);
}

.open-notification-block--type-4 {
    background-color: #f5f3ff !important;
    border-color: rgba(124, 58, 237, 0.16);
}

.open-notification-block--type-5 {
    background-color: #fef2f2 !important;
    border-color: rgba(220, 38, 38, 0.14);
}

.open-notification-block--type-6 {
    background-color: #ecfeff !important;
    border-color: rgba(8, 145, 178, 0.16);
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
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}

.notification-action {
    margin-left: auto;
}

.course-infos-grid {
    display: grid;
    gap: 10px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.course-info-card {
    min-width: 0;
}

.course-info-summary-card__content {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px;
}

.course-info-block {
    border: 1px solid transparent;
    border-radius: 8px;
    min-width: 0;
    padding: 10px 12px;
}

.course-info-block--countdown {
    background-color: #eef6ff;
    border-color: rgba(37, 99, 235, 0.16);
}

.course-info-block--schema {
    background-color: #f0fdf4;
    border-color: rgba(22, 163, 74, 0.16);
}

.course-info-block--description {
    background-color: #fff7ed;
    border-color: rgba(234, 88, 12, 0.16);
}

.student-grade-switches {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));
    gap: 8px 24px;
}

@media (max-width: 700px) {
    .course-infos-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
