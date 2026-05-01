<template>
    <v-col cols="12" lg="6">
        <ItsGridBox
            variant="overview"
            color="secondary"
            title="Abrechnung drucken"
            icon="mdi-receipt-text-outline"
            data-testid="restaurant-reports-billing-card">
            <div class="billing-reports-section billing-reports-section--weeks">
                <div class="billing-reports-toolbar">
                    <div>
                        <div class="billing-reports-toolbar__eyebrow">Kalenderwochen</div>
                        <div class="billing-reports-toolbar__meta">
                            {{ visibleWeeksLabel }}
                        </div>
                    </div>

                    <div class="billing-reports-toolbar__actions">
                        <v-btn
                            size="small"
                            variant="outlined"
                            color="secondary"
                            rounded="lg"
                            :disabled="!hasPreviousWeeksWindow || isLoading"
                            @click="showPreviousWeeksWindow">
                            <v-icon icon="mdi-chevron-left" size="18" />
                        </v-btn>
                        <v-btn
                            size="small"
                            variant="outlined"
                            color="secondary"
                            rounded="lg"
                            :disabled="!hasNextWeeksWindow || isLoading"
                            @click="showNextWeeksWindow">
                            <v-icon icon="mdi-chevron-right" size="18" />
                        </v-btn>
                    </div>
                </div>

                <v-alert
                    v-if="!isLoading && !sortedWeeks.length"
                    type="info"
                    variant="tonal"
                    class="mb-3">
                    Es stehen noch keine abrechenbaren Kalenderwochen zur Verfügung.
                </v-alert>

                <div v-else class="billing-reports-weeks">
                    <button
                        v-for="week in visibleWeeks"
                        :key="week.week_start"
                        type="button"
                        class="billing-reports-week"
                        :class="{
                            'is-selected': isWeekSelected(week.week_start),
                            'is-range-first': isRangeFirst(week.week_start),
                            'is-range-last': isRangeLast(week.week_start),
                        }"
                        :data-testid="`restaurant-billing-week-${week.week_start}`"
                        @click="toggleWeek(week)">
                        <div class="billing-reports-week__title">
                            {{ week.label }}
                        </div>
                        <div class="billing-reports-week__range">
                            {{ week.date_range_label }}
                        </div>
                        <div class="billing-reports-week__state">
                            <span
                                class="billing-reports-pill"
                                :class="weekStatePillClass(week)">
                                {{ weekStateLabel(week) }}
                            </span>
                        </div>
                    </button>
                </div>
            </div>

            <div class="billing-reports-footer">
                <div class="billing-reports-footer__selection">
                    <span class="billing-reports-footer__label">Zeitraum</span>
                    <strong class="billing-reports-footer__value">
                        {{ selectedPeriodLabel }}
                    </strong>
                    <span v-if="selectedRangeLabel" class="billing-reports-footer__meta">
                        {{ selectedRangeLabel }}
                    </span>
                </div>

                <div class="billing-reports-footer__actions">
                    <v-btn
                        v-if="canPreviewSelectedWeeks"
                        color="info"
                        variant="tonal"
                        rounded="xl"
                        prepend-icon="mdi-eye-outline"
                        @click="previewBilling">
                        Abrechnung ansehen
                    </v-btn>

                    <v-btn
                        color="primary"
                        variant="tonal"
                        rounded="xl"
                        prepend-icon="mdi-receipt-text-plus-outline"
                        :disabled="!selectedWeeks.length"
                        @click="confirmDialog = true">
                        Abrechnung drucken
                    </v-btn>
                </div>
            </div>

            <div class="billing-reports-section">
                <div class="billing-reports-toolbar">
                    <div>
                        <div class="billing-reports-toolbar__eyebrow">Zu erstellende Abrechnung</div>
                        <div class="billing-reports-toolbar__meta">
                            {{ visibleBillingsLabel }}
                        </div>
                    </div>

                    <div class="billing-reports-toolbar__actions">
                        <v-btn
                            size="small"
                            variant="outlined"
                            color="secondary"
                            rounded="lg"
                            :disabled="!hasPreviousBillingsWindow || isLoading"
                            @click="showPreviousBillingsWindow">
                            <v-icon icon="mdi-chevron-left" size="18" />
                        </v-btn>
                        <v-btn
                            size="small"
                            variant="outlined"
                            color="secondary"
                            rounded="lg"
                            :disabled="!hasNextBillingsWindow || isLoading"
                            @click="showNextBillingsWindow">
                            <v-icon icon="mdi-chevron-right" size="18" />
                        </v-btn>
                    </div>
                </div>

                <v-progress-linear
                    v-if="isLoading"
                    indeterminate
                    color="secondary"
                    rounded
                    class="mb-4" />

                <v-alert
                    v-else-if="!sortedBillings.length"
                    type="info"
                    variant="tonal"
                    class="mb-3">
                    Es wurden noch keine Abrechnungen erstellt.
                </v-alert>

                <div v-else class="billing-reports-list">
                    <button
                        v-for="billing in visibleBillings"
                        :key="billing.id"
                        type="button"
                        class="billing-reports-item"
                        :data-testid="`restaurant-billing-history-${billing.id}`"
                        @click="openBillingPrint(billing.id)">
                        <div class="billing-reports-item__head">
                            <div>
                                <div class="billing-reports-item__title">
                                    {{ billing.period_label }}
                                </div>
                                <div class="billing-reports-item__range">
                                    {{ billing.date_range_label }}
                                </div>
                            </div>

                            <span class="billing-reports-pill billing-reports-pill--amount">
                                {{ formatCurrency(billing.total_amount) }}
                            </span>
                        </div>

                        <div class="billing-reports-item__meta">
                            <span class="billing-reports-pill billing-reports-pill--muted">
                                {{ billing.bookings_count }} Bestellung<span v-if="Number(billing.bookings_count) !== 1">en</span>
                            </span>
                            <span class="billing-reports-pill billing-reports-pill--muted">
                                {{ billing.created_at }}
                            </span>
                        </div>
                    </button>
                </div>
            </div>
        </ItsGridBox>

        <v-dialog v-model="confirmDialog" max-width="520" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-h6 pt-5 px-5">
                    Zeitraum abrechnen
                </v-card-title>
                <v-card-text class="px-5 pb-2">
                    <div class="billing-reports-dialog__copy">
                        Soll der Zeitraum <strong>{{ selectedPeriodLabel }}</strong> wirklich abgerechnet werden?
                    </div>
                    <div v-if="selectedRangeLabel" class="billing-reports-dialog__meta">
                        {{ selectedRangeLabel }}
                    </div>
                </v-card-text>
                <v-card-actions class="px-5 pb-5 justify-end">
                    <v-btn
                        variant="text"
                        color="secondary"
                        rounded="lg"
                        :disabled="isCreating"
                        @click="confirmDialog = false">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="secondary"
                        variant="flat"
                        rounded="lg"
                        :loading="isCreating"
                        @click="createBilling">
                        Jetzt abrechnen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import { mapState } from 'pinia'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import { useRestaurantBillingStore } from '@/stores/admin/restaurant/RestaurantBillingStore'

