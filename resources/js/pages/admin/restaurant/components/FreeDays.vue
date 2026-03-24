<template>
    <v-col cols="12">
        <ItsGridBox variant="overview" color="primary" title="Freie Tage" icon="mdi-calendar-remove-outline">
            <template #header-actions>
                <div class="free-days-header-actions">
                    <v-btn size="small" variant="outlined" rounded="xl" @click="loadPreviousYear">
                        <v-icon icon="mdi-chevron-left" size="16" class="mr-1" />
                        Vorjahr
                    </v-btn>
                    <v-btn size="small" color="primary" variant="flat" rounded="xl" @click="loadCurrentYear">
                        {{ currentYearLabel }}
                    </v-btn>
                    <v-btn size="small" variant="outlined" rounded="xl" @click="loadNextYear">
                        Naechstes Jahr
                        <v-icon icon="mdi-chevron-right" size="16" class="ml-1" />
                    </v-btn>
                </div>
            </template>

            <div class="free-days-toolbar">
                <div>
                    <div class="free-days-toolbar__eyebrow">Jahresansicht</div>
                    <div class="free-days-toolbar__title">{{ year }}</div>
                    <div class="free-days-toolbar__copy">
                        Tage anklicken, um Aenderungen vorzumerken. Gespeichert wird erst ueber den Button.
                    </div>
                </div>

                <div class="free-days-toolbar__chips">
                    <v-chip color="primary" variant="tonal" rounded="xl">
                        {{ displayFreeDayCount }} freie Tage
                    </v-chip>
                    <v-chip v-if="pendingChangeCount" color="warning" variant="tonal" rounded="xl">
                        {{ pendingChangeCount }} ungespeichert
                    </v-chip>
                </div>
            </div>

            <v-row dense class="mt-1">
                <v-col cols="12" lg="4">
                    <v-sheet rounded="xl" class="free-days-range pa-4">
                        <div class="free-days-range__title">Aenderungen sammeln</div>
                        <div class="free-days-range__copy">
                            Einzelne Tage werden lokal vorgemerkt. Fuer neue Bereiche klicken Sie auf
                            <strong>Bereich waehlen</strong> und dann auf Start- und Endtag im Kalender.
                        </div>

                        <div class="free-days-range__summary">
                            {{ rangeSummary }}
                        </div>

                        <div class="free-days-range__actions">
                            <v-btn
                                :color="isRangeSelecting ? 'secondary' : 'primary'"
                                :variant="isRangeSelecting ? 'outlined' : 'flat'"
                                rounded="xl"
                                @click="toggleRangeSelection">
                                {{ isRangeSelecting ? 'Auswahl abbrechen' : 'Bereich waehlen' }}
                            </v-btn>
                            <v-btn color="primary" rounded="xl" :disabled="!pendingChangeCount" @click="saveChanges">
                                Aenderungen speichern
                            </v-btn>
                            <v-btn variant="text" rounded="xl" :disabled="!pendingChangeCount" @click="discardChanges">
                                Verwerfen
                            </v-btn>
                        </div>
                    </v-sheet>
                </v-col>

                <v-col cols="12" lg="8">
                    <div class="free-days-months">
                        <v-sheet
                            v-for="month in monthCards"
                            :key="month.monthIndex"
                            rounded="xl"
                            class="free-days-month pa-3">
                            <div class="free-days-month__header">
                                <div>
                                    <div class="free-days-month__title">{{ month.label }}</div>
                                    <div class="free-days-month__meta">{{ month.freeCount }} frei</div>
                                </div>
                            </div>

                            <div class="free-days-month__weekdays">
                                <span v-for="weekday in weekdayLabels" :key="weekday">{{ weekday }}</span>
                            </div>

                            <div class="free-days-month__grid">
                                <template v-for="cell in month.cells" :key="cell.key">
                                    <div v-if="cell.empty" class="free-days-month__placeholder" />

                                    <button
                                        v-else
                                        type="button"
                                        class="free-days-month__day"
                                        :class="dayClasses(cell)"
                                        @click="handleDayClick(cell.iso)">
                                        <span class="free-days-month__day-number">{{ cell.day }}</span>
                                        <span class="free-days-month__day-week">{{ cell.weekday }}</span>
                                    </button>
                                </template>
                            </div>
                        </v-sheet>
                    </div>
                </v-col>
            </v-row>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { mapState } from 'pinia'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import { useFreeDayStore } from '@/stores/admin/restaurant/FreeDayStore'

