<template>
    <v-col cols="12" lg="6">
        <ItsGridBox
            variant="overview"
            color="primary"
            title="Menüsummen drucken"
            icon="mdi-printer-outline"
            data-testid="restaurant-reports-order-list-card">
            <div class="restaurant-reports-toolbar">
                <div>
                    <div class="restaurant-reports-toolbar__eyebrow">Menüpläne</div>
                    <div class="restaurant-reports-toolbar__meta">
                        {{ visiblePlansLabel }}
                    </div>
                </div>

                <div class="restaurant-reports-toolbar__actions">
                    <v-btn
                        size="small"
                        variant="outlined"
                        color="secondary"
                        rounded="lg"
                        :disabled="!hasPreviousWindow || isLoading"
                        @click="showPreviousWindow">
                        <v-icon icon="mdi-chevron-left" size="18" />
                    </v-btn>
                    <v-btn
                        size="small"
                        variant="outlined"
                        color="secondary"
                        rounded="lg"
                        :disabled="!hasNextWindow || isLoading"
                        @click="showNextWindow">
                        <v-icon icon="mdi-chevron-right" size="18" />
                    </v-btn>
                </div>
            </div>

            <v-progress-linear
                v-if="isLoading"
                indeterminate
                color="primary"
                rounded
                class="mb-4" />

            <v-alert
                v-else-if="!sortedPlans.length"
                type="info"
                variant="tonal"
                class="mb-3">
                Es sind noch keine Menüpläne vorhanden.
            </v-alert>

            <div v-else class="restaurant-reports-plan-list">
                <button
                    v-for="plan in visiblePlans"
                    :key="plan.id"
                    type="button"
                    class="restaurant-reports-plan"
                    :class="{ 'is-selected': selectedPlanId === plan.id }"
                    :data-testid="`restaurant-report-plan-${plan.id}`"
                    @click="selectPlan(plan.id)">
                    <div class="restaurant-reports-plan__head">
                        <div>
                            <div class="restaurant-reports-plan__title">
                                {{ planDisplayTitle(plan) }}
                            </div>
                            <div class="restaurant-reports-plan__range">
                                {{ planRangeLabel(plan) }}
                            </div>
                        </div>

                        <span
                            v-if="isCurrentPlan(plan)"
                            class="restaurant-reports-pill restaurant-reports-pill--current">
                            Aktuell
                        </span>
                    </div>

                    <div class="restaurant-reports-plan__states">
                        <span class="restaurant-reports-pill restaurant-reports-pill--count">
                            {{ planBookingsSummary(plan) }}
                        </span>
                        <span class="restaurant-reports-pill" :class="planVisibilityState(plan).className">
                            {{ planVisibilityState(plan).label }}
                        </span>
                        <span class="restaurant-reports-pill" :class="planOrderState(plan).className">
                            {{ planOrderState(plan).label }}
                        </span>
                    </div>
                </button>
            </div>

            <div class="restaurant-reports-footer">
                <div class="restaurant-reports-footer__selection">
                    <span class="restaurant-reports-footer__label">Ausgewählt</span>
                    <strong class="restaurant-reports-footer__value">
                        {{ selectedPlan ? planDisplayTitle(selectedPlan) : 'Kein Menüplan' }}
                    </strong>
                </div>

                <v-btn
                    color="primary"
                    variant="tonal"
                    rounded="xl"
                    prepend-icon="mdi-format-list-bulleted"
                    :disabled="!selectedPlan"
                    @click="openSummaryPrint">
                    Drucken
                </v-btn>
            </div>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { mapState } from 'pinia'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import { useMenuPlanStore } from '@/stores/admin/restaurant/MenuPlanStore'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'

function defaultOnlineSettings() {
    return {
        visibility_start_mode: 'when_available',
        visibility_start_week_offset: 2,
        visibility_start_day_of_week: 0,
        visibility_start_time: '15:00',
        order_start_mode: 'when_available',
        order_start_week_offset: 2,
        order_start_day_of_week: 0,
        order_start_time: '15:00',
        order_end_week_offset: 0,
        order_end_day_of_week: 4,
        order_end_time: '09:00',
        visibility_end_mode: 'plan_end',
    }
}