function billingCreatedAtSortValue(value) {
    const normalized = String(value || '').trim()

    const localizedMatch = normalized.match(/^(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{2}):(\d{2}))?$/)

    if (localizedMatch) {
        const [, day, month, year, hours = '00', minutes = '00'] = localizedMatch

        return new Date(`${year}-${month}-${day}T${hours}:${minutes}:00`).getTime()
    }

    const timestamp = Date.parse(normalized)

    return Number.isNaN(timestamp) ? 0 : timestamp
}

export default {
    components: { ItsGridBox },

    data() {
        return {
            isLoading: false,
            isCreating: false,
            confirmDialog: false,
            previewActionHidden: false,
            billingsWindowStart: 0,
            weeksWindowStart: 0,
            selectionAnchor: null,
            selectionStart: null,
            selectionEnd: null,
        }
    },

    computed: {
        ...mapState(useRestaurantBillingStore, ['billings', 'weeks']),
        sortedBillings() {
            return [...(this.billings || [])].sort((left, right) => {
                return billingCreatedAtSortValue(right?.created_at) - billingCreatedAtSortValue(left?.created_at)
            })
        },
        visibleBillings() {
            return this.sortedBillings.slice(this.billingsWindowStart, this.billingsWindowStart + 3)
        },
        hasPreviousBillingsWindow() {
            return this.billingsWindowStart > 0
        },
        hasNextBillingsWindow() {
            return this.billingsWindowStart + 3 < this.sortedBillings.length
        },
        billingsMaxWindowStart() {
            return Math.max(this.sortedBillings.length - 3, 0)
        },
        visibleBillingsLabel() {
            if (!this.sortedBillings.length) {
                return 'Keine Abrechnungen vorhanden'
            }

            const start = this.billingsWindowStart + 1
            const end = this.billingsWindowStart + this.visibleBillings.length

            return `${start}-${end} von ${this.sortedBillings.length}`
        },
        sortedWeeks() {
            return [...(this.weeks || [])]
                .filter((week) => week.is_billed !== true)
                .sort((left, right) => {
                    return String(left?.week_start || '').localeCompare(String(right?.week_start || ''))
                })
        },
        visibleWeeks() {
            return this.sortedWeeks.slice(this.weeksWindowStart, this.weeksWindowStart + 8)
        },
        hasPreviousWeeksWindow() {
            return this.weeksWindowStart > 0
        },
        hasNextWeeksWindow() {
            return this.weeksWindowStart + 8 < this.sortedWeeks.length
        },
        weeksMaxWindowStart() {
            return Math.max(this.sortedWeeks.length - 8, 0)
        },
        visibleWeeksLabel() {
            if (!this.sortedWeeks.length) {
                return 'Keine Kalenderwochen verfügbar'
            }

            const start = this.weeksWindowStart + 1
            const end = this.weeksWindowStart + this.visibleWeeks.length

            return `${start}-${end} von ${this.sortedWeeks.length}`
        },
        selectedWeeks() {
            if (!this.selectionStart || !this.selectionEnd) {
                return []
            }

            const startIndex = this.sortedWeeks.findIndex((week) => week.week_start === this.selectionStart)
            const endIndex = this.sortedWeeks.findIndex((week) => week.week_start === this.selectionEnd)

            if (startIndex === -1 || endIndex === -1) {
                return []
            }

            return this.sortedWeeks.slice(Math.min(startIndex, endIndex), Math.max(startIndex, endIndex) + 1)
        },
        selectedPeriodLabel() {
            if (!this.selectedWeeks.length) {
                return 'Keine Kalenderwoche ausgewählt'
            }

            const firstWeek = this.selectedWeeks[0]
            const lastWeek = this.selectedWeeks[this.selectedWeeks.length - 1]

            return this.periodLabel(firstWeek.week_start, lastWeek.week_end)
        },
        selectedRangeLabel() {
            if (!this.selectedWeeks.length) {
                return ''
            }

            const firstWeek = this.selectedWeeks[0]
            const lastWeek = this.selectedWeeks[this.selectedWeeks.length - 1]

            return `${this.formatDate(firstWeek.week_start)} - ${this.formatDate(lastWeek.week_end)}`
        },
        canPreviewSelectedWeeks() {
            return this.selectedWeeks.length > 0 && !this.previewActionHidden
        },
    },

    async created() {
        this.isLoading = true

        const billingStore = useRestaurantBillingStore()

        if (!billingStore.isLoaded) {
            await billingStore.load()
        }

        this.initializeWindows()
        this.isLoading = false
    },

    methods: {
        initializeWindows() {
            this.billingsWindowStart = 0

            if (!this.sortedWeeks.length) {
                this.weeksWindowStart = 0
                this.selectionAnchor = null
                this.selectionStart = null
                this.selectionEnd = null
                return
            }

            this.weeksWindowStart = Math.max(0, Math.min(this.sortedWeeks.length - 4, this.weeksMaxWindowStart))

            const firstWeek = this.sortedWeeks[0] || null

            this.selectionAnchor = firstWeek?.week_start || null
            this.selectionStart = firstWeek?.week_start || null
            this.selectionEnd = firstWeek?.week_start || null
        },
        showPreviousBillingsWindow() {
            if (!this.hasPreviousBillingsWindow) {
                return
            }

            this.billingsWindowStart = Math.max(0, this.billingsWindowStart - 3)
        },
        showNextBillingsWindow() {
            if (!this.hasNextBillingsWindow) {
                return
            }

            this.billingsWindowStart = Math.min(this.billingsMaxWindowStart, this.billingsWindowStart + 3)
        },
        showPreviousWeeksWindow() {
            if (!this.hasPreviousWeeksWindow) {
                return
            }

            this.weeksWindowStart = Math.max(0, this.weeksWindowStart - 8)
        },
        showNextWeeksWindow() {
            if (!this.hasNextWeeksWindow) {
                return
            }

            this.weeksWindowStart = Math.min(this.weeksMaxWindowStart, this.weeksWindowStart + 8)
        },
        toggleWeek(week) {
            if (!week) {
                return
            }

            this.previewActionHidden = false

            if (!this.selectionAnchor || this.isWeekSelected(week.week_start)) {
                this.setSelection(week.week_start, week.week_start, week.week_start)
                return
            }

            const anchorIndex = this.sortedWeeks.findIndex((item) => item.week_start === this.selectionAnchor)
            const targetIndex = this.sortedWeeks.findIndex((item) => item.week_start === week.week_start)

            if (anchorIndex === -1 || targetIndex === -1) {
                this.setSelection(week.week_start, week.week_start, week.week_start)
                return
            }

            const range = this.sortedWeeks.slice(Math.min(anchorIndex, targetIndex), Math.max(anchorIndex, targetIndex) + 1)

            this.setSelection(
                this.selectionAnchor,
                range[0]?.week_start || week.week_start,
                range[range.length - 1]?.week_start || week.week_start,
            )
        },
        setSelection(anchor, start, end) {
            this.selectionAnchor = anchor
            this.selectionStart = start
            this.selectionEnd = end
        },
        isWeekSelected(weekStart) {
            return this.selectedWeeks.some((week) => week.week_start === weekStart)
        },
        isRangeFirst(weekStart) {
            return this.selectedWeeks.length > 0 && this.selectedWeeks[0].week_start === weekStart
        },
        isRangeLast(weekStart) {
            return this.selectedWeeks.length > 0 && this.selectedWeeks[this.selectedWeeks.length - 1].week_start === weekStart
        },
        weekStatePillClass(week) {
            return this.isWeekSelected(week.week_start)
                ? 'billing-reports-pill--selected'
                : 'billing-reports-pill--active'
        },
        weekStateLabel(week) {
            return this.isWeekSelected(week.week_start) ? 'Ausgewählt' : 'Offen'
        },
        formatDate(isoString) {
            if (!isoString) {
                return ''
            }

            return new Intl.DateTimeFormat('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            }).format(new Date(`${isoString}T00:00:00`))
        },
        periodLabel(startIso, endIso) {
            if (!startIso || !endIso) {
                return 'KW --'
            }

            const startDate = new Date(`${startIso}T00:00:00`)
            const endDate = new Date(`${endIso}T00:00:00`)
            const startWeek = this.weekNumberLabel(startDate)
            const endWeek = this.weekNumberLabel(endDate)
            const startYear = this.weekYearLabel(startDate)
            const endYear = this.weekYearLabel(endDate)

            if (startWeek === endWeek && startYear === endYear) {
                return `KW ${startWeek}/${startYear}`
            }

            if (startYear === endYear) {
                return `KW ${startWeek}-${endWeek}/${startYear}`
            }

            return `KW ${startWeek}/${startYear} - KW ${endWeek}/${endYear}`
        },
        weekNumberLabel(date) {
            const workingDate = new Date(date)
            const day = workingDate.getDay() || 7
            workingDate.setDate(workingDate.getDate() + 4 - day)
            const yearStart = new Date(workingDate.getFullYear(), 0, 1)
            const weekNumber = Math.ceil((((workingDate - yearStart) / 86400000) + 1) / 7)

            return String(weekNumber).padStart(2, '0')
        },
        weekYearLabel(date) {
            const workingDate = new Date(date)
            const day = workingDate.getDay() || 7
            workingDate.setDate(workingDate.getDate() + 4 - day)

            return workingDate.getFullYear()
        },
        formatCurrency(value) {
            return new Intl.NumberFormat('de-AT', {
                style: 'currency',
                currency: 'EUR',
            }).format(Number(value || 0))
        },
        openBillingPrint(billingId) {
            if (!billingId) {
                return
            }

            window.open(`/api/admin/restaurant/billings/${billingId}/print`, '_blank', 'noopener')
        },
        previewBilling() {
            if (!this.selectedWeeks.length) {
                return
            }

            const params = new URLSearchParams()

            this.selectedWeeks.forEach((week) => {
                params.append('weeks[]', week.week_start)
            })

            window.open(`/api/admin/restaurant/billings/preview?${params.toString()}`, '_blank', 'noopener')
        },
        async createBilling() {
            if (!this.selectedWeeks.length) {
                return
            }

            const billingStore = useRestaurantBillingStore()

            this.isCreating = true

            try {
                const createdBilling = await billingStore.create({
                    weeks: this.selectedWeeks.map((week) => week.week_start),
                })

                if (!createdBilling?.id) {
                    return
                }

                this.previewActionHidden = true
                this.confirmDialog = false
                this.initializeWindows()
                this.openBillingPrint(createdBilling.id)
            } finally {
                this.isCreating = false
            }
        },
    },
}
</script>

<style scoped>
.billing-reports-section + .billing-reports-section {
    margin-top: 18px;
    padding-top: 18px;
    border-top: 1px solid rgba(148, 163, 184, 0.22);
}

.billing-reports-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 12px;
}

