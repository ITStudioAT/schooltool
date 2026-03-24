<template>
    <div class="mpe-page">
        <v-container fluid class="ma-0 w-100 pa-2">
            <AdminSectionHero
                class="mb-3"
                eyebrow="Restaurant"
                title="Men&uuml;pl&auml;ne"
                :active-section="headerActiveSection"
                :chips="headerChips"
                :show-current-user-chip="true"
                focus-label="Seitenstatus"
                primary-color="#213547"
                secondary-color="#b45309"
                left-orb-color="#fde68a"
                right-orb-color="#fdba74" />

            <v-row dense>
                <v-col cols="12">
                    <v-sheet rounded="xl" class="mpe-stage pa-5">

                        <!-- Dark gradient header -->
                        <div class="mpe-header">
                            <div class="mpe-header__left">
                                <div class="mpe-header__eyebrow">{{ entryModeLabel }}</div>
                                <h2 class="mpe-header__title">{{ entryHeadline }}</h2>
                                <div class="mpe-header__range">{{ rangeLabel }}</div>
                            </div>
                            <div class="mpe-header__right">
                                <div class="mpe-badge" data-testid="plan-progress-badge">
                                    <strong>{{ coveragePercent }}%</strong>
                                    <span>fertig</span>
                                </div>
                                <v-btn
                                    color="white"
                                    rounded="xl"
                                    variant="tonal"
                                    prepend-icon="mdi-arrow-left"
                                    :to="backTarget">
                                    {{ backButtonLabel }}
                                </v-btn>
                            </div>
                        </div>

                        <!-- Summary stats strip -->
                        <div class="mpe-summary">
                            <div class="mpe-stat">
                                <v-icon icon="mdi-calendar-week" size="15" class="mpe-stat__icon" />
                                <span class="mpe-stat__label">Tage</span>
                                <strong class="mpe-stat__value">{{ planDays.length || 0 }}</strong>
                            </div>
                            <div class="mpe-stat">
                                <v-icon icon="mdi-silverware" size="15" class="mpe-stat__icon" />
                                <span class="mpe-stat__label">Mit Men&uuml;</span>
                                <strong class="mpe-stat__value">{{ selectedMenuCount }}</strong>
                            </div>
                            <div class="mpe-stat">
                                <v-icon icon="mdi-clock-outline" size="15" class="mpe-stat__icon" />
                                <span class="mpe-stat__label">Offen</span>
                                <strong class="mpe-stat__value">{{ openDayCount }}</strong>
                            </div>
                            <div v-if="freeDayCount > 0" class="mpe-stat mpe-stat--free">
                                <v-icon icon="mdi-calendar-remove-outline" size="15" class="mpe-stat__icon" />
                                <span class="mpe-stat__label">Frei</span>
                                <strong class="mpe-stat__value">{{ freeDayCount }}</strong>
                            </div>
                        </div>

                        <!-- Week Board -->
                        <section v-if="planDays.length" class="mpe-board">
                            <article
                                v-for="day in planDays"
                                :key="day.iso"
                                class="mpe-day"
                                :class="day.isFreeDay ? 'mpe-day--free' : day.menu ? 'mpe-day--filled' : 'mpe-day--empty'"
                                :data-testid="`plan-day-${day.iso}`">

                                <header class="mpe-day__header">
                                    <div>
                                        <div class="mpe-day__weekday">{{ day.weekdayLabel }}</div>
                                        <div class="mpe-day__date">{{ day.dateLabel }}</div>
                                    </div>
                                    <div
                                        class="mpe-day__status"
                                        :class="day.isFreeDay ? 'is-free' : day.menu ? 'is-filled' : 'is-empty'">
                                        <v-icon
                                            :icon="day.isFreeDay ? 'mdi-leaf' : day.menu ? 'mdi-check' : 'mdi-clock-outline'"
                                            size="11"
                                            class="mr-1" />
                                        {{ day.isFreeDay ? 'Frei' : day.menu ? 'Belegt' : 'Offen' }}
                                    </div>
                                </header>

                                <div v-if="day.isFreeDay" class="mpe-free-card" :data-testid="`free-day-${day.iso}`">
                                    <v-icon icon="mdi-calendar-remove-outline" size="36" class="mpe-free-card__icon" />
                                    <p class="mpe-free-card__text">Freier Tag</p>
                                </div>

                                <div v-else-if="day.menu" class="mpe-menu-card" :data-testid="`selected-menu-${day.iso}`">
                                    <div class="mpe-menu-card__icon">
                                        <v-icon icon="mdi-silverware" size="20" />
                                    </div>
                                    <div class="mpe-menu-card__body">
                                        <div class="mpe-menu-card__type">Men&uuml;</div>
                                        <div class="mpe-menu-card__title">{{ day.menu.title }}</div>
                                        <div v-if="day.menu.note" class="mpe-menu-card__note">{{ day.menu.note }}</div>
                                    </div>
                                </div>

                                <div v-else class="mpe-empty-card">
                                    <v-icon icon="mdi-plus-circle-outline" size="36" class="mpe-empty-card__icon" />
                                    <p class="mpe-empty-card__text">Noch kein Men&uuml; f&uuml;r diesen Tag.</p>
                                    <v-btn
                                        size="small"
                                        color="primary"
                                        rounded="xl"
                                        variant="tonal"
                                        prepend-icon="mdi-plus"
                                        :data-testid="`add-menu-${day.iso}`"
                                        @click="assignDummyMenu(day.iso)">
                                        Menu hinzufuegen
                                    </v-btn>
                                </div>
                            </article>
                        </section>

                        <section v-else class="mpe-no-range" data-testid="plan-days-empty">
                            <v-icon icon="mdi-calendar-question" size="44" color="grey-lighten-1" />
                            <strong>Kein g&uuml;ltiger Zeitraum</strong>
                            <span>Diese Ansicht ben&ouml;tigt Start- und Enddatum.</span>
                        </section>
                    </v-sheet>
                </v-col>

            </v-row>
        </v-container>
    </div>
