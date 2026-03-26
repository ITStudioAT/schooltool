<template>
    <div class="mpe-page">
        <v-container fluid class="ma-0 w-100 pa-2">
            <AdminSectionHero
                class="mb-3"
                eyebrow="Restaurant"
                title="Menüpläne"
                :active-section="headerActiveSection"
                :chips="headerChips"
                :show-current-user-chip="true"
                focus-label="Seitenstatus"
                primary-color="#213547"
                secondary-color="#b45309"
                left-orb-color="#fde68a"
                right-orb-color="#fdba74" />

            <v-row dense>
                <v-col cols="12">
                    <v-sheet rounded="xl" class="mpe-stage pa-5">

                        <!-- Dark gradient header -->
                        <div class="mpe-header">
                            <div class="mpe-header__left">
                                <div class="mpe-header__eyebrow">{{ entryModeLabel }}</div>
                                <h2 class="mpe-header__title">{{ entryHeadline }}</h2>
                                <div class="mpe-header__range">{{ rangeLabel }}</div>
                            </div>
                            <div class="mpe-header__right">
                                <div class="mpe-badge" data-testid="plan-progress-badge">
                                    <strong>{{ coveragePercent }}%</strong>
                                    <span>fertig</span>
                                </div>
                                <div class="mpe-header__actions">
                                    <v-btn
                                        color="primary"
                                        rounded="xl"
                                        variant="flat"
                                        prepend-icon="mdi-content-save-outline"
                                        :loading="isSaving"
                                        @click="savePlan">
                                        Speichern
                                    </v-btn>
                                    <v-btn
                                        v-if="printHref"
                                        color="white"
                                        rounded="xl"
                                        variant="tonal"
                                        icon="mdi-printer-outline"
                                        :loading="isPrinting"
                                        :disabled="isPrinting"
                                        aria-label="Menüplan als PDF drucken"
                                        title="Menüplan als PDF drucken"
                                        data-testid="print-menu-plan-button"
                                        @click="downloadPlanPdf" />
                                    <v-btn
                                        color="white"
                                        rounded="xl"
                                        variant="tonal"
                                        prepend-icon="mdi-arrow-left"
                                        :to="backTarget">
                                        Zurück
                                    </v-btn>
                                </div>
                            </div>
                        </div>

                        <!-- Plan title -->
                        <div class="mpe-plan-title-row">
                            <v-text-field
                                v-model="planTitle"
                                label="Planbezeichnung (optional)"
                                variant="outlined"
                                density="comfortable"
                                hide-details
                                placeholder="z. B. Frühlingswoche" />
                        </div>

                        <!-- Summary stats strip -->
                        <div class="mpe-summary">
                            <div class="mpe-stat">
                                <v-icon icon="mdi-calendar-week" size="15" class="mpe-stat__icon" />
                                <span class="mpe-stat__label">Tage</span>
                                <strong class="mpe-stat__value">{{ planDays.length || 0 }}</strong>
                            </div>
                            <div class="mpe-stat">
                                <v-icon icon="mdi-silverware" size="15" class="mpe-stat__icon" />
                                <span class="mpe-stat__label">Mit Menü</span>
                                <strong class="mpe-stat__value">{{ filledDayCount }}</strong>
                            </div>
                            <div class="mpe-stat">
                                <v-icon icon="mdi-clock-outline" size="15" class="mpe-stat__icon" />
                                <span class="mpe-stat__label">Offen</span>
                                <strong class="mpe-stat__value">{{ openDayCount }}</strong>
                            </div>
                            <div v-if="freeDayCount > 0" class="mpe-stat mpe-stat--free">
                                <v-icon icon="mdi-calendar-remove-outline" size="15" class="mpe-stat__icon" />
                                <span class="mpe-stat__label">Frei</span>
                                <strong class="mpe-stat__value">{{ freeDayCount }}</strong>
                            </div>
                            <div
                                v-if="canToggleAvailability"
                                class="mpe-stat mpe-stat--availability"
                                data-testid="availability-card">
                                <div class="mpe-stat__availability-header">
                                    <v-icon icon="mdi-eye-check-outline" size="15" class="mpe-stat__icon" />
                                    <span class="mpe-stat__label">Status</span>
                                </div>
                                <button
                                    type="button"
                                    class="mpe-availability-toggle"
                                    :class="{ 'is-active': isPlanAvailable }"
                                    :aria-pressed="isPlanAvailable ? 'true' : 'false'"
                                    data-testid="availability-toggle"
                                    @click="togglePlanAvailability">
                                    Verfügbar
                                </button>
                            </div>
                        </div>

                        <!-- Week Board -->
                        <section v-if="planDays.length" class="mpe-board">
                            <article
                                v-for="day in planDays"
                                :key="day.iso"
                                class="mpe-day"
                                :class="day.isFreeDay ? 'mpe-day--free' : dayEntries(day.iso).length ? 'mpe-day--filled' : 'mpe-day--empty'"
                                :data-testid="`plan-day-${day.iso}`">

                                <header class="mpe-day__header">
                                    <div>
                                        <div class="mpe-day__weekday">{{ day.weekdayLabel }}</div>
                                        <div class="mpe-day__date">{{ day.dateLabel }}</div>
                                    </div>
                                    <div
                                        class="mpe-day__status"
                                        :class="day.isFreeDay ? 'is-free' : dayEntries(day.iso).length ? 'is-filled' : 'is-empty'">
                                        <v-icon
                                            :icon="day.isFreeDay ? 'mdi-leaf' : dayEntries(day.iso).length ? 'mdi-check' : 'mdi-clock-outline'"
                                            size="11"
                                            class="mr-1" />
                                        {{ day.isFreeDay ? 'Frei' : dayEntries(day.iso).length ? `${dayEntries(day.iso).length} Menü(s)` : 'Offen' }}
                                    </div>
                                </header>

                                <!-- Free day -->
                                <div v-if="day.isFreeDay" class="mpe-free-card">
                                    <v-icon icon="mdi-calendar-remove-outline" size="36" class="mpe-free-card__icon" />
                                    <p class="mpe-free-card__text">Freier Tag</p>
                                </div>

                                <!-- Assigned menus + add button -->
                                <div v-else class="mpe-day__body">
                                    <!-- Each assigned menu entry -->
                                    <div
                                        v-for="(entry, entryIndex) in dayEntries(day.iso)"
                                        :key="entry._key"
                                        class="mpe-entry-card">
                                        <div class="mpe-entry-card__header">
                                            <div class="mpe-entry-card__title">{{ entry.menuTitle || entry.menu.title }}</div>
                                        </div>
                                        <div class="mpe-entry-card__actions">
                                            <v-btn
                                                icon="mdi-arrow-up"
                                                size="x-small"
                                                variant="text"
                                                :disabled="entryIndex === 0"
                                                @click="moveEntry(day.iso, entryIndex, -1)" />
                                            <v-btn
                                                icon="mdi-arrow-down"
                                                size="x-small"
                                                variant="text"
                                                :disabled="entryIndex === dayEntries(day.iso).length - 1"
                                                @click="moveEntry(day.iso, entryIndex, 1)" />
                                            <v-btn
                                                icon="mdi-delete"
                                                size="x-small"
                                                variant="text"
                                                color="warning"
                                                @click="requestDeleteEntry(day.iso, entry._key)" />
                                            <v-btn
                                                icon="mdi-pencil"
                                                size="x-small"
                                                variant="text"
                                                color="primary"
                                                @click="openEditEntryDialog(day.iso, entry._key)" />
                                            <v-btn
                                                icon="mdi-eye-outline"
                                                size="x-small"
                                                variant="text"
                                                color="primary"
                                                :data-testid="`preview-menu-${day.iso}-${entry._key}`"
                                                @click="openEntryPreviewDialog(day.iso, entry._key)" />
                                        </div>
                                        <div v-if="entry.menu.foods && entry.menu.foods.length" class="mpe-entry-card__foods">
                                            <span
                                                v-for="food in sortedFoods(entry.menu.foods)"
                                                :key="food.id"
                                                class="mpe-entry-card__food">
                                                {{ food.title }}
                                            </span>
                                        </div>
                                        <div v-if="entryEffectivePrice(entry) != null" class="mpe-entry-card__base-price">
                                            Preis: {{ formatPrice(entryEffectivePrice(entry)) }}
                                        </div>
                                        <div v-if="entryHasPriceOverride(entry) && entry.menu.price != null" class="mpe-entry-card__price-note">
                                            Basispreis: {{ formatPrice(entry.menu.price) }}
                                        </div>

                                        <div v-if="entryActiveEatingTimeLabels(entry).length" class="mpe-eating-times mpe-eating-times--active mt-2">
                                            <div class="mpe-eating-times__label">Aktiv:</div>
                                            <div
                                                class="mpe-eating-times__summary"
                                                :data-testid="`entry-active-times-${day.iso}-${entry._key}`">
                                                {{ entryActiveEatingTimeLabels(entry).join(', ') }}
                                            </div>
                                        </div>

                                        <!-- Eating time toggles -->
                                        <div v-if="eatingTimes.length" class="mpe-eating-times mt-2">
                                            <div class="mpe-eating-times__label">Speisezeiten:</div>
                                            <div class="d-flex flex-wrap ga-1 mt-1">
                                                <v-chip
                                                    v-for="et in eatingTimes"
                                                    :key="et.id"
                                                    size="small"
                                                    :color="isTimeActive(entry, et.id) ? 'primary' : undefined"
                                                    :variant="isTimeActive(entry, et.id) ? 'flat' : 'outlined'"
                                                    class="mpe-time-chip"
                                                    @click="toggleTime(entry, et.id)">
                                                    {{ et.eating_time }} Uhr
                                                </v-chip>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Inline search panel -->
                                    <div v-if="searchOpen(day.iso)" class="mpe-search-panel">
                                        <div class="d-flex gap-2 mb-2">
                                            <v-btn
                                                size="small"
                                                color="primary"
                                                variant="tonal"
                                                prepend-icon="mdi-plus"
                                                @click="openCreateMenuDialog(day.iso)">
                                                {{ newMenuLabel }}
                                            </v-btn>
                                            <v-btn
                                                size="small"
                                                variant="text"
                                                color="secondary"
                                                @click="closeSearch(day.iso)">
                                                Abbrechen
                                            </v-btn>
                                        </div>
                                        <v-text-field
                                            :ref="`search-${day.iso}`"
                                            v-model="searchQueryFor(day.iso).query"
                                            label="Menü suchen"
                                            prepend-inner-icon="mdi-magnify"
                                            variant="outlined"
                                            density="compact"
                                            hide-details
                                            clearable
                                            autofocus
                                            class="mb-2"
                                            @keydown.esc="closeSearch(day.iso)" />

                                        <div v-if="filteredMenusFor(day.iso).length" class="mpe-search-results">
                                            <button
                                                v-for="menu in filteredMenusFor(day.iso)"
                                                :key="menu.id"
                                                type="button"
                                                class="mpe-search-result"
                                                @click="addEntryForDay(day.iso, menu)">
                                                <div class="mpe-search-result__top">
                                                    <div class="mpe-search-result__title">{{ menu.title }}</div>
                                                    <div v-if="menu.price != null" class="mpe-search-result__price">{{ formatPrice(menu.price) }}</div>
                                                </div>
                                                <div v-if="menu.foods && menu.foods.length" class="mpe-search-result__foods">
                                                    <span
                                                        v-for="food in sortedFoods(menu.foods)"
                                                        :key="food.id"
                                                        class="mpe-search-result__food">
                                                        {{ food.title }}
                                                    </span>
                                                </div>
                                            </button>
                                        </div>
                                        <div v-else class="mpe-search-empty">
                                            <span>Kein passendes Menü gefunden.</span>
                                        </div>
                                    </div>

                                    <!-- Add menu button -->
                                    <v-btn
                                        v-if="!searchOpen(day.iso)"
                                        size="small"
                                        color="primary"
                                        rounded="xl"
                                        variant="tonal"
                                        prepend-icon="mdi-plus"
                                        class="mpe-add-btn"
                                        @click="openSearch(day.iso)">
                                        Menü hinzufügen
                                    </v-btn>
                                </div>
                            </article>
                        </section>

                        <section v-else class="mpe-no-range" data-testid="plan-days-empty">
                            <v-icon icon="mdi-calendar-question" size="44" color="grey-lighten-1" />
                            <strong>Kein gültiger Zeitraum</strong>
                            <span>Diese Ansicht benötigt Start- und Enddatum.</span>
                        </section>
                    </v-sheet>
                </v-col>
            </v-row>
        </v-container>

        <!-- Create new menu dialog -->
        <v-dialog v-model="createMenuDialog" max-width="820" persistent>
            <v-card rounded="xl">
                <v-card-title class="d-flex align-center">
                    <span>{{ newMenuLabel }}</span>
                    <v-spacer />
                    <v-btn icon="mdi-close" variant="text" @click="closeCreateMenuDialog" />
                </v-card-title>

                <v-card-text>
                    <v-form ref="createMenuForm" v-model="isCreateMenuFormValid" @submit.prevent="saveNewMenu">
                        <v-row dense>
                            <v-col cols="12" md="8">
                                <v-text-field
                                    v-model="newMenuForm.title"
                                    label="Titel"
                                    variant="outlined"
                                    density="comfortable"
                                    :rules="[required()]"
                                    autofocus />
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="newMenuForm.price"
                                    label="Preis"
                                    type="text"
                                    inputmode="decimal"
                                    placeholder="z. B. 9,5"
                                    variant="outlined"
                                    density="comfortable"
                                    @blur="normalizeNewMenuPrice" />
                            </v-col>

                            <v-col cols="12">
                                <div class="menu-course-builder">
                                    <div class="menu-course-builder__header">
                                        <div>
                                            <div class="text-subtitle-1 font-weight-bold">Gang {{ currentNewMenuCourseNumber }}</div>
                                            <div class="text-body-2 text-medium-emphasis">
                                                Lege jeden Gang nacheinander an: zuerst Kategorie, dann Speise.
                                            </div>
                                        </div>

                                        <v-chip color="secondary" variant="tonal">
                                            {{ newMenuForm.foodIds.length }} gewählt
                                        </v-chip>
                                    </div>

                                    <div class="text-subtitle-2 mb-2">1. Kategorie wählen</div>
                                    <div v-if="categories.length" class="d-flex flex-wrap ga-2 menu-category-chip-list">
                                        <v-chip
                                            v-for="category in categories"
                                            :key="`new-menu-category-${category.id}`"
                                            class="menu-category-chip"
                                            :class="{ 'menu-category-chip--selected': isNewMenuDraftCategorySelected(category.id) }"
                                            variant="outlined"
                                            @click="selectNewMenuDraftCategory(category.id)">
                                            {{ category.title }}
                                        </v-chip>
                                    </div>
                                    <v-alert v-else type="warning" variant="tonal">
                                        Bitte zuerst unter Einstellungen Kategorien anlegen.
                                    </v-alert>

                                    <div class="text-subtitle-2 mb-2 mt-4">2. Speise wählen</div>
                                    <div v-if="!newMenuForm.courseDraft.categoryId" class="text-body-2 text-medium-emphasis">
                                        Wähle zuerst eine Kategorie für Gang {{ currentNewMenuCourseNumber }}.
                                    </div>
                                    <div v-else-if="availableFoodsForNewMenu.length">
                                        <v-text-field
                                            v-model="newMenuFoodSearch"
                                            label="Speise suchen"
                                            prepend-inner-icon="mdi-magnify"
                                            variant="outlined"
                                            density="comfortable"
                                            clearable
                                            class="mb-3" />

                                        <div v-if="filteredFoodsForNewMenu.length" class="menu-food-chip-list">
                                            <div class="menu-food-chip-scroll">
                                                <div class="d-flex flex-wrap ga-2">
                                                    <v-chip
                                                        v-for="food in filteredFoodsForNewMenu"
                                                        :key="`new-menu-food-option-${food.id}`"
                                                        class="menu-food-chip"
                                                        :class="{
                                                            'menu-food-chip--selected': isNewMenuDraftFoodSelected(food.id),
                                                            'menu-food-chip--disabled': isNewMenuFoodAlreadyAssigned(food.id),
                                                        }"
                                                        variant="outlined"
                                                        :disabled="isNewMenuFoodAlreadyAssigned(food.id)"
                                                        @click="selectNewMenuDraftFood(food.id)">
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
                                            :disabled="!canAddNewMenuDraftCourse"
                                            @click="appendNewMenuDraftCourse">
                                            Gang {{ currentNewMenuCourseNumber }} hinzufügen
                                        </v-btn>
                                    </div>
                                </div>
                            </v-col>

                            <v-col cols="12">
                                <div class="text-subtitle-2 mb-2">Menüfolge</div>
                                <v-alert v-if="!newMenuForm.foodIds.length" type="info" variant="tonal">
                                    Noch kein Gang angelegt. Beginne mit Gang 1.
                                </v-alert>

                                <div v-else class="d-flex flex-column ga-2">
                                    <div
                                        v-for="(foodId, index) in newMenuForm.foodIds"
                                        :key="`selected-new-menu-food-${foodId}`"
                                        class="menu-selected-course">
                                        <div class="d-flex align-center ga-2">
                                            <v-chip size="small" color="primary" variant="flat">
                                                Gang {{ index + 1 }}
                                            </v-chip>
                                            <div>
                                                <div class="font-weight-medium">{{ selectedNewMenuFoodTitle(foodId) }}</div>
                                                <div v-if="selectedNewMenuFoodCategoryTitle(foodId)" class="text-caption text-medium-emphasis">
                                                    {{ selectedNewMenuFoodCategoryTitle(foodId) }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex align-center ga-1">
                                            <v-btn
                                                icon="mdi-arrow-up"
                                                size="x-small"
                                                variant="text"
                                                :disabled="index === 0"
                                                @click="moveNewMenuSelectedFood(index, -1)" />
                                            <v-btn
                                                icon="mdi-arrow-down"
                                                size="x-small"
                                                variant="text"
                                                :disabled="index === newMenuForm.foodIds.length - 1"
                                                @click="moveNewMenuSelectedFood(index, 1)" />
                                            <v-btn
                                                icon="mdi-delete-outline"
                                                size="x-small"
                                                variant="text"
                                                color="error"
                                                @click="removeNewMenuSelectedFood(foodId)" />
                                        </div>
                                    </div>
                                </div>
                            </v-col>
                        </v-row>
                    </v-form>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closeCreateMenuDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" :loading="isCreatingMenu" @click="saveNewMenu">
                        Speichern
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="entryEditDialog" max-width="520" persistent>
            <v-card rounded="xl">
                <v-card-title class="d-flex align-center">
                    <span>Eintrag bearbeiten</span>
                    <v-spacer />
                    <v-btn icon="mdi-close" variant="text" @click="closeEditEntryDialog" />
                </v-card-title>

                <v-card-text>
                    <v-text-field
                        v-model="entryEditForm.menuTitle"
                        label="Menü"
                        variant="outlined"
                        density="comfortable"
                        class="mb-3" />

                    <v-text-field
                        v-model="entryEditForm.price"
                        label="Preis im Menüplan"
                        type="text"
                        inputmode="decimal"
                        placeholder="Leer lassen, um Menüpreis zu übernehmen"
                        variant="outlined"
                        density="comfortable"
                        clearable
                        @blur="normalizeEntryEditPriceField" />

                    <v-textarea
                        v-model="entryEditForm.comments"
                        label="Kommentare"
                        variant="outlined"
                        density="comfortable"
                        rows="3"
                        auto-grow
                        class="mt-3" />
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closeEditEntryDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" @click="saveEntryEdit">
                        Speichern
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="entryPreviewDialog" max-width="960" persistent data-testid="entry-preview-dialog">
            <v-card rounded="xl">
                <v-card-title class="d-flex align-center">
                    <div>
                        <div class="text-overline">Menü-Details</div>
                        <div>{{ entryPreviewEntry?.menuTitle || entryPreviewEntry?.menu?.title || 'Menü' }}</div>
                    </div>
                    <v-spacer />
                    <v-btn icon="mdi-close" variant="text" @click="closeEntryPreviewDialog" />
                </v-card-title>

                <v-card-text v-if="entryPreviewEntry" class="mpe-preview">
                    <div class="mpe-preview__meta">
                        <div class="mpe-preview__meta-item">
                            <span class="mpe-preview__meta-label">Tag</span>
                            <strong>{{ formatDate(entryPreviewTarget.iso) }}</strong>
                        </div>
                        <div class="mpe-preview__meta-item" v-if="entryEffectivePrice(entryPreviewEntry) != null">
                            <span class="mpe-preview__meta-label">Preis im Plan</span>
                            <strong>{{ formatPrice(entryEffectivePrice(entryPreviewEntry)) }}</strong>
                        </div>
                        <div class="mpe-preview__meta-item" v-if="entryHasPriceOverride(entryPreviewEntry) && entryPreviewEntry.menu?.price != null">
                            <span class="mpe-preview__meta-label">Basispreis</span>
                            <strong>{{ formatPrice(entryPreviewEntry.menu.price) }}</strong>
                        </div>
                        <div class="mpe-preview__meta-item">
                            <span class="mpe-preview__meta-label">Gänge</span>
                            <strong>{{ sortedFoods(entryPreviewEntry.menu?.foods || []).length }}</strong>
                        </div>
                    </div>

                    <div v-if="sortedFoods(entryPreviewEntry.menu?.foods || []).length" class="mpe-preview__foods">
                        <article
                            v-for="food in sortedFoods(entryPreviewEntry.menu?.foods || [])"
                            :key="food.id"
                            class="mpe-preview-food"
                            :class="{ 'mpe-preview-food--no-media': !food.food_image_url }"
                            :data-testid="`preview-food-${food.id}`">
                            <div class="mpe-preview-food__media" v-if="food.food_image_url">
                                <v-img :src="food.food_image_url" cover height="140" />
                            </div>

                            <div class="mpe-preview-food__body">
                                <div class="mpe-preview-food__course-row">
                                    <div class="mpe-preview-food__course">Gang {{ food.course_number || '-' }}</div>
                                </div>

                                <div class="mpe-preview-food__head">
                                    <h3 class="mpe-preview-food__title">{{ food.title }}</h3>
                                    <v-chip v-if="food.category?.title" size="small" variant="tonal" color="secondary">
                                        {{ food.category.title }}
                                    </v-chip>
                                </div>

                                <div class="mpe-preview-food__price" v-if="food.price != null">
                                    Einzelpreis: {{ formatPrice(food.price) }}
                                </div>

                                <div class="mpe-preview-food__description" v-if="food.description">
                                    {{ food.description }}
                                </div>

                                <div v-if="food.allergens?.length" class="mpe-preview-food__section">
                                    <div class="mpe-preview-food__label">Allergene</div>
                                    <div class="mpe-preview-food__chips">
                                        <v-chip
                                            v-for="allergen in food.allergens"
                                            :key="`${food.id}-allergen-${allergen}`"
                                            size="small"
                                            variant="outlined">
                                            {{ allergenLabel(allergen) }}
                                        </v-chip>
                                    </div>
                                </div>

                                <div v-if="food.ingredient_icons?.length" class="mpe-preview-food__section">
                                    <div class="mpe-preview-food__label">Symbole</div>
                                    <div class="mpe-preview-food__icon-list">
                                        <div
                                            v-for="icon in food.ingredient_icons"
                                            :key="`${food.id}-icon-${icon.id}`"
                                            class="mpe-preview-food__icon">
                                            <v-avatar size="28" class="mpe-preview-food__icon-avatar">
                                                <v-img v-if="icon.image_url" :src="icon.image_url" />
                                                <span v-else>{{ shortLabel(icon.title) }}</span>
                                            </v-avatar>
                                            <span>{{ icon.title }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div v-if="entryPreviewEntry?.comments" class="mpe-preview-food__section">
                        <div class="mpe-preview-food__label">Kommentar</div>
                        <div class="mpe-preview-food__description">{{ entryPreviewEntry.comments }}</div>
                    </div>

                    <v-alert v-if="!sortedFoods(entryPreviewEntry.menu?.foods || []).length" type="info" variant="tonal">
                        Für dieses Menü wurden noch keine Speisen hinterlegt.
                    </v-alert>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn color="primary" variant="flat" @click="closeEntryPreviewDialog">
                        Schließen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteEntryDialog" max-width="460" persistent>
            <v-card rounded="xl">
                <v-card-title>Eintrag löschen</v-card-title>

                <v-card-text>
                    <div class="text-body-1">
                        Soll <strong>{{ deleteEntryMenuTitle() || 'dieser Menüeintrag' }}</strong> wirklich aus dem Menüplan entfernt werden?
                    </div>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="cancelDeleteEntry">Abbrechen</v-btn>
                    <v-btn color="warning" variant="flat" @click="confirmDeleteEntry">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import { mapState } from 'pinia'
import { useValidationRulesSetup } from '@/helpers/rules'
import { NEW_MENU_LABEL } from '@/pages/admin/restaurant/menuLabels'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useFoodStore } from '@/stores/admin/restaurant/FoodStore'
import { useFreeDayStore } from '@/stores/admin/restaurant/FreeDayStore'
import { useMenuPlanStore } from '@/stores/admin/restaurant/MenuPlanStore'
import { useMenuStore } from '@/stores/admin/restaurant/MenuStore'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'
import { useEatingTimeStore } from '@/stores/admin/restaurant/EatingTimeStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

function isValidIsoDate(value) {
    return /^\d{4}-\d{2}-\d{2}$/.test(String(value || ''))
}

function emptyNewMenuForm() {
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

let keyCounter = 0

function newEntryKey() {
    return `entry-${++keyCounter}`
}

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { AdminSectionHero },

    data() {
        return {
            newMenuLabel: NEW_MENU_LABEL,
            planTitle: '',
            isPlanAvailable: false,
            entriesByDate: {},   // { iso: [{ _key, menu, menuTitle, price, comments, eatingTimeIds, eatingTimes }] }
            searchStates: {},    // { iso: { open: bool, query: '' } }
            isSaving: false,
            isPrinting: false,
            createMenuDialog: false,
            createMenuForDate: null,
            editingMenuId: null,
            isCreateMenuFormValid: false,
            isCreatingMenu: false,
            entryEditDialog: false,
            entryPreviewDialog: false,
            deleteEntryDialog: false,
            entryPreviewTarget: {
                iso: '',
                key: '',
            },
            entryEditTarget: {
                iso: '',
                key: '',
            },
            deleteEntryTarget: {
                iso: '',
                key: '',
            },
            entryEditForm: {
                menuTitle: '',
                price: '',
                comments: '',
            },
            newMenuFoodSearch: '',
            newMenuForm: emptyNewMenuForm(),
        }
    },

    created() {
        this.loadInitialData()
    },

    watch: {
        '$route.query.mode': 'loadInitialData',
        '$route.query.plan_id': 'loadInitialData',
        '$route.query.start': 'loadInitialData',
        '$route.query.end': 'loadInitialData',
        openDayCount(newCount) {
            if (newCount > 0 && this.isPlanAvailable) {
                this.isPlanAvailable = false
            }
        },
    },

    computed: {
        ...mapState(useAdminStore, ['config']),
        ...mapState(useFoodStore, ['foods']),
        ...mapState(useFreeDayStore, ['freeDaysByDate']),
        ...mapState(useRestaurantStore, ['categories', 'allergenOptions']),

        menus() {
            return useMenuStore().menus
        },

        eatingTimes() {
            return useEatingTimeStore().sortedEatingTimes
        },

        newMenuFoodOptions() {
            return [...this.foods].sort((left, right) => String(left.title || '').localeCompare(String(right.title || ''), 'de'))
        },

        currentNewMenuCourseNumber() {
            return this.newMenuForm.foodIds.length + 1
        },

        availableFoodsForNewMenu() {
            const selectedCategoryId = Number(this.newMenuForm.courseDraft.categoryId || 0)

            return this.newMenuFoodOptions.filter((food) => {
                return Number(food.category?.id || 0) === selectedCategoryId
            })
        },

        filteredFoodsForNewMenu() {
            const normalizedSearch = String(this.newMenuFoodSearch || '').trim().toLocaleLowerCase('de')

            if (normalizedSearch === '') {
                return this.availableFoodsForNewMenu
            }

            return this.availableFoodsForNewMenu.filter((food) => {
                return String(food.title || '').toLocaleLowerCase('de').includes(normalizedSearch)
            })
        },

        canAddNewMenuDraftCourse() {
            return Number(this.newMenuForm.courseDraft.categoryId) > 0 && Number(this.newMenuForm.courseDraft.foodId) > 0
        },

        entryMode() {
            const mode = String(this.$route?.query?.mode || '')

            return ['create', 'edit'].includes(mode) ? mode : ''
        },

        entryModeLabel() {
            return this.entryMode === 'edit' ? 'Plan bearbeiten' : 'Neuer Plan'
        },

        entryHeadline() {
            return this.entryMode === 'edit' ? 'Menüplan' : 'Menüplan erstellen'
        },

        planId() {
            const id = parseInt(this.$route?.query?.plan_id, 10)

            return isNaN(id) ? null : id
        },

        rangeBounds() {
            const start = String(this.$route?.query?.start || '')
            const end = String(this.$route?.query?.end || '')

            if (!isValidIsoDate(start) || !isValidIsoDate(end)) {
                return null
            }

            return start <= end ? { start, end } : { start: end, end: start }
        },

        planDays() {
            if (!this.rangeBounds) {
                return []
            }

            const days = []
            let cursor = this.rangeBounds.start

            while (cursor <= this.rangeBounds.end) {
                days.push({
                    iso: cursor,
                    weekdayLabel: this.toDate(cursor).toLocaleDateString('de-AT', { weekday: 'long' }),
                    dateLabel: this.toDate(cursor).toLocaleDateString('de-AT', { day: '2-digit', month: 'long' }),
                    isFreeDay: !!this.freeDaysByDate[cursor],
                })
                cursor = this.addDaysIso(cursor, 1)
            }

            return days
        },

        filledDayCount() {
            return this.planDays.filter((day) => !day.isFreeDay && this.dayEntries(day.iso).length > 0).length
        },

        freeDayCount() {
            return this.planDays.filter((day) => day.isFreeDay).length
        },

        openDayCount() {
            return this.planDays.filter((day) => !day.isFreeDay && this.dayEntries(day.iso).length === 0).length
        },

        canToggleAvailability() {
            return this.planDays.length > 0 && this.openDayCount === 0
        },

        coveragePercent() {
            const assignable = this.planDays.filter((day) => !day.isFreeDay).length

            return assignable ? Math.round((this.filledDayCount / assignable) * 100) : 0
        },

        returnWeek() {
            const returnWeek = String(this.$route?.query?.return_week || '')

            return isValidIsoDate(returnWeek) ? returnWeek : ''
        },

        backTarget() {
            const path = String(this.$route?.query?.return_to || '/admin/restaurant/menu-plans')
            const query = this.returnWeek ? { week: this.returnWeek } : {}

            return { path, query }
        },

        rangeLabel() {
            if (!this.rangeBounds) {
                return 'Kein Zeitraum'
            }

            return `${this.formatDate(this.rangeBounds.start)} – ${this.formatDate(this.rangeBounds.end)}`
        },

        headerActiveSection() {
            return {
                icon: 'mdi-calendar-text-outline',
                label: 'Menüpläne',
                note: this.entryMode === 'edit' ? 'Bearbeiten aktiv' : 'Erstellen aktiv',
            }
        },

        headerChips() {
            return [
                {
                    text: this.config?.selected_school?.long_name || this.config?.selected_school?.short_name || 'Schule aktiv',
                    icon: 'mdi-domain',
                    color: 'white',
                },
                {
                    text: this.rangeLabel,
                    icon: 'mdi-link-variant',
                    color: 'white',
                },
            ]
        },
        printHref() {
            if (! this.planId) {
                return ''
            }

            return `/api/admin/restaurant/menu-plans/${this.planId}/print`
        },
        entryPreviewEntry() {
            if (! this.entryPreviewTarget.iso || ! this.entryPreviewTarget.key) {
                return null
            }

            return this.dayEntries(this.entryPreviewTarget.iso).find((item) => item?._key === this.entryPreviewTarget.key) || null
        },
    },

    methods: {
        // ── Data loading ─────────────────────────────────────────────────

        async loadInitialData() {
            const freeDayStore = useFreeDayStore()
            const foodStore = useFoodStore()
            const menuStore = useMenuStore()
            const restaurantStore = useRestaurantStore()
            const eatingTimeStore = useEatingTimeStore()

            const year = this.rangeBounds ? parseInt(this.rangeBounds.start.substring(0, 4), 10) : new Date().getFullYear()
            freeDayStore.loadYear(year)

            if (! foodStore.foods.length) {
                foodStore.index()
            }

            if (! menuStore.menus.length) {
                menuStore.index()
            }

            if (! restaurantStore.settings) {
                restaurantStore.loadSettings()
            }

            if (! eatingTimeStore.isLoaded) {
                eatingTimeStore.load()
            }

            if (this.entryMode === 'edit' && this.planId) {
                await this.loadExistingPlan(this.planId)
            } else {
                this.entriesByDate = {}
                this.planTitle = ''
                this.isPlanAvailable = false
            }
        },

        async loadExistingPlan(id) {
            const plan = await useMenuPlanStore().show(id)

            if (! plan) {
                return
            }

            this.planTitle = plan.title || ''
            this.isPlanAvailable = plan.is_available === true

            const next = {}

            ;(plan.entries || []).forEach((entry) => {
                const iso = entry.plan_date
                const menu = entry.menu || { id: entry.menu_id, title: '?', price: null }

                if (! next[iso]) {
                    next[iso] = []
                }

                next[iso].push({
                    _key: newEntryKey(),
                    menu,
                    menuTitle: entry.menu_title || menu.title || '',
                    price: entry.price != null ? String(entry.price) : (menu.price != null ? String(menu.price) : ''),
                    comments: entry.comments || '',
                    eatingTimeIds: entry.eating_time_ids || [],
                    eatingTimes: entry.eating_times || [],
                })
            })

            this.entriesByDate = next
        },

        togglePlanAvailability() {
            if (! this.canToggleAvailability) {
                return
            }

            this.isPlanAvailable = ! this.isPlanAvailable
        },

        // ── Entry management ─────────────────────────────────────────────

        dayEntries(iso) {
            return this.entriesByDate[iso] || []
        },

        entryEffectivePrice(entry) {
            if (entry?.price !== '') {
                return entry?.price ?? null
            }

            return entry?.menu?.price ?? null
        },

        entryHasPriceOverride(entry) {
            return entry?.price !== '' && String(entry?.price ?? '') !== String(entry?.menu?.price ?? '')
        },

        addEntryForDay(iso, menu) {
            if (! this.entriesByDate[iso]) {
                this.entriesByDate = { ...this.entriesByDate, [iso]: [] }
            }

            this.entriesByDate[iso] = [
                ...this.entriesByDate[iso],
                {
                    _key: newEntryKey(),
                    menu,
                    menuTitle: menu?.title || '',
                    price: menu?.price != null ? String(menu.price) : '',
                    comments: '',
                    eatingTimeIds: this.eatingTimes.map((et) => et.id),
                    eatingTimes: this.eatingTimes.map((et) => ({
                        id: et.id,
                        eating_time: et.eating_time,
                    })),
                },
            ]

            this.closeSearch(iso)
        },

        removeEntry(iso, key) {
            if (! this.entriesByDate[iso]) {
                return
            }

            this.entriesByDate[iso] = this.entriesByDate[iso].filter((e) => e._key !== key)
        },

        moveEntry(iso, index, direction) {
            const entries = this.entriesByDate[iso]

            if (! Array.isArray(entries)) {
                return
            }

            const targetIndex = index + direction

            if (targetIndex < 0 || targetIndex >= entries.length) {
                return
            }

            const reorderedEntries = [...entries]
            const [movedEntry] = reorderedEntries.splice(index, 1)
            reorderedEntries.splice(targetIndex, 0, movedEntry)
            this.entriesByDate[iso] = reorderedEntries
        },

        isTimeActive(entry, timeId) {
            return entry.eatingTimeIds.includes(timeId)
        },

        formatEatingTimeLabel(value) {
            const normalizedValue = String(value || '').trim()

            if (normalizedValue === '') {
                return ''
            }

            const shortValue = normalizedValue.match(/^\d{2}:\d{2}/)?.[0] || normalizedValue

            return `${shortValue} Uhr`
        },

        entryActiveEatingTimeLabels(entry) {
            const availableLabels = new Map(
                this.eatingTimes.map((time) => [
                    Number(time.id),
                    this.formatEatingTimeLabel(time.eating_time),
                ]),
            )
            const storedLabels = new Map(
                (entry?.eatingTimes || []).map((time) => [
                    Number(time.id),
                    this.formatEatingTimeLabel(time.eating_time),
                ]),
            )

            return (entry?.eatingTimeIds || [])
                .map((timeId) => availableLabels.get(Number(timeId)) || storedLabels.get(Number(timeId)) || '')
                .filter((label) => label !== '')
        },

        toggleTime(entry, timeId) {
            if (entry.eatingTimeIds.includes(timeId)) {
                entry.eatingTimeIds = entry.eatingTimeIds.filter((id) => id !== timeId)
            } else {
                entry.eatingTimeIds = [...entry.eatingTimeIds, timeId]
            }

            entry.eatingTimes = this.eatingTimes.filter((time) => entry.eatingTimeIds.includes(time.id)).map((time) => ({
                id: time.id,
                eating_time: time.eating_time,
            }))
        },

        // ── Search ───────────────────────────────────────────────────────

        searchOpen(iso) {
            return this.searchStates[iso]?.open === true
        },

        searchQueryFor(iso) {
            if (! this.searchStates[iso]) {
                this.searchStates = { ...this.searchStates, [iso]: { open: false, query: '' } }
            }

            return this.searchStates[iso]
        },

        openSearch(iso) {
            this.searchStates = {
                ...this.searchStates,
                [iso]: { open: true, query: '' },
            }
        },

        closeSearch(iso) {
            if (this.searchStates[iso]) {
                this.searchStates[iso].open = false
                this.searchStates[iso].query = ''
            }
        },

        filteredMenusFor(iso) {
            const query = (this.searchStates[iso]?.query || '').toLowerCase().trim()
            const assignedIds = new Set(this.dayEntries(iso).map((e) => e.menu.id))

            return this.menus.filter((menu) => {
                if (assignedIds.has(menu.id)) {
                    return false
                }

                if (! query) {
                    return true
                }

                return String(menu.title || '').toLowerCase().includes(query)
            })
        },

        // ── Create new menu dialog ────────────────────────────────────────

        openCreateMenuDialog(iso) {
            this.createMenuForDate = iso
            this.editingMenuId = null
            this.newMenuForm = emptyNewMenuForm()
            this.newMenuFoodSearch = ''
            this.isCreateMenuFormValid = false
            this.createMenuDialog = true
        },

        openEditMenuDialog(menu) {
            this.createMenuForDate = null
            this.editingMenuId = menu?.id ?? null
            this.newMenuForm = {
                title: menu?.title || '',
                foodIds: this.sortedFoods(menu?.foods || []).map((food) => food.id),
                price: this.normalizeNewMenuPriceInput(menu?.price),
                courseDraft: {
                    categoryId: null,
                    foodId: null,
                },
            }
            this.newMenuFoodSearch = ''
            this.isCreateMenuFormValid = false
            this.createMenuDialog = true
        },

        openEntryPreviewDialog(iso, key) {
            const entry = this.dayEntries(iso).find((item) => item?._key === key)

            if (! entry) {
                return
            }

            this.entryPreviewTarget = { iso, key }
            this.entryPreviewDialog = true
        },

        openEditEntryDialog(iso, key) {
            const entry = this.dayEntries(iso).find((item) => item?._key === key)

            if (! entry) {
                return
            }

            this.entryEditTarget = { iso, key }
            this.entryEditForm = {
                menuTitle: entry.menuTitle || entry.menu?.title || '',
                price: this.normalizeNewMenuPriceInput(entry.price),
                comments: entry.comments || '',
            }
            this.entryEditDialog = true
        },

        requestDeleteEntry(iso, key) {
            const entry = this.dayEntries(iso).find((item) => item?._key === key)

            if (! entry) {
                return
            }

            this.deleteEntryTarget = { iso, key }
            this.deleteEntryDialog = true
        },

        closeEditEntryDialog() {
            this.entryEditDialog = false
            this.entryEditTarget = {
                iso: '',
                key: '',
            }
            this.entryEditForm = {
                menuTitle: '',
                price: '',
                comments: '',
            }
        },

        closeEntryPreviewDialog() {
            this.entryPreviewDialog = false
            this.entryPreviewTarget = {
                iso: '',
                key: '',
            }
        },

        cancelDeleteEntry() {
            this.deleteEntryDialog = false
            this.deleteEntryTarget = {
                iso: '',
                key: '',
            }
        },

        confirmDeleteEntry() {
            this.removeEntry(this.deleteEntryTarget.iso, this.deleteEntryTarget.key)
            this.cancelDeleteEntry()
        },

        closeCreateMenuDialog() {
            this.createMenuDialog = false
            this.createMenuForDate = null
            this.editingMenuId = null
            this.isCreateMenuFormValid = false
            this.newMenuFoodSearch = ''
            this.newMenuForm = emptyNewMenuForm()
        },

        normalizeNewMenuPriceInput(value) {
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

        normalizeNewMenuPricePayload(value) {
            const normalizedPrice = this.normalizeNewMenuPriceInput(value)

            return normalizedPrice === '' ? '' : normalizedPrice.replace(',', '.')
        },

        normalizeNewMenuPrice() {
            this.newMenuForm.price = this.normalizeNewMenuPriceInput(this.newMenuForm.price)
        },

        normalizeEntryEditPriceField() {
            this.entryEditForm.price = this.normalizeNewMenuPriceInput(this.entryEditForm.price)
        },

        isNewMenuDraftCategorySelected(categoryId) {
            return Number(this.newMenuForm.courseDraft.categoryId) === Number(categoryId)
        },

        selectNewMenuDraftCategory(categoryId) {
            const normalizedCategoryId = Number(categoryId)

            if (! Number.isFinite(normalizedCategoryId) || normalizedCategoryId <= 0) {
                this.resetNewMenuCourseDraft()
                return
            }

            if (this.isNewMenuDraftCategorySelected(normalizedCategoryId)) {
                this.resetNewMenuCourseDraft()
                return
            }

            this.newMenuForm.courseDraft = {
                categoryId: normalizedCategoryId,
                foodId: null,
            }
            this.newMenuFoodSearch = ''
        },

        isNewMenuDraftFoodSelected(foodId) {
            return Number(this.newMenuForm.courseDraft.foodId) === Number(foodId)
        },

        isNewMenuFoodAlreadyAssigned(foodId) {
            return this.newMenuForm.foodIds.some((value) => Number(value) === Number(foodId))
        },

        selectNewMenuDraftFood(foodId) {
            const normalizedFoodId = Number(foodId)

            if (! Number.isFinite(normalizedFoodId) || normalizedFoodId <= 0 || this.isNewMenuFoodAlreadyAssigned(normalizedFoodId)) {
                return
            }

            if (this.isNewMenuDraftFoodSelected(normalizedFoodId)) {
                this.newMenuForm.courseDraft.foodId = null
                return
            }

            this.newMenuForm.courseDraft.foodId = normalizedFoodId
        },

        appendNewMenuDraftCourse() {
            if (! this.canAddNewMenuDraftCourse || this.isNewMenuFoodAlreadyAssigned(this.newMenuForm.courseDraft.foodId)) {
                return
            }

            this.newMenuForm.foodIds = [...this.newMenuForm.foodIds, this.newMenuForm.courseDraft.foodId]
            this.resetNewMenuCourseDraft()
        },

        resetNewMenuCourseDraft() {
            this.newMenuForm.courseDraft = {
                categoryId: null,
                foodId: null,
            }
            this.newMenuFoodSearch = ''
        },

        removeNewMenuSelectedFood(foodId) {
            this.newMenuForm.foodIds = this.newMenuForm.foodIds.filter((value) => Number(value) !== Number(foodId))
        },

        moveNewMenuSelectedFood(index, direction) {
            const targetIndex = index + direction
            if (targetIndex < 0 || targetIndex >= this.newMenuForm.foodIds.length) {
                return
            }

            const reorderedFoodIds = [...this.newMenuForm.foodIds]
            const [movedFoodId] = reorderedFoodIds.splice(index, 1)
            reorderedFoodIds.splice(targetIndex, 0, movedFoodId)
            this.newMenuForm.foodIds = reorderedFoodIds
        },

        selectedNewMenuFoodTitle(foodId) {
            return this.newMenuFoodOptions.find((food) => Number(food.id) === Number(foodId))?.title || `Speise #${foodId}`
        },

        selectedNewMenuFoodCategoryTitle(foodId) {
            return this.newMenuFoodOptions.find((food) => Number(food.id) === Number(foodId))?.category?.title || ''
        },

        entryEditMenuTitle() {
            const entry = this.dayEntries(this.entryEditTarget.iso).find((item) => item?._key === this.entryEditTarget.key)

            if (! entry?.menuTitle && ! entry?.menu?.title) {
                return ''
            }

            return entry.menuTitle || entry.menu.title
        },

        deleteEntryMenuTitle() {
            const entry = this.dayEntries(this.deleteEntryTarget.iso).find((item) => item?._key === this.deleteEntryTarget.key)

            if (! entry?.menuTitle && ! entry?.menu?.title) {
                return ''
            }

            return entry.menuTitle || entry.menu.title
        },

        applyEditedMenuToEntries(updatedMenu) {
            if (! updatedMenu?.id) {
                return
            }

            const nextEntriesByDate = {}

            Object.entries(this.entriesByDate).forEach(([iso, entries]) => {
                nextEntriesByDate[iso] = (entries || []).map((entry) => {
                    if (Number(entry?.menu?.id) !== Number(updatedMenu.id)) {
                        return entry
                    }

                    return {
                        ...entry,
                        menu: updatedMenu,
                    }
                })
            })

            this.entriesByDate = nextEntriesByDate
        },

        saveEntryEdit() {
            const entries = this.entriesByDate[this.entryEditTarget.iso]

            if (! Array.isArray(entries)) {
                return
            }

            const nextPrice = this.normalizeNewMenuPricePayload(this.entryEditForm.price)

            this.entriesByDate[this.entryEditTarget.iso] = entries.map((entry) => {
                if (entry?._key !== this.entryEditTarget.key) {
                    return entry
                }

                return {
                    ...entry,
                    menuTitle: String(this.entryEditForm.menuTitle || '').trim(),
                    price: nextPrice,
                    comments: String(this.entryEditForm.comments || '').trim(),
                }
            })

            this.closeEditEntryDialog()
        },

        async saveNewMenu() {
            this.isCreateMenuFormValid = false
            await this.$refs.createMenuForm?.validate()

            if (! this.isCreateMenuFormValid) {
                return
            }

            this.isCreatingMenu = true

            try {
                const menuStore = useMenuStore()
                const saved = await menuStore.store({
                    title: this.newMenuForm.title,
                    price: this.normalizeNewMenuPricePayload(this.newMenuForm.price),
                    food_ids: this.newMenuForm.foodIds,
                })

                if (saved && this.createMenuForDate) {
                    this.addEntryForDay(this.createMenuForDate, saved)
                    this.closeSearch(this.createMenuForDate)
                }

                this.closeCreateMenuDialog()
            } finally {
                this.isCreatingMenu = false
            }
        },

        // ── Save plan ────────────────────────────────────────────────────

        buildPayload() {
            const entries = []

            Object.entries(this.entriesByDate).forEach(([iso, dayEntries]) => {
                dayEntries.forEach((entry) => {
                    entries.push({
                        plan_date: iso,
                        menu_id: entry.menu.id,
                        menu_title: entry.menuTitle !== '' ? entry.menuTitle : null,
                        price: entry.price !== '' ? entry.price : null,
                        comments: entry.comments !== '' ? entry.comments : null,
                        eating_time_ids: entry.eatingTimeIds,
                    })
                })
            })

            return {
                title: this.planTitle || null,
                start_date: this.rangeBounds?.start,
                end_date: this.rangeBounds?.end,
                is_available: this.canToggleAvailability ? this.isPlanAvailable : false,
                entries,
            }
        },

        async savePlan() {
            if (! this.rangeBounds) {
                return
            }

            this.isSaving = true

            try {
                const store = useMenuPlanStore()
                const payload = this.buildPayload()
                let result

                if (this.planId) {
                    result = await store.update(this.planId, payload)
                } else {
                    result = await store.store(payload)

                    if (result && result.id) {
                        // Update URL to edit mode so further saves become updates
                        this.$router.replace({
                            query: {
                                ...this.$route.query,
                                mode: 'edit',
                                plan_id: result.id,
                            },
                        }).catch(() => {})
                    }
                }
            } finally {
                this.isSaving = false
            }
        },

        async downloadPlanPdf() {
            if (! this.printHref || this.isPrinting) {
                return
            }

            const adminStore = useAdminStore()
            const notification = useNotificationStore()

            this.isPrinting = true
            adminStore.is_loading++

            try {
                const response = await axios.get(this.printHref, {
                    responseType: 'blob',
                })

                const disposition = response?.headers?.['content-disposition']
                const fileName = this.fileNameFromContentDisposition(disposition) || `menu-plan-${this.planId || 'export'}.pdf`
                const blob = response?.data instanceof Blob ? response.data : new Blob([response?.data], { type: 'application/pdf' })
                const objectUrl = URL.createObjectURL(blob)
                const link = document.createElement('a')

                link.href = objectUrl
                link.download = fileName
                document.body.appendChild(link)
                link.click()
                link.remove()
                URL.revokeObjectURL(objectUrl)
            } catch (error) {
                notification.notify({
                    status: error?.response?.status,
                    message: error?.response?.data?.message || 'Menüplan-PDF konnte nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
            } finally {
                adminStore.is_loading--
                this.isPrinting = false
            }
        },

        // ── Helpers ──────────────────────────────────────────────────────

        fileNameFromContentDisposition(headerValue) {
            const normalizedHeader = String(headerValue || '').trim()

            if (! normalizedHeader) {
                return ''
            }

            const utf8Match = normalizedHeader.match(/filename\*\s*=\s*UTF-8''([^;]+)/i)

            if (utf8Match?.[1]) {
                try {
                    return decodeURIComponent(utf8Match[1]).replace(/["']/g, '').trim()
                } catch {
                    return utf8Match[1].replace(/["']/g, '').trim()
                }
            }

            const plainMatch = normalizedHeader.match(/filename\s*=\s*"?(?<file>[^";]+)"?/i)

            return plainMatch?.groups?.file?.trim() || ''
        },

        formatPrice(price) {
            if (price == null) {
                return ''
            }

            return parseFloat(price).toFixed(2).replace('.', ',') + ' €'
        },

        toDate(isoString) {
            return new Date(`${isoString}T00:00:00`)
        },

        addDaysIso(isoString, days) {
            const date = this.toDate(isoString)
            date.setDate(date.getDate() + days)

            return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-')
        },

        formatDate(isoString) {
            return this.toDate(isoString).toLocaleDateString('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },

        allergenLabel(value) {
            const normalizedValue = String(value || '').trim().toUpperCase()
            const allergenOption = this.allergenOptions.find((option) => option.character === normalizedValue)

            if (! allergenOption) {
                return String(value || '')
            }

            return `${allergenOption.character} - ${allergenOption.short_description}`
        },

        shortLabel(value) {
            return String(value || '').trim().slice(0, 2).toUpperCase()
        },

        sortedFoods(foods) {
            return [...(foods || [])].sort((a, b) => (a.course_number ?? 99) - (b.course_number ?? 99))
        },
    },
}
</script>

<style scoped>
/* ---- Page ---- */

.mpe-page {
    min-height: 100vh;
    background: linear-gradient(160deg, #fafaf8 0%, #f5ede0 100%);
}

/* ---- Stage ---- */

.mpe-stage {
    height: 100%;
    border: 1px solid rgba(180, 83, 9, 0.1);
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 8px 32px rgba(15, 23, 42, 0.07);
}

/* ---- Dark Gradient Header ---- */

.mpe-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    padding: 24px;
    border-radius: 20px;
    background: linear-gradient(135deg, #1e293b 0%, #334155 55%, #475569 100%);
    color: #f8fafc;
    margin-bottom: 20px;
}

.mpe-header__eyebrow {
    font-size: 0.71rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(248, 250, 252, 0.52);
    margin-bottom: 6px;
}

.mpe-header__title {
    font-size: clamp(1.65rem, 2.8vw, 2.2rem);
    font-weight: 900;
    line-height: 1.05;
    margin: 0;
}

.mpe-header__range {
    margin-top: 8px;
    font-size: 0.9rem;
    color: rgba(248, 250, 252, 0.6);
}

.mpe-header__right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
    flex-shrink: 0;
}

.mpe-header__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: nowrap;
    white-space: nowrap;
}

.mpe-header__actions > * {
    flex: 0 0 auto;
}

.mpe-badge {
    min-width: 96px;
    padding: 12px 14px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.14);
    text-align: center;
}

.mpe-badge strong {
    display: block;
    font-size: 1.8rem;
    line-height: 1;
    font-weight: 900;
}

.mpe-badge span {
    font-size: 0.71rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: rgba(248, 250, 252, 0.6);
}

/* ---- Plan title row ---- */

.mpe-plan-title-row {
    margin-bottom: 18px;
}

/* ---- Summary Strip ---- */

.mpe-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.mpe-stat {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding: 14px 16px;
    border-radius: 16px;
    background: linear-gradient(160deg, #fffdf5, #fef3c7);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.mpe-stat__icon { color: #b45309; }

.mpe-stat__label {
    font-size: 0.69rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    color: #b45309;
}

.mpe-stat__value {
    font-size: 1.15rem;
    font-weight: 900;
    color: #1f2937;
    line-height: 1;
}

.mpe-stat__availability-header {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mpe-availability-toggle {
    width: 100%;
    min-height: 56px;
    border: 1px solid rgba(148, 163, 184, 0.35);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.92);
    color: #475569;
    font: inherit;
    font-size: 1rem;
    font-weight: 800;
    letter-spacing: 0.01em;
    cursor: pointer;
    transition: transform 0.18s ease, background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease, color 0.18s ease;
}

.mpe-availability-toggle:hover {
    transform: translateY(-1px);
    border-color: rgba(34, 197, 94, 0.38);
}

.mpe-availability-toggle.is-active {
    border-color: rgba(22, 163, 74, 0.5);
    background: linear-gradient(160deg, #dcfce7, #bbf7d0);
    color: #166534;
    box-shadow: inset 0 0 0 1px rgba(22, 163, 74, 0.16);
}

/* ---- Week Board ---- */

.mpe-board {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 14px;
}

/* ---- Day Tile ---- */

.mpe-day {
    display: flex;
    flex-direction: column;
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.mpe-day:hover {
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.1);
    transform: translateY(-2px);
}

.mpe-day--filled {
    background: linear-gradient(180deg, #ffffff 0%, #fffcf0 100%);
    border-color: rgba(245, 158, 11, 0.25);
}

.mpe-day--empty {
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border-style: dashed;
    border-color: #d1d5db;
}

.mpe-day--free {
    background: linear-gradient(180deg, #ffffff 0%, #f0fdf4 100%);
    border-color: rgba(34, 197, 94, 0.3);
}

.mpe-day__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 14px 16px 10px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    background: rgba(255, 255, 255, 0.75);
    flex-shrink: 0;
}

.mpe-day__weekday {
    font-size: 1rem;
    font-weight: 800;
    color: #111827;
    text-transform: capitalize;
}

.mpe-day__date {
    font-size: 0.78rem;
    color: #6b7280;
    margin-top: 2px;
}

.mpe-day__status {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
    white-space: nowrap;
}

.mpe-day__status.is-filled { background: rgba(245, 158, 11, 0.14); color: #92400e; }
.mpe-day__status.is-empty { background: rgba(100, 116, 139, 0.1); color: #475569; }
.mpe-day__status.is-free { background: rgba(34, 197, 94, 0.12); color: #15803d; }

.mpe-day__body {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 12px;
    flex: 1;
}

/* ---- Entry Card (assigned menu) ---- */

.mpe-entry-card {
    padding: 10px 12px;
    border-radius: 12px;
    background: linear-gradient(160deg, #fffcf0, #fef3c7);
    border: 1px solid rgba(245, 158, 11, 0.25);
}

.mpe-entry-card__header {
    margin-bottom: 6px;
}

.mpe-entry-card__actions {
    display: flex;
    align-items: center;
    gap: 2px;
    flex-wrap: wrap;
    justify-content: flex-end;
    margin-bottom: 6px;
}

.mpe-entry-card__title {
    font-size: 0.88rem;
    font-weight: 700;
    color: #1f2937;
    line-height: 1.4;
    word-break: break-word;
}

.mpe-entry-card__price :deep(.v-field) {
    font-size: 0.82rem;
}

.mpe-entry-card__foods {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-top: 4px;
    padding-left: 2px;
}

.mpe-entry-card__food {
    font-size: 0.78rem;
    color: #374151;
    line-height: 1.4;
}

.mpe-entry-card__food::before {
    content: '·\00a0';
    color: #b45309;
}

.mpe-entry-card__base-price {
    display: block;
    width: 100%;
    font-size: 0.75rem;
    color: #b45309;
    font-weight: 600;
    margin-top: 4px;
    text-align: right;
}

.mpe-entry-card__price-note {
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 2px;
}

.mpe-preview {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.mpe-preview__meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
}

.mpe-preview__meta-item {
    padding: 12px 14px;
    border-radius: 14px;
    background: linear-gradient(160deg, #fffdf5, #fef3c7);
    border: 1px solid rgba(245, 158, 11, 0.18);
}

.mpe-preview__meta-label {
    display: block;
    margin-bottom: 4px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #92400e;
}

.mpe-preview__foods {
    display: grid;
    gap: 14px;
}

.mpe-preview-food {
    display: grid;
    grid-template-columns: minmax(0, 180px) minmax(0, 1fr);
    gap: 16px;
    padding: 16px;
    border-radius: 18px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
}

.mpe-preview-food--no-media {
    grid-template-columns: minmax(0, 1fr);
}

.mpe-preview-food__media {
    overflow: hidden;
    border-radius: 14px;
    background: #f8fafc;
}

.mpe-preview-food__body {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.mpe-preview-food__course-row {
    width: 100%;
}

.mpe-preview-food__head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    width: 100%;
}

.mpe-preview-food__course {
    display: block;
    width: 100%;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(245, 158, 11, 0.14);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #b45309;
}

.mpe-preview-food__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: #111827;
    flex: 1;
}

.mpe-preview-food__price {
    font-size: 0.82rem;
    font-weight: 600;
    color: #b45309;
}

.mpe-preview-food__description {
    font-size: 0.9rem;
    line-height: 1.55;
    color: #374151;
    white-space: pre-line;
}

.mpe-preview-food__section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.mpe-preview-food__label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #6b7280;
}

.mpe-preview-food__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.mpe-preview-food__icon-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.mpe-preview-food__icon {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    border-radius: 999px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    font-size: 0.82rem;
    color: #374151;
}

.mpe-preview-food__icon-avatar {
    background: #fff7ed;
    color: #9a3412;
    font-size: 0.72rem;
    font-weight: 700;
}

/* ---- Eating time chips ---- */

.mpe-eating-times__label {
    font-size: 0.69rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #b45309;
}

.mpe-eating-times__summary {
    font-size: 0.84rem;
    font-weight: 600;
    color: #374151;
}

.mpe-time-chip {
    cursor: pointer;
}

/* ---- Search panel ---- */

.mpe-search-panel {
    padding: 10px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.mpe-search-results {
    display: flex;
    flex-direction: column;
    gap: 2px;
    max-height: 800px;
    overflow-y: auto;
    margin-bottom: 4px;
}

.mpe-search-result {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 8px 10px;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    cursor: pointer;
    text-align: left;
    transition: background-color 0.12s ease;
}

.mpe-search-result:hover {
    background: #fef3c7;
    border-color: rgba(245, 158, 11, 0.4);
}

.mpe-search-result__top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.mpe-search-result__title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #1f2937;
}

.mpe-search-result__price {
    font-size: 0.78rem;
    color: #b45309;
    font-weight: 600;
    white-space: nowrap;
}

.mpe-search-result__foods {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.mpe-search-result__food {
    font-size: 0.75rem;
    color: #6b7280;
    line-height: 1.3;
}

.mpe-search-result__food::before {
    content: '·\00a0';
    color: #b45309;
}

.mpe-search-empty {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    padding: 8px 4px;
    font-size: 0.84rem;
    color: #6b7280;
}

/* ---- Add button ---- */

.mpe-add-btn {
    align-self: flex-start;
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

/* ---- Free Day Card ---- */

.mpe-free-card {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 20px 16px;
    text-align: center;
    background: linear-gradient(160deg, #f0fdf4, #dcfce7);
}

.mpe-free-card__icon { color: #4ade80; }

.mpe-free-card__text {
    font-size: 0.9rem;
    font-weight: 700;
    color: #15803d;
    letter-spacing: 0.02em;
    margin: 0;
}

/* ---- Free stat variant ---- */

.mpe-stat--free .mpe-stat__icon { color: #15803d; }
.mpe-stat--free .mpe-stat__label { color: #15803d; }
.mpe-stat--free { background: linear-gradient(160deg, #f0fdf4, #dcfce7); border-color: rgba(34, 197, 94, 0.2); }
.mpe-stat--availability { background: linear-gradient(160deg, #f8fafc, #e2e8f0); border-color: rgba(148, 163, 184, 0.24); }
.mpe-stat--availability .mpe-stat__icon,
.mpe-stat--availability .mpe-stat__label { color: #475569; }

/* ---- No-range Empty State ---- */

.mpe-no-range {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 44px 24px;
    border-radius: 20px;
    background: #f8fafc;
    border: 1px dashed #d1d5db;
    text-align: center;
    color: #374151;
}

.mpe-no-range span {
    font-size: 0.9rem;
    color: #6b7280;
}

/* ---- Responsive ---- */

@media (max-width: 1260px) {
    .mpe-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 960px) {
    .mpe-header { flex-direction: column; }
    .mpe-header__right { width: 100%; flex-direction: row; align-items: center; flex-wrap: nowrap; justify-content: space-between; }
    .mpe-header__actions { max-width: 100%; overflow-x: auto; }
}

@media (max-width: 640px) {
    .mpe-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .mpe-board { grid-template-columns: 1fr; }
    .mpe-preview-food { grid-template-columns: 1fr; }
}
</style>
