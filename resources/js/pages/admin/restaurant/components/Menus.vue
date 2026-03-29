<template>
    <v-col cols="12">
        <ItsGridBox variant="overview" color="primary" title="Menüs" icon="mdi-food-takeout-box-outline">
            <template #header-actions>
                <div class="d-flex ga-2">
                    <v-btn
                        size="small"
                        :color="viewMode === 'detail' ? 'primary' : 'secondary'"
                        :variant="viewMode === 'detail' ? 'flat' : 'tonal'"
                        prepend-icon="mdi-view-dashboard-outline"
                        @click="setViewMode('detail')">
                        Detailansicht
                    </v-btn>
                    <v-btn
                        size="small"
                        :color="viewMode === 'image' ? 'primary' : 'secondary'"
                        :variant="viewMode === 'image' ? 'flat' : 'tonal'"
                        prepend-icon="mdi-image-multiple-outline"
                        @click="setViewMode('image')">
                        Bildansicht
                    </v-btn>
                    <v-btn
                        size="small"
                        :color="viewMode === 'compact' ? 'primary' : 'secondary'"
                        :variant="viewMode === 'compact' ? 'flat' : 'tonal'"
                        prepend-icon="mdi-view-grid-outline"
                        @click="setViewMode('compact')">
                        Kompaktansicht
                    </v-btn>
                    <v-btn
                        size="small"
                        color="warning"
                        variant="flat"
                        prepend-icon="mdi-refresh"
                        @click="reloadMenus">
                        Aktualisieren
                    </v-btn>
                    <v-btn
                        size="small"
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-plus"
                        @click="openCreateDialog">
                        {{ newMenuLabel }}
                    </v-btn>
                </div>
            </template>

            <div v-if="menus.length" class="menu-search-panel mb-4">
                <v-text-field
                    v-model="menuSearchQuery"
                    label="Menü suchen"
                    prepend-inner-icon="mdi-magnify"
                    variant="outlined"
                    density="comfortable"
                    clearable
                    hide-details
                    class="menu-search-panel__field" />
            </div>

            <v-alert v-if="!menus.length" type="info" variant="tonal" class="mb-3">
                Noch keine Menüs vorhanden. Lege das erste Menü mit einem oder mehreren Gängen an.
            </v-alert>

            <v-alert v-else-if="!filteredMenus.length" type="info" variant="tonal" class="mb-3">
                Kein Menü passt zur aktuellen Suche.
            </v-alert>

            <v-row v-else dense>
                <v-col v-for="menu in paginatedMenus" :key="menu.id" cols="12" :md="cardColumnMd" :xl="cardColumnXl">
                    <v-card rounded="xl" variant="outlined" class="menu-card h-100" :class="{ 'menu-card--compact': isCompactView, 'menu-card--image': isImageView }">
                        <v-card-text class="pa-4">
                            <div class="text-h6 font-weight-bold">{{ menu.title }}</div>

                            <div class="menu-card__summary d-flex flex-wrap align-center justify-space-between ga-3 mt-1">
                                <div class="menu-card__summary-meta d-flex flex-wrap align-center ga-2">
                                    <div class="text-body-2 text-medium-emphasis">
                                        {{ menu.courses_count || menu.foods?.length || 0 }} Gänge
                                    </div>

                                    <v-chip v-if="hasPrice(menu.price)" color="primary" variant="flat">
                                        {{ formatCardPrice(menu.price) }}
                                    </v-chip>
                                </div>

                                <div class="menu-card__summary-actions d-flex align-center justify-end ga-1">
                                    <v-btn
                                        size="x-small"
                                        color="primary"
                                        variant="text"
                                        icon="mdi-pencil"
                                        @click="openEditDialog(menu)" />
                                    <v-btn
                                        size="x-small"
                                        color="error"
                                        variant="text"
                                        icon="mdi-delete"
                                        @click="requestDeleteMenu(menu)" />
                                </div>
                            </div>

                            <div v-if="!isCompactView && menu.foods?.length" class="mt-3">
                                <div class="text-caption text-medium-emphasis mb-2">Menüfolge</div>
                                <div class="d-flex flex-column ga-2">
                                    <div
                                        v-for="course in orderedMenuFoods(menu.foods)"
                                        :key="`${menu.id}-course-${course.id}`"
                                        class="menu-course-row"
                                        :class="{ 'menu-course-row--image': isImageView }">
                                        <div v-if="isImageView && course.food_image_url" class="menu-course-row__image">
                                            <v-img
                                                :src="course.food_image_url"
                                                class="menu-course-row__image-element"
                                                width="104"
                                                height="72"
                                                cover />
                                        </div>

                                        <div class="menu-course-row__content">
                                            <v-chip size="x-small" color="secondary" variant="tonal">
                                                Gang {{ course.course_number }}
                                            </v-chip>
                                            <span class="text-body-2 font-weight-medium">{{ course.title }}</span>
                                            <span v-if="course.category?.title" class="text-caption text-medium-emphasis">{{ course.category.title }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <div v-if="filteredMenus.length" class="menu-pagination d-flex flex-wrap align-center justify-space-between ga-3 mt-4">
                <div class="d-flex flex-wrap align-center ga-3">
                    <div class="menu-pagination__selector">
                        <div class="menu-pagination__label">Pro Seite:</div>

                        <v-btn
                            size="small"
                            rounded="pill"
                            class="menu-pagination__counter"
                            color="primary"
                            variant="flat"
                            @click="openPaginationDialog">
                            {{ currentPaginationNumber }}
                        </v-btn>
                    </div>

                    <div class="text-body-2 text-medium-emphasis">
                        {{ paginationSummary }}
                    </div>
                </div>

                <v-pagination
                    v-if="pageCount > 1"
                    v-model="currentPage"
                    :length="pageCount"
                    :total-visible="6"
                    density="comfortable" />
            </div>
        </ItsGridBox>

        <v-dialog v-model="dialog" max-width="820" persistent>
            <v-card rounded="xl">
                <v-card-title class="d-flex align-center">
                    <span>{{ editingMenuId ? 'Menü bearbeiten' : newMenuLabel }}</span>
                    <v-spacer />
                    <v-btn icon="mdi-close" variant="text" @click="closeDialog" />
                </v-card-title>

                <v-card-text>
                    <v-form ref="menuForm" v-model="isMenuFormValid" @submit.prevent="saveMenu">
                        <v-row dense>
                        <v-col cols="12" md="8">
                            <v-text-field
                                v-model="form.title"
                                label="Titel"
                                variant="outlined"
                                density="comfortable"
                                :rules="[required()]"
                                autofocus />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="form.price"
                                label="Preis"
                                type="text"
                                inputmode="decimal"
                                placeholder="z. B. 9,5"
                                variant="outlined"
                                density="comfortable"
                                @blur="normalizePriceField" />
                        </v-col>

                        <v-col cols="12">
                            <div class="menu-course-builder">
                                <div class="menu-course-builder__header">
                                    <div>
                                        <div class="text-subtitle-1 font-weight-bold">Gang {{ currentCourseNumber }}</div>
                                        <div class="text-body-2 text-medium-emphasis">
                                            Lege jeden Gang nacheinander an: zuerst Kategorie, dann Speise.
                                        </div>
                                    </div>

                                    <v-chip color="secondary" variant="tonal">
                                        {{ form.foodIds.length }} gewählt
                                    </v-chip>
                                </div>

                                <div class="text-subtitle-2 mb-2">1. Kategorie wählen</div>
                                <div v-if="categories.length" class="d-flex flex-wrap ga-2 menu-category-chip-list">
                                    <v-chip
                                        v-for="category in categories"
                                        :key="`menu-category-${category.id}`"
                                        class="menu-category-chip"
                                        :class="{ 'menu-category-chip--selected': isDraftCategorySelected(category.id) }"
                                        variant="outlined"
                                        @click="selectDraftCategory(category.id)">
                                        {{ category.title }}
                                    </v-chip>
                                </div>
                                <v-alert v-else type="warning" variant="tonal">
                                    Bitte zuerst unter Einstellungen Kategorien anlegen.
                                </v-alert>

                                <div class="text-subtitle-2 mb-2 mt-4">2. Speise wählen</div>
                                <div v-if="!form.courseDraft.categoryId" class="text-body-2 text-medium-emphasis">
                                    Wähle zuerst eine Kategorie für Gang {{ currentCourseNumber }}.
                                </div>
                                <div v-else-if="availableFoodsForDraft.length">
                                    <v-text-field
                                        v-model="foodSearch"
                                        label="Speise suchen"
                                        prepend-inner-icon="mdi-magnify"
                                        variant="outlined"
                                        density="comfortable"
                                        clearable
                                        class="mb-3" />

                                    <div v-if="filteredFoodsForDraft.length" class="menu-food-chip-list">
                                        <div class="menu-food-chip-scroll">
                                            <div class="d-flex flex-wrap ga-2">
                                                <v-chip
                                                    v-for="food in filteredFoodsForDraft"
                                                    :key="`menu-food-option-${food.id}`"
                                                    class="menu-food-chip"
                                                    :class="{
                                                        'menu-food-chip--selected': isDraftFoodSelected(food.id),
                                                        'menu-food-chip--disabled': isFoodAlreadyAssigned(food.id),
                                                    }"
                                                    variant="outlined"
                                                    :disabled="isFoodAlreadyAssigned(food.id)"
                                                    @click="selectDraftFood(food.id)">
                                                    {{ food.title }}
                                                </v-chip>
                                            </div>
                                        </div>
                                    </div>
                                    <v-alert v-else type="info" variant="tonal">
                                        Keine Speise passt zur aktuellen Suche.
                                    </v-alert>
                                </div>
                                <v-alert v-else type="info" variant="tonal">
                                    In dieser Kategorie gibt es aktuell keine verfügbare Speise.
                                </v-alert>

                                <div class="d-flex justify-end mt-4">
                                    <v-btn
                                        color="primary"
                                        variant="flat"
                                        prepend-icon="mdi-plus"
                                        :disabled="!canAddDraftCourse"
                                        @click="appendDraftCourse">
                                        Gang {{ currentCourseNumber }} hinzufügen
                                    </v-btn>
                                </div>
                            </div>
                        </v-col>

                        <v-col cols="12">
                            <div class="text-subtitle-2 mb-2">Menüfolge</div>
                            <v-alert v-if="!form.foodIds.length" type="info" variant="tonal">
                                Noch kein Gang angelegt. Beginne mit Gang 1.
                            </v-alert>

                            <div v-else class="d-flex flex-column ga-2">
                                <div
                                    v-for="(foodId, index) in form.foodIds"
                                    :key="`selected-menu-food-${foodId}`"
                                    class="menu-selected-course">
                                    <div class="d-flex align-center ga-2">
                                        <v-chip size="small" color="primary" variant="flat">
                                            Gang {{ index + 1 }}
                                        </v-chip>
                                        <div>
                                            <div class="font-weight-medium">{{ selectedFoodTitle(foodId) }}</div>
                                            <div v-if="selectedFoodCategoryTitle(foodId)" class="text-caption text-medium-emphasis">
                                                {{ selectedFoodCategoryTitle(foodId) }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex align-center ga-1">
                                        <v-btn icon="mdi-arrow-up" size="x-small" variant="text" :disabled="index === 0" @click="moveSelectedFood(index, -1)" />
                                        <v-btn icon="mdi-arrow-down" size="x-small" variant="text" :disabled="index === form.foodIds.length - 1" @click="moveSelectedFood(index, 1)" />
                                        <v-btn icon="mdi-delete-outline" size="x-small" variant="text" color="error" @click="removeSelectedFood(foodId)" />
                                    </div>
                                </div>
                            </div>
                        </v-col>
                        </v-row>
                    </v-form>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closeDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" :loading="isSaving" @click="saveMenu">
                        Speichern
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="paginationDialog" max-width="420" persistent>
            <v-card rounded="xl">
                <v-card-title>Menüs pro Seite</v-card-title>

                <v-card-text>
                    <v-text-field
                        v-model="paginationDialogValue"
                        label="Anzahl"
                        type="number"
                        min="1"
                        max="200"
                        variant="outlined"
                        density="comfortable"
                        autofocus />
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closePaginationDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" @click="savePaginationDialog">
                        Speichern
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog" max-width="440" persistent>
            <v-card rounded="xl">
                <v-card-title>Löschen bestätigen</v-card-title>

                <v-card-text>
                    <div class="text-body-1">
                        Soll <strong>{{ pendingDeleteMenu?.title || 'dieses Menü' }}</strong> wirklich gelöscht werden?
                    </div>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="cancelDeleteMenu">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" @click="confirmDeleteMenu">
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
import { NEW_MENU_LABEL } from '@/pages/admin/restaurant/menuLabels'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import { useFoodStore } from '@/stores/admin/restaurant/FoodStore'
import { useMenuStore } from '@/stores/admin/restaurant/MenuStore'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'

function emptyForm() {
    return {
        title: '',
        foodIds: [],
        price: '',
        courseDraft: {
            categoryId: null,
            foodId: null,
        },
    }
}

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox },

    data() {
        return {
            dialog: false,
            deleteDialog: false,
            paginationDialog: false,
            isMenuFormValid: false,
            viewMode: 'detail',
            currentPage: 1,
            paginationDialogValue: '12',
            menuSearchQuery: '',
            foodSearch: '',
            editingMenuId: null,
            pendingDeleteMenu: null,
            form: emptyForm(),
            isSaving: false,
        }
    },

    computed: {
        ...mapState(useMenuStore, ['menus']),
        ...mapState(useFoodStore, ['foods']),
        ...mapState(useRestaurantStore, ['categories', 'userSettings', 'canManageUserSettings']),
        foodOptions() {
            return [...this.foods].sort((left, right) => String(left.title || '').localeCompare(String(right.title || ''), 'de'))
        },
        currentCourseNumber() {
            return this.form.foodIds.length + 1
        },
        availableFoodsForDraft() {
            const selectedCategoryId = Number(this.form.courseDraft.categoryId || 0)

            return this.foodOptions.filter((food) => {
                return Number(food.category?.id || 0) === selectedCategoryId
            })
        },
        filteredFoodsForDraft() {
            const normalizedSearch = String(this.foodSearch || '').trim().toLocaleLowerCase('de')

            if (normalizedSearch === '') {
                return this.availableFoodsForDraft
            }

            return this.availableFoodsForDraft.filter((food) => {
                return String(food.title || '').toLocaleLowerCase('de').includes(normalizedSearch)
            })
        },
        canAddDraftCourse() {
            return Number(this.form.courseDraft.categoryId) > 0 && Number(this.form.courseDraft.foodId) > 0
        },
        isCompactView() {
            return this.viewMode === 'compact'
        },
        isImageView() {
            return this.viewMode === 'image'
        },
        cardColumnMd() {
            return this.isCompactView ? 4 : 6
        },
        cardColumnXl() {
            return this.isCompactView ? 3 : 4
        },
        currentPaginationNumber() {
            const value = Number(this.userSettings?.restaurant_foods_pagination_number || 0)

            return Number.isFinite(value) && value > 0 ? Math.min(200, Math.round(value)) : 12
        },
        newMenuLabel() {
            return NEW_MENU_LABEL
        },
        filteredMenus() {
            const normalizedSearch = this.normalizeSearchValue(this.menuSearchQuery)

            return this.menus.filter((menu) => {
                if (normalizedSearch === '') {
                    return true
                }

                const searchSegments = [
                    menu.title,
                    ...(menu.foods || []).flatMap((food) => [food.title, food.category?.title]),
                ]

                return searchSegments.some((value) => this.normalizeSearchValue(value).includes(normalizedSearch))
            })
        },
        pageCount() {
            return Math.max(1, Math.ceil(this.filteredMenus.length / this.currentPaginationNumber))
        },
        paginatedMenus() {
            const start = (this.currentPage - 1) * this.currentPaginationNumber
            const end = start + this.currentPaginationNumber

            return this.filteredMenus.slice(start, end)
        },
        paginationSummary() {
            if (! this.filteredMenus.length) {
                return '0 - 0 von 0'
            }

            const from = (this.currentPage - 1) * this.currentPaginationNumber + 1
            const to = Math.min(this.currentPage * this.currentPaginationNumber, this.filteredMenus.length)

            return `${from} - ${to} von ${this.filteredMenus.length}`
        },
    },

    watch: {
        currentPaginationNumber: {
            immediate: true,
            handler() {
                this.paginationDialogValue = String(this.currentPaginationNumber)
                this.clampCurrentPage()
            },
        },
        menus() {
            this.clampCurrentPage()
        },
        menuSearchQuery() {
            this.currentPage = 1
        },
    },

    methods: {
        normalizeSearchValue(value) {
            return String(value || '').trim().toLocaleLowerCase('de')
        },
        clampCurrentPage() {
            this.currentPage = Math.min(Math.max(1, this.currentPage), this.pageCount)
        },
        normalizePaginationNumber(value) {
            const normalized = Math.max(1, Math.min(200, Math.round(Number(value))))

            return Number.isFinite(normalized) && normalized > 0 ? normalized : this.currentPaginationNumber
        },
        openPaginationDialog() {
            this.paginationDialogValue = String(this.currentPaginationNumber)
            this.paginationDialog = true
        },
        closePaginationDialog() {
            this.paginationDialog = false
            this.paginationDialogValue = String(this.currentPaginationNumber)
        },
        async savePaginationDialog() {
            const normalized = this.normalizePaginationNumber(this.paginationDialogValue)

            if (! this.canManageUserSettings || normalized === this.currentPaginationNumber) {
                this.closePaginationDialog()
                this.clampCurrentPage()
                return
            }

            await useRestaurantStore().updateUserSettings(normalized)
            this.closePaginationDialog()
            this.clampCurrentPage()
        },
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
        orderedMenuFoods(foods) {
            return [...(foods || [])].sort((left, right) => Number(left.course_number || 0) - Number(right.course_number || 0))
        },
        isDraftCategorySelected(categoryId) {
            return Number(this.form.courseDraft.categoryId) === Number(categoryId)
        },
        selectDraftCategory(categoryId) {
            const normalizedCategoryId = Number(categoryId)

            if (! Number.isFinite(normalizedCategoryId) || normalizedCategoryId <= 0) {
                this.resetCourseDraft()
                return
            }

            if (this.isDraftCategorySelected(normalizedCategoryId)) {
                this.resetCourseDraft()
                return
            }

            this.form.courseDraft = {
                categoryId: normalizedCategoryId,
                foodId: null,
            }
            this.foodSearch = ''
        },
        isDraftFoodSelected(foodId) {
            return Number(this.form.courseDraft.foodId) === Number(foodId)
        },
        isFoodAlreadyAssigned(foodId) {
            return this.form.foodIds.some((value) => Number(value) === Number(foodId))
        },
        selectDraftFood(foodId) {
            const normalizedFoodId = Number(foodId)

            if (! Number.isFinite(normalizedFoodId) || normalizedFoodId <= 0 || this.isFoodAlreadyAssigned(normalizedFoodId)) {
                return
            }

            if (this.isDraftFoodSelected(normalizedFoodId)) {
                this.form.courseDraft.foodId = null
                return
            }

            this.form.courseDraft.foodId = normalizedFoodId
        },
        appendDraftCourse() {
            if (! this.canAddDraftCourse || this.isFoodAlreadyAssigned(this.form.courseDraft.foodId)) {
                return
            }

            this.form.foodIds = [...this.form.foodIds, this.form.courseDraft.foodId]
            this.resetCourseDraft()
        },
        resetCourseDraft() {
            this.form.courseDraft = {
                categoryId: null,
                foodId: null,
            }
            this.foodSearch = ''
        },
        removeSelectedFood(foodId) {
            this.form.foodIds = this.form.foodIds.filter((value) => Number(value) !== Number(foodId))
        },
        moveSelectedFood(index, direction) {
            const targetIndex = index + direction
            if (targetIndex < 0 || targetIndex >= this.form.foodIds.length) {
                return
            }

            const reorderedFoodIds = [...this.form.foodIds]
            const [movedFoodId] = reorderedFoodIds.splice(index, 1)
            reorderedFoodIds.splice(targetIndex, 0, movedFoodId)
            this.form.foodIds = reorderedFoodIds
        },
        selectedFoodTitle(foodId) {
            return this.foodOptions.find((food) => Number(food.id) === Number(foodId))?.title || `Speise #${foodId}`
        },
        selectedFoodCategoryTitle(foodId) {
            return this.foodOptions.find((food) => Number(food.id) === Number(foodId))?.category?.title || ''
        },
        openCreateDialog() {
            this.editingMenuId = null
            this.form = emptyForm()
            this.isMenuFormValid = false
            this.foodSearch = ''
            this.dialog = true
        },
        openEditDialog(menu) {
            this.editingMenuId = menu.id
            this.form = {
                title: menu.title || '',
                foodIds: this.orderedMenuFoods(menu.foods || []).map((food) => food.id),
                price: this.normalizePriceInput(menu.price),
                courseDraft: {
                    categoryId: null,
                    foodId: null,
                },
            }
            this.isMenuFormValid = false
            this.foodSearch = ''
            this.dialog = true
        },
        closeDialog() {
            this.dialog = false
            this.editingMenuId = null
            this.isMenuFormValid = false
            this.form = emptyForm()
            this.foodSearch = ''
        },
        requestDeleteMenu(menu) {
            this.pendingDeleteMenu = menu
            this.deleteDialog = true
        },
        cancelDeleteMenu() {
            this.deleteDialog = false
            this.pendingDeleteMenu = null
        },
        setViewMode(mode) {
            if (! ['detail', 'image', 'compact'].includes(mode)) {
                return
            }

            this.viewMode = mode
        },
        async reloadMenus() {
            await Promise.all([useMenuStore().index(), useFoodStore().index(), useRestaurantStore().loadSettings()])
        },
        async saveMenu() {
            if (this.isSaving) {
                return
            }

            this.isMenuFormValid = false
            await this.$refs.menuForm?.validate()

            if (! this.isMenuFormValid) {
                return
            }

            this.isSaving = true

            const payload = {
                title: this.form.title,
                food_ids: this.form.foodIds,
                price: this.normalizePricePayload(this.form.price),
            }

            const menuStore = useMenuStore()
            const restaurantStore = useRestaurantStore()
            const result = this.editingMenuId
                ? await menuStore.update(this.editingMenuId, payload)
                : await menuStore.store(payload)

            if (result) {
                await restaurantStore.loadSettings()
                this.closeDialog()
            }

            this.isSaving = false
        },
        async confirmDeleteMenu() {
            if (! this.pendingDeleteMenu) {
                return
            }

            const deleted = await useMenuStore().destroy(this.pendingDeleteMenu.id)
            if (deleted) {
                await useRestaurantStore().loadSettings()
                this.cancelDeleteMenu()
            }
        },
    },
}
</script>

