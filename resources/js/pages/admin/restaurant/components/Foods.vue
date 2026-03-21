<template>
    <v-col cols="12">
        <ItsGridBox variant="overview" color="primary" title="Speisen" icon="mdi-silverware-variant">
            <template #header-actions>
                <div class="d-flex ga-2">
                    <v-btn
                        size="small"
                        color="secondary"
                        :variant="isCompactView ? 'flat' : 'tonal'"
                        :prepend-icon="isCompactView ? 'mdi-view-agenda-outline' : 'mdi-view-grid'"
                        @click="toggleCardView">
                        {{ isCompactView ? 'Detailansicht' : 'Kompaktansicht' }}
                    </v-btn>
                    <v-btn
                        size="small"
                        color="warning"
                        variant="flat"
                        prepend-icon="mdi-refresh"
                        @click="reloadFoods">
                        Aktualisieren
                    </v-btn>
                    <v-btn
                        size="small"
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-plus"
                        @click="openCreateDialog">
                        Neue Speise
                    </v-btn>
                </div>
            </template>

            <v-alert v-if="!foods.length" type="info" variant="tonal" class="mb-3">
                Noch keine Speisen vorhanden. Lege die erste Speise an und erweitere Kategorien direkt beim Speichern.
            </v-alert>

            <v-row v-else dense>
                <v-col v-for="food in foods" :key="food.id" cols="12" :md="isCompactView ? 4 : 6" :xl="isCompactView ? 3 : 4">
                    <v-card rounded="xl" variant="outlined" class="food-card h-100" :class="{ 'food-card--compact': isCompactView }">
                        <v-card-text class="pa-4">
                            <div class="text-h6 font-weight-bold">{{ food.title }}</div>

                            <div
                                class="d-flex ga-3 mt-1"
                                :class="isCompactView ? 'align-center' : 'align-start justify-space-between'">
                                <div class="text-body-2 text-medium-emphasis">{{ food.category?.title || 'Keine Kategorie' }}</div>

                                <v-chip v-if="!isCompactView && hasPrice(food.price)" color="primary" variant="flat">
                                    {{ formatCardPrice(food.price) }}
                                </v-chip>
                            </div>

                            <div v-if="!isCompactView && food.description" class="text-body-2 mt-3">
                                {{ food.description }}
                            </div>

                            <div v-if="!isCompactView && food.food_image_url" class="food-card__inline-image mt-3">
                                <v-img
                                    :src="food.food_image_url"
                                    class="food-card__inline-image-element"
                                    max-width="120"
                                    max-height="60"
                                    contain />
                            </div>

                            <div v-if="!isCompactView && food.allergens?.length" class="mt-3">
                                <div class="text-caption text-medium-emphasis mb-2">Allergene</div>
                                <div class="d-flex flex-wrap ga-2">
                                    <v-chip
                                        v-for="allergen in food.allergens"
                                        :key="`${food.id}-allergen-${allergen}`"
                                        size="small"
                                        color="warning"
                                        variant="tonal">
                                        {{ allergenLabel(allergen) }}
                                    </v-chip>
                                </div>
                            </div>

                            <div v-if="!isCompactView && food.ingredient_icons?.length" class="mt-3">
                                <div class="text-caption text-medium-emphasis mb-2">Zutaten-Hinweise</div>
                                <div class="d-flex flex-wrap ga-2">
                                    <div
                                        v-for="icon in food.ingredient_icons"
                                        :key="`${food.id}-icon-${icon.id}`"
                                        class="d-flex align-center ga-2 rounded-pill px-2 py-1 bg-grey-lighten-4">
                                        <v-avatar size="28">
                                            <v-img v-if="icon.image_url" :src="icon.image_url" cover />
                                            <span v-else class="text-caption font-weight-bold">{{ shortLabel(icon.title) }}</span>
                                        </v-avatar>
                                        <span class="text-body-2">{{ icon.title }}</span>
                                    </div>
                                </div>
                            </div>
                        </v-card-text>

                        <v-card-actions class="px-4 pb-4 pt-0" :class="{ 'food-card__actions--compact': isCompactView }">
                            <v-spacer />
                            <v-btn
                                size="small"
                                color="primary"
                                :variant="isCompactView ? 'text' : 'tonal'"
                                prepend-icon="mdi-pencil"
                                @click="openEditDialog(food)">
                                {{ isCompactView ? '' : 'Bearbeiten' }}
                            </v-btn>
                            <v-btn
                                size="small"
                                color="error"
                                variant="text"
                                prepend-icon="mdi-delete"
                                @click="requestDeleteFood(food)">
                                {{ isCompactView ? '' : 'Löschen' }}
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-col>
            </v-row>
        </ItsGridBox>

        <v-dialog v-model="dialog" max-width="760">
            <v-card rounded="xl">
                <v-card-title class="d-flex align-center">
                    <span>{{ editingFoodId ? 'Speise bearbeiten' : 'Neue Speise' }}</span>
                    <v-spacer />
                    <v-btn icon="mdi-close" variant="text" @click="closeDialog" />
                </v-card-title>

                <v-card-text>
                    <v-row dense>
                        <v-col cols="12" md="7">
                            <v-text-field
                                v-model="form.title"
                                label="Titel"
                                variant="outlined"
                                density="comfortable" />
                        </v-col>

                        <v-col cols="12" md="5">
                            <v-combobox
                                v-model="form.categoryTitle"
                                :items="categoryTitles"
                                label="Kategorie"
                                variant="outlined"
                                density="comfortable"
                                clearable />
                        </v-col>

                        <v-col cols="12">
                            <v-textarea
                                v-model="form.description"
                                label="Beschreibung"
                                rows="3"
                                variant="outlined"
                                density="comfortable" />
                        </v-col>

                        <v-col cols="12">
                            <div class="text-subtitle-2 mb-2">Speisenbild</div>
                            <file-pond
                                name="foodImage"
                                :allow-multiple="false"
                                :allow-file-type-validation="true"
                                :accepted-file-types="['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml']"
                                :allow-image-preview="true"
                                :allow-revert="false"
                                :allow-remove="true"
                                :files="foodImageFiles"
                                label-idle="<strong>Bild hierher ziehen oder <i>klicken</i></strong>"
                                label-file-processing-complete="Bild bereit"
                                @updatefiles="updateFoodImageFiles" />
                        </v-col>

                        <v-col cols="12">
                            <div class="text-subtitle-2 mb-2">Allergene</div>
                            <div class="text-body-2 text-medium-emphasis mb-3">
                                Klicke auf die Allergene, um sie hinzuzufügen oder zu entfernen.
                            </div>

                            <div class="d-flex flex-wrap ga-2 allergen-chip-list">
                                <v-chip
                                    v-for="allergen in allergenOptions"
                                    :key="`allergen-option-${allergen.character}`"
                                    class="allergen-chip"
                                    :class="{ 'allergen-chip--selected': isSelectedAllergen(allergen.character) }"
                                    variant="outlined"
                                    filter
                                    @click="toggleAllergen(allergen.character)">
                                    <span class="allergen-chip__code">{{ allergen.character }}</span>
                                    <span class="allergen-chip__label">{{ allergen.short_description }}</span>
                                </v-chip>
                            </div>
                        </v-col>

                        <v-col cols="12">
                            <div class="text-subtitle-2 mb-2">Zutaten-Symbole</div>
                            <div class="text-body-2 text-medium-emphasis mb-3">
                                Klicke auf die Symbole, um sie hinzuzufügen oder zu entfernen.
                            </div>

                            <div class="d-flex flex-wrap ga-2 ingredient-icon-chip-list">
                                <v-chip
                                    v-for="icon in ingredientIcons"
                                    :key="`ingredient-icon-option-${icon.id}`"
                                    class="ingredient-icon-chip"
                                    :class="{ 'ingredient-icon-chip--selected': isSelectedIngredientIcon(icon.id) }"
                                    variant="outlined"
                                    @click="toggleIngredientIcon(icon.id)">
                                    <v-avatar size="24" class="ingredient-icon-chip__avatar">
                                        <v-img v-if="icon.image_url" :src="icon.image_url" />
                                        <span v-else class="ingredient-icon-chip__fallback">{{ shortLabel(icon.title) }}</span>
                                    </v-avatar>
                                    <span class="ingredient-icon-chip__label">{{ icon.title }}</span>
                                </v-chip>
                            </div>
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="form.price"
                                label="Preis"
                                type="text"
                                inputmode="decimal"
                                placeholder="z. B. 4,5"
                                variant="outlined"
                                density="comfortable"
                                clearable
                                @blur="normalizePriceField" />
                        </v-col>

                        <v-col cols="12" v-if="currentImageUrl || foodImagePreview">
                            <div class="text-caption text-medium-emphasis mb-2">Vorschau</div>
                            <v-sheet class="rounded-lg overflow-hidden border">
                                <v-img :src="foodImagePreview || currentImageUrl" height="180" cover />
                            </v-sheet>
                        </v-col>

                        <v-col cols="12" v-if="editingFoodId && currentImageUrl">
                            <v-checkbox
                                v-model="form.removeFoodImage"
                                label="Bestehendes Bild entfernen"
                                density="comfortable"
                                hide-details />
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closeDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" :loading="isSaving" @click="saveFood">
                        Speichern
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>


        <v-dialog v-model="deleteDialog" max-width="440">
            <v-card rounded="xl">
                <v-card-title>Löschen bestätigen</v-card-title>

                <v-card-text>
                    <div class="text-body-1">
                        Soll <strong>{{ pendingDeleteFood?.title || 'diese Speise' }}</strong> wirklich gelöscht werden?
                    </div>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="cancelDeleteFood">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" @click="confirmDeleteFood">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import { mapState } from 'pinia'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import { useFoodStore } from '@/stores/admin/restaurant/FoodStore'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'
