<template>
    <div class="students-timetable-v2-page">
        <v-row v-if="reviewFlowVisible" dense align="stretch">
            <v-col cols="12" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-review-card">
                    <v-card-text class="students-timetable-v2-review-card__content">
                        <div class="students-timetable-v2-review-card__main">
                            <div class="students-timetable-v2-review-card__identity">
                                <v-icon :icon="storedTimetableStudentContext ? 'mdi-account-school-outline' : 'mdi-account-off-outline'" size="20" color="primary" />
                                <strong>{{ courseReviewStudentLabel }}</strong>
                            </div>
                            <div class="students-timetable-v2-review-card__selection">
                                <v-chip
                                    v-for="item in courseReviewSelectionSummary"
                                    :key="item.key"
                                    size="small"
                                    class="students-timetable-v2-review-card__selection-chip"
                                    variant="flat">
                                    {{ item.label }}: {{ item.value }}
                                </v-chip>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" class="students-timetable-v2-card-column students-timetable-v2-selected-courses-column">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-selected-courses-card">
                    <v-card-title class="students-timetable-v2-selected-courses-card__title">
                        <span>Ausgewählte Kurse</span>
                        <span v-if="selectedCourseItemsClickable" class="students-timetable-v2-selected-courses-card__filters">
                            <v-btn
                                v-for="option in offeredCourseBulkSelectionOptions"
                                :key="option.key"
                                size="small"
                                variant="tonal"
                                color="primary"
                                @click="applyOfferedCourseBulkSelection(option.key)">
                                {{ option.label }}
                            </v-btn>
                        </span>
                        <span class="students-timetable-v2-selected-courses-card__summary">
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ selectedCourseSummary.countLabel }}
                            </v-chip>
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ selectedCourseSummary.hoursLabel }}
                            </v-chip>
                        </span>
                    </v-card-title>
                    <v-card-text>
                        <div v-if="selectedCourseItems.length" class="students-timetable-v2-selected-courses-card__list">
                            <v-chip
                                v-for="course in selectedCourseItems"
                                :key="course.selectionKey"
                                size="small"
                                color="success"
                                :variant="selectedCourseItemActive(course) ? 'flat' : 'tonal'"
                                :closable="selectedCourseItemsDeletable"
                                :disabled="selectedTimetableSummaryChipsDisabled"
                                :close-label="`Kurs ${course.label} entfernen`"
                                close-icon="mdi-close"
                                class="students-timetable-v2-selected-courses-card__course"
                                :class="{
                                    'students-timetable-v2-selected-courses-card__course--active': selectedCourseItemActive(course),
                                    'students-timetable-v2-selected-courses-card__course--passive': !selectedCourseItemsClickable || selectedTimetableSummaryChipsDisabled,
                                    'students-timetable-v2-selected-courses-card__course--offered-deselected': offeredCourseItemsAllDeselected(course),
                                }"
                                :role="selectedCourseItemsClickable && !selectedTimetableSummaryChipsDisabled ? 'button' : undefined"
                                :aria-pressed="selectedCourseItemsClickable && !selectedTimetableSummaryChipsDisabled ? (selectedCourseItemActive(course) ? 'true' : 'false') : undefined"
                                @click:close.stop="removeSelectedCourseItem(course)"
                                @click="selectReviewCourse(course)">
                                <span>{{ course.label }}</span>
                                <span v-if="course.meta" class="students-timetable-v2-selected-courses-card__meta">
                                    {{ course.meta }}
                                </span>
                            </v-chip>
                        </div>
                        <v-alert v-else type="info" variant="tonal" density="compact">
                            Keine Kurse ausgewählt.
                        </v-alert>
                    </v-card-text>
                </v-card>
                <v-card
                    v-if="selectedTimetableOptionItems.length"
                    rounded="lg"
                    class="students-timetable-v2-card students-timetable-v2-selected-options-card">
                    <v-card-title class="students-timetable-v2-selected-options-card__title">
                        Optionen
                    </v-card-title>
                    <v-card-text>
                        <div class="students-timetable-v2-selected-options-card__list">
                            <v-chip
                                v-for="option in selectedTimetableOptionItems"
                                :key="option.key"
                                size="small"
                                color="success"
                                variant="tonal"
                                prepend-icon="mdi-check"
                                :closable="selectedTimetableOptionItemsDeletable"
                                :disabled="selectedTimetableSummaryChipsDisabled"
                                :close-label="`Option ${option.label} entfernen`"
                                close-icon="mdi-close"
                                @click:close.stop="removeSelectedTimetableOption(option)">
                                {{ option.label }}
                            </v-chip>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="courseReviewVisible && selectedReviewCourseItem" cols="12" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-offered-courses-card">
                    <v-card-title class="students-timetable-v2-offered-courses-card__title">
                        <span>Angebotene Kurse</span>
                        <v-chip size="x-small" color="primary" variant="tonal">
                            {{ selectedReviewCourseItem.label }}
                        </v-chip>
                    </v-card-title>
                    <v-card-text>
                        <v-progress-linear
                            v-if="courseGroupsLoading"
                            indeterminate
                            color="primary"
                            class="students-timetable-v2-completed-courses__loading" />
                        <v-alert v-else-if="courseGroupsError" type="error" variant="tonal" density="compact">
                            {{ courseGroupsError }}
                        </v-alert>
                        <div v-else-if="selectedReviewOfferedCourseItems.length" class="students-timetable-v2-offered-courses-card__list">
                            <div
                                v-for="course in selectedReviewOfferedCourseItems"
                                :key="course.key"
                                class="students-timetable-v2-offered-courses-card__item students-timetable-v2-offered-courses-card__item--toggle"
                                :class="{
                                    'students-timetable-v2-offered-courses-card__item--selected': offeredCourseSelected(course),
                                    'students-timetable-v2-offered-courses-card__item--deselected': !offeredCourseSelected(course),
                                }"
                                role="button"
                                tabindex="0"
                                :aria-pressed="offeredCourseSelected(course) ? 'true' : 'false'"
                                @click="toggleOfferedCourseItem(course)"
                                @keydown.enter.prevent="toggleOfferedCourseItem(course)"
                                @keydown.space.prevent="toggleOfferedCourseItem(course)">
                                <v-icon v-if="offeredCourseSelected(course)" icon="mdi-check" size="16" color="success" />
                                <span class="students-timetable-v2-offered-courses-card__name">
                                    {{ course.name || course.code }}
                                </span>
                                <span v-if="offeredCourseCodeVisible(course)" class="students-timetable-v2-offered-courses-card__code">
                                    {{ course.code }}
                                </span>
                                <v-chip v-if="course.scheduleLabel" size="x-small" color="primary" variant="tonal">
                                    {{ course.scheduleLabel }}
                                </v-chip>
                                <v-chip v-if="course.recurrenceLabel" size="x-small" color="primary" variant="tonal">
                                    {{ course.recurrenceLabel }}
                                </v-chip>
                                <v-chip
                                    v-if="course.distanceLearning"
                                    size="x-small"
                                    color="warning"
                                    variant="tonal"
                                    title="Fernunterricht">
                                    Fernunterricht
                                </v-chip>
                                <v-chip v-if="course.roomsLabel" size="x-small" color="secondary" variant="outlined">
                                    {{ course.roomsLabel }}
                                </v-chip>
                            </div>
                        </div>
                        <v-alert v-else type="info" variant="tonal" density="compact">
                            Keine angebotenen Kurse gefunden.
                        </v-alert>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="reviewButtonCardVisible" cols="12" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-restart-card">
                    <v-card-text class="students-timetable-v2-restart-card__content">
                        <v-btn
                            color="error"
                            variant="tonal"
                            size="large"
                            prepend-icon="mdi-restart"
                            @click="restartTimetableV2">
                            Neustart
                        </v-btn>
                        <div class="students-timetable-v2-restart-card__navigation-actions">
                            <v-btn
                                color="primary"
                                variant="tonal"
                                size="large"
                                prepend-icon="mdi-arrow-left"
                                @click="backToCourseSelection">
                                Zurück
                            </v-btn>
                            <v-btn
                                color="success"
                                variant="tonal"
                                size="large"
                                append-icon="mdi-arrow-right"
                                @click="openTimetableCalculation">
                                Weiter
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="timetableCalculationVisible" cols="12" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-calculation-card">
                    <v-card-title class="students-timetable-v2-calculation-card__title">
                        <span class="students-timetable-v2-calculation-card__heading">
                            <span>Stundenpläne</span>
                            <v-progress-circular
                                v-if="moreCourseAvailabilityLoading"
                                indeterminate
                                size="18"
                                width="2"
                                color="primary"
                                class="students-timetable-v2-calculation-card__availability-loader"
                                title="Mehr Kurse werden geprüft"
                                aria-label="Mehr Kurse werden geprüft" />
                        </span>
                        <span v-if="timetableOptionsCardVisible" class="students-timetable-v2-calculation-card__actions">
                            <v-btn
                                size="large"
                                color="warning"
                                variant="tonal"
                                prepend-icon="mdi-close"
                                @click="closeTimetableOptionsCard">
                                Abbruch
                            </v-btn>
                            <v-btn
                                size="large"
                                color="success"
                                variant="flat"
                                append-icon="mdi-check"
                                :disabled="!timetableOptionsChanged || timetableCalculationLoading"
                                @click="applyTimetableOptions">
                                Anwenden
                            </v-btn>
                        </span>
                        <span v-else-if="!moreCoursesVisible" class="students-timetable-v2-calculation-card__actions">
                            <span class="students-timetable-v2-calculation-card__primary-actions">
                                <v-btn
                                    size="large"
                                    :color="moreCoursesButtonUnavailable ? 'error' : 'primary'"
                                    variant="tonal"
                                    prepend-icon="mdi-plus-circle-outline"
                                    :disabled="timetableCalculationLoading || moreCourseAvailabilityLoading || moreCoursesButtonUnavailable"
                                    @click="toggleMoreCoursesCard">
                                    Mehr Kurse
                                </v-btn>
                                <v-btn
                                    size="large"
                                    color="info"
                                    variant="tonal"
                                    prepend-icon="mdi-cog-outline"
                                    :disabled="timetableCalculationLoading || moreCourseAvailabilityLoading"
                                    :aria-expanded="timetableOptionsCardVisible ? 'true' : 'false'"
                                    @click="toggleTimetableOptionsCard">
                                    Optionen
                                </v-btn>
                            </span>
                            <v-btn
                                size="large"
                                color="warning"
                                variant="tonal"
                                prepend-icon="mdi-restore"
                                class="students-timetable-v2-calculation-card__reset-button"
                                :disabled="timetableCalculationLoading || !timetableCalculationResetAvailable"
                                @click="resetTimetableCalculationChanges">
                                Zurücksetzen
                            </v-btn>
                        </span>
                        <span v-else class="students-timetable-v2-calculation-card__more-course-actions">
                            <v-btn
                                size="large"
                                color="warning"
                                variant="tonal"
                                prepend-icon="mdi-close"
                                @click="cancelMoreCoursesCard">
                                Abbruch
                            </v-btn>
                            <v-btn
                                size="large"
                                color="success"
                                variant="tonal"
                                append-icon="mdi-check"
                                :disabled="!moreCoursesSelectionChanged || timetableCalculationLoading"
                                @click="applyMoreCoursesSelection">
                                Anwenden
                            </v-btn>
                        </span>
                    </v-card-title>
                    <v-card-text class="students-timetable-v2-calculation-card__content">
                        <v-progress-linear
                            v-if="timetableCalculationLoading"
                            indeterminate
                            color="primary"
                            class="students-timetable-v2-completed-courses__loading" />
                        <v-alert
                            v-if="timetableCalculationLoading"
                            type="info"
                            variant="tonal"
                            density="compact"
                            icon="mdi-calculator-variant-outline">
                            Die Stundenpläne werden berechnet.
                        </v-alert>
                        <v-alert
                            v-else-if="timetableCalculationError"
                            type="error"
                            variant="tonal"
                            density="compact">
                            {{ timetableCalculationError }}
                        </v-alert>
                        <v-alert
                            v-else-if="timetableCalculationResult && !moreCoursesVisible && !timetableOptionsCardVisible"
                            type="success"
                            variant="tonal"
                            density="compact"
                            icon="mdi-check-circle-outline">
                            <div class="students-timetable-v2-calculation-card__success">
                                <span>Die Stundenpläne wurden erfolgreich erstellt.</span>
                                <span class="students-timetable-v2-calculation-card__summary">
                                    <v-chip size="small" color="success" variant="tonal">
                                        {{ timetableCalculationCountLabel }}
                                    </v-chip>
                                    <v-chip
                                        v-for="countItem in timetableCalculationResultCountItems"
                                        :key="countItem.key"
                                        size="small"
                                        :color="countItem.color"
                                        variant="tonal">
                                        {{ countItem.label }}
                                    </v-chip>
                                </span>
                            </div>
                        </v-alert>
                        <v-card
                            v-if="timetableOptionsCardVisible"
                            rounded="lg"
                            variant="tonal"
                            class="students-timetable-v2-options-card">
                            <v-card-title class="students-timetable-v2-options-card__title">
                                Optionen
                            </v-card-title>
                            <v-card-text>
                                <div class="students-timetable-v2-options-card__list">
                                    <v-card
                                        v-if="!timetableNoSaturdaySelected"
                                        rounded="lg"
                                        density="compact"
                                        variant="tonal"
                                        :color="timetableNoSaturdayDraftSelected ? 'success' : undefined"
                                        :disabled="timetableCalculationLoading"
                                        :class="[
                                            'students-timetable-v2-options-card__option',
                                            { 'students-timetable-v2-options-card__option--selected': timetableNoSaturdayDraftSelected },
                                        ]"
                                        role="button"
                                        tabindex="0"
                                        :aria-pressed="timetableNoSaturdayDraftSelected ? 'true' : 'false'"
                                        @click="toggleNoSaturdayTimetableOption"
                                        @keydown.enter.prevent="toggleNoSaturdayTimetableOption"
                                        @keydown.space.prevent="toggleNoSaturdayTimetableOption">
                                        <v-card-title class="students-timetable-v2-options-card__option-title">
                                            Kein Samstag
                                        </v-card-title>
                                        <v-card-text class="students-timetable-v2-options-card__option-content">
                                            <span class="students-timetable-v2-options-card__option-count">
                                                {{ noSaturdayTimetableCountFormatted }}
                                            </span>
                                        </v-card-text>
                                    </v-card>
                                    <v-card
                                        v-if="!timetableMaxFreeDaysSelected"
                                        rounded="lg"
                                        density="compact"
                                        variant="tonal"
                                        :color="timetableMaxFreeDaysSelected ? 'success' : undefined"
                                        :disabled="timetableCalculationLoading || timetableMaxFreeDaysSelected || maxFreeDaysTimetableCount <= 0"
                                        :class="[
                                            'students-timetable-v2-options-card__option',
                                            { 'students-timetable-v2-options-card__option--selected': timetableMaxFreeDaysSelected },
                                        ]"
                                        role="button"
                                        tabindex="0"
                                        :aria-pressed="timetableMaxFreeDaysSelected ? 'true' : 'false'"
                                        @click="applyMaxFreeDaysTimetableOption"
                                        @keydown.enter.prevent="applyMaxFreeDaysTimetableOption"
                                        @keydown.space.prevent="applyMaxFreeDaysTimetableOption">
                                        <v-card-title class="students-timetable-v2-options-card__option-title">
                                            Max freie Tage
                                        </v-card-title>
                                        <v-card-text class="students-timetable-v2-options-card__option-content">
                                            <span class="students-timetable-v2-options-card__option-count">
                                                {{ maxFreeDaysTimetableCountFormatted }}
                                            </span>
                                        </v-card-text>
                                    </v-card>
                                </div>
                            </v-card-text>
                        </v-card>
                        <v-card
                            v-if="moreCoursesCardVisible"
                            rounded="lg"
                            variant="tonal"
                            class="students-timetable-v2-more-courses-card">
                            <v-card-title class="students-timetable-v2-more-courses-card__title">
                                Mehr Kurse
                            </v-card-title>
                            <v-card-text>
                                <template v-if="moreCourseAvailabilityLoading">
                                    <v-progress-linear
                                        indeterminate
                                        color="primary"
                                        class="students-timetable-v2-completed-courses__loading" />
                                    <v-alert type="info" variant="tonal" density="compact" icon="mdi-calculator-variant-outline">
                                        Weitere Kurse werden geprüft.
                                    </v-alert>
                                </template>
                                <div v-else-if="moreCoursesCardItems.length" class="students-timetable-v2-more-courses-card__list">
                                    <v-chip
                                        v-for="course in moreCoursesCardItems"
                                        :key="course.selectionKey"
                                        size="small"
                                        :color="moreCourseChipColor(course)"
                                        :variant="selectedMoreCourseItem?.selectionKey === course.selectionKey && !moreCourseUnavailable(course) ? 'flat' : 'tonal'"
                                        :disabled="moreCourseDisabled(course)"
                                        class="students-timetable-v2-selected-courses-card__course students-timetable-v2-more-courses-card__course"
                                        :class="{
                                            'students-timetable-v2-selected-courses-card__course--active': selectedMoreCourseItem?.selectionKey === course.selectionKey,
                                            'students-timetable-v2-selected-courses-card__course--offered-deselected': moreCourseOfferedCourseItemsAllDeselected(course),
                                            'students-timetable-v2-more-courses-card__course--unavailable': moreCourseUnavailable(course),
                                        }"
                                        :role="moreCourseDisabled(course) ? undefined : 'button'"
                                        :tabindex="moreCourseDisabled(course) ? undefined : 0"
                                        :aria-expanded="selectedMoreCourseItem?.selectionKey === course.selectionKey ? 'true' : 'false'"
                                        @click="toggleMoreCourseOffers(course)"
                                        @keydown.enter.prevent="toggleMoreCourseOffers(course)"
                                        @keydown.space.prevent="toggleMoreCourseOffers(course)">
                                        <span class="students-timetable-v2-more-courses-card__label">
                                            {{ course.label }}
                                        </span>
                                        <span v-if="course.meta" class="students-timetable-v2-more-courses-card__meta">
                                            {{ course.meta }}
                                        </span>
                                        <span class="students-timetable-v2-more-courses-card__group">
                                            {{ course.courseGroupLabel }}
                                        </span>
                                    </v-chip>
                                </div>
                                <v-alert v-else type="info" variant="tonal" density="compact">
                                    Keine weiteren Kurse verfügbar.
                                </v-alert>
                            </v-card-text>
                        </v-card>
                        <v-card
                            v-if="moreCoursesCardVisible && selectedMoreCourseItem"
                            rounded="lg"
                            variant="tonal"
                            class="students-timetable-v2-more-courses-offered-card students-timetable-v2-offered-courses-card">
                            <v-card-title class="students-timetable-v2-offered-courses-card__title">
                                <span>Angebotene Kurse</span>
                                <v-chip size="x-small" color="primary" variant="tonal">
                                    {{ selectedMoreCourseItem.label }}
                                </v-chip>
                            </v-card-title>
                            <v-card-text>
                                <v-progress-linear
                                    v-if="courseGroupsLoading"
                                    indeterminate
                                    color="primary"
                                    class="students-timetable-v2-completed-courses__loading" />
                                <v-alert v-else-if="courseGroupsError" type="error" variant="tonal" density="compact">
                                    {{ courseGroupsError }}
                                </v-alert>
                                <div v-else-if="selectedMoreCourseOfferedCourseItems.length" class="students-timetable-v2-offered-courses-card__list">
                                    <div
                                        v-for="course in selectedMoreCourseOfferedCourseItems"
                                        :key="course.key"
                                        class="students-timetable-v2-offered-courses-card__item students-timetable-v2-offered-courses-card__item--toggle"
                                        :class="{
                                            'students-timetable-v2-offered-courses-card__item--selected': moreOfferedCourseSelected(course),
                                            'students-timetable-v2-offered-courses-card__item--deselected': !moreOfferedCourseSelected(course),
                                        }"
                                        role="button"
                                        tabindex="0"
                                        :aria-pressed="moreOfferedCourseSelected(course) ? 'true' : 'false'"
                                        @click="toggleMoreOfferedCourseItem(course)"
                                        @keydown.enter.prevent="toggleMoreOfferedCourseItem(course)"
                                        @keydown.space.prevent="toggleMoreOfferedCourseItem(course)">
                                        <v-icon v-if="moreOfferedCourseSelected(course)" icon="mdi-check" size="16" color="success" />
                                        <span class="students-timetable-v2-offered-courses-card__name">
                                            {{ course.name || course.code }}
                                        </span>
                                        <span v-if="offeredCourseCodeVisible(course)" class="students-timetable-v2-offered-courses-card__code">
                                            {{ course.code }}
                                        </span>
                                        <v-chip v-if="course.scheduleLabel" size="x-small" color="primary" variant="tonal">
                                            {{ course.scheduleLabel }}
                                        </v-chip>
                                        <v-chip v-if="course.recurrenceLabel" size="x-small" color="primary" variant="tonal">
                                            {{ course.recurrenceLabel }}
                                        </v-chip>
                                        <v-chip
                                            v-if="course.distanceLearning"
                                            size="x-small"
                                            color="warning"
                                            variant="tonal"
                                            title="Fernunterricht">
                                            Fernunterricht
                                        </v-chip>
                                        <v-chip v-if="course.roomsLabel" size="x-small" color="secondary" variant="outlined">
                                            {{ course.roomsLabel }}
                                        </v-chip>
                                    </div>
                                </div>
                                <v-alert v-else type="info" variant="tonal" density="compact">
                                    Keine angebotenen Kurse gefunden.
                                </v-alert>
                            </v-card-text>
                        </v-card>
                        <div
                            v-if="selectedTimetableV2Result"
                            class="students-timetable-v2-result">
                            <div class="students-timetable-v2-result__header">
                                <div class="students-timetable-v2-result__title">
                                    <v-icon icon="mdi-calendar-check-outline" size="18" color="success" />
                                    <span>{{ selectedTimetableV2TitleLabel }}</span>
                                </div>
                                <div class="students-timetable-v2-result__meta">
                                    <v-btn
                                        icon="mdi-chevron-left"
                                        size="small"
                                        variant="tonal"
                                        color="primary"
                                        :disabled="!selectedTimetableV2PreviousAvailable"
                                        aria-label="Vorheriger Stundenplan"
                                        @click="moveSelectedTimetableV2Result(-1)" />
                                    <div class="students-timetable-v2-result__counter" aria-live="polite">
                                        {{ selectedTimetableV2CounterLabel }}
                                    </div>
                                    <v-btn
                                        icon="mdi-chevron-right"
                                        size="small"
                                        variant="tonal"
                                        color="primary"
                                        :disabled="!selectedTimetableV2NextAvailable"
                                        aria-label="Nächster Stundenplan"
                                        @click="moveSelectedTimetableV2Result(1)" />
                                    <v-text-field
                                        :model-value="timetableCalculationNumberDraft"
                                        type="number"
                                        min="1"
                                        :max="selectedTimetableV2NumberLimit()"
                                        density="comfortable"
                                        variant="solo-filled"
                                        flat
                                        hide-spin-buttons
                                        hide-details
                                        single-line
                                        prefix="Nr."
                                        :disabled="timetableCalculationLoading"
                                        class="students-timetable-v2-result__number-input"
                                        aria-label="Stundenplan Nummer"
                                        @update:model-value="updateTimetableV2NumberDraft"
                                        @keydown.enter.prevent="commitSelectedTimetableV2Number"
                                        @blur="commitSelectedTimetableV2Number" />
                                </div>
                            </div>
                            <div
                                v-if="selectedTimetableV2ConflictSummaryItems.length"
                                class="students-timetable-v2-result-conflicts"
                                :class="`students-timetable-v2-result-conflicts--${selectedTimetableV2ConflictSeverity}`">
                                <div class="students-timetable-v2-result-conflicts__title">
                                    <v-icon :icon="selectedTimetableV2ConflictIcon" size="16" />
                                    <span>{{ selectedTimetableV2ConflictTitle }}</span>
                                </div>
                                <ul class="students-timetable-v2-result-conflicts__list">
                                    <li
                                        v-for="conflict in selectedTimetableV2ConflictSummaryItems"
                                        :key="conflict">
                                        {{ conflict }}
                                    </li>
                                </ul>
                                <div
                                    v-if="selectedTimetableV2ConflictResolutionOptions.length"
                                    class="students-timetable-v2-result-conflicts__actions">
                                    <v-btn
                                        v-for="option in selectedTimetableV2ConflictResolutionOptions"
                                        :key="option.selectionKey"
                                        size="small"
                                        color="error"
                                        variant="tonal"
                                        prepend-icon="mdi-close-circle-outline"
                                        @click="applySelectedTimetableV2ConflictResolution(option)">
                                        {{ option.label }} entfernen · {{ option.countLabel }}
                                    </v-btn>
                                </div>
                            </div>
                            <div
                                class="students-timetable-v2-result-grid"
                                :style="{ '--students-timetable-v2-result-weekdays': selectedTimetableV2Weekdays.length }">
                                <div class="students-timetable-v2-result-grid__cell students-timetable-v2-result-grid__cell--header">Std.</div>
                                <div
                                    v-for="weekday in selectedTimetableV2Weekdays"
                                    :key="`timetable-v2-result-header-${weekday.value}`"
                                    class="students-timetable-v2-result-grid__cell students-timetable-v2-result-grid__cell--header">
                                    {{ weekday.shortTitle }}
                                </div>

                                <template
                                    v-for="time in selectedTimetableV2Times"
                                    :key="`timetable-v2-result-time-${time.value}`">
                                    <div class="students-timetable-v2-result-grid__cell students-timetable-v2-result-grid__cell--time">
                                        <span class="students-timetable-v2-result-grid__hour">{{ time.hourLabel }}</span>
                                        <span v-if="time.timeFrom" class="students-timetable-v2-result-grid__time">{{ time.timeFrom }}</span>
                                        <span v-if="time.timeUntil" class="students-timetable-v2-result-grid__time">{{ time.timeUntil }}</span>
                                    </div>
                                    <div
                                        v-for="weekday in selectedTimetableV2Weekdays"
                                        :key="`timetable-v2-result-${weekday.value}-${time.value}`"
                                        class="students-timetable-v2-result-grid__cell"
                                        :class="selectedTimetableV2CellClasses(weekday.value, time.value)">
                                        <div
                                            v-if="selectedTimetableV2Slot(weekday.value, time.value)"
                                            class="students-timetable-v2-result-grid__content"
                                            :class="{ 'students-timetable-v2-result-grid__content--has-overlap-chip': selectedTimetableV2OccasionalOverlapChips(selectedTimetableV2Slot(weekday.value, time.value)).length }">
                                            <div
                                                v-if="selectedTimetableV2OccasionalOverlapChips(selectedTimetableV2Slot(weekday.value, time.value)).length"
                                                class="students-timetable-v2-result-grid__overlap-chips">
                                                <v-chip
                                                    v-for="chip in selectedTimetableV2OccasionalOverlapChips(selectedTimetableV2Slot(weekday.value, time.value))"
                                                    :key="chip.key"
                                                    class="students-timetable-v2-result-grid__overlap-chip"
                                                    color="warning"
                                                    density="compact"
                                                    label
                                                    size="x-small"
                                                    variant="flat">
                                                    {{ chip.label }}
                                                </v-chip>
                                            </div>
                                            <div class="students-timetable-v2-result-grid__code">
                                                <span>{{ selectedTimetableV2SlotTitle(selectedTimetableV2DisplaySlot(selectedTimetableV2Slot(weekday.value, time.value))) }}</span>
                                                <sup
                                                    v-if="selectedTimetableV2DisplaySlot(selectedTimetableV2Slot(weekday.value, time.value)).isDistanceLearningCourse"
                                                    class="students-timetable-v2-result-grid__badge">
                                                    FU
                                                </sup>
                                                <sup
                                                    v-if="selectedTimetableV2DisplaySlot(selectedTimetableV2Slot(weekday.value, time.value)).isAdditionalCourse"
                                                    class="students-timetable-v2-result-grid__badge students-timetable-v2-result-grid__badge--additional">
                                                    Zusatz
                                                </sup>
                                            </div>
                                            <div
                                                v-if="selectedTimetableV2SlotDetails(selectedTimetableV2DisplaySlot(selectedTimetableV2Slot(weekday.value, time.value)))"
                                                class="students-timetable-v2-result-grid__details">
                                                {{ selectedTimetableV2SlotDetails(selectedTimetableV2DisplaySlot(selectedTimetableV2Slot(weekday.value, time.value))) }}
                                            </div>
                                            <div
                                                v-if="selectedTimetableV2SlotTimePatternLabel(
                                                    selectedTimetableV2DisplaySlot(selectedTimetableV2Slot(weekday.value, time.value)),
                                                    { showRegularRange: selectedTimetableV2SameSlotEntries(selectedTimetableV2Slot(weekday.value, time.value)).length > 0 },
                                                )"
                                                class="students-timetable-v2-result-grid__recurrence">
                                                {{
                                                    selectedTimetableV2SlotTimePatternLabel(
                                                        selectedTimetableV2DisplaySlot(selectedTimetableV2Slot(weekday.value, time.value)),
                                                        { showRegularRange: selectedTimetableV2SameSlotEntries(selectedTimetableV2Slot(weekday.value, time.value)).length > 0 },
                                                    )
                                                }}
                                            </div>
                                            <div
                                                v-if="selectedTimetableV2SameSlotEntries(selectedTimetableV2Slot(weekday.value, time.value)).length"
                                                class="students-timetable-v2-result-grid__same-slots">
                                                <div
                                                    v-for="sameSlotEntry in selectedTimetableV2SameSlotEntries(selectedTimetableV2Slot(weekday.value, time.value))"
                                                    :key="selectedTimetableV2SlotKey(sameSlotEntry)"
                                                    class="students-timetable-v2-result-grid__same-slot">
                                                    <span>{{ selectedTimetableV2SlotTitle(sameSlotEntry) }}</span>
                                                    <span v-if="selectedTimetableV2SlotTimePatternLabel(sameSlotEntry, { showRegularRange: true })">
                                                        {{ selectedTimetableV2SlotTimePatternLabel(sameSlotEntry, { showRegularRange: true }) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div
                                                v-if="selectedTimetableV2DisplayedSlotConflicts(selectedTimetableV2Slot(weekday.value, time.value)).length"
                                                class="students-timetable-v2-result-grid__conflicts">
                                                <div class="students-timetable-v2-result-grid__conflict-title">
                                                    Konflikt mit:
                                                </div>
                                                <div
                                                    v-for="conflict in selectedTimetableV2DisplayedSlotConflicts(selectedTimetableV2Slot(weekday.value, time.value))"
                                                    :key="selectedTimetableV2ConflictKey(conflict)"
                                                    class="students-timetable-v2-result-grid__conflict">
                                                    {{ selectedTimetableV2ConflictLabel(conflict) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <v-alert
                            v-else-if="timetableCalculationResult"
                            type="info"
                            variant="tonal"
                            density="compact">
                            {{ timetableCalculationUnavailableLabel }}
                        </v-alert>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="calculationButtonCardVisible" cols="12" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-restart-card">
                    <v-card-text class="students-timetable-v2-restart-card__content">
                        <v-btn
                            color="error"
                            variant="tonal"
                            size="large"
                            prepend-icon="mdi-restart"
                            :disabled="timetableCalculationLoading"
                            @click="restartTimetableV2">
                            Neustart
                        </v-btn>
                        <v-btn
                            color="primary"
                            variant="tonal"
                            size="large"
                            class="students-timetable-v2-restart-card__automatic-button"
                            prepend-icon="mdi-arrow-left"
                            :disabled="timetableCalculationLoading"
                            @click="backToCourseReview">
                            Zurück
                        </v-btn>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row v-else dense align="stretch">
            <v-col v-if="startCardVisible" cols="12" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-start-card">
                    <v-card-title>Start</v-card-title>
                    <v-card-text>
                        <div class="students-timetable-v2-start-card__actions">
                            <v-btn
                                color="primary"
                                variant="flat"
                                size="x-large"
                                class="students-timetable-v2-start-card__button"
                                prepend-icon="mdi-account-school-outline"
                                @click="startWithStudent">
                                Mit Studierenden
                            </v-btn>
                            <v-btn
                                color="secondary"
                                variant="tonal"
                                size="x-large"
                                class="students-timetable-v2-start-card__button"
                                prepend-icon="mdi-account-off-outline"
                                @click="startWithoutStudent">
                                Ohne Studierenden
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="studentCardVisible" cols="12" md="6" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card">
                    <v-card-title>Studierende</v-card-title>
                    <v-card-text>
                        <div class="students-timetable-v2-student-context">
                            <div class="students-timetable-v2-student-context__title">
                                <v-icon icon="mdi-account-school-outline" size="18" color="primary" />
                                <span class="students-timetable-v2-student-context__student-label">
                                    {{ storedTimetableStudentLabel }}
                                </span>
                                <span class="students-timetable-v2-student-actions">
                                    <v-btn
                                        icon="mdi-pencil"
                                        variant="tonal"
                                        color="primary"
                                        density="comfortable"
                                        size="small"
                                        title="Student bearbeiten"
                                        aria-label="Student bearbeiten"
                                        @click.stop="openStudentDialog" />
                                    <v-btn
                                        v-if="storedTimetableStudentContext"
                                        icon="mdi-close-circle-outline"
                                        variant="text"
                                        color="error"
                                        density="comfortable"
                                        size="small"
                                        title="Student löschen"
                                        aria-label="Student löschen"
                                        @click.stop="clearStoredTimetableStudent" />
                                </span>
                            </div>
                        </div>
                        <div v-if="storedTimetableStudentContext" class="students-timetable-v2-completed-courses">
                            <div class="students-timetable-v2-completed-courses__title">
                                <v-icon icon="mdi-school-outline" size="18" color="primary" />
                                <span>Abgeschlossene Kurse</span>
                                <v-chip size="x-small" color="primary" variant="tonal">
                                    {{ storedCompletedCourseItems.length }}
                                </v-chip>
                            </div>
                            <v-progress-linear v-if="studentCompletedCoursesLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                            <v-alert v-else-if="studentCompletedCoursesError" type="error" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                                {{ studentCompletedCoursesError }}
                            </v-alert>
                            <div v-else-if="storedCompletedCourseItems.length" class="students-timetable-v2-completed-courses__list">
                                <div v-for="course in storedCompletedCourseItems" :key="course.key" class="students-timetable-v2-completed-courses__item">
                                    <span>{{ course.label }}</span>
                                    <v-chip v-if="course.meta" size="x-small" color="primary" variant="tonal">
                                        {{ course.meta }}
                                    </v-chip>
                                </div>
                            </div>
                            <v-alert v-else type="info" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                                Keine abgeschlossenen Kurse gefunden.
                            </v-alert>
                        </div>
                        <div v-if="storedTimetableStudentContext" class="students-timetable-v2-completed-courses">
                            <div class="students-timetable-v2-completed-courses__title">
                                <v-icon icon="mdi-alert-circle-outline" size="18" color="error" />
                                <span>Fehlende Kurse</span>
                                <v-chip size="x-small" color="error" variant="tonal">
                                    {{ storedMissingCourseItems.length }}
                                </v-chip>
                            </div>
                            <v-progress-linear v-if="studentCompletedCoursesLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                            <v-alert v-else-if="studentCompletedCoursesError" type="error" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                                {{ studentCompletedCoursesError }}
                            </v-alert>
                            <div v-else-if="storedMissingCourseItems.length" class="students-timetable-v2-completed-courses__list">
                                <div
                                    v-for="course in storedMissingCourseItems"
                                    :key="course.key"
                                    class="students-timetable-v2-completed-courses__item students-timetable-v2-completed-courses__item--missing">
                                    <span>{{ course.label }}</span>
                                    <v-chip v-if="course.meta" size="x-small" color="error" variant="tonal">
                                        {{ course.meta }}
                                    </v-chip>
                                </div>
                            </div>
                            <v-alert v-else type="info" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                                Keine fehlenden Kurse gefunden.
                            </v-alert>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="withoutStudentBackgroundVisible" cols="12" md="6" class="students-timetable-v2-without-student-background-column" aria-hidden="true">
                <div class="students-timetable-v2-without-student-watermark">
                    <v-icon icon="mdi-account-off-outline" class="students-timetable-v2-without-student-watermark__icon" />
                    <span>Ohne Studierenden</span>
                </div>
            </v-col>

            <v-col v-if="selectionCardVisible" cols="12" md="6" :offset-md="selectionCardOffsetMd" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card">
                    <v-card-title>Auswahl</v-card-title>
                    <v-card-text>
                        <div v-if="selectionCardVisible" class="students-timetable-v2-selection">
                            <div v-for="item in storedTimetableSelectionSummary" :key="item.key" class="students-timetable-v2-selection__item">
                                <span>{{ item.label }}</span>
                                <div v-if="item.options?.length" class="students-timetable-v2-selection__chips">
                                    <v-chip
                                        v-for="option in item.options"
                                        :key="option.value"
                                        size="small"
                                        :color="selectionOptionSelected(item, option) ? 'success' : 'secondary'"
                                        :variant="selectionOptionSelected(item, option) ? 'flat' : 'outlined'"
                                        class="students-timetable-v2-selection__chip"
                                        :aria-pressed="selectionOptionSelected(item, option) ? 'true' : 'false'"
                                        @click="selectTimetableSelectionOption(item.key, option.value)">
                                        {{ option.title }}
                                    </v-chip>
                                </div>
                                <strong v-else class="students-timetable-v2-selection__value" :class="{ 'students-timetable-v2-selection__value--unknown': !item.known }">
                                    {{ item.value }}
                                </strong>
                            </div>
                        </div>
                        <div v-else class="students-timetable-v2-selection__empty">Kein Student ausgewählt</div>
                    </v-card-text>
                </v-card>
            </v-col>

            <div v-if="courseCardsVisible" class="students-timetable-v2-row-break" aria-hidden="true"></div>

            <v-col v-if="missingCourseCardVisible" cols="12" :md="courseCardMdColumns" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card">
                    <v-card-title class="students-timetable-v2-course-card-title">
                        <span>Fehlende Kurse</span>
                        <span class="students-timetable-v2-course-card-title__chips">
                            <v-chip size="x-small" color="error" variant="tonal">
                                {{ storedMissingCourseCardSummary.countLabel }}
                            </v-chip>
                            <v-chip size="x-small" color="error" variant="tonal">
                                {{ storedMissingCourseCardSummary.hoursLabel }}
                            </v-chip>
                        </span>
                    </v-card-title>
                    <v-card-text>
                        <v-progress-linear v-if="studentCompletedCoursesLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                        <v-progress-linear v-else-if="subjectRowsLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                        <v-alert v-else-if="studentCompletedCoursesError || subjectRowsError" type="error" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                            {{ studentCompletedCoursesError || subjectRowsError }}
                        </v-alert>
                        <div v-else class="students-timetable-v2-completed-courses__list">
                            <v-chip
                                v-for="course in storedMissingCourseCardItems"
                                :key="course.key"
                                :color="courseItemSelected(course, 'missing') ? 'error' : 'secondary'"
                                :variant="courseItemSelected(course, 'missing') ? 'tonal' : 'outlined'"
                                class="students-timetable-v2-completed-courses__item students-timetable-v2-completed-courses__item--missing students-timetable-v2-completed-courses__item--toggle"
                                :class="{ 'students-timetable-v2-completed-courses__item--deselected': !courseItemSelected(course, 'missing') }"
                                role="button"
                                :aria-pressed="courseItemSelected(course, 'missing') ? 'true' : 'false'"
                                @click="toggleCourseItem(course, 'missing')">
                                <v-icon v-if="courseItemSelected(course, 'missing')" icon="mdi-check" size="14" />
                                <span>{{ course.label }}</span>
                                <span v-if="course.hoursMeta" class="students-timetable-v2-completed-courses__item-meta">
                                    {{ course.hoursMeta }}
                                </span>
                            </v-chip>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="courseCardsVisible" cols="12" :md="courseCardMdColumns" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card">
                    <v-card-title class="students-timetable-v2-course-card-title">
                        <span>Vorgesehene Kurse</span>
                        <span class="students-timetable-v2-course-card-title__meta">
                            <span class="students-timetable-v2-course-card-title__chips">
                                <v-chip size="x-small" color="success" variant="tonal">
                                    {{ storedPlannedCourseSummary.countLabel }}
                                </v-chip>
                                <v-chip size="x-small" color="success" variant="tonal">
                                    {{ storedPlannedCourseSummary.hoursLabel }}
                                </v-chip>
                            </span>
                            <span class="students-timetable-v2-course-card-title__actions">
                                <v-btn
                                    icon="mdi-checkbox-marked-outline"
                                    size="x-small"
                                    density="compact"
                                    variant="tonal"
                                    color="success"
                                    title="Alle vorgesehenen Kurse auswählen"
                                    :disabled="!storedPlannedCourseItems.length || courseGroupAllSelected('planned')"
                                    @click.stop="setCourseGroupSelection('planned', true)" />
                                <v-btn
                                    icon="mdi-checkbox-blank-outline"
                                    size="x-small"
                                    density="compact"
                                    variant="tonal"
                                    color="secondary"
                                    title="Alle vorgesehenen Kurse abwählen"
                                    :disabled="!storedPlannedCourseItems.length || courseGroupNoneSelected('planned')"
                                    @click.stop="setCourseGroupSelection('planned', false)" />
                            </span>
                        </span>
                    </v-card-title>
                    <v-card-text>
                        <v-progress-linear v-if="studentCompletedCoursesLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                        <v-progress-linear v-else-if="subjectRowsLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                        <v-alert v-else-if="studentCompletedCoursesError || subjectRowsError" type="error" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                            {{ studentCompletedCoursesError || subjectRowsError }}
                        </v-alert>
                        <div v-else-if="courseCardsVisible && storedPlannedCourseItems.length" class="students-timetable-v2-completed-courses__list">
                            <v-chip
                                v-for="course in storedPlannedCourseItems"
                                :key="course.key"
                                :color="courseItemSelected(course, 'planned') ? 'success' : 'secondary'"
                                :variant="courseItemSelected(course, 'planned') ? 'tonal' : 'outlined'"
                                class="students-timetable-v2-completed-courses__item students-timetable-v2-completed-courses__item--planned students-timetable-v2-completed-courses__item--toggle"
                                :class="{ 'students-timetable-v2-completed-courses__item--deselected': !courseItemSelected(course, 'planned') }"
                                role="button"
                                :aria-pressed="courseItemSelected(course, 'planned') ? 'true' : 'false'"
                                @click="toggleCourseItem(course, 'planned')">
                                <v-icon v-if="courseItemSelected(course, 'planned')" icon="mdi-check" size="14" />
                                <span>{{ course.label }}</span>
                                <span v-if="course.meta" class="students-timetable-v2-completed-courses__item-meta">
                                    {{ course.meta }}
                                </span>
                            </v-chip>
                        </div>
                        <v-alert v-else-if="courseCardsVisible" type="info" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                            Keine vorgesehenen Kurse gefunden.
                        </v-alert>
                        <div v-else class="students-timetable-v2-selection__empty">Kein Student ausgewählt</div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="courseCardsVisible" cols="12" :md="courseCardMdColumns" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card">
                    <v-card-title class="students-timetable-v2-course-card-title">
                        <span>Zusätzliche Kurse</span>
                        <span class="students-timetable-v2-course-card-title__meta">
                            <span class="students-timetable-v2-course-card-title__chips">
                                <v-chip size="x-small" color="info" variant="tonal">
                                    {{ storedAdditionalCourseSummary.countLabel }}
                                </v-chip>
                                <v-chip size="x-small" color="info" variant="tonal">
                                    {{ storedAdditionalCourseSummary.hoursLabel }}
                                </v-chip>
                            </span>
                            <span class="students-timetable-v2-course-card-title__actions">
                                <v-btn
                                    icon="mdi-checkbox-marked-outline"
                                    size="x-small"
                                    density="compact"
                                    variant="tonal"
                                    color="info"
                                    title="Alle zusätzlichen Kurse auswählen"
                                    :disabled="!storedAdditionalCourseItems.length || courseGroupAllSelected('additional')"
                                    @click.stop="setCourseGroupSelection('additional', true)" />
                                <v-btn
                                    icon="mdi-checkbox-blank-outline"
                                    size="x-small"
                                    density="compact"
                                    variant="tonal"
                                    color="secondary"
                                    title="Alle zusätzlichen Kurse abwählen"
                                    :disabled="!storedAdditionalCourseItems.length || courseGroupNoneSelected('additional')"
                                    @click.stop="setCourseGroupSelection('additional', false)" />
                            </span>
                        </span>
                    </v-card-title>
                    <v-card-text>
                        <v-progress-linear v-if="studentCompletedCoursesLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                        <v-progress-linear v-else-if="subjectRowsLoading" indeterminate color="primary" class="students-timetable-v2-completed-courses__loading" />
                        <v-alert v-else-if="studentCompletedCoursesError || subjectRowsError" type="error" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                            {{ studentCompletedCoursesError || subjectRowsError }}
                        </v-alert>
                        <div v-else-if="courseCardsVisible && storedAdditionalCourseItems.length" class="students-timetable-v2-completed-courses__list">
                            <v-chip
                                v-for="course in storedAdditionalCourseItems"
                                :key="course.key"
                                :color="courseItemSelected(course, 'additional') ? 'info' : 'secondary'"
                                :variant="courseItemSelected(course, 'additional') ? 'tonal' : 'outlined'"
                                class="students-timetable-v2-completed-courses__item students-timetable-v2-completed-courses__item--additional students-timetable-v2-completed-courses__item--toggle"
                                :class="{ 'students-timetable-v2-completed-courses__item--deselected': !courseItemSelected(course, 'additional') }"
                                role="button"
                                :aria-pressed="courseItemSelected(course, 'additional') ? 'true' : 'false'"
                                @click="toggleCourseItem(course, 'additional')">
                                <v-icon v-if="courseItemSelected(course, 'additional')" icon="mdi-check" size="14" />
                                <span>{{ course.label }}</span>
                                <span v-if="course.meta" class="students-timetable-v2-completed-courses__item-meta">
                                    {{ course.meta }}
                                </span>
                            </v-chip>
                        </div>
                        <v-alert v-else-if="courseCardsVisible" type="info" variant="tonal" density="compact" class="students-timetable-v2-completed-courses__alert">
                            Keine zusätzlichen Kurse gefunden.
                        </v-alert>
                        <div v-else class="students-timetable-v2-selection__empty">Kein Student ausgewählt</div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="courseCardsVisible && selectedCourseLimitExceeded" cols="12" class="students-timetable-v2-card-column">
                <v-alert type="info" variant="tonal" density="compact" icon="mdi-information-outline">
                    <div>Wählen Sie maximal 10 Kurse und 30 Stunden aus und klicken Sie danach auf Weiter.</div>
                    <div>Sie können später noch Änderungen vornehmen.</div>
                </v-alert>
            </v-col>

            <v-col v-if="restartCardVisible" cols="12" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-restart-card">
                    <v-card-text class="students-timetable-v2-restart-card__content">
                        <v-btn
                            color="error"
                            variant="tonal"
                            size="large"
                            prepend-icon="mdi-restart"
                            @click="restartTimetableV2">
                            Neustart
                        </v-btn>
                        <v-btn
                            v-if="courseCardsVisible"
                            color="primary"
                            variant="tonal"
                            size="large"
                            prepend-icon="mdi-tune-variant"
                            @click="applyCourseLimitPreselection(true)">
                            Vorauswahl Kurse
                        </v-btn>
                        <v-btn
                            v-if="courseCardsVisible"
                            color="success"
                            variant="tonal"
                            size="large"
                            class="students-timetable-v2-restart-card__automatic-button"
                            append-icon="mdi-arrow-right"
                            :disabled="selectedCourseLimitExceeded"
                            @click="openCourseReview">
                            Weiter
                        </v-btn>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="studentDialogOpen" persistent max-width="560">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-account-school-outline" />
                    Student bearbeiten
                </v-card-title>
                <v-card-text>
                    <div class="students-timetable-v2-student-dialog__meta">
                        {{ studentTotalCountLabel }}
                    </div>
                    <v-text-field
                        ref="studentSearchField"
                        v-model="studentSearch"
                        label="Student suchen"
                        variant="outlined"
                        density="compact"
                        :loading="studentOptionsLoading"
                        clearable
                        hide-details="auto"
                        @keydown.enter.prevent="submitStudentSearch" />
                    <div class="students-timetable-v2-student-search-results">
                        <v-btn
                            size="small"
                            variant="tonal"
                            :color="studentSelectionDraft.studentCode === null ? 'primary' : 'secondary'"
                            class="students-timetable-v2-student-search-results__item"
                            block
                            @click="selectStudentDraft(null)">
                            Kein Student
                        </v-btn>
                        <template v-if="studentSearchReady">
                            <v-btn
                                v-for="student in filteredStudentResults"
                                :key="student.student_code"
                                size="small"
                                variant="tonal"
                                :color="String(studentSelectionDraft.studentCode) === String(student.student_code) ? 'primary' : 'secondary'"
                                class="students-timetable-v2-student-search-results__item"
                                block
                                @click="selectStudentDraft(student.student_code)">
                                {{ studentOptionTitle(student) }}
                            </v-btn>
                            <div v-if="!filteredStudentResults.length" class="students-timetable-v2-student-search-results__empty">Keine Schüler gefunden</div>
                        </template>
                        <div v-else class="students-timetable-v2-student-search-results__empty">Mindestens 2 Zeichen eingeben</div>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeStudentDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" @click="updateStudentSelection">Aktualisieren</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

const TIMETABLE_STORAGE_KEY_PREFIX = 'students-timetables:overview:last-timetable'
const TIMETABLE_V2_OVERVIEW_PATH = '/admin/students-timetables/timetable-v2/overview'
const TIMETABLE_V2_ROUTE_STEPS = ['selection', 'course-review', 'timetable-calculation']
const TIMETABLE_V2_ROUTE_MODES = ['student', 'without-student']
const TIMETABLE_MAX_FREE_DAYS_CRITERION_KEY = 'free_days'
const TIMETABLE_SATURDAY_FREE_CRITERION_KEY = 'saturday_free'
const OFFERED_COURSE_BULK_SELECTION_OPTIONS = [
    { key: 'all', label: 'Alle' },
    { key: 'distance-learning', label: 'Nur Fernunterricht' },
    { key: 'without-distance-learning', label: 'Ohne Fernunterricht' },
]

export default {
    data() {
        return {
            robotStudents: [],
            storageRevision: 0,
            studentDialogOpen: false,
            studentCompletedCoursesError: '',
            studentCompletedCoursesLoading: false,
            studentOverviewActiveRequestKey: '',
            studentOverviewLoadedRequestKey: '',
            studentCompletedCoursesRequestId: 0,
            studentOptionsLoading: false,
            studentSearch: '',
            studentSelectionDraft: {
                studentCode: null,
            },
            courseGroups: [],
            courseGroupsError: '',
            courseGroupsLoading: false,
            schoolHours: [],
            schoolHoursLoading: false,
            subjectRows: [],
            subjectRowsError: '',
            subjectRowsLoading: false,
            selectedReviewCourseKey: '',
            timetableCalculationError: '',
            timetableCalculationLoading: false,
            timetableCalculationRequestId: 0,
            timetableCalculationResult: null,
            timetableCalculationNumberDraft: '1',
            timetableCalculationSelectedNumber: 1,
            initialTimetableCalculationSelectedNumber: 1,
            initialTimetableCalculationSelection: null,
            initialTimetableCalculationNoSaturdaySelected: false,
            initialTimetableCalculationMaxFreeDaysSelected: false,
            timetableNoSaturdayDraftSelected: false,
            timetableNoSaturdaySelected: false,
            timetableMaxFreeDaysDraftSelected: false,
            timetableMaxFreeDaysSelected: false,
            timetableOptionsCardVisible: false,
            moreCoursesVisible: false,
            moreCoursesCardVisible: false,
            moreCoursesSelectionSnapshot: '',
            courseSelectionSnapshotSelections: {},
            offeredCourseSelectionSnapshotSelections: {},
            moreOfferedCourseSelectionSnapshot: '',
            moreOfferedCourseSelectionSnapshotSelections: {},
            moreCourseAvailabilityLoading: false,
            moreCourseAvailabilityByKey: {},
            moreCourseAvailabilityRequestId: 0,
            moreCourseAvailabilitySignature: '',
            selectedMoreCourseKey: '',
            timetableStartMode: '',
            timetableV2Step: 'selection',
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        selectedSchoolyear() {
            return this.config?.selected_schoolyear || {}
        },
        startCardVisible() {
            return !this.reviewFlowVisible && !this.storedTimetableStudentContext && this.timetableStartMode === ''
        },
        reviewFlowVisible() {
            return this.courseReviewVisible || this.timetableCalculationVisible
        },
        courseReviewVisible() {
            return this.timetableV2Step === 'course-review'
        },
        selectedCourseItemsClickable() {
            return this.courseReviewVisible
        },
        selectedTimetableSummaryChipsDisabled() {
            return this.moreCoursesVisible || this.timetableOptionsCardVisible
        },
        selectedCourseItemsDeletable() {
            return this.timetableCalculationVisible
                && !this.timetableCalculationLoading
                && !this.selectedTimetableSummaryChipsDisabled
        },
        selectedTimetableOptionItemsDeletable() {
            return !this.timetableCalculationLoading && !this.selectedTimetableSummaryChipsDisabled
        },
        offeredCourseBulkSelectionOptions() {
            return OFFERED_COURSE_BULK_SELECTION_OPTIONS
        },
        timetableCalculationVisible() {
            return this.timetableV2Step === 'timetable-calculation'
        },
        studentCardVisible() {
            return Boolean(this.storedTimetableStudentContext) || this.timetableStartMode === 'student'
        },
        courseCardsVisible() {
            return Boolean(this.storedTimetableStudentContext) || (this.timetableStartMode === 'without-student' && Boolean(this.noStudentSelectedSemester))
        },
        noStudentCourseSelectionMode() {
            return !this.storedTimetableStudentContext && this.timetableStartMode === 'without-student'
        },
        missingCourseCardVisible() {
            return this.courseCardsVisible && this.storedMissingCourseCardItems.length > 0
        },
        courseCardMdColumns() {
            return this.missingCourseCardVisible ? 4 : 6
        },
        selectionCardVisible() {
            return Boolean(this.storedTimetableStudentContext) || this.timetableStartMode === 'without-student'
        },
        withoutStudentBackgroundVisible() {
            return !this.storedTimetableStudentContext && this.timetableStartMode === 'without-student'
        },
        selectionCardOffsetMd() {
            return 0
        },
        timetableV2PageLoading() {
            const reviewDataLoading = this.courseReviewVisible || this.timetableCalculationVisible
                ? this.courseGroupsLoading || this.schoolHoursLoading
                : false

            return this.studentCompletedCoursesLoading
                || this.subjectRowsLoading
                || reviewDataLoading
        },
        reviewButtonCardVisible() {
            return this.courseReviewVisible && !this.timetableV2PageLoading
        },
        calculationButtonCardVisible() {
            return this.timetableCalculationVisible && !this.timetableV2PageLoading
        },
        restartCardVisible() {
            return !this.timetableV2PageLoading
                && (Boolean(this.storedTimetableStudentContext) || this.timetableStartMode !== '')
        },
        storedTimetableStudentLabel() {
            const label = String(this.storedTimetableStudentContext?.student?.label || '').trim()

            if (!label) return 'Kein Student'

            return label
        },
        courseReviewStudentLabel() {
            return this.storedTimetableStudentContext ? this.storedTimetableStudentLabel : 'Ohne Studierenden'
        },
        courseReviewSelectionSummary() {
            return this.storedTimetableSelectionSummary
                .map((item) => ({
                    ...item,
                    value: this.courseReviewSelectionValue(item),
                }))
                .filter((item) => this.selectionValueIsKnown(item.value))
        },
        storedTimetableStudentContext() {
            return this.storedTimetableState?.transferredStudentContext || null
        },
        storedTimetableStudentCode() {
            return this.normalizedStudentCode(this.storedTimetableStudentContext?.student?.studentCode)
        },
        storedCompletedCourseItems() {
            return this.normalizedCompletedCourseItems(this.storedTimetableStudentContext?.courses?.completed || [])
        },
        storedMissingCourseItems() {
            return this.normalizedMissingCourseItems(this.storedTimetableStudentContext?.courses?.failed || [])
        },
        storedMissingCourseCardItems() {
            const courses = this.storedTimetableStudentContext?.courses || {}
            const overviewCoursesByCode = new Map(
                this.normalizedOverviewCourseItems([
                    ...(courses.missing || []),
                    ...(courses.planned || []),
                    ...(courses.additional || []),
                ]).map((course) => [this.normalizedCourseCode(course.code || course.label), course])
            )

            return this.storedMissingCourseItems.map((course) => {
                const overviewCourse = overviewCoursesByCode.get(this.normalizedCourseCode(course.code || course.label))
                const hours = this.courseHoursNumber(overviewCourse) || this.subjectRowHoursForCourseCode(course.code || course.label)

                return {
                    ...course,
                    hours,
                    hoursMeta: hours ? `${this.formatHours(hours)} Std.` : '',
                }
            })
        },
        storedPlannedCourseItems() {
            if (!this.storedTimetableStudentContext) {
                return this.noStudentPlannedCourseItems
            }

            const courses = this.storedTimetableStudentContext?.courses || {}

            return this.sortedCourseItems(this.uniqueCourseItems([
                ...this.normalizedOverviewCourseItems(courses.missing || []),
                ...this.normalizedOverviewCourseItems(courses.planned || []),
            ]))
        },
        storedAdditionalCourseItems() {
            if (!this.storedTimetableStudentContext) {
                return this.noStudentAdditionalCourseItems
            }

            const courses = this.storedTimetableStudentContext?.courses || {}

            return this.sortedCourseItems(this.uniqueCourseItems(this.normalizedOverviewCourseItems(courses.additional || [])))
        },
        selectedPlannedCourseItems() {
            return this.storedPlannedCourseItems.filter((course) => this.courseItemSelected(course, 'planned'))
        },
        selectedMissingCourseCardItems() {
            return this.storedMissingCourseCardItems.filter((course) => this.courseItemSelected(course, 'missing'))
        },
        selectedAdditionalCourseItems() {
            return this.storedAdditionalCourseItems.filter((course) => this.courseItemSelected(course, 'additional'))
        },
        moreCoursesCardItems() {
            return [
                ...this.storedMissingCourseCardItems
                    .filter((course) => !this.courseItemSelected(course, 'missing'))
                    .map((course) => this.moreCoursesCardItem(course, 'missing')),
                ...this.storedPlannedCourseItems
                    .filter((course) => !this.courseItemSelected(course, 'planned'))
                    .map((course) => this.moreCoursesCardItem(course, 'planned')),
                ...this.storedAdditionalCourseItems
                    .filter((course) => !this.courseItemSelected(course, 'additional'))
                    .map((course) => this.moreCoursesCardItem(course, 'additional')),
            ]
        },
        moreCoursesButtonUnavailable() {
            return this.moreCoursesCardItems.length > 0
                && this.moreCourseAvailabilityComplete()
                && this.moreCoursesCardItems.every((course) => this.moreCourseUnavailable(course))
        },
        selectedMoreCourseItem() {
            return this.moreCoursesCardItems.find((course) => course.selectionKey === this.selectedMoreCourseKey)
                || null
        },
        selectedMoreCourseOfferedCourseItems() {
            if (!this.selectedMoreCourseItem) return []

            return this.offeredCourseItemsForSelectedCourse(this.selectedMoreCourseItem)
        },
        moreOfferedCourseSelectionChanged() {
            return this.moreOfferedCourseSelectionSignature() !== this.moreOfferedCourseSelectionSnapshot
        },
        moreCoursesSelectionChanged() {
            return this.moreCoursesSelectionSignature() !== this.moreCoursesSelectionSnapshot
                || this.timetableOptionsChanged
        },
        selectedCourseItems() {
            return this.sortedCourseItems([
                ...this.selectedMissingCourseCardItems.map((course) => this.selectedCourseListItem(course, 'missing')),
                ...this.selectedPlannedCourseItems.map((course) => this.selectedCourseListItem(course, 'planned')),
                ...this.selectedAdditionalCourseItems.map((course) => this.selectedCourseListItem(course, 'additional')),
            ])
        },
        courseGroupsByCourseCode() {
            const courseGroupsByCourseCode = new Map()
            const courseGroups = Array.isArray(this.courseGroups) ? this.courseGroups : []

            courseGroups.forEach((courseGroup) => {
                this.courseGroupCodes(courseGroup).forEach((courseCode) => {
                    if (!courseGroupsByCourseCode.has(courseCode)) {
                        courseGroupsByCourseCode.set(courseCode, [])
                    }

                    courseGroupsByCourseCode.get(courseCode).push(courseGroup)
                })
            })

            return courseGroupsByCourseCode
        },
        selectedReviewCourseItem() {
            return this.selectedCourseItems.find((course) => course.selectionKey === this.selectedReviewCourseKey)
                || null
        },
        selectedReviewOfferedCourseItems() {
            if (!this.selectedReviewCourseItem) return []

            return this.offeredCourseItemsForSelectedCourse(this.selectedReviewCourseItem)
        },
        storedMissingCourseCardSummary() {
            return this.courseItemsSummary(this.selectedMissingCourseCardItems)
        },
        storedPlannedCourseSummary() {
            return this.courseItemsSummary(this.selectedPlannedCourseItems)
        },
        storedAdditionalCourseSummary() {
            return this.courseItemsSummary(this.selectedAdditionalCourseItems)
        },
        selectedCourseSummary() {
            return this.courseItemsSummary([
                ...this.selectedMissingCourseCardItems,
                ...this.selectedPlannedCourseItems,
                ...this.selectedAdditionalCourseItems,
            ])
        },
        selectedCourseLimitExceeded() {
            return this.selectedCourseSummary.count > 10 || this.selectedCourseSummary.hours > 30
        },
        timetableCalculationCountLabel() {
            const count = this.timetableCalculationDisplayTotalCount

            return `${this.formatNumber(count)} ${count === 1 ? 'Stundenplan' : 'Stundenpläne'} gesamt`
        },
        timetableCalculationDisplayTotalCount() {
            if (this.timetableQualityCriteriaRequired) return this.selectedTimetableV2ResultCount

            return Number(this.timetableCalculationResult?.timetable_variation_count || 0)
        },
        timetableQualityCriteriaRequired() {
            return this.timetableMaxFreeDaysSelected === true
        },
        noSaturdayTimetableCount() {
            if (this.timetableQualityCriteriaRequired && this.saturdayFreeQualityCounter) {
                return Number(this.saturdayFreeQualityCounter.count || 0)
            }

            return Number(this.timetableCalculationResult?.no_saturday_timetable_count || 0)
        },
        noSaturdayTimetableCountFormatted() {
            return this.formatNumber(this.noSaturdayTimetableCount)
        },
        saturdayFreeQualityCounter() {
            return (Array.isArray(this.timetableCalculationResult?.quality_counters)
                ? this.timetableCalculationResult.quality_counters
                : []
            ).find((counter) => counter?.key === TIMETABLE_SATURDAY_FREE_CRITERION_KEY) || null
        },
        maxFreeDaysQualityCounter() {
            return (Array.isArray(this.timetableCalculationResult?.quality_counters)
                ? this.timetableCalculationResult.quality_counters
                : []
            ).find((counter) => counter?.key === TIMETABLE_MAX_FREE_DAYS_CRITERION_KEY) || null
        },
        maxFreeDaysTimetableCount() {
            return Number(this.maxFreeDaysQualityCounter?.count || 0)
        },
        maxFreeDaysTimetableCountFormatted() {
            return this.formatNumber(this.maxFreeDaysTimetableCount)
        },
        timetableOptionsChanged() {
            return this.timetableNoSaturdayDraftSelected !== this.timetableNoSaturdaySelected
                || this.timetableMaxFreeDaysDraftSelected !== this.timetableMaxFreeDaysSelected
        },
        timetableCalculationResetAvailable() {
            if (!this.timetableCalculationVisible) return false

            return this.timetableCalculationSelectionSnapshot(this.storedTimetableV2Selection)
                !== this.timetableCalculationSelectionSnapshot(this.initialTimetableCalculationSelection || {})
                || Number(this.timetableCalculationSelectedNumber || 1) !== Number(this.initialTimetableCalculationSelectedNumber || 1)
                || this.timetableNoSaturdaySelected !== this.initialTimetableCalculationNoSaturdaySelected
                || this.timetableMaxFreeDaysSelected !== this.initialTimetableCalculationMaxFreeDaysSelected
        },
        selectedTimetableOptionItems() {
            const options = []
            const noSaturdaySelected = this.moreCoursesVisible && !this.moreCoursesCardVisible && this.timetableOptionsChanged
                ? this.timetableNoSaturdayDraftSelected
                : this.timetableNoSaturdaySelected
            const maxFreeDaysSelected = this.moreCoursesVisible && !this.moreCoursesCardVisible && this.timetableOptionsChanged
                ? this.timetableMaxFreeDaysDraftSelected
                : this.timetableMaxFreeDaysSelected

            if (noSaturdaySelected) {
                options.push({
                    key: 'no-saturday',
                    label: 'Kein Samstag',
                })
            }

            if (maxFreeDaysSelected) {
                options.push({
                    key: 'max-free-days',
                    label: 'Max freie Tage',
                })
            }

            return options
        },
        timetableCalculationResultCountItems() {
            return [
                {
                    key: 'valid',
                    color: 'success',
                    count: this.timetableCalculationDisplayValidResultCount,
                    singular: 'gültig',
                    plural: 'gültig',
                },
                {
                    key: 'conflict',
                    color: 'error',
                    count: this.timetableCalculationDisplayConflictResultCount,
                    singular: 'Konflikt',
                    plural: 'Konflikte',
                },
            ].map((item) => ({
                ...item,
                label: `${this.formatNumber(item.count)} ${item.count === 1 ? item.singular : item.plural}`,
            }))
        },
        timetableCalculationValidResultCount() {
            return Number(this.timetableCalculationResult?.full_green_timetable_count || 0)
                + Number(this.timetableCalculationResult?.green_timetable_count || 0)
        },
        timetableCalculationConflictResultCount() {
            return Number(this.timetableCalculationResult?.conflict_timetable_count || this.timetableCalculationResult?.red_timetable_count || 0)
        },
        timetableCalculationDisplayValidResultCount() {
            if (!this.timetableQualityCriteriaRequired) return this.timetableCalculationValidResultCount

            return this.selectedTimetableV2ResultType === 'conflict' ? 0 : this.selectedTimetableV2ResultCount
        },
        timetableCalculationDisplayConflictResultCount() {
            if (!this.timetableQualityCriteriaRequired) return this.timetableCalculationConflictResultCount

            return this.selectedTimetableV2ResultType === 'conflict' ? this.selectedTimetableV2ResultCount : 0
        },
        timetableCalculationUnavailableLabel() {
            if (this.timetableCalculationDisplayValidResultCount <= 0 && this.timetableCalculationDisplayConflictResultCount > 0) {
                return 'Es wurden nur Stundenpläne mit Überschneidung gefunden.'
            }

            return 'Es wurde kein gültiger Stundenplan gefunden.'
        },
        selectedTimetableV2Result() {
            const selectedTimetable = this.timetableCalculationResult?.selected_timetable

            return selectedTimetable && typeof selectedTimetable === 'object' && !Array.isArray(selectedTimetable)
                ? selectedTimetable
                : null
        },
        selectedTimetableV2ResultType() {
            const type = String(this.selectedTimetableV2Result?.type || '').trim()

            return ['full_green', 'green', 'conflict'].includes(type) ? type : 'full_green'
        },
        selectedTimetableV2TitleLabel() {
            return this.selectedTimetableV2ResultType === 'conflict'
                ? 'Stundenplan mit Überschneidung'
                : 'Gültiger Stundenplan'
        },
        selectedTimetableV2ConflictSeverity() {
            const conflictPairs = this.selectedTimetableV2ConflictPairs()

            return conflictPairs.length > 0
                && conflictPairs.every((pair) => this.selectedTimetableV2ConflictPairIsOccasional(pair))
                ? 'warning'
                : 'error'
        },
        selectedTimetableV2ConflictIcon() {
            return this.selectedTimetableV2ConflictSeverity === 'warning'
                ? 'mdi-alert-outline'
                : 'mdi-alert-circle-outline'
        },
        selectedTimetableV2ConflictTitle() {
            return this.selectedTimetableV2ConflictSeverity === 'warning'
                ? 'Überschneidungen'
                : 'Konflikte'
        },
        selectedTimetableV2ConflictSummaryItems() {
            const slotConflictLabels = this.uniqueValues(this.selectedTimetableV2SlotEntries
                .flatMap((entry) => this.selectedTimetableV2SlotConflicts(entry.slot)
                    .map((conflict) => this.selectedTimetableV2ConflictSummaryLabel(entry.slot, conflict)))
                .map((conflict) => String(conflict || '').trim())
                .filter(Boolean))

            if (slotConflictLabels.length) {
                return slotConflictLabels
            }

            const problems = Array.isArray(this.selectedTimetableV2Result?.problems)
                ? this.selectedTimetableV2Result.problems
                : []

            return this.uniqueValues(problems
                .map((problem) => String(problem || '').trim())
                .filter(Boolean))
        },
        selectedTimetableV2ConflictResolutionOptions() {
            const optionsBySelectionKey = new Map()

            this.selectedTimetableV2ConflictPairs()
                .filter((pair) => !this.selectedTimetableV2ConflictPairIsOccasional(pair))
                .forEach((pair) => {
                    [pair.slot, pair.conflict].forEach((slot) => {
                        const offeredCourse = this.selectedTimetableV2OfferedCourseForSlot(slot)
                        const selectionKey = offeredCourse?.selectionKey || ''
                        if (!selectionKey || !this.offeredCourseSelected(offeredCourse)) return

                        if (!optionsBySelectionKey.has(selectionKey)) {
                            optionsBySelectionKey.set(selectionKey, {
                                conflictKeys: new Set(),
                                label: this.selectedTimetableV2CourseProblemLabel(slot) || offeredCourse.groupSelectionLabel || offeredCourse.name,
                                selectionKey,
                            })
                        }

                        optionsBySelectionKey.get(selectionKey).conflictKeys.add(pair.key)
                    })
                })

            return [...optionsBySelectionKey.values()]
                .map((option) => {
                    const count = option.conflictKeys.size

                    return {
                        count,
                        countLabel: `${this.formatNumber(count)} ${count === 1 ? 'Konflikt' : 'Konflikte'} lösen`,
                        label: option.label,
                        selectionKey: option.selectionKey,
                    }
                })
                .filter((option) => option.count > 0)
                .sort((firstOption, secondOption) => {
                    if (firstOption.count !== secondOption.count) return secondOption.count - firstOption.count

                    return firstOption.label.localeCompare(secondOption.label, 'de-AT', {
                        numeric: true,
                        sensitivity: 'base',
                    })
                })
        },
        selectedTimetableV2StatusLabel() {
            return String(this.selectedTimetableV2Result?.statusMessage || '').trim() || 'Gültiger Stundenplan'
        },
        selectedTimetableV2ResultCount() {
            if (this.timetableQualityCriteriaRequired) {
                return Math.max(0, Number(this.timetableCalculationResult?.selected_quality_criteria_count || 0))
            }

            const count = this.selectedTimetableV2ResultType === 'conflict'
                ? this.timetableCalculationConflictResultCount
                : this.timetableCalculationValidResultCount

            return Math.max(0, Number(count || 0))
        },
        selectedTimetableV2CounterLabel() {
            return `${this.formatNumber(this.timetableCalculationSelectedNumber)} / ${this.formatNumber(this.selectedTimetableV2ResultCount)} ${this.selectedTimetableV2ResultTypeCounterLabel}`
        },
        selectedTimetableV2ResultTypeCounterLabel() {
            return this.selectedTimetableV2ResultType === 'conflict' ? 'Konflikte' : 'gültige'
        },
        selectedTimetableV2ResultCountLabel() {
            if (this.selectedTimetableV2ResultCount <= 0) return ''

            const singularLabel = this.selectedTimetableV2ResultType === 'conflict'
                ? 'Stundenplan mit Überschneidung'
                : 'gültiger Stundenplan'
            const pluralLabel = this.selectedTimetableV2ResultType === 'conflict'
                ? 'Stundenpläne mit Überschneidung'
                : 'gültige Stundenpläne'

            return `${this.formatNumber(this.selectedTimetableV2ResultCount)} ${this.selectedTimetableV2ResultCount === 1 ? singularLabel : pluralLabel}`
        },
        selectedTimetableV2PreviousAvailable() {
            return !this.timetableCalculationLoading
                && this.selectedTimetableV2ResultCount > 1
                && this.timetableCalculationSelectedNumber > 1
        },
        selectedTimetableV2NextAvailable() {
            return !this.timetableCalculationLoading
                && this.selectedTimetableV2ResultCount > 1
                && this.timetableCalculationSelectedNumber < this.selectedTimetableV2ResultCount
        },
        selectedTimetableV2SlotEntries() {
            const slots = this.selectedTimetableV2Result?.slots || {}

            return Object.entries(slots)
                .map(([key, slot]) => this.selectedTimetableV2NormalizedSlotEntry(key, slot))
                .filter((entry) => entry.weekday > 0 && entry.hour > 0)
        },
        selectedTimetableV2Weekdays() {
            const hasSaturday = this.selectedTimetableV2SlotEntries.some((entry) => entry.weekday === 6)

            return this.timetableV2WeekdayOptions().filter((weekday) => Number(weekday.value) <= 5 || hasSaturday)
        },
        selectedTimetableV2Times() {
            const hours = this.uniqueValues(this.selectedTimetableV2SlotEntries
                .map((entry) => entry.hour)
                .filter((hour) => Number.isFinite(hour) && hour > 0))
                .sort((firstHour, secondHour) => firstHour - secondHour)

            if (!hours.length) return []

            const firstHour = Math.min(...hours)
            const lastHour = Math.max(...hours)
            const configuredTimesByHour = new Map(this.timetableV2ConfiguredTimeOptions()
                .map((time) => [Number(time.value), time]))

            return Array.from({ length: (lastHour - firstHour) + 1 }, (_, index) => firstHour + index)
                .map((hour) => configuredTimesByHour.get(hour) || {
                    hourLabel: `${hour}.`,
                    timeFrom: '',
                    timeUntil: '',
                    value: hour,
                })
        },
        courseLimitPreselectionSignature() {
            const courseItems = [
                ...this.storedMissingCourseCardItems
                    .map((course) => this.courseLimitPreselectionSignaturePart(course, 'missing')),
                ...this.storedPlannedCourseItems
                    .map((course) => this.courseLimitPreselectionSignaturePart(course, 'planned')),
                ...this.storedAdditionalCourseItems
                    .map((course) => this.courseLimitPreselectionSignaturePart(course, 'additional')),
            ]

            if (!courseItems.length) return ''

            return JSON.stringify({
                maxCourses: 10,
                maxHours: 30,
                courseItems,
            })
        },
        storedTimetableState() {
            this.storageRevision

            const storage = this.timetableStorage()
            if (!storage) return null

            return (
                this.timetableStorageKeys()
                    .map((key) => this.parseStoredTimetableState(storage.getItem(key)))
                    .find((state) => state !== null) || null
            )
        },
        normalizedStudentSearch() {
            return String(this.studentSearch || '')
                .trim()
                .toLowerCase()
        },
        studentSearchReady() {
            return this.normalizedStudentSearch.length >= 2
        },
        filteredStudentResults() {
            if (!this.studentSearchReady) return []

            return this.robotStudents.filter((student) => this.studentOptionTitle(student).toLowerCase().includes(this.normalizedStudentSearch))
        },
        studentTotalCountLabel() {
            const count = Array.isArray(this.robotStudents) ? this.robotStudents.length : 0

            return `${this.formatNumber(count)} Studenten gesamt`
        },
        storedTimetableV2Selection() {
            return this.storedTimetableState?.timetableV2Selection || {}
        },
        storedTimetableV2Options() {
            const options = this.storedTimetableState?.timetableV2Options

            return options && typeof options === 'object' && !Array.isArray(options)
                ? options
                : {}
        },
        courseSelectionOverrides() {
            const courseSelections = this.storedTimetableV2Selection.courseSelections

            return courseSelections && typeof courseSelections === 'object' && !Array.isArray(courseSelections)
                ? courseSelections
                : {}
        },
        offeredCourseSelectionOverrides() {
            const offeredCourseSelections = this.storedTimetableV2Selection.offeredCourseSelections

            return offeredCourseSelections && typeof offeredCourseSelections === 'object' && !Array.isArray(offeredCourseSelections)
                ? offeredCourseSelections
                : {}
        },
        moreOfferedCourseSelectionOverrides() {
            const moreOfferedCourseSelections = this.storedTimetableV2Selection.moreOfferedCourseSelections

            return moreOfferedCourseSelections && typeof moreOfferedCourseSelections === 'object' && !Array.isArray(moreOfferedCourseSelections)
                ? moreOfferedCourseSelections
                : {}
        },
        noStudentSelectedSemester() {
            const semester = Number(this.storedTimetableV2Selection.semester || 0)

            return Number.isFinite(semester) && semester > 0 ? semester : null
        },
        effectiveTimetableV2Selection() {
            return {
                ...this.defaultNoStudentTimetableV2Selection(),
                ...this.storedTimetableV2Selection,
            }
        },
        noStudentPlannedCourseItems() {
            if (!this.noStudentSelectedSemester) return []

            return this.sortedCourseItems(this.normalizedOverviewCourseItems(this.noStudentPlannedCourses()))
        },
        noStudentAdditionalCourseItems() {
            if (!this.noStudentSelectedSemester) return []

            return this.sortedCourseItems(this.uniqueCourseItems(this.normalizedOverviewCourseItems(this.noStudentAdditionalCourses())))
        },
        storedTimetableSelectionSummary() {
            const semesterLabel = this.storedTimetableStudentContext?.student?.semesterLabel
            const semesterValue = this.storedTimetableStudentContext
                ? this.semesterValueFromLabel(semesterLabel)
                : this.storedTimetableV2Selection.semester

            return [
                {
                    key: 'semester',
                    label: 'Semester',
                    value: this.knownSelectionValue(semesterLabel || semesterValue),
                    known: this.selectionValueIsKnown(semesterLabel || semesterValue),
                    options: this.storedTimetableStudentContext ? [] : this.semesterOptions(),
                },
                {
                    key: 'religion',
                    label: 'Ethik / Religion',
                    options: this.religionOptionsForSelectedStudent(),
                },
                {
                    key: 'language',
                    label: 'Sprache',
                    options: this.languageOptions(),
                },
                {
                    key: 'branch',
                    label: 'Zweig',
                    options: this.branchOptions(),
                },
                {
                    key: 'artsSubject',
                    label: 'ME / BE',
                    options: this.artsSubjectOptions(),
                },
            ]
        },
    },

    watch: {
        '$route.query.step'() {
            this.applyTimetableV2RouteFromRoute()
        },
        '$route.query.mode'() {
            this.applyTimetableV2RouteFromRoute()
        },
        '$route.query.tt'() {
            this.applyTimetableV2RouteFromRoute()
        },
    },

    mounted() {
        this.syncStoredTimetableOptions()
        this.applyTimetableV2RouteFromRoute({ restoreEffects: false })
        this.syncTimetableV2Route({ replace: true })
        const subjectRowsPromise = this.loadSubjectRows()
        const courseGroupsPromise = this.courseReviewVisible || this.timetableCalculationVisible
            ? this.loadCourseGroups()
            : null
        const schoolHoursPromise = this.courseReviewVisible || this.timetableCalculationVisible
            ? this.loadSchoolHours()
            : null
        this.loadStoredStudentOverview(this.storedTimetableStudentCode)
        this.restoreTimetableV2RouteStepEffects({
            subjectRowsPromise,
            courseGroupsPromise,
            schoolHoursPromise,
        })
    },

    methods: {
        startWithStudent() {
            this.selectedReviewCourseKey = ''
            this.timetableStartMode = 'student'
            this.setTimetableV2Step('selection')
        },
        startWithoutStudent() {
            this.selectedReviewCourseKey = ''
            this.timetableStartMode = 'without-student'
            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Selection: this.defaultNoStudentTimetableV2Selection(),
                transferredStudentContext: null,
            })
            this.loadSubjectRows()
            this.setTimetableV2Step('selection')
        },
        restartTimetableV2() {
            this.selectedReviewCourseKey = ''
            this.timetableStartMode = ''
            this.timetableCalculationError = ''
            this.timetableCalculationLoading = false
            this.timetableCalculationResult = null
            this.timetableCalculationSelectedNumber = 1
            this.studentDialogOpen = false
            this.studentSearch = ''
            this.studentSelectionDraft = {
                studentCode: null,
            }
            this.studentCompletedCoursesError = ''
            this.studentCompletedCoursesLoading = false
            this.subjectRowsError = ''
            this.courseGroupsError = ''
            this.studentOverviewActiveRequestKey = ''
            this.studentOverviewLoadedRequestKey = ''

            this.saveStoredTimetableState(this.defaultStoredTimetableState())
            this.setTimetableV2Step('selection')
        },
        openCourseReview() {
            if (this.selectedCourseLimitExceeded) return

            this.selectedReviewCourseKey = this.selectedReviewCourseItem?.selectionKey || ''
            this.setTimetableV2Step('course-review')
            this.loadCourseGroups?.()
            this.loadSchoolHours?.()
        },
        openTimetableCalculation() {
            this.timetableCalculationSelectedNumber = 1
            this.setTimetableV2Step('timetable-calculation')
            return this.calculateTimetables()
        },
        backToCourseReview() {
            this.setTimetableV2Step('course-review')
        },
        backToCourseSelection() {
            this.selectedReviewCourseKey = ''
            this.setTimetableV2Step('selection')
        },
        timetableV2WeekdayOptions() {
            return [
                { shortTitle: 'Mo', value: 1 },
                { shortTitle: 'Di', value: 2 },
                { shortTitle: 'Mi', value: 3 },
                { shortTitle: 'Do', value: 4 },
                { shortTitle: 'Fr', value: 5 },
                { shortTitle: 'Sa', value: 6 },
            ]
        },
        timetableV2ConfiguredTimeOptions() {
            return (Array.isArray(this.schoolHours) ? this.schoolHours : [])
                .map((schoolHour) => ({
                    hourLabel: `${Number(schoolHour.hour)}.`,
                    timeFrom: this.formatTimeValue(schoolHour?.from),
                    timeUntil: this.formatTimeValue(schoolHour?.until),
                    value: Number(schoolHour.hour),
                }))
                .filter((time) => Number.isFinite(time.value))
                .sort((firstTime, secondTime) => firstTime.value - secondTime.value)
        },
        selectedTimetableV2NormalizedSlotEntry(key, slot) {
            const [keyWeekday, keyHour] = String(key || '').split('-')
            const courseGroup = slot?.courseGroup || {}

            return {
                hour: Number(courseGroup.hour || keyHour || 0),
                key,
                slot,
                weekday: Number(courseGroup.weekday || keyWeekday || 0),
            }
        },
        selectedTimetableV2Slot(weekday, hour) {
            return this.selectedTimetableV2Result?.slots?.[`${weekday}-${hour}`] || null
        },
        selectedTimetableV2CellClasses(weekday, hour) {
            const slot = this.selectedTimetableV2Slot(weekday, hour)
            const conflictSeverity = this.selectedTimetableV2SlotConflictSeverity(slot)
            const displaySlot = this.selectedTimetableV2DisplaySlot(slot)

            return {
                'students-timetable-v2-result-grid__cell--filled': Boolean(slot),
                'students-timetable-v2-result-grid__cell--additional': displaySlot?.isAdditionalCourse === true,
                'students-timetable-v2-result-grid__cell--conflict': conflictSeverity === 'error',
            }
        },
        selectedTimetableV2DisplaySlot(slot) {
            const overlapItems = [
                slot,
                ...this.selectedTimetableV2SlotConflicts(slot),
            ]
            const regularSlot = overlapItems.find((item) => !this.selectedTimetableV2SlotIsOccasional(item))

            return regularSlot || slot
        },
        selectedTimetableV2OccasionalOverlapChips(slot) {
            if (!this.selectedTimetableV2SlotConflicts(slot).length) return []

            const chipsByKey = new Map()
            const displaySlot = this.selectedTimetableV2DisplaySlot(slot)
            const overlapItems = [
                slot,
                ...this.selectedTimetableV2SlotConflicts(slot),
            ]

            overlapItems
                .filter((item) => this.selectedTimetableV2SlotIsOccasional(item))
                .filter((item) => this.selectedTimetableV2OccasionalOverlapChipVisible(item, displaySlot))
                .forEach((item) => {
                    const label = [
                        this.selectedTimetableV2CourseProblemLabel(item),
                        this.selectedTimetableV2SlotDateLabel(item),
                    ].filter(Boolean).join(' ')
                    const key = this.selectedTimetableV2SlotKey(item) || label

                    if (!key || !label || chipsByKey.has(key)) return

                    chipsByKey.set(key, {
                        key,
                        label,
                    })
                })

            return [...chipsByKey.values()]
        },
        selectedTimetableV2OccasionalOverlapChipVisible(item, displaySlot) {
            if (!item || !displaySlot) return true

            return item !== displaySlot
        },
        selectedTimetableV2DisplayedSlotConflicts(slot) {
            if (this.selectedTimetableV2SlotConflictSeverity(slot) !== 'error') return []

            return this.selectedTimetableV2SlotConflicts(slot)
                .filter((conflict) => !this.selectedTimetableV2SlotIsOccasional(conflict))
        },
        selectedTimetableV2SlotTitle(slot) {
            return this.spacedCourseCode(slot?.code || slot?.sourceLabel || slot?.name || '')
        },
        selectedTimetableV2SlotDetails(slot) {
            const sourceLabel = String(slot?.sourceLabel || '').trim()
            const title = String(slot?.code || '').trim()

            if (!sourceLabel || sourceLabel === title) return ''

            return sourceLabel
        },
        selectedTimetableV2SlotRecurrenceLabel(slot) {
            const interval = this.courseGroupWeekInterval(slot?.courseGroup || slot)
            if (Number.isInteger(interval) && interval > 1) return `${interval}-wöchig`

            const recurrenceLabel = String(slot?.courseGroup?.recurrenceLabel || slot?.courseGroup?.recurrence_label || slot?.recurrenceLabel || slot?.recurrence_label || '').trim()
            if (!recurrenceLabel || /^w[öo]chentlich$/iu.test(recurrenceLabel) || /^1\s*-\s*w[öo]chig$/iu.test(recurrenceLabel)) return ''

            return recurrenceLabel
        },
        selectedTimetableV2SlotDateLabel(slot, options = {}) {
            const dateRangeLabel = String(slot?.dateRangeLabel || '').trim()

            if (!this.selectedTimetableV2SlotIsOccasional(slot)) {
                return options?.showRegularRange === true ? dateRangeLabel : ''
            }

            const exactDateLabels = this.selectedTimetableV2SlotExactDateLabels(slot)
            if (exactDateLabels.length) return exactDateLabels.join(', ')

            return options?.showRegularRange === true || !this.selectedTimetableV2DateLabelIsRange(dateRangeLabel)
                ? dateRangeLabel
                : ''
        },
        selectedTimetableV2SlotTimePatternLabel(slot, options = {}) {
            const recurrenceLabel = this.selectedTimetableV2SlotRecurrenceLabel(slot)
            const dateLabel = this.selectedTimetableV2SlotDateLabel(slot, options)

            if (recurrenceLabel && dateLabel) return `${recurrenceLabel}: ${dateLabel}`

            return recurrenceLabel || dateLabel
        },
        selectedTimetableV2SlotExactDateLabels(slot) {
            if (!Array.isArray(slot?.courseGroup?.dates)) return []

            return this.uniqueValues([...slot.courseGroup.dates]
                .filter(Boolean)
                .sort()
                .map((date) => this.formatCompactDateValue(date))
                .filter(Boolean))
        },
        selectedTimetableV2DateLabelIsRange(label) {
            return /\d{1,2}\.\d{1,2}\.?\s*-\s*\d{1,2}\.\d{1,2}\.?/u.test(String(label || ''))
        },
        selectedTimetableV2SameSlotEntries(slot) {
            return Array.isArray(slot?.sameSlotEntries) ? slot.sameSlotEntries : []
        },
        selectedTimetableV2SlotConflicts(slot) {
            return Array.isArray(slot?.conflicts) ? slot.conflicts : []
        },
        selectedTimetableV2SlotConflictSeverity(slot) {
            const conflicts = this.selectedTimetableV2SlotConflicts(slot)
            if (!conflicts.length) return ''

            return conflicts.every((conflict) => this.selectedTimetableV2ConflictPairIsOccasional({ slot, conflict }))
                ? 'warning'
                : 'error'
        },
        selectedTimetableV2ConflictPairs() {
            const conflictPairsByKey = new Map()

            this.selectedTimetableV2SlotEntries.forEach((entry) => {
                this.selectedTimetableV2SlotConflicts(entry.slot).forEach((conflict) => {
                    const key = [
                        this.selectedTimetableV2CourseProblemLabel(entry.slot),
                        this.selectedTimetableV2CourseProblemLabel(conflict),
                        this.selectedTimetableV2ConflictDateLabel(entry.slot, conflict),
                    ].filter(Boolean).join('|')

                    if (!key || conflictPairsByKey.has(key)) return

                    conflictPairsByKey.set(key, {
                        conflict,
                        key,
                        slot: entry.slot,
                    })
                })
            })

            return [...conflictPairsByKey.values()]
        },
        selectedTimetableV2ConflictPairIsOccasional(pair) {
            return this.selectedTimetableV2SlotIsOccasional(pair?.slot)
                || this.selectedTimetableV2SlotIsOccasional(pair?.conflict)
        },
        selectedTimetableV2ConflictSummaryLabel(slot, conflict) {
            const oneDayCourseFirst = this.selectedTimetableV2SlotIsOccasional(conflict)
                && !this.selectedTimetableV2SlotIsOccasional(slot)
            const firstItem = oneDayCourseFirst ? conflict : slot
            const secondItem = oneDayCourseFirst ? slot : conflict
            const firstTitle = this.selectedTimetableV2CourseProblemLabel(firstItem)
            const secondTitle = this.selectedTimetableV2ConflictLabel(secondItem, firstItem)

            return firstTitle && secondTitle
                ? `${firstTitle} überschneidet sich mit ${secondTitle}.`
                : secondTitle
        },
        selectedTimetableV2SlotIsOccasional(slot) {
            return slot?.isOccasional === true
                || (Array.isArray(slot?.courseGroup?.dates) && slot.courseGroup.dates.filter(Boolean).length === 1)
        },
        selectedTimetableV2OfferedCourseForSlot(slot) {
            const slotLabels = this.selectedTimetableV2SlotMatchLabels(slot)
            if (!slotLabels.length) return null

            return this.allSelectedOfferedCourseItems()
                .find((offeredCourse) => this.selectedTimetableV2OfferedCourseMatchesSlotLabels(offeredCourse, slotLabels)) || null
        },
        selectedTimetableV2SlotMatchLabels(slot) {
            return this.uniqueValues([
                slot?.sourceLabel,
                ...(Array.isArray(slot?.alternativeLabels) ? slot.alternativeLabels : []),
                slot?.courseGroup?.class_name,
                slot?.courseGroup?.display_label,
                slot?.courseGroup?.title,
            ]
                .map((label) => this.normalizedTimetableV2ConflictMatchLabel(label))
                .filter(Boolean))
        },
        selectedTimetableV2OfferedCourseMatchesSlotLabels(offeredCourse, slotLabels) {
            const offeredCourseLabels = [
                offeredCourse?.groupSelectionLabel,
                offeredCourse?.name,
                offeredCourse?.backendSelectionKey,
            ]
                .map((label) => this.normalizedTimetableV2ConflictMatchLabel(label))
                .filter(Boolean)

            return offeredCourseLabels.some((label) => slotLabels.includes(label))
        },
        normalizedTimetableV2ConflictMatchLabel(label) {
            return String(label || '')
                .trim()
                .toLocaleLowerCase('de-AT')
        },
        selectedTimetableV2ConflictLabel(conflict, slot = null) {
            return [
                this.selectedTimetableV2CourseProblemLabel(conflict),
                this.selectedTimetableV2SlotRecurrenceLabel(conflict),
                this.selectedTimetableV2ConflictDateLabel(slot, conflict),
            ].filter(Boolean).join(' ')
        },
        selectedTimetableV2ConflictDateLabel(slot, conflict) {
            const dateLabels = this.selectedTimetableV2ConflictDateLabels(slot, conflict)

            return dateLabels.length ? `(${dateLabels.join(', ')})` : ''
        },
        selectedTimetableV2ConflictDateLabels(slot, conflict) {
            const problemDateLabels = this.selectedTimetableV2ProblemDateLabels(slot, conflict)
            if (problemDateLabels.length) return problemDateLabels

            const sharedDateLabels = this.selectedTimetableV2SharedDateLabels(slot, conflict)
            if (sharedDateLabels.length) return sharedDateLabels

            if (this.selectedTimetableV2SlotIsOccasional(slot)) {
                return this.selectedTimetableV2SlotDateLabel(slot)
                    .split(',')
                    .map((date) => date.trim())
                    .filter(Boolean)
            }

            return this.selectedTimetableV2SlotDateLabel(conflict)
                .split(',')
                .map((date) => date.trim())
                .filter(Boolean)
        },
        selectedTimetableV2ProblemDateLabels(slot, conflict) {
            const slotLabel = this.selectedTimetableV2CourseProblemLabel(slot)
            const conflictLabel = this.selectedTimetableV2CourseProblemLabel(conflict)
            if (!slotLabel || !conflictLabel) return []

            const problems = Array.isArray(this.selectedTimetableV2Result?.problems)
                ? this.selectedTimetableV2Result.problems
                : []
            const problem = problems
                .map((item) => String(item || '').trim())
                .find((item) => item.includes(slotLabel) && item.includes(conflictLabel))

            return this.selectedTimetableV2DateLabelsFromText(problem)
        },
        selectedTimetableV2SharedDateLabels(slot, conflict) {
            const slotDates = Array.isArray(slot?.courseGroup?.dates) ? slot.courseGroup.dates.filter(Boolean) : []
            const conflictDates = Array.isArray(conflict?.courseGroup?.dates) ? conflict.courseGroup.dates.filter(Boolean) : []

            if (!slotDates.length || !conflictDates.length) return []

            const conflictDateSet = new Set(conflictDates)
            const sharedDates = this.uniqueValues(slotDates.filter((date) => conflictDateSet.has(date))).sort()

            return sharedDates.length <= 8
                ? sharedDates.map((date) => this.formatCompactDateValue(date)).filter(Boolean)
                : []
        },
        selectedTimetableV2DateLabelsFromText(text) {
            const dateMatches = String(text || '').match(/\d{4}-\d{2}-\d{2}|\d{1,2}\.\d{1,2}\.?/gu) || []

            return this.uniqueValues(dateMatches
                .map((date) => this.selectedTimetableV2CompactDateLabel(date))
                .filter(Boolean))
        },
        selectedTimetableV2CompactDateLabel(value) {
            const normalizedValue = String(value || '').trim()
            const compactDateMatch = normalizedValue.match(/^(\d{1,2})\.(\d{1,2})\.?$/u)

            if (compactDateMatch) {
                return `${compactDateMatch[1].padStart(2, '0')}.${compactDateMatch[2].padStart(2, '0')}.`
            }

            return this.formatCompactDateValue(normalizedValue)
        },
        selectedTimetableV2CourseProblemLabel(item) {
            const sourceLabel = String(item?.sourceLabel || '').trim()
            if (sourceLabel) return sourceLabel

            return this.uniqueValues([
                this.spacedCourseCode(item?.code || ''),
                String(item?.name || '').trim(),
            ].filter(Boolean)).join(' ')
        },
        selectedTimetableV2ConflictKey(conflict) {
            return [
                conflict?.key,
                conflict?.label,
                conflict?.sourceLabel,
                conflict?.dateRangeLabel,
            ].filter(Boolean).join('|')
        },
        selectedTimetableV2SlotKey(slot) {
            return [
                slot?.key,
                slot?.code,
                slot?.sourceLabel,
                slot?.dateRangeLabel,
            ].filter(Boolean).join('|')
        },
        applySelectedTimetableV2ConflictResolution(option) {
            const selectionKey = String(option?.selectionKey || '').trim()
            if (!selectionKey) return null

            this.saveOfferedCourseSelections({
                ...this.offeredCourseSelectionOverrides,
                [selectionKey]: false,
            })
            this.setSelectedTimetableV2Number(1, { replace: true })

            return this.calculateTimetables()
        },
        selectedTimetableV2NumberLimit() {
            return Math.max(1, this.selectedTimetableV2ResultCount || this.timetableCalculationSelectedNumber)
        },
        normalizedTimetableV2RouteTimetableNumber(number = this.$route?.query?.tt) {
            const normalizedNumber = Number(number || 1)

            return Number.isFinite(normalizedNumber) && normalizedNumber > 0
                ? Math.trunc(normalizedNumber)
                : 1
        },
        setSelectedTimetableV2Number(number, options = {}) {
            this.timetableCalculationSelectedNumber = Math.min(
                Math.max(this.normalizedTimetableV2RouteTimetableNumber(number), 1),
                this.selectedTimetableV2NumberLimit(),
            )
            this.syncTimetableV2NumberDraft()

            if (options?.syncRoute !== false) {
                this.syncTimetableV2Route(options)
            }
        },
        syncTimetableV2NumberDraft() {
            this.timetableCalculationNumberDraft = String(this.timetableCalculationSelectedNumber || 1)
        },
        updateTimetableV2NumberDraft(value) {
            this.timetableCalculationNumberDraft = String(value || '')
        },
        commitSelectedTimetableV2Number() {
            if (this.timetableCalculationLoading) return null

            const previousNumber = this.timetableCalculationSelectedNumber

            this.setSelectedTimetableV2Number(this.timetableCalculationNumberDraft)
            this.syncTimetableV2NumberDraft()

            if (this.timetableCalculationSelectedNumber === previousNumber) return null

            return this.calculateTimetables()
        },
        moveSelectedTimetableV2Result(direction) {
            const nextNumber = this.timetableCalculationSelectedNumber + Number(direction || 0)

            this.setSelectedTimetableV2Number(nextNumber)
            return this.calculateTimetables()
        },
        normalizedTimetableV2RouteStep(step = this.$route?.query?.step) {
            const normalizedStep = String(step || '').trim()

            return TIMETABLE_V2_ROUTE_STEPS.includes(normalizedStep) ? normalizedStep : ''
        },
        normalizedTimetableV2Step(step = '') {
            return this.normalizedTimetableV2RouteStep(step) || 'selection'
        },
        normalizedTimetableV2RouteMode(mode = this.$route?.query?.mode) {
            const normalizedMode = String(mode || '').trim()

            return TIMETABLE_V2_ROUTE_MODES.includes(normalizedMode) ? normalizedMode : ''
        },
        currentTimetableV2RouteMode() {
            if (this.storedTimetableStudentContext || this.timetableStartMode === 'student') return 'student'
            if (this.timetableStartMode === 'without-student') return 'without-student'

            return ''
        },
        timetableV2RouteLocation(step = this.timetableV2Step, mode = this.currentTimetableV2RouteMode()) {
            const normalizedStep = this.normalizedTimetableV2Step(step)
            const normalizedMode = this.normalizedTimetableV2RouteMode(mode)
            const query = { ...(this.$route?.query || {}) }

            query.step = normalizedStep

            if (normalizedStep === 'timetable-calculation') {
                query.tt = String(this.timetableCalculationSelectedNumber)
            } else {
                delete query.tt
            }

            if (normalizedMode) {
                query.mode = normalizedMode
            } else {
                delete query.mode
            }

            return {
                path: TIMETABLE_V2_OVERVIEW_PATH,
                query,
            }
        },
        timetableV2RouteLocationMatches(location) {
            return this.$route?.path === location.path
                && this.normalizedTimetableV2RouteStep() === this.normalizedTimetableV2RouteStep(location?.query?.step)
                && this.normalizedTimetableV2RouteMode() === this.normalizedTimetableV2RouteMode(location?.query?.mode)
                && this.normalizedTimetableV2RouteTimetableNumber() === this.normalizedTimetableV2RouteTimetableNumber(location?.query?.tt)
        },
        syncTimetableV2Route(options = {}) {
            const location = this.timetableV2RouteLocation()
            if (this.timetableV2RouteLocationMatches(location)) return

            const routerMethod = options?.replace === true && this.$router?.replace
                ? this.$router.replace
                : this.$router?.push
            const navigation = routerMethod?.call(this.$router, location)
            navigation?.catch?.(() => {})
        },
        setTimetableV2Step(step, options = {}) {
            const normalizedStep = this.normalizedTimetableV2Step(step)
            const previousStep = this.timetableV2Step

            this.timetableV2Step = normalizedStep
            this.syncInitialTimetableCalculationSelection(previousStep)

            if (options?.syncRoute !== false) {
                this.syncTimetableV2Route(options)
            }
        },
        applyTimetableV2RouteMode(mode) {
            const normalizedMode = this.normalizedTimetableV2RouteMode(mode)
            if (!normalizedMode) return

            if (this.storedTimetableStudentContext) {
                this.timetableStartMode = 'student'

                return
            }

            this.timetableStartMode = normalizedMode

            if (normalizedMode === 'without-student' && !this.noStudentSelectedSemester) {
                this.saveStoredTimetableState({
                    ...this.defaultStoredTimetableState(),
                    ...this.storedTimetableStateForSaving(),
                    timetableV2Selection: this.defaultNoStudentTimetableV2Selection(),
                    transferredStudentContext: null,
                })
                this.loadSubjectRows()
            }
        },
        applyTimetableV2RouteFromRoute(options = {}) {
            const previousTimetableNumber = this.timetableCalculationSelectedNumber

            this.applyTimetableV2RouteMode(this.$route?.query?.mode)
            this.setSelectedTimetableV2Number(this.$route?.query?.tt, { syncRoute: false })
            this.setTimetableV2Step(this.normalizedTimetableV2RouteStep(), { syncRoute: false })

            if (options?.syncRoute !== false) {
                this.syncTimetableV2Route({ replace: true })
            }

            if (
                options?.restoreEffects !== false
                && this.timetableCalculationVisible
                && previousTimetableNumber !== this.timetableCalculationSelectedNumber
                && this.timetableCalculationResult
            ) {
                this.calculateTimetables()

                return
            }

            if (options?.restoreEffects !== false) {
                this.restoreTimetableV2RouteStepEffects()
            }
        },
        restoreTimetableV2RouteStepEffects(loadPromises = {}) {
            if (this.courseReviewVisible || this.timetableCalculationVisible) {
                this.selectedReviewCourseKey = this.selectedReviewCourseItem?.selectionKey || ''
                this.loadCourseGroups?.()
                this.loadSchoolHours?.()
            }

            if (!this.timetableCalculationVisible || this.timetableCalculationLoading || this.timetableCalculationResult) return

            Promise.all([
                loadPromises.subjectRowsPromise || this.loadSubjectRows?.(),
                loadPromises.courseGroupsPromise || this.loadCourseGroups?.(),
                loadPromises.schoolHoursPromise || this.loadSchoolHours?.(),
            ]).then(() => {
                if (!this.timetableCalculationVisible || this.timetableCalculationLoading || this.timetableCalculationResult) return

                this.calculateTimetables()
            })
        },
        syncInitialTimetableCalculationSelection(previousStep = '') {
            if (!this.timetableCalculationVisible) {
                this.initialTimetableCalculationSelection = null
                this.initialTimetableCalculationSelectedNumber = 1
                this.initialTimetableCalculationNoSaturdaySelected = false
                this.initialTimetableCalculationMaxFreeDaysSelected = false
                this.timetableNoSaturdayDraftSelected = false
                this.timetableNoSaturdaySelected = false
                this.timetableMaxFreeDaysDraftSelected = false
                this.timetableMaxFreeDaysSelected = false

                return
            }

            if (previousStep === 'timetable-calculation' && this.initialTimetableCalculationSelection !== null) return

            this.initialTimetableCalculationSelection = this.clonedTimetableV2Selection(this.storedTimetableV2Selection)
            this.initialTimetableCalculationSelectedNumber = this.timetableCalculationSelectedNumber
            this.initialTimetableCalculationNoSaturdaySelected = this.timetableNoSaturdaySelected
            this.initialTimetableCalculationMaxFreeDaysSelected = this.timetableMaxFreeDaysSelected
        },
        clonedTimetableV2Selection(selection = {}) {
            return JSON.parse(JSON.stringify(selection && typeof selection === 'object' && !Array.isArray(selection) ? selection : {}))
        },
        timetableCalculationSelectionSnapshot(selection = {}) {
            return JSON.stringify(this.normalizedTimetableCalculationSelection(this.clonedTimetableV2Selection(selection)))
        },
        normalizedTimetableCalculationSelection(value) {
            if (Array.isArray(value)) {
                return value.map((item) => this.normalizedTimetableCalculationSelection(item))
            }

            if (value && typeof value === 'object') {
                return Object.keys(value)
                    .sort((firstKey, secondKey) => firstKey.localeCompare(secondKey, 'de-AT'))
                    .reduce((normalizedSelection, key) => ({
                        ...normalizedSelection,
                        [key]: this.normalizedTimetableCalculationSelection(value[key]),
                    }), {})
            }

            return value
        },
        resetTimetableCalculationChanges() {
            if (this.timetableCalculationLoading || !this.timetableCalculationResetAvailable) return null

            const timetableV2Selection = this.clonedTimetableV2Selection(this.initialTimetableCalculationSelection || {})

            this.closeMoreCoursesCard()
            this.timetableNoSaturdaySelected = this.initialTimetableCalculationNoSaturdaySelected
            this.timetableMaxFreeDaysSelected = this.initialTimetableCalculationMaxFreeDaysSelected
            this.closeTimetableOptionsCard()
            this.resetMoreCourseAvailability()
            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Selection,
                timetableV2Options: this.timetableV2OptionsForSaving(),
                transferredStudentContext: this.storedTimetableStudentContext,
            })
            this.setSelectedTimetableV2Number(this.initialTimetableCalculationSelectedNumber || 1, { replace: true })

            return this.calculateTimetables()
        },
        async calculateTimetables() {
            const requestId = this.timetableCalculationRequestId + 1
            this.timetableCalculationRequestId = requestId
            this.timetableCalculationError = ''
            this.timetableCalculationLoading = true
            const previousCalculationResult = this.timetableCalculationResult
            const requestedTimetableNumber = this.timetableCalculationSelectedNumber
            this.timetableCalculationResult = null
            this.resetMoreCourseAvailability()

            try {
                let response = await this.requestTimetableV2Calculation({
                    calculationResult: previousCalculationResult,
                    selectedTimetableNumber: requestedTimetableNumber,
                })

                if (requestId !== this.timetableCalculationRequestId) return

                let calculationResult = response.data?.data || {}
                const fallbackTimetableRequest = this.timetableV2FallbackSelectedTimetableRequest(
                    calculationResult,
                    requestedTimetableNumber,
                )

                if (fallbackTimetableRequest) {
                    response = await this.requestTimetableV2Calculation(fallbackTimetableRequest)

                    if (requestId !== this.timetableCalculationRequestId) return

                    calculationResult = response.data?.data || calculationResult
                }

                this.timetableCalculationResult = calculationResult
                this.setSelectedTimetableV2Number(
                    this.selectedTimetableV2CombinedNumberFromResult(this.timetableCalculationResult) || requestedTimetableNumber,
                    { replace: true },
                )
                this.startMoreCourseAvailabilityCheck()
            } catch (error) {
                if (requestId !== this.timetableCalculationRequestId) return

                this.timetableCalculationError = this.timetableCalculationErrorMessage(error)
            } finally {
                if (requestId === this.timetableCalculationRequestId) {
                    this.timetableCalculationLoading = false
                }
            }
        },
        requestTimetableV2Calculation(options = {}) {
            return axios.post(
                '/api/admin/students-timetables/robot/backend-timetable',
                this.timetableV2CalculationPayload(options),
            )
        },
        requestMoreCourseAvailability(course) {
            return axios.post(
                '/api/admin/students-timetables/robot/backend-timetable',
                this.timetableV2CalculationPayloadForMoreCourseAvailability(course),
            )
        },
        timetableV2FallbackSelectedTimetableRequest(calculationResult, selectedTimetableNumber = this.timetableCalculationSelectedNumber) {
            const desiredRequest = this.timetableV2SelectedTimetableRequest({
                calculationResult,
                selectedTimetableNumber,
            })
            const selectedTimetable = calculationResult?.selected_timetable

            if (
                selectedTimetable
                && selectedTimetable.type === desiredRequest.selectedTimetableType
                && Number(selectedTimetable.number || 1) === desiredRequest.selectedTimetableNumber
            ) {
                return null
            }

            if (['full_green', 'green', 'conflict'].includes(desiredRequest.selectedTimetableType)) return desiredRequest

            return null
        },
        timetableV2FallbackSelectedTimetableType(calculationResult) {
            return this.timetableV2FallbackSelectedTimetableRequest(calculationResult)?.selectedTimetableType || ''
        },
        timetableV2SelectedTimetableRequest(options = {}) {
            const calculationResult = options.calculationResult || this.timetableCalculationResult
            const selectedTimetableNumber = this.normalizedTimetableV2RouteTimetableNumber(
                options.selectedTimetableNumber || this.timetableCalculationSelectedNumber,
            )
            const fullGreenCount = Number(calculationResult?.full_green_timetable_count || 0)
            const greenCount = Number(calculationResult?.green_timetable_count || 0)
            const conflictCount = Number(calculationResult?.conflict_timetable_count || calculationResult?.red_timetable_count || 0)

            if (calculationResult && selectedTimetableNumber > fullGreenCount && greenCount > 0) {
                return {
                    selectedTimetableType: 'green',
                    selectedTimetableNumber: Math.min(selectedTimetableNumber - fullGreenCount, greenCount),
                }
            }

            if (calculationResult && fullGreenCount <= 0 && greenCount <= 0 && conflictCount > 0) {
                return {
                    selectedTimetableType: 'conflict',
                    selectedTimetableNumber: Math.min(selectedTimetableNumber, conflictCount),
                }
            }

            return {
                selectedTimetableType: 'full_green',
                selectedTimetableNumber: selectedTimetableNumber,
            }
        },
        selectedTimetableV2CombinedNumberFromResult(calculationResult) {
            const selectedTimetable = calculationResult?.selected_timetable
            const selectedTimetableNumber = Number(selectedTimetable?.number || 0)

            if (!Number.isFinite(selectedTimetableNumber) || selectedTimetableNumber <= 0) return 0
            if (selectedTimetable?.type === 'green') {
                return Number(calculationResult?.full_green_timetable_count || 0) + selectedTimetableNumber
            }

            return selectedTimetableNumber
        },
        timetableV2CalculationPayload(options = {}) {
            const selectedCourses = this.timetableV2CalculationSelectedCourses()
            const selectedCourseKeys = this.uniqueValues(selectedCourses
                .filter((course) => course.courseGroup !== 'additional')
                .map((course) => this.timetableV2CalculationCourseKey(course))
                .filter(Boolean))
            const selectedAdditionalCourseKeys = this.uniqueValues(selectedCourses
                .filter((course) => course.courseGroup === 'additional')
                .map((course) => this.timetableV2CalculationCourseKey(course))
                .filter(Boolean))
            const selectedTimetableRequest = options.selectedTimetableType
                ? {
                    selectedTimetableType: options.selectedTimetableType,
                    selectedTimetableNumber: options.selectedTimetableNumber || this.timetableCalculationSelectedNumber,
                }
                : this.timetableV2SelectedTimetableRequest(options)
            const maxFreeDaysSelected = this.timetableQualityCriteriaRequired
            const includeQualityCounters = options?.includeQualityCounters !== false
            const selectedQualityCriterionKeys = maxFreeDaysSelected ? [TIMETABLE_MAX_FREE_DAYS_CRITERION_KEY] : []
            const evaluationCriteria = includeQualityCounters || maxFreeDaysSelected
                ? this.timetableV2QualityCriteriaPayload()
                : []

            return {
                selection: this.timetableV2CalculationSelection(),
                constraints: this.timetableV2CalculationConstraints(),
                student: {
                    studentCode: this.normalizedStudentCode(this.storedTimetableStudentContext?.student?.studentCode),
                },
                selected_course_keys: selectedCourseKeys,
                selected_additional_course_keys: selectedAdditionalCourseKeys,
                deselected_course_keys: [],
                deselected_course_group_keys: this.timetableV2DeselectedOfferedCourseGroupKeys(),
                selected_additional_courses_required: selectedAdditionalCourseKeys.length > 0,
                selected_timetable_type: selectedTimetableRequest.selectedTimetableType,
                selected_timetable_number: selectedTimetableRequest.selectedTimetableNumber,
                selected_quality_criterion_keys: selectedQualityCriterionKeys,
                selected_quality_criteria_required: maxFreeDaysSelected,
                include_quality_counters: includeQualityCounters,
                evaluation_criteria: evaluationCriteria,
            }
        },
        timetableV2QualityCriteriaPayload() {
            return [
                {
                    key: TIMETABLE_MAX_FREE_DAYS_CRITERION_KEY,
                    enabled: true,
                    priority: 1,
                    option: null,
                },
                {
                    key: TIMETABLE_SATURDAY_FREE_CRITERION_KEY,
                    enabled: true,
                    priority: 2,
                    option: null,
                },
            ]
        },
        timetableV2CalculationPayloadForMoreCourseAvailability(course) {
            const payload = this.timetableV2CalculationPayload({
                includeQualityCounters: false,
                selectedTimetableType: 'full_green',
                selectedTimetableNumber: 1,
            })
            const courseKey = this.timetableV2CalculationCourseKey(course)

            if (!courseKey) return payload

            if (course?.courseGroup === 'additional') {
                payload.selected_additional_course_keys = this.uniqueValues([
                    ...(payload.selected_additional_course_keys || []),
                    courseKey,
                ])
                payload.selected_additional_courses_required = true
            } else {
                payload.selected_course_keys = this.uniqueValues([
                    ...(payload.selected_course_keys || []),
                    courseKey,
                ])
            }

            return payload
        },
        timetableV2CalculationSelectedCourses() {
            const selectedCourses = (Array.isArray(this.selectedCourseItems) ? this.selectedCourseItems : [])
                .filter((course) => !this.offeredCourseItemsAllDeselected(course))
            const moreSelectedCourses = this.timetableV2CalculationSelectedMoreCourses()

            return this.uniqueTimetableV2CalculationSelectedCourses([
                ...selectedCourses,
                ...moreSelectedCourses,
            ])
        },
        timetableV2CalculationSelectedMoreCourses() {
            return this.moreCoursesCardItems
                .filter((course) => this.moreCourseSelectedForAdding(course))
                .filter((course) => !this.moreCourseUnavailable(course))
                .filter((course) => this.moreCourseOfferedCourseItemsAnySelected(course))
        },
        uniqueTimetableV2CalculationSelectedCourses(courses) {
            const coursesBySelectionKey = new Map()

            courses.forEach((course) => {
                const selectionKey = [
                    course?.courseGroup,
                    this.timetableV2CalculationCourseKey(course),
                ].filter(Boolean).join(':')

                if (!selectionKey || coursesBySelectionKey.has(selectionKey)) return

                coursesBySelectionKey.set(selectionKey, course)
            })

            return [...coursesBySelectionKey.values()]
        },
        timetableV2CalculationSelection() {
            const selection = this.effectiveTimetableV2Selection || {}
            const studentSemester = this.semesterValueFromLabel(this.storedTimetableStudentContext?.student?.semesterLabel)
            const semester = Number(studentSemester || selection.semester || 1)

            return {
                semester: Number.isFinite(semester) && semester > 0 ? semester : 1,
                religion: String(selection.religion || this.storedTimetableStudentContext?.student?.religion || '').trim() || null,
                branch: String(selection.branch || '').trim() || null,
                artsSubject: String(selection.artsSubject || '').trim() || null,
                language: String(selection.language || '').trim() || null,
            }
        },
        timetableV2CalculationConstraints() {
            const availableWeekdays = this.timetableNoSaturdaySelected
                ? [1, 2, 3, 4, 5]
                : [1, 2, 3, 4, 5, 6]

            return {
                availableWeekdays,
                excludedWeekdayTimes: [],
                availableTimes: this.timetableV2CalculationAvailableTimes(),
            }
        },
        timetableV2CalculationAvailableTimes() {
            const schoolHourValues = (Array.isArray(this.schoolHours) ? this.schoolHours : [])
                .map((schoolHour) => Number(schoolHour?.hour))
                .filter((hour) => Number.isInteger(hour) && hour > 0)

            return schoolHourValues.length
                ? this.uniqueValues(schoolHourValues).sort((firstHour, secondHour) => firstHour - secondHour)
                : Array.from({ length: 20 }, (_, index) => index + 1)
        },
        timetableV2CalculationCourseKey(course) {
            return String(course?.key || course?.code || course?.label || '').trim()
        },
        timetableV2DeselectedOfferedCourseGroupKeys() {
            const selectedCourseGroupKeys = (Array.isArray(this.selectedCourseItems) ? this.selectedCourseItems : [])
                .flatMap((course) => this.offeredCourseItemsForSelectedCourse(course)
                    .filter((offeredCourse) => !this.offeredCourseSelected(offeredCourse))
                    .map((offeredCourse) => offeredCourse.backendSelectionKey))
                .filter(Boolean)
            const moreCourseGroupKeys = this.timetableV2CalculationSelectedMoreCourses()
                .flatMap((course) => this.offeredCourseItemsForSelectedCourse(course)
                    .filter((offeredCourse) => !this.moreOfferedCourseSelected(offeredCourse))
                    .map((offeredCourse) => offeredCourse.backendSelectionKey))
                .filter(Boolean)

            return this.uniqueValues([
                ...selectedCourseGroupKeys,
                ...moreCourseGroupKeys,
            ])
        },
        timetableCalculationErrorMessage(error) {
            const errors = error?.response?.data?.errors || {}
            const firstError = Object.values(errors)
                .flat()
                .find((message) => String(message || '').trim())
            const responseMessage = error?.response?.data?.message

            return firstError || responseMessage || 'Die Stundenpläne konnten nicht berechnet werden.'
        },
        applyCourseLimitPreselection(force = false) {
            if (!this.courseCardsVisible) return false

            const preselectionSignature = this.courseLimitPreselectionSignature
            if (!preselectionSignature) return false

            const currentTimetableV2Selection = this.storedTimetableV2Selection || {}
            if (!force && currentTimetableV2Selection.courseLimitPreselectionKey === preselectionSignature) return false

            const courseSelections = this.courseSelectionsForCourseLimitPreselection(force ? {} : this.courseSelectionOverrides)
            const timetableV2Selection = {
                ...currentTimetableV2Selection,
                courseLimitPreselectionKey: preselectionSignature,
            }

            if (Object.keys(courseSelections).length) {
                timetableV2Selection.courseSelections = courseSelections
            } else {
                delete timetableV2Selection.courseSelections
            }

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Selection,
                transferredStudentContext: this.storedTimetableStudentContext,
            })

            return true
        },
        courseSelectionsForCourseLimitPreselection(courseSelections = {}) {
            const nextCourseSelections = { ...(courseSelections || {}) }

            this.courseLimitDuplicateModuleCourseItems(nextCourseSelections).forEach((courseItem) => {
                const selectionKey = this.courseSelectionKey(courseItem.course, courseItem.courseGroup)
                if (selectionKey) {
                    nextCourseSelections[selectionKey] = false
                }
            })

            const selectedCourseItems = this.courseLimitSelectedCourseItems(nextCourseSelections)
            let selectedCourseCount = selectedCourseItems.length
            let selectedCourseHours = selectedCourseItems
                .reduce((hours, courseItem) => hours + this.courseHoursNumber(courseItem.course), 0)

            this.courseLimitPreselectionCandidates(nextCourseSelections).forEach((courseItem) => {
                if (selectedCourseCount <= 10 && selectedCourseHours <= 30) return

                const selectionKey = this.courseSelectionKey(courseItem.course, courseItem.courseGroup)
                if (!selectionKey || nextCourseSelections[selectionKey] === false) return

                nextCourseSelections[selectionKey] = false
                selectedCourseCount--
                selectedCourseHours -= this.courseHoursNumber(courseItem.course)
            })

            return nextCourseSelections
        },
        courseLimitSelectedCourseItems(courseSelections = {}) {
            return [
                ...this.storedMissingCourseCardItems.map((course) => ({ course, courseGroup: 'missing' })),
                ...this.storedPlannedCourseItems.map((course) => ({ course, courseGroup: 'planned' })),
                ...this.storedAdditionalCourseItems.map((course) => ({ course, courseGroup: 'additional' })),
            ].filter((courseItem) => this.courseSelectedBySelections(
                courseItem.course,
                courseItem.courseGroup,
                courseSelections,
            ))
        },
        courseLimitPreselectionCandidates(courseSelections = {}) {
            return [
                ...this.rankedCourseLimitPreselectionGroup(this.storedAdditionalCourseItems, 'additional', courseSelections),
                ...this.rankedCourseLimitPreselectionGroup(this.storedPlannedCourseItems, 'planned', courseSelections),
            ]
        },
        courseLimitDuplicateModuleCourseItems(courseSelections = {}) {
            return [
                ...this.duplicateModuleCourseItemsForGroup(this.storedAdditionalCourseItems, 'additional', courseSelections),
                ...this.duplicateModuleCourseItemsForGroup(this.storedPlannedCourseItems, 'planned', courseSelections),
            ]
        },
        duplicateModuleCourseItemsForGroup(courses, courseGroup, courseSelections = {}) {
            const selectedCourseItemsByBase = new Map()
            const courseItems = Array.isArray(courses) ? courses : []

            courseItems
                .map((course) => ({ course, courseGroup }))
                .filter((courseItem) => this.courseSelectedBySelections(
                    courseItem.course,
                    courseItem.courseGroup,
                    courseSelections,
                ))
                .forEach((courseItem) => {
                    const baseCode = this.courseBaseCode(courseItem.course)
                    if (!baseCode) return

                    selectedCourseItemsByBase.set(baseCode, [
                        ...(selectedCourseItemsByBase.get(baseCode) || []),
                        courseItem,
                    ])
                })

            return [...selectedCourseItemsByBase.values()]
                .flatMap((courseItems) => this.higherModuleCourseItems(courseItems))
        },
        higherModuleCourseItems(courseItems) {
            return [...courseItems]
                .sort((firstCourseItem, secondCourseItem) =>
                    this.courseModuleNumber(firstCourseItem.course) - this.courseModuleNumber(secondCourseItem.course))
                .slice(1)
        },
        rankedCourseLimitPreselectionGroup(courses, courseGroup, courseSelections = {}) {
            return (Array.isArray(courses) ? courses : [])
                .map((course) => ({ course, courseGroup }))
                .filter((courseItem) => this.courseSelectedBySelections(
                    courseItem.course,
                    courseItem.courseGroup,
                    courseSelections,
                ))
                .sort((firstCourseItem, secondCourseItem) =>
                    this.compareCourseLimitPreselectionItems(firstCourseItem.course, secondCourseItem.course))
        },
        compareCourseLimitPreselectionItems(firstCourse, secondCourse) {
            const firstPriority = this.courseLimitBasePriority(firstCourse)
            const secondPriority = this.courseLimitBasePriority(secondCourse)
            if (firstPriority !== secondPriority) return firstPriority - secondPriority

            const firstSemester = this.courseSemesterNumber(firstCourse)
            const secondSemester = this.courseSemesterNumber(secondCourse)
            if (firstSemester !== secondSemester) return secondSemester - firstSemester

            const firstModule = this.courseModuleNumber(firstCourse)
            const secondModule = this.courseModuleNumber(secondCourse)
            if (firstModule !== secondModule) return secondModule - firstModule

            return this.courseItemSortValue(secondCourse).localeCompare(this.courseItemSortValue(firstCourse), 'de-AT', {
                numeric: true,
                sensitivity: 'base',
            })
        },
        courseSelectedBySelections(course, courseGroup, courseSelections = {}) {
            const selectionKey = this.courseSelectionKey(course, courseGroup)
            if (!selectionKey) return this.courseGroupDefaultSelected(courseGroup)

            if (courseSelections[selectionKey] === true) return true
            if (courseSelections[selectionKey] === false) return false

            return this.courseGroupDefaultSelected(courseGroup)
        },
        courseLimitPreselectionSignaturePart(course, courseGroup) {
            return [
                courseGroup,
                this.courseSelectionKey(course, courseGroup),
                this.courseHoursNumber(course),
                this.courseSemesterNumber(course),
                this.courseModuleNumber(course),
            ].join(':')
        },
        selectedCourseListItem(course, courseGroup) {
            const hours = this.courseHoursNumber(course)
            const label = String(course?.label || course?.code || course?.name || '').trim()

            return {
                selectionKey: this.courseSelectionKey(course, courseGroup) || `${courseGroup}:${course?.key || course?.label}`,
                courseGroup,
                code: this.normalizedCourseCode(course?.code || label),
                hours,
                key: String(course?.key || course?.code || label).trim(),
                label,
                meta: String(course?.meta || course?.hoursMeta || (hours ? `${this.formatHours(hours)} Std.` : '')).trim(),
            }
        },
        moreCoursesCardItem(course, courseGroup) {
            return {
                ...this.selectedCourseListItem(course, courseGroup),
                courseGroupLabel: this.moreCoursesCardItemGroupLabel(courseGroup),
            }
        },
        moreCoursesCardItemGroupLabel(courseGroup) {
            if (courseGroup === 'additional') return 'Zusätzlich'
            if (courseGroup === 'missing') return 'Fehlend'

            return 'Vorgesehen'
        },
        toggleMoreCoursesCard() {
            if (this.moreCoursesVisible) {
                this.closeMoreCoursesCard()

                return
            }

            return this.openMoreCoursesCard()
        },
        openMoreCoursesCard() {
            this.moreCoursesVisible = true
            this.moreCoursesCardVisible = true
            this.timetableOptionsCardVisible = false
            this.moreCoursesSelectionSnapshot = this.moreCoursesSelectionSignature()
            this.courseSelectionSnapshotSelections = { ...this.courseSelectionOverrides }
            this.offeredCourseSelectionSnapshotSelections = { ...this.offeredCourseSelectionOverrides }
            this.moreOfferedCourseSelectionSnapshot = this.moreOfferedCourseSelectionSignature()
            this.moreOfferedCourseSelectionSnapshotSelections = { ...this.moreOfferedCourseSelectionOverrides }

            return this.ensureMoreCourseAvailability()
        },
        openMoreCoursesPendingActions() {
            this.moreCoursesVisible = true
            this.moreCoursesCardVisible = false
            this.timetableOptionsCardVisible = false
            this.timetableNoSaturdayDraftSelected = this.timetableNoSaturdaySelected
            this.timetableMaxFreeDaysDraftSelected = this.timetableMaxFreeDaysSelected
            this.moreCoursesSelectionSnapshot = this.moreCoursesSelectionSignature()
            this.courseSelectionSnapshotSelections = { ...this.courseSelectionOverrides }
            this.offeredCourseSelectionSnapshotSelections = { ...this.offeredCourseSelectionOverrides }
            this.moreOfferedCourseSelectionSnapshot = this.moreOfferedCourseSelectionSignature()
            this.moreOfferedCourseSelectionSnapshotSelections = { ...this.moreOfferedCourseSelectionOverrides }
        },
        cancelMoreCoursesCard() {
            if (this.moreCoursesSelectionChanged) {
                this.saveMoreCoursesSelectionState({
                    courseSelections: { ...this.courseSelectionSnapshotSelections },
                    moreOfferedCourseSelections: { ...this.moreOfferedCourseSelectionSnapshotSelections },
                    offeredCourseSelections: { ...this.offeredCourseSelectionSnapshotSelections },
                })
            }

            this.closeMoreCoursesCard()
        },
        applyMoreCoursesSelection() {
            if (!this.moreCoursesSelectionChanged || this.timetableCalculationLoading) return null

            const timetableOptionsChanged = this.timetableOptionsChanged

            this.timetableNoSaturdaySelected = this.timetableNoSaturdayDraftSelected
            this.timetableMaxFreeDaysSelected = this.timetableMaxFreeDaysDraftSelected
            if (timetableOptionsChanged) {
                this.saveTimetableV2Options()
            }
            this.setSelectedTimetableV2Number(1, { replace: true })
            const calculationRequest = this.calculateTimetables()
            this.promoteSelectedMoreCourses()
            this.closeMoreCoursesCard()

            return calculationRequest
        },
        promoteSelectedMoreCourses() {
            const selectedMoreCourses = this.moreCoursesCardItems
                .filter((course) => this.moreCourseSelectedForAdding(course))
                .filter((course) => !this.moreCourseUnavailable(course))
                .filter((course) => this.moreCourseOfferedCourseItemsAnySelected(course))

            if (!selectedMoreCourses.length) return

            const courseSelections = { ...this.courseSelectionOverrides }
            const offeredCourseSelections = { ...this.offeredCourseSelectionOverrides }
            const moreOfferedCourseSelections = { ...this.moreOfferedCourseSelectionOverrides }

            selectedMoreCourses.forEach((course) => {
                this.promoteSelectedMoreCourse(course, {
                    courseSelections,
                    moreOfferedCourseSelections,
                    offeredCourseSelections,
                })
            })

            this.saveMoreCoursesSelectionState({
                courseSelections,
                moreOfferedCourseSelections,
                offeredCourseSelections,
            })
        },
        promoteSelectedMoreCourse(course, selections) {
            const selectionKey = course?.selectionKey || this.courseSelectionKey(course, course?.courseGroup)
            if (!selectionKey) return

            if (this.courseGroupDefaultSelected(course.courseGroup)) {
                delete selections.courseSelections[selectionKey]
            } else {
                selections.courseSelections[selectionKey] = true
            }

            this.offeredCourseItemsForSelectedCourse(course).forEach((offeredCourse) => {
                const offeredCourseSelectionKey = offeredCourse?.selectionKey || this.offeredCourseSelectionKey(offeredCourse, course)
                if (!offeredCourseSelectionKey) return

                if (this.moreOfferedCourseSelected(offeredCourse)) {
                    delete selections.offeredCourseSelections[offeredCourseSelectionKey]
                } else {
                    selections.offeredCourseSelections[offeredCourseSelectionKey] = false
                }

                delete selections.moreOfferedCourseSelections[offeredCourseSelectionKey]
            })
        },
        closeMoreCoursesCard() {
            this.moreCoursesVisible = false
            this.moreCoursesCardVisible = false
            this.selectedMoreCourseKey = ''
            this.moreCoursesSelectionSnapshot = ''
            this.courseSelectionSnapshotSelections = {}
            this.offeredCourseSelectionSnapshotSelections = {}
            this.moreOfferedCourseSelectionSnapshot = ''
            this.moreOfferedCourseSelectionSnapshotSelections = {}
            this.timetableNoSaturdayDraftSelected = this.timetableNoSaturdaySelected
            this.timetableMaxFreeDaysDraftSelected = this.timetableMaxFreeDaysSelected
        },
        toggleTimetableOptionsCard() {
            if (this.timetableCalculationLoading) return

            if (this.timetableOptionsCardVisible) {
                this.closeTimetableOptionsCard()

                return
            }

            this.timetableNoSaturdayDraftSelected = this.timetableNoSaturdaySelected
            this.timetableMaxFreeDaysDraftSelected = this.timetableMaxFreeDaysSelected
            this.timetableOptionsCardVisible = true
        },
        closeTimetableOptionsCard() {
            this.timetableOptionsCardVisible = false
            this.timetableNoSaturdayDraftSelected = this.timetableNoSaturdaySelected
            this.timetableMaxFreeDaysDraftSelected = this.timetableMaxFreeDaysSelected
        },
        toggleNoSaturdayTimetableOption() {
            if (this.timetableCalculationLoading) return null

            this.timetableNoSaturdayDraftSelected = !this.timetableNoSaturdayDraftSelected
        },
        applyMaxFreeDaysTimetableOption() {
            if (this.timetableCalculationLoading || this.timetableMaxFreeDaysSelected || this.maxFreeDaysTimetableCount <= 0) return null

            this.timetableMaxFreeDaysDraftSelected = true
            this.timetableMaxFreeDaysSelected = true
            this.saveTimetableV2Options()
            this.setSelectedTimetableV2Number(1, { replace: true })
            this.resetMoreCourseAvailability()
            this.closeTimetableOptionsCard()

            return this.calculateTimetables()
        },
        applyTimetableOptions() {
            if (!this.timetableOptionsChanged || this.timetableCalculationLoading) return null

            this.timetableNoSaturdaySelected = this.timetableNoSaturdayDraftSelected
            this.timetableMaxFreeDaysSelected = this.timetableMaxFreeDaysDraftSelected
            this.saveTimetableV2Options()
            this.setSelectedTimetableV2Number(1, { replace: true })
            this.resetMoreCourseAvailability()
            this.closeTimetableOptionsCard()

            return this.calculateTimetables()
        },
        removeSelectedTimetableOption(option) {
            if (!this.selectedTimetableOptionItemsDeletable) return

            if (!['no-saturday', 'max-free-days'].includes(option?.key)) return

            if (!this.moreCoursesVisible) {
                this.openMoreCoursesPendingActions()
            }

            if (option?.key === 'no-saturday') {
                this.timetableNoSaturdayDraftSelected = false
            }

            if (option?.key === 'max-free-days') {
                this.timetableMaxFreeDaysDraftSelected = false
            }
        },
        toggleMoreCourseOffers(course) {
            if (this.moreCourseDisabled(course)) return

            const selectionKey = course?.selectionKey || this.courseSelectionKey(course, course?.courseGroup)
            if (!selectionKey) return
            if (this.selectedMoreCourseKey === selectionKey) return

            this.selectedMoreCourseKey = selectionKey
            this.saveMoreOfferedCourseSelections(this.moreOfferedCourseSelectionsForSingleCourse(course))
        },
        startMoreCourseAvailabilityCheck() {
            return this.ensureMoreCourseAvailability()
        },
        ensureMoreCourseAvailability() {
            const availabilitySignature = this.moreCourseAvailabilityCurrentSignature()
            if (!availabilitySignature) return Promise.resolve([])

            if (
                this.moreCourseAvailabilitySignature === availabilitySignature
                && (this.moreCourseAvailabilityLoading || this.moreCourseAvailabilityComplete(availabilitySignature))
            ) {
                return Promise.resolve([])
            }

            return this.loadMoreCourseAvailability(availabilitySignature)
        },
        resetMoreCourseAvailability() {
            this.moreCourseAvailabilityLoading = false
            this.moreCourseAvailabilityByKey = {}
            this.moreCourseAvailabilitySignature = ''
            this.moreCourseAvailabilityRequestId++
        },
        moreCourseAvailabilityCurrentSignature() {
            const candidateCourseKeys = this.moreCoursesCardItems
                .map((course) => this.moreCourseAvailabilityKey(course))
                .filter(Boolean)
                .sort((firstKey, secondKey) => firstKey.localeCompare(secondKey, 'de-AT'))

            if (!candidateCourseKeys.length) return ''

            return JSON.stringify({
                candidateCourseKeys,
                courseSelections: this.selectionSignatureEntries(this.courseSelectionOverrides),
                maxFreeDaysSelected: this.timetableMaxFreeDaysSelected === true,
                offeredCourseSelections: this.selectionSignatureEntries(this.offeredCourseSelectionOverrides),
                noSaturdaySelected: this.timetableNoSaturdaySelected === true,
            })
        },
        moreCourseAvailabilityComplete(availabilitySignature = this.moreCourseAvailabilityCurrentSignature()) {
            if (this.moreCourseAvailabilitySignature !== availabilitySignature) return false

            return this.moreCoursesCardItems.every((course) => {
                const availabilityKey = this.moreCourseAvailabilityKey(course)

                return availabilityKey
                    && Object.prototype.hasOwnProperty.call(this.moreCourseAvailabilityByKey, availabilityKey)
            })
        },
        loadMoreCourseAvailability(availabilitySignature = this.moreCourseAvailabilityCurrentSignature()) {
            const requestId = this.moreCourseAvailabilityRequestId + 1
            this.moreCourseAvailabilityRequestId = requestId
            this.moreCourseAvailabilityByKey = {}
            this.moreCourseAvailabilitySignature = availabilitySignature
            this.moreCourseAvailabilityLoading = true

            const courses = this.moreCoursesCardItems
            if (!availabilitySignature || !courses.length) {
                this.moreCourseAvailabilityLoading = false

                return Promise.resolve([])
            }

            return Promise.allSettled(courses.map((course) => this.loadMoreCourseAvailabilityForCourse(course, requestId)))
                .then((results) => {
                    if (requestId === this.moreCourseAvailabilityRequestId) {
                        this.moreCourseAvailabilityLoading = false
                    }

                    return results
                })
        },
        async loadMoreCourseAvailabilityForCourse(course, requestId) {
            const availabilityKey = this.moreCourseAvailabilityKey(course)
            if (!availabilityKey) return

            if (!this.offeredCourseItemsForSelectedCourse(course).length) {
                this.setMoreCourseAvailability(availabilityKey, false, requestId)

                return
            }

            try {
                const response = await this.requestMoreCourseAvailability(course)
                const result = response.data?.data || {}
                const validTimetableCount = this.validTimetableCountForActiveOptions(result)

                this.setMoreCourseAvailability(availabilityKey, validTimetableCount > 0, requestId)
            } catch {
                if (requestId !== this.moreCourseAvailabilityRequestId) return

                this.setMoreCourseAvailability(availabilityKey, false, requestId)
            }
        },
        validTimetableCountForActiveOptions(result = {}) {
            if (this.timetableQualityCriteriaRequired) {
                return Number(result?.selected_quality_criteria_count || 0)
            }

            return Number(result?.full_green_timetable_count || 0)
                + Number(result?.green_timetable_count || 0)
        },
        setMoreCourseAvailability(availabilityKey, available, requestId = this.moreCourseAvailabilityRequestId) {
            if (requestId !== this.moreCourseAvailabilityRequestId) return

            this.moreCourseAvailabilityByKey = {
                ...this.moreCourseAvailabilityByKey,
                [availabilityKey]: available === true,
            }

            if (!this.moreCoursesVisible) return
            if (this.selectedMoreCourseKey && this.moreCourseAvailabilityByKey[this.selectedMoreCourseKey] === false) {
                this.selectedMoreCourseKey = ''
            }
        },
        moreCourseAvailabilityKey(course) {
            return course?.selectionKey || this.courseSelectionKey(course, course?.courseGroup)
        },
        moreCourseUnavailable(course) {
            const availabilityKey = this.moreCourseAvailabilityKey(course)
            if (!availabilityKey) return false

            return this.moreCourseAvailabilityByKey[availabilityKey] === false
        },
        moreCourseDisabled(course) {
            return this.moreCourseAvailabilityLoading || this.moreCourseUnavailable(course) || this.moreCourseSelectionLocked(course)
        },
        moreCourseSelectionLocked(course) {
            const selectionKey = this.moreCourseAvailabilityKey(course)
            if (!selectionKey || !this.selectedMoreCourseKey) return false
            if (!this.selectedMoreCourseItem || !this.moreCourseOfferedCourseItemsAnySelected(this.selectedMoreCourseItem)) return false

            return this.selectedMoreCourseKey !== selectionKey
        },
        moreCourseSelectedForAdding(course) {
            const selectionKey = this.moreCourseAvailabilityKey(course)
            if (!selectionKey) return false

            return this.selectedMoreCourseKey === selectionKey
        },
        moreCourseChipColor(course) {
            return this.moreCourseUnavailable(course) ? 'error' : 'success'
        },
        moreCourseOfferedCourseItemsAllDeselected(course) {
            const offeredCourses = this.offeredCourseItemsForSelectedCourse(course)

            return offeredCourses.length > 0 && offeredCourses.every((offeredCourse) => !this.moreOfferedCourseSelected(offeredCourse))
        },
        moreCourseOfferedCourseItemsAnySelected(course) {
            return this.offeredCourseItemsForSelectedCourse(course)
                .some((offeredCourse) => this.moreOfferedCourseSelected(offeredCourse))
        },
        moreOfferedCourseSelected(course) {
            const selectionKey = course?.selectionKey || this.offeredCourseSelectionKey(course, this.selectedMoreCourseItem)
            if (!selectionKey) return false

            return this.moreOfferedCourseSelectionOverrides[selectionKey] === true
        },
        moreOfferedCourseSelectionSignature(moreOfferedCourseSelections = this.moreOfferedCourseSelectionOverrides) {
            const selections = moreOfferedCourseSelections && typeof moreOfferedCourseSelections === 'object' && !Array.isArray(moreOfferedCourseSelections)
                ? moreOfferedCourseSelections
                : {}

            return Object.entries(selections)
                .filter(([, selected]) => selected === true)
                .map(([selectionKey]) => selectionKey)
                .sort()
                .join('|')
        },
        moreCoursesSelectionSignature(
            courseSelections = this.courseSelectionOverrides,
            offeredCourseSelections = this.offeredCourseSelectionOverrides,
            moreOfferedCourseSelections = this.moreOfferedCourseSelectionOverrides,
        ) {
            return JSON.stringify({
                courseSelections: this.selectionSignatureEntries(courseSelections),
                offeredCourseSelections: this.selectionSignatureEntries(offeredCourseSelections),
                moreOfferedCourseSelections: this.selectionSignatureEntries(moreOfferedCourseSelections),
            })
        },
        selectionSignatureEntries(selections) {
            const normalizedSelections = selections && typeof selections === 'object' && !Array.isArray(selections)
                ? selections
                : {}

            return Object.entries(normalizedSelections)
                .filter(([, selected]) => selected === true || selected === false)
                .sort(([firstKey], [secondKey]) => firstKey.localeCompare(secondKey, 'de-AT'))
        },
        moreOfferedCourseSelectionsForSingleCourse(course) {
            return this.offeredCourseItemsForSelectedCourse(course).reduce((selections, offeredCourse) => {
                const selectionKey = offeredCourse?.selectionKey || this.offeredCourseSelectionKey(offeredCourse, course)
                if (!selectionKey) return selections

                return {
                    ...selections,
                    [selectionKey]: true,
                }
            }, {})
        },
        toggleMoreOfferedCourseItem(course) {
            const selectionKey = course?.selectionKey || this.offeredCourseSelectionKey(course, this.selectedMoreCourseItem)
            if (!selectionKey) return

            const moreOfferedCourseSelections = { ...this.moreOfferedCourseSelectionOverrides }

            if (this.moreOfferedCourseSelected(course)) {
                delete moreOfferedCourseSelections[selectionKey]
            } else {
                moreOfferedCourseSelections[selectionKey] = true
            }

            this.saveMoreOfferedCourseSelections(moreOfferedCourseSelections)
        },
        saveMoreOfferedCourseSelections(moreOfferedCourseSelections) {
            this.saveMoreCoursesSelectionState({
                courseSelections: { ...this.courseSelectionOverrides },
                moreOfferedCourseSelections,
                offeredCourseSelections: { ...this.offeredCourseSelectionOverrides },
            })
        },
        saveMoreCoursesSelectionState({ courseSelections, moreOfferedCourseSelections, offeredCourseSelections }) {
            const timetableV2Selection = { ...this.storedTimetableV2Selection }

            if (Object.keys(courseSelections).length) {
                timetableV2Selection.courseSelections = courseSelections
            } else {
                delete timetableV2Selection.courseSelections
            }

            if (Object.keys(offeredCourseSelections).length) {
                timetableV2Selection.offeredCourseSelections = offeredCourseSelections
            } else {
                delete timetableV2Selection.offeredCourseSelections
            }

            if (Object.keys(moreOfferedCourseSelections).length) {
                timetableV2Selection.moreOfferedCourseSelections = moreOfferedCourseSelections
            } else {
                delete timetableV2Selection.moreOfferedCourseSelections
            }

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Selection,
                transferredStudentContext: this.storedTimetableStudentContext,
            })
        },
        selectedCourseItemActive(course) {
            return this.selectedCourseItemsClickable
                && this.selectedReviewCourseItem?.selectionKey === course?.selectionKey
        },
        selectReviewCourse(course) {
            if (!this.selectedCourseItemsClickable) return

            const selectionKey = course?.selectionKey || ''

            this.selectedReviewCourseKey = this.selectedReviewCourseKey === selectionKey
                ? ''
                : selectionKey
        },
        removeSelectedCourseItem(course) {
            if (!this.selectedCourseItemsDeletable) return

            if (!this.moreCoursesVisible) {
                this.openMoreCoursesPendingActions()
            }

            this.saveCourseItemSelection(course, false)
        },
        saveCourseItemSelection(course, selected) {
            const courseGroup = course?.courseGroup
            const selectionKey = course?.selectionKey || this.courseSelectionKey(course, courseGroup)
            if (!selectionKey || !courseGroup) return

            const courseSelections = { ...this.courseSelectionOverrides }

            if (selected) {
                if (this.courseGroupDefaultSelected(courseGroup)) {
                    delete courseSelections[selectionKey]
                } else {
                    courseSelections[selectionKey] = true
                }
            } else if (this.courseGroupDefaultSelected(courseGroup)) {
                courseSelections[selectionKey] = false
            } else {
                delete courseSelections[selectionKey]
            }

            this.saveMoreCoursesSelectionState({
                courseSelections,
                moreOfferedCourseSelections: { ...this.moreOfferedCourseSelectionOverrides },
                offeredCourseSelections: { ...this.offeredCourseSelectionOverrides },
            })
        },
        offeredCourseItemsAllDeselected(course) {
            const offeredCourses = this.offeredCourseItemsForSelectedCourse(course)

            return offeredCourses.length > 0 && offeredCourses.every((offeredCourse) => !this.offeredCourseSelected(offeredCourse))
        },
        offeredCourseItemsForSelectedCourse(course) {
            const courseAliases = this.courseCodeAliases({ code: course?.code || course?.label })
            const courseGroups = this.courseGroupsForCourseAliases(courseAliases)

            const offeredCourseItems = courseGroups
                .filter((courseGroup) => this.courseGroupMatchesSelectedCourse(courseGroup, course))
                .map((courseGroup) => this.offeredCourseGroupItem(courseGroup))
                .sort((firstCourse, secondCourse) => this.compareOfferedCourseItems(firstCourse, secondCourse))

            return this.uniqueOfferedCourseItems(offeredCourseItems, course)
        },
        courseGroupsForCourseAliases(courseAliases) {
            if (!Array.isArray(courseAliases) || !courseAliases.length) return []

            const courseGroupsByCourseCode = this.courseGroupsByCourseCode

            if (!(courseGroupsByCourseCode instanceof Map)) {
                return Array.isArray(this.courseGroups) ? this.courseGroups : []
            }

            const courseGroups = new Set()

            courseAliases.forEach((courseAlias) => {
                const matchingCourseGroups = courseGroupsByCourseCode.get(courseAlias) || []

                matchingCourseGroups.forEach((courseGroup) => {
                    courseGroups.add(courseGroup)
                })
            })

            return Array.from(courseGroups)
        },
        uniqueOfferedCourseItems(courseItems, selectedCourse) {
            const courseItemsByIdentity = new Map()
            const offeredCourseItems = Array.isArray(courseItems) ? courseItems : []

            offeredCourseItems.forEach((courseItem) => {
                const identityKey = this.offeredCourseIdentityKey(courseItem)
                const existingCourseItem = courseItemsByIdentity.get(identityKey)

                if (!existingCourseItem) {
                    courseItemsByIdentity.set(identityKey, { ...courseItem })

                    return
                }

                courseItemsByIdentity.set(identityKey, {
                    ...existingCourseItem,
                    roomsLabel: this.mergedLabelList(existingCourseItem.roomsLabel, courseItem.roomsLabel),
                    recurrenceLabel: this.mergedLabelList(existingCourseItem.recurrenceLabel, courseItem.recurrenceLabel),
                    scheduleSlots: this.mergedScheduleSlots(existingCourseItem.scheduleSlots, courseItem.scheduleSlots),
                })
            })

            return [...courseItemsByIdentity.values()].map((courseItem) => {
                const scheduleSlots = this.mergedScheduleSlots(courseItem.scheduleSlots, [])

                return {
                    ...courseItem,
                    backendSelectionKey: this.offeredCourseBackendSelectionKey(courseItem, selectedCourse),
                    distanceLearning: this.offeredCourseIsDistanceLearning({ ...courseItem, scheduleSlots }, selectedCourse),
                    recurrenceLabel: courseItem.recurrenceLabel,
                    selectionKey: this.offeredCourseSelectionKey(courseItem, selectedCourse),
                    scheduleLabel: this.compactScheduleSlotsLabel(scheduleSlots),
                    scheduleSlots,
                }
            })
        },
        offeredCourseSelected(course) {
            const selectionKey = course?.selectionKey || this.offeredCourseSelectionKey(course)
            if (!selectionKey) return true

            return this.offeredCourseSelectionOverrides[selectionKey] !== false
        },
        toggleOfferedCourseItem(course) {
            const selectionKey = course?.selectionKey || this.offeredCourseSelectionKey(course)
            if (!selectionKey) return

            const offeredCourseSelections = { ...this.offeredCourseSelectionOverrides }

            if (this.offeredCourseSelected(course)) {
                offeredCourseSelections[selectionKey] = false
            } else {
                delete offeredCourseSelections[selectionKey]
            }

            this.saveOfferedCourseSelections(offeredCourseSelections)
        },
        applyOfferedCourseBulkSelection(optionKey) {
            const offeredCourses = this.allSelectedOfferedCourseItems()
            if (!offeredCourses.length) return

            const offeredCourseSelections = { ...this.offeredCourseSelectionOverrides }

            offeredCourses.forEach((course) => {
                const selectionKey = course?.selectionKey || this.offeredCourseSelectionKey(course)
                if (!selectionKey) return

                if (this.offeredCourseExcludedByBulkSelection(optionKey, course)) {
                    offeredCourseSelections[selectionKey] = false

                    return
                }

                delete offeredCourseSelections[selectionKey]
            })

            this.saveOfferedCourseSelections(offeredCourseSelections)
        },
        allSelectedOfferedCourseItems() {
            return (Array.isArray(this.selectedCourseItems) ? this.selectedCourseItems : [])
                .flatMap((course) => this.offeredCourseItemsForSelectedCourse(course))
        },
        offeredCourseExcludedByBulkSelection(optionKey, course) {
            if (optionKey === 'all') return false
            if (optionKey === 'distance-learning') return course?.distanceLearning !== true
            if (optionKey === 'without-distance-learning') return course?.distanceLearning === true

            return false
        },
        saveOfferedCourseSelections(offeredCourseSelections) {
            const timetableV2Selection = { ...this.storedTimetableV2Selection }

            if (Object.keys(offeredCourseSelections).length) {
                timetableV2Selection.offeredCourseSelections = offeredCourseSelections
            } else {
                delete timetableV2Selection.offeredCourseSelections
            }

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Selection,
                transferredStudentContext: this.storedTimetableStudentContext,
            })
        },
        offeredCourseSelectionKey(courseItem, selectedCourse = this.selectedReviewCourseItem) {
            const selectedCourseKey = selectedCourse?.selectionKey
                || this.courseSelectionKey(selectedCourse, selectedCourse?.courseGroup)
                || this.normalizedCourseCode(selectedCourse?.code || selectedCourse?.label || '')
            const offeredCourseKey = this.offeredCourseIdentityKey(courseItem)

            return [selectedCourseKey, offeredCourseKey].filter(Boolean).join('::')
        },
        offeredCourseBackendSelectionKey(courseItem, selectedCourse = this.selectedReviewCourseItem) {
            return [
                this.timetableV2CalculationCourseKey(selectedCourse),
                courseItem?.groupSelectionLabel || courseItem?.name || courseItem?.code || '',
            ].filter(Boolean).join('|')
        },
        offeredCourseIsDistanceLearning(courseItem, selectedCourse) {
            const requiredSlotCount = this.requiredSlotCountForCourse(selectedCourse)
            const scheduledWeeklyLoad = this.offeredCourseScheduledWeeklyLoad(courseItem)

            return requiredSlotCount >= 2
                && scheduledWeeklyLoad > 0
                && Math.abs((scheduledWeeklyLoad * 2) - requiredSlotCount) < 0.001
        },
        requiredSlotCountForCourse(course) {
            return Math.max(1, Math.round(Number(course?.hours || 0) || 0))
        },
        offeredCourseScheduledWeeklyLoad(courseItem) {
            return this.mergedScheduleSlots(courseItem?.scheduleSlots, [])
                .reduce((total, scheduleSlot) => total + this.courseGroupWeeklySlotLoad(scheduleSlot), 0)
        },
        courseGroupWeeklySlotLoad(courseGroup) {
            const interval = this.courseGroupWeekInterval(courseGroup)

            return interval && interval > 0 ? 1 / interval : 1
        },
        courseGroupWeekInterval(courseGroup) {
            const explicitInterval = Number(courseGroup?.recurrenceInterval || courseGroup?.recurrence_interval)
            if (Number.isInteger(explicitInterval) && explicitInterval > 0) return explicitInterval

            const labelMatch = String(courseGroup?.recurrenceLabel || courseGroup?.recurrence_label || '').match(/(\d+)\s*-\s*w/iu)
            if (labelMatch) return Number(labelMatch[1])

            const weeklyLabel = String(courseGroup?.recurrenceLabel || courseGroup?.recurrence_label || '').trim()
            if (/^w[öo]chentlich$/iu.test(weeklyLabel) || /^1\s*-\s*w[öo]chig$/iu.test(weeklyLabel)) return 1

            return null
        },
        offeredCourseIdentityKey(courseItem) {
            return [
                courseItem?.semester || '',
                this.normalizedCourseCode(courseItem?.code),
                this.normalizedCourseCode(courseItem?.name),
            ].join('|')
        },
        mergedLabelList(firstLabel, secondLabel) {
            return this.uniqueValues([
                ...String(firstLabel || '').split(','),
                ...String(secondLabel || '').split(','),
            ]
                .map((label) => label.trim())
                .filter(Boolean))
                .join(', ')
        },
        compareOfferedCourseItems(firstCourse, secondCourse) {
            const firstSemester = Number(firstCourse?.semester || 0)
            const secondSemester = Number(secondCourse?.semester || 0)
            if (firstSemester !== secondSemester) return firstSemester - secondSemester

            const firstSlot = Number(firstCourse?.weekday || 0) * 100 + Number(firstCourse?.hour || 0)
            const secondSlot = Number(secondCourse?.weekday || 0) * 100 + Number(secondCourse?.hour || 0)
            if (firstSlot !== secondSlot) return firstSlot - secondSlot

            return String(firstCourse?.name || firstCourse?.code || '').localeCompare(
                String(secondCourse?.name || secondCourse?.code || ''),
                'de-AT',
                {
                    numeric: true,
                    sensitivity: 'base',
                },
            )
        },
        courseGroupMatchesSelectedCourse(courseGroup, course) {
            const courseAliases = this.courseCodeAliases({ code: course?.code || course?.label })
            if (!courseAliases.length) return false

            const courseGroupCodes = this.courseGroupCodes(courseGroup)
            if (!courseAliases.some((courseAlias) => courseGroupCodes.includes(courseAlias))) return false

            return !this.courseGroupHasConflictingModuleCode(this.courseGroupLeadingCodes(courseGroup), courseAliases)
        },
        courseGroupHasConflictingModuleCode(leadingCourseCodes, courseAliases) {
            const aliasesWithModule = courseAliases
                .map((courseAlias) => this.courseCodeModuleParts(courseAlias))
                .filter((parts) => parts.module)

            if (!aliasesWithModule.length) return false

            return (Array.isArray(leadingCourseCodes) ? leadingCourseCodes : [])
                .map((courseCode) => this.courseCodeModuleParts(courseCode))
                .filter((parts) => parts.module)
                .some((parts) => {
                    const aliasesWithSameBase = aliasesWithModule.filter((aliasParts) =>
                        this.courseBaseAliases(aliasParts.base).includes(parts.base)
                            || this.courseBaseAliases(parts.base).includes(aliasParts.base))

                    return aliasesWithSameBase.length
                        && !aliasesWithSameBase.some((aliasParts) => aliasParts.module === parts.module)
                })
        },
        courseGroupCodes(courseGroup) {
            return this.uniqueValues([
                ...this.courseGroupLeadingCodes(courseGroup),
                ...[
                    courseGroup?.course,
                    courseGroup?.subject,
                    courseGroup?.module_code,
                    courseGroup?.title,
                    courseGroup?.display_label,
                ].flatMap((value) => this.courseCodeTokensFromValue(value)),
            ]
                .map((value) => this.normalizedCourseCode(value))
                .filter(Boolean))
        },
        courseGroupLeadingCodes(courseGroup) {
            return this.uniqueValues([
                courseGroup?.class_name,
                courseGroup?.display_label,
                courseGroup?.title,
            ]
                .flatMap((value) => this.leadingCourseCodesFromValue(value))
                .map((value) => this.normalizedCourseCode(value))
                .filter(Boolean))
        },
        leadingCourseCodesFromValue(value) {
            const firstSegment = String(value || '')
                .split(/\s+-\s+|[-\s]/u)[0]
                ?.trim() || ''

            return this.courseCodeTokensFromValue(firstSegment)
        },
        offeredCourseGroupItem(courseGroup) {
            const code = this.spacedCourseCode(courseGroup?.title || courseGroup?.course || courseGroup?.module_code || courseGroup?.subject)

            return {
                key: courseGroup?.key || [
                    courseGroup?.semester,
                    courseGroup?.weekday,
                    courseGroup?.hour,
                    courseGroup?.title,
                    courseGroup?.display_label,
                    courseGroup?.teacher,
                ].join('|'),
                code,
                groupSelectionLabel: this.courseGroupOptionLabel(courseGroup),
                name: this.offeredCourseGroupLabel(courseGroup, code),
                semester: Number(courseGroup?.semester || 0) || null,
                weekday: Number(courseGroup?.weekday || 0) || null,
                hour: Number(courseGroup?.hour || 0) || null,
                scheduleSlots: this.courseGroupScheduleSlots(courseGroup),
                scheduleLabel: this.compactScheduleSlotsLabel(this.courseGroupScheduleSlots(courseGroup)),
                recurrenceLabel: this.offeredCourseRecurrenceLabel(courseGroup),
                roomsLabel: (Array.isArray(courseGroup?.rooms) ? courseGroup.rooms : [])
                    .filter(Boolean)
                    .join(', '),
            }
        },
        offeredCourseGroupLabel(courseGroup, code) {
            const teacher = this.cleanedOfferedCourseTeacherSegment(courseGroup?.teacher, code)
            const group = this.cleanedOfferedCourseGroupSegment(
                courseGroup?.student_group || courseGroup?.class_name || courseGroup?.display_label,
                code,
                teacher,
            )
            const fallbackLabel = this.spacedCourseCode(courseGroup?.display_label || courseGroup?.title || courseGroup?.course || courseGroup?.subject)

            return this.uniqueValues([
                code,
                group,
                teacher,
            ].filter(Boolean)).join(' - ') || fallbackLabel || 'Ohne Bezeichnung'
        },
        courseGroupOptionLabel(courseGroup) {
            return String(
                courseGroup?.class_name
                || courseGroup?.display_label
                || courseGroup?.title
                || courseGroup?.course
                || courseGroup?.subject
                || 'Ohne Bezeichnung',
            ).trim()
        },
        offeredCourseCodeVisible(course) {
            const code = String(course?.code || '').trim()
            const name = String(course?.name || '').trim()
            if (!code || !name || code === name) return false

            const codeVariants = this.uniqueValues([
                code,
                this.normalizedCourseCode(code),
                String(code).replace(/\s+/gu, ''),
            ].filter(Boolean))

            return !codeVariants.some((codeVariant) => this.labelStartsWithCourseCode(name, codeVariant))
        },
        labelStartsWithCourseCode(label, code) {
            const normalizedLabel = String(label || '').trim()
            const normalizedCode = String(code || '').trim()
            if (!normalizedLabel || !normalizedCode) return false

            const escapedCode = normalizedCode.replace(/[.*+?^${}()|[\]\\]/gu, '\\$&')

            return new RegExp(`^${escapedCode}(?:\\s|-|$)`, 'iu').test(normalizedLabel)
        },
        cleanedOfferedCourseTeacherSegment(value, code) {
            let segment = String(value || '').trim()

            segment = this.stripLabelSegment(segment, code)
            segment = this.stripLabelSegment(segment, String(code || '').replace(/\s+/gu, ''))
            segment = this.stripLabelSegment(segment, this.courseCodeModuleParts(code).base)

            return segment.trim()
        },
        offeredCourseRecurrenceLabel(courseGroup) {
            if (courseGroup?.is_block || String(courseGroup?.block_label || '').trim()) {
                return this.courseGroupDateRangeLabel(courseGroup)
            }

            return this.offeredCourseScheduleRecurrenceLabel(courseGroup)
        },
        offeredCourseScheduleRecurrenceLabel(courseGroup) {
            if (courseGroup?.is_block || String(courseGroup?.block_label || '').trim()) return ''

            const interval = this.courseGroupWeekInterval(courseGroup)
            if (interval) return `${interval}-wöchig`

            return String(courseGroup?.recurrence_label || '').trim()
        },
        courseGroupDateRangeLabel(courseGroup) {
            const dates = Array.isArray(courseGroup?.dates)
                ? [...courseGroup.dates].filter(Boolean).sort()
                : []
            const from = courseGroup?.first_date || dates[0] || null
            const until = courseGroup?.last_date || dates[dates.length - 1] || from
            const fromLabel = this.formatCompactDateValue(from)
            const untilLabel = this.formatCompactDateValue(until)

            if (fromLabel && untilLabel && fromLabel !== untilLabel) {
                return `${fromLabel} - ${untilLabel}`
            }

            return fromLabel || untilLabel || ''
        },
        formatCompactDateValue(value) {
            const date = this.normalizedDate(value)
            if (!date) return value || ''

            const day = date.getDate().toString().padStart(2, '0')
            const month = (date.getMonth() + 1).toString().padStart(2, '0')

            return `${day}.${month}.`
        },
        normalizedDate(value) {
            const rawValue = String(value || '').trim()
            if (!rawValue) return null

            const match = rawValue.match(/^(\d{4})-(\d{2})-(\d{2})$/u)
            const date = match
                ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
                : new Date(rawValue)

            if (Number.isNaN(date.getTime())) return null

            date.setHours(0, 0, 0, 0)

            return date
        },
        cleanedOfferedCourseGroupSegment(value, code, teacher) {
            let segment = String(value || '').trim()

            segment = this.stripLabelSegment(segment, code)
            segment = this.stripLabelSegment(segment, String(code || '').replace(/\s+/gu, ''))
            segment = this.stripLabelSegment(segment, this.courseCodeModuleParts(code).base)
            segment = this.stripLabelSegment(segment, teacher)

            return segment
                .replace(/\s*-\s*/gu, ' - ')
                .replace(/^(?:-|\s)+|(?:-|\s)+$/gu, '')
                .trim()
        },
        courseGroupScheduleSlots(courseGroup) {
            const hour = Number(courseGroup?.hour)
            if (!Number.isFinite(hour) || hour <= 0) return []

            const timeRange = this.courseGroupTimeRange(courseGroup)

            return [{
                weekday: Number(courseGroup?.weekday || 0) || null,
                hour,
                from: timeRange.from,
                recurrenceInterval: this.courseGroupWeekInterval(courseGroup),
                recurrenceLabel: this.offeredCourseScheduleRecurrenceLabel(courseGroup),
                until: timeRange.until,
            }]
        },
        mergedScheduleSlots(firstSlots, secondSlots) {
            const scheduleSlots = [
                ...(Array.isArray(firstSlots) ? firstSlots : []),
                ...(Array.isArray(secondSlots) ? secondSlots : []),
            ]

            return Object.values(scheduleSlots.reduce((slots, slot) => {
                const key = [
                    slot?.weekday || '',
                    slot?.hour || '',
                    slot?.from || '',
                    slot?.recurrenceInterval || '',
                    slot?.recurrenceLabel || '',
                    slot?.until || '',
                ].join('|')

                slots[key] ??= slot

                return slots
            }, {})).sort((firstSlot, secondSlot) =>
                Number(firstSlot?.weekday || 0) - Number(secondSlot?.weekday || 0)
                || Number(firstSlot?.hour || 0) - Number(secondSlot?.hour || 0))
        },
        compactScheduleSlotsLabel(scheduleSlots) {
            const slots = (Array.isArray(scheduleSlots) ? scheduleSlots : [])
                .filter((slot) => Number.isFinite(Number(slot?.hour)) && Number(slot?.hour) > 0)
                .sort((firstSlot, secondSlot) =>
                    Number(firstSlot?.weekday || 0) - Number(secondSlot?.weekday || 0)
                    || Number(firstSlot?.hour || 0) - Number(secondSlot?.hour || 0))
            const ranges = []

            slots.forEach((slot) => {
                const previousRange = ranges[ranges.length - 1]
                if (previousRange && this.scheduleSlotExtendsRange(previousRange, slot)) {
                    previousRange.endHour = Number(slot.hour)
                    previousRange.until = slot.until || previousRange.until

                    return
                }

                ranges.push({
                    weekday: Number(slot.weekday || 0) || null,
                    startHour: Number(slot.hour),
                    endHour: Number(slot.hour),
                    from: slot.from || '',
                    recurrenceLabel: slot.recurrenceLabel || '',
                    until: slot.until || '',
                })
            })

            return ranges.map((range) => this.scheduleRangeLabel(range)).filter(Boolean).join(', ')
        },
        scheduleSlotExtendsRange(range, slot) {
            return Number(range?.weekday || 0) === Number(slot?.weekday || 0)
                && Number(range?.endHour || 0) + 1 === Number(slot?.hour || 0)
        },
        scheduleRangeLabel(range) {
            const weekdayLabel = this.courseGroupWeekdayLabel(range?.weekday)
            const hourLabel = Number(range?.startHour) === Number(range?.endHour)
                ? `${Number(range?.startHour)}.`
                : `${Number(range?.startHour)}.-${Number(range?.endHour)}.`
            const timeRangeLabel = [range?.from, range?.until].filter(Boolean).join('-')

            return [weekdayLabel, hourLabel, timeRangeLabel].filter(Boolean).join(' ')
        },
        courseGroupWeekdayLabel(weekday) {
            return [
                { shortTitle: 'Mo', value: 1 },
                { shortTitle: 'Di', value: 2 },
                { shortTitle: 'Mi', value: 3 },
                { shortTitle: 'Do', value: 4 },
                { shortTitle: 'Fr', value: 5 },
                { shortTitle: 'Sa', value: 6 },
            ]
                .find((option) => Number(option.value) === Number(weekday))?.shortTitle || ''
        },
        courseGroupTimeRange(courseGroup) {
            const schoolHour = (Array.isArray(this.schoolHours) ? this.schoolHours : [])
                .find((configuredSchoolHour) => Number(configuredSchoolHour?.hour) === Number(courseGroup?.hour))
            const from = this.formatTimeValue(schoolHour?.from)
            const until = this.formatTimeValue(schoolHour?.until)

            return { from, until }
        },
        stripLabelSegment(value, segment) {
            const labelSegment = String(segment || '').trim()
            if (!value || !labelSegment) return value

            const escapedSegment = labelSegment.replace(/[.*+?^${}()|[\]\\]/gu, '\\$&')

            return value
                .replace(new RegExp(`^\\s*${escapedSegment}\\s*-?\\s*`, 'iu'), '')
                .replace(new RegExp(`\\s*-?\\s*${escapedSegment}\\s*$`, 'iu'), '')
        },
        spacedCourseCode(value) {
            return String(value || '')
                .trim()
                .replace(/^([A-Za-zÄÖÜäöüß]+)\s*(\d+)$/u, '$1 $2')
        },
        formatTimeValue(value) {
            const rawValue = String(value || '').trim()

            if (!rawValue) return ''

            return rawValue.slice(0, 5)
        },
        async openStudentDialog() {
            this.studentSelectionDraft = {
                studentCode: this.normalizedStudentCode(this.storedTimetableStudentContext?.student?.studentCode),
            }
            this.studentSearch = ''
            this.studentDialogOpen = true
            await this.loadRobotStudents()
            this.$nextTick(() => this.focusStudentSearchField())
        },
        closeStudentDialog() {
            this.studentDialogOpen = false
            this.studentSearch = ''
        },
        focusStudentSearchField() {
            const searchField = this.$refs.studentSearchField

            searchField?.focus?.()
            searchField?.$el?.querySelector?.('input')?.focus?.()
        },
        async loadRobotStudents() {
            if (this.robotStudents.length || this.studentOptionsLoading) return

            this.studentOptionsLoading = true
            try {
                const response = await axios.get('/api/admin/students-timetables/robot/students')

                this.robotStudents = response.data?.data || []
            } catch {
                this.robotStudents = []
            } finally {
                this.studentOptionsLoading = false
            }
        },
        async loadCourseGroups() {
            if (this.courseGroups.length || this.courseGroupsLoading) return

            this.courseGroupsLoading = true
            this.courseGroupsError = ''

            try {
                const response = await axios.get('/api/admin/students-timetables/course-groups')

                this.courseGroups = response.data?.data || []
            } catch {
                this.courseGroups = []
                this.courseGroupsError = 'Die angebotenen Kurse konnten nicht geladen werden.'
            } finally {
                this.courseGroupsLoading = false
            }
        },
        async loadSchoolHours() {
            if (this.schoolHours.length || this.schoolHoursLoading) return

            this.schoolHoursLoading = true

            try {
                const response = await axios.get('/api/admin/students-timetables/school-hours')

                this.schoolHours = response.data?.data || []
            } catch {
                this.schoolHours = []
            } finally {
                this.schoolHoursLoading = false
            }
        },
        async loadSubjectRows() {
            if (this.subjectRows.length) {
                this.applyCourseLimitPreselection()

                return
            }

            if (this.subjectRowsLoading) return

            this.subjectRowsLoading = true
            this.subjectRowsError = ''

            try {
                const response = await axios.get('/api/admin/students-timetables/subjects-overview-settings', {
                    params: {
                        subjects_only: 1,
                    },
                })

                this.subjectRows = response.data?.data?.subjects || []
            } catch {
                this.subjectRows = []
                this.subjectRowsError = 'Die Kurse konnten nicht geladen werden.'
            } finally {
                this.subjectRowsLoading = false
                this.applyCourseLimitPreselection()
            }
        },
        async loadStoredStudentOverview(studentCode) {
            const normalizedStudentCode = this.normalizedStudentCode(studentCode)
            const selection = this.studentOverviewSelectionPayload()
            const requestKey = this.studentOverviewRequestKey(normalizedStudentCode, selection)

            if (!normalizedStudentCode) {
                this.studentCompletedCoursesError = ''
                this.studentCompletedCoursesLoading = false
                this.studentOverviewActiveRequestKey = ''
                this.studentOverviewLoadedRequestKey = ''

                return
            }

            if (requestKey && requestKey === this.studentOverviewActiveRequestKey) return

            if (requestKey && requestKey === this.studentOverviewLoadedRequestKey) {
                this.studentCompletedCoursesLoading = false

                return
            }

            const requestId = this.studentCompletedCoursesRequestId + 1
            this.studentCompletedCoursesRequestId = requestId

            this.studentOverviewActiveRequestKey = requestKey
            this.studentCompletedCoursesLoading = true
            this.studentCompletedCoursesError = ''
            this.loadSubjectRows()

            try {
                const response = await axios.get('/api/admin/students-timetables/robot/student-overview', {
                    params: {
                        student_code: normalizedStudentCode,
                        strict_selection: 1,
                        payload: 'course_history',
                        ...(Object.keys(selection).length ? { selection } : {}),
                    },
                })

                if (requestId !== this.studentCompletedCoursesRequestId) return
                if (this.storedTimetableStudentCode !== normalizedStudentCode) return

                const overviewSummary = response.data?.data || {}
                const courseHistory = this.overviewStudentCourseHistoryFromSummary(overviewSummary)
                const storedState = this.storedTimetableStateForSaving()
                const timetableV2Selection = this.timetableV2SelectionWithCourseDefaults(storedState?.timetableV2Selection || {}, courseHistory.completed)
                const nextSelection = this.studentOverviewSelectionPayloadForSelection(timetableV2Selection)
                const shouldReloadWithCourseDefaults = this.studentOverviewRequestKey(normalizedStudentCode, nextSelection) !== requestKey

                this.saveStoredTimetableState({
                    ...this.defaultStoredTimetableState(),
                    ...storedState,
                    timetableV2Selection,
                    transferredStudentContext: {
                        ...this.storedTimetableStudentContext,
                        student: {
                            ...this.storedTimetableStudentContext?.student,
                            religion: overviewSummary?.student?.religion ?? this.storedTimetableStudentContext?.student?.religion,
                        },
                        courses: {
                            ...this.storedTimetableStudentContext?.courses,
                            ...courseHistory,
                        },
                    },
                })
                this.studentOverviewLoadedRequestKey = requestKey

                if (shouldReloadWithCourseDefaults) {
                    this.loadStoredStudentOverview(normalizedStudentCode)
                } else {
                    this.applyCourseLimitPreselection()
                }
            } catch {
                if (requestId !== this.studentCompletedCoursesRequestId) return

                this.studentCompletedCoursesError = 'Die abgeschlossenen Kurse konnten nicht geladen werden.'
            } finally {
                if (requestId === this.studentCompletedCoursesRequestId) {
                    this.studentOverviewActiveRequestKey = ''
                    this.studentCompletedCoursesLoading = false
                }
            }
        },
        selectStudentDraft(studentCode) {
            this.studentSelectionDraft.studentCode = this.normalizedStudentCode(studentCode)
        },
        submitStudentSearch() {
            if (this.studentSearchReady && this.filteredStudentResults.length === 1) {
                this.selectStudentDraft(this.filteredStudentResults[0].student_code)
            }

            this.updateStudentSelection()
        },
        updateStudentSelection() {
            const studentCode = this.normalizedStudentCode(this.studentSelectionDraft.studentCode)
            const selectedStudent = studentCode ? this.robotStudents.find((student) => String(student.student_code) === studentCode) : null

            this.selectedReviewCourseKey = ''
            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                selection: this.updatedStoredSelection(selectedStudent),
                timetableV2Selection: {},
                transferredStudentContext: selectedStudent ? this.transferredStudentContextFromRobotStudent(selectedStudent) : null,
            })
            this.timetableStartMode = selectedStudent ? 'student' : ''
            this.setTimetableV2Step('selection')
            this.closeStudentDialog()
            this.studentOverviewLoadedRequestKey = ''
            this.loadStoredStudentOverview(studentCode)
        },
        clearStoredTimetableStudent() {
            this.selectedReviewCourseKey = ''
            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Selection: {},
                transferredStudentContext: null,
            })
            this.timetableStartMode = ''
            this.studentOverviewActiveRequestKey = ''
            this.studentOverviewLoadedRequestKey = ''
            this.studentCompletedCoursesLoading = false
            this.setTimetableV2Step('selection')
        },
        selectTimetableSelectionOption(key, value) {
            if (!this.selectionCardVisible) return

            this.selectedReviewCourseKey = ''
            this.setTimetableV2Step('selection')
            const timetableV2Selection = { ...this.storedTimetableV2Selection }
            if (String(timetableV2Selection[key] || '') === String(value)) {
                timetableV2Selection[key] = null
            } else {
                timetableV2Selection[key] = value
            }

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Selection,
                transferredStudentContext: this.storedTimetableStudentContext,
            })
            this.studentOverviewLoadedRequestKey = ''
            if (this.storedTimetableStudentContext) {
                this.loadStoredStudentOverview(this.storedTimetableStudentCode)
            } else {
                if (key === 'semester') {
                    this.loadSubjectRows()
                }

                this.applyCourseLimitPreselection()
            }
        },
        selectionOptionSelected(item, option) {
            return String(this.storedTimetableV2Selection?.[item.key] || '') === String(option.value)
        },
        courseItemSelected(course, courseGroup) {
            const selectionKey = this.courseSelectionKey(course, courseGroup)
            if (!selectionKey) return this.courseGroupDefaultSelected(courseGroup)

            const courseSelectionOverrides = this.courseSelectionOverrides || {}

            if (courseSelectionOverrides[selectionKey] === true) return true
            if (courseSelectionOverrides[selectionKey] === false) return false

            return this.courseGroupDefaultSelected(courseGroup)
        },
        courseGroupDefaultSelected(courseGroup) {
            return courseGroup !== 'additional'
        },
        courseGroupItems(courseGroup) {
            if (courseGroup === 'planned') {
                return this.storedPlannedCourseItems
            }

            if (courseGroup === 'additional') {
                return this.storedAdditionalCourseItems
            }

            return []
        },
        courseGroupAllSelected(courseGroup) {
            const courseItems = this.courseGroupItems(courseGroup)

            return courseItems.length > 0 && courseItems.every((course) => this.courseItemSelected(course, courseGroup))
        },
        courseGroupNoneSelected(courseGroup) {
            const courseItems = this.courseGroupItems(courseGroup)

            return courseItems.length > 0 && courseItems.every((course) => !this.courseItemSelected(course, courseGroup))
        },
        setCourseGroupSelection(courseGroup, selected) {
            const courseItems = this.courseGroupItems(courseGroup)
            if (!courseItems.length) return

            const timetableV2Selection = { ...this.storedTimetableV2Selection }
            const courseSelections = { ...this.courseSelectionOverrides }

            courseItems.forEach((course) => {
                const selectionKey = this.courseSelectionKey(course, courseGroup)
                if (!selectionKey) return

                if (selected) {
                    if (this.courseGroupDefaultSelected(courseGroup)) {
                        delete courseSelections[selectionKey]
                    } else {
                        courseSelections[selectionKey] = true
                    }
                } else {
                    if (this.courseGroupDefaultSelected(courseGroup)) {
                        courseSelections[selectionKey] = false
                    } else {
                        delete courseSelections[selectionKey]
                    }
                }
            })

            if (Object.keys(courseSelections).length) {
                timetableV2Selection.courseSelections = courseSelections
            } else {
                delete timetableV2Selection.courseSelections
            }

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Selection,
                transferredStudentContext: this.storedTimetableStudentContext,
            })
        },
        toggleCourseItem(course, courseGroup) {
            const selectionKey = this.courseSelectionKey(course, courseGroup)
            if (!selectionKey) return

            const timetableV2Selection = { ...this.storedTimetableV2Selection }
            const courseSelections = { ...this.courseSelectionOverrides }

            if (this.courseItemSelected(course, courseGroup)) {
                if (this.courseGroupDefaultSelected(courseGroup)) {
                    courseSelections[selectionKey] = false
                } else {
                    delete courseSelections[selectionKey]
                }
            } else {
                if (this.courseGroupDefaultSelected(courseGroup)) {
                    delete courseSelections[selectionKey]
                } else {
                    courseSelections[selectionKey] = true
                }
            }

            if (Object.keys(courseSelections).length) {
                timetableV2Selection.courseSelections = courseSelections
            } else {
                delete timetableV2Selection.courseSelections
            }

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Selection,
                transferredStudentContext: this.storedTimetableStudentContext,
            })
        },
        courseSelectionKey(course, courseGroup) {
            const courseKey = this.normalizedCourseCode(course?.code || '')
                || String(course?.key || course?.label || '').trim()

            return courseKey ? `${courseGroup}:${courseKey}` : ''
        },
        updatedStoredSelection(selectedStudent) {
            const currentSelection = this.storedTimetableStateForSaving()?.selection || {}
            const semester = this.studentSemester(selectedStudent)

            if (!semester) return currentSelection

            return {
                ...currentSelection,
                semester,
            }
        },
        storedTimetableStateForSaving() {
            return this.storedTimetableState || this.parseStoredTimetableState(this.timetableStorage()?.getItem(this.timetableStorageKey())) || null
        },
        saveStoredTimetableState(state) {
            try {
                this.timetableStorage()?.setItem(this.timetableStorageKey(), JSON.stringify(state))
            } catch {
                // Ignore unavailable or full browser storage.
            }

            this.storageRevision++
        },
        syncStoredTimetableOptions() {
            this.timetableNoSaturdaySelected = this.storedTimetableV2Options.noSaturday === true
            this.timetableNoSaturdayDraftSelected = this.timetableNoSaturdaySelected
            this.timetableMaxFreeDaysSelected = this.storedTimetableV2Options.maxFreeDays === true
            this.timetableMaxFreeDaysDraftSelected = this.timetableMaxFreeDaysSelected
        },
        timetableV2OptionsForSaving() {
            return {
                maxFreeDays: this.timetableMaxFreeDaysSelected === true,
                noSaturday: this.timetableNoSaturdaySelected === true,
            }
        },
        saveTimetableV2Options() {
            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Options: this.timetableV2OptionsForSaving(),
            })
        },
        defaultStoredTimetableState() {
            return {
                selection: {
                    semester: 1,
                    religion: 'ETH',
                    language: 'L',
                    branch: 'wirtschaftskundlich',
                    artsSubject: 'ME',
                },
                timetableV2Selection: {},
                timetableV2Options: {
                    maxFreeDays: false,
                    noSaturday: false,
                },
                transferredStudentContext: null,
            }
        },
        defaultNoStudentTimetableV2Selection() {
            return {
                semester: 1,
            }
        },
        knownSelectionValue(value = null) {
            const normalizedValue = String(value || '').trim()

            return normalizedValue || '--'
        },
        courseReviewSelectionValue(item) {
            if (item?.key === 'semester') {
                return this.courseReviewSemesterSelectionValue(item)
            }

            if (item?.value && item.value !== '--') return item.value

            const value = this.storedTimetableV2Selection?.[item.key]
            if (String(value || '').trim() === '') return ''

            return this.selectedOptionTitle(item.options || [], value)
        },
        courseReviewSemesterSelectionValue(item) {
            const value = item?.value && item.value !== '--'
                ? item.value
                : this.storedTimetableV2Selection?.semester
            const semester = this.semesterValueFromLabel(value)

            if (!semester) return this.knownSelectionValue(value)

            return this.selectedOptionTitle(this.semesterOptions(), semester)
        },
        selectionValueIsKnown(value) {
            return String(value || '').trim() !== ''
        },
        completedCourseItemsFromApi(courses) {
            return this.normalizedCompletedCourseItems(courses)
        },
        missingCourseItemsFromApi(courses) {
            return this.normalizedMissingCourseItems(courses)
        },
        overviewStudentCourseHistoryFromSummary(overviewSummary) {
            const automaticCourseSections = Array.isArray(overviewSummary?.automatic_course_selection?.sections) ? overviewSummary.automatic_course_selection.sections : []
            const automaticMissingCourses = automaticCourseSections.find((section) => section?.key === 'missing')?.items
            const automaticPlannedCourses = automaticCourseSections.find((section) => section?.key === 'proposed')?.items

            return {
                completed: this.completedCourseItemsFromApi(overviewSummary?.completed_courses || []),
                failed: this.missingCourseItemsFromApi(overviewSummary?.completed_courses || []),
                missing: this.normalizedOverviewCourseItems(automaticMissingCourses || overviewSummary?.missing_courses || []),
                planned: this.sortedCourseItems(
                    this.normalizedOverviewCourseItems(automaticPlannedCourses || overviewSummary?.proposed_courses || []),
                ),
                additional: this.normalizedOverviewCourseItems(overviewSummary?.additional_courses || []),
            }
        },
        normalizedCompletedCourseItems(courses) {
            return (Array.isArray(courses) ? courses : [])
                .map((course, index) => {
                    const code = String(course?.code || course?.subject || '').trim()
                    const label = String(course?.label || code || course?.name || '').trim()
                    const grade = String(course?.meta || course?.grade || '').trim()

                    return {
                        key: String(course?.key || `completed-${label || index}-${grade || index}`).trim(),
                        code,
                        label,
                        meta: grade,
                    }
                })
                .filter((course) => course.label)
                .filter((course) => this.completedCourseGradeIsAccepted(course.meta))
        },
        normalizedMissingCourseItems(courses) {
            return (Array.isArray(courses) ? courses : [])
                .map((course, index) => {
                    const code = String(course?.code || course?.subject || '').trim()
                    const label = String(course?.label || code || course?.name || '').trim()
                    const grade = String(course?.meta || course?.grade || '').trim()

                    return {
                        key: String(course?.key || `missing-${label || index}-${grade || index}`).trim(),
                        code,
                        label,
                        meta: grade,
                    }
                })
                .filter((course) => course.label)
                .filter((course) => this.missingCourseGradeIsAccepted(course.meta))
        },
        normalizedOverviewCourseItems(courses) {
            return (Array.isArray(courses) ? courses : [])
                .map((course, index) => {
                    const code = String(course?.code || course?.subject || '').trim()
                    const name = String(course?.name || course?.title || '').trim()
                    const label = String(course?.label || code || name).trim()
                    const hours = Number(course?.hours || course?.hours_per_week || 0)
                    const meta = String(course?.meta || course?.hours_label || (hours ? `${this.formatHours(hours)} Std.` : '')).trim()
                    const semester = Number(course?.semester || course?.subject_semester || course?.semester_number || 0)

                    return {
                        key: String(course?.key || `${code || label || index}-${index}`).trim(),
                        code,
                        hours,
                        semester: Number.isFinite(semester) && semester > 0 ? semester : null,
                        name,
                        label,
                        meta,
                    }
                })
                .filter((course) => course.label)
        },
        uniqueCourseItems(courses) {
            const courseItemsByKey = new Map()
            const courseItems = Array.isArray(courses) ? courses : []

            courseItems.forEach((course) => {
                const key = String(course?.code || course?.key || course?.label || '').trim()
                if (!key || courseItemsByKey.has(key)) return

                courseItemsByKey.set(key, course)
            })

            return [...courseItemsByKey.values()]
        },
        sortedCourseItems(courses) {
            return [...(Array.isArray(courses) ? courses : [])]
                .sort((firstCourse, secondCourse) => this.courseItemSortValue(firstCourse)
                    .localeCompare(this.courseItemSortValue(secondCourse), 'de-AT', {
                        numeric: true,
                        sensitivity: 'base',
                    }))
        },
        courseItemSortValue(course) {
            return String(course?.code || course?.label || course?.name || '').trim()
        },
        courseSemesterNumber(course) {
            const semester = Number(course?.semester || course?.subject_semester || course?.semester_number || 0)
            if (Number.isFinite(semester) && semester > 0) return semester

            return this.courseModuleNumber(course)
        },
        courseModuleNumber(course) {
            const courseValue = String(course?.code || course?.label || course?.name || '').trim()
            const moduleMatch = courseValue.match(/(\d+)(?!.*\d)/u)

            return moduleMatch ? Number(moduleMatch[1]) : 0
        },
        courseBaseCode(course) {
            return String(course?.code || course?.label || course?.name || '')
                .trim()
                .toLocaleUpperCase('de-AT')
                .replace(/\d+$/u, '')
        },
        courseLimitBasePriority(course) {
            const baseCode = this.courseBaseCode(course)
            const coreCoursePriority = ['L', 'F', 'S', 'E', 'ETH', 'M', 'D'].indexOf(baseCode)

            return coreCoursePriority === -1 ? 0 : coreCoursePriority + 1
        },
        courseItemsSummary(courses) {
            const courseItems = Array.isArray(courses) ? courses : []
            const hours = courseItems.reduce((sum, course) => sum + this.courseHoursNumber(course), 0)

            return {
                count: courseItems.length,
                hours,
                countLabel: `${this.formatNumber(courseItems.length)} ${courseItems.length === 1 ? 'Kurs' : 'Kurse'}`,
                hoursLabel: `${this.formatHours(hours)} Std.`,
            }
        },
        courseHoursNumber(course) {
            const numericHours = Number(course?.hours ?? course?.hours_per_week ?? 0)

            if (Number.isFinite(numericHours) && numericHours > 0) return numericHours

            const hoursMatch = String(course?.meta || course?.hours_label || '').match(/(\d+(?:[,.]\d+)?)\s*Std/iu)

            return hoursMatch ? Number(hoursMatch[1].replace(',', '.')) : 0
        },
        subjectRowHoursForCourseCode(code) {
            const normalizedCode = this.normalizedCourseCode(code)
            if (!normalizedCode) return 0

            const matchingSubject = (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .filter((subject) => subject?.is_active !== false)
                .flatMap((subject) => this.subjectCourseVariants(subject))
                .map((subject) => ({
                    code: this.normalizedCourseCode(subject?.json_code || subject?.json_subject || subject?.name),
                    hours: Number(subject?.hours_per_week || 0),
                }))
                .find((subject) => subject.code === normalizedCode)

            return Number.isFinite(matchingSubject?.hours) && matchingSubject.hours > 0 ? matchingSubject.hours : 0
        },
        noStudentPlannedCourses() {
            return this.coursesForSemester(this.noStudentSelectedSemester)
        },
        noStudentAdditionalCourses() {
            const plannedCourses = this.noStudentPlannedCourses()
            const plannedCourseCodes = this.courseCodeSet(plannedCourses)

            return this.coursesAfterSemester(this.noStudentSelectedSemester)
                .filter((course) => !this.courseCodeSetContainsCourse(plannedCourseCodes, course))
                .filter((course) => this.coursePossibleAsAdditionalCourse(course, plannedCourseCodes))
                .filter((course, index, courses) =>
                    courses.findIndex((candidate) => this.courseUniqueKey(candidate) === this.courseUniqueKey(course)) === index)
        },
        coursesForSemester(semester) {
            return this.sortedCourseItems((Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .filter((subject) => subject?.is_active !== false)
                .filter((subject) => Number(subject?.semester) === Number(semester))
                .filter((subject) => this.subjectMatchesSelectedBranch(subject))
                .filter((subject) => this.subjectMatchesSelectedChoices(subject))
                .flatMap((subject) => this.selectedCoursesFromSubject(subject))
                .filter((course, index, courses) =>
                    courses.findIndex((candidate) => this.courseUniqueKey(candidate) === this.courseUniqueKey(course)) === index))
        },
        coursesAfterSemester(semester) {
            return (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .map((subject) => Number(subject?.semester))
                .filter((subjectSemester) => Number.isFinite(subjectSemester) && subjectSemester > Number(semester))
                .filter((subjectSemester, index, subjectSemesters) => subjectSemesters.indexOf(subjectSemester) === index)
                .sort((firstSemester, secondSemester) => firstSemester - secondSemester)
                .flatMap((subjectSemester) => this.coursesForSemester(subjectSemester))
        },
        subjectMatchesSelectedBranch(subject) {
            if (this.isArtsSubject(subject)) return true

            const branch = String(subject?.branch || '').trim()

            return !branch || branch === 'common' || branch === this.effectiveTimetableV2Selection.branch
        },
        subjectMatchesSelectedChoices(subject) {
            if (this.isReligionSubject(subject)) return Boolean(this.effectiveTimetableV2Selection.religion)
            if (this.isArtsSubject(subject)) return this.subjectBaseKey(subject) === this.effectiveTimetableV2Selection.artsSubject
            if (this.isLanguageSubject(subject)) return this.languageSubjectMatchesSelection(subject)

            return true
        },
        languageSubjectMatchesSelection(subject) {
            const selectedLanguage = String(this.effectiveTimetableV2Selection.language || '').trim()
            if (!selectedLanguage) return false

            const languageCode = this.languageSubjectCode(subject)

            return !languageCode || languageCode === selectedLanguage
        },
        languageSubjectCode(subject) {
            const rawBaseKey = this.subjectBaseKey(subject)
            const baseKey = this.normalizedCourseCode(rawBaseKey)
            if (['L', 'F', 'S'].includes(baseKey)) return baseKey
            if (rawBaseKey !== 'L/F/S') return ''

            const jsonCodeParts = this.courseCodeAliasParts(this.courseCodeWithoutModule(subject?.json_code))
                .map((value) => this.normalizedCourseCode(value))

            return jsonCodeParts.length === 1 && ['L', 'F', 'S'].includes(jsonCodeParts[0])
                ? jsonCodeParts[0]
                : ''
        },
        selectedCoursesFromSubject(subject) {
            return this.subjectCourseVariants(subject)
                .map((courseSubject) => this.selectedCourseFromSubject(courseSubject))
        },
        subjectCourseVariants(subject) {
            if (this.isReligionSubject(subject) || this.isLanguageSubject(subject)) return [subject]

            const courseCodes = this.courseCodeAliasParts(subject?.json_code)
            if (courseCodes.length <= 1) return [subject]

            const splitHours = Number(subject?.hours_per_week || 0) / courseCodes.length

            return courseCodes.map((courseCode) => ({
                ...subject,
                json_code: courseCode,
                hours_per_week: Number.isFinite(splitHours) ? splitHours : subject?.hours_per_week,
            }))
        },
        selectedCourseFromSubject(subject) {
            const code = this.selectedCourseCode(subject)

            return {
                key: [
                    subject?.id || subject?.local_id || '',
                    subject?.semester || '',
                    subject?.branch || 'common',
                    subject?.json_code || '',
                    subject?.json_subject || '',
                    subject?.name || '',
                    code,
                ].join('|'),
                code,
                name: this.selectedCourseName(subject),
                hours: Number(subject?.hours_per_week || 0),
                semester: Number(subject?.semester || 0) || null,
            }
        },
        selectedCourseCode(subject) {
            if (this.isReligionSubject(subject)) return `${this.effectiveTimetableV2Selection.religion}${this.subjectModuleNumber(subject)}`
            if (this.isLanguageSubject(subject)) return `${this.effectiveTimetableV2Selection.language}${this.subjectModuleNumber(subject)}`

            return this.alternativeDisplay(subject?.json_code || subject?.json_subject || subject?.name)
        },
        selectedCourseName(subject) {
            const moduleNumber = this.subjectModuleNumber(subject)

            if (this.isReligionSubject(subject)) {
                return `${this.selectedOptionDescription(this.religionOptions(), this.effectiveTimetableV2Selection.religion)} ${moduleNumber}`.trim()
            }

            if (this.isLanguageSubject(subject)) {
                return `${this.selectedOptionDescription(this.languageOptions(), this.effectiveTimetableV2Selection.language)} ${moduleNumber}`.trim()
            }

            if (this.isArtsSubject(subject)) {
                return `${this.selectedOptionDescription(this.artsSubjectOptions(), this.effectiveTimetableV2Selection.artsSubject)} ${moduleNumber}`.trim()
            }

            return subject?.name || subject?.json_subject || subject?.json_code || '-'
        },
        selectedOptionDescription(options, value) {
            return String(this.selectedOptionTitle(options, value)).split(' - ').pop()
        },
        selectedOptionTitle(options, value) {
            return (Array.isArray(options) ? options : []).find((option) => String(option.value) === String(value))?.title || value || ''
        },
        subjectBaseKey(subject) {
            const jsonSubject = String(subject?.json_subject || '').trim()

            return jsonSubject || String(subject?.json_code || '').replace(/\d+$/u, '')
        },
        subjectModuleNumber(subject) {
            return String(subject?.json_code || '').match(/(\d+)$/u)?.[1] || ''
        },
        isReligionSubject(subject) {
            return this.subjectBaseKey(subject) === 'R/ET'
        },
        isLanguageSubject(subject) {
            const rawBaseKey = this.subjectBaseKey(subject)
            const baseKey = this.normalizedCourseCode(rawBaseKey)

            return rawBaseKey === 'L/F/S' || ['L', 'F', 'S'].includes(baseKey)
        },
        isArtsSubject(subject) {
            return ['ME', 'BE'].includes(this.subjectBaseKey(subject))
        },
        coursePossibleAsAdditionalCourse(course, plannedCourseCodes) {
            return this.courseModuleParts(course)
                .some((parts) => this.courseModulePrerequisiteMet(parts, plannedCourseCodes))
        },
        courseModuleParts(course) {
            return this.courseCodeAliasParts(course?.code)
                .map((courseCode) => this.courseCodeModuleParts(courseCode))
                .filter((parts) => parts.module)
                .filter((parts, index, allParts) =>
                    allParts.findIndex((candidate) => candidate.base === parts.base && candidate.module === parts.module) === index)
        },
        courseModulePrerequisiteMet(parts, plannedCourseCodes) {
            const moduleNumber = Number(parts.module)
            if (!Number.isInteger(moduleNumber)) return false
            if (moduleNumber === 1) return true

            const baseAliases = this.courseBaseAliases(parts.base)
            if (moduleNumber === 2) {
                return baseAliases.some((baseAlias) => plannedCourseCodes.has(`${baseAlias}1`))
            }

            return false
        },
        courseCodeSet(courses) {
            return new Set((Array.isArray(courses) ? courses : [])
                .flatMap((course) => this.courseCodeAliases(course))
                .filter(Boolean))
        },
        courseCodeSetContainsCourse(courseCodes, course) {
            return this.courseCodeAliases(course)
                .some((courseCode) => courseCodes.has(courseCode))
        },
        courseCodeAliases(course) {
            return this.courseCodeAliasParts(course?.code)
                .map((courseCode) => this.normalizedCourseCode(courseCode))
                .flatMap((courseCode) => {
                    const { base, module } = this.courseCodeModuleParts(courseCode)

                    return this.courseBaseAliases(base).map((baseAlias) => `${baseAlias}${module}`)
                })
                .filter(Boolean)
        },
        courseBaseAliases(base) {
            const normalizedBase = this.normalizedCourseCode(base)
            const mappedAliases = {
                ET: ['ETH', 'R', 'RK'],
                ETH: ['ET', 'R', 'RK'],
                GPB: ['GS'],
                GS: ['GPB'],
                GW: ['GWB'],
                GWB: ['GW'],
                LPT: ['LET'],
                LET: ['LPT'],
                ME: ['MU'],
                MU: ['ME'],
                R: ['RK', 'ET', 'ETH'],
                RK: ['R', 'ET', 'ETH'],
                S: ['SPA'],
                SPA: ['S'],
            }

            return this.uniqueValues([normalizedBase, ...(mappedAliases[normalizedBase] || [])].filter(Boolean))
        },
        courseUniqueKey(course) {
            return this.normalizedCourseCode(course?.code || '') || String(course?.key || '')
        },
        alternativeDisplay(value) {
            const normalizedValue = String(value || '').trim()
            if (!normalizedValue.includes('/')) return normalizedValue || '-'

            return normalizedValue
                .split('/')
                .map((part) => part.trim())
                .filter(Boolean)
                .join(' / ')
        },
        completedCourseGradeIsAccepted(grade) {
            const normalizedGrade = String(grade || '')
                .trim()
                .toLocaleUpperCase('de-AT')

            return normalizedGrade !== '' && !['5', 'N'].includes(normalizedGrade)
        },
        missingCourseGradeIsAccepted(grade) {
            const normalizedGrade = String(grade || '')
                .trim()
                .toLocaleUpperCase('de-AT')

            return ['5', 'N'].includes(normalizedGrade)
        },
        timetableV2SelectionWithCourseDefaults(selection, completedCourses) {
            const courseDefaults = this.selectedStudentCourseHistoryDefaults(completedCourses)

            return Object.entries(courseDefaults).reduce(
                (nextSelection, [key, value]) => {
                    if (!Object.prototype.hasOwnProperty.call(nextSelection, key)) {
                        nextSelection[key] = value
                    }

                    return nextSelection
                },
                { ...(selection || {}) }
            )
        },
        studentOverviewSelectionPayload() {
            return this.studentOverviewSelectionPayloadForSelection(this.storedTimetableV2Selection || {})
        },
        studentOverviewSelectionPayloadForSelection(selection) {
            const semester = this.semesterValueFromLabel(this.storedTimetableStudentContext?.student?.semesterLabel)
                || selection.semester

            return Object.fromEntries(
                [
                    ['semester', semester],
                    ['religion', selection.religion],
                    ['language', selection.language],
                    ['branch', selection.branch],
                    ['artsSubject', selection.artsSubject],
                ].filter(([, value]) => String(value || '').trim() !== '')
            )
        },
        studentOverviewRequestKey(studentCode, selection) {
            return JSON.stringify({
                studentCode: this.normalizedStudentCode(studentCode),
                selection,
            })
        },
        selectedStudentCourseHistoryDefaults(completedCourses) {
            const completedCourseCodes = this.studentCourseCodeSet(completedCourses)

            return Object.fromEntries(
                [
                    ['religion', this.inferredSelectionOptionFromCourseCodes(this.religionOptions(), completedCourseCodes)],
                    ['language', this.inferredSelectionOptionFromCourseCodes(this.languageOptions(), completedCourseCodes)],
                    ['branch', this.inferredBranchFromCourseCodes(completedCourseCodes)],
                    ['artsSubject', this.inferredSelectionOptionFromCourseCodes(this.artsSubjectOptions(), completedCourseCodes)],
                ].filter(([, value]) => Boolean(value))
            )
        },
        studentCourseCodeSet(courses) {
            return new Set(
                (Array.isArray(courses) ? courses : [])
                    .flatMap((course) => this.courseCodesFromCourse(course))
                    .map((courseCode) => this.normalizedCourseCode(courseCode))
                    .filter(Boolean)
            )
        },
        courseCodesFromCourse(course) {
            return this.uniqueValues([course?.code, course?.label, course?.name].flatMap((value) => this.courseCodeTokensFromValue(value)))
        },
        courseCodeTokensFromValue(value) {
            return (
                String(value || '')
                    .toLocaleUpperCase('de-AT')
                    .match(/[A-ZÄÖÜ]+[0-9]*/gu) || []
            )
        },
        inferredSelectionOptionFromCourseCodes(options, courseCodes) {
            if (!(courseCodes instanceof Set) || !courseCodes.size) return ''

            return (
                (Array.isArray(options) ? options : [])
                    .map((option, optionIndex) => ({
                        option,
                        optionIndex,
                        match: this.bestSelectionOptionCourseCodeMatch(option?.value, courseCodes),
                    }))
                    .filter(({ match }) => match)
                    .sort(
                        (firstOption, secondOption) =>
                            secondOption.match.module - firstOption.match.module ||
                            firstOption.optionIndex - secondOption.optionIndex ||
                            secondOption.match.courseIndex - firstOption.match.courseIndex
                    )[0]?.option?.value || ''
            )
        },
        bestSelectionOptionCourseCodeMatch(value, courseCodes) {
            const optionAliases = this.selectionCourseAliases(value).map((alias) => this.courseCodeWithoutModule(alias))
            if (!optionAliases.length) return null

            return (
                [...courseCodes]
                    .map((courseCode, courseIndex) => ({
                        ...this.courseCodeModuleParts(courseCode),
                        courseIndex,
                    }))
                    .filter((parts) => optionAliases.includes(parts.base))
                    .map((parts) => ({
                        module: Number(parts.module || 0),
                        courseIndex: parts.courseIndex,
                    }))
                    .sort((firstMatch, secondMatch) => secondMatch.module - firstMatch.module || secondMatch.courseIndex - firstMatch.courseIndex)[0] || null
            )
        },
        inferredBranchFromCourseCodes(courseCodes) {
            if (!(courseCodes instanceof Set) || !courseCodes.size) return ''

            const branchAliases = [
                {
                    value: 'wirtschaftskundlich',
                    aliases: ['INF', 'OKO', 'OEKO', 'OEK', 'WIKU', 'BWL', 'RW', 'WR'],
                },
            ]
            const matchingBranch = branchAliases
                .map((branch, branchIndex) => ({
                    branch,
                    branchIndex,
                    match: this.bestSelectionAliasesCourseCodeMatch(branch.aliases, courseCodes),
                }))
                .filter(({ match }) => match)
                .sort(
                    (firstBranch, secondBranch) =>
                        secondBranch.match.module - firstBranch.match.module ||
                        firstBranch.branchIndex - secondBranch.branchIndex ||
                        secondBranch.match.courseIndex - firstBranch.match.courseIndex
                )[0]

            return matchingBranch?.branch?.value || ''
        },
        bestSelectionAliasesCourseCodeMatch(aliases, courseCodes) {
            const optionAliases = (Array.isArray(aliases) ? aliases : []).flatMap((alias) => this.selectionCourseAliases(alias)).map((alias) => this.courseCodeWithoutModule(alias))
            if (!optionAliases.length) return null

            return (
                [...courseCodes]
                    .map((courseCode, courseIndex) => ({
                        ...this.courseCodeModuleParts(courseCode),
                        courseIndex,
                    }))
                    .filter((parts) => optionAliases.includes(parts.base))
                    .map((parts) => ({
                        module: Number(parts.module || 0),
                        courseIndex: parts.courseIndex,
                    }))
                    .sort((firstMatch, secondMatch) => secondMatch.module - firstMatch.module || secondMatch.courseIndex - firstMatch.courseIndex)[0] || null
            )
        },
        selectionCourseAliases(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const aliases = {
                BE: ['BE'],
                ET: ['ETH', 'ET'],
                ETH: ['ETH', 'ET'],
                F: ['F', 'FR', 'FRA', 'FRZ'],
                INF: ['INF'],
                L: ['L', 'LET', 'LPT'],
                LET: ['L', 'LET', 'LPT'],
                LPT: ['L', 'LET', 'LPT'],
                ME: ['ME', 'MU'],
                MU: ['ME', 'MU'],
                R: ['RK', 'R'],
                REV: ['REV', 'EV', 'EVANG'],
                RIS: ['RIS', 'ISLAM'],
                RK: ['RK', 'R'],
                RKATH: ['RK', 'R'],
                ROR: ['ROR', 'ORTH'],
                S: ['S', 'SPA'],
                SPA: ['S', 'SPA'],
            }

            return this.uniqueValues([normalizedValue, ...(aliases[normalizedValue] || [])].filter(Boolean))
        },
        courseCodeAliasParts(value) {
            return String(value || '')
                .split('/')
                .map((part) => part.trim())
                .filter(Boolean)
        },
        courseCodeWithoutModule(value) {
            return this.courseCodeModuleParts(value).base
        },
        courseCodeModuleParts(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const match = normalizedValue.match(/^([A-Z]+)([0-9]*)$/u)

            if (!match) {
                return {
                    base: normalizedValue,
                    module: '',
                }
            }

            return {
                base: match[1],
                module: match[2] || '',
            }
        },
        normalizedCourseCode(value) {
            return String(value || '')
                .trim()
                .toLocaleUpperCase('de-AT')
                .replace(/Ä/gu, 'AE')
                .replace(/Ö/gu, 'OE')
                .replace(/Ü/gu, 'UE')
                .replace(/ß/gu, 'SS')
                .replace(/[^A-Z0-9]/gu, '')
        },
        uniqueValues(values) {
            return (Array.isArray(values) ? values : []).filter((value, index, allValues) => allValues.indexOf(value) === index)
        },
        religionOptions() {
            return [
                { title: 'ETH - Ethik', value: 'ETH' },
                { title: 'Rev - Religion evangelisch', value: 'Rev' },
                { title: 'Ris - Religion Islam', value: 'Ris' },
                { title: 'Rk - Religion katholisch', value: 'Rk' },
                { title: 'Ror - Religion orthodox', value: 'Ror' },
            ]
        },
        religionOptionsForSelectedStudent() {
            const options = this.religionOptions()
            const religion = this.storedTimetableStudentReligion()

            if (!religion || this.studentReligionMatchesNoConfession(religion)) return options

            const allowedValues = ['ETH', this.studentReligionOptionValue(religion)].filter(Boolean)

            return options.filter((option) => allowedValues.includes(option.value))
        },
        storedTimetableStudentReligion() {
            return String(this.storedTimetableStudentContext?.student?.religion || '').trim()
        },
        normalizedStudentReligion(value) {
            return String(value || '')
                .trim()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/gu, '')
                .replace(/ß/gu, 'ss')
                .replace(/[^a-z0-9]/gu, '')
        },
        studentReligionMatchesNoConfession(religion) {
            const normalizedReligion = this.normalizedStudentReligion(religion)

            return ['ob', 'ohnebekenntnis', 'ohnebekenntniss', 'keinbekenntnis', 'keinbekenntniss', 'konfessionslos'].includes(normalizedReligion)
        },
        studentReligionOptionValue(religion) {
            const normalizedReligion = this.normalizedStudentReligion(religion)
            const matchingReligionOption = this.studentReligionOptionAliases().find((option) =>
                option.aliases.some(
                    (alias) =>
                        normalizedReligion === alias ||
                        (alias.length >= 4 && normalizedReligion.includes(alias)) ||
                        (normalizedReligion.length >= 4 && alias.includes(normalizedReligion))
                )
            )

            return matchingReligionOption?.value || null
        },
        studentReligionOptionAliases() {
            return [
                {
                    value: 'Rev',
                    aliases: ['rev', 'ev', 'evang', 'evangelisch', 'evangab', 'evangelischab'],
                },
                {
                    value: 'Ris',
                    aliases: ['ris', 'islam', 'islamisch', 'muslim', 'moslem'],
                },
                {
                    value: 'Rk',
                    aliases: ['rk', 'kath', 'katholisch', 'romkath', 'roemkath', 'roemischkatholisch'],
                },
                {
                    value: 'Ror',
                    aliases: ['ror', 'orth', 'orthodox', 'griechorth', 'griechischorthodox'],
                },
            ]
        },
        languageOptions() {
            return [
                { title: 'L - Latein', value: 'L' },
                { title: 'F - Französisch', value: 'F' },
                { title: 'S - Spanisch', value: 'S' },
            ]
        },
        branchOptions() {
            return [
                { title: 'Wirtschaftskundlicher Zweig', value: 'wirtschaftskundlich' },
                { title: 'Gymnasialer Zweig', value: 'gymnasial' },
            ]
        },
        artsSubjectOptions() {
            return [
                { title: 'ME - Musikerziehung', value: 'ME' },
                { title: 'BE - Bildnerische Erziehung', value: 'BE' },
            ]
        },
        timetableStorageKeys() {
            const schoolyearId = this.selectedSchoolyear?.id || 'default'

            return [`${TIMETABLE_STORAGE_KEY_PREFIX}:${schoolyearId}`, `${TIMETABLE_STORAGE_KEY_PREFIX}:default`].filter((key, index, keys) => keys.indexOf(key) === index)
        },
        timetableStorageKey() {
            return `${TIMETABLE_STORAGE_KEY_PREFIX}:${this.selectedSchoolyear?.id || 'default'}`
        },
        timetableStorage() {
            if (typeof window === 'undefined' || !window.localStorage) {
                return null
            }

            return window.localStorage
        },
        parseStoredTimetableState(value) {
            if (!value) return null

            try {
                return JSON.parse(value)
            } catch {
                return null
            }
        },
        transferredStudentContextFromRobotStudent(student) {
            if (!student) return null

            return {
                student: {
                    studentCode: this.normalizedStudentCode(student.student_code),
                    label: this.studentOptionTitle(student),
                    semesterLabel: this.studentSemesterLabel(student),
                    religion: String(student.religion || '').trim(),
                    email: String(student.email || '').trim(),
                },
                courses: {
                    completed: [],
                    missing: [],
                    planned: [],
                    additional: [],
                },
            }
        },
        studentOptionTitle(student) {
            const schoolClass = String(student?.class || '').trim()
            const lastName = String(student?.last_name || '').trim()
            const firstName = String(student?.first_name || '').trim()
            const semester = this.studentSemesterLabel(student)
            const name = [lastName, firstName].filter(Boolean).join(' ')

            return [schoolClass, name, semester].filter(Boolean).join(' · ')
        },
        studentSemesterLabel(student) {
            const semester = this.studentSemester(student)

            return semester ? `Semester ${semester}` : ''
        },
        semesterValueFromLabel(value) {
            const semesterMatch = String(value || '').match(/\d+/u)
            const semester = Number(semesterMatch?.[0] || 0)

            return Number.isFinite(semester) && semester > 0 ? semester : null
        },
        studentSemester(student) {
            const schoolLevel = this.studentSchoolLevelKey(student)

            return this.studentSemesterBySchoolLevel()[schoolLevel] || null
        },
        studentSemesterBySchoolLevel() {
            return {
                '09_1': 1,
                '09_2': 2,
                '10_1': 3,
                '10_2': 4,
                '11_1': 5,
                '11_2': 6,
                '12_1': 7,
                '12_2': 8,
            }
        },
        semesterOptions() {
            return Array.from({ length: 8 }, (_, index) => {
                const semester = index + 1

                return {
                    title: `Semester ${semester}`,
                    value: semester,
                }
            })
        },
        studentSchoolLevelKey(student) {
            const importedSchoolLevel = this.normalizedStudentSchoolLevel(student?.school_level, student?.attendance_year)

            if (importedSchoolLevel) return importedSchoolLevel

            const schoolClass = String(student?.class || '').trim()
            const schoolLevelMatch = schoolClass.match(/^(\d+)[._-]?([12])?/u)
            if (!schoolLevelMatch) return ''

            return `${schoolLevelMatch[1].padStart(2, '0')}_${schoolLevelMatch[2] || '1'}`
        },
        normalizedStudentSchoolLevel(schoolLevel, attendanceYear) {
            const normalizedSchoolLevel = String(schoolLevel || '').trim()

            if (normalizedSchoolLevel) return normalizedSchoolLevel.replace('.', '_')

            const normalizedAttendanceYear = String(attendanceYear || '').trim()
            const attendanceYearMatch = normalizedAttendanceYear.match(/^(\d+)[._-]?([12])?$/u)

            if (!attendanceYearMatch) return ''

            return `${attendanceYearMatch[1].padStart(2, '0')}_${attendanceYearMatch[2] || '1'}`
        },
        normalizedStudentCode(value) {
            const studentCode = value === null || value === undefined ? '' : String(value).trim()

            return studentCode === '' ? null : studentCode
        },
        formatNumber(value) {
            return Number(value || 0).toLocaleString('de-AT')
        },
        formatHours(value) {
            const hours = Number(value || 0)

            if (!Number.isFinite(hours)) return '0'

            return Number.isInteger(hours) ? String(hours) : hours.toLocaleString('de-AT', { maximumFractionDigits: 2 })
        },
    },
}
</script>

