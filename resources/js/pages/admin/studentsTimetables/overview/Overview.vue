<template>
    <v-col cols="12" xl="10">
        <v-card rounded="lg" border>
            <v-card-title class="d-flex flex-wrap align-center ga-2">
                <v-icon icon="mdi-calendar-clock" />
                Stundenplan
                <v-chip v-if="schoolyearName" size="small" variant="tonal" color="light-blue">
                    {{ schoolyearName }}
                </v-chip>
            </v-card-title>
            <v-card-text>
                <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-3" />

                <v-alert v-if="!loading && !configuredSchoolHours.length" type="info" variant="tonal" class="mb-3">
                    Es sind noch keine Schulstunden hinterlegt. Die Übersicht zeigt vorläufig 10 Stunden.
                </v-alert>

                <div class="semester-grid">
                    <section v-for="semester in semesters" :key="semester.value" class="semester-section">
                        <div class="semester-section__header">
                            <div class="d-flex align-center ga-2">
                                <v-icon icon="mdi-calendar-range" size="18" color="primary" />
                                <span class="text-subtitle-2 font-weight-bold">{{ semester.label }}</span>
                            </div>
                            <v-chip size="x-small" color="primary" variant="tonal">Mo-Sa</v-chip>
                        </div>

                        <div class="timetable-table-wrapper">
                            <table class="timetable-grid-table">
                                <thead>
                                    <tr>
                                        <th class="timetable-hour-header-cell"></th>
                                        <th v-for="weekday in weekdays" :key="`${semester.value}-${weekday.key}`" class="timetable-day-header-cell">
                                            <div>{{ weekday.label }}</div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="hour in timetableHours" :key="`${semester.value}-${hour.hour}`">
                                        <td class="timetable-hour-cell">
                                            <div class="timetable-hour-num">{{ hour.hour }}.</div>
                                            <div v-if="hour.from || hour.until" class="timetable-hour-time">
                                                {{ hour.from }}<br>{{ hour.until }}
                                            </div>
                                        </td>
                                        <td
                                            v-for="weekday in weekdays"
                                            :key="`${semester.value}-${weekday.key}-${hour.hour}`"
                                            class="timetable-grid-cell">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </v-card-text>
        </v-card>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

const FALLBACK_HOUR_COUNT = 10

export default {
    name: 'StudentsTimetablesOverview',

    data() {
        return {
            loading: false,
            schoolHours: [],
            weekdays: [
                { key: 'mo', label: 'Mo' },
                { key: 'tu', label: 'Di' },
                { key: 'we', label: 'Mi' },
                { key: 'th', label: 'Do' },
                { key: 'fr', label: 'Fr' },
                { key: 'sa', label: 'Sa' },
            ],
            semesters: [
                { value: 1, label: 'Semester 1' },
                { value: 2, label: 'Semester 2' },
            ],
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        schoolyearName() {
            return this.config?.selected_schoolyear?.name || ''
        },
        configuredSchoolHours() {
            return Array.isArray(this.schoolHours) ? this.schoolHours : []
        },
        timetableHours() {
            if (this.configuredSchoolHours.length) {
                return this.configuredSchoolHours
                    .map((schoolHour) => ({
                        hour: Number(schoolHour.hour),
                        from: this.formatTimeValue(schoolHour.from),
                        until: this.formatTimeValue(schoolHour.until),
                    }))
                    .filter((schoolHour) => Number.isFinite(schoolHour.hour))
                    .sort((a, b) => a.hour - b.hour)
            }

            return Array.from({ length: FALLBACK_HOUR_COUNT }, (item, index) => ({
                hour: index + 1,
                from: '',
                until: '',
            }))
        },
    },

    watch: {
        'config.selected_schoolyear.id'() {
            this.loadSchoolHours()
        },
    },

    mounted() {
        this.loadSchoolHours()
    },

    methods: {
        async loadSchoolHours() {
            this.loading = true
            try {
                const response = await axios.get('/api/admin/students-timetables/school-hours')
                this.schoolHours = response.data?.data || []
            } catch {
                this.schoolHours = []
            } finally {
                this.loading = false
            }
        },
        formatTimeValue(value) {
            const raw = (value || '').toString().trim()
            if (!raw) return ''

            return raw.slice(0, 5)
        },
    },
}
</script>

<style scoped>
.semester-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}

.semester-section {
    border: 1px solid rgba(0, 0, 0, 0.12);
    border-radius: 8px;
    background: #ffffff;
    overflow: hidden;
}

.semester-section__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 12px;
    background-color: #f5f5f5;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.timetable-table-wrapper {
    overflow-x: auto;
}

.timetable-grid-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 680px;
    font-size: 0.8rem;
}

.timetable-grid-table th,
.timetable-grid-table td {
    border: 1px solid rgba(0, 0, 0, 0.1);
    padding: 0;
    vertical-align: top;
}

.timetable-hour-header-cell {
    width: 58px;
    min-width: 58px;
    background-color: #f5f5f5;
}

.timetable-day-header-cell {
    text-align: center;
    min-width: 96px;
    background-color: #f5f5f5;
    font-weight: 600;
    padding: 8px 4px;
}

.timetable-hour-cell {
    text-align: center;
    background-color: #f5f5f5;
    padding: 6px 4px;
    white-space: nowrap;
    min-width: 58px;
}

.timetable-hour-num {
    font-weight: 600;
    font-size: 0.8rem;
}

.timetable-hour-time {
    font-size: 0.7rem;
    opacity: 0.65;
}

.timetable-grid-cell {
    min-width: 96px;
    height: 52px;
    background-color: #ffffff;
}
</style>