</template>

<script>
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import { mapState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useFreeDayStore } from '@/stores/admin/restaurant/FreeDayStore'

function isValidIsoDate(value) {
    return /^\d{4}-\d{2}-\d{2}$/.test(String(value || ''))
}

export default {
    components: { AdminSectionHero },

    data() {
        return {
            menuSelectionsByDate: {},
            dummyMenus: [
                { title: 'Pasta Napoli', note: 'Dummy-Menue mit Tomatensauce' },
                { title: 'Gemuese Curry', note: 'Dummy-Menue mit Reis' },
                { title: 'Kartoffel Gratin', note: 'Dummy-Menue aus dem Ofen' },
                { title: 'Falafel Teller', note: 'Dummy-Menue mit Joghurt Dip' },
            ],
        }
    },

    created() {
        this.syncSelectionsFromRoute()
        this.loadFreeDays()
    },

    watch: {
        '$route.query.mode': 'syncSelectionsFromRoute',
        '$route.query.plan_id': 'syncSelectionsFromRoute',
        '$route.query.start': 'syncSelectionsFromRoute',
        '$route.query.end': 'syncSelectionsFromRoute',
    },

    computed: {
        ...mapState(useAdminStore, ['config']),
        ...mapState(useFreeDayStore, ['freeDaysByDate']),
        entryMode() {
            const mode = String(this.$route?.query?.mode || '')

            return ['create', 'edit'].includes(mode) ? mode : ''
        },
        entryModeLabel() {
            return this.entryMode === 'edit' ? 'Plan bearbeiten' : 'Neuer Plan'
        },
        entryHeadline() {
            return this.entryMode === 'edit' ? 'Wochenboard' : 'Menues planen'
        },
        planIdentifier() {
            return String(this.$route?.query?.plan_id || 'Dummy')
        },
        rangeBounds() {
            const start = String(this.$route?.query?.start || '')
            const end = String(this.$route?.query?.end || '')

            if (!isValidIsoDate(start) || !isValidIsoDate(end)) {
                return null
            }

            return start <= end ? { start, end } : { start: end, end: start }
        },
        planDays() {
            if (!this.rangeBounds) {
                return []
            }

            const days = []
            let cursor = this.rangeBounds.start

            while (cursor <= this.rangeBounds.end) {
                days.push({
                    iso: cursor,
                    weekdayLabel: this.toDate(cursor).toLocaleDateString('de-AT', { weekday: 'long' }),
                    dateLabel: this.toDate(cursor).toLocaleDateString('de-AT', { day: '2-digit', month: 'long' }),
                    menu: this.menuSelectionsByDate[cursor] || null,
                    isFreeDay: !!this.freeDaysByDate[cursor],
                })
                cursor = this.addDaysIso(cursor, 1)
            }

            return days
        },
        selectedMenuCount() {
            return this.planDays.filter((day) => day.menu !== null && !day.isFreeDay).length
        },
        freeDayCount() {
            return this.planDays.filter((day) => day.isFreeDay).length
        },
        openDayCount() {
            return this.planDays.filter((day) => !day.menu && !day.isFreeDay).length
        },
        coveragePercent() {
            const assignable = this.planDays.filter((day) => !day.isFreeDay).length

            return assignable ? Math.round((this.selectedMenuCount / assignable) * 100) : 0
        },
        firstOpenDay() {
            return this.planDays.find((day) => !day.menu && !day.isFreeDay) || null
        },
        firstOpenDayLabel() {
            return this.firstOpenDay ? this.formatDate(this.firstOpenDay.iso) : 'Alle belegt'
        },
        nextActionTitle() {
            if (!this.planDays.length) {
                return 'Zeitraum fehlt'
            }

            return this.firstOpenDay ? 'Offenen Tag fuellen' : 'Plan pruefen'
        },
        nextActionCopy() {
            if (!this.planDays.length) {
                return 'Bitte zuerst einen gueltigen Zeitraum uebergeben.'
            }

            return this.firstOpenDay
                ? `${this.formatDate(this.firstOpenDay.iso)} als naechstes belegen.`
                : 'Alle Tage sind aktuell mit einem Menue versehen.'
        },
        progressMessage() {
            if (!this.planDays.length) {
                return 'Noch keine Planung verfuegbar.'
            }

            return this.openDayCount === 0
                ? 'Die Woche ist komplett gefuellt.'
                : `${this.openDayCount} Tage sind noch offen.`
        },
        ringStyle() {
            return {
                background: `conic-gradient(#b45309 0 ${this.coveragePercent}%, rgba(180, 83, 9, 0.12) ${this.coveragePercent}% 100%)`,
            }
        },
        returnWeek() {
            const returnWeek = String(this.$route?.query?.return_week || '')

            return isValidIsoDate(returnWeek) ? returnWeek : ''
        },
        backTarget() {
            const path = String(this.$route?.query?.return_to || '/admin/restaurant/menu-plans')
            const query = this.returnWeek ? { week: this.returnWeek } : {}

            return { path, query }
        },
        backButtonLabel() {
            return this.entryMode === 'edit' ? 'Zurueck zum Plan' : 'Zurueck zur Auswahl'
        },
        rangeLabel() {
            if (!this.rangeBounds) {
                return 'Kein Zeitraum'
            }

            return `${this.formatDate(this.rangeBounds.start)} - ${this.formatDate(this.rangeBounds.end)}`
        },
        headerActiveSection() {
            return {
                icon: 'mdi-calendar-text-outline',
                label: 'Men\u00fcpl\u00e4ne',
                note: this.entryMode === 'edit' ? 'Bearbeiten aktiv' : 'Erstellen aktiv',
            }
        },
        headerChips() {
            return [
                {
                    text: this.config?.selected_school?.long_name || this.config?.selected_school?.short_name || 'Schule aktiv',
                    icon: 'mdi-domain',
                    color: 'white',
                },
                {
                    text: this.returnWeek ? `Rueckkehr zur Woche ${this.returnWeek}` : this.rangeLabel,
                    icon: 'mdi-link-variant',
                    color: 'white',
                },
            ]
        },
    },

    methods: {
        syncSelectionsFromRoute() {
            const nextSelections = {}

            if (this.entryMode === 'edit') {
                this.buildSeededSelections().forEach((selection) => {
                    nextSelections[selection.iso] = selection.menu
                })
            }

            this.menuSelectionsByDate = nextSelections
        },
        buildSeededSelections() {
            return this.basePlanDays()
                .filter((day, index) => index % 2 === 0)
                .map((day, index) => ({ iso: day.iso, menu: this.dummyMenus[index % this.dummyMenus.length] }))
        },
        basePlanDays() {
            if (!this.rangeBounds) {
                return []
            }

            const days = []
            let cursor = this.rangeBounds.start

            while (cursor <= this.rangeBounds.end) {
                days.push({ iso: cursor })
                cursor = this.addDaysIso(cursor, 1)
            }

            return days
        },
        loadFreeDays() {
            const store = useFreeDayStore()
            const year = this.rangeBounds ? parseInt(this.rangeBounds.start.substring(0, 4), 10) : new Date().getFullYear()
            store.loadYear(year)
        },
        assignDummyMenu(isoString) {
            if (!isValidIsoDate(isoString) || this.menuSelectionsByDate[isoString]) {
                return
            }

            if (this.freeDaysByDate[isoString]) {
                return
            }

            this.menuSelectionsByDate = {
                ...this.menuSelectionsByDate,
                [isoString]: this.buildDummyMenuForDate(isoString),
            }
        },
        buildDummyMenuForDate(isoString) {
            const dayIndex = this.basePlanDays().findIndex((day) => day.iso === isoString)

            return this.dummyMenus[dayIndex < 0 ? 0 : dayIndex % this.dummyMenus.length]
        },
        toDate(isoString) {
            return new Date(`${isoString}T00:00:00`)
        },
        addDaysIso(isoString, days) {
            const date = this.toDate(isoString)
            date.setDate(date.getDate() + days)

            return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-')
        },
        formatDate(isoString) {
            return this.toDate(isoString).toLocaleDateString('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
    },
}
</script>

<style scoped>
/* ---- Page ---- */

.mpe-page {
    min-height: 100vh;
    background: linear-gradient(160deg, #fafaf8 0%, #f5ede0 100%);
}

/* ---- Stage & Sidebar Panels ---- */

.mpe-stage,
.mpe-sidebar {
    height: 100%;
    border: 1px solid rgba(180, 83, 9, 0.1);
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 8px 32px rgba(15, 23, 42, 0.07);
}

/* ---- Dark Gradient Header ---- */

.mpe-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    padding: 24px;
    border-radius: 20px;
    background: linear-gradient(135deg, #1e293b 0%, #334155 55%, #475569 100%);
    color: #f8fafc;
    margin-bottom: 20px;
}

.mpe-header__eyebrow {
    font-size: 0.71rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(248, 250, 252, 0.52);
    margin-bottom: 6px;
}

.mpe-header__title {
    font-size: clamp(1.65rem, 2.8vw, 2.2rem);
    font-weight: 900;
    line-height: 1.05;
    margin: 0;
}

.mpe-header__range {
    margin-top: 8px;
    font-size: 0.9rem;
    color: rgba(248, 250, 252, 0.6);
}

.mpe-header__right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
    flex-shrink: 0;
}

.mpe-badge {
    min-width: 96px;
    padding: 12px 14px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.14);
    text-align: center;
}

.mpe-badge strong {
    display: block;
    font-size: 1.8rem;
    line-height: 1;
    font-weight: 900;
}

.mpe-badge span {
    font-size: 0.71rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: rgba(248, 250, 252, 0.6);
}

/* ---- Summary Strip ---- */

.mpe-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.mpe-stat {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding: 14px 16px;
    border-radius: 16px;
    background: linear-gradient(160deg, #fffdf5, #fef3c7);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.mpe-stat__icon { color: #b45309; }

.mpe-stat__label {
    font-size: 0.69rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    color: #b45309;
}

.mpe-stat__value {
    font-size: 1.15rem;
    font-weight: 900;
    color: #1f2937;
    line-height: 1;
}

/* ---- Week Board ---- */

.mpe-board {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 14px;
}

/* ---- Day Tile ---- */

.mpe-day {
    display: flex;
    flex-direction: column;
    border-radius: 20px;
    overflow: hidden;
    min-height: 280px;
    border: 1px solid #e5e7eb;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.mpe-day:hover {
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.1);
    transform: translateY(-2px);
}

.mpe-day--filled {
    background: linear-gradient(180deg, #ffffff 0%, #fffcf0 100%);
    border-color: rgba(245, 158, 11, 0.25);
}

.mpe-day--empty {
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border-style: dashed;
    border-color: #d1d5db;
}

.mpe-day--free {
    background: linear-gradient(180deg, #ffffff 0%, #f0fdf4 100%);
    border-color: rgba(34, 197, 94, 0.3);
}

.mpe-day__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 14px 16px 10px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    background: rgba(255, 255, 255, 0.75);
    flex-shrink: 0;
}

.mpe-day__weekday {
    font-size: 1rem;
    font-weight: 800;
    color: #111827;
    text-transform: capitalize;
}

.mpe-day__date {
    font-size: 0.78rem;
    color: #6b7280;
    margin-top: 2px;
}

.mpe-day__status {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
    white-space: nowrap;
}

.mpe-day__status.is-filled {
    background: rgba(245, 158, 11, 0.14);
    color: #92400e;
}

.mpe-day__status.is-empty {
    background: rgba(100, 116, 139, 0.1);
    color: #475569;
}

.mpe-day__status.is-free {
    background: rgba(34, 197, 94, 0.12);
    color: #15803d;
}

/* ---- Menu Card (filled state) ---- */

.mpe-menu-card {
    display: flex;
    gap: 13px;
    align-items: flex-start;
    flex: 1;
    padding: 16px;
    background: linear-gradient(160deg, #fffcf0, #fef3c7);
}

.mpe-menu-card__icon {
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: rgba(245, 158, 11, 0.12);
    display: grid;
    place-items: center;
    color: #b45309;
    flex-shrink: 0;
}

.mpe-menu-card__body { flex: 1; min-width: 0; }

.mpe-menu-card__type {
    font-size: 0.67rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    color: #b45309;
    margin-bottom: 3px;
}

.mpe-menu-card__title {
    font-size: 0.93rem;
    font-weight: 800;
    color: #1f2937;
    line-height: 1.35;
}

.mpe-menu-card__note {
    margin-top: 5px;
    font-size: 0.8rem;
    color: #6b7280;
    line-height: 1.45;
}

/* ---- Free Day Card ---- */

.mpe-free-card {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 20px 16px;
    text-align: center;
    background: linear-gradient(160deg, #f0fdf4, #dcfce7);
}

.mpe-free-card__icon { color: #4ade80; }

.mpe-free-card__text {
    font-size: 0.9rem;
    font-weight: 700;
    color: #15803d;
    letter-spacing: 0.02em;
    margin: 0;
}

/* ---- Free stat variant ---- */

.mpe-stat--free .mpe-stat__icon { color: #15803d; }
.mpe-stat--free .mpe-stat__label { color: #15803d; }
.mpe-stat--free { background: linear-gradient(160deg, #f0fdf4, #dcfce7); border-color: rgba(34, 197, 94, 0.2); }

/* ---- Empty Card (empty state inside tile) ---- */

.mpe-empty-card {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 13px;
    padding: 20px 16px;
    text-align: center;
}

.mpe-empty-card__icon { color: #d1d5db; }

.mpe-empty-card__text {
    font-size: 0.84rem;
    color: #9ca3af;
    line-height: 1.5;
    max-width: 13rem;
    margin: 0;
}

/* ---- No-range Empty State ---- */

.mpe-no-range {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 44px 24px;
    border-radius: 20px;
    background: #f8fafc;
    border: 1px dashed #d1d5db;
    text-align: center;
    color: #374151;
}

.mpe-no-range span {
    font-size: 0.9rem;
    color: #6b7280;
}

/* ---- Responsive ---- */

@media (max-width: 1260px) {
    .mpe-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 960px) {
    .mpe-header { flex-direction: column; }
    .mpe-header__right { width: 100%; flex-direction: row; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
}

@media (max-width: 640px) {
    .mpe-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .mpe-board { grid-template-columns: 1fr; }
}
</style>
