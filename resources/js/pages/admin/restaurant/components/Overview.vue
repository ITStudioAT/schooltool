the<template>
    <v-col cols="12">
        <v-row dense>
            <v-col cols="12" md="6" xl="3">
                <ItsGridBox variant="overview" color="primary" title="Speisen" icon="mdi-silverware-variant">
                    <div class="restaurant-overview-stat">{{ stats.foods_count || 0 }}</div>
                    <div class="restaurant-overview-action">
                        <v-btn
                            size="small"
                            color="primary"
                            variant="tonal"
                            prepend-icon="mdi-arrow-right"
                            :loading="navigating === 'foods'"
                            :disabled="navigating !== null"
                            @click="openFoods">
                            Zu den Speisen
                        </v-btn>
                    </div>
                </ItsGridBox>
            </v-col>

            <v-col cols="12" md="6" xl="3">
                <ItsGridBox variant="overview" color="primary" title="Men&uuml;s" icon="mdi-food-takeout-box-outline">
                    <div class="restaurant-overview-stat">{{ stats.menus_count || 0 }}</div>
                    <div class="restaurant-overview-action">
                        <v-btn
                            size="small"
                            color="primary"
                            variant="tonal"
                            prepend-icon="mdi-arrow-right"
                            :loading="navigating === 'menus'"
                            :disabled="navigating !== null"
                            @click="openMenus">
                            Zu den Menüs
                        </v-btn>
                    </div>
                </ItsGridBox>
            </v-col>

            <v-col cols="12" md="6" xl="3">
                <ItsGridBox variant="overview" color="primary" title="Benutzer" icon="mdi-account-multiple-outline">
                    <div class="restaurant-overview-stat">{{ stats.lunch_users_count || 0 }}</div>
                    <div class="restaurant-overview-action">
                        <v-btn
                            size="small"
                            color="primary"
                            variant="tonal"
                            prepend-icon="mdi-arrow-right"
                            :loading="navigating === 'users'"
                            :disabled="navigating !== null"
                            @click="openUsers">
                            Zu Benutzern
                        </v-btn>
                    </div>
                </ItsGridBox>
            </v-col>

            <v-col cols="12" md="6" xl="3">
                <ItsGridBox
                    variant="overview"
                    :color="pendingConfirmationCardColor"
                    title="Benutzer zu bestätigen"
                    icon="mdi-account-clock-outline">
                    <div class="restaurant-overview-stat">{{ stats.lunch_users_pending_confirmation_count || 0 }}</div>
                    <div class="restaurant-overview-action">
                        <v-btn
                            size="small"
                            :color="pendingConfirmationCount > 0 ? 'error' : 'primary'"
                            variant="tonal"
                            prepend-icon="mdi-filter-check-outline"
                            :loading="navigating === 'pending'"
                            :disabled="navigating !== null"
                            @click="openPendingConfirmationUsers">
                            Nur zu bestätigen
                        </v-btn>
                    </div>
                </ItsGridBox>
            </v-col>

            <v-col cols="12">
                <ItsGridBox
                    variant="overview"
                    color="primary"
                    title="Menüpläne"
                    icon="mdi-calendar-week">
                    <div v-if="menuPlanWeeks.length" class="restaurant-overview-weeks">
                        <div v-for="week in menuPlanWeeks" :key="week.week_start" class="restaurant-overview-week">
                            <div class="restaurant-overview-week-label">
                                <div class="font-weight-bold">KW {{ week.calendar_week }}/{{ week.week_year }}</div>
                                <div class="text-body-2">{{ formatDate(week.start_date) }} – {{ formatDate(week.end_date) }}</div>
                            </div>
                            <div class="restaurant-overview-week-count">
                                <strong>{{ week.bookings_count }}</strong>
                                <span>{{ week.bookings_count === 1 ? 'Buchung' : 'Buchungen' }}</span>
                            </div>
                            <v-btn
                                size="small"
                                color="primary"
                                variant="tonal"
                                class="restaurant-overview-week-button"
                                :loading="navigating === week.week_start"
                                :disabled="navigating !== null"
                                @click="openBookings(week)">
                                Buchungen
                            </v-btn>
                            <div v-for="plan in week.plans || []" :key="plan.id" class="restaurant-overview-plan-detail">
                                <div>
                                    <div class="font-weight-medium">{{ plan.title || 'Menüplan' }} · {{ bookingStatus(plan) }}</div>
                                    <div class="text-caption">{{ formatDate(plan.start_date) }} – {{ formatDate(plan.end_date) }}</div>
                                    <div v-if="!plan.is_available" class="text-caption">Freigabe erforderlich</div>
                                    <template v-else>
                                        <div v-if="plan.order_start_at" class="text-caption">Öffnung: {{ formatDateTime(plan.order_start_at, plan.timezone) }}</div>
                                        <div class="text-caption">Bestellschluss: {{ formatDateTime(plan.order_end_at, plan.timezone) }}</div>
                                        <div v-if="bookingStatus(plan) === 'Öffnet später'" class="text-caption font-weight-bold">Öffnet in {{ openingCountdown(plan) }}</div>
                                    </template>
                                </div>
                                <v-btn size="small" variant="tonal" color="primary"
                                    :to="{ path: '/admin/menu-plans', query: { mode: 'edit', plan_id: plan.id, return_to: '/admin/restaurant' } }">Zum Menüplan</v-btn>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-body-2">Keine Menüpläne für die aktuelle Woche oder kommende Wochen vorhanden.</div>
                </ItsGridBox>
            </v-col>
        </v-row>
    </v-col>
