<template>
    <div class="content-card">
        <div class="content-head">
            <v-icon size="26">mdi-book-open-variant</v-icon>
            <h2>Meine Fächer</h2>
            <v-chip v-if="!loading" size="small" class="ml-auto">{{ courses.length }}</v-chip>
        </div>
        <p class="content-copy">Hier findest du alle deine Fächer.</p>

        <!-- Loading State -->
        <div v-if="loading" class="courses-loading">
            <v-progress-circular indeterminate color="#fd802e" />
            <p>Lade Fächer...</p>
        </div>

        <!-- Courses Grid -->
        <div v-else-if="courses.length > 0" class="courses-grid">
            <div v-for="course in courses" :key="course.id" class="course-card" @click="handleCourseClick(course)">
                <div class="course-header">
                    <v-icon size="24" color="#fd802e">mdi-school</v-icon>
                </div>
                <h3 class="course-title">{{ course.title }}</h3>
                <p class="course-teacher">{{ course.teacher }}</p>
                <div v-if="course.next_course_date" class="d-flex align-center ga-1 text-caption text-medium-emphasis mb-2">
                    <v-icon size="16">mdi-calendar-clock</v-icon>
                    <span>{{ formatNextCourseDate(course.next_course_date) }}</span>
                </div>
                <div class="course-footer">
                    <span v-if="course.classes && course.classes.length > 0" class="course-info">
                        <v-icon size="16">mdi-account-group</v-icon>
                        {{ course.classes.join(', ') }}
                    </span>
                    <span class="course-info">
                        <v-icon size="16">mdi-account-multiple</v-icon>
                        {{ course.students_count }} Schüler:innen
                    </span>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="courses-empty">
            <v-icon size="64" color="#fd802e">mdi-book-off-outline</v-icon>
            <h3>Keine Fächer gefunden</h3>
            <p>Du bist derzeit in keinen Fächern eingeschrieben.</p>
        </div>
    </div>
</template>

<script>
import { parseLocalDate } from '@/helpers/date'
import { useCourseStore } from '@/stores/student/CourseStore'

