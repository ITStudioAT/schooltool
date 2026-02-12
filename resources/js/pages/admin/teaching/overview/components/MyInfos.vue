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
                                    <v-chip v-if="entry.course_title" size="x-small" variant="tonal" color="primary" class="chip-truncate">{{ entry.course_title }}</v-chip>
                                    <v-chip v-if="entry.date" size="x-small" variant="tonal" color="primary">{{ formatDate(entry.date) }}</v-chip>
                                    <v-chip v-if="entry.due_date" size="x-small" variant="tonal" :color="dueDateColor(entry.due_date)">Fällig bis {{ formatDate(entry.due_date) }}</v-chip>
                                    <v-chip v-if="entry.type" size="x-small" variant="outlined" color="secondary" class="chip-truncate">{{ notificationTypeLabel(entry.type) }}</v-chip>
                                    <v-chip v-if="entry.student_class" size="x-small" variant="outlined" color="primary">{{ entry.student_class }}</v-chip>
                                    <v-chip v-if="entry.student_label" size="x-small" variant="outlined" class="chip-truncate">{{ entry.student_label }}</v-chip>
                                    <div class="notification-description text-caption w-100">{{ entry.description || '' }}</div>
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!openNotifications.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine offenen Verständigungen.</v-list-item-title>
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
        this.teachingStore = useTeachingStore()
        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
        }
        await this.loadOpenNotifications()
    },

    data() {
        return {
            teachingStore: null,
            openNotifications: [],
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        ...mapWritableState(useCourseStore, ['courses']),
        myCourses() {
            const userId = this.config?.user?.id
            const list = Array.isArray(this.courses) ? this.courses : []
            if (!userId) return []
            return list.filter((course) => course?.user_id === userId)
        },
        myCourseIds() {
            return (this.myCourses || []).map((course) => course?.id).filter((id) => !!id)
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
