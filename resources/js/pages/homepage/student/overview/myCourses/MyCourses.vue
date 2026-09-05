<template>
    <div class="student-subjects">
        <div class="subjects-heading">
            <div>
                <h2>Meine Fächer <span v-if="!loading" class="subjects-count">{{ courses.length }}</span></h2>
                <p>Öffne ein Fach für Termine, Lerninhalte und Leistungen.</p>
            </div>
            <label v-if="courses.length > 1" class="subjects-search">
                <v-icon size="21" aria-hidden="true">mdi-magnify</v-icon>
                <input v-model="courseSearch" type="search" aria-label="Fächer suchen" placeholder="Fach suchen …" data-testid="student-course-search" />
            </label>
        </div>

        <div v-if="loading" class="subjects-empty" role="status">
            <v-progress-circular indeterminate color="#4056d6" />
            <p>Lade Fächer...</p>
        </div>

        <div v-else-if="filteredCourses.length" class="subjects-grid">
            <button
                v-for="course in filteredCourses"
                :key="course.id"
                type="button"
                class="subject-card"
                :aria-label="`${course.title} öffnen`"
                @click="handleCourseClick(course)">
                <span class="subject-topline">
                    <span class="subject-symbol"><v-icon size="25" aria-hidden="true">mdi-book-open-page-variant-outline</v-icon></span>
                    <span class="subject-open"><v-icon size="21" aria-hidden="true">mdi-arrow-top-right</v-icon></span>
                </span>
                <span class="subject-title">{{ course.title }}</span>
                <span v-if="course.teacher" class="subject-teacher">{{ course.teacher }}</span>
                <span class="subject-next-date">
                    <v-icon size="18" aria-hidden="true">mdi-calendar-outline</v-icon>
                    <span>
                        <span class="subject-date-label">Nächster Unterricht</span>
                        <strong>{{ formatNextCourseDate(course.next_course_date) || 'Noch kein Termin geplant' }}</strong>
                    </span>
                </span>
                <span class="subject-footer">
                    <span v-if="course.classes && course.classes.length > 0">
                        {{ course.classes.join(', ') }}
                    </span>
                    <span v-if="course.students_count !== undefined && course.students_count !== null">{{ course.students_count }} Schüler:innen</span>
                    <span class="subject-action">Zum Fach <v-icon size="16" aria-hidden="true">mdi-arrow-right</v-icon></span>
                </span>
            </button>
        </div>

        <div v-else-if="courses.length" class="subjects-empty" role="status">
            <v-icon size="40" color="#4056d6">mdi-magnify</v-icon>
            <h3>Kein passendes Fach gefunden</h3>
            <p>Suche nach einem Fach, einer Lehrperson oder einer Klasse.</p>
            <button type="button" class="subjects-reset" @click="courseSearch = ''">Alle Fächer anzeigen</button>
        </div>
        <div v-else class="subjects-empty">
            <v-icon size="44" color="#4056d6">mdi-book-open-outline</v-icon>
            <h3>Hier ist Platz für deine Fächer</h3>
            <p>Sobald du einem Fach zugeordnet bist, findest du es hier.</p>
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
            courseSearch: '',
            loading: false,
            nowTs: Date.now(),
            nowTimer: null,
            simulatedCourseEndAtById: {},
        }
    },

    computed: {
        filteredCourses() {
            const query = this.courseSearch.trim().toLocaleLowerCase('de')
            if (!query) {
                return this.courses
            }

            return this.courses.filter((course) => {
                const searchableText = [course.title, course.teacher, ...(course.classes || [])].join(' ')
                return searchableText.toLocaleLowerCase('de').includes(query)
            })
        },

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

<style scoped>
.student-subjects {
    color: #18243b;
}

.subjects-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 20px;
}

.subjects-heading h2 {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.3rem;
    font-weight: 750;
    letter-spacing: -0.03em;
}

