<template>
    <v-col cols="12">
        <v-row dense>
            <v-col cols="12" lg="5">
                <ItsGridBox variant="overview" color="primary" title="Kategorien" icon="mdi-shape-outline">
                    <template #header-actions>
                        <v-btn size="small" color="primary" variant="flat" prepend-icon="mdi-plus" @click="openNewCategory">
                            Neue Kategorie
                        </v-btn>
                    </template>

                    <v-alert v-if="!categories.length" type="info" variant="tonal" class="mb-3">
                        Noch keine Kategorien vorhanden.
                    </v-alert>

                    <v-list v-else density="comfortable" class="bg-transparent pa-0">
                        <v-list-item
                            v-for="category in categories"
                            :key="category.id"
                            :title="category.title"
                            :subtitle="`${category.foods_count || 0} Speisen`">
                            <template #append>
                                <div class="d-flex ga-1">
                                    <v-btn icon="mdi-pencil" size="x-small" variant="text" @click="editCategory(category)" />
                                    <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="destroyCategory(category)" />
                                </div>
                            </template>
                        </v-list-item>
                    </v-list>

                    <v-divider class="my-4" />

                    <v-text-field
                        v-model="categoryForm.title"
                        label="Kategoriename"
                        variant="outlined"
                        density="comfortable" />
                    <v-text-field
                        v-model="categoryForm.sort_order"
                        label="Reihenfolge"
                        type="number"
                        variant="outlined"
                        density="comfortable" />

                    <div class="d-flex justify-end ga-2">
                        <v-btn variant="text" :disabled="!categoryEditingId" @click="resetCategoryForm">Zurücksetzen</v-btn>
                        <v-btn color="primary" variant="flat" @click="saveCategory">
                            {{ categoryEditingId ? 'Kategorie aktualisieren' : 'Kategorie speichern' }}
                        </v-btn>
                    </div>
                </ItsGridBox>
            </v-col>

            <v-col cols="12" lg="7">
                <ItsGridBox variant="overview" color="primary" title="Zutaten-Symbole" icon="mdi-image-multiple-outline">
                    <template #header-actions>
                        <v-btn size="small" color="primary" variant="flat" prepend-icon="mdi-plus" @click="openNewIngredientIcon">
                            Neues Symbol
                        </v-btn>
                    </template>

                    <v-alert v-if="!ingredientIcons.length" type="info" variant="tonal" class="mb-3">
                        Noch keine Zutaten-Symbole vorhanden.
                    </v-alert>

                    <v-row dense>
                        <v-col
                            v-for="icon in ingredientIcons"
                            :key="icon.id"
                            cols="12"
                            md="6">
                            <v-sheet rounded="lg" class="settings-icon-card pa-3">
                                <div class="d-flex align-center ga-3">
                                    <v-avatar size="56" rounded="lg" class="settings-icon-card__avatar">
                                        <v-img v-if="icon.image_url" :src="icon.image_url" cover />
                                        <span v-else class="font-weight-bold">{{ shortLabel(icon.title) }}</span>
                                    </v-avatar>

                                    <div class="flex-grow-1">
                                        <div class="font-weight-bold">{{ icon.title }}</div>
                                        <div class="text-body-2 text-medium-emphasis">{{ icon.foods_count || 0 }} Speisen</div>
                                    </div>

                                    <div class="d-flex ga-1">
                                        <v-btn icon="mdi-pencil" size="x-small" variant="text" @click="editIngredientIcon(icon)" />
                                        <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="destroyIngredientIcon(icon)" />
                                    </div>
                                </div>
                            </v-sheet>
                        </v-col>
                    </v-row>

                    <v-divider class="my-4" />

                    <v-row dense>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="ingredientIconForm.title"
                                label="Titel"
                                variant="outlined"
                                density="comfortable" />
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-text-field
                                v-model="ingredientIconForm.sort_order"
                                label="Reihenfolge"
                                type="number"
                                variant="outlined"
                                density="comfortable" />
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-file-input
                                v-model="ingredientIconForm.image"
                                label="Bild"
                                accept="image/*"
                                variant="outlined"
                                density="comfortable"
                                clearable />
                        </v-col>

                        <v-col cols="12" v-if="ingredientIconPreview || ingredientIconForm.currentImageUrl">
                            <div class="text-caption text-medium-emphasis mb-2">Vorschau</div>
                            <v-sheet rounded="lg" class="settings-icon-preview pa-4">
                                <v-avatar size="84" rounded="lg">
                                    <v-img :src="ingredientIconPreview || ingredientIconForm.currentImageUrl" cover />
                                </v-avatar>
                            </v-sheet>
                        </v-col>

                        <v-col cols="12" v-if="ingredientIconEditingId && ingredientIconForm.currentImageUrl">
                            <v-checkbox
                                v-model="ingredientIconForm.removeImage"
                                label="Bestehendes Bild entfernen"
                                density="comfortable"
                                hide-details />
                        </v-col>
                    </v-row>

                    <div class="d-flex justify-end ga-2">
                        <v-btn variant="text" :disabled="!ingredientIconEditingId" @click="resetIngredientIconForm">Zurücksetzen</v-btn>
                        <v-btn color="primary" variant="flat" @click="saveIngredientIcon">
                            {{ ingredientIconEditingId ? 'Symbol aktualisieren' : 'Symbol speichern' }}
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

