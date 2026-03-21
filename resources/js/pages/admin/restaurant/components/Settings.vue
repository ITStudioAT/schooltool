<template>
    <v-col cols="12">
        <v-row dense>
            <v-col cols="12">
                <v-sheet rounded="xl" class="settings-subnav pa-2 mb-3">
                    <div class="d-flex flex-wrap ga-2">
                        <v-btn
                            rounded="xl"
                            :color="selectedPanel === 'categories' ? 'primary' : undefined"
                            :variant="selectedPanel === 'categories' ? 'flat' : 'outlined'"
                            class="settings-subnav__button"
                            :class="{ 'settings-subnav__button--active': selectedPanel === 'categories' }"
                            @click="activatePanel('categories')">
                            Kategorien
                        </v-btn>
                        <v-btn
                            rounded="xl"
                            :color="selectedPanel === 'ingredient-icons' ? 'primary' : undefined"
                            :variant="selectedPanel === 'ingredient-icons' ? 'flat' : 'outlined'"
                            class="settings-subnav__button"
                            :class="{ 'settings-subnav__button--active': selectedPanel === 'ingredient-icons' }"
                            @click="activatePanel('ingredient-icons')">
                            Zutaten-Symbole
                        </v-btn>
                    </div>
                </v-sheet>
            </v-col>

            <v-col v-if="selectedPanel === 'categories'" cols="12">
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
                                    <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="openCategoryDeleteDialog(category)" />
                                </div>
                            </template>
                        </v-list-item>
                    </v-list>
                </ItsGridBox>
            </v-col>

            <v-col v-if="selectedPanel === 'ingredient-icons'" cols="12">
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
                                        <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="openIngredientIconDeleteDialog(icon)" />
                                    </div>
                                </div>
                            </v-sheet>
                        </v-col>
                    </v-row>
                </ItsGridBox>
            </v-col>
        </v-row>

        <v-dialog v-model="categoryDialog" max-width="520" persistent>
            <v-card rounded="xl">
                <v-card-title>{{ categoryEditingId ? 'Kategorie bearbeiten' : 'Neue Kategorie' }}</v-card-title>

                <v-card-text>
                    <v-form ref="categoryForm" v-model="isCategoryFormValid" @submit.prevent="saveCategory">
                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="categoryForm.title"
                                    label="Kategoriename"
                                    variant="outlined"
                                    density="comfortable"
                                    autofocus
                                    :rules="[required(), maxLength(255)]" />
                            </v-col>

                            <v-col cols="12">
                                <v-text-field
                                    v-model="categoryForm.sort_order"
                                    label="Reihenfolge"
                                    type="number"
                                    variant="outlined"
                                    density="comfortable" />
                            </v-col>
                        </v-row>
                    </v-form>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closeCategoryDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" @click="saveCategory">
                        {{ categoryEditingId ? 'Kategorie aktualisieren' : 'Kategorie speichern' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="categoryDeleteDialog" max-width="440" persistent>
            <v-card rounded="xl">
                <v-card-title>Kategorie löschen</v-card-title>

                <v-card-text>
                    <div class="text-body-1">
                        Soll <strong>{{ pendingDeleteCategory?.title || 'diese Kategorie' }}</strong> wirklich gelöscht werden?
                    </div>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closeCategoryDeleteDialog">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" @click="confirmDestroyCategory">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="ingredientIconDialog" max-width="560" persistent>
            <v-card rounded="xl">
                <v-card-title>{{ ingredientIconEditingId ? 'Symbol bearbeiten' : 'Neues Symbol' }}</v-card-title>

                <v-card-text>
                    <v-form ref="ingredientIconFormRef" v-model="isIngredientIconFormValid" @submit.prevent="saveIngredientIcon">
                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="ingredientIconForm.title"
                                    label="Titel"
                                    variant="outlined"
                                    density="comfortable"
                                    autofocus
                                    :rules="[required(), maxLength(255)]" />
                            </v-col>

                            <v-col cols="12">
                                <div class="text-subtitle-2 mb-2">SVG hochladen</div>
                                <file-pond
                                    name="ingredientIconImage"
                                    :allow-multiple="false"
                                    :allow-file-type-validation="true"
                                    :accepted-file-types="['image/svg+xml']"
                                    :allow-image-preview="true"
                                    :allow-revert="false"
                                    :allow-remove="true"
                                    :files="ingredientIconFiles"
                                    label-idle="<strong>SVG hierher ziehen oder <i>klicken</i></strong>"
                                    label-file-processing-complete="SVG bereit"
                                    @updatefiles="updateIngredientIconFiles" />
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
                    </v-form>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closeIngredientIconDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" @click="saveIngredientIcon">
                        {{ ingredientIconEditingId ? 'Symbol aktualisieren' : 'Symbol speichern' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="ingredientIconDeleteDialog" max-width="440" persistent>
            <v-card rounded="xl">
                <v-card-title>Symbol löschen</v-card-title>

                <v-card-text>
                    <div class="text-body-1">
                        Soll <strong>{{ pendingDeleteIngredientIcon?.title || 'dieses Symbol' }}</strong> wirklich gelöscht werden?
                    </div>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closeIngredientIconDeleteDialog">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" @click="confirmDestroyIngredientIcon">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import { mapState } from 'pinia'
import { useValidationRulesSetup } from '@/helpers/rules'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'
import vueFilePond from 'vue-filepond/dist/vue-filepond.js'
import 'filepond/dist/filepond.min.css'
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.min.css'
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'
import FilePondPluginImagePreview from 'filepond-plugin-image-preview'

const FilePond = vueFilePond(FilePondPluginFileValidateType, FilePondPluginImagePreview)

function emptyCategoryForm() {
    return {
        title: '',
        sort_order: 0,
    }
}

function emptyIngredientIconForm() {
    return {
        title: '',
        image: null,
        currentImageUrl: null,
        removeImage: false,
    }
}

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { FilePond, ItsGridBox },

    data() {
        return {
            selectedPanel: 'categories',
            categoryDialog: false,
            isCategoryFormValid: false,
            categoryDeleteDialog: false,
            ingredientIconDialog: false,
            ingredientIconDeleteDialog: false,
            isIngredientIconFormValid: false,
            categoryEditingId: null,
            pendingDeleteCategory: null,
            pendingDeleteIngredientIcon: null,
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
        ingredientIconFiles() {
            return this.ingredientIconForm.image ? [{ source: this.ingredientIconForm.image, options: { type: 'local' } }] : []
        },
    },

    methods: {
        activatePanel(panel) {
            this.selectedPanel = panel
        },
        shortLabel(title) {
            return String(title || '').slice(0, 2).toUpperCase()
        },
        openNewCategory() {
            this.resetCategoryForm()
            this.categoryDialog = true
        },
        editCategory(category) {
            this.categoryEditingId = category.id
            this.categoryForm = {
                title: category.title,
                sort_order: category.sort_order || 0,
            }
            this.categoryDialog = true
        },
        resetCategoryForm() {
            this.categoryEditingId = null
            this.categoryForm = emptyCategoryForm()
            this.isCategoryFormValid = false
        },
        closeCategoryDialog() {
            this.categoryDialog = false
            this.resetCategoryForm()
        },
        openCategoryDeleteDialog(category) {
            this.pendingDeleteCategory = category
            this.categoryDeleteDialog = true
        },
        closeCategoryDeleteDialog() {
            this.categoryDeleteDialog = false
            this.pendingDeleteCategory = null
        },
        async saveCategory() {
            this.isCategoryFormValid = false
            await this.$refs.categoryForm?.validate()

            if (! this.isCategoryFormValid) {
                return
            }

            const restaurantStore = useRestaurantStore()
            const payload = {
                title: this.categoryForm.title,
                sort_order: this.categoryForm.sort_order,
            }

            const result = this.categoryEditingId
                ? await restaurantStore.updateCategory(this.categoryEditingId, payload)
                : await restaurantStore.storeCategory(payload)

            if (result) {
                this.categoryDialog = false
                this.resetCategoryForm()
                await restaurantStore.loadSettings()
            }
        },
        async confirmDestroyCategory() {
            if (! this.pendingDeleteCategory) {
                return
            }

            const deleted = await useRestaurantStore().destroyCategory(this.pendingDeleteCategory.id)
            if (deleted) {
                await useRestaurantStore().loadSettings()
            }

            this.closeCategoryDeleteDialog()
        },
        openNewIngredientIcon() {
            this.resetIngredientIconForm()
            this.ingredientIconDialog = true
        },
        editIngredientIcon(icon) {
            this.ingredientIconEditingId = icon.id
            this.ingredientIconForm = {
                title: icon.title,
                image: null,
                currentImageUrl: icon.image_url || null,
                removeImage: false,
            }
            this.ingredientIconDialog = true
        },
        resetIngredientIconForm() {
            this.ingredientIconEditingId = null
            this.ingredientIconForm = emptyIngredientIconForm()
            this.isIngredientIconFormValid = false
        },
        closeIngredientIconDialog() {
            this.ingredientIconDialog = false
            this.resetIngredientIconForm()
        },
        openIngredientIconDeleteDialog(icon) {
            this.pendingDeleteIngredientIcon = icon
            this.ingredientIconDeleteDialog = true
        },
        closeIngredientIconDeleteDialog() {
            this.ingredientIconDeleteDialog = false
            this.pendingDeleteIngredientIcon = null
        },
        updateIngredientIconFiles(fileItems) {
            const selectedFile = fileItems?.[0]?.file ?? null
            this.ingredientIconForm.image = selectedFile

            if (selectedFile) {
                this.ingredientIconForm.removeImage = false
            }
        },
        async saveIngredientIcon() {
            this.isIngredientIconFormValid = false
            await this.$refs.ingredientIconFormRef?.validate()

            if (! this.isIngredientIconFormValid) {
                return
            }

            const restaurantStore = useRestaurantStore()
            const payload = {
                title: this.ingredientIconForm.title,
                image: this.ingredientIconForm.image,
                remove_image: this.ingredientIconForm.removeImage ? '1' : '',
            }

            const result = this.ingredientIconEditingId
                ? await restaurantStore.updateIngredientIcon(this.ingredientIconEditingId, payload)
                : await restaurantStore.storeIngredientIcon(payload)

            if (result) {
                this.ingredientIconDialog = false
                this.resetIngredientIconForm()
                await restaurantStore.loadSettings()
            }
        },
        async confirmDestroyIngredientIcon() {
            if (! this.pendingDeleteIngredientIcon) {
                return
            }

            const deleted = await useRestaurantStore().destroyIngredientIcon(this.pendingDeleteIngredientIcon.id)
            if (deleted) {
                await useRestaurantStore().loadSettings()
            }

            this.closeIngredientIconDeleteDialog()
        },
    },
}
</script>

<style scoped>
.settings-subnav {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(255, 255, 255, 0.92);
}

.settings-subnav__button {
    border-color: rgba(100, 116, 139, 0.28);
    background: rgba(248, 250, 252, 0.94);
    color: rgb(15, 23, 42);
}

.settings-subnav__button--active {
    color: inherit;
}

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
