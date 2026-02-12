<template>
    <ItsGridBox color="primary" title="Stundenplan" icon="mdi-calendar-clock" class="w-100" :disabled="action != ''">
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <v-btn-toggle v-model="range" mandatory density="compact" color="primary" class="w-100">
                    <v-btn :value="RANGE_TODAY" size="small">Heute</v-btn>
                    <v-btn :value="RANGE_WEEK" size="small">Diese Woche</v-btn>
                    <v-btn :value="RANGE_TWO_WEEKS" size="small">Zwei Wochen</v-btn>
                    <v-btn :value="RANGE_MONTH" size="small">Dieser Monat</v-btn>
                </v-btn-toggle>

                <v-card variant="outlined" class="mt-2">
                    <v-card-title class="text-subtitle-2 d-flex align-center ga-2">
                        <v-icon size="18">mdi-format-list-bulleted</v-icon>
                        Unterricht
                        <v-chip size="x-small" color="primary" variant="tonal">{{ filteredItems.length }}</v-chip>
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item v-for="item in filteredItems" :key="item.key" class="cursor-pointer" @click="openCourse(item)">
                                <div class="d-flex flex-column ga-2 w-100">
                                    <div class="d-flex flex-wrap align-center ga-2 w-100">
                                        <v-chip size="x-small" variant="tonal" color="primary">{{ formatDate(item.date) }}</v-chip>
                                        <v-chip size="x-small" variant="outlined" color="primary">{{ item.hoursLabel }}</v-chip>
                                        <v-chip size="x-small" variant="outlined">{{ item.classLabel }}</v-chip>
                                        <v-chip size="x-small" variant="tonal" color="secondary" class="chip-truncate">{{ item.courseTitle }}</v-chip>
                                    </div>
                                    <div v-if="item.content" class="text-caption timetable-content" v-html="contentHtml(item.content)"></div>
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!filteredItems.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine Termine im gewaehlten Zeitraum.</v-list-item-title>
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
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

const RANGE_TODAY = 'today'
const RANGE_WEEK = 'week'
const RANGE_TWO_WEEKS = 'two_weeks'
const RANGE_MONTH = 'month'

export default {
    components: { ItsGridBox },

    data() {
        return {
            RANGE_TODAY,
            RANGE_WEEK,
            RANGE_TWO_WEEKS,
            RANGE_MONTH,
            range: RANGE_TODAY,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, ['courses', 'selected_course', 'selected_course_id', 'selected_course_student', 'show_infos']),
        ...mapWritableState(useCourseDateStore, ['selected_courseDate']),
        myCourses() {
            const userId = this.config?.user?.id
            const list = Array.isArray(this.courses) ? this.courses : []
            if (!userId) return []
            return list.filter((course) => course?.user_id === userId)
        },
        timetableItems() {
            return (this.myCourses || [])
                .flatMap((course) => {
                    const classLabel = Array.isArray(course?.classes) ? course.classes.join(', ') : ''
                    const courseTitle = (course?.title || '').toString().trim()
                    const dates = Array.isArray(course?.course_dates) ? course.course_dates : []

                    return dates.map((courseDate) => {
                        const date = (courseDate?.date || '').toString().slice(0, 10)
                        const dateObj = parseLocalDate(date)
                        const hoursRaw = Array.isArray(courseDate?.hours) ? courseDate.hours : []
                        const hours = [...hoursRaw]
                            .map((h) => Number(h))
                            .filter((h) => Number.isFinite(h))
                            .sort((a, b) => a - b)
                        const hoursLabel = hours.length ? hours.map((h) => `${h}. Std`).join(', ') : '-'

                        return {
                            key: `${course?.id || 'x'}-${courseDate?.id || date}-${hoursLabel}`,
                            courseId: course?.id || null,
                            courseDateId: courseDate?.id || null,
                            date,
                            dateObj,
                            hours,
                            hoursLabel,
                            classLabel: classLabel || '-',
                            courseTitle: courseTitle || '-',
                            content: (courseDate?.content || '').toString().trim(),
                        }
                    })
                })
                .filter((item) => !isNaN(item.dateObj.getTime()))
                .sort((a, b) => {
                    const dateCmp = a.date.localeCompare(b.date)
                    if (dateCmp !== 0) return dateCmp
                    const hourA = a.hours[0] ?? 999
                    const hourB = b.hours[0] ?? 999
                    if (hourA !== hourB) return hourA - hourB
                    return a.courseTitle.localeCompare(b.courseTitle, 'de', { sensitivity: 'base' })
                })
        },
        filteredItems() {
            const [from, until] = this.currentRangeBounds()
            if (!from || !until) return this.timetableItems
            return this.timetableItems.filter((item) => item.dateObj >= from && item.dateObj <= until)
        },
    },

    methods: {
        normalizeDay(date) {
            const d = new Date(date)
            d.setHours(0, 0, 0, 0)
            return d
        },
        startOfWeek(date) {
            const d = this.normalizeDay(date)
            const day = d.getDay()
            const diff = day === 0 ? -6 : 1 - day
            d.setDate(d.getDate() + diff)
            return d
        },
        endOfWeek(date) {
            const start = this.startOfWeek(date)
            const end = new Date(start)
            end.setDate(start.getDate() + 6)
            return end
        },
        currentRangeBounds() {
            const today = this.normalizeDay(new Date())
            if (this.range === RANGE_TODAY) {
                return [today, today]
            }
            if (this.range === RANGE_WEEK) {
                return [this.startOfWeek(today), this.endOfWeek(today)]
            }
            if (this.range === RANGE_TWO_WEEKS) {
                const start = this.startOfWeek(today)
                const end = new Date(start)
                end.setDate(start.getDate() + 13)
                return [start, end]
            }
            if (this.range === RANGE_MONTH) {
                const start = new Date(today.getFullYear(), today.getMonth(), 1)
                const end = new Date(today.getFullYear(), today.getMonth() + 1, 0)
                end.setHours(0, 0, 0, 0)
                return [start, end]
            }
            return [null, null]
        },
        formatDate(date) {
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
        openCourse(item) {
            const courseId = item?.courseId
            if (!courseId) return
            const course = (this.courses || []).find((c) => c?.id === courseId)
            if (!course) return

            const courseStore = useCourseStore()
            courseStore.ensureCourseStudentCollections(course)

            this.selected_course = course
            this.selected_course_id = course.id
            this.selected_course_student = null
            this.action_2 = ''
            this.show_infos = true

            const dateId = item?.courseDateId
            if (!dateId) {
                this.selected_courseDate = null
                return
            }
            const date = (course?.course_dates || []).find((d) => d?.id === dateId) || null
            this.selected_courseDate = date
        },
    },
}
</script>

<style scoped>
.chip-truncate {
    max-width: 100%;
}

.chip-truncate :deep(.v-chip__content) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.timetable-content :deep(p) {
    margin: 0;
    min-height: 1.2em;
}
</style>
