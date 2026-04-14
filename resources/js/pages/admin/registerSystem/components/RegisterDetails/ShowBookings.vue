<template>
    <v-col cols="12" md="6" xl="4">
        <v-card rounded="xl" class="rd-card" flat>
            <v-card-text class="pa-4">
                <div class="rd-card__header mb-3">
                    <div class="rd-card__icon-wrap">
                        <v-icon size="18" icon="mdi-view-list-outline" />
                    </div>
                    <div class="rd-card__header-title">Anmeldungen</div>
                    <div class="ml-auto d-flex align-center ga-2">
                        <v-btn
                            v-if="selected_bookings.length > 0 && booking_action === ''"
                            icon="mdi-delete-outline"
                            variant="tonal"
                            color="error"
                            size="small"
                            :title="selected_bookings.length === 1 ? 'Anmeldung löschen' : 'Anmeldungen löschen'"
                            @click="remove" />
                        <v-btn icon="mdi-arrow-left" variant="tonal" color="secondary" size="small" title="Zurück" @click="action = ''" />
                    </div>
                </div>

                <div v-for="booking in bookings" :key="booking.id" class="rd-booking-group mb-4">
                    <div class="rd-booking-group__header">
                        <v-icon size="14" class="mr-1">mdi-calendar</v-icon>{{ booking.date }}
                        <v-icon size="14" class="mx-1">mdi-clock-outline</v-icon>{{ booking.from }} – {{ booking.to }}
                        <v-icon size="14" class="mx-1">mdi-account-hard-hat-outline</v-icon>{{ booking.supervisor }}
                    </div>

                    <div v-if="booking.bookings.length === 0" class="rd-booking-empty">Keine Buchungen.</div>

                    <v-list
                        v-if="booking.bookings.length >= 1"
                        variant="flat"
                        select-strategy="leaf"
                        v-model:selected="selected_bookings"
                        color="success"
                        bg-color="transparent"
                        density="compact"
                        :disabled="booking_action !== ''">
                        <v-list-item
                            v-for="item in booking.bookings"
                            :key="item.id"
                            :value="item.id"
                            rounded="lg"
                            class="rd-booking-item mb-1">
                            <template #title>
                                <div class="rd-booking-item__name">{{ item.last_name }} {{ item.first_name }}</div>
                                <div class="rd-booking-item__meta">
                                    <span><v-icon size="12" class="mr-1">mdi-email-outline</v-icon>{{ item.email }}</span>
                                    <span v-if="item.phone"><v-icon size="12" class="mr-1">mdi-phone-outline</v-icon>{{ item.phone }}</span>
                                </div>
                                <div class="rd-booking-item__student">
                                    <v-icon size="12" class="mr-1">mdi-human-child</v-icon>
                                    {{ item.student_last_name }} {{ item.student_first_name }}
                                    <span v-if="item.student_birthdate" class="ml-2">
                                        <v-icon size="11" class="mr-1">mdi-cake-variant-outline</v-icon>{{ item.student_birthdate }}
                                    </span>
                                </div>
                                <div class="text-caption rd-booking-item__note" v-if="item.note">{{ item.note }}</div>
                            </template>
                        </v-list-item>
                    </v-list>
                </div>
            </v-card-text>
        </v-card>

        <!-- Delete bookings dialog -->
        <v-dialog :model-value="booking_action === 'remove_booking'" max-width="420" persistent>
            <v-card rounded="xl" class="rd-dialog-card">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-5">
                    <v-icon color="error" size="20">mdi-delete-outline</v-icon>
                    {{ selected_bookings.length === 1 ? 'Anmeldung löschen' : 'Anmeldungen löschen' }}
                </v-card-title>
                <v-card-text class="px-5">
                    {{ selected_bookings.length > 1 ? 'Sollen die markierten Anmeldungen wirklich gelöscht werden?' : 'Soll die markierte Anmeldung wirklich gelöscht werden?' }}
                    <v-checkbox label="Lösch-Verständigung per E-Mail?" v-model="delete_notify" color="primary" density="compact" class="mt-2" />
                </v-card-text>
                <v-card-actions class="px-5 pb-5">
                    <v-btn variant="tonal" rounded="lg" @click="abortBooking">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn color="error" variant="flat" rounded="lg" @click="deleteBookings(selected_bookings, delete_notify)">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import { useRegisterDateStore } from '@/stores/admin/RegisterDateStore'
