<template>
    <div class="curriculum-detail">
        <div class="curriculum-detail__header mb-4">
            <v-btn
                variant="tonal"
                color="secondary"
                size="small"
                rounded="xl"
                prepend-icon="mdi-arrow-left"
                class="text-none mb-3"
                @click="$emit('back')">
                Zurück zur Übersicht
            </v-btn>
            <div class="curriculum-detail__title-row">
                <div>
                    <h2 class="curriculum-detail__title">{{ curriculum.title }}</h2>
                    <p v-if="curriculum.description" class="curriculum-detail__desc">{{ curriculum.description }}</p>
                </div>
                <div class="curriculum-detail__meta d-flex align-center ga-2">
                    <v-chip size="small" color="primary" variant="tonal" class="font-weight-bold">
                        {{ curriculum.semester_count ?? 2 }} Semester
                    </v-chip>
                    <div class="curriculum-detail__year-picker d-flex align-center ga-1">
                        <v-btn
                            icon="mdi-chevron-left"
                            variant="tonal"
                            color="secondary"
                            size="x-small"
                            @click="selectedYear--" />
                        <v-chip
                            size="small"
                            variant="tonal"
                            color="primary"
                            class="font-weight-bold px-3">
                            {{ selectedYear }}/{{ selectedYear + 1 }}
                        </v-chip>
                        <v-btn
                            icon="mdi-chevron-right"
                            variant="tonal"
                            color="secondary"
                            size="x-small"
                            @click="selectedYear++" />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="(curriculum.semester_count ?? 2) === 1" class="curriculum-detail__semester-picker mb-4">
            <v-sheet rounded="xl" class="curriculum-detail__picker-sheet pa-3">
                <div class="text-body-2 font-weight-medium mb-2" style="color: #cbd5e1">
                    Welches Semester anzeigen?
                </div>
                <v-btn-toggle
                    v-model="selectedHalf"
                    mandatory
                    color="primary"
                    density="comfortable"
                    rounded="lg"
                    class="semester-toggle">
                    <v-btn value="first" variant="outlined" class="text-none px-5 semester-toggle__btn">
                        <v-icon size="16" class="mr-1">mdi-weather-snowy</v-icon>
                        Wintersemester (Sep – Feb)
                    </v-btn>
                    <v-btn value="second" variant="outlined" class="text-none px-5 semester-toggle__btn">
                        <v-icon size="16" class="mr-1">mdi-white-balance-sunny</v-icon>
                        Sommersemester (Feb – Jul)
                    </v-btn>
                </v-btn-toggle>
            </v-sheet>
        </div>

        <div class="curriculum-detail__calendar">
            <div
                v-for="(month, idx) in visibleMonths"
                :key="month.key"
                class="curriculum-detail__month"
                :style="{ '--month-hue': monthHue(idx) }">
                <div class="curriculum-detail__month-header">
                    <div class="curriculum-detail__month-name">{{ month.name }}</div>
                    <div class="curriculum-detail__month-year">{{ month.year }}</div>
                </div>
                <div class="curriculum-detail__weeks">
                    <div
                        v-for="(week, wIdx) in month.weeks"
                        :key="wIdx"
                        class="curriculum-detail__week"
                        :class="{ 'curriculum-detail__week--current': week.isCurrent }">
                        <div class="curriculum-detail__week-number">
                            <span class="curriculum-detail__week-kw">KW</span>
                            <span class="curriculum-detail__week-num">{{ week.kw }}</span>
                        </div>
                        <div class="curriculum-detail__week-days">
                            <div
                                v-for="day in week.days"
                                :key="day.date"
                                class="curriculum-detail__day"
                                :class="{
                                    'curriculum-detail__day--today': day.isToday,
                                    'curriculum-detail__day--outside': day.outsideMonth,
                                }">
                                <span class="curriculum-detail__day-name">{{ day.dayName }}</span>
                                <span class="curriculum-detail__day-num">{{ day.dayNum }}</span>
                            </div>
                        </div>
                        <div class="curriculum-detail__week-range">
                            {{ week.rangeLabel }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { mapState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

const DAY_NAMES_SHORT = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']
const MONTH_NAMES = [
    'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni',
    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
]

export default {
    name: 'CurriculumDetail',
    props: {
        curriculum: { type: Object, required: true },
    },
    emits: ['back'],

    data() {
        const adminStore = useAdminStore()
        const sy = adminStore.config?.selected_schoolyear
        let initYear
        if (sy?.from) {
            const y = parseInt(sy.from.substring(0, 4), 10)
            initYear = Number.isFinite(y) ? y : null
        }
        if (!initYear) {
            const now = new Date()
            initYear = now.getMonth() >= 8 ? now.getFullYear() : now.getFullYear() - 1
        }
        return {
            selectedHalf: 'first',
            selectedYear: initYear,
        }
    },

    computed: {
        ...mapState(useAdminStore, ['config']),

        allMonths() {
            const months = []
            const startYear = this.selectedYear
            // September (8) to July (6) next year
            const monthSequence = [
                { m: 8, y: startYear },     // Sep
                { m: 9, y: startYear },      // Okt
                { m: 10, y: startYear },     // Nov
                { m: 11, y: startYear },     // Dez
                { m: 0, y: startYear + 1 },  // Jan
                { m: 1, y: startYear + 1 },  // Feb
                { m: 2, y: startYear + 1 },  // Mar
                { m: 3, y: startYear + 1 },  // Apr
                { m: 4, y: startYear + 1 },  // Mai
                { m: 5, y: startYear + 1 },  // Jun
                { m: 6, y: startYear + 1 },  // Jul
            ]

            const today = new Date()
            today.setHours(0, 0, 0, 0)

            for (const entry of monthSequence) {
                const weeks = this.buildWeeks(entry.y, entry.m, today)
                months.push({
                    key: `${entry.y}-${entry.m}`,
                    name: MONTH_NAMES[entry.m],
                    year: entry.y,
                    month: entry.m,
                    weeks,
                })
            }
            return months
        },

        visibleMonths() {
            const semCount = this.curriculum.semester_count ?? 2
            if (semCount === 2) return this.allMonths

            // 1 semester: first half = Sep-Feb (indices 0-5), second half = Feb-Jul (indices 5-10)
            if (this.selectedHalf === 'first') {
                return this.allMonths.slice(0, 6)
            }
            return this.allMonths.slice(5, 11)
        },
    },

    methods: {
        buildWeeks(year, month, today) {
            const weeks = []
            const firstDay = new Date(year, month, 1)
            const lastDay = new Date(year, month + 1, 0)

            // Find Monday of the week containing the 1st
            let cursor = new Date(firstDay)
            const dow = cursor.getDay()
            const mondayOffset = dow === 0 ? -6 : 1 - dow
            cursor.setDate(cursor.getDate() + mondayOffset)

            while (cursor <= lastDay || cursor.getDay() !== 1) {
                const days = []
                let weekHasMonthDay = false
                const weekStart = new Date(cursor)

                for (let d = 0; d < 7; d++) {
                    const date = new Date(cursor)
                    const inMonth = date.getMonth() === month && date.getFullYear() === year
                    if (inMonth) weekHasMonthDay = true

                    if (d < 5) {
                        days.push({
                            date: date.toISOString().slice(0, 10),
                            dayNum: date.getDate(),
                            dayName: DAY_NAMES_SHORT[d],
                            isToday: date.getTime() === today.getTime(),
                            outsideMonth: !inMonth,
                        })
                    }
                    cursor.setDate(cursor.getDate() + 1)
                }

                if (!weekHasMonthDay) break

                const friday = new Date(weekStart)
                friday.setDate(friday.getDate() + 4)

                const kw = this.getISOWeek(weekStart)
                const isCurrent = today >= weekStart && today <= friday

                weeks.push({
                    kw,
                    days,
                    isCurrent,
                    rangeLabel: `${weekStart.getDate()}.${weekStart.getMonth() + 1}. – ${friday.getDate()}.${friday.getMonth() + 1}.`,
                })
            }

            return weeks
        },

        getISOWeek(date) {
            const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
            d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7))
            const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1))
            return Math.ceil((((d - yearStart) / 86400000) + 1) / 7)
        },

        monthHue(idx) {
            // Distribute hues across the visible months for a colorful gradient
            const base = 220 // start with indigo
            return (base + idx * 28) % 360
        },
    },
}
</script>