export default {
    components: { ItsGridBox },

    data() {
        return {
            isLoading: false,
            selectedPlanId: null,
            windowStartIndex: 0,
            clockValue: new Date(),
            clockInterval: null,
        }
    },

    computed: {
        ...mapState(useMenuPlanStore, ['plans']),
        ...mapState(useRestaurantStore, ['onlineSettings']),
        sortedPlans() {
            return [...(this.plans || [])].sort((left, right) => {
                return String(left?.start_date || '').localeCompare(String(right?.start_date || ''))
            })
        },
        visiblePlans() {
            return this.sortedPlans.slice(this.windowStartIndex, this.windowStartIndex + 5)
        },
        selectedPlan() {
            return this.sortedPlans.find((plan) => plan.id === this.selectedPlanId) || null
        },
        hasPreviousWindow() {
            return this.windowStartIndex > 0
        },
        hasNextWindow() {
            return this.windowStartIndex + 5 < this.sortedPlans.length
        },
        maxWindowStart() {
            return Math.max(this.sortedPlans.length - 5, 0)
        },
        visiblePlansLabel() {
            if (!this.sortedPlans.length) {
                return 'Keine Menüpläne verfügbar'
            }

            const start = this.windowStartIndex + 1
            const end = this.windowStartIndex + this.visiblePlans.length

            return `${start}-${end} von ${this.sortedPlans.length}`
        },
    },

    async created() {
        this.isLoading = true

        const menuPlanStore = useMenuPlanStore()
        const restaurantStore = useRestaurantStore()
        const loadingTasks = []

        if (!restaurantStore.settings) {
            loadingTasks.push(restaurantStore.loadSettings())
        }

        if (!menuPlanStore.isLoaded) {
            loadingTasks.push(menuPlanStore.load())
        }

        if (loadingTasks.length > 0) {
            await Promise.all(loadingTasks)
        }

        this.initializePlanWindow()
        this.startClock()
        this.isLoading = false
    },

    beforeUnmount() {
        if (this.clockInterval) {
            window.clearInterval(this.clockInterval)
            this.clockInterval = null
        }
    },

    methods: {
        startClock() {
            if (typeof window === 'undefined') {
                return
            }

            this.clockInterval = window.setInterval(() => {
                this.clockValue = new Date()
            }, 60000)
        },
        initializePlanWindow() {
            if (!this.sortedPlans.length) {
                this.selectedPlanId = null
                this.windowStartIndex = 0
                return
            }

            const referencePlanIndex = this.resolveReferencePlanIndex()
            const nextWindowStart = this.centerWindowAround(referencePlanIndex)
            const referencePlan = this.sortedPlans[referencePlanIndex] || null

            this.windowStartIndex = nextWindowStart
            this.selectedPlanId = referencePlan?.id || this.sortedPlans[nextWindowStart]?.id || null
        },
        resolveReferencePlanIndex() {
            if (!this.sortedPlans.length) {
                return 0
            }

            const todayIso = this.toIso(this.nowDate())
            const containingTodayIndex = this.sortedPlans.findIndex((plan) => {
                return String(plan.start_date || '') <= todayIso && String(plan.end_date || '') >= todayIso
            })

            if (containingTodayIndex !== -1) {
                return containingTodayIndex
            }

            const upcomingIndex = this.sortedPlans.findIndex((plan) => String(plan.start_date || '') > todayIso)

            if (upcomingIndex !== -1) {
                return upcomingIndex
            }

            return this.sortedPlans.length - 1
        },
        centerWindowAround(index) {
            return Math.max(0, Math.min(index - 2, this.maxWindowStart))
        },
        selectPlan(planId) {
            this.selectedPlanId = planId
        },
        showPreviousWindow() {
            if (!this.hasPreviousWindow) {
                return
            }

            const nextWindowStart = Math.max(0, this.windowStartIndex - 5)
            this.windowStartIndex = nextWindowStart
            this.selectedPlanId = this.sortedPlans[nextWindowStart]?.id || null
        },
        showNextWindow() {
            if (!this.hasNextWindow) {
                return
            }

            const nextWindowStart = Math.min(this.maxWindowStart, this.windowStartIndex + 5)
            this.windowStartIndex = nextWindowStart
            this.selectedPlanId = this.sortedPlans[nextWindowStart]?.id || null
        },
        nowDate() {
            return this.clockValue instanceof Date ? this.clockValue : new Date(this.clockValue)
        },
        toDate(isoString) {
            return new Date(`${isoString}T00:00:00`)
        },
        toIso(date) {
            return [
                date.getFullYear(),
                String(date.getMonth() + 1).padStart(2, '0'),
                String(date.getDate()).padStart(2, '0'),
            ].join('-')
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
        isoAtTime(isoString, timeString = '00:00', useEndOfDay = false) {
            const date = this.toDate(isoString)

            if (useEndOfDay) {
                date.setHours(23, 59, 59, 999)
                return date
            }

            const [hours, minutes] = String(timeString || '00:00')
                .split(':')
                .map((value) => Number.parseInt(value, 10) || 0)

            date.setHours(hours, minutes, 0, 0)

            return date
        },
        dayOffsetFromMonday(dayOfWeek) {
            const normalized = Number(dayOfWeek)

            return normalized === 0 ? 6 : normalized - 1
        },
        planScheduleSettings(plan) {
            const settings = {
                ...defaultOnlineSettings(),
                ...(this.onlineSettings || {}),
            }

            return {
                visibility_start_mode: plan?.visibility_start_mode || settings.visibility_start_mode,
                visibility_start_week_offset: Number(plan?.visibility_start_week_offset ?? settings.visibility_start_week_offset),
                visibility_start_day_of_week: Number(plan?.visibility_start_day_of_week ?? settings.visibility_start_day_of_week),
                visibility_start_time: String(plan?.visibility_start_time || settings.visibility_start_time || '15:00'),
                order_start_mode: plan?.order_start_mode || settings.order_start_mode,
                order_start_week_offset: Number(plan?.order_start_week_offset ?? settings.order_start_week_offset),
                order_start_day_of_week: Number(plan?.order_start_day_of_week ?? settings.order_start_day_of_week),
                order_start_time: String(plan?.order_start_time || settings.order_start_time || '15:00'),
                order_end_week_offset: Number(plan?.order_end_week_offset ?? settings.order_end_week_offset),
                order_end_day_of_week: Number(plan?.order_end_day_of_week ?? settings.order_end_day_of_week),
                order_end_time: String(plan?.order_end_time || settings.order_end_time || '09:00'),
                visibility_end_mode: plan?.visibility_end_mode || settings.visibility_end_mode,
            }
        },
        individualScheduleDateTime(plan, field) {
            if (plan?.use_individual_schedule_values !== true) {
                return null
            }

            const value = String(plan?.[field] || '').trim()

            if (!value) {
                return null
            }

            return value.includes('T') ? new Date(value) : this.toDate(value)
        },
        scheduledDateTime(plan, weekOffset, dayOfWeek, timeString) {
            const menuWeekStartIso = this.startOfWeekIso(plan.start_date)
            const targetIso = this.addDaysIso(menuWeekStartIso, this.dayOffsetFromMonday(dayOfWeek) - (Number(weekOffset) * 7))

            return this.isoAtTime(targetIso, timeString)
        },
        visibilityStartDateTime(plan) {
            const individualValue = this.individualScheduleDateTime(plan, 'visible_start_at')

            if (individualValue) {
                return individualValue
            }

            const schedule = this.planScheduleSettings(plan)

            if (schedule.visibility_start_mode === 'scheduled') {
                return this.scheduledDateTime(plan, schedule.visibility_start_week_offset, schedule.visibility_start_day_of_week, schedule.visibility_start_time)
            }

            if (schedule.visibility_start_mode === 'when_orderable') {
                return this.orderStartDateTime(plan)
            }

            return new Date(0)
        },
        visibilityEndDateTime(plan) {
            const individualValue = this.individualScheduleDateTime(plan, 'visible_end_at')

            if (individualValue) {
                return individualValue
            }

            const schedule = this.planScheduleSettings(plan)
            const visibilityEndIso = schedule.visibility_end_mode === 'week_end'
                ? this.addDaysIso(this.startOfWeekIso(plan.end_date), 6)
                : plan.end_date

            return this.isoAtTime(visibilityEndIso, '23:59', true)
        },
        orderStartDateTime(plan) {
            const individualValue = this.individualScheduleDateTime(plan, 'order_start_at')

            if (individualValue) {
                return individualValue
            }

            const schedule = this.planScheduleSettings(plan)

            return schedule.order_start_mode === 'scheduled'
                ? this.scheduledDateTime(plan, schedule.order_start_week_offset, schedule.order_start_day_of_week, schedule.order_start_time)
                : new Date(0)
        },
        orderEndDateTime(plan) {
            const individualValue = this.individualScheduleDateTime(plan, 'order_end_at')

            if (individualValue) {
                return individualValue
            }

            const schedule = this.planScheduleSettings(plan)

            return this.scheduledDateTime(plan, schedule.order_end_week_offset, schedule.order_end_day_of_week, schedule.order_end_time)
        },
        statusState(isCurrent, isPast, currentLabel, futureLabel, pastLabel) {
            if (isCurrent) {
                return {
                    label: currentLabel,
                    className: 'restaurant-reports-pill--active',
                }
            }

            if (isPast) {
                return {
                    label: pastLabel,
                    className: 'restaurant-reports-pill--past',
                }
            }

            return {
                label: futureLabel,
                className: 'restaurant-reports-pill--muted',
            }
        },
        planVisibilityState(plan) {
            if (plan?.is_available !== true) {
                return {
                    label: 'Nicht sichtbar',
                    className: 'restaurant-reports-pill--muted',
                }
            }

            const now = this.nowDate()
            const visibilityStart = this.visibilityStartDateTime(plan)
            const visibilityEnd = this.visibilityEndDateTime(plan)

            return this.statusState(
                visibilityStart <= now && now <= visibilityEnd,
                now > visibilityEnd,
                'Sichtbar',
                'Nicht sichtbar',
                'Nicht mehr sichtbar',
            )
        },
        planOrderState(plan) {
            if (plan?.is_available !== true) {
                return {
                    label: 'Nicht bestellbar',
                    className: 'restaurant-reports-pill--muted',
                }
            }

            const now = this.nowDate()
            const orderStart = this.orderStartDateTime(plan)
            const orderEnd = this.orderEndDateTime(plan)

            return this.statusState(
                orderStart <= now && now <= orderEnd,
                now > orderEnd,
                'Bestellbar',
                'Nicht bestellbar',
                'Nicht mehr bestellbar',
            )
        },
        isCurrentPlan(plan) {
            const todayIso = this.toIso(this.nowDate())

            return String(plan.start_date || '') <= todayIso && String(plan.end_date || '') >= todayIso
        },
        planDisplayTitle(plan) {
            const title = String(plan?.title || '').trim()

            return title || `Bestellliste KW ${this.isoWeekLabel(plan?.start_date)} (${this.planBookingsSummary(plan)})`
        },
        planBookingsCount(plan) {
            return (Array.isArray(plan?.entries) ? plan.entries : []).reduce((sum, entry) => {
                return sum + Number(entry?.booked_menu_count || 0)
            }, 0)
        },
        planBookingsSummary(plan) {
            const bookingsCount = this.planBookingsCount(plan)

            return bookingsCount === 1 ? '1 Bestellung' : `${bookingsCount} Bestellungen`
        },
        isoWeekLabel(isoString) {
            if (!isoString) {
                return '--'
            }

            const date = this.toDate(isoString)
            const day = date.getDay() || 7

            date.setDate(date.getDate() + 4 - day)

            const yearStart = new Date(date.getFullYear(), 0, 1)
            const weekNumber = Math.ceil((((date - yearStart) / 86400000) + 1) / 7)

            return String(weekNumber).padStart(2, '0')
        },
        formatDate(isoString) {
            if (!isoString) {
                return ''
            }

            return new Intl.DateTimeFormat('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            }).format(this.toDate(isoString))
        },
        planRangeLabel(plan) {
            return `${this.formatDate(plan.start_date)} - ${this.formatDate(plan.end_date)}`
        },
        openSummaryPrint() {
            if (!this.selectedPlan?.id) {
                return
            }

            const url = `/api/admin/restaurant/menu-plans/${this.selectedPlan.id}/print?type=summary`
            window.open(url, '_blank', 'noopener')
        },
    },
}
</script>

<style scoped>
.restaurant-reports-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
}

.restaurant-reports-toolbar__eyebrow {
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
}

.restaurant-reports-toolbar__meta {
    margin-top: 4px;
    font-size: 0.96rem;
    font-weight: 600;
    color: #0f172a;
}

.restaurant-reports-toolbar__actions {
    display: inline-flex;
    gap: 8px;
}

.restaurant-reports-plan-list {
    display: grid;
    gap: 12px;
}

.restaurant-reports-plan {
    width: 100%;
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 18px;
    background: #fff;
    padding: 16px;
    text-align: left;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.restaurant-reports-plan:hover {
    border-color: rgba(59, 130, 246, 0.5);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
    transform: translateY(-1px);
}

.restaurant-reports-plan.is-selected {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.restaurant-reports-plan__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.restaurant-reports-plan__title {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}

.restaurant-reports-plan__range {
    margin-top: 4px;
    font-size: 0.92rem;
    color: #475569;
}

.restaurant-reports-plan__states {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.restaurant-reports-pill {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.01em;
}

.restaurant-reports-pill--current {
    background: rgba(37, 99, 235, 0.12);
    color: #1d4ed8;
}

.restaurant-reports-pill--active {
    background: rgba(14, 165, 233, 0.14);
    color: #0369a1;
}

.restaurant-reports-pill--past {
    background: rgba(249, 115, 22, 0.12);
    color: #c2410c;
}

.restaurant-reports-pill--muted {
    background: rgba(148, 163, 184, 0.16);
    color: #475569;
}

.restaurant-reports-pill--count {
    background: rgba(234, 179, 8, 0.14);
    color: #a16207;
}

.restaurant-reports-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-top: 18px;
}

.restaurant-reports-footer__selection {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.restaurant-reports-footer__label {
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
}

.restaurant-reports-footer__value {
    color: #0f172a;
}

@media (max-width: 720px) {
    .restaurant-reports-toolbar,
    .restaurant-reports-footer {
        flex-direction: column;
        align-items: stretch;
    }

    .restaurant-reports-toolbar__actions {
        justify-content: flex-end;
    }

    .restaurant-reports-plan__head {
        flex-direction: column;
    }
}
</style>
