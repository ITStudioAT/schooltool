<template>
    <ItsGridBox variant="overview" color="primary" title="Infos" icon="mdi-information-outline" class="w-100">
        <template #header-actions>
            <v-btn icon="mdi-eye-off-outline" size="small" variant="tonal" title="Ausblenden" @click="show_my_infos = false" />
        </template>
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <v-card variant="outlined" class="mt-2" v-if="openNotifications.length">
                    <v-card-title class="text-subtitle-2 d-flex align-center ga-2 flex-wrap">
                        <v-icon size="18">mdi-bell-alert</v-icon>
                        Offene Verständigungen (alle Fächer)
                        <v-chip size="x-small" color="warning" variant="flat">{{ openNotifications.length }}</v-chip>
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item v-for="entry in openNotifications" :key="entry.id">
                                <div class="notification-row d-flex flex-wrap align-start ga-2 w-100">
                                    <v-chip v-if="classCourseLabel(entry)" size="x-small" variant="tonal" color="primary" class="chip-truncate">{{ classCourseLabel(entry) }}</v-chip>
                                    <v-chip v-if="entry.student_label" size="x-small" variant="outlined" class="chip-truncate">{{ entry.student_label }}</v-chip>
                                    <v-chip v-if="entry.date" size="x-small" variant="tonal" color="primary">{{ formatDate(entry.date) }}</v-chip>
                                    <v-chip v-if="entry.due_date" size="x-small" variant="tonal" :color="dueDateColor(entry.due_date)">Fällig bis {{ formatDate(entry.due_date) }}</v-chip>
                                    <v-chip v-if="entry.type" size="x-small" variant="outlined" color="secondary" class="chip-truncate">{{ notificationTypeLabel(entry.type) }}</v-chip>
                                    <v-btn
                                        icon="mdi-open-in-new"
                                        size="x-small"
                                        color="primary"
                                        variant="tonal"
                                        @click="jumpToCourseStudent(entry)" />
                                    <div class="notification-description text-caption w-100">{{ entry.description || '' }}</div>
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!openNotifications.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine offenen Verständigungen.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-2">
                    <v-card-title class="text-subtitle-2 d-flex align-center ga-2 flex-wrap">
                        <v-icon size="18">mdi-chart-box-outline</v-icon>
                        Allgemeine Infos
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item v-if="showActiveLessonEndCountdown">
                                <div class="d-flex align-center justify-space-between ga-2 w-100">
                                    <div class="text-caption text-medium-emphasis">Untericht endet in:</div>
                                    <div class="text-body-1 font-weight-medium text-primary">{{ activeLessonEndsInDisplay }}</div>
                                </div>
                            </v-list-item>
                            <v-list-item>
                                <div class="d-flex align-center justify-space-between ga-2 w-100">
                                    <div class="text-caption text-medium-emphasis">Heute</div>
                                    <div class="text-body-2 font-weight-medium">{{ nowLabel }}</div>
                                </div>
                            </v-list-item>
                            <v-list-item v-if="showLessonCountdownRows">
                                <div class="d-flex align-center justify-space-between ga-2 w-100">
                                    <div class="text-caption text-medium-emphasis">Nächster Unterricht in</div>
                                    <div class="text-body-2 font-weight-medium">{{ nextLessonCountdownLabel }}</div>
                                </div>
                            </v-list-item>
                            <v-list-item>
                                <div class="d-flex align-center justify-space-between ga-2 w-100">
                                    <div class="text-caption text-medium-emphasis">Meine Kurse</div>
                                    <v-chip size="x-small" variant="tonal" color="primary">{{ myCourses.length }}</v-chip>
                                </div>
                            </v-list-item>
                            <v-list-item>
                                <div class="d-flex align-center justify-space-between ga-2 w-100">
                                    <div class="text-caption text-medium-emphasis">Meine Schüler:innen</div>
                                    <v-chip size="x-small" variant="tonal" color="primary">{{ myStudentCount }}</v-chip>
                                </div>
                            </v-list-item>
                            <v-list-item>
                                <div class="d-flex align-center justify-space-between ga-2 w-100">
                                    <div class="text-caption text-medium-emphasis">Tage seit Schuljahresbeginn</div>
                                    <v-chip size="x-small" variant="tonal" color="primary">{{ daysSinceSchoolyearStart }}</v-chip>
                                </div>
                            </v-list-item>
                            <v-list-item>
                                <div class="d-flex align-center justify-space-between ga-2 w-100">
                                    <div class="text-caption text-medium-emphasis">Tage bis Schuljahresende</div>
                                    <v-chip size="x-small" variant="tonal" color="primary">{{ daysUntilSchoolyearEnd }}</v-chip>
                                </div>
                            </v-list-item>
                            <v-list-item>
                                <div class="d-flex flex-column ga-2 w-100">
                                    <div class="d-flex align-center justify-space-between ga-2 w-100">
                                        <div class="text-caption text-medium-emphasis">Schuljahr-Fortschritt</div>
                                        <div class="text-caption text-medium-emphasis">{{ schoolyearProgressSince }}%</div>
                                    </div>
                                    <v-progress-linear
                                        :model-value="schoolyearProgressSince"
                                        height="10"
                                        rounded
                                        color="success"
                                        bg-color="warning" />
                                </div>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>