import vueFilePond from 'vue-filepond/dist/vue-filepond.js'
import 'filepond/dist/filepond.min.css'
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.min.css'
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'
import FilePondPluginImagePreview from 'filepond-plugin-image-preview'

const FilePond = vueFilePond(FilePondPluginFileValidateType, FilePondPluginImagePreview)

function emptyForm() {
    return {
        title: '',
        description: '',
        categoryTitle: '',
        allergens: [],
        ingredientIconIds: [],
        price: '',
        foodImage: null,
        removeFoodImage: false,
    }
}

export default {
    components: { ItsGridBox, FilePond },

    data() {
        return {
            dialog: false,
            deleteDialog: false,
            isCompactView: false,
            editingFoodId: null,
            pendingDeleteFood: null,
            form: emptyForm(),
            currentImageUrl: null,
            isSaving: false,
        }
    },

    computed: {
        ...mapState(useFoodStore, ['foods']),
        ...mapState(useRestaurantStore, ['categories', 'ingredientIcons', 'allergenOptions']),
        categoryTitles() {
            return this.categories.map((category) => category.title)
        },
        foodImagePreview() {
            if (! this.form.foodImage) {
                return null
            }

            return URL.createObjectURL(this.form.foodImage)
        },
        foodImageFiles() {
            return this.form.foodImage ? [{ source: this.form.foodImage, options: { type: 'local' } }] : []
        },
    },

    methods: {
        hasPrice(price) {
            return ! (price === null || price === undefined || price === '')
        },
        formatCardPrice(price) {
            if (! this.hasPrice(price)) {
                return ''
            }

            const numericPrice = Number(price)
            if (Number.isNaN(numericPrice)) {
                return ''
            }

            return `${numericPrice.toFixed(2).replace('.', ',')} EUR`
        },
        formatPrice(price) {
            if (price === null || price === undefined || price === '') {
                return 'Preis offen'
            }

            const numericPrice = Number(price)
            if (Number.isNaN(numericPrice)) {
                return 'Preis offen'
            }

            return `${numericPrice.toFixed(1).replace('.', ',')} EUR`
        },
        normalizePriceInput(value) {
            const rawValue = String(value ?? '').trim()

            if (rawValue === '') {
                return ''
            }

            const normalizedValue = rawValue.replace(',', '.')
            const numericPrice = Number(normalizedValue)

            if (Number.isNaN(numericPrice)) {
                return rawValue
            }

            const roundedPrice = Math.round(numericPrice * 10) / 10

            return roundedPrice.toFixed(1).replace('.', ',')
        },
        normalizePricePayload(value) {
            const normalizedPrice = this.normalizePriceInput(value)

            return normalizedPrice === '' ? '' : normalizedPrice.replace(',', '.')
        },
        normalizePriceField() {
            this.form.price = this.normalizePriceInput(this.form.price)
        },
        updateFoodImageFiles(fileItems) {
            const selectedFile = fileItems?.[0]?.file ?? null
            this.form.foodImage = selectedFile

            if (selectedFile) {
                this.form.removeFoodImage = false
            }
        },
        shortLabel(title) {
            return String(title || '').slice(0, 2).toUpperCase()
        },
        allergenLabel(value) {
            const normalizedValue = String(value || '').trim()
            const allergenOption = this.allergenOptions.find((option) => option.character === normalizedValue)

            if (! allergenOption) {
                return normalizedValue
            }

            return `${allergenOption.character} - ${allergenOption.short_description}`
        },
        isSelectedAllergen(character) {
            return this.form.allergens.includes(character)
        },
        toggleAllergen(character) {
            if (this.isSelectedAllergen(character)) {
                this.form.allergens = this.form.allergens.filter((value) => value !== character)
                return
            }

            this.form.allergens = [...this.form.allergens, character]
        },
        isSelectedIngredientIcon(iconId) {
            return this.form.ingredientIconIds.includes(iconId)
        },
        toggleIngredientIcon(iconId) {
            if (this.isSelectedIngredientIcon(iconId)) {
                this.form.ingredientIconIds = this.form.ingredientIconIds.filter((value) => value !== iconId)
                return
            }

            this.form.ingredientIconIds = [...this.form.ingredientIconIds, iconId]
        },
        openCreateDialog() {
            this.editingFoodId = null
            this.form = emptyForm()
            this.currentImageUrl = null
            this.dialog = true
        },
        toggleCardView() {
            this.isCompactView = ! this.isCompactView
        },
        openEditDialog(food) {
            this.editingFoodId = food.id
            this.form = {
                title: food.title || '',
                description: food.description || '',
                categoryTitle: food.category?.title || '',
                allergens: [...(food.allergens || [])],
                ingredientIconIds: (food.ingredient_icons || []).map((icon) => icon.id),
                price: this.normalizePriceInput(food.price),
                foodImage: null,
                removeFoodImage: false,
            }
            this.currentImageUrl = food.food_image_url || null
            this.dialog = true
        },
        closeDialog() {
            this.dialog = false
            this.editingFoodId = null
            this.form = emptyForm()
            this.currentImageUrl = null
        },
        requestDeleteFood(food) {
            this.pendingDeleteFood = food
            this.deleteDialog = true
        },
        cancelDeleteFood() {
            this.deleteDialog = false
            this.pendingDeleteFood = null
        },
        async confirmDeleteFood() {
            if (! this.pendingDeleteFood) {
                return
            }

            const deleted = await useFoodStore().destroy(this.pendingDeleteFood.id)
            if (deleted) {
                await useRestaurantStore().loadSettings()
            }

            this.cancelDeleteFood()
        },
        async reloadFoods() {
            await Promise.all([useFoodStore().index(), useRestaurantStore().loadSettings()])
        },
        async saveFood() {
            if (this.isSaving) {
                return
            }

            this.isSaving = true

            const payload = {
                title: this.form.title,
                description: this.form.description,
                category_title: this.form.categoryTitle,
                allergens: this.form.allergens,
                ingredient_icon_ids: this.form.ingredientIconIds,
                price: this.normalizePricePayload(this.form.price),
                food_image: this.form.foodImage,
                remove_food_image: this.form.removeFoodImage ? '1' : '',
            }

            const foodStore = useFoodStore()
            const restaurantStore = useRestaurantStore()
            const result = this.editingFoodId
                ? await foodStore.update(this.editingFoodId, payload)
                : await foodStore.store(payload)

            if (result) {
                await restaurantStore.loadSettings()
                this.closeDialog()
            }

            this.isSaving = false
        },
    },
}
</script>