<style scoped>
.menu-card {
    display: flex;
    flex-direction: column;
    border-color: rgba(15, 23, 42, 0.1);
    background: rgba(255, 255, 255, 0.96);
}

.menu-card--compact {
    background: rgba(255, 255, 255, 0.98);
}

.menu-card :deep(.v-card-text) {
    flex: 1 1 auto;
}

.menu-card__summary-actions {
    margin-left: auto;
}

.menu-card__summary-actions :deep(button) {
    min-width: 0;
    width: 1.9rem;
    height: 1.9rem;
}

.menu-course-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.menu-course-row--image {
    align-items: flex-start;
    flex-wrap: nowrap;
    padding: 0.65rem 0.75rem;
    border: 1px solid rgba(148, 163, 184, 0.16);
    border-radius: 1rem;
    background: rgba(248, 250, 252, 0.78);
}

.menu-course-row__image {
    flex: 0 0 auto;
}

.menu-course-row__image-element {
    border-radius: 0.9rem;
    background: rgba(226, 232, 240, 0.72);
}

.menu-course-row__content {
    display: flex;
    flex: 1 1 auto;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.menu-pagination {
    border-top: 1px solid rgba(148, 163, 184, 0.18);
    padding-top: 1rem;
}

.menu-pagination__selector {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.7rem 0.85rem;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 1rem;
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.96), rgba(241, 245, 249, 0.88));
    box-shadow: 0 10px 24px -20px rgba(15, 23, 42, 0.4);
}

