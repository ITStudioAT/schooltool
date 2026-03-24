<template>
    <v-col cols="12">
        <v-row dense>
            <v-col cols="12" md="6" xl="3">
                <ItsGridBox variant="overview" color="primary" title="Speisen" icon="mdi-silverware-variant">
                    <div class="restaurant-overview-stat">{{ stats.foods_count || 0 }}</div>
                    <div class="restaurant-overview-copy">Gerichte im aktuellen Restaurant-Bereich</div>
                </ItsGridBox>
            </v-col>

            <v-col cols="12" md="6" xl="3">
                <ItsGridBox variant="overview" color="primary" title="Men&uuml;s" icon="mdi-food-takeout-box-outline">
                    <div class="restaurant-overview-stat">{{ stats.menus_count || 0 }}</div>
                    <div class="restaurant-overview-copy">Zusammengestellte Men&uuml;folgen mit mehreren G&auml;ngen</div>
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

    computed: {
        ...mapState(useRestaurantStore, ['stats']),
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

.restaurant-overview-copy {
    margin-top: 10px;
    color: rgba(15, 23, 42, 0.72);
    line-height: 1.45;
}
</style>
