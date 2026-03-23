<template>
    <v-col cols="12">
        <div class="menu-plans-root">
            <v-row dense class="ma-0">
                <v-col cols="12" lg="8" class="pa-0 pr-lg-2">
                    <v-sheet rounded="xl" class="menu-plans-calendar pa-4" data-testid="menu-plans-calendar">
                        <div class="menu-plans-calendar__header">
                            <div>
                                <h3 class="menu-plans-calendar__title">Kalenderwoche</h3>
                                <div class="menu-plans-calendar__range">{{ currentWeekRangeLabel }}</div>
                            </div>
                            <div class="menu-plans-calendar__nav">
                                <v-btn size="small" variant="tonal" color="secondary" rounded="lg" @click="goToPreviousWeek">
                                    <v-icon icon="mdi-chevron-left" size="14" class="mr-1" />
                                    Vorwoche
                                </v-btn>
                                <v-btn size="small" variant="tonal" color="secondary" rounded="lg" @click="goToCurrentWeek">Heute</v-btn>
                                <v-btn size="small" variant="tonal" color="secondary" rounded="lg" @click="goToNextWeek">
                                    N&auml;chste Woche
                                    <v-icon icon="mdi-chevron-right" size="14" class="ml-1" />
                                </v-btn>
                            </div>
                        </div>

                        <div class="menu-plans-calendar__weeks" role="list" aria-label="Wochentage">
                            <div
                                v-for="(week, weekIndex) in calendarWeeks"
                                :key="`week-${weekIndex}`"
                                class="menu-plans-calendar__week"
                                :data-testid="`menu-week-${weekIndex + 1}`">
                                <button
                                    v-for="day in week"
                                    :key="day.iso"
                                    type="button"
                                    class="menu-day"
                                    :class="dayCellClasses(day.iso)"
                                    :data-testid="`menu-day-${day.iso}`"
                                    @click="selectDay(day.iso)">
                                    <span class="menu-day__weekday">{{ day.labelShort }}</span>
                                    <span class="menu-day__number">{{ day.dayNumber }}</span>
                                    <span class="menu-day__month">{{ day.monthShort }}</span>
                                    <span v-if="day.hasPlan" class="menu-day__badge">{{ day.planCount }} Plan{{ day.planCount > 1 ? 'e' : '' }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="menu-plans-calendar__legend">
                            <span class="legend-item"><i class="legend-dot legend-dot--plan" /> Bereits geplanter Zeitraum</span>
                            <span class="legend-item"><i class="legend-dot legend-dot--selection" /> Deine Auswahl</span>
                        </div>
                    </v-sheet>
                </v-col>

                <v-col cols="12" lg="4" class="pa-0 pl-lg-2 mt-2 mt-lg-0">
                    <v-sheet rounded="xl" class="menu-plans-side pa-4" data-testid="menu-plans-selection">
                        <h3 class="menu-plans-side__title">Neuen Zeitraum w&auml;hlen</h3>
                        <p class="menu-plans-side__hint">Starttag klicken, Endtag klicken, danach Erstellen.</p>

                        <div class="selection-grid">
                            <div class="selection-card">
                                <div class="selection-card__label">Start</div>
                                <div class="selection-card__value">{{ selectedStartIso ? formatDate(selectedStartIso) : 'Nicht gew&auml;hlt' }}</div>
                            </div>
                            <div class="selection-card">
                                <div class="selection-card__label">Ende</div>
                                <div class="selection-card__value">{{ selectedEndIso ? formatDate(selectedEndIso) : 'Nicht gew&auml;hlt' }}</div>
                            </div>
                        </div>

                        <div class="menu-plans-side__summary">
                            {{ selectionSummary }}
                        </div>

                        <div class="menu-plans-side__actions">
                            <v-btn
                                v-if="isExistingPlanSelection"
                                color="warning"
                                rounded="xl"
                                :disabled="!selectedExistingPlan"
                                @click="editSelectedPlan">
                                Bearbeiten
                            </v-btn>
                            <v-btn v-else color="primary" rounded="xl" :disabled="!canCreatePreview" @click="createPreview">
                                Erstellen
                            </v-btn>
                            <v-btn variant="text" color="secondary" rounded="xl" @click="resetSelection">
                                Zur&uuml;cksetzen
                            </v-btn>
                        </div>

                        <v-alert
                            v-if="previewMessage"
                            type="info"
                            variant="tonal"
                            density="comfortable"
                            class="mt-3"
                            data-testid="menu-plan-preview-message">
                            {{ previewMessage }}
                        </v-alert>
                    </v-sheet>
                </v-col>
            </v-row>

        </div>
    </v-col>
</template>

<script>
export default {
    data() {
        const todayIso = this.toIso(new Date())

        return {
            todayIso,
            currentWeekStartIso: this.startOfWeekIso(todayIso),
            selectedStartIso: '',
            selectedEndIso: '',
            previewMessage: '',
            menuPlans: [
                {
                    id: 'mp-2026-03-23',
                    title: 'Fr\u00fchlingswoche',
                    start_iso: '2026-03-23',
                    end_iso: '2026-03-27',
                    days: 5,
                },
                {
                    id: 'mp-2026-03-30',
                    title: 'Projektwoche Spezial',
                    start_iso: '2026-03-30',
                    end_iso: '2026-04-03',
                    days: 5,
                },
                {
                    id: 'mp-2026-04-13',
                    title: 'Bio-Themenwoche',
                    start_iso: '2026-04-13',
                    end_iso: '2026-04-24',
                    days: 12,
                },
            ],
            weekDayLabels: ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
        }
    },

    computed: {
        weekDaysWithDates() {
            return this.buildWeekDays(this.currentWeekStartIso)
        },
        calendarWeeks() {
            return [0, 1, 2].map((weekOffset) => {
                const weekStartIso = this.addDaysIso(this.currentWeekStartIso, weekOffset * 7)

                return this.buildWeekDays(weekStartIso)
            })
        },
        currentWeekRangeLabel() {
            const weekEndIso = this.addDaysIso(this.currentWeekStartIso, 20)

            return `${this.formatDate(this.currentWeekStartIso)} - ${this.formatDate(weekEndIso)}`
        },
        canCreatePreview() {
            return this.selectedStartIso !== '' && this.selectedEndIso !== ''
        },
        selectedExistingPlan() {
            if (!this.selectedStartIso || !this.selectedEndIso) {
                return null
            }

            return this.menuPlans.find((plan) => plan.start_iso === this.selectedStartIso && plan.end_iso === this.selectedEndIso) || null
        },
        isExistingPlanSelection() {
            return this.selectedExistingPlan !== null
        },
        selectionSummary() {
            if (!this.selectedStartIso) {
                return 'Noch keine Auswahl. Bitte Starttag w\u00e4hlen.'
            }

            if (!this.selectedEndIso) {
                return `Start ist ${this.formatDate(this.selectedStartIso)}. W\u00e4hle jetzt den Endtag.`
            }

            if (this.isExistingPlanSelection) {
                return `Bestehender Plan ausgew\u00e4hlt: ${this.formatPeriod(this.selectedStartIso, this.selectedEndIso)}`
            }

            return `Ausgew\u00e4hlt: ${this.formatPeriod(this.selectedStartIso, this.selectedEndIso)}`
        },
    },

    methods: {
        buildWeekDays(weekStartIso) {
            return Array.from({ length: 7 }, (_, index) => {
                const iso = this.addDaysIso(weekStartIso, index)
                const date = this.toDate(iso)
                const planCount = this.planCountForDay(iso)

                return {
                    iso,
                    labelShort: this.weekDayLabels[index],
                    dayNumber: String(date.getDate()).padStart(2, '0'),
                    monthShort: date.toLocaleDateString('de-AT', { month: 'short' }),
                    hasPlan: planCount > 0,
                    planCount,
                }
            })
        },
        toDate(isoString) {
            return new Date(`${isoString}T00:00:00`)
        },
        toIso(date) {
            const year = date.getFullYear()
            const month = String(date.getMonth() + 1).padStart(2, '0')
            const day = String(date.getDate()).padStart(2, '0')

            return `${year}-${month}-${day}`
        },
        addDaysIso(isoString, days) {
            const date = this.toDate(isoString)
            date.setDate(date.getDate() + days)

            return this.toIso(date)
        },
        startOfWeekIso(isoString) {
            const date = this.toDate(isoString)
            const dayIndex = date.getDay()
            const distanceToMonday = dayIndex === 0 ? -6 : 1 - dayIndex
            date.setDate(date.getDate() + distanceToMonday)

            return this.toIso(date)
        },
        formatDate(isoString) {
            return this.toDate(isoString).toLocaleDateString('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            })
        },
        formatPeriod(startIso, endIso) {
            return `${this.formatDate(startIso)} - ${this.formatDate(endIso)}`
        },
        isWithinRange(targetIso, startIso, endIso) {
            return targetIso >= startIso && targetIso <= endIso
        },
        planCountForDay(isoString) {
            return this.menuPlans.filter((plan) => this.isWithinRange(isoString, plan.start_iso, plan.end_iso)).length
        },
        findPlanForDay(isoString) {
            return this.menuPlans.find((plan) => this.isWithinRange(isoString, plan.start_iso, plan.end_iso)) || null
        },
        planIdsForDay(isoString) {
            return this.menuPlans
                .filter((plan) => this.isWithinRange(isoString, plan.start_iso, plan.end_iso))
                .map((plan) => plan.id)
        },
        sharesPlan(isoString, comparisonIso) {
            const currentPlanIds = this.planIdsForDay(isoString)
            const comparisonPlanIds = this.planIdsForDay(comparisonIso)

            return currentPlanIds.some((planId) => comparisonPlanIds.includes(planId))
        },
        isDayInSelectedRange(isoString) {
            if (!this.selectedStartIso || !this.selectedEndIso) {
                return false
            }

            return this.isWithinRange(isoString, this.selectedStartIso, this.selectedEndIso)
        },
        dayCellClasses(isoString) {
            const hasPlan = this.planCountForDay(isoString) > 0
            const previousIso = this.addDaysIso(isoString, -1)
            const nextIso = this.addDaysIso(isoString, 1)
            const hasPreviousPlanConnection = hasPlan && this.sharesPlan(isoString, previousIso)
            const hasNextPlanConnection = hasPlan && this.sharesPlan(isoString, nextIso)

            return {
                'is-today': isoString === this.todayIso,
                'has-plan': hasPlan,
                'has-plan-start': hasPlan && !hasPreviousPlanConnection,
                'has-plan-middle': hasPlan && hasPreviousPlanConnection && hasNextPlanConnection,
                'has-plan-end': hasPlan && !hasNextPlanConnection,
                'has-plan-single': hasPlan && !hasPreviousPlanConnection && !hasNextPlanConnection,
                'is-range-start': isoString === this.selectedStartIso,
                'is-range-end': isoString === this.selectedEndIso,
                'is-selected-range': this.isDayInSelectedRange(isoString),
            }
        },
        selectDay(isoString) {
            this.previewMessage = ''
            const clickedPlan = this.findPlanForDay(isoString)

            if (clickedPlan) {
                this.selectedStartIso = clickedPlan.start_iso
                this.selectedEndIso = clickedPlan.end_iso

                return
            }

            if (!this.selectedStartIso || (this.selectedStartIso && this.selectedEndIso)) {
                this.selectedStartIso = isoString
                this.selectedEndIso = ''

                return
            }

            if (isoString < this.selectedStartIso) {
                this.selectedEndIso = this.selectedStartIso
                this.selectedStartIso = isoString

                return
            }

            this.selectedEndIso = isoString
        },
        createPreview() {
            if (!this.canCreatePreview) {
                return
            }

            this.$router.push({
                path: '/admin/menu-plans',
                query: {
                    mode: 'create',
                    start: this.selectedStartIso,
                    end: this.selectedEndIso,
                },
            })
        },
        editSelectedPlan() {
            if (!this.selectedExistingPlan) {
                return
            }

            this.$router.push({
                path: '/admin/menu-plans',
                query: {
                    mode: 'edit',
                    plan_id: this.selectedExistingPlan.id,
                    start: this.selectedExistingPlan.start_iso,
                    end: this.selectedExistingPlan.end_iso,
                },
            })
        },
        resetSelection() {
            this.selectedStartIso = ''
            this.selectedEndIso = ''
            this.previewMessage = ''
        },
        goToCurrentWeek() {
            this.currentWeekStartIso = this.startOfWeekIso(this.todayIso)
        },
        goToPreviousWeek() {
            this.currentWeekStartIso = this.addDaysIso(this.currentWeekStartIso, -7)
        },
        goToNextWeek() {
            this.currentWeekStartIso = this.addDaysIso(this.currentWeekStartIso, 7)
        },
    },
}
</script>

<style scoped>
.menu-plans-root {
    --menu-plans-bg: #061425;
    --menu-plans-panel: linear-gradient(155deg, rgba(12, 29, 48, 0.96), rgba(7, 22, 38, 0.93));
    --menu-plans-line: rgba(125, 211, 252, 0.19);
    --menu-plans-copy: #c7d2fe;
    --menu-plans-title: #f8fafc;
    --menu-plans-accent: #f59e0b;
    --menu-plans-good: #22d3ee;
    --menu-plans-selected: #f97316;
}

.menu-plans-hero,
.menu-plans-calendar,
.menu-plans-side {
    border: 1px solid var(--menu-plans-line);
    background: var(--menu-plans-panel);
    box-shadow: 0 22px 40px rgba(2, 6, 23, 0.45);
    color: var(--menu-plans-title);
}

.menu-plans-calendar__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.menu-plans-calendar__nav {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.menu-plans-calendar__title,
.menu-plans-side__title {
    font-size: 1.08rem;
    font-weight: 700;
}

.menu-plans-calendar__range,
.menu-plans-side__hint,
.menu-plans-side__summary {
    color: var(--menu-plans-copy);
}

.menu-plans-calendar__weeks {
    display: grid;
    gap: 6px;
}

.menu-plans-calendar__week {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0;
}

.menu-day {
    border: 1px solid rgba(148, 163, 184, 0.3);
    border-radius: 10px;
    padding: 7px 6px;
    background: rgba(15, 23, 42, 0.6);
    color: #e2e8f0;
    display: flex;
    flex-direction: column;
    gap: 2px;
    text-align: left;
    transition: transform 0.16s ease, border-color 0.16s ease, background-color 0.16s ease;
}

.menu-day:hover {
    transform: translateY(-1px);
    border-color: rgba(125, 211, 252, 0.6);
}

.menu-day__weekday {
    font-size: 0.68rem;
    color: #94a3b8;
}

.menu-day__number {
    font-size: 0.94rem;
    font-weight: 700;
    line-height: 1.15;
}

.menu-day__month {
    font-size: 0.66rem;
    color: #cbd5e1;
}

.menu-day__badge {
    margin-top: 3px;
    font-size: 0.62rem;
    color: #082f49;
    background: #a5f3fc;
    border-radius: 999px;
    padding: 1px 6px;
    align-self: flex-start;
    font-weight: 700;
}

.menu-day.has-plan {
    position: relative;
    border-color: rgba(34, 211, 238, 0.55);
    background: rgba(34, 211, 238, 0.16);
    z-index: 1;
}

.menu-day.has-plan-start,
.menu-day.has-plan-middle,
.menu-day.has-plan-end {
    border-radius: 0;
}

.menu-day.has-plan-start,
.menu-day.has-plan-middle {
    border-right-color: transparent;
}

.menu-day.has-plan-middle,
.menu-day.has-plan-end {
    border-left-color: transparent;
}

.menu-day.has-plan-start {
    border-top-left-radius: 10px;
    border-bottom-left-radius: 10px;
}

.menu-day.has-plan-end {
    border-top-right-radius: 10px;
    border-bottom-right-radius: 10px;
}

.menu-day.is-selected-range {
    background: rgba(249, 115, 22, 0.22);
    border-color: rgba(251, 146, 60, 0.8);
}

.menu-day.is-range-start,
.menu-day.is-range-end {
    border-width: 2px;
    border-color: #fdba74;
    background: rgba(249, 115, 22, 0.34);
}

.menu-day.is-today {
    box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.75);
}

.menu-plans-calendar__legend {
    margin-top: 12px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    color: var(--menu-plans-copy);
    font-size: 0.78rem;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.legend-dot--plan {
    background: var(--menu-plans-good);
}

.legend-dot--selection {
    background: var(--menu-plans-selected);
}

.selection-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin-top: 10px;
}

.selection-card {
    border: 1px solid rgba(125, 211, 252, 0.26);
    border-radius: 12px;
    padding: 10px;
    background: rgba(15, 23, 42, 0.6);
}

.selection-card__label {
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-size: 0.7rem;
    color: #93c5fd;
}

.selection-card__value {
    margin-top: 6px;
    font-size: 0.88rem;
    font-weight: 700;
}

.menu-plans-side__summary {
    margin-top: 14px;
    font-size: 0.93rem;
    line-height: 1.45;
}

.menu-plans-side__actions {
    margin-top: 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

@media (max-width: 960px) {
    .menu-plans-calendar__week {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .selection-grid {
        grid-template-columns: 1fr;
    }
}
</style>