.menu-pagination__label {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: rgba(71, 85, 105, 0.92);
}

.menu-pagination__counter {
    min-width: 3.2rem;
    font-weight: 700;
}

.menu-search-panel {
    padding: 1rem 1.05rem;
    border: 1px solid rgba(14, 116, 144, 0.12);
    border-radius: 1.1rem;
    background:
        radial-gradient(circle at top left, rgba(224, 242, 254, 0.78), transparent 36%),
        linear-gradient(135deg, rgba(248, 250, 252, 0.98), rgba(241, 245, 249, 0.92));
    box-shadow: 0 16px 32px -28px rgba(15, 23, 42, 0.42);
}

.menu-search-panel__field {
    max-width: 28rem;
}

.menu-course-builder {
    padding: 1rem 1.05rem 1.05rem;
    border: 1px solid rgba(14, 116, 144, 0.14);
    border-radius: 1.1rem;
    background:
        radial-gradient(circle at top left, rgba(224, 242, 254, 0.9), transparent 38%),
        linear-gradient(135deg, rgba(248, 250, 252, 0.98), rgba(241, 245, 249, 0.92));
    box-shadow: 0 16px 34px -28px rgba(15, 23, 42, 0.5);
}

.menu-course-builder__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.menu-category-chip-list,
.menu-food-chip-list {
    width: 100%;
}