<style scoped>
.curriculum-detail {
    max-width: 1100px;
}

.curriculum-detail__title-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.curriculum-detail__title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #e2e8f0;
    margin: 0;
}

.curriculum-detail__desc {
    font-size: 0.88rem;
    color: #94a3b8;
    margin: 4px 0 0;
}

.curriculum-detail__picker-sheet {
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: rgba(15, 23, 42, 0.7);
}

.semester-toggle__btn {
    color: #c7d2fe !important;
    border-color: rgba(148, 163, 184, 0.35) !important;
}

.semester-toggle .v-btn--active.semester-toggle__btn {
    color: #fff !important;
}

/* ---------- Calendar ---------- */
.curriculum-detail__calendar {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
}

.curriculum-detail__month {
    --accent: hsl(var(--month-hue), 65%, 68%);
    --accent-dim: hsl(var(--month-hue), 45%, 22%);
    --accent-glow: hsl(var(--month-hue), 70%, 50%);
    border-radius: 20px;
    border: 1px solid hsl(var(--month-hue), 50%, 30%, 0.35);
    background: linear-gradient(
        135deg,
        hsl(var(--month-hue), 35%, 12%, 0.85) 0%,
        rgba(15, 23, 42, 0.85) 100%
    );
    overflow: hidden;
    backdrop-filter: blur(12px);
    width: fit-content;
}

