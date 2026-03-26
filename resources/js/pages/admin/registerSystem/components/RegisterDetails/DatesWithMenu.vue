<template>
    <!-- TERMINE -->
    <v-col cols="12" md="6" xl="4">
        <v-card rounded="xl" class="rd-card" flat>
            <v-card-text class="pa-4">
                <div class="rd-card__header mb-3">
                    <div class="rd-card__icon-wrap">
                        <v-icon size="18" icon="mdi-calendar-clock-outline" />
                    </div>
                    <div class="rd-card__header-title">Termine</div>
                    <v-btn
                        class="ml-auto"
                        icon="mdi-refresh"
                        variant="tonal"
                        color="secondary"
                        size="small"
                        title="Aktualisieren"
                        @click="refresh" />
                </div>

                <!-- Day pills -->
                <div v-if="days.length > 0" class="rd-day-pills mb-3" :class="{ 'is-locked': action !== '' }">
                    <v-btn
                        v-for="day in days"
                        :key="day.id"
                        size="small"
                        rounded="xl"
                        :variant="day === selected_day ? 'flat' : 'tonal'"
                        :class="day === selected_day ? 'rd-day-pill--active' : 'rd-day-pill--idle'"
                        class="rd-day-pill"
                        @click="selectDay(day)">
                        <div class="rd-day-pill__inner">
                            <span class="rd-day-pill__date">{{ day.date }}</span>
                            <span class="rd-day-pill__sub">{{ day.day }} ({{ day.bookings_count }})</span>
                        </div>
                    </v-btn>
                </div>

                <div v-if="days.length === 0" class="rd-empty mb-3">
                    <v-icon size="16" class="mr-1">mdi-information-outline</v-icon>
                    Keine Termine vorhanden.
                </div>

                <!-- Search -->
                <v-form ref="form" v-model="is_valid" @submit.prevent="search(search_string)" class="mb-2">
                    <div class="d-flex align-center ga-2">
                        <v-text-field
                            clearable
                            variant="outlined"
                            density="compact"
                            rounded="lg"
                            v-model="search_string"
                            label="Suche"
                            :rules="[maxLength(255)]"
                            hide-details
                            @click:clear="refresh"
                            class="flex-1-1" />
                        <v-btn icon="mdi-magnify" variant="tonal" color="primary" size="small" @click="search(search_string)" />
                    </div>
                </v-form>

                <!-- Select all / none -->
                <div class="d-flex align-center ga-2 mb-2" :class="{ 'rd-locked': action !== '' }">
                    <v-btn size="x-small" variant="tonal" color="secondary" rounded="lg" @click="selectAllRegisterDates">
                        Alle ({{ register_dates.length - selected_register_dates.length }})
                    </v-btn>
                    <v-btn size="x-small" variant="tonal" color="secondary" rounded="lg" @click="selectNoRegisterDates">
                        Abwählen ({{ selected_register_dates.length }})
                    </v-btn>
                </div>

                <!-- Dates list -->
                <div class="rd-date-list" :class="{ 'rd-locked': action !== '' }">
                    <v-list
                        variant="flat"
                        select-strategy="leaf"
                        v-model:selected="selected_register_dates"
                        color="success"
                        bg-color="transparent"
                        density="compact">
                        <v-list-item
                            v-for="register_date in register_dates"
                            :key="register_date.id"
                            :value="register_date.id"
                            rounded="lg"
                            class="rd-date-item mb-1">
                            <template #title>
                                <div class="d-flex align-center justify-space-between" :class="registerDateClass(register_date)">
                                    <div class="d-flex align-center gap-2">
                                        <v-icon v-if="register_date.is_locked" icon="mdi-lock" size="14" class="mr-1" />
                                        <span class="rd-date-item__time">
                                            {{ register_date.from }} – {{ register_date.to }}
                                            [{{ register_date.count_bookings }}/{{ register_date.max_registrations }}]
                                        </span>
                                    </div>
                                    <span class="rd-date-item__supervisor">{{ register_date.supervisor }}</span>
                                </div>
                                <div class="text-caption rd-date-item__datestr" v-if="search_string">{{ register_date.date }}</div>
                            </template>
                        </v-list-item>
                    </v-list>
                </div>
            </v-card-text>
        </v-card>
    </v-col>

    <!-- MENÜ -->
    <v-col cols="12" md="6" xl="4" v-if="action === ''">
        <v-card rounded="xl" class="rd-card" flat>
            <v-card-text class="pa-4">
                <div class="rd-card__header mb-4">
                    <div class="rd-card__icon-wrap">
                        <v-icon size="18" icon="mdi-gesture-tap-button" />
                    </div>
                    <div class="rd-card__header-title">Aktionen</div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <v-btn
                        v-if="selected_register_dates.length === 1"
                        rounded="lg"
                        variant="tonal"
                        color="primary"
                        prepend-icon="mdi-account-plus"
                        @click="addPerson(selected_register_dates[0])">
                        Person anmelden
                    </v-btn>

                    <v-btn
                        v-if="selected_register_dates.length >= 1"
                        rounded="lg"
                        variant="tonal"
                        color="primary"
                        prepend-icon="mdi-view-list"
                        @click="showBookings()">
                        Anmeldungen anzeigen
                    </v-btn>

                    <v-btn
                        rounded="lg"
                        variant="tonal"
                        color="secondary"
                        prepend-icon="mdi-cloud-print-outline"
                        @click="action = 'print'">
                        Drucken
                    </v-btn>

                    <v-btn
                        rounded="lg"
                        variant="tonal"
                        color="success"
                        prepend-icon="mdi-calendar-plus"
                        @click="addDates">
                        Termine anlegen
                    </v-btn>

                    <template v-if="selected_register_dates.length >= 1">
                        <v-btn
                            v-if="!selectedDatesAreLocked"
                            rounded="lg"
                            variant="tonal"
                            color="warning"
                            prepend-icon="mdi-lock"
                            @click="lockDates(selected_register_dates)">
                            {{ selected_register_dates.length === 1 ? 'Termin' : 'Termine' }} sperren
                        </v-btn>

                        <v-btn
                            v-if="selectedDatesAreLocked"
                            rounded="lg"
                            variant="tonal"
                            color="success"
                            prepend-icon="mdi-lock-open"
                            @click="unlockDates(selected_register_dates)">
                            {{ selected_register_dates.length === 1 ? 'Termin' : 'Termine' }} entsperren
                        </v-btn>

                        <v-btn
                            rounded="lg"
                            variant="tonal"
                            color="error"
                            prepend-icon="mdi-delete-outline"
                            @click="deleteDates">
                            {{ selected_register_dates.length === 1 ? 'Termin' : 'Termine' }} löschen
                        </v-btn>
                    </template>
                </div>
            </v-card-text>
        </v-card>
    </v-col>

    <!-- PRINT MENÜ -->
    <v-col cols="12" md="6" xl="4" v-if="action === 'print'">
        <v-card rounded="xl" class="rd-card" flat>
            <v-card-text class="pa-4">
                <div class="rd-card__header mb-4">
                    <div class="rd-card__icon-wrap">
                        <v-icon size="18" icon="mdi-printer-outline" />
                    </div>
                    <div class="rd-card__header-title">Export & Druck</div>
                    <v-btn class="ml-auto" icon="mdi-arrow-left" variant="tonal" color="secondary" size="small" @click="action = ''" />
                </div>

                <div v-if="subaction === ''" class="d-flex flex-wrap gap-2">
                    <v-btn rounded="lg" variant="tonal" color="success" prepend-icon="mdi-microsoft-excel" @click="printExcel(selected_register.id)">
                        Excel
                    </v-btn>
                    <v-btn rounded="lg" variant="tonal" color="error" prepend-icon="mdi-file-pdf-box" @click="printSupervisor(selected_register.id)">
                        PDF Betreuer
                    </v-btn>
                    <v-btn rounded="lg" variant="tonal" color="error" prepend-icon="mdi-file-pdf-box" @click="printDate(selected_register.id)">
                        PDF Tag
                    </v-btn>
                </div>

                <v-alert v-if="subaction === 'excel'" type="info" variant="tonal" rounded="lg" density="compact" class="mb-3">
                    Der Auftrag wurde erteilt. Sie erhalten das Excel-Ergebnis per E-Mail.
                </v-alert>
                <v-alert v-if="subaction === 'supervisor'" type="info" variant="tonal" rounded="lg" density="compact" class="mb-3">
                    Der Auftrag (nach Betreuer) wurde erteilt. Sie erhalten das PDF per E-Mail.
                </v-alert>
                <v-alert v-if="subaction === 'date'" type="info" variant="tonal" rounded="lg" density="compact" class="mb-3">
                    Der Auftrag (nach Tag) wurde erteilt. Sie erhalten das PDF per E-Mail.
                </v-alert>
                <v-btn v-if="subaction !== ''" rounded="lg" variant="tonal" color="primary" @click="subaction = ''">Weiter</v-btn>
            </v-card-text>
        </v-card>
    </v-col>

    <!-- DELETE DATES DIALOG -->
    <v-dialog :model-value="action === 'delete_dates'" max-width="440" persistent>
        <v-card rounded="xl" class="rd-dialog-card">
            <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-5">
                <v-icon color="error" size="20">mdi-delete-outline</v-icon>
                {{ selected_register_dates.length === 1 ? 'Termin löschen' : 'Termine löschen' }}
            </v-card-title>
            <v-card-text class="px-5">
                <template v-if="countRegistrations(selected_register_dates) > 0">
                    <div class="rd-dialog-text">
                        {{ selected_register_dates.length > 1 ? 'Die Termine können nicht gelöscht werden' : 'Der Termin kann nicht gelöscht werden' }},
                        da
                        {{ countRegistrations(selected_register_dates) === 1 ? 'eine Buchung' : countRegistrations(selected_register_dates) + ' Buchungen' }}
                        {{ selected_register_dates.length > 1 ? 'beinhalten.' : 'beinhaltet.' }}
                    </div>
                </template>
                <template v-else>
                    <div class="rd-dialog-text">
                        {{ selected_register_dates.length > 1 ? 'Sollen die markierten Termine wirklich gelöscht werden?' : 'Soll der markierte Termin wirklich gelöscht werden?' }}
                    </div>
                </template>
            </v-card-text>
            <v-card-actions class="px-5 pb-5">
                <v-btn variant="tonal" rounded="lg" @click="abortDelete" :disabled="is_deleting">Abbrechen</v-btn>
                <v-spacer />
                <v-btn
                    v-if="countRegistrations(selected_register_dates) === 0"
                    color="error"
                    variant="flat"
                    rounded="lg"
                    :loading="is_deleting"
                    @click="doDeleteDates(selected_register_dates)">
                    Löschen
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import { useRegisterPrintStore } from '@/stores/admin/RegisterPrintStore'
import { useRegisterDateStore } from '@/stores/admin/RegisterDateStore'
import { useRegisterDateBookingStore } from '@/stores/admin/RegisterDateBookingStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.registerStore = useRegisterStore()
        this.registerPrintStore = useRegisterPrintStore()
        this.registerDateStore = useRegisterDateStore()
        this.registerDateBookingStore = useRegisterDateBookingStore()
        this.register_dates = []
        await this.loadDays()
        this.selected_day = null
        if (this.days.length > 0) this.selected_day = this.days[0]
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerStore: null,
            registerPrintStore: null,
            is_valid: false,
            search_string: '',
            subaction: '',
            is_deleting: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'selected_school', 'selected_schoolyear', 'selected_register', 'selected_active_register', 'action']),
        ...mapWritableState(useRegisterStore, []),
        ...mapWritableState(useRegisterDateStore, ['days', 'selected_day', 'register_dates', 'data', 'selected_register_dates']),
        ...mapWritableState(useRegisterDateBookingStore, ['person']),

        selectedDatesAreLocked() {
            return this.selected_register_dates.some((id) =>
                this.register_dates.find((d) => d.id === id)?.is_locked
            )
        },
    },

    watch: {
        async selected_day() {
            if (this.selected_day == null) return
            this.selected_register_dates = []
            this.search_string = ''
            if (this.selected_day) await this.loadRegisterDates(this.selected_day.date)
        },
    },

    methods: {
        async printExcel(register_id) {
            await this.registerPrintStore.printExcel(register_id)
            this.subaction = 'excel'
        },
        async printSupervisor(register_id) {
            await this.registerPrintStore.printSupervisor(register_id)
            this.subaction = 'supervisor'
        },
        async printDate(register_id) {
            await this.registerPrintStore.printDate(register_id)
            this.subaction = 'date'
        },
        countRegistrations(register_dates) {
            return this.register_dates.filter((date) => register_dates.includes(date.id)).reduce((sum, date) => sum + date.count_bookings, 0)
        },
        abortDelete() {
            this.action = ''
        },
        async lockDates(register_dates) {
            if (!(await this.registerDateStore.lockRegisterDates(register_dates))) return
            this.register_dates = this.register_dates.map((date) => {
                if (register_dates.includes(date.id)) {
                    return { ...date, is_locked: true }
                }
                return date
            })
        },
        async unlockDates(register_dates) {
            if (!(await this.registerDateStore.unlockRegisterDates(register_dates))) return
            this.register_dates = this.register_dates.map((date) => {
                if (register_dates.includes(date.id)) {
                    return { ...date, is_locked: false }
                }
                return date
            })
        },
        async search(search_string) {
            if (!search_string || search_string === '') {
                await this.loadRegisterDates(this.selected_day.date)
                return
            }
            this.selected_day = null
            await this.registerDateStore.filterRegisterDates(search_string)
        },
        registerDateClass(register_date) {
            if (register_date.count_bookings > register_date.max_registrations) return 'text-error'
            if (register_date.count_bookings === register_date.max_registrations) return 'text-warning'
            if (register_date.count_bookings > 0) return 'text-success'
            return ''
        },
        selectAllRegisterDates() {
            this.selected_register_dates = this.register_dates.map((item) => item.id)
        },
        selectNoRegisterDates() {
            this.selected_register_dates = []
        },
        showBookings() {
            this.action = 'show_bookings'
        },
        deleteDates() {
            this.action = 'delete_dates'
        },
        async doDeleteDates(register_dates) {
            const old_id = this.selected_day.id
            if (!(await this.registerDateStore.deleteRegisterDates(register_dates))) return
            this.is_deleting = true
            await this.adminStore.loadConfig()
            await this.registerDateStore.loadDays()
            const day = this.days.find((day) => day.id === old_id)
            this.selected_day = day || null
            this.refresh()
            this.is_deleting = false
            this.action = ''
        },
        async refresh() {
            this.search_string = ''
            if (this.days.length === 0) {
                this.register_dates = []
                return
            }
            if (!this.selected_day && this.days.length >= 1) this.selected_day = this.days[0]
            if (this.selected_day) {
                await this.loadRegisterDates(this.selected_day.date)
            } else {
                this.register_dates = []
            }
            this.selected_register_dates = []
        },
        selectDay(day) {
            this.selected_day = day
        },
        async loadDays() {
            this.register_dates = []
            this.search_string = ''
            await this.registerDateStore.loadDays()
        },
        async loadRegisterDates(date) {
            await this.registerDateStore.loadRegisterDates(date)
        },
        addPerson(register_date) {
            this.person = { register_date_id: register_date.id }
            this.action = 'add_person'
        },
        addDates() {
            this.data = {
                date_from: this.selected_day ? this.selected_day.date : new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10),
                time_from: '08:00',
                time_until: '12:00',
                monday: true,
                tuesday: true,
                wednesday: true,
                thursday: true,
                friday: true,
                saturday: false,
                sunday: false,
                min_per_date: 1,
                pause: 0,
                max_registrations: 1,
            }
            this.action = 'add_dates'
        },
    },
}
</script>

