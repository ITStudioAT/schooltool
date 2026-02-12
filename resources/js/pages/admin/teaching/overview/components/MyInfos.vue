<template>
    <ItsGridBox color="primary" title="Infos" icon="mdi-information-outline" class="w-100">
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <v-card variant="outlined" class="mt-2">
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
                                    <v-chip v-if="entry.student_class" size="x-small" variant="outlined" color="primary">{{ entry.student_class }}</v-chip>
                                    <v-chip v-if="entry.course_title" size="x-small" variant="tonal" color="primary" class="chip-truncate">{{ entry.course_title }}</v-chip>
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
                            <v-list-item>
                                <div class="d-flex align-center justify-space-between ga-2 w-100">
                                    <div class="text-caption text-medium-emphasis">Heute</div>
                                    <div class="text-body-2 font-weight-medium">{{ nowLabel }}</div>
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
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { ItsGridBox },

    async beforeMount() {
        this.courseStore = useCourseStore()
        this.teachingStore = useTeachingStore()
        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
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
            teachingStore: null,
            openNotifications: [],
            nowTs: Date.now(),
            nowTimer: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action_2']),
        ...mapWritableState(useCourseStore, ['courses', 'selected_course', 'selected_course_id', 'selected_course_student']),
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
                second: '2-digit',
            })
        },
        myStudentCount() {
            const ids = new Set()
            ;(this.myCourses || []).forEach((course) => {
                const studentsInfo = Array.isArray(course?.students_info) ? course.students_info : []
                if (studentsInfo.length) {
                    studentsInfo.forEach((student) => {
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
            return due < today ? 'error' : 'warning'
        },
        jumpToCourseStudent(entry) {
            if (!entry?.course_id) return
            const course = (this.courses || []).find((item) => item?.id === entry.course_id)
            if (!course) return

            if (!this.courseStore) this.courseStore = useCourseStore()
            this.courseStore.ensureCourseStudentCollections(course)

            this.selected_course = course
            this.selected_course_id = course.id
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