.curriculum-detail__month-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    padding: 14px 20px 8px;
    border-bottom: 1px solid hsl(var(--month-hue), 50%, 30%, 0.25);
}

.curriculum-detail__month-name {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--accent);
    letter-spacing: 0.02em;
}

.curriculum-detail__month-year {
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748b;
}

/* ---------- Weeks ---------- */
.curriculum-detail__weeks {
    padding: 10px 12px 14px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
}

.curriculum-detail__week {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 6px 10px;
    border-radius: 12px;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(148, 163, 184, 0.08);
    transition: background 0.2s, border-color 0.2s;
}

.curriculum-detail__week:hover {
    background: rgba(30, 41, 59, 0.7);
    border-color: rgba(148, 163, 184, 0.18);
}

.curriculum-detail__week--current {
    background: rgba(99, 102, 241, 0.12) !important;
    border-color: rgba(99, 102, 241, 0.35) !important;
    box-shadow: 0 0 16px rgba(99, 102, 241, 0.15);
}

/* ---------- Week number ---------- */
.curriculum-detail__week-number {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 36px;
    flex-shrink: 0;
}

.curriculum-detail__week-kw {
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.08em;
}

.curriculum-detail__week-num {
    font-size: 1rem;
    font-weight: 800;
    color: var(--accent);
    line-height: 1;
}

/* ---------- Days grid ---------- */
.curriculum-detail__week-days {
    display: flex;
    gap: 3px;
    flex: 1;
}

.curriculum-detail__day {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(148, 163, 184, 0.06);
    transition: all 0.15s;
}

.curriculum-detail__day--outside {
    opacity: 0.2;
}

.curriculum-detail__day--today {
    background: linear-gradient(135deg, #4f46e5, #7c3aed) !important;
    border-color: #818cf8 !important;
    opacity: 1 !important;
    box-shadow: 0 0 14px rgba(99, 102, 241, 0.5);
}

.curriculum-detail__day-name {
    font-size: 0.58rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    line-height: 1;
}

.curriculum-detail__day--today .curriculum-detail__day-name {
    color: #c7d2fe;
}

.curriculum-detail__day-num {
    font-size: 0.82rem;
    font-weight: 700;
    color: #cbd5e1;
    line-height: 1.2;
}

.curriculum-detail__day--today .curriculum-detail__day-num {
    color: #fff;
}

/* ---------- Week range label ---------- */
.curriculum-detail__week-range {
    font-size: 0.7rem;
    color: #475569;
    font-weight: 500;
    white-space: nowrap;
    min-width: 80px;
    text-align: right;
}

/* ---------- Responsive ---------- */
@media (max-width: 700px) {
    .curriculum-detail__day {
        width: 34px;
        height: 36px;
    }

    .curriculum-detail__week-range {
        display: none;
    }

    .curriculum-detail__month-header {
        padding: 10px 14px 6px;
    }
}
</style>