.menu-category-chip {
    border-radius: 999px;
    border: 1px solid rgba(14, 116, 144, 0.16);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.94));
    box-shadow: 0 12px 26px -22px rgba(15, 23, 42, 0.42);
    color: rgb(15, 23, 42);
    font-weight: 600;
    letter-spacing: 0.01em;
    padding-inline: 0.7rem;
}

.menu-category-chip--selected {
    border-color: rgba(14, 116, 144, 0.88);
    background: linear-gradient(135deg, rgb(8, 145, 178), rgb(14, 116, 144));
    color: rgb(255, 255, 255);
    box-shadow: 0 18px 32px -22px rgba(14, 116, 144, 0.52);
}

.menu-food-chip {
    border-radius: 999px;
    border-color: rgba(15, 23, 42, 0.16);
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 10px 24px -18px rgba(15, 23, 42, 0.3);
}

.menu-food-chip-scroll {
    max-height: 14.5rem;
    overflow-y: auto;
    padding: 0.15rem 0.1rem 0.35rem;
}

.menu-food-chip--selected {
    border-color: rgba(180, 83, 9, 0.8);
    background: rgba(255, 237, 213, 0.96);
}

.menu-food-chip--disabled {
    opacity: 0.5;
    box-shadow: none;
}

.menu-selected-course {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding: 0.7rem 0.85rem;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 1rem;
    background: rgba(248, 250, 252, 0.84);
}
</style>