export default {
    emits: ['live-timer-change'],

    async beforeMount() {
        await this.loadCourses()
    },
    mounted() {
        this.nowTimer = setInterval(() => {
            this.nowTs = Date.now()
        }, 1000)
    },
    unmounted() {
        if (this.nowTimer) {
            clearInterval(this.nowTimer)
        }
    },

    data() {
        return {
            courseStore: useCourseStore(),
            courses: [],
            loading: false,
            nowTs: Date.now(),
            nowTimer: null,
            simulatedCourseEndAtById: {},
        }
    },

    computed: {
        activeHeaderTimer() {
            const list = Array.isArray(this.courses) ? this.courses : []
            for (const course of list) {
                const remainingLabel = this.courseRemainingLabel(course)
                if (!remainingLabel) {
                    continue
                }

                return {
                    title: (course?.title || '').toString().trim() || 'Kurs',
                    remainingLabel,
                    isSimulated: this.isSimulatedCourseTimer(course),
                }
            }

            return null
        },
        headerLiveTimerLabel() {
            if (!this.activeHeaderTimer) {
                return null
            }

            const modePrefix = this.activeHeaderTimer.isSimulated ? 'Testmodus' : 'Live'
            return `${modePrefix}: ${this.activeHeaderTimer.title} endet in ${this.activeHeaderTimer.remainingLabel}`
        },
    },

    watch: {
        activeHeaderTimer: {
            immediate: true,
            deep: true,
            handler(value) {
                this.$emit('live-timer-change', value || null)
            },
        },
        '$route.query.live_timer'() {
            this.setupSimulatedTimers()
        },
    },

    methods: {
        async loadCourses() {
            this.loading = true
            try {
                const success = await this.courseStore.getCourses()
                if (success) {
                    this.courses = this.courseStore.courses || []
                    this.setupSimulatedTimers()
                }
            } catch (error) {
                console.error('Error loading courses:', error)
            } finally {
                this.loading = false
            }
        },

        handleCourseClick(course) {
            this.$router.push({
                path: `/student/course/${course.id}`,
                query: { ...(this.$route?.query || {}) },
            })
        },

        formatNextCourseDate(nextCourseDate) {
            const dateValue = nextCourseDate?.date
            if (!dateValue) {
                return ''
            }

            const parsedDate = parseLocalDate(dateValue)
            if (isNaN(parsedDate.getTime())) {
                return ''
            }

            const dateLabel = parsedDate.toLocaleDateString('de-DE', {
                weekday: 'short',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            })
            const timeLabel = (nextCourseDate?.time_label || '').toString().trim()

            return timeLabel ? `${dateLabel}, ${timeLabel}` : dateLabel
        },
        setupSimulatedTimers() {
            const nextSimulatedEndAtById = {}
            if (!this.isLiveTimerTestMode()) {
                this.simulatedCourseEndAtById = nextSimulatedEndAtById
                return
            }

            const nowTs = this.nowTs

            ;(this.courses || []).forEach((course) => {
                if (course?.active_course_end_at || !course?.next_course_date?.time_label || !course?.id) {
                    return
                }

                const durationSeconds = this.durationSecondsFromTimeLabel(course.next_course_date.time_label)
                if (durationSeconds <= 0) {
                    return
                }

                nextSimulatedEndAtById[String(course.id)] = new Date(nowTs + (durationSeconds * 1000)).toISOString()
            })

            // TESTMODE-FALLBACK: Wenn kein Timer gesetzt wurde, ersten Kurs mit 45 Min. simulieren
            if (Object.keys(nextSimulatedEndAtById).length === 0) {
                const firstCourse = (this.courses || []).find((c) => c?.id && !c?.active_course_end_at)
                if (firstCourse) {
                    nextSimulatedEndAtById[String(firstCourse.id)] = new Date(nowTs + 45 * 60 * 1000).toISOString()
                }
            }

            this.simulatedCourseEndAtById = nextSimulatedEndAtById
        },
        isLiveTimerTestMode() {
            const liveTimerQuery = this.$route?.query?.live_timer
            const timerQuery = this.$route?.query?.timer
            return liveTimerQuery === '1'
                || liveTimerQuery === 'true'
                || timerQuery === '1'
                || timerQuery === 'true'
        },
        durationSecondsFromTimeLabel(timeLabel) {
            const normalized = (timeLabel || '').toString().trim()
            if (!normalized.includes('-')) {
                return 0
            }

            const [fromRaw, untilRaw] = normalized.split('-').map((part) => part.trim())
            const fromMinutes = this.minutesFromTime(fromRaw)
            const untilMinutes = this.minutesFromTime(untilRaw)

            if (fromMinutes === null || untilMinutes === null || untilMinutes <= fromMinutes) {
                return 0
            }

            return (untilMinutes - fromMinutes) * 60
        },
        minutesFromTime(value) {
            const parts = (value || '').toString().trim().split(':').map((part) => Number(part))
            if (!Number.isFinite(parts[0]) || !Number.isFinite(parts[1])) {
                return null
            }

            return (parts[0] * 60) + parts[1]
        },
        isSimulatedCourseTimer(course) {
            if (course?.active_course_end_at) {
                return false
            }

            const courseId = String(course?.id || '')
            if (!courseId) {
                return false
            }

            return !!this.simulatedCourseEndAtById[courseId]
        },
        courseRemainingLabel(course) {
            const courseId = String(course?.id || '')
            const simulatedEndAt = courseId ? this.simulatedCourseEndAtById[courseId] : null
            const endAtRaw = (course?.active_course_end_at || simulatedEndAt || '').toString().trim()
            if (!endAtRaw) {
                return null
            }

            const endAt = new Date(endAtRaw)
            if (isNaN(endAt.getTime())) {
                return null
            }

            const diffSeconds = Math.max(0, Math.floor((endAt.getTime() - this.nowTs) / 1000))
            if (diffSeconds <= 0) {
                return null
            }

            const hours = Math.floor(diffSeconds / 3600)
            const minutes = Math.floor((diffSeconds % 3600) / 60)

            if (hours > 0) {
                return `${String(hours).padStart(2, '0')}h ${String(minutes).padStart(2, '0')}m`
            }

            return `${String(minutes).padStart(2, '0')}m`
        },
    },
}
</script>