<script>
import { parseLocalDate } from '@/helpers/date'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { ItsGridBox },

    async beforeMount() {
        this.courseStore = useCourseStore()
        this.schoolHourStore = useSchoolHourStore()
        this.teachingStore = useTeachingStore()
        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
        }
        if (!Array.isArray(this.school_hours) || this.school_hours.length === 0) {
            await this.schoolHourStore.index()
        }
        await this.loadOpenNotifications()
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
            courseStore: null,
            schoolHourStore: null,
            teachingStore: null,
            openNotifications: [],
            nowTs: Date.now(),
            nowTimer: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action_2']),
        ...mapWritableState(useCourseStore, ['courses', 'selected_course', 'selected_course_id', 'selected_course_student', 'show_my_infos']),
        ...mapWritableState(useSchoolHourStore, ['school_hours']),
        myCourses() {
            const userId = this.config?.user?.id
            const list = Array.isArray(this.courses) ? this.courses : []
            if (!userId) return []
            return list.filter((course) => course?.user_id === userId)
        },
        myCourseIds() {
            return (this.myCourses || []).map((course) => course?.id).filter((id) => !!id)
        },
        nowLabel() {
            const d = new Date(this.nowTs)
            return d.toLocaleString('de-DE', {
                weekday: 'long',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            })
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
        hasConfiguredSchoolHours() {
            return Object.keys(this.schoolHoursByHour).length > 0
        },
        hasCourseHoursInMyCourses() {
            const courses = Array.isArray(this.myCourses) ? this.myCourses : []

            return courses.some((course) => {
                const courseDates = Array.isArray(course?.course_dates) ? course.course_dates : []
                return courseDates.some((courseDate) => {
                    const hours = Array.isArray(courseDate?.hours) ? courseDate.hours : []
                    return hours.some((hour) => Number.isFinite(Number(hour)))
                })
            })
        },
        showLessonCountdownRows() {
            return this.hasConfiguredSchoolHours && this.hasCourseHoursInMyCourses
        },
        showActiveLessonEndCountdown() {
            return this.showLessonCountdownRows && !!this.activeLessonEndAt
        },
        nextLessonStartAt() {
            const now = new Date(this.nowTs)
            const courses = Array.isArray(this.myCourses) ? this.myCourses : []
            let nearestStartTs = null

            courses.forEach((course) => {
                const courseDates = Array.isArray(course?.course_dates) ? course.course_dates : []
                courseDates.forEach((courseDate) => {
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
            })

            return nearestStartTs ? new Date(nearestStartTs) : null
        },
        activeLessonEndAt() {
            const now = new Date(this.nowTs)
            const courses = Array.isArray(this.myCourses) ? this.myCourses : []
            let nearestEndTs = null

            courses.forEach((course) => {
                const courseDates = Array.isArray(course?.course_dates) ? course.course_dates : []
                courseDates.forEach((courseDate) => {
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
            })

            return nearestEndTs ? new Date(nearestEndTs) : null
        },
        activeLessonEndsInDisplay() {
            if (!this.activeLessonEndAt) {
                return '–'
            }

            const diffSeconds = Math.max(0, Math.floor((this.activeLessonEndAt.getTime() - this.nowTs) / 1000))
            const hours = Math.floor(diffSeconds / 3600)
            const minutes = Math.floor((diffSeconds % 3600) / 60)

            if (hours > 0) {
                return `${this.padTwo(hours)}h ${this.padTwo(minutes)}m`
            }

            return `${this.padTwo(minutes)}m`
        },
        nextLessonCountdownLabel() {
            if (!this.nextLessonStartAt) {
                return '–'
            }

            const diffSeconds = Math.max(0, Math.floor((this.nextLessonStartAt.getTime() - this.nowTs) / 1000))
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
            if (minutes > 0) {
                parts.push(`${this.padTwo(minutes)}m`)
            }

            if (!parts.length) {
                return '< 1m'
            }

            return parts.join(' ')
        },
        myStudentCount() {
            const ids = new Set()
            ;(this.myCourses || []).forEach((course) => {
                const studentsInfo = Array.isArray(course?.students_info) ? course.students_info : []
                if (studentsInfo.length) {
                    studentsInfo.forEach((student) => {
                        if (student?.canceled_at) return
                        if (student?.id != null) ids.add(String(student.id))
                    })
                    return
                }

                const studentsRaw = Array.isArray(course?.students) ? course.students : []
                studentsRaw.forEach((student) => {
                    if (student == null) return
                    if (typeof student === 'object') {
                        if (student.id != null) ids.add(String(student.id))
                        return
                    }
                    ids.add(String(student))
                })
            })
            return ids.size
        },
        schoolyearFrom() {
            return this.config?.selected_schoolyear?.from || null
        },
        schoolyearUntil() {
            return this.config?.selected_schoolyear?.until || null
        },
        daysSinceSchoolyearStart() {
            if (!this.schoolyearFrom) return '–'
            return this.daysDiffFromToday(this.schoolyearFrom, 'since')
        },
        daysUntilSchoolyearEnd() {
            if (!this.schoolyearUntil) return '–'
            return this.daysDiffFromToday(this.schoolyearUntil, 'until')
        },
        schoolyearDurationDays() {
            if (!this.schoolyearFrom || !this.schoolyearUntil) return 0
            const start = parseLocalDate(this.schoolyearFrom)
            const end = parseLocalDate(this.schoolyearUntil)
            if (isNaN(start.getTime()) || isNaN(end.getTime())) return 0
            start.setHours(0, 0, 0, 0)
            end.setHours(0, 0, 0, 0)
            const diff = Math.floor((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1
            return Math.max(0, diff)
        },
        schoolyearElapsedDays() {
            if (!this.schoolyearFrom || !this.schoolyearDurationDays) return 0
            const start = parseLocalDate(this.schoolyearFrom)
            if (isNaN(start.getTime())) return 0
            start.setHours(0, 0, 0, 0)
            const today = new Date(this.nowTs)
            today.setHours(0, 0, 0, 0)
            const elapsed = Math.floor((today.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1
            return Math.min(this.schoolyearDurationDays, Math.max(0, elapsed))
        },
        schoolyearRemainingDays() {
            if (!this.schoolyearUntil || !this.schoolyearDurationDays) return 0
            const end = parseLocalDate(this.schoolyearUntil)
            if (isNaN(end.getTime())) return 0
            end.setHours(0, 0, 0, 0)
            const today = new Date(this.nowTs)
            today.setHours(0, 0, 0, 0)
            const remaining = Math.floor((end.getTime() - today.getTime()) / (1000 * 60 * 60 * 24))
            return Math.min(this.schoolyearDurationDays, Math.max(0, remaining))
        },
        schoolyearProgressSince() {
            if (!this.schoolyearDurationDays) return 0
            return Math.round((this.schoolyearElapsedDays / this.schoolyearDurationDays) * 100)
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
        myCourseIds: {
            async handler() {
                await this.loadOpenNotifications()
            },
            deep: false,
        },
    },

    methods: {
        async loadOpenNotifications() {
            if (!this.myCourseIds.length) {
                this.openNotifications = []
                return
            }

            const myCoursesById = new Map((this.myCourses || []).map((course) => [course.id, course]))

            const responses = await Promise.all(
                this.myCourseIds.map(async (courseId) => {
                    const course = myCoursesById.get(courseId)
                    if (!course) return { course: null, entries: [] }

                    try {
                        const response = await axios.get('/api/admin/teaching/course_behaviour_entries', {
                            params: { course_id: courseId },
                        })
                        return {
                            course,
                            entries: response?.data?.data || [],
                        }
                    } catch {
                        return {
                            course,
                            entries: [],
                        }
                    }
                })
            )

            this.openNotifications = responses
                .flatMap(({ course, entries }) =>
                    (entries || [])
                        .filter((entry) => entry.kind === 'notification' && !!entry.due_date && !entry.done_date)
                        .map((entry) => ({
                            ...entry,
                            course_id: course.id,
                            course_title: course.title || '',
                            student_label: this.studentLabel(course, entry.user_id),
                            student_class: this.studentClass(course, entry.user_id),
                        }))
                )
                .sort((a, b) => {
                    const dueA = a.due_date || '9999-12-31'
                    const dueB = b.due_date || '9999-12-31'
                    if (dueA !== dueB) return dueA.localeCompare(dueB)
                    const dateA = a.date || '9999-12-31'
                    const dateB = b.date || '9999-12-31'
                    return dateA.localeCompare(dateB)
                })
        },
        notificationTypeLabel(type) {
            if (!type) return ''
            const name = this.notificationTypesByShort.get(type)
            return name ? `${type} - ${name}` : type
        },
        classCourseLabel(entry) {
            const cls = (entry?.student_class || '').toString().trim()
            const course = (entry?.course_title || '').toString().trim()
            if (cls && course) return `${cls} - ${course}`
            return cls || course || ''
        },
        studentLabel(course, userId) {
            const student = this.findStudent(course, userId)
            if (!student) return ''
            return `${student.last_name || ''}, ${student.first_name || ''}`.trim().replace(/^,\s*/, '')
        },
        studentClass(course, userId) {
            const student = this.findStudent(course, userId)
            if (!student) return ''
            return (student.schoolclass || student.class || '').toString().trim()
        },
        findStudent(course, userId) {
            const fromInfo = Array.isArray(course?.students_info) ? course.students_info : []
            const fromStudentsObjects = Array.isArray(course?.students) ? course.students.filter((s) => s && typeof s === 'object') : []
            const students = fromInfo.length ? fromInfo : fromStudentsObjects
            const needle = String(userId ?? '')
            if (!needle) return null
            return students.find((s) => String(s?.id ?? '') === needle) || null
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
        jumpToCourseStudent(entry) {
            if (!entry?.course_id) return
            const course = (this.courses || []).find((item) => item?.id === entry.course_id)
            if (!course) return

            if (!this.courseStore) this.courseStore = useCourseStore()
            this.courseStore.ensureCourseStudentCollections(course)

            this.selected_course = course
            this.selected_course_id = course.id
            this.action = ''
            this.action_2 = ''

            this.$nextTick(() => {
                this.courseStore.ensureCourseStudentCollections(this.selected_course)
                const student = this.findStudent(this.selected_course, entry.user_id)
                this.selected_course_student = student || null
                this.action_2 = student ? 'course_student_view' : ''
            })
        },
        daysDiffFromToday(dateStr, mode) {
            const target = parseLocalDate(dateStr)
            if (isNaN(target.getTime())) return '–'
            target.setHours(0, 0, 0, 0)

            const today = new Date(this.nowTs)
            today.setHours(0, 0, 0, 0)

            const diffDays = Math.floor((today.getTime() - target.getTime()) / (1000 * 60 * 60 * 24))
            if (mode === 'since') return Math.max(0, diffDays)
            return Math.max(0, -diffDays)
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
    },
}
</script>

<style scoped>
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
</style>
