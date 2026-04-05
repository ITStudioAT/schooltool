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

            <v-col cols="12" md="6" xl="3">
                <ItsGridBox
                    variant="overview"
                    color="primary"
                    title="Gebuchte Menüs"
                    icon="mdi-food-takeout-box">
                    <div class="restaurant-overview-stat">{{ stats.booked_menus_count || 0 }}</div>
                    <div class="restaurant-overview-action">
                        <v-btn
                            size="small"
                            color="primary"
                            variant="tonal"
                            prepend-icon="mdi-arrow-right"
                            :loading="navigating === 'menuPlans'"
                            :disabled="navigating !== null"
                            @click="openMenuPlans">
                            Zu Menüplänen
                        </v-btn>
                    </div>
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
        }
    },

    computed: {
        ...mapState(useRestaurantStore, ['stats']),
        pendingConfirmationCount() {
            return Number(this.stats?.lunch_users_pending_confirmation_count || 0)
        },
        pendingConfirmationCardColor() {
            return this.pendingConfirmationCount > 0 ? 'error' : 'primary'
        },
    },

    methods: {
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
        openMenuPlans() {
            this.navigate('menuPlans', '/admin/restaurant/menu-plans')
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
}
</style>