<style scoped>
.rd-card {
    border: 1px solid rgba(148, 163, 184, 0.14);
    background: rgba(30, 41, 59, 0.82) !important;
    backdrop-filter: blur(4px);
    color: #e2e8f0 !important;
}

.rd-card__header {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rd-card__icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: rgba(99, 102, 241, 0.18);
    color: #818cf8;
    flex-shrink: 0;
}

.rd-card__header-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #f1f5f9;
}

.rd-empty {
    display: flex;
    align-items: center;
    font-size: 0.84rem;
    color: #64748b;
}

.rd-day-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.rd-day-pills.is-locked {
    opacity: 0.6;
    pointer-events: none;
}

.rd-day-pill {
    height: 40px !important;
    text-transform: none;
    letter-spacing: 0;
    padding: 0 10px;
}

.rd-day-pill__inner {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.rd-day-pill__date {
    font-weight: 650;
    font-size: 0.82rem;
}

.rd-day-pill__sub {
    font-size: 0.68rem;
    opacity: 0.8;
}

.rd-day-pill--active {
    background: linear-gradient(135deg, #15803d, #16a34a) !important;
    color: #fff !important;
    box-shadow: 0 0 10px rgba(34, 197, 94, 0.35) !important;
}

.rd-day-pill--idle {
    background: rgba(34, 197, 94, 0.12) !important;
    color: #4ade80 !important;
    border: 1px solid rgba(34, 197, 94, 0.2) !important;
}

.rd-date-list {
    max-height: 340px;
    overflow-y: auto;
}

.rd-locked {
    opacity: 0.6;
    pointer-events: none;
}

.rd-date-item {
    border: 1px solid transparent;
    border-radius: 8px !important;
}

.rd-date-item__time {
    font-size: 0.86rem;
    font-weight: 600;
    color: #e2e8f0;
}

.rd-date-item__supervisor {
    font-size: 0.78rem;
    color: #64748b;
}

.rd-date-item__datestr {
    color: #64748b;
    font-size: 0.72rem;
}

.rd-date-list :deep(.v-list-item) {
    background: rgba(15, 23, 42, 0.4) !important;
    border: 1px solid rgba(148, 163, 184, 0.1);
}

.rd-date-list :deep(.v-list-item--active) {
    background: rgba(34, 197, 94, 0.12) !important;
    border-color: rgba(34, 197, 94, 0.25) !important;
}

.rd-date-list :deep(.v-list-item__content),
.rd-date-list :deep(.v-list-item-title) {
    color: #e2e8f0 !important;
}

.gap-2 {
    gap: 8px;
}

.rd-dialog-card {
    border: 1px solid rgba(148, 163, 184, 0.14);
    background: #1e293b !important;
    color: #e2e8f0 !important;
}

.rd-dialog-text {
    font-size: 0.92rem;
    color: #cbd5e1;
    line-height: 1.5;
}
</style>
