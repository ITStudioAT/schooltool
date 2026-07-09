<template>
    <div
        class="students-timetable-v2-page"
        :class="{
            'students-timetable-v2-page--actions-disabled': timetableV2PageActionsDisabled,
        }"
        :aria-busy="timetableV2PageActionsDisabled ? 'true' : 'false'"
        :inert="timetableV2PageActionsDisabled ? '' : null">
        <div
            v-if="initialRouteLoading"
            class="students-timetable-v2-initial-loader"
            role="status"
            aria-live="polite">
            <v-progress-linear
                indeterminate
                color="primary"
                rounded
                height="5"
                class="students-timetable-v2-initial-loader__bar" />
            <span>Stundenplan wird geladen</span><span class="students-timetable-v2-loading-dots" aria-hidden="true"></span>
        </div>

        <v-row v-else-if="reviewFlowVisible" dense align="stretch">
            <v-col v-if="timetableV2StepperVisible" cols="12" class="students-timetable-v2-card-column">
                <v-stepper
                    :model-value="timetableV2StepNumber"
                    :items="timetableV2StepperItems"
                    hide-actions
                    flat
                    color="primary"
                    class="students-timetable-v2-stepper" />
            </v-col>

            <v-col cols="12" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-review-card">
                    <v-card-text class="students-timetable-v2-review-card__content">
                        <div class="students-timetable-v2-review-card__main">
                            <div class="students-timetable-v2-review-card__identity">
                                <v-icon :icon="reviewFlowStudentIcon" size="20" color="primary" />
                                <strong>{{ courseReviewStudentLabel }}</strong>
                                <span v-if="courseReviewStudentReligionMeta" class="students-timetable-v2-review-card__student-religion">
                                    {{ courseReviewStudentReligionMeta }}
                                </span>
                                <button
                                    v-if="courseReviewStudentEmail"
                                    type="button"
                                    class="students-timetable-v2-review-card__student-email"
                                    :class="{ 'students-timetable-v2-review-card__student-email--copied': copiedStudentEmailCode === courseReviewStudentCode }"
                                    :aria-label="copiedStudentEmailCode === courseReviewStudentCode ? `E-Mail-Adresse kopiert: ${courseReviewStudentEmail}` : `E-Mail-Adresse kopieren: ${courseReviewStudentEmail}`"
                                    :title="copiedStudentEmailCode === courseReviewStudentCode ? 'Kopiert!' : `E-Mail-Adresse kopieren: ${courseReviewStudentEmail}`"
                                    @click.stop="copyCourseReviewStudentEmail">
                                    <v-icon icon="mdi-email-outline" size="14" />
                                    <span>{{ courseReviewStudentEmail }}</span>
                                    <v-icon
                                        :icon="copiedStudentEmailCode === courseReviewStudentCode ? 'mdi-check' : 'mdi-content-copy'"
                                        size="13" />
                                    <span
                                        v-if="copiedStudentEmailCode === courseReviewStudentCode"
                                        class="students-timetable-v2-review-card__student-email-copied">
                                        Kopiert
                                    </span>
                                </button>
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

            <v-col
                v-if="offerChoiceModuleItems.length"
                cols="12"
                class="students-timetable-v2-card-column">
                <v-card
                    rounded="lg"
                    class="students-timetable-v2-card students-timetable-v2-offerchoices">
                    <v-card-title class="students-timetable-v2-offerchoices__title">
                        Gewählte Module
                    </v-card-title>
                    <v-card-text class="students-timetable-v2-offerchoices__content">
                        <div class="students-timetable-v2-offerchoices__modules">
                            <v-chip
                                v-for="module in offerChoiceModuleItems"
                                :key="module.key"
                                size="large"
                                color="primary"
                                variant="tonal"
                                :closable="selectedCourseItemsDeletable"
                                :disabled="selectedCourseItemsDisabled"
                                :close-label="`Modul ${module.label} entfernen`"
                                close-icon="mdi-close"
                                class="students-timetable-v2-offerchoices__module"
                                @click:close.stop="removeSelectedCourseItem(module.course)">
                                <span>{{ module.label }}</span>
                                <span
                                    v-if="!adoptedTimetableVisible"
                                    class="students-timetable-v2-offerchoices__meta">
                                    {{ module.countLabel }}
                                </span>
                            </v-chip>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="courseReviewVisible" cols="12" class="students-timetable-v2-card-column students-timetable-v2-selected-courses-column">
                <v-card
                    rounded="lg"
                    class="students-timetable-v2-card students-timetable-v2-selected-offers-card">
                    <v-card-title class="students-timetable-v2-selected-offers-card__title">
                        <span>Ausgewählte Angebote</span>
                    </v-card-title>
                    <v-card-text class="students-timetable-v2-selected-offers-card__content">
                        <div v-if="selectedAdaptedOfferedCourseGroups.length" class="students-timetable-v2-selected-offers-card__groups">
                            <v-card
                                v-for="group in selectedAdaptedOfferedCourseGroups"
                                :key="group.key"
                                rounded="lg"
                                variant="tonal"
                                class="students-timetable-v2-selected-offers-card__group">
                                <v-card-title class="students-timetable-v2-selected-offers-card__group-title">
                                    <span>{{ group.label }}</span>
                                    <v-chip size="x-small" color="primary" variant="tonal">
                                        {{ group.offers.length }}
                                    </v-chip>
                                    <span class="students-timetable-v2-selected-offers-card__group-actions">
                                        <v-btn
                                            icon="mdi-check-all"
                                            size="x-small"
                                            color="success"
                                            variant="tonal"
                                            title="Alle Angebote auswählen"
                                            aria-label="Alle Angebote auswählen"
                                            @click.stop="selectAdaptedOfferedCourseGroupOffers(group, true)" />
                                        <v-btn
                                            icon="mdi-close-circle-outline"
                                            size="x-small"
                                            color="error"
                                            variant="tonal"
                                            title="Alle Angebote abwählen"
                                            aria-label="Alle Angebote abwählen"
                                            @click.stop="selectAdaptedOfferedCourseGroupOffers(group, false)" />
                                    </span>
                                </v-card-title>
                                <v-card-text class="students-timetable-v2-selected-offers-card__group-content">
                                    <div class="students-timetable-v2-selected-offers-card__list">
                                        <div
                                            v-for="offer in group.offers"
                                            :key="offer.key"
                                            class="students-timetable-v2-selected-offers-card__item"
                                            :class="{
                                                'students-timetable-v2-selected-offers-card__item--selected': offer.selected,
                                                'students-timetable-v2-selected-offers-card__item--deselected': !offer.selected,
                                            }"
                                            role="button"
                                            tabindex="0"
                                            :aria-pressed="offer.selected ? 'true' : 'false'"
                                            @click="toggleOfferedCourseItem(offer)"
                                            @keydown.enter.prevent="toggleOfferedCourseItem(offer)"
                                            @keydown.space.prevent="toggleOfferedCourseItem(offer)">
                                            <v-icon
                                                :icon="offer.selected ? 'mdi-check-circle-outline' : 'mdi-checkbox-blank-circle-outline'"
                                                size="18"
                                                class="students-timetable-v2-selected-offers-card__icon" />
                                            <div class="students-timetable-v2-selected-offers-card__row">
                                                <strong class="students-timetable-v2-selected-offers-card__label">
                                                    {{ offer.label }}
                                                </strong>
                                                <v-chip
                                                    v-if="offer.courseLabel"
                                                    size="x-small"
                                                    color="success"
                                                    variant="tonal"
                                                    class="students-timetable-v2-selected-offers-card__course">
                                                    {{ offer.courseLabel }}
                                                </v-chip>
                                                <v-chip
                                                    v-if="offer.instructionLabel"
                                                    size="x-small"
                                                    color="warning"
                                                    variant="tonal">
                                                    {{ offer.instructionLabel }}
                                                </v-chip>
                                                <span v-if="offer.scheduleLabel" class="students-timetable-v2-selected-offers-card__schedule">
                                                    {{ offer.scheduleLabel }}
                                                </span>
                                                <span v-if="offer.roomsLabel" class="students-timetable-v2-selected-offers-card__rooms">
                                                    {{ offer.roomsLabel }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </div>
                        <v-alert v-else type="info" variant="tonal" density="compact">
                            Keine Angebote ausgewählt.
                        </v-alert>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col
                v-if="adoptedTimetableVisible && !adoptedTimetableCourseRemovalPendingVisible"
                cols="12"
                class="students-timetable-v2-card-column students-timetable-v2-selected-courses-column">
                <v-card
                    rounded="lg"
                    class="students-timetable-v2-card students-timetable-v2-more-adopted-courses-card">
                    <v-card-title class="students-timetable-v2-more-adopted-courses-card__title">
                        {{ moreAdoptedCoursesTitle }}
                    </v-card-title>
                    <v-card-text class="students-timetable-v2-more-adopted-courses-card__content">
                        <div v-if="!activeMoreAdoptedCourseCard" class="students-timetable-v2-more-adopted-courses-card__grid">
                            <v-card
                                v-for="card in moreAdoptedCourseCards"
                                :key="card.key"
                                rounded="lg"
                                variant="tonal"
                                class="students-timetable-v2-more-adopted-courses-card__category"
                                :class="[
                                    `students-timetable-v2-more-adopted-courses-card__category--${card.key}`,
                                    {
                                        'students-timetable-v2-more-adopted-courses-card__category--disabled': moreAdoptedCourseCardDisabled(card),
                                    },
                                ]"
                                :disabled="moreAdoptedCourseCardDisabled(card)"
                                :ripple="!moreAdoptedCourseCardDisabled(card)"
                                :role="moreAdoptedCourseCardDisabled(card) ? undefined : 'button'"
                                :tabindex="moreAdoptedCourseCardDisabled(card) ? -1 : 0"
                                :aria-disabled="moreAdoptedCourseCardDisabled(card) ? 'true' : 'false'"
                                @click="openMoreAdoptedCourseCard(card)"
                                @keydown.enter.prevent="openMoreAdoptedCourseCard(card)"
                                @keydown.space.prevent="openMoreAdoptedCourseCard(card)">
                                <div class="students-timetable-v2-more-adopted-courses-card__category-title">
                                    <span>{{ card.title }}</span>
                                </div>
                            </v-card>
                        </div>
                        <div v-else class="students-timetable-v2-more-adopted-courses-card__course-view">
                            <section
                                class="students-timetable-v2-more-adopted-courses-card__back-card"
                                role="button"
                                tabindex="0"
                                @click="closeMoreAdoptedCourseCard"
                                @keydown.enter.prevent="closeMoreAdoptedCourseCard"
                                @keydown.space.prevent="closeMoreAdoptedCourseCard">
                                <v-icon icon="mdi-arrow-left" size="18" />
                                <span>Zurück</span>
                            </section>
                            <div v-if="moreAdoptedCourseItems.length" class="students-timetable-v2-more-adopted-courses-card__course-list">
                                <v-card
                                    v-for="course in moreAdoptedCourseItems"
                                    :key="course.selectionKey"
                                    rounded="lg"
                                    variant="tonal"
                                    :color="moreAdoptedCourseColor(course)"
                                    class="students-timetable-v2-more-adopted-courses-card__course"
                                    :class="[
                                        `students-timetable-v2-more-adopted-courses-card__course--${moreAdoptedCourseColor(course)}`,
                                        { 'students-timetable-v2-more-adopted-courses-card__course--active': moreAdoptedCourseItemActive(course) },
                                    ]"
                                    role="button"
                                    tabindex="0"
                                    :aria-expanded="moreAdoptedCourseItemActive(course) ? 'true' : 'false'"
                                    @click="openMoreAdoptedCourseItem(course)"
                                    @keydown.enter.prevent="openMoreAdoptedCourseItem(course)"
                                    @keydown.space.prevent="openMoreAdoptedCourseItem(course)">
                                    <span class="students-timetable-v2-more-adopted-courses-card__course-label">
                                        {{ course.label }}
                                    </span>
                                    <v-chip v-if="course.meta" size="x-small" :color="moreAdoptedCourseColor(course)" variant="tonal">
                                        {{ course.meta }}
                                    </v-chip>
                                </v-card>
                            </div>
                            <v-alert v-else type="info" variant="tonal" density="compact">
                                Keine Module verfügbar.
                            </v-alert>
                            <v-card
                                v-if="selectedMoreAdoptedCourseItem"
                                rounded="lg"
                                variant="tonal"
                                class="students-timetable-v2-more-adopted-course-offers-card students-timetable-v2-offered-courses-card">
                                <v-card-title class="students-timetable-v2-offered-courses-card__title">
                                    Angebotene Kurse zum Modul {{ selectedMoreAdoptedCourseItem.label }}
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
                                    <div v-else-if="selectedMoreAdoptedCourseOfferedCourseItems.length" class="students-timetable-v2-offered-courses-card__list">
                                        <div
                                            v-for="course in selectedMoreAdoptedCourseOfferedCourseItems"
                                            :key="course.key"
                                            class="students-timetable-v2-offered-courses-card__item students-timetable-v2-offered-courses-card__item--toggle"
                                            :class="{
                                                'students-timetable-v2-offered-courses-card__item--available': !moreAdoptedCourseOfferCollides(course),
                                                'students-timetable-v2-offered-courses-card__item--colliding': moreAdoptedCourseOfferCollides(course),
                                            }"
                                            role="button"
                                            tabindex="0"
                                            :title="moreAdoptedCourseOfferCollides(course) ? 'Überschneidet sich mit dem aktuellen Stundenplan.' : undefined"
                                            @click="insertMoreAdoptedCourseOfferIntoTimetable(course)"
                                            @keydown.enter.prevent="insertMoreAdoptedCourseOfferIntoTimetable(course)"
                                            @keydown.space.prevent="insertMoreAdoptedCourseOfferIntoTimetable(course)">
                                            <span class="students-timetable-v2-offered-courses-card__name">
                                                {{ course.name || course.code }}
                                            </span>
                                            <span v-if="offeredCourseCodeVisible(course)" class="students-timetable-v2-offered-courses-card__code">
                                                {{ course.code }}
                                            </span>
                                            <v-chip v-if="course.scheduleLabel" size="x-small" color="primary" variant="tonal">
                                                {{ course.scheduleLabel }}
                                            </v-chip>
                                            <v-chip
                                                v-if="offeredCourseInstructionVisible(course)"
                                                size="x-small"
                                                color="warning"
                                                variant="tonal"
                                                :title="offeredCourseInstructionLabel(course)">
                                                {{ offeredCourseInstructionLabel(course) }}
                                            </v-chip>
                                            <v-chip v-if="course.roomsLabel" size="x-small" color="secondary" variant="outlined">
                                                {{ course.roomsLabel }}
                                            </v-chip>
                                        </div>
                                    </div>
                                    <v-alert v-else type="info" variant="tonal" density="compact">
                                        Keine angebotenen Module gefunden.
                                    </v-alert>
                                </v-card-text>
                            </v-card>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="selectedCourseOfferCardVisible" cols="12" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-offered-courses-card">
                    <v-card-title class="students-timetable-v2-offered-courses-card__title">
                        Angebotene Kurse zum Modul {{ selectedReviewCourseItem.label }}
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
                                class="students-timetable-v2-offered-courses-card__item"
                                :class="course.classes"
                                :role="selectedCourseOfferItemsSelectable ? 'button' : undefined"
                                :tabindex="selectedCourseOfferItemsSelectable ? 0 : undefined"
                                :aria-pressed="course.ariaPressed"
                                :title="course.title"
                                @click="toggleSelectedReviewOfferedCourseItem(course)"
                                @keydown.enter.prevent="toggleSelectedReviewOfferedCourseItem(course)"
                                @keydown.space.prevent="toggleSelectedReviewOfferedCourseItem(course)">
                                <v-icon v-if="course.selected" icon="mdi-check" size="16" color="success" />
                                <span class="students-timetable-v2-offered-courses-card__name">
                                    {{ course.name || course.code }}
                                </span>
                                <span v-if="offeredCourseCodeVisible(course)" class="students-timetable-v2-offered-courses-card__code">
                                    {{ course.code }}
                                </span>
                                <v-chip v-if="course.scheduleLabel" size="x-small" color="primary" variant="tonal">
                                    {{ course.scheduleLabel }}
                                </v-chip>
                                <v-chip
                                    v-if="course.instructionVisible"
                                    size="x-small"
                                    color="warning"
                                    variant="tonal"
                                    :title="course.instructionLabel">
                                    {{ course.instructionLabel }}
                                </v-chip>
                                <v-chip v-if="course.roomsLabel" size="x-small" color="secondary" variant="outlined">
                                    {{ course.roomsLabel }}
                                </v-chip>
                            </div>
                        </div>
                        <v-alert v-else type="info" variant="tonal" density="compact">
                            Keine angebotenen Module gefunden.
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
                                :disabled="timetableV2PageLoading"
                                :loading="timetableV2PageLoading"
                                @click="openTimetableCalculation">
                                Weiter
                            </v-btn>
                        </div>
                        <v-progress-linear
                            v-if="timetableV2PageLoading"
                            indeterminate
                            rounded
                            color="primary"
                            height="4"
                            class="students-timetable-v2-restart-card__loading"
                            title="Daten werden geladen"
                            aria-label="Daten werden geladen" />
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="timetableCalculationVisible || adoptedTimetableVisible" cols="12" class="students-timetable-v2-card-column">
                <v-card
                    rounded="lg"
                    class="students-timetable-v2-card students-timetable-v2-calculation-card"
                    :class="{ 'students-timetable-v2-calculation-card--final': adoptedTimetableVisible }">
                    <v-card-title class="students-timetable-v2-calculation-card__title">
                        <span class="students-timetable-v2-calculation-card__heading">
                            <span>{{ timetableCalculationCardTitleLabel }}</span>
                            <span
                                v-if="timetableCalculationProgressVisible"
                                class="students-timetable-v2-calculation-card__title-progress">
                                <v-progress-linear
                                    :model-value="timetableCalculationProgressValue"
                                    max="100"
                                    color="primary"
                                    height="18"
                                    rounded
                                    striped
                                    class="students-timetable-v2-completed-courses__loading">
                                    {{ timetableCalculationProgressLabel }}
                                </v-progress-linear>
                            </span>
                        </span>
                        <span v-if="timetableCalculationVisible && timetableOptionsCardVisible" class="students-timetable-v2-calculation-card__actions">
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
                        <span
                            v-else-if="timetableCalculationVisible && moreCoursesVisible && !moreCoursesCardVisible"
                            class="students-timetable-v2-calculation-card__more-course-actions">
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
                                color="error"
                                variant="flat"
                                prepend-icon="mdi-delete"
                                :loading="timetableCalculationLoading"
                                :disabled="!moreCoursesSelectionChanged || timetableCalculationLoading"
                                @click="applyMoreCoursesSelection">
                                Löschen
                            </v-btn>
                        </span>
                        <span v-else-if="timetableCalculationVisible && !moreCoursesCardVisible" class="students-timetable-v2-calculation-card__actions">
                            <span class="students-timetable-v2-calculation-card__primary-actions">
                                <v-btn
                                    size="large"
                                    :color="moreCoursesButtonUnavailable ? 'error' : 'primary'"
                                    variant="tonal"
                                    prepend-icon="mdi-plus-circle-outline"
                                    :disabled="timetableCalculationLoading || moreCourseAvailabilityLoading"
                                    @click="toggleMoreCoursesCard">
                                    Mehr Module
                                </v-btn>
                                <v-btn
                                    size="large"
                                    :color="timetableOptionsButtonUnavailable ? 'error' : 'info'"
                                    variant="tonal"
                                    prepend-icon="mdi-cog-outline"
                                    :disabled="timetableCalculationLoading || timetableQualityCountersLoading || moreCourseAvailabilityLoading || timetableOptionsButtonUnavailable"
                                    :aria-expanded="timetableOptionsCardVisible ? 'true' : 'false'"
                                    @click="toggleTimetableOptionsCard">
                                    Optionen
                                </v-btn>
                            </span>
                            <v-btn
                                v-if="timetableCalculationResetAvailable"
                                size="large"
                                color="warning"
                                variant="tonal"
                                prepend-icon="mdi-restore"
                                class="students-timetable-v2-calculation-card__reset-button"
                                :disabled="timetableCalculationLoading"
                                @click="resetTimetableCalculationChanges">
                                Zurücksetzen
                            </v-btn>
                            <v-btn
                                size="large"
                                color="success"
                                variant="flat"
                                prepend-icon="mdi-calendar-check-outline"
                                :disabled="timetableCalculationLoading || !selectedTimetableV2Result"
                                @click="adoptCurrentTimetableV2Result">
                                {{ adoptSelectedTimetableV2ButtonLabel }}
                            </v-btn>
                        </span>
                        <span v-else-if="timetableCalculationVisible" class="students-timetable-v2-calculation-card__more-course-actions">
                            <v-btn
                                size="large"
                                color="warning"
                                variant="tonal"
                                prepend-icon="mdi-close"
                                @click="cancelMoreCoursesCard">
                                Abbruch
                            </v-btn>
                        </span>
                        <span
                            v-else-if="adoptedTimetableCourseRemovalPendingVisible"
                            class="students-timetable-v2-calculation-card__more-course-actions">
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
                                color="error"
                                variant="flat"
                                prepend-icon="mdi-delete"
                                :disabled="!moreCoursesSelectionChanged"
                                @click="applyAdoptedTimetableCourseRemoval">
                                Löschen
                            </v-btn>
                        </span>
                    </v-card-title>
                    <v-card-text class="students-timetable-v2-calculation-card__content">
                        <v-alert
                            v-if="adoptedPublishedTimetableReport.message"
                            class="students-timetable-v2-published-timetable-report"
                            :type="adoptedPublishedTimetableReport.type"
                            variant="tonal"
                            closable
                            density="compact"
                            @click:close="clearAdoptedPublishedTimetableReport">
                            {{ adoptedPublishedTimetableReport.message }}
                        </v-alert>
                        <v-progress-linear
                            v-if="(timetableV2PageLoading || timetableCalculationLoading) && !timetableCalculationProgressVisible"
                            indeterminate
                            color="primary"
                            class="students-timetable-v2-completed-courses__loading" />
                        <v-alert
                            v-if="timetableV2PageLoading"
                            type="info"
                            variant="tonal"
                            density="compact"
                            icon="mdi-dots-horizontal-circle-outline">
                            <span>Daten werden geladen</span><span class="students-timetable-v2-loading-dots" aria-hidden="true"></span>
                        </v-alert>
                        <v-alert
                            v-else-if="timetableCalculationLoading"
                            type="info"
                            variant="tonal"
                            density="compact"
                            :icon="timetableCalculationLoadingIcon">
                            {{ timetableCalculationLoadingLabel }}
                        </v-alert>
                        <v-alert
                            v-else-if="timetableCalculationError"
                            type="error"
                            variant="tonal"
                            density="compact">
                            {{ timetableCalculationError }}
                        </v-alert>
                        <v-alert
                            v-else-if="timetableCalculationVisible && timetableCalculationResult && !moreCoursesVisible && !timetableOptionsCardVisible"
                            :type="timetableCalculationResultAlertType"
                            variant="tonal"
                            density="compact"
                            :icon="timetableCalculationResultAlertIcon">
                            <div class="students-timetable-v2-calculation-card__success">
                                <span>{{ timetableCalculationResultStatusLabel }}</span>
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
                                <span class="students-timetable-v2-calculation-card__timetable-selector students-timetable-v2-result__meta">
                                    <v-btn
                                        icon="mdi-chevron-left"
                                        size="small"
                                        variant="tonal"
                                        color="primary"
                                        :disabled="!selectedTimetableV2PreviousAvailable || timetablePendingActionConfirmationVisible"
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
                                        :disabled="!selectedTimetableV2NextAvailable || timetablePendingActionConfirmationVisible"
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
                                        :disabled="timetableCalculationLoading || timetablePendingActionConfirmationVisible"
                                        class="students-timetable-v2-result__number-input"
                                        aria-label="Stundenplan Nummer"
                                        @update:model-value="updateTimetableV2NumberDraft"
                                        @keydown.enter.prevent="commitSelectedTimetableV2Number"
                                        @blur="commitSelectedTimetableV2Number" />
                                </span>
                            </div>
                        </v-alert>
                        <v-alert
                            v-else-if="adoptedTimetableVisible && !adoptedTimetableCalculationResult"
                            type="info"
                            variant="tonal"
                            density="compact"
                            icon="mdi-calendar-alert-outline">
                            Es wurde noch kein Stundenplan übernommen.
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
                                        :ripple="!timetableOptionUnavailable('no-saturday')"
                                        :class="[
                                            'students-timetable-v2-options-card__option',
                                            {
                                                'students-timetable-v2-options-card__option--selected': timetableNoSaturdayDraftSelected,
                                                'students-timetable-v2-options-card__option--unavailable': timetableOptionUnavailable('no-saturday'),
                                                'students-timetable-v2-options-card__option--loading': timetableCalculationLoading || timetableQualityCountersLoading,
                                            },
                                        ]"
                                        role="button"
                                        :tabindex="timetableOptionUnavailable('no-saturday') ? -1 : 0"
                                        :aria-disabled="timetableOptionUnavailable('no-saturday') ? 'true' : 'false'"
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
                                        v-if="!timetableStartsFromPeriod10Selected"
                                        rounded="lg"
                                        density="compact"
                                        variant="tonal"
                                        :color="timetableStartsFromPeriod10DraftSelected ? 'success' : undefined"
                                        :ripple="!timetableOptionUnavailable('starts-from-period-10')"
                                        :class="[
                                            'students-timetable-v2-options-card__option',
                                            {
                                                'students-timetable-v2-options-card__option--selected': timetableStartsFromPeriod10DraftSelected,
                                                'students-timetable-v2-options-card__option--unavailable': timetableOptionUnavailable('starts-from-period-10'),
                                                'students-timetable-v2-options-card__option--loading': timetableCalculationLoading || timetableQualityCountersLoading,
                                            },
                                        ]"
                                        role="button"
                                        :tabindex="timetableOptionUnavailable('starts-from-period-10') ? -1 : 0"
                                        :aria-disabled="timetableOptionUnavailable('starts-from-period-10') ? 'true' : 'false'"
                                        :aria-pressed="timetableStartsFromPeriod10DraftSelected ? 'true' : 'false'"
                                        @click="toggleStartsFromPeriod10TimetableOption"
                                        @keydown.enter.prevent="toggleStartsFromPeriod10TimetableOption"
                                        @keydown.space.prevent="toggleStartsFromPeriod10TimetableOption">
                                        <v-card-title class="students-timetable-v2-options-card__option-title">
                                            Erst ab 10. Stunde
                                        </v-card-title>
                                        <v-card-text class="students-timetable-v2-options-card__option-content">
                                            <span class="students-timetable-v2-options-card__option-count">
                                                {{ startsFromPeriod10TimetableCountFormatted }}
                                            </span>
                                        </v-card-text>
                                    </v-card>
                                    <v-card
                                        v-if="!timetableMaxFreeDaysSelected"
                                        rounded="lg"
                                        density="compact"
                                        variant="tonal"
                                        :color="timetableMaxFreeDaysDraftSelected ? 'success' : undefined"
                                        :ripple="!timetableOptionUnavailable('max-free-days')"
                                        :class="[
                                            'students-timetable-v2-options-card__option',
                                            {
                                                'students-timetable-v2-options-card__option--selected': timetableMaxFreeDaysDraftSelected,
                                                'students-timetable-v2-options-card__option--unavailable': timetableOptionUnavailable('max-free-days'),
                                                'students-timetable-v2-options-card__option--loading': timetableCalculationLoading || timetableQualityCountersLoading,
                                            },
                                        ]"
                                        role="button"
                                        :tabindex="timetableOptionUnavailable('max-free-days') ? -1 : 0"
                                        :aria-disabled="timetableOptionUnavailable('max-free-days') ? 'true' : 'false'"
                                        :aria-pressed="timetableMaxFreeDaysDraftSelected ? 'true' : 'false'"
                                        @click="toggleMaxFreeDaysTimetableOption"
                                        @keydown.enter.prevent="toggleMaxFreeDaysTimetableOption"
                                        @keydown.space.prevent="toggleMaxFreeDaysTimetableOption">
                                        <v-card-title class="students-timetable-v2-options-card__option-title">
                                            {{ maxFreeDaysOptionMaximumLabel }}
                                        </v-card-title>
                                        <v-card-text class="students-timetable-v2-options-card__option-content">
                                            <span class="students-timetable-v2-options-card__option-count">
                                                {{ maxFreeDaysTimetableCountFormatted }}
                                            </span>
                                        </v-card-text>
                                    </v-card>
                                    <v-card
                                        v-if="!timetableNoDistanceLearningSelected"
                                        rounded="lg"
                                        density="compact"
                                        variant="tonal"
                                        :color="timetableNoDistanceLearningDraftSelected ? 'success' : undefined"
                                        :ripple="!timetableOptionUnavailable('no-distance-learning')"
                                        :class="[
                                            'students-timetable-v2-options-card__option',
                                            {
                                                'students-timetable-v2-options-card__option--selected': timetableNoDistanceLearningDraftSelected,
                                                'students-timetable-v2-options-card__option--unavailable': timetableOptionUnavailable('no-distance-learning'),
                                                'students-timetable-v2-options-card__option--loading': timetableCalculationLoading || timetableQualityCountersLoading,
                                            },
                                        ]"
                                        role="button"
                                        :tabindex="timetableOptionUnavailable('no-distance-learning') ? -1 : 0"
                                        :aria-disabled="timetableOptionUnavailable('no-distance-learning') ? 'true' : 'false'"
                                        :aria-pressed="timetableNoDistanceLearningDraftSelected ? 'true' : 'false'"
                                        @click="toggleNoDistanceLearningTimetableOption"
                                        @keydown.enter.prevent="toggleNoDistanceLearningTimetableOption"
                                        @keydown.space.prevent="toggleNoDistanceLearningTimetableOption">
                                        <v-card-title class="students-timetable-v2-options-card__option-title">
                                            Kein Fernunterricht
                                        </v-card-title>
                                        <v-card-text class="students-timetable-v2-options-card__option-content">
                                            <span class="students-timetable-v2-options-card__option-count">
                                                {{ noDistanceLearningTimetableCountFormatted }}
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
                                {{ moreCoursesTitle }}
                            </v-card-title>
                            <v-card-text>
                                <template v-if="moreCourseAvailabilityLoading">
                                    <v-progress-linear
                                        indeterminate
                                        color="primary"
                                        class="students-timetable-v2-completed-courses__loading" />
                                    <v-alert type="info" variant="tonal" density="compact" icon="mdi-calculator-variant-outline">
                                        Weitere Module werden geprüft.
                                    </v-alert>
                                </template>
                                <div v-else-if="!displayedActiveMoreCoursesCategoryCard" class="students-timetable-v2-more-courses-card__category-grid">
                                    <v-card
                                        v-for="card in displayedMoreCoursesCategoryCards"
                                        :key="card.key"
                                        rounded="lg"
                                        variant="tonal"
                                        class="students-timetable-v2-more-courses-card__category"
                                        :class="[
                                            `students-timetable-v2-more-courses-card__category--${card.key}`,
                                            {
                                                'students-timetable-v2-more-courses-card__category--empty': card.disabled,
                                            },
                                        ]"
                                        :disabled="card.disabled"
                                        :ripple="!card.disabled"
                                        :role="card.disabled ? undefined : 'button'"
                                        :tabindex="card.disabled ? -1 : 0"
                                        :aria-disabled="card.disabled ? 'true' : 'false'"
                                        @click="openMoreCoursesCategoryCard(card)"
                                        @keydown.enter.prevent="openMoreCoursesCategoryCard(card)"
                                        @keydown.space.prevent="openMoreCoursesCategoryCard(card)">
                                        <div class="students-timetable-v2-more-courses-card__category-title">
                                            <span>{{ card.title }}</span>
                                        </div>
                                    </v-card>
                                </div>
                                <div v-else class="students-timetable-v2-more-courses-card__course-view">
                                    <section
                                        class="students-timetable-v2-more-courses-card__back-card"
                                        role="button"
                                        tabindex="0"
                                        @click="closeMoreCoursesCategoryCard"
                                        @keydown.enter.prevent="closeMoreCoursesCategoryCard"
                                        @keydown.space.prevent="closeMoreCoursesCategoryCard">
                                        <v-icon icon="mdi-arrow-left" size="18" />
                                        <span>Zurück</span>
                                    </section>
                                    <div v-if="displayedActiveMoreCoursesCategoryCard.items.length" class="students-timetable-v2-more-courses-card__list">
                                        <v-card
                                            v-for="course in displayedActiveMoreCoursesCategoryCard.items"
                                            :key="course.selectionKey"
                                            rounded="lg"
                                            variant="tonal"
                                            :color="course.color"
                                            :disabled="course.disabled"
                                            :ripple="!course.disabled"
                                            class="students-timetable-v2-more-courses-card__course"
                                            :class="course.classes"
                                            :role="course.disabled ? undefined : 'button'"
                                            :tabindex="course.disabled ? -1 : 0"
                                            :aria-disabled="course.ariaDisabled"
                                            :aria-expanded="course.ariaExpanded"
                                            :title="course.title"
                                            @click="toggleMoreCourseOffers(course)"
                                            @keydown.enter.prevent="toggleMoreCourseOffers(course)"
                                            @keydown.space.prevent="toggleMoreCourseOffers(course)">
                                            <span class="students-timetable-v2-more-courses-card__label">
                                                {{ course.label }}
                                            </span>
                                            <v-chip
                                                v-if="course.meta"
                                                size="x-small"
                                                :color="course.color"
                                                variant="tonal"
                                                class="students-timetable-v2-more-courses-card__meta">
                                                {{ course.meta }}
                                            </v-chip>
                                        </v-card>
                                    </div>
                                    <v-alert v-else type="info" variant="tonal" density="compact">
                                        Keine weiteren Module verfügbar.
                                    </v-alert>
                                </div>
                            </v-card-text>
                        </v-card>
                        <v-card
                            v-if="moreCoursesCardVisible && selectedMoreCourseItem"
                            rounded="lg"
                            variant="tonal"
                            class="students-timetable-v2-more-courses-offered-card students-timetable-v2-offered-courses-card">
                            <v-card-title class="students-timetable-v2-offered-courses-card__title">
                                <span>Angebotene Kurse zum Modul {{ selectedMoreCourseItem.label }}</span>
                                <span class="students-timetable-v2-offered-courses-card__actions">
                                    <v-btn
                                        size="small"
                                        color="success"
                                        variant="tonal"
                                        append-icon="mdi-check"
                                        :disabled="!moreCoursesSelectionChanged || timetableCalculationLoading"
                                        @click.stop="applyMoreCoursesSelection">
                                        Anwenden
                                    </v-btn>
                                    <v-btn
                                        prepend-icon="mdi-close"
                                        size="small"
                                        variant="tonal"
                                        color="error"
                                        title="Schließen"
                                        aria-label="Schließen"
                                        @click.stop="closeMoreCourseOffers">
                                        Schließen
                                    </v-btn>
                                </span>
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
                                        :class="course.classes"
                                        :role="course.disabled ? undefined : 'button'"
                                        :tabindex="course.disabled ? -1 : 0"
                                        :aria-pressed="course.ariaPressed"
                                        :aria-disabled="course.ariaDisabled"
                                        :title="course.title"
                                        @click="toggleMoreOfferedCourseItem(course, selectedMoreCourseItem)"
                                        @keydown.enter.prevent="toggleMoreOfferedCourseItem(course, selectedMoreCourseItem)"
                                        @keydown.space.prevent="toggleMoreOfferedCourseItem(course, selectedMoreCourseItem)">
                                        <v-icon v-if="course.selected" icon="mdi-check" size="16" color="success" />
                                        <span class="students-timetable-v2-offered-courses-card__name">
                                            {{ course.name || course.code }}
                                        </span>
                                        <span v-if="offeredCourseCodeVisible(course)" class="students-timetable-v2-offered-courses-card__code">
                                            {{ course.code }}
                                        </span>
                                        <v-chip v-if="course.scheduleLabel" size="x-small" color="primary" variant="tonal">
                                            {{ course.scheduleLabel }}
                                        </v-chip>
                                        <v-chip
                                            v-if="course.instructionVisible"
                                            size="x-small"
                                            color="warning"
                                            variant="tonal"
                                            :title="course.instructionLabel">
                                            {{ course.instructionLabel }}
                                        </v-chip>
                                        <v-chip v-if="course.roomsLabel" size="x-small" color="secondary" variant="outlined">
                                            {{ course.roomsLabel }}
                                        </v-chip>
                                        <div
                                            v-if="course.conflictItems.length"
                                            class="students-timetable-v2-offered-courses-card__conflicts">
                                            <div class="students-timetable-v2-offered-courses-card__conflicts-title">
                                                Konflikte
                                            </div>
                                            <div
                                                v-for="conflict in course.conflictItems"
                                                :key="conflict.key"
                                                class="students-timetable-v2-offered-courses-card__conflict">
                                                <v-icon icon="mdi-alert-circle-outline" size="14" />
                                                <span class="students-timetable-v2-offered-courses-card__conflict-time">
                                                    {{ conflict.timeLabel }}
                                                </span>
                                                <span
                                                    v-if="conflict.dateLabel"
                                                    class="students-timetable-v2-offered-courses-card__conflict-date">
                                                    {{ conflict.dateLabel }}
                                                </span>
                                                <span class="students-timetable-v2-offered-courses-card__conflict-courses">
                                                    {{ conflict.courseLabels.join(', ') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <v-alert v-else type="info" variant="tonal" density="compact">
                                    Keine angebotenen Module gefunden.
                                </v-alert>
                            </v-card-text>
                        </v-card>
                        <div
                            v-if="selectedTimetableV2Result"
                            class="students-timetable-v2-result">
                            <div class="students-timetable-v2-result__header">
                                <div class="students-timetable-v2-result__title">
                                    <v-btn
                                        v-if="selectedTimetableV2RestartButtonInHeaderVisible"
                                        color="error"
                                        variant="tonal"
                                        size="large"
                                        prepend-icon="mdi-restart"
                                        :disabled="timetableCalculationLoading || timetablePendingActionConfirmationVisible"
                                        @click="restartTimetableV2">
                                        Neustart
                                    </v-btn>
                                    <template v-if="selectedTimetableV2TitleLabel">
                                        <v-icon icon="mdi-calendar-check-outline" size="18" color="success" />
                                        <span>{{ selectedTimetableV2TitleLabel }}</span>
                                    </template>
                                </div>
                                <div
                                    v-if="selectedTimetableV2RestartButtonInHeaderVisible || adoptedTimetableVisible"
                                    class="students-timetable-v2-result__meta">
                                    <v-btn
                                        v-if="adoptedTimetableVisible"
                                        color="error"
                                        variant="tonal"
                                        size="large"
                                        prepend-icon="mdi-printer-outline"
                                        :disabled="pdfExporting || !selectedTimetableV2Result"
                                        :loading="pdfExporting"
                                        @click="openAdoptedTimetablePrintDialog">
                                        Drucken
                                    </v-btn>
                                    <v-btn
                                        v-if="adoptedTimetableSaveVisible"
                                        color="success"
                                        variant="flat"
                                        size="large"
                                        prepend-icon="mdi-content-save-outline"
                                        :title="`Stundenplan für ${adoptedPublishedTimetableStudentName} speichern`"
                                        :aria-label="`Stundenplan für ${adoptedPublishedTimetableStudentName} speichern`"
                                        :disabled="publishedTimetableSaving || !selectedTimetableV2Result"
                                        :loading="publishedTimetableSaving"
                                        @click="saveAdoptedPublishedStudentTimetable">
                                        <span class="students-timetable-v2-save-button__label">
                                            <span>Speichern für</span>
                                            <span>{{ adoptedPublishedTimetableStudentName }}</span>
                                        </span>
                                    </v-btn>
                                    <v-btn
                                        v-if="selectedTimetableV2RestartButtonInHeaderVisible"
                                        color="primary"
                                        variant="tonal"
                                        size="large"
                                        prepend-icon="mdi-arrow-left"
                                        :disabled="timetableCalculationLoading || timetablePendingActionConfirmationVisible"
                                        @click="adoptedTimetableVisible ? backToTimetableCalculation() : backToCourseReview()">
                                        Zurück
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
                                                    { showRegularRange: selectedTimetableV2DisplaySameSlotEntries(selectedTimetableV2Slot(weekday.value, time.value)).length > 0 },
                                                )"
                                                class="students-timetable-v2-result-grid__recurrence">
                                                <template
                                                    v-if="selectedTimetableV2SlotTimePatternParts(
                                                        selectedTimetableV2DisplaySlot(selectedTimetableV2Slot(weekday.value, time.value)),
                                                        { showRegularRange: selectedTimetableV2DisplaySameSlotEntries(selectedTimetableV2Slot(weekday.value, time.value)).length > 0 },
                                                    ).dateLabel">
                                                    <span
                                                        v-if="selectedTimetableV2SlotTimePatternParts(
                                                            selectedTimetableV2DisplaySlot(selectedTimetableV2Slot(weekday.value, time.value)),
                                                            { showRegularRange: selectedTimetableV2DisplaySameSlotEntries(selectedTimetableV2Slot(weekday.value, time.value)).length > 0 },
                                                        ).recurrenceLabel">
                                                        {{
                                                            selectedTimetableV2SlotTimePatternParts(
                                                                selectedTimetableV2DisplaySlot(selectedTimetableV2Slot(weekday.value, time.value)),
                                                                { showRegularRange: selectedTimetableV2DisplaySameSlotEntries(selectedTimetableV2Slot(weekday.value, time.value)).length > 0 },
                                                            ).recurrenceLabel
                                                        }}:
                                                    </span>
                                                    <span class="students-timetable-v2-result-grid__date">
                                                        {{
                                                            selectedTimetableV2SlotTimePatternParts(
                                                                selectedTimetableV2DisplaySlot(selectedTimetableV2Slot(weekday.value, time.value)),
                                                                { showRegularRange: selectedTimetableV2DisplaySameSlotEntries(selectedTimetableV2Slot(weekday.value, time.value)).length > 0 },
                                                            ).dateLabel
                                                        }}{{
                                                            selectedTimetableV2SlotTimePatternParts(
                                                                selectedTimetableV2DisplaySlot(selectedTimetableV2Slot(weekday.value, time.value)),
                                                                { showRegularRange: selectedTimetableV2DisplaySameSlotEntries(selectedTimetableV2Slot(weekday.value, time.value)).length > 0 },
                                                            ).compactSuffix
                                                        }}
                                                    </span>
                                                </template>
                                                <template v-else>
                                                    {{
                                                        selectedTimetableV2SlotTimePatternLabel(
                                                            selectedTimetableV2DisplaySlot(selectedTimetableV2Slot(weekday.value, time.value)),
                                                            { showRegularRange: selectedTimetableV2DisplaySameSlotEntries(selectedTimetableV2Slot(weekday.value, time.value)).length > 0 },
                                                        )
                                                    }}
                                                </template>
                                            </div>
                                            <div
                                                v-if="selectedTimetableV2SlotInstructionLabel(selectedTimetableV2DisplaySlot(selectedTimetableV2Slot(weekday.value, time.value)))"
                                                class="students-timetable-v2-result-grid__distance-learning">
                                                {{ selectedTimetableV2SlotInstructionLabel(selectedTimetableV2DisplaySlot(selectedTimetableV2Slot(weekday.value, time.value))) }}
                                            </div>
                                            <div
                                                v-if="selectedTimetableV2DisplaySameSlotEntries(selectedTimetableV2Slot(weekday.value, time.value)).length"
                                                class="students-timetable-v2-result-grid__same-slots">
                                                <div
                                                    v-for="sameSlotEntry in selectedTimetableV2DisplaySameSlotEntries(selectedTimetableV2Slot(weekday.value, time.value))"
                                                    :key="selectedTimetableV2SlotKey(sameSlotEntry)"
                                                    class="students-timetable-v2-result-grid__same-slot">
                                                    <div class="students-timetable-v2-result-grid__code">
                                                        <span>{{ selectedTimetableV2SlotTitle(sameSlotEntry) }}</span>
                                                        <sup
                                                            v-if="sameSlotEntry.isAdditionalCourse"
                                                            class="students-timetable-v2-result-grid__badge students-timetable-v2-result-grid__badge--additional">
                                                            Zusatz
                                                        </sup>
                                                    </div>
                                                    <div
                                                        v-if="selectedTimetableV2SlotDetails(sameSlotEntry)"
                                                        class="students-timetable-v2-result-grid__details">
                                                        {{ selectedTimetableV2SlotDetails(sameSlotEntry) }}
                                                    </div>
                                                    <div
                                                        v-if="selectedTimetableV2SlotTimePatternLabel(sameSlotEntry, { showRegularRange: true })"
                                                        class="students-timetable-v2-result-grid__recurrence">
                                                        <template v-if="selectedTimetableV2SlotTimePatternParts(sameSlotEntry, { showRegularRange: true }).dateLabel">
                                                            <span v-if="selectedTimetableV2SlotTimePatternParts(sameSlotEntry, { showRegularRange: true }).recurrenceLabel">
                                                                {{ selectedTimetableV2SlotTimePatternParts(sameSlotEntry, { showRegularRange: true }).recurrenceLabel }}:
                                                            </span>
                                                            <span class="students-timetable-v2-result-grid__date">
                                                                {{ selectedTimetableV2SlotTimePatternParts(sameSlotEntry, { showRegularRange: true }).dateLabel }}{{ selectedTimetableV2SlotTimePatternParts(sameSlotEntry, { showRegularRange: true }).compactSuffix }}
                                                            </span>
                                                        </template>
                                                        <template v-else>
                                                            {{ selectedTimetableV2SlotTimePatternLabel(sameSlotEntry, { showRegularRange: true }) }}
                                                        </template>
                                                    </div>
                                                    <div
                                                        v-if="selectedTimetableV2SlotInstructionLabel(sameSlotEntry)"
                                                        class="students-timetable-v2-result-grid__distance-learning">
                                                        {{ selectedTimetableV2SlotInstructionLabel(sameSlotEntry) }}
                                                    </div>
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
                                        <template
                                            v-for="part in selectedTimetableV2ConflictSummaryParts(conflict)"
                                            :key="part.key">
                                            <span
                                                class="students-timetable-v2-result-conflicts__part"
                                                :class="{ 'students-timetable-v2-result-conflicts__date': part.type === 'date' }">{{ part.text }}</span>
                                        </template>
                                    </li>
                                </ul>
                                <div
                                    v-if="selectedTimetableV2ConflictResolutionActionsVisible"
                                    class="students-timetable-v2-result-conflicts__actions">
                                    <v-btn
                                        v-for="option in selectedTimetableV2ConflictResolutionOptions"
                                        :key="option.selectionKey"
                                        size="small"
                                        :color="option.color"
                                        variant="tonal"
                                        :prepend-icon="option.icon"
                                        :class="{ 'students-timetable-v2-result-conflicts__action--recommended': option.recommended }"
                                        :disabled="timetablePendingActionConfirmationVisible"
                                        @click="applySelectedTimetableV2ConflictResolution(option)">
                                        {{ option.buttonLabel }}
                                    </v-btn>
                                </div>
                            </div>
                        </div>
                        <v-alert
                            v-else-if="timetableCalculationResult && selectedTimetableV2ProblemCourseItems.length"
                            type="warning"
                            variant="tonal"
                            density="compact"
                            icon="mdi-calendar-alert-outline">
                            <div class="students-timetable-v2-result-problems">
                                <div class="students-timetable-v2-result-problems__title">
                                    {{ timetableCalculationUnavailableLabel }}
                                </div>
                                <ul class="students-timetable-v2-result-problems__list">
                                    <li
                                        v-for="problemCourse in selectedTimetableV2ProblemCourseItems"
                                        :key="problemCourse.key">
                                        <strong>{{ problemCourse.label }}</strong>
                                        <span v-if="problemCourse.reasonLabel">: {{ problemCourse.reasonLabel }}</span>
                                    </li>
                                </ul>
                                <div
                                    v-if="selectedTimetableV2ProblemCourseActionsVisible"
                                    class="students-timetable-v2-result-problems__actions">
                                    <v-btn
                                        v-for="problemCourse in selectedTimetableV2ProblemCourseItems"
                                        :key="`problem-course-action-${problemCourse.key}`"
                                        size="small"
                                        color="error"
                                        variant="tonal"
                                        prepend-icon="mdi-close-circle-outline"
                                        :disabled="!problemCourse.selectedCourse || timetablePendingActionConfirmationVisible"
                                        @click="applySelectedTimetableV2ProblemCourseResolution(problemCourse)">
                                        {{ problemCourse.buttonLabel }}
                                    </v-btn>
                                </div>
                            </div>
                        </v-alert>
                        <v-alert
                            v-else-if="timetableCalculationResult"
                            type="info"
                            variant="tonal"
                            density="compact">
                            <div class="students-timetable-v2-result-problems">
                                <div class="students-timetable-v2-result-problems__title">
                                    {{ timetableCalculationUnavailableLabel }}
                                </div>
                                <div
                                    v-if="selectedTimetableV2NoResultResolutionActionsVisible"
                                    class="students-timetable-v2-result-problems__actions">
                                    <v-btn
                                        v-for="course in selectedTimetableV2NoResultResolutionCourseItems"
                                        :key="`no-result-course-action-${course.key}`"
                                        size="small"
                                        color="error"
                                        variant="tonal"
                                        prepend-icon="mdi-close-circle-outline"
                                        :disabled="!course.selectedCourse || timetablePendingActionConfirmationVisible"
                                        @click="applySelectedTimetableV2CourseResolution(course)">
                                        {{ course.buttonLabel }}
                                    </v-btn>
                                    <v-btn
                                        v-for="option in selectedTimetableV2NoResultResolutionOptionItems"
                                        :key="`no-result-option-action-${option.key}`"
                                        size="small"
                                        color="warning"
                                        variant="tonal"
                                        prepend-icon="mdi-tune-variant-remove"
                                        :disabled="timetablePendingActionConfirmationVisible"
                                        @click="applySelectedTimetableV2OptionResolution(option)">
                                        {{ option.buttonLabel }}
                                    </v-btn>
                                </div>
                            </div>
                        </v-alert>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col
                v-if="(calculationButtonCardVisible || adoptedTimetableButtonCardVisible) && !selectedTimetableV2Result"
                cols="12"
                class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-restart-card">
                    <v-card-text class="students-timetable-v2-restart-card__content">
                        <v-btn
                            v-if="!selectedTimetableV2RestartButtonInHeaderVisible"
                            color="error"
                            variant="tonal"
                            size="large"
                            prepend-icon="mdi-restart"
                            :disabled="timetableCalculationLoading || timetablePendingActionConfirmationVisible"
                            @click="restartTimetableV2">
                            Neustart
                        </v-btn>
                        <div class="students-timetable-v2-restart-card__navigation-actions">
                            <v-btn
                                v-if="!selectedTimetableV2RestartButtonInHeaderVisible"
                                color="primary"
                                variant="tonal"
                                size="large"
                                prepend-icon="mdi-arrow-left"
                                :disabled="timetableCalculationLoading || timetablePendingActionConfirmationVisible"
                                @click="adoptedTimetableVisible ? backToTimetableCalculation() : backToCourseReview()">
                                Zurück
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row v-else dense align="stretch">
            <v-col v-if="timetableV2StepperVisible" cols="12" class="students-timetable-v2-card-column">
                <v-stepper
                    :model-value="timetableV2StepNumber"
                    :items="timetableV2StepperItems"
                    hide-actions
                    flat
                    color="primary"
                    class="students-timetable-v2-stepper" />
            </v-col>

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

            <v-col
                v-if="studentCardVisible || selectionCardVisible"
                cols="12"
                class="students-timetable-v2-card-column students-timetable-v2-student-card-stack">
                <div class="ttv2-strip">
                    <div class="ttv2-strip__identity-row">
                        <div v-if="studentCardVisible" class="ttv2-strip__student">
                            <div class="ttv2-strip__student-icon" aria-hidden="true">
                                {{ storedTimetableStudentInitials }}
                            </div>
                            <div class="ttv2-strip__student-info">
                                <span class="ttv2-strip__student-name">{{ storedTimetableStudentLabel }}</span>
                                <span v-if="storedTimetableStudentReligionMeta() || storedTimetableStudentEmail" class="ttv2-strip__student-meta">
                                    <span v-if="storedTimetableStudentReligionMeta()" class="ttv2-strip__student-religion">
                                        {{ storedTimetableStudentReligionMeta() }}
                                    </span>
                                    <span v-if="storedTimetableStudentReligionMeta() && storedTimetableStudentEmail" aria-hidden="true">·</span>
                                    <button
                                        v-if="storedTimetableStudentEmail"
                                        type="button"
                                        class="ttv2-strip__student-email"
                                        :class="{ 'ttv2-strip__student-email--copied': copiedStudentEmailCode === storedTimetableStudentCode }"
                                        :title="copiedStudentEmailCode === storedTimetableStudentCode ? 'Kopiert!' : `E-Mail kopieren: ${storedTimetableStudentEmail}`"
                                        @click.stop="copyStoredTimetableStudentEmail">
                                        <span>{{ storedTimetableStudentEmail }}</span>
                                        <v-icon :icon="copiedStudentEmailCode === storedTimetableStudentCode ? 'mdi-check' : 'mdi-content-copy'" size="11" />
                                    </button>
                                </span>
                            </div>
                            <div class="ttv2-strip__student-actions">
                                <v-btn
                                    icon="mdi-pencil"
                                    variant="text"
                                    density="compact"
                                    size="x-small"
                                    title="Student bearbeiten"
                                    @click.stop="openStudentDialog" />
                                <v-btn
                                    v-if="storedTimetableStudentContext"
                                    icon="mdi-close"
                                    variant="text"
                                    density="compact"
                                    size="x-small"
                                    title="Student entfernen"
                                    @click.stop="clearStoredTimetableStudent" />
                            </div>
                        </div>

                        <div v-if="!studentCardVisible && selectionCardVisible" class="ttv2-strip__no-student">
                            <v-icon icon="mdi-account-off-outline" size="18" />
                            <span>Ohne Studierenden</span>
                        </div>
                    </div>

                    <div v-if="selectionCardVisible" class="ttv2-strip__selections">
                        <div
                            v-for="item in storedTimetableSelectionSummary"
                            :key="item.key"
                            class="ttv2-strip__sel-group"
                            role="group"
                            :aria-label="item.label"
                            :title="item.label">
                            <span class="ttv2-strip__sel-label">{{ item.label }}</span>
                            <div v-if="item.options?.length" class="ttv2-strip__sel-chips">
                                <v-chip
                                    v-for="option in item.options"
                                    :key="option.value"
                                    size="small"
                                    density="default"
                                    class="ttv2-strip__sel-chip"
                                    :class="{ 'ttv2-strip__sel-chip--selected': selectionOptionSelected(item, option) }"
                                    variant="flat"
                                    :aria-pressed="selectionOptionSelected(item, option) ? 'true' : 'false'"
                                    @click="selectTimetableSelectionOption(item.key, option.value)">
                                    <v-icon v-if="selectionOptionSelected(item, option)" icon="mdi-check" size="14" />
                                    {{ option.title }}
                                </v-chip>
                            </div>
                            <span v-if="item.meta" class="ttv2-strip__sel-meta">{{ item.meta }}</span>
                            <strong v-else-if="!item.options?.length" class="ttv2-strip__sel-value" :class="{ 'ttv2-strip__sel-value--unknown': !item.known }">
                                {{ item.value }}
                            </strong>
                        </div>
                    </div>
                </div>
            </v-col>

            <v-col v-if="courseCardsVisible" cols="12" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-visited-courses-card">
                    <v-card-title class="students-timetable-v2-visited-courses-card__title">
                        <span>Besuchte Module</span>
                        <span class="students-timetable-v2-visited-courses-card__legend" aria-label="Legende">
                            <span><i class="students-timetable-v2-visited-courses-card__legend-dot students-timetable-v2-visited-courses-card__legend-dot--completed"></i> Bestanden</span>
                            <span><i class="students-timetable-v2-visited-courses-card__legend-dot students-timetable-v2-visited-courses-card__legend-dot--failed"></i> Negativ</span>
                        </span>
                    </v-card-title>
                    <v-card-text class="students-timetable-v2-visited-courses-card__content">
                        <div v-if="visitedCourseItems.length" class="students-timetable-v2-visited-courses-card__list">
                            <v-chip
                                v-for="course in visitedCourseItems"
                                :key="course.key"
                                variant="flat"
                                size="small"
                                label
                                class="students-timetable-v2-visited-courses-card__chip"
                                :class="`students-timetable-v2-visited-courses-card__chip--${course.status}`"
                                :title="course.title">
                                <span class="students-timetable-v2-visited-courses-card__course">{{ course.label }}</span>
                                <span v-if="course.status === 'failed'" class="students-timetable-v2-visited-courses-card__negative-badge">NEG</span>
                                <span class="students-timetable-v2-visited-courses-card__grade">{{ course.grade }}</span>
                            </v-chip>
                        </div>
                        <v-alert v-else type="info" variant="tonal" density="compact">
                            Keine besuchten Module gefunden.
                        </v-alert>
                    </v-card-text>
                </v-card>
            </v-col>

            <CourseSelectionCards
                v-if="courseCardsVisible"
                :cards="courseSelectionCards"
                :course-card-md-columns="courseCardMdColumns"
                :course-cards-error="studentCompletedCoursesError || subjectRowsError"
                :course-selections="storedCourseSelectionOverrides"
                :student-completed-courses-loading="studentCompletedCoursesLoading"
                :subject-rows-loading="subjectRowsLoading"
                @apply-course-selections="applyDraftCourseSelections" />

            <v-col v-if="courseCardsVisible && selectedCourseLimitReached" cols="12" class="students-timetable-v2-card-column">
                <v-alert type="info" variant="tonal" density="compact" icon="mdi-information-outline" class="students-timetable-v2-limit-alert">
                    <div>Maximum erreicht: Negative Module und Frühere Module dürfen zusammen höchstens 10 Module und 30 Stunden ergeben.</div>
                    <div>Wählen Sie ein Modul ab, um ein anderes Modul auszuwählen.</div>
                </v-alert>
            </v-col>

            <v-col v-if="restartCardVisible" cols="12" class="students-timetable-v2-card-column">
                <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-restart-card">
                    <v-card-text class="students-timetable-v2-restart-card__content">
                        <div v-if="courseCardsVisible" class="students-timetable-v2-restart-card__summary">
                            <span>Ausgewählt: <strong>{{ selectedCourseLimitSummary.countLabel }}</strong></span>
                            <span>{{ selectedCourseLimitSummary.hoursLabel }}</span>
                            <span class="students-timetable-v2-restart-card__limit">Maximal 10 Module / 30 Std.</span>
                        </div>
                        <div class="students-timetable-v2-restart-card__actions">
                            <v-btn
                                color="error"
                                variant="outlined"
                                size="large"
                                prepend-icon="mdi-restart"
                                @click="restartTimetableV2">
                                Neustart
                            </v-btn>
                            <v-btn
                                v-if="courseCardsVisible && courseLimitPreselectionResetAvailable"
                                class="students-timetable-v2-restart-card__preselection-button"
                                variant="outlined"
                                size="large"
                                prepend-icon="mdi-tune-variant"
                                @click="applyCourseLimitPreselection(true)">
                                Vorauswahl zurücksetzen
                            </v-btn>
                            <v-btn
                                v-if="courseCardsVisible"
                                variant="flat"
                                size="large"
                                class="students-timetable-v2-restart-card__automatic-button"
                                append-icon="mdi-arrow-right"
                                :disabled="selectedCourseLimitExceeded"
                                @click="openCourseReview">
                                Weiter
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="printDialogVisible" persistent max-width="460">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-printer-outline" />
                    Stundenplan drucken
                </v-card-title>
                <v-card-text>
                    <v-checkbox
                        v-model="printOptions.singleWeeks"
                        label="Einzelne Wochen drucken"
                        color="primary"
                        density="compact"
                        hide-details />
                    <v-checkbox
                        v-model="printOptions.courseList"
                        label="Modulliste"
                        color="primary"
                        density="compact"
                        hide-details />
                    <v-checkbox
                        v-model="printOptions.courseOverview"
                        label="Modulübersicht"
                        color="primary"
                        density="compact"
                        hide-details />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" :disabled="pdfExporting" @click="closeAdoptedTimetablePrintDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        prepend-icon="mdi-printer-outline"
                        :disabled="!selectedTimetableV2Result"
                        :loading="pdfExporting"
                        @click="downloadAdoptedTimetablePdf">
                        Drucken
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

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
                                tag="div"
                                role="button"
                                tabindex="0"
                                size="small"
                                variant="tonal"
                                :color="String(studentSelectionDraft.studentCode) === String(student.student_code) ? 'primary' : 'secondary'"
                                class="students-timetable-v2-student-search-results__item"
                                block
                                @click="selectStudentDraft(student.student_code)"
                                @keydown.enter.prevent="selectStudentDraft(student.student_code)"
                                @keydown.space.prevent="selectStudentDraft(student.student_code)">
                                <span class="students-timetable-v2-student-search-results__label">
                                    {{ studentOptionTitle(student) }}
                                </span>
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
import CourseSelectionCards from './CourseSelectionCards.vue'

const TIMETABLE_V2_OVERVIEW_PATH = '/admin/students-timetables/timetable-v2/overview'
const TIMETABLE_V2_SELECTION_BOOTSTRAP_ENDPOINT = '/api/admin/students-timetables/timetable-v2-selection-bootstrap'
const TIMETABLE_V2_ROUTE_STEPS = ['selection', 'course-review', 'timetable-calculation', 'timetable-adoption']
const TIMETABLE_V2_ROUTE_MODES = ['student', 'without-student']
const TIMETABLE_MAX_FREE_DAYS_CRITERION_KEY = 'free_days'
const TIMETABLE_AVOID_DISTANCE_LEARNING_CRITERION_KEY = 'avoid_distance_learning'
const TIMETABLE_NO_DISTANCE_LEARNING_OPTION_VALUE = 'none'
const TIMETABLE_SATURDAY_FREE_CRITERION_KEY = 'saturday_free'
const TIMETABLE_STARTS_FROM_PERIOD_10_CRITERION_KEY = 'starts_from_period_10'
const TIMETABLE_PROGRESS_FINALIZING_THRESHOLD = 95
const TIMETABLE_PROGRESS_PENDING_LIMIT = 99
const OFFERED_COURSE_BULK_SELECTION_OPTIONS = [
    { key: 'all', label: 'Alle' },
    { key: 'distance-learning', label: 'Nur Fernunterricht' },
    { key: 'without-distance-learning', label: 'Ohne Fernunterricht' },
]
const TIMETABLE_COURSE_SUBJECT_LABELS = {
    REV: { code: 'Rev', name: 'Evangelische Religion' },
    RK: { code: 'Rk', name: 'Katholische Religion' },
    RIS: { code: 'Ris', name: 'Islamische Religion' },
    ROR: { code: 'Ror', name: 'Orthodoxe Religion' },
    D: { code: 'D', name: 'Deutsch' },
    E: { code: 'E', name: 'Englisch' },
    F: { code: 'F', name: 'Französisch' },
    L: { code: 'L', name: 'Latein' },
    SPA: { code: 'SPA', name: 'Spanisch' },
    GWB: { code: 'GWB', name: 'Geographie' },
    GPB: { code: 'GPB', name: 'Geschichte' },
    BU: { code: 'BU', name: 'Biologie' },
    CH: { code: 'CH', name: 'Chemie' },
    PH: { code: 'PH', name: 'Physik' },
    PP: { code: 'PP', name: 'Psychologie und Philosophie' },
    MU: { code: 'MU', name: 'Musik' },
    KG: { code: 'KG', name: 'Kunst' },
    INF: { code: 'INF', name: 'Informatik' },
    OEKO: { code: 'ÖKO', name: 'Ökonomie' },
}
const TIMETABLE_COURSE_SUBJECT_DISPLAY_ALIASES = {
    GW: 'GWB',
    OEK: 'OEKO',
    OKO: 'OEKO',
    OKON: 'OEKO',
    S: 'SPA',
}

export default {
    components: {
        CourseSelectionCards,
    },

    data() {
        return {
            robotStudents: [],
            initialRouteLoading: true,
            storageRevision: 0,
            storedTimetableState: null,
            pendingStoredTimetableState: null,
            storedTimetableStateLoading: false,
            storedTimetableStateSaving: false,
            storedTimetableStateSaveRequestId: 0,
            storedTimetableStateSaveTimer: null,
            draftCourseSelections: null,
            studentDialogOpen: false,
            studentCompletedCoursesError: '',
            studentCompletedCoursesLoading: false,
            selectionBootstrapLoading: false,
            studentOverviewActiveRequestKey: '',
            studentOverviewLoadedRequestKey: '',
            studentCompletedCoursesRequestId: 0,
            studentOptionsLoading: false,
            studentSearch: '',
            copiedStudentEmailCode: null,
            copyStudentEmailResetTimeout: null,
            studentSelectionDraft: {
                studentCode: null,
            },
            studentSelectionExplicitlyCleared: false,
            courseGroups: [],
            courseGroupsRevision: 0,
            courseGroupsError: '',
            courseGroupsLoaded: false,
            courseGroupsLoading: false,
            offeredCourseItemsCache: {},
            offeredCourseItemsCacheCourseGroups: null,
            schoolHours: [],
            schoolHoursLoading: false,
            subjectRows: [],
            subjectRowsError: '',
            subjectRowsLoading: false,
            selectedReviewCourseKey: '',
            timetableCalculationError: '',
            timetableCalculationLoading: false,
            timetableCalculationLoadingMode: 'calculation',
            timetableCalculationProgress: 0,
            timetableCalculationProgressCompletionPending: false,
            timetableCalculationProgressResetTimer: null,
            timetableCalculationProgressSource: '',
            timetableCalculationProgressTimer: null,
            timetableCalculationRequestId: 0,
            timetableCalculationResult: null,
            timetableCalculationResultCache: {},
            adoptedTimetableCalculationResult: null,
            adoptedTimetableSelectedNumber: 1,
            adoptedTimetableCalculationSelectionSnapshot: null,
            adoptedTimetableCalculationOptionsSnapshot: null,
            adoptedTimetableSelectionSnapshot: null,
            adoptedTimetableStudentContextSnapshot: null,
            printDialogVisible: false,
            printOptions: {
                singleWeeks: false,
                courseList: true,
                courseOverview: true,
            },
            pdfExporting: false,
            publishedTimetableSaving: false,
            adoptedPublishedTimetableReport: {
                type: 'success',
                message: '',
            },
            timetableCalculationNumberDraft: '1',
            timetableCalculationSelectedNumber: 1,
            calculationTimetableV2Selection: null,
            initialTimetableCalculationSelectedNumber: 1,
            initialTimetableCalculationSelection: null,
            initialTimetableCalculationNoSaturdaySelected: false,
            initialTimetableCalculationMaxFreeDaysSelected: false,
            initialTimetableCalculationNoDistanceLearningSelected: false,
            initialTimetableCalculationStartsFromPeriod10Selected: false,
            timetableNoSaturdayDraftSelected: false,
            timetableNoSaturdaySelected: false,
            timetableMaxFreeDaysDraftSelected: false,
            timetableMaxFreeDaysSelected: false,
            timetableNoDistanceLearningDraftSelected: false,
            timetableNoDistanceLearningSelected: false,
            timetableStartsFromPeriod10DraftSelected: false,
            timetableStartsFromPeriod10Selected: false,
            timetableOptionsCardVisible: false,
            moreCoursesVisible: false,
            moreCoursesCardVisible: false,
            activeMoreCoursesCategoryCardKey: '',
            activeMoreAdoptedCourseCardKey: '',
            activeMoreExistingCourseBaseKey: '',
            moreCoursesSelectionSnapshot: '',
            courseSelectionSnapshotSelections: {},
            pendingRemovedSelectedCourseKeys: {},
            offeredCourseSelectionSnapshotSelections: {},
            moreOfferedCourseSelectionSnapshot: '',
            moreOfferedCourseSelectionSnapshotSelections: {},
            moreCourseAvailabilityLoading: false,
            moreCourseAvailabilityByKey: {},
            moreCourseAvailabilityRequestId: 0,
            moreCourseAvailabilitySignature: '',
            restorableMoreCourseAvailabilitySignatures: {},
            timetableQualityCountersLoading: false,
            timetableQualityCountersRequestId: 0,
            conflictResolutionRecommendationByKey: {},
            conflictResolutionRecommendationLoading: false,
            conflictResolutionRecommendationPromise: null,
            conflictResolutionRecommendationRequestId: 0,
            conflictResolutionRecommendationSignature: '',
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
            return this.courseReviewVisible || this.timetableCalculationVisible || this.adoptedTimetableVisible
        },
        timetableV2StepperVisible() {
            return !this.startCardVisible
                && (this.timetableStartMode === 'student' || Boolean(this.storedTimetableStudentContext))
        },
        timetableV2StepNumber() {
            return TIMETABLE_V2_ROUTE_STEPS.indexOf(this.timetableV2Step) + 1
        },
        timetableV2StepperItems() {
            const currentStepNumber = this.timetableV2StepNumber

            return [
                { title: 'Auswahl', value: 1 },
                { title: 'Module', value: 2 },
                { title: 'Stundenplan', value: 3 },
                { title: 'Übernahme', value: 4 },
            ].map((item) => ({
                ...item,
                complete: item.value < currentStepNumber,
                editable: false,
            }))
        },
        timetableV2PageActionsDisabled() {
            return this.timetableV2PageLoading
        },
        courseReviewVisible() {
            return this.timetableV2Step === 'course-review'
        },
        selectedCourseItemsClickable() {
            return this.courseReviewVisible && !this.timetableV2PageActionsDisabled
        },
        selectedCourseItemsDisabled() {
            return this.timetableV2PageActionsDisabled || this.moreCoursesVisible || this.timetableOptionsCardVisible
        },
        selectedTimetableSummaryChipsDisabled() {
            return this.timetableV2PageActionsDisabled || this.moreCoursesVisible || this.timetableOptionsCardVisible || this.adoptedTimetableVisible
        },
        timetablePendingActionConfirmationVisible() {
            return (
                this.timetableCalculationVisible
                && (this.moreCoursesVisible || this.timetableOptionsCardVisible)
            ) || this.adoptedTimetableCourseRemovalPendingVisible
        },
        adoptedTimetableCourseRemovalPendingVisible() {
            return this.adoptedTimetableVisible
                && this.moreCoursesVisible
                && !this.moreCoursesCardVisible
        },
        selectedCourseItemsDeletable() {
            return (this.timetableCalculationVisible || this.adoptedTimetableVisible)
                && !this.timetableCalculationLoading
                && !this.selectedCourseItemsDisabled
        },
        selectedTimetableOptionItemsDeletable() {
            return !this.timetableCalculationLoading && !this.selectedTimetableSummaryChipsDisabled
        },
        selectedCourseItemOfferStatusVisible() {
            return !this.timetableCalculationVisible
        },
        offeredCourseBulkSelectionOptions() {
            return OFFERED_COURSE_BULK_SELECTION_OPTIONS
        },
        includeNormalunterrichtCourseVariants() {
            return this.instructionCourseFiltersForSelection(
                this.currentTimetableV2SelectionForCourseState(),
            ).includeNormalunterrichtCourses
        },
        includeDistanceLearningCourseVariants() {
            return this.instructionCourseFiltersForSelection(
                this.currentTimetableV2SelectionForCourseState(),
            ).includeDistanceLearningCourses
        },
        includeKompaktunterrichtCourseVariants() {
            return this.instructionCourseFiltersForSelection(
                this.currentTimetableV2SelectionForCourseState(),
            ).includeKompaktunterrichtCourses
        },
        timetableCalculationVisible() {
            return this.timetableV2Step === 'timetable-calculation'
        },
        adoptedTimetableVisible() {
            return this.timetableV2Step === 'timetable-adoption'
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
        courseCardMdColumns() {
            return 3
        },
        courseSelectionCards() {
            return [
                {
                    courseGroup: 'completed',
                    emptyLabel: 'Keine abgeschlossenen Module gefunden.',
                    items: this.storedCompletedCourseItems.map((course) => this.courseSelectionCardItem(course, 'completed')),
                    key: 'completed',
                    mdColumns: 12,
                    title: 'Abgeschlossene Module',
                    visible: this.courseCardsVisible,
                },
                {
                    courseGroup: 'missing',
                    emptyLabel: 'Keine negativen Module gefunden.',
                    items: this.storedMissingCourseCardItems.map((course) => this.courseSelectionCardItem(course, 'missing')),
                    key: 'missing',
                    title: 'Negative Module',
                    visible: this.courseCardsVisible,
                },
                {
                    courseGroup: 'planned',
                    emptyLabel: 'Keine vorgesehenen Module gefunden.',
                    items: this.storedPlannedCourseItems.map((course) => this.courseSelectionCardItem(course, 'planned')),
                    key: 'planned',
                    title: 'Frühere Module',
                    visible: this.courseCardsVisible,
                },
                {
                    courseGroup: 'semester',
                    emptyLabel: 'Keine aktuellen Module gefunden.',
                    items: this.storedSemesterCourseItems.map((course) => this.courseSelectionCardItem(course, 'semester')),
                    key: 'semester',
                    title: 'Aktuelle Module',
                    visible: this.courseCardsVisible,
                },
                {
                    courseGroup: 'additional',
                    emptyLabel: 'Keine zusätzlichen Module gefunden.',
                    items: this.storedAdditionalCourseItems.map((course) => this.courseSelectionCardItem(course, 'additional')),
                    key: 'additional',
                    title: 'Zusätzliche Module',
                    visible: this.courseCardsVisible,
                },
            ]
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
            const reviewDataLoading = this.courseReviewVisible || this.timetableCalculationVisible || this.adoptedTimetableVisible
                ? this.courseGroupsLoading || this.schoolHoursLoading
                : false

            return this.initialRouteLoading
                || this.storedTimetableStateLoading
                || this.studentCompletedCoursesLoading
                || this.subjectRowsLoading
                || reviewDataLoading
        },
        reviewButtonCardVisible() {
            return this.courseReviewVisible
        },
        calculationButtonCardVisible() {
            return this.timetableCalculationVisible && !this.timetableV2PageLoading
        },
        adoptedTimetableButtonCardVisible() {
            return this.adoptedTimetableVisible && !this.timetableV2PageLoading
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
        storedTimetableStudentInitials() {
            const [studentName = ''] = this.storedTimetableStudentLabel.split('·')
            const nameParts = studentName.trim().split(/\s+/u).filter(Boolean)

            return nameParts.slice(0, 2).map((namePart) => namePart.charAt(0)).join('').toUpperCase() || '–'
        },
        storedTimetableStudentEmail() {
            const storedEmail = String(this.storedTimetableStudentContext?.student?.email || '').trim()
            if (storedEmail) return storedEmail

            const studentCode = this.storedTimetableStudentCode
            const selectedStudent = studentCode
                ? (Array.isArray(this.robotStudents) ? this.robotStudents : [])
                    .find((student) => this.normalizedStudentCode(student?.student_code) === studentCode)
                : null

            return this.studentEmail(selectedStudent)
        },
        courseReviewStudentLabel() {
            if (!this.adoptedTimetableVisible) {
                return this.storedTimetableStudentContext ? this.storedTimetableStudentLabel : 'Ohne Studierenden'
            }

            const label = String(this.adoptedTimetableStudentContextSnapshot?.student?.label || '').trim()

            return label || 'Ohne Studierenden'
        },
        courseReviewStudentCode() {
            if (!this.adoptedTimetableVisible) return this.storedTimetableStudentCode

            return this.normalizedStudentCode(
                this.adoptedTimetableStudentContextSnapshot?.student?.studentCode
                    || this.storedTimetableStudentContext?.student?.studentCode,
            )
        },
        courseReviewStudentEmail() {
            const contextEmail = this.adoptedTimetableVisible
                ? String(
                    this.adoptedTimetableStudentContextSnapshot?.student?.email
                        || this.storedTimetableStudentContext?.student?.email
                        || '',
                ).trim()
                : this.storedTimetableStudentEmail

            if (contextEmail) return contextEmail

            const studentCode = this.courseReviewStudentCode
            const selectedStudent = studentCode
                ? (Array.isArray(this.robotStudents) ? this.robotStudents : [])
                    .find((student) => this.normalizedStudentCode(student?.student_code) === studentCode)
                : null

            return this.studentEmail(selectedStudent)
        },
        courseReviewStudentReligionMeta() {
            const contextReligion = this.adoptedTimetableVisible
                ? String(
                    this.adoptedTimetableStudentContextSnapshot?.student?.religion
                        || this.storedTimetableStudentContext?.student?.religion
                        || '',
                ).trim()
                : this.storedTimetableStudentReligion()

            if (contextReligion) return `Religion: ${contextReligion}`

            const studentCode = this.courseReviewStudentCode
            const selectedStudent = studentCode
                ? (Array.isArray(this.robotStudents) ? this.robotStudents : [])
                    .find((student) => this.normalizedStudentCode(student?.student_code) === studentCode)
                : null
            const religion = String(selectedStudent?.religion || '').trim()

            return religion ? `Religion: ${religion}` : null
        },
        adoptedPublishedTimetableStudentCode() {
            return this.normalizedStudentCode(
                this.adoptedTimetableStudentContextSnapshot?.student?.studentCode
                    || this.storedTimetableStudentContext?.student?.studentCode,
            )
        },
        adoptedPublishedTimetableStudentName() {
            const studentCode = this.adoptedPublishedTimetableStudentCode
            const selectedStudent = studentCode
                ? (Array.isArray(this.robotStudents) ? this.robotStudents : [])
                    .find((student) => this.normalizedStudentCode(student?.student_code) === studentCode)
                : null
            const studentName = [selectedStudent?.last_name, selectedStudent?.first_name]
                .map((value) => String(value || '').trim())
                .filter(Boolean)
                .join(' ')

            if (studentName) return studentName

            const label = String(this.adoptedTimetableStudentContextSnapshot?.student?.label || this.courseReviewStudentLabel || '').trim()
            const labelParts = label
                .split('·')
                .map((part) => part.trim())
                .filter(Boolean)

            if (labelParts.length >= 2) return labelParts[1]

            return label || 'Student'
        },
        adoptedTimetableSaveVisible() {
            return this.adoptedTimetableVisible
                && Boolean(this.selectedTimetableV2Result)
                && Boolean(this.adoptedPublishedTimetableStudentCode)
        },
        reviewFlowStudentIcon() {
            return this.courseReviewStudentLabel === 'Ohne Studierenden'
                ? 'mdi-account-off-outline'
                : 'mdi-account-school-outline'
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
        storedSemesterCourseItems() {
            if (!this.storedTimetableStudentContext) {
                return Array.isArray(this.noStudentPlannedCourseItems) ? this.noStudentPlannedCourseItems : []
            }

            const completedCourseCodes = this.courseCodeSet(this.storedCompletedCourseItems)
            const failedCourseCodes = this.courseCodeSet(this.storedMissingCourseItems)

            return this.sortedCourseItems(this.uniqueCourseItems(this.selectedStudentDefaultSemesterCourses())
                .filter((course) => !this.courseCodeSetContainsCourse(completedCourseCodes, course))
                .filter((course) => !this.courseCodeSetContainsCourse(failedCourseCodes, course))
                .filter((course) => !this.courseBlockedByNegativeCourse(course)))
        },
        visitedCourseItems() {
            return this.sortedCourseItems([
                ...this.storedCompletedCourseItems.map((course) => this.visitedCourseItem(course, 'completed')),
                ...this.storedMissingCourseItems.map((course) => this.visitedCourseItem(course, 'failed')),
            ])
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
                const courseCode = course.code || course.label
                const overviewCourse = overviewCoursesByCode.get(this.normalizedCourseCode(courseCode))
                const subjectRowCourse = this.subjectRowCourseForCourseCode(courseCode)
                const courseHours = this.courseHoursNumber(course)
                if (!overviewCourse && !subjectRowCourse && !courseHours) return null

                const hours = courseHours
                    || this.courseHoursNumber(overviewCourse)
                    || Number(subjectRowCourse?.hours || 0)
                const matchedCourseKey = String(overviewCourse?.key || subjectRowCourse?.key || course.key || '').trim()
                const matchedCourseCode = String(course.code || overviewCourse?.code || subjectRowCourse?.code || '').trim()
                const matchedCourseLabel = String(course.label || matchedCourseCode || overviewCourse?.label || subjectRowCourse?.label || '').trim()

                return {
                    ...course,
                    key: matchedCourseKey,
                    code: matchedCourseCode,
                    label: matchedCourseLabel,
                    hours,
                    hoursMeta: hours ? `${this.formatHours(hours)} Std.` : '',
                }
            }).filter(Boolean)
        },
        storedPlannedCourseItems() {
            if (!this.storedTimetableStudentContext) {
                return []
            }

            const courses = this.storedTimetableStudentContext?.courses || {}
            const additionalCourseItems = this.normalizedOverviewCourseItems(courses.additional || [])
            const negativeCourseCodes = new Set(this.storedMissingCourseCardItems
                .map((course) => this.normalizedCourseCode(course.code || course.label))
                .filter(Boolean))
            const completedCourseCodes = this.courseCodeSet(this.storedCompletedCourseItems)
            const semesterCourseCodes = this.courseCodeSet(this.storedSemesterCourseItems)

            return this.sortedCourseItems(this.uniqueCourseItems([
                ...this.normalizedOverviewCourseItems(courses.missing || []),
                ...this.normalizedOverviewCourseItems(courses.planned || []),
                ...this.selectedStudentPreviousSemesterCourses(),
                ...additionalCourseItems.filter((course) => this.courseDueBySelectedStudentSemester(course)),
            ])
                .filter((course) => !this.courseAfterSelectedStudentSemester(course))
                .filter((course) => !this.courseCodeSetContainsCourse(completedCourseCodes, course))
                .filter((course) => !this.courseCodeSetContainsCourse(negativeCourseCodes, course))
                .filter((course) => !this.courseCodeSetContainsCourse(semesterCourseCodes, course))
                .filter((course) => !this.courseBlockedByNegativeCourse(course)))
        },
        storedAdditionalCourseItems() {
            if (!this.storedTimetableStudentContext) {
                return this.noStudentAdditionalCourseItems
            }

            const courses = this.storedTimetableStudentContext?.courses || {}
            const additionalCourseItems = this.normalizedOverviewCourseItems(courses.additional || [])
            const dueAdditionalCourseCodes = this.courseCodeSet(
                additionalCourseItems.filter((course) => this.courseDueBySelectedStudentSemester(course)),
            )
            const completedCourseCodes = this.courseCodeSet(this.storedCompletedCourseItems)
            const negativeCourseCodes = this.courseCodeSet(this.storedMissingCourseCardItems)
            const selectedSemesterCourseCodes = this.courseCodeSet(this.selectedSemesterCourseLimitItems()
                .map((courseItem) => courseItem.course))
            const futurePlannedCourseItems = this.normalizedOverviewCourseItems([
                ...(courses.missing || []),
                ...(courses.planned || []),
            ]).filter((course) => this.courseAfterSelectedStudentSemester(course))

            return this.sortedCourseItems(this.uniqueCourseItems([
                ...additionalCourseItems,
                ...futurePlannedCourseItems,
            ])
                .filter((course) => !this.courseCodeSetContainsCourse(completedCourseCodes, course))
                .filter((course) => !this.courseCodeSetContainsCourse(negativeCourseCodes, course))
                .filter((course) => !this.courseCodeSetContainsCourse(dueAdditionalCourseCodes, course))
                .filter((course) => !this.courseCodeSetContainsCourse(selectedSemesterCourseCodes, course)))
        },
        selectedPlannedCourseItems() {
            return this.storedPlannedCourseItems.filter((course) => this.courseItemSelected(course, 'planned'))
        },
        selectedCompletedCourseItems() {
            return this.storedCompletedCourseItems.filter((course) => this.courseItemSelected(course, 'completed'))
        },
        selectedMissingCourseCardItems() {
            return this.storedMissingCourseCardItems.filter((course) => this.courseItemSelected(course, 'missing'))
        },
        selectedSemesterCourseItems() {
            return this.storedSemesterCourseItems.filter((course) => this.courseItemSelected(course, 'semester'))
        },
        selectedAdditionalCourseItems() {
            return this.storedAdditionalCourseItems.filter((course) => this.courseItemSelected(course, 'additional'))
        },
        moreCoursesCardItems() {
            const selectedCourseCodes = this.courseCodeSet(this.selectedCourseItems)
            const courseNotAlreadySelected = (course) => !this.courseCodeSetContainsCourse(selectedCourseCodes, course)

            return this.sortedCourseItems([
                ...this.storedCompletedCourseItems
                    .filter(courseNotAlreadySelected)
                    .map((course) => this.moreCoursesCardItem(course, 'completed')),
                ...this.storedMissingCourseCardItems
                    .filter(courseNotAlreadySelected)
                    .map((course) => this.moreCoursesCardItem(course, 'missing')),
                ...this.storedSemesterCourseItems
                    .filter(courseNotAlreadySelected)
                    .map((course) => this.moreCoursesCardItem(course, 'semester')),
                ...this.storedPlannedCourseItems
                    .filter(courseNotAlreadySelected)
                    .map((course) => this.moreCoursesCardItem(course, 'planned')),
                ...this.storedAdditionalCourseItems
                    .filter(courseNotAlreadySelected)
                    .map((course) => this.moreCoursesCardItem(course, 'additional')),
            ])
        },
        moreCoursesCardItemsBySelectionKey() {
            return new Map(this.moreCoursesCardItems
                .filter((course) => course.selectionKey)
                .map((course) => [course.selectionKey, course]))
        },
        moreCoursesCategoryCards() {
            return [
                {
                    courseGroups: ['completed'],
                    key: 'completed',
                    title: 'Abgeschlossene',
                },
                {
                    courseGroups: ['missing'],
                    key: 'missing',
                    title: 'Negative',
                },
                {
                    courseGroups: ['planned'],
                    key: 'planned',
                    title: 'Frühere',
                },
                {
                    courseGroups: ['semester'],
                    key: 'semester',
                    title: 'Aktuelle',
                },
                {
                    courseGroups: ['additional'],
                    key: 'additional',
                    title: 'Zusätzliche',
                },
            ].map((card) => {
                const items = this.moreCoursesCardItems
                    .filter((course) => card.courseGroups.includes(course.courseGroup))

                return {
                    ...card,
                    countLabel: `${this.formatNumber(items.length)} ${items.length === 1 ? 'Modul' : 'Module'}`,
                    items,
                }
            })
        },
        displayedMoreCoursesCategoryCards() {
            return this.moreCoursesCategoryCards.map((card) => ({
                ...card,
                disabled: this.moreCoursesCategoryCardDisabled(card),
            }))
        },
        displayedActiveMoreCoursesCategoryCard() {
            const displayedCards = this.displayedMoreCoursesCategoryCards
            let activeCard = displayedCards.find((card) => card.key === this.activeMoreCoursesCategoryCardKey)

            if (!activeCard && this.selectedMoreCourseItem) {
                const selectedCard = this.moreCoursesCategoryCardForCourse(this.selectedMoreCourseItem)
                activeCard = selectedCard
                    ? displayedCards.find((card) => card.key === selectedCard.key) || null
                    : null
            }

            if (!activeCard) return null

            const context = this.moreCoursesDisplayContext()

            return {
                ...activeCard,
                items: activeCard.items.map((course) => this.displayedMoreCourseCardItem(course, context)),
            }
        },
        moreCoursesTitle() {
            if (!this.activeMoreCoursesCategoryCard) return 'Mehr Module'

            return ['Mehr Module', this.activeMoreCoursesCategoryCard.title].filter(Boolean).join(': ')
        },
        activeMoreCoursesCategoryCard() {
            const activeCard = this.moreCoursesCategoryCards.find((card) => card.key === this.activeMoreCoursesCategoryCardKey)
            if (activeCard) return activeCard

            if (!this.selectedMoreCourseItem) return null

            return this.moreCoursesCategoryCardForCourse(this.selectedMoreCourseItem)
        },
        moreAdoptedCourseCards() {
            return [
                {
                    courseGroups: ['completed'],
                    key: 'completed',
                    title: 'Abgeschlossene',
                },
                {
                    courseGroups: ['missing'],
                    key: 'missing',
                    title: 'Negative',
                },
                {
                    courseGroups: ['semester', 'planned'],
                    key: 'planned',
                    title: 'Vorgesehene',
                },
                {
                    courseGroups: ['additional'],
                    key: 'additional',
                    title: 'Zusätzliche',
                },
                {
                    courseGroups: ['more'],
                    key: 'more',
                    title: 'Weitere',
                },
            ]
        },
        moreAdoptedCoursesTitle() {
            if (!this.activeMoreAdoptedCourseCard) return 'Weitere Module'

            return [
                'Weitere Module',
                this.activeMoreAdoptedCourseCard.title,
                this.selectedMoreExistingCourseBaseItem?.label,
                this.selectedMoreAdoptedCourseItem?.label,
            ].filter(Boolean).join(': ')
        },
        activeMoreAdoptedCourseCard() {
            return this.moreAdoptedCourseCards.find((card) => card.key === this.activeMoreAdoptedCourseCardKey)
                || null
        },
        moreAdoptedCourseCandidateItems() {
            if (!this.activeMoreAdoptedCourseCard) return []

            return this.moreAdoptedCourseCandidateItemsForCard(this.activeMoreAdoptedCourseCard)
        },
        moreAdoptedCourseItems() {
            if (this.activeMoreAdoptedCourseCard?.key !== 'more') return this.moreAdoptedCourseCandidateItems
            if (!this.selectedMoreExistingCourseBaseItem) return this.moreAdoptedCourseTopLevelItems

            return this.selectedMoreExistingCourseBaseItem.courses
        },
        moreAdoptedCourseTopLevelItems() {
            if (this.activeMoreAdoptedCourseCard?.key !== 'more') return []

            return this.moreExistingCourseTopLevelItems(this.moreAdoptedCourseCandidateItems)
        },
        selectedMoreExistingCourseBaseItem() {
            if (!this.activeMoreExistingCourseBaseKey) return null

            return this.moreAdoptedCourseTopLevelItems.find((course) => course.topLevelCourseKey === this.activeMoreExistingCourseBaseKey)
                || null
        },
        selectedMoreAdoptedCourseItem() {
            return this.moreAdoptedCourseItems.find((course) => course.selectionKey === this.selectedMoreCourseKey)
                || null
        },
        selectedMoreAdoptedCourseOfferedCourseItems() {
            if (!this.selectedMoreAdoptedCourseItem) return []

            return this.offeredCourseItemsForSelectedCourse(this.selectedMoreAdoptedCourseItem)
        },
        moreCourseAvailabilityCandidateItems() {
            return this.moreCoursesCardItems
        },
        moreCoursesButtonUnavailable() {
            return this.moreCoursesCardItems.length > 0
                && this.moreCourseAvailabilityComplete()
                && this.moreCoursesCardItems.every((course) => this.moreCourseUnavailable(course))
        },
        timetableOptionsUnavailable() {
            return Boolean(this.timetableCalculationResult)
                && this.timetableCalculationDisplayConflictResultCount > 0
                && this.timetableCalculationDisplayValidResultCount <= 0
        },
        timetableOptionsButtonUnavailable() {
            if (!this.timetableCalculationResult) return false
            if (this.timetableOptionsUnavailable) return true

            return [
                !this.timetableNoSaturdaySelected && this.noSaturdayTimetableCount > 0,
                !this.timetableStartsFromPeriod10Selected && this.startsFromPeriod10TimetableCount > 0,
                !this.timetableMaxFreeDaysSelected && this.maxFreeDaysTimetableCount > 0,
                !this.timetableNoDistanceLearningSelected && this.noDistanceLearningTimetableCount > 0,
            ].every((optionAvailable) => !optionAvailable)
        },
        selectedMoreCourseItem() {
            return this.moreCoursesCardItemsBySelectionKey.get(String(this.selectedMoreCourseKey || '').trim())
                || null
        },
        selectedMoreCourseOfferedCourseItems() {
            if (!this.selectedMoreCourseItem) return []

            const moreCourse = this.selectedMoreCourseItem
            const context = this.moreOfferedCourseSelectionContext(moreCourse)

            return this.offeredCourseItemsForSelectedCourse(moreCourse)
                .map((course) => this.selectedMoreCourseOfferedCourseItem(course, moreCourse, context))
        },
        moreOfferedCourseSelectionChanged() {
            return this.moreOfferedCourseSelectionSignature() !== this.moreOfferedCourseSelectionSnapshot
        },
        moreCoursesSelectionChanged() {
            return this.currentMoreCoursesSelectionSignature() !== this.moreCoursesSelectionSnapshot
                || (this.moreCoursesVisible && !this.moreCoursesCardVisible && this.hasPendingRemovedSelectedCourses)
                || this.timetableOptionsChanged
        },
        selectedCourseItems() {
            const selectedCourseItems = this.selectedCourseItemsForCourseSelections(this.currentCourseSelectionOverrides())

            if (!this.hasPendingRemovedSelectedCourses) return selectedCourseItems

            return selectedCourseItems.filter((course) => !this.pendingRemovedSelectedCourse(course))
        },
        selectedAdaptedOfferedCourseItems() {
            if (!this.courseReviewVisible) return []

            return this.selectedAdaptedOfferedCourseGroups
                .flatMap((group) => group.offers)
                .sort((firstOffer, secondOffer) => firstOffer.sortLabel.localeCompare(secondOffer.sortLabel, 'de-AT', {
                    numeric: true,
                    sensitivity: 'base',
                }))
        },
        selectedAdaptedOfferedCourseGroups() {
            if (!this.courseReviewVisible) return []

            const selectedCourses = Array.isArray(this.selectedCourseItems) ? this.selectedCourseItems : []

            return selectedCourses
                .map((course, courseIndex) => {
                    const label = this.courseDisplayLabel(course?.label || course?.code || '')
                    const offers = this.offeredCourseItemsForSelectedCourse(course)
                        .map((offeredCourse, index) => this.selectedAdaptedOfferedCourseItem(offeredCourse, course, index))
                        .sort((firstOffer, secondOffer) => firstOffer.sortLabel.localeCompare(secondOffer.sortLabel, 'de-AT', {
                            numeric: true,
                            sensitivity: 'base',
                        }))

                    return {
                        key: [
                            course?.selectionKey,
                            course?.key,
                            course?.code,
                            courseIndex,
                        ].filter((value) => String(value || '').trim()).join('|'),
                        label: label || 'Ohne Modul',
                        offers,
                        sortLabel: label || '',
                    }
                })
                .filter((group) => group.offers.length > 0)
                .sort((firstGroup, secondGroup) => firstGroup.sortLabel.localeCompare(secondGroup.sortLabel, 'de-AT', {
                    numeric: true,
                    sensitivity: 'base',
                }))
        },
        offerChoiceModuleItems() {
            if (!this.timetableCalculationVisible && !this.adoptedTimetableVisible) return []

            const selectedCourses = Array.isArray(this.selectedCourseItems) ? this.selectedCourseItems : []

            return selectedCourses
                .map((course, index) => {
                    const offers = this.offeredCourseItemsForSelectedCourse(course)
                    const selectedOfferCount = offers
                        .filter((offeredCourse) => this.offeredCourseSelectedForCalculation(offeredCourse, course))
                        .length

                    if (!selectedOfferCount) return null

                    const label = this.courseDisplayLabel(course?.label || course?.code || '')

                    return {
                        countLabel: offers.length > 0 ? `${selectedOfferCount}/${offers.length}` : String(selectedOfferCount),
                        course,
                        key: [
                            course?.selectionKey,
                            course?.key,
                            course?.code,
                            index,
                        ].filter((value) => String(value || '').trim()).join('|'),
                        label: label || 'Ohne Modul',
                        sortLabel: label || '',
                    }
                })
                .filter(Boolean)
                .sort((firstModule, secondModule) => firstModule.sortLabel.localeCompare(secondModule.sortLabel, 'de-AT', {
                    numeric: true,
                    sensitivity: 'base',
                }))
        },
        hasPendingRemovedSelectedCourses() {
            return Object.keys(this.pendingRemovedSelectedCourseKeys || {}).length > 0
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

            const offeredCourseSelections = this.currentOfferedCourseSelectionOverrides()
            const instructionFilters = this.instructionCourseFiltersForSelection(
                this.currentTimetableV2SelectionForCourseState(),
            )

            return this.offeredCourseItemsForSelectedCourse(this.selectedReviewCourseItem)
                .map((course) => this.selectedReviewOfferedCourseItem(course, {
                    instructionFilters,
                    offeredCourseSelections,
                    selectable: this.selectedCourseOfferItemsSelectable,
                }))
        },
        selectedCourseOfferCardVisible() {
            return Boolean(this.selectedReviewCourseItem)
                && this.courseReviewVisible
                && !this.moreCoursesVisible
                && !this.timetableOptionsCardVisible
        },
        selectedCourseOfferItemsSelectable() {
            return this.courseReviewVisible
        },
        storedMissingCourseCardSummary() {
            return this.courseItemsSummary(this.selectedMissingCourseCardItems)
        },
        storedSemesterCourseSummary() {
            return this.courseItemsSummary(this.selectedSemesterCourseItems)
        },
        storedCompletedCourseSummary() {
            return this.courseItemsSummary(this.selectedCompletedCourseItems)
        },
        storedPlannedCourseSummary() {
            return this.courseItemsSummary(this.selectedPlannedCourseItems)
        },
        storedAdditionalCourseSummary() {
            return this.courseItemsSummary(this.selectedAdditionalCourseItems)
        },
        selectedCourseSummary() {
            return this.courseItemsSummary([
                ...this.selectedCompletedCourseItems,
                ...this.selectedMissingCourseCardItems,
                ...this.selectedSemesterCourseItems,
                ...this.selectedPlannedCourseItems,
                ...this.selectedAdditionalCourseItems,
            ])
        },
        selectedCourseLimitSummary() {
            return this.courseItemsSummary([
                ...this.selectedCompletedCourseItems,
                ...this.selectedMissingCourseCardItems,
                ...this.selectedSemesterCourseItems,
                ...this.selectedPlannedCourseItems,
            ])
        },
        selectedCourseLimitReached() {
            return this.selectedCourseLimitSummary.count >= 10 || this.selectedCourseLimitSummary.hours >= 30
        },
        selectedCourseLimitExceeded() {
            return this.selectedCourseLimitSummary.count > 10 || this.selectedCourseLimitSummary.hours > 30
        },
        courseLimitPreselectionResetAvailable() {
            if (!this.courseCardsVisible || !this.courseLimitPreselectionSignature) return false

            const currentSelections = JSON.stringify(this.selectionSignatureEntries(this.courseSelectionOverrides))
            const preselectedSelections = JSON.stringify(this.selectionSignatureEntries(
                this.courseSelectionsForCourseLimitPreselection({}),
            ))

            return currentSelections !== preselectedSelections
        },
        timetableCalculationCountLabel() {
            const count = this.timetableCalculationDisplayTotalCount

            return `${this.formatNumber(count)} ${count === 1 ? 'Stundenplan' : 'Stundenpläne'} gesamt`
        },
        timetableCalculationLoadingLabel() {
            return this.timetableCalculationLoadingMode === 'timetable'
                ? 'Der Stundenplan wird geladen.'
                : 'Die Stundenpläne werden berechnet.'
        },
        timetableCalculationLoadingIcon() {
            return this.timetableCalculationLoadingMode === 'timetable'
                ? 'mdi-calendar-clock-outline'
                : 'mdi-calculator-variant-outline'
        },
        timetableCalculationProgressVisible() {
            return this.timetableCalculationVisible
                && Boolean(this.timetableCalculationProgressSource)
                && (
                    this.timetableCalculationLoading
                    || this.moreCourseAvailabilityLoading
                    || this.timetableQualityCountersLoading
                    || this.timetableCalculationProgressValue > 0
                )
        },
        timetableCalculationProgressValue() {
            return Math.max(0, Math.min(100, Math.round(Number(this.timetableCalculationProgress || 0))))
        },
        timetableCalculationProgressLabel() {
            if (this.timetableCalculationProgressFinalizing) return 'Fast fertig'

            return `${this.timetableCalculationProgressValue}%`
        },
        timetableCalculationProgressFinalizing() {
            return this.timetableCalculationProgressValue >= TIMETABLE_PROGRESS_FINALIZING_THRESHOLD
                && this.timetableCalculationProgressValue < 100
                && (
                    this.timetableCalculationLoading
                    || this.moreCourseAvailabilityLoading
                    || this.timetableQualityCountersLoading
                    || this.conflictResolutionRecommendationLoading
                    || this.timetableCalculationProgressCompletionPending
                )
        },
        timetableCalculationDisplayTotalCount() {
            if (this.timetableQualityCriteriaRequired) return this.selectedTimetableV2ResultCount

            return Number(this.currentTimetableV2CalculationResult?.timetable_variation_count || 0)
        },
        timetableQualityCriteriaRequired() {
            return this.timetableMaxFreeDaysSelected === true
                || this.timetableNoDistanceLearningSelected === true
        },
        noSaturdayTimetableCount() {
            if (this.timetableQualityCriteriaRequired && this.saturdayFreeQualityCounter) {
                return Number(this.saturdayFreeQualityCounter.count || 0)
            }

            return Number(this.currentTimetableV2CalculationResult?.no_saturday_timetable_count || 0)
        },
        noSaturdayTimetableCountFormatted() {
            return this.formatNumber(this.noSaturdayTimetableCount)
        },
        saturdayFreeQualityCounter() {
            return (Array.isArray(this.currentTimetableV2CalculationResult?.quality_counters)
                ? this.currentTimetableV2CalculationResult.quality_counters
                : []
            ).find((counter) => counter?.key === TIMETABLE_SATURDAY_FREE_CRITERION_KEY) || null
        },
        maxFreeDaysQualityCounter() {
            return (Array.isArray(this.currentTimetableV2CalculationResult?.quality_counters)
                ? this.currentTimetableV2CalculationResult.quality_counters
                : []
            ).find((counter) => counter?.key === TIMETABLE_MAX_FREE_DAYS_CRITERION_KEY) || null
        },
        noDistanceLearningQualityCounter() {
            return (Array.isArray(this.currentTimetableV2CalculationResult?.quality_counters)
                ? this.currentTimetableV2CalculationResult.quality_counters
                : []
            ).find((counter) => counter?.key === TIMETABLE_AVOID_DISTANCE_LEARNING_CRITERION_KEY) || null
        },
        startsFromPeriod10QualityCounter() {
            return (Array.isArray(this.currentTimetableV2CalculationResult?.quality_counters)
                ? this.currentTimetableV2CalculationResult.quality_counters
                : []
            ).find((counter) => counter?.key === TIMETABLE_STARTS_FROM_PERIOD_10_CRITERION_KEY) || null
        },
        maxFreeDaysTimetableCount() {
            return Number(this.maxFreeDaysQualityCounter?.count || 0)
        },
        maxFreeDaysTimetableCountFormatted() {
            return this.formatNumber(this.maxFreeDaysTimetableCount)
        },
        maxFreeDaysOptionMaximumLabel() {
            const bestValue = Number(this.maxFreeDaysQualityCounter?.best_value)

            if (Number.isFinite(bestValue)) {
                return `Max ${this.formatNumber(bestValue)} ${bestValue === 1 ? 'freier Tag' : 'freie Tage'}`
            }

            const bestLabel = String(this.maxFreeDaysQualityCounter?.best_label || '').trim()

            if (bestLabel && bestLabel !== '-') {
                return `Max ${bestLabel}`
            }

            return this.maxFreeDaysTimetableCountFormatted
        },
        maxFreeDaysSelectedOptionLabel() {
            const bestValue = Number(this.maxFreeDaysQualityCounter?.best_value)

            if (Number.isFinite(bestValue)) {
                return `Max ${this.formatNumber(bestValue)} ${bestValue === 1 ? 'freier Tag' : 'freie Tage'}`
            }

            const bestLabel = String(this.maxFreeDaysQualityCounter?.best_label || '').trim()

            if (bestLabel && bestLabel !== '-') {
                return `Max ${bestLabel}`
            }

            return 'Max freie Tage'
        },
        noDistanceLearningTimetableCount() {
            return Number(this.noDistanceLearningQualityCounter?.count || 0)
        },
        noDistanceLearningTimetableCountFormatted() {
            return this.formatNumber(this.noDistanceLearningTimetableCount)
        },
        startsFromPeriod10TimetableCount() {
            return Number(this.startsFromPeriod10QualityCounter?.count || 0)
        },
        startsFromPeriod10TimetableCountFormatted() {
            return this.formatNumber(this.startsFromPeriod10TimetableCount)
        },
        timetableOptionsChanged() {
            return this.timetableNoSaturdayDraftSelected !== this.timetableNoSaturdaySelected
                || this.timetableMaxFreeDaysDraftSelected !== this.timetableMaxFreeDaysSelected
                || this.timetableNoDistanceLearningDraftSelected !== this.timetableNoDistanceLearningSelected
                || this.timetableStartsFromPeriod10DraftSelected !== this.timetableStartsFromPeriod10Selected
        },
        timetableCalculationResetAvailable() {
            if (!this.timetableCalculationVisible) return false

            return this.timetableCalculationSelectionSnapshot(this.currentTimetableV2SelectionForCourseState())
                !== this.timetableCalculationSelectionSnapshot(this.initialTimetableCalculationSelection || {})
                || Number(this.timetableCalculationSelectedNumber || 1) !== Number(this.initialTimetableCalculationSelectedNumber || 1)
                || this.timetableNoSaturdaySelected
                || this.timetableMaxFreeDaysSelected
                || this.timetableNoDistanceLearningSelected
                || this.timetableStartsFromPeriod10Selected
        },
        selectedTimetableOptionItems() {
            const options = []
            const adoptedTimetableOptions = this.adoptedTimetableVisible
                && this.adoptedTimetableCalculationOptionsSnapshot
                && typeof this.adoptedTimetableCalculationOptionsSnapshot === 'object'
                && !Array.isArray(this.adoptedTimetableCalculationOptionsSnapshot)
                ? this.adoptedTimetableCalculationOptionsSnapshot
                : null
            const selectedTimetableOptions = adoptedTimetableOptions || {
                maxFreeDays: this.timetableMaxFreeDaysSelected,
                noDistanceLearning: this.timetableNoDistanceLearningSelected,
                noSaturday: this.timetableNoSaturdaySelected,
                startsFromPeriod10: this.timetableStartsFromPeriod10Selected,
            }
            const useDraftTimetableOptions = this.timetableOptionsChanged
                && (this.timetableOptionsCardVisible || (this.moreCoursesVisible && !this.moreCoursesCardVisible))
            const noSaturdaySelected = useDraftTimetableOptions
                ? this.timetableNoSaturdayDraftSelected
                : selectedTimetableOptions.noSaturday === true
            const maxFreeDaysSelected = useDraftTimetableOptions
                ? this.timetableMaxFreeDaysDraftSelected
                : selectedTimetableOptions.maxFreeDays === true
            const noDistanceLearningSelected = useDraftTimetableOptions
                ? this.timetableNoDistanceLearningDraftSelected
                : selectedTimetableOptions.noDistanceLearning === true
            const startsFromPeriod10Selected = useDraftTimetableOptions
                ? this.timetableStartsFromPeriod10DraftSelected
                : selectedTimetableOptions.startsFromPeriod10 === true

            if (noSaturdaySelected) {
                options.push({
                    key: 'no-saturday',
                    label: 'Kein Samstag',
                })
            }

            if (startsFromPeriod10Selected) {
                options.push({
                    key: 'starts-from-period-10',
                    label: 'Erst ab 10. Stunde',
                })
            }

            if (noDistanceLearningSelected) {
                options.push({
                    key: 'no-distance-learning',
                    label: 'Kein Fernunterricht',
                })
            }

            if (maxFreeDaysSelected) {
                options.push({
                    key: 'max-free-days',
                    label: this.maxFreeDaysSelectedOptionLabel,
                })
            }

            return options
        },
        selectedTimetableOptionsSummaryCardVisible() {
            return this.selectedTimetableOptionItems.length > 0
                || this.timetableCalculationVisible
                || this.adoptedTimetableVisible
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
            return Number(this.currentTimetableV2CalculationResult?.full_green_timetable_count || 0)
                + Number(this.currentTimetableV2CalculationResult?.green_timetable_count || 0)
        },
        timetableCalculationConflictResultCount() {
            return Number(this.currentTimetableV2CalculationResult?.conflict_timetable_count || this.currentTimetableV2CalculationResult?.red_timetable_count || 0)
        },
        timetableCalculationDisplayValidResultCount() {
            if (this.selectedTimetableV2HasErrorConflicts) return 0
            if (!this.timetableQualityCriteriaRequired) return this.timetableCalculationValidResultCount

            return this.selectedTimetableV2ResultType === 'conflict' ? 0 : this.selectedTimetableV2ResultCount
        },
        timetableCalculationDisplayConflictResultCount() {
            if (this.selectedTimetableV2HasErrorConflicts) return Math.max(1, this.selectedTimetableV2ResultCount)
            if (!this.timetableQualityCriteriaRequired) return this.timetableCalculationConflictResultCount

            return this.selectedTimetableV2ResultType === 'conflict' ? this.selectedTimetableV2ResultCount : 0
        },
        timetableCalculationUnavailableLabel() {
            if (this.timetableCalculationDisplayValidResultCount <= 0 && this.timetableCalculationDisplayConflictResultCount > 0) {
                return 'Es wurden nur Stundenpläne mit Überschneidung gefunden.'
            }

            return 'Es wurde kein gültiger Stundenplan gefunden.'
        },
        timetableCalculationResultStatusLabel() {
            return this.selectedTimetableV2Result
                ? 'Die Stundenpläne wurden erfolgreich erstellt.'
                : this.timetableCalculationUnavailableLabel
        },
        timetableCalculationResultAlertType() {
            return this.selectedTimetableV2Result ? 'success' : 'warning'
        },
        timetableCalculationResultAlertIcon() {
            return this.selectedTimetableV2Result ? 'mdi-check-circle-outline' : 'mdi-calendar-alert-outline'
        },
        selectedTimetableV2Result() {
            const selectedTimetable = this.currentTimetableV2CalculationResult?.selected_timetable

            return selectedTimetable && typeof selectedTimetable === 'object' && !Array.isArray(selectedTimetable)
                ? selectedTimetable
                : null
        },
        currentTimetableV2CalculationResult() {
            return this.adoptedTimetableVisible
                ? this.adoptedTimetableCalculationResult
                : this.timetableCalculationResult
        },
        adoptedTimetableNumberLabel() {
            return `Finaler Stundenplan Nr. ${this.formatNumber(this.adoptedTimetableSelectedNumber)}`
        },
        timetableCalculationCardTitleLabel() {
            return this.adoptedTimetableVisible
                ? this.adoptedTimetableNumberLabel
                : 'Stundenpläne'
        },
        adoptSelectedTimetableV2ButtonLabel() {
            return `Stundenplan Nr. ${this.formatNumber(this.timetableCalculationSelectedNumber)} übernehmen`
        },
        selectedTimetableV2ResultType() {
            const type = String(this.selectedTimetableV2Result?.type || '').trim()

            if (this.selectedTimetableV2HasErrorConflicts) return 'conflict'

            return ['full_green', 'green', 'conflict'].includes(type) ? type : 'full_green'
        },
        selectedTimetableV2TitleLabel() {
            return this.selectedTimetableV2ResultType === 'conflict'
                ? 'Stundenplan mit Überschneidung'
                : ''
        },
        selectedTimetableV2RestartButtonInHeaderVisible() {
            return Boolean(this.selectedTimetableV2Result)
                && (this.timetableCalculationVisible || this.adoptedTimetableVisible)
        },
        selectedTimetableV2ConflictSeverity() {
            const conflictPairs = this.selectedTimetableV2ConflictPairs()
            if (!conflictPairs.length) {
                return this.selectedTimetableV2ApparentOverlapSummaryItems.length ? 'info' : ''
            }

            return conflictPairs.length > 0
                && conflictPairs.every((pair) => this.selectedTimetableV2ConflictPairIsOccasional(pair))
                ? 'warning'
                : 'error'
        },
        selectedTimetableV2HasErrorConflicts() {
            return this.selectedTimetableV2ConflictPairs()
                .some((pair) => !this.selectedTimetableV2ConflictPairIsOccasional(pair))
        },
        selectedTimetableV2ConflictIcon() {
            if (this.selectedTimetableV2ConflictSeverity === 'info') return 'mdi-information-outline'

            return this.selectedTimetableV2ConflictSeverity === 'warning'
                ? 'mdi-alert-outline'
                : 'mdi-alert-circle-outline'
        },
        selectedTimetableV2ConflictTitle() {
            if (this.selectedTimetableV2ConflictSeverity === 'info') return 'Hinweise'

            return this.selectedTimetableV2ConflictSeverity === 'warning'
                ? 'Überschneidungen'
                : 'Konflikte'
        },
        selectedTimetableV2ConflictSummaryItems() {
            const slotConflictLabels = this.uniqueValues([...this.selectedTimetableV2SlotEntries]
                .sort((firstEntry, secondEntry) =>
                    Number(firstEntry.weekday || 0) - Number(secondEntry.weekday || 0)
                    || Number(firstEntry.hour || 0) - Number(secondEntry.hour || 0))
                .flatMap((entry) => [
                    ...this.selectedTimetableV2SlotConflicts(entry.slot),
                    ...this.selectedTimetableV2RegularSameSlotConflicts(entry.slot),
                ].map((conflict) => this.selectedTimetableV2ConflictSummaryLabel(entry.slot, conflict, {
                        slotHour: entry.hour,
                        slotWeekday: entry.weekday,
                    })))
                .map((conflict) => String(conflict || '').trim())
                .filter(Boolean))

            if (slotConflictLabels.length) {
                return slotConflictLabels
            }

            if (this.selectedTimetableV2ApparentOverlapSummaryItems.length) {
                return this.selectedTimetableV2ApparentOverlapSummaryItems
            }

            if (!this.selectedTimetableV2ConflictPairs().length) return []

            const problems = Array.isArray(this.selectedTimetableV2Result?.problems)
                ? this.selectedTimetableV2Result.problems
                : []

            return this.uniqueValues(problems
                .map((problem) => String(problem || '').trim())
                .filter(Boolean))
        },
        selectedTimetableV2ApparentOverlapSummaryItems() {
            return this.uniqueValues([...this.selectedTimetableV2SlotEntries]
                .sort((firstEntry, secondEntry) =>
                    Number(firstEntry.weekday || 0) - Number(secondEntry.weekday || 0)
                    || Number(firstEntry.hour || 0) - Number(secondEntry.hour || 0))
                .flatMap((entry) => this.selectedTimetableV2DisplaySameSlotEntries(entry.slot)
                    .filter((sameSlotEntry) => this.selectedTimetableV2SlotDateRangeBounds(entry.slot)
                        && this.selectedTimetableV2SlotDateRangeBounds(sameSlotEntry)
                        && !this.selectedTimetableV2SlotDateRangesOverlap(entry.slot, sameSlotEntry))
                    .map((sameSlotEntry) => this.selectedTimetableV2ApparentOverlapSummaryLabel(entry.slot, sameSlotEntry, {
                        slotHour: entry.hour,
                        slotWeekday: entry.weekday,
                    })))
                .map((summary) => String(summary || '').trim())
                .filter(Boolean))
        },
        selectedTimetableV2ConflictResolutionOptions() {
            const optionsBySelectionKey = new Map()
            const conflictPairs = this.selectedTimetableV2ConflictPairs()
                .filter((pair) => !this.selectedTimetableV2ConflictPairIsOccasional(pair))

            conflictPairs
                .forEach((pair) => {
                    [pair.slot, pair.conflict].forEach((slot) => {
                        const offeredCourse = this.selectedTimetableV2OfferedCourseForSlot(slot)
                        const offeredCourseSelectionKey = offeredCourse?.selectionKey || ''
                        if (!offeredCourseSelectionKey || !this.offeredCourseSelected(offeredCourse)) return

                        const selectedCourse = this.selectedTimetableV2SelectedCourseForOfferedCourse(offeredCourse)
                        const selectionKey = selectedCourse?.selectionKey || ''
                        if (!selectionKey) return

                        if (!optionsBySelectionKey.has(selectionKey)) {
                            const courseLabel = String(selectedCourse?.label || selectedCourse?.code || '').trim()

                            optionsBySelectionKey.set(selectionKey, {
                                conflictKeys: new Set(),
                                label: courseLabel || this.selectedTimetableV2CourseProblemLabel(slot) || offeredCourse.groupSelectionLabel || offeredCourse.name,
                                selectedCourse,
                                selectionKey,
                            })
                        }

                        optionsBySelectionKey.get(selectionKey).conflictKeys.add(pair.key)
                    })
                })

            return [...optionsBySelectionKey.values()]
                .map((option) => {
                    const count = option.conflictKeys.size
                    const targetLabel = option.label
                    const recommended = this.conflictResolutionRecommendationByKey?.[option.selectionKey] === true

                    return {
                        actionLabel: 'Modul entfernen',
                        actionType: 'course',
                        buttonLabel: recommended
                            ? `${targetLabel} Entfernen · alle Konflikte`
                            : `${targetLabel} Entfernen · ${this.formatNumber(count)} ${count === 1 ? 'Konflikt' : 'Konflikte'}`,
                        color: 'error',
                        count,
                        countLabel: `${this.formatNumber(count)} ${count === 1 ? 'Konflikt' : 'Konflikte'} betroffen`,
                        effectLabel: 'vollständig entfernen',
                        icon: 'mdi-close-circle-outline',
                        label: option.label,
                        recommended,
                        selectedCourse: option.selectedCourse,
                        selectionKey: option.selectionKey,
                        targetLabel,
                    }
                })
                .filter((option) => option.count > 0)
                .sort((firstOption, secondOption) => {
                    if (firstOption.recommended !== secondOption.recommended) {
                        return firstOption.recommended ? -1 : 1
                    }

                    if (firstOption.count !== secondOption.count) return secondOption.count - firstOption.count

                    return firstOption.label.localeCompare(secondOption.label, 'de-AT', {
                        numeric: true,
                        sensitivity: 'base',
                    })
                })
        },
        selectedTimetableV2ConflictResolutionActionsVisible() {
            const recommendationSignature = this.conflictResolutionRecommendationCurrentSignature()

            return this.selectedTimetableV2ConflictResolutionOptions.length > 0
                && Boolean(recommendationSignature)
                && !this.conflictResolutionRecommendationLoading
                && this.conflictResolutionRecommendationSignature === recommendationSignature
        },
        selectedTimetableV2ProblemCourseItems() {
            const problemCourses = Array.isArray(this.currentTimetableV2CalculationResult?.problem_courses)
                ? this.currentTimetableV2CalculationResult.problem_courses
                : []

            return problemCourses
                .map((problemCourse, index) => {
                    const key = String(problemCourse?.key || '').trim()
                    const code = String(problemCourse?.code || '').trim()
                    const selectedCourse = this.selectedCourseItems
                        .find((course) => key && String(course?.key || '').trim() === key)
                        || this.selectedCourseItems
                            .find((course) => code && String(course?.code || '').trim() === code)
                        || null
                    const label = this.courseDisplayLabel(
                        selectedCourse?.label
                        || problemCourse?.label
                        || problemCourse?.name
                        || code
                        || 'Modul',
                    )

                    return {
                        key: key || selectedCourse?.selectionKey || code || `problem-course-${index}`,
                        buttonLabel: `${label} entfernen`,
                        code,
                        label,
                        reason: String(problemCourse?.reason || '').trim(),
                        reasonLabel: String(problemCourse?.reason_label || '').trim(),
                        selectedCourse,
                    }
                })
                .filter((problemCourse) => problemCourse.label)
        },
        selectedTimetableV2ProblemCourseActionsVisible() {
            return this.selectedTimetableV2ProblemCourseItems
                .some((problemCourse) => Boolean(problemCourse.selectedCourse))
        },
        selectedTimetableV2NoResultResolutionCourseItems() {
            if (this.selectedTimetableV2Result || !this.timetableCalculationResult) return []
            if (this.selectedTimetableV2ProblemCourseItems.length) return []

            const selectedCourses = this.selectedCourseItems
                .filter((course) => Boolean(course?.selectionKey || this.courseSelectionKey(course, course?.courseGroup)))
            const constrainedCourses = selectedCourses
                .filter((course) => this.offeredCourseItemsPartlySelected(course) || this.offeredCourseItemsAllDeselected(course))
            const courses = constrainedCourses.length ? constrainedCourses : selectedCourses

            return courses
                .map((course, index) => {
                    const label = this.courseDisplayLabel(course?.label || course?.code || course?.name || 'Modul')
                    const key = String(course?.selectionKey || this.courseSelectionKey(course, course?.courseGroup) || `course-${index}`).trim()

                    return {
                        key,
                        buttonLabel: `${label} entfernen`,
                        label,
                        selectedCourse: course,
                    }
                })
                .filter((course) => course.label && course.key)
        },
        selectedTimetableV2NoResultResolutionOptionItems() {
            if (this.selectedTimetableV2Result || !this.timetableCalculationResult) return []

            return this.selectedTimetableOptionItems
                .filter((option) => ['no-saturday', 'max-free-days', 'no-distance-learning', 'starts-from-period-10'].includes(option?.key))
                .map((option) => ({
                    ...option,
                    buttonLabel: `${option.label} aufheben`,
                }))
        },
        selectedTimetableV2NoResultResolutionActionsVisible() {
            return this.selectedTimetableV2NoResultResolutionCourseItems.length > 0
                || this.selectedTimetableV2NoResultResolutionOptionItems.length > 0
        },
        selectedTimetableV2StatusLabel() {
            return String(this.selectedTimetableV2Result?.statusMessage || '').trim()
        },
        selectedTimetableV2ResultCount() {
            if (this.selectedTimetableV2HasErrorConflicts) {
                return Math.max(
                    1,
                    Number(this.currentTimetableV2CalculationResult?.selected_quality_criteria_count || 0),
                    this.timetableCalculationConflictResultCount,
                )
            }

            if (this.timetableQualityCriteriaRequired) {
                const selectedQualityCriteriaCount = Number(this.currentTimetableV2CalculationResult?.selected_quality_criteria_count || 0)

                if (this.selectedTimetableV2ResultType === 'conflict' && selectedQualityCriteriaCount <= 0) {
                    return this.timetableCalculationConflictResultCount
                }

                return Math.max(0, selectedQualityCriteriaCount)
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
        selectedTimetableV2CourseCodes() {
            return new Set(this.uniqueValues(this.selectedTimetableV2SlotEntries
                .flatMap((entry) => this.selectedTimetableV2CourseCodeItems(entry.slot))
                .map((courseCode) => this.normalizedCourseCode(courseCode))
                .filter(Boolean)))
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
                ...this.storedSemesterCourseItems
                    .map((course) => this.courseLimitPreselectionSignaturePart(course, 'semester')),
                ...this.storedPlannedCourseItems
                    .map((course) => this.courseLimitPreselectionSignaturePart(course, 'planned')),
            ]

            if (!courseItems.length) return ''

            return JSON.stringify({
                maxCourses: 10,
                maxHours: 30,
                courseItems,
            })
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

            return this.robotStudents
                .filter((student) => [
                    this.studentOptionTitle(student),
                    this.studentEmail(student),
                ].join(' ').toLowerCase().includes(this.normalizedStudentSearch))
                .sort(this.compareStudentsByName)
        },
        studentTotalCountLabel() {
            const count = Array.isArray(this.robotStudents) ? this.robotStudents.length : 0

            return `${this.formatNumber(count)} Studenten gesamt`
        },
        storedTimetableV2Selection() {
            return this.storedTimetableState?.timetableV2Selection || {}
        },
        storedAdaptedTimetableV2Selection() {
            const selection = this.storedTimetableState?.adaptedTimetableV2Selection

            return selection && typeof selection === 'object' && !Array.isArray(selection)
                ? selection
                : null
        },
        storedTimetableV2Options() {
            const options = this.storedTimetableState?.timetableV2Options

            return options && typeof options === 'object' && !Array.isArray(options)
                ? options
                : {}
        },
        storedCourseSelectionOverrides() {
            return this.courseSelectionOverridesForSelection(this.storedTimetableV2Selection)
        },
        courseSelectionOverrides() {
            return this.draftCourseSelections && typeof this.draftCourseSelections === 'object' && !Array.isArray(this.draftCourseSelections)
                ? this.draftCourseSelections
                : this.storedCourseSelectionOverrides
        },
        courseSelectionDraftChanged() {
            if (!this.draftCourseSelections || typeof this.draftCourseSelections !== 'object' || Array.isArray(this.draftCourseSelections)) {
                return false
            }

            return this.courseSelectionSignature(this.draftCourseSelections)
                !== this.courseSelectionSignature(this.storedCourseSelectionOverrides)
        },
        offeredCourseSelectionOverrides() {
            return this.offeredCourseSelectionOverridesForSelection(this.storedTimetableV2Selection)
        },
        moreOfferedCourseSelectionOverrides() {
            return this.moreOfferedCourseSelectionOverridesForSelection(this.storedTimetableV2Selection)
        },
        noStudentSelectedSemester() {
            const semester = Number(this.storedTimetableV2Selection.semester || 0)

            return Number.isFinite(semester) && semester > 0 ? semester : null
        },
        effectiveTimetableV2Selection() {
            return this.timetableV2SelectionWithCourseDefaults({
                ...this.defaultNoStudentTimetableV2Selection(),
                ...this.storedTimetableV2Selection,
            }, this.storedTimetableStudentContext?.courses || {})
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
        '$route.query.moreCourses'() {
            this.applyTimetableV2RouteFromRoute()
        },
        '$route.query.moreCourse'() {
            this.applyTimetableV2RouteFromRoute()
        },
        filteredStudentResults(students) {
            if (this.studentSelectionExplicitlyCleared) return

            const draftedStudentCode = this.normalizedStudentCode(this.studentSelectionDraft.studentCode)
            const draftedStudentStillVisible = draftedStudentCode !== null
                && students.some((student) => this.normalizedStudentCode(student?.student_code) === draftedStudentCode)

            if (draftedStudentStillVisible) return

            const [firstStudent] = students

            if (!firstStudent) return

            this.selectStudentDraft(firstStudent.student_code)
        },
    },

    async mounted() {
        try {
            await this.loadTimetableV2SelectionBootstrap('', {
                applyStoredState: true,
                persistStudentOverviewState: false,
            })
            this.syncStoredTimetableOptions()
            this.applyTimetableV2RouteFromRoute({ restoreEffects: false })
            this.syncTimetableV2Route({ replace: true })
            const subjectRowsPromise = Promise.resolve()
            const courseGroupsPromise = Promise.resolve()
            const schoolHoursPromise = this.courseReviewVisible || this.timetableCalculationVisible || this.adoptedTimetableVisible
                ? this.loadSchoolHours()
                : null
            if (this.storedTimetableStudentCode && !this.storedTimetableStudentEmail) {
                this.loadRobotStudents()
            }
            const routeEffectsPromise = this.restoreTimetableV2RouteStepEffects({
                subjectRowsPromise,
                courseGroupsPromise,
                schoolHoursPromise,
            })

            await Promise.all([
                subjectRowsPromise,
                courseGroupsPromise,
                schoolHoursPromise,
                routeEffectsPromise,
            ].filter(Boolean))
        } finally {
            this.initialRouteLoading = false
        }
    },

    beforeUnmount() {
        this.flushPendingStoredTimetableStateSave()
        this.clearTimetableCalculationProgressTimers()
        this.clearCopyStudentEmailResetTimeout()
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
            this.clearAdoptedTimetableV2Result()
            this.clearAdoptedPublishedTimetableReport()
            this.timetableCalculationSelectedNumber = 1
            this.studentDialogOpen = false
            this.studentSearch = ''
            this.studentSelectionDraft = {
                studentCode: null,
            }
            this.studentSelectionExplicitlyCleared = false
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

            this.persistCourseSelectionsForCourseReview()
            this.selectedReviewCourseKey = this.selectedReviewCourseItem?.selectionKey || ''
            this.setTimetableV2Step('course-review')
            this.loadCourseGroups?.()
            this.loadSchoolHours?.()
        },
        openTimetableCalculation() {
            this.selectedReviewCourseKey = ''
            this.timetableCalculationSelectedNumber = 1
            this.clearAdoptedTimetableV2Result()
            this.persistCourseSelectionsForCourseReview()
            this.setTimetableV2Step('timetable-calculation')
            return this.calculateTimetables()
        },
        adoptCurrentTimetableV2Result() {
            if (this.timetableCalculationLoading || !this.selectedTimetableV2Result || !this.timetableCalculationResult) return

            this.closeMoreCoursesCard()
            this.closeTimetableOptionsCard()
            this.freezeCurrentTimetableV2ResultForAdoption()
            this.setTimetableV2Step('timetable-adoption')
        },
        freezeCurrentTimetableV2ResultForAdoption() {
            if (!this.timetableCalculationResult?.selected_timetable) return

            this.adoptedTimetableCalculationResult = this.clonedTimetableV2Value(this.timetableCalculationResult)
            this.adoptedTimetableSelectedNumber = this.timetableCalculationSelectedNumber
            this.adoptedTimetableCalculationSelectionSnapshot = this.clonedTimetableV2Selection(this.currentTimetableV2SelectionForCourseState())
            this.adoptedTimetableCalculationOptionsSnapshot = this.clonedTimetableV2Value(this.timetableV2OptionsForSaving())
            this.adoptedTimetableSelectionSnapshot = this.clonedTimetableV2Selection(this.currentTimetableV2SelectionForCourseState())
            this.adoptedTimetableStudentContextSnapshot = this.clonedTimetableV2Value(this.storedTimetableStudentContext)
            this.saveAdoptedTimetableStateSnapshot()
        },
        clearAdoptedTimetableV2Result() {
            this.adoptedTimetableCalculationResult = null
            this.adoptedTimetableSelectedNumber = 1
            this.adoptedTimetableCalculationSelectionSnapshot = null
            this.adoptedTimetableCalculationOptionsSnapshot = null
            this.adoptedTimetableSelectionSnapshot = null
            this.adoptedTimetableStudentContextSnapshot = null
        },
        backToCourseReview() {
            this.persistCalculationSelectionForCourseReview()
            this.setTimetableV2Step('course-review')
        },
        backToTimetableCalculation() {
            this.closeMoreCoursesCard()
            this.clearPendingRemovedSelectedCourses()
            this.setTimetableV2Step('timetable-calculation')
            this.restoreAdoptedTimetableCalculationSelection()
        },
        restoreAdoptedTimetableCalculationSelection() {
            if (!this.adoptedTimetableCalculationSelectionSnapshot) return

            const timetableV2Selection = this.clonedTimetableV2Selection(this.adoptedTimetableCalculationSelectionSnapshot)
            const timetableV2Options = this.clonedTimetableV2Value(
                this.adoptedTimetableCalculationOptionsSnapshot || this.timetableV2OptionsForSaving(),
            )

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                adaptedTimetableV2Selection: timetableV2Selection,
                timetableV2Options,
                transferredStudentContext: this.storedTimetableStudentContext,
            })
            this.calculationTimetableV2Selection = this.clonedTimetableV2Selection(timetableV2Selection)
            this.applyTimetableV2OptionState(timetableV2Options)
            this.initialTimetableCalculationSelection = this.clonedTimetableV2Selection(timetableV2Selection)
            this.initialTimetableCalculationMaxFreeDaysSelected = this.timetableMaxFreeDaysSelected
            this.initialTimetableCalculationNoDistanceLearningSelected = this.timetableNoDistanceLearningSelected
            this.initialTimetableCalculationNoSaturdaySelected = this.timetableNoSaturdaySelected
            this.initialTimetableCalculationStartsFromPeriod10Selected = this.timetableStartsFromPeriod10Selected
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
        openAdoptedTimetablePrintDialog() {
            if (this.pdfExporting || !this.selectedTimetableV2Result) return

            this.printDialogVisible = true
        },
        closeAdoptedTimetablePrintDialog() {
            if (this.pdfExporting) return

            this.printDialogVisible = false
        },
        async downloadAdoptedTimetablePdf() {
            if (this.pdfExporting || !this.selectedTimetableV2Result) return

            this.pdfExporting = true

            try {
                const response = await axios.post(
                    '/api/admin/students-timetables/overview/pdf',
                    this.adoptedTimetablePdfPayload(),
                    { responseType: 'blob' },
                )
                const fileName = this.fileNameFromContentDisposition(response?.headers?.['content-disposition'])
                    || 'stundenplan.pdf'
                const blob = response?.data instanceof Blob
                    ? response.data
                    : new Blob([response?.data], { type: 'application/pdf' })

                this.downloadBlob(blob, fileName)
                this.printDialogVisible = false
            } catch (error) {
                console.error(error)
                window.alert?.('PDF konnte nicht erstellt werden.')
            } finally {
                this.pdfExporting = false
            }
        },
        async saveAdoptedPublishedStudentTimetable() {
            if (
                this.publishedTimetableSaving
                || !this.adoptedPublishedTimetableStudentCode
                || !this.selectedTimetableV2Result
            ) {
                return
            }

            this.publishedTimetableSaving = true
            this.clearAdoptedPublishedTimetableReport()

            try {
                const response = await axios.post(
                    '/api/admin/students-timetables/overview/student-timetable',
                    this.adoptedPublishedStudentTimetablePayload(),
                )

                this.adoptedPublishedTimetableReport = {
                    type: 'success',
                    message: response?.data?.message || 'Stundenplan wurde gespeichert.',
                }
                this.markAdoptedPublishedTimetable(response?.data?.data || {})
            } catch (error) {
                this.adoptedPublishedTimetableReport = {
                    type: 'error',
                    message: error?.response?.data?.message || 'Stundenplan konnte nicht gespeichert werden.',
                }
            } finally {
                this.publishedTimetableSaving = false
            }
        },
        clearAdoptedPublishedTimetableReport() {
            this.adoptedPublishedTimetableReport = {
                type: 'success',
                message: '',
            }
        },
        adoptedPublishedStudentTimetablePayload() {
            return {
                student_code: this.adoptedPublishedTimetableStudentCode,
                student_label: this.adoptedPublishedTimetableStudentName,
                timetable: this.adoptedTimetablePdfPayload(false),
                state: this.adoptedPublishedStudentTimetableState(),
            }
        },
        adoptedPublishedStudentTimetableState() {
            return {
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                source: 'timetable-v2',
                timetableV2Step: 'timetable-adoption',
                timetableV2Selection: this.clonedTimetableV2Selection(
                    this.adoptedTimetableSelectionSnapshot || this.storedTimetableV2Selection,
                ),
                timetableV2Options: this.clonedTimetableV2Value(
                    this.adoptedTimetableCalculationOptionsSnapshot || this.timetableV2OptionsForSaving(),
                ),
                timetableV2SelectedNumber: this.adoptedTimetableSelectedNumber,
                transferredStudentContext: this.clonedTimetableV2Value(
                    this.adoptedTimetableStudentContextSnapshot || this.storedTimetableStudentContext,
                ),
            }
        },
        adoptedTimetableStateSnapshot() {
            if (!this.adoptedTimetableCalculationResult?.selected_timetable) return null

            return {
                adoptedCalculationResult: this.clonedTimetableV2Value(this.adoptedTimetableCalculationResult),
                calculationOptions: this.clonedTimetableV2Value(
                    this.adoptedTimetableCalculationOptionsSnapshot || this.timetableV2OptionsForSaving(),
                ),
                calculationResult: this.clonedTimetableV2Value(
                    this.timetableCalculationResult || this.adoptedTimetableCalculationResult,
                ),
                calculationSelection: this.clonedTimetableV2Selection(
                    this.adoptedTimetableCalculationSelectionSnapshot || this.storedTimetableV2Selection,
                ),
                selectedNumber: this.adoptedTimetableSelectedNumber,
                selection: this.clonedTimetableV2Selection(
                    this.adoptedTimetableSelectionSnapshot || this.storedTimetableV2Selection,
                ),
                studentContext: this.clonedTimetableV2Value(
                    this.adoptedTimetableStudentContextSnapshot || this.storedTimetableStudentContext,
                ),
            }
        },
        saveAdoptedTimetableStateSnapshot() {
            const timetableV2Adoption = this.adoptedTimetableStateSnapshot()
            if (!timetableV2Adoption) return

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Adoption,
            })
        },
        markAdoptedPublishedTimetable(publishedTimetable) {
            const studentCode = this.normalizedStudentCode(
                publishedTimetable?.student_code || this.adoptedPublishedTimetableStudentCode,
            )
            if (!studentCode) return

            this.robotStudents = (Array.isArray(this.robotStudents) ? this.robotStudents : []).map((student) => {
                if (this.normalizedStudentCode(student?.student_code) !== studentCode) return student

                return {
                    ...student,
                    has_published_timetable: true,
                    published_timetable_id: publishedTimetable?.id || student.published_timetable_id || null,
                    published_timetable_at: publishedTimetable?.published_at || student.published_timetable_at || null,
                }
            })
        },
        adoptedTimetablePdfPayload(includePrintOptions = true) {
            const payload = {
                title: 'Stundenplan',
                subtitle: '',
                schoolyear: this.selectedSchoolyear?.name || this.selectedSchoolyear?.label || '',
                student: this.courseReviewStudentLabel || '',
                generated_at: new Intl.DateTimeFormat('de-AT', {
                    dateStyle: 'short',
                    timeStyle: 'short',
                }).format(new Date()),
                weekdays: this.selectedTimetableV2Weekdays.map((weekday) => ({
                    label: weekday.shortTitle || weekday.label || '',
                })),
                semesters: [this.adoptedTimetablePdfSemesterPayload()],
            }

            if (includePrintOptions) {
                payload.print_options = this.adoptedTimetablePdfPrintOptionsPayload()
            }

            return payload
        },
        adoptedTimetablePdfPrintOptionsPayload() {
            return {
                single_weeks: this.printOptions?.singleWeeks === true,
                course_list: this.printOptions?.courseList !== false,
                course_overview: this.printOptions?.courseOverview !== false,
            }
        },
        adoptedTimetablePdfSemesterPayload() {
            return {
                label: this.adoptedTimetablePdfSemesterLabel(),
                date_range: '',
                weeks: [
                    {
                        label: '',
                        hours: this.selectedTimetableV2Times
                            .map((hour) => this.adoptedTimetablePdfHourPayload(hour)),
                    },
                ],
            }
        },
        adoptedTimetablePdfSemesterLabel() {
            const semester = this.semesterValueFromLabel(this.adoptedTimetableStudentContextSnapshot?.student?.semesterLabel)
                || Number(this.adoptedTimetableSelectionSnapshot?.semester || 0)
                || Number(this.storedTimetableV2Selection?.semester || 0)

            return semester ? `Semester ${semester}` : 'Finaler Stundenplan'
        },
        adoptedTimetablePdfHourPayload(hour) {
            return {
                hour: Number(hour.value),
                from: hour.timeFrom || '',
                until: hour.timeUntil || '',
                cells: this.selectedTimetableV2Weekdays.map((weekday) => (
                    this.adoptedTimetablePdfCellPayload(weekday.value, hour.value)
                )),
            }
        },
        adoptedTimetablePdfCellPayload(weekday, hour) {
            const slot = this.selectedTimetableV2Slot(weekday, hour)

            if (!slot) {
                return {
                    status: 'empty',
                    courses: [],
                    markers: [],
                }
            }

            const conflicts = this.selectedTimetableV2SlotConflicts(slot)
            const courseItemsByKey = new Map()
            const overlapItems = [
                slot,
                ...this.selectedTimetableV2DisplaySameSlotEntries(slot),
                ...conflicts,
            ].filter(Boolean)
            const hasRegularOverlapItem = overlapItems.some((courseItem) => !this.selectedTimetableV2SlotIsOccasional(courseItem))
            const courseItems = hasRegularOverlapItem
                ? overlapItems.filter((courseItem) => !this.selectedTimetableV2SlotIsOccasional(courseItem))
                : overlapItems
            const markers = hasRegularOverlapItem
                ? this.adoptedTimetablePdfOccasionalMarkers(overlapItems)
                : []

            courseItems.filter(Boolean).forEach((courseItem) => {
                const key = this.selectedTimetableV2SlotKey(courseItem)
                    || this.selectedTimetableV2CourseProblemLabel(courseItem)
                    || this.selectedTimetableV2SlotTitle(courseItem)

                if (!key || courseItemsByKey.has(key)) return

                courseItemsByKey.set(key, this.adoptedTimetablePdfCoursePayload(courseItem))
            })

            const status = this.adoptedTimetablePdfCellStatus(slot, [...courseItemsByKey.values()])

            return {
                status,
                courses: [...courseItemsByKey.values()],
                markers,
            }
        },
        adoptedTimetablePdfOccasionalMarkers(overlapItems) {
            const markersByKey = new Map()

            overlapItems
                .filter((courseItem) => this.selectedTimetableV2SlotIsOccasional(courseItem))
                .forEach((courseItem) => {
                    const label = this.selectedTimetableV2SlotTitle(courseItem)
                    const title = [
                        this.selectedTimetableV2CourseProblemLabel(courseItem),
                        this.selectedTimetableV2SlotTimePatternLabel(courseItem, { showRegularRange: true }),
                    ].filter(Boolean).join(' ')
                    const key = this.selectedTimetableV2SlotKey(courseItem) || title || label

                    if (!key || !label || markersByKey.has(key)) return

                    markersByKey.set(key, {
                        label,
                        title: title || label,
                    })
                })

            return [...markersByKey.values()]
        },
        adoptedTimetablePdfCellStatus(slot, courses) {
            const conflictSeverity = this.selectedTimetableV2SlotConflictSeverity(slot)

            if (conflictSeverity === 'error') return 'conflict'
            if (conflictSeverity === 'warning') return 'filled'
            if (courses.length > 1) return 'warning'

            return courses.length ? 'filled' : 'empty'
        },
        adoptedTimetablePdfCoursePayload(slot) {
            return {
                label: this.selectedTimetableV2SlotTitle(slot),
                details: this.adoptedTimetablePdfCourseDetails(slot),
                dates: this.selectedTimetableV2SlotExactDates(slot),
                is_fu: slot?.isDistanceLearningCourse === true || slot?.courseGroup?.distanceLearning === true,
                student_course_type: slot?.isAdditionalCourse === true ? 'additional' : '',
                student_course_badge: slot?.isAdditionalCourse === true ? 'Zusatz' : '',
            }
        },
        adoptedTimetablePdfCourseDetails(slot) {
            return [
                this.selectedTimetableV2SlotDetails(slot),
                this.adoptedTimetablePdfCourseTimePatternLabel(slot),
            ].filter(Boolean).join('\n')
        },
        adoptedTimetablePdfCourseTimePatternLabel(slot) {
            const isKompaktunterricht = this.selectedTimetableV2SlotIsKompaktunterricht(slot)
            const timePatternLabel = this.selectedTimetableV2SlotTimePatternLabel(slot, {
                hideWholeSemesterRange: !isKompaktunterricht,
                showRegularRange: isKompaktunterricht,
            })

            if (!isKompaktunterricht || /\(Kompakt\)/iu.test(timePatternLabel)) {
                return timePatternLabel
            }

            return timePatternLabel ? `${timePatternLabel} (Kompakt)` : 'Kompakt'
        },
        downloadBlob(blob, filename) {
            const objectUrl = URL.createObjectURL(blob)
            const link = document.createElement('a')

            link.href = objectUrl
            link.download = filename
            document.body.appendChild(link)
            link.click()
            link.remove()
            URL.revokeObjectURL(objectUrl)
        },
        fileNameFromContentDisposition(headerValue) {
            const normalizedHeader = String(headerValue || '').trim()
            if (!normalizedHeader) return ''

            const utf8Match = normalizedHeader.match(/filename\*\s*=\s*UTF-8''([^;]+)/iu)
            if (utf8Match?.[1]) {
                try {
                    return decodeURIComponent(utf8Match[1]).replace(/["']/g, '').trim()
                } catch {
                    return utf8Match[1].replace(/["']/g, '').trim()
                }
            }

            const plainMatch = normalizedHeader.match(/filename\s*=\s*"?(?<file>[^";]+)"?/iu)

            return plainMatch?.groups?.file?.trim() || ''
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

            return [
                ...this.selectedTimetableV2SlotConflicts(slot),
                ...this.selectedTimetableV2RegularSameSlotConflicts(slot),
            ]
                .filter((conflict) => !this.selectedTimetableV2SlotIsOccasional(conflict))
        },
        selectedTimetableV2ConflictSummaryParts(summary) {
            const text = String(summary || '')
            const dateItemPattern = String.raw`\d{1,2}\.\d{1,2}\.?(?:\([AB]\))?`
            const dateSequencePattern = new RegExp(`${dateItemPattern}(?:\\s*,\\s*${dateItemPattern})*`, 'gu')
            const parts = []
            let lastIndex = 0

            for (const match of text.matchAll(dateSequencePattern)) {
                const matchIndex = match.index || 0

                if (matchIndex > lastIndex) {
                    parts.push({
                        key: `${parts.length}-text`,
                        text: text.slice(lastIndex, matchIndex),
                        type: 'text',
                    })
                }

                parts.push({
                    key: `${parts.length}-date`,
                    text: match[0],
                    type: 'date',
                })

                lastIndex = matchIndex + match[0].length
            }

            if (lastIndex < text.length) {
                parts.push({
                    key: `${parts.length}-text`,
                    text: text.slice(lastIndex),
                    type: 'text',
                })
            }

            return parts.length ? parts : [{
                key: '0-text',
                text,
                type: 'text',
            }]
        },
        selectedTimetableV2SlotTitle(slot) {
            return this.courseDisplayLabel(slot?.code || slot?.sourceLabel || slot?.name || '')
        },
        selectedTimetableV2SlotDetails(slot) {
            const sourceLabel = String(slot?.sourceLabel || '').trim()
            const title = String(slot?.code || '').trim()

            if (!sourceLabel || sourceLabel === title) return ''

            return this.courseDisplayLabel(sourceLabel)
        },
        selectedTimetableV2SlotMatchesCourse(slot, targetCourseKey) {
            const normalizedTargetCourseKey = this.normalizedCourseCode(targetCourseKey)
            if (!normalizedTargetCourseKey) return false

            return [
                slot?.sourceLabel,
                slot?.groupSelectionLabel,
                slot?.name,
                slot?.label,
                slot?.code,
                slot?.class_name,
                slot?.display_label,
                slot?.student_group,
                slot?.title,
                slot?.courseGroup?.class_name,
                slot?.courseGroup?.display_label,
                slot?.courseGroup?.student_group,
                slot?.courseGroup?.title,
            ]
                .map((label) => this.normalizedCourseCode(label))
                .some((label) => label === normalizedTargetCourseKey || label.startsWith(normalizedTargetCourseKey))
        },
        selectedTimetableV2IsoWeekNumber(date) {
            if (!(date instanceof Date) || Number.isNaN(date.getTime())) return null

            const weekDate = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
            const dayNumber = weekDate.getUTCDay() || 7
            weekDate.setUTCDate(weekDate.getUTCDate() + 4 - dayNumber)

            const yearStart = new Date(Date.UTC(weekDate.getUTCFullYear(), 0, 1))

            return Math.ceil((((weekDate - yearStart) / 86400000) + 1) / 7)
        },
        selectedTimetableV2TwoWeekParitySuffix(source) {
            const dates = Array.isArray(source?.courseGroup?.dates)
                ? source.courseGroup.dates
                : (Array.isArray(source?.dates) ? source.dates : [])
            const dateValues = dates
                .map((date) => String(date || '').trim())
                .filter(Boolean)

            if (!dateValues.length) return ''

            const weekNumbers = dateValues
                .map((date) => this.normalizedDate(date))
                .map((date) => this.selectedTimetableV2IsoWeekNumber(date))

            if (weekNumbers.length !== dateValues.length || weekNumbers.some((weekNumber) => !Number.isInteger(weekNumber))) return ''

            const parities = this.uniqueValues(weekNumbers.map((weekNumber) => weekNumber % 2))
            if (parities.length !== 1) return ''

            return parities[0] === 0 ? ' A' : ' B'
        },
        selectedTimetableV2WeekIntervalLabel(interval, source) {
            const intervalNumber = Number(interval)
            if (!Number.isInteger(intervalNumber) || intervalNumber <= 1) return ''

            const label = `${intervalNumber}-wöchig`

            return intervalNumber === 2
                ? `${label}${this.selectedTimetableV2TwoWeekParitySuffix(source)}`
                : label
        },
        selectedTimetableV2SlotRecurrenceLabel(slot) {
            const courseGroup = slot?.courseGroup || slot
            const interval = this.courseGroupWeekInterval(courseGroup)
            const recurrenceSource = slot?.courseGroup ? slot : courseGroup
            if (Number.isInteger(interval) && interval > 1) return this.selectedTimetableV2WeekIntervalLabel(interval, recurrenceSource)

            const recurrenceLabel = String(slot?.courseGroup?.recurrenceLabel || slot?.courseGroup?.recurrence_label || slot?.recurrenceLabel || slot?.recurrence_label || '').trim()
            if (!recurrenceLabel || /^w[öo]chentlich$/iu.test(recurrenceLabel) || /^1\s*-\s*w[öo]chig$/iu.test(recurrenceLabel)) return ''
            if (/^2\s*-?\s*w(?:öchig|ochig)?(?:\s+[AB])?$/iu.test(recurrenceLabel)) {
                return /\s+[AB]$/iu.test(recurrenceLabel)
                    ? recurrenceLabel
                    : `${recurrenceLabel}${this.selectedTimetableV2TwoWeekParitySuffix(recurrenceSource)}`
            }

            return recurrenceLabel
        },
        selectedTimetableV2SlotDateLabel(slot, options = {}) {
            const dateRangeLabel = String(slot?.dateRangeLabel || '').trim()

            if (options?.hideWholeSemesterRange === true && this.selectedTimetableV2DateRangeCoversWholeSemester(dateRangeLabel)) {
                return ''
            }

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
            const compactSuffix = dateLabel && this.selectedTimetableV2SlotIsKompaktunterricht(slot) ? ' (Kompakt)' : ''

            if (recurrenceLabel && dateLabel) return `${recurrenceLabel}: ${dateLabel}${compactSuffix}`

            return recurrenceLabel || (dateLabel ? `${dateLabel}${compactSuffix}` : '')
        },
        selectedTimetableV2SlotTimePatternParts(slot, options = {}) {
            const recurrenceLabel = this.selectedTimetableV2SlotRecurrenceLabel(slot)
            const dateLabel = this.selectedTimetableV2SlotDateLabel(slot, options)
            const compactSuffix = dateLabel && this.selectedTimetableV2SlotIsKompaktunterricht(slot) ? ' (Kompakt)' : ''

            return {
                compactSuffix,
                dateLabel,
                label: recurrenceLabel && dateLabel
                    ? `${recurrenceLabel}: ${dateLabel}${compactSuffix}`
                    : recurrenceLabel || (dateLabel ? `${dateLabel}${compactSuffix}` : ''),
                recurrenceLabel,
            }
        },
        selectedTimetableV2SlotExactDateLabels(slot) {
            return this.selectedTimetableV2SlotExactDates(slot)
                .map((date) => this.formatCompactDateWithWeekValue(date))
                .filter(Boolean)
        },
        selectedTimetableV2SlotExactDates(slot) {
            if (!Array.isArray(slot?.courseGroup?.dates)) return []

            return this.uniqueValues([...slot.courseGroup.dates]
                .map((date) => String(date || '').trim())
                .filter(Boolean)
                .sort())
        },
        selectedTimetableV2DateLabelIsRange(label) {
            return /\d{1,2}\.\d{1,2}\.?\s*-\s*\d{1,2}\.\d{1,2}\.?/u.test(String(label || ''))
        },
        selectedTimetableV2DateRangeCoversWholeSemester(label) {
            const dateRangeKey = this.selectedTimetableV2DateRangeKey(label)
            if (!dateRangeKey) return false

            return this.selectedTimetableV2FullSemesterDateRangeKeys().includes(dateRangeKey)
        },
        selectedTimetableV2FullSemesterDateRangeKeys() {
            const schoolyear = this.selectedSchoolyear || {}
            const fromDate = this.normalizedDate(schoolyear.from)
            const sem2StartDate = this.normalizedDate(schoolyear.sem_2_start)
            const untilDate = this.normalizedDate(schoolyear.until)
            const dateRanges = [
                [fromDate, untilDate],
            ]

            if (fromDate && sem2StartDate) {
                const sem1UntilDate = new Date(sem2StartDate)
                sem1UntilDate.setDate(sem1UntilDate.getDate() - 1)
                dateRanges.push([fromDate, sem1UntilDate])
            }

            if (sem2StartDate && untilDate) {
                dateRanges.push([sem2StartDate, untilDate])
            }

            return this.uniqueValues(dateRanges
                .map(([rangeFrom, rangeUntil]) => this.selectedTimetableV2DateRangeKeyFromDates(rangeFrom, rangeUntil))
                .filter(Boolean))
        },
        selectedTimetableV2DateRangeKey(label) {
            const dateMatches = [...String(label || '').matchAll(/(\d{1,2})\.(\d{1,2})\.?(?:\d{2,4})?/gu)]
            if (dateMatches.length < 2) return ''

            const firstDate = dateMatches[0]
            const lastDate = dateMatches[dateMatches.length - 1]

            return [
                this.selectedTimetableV2DateKeyPart(Number(firstDate[1]), Number(firstDate[2])),
                this.selectedTimetableV2DateKeyPart(Number(lastDate[1]), Number(lastDate[2])),
            ].filter(Boolean).join('|')
        },
        selectedTimetableV2DateRangeKeyFromDates(fromDate, untilDate) {
            if (!fromDate || !untilDate) return ''

            return [
                this.selectedTimetableV2DateKeyPart(fromDate.getDate(), fromDate.getMonth() + 1),
                this.selectedTimetableV2DateKeyPart(untilDate.getDate(), untilDate.getMonth() + 1),
            ].filter(Boolean).join('|')
        },
        selectedTimetableV2DateKeyPart(day, month) {
            if (!Number.isFinite(day) || !Number.isFinite(month)) return ''

            return `${Number(month)}-${Number(day)}`
        },
        selectedTimetableV2SameSlotEntries(slot) {
            return Array.isArray(slot?.sameSlotEntries) ? slot.sameSlotEntries : []
        },
        selectedTimetableV2DisplaySameSlotEntries(slot) {
            const sameSlotEntriesByKey = new Map()
            const displaySameSlotEntries = [
                ...this.selectedTimetableV2SameSlotEntries(slot),
                ...this.selectedTimetableV2RawSlotConflicts(slot)
                    .filter((conflict) => !this.selectedTimetableV2SlotConflictDatesOverlap(slot, conflict))
                    .map((conflict) => this.selectedTimetableV2SlotConflictDateSource(slot, conflict)),
            ]

            displaySameSlotEntries.filter(Boolean)
                .forEach((sameSlotEntry) => {
                    const key = this.selectedTimetableV2DisplaySameSlotEntryKey(sameSlotEntry)

                    if (!key) return
                    if (sameSlotEntriesByKey.has(key) && this.selectedTimetableV2SlotDateRangeBounds(sameSlotEntriesByKey.get(key))) return

                    sameSlotEntriesByKey.set(key, sameSlotEntry)
                })

            return [...sameSlotEntriesByKey.values()]
        },
        selectedTimetableV2DisplaySameSlotEntryKey(sameSlotEntry) {
            return [
                this.selectedTimetableV2CourseProblemLabel(sameSlotEntry),
                sameSlotEntry?.code || '',
                sameSlotEntry?.sourceLabel || '',
                sameSlotEntry?.courseGroup?.weekday || sameSlotEntry?.weekday || '',
                sameSlotEntry?.courseGroup?.hour || sameSlotEntry?.hour || '',
                sameSlotEntry?.from || '',
                sameSlotEntry?.until || '',
            ]
                .map((value) => this.normalizedCourseCode(value) || String(value || '').trim())
                .filter(Boolean)
                .join('|')
        },
        selectedTimetableV2RawSlotConflicts(slot) {
            return Array.isArray(slot?.conflicts) ? slot.conflicts : []
        },
        selectedTimetableV2SlotConflicts(slot) {
            return this.selectedTimetableV2RawSlotConflicts(slot)
                .filter((conflict) => this.selectedTimetableV2SlotConflictDatesOverlap(slot, conflict))
        },
        selectedTimetableV2SlotConflictDatesOverlap(slot, conflict) {
            if (!slot || !conflict) return false
            const conflictDateSource = this.selectedTimetableV2SlotConflictDateSource(slot, conflict)
            if (!this.selectedTimetableV2SlotDateRangeBounds(slot) || !this.selectedTimetableV2SlotDateRangeBounds(conflictDateSource)) return true

            return this.selectedTimetableV2SlotDateRangesOverlap(slot, conflictDateSource)
        },
        selectedTimetableV2SlotConflictDateSource(slot, conflict) {
            if (this.selectedTimetableV2SlotDateRangeBounds(conflict)) return conflict

            const conflictCourseKeys = [
                conflict?.code,
                conflict?.sourceLabel,
                conflict?.name,
                conflict?.label,
                conflict?.courseGroup?.class_name,
                conflict?.courseGroup?.display_label,
                conflict?.courseGroup?.title,
            ]
                .map((value) => this.normalizedCourseCode(value))
                .filter(Boolean)

            return this.selectedTimetableV2SameSlotEntries(slot)
                .find((sameSlotEntry) => this.selectedTimetableV2SlotDateRangeBounds(sameSlotEntry)
                    && conflictCourseKeys.some((courseKey) => this.selectedTimetableV2SlotMatchesCourse(sameSlotEntry, courseKey)))
                || conflict
        },
        selectedTimetableV2RegularSameSlotConflicts(slot) {
            if (!slot) return []

            return this.selectedTimetableV2SameSlotEntries(slot)
                .filter((sameSlotEntry) => this.selectedTimetableV2RegularSameSlotEntryConflicts(slot, sameSlotEntry))
        },
        selectedTimetableV2RegularSameSlotEntryConflicts(slot, sameSlotEntry) {
            if (this.selectedTimetableV2SlotIsOccasional(slot) || this.selectedTimetableV2SlotIsOccasional(sameSlotEntry)) return false

            return this.selectedTimetableV2SlotDateRangesOverlap(slot, sameSlotEntry)
        },
        selectedTimetableV2SlotDateRangesOverlap(firstSlot, secondSlot) {
            const firstExactDateValues = this.selectedTimetableV2SlotExactDateValues(firstSlot)
            const secondExactDateValues = this.selectedTimetableV2SlotExactDateValues(secondSlot)

            if (firstExactDateValues.length && secondExactDateValues.length) {
                const secondExactDateValueSet = new Set(secondExactDateValues)

                return firstExactDateValues.some((dateValue) => secondExactDateValueSet.has(dateValue))
            }

            const firstRange = this.selectedTimetableV2SlotDateRangeBounds(firstSlot)
            const secondRange = this.selectedTimetableV2SlotDateRangeBounds(secondSlot)

            if (!firstRange || !secondRange) return false

            return Math.max(firstRange.start, secondRange.start) <= Math.min(firstRange.end, secondRange.end)
        },
        selectedTimetableV2SlotDateRangeBounds(slot) {
            const exactDateValues = this.selectedTimetableV2SlotExactDateValues(slot)

            if (exactDateValues.length) {
                return {
                    start: Math.min(...exactDateValues),
                    end: Math.max(...exactDateValues),
                }
            }

            const rangeValues = [...String(slot?.dateRangeLabel || '').matchAll(/(\d{1,2})\.(\d{1,2})\.?/gu)]
                .map((match) => this.selectedTimetableV2MonthDayValue(match[1], match[2]))
                .filter((dateValue) => Number.isFinite(dateValue))

            if (rangeValues.length < 2) return null

            return {
                start: rangeValues[0],
                end: rangeValues[rangeValues.length - 1],
            }
        },
        selectedTimetableV2SlotExactDateValues(slot) {
            return this.selectedTimetableV2SlotExactDateLabels(slot)
                .map((dateLabel) => this.selectedTimetableV2CompactDateValue(dateLabel))
                .filter((dateValue) => Number.isFinite(dateValue))
        },
        selectedTimetableV2CompactDateValue(dateLabel) {
            const compactDateMatch = String(dateLabel || '').trim().match(/^(\d{1,2})\.(\d{1,2})\.?(?:\([AB]\))?$/u)

            if (!compactDateMatch) return NaN

            return this.selectedTimetableV2MonthDayValue(compactDateMatch[1], compactDateMatch[2])
        },
        selectedTimetableV2MonthDayValue(day, month) {
            const dayNumber = Number(day)
            const monthNumber = Number(month)

            if (!Number.isFinite(dayNumber) || !Number.isFinite(monthNumber)) return NaN

            return (monthNumber * 31) + dayNumber
        },
        selectedTimetableV2SlotConflictSeverity(slot) {
            const conflicts = [
                ...this.selectedTimetableV2SlotConflicts(slot),
                ...this.selectedTimetableV2RegularSameSlotConflicts(slot),
            ]
            if (!conflicts.length) return ''

            return conflicts.every((conflict) => this.selectedTimetableV2ConflictPairIsOccasional({ slot, conflict }))
                ? 'warning'
                : 'error'
        },
        selectedTimetableV2ConflictPairs() {
            const conflictPairsByKey = new Map()

            this.selectedTimetableV2SlotEntries.forEach((entry) => {
                [
                    ...this.selectedTimetableV2SlotConflicts(entry.slot),
                    ...this.selectedTimetableV2RegularSameSlotConflicts(entry.slot),
                ].forEach((conflict) => {
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
        selectedTimetableV2ConflictSummaryLabel(slot, conflict, options = {}) {
            const oneDayCourseFirst = this.selectedTimetableV2SlotIsOccasional(conflict)
                && !this.selectedTimetableV2SlotIsOccasional(slot)
            const firstItem = oneDayCourseFirst ? conflict : slot
            const secondItem = oneDayCourseFirst ? slot : conflict
            const firstTitle = this.selectedTimetableV2CourseProblemLabelWithHour(
                firstItem,
                oneDayCourseFirst ? null : options?.slotHour,
                oneDayCourseFirst ? null : options?.slotWeekday,
            )
            const secondTitle = this.selectedTimetableV2ConflictLabel(secondItem, firstItem)

            return firstTitle && secondTitle
                ? `${firstTitle} überschneidet sich mit ${secondTitle}.`
                : secondTitle
        },
        selectedTimetableV2ApparentOverlapSummaryLabel(slot, sameSlotEntry, options = {}) {
            const weekdayLabel = this.courseGroupWeekdayLabel(slot?.courseGroup?.weekday || slot?.weekday || options?.slotWeekday)
            const hour = Number(slot?.courseGroup?.hour || slot?.hour || options?.slotHour || 0)
            const hourLabel = Number.isFinite(hour) && hour > 0 ? `${hour}.` : ''
            const slotLabel = this.selectedTimetableV2CourseProblemLabel(slot)
            const sameSlotLabel = this.selectedTimetableV2CourseProblemLabel(sameSlotEntry)
            const slotDateLabel = this.selectedTimetableV2ApparentOverlapDateLabel(slot)
            const sameSlotDateLabel = this.selectedTimetableV2ApparentOverlapDateLabel(sameSlotEntry)
            const timeLabel = [weekdayLabel, hourLabel].filter(Boolean).join(' ')

            if (!slotLabel || !sameSlotLabel || !slotDateLabel || !sameSlotDateLabel) return ''

            return `${timeLabel}: ${slotLabel} Termine (${slotDateLabel}); ${sameSlotLabel} Termine (${sameSlotDateLabel}) - keine gleichen Termine.`
        },
        selectedTimetableV2ApparentOverlapDateLabel(slot) {
            const exactDateLabels = this.selectedTimetableV2SlotExactDateLabels(slot)
            if (exactDateLabels.length) return exactDateLabels.join(', ')

            return String(this.selectedTimetableV2SlotDateLabel(slot, { showRegularRange: true }) || slot?.dateRangeLabel || '').trim()
        },
        selectedTimetableV2CourseProblemLabelWithHour(item, fallbackHour = null, fallbackWeekday = null) {
            const label = this.selectedTimetableV2CourseProblemLabel(item)
            if (!label) return ''

            const hour = Number(item?.courseGroup?.hour || item?.hour || fallbackHour || 0)
            if (!Number.isFinite(hour) || hour <= 0) return label

            const weekdayLabel = this.courseGroupWeekdayLabel(item?.courseGroup?.weekday || item?.weekday || fallbackWeekday)

            return [label, weekdayLabel, `${hour}.`].filter(Boolean).join(' ')
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
        selectedTimetableV2SelectedCourseForOfferedCourse(offeredCourse) {
            const selectedCourseSelectionKey = String(offeredCourse?.selectionKey || '').split('::')[0] || ''
            const selectedCourses = Array.isArray(this.selectedCourseItems) ? this.selectedCourseItems : []

            if (selectedCourseSelectionKey) {
                const selectedCourse = selectedCourses.find((course) => course.selectionKey === selectedCourseSelectionKey)
                if (selectedCourse) return selectedCourse
            }

            const courseKey = String(offeredCourse?.backendSelectionKey || '').split('|')[0] || ''

            return selectedCourses.find((course) =>
                [
                    course?.key,
                    course?.code,
                    course?.label,
                ].map((value) => String(value || '').trim()).includes(courseKey)) || null
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
            const slotLabel = this.selectedTimetableV2RawCourseProblemLabel(slot)
            const conflictLabel = this.selectedTimetableV2RawCourseProblemLabel(conflict)
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
                ? sharedDates.map((date) => this.formatCompactDateWithWeekValue(date)).filter(Boolean)
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

            return this.formatCompactDateWithWeekValue(normalizedValue)
        },
        selectedTimetableV2CourseProblemLabel(item) {
            return this.courseDisplayLabel(this.selectedTimetableV2RawCourseProblemLabel(item))
        },
        selectedTimetableV2RawCourseProblemLabel(item) {
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
            if (!option?.selectedCourse) return null

            this.saveCourseItemSelection(option.selectedCourse, false)

            this.setSelectedTimetableV2Number(1, { replace: true })

            return this.calculateTimetables()
        },
        applySelectedTimetableV2ProblemCourseResolution(problemCourse) {
            if (!problemCourse?.selectedCourse) return null

            return this.applySelectedTimetableV2CourseResolution(problemCourse)
        },
        applySelectedTimetableV2CourseResolution(courseResolution) {
            if (!courseResolution?.selectedCourse) return null

            this.saveCourseItemSelection(courseResolution.selectedCourse, false)

            this.setSelectedTimetableV2Number(1, { replace: true })

            return this.calculateTimetables()
        },
        applySelectedTimetableV2OptionResolution(option) {
            if (!['no-saturday', 'max-free-days', 'no-distance-learning', 'starts-from-period-10'].includes(option?.key)) return null

            if (option.key === 'no-saturday') {
                this.timetableNoSaturdaySelected = false
                this.timetableNoSaturdayDraftSelected = false
            }

            if (option.key === 'max-free-days') {
                this.timetableMaxFreeDaysSelected = false
                this.timetableMaxFreeDaysDraftSelected = false
            }

            if (option.key === 'no-distance-learning') {
                this.timetableNoDistanceLearningSelected = false
                this.timetableNoDistanceLearningDraftSelected = false
            }

            if (option.key === 'starts-from-period-10') {
                this.timetableStartsFromPeriod10Selected = false
                this.timetableStartsFromPeriod10DraftSelected = false
            }

            this.saveTimetableV2Options()
            this.setSelectedTimetableV2Number(1, { replace: true })
            this.resetMoreCourseAvailability()

            return this.calculateTimetables({ progressContext: 'options' })
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
        normalizedTimetableV2RouteMoreCourses(value) {
            const routeValue = arguments.length ? value : this.$route?.query?.moreCourses

            return ['1', 'true'].includes(String(routeValue || '').trim())
        },
        normalizedTimetableV2RouteMoreCourseKey(value) {
            const routeValue = arguments.length ? value : this.$route?.query?.moreCourse

            return String(routeValue || '').trim().replace(/:+$/u, '')
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

            return this.calculateTimetables({ refreshAuxiliary: false })
        },
        moveSelectedTimetableV2Result(direction) {
            const nextNumber = this.timetableCalculationSelectedNumber + Number(direction || 0)

            this.setSelectedTimetableV2Number(nextNumber)
            return this.calculateTimetables({ refreshAuxiliary: false })
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

            if (normalizedStep === 'timetable-calculation' || normalizedStep === 'timetable-adoption') {
                query.tt = String(this.timetableCalculationSelectedNumber)
            } else {
                delete query.tt
            }

            if (normalizedStep === 'timetable-calculation' && this.moreCoursesVisible) {
                query.moreCourses = '1'

                if (this.selectedMoreCourseKey) {
                    query.moreCourse = this.selectedMoreCourseKey
                } else {
                    delete query.moreCourse
                }
            } else {
                delete query.moreCourses
                delete query.moreCourse
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
                && this.normalizedTimetableV2RouteMoreCourses() === this.normalizedTimetableV2RouteMoreCourses(location?.query?.moreCourses)
                && this.normalizedTimetableV2RouteMoreCourseKey() === this.normalizedTimetableV2RouteMoreCourseKey(location?.query?.moreCourse)
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
            if (normalizedStep !== 'timetable-calculation') {
                this.calculationTimetableV2Selection = null
            }
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
            this.applyMoreCoursesRouteState()

            if (options?.syncRoute !== false) {
                this.syncTimetableV2Route({ replace: true })
            }

            if (
                options?.restoreEffects !== false
                && this.timetableCalculationVisible
                && previousTimetableNumber !== this.timetableCalculationSelectedNumber
                && this.timetableCalculationResult
            ) {
                this.calculateTimetables({ refreshAuxiliary: false })

                return
            }

            if (options?.restoreEffects !== false) {
                this.restoreTimetableV2RouteStepEffects()
            }
        },
        restoreTimetableV2RouteStepEffects(loadPromises = {}) {
            if (this.courseReviewVisible || this.timetableCalculationVisible || this.adoptedTimetableVisible) {
                this.selectedReviewCourseKey = this.timetableCalculationVisible
                    ? ''
                    : this.selectedReviewCourseItem?.selectionKey || ''

                if (!loadPromises.courseGroupsPromise) {
                    this.loadCourseGroups?.()
                }

                if (!loadPromises.schoolHoursPromise) {
                    this.loadSchoolHours?.()
                }
            }

            if (this.adoptedTimetableVisible) {
                return this.restoreAdoptedTimetableV2ResultFromRoute(loadPromises)
            }

            if (!this.timetableCalculationVisible || this.timetableCalculationLoading || this.timetableCalculationResult) return null

            return Promise.all([
                loadPromises.subjectRowsPromise || this.loadSubjectRows?.(),
                loadPromises.courseGroupsPromise || this.loadCourseGroups?.(),
                loadPromises.schoolHoursPromise || this.loadSchoolHours?.(),
            ]).then(() => {
                if (!this.timetableCalculationVisible || this.timetableCalculationLoading || this.timetableCalculationResult) return

                this.calculateTimetables()
            })
        },
        restoreAdoptedTimetableV2ResultFromRoute(loadPromises = {}) {
            if (!this.adoptedTimetableVisible) return null
            if (this.adoptedTimetableCalculationResult || this.timetableCalculationLoading) return null
            if (this.restoreAdoptedTimetableV2ResultFromStoredState()) return this.adoptedTimetableCalculationResult

            return Promise.all([
                loadPromises.subjectRowsPromise || this.loadSubjectRows?.(),
                loadPromises.courseGroupsPromise || this.loadCourseGroups?.(),
                loadPromises.schoolHoursPromise || this.loadSchoolHours?.(),
            ]).then(async () => {
                if (!this.adoptedTimetableVisible || this.adoptedTimetableCalculationResult || this.timetableCalculationLoading) return null

                await this.calculateTimetables()

                if (!this.adoptedTimetableVisible || !this.timetableCalculationResult?.selected_timetable) return null

                this.freezeCurrentTimetableV2ResultForAdoption()

                return this.adoptedTimetableCalculationResult
            })
        },
        restoreAdoptedTimetableV2ResultFromStoredState() {
            const adoption = this.storedTimetableState?.timetableV2Adoption
            if (!adoption || typeof adoption !== 'object' || Array.isArray(adoption)) return false

            const adoptedCalculationResult = adoption.adoptedCalculationResult || adoption.calculationResult
            if (!adoptedCalculationResult?.selected_timetable) return false

            this.adoptedTimetableCalculationResult = this.clonedTimetableV2Value(adoptedCalculationResult)
            this.timetableCalculationResult = adoption.calculationResult?.selected_timetable
                ? this.clonedTimetableV2Value(adoption.calculationResult)
                : this.clonedTimetableV2Value(adoptedCalculationResult)
            this.adoptedTimetableSelectedNumber = this.normalizedTimetableV2RouteTimetableNumber(
                adoption.selectedNumber || this.$route?.query?.tt || 1,
            )
            this.timetableCalculationSelectedNumber = this.adoptedTimetableSelectedNumber
            this.adoptedTimetableCalculationSelectionSnapshot = this.clonedTimetableV2Selection(
                adoption.calculationSelection || adoption.selection || this.storedTimetableV2Selection,
            )
            this.adoptedTimetableCalculationOptionsSnapshot = this.clonedTimetableV2Value(
                adoption.calculationOptions || this.timetableV2OptionsForSaving(),
            )
            this.adoptedTimetableSelectionSnapshot = this.clonedTimetableV2Selection(
                adoption.selection || this.storedTimetableV2Selection,
            )
            this.adoptedTimetableStudentContextSnapshot = this.clonedTimetableV2Value(
                adoption.studentContext || this.storedTimetableStudentContext,
            )
            this.applyTimetableV2OptionState(this.adoptedTimetableCalculationOptionsSnapshot)

            return true
        },
        applyMoreCoursesRouteState() {
            if (!this.timetableCalculationVisible) {
                if (this.moreCoursesVisible || this.moreCoursesCardVisible) {
                    this.closeMoreCoursesCard({ syncRoute: false })
                }

                return
            }

            if (!this.normalizedTimetableV2RouteMoreCourses()) {
                if (this.moreCoursesVisible || this.moreCoursesCardVisible) {
                    this.closeMoreCoursesCard({ syncRoute: false })
                }

                return
            }

            const selectedMoreCourseKey = this.normalizedTimetableV2RouteMoreCourseKey()

            if (!this.moreCoursesVisible) {
                this.openMoreCoursesCard({ syncRoute: false })
            }

            this.selectedMoreCourseKey = selectedMoreCourseKey
        },
        courseGroupsNeededForCurrentStep() {
            return this.courseReviewVisible
                || this.timetableCalculationVisible
                || this.adoptedTimetableVisible
                || this.courseCardsVisible
        },
        syncInitialTimetableCalculationSelection(previousStep = '') {
            if (this.timetableV2Step !== 'timetable-calculation') {
                this.calculationTimetableV2Selection = null
                this.initialTimetableCalculationSelection = null
                this.initialTimetableCalculationSelectedNumber = 1
                this.initialTimetableCalculationNoSaturdaySelected = false
                this.initialTimetableCalculationMaxFreeDaysSelected = false
                this.initialTimetableCalculationNoDistanceLearningSelected = false
                this.initialTimetableCalculationStartsFromPeriod10Selected = false
                this.timetableNoSaturdayDraftSelected = false
                this.timetableNoSaturdaySelected = false
                this.timetableMaxFreeDaysDraftSelected = false
                this.timetableMaxFreeDaysSelected = false
                this.timetableNoDistanceLearningDraftSelected = false
                this.timetableNoDistanceLearningSelected = false
                this.timetableStartsFromPeriod10DraftSelected = false
                this.timetableStartsFromPeriod10Selected = false

                return
            }

            if (previousStep === 'timetable-calculation' && this.initialTimetableCalculationSelection !== null) return

            const timetableV2Selection = this.clonedTimetableV2Selection(this.currentTimetableV2SelectionForCourseState())

            this.calculationTimetableV2Selection = this.clonedTimetableV2Selection(timetableV2Selection)
            this.initialTimetableCalculationSelection = this.clonedTimetableV2Selection(timetableV2Selection)
            this.initialTimetableCalculationSelectedNumber = this.timetableCalculationSelectedNumber
            this.initialTimetableCalculationNoSaturdaySelected = this.timetableNoSaturdaySelected
            this.initialTimetableCalculationMaxFreeDaysSelected = this.timetableMaxFreeDaysSelected
            this.initialTimetableCalculationNoDistanceLearningSelected = this.timetableNoDistanceLearningSelected
            this.initialTimetableCalculationStartsFromPeriod10Selected = this.timetableStartsFromPeriod10Selected
        },
        clonedTimetableV2Selection(selection = {}) {
            return JSON.parse(JSON.stringify(selection && typeof selection === 'object' && !Array.isArray(selection) ? selection : {}))
        },
        timetableV2SelectionWithAllOfferedCoursesSelected(selection = {}) {
            const timetableV2Selection = this.clonedTimetableV2Selection(selection)

            delete timetableV2Selection.offeredCourseSelections
            delete timetableV2Selection.moreOfferedCourseSelections

            return timetableV2Selection
        },
        resetCourseReviewOfferedCourseSelections() {
            const timetableV2Selection = this.currentTimetableV2SelectionForCourseState()
            if (
                !Object.prototype.hasOwnProperty.call(timetableV2Selection, 'offeredCourseSelections')
                && !Object.prototype.hasOwnProperty.call(timetableV2Selection, 'moreOfferedCourseSelections')
            ) {
                return
            }

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                adaptedTimetableV2Selection: this.timetableV2SelectionWithAllOfferedCoursesSelected(timetableV2Selection),
                transferredStudentContext: this.storedTimetableStudentContext,
            })
        },
        persistCourseSelectionsForCourseReview(courseSelections = this.courseSelectionOverrides) {
            const timetableV2Selection = this.courseReviewTimetableV2Selection(courseSelections)

            if (
                this.timetableCalculationSelectionSnapshot(timetableV2Selection)
                === this.timetableCalculationSelectionSnapshot(this.storedAdaptedTimetableV2Selection || {})
            ) {
                return
            }

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                adaptedTimetableV2Selection: timetableV2Selection,
                transferredStudentContext: this.storedTimetableStudentContext,
            })
        },
        courseReviewTimetableV2Selection(courseSelections = this.courseSelectionOverrides) {
            const existingAdaptedSelection = this.storedAdaptedTimetableV2Selection
            const timetableV2Selection = this.clonedTimetableV2Selection(existingAdaptedSelection || this.storedTimetableV2Selection)
            const normalizedCourseSelections = this.normalizedCourseSelections(
                existingAdaptedSelection
                    ? this.courseSelectionOverridesForSelection(existingAdaptedSelection)
                    : courseSelections,
            )
            const preselectionSignature = this.courseLimitPreselectionSignature

            if (!existingAdaptedSelection) {
                delete timetableV2Selection.offeredCourseSelections
                delete timetableV2Selection.moreOfferedCourseSelections
                delete timetableV2Selection.includeNormalunterrichtCourses
                delete timetableV2Selection.includeDistanceLearningCourses
                delete timetableV2Selection.includeKompaktunterrichtCourses
            }

            if (Object.keys(normalizedCourseSelections).length) {
                timetableV2Selection.courseSelections = normalizedCourseSelections
            } else {
                delete timetableV2Selection.courseSelections
            }

            if (preselectionSignature) {
                timetableV2Selection.courseLimitPreselectionKey = preselectionSignature
            } else {
                delete timetableV2Selection.courseLimitPreselectionKey
            }

            return timetableV2Selection
        },
        clonedTimetableV2Value(value) {
            return JSON.parse(JSON.stringify(value ?? null))
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
            this.timetableNoSaturdaySelected = false
            this.timetableMaxFreeDaysSelected = false
            this.timetableNoDistanceLearningSelected = false
            this.timetableStartsFromPeriod10Selected = false
            this.closeTimetableOptionsCard()
            this.clearPendingRemovedSelectedCourses()
            this.resetMoreCourseAvailability()
            this.calculationTimetableV2Selection = this.clonedTimetableV2Selection(timetableV2Selection)
            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                adaptedTimetableV2Selection: timetableV2Selection,
                timetableV2Options: this.timetableV2OptionsForSaving(),
                transferredStudentContext: this.storedTimetableStudentContext,
            })
            this.setSelectedTimetableV2Number(this.initialTimetableCalculationSelectedNumber || 1, { replace: true })

            return this.calculateTimetables({ progressContext: 'options' })
        },
        async calculateTimetables(options = {}) {
            const { progressContext = '', ...calculationOptions } = options || {}
            const previousCalculationResult = this.timetableCalculationResult
            const requestedTimetableNumber = this.timetableCalculationSelectedNumber
            const refreshAuxiliary = calculationOptions?.refreshAuxiliary !== false
            const includeQualityCounters = calculationOptions?.includeQualityCounters === true
            const calculationCacheKey = this.timetableV2CalculationResultCacheKey({
                ...calculationOptions,
                calculationResult: previousCalculationResult,
                includeQualityCounters,
                selectedTimetableNumber: requestedTimetableNumber,
            })
            const cachedCalculationResult = !refreshAuxiliary
                ? this.cachedTimetableV2CalculationResult(calculationCacheKey)
                : null

            if (cachedCalculationResult) {
                this.timetableCalculationError = ''
                this.timetableCalculationResult = cachedCalculationResult
                this.setSelectedTimetableV2Number(
                    this.selectedTimetableV2CombinedNumberFromResult(this.timetableCalculationResult) || requestedTimetableNumber,
                    { replace: true },
                )
                this.ensureConflictResolutionRecommendations()

                return cachedCalculationResult
            }

            const requestId = this.timetableCalculationRequestId + 1
            this.timetableCalculationRequestId = requestId
            this.timetableCalculationError = ''
            this.timetableCalculationLoadingMode = refreshAuxiliary ? 'calculation' : 'timetable'
            this.timetableCalculationLoading = true
            this.timetableCalculationResult = null
            this.startTimetableCalculationProgress(
                refreshAuxiliary && ['more-courses', 'options'].includes(progressContext)
                    ? progressContext
                    : '',
            )
            if (refreshAuxiliary) {
                this.resetMoreCourseAvailability()
                this.resetTimetableQualityCounters()
                this.timetableCalculationResultCache = {}
            }

            try {
                let response = await this.requestTimetableV2Calculation({
                    ...calculationOptions,
                    calculationResult: previousCalculationResult,
                    includeQualityCounters: false,
                    selectedTimetableNumber: requestedTimetableNumber,
                })

                if (requestId !== this.timetableCalculationRequestId) return

                let calculationResult = response.data?.data || {}
                const fallbackTimetableRequest = this.timetableV2FallbackSelectedTimetableRequest(
                    calculationResult,
                    requestedTimetableNumber,
                )

                if (fallbackTimetableRequest) {
                    response = await this.requestTimetableV2Calculation({
                        ...fallbackTimetableRequest,
                        includeQualityCounters: false,
                        ignoreSelectedQualityCriteria: fallbackTimetableRequest.selectedTimetableType === 'conflict',
                    })

                    if (requestId !== this.timetableCalculationRequestId) return

                    calculationResult = response.data?.data || calculationResult
                }

                this.timetableCalculationResult = this.timetableV2CalculationResultWithAuxiliaryData(
                    calculationResult,
                    previousCalculationResult,
                    refreshAuxiliary,
                )
                this.rememberTimetableV2CalculationResult(calculationCacheKey, this.timetableCalculationResult)
                this.setSelectedTimetableV2Number(
                    this.selectedTimetableV2CombinedNumberFromResult(this.timetableCalculationResult) || requestedTimetableNumber,
                    { replace: true },
                )
                if (refreshAuxiliary) {
                    this.refreshTimetableAuxiliaryInBackground()
                }

                await this.ensureConflictResolutionRecommendations()

                return this.timetableCalculationResult
            } catch (error) {
                if (requestId !== this.timetableCalculationRequestId) return

                this.timetableCalculationError = this.timetableCalculationErrorMessage(error)
            } finally {
                if (requestId === this.timetableCalculationRequestId) {
                    this.timetableCalculationLoading = false
                    this.completeTimetableCalculationProgress()
                }
            }
        },
        startTimetableCalculationProgress(progressSource = '') {
            this.clearTimetableCalculationProgressTimers()
            this.timetableCalculationProgressCompletionPending = false
            this.timetableCalculationProgressSource = progressSource
            this.timetableCalculationProgress = 0

            if (!progressSource) return

            this.timetableCalculationProgressTimer = globalThis.setInterval(() => {
                const currentProgress = Number(this.timetableCalculationProgress || 0)
                const increment = currentProgress < 35
                    ? 8
                    : currentProgress < 75
                        ? 4
                        : currentProgress < TIMETABLE_PROGRESS_FINALIZING_THRESHOLD
                            ? 2
                            : 0.5

                this.timetableCalculationProgress = Math.min(TIMETABLE_PROGRESS_PENDING_LIMIT, currentProgress + increment)
            }, 350)

            if (typeof this.timetableCalculationProgressTimer?.unref === 'function') {
                this.timetableCalculationProgressTimer.unref()
            }
        },
        completeTimetableCalculationProgress() {
            if (!this.timetableCalculationProgressSource) return
            if (
                this.timetableCalculationLoading
                || this.moreCourseAvailabilityLoading
                || this.timetableQualityCountersLoading
                || this.conflictResolutionRecommendationLoading
            ) {
                this.timetableCalculationProgressCompletionPending = true

                return
            }

            this.timetableCalculationProgressCompletionPending = false
            this.clearTimetableCalculationProgressTimer()
            this.timetableCalculationProgress = 100
            this.scheduleTimetableCalculationProgressReset()
        },
        scheduleTimetableCalculationProgressReset() {
            this.clearTimetableCalculationProgressResetTimer()
            this.timetableCalculationProgressResetTimer = globalThis.setTimeout(() => {
                this.timetableCalculationProgress = 0
                this.timetableCalculationProgressCompletionPending = false
                this.timetableCalculationProgressSource = ''
                this.timetableCalculationProgressResetTimer = null
            }, 900)

            if (typeof this.timetableCalculationProgressResetTimer?.unref === 'function') {
                this.timetableCalculationProgressResetTimer.unref()
            }
        },
        clearTimetableCalculationProgressTimer() {
            if (!this.timetableCalculationProgressTimer) return

            globalThis.clearInterval(this.timetableCalculationProgressTimer)
            this.timetableCalculationProgressTimer = null
        },
        clearTimetableCalculationProgressTimers() {
            this.clearTimetableCalculationProgressTimer()
            this.clearTimetableCalculationProgressResetTimer()
        },
        clearTimetableCalculationProgressResetTimer() {
            if (!this.timetableCalculationProgressResetTimer) return

            globalThis.clearTimeout(this.timetableCalculationProgressResetTimer)
            this.timetableCalculationProgressResetTimer = null
        },
        cachedTimetableV2CalculationResult(calculationCacheKey) {
            if (!calculationCacheKey) return null

            const cachedResult = this.timetableCalculationResultCache?.[calculationCacheKey]

            return cachedResult ? this.clonedTimetableV2Value(cachedResult) : null
        },
        rememberTimetableV2CalculationResult(calculationCacheKey, calculationResult) {
            if (!calculationCacheKey || !calculationResult) return

            this.timetableCalculationResultCache = {
                ...(this.timetableCalculationResultCache || {}),
                [calculationCacheKey]: this.clonedTimetableV2Value(calculationResult),
            }
        },
        timetableV2CalculationResultCacheKey(options = {}) {
            const payload = this.timetableV2CalculationPayload({
                ...options,
                includeQualityCounters: false,
            })

            if (!payload.selected_quality_criteria_required) {
                delete payload.evaluation_criteria
            }

            delete payload.include_quality_counters

            return JSON.stringify(payload)
        },
        timetableV2CalculationResultWithAuxiliaryData(calculationResult, previousCalculationResult, refreshAuxiliary = true) {
            if (refreshAuxiliary || !previousCalculationResult) return calculationResult

            const nextCalculationResult = { ...(calculationResult || {}) }

            if (Array.isArray(previousCalculationResult.quality_counters)) {
                nextCalculationResult.quality_counters = previousCalculationResult.quality_counters
            }

            if (Object.prototype.hasOwnProperty.call(previousCalculationResult, 'no_saturday_timetable_count')) {
                nextCalculationResult.no_saturday_timetable_count = previousCalculationResult.no_saturday_timetable_count
            }

            return nextCalculationResult
        },
        requestTimetableV2Calculation(options = {}) {
            return axios.post(
                '/api/admin/students-timetables/robot/backend-timetable',
                this.timetableV2CalculationPayload(options),
            )
        },
        requestMoreCourseAvailability(courses, options = {}) {
            return axios.post(
                '/api/admin/students-timetables/robot/backend-timetable-availability',
                this.timetableV2CalculationPayloadForMoreCourseAvailability(courses, options),
            )
        },
        requestTimetableV2QualityCounters(options = {}) {
            return axios.post(
                '/api/admin/students-timetables/robot/quality-counters',
                this.timetableV2QualityCountersPayload(options),
            )
        },
        timetableV2QualityCountersPayload(options = {}) {
            const payload = this.timetableV2CalculationPayload({
                ...options,
                includeQualityCounters: true,
            })

            delete payload.include_quality_counters
            delete payload.selected_quality_criteria_required

            return payload
        },
        refreshTimetableAuxiliaryInBackground() {
            void this.ensureMoreCourseAvailability()
            void this.loadTimetableQualityCounters()
        },
        resetTimetableQualityCounters() {
            this.timetableQualityCountersLoading = false
            this.timetableQualityCountersRequestId++
        },
        async loadTimetableQualityCounters(options = {}) {
            const requestId = this.timetableQualityCountersRequestId + 1
            this.timetableQualityCountersRequestId = requestId
            this.timetableQualityCountersLoading = true

            try {
                const response = await this.requestTimetableV2QualityCounters(options)
                if (requestId !== this.timetableQualityCountersRequestId) return

                this.applyTimetableQualityCounters(response.data?.data || {})
            } catch {
                if (requestId !== this.timetableQualityCountersRequestId) return

                this.applyTimetableQualityCounters({
                    all_quality_criteria_count: 0,
                    quality_counters: [],
                    selected_quality_criteria_count: 0,
                })
            } finally {
                if (requestId === this.timetableQualityCountersRequestId) {
                    this.timetableQualityCountersLoading = false
                    this.completeTimetableCalculationProgress()
                }
            }
        },
        applyTimetableQualityCounters(qualityCountersResult = {}) {
            if (!this.timetableCalculationResult) return

            this.timetableCalculationResult = {
                ...this.timetableCalculationResult,
                all_quality_criteria_count: Number(qualityCountersResult.all_quality_criteria_count || 0),
                quality_counters: Array.isArray(qualityCountersResult.quality_counters)
                    ? qualityCountersResult.quality_counters
                    : [],
                selected_quality_criteria_count: Number(qualityCountersResult.selected_quality_criteria_count || 0),
            }
        },
        conflictResolutionRecommendationCurrentSignature() {
            const optionKeys = this.selectedTimetableV2ConflictResolutionOptions
                .map((option) => option.selectionKey)
                .filter(Boolean)
                .sort((firstKey, secondKey) => firstKey.localeCompare(secondKey, 'de-AT'))

            if (!optionKeys.length) return ''

            return JSON.stringify({
                optionKeys,
                result: this.selectedTimetableV2ConflictPairs()
                    .map((pair) => pair.key)
                    .filter(Boolean)
                    .sort((firstKey, secondKey) => firstKey.localeCompare(secondKey, 'de-AT')),
            })
        },
        ensureConflictResolutionRecommendations() {
            if (this.selectedTimetableV2ResultType !== 'conflict') {
                this.resetConflictResolutionRecommendations()

                return Promise.resolve({})
            }

            const recommendationSignature = this.conflictResolutionRecommendationCurrentSignature()
            if (!recommendationSignature) {
                this.resetConflictResolutionRecommendations()

                return Promise.resolve({})
            }

            if (
                this.conflictResolutionRecommendationSignature === recommendationSignature
                && (this.conflictResolutionRecommendationLoading || Object.keys(this.conflictResolutionRecommendationByKey).length)
            ) {
                return this.conflictResolutionRecommendationPromise || Promise.resolve(this.conflictResolutionRecommendationByKey)
            }

            return this.loadConflictResolutionRecommendations(recommendationSignature)
        },
        resetConflictResolutionRecommendations() {
            this.conflictResolutionRecommendationByKey = {}
            this.conflictResolutionRecommendationLoading = false
            this.conflictResolutionRecommendationPromise = null
            this.conflictResolutionRecommendationRequestId++
            this.conflictResolutionRecommendationSignature = ''
        },
        loadConflictResolutionRecommendations(recommendationSignature = this.conflictResolutionRecommendationCurrentSignature()) {
            const requestId = this.conflictResolutionRecommendationRequestId + 1
            this.conflictResolutionRecommendationRequestId = requestId
            this.conflictResolutionRecommendationByKey = {}
            this.conflictResolutionRecommendationLoading = true
            this.conflictResolutionRecommendationSignature = recommendationSignature

            const options = this.selectedTimetableV2ConflictResolutionOptions
                .filter((option) => option.selectionKey && option.selectedCourse)

            if (!recommendationSignature || !options.length) {
                this.conflictResolutionRecommendationLoading = false
                this.conflictResolutionRecommendationPromise = null

                return Promise.resolve({})
            }

            this.conflictResolutionRecommendationPromise = Promise.all(options.map((option) =>
                this.requestTimetableV2Calculation({
                    excludedSelectedCourseSelectionKey: option.selectionKey,
                    includeQualityCounters: false,
                    selectedTimetableNumber: 1,
                    selectedTimetableType: 'full_green',
                })
                    .then((response) => [
                        option.selectionKey,
                        this.timetableV2CalculationResultHasValidTimetable(response.data?.data || {}),
                    ])
                    .catch(() => [option.selectionKey, false])))
                .then((recommendationEntries) => {
                    if (requestId !== this.conflictResolutionRecommendationRequestId) return {}

                    const recommendations = Object.fromEntries(recommendationEntries)
                    this.conflictResolutionRecommendationByKey = recommendations

                    return recommendations
                })
                .finally(() => {
                    if (requestId === this.conflictResolutionRecommendationRequestId) {
                        this.conflictResolutionRecommendationLoading = false
                        this.conflictResolutionRecommendationPromise = null
                    }
                })

            return this.conflictResolutionRecommendationPromise
        },
        timetableV2CalculationResultHasValidTimetable(calculationResult = {}) {
            return Number(calculationResult?.full_green_timetable_count || 0) > 0
                || Number(calculationResult?.green_timetable_count || 0) > 0
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
            const selectedCourses = this.timetableV2CalculationSelectedCourses(options)
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
            const maxFreeDaysSelected = this.timetableMaxFreeDaysSelected === true
            const noDistanceLearningSelected = this.timetableNoDistanceLearningSelected === true
            const includeQualityCounters = options?.includeQualityCounters !== false
            const ignoreSelectedQualityCriteria = options?.ignoreSelectedQualityCriteria === true
                || selectedTimetableRequest.selectedTimetableType === 'conflict'
            const qualityCriteriaRequired = !ignoreSelectedQualityCriteria && this.timetableQualityCriteriaRequired
            const selectedQualityCriterionKeys = ignoreSelectedQualityCriteria ? [] : [
                ...(maxFreeDaysSelected ? [TIMETABLE_MAX_FREE_DAYS_CRITERION_KEY] : []),
                ...(noDistanceLearningSelected ? [TIMETABLE_AVOID_DISTANCE_LEARNING_CRITERION_KEY] : []),
            ]
            const evaluationCriteria = includeQualityCounters || qualityCriteriaRequired
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
                selected_course_group_keys: this.timetableV2SelectedOfferedCourseGroupKeys(options),
                deselected_course_group_keys: this.timetableV2DeselectedOfferedCourseGroupKeys(options),
                selected_additional_courses_required: selectedAdditionalCourseKeys.length > 0,
                selected_timetable_type: selectedTimetableRequest.selectedTimetableType,
                selected_timetable_number: selectedTimetableRequest.selectedTimetableNumber,
                selected_quality_criterion_keys: selectedQualityCriterionKeys,
                selected_quality_criteria_required: qualityCriteriaRequired,
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
                    key: TIMETABLE_AVOID_DISTANCE_LEARNING_CRITERION_KEY,
                    enabled: true,
                    priority: 2,
                    option: TIMETABLE_NO_DISTANCE_LEARNING_OPTION_VALUE,
                },
                {
                    key: TIMETABLE_SATURDAY_FREE_CRITERION_KEY,
                    enabled: true,
                    priority: 3,
                    option: null,
                },
                {
                    key: TIMETABLE_STARTS_FROM_PERIOD_10_CRITERION_KEY,
                    enabled: true,
                    priority: 4,
                    option: null,
                },
            ]
        },
        timetableV2CalculationPayloadForMoreCourseAvailability(courses = [], options = {}) {
            const payload = this.timetableV2CalculationPayload({
                includeQualityCounters: false,
            })
            const candidateCourses = (Array.isArray(courses) ? courses : [courses])
                .flatMap((course) => this.moreCourseAvailabilityPayloadCandidates(course, options))
                .filter((course) => course.availability_key && course.course_group && course.course_key)

            payload.candidate_courses = candidateCourses
            payload.availability_only = true

            return payload
        },
        moreCourseAvailabilityPayloadCandidates(course, options = {}) {
            const availabilityKey = this.moreCourseAvailabilityKey(course)
            const courseGroup = this.moreCourseAvailabilityCourseGroup(course)
            const courseKey = this.timetableV2AvailabilityCourseKey(course)
            const parentCandidate = {
                availability_key: availabilityKey,
                course_group: courseGroup,
                course_key: courseKey,
            }

            if (options.includeOffers === false) {
                return [parentCandidate]
            }

            const offeredCourses = this.offeredCourseItemsForSelectedCourse(course)
            const offeredCourseGroupKeys = offeredCourses
                .map((offeredCourse) => offeredCourse?.backendSelectionKey)
                .filter(Boolean)

            return [
                parentCandidate,
                ...offeredCourses.map((offeredCourse) => ({
                    availability_key: this.moreCourseOfferAvailabilityKey(course, offeredCourse),
                    course_group: courseGroup,
                    course_key: courseKey,
                    deselected_course_group_keys: offeredCourseGroupKeys
                        .filter((courseGroupKey) => courseGroupKey !== offeredCourse?.backendSelectionKey),
                })),
            ]
        },
        moreCourseAvailabilityCourseGroup(course) {
            const courseGroup = String(course?.courseGroup || '').trim()
            if (['additional', 'missing'].includes(courseGroup)) return courseGroup

            return 'planned'
        },
        timetableV2CalculationSelectedCourses(options = {}) {
            const excludedSelectedCourseSelectionKey = String(options?.excludedSelectedCourseSelectionKey || '').trim()
            const selectedCourses = (Array.isArray(this.selectedCourseItems) ? this.selectedCourseItems : [])
                .filter((course) => course.selectionKey !== excludedSelectedCourseSelectionKey)
                .filter((course) => !this.offeredCourseItemsAllDeselectedForCalculation(course))
            const moreSelectedCourses = this.timetableV2CalculationSelectedMoreCourses()
                .filter((course) => course.selectionKey !== excludedSelectedCourseSelectionKey)

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
            const selection = this.timetableV2SelectionWithCourseDefaults({
                ...this.defaultNoStudentTimetableV2Selection(),
                ...this.currentTimetableV2SelectionForCourseState(),
            }, this.storedTimetableStudentContext?.courses || {})
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
            const availableTimes = schoolHourValues.length
                ? this.uniqueValues(schoolHourValues).sort((firstHour, secondHour) => firstHour - secondHour)
                : Array.from({ length: 20 }, (_, index) => index + 1)

            return this.timetableStartsFromPeriod10Selected
                ? availableTimes.filter((hour) => hour >= 10)
                : availableTimes
        },
        timetableV2CalculationCourseKey(course) {
            const courseKey = String(course?.key || '').trim()
            const courseCode = this.normalizedCourseCode(course?.code || course?.label || '')
            const subjectRowCourse = this.subjectRowCourseForCourseCode(courseCode)

            return courseKey && !this.syntheticCourseKey(courseKey)
                ? courseKey
                : subjectRowCourse?.key || courseCode || courseKey
        },
        timetableV2AvailabilityCourseKey(course) {
            const courseKey = this.timetableV2CalculationCourseKey(course)
            const courseCode = this.normalizedCourseCode(course?.code || course?.label || '')
            if (!courseKey || !courseCode) return courseKey

            const escapedCourseCode = courseCode.replace(/[.*+?^${}()|[\]\\]/gu, '\\$&')
            if (!new RegExp(`^${escapedCourseCode}-\\d+$`, 'u').test(courseKey)) return courseKey

            const subjectRowCourse = this.subjectRowCourseForCourseCode(courseCode)

            return subjectRowCourse?.key || courseCode
        },
        syntheticCourseKey(courseKey) {
            return /^(completed|missing|subject-row)-/iu.test(String(courseKey || '').trim())
        },
        timetableV2SelectedOfferedCourseGroupKeys(options = {}) {
            const excludedSelectedCourseSelectionKey = String(options?.excludedSelectedCourseSelectionKey || '').trim()
            const selectedCourseGroupKeys = (Array.isArray(this.selectedCourseItems) ? this.selectedCourseItems : [])
                .filter((course) => course.selectionKey !== excludedSelectedCourseSelectionKey)
                .flatMap((course) => this.offeredCourseItemsForSelectedCourse(course)
                    .filter((offeredCourse) => this.offeredCourseSelectedForCalculation(offeredCourse, course))
                    .map((offeredCourse) => offeredCourse.backendSelectionKey))
                .filter(Boolean)
            const moreCourseGroupKeys = this.timetableV2CalculationSelectedMoreCourses()
                .flatMap((course) => this.offeredCourseItemsForSelectedCourse(course)
                    .filter((offeredCourse) => this.moreOfferedCourseSelected(offeredCourse))
                    .map((offeredCourse) => offeredCourse.backendSelectionKey))
                .filter(Boolean)

            return this.uniqueValues([
                ...selectedCourseGroupKeys,
                ...moreCourseGroupKeys,
            ])
        },
        timetableV2DeselectedOfferedCourseGroupKeys(options = {}) {
            const excludedSelectedCourseSelectionKey = String(options?.excludedSelectedCourseSelectionKey || '').trim()
            const selectedCourseGroupKeys = (Array.isArray(this.selectedCourseItems) ? this.selectedCourseItems : [])
                .filter((course) => course.selectionKey !== excludedSelectedCourseSelectionKey)
                .flatMap((course) => this.offeredCourseItemsForSelectedCourse(course)
                    .filter((offeredCourse) => !this.offeredCourseSelectedForCalculation(offeredCourse, course))
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
            if (this.reviewFlowVisible) return false

            const preselectionSignature = this.courseLimitPreselectionSignature
            if (!preselectionSignature) return false

            const currentTimetableV2Selection = this.storedTimetableV2Selection || {}
            const courseSelections = this.courseSelectionsForCourseLimitPreselection(force ? {} : this.courseSelectionOverrides)
            const currentSelections = JSON.stringify(this.selectionSignatureEntries(this.courseSelectionOverrides))
            const preselectedSelections = JSON.stringify(this.selectionSignatureEntries(courseSelections))
            if (
                !force
                && currentTimetableV2Selection.courseLimitPreselectionKey === preselectionSignature
                && currentSelections === preselectedSelections
            ) {
                return false
            }

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
                ...(force || this.storedAdaptedTimetableV2Selection
                    ? { adaptedTimetableV2Selection: force ? null : this.storedAdaptedTimetableV2Selection }
                    : {}),
                transferredStudentContext: this.storedTimetableStudentContext,
            })

            return true
        },
        courseSelectionsForCourseLimitPreselection(courseSelections = {}) {
            const nextCourseSelections = this.courseSelectionOverridesForCurrentCourseItems(courseSelections)

            this.courseLimitDefaultSelectedCourseItems().forEach((courseItem) => {
                this.courseSelectionKeys(courseItem.course, courseItem.courseGroup).forEach((selectionKey) => {
                    if (nextCourseSelections[selectionKey] === true) {
                        delete nextCourseSelections[selectionKey]
                    }
                })
            })

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
        courseSelectionOverridesForCurrentCourseItems(courseSelections = {}) {
            const currentSelectionKeys = new Set(this.courseLimitPreselectionCourseItems()
                .concat(this.storedAdditionalCourseItems.map((course) => ({ course, courseGroup: 'additional' })))
                .filter((courseItem) => !this.courseItemUnavailable(courseItem.course, courseItem.courseGroup))
                .flatMap((courseItem) => this.courseSelectionKeys(courseItem.course, courseItem.courseGroup))
                .filter(Boolean))

            return Object.fromEntries(
                Object.entries(this.normalizedCourseSelections(courseSelections))
                    .filter(([selectionKey]) => currentSelectionKeys.has(selectionKey)),
            )
        },
        courseLimitPreselectionCourseItems() {
            return [
                ...this.storedCompletedCourseItems.map((course) => ({ course, courseGroup: 'completed' })),
                ...this.storedMissingCourseCardItems.map((course) => ({ course, courseGroup: 'missing' })),
                ...this.storedSemesterCourseItems.map((course) => ({ course, courseGroup: 'semester' })),
                ...this.storedPlannedCourseItems.map((course) => ({ course, courseGroup: 'planned' })),
            ]
        },
        courseLimitDefaultSelectedCourseItems() {
            return this.courseLimitPreselectionCourseItems()
                .filter((courseItem) => this.courseItemDefaultSelected(courseItem.course, courseItem.courseGroup))
        },
        courseLimitSelectedCourseItems(courseSelections = {}) {
            return this.courseLimitPreselectionCourseItems().filter((courseItem) => this.courseSelectedBySelections(
                courseItem.course,
                courseItem.courseGroup,
                courseSelections,
            ))
        },
        courseLimitPreselectionCandidates(courseSelections = {}) {
            return this.rankedCourseLimitPreselectionItems(this.selectedSemesterCourseLimitItems(), courseSelections)
        },
        courseLimitDuplicateModuleCourseItems(courseSelections = {}) {
            return this.duplicateModuleCourseItems(this.plannedCourseLimitItems(), courseSelections)
        },
        selectedSemesterCourseLimitItems() {
            return [
                ...this.storedMissingCourseCardItems
                    .filter((course) => this.courseIncludedInSelectedStudentDefaultSemester(course))
                    .map((course) => ({ course, courseGroup: 'missing' })),
                ...this.storedSemesterCourseItems.map((course) => ({ course, courseGroup: 'semester' })),
            ]
        },
        plannedCourseLimitItems() {
            return [
                ...this.storedPlannedCourseItems.map((course) => ({ course, courseGroup: 'planned' })),
            ]
        },
        duplicateModuleCourseItemsForGroup(courses, courseGroup, courseSelections = {}) {
            return this.duplicateModuleCourseItems(
                (Array.isArray(courses) ? courses : []).map((course) => ({ course, courseGroup })),
                courseSelections,
            )
        },
        duplicateModuleCourseItems(courseItems, courseSelections = {}) {
            const selectedCourseItemsByBase = new Map()
            const normalizedCourseItems = Array.isArray(courseItems) ? courseItems : []

            normalizedCourseItems
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
            return this.rankedCourseLimitPreselectionItems(
                (Array.isArray(courses) ? courses : []).map((course) => ({ course, courseGroup })),
                courseSelections,
            )
        },
        rankedCourseLimitPreselectionItems(courseItems, courseSelections = {}) {
            return (Array.isArray(courseItems) ? courseItems : [])
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
            const selectionKeys = this.courseSelectionKeys(course, courseGroup)
            const selectionKey = selectionKeys[0] || ''
            const explicitSelection = this.courseSelectionExplicitValue(course, courseGroup, courseSelections)

            if (explicitSelection === false) return false
            if (this.courseItemUnavailableReason(course, courseGroup) === 'prerequisite') return false
            if (explicitSelection === true) return true
            if (this.courseItemUnavailable(course, courseGroup)) return false
            if (this.courseSelectionBlockedByOpenPrerequisiteCourse(course, courseGroup)) return false
            if (!selectionKey) return this.courseItemDefaultSelected(course, courseGroup)

            return this.courseItemDefaultSelected(course, courseGroup)
        },
        selectedCourseItemsForCourseSelections(courseSelections = this.courseSelectionOverrides) {
            const selectedCourseItems = [
                ...this.storedCompletedCourseItems
                    .filter((course) => this.courseSelectedBySelections(course, 'completed', courseSelections))
                    .map((course) => this.selectedCourseListItem(course, 'completed')),
                ...this.storedMissingCourseCardItems
                    .filter((course) => this.courseSelectedBySelections(course, 'missing', courseSelections))
                    .map((course) => this.selectedCourseListItem(course, 'missing')),
                ...this.storedSemesterCourseItems
                    .filter((course) => this.courseSelectedBySelections(course, 'semester', courseSelections))
                    .map((course) => this.selectedCourseListItem(course, 'semester')),
                ...this.storedPlannedCourseItems
                    .filter((course) => this.courseSelectedBySelections(course, 'planned', courseSelections))
                    .map((course) => this.selectedCourseListItem(course, 'planned')),
                ...this.storedAdditionalCourseItems
                    .filter((course) => this.courseSelectedBySelections(course, 'additional', courseSelections))
                    .map((course) => this.selectedCourseListItem(course, 'additional')),
            ]

            return this.sortedCourseItems([
                ...selectedCourseItems,
                ...this.selectedFallbackCourseItemsForCourseSelections(courseSelections, selectedCourseItems),
            ])
        },
        selectedFallbackCourseItemsForCourseSelections(courseSelections = {}, selectedCourseItems = []) {
            const selectedCourseCodes = this.courseCodeSet(selectedCourseItems)

            return this.selectionSignatureEntries(courseSelections)
                .filter(([, selected]) => selected === true)
                .map(([selectionKey]) => this.selectedFallbackCourseItem(selectionKey, selectedCourseCodes))
                .filter(Boolean)
        },
        selectedFallbackCourseItem(selectionKey, selectedCourseCodes = new Set()) {
            const courseGroup = this.selectedFallbackCourseGroup(selectionKey)
            if (!courseGroup) return null

            const courseKey = String(selectionKey || '').slice(courseGroup.length + 1).trim()
            const courseCode = this.courseSelectionCourseCodeFromValue(courseKey) || this.normalizedCourseCode(courseKey)
            if (!courseCode) return null

            const subjectRowCourse = this.subjectRowCourseForCourseCode(courseCode)
            const fallbackCourse = subjectRowCourse ? {
                ...subjectRowCourse,
                code: courseCode,
                label: courseCode,
            } : {
                code: courseCode,
                label: courseCode,
            }
            if (this.courseCodeSetContainsCourse(selectedCourseCodes, fallbackCourse)) return null
            if (!this.selectedFallbackCourseVisible(fallbackCourse, courseGroup)) return null

            return {
                ...this.selectedCourseListItem(fallbackCourse, courseGroup),
                selectionKey,
            }
        },
        selectedFallbackCourseGroup(selectionKey) {
            const courseGroup = String(selectionKey || '').split(':')[0] || ''

            return ['additional', 'semester', 'planned', 'completed', 'missing'].includes(courseGroup)
                ? courseGroup
                : ''
        },
        selectedFallbackCourseVisible(course, courseGroup) {
            if (courseGroup === 'additional') {
                if (this.courseCodeSetContainsCourse(this.courseCodeSet(this.storedCompletedCourseItems), course)) return false
                if (this.courseCodeSetContainsCourse(this.courseCodeSet(this.storedMissingCourseCardItems), course)) return false

                return !this.courseMissingCompletedPrerequisite(course, courseGroup)
            }

            return (this.timetableCalculationVisible || this.adoptedTimetableVisible)
                && this.courseItemInSelectedTimetableV2(course)
        },
        courseLimitPreselectionSignaturePart(course, courseGroup) {
            return [
                courseGroup,
                this.courseSelectionKey(course, courseGroup),
                this.courseHoursNumber(course),
                this.courseSemesterNumber(course),
                this.courseModuleNumber(course),
                this.courseItemUnavailable(course, courseGroup) ? 'unavailable' : 'available',
            ].join(':')
        },
        selectedCourseListItem(course, courseGroup) {
            const hours = this.courseHoursNumber(course)
            const label = this.courseDisplayLabel(course?.label || course?.code || course?.name || '')

            return {
                selectionKey: this.courseSelectionKey(course, courseGroup) || `${courseGroup}:${course?.key || course?.label}`,
                courseGroup,
                code: this.normalizedCourseCode(course?.code || label),
                hours,
                key: String(course?.key || course?.code || label).trim(),
                label,
                meta: String(course?.hoursMeta || (hours ? `${this.formatHours(hours)} Std.` : '') || course?.meta || '').trim(),
            }
        },
        moreCoursesCardItem(course, courseGroup) {
            return {
                ...this.selectedCourseListItem(course, courseGroup),
                courseGroupLabel: this.moreCoursesCardItemGroupLabel(courseGroup),
            }
        },
        moreAdoptedCourseCandidateItemsForGroup(courseGroup) {
            if (courseGroup === 'completed') {
                return this.storedCompletedCourseItems.map((course) => this.moreCoursesCardItem(course, courseGroup))
            }

            if (courseGroup === 'missing') {
                return this.storedMissingCourseCardItems.map((course) => this.moreCoursesCardItem(course, courseGroup))
            }

            if (courseGroup === 'planned') {
                return [
                    ...this.storedSemesterCourseItems.map((course) => this.moreCoursesCardItem(course, 'semester')),
                    ...this.storedPlannedCourseItems.map((course) => this.moreCoursesCardItem(course, courseGroup)),
                ]
                    .filter((course) => this.offeredCourseItemsForSelectedCourse(course).length > 0)
            }

            if (courseGroup === 'additional') {
                return this.storedAdditionalCourseItems.map((course) => this.moreCoursesCardItem(course, courseGroup))
            }

            if (courseGroup === 'more') {
                return this.moreExistingCourseItems()
            }

            return []
        },
        moreAdoptedCourseCandidateItemsForCard(card) {
            if (!card) return []

            return this.uniqueMoreCourseItems(card.courseGroups
                .flatMap((courseGroup) => this.moreAdoptedCourseCandidateItemsForGroup(courseGroup))
                .filter((course) => !this.courseItemInSelectedTimetableV2(course)))
        },
        moreAdoptedCourseCardDisabled(card) {
            return this.moreAdoptedCourseCandidateItemsForCard(card).length === 0
        },
        moreExistingCourseItems() {
            const mentionedCourseCodes = this.mentionedMoreCourseCodes()
            const courseCodes = this.moreExistingCourseCodes()

            return this.sortedCourseItems(courseCodes
                .filter((courseCode) => !mentionedCourseCodes.has(this.normalizedCourseCode(courseCode)))
                .map((courseCode) => this.moreExistingCourseItem(courseCode)))
        },
        moreExistingCourseTopLevelItems(courses) {
            const coursesByBaseKey = new Map()
            const sourceCourses = Array.isArray(courses) ? courses : []

            sourceCourses.forEach((course) => {
                const baseKey = this.moreExistingCourseBaseKey(course)
                if (!baseKey) return

                const entry = coursesByBaseKey.get(baseKey) || {
                    courseGroup: 'more-base',
                    courseGroupLabel: 'Modulgruppe',
                    courses: [],
                    isTopLevelCourseGroup: true,
                    key: `more-base:${baseKey}`,
                    label: this.moreExistingCourseBaseLabel(baseKey),
                    selectionKey: `more-base:${baseKey}`,
                    topLevelCourseKey: baseKey,
                }

                entry.courses.push(course)
                coursesByBaseKey.set(baseKey, entry)
            })

            return this.sortedCourseItems([...coursesByBaseKey.values()]
                .map((entry) => ({
                    ...entry,
                    courses: this.sortedCourseItems(entry.courses),
                    meta: `${this.formatNumber(entry.courses.length)} ${entry.courses.length === 1 ? 'Modul' : 'Module'}`,
                })))
        },
        moreExistingCourseBaseKey(course) {
            const parts = this.courseCodeModuleParts(course?.code || course?.label || course?.name || '')

            return parts.base || this.normalizedCourseCode(course?.code || course?.label || course?.name || '')
        },
        moreExistingCourseBaseLabel(baseKey) {
            return this.courseDisplayLabel(baseKey)
        },
        moreExistingCourseCodes() {
            const courseGroups = Array.isArray(this.courseGroups) ? this.courseGroups : []

            const courseCodes = this.uniqueValues(courseGroups
                .flatMap((courseGroup) => this.explicitCourseGroupCodes(courseGroup))
                .map((courseCode) => this.normalizedCourseCode(courseCode))
                .filter(Boolean))

            return this.withoutAggregateExistingCourseCodes(courseCodes)
        },
        withoutAggregateExistingCourseCodes(courseCodes) {
            const normalizedCourseCodes = this.uniqueValues((Array.isArray(courseCodes) ? courseCodes : [])
                .map((courseCode) => this.normalizedCourseCode(courseCode))
                .filter(Boolean))
            const moduleBaseAliases = new Set(normalizedCourseCodes
                .map((courseCode) => this.courseCodeModuleParts(courseCode))
                .filter((parts) => parts.module)
                .flatMap((parts) => this.aggregateExistingCourseBaseAliases(parts.base)))

            return normalizedCourseCodes.filter((courseCode) => {
                const parts = this.courseCodeModuleParts(courseCode)
                if (parts.module) return true

                const aggregateAliases = this.aggregateExistingCourseBaseAliases(parts.base)
                if (!aggregateAliases.length) return true

                return !aggregateAliases.some((aggregateAlias) => moduleBaseAliases.has(aggregateAlias))
            })
        },
        explicitCourseGroupCodes(courseGroup) {
            const moduleCodes = this.courseCodeTokensFromValue(courseGroup?.module_code)
            if (moduleCodes.length) return this.uniqueValues(moduleCodes)

            return this.uniqueValues([
                courseGroup?.course,
                courseGroup?.subject,
            ].flatMap((value) => this.courseCodeTokensFromValue(value)))
        },
        aggregateExistingCourseBaseAliases(base) {
            const normalizedBase = this.normalizedCourseCode(base)
            const aliases = {
                CH: ['CH'],
                L: ['LET', 'LPT'],
                LET: ['L', 'LPT'],
                LPT: ['L', 'LET'],
                OEK: ['OEKO', 'OKON', 'OKO'],
                OEKO: ['OEK', 'OKON', 'OKO'],
                OKO: ['OEK', 'OEKO', 'OKON'],
                OKON: ['OEK', 'OEKO', 'OKO'],
                S: ['SPA'],
                SPA: ['S'],
            }

            if (!aliases[normalizedBase]) return []

            return this.uniqueValues([normalizedBase, ...aliases[normalizedBase]].filter(Boolean))
        },
        mentionedMoreCourseCodes() {
            return new Set([
                ...this.storedCompletedCourseItems,
                ...this.storedMissingCourseCardItems,
                ...this.storedSemesterCourseItems,
                ...this.storedPlannedCourseItems,
                ...this.storedAdditionalCourseItems,
            ].flatMap((course) => this.courseCodeAliases({ code: course?.code || course?.label })))
        },
        moreExistingCourseItem(courseCode) {
            const normalizedCourseCode = this.normalizedCourseCode(courseCode)
            const hours = this.subjectRowHoursForCourseCode(normalizedCourseCode)

            return this.moreCoursesCardItem({
                code: normalizedCourseCode,
                hours,
                key: normalizedCourseCode,
                label: normalizedCourseCode,
                meta: hours ? `${this.formatHours(hours)} Std.` : '',
            }, 'more')
        },
        uniqueMoreCourseItems(courses) {
            const coursesBySelectionKey = new Map()

            courses.forEach((course) => {
                const selectionKey = this.moreCourseAvailabilityKey(course)
                if (!selectionKey || coursesBySelectionKey.has(selectionKey)) return

                coursesBySelectionKey.set(selectionKey, course)
            })

            return [...coursesBySelectionKey.values()]
        },
        moreCoursesCardItemGroupLabel(courseGroup) {
            if (courseGroup === 'additional') return 'Zusätzlich'
            if (courseGroup === 'completed') return 'Abgeschlossen'
            if (courseGroup === 'missing') return 'Fehlend'
            if (courseGroup === 'more') return 'Weiteres Modul'
            if (courseGroup === 'semester') return 'Aktuell'
            if (courseGroup === 'planned') return 'Vorgesehen'

            return 'Vorgesehen'
        },
        moreCoursesCategoryCardForCourse(course) {
            const selectionKey = course?.selectionKey || this.courseSelectionKey(course, course?.courseGroup)
            if (!selectionKey) return null

            return this.moreCoursesCategoryCards.find((card) => card.items
                .some((cardCourse) => cardCourse.selectionKey === selectionKey))
                || null
        },
        moreCoursesCategoryCardDisabled(card) {
            return !Array.isArray(card?.items) || card.items.length === 0
        },
        moreCoursesDisplayContext() {
            const selectedMoreCourseKey = String(this.selectedMoreCourseKey || '').trim()
            const selectedMoreCourse = selectedMoreCourseKey
                ? this.moreCoursesCardItemsBySelectionKey.get(selectedMoreCourseKey) || null
                : null
            const restorableMoreCourseAvailabilitySignatures = this.restorableMoreCourseAvailabilitySignatures || {}
            const restorableAvailabilitySignature = Object.keys(restorableMoreCourseAvailabilitySignatures).length
                ? this.moreCourseAvailabilityCurrentSignature()
                : ''
            const selectedMoreCourseContext = selectedMoreCourse
                ? this.moreOfferedCourseSelectionContext(selectedMoreCourse, { restorableAvailabilitySignature })
                : null

            return {
                availabilityLoading: this.moreCourseAvailabilityLoading,
                instructionFilters: this.instructionCourseFiltersForSelection(
                    this.currentTimetableV2SelectionForCourseState(),
                ),
                moreCourseAvailabilityByKey: this.moreCourseAvailabilityByKey,
                moreOfferedCourseSelections: this.currentMoreOfferedCourseSelectionOverrides(),
                offeredCourseSelections: this.currentOfferedCourseSelectionOverrides(),
                restorableAvailabilitySignature,
                selectedMoreCourse,
                selectedMoreCourseHasSelectedOffers: selectedMoreCourse
                    ? this.offeredCourseItemsForSelectedCourse(selectedMoreCourse)
                        .some((offeredCourse) => this.moreOfferedCourseSelectedWithContext(
                            offeredCourse,
                            selectedMoreCourse,
                            selectedMoreCourseContext,
                        ))
                    : false,
                selectedMoreCourseKey,
            }
        },
        displayedMoreCourseCardItem(course, context = {}) {
            const availabilityKey = this.moreCourseAvailabilityKey(course)
            const restorable = this.moreCourseRestorableWithContext(course, context, availabilityKey)
            const unavailable = this.moreCourseUnavailableWithContext(course, context, {
                availabilityKey,
                restorable,
            })
            const locked = this.moreCourseSelectionLockedWithContext(course, context, availabilityKey)
            const disabled = context.availabilityLoading === true || locked
            const color = this.moreCourseChipColorWithContext(course, context, {
                availabilityKey,
                restorable,
                unavailable,
            })
            const active = context.selectedMoreCourseKey === availabilityKey

            return {
                ...course,
                active,
                ariaDisabled: disabled ? 'true' : 'false',
                ariaExpanded: active ? 'true' : 'false',
                classes: [
                    `students-timetable-v2-more-courses-card__course--${color}`,
                    {
                        'students-timetable-v2-more-courses-card__course--active': active,
                        'students-timetable-v2-more-courses-card__course--disabled': disabled,
                        'students-timetable-v2-more-courses-card__course--unavailable': unavailable,
                    },
                ],
                color,
                disabled,
                title: unavailable ? '!! Konflikte !!' : undefined,
                unavailable,
            }
        },
        moreCourseRestorableWithContext(course, context = {}, availabilityKey = this.moreCourseAvailabilityKey(course)) {
            if (!availabilityKey || !context.restorableAvailabilitySignature) return false

            return this.restorableMoreCourseAvailabilitySignatures?.[availabilityKey]
                === context.restorableAvailabilitySignature
        },
        moreCourseUnavailableWithContext(course, context = {}, state = {}) {
            const restorable = Object.prototype.hasOwnProperty.call(state, 'restorable')
                ? state.restorable === true
                : this.moreCourseRestorableWithContext(course, context, state.availabilityKey)
            if (restorable) return false

            const availabilityKey = state.availabilityKey || this.moreCourseAvailabilityKey(course)
            if (!availabilityKey) return false

            return (context.moreCourseAvailabilityByKey || this.moreCourseAvailabilityByKey)?.[availabilityKey] === false
        },
        moreCourseSelectionLockedWithContext(course, context = {}, availabilityKey = this.moreCourseAvailabilityKey(course)) {
            if (!availabilityKey || !context.selectedMoreCourseKey) return false
            if (!context.selectedMoreCourse || context.selectedMoreCourseHasSelectedOffers !== true) return false

            return context.selectedMoreCourseKey !== availabilityKey
        },
        moreCourseChipColorWithContext(course, context = {}, state = {}) {
            const availabilityState = this.moreCourseOfferAvailabilityStateWithContext(course, context, state)

            if (availabilityState === 'all') return 'success'
            if (availabilityState === 'some') return 'warning'
            if (availabilityState === 'none') return 'error'

            return this.moreCourseUnavailableWithContext(course, context, state) ? 'error' : 'success'
        },
        moreCourseOfferAvailabilityStateWithContext(course, context = {}, state = {}) {
            const restorable = Object.prototype.hasOwnProperty.call(state, 'restorable')
                ? state.restorable === true
                : this.moreCourseRestorableWithContext(course, context, state.availabilityKey)
            if (restorable) return 'all'

            const unavailable = Object.prototype.hasOwnProperty.call(state, 'unavailable')
                ? state.unavailable === true
                : this.moreCourseUnavailableWithContext(course, context, {
                    availabilityKey: state.availabilityKey,
                    restorable,
                })
            const offeredCourses = this.offeredCourseItemsForSelectedCourse(course)
            if (!offeredCourses.length) return unavailable ? 'none' : 'unknown'

            const moreOfferedCourseSelections = context.moreOfferedCourseSelections || this.currentMoreOfferedCourseSelectionOverrides()
            const offeredCourseSelectionKeys = offeredCourses
                .map((offeredCourse) => offeredCourse?.selectionKey || this.offeredCourseSelectionKey(offeredCourse, course))
                .filter(Boolean)
            const explicitSelections = offeredCourseSelectionKeys
                .map((selectionKey) => moreOfferedCourseSelections[selectionKey])
                .filter((selected) => selected === true || selected === false)
            const hasExplicitOfferSelection = explicitSelections.length > 0
            const selectionContext = {
                ...context,
                moreCourseRestorable: restorable,
                moreCourseSelectedForAdding: context.selectedMoreCourseKey === (state.availabilityKey || this.moreCourseAvailabilityKey(course)),
                moreCourseUnavailable: unavailable,
                usesExplicitSelectedOfferList: hasExplicitOfferSelection,
            }
            const availabilityByKey = context.moreCourseAvailabilityByKey || this.moreCourseAvailabilityByKey
            const offerAvailabilityValues = offeredCourses.map((offeredCourse) => {
                if (hasExplicitOfferSelection && !this.moreOfferedCourseSelectedWithContext(offeredCourse, course, selectionContext)) return false

                const availability = availabilityByKey[this.moreCourseOfferAvailabilityKey(course, offeredCourse)]
                if (availability === true || availability === false) return availability
                if (hasExplicitOfferSelection && !unavailable) return true

                return null
            })

            if (offerAvailabilityValues.some((availability) => availability === null)) {
                return unavailable ? 'none' : 'unknown'
            }

            const availableOfferCount = offerAvailabilityValues.filter((available) => available === true).length

            if (availableOfferCount === offeredCourses.length) return 'all'
            if (availableOfferCount > 0) return 'some'

            return 'none'
        },
        openMoreCoursesCategoryCard(card) {
            if (this.moreCoursesCategoryCardDisabled(card)) return

            const cardKey = String(card?.key || '').trim()
            if (!cardKey) return

            this.activeMoreCoursesCategoryCardKey = cardKey
            this.selectedMoreCourseKey = ''
            this.syncTimetableV2Route({ replace: true })
        },
        closeMoreCoursesCategoryCard() {
            this.activeMoreCoursesCategoryCardKey = ''
            this.closeMoreCourseOffers()
        },
        openMoreAdoptedCourseCard(card) {
            if (this.moreAdoptedCourseCardDisabled(card)) return

            const cardKey = String(card?.key || '').trim()
            if (!cardKey) return

            this.activeMoreAdoptedCourseCardKey = cardKey
            this.activeMoreExistingCourseBaseKey = ''
            this.selectedMoreCourseKey = ''
        },
        closeMoreAdoptedCourseCard() {
            if (this.activeMoreExistingCourseBaseKey) {
                this.activeMoreExistingCourseBaseKey = ''
                this.selectedMoreCourseKey = ''

                return
            }

            this.activeMoreAdoptedCourseCardKey = ''
            this.activeMoreExistingCourseBaseKey = ''
            this.selectedMoreCourseKey = ''
        },
        openMoreAdoptedCourseItem(course) {
            if (course?.isTopLevelCourseGroup) {
                this.activeMoreExistingCourseBaseKey = course.topLevelCourseKey
                this.selectedMoreCourseKey = ''

                return
            }

            this.openMoreAdoptedCourseOffers(course)
        },
        moreAdoptedCourseItemActive(course) {
            if (course?.isTopLevelCourseGroup) {
                return this.activeMoreExistingCourseBaseKey === course.topLevelCourseKey
            }

            return this.selectedMoreAdoptedCourseItem?.selectionKey === course?.selectionKey
        },
        openMoreAdoptedCourseOffers(course) {
            const selectionKey = course?.selectionKey || this.courseSelectionKey(course, course?.courseGroup)
            if (!selectionKey) return

            this.selectedMoreCourseKey = this.selectedMoreCourseKey === selectionKey
                ? ''
                : selectionKey
        },
        moreAdoptedCourseColor(course) {
            const offeredCourses = this.offeredCourseItemsForSelectedCourse(course)
            if (!offeredCourses.length) return 'success'

            const availableOfferCount = offeredCourses
                .filter((offeredCourse) => !this.moreAdoptedCourseOfferCollidesForCourse(offeredCourse, course))
                .length

            if (availableOfferCount === offeredCourses.length) return 'success'
            if (availableOfferCount > 0) return 'warning'

            return 'error'
        },
        moreAdoptedCourseOfferCollides(offeredCourse) {
            return this.moreAdoptedCourseOfferCollidesForCourse(offeredCourse, this.selectedMoreAdoptedCourseItem)
        },
        moreAdoptedCourseOfferCollidesForCourse(offeredCourse, selectedCourse) {
            const slots = this.adoptedTimetableCalculationResult?.selected_timetable?.slots
            if (!selectedCourse || !offeredCourse || !slots || typeof slots !== 'object') return false

            return this.adoptedTimetableSlotsFromOfferedCourse(offeredCourse, selectedCourse)
                .some((slot) => this.adoptedTimetableSlotCollidesWithExistingCourse(slot, selectedCourse))
        },
        adoptedTimetableSlotCollidesWithExistingCourse(slot, selectedCourse) {
            const slots = this.adoptedTimetableCalculationResult?.selected_timetable?.slots
            if (!slots || typeof slots !== 'object') return false

            const slotKey = `${Number(slot?.courseGroup?.weekday || 0)}-${Number(slot?.courseGroup?.hour || 0)}`
            if (!slotKey || slotKey === '0-0') return false

            const existingSlot = slots[slotKey]
            if (!existingSlot) return false

            return this.adoptedTimetableSlotEntries(existingSlot)
                .some((entry) => !this.timetableSlotMatchesCourse(entry, selectedCourse))
        },
        adoptedTimetableSlotEntries(slot) {
            if (!slot) return []

            return [
                slot,
                ...(Array.isArray(slot.sameSlotEntries) ? slot.sameSlotEntries : []),
                ...(Array.isArray(slot.conflicts) ? slot.conflicts : []),
            ]
        },
        insertMoreAdoptedCourseOfferIntoTimetable(offeredCourse) {
            const selectedCourse = this.selectedMoreAdoptedCourseItem
            const slots = this.adoptedTimetableCalculationResult?.selected_timetable?.slots
            if (!selectedCourse || !offeredCourse || !slots || typeof slots !== 'object') return

            this.removeCourseFromAdoptedTimetableResult(selectedCourse)

            this.adoptedTimetableSlotsFromOfferedCourse(offeredCourse, selectedCourse)
                .forEach((slot) => this.insertAdoptedTimetableSlot(slot))

            this.saveCourseItemSelection(selectedCourse, true)
            this.activeMoreAdoptedCourseCardKey = ''
            this.activeMoreExistingCourseBaseKey = ''
            this.selectedMoreCourseKey = ''
        },
        adoptedTimetableSlotsFromOfferedCourse(offeredCourse, selectedCourse) {
            const scheduleSlots = Array.isArray(offeredCourse?.scheduleSlots) && offeredCourse.scheduleSlots.length
                ? offeredCourse.scheduleSlots
                : [{
                    hour: offeredCourse?.hour,
                    weekday: offeredCourse?.weekday,
                }]

            return scheduleSlots
                .map((scheduleSlot) => this.adoptedTimetableSlotFromOfferedCourse(offeredCourse, selectedCourse, scheduleSlot))
                .filter(Boolean)
        },
        adoptedTimetableSlotFromOfferedCourse(offeredCourse, selectedCourse, scheduleSlot) {
            const weekday = Number(scheduleSlot?.weekday || offeredCourse?.weekday || 0)
            const hour = Number(scheduleSlot?.hour || offeredCourse?.hour || 0)
            if (!Number.isFinite(weekday) || weekday <= 0 || !Number.isFinite(hour) || hour <= 0) return null

            const courseGroup = {
                ...(offeredCourse?.courseGroup || {}),
                hour,
                weekday,
            }

            if (Number.isInteger(Number(scheduleSlot?.recurrenceInterval)) && Number(scheduleSlot.recurrenceInterval) > 0) {
                courseGroup.recurrence_interval = Number(scheduleSlot.recurrenceInterval)
            }

            if (scheduleSlot?.recurrenceLabel) {
                courseGroup.recurrence_label = scheduleSlot.recurrenceLabel
            }

            return {
                code: this.normalizedCourseCode(selectedCourse?.code || selectedCourse?.label || offeredCourse?.code),
                courseGroup,
                dateRangeLabel: scheduleSlot?.dateRangeLabel || this.adoptedTimetableOfferDateRangeLabel(offeredCourse),
                isAdditionalCourse: selectedCourse?.courseGroup === 'additional',
                isDistanceLearningCourse: offeredCourse?.distanceLearning === true,
                isKompaktunterrichtCourse: offeredCourse?.isKompaktunterricht === true,
                key: [
                    offeredCourse?.selectionKey || offeredCourse?.key,
                    weekday,
                    hour,
                ].filter(Boolean).join('|'),
                sourceLabel: offeredCourse?.groupSelectionLabel || offeredCourse?.name || offeredCourse?.code || selectedCourse?.label || '',
            }
        },
        adoptedTimetableOfferDateRangeLabel(offeredCourse) {
            if (!offeredCourse?.courseGroup?.is_block && !String(offeredCourse?.courseGroup?.block_label || '').trim()) return ''

            return this.courseGroupDateRangeLabel(offeredCourse.courseGroup)
        },
        insertAdoptedTimetableSlot(slot) {
            const slots = this.adoptedTimetableCalculationResult?.selected_timetable?.slots
            if (!slots || typeof slots !== 'object') return

            const slotKey = `${Number(slot?.courseGroup?.weekday || 0)}-${Number(slot?.courseGroup?.hour || 0)}`
            if (!slotKey || slotKey === '0-0') return

            const existingSlot = slots[slotKey]
            if (!existingSlot) {
                slots[slotKey] = slot

                return
            }

            const conflicts = Array.isArray(existingSlot.conflicts)
                ? [...existingSlot.conflicts]
                : []
            const slotIdentityKey = this.selectedTimetableV2SlotKey(slot)

            if (!conflicts.some((conflict) => this.selectedTimetableV2SlotKey(conflict) === slotIdentityKey)) {
                conflicts.push(slot)
            }

            slots[slotKey] = {
                ...existingSlot,
                conflicts,
            }
        },
        toggleMoreCoursesCard() {
            if (this.moreCoursesVisible) {
                if (!this.moreCoursesCardVisible) {
                    this.moreCoursesCardVisible = true
                    this.timetableOptionsCardVisible = false
                    this.syncTimetableV2Route({ replace: true, syncRoute: true })

                    return this.ensureMoreCourseAvailability()
                }

                this.closeMoreCoursesCard({ replace: true, syncRoute: true })

                return
            }

            return this.openMoreCoursesCard()
        },
        openMoreCoursesCard(options = {}) {
            this.moreCoursesVisible = true
            this.moreCoursesCardVisible = true
            this.timetableOptionsCardVisible = false
            this.activeMoreCoursesCategoryCardKey = ''
            this.moreCoursesSelectionSnapshot = this.currentMoreCoursesSelectionSignature()
            this.courseSelectionSnapshotSelections = { ...this.currentCourseSelectionOverrides() }
            this.offeredCourseSelectionSnapshotSelections = { ...this.currentOfferedCourseSelectionOverrides() }
            this.moreOfferedCourseSelectionSnapshot = this.moreOfferedCourseSelectionSignature(this.currentMoreOfferedCourseSelectionOverrides())
            this.moreOfferedCourseSelectionSnapshotSelections = { ...this.currentMoreOfferedCourseSelectionOverrides() }

            if (options?.syncRoute !== false) {
                this.syncTimetableV2Route(options)
            }

            return this.ensureMoreCourseAvailability()
        },
        openMoreCoursesPendingActions() {
            this.moreCoursesVisible = true
            this.moreCoursesCardVisible = false
            this.timetableOptionsCardVisible = false
            this.activeMoreCoursesCategoryCardKey = ''
            this.timetableNoSaturdayDraftSelected = this.timetableNoSaturdaySelected
            this.timetableMaxFreeDaysDraftSelected = this.timetableMaxFreeDaysSelected
            this.timetableNoDistanceLearningDraftSelected = this.timetableNoDistanceLearningSelected
            this.timetableStartsFromPeriod10DraftSelected = this.timetableStartsFromPeriod10Selected
            this.moreCoursesSelectionSnapshot = this.currentMoreCoursesSelectionSignature()
            this.courseSelectionSnapshotSelections = { ...this.currentCourseSelectionOverrides() }
            this.offeredCourseSelectionSnapshotSelections = { ...this.currentOfferedCourseSelectionOverrides() }
            this.moreOfferedCourseSelectionSnapshot = this.moreOfferedCourseSelectionSignature(this.currentMoreOfferedCourseSelectionOverrides())
            this.moreOfferedCourseSelectionSnapshotSelections = { ...this.currentMoreOfferedCourseSelectionOverrides() }
        },
        cancelMoreCoursesCard() {
            if (this.moreCoursesSelectionChanged) {
                this.saveCurrentMoreCoursesSelectionState({
                    courseSelections: { ...this.courseSelectionSnapshotSelections },
                    moreOfferedCourseSelections: { ...this.moreOfferedCourseSelectionSnapshotSelections },
                    offeredCourseSelections: { ...this.offeredCourseSelectionSnapshotSelections },
                })
            }

            this.clearPendingRemovedSelectedCourses()
            this.closeMoreCoursesCard({ replace: true, syncRoute: true })
        },
        async applyMoreCoursesSelection() {
            if (!this.moreCoursesSelectionChanged || this.timetableCalculationLoading) return null

            const timetableOptionsChanged = this.timetableOptionsChanged

            this.timetableNoSaturdaySelected = this.timetableNoSaturdayDraftSelected
            this.timetableMaxFreeDaysSelected = this.timetableMaxFreeDaysDraftSelected
            this.timetableNoDistanceLearningSelected = this.timetableNoDistanceLearningDraftSelected
            this.timetableStartsFromPeriod10Selected = this.timetableStartsFromPeriod10DraftSelected
            if (timetableOptionsChanged) {
                this.saveTimetableV2Options()
            }
            this.setSelectedTimetableV2Number(1, { replace: true })
            this.promoteSelectedMoreCourses()
            this.closeMoreCoursesCard({ replace: true, syncRoute: true })

            const calculationResult = await this.calculateTimetables({ progressContext: 'more-courses' })

            this.closeMoreCoursesCard({ replace: true, syncRoute: true })

            return calculationResult
        },
        applyAdoptedTimetableCourseRemoval() {
            if (!this.adoptedTimetableCourseRemovalPendingVisible || !this.moreCoursesSelectionChanged) return null

            const selectedCourseKeys = new Set(this.selectedCourseItems.map((course) => course.selectionKey))
            const removedCourses = this.selectedCourseItemsForCourseSelections(this.courseSelectionSnapshotSelections)
                .filter((course) => !selectedCourseKeys.has(course.selectionKey))

            removedCourses.forEach((course) => {
                this.removeCourseFromAdoptedTimetableResult(course)
            })

            this.adoptedTimetableSelectionSnapshot = this.clonedTimetableV2Selection(
                this.adoptedTimetableSelectionSnapshot || this.storedTimetableV2Selection,
            )
            this.saveAdoptedTimetableStateSnapshot()
            this.closeMoreCoursesCard({ replace: true, syncRoute: true })

            return removedCourses
        },
        removeCourseFromAdoptedTimetableResult(course) {
            const slots = this.adoptedTimetableCalculationResult?.selected_timetable?.slots
            if (!slots || typeof slots !== 'object') return

            Object.entries(slots).forEach(([slotKey, slot]) => {
                const updatedSlot = this.adoptedTimetableSlotWithoutCourse(slot, course)

                if (updatedSlot) {
                    slots[slotKey] = updatedSlot

                    return
                }

                delete slots[slotKey]
            })
        },
        adoptedTimetableSlotWithoutCourse(slot, course) {
            if (!slot || this.timetableSlotMatchesCourse(slot, course)) return null

            const updatedSlot = { ...slot }
            const sameSlotEntries = this.timetableSlotEntriesWithoutCourse(slot.sameSlotEntries, course)
            const conflicts = this.timetableSlotEntriesWithoutCourse(slot.conflicts, course)

            if (sameSlotEntries.length) {
                updatedSlot.sameSlotEntries = sameSlotEntries
            } else {
                delete updatedSlot.sameSlotEntries
            }

            if (conflicts.length) {
                updatedSlot.conflicts = conflicts
            } else {
                delete updatedSlot.conflicts
            }

            return updatedSlot
        },
        timetableSlotEntriesWithoutCourse(entries, course) {
            return (Array.isArray(entries) ? entries : [])
                .map((entry) => this.adoptedTimetableSlotWithoutCourse(entry, course))
                .filter(Boolean)
        },
        timetableSlotMatchesCourse(slot, course) {
            const courseCodes = new Set(this.courseCodeAliases({ code: course?.code || course?.label }))
            if (!courseCodes.size) return false

            return this.timetableSlotCourseCodes(slot).some((courseCode) => courseCodes.has(courseCode))
        },
        courseItemInSelectedTimetableV2(course) {
            const selectedTimetableV2CourseCodes = this.selectedTimetableV2CourseCodes
            if (!(selectedTimetableV2CourseCodes instanceof Set) || !selectedTimetableV2CourseCodes.size) return false

            return this.courseCodeAliases({ code: course?.code || course?.label })
                .some((courseCode) => selectedTimetableV2CourseCodes.has(courseCode))
        },
        selectedTimetableV2CourseCodeItems(slot) {
            if (!slot) return []

            return this.uniqueValues([
                ...this.timetableSlotCourseCodes(slot),
                ...this.selectedTimetableV2SameSlotEntries(slot)
                    .flatMap((sameSlotEntry) => this.selectedTimetableV2CourseCodeItems(sameSlotEntry)),
                ...this.selectedTimetableV2SlotConflicts(slot)
                    .flatMap((conflict) => this.selectedTimetableV2CourseCodeItems(conflict)),
            ])
        },
        timetableSlotCourseCodes(slot) {
            return this.uniqueValues([
                ...this.courseGroupCodes(slot?.courseGroup || {}),
                ...[
                    slot?.code,
                    slot?.name,
                    slot?.sourceLabel,
                    ...(Array.isArray(slot?.alternativeLabels) ? slot.alternativeLabels : []),
                ].flatMap((value) => this.courseCodeTokensFromValue(value)),
            ]
                .map((courseCode) => this.normalizedCourseCode(courseCode))
                .filter(Boolean))
        },
        promoteSelectedMoreCourses() {
            const selectedMoreCourses = this.moreCoursesCardItems
                .filter((course) => this.moreCourseSelectedForAdding(course))
                .filter((course) => !this.moreCourseUnavailable(course))
                .filter((course) => this.moreCourseOfferedCourseItemsAnySelected(course))

            if (!selectedMoreCourses.length) return

            const courseSelections = { ...this.currentCourseSelectionOverrides() }
            const offeredCourseSelections = { ...this.currentOfferedCourseSelectionOverrides() }
            const moreOfferedCourseSelections = { ...this.currentMoreOfferedCourseSelectionOverrides() }

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
            const selectionKey = this.courseSelectionKey(course, course?.courseGroup)
                || String(course?.selectionKey || '').trim()
            if (!selectionKey) return

            this.clearPendingRemovedSelectedCourse(course)

            this.courseSelectionKeys(course, course.courseGroup).forEach((courseSelectionKey) => {
                delete selections.courseSelections[courseSelectionKey]
            })
            selections.courseSelections[selectionKey] = true

            this.offeredCourseItemsForSelectedCourse(course).forEach((offeredCourse) => {
                const offeredCourseSelectionKey = offeredCourse?.selectionKey || this.offeredCourseSelectionKey(offeredCourse, course)
                if (!offeredCourseSelectionKey) return

                if (this.moreOfferedCourseSelected(offeredCourse)) {
                    if (this.offeredCourseIncludedByInstructionFilters(offeredCourse)) {
                        delete selections.offeredCourseSelections[offeredCourseSelectionKey]
                    } else {
                        selections.offeredCourseSelections[offeredCourseSelectionKey] = true
                    }
                } else {
                    selections.offeredCourseSelections[offeredCourseSelectionKey] = false
                }

                delete selections.moreOfferedCourseSelections[offeredCourseSelectionKey]
            })

            this.clearRestorableMoreCourse(course)
        },
        closeMoreCoursesCard(options = {}) {
            this.moreCoursesVisible = false
            this.moreCoursesCardVisible = false
            this.activeMoreCoursesCategoryCardKey = ''
            this.activeMoreAdoptedCourseCardKey = ''
            this.activeMoreExistingCourseBaseKey = ''
            this.selectedMoreCourseKey = ''
            this.moreCoursesSelectionSnapshot = ''
            this.courseSelectionSnapshotSelections = {}
            this.offeredCourseSelectionSnapshotSelections = {}
            this.moreOfferedCourseSelectionSnapshot = ''
            this.moreOfferedCourseSelectionSnapshotSelections = {}
            this.timetableNoSaturdayDraftSelected = this.timetableNoSaturdaySelected
            this.timetableMaxFreeDaysDraftSelected = this.timetableMaxFreeDaysSelected
            this.timetableNoDistanceLearningDraftSelected = this.timetableNoDistanceLearningSelected
            this.timetableStartsFromPeriod10DraftSelected = this.timetableStartsFromPeriod10Selected

            if (options?.syncRoute === true) {
                this.syncTimetableV2Route(options)
            }
        },
        toggleTimetableOptionsCard() {
            if (this.timetableCalculationLoading || this.timetableQualityCountersLoading || this.timetableOptionsUnavailable) return

            if (this.timetableOptionsCardVisible) {
                this.closeTimetableOptionsCard()

                return
            }

            this.timetableNoSaturdayDraftSelected = this.timetableNoSaturdaySelected
            this.timetableMaxFreeDaysDraftSelected = this.timetableMaxFreeDaysSelected
            this.timetableNoDistanceLearningDraftSelected = this.timetableNoDistanceLearningSelected
            this.timetableStartsFromPeriod10DraftSelected = this.timetableStartsFromPeriod10Selected
            this.timetableOptionsCardVisible = true
        },
        closeTimetableOptionsCard() {
            this.timetableOptionsCardVisible = false
            this.timetableNoSaturdayDraftSelected = this.timetableNoSaturdaySelected
            this.timetableMaxFreeDaysDraftSelected = this.timetableMaxFreeDaysSelected
            this.timetableNoDistanceLearningDraftSelected = this.timetableNoDistanceLearningSelected
            this.timetableStartsFromPeriod10DraftSelected = this.timetableStartsFromPeriod10Selected
        },
        timetableOptionUnavailable(optionKey) {
            if (this.timetableOptionsUnavailable) return true
            if (this.timetableCalculationLoading || this.timetableQualityCountersLoading) return true
            if (this.selectedTimetableV2HasErrorConflicts) return true

            if (optionKey === 'no-saturday') {
                return this.timetableNoSaturdaySelected || this.noSaturdayTimetableCount <= 0
            }

            if (optionKey === 'max-free-days') {
                return this.timetableMaxFreeDaysSelected || this.maxFreeDaysTimetableCount <= 0
            }

            if (optionKey === 'no-distance-learning') {
                return this.timetableNoDistanceLearningSelected || this.noDistanceLearningTimetableCount <= 0
            }

            if (optionKey === 'starts-from-period-10') {
                return this.startsFromPeriod10TimetableCount <= 0
            }

            return false
        },
        timetableOptionDraftSelected(optionKey) {
            if (optionKey === 'no-saturday') return this.timetableNoSaturdayDraftSelected === true
            if (optionKey === 'starts-from-period-10') return this.timetableStartsFromPeriod10DraftSelected === true
            if (optionKey === 'max-free-days') return this.timetableMaxFreeDaysDraftSelected === true
            if (optionKey === 'no-distance-learning') return this.timetableNoDistanceLearningDraftSelected === true

            return false
        },
        selectTimetableOptionDraft(optionKey) {
            if (optionKey === 'no-saturday') {
                this.timetableNoSaturdayDraftSelected = true
            }

            if (optionKey === 'max-free-days') {
                this.timetableMaxFreeDaysDraftSelected = true
            }

            if (optionKey === 'no-distance-learning') {
                this.timetableNoDistanceLearningDraftSelected = true
            }

            if (optionKey === 'starts-from-period-10') {
                this.timetableStartsFromPeriod10DraftSelected = true
            }
        },
        selectTimetableOption(optionKey) {
            this.selectTimetableOptionDraft(optionKey)

            if (optionKey === 'no-saturday') {
                this.timetableNoSaturdaySelected = true
            }

            if (optionKey === 'max-free-days') {
                this.timetableMaxFreeDaysSelected = true
            }

            if (optionKey === 'no-distance-learning') {
                this.timetableNoDistanceLearningSelected = true
            }

            if (optionKey === 'starts-from-period-10') {
                this.timetableStartsFromPeriod10Selected = true
            }
        },
        toggleNoSaturdayTimetableOption() {
            if (this.timetableOptionUnavailable('no-saturday')) return null

            if (this.timetableNoSaturdayDraftSelected) {
                this.timetableNoSaturdayDraftSelected = false

                return null
            }

            this.selectTimetableOptionDraft('no-saturday')
        },
        toggleStartsFromPeriod10TimetableOption() {
            if (this.timetableOptionUnavailable('starts-from-period-10')) return null

            if (this.timetableStartsFromPeriod10DraftSelected) {
                this.timetableStartsFromPeriod10DraftSelected = false

                return null
            }

            this.selectTimetableOptionDraft('starts-from-period-10')
        },
        toggleMaxFreeDaysTimetableOption() {
            if (this.timetableOptionUnavailable('max-free-days')) return null

            if (this.timetableMaxFreeDaysDraftSelected) {
                this.timetableMaxFreeDaysDraftSelected = false

                return null
            }

            this.selectTimetableOptionDraft('max-free-days')
        },
        toggleNoDistanceLearningTimetableOption() {
            if (this.timetableOptionUnavailable('no-distance-learning')) return null

            if (this.timetableNoDistanceLearningDraftSelected) {
                this.timetableNoDistanceLearningDraftSelected = false

                return null
            }

            this.selectTimetableOptionDraft('no-distance-learning')
        },
        async applyTimetableOptions() {
            if (!this.timetableOptionsChanged || this.timetableCalculationLoading || this.timetableOptionsUnavailable) return null

            const previousOptions = this.timetableV2OptionsForSaving()
            const previousCalculationResult = this.timetableCalculationResult
            const previousSelectedNumber = this.timetableCalculationSelectedNumber

            this.timetableNoSaturdaySelected = this.timetableNoSaturdayDraftSelected
            this.timetableMaxFreeDaysSelected = this.timetableMaxFreeDaysDraftSelected
            this.timetableNoDistanceLearningSelected = this.timetableNoDistanceLearningDraftSelected
            this.timetableStartsFromPeriod10Selected = this.timetableStartsFromPeriod10DraftSelected
            this.saveTimetableV2Options()
            this.setSelectedTimetableV2Number(1, { replace: true })
            this.resetMoreCourseAvailability()
            this.closeTimetableOptionsCard()

            const calculationResult = await this.calculateTimetables({ progressContext: 'options' })

            if (this.selectedTimetableV2HasErrorConflicts) {
                this.applyTimetableV2OptionState(previousOptions)
                this.timetableCalculationResult = previousCalculationResult
                this.setSelectedTimetableV2Number(previousSelectedNumber, { replace: true })
                this.saveTimetableV2Options()
                this.timetableCalculationError = 'Die Option wurde nicht übernommen, weil sie zu Überschneidungen führt.'

                return null
            }

            return calculationResult
        },
        removeSelectedTimetableOption(option) {
            if (!this.selectedTimetableOptionItemsDeletable) return

            if (!['no-saturday', 'max-free-days', 'no-distance-learning', 'starts-from-period-10'].includes(option?.key)) return

            if (!this.moreCoursesVisible) {
                this.openMoreCoursesPendingActions()
            }

            if (option?.key === 'no-saturday') {
                this.timetableNoSaturdayDraftSelected = false
            }

            if (option?.key === 'max-free-days') {
                this.timetableMaxFreeDaysDraftSelected = false
            }

            if (option?.key === 'no-distance-learning') {
                this.timetableNoDistanceLearningDraftSelected = false
            }

            if (option?.key === 'starts-from-period-10') {
                this.timetableStartsFromPeriod10DraftSelected = false
            }
        },
        toggleMoreCourseOffers(course) {
            const disabled = Object.prototype.hasOwnProperty.call(course, 'disabled')
                ? course.disabled === true
                : this.moreCourseOpenDisabled(course)
            if (disabled) return

            const selectionKey = course?.selectionKey || this.courseSelectionKey(course, course?.courseGroup)
            if (!selectionKey) return
            if (this.selectedMoreCourseKey === selectionKey) {
                this.closeMoreCourseOffers()

                return
            }

            this.selectedMoreCourseKey = selectionKey
            this.syncTimetableV2Route()
            void this.ensureMoreCourseAvailability()
        },
        closeMoreCourseOffers() {
            if (!this.selectedMoreCourseKey) return

            this.selectedMoreCourseKey = ''
            this.syncTimetableV2Route({ replace: true })
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
            this.restorableMoreCourseAvailabilitySignatures = {}
            this.moreCourseAvailabilityRequestId++

            if (this.timetableCalculationProgressSource !== 'availability') return

            this.clearTimetableCalculationProgressTimers()
            this.timetableCalculationProgress = 0
            this.timetableCalculationProgressCompletionPending = false
            this.timetableCalculationProgressSource = ''
        },
        moreCourseAvailabilityCurrentSignature() {
            const candidateCourseKeys = this.moreCourseAvailabilityCandidateItems
                .flatMap((course) => this.moreCourseAvailabilityPayloadCandidates(course, { includeOffers: true }))
                .map((course) => course.availability_key)
                .filter(Boolean)
                .sort((firstKey, secondKey) => firstKey.localeCompare(secondKey, 'de-AT'))

            if (!candidateCourseKeys.length) return ''

            return JSON.stringify({
                candidateCourseKeys,
                courseSelections: this.selectionSignatureEntries(this.courseSelectionOverrides),
                maxFreeDaysSelected: this.timetableMaxFreeDaysSelected === true,
                noDistanceLearningSelected: this.timetableNoDistanceLearningSelected === true,
                offeredCourseSelections: this.selectionSignatureEntries(this.offeredCourseSelectionOverrides),
                noSaturdaySelected: this.timetableNoSaturdaySelected === true,
                startsFromPeriod10Selected: this.timetableStartsFromPeriod10Selected === true,
            })
        },
        moreCourseAvailabilityComplete(availabilitySignature = this.moreCourseAvailabilityCurrentSignature()) {
            if (this.moreCourseAvailabilitySignature !== availabilitySignature) return false

            return this.moreCourseAvailabilityCandidateItems.every((course) => {
                if (this.moreCourseRestorable(course)) return true

                return this.moreCourseAvailabilityPayloadCandidates(course, { includeOffers: true })
                    .every((candidateCourse) =>
                        candidateCourse.availability_key
                        && Object.prototype.hasOwnProperty.call(
                            this.moreCourseAvailabilityByKey,
                            candidateCourse.availability_key,
                        )
                    )
            })
        },
        loadMoreCourseAvailability(availabilitySignature = this.moreCourseAvailabilityCurrentSignature()) {
            const requestId = this.moreCourseAvailabilityRequestId + 1
            this.moreCourseAvailabilityRequestId = requestId
            this.moreCourseAvailabilityByKey = {}
            this.moreCourseAvailabilitySignature = availabilitySignature
            this.moreCourseAvailabilityLoading = true

            const courses = this.moreCourseAvailabilityCandidateItems
            if (!availabilitySignature || !courses.length) {
                this.moreCourseAvailabilityLoading = false

                return Promise.resolve([])
            }

            const coursesToCheck = courses.filter((course) => this.prepareMoreCourseAvailability(course, requestId))

            if (!coursesToCheck.length) {
                this.moreCourseAvailabilityLoading = false

                return Promise.resolve([])
            }

            if (!this.timetableCalculationProgressSource) {
                this.startTimetableCalculationProgress('availability')
            }

            return this.requestMoreCourseAvailability(coursesToCheck, { includeOffers: true })
                .then((response) => {
                    if (requestId !== this.moreCourseAvailabilityRequestId) return []

                    const availability = response.data?.data?.availability || {}
                    this.setMoreCourseAvailabilityFromResponse(availability, requestId)

                    coursesToCheck
                        .flatMap((course) => this.moreCourseAvailabilityPayloadCandidates(course, { includeOffers: true }))
                        .filter((candidateCourse) => candidateCourse.availability_key)
                        .forEach((candidateCourse) => {
                            this.setMoreCourseAvailability(
                                candidateCourse.availability_key,
                                this.moreCourseAvailabilityResultAvailable(availability[candidateCourse.availability_key]),
                                requestId,
                            )
                        })

                    return availability
                })
                .catch(() => {
                    if (requestId !== this.moreCourseAvailabilityRequestId) return []

                    coursesToCheck
                        .flatMap((course) => this.moreCourseAvailabilityPayloadCandidates(course, { includeOffers: true }))
                        .filter((course) => course.availability_key)
                        .forEach((course) => this.setMoreCourseAvailability(course.availability_key, false, requestId))

                    return []
                })
                .finally(() => {
                    if (requestId === this.moreCourseAvailabilityRequestId) {
                        this.moreCourseAvailabilityLoading = false
                        this.completeTimetableCalculationProgress()
                    }
                })
        },
        prepareMoreCourseAvailability(course, requestId) {
            const availabilityKey = this.moreCourseAvailabilityKey(course)
            if (!availabilityKey) return false

            if (this.moreCourseRestorable(course)) {
                this.setMoreCourseAvailability(availabilityKey, true, requestId)

                return false
            }

            if (!this.offeredCourseItemsForSelectedCourse(course).length) {
                this.setMoreCourseAvailability(availabilityKey, false, requestId)

                return false
            }

            return true
        },
        async loadMoreCourseAvailabilityForCourse(course, requestId) {
            const availabilityKey = this.moreCourseAvailabilityKey(course)
            if (!availabilityKey) return

            if (!this.prepareMoreCourseAvailability(course, requestId)) return

            try {
                const response = await this.requestMoreCourseAvailability([course], { includeOffers: true })
                const availability = response.data?.data?.availability || {}
                this.setMoreCourseAvailabilityFromResponse(availability, requestId)

                this.moreCourseAvailabilityPayloadCandidates(course, { includeOffers: true })
                    .filter((candidateCourse) => candidateCourse.availability_key)
                    .forEach((candidateCourse) => this.setMoreCourseAvailability(
                        candidateCourse.availability_key,
                        this.moreCourseAvailabilityResultAvailable(availability[candidateCourse.availability_key]),
                        requestId,
                    ))
            } catch {
                if (requestId !== this.moreCourseAvailabilityRequestId) return

                this.moreCourseAvailabilityPayloadCandidates(course, { includeOffers: true })
                    .filter((candidateCourse) => candidateCourse.availability_key)
                    .forEach((candidateCourse) => this.setMoreCourseAvailability(candidateCourse.availability_key, false, requestId))
            }
        },
        moreCourseAvailabilityResultAvailable(result = {}) {
            if (typeof result === 'boolean') return result

            return result?.available === true
        },
        setMoreCourseAvailabilityFromResponse(availability = {}, requestId = this.moreCourseAvailabilityRequestId) {
            Object.entries(availability || {}).forEach(([availabilityKey, result]) => {
                this.setMoreCourseAvailability(
                    availabilityKey,
                    this.moreCourseAvailabilityResultAvailable(result),
                    requestId,
                )
            })
        },
        setMoreCourseAvailability(availabilityKey, available, requestId = this.moreCourseAvailabilityRequestId) {
            if (requestId !== this.moreCourseAvailabilityRequestId) return

            this.moreCourseAvailabilityByKey = {
                ...this.moreCourseAvailabilityByKey,
                [availabilityKey]: available === true,
            }
        },
        moreCourseAvailabilityKey(course) {
            return course?.selectionKey || this.courseSelectionKey(course, course?.courseGroup)
        },
        moreCourseOfferAvailabilityKey(course, offeredCourse) {
            return [
                this.moreCourseAvailabilityKey(course),
                offeredCourse?.selectionKey || this.offeredCourseSelectionKey(offeredCourse, course),
            ].filter(Boolean).join('::offer::')
        },
        moreCourseRestorable(course) {
            const availabilityKey = this.moreCourseAvailabilityKey(course)
            if (!availabilityKey) return false

            return this.restorableMoreCourseAvailabilitySignatures?.[availabilityKey]
                === this.moreCourseAvailabilityCurrentSignature()
        },
        markMoreCourseRestorable(course) {
            const availabilityKey = this.moreCourseAvailabilityKey(course)
            if (!availabilityKey) return

            this.restorableMoreCourseAvailabilitySignatures = {
                ...(this.restorableMoreCourseAvailabilitySignatures || {}),
                [availabilityKey]: this.moreCourseAvailabilityCurrentSignature(),
            }
        },
        clearRestorableMoreCourse(course) {
            const availabilityKey = this.moreCourseAvailabilityKey(course)
            if (!availabilityKey || !this.restorableMoreCourseAvailabilitySignatures?.[availabilityKey]) return

            const restorableMoreCourseAvailabilitySignatures = { ...this.restorableMoreCourseAvailabilitySignatures }
            delete restorableMoreCourseAvailabilitySignatures[availabilityKey]

            this.restorableMoreCourseAvailabilitySignatures = restorableMoreCourseAvailabilitySignatures
        },
        moreCourseUnavailable(course) {
            if (this.moreCourseRestorable(course)) return false

            const availabilityKey = this.moreCourseAvailabilityKey(course)
            if (!availabilityKey) return false

            return this.moreCourseAvailabilityByKey[availabilityKey] === false
        },
        moreCourseDisabled(course) {
            return this.moreCourseAvailabilityLoading || this.moreCourseUnavailable(course) || this.moreCourseSelectionLocked(course)
        },
        moreCourseOpenDisabled(course) {
            return this.moreCourseAvailabilityLoading || this.moreCourseSelectionLocked(course)
        },
        moreCourseOfferUnavailable(offeredCourse, moreCourse = this.selectedMoreCourseItem) {
            if (!moreCourse || this.moreCourseRestorable(moreCourse)) return false

            const availabilityKey = this.moreCourseOfferAvailabilityKey(moreCourse, offeredCourse)
            if (!availabilityKey) return false
            const availability = this.moreCourseAvailabilityByKey[availabilityKey]
            if (availability === true || availability === false) return availability === false

            return this.moreCourseUnavailable(moreCourse)
        },
        moreCourseOfferDisabled(offeredCourse, moreCourse = this.selectedMoreCourseItem) {
            return this.moreCourseAvailabilityLoading || this.moreCourseOfferUnavailable(offeredCourse, moreCourse)
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
            const availabilityState = this.moreCourseOfferAvailabilityState(course)

            if (availabilityState === 'all') return 'success'
            if (availabilityState === 'some') return 'warning'
            if (availabilityState === 'none') return 'error'

            return this.moreCourseUnavailable(course) ? 'error' : 'success'
        },
        moreCourseOfferAvailabilityState(course) {
            if (this.moreCourseRestorable(course)) return 'all'

            const offeredCourses = this.offeredCourseItemsForSelectedCourse(course)
            if (!offeredCourses.length) return this.moreCourseUnavailable(course) ? 'none' : 'unknown'

            const hasExplicitOfferSelection = offeredCourses.some((offeredCourse) => {
                const selectionKey = offeredCourse?.selectionKey || this.offeredCourseSelectionKey(offeredCourse, course)

                return selectionKey && Object.prototype.hasOwnProperty.call(this.currentMoreOfferedCourseSelectionOverrides(), selectionKey)
            })
            const offerAvailabilityValues = offeredCourses.map((offeredCourse) => {
                if (hasExplicitOfferSelection && !this.moreOfferedCourseSelected(offeredCourse, course)) return false

                const availability = this.moreCourseAvailabilityByKey[this.moreCourseOfferAvailabilityKey(course, offeredCourse)]
                if (availability === true || availability === false) return availability
                if (hasExplicitOfferSelection && !this.moreCourseUnavailable(course)) return true

                return null
            })

            if (offerAvailabilityValues.some((availability) => availability === null)) {
                return this.moreCourseUnavailable(course) ? 'none' : 'unknown'
            }

            const availableOfferCount = offerAvailabilityValues.filter((available) => available === true).length

            if (availableOfferCount === offeredCourses.length) return 'all'
            if (availableOfferCount > 0) return 'some'

            return 'none'
        },
        moreCourseOfferedCourseItemsAllDeselected(course) {
            const offeredCourses = this.offeredCourseItemsForSelectedCourse(course)

            return offeredCourses.length > 0 && offeredCourses.every((offeredCourse) => !this.moreOfferedCourseSelected(offeredCourse, course))
        },
        moreCourseOfferedCourseItemsAnySelected(course) {
            return this.offeredCourseItemsForSelectedCourse(course)
                .some((offeredCourse) => this.moreOfferedCourseSelected(offeredCourse, course))
        },
        moreOfferedCourseSelectionContext(moreCourse = this.selectedMoreCourseItem, options = {}) {
            const restorableAvailabilitySignature = Object.prototype.hasOwnProperty.call(options, 'restorableAvailabilitySignature')
                ? options.restorableAvailabilitySignature
                : Object.keys(this.restorableMoreCourseAvailabilitySignatures || {}).length
                    ? this.moreCourseAvailabilityCurrentSignature()
                    : ''
            const baseContext = {
                moreCourseAvailabilityByKey: this.moreCourseAvailabilityByKey,
                restorableAvailabilitySignature,
            }
            const moreCourseRestorable = this.moreCourseRestorableWithContext(moreCourse, baseContext)
            const moreCourseUnavailable = this.moreCourseUnavailableWithContext(moreCourse, baseContext, {
                restorable: moreCourseRestorable,
            })

            return {
                instructionFilters: this.instructionCourseFiltersForSelection(
                    this.currentTimetableV2SelectionForCourseState(),
                ),
                moreCourseAvailabilityByKey: this.moreCourseAvailabilityByKey,
                moreCourseRestorable,
                moreCourseSelectedForAdding: this.moreCourseSelectedForAdding(moreCourse),
                moreCourseUnavailable,
                moreOfferedCourseSelections: this.currentMoreOfferedCourseSelectionOverrides(),
                offeredCourseSelections: this.currentOfferedCourseSelectionOverrides(),
                restorableAvailabilitySignature,
                usesExplicitSelectedOfferList: this.moreCourseUsesExplicitSelectedOfferList(moreCourse),
            }
        },
        selectedMoreCourseOfferedCourseItem(course, moreCourse = this.selectedMoreCourseItem, context = {}) {
            const unavailable = this.moreCourseOfferUnavailableWithContext(course, moreCourse, context)
            const disabled = this.moreCourseAvailabilityLoading || unavailable
            const selected = this.moreOfferedCourseSelectedWithContext(course, moreCourse, {
                ...context,
                unavailable,
            })
            const instructionLabel = this.offeredCourseInstructionLabel(course)
            const conflictItems = unavailable
                ? this.moreCourseOfferConflictItems(course)
                : []

            return {
                ...course,
                ariaDisabled: disabled ? 'true' : 'false',
                ariaPressed: selected ? 'true' : 'false',
                classes: {
                    'students-timetable-v2-offered-courses-card__item--selected': selected && !unavailable,
                    'students-timetable-v2-offered-courses-card__item--deselected': !selected && !unavailable,
                    'students-timetable-v2-offered-courses-card__item--available': !unavailable,
                    'students-timetable-v2-offered-courses-card__item--unavailable': unavailable,
                },
                disabled,
                conflictItems,
                instructionLabel,
                instructionVisible: instructionLabel !== '',
                selected,
                title: unavailable ? 'Kann nicht konfliktfrei in den Stundenplan eingefügt werden.' : undefined,
                unavailable,
            }
        },
        moreCourseOfferUnavailableWithContext(offeredCourse, moreCourse = this.selectedMoreCourseItem, context = {}) {
            if (!moreCourse || context.moreCourseRestorable === true) return false

            const availabilityKey = this.moreCourseOfferAvailabilityKey(moreCourse, offeredCourse)
            if (!availabilityKey) return false

            const availability = (context.moreCourseAvailabilityByKey || this.moreCourseAvailabilityByKey)[availabilityKey]
            if (availability === true || availability === false) return availability === false

            return context.moreCourseUnavailable === true
        },
        moreCourseOfferConflictItems(offeredCourse) {
            const conflictItemsByKey = new Map()
            const offeredCourseSlots = Array.isArray(offeredCourse?.scheduleSlots) ? offeredCourse.scheduleSlots : []
            const timetableSlots = this.selectedTimetableV2ConflictComparisonSlots()

            offeredCourseSlots.forEach((offeredCourseSlot) => {
                timetableSlots
                    .filter((timetableSlot) => this.moreCourseOfferSlotConflicts(offeredCourseSlot, timetableSlot))
                    .forEach((timetableSlot) => {
                        const timeLabel = this.moreCourseOfferConflictTimeLabel(offeredCourseSlot)
                        const dateLabel = this.moreCourseOfferConflictDateLabel(offeredCourseSlot, timetableSlot)
                        const courseLabel = this.selectedTimetableV2CourseProblemLabel(timetableSlot)
                        const key = [
                            timeLabel,
                            dateLabel,
                        ].filter(Boolean).join('|')

                        if (!key || !courseLabel) return

                        const conflictItem = conflictItemsByKey.get(key) || {
                            courseLabels: [],
                            dateLabel,
                            key,
                            timeLabel,
                        }

                        conflictItem.courseLabels = this.uniqueValues([
                            ...conflictItem.courseLabels,
                            courseLabel,
                        ])
                        conflictItemsByKey.set(key, conflictItem)
                    })
            })

            return [...conflictItemsByKey.values()]
        },
        selectedTimetableV2ConflictComparisonSlots() {
            const slotsByKey = new Map()

            this.selectedTimetableV2SlotEntries.forEach((entry) => {
                [
                    entry.slot,
                    ...this.selectedTimetableV2SameSlotEntries(entry.slot),
                    ...this.selectedTimetableV2RawSlotConflicts(entry.slot),
                ].filter(Boolean).forEach((slot) => {
                    const key = this.selectedTimetableV2SlotKey(slot)
                        || [
                            entry.key,
                            this.selectedTimetableV2CourseProblemLabel(slot),
                            slot?.courseGroup?.weekday || slot?.weekday || entry.weekday,
                            slot?.courseGroup?.hour || slot?.hour || entry.hour,
                        ].filter(Boolean).join('|')

                    if (key) {
                        slotsByKey.set(key, slot)
                    }
                })
            })

            return [...slotsByKey.values()]
        },
        moreCourseOfferSlotConflicts(offeredCourseSlot, timetableSlot) {
            const offeredWeekday = Number(offeredCourseSlot?.weekday || offeredCourseSlot?.courseGroup?.weekday || 0)
            const offeredHour = Number(offeredCourseSlot?.hour || offeredCourseSlot?.courseGroup?.hour || 0)
            const timetableWeekday = Number(timetableSlot?.courseGroup?.weekday || timetableSlot?.weekday || 0)
            const timetableHour = Number(timetableSlot?.courseGroup?.hour || timetableSlot?.hour || 0)

            if (!Number.isFinite(offeredWeekday) || !Number.isFinite(offeredHour)) return false
            if (offeredWeekday <= 0 || offeredHour <= 0) return false
            if (offeredWeekday !== timetableWeekday || offeredHour !== timetableHour) return false

            const offeredRange = this.selectedTimetableV2SlotDateRangeBounds(offeredCourseSlot)
            const timetableRange = this.selectedTimetableV2SlotDateRangeBounds(timetableSlot)
            if (!offeredRange || !timetableRange) return true

            return this.selectedTimetableV2SlotDateRangesOverlap(offeredCourseSlot, timetableSlot)
        },
        moreCourseOfferConflictTimeLabel(slot) {
            const weekdayLabel = this.courseGroupWeekdayLabel(slot?.weekday || slot?.courseGroup?.weekday)
            const hour = Number(slot?.hour || slot?.courseGroup?.hour || 0)
            const hourLabel = Number.isFinite(hour) && hour > 0 ? `${hour}.` : ''
            const recurrenceLabel = this.scheduleRecurrenceLabel(
                slot?.recurrenceLabel || this.selectedTimetableV2SlotRecurrenceLabel(slot),
            )
            const timeRangeLabel = [slot?.from, slot?.until].filter(Boolean).join('-')

            return [weekdayLabel, hourLabel, recurrenceLabel, timeRangeLabel].filter(Boolean).join(' ')
        },
        moreCourseOfferConflictDateLabel(offeredCourseSlot, timetableSlot) {
            const dateLabels = this.selectedTimetableV2ConflictDateLabels(offeredCourseSlot, timetableSlot)
            if (dateLabels.length) return dateLabels.join(', ')

            return String(
                this.selectedTimetableV2SlotDateLabel(offeredCourseSlot, { showRegularRange: true })
                || offeredCourseSlot?.dateRangeLabel
                || this.selectedTimetableV2SlotDateLabel(timetableSlot, { showRegularRange: true })
                || timetableSlot?.dateRangeLabel
                || '',
            ).trim()
        },
        moreOfferedCourseSelected(course, moreCourse = this.selectedMoreCourseItem) {
            return this.moreOfferedCourseSelectedWithContext(
                course,
                moreCourse,
                this.moreOfferedCourseSelectionContext(moreCourse),
            )
        },
        moreOfferedCourseSelectedWithContext(course, moreCourse = this.selectedMoreCourseItem, context = {}) {
            const selectionKey = course?.selectionKey || this.offeredCourseSelectionKey(course, moreCourse)
            if (!selectionKey) return false
            if (context.unavailable === true || this.moreCourseOfferUnavailableWithContext(course, moreCourse, context)) return false
            if (context.offeredCourseSelections?.[selectionKey] === false) return false

            if (context.moreOfferedCourseSelections?.[selectionKey] === false) return false
            if (context.moreOfferedCourseSelections?.[selectionKey] === true) return true

            return context.moreCourseSelectedForAdding === true
        },
        moreCourseUsesExplicitSelectedOfferList(moreCourse = this.selectedMoreCourseItem) {
            if (!moreCourse) return false

            const offeredCourseSelectionKeys = this.offeredCourseItemsForSelectedCourse(moreCourse)
                .map((offeredCourse) => offeredCourse?.selectionKey || this.offeredCourseSelectionKey(offeredCourse, moreCourse))
                .filter(Boolean)
            const explicitSelections = offeredCourseSelectionKeys
                .map((selectionKey) => this.currentMoreOfferedCourseSelectionOverrides()[selectionKey])
                .filter((selected) => selected === true || selected === false)

            return explicitSelections.some((selected) => selected === true)
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
        courseSelectionOverridesForSelection(selection = {}) {
            const courseSelections = selection?.courseSelections

            return courseSelections && typeof courseSelections === 'object' && !Array.isArray(courseSelections)
                ? courseSelections
                : {}
        },
        offeredCourseSelectionOverridesForSelection(selection = {}) {
            const offeredCourseSelections = selection?.offeredCourseSelections

            return offeredCourseSelections && typeof offeredCourseSelections === 'object' && !Array.isArray(offeredCourseSelections)
                ? offeredCourseSelections
                : {}
        },
        moreOfferedCourseSelectionOverridesForSelection(selection = {}) {
            const moreOfferedCourseSelections = selection?.moreOfferedCourseSelections

            return moreOfferedCourseSelections && typeof moreOfferedCourseSelections === 'object' && !Array.isArray(moreOfferedCourseSelections)
                ? moreOfferedCourseSelections
                : {}
        },
        currentTimetableV2SelectionForCourseState() {
            if (this.adoptedTimetableVisible && this.adoptedTimetableSelectionSnapshot) {
                return this.adoptedTimetableSelectionSnapshot
            }

            if (this.timetableCalculationVisible && this.calculationTimetableV2Selection) {
                return this.calculationTimetableV2Selection
            }

            if ((this.courseReviewVisible || this.timetableCalculationVisible) && this.storedAdaptedTimetableV2Selection) {
                return this.storedAdaptedTimetableV2Selection
            }

            return this.storedTimetableV2Selection
        },
        currentCourseSelectionOverrides() {
            if (this.adoptedTimetableVisible || this.courseReviewVisible || this.timetableCalculationVisible) {
                return this.courseSelectionOverridesForSelection(this.currentTimetableV2SelectionForCourseState())
            }

            return this.courseSelectionOverrides
        },
        currentOfferedCourseSelectionOverrides() {
            return this.offeredCourseSelectionOverridesForSelection(this.currentTimetableV2SelectionForCourseState())
        },
        currentMoreOfferedCourseSelectionOverrides() {
            return this.moreOfferedCourseSelectionOverridesForSelection(this.currentTimetableV2SelectionForCourseState())
        },
        currentMoreCoursesSelectionSignature() {
            return this.moreCoursesSelectionSignature(
                this.currentCourseSelectionOverrides(),
                this.currentOfferedCourseSelectionOverrides(),
                this.currentMoreOfferedCourseSelectionOverrides(),
            )
        },
        moreCoursesSelectionSignature(
            courseSelections = this.courseSelectionOverrides,
            offeredCourseSelections = this.offeredCourseSelectionOverrides,
            moreOfferedCourseSelections = this.moreOfferedCourseSelectionOverrides,
            selectedMoreCourseKey = this.selectedMoreCourseKey,
        ) {
            return JSON.stringify({
                courseSelections: this.selectionSignatureEntries(courseSelections),
                offeredCourseSelections: this.selectionSignatureEntries(offeredCourseSelections),
                moreOfferedCourseSelections: this.selectionSignatureEntries(moreOfferedCourseSelections),
                selectedMoreCourseKey: String(selectedMoreCourseKey || '').trim(),
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
        courseSelectionSignature(courseSelections = {}) {
            return JSON.stringify(this.selectionSignatureEntries(courseSelections))
        },
        normalizedCourseSelections(courseSelections = {}) {
            const normalizedSelections = courseSelections && typeof courseSelections === 'object' && !Array.isArray(courseSelections)
                ? courseSelections
                : {}

            return Object.fromEntries(
                Object.entries(normalizedSelections)
                    .filter(([, selected]) => selected === true || selected === false),
            )
        },
        draftCourseSelectionOverrides() {
            return {
                ...this.normalizedCourseSelections(this.courseSelectionOverrides),
            }
        },
        setDraftCourseSelections(courseSelections = null) {
            if (!courseSelections || typeof courseSelections !== 'object' || Array.isArray(courseSelections)) {
                this.resetDraftCourseSelections()

                return
            }

            this.draftCourseSelections = this.normalizedCourseSelections(courseSelections)
        },
        resetDraftCourseSelections() {
            this.draftCourseSelections = null
        },
        applyDraftCourseSelections(courseSelections = this.draftCourseSelections) {
            const normalizedCourseSelections = this.normalizedCourseSelections(courseSelections)

            if (
                this.courseSelectionSignature(normalizedCourseSelections)
                === this.courseSelectionSignature(this.storedCourseSelectionOverrides)
            ) {
                return
            }

            const timetableV2Selection = { ...this.storedTimetableV2Selection }

            if (Object.keys(normalizedCourseSelections).length) {
                timetableV2Selection.courseSelections = normalizedCourseSelections
            } else {
                delete timetableV2Selection.courseSelections
            }

            this.resetDraftCourseSelections()
            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                timetableV2Selection,
                transferredStudentContext: this.storedTimetableStudentContext,
            })
        },
        moreOfferedCourseSelectionsForSingleCourse(course) {
            return this.offeredCourseItemsForSelectedCourse(course).reduce((selections, offeredCourse) => {
                const selectionKey = offeredCourse?.selectionKey || this.offeredCourseSelectionKey(offeredCourse, course)
                if (!selectionKey) return selections
                if (this.currentOfferedCourseSelectionOverrides()[selectionKey] === false) return selections
                if (this.moreCourseOfferUnavailable(offeredCourse, course)) return selections

                return {
                    ...selections,
                    [selectionKey]: true,
                }
            }, {})
        },
        toggleMoreOfferedCourseItem(course, moreCourse = this.selectedMoreCourseItem) {
            const disabled = Object.prototype.hasOwnProperty.call(course, 'disabled')
                ? course.disabled === true
                : this.moreCourseOfferDisabled(course, moreCourse)

            if (disabled) return

            const selectionKey = course?.selectionKey || this.offeredCourseSelectionKey(course, moreCourse)
            if (!selectionKey) return

            const moreOfferedCourseSelections = { ...this.currentMoreOfferedCourseSelectionOverrides() }
            const offeredCourseSelections = { ...this.currentOfferedCourseSelectionOverrides() }
            const selected = Object.prototype.hasOwnProperty.call(course, 'selected')
                ? course.selected === true
                : this.moreOfferedCourseSelected(course, moreCourse)
            const usesExplicitSelectedOfferList = this.moreCourseUsesExplicitSelectedOfferList(moreCourse)

            if (selected) {
                moreOfferedCourseSelections[selectionKey] = false
            } else if (usesExplicitSelectedOfferList) {
                moreOfferedCourseSelections[selectionKey] = true
                delete offeredCourseSelections[selectionKey]
            } else {
                delete moreOfferedCourseSelections[selectionKey]
                delete offeredCourseSelections[selectionKey]
            }

            this.saveMoreOfferedCourseSelections(moreOfferedCourseSelections, offeredCourseSelections)
        },
        deselectSelectedMoreCourseOfferedCourses() {
            const offeredCourses = this.selectedMoreCourseOfferedCourseItems
            if (!offeredCourses.length) return
            if (offeredCourses.every((course) => !this.moreOfferedCourseSelected(course))) return

            const moreOfferedCourseSelections = { ...this.currentMoreOfferedCourseSelectionOverrides() }

            offeredCourses.forEach((course) => {
                const selectionKey = course?.selectionKey || this.offeredCourseSelectionKey(course, this.selectedMoreCourseItem)
                if (!selectionKey) return

                moreOfferedCourseSelections[selectionKey] = false
            })

            this.saveMoreOfferedCourseSelections(moreOfferedCourseSelections)
        },
        saveMoreOfferedCourseSelections(moreOfferedCourseSelections, offeredCourseSelections = { ...this.currentOfferedCourseSelectionOverrides() }) {
            this.saveCurrentMoreCoursesSelectionState({
                courseSelections: { ...this.currentCourseSelectionOverrides() },
                moreOfferedCourseSelections,
                offeredCourseSelections,
            })
        },
        saveCurrentMoreCoursesSelectionState(selections) {
            if (this.adoptedTimetableVisible) {
                this.saveAdoptedTimetableSelectionState(selections)

                return
            }

            this.saveMoreCoursesSelectionState(selections)
        },
        saveAdoptedTimetableSelectionState({ courseSelections, moreOfferedCourseSelections, offeredCourseSelections }) {
            const timetableV2Selection = this.clonedTimetableV2Selection(
                this.adoptedTimetableSelectionSnapshot || this.storedTimetableV2Selection,
            )

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

            this.adoptedTimetableSelectionSnapshot = timetableV2Selection
            this.saveAdoptedTimetableStateSnapshot()
        },
        saveMoreCoursesSelectionState({ courseSelections, moreOfferedCourseSelections, offeredCourseSelections }) {
            const saveAdaptedSelection = this.courseReviewVisible
            const timetableV2Selection = {
                ...(saveAdaptedSelection || this.timetableCalculationVisible
                    ? this.currentTimetableV2SelectionForCourseState()
                    : this.storedTimetableV2Selection),
            }

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

            if (this.timetableCalculationVisible) {
                this.calculationTimetableV2Selection = timetableV2Selection
                this.saveStoredTimetableState({
                    ...this.defaultStoredTimetableState(),
                    ...this.storedTimetableStateForSaving(),
                    adaptedTimetableV2Selection: timetableV2Selection,
                    transferredStudentContext: this.storedTimetableStudentContext,
                })

                return
            }

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                ...(saveAdaptedSelection
                    ? { adaptedTimetableV2Selection: timetableV2Selection }
                    : { timetableV2Selection }),
                transferredStudentContext: this.storedTimetableStudentContext,
            })
        },
        persistCalculationSelectionForCourseReview() {
            if (!this.timetableCalculationVisible || !this.calculationTimetableV2Selection) return

            const timetableV2Selection = this.clonedTimetableV2Selection(this.calculationTimetableV2Selection)

            if (
                this.timetableCalculationSelectionSnapshot(timetableV2Selection)
                === this.timetableCalculationSelectionSnapshot(this.storedAdaptedTimetableV2Selection || {})
            ) {
                return
            }

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                adaptedTimetableV2Selection: timetableV2Selection,
                transferredStudentContext: this.storedTimetableStudentContext,
            })
        },
        selectedCourseItemActive(course) {
            return this.selectedCourseItemsClickable
                && this.selectedReviewCourseItem?.selectionKey === course?.selectionKey
        },
        selectedCourseItemPassive() {
            return (!this.selectedCourseItemsClickable && !this.selectedCourseItemsDeletable)
                || this.selectedCourseItemsDisabled
        },
        selectedCourseItemColor(course) {
            return this.selectedCourseItemHasConflict(course) ? 'error' : 'success'
        },
        selectedCourseItemHasConflict(course) {
            if (!this.adoptedTimetableVisible) return false

            const slots = Object.values(this.currentTimetableV2CalculationResult?.selected_timetable?.slots || {})

            return slots.some((slot) => this.selectedCourseItemConflictsWithSlot(course, slot))
        },
        selectedCourseItemConflictsWithSlot(course, slot) {
            const conflicts = this.selectedTimetableV2DisplayedSlotConflicts(slot)
            if (!conflicts.length) return false

            return conflicts.some((conflict) =>
                this.timetableSlotMatchesCourse(slot, course)
                || this.timetableSlotMatchesCourse(conflict, course))
        },
        selectReviewCourse(course) {
            if (!this.selectedCourseItemsClickable || this.selectedCourseItemsDisabled) return

            const selectionKey = course?.selectionKey || ''

            this.selectedReviewCourseKey = this.selectedReviewCourseKey === selectionKey
                ? ''
                : selectionKey
        },
        instructionCourseFiltersForSelection(selection = {}) {
            return {
                includeNormalunterrichtCourses: selection?.includeNormalunterrichtCourses !== false,
                includeDistanceLearningCourses: selection?.includeDistanceLearningCourses !== false,
                includeKompaktunterrichtCourses: selection?.includeKompaktunterrichtCourses !== false,
            }
        },
        unrestrictedInstructionCourseFilters() {
            return {
                includeNormalunterrichtCourses: true,
                includeDistanceLearningCourses: true,
                includeKompaktunterrichtCourses: true,
            }
        },
        updateInstructionCourseFilter(key, value) {
            if (![
                'includeNormalunterrichtCourses',
                'includeDistanceLearningCourses',
                'includeKompaktunterrichtCourses',
            ].includes(key)) return

            const saveAdaptedSelection = this.courseReviewVisible
            const timetableV2Selection = {
                ...(saveAdaptedSelection || this.timetableCalculationVisible
                    ? this.currentTimetableV2SelectionForCourseState()
                    : this.storedTimetableV2Selection),
            }

            if (value === false) {
                timetableV2Selection[key] = false
            } else {
                delete timetableV2Selection[key]
            }

            if (this.timetableCalculationVisible) {
                this.calculationTimetableV2Selection = timetableV2Selection

                return
            }

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                ...(saveAdaptedSelection
                    ? { adaptedTimetableV2Selection: timetableV2Selection }
                    : { timetableV2Selection }),
                transferredStudentContext: this.storedTimetableStudentContext,
            })
        },
        removeSelectedCourseItem(course) {
            if (!this.selectedCourseItemsDeletable || this.selectedCourseItemsDisabled) return

            if ((this.timetableCalculationVisible || this.adoptedTimetableVisible) && !this.moreCoursesVisible) {
                this.openMoreCoursesPendingActions()
            }

            this.saveCourseItemSelection(course, false)
            this.markMoreCourseRestorable(course)
        },
        saveCourseItemSelection(course, selected) {
            const courseGroup = course?.courseGroup
            const selectionKey = this.courseSelectionKey(course, courseGroup) || String(course?.selectionKey || '').trim()
            if (!selectionKey || !courseGroup) return

            const courseSelections = { ...this.currentCourseSelectionOverrides() }
            const explicitlySelected = this.courseSelectionExplicitValue(course, courseGroup, courseSelections) === true
            const selectionKeys = this.courseSelectionKeys(course, courseGroup)
            const currentlySelected = (Array.isArray(this.selectedCourseItems) ? this.selectedCourseItems : [])
                .some((selectedCourse) => selectionKeys.includes(selectedCourse?.selectionKey))

            if (selected) {
                this.clearPendingRemovedSelectedCourse(course)

                if (this.courseItemDefaultSelected(course, courseGroup)) {
                    this.courseSelectionKeys(course, courseGroup).forEach((courseSelectionKey) => {
                        delete courseSelections[courseSelectionKey]
                    })
                } else {
                    courseSelections[selectionKey] = true
                }
            } else if (this.courseItemDefaultSelected(course, courseGroup) || (currentlySelected && !explicitlySelected)) {
                this.courseSelectionKeys(course, courseGroup).forEach((courseSelectionKey) => {
                    courseSelections[courseSelectionKey] = false
                })
            } else {
                delete courseSelections[selectionKey]
            }

            this.saveCurrentMoreCoursesSelectionState({
                courseSelections,
                moreOfferedCourseSelections: { ...this.currentMoreOfferedCourseSelectionOverrides() },
                offeredCourseSelections: { ...this.currentOfferedCourseSelectionOverrides() },
            })

            if (!selected) {
                this.markPendingRemovedSelectedCourse(course)
            }
        },
        pendingRemovedSelectedCourse(course) {
            const pendingRemovedSelectedCourseKeys = this.pendingRemovedSelectedCourseKeys || {}

            return this.courseSelectionKeys(course, course?.courseGroup)
                .some((courseSelectionKey) => pendingRemovedSelectedCourseKeys[courseSelectionKey] === true)
        },
        markPendingRemovedSelectedCourse(course) {
            const courseSelectionKeys = this.courseSelectionKeys(course, course?.courseGroup)
            if (!courseSelectionKeys.length) return

            this.pendingRemovedSelectedCourseKeys = {
                ...(this.pendingRemovedSelectedCourseKeys || {}),
                ...Object.fromEntries(courseSelectionKeys.map((courseSelectionKey) => [courseSelectionKey, true])),
            }
        },
        clearPendingRemovedSelectedCourse(course) {
            const courseSelectionKeys = this.courseSelectionKeys(course, course?.courseGroup)
            if (!courseSelectionKeys.length || !this.hasPendingRemovedSelectedCourses) return

            const pendingRemovedSelectedCourseKeys = { ...(this.pendingRemovedSelectedCourseKeys || {}) }

            courseSelectionKeys.forEach((courseSelectionKey) => {
                delete pendingRemovedSelectedCourseKeys[courseSelectionKey]
            })

            this.pendingRemovedSelectedCourseKeys = pendingRemovedSelectedCourseKeys
        },
        clearPendingRemovedSelectedCourses() {
            this.pendingRemovedSelectedCourseKeys = {}
        },
        offeredCourseItemsAllDeselected(course) {
            const offeredCourses = this.offeredCourseItemsForSelectedCourse(course)

            return offeredCourses.length > 0 && offeredCourses.every((offeredCourse) => !this.offeredCourseSelected(offeredCourse))
        },
        offeredCourseItemsAllDeselectedForCalculation(course) {
            const offeredCourses = this.offeredCourseItemsForSelectedCourse(course)

            return offeredCourses.length > 0
                && offeredCourses.every((offeredCourse) => !this.offeredCourseSelectedForCalculation(offeredCourse, course))
        },
        offeredCourseSelectedForCalculation(offeredCourse, selectedCourse) {
            const instructionFilters = this.courseExplicitlySelectedForCalculation(selectedCourse)
                ? this.unrestrictedInstructionCourseFilters()
                : this.instructionCourseFiltersForSelection(this.currentTimetableV2SelectionForCourseState())

            return this.offeredCourseSelectedWithContext(
                offeredCourse,
                this.currentOfferedCourseSelectionOverrides(),
                instructionFilters,
            )
        },
        courseExplicitlySelectedForCalculation(course) {
            const courseGroup = course?.courseGroup
            if (!courseGroup) return false

            return this.courseSelectionExplicitValue(course, courseGroup, this.currentCourseSelectionOverrides()) === true
        },
        offeredCourseItemsPartlySelected(course) {
            const offeredCourses = this.offeredCourseItemsForSelectedCourse(course)
            if (offeredCourses.length <= 1) return false

            const selectedOfferedCourseCount = offeredCourses
                .filter((offeredCourse) => this.offeredCourseSelected(offeredCourse))
                .length

            return selectedOfferedCourseCount > 0 && selectedOfferedCourseCount < offeredCourses.length
        },
        offeredCourseItemsForSelectedCourse(course) {
            if (this.offeredCourseItemsCacheCourseGroups !== this.courseGroups) {
                this.resetOfferedCourseItemsCache()
            }

            const cacheKey = this.offeredCourseItemsCacheKey(course)
            if (cacheKey && Array.isArray(this.offeredCourseItemsCache?.[cacheKey])) {
                return this.offeredCourseItemsCache[cacheKey]
            }

            const courseAliases = this.courseCodeAliases({ code: course?.code || course?.label })
            const courseGroups = this.courseGroupsForCourseAliases(courseAliases)

            const offeredCourseItems = courseGroups
                .filter((courseGroup) => this.courseGroupMatchesSelectedCourse(courseGroup, course))
                .map((courseGroup) => this.offeredCourseGroupItem(courseGroup))
                .sort((firstCourse, secondCourse) => this.compareOfferedCourseItems(firstCourse, secondCourse))

            const uniqueOfferedCourseItems = this.uniqueOfferedCourseItems(offeredCourseItems, course)

            if (cacheKey) {
                this.offeredCourseItemsCache = {
                    ...this.offeredCourseItemsCache,
                    [cacheKey]: uniqueOfferedCourseItems,
                }
            }

            return uniqueOfferedCourseItems
        },
        instructionFilteredOfferedCourseItemsForSelectedCourse(course, filters = null) {
            return this.offeredCourseItemsForSelectedCourse(course)
                .filter((offeredCourse) => this.offeredCourseIncludedByInstructionFilters(offeredCourse, filters))
        },
        offeredCourseItemsCacheKey(course) {
            const courseKey = [
                this.courseGroupsRevision,
                this.normalizedCourseCode(course?.code || course?.label || ''),
                String(course?.key || course?.selectionKey || '').trim(),
                String(course?.courseGroup || '').trim(),
            ].join('|')

            return courseKey.replace(/\|+$/g, '')
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
                    isKompaktunterricht: existingCourseItem.isKompaktunterricht === true || courseItem.isKompaktunterricht === true,
                    roomsLabel: this.mergedLabelList(existingCourseItem.roomsLabel, courseItem.roomsLabel),
                    recurrenceLabel: this.mergedLabelList(existingCourseItem.recurrenceLabel, courseItem.recurrenceLabel),
                    scheduleSlots: this.mergedScheduleSlots(existingCourseItem.scheduleSlots, courseItem.scheduleSlots),
                })
            })

            return [...courseItemsByIdentity.values()].map((courseItem) => {
                const scheduleSlots = this.mergedScheduleSlots(courseItem.scheduleSlots, [])
                const isKompaktunterricht = this.offeredCourseIsKompaktunterricht(courseItem)

                return {
                    ...courseItem,
                    backendSelectionKey: this.offeredCourseBackendSelectionKey(courseItem, selectedCourse),
                    distanceLearning: this.offeredCourseIsDistanceLearning({ ...courseItem, scheduleSlots }, selectedCourse),
                    isKompaktunterricht,
                    recurrenceLabel: courseItem.recurrenceLabel,
                    selectionKey: this.offeredCourseSelectionKey(courseItem, selectedCourse),
                    scheduleLabel: this.compactScheduleSlotsLabel(scheduleSlots, {
                        recurrenceFallback: courseItem.recurrenceLabel,
                        showDateRanges: isKompaktunterricht,
                    }),
                    scheduleSlots,
                }
            })
        },
        offeredCourseSelected(course) {
            return this.offeredCourseSelectedWithContext(
                course,
                this.currentOfferedCourseSelectionOverrides(),
                this.instructionCourseFiltersForSelection(this.currentTimetableV2SelectionForCourseState()),
            )
        },
        offeredCourseSelectedWithContext(course, offeredCourseSelections, instructionFilters) {
            const selectionKey = course?.selectionKey || this.offeredCourseSelectionKey(course)
            if (!selectionKey) return true
            if (offeredCourseSelections?.[selectionKey] === true) return true
            if (offeredCourseSelections?.[selectionKey] === false) return false

            return this.offeredCourseIncludedByInstructionFilters(course, instructionFilters)
        },
        selectedReviewOfferedCourseItem(course, options = {}) {
            const selected = this.offeredCourseSelectedWithContext(
                course,
                options.offeredCourseSelections || {},
                options.instructionFilters || null,
            )
            const selectable = options.selectable === true
            const instructionLabel = this.offeredCourseInstructionLabel(course)

            return {
                ...course,
                ariaPressed: selectable ? (selected ? 'true' : 'false') : undefined,
                classes: {
                    'students-timetable-v2-offered-courses-card__item--toggle': selectable,
                    'students-timetable-v2-offered-courses-card__item--selected': selected,
                    'students-timetable-v2-offered-courses-card__item--deselected': !selected,
                },
                instructionLabel,
                instructionVisible: instructionLabel !== '',
                selected,
                title: undefined,
            }
        },
        selectedAdaptedOfferedCourseItem(offeredCourse, selectedCourse, index = 0) {
            const selectionKey = offeredCourse?.selectionKey || this.offeredCourseSelectionKey(offeredCourse, selectedCourse)
            const backendSelectionKey = String(offeredCourse?.backendSelectionKey || '').trim()
            const label = String(
                offeredCourse?.groupSelectionLabel
                || offeredCourse?.name
                || offeredCourse?.code
                || 'Ohne Bezeichnung',
            ).trim()
            const courseLabel = this.courseDisplayLabel(selectedCourse?.label || selectedCourse?.code || '')
            const selected = this.offeredCourseSelectedForCalculation(offeredCourse, selectedCourse)

            return {
                key: [
                    selectionKey,
                    backendSelectionKey,
                    index,
                ].filter((value) => String(value || '').trim()).join('|'),
                courseLabel,
                backendSelectionKey,
                instructionLabel: this.offeredCourseInstructionLabel(offeredCourse),
                label,
                roomsLabel: String(offeredCourse?.roomsLabel || '').trim(),
                scheduleLabel: String(offeredCourse?.scheduleLabel || '').trim(),
                selected,
                selectionKey,
                sortLabel: [
                    label,
                    courseLabel,
                ].filter(Boolean).join(' '),
            }
        },
        offeredCourseIncludedByInstructionFilters(course, filters = null) {
            const resolvedFilters = filters || this.instructionCourseFiltersForSelection(
                this.currentTimetableV2SelectionForCourseState(),
            )

            if (!resolvedFilters.includeNormalunterrichtCourses && this.offeredCourseIsNormalunterrichtCourse(course)) {
                return false
            }

            if (!resolvedFilters.includeDistanceLearningCourses && this.offeredCourseIsDistanceLearningCourse(course)) {
                return false
            }

            if (!resolvedFilters.includeKompaktunterrichtCourses && this.offeredCourseIsKompaktunterricht(course)) {
                return false
            }

            return true
        },
        offeredCourseIsNormalunterrichtCourse(course) {
            return !this.offeredCourseIsDistanceLearningCourse(course)
                && !this.offeredCourseIsKompaktunterricht(course)
        },
        offeredCourseIsDistanceLearningCourse(course) {
            return course?.distanceLearning === true
                || course?.isDistanceLearningCourse === true
                || course?.is_fu === true
                || course?.courseGroup?.distanceLearning === true
                || course?.courseGroup?.isDistanceLearningCourse === true
                || course?.courseGroup?.is_fu === true
        },
        toggleOfferedCourseItem(course) {
            const selectionKey = course?.selectionKey || this.offeredCourseSelectionKey(course)
            if (!selectionKey) return

            const offeredCourseSelections = { ...this.currentOfferedCourseSelectionOverrides() }

            const selected = Object.prototype.hasOwnProperty.call(course, 'selected')
                ? course.selected === true
                : this.offeredCourseSelected(course)

            if (selected) {
                offeredCourseSelections[selectionKey] = false
            } else {
                offeredCourseSelections[selectionKey] = true
            }

            this.saveOfferedCourseSelections(offeredCourseSelections)
        },
        selectAdaptedOfferedCourseGroupOffers(group, selected) {
            const offers = Array.isArray(group?.offers) ? group.offers : []
            if (!offers.length) return

            const offeredCourseSelections = { ...this.currentOfferedCourseSelectionOverrides() }

            offers.forEach((offer) => {
                const selectionKey = offer?.selectionKey || this.offeredCourseSelectionKey(offer)
                if (!selectionKey) return

                offeredCourseSelections[selectionKey] = selected === true
            })

            this.saveOfferedCourseSelections(offeredCourseSelections)
        },
        toggleSelectedReviewOfferedCourseItem(course) {
            if (!this.selectedCourseOfferItemsSelectable) return

            this.toggleOfferedCourseItem(course)
        },
        applyOfferedCourseBulkSelection(optionKey) {
            const offeredCourses = this.allSelectedOfferedCourseItems()
            if (!offeredCourses.length) return

            const offeredCourseSelections = { ...this.currentOfferedCourseSelectionOverrides() }

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
        offeredCourseInstructionVisible(course) {
            return this.offeredCourseInstructionLabel(course) !== ''
        },
        offeredCourseInstructionLabel(course) {
            if (this.offeredCourseIsKompaktunterricht(course)) return 'Kompaktunterricht'
            if (course?.distanceLearning === true) return 'Fernunterricht'

            return ''
        },
        offeredCourseIsKompaktunterricht(courseItem) {
            return courseItem?.isKompaktunterricht === true
                || courseItem?.is_kompaktunterricht === true
                || courseItem?.courseGroup?.is_kompaktunterricht === true
                || courseItem?.courseGroup?.isKompaktunterricht === true
                || this.courseItemLooksLikeRKompaktunterricht(courseItem)
        },
        courseItemLooksLikeRKompaktunterricht(courseItem) {
            return [
                courseItem,
                courseItem?.courseGroup,
            ].some((course) => [
                course?.sourceLabel,
                course?.groupSelectionLabel,
                course?.name,
                course?.label,
                course?.code,
                course?.class_name,
                course?.display_label,
                course?.student_group,
                course?.title,
            ].some((label) => this.courseLabelLooksLikeRKompaktunterricht(label)))
        },
        courseLabelLooksLikeRKompaktunterricht(value) {
            return /(?:^|[\s-])\d+\s*R(?:$|[\s-])/iu.test(String(value || ''))
        },
        selectedTimetableV2SlotInstructionLabel(slot) {
            if (this.selectedTimetableV2SlotIsKompaktunterricht(slot)) return 'Kompaktunterricht'

            if (slot?.isDistanceLearningCourse === true || slot?.courseGroup?.distanceLearning === true) return 'Fernunterricht'

            return ''
        },
        selectedTimetableV2SlotIsKompaktunterricht(slot) {
            return slot?.isKompaktunterrichtCourse === true
                || slot?.isKompaktunterricht === true
                || slot?.courseGroup?.is_kompaktunterricht === true
                || slot?.courseGroup?.isKompaktunterricht === true
                || this.courseItemLooksLikeRKompaktunterricht(slot)
        },
        saveOfferedCourseSelections(offeredCourseSelections) {
            if (this.adoptedTimetableVisible) {
                this.saveAdoptedTimetableSelectionState({
                    courseSelections: { ...this.currentCourseSelectionOverrides() },
                    moreOfferedCourseSelections: { ...this.currentMoreOfferedCourseSelectionOverrides() },
                    offeredCourseSelections,
                })

                return
            }

            const saveAdaptedSelection = this.courseReviewVisible
            const timetableV2Selection = {
                ...(saveAdaptedSelection || this.timetableCalculationVisible
                    ? this.currentTimetableV2SelectionForCourseState()
                    : this.storedTimetableV2Selection),
            }

            if (Object.keys(offeredCourseSelections).length) {
                timetableV2Selection.offeredCourseSelections = offeredCourseSelections
            } else {
                delete timetableV2Selection.offeredCourseSelections
            }

            if (this.timetableCalculationVisible) {
                this.calculationTimetableV2Selection = timetableV2Selection

                return
            }

            this.saveStoredTimetableState({
                ...this.defaultStoredTimetableState(),
                ...this.storedTimetableStateForSaving(),
                ...(saveAdaptedSelection
                    ? { adaptedTimetableV2Selection: timetableV2Selection }
                    : { timetableV2Selection }),
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
            const courseKey = this.normalizedCourseCode(selectedCourse?.code || selectedCourse?.label || '')
                || this.timetableV2CalculationCourseKey(selectedCourse)

            return [
                courseKey,
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
            if (!this.courseGroupMatchesSelectedReligionCourse(courseGroupCodes, course)) return false

            return !this.courseGroupHasConflictingModuleCode(this.courseGroupLeadingCodes(courseGroup), courseAliases)
        },
        courseGroupMatchesSelectedReligionCourse(courseGroupCodes, course) {
            const selectedReligionBase = this.selectedReligionCourseBase(course)
            if (!selectedReligionBase) return true

            return (Array.isArray(courseGroupCodes) ? courseGroupCodes : [])
                .map((courseCode) => this.courseCodeModuleParts(courseCode).base)
                .some((base) => this.religionCourseBase(base) === selectedReligionBase)
        },
        selectedReligionCourseBase(course) {
            const courseBase = this.religionCourseBase(this.courseCodeModuleParts(course?.code || course?.label || '').base)
            if (!courseBase) return ''
            if (courseBase !== 'R') return courseBase

            return this.religionCourseBase(this.effectiveTimetableV2Selection?.religion)
        },
        religionCourseBase(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const religionBases = {
                ET: 'ETH',
                ETH: 'ETH',
                R: 'R',
                REV: 'REV',
                RIS: 'RIS',
                RK: 'RK',
                RKATH: 'RK',
                ROR: 'ROR',
            }

            return religionBases[normalizedValue] || ''
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
            const code = this.courseDisplayLabel(courseGroup?.title || courseGroup?.course || courseGroup?.module_code || courseGroup?.subject)
            const displayCode = code
            const isKompaktunterricht = this.offeredCourseIsKompaktunterricht(courseGroup)
            const recurrenceLabel = this.offeredCourseRecurrenceLabel(courseGroup)
            const scheduleSlots = this.courseGroupScheduleSlots(courseGroup)

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
                courseGroup: this.clonedTimetableV2Value(courseGroup),
                groupSelectionLabel: this.courseGroupOptionLabel(courseGroup),
                name: this.offeredCourseGroupLabel(courseGroup, code, displayCode),
                semester: Number(courseGroup?.semester || 0) || null,
                weekday: Number(courseGroup?.weekday || 0) || null,
                hour: Number(courseGroup?.hour || 0) || null,
                scheduleSlots,
                scheduleLabel: this.compactScheduleSlotsLabel(scheduleSlots, {
                    recurrenceFallback: recurrenceLabel,
                    showDateRanges: isKompaktunterricht,
                }),
                recurrenceLabel,
                isKompaktunterricht,
                roomsLabel: (Array.isArray(courseGroup?.rooms) ? courseGroup.rooms : [])
                    .filter(Boolean)
                    .join(', '),
            }
        },
        offeredCourseGroupLabel(courseGroup, code, displayCode = code) {
            const teacher = this.cleanedOfferedCourseTeacherSegment(courseGroup?.teacher, code)
            const group = this.cleanedOfferedCourseGroupSegment(
                courseGroup?.student_group || courseGroup?.class_name || courseGroup?.display_label,
                code,
                teacher,
            )
            const fallbackLabel = this.courseDisplayLabel(courseGroup?.display_label || courseGroup?.title || courseGroup?.course || courseGroup?.subject)

            return this.uniqueValues([
                displayCode,
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
                return ''
            }

            return this.offeredCourseScheduleRecurrenceLabel(courseGroup)
        },
        offeredCourseScheduleRecurrenceLabel(courseGroup) {
            if (courseGroup?.is_block || String(courseGroup?.block_label || '').trim()) return ''

            const interval = this.courseGroupWeekInterval(courseGroup)
            if (interval) return this.selectedTimetableV2WeekIntervalLabel(interval, courseGroup)

            const recurrenceLabel = String(courseGroup?.recurrence_label || '').trim()
            if (/^2\s*-?\s*w(?:öchig|ochig)?(?:\s+[AB])?$/iu.test(recurrenceLabel)) {
                return /\s+[AB]$/iu.test(recurrenceLabel)
                    ? recurrenceLabel
                    : `${recurrenceLabel}${this.selectedTimetableV2TwoWeekParitySuffix(courseGroup)}`
            }

            return recurrenceLabel
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
        formatCompactDateWithWeekValue(value) {
            const date = this.normalizedDate(value)
            const label = this.formatCompactDateValue(value)
            const weekLabel = this.selectedTimetableV2DateWeekParityLabel(date)

            return label && weekLabel ? `${label}(${weekLabel})` : label
        },
        selectedTimetableV2DateWeekParityLabel(date) {
            const weekNumber = this.selectedTimetableV2IsoWeekNumber(date)
            if (!Number.isInteger(weekNumber)) return ''

            return weekNumber % 2 === 0 ? 'A' : 'B'
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
                courseGroup,
                from: timeRange.from,
                dateRangeLabel: this.courseGroupDateRangeLabel(courseGroup),
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
                    slot?.dateRangeLabel || '',
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
        compactScheduleSlotsLabel(scheduleSlots, options = {}) {
            const slots = (Array.isArray(scheduleSlots) ? scheduleSlots : [])
                .filter((slot) => Number.isFinite(Number(slot?.hour)) && Number(slot?.hour) > 0)
                .map((slot) => ({
                    ...slot,
                    dateRangeLabel: options?.showDateRanges === true ? slot.dateRangeLabel || '' : '',
                    recurrenceLabel: this.scheduleRecurrenceLabel(slot.recurrenceLabel || options?.recurrenceFallback || ''),
                }))
                .sort((firstSlot, secondSlot) =>
                    Number(firstSlot?.weekday || 0) - Number(secondSlot?.weekday || 0)
                    || Number(firstSlot?.hour || 0) - Number(secondSlot?.hour || 0))
            const ranges = []

            this.scheduleSlotsWithFullTwoWeekPairs(slots).forEach((slot) => {
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
                    dateRangeLabel: slot.dateRangeLabel || '',
                    recurrenceLabel: slot.recurrenceLabel || '',
                    until: slot.until || '',
                })
            })

            return ranges.map((range) => this.scheduleRangeLabel(range)).filter(Boolean).join(', ')
        },
        scheduleSlotsWithFullTwoWeekPairs(scheduleSlots) {
            const twoWeekSlotsByKey = new Map()

            scheduleSlots.forEach((slot, index) => {
                const parity = this.twoWeekParityFromRecurrenceLabel(slot?.recurrenceLabel)
                if (!parity) return

                const slotKey = this.scheduleSlotWithoutRecurrenceKey(slot)

                if (!twoWeekSlotsByKey.has(slotKey)) {
                    twoWeekSlotsByKey.set(slotKey, {
                        A: [],
                        B: [],
                    })
                }

                twoWeekSlotsByKey.get(slotKey)[parity].push({ index, slot })
            })

            const replacementSlotsByIndex = new Map()
            const skippedIndexes = new Set()

            twoWeekSlotsByKey.forEach((slotsByParity) => {
                if (!slotsByParity.A.length || !slotsByParity.B.length) return

                const matchingSlots = [...slotsByParity.A, ...slotsByParity.B]
                const replacementIndex = Math.min(...matchingSlots.map(({ index }) => index))
                const replacementSlot = matchingSlots
                    .find(({ index }) => index === replacementIndex)
                    ?.slot

                matchingSlots.forEach(({ index }) => skippedIndexes.add(index))
                replacementSlotsByIndex.set(replacementIndex, {
                    ...replacementSlot,
                    recurrenceLabel: '',
                })
            })

            return scheduleSlots.flatMap((slot, index) => {
                if (replacementSlotsByIndex.has(index)) {
                    return [replacementSlotsByIndex.get(index)]
                }

                if (skippedIndexes.has(index)) {
                    return []
                }

                return [slot]
            })
        },
        scheduleSlotWithoutRecurrenceKey(slot) {
            return [
                Number(slot?.weekday || 0) || '',
                Number(slot?.hour || 0) || '',
                slot?.from || '',
                slot?.until || '',
                slot?.dateRangeLabel || '',
            ].join('|')
        },
        twoWeekParityFromRecurrenceLabel(label) {
            return String(label || '').trim().match(/^2\s*-?\s*w(?:öchig|ochig)?\s+([AB])$/iu)?.[1]?.toUpperCase() || ''
        },
        scheduleRecurrenceLabel(label) {
            const recurrenceLabel = String(label || '').trim()
            if (!recurrenceLabel) return ''

            return this.recurrenceLabelCoversBothTwoWeekParities(recurrenceLabel)
                ? ''
                : recurrenceLabel
        },
        recurrenceLabelCoversBothTwoWeekParities(label) {
            const parities = String(label || '')
                .split(/\s*,\s*/u)
                .map(part => this.twoWeekParityFromRecurrenceLabel(part))
                .filter(Boolean)

            return parities.includes('A') && parities.includes('B')
        },
        scheduleSlotExtendsRange(range, slot) {
            return Number(range?.weekday || 0) === Number(slot?.weekday || 0)
                && Number(range?.endHour || 0) + 1 === Number(slot?.hour || 0)
                && String(range?.dateRangeLabel || '') === String(slot?.dateRangeLabel || '')
                && String(range?.recurrenceLabel || '') === String(slot?.recurrenceLabel || '')
        },
        scheduleRangeLabel(range) {
            const weekdayLabel = this.courseGroupWeekdayLabel(range?.weekday)
            const hourLabel = Number(range?.startHour) === Number(range?.endHour)
                ? `${Number(range?.startHour)}.`
                : `${Number(range?.startHour)}.-${Number(range?.endHour)}.`
            const recurrenceLabel = this.scheduleRecurrenceLabel(range?.recurrenceLabel)
            const timeRangeLabel = [range?.from, range?.until].filter(Boolean).join('-')
            const dateRangeLabel = String(range?.dateRangeLabel || '').trim()
            const dateRangeSuffix = dateRangeLabel ? `(${dateRangeLabel})` : ''

            return [weekdayLabel, hourLabel, recurrenceLabel, timeRangeLabel, dateRangeSuffix].filter(Boolean).join(' ')
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
            const from = this.formatTimeValue(
                courseGroup?.starts_at
                || courseGroup?.time_from
                || courseGroup?.from
                || schoolHour?.from,
            )
            const until = this.formatTimeValue(
                courseGroup?.ends_at
                || courseGroup?.time_until
                || courseGroup?.until
                || schoolHour?.until,
            )

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
        courseDisplayLabel(value) {
            const label = String(value || '').trim()
            if (!label) return ''

            return this.canonicalCourseDisplayLabel(label) || label
        },
        canonicalCourseDisplayLabel(value) {
            const label = String(value || '').trim()
            const match = label.match(/^([A-Za-zÄÖÜäöüß]+)\s*([0-9]*)/u)

            if (!match) return ''

            const [, subjectCode, moduleCode] = match
            const normalizedSubjectCode = this.normalizedCourseCode(subjectCode)
            const subjectLabel = TIMETABLE_COURSE_SUBJECT_LABELS[
                TIMETABLE_COURSE_SUBJECT_DISPLAY_ALIASES[normalizedSubjectCode] || normalizedSubjectCode
            ]

            if (!subjectLabel) return ''

            const rest = label.slice(match[0].length)
            const formattedRest = rest

            return `${subjectLabel.code}${moduleCode || ''}${formattedRest || ''}`
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
            this.studentSelectionExplicitlyCleared = false
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
            if (this.courseGroups.length) {
                this.courseGroupsLoaded = true
                this.applyCourseLimitPreselection()

                return
            }

            if (this.courseGroupsLoading) return

            this.courseGroupsLoading = true
            this.courseGroupsError = ''

            try {
                const response = await axios.get('/api/admin/students-timetables/course-groups')

                this.replaceCourseGroups(response.data?.data || [])
            } catch {
                this.replaceCourseGroups([])
                this.courseGroupsError = 'Die angebotenen Module konnten nicht geladen werden.'
            } finally {
                this.courseGroupsLoaded = true
                this.courseGroupsLoading = false
                this.applyCourseLimitPreselection()
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
                this.subjectRowsError = 'Die Module konnten nicht geladen werden.'
            } finally {
                this.subjectRowsLoading = false
                this.applyCourseLimitPreselection()
            }
        },
        async loadTimetableV2SelectionBootstrap(studentCode = '', options = {}) {
            const normalizedStudentCode = this.normalizedStudentCode(studentCode)
            const selection = normalizedStudentCode ? this.studentOverviewSelectionPayload() : {}

            this.selectionBootstrapLoading = true
            this.courseGroupsLoading = !this.courseGroups.length
            this.subjectRowsLoading = !this.subjectRows.length
            this.courseGroupsError = ''
            this.subjectRowsError = ''

            try {
                const response = await axios.get(TIMETABLE_V2_SELECTION_BOOTSTRAP_ENDPOINT, {
                    params: {
                        ...(normalizedStudentCode ? { student_code: normalizedStudentCode } : {}),
                        ...(normalizedStudentCode ? { strict_selection: options.strictSelection === false ? 0 : 1 } : {}),
                        ...(Object.keys(selection).length ? { selection } : {}),
                    },
                })
                const data = response.data?.data || {}

                if (options.applyStoredState === true) {
                    const storedState = data.state

                    this.storedTimetableState = storedState && typeof storedState === 'object' && !Array.isArray(storedState)
                        ? storedState
                        : null
                    this.storageRevision++
                }

                if (Array.isArray(data.course_groups)) {
                    this.replaceCourseGroups(data.course_groups)
                    this.courseGroupsLoaded = true
                }

                if (Array.isArray(data.subjects)) {
                    this.subjectRows = data.subjects
                }

                const overviewStudentCode = normalizedStudentCode
                    || this.normalizedStudentCode(data.student_overview?.student?.student_code)
                    || this.storedTimetableStudentCode

                if (overviewStudentCode && data.student_overview && typeof data.student_overview === 'object' && options.applyStudentOverview !== false) {
                    const requestSelection = this.studentOverviewSelectionPayload()
                    const requestKey = this.studentOverviewRequestKey(overviewStudentCode, requestSelection)

                    this.applyStoredStudentOverviewSummary(overviewStudentCode, data.student_overview, requestKey, {
                        persistState: options.persistStudentOverviewState !== false,
                    })
                } else if (options.applyStudentOverview !== false) {
                    this.applyCourseLimitPreselection()
                }

                return data
            } catch (error) {
                if (!this.courseGroups.length) {
                    this.replaceCourseGroups([])
                    this.courseGroupsError = 'Die angebotenen Module konnten nicht geladen werden.'
                }

                if (!this.subjectRows.length) {
                    this.subjectRows = []
                    this.subjectRowsError = 'Die Module konnten nicht geladen werden.'
                }

                throw error
            } finally {
                this.selectionBootstrapLoading = false
                this.courseGroupsLoading = false
                this.subjectRowsLoading = false
            }
        },
        async loadStoredStudentOverview(studentCode, options = {}) {
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

            try {
                const data = await this.loadTimetableV2SelectionBootstrap(normalizedStudentCode, {
                    applyStoredState: false,
                    applyStudentOverview: false,
                    strictSelection: options.strictSelection !== false,
                })

                if (requestId !== this.studentCompletedCoursesRequestId) return
                if (this.storedTimetableStudentCode !== normalizedStudentCode) return

                const overviewSummary = data.student_overview || {}

                this.applyStoredStudentOverviewSummary(normalizedStudentCode, overviewSummary, requestKey)
            } catch {
                if (requestId !== this.studentCompletedCoursesRequestId) return

                this.studentCompletedCoursesError = 'Die abgeschlossenen Module konnten nicht geladen werden.'
            } finally {
                if (requestId === this.studentCompletedCoursesRequestId) {
                    this.studentOverviewActiveRequestKey = ''
                    this.studentCompletedCoursesLoading = false
                }
            }
        },
        applyStoredStudentOverviewSummary(normalizedStudentCode, overviewSummary, requestKey = '', options = {}) {
            if (this.storedTimetableStudentCode && this.storedTimetableStudentCode !== normalizedStudentCode) return

            const courseHistory = this.overviewStudentCourseHistoryFromSummary(overviewSummary)
            const storedState = this.storedTimetableStateForSaving()
            const inferredSelection = this.timetableV2SelectionFromStudentOverviewSelection(overviewSummary?.selection || {})
            const timetableV2Selection = this.timetableV2SelectionWithCourseDefaults({
                ...inferredSelection,
                ...(storedState?.timetableV2Selection || {}),
            }, courseHistory)

            const nextState = {
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
            }

            if (options.persistState === false) {
                this.storedTimetableState = nextState
                this.storageRevision++
                this.resetDraftCourseSelections()
            } else if (!this.storedTimetableStatesEqual(storedState, nextState)) {
                this.saveStoredTimetableState(nextState)
            }

            this.studentOverviewLoadedRequestKey = requestKey
            this.applyCourseLimitPreselection()
        },
        timetableV2SelectionFromStudentOverviewSelection(selection) {
            if (!selection || typeof selection !== 'object' || Array.isArray(selection)) return {}

            return Object.fromEntries(
                [
                    ['semester', selection.semester],
                    ['religion', selection.religion],
                    ['language', selection.language],
                    ['branch', selection.branch],
                    ['artsSubject', selection.arts_subject ?? selection.artsSubject],
                ].filter(([, value]) => String(value || '').trim() !== '')
            )
        },
        selectStudentDraft(studentCode) {
            const normalizedStudentCode = this.normalizedStudentCode(studentCode)

            this.studentSelectionDraft.studentCode = normalizedStudentCode
            this.studentSelectionExplicitlyCleared = normalizedStudentCode === null
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
            if (!selectedStudent) return null

            this.selectedReviewCourseKey = ''
            this.clearAdoptedPublishedTimetableReport()
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
            this.loadStoredStudentOverview(studentCode, { strictSelection: false })
        },
        clearStoredTimetableStudent() {
            this.selectedReviewCourseKey = ''
            this.clearAdoptedPublishedTimetableReport()
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
            return String(this.effectiveTimetableV2Selection?.[item.key] || '') === String(option.value)
        },
        courseItemSelected(course, courseGroup) {
            const selectionKeys = this.courseSelectionKeys(course, courseGroup)
            const selectionKey = selectionKeys[0] || ''

            const explicitSelection = this.courseSelectionExplicitValue(course, courseGroup, this.courseSelectionOverrides)

            if (explicitSelection === false) return false
            if (this.courseItemUnavailableReason(course, courseGroup) === 'prerequisite') return false
            if (explicitSelection === true) return true
            if (this.courseItemUnavailable(course, courseGroup)) return false
            if (this.courseSelectionBlockedByOpenPrerequisiteCourse(course, courseGroup)) return false
            if (!selectionKey) return this.courseItemDefaultSelected(course, courseGroup)

            return this.courseItemDefaultSelected(course, courseGroup)
        },
        courseGroupDefaultSelected(courseGroup) {
            return courseGroup === 'semester'
        },
        courseItemDefaultSelected(course, courseGroup) {
            if (this.courseItemUnavailable(course, courseGroup)) {
                return false
            }

            if (courseGroup === 'missing' && this.negativeCourseBlockedByLowerModule(course)) {
                return false
            }

            if (courseGroup === 'missing' && this.courseIncludedInSelectedStudentDefaultSemester(course)) {
                return true
            }

            if (courseGroup === 'planned' && this.courseBlockedByNegativeCourse(course)) {
                return false
            }

            if (this.coursePreselectionBlockedByMissingPreviousModule(course, courseGroup)) {
                return false
            }

            if (this.courseSelectionBlockedByOpenPrerequisiteCourse(course, courseGroup)) {
                return false
            }

            return this.courseGroupDefaultSelected(courseGroup)
        },
        courseIncludedInSelectedStudentDefaultSemester(course) {
            return this.selectedStudentDefaultSemesterCourses()
                .some((defaultCourse) => this.courseCodesMatch(defaultCourse, course))
        },
        courseCodesMatch(firstCourse, secondCourse) {
            const firstCourseAliases = this.courseCodeAliases(firstCourse)
            const secondCourseAliases = this.courseCodeAliases(secondCourse)

            return firstCourseAliases.some((courseAlias) => secondCourseAliases.includes(courseAlias))
        },
        negativeCourseBlockedByLowerModule(course) {
            const courseBaseCode = this.courseBaseCode(course)
            const courseModuleNumber = this.courseModuleNumber(course)
            if (!courseBaseCode || !courseModuleNumber) return false
            if (courseBaseCode.length <= 1) return false

            return this.storedMissingCourseCardItems.some((otherCourse) =>
                this.courseBaseCode(otherCourse) === courseBaseCode
                    && this.courseModuleNumber(otherCourse) > 0
                    && this.courseModuleNumber(otherCourse) < courseModuleNumber)
        },
        courseBlockedByNegativeCourse(course) {
            const courseBaseCode = this.courseBaseCode(course)
            const courseModuleNumber = this.courseModuleNumber(course)
            if (!courseBaseCode || !courseModuleNumber) return false

            return this.storedMissingCourseCardItems.some((negativeCourse) =>
                this.courseBaseCode(negativeCourse) === courseBaseCode
                    && this.courseModuleNumber(negativeCourse) > 0
                    && courseModuleNumber > this.courseModuleNumber(negativeCourse) + 1)
        },
        coursePreselectionBlockedByMissingPreviousModule(course, courseGroup) {
            if (!['semester', 'planned'].includes(courseGroup)) return false

            const courseBaseCode = this.courseBaseCode(course)
            const courseModuleNumber = this.courseModuleNumber(course)
            if (!courseBaseCode || courseModuleNumber <= 1) return false

            return this.openStudentCourseItems().some((openCourse) =>
                this.courseBaseCode(openCourse) === courseBaseCode
                    && this.courseModuleNumber(openCourse) === courseModuleNumber - 1)
        },
        courseSelectionBlockedByOpenPrerequisiteCourse(course, courseGroup) {
            return this.courseMissingCompletedPrerequisite(course, courseGroup)
        },
        courseMissingCompletedPrerequisite(course, courseGroup) {
            if (!['semester', 'planned', 'additional'].includes(courseGroup)) return false
            if (!this.storedTimetableStudentContext) return false

            const courseModuleParts = this.courseModuleParts(course)
            if (!courseModuleParts.length) return false

            const completedCourseCodes = this.courseCodeSet(this.storedCompletedCourseItems)
            const openCourseCodes = this.courseCodeSet(this.openStudentCourseItems())

            return !courseModuleParts.some((parts) => this.courseModulePrerequisiteMet(parts, completedCourseCodes, openCourseCodes))
        },
        openStudentCourseItems() {
            const courses = this.storedTimetableStudentContext?.courses || {}

            return this.uniqueCourseItems([
                ...this.storedMissingCourseCardItems,
                ...this.storedSemesterCourseItems,
                ...this.storedPlannedCourseItems,
                ...this.missingStudentCourseItems(courses),
                ...this.normalizedOverviewCourseItems(courses.planned || []),
            ])
        },
        missingStudentCourseItems(courses = this.storedTimetableStudentContext?.courses || {}) {
            return this.uniqueCourseItems([
                ...this.storedMissingCourseCardItems,
                ...this.normalizedOverviewCourseItems(courses.missing || []),
            ])
        },
        courseGroupItems(courseGroup) {
            if (courseGroup === 'completed') {
                return this.storedCompletedCourseItems
            }

            if (courseGroup === 'missing') {
                return this.storedMissingCourseCardItems
            }

            if (courseGroup === 'semester') {
                return this.storedSemesterCourseItems
            }

            if (courseGroup === 'planned') {
                return this.storedPlannedCourseItems
            }

            if (courseGroup === 'additional') {
                return this.storedAdditionalCourseItems
            }

            return []
        },
        courseGroupAllSelected(courseGroup) {
            const courseItems = this.courseGroupSelectableItems(courseGroup)

            return courseItems.length > 0 && courseItems.every((course) => this.courseItemSelected(course, courseGroup))
        },
        courseGroupNoneSelected(courseGroup) {
            const courseItems = this.courseGroupSelectableItems(courseGroup)

            return courseItems.length > 0 && courseItems.every((course) => !this.courseItemSelected(course, courseGroup))
        },
        courseGroupSelectableItems(courseGroup) {
            return this.courseGroupItems(courseGroup)
                .filter((course) => !this.courseItemUnavailable(course, courseGroup))
        },
        courseGroupSelectionWouldExceedLimit(courseGroup) {
            const courseItems = this.courseGroupSelectableItems(courseGroup)
            const courseSelections = { ...this.courseSelectionOverrides }

            return courseItems
                .filter((course) => !this.courseSelectedBySelections(course, courseGroup, courseSelections))
                .some((course) => {
                    if (this.courseSelectionWouldExceedLimit(course, courseGroup, courseSelections)) return true

                    const selectionKey = this.courseSelectionKey(course, courseGroup)
                    if (!selectionKey) return false
                    const selectionKeys = this.courseSelectionKeys(course, courseGroup)

                    if (this.courseItemDefaultSelected(course, courseGroup)) {
                        selectionKeys.forEach((courseSelectionKey) => {
                            delete courseSelections[courseSelectionKey]
                        })
                    } else {
                        courseSelections[selectionKey] = true
                    }

                return false
            })
        },
        courseSelectionCardItem(course, courseGroup) {
            const selected = this.courseItemSelected(course, courseGroup)
            const unavailableReason = this.courseItemUnavailableReason(course, courseGroup)
            const unavailable = Boolean(unavailableReason)
            const disabled = this.courseItemSelectionDisabled(course, courseGroup)
            const selectionKey = this.courseSelectionKey(course, courseGroup)
            const selectionKeys = this.courseSelectionKeys(course, courseGroup)
            const label = this.courseDisplayLabel(course?.label || course?.code || course?.name || '')

            return {
                ariaDisabled: disabled ? 'true' : 'false',
                ariaPressed: selected ? 'true' : 'false',
                classes: {
                    [`students-timetable-v2-completed-courses__item--${courseGroup}`]: true,
                    'students-timetable-v2-completed-courses__item--selected': selected && (courseGroup === 'additional' || !unavailable),
                    'students-timetable-v2-completed-courses__item--deselected': !selected,
                    'students-timetable-v2-completed-courses__item--limit-disabled': disabled,
                    'students-timetable-v2-completed-courses__item--unavailable': unavailable,
                },
                color: this.courseSelectionCardItemColor(courseGroup, unavailable),
                course,
                courseGroup,
                defaultSelected: this.courseItemDefaultSelected(course, courseGroup),
                hoursNumber: this.courseHoursNumber(course),
                key: course.key || selectionKey || label,
                label,
                meta: this.courseSelectionCardItemMeta(course, courseGroup),
                selected,
                selectionKey,
                selectionKeys,
                title: this.courseItemSelectionDisabledLabel(course, courseGroup),
                unavailable,
                unavailableReason,
                variant: this.courseSelectionCardItemVariant(courseGroup, selected, unavailable),
            }
        },
        visitedCourseItem(course, status) {
            const label = this.courseDisplayLabel(course?.label || course?.code || course?.name || '')
            const grade = String(course?.meta || course?.grade || '').trim() || '-'
            const statusLabel = status === 'failed' ? 'Negativ' : 'Abgeschlossen'

            return {
                ...course,
                grade,
                key: `${status}-${String(course?.key || label || grade).trim()}`,
                label,
                status,
                title: `${label}: ${statusLabel}, Note ${grade}`,
            }
        },
        courseSelectionCardItemColor(courseGroup, unavailable) {
            if (['semester', 'planned', 'additional'].includes(courseGroup) && unavailable) return 'error'

            return 'success'
        },
        courseSelectionCardItemMeta(course, courseGroup) {
            if (courseGroup === 'completed') return this.completedCourseItemMeta(course)
            if (courseGroup === 'missing') return course.hoursMeta || this.courseHoursMeta(course)

            return String(course?.meta || course?.hoursMeta || this.courseHoursMeta(course)).trim()
        },
        courseHoursMeta(course) {
            const hours = this.courseHoursNumber(course)

            return hours ? `${this.formatHours(hours)} Std.` : ''
        },
        courseSelectionCardItemVariant(courseGroup, selected, unavailable) {
            if (courseGroup === 'additional') return selected ? 'tonal' : 'outlined'

            return selected || unavailable ? 'tonal' : 'outlined'
        },
        setCourseGroupSelection(courseGroup, selected) {
            const courseItems = this.courseGroupItems(courseGroup)
            if (!courseItems.length) return

            const courseSelections = this.draftCourseSelectionOverrides()

            courseItems.forEach((course) => {
                const selectionKey = this.courseSelectionKey(course, courseGroup)
                if (!selectionKey) return
                if (selected && this.courseItemUnavailable(course, courseGroup)) return
                const selectionKeys = this.courseSelectionKeys(course, courseGroup)

                if (selected) {
                    if (this.courseSelectionWouldExceedLimit(course, courseGroup, courseSelections)) return

                    if (this.courseItemDefaultSelected(course, courseGroup)) {
                        selectionKeys.forEach((courseSelectionKey) => {
                            delete courseSelections[courseSelectionKey]
                        })
                    } else {
                        selectionKeys.forEach((courseSelectionKey) => {
                            delete courseSelections[courseSelectionKey]
                        })
                        courseSelections[selectionKey] = true
                    }
                } else {
                    if (this.courseItemDefaultSelected(course, courseGroup)) {
                        selectionKeys.forEach((courseSelectionKey) => {
                            courseSelections[courseSelectionKey] = false
                        })
                    } else {
                        selectionKeys.forEach((courseSelectionKey) => {
                            delete courseSelections[courseSelectionKey]
                        })
                    }
                }
            })

            this.setDraftCourseSelections(courseSelections)
        },
        toggleCourseSelectionCardItem(courseItem) {
            if (!courseItem?.course || !courseItem?.courseGroup) return

            this.toggleCourseItem(courseItem.course, courseItem.courseGroup)
        },
        toggleCourseItem(course, courseGroup) {
            const selectionKey = this.courseSelectionKey(course, courseGroup)
            if (!selectionKey) return
            if (this.courseItemUnavailable(course, courseGroup)) return

            const courseSelections = this.draftCourseSelectionOverrides()
            const selectionKeys = this.courseSelectionKeys(course, courseGroup)

            if (this.courseItemSelected(course, courseGroup)) {
                if (this.courseItemDefaultSelected(course, courseGroup)) {
                    selectionKeys.forEach((courseSelectionKey) => {
                        courseSelections[courseSelectionKey] = false
                    })
                } else {
                    selectionKeys.forEach((courseSelectionKey) => {
                        delete courseSelections[courseSelectionKey]
                    })
                }
            } else {
                if (this.courseSelectionWouldExceedLimit(course, courseGroup, courseSelections)) return

                if (this.courseItemDefaultSelected(course, courseGroup)) {
                    selectionKeys.forEach((courseSelectionKey) => {
                        delete courseSelections[courseSelectionKey]
                    })
                } else {
                    selectionKeys.forEach((courseSelectionKey) => {
                        delete courseSelections[courseSelectionKey]
                    })
                    courseSelections[selectionKey] = true
                }
            }

            this.setDraftCourseSelections(courseSelections)
        },
        courseItemSelectionDisabled(course, courseGroup) {
            if (this.courseItemUnavailable(course, courseGroup)) return true
            if (this.courseItemSelected(course, courseGroup)) return false

            return this.courseSelectionWouldExceedLimit(course, courseGroup, this.courseSelectionOverrides)
        },
        courseItemSelectionDisabledLabel(course, courseGroup) {
            if (this.courseItemUnavailable(course, courseGroup)) {
                if (this.courseItemUnavailableReason(course, courseGroup) === 'prerequisite') {
                    return 'Voraussetzung nicht erfüllt'
                }

                return 'Kein angebotenes Modul vorhanden'
            }

            return this.courseItemSelectionDisabled(course, courseGroup)
                ? 'Maximum von 10 Modulen oder 30 Stunden erreicht'
                : undefined
        },
        courseItemUnavailable(course, courseGroup) {
            if (this.courseItemUnavailableReason(course, courseGroup)) return true

            return false
        },
        courseItemUnavailableReason(course, courseGroup) {
            if (this.courseMissingCompletedPrerequisite(course, courseGroup)) return 'prerequisite'
            if (!['completed', 'missing', 'semester', 'planned'].includes(courseGroup)) return false
            if (!this.courseOfferAvailabilityKnown()) return false

            return this.offeredCourseItemsForSelectedCourse(course).length === 0 ? 'offer' : false
        },
        courseOfferAvailabilityKnown() {
            return this.courseGroupsLoaded
                || this.courseGroupsError
                || (Array.isArray(this.courseGroups) && this.courseGroups.length > 0)
        },
        courseSelectionWouldExceedLimit(course, courseGroup, courseSelections = {}) {
            if (!['completed', 'missing', 'semester', 'planned'].includes(courseGroup)) return false
            if (this.courseItemUnavailable(course, courseGroup)) return false
            if (this.courseSelectedBySelections(course, courseGroup, courseSelections)) return false

            const selectedCourseItems = this.courseLimitSelectedCourseItems(courseSelections)
            const selectedCourseCount = selectedCourseItems.length
            const selectedCourseHours = selectedCourseItems
                .reduce((hours, courseItem) => hours + this.courseHoursNumber(courseItem.course), 0)

            return selectedCourseCount + 1 > 10
                || selectedCourseHours + this.courseHoursNumber(course) > 30
        },
        courseSelectionKey(course, courseGroup) {
            const courseKey = this.courseSelectionCourseKey(course)

            return courseKey ? `${courseGroup}:${courseKey}` : ''
        },
        courseSelectionCourseKey(course) {
            const explicitCourseCode = this.normalizedCourseCode(course?.code || course?.subject || '')

            return (explicitCourseCode ? this.courseCodeAliases({ code: explicitCourseCode })[0] || explicitCourseCode : '')
                || this.courseSelectionCourseCodeFromValue(course?.label)
                || this.courseSelectionCourseCodeFromValue(course?.name)
                || String(course?.key || course?.label || '').trim()
        },
        courseSelectionCourseCodeFromValue(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            if (this.courseSelectionCourseCodeToken(normalizedValue)) {
                return this.courseCodeAliases({ code: normalizedValue })[0] || normalizedValue
            }

            const token = this.courseCodeTokensFromValue(value)
                .map((courseCode) => this.normalizedCourseCode(courseCode))
                .find((courseCode) => this.courseSelectionCourseCodeToken(courseCode) && this.courseCodeModuleParts(courseCode).module)

            return token ? this.courseCodeAliases({ code: token })[0] || token : ''
        },
        courseSelectionCourseCodeToken(value) {
            return /^[A-Z]{1,5}[0-9]*[A-Z]*$/u.test(this.normalizedCourseCode(value))
        },
        courseSelectionExplicitValue(course, courseGroup, courseSelections = {}) {
            const normalizedSelections = this.normalizedCourseSelections(courseSelections)
            const selectionKeys = this.courseSelectionKeys(course, courseGroup)
            const canonicalSelectionKey = this.courseSelectionKey(course, courseGroup)
            const orderedSelectionKeys = this.uniqueValues([
                canonicalSelectionKey,
                ...selectionKeys,
            ].filter(Boolean))

            const explicitSelectionKey = orderedSelectionKeys
                .find((selectionKey) => normalizedSelections[selectionKey] === true || normalizedSelections[selectionKey] === false)

            return explicitSelectionKey ? normalizedSelections[explicitSelectionKey] : null
        },
        courseSelectionKeys(course, courseGroup) {
            const courseKeys = [
                this.courseSelectionKey(course, courseGroup),
                String(course?.selectionKey || '').trim(),
                String(course?.key || '').trim() ? `${courseGroup}:${String(course.key).trim()}` : '',
                ...this.courseCodeAliases(course).map((courseCode) => `${courseGroup}:${courseCode}`),
            ]

            return this.uniqueValues(courseKeys.filter(Boolean))
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
            return this.storedTimetableState || null
        },
        storedTimetableStatesEqual(firstState, secondState) {
            return this.storedTimetableStateSignature(firstState) === this.storedTimetableStateSignature(secondState)
        },
        storedTimetableStateSignature(state) {
            return JSON.stringify(this.stableStoredTimetableStateValue(state ?? null))
        },
        stableStoredTimetableStateValue(value) {
            if (Array.isArray(value)) {
                return value.map((item) => this.stableStoredTimetableStateValue(item))
            }

            if (value && typeof value === 'object') {
                return Object.fromEntries(
                    Object.keys(value)
                        .sort()
                        .map((key) => [key, this.stableStoredTimetableStateValue(value[key])])
                )
            }

            return value
        },
        replaceCourseGroups(courseGroups) {
            this.courseGroups = Array.isArray(courseGroups) ? courseGroups : []
            this.courseGroupsRevision++
            this.resetOfferedCourseItemsCache()
        },
        resetOfferedCourseItemsCache() {
            this.offeredCourseItemsCache = {}
            this.offeredCourseItemsCacheCourseGroups = this.courseGroups
        },
        saveStoredTimetableState(state) {
            this.storedTimetableState = state
            this.storageRevision++
            this.pendingStoredTimetableState = state
            this.resetDraftCourseSelections()

            const requestId = this.storedTimetableStateSaveRequestId + 1
            this.storedTimetableStateSaveRequestId = requestId

            this.clearStoredTimetableStateSaveTimer()
            this.storedTimetableStateSaveTimer = globalThis.setTimeout(() => {
                if (requestId !== this.storedTimetableStateSaveRequestId) return

                this.storedTimetableStateSaveTimer = null
                this.pendingStoredTimetableState = null
                this.persistStoredTimetableState(state, requestId)
            }, 180)
        },
        persistStoredTimetableState(state, requestId = this.storedTimetableStateSaveRequestId) {
            this.storedTimetableStateSaving = true

            axios.put('/api/admin/students-timetables/timetable-v2-state', { state })
                .then((response) => {
                    if (requestId !== this.storedTimetableStateSaveRequestId) return

                    const storedState = response.data?.data?.state
                    if (
                        storedState
                        && typeof storedState === 'object'
                        && !Array.isArray(storedState)
                        && this.storedTimetableStatesEqual(storedState, state)
                    ) {
                        this.storedTimetableState = storedState
                        this.storageRevision++
                    }
                })
                .catch(() => {})
                .finally(() => {
                    if (requestId === this.storedTimetableStateSaveRequestId) {
                        this.storedTimetableStateSaving = false
                    }
                })
        },
        clearStoredTimetableStateSaveTimer() {
            if (!this.storedTimetableStateSaveTimer) return

            globalThis.clearTimeout(this.storedTimetableStateSaveTimer)
            this.storedTimetableStateSaveTimer = null
        },
        flushPendingStoredTimetableStateSave() {
            if (!this.pendingStoredTimetableState) {
                this.clearStoredTimetableStateSaveTimer()

                return
            }

            const state = this.pendingStoredTimetableState
            const requestId = this.storedTimetableStateSaveRequestId
            this.pendingStoredTimetableState = null
            this.clearStoredTimetableStateSaveTimer()
            this.persistStoredTimetableState(state, requestId)
        },
        async loadStoredTimetableState() {
            this.storedTimetableStateLoading = true

            try {
                const response = await axios.get('/api/admin/students-timetables/timetable-v2-state')
                const storedState = response.data?.data?.state

                this.storedTimetableState = storedState && typeof storedState === 'object' && !Array.isArray(storedState)
                    ? storedState
                    : null
            } catch {
                this.storedTimetableState = null
            } finally {
                this.storedTimetableStateLoading = false
                this.storageRevision++
            }
        },
        syncStoredTimetableOptions() {
            this.applyTimetableV2OptionState(this.storedTimetableV2Options)
        },
        applyTimetableV2OptionState(options = {}) {
            this.timetableNoSaturdaySelected = options.noSaturday === true
            this.timetableNoSaturdayDraftSelected = this.timetableNoSaturdaySelected
            this.timetableMaxFreeDaysSelected = options.maxFreeDays === true
            this.timetableMaxFreeDaysDraftSelected = this.timetableMaxFreeDaysSelected
            this.timetableNoDistanceLearningSelected = options.noDistanceLearning === true
            this.timetableNoDistanceLearningDraftSelected = this.timetableNoDistanceLearningSelected
            this.timetableStartsFromPeriod10Selected = options.startsFromPeriod10 === true
            this.timetableStartsFromPeriod10DraftSelected = this.timetableStartsFromPeriod10Selected
        },
        timetableV2OptionsForSaving() {
            return {
                maxFreeDays: this.timetableMaxFreeDaysSelected === true,
                noDistanceLearning: this.timetableNoDistanceLearningSelected === true,
                noSaturday: this.timetableNoSaturdaySelected === true,
                startsFromPeriod10: this.timetableStartsFromPeriod10Selected === true,
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
                    noDistanceLearning: false,
                    noSaturday: false,
                    startsFromPeriod10: false,
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
            const timetableV2Selection = this.adoptedTimetableVisible && this.adoptedTimetableSelectionSnapshot
                ? this.adoptedTimetableSelectionSnapshot
                : this.storedTimetableV2Selection

            if (item?.key === 'semester') {
                return this.courseReviewSemesterSelectionValue(item, timetableV2Selection)
            }

            if (item?.value && item.value !== '--') return item.value

            const value = timetableV2Selection?.[item.key]
            if (String(value || '').trim() === '') return ''

            return this.selectedOptionTitle(item.options || [], value)
        },
        courseReviewSemesterSelectionValue(item, timetableV2Selection = this.storedTimetableV2Selection) {
            const value = item?.value && item.value !== '--'
                ? item.value
                : timetableV2Selection?.semester
            const semester = this.semesterValueFromLabel(value)

            if (!semester) return this.knownSelectionValue(value)

            return this.selectedOptionTitle(this.semesterOptions(), semester)
        },
        selectionValueIsKnown(value) {
            return String(value || '').trim() !== ''
        },
        completedCourseItemsFromApi(courses) {
            return this.courseHistoryAttemptGroups(courses)
                .filter((group) => group.attempts.some((attempt) => this.completedCourseGradeIsAccepted(attempt.meta)))
                .map((group) => {
                    const completedAttempt = group.attempts.find((attempt) => this.completedCourseGradeIsAccepted(attempt.meta))
                        || group.attempts[0]
                    const meta = this.courseHistoryAttemptGradeTrend(group.attempts)

                    return {
                        ...completedAttempt,
                        key: `completed-${group.key}-${meta}`,
                        meta,
                    }
                })
        },
        missingCourseItemsFromApi(courses) {
            return this.courseHistoryAttemptGroups(courses)
                .filter((group) => !group.attempts.some((attempt) => this.completedCourseGradeIsAccepted(attempt.meta)))
                .map((group) => {
                    const missingAttempt = group.attempts.find((attempt) => this.missingCourseGradeIsAccepted(attempt.meta))
                        || group.attempts[0]
                    const meta = this.courseHistoryAttemptGradeTrend(group.attempts)

                    return {
                        ...missingAttempt,
                        key: `missing-${group.key}-${meta}`,
                        meta,
                    }
                })
                .filter((course) => this.missingCourseGradeIsAccepted(course.meta))
        },
        courseHistoryAttemptGroups(courses) {
            const groups = new Map()

            this.normalizedCourseHistoryAttemptItems(courses).forEach((attempt) => {
                const key = this.courseHistoryAttemptGroupKey(attempt)
                if (!key) return

                if (!groups.has(key)) {
                    groups.set(key, {
                        key,
                        attempts: [],
                    })
                }

                groups.get(key).attempts.push(attempt)
            })

            return [...groups.values()]
        },
        normalizedCourseHistoryAttemptItems(courses) {
            return (Array.isArray(courses) ? courses : [])
                .map((course, index) => {
                    const code = String(course?.code || course?.subject || '').trim()
                    const label = String(course?.label || code || course?.name || '').trim()
                    const grade = String(course?.meta || course?.grade || '').trim()

                    return {
                        key: String(course?.key || `attempt-${label || index}-${grade || index}`).trim(),
                        code,
                        hours: Number(course?.hours || 0),
                        hours_per_week: Number(course?.hours_per_week || 0),
                        label,
                        meta: grade,
                    }
                })
                .filter((course) => course.label && course.meta)
        },
        courseHistoryAttemptGroupKey(course) {
            return this.normalizedCourseCode(course?.code || course?.label || '')
        },
        courseHistoryAttemptGradeTrend(attempts) {
            const grades = (Array.isArray(attempts) ? attempts : [])
                .map((attempt) => String(attempt?.meta || '').trim())
                .filter(Boolean)
            const failedGrades = grades.filter((grade) => this.missingCourseGradeIsAccepted(grade))
            const completedGrades = grades.filter((grade) => this.completedCourseGradeIsAccepted(grade))
            const otherGrades = grades.filter((grade) => !this.missingCourseGradeIsAccepted(grade) && !this.completedCourseGradeIsAccepted(grade))

            return this.uniqueValues([
                ...failedGrades,
                ...completedGrades,
                ...otherGrades,
            ]).join('|')
        },
        overviewStudentCourseHistoryFromSummary(overviewSummary) {
            const automaticCourseSections = Array.isArray(overviewSummary?.automatic_course_selection?.sections) ? overviewSummary.automatic_course_selection.sections : []
            const automaticMissingCourses = automaticCourseSections.find((section) => section?.key === 'missing')?.items
            const automaticPlannedCourses = automaticCourseSections.find((section) => section?.key === 'proposed')?.items
            const completedCourses = Array.isArray(overviewSummary?.completed_courses) ? overviewSummary.completed_courses : []
            const missingCourses = Array.isArray(automaticMissingCourses)
                ? automaticMissingCourses
                : (Array.isArray(overviewSummary?.missing_courses) ? overviewSummary.missing_courses : [])
            const courseHistoryCourses = [
                ...completedCourses,
                ...missingCourses,
            ]

            return {
                completed: this.completedCourseItemsFromApi(courseHistoryCourses),
                failed: this.missingCourseItemsFromApi(courseHistoryCourses),
                missing: this.normalizedOverviewCourseItems(missingCourses),
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
                    const hours = this.courseHoursNumber(course) || this.subjectRowHoursForCourseCode(code)

                    return {
                        key: String(course?.key || `completed-${label || index}-${grade || index}`).trim(),
                        code,
                        hours,
                        hoursMeta: hours ? `${this.formatHours(hours)} Std.` : '',
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
                    const hours = this.courseHoursNumber(course) || this.subjectRowHoursForCourseCode(code)

                    return {
                        key: String(course?.key || `missing-${label || index}-${grade || index}`).trim(),
                        code,
                        hours,
                        hoursMeta: hours ? `${this.formatHours(hours)} Std.` : '',
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
        courseKnownSemesterNumber(course) {
            const semester = Number(course?.semester || course?.subject_semester || course?.semester_number || 0)
            if (Number.isFinite(semester) && semester > 0) return semester

            return this.subjectRowSemesterForCourse(course)
        },
        courseAfterSelectedStudentSemester(course) {
            const selectedSemester = this.selectedStudentDefaultSemester()
            const courseSemester = this.courseKnownSemesterNumber(course)

            return Number.isFinite(selectedSemester)
                && Number.isFinite(courseSemester)
                && courseSemester > selectedSemester
        },
        courseDueBySelectedStudentSemester(course) {
            const selectedSemester = this.selectedStudentDefaultSemester()
            const courseSemester = this.courseKnownSemesterNumber(course)

            return Number.isFinite(selectedSemester)
                && Number.isFinite(courseSemester)
                && courseSemester > 0
                && courseSemester <= selectedSemester
        },
        subjectRowSemesterForCourse(course) {
            const courseCodes = this.courseCodeSet([course])
            if (!courseCodes.size) return null

            const semesters = (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .filter((subject) => subject?.is_active !== false)
                .filter((subject) => this.subjectMatchesSelectedBranch(subject))
                .filter((subject) => this.subjectMatchesSelectedChoices(subject))
                .flatMap((subject) => this.selectedCoursesFromSubject(subject))
                .filter((subjectCourse) => this.courseCodeSetContainsCourse(courseCodes, subjectCourse))
                .map((subjectCourse) => Number(subjectCourse?.semester || 0))
                .filter((semester) => Number.isFinite(semester) && semester > 0)

            return semesters.length ? Math.min(...semesters) : null
        },
        courseModuleNumber(course) {
            const module = Number(this.courseCodeModuleParts(course?.code || course?.label || course?.name || '').module || 0)
            if (Number.isFinite(module) && module > 0) return module

            return 0
        },
        courseBaseCode(course) {
            const parts = this.courseCodeModuleParts(course?.code || course?.label || course?.name || '')
            if (parts.module) return parts.base

            return parts.base
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
                countLabel: `${this.formatNumber(courseItems.length)} ${courseItems.length === 1 ? 'Modul' : 'Module'}`,
                hoursLabel: `${this.formatHours(hours)} Std.`,
            }
        },
        completedCourseItemMeta(course) {
            const hours = this.courseHoursNumber(course)
                || this.subjectRowHoursForCourseCode(course?.code || course?.label || course?.name)

            return hours ? `${this.formatHours(hours)} Std.` : ''
        },
        courseHoursNumber(course) {
            const numericHours = Number(course?.hours ?? course?.hours_per_week ?? 0)

            if (Number.isFinite(numericHours) && numericHours > 0) return numericHours

            const hoursMatch = String(course?.meta || course?.hours_label || '').match(/(\d+(?:[,.]\d+)?)\s*Std/iu)

            return hoursMatch ? Number(hoursMatch[1].replace(',', '.')) : 0
        },
        subjectRowHoursForCourseCode(code) {
            const { base, module } = this.courseCodeModuleParts(code)
            const religionCourseBases = new Set([
                'R',
                'ET',
                ...this.religionOptions().map((option) => this.normalizedCourseCode(option.value)),
            ])
            if (module && religionCourseBases.has(base)) {
                const religionSubject = (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                    .filter((subject) => subject?.is_active !== false)
                    .find((subject) => this.isReligionSubject(subject) && this.subjectModuleNumber(subject) === module)
                const religionHours = Number(religionSubject?.hours_per_week || 0)

                if (Number.isFinite(religionHours) && religionHours > 0) return religionHours
            }

            const subjectRowCourse = this.subjectRowCourseForCourseCode(code)
            const directHours = Number(subjectRowCourse?.hours || 0)

            return Number.isFinite(directHours) && directHours > 0 ? directHours : 0
        },
        subjectRowCourseForCourseCode(code) {
            const normalizedCode = this.normalizedCourseCode(code)
            if (!normalizedCode) return null

            return (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .filter((subject) => subject?.is_active !== false)
                .flatMap((subject) => this.subjectCourseVariants(subject))
                .flatMap((subject) => {
                    const selectedCourse = this.selectedCourseFromSubject(subject)
                    const selectedCourseCode = this.normalizedCourseCode(selectedCourse.code || subject?.json_code || subject?.json_subject || subject?.name)
                    const sourceCourseCode = this.normalizedCourseCode(subject?.json_code || selectedCourse.code || subject?.json_subject || subject?.name)
                    const hours = Number(selectedCourse.hours || subject?.hours_per_week || 0)

                    return this.uniqueValues([selectedCourseCode, sourceCourseCode])
                        .filter(Boolean)
                        .map((subjectCode) => ({
                            code: subjectCode,
                            hours,
                            key: selectedCourse.key,
                            label: subjectCode === selectedCourseCode
                                ? selectedCourse.name || selectedCourse.code
                                : subjectCode,
                        }))
                })
                .find((subject) => subject.code === normalizedCode)
                || null
        },
        noStudentPlannedCourses() {
            return this.coursesForSemester(this.noStudentSelectedSemester)
        },
        selectedStudentDefaultSemesterCourses() {
            const semester = this.selectedStudentDefaultSemester()

            return semester ? this.coursesForSemester(semester) : []
        },
        selectedStudentPreviousSemesterCourses() {
            const semester = this.selectedStudentDefaultSemester()

            return semester ? this.coursesBeforeSemester(semester) : []
        },
        selectedStudentDefaultSemester() {
            const contextSemester = this.semesterValueFromLabel(this.storedTimetableStudentContext?.student?.semesterLabel)
            const selectedSemester = Number(this.storedTimetableV2Selection?.semester || 0)

            if (contextSemester) return contextSemester
            if (Number.isFinite(selectedSemester) && selectedSemester > 0) return selectedSemester

            return null
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
        coursesBeforeSemester(semester) {
            return (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .map((subject) => Number(subject?.semester))
                .filter((subjectSemester) => Number.isFinite(subjectSemester) && subjectSemester > 0 && subjectSemester < Number(semester))
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

            return !languageCode || this.selectionCourseAliases(selectedLanguage).includes(languageCode)
        },
        languageSubjectCode(subject) {
            const rawBaseKey = this.subjectBaseKey(subject)
            const baseKey = this.normalizedCourseCode(rawBaseKey)
            if (['L', 'F', 'S', 'SPA'].includes(baseKey)) return this.courseDisplayLabel(baseKey)
            if (rawBaseKey !== 'L/F/S') return ''

            const jsonCodeParts = this.courseCodeAliasParts(this.courseCodeWithoutModule(subject?.json_code))
                .map((value) => this.normalizedCourseCode(value))

            return jsonCodeParts.length === 1 && ['L', 'F', 'S', 'SPA'].includes(jsonCodeParts[0])
                ? this.courseDisplayLabel(jsonCodeParts[0])
                : ''
        },
        selectedCoursesFromSubject(subject) {
            return this.subjectCourseVariants(subject)
                .map((courseSubject) => this.selectedCourseFromSubject(courseSubject))
                .filter((course) => this.normalizedCourseCode(course.code))
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
            const name = this.selectedCourseName(subject)
            const hours = Number(subject?.hours_per_week || 0)
            const hoursMeta = hours ? `${this.formatHours(hours)} Std.` : ''

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
                label: code || name,
                name,
                hours,
                hoursMeta,
                meta: hoursMeta,
                semester: Number(subject?.semester || 0) || null,
            }
        },
        selectedCourseCode(subject) {
            if (this.isReligionSubject(subject)) return `${this.effectiveTimetableV2Selection.religion}${this.subjectModuleNumber(subject)}`
            if (this.isLanguageSubject(subject)) return this.courseDisplayLabel(`${this.effectiveTimetableV2Selection.language}${this.subjectModuleNumber(subject)}`)

            return this.alternativeDisplay(subject?.json_code || subject?.json_subject || subject?.name)
        },
        selectedCourseName(subject) {
            const moduleNumber = this.subjectModuleNumber(subject)

            if (this.isReligionSubject(subject)) {
                return this.courseDisplayLabel(`${this.effectiveTimetableV2Selection.religion}${moduleNumber}`)
            }

            if (this.isLanguageSubject(subject)) {
                return this.courseDisplayLabel(`${this.effectiveTimetableV2Selection.language}${moduleNumber}`)
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
                .some((parts) => this.courseModulePrerequisiteMet(
                    parts,
                    this.courseCodeSet(this.storedCompletedCourseItems),
                    plannedCourseCodes,
                ))
        },
        courseModuleParts(course) {
            return this.courseCodeAliasParts(course?.code)
                .map((courseCode) => this.courseCodeModuleParts(courseCode))
                .filter((parts) => parts.module)
                .filter((parts, index, allParts) =>
                    allParts.findIndex((candidate) => candidate.base === parts.base && candidate.module === parts.module) === index)
        },
        courseModulePrerequisiteMet(parts, completedCourseCodes = new Set(), plannedCourseCodes = new Set()) {
            const moduleNumber = Number(parts.module)
            if (!Number.isInteger(moduleNumber)) return false
            if (moduleNumber === 1) return true

            const baseAliases = this.courseBaseAliases(parts.base)
            if (moduleNumber === 2) {
                return baseAliases.some((baseAlias) =>
                    completedCourseCodes.has(`${baseAlias}1`) || plannedCourseCodes.has(`${baseAlias}1`))
            }

            const prerequisiteModuleNumber = moduleNumber - 2

            return baseAliases.some((baseAlias) => completedCourseCodes.has(`${baseAlias}${prerequisiteModuleNumber}`))
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
            const courseCodeValue = String(course?.code || course?.subject || course?.label || course?.name || '').trim()
            const compactCourseCodeAliases = new Set(this.compactCourseCodeAliasesFromValue(courseCodeValue))

            return this.courseCodeAliasParts(courseCodeValue)
                .map((courseCode) => this.normalizedCourseCode(courseCode))
                .flatMap((courseCode) => {
                    if (compactCourseCodeAliases.has(courseCode)) {
                        return [courseCode]
                    }

                    const { base, module } = this.courseCodeModuleParts(courseCode)

                    return this.courseBaseAliases(base).map((baseAlias) => `${baseAlias}${module}`)
                })
                .filter(Boolean)
                .filter((courseCode, index, courseCodes) => courseCodes.indexOf(courseCode) === index)
        },
        courseBaseAliases(base) {
            const normalizedBase = this.normalizedCourseCode(base)
            const mappedAliases = {
                ET: ['ETH'],
                ETH: ['ET'],
                GPB: ['GS'],
                GS: ['GPB'],
                GW: ['GWB'],
                GWB: ['GW'],
                LPT: ['LET'],
                LET: ['LPT'],
                ME: ['MU'],
                MU: ['ME'],
                R: ['RK', 'REV', 'RIS', 'ROR'],
                REV: ['R', 'EV', 'EVANG'],
                RIS: ['R', 'ISLAM'],
                RK: ['R', 'RKATH'],
                RKATH: ['R', 'RK'],
                ROR: ['R', 'ORTH'],
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
            const normalizedGrades = String(grade || '')
                .split('|')
                .map((gradePart) => gradePart.trim().toLocaleUpperCase('de-AT'))
                .filter(Boolean)

            return normalizedGrades.some((normalizedGrade) => !['5', 'N'].includes(normalizedGrade))
        },
        missingCourseGradeIsAccepted(grade) {
            const normalizedGrades = String(grade || '')
                .split('|')
                .map((gradePart) => gradePart.trim().toLocaleUpperCase('de-AT'))
                .filter(Boolean)

            return normalizedGrades.length > 0
                && normalizedGrades.every((normalizedGrade) => ['5', 'N'].includes(normalizedGrade))
        },
        timetableV2SelectionWithCourseDefaults(selection, courseHistory) {
            const courseDefaults = this.selectedStudentCourseHistoryDefaults(courseHistory)

            return Object.entries(courseDefaults).reduce(
                (nextSelection, [key, value]) => {
                    if (key === 'religion' && value === 'ETH') {
                        nextSelection[key] = value

                        return nextSelection
                    }

                    if (String(nextSelection[key] ?? '').trim() === '') {
                        nextSelection[key] = value
                    }

                    return nextSelection
                },
                this.normalizedTimetableV2Selection(selection)
            )
        },
        normalizedTimetableV2Selection(selection) {
            const nextSelection = { ...(selection || {}) }
            const optionGroups = {
                artsSubject: this.artsSubjectOptions(),
                language: this.languageOptions(),
                religion: this.religionOptions(),
            }

            Object.entries(optionGroups).forEach(([key, options]) => {
                const canonicalValue = this.canonicalSelectionOptionValue(options, nextSelection[key])

                if (canonicalValue) {
                    nextSelection[key] = canonicalValue
                }
            })

            return nextSelection
        },
        canonicalSelectionOptionValue(options, value) {
            const normalizedValue = this.normalizedCourseCode(value)

            if (!normalizedValue) return ''

            const normalizedBase = this.courseCodeWithoutModule(normalizedValue)
            const matchingOption = (Array.isArray(options) ? options : []).find((option) =>
                this.selectionCourseAliases(option?.value)
                    .map((alias) => this.courseCodeWithoutModule(alias))
                    .includes(normalizedBase)
            )

            return matchingOption?.value || ''
        },
        studentOverviewSelectionPayload() {
            return this.studentOverviewSelectionPayloadForSelection(this.storedTimetableV2Selection || {})
        },
        studentOverviewSelectionPayloadForSelection(selection) {
            const selectionWithDefaults = this.timetableV2SelectionWithCourseDefaults(
                selection,
                this.storedTimetableStudentContext?.courses || {},
            )
            const semester = this.semesterValueFromLabel(this.storedTimetableStudentContext?.student?.semesterLabel)
                || selectionWithDefaults.semester

            return Object.fromEntries(
                [
                    ['semester', semester],
                    ['religion', selectionWithDefaults.religion],
                    ['language', selectionWithDefaults.language],
                    ['branch', selectionWithDefaults.branch],
                    ['artsSubject', selectionWithDefaults.artsSubject],
                ].filter(([, value]) => String(value || '').trim() !== '')
            )
        },
        studentOverviewRequestKey(studentCode, selection) {
            return JSON.stringify({
                studentCode: this.normalizedStudentCode(studentCode),
                selection,
            })
        },
        selectedStudentCourseHistoryDefaults(courseHistory) {
            const completedCourses = Array.isArray(courseHistory)
                ? courseHistory
                : courseHistory?.completed
            const completedCourseCodes = this.studentCourseCodeSet(completedCourses)
            const visitedCourseCodes = this.studentVisitedCourseCodeSet(courseHistory)
            const completedEthicsDefault = this.inferredSelectionOptionFromCourseCodes([{ value: 'ETH' }], completedCourseCodes)
            const completedReligionDefault = this.inferredSelectionOptionFromCourseCodes(this.religionOptions(), completedCourseCodes)
            const studentReligionDefault = this.selectedStudentReligionDefault()

            return Object.fromEntries(
                [
                    ['religion', completedEthicsDefault || studentReligionDefault || completedReligionDefault],
                    ['language', this.inferredSelectionOptionFromCourseCodes(this.languageOptions(), completedCourseCodes)],
                    ['branch', this.inferredBranchFromCourseCodes(visitedCourseCodes)],
                    ['artsSubject', this.inferredSelectionOptionFromCourseCodes(this.artsSubjectOptions(), completedCourseCodes)],
                ].filter(([, value]) => Boolean(value))
            )
        },
        selectedStudentReligionDefault() {
            const religion = this.storedTimetableStudentReligion()

            if (!religion || this.studentReligionMatchesNoConfession(religion)) return ''

            return this.studentReligionOptionValue(religion) || ''
        },
        studentCourseCodeSet(courses) {
            return new Set(
                (Array.isArray(courses) ? courses : [])
                    .flatMap((course) => this.courseCodesFromCourse(course))
                    .map((courseCode) => this.normalizedCourseCode(courseCode))
                    .filter(Boolean)
            )
        },
        studentVisitedCourseCodeSet(courseHistory) {
            if (Array.isArray(courseHistory)) return this.studentCourseCodeSet(courseHistory)

            return this.studentCourseCodeSet([
                ...(courseHistory?.completed || []),
                ...(courseHistory?.failed || []),
            ])
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
                    aliases: ['INF'],
                    minimumModule: 2,
                },
                {
                    value: 'wirtschaftskundlich',
                    aliases: ['OKO', 'OEKO', 'OEK', 'WIKU', 'BWL', 'RW', 'WR'],
                },
                {
                    value: 'gymnasial',
                    aliases: ['L', 'F', 'S'],
                    minimumModule: 6,
                },
            ]
            const matchingBranch = branchAliases
                .map((branch, branchIndex) => ({
                    branch,
                    branchIndex,
                    match: this.bestSelectionAliasesCourseCodeMatch(branch.aliases, courseCodes),
                }))
                .filter(({ branch, match }) => match && Number(match.module || 0) >= Number(branch.minimumModule || 0))
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
            const courseCodeParts = String(value || '')
                .split('/')
                .map((part) => part.trim())
                .filter(Boolean)
            const inheritedModule = [...courseCodeParts]
                .reverse()
                .map((part) => this.courseCodeModuleParts(part).module)
                .find(Boolean) || ''

            return this.uniqueValues(courseCodeParts
                .flatMap((courseCode) => {
                    const { base, module } = this.courseCodeModuleParts(courseCode)
                    const compactBaseAliases = this.compactCourseCodeBaseAliases(base)
                    const resolvedModule = module || inheritedModule

                    if (compactBaseAliases.length) {
                        return compactBaseAliases.map((baseAlias) => `${baseAlias}${resolvedModule}`)
                    }

                    return [`${base}${resolvedModule}`]
                })
                .filter(Boolean))
        },
        compactCourseCodeBaseAliases(base) {
            const normalizedBase = this.normalizedCourseCode(base)
            const mappedAliases = {
                MEMU: ['ME'],
            }

            return mappedAliases[normalizedBase] || []
        },
        compactCourseCodeAliasesFromValue(value) {
            const courseCodeParts = String(value || '')
                .split('/')
                .map((part) => part.trim())
                .filter(Boolean)
            const inheritedModule = [...courseCodeParts]
                .reverse()
                .map((part) => this.courseCodeModuleParts(part).module)
                .find(Boolean) || ''

            return this.uniqueValues(courseCodeParts
                .flatMap((courseCode) => {
                    const { base, module } = this.courseCodeModuleParts(courseCode)
                    const compactBaseAliases = this.compactCourseCodeBaseAliases(base)
                    const resolvedModule = module || inheritedModule

                    return compactBaseAliases.map((baseAlias) => `${baseAlias}${resolvedModule}`)
                })
                .filter(Boolean))
        },
        courseCodeWithoutModule(value) {
            return this.courseCodeModuleParts(value).base
        },
        courseCodeModuleParts(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const match = normalizedValue.match(/^([A-Z]+)([0-9]*)$/u)
                || normalizedValue.match(/^([A-Z]+)([0-9]+)[A-Z]+$/u)

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
                { title: 'Rev - Evangelische Religion', value: 'Rev' },
                { title: 'Ris - Islamische Religion', value: 'Ris' },
                { title: 'Rk - Katholische Religion', value: 'Rk' },
                { title: 'Ror - Orthodoxe Religion', value: 'Ror' },
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
        storedTimetableStudentReligionMeta() {
            const religion = this.storedTimetableStudentReligion()

            return religion ? `Religion: ${religion}` : null
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
                { title: 'SPA - Spanisch', value: 'SPA' },
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

            return [name, schoolClass, semester].filter(Boolean).join(' · ')
        },
        compareStudentsByName(firstStudent, secondStudent) {
            const firstStudentName = [
                firstStudent?.last_name,
                firstStudent?.first_name,
            ].map(value => String(value || '').trim()).join(' ')
            const secondStudentName = [
                secondStudent?.last_name,
                secondStudent?.first_name,
            ].map(value => String(value || '').trim()).join(' ')
            const nameComparison = firstStudentName.localeCompare(secondStudentName, 'de', {
                sensitivity: 'base',
                numeric: true,
            })

            if (nameComparison !== 0) return nameComparison

            return String(firstStudent?.class || '').localeCompare(
                String(secondStudent?.class || ''),
                'de',
                { sensitivity: 'base', numeric: true },
            )
        },
        studentEmail(student) {
            return String(student?.email || '').trim()
        },
        async copyStudentEmail(student) {
            const emailAddress = this.studentEmail(student)
            if (!emailAddress) return false

            const copied = await this.copyTextToClipboard(emailAddress)
            if (!copied) return false

            this.copiedStudentEmailCode = this.normalizedStudentCode(student?.student_code)
            this.clearCopyStudentEmailResetTimeout()
            this.copyStudentEmailResetTimeout = globalThis.setTimeout(() => {
                this.copiedStudentEmailCode = null
                this.copyStudentEmailResetTimeout = null
            }, 1800)

            if (typeof this.copyStudentEmailResetTimeout?.unref === 'function') {
                this.copyStudentEmailResetTimeout.unref()
            }

            return true
        },
        copyStoredTimetableStudentEmail() {
            return this.copyStudentEmail({
                student_code: this.storedTimetableStudentCode,
                email: this.storedTimetableStudentEmail,
            })
        },
        copyCourseReviewStudentEmail() {
            return this.copyStudentEmail({
                student_code: this.courseReviewStudentCode,
                email: this.courseReviewStudentEmail,
            })
        },
        clearCopyStudentEmailResetTimeout() {
            if (!this.copyStudentEmailResetTimeout) return

            globalThis.clearTimeout(this.copyStudentEmailResetTimeout)
            this.copyStudentEmailResetTimeout = null
        },
        async copyTextToClipboard(value) {
            const text = String(value || '').trim()
            if (!text) return false

            if (typeof navigator !== 'undefined' && navigator?.clipboard?.writeText) {
                try {
                    await navigator.clipboard.writeText(text)

                    return true
                } catch {
                    // Fall back to the textarea copy path below.
                }
            }

            if (typeof document === 'undefined') return false

            try {
                const textarea = document.createElement('textarea')
                textarea.value = text
                textarea.setAttribute('readonly', '')
                textarea.style.position = 'fixed'
                textarea.style.left = '-9999px'
                document.body.appendChild(textarea)
                textarea.select()
                textarea.setSelectionRange(0, text.length)
                const copied = document.execCommand('copy')
                document.body.removeChild(textarea)

                return copied
            } catch {
                return false
            }
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
.students-timetable-v2-page--actions-disabled :deep(button),
.students-timetable-v2-page--actions-disabled :deep(.v-btn),
.students-timetable-v2-page--actions-disabled :deep([role="button"]) {
    cursor: default !important;
    opacity: 0.5;
    pointer-events: none;
}

.students-timetable-v2-initial-loader {
    display: grid;
    place-items: center;
    min-height: 220px;
    gap: 10px;
    color: #075985;
    font-size: 0.9rem;
    font-weight: 800;
    line-height: 1.2;
}

.students-timetable-v2-initial-loader__bar {
    width: min(280px, 70vw);
}

.students-timetable-v2-loading-dots::after {
    display: inline-block;
    width: 1.5em;
    overflow: hidden;
    vertical-align: bottom;
    animation: students-timetable-v2-loading-dots 1.2s steps(4, end) infinite;
    content: "...";
}

@keyframes students-timetable-v2-loading-dots {
    0% {
        width: 0;
    }

    100% {
        width: 1.5em;
    }
}

.students-timetable-v2-card {
    display: flex;
    flex: 1 1 auto;
    width: 100%;
    height: 100%;
    flex-direction: column;
    border: 1px solid var(--schedule-border);
    background: #ffffff;
    box-shadow: none;
}

.students-timetable-v2-card :deep(.v-card-text) {
    flex: 1 1 auto;
}

.students-timetable-v2-calculation-card--final {
    border-color: rgba(22, 163, 74, 0.28);
    background: rgba(240, 253, 244, 0.96);
    box-shadow: 0 10px 22px rgba(22, 163, 74, 0.12);
}

.students-timetable-v2-card-column {
    display: flex !important;
    align-self: stretch;
    min-width: 0;
}

.students-timetable-v2-student-card-stack {
    flex-direction: column;
    gap: 10px;
}

.ttv2-strip {
    display: grid;
    gap: 18px;
    width: 100%;
    flex: 1 1 100%;
    border: 0;
    border-radius: 12px;
    padding: 0;
    background: #ffffff;
    box-shadow: none;
}

.students-timetable-v2-stepper {
    width: 100%;
    border: 0;
    border-radius: 0;
    background: #ffffff !important;
    box-shadow: none;
    overflow: hidden;
}

.students-timetable-v2-stepper :deep(.v-stepper-header) {
    min-height: 60px;
    box-shadow: none;
}

.students-timetable-v2-stepper :deep(.v-stepper-item) {
    position: relative;
    min-height: 60px;
    padding: 8px 12px;
    border-radius: 0;
    color: var(--schedule-muted);
    transition:
        background-color 0.18s ease,
        box-shadow 0.18s ease,
        color 0.18s ease;
}

.students-timetable-v2-stepper :deep(.v-stepper-item--selected) {
    background: transparent;
    color: var(--schedule-heading);
    box-shadow: none;
}

.students-timetable-v2-stepper :deep(.v-stepper-item--complete:not(.v-stepper-item--selected)) {
    color: var(--schedule-accent);
}

.students-timetable-v2-stepper :deep(.v-stepper-item__avatar.v-avatar) {
    width: 28px;
    height: 28px;
    background: #e0e3eb !important;
    color: var(--schedule-muted) !important;
    font-size: 0.8rem;
    font-weight: 700;
}

.students-timetable-v2-stepper :deep(.v-stepper-item--selected .v-stepper-item__avatar.v-avatar) {
    background: var(--schedule-accent) !important;
    color: #ffffff !important;
    box-shadow: none;
    transform: none;
}

.students-timetable-v2-stepper :deep(.v-stepper-item__title) {
    font-size: 0.86rem;
    font-weight: 600;
    letter-spacing: 0;
}

.students-timetable-v2-stepper :deep(.v-stepper-item--selected .v-stepper-item__title) {
    font-size: 0.86rem;
    font-weight: 700;
}

.ttv2-strip__identity-row {
    display: flex;
    align-items: center;
    min-height: 76px;
    border: 1px solid var(--schedule-border);
    border-radius: 12px;
    padding: 16px 20px;
    background: #f7f8fb;
}

.ttv2-strip__student {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    flex-shrink: 0;
}

.ttv2-strip__student-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--schedule-accent);
    color: #ffffff;
    flex-shrink: 0;
    font-size: 0.84rem;
    font-weight: 700;
}

.ttv2-strip__student-info {
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 0;
}

.ttv2-strip__student-name {
    color: var(--schedule-heading);
    font-size: 0.94rem;
    font-weight: 700;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ttv2-strip__student-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.ttv2-strip__student-religion {
    color: var(--schedule-muted);
    font-size: 0.78rem;
    font-weight: 500;
    line-height: 1;
}

.ttv2-strip__student-email {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    border: none;
    background: none;
    color: var(--schedule-muted);
    font-size: 0.78rem;
    font-weight: 500;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    transition: color 0.15s;
}

.ttv2-strip__student-email:hover,
.ttv2-strip__student-email:focus-visible {
    color: var(--schedule-accent-dark);
}

.ttv2-strip__student-email--copied {
    color: #4ade80 !important;
}

.ttv2-strip__student-actions {
    display: flex;
    align-items: center;
    gap: 2px;
    flex-shrink: 0;
    color: #475569;
    margin-left: auto;
}

.ttv2-strip__no-student {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 700;
}

.ttv2-strip__selections {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    align-items: start;
    gap: 16px;
    min-width: 0;
}

.ttv2-strip__sel-group {
    display: grid;
    align-items: start;
    gap: 8px;
    min-width: 0;
    padding: 0;
    border: 0;
    background: transparent;
    box-shadow: none;
}

.ttv2-strip__sel-label {
    color: var(--schedule-muted);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.ttv2-strip__sel-chips {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.ttv2-strip__sel-chip {
    cursor: pointer;
    min-height: 30px;
    border: 1.5px solid transparent !important;
    border-radius: 8px !important;
    background: #f1f2f6 !important;
    color: #5b6472 !important;
    font-size: 0.78rem !important;
    font-weight: 600;
    box-shadow: none !important;
}

.ttv2-strip__sel-chip:hover {
    border-color: #c7c9f5 !important;
    color: var(--schedule-accent-dark) !important;
}

.ttv2-strip__sel-chip--selected {
    border-color: var(--schedule-accent-dark) !important;
    background: var(--schedule-accent) !important;
    color: #ffffff !important;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.35) !important;
}

.students-timetable-v2-visited-courses-card__title {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    color: var(--schedule-heading);
    font-size: 0.94rem;
    font-weight: 700;
}

.students-timetable-v2-visited-courses-card__legend {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    color: var(--schedule-muted);
    font-size: 0.72rem;
    font-weight: 600;
}

.students-timetable-v2-visited-courses-card__legend > span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.students-timetable-v2-visited-courses-card__legend-dot {
    width: 9px;
    height: 9px;
    border-radius: 3px;
}

.students-timetable-v2-visited-courses-card__legend-dot--completed {
    border: 1px solid #d7dae2;
    background: #f1f2f6;
}

.students-timetable-v2-visited-courses-card__legend-dot--failed {
    background: #8f1f16;
}

.students-timetable-v2-visited-courses-card__content {
    padding-top: 2px;
}

.students-timetable-v2-visited-courses-card__list {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-visited-courses-card__chip {
    border: 1px solid #d7dae2 !important;
    border-radius: 8px !important;
    background: #f1f2f6 !important;
    color: #3d4451 !important;
    font-weight: 600;
    box-shadow: none !important;
}

.students-timetable-v2-visited-courses-card__chip--failed {
    border-color: #f3b9b3 !important;
    background: #fdecea !important;
    color: #8f1f16 !important;
}

.students-timetable-v2-visited-courses-card__course {
    margin-right: 6px;
}

.students-timetable-v2-visited-courses-card__negative-badge {
    border-radius: 5px;
    padding: 1px 5px;
    background: #8f1f16;
    color: #ffffff;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.04em;
}

.students-timetable-v2-visited-courses-card__grade {
    display: inline-flex;
    align-items: center;
    min-height: 18px;
    padding: 0 6px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.78);
    font-size: 0.72rem;
    font-weight: 900;
}

.ttv2-strip__sel-meta {
    border-radius: 999px;
    padding: 1px 7px;
    background: #f1f2f6;
    color: #5b6472;
    font-size: 0.7rem;
    font-weight: 700;
    white-space: nowrap;
    line-height: 1.2;
}

.ttv2-strip__sel-value {
    color: var(--schedule-heading);
    font-size: 0.82rem;
    font-weight: 700;
    white-space: nowrap;
}

.ttv2-strip__sel-value--unknown {
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 999px;
    padding: 1px 8px;
    background: rgba(148, 163, 184, 0.1);
    color: #64748b;
    font-size: 0.78rem;
}

@media (max-width: 640px) {
    .ttv2-strip {
        gap: 14px;
    }

    .ttv2-strip__student {
        flex-wrap: wrap;
    }

    .ttv2-strip__selections {
        grid-template-columns: minmax(0, 1fr);
        gap: 14px;
    }

    .ttv2-strip__identity-row {
        padding: 14px;
    }
}

@media (min-width: 641px) and (max-width: 1100px) {
    .ttv2-strip__selections {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
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

.students-timetable-v2-course-selection-summary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    font-size: 0.82rem;
    font-weight: 800;
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
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    justify-content: space-between;
    border-radius: 12px;
    padding: 16px 20px !important;
    background: #eef1ff;
}

.students-timetable-v2-restart-card {
    border: 0;
    background: #eef1ff;
}

.students-timetable-v2-restart-card__summary {
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
    color: #3d4451;
    font-size: 0.88rem;
    font-weight: 600;
}

.students-timetable-v2-restart-card__summary strong {
    color: var(--schedule-accent);
    font-weight: 800;
}

.students-timetable-v2-restart-card__limit {
    color: var(--schedule-muted);
    font-weight: 500;
}

.students-timetable-v2-restart-card__actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-left: auto;
}

.students-timetable-v2-restart-card__actions :deep(.v-btn) {
    border-radius: 10px;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: none;
}

.students-timetable-v2-restart-card__preselection-button {
    border-color: #c7c9f5 !important;
    color: var(--schedule-accent-dark) !important;
}

.students-timetable-v2-restart-card__automatic-button {
    background: var(--schedule-accent) !important;
    color: #ffffff !important;
    box-shadow: 0 6px 14px rgba(79, 70, 229, 0.26);
}

.students-timetable-v2-limit-alert {
    border-color: #c7c9f5 !important;
    background: #eef1ff !important;
    color: #4338ca !important;
}

.students-timetable-v2-restart-card__navigation-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-left: auto;
}

.students-timetable-v2-restart-card__loading {
    flex-basis: 100%;
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
    gap: 4px 10px;
    align-items: center;
}

.students-timetable-v2-selected-courses-card__filters :deep(.v-selection-control) {
    min-height: 30px;
}

.students-timetable-v2-selected-courses-card__filters :deep(.v-label) {
    font-size: 0.82rem;
    font-weight: 700;
    opacity: 0.88;
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

.students-timetable-v2-selected-courses-card__course--button {
    min-height: 44px !important;
    padding-inline: 18px !important;
    border-radius: 8px !important;
    font-size: 0.98rem;
    font-weight: 800;
}

.students-timetable-v2-selected-courses-card__course--button :deep(.v-chip__content) {
    gap: 4px;
}

.students-timetable-v2-selected-courses-card__course--passive {
    cursor: default;
    pointer-events: none;
}

.students-timetable-v2-selected-courses-card__course--active {
    border-color: #15803d !important;
    background: #16a34a !important;
    color: #ffffff !important;
    box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.34);
}

.students-timetable-v2-selected-courses-card__course--active .students-timetable-v2-selected-courses-card__meta {
    color: #ffffff;
}

.students-timetable-v2-selected-courses-card__course--offered-partial {
    border: 1px solid rgba(217, 119, 6, 0.36);
    background: rgba(255, 251, 235, 0.96) !important;
    color: #92400e !important;
}

.students-timetable-v2-selected-courses-card__course--offered-partial.students-timetable-v2-selected-courses-card__course--active {
    border-color: rgba(154, 52, 18, 0.58) !important;
    background: #c2410c !important;
    color: #ffffff !important;
    box-shadow: 0 0 0 2px rgba(194, 65, 12, 0.3);
}

.students-timetable-v2-selected-courses-card__course--offered-deselected {
    border: 1px solid rgba(220, 38, 38, 0.42);
    background: rgba(254, 226, 226, 0.96) !important;
    color: #991b1b !important;
}

.students-timetable-v2-selected-courses-card__course--offered-deselected.students-timetable-v2-selected-courses-card__course--active {
    border-color: rgba(127, 29, 29, 0.64) !important;
    background: #991b1b !important;
    color: #ffffff !important;
    box-shadow: 0 0 0 2px rgba(153, 27, 27, 0.3);
}

.students-timetable-v2-selected-courses-card__course--conflict {
    border: 1px solid rgba(220, 38, 38, 0.62) !important;
    background: rgba(254, 226, 226, 0.98) !important;
    color: #7f1d1d !important;
}

.students-timetable-v2-selected-courses-card__course--conflict .students-timetable-v2-selected-courses-card__meta {
    color: #7f1d1d !important;
}

.students-timetable-v2-selected-courses-card__meta {
    margin-left: 6px;
    font-weight: 800;
}

.students-timetable-v2-selected-offers-card {
    border: 1px solid rgba(14, 165, 233, 0.16);
}

.students-timetable-v2-selected-offers-card__title {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-selected-offers-card__content {
    padding-top: 0 !important;
}

.students-timetable-v2-selected-offers-card__groups {
    display: grid;
    gap: 10px;
}

.students-timetable-v2-selected-offers-card__group {
    border: 1px solid rgba(14, 165, 233, 0.14);
    background: rgba(248, 250, 252, 0.72);
}

.students-timetable-v2-selected-offers-card__group-title {
    display: flex;
    align-items: center;
    min-height: 0;
    gap: 8px;
    padding: 10px 12px 4px;
    color: #0f172a;
    font-size: 0.92rem;
    font-weight: 900;
    line-height: 1.15;
}

.students-timetable-v2-selected-offers-card__group-actions {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-left: auto;
}

.students-timetable-v2-selected-offers-card__group-content {
    padding: 6px 10px 10px !important;
}

.students-timetable-v2-selected-offers-card__list {
    display: grid;
    gap: 8px;
}

.students-timetable-v2-selected-offers-card__item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    column-gap: 8px;
    row-gap: 5px;
    align-items: start;
    border: 1px solid rgba(148, 163, 184, 0.24);
    border-radius: 8px;
    padding: 9px 10px;
    background: rgba(248, 250, 252, 0.86);
    cursor: pointer;
}

.students-timetable-v2-selected-offers-card__item--selected {
    border-color: rgba(22, 163, 74, 0.2);
    background: rgba(240, 253, 244, 0.82);
}

.students-timetable-v2-selected-offers-card__item:hover,
.students-timetable-v2-selected-offers-card__item:focus-visible {
    border-color: rgba(14, 165, 233, 0.38);
    outline: none;
    box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.14);
}

.students-timetable-v2-selected-offers-card__item--deselected {
    color: #64748b;
}

.students-timetable-v2-selected-offers-card__icon {
    color: #94a3b8;
}

.students-timetable-v2-selected-offers-card__item--selected .students-timetable-v2-selected-offers-card__icon {
    color: #15803d;
}

.students-timetable-v2-selected-offers-card__row {
    display: flex;
    align-items: center;
    min-width: 0;
    flex-wrap: wrap;
    gap: 6px;
}

.students-timetable-v2-selected-offers-card__label {
    min-width: 0;
    color: #0f172a;
    font-size: 0.92rem;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.students-timetable-v2-selected-offers-card__course {
    font-weight: 800;
}

.students-timetable-v2-selected-offers-card__schedule,
.students-timetable-v2-selected-offers-card__rooms {
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.students-timetable-v2-offerchoices {
    border: 1px solid rgba(14, 165, 233, 0.16);
    background: rgba(248, 250, 252, 0.9);
}

.students-timetable-v2-offerchoices__title {
    min-height: 0;
    padding: 10px 14px 4px;
    color: #0f172a;
    font-size: 0.98rem;
    font-weight: 900;
    line-height: 1.15;
}

.students-timetable-v2-offerchoices__content {
    padding: 6px 14px 14px !important;
}

.students-timetable-v2-offerchoices__modules {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.students-timetable-v2-offerchoices__module {
    height: auto !important;
    min-height: 38px;
    padding-inline: 12px;
    font-size: 0.95rem;
    font-weight: 900;
}

.students-timetable-v2-offerchoices__module :deep(.v-chip__content) {
    min-width: 0;
    white-space: normal;
    overflow-wrap: anywhere;
}

.students-timetable-v2-offerchoices__meta {
    margin-left: 7px;
    font-size: 0.78rem;
    font-weight: 900;
    opacity: 0.72;
}

.students-timetable-v2-selected-courses-column {
    flex-direction: column;
    gap: 10px;
}

.students-timetable-v2-selected-courses-column > .students-timetable-v2-card {
    flex: 0 0 auto;
    height: auto;
}

.students-timetable-v2-more-adopted-courses-card {
    border: 1px solid rgba(14, 165, 233, 0.16);
}

.students-timetable-v2-more-adopted-courses-card__title {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-more-adopted-courses-card__content {
    min-height: 48px;
}

.students-timetable-v2-more-adopted-courses-card__grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
}

.students-timetable-v2-more-adopted-courses-card__category {
    display: grid;
    place-items: center;
    min-width: 0;
    min-height: 76px;
    gap: 8px;
    padding: 10px;
    border: 1px solid rgba(148, 163, 184, 0.24);
    border-radius: 8px;
    background: rgba(248, 250, 252, 0.82);
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        background 0.15s ease,
        transform 0.15s ease;
}

.students-timetable-v2-more-adopted-courses-card__category:hover,
.students-timetable-v2-more-adopted-courses-card__category:focus-visible {
    border-color: rgba(37, 99, 235, 0.34);
    background: rgba(239, 246, 255, 0.92);
    outline: none;
    transform: translateY(-1px);
}

.students-timetable-v2-more-adopted-courses-card__category--disabled {
    background: rgba(241, 245, 249, 0.72);
    border-color: rgba(148, 163, 184, 0.16) !important;
    color: rgba(15, 23, 42, 0.42);
    cursor: default;
    opacity: 0.58;
}

.students-timetable-v2-more-adopted-courses-card__category--disabled:hover,
.students-timetable-v2-more-adopted-courses-card__category--disabled:focus-visible {
    background: rgba(241, 245, 249, 0.72);
    border-color: rgba(148, 163, 184, 0.16) !important;
    transform: none;
}

.students-timetable-v2-more-adopted-courses-card__category-title {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    max-width: 100%;
    min-width: 0;
    color: #0f172a;
    font-size: 0.88rem;
    font-weight: 900;
    line-height: 1.2;
    text-align: center;
    overflow-wrap: anywhere;
    hyphens: auto;
}

.students-timetable-v2-more-adopted-courses-card__category--completed {
    border-color: rgba(22, 163, 74, 0.24);
}

.students-timetable-v2-more-adopted-courses-card__category--missing {
    border-color: rgba(239, 68, 68, 0.24);
}

.students-timetable-v2-more-adopted-courses-card__category--planned {
    border-color: rgba(22, 163, 74, 0.24);
}

.students-timetable-v2-more-adopted-courses-card__category--additional {
    border-color: rgba(14, 165, 233, 0.24);
}

.students-timetable-v2-more-adopted-courses-card__category--more {
    border-color: rgba(37, 99, 235, 0.24);
}

.students-timetable-v2-more-adopted-courses-card__course-view {
    display: grid;
    gap: 10px;
}

.students-timetable-v2-more-adopted-courses-card__back-card {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: fit-content;
    min-height: 40px;
    padding: 8px 12px;
    border: 1px solid rgba(37, 99, 235, 0.22);
    border-radius: 8px;
    background: rgba(239, 246, 255, 0.86);
    color: #1d4ed8;
    cursor: pointer;
    font-size: 0.86rem;
    font-weight: 800;
    line-height: 1.2;
    transition:
        border-color 0.15s ease,
        background 0.15s ease;
}

.students-timetable-v2-more-adopted-courses-card__back-card:hover,
.students-timetable-v2-more-adopted-courses-card__back-card:focus-visible {
    border-color: rgba(37, 99, 235, 0.36);
    background: rgba(219, 234, 254, 0.94);
    outline: none;
}

.students-timetable-v2-more-adopted-courses-card__course-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(116px, 1fr));
    gap: 10px;
}

.students-timetable-v2-more-adopted-courses-card__course {
    display: grid;
    place-items: center;
    gap: 6px;
    min-height: 64px;
    padding: 10px 12px;
    border: 1px solid rgba(22, 163, 74, 0.44);
    background: #bbf7d0;
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        background 0.15s ease,
        box-shadow 0.15s ease,
        transform 0.15s ease;
}

.students-timetable-v2-more-adopted-courses-card__course:hover,
.students-timetable-v2-more-adopted-courses-card__course:focus-visible,
.students-timetable-v2-more-adopted-courses-card__course--active {
    border-color: rgba(21, 128, 61, 0.7);
    background: #86efac;
    box-shadow: 0 10px 18px rgba(22, 163, 74, 0.18);
    outline: none;
    transform: translateY(-1px);
}

.students-timetable-v2-more-adopted-courses-card__course--warning {
    border-color: rgba(217, 119, 6, 0.58);
    background: #fde68a;
}

.students-timetable-v2-more-adopted-courses-card__course--warning:hover,
.students-timetable-v2-more-adopted-courses-card__course--warning:focus-visible,
.students-timetable-v2-more-adopted-courses-card__course--warning.students-timetable-v2-more-adopted-courses-card__course--active {
    border-color: rgba(180, 83, 9, 0.78);
    background: #fcd34d;
    box-shadow: 0 10px 18px rgba(217, 119, 6, 0.22);
}

.students-timetable-v2-more-adopted-courses-card__course--error {
    border-color: rgba(220, 38, 38, 0.58);
    background: #fecaca;
}

.students-timetable-v2-more-adopted-courses-card__course--error:hover,
.students-timetable-v2-more-adopted-courses-card__course--error:focus-visible,
.students-timetable-v2-more-adopted-courses-card__course--error.students-timetable-v2-more-adopted-courses-card__course--active {
    border-color: rgba(185, 28, 28, 0.82);
    background: #fca5a5;
    box-shadow: 0 10px 18px rgba(220, 38, 38, 0.24);
}

.students-timetable-v2-more-adopted-courses-card__course-label {
    color: #14532d;
    font-size: 1rem;
    font-weight: 900;
    line-height: 1.2;
    text-align: center;
    overflow-wrap: anywhere;
}

.students-timetable-v2-more-adopted-courses-card__course--warning .students-timetable-v2-more-adopted-courses-card__course-label {
    color: #713f12;
}

.students-timetable-v2-more-adopted-courses-card__course--error .students-timetable-v2-more-adopted-courses-card__course-label {
    color: #7f1d1d;
}

.students-timetable-v2-more-adopted-courses-card__course--success :deep(.v-chip) {
    background: rgba(21, 128, 61, 0.18) !important;
    color: #14532d !important;
}

.students-timetable-v2-more-adopted-courses-card__course--warning :deep(.v-chip) {
    background: rgba(180, 83, 9, 0.18) !important;
    color: #713f12 !important;
}

.students-timetable-v2-more-adopted-courses-card__course--error :deep(.v-chip) {
    background: rgba(185, 28, 28, 0.18) !important;
    color: #7f1d1d !important;
}

.students-timetable-v2-more-adopted-course-offers-card {
    border: 1px solid rgba(37, 99, 235, 0.14);
}

.students-timetable-v2-offered-courses-card__title {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-offered-courses-card__actions {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-left: auto;
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
    border-color: rgba(220, 38, 38, 0.28);
    background: rgba(254, 242, 242, 0.9);
    color: #7f1d1d;
}

.students-timetable-v2-offered-courses-card__item--deselected .students-timetable-v2-offered-courses-card__name {
    color: #7f1d1d;
}

.students-timetable-v2-offered-courses-card__item--available {
    border-color: rgba(22, 163, 74, 0.36);
    background: rgba(240, 253, 244, 0.92);
    color: #14532d;
}

.students-timetable-v2-offered-courses-card__item--available .students-timetable-v2-offered-courses-card__name,
.students-timetable-v2-offered-courses-card__item--available .students-timetable-v2-offered-courses-card__code {
    color: #14532d;
}

.students-timetable-v2-offered-courses-card__item--unavailable {
    cursor: not-allowed;
    border-color: rgba(220, 38, 38, 0.5);
    background: rgba(254, 242, 242, 0.95);
    color: #7f1d1d;
    box-shadow: inset 0 0 0 1px rgba(220, 38, 38, 0.14);
}

.students-timetable-v2-offered-courses-card__item--unavailable .students-timetable-v2-offered-courses-card__name,
.students-timetable-v2-offered-courses-card__item--unavailable .students-timetable-v2-offered-courses-card__code {
    color: #7f1d1d;
}

.students-timetable-v2-offered-courses-card__item--colliding {
    border-color: rgba(220, 38, 38, 0.5);
    background: rgba(254, 242, 242, 0.95);
    color: #7f1d1d;
    box-shadow: inset 0 0 0 1px rgba(220, 38, 38, 0.14);
}

.students-timetable-v2-offered-courses-card__item--colliding .students-timetable-v2-offered-courses-card__name,
.students-timetable-v2-offered-courses-card__item--colliding .students-timetable-v2-offered-courses-card__code {
    color: #7f1d1d;
}

.students-timetable-v2-offered-courses-card__name {
    color: #0f172a;
    font-weight: 800;
}

.students-timetable-v2-offered-courses-card__code {
    color: rgba(15, 23, 42, 0.62);
    font-weight: 800;
}

.students-timetable-v2-offered-courses-card__conflicts {
    display: grid;
    flex-basis: 100%;
    gap: 5px;
    border-top: 1px solid rgba(220, 38, 38, 0.16);
    margin-top: 2px;
    padding-top: 7px;
}

.students-timetable-v2-offered-courses-card__conflicts-title {
    color: #991b1b;
    font-size: 0.68rem;
    font-weight: 900;
    line-height: 1.1;
}

.students-timetable-v2-offered-courses-card__conflict {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    color: #7f1d1d;
    font-size: 0.74rem;
    font-weight: 750;
    line-height: 1.25;
}

.students-timetable-v2-offered-courses-card__conflict-time {
    font-weight: 900;
}

.students-timetable-v2-offered-courses-card__conflict-date {
    border-radius: 999px;
    padding: 1px 6px;
    background: rgba(254, 226, 226, 0.88);
    font-weight: 850;
}

.students-timetable-v2-offered-courses-card__conflict-courses {
    overflow-wrap: anywhere;
}

.students-timetable-v2-calculation-card__success,
.students-timetable-v2-calculation-card__summary {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.students-timetable-v2-calculation-card__success {
    width: 100%;
}

.students-timetable-v2-calculation-card__timetable-selector {
    justify-content: flex-end;
    margin-left: auto;
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

.students-timetable-v2-calculation-card__title-progress {
    display: inline-flex;
    align-items: center;
    width: clamp(140px, 24vw, 220px);
}

.students-timetable-v2-calculation-card__title-progress :deep(.v-progress-linear__content) {
    color: rgb(var(--v-theme-on-primary));
    font-size: 0.72rem;
    font-weight: 800;
}

.students-timetable-v2-calculation-card__actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    flex: 0 1 auto;
    margin-left: auto;
}

.students-timetable-v2-save-button__label {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.08;
}

.students-timetable-v2-published-timetable-report {
    margin-bottom: 10px;
}

.students-timetable-v2-calculation-card__more-course-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
    margin-left: auto;
    flex: 0 1 auto;
    flex-wrap: wrap;
}

.students-timetable-v2-calculation-card__primary-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-calculation-card__reset-button {
    margin-left: 0;
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
    border: 1px solid rgba(100, 116, 139, 0.24);
    background: rgba(248, 250, 252, 0.96);
    flex: 0 1 220px;
    cursor: pointer;
    max-width: 220px;
}

.students-timetable-v2-options-card__option--unavailable {
    border-color: rgba(148, 163, 184, 0.34);
    background: rgba(241, 245, 249, 0.96);
    cursor: not-allowed;
}

.students-timetable-v2-options-card__option--unavailable .students-timetable-v2-options-card__option-title {
    color: #64748b;
}

.students-timetable-v2-options-card__option--unavailable .students-timetable-v2-options-card__option-count {
    color: #475569;
}

.students-timetable-v2-options-card__option--loading {
    border-color: rgba(100, 116, 139, 0.3);
    background: rgba(248, 250, 252, 0.98);
    cursor: wait;
}

.students-timetable-v2-options-card__option--loading .students-timetable-v2-options-card__option-title {
    color: #475569;
}

.students-timetable-v2-options-card__option--loading .students-timetable-v2-options-card__option-count {
    color: #334155;
}

.students-timetable-v2-options-card__option--selected {
    border-color: rgba(22, 163, 74, 0.35);
    background: rgba(220, 252, 231, 0.75);
}

.students-timetable-v2-options-card__option--selected .students-timetable-v2-options-card__option-title {
    color: #15803d;
}

.students-timetable-v2-options-card__option-title {
    color: #334155;
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

.students-timetable-v2-more-courses-card__category-grid {
    display: grid;
    gap: 10px;
    grid-template-columns: repeat(5, minmax(0, 1fr));
}

.students-timetable-v2-more-courses-card__category {
    display: grid;
    place-items: center;
    min-width: 0;
    min-height: 76px;
    gap: 8px;
    padding: 10px;
    border: 1px solid rgba(148, 163, 184, 0.24);
    border-radius: 8px;
    background: rgba(248, 250, 252, 0.82);
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        background 0.15s ease,
        transform 0.15s ease;
}

.students-timetable-v2-more-courses-card__category:hover,
.students-timetable-v2-more-courses-card__category:focus-visible {
    border-color: rgba(37, 99, 235, 0.34);
    background: rgba(239, 246, 255, 0.92);
    outline: none;
    transform: translateY(-1px);
}

.students-timetable-v2-more-courses-card__category--empty {
    background: rgba(241, 245, 249, 0.72);
    border-color: rgba(148, 163, 184, 0.16) !important;
    color: rgba(15, 23, 42, 0.42);
    cursor: default;
    opacity: 0.58;
}

.students-timetable-v2-more-courses-card__category--empty:hover,
.students-timetable-v2-more-courses-card__category--empty:focus-visible {
    background: rgba(241, 245, 249, 0.72);
    border-color: rgba(148, 163, 184, 0.16) !important;
    transform: none;
}

.students-timetable-v2-more-courses-card__category-title {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    max-width: 100%;
    min-width: 0;
    color: #0f172a;
    font-size: 0.88rem;
    font-weight: 900;
    line-height: 1.2;
    text-align: center;
    overflow-wrap: anywhere;
    hyphens: auto;
}

.students-timetable-v2-more-courses-card__category--completed {
    border-color: rgba(22, 163, 74, 0.24);
}

.students-timetable-v2-more-courses-card__category--missing {
    border-color: rgba(239, 68, 68, 0.24);
}

.students-timetable-v2-more-courses-card__category--planned {
    border-color: rgba(22, 163, 74, 0.24);
}

.students-timetable-v2-more-courses-card__category--semester {
    border-color: rgba(14, 165, 233, 0.24);
}

.students-timetable-v2-more-courses-card__category--additional {
    border-color: rgba(37, 99, 235, 0.24);
}

.students-timetable-v2-more-courses-card__course-view {
    display: grid;
    gap: 10px;
}

.students-timetable-v2-more-courses-card__back-card {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: fit-content;
    min-height: 40px;
    padding: 8px 12px;
    border: 1px solid rgba(37, 99, 235, 0.22);
    border-radius: 8px;
    background: rgba(239, 246, 255, 0.86);
    color: #1d4ed8;
    cursor: pointer;
    font-size: 0.86rem;
    font-weight: 900;
    line-height: 1.1;
    transition:
        border-color 0.15s ease,
        background 0.15s ease,
        color 0.15s ease;
}

.students-timetable-v2-more-courses-card__back-card:hover,
.students-timetable-v2-more-courses-card__back-card:focus-visible {
    border-color: rgba(37, 99, 235, 0.32);
    background: rgba(37, 99, 235, 0.08);
    color: #1e40af;
    outline: none;
}

.students-timetable-v2-more-courses-card__list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(116px, 1fr));
    gap: 10px;
}

.students-timetable-v2-more-courses-card__course {
    display: grid;
    place-items: center;
    gap: 6px;
    min-height: 64px;
    padding: 10px 12px;
    border: 1px solid rgba(22, 163, 74, 0.44);
    background: #bbf7d0;
    cursor: pointer;
    max-width: 100%;
    transition:
        border-color 0.15s ease,
        background 0.15s ease,
        box-shadow 0.15s ease,
        transform 0.15s ease;
}

.students-timetable-v2-more-courses-card__course:hover,
.students-timetable-v2-more-courses-card__course:focus-visible,
.students-timetable-v2-more-courses-card__course--active {
    border-color: rgba(21, 128, 61, 0.7);
    background: #86efac;
    box-shadow: 0 10px 18px rgba(22, 163, 74, 0.18);
    outline: none;
    transform: translateY(-1px);
}

.students-timetable-v2-more-courses-card__course--warning {
    border-color: rgba(217, 119, 6, 0.58);
    background: #fde68a;
}

.students-timetable-v2-more-courses-card__course--warning:hover,
.students-timetable-v2-more-courses-card__course--warning:focus-visible,
.students-timetable-v2-more-courses-card__course--warning.students-timetable-v2-more-courses-card__course--active {
    border-color: rgba(180, 83, 9, 0.78);
    background: #fcd34d;
    box-shadow: 0 10px 18px rgba(217, 119, 6, 0.22);
}

.students-timetable-v2-more-courses-card__course--error {
    border-color: rgba(220, 38, 38, 0.58);
    background: #fecaca;
}

.students-timetable-v2-more-courses-card__course--error:hover,
.students-timetable-v2-more-courses-card__course--error:focus-visible,
.students-timetable-v2-more-courses-card__course--error.students-timetable-v2-more-courses-card__course--active {
    border-color: rgba(185, 28, 28, 0.82);
    background: #fca5a5;
    box-shadow: 0 10px 18px rgba(220, 38, 38, 0.24);
}

.students-timetable-v2-more-courses-card__course--unavailable {
    opacity: 0.78;
}

.students-timetable-v2-more-courses-card__course--disabled {
    box-shadow: none !important;
    cursor: not-allowed;
    opacity: 0.48;
    transform: none !important;
}

.students-timetable-v2-more-courses-card__course--disabled:hover,
.students-timetable-v2-more-courses-card__course--disabled:focus-visible {
    box-shadow: none !important;
    outline: none;
    transform: none !important;
}

.students-timetable-v2-more-courses-card__course--success.students-timetable-v2-more-courses-card__course--disabled:hover,
.students-timetable-v2-more-courses-card__course--success.students-timetable-v2-more-courses-card__course--disabled:focus-visible {
    border-color: rgba(22, 163, 74, 0.44);
    background: #bbf7d0;
}

.students-timetable-v2-more-courses-card__course--warning.students-timetable-v2-more-courses-card__course--disabled:hover,
.students-timetable-v2-more-courses-card__course--warning.students-timetable-v2-more-courses-card__course--disabled:focus-visible {
    border-color: rgba(217, 119, 6, 0.58);
    background: #fde68a;
}

.students-timetable-v2-more-courses-card__course--error.students-timetable-v2-more-courses-card__course--disabled:hover,
.students-timetable-v2-more-courses-card__course--error.students-timetable-v2-more-courses-card__course--disabled:focus-visible {
    border-color: rgba(220, 38, 38, 0.58);
    background: #fecaca;
}

.students-timetable-v2-more-courses-card__label {
    color: #14532d;
    font-size: 1rem;
    font-weight: 900;
    line-height: 1.2;
    text-align: center;
    overflow-wrap: anywhere;
}

.students-timetable-v2-more-courses-card__course--warning .students-timetable-v2-more-courses-card__label {
    color: #713f12;
}

.students-timetable-v2-more-courses-card__course--error .students-timetable-v2-more-courses-card__label {
    color: #7f1d1d;
}

.students-timetable-v2-more-courses-card__course--success :deep(.v-chip) {
    background: rgba(21, 128, 61, 0.18) !important;
    color: #14532d !important;
}

.students-timetable-v2-more-courses-card__course--warning :deep(.v-chip) {
    background: rgba(180, 83, 9, 0.18) !important;
    color: #713f12 !important;
}

.students-timetable-v2-more-courses-card__course--error :deep(.v-chip) {
    background: rgba(185, 28, 28, 0.18) !important;
    color: #7f1d1d !important;
}

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

.students-timetable-v2-result-conflicts--info {
    border: 1px solid rgba(14, 165, 233, 0.22);
    background: rgba(240, 249, 255, 0.96);
    color: #075985;
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

.students-timetable-v2-result-conflicts__part {
    white-space: pre-wrap;
}

.students-timetable-v2-result-conflicts__date {
    font-weight: 300;
}

.students-timetable-v2-result-conflicts__actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-result-conflicts__action--recommended {
    border: 1px solid rgba(220, 38, 38, 0.5);
}

.students-timetable-v2-result-problems {
    display: grid;
    gap: 8px;
}

.students-timetable-v2-result-problems__title {
    font-weight: 900;
}

.students-timetable-v2-result-problems__list {
    display: grid;
    gap: 4px;
    margin: 0;
    padding-left: 18px;
    font-size: 0.78rem;
    font-weight: 700;
    line-height: 1.3;
}

.students-timetable-v2-result-problems__actions {
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

.students-timetable-v2-result-grid__distance-learning {
    color: #92400e;
    font-size: 0.7rem;
    font-weight: 800;
    line-height: 1.2;
}

.students-timetable-v2-result-grid__date {
    color: #0369a1;
    font-weight: 400;
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

.students-timetable-v2-result-grid__conflict {
    color: #7f1d1d;
    font-size: 0.82rem;
    font-weight: 900;
    line-height: 1.15;
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
    display: inline-grid;
    width: fit-content;
    max-width: 100%;
    gap: 6px;
    border: 1px solid rgba(14, 165, 233, 0.22);
    border-radius: 8px;
    padding: 6px 7px;
    background: rgba(240, 249, 255, 0.96);
}

.students-timetable-v2-student-context--summary {
    width: 100%;
}

.students-timetable-v2-student-context__title {
    display: grid;
    align-items: center;
    grid-template-columns: 22px minmax(0, 1fr) auto;
    column-gap: 7px;
    row-gap: 4px;
    min-width: 0;
    color: #0f172a;
    font-weight: 800;
}

.students-timetable-v2-student-context__icon {
    display: inline-grid;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border: 1px solid rgba(37, 99, 235, 0.14);
    border-radius: 7px;
    background: rgba(219, 234, 254, 0.72);
}

.students-timetable-v2-student-context__student-text {
    display: grid;
    min-width: 0;
}

.students-timetable-v2-student-context__student-main {
    display: grid;
    align-items: start;
    gap: 3px;
    min-width: 0;
}

.students-timetable-v2-student-context__student-label {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.96rem;
    line-height: 1.18;
}

.students-timetable-v2-student-context__student-meta {
    display: flex;
    align-items: center;
    min-width: 0;
    gap: 5px;
    flex-wrap: wrap;
}

.students-timetable-v2-review-card__student-religion,
.students-timetable-v2-student-context__student-religion {
    display: inline-flex;
    align-items: center;
    max-width: 100%;
    border-radius: 999px;
    padding: 1px 0;
    color: #0369a1;
    font-size: 0.7rem;
    font-weight: 700;
    line-height: 1.1;
}

.students-timetable-v2-student-actions {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    align-self: center;
    gap: 4px;
    padding-left: 4px;
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

.students-timetable-v2-course-card-footer {
    min-height: 0;
    padding: 0 16px 14px;
    color: rgba(15, 23, 42, 0.62);
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1.25;
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

.students-timetable-v2-completed-courses__item--completed {
    border-color: rgba(22, 163, 74, 0.18);
    background: rgba(240, 253, 244, 0.78);
}

.students-timetable-v2-completed-courses__item--missing {
    border-color: rgba(22, 163, 74, 0.18);
    background: rgba(240, 253, 244, 0.78);
}

.students-timetable-v2-completed-courses__item--semester {
    border-color: rgba(22, 163, 74, 0.18);
    background: rgba(240, 253, 244, 0.78);
}

.students-timetable-v2-completed-courses__item--additional {
    border-color: rgba(22, 163, 74, 0.18);
    background: rgba(240, 253, 244, 0.78);
}

.students-timetable-v2-completed-courses__item--toggle {
    cursor: pointer;
}

.students-timetable-v2-completed-courses__item--static {
    cursor: default;
}

.students-timetable-v2-completed-courses__item--deselected {
    background: rgba(255, 255, 255, 0.86);
    box-shadow: none;
}

.students-timetable-v2-completed-courses__item--selected {
    border-color: #15803d !important;
    background: #16a34a !important;
    color: #ffffff !important;
    box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.34);
}

.students-timetable-v2-completed-courses__item--selected .students-timetable-v2-completed-courses__item-meta {
    background: rgba(255, 255, 255, 0.22);
    color: #ffffff;
}

.students-timetable-v2-completed-courses__item--limit-disabled {
    cursor: not-allowed;
    border-color: rgba(14, 116, 144, 0.42);
    background: rgba(236, 254, 255, 0.96);
    color: #155e75;
}

.students-timetable-v2-completed-courses__item--limit-disabled span:first-child {
    text-decoration: none;
}

.students-timetable-v2-completed-courses__item--unavailable {
    cursor: not-allowed;
    border-color: rgba(220, 38, 38, 0.38);
    background: rgba(254, 226, 226, 0.94);
    color: #991b1b;
}

.students-timetable-v2-completed-courses__item--unavailable span:first-child {
    text-decoration: none;
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
    gap: 10px;
    max-height: 260px;
    margin-top: 10px;
    overflow-y: auto;
}

.students-timetable-v2-student-search-results__item {
    justify-content: flex-start;
    min-height: 40px;
    padding-block: 7px;
    text-transform: none;
}

.students-timetable-v2-student-search-results__label {
    width: 100%;
    color: rgb(var(--v-theme-on-surface));
    font-size: 0.86rem;
    font-weight: 700;
    letter-spacing: 0;
    line-height: 1.35;
    overflow-wrap: anywhere;
    text-align: left;
    white-space: normal;
}

.students-timetable-v2-review-card__student-email,
.students-timetable-v2-student-context__student-email {
    display: inline-flex;
    align-items: center;
    max-width: 100%;
    gap: 4px;
    border: 1px solid rgba(37, 99, 235, 0.22);
    border-radius: 999px;
    padding: 1px 6px;
    background: rgba(219, 234, 254, 0.82);
    color: #1d4ed8;
    cursor: pointer;
    font: inherit;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0;
    line-height: 1.15;
}

.students-timetable-v2-student-context__student-email {
    justify-self: start;
    min-width: 0;
}

.students-timetable-v2-review-card__student-email span,
.students-timetable-v2-student-context__student-email span {
    overflow: hidden;
    min-width: 0;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.students-timetable-v2-review-card__student-email:hover,
.students-timetable-v2-review-card__student-email:focus-visible,
.students-timetable-v2-student-context__student-email:hover,
.students-timetable-v2-student-context__student-email:focus-visible {
    background: rgba(191, 219, 254, 0.96);
    outline: none;
}

.students-timetable-v2-review-card__student-email--copied,
.students-timetable-v2-student-context__student-email--copied {
    border-color: rgba(22, 163, 74, 0.28);
    background: rgba(220, 252, 231, 0.92);
    color: #15803d;
}

.students-timetable-v2-review-card__student-email-copied,
.students-timetable-v2-student-context__student-email-copied {
    color: #15803d;
}

.students-timetable-v2-student-search-results__empty {
    padding: 8px 2px;
    color: rgba(var(--v-theme-on-surface), 0.58);
    font-size: 0.78rem;
}

@media (max-width: 640px) {
    .students-timetable-v2-student-summary-card__content {
        grid-template-columns: minmax(0, 1fr);
    }

    .students-timetable-v2-student-summary-card__selection {
        border-top: 1px solid rgba(14, 165, 233, 0.14);
        border-left: 0;
        padding-top: 9px;
        padding-left: 0;
    }

    .students-timetable-v2-selection--summary {
        grid-template-columns: minmax(0, 1fr);
    }

    .students-timetable-v2-student-context {
        width: 100%;
    }

    .students-timetable-v2-student-context__title {
        grid-template-columns: 22px minmax(0, 1fr);
    }

    .students-timetable-v2-student-actions {
        grid-column: 2;
        justify-self: start;
        padding-left: 0;
    }
}

.students-timetable-v2-selection-card :deep(.v-card-title) {
    min-height: 0;
}

.students-timetable-v2-selection-card__title {
    padding: 10px 14px 4px;
    font-size: 0.98rem;
    font-weight: 800;
    line-height: 1.15;
}

.students-timetable-v2-selection-card__content {
    padding: 4px 14px 12px !important;
}

.students-timetable-v2-selection {
    display: grid;
    gap: 5px;
}

.students-timetable-v2-selection--summary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
}

.students-timetable-v2-selection__item {
    display: grid;
    align-items: center;
    grid-template-columns: minmax(78px, 112px) minmax(0, 1fr);
    gap: 6px;
    min-width: 0;
    padding: 2px 0;
}

.students-timetable-v2-selection__item--summary {
    align-items: start;
    grid-template-columns: minmax(0, 1fr);
    gap: 4px;
    border: 1px solid rgba(14, 165, 233, 0.16);
    border-radius: 8px;
    padding: 7px 8px;
    background: rgba(248, 250, 252, 0.78);
}

.students-timetable-v2-selection__label {
    color: rgba(15, 23, 42, 0.62);
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0;
    line-height: 1.1;
}

.students-timetable-v2-selection__item strong {
    font-size: 0.84rem;
    font-weight: 800;
    line-height: 1.1;
}

.students-timetable-v2-selection__value {
    width: fit-content;
    min-width: 0;
}

.students-timetable-v2-selection__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    min-width: 0;
}

.students-timetable-v2-selection__meta {
    width: fit-content;
    max-width: 100%;
    overflow: hidden;
    border-radius: 999px;
    padding: 1px 6px;
    background: rgba(14, 165, 233, 0.1);
    color: #0369a1;
    font-size: 0.72rem;
    font-weight: 800;
    line-height: 1.2;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.students-timetable-v2-selection__chip {
    max-width: 100%;
    cursor: pointer;
    font-size: 0.7rem;
    font-weight: 800;
}

.students-timetable-v2-selection__chip :deep(.v-chip__content) {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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

@media (max-width: 520px) {
    .students-timetable-v2-selection__item {
        grid-template-columns: minmax(0, 1fr);
        gap: 3px;
    }
}

@media (max-width: 640px) {
    .students-timetable-v2-start-card__actions {
        grid-template-columns: 1fr;
    }

    .students-timetable-v2-calculation-card__content {
        padding: 10px !important;
    }

    .students-timetable-v2-result-grid {
        grid-template-columns: minmax(34px, 0.58fr) repeat(var(--students-timetable-v2-result-weekdays, 5), minmax(0, 1fr));
        overflow-x: visible;
    }

    .students-timetable-v2-result-grid__cell {
        min-height: 50px;
        padding: 4px;
    }

    .students-timetable-v2-result-grid__cell--header {
        min-height: 28px;
        font-size: 0.68rem;
    }

    .students-timetable-v2-result-grid__cell--time {
        min-height: 50px;
        font-size: 0.6rem;
        line-height: 1.08;
    }

    .students-timetable-v2-result-grid__code {
        gap: 2px;
        font-size: 0.72rem;
        line-height: 1.1;
    }

    .students-timetable-v2-result-grid__hour {
        font-size: 0.72rem;
    }

    .students-timetable-v2-result-grid__details,
    .students-timetable-v2-result-grid__date,
    .students-timetable-v2-result-grid__recurrence,
    .students-timetable-v2-result-grid__same-slot,
    .students-timetable-v2-result-grid__conflicts {
        display: none;
    }

    .students-timetable-v2-result-grid__badge {
        padding: 1px 3px;
        font-size: 0.46rem;
    }

    .students-timetable-v2-more-adopted-courses-card__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .students-timetable-v2-more-courses-card__category-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .students-timetable-v2-more-adopted-courses-card__category {
        min-height: 64px;
        padding: 8px;
    }

    .students-timetable-v2-more-courses-card__category {
        min-height: 64px;
        padding: 8px;
    }

    .students-timetable-v2-more-adopted-courses-card__category-title {
        font-size: 0.82rem;
        line-height: 1.15;
    }

    .students-timetable-v2-more-courses-card__category-title {
        font-size: 0.82rem;
        line-height: 1.15;
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