.billing-reports-toolbar__eyebrow {
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
}

.billing-reports-toolbar__meta {
    margin-top: 4px;
    font-size: 0.96rem;
    font-weight: 600;
    color: #0f172a;
}

.billing-reports-toolbar__actions {
    display: inline-flex;
    gap: 8px;
}

.billing-reports-list {
    display: grid;
    gap: 10px;
}

.billing-reports-item,
.billing-reports-week {
    width: 100%;
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 18px;
    background: #fff;
    padding: 14px;
    text-align: left;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.billing-reports-item:hover,
.billing-reports-week:hover:not(:disabled) {
    border-color: rgba(14, 116, 144, 0.55);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
    transform: translateY(-1px);
}

.billing-reports-item__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.billing-reports-item__title,
.billing-reports-week__title {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}

.billing-reports-item__range,
.billing-reports-week__range {
    margin-top: 4px;
    font-size: 0.92rem;
    color: #475569;
}

.billing-reports-item__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.billing-reports-pill {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.01em;
}

.billing-reports-pill--amount {
    background: rgba(234, 179, 8, 0.14);
    color: #a16207;
}

.billing-reports-pill--muted {
    background: rgba(148, 163, 184, 0.16);
    color: #475569;
}

.billing-reports-pill--active {
    background: rgba(14, 165, 233, 0.14);
    color: #0369a1;
}

.billing-reports-weeks {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.billing-reports-week.is-selected {
    border-color: #0f766e;
    border-width: 2px;
    background: linear-gradient(135deg, rgba(15, 118, 110, 0.08) 0%, rgba(20, 184, 166, 0.10) 100%);
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.14), 0 4px 12px rgba(15, 118, 110, 0.10);
    border-left: 4px solid #0f766e;
    padding-left: 12px;
}

.billing-reports-week.is-selected .billing-reports-week__title {
    color: #0f766e;
}

.billing-reports-week.is-range-first {
    position: relative;
}

.billing-reports-week.is-range-first::before {
    content: 'VON';
    position: absolute;
    top: 6px;
    right: 10px;
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #0f766e;
    opacity: 0.7;
}

.billing-reports-week.is-range-last:not(.is-range-first) {
    position: relative;
}

.billing-reports-week.is-range-last:not(.is-range-first)::before {
    content: 'BIS';
    position: absolute;
    top: 6px;
    right: 10px;
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #0f766e;
    opacity: 0.7;
}

.billing-reports-week__state {
    margin-top: 10px;
}

.billing-reports-pill--selected {
    background: rgba(15, 118, 110, 0.16);
    color: #0f766e;
}

.billing-reports-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-top: 18px;
}

.billing-reports-footer__actions {
    display: inline-flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
}

.billing-reports-footer__selection {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.billing-reports-footer__label {
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
}

.billing-reports-footer__value {
    color: #0f172a;
}

.billing-reports-footer__meta,
.billing-reports-dialog__meta {
    font-size: 0.9rem;
    color: #475569;
}

.billing-reports-dialog__copy {
    font-size: 0.98rem;
    color: #0f172a;
}

@media (max-width: 960px) {
    .billing-reports-weeks {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 720px) {
    .billing-reports-toolbar,
    .billing-reports-footer,
    .billing-reports-item__head {
        flex-direction: column;
        align-items: stretch;
    }

    .billing-reports-toolbar__actions {
        justify-content: flex-end;
    }

    .billing-reports-footer__actions {
        justify-content: stretch;
    }
}
</style>