<style scoped>
.students-timetable-v2-card {
    display: flex;
    flex: 1 1 auto;
    width: 100%;
    height: 100%;
    flex-direction: column;
    border: 1px solid rgba(37, 99, 235, 0.12);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
}

.students-timetable-v2-card :deep(.v-card-text) {
    flex: 1 1 auto;
}

.students-timetable-v2-card-column {
    display: flex !important;
    align-self: stretch;
    min-width: 0;
}

.students-timetable-v2-row-break {
    flex-basis: 100%;
    width: 0;
    height: 0;
    padding: 0;
}

.students-timetable-v2-without-student-background-column {
    display: none;
    min-width: 0;
    align-self: stretch;
    pointer-events: none;
    user-select: none;
}

.students-timetable-v2-without-student-watermark {
    display: grid;
    width: 100%;
    align-content: center;
    justify-items: center;
    gap: 10px;
    padding: 24px;
    color: rgba(15, 23, 42, 0.12);
    font-size: 2.8rem;
    font-weight: 900;
    line-height: 1.05;
    text-align: center;
}

.students-timetable-v2-without-student-watermark__icon {
    font-size: 6.5rem;
}

.students-timetable-v2-course-card-title {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-course-card-title__chips {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.students-timetable-v2-course-card-title__meta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
    margin-left: auto;
}

.students-timetable-v2-course-card-title__actions {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.students-timetable-v2-card--empty {
    min-height: 120px;
}

.students-timetable-v2-start-card__actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.students-timetable-v2-start-card__button {
    min-height: 96px;
    font-weight: 800;
}

.students-timetable-v2-restart-card__content {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: space-between;
}

.students-timetable-v2-restart-card__automatic-button {
    margin-left: auto;
}

.students-timetable-v2-restart-card__navigation-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-left: auto;
}

.students-timetable-v2-review-card__content {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    justify-content: space-between;
}

.students-timetable-v2-review-card__main {
    display: grid;
    min-width: 0;
    gap: 8px;
}

.students-timetable-v2-review-card__identity,
.students-timetable-v2-review-card__selection {
    display: inline-flex;
    align-items: center;
    min-width: 0;
    flex-wrap: wrap;
    gap: 8px;
}

.students-timetable-v2-review-card__selection-chip {
    border: 1px solid rgba(14, 165, 233, 0.28);
    background: #f0f9ff !important;
    color: #0f172a !important;
    font-weight: 700;
}

.students-timetable-v2-selected-courses-card__list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.students-timetable-v2-selected-courses-card__filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.students-timetable-v2-selected-courses-card__title {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-selected-courses-card__summary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-left: auto;
}

.students-timetable-v2-selected-courses-card__course {
    cursor: pointer;
}

.students-timetable-v2-selected-courses-card__course--passive {
    cursor: default;
}

.students-timetable-v2-selected-courses-card__course--active {
    box-shadow: 0 0 0 1px rgba(0, 137, 123, 0.26);
}

.students-timetable-v2-selected-courses-card__course--offered-deselected span {
    text-decoration: line-through;
}

.students-timetable-v2-selected-courses-card__meta {
    margin-left: 6px;
    font-weight: 800;
}

.students-timetable-v2-selected-courses-column {
    flex-direction: column;
    gap: 10px;
}

.students-timetable-v2-selected-courses-column > .students-timetable-v2-card {
    flex: 0 0 auto;
    height: auto;
}

.students-timetable-v2-selected-options-card {
    border: 1px solid rgba(22, 163, 74, 0.16);
}

.students-timetable-v2-selected-options-card__title {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-selected-options-card__list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.students-timetable-v2-offered-courses-card__title {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-offered-courses-card__list {
    display: grid;
    gap: 8px;
}

.students-timetable-v2-offered-courses-card__item {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    border: 1px solid rgba(71, 85, 105, 0.2);
    border-radius: 8px;
    padding: 8px 10px;
    background: #ffffff;
}

.students-timetable-v2-offered-courses-card__item--toggle {
    cursor: pointer;
}

.students-timetable-v2-offered-courses-card__item--selected {
    border-color: rgba(22, 163, 74, 0.36);
    background: rgba(220, 252, 231, 0.9);
    box-shadow: inset 0 0 0 1px rgba(22, 163, 74, 0.12);
}

.students-timetable-v2-offered-courses-card__item--deselected {
    border-color: rgba(71, 85, 105, 0.48);
    background: rgba(248, 250, 252, 0.92);
    color: #64748b;
}

.students-timetable-v2-offered-courses-card__item--deselected .students-timetable-v2-offered-courses-card__name {
    color: #64748b;
    text-decoration: line-through;
}

.students-timetable-v2-offered-courses-card__name {
    color: #0f172a;
    font-weight: 800;
}

.students-timetable-v2-offered-courses-card__code {
    color: rgba(15, 23, 42, 0.62);
    font-weight: 800;
}

.students-timetable-v2-calculation-card__success,
.students-timetable-v2-calculation-card__summary {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.students-timetable-v2-calculation-card__title {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-calculation-card__heading {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 32px;
}

.students-timetable-v2-calculation-card__availability-loader {
    flex: 0 0 auto;
}

.students-timetable-v2-calculation-card__actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    flex: 1 1 auto;
    align-items: center;
}

.students-timetable-v2-calculation-card__more-course-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-calculation-card__primary-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-calculation-card__reset-button {
    margin-left: auto;
}

.students-timetable-v2-options-card {
    border: 1px solid rgba(71, 85, 105, 0.16);
}

.students-timetable-v2-options-card__title {
    font-size: 0.95rem;
    font-weight: 900;
}

.students-timetable-v2-options-card__list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.students-timetable-v2-options-card__option {
    border: 1px solid rgba(14, 165, 233, 0.18);
    flex: 0 1 220px;
    cursor: pointer;
    max-width: 220px;
}

.students-timetable-v2-options-card__option--selected {
    border-color: rgba(22, 163, 74, 0.35);
    background: rgba(220, 252, 231, 0.75);
}

.students-timetable-v2-options-card__option--selected .students-timetable-v2-options-card__option-title {
    color: #15803d;
}

.students-timetable-v2-options-card__option[disabled] {
    cursor: not-allowed;
}

.students-timetable-v2-options-card__option-title {
    color: #0369a1;
    min-height: 0;
    padding: 8px 10px 2px;
    font-size: 0.78rem;
    font-weight: 900;
    line-height: 1.1;
}

.students-timetable-v2-options-card__option-content {
    padding: 0 10px 8px;
}

.students-timetable-v2-options-card__option-count {
    color: #0f172a;
    font-size: 0.95rem;
    font-weight: 900;
    line-height: 1.15;
}

.students-timetable-v2-more-courses-card {
    border: 1px solid rgba(37, 99, 235, 0.14);
}

.students-timetable-v2-more-courses-card__title {
    font-size: 0.95rem;
    font-weight: 900;
}

.students-timetable-v2-more-courses-card__list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.students-timetable-v2-more-courses-card__course {
    cursor: pointer;
    max-width: 100%;
}

.students-timetable-v2-more-courses-card__course--unavailable {
    cursor: not-allowed;
    opacity: 0.78;
}

.students-timetable-v2-more-courses-card__meta,
.students-timetable-v2-more-courses-card__group {
    margin-left: 6px;
    font-weight: 800;
}

.students-timetable-v2-more-courses-card__group {
    opacity: 0.72;
}

.students-timetable-v2-more-courses-offered-card {
    border: 1px solid rgba(37, 99, 235, 0.14);
}

.students-timetable-v2-result {
    display: grid;
    gap: 10px;
    margin-top: 12px;
}

.students-timetable-v2-result__header {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: space-between;
}

.students-timetable-v2-result__title,
.students-timetable-v2-result__meta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    font-weight: 800;
}

.students-timetable-v2-result__number-input {
    flex: 0 0 112px;
    width: 112px;
}

.students-timetable-v2-result__number-input :deep(.v-field) {
    min-height: 40px;
}

.students-timetable-v2-result__number-input :deep(.v-field__field) {
    align-items: center;
    min-height: 40px;
}

.students-timetable-v2-result__number-input :deep(.v-field__input) {
    align-items: center;
    flex-wrap: nowrap;
    line-height: 1.25rem;
    min-height: 40px;
    padding-top: 0;
    padding-bottom: 0;
}

.students-timetable-v2-result__number-input :deep(.v-text-field__prefix) {
    align-items: center;
    line-height: 1.25rem;
    min-height: 40px;
    padding-top: 0;
    padding-bottom: 0;
}

.students-timetable-v2-result__number-input :deep(input) {
    text-align: center;
    font-size: 0.95rem;
    font-weight: 800;
    line-height: 1.25rem;
}

.students-timetable-v2-result-conflicts {
    display: grid;
    gap: 6px;
    border-radius: 8px;
    padding: 9px 10px;
}

.students-timetable-v2-result-conflicts--error {
    border: 1px solid rgba(220, 38, 38, 0.18);
    background: rgba(254, 226, 226, 0.96);
    color: #7f1d1d;
}

.students-timetable-v2-result-conflicts--warning {
    border: 1px solid rgba(217, 119, 6, 0.22);
    background: rgba(254, 243, 199, 0.96);
    color: #78350f;
}

.students-timetable-v2-result-conflicts__title {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
    font-weight: 900;
}

.students-timetable-v2-result-conflicts__list {
    display: grid;
    gap: 4px;
    margin: 0;
    padding-left: 18px;
    font-size: 0.76rem;
    font-weight: 700;
    line-height: 1.3;
}

.students-timetable-v2-result-conflicts__actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-result-grid {
    display: grid;
    grid-template-columns: 58px repeat(var(--students-timetable-v2-result-weekdays, 5), minmax(96px, 1fr));
    overflow-x: auto;
    border: 1px solid rgba(14, 165, 233, 0.24);
    border-radius: 8px;
    background: #e2e8f0;
    gap: 1px;
}

.students-timetable-v2-result-grid__cell {
    min-height: 64px;
    min-width: 0;
    background: rgba(255, 255, 255, 0.96);
    padding: 7px;
}

.students-timetable-v2-result-grid__cell--header {
    min-height: 34px;
    background: #f0f9ff;
    color: #075985;
    font-size: 0.78rem;
    font-weight: 900;
    text-align: center;
}

.students-timetable-v2-result-grid__cell--time {
    display: grid;
    align-content: center;
    justify-items: center;
    min-height: 64px;
    background: #f8fafc;
    color: #334155;
    font-size: 0.72rem;
    font-weight: 800;
    line-height: 1.15;
    text-align: center;
}

.students-timetable-v2-result-grid__cell--filled {
    background: rgba(220, 252, 231, 0.94);
}

.students-timetable-v2-result-grid__cell--additional {
    background: rgba(219, 234, 254, 0.94);
}

.students-timetable-v2-result-grid__cell--conflict {
    background: rgba(254, 226, 226, 0.96);
}

.students-timetable-v2-result-grid__content {
    position: relative;
    display: grid;
    gap: 3px;
    min-width: 0;
}

.students-timetable-v2-result-grid__content--has-overlap-chip {
    padding-top: 20px;
}

.students-timetable-v2-result-grid__overlap-chips {
    position: absolute;
    top: 0;
    right: 0;
    display: flex;
    justify-content: flex-end;
    gap: 4px;
    max-width: 100%;
    pointer-events: none;
}

.students-timetable-v2-result-grid__overlap-chip {
    max-width: 100%;
    font-weight: 800;
}

.students-timetable-v2-result-grid__overlap-chip :deep(.v-chip__content) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.students-timetable-v2-result-grid__code {
    display: flex;
    align-items: baseline;
    gap: 4px;
    flex-wrap: wrap;
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 900;
    line-height: 1.15;
}

.students-timetable-v2-result-grid__details,
.students-timetable-v2-result-grid__date,
.students-timetable-v2-result-grid__recurrence,
.students-timetable-v2-result-grid__same-slot {
    color: #334155;
    font-size: 0.7rem;
    font-weight: 700;
    line-height: 1.2;
}

.students-timetable-v2-result-grid__recurrence {
    color: #0f766e;
}

.students-timetable-v2-result-grid__date {
    color: #0369a1;
}

.students-timetable-v2-result-grid__same-slots {
    display: grid;
    gap: 3px;
    margin-top: 2px;
}

.students-timetable-v2-result-grid__same-slot {
    border-top: 1px solid rgba(14, 165, 233, 0.18);
    padding-top: 3px;
}

.students-timetable-v2-result-grid__conflicts {
    display: grid;
    gap: 2px;
    margin-top: 4px;
    border-top: 1px solid rgba(220, 38, 38, 0.22);
    padding-top: 4px;
    color: #991b1b;
    font-size: 0.66rem;
    font-weight: 800;
    line-height: 1.18;
}

.students-timetable-v2-result-grid__conflict-title {
    color: #7f1d1d;
    font-weight: 900;
}

.students-timetable-v2-result-grid__badge {
    border-radius: 999px;
    padding: 1px 4px;
    background: rgba(245, 158, 11, 0.18);
    color: #92400e;
    font-size: 0.56rem;
    font-weight: 900;
    line-height: 1.1;
}

.students-timetable-v2-result-grid__badge--additional {
    background: rgba(37, 99, 235, 0.16);
    color: #1d4ed8;
}

.students-timetable-v2-result-grid__hour {
    font-size: 0.86rem;
}

.students-timetable-v2-result-grid__time {
    display: block;
}

.students-timetable-v2-student-context {
    display: grid;
    gap: 10px;
    border: 1px solid rgba(14, 165, 233, 0.22);
    border-radius: 8px;
    padding: 10px;
    background: #f0f9ff;
}

.students-timetable-v2-student-context__title {
    display: flex;
    align-items: center;
    min-width: 0;
    flex-wrap: wrap;
    gap: 8px;
    color: #0f172a;
    font-weight: 800;
}

.students-timetable-v2-student-context__student-label {
    font-size: 1.08rem;
    line-height: 1.3;
}

.students-timetable-v2-student-actions {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 6px;
}

.students-timetable-v2-completed-courses {
    display: grid;
    gap: 8px;
    margin-top: 12px;
}

.students-timetable-v2-completed-courses__title {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #0f172a;
    font-size: 0.92rem;
    font-weight: 800;
}

.students-timetable-v2-completed-courses__list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.students-timetable-v2-completed-courses__item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(14, 165, 233, 0.18);
    border-radius: 8px;
    padding: 5px 7px;
    background: rgba(248, 250, 252, 0.88);
    color: #0f172a;
    font-size: 0.78rem;
    font-weight: 700;
}

.students-timetable-v2-completed-courses__item--missing {
    border-color: rgba(220, 38, 38, 0.18);
    background: rgba(254, 242, 242, 0.78);
}

.students-timetable-v2-completed-courses__item--additional {
    border-color: rgba(2, 136, 209, 0.18);
    background: rgba(240, 249, 255, 0.78);
}

.students-timetable-v2-completed-courses__item--toggle {
    cursor: pointer;
}

.students-timetable-v2-completed-courses__item--deselected {
    border-color: rgba(71, 85, 105, 0.52);
    background: rgba(248, 250, 252, 0.96);
    box-shadow: inset 0 0 0 1px rgba(71, 85, 105, 0.12);
    color: #334155;
}

.students-timetable-v2-completed-courses__item--deselected span:first-child {
    text-decoration: line-through;
}

.students-timetable-v2-completed-courses__item-meta {
    border-radius: 999px;
    padding: 1px 6px;
    background: rgba(255, 255, 255, 0.72);
    font-size: 0.7rem;
    font-weight: 800;
}

.students-timetable-v2-completed-courses__loading,
.students-timetable-v2-completed-courses__alert {
    margin-top: 2px;
}

.students-timetable-v2-student-dialog__meta {
    margin-bottom: 10px;
    color: rgba(var(--v-theme-on-surface), 0.68);
    font-size: 0.78rem;
    font-weight: 700;
}

.students-timetable-v2-student-search-results {
    display: grid;
    gap: 6px;
    max-height: 260px;
    margin-top: 10px;
    overflow-y: auto;
}

.students-timetable-v2-student-search-results__item {
    justify-content: flex-start;
    min-height: 32px;
}

.students-timetable-v2-student-search-results__empty {
    padding: 8px 2px;
    color: rgba(var(--v-theme-on-surface), 0.58);
    font-size: 0.78rem;
}

.students-timetable-v2-selection {
    display: grid;
    gap: 10px;
}

.students-timetable-v2-selection__item {
    display: grid;
    gap: 2px;
}

.students-timetable-v2-selection__item span {
    color: rgba(0, 0, 0, 0.6);
    font-size: 0.78rem;
}

.students-timetable-v2-selection__item strong {
    font-size: 0.92rem;
    font-weight: 600;
}

.students-timetable-v2-selection__value {
    width: fit-content;
}

.students-timetable-v2-selection__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.students-timetable-v2-selection__chip {
    cursor: pointer;
    font-weight: 700;
}

.students-timetable-v2-selection__value--unknown {
    border: 1px solid rgba(100, 116, 139, 0.24);
    border-radius: 999px;
    padding: 1px 8px 2px;
    background: rgba(148, 163, 184, 0.14);
    color: rgba(71, 85, 105, 0.72);
    font-size: 0.82rem;
    line-height: 1.2;
    letter-spacing: 0;
}

.students-timetable-v2-selection__empty {
    color: rgba(0, 0, 0, 0.58);
    font-size: 0.86rem;
    font-weight: 600;
}

@media (max-width: 640px) {
    .students-timetable-v2-start-card__actions {
        grid-template-columns: 1fr;
    }
}

@media (min-width: 960px) {
    .students-timetable-v2-without-student-background-column {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 170px;
    }
}
</style>
