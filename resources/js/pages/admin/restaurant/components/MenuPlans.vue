<template>
    <v-col cols="12">
        <div class="mp-root">
            <v-row dense class="ma-0">
                <v-col cols="12" lg="8" class="pa-0 pr-lg-3">
                    <v-sheet rounded="xl" elevation="0" class="mp-calendar pa-5" data-testid="menu-plans-calendar">

                        <div class="mp-cal-header">
                            <div>
                                <div class="mp-eyebrow">Kalenderansicht</div>
                                <h3 class="mp-cal-title">{{ currentWeekRangeLabel }}</h3>
                            </div>
                            <div class="mp-cal-nav">
                                <v-btn size="small" variant="outlined" color="secondary" rounded="lg" @click="goToPreviousWeek">
                                    <v-icon icon="mdi-chevron-left" size="18" />
                                </v-btn>
                                <v-btn size="small" variant="tonal" color="warning" rounded="lg" @click="goToCurrentWeek">Heute</v-btn>
                                <v-btn size="small" variant="outlined" color="secondary" rounded="lg" @click="goToNextWeek">
                                    <v-icon icon="mdi-chevron-right" size="18" />
                                </v-btn>
                            </div>
                        </div>

                        <div class="mp-weekday-row">
                            <div v-for="label in weekDayLabels" :key="label" class="mp-weekday-label">{{ label }}</div>
                        </div>

                        <div class="mp-weeks" role="list" aria-label="Wochentage">
                            <div
                                v-for="(week, weekIndex) in calendarWeeks"
                                :key="`week-${weekIndex}`"
                                class="mp-week"
                                :data-testid="`menu-week-${weekIndex + 1}`">
                                <button
                                    v-for="day in week"
                                    :key="day.iso"
                                    type="button"
                                    class="mp-day"
                                    :class="dayCellClasses(day.iso)"
                                    :data-testid="`menu-day-${day.iso}`"
                                    @click="selectDay(day.iso)">
                                    <span class="mp-day__num">{{ day.dayNumber }}</span>
                                    <span class="mp-day__mon">{{ day.monthShort }}</span>
                                    <span v-if="day.hasPlan" class="mp-day__dot" aria-hidden="true" />
                                </button>
                            </div>
                        </div>

                        <div class="mp-legend">
                            <span class="mp-legend-item">
                                <span class="mp-legend-swatch mp-legend-swatch--plan" />
                                Geplanter Zeitraum
                            </span>
                            <span class="mp-legend-item">
                                <span class="mp-legend-swatch mp-legend-swatch--today" />
                                Heute
                            </span>
                            <span class="mp-legend-item">
                                <span class="mp-legend-swatch mp-legend-swatch--select" />
                                Deine Auswahl
                            </span>
                        </div>

                    </v-sheet>
                </v-col>

                <v-col cols="12" lg="4" class="pa-0 pl-lg-3 mt-3 mt-lg-0">
                    <v-sheet rounded="xl" elevation="0" class="mp-side pa-5" data-testid="menu-plans-selection">
                        <h3 class="mp-side__title">Zeitraum w&auml;hlen</h3>
                        <p class="mp-side__hint">Klicke im Kalender erst auf den Starttag, dann auf den Endtag.</p>

                        <div class="mp-steps">
                            <div class="mp-step" :class="stepClass(1, !!selectedStartIso)">
                                <div class="mp-step__indicator">
                                    <v-icon v-if="selectedStartIso" icon="mdi-check" size="14" />
                                    <span v-else>1</span>
                                </div>
                                <div class="mp-step__body">
                                    <div class="mp-step__label">Starttag</div>
                                    <div class="mp-step__value">{{ selectedStartIso ? formatDate(selectedStartIso) : 'Noch nicht gew&auml;hlt' }}</div>
                                </div>
                            </div>

                            <div class="mp-step" :class="stepClass(2, !!selectedEndIso)">
                                <div class="mp-step__indicator">
                                    <v-icon v-if="selectedEndIso" icon="mdi-check" size="14" />
                                    <span v-else>2</span>
                                </div>
                                <div class="mp-step__body">
                                    <div class="mp-step__label">Endtag</div>
                                    <div class="mp-step__value">{{ selectedEndIso ? formatDate(selectedEndIso) : 'Noch nicht gew&auml;hlt' }}</div>
                                </div>
                            </div>

                            <div class="mp-step" :class="{ 'is-active': canCreatePreview || isExistingPlanSelection }">
                                <div class="mp-step__indicator">3</div>
                                <div class="mp-step__body">
                                    <div class="mp-step__label">{{ isExistingPlanSelection ? 'Plan bearbeiten' : 'Plan erstellen' }}</div>
                                    <div class="mp-step__value mp-step__value--note">{{ selectionSummary }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="mp-side-actions">
                            <v-btn
                                v-if="isExistingPlanSelection"
                                color="warning"
                                rounded="xl"
                                block
                                prepend-icon="mdi-pencil-outline"
                                :disabled="!selectedExistingPlan"
                                @click="editSelectedPlan">
                                Bearbeiten
                            </v-btn>
                            <v-btn
                                v-else
                                color="primary"
                                rounded="xl"
                                block
                                prepend-icon="mdi-plus"
                                :disabled="!canCreatePreview"
                                @click="createPreview">
                                Erstellen
                            </v-btn>
                            <v-btn variant="text" color="secondary" rounded="xl" block @click="resetSelection">
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
function isValidIsoDate(value) {
    return /^\d{4}-\d{2}-\d{2}$/.test(String(value || ''))
}

export default {
    data() {
        const todayIso = this.toIso(new Date())
        const routeWeek = this.$route?.query?.week

        return {
            todayIso,
            currentWeekStartIso: this.resolveWeekStartIso(routeWeek, todayIso),
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

    watch: {
        '$route.query.week'(week) {
            if (! isValidIsoDate(week)) {
                return
            }

            this.currentWeekStartIso = this.startOfWeekIso(week)
        },
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
        resolveWeekStartIso(week, fallbackIso) {
            if (! isValidIsoDate(week)) {
                return this.startOfWeekIso(fallbackIso)
            }

            return this.startOfWeekIso(String(week))
        },
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
        selectPlanRange(plan) {
            this.selectedStartIso = plan.start_iso
            this.selectedEndIso = plan.end_iso
        },
        stepClass(stepNum, isDone) {
            let isActive = false

            if (stepNum === 1) {
                isActive = !this.selectedStartIso
            } else if (stepNum === 2) {
                isActive = !!this.selectedStartIso && !this.selectedEndIso
            }

            return { 'is-done': isDone, 'is-active': isActive && !isDone }
        },
        createPreview() {
            if (!this.canCreatePreview) {
                return
            }

            this.navigateToEditor({
                mode: 'create',
                start: this.selectedStartIso,
                end: this.selectedEndIso,
            })
        },
        editSelectedPlan() {
            if (!this.selectedExistingPlan) {
                return
            }

            this.navigateToEditor({
                mode: 'edit',
                plan_id: this.selectedExistingPlan.id,
                start: this.selectedExistingPlan.start_iso,
                end: this.selectedExistingPlan.end_iso,
            })
        },
        navigateToEditor(query) {
            this.$router.push({
                path: '/admin/menu-plans',
                query: {
                    ...query,
                    return_to: '/admin/restaurant/menu-plans',
                    return_week: this.currentWeekStartIso,
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
/* ---- Calendar Panel ---- */

.mp-calendar,
.mp-side {
    border: 1px solid rgba(180, 83, 9, 0.13);
    background: #ffffff;
    box-shadow: 0 4px 24px rgba(15, 23, 42, 0.07);
}

.mp-cal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.mp-eyebrow {
    font-size: 0.71rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #b45309;
    margin-bottom: 3px;
}

.mp-cal-title {
    font-size: 1.08rem;
    font-weight: 800;
    color: #1f2937;
}

.mp-cal-nav {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ---- Weekday Header Row ---- */

.mp-weekday-row {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    margin-bottom: 6px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f3f4f6;
}

.mp-weekday-label {
    text-align: center;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #9ca3af;
}

/* ---- Weeks Grid ---- */

.mp-weeks {
    display: grid;
    gap: 4px;
}

.mp-week {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0;
}

/* ---- Day Cell ---- */

.mp-day {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    padding: 10px 4px;
    min-height: 70px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #ffffff;
    color: #374151;
    cursor: pointer;
    transition: background-color 0.14s ease, border-color 0.14s ease, transform 0.14s ease, box-shadow 0.14s ease;
    text-align: center;
}

.mp-day:hover {
    background: #fefce8;
    border-color: #fbbf24;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.18);
    z-index: 2;
}

.mp-day__num {
    font-size: 1rem;
    font-weight: 800;
    line-height: 1.1;
    color: #1f2937;
}

.mp-day__mon {
    font-size: 0.62rem;
    color: #9ca3af;
    text-transform: capitalize;
}

.mp-day__dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #f59e0b;
    margin-top: 3px;
}

/* ---- Plan State ---- */

.mp-day.has-plan {
    background: linear-gradient(160deg, #fffbeb 0%, #fef3c7 100%);
    border-color: rgba(245, 158, 11, 0.5);
    z-index: 1;
}

.mp-day.has-plan .mp-day__num { color: #78350f; }
.mp-day.has-plan .mp-day__mon { color: #b45309; }

.mp-day.has-plan-start,
.mp-day.has-plan-middle,
.mp-day.has-plan-end {
    border-radius: 0;
}

.mp-day.has-plan-start,
.mp-day.has-plan-middle {
    border-right-color: transparent;
}

.mp-day.has-plan-middle,
.mp-day.has-plan-end {
    border-left-color: transparent;
}

.mp-day.has-plan-start {
    border-top-left-radius: 10px;
    border-bottom-left-radius: 10px;
}

.mp-day.has-plan-end {
    border-top-right-radius: 10px;
    border-bottom-right-radius: 10px;
}

/* ---- Selection State ---- */

.mp-day.is-selected-range {
    background: linear-gradient(160deg, #fff7ed 0%, #ffedd5 100%);
    border-color: rgba(251, 146, 60, 0.65);
}

.mp-day.is-selected-range .mp-day__num { color: #9a3412; }

.mp-day.is-range-start,
.mp-day.is-range-end {
    background: linear-gradient(160deg, #fed7aa 0%, #fdba74 100%);
    border-color: #ea580c;
    border-width: 2px;
}

.mp-day.is-range-start .mp-day__num,
.mp-day.is-range-end .mp-day__num {
    color: #7c2d12;
    font-weight: 900;
}

/* ---- Today ---- */

.mp-day.is-today {
    box-shadow: inset 0 0 0 2px #1e293b;
}

.mp-day.is-today .mp-day__num { color: #1e293b; }

/* ---- Legend ---- */

.mp-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #f3f4f6;
    font-size: 0.77rem;
    color: #6b7280;
}

.mp-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.mp-legend-swatch {
    width: 10px;
    height: 10px;
    display: inline-block;
    flex-shrink: 0;
}

.mp-legend-swatch--plan { background: #f59e0b; border-radius: 3px; }
.mp-legend-swatch--today { border-radius: 50%; box-shadow: inset 0 0 0 2px #1e293b; }
.mp-legend-swatch--select { background: #ea580c; border-radius: 3px; }

/* ---- Sidebar ---- */

.mp-side {
    height: 100%;
}

.mp-side__title {
    font-size: 1.05rem;
    font-weight: 800;
    color: #1f2937;
    margin-bottom: 4px;
}

.mp-side__hint {
    font-size: 0.85rem;
    color: #6b7280;
    line-height: 1.5;
    margin-bottom: 20px;
}

/* ---- Steps ---- */

.mp-steps {
    display: grid;
    gap: 10px;
    margin-bottom: 22px;
}

.mp-step {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 13px 14px;
    border-radius: 14px;
    border: 1px solid #f3f4f6;
    background: #fafafa;
    transition: background-color 0.2s ease, border-color 0.2s ease;
}

.mp-step.is-active {
    background: linear-gradient(145deg, #fffdf5, #fef3c7);
    border-color: rgba(245, 158, 11, 0.4);
}

.mp-step.is-done {
    background: linear-gradient(145deg, #f0fdf4, #dcfce7);
    border-color: rgba(34, 197, 94, 0.3);
}

.mp-step__indicator {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 0.85rem;
    font-weight: 800;
    flex-shrink: 0;
    background: #e5e7eb;
    color: #9ca3af;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.mp-step.is-active .mp-step__indicator {
    background: #f59e0b;
    color: #ffffff;
    box-shadow: 0 3px 10px rgba(245, 158, 11, 0.4);
}

.mp-step.is-done .mp-step__indicator {
    background: #22c55e;
    color: #ffffff;
}

.mp-step__body { flex: 1; min-width: 0; }

.mp-step__label {
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #9ca3af;
    margin-bottom: 3px;
}

.mp-step.is-active .mp-step__label { color: #b45309; }
.mp-step.is-done .mp-step__label { color: #16a34a; }

.mp-step__value {
    font-size: 0.9rem;
    font-weight: 700;
    color: #1f2937;
    line-height: 1.35;
}

.mp-step__value--note {
    font-weight: 400;
    font-size: 0.83rem;
    color: #6b7280;
    line-height: 1.45;
}

/* ---- Sidebar Actions ---- */

.mp-side-actions {
    display: grid;
    gap: 8px;
}

/* ---- Responsive ---- */

@media (max-width: 960px) {
    .mp-week { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .mp-weekday-row { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}

@media (max-width: 600px) {
    .mp-week { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .mp-weekday-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
