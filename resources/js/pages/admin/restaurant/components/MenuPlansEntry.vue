<template>
    <div
        class="mpe-page"
        :class="{ 'mpe-page--busy': isNavigatingBack }"
        :aria-busy="isNavigatingBack ? 'true' : 'false'">
        <div
            v-if="isNavigatingBack"
            class="mpe-overlay"
            data-testid="menu-plan-back-overlay"
            aria-live="polite">
            <div class="mpe-overlay__content">
                <div class="mpe-overlay__spinner" aria-hidden="true" />
                <div class="mpe-overlay__text">Ansicht wird geladen ...</div>
            </div>
        </div>
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
                                        :disabled="isNavigatingBack"
                                        @click="savePlan">
                                        Speichern
                                    </v-btn>
                                    <v-btn
                                        v-if="planId"
                                        color="warning"
                                        rounded="xl"
                                        variant="tonal"
                                        prepend-icon="mdi-delete-outline"
                                        :disabled="!canDeletePlan || isNavigatingBack"
                                        data-testid="delete-menu-plan-button"
                                        @click="requestDeletePlan">
                                        {{ deletePlanButtonLabel }}
                                    </v-btn>
                                    <v-btn
                                        color="white"
                                        rounded="xl"
                                        variant="tonal"
                                        prepend-icon="mdi-arrow-left"
                                        :loading="isNavigatingBack"
                                        :disabled="isNavigatingBack"
                                        data-testid="menu-plan-back-button"
                                        @click="navigateBack">
                                        Zurück
                                    </v-btn>
                                </div>
                                <div
                                    v-if="planId && hasPlanBookings"
                                    class="mpe-header__hint"
                                    data-testid="delete-menu-plan-hint">
                                    {{ deletePlanHint }}
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

                        <div v-if="rangeBounds" class="mpe-schedule-preview" data-testid="plan-schedule-preview">
                            <div class="mpe-schedule-preview__header">
                                <div>
                                    <div class="mpe-schedule-preview__eyebrow">Berechnete Termine</div>
                                    <div class="mpe-schedule-preview__title">Wirksame Sichtbarkeit und Bestellbarkeit</div>
                                </div>

                                <label class="mpe-schedule-preview__toggle" data-testid="individual-schedule-toggle">
                                    <input
                                        v-model="useIndividualScheduleValues"
                                        type="checkbox"
                                        class="mpe-schedule-preview__toggle-input"
                                        @change="handleIndividualScheduleToggle">
                                    <span class="mpe-schedule-preview__toggle-copy">
                                        Individuelle Zeitpunkte verwenden
                                    </span>
                                </label>
                            </div>

                            <div class="mpe-schedule-preview__grid">
                                <div
                                    v-for="item in calculatedScheduleItems"
                                    :key="item.key"
                                    class="mpe-schedule-preview__item">
                                    <span class="mpe-schedule-preview__label">{{ item.label }}</span>
                                    <template v-if="useIndividualScheduleValues">
                                        <input
                                            v-model="individualScheduleForm[item.modelKey]"
                                            type="datetime-local"
                                            class="mpe-schedule-preview__input"
                                            :data-testid="`individual-schedule-${item.modelKey}`">
                                    </template>
                                    <template v-else>
                                        <strong class="mpe-schedule-preview__value">{{ item.value }}</strong>
                                    </template>
                                    <span v-if="item.note" class="mpe-schedule-preview__note">{{ item.note }}</span>
                                </div>
                            </div>
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
                                v-if="planDays.length > 0"
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
                                    :disabled="isSaving || !canToggleAvailability"
                                    :title="!canToggleAvailability ? 'Alle Tage müssen Menüeinträge haben' : ''"
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
                                    <div class="mpe-day__header-actions">
                                        <div
                                            class="mpe-day__status"
                                        :class="day.isFreeDay ? 'is-free' : dayEntries(day.iso).length ? 'is-filled' : 'is-empty'">
                                        <v-icon
                                            :icon="day.isFreeDay ? 'mdi-leaf' : dayEntries(day.iso).length ? 'mdi-check' : 'mdi-clock-outline'"
                                            size="11"
                                            class="mr-1" />
                                        {{ day.isFreeDay ? 'Frei' : dayEntries(day.iso).length ? `${dayEntries(day.iso).length} Menü(s)` : 'Offen' }}
                                    </div>
                                        <v-btn
                                            v-if="canDeleteBoundaryDay(day.iso)"
                                            icon="mdi-delete-outline"
                                            size="x-small"
                                            variant="text"
                                            color="warning"
                                            :data-testid="`delete-boundary-day-${day.iso}`"
                                            @click="requestDeleteBoundaryDay(day.iso)" />
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
                                        class="mpe-entry-card"
                                        :class="{ 'mpe-entry-card--locked': isEntryLocked(entry) }">
                                        <div class="mpe-entry-card__header">
                                            <div class="mpe-entry-card__title-row">
                                                <div class="mpe-entry-card__title-block">
                                                    <div class="mpe-entry-card__title">{{ entry.menuTitle || entry.menu.title }}</div>
                                                    <div v-if="entryFoodSummary(entry)" class="mpe-entry-card__food-summary">{{ entryFoodSummary(entry) }}</div>
                                                </div>
                                                <span
                                                    v-if="isEntryLocked(entry)"
                                                    class="mpe-entry-card__lock"
                                                    :data-testid="`locked-menu-entry-${day.iso}-${entry._key}`">
                                                    <v-icon icon="mdi-lock-outline" size="12" />
                                                    {{ entryLockLabel(entry) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mpe-entry-card__actions">
                                            <v-btn
                                                icon="mdi-arrow-up"
                                                size="x-small"
                                                variant="text"
                                                :disabled="entryIndex === 0 || dayHasLockedEntries(day.iso)"
                                                :data-testid="`move-entry-up-${day.iso}-${entry._key}`"
                                                @click="moveEntry(day.iso, entryIndex, -1)" />
                                            <v-btn
                                                icon="mdi-arrow-down"
                                                size="x-small"
                                                variant="text"
                                                :disabled="entryIndex === dayEntries(day.iso).length - 1 || dayHasLockedEntries(day.iso)"
                                                :data-testid="`move-entry-down-${day.iso}-${entry._key}`"
                                                @click="moveEntry(day.iso, entryIndex, 1)" />
                                            <v-btn
                                                icon="mdi-delete"
                                                size="x-small"
                                                variant="text"
                                                color="warning"
                                                :disabled="isEntryLocked(entry)"
                                                :data-testid="`delete-entry-${day.iso}-${entry._key}`"
                                                @click="requestDeleteEntry(day.iso, entry._key)" />
                                            <v-btn
                                                icon="mdi-pencil"
                                                size="x-small"
                                                variant="text"
                                                color="primary"
                                                :disabled="isEntryLocked(entry)"
                                                :data-testid="`edit-entry-${day.iso}-${entry._key}`"
                                                @click="openEditEntryDialog(day.iso, entry._key)" />
                                            <v-btn
                                                v-if="isEntryLocked(entry)"
                                                icon="mdi-account-group-outline"
                                                size="x-small"
                                                variant="text"
                                                color="deep-orange"
                                                title="Buchungen anzeigen"
                                                :data-testid="`show-bookings-${day.iso}-${entry._key}`"
                                                @click="openBookingsDialog(entry)" />
                                            <v-btn
                                                v-if="entryCanManageBookings(entry)"
                                                icon="mdi-plus-circle-outline"
                                                size="x-small"
                                                variant="text"
                                                color="success"
                                                title="Buchung hinzufügen"
                                                :data-testid="`add-booking-${day.iso}-${entry._key}`"
                                                @click="openCreateBookingDialog(entry)" />
                                            <v-btn
                                                icon="mdi-eye-outline"
                                                size="x-small"
                                                variant="text"
                                                color="primary"
                                                :data-testid="`preview-menu-${day.iso}-${entry._key}`"
                                                @click="openEntryPreviewDialog(day.iso, entry._key)" />
                                        </div>
                                        <div v-if="entryEffectivePrice(entry) != null" class="mpe-entry-card__base-price">
                                            Preis: {{ formatPrice(entryEffectivePrice(entry)) }}
                                        </div>
                                        <div v-if="entryHasPriceOverride(entry) && entry.menu.price != null" class="mpe-entry-card__price-note">
                                            Basispreis: {{ formatPrice(entry.menu.price) }}
                                        </div>
                                        <div v-if="isEntryLocked(entry)" class="mpe-entry-card__lock-note">
                                            Dieser Eintrag ist wegen vorhandener Buchungen gesperrt und kann nicht bearbeitet, verschoben oder gelöscht werden.
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
                                                    :disabled="isEntryLocked(entry)"
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

        <v-dialog v-model="deleteBoundaryDayDialog" max-width="460" persistent>
            <v-card rounded="xl">
                <v-card-title>Tag entfernen</v-card-title>

                <v-card-text>
                    <div class="text-body-1">
                        Soll <strong>{{ deleteBoundaryDayLabel || 'dieser Tag' }}</strong> wirklich aus dem Plan entfernt werden?
                    </div>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="cancelDeleteBoundaryDay">Abbrechen</v-btn>
                    <v-btn color="warning" variant="flat" @click="confirmDeleteBoundaryDay">
                        Entfernen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deletePlanDialog" max-width="520" persistent data-testid="delete-menu-plan-dialog">
            <v-card rounded="xl">
                <v-card-title>{{ deletePlanDialogTitle }}</v-card-title>

                <v-card-text>
                    <div class="text-body-1">
                        {{ deletePlanDialogMessage }}
                    </div>
                    <div class="text-body-2 text-medium-emphasis mt-3">
                        {{ deletePlanDialogDescription }}
                    </div>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" :disabled="isDeletingPlan" @click="cancelDeletePlan">Abbrechen</v-btn>
                    <v-btn
                        color="warning"
                        variant="flat"
                        :loading="isDeletingPlan"
                        :disabled="isDeletingPlan"
                        @click="confirmDeletePlan">
                        {{ deletePlanConfirmLabel }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="saveReleaseDialog" max-width="560" persistent data-testid="save-release-dialog">
            <v-card rounded="xl" class="save-release-dialog">
                <v-card-title>Menüplan freigeben?</v-card-title>

                <div class="save-release-dialog__hero">
                    <div class="save-release-dialog__icon">
                        <v-icon icon="mdi-alert-decagram" color="warning" size="30" />
                    </div>
                    <div class="save-release-dialog__hero-copy">
                        <div class="save-release-dialog__eyebrow">Fertig, aber noch nicht freigegeben</div>
                        <div class="save-release-dialog__title">Menüplan jetzt freigeben?</div>
                    </div>
                </div>

                <v-card-text class="save-release-dialog__body">
                    <v-alert
                        type="warning"
                        variant="tonal"
                        density="comfortable"
                        class="save-release-dialog__alert"
                        data-testid="save-release-alert">
                        Dieser Menüplan ist vollständig. Ohne Freigabe bleibt er für Sichtbarkeit und Bestellungen gesperrt.
                    </v-alert>

                    <div class="save-release-dialog__question">
                        Soll der fertige Menüplan jetzt freigegeben werden?
                    </div>
                </v-card-text>

                <v-card-actions class="px-6 pb-5 save-release-dialog__actions">
                    <v-spacer />
                    <v-btn variant="text" prepend-icon="mdi-clock-outline" @click="confirmSaveWithoutRelease">Noch nicht</v-btn>
                    <v-btn color="primary" variant="flat" prepend-icon="mdi-eye-check-outline" @click="confirmSaveAndRelease">
                        Freigeben
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="bookingsDialog" max-width="680" persistent data-testid="bookings-dialog">
            <v-card rounded="xl">
                <v-card-title class="d-flex align-center justify-space-between">
                    <span>Buchungen: {{ bookingsDialogEntryTitle }}</span>
                    <v-btn icon="mdi-close" variant="text" @click="bookingsDialog = false" />
                </v-card-title>

                <v-card-text>
                    <div v-if="bookingsDialogLoading" class="text-center py-6">
                        <v-progress-circular indeterminate color="primary" />
                    </div>

                    <v-alert
                        v-else-if="!bookingsDialogData.length"
                        type="info"
                        variant="tonal"
                        density="comfortable">
                        Keine Buchungen vorhanden.
                    </v-alert>

                    <v-table v-else density="compact" class="mpe-bookings-table">
                        <thead>
                            <tr>
                                <th>Bestellung</th>
                                <th>Essenszeit</th>
                                <th class="text-right">Anz.</th>
                                <th>Gebucht am</th>
                                <th v-if="bookingsDialogCanDelete" class="text-right"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="booking in bookingsDialogData" :key="booking.id">
                                <td>
                                    <div>{{ booking.ordered_for }}</div>
                                    <div
                                        v-if="booking.user_name && booking.user_name !== booking.ordered_for"
                                        class="text-caption text-grey">
                                        {{ booking.user_name }}
                                    </div>
                                </td>
                                <td>{{ formatBookingEatingTime(booking.eating_time) || '–' }}</td>
                                <td class="text-right">{{ booking.quantity }}</td>
                                <td>{{ booking.booked_at || '–' }}</td>
                                <td v-if="bookingsDialogCanDelete" class="text-right">
                                    <v-btn
                                        icon="mdi-close"
                                        size="x-small"
                                        variant="text"
                                        color="error"
                                        title="Buchung löschen"
                                        :data-testid="`delete-booking-${booking.id}`"
                                        @click="requestDeleteBooking(booking)" />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="font-weight-bold">Gesamt</td>
                                <td class="text-right font-weight-bold">{{ bookingsDialogTotalQuantity }}</td>
                                <td :colspan="bookingsDialogCanDelete ? 2 : 1"></td>
                            </tr>
                        </tfoot>
                    </v-table>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn color="primary" variant="flat" @click="bookingsDialog = false">Schließen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteBookingDialog" max-width="480" persistent data-testid="delete-booking-dialog">
            <v-card rounded="xl">
                <v-card-title>Buchung löschen</v-card-title>
                <v-card-text>
                    Soll diese Buchung wirklich gelöscht werden?
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" :disabled="isDeletingBooking" @click="cancelDeleteBooking">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" :loading="isDeletingBooking" @click="confirmDeleteBooking">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="createBookingDialog" max-width="760" persistent data-testid="create-booking-dialog">
            <v-card rounded="xl">
                <v-card-title>Buchung hinzufügen: {{ createBookingDialogEntryTitle }}</v-card-title>
                <v-card-text class="create-booking-dialog">
                    <div class="create-booking-dialog__section">
                        <label class="create-booking-dialog__label" for="create-booking-user-search">Benutzer suchen</label>
                        <input
                            id="create-booking-user-search"
                            v-model="createBookingUserSearch"
                            type="text"
                            class="create-booking-dialog__search"
                            placeholder="Name, E-Mail oder Klasse"
                            data-testid="create-booking-user-search"
                            @input="handleCreateBookingSearchInput">

                        <div v-if="createBookingSearchLoading" class="create-booking-dialog__status">
                            Suche läuft ...
                        </div>

                        <div
                            v-else-if="createBookingSearchResults.length"
                            class="create-booking-dialog__results"
                            data-testid="create-booking-user-results">
                            <button
                                v-for="user in createBookingSearchResults"
                                :key="user.id"
                                type="button"
                                class="create-booking-user-result"
                                :data-testid="`create-booking-user-${user.id}`"
                                @click="selectCreateBookingUser(user)">
                                <strong>{{ user.name }}</strong>
                                <span>{{ user.email }}</span>
                                <span v-if="user.schoolclass">{{ user.schoolclass }}</span>
                            </button>
                        </div>

                        <div
                            v-else-if="createBookingUserSearch.trim().length >= 2"
                            class="create-booking-dialog__status"
                            data-testid="create-booking-user-empty">
                            Keine passenden Benutzer gefunden.
                        </div>
                    </div>

                    <div
                        v-if="createBookingSelectedUser"
                        class="create-booking-selected-user"
                        data-testid="create-booking-selected-user">
                        <strong>{{ createBookingSelectedUser.name }}</strong>
                        <span>{{ createBookingSelectedUser.email }}</span>
                        <span v-if="createBookingSelectedUser.schoolclass">{{ createBookingSelectedUser.schoolclass }}</span>
                    </div>

                    <div v-if="createBookingDialogEatingTimes.length" class="create-booking-dialog__section">
                        <div class="create-booking-dialog__label">Essenszeit</div>
                        <div class="create-booking-dialog__choices">
                            <button
                                v-for="time in createBookingDialogEatingTimes"
                                :key="time.id"
                                type="button"
                                class="create-booking-choice"
                                :class="{ 'is-active': createBookingForm.restaurantEatingTimeId === time.id }"
                                :data-testid="`create-booking-time-${time.id}`"
                                @click="selectCreateBookingTime(time.id)">
                                {{ formatBookingEatingTime(time.eating_time) }}
                            </button>
                        </div>
                    </div>

                    <div class="create-booking-dialog__section">
                        <div class="create-booking-dialog__label">Anzahl</div>
                        <div class="create-booking-dialog__choices">
                            <button
                                v-for="quantity in [1, 2, 3]"
                                :key="quantity"
                                type="button"
                                class="create-booking-choice"
                                :class="{ 'is-active': createBookingForm.quantity === quantity }"
                                :disabled="quantity > createBookingMaxQuantity"
                                :data-testid="`create-booking-quantity-${quantity}`"
                                @click="selectCreateBookingQuantity(quantity)">
                                {{ quantity }}
                            </button>
                        </div>
                    </div>
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" :disabled="createBookingSaving" @click="closeCreateBookingDialog">Abbruch</v-btn>
                    <v-btn color="success" variant="flat" :disabled="!canSubmitCreateBooking" :loading="createBookingSaving" @click="confirmCreateBooking">
                        Hinzufügen
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

function normalizedRangeBounds(start, end) {
    if (! isValidIsoDate(start) || ! isValidIsoDate(end)) {
        return null
    }

    return start <= end ? { start, end } : { start: end, end: start }
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

function emptyIndividualScheduleForm() {
    return {
        visibleStartAt: '',
        visibleEndAt: '',
        orderStartAt: '',
        orderEndAt: '',
    }
}

function emptyPlanScheduleForm() {
    return {
        visibilityStartMode: 'when_available',
        visibilityStartWeekOffset: 2,
        visibilityStartDayOfWeek: 0,
        visibilityStartTime: '15:00',
        orderStartMode: 'when_available',
        orderStartWeekOffset: 2,
        orderStartDayOfWeek: 0,
        orderStartTime: '15:00',
        orderEndWeekOffset: 1,
        orderEndDayOfWeek: 5,
        orderEndTime: '17:00',
        visibilityEndMode: 'plan_end',
    }
}

function emptyCreateBookingForm() {
    return {
        userId: null,
        restaurantEatingTimeId: null,
        quantity: 1,
    }
}

const weekOptions = [
    { value: 2, label: 'Vorvorwoche' },
    { value: 1, label: 'Vorwoche' },
    { value: 0, label: 'Menüwoche' },
]

const dayOptions = [
    { value: 1, label: 'Montag', short: 'Mo' },
    { value: 2, label: 'Dienstag', short: 'Di' },
    { value: 3, label: 'Mittwoch', short: 'Mi' },
    { value: 4, label: 'Donnerstag', short: 'Do' },
    { value: 5, label: 'Freitag', short: 'Fr' },
    { value: 6, label: 'Samstag', short: 'Sa' },
    { value: 0, label: 'Sonntag', short: 'So' },
]

const visibilityEndModeOptions = [
    { value: 'plan_end', label: 'Bis zum letzten Tag des Menüplans' },
    { value: 'week_end', label: 'Bis zum Ende der Woche' },
]

function defaultOnlineSettings() {
    return {
        visibility_start_mode: 'when_available',
        visibility_start_week_offset: 2,
        visibility_start_day_of_week: 0,
        visibility_start_time: '15:00',
        order_start_mode: 'when_available',
        order_start_week_offset: 2,
        order_start_day_of_week: 0,
        order_start_time: '15:00',
        order_end_week_offset: 1,
        order_end_day_of_week: 5,
        order_end_time: '17:00',
        visibility_end_mode: 'plan_end',
    }
}

function createLocalDateFromIso(isoString) {
    if (! isValidIsoDate(isoString)) {
        return null
    }

    const [year, month, day] = isoString.split('-').map((part) => parseInt(part, 10))

    return new Date(year, month - 1, day, 0, 0, 0, 0)
}

function toIsoDateString(date) {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

function addDaysIsoValue(isoString, days) {
    const date = createLocalDateFromIso(isoString)

    if (! date) {
        return ''
    }

    date.setDate(date.getDate() + Number(days || 0))

    return toIsoDateString(date)
}

function startOfWeekIsoValue(isoString) {
    const date = createLocalDateFromIso(isoString)

    if (! date) {
        return ''
    }

    const day = date.getDay()
    const diff = day === 0 ? -6 : 1 - day
    date.setDate(date.getDate() + diff)

    return toIsoDateString(date)
}

function dayOffsetFromMonday(dayOfWeek) {
    const normalized = Number(dayOfWeek)

    return normalized === 0 ? 6 : Math.max(0, normalized - 1)
}

function isoAtTimeValue(isoString, timeString = '00:00', useEndOfDay = false) {
    const date = createLocalDateFromIso(isoString)

    if (! date) {
        return ''
    }

    if (useEndOfDay) {
        date.setHours(23, 59, 0, 0)
    } else {
        const [hours, minutes] = String(timeString || '00:00').split(':').map((part) => parseInt(part, 10) || 0)
        date.setHours(hours, minutes, 0, 0)
    }

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    const hours = String(date.getHours()).padStart(2, '0')
    const minutes = String(date.getMinutes()).padStart(2, '0')

    return `${year}-${month}-${day}T${hours}:${minutes}`
}

function scheduledDateTimeLocal(rangeBounds, weekOffset, dayOfWeek, timeString) {
    if (! rangeBounds?.start) {
        return ''
    }

    const menuWeekStartIso = startOfWeekIsoValue(rangeBounds.start)
    const targetIso = addDaysIsoValue(menuWeekStartIso, dayOffsetFromMonday(dayOfWeek) - (Number(weekOffset) * 7))

    return isoAtTimeValue(targetIso, timeString)
}

function formatScheduleDateTimeValue(dateTimeValue) {
    const [datePart, timePart = ''] = String(dateTimeValue || '').split('T')
    const date = createLocalDateFromIso(datePart)

    if (! date) {
        return ''
    }

    return `${date.toLocaleDateString('de-AT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    })} ${String(timePart).slice(0, 5)}`
}

function individualScheduleFormFromValues(values = {}) {
    return {
        visibleStartAt: String(values.visible_start_at || ''),
        visibleEndAt: String(values.visible_end_at || ''),
        orderStartAt: String(values.order_start_at || ''),
        orderEndAt: String(values.order_end_at || ''),
    }
}

function derivePlanScheduleForm(rangeBounds, onlineSettings, overrides = {}) {
    if (! rangeBounds?.start || ! rangeBounds?.end) {
        return emptyPlanScheduleForm()
    }

    const settings = {
        ...defaultOnlineSettings(),
        ...(onlineSettings || {}),
        ...(overrides || {}),
    }

    return {
        visibilityStartMode: settings.visibility_start_mode,
        visibilityStartWeekOffset: Number(settings.visibility_start_week_offset),
        visibilityStartDayOfWeek: Number(settings.visibility_start_day_of_week),
        visibilityStartTime: String(settings.visibility_start_time || '15:00').slice(0, 5),
        orderStartMode: settings.order_start_mode,
        orderStartWeekOffset: Number(settings.order_start_week_offset),
        orderStartDayOfWeek: Number(settings.order_start_day_of_week),
        orderStartTime: String(settings.order_start_time || '15:00').slice(0, 5),
        orderEndWeekOffset: Number(settings.order_end_week_offset),
        orderEndDayOfWeek: Number(settings.order_end_day_of_week),
        orderEndTime: String(settings.order_end_time || '17:00').slice(0, 5),
        visibilityEndMode: settings.visibility_end_mode,
    }
}

function nonEmptyScheduleOverrides(overrides = {}) {
    return Object.fromEntries(
        Object.entries(overrides).filter(([, value]) => value !== null && value !== undefined && value !== ''),
    )
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
            planScheduleForm: emptyPlanScheduleForm(),
            isPlanAvailable: false,
            activeRangeBounds: null,
            entriesByDate: {},   // { iso: [{ _key, menu, menuTitle, price, comments, eatingTimeIds, eatingTimes }] }
            searchStates: {},    // { iso: { open: bool, query: '' } }
            isSaving: false,
            isPrinting: false,
            isNavigatingBack: false,
            createMenuDialog: false,
            createMenuForDate: null,
            editingMenuId: null,
            isCreateMenuFormValid: false,
            isCreatingMenu: false,
            entryEditDialog: false,
            entryPreviewDialog: false,
            deleteEntryDialog: false,
            deleteBoundaryDayDialog: false,
            deletePlanDialog: false,
            saveReleaseDialog: false,
            bookingsDialog: false,
            bookingsDialogLoading: false,
            bookingsDialogEntryTitle: '',
            bookingsDialogData: [],
            bookingsDialogCanDelete: false,
            bookingsDialogEntryId: null,
            createBookingDialog: false,
            createBookingDialogEntryId: null,
            createBookingDialogEntryTitle: '',
            createBookingDialogEatingTimes: [],
            createBookingForm: emptyCreateBookingForm(),
            createBookingUserSearch: '',
            createBookingSearchResults: [],
            createBookingSearchLoading: false,
            createBookingSelectedUser: null,
            createBookingSearchTimer: null,
            createBookingSaving: false,
            deleteBookingDialog: false,
            deleteBookingTargetId: null,
            isDeletingBooking: false,
            isDeletingPlan: false,
            hasPlanBookings: false,
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
            deleteBoundaryDayTargetIso: '',
            entryEditForm: {
                menuTitle: '',
                price: '',
                comments: '',
            },
            newMenuFoodSearch: '',
            newMenuForm: emptyNewMenuForm(),
            useIndividualScheduleValues: false,
            individualScheduleForm: emptyIndividualScheduleForm(),
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
        ...mapState(useRestaurantStore, ['categories', 'allergenOptions', 'onlineSettings']),

        menus() {
            return useMenuStore().menus
        },

        eatingTimes() {
            return useEatingTimeStore().sortedEatingTimes
        },

        weekOptions() {
            return weekOptions
        },

        dayOptions() {
            return dayOptions
        },

        visibilityStartModeOptions() {
            return [
                { value: 'when_available', label: 'Sobald verfügbar' },
                { value: 'when_orderable', label: 'Sobald bestellbar' },
                { value: 'scheduled', label: 'Fixer Tag' },
            ]
        },

        orderStartModeOptions() {
            return [
                { value: 'when_available', label: 'Sobald verfügbar' },
                { value: 'scheduled', label: 'Fixer Tag' },
            ]
        },

        visibilityEndOptions() {
            return visibilityEndModeOptions
        },

        calculatedScheduleItems() {
            if (!this.rangeBounds) {
                return []
            }

            const resolved = this.resolvedPlanSchedulePayload()
            const visibilityStartsWithOrderStart = this.planScheduleForm.visibilityStartMode === 'when_orderable'
            const orderStartsAutomatically = this.planScheduleForm.orderStartMode !== 'scheduled'
            const isIndividual = this.useIndividualScheduleValues === true

            return [
                {
                    key: 'visibility-start',
                    modelKey: 'visibleStartAt',
                    label: 'Sichtbar ab',
                    value: isIndividual
                        ? this.formatCalculatedScheduleDateTime(this.individualScheduleForm.visibleStartAt)
                        : this.planScheduleForm.visibilityStartMode === 'scheduled'
                            ? this.formatCalculatedScheduleDateTime(resolved.visible_start_at)
                            : 'Sofort',
                    note: isIndividual
                        ? 'Individueller Zeitpunkt'
                        : this.planScheduleForm.visibilityStartMode === 'scheduled'
                            ? ''
                            : visibilityStartsWithOrderStart
                                ? (orderStartsAutomatically ? 'automatisch mit Bestellstart' : 'mit Bestellstart gekoppelt')
                                : 'automatisch bei Freigabe',
                },
                {
                    key: 'order-start',
                    modelKey: 'orderStartAt',
                    label: 'Bestellstart',
                    value: isIndividual
                        ? this.formatCalculatedScheduleDateTime(this.individualScheduleForm.orderStartAt)
                        : this.planScheduleForm.orderStartMode === 'scheduled'
                            ? this.formatCalculatedScheduleDateTime(resolved.order_start_at)
                            : 'Sofort',
                    note: isIndividual
                        ? 'Individueller Zeitpunkt'
                        : this.planScheduleForm.orderStartMode === 'scheduled'
                            ? ''
                            : 'automatisch bei Freigabe',
                },
                {
                    key: 'order-end',
                    modelKey: 'orderEndAt',
                    label: 'Bestellende',
                    value: isIndividual
                        ? this.formatCalculatedScheduleDateTime(this.individualScheduleForm.orderEndAt)
                        : this.formatCalculatedScheduleDateTime(resolved.order_end_at),
                    note: isIndividual ? 'Individueller Zeitpunkt' : '',
                },
                {
                    key: 'visibility-end',
                    modelKey: 'visibleEndAt',
                    label: 'Sichtbar bis',
                    value: isIndividual
                        ? this.formatCalculatedScheduleDateTime(this.individualScheduleForm.visibleEndAt)
                        : this.formatCalculatedScheduleDateTime(resolved.visible_end_at),
                    note: isIndividual
                        ? 'Individueller Zeitpunkt'
                        : this.planScheduleForm.visibilityEndMode === 'week_end'
                            ? 'bis Ende der Kalenderwoche'
                            : 'bis Ende des Menuplans',
                },
            ]
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

        routeRangeBounds() {
            const start = String(this.$route?.query?.start || '')
            const end = String(this.$route?.query?.end || '')

            return normalizedRangeBounds(start, end)
        },

        rangeBounds() {
            return this.activeRangeBounds || this.routeRangeBounds
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
        canDeletePlan() {
            return this.planId !== null && this.hasPlanBookings !== true && ! this.isDeletingPlan && ! this.isSaving
        },
        deletePlanButtonLabel() {
            return this.hasPlanBookings ? 'L\u00f6schen gesperrt' : 'L\u00f6schen'
        },
        deletePlanHint() {
            return 'Mit bestehenden Buchungen kann der Men\u00fcplan nicht gel\u00f6scht werden.'
        },
        deletePlanDialogTitle() {
            return 'Men\u00fcplan l\u00f6schen'
        },
        deletePlanDialogMessage() {
            const label = this.planTitle || this.rangeLabel || 'dieser Men\u00fcplan'

            return `Soll ${label} wirklich vollst\u00e4ndig gel\u00f6scht werden?`
        },
        deletePlanDialogDescription() {
            return 'Der gesamte Zeitraum und alle enthaltenen Men\u00fceintr\u00e4ge werden entfernt.'
        },
        deletePlanConfirmLabel() {
            return 'L\u00f6schen'
        },
        bookingsDialogTotalQuantity() {
            return this.bookingsDialogData.reduce((sum, b) => sum + Number(b.quantity || 0), 0)
        },
        createBookingMaxQuantity() {
            const availableRecipientCount = Number(this.createBookingSelectedUser?.available_recipient_count || 1)

            return Math.max(1, Math.min(3, availableRecipientCount))
        },
        canSubmitCreateBooking() {
            if (! this.createBookingSelectedUser || ! this.createBookingForm.userId || this.createBookingSaving) {
                return false
            }

            if (this.createBookingDialogEatingTimes.length > 0 && ! this.createBookingForm.restaurantEatingTimeId) {
                return false
            }

            return this.createBookingForm.quantity >= 1 && this.createBookingForm.quantity <= this.createBookingMaxQuantity
        },
        deleteBoundaryDayLabel() {
            return isValidIsoDate(this.deleteBoundaryDayTargetIso) ? this.formatDate(this.deleteBoundaryDayTargetIso) : ''
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
            this.deletePlanDialog = false
            this.isDeletingPlan = false
            this.activeRangeBounds = this.routeRangeBounds

            const year = this.rangeBounds ? parseInt(this.rangeBounds.start.substring(0, 4), 10) : new Date().getFullYear()
            freeDayStore.loadYear(year)

            if (! foodStore.foods.length) {
                foodStore.index()
            }

            if (! menuStore.menus.length) {
                menuStore.index()
            }

            if (! restaurantStore.settings) {
                await restaurantStore.loadSettings()
            }

            if (! eatingTimeStore.isLoaded) {
                eatingTimeStore.load()
            }

            if (this.entryMode === 'edit' && this.planId) {
                await this.loadExistingPlan(this.planId)
            } else {
                this.entriesByDate = {}
                this.searchStates = {}
                this.planTitle = ''
                this.planScheduleForm = this.defaultPlanScheduleForm()
                this.useIndividualScheduleValues = false
                this.individualScheduleForm = this.individualScheduleFormFromResolvedSchedule()
                this.isPlanAvailable = false
                this.hasPlanBookings = false
            }
        },

        async loadExistingPlan(id) {
            const plan = await useMenuPlanStore().show(id)

            if (! plan) {
                return
            }

            this.planTitle = plan.title || ''
            this.isPlanAvailable = plan.is_available === true
            this.hasPlanBookings = plan.has_bookings === true
            this.activeRangeBounds = normalizedRangeBounds(plan.start_date, plan.end_date)
            this.planScheduleForm = this.planScheduleFormFromPlan(plan)
            this.useIndividualScheduleValues = plan.use_individual_schedule_values === true
            this.individualScheduleForm = this.useIndividualScheduleValues
                ? this.individualScheduleFormFromPlan(plan)
                : this.individualScheduleFormFromResolvedSchedule()

            const next = {}

            ;(plan.entries || []).forEach((entry) => {
                const iso = entry.plan_date
                const menu = entry.menu || { id: entry.menu_id, title: '?', price: null }

                if (! next[iso]) {
                    next[iso] = []
                }

                next[iso].push({
                    id: entry.id || null,
                    _key: newEntryKey(),
                    menu,
                    menuTitle: entry.menu_title || menu.title || '',
                    price: entry.price != null ? String(entry.price) : (menu.price != null ? String(menu.price) : ''),
                    comments: entry.comments || '',
                    bookedMenuCount: Number(entry.booked_menu_count || 0),
                    canManageBookings: entry.can_manage_bookings === true,
                    eatingTimeIds: entry.eating_time_ids || [],
                    eatingTimes: entry.eating_times || [],
                })
            })

            this.entriesByDate = next
        },

        async togglePlanAvailability() {
            if (! this.canToggleAvailability || this.isSaving) {
                return
            }

            const previousAvailability = this.isPlanAvailable
            const previousScheduleForm = { ...this.planScheduleForm }
            const nextAvailability = ! this.isPlanAvailable

            if (nextAvailability) {
                this.planScheduleForm = this.releasePlanScheduleForm()
            }

            this.isPlanAvailable = nextAvailability

            if (! this.planId) {
                return
            }

            const result = await this.persistPlan(nextAvailability)

            if (! result) {
                this.isPlanAvailable = previousAvailability
                this.planScheduleForm = previousScheduleForm
            }
        },

        // ── Entry management ─────────────────────────────────────────────

        dayEntries(iso) {
            return this.entriesByDate[iso] || []
        },

        findEntry(iso, key) {
            return this.dayEntries(iso).find((entry) => entry?._key === key) || null
        },

        entryBookedMenuCount(entry) {
            return Number(entry?.bookedMenuCount || 0)
        },

        isEntryLocked(entry) {
            return this.entryBookedMenuCount(entry) > 0
        },

        entryCanManageBookings(entry) {
            return this.planId !== null && entry?.id !== null && entry?.canManageBookings === true
        },

        dayHasLockedEntries(iso) {
            return this.dayEntries(iso).some((entry) => this.isEntryLocked(entry))
        },

        entryLockLabel(entry) {
            const bookingCount = this.entryBookedMenuCount(entry)

            if (bookingCount <= 0) {
                return ''
            }

            return bookingCount === 1
                ? 'Gesperrt: 1 Buchung'
                : `Gesperrt: ${bookingCount} Buchungen`
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
                    id: null,
                    _key: newEntryKey(),
                    menu,
                    menuTitle: menu?.title || '',
                    price: menu?.price != null ? String(menu.price) : '',
                    comments: '',
                    bookedMenuCount: 0,
                    canManageBookings: false,
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
            const entry = this.findEntry(iso, key)

            if (! entry || this.isEntryLocked(entry)) {
                return
            }

            this.entriesByDate[iso] = this.entriesByDate[iso].filter((e) => e._key !== key)
        },

        moveEntry(iso, index, direction) {
            const entries = this.entriesByDate[iso]

            if (! Array.isArray(entries)) {
                return
            }

            const currentEntry = entries[index]
            const targetIndex = index + direction

            if (! currentEntry || this.dayHasLockedEntries(iso)) {
                return
            }

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

        formatBookingEatingTime(value) {
            const normalizedValue = String(value || '').trim()

            if (normalizedValue === '') {
                return ''
            }

            return normalizedValue.match(/^\d{2}:\d{2}/)?.[0] || normalizedValue
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
            if (this.isEntryLocked(entry)) {
                return
            }

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
            const entry = this.findEntry(iso, key)

            if (! entry || this.isEntryLocked(entry)) {
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
            const entry = this.findEntry(iso, key)

            if (! entry || this.isEntryLocked(entry)) {
                return
            }

            this.deleteEntryTarget = { iso, key }
            this.deleteEntryDialog = true
        },

        canDeleteBoundaryDay(iso) {
            if (! this.rangeBounds || this.planDays.length <= 1) {
                return false
            }

            if (this.dayHasLockedEntries(iso)) {
                return false
            }

            return iso === this.rangeBounds.start || iso === this.rangeBounds.end
        },

        requestDeleteBoundaryDay(iso) {
            if (! this.canDeleteBoundaryDay(iso)) {
                return
            }

            this.deleteBoundaryDayTargetIso = iso
            this.deleteBoundaryDayDialog = true
        },

        requestDeletePlan() {
            if (! this.canDeletePlan) {
                return
            }

            this.deletePlanDialog = true
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

        async openBookingsDialog(entry) {
            if (! entry?.id || ! this.planId) {
                return
            }

            this.bookingsDialogEntryId = entry.id
            this.bookingsDialogEntryTitle = entry.menuTitle || entry.menu?.title || ''
            this.bookingsDialogData = []
            this.bookingsDialogCanDelete = false
            this.bookingsDialogLoading = true
            this.bookingsDialog = true

            try {
                const response = await axios.get(`/api/admin/restaurant/menu-plans/${this.planId}/entries/${entry.id}/bookings`)
                this.bookingsDialogData = response?.data?.data || []
                this.bookingsDialogCanDelete = response?.data?.meta?.can_delete_bookings === true
            } catch (error) {
                const notification = useNotificationStore()
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Buchungen konnten nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
                this.bookingsDialog = false
            } finally {
                this.bookingsDialogLoading = false
            }
        },

        resetCreateBookingState() {
            if (this.createBookingSearchTimer) {
                clearTimeout(this.createBookingSearchTimer)
                this.createBookingSearchTimer = null
            }

            this.createBookingDialogEntryId = null
            this.createBookingDialogEntryTitle = ''
            this.createBookingDialogEatingTimes = []
            this.createBookingForm = emptyCreateBookingForm()
            this.createBookingUserSearch = ''
            this.createBookingSearchResults = []
            this.createBookingSearchLoading = false
            this.createBookingSelectedUser = null
            this.createBookingSaving = false
        },

        openCreateBookingDialog(entry) {
            if (! this.entryCanManageBookings(entry)) {
                return
            }

            this.resetCreateBookingState()
            this.createBookingDialogEntryId = entry.id
            this.createBookingDialogEntryTitle = entry.menuTitle || entry.menu?.title || ''
            this.createBookingDialogEatingTimes = Array.isArray(entry.eatingTimes) ? entry.eatingTimes : []
            this.createBookingForm.restaurantEatingTimeId = this.createBookingDialogEatingTimes[0]?.id || null
            this.createBookingDialog = true
        },

        closeCreateBookingDialog() {
            this.createBookingDialog = false
            this.resetCreateBookingState()
        },

        handleCreateBookingSearchInput() {
            this.createBookingSelectedUser = null
            this.createBookingForm.userId = null

            if (this.createBookingSearchTimer) {
                clearTimeout(this.createBookingSearchTimer)
                this.createBookingSearchTimer = null
            }

            const query = String(this.createBookingUserSearch || '').trim()

            if (query.length < 2) {
                this.createBookingSearchResults = []
                this.createBookingSearchLoading = false

                return
            }

            this.createBookingSearchTimer = setTimeout(() => {
                this.searchCreateBookingUsers(query)
            }, 250)
        },

        async searchCreateBookingUsers(query) {
            if (! this.planId || ! this.createBookingDialogEntryId) {
                return
            }

            this.createBookingSearchLoading = true

            try {
                const response = await axios.get(
                    `/api/admin/restaurant/menu-plans/${this.planId}/entries/${this.createBookingDialogEntryId}/booking-users`,
                    { params: { search_string: query } },
                )

                if (String(this.createBookingUserSearch || '').trim() === query) {
                    this.createBookingSearchResults = response?.data?.data || []
                }
            } catch (error) {
                useNotificationStore().notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Benutzer konnten nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
            } finally {
                if (String(this.createBookingUserSearch || '').trim() === query) {
                    this.createBookingSearchLoading = false
                }
            }
        },

        selectCreateBookingUser(user) {
            this.createBookingSelectedUser = user
            this.createBookingForm.userId = user.id
            this.createBookingUserSearch = `${user.name} <${user.email}>`
            this.createBookingSearchResults = []

            if (this.createBookingForm.quantity > this.createBookingMaxQuantity) {
                this.createBookingForm.quantity = this.createBookingMaxQuantity
            }
        },

        selectCreateBookingTime(timeId) {
            this.createBookingForm.restaurantEatingTimeId = timeId
        },

        selectCreateBookingQuantity(quantity) {
            if (quantity > this.createBookingMaxQuantity) {
                return
            }

            this.createBookingForm.quantity = quantity
        },

        async confirmCreateBooking() {
            if (! this.planId || ! this.createBookingDialogEntryId || ! this.canSubmitCreateBooking) {
                return
            }

            this.createBookingSaving = true

            try {
                await axios.post(
                    `/api/admin/restaurant/menu-plans/${this.planId}/entries/${this.createBookingDialogEntryId}/bookings`,
                    {
                        data: {
                            user_id: this.createBookingForm.userId,
                            restaurant_eating_time_id: this.createBookingDialogEatingTimes.length > 0
                                ? this.createBookingForm.restaurantEatingTimeId
                                : null,
                            quantity: this.createBookingForm.quantity,
                        },
                    },
                )

                await this.loadExistingPlan(this.planId)
                this.closeCreateBookingDialog()

                useNotificationStore().notify({
                    message: 'Buchung wurde hinzugefügt.',
                    type: 'success',
                    timeout: 2200,
                })
            } catch (error) {
                useNotificationStore().notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Buchung konnte nicht hinzugefügt werden.',
                    type: 'error',
                    timeout: 3000,
                })
            } finally {
                this.createBookingSaving = false
            }
        },

        requestDeleteBooking(booking) {
            if (! this.bookingsDialogCanDelete || ! booking?.id) {
                return
            }

            this.deleteBookingTargetId = booking.id
            this.deleteBookingDialog = true
        },

        cancelDeleteBooking() {
            if (this.isDeletingBooking) {
                return
            }

            this.deleteBookingDialog = false
            this.deleteBookingTargetId = null
        },

        async confirmDeleteBooking() {
            if (! this.planId || ! this.bookingsDialogEntryId || ! this.deleteBookingTargetId) {
                return
            }

            this.isDeletingBooking = true

            try {
                await axios.delete(`/api/admin/restaurant/menu-plans/${this.planId}/entries/${this.bookingsDialogEntryId}/bookings/${this.deleteBookingTargetId}`)

                this.bookingsDialogData = this.bookingsDialogData.filter((booking) => booking.id !== this.deleteBookingTargetId)
                await this.loadExistingPlan(this.planId)
                this.isDeletingBooking = false
                this.cancelDeleteBooking()

                useNotificationStore().notify({
                    message: 'Buchung wurde gelöscht.',
                    type: 'success',
                    timeout: 2200,
                })
            } catch (error) {
                useNotificationStore().notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Buchung konnte nicht gelöscht werden.',
                    type: 'error',
                    timeout: 3000,
                })
            } finally {
                this.isDeletingBooking = false
            }
        },

        cancelDeleteEntry() {
            this.deleteEntryDialog = false
            this.deleteEntryTarget = {
                iso: '',
                key: '',
            }
        },

        async confirmDeleteEntry() {
            const targetIso = this.deleteEntryTarget.iso
            const targetKey = this.deleteEntryTarget.key
            const entry = this.findEntry(targetIso, targetKey)

            this.removeEntry(targetIso, targetKey)
            this.cancelDeleteEntry()

            if (! this.planId || ! entry?.id) {
                return
            }

            const persisted = await this.persistPlan()

            if (! persisted) {
                await this.loadExistingPlan(this.planId)
            }
        },

        cancelDeleteBoundaryDay() {
            this.deleteBoundaryDayDialog = false
            this.deleteBoundaryDayTargetIso = ''
        },

        cancelDeletePlan() {
            if (this.isDeletingPlan) {
                return
            }

            this.deletePlanDialog = false
        },

        confirmDeleteBoundaryDay() {
            const targetIso = this.deleteBoundaryDayTargetIso

            if (! this.canDeleteBoundaryDay(targetIso)) {
                this.cancelDeleteBoundaryDay()
                return
            }

            const nextBounds = targetIso === this.rangeBounds.start
                ? normalizedRangeBounds(this.addDaysIso(this.rangeBounds.start, 1), this.rangeBounds.end)
                : normalizedRangeBounds(this.rangeBounds.start, this.addDaysIso(this.rangeBounds.end, -1))

            this.activeRangeBounds = nextBounds

            const { [targetIso]: _removedEntries, ...remainingEntriesByDate } = this.entriesByDate
            const { [targetIso]: _removedSearchState, ...remainingSearchStates } = this.searchStates

            this.entriesByDate = remainingEntriesByDate
            this.searchStates = remainingSearchStates
            this.cancelDeleteBoundaryDay()
        },

        async confirmDeletePlan() {
            if (! this.canDeletePlan || ! this.planId) {
                return
            }

            this.isDeletingPlan = true

            try {
                const deleted = await useMenuPlanStore().destroy(this.planId)

                if (! deleted) {
                    await this.loadExistingPlan(this.planId)
                    return
                }

                this.deletePlanDialog = false

                this.$router.push(this.backTarget).catch(() => {})
            } finally {
                this.isDeletingPlan = false
            }
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

                    const previousMenuTitle = String(entry?.menu?.title || '')
                    const previousMenuPrice = entry?.menu?.price ?? null
                    const usesMenuBaseTitle = entry?.menuTitle === ''
                        || String(entry?.menuTitle ?? '') === previousMenuTitle
                    const usesMenuBasePrice = entry?.price === ''
                        || String(entry?.price ?? '') === String(previousMenuPrice ?? '')

                    return {
                        ...entry,
                        menu: updatedMenu,
                        menuTitle: usesMenuBaseTitle
                            ? ''
                            : entry?.menuTitle ?? '',
                        price: usesMenuBasePrice
                            ? (updatedMenu?.price != null ? String(updatedMenu.price) : '')
                            : entry?.price ?? '',
                    }
                })
            })

            this.entriesByDate = nextEntriesByDate
        },

        async saveEntryEdit() {
            const entries = this.entriesByDate[this.entryEditTarget.iso]
            const entry = this.findEntry(this.entryEditTarget.iso, this.entryEditTarget.key)

            if (! Array.isArray(entries) || ! entry || this.isEntryLocked(entry)) {
                return
            }

            const nextMenuTitle = String(this.entryEditForm.menuTitle || '').trim()
            const nextPrice = this.normalizeNewMenuPricePayload(this.entryEditForm.price)
            const menuId = Number(entry?.menu?.id || 0)

            if (menuId > 0) {
                const menuFoods = this.sortedFoods(entry?.menu?.foods || []).map((food) => food.id)
                const nextMenuPayload = {
                    title: nextMenuTitle !== '' ? nextMenuTitle : String(entry?.menu?.title || ''),
                    price: nextPrice !== '' ? nextPrice : (entry?.menu?.price ?? ''),
                    food_ids: menuFoods,
                }
                const savedMenu = await useMenuStore().update(menuId, nextMenuPayload)

                if (! savedMenu) {
                    return
                }

                this.applyEditedMenuToEntries(savedMenu)
            }

            const currentEntries = this.entriesByDate[this.entryEditTarget.iso] || entries

            this.entriesByDate[this.entryEditTarget.iso] = currentEntries.map((entry) => {
                if (entry?._key !== this.entryEditTarget.key) {
                    return entry
                }

                return {
                    ...entry,
                    menuTitle: nextMenuTitle,
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
                const createMenuForDate = this.createMenuForDate
                const editingMenuId = this.editingMenuId
                const payload = {
                    title: this.newMenuForm.title,
                    price: this.normalizeNewMenuPricePayload(this.newMenuForm.price),
                    food_ids: this.newMenuForm.foodIds,
                }
                const saved = editingMenuId
                    ? await menuStore.update(editingMenuId, payload)
                    : await menuStore.store(payload)

                if (! saved) {
                    return
                }

                if (editingMenuId) {
                    this.applyEditedMenuToEntries(saved)
                } else if (createMenuForDate) {
                    this.addEntryForDay(createMenuForDate, saved)
                    this.closeSearch(createMenuForDate)
                }

                this.closeCreateMenuDialog()
            } finally {
                this.isCreatingMenu = false
            }
        },

        // ── Save plan ────────────────────────────────────────────────────

        menuPlanOnlineSettings() {
            return {
                ...defaultOnlineSettings(),
                ...(this.onlineSettings || {}),
            }
        },

        individualScheduleFormFromPlan(plan) {
            return individualScheduleFormFromValues({
                visible_start_at: plan?.visible_start_at,
                visible_end_at: plan?.visible_end_at,
                order_start_at: plan?.order_start_at,
                order_end_at: plan?.order_end_at,
            })
        },

        individualScheduleFormFromResolvedSchedule() {
            return individualScheduleFormFromValues(this.resolvedPlanSchedulePayload())
        },

        handleIndividualScheduleToggle() {
            if (this.useIndividualScheduleValues !== true) {
                return
            }

            const hasAnyValue = Object.values(this.individualScheduleForm).some((value) => String(value || '').trim() !== '')

            if (!hasAnyValue) {
                this.individualScheduleForm = this.individualScheduleFormFromResolvedSchedule()
            }
        },

        formatCalculatedScheduleDateTime(dateTimeValue) {
            return formatScheduleDateTimeValue(dateTimeValue) || 'Kein fester Zeitpunkt'
        },

        defaultPlanScheduleForm() {
            return derivePlanScheduleForm(this.rangeBounds, this.menuPlanOnlineSettings())
        },

        planScheduleFormFromPlan(plan) {
            return derivePlanScheduleForm(
                normalizedRangeBounds(plan?.start_date, plan?.end_date),
                this.menuPlanOnlineSettings(),
            )
        },

        releasePlanScheduleForm() {
            return { ...this.planScheduleForm }
        },

        resetPlanScheduleDefaults() {
            this.planScheduleForm = this.defaultPlanScheduleForm()
        },

        resolvedPlanSchedulePayload() {
            const scheduleForm = { ...this.planScheduleForm }

            if (
                scheduleForm.visibilityStartMode === 'scheduled'
                && scheduleForm.orderStartMode === 'scheduled'
                && this.scheduledPosition(
                    scheduleForm.visibilityStartWeekOffset,
                    scheduleForm.visibilityStartDayOfWeek,
                    scheduleForm.visibilityStartTime,
                ) > this.scheduledPosition(
                    scheduleForm.orderStartWeekOffset,
                    scheduleForm.orderStartDayOfWeek,
                    scheduleForm.orderStartTime,
                )
            ) {
                scheduleForm.visibilityStartWeekOffset = scheduleForm.orderStartWeekOffset
                scheduleForm.visibilityStartDayOfWeek = scheduleForm.orderStartDayOfWeek
                scheduleForm.visibilityStartTime = scheduleForm.orderStartTime
            }

            return {
                visibility_start_mode: scheduleForm.visibilityStartMode,
                visibility_start_week_offset: scheduleForm.visibilityStartMode === 'scheduled' ? Number(scheduleForm.visibilityStartWeekOffset) : null,
                visibility_start_day_of_week: scheduleForm.visibilityStartMode === 'scheduled' ? Number(scheduleForm.visibilityStartDayOfWeek) : null,
                visibility_start_time: scheduleForm.visibilityStartMode === 'scheduled' ? String(scheduleForm.visibilityStartTime || '').slice(0, 5) : null,
                order_start_mode: scheduleForm.orderStartMode,
                order_start_week_offset: scheduleForm.orderStartMode === 'scheduled' ? Number(scheduleForm.orderStartWeekOffset) : null,
                order_start_day_of_week: scheduleForm.orderStartMode === 'scheduled' ? Number(scheduleForm.orderStartDayOfWeek) : null,
                order_start_time: scheduleForm.orderStartMode === 'scheduled' ? String(scheduleForm.orderStartTime || '').slice(0, 5) : null,
                order_end_week_offset: Number(scheduleForm.orderEndWeekOffset),
                order_end_day_of_week: Number(scheduleForm.orderEndDayOfWeek),
                order_end_time: String(scheduleForm.orderEndTime || '').slice(0, 5),
                visibility_end_mode: scheduleForm.visibilityEndMode,
                visible_start_at: scheduleForm.visibilityStartMode === 'scheduled'
                    ? scheduledDateTimeLocal(this.rangeBounds, scheduleForm.visibilityStartWeekOffset, scheduleForm.visibilityStartDayOfWeek, scheduleForm.visibilityStartTime)
                    : null,
                visible_end_at: scheduleForm.visibilityEndMode === 'week_end'
                    ? isoAtTimeValue(addDaysIsoValue(startOfWeekIsoValue(this.rangeBounds?.end), 6), '23:59')
                    : isoAtTimeValue(this.rangeBounds?.end, '23:59'),
                order_start_at: scheduleForm.orderStartMode === 'scheduled'
                    ? scheduledDateTimeLocal(this.rangeBounds, scheduleForm.orderStartWeekOffset, scheduleForm.orderStartDayOfWeek, scheduleForm.orderStartTime)
                    : null,
                order_end_at: scheduledDateTimeLocal(this.rangeBounds, scheduleForm.orderEndWeekOffset, scheduleForm.orderEndDayOfWeek, scheduleForm.orderEndTime),
            }
        },

        scheduledPosition(weekOffset, dayOfWeek, timeString) {
            const [hours, minutes] = String(timeString || '00:00').split(':').map((value) => parseInt(value, 10) || 0)

            return (dayOffsetFromMonday(dayOfWeek) * 1440)
                - (Number(weekOffset) * 7 * 1440)
                + (hours * 60)
                + minutes
        },

        buildPayload(forcedAvailability = null) {
            const entries = []
            const isAvailable = typeof forcedAvailability === 'boolean'
                ? forcedAvailability
                : (this.canToggleAvailability ? this.isPlanAvailable : false)
            const resolvedSchedule = this.resolvedPlanSchedulePayload()
            const useIndividualScheduleValues = this.useIndividualScheduleValues === true

            Object.entries(this.entriesByDate).forEach(([iso, dayEntries]) => {
                dayEntries.forEach((entry) => {
                    entries.push({
                        ...(entry.id ? { id: entry.id } : {}),
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
                is_available: isAvailable,
                ...resolvedSchedule,
                use_individual_schedule_values: useIndividualScheduleValues,
                visible_start_at: useIndividualScheduleValues ? this.individualScheduleForm.visibleStartAt || null : null,
                visible_end_at: useIndividualScheduleValues ? this.individualScheduleForm.visibleEndAt || null : null,
                order_start_at: useIndividualScheduleValues ? this.individualScheduleForm.orderStartAt || null : null,
                order_end_at: useIndividualScheduleValues ? this.individualScheduleForm.orderEndAt || null : null,
                entries,
            }
        },

        async savePlan() {
            if (! this.rangeBounds) {
                return
            }

            if (this.canToggleAvailability && ! this.isPlanAvailable) {
                this.saveReleaseDialog = true
                return
            }

            await this.persistPlan()
        },

        async confirmSaveWithoutRelease() {
            this.saveReleaseDialog = false
            await this.persistPlan(false)
        },

        async confirmSaveAndRelease() {
            this.saveReleaseDialog = false
            this.planScheduleForm = this.releasePlanScheduleForm()
            await this.persistPlan(true)
        },

        async persistPlan(forcedAvailability = null) {
            this.isSaving = true

            try {
                const store = useMenuPlanStore()
                const payload = this.buildPayload(forcedAvailability)
                let result

                if (this.planId) {
                    result = await store.update(this.planId, payload)
                } else {
                    result = await store.store(payload)
                }

                if (result && result.id && result.start_date && result.end_date) {
                    this.isPlanAvailable = typeof result.is_available === 'boolean' ? result.is_available : payload.is_available === true
                    this.hasPlanBookings = result.has_bookings === true
                    this.activeRangeBounds = normalizedRangeBounds(result.start_date, result.end_date)

                    this.$router.replace({
                        query: {
                            ...this.$route.query,
                            mode: 'edit',
                            plan_id: result.id,
                            start: result.start_date,
                            end: result.end_date,
                        },
                    }).catch(() => {})
                }

                return result
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

        async navigateBack() {
            if (this.isNavigatingBack) {
                return
            }

            this.isNavigatingBack = true

            try {
                const navigationFailure = await this.$router.push(this.backTarget)

                if (navigationFailure) {
                    this.isNavigatingBack = false
                }

                return navigationFailure
            } catch (error) {
                this.isNavigatingBack = false
                throw error
            }
        },

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

        entryFoodSummary(entry) {
            const foods = this.sortedFoods(entry.menu?.foods || [])
            if (foods.length === 0) {
                return ''
            }
            return foods.map((f) => f.title).join(', ')
        },
    },
}
</script>

<style scoped>
/* ---- Page ---- */

.mpe-page {
    position: relative;
    min-height: 100vh;
    background: linear-gradient(160deg, #fafaf8 0%, #f5ede0 100%);
}

.mpe-page--busy {
    user-select: none;
}

.mpe-overlay {
    position: fixed;
    inset: 0;
    z-index: 40;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(255, 255, 255, 0.38);
    backdrop-filter: blur(2px);
}

.mpe-overlay__content {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 999px;
    background: rgba(255, 247, 237, 0.98);
    border: 1px solid rgba(234, 88, 12, 0.18);
    color: #9a3412;
    font-weight: 700;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
}

.mpe-overlay__spinner {
    width: 18px;
    height: 18px;
    border-radius: 999px;
    border: 2px solid rgba(234, 88, 12, 0.2);
    border-top-color: #ea580c;
    animation: mpe-overlay-spin 0.7s linear infinite;
}

.mpe-overlay__text {
    font-size: 0.92rem;
    line-height: 1.2;
}

@keyframes mpe-overlay-spin {
    to {
        transform: rotate(360deg);
    }
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

.mpe-header__hint {
    max-width: 360px;
    font-size: 0.82rem;
    line-height: 1.4;
    color: rgba(248, 250, 252, 0.72);
    text-align: right;
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

.mpe-schedule-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.mpe-schedule-actions__hint {
    color: #64748b;
    font-size: 0.92rem;
}

.mpe-schedule-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}

.mpe-schedule-card {
    padding: 16px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 18px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.94));
}

.mpe-schedule-card__title {
    margin-bottom: 12px;
    font-size: 0.82rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.mpe-schedule-card__fields {
    display: grid;
    gap: 10px;
    margin-top: 12px;
}

.mpe-schedule-card__fields--always-open {
    margin-top: 0;
}

.mpe-schedule-preview {
    margin-bottom: 18px;
    padding: 16px;
    border: 1px solid rgba(59, 130, 246, 0.16);
    border-radius: 18px;
    background: linear-gradient(160deg, rgba(239, 246, 255, 0.94), rgba(248, 250, 252, 0.98));
}

.mpe-schedule-preview__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.mpe-schedule-preview__eyebrow {
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #1d4ed8;
}

.mpe-schedule-preview__title {
    font-size: 1rem;
    font-weight: 800;
    color: #1e293b;
}

.mpe-schedule-preview__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 10px;
}

.mpe-schedule-preview__item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    padding: 12px 14px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(148, 163, 184, 0.18);
}

.mpe-schedule-preview__label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
}

.mpe-schedule-preview__value {
    font-size: 0.98rem;
    font-weight: 800;
    line-height: 1.35;
    color: #0f172a;
}

.mpe-schedule-preview__note {
    font-size: 0.82rem;
    line-height: 1.35;
    color: #475569;
}

.mpe-schedule-preview__toggle {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-height: 44px;
    padding: 10px 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.86);
    border: 1px solid rgba(148, 163, 184, 0.22);
    cursor: pointer;
}

.mpe-schedule-preview__toggle-input {
    width: 18px;
    height: 18px;
    accent-color: #2563eb;
}

.mpe-schedule-preview__toggle-copy {
    font-size: 0.88rem;
    font-weight: 700;
    color: #1e293b;
}

.mpe-schedule-preview__input {
    width: 100%;
    min-height: 44px;
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.95);
    padding: 10px 12px;
    font: inherit;
    color: #0f172a;
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

.mpe-day__header-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.save-release-dialog > .v-card-title {
    display: none;
}

.save-release-dialog__hero {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 24px 24px 18px;
    background: linear-gradient(135deg, #fff8eb 0%, #ffedd5 100%);
    border-bottom: 1px solid rgba(245, 158, 11, 0.18);
}

.save-release-dialog__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 54px;
    height: 54px;
    border-radius: 16px;
    background: rgba(245, 158, 11, 0.16);
    flex-shrink: 0;
}

.save-release-dialog__hero-copy {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.save-release-dialog__eyebrow {
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #b45309;
}

.save-release-dialog__title {
    font-size: 1.4rem;
    font-weight: 800;
    line-height: 1.2;
    color: #111827;
}

.save-release-dialog__body {
    display: grid;
    gap: 14px;
    padding-top: 20px;
}

.save-release-dialog__alert {
    margin-bottom: 0;
}

.save-release-dialog__question {
    font-size: 1rem;
    font-weight: 700;
    color: #1f2937;
}

.save-release-dialog__actions {
    gap: 10px;
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

.mpe-entry-card--locked {
    background: linear-gradient(160deg, #fff7ed, #ffedd5);
    border-color: rgba(194, 65, 12, 0.26);
}

.mpe-entry-card__header {
    margin-bottom: 6px;
}

.mpe-entry-card__title-row {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.mpe-entry-card__actions {
    display: flex;
    align-items: center;
    gap: 2px;
    flex-wrap: wrap;
    justify-content: flex-end;
    margin-bottom: 6px;
}

.mpe-entry-card__title-block {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.mpe-entry-card__title {
    font-size: 0.88rem;
    font-weight: 700;
    color: #1f2937;
    line-height: 1.4;
    word-break: break-word;
}

.mpe-entry-card__food-summary {
    font-size: 0.76rem;
    color: #6b7280;
    line-height: 1.3;
    word-break: break-word;
}

.mpe-entry-card__lock {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border-radius: 999px;
    background: rgba(194, 65, 12, 0.1);
    color: #9a3412;
    font-size: 0.7rem;
    font-weight: 700;
    white-space: nowrap;
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

.mpe-entry-card__lock-note {
    margin-top: 6px;
    font-size: 0.75rem;
    line-height: 1.45;
    color: #9a3412;
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
    .mpe-schedule-actions { flex-direction: column; align-items: stretch; }
    .mpe-schedule-preview__header { flex-direction: column; }
}

@media (max-width: 640px) {
    .mpe-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .mpe-board { grid-template-columns: 1fr; }
    .mpe-preview-food { grid-template-columns: 1fr; }
}

.mpe-bookings-table {
    font-size: 0.85rem;
}

.mpe-bookings-table th {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #6b7280;
}

.mpe-bookings-table tfoot td {
    border-top: 2px solid #e5e7eb;
}

.create-booking-dialog {
    display: grid;
    gap: 16px;
}

.create-booking-dialog__section {
    display: grid;
    gap: 8px;
}

.create-booking-dialog__label {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #6b7280;
}

.create-booking-dialog__search {
    width: 100%;
    min-height: 44px;
    padding: 10px 12px;
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.98);
    font: inherit;
    color: #0f172a;
}

.create-booking-dialog__results {
    display: grid;
    gap: 8px;
}

.create-booking-user-result {
    display: grid;
    gap: 2px;
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: #ffffff;
    text-align: left;
    cursor: pointer;
}

.create-booking-user-result:hover {
    border-color: rgba(34, 197, 94, 0.35);
    background: #f0fdf4;
}

.create-booking-selected-user {
    display: grid;
    gap: 2px;
    padding: 12px 14px;
    border-radius: 14px;
    background: linear-gradient(160deg, #f0fdf4, #dcfce7);
    border: 1px solid rgba(34, 197, 94, 0.18);
    color: #166534;
}

.create-booking-dialog__choices {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.create-booking-choice {
    min-width: 56px;
    min-height: 40px;
    padding: 8px 12px;
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 999px;
    background: #ffffff;
    color: #334155;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}

.create-booking-choice.is-active {
    border-color: rgba(22, 163, 74, 0.4);
    background: #dcfce7;
    color: #166534;
}

.create-booking-choice:disabled {
    cursor: not-allowed;
    opacity: 0.45;
}

.create-booking-dialog__status {
    font-size: 0.88rem;
    color: #64748b;
}
</style>