import { useRegisterDateBookingStore } from '@/stores/admin/RegisterDateBookingStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.registerStore = useRegisterStore()
        this.registerDateStore = useRegisterDateStore()
        this.registerDateBookingStore = useRegisterDateBookingStore()
        await this.loadBookings(this.selected_register_dates)
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerStore: null,
            registerDateStore: null,
            registerDateBookingStore: null,
            is_valid: false,
            search_string: '',
            booking_action: '',
            delete_notify: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'selected_school', 'selected_schoolyear', 'selected_register', 'selected_active_register', 'action']),
        ...mapWritableState(useRegisterStore, []),
        ...mapWritableState(useRegisterDateStore, ['register_dates', 'selected_register_dates', 'days', 'selected_day']),
        ...mapWritableState(useRegisterDateBookingStore, ['bookings', 'selected_bookings']),
    },

    watch: {},

    methods: {
        abortBooking() {
            this.booking_action = ''
        },
        async loadBookings(dates) {
            await this.registerDateBookingStore.loadBookings(dates)
        },
        async deleteBookings(bookings, notify) {
            if (!(await this.registerDateBookingStore.deleteBookings(bookings, notify))) return
            const date = this.registerDateStore?.register_dates[0].date
            if (date) {
                await this.registerDateStore.loadRegisterDates(date)
            }
            await this.adminStore.loadConfig()
            await this.loadBookings(this.selected_register_dates)
            this.booking_action = ''
        },
        remove() {
            this.delete_notify = false
            this.booking_action = 'remove_booking'
        },
        abort() {
            this.action = ''
        },
    },
}
</script>

<style scoped>
.rd-card {
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92)) !important;
    backdrop-filter: blur(4px);
    color: #10263a !important;
}

.rd-card__header { display: flex; align-items: center; gap: 10px; }

.rd-card__icon-wrap {
    display: flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 8px;
    background: rgba(99, 102, 241, 0.18); color: #818cf8; flex-shrink: 0;
}

.rd-card__header-title { font-size: 0.95rem; font-weight: 700; color: #10263a; }

.rd-booking-group__header {
    display: flex; align-items: center; flex-wrap: wrap;
    font-size: 0.78rem; color: rgba(16, 38, 58, 0.62);
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px; padding: 6px 10px; margin-bottom: 4px;
}

.rd-booking-empty {
    font-size: 0.82rem; color: #475569; padding: 4px 8px;
}

.rd-booking-item { border-radius: 8px !important; }

.rd-booking-item__name { font-size: 0.88rem; font-weight: 600; color: #10263a; }

.rd-booking-item__meta {
    display: flex; flex-wrap: wrap; gap: 8px;
    font-size: 0.76rem; color: rgba(16, 38, 58, 0.62); margin-top: 2px;
}

.rd-booking-item__student {
    display: flex; align-items: center; flex-wrap: wrap;
    font-size: 0.8rem; color: rgba(16, 38, 58, 0.68); margin-top: 2px;
}

.rd-booking-item__note { color: rgba(16, 38, 58, 0.62); margin-top: 2px; }

:deep(.v-list-item) {
    background: rgba(255, 255, 255, 0.82) !important;
    border: 1px solid rgba(16, 38, 58, 0.08);
}

:deep(.v-list-item--active) {
    background: rgba(34, 197, 94, 0.12) !important;
    border-color: rgba(34, 197, 94, 0.25) !important;
}

:deep(.v-list-item__content),
:deep(.v-list-item-title) {
    color: #10263a !important;
}

.rd-dialog-card {
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(241, 245, 249, 0.96)) !important;
    color: #10263a !important;
}
</style>