.subjects-heading p {
    margin: 6px 0 0;
    color: #647086;
    font-size: 0.9rem;
    line-height: 1.5;
}

.subjects-count {
    min-width: 28px;
    padding: 3px 8px;
    border-radius: 8px;
    background: #e9edfc;
    color: #4056d6;
    font-size: 0.8rem;
    text-align: center;
}

.subjects-search {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 0 1 270px;
    min-width: 200px;
    min-height: 46px;
    padding: 0 14px;
    border: 1px solid #dbe2ee;
    border-radius: 12px;
    background: #fff;
    color: #647086;
}

.subjects-search input {
    width: 100%;
    min-width: 0;
    min-height: 44px;
    color: #18243b;
    font: inherit;
    font-size: 0.9rem;
    outline: none;
}

.subjects-search:focus-within {
    outline: 3px solid #c6cef8;
    border-color: #4056d6;
}

.subjects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 290px), 1fr));
    gap: 18px;
}

.subject-card {
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 22px;
    border: 1px solid #e0e6f0;
    border-radius: 18px;
    background: #fff;
    color: #18243b;
    font: inherit;
    text-align: left;
    cursor: pointer;
    box-shadow: 0 3px 12px rgba(24, 36, 59, 0.025);
    transition: border-color 160ms ease, box-shadow 160ms ease;
}

.subject-card:hover {
    border-color: #aab6f0;
    box-shadow: 0 8px 24px rgba(64, 86, 214, 0.08);
}

.subject-card:focus-visible,
.subjects-reset:focus-visible {
    outline: 3px solid #4056d6;
    outline-offset: 3px;
}

.subject-topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
}

.subject-symbol,
.subject-open {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 14px;
    color: #4056d6;
    background: #eef0ff;
}

.subject-card:nth-child(3n + 2) .subject-symbol {
    background: #e9f6f0;
    color: #31715b;
}

.subject-card:nth-child(3n) .subject-symbol {
    background: #f2f5dc;
    color: #707b35;
}

.subject-open {
    width: 32px;
    height: 32px;
    background: #f5f7fc;
    color: #73809a;
    border-radius: 50%;
}

.subject-title {
    font-size: 1.15rem;
    line-height: 1.4;
    font-weight: 750;
    letter-spacing: -0.025em;
    overflow-wrap: anywhere;
}

.subject-teacher {
    margin-top: 4px;
    color: #647086;
    font-size: 0.88rem;
    overflow-wrap: anywhere;
}

.subject-next-date {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 20px 0;
    padding: 12px;
    border-radius: 11px;
    background: #f6f8fc;
    color: #647086;
}

.subject-date-label {
    display: block;
    margin-bottom: 3px;
    font-size: 0.72rem;
}

.subject-next-date strong {
    color: #384761;
    font-size: 0.82rem;
    font-weight: 600;
}

.subject-footer {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px 12px;
    margin-top: auto;
    color: #647086;
    font-size: 0.74rem;
}

.subject-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-left: auto;
    color: #4056d6;
    font-size: 0.8rem;
    font-weight: 700;
}

.subjects-empty {
    display: flex;
    align-items: center;
    flex-direction: column;
    gap: 12px;
    padding: 44px 24px;
    border: 1px solid #e0e6f0;
    border-radius: 18px;
    background: #fff;
    text-align: center;
}

.subjects-empty h3 {
    font-size: 1.05rem;
}

.subjects-empty p {
    color: #647086;
    font-size: 0.9rem;
}

.subjects-reset {
    min-height: 44px;
    padding: 10px 16px;
    border-radius: 10px;
    background: #eef0ff;
    color: #4056d6;
    font-weight: 600;
}

@media (max-width: 600px) {
    .subjects-heading {
        align-items: stretch;
        flex-direction: column;
        gap: 14px;
    }

    .subjects-search {
        flex-basis: auto;
    }

    .subject-card {
        padding: 18px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .subject-card {
        transition: none;
    }
}
</style>