</template>

<script>
import { mapState } from 'pinia'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'

export default {
    components: { ItsGridBox },

    data() {
        return {
            navigating: null,
            nowMillis: Date.now(),
            countdownTimer: null,
        }
    },

    computed: {
        ...mapState(useRestaurantStore, ['stats']),
        menuPlanWeeks() {
            return this.stats?.menu_plan_weeks || []
        },
        pendingConfirmationCount() {
            return Number(this.stats?.lunch_users_pending_confirmation_count || 0)
        },
        pendingConfirmationCardColor() {
            return this.pendingConfirmationCount > 0 ? 'error' : 'primary'
        },
    },

    mounted() {
        this.countdownTimer = setInterval(() => { this.nowMillis = Date.now() }, 1000)
    },

    beforeUnmount() {
        clearInterval(this.countdownTimer)
    },

    methods: {
        bookingStatus(plan) {
            if (!plan.is_available) return 'Nicht freigegeben'
            if (this.nowMillis > Date.parse(plan.order_end_at)) return 'Buchung geschlossen'
            if (plan.order_start_at && this.nowMillis < Date.parse(plan.order_start_at)) return 'Öffnet später'
            return 'Buchung offen'
        },
        formatDateTime(value, timezone) {
            return new Date(value).toLocaleString('de-AT', {
                timeZone: timezone, day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
            })
        },
        openingCountdown(plan) {
            const seconds = Math.max(0, Math.ceil((Date.parse(plan.order_start_at) - this.nowMillis) / 1000))
            const days = Math.floor(seconds / 86400)
            const hours = Math.floor((seconds % 86400) / 3600)
            const minutes = Math.floor((seconds % 3600) / 60)
            return `${days} T ${hours} Std ${minutes} Min ${seconds % 60} Sek`
        },
        formatDate(date) {
            return new Date(`${date}T00:00:00`).toLocaleDateString('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            })
        },
        navigate(key, to) {
            this.navigating = key
            this.$router.push(to)
        },
        openFoods() {
            this.navigate('foods', '/admin/restaurant/foods')
        },
        openMenus() {
            this.navigate('menus', '/admin/restaurant/menus')
        },
        openUsers() {
            this.navigate('users', '/admin/restaurant/users')
        },
        openPendingConfirmationUsers() {
            this.navigate('pending', {
                path: '/admin/restaurant/users',
                query: {
                    only_pending_confirmation: '1',
                },
            })
        },
        openBookings(week) {
            this.navigate(week.week_start, {
                path: '/admin/restaurant/bookings',
                query: { week_start: week.week_start },
            })
        },
    },
}
</script>

<style scoped>
.restaurant-overview-stat {
    font-size: clamp(2rem, 4vw, 2.8rem);
    font-weight: 800;
    line-height: 1;
    color: #0f172a;
}

.restaurant-overview-action {
    margin-top: 0.9rem;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.restaurant-overview-weeks {
    display: grid;
    gap: 12px;
}

.restaurant-overview-week {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 140px 112px;
    align-items: center;
    gap: 12px 24px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}

.restaurant-overview-week:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}

.restaurant-overview-week-count {
    display: flex;
    align-items: baseline;
    gap: 5px;
    white-space: nowrap;
    font-size: 0.8rem;
}

.restaurant-overview-week-count strong {
    font-size: 1.25rem;
    line-height: 1.2;
}

.restaurant-overview-week-button {
    justify-self: end;
}

.restaurant-overview-week-label {
    min-width: 0;
}

.restaurant-overview-plan-detail {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    padding: 8px 0 8px 12px;
    border-left: 2px solid rgba(var(--v-theme-primary), 0.2);
}

@media (max-width: 599px) {
    .restaurant-overview-week {
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 8px 12px;
    }

    .restaurant-overview-week-count {
        grid-column: 1;
    }

    .restaurant-overview-week-button {
        grid-column: 2;
        grid-row: 1 / span 2;
    }
}
</style>
