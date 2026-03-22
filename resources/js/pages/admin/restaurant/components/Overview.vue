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
                <ItsGridBox variant="overview" color="primary" title="Kategorien" icon="mdi-shape-outline">
                    <div class="restaurant-overview-stat">{{ stats.categories_count || 0 }}</div>
                    <div class="restaurant-overview-copy">Vorspeise, Hauptspeise, Nachspeise und Erweiterungen</div>
                </ItsGridBox>
            </v-col>

            <v-col cols="12" md="6" xl="3">
                <ItsGridBox variant="overview" color="primary" title="Zutaten-Symbole" icon="mdi-image-outline">
                    <div class="restaurant-overview-stat">{{ stats.ingredient_icons_count || 0 }}</div>
                    <div class="restaurant-overview-copy">Kleine Bilder für Schwein, Rind, Fisch und mehr</div>
                </ItsGridBox>
            </v-col>

            <v-col cols="12" md="6" xl="3">
                <ItsGridBox variant="overview" color="primary" title="Menüs" icon="mdi-food-takeout-box-outline">
                    <div class="restaurant-overview-stat">{{ stats.menus_count || 0 }}</div>
                    <div class="restaurant-overview-copy">Zusammengestellte Menüfolgen mit mehreren Gängen</div>
                </ItsGridBox>
            </v-col>

            <v-col cols="12" md="6" xl="3">
                <ItsGridBox variant="overview" color="primary" title="Ohne Preis" icon="mdi-cash-remove">
                    <div class="restaurant-overview-stat">{{ stats.foods_without_price_count || 0 }}</div>
                    <div class="restaurant-overview-copy">Speisen, die noch keinen Preis eingetragen haben</div>
                </ItsGridBox>
            </v-col>

            <v-col cols="12" lg="7">
                <ItsGridBox variant="overview" color="primary" title="Kategorien schnell erweitern" icon="mdi-shape-plus-outline">
                    <v-alert type="info" variant="tonal" class="mb-3">
                        Neue Kategorien können direkt beim Anlegen einer Speise eingetippt oder unter Einstellungen sauber verwaltet werden.
                    </v-alert>

                    <div class="d-flex flex-wrap ga-2">
                        <v-chip
                            v-for="category in categories"
                            :key="category.id"
                            color="primary"
                            variant="tonal">
                            {{ category.title }}
                        </v-chip>
                    </div>
                </ItsGridBox>
            </v-col>

            <v-col cols="12" lg="5">
                <ItsGridBox variant="overview" color="primary" title="Allergene im Umlauf" icon="mdi-alert-circle-outline">
                    <v-alert v-if="!allergenSuggestions.length" type="warning" variant="tonal">
                        Noch keine Allergene hinterlegt. Diese erscheinen automatisch, sobald Speisen gepflegt werden.
                    </v-alert>

                    <div v-else class="d-flex flex-wrap ga-2">
                        <v-chip
                            v-for="allergen in allergenSuggestions"
                            :key="allergen"
                            color="secondary"
                            variant="tonal">
                            {{ allergen }}
                        </v-chip>
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

    computed: {
        ...mapState(useRestaurantStore, ['categories', 'allergenSuggestions', 'stats']),
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