<style scoped>
.food-card {
    border-color: rgba(15, 23, 42, 0.1);
    background: rgba(255, 255, 255, 0.96);
}

.food-card--compact {
    background: rgba(255, 255, 255, 0.98);
}

.food-card__actions--compact {
    min-height: 0;
    padding-top: 0;
}

.food-card__actions--compact :deep(button) {
    min-width: 0;
    padding-inline: 0.25rem;
}

.food-card__inline-image {
    display: flex;
    align-items: center;
    min-height: 60px;
}

.food-card__inline-image-element {
    max-width: 120px;
    max-height: 60px;
    border-radius: 0.75rem;
    background: rgba(248, 250, 252, 0.92);
}

.allergen-chip-list {
    width: 100%;
}

.allergen-chip {
    border-radius: 999px;
    border-color: rgba(15, 23, 42, 0.18);
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.98), rgba(241, 245, 249, 0.94));
    box-shadow: 0 10px 24px -18px rgba(15, 23, 42, 0.45);
    justify-content: flex-start;
    padding-left: 0.15rem;
    padding-right: 0.5rem;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.allergen-chip:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 26px -18px rgba(15, 23, 42, 0.55);
}

.allergen-chip--selected {
    border-color: rgb(194, 65, 12);
    background: linear-gradient(135deg, rgb(249, 115, 22), rgb(234, 88, 12));
    color: rgb(255, 255, 255);
    box-shadow: 0 14px 28px -18px rgba(234, 88, 12, 0.75);
}