function pad(value) {
    return String(value).padStart(2, '0')
}

export default {
    components: { ItsGridBox },

    data() {
        const currentYear = new Date().getFullYear()

        return {
            currentYear,
            weekdayLabels: ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
            isRangeSelecting: false,
            pendingRangeStart: '',
            lastSelectedRange: null,
        }
    },

    async mounted() {
        const freeDayStore = useFreeDayStore()

        if (! freeDayStore.isLoaded) {
            await freeDayStore.loadYear(this.currentYear)
        }
    },

    computed: {
        ...mapState(useFreeDayStore, ['year', 'pendingChangeCount', 'pendingSetDates', 'pendingUnsetDates', 'displayFreeDayCount']),
        currentYearLabel() {
            return this.year === this.currentYear ? 'Dieses Jahr' : `${this.currentYear}`
        },
        rangeSummary() {
            if (this.isRangeSelecting && ! this.pendingRangeStart) {
                return 'Bereichsauswahl aktiv: Bitte zuerst den Starttag klicken.'
            }

            if (this.isRangeSelecting) {
                return `Start gewaehlt: ${this.formatDate(this.pendingRangeStart)}. Bitte jetzt den Endtag klicken.`
            }

            if (this.lastSelectedRange) {
                return `Zuletzt vorgemerkt: ${this.formatDate(this.lastSelectedRange.start)} bis ${this.formatDate(this.lastSelectedRange.end)}.`
            }

            if (this.pendingChangeCount) {
                return 'Es gibt ungespeicherte Aenderungen. Diese werden erst mit Speichern uebernommen.'
            }

            return 'Keine ungespeicherten Aenderungen.'
        },
        monthCards() {
            const freeDayStore = useFreeDayStore()

            return Array.from({ length: 12 }, (_, monthIndex) => {
                const firstDate = new Date(this.year, monthIndex, 1)
                const monthLabel = firstDate.toLocaleDateString('de-AT', {
                    month: 'long',
                })
                const daysInMonth = new Date(this.year, monthIndex + 1, 0).getDate()
                const leadingEmptyDays = this.leadingEmptyDays(firstDate)
                const cells = []

                for (let index = 0; index < leadingEmptyDays; index++) {
                    cells.push({
                        key: `empty-${monthIndex}-${index}`,
                        empty: true,
                    })
                }

                for (let day = 1; day <= daysInMonth; day++) {
                    const iso = this.isoDate(this.year, monthIndex, day)
                    const date = new Date(this.year, monthIndex, day)
                    const weekday = date.toLocaleDateString('de-AT', {
                        weekday: 'short',
                    })

                    cells.push({
                        key: iso,
                        iso,
                        day,
                        weekday,
                        empty: false,
                        isFree: freeDayStore.isDateFree(iso),
                        isPendingSet: this.pendingSetDates.includes(iso),
                        isPendingUnset: this.pendingUnsetDates.includes(iso),
                    })
                }

                return {
                    monthIndex,
                    label: monthLabel,
                    freeCount: cells.filter((cell) => cell.isFree).length,
                    cells,
                }
            })
        },
    },

    methods: {
        leadingEmptyDays(date) {
            const dayIndex = date.getDay()

            return dayIndex === 0 ? 6 : dayIndex - 1
        },
        isoDate(year, monthIndex, day) {
            return `${year}-${pad(monthIndex + 1)}-${pad(day)}`
        },
        formatDate(isoDate) {
            return new Date(`${isoDate}T00:00:00`).toLocaleDateString('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            })
        },
        dayClasses(cell) {
            const today = new Date()

            return {
                'is-free': cell.isFree,
                'is-pending-set': cell.isPendingSet,
                'is-pending-unset': cell.isPendingUnset,
                'is-range-anchor': this.pendingRangeStart === cell.iso,
                'is-today': cell.iso === this.isoDate(today.getFullYear(), today.getMonth(), today.getDate()),
            }
        },
        resetRangeSelection() {
            this.isRangeSelecting = false
            this.pendingRangeStart = ''
        },
        toggleRangeSelection() {
            if (this.isRangeSelecting) {
                this.resetRangeSelection()
                return
            }

            this.lastSelectedRange = null
            this.isRangeSelecting = true
            this.pendingRangeStart = ''
        },
        async loadPreviousYear() {
            this.resetRangeSelection()
            await useFreeDayStore().loadYear(this.year - 1)
        },
        async loadNextYear() {
            this.resetRangeSelection()
            await useFreeDayStore().loadYear(this.year + 1)
        },
        async loadCurrentYear() {
            this.resetRangeSelection()
            await useFreeDayStore().loadYear(this.currentYear)
        },
        handleDayClick(isoDate) {
            if (! this.isRangeSelecting) {
                useFreeDayStore().toggleDay(isoDate)
                return
            }

            if (! this.pendingRangeStart) {
                this.pendingRangeStart = isoDate
                return
            }

            const startDate = isoDate < this.pendingRangeStart ? isoDate : this.pendingRangeStart
            const endDate = isoDate < this.pendingRangeStart ? this.pendingRangeStart : isoDate

            useFreeDayStore().stageRange(startDate, endDate)
            this.lastSelectedRange = {
                start: startDate,
                end: endDate,
            }
            this.resetRangeSelection()
        },
        async saveChanges() {
            const success = await useFreeDayStore().saveChanges()

            if (success) {
                this.lastSelectedRange = null
                this.resetRangeSelection()
            }
        },
        discardChanges() {
            useFreeDayStore().resetPendingChanges()
            this.lastSelectedRange = null
            this.resetRangeSelection()
        },
    },
}
</script>

<style scoped>
.free-days-header-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.free-days-toolbar {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: flex-start;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.free-days-toolbar__eyebrow {
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.72rem;
    color: rgba(15, 23, 42, 0.56);
}

.free-days-toolbar__title {
    font-size: clamp(1.8rem, 3vw, 2.4rem);
    font-weight: 800;
    line-height: 1;
    color: #0f172a;
}

.free-days-toolbar__copy {
    margin-top: 6px;
    color: rgba(15, 23, 42, 0.68);
    max-width: 560px;
}

.free-days-toolbar__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.free-days-range,
.free-days-month {
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(255, 255, 255, 0.94);
}

.free-days-range__title,
.free-days-month__title {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}

.free-days-range__copy,
.free-days-range__summary,
.free-days-month__meta {
    color: rgba(15, 23, 42, 0.66);
}

.free-days-range__actions {
    margin-top: 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.free-days-months {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.free-days-month__header {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: flex-start;
    margin-bottom: 10px;
}

.free-days-month__weekdays {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 6px;
    margin-bottom: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    color: rgba(15, 23, 42, 0.54);
}

.free-days-month__grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 6px;
}

.free-days-month__placeholder {
    min-height: 52px;
}

.free-days-month__day {
    min-height: 52px;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.28);
    background: #f8fafc;
    color: #0f172a;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    padding: 8px;
    transition: transform 0.16s ease, border-color 0.16s ease, background-color 0.16s ease;
}

.free-days-month__day:hover {
    transform: translateY(-1px);
    border-color: rgba(59, 130, 246, 0.45);
}

.free-days-month__day.is-free {
    border-color: rgba(16, 185, 129, 0.42);
    background: rgba(16, 185, 129, 0.14);
    color: #065f46;
}

.free-days-month__day.is-pending-set {
    border-color: rgba(5, 150, 105, 0.7);
    background: rgba(16, 185, 129, 0.22);
}

.free-days-month__day.is-pending-unset {
    border-color: rgba(239, 68, 68, 0.55);
    background: rgba(254, 226, 226, 0.88);
    color: #991b1b;
}

.free-days-month__day.is-range-anchor {
    box-shadow: inset 0 0 0 2px rgba(59, 130, 246, 0.6);
}

.free-days-month__day.is-today {
    box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.56);
}

.free-days-month__day-number {
    font-size: 0.94rem;
    font-weight: 800;
    line-height: 1;
}

.free-days-month__day-week {
    margin-top: 4px;
    font-size: 0.68rem;
    opacity: 0.72;
}

@media (max-width: 1200px) {
    .free-days-months {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .free-days-month__grid,
    .free-days-month__weekdays {
        gap: 4px;
    }

    .free-days-month__day,
    .free-days-month__placeholder {
        min-height: 44px;
    }
}
</style>