function emptyCategoryForm() {
    return {
        title: '',
        sort_order: 0,
    }
}

function emptyIngredientIconForm() {
    return {
        title: '',
        sort_order: 0,
        image: null,
        currentImageUrl: null,
        removeImage: false,
    }
}

export default {
    components: { ItsGridBox },

    data() {
        return {
            categoryEditingId: null,
            categoryForm: emptyCategoryForm(),
            ingredientIconEditingId: null,
            ingredientIconForm: emptyIngredientIconForm(),
        }
    },

    computed: {
        ...mapState(useRestaurantStore, ['categories', 'ingredientIcons']),
        ingredientIconPreview() {
            if (! this.ingredientIconForm.image) {
                return null
            }

            return URL.createObjectURL(this.ingredientIconForm.image)
        },
    },

    methods: {
        shortLabel(title) {
            return String(title || '').slice(0, 2).toUpperCase()
        },
        openNewCategory() {
            this.resetCategoryForm()
        },
        editCategory(category) {
            this.categoryEditingId = category.id
            this.categoryForm = {
                title: category.title,
                sort_order: category.sort_order || 0,
            }
        },
        resetCategoryForm() {
            this.categoryEditingId = null
            this.categoryForm = emptyCategoryForm()
        },
        async saveCategory() {
            const restaurantStore = useRestaurantStore()
            const payload = {
                title: this.categoryForm.title,
                sort_order: this.categoryForm.sort_order,
            }

            const result = this.categoryEditingId
                ? await restaurantStore.updateCategory(this.categoryEditingId, payload)
                : await restaurantStore.storeCategory(payload)

            if (result) {
                this.resetCategoryForm()
                await restaurantStore.loadSettings()
            }
        },
        async destroyCategory(category) {
            if (! window.confirm(`Soll die Kategorie "${category.title}" wirklich gelöscht werden?`)) {
                return
            }

            const deleted = await useRestaurantStore().destroyCategory(category.id)
            if (deleted) {
                await useRestaurantStore().loadSettings()
            }
        },
        openNewIngredientIcon() {
            this.resetIngredientIconForm()
        },
        editIngredientIcon(icon) {
            this.ingredientIconEditingId = icon.id
            this.ingredientIconForm = {
                title: icon.title,
                sort_order: icon.sort_order || 0,
                image: null,
                currentImageUrl: icon.image_url || null,
                removeImage: false,
            }
        },
        resetIngredientIconForm() {
            this.ingredientIconEditingId = null
            this.ingredientIconForm = emptyIngredientIconForm()
        },
        async saveIngredientIcon() {
            const restaurantStore = useRestaurantStore()
            const payload = {
                title: this.ingredientIconForm.title,
                sort_order: this.ingredientIconForm.sort_order,
                image: this.ingredientIconForm.image,
                remove_image: this.ingredientIconForm.removeImage ? '1' : '',
            }

            const result = this.ingredientIconEditingId
                ? await restaurantStore.updateIngredientIcon(this.ingredientIconEditingId, payload)
                : await restaurantStore.storeIngredientIcon(payload)

            if (result) {
                this.resetIngredientIconForm()
                await restaurantStore.loadSettings()
            }
        },
        async destroyIngredientIcon(icon) {
            if (! window.confirm(`Soll das Symbol "${icon.title}" wirklich gelöscht werden?`)) {
                return
            }

            const deleted = await useRestaurantStore().destroyIngredientIcon(icon.id)
            if (deleted) {
                await useRestaurantStore().loadSettings()
            }
        },
    },
}
</script>

<style scoped>
.settings-icon-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: rgba(255, 255, 255, 0.92);
}

.settings-icon-card__avatar {
    background: rgba(59, 130, 246, 0.12);
}

.settings-icon-preview {
    border: 1px dashed rgba(59, 130, 246, 0.35);
    background: rgba(239, 246, 255, 0.72);
    display: flex;
    justify-content: center;
}
</style>