.allergen-chip--selected .allergen-chip__code {
    background: rgba(255, 255, 255, 0.24);
    color: rgb(255, 255, 255);
}

.allergen-chip--selected .allergen-chip__label {
    color: rgb(255, 255, 255);
}

.allergen-chip__code {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.8rem;
    height: 1.8rem;
    margin-right: 0.45rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.08);
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.04em;
}

.allergen-chip__label {
    font-weight: 500;
    letter-spacing: 0.01em;
}

.ingredient-icon-chip-list {
    width: 100%;
}

.ingredient-icon-chip {
    border-radius: 999px;
    border-color: rgba(15, 23, 42, 0.16);
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 10px 24px -18px rgba(15, 23, 42, 0.3);
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background-color 0.18s ease;
}

.ingredient-icon-chip:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 26px -18px rgba(15, 23, 42, 0.4);
}

.ingredient-icon-chip--selected {
    border-color: rgba(30, 64, 175, 0.8);
    background: rgba(219, 234, 254, 0.95);
    box-shadow: 0 14px 28px -18px rgba(37, 99, 235, 0.45);
}

.ingredient-icon-chip__avatar {
    margin-right: 0.45rem;
    background: rgba(15, 23, 42, 0.06);
}

.ingredient-icon-chip__fallback {
    font-size: 0.72rem;
    font-weight: 700;
}

.ingredient-icon-chip__label {
    font-weight: 500;
}
</style>


