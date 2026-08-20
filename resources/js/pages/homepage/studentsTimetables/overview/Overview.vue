<template>
    <div class="lernportal-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <StudentTimetablesNavigationDrawer v-model="showDrawer" current-route="overview" />

        <section class="hero">
            <div class="hero-card">
                <div class="hero-topline">
                    <v-btn class="back-btn" variant="text" prepend-icon="mdi-arrow-left" @click="$router.push('/')">Zur Startseite</v-btn>
                    <v-btn class="menu-btn" variant="text" icon="mdi-menu" @click="showDrawer = true" />
                </div>

                <h1 class="hero-title">Schülerstundenpläne</h1>
                <p class="hero-subtitle">Ihr Stundenplanbereich.</p>

                <div class="hero-badges">
                    <span class="hero-badge">{{ weekdayLabel }}</span>
                    <span class="hero-badge dark">{{ dateLabel }}</span>
                    <span v-if="user" class="hero-badge">{{ user.first_name }} {{ user.last_name }}</span>
                    <span v-if="user?.schoolclass" class="hero-badge dark">{{ user.schoolclass }}</span>
                    <span v-if="studentReligionLabel" class="hero-badge">{{ studentReligionLabel }}</span>
                </div>

                <div class="hero-course-history">
                    <template
                        v-for="section in heroCourseHistorySections"
                        :key="section.key">
                        <div
                            class="hero-course-history__section">
                            <div class="hero-course-history__title">
                                <v-icon :icon="section.icon" size="18" :color="section.color" />
                                <span>{{ section.title }}</span>
                                <v-chip size="x-small" :color="section.color" variant="tonal">
                                    {{ section.items.length }}
                                </v-chip>
                            </div>
                            <div v-if="section.items.length" class="hero-course-history__list">
                                <span
                                    v-for="course in section.items"
                                    :key="courseKey(section.key, course)"
                                    class="hero-course-history__item"
                                    :class="`hero-course-history__item--${section.key}`">
                                    <span>{{ courseHistoryCourseLabel(course) }}</span>
                                    <v-chip v-if="courseHistoryCourseMeta(course)" size="x-small" :color="section.color" variant="tonal">
                                        {{ courseHistoryCourseMeta(course) }}
                                    </v-chip>
                                </span>
                            </div>
                            <div v-else class="hero-course-history__empty">
                                {{ section.empty }}
                            </div>
                        </div>
                        <div
                            v-if="section.key === 'completed' && heroTimetableSelectionSummary.length"
                            class="hero-course-history__selection-summary">
                            <v-chip
                                v-for="item in heroTimetableSelectionSummary"
                                :key="item.key"
                                size="small"
                                class="hero-course-history__selection-chip"
                                variant="flat">
                                {{ item.label }}: {{ item.value }}
                            </v-chip>
                        </div>
                    </template>
                </div>

                <div class="hero-logout-row">
                    <v-btn class="logout-btn" variant="text" prepend-icon="mdi-logout" @click="handleLogout">Abmelden</v-btn>
                </div>
            </div>
        </section>

        <section class="content-cover">
            <div class="content-card">
                <div v-if="!automaticTimetableCriteriaReviewVisible" class="content-head">
                    <v-icon size="26">mdi-calendar-clock-outline</v-icon>
                    <h2>Ihre Daten</h2>
                    <v-btn
                        v-if="!showEvaluationSettings && !showManualTimetable"
                        class="overview-selection__reset ml-auto"
                        prepend-icon="mdi-restore"
                        variant="tonal"
                        color="primary"
                        density="comfortable"
                        size="large"
                        :disabled="!hasSelectionOverride"
                        @click="restoreSelectionDefaults">
                        Zurücksetzen
                    </v-btn>
                </div>

                <v-card
                    v-if="automaticTimetableCoursesLoadingVisible"
                    rounded="lg"
                    class="automatic-course-loading-card">
                    <v-card-text class="automatic-course-loading-card__content">
                        <v-progress-circular indeterminate color="primary" size="34" width="4" />
                        <div class="automatic-course-loading-card__copy">
                            <div class="automatic-course-loading-card__title">Kurse werden geladen</div>
                            <div class="automatic-course-loading-card__subtitle">
                                Die ausgewählten Kurse und angebotenen Kurse werden vorbereitet.
                            </div>
                        </div>
                    </v-card-text>
                </v-card>

                    <v-card
                        v-else-if="automaticTimetableCriteriaReviewVisible"
                        rounded="lg"
                        class="automatic-course-review-card">
                    <v-card-title class="automatic-course-review-card__title students-timetable-v2-selected-courses-card__title">
                        <span>Ausgewählte Kurse</span>
                        <span class="automatic-course-review-card__filters students-timetable-v2-selected-courses-card__filters">
                            <v-btn
                                v-for="option in automaticCourseBulkSelectionOptions"
                                :key="option.key"
                                size="small"
                                variant="tonal"
                                color="#3949AB"
                                class="students-timetable-v2-selected-courses-card__bulk-button"
                                @click="applyAutomaticCourseBulkSelection(option.key)">
                                {{ option.label }}
                            </v-btn>
                        </span>
                        <span class="automatic-course-review-card__summary">
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ overviewSelectedCourseLimitSummary.countLabel }}
                            </v-chip>
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ overviewSelectedCourseLimitSummary.hoursLabel }}
                            </v-chip>
                        </span>
                    </v-card-title>
                    <v-card-text>
                        <div v-if="automaticSelectedCourseItems.length" class="automatic-course-review-card__list students-timetable-v2-selected-courses-card__list">
                            <v-chip
                                v-for="course in automaticSelectedCourseItems"
                                :key="course.selectionKey"
                                size="small"
                                color="success"
                                :variant="automaticReviewCourseActive(course) ? 'flat' : 'tonal'"
                                class="automatic-course-review-card__course students-timetable-v2-selected-courses-card__course"
                                :class="{
                                    'automatic-course-review-card__course--active': automaticReviewCourseActive(course),
                                    'students-timetable-v2-selected-courses-card__course--active': automaticReviewCourseActive(course),
                                    'students-timetable-v2-selected-courses-card__course--offered-partial': automaticOfferedCourseItemsPartlySelected(course),
                                    'students-timetable-v2-selected-courses-card__course--offered-deselected': automaticOfferedCourseItemsAllDeselected(course),
                                    'automatic-course-review-card__course--distance-learning': course.distanceLearning,
                                }"
                                role="button"
                                :aria-pressed="automaticReviewCourseActive(course) ? 'true' : 'false'"
                                @click="selectAutomaticReviewCourse(course)">
                                <span>{{ course.label }}</span>
                                <span v-if="course.meta" class="automatic-course-review-card__meta students-timetable-v2-selected-courses-card__meta">
                                    {{ course.meta }}
                                </span>
                            </v-chip>
                        </div>
                        <v-alert v-else type="info" variant="tonal" density="compact">
                            Keine Kurse ausgewählt.
                        </v-alert>
                    </v-card-text>
                    <v-card-actions class="automatic-course-review-card__footer">
                        Hier können einzelne Kurse (z.B. Fernunterricht) abgewählt werden.
                    </v-card-actions>
                </v-card>

                <v-card
                    v-if="automaticTimetableCriteriaReviewVisible && selectedAutomaticReviewCourse"
                    rounded="lg"
                    class="automatic-offered-courses-card">
                    <v-card-title class="automatic-offered-courses-card__title">
                        <span>Angebotene Kurse</span>
                        <v-chip size="x-small" color="primary" variant="tonal">
                            {{ selectedAutomaticReviewCourse.label }}
                        </v-chip>
                    </v-card-title>
                    <v-card-text>
                        <div v-if="selectedAutomaticReviewOfferedCourseItems.length" class="automatic-offered-courses-card__list">
                            <div
                                v-for="course in selectedAutomaticReviewOfferedCourseItems"
                                :key="course.selectionKey"
                                class="automatic-offered-courses-card__item automatic-offered-courses-card__item--toggle"
                                :class="{
                                    'automatic-offered-courses-card__item--selected': automaticOfferedCourseSelected(course),
                                    'automatic-offered-courses-card__item--deselected': !automaticOfferedCourseSelected(course),
                                }"
                                role="button"
                                tabindex="0"
                                :aria-pressed="automaticOfferedCourseSelected(course) ? 'true' : 'false'"
                                @click="toggleAutomaticOfferedCourse(course)"
                                @keydown.enter.prevent="toggleAutomaticOfferedCourse(course)"
                                @keydown.space.prevent="toggleAutomaticOfferedCourse(course)">
                                <v-icon v-if="automaticOfferedCourseSelected(course)" icon="mdi-check" size="16" color="success" />
                                <span class="automatic-offered-courses-card__name">
                                    {{ course.name || course.code }}
                                </span>
                                <span v-if="automaticOfferedCourseCodeVisible(course)" class="automatic-offered-courses-card__code">
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
                            </div>
                        </div>
                        <v-alert v-else type="info" variant="tonal" density="compact">
                            Keine angebotenen Kurse gefunden.
                        </v-alert>
                    </v-card-text>
                </v-card>

                <div v-if="!automaticTimetableCriteriaReviewVisible" class="overview-selection">
                    <div class="overview-selected-cards">
                        <div
                            v-for="item in selectionItems"
                            :key="item.key"
                            class="overview-selected-card"
                            :class="{ 'overview-selected-card--overridden': selectionItemOverridden(item) }">
                            <div v-if="item.meta" class="overview-selected-card__meta">{{ item.meta }}</div>
                            <div class="overview-selected-card__top">
                                <div class="overview-selected-card__label">{{ item.label }}</div>
                                <v-btn
                                    v-if="selectionItemEditable(item) && !showEvaluationSettings && !showManualTimetable"
                                    icon="mdi-pencil"
                                    variant="text"
                                    color="primary"
                                    density="comfortable"
                                    size="x-small"
                                    :title="`${item.label} bearbeiten`"
                                    :aria-label="`${item.label} bearbeiten`"
                                    @click="openSelectionDialog(item)" />
                            </div>
                            <div class="overview-selected-card__value">{{ item.value || '-' }}</div>
                        </div>
                    </div>
                </div>

                <v-dialog v-model="selectionDialogOpen" max-width="420">
                    <v-card>
                        <v-card-title>{{ selectionDraftLabel }} bearbeiten</v-card-title>
                        <v-card-text>
                            <v-select
                                v-model="selectionDraftValue"
                                :items="selectionDraftOptions"
                                item-title="title"
                                item-value="value"
                                :label="selectionDraftLabel"
                                variant="outlined"
                                density="comfortable"
                                hide-details />
                        </v-card-text>
                        <v-card-actions>
                            <v-spacer />
                            <v-btn variant="text" @click="closeSelectionDialog">Abbrechen</v-btn>
                            <v-btn color="primary" variant="flat" @click="saveSelectionDialog">Speichern</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

                <v-dialog v-model="personalTimetableDeleteDialogOpen" persistent max-width="460">
                    <v-card>
                        <v-card-title>Mein Stundenplan löschen?</v-card-title>
                        <v-card-text>
                            Dieser Stundenplan wird nur für dich und dieses Schuljahr gelöscht. Der von der Schule gespeicherte Stundenplan bleibt erhalten.
                        </v-card-text>
                        <v-card-actions>
                            <v-spacer />
                            <v-btn
                                variant="text"
                                :disabled="personalTimetableDeleting"
                                @click="closePersonalTimetableDeleteDialog">
                                Abbrechen
                            </v-btn>
                            <v-btn
                                color="error"
                                variant="flat"
                                prepend-icon="mdi-trash-can-outline"
                                :loading="personalTimetableDeleting"
                                :disabled="personalTimetableDeleting"
                                @click="deletePersonalTimetable">
                                Löschen
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

                <v-dialog v-model="studentCoursePickerDialogOpen" persistent max-width="760">
                    <v-card rounded="lg">
                        <v-card-title class="d-flex align-center ga-2">
                            <v-icon icon="mdi-plus-circle" color="primary" />
                            Kurs wählen
                            <v-spacer />
                            <v-btn
                                icon="mdi-close"
                                variant="text"
                                size="small"
                                aria-label="Kursauswahl schließen"
                                @click="studentCoursePickerDialogOpen = false" />
                        </v-card-title>
                        <v-divider />
                        <v-card-text>
                            <div class="course-menu-dialog-options">
                                <v-btn-toggle
                                    v-model="selectedStudentCoursePickerTab"
                                    density="compact"
                                    variant="text"
                                    rounded="lg"
                                    selected-class="course-choice-restriction-option--active"
                                    mandatory
                                    class="course-choice-restriction-switch">
                                    <v-btn
                                        v-for="option in studentCoursePickerTabs"
                                        :key="option.value"
                                        :value="option.value"
                                        class="course-choice-restriction-option"
                                        size="small">
                                        {{ option.label }}
                                    </v-btn>
                                </v-btn-toggle>
                            </div>

                            <div v-if="studentCoursePickerCourseMenus.length" class="course-menu-dialog-chips">
                                <v-chip
                                    v-for="courseMenu in studentCoursePickerCourseMenus"
                                    :key="courseMenu.key"
                                    size="small"
                                    :color="studentCoursePickerMenuHasActiveSelection(courseMenu) ? 'success' : 'primary'"
                                    :variant="selectedStudentCoursePickerMenu?.key === courseMenu.key ? 'flat' : 'tonal'"
                                    class="course-menu-dialog-chip"
                                    @click="selectStudentCoursePickerMenu(courseMenu)">
                                    {{ courseMenu.label }}
                                </v-chip>
                            </div>

                            <div v-else class="empty-state">
                                <v-icon size="20">mdi-information-outline</v-icon>
                                <span>Keine Kurse in dieser Auswahl.</span>
                            </div>

                            <div v-if="selectedStudentCoursePickerMenu" class="course-choice-panel__semesters course-menu-dialog-items">
                                <section
                                    :key="selectedStudentCoursePickerMenu.key"
                                    class="course-choice-semester">
                                    <div class="course-choice-semester__label">
                                        <span>{{ selectedStudentCoursePickerMenu.semesterLabel }}</span>
                                        <span class="course-choice-semester__dates">{{ selectedStudentCoursePickerMenu.semesterDateRangeLabel }}</span>
                                        <v-chip size="x-small" color="primary" variant="tonal">
                                            {{ selectedStudentCoursePickerMenu.label }}
                                        </v-chip>
                                    </div>
                                    <div class="course-item-chips">
                                        <v-chip
                                            v-for="entryOption in selectedStudentCoursePickerEntryOptions"
                                            :key="entryOption.key"
                                            size="small"
                                            :color="entryOption.color"
                                            :variant="entryOption.isActive ? 'flat' : 'tonal'"
                                            class="course-item-chip"
                                            :class="{ 'course-item-chip--disabled': entryOption.isDisabled }"
                                            :aria-disabled="entryOption.isDisabled ? 'true' : 'false'"
                                            @click="toggleStudentCoursePickerEntry(entryOption)">
                                            <v-icon
                                                :icon="entryOption.isActive ? 'mdi-check' : 'mdi-calendar-blank'"
                                                size="16"
                                                start />
                                            <span>{{ entryOption.label }}</span>
                                            <span v-if="entryOption.scheduleLabel" class="course-item-chip__schedule">
                                                {{ entryOption.scheduleLabel }}
                                            </span>
                                        </v-chip>
                                    </div>
                                </section>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-dialog>

                <div
                    v-if="overviewCourseSelectionVisible && !showEvaluationSettings && !showManualTimetable"
                    class="overview-course-selection">
                    <div class="overview-course-selection__toolbar">
                        <v-btn
                            color="primary"
                            variant="tonal"
                            size="large"
                            prepend-icon="mdi-tune-variant"
                            :disabled="!automaticTimetableCoursePreselectionResetAvailable"
                            @click="resetAutomaticTimetableCoursePreselection">
                            Vorauswahl zurücksetzen
                        </v-btn>
                    </div>

                    <v-card
                        v-for="section in overviewCourseSelectionSections"
                        :key="section.key"
                        rounded="lg"
                        variant="flat"
                        class="overview-course-selection__card students-timetable-v2-card">
                        <v-card-title class="overview-course-selection__title students-timetable-v2-course-card-title">
                            <span>{{ section.title }}</span>
                            <span class="overview-course-selection__title-meta students-timetable-v2-course-card-title__meta">
                                <span class="overview-course-selection__chips students-timetable-v2-course-card-title__chips">
                                    <v-chip size="x-small" :color="section.color" variant="tonal">
                                        {{ courseItemsSummary(section.selectedItems).countLabel }}
                                    </v-chip>
                                    <v-chip size="x-small" :color="section.color" variant="tonal">
                                        {{ courseItemsSummary(section.selectedItems).hoursLabel }}
                                    </v-chip>
                                </span>
                                <span v-if="section.bulkSelectable" class="overview-course-selection__actions students-timetable-v2-course-card-title__actions">
                                    <v-btn
                                        icon="mdi-checkbox-marked-outline"
                                        size="x-small"
                                        density="compact"
                                        variant="tonal"
                                        color="success"
                                        title="Alle vorgesehenen Kurse auswählen"
                                        :disabled="!section.items.length || overviewCourseGroupAllSelected(section.key) || overviewCourseGroupSelectionWouldExceedLimit(section.key)"
                                        @click.stop="setOverviewCourseGroupSelection(section.key, true)" />
                                    <v-btn
                                        icon="mdi-checkbox-blank-outline"
                                        size="x-small"
                                        density="compact"
                                        variant="tonal"
                                        color="secondary"
                                        title="Alle vorgesehenen Kurse abwählen"
                                        :disabled="!section.items.length || overviewCourseGroupNoneSelected(section.key)"
                                        @click.stop="setOverviewCourseGroupSelection(section.key, false)" />
                                </span>
                            </span>
                        </v-card-title>
                        <v-card-text>
                            <div
                                v-if="section.items.length"
                                class="overview-course-selection__list students-timetable-v2-completed-courses__list">
                                <v-chip
                                    v-for="course in section.items"
                                    :key="overviewCourseSelectionKey(course, section.key)"
                                    size="small"
                                    :color="overviewCourseItemColor(course, section.key)"
                                    :variant="overviewCourseItemSelected(course, section.key) ? 'tonal' : 'outlined'"
                                    class="overview-course-selection__item students-timetable-v2-completed-courses__item"
                                    :class="{
                                        'students-timetable-v2-completed-courses__item--toggle': section.selectable,
                                        'students-timetable-v2-completed-courses__item--static': !section.selectable,
                                        'students-timetable-v2-completed-courses__item--deselected': section.selectable && !overviewCourseItemSelected(course, section.key),
                                        'students-timetable-v2-completed-courses__item--limit-disabled': overviewCourseItemSelectionDisabled(course, section.key),
                                        'students-timetable-v2-completed-courses__item--unavailable': overviewCourseItemUnavailable(course, section.key),
                                        [`students-timetable-v2-completed-courses__item--${section.key}`]: true,
                                        'overview-course-selection__item--selected': overviewCourseItemSelected(course, section.key),
                                        'overview-course-selection__item--deselected': section.selectable && !overviewCourseItemSelected(course, section.key),
                                        [`overview-course-selection__item--${section.key}`]: true,
                                        'overview-course-selection__item--static': !section.selectable,
                                        'overview-course-selection__item--limit-disabled': overviewCourseItemSelectionDisabled(course, section.key),
                                        'overview-course-selection__item--unavailable': overviewCourseItemUnavailable(course, section.key),
                                    }"
                                    :role="section.selectable ? 'button' : undefined"
                                    :aria-pressed="section.selectable ? (overviewCourseItemSelected(course, section.key) ? 'true' : 'false') : undefined"
                                    :aria-disabled="overviewCourseItemSelectionDisabled(course, section.key) ? 'true' : undefined"
                                    :title="overviewCourseItemSelectionDisabledLabel(course, section.key)"
                                    @click="toggleOverviewCourseItem(course, section.key)">
                                    <v-icon v-if="overviewCourseItemSelected(course, section.key)" icon="mdi-check" size="14" />
                                    <span>{{ overviewCourseItemLabel(course) }}</span>
                                    <span
                                        v-if="overviewCourseItemMeta(course)"
                                        class="overview-course-selection__item-meta students-timetable-v2-completed-courses__item-meta">
                                        {{ overviewCourseItemMeta(course) }}
                                    </span>
                                </v-chip>
                            </div>
                            <v-alert
                                v-else
                                type="info"
                                variant="tonal"
                                density="compact"
                                class="students-timetable-v2-completed-courses__alert">
                                {{ section.empty }}
                            </v-alert>
                        </v-card-text>
                        <v-card-actions class="overview-course-selection__footer students-timetable-v2-course-card-footer">
                            {{ section.footer }}
                        </v-card-actions>
                    </v-card>

                    <v-alert
                        type="info"
                        variant="tonal"
                        density="compact"
                        icon="mdi-counter"
                        class="overview-course-selection__summary">
                        <div class="overview-course-selection__summary-content">
                            <span>Ausgewählt</span>
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ overviewSelectedCourseLimitSummary.countLabel }}
                            </v-chip>
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ overviewSelectedCourseLimitSummary.hoursLabel }}
                            </v-chip>
                            <span>Maximal 10/30</span>
                        </div>
                    </v-alert>

                    <v-alert
                        v-if="overviewSelectedCourseLimitReached"
                        type="info"
                        variant="tonal"
                        density="compact"
                        icon="mdi-information-outline"
                        class="overview-course-selection__limit-alert">
                        <div>Maximum erreicht: Negative Kurse und Vorgesehene Kurse dürfen zusammen höchstens 10 Kurse und 30 Stunden ergeben.</div>
                        <div>Wählen Sie einen Kurs ab, um einen anderen Kurs auszuwählen.</div>
                    </v-alert>
                </div>

                <div v-if="timetableActionsVisible" class="timetable-actions">
                    <v-btn
                        class="timetable-actions__button timetable-actions__automatic"
                        color="success"
                        variant="tonal"
                        size="large"
                        append-icon="mdi-arrow-right"
                        @click="openAutomaticTimetable">
                        Weiter
                    </v-btn>
                    <v-btn
                        v-if="savedTimetableActionsVisible"
                        class="timetable-actions__button timetable-actions__personal"
                        variant="flat"
                        prepend-icon="mdi-account-calendar"
                        @click="openPersonalTimetable">
                        Mein Stundenplan
                    </v-btn>
                    <v-btn
                        v-if="savedTimetableActionsVisible && hasPublishedTimetable"
                        class="timetable-actions__button timetable-actions__published"
                        variant="flat"
                        prepend-icon="mdi-calendar-check"
                        @click="openPublishedTimetable">
                        Von der Schule gespeicherter Stundenplan
                    </v-btn>
                </div>

                <section v-if="showManualTimetable" class="student-manual-timetable">
                    <div
                        v-if="manualTimetableMode === 'personal'"
                        class="student-manual-timetable__toolbar student-published-timetable__titlebar">
                        <div>
                            <h3>Übernommener Stundenplan</h3>
                        </div>
                    </div>

                    <div
                        v-if="manualTimetableMode !== 'personal'"
                        class="student-manual-timetable__toolbar">
                        <div>
                            <h3>{{ manualTimetableTitle }}</h3>
                        </div>
                        <div class="student-manual-timetable__toolbar-actions">
                            <v-btn
                                v-if="manualTimetableMode === 'published' && hasPublishedTimetable"
                                color="primary"
                                variant="flat"
                                prepend-icon="mdi-content-save-check-outline"
                                :loading="personalTimetableSaving"
                                :disabled="personalTimetableSaving"
                                @click="adoptPublishedTimetable">
                                Als mein Stundenplan übernehmen
                            </v-btn>
                            <v-btn
                                color="#3949AB"
                                variant="text"
                                prepend-icon="mdi-arrow-left"
                                class="students-timetable-v2-restart-card__primary-button"
                                @click="closeManualTimetable">
                                Zurück
                            </v-btn>
                        </div>
                    </div>

                    <v-alert
                        v-if="personalTimetableViewChangeVisible"
                        class="student-manual-timetable__view-change"
                        type="success"
                        variant="tonal"
                        density="comfortable"
                        icon="mdi-swap-horizontal-bold"
                        closable
                        close-label="Meldung schließen"
                        @click:close="dismissPersonalTimetableViewChangeMessage">
                        Der Stundenplan wurde übernommen. Du siehst jetzt "Mein Stundenplan".
                    </v-alert>

                    <v-card
                        v-if="personalTimetableCourseSelectionVisible"
                        rounded="lg"
                        class="students-timetable-v2-card students-timetable-v2-selected-courses-card student-course-choice-panel">
                        <v-card-title class="students-timetable-v2-selected-courses-card__title">
                            <span>Ausgewählte Kurse</span>
                            <span class="students-timetable-v2-selected-courses-card__summary">
                                <v-chip size="x-small" color="primary" variant="tonal">
                                    {{ savedTimetableCourseChips.length }} ausgewählt
                                </v-chip>
                            </span>
                        </v-card-title>
                        <v-card-text>
                            <div v-if="savedTimetableCourseChips.length" class="students-timetable-v2-selected-courses-card__list">
                                <v-chip
                                    v-for="courseChip in savedTimetableCourseChips"
                                    :key="courseChip.key"
                                    size="small"
                                    variant="tonal"
                                    closable
                                    close-icon="mdi-close"
                                    :close-label="`Kurs ${courseChip.title} entfernen`"
                                    class="students-timetable-v2-selected-courses-card__course students-timetable-v2-selected-courses-card__course--passive student-selected-course-filter-chip"
                                    @click:close="deselectSavedTimetableCourseChip(courseChip)">
                                    <span>{{ courseChip.shortLabel }}</span>
                                    <span v-if="courseChip.hoursText" class="students-timetable-v2-selected-courses-card__meta">
                                        {{ courseChip.hoursText }}
                                    </span>
                                </v-chip>
                            </div>
                            <v-alert v-else type="info" variant="tonal" density="compact">
                                Keine Kurse ausgewählt.
                            </v-alert>
                        </v-card-text>
                    </v-card>

                    <v-card
                        v-if="personalMoreCoursesVisible"
                        rounded="lg"
                        class="students-timetable-v2-card students-timetable-v2-more-adopted-courses-card">
                        <v-card-title class="students-timetable-v2-more-adopted-courses-card__title">
                            {{ personalMoreCoursesTitle }}
                        </v-card-title>
                        <v-card-text class="students-timetable-v2-more-adopted-courses-card__content">
                            <div v-if="!activePersonalMoreCourseCard" class="students-timetable-v2-more-adopted-courses-card__grid">
                                <v-card
                                    v-for="card in personalMoreCourseCards"
                                    :key="card.key"
                                    rounded="lg"
                                    variant="flat"
                                    class="students-timetable-v2-more-adopted-courses-card__category"
                                    :class="[
                                        `students-timetable-v2-more-adopted-courses-card__category--${card.key}`,
                                        {
                                            'students-timetable-v2-more-adopted-courses-card__category--disabled': personalMoreCourseCardDisabled(card),
                                        },
                                    ]"
                                    :disabled="personalMoreCourseCardDisabled(card)"
                                    role="button"
                                    tabindex="0"
                                    @click="openPersonalMoreCourseCard(card)"
                                    @keydown.enter.prevent="openPersonalMoreCourseCard(card)"
                                    @keydown.space.prevent="openPersonalMoreCourseCard(card)">
                                    <div class="students-timetable-v2-more-adopted-courses-card__category-title">
                                        {{ card.title }}
                                    </div>
                                </v-card>
                            </div>

                            <div v-else class="students-timetable-v2-more-adopted-courses-card__course-view">
                                <div
                                    class="students-timetable-v2-more-adopted-courses-card__back-card"
                                    role="button"
                                    tabindex="0"
                                    @click="closePersonalMoreCourseCard"
                                    @keydown.enter.prevent="closePersonalMoreCourseCard"
                                    @keydown.space.prevent="closePersonalMoreCourseCard">
                                    <v-icon icon="mdi-arrow-left" size="18" />
                                    Zurück
                                </div>

                                <div v-if="personalMoreCourseMenus.length" class="students-timetable-v2-more-adopted-courses-card__course-list">
                                    <v-card
                                        v-for="courseMenu in personalMoreCourseMenus"
                                        :key="courseMenu.key"
                                        rounded="lg"
                                        variant="tonal"
                                        color="success"
                                        class="students-timetable-v2-more-adopted-courses-card__course"
                                        :class="{ 'students-timetable-v2-more-adopted-courses-card__course--active': personalMoreCourseMenuActive(courseMenu) }"
                                        role="button"
                                        tabindex="0"
                                        @click="openPersonalMoreCourseMenu(courseMenu)"
                                        @keydown.enter.prevent="openPersonalMoreCourseMenu(courseMenu)"
                                        @keydown.space.prevent="openPersonalMoreCourseMenu(courseMenu)">
                                        <span class="students-timetable-v2-more-adopted-courses-card__course-label">
                                            {{ courseMenu.label }}
                                        </span>
                                        <v-chip v-if="courseMenu.meta" size="x-small" color="success" variant="tonal">
                                            {{ courseMenu.meta }}
                                        </v-chip>
                                        <v-chip
                                            v-if="studentCoursePickerMenuHasActiveSelection(courseMenu)"
                                            size="x-small"
                                            color="success"
                                            variant="tonal">
                                            Ausgewählt
                                        </v-chip>
                                    </v-card>
                                </div>
                                <v-alert v-else type="info" variant="tonal" density="compact">
                                    Keine Kurse verfügbar.
                                </v-alert>

                                <v-card
                                    v-if="selectedPersonalMoreCourseMenu"
                                    rounded="lg"
                                    variant="tonal"
                                    class="students-timetable-v2-more-adopted-course-offers-card students-timetable-v2-offered-courses-card">
                                    <v-card-title class="students-timetable-v2-offered-courses-card__title">
                                        Angebotene Kurse
                                        <v-chip size="x-small" color="primary" variant="tonal">
                                            {{ selectedPersonalMoreCourseMenu.label }}
                                        </v-chip>
                                    </v-card-title>
                                    <v-card-text>
                                        <div v-if="selectedPersonalMoreCourseEntryOptions.length" class="students-timetable-v2-offered-courses-card__list">
                                            <div
                                                v-for="course in selectedPersonalMoreCourseEntryOptions"
                                                :key="course.key"
                                                class="students-timetable-v2-offered-courses-card__item students-timetable-v2-offered-courses-card__item--toggle"
                                                :class="{
                                                    'students-timetable-v2-offered-courses-card__item--selected': course.isActive,
                                                    'students-timetable-v2-offered-courses-card__item--deselected': course.isDisabled,
                                                }"
                                                role="button"
                                                tabindex="0"
                                                :aria-pressed="course.isActive ? 'true' : 'false'"
                                                :aria-disabled="course.isDisabled ? 'true' : 'false'"
                                                @click="togglePersonalMoreCourseEntry(course)"
                                                @keydown.enter.prevent="togglePersonalMoreCourseEntry(course)"
                                                @keydown.space.prevent="togglePersonalMoreCourseEntry(course)">
                                                <span class="students-timetable-v2-offered-courses-card__name">
                                                    {{ course.label }}
                                                </span>
                                                <v-chip v-if="course.offeredScheduleLabel" size="x-small" color="primary" variant="tonal">
                                                    {{ course.offeredScheduleLabel }}
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
                                                <v-chip v-if="course.hasRelatedOverlap" size="x-small" color="warning" variant="tonal">
                                                    Überschneidung
                                                </v-chip>
                                                <v-chip v-if="course.isDisabled" size="x-small" color="error" variant="tonal">
                                                    Blockiert
                                                </v-chip>
                                            </div>
                                        </div>
                                        <v-alert v-else type="info" variant="tonal" density="compact">
                                            Keine angebotenen Kurse gefunden.
                                        </v-alert>
                                    </v-card-text>
                                </v-card>
                            </div>
                        </v-card-text>
                    </v-card>

                    <div v-if="publishedTimetableVisible" class="student-published-timetable">
                        <div v-if="publishedTimetableSubtitle" class="student-published-timetable__meta">
                            {{ publishedTimetableSubtitle }}
                        </div>

                        <div
                            v-for="semester in publishedTimetableSemesters"
                            :key="publishedTimetableSemesterKey(semester)"
                            class="student-published-timetable__semester">
                            <div class="student-published-timetable__semester-head">
                                <div class="student-published-timetable__semester-title">
                                    <h4>{{ semester.label || 'Semester' }}</h4>
                                    <span v-if="semester.date_range">{{ semester.date_range }}</span>
                                </div>
                                <div
                                    v-if="manualTimetableMode === 'personal'"
                                    class="student-published-timetable__semester-actions">
                                    <v-btn
                                        color="error"
                                        variant="tonal"
                                        size="large"
                                        prepend-icon="mdi-file-pdf-box"
                                        :loading="pdfExporting"
                                        :disabled="pdfExporting || !publishedTimetableVisible"
                                        @click="downloadPersonalTimetablePdf">
                                        PDF
                                    </v-btn>
                                    <v-btn
                                        color="success"
                                        variant="flat"
                                        size="large"
                                        prepend-icon="mdi-content-save-outline"
                                        :loading="personalTimetableSaving"
                                        :disabled="personalTimetableSaving || !publishedTimetableVisible"
                                        @click="savePersonalVisibleTimetable">
                                        Für mich Speichern
                                    </v-btn>
                                </div>
                            </div>

                            <div
                                v-if="manualTimetableMode === 'personal'"
                                class="student-published-timetable__actions">
                                <v-btn
                                    color="error"
                                    variant="tonal"
                                    size="large"
                                    prepend-icon="mdi-restart"
                                    @click="restartPersonalTimetable">
                                    Neustart
                                </v-btn>
                                <v-btn
                                    color="#3949AB"
                                    variant="text"
                                    size="large"
                                    prepend-icon="mdi-arrow-left"
                                    class="students-timetable-v2-restart-card__primary-button"
                                    @click="goBackFromPersonalTimetable">
                                    Zurück
                                </v-btn>
                            </div>

                            <div
                                v-for="week in publishedTimetableSemesterWeeks(semester)"
                                :key="publishedTimetableWeekKey(semester, week)"
                                class="student-published-timetable__week">
                                <div v-if="week.label" class="student-published-timetable__week-label">{{ week.label }}</div>
                                <div
                                    class="student-published-timetable__grid"
                                    :style="{ '--published-timetable-weekday-count': publishedTimetableWeekdays.length }">
                                    <div class="student-published-timetable__corner">Std.</div>
                                    <div
                                        v-for="weekday in publishedTimetableWeekdays"
                                        :key="weekday.label"
                                        class="student-published-timetable__weekday">
                                        {{ weekday.label }}
                                    </div>

                                    <template
                                        v-for="hour in publishedTimetableWeekHours(week)"
                                        :key="publishedTimetableHourKey(week, hour)">
                                        <div class="student-published-timetable__hour">
                                            <strong>{{ hour.hour }}.</strong>
                                            <span v-if="hour.from || hour.until">{{ hour.from }} - {{ hour.until }}</span>
                                        </div>
                                        <div
                                            v-for="(cell, cellIndex) in publishedTimetableHourCells(hour)"
                                            :key="`${publishedTimetableHourKey(week, hour)}-${cellIndex}`"
                                            class="student-published-timetable__cell"
                                            :class="publishedTimetableCellClasses(cell, semester, hour, cellIndex)">
                                            <div
                                                v-for="course in publishedTimetableCellCourses(cell, semester, hour, cellIndex)"
                                                :key="publishedTimetableCourseKey(course)"
                                                class="student-published-timetable__block"
                                                :class="publishedTimetableCourseClasses(course, cell)">
                                                <div class="student-published-timetable__code">
                                                    <span>{{ course.label || '-' }}</span>
                                                    <sup
                                                        v-if="publishedTimetableCourseIsAdditional(course)"
                                                        class="student-published-timetable__badge student-published-timetable__badge--additional">
                                                        Zusatz
                                                    </sup>
                                                    <sup
                                                        v-else-if="course.student_course_badge"
                                                        class="student-published-timetable__badge">
                                                        {{ course.student_course_badge }}
                                                    </sup>
                                                </div>
                                                <div
                                                    v-if="publishedTimetableCourseDetails(course)"
                                                    class="student-published-timetable__details">
                                                    {{ publishedTimetableCourseDetails(course) }}
                                                </div>
                                                <div
                                                    v-if="publishedTimetableCourseIsDistanceLearning(course, semester, hour, cellIndex)"
                                                    class="student-published-timetable__distance-learning">
                                                    Fernunterricht
                                                </div>
                                                <div
                                                    v-if="publishedTimetableCourseRecurrenceLabel(course, semester, hour, cellIndex)"
                                                    class="student-published-timetable__recurrence">
                                                    {{ publishedTimetableCourseRecurrenceLabel(course, semester, hour, cellIndex) }}
                                                </div>
                                            </div>

                                            <div
                                                v-if="publishedTimetableCellMarkers(cell).length"
                                                class="student-published-timetable__markers">
                                                <span
                                                    v-for="marker in publishedTimetableCellMarkers(cell)"
                                                    :key="publishedTimetableMarkerKey(marker)"
                                                    class="student-published-timetable__marker"
                                                    :class="{ 'student-published-timetable__marker--additional': publishedTimetableMarkerIsAdditional(marker) }">
                                                    <span>{{ marker.label || marker.title || '-' }}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div
                                    v-if="publishedTimetableSameSlotGroups(week).length"
                                    class="timetable-date-overview">
                                    <div class="timetable-date-overview__title">Termine in gleichen Zellen</div>
                                    <div class="timetable-date-overview__groups">
                                        <div
                                            v-for="group in publishedTimetableSameSlotGroups(week)"
                                            :key="group.key"
                                            class="timetable-date-overview__group">
                                            <div class="timetable-date-overview__slot">{{ group.title }}</div>
                                            <div class="timetable-date-overview__courses">
                                                <div
                                                    v-for="course in group.courses"
                                                    :key="course.key"
                                                    class="timetable-date-overview__course">
                                                    <div class="timetable-date-overview__course-title">
                                                        <span>{{ course.title }}</span>
                                                        <span
                                                            v-if="course.dateRangeLabel"
                                                            class="timetable-date-overview__range">
                                                            {{ course.dateRangeLabel }}
                                                        </span>
                                                    </div>
                                                    <div class="timetable-date-overview__dates">
                                                        <span
                                                            v-for="dateLabel in course.dateLabels"
                                                            :key="dateLabel"
                                                            class="timetable-date-overview__date">
                                                            {{ dateLabel }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="personalTimetableEmptyVisible" class="student-manual-timetable__empty student-published-timetable__empty">
                        <v-icon size="24">mdi-calendar-blank-outline</v-icon>
                        <span>Mein Stundenplan ist noch leer.</span>
                    </div>

                    <div v-else class="student-manual-timetable__layout">
                        <div class="student-manual-timetable__courses">
                            <v-expansion-panels v-model="manualExpandedCourseSections" multiple flat>
                                <v-expansion-panel
                                    v-for="section in manualTimetableCourseSections"
                                    :key="section.key"
                                    :value="section.key"
                                    class="student-manual-timetable__course-section">
                                    <v-expansion-panel-title class="student-manual-timetable__course-section-title">
                                        <v-icon size="20">{{ section.icon }}</v-icon>
                                        <span>{{ section.title }}</span>
                                        <v-chip size="x-small" variant="flat" :color="section.color">{{ section.items.length }}</v-chip>
                                    </v-expansion-panel-title>

                                    <v-expansion-panel-text>
                                        <div v-if="section.items.length" class="student-manual-timetable__course-list">
                                            <label
                                                v-for="course in section.items"
                                                :key="manualCourseKey(course)"
                                                class="student-manual-timetable__course"
                                                :class="{ 'student-manual-timetable__course--disabled': !manualCourseGroups(course).length }">
                                                <v-checkbox-btn
                                                    :model-value="manualCourseSelected(course)"
                                                    :disabled="!manualCourseGroups(course).length"
                                                    density="compact"
                                                    color="primary"
                                                    @update:model-value="setManualCourseSelected(course, $event)" />
                                                <span>
                                                    <strong>{{ course.code || course.name || '-' }}</strong>
                                                    <small>{{ manualCourseMeta(course) }}</small>
                                                </span>
                                            </label>
                                        </div>

                                        <div v-else class="empty-state">
                                            <v-icon size="20">mdi-information-outline</v-icon>
                                            <span>{{ section.empty }}</span>
                                        </div>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </div>

                        <div class="student-manual-timetable__preview">
                            <div v-if="manualSelectedCourseGroups.length" class="student-manual-timetable__grid-wrap">
                                <div
                                    class="student-manual-timetable__grid"
                                    :style="{ '--manual-timetable-weekday-count': manualTimetableWeekdays.length }">
                                    <div class="student-manual-timetable__corner">Std.</div>
                                    <div
                                        v-for="weekday in manualTimetableWeekdays"
                                        :key="weekday.value"
                                        class="student-manual-timetable__weekday">
                                        {{ weekday.label }}
                                    </div>

                                    <template v-for="hour in manualTimetableHours" :key="hour.value">
                                        <div class="student-manual-timetable__hour">
                                            <strong>{{ hour.hourLabel }}</strong>
                                            <span v-if="hour.timeFrom || hour.timeUntil">{{ hour.timeFrom }} - {{ hour.timeUntil }}</span>
                                        </div>
                                        <div
                                            v-for="weekday in manualTimetableWeekdays"
                                            :key="`${weekday.value}-${hour.value}`"
                                            class="student-manual-timetable__cell"
                                            :class="{ 'student-manual-timetable__cell--conflict': manualGroupsForCell(weekday.value, hour.value).length > 1 }">
                                            <div
                                                v-for="group in manualGroupsForCell(weekday.value, hour.value)"
                                                :key="manualCourseGroupKey(group)"
                                                class="student-manual-timetable__block">
                                                <strong>{{ manualCourseGroupLabel(group) }}</strong>
                                                <span>{{ manualCourseGroupDetails(group) }}</span>
                                                <small v-if="manualCourseGroupWeekMarker(group)">{{ manualCourseGroupWeekMarker(group) }}</small>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div v-else class="student-manual-timetable__empty">
                                <v-icon size="24">mdi-calendar-search</v-icon>
                                <span>Keine passenden Kurstermine gefunden.</span>
                            </div>
                        </div>
                    </div>
                </section>

                <StudentTimetableEvaluationSettings
                    v-if="showEvaluationSettings && !automaticTimetableCriteriaReviewVisible"
                    :initial-step="automaticTimetableStep"
                    :initial-selected-course-keys="automaticTimetableCourseKeys"
                    :initial-deselected-course-group-keys="automaticTimetableDeselectedCourseGroupKeys"
                    :initial-selected-additional-course-keys="automaticTimetableAdditionalCourseKeys"
                    :initial-selected-quality-criterion-keys="[]"
                    :default-quality-criterion-selection="false"
                    :proposed-courses="automaticTimetableSelectableCourses"
                    :result-more-course-group-candidates="manualTimetableCourses"
                    :course-summary="automaticTimetableCourseSummary"
                    :course-sections="automaticTimetableAllCourseSections"
                    :selection-override="selectionOverridePayload() || {}"
                    :adopt-timetable-loading="personalTimetableSaving"
                    @close="closeAutomaticTimetable"
                    @additional-course-selection-change="setAutomaticTimetableAdditionalCourseKeys"
                    @adopt-timetable="adoptGeneratedTimetable"
                    @course-selection-change="setAutomaticTimetableCourseKeys"
                    @courses-selected="finishAutomaticTimetable"
                    @quality-criteria-selection-change="setAutomaticTimetableQualityCriterionKeys"
                    @restart="restartAutomaticTimetable"
                    @step-change="setAutomaticTimetableStep" />

                <div
                    v-if="automaticTimetableCriteriaReviewVisible"
                    class="students-timetable-v2-card-column">
                    <v-card rounded="lg" class="students-timetable-v2-card students-timetable-v2-restart-card">
                        <v-card-text class="students-timetable-v2-restart-card__content">
                            <v-btn
                                color="error"
                                variant="tonal"
                                size="large"
                                prepend-icon="mdi-restart"
                                @click="restartAutomaticTimetable">
                                Neustart
                            </v-btn>
                            <div class="students-timetable-v2-restart-card__navigation-actions">
                                <v-btn
                                    color="primary"
                                    variant="tonal"
                                    size="large"
                                    prepend-icon="mdi-arrow-left"
                                    class="students-timetable-v2-restart-card__primary-button"
                                    @click="goBackFromAutomaticCourseReview">
                                    Zurück
                                </v-btn>
                                <v-btn
                                    color="success"
                                    variant="tonal"
                                    size="large"
                                    append-icon="mdi-arrow-right"
                                    :disabled="!automaticSelectedCourseItems.length || automaticTimetableCoursesLoadingVisible"
                                    :loading="automaticTimetableCoursesLoadingVisible"
                                    @click="continueAutomaticCourseReview">
                                    Weiter
                                </v-btn>
                            </div>
                            <v-progress-linear
                                v-if="automaticTimetableCoursesLoadingVisible"
                                indeterminate
                                rounded
                                color="primary"
                                height="4"
                                class="students-timetable-v2-restart-card__loading"
                                title="Kursauswahl wird geladen"
                                aria-label="Kursauswahl wird geladen" />
                        </v-card-text>
                    </v-card>
                </div>
            </div>
        </section>
    </div>
</template>

<script>
import axios from 'axios'
import { mapWritableState } from 'pinia'
import { useStudentTimetablesUserStore } from '@/stores/studentsTimetables/StudentTimetablesUserStore'
import StudentTimetableEvaluationSettings from '../components/StudentTimetableEvaluationSettings.vue'
import StudentTimetablesNavigationDrawer from '../components/StudentTimetablesNavigationDrawer.vue'
import '../../../../../css/student.css'

const noAutomaticTimetableCourseValue = '__none'
const noAutomaticTimetableQualityCriteriaValue = '__none'
const automaticCourseBulkSelectionOptions = [
    { key: 'all', label: 'Alle' },
    { key: 'distance-learning', label: 'Nur Fernunterricht' },
    { key: 'without-distance-learning', label: 'Ohne Fernunterricht' },
]
const studentOverviewEditableSelectionKeys = ['religion', 'language', 'branch', 'arts_subject']
const studentOverviewSelectionOptionValues = {
    religion: ['ETH', 'Rev', 'Ris', 'Rk', 'Ror'],
    language: ['L', 'F', 'S'],
    branch: ['wirtschaftskundlich', 'gymnasial'],
    arts_subject: ['ME', 'BE'],
}

export default {
    components: {
        StudentTimetableEvaluationSettings,
        StudentTimetablesNavigationDrawer,
    },

    async beforeMount() {
        this.studentTimetablesStore = useStudentTimetablesUserStore()
        const isAuthenticated = await this.studentTimetablesStore.getCurrentUser()

        if (!isAuthenticated || !this.user) {
            this.$router.push('/homepage/students-timetables')
            return
        }

        await this.loadOverview()
    },

    data() {
        return {
            studentTimetablesStore: null,
            overviewLoading: true,
            showDrawer: false,
            showEvaluationSettings: false,
            showManualTimetable: false,
            expandedCourseSections: [],
            manualExpandedCourseSections: ['missing', 'proposed'],
            manualSelectedCourseKeys: [],
            manualSelectedCourseGroupKeys: [],
            manualTimetableMode: 'manual',
            personalTimetableDeleteDialogOpen: false,
            personalTimetableDeleting: false,
            personalTimetableSaving: false,
            pdfExporting: false,
            personalTimetableViewChangedMessageVisible: false,
            hiddenSavedTimetableCourseChipKeys: [],
            personalAdditionalTimetableEntryKeys: [],
            studentCoursePickerDialogOpen: false,
            selectedStudentCoursePickerTab: 'missing',
            selectedStudentCoursePickerMenuKey: '',
            activePersonalMoreCourseCardKey: '',
            activePersonalMoreCourseMenuKey: '',
            selectionOverride: {},
            selectionDialogOpen: false,
            selectionDraftKey: '',
            selectionDraftLabel: '',
            selectionDraftValue: null,
            selectedAutomaticReviewCourseKey: '',
            automaticOfferedCourseSelectionOverrides: {},
            automaticTimetableInitialRouteCourseKeys: [],
            automaticTimetableSelectedCourseKeys: [],
            automaticTimetableCourseKeysInitialized: false,
        }
    },

    computed: {
        ...mapWritableState(useStudentTimetablesUserStore, ['user', 'overview']),

        automaticTimetableStep() {
            const step = String(this.$route.query.automatic_timetable || '')

            return this.isAutomaticTimetableStep(step) ? step : 'criteria'
        },
        automaticTimetableCourseKeys() {
            return this.automaticTimetableCourseKeysInitialized
                ? this.automaticTimetableSelectedCourseKeys
                : this.automaticTimetableCourseKeysFromRoute()
        },
        automaticTimetableAdditionalCourseKeys() {
            return this.automaticTimetableAdditionalCourseKeysFromRoute()
        },
        automaticTimetableQualityCriterionKeys() {
            return []
        },
        automaticTimetableQualityCriteriaSelectionExplicit() {
            return true
        },
        automaticTimetableCoursePreselectionKeys() {
            return this.overviewDefaultSelectedCourseKeys
        },
        automaticTimetableCoursePreselectionResetAvailable() {
            const currentCourseKeys = JSON.stringify(this.sortedAutomaticTimetableCourseKeys(this.automaticTimetableCourseKeys))
            const preselectedCourseKeys = JSON.stringify(this.sortedAutomaticTimetableCourseKeys(this.automaticTimetableCoursePreselectionKeys))

            return currentCourseKeys !== preselectedCourseKeys
        },
        automaticTimetableDeselectedCourseGroupKeys() {
            return this.automaticSelectedCourseItems
                .flatMap(course => this.automaticOfferedCourseItemsForSelectedCourse(course)
                    .filter(offeredCourse => !this.automaticOfferedCourseSelected(offeredCourse))
                    .map(offeredCourse => offeredCourse.backendSelectionKey))
                .filter(Boolean)
                .filter((courseGroupKey, index, courseGroupKeys) => courseGroupKeys.indexOf(courseGroupKey) === index)
        },
        automaticCourseBulkSelectionOptions() {
            return automaticCourseBulkSelectionOptions
        },
        automaticTimetableCriteriaReviewVisible() {
            return this.automaticTimetableStep === 'criteria' && this.showEvaluationSettings
        },
        automaticTimetableCoursesLoadingVisible() {
            return this.automaticTimetableCriteriaReviewVisible && !this.overview
        },
        timetableActionsVisible() {
            return !this.overviewLoading
                && !this.showEvaluationSettings
                && !this.showManualTimetable
        },
        savedTimetableActionsVisible() {
            return false
        },
        weekdayLabel() {
            return new Intl.DateTimeFormat('de-AT', { weekday: 'long' }).format(new Date())
        },
        dateLabel() {
            return new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date())
        },
        selectionItems() {
            return Array.isArray(this.overview?.selection_items) ? this.overview.selection_items : []
        },
        studentReligionLabel() {
            const religionItem = this.selectionItems.find(item => String(item?.key || '') === 'religion')
            const storedReligion = String(this.overview?.student?.religion || this.user?.religion || '').trim()

            if (storedReligion) {
                return storedReligion
            }

            const religionMeta = String(religionItem?.meta || '').replace(/^Religion:\s*/i, '').trim()
            const religionLabel = String(religionMeta || religionItem?.value || '').trim()

            return religionLabel || null
        },
        selectionDraftOptions() {
            return this.selectionOptionsForKey(this.selectionDraftKey)
        },
        hasSelectionOverride() {
            return Object.keys(this.selectionOverride || {}).length > 0
        },
        courseSections() {
            return this.sortedCourseSections(this.overview?.course_sections)
        },
        overviewCourseSelectionSections() {
            const sections = [
                {
                    key: 'missing',
                    title: 'Negative Kurse',
                    color: 'error',
                    items: this.overviewCourseGroupItems('missing'),
                    selectedItems: this.overviewSelectedCourseItemsForGroup('missing'),
                    empty: 'Keine negativen Kurse gefunden.',
                    footer: 'Die Auswahl kann später noch verändert werden!',
                    selectable: true,
                    bulkSelectable: false,
                },
                {
                    key: 'planned',
                    title: 'Vorgesehene Kurse',
                    color: 'success',
                    items: this.overviewCourseGroupItems('planned'),
                    selectedItems: this.overviewSelectedCourseItemsForGroup('planned'),
                    empty: 'Keine vorgesehenen Kurse gefunden.',
                    footer: 'Die Auswahl kann später noch verändert werden!',
                    selectable: true,
                    bulkSelectable: true,
                },
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    color: 'info',
                    items: this.overviewCourseGroupItems('additional'),
                    selectedItems: this.overviewCourseGroupItems('additional'),
                    empty: 'Keine zusätzlichen Kurse gefunden.',
                    footer: 'Die Auswahl kann später hinzugefügt werden!',
                    selectable: false,
                    bulkSelectable: false,
                },
            ]

            return sections.filter(section => section.items.length > 0)
        },
        overviewCourseSelectionVisible() {
            return this.overviewCourseSelectionSections.length > 0
        },
        overviewDefaultSelectedCourseKeys() {
            const selectedCourseKeys = new Set()

            this.overviewCourseLimitDefaultCourseItems().forEach(({ course, courseGroup }) => {
                if (this.overviewCourseItemDefaultSelected(course, courseGroup)) {
                    selectedCourseKeys.add(this.overviewCourseSelectionKey(course, courseGroup))
                }
            })

            this.overviewDuplicateModuleCourseItemsForGroup('planned', selectedCourseKeys).forEach((courseItem) => {
                selectedCourseKeys.delete(this.overviewCourseSelectionKey(courseItem.course, courseItem.courseGroup))
            })

            let selectedCourseItems = this.overviewCourseLimitSelectedCourseItems(selectedCourseKeys)
            let selectedCourseCount = selectedCourseItems.length
            let selectedCourseHours = selectedCourseItems
                .reduce((hours, courseItem) => hours + this.courseHoursNumber(courseItem.course), 0)

            this.overviewRankedCourseLimitPreselectionGroup('planned', selectedCourseKeys).forEach((courseItem) => {
                if (selectedCourseCount <= 10 && selectedCourseHours <= 30) {
                    return
                }

                const courseKey = this.overviewCourseSelectionKey(courseItem.course, courseItem.courseGroup)

                if (!selectedCourseKeys.has(courseKey)) {
                    return
                }

                selectedCourseKeys.delete(courseKey)
                selectedCourseCount--
                selectedCourseHours -= this.courseHoursNumber(courseItem.course)
            })

            return this.overviewCourseLimitSelectedCourseItems(selectedCourseKeys)
                .map(courseItem => this.overviewCourseSelectionKey(courseItem.course, courseItem.courseGroup))
                .filter(Boolean)
        },
        overviewSelectedCourseLimitItems() {
            return this.overviewSelectedCourseItemsForKeys(this.automaticTimetableCourseKeys)
        },
        automaticSelectedCourseItems() {
            return this.overviewSelectedCourseLimitItems.map(course => this.automaticSelectedCourseItem(course))
        },
        selectedAutomaticReviewCourse() {
            return this.automaticSelectedCourseItems
                .find(course => course.selectionKey === this.selectedAutomaticReviewCourseKey)
                || null
        },
        selectedAutomaticReviewOfferedCourseItems() {
            if (!this.selectedAutomaticReviewCourse) {
                return []
            }

            return this.automaticOfferedCourseItemsForSelectedCourse(this.selectedAutomaticReviewCourse)
        },
        overviewSelectedCourseLimitSummary() {
            return this.courseItemsSummary(this.overviewSelectedCourseLimitItems)
        },
        overviewSelectedCourseLimitReached() {
            return this.overviewSelectedCourseLimitSummary.count >= 10 || this.overviewSelectedCourseLimitSummary.hours >= 30
        },
        heroCourseHistorySections() {
            const sections = [
                {
                    key: 'completed',
                    title: 'Abgeschlossene Kurse',
                    icon: 'mdi-school-outline',
                    color: 'success',
                    empty: 'Keine abgeschlossenen Kurse gefunden.',
                    items: this.courseHistoryItems('completed', this.overview?.completed_courses),
                },
                {
                    key: 'missing',
                    title: 'Negative Kurse',
                    icon: 'mdi-alert-circle-outline',
                    color: 'error',
                    empty: 'Keine negativen Kurse gefunden.',
                    items: this.courseHistoryItems('missing', this.overview?.missing_courses),
                },
            ]

            return sections.filter(section => section.key !== 'missing' || section.items.length > 0)
        },
        heroTimetableSelectionSummary() {
            return this.selectionItems
                .filter(item => String(item?.value || '').trim())
                .map(item => ({
                    ...item,
                    value: String(item.value || '').trim(),
                    label: String(item.label || '').trim(),
                }))
        },
        automaticTimetableCourseSelection() {
            const courseSelection = this.overview?.automatic_course_selection

            return courseSelection && typeof courseSelection === 'object' ? courseSelection : {}
        },
        automaticTimetableCourseSections() {
            return Array.isArray(this.automaticTimetableCourseSelection.sections)
                ? this.automaticTimetableCourseSelection.sections
                : []
        },
        automaticTimetableAllCourseSections() {
            const automaticAdditionalCourseSection = this.automaticTimetableCourseSections
                .find(section => String(section?.key || '') === 'additional')
            const additionalCourseSections = this.courseSections
                .filter(section => String(section?.key || '') === 'additional')
            const additionalCourses = Array.isArray(this.overview?.additional_courses)
                ? this.overview.additional_courses
                : []
            const additionalCourseItems = this.uniqueCourseItems([
                ...(Array.isArray(automaticAdditionalCourseSection?.items) ? automaticAdditionalCourseSection.items : []),
                ...additionalCourseSections.flatMap(section => Array.isArray(section?.items) ? section.items : []),
                ...additionalCourses,
            ])
            const mergedAdditionalCourseSection = additionalCourseItems.length
                ? {
                    ...(automaticAdditionalCourseSection || additionalCourseSections[0]),
                    key: 'additional',
                    title: automaticAdditionalCourseSection?.title || additionalCourseSections[0]?.title || 'Zusätzliche Kurse',
                    icon: automaticAdditionalCourseSection?.icon || additionalCourseSections[0]?.icon || 'mdi-plus-circle-outline',
                    color: automaticAdditionalCourseSection?.color || additionalCourseSections[0]?.color || 'warning',
                    empty: automaticAdditionalCourseSection?.empty || additionalCourseSections[0]?.empty || 'Keine zusätzlichen Kurse erkannt.',
                    items: additionalCourseItems,
                }
                : null
            const syntheticAdditionalCourseSections = additionalCourseSections.length || !additionalCourses.length
                ? []
                : [{
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    icon: 'mdi-plus-circle-outline',
                    color: 'warning',
                    items: additionalCourses,
                    empty: 'Keine zusätzlichen Kurse erkannt.',
                }]

            return [
                ...this.automaticTimetableCourseSections
                    .filter(section => String(section?.key || '') !== 'additional'),
                ...(mergedAdditionalCourseSection ? [mergedAdditionalCourseSection] : syntheticAdditionalCourseSections),
            ].map(section => ({
                ...section,
                items: this.sortedCourseItems(section?.items),
            }))
        },
        automaticTimetableSelectableCourses() {
            return this.sortedCourseItems(this.automaticTimetableCourseSelection.courses)
        },
        automaticTimetableCourseSummary() {
            return this.automaticTimetableCourseSelection
        },
        manualTimetableSelection() {
            const manualTimetable = this.overview?.manual_timetable

            return manualTimetable && typeof manualTimetable === 'object' ? manualTimetable : {}
        },
        manualTimetableTitle() {
            if (this.manualTimetableMode === 'personal') {
                return 'Mein Stundenplan'
            }

            if (this.manualTimetableMode === 'published') {
                return 'Von der Schule gespeicherter Stundenplan'
            }

            return 'Stundenplan'
        },
        publishedTimetableSelection() {
            const publishedTimetable = this.overview?.published_timetable

            return publishedTimetable && typeof publishedTimetable === 'object' ? publishedTimetable : {}
        },
        personalTimetableSelection() {
            const personalTimetable = this.overview?.personal_timetable

            return personalTimetable && typeof personalTimetable === 'object' ? personalTimetable : {}
        },
        hasPublishedTimetable() {
            return Boolean(this.publishedTimetableSelection.id)
        },
        hasPersonalTimetable() {
            return Boolean(this.personalTimetableSelection.id)
        },
        activeSavedTimetableSelection() {
            return this.manualTimetableMode === 'personal'
                ? this.personalTimetableSelection
                : this.publishedTimetableSelection
        },
        activeSavedTimetableCourseGroupKeys() {
            const courseGroupKeys = Array.isArray(this.activeSavedTimetableSelection.active_course_group_keys)
                ? this.activeSavedTimetableSelection.active_course_group_keys
                : this.activeSavedTimetableSelection.state?.activeCourseGroupFilterKeys

            return (Array.isArray(courseGroupKeys) ? courseGroupKeys : [])
                .map(courseGroupKey => String(courseGroupKey || ''))
                .filter(Boolean)
        },
        publishedTimetableCourseGroupKeys() {
            const courseGroupKeys = Array.isArray(this.publishedTimetableSelection.active_course_group_keys)
                ? this.publishedTimetableSelection.active_course_group_keys
                : this.publishedTimetableSelection.state?.activeCourseGroupFilterKeys

            return (Array.isArray(courseGroupKeys) ? courseGroupKeys : [])
                .map(courseGroupKey => String(courseGroupKey || ''))
                .filter(Boolean)
        },
        publishedTimetablePayload() {
            const timetable = this.activeSavedTimetableSelection.timetable

            return timetable && typeof timetable === 'object' ? timetable : {}
        },
        publishedTimetableVisible() {
            return ['personal', 'published'].includes(this.manualTimetableMode) && this.publishedTimetableSemesters.length > 0
        },
        personalTimetableEmptyVisible() {
            return this.manualTimetableMode === 'personal' && this.publishedTimetableSemesters.length === 0
        },
        personalTimetableViewChangeVisible() {
            return this.manualTimetableMode === 'personal' && this.personalTimetableViewChangedMessageVisible
        },
        personalTimetableCourseSelectionVisible() {
            return this.manualTimetableMode === 'personal' && this.savedTimetableCourseChipEntries.length > 0
        },
        personalMoreCoursesVisible() {
            return this.manualTimetableMode === 'personal' && this.studentCoursePickerAllCourseGroups.length > 0
        },
        personalMoreCourseCards() {
            return [
                { key: 'missing', tab: 'missing', title: 'Negative' },
                { key: 'planned', tab: 'proposed', title: 'Vorgesehene' },
                { key: 'additional', tab: 'additional', title: 'Zusätzliche' },
                { key: 'more', tab: 'open', title: 'Weitere' },
            ]
        },
        activePersonalMoreCourseCard() {
            return this.personalMoreCourseCards
                .find(card => card.key === this.activePersonalMoreCourseCardKey)
                || null
        },
        personalMoreCoursesTitle() {
            if (!this.activePersonalMoreCourseCard) {
                return 'Weitere Kurse'
            }

            return `Weitere Kurse: ${this.activePersonalMoreCourseCard.title}`
        },
        personalMoreCourseMenus() {
            if (!this.activePersonalMoreCourseCard) {
                return []
            }

            return this.personalMoreCourseMenusForCard(this.activePersonalMoreCourseCard)
        },
        selectedPersonalMoreCourseMenu() {
            return this.personalMoreCourseMenus
                .find(courseMenu => courseMenu.key === this.activePersonalMoreCourseMenuKey)
                || null
        },
        selectedPersonalMoreCourseEntryOptions() {
            if (!this.selectedPersonalMoreCourseMenu) {
                return []
            }

            const semester = this.selectedPersonalMoreCourseMenu.semester

            return this.selectedPersonalMoreCourseMenu.entries.map((entry) => {
                const hasBlockingOverlap = this.studentCoursePickerEntryHasBlockingOverlap(entry, semester)
                const hasRelatedOverlap = this.studentCoursePickerEntryHasRelatedOverlap(entry, semester, { hasBlockingOverlap })
                const isActive = this.studentCoursePickerEntryActive(entry)
                const isDisabled = hasBlockingOverlap && !isActive

                return {
                    ...entry,
                    hasBlockingOverlap,
                    hasRelatedOverlap,
                    isActive,
                    isDisabled,
                    distanceLearning: this.personalMoreCourseEntryIsDistanceLearning(entry),
                    color: this.studentCoursePickerEntryColor(entry, {
                        hasBlockingOverlap,
                        hasRelatedOverlap,
                        isActive,
                    }),
                }
            })
        },
        studentCoursePickerTabs() {
            return [
                { value: 'missing', label: 'Negative Kurse' },
                { value: 'proposed', label: 'Vorgesehene Kurse' },
                { value: 'additional', label: 'Zusätzliche Kurse' },
                { value: 'open', label: 'Offene Kurse' },
                { value: 'all', label: 'Alle' },
            ]
        },
        studentCoursePickerSemesterContexts() {
            return this.publishedTimetableSemesters.map((semester, index) => {
                const semesterValue = Number(semester?.value || semester?.semester || index + 1)

                return {
                    value: Number.isFinite(semesterValue) && semesterValue > 0 ? semesterValue : index + 1,
                    label: semester?.label || `Semester ${index + 1}`,
                    dateRangeLabel: semester?.date_range || '',
                }
            })
        },
        savedTimetableCourseChips() {
            const hiddenCourseChipKeys = new Set(this.hiddenSavedTimetableCourseChipKeys)

            return [
                ...this.savedTimetableCourseChipEntries
                    .filter(courseChip => !hiddenCourseChipKeys.has(courseChip.key)),
                ...this.personalAdditionalTimetableCourseChips,
            ]
        },
        personalAdditionalTimetableCourseChips() {
            const selectedEntryKeys = new Set(Array.isArray(this.personalAdditionalTimetableEntryKeys)
                ? this.personalAdditionalTimetableEntryKeys
                : [])

            return this.studentCoursePickerAllEntries
                .filter(entry => selectedEntryKeys.has(this.studentCoursePickerEntrySelectionKey(entry)))
                .map((entry) => {
                    const hours = this.personalAdditionalTimetableEntryHours(entry)

                    return {
                        key: this.studentCoursePickerEntrySelectionKey(entry),
                        title: entry.label,
                        label: [
                            entry.label,
                            entry.scheduleLabel,
                        ].filter(Boolean).join(' · '),
                        shortLabel: this.savedTimetableCourseChipShortLabel({ title: entry.label }),
                        hoursText: hours > 0 ? `${this.formatHours(hours)} Std.` : '',
                        hoursLabel: entry.scheduleLabel || '',
                        timeRanges: [],
                        detailLabels: [],
                        hasOverlap: false,
                        studentCourseType: entry.studentCourseType,
                        studentCourseBadge: '',
                        isPersonalAdditional: true,
                    }
                })
        },
        studentCoursePickerAllCourseGroups() {
            return this.manualTimetableCourseSections.flatMap((section) => {
                const sectionKey = String(section?.key || '')
                const courses = Array.isArray(section?.items) ? section.items : []

                return courses.flatMap((course) => this.manualCourseGroups(course).map((courseGroup) => ({
                    ...courseGroup,
                    course: courseGroup?.course || course?.code || course?.name || courseGroup?.subject || courseGroup?.title,
                    display_label: courseGroup?.display_label || courseGroup?.course || course?.code || course?.name,
                    semester: courseGroup?.semester || course?.semester || 1,
                    hours: courseGroup?.hours || course?.hours || course?.hours_per_week || 0,
                    studentCourseType: sectionKey,
                    sourceColor: section?.color || 'primary',
                })))
            })
        },
        studentCoursePickerCourseGroups() {
            return this.studentCoursePickerAllCourseGroups
                .filter(courseGroup => this.studentCoursePickerCourseGroupMatchesTab(courseGroup))
        },
        studentCoursePickerAllEntries() {
            const entries = new Map()

            this.studentCoursePickerAllCourseGroups.forEach((courseGroup) => {
                const semester = Number(courseGroup?.semester || 1)
                const label = this.studentCoursePickerEntryLabel(courseGroup)
                const entryKey = `semester-${semester}-${label.toLocaleUpperCase('de-AT')}`

                if (!entries.has(entryKey)) {
                    entries.set(entryKey, {
                        key: entryKey,
                        label,
                        semester,
                        courseGroups: [],
                        courseGroupKeys: [],
                        studentCourseType: courseGroup.studentCourseType,
                        sourceColor: courseGroup.sourceColor,
                    })
                }

                const entry = entries.get(entryKey)
                entry.courseGroups.push(courseGroup)
                if (courseGroup?.key) {
                    entry.courseGroupKeys.push(String(courseGroup.key))
                }
            })

            return Array.from(entries.values())
                .map(entry => ({
                    ...entry,
                    courseGroupKeys: [...new Set(entry.courseGroupKeys)],
                    courseChipKey: this.studentCoursePickerEntryCourseChipKey(entry),
                    selectionKey: this.studentCoursePickerEntrySelectionKey(entry),
                    scheduleLabel: this.studentCoursePickerEntryScheduleLabel(entry),
                }))
        },
        studentCoursePickerCourseMenus() {
            return this.courseMenusForStudentCoursePickerGroups(this.studentCoursePickerCourseGroups)
        },
        selectedStudentCoursePickerMenu() {
            return this.studentCoursePickerCourseMenus
                .find(courseMenu => courseMenu.key === this.selectedStudentCoursePickerMenuKey)
                || null
        },
        selectedStudentCoursePickerEntryOptions() {
            if (!this.selectedStudentCoursePickerMenu) {
                return []
            }

            const semester = this.selectedStudentCoursePickerMenu.semester

            return this.selectedStudentCoursePickerMenu.entries.map((entry) => {
                const hasBlockingOverlap = this.studentCoursePickerEntryHasBlockingOverlap(entry, semester)
                const hasRelatedOverlap = this.studentCoursePickerEntryHasRelatedOverlap(entry, semester, { hasBlockingOverlap })
                const isActive = this.studentCoursePickerEntryActive(entry)
                const isDisabled = hasBlockingOverlap && !isActive

                return {
                    ...entry,
                    hasBlockingOverlap,
                    hasRelatedOverlap,
                    isActive,
                    isDisabled,
                    color: this.studentCoursePickerEntryColor(entry, {
                        hasBlockingOverlap,
                        hasRelatedOverlap,
                        isActive,
                    }),
                }
            })
        },
        savedTimetableCourseChipEntries() {
            const courseChips = new Map()

            this.publishedTimetableSemesters.forEach((semester) => {
                this.publishedTimetableSemesterWeeks(semester).forEach((week) => {
                    this.publishedTimetableWeekHours(week).forEach((hour) => {
                        this.publishedTimetableHourCells(hour).forEach((cell, cellIndex) => {
                            const rawCourses = this.publishedTimetableCellRawCourses(cell)

                            rawCourses.forEach((course) => {
                                const courseKey = this.savedTimetableCourseChipKey(course)

                                if (!courseKey) {
                                    return
                                }

                                if (!courseChips.has(courseKey)) {
                                    courseChips.set(courseKey, {
                                        key: courseKey,
                                        title: this.savedTimetableCourseChipTitle(course),
                                        timeRanges: [],
                                        detailLabels: [],
                                        hasOverlap: false,
                                        studentCourseType: String(course?.student_course_type || ''),
                                        studentCourseBadge: String(course?.student_course_badge || ''),
                                    })
                                }

                                const courseChip = courseChips.get(courseKey)
                                const timeRange = this.savedTimetableCourseTimeRange(hour, cellIndex)
                                const detailLabel = this.savedTimetableCourseDetailLabel(course)

                                if (timeRange && !this.savedTimetableCourseTimeRangeExists(courseChip.timeRanges, timeRange)) {
                                    courseChip.timeRanges.push(timeRange)
                                }

                                if (detailLabel && !courseChip.detailLabels.includes(detailLabel)) {
                                    courseChip.detailLabels.push(detailLabel)
                                }

                                courseChip.hasOverlap = courseChip.hasOverlap
                                    || rawCourses.length > 1
                                    || ['warning', 'conflict'].includes(String(cell?.status || ''))

                                if (!courseChip.studentCourseType && course?.student_course_type) {
                                    courseChip.studentCourseType = String(course.student_course_type)
                                }

                                if (!courseChip.studentCourseBadge && course?.student_course_badge) {
                                    courseChip.studentCourseBadge = String(course.student_course_badge)
                                }
                            })
                        })
                    })
                })
            })

            return Array.from(courseChips.values())
                .map(courseChip => ({
                    ...courseChip,
                    shortLabel: this.savedTimetableCourseChipShortLabel(courseChip),
                    hoursText: this.savedTimetableCourseChipHoursText(courseChip),
                    hoursLabel: this.savedTimetableCourseChipHoursLabel(courseChip),
                    label: this.savedTimetableCourseChipLabel(courseChip),
                }))
                .sort((leftCourseChip, rightCourseChip) => leftCourseChip.title.localeCompare(rightCourseChip.title, 'de-AT', {
                    numeric: true,
                    sensitivity: 'base',
                }))
        },
        publishedTimetableSubtitle() {
            return [
                this.publishedTimetablePayload.student,
                this.publishedTimetablePayload.schoolyear,
                this.publishedTimetablePayload.generated_at,
            ]
                .filter(Boolean)
                .join(' · ')
        },
        publishedTimetableWeekdays() {
            const weekdays = Array.isArray(this.publishedTimetablePayload.weekdays)
                ? this.publishedTimetablePayload.weekdays
                : []

            return weekdays
                .map((weekday, index) => ({
                    label: String(weekday?.label || index + 1),
                }))
                .filter(weekday => weekday.label)
        },
        publishedTimetableSemesters() {
            return Array.isArray(this.publishedTimetablePayload.semesters)
                ? this.publishedTimetablePayload.semesters
                : []
        },
        manualTimetableCourseSections() {
            return this.sortedCourseSections(this.manualTimetableSelection.sections)
        },
        manualTimetableCourses() {
            return this.manualTimetableCourseSections
                .flatMap(section => Array.isArray(section.items) ? section.items : [])
        },
        manualSelectedCourses() {
            const selectedCourseKeys = new Set(this.manualSelectedCourseKeys)

            return this.manualTimetableCourses
                .filter(course => selectedCourseKeys.has(this.manualCourseKey(course)))
        },
        manualSelectedCourseGroups() {
            const courseGroups = new Map()
            const selectedCourseGroupKeys = new Set(this.manualSelectedCourseGroupKeys)
            const shouldFilterCourseGroups = selectedCourseGroupKeys.size > 0

            this.manualSelectedCourses.forEach((course) => {
                this.manualCourseGroups(course).forEach((courseGroup) => {
                    const courseGroupKey = this.manualCourseGroupKey(courseGroup)

                    if (shouldFilterCourseGroups && !selectedCourseGroupKeys.has(courseGroupKey)) {
                        return
                    }

                    if (!courseGroups.has(courseGroupKey)) {
                        courseGroups.set(courseGroupKey, courseGroup)
                    }
                })
            })

            return Array.from(courseGroups.values())
        },
        manualTimetableWeekdays() {
            const selectedWeekdays = new Set(
                this.manualSelectedCourseGroups
                    .map(courseGroup => Number(courseGroup?.weekday || 0))
                    .filter(weekday => weekday >= 1 && weekday <= 6),
            )
            const weekdayLabels = [
                { value: 1, label: 'Mo' },
                { value: 2, label: 'Di' },
                { value: 3, label: 'Mi' },
                { value: 4, label: 'Do' },
                { value: 5, label: 'Fr' },
                { value: 6, label: 'Sa' },
            ]

            return weekdayLabels.filter(weekday => weekday.value < 6 || selectedWeekdays.has(6))
        },
        manualTimetableHours() {
            const hours = new Map()

            this.manualSelectedCourseGroups.forEach((courseGroup) => {
                const hour = Number(courseGroup?.hour || 0)

                if (!Number.isFinite(hour) || hour <= 0) {
                    return
                }

                if (!hours.has(hour)) {
                    hours.set(hour, {
                        value: hour,
                        hourLabel: `${hour}.`,
                        timeFrom: this.manualTimetableHourTimeFrom(courseGroup, hour),
                        timeUntil: this.manualTimetableHourTimeUntil(courseGroup, hour),
                    })
                }
            })

            return Array.from(hours.values()).sort((firstHour, secondHour) => firstHour.value - secondHour.value)
        },
    },

    watch: {
        '$route.query.automatic_timetable': {
            immediate: true,
            handler(step) {
                this.showEvaluationSettings = this.isAutomaticTimetableStep(step)

                if (String(step || '') === 'criteria') {
                    this.removeAutomaticTimetableCriteriaQuery()
                }
            },
        },
        '$route.query.manual_timetable': {
            immediate: true,
            handler(value) {
                this.applyManualTimetableRoute(value)
            },
        },
    },

    methods: {
        async loadOverview() {
            this.overviewLoading = true

            try {
                await this.studentTimetablesStore.loadOverview()
                this.rememberAutomaticTimetableRouteCourseKeys()
                this.syncAutomaticTimetableCourseKeysFromRoute()
                this.canonicalizeAutomaticTimetableCourseQuery()
                this.syncSelectionOverrideFromOverview()
                this.applyCurrentManualTimetableSelection()
            } finally {
                this.overviewLoading = false
            }
        },
        async handleLogout() {
            this.showDrawer = false
            await this.studentTimetablesStore.logout()
            this.$router.push('/homepage/students-timetables')
        },
        openAutomaticTimetable() {
            this.personalTimetableViewChangedMessageVisible = false
            this.showEvaluationSettings = true
            this.showManualTimetable = false
            this.setAutomaticTimetableStep('criteria')
        },
        closeAutomaticTimetable() {
            this.showEvaluationSettings = false
            this.clearAutomaticTimetableStep()
        },
        finishAutomaticTimetable() {
            this.showEvaluationSettings = false
            this.clearAutomaticTimetableStep()
        },
        setAutomaticTimetableStep(step) {
            if (!this.isAutomaticTimetableStep(step)) {
                return
            }

            const query = { ...this.$route.query }
            delete query.manual_timetable
            delete query.automatic_timetable_courses
            delete query.automatic_timetable_criteria
            if (step !== 'result') {
                delete query.automatic_timetable_additional_courses
            }
            query.automatic_timetable = step

            if (
                this.$route.query.automatic_timetable === step
                && !Object.prototype.hasOwnProperty.call(this.$route.query, 'automatic_timetable_courses')
                && !Object.prototype.hasOwnProperty.call(this.$route.query, 'automatic_timetable_criteria')
                && !Object.prototype.hasOwnProperty.call(this.$route.query, 'manual_timetable')
            ) {
                return
            }

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        setAutomaticTimetableCourseKeys(courseKeys) {
            const selectedCourseKeys = this.normalizedOverviewSelectionKeys(courseKeys)

            const currentCourseKeys = this.automaticTimetableCourseKeys
            if (JSON.stringify(currentCourseKeys) === JSON.stringify(selectedCourseKeys)) {
                return
            }

            this.automaticTimetableSelectedCourseKeys = selectedCourseKeys
            this.automaticTimetableCourseKeysInitialized = true

            const query = { ...this.$route.query }
            delete query.automatic_timetable_courses
            delete query.automatic_timetable_criteria

            if (
                !Object.prototype.hasOwnProperty.call(this.$route.query, 'automatic_timetable_courses')
                && !Object.prototype.hasOwnProperty.call(this.$route.query, 'automatic_timetable_criteria')
            ) {
                return
            }

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        setAutomaticTimetableAdditionalCourseKeys(courseKeys) {
            const selectedCourseKeys = this.normalizedOverviewSelectionKeys(courseKeys)
            const currentCourseKeys = this.automaticTimetableAdditionalCourseKeys

            if (JSON.stringify(currentCourseKeys) === JSON.stringify(selectedCourseKeys)) {
                return
            }

            const query = { ...this.$route.query }
            delete query.automatic_timetable_additional_courses

            if (selectedCourseKeys.length) {
                query.automatic_timetable_additional_courses = selectedCourseKeys
            }

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        resetAutomaticTimetableCoursePreselection() {
            if (!this.automaticTimetableCoursePreselectionResetAvailable) {
                return
            }

            this.setAutomaticTimetableCourseKeys(this.automaticTimetableCoursePreselectionKeys)
        },
        automaticSelectedCourseItem(course) {
            const hours = this.courseHoursNumber(course)
            const label = this.overviewCourseItemDisplayLabel(course)

            return {
                ...course,
                selectionKey: this.overviewCourseSelectionKey(course, course?.courseGroup),
                label,
                meta: this.overviewCourseItemMeta(course) || (hours ? `${this.formatHours(hours)} Std.` : ''),
                distanceLearning: this.automaticCourseItemIsDistanceLearning(course),
            }
        },
        overviewCourseItemDisplayLabel(course) {
            const explicitLabel = String(course?.label || '').trim()

            if (!explicitLabel) {
                return this.overviewCourseItemLabel(course)
            }

            const normalizedLabelParts = explicitLabel.split(' - ').map((part) => part.trim()).filter(Boolean)
            if (normalizedLabelParts.length >= 2 && normalizedLabelParts[0] === normalizedLabelParts[1]) {
                return normalizedLabelParts[0]
            }

            return explicitLabel
        },
        applyAutomaticCourseBulkSelection(optionKey) {
            const offeredCourseSelections = { ...this.automaticOfferedCourseSelectionOverrides }

            this.automaticSelectedCourseItems
                .flatMap(course => this.automaticOfferedCourseItemsForSelectedCourse(course))
                .forEach((course) => {
                    if (this.automaticOfferedCourseExcludedByBulkSelection(optionKey, course)) {
                        offeredCourseSelections[course.selectionKey] = false

                        return
                    }

                    delete offeredCourseSelections[course.selectionKey]
                })

            this.automaticOfferedCourseSelectionOverrides = offeredCourseSelections
        },
        automaticOfferedCourseExcludedByBulkSelection(optionKey, course) {
            if (optionKey === 'all') {
                return false
            }

            if (optionKey === 'distance-learning') {
                return course?.distanceLearning !== true
            }

            if (optionKey === 'without-distance-learning') {
                return course?.distanceLearning === true
            }

            return false
        },
        selectAutomaticReviewCourse(course) {
            const selectionKey = course?.selectionKey || ''

            this.selectedAutomaticReviewCourseKey = this.selectedAutomaticReviewCourseKey === selectionKey
                ? ''
                : selectionKey
        },
        automaticReviewCourseActive(course) {
            return this.selectedAutomaticReviewCourse?.selectionKey === course?.selectionKey
        },
        automaticOfferedCourseSelected(course) {
            return this.automaticOfferedCourseSelectionOverrides[course?.selectionKey] !== false
        },
        toggleAutomaticOfferedCourse(course) {
            const selectionKey = course?.selectionKey || ''
            if (!selectionKey) {
                return
            }

            const offeredCourseSelections = { ...this.automaticOfferedCourseSelectionOverrides }

            if (this.automaticOfferedCourseSelected(course)) {
                offeredCourseSelections[selectionKey] = false
            } else {
                delete offeredCourseSelections[selectionKey]
            }

            this.automaticOfferedCourseSelectionOverrides = offeredCourseSelections
        },
        automaticOfferedCourseItemsAllDeselected(course) {
            const offeredCourses = this.automaticOfferedCourseItemsForSelectedCourse(course)

            return offeredCourses.length > 0 && offeredCourses.every(offeredCourse => !this.automaticOfferedCourseSelected(offeredCourse))
        },
        automaticOfferedCourseItemsPartlySelected(course) {
            const offeredCourses = this.automaticOfferedCourseItemsForSelectedCourse(course)
            if (offeredCourses.length <= 1) {
                return false
            }

            const selectedOfferedCourseCount = offeredCourses
                .filter(offeredCourse => this.automaticOfferedCourseSelected(offeredCourse))
                .length

            return selectedOfferedCourseCount > 0 && selectedOfferedCourseCount < offeredCourses.length
        },
        automaticOfferedCourseItemsForSelectedCourse(course) {
            const courseGroups = this.automaticCourseGroupsForSelectedCourse(course)
            const offeredCourseItems = courseGroups
                .map(courseGroup => this.automaticOfferedCourseGroupItem(courseGroup, course))
                .sort((firstCourse, secondCourse) => this.compareAutomaticOfferedCourseItems(firstCourse, secondCourse))

            return this.uniqueAutomaticOfferedCourseItems(offeredCourseItems, course)
        },
        automaticCourseGroupsForSelectedCourse(course) {
            const directCourseGroups = Array.isArray(course?.course_groups) ? course.course_groups : []

            if (directCourseGroups.length) {
                return directCourseGroups
            }

            const manualTimetableCourses = Array.isArray(this.manualTimetableCourses)
                ? this.manualTimetableCourses
                : []

            return manualTimetableCourses
                .filter(manualCourse => this.automaticCoursesMatch(manualCourse, course))
                .flatMap(manualCourse => this.manualCourseGroups(manualCourse))
        },
        automaticCoursesMatch(manualCourse, selectedCourse) {
            const manualCourseCodes = this.automaticCourseAliases(manualCourse)
            const selectedCourseCodes = this.automaticCourseAliases(selectedCourse)

            if (!manualCourseCodes.length || !selectedCourseCodes.length) {
                return false
            }

            return selectedCourseCodes.some(selectedCourseCode => manualCourseCodes.includes(selectedCourseCode))
        },
        automaticCourseAliases(course) {
            return [
                course?.code,
                course?.ttCode,
                ...(Array.isArray(course?.ttCodes) ? course.ttCodes : []),
                course?.label,
            ]
                .flatMap(courseCode => String(courseCode || '').split('/'))
                .map(courseCode => this.normalizedCourseCode(courseCode))
                .filter(Boolean)
                .filter((courseCode, index, courseCodes) => courseCodes.indexOf(courseCode) === index)
        },
        automaticOfferedCourseGroupItem(courseGroup, selectedCourse) {
            const code = String(courseGroup?.course || courseGroup?.subject || courseGroup?.title || selectedCourse?.code || '').trim()
            const scheduleLabel = this.manualCourseGroupScheduleLabel(courseGroup)
            const groupSelectionLabel = this.automaticOfferedCourseGroupSelectionLabel(courseGroup)

            return {
                key: this.manualCourseGroupKey(courseGroup),
                selectionKey: [
                    selectedCourse?.selectionKey || this.overviewCourseSelectionKey(selectedCourse, selectedCourse?.courseGroup),
                    this.manualCourseGroupKey(courseGroup),
                ].filter(Boolean).join('::'),
                code,
                name: this.automaticOfferedCourseName(courseGroup, code),
                groupSelectionLabel,
                backendSelectionKey: [
                    selectedCourse?.key || selectedCourse?.selectionKey || this.overviewCourseSelectionKey(selectedCourse, selectedCourse?.courseGroup),
                    groupSelectionLabel,
                ].filter(Boolean).join('|'),
                weekday: Number(courseGroup?.weekday || 0) || null,
                hour: Number(courseGroup?.hour || 0) || null,
                courseGroups: [courseGroup],
                scheduleLabel,
                recurrenceLabel: this.manualCourseGroupWeekMarker(courseGroup).replace(/[()]/gu, '').trim(),
                distanceLearning: this.automaticOfferedCourseIsDistanceLearning([courseGroup], selectedCourse),
            }
        },
        uniqueAutomaticOfferedCourseItems(courseItems, selectedCourse) {
            const courseItemsByIdentity = new Map()
            const offeredCourseItems = Array.isArray(courseItems) ? courseItems : []

            offeredCourseItems.forEach((courseItem) => {
                const identityKey = this.automaticOfferedCourseIdentityKey(courseItem)
                const existingCourseItem = courseItemsByIdentity.get(identityKey)

                if (!existingCourseItem) {
                    courseItemsByIdentity.set(identityKey, { ...courseItem })

                    return
                }

                const courseGroups = [
                    ...(Array.isArray(existingCourseItem.courseGroups) ? existingCourseItem.courseGroups : []),
                    ...(Array.isArray(courseItem.courseGroups) ? courseItem.courseGroups : []),
                ]

                courseItemsByIdentity.set(identityKey, {
                    ...existingCourseItem,
                    courseGroups,
                    scheduleLabel: this.automaticMergedLabelList(existingCourseItem.scheduleLabel, courseItem.scheduleLabel),
                    recurrenceLabel: this.automaticMergedLabelList(existingCourseItem.recurrenceLabel, courseItem.recurrenceLabel),
                    distanceLearning: this.automaticOfferedCourseIsDistanceLearning(courseGroups, selectedCourse),
                })
            })

            return Array.from(courseItemsByIdentity.values())
        },
        automaticOfferedCourseIdentityKey(courseItem) {
            return [
                this.normalizedCourseCode(courseItem?.code),
                this.normalizedCourseCode(courseItem?.name),
            ].join('|')
        },
        automaticMergedLabelList(firstLabel, secondLabel) {
            return [
                ...String(firstLabel || '').split(','),
                ...String(secondLabel || '').split(','),
            ]
                .map(label => label.trim())
                .filter(Boolean)
                .filter((label, index, labels) => labels.indexOf(label) === index)
                .join(', ')
        },
        automaticOfferedCourseName(courseGroup, code) {
            return String(
                courseGroup?.display_label
                || courseGroup?.class_name
                || code
                || courseGroup?.title
                || 'Ohne Bezeichnung',
            ).trim()
        },
        automaticOfferedCourseGroupSelectionLabel(courseGroup) {
            return String(
                courseGroup?.class_name
                || courseGroup?.display_label
                || courseGroup?.title
                || courseGroup?.course
                || courseGroup?.subject
                || '',
            ).trim()
        },
        automaticOfferedCourseIsDistanceLearning(courseGroups, selectedCourse) {
            const requiredSlotCount = Math.max(1, Math.round(this.courseHoursNumber(selectedCourse)))
            const scheduledWeeklyLoad = this.courseGroupsScheduledWeeklyLoad(courseGroups)

            return requiredSlotCount >= 2
                && scheduledWeeklyLoad > 0
                && Math.abs((scheduledWeeklyLoad * 2) - requiredSlotCount) < 0.001
        },
        compareAutomaticOfferedCourseItems(firstCourse, secondCourse) {
            const firstSlot = Number(firstCourse?.weekday || 0) * 100 + Number(firstCourse?.hour || 0)
            const secondSlot = Number(secondCourse?.weekday || 0) * 100 + Number(secondCourse?.hour || 0)

            if (firstSlot !== secondSlot) {
                return firstSlot - secondSlot
            }

            return String(firstCourse?.name || firstCourse?.code || '').localeCompare(
                String(secondCourse?.name || secondCourse?.code || ''),
                'de-AT',
                {
                    numeric: true,
                    sensitivity: 'base',
                },
            )
        },
        automaticOfferedCourseCodeVisible(course) {
            const code = String(course?.code || '').trim()
            const name = String(course?.name || '').trim()

            return Boolean(code && name && code !== name && !name.startsWith(`${code} -`))
        },
        restartAutomaticTimetable() {
            this.personalTimetableViewChangedMessageVisible = false
            this.showEvaluationSettings = true
            this.showManualTimetable = false
            this.selectedAutomaticReviewCourseKey = ''
            this.automaticOfferedCourseSelectionOverrides = {}
            this.automaticTimetableInitialRouteCourseKeys = [noAutomaticTimetableCourseValue]
            const defaultCourseKeys = this.automaticTimetableDefaultCourseKeys()

            this.automaticTimetableInitialRouteCourseKeys = []
            this.automaticTimetableSelectedCourseKeys = defaultCourseKeys
            this.automaticTimetableCourseKeysInitialized = true
            this.$router.push({
                path: this.$route.path,
                query: {},
            })
        },
        goBackFromAutomaticCourseReview() {
            this.closeAutomaticTimetable()
        },
        continueAutomaticCourseReview() {
            this.setAutomaticTimetableStep('result')
        },
        removeAutomaticTimetableCriteriaQuery() {
            if (!Object.prototype.hasOwnProperty.call(this.$route.query, 'automatic_timetable_criteria')) {
                return
            }

            const query = { ...this.$route.query }
            delete query.automatic_timetable_criteria

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        setAutomaticTimetableQualityCriterionKeys(criterionKeys) {
            this.removeAutomaticTimetableCriteriaQuery()
        },
        clearAutomaticTimetableStep() {
            if (
                !this.$route.query.automatic_timetable
                && !this.$route.query.automatic_timetable_courses
                && !this.$route.query.automatic_timetable_additional_courses
                && !this.$route.query.automatic_timetable_criteria
            ) {
                return
            }

            const query = { ...this.$route.query }
            delete query.automatic_timetable
            delete query.automatic_timetable_courses
            delete query.automatic_timetable_additional_courses
            delete query.automatic_timetable_criteria

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        isAutomaticTimetableStep(step) {
            return ['criteria', 'courses', 'result'].includes(String(step || ''))
        },
        openManualTimetable() {
            this.personalTimetableViewChangedMessageVisible = false
            this.showManualTimetable = true
            this.showEvaluationSettings = false
            this.manualTimetableMode = 'manual'
            this.manualSelectedCourseKeys = []
            this.manualSelectedCourseGroupKeys = []
            this.ensureManualTimetableDefaultSelection()

            const query = { ...this.$route.query }
            delete query.automatic_timetable
            delete query.automatic_timetable_courses
            delete query.automatic_timetable_additional_courses
            delete query.automatic_timetable_criteria
            query.manual_timetable = '1'

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        openPublishedTimetable() {
            if (!this.hasPublishedTimetable) {
                return
            }

            this.personalTimetableViewChangedMessageVisible = false
            this.showManualTimetable = true
            this.showEvaluationSettings = false
            this.manualTimetableMode = 'published'
            this.applyPublishedTimetableSelection()

            const query = { ...this.$route.query }
            delete query.automatic_timetable
            delete query.automatic_timetable_courses
            delete query.automatic_timetable_additional_courses
            delete query.automatic_timetable_criteria
            query.manual_timetable = 'published'

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        openPersonalTimetable() {
            this.personalTimetableViewChangedMessageVisible = false
            this.showManualTimetable = true
            this.showEvaluationSettings = false
            this.manualTimetableMode = 'personal'
            this.applySavedTimetableSelection()

            const query = { ...this.$route.query }
            delete query.automatic_timetable
            delete query.automatic_timetable_courses
            delete query.automatic_timetable_additional_courses
            delete query.automatic_timetable_criteria
            query.manual_timetable = 'personal'

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        async adoptPublishedTimetable() {
            if (!this.hasPublishedTimetable || this.personalTimetableSaving) {
                return
            }

            this.personalTimetableSaving = true

            try {
                const saved = await this.studentTimetablesStore.adoptPublishedTimetable()

                if (!saved) {
                    return
                }

                this.openAdoptedPersonalTimetable()
            } finally {
                this.personalTimetableSaving = false
            }
        },
        async adoptGeneratedTimetable(payload) {
            if (this.personalTimetableSaving) {
                return
            }

            this.personalTimetableSaving = true

            try {
                const saved = await this.studentTimetablesStore.adoptPublishedTimetable(payload)

                if (!saved) {
                    return
                }

                this.openAdoptedPersonalTimetable()
            } finally {
                this.personalTimetableSaving = false
            }
        },
        async savePersonalVisibleTimetable() {
            if (this.personalTimetableSaving || !this.publishedTimetableVisible) {
                return
            }

            this.personalTimetableSaving = true

            try {
                const saved = await this.studentTimetablesStore.adoptPublishedTimetable(this.personalVisibleTimetablePayload())

                if (!saved) {
                    return
                }

                this.hiddenSavedTimetableCourseChipKeys = []
                this.personalAdditionalTimetableEntryKeys = []
                this.openAdoptedPersonalTimetable()
            } finally {
                this.personalTimetableSaving = false
            }
        },
        async downloadPersonalTimetablePdf() {
            if (this.pdfExporting || !this.publishedTimetableVisible) {
                return
            }

            this.pdfExporting = true

            try {
                const response = await axios.post(
                    '/api/homepage/students-timetables/overview/pdf',
                    this.personalVisibleTimetable(),
                    { responseType: 'blob' },
                )
                const fileName = this.fileNameFromContentDisposition(response?.headers?.['content-disposition'])
                    || 'stundenplan.pdf'
                const blob = response?.data instanceof Blob
                    ? response.data
                    : new Blob([response?.data], { type: 'application/pdf' })

                this.downloadBlob(blob, fileName)
            } finally {
                this.pdfExporting = false
            }
        },
        personalVisibleTimetablePayload() {
            return {
                active_course_group_keys: this.activeSavedTimetableCourseGroupKeys,
                timetable: this.personalVisibleTimetable(),
                state: this.personalVisibleTimetableState(),
            }
        },
        personalVisibleTimetable() {
            return {
                title: this.publishedTimetablePayload.title || this.manualTimetableTitle || 'Stundenplan',
                subtitle: this.publishedTimetablePayload.subtitle || '',
                schoolyear: this.publishedTimetablePayload.schoolyear || '',
                student: this.publishedTimetablePayload.student || this.publishedTimetableSubtitle || '',
                generated_at: new Intl.DateTimeFormat('de-AT', {
                    dateStyle: 'short',
                    timeStyle: 'short',
                }).format(new Date()),
                weekdays: this.publishedTimetableWeekdays.map(weekday => ({
                    label: String(weekday?.label || ''),
                })),
                semesters: this.publishedTimetableSemesters.map(semester => this.personalVisibleTimetableSemester(semester)),
            }
        },
        personalVisibleTimetableSemester(semester) {
            return {
                label: String(semester?.label || 'Semester'),
                date_range: String(semester?.date_range || ''),
                weeks: this.publishedTimetableSemesterWeeks(semester).map(week => this.personalVisibleTimetableWeek(semester, week)),
            }
        },
        personalVisibleTimetableWeek(semester, week) {
            return {
                label: String(week?.label || ''),
                hours: this.publishedTimetableWeekHours(week).map(hour => this.personalVisibleTimetableHour(semester, hour)),
            }
        },
        personalVisibleTimetableHour(semester, hour) {
            return {
                hour: Number(hour?.hour || 0),
                from: String(hour?.from || ''),
                until: String(hour?.until || ''),
                cells: this.publishedTimetableHourCells(hour)
                    .map((cell, cellIndex) => this.personalVisibleTimetableCell(cell, semester, hour, cellIndex)),
            }
        },
        personalVisibleTimetableCell(cell, semester, hour, cellIndex) {
            const courses = this.publishedTimetableCellCourses(cell, semester, hour, cellIndex)
                .map(course => this.personalVisibleTimetableCourse(course, semester, hour, cellIndex))
            const markers = this.publishedTimetableCellMarkers(cell)
                .map(marker => ({
                    label: String(marker?.label || marker?.title || '-'),
                    title: String(marker?.title || marker?.label || ''),
                }))

            return {
                status: this.personalVisibleTimetableCellStatus(cell, courses, markers),
                courses,
                markers,
            }
        },
        personalVisibleTimetableCellStatus(cell, courses, markers) {
            if (String(cell?.status || '') === 'conflict') {
                return 'conflict'
            }

            if (courses.length > 1 || markers.length > 0 || String(cell?.status || '') === 'warning') {
                return 'warning'
            }

            return courses.length > 0 ? 'filled' : 'empty'
        },
        personalVisibleTimetableCourse(course, semester, hour, cellIndex) {
            const recurrenceLabel = this.publishedTimetableCourseRecurrenceLabel(course, semester, hour, cellIndex)

            return {
                label: String(course?.label || '-'),
                details: [
                    recurrenceLabel,
                    this.publishedTimetableCourseDetails(course),
                ].filter(Boolean).join(' · '),
                dates: Array.isArray(course?.dates) ? course.dates : [],
                is_fu: this.publishedTimetableCourseIsDistanceLearning(course, semester, hour, cellIndex),
                recurrence_label: recurrenceLabel,
                recurrence_interval: this.publishedTimetableRecurrenceIntervalFromLabel(recurrenceLabel),
                student_course_type: String(course?.student_course_type || ''),
                student_course_badge: String(course?.student_course_badge || ''),
            }
        },
        personalVisibleTimetableState() {
            return {
                ...(this.activeSavedTimetableSelection.state || {}),
                activeCourseGroupFilterKeys: this.activeSavedTimetableCourseGroupKeys,
                source: 'student-personal-timetable',
            }
        },
        publishedTimetableRecurrenceIntervalFromLabel(label) {
            const match = String(label || '').match(/(\d+)\s*-\s*wöchig/iu)
            const interval = match ? Number(match[1]) : null

            return Number.isInteger(interval) && interval > 1 ? interval : null
        },
        fileNameFromContentDisposition(contentDisposition) {
            const utf8Match = String(contentDisposition || '').match(/filename\*=UTF-8''([^;]+)/iu)

            if (utf8Match) {
                return decodeURIComponent(utf8Match[1])
            }

            const quotedMatch = String(contentDisposition || '').match(/filename="([^"]+)"/iu)

            return quotedMatch ? quotedMatch[1] : ''
        },
        downloadBlob(blob, fileName) {
            const url = window.URL.createObjectURL(blob)
            const link = document.createElement('a')

            link.href = url
            link.download = fileName
            document.body.appendChild(link)
            link.click()
            link.remove()
            window.URL.revokeObjectURL(url)
        },
        openAdoptedPersonalTimetable() {
            this.showManualTimetable = true
            this.showEvaluationSettings = false
            this.manualTimetableMode = 'personal'
            this.applySavedTimetableSelection()
            this.personalTimetableViewChangedMessageVisible = true

            const query = { ...this.$route.query }
            delete query.automatic_timetable
            delete query.automatic_timetable_courses
            delete query.automatic_timetable_additional_courses
            delete query.automatic_timetable_criteria
            query.manual_timetable = 'personal'

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        dismissPersonalTimetableViewChangeMessage() {
            this.personalTimetableViewChangedMessageVisible = false
        },
        deselectSavedTimetableCourseChip(courseChip) {
            if (courseChip?.isPersonalAdditional) {
                const courseChipKey = String(courseChip?.key || '')

                this.personalAdditionalTimetableEntryKeys = (Array.isArray(this.personalAdditionalTimetableEntryKeys)
                    ? this.personalAdditionalTimetableEntryKeys
                    : [])
                    .filter(entryKey => entryKey !== courseChipKey)

                return
            }

            const courseChipKey = String(courseChip?.key || '')

            if (!courseChipKey || this.hiddenSavedTimetableCourseChipKeys.includes(courseChipKey)) {
                return
            }

            this.hiddenSavedTimetableCourseChipKeys = [
                ...this.hiddenSavedTimetableCourseChipKeys,
                courseChipKey,
            ]
        },
        deselectAllSavedTimetableCourseChips() {
            this.hiddenSavedTimetableCourseChipKeys = this.savedTimetableCourseChipEntries
                .map(courseChip => courseChip.key)
            this.personalAdditionalTimetableEntryKeys = []
        },
        openStudentCoursePickerDialog() {
            this.selectedStudentCoursePickerTab = 'missing'
            this.selectedStudentCoursePickerMenuKey = ''
            this.studentCoursePickerDialogOpen = true
        },
        selectStudentCoursePickerMenu(courseMenu) {
            this.selectedStudentCoursePickerMenuKey = String(courseMenu?.key || '')
        },
        toggleStudentCoursePickerEntry(entryOption) {
            if (entryOption?.isDisabled) {
                return
            }

            const courseChipKey = String(entryOption?.courseChipKey || '')

            if (courseChipKey) {
                if (entryOption.isActive) {
                    this.deselectSavedTimetableCourseChip({ key: courseChipKey })
                    return
                }

                this.hiddenSavedTimetableCourseChipKeys = this.hiddenSavedTimetableCourseChipKeys
                    .filter(hiddenCourseChipKey => hiddenCourseChipKey !== courseChipKey)
                return
            }

            const selectionKey = this.studentCoursePickerEntrySelectionKey(entryOption)

            if (!selectionKey) {
                return
            }

            if (entryOption.isActive) {
                this.personalAdditionalTimetableEntryKeys = (Array.isArray(this.personalAdditionalTimetableEntryKeys)
                    ? this.personalAdditionalTimetableEntryKeys
                    : [])
                    .filter(entryKey => entryKey !== selectionKey)
                return
            }

            const personalAdditionalTimetableEntryKeys = Array.isArray(this.personalAdditionalTimetableEntryKeys)
                ? this.personalAdditionalTimetableEntryKeys
                : []

            if (!personalAdditionalTimetableEntryKeys.includes(selectionKey)) {
                this.personalAdditionalTimetableEntryKeys = [
                    ...personalAdditionalTimetableEntryKeys,
                    selectionKey,
                ]
            }
        },
        personalMoreCourseCardDisabled(card) {
            return this.personalMoreCourseMenusForCard(card).length === 0
        },
        openPersonalMoreCourseCard(card) {
            if (this.personalMoreCourseCardDisabled(card)) {
                return
            }

            this.activePersonalMoreCourseCardKey = String(card?.key || '')
            this.activePersonalMoreCourseMenuKey = ''
        },
        closePersonalMoreCourseCard() {
            if (this.activePersonalMoreCourseMenuKey) {
                this.activePersonalMoreCourseMenuKey = ''
                return
            }

            this.activePersonalMoreCourseCardKey = ''
        },
        openPersonalMoreCourseMenu(courseMenu) {
            this.activePersonalMoreCourseMenuKey = String(courseMenu?.key || '')
        },
        personalMoreCourseMenuActive(courseMenu) {
            return String(courseMenu?.key || '') === this.activePersonalMoreCourseMenuKey
        },
        togglePersonalMoreCourseEntry(course) {
            this.toggleStudentCoursePickerEntry(course)
        },
        personalMoreCourseMenusForCard(card) {
            const tab = String(card?.tab || 'proposed')
            const courseGroups = this.studentCoursePickerAllCourseGroups
                .filter(courseGroup => this.studentCoursePickerCourseGroupMatchesTabValue(courseGroup, tab))
                .filter(courseGroup => !this.studentCoursePickerCourseGroupInVisibleTimetable(courseGroup))

            if (['planned', 'additional'].includes(String(card?.key || ''))) {
                return this.courseMenusForStudentCoursePickerGroups(
                    courseGroups,
                    { concreteCourseLabels: true },
                )
            }

            return this.courseMenusForStudentCoursePickerGroups(courseGroups)
        },
        courseMenusForStudentCoursePickerGroups(courseGroups, options = {}) {
            const courseMenus = new Map()

            courseGroups.forEach((courseGroup) => {
                const semester = Number(courseGroup?.semester || 1)
                const semesterContext = this.studentCoursePickerSemesterContext(semester)
                const label = options?.concreteCourseLabels
                    ? this.studentCoursePickerConcreteMenuLabel(courseGroup)
                    : this.studentCoursePickerMenuLabel(courseGroup)
                const normalizedLabel = label.toLocaleUpperCase('de-AT')
                const courseMenuKey = `semester-${semester}-${normalizedLabel}`

                if (!courseMenus.has(courseMenuKey)) {
                    courseMenus.set(courseMenuKey, {
                        key: courseMenuKey,
                        label,
                        semester,
                        semesterLabel: semesterContext.label,
                        semesterDateRangeLabel: semesterContext.dateRangeLabel,
                        entries: [],
                        entriesByLabel: new Map(),
                    })
                }

                const courseMenu = courseMenus.get(courseMenuKey)
                const entryLabel = this.studentCoursePickerEntryLabel(courseGroup)
                const entryKey = `${courseMenuKey}-${entryLabel.toLocaleUpperCase('de-AT')}`

                if (!courseMenu.entriesByLabel.has(entryKey)) {
                    courseMenu.entriesByLabel.set(entryKey, {
                        key: entryKey,
                        label: entryLabel,
                        courseGroups: [],
                        courseGroupKeys: [],
                        studentCourseType: courseGroup.studentCourseType,
                        sourceColor: courseGroup.sourceColor,
                    })
                }

                const entry = courseMenu.entriesByLabel.get(entryKey)
                entry.courseGroups.push(courseGroup)
                if (courseGroup?.key) {
                    entry.courseGroupKeys.push(String(courseGroup.key))
                }
            })

            return Array.from(courseMenus.values())
                .map((courseMenu) => {
                    const entries = Array.from(courseMenu.entriesByLabel.values())

                    return {
                        ...courseMenu,
                        meta: this.studentCoursePickerCourseMenuMeta(entries),
                        entries: entries
                            .map(entry => ({
                                ...entry,
                                courseGroupKeys: [...new Set(entry.courseGroupKeys)],
                                courseChipKey: this.studentCoursePickerEntryCourseChipKey(entry),
                                selectionKey: this.studentCoursePickerEntrySelectionKey(entry),
                                scheduleLabel: this.studentCoursePickerEntryScheduleLabel(entry),
                                offeredScheduleLabel: this.studentCoursePickerEntryOfferedScheduleLabel(entry),
                                recurrenceLabel: this.studentCoursePickerEntryRecurrenceLabel(entry),
                            }))
                            .sort((leftEntry, rightEntry) => this.compareStudentCoursePickerEntryOfferItems(leftEntry, rightEntry)),
                    }
                })
                .sort((leftCourseMenu, rightCourseMenu) => {
                    if (leftCourseMenu.semester !== rightCourseMenu.semester) {
                        return leftCourseMenu.semester - rightCourseMenu.semester
                    }

                    return leftCourseMenu.label.localeCompare(rightCourseMenu.label, 'de-AT', {
                        numeric: true,
                        sensitivity: 'base',
                    })
                })
        },
        studentCoursePickerMenuHasActiveSelection(courseMenu) {
            return (courseMenu?.entries || [])
                .some(entry => this.studentCoursePickerEntryActive(entry))
        },
        studentCoursePickerSelectedEntries(semester) {
            return this.studentCoursePickerAllEntries
                .filter(entry => Number(entry?.semester) === Number(semester))
                .filter(entry => this.studentCoursePickerEntryActive(entry))
        },
        studentCoursePickerEntryHasBlockingOverlap(entry, semester) {
            return this.studentCoursePickerSelectedEntries(semester)
                .some(selectedEntry => (
                    selectedEntry.key !== entry?.key
                    && this.studentCoursePickerEntriesHaveBlockingOverlap(entry, selectedEntry)
                ))
        },
        studentCoursePickerEntryHasRelatedOverlap(entry, semester, options = {}) {
            if (options?.hasBlockingOverlap ?? this.studentCoursePickerEntryHasBlockingOverlap(entry, semester)) {
                return false
            }

            return this.studentCoursePickerSelectedEntries(semester)
                .some(selectedEntry => (
                    selectedEntry.key !== entry?.key
                    && this.studentCoursePickerEntriesOverlap(entry, selectedEntry)
                ))
        },
        studentCoursePickerEntriesHaveBlockingOverlap(leftEntry, rightEntry) {
            return (leftEntry?.courseGroups || []).some(leftCourseGroup => (
                (rightEntry?.courseGroups || []).some(rightCourseGroup => (
                    this.studentCoursePickerCourseGroupsOverlap(leftCourseGroup, rightCourseGroup)
                        && this.studentCoursePickerCourseGroupDatesOverlap(leftCourseGroup, rightCourseGroup)
                        && !this.studentCoursePickerCourseGroupOverlapIsSingleDateOnly(leftCourseGroup, rightCourseGroup)
                ))
            ))
        },
        studentCoursePickerEntriesOverlap(leftEntry, rightEntry) {
            return (leftEntry?.courseGroups || []).some(leftCourseGroup => (
                (rightEntry?.courseGroups || []).some(rightCourseGroup => (
                    this.studentCoursePickerCourseGroupsOverlap(leftCourseGroup, rightCourseGroup)
                        && !this.studentCoursePickerCourseGroupOverlapIsSingleDateOnly(leftCourseGroup, rightCourseGroup)
                ))
            ))
        },
        studentCoursePickerCourseGroupsOverlap(leftCourseGroup, rightCourseGroup) {
            if (leftCourseGroup?.key && leftCourseGroup.key === rightCourseGroup?.key) {
                return false
            }

            if (
                Number(leftCourseGroup?.semester) !== Number(rightCourseGroup?.semester)
                || Number(leftCourseGroup?.weekday) !== Number(rightCourseGroup?.weekday)
            ) {
                return false
            }

            const leftTimeRange = this.studentCoursePickerCourseGroupTimeRangeParts(leftCourseGroup)
            const rightTimeRange = this.studentCoursePickerCourseGroupTimeRangeParts(rightCourseGroup)
            if (leftTimeRange.from && leftTimeRange.until && rightTimeRange.from && rightTimeRange.until) {
                return leftTimeRange.from < rightTimeRange.until && rightTimeRange.from < leftTimeRange.until
            }

            return Number(leftCourseGroup?.hour) === Number(rightCourseGroup?.hour)
        },
        studentCoursePickerCourseGroupDates(courseGroup) {
            const explicitDates = Array.isArray(courseGroup?.dates) ? courseGroup.dates : []

            return [
                ...explicitDates,
                courseGroup?.first_date,
                courseGroup?.date,
            ]
                .map(date => String(date || '').trim())
                .filter(Boolean)
                .filter((date, index, dates) => dates.indexOf(date) === index)
                .sort()
        },
        studentCoursePickerCourseGroupDatesOverlap(leftCourseGroup, rightCourseGroup) {
            const leftDates = this.studentCoursePickerCourseGroupDates(leftCourseGroup)
            const rightDates = this.studentCoursePickerCourseGroupDates(rightCourseGroup)

            if (!leftDates.length || !rightDates.length) {
                return true
            }

            return leftDates.some(date => rightDates.includes(date))
        },
        studentCoursePickerCourseGroupOverlapIsSingleDateOnly(leftCourseGroup, rightCourseGroup) {
            return this.studentCoursePickerCourseGroupIsSingleDate(leftCourseGroup)
                || this.studentCoursePickerCourseGroupIsSingleDate(rightCourseGroup)
        },
        studentCoursePickerCourseGroupIsSingleDate(courseGroup) {
            const explicitInterval = Number(courseGroup?.recurrence_interval)
            if ([1, 2, 3, 4].includes(explicitInterval)) {
                return false
            }

            const labelMatch = String(courseGroup?.recurrence_label || '').match(/(\d+)\s*-\s*w/iu)
            if (labelMatch) {
                return ![1, 2, 3, 4].includes(Number(labelMatch[1]))
            }

            return false
        },
        studentCoursePickerCourseGroupTimeRangeParts(courseGroup) {
            return {
                from: this.formatTimeValue(
                    courseGroup?.starts_at
                    || courseGroup?.time_from
                    || courseGroup?.from
                    || this.configuredSchoolHour(courseGroup?.hour)?.from,
                ),
                until: this.formatTimeValue(
                    courseGroup?.ends_at
                    || courseGroup?.time_until
                    || courseGroup?.until
                    || this.configuredSchoolHour(courseGroup?.hour)?.until,
                ),
            }
        },
        studentCoursePickerCourseGroupMatchesTab(courseGroup) {
            return this.studentCoursePickerCourseGroupMatchesTabValue(courseGroup, this.selectedStudentCoursePickerTab)
        },
        studentCoursePickerCourseGroupMatchesTabValue(courseGroup, tabValue) {
            const tab = String(tabValue || 'proposed')
            const studentCourseType = String(courseGroup?.studentCourseType || '')

            if (tab === 'all') {
                return true
            }

            if (tab === 'open') {
                return !this.savedTimetableCourseChipEntryByTitle(this.studentCoursePickerEntryLabel(courseGroup))
            }

            if (tab === 'missing') {
                return studentCourseType === 'missing'
            }

            if (tab === 'additional') {
                return studentCourseType === 'additional'
            }

            return !['missing', 'additional'].includes(studentCourseType)
        },
        studentCoursePickerSemesterContext(semester) {
            return this.studentCoursePickerSemesterContexts
                .find(semesterContext => Number(semesterContext.value) === Number(semester))
                || {
                    value: Number(semester) || 1,
                    label: `Semester ${Number(semester) || 1}`,
                    dateRangeLabel: '',
                }
        },
        studentCoursePickerMenuLabel(courseGroup) {
            const label = this.studentCoursePickerEntryLabel(courseGroup)
            const match = label
                .trim()
                .match(/^(.+?)(?=\d|\s|-|$)/u)

            return (match ? match[1] : label).trim().toLocaleUpperCase('de-AT') || '-'
        },
        studentCoursePickerConcreteMenuLabel(courseGroup) {
            const label = this.studentCoursePickerEntryLabel(courseGroup)
            const [concreteLabel] = label.trim().split(/\s+-\s+|\s/u)

            if (concreteLabel) {
                return this.readableCourseCodeLabel(concreteLabel)
            }

            const course = String(courseGroup?.course || '').trim()

            return this.readableCourseCodeLabel((course || label).trim()) || '-'
        },
        studentCoursePickerCourseGroupInVisibleTimetable(courseGroup) {
            const savedCourseChip = this.savedTimetableCourseChipEntryByTitle(this.studentCoursePickerEntryLabel(courseGroup))

            if (savedCourseChip && !this.hiddenSavedTimetableCourseChipKeys.includes(savedCourseChip.key)) {
                return true
            }

            const concreteCourseAliases = this.courseCodeAliases(this.studentCoursePickerConcreteMenuLabel(courseGroup))

            if (!concreteCourseAliases.length) {
                return false
            }

            const concreteCourseAliasSet = new Set(concreteCourseAliases)
            const hiddenCourseChipKeys = new Set(this.hiddenSavedTimetableCourseChipKeys)

            return this.savedTimetableCourseChipEntries
                .filter(courseChip => !hiddenCourseChipKeys.has(courseChip.key))
                .some(courseChip => this.courseCodeAliases(this.savedTimetableCourseChipShortLabel(courseChip))
                    .some(courseCode => concreteCourseAliasSet.has(courseCode)))
        },
        studentCoursePickerEntryLabel(courseGroup) {
            return courseGroup?.display_label || this.manualCourseGroupLabel(courseGroup)
        },
        studentCoursePickerEntryActive(entry) {
            const courseChipKey = String(entry?.courseChipKey || '')

            if (courseChipKey) {
                return !this.hiddenSavedTimetableCourseChipKeys.includes(courseChipKey)
            }

            const selectionKey = this.studentCoursePickerEntrySelectionKey(entry)

            const selectedEntryKeys = Array.isArray(this.personalAdditionalTimetableEntryKeys)
                ? this.personalAdditionalTimetableEntryKeys
                : []

            return Boolean(selectionKey && selectedEntryKeys.includes(selectionKey))
        },
        studentCoursePickerEntryColor(entry, state = {}) {
            if (state.hasBlockingOverlap) {
                return 'error'
            }

            if (state.hasRelatedOverlap) {
                return 'warning'
            }

            if (state.isActive) {
                return 'success'
            }

            if (String(entry?.studentCourseType || '') === 'additional') {
                return 'warning'
            }

            return 'primary'
        },
        studentCoursePickerEntryCourseChipKey(entry) {
            return this.savedTimetableCourseChipEntryByTitle(entry?.label)?.key || ''
        },
        studentCoursePickerEntrySelectionKey(entry) {
            const courseChipKey = String(entry?.courseChipKey || '').trim()

            if (courseChipKey) {
                return courseChipKey
            }

            const label = String(entry?.label || '').trim()
            const semester = Number(entry?.semester || 1)

            return label
                ? `semester-${semester}-${label.toLocaleUpperCase('de-AT')}`
                : String(entry?.key || '').trim()
        },
        studentCoursePickerCourseMenuMeta(entries) {
            const hours = [...new Set((Array.isArray(entries) ? entries : [])
                .map(entry => this.personalAdditionalTimetableEntryHours(entry))
                .filter(value => value > 0))]

            return hours.length === 1 ? `${this.formatHours(hours[0])} Std.` : ''
        },
        personalAdditionalTimetableEntryHours(entry) {
            const entryHours = this.courseHoursNumber(entry)

            if (entryHours > 0) {
                return entryHours
            }

            const courseGroup = (Array.isArray(entry?.courseGroups) ? entry.courseGroups : [])
                .find(group => this.courseHoursNumber(group) > 0)

            return this.courseHoursNumber(courseGroup)
        },
        personalMoreCourseEntryIsDistanceLearning(entry) {
            const courseGroups = Array.isArray(entry?.courseGroups)
                ? entry.courseGroups
                : this.manualCourseGroups(entry)

            if (courseGroups.some(courseGroup => (
                courseGroup?.distanceLearning === true
                || courseGroup?.distance_learning === true
                || courseGroup?.isDistanceLearningCourse === true
                || courseGroup?.is_distance_learning_course === true
            ))) {
                return true
            }

            const requiredSlotCount = Math.max(1, Math.round(this.personalAdditionalTimetableEntryHours(entry)))
            const scheduledWeeklyLoad = this.courseGroupsScheduledWeeklyLoad(courseGroups)

            if (requiredSlotCount >= 2
                && scheduledWeeklyLoad > 0
                && Math.abs((scheduledWeeklyLoad * 2) - requiredSlotCount) < 0.001) {
                return true
            }

            return false
        },
        savedTimetableCourseChipEntryByTitle(title) {
            const normalizedTitle = String(title || '').trim().toLocaleUpperCase('de-AT')

            if (!normalizedTitle) {
                return null
            }

            return this.savedTimetableCourseChipEntries.find(courseChip => (
                String(courseChip?.title || '').trim().toLocaleUpperCase('de-AT') === normalizedTitle
            )) || null
        },
        studentCoursePickerEntryOfferedScheduleLabel(entry) {
            const courseGroups = Array.isArray(entry?.courseGroups) ? entry.courseGroups : []

            return this.studentCoursePickerMergedScheduleLabels(courseGroups).join(', ')
        },
        studentCoursePickerEntryRecurrenceLabel(entry) {
            const courseGroups = Array.isArray(entry?.courseGroups) ? entry.courseGroups : []
            const blockDateRangeLabels = [...new Set(courseGroups
                .filter(courseGroup => courseGroup?.is_block || String(courseGroup?.block_label || '').trim())
                .map(courseGroup => this.studentCoursePickerCourseGroupDateRangeLabel(courseGroup))
                .filter(Boolean))]

            if (blockDateRangeLabels.length) {
                return blockDateRangeLabels.join(', ')
            }

            return this.studentCoursePickerFrequencyLabel(courseGroups)
        },
        studentCoursePickerCourseGroupDateRangeLabel(courseGroup) {
            const dates = Array.isArray(courseGroup?.dates)
                ? [...courseGroup.dates].filter(Boolean).sort()
                : []
            const firstDate = courseGroup?.first_date || dates[0] || null
            const lastDate = courseGroup?.last_date || dates[dates.length - 1] || firstDate
            const labels = [
                this.formatPublishedTimetableCompactDate(firstDate),
                this.formatPublishedTimetableCompactDate(lastDate),
            ].filter(Boolean)

            return labels.filter((dateLabel, index) => index === 0 || dateLabel !== labels[0]).join(' - ')
        },
        compareStudentCoursePickerEntryOfferItems(firstEntry, secondEntry) {
            const firstSlot = this.studentCoursePickerEntryFirstSlot(firstEntry)
            const secondSlot = this.studentCoursePickerEntryFirstSlot(secondEntry)

            if (firstSlot.semester !== secondSlot.semester) {
                return firstSlot.semester - secondSlot.semester
            }

            if (firstSlot.slot !== secondSlot.slot) {
                return firstSlot.slot - secondSlot.slot
            }

            return String(firstEntry?.label || '').localeCompare(String(secondEntry?.label || ''), 'de-AT', {
                numeric: true,
                sensitivity: 'base',
            })
        },
        studentCoursePickerEntryFirstSlot(entry) {
            const courseGroups = Array.isArray(entry?.courseGroups) ? entry.courseGroups : []

            return courseGroups
                .map(courseGroup => ({
                    semester: Number(courseGroup?.semester || entry?.semester || 0),
                    slot: (Number(courseGroup?.weekday || 0) * 10000)
                        + (this.studentCoursePickerTimeLabelToMinutes(courseGroup?.starts_at || courseGroup?.time_from) ?? (Number(courseGroup?.hour || 0) * 100)),
                }))
                .sort((leftSlot, rightSlot) => {
                    if (leftSlot.semester !== rightSlot.semester) {
                        return leftSlot.semester - rightSlot.semester
                    }

                    return leftSlot.slot - rightSlot.slot
                })[0] || {
                    semester: Number(entry?.semester || 0),
                    slot: 999999,
                }
        },
        studentCoursePickerEntryScheduleLabel(entry) {
            const courseGroups = Array.isArray(entry?.courseGroups) ? entry.courseGroups : []
            const scheduleLabels = this.studentCoursePickerMergedScheduleLabels(courseGroups)
            const frequencyLabel = this.studentCoursePickerFrequencyLabel(courseGroups)

            return [
                scheduleLabels.join(', '),
                frequencyLabel ? `(${frequencyLabel})` : '',
            ]
                .filter(Boolean)
                .join(' ')
        },
        studentCoursePickerMergedScheduleLabels(courseGroups) {
            const schedules = new Map()

            courseGroups.forEach((courseGroup) => {
                const weekday = Number(courseGroup?.weekday || 0)
                const weekdayLabel = this.manualCourseGroupWeekdayLabel(courseGroup)
                const from = String(courseGroup?.starts_at || courseGroup?.time_from || '')
                const until = String(courseGroup?.ends_at || courseGroup?.time_until || '')

                if (!weekdayLabel && !from && !until) {
                    return
                }

                const scheduleKey = weekday ? `weekday-${weekday}` : `time-${from}-${until}`

                if (!schedules.has(scheduleKey)) {
                    schedules.set(scheduleKey, {
                        weekdayOrder: weekday || 99,
                        weekdayLabel,
                        ranges: [],
                        fallbackLabels: new Set(),
                    })
                }

                const schedule = schedules.get(scheduleKey)

                if (from && until) {
                    schedule.ranges.push({
                        from,
                        until,
                        fromMinutes: this.studentCoursePickerTimeLabelToMinutes(from),
                        untilMinutes: this.studentCoursePickerTimeLabelToMinutes(until),
                    })

                    return
                }

                schedule.fallbackLabels.add([weekdayLabel, this.manualCourseGroupTimeRange(courseGroup)].filter(Boolean).join(' '))
            })

            return Array.from(schedules.values())
                .sort((leftSchedule, rightSchedule) => leftSchedule.weekdayOrder - rightSchedule.weekdayOrder)
                .flatMap((schedule) => {
                    const mergedRanges = schedule.ranges
                        .sort((leftRange, rightRange) => (leftRange.fromMinutes ?? 0) - (rightRange.fromMinutes ?? 0))
                        .reduce((ranges, range) => {
                            const lastRange = ranges[ranges.length - 1]
                            const gapMinutes = lastRange && lastRange.untilMinutes !== null && range.fromMinutes !== null
                                ? range.fromMinutes - lastRange.untilMinutes
                                : null

                            if (lastRange && gapMinutes !== null && gapMinutes <= 15) {
                                if ((range.untilMinutes ?? 0) > (lastRange.untilMinutes ?? 0)) {
                                    lastRange.until = range.until
                                    lastRange.untilMinutes = range.untilMinutes
                                }

                                return ranges
                            }

                            ranges.push({ ...range })

                            return ranges
                        }, [])
                        .map(range => [schedule.weekdayLabel, `${range.from} - ${range.until}`].filter(Boolean).join(' '))

                    return [
                        ...mergedRanges,
                        ...schedule.fallbackLabels,
                    ]
                })
                .filter(Boolean)
        },
        studentCoursePickerFrequencyLabel(courseGroups) {
            const recurrenceLabels = [...new Set(courseGroups
                .map(courseGroup => this.manualCourseGroupWeekMarker(courseGroup).replace(/[()]/gu, '').trim())
                .filter(Boolean))]

            return recurrenceLabels.join(', ')
        },
        studentCoursePickerTimeLabelToMinutes(value) {
            const match = String(value || '').match(/^(\d{1,2}):(\d{2})$/u)

            if (!match) {
                return null
            }

            return Number(match[1]) * 60 + Number(match[2])
        },
        openPersonalTimetableDeleteDialog() {
            if (this.manualTimetableMode !== 'personal' || !this.hasPersonalTimetable) {
                return
            }

            this.personalTimetableDeleteDialogOpen = true
        },
        closePersonalTimetableDeleteDialog() {
            if (this.personalTimetableDeleting) {
                return
            }

            this.personalTimetableDeleteDialogOpen = false
        },
        async deletePersonalTimetable() {
            if (this.manualTimetableMode !== 'personal' || !this.hasPersonalTimetable || this.personalTimetableDeleting) {
                return
            }

            this.personalTimetableDeleting = true

            try {
                const deleted = await this.studentTimetablesStore.deletePersonalTimetable()

                if (!deleted) {
                    return
                }

                this.personalTimetableDeleteDialogOpen = false
                this.closeManualTimetable()
            } finally {
                this.personalTimetableDeleting = false
            }
        },
        closeManualTimetable() {
            this.personalTimetableViewChangedMessageVisible = false
            this.showManualTimetable = false
            this.clearManualTimetableState()
        },
        goBackFromPersonalTimetable() {
            this.$router.back()
        },
        restartPersonalTimetable() {
            this.personalTimetableViewChangedMessageVisible = false
            this.showManualTimetable = false
            this.showEvaluationSettings = false
            this.manualTimetableMode = 'manual'
            this.manualSelectedCourseKeys = []
            this.manualSelectedCourseGroupKeys = []
            this.hiddenSavedTimetableCourseChipKeys = []
            this.personalAdditionalTimetableEntryKeys = []
            this.selectedStudentCoursePickerTab = 'missing'
            this.selectedStudentCoursePickerMenuKey = ''
            this.activePersonalMoreCourseCardKey = ''
            this.activePersonalMoreCourseMenuKey = ''
            this.studentCoursePickerDialogOpen = false

            this.$router.push({
                path: this.$route.path,
                query: {},
            })
        },
        clearManualTimetableState() {
            if (!this.$route.query.manual_timetable) {
                return
            }

            const query = { ...this.$route.query }
            delete query.manual_timetable

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        applyManualTimetableRoute(value) {
            const manualTimetableRoute = String(value || '')

            this.showManualTimetable = ['1', 'personal', 'published'].includes(manualTimetableRoute)
            this.manualTimetableMode = ['personal', 'published'].includes(manualTimetableRoute)
                ? manualTimetableRoute
                : 'manual'

            this.applyCurrentManualTimetableSelection()
        },
        applyCurrentManualTimetableSelection() {
            if (!this.showManualTimetable) {
                return
            }

            if (['personal', 'published'].includes(this.manualTimetableMode)) {
                this.applySavedTimetableSelection()

                return
            }

            this.ensureManualTimetableDefaultSelection()
        },
        applyPublishedTimetableSelection() {
            this.applySavedTimetableSelection(this.publishedTimetableCourseGroupKeys)
        },
        applySavedTimetableSelection(courseGroupKeys = this.activeSavedTimetableCourseGroupKeys) {
            this.hiddenSavedTimetableCourseChipKeys = []
            this.personalAdditionalTimetableEntryKeys = []
            const savedCourseGroupKeys = courseGroupKeys
            const savedCourseGroupKeySet = new Set(savedCourseGroupKeys)

            this.manualSelectedCourseGroupKeys = savedCourseGroupKeys
            this.manualSelectedCourseKeys = this.manualTimetableCourses
                .filter(course => this.manualCourseGroups(course)
                    .some(courseGroup => savedCourseGroupKeySet.has(this.manualCourseGroupKey(courseGroup))))
                .map(course => this.manualCourseKey(course))

            if (!this.manualSelectedCourseKeys.length) {
                this.ensureManualTimetableDefaultSelection()
            }
        },
        publishedTimetableSemesterKey(semester) {
            return [
                semester?.label || '',
                semester?.date_range || '',
            ].join('|')
        },
        publishedTimetableSemesterWeeks(semester) {
            return Array.isArray(semester?.weeks) ? semester.weeks : []
        },
        publishedTimetableWeekKey(semester, week) {
            return [
                this.publishedTimetableSemesterKey(semester),
                week?.label || '',
                this.publishedTimetableWeekHours(week)
                    .map(hour => hour.hour)
                    .join(','),
            ].join('|')
        },
        publishedTimetableWeekHours(week) {
            return Array.isArray(week?.hours) ? week.hours : []
        },
        publishedTimetableHourKey(week, hour) {
            return [
                week?.label || '',
                hour?.hour || '',
                hour?.from || '',
                hour?.until || '',
            ].join('|')
        },
        publishedTimetableHourCells(hour) {
            const cells = Array.isArray(hour?.cells) ? hour.cells : []
            const weekdayCount = Math.max(this.publishedTimetableWeekdays.length, cells.length)

            return Array.from({ length: weekdayCount }, (_, index) => cells[index] || {})
        },
        publishedTimetableCellCourses(cell, semester = null, hour = null, cellIndex = null) {
            const courses = this.publishedTimetableCellRawCourses(cell)
            const hiddenCourseChipKeys = new Set(this.hiddenSavedTimetableCourseChipKeys)
            const visibleCourses = this.manualTimetableMode === 'personal' && this.hiddenSavedTimetableCourseChipKeys.length
                ? courses.filter(course => !hiddenCourseChipKeys.has(this.savedTimetableCourseChipKey(course)))
                : courses

            return [
                ...visibleCourses,
                ...this.personalAdditionalTimetableCoursesForCell(semester, hour, cellIndex),
            ]
        },
        personalAdditionalTimetableCoursesForCell(semester, hour, cellIndex) {
            if (
                this.manualTimetableMode !== 'personal'
                || !Array.isArray(this.personalAdditionalTimetableEntryKeys)
                || !this.personalAdditionalTimetableEntryKeys.length
                || !hour
                || cellIndex === null
            ) {
                return []
            }

            const selectedEntryKeys = new Set(this.personalAdditionalTimetableEntryKeys)
            const semesterIndex = this.publishedTimetableSemesters.indexOf(semester)
            const semesterValue = Number(semester?.value || semester?.semester || (semesterIndex >= 0 ? semesterIndex + 1 : 1))
            const weekday = this.publishedTimetableWeekdayForCellIndex(Number(cellIndex))
            const weekdayValue = Number(weekday?.value || Number(cellIndex) + 1)
            const hourValue = Number(hour?.hour || 0)
            const from = this.formatTimeValue(hour?.from)
            const until = this.formatTimeValue(hour?.until)

            return this.studentCoursePickerAllEntries
                .filter(entry => selectedEntryKeys.has(this.studentCoursePickerEntrySelectionKey(entry)))
                .flatMap((entry) => (entry.courseGroups || [])
                    .filter(courseGroup => this.personalAdditionalTimetableCourseGroupMatchesCell(courseGroup, {
                        semesterValue,
                        weekdayValue,
                        hourValue,
                        from,
                        until,
                    }))
                    .map(courseGroup => this.personalAdditionalTimetableCourseForGroup(entry, courseGroup)))
        },
        personalAdditionalTimetableCourseGroupMatchesCell(courseGroup, cellContext) {
            const courseGroupSemester = Number(courseGroup?.semester || 1)
            const courseGroupWeekday = Number(courseGroup?.weekday || 0)
            const courseGroupHour = Number(courseGroup?.hour || 0)
            const courseGroupTimeParts = this.studentCoursePickerCourseGroupTimeRangeParts(courseGroup)
            const timeMatches = Boolean(
                cellContext.from
                && cellContext.until
                && courseGroupTimeParts.from === cellContext.from
                && courseGroupTimeParts.until === cellContext.until,
            )

            return courseGroupSemester === cellContext.semesterValue
                && courseGroupWeekday === cellContext.weekdayValue
                && (courseGroupHour === cellContext.hourValue || timeMatches)
        },
        personalAdditionalTimetableCourseForGroup(entry, courseGroup) {
            const scheduleLabel = this.studentCoursePickerEntryScheduleLabel({
                ...entry,
                courseGroups: [courseGroup],
            })

            return {
                key: [
                    this.studentCoursePickerEntrySelectionKey(entry),
                    courseGroup?.key || '',
                ].join('|'),
                label: entry?.label || this.studentCoursePickerEntryLabel(courseGroup),
                details: scheduleLabel,
                dates: Array.isArray(courseGroup?.dates) ? courseGroup.dates : [],
                student_course_badge: 'Ausgewählt',
                student_course_type: courseGroup?.studentCourseType || entry?.studentCourseType || '',
            }
        },
        publishedTimetableCellRawCourses(cell) {
            return Array.isArray(cell?.courses) ? cell.courses : []
        },
        publishedTimetableCellMarkers(cell) {
            return Array.isArray(cell?.markers) ? cell.markers : []
        },
        publishedTimetableCellClasses(cell, semester = null, hour = null, cellIndex = null) {
            const courses = this.publishedTimetableCellCourses(cell, semester, hour, cellIndex)
            const hasMultipleCourses = courses.length > 1

            return {
                'student-published-timetable__cell--filled': courses.length > 0,
                'student-published-timetable__cell--warning': hasMultipleCourses || String(cell?.status || '') === 'warning',
                'student-published-timetable__cell--conflict': !hasMultipleCourses && String(cell?.status || '') === 'conflict',
                'student-published-timetable__cell--related': !hasMultipleCourses && String(cell?.status || '') === 'related',
                'student-published-timetable__cell--additional': courses.some(course => this.publishedTimetableCourseIsAdditional(course)),
                'student-published-timetable__cell--has-occasional': this.publishedTimetableCellMarkers(cell).length > 0,
            }
        },
        publishedTimetableCourseClasses(course, cell = null) {
            return {
                'student-published-timetable__block--conflict': String(cell?.status || '') === 'conflict',
                'student-published-timetable__block--additional': this.publishedTimetableCourseIsAdditional(course),
            }
        },
        publishedTimetableCourseIsAdditional(course) {
            const courseType = String(course?.student_course_type || '').toLocaleLowerCase('de-AT')
            const badge = String(course?.student_course_badge || '').toLocaleLowerCase('de-AT')

            return courseType.includes('additional')
                || courseType.includes('zusätzlich')
                || badge === 'ausgewählt'
        },
        publishedTimetableMarkerIsAdditional(marker) {
            const markerType = String(marker?.type || marker?.student_course_type || '').toLocaleLowerCase('de-AT')
            const markerLabel = String(marker?.label || marker?.title || '').toLocaleLowerCase('de-AT')

            return markerType.includes('additional')
                || markerType.includes('zusätzlich')
                || markerLabel.includes('zusatz')
        },
        publishedTimetableCourseDetails(course) {
            return this.publishedTimetableCourseDetailParts(course)
                .filter(detail => !this.publishedTimetableDetailIsDistanceLearning(detail))
                .filter(detail => !this.publishedTimetableDetailIsDateRange(detail))
                .filter(detail => !this.publishedTimetableDetailIsRecurrence(detail))
                .join(' · ')
        },
        publishedTimetableCourseIsDistanceLearning(course, semester = null, hour = null, cellIndex = null) {
            if (
                course?.isDistanceLearningCourse === true
                || course?.is_fu === true
                || course?.is_distance_learning === true
                || course?.distance_learning === true
            ) {
                return true
            }

            const matchingCourseGroup = this.publishedTimetableCourseGroupForCellCourse(course, semester, hour, cellIndex)

            if (matchingCourseGroup) {
                const sourceCourse = this.publishedTimetableActiveSourceCourseForGroup(matchingCourseGroup)

                return this.personalMoreCourseEntryIsDistanceLearning(sourceCourse || {
                    courseGroups: [matchingCourseGroup],
                })
            }

            return this.publishedTimetableCourseDetailParts(course)
                .some(detail => this.publishedTimetableDetailIsDistanceLearning(detail))
        },
        publishedTimetableCourseRecurrenceLabel(course, semester = null, hour = null, cellIndex = null) {
            const matchingCourseGroup = this.publishedTimetableCourseGroupForCellCourse(course, semester, hour, cellIndex)
            const recurrenceLabels = [
                course?.recurrence_label,
                course?.recurrenceLabel,
                matchingCourseGroup?.recurrence_label,
                matchingCourseGroup?.recurrenceLabel,
                this.publishedTimetableCourseGroupIntervalLabel(matchingCourseGroup),
                this.publishedTimetableDatesIntervalLabel(course?.dates),
                ...this.publishedTimetableCourseDetailParts(course),
            ]
                .flatMap(label => this.normalizedPublishedTimetableRecurrenceLabels(label))
                .filter(Boolean)

            return [...new Set(recurrenceLabels)].join(', ')
        },
        publishedTimetableCourseGroupForCellCourse(course, semester = null, hour = null, cellIndex = null) {
            if (!semester || !hour || cellIndex === null) {
                return null
            }

            const semesterValue = Number(semester?.value || semester?.semester || 0)
            const semesterRequired = semesterValue > 0
            const weekday = this.publishedTimetableWeekdayForCellIndex(Number(cellIndex))
            const weekdayValue = Number(weekday?.value || Number(cellIndex) + 1)
            const hourValue = Number(hour?.hour || 0)
            const from = this.formatTimeValue(hour?.from)
            const until = this.formatTimeValue(hour?.until)
            const courseCodeAliases = new Set(this.courseCodeAliases(course?.label || ''))
            const courseDetails = this.normalizedCourseCode(course?.details || '')

            return this.publishedTimetableSourceCourseGroups()
                .find((courseGroup) => {
                    if (!this.publishedTimetableCourseGroupMatchesCell(courseGroup, {
                        semesterValue,
                        semesterRequired,
                        weekdayValue,
                        hourValue,
                        from,
                        until,
                    })) {
                        return false
                    }

                    const groupLabels = [
                        courseGroup?.course,
                        courseGroup?.subject,
                        courseGroup?.title,
                        courseGroup?.display_label,
                        this.manualCourseGroupLabel(courseGroup),
                    ]
                        .map(label => this.normalizedCourseCode(label))
                        .filter(Boolean)

                    return groupLabels.some(groupLabel => courseCodeAliases.has(groupLabel))
                        || (courseDetails && groupLabels.some(groupLabel => courseDetails.includes(groupLabel)))
                }) || null
        },
        publishedTimetableCourseGroupMatchesCell(courseGroup, cellContext) {
            const courseGroupSemester = Number(courseGroup?.semester || 0)
            const courseGroupWeekday = Number(courseGroup?.weekday || 0)
            const courseGroupHour = Number(courseGroup?.hour || 0)
            const courseGroupTimeParts = this.studentCoursePickerCourseGroupTimeRangeParts(courseGroup)
            const timeMatches = Boolean(
                cellContext.from
                && cellContext.until
                && courseGroupTimeParts.from === cellContext.from
                && courseGroupTimeParts.until === cellContext.until,
            )
            const semesterMatches = !cellContext.semesterRequired
                || !courseGroupSemester
                || courseGroupSemester === cellContext.semesterValue

            return semesterMatches
                && courseGroupWeekday === cellContext.weekdayValue
                && (courseGroupHour === cellContext.hourValue || timeMatches)
        },
        publishedTimetableSourceCourseGroups() {
            const manualTimetableCourses = Array.isArray(this.manualTimetableCourses)
                ? this.manualTimetableCourses
                : this.manualTimetableCourseSections
                    .flatMap(section => Array.isArray(section?.items) ? section.items : [])

            return manualTimetableCourses
                .flatMap(course => this.manualCourseGroups(course))
        },
        publishedTimetableSourceCourseForGroup(sourceCourseGroup) {
            if (!sourceCourseGroup) {
                return null
            }

            const sourceCourseGroupKey = this.manualCourseGroupKey(sourceCourseGroup)

            return this.manualTimetableCourses.find(course => this.manualCourseGroups(course)
                .some(courseGroup => courseGroup === sourceCourseGroup
                    || this.manualCourseGroupKey(courseGroup) === sourceCourseGroupKey)) || null
        },
        publishedTimetableActiveSourceCourseForGroup(sourceCourseGroup) {
            const sourceCourse = this.publishedTimetableSourceCourseForGroup(sourceCourseGroup)

            if (!sourceCourse) {
                return null
            }

            const activeCourseGroupKeys = new Set(this.activeSavedTimetableCourseGroupKeys)

            if (!activeCourseGroupKeys.size) {
                return sourceCourse
            }

            const activeCourseGroups = this.manualCourseGroups(sourceCourse)
                .filter(courseGroup => activeCourseGroupKeys.has(this.manualCourseGroupKey(courseGroup)))

            return {
                ...sourceCourse,
                course_groups: activeCourseGroups.length ? activeCourseGroups : this.manualCourseGroups(sourceCourse),
            }
        },
        publishedTimetableCourseGroupIntervalLabel(courseGroup) {
            if (!courseGroup) {
                return ''
            }

            const explicitInterval = Number(courseGroup?.recurrence_interval)
            if (Number.isInteger(explicitInterval) && explicitInterval > 1) {
                return `${explicitInterval}-wöchig`
            }

            return this.publishedTimetableDatesIntervalLabel(this.studentCoursePickerCourseGroupDates(courseGroup))
        },
        publishedTimetableDatesIntervalLabel(dates) {
            const interval = this.publishedTimetableWeekIntervalFromDates(dates)

            return interval && interval > 1 ? `${interval}-wöchig` : ''
        },
        publishedTimetableWeekIntervalFromDates(dates) {
            if (!Array.isArray(dates) || dates.length < 2) {
                return null
            }

            const parsedDates = dates
                .map(date => this.publishedTimetableDateFromIsoValue(date))
                .filter(Boolean)
                .sort((firstDate, secondDate) => firstDate.getTime() - secondDate.getTime())

            if (parsedDates.length < 2) {
                return null
            }

            const weekDiffs = parsedDates
                .slice(1)
                .map((date, index) => Math.round((date.getTime() - parsedDates[index].getTime()) / (7 * 24 * 60 * 60 * 1000)))
                .filter(diff => diff > 0)

            if (!weekDiffs.length) {
                return null
            }

            return weekDiffs.reduce((interval, diff) => this.greatestCommonDivisor(interval, diff), weekDiffs[0])
        },
        greatestCommonDivisor(leftValue, rightValue) {
            let leftNumber = Math.abs(Number(leftValue || 0))
            let rightNumber = Math.abs(Number(rightValue || 0))

            while (rightNumber > 0) {
                const nextNumber = leftNumber % rightNumber
                leftNumber = rightNumber
                rightNumber = nextNumber
            }

            return leftNumber || null
        },
        publishedTimetableCourseDetailParts(course) {
            return String(course?.details || '')
                .split('·')
                .map(detail => detail.trim())
                .filter(Boolean)
        },
        publishedTimetableDetailIsDistanceLearning(detail) {
            const normalizedDetail = String(detail || '').trim().toLocaleUpperCase('de-AT')

            return normalizedDetail === 'FU'
                || normalizedDetail === 'FERNUNTERRICHT'
        },
        publishedTimetableDetailIsDateRange(detail) {
            return /\d{2}\.\d{2}\./u.test(String(detail || ''))
                || /\d{4}-\d{2}-\d{2}/u.test(String(detail || ''))
        },
        publishedTimetableDetailIsRecurrence(detail) {
            return this.normalizedPublishedTimetableRecurrenceLabels(detail).length > 0
                || this.publishedTimetableDetailIsWeeklyRecurrence(detail)
        },
        publishedTimetableDetailIsWeeklyRecurrence(detail) {
            const label = String(detail || '').trim()

            return /^(\d+)\s*(?:-?\s*w|(?:-?\s*)?wöchig|(?:-?\s*)?wöchentlich)$/iu.test(label)
                || /^wöchentlich$/iu.test(label)
        },
        normalizedPublishedTimetableRecurrenceLabels(value) {
            return String(value || '')
                .replace(/[()]/gu, '')
                .split(',')
                .map(label => this.normalizedPublishedTimetableRecurrenceLabel(label))
                .filter(Boolean)
        },
        normalizedPublishedTimetableRecurrenceLabel(value) {
            const label = String(value || '').trim()

            if (!label || this.publishedTimetableDetailIsDateRange(label) || this.publishedTimetableDetailIsDistanceLearning(label)) {
                return ''
            }

            const numericMatch = label.match(/^(\d+)\s*(?:-?\s*w|(?:-?\s*)?wöchig|(?:-?\s*)?wöchentlich)$/iu)

            if (numericMatch) {
                const interval = Number(numericMatch[1])

                return interval > 1 ? `${interval}-wöchig` : ''
            }

            if (/^wöchentlich$/iu.test(label)) {
                return ''
            }

            return ''
        },
        publishedTimetableCourseKey(course) {
            return [
                course?.label || '',
                course?.details || '',
                Array.isArray(course?.dates) ? course.dates.join(',') : '',
            ].join('|')
        },
        publishedTimetableMarkerKey(marker) {
            return [
                marker?.label || '',
                marker?.title || '',
            ].join('|')
        },
        publishedTimetableSameSlotGroups(week) {
            return this.publishedTimetableWeekHours(week)
                .flatMap((hour) => this.publishedTimetableHourCells(hour)
                    .map((cell, cellIndex) => this.publishedTimetableSameSlotGroup(hour, cell, cellIndex))
                    .filter(Boolean))
        },
        publishedTimetableSameSlotGroup(hour, cell, cellIndex) {
            const courses = this.publishedTimetableCellCourses(cell)
                .map(course => this.publishedTimetableSameSlotCourse(course))
                .filter(course => course.dateLabels.length > 0)
                .sort((leftCourse, rightCourse) => leftCourse.title.localeCompare(rightCourse.title, 'de-AT', {
                    numeric: true,
                    sensitivity: 'base',
                }))

            if (courses.length < 2) {
                return null
            }

            return {
                key: [
                    hour?.hour || '',
                    hour?.from || '',
                    hour?.until || '',
                    cellIndex,
                    courses.map(course => course.key).join('|'),
                ].join('|'),
                title: this.publishedTimetableSameSlotTitle(hour, cellIndex),
                courses,
            }
        },
        publishedTimetableSameSlotCourse(course) {
            const dates = this.publishedTimetableCourseDates(course)

            return {
                key: this.publishedTimetableCourseKey(course),
                title: this.publishedTimetableSameSlotCourseTitle(course),
                dateRangeLabel: this.publishedTimetableDateRangeLabel(dates),
                dateLabels: dates.map(date => this.formatPublishedTimetableDateWithWeekday(date)),
            }
        },
        publishedTimetableCourseDates(course) {
            return (Array.isArray(course?.dates) ? course.dates : [])
                .map(date => String(date || '').trim())
                .filter(Boolean)
                .filter((date, index, dates) => dates.indexOf(date) === index)
                .sort()
        },
        publishedTimetableSameSlotCourseTitle(course) {
            return String(course?.label || course?.title || course?.course || 'Ohne Bezeichnung').trim()
        },
        publishedTimetableSameSlotTitle(hour, cellIndex) {
            const weekday = this.publishedTimetableWeekdayForCellIndex(cellIndex)
            const hourLabel = hour?.hour ? `${Number(hour.hour)}.` : ''
            const timeRange = hour?.from && hour?.until ? `${hour.from} - ${hour.until}` : ''

            return [
                weekday?.label || '',
                hourLabel,
                timeRange,
            ]
                .filter(Boolean)
                .join(' ')
        },
        publishedTimetableWeekdayForCellIndex(cellIndex) {
            return this.publishedTimetableWeekdays[cellIndex] || {}
        },
        publishedTimetableDateRangeLabel(dates) {
            if (!Array.isArray(dates) || !dates.length) {
                return ''
            }

            const firstDate = dates[0]
            const lastDate = dates[dates.length - 1]

            if (firstDate === lastDate) {
                return this.formatPublishedTimetableCompactDate(firstDate)
            }

            return [
                this.formatPublishedTimetableCompactDate(firstDate),
                this.formatPublishedTimetableCompactDate(lastDate),
            ].filter(Boolean).join(' - ')
        },
        formatPublishedTimetableDateWithWeekday(value) {
            return [
                this.publishedTimetableWeekdayLabelForDate(value),
                this.formatPublishedTimetableDate(value),
            ].filter(Boolean).join(', ')
        },
        publishedTimetableWeekdayLabelForDate(value) {
            const date = this.publishedTimetableDateFromIsoValue(value)
            if (!date) {
                return ''
            }

            const weekday = date.getDay()
            const isoWeekday = weekday === 0 ? 7 : weekday
            const weekdayLabels = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']

            return weekdayLabels[isoWeekday - 1] || ''
        },
        formatPublishedTimetableDate(value) {
            const date = this.publishedTimetableDateFromIsoValue(value)
            if (!date) {
                return value || ''
            }

            return new Intl.DateTimeFormat('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            }).format(date)
        },
        formatPublishedTimetableCompactDate(value) {
            const date = this.publishedTimetableDateFromIsoValue(value)
            if (!date) {
                return value || ''
            }

            const day = date.getDate().toString().padStart(2, '0')
            const month = (date.getMonth() + 1).toString().padStart(2, '0')

            return `${day}.${month}.`
        },
        publishedTimetableDateFromIsoValue(value) {
            const match = String(value || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})$/u)
            if (!match) {
                return null
            }

            const [, year, month, day] = match

            return new Date(Number(year), Number(month) - 1, Number(day))
        },
        ensureManualTimetableDefaultSelection() {
            if (!this.showManualTimetable || this.manualSelectedCourseKeys.length) {
                return
            }

            this.manualSelectedCourseKeys = this.manualTimetableCourseSections
                .filter(section => ['missing', 'proposed'].includes(String(section?.key || '')))
                .flatMap(section => Array.isArray(section.items) ? section.items : [])
                .filter(course => this.manualCourseGroups(course).length)
                .map(course => this.manualCourseKey(course))
        },
        manualCourseKey(course) {
            return String(course?.key || [course?.code || '', course?.name || '', course?.semester || ''].join('|'))
        },
        manualCourseGroups(course) {
            return Array.isArray(course?.course_groups) ? course.course_groups : []
        },
        manualCourseSelected(course) {
            return this.manualSelectedCourseKeys.includes(this.manualCourseKey(course))
        },
        setManualCourseSelected(course, selected) {
            const courseKey = this.manualCourseKey(course)

            if (!courseKey) {
                return
            }

            this.manualSelectedCourseGroupKeys = []
            this.manualTimetableMode = 'manual'

            if (selected === true && !this.manualSelectedCourseKeys.includes(courseKey)) {
                this.manualSelectedCourseKeys = [...this.manualSelectedCourseKeys, courseKey]
                return
            }

            if (selected !== true) {
                this.manualSelectedCourseKeys = this.manualSelectedCourseKeys
                    .filter(selectedCourseKey => selectedCourseKey !== courseKey)
            }
        },
        deselectManualCourseGroup(courseGroup) {
            const courseGroupKey = this.manualCourseGroupKey(courseGroup)

            if (!courseGroupKey) {
                return
            }

            const selectedCourseGroupKeys = this.manualSelectedCourseGroupKeys.length
                ? this.manualSelectedCourseGroupKeys
                : this.manualSelectedCourseGroups.map(selectedCourseGroup => this.manualCourseGroupKey(selectedCourseGroup))
            const nextCourseGroupKeys = selectedCourseGroupKeys
                .filter(selectedCourseGroupKey => selectedCourseGroupKey !== courseGroupKey)

            this.manualSelectedCourseGroupKeys = nextCourseGroupKeys
            this.manualSelectedCourseKeys = this.manualTimetableCourses
                .filter(course => this.manualCourseGroups(course)
                    .some(selectedCourseGroup => nextCourseGroupKeys.includes(this.manualCourseGroupKey(selectedCourseGroup))))
                .map(course => this.manualCourseKey(course))
        },
        deselectAllManualCourseGroups() {
            this.manualSelectedCourseGroupKeys = []
            this.manualSelectedCourseKeys = []
        },
        manualCourseMeta(course) {
            const courseGroupsCount = this.manualCourseGroups(course).length
            const hoursLabel = this.courseHoursLabel(course)
            const groupLabel = courseGroupsCount === 1 ? '1 Termin' : `${courseGroupsCount} Termine`

            return [hoursLabel, groupLabel]
                .filter(Boolean)
                .join(' · ')
        },
        manualGroupsForCell(weekday, hour) {
            return this.manualSelectedCourseGroups
                .filter(courseGroup => Number(courseGroup?.weekday || 0) === Number(weekday))
                .filter(courseGroup => Number(courseGroup?.hour || 0) === Number(hour))
        },
        manualCourseGroupKey(courseGroup) {
            return String(courseGroup?.key || [
                courseGroup?.course || courseGroup?.subject || courseGroup?.title || '',
                courseGroup?.class_name || '',
                courseGroup?.weekday || '',
                courseGroup?.hour || '',
                courseGroup?.starts_at || '',
                courseGroup?.ends_at || '',
            ].join('|'))
        },
        manualCourseGroupLabel(courseGroup) {
            return courseGroup?.course || courseGroup?.subject || courseGroup?.title || courseGroup?.display_label || '-'
        },
        manualCourseGroupChipLabel(courseGroup) {
            return [
                courseGroup?.display_label || this.manualCourseGroupLabel(courseGroup),
                this.manualCourseGroupScheduleLabel(courseGroup),
            ]
                .filter(Boolean)
                .join(' · ')
        },
        manualCourseGroupScheduleLabel(courseGroup) {
            return [
                this.manualCourseGroupWeekdayLabel(courseGroup),
                this.manualCourseGroupTimeRange(courseGroup),
                this.manualCourseGroupWeekMarker(courseGroup),
            ]
                .filter(Boolean)
                .join(' ')
        },
        manualCourseGroupWeekdayLabel(courseGroup) {
            const weekday = Number(courseGroup?.weekday || 0)
            const weekdayLabels = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']

            return weekdayLabels[weekday] || ''
        },
        manualCourseGroupTimeRange(courseGroup) {
            return [
                courseGroup?.starts_at || courseGroup?.time_from,
                courseGroup?.ends_at || courseGroup?.time_until,
            ]
                .filter(Boolean)
                .join(' - ')
        },
        manualCourseGroupChipColor(courseGroup) {
            return this.manualCourseGroupHasOverlap(courseGroup) ? 'warning' : 'success'
        },
        manualCourseGroupHasOverlap(courseGroup) {
            const weekday = Number(courseGroup?.weekday || 0)
            const hour = Number(courseGroup?.hour || 0)

            if (!weekday || !hour) {
                return false
            }

            return this.manualGroupsForCell(weekday, hour).length > 1
        },
        manualCourseGroupStudentCourseType(courseGroup) {
            const sectionKey = this.manualCourseSectionKeyForCourseGroup(courseGroup)

            if (sectionKey === 'missing') {
                return 'missing'
            }

            if (sectionKey === 'additional') {
                return 'additional'
            }

            return ''
        },
        manualCourseGroupStudentCourseBadge(courseGroup) {
            const studentCourseType = this.manualCourseGroupStudentCourseType(courseGroup)

            if (studentCourseType === 'missing') {
                return 'Fehlend'
            }

            if (studentCourseType === 'additional') {
                return 'Zusätzlich'
            }

            return ''
        },
        savedTimetableCourseChipKey(course) {
            return this.savedTimetableCourseChipTitle(course)
        },
        savedTimetableCourseChipTitle(course) {
            return String(course?.label || course?.title || course?.course || '').trim()
        },
        savedTimetableCourseChipShortLabel(courseChip) {
            const title = String(courseChip?.title || '').trim()
            const [shortLabel] = title.split(/\s+-\s+|\s/u)

            return shortLabel || title || '-'
        },
        savedTimetableCourseChipHoursText(courseChip) {
            const matchedCourse = this.savedTimetableCourseForChip(courseChip)
            const courseHours = this.courseHoursNumber(matchedCourse)
            const fallbackHours = matchedCourse
                ? this.savedTimetableCourseGroupCountForChip(courseChip, matchedCourse)
                : this.savedTimetableCourseMergedTimeRanges(courseChip.timeRanges).length
            const hours = courseHours || fallbackHours

            return hours > 0 ? `${this.formatHours(hours)} Std.` : ''
        },
        savedTimetableCourseForChip(courseChip) {
            const chipTitle = String(courseChip?.title || '').trim().toLocaleUpperCase('de-AT')
            const chipShortLabel = this.normalizedCourseCode(this.savedTimetableCourseChipShortLabel(courseChip))
            const manualTimetableCourses = Array.isArray(this.manualTimetableCourses)
                ? this.manualTimetableCourses
                : this.manualTimetableCourseSections
                    .flatMap(section => Array.isArray(section?.items) ? section.items : [])

            return manualTimetableCourses.find((course) => {
                const courseCode = this.normalizedCourseCode(course?.code || course?.name || '')
                const courseGroups = this.manualCourseGroups(course)

                return courseGroups.some(courseGroup => (
                    String(this.studentCoursePickerEntryLabel(courseGroup) || '').trim().toLocaleUpperCase('de-AT') === chipTitle
                )) || (chipShortLabel && courseCode === chipShortLabel)
            }) || null
        },
        savedTimetableCourseGroupCountForChip(courseChip, course) {
            const chipTitle = String(courseChip?.title || '').trim().toLocaleUpperCase('de-AT')
            const matchingCourseGroups = this.manualCourseGroups(course)
                .filter(courseGroup => (
                    String(this.studentCoursePickerEntryLabel(courseGroup) || '').trim().toLocaleUpperCase('de-AT') === chipTitle
                ))

            return matchingCourseGroups.length || this.manualCourseGroups(course).length
        },
        savedTimetableCourseChipLabel(courseChip) {
            const scheduleLabel = [
                this.savedTimetableCourseMergedTimeLabels(courseChip.timeRanges).join(', '),
                courseChip.detailLabels.join(', '),
            ]
                .filter(Boolean)
                .join(' ')

            return [
                courseChip.title,
                scheduleLabel,
            ]
                .filter(Boolean)
                .join(' · ')
        },
        savedTimetableCourseChipHoursLabel(courseChip) {
            return [...new Set(this.savedTimetableCourseMergedTimeRanges(courseChip.timeRanges)
                .map(timeRange => this.savedTimetableCourseHourRangeLabel(timeRange))
                .filter(Boolean))]
                .join(', ')
        },
        savedTimetableCourseTimeRange(hour, cellIndex) {
            const weekday = this.publishedTimetableWeekdayForCellIndex(cellIndex)
            const weekdayLabel = String(weekday?.label || '')
            const from = String(hour?.from || '')
            const until = String(hour?.until || '')
            const hourValue = Number(hour?.hour || 0)

            if (!weekdayLabel && !from && !until && !hourValue) {
                return null
            }

            return {
                weekdayIndex: Number(cellIndex),
                weekdayLabel,
                from,
                until,
                startHour: Number.isFinite(hourValue) && hourValue > 0 ? hourValue : null,
                endHour: Number.isFinite(hourValue) && hourValue > 0 ? hourValue : null,
            }
        },
        savedTimetableCourseTimeRangeExists(timeRanges, timeRange) {
            return timeRanges.some(existingTimeRange => (
                existingTimeRange.weekdayLabel === timeRange.weekdayLabel
                    && existingTimeRange.from === timeRange.from
                    && existingTimeRange.until === timeRange.until
            ))
        },
        savedTimetableCourseMergedTimeLabels(timeRanges) {
            return this.savedTimetableCourseMergedTimeRanges(timeRanges)
                .map(timeRange => this.savedTimetableCourseTimeRangeLabel(timeRange))
                .filter(Boolean)
        },
        savedTimetableCourseMergedTimeRanges(timeRanges) {
            return [...timeRanges]
                .sort((leftTimeRange, rightTimeRange) => (
                    leftTimeRange.weekdayIndex - rightTimeRange.weekdayIndex
                        || leftTimeRange.from.localeCompare(rightTimeRange.from)
                        || leftTimeRange.until.localeCompare(rightTimeRange.until)
                ))
                .reduce((mergedTimeRanges, timeRange) => {
                    const previousTimeRange = mergedTimeRanges[mergedTimeRanges.length - 1]

                    if (
                        previousTimeRange
                        && previousTimeRange.weekdayLabel === timeRange.weekdayLabel
                        && this.savedTimetableCourseTimeRangesAreConsecutive(previousTimeRange, timeRange)
                    ) {
                        previousTimeRange.until = timeRange.until
                        previousTimeRange.endHour = timeRange.endHour

                        return mergedTimeRanges
                    }

                    mergedTimeRanges.push({ ...timeRange })

                    return mergedTimeRanges
                }, [])
        },
        savedTimetableCourseTimeRangesAreConsecutive(previousTimeRange, timeRange) {
            if (
                previousTimeRange.endHour
                && timeRange.startHour
                && previousTimeRange.endHour + 1 === timeRange.startHour
            ) {
                return true
            }

            return Boolean(previousTimeRange.until && previousTimeRange.until === timeRange.from)
        },
        savedTimetableCourseTimeRangeLabel(timeRange) {
            const timeLabel = this.savedTimetableCourseHourRangeLabel(timeRange)
                || [
                    timeRange.from,
                    timeRange.until,
                ]
                    .filter(Boolean)
                    .join(' - ')

            return [
                timeRange.weekdayLabel,
                timeLabel,
            ]
                .filter(Boolean)
                .join(' ')
        },
        savedTimetableCourseHourRangeLabel(timeRange) {
            if (!timeRange.startHour) {
                return ''
            }

            if (!timeRange.endHour || timeRange.startHour === timeRange.endHour) {
                return `${timeRange.startHour}.`
            }

            return `${timeRange.startHour}.-${timeRange.endHour}`
        },
        savedTimetableCourseChipBadgeLabel(courseChip) {
            if (courseChip?.studentCourseType === 'missing' || courseChip?.studentCourseBadge === 'Fehlend') {
                return 'F'
            }

            if (courseChip?.studentCourseType === 'additional' || courseChip?.studentCourseBadge === 'Zusätzlich') {
                return 'Z'
            }

            return courseChip?.studentCourseBadge || ''
        },
        savedTimetableCourseDetailLabel(course) {
            const details = String(course?.details || '')
                .split('·')
                .map(detail => detail.trim())
                .filter(Boolean)
                .filter(detail => detail.toLocaleUpperCase('de-AT') !== 'FU')
                .filter(detail => !/\d{2}\.\d{2}\./u.test(detail))
                .map(detail => detail
                    .replace(/^(\d+)\s*-\s*wöchig$/iu, '$1w')
                    .replace(/^(\d+)\s*wöchig$/iu, '$1w'))

            if (!details.length) {
                return ''
            }

            return `(${details.join(', ')})`
        },
        manualCourseSectionKeyForCourseGroup(courseGroup) {
            const courseGroupKey = this.manualCourseGroupKey(courseGroup)

            for (const section of this.manualTimetableCourseSections) {
                const courses = Array.isArray(section?.items) ? section.items : []
                const matchingCourse = courses.find(course => this.manualCourseGroups(course)
                    .some(selectedCourseGroup => this.manualCourseGroupKey(selectedCourseGroup) === courseGroupKey))

                if (matchingCourse) {
                    return String(section?.key || '')
                }
            }

            return ''
        },
        manualCourseGroupDetails(courseGroup) {
            return [
                courseGroup?.display_label,
                courseGroup?.class_name,
            ]
                .filter(Boolean)
                .join(' · ')
        },
        manualCourseGroupWeekMarker(courseGroup) {
            if (courseGroup?.recurrence_label) {
                return `(${courseGroup.recurrence_label})`
            }

            const recurrenceInterval = Number(courseGroup?.recurrence_interval || 0)

            if (recurrenceInterval > 1) {
                return `(${recurrenceInterval}-w)`
            }

            if (courseGroup?.block_label) {
                return courseGroup.block_label
            }

            return ''
        },
        manualTimetableHourTimeFrom(courseGroup, hour = null) {
            return this.formatTimeValue(
                courseGroup?.starts_at
                || courseGroup?.time_from
                || courseGroup?.from
                || this.configuredSchoolHour(hour ?? courseGroup?.hour)?.from,
            )
        },
        manualTimetableHourTimeUntil(courseGroup, hour = null) {
            return this.formatTimeValue(
                courseGroup?.ends_at
                || courseGroup?.time_until
                || courseGroup?.until
                || this.configuredSchoolHour(hour ?? courseGroup?.hour)?.until,
            )
        },
        configuredSchoolHour(hour) {
            const schoolHours = Array.isArray(this.overview?.school_hours) ? this.overview.school_hours : []

            return schoolHours.find(schoolHour => Number(schoolHour?.hour) === Number(hour)) || {}
        },
        formatTimeValue(value) {
            const timeValue = String(value || '').trim()

            return timeValue ? timeValue.slice(0, 5) : ''
        },
        selectionItemEditable(item) {
            return studentOverviewEditableSelectionKeys.includes(String(item?.key || ''))
        },
        selectionItemOverridden(item) {
            const key = String(item?.key || '')

            return Object.prototype.hasOwnProperty.call(this.selectionOverride || {}, key)
        },
        selectionOptionsForKey(key) {
            const optionKey = String(key || '')
            const options = this.overview?.selection_options?.[optionKey]

            return Array.isArray(options) ? options : []
        },
        openSelectionDialog(item) {
            if (!this.selectionItemEditable(item)) {
                return
            }

            this.selectionDraftKey = item.key
            this.selectionDraftLabel = item.label
            this.selectionDraftValue = this.currentSelectionValue(item.key)
            this.selectionDialogOpen = true
        },
        closeSelectionDialog() {
            this.selectionDialogOpen = false
            this.selectionDraftKey = ''
            this.selectionDraftLabel = ''
            this.selectionDraftValue = null
        },
        async saveSelectionDialog() {
            const key = String(this.selectionDraftKey || '')

            if (!studentOverviewEditableSelectionKeys.includes(key)) {
                this.closeSelectionDialog()

                return
            }

            const value = String(this.selectionDraftValue || '')
            const allowedValues = studentOverviewSelectionOptionValues[key] || []

            if (!allowedValues.includes(value)) {
                return
            }

            this.selectionOverride = {
                ...this.selectionOverride,
                [key]: value,
            }
            this.closeSelectionDialog()

            if (!await this.studentTimetablesStore.updateProfileSelection(this.selectionOverridePayload() || {})) {
                return
            }

            this.syncSelectionOverrideFromOverview()
        },
        async restoreSelectionDefaults() {
            if (!await this.studentTimetablesStore.restoreProfileSelection()) {
                return
            }

            this.syncSelectionOverrideFromOverview()
        },
        currentSelectionValue(key) {
            const selectionKey = String(key || '')

            return this.selectionOverride?.[selectionKey] || this.overview?.selection?.[selectionKey] || null
        },
        syncSelectionOverrideFromOverview() {
            this.selectionOverride = this.normalizedSelectionOverride(this.overview?.selection_override || {})
        },
        selectionOverridePayload() {
            const selection = this.normalizedSelectionOverride(this.selectionOverride)

            return Object.keys(selection).length ? selection : null
        },
        normalizedSelectionOverride(selection) {
            const normalizedSelection = {}

            studentOverviewEditableSelectionKeys.forEach((key) => {
                const value = String(selection?.[key] || '').trim()

                if ((studentOverviewSelectionOptionValues[key] || []).includes(value)) {
                    normalizedSelection[key] = value
                }
            })

            return normalizedSelection
        },
        overviewCourseGroupItems(courseGroup) {
            const courseItems = this.overviewCourseGroupSourceCourses(courseGroup)
                .map(course => this.normalizedOverviewCourseItem(course, courseGroup))
            const routeCourseItems = courseGroup === 'planned'
                ? this.automaticTimetableRouteCourseItems()
                : []
            const negativeCourseCodes = courseGroup === 'planned'
                ? this.overviewNegativeCourseCodes()
                : new Set()

            return this.sortedCourseItems(this.uniqueCourseItems(
                [
                    ...courseItems,
                    ...routeCourseItems,
                ].filter(course => !negativeCourseCodes.has(this.normalizedCourseCode(course?.code || course?.label)))
            ))
        },
        overviewCourseGroupSourceCourses(courseGroup) {
            const automaticCourses = this.overviewCourseGroupAutomaticCourses(courseGroup)

            if (automaticCourses.length) {
                return automaticCourses
            }

            const sectionKey = courseGroup === 'planned' ? 'proposed' : courseGroup

            return this.courseHistoryItems(sectionKey, this.overviewCourseGroupFallbackCourses(courseGroup))
        },
        overviewCourseGroupAutomaticCourses(courseGroup) {
            const sectionKey = courseGroup === 'planned' ? 'proposed' : courseGroup
            const automaticSections = Array.isArray(this.automaticTimetableCourseSections)
                ? this.automaticTimetableCourseSections
                : (Array.isArray(this.overview?.automatic_course_selection?.sections)
                    ? this.overview.automatic_course_selection.sections
                    : [])
            const automaticSection = automaticSections
                .find(section => String(section?.key || '') === sectionKey)

            return Array.isArray(automaticSection?.items) ? automaticSection.items : []
        },
        overviewNegativeCourseCodes() {
            return new Set(this.overviewCourseGroupItems('missing')
                .map(course => this.normalizedCourseCode(course?.code || course?.label))
                .filter(Boolean))
        },
        automaticTimetableRouteCourseItems() {
            const initialRouteCourseKeys = Array.isArray(this.automaticTimetableInitialRouteCourseKeys)
                ? this.automaticTimetableInitialRouteCourseKeys
                : []
            const courseKeyList = initialRouteCourseKeys.length
                ? initialRouteCourseKeys
                : this.serializedAutomaticTimetableCourseKeysFromQuery(this.$route?.query || {})

            return courseKeyList
                .map(courseKey => this.overviewCourseItemFromSerializedSelectionKey(courseKey))
                .filter(Boolean)
        },
        automaticTimetableDefaultCourseKeys() {
            const selectableCourseKeys = new Set(this.overviewCourseItemsForSelectionResolution()
                .filter(({ courseGroup }) => ['missing', 'planned'].includes(courseGroup))
                .map(({ course, courseGroup }) => this.overviewCourseSelectionKey(course, courseGroup))
                .filter(Boolean))

            return this.overviewDefaultSelectedCourseKeys
                .filter(courseKey => selectableCourseKeys.has(courseKey))
        },
        automaticTimetableCourseKeysFromRoute(query = this.$route?.query || {}) {
            const courseKeys = query.automatic_timetable_courses
            const courseKeyList = Array.isArray(courseKeys) ? courseKeys : [courseKeys]

            if (courseKeyList.includes(noAutomaticTimetableCourseValue)) {
                return []
            }

            const selectedCourseKeys = this.normalizedOverviewSelectionKeys(courseKeyList
                .map(courseKey => String(courseKey || ''))
                .filter(courseKey => courseKey !== noAutomaticTimetableCourseValue))
                .filter(Boolean)

            return selectedCourseKeys.length || Object.prototype.hasOwnProperty.call(query, 'automatic_timetable_courses')
                ? selectedCourseKeys
                : this.overviewDefaultSelectedCourseKeys
        },
        automaticTimetableAdditionalCourseKeysFromRoute(query = this.$route?.query || {}) {
            if (!Object.prototype.hasOwnProperty.call(query, 'automatic_timetable_additional_courses')) {
                return []
            }

            const courseKeys = query.automatic_timetable_additional_courses
            const courseKeyList = Array.isArray(courseKeys) ? courseKeys : [courseKeys]

            if (courseKeyList.includes(noAutomaticTimetableCourseValue)) {
                return []
            }

            return this.normalizedOverviewSelectionKeys(courseKeyList
                .map(courseKey => String(courseKey || ''))
                .filter(courseKey => courseKey !== noAutomaticTimetableCourseValue))
                .filter(Boolean)
                .filter(courseKey => String(courseKey || '').startsWith('additional:'))
        },
        syncAutomaticTimetableCourseKeysFromRoute() {
            this.automaticTimetableSelectedCourseKeys = this.automaticTimetableCourseKeysFromRoute()
            this.automaticTimetableCourseKeysInitialized = true
        },
        rememberAutomaticTimetableRouteCourseKeys() {
            if (Array.isArray(this.automaticTimetableInitialRouteCourseKeys) && this.automaticTimetableInitialRouteCourseKeys.length) {
                return
            }

            this.automaticTimetableInitialRouteCourseKeys = this.serializedAutomaticTimetableCourseKeysFromQuery(this.$route?.query || {})
        },
        canonicalizeAutomaticTimetableCourseQuery() {
            const query = this.$route?.query || {}

            if (String(query.automatic_timetable || '') === 'result') {
                if (
                    !Object.prototype.hasOwnProperty.call(query, 'automatic_timetable_courses')
                    && !Object.prototype.hasOwnProperty.call(query, 'automatic_timetable_criteria')
                ) {
                    return
                }

                const nextQuery = { ...query }
                delete nextQuery.manual_timetable
                delete nextQuery.automatic_timetable_courses
                delete nextQuery.automatic_timetable_criteria

                this.$router.replace({
                    path: this.$route.path,
                    query: nextQuery,
                })

                return
            }

            if (!Object.prototype.hasOwnProperty.call(query, 'automatic_timetable_courses')) {
                return
            }

            const courseKeys = query.automatic_timetable_courses
            const courseKeyList = Array.isArray(courseKeys) ? courseKeys : [courseKeys]

            if (courseKeyList.includes(noAutomaticTimetableCourseValue)) {
                return
            }

            const currentCourseKeys = courseKeyList
                .map(courseKey => String(courseKey || '').trim())
                .filter(Boolean)
            const canonicalCourseKeys = this.normalizedOverviewSelectionKeys(currentCourseKeys)

            if (JSON.stringify(currentCourseKeys) === JSON.stringify(canonicalCourseKeys)) {
                return
            }

            this.$router.replace({
                path: this.$route.path,
                query: {
                    ...query,
                    automatic_timetable_courses: canonicalCourseKeys.length
                        ? canonicalCourseKeys
                        : noAutomaticTimetableCourseValue,
                },
            })
        },
        serializedAutomaticTimetableCourseKeysFromQuery(query) {
            if (!Object.prototype.hasOwnProperty.call(query, 'automatic_timetable_courses')) {
                return []
            }

            const courseKeys = query.automatic_timetable_courses
            const courseKeyList = Array.isArray(courseKeys) ? courseKeys : [courseKeys]

            if (courseKeyList.includes(noAutomaticTimetableCourseValue)) {
                return []
            }

            return courseKeyList
                .map(courseKey => String(courseKey || '').trim())
                .filter(courseKey => courseKey && courseKey !== noAutomaticTimetableCourseValue)
                .filter(courseKey => courseKey.split('|').length >= 7)
        },
        overviewCourseItemFromSerializedSelectionKey(courseKey) {
            const selectionKey = String(courseKey || '').trim()

            if (!selectionKey || selectionKey === noAutomaticTimetableCourseValue) {
                return null
            }

            const parts = selectionKey.split('|')

            if (parts.length < 7) {
                return null
            }

            const [sourceId, semester, branch, jsonCode, jsonSubject] = parts
            const selectedCode = String(parts[parts.length - 1] || '').trim()
            const name = parts.slice(5, -1).join('|').trim()
            const semesterNumber = Number(semester)

            return this.normalizedOverviewCourseItem({
                key: selectionKey,
                code: selectedCode,
                name,
                semester: Number.isFinite(semesterNumber) ? semesterNumber : null,
                branch,
                json_code: jsonCode,
                json_subject: jsonSubject,
                source_id: sourceId,
                courseGroup: 'planned',
            }, 'planned')
        },
        overviewCourseGroupFallbackCourses(courseGroup) {
            if (courseGroup === 'missing') {
                return this.overview?.missing_courses
            }

            if (courseGroup === 'planned') {
                return this.overview?.proposed_courses
            }

            if (courseGroup === 'additional') {
                return this.overview?.additional_courses
            }

            return []
        },
        normalizedOverviewCourseItem(course, courseGroup = '') {
            const code = String(course?.code || '').trim()
            const name = String(course?.name || '').trim()
            const label = String(course?.label || code || name || '').trim()
            const hours = this.courseHoursNumber(course)
            const meta = String(course?.hoursMeta || course?.meta || course?.hours_label || (hours ? `${this.formatHours(hours)} Std.` : '') || course?.grade || '').trim()
            const normalizedCourseGroup = this.normalizedOverviewCourseGroupKey(courseGroup || course?.courseGroup || course?.course_group || '')

            return {
                ...course,
                key: String(course?.key || [code, name, course?.semester || '', hours || ''].join('|')).trim(),
                courseGroup: normalizedCourseGroup,
                code,
                name,
                label,
                hours,
                meta,
                hoursMeta: hours ? `${this.formatHours(hours)} Std.` : '',
            }
        },
        uniqueCourseItems(courses) {
            const courseItemsByKey = new Map()
            const courseItems = Array.isArray(courses) ? courses : []

            courseItems.forEach((course) => {
                const key = this.overviewCourseSelectionKey(course, course?.courseGroup)

                if (key && !courseItemsByKey.has(key)) {
                    courseItemsByKey.set(key, course)
                }
            })

            return Array.from(courseItemsByKey.values())
        },
        sortedAutomaticTimetableCourseKeys(courseKeys) {
            return (Array.isArray(courseKeys) ? courseKeys : [])
                .map(courseKey => String(courseKey || '').trim())
                .filter(Boolean)
                .sort((firstCourseKey, secondCourseKey) => firstCourseKey.localeCompare(secondCourseKey, 'de-AT', {
                    numeric: true,
                    sensitivity: 'base',
                }))
        },
        sortedCourseSections(sections) {
            return (Array.isArray(sections) ? sections : []).map(section => ({
                ...section,
                items: this.sortedCourseItems(section?.items),
            }))
        },
        sortedCourseItems(courses) {
            return [...(Array.isArray(courses) ? courses : [])]
                .sort((firstCourse, secondCourse) => this.compareCourseItems(firstCourse, secondCourse))
        },
        compareCourseItems(firstCourse, secondCourse) {
            const labelComparison = this.overviewCourseItemLabel(firstCourse).localeCompare(this.overviewCourseItemLabel(secondCourse), 'de-AT', {
                numeric: true,
                sensitivity: 'base',
            })

            if (labelComparison !== 0) {
                return labelComparison
            }

            const firstSemester = Number(firstCourse?.semester || 0)
            const secondSemester = Number(secondCourse?.semester || 0)

            return firstSemester - secondSemester
        },
        overviewCourseSelectionKey(course, courseGroup = '') {
            const normalizedCourseGroup = this.normalizedOverviewCourseGroupKey(courseGroup || course?.courseGroup || course?.course_group || '')
            const normalizedCode = this.normalizedCourseCode(course?.code || course?.label || '')

            if (normalizedCourseGroup && normalizedCode) {
                return `${normalizedCourseGroup}:${normalizedCode}`
            }

            const compactKey = this.normalizedOverviewKnownSelectionKey(course?.key)

            if (compactKey) {
                return compactKey
            }

            return String(course?.key || [
                course?.code || '',
                course?.name || '',
                course?.semester || '',
                course?.hours ?? course?.hours_per_week ?? '',
            ].join('|')).trim()
        },
        normalizedOverviewSelectionKeys(courseKeys) {
            return (Array.isArray(courseKeys) ? courseKeys : [])
                .map(courseKey => this.normalizedOverviewSelectionKey(courseKey))
                .filter(Boolean)
                .filter((courseKey, index, courseKeyList) => courseKeyList.indexOf(courseKey) === index)
        },
        normalizedOverviewSelectionKey(courseKey) {
            const value = String(courseKey || '').trim()

            if (!value || value === noAutomaticTimetableCourseValue) {
                return ''
            }

            const knownSelectionKey = this.normalizedOverviewKnownSelectionKey(value)

            if (knownSelectionKey) {
                return knownSelectionKey
            }

            const matchingCourseItem = this.overviewCourseItemsForSelectionResolution()
                .find(({ course, courseGroup }) => this.overviewRawSelectionKeyMatchesCourse(value, course, courseGroup))

            if (matchingCourseItem) {
                return this.overviewCourseSelectionKey(matchingCourseItem.course, matchingCourseItem.courseGroup)
            }

            const normalizedCode = this.normalizedCourseCode(value)

            return normalizedCode ? `planned:${normalizedCode}` : value
        },
        normalizedOverviewKnownSelectionKey(courseKey) {
            const value = String(courseKey || '').trim()

            if (!value || value === noAutomaticTimetableCourseValue) {
                return ''
            }

            const serializedCourse = this.overviewCourseItemFromSerializedSelectionKey(value)

            if (serializedCourse) {
                return this.overviewCourseSelectionKey(serializedCourse, serializedCourse.courseGroup || 'planned')
            }

            const compactMatch = value.match(/^(missing|planned|proposed|additional):(.+)$/u)

            if (compactMatch) {
                const courseGroup = this.normalizedOverviewCourseGroupKey(compactMatch[1])
                const normalizedCode = this.normalizedCourseCode(compactMatch[2])

                return courseGroup && normalizedCode ? `${courseGroup}:${normalizedCode}` : ''
            }

            return ''
        },
        overviewCourseItemsForSelectionResolution() {
            return ['missing', 'planned', 'additional'].flatMap(courseGroup =>
                this.overviewCourseGroupSourceCourses(courseGroup)
                    .map(course => ({
                        course: this.normalizedOverviewCourseItem(course, courseGroup),
                        courseGroup,
                    })))
        },
        overviewRawSelectionKeyMatchesCourse(selectionKey, course, courseGroup) {
            const value = String(selectionKey || '').trim()
            const normalizedValue = this.normalizedCourseCode(value)
            const backendKey = String(course?.key || '').trim()

            return value === this.overviewCourseSelectionKey(course, courseGroup)
                || (backendKey && value === backendKey)
                || (normalizedValue && [
                    course?.code,
                    course?.label,
                    course?.name,
                    course?.json_code,
                ].some(courseValue => this.normalizedCourseCode(courseValue) === normalizedValue))
        },
        normalizedOverviewCourseGroupKey(courseGroup) {
            const normalizedCourseGroup = String(courseGroup || '').trim()

            if (normalizedCourseGroup === 'proposed') {
                return 'planned'
            }

            return ['missing', 'planned', 'additional'].includes(normalizedCourseGroup)
                ? normalizedCourseGroup
                : ''
        },
        overviewCourseItemLabel(course) {
            const explicitLabel = String(course?.label || '').trim()

            if (explicitLabel) {
                return explicitLabel
            }

            return [
                String(course?.code || '').trim(),
                String(course?.name || '').trim(),
            ].filter(Boolean).join(' - ') || '-'
        },
        overviewCourseItemMeta(course) {
            return String(course?.hoursMeta || course?.meta || '').trim()
        },
        overviewCourseItemColor(course, courseGroup) {
            if (this.overviewCourseItemUnavailable(course, courseGroup)) {
                return 'error'
            }

            if (courseGroup === 'missing') {
                return 'error'
            }

            if (courseGroup === 'additional') {
                return 'info'
            }

            return 'success'
        },
        overviewCourseItemSelected(course, courseGroup) {
            if (!['missing', 'planned'].includes(courseGroup)) {
                return false
            }

            if (this.overviewCourseItemUnavailable(course, courseGroup)) {
                return false
            }

            return this.automaticTimetableCourseKeys.includes(this.overviewCourseSelectionKey(course, courseGroup))
        },
        overviewSelectedCourseItemsForGroup(courseGroup) {
            return this.overviewCourseGroupItems(courseGroup)
                .filter(course => this.overviewCourseItemSelected(course, courseGroup))
        },
        overviewSelectedCourseItemsForKeys(courseKeys) {
            const selectedCourseKeys = new Set(this.normalizedOverviewSelectionKeys(courseKeys))

            return [
                ...this.overviewCourseGroupItems('missing'),
                ...this.overviewCourseGroupItems('planned'),
            ].filter(course =>
                selectedCourseKeys.has(this.overviewCourseSelectionKey(course, course?.courseGroup))
                    && !this.overviewCourseItemUnavailable(course, course?.courseGroup))
        },
        overviewCourseGroupAllSelected(courseGroup) {
            const courseItems = this.overviewCourseGroupSelectableItems(courseGroup)

            return courseItems.length > 0 && courseItems.every(course => this.overviewCourseItemSelected(course, courseGroup))
        },
        overviewCourseGroupNoneSelected(courseGroup) {
            const courseItems = this.overviewCourseGroupSelectableItems(courseGroup)

            return courseItems.length > 0 && courseItems.every(course => !this.overviewCourseItemSelected(course, courseGroup))
        },
        overviewCourseGroupSelectableItems(courseGroup) {
            return this.overviewCourseGroupItems(courseGroup)
                .filter(course => !this.overviewCourseItemUnavailable(course, courseGroup))
        },
        overviewCourseGroupSelectionWouldExceedLimit(courseGroup) {
            const courseItems = this.overviewCourseGroupSelectableItems(courseGroup)
            const selectedCourseKeys = [...this.automaticTimetableCourseKeys]

            return courseItems
                .filter(course => !selectedCourseKeys.includes(this.overviewCourseSelectionKey(course, courseGroup)))
                .some((course) => {
                    if (this.overviewCourseSelectionWouldExceedLimit(course, courseGroup, selectedCourseKeys)) {
                        return true
                    }

                    selectedCourseKeys.push(this.overviewCourseSelectionKey(course, courseGroup))

                    return false
                })
        },
        setOverviewCourseGroupSelection(courseGroup, selected) {
            const selectedCourseKeys = [...this.automaticTimetableCourseKeys]

            this.overviewCourseGroupItems(courseGroup).forEach((course) => {
                const courseKey = this.overviewCourseSelectionKey(course, courseGroup)

                if (!courseKey) {
                    return
                }

                const selectedIndex = selectedCourseKeys.indexOf(courseKey)

                if (!selected) {
                    if (selectedIndex !== -1) {
                        selectedCourseKeys.splice(selectedIndex, 1)
                    }

                    return
                }

                if (selectedIndex !== -1 || this.overviewCourseItemSelectionDisabled(course, courseGroup, selectedCourseKeys)) {
                    return
                }

                selectedCourseKeys.push(courseKey)
            })

            this.setAutomaticTimetableCourseKeys(selectedCourseKeys)
        },
        toggleOverviewCourseItem(course, courseGroup) {
            if (!['missing', 'planned'].includes(courseGroup)) {
                return
            }

            if (this.overviewCourseItemSelectionDisabled(course, courseGroup)) {
                return
            }

            const courseKey = this.overviewCourseSelectionKey(course, courseGroup)

            if (!courseKey) {
                return
            }

            const selectedCourseKeys = [...this.automaticTimetableCourseKeys]
            const selectedIndex = selectedCourseKeys.indexOf(courseKey)

            if (selectedIndex !== -1) {
                selectedCourseKeys.splice(selectedIndex, 1)
            } else {
                selectedCourseKeys.push(courseKey)
            }

            this.setAutomaticTimetableCourseKeys(selectedCourseKeys)
        },
        overviewCourseItemSelectionDisabled(course, courseGroup, selectedCourseKeys = this.automaticTimetableCourseKeys) {
            if (!['missing', 'planned'].includes(courseGroup)) {
                return false
            }

            if (this.overviewCourseItemUnavailable(course, courseGroup)) {
                return true
            }

            const courseKey = this.overviewCourseSelectionKey(course, courseGroup)

            if (courseKey && selectedCourseKeys.includes(courseKey)) {
                return false
            }

            return this.overviewCourseSelectionWouldExceedLimit(course, courseGroup, selectedCourseKeys)
        },
        overviewCourseItemSelectionDisabledLabel(course, courseGroup) {
            if (this.overviewCourseItemUnavailable(course, courseGroup)) {
                return 'Kein angebotener Kurs vorhanden'
            }

            if (!this.overviewCourseItemSelectionDisabled(course, courseGroup)) {
                return undefined
            }

            return 'Maximum von 10 Kursen oder 30 Stunden erreicht'
        },
        overviewCourseItemUnavailable(course, courseGroup) {
            if (!['missing', 'planned'].includes(courseGroup)) {
                return false
            }

            if (!this.overviewCourseOfferAvailabilityKnown(course)) {
                return false
            }

            return this.automaticOfferedCourseItemsForSelectedCourse(course).length === 0
        },
        overviewCourseOfferAvailabilityKnown(course = null) {
            const manualTimetableCourseSections = Array.isArray(this.manualTimetableCourseSections)
                ? this.manualTimetableCourseSections
                : []
            const manualTimetableCourses = Array.isArray(this.manualTimetableCourses)
                ? this.manualTimetableCourses
                : []

            return Array.isArray(course?.course_groups)
                || manualTimetableCourseSections.length > 0
                || manualTimetableCourses.length > 0
        },
        overviewCourseSelectionWouldExceedLimit(course, courseGroup, selectedCourseKeys = this.automaticTimetableCourseKeys) {
            if (!['missing', 'planned'].includes(courseGroup)) {
                return false
            }

            if (this.overviewCourseItemUnavailable(course, courseGroup)) {
                return false
            }

            const courseKey = this.overviewCourseSelectionKey(course, courseGroup)

            if (!courseKey || selectedCourseKeys.includes(courseKey)) {
                return false
            }

            const selectedCourseItems = this.overviewSelectedCourseItemsForKeys(selectedCourseKeys)
            const selectedCourseHours = selectedCourseItems.reduce((hours, courseItem) => hours + this.courseHoursNumber(courseItem), 0)

            return selectedCourseItems.length + 1 > 10
                || selectedCourseHours + this.courseHoursNumber(course) > 30
        },
        overviewCourseLimitDefaultCourseItems() {
            return [
                ...this.overviewCourseGroupItems('missing').map(course => ({ course, courseGroup: 'missing' })),
                ...this.overviewCourseGroupItems('planned').map(course => ({ course, courseGroup: 'planned' })),
            ]
        },
        overviewCourseLimitSelectedCourseItems(selectedCourseKeys) {
            const selectedKeys = selectedCourseKeys instanceof Set
                ? selectedCourseKeys
                : new Set(this.normalizedOverviewSelectionKeys(selectedCourseKeys))

            return this.overviewCourseLimitDefaultCourseItems()
                .filter(courseItem => selectedKeys.has(this.overviewCourseSelectionKey(courseItem.course, courseItem.courseGroup)))
        },
        overviewDuplicateModuleCourseItemsForGroup(courseGroup, selectedCourseKeys) {
            const selectedCourseItemsByBase = new Map()
            const selectedKeys = selectedCourseKeys instanceof Set
                ? selectedCourseKeys
                : new Set(this.normalizedOverviewSelectionKeys(selectedCourseKeys))

            this.overviewCourseGroupItems(courseGroup)
                .map(course => ({ course, courseGroup }))
                .filter(courseItem => selectedKeys.has(this.overviewCourseSelectionKey(courseItem.course, courseItem.courseGroup)))
                .forEach((courseItem) => {
                    const baseCode = this.courseBaseCode(courseItem.course)
                    if (!baseCode) {
                        return
                    }

                    selectedCourseItemsByBase.set(baseCode, [
                        ...(selectedCourseItemsByBase.get(baseCode) || []),
                        courseItem,
                    ])
                })

            return [...selectedCourseItemsByBase.values()]
                .flatMap(courseItems => this.higherModuleCourseItems(courseItems))
        },
        higherModuleCourseItems(courseItems) {
            return [...courseItems]
                .sort((firstCourseItem, secondCourseItem) =>
                    this.courseModuleNumber(firstCourseItem.course) - this.courseModuleNumber(secondCourseItem.course))
                .slice(1)
        },
        overviewRankedCourseLimitPreselectionGroup(courseGroup, selectedCourseKeys) {
            const selectedKeys = selectedCourseKeys instanceof Set
                ? selectedCourseKeys
                : new Set(this.normalizedOverviewSelectionKeys(selectedCourseKeys))

            return this.overviewCourseGroupItems(courseGroup)
                .map(course => ({ course, courseGroup }))
                .filter(courseItem => selectedKeys.has(this.overviewCourseSelectionKey(courseItem.course, courseItem.courseGroup)))
                .sort((firstCourseItem, secondCourseItem) =>
                    this.compareCourseLimitPreselectionItems(firstCourseItem.course, secondCourseItem.course))
        },
        compareCourseLimitPreselectionItems(firstCourse, secondCourse) {
            const firstPriority = this.courseLimitBasePriority(firstCourse)
            const secondPriority = this.courseLimitBasePriority(secondCourse)
            if (firstPriority !== secondPriority) {
                return firstPriority - secondPriority
            }

            const firstSemester = this.courseSemesterNumber(firstCourse)
            const secondSemester = this.courseSemesterNumber(secondCourse)
            if (firstSemester !== secondSemester) {
                return secondSemester - firstSemester
            }

            const firstModule = this.courseModuleNumber(firstCourse)
            const secondModule = this.courseModuleNumber(secondCourse)
            if (firstModule !== secondModule) {
                return secondModule - firstModule
            }

            return this.courseItemSortValue(secondCourse).localeCompare(this.courseItemSortValue(firstCourse), 'de-AT', {
                numeric: true,
                sensitivity: 'base',
            })
        },
        overviewCourseItemDefaultSelected(course, courseGroup) {
            return ['missing', 'planned'].includes(courseGroup)
                && this.overviewCourseItemDefaultSelectable(course, courseGroup)
        },
        overviewCourseItemDefaultSelectable(course, courseGroup) {
            if (this.overviewCourseItemUnavailable(course, courseGroup)) {
                return false
            }

            if (courseGroup === 'missing') {
                return !this.negativeCourseBlockedByLowerModule(course)
            }

            if (courseGroup === 'planned') {
                return !this.plannedCourseBlockedByNegativeCourse(course)
            }

            return false
        },
        negativeCourseBlockedByLowerModule(course) {
            const courseBaseCode = this.courseBaseCode(course)
            const courseModuleNumber = this.courseModuleNumber(course)

            if (!courseBaseCode || !courseModuleNumber || courseBaseCode.length <= 1) {
                return false
            }

            return this.overviewCourseGroupItems('missing').some(otherCourse =>
                this.courseBaseCode(otherCourse) === courseBaseCode
                    && this.courseModuleNumber(otherCourse) > 0
                    && this.courseModuleNumber(otherCourse) < courseModuleNumber)
        },
        plannedCourseBlockedByNegativeCourse(course) {
            const courseBaseCode = this.courseBaseCode(course)
            const courseModuleNumber = this.courseModuleNumber(course)

            if (!courseBaseCode || !courseModuleNumber) {
                return false
            }

            return this.overviewCourseGroupItems('missing').some(negativeCourse =>
                this.courseBaseCode(negativeCourse) === courseBaseCode
                    && this.courseModuleNumber(negativeCourse) > 0
                    && this.courseModuleNumber(negativeCourse) < courseModuleNumber)
        },
        courseSemesterNumber(course) {
            const semester = Number(course?.semester || course?.subject_semester || course?.semester_number || 0)
            if (Number.isFinite(semester) && semester > 0) {
                return semester
            }

            return this.courseModuleNumber(course)
        },
        courseModuleNumber(course) {
            const module = Number(this.courseCodeModuleParts(course?.code || course?.label || course?.name || '').module || 0)

            return Number.isFinite(module) && module > 0 ? module : 0
        },
        courseBaseCode(course) {
            const parts = this.courseCodeModuleParts(course?.code || course?.label || course?.name || '')

            return parts.module ? parts.base : this.normalizedCourseCode(course?.code || course?.label || course?.name || '')
        },
        courseCodeAliases(value) {
            const compactCourseCodeAliases = new Set(this.compactCourseCodeAliasesFromValue(value))

            return this.courseCodeAliasParts(value)
                .map(courseCode => this.normalizedCourseCode(courseCode))
                .flatMap((courseCode) => {
                    if (compactCourseCodeAliases.has(courseCode)) {
                        return [courseCode]
                    }

                    const { base, module } = this.courseCodeModuleParts(courseCode)

                    return this.courseBaseAliases(base).map(baseAlias => `${baseAlias}${module}`)
                })
                .filter(Boolean)
                .filter((courseCode, index, courseCodes) => courseCodes.indexOf(courseCode) === index)
        },
        courseCodeAliasParts(value) {
            const courseCodeParts = String(value || '')
                .split('/')
                .map(part => part.trim())
                .filter(Boolean)
            const inheritedModule = [...courseCodeParts]
                .reverse()
                .map(part => this.courseCodeModuleParts(part).module)
                .find(Boolean) || ''

            return courseCodeParts
                .flatMap((courseCode) => {
                    const { base, module } = this.courseCodeModuleParts(courseCode)
                    const compactBaseAliases = this.compactCourseCodeBaseAliases(base)
                    const resolvedModule = module || inheritedModule

                    if (compactBaseAliases.length) {
                        return compactBaseAliases.map(baseAlias => `${baseAlias}${resolvedModule}`)
                    }

                    return [`${base}${resolvedModule}`]
                })
                .filter(Boolean)
                .filter((courseCode, index, courseCodes) => courseCodes.indexOf(courseCode) === index)
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
                .map(part => part.trim())
                .filter(Boolean)
            const inheritedModule = [...courseCodeParts]
                .reverse()
                .map(part => this.courseCodeModuleParts(part).module)
                .find(Boolean) || ''

            return courseCodeParts
                .flatMap((courseCode) => {
                    const { base, module } = this.courseCodeModuleParts(courseCode)
                    const compactBaseAliases = this.compactCourseCodeBaseAliases(base)
                    const resolvedModule = module || inheritedModule

                    return compactBaseAliases.map(baseAlias => `${baseAlias}${resolvedModule}`)
                })
                .filter(Boolean)
                .filter((courseCode, index, courseCodes) => courseCodes.indexOf(courseCode) === index)
        },
        readableCourseCodeLabel(value) {
            const rawValue = String(value || '').trim()
            const normalizedValue = this.normalizedCourseCode(rawValue)
            const { base, module } = this.courseCodeModuleParts(normalizedValue)
            const compactBaseAliases = this.compactCourseCodeBaseAliases(base)

            if (compactBaseAliases.length) {
                return `${compactBaseAliases.join('/')}${module}`
            }

            return rawValue.toLocaleUpperCase('de-AT')
        },
        courseBaseAliases(base) {
            const normalizedBase = this.normalizedCourseCode(base)
            const mappedAliases = {
                ET: ['ETH'],
                ETH: ['ET'],
                GPB: ['GS'],
                GS: ['GPB', 'GSGPB'],
                GSGPB: ['GS'],
                GW: ['GWB'],
                GWB: ['GW'],
                LPT: ['LET'],
                LET: ['LPT'],
                ME: ['MU'],
                MU: ['ME'],
                R: ['RK'],
                RK: ['R'],
                S: ['SPA'],
                SPA: ['S'],
            }

            return [...new Set([normalizedBase, ...(mappedAliases[normalizedBase] || [])].filter(Boolean))]
        },
        courseLimitBasePriority(course) {
            const baseCode = this.courseBaseCode(course)
            const coreCoursePriority = ['L', 'F', 'S', 'E', 'ETH', 'M', 'D'].indexOf(baseCode)

            return coreCoursePriority === -1 ? 0 : coreCoursePriority + 1
        },
        courseItemSortValue(course) {
            return String(course?.code || course?.label || course?.name || '').trim()
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
        automaticCourseItemIsDistanceLearning(course) {
            if (
                course?.distanceLearning === true
                || course?.distance_learning === true
                || course?.isDistanceLearningCourse === true
                || course?.is_distance_learning_course === true
            ) {
                return true
            }

            const courseGroups = Array.isArray(course?.course_groups)
                ? course.course_groups
                : []
            const requiredSlotCount = Math.max(1, Math.round(this.courseHoursNumber(course)))
            const scheduledWeeklyLoad = this.courseGroupsScheduledWeeklyLoad(courseGroups)

            return requiredSlotCount >= 2
                && scheduledWeeklyLoad > 0
                && Math.abs((scheduledWeeklyLoad * 2) - requiredSlotCount) < 0.001
        },
        courseGroupsScheduledWeeklyLoad(courseGroups) {
            return (Array.isArray(courseGroups) ? courseGroups : [])
                .filter(courseGroup => !this.courseGroupIsOccasional(courseGroup))
                .reduce((load, courseGroup) => load + (1 / this.courseGroupWeekInterval(courseGroup)), 0)
        },
        courseGroupIsOccasional(courseGroup) {
            const recurrenceType = String(courseGroup?.recurrence_type || '').toLowerCase()

            return recurrenceType === 'single'
                || recurrenceType === 'block'
                || Number(courseGroup?.dates_count || 0) === 1
        },
        courseGroupWeekInterval(courseGroup) {
            const explicitInterval = Number(courseGroup?.recurrence_interval)
            if (Number.isInteger(explicitInterval) && explicitInterval > 0) {
                return explicitInterval
            }

            const labelMatch = String(courseGroup?.recurrence_label || '').match(/(\d+)\s*-\s*w/iu)
            if (labelMatch) {
                return Number(labelMatch[1])
            }

            return 1
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

            if (Number.isFinite(numericHours) && numericHours > 0) {
                return numericHours
            }

            const hoursMatch = String(course?.meta || course?.hours_label || '').match(/(\d+(?:[,.]\d+)?)\s*Std/iu)

            return hoursMatch ? Number(hoursMatch[1].replace(',', '.')) : 0
        },
        formatNumber(value) {
            return Number(value || 0).toLocaleString('de-AT')
        },
        formatHours(value) {
            const hours = Number(value || 0)

            if (!Number.isFinite(hours)) {
                return '0'
            }

            return Number.isInteger(hours) ? String(hours) : hours.toLocaleString('de-AT', { maximumFractionDigits: 2 })
        },
        courseKey(sectionKey, course) {
            return [sectionKey, course.code || '', course.name || '', course.semester || '', course.grade || ''].join('|')
        },
        courseHistoryItems(sectionKey, fallbackCourses) {
            const section = this.courseSections.find(courseSection => String(courseSection?.key || '') === sectionKey)

            if (Array.isArray(section?.items)) {
                return section.items
            }

            return Array.isArray(fallbackCourses) ? fallbackCourses : []
        },
        courseHistoryCourseLabel(course) {
            const explicitLabel = String(course?.label || '').trim()

            if (explicitLabel) {
                return explicitLabel
            }

            const code = String(course?.code || '').trim()
            const name = String(course?.name || '').trim()

            if (code && name && code.toLowerCase() === name.toLowerCase()) {
                return code
            }

            return [code, name].filter(Boolean).join(' - ')
        },
        courseHistoryCourseMeta(course) {
            return String(course?.meta || course?.grade || '').trim()
        },
        courseHoursLabel(course) {
            const hours = course.hours ?? course.hours_per_week

            if (hours === null || hours === undefined || hours === '') {
                return null
            }

            const numericHours = Number(hours)

            if (!Number.isFinite(numericHours)) {
                return null
            }

            return `${new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 }).format(numericHours)} Std.`
        },
    },
}
</script>

<style scoped>
.logout-btn {
    color: var(--charcoal);
    font-weight: 700;
    border-radius: 999px;
}

.hero-badge {
    background: rgba(219, 234, 254, 0.82);
    border-color: rgba(37, 99, 235, 0.28);
    color: #1e3a8a;
}

.hero-badge.dark {
    color: #1d4ed8;
    background: rgba(37, 99, 235, 0.2);
    border-color: rgba(37, 99, 235, 0.5);
}

.hero-course-history {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
    margin-top: 12px;
}

.hero-course-history__section {
    display: grid;
    gap: 7px;
    min-width: 0;
    padding: 10px 12px;
    border: 1px solid rgba(23, 45, 64, 0.16);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.72);
}

.hero-course-history__title {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #10263a;
    font-size: 0.88rem;
    font-weight: 850;
}

.hero-course-history__list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.hero-course-history__item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 100%;
    padding: 4px 8px;
    border: 1px solid rgba(14, 165, 233, 0.18);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.78);
    color: #10263a;
    font-size: 0.74rem;
    font-weight: 760;
    line-height: 1.2;
}

.hero-course-history__item > span:first-child {
    min-width: 0;
    overflow-wrap: anywhere;
}

.hero-course-history__item--completed {
    border-color: rgba(22, 163, 74, 0.24);
    background: rgba(240, 253, 244, 0.82);
}

.hero-course-history__item--missing {
    border-color: rgba(220, 38, 38, 0.22);
    background: rgba(254, 242, 242, 0.82);
}

.hero-course-history__empty {
    color: rgba(16, 38, 58, 0.72);
    font-size: 0.76rem;
    font-weight: 760;
}

.hero-course-history__selection-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.hero-course-history__selection-chip {
    border: 1px solid rgba(14, 165, 233, 0.28);
    background: #f0f9ff !important;
    color: #0f172a !important;
    font-weight: 700;
}

.hero-logout-row {
    margin-top: 12px;
    display: flex;
    justify-content: flex-end;
}

.overview-selection {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 20px;
}

.overview-selected-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 8px;
    min-width: 0;
    width: 100%;
}

.overview-selected-card {
    min-width: 0;
    width: 100%;
    padding: 9px 11px;
    border: 1px solid rgba(var(--v-theme-primary), 0.26);
    border-radius: 6px;
    background: #f8fafc;
}

.overview-selected-card--overridden {
    border-color: rgba(var(--v-theme-primary), 0.52);
    background: #eef6ff;
}

.overview-selected-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    min-height: 24px;
}

.overview-selected-card__label {
    color: #172554;
    font-size: 0.7rem;
    font-weight: 850;
}

.overview-selected-card__meta {
    margin-bottom: 3px;
    color: #64748b;
    font-size: 0.64rem;
    font-weight: 850;
    line-height: 1.1;
}

.overview-selected-card__value {
    margin-top: 4px;
    color: #020617;
    font-size: 0.78rem;
    font-weight: 800;
    line-height: 1.15;
    overflow-wrap: anywhere;
}

.overview-selection__reset {
    flex: 0 0 auto;
    min-height: 42px;
    padding-inline: 18px;
    font-weight: 900;
}

.overview-course-selection {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 18px;
}

.overview-course-selection__toolbar {
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-end;
    min-width: 0;
}

.overview-course-selection__toolbar :deep(.v-btn) {
    font-weight: 900;
    letter-spacing: 0;
}

.overview-course-selection__card {
    display: flex;
    flex-direction: column;
    min-width: 0;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 8px;
    background: #f8fafc;
}

.overview-course-selection__title {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    padding: 12px 12px 8px;
    color: #10263a;
    font-size: 0.94rem;
    font-weight: 900;
    line-height: 1.15;
}

.overview-course-selection__title-meta {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 6px;
}

.overview-course-selection__chips,
.overview-course-selection__actions,
.overview-course-selection__summary-content {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.overview-course-selection__card :deep(.v-card-text) {
    flex: 1;
    padding: 4px 12px 10px;
}

.overview-course-selection__list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.overview-course-selection__item {
    max-width: 100%;
    border-radius: 8px;
    font-weight: 700;
}

.overview-course-selection__item--missing {
    border-color: rgba(220, 38, 38, 0.18);
    background: rgba(254, 242, 242, 0.78);
}

.overview-course-selection__item--planned {
    border-color: rgba(22, 163, 74, 0.18);
    background: rgba(240, 253, 244, 0.78);
}

.overview-course-selection__item--additional {
    border-color: rgba(2, 136, 209, 0.18);
    background: rgba(240, 249, 255, 0.78);
}

.overview-course-selection__item span {
    min-width: 0;
    overflow-wrap: anywhere;
}

.overview-course-selection__item--deselected {
    border-color: rgba(100, 116, 139, 0.28);
    background: #ffffff;
    color: #334155;
    box-shadow: none;
}

.overview-course-selection__item--selected {
    border-color: #15803d !important;
    background: #16a34a !important;
    color: #ffffff !important;
    box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.34);
}

.overview-course-selection__item--selected .overview-course-selection__item-meta {
    background: rgba(255, 255, 255, 0.22);
    color: #ffffff;
}

.overview-course-selection__item--static {
    cursor: default;
}

.overview-course-selection__item--limit-disabled {
    cursor: not-allowed;
    border-color: rgba(14, 116, 144, 0.42);
    color: #155e75;
}

.overview-course-selection__item--limit-disabled.overview-course-selection__item--deselected {
    background: #ffffff;
}

.overview-course-selection__item--unavailable {
    cursor: not-allowed;
    border-color: rgba(220, 38, 38, 0.42);
    background: rgba(254, 226, 226, 0.96);
    color: #991b1b;
}

.overview-course-selection__item--unavailable.overview-course-selection__item--deselected {
    background: rgba(254, 226, 226, 0.96);
}

.overview-course-selection__item-meta {
    border-radius: 999px;
    padding: 1px 6px;
    background: rgba(255, 255, 255, 0.72);
    font-size: 0.7rem;
    font-weight: 850;
}

.overview-course-selection__footer {
    min-height: 0;
    padding: 0 12px 10px;
    color: rgba(16, 38, 58, 0.62);
    font-size: 0.72rem;
    font-weight: 760;
}

.overview-course-selection__summary,
.overview-course-selection__limit-alert {
    grid-column: 1 / -1;
}

.overview-course-selection__summary-content {
    font-weight: 850;
}

.students-timetable-v2-card {
    display: flex;
    flex: 1 1 auto;
    min-width: 0;
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

.students-timetable-v2-course-card-footer {
    min-height: 0;
    color: rgba(15, 23, 42, 0.62);
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1.25;
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

.students-timetable-v2-completed-courses__item--planned {
    border-color: rgba(22, 163, 74, 0.18);
    background: rgba(240, 253, 244, 0.78);
}

.students-timetable-v2-completed-courses__item--additional {
    border-color: rgba(2, 136, 209, 0.18);
    background: rgba(240, 249, 255, 0.78);
}

.students-timetable-v2-completed-courses__item--toggle {
    cursor: pointer;
}

.students-timetable-v2-completed-courses__item--static {
    cursor: default;
}

.students-timetable-v2-completed-courses__item--deselected {
    border-color: rgba(100, 116, 139, 0.28);
    background: #ffffff;
    color: #334155;
    box-shadow: none;
}

.students-timetable-v2-completed-courses__item--limit-disabled {
    cursor: not-allowed;
    border-color: rgba(14, 116, 144, 0.42);
    color: #155e75;
}

.students-timetable-v2-completed-courses__item--limit-disabled.students-timetable-v2-completed-courses__item--deselected {
    background: #ffffff;
}

.students-timetable-v2-completed-courses__item--unavailable {
    cursor: not-allowed;
    border-color: rgba(220, 38, 38, 0.42);
    background: rgba(254, 226, 226, 0.96);
    color: #991b1b;
}

.students-timetable-v2-completed-courses__item--unavailable.students-timetable-v2-completed-courses__item--deselected {
    background: rgba(254, 226, 226, 0.96);
}

.students-timetable-v2-completed-courses__item-meta {
    border-radius: 999px;
    padding: 1px 6px;
    background: rgba(255, 255, 255, 0.72);
    font-size: 0.7rem;
    font-weight: 800;
}

.students-timetable-v2-completed-courses__alert {
    margin-top: 2px;
}

.automatic-course-review-card {
    border: 1px solid rgba(14, 165, 233, 0.16);
    background: #ffffff;
}

.automatic-course-review-card__title {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 16px 20px 8px;
}

.automatic-course-review-card__filters {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.automatic-course-review-card__summary {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-left: auto;
}

.automatic-course-review-card__list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.automatic-course-loading-card {
    border: 1px solid rgba(37, 99, 235, 0.18);
    background: rgba(255, 255, 255, 0.96);
}

.automatic-course-loading-card__content {
    display: flex;
    align-items: center;
    gap: 14px;
    min-height: 96px;
}

.automatic-course-loading-card__copy {
    display: grid;
    gap: 4px;
}

.automatic-course-loading-card__title {
    color: #0f172a;
    font-size: 1rem;
    font-weight: 900;
    line-height: 1.2;
}

.automatic-course-loading-card__subtitle {
    color: rgba(15, 23, 42, 0.68);
    font-size: 0.86rem;
    font-weight: 750;
    line-height: 1.35;
}

.automatic-course-review-card__course {
    cursor: pointer;
    font-weight: 850;
}

.automatic-course-review-card__course--active {
    border-color: #15803d !important;
    background: #16a34a !important;
    color: #ffffff !important;
    box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.34);
}

.automatic-course-review-card__course--distance-learning {
    border: 1px solid rgba(14, 165, 233, 0.3);
}

.automatic-course-review-card__course--offered-partial {
    border: 1px solid rgba(217, 119, 6, 0.36);
    background: rgba(255, 251, 235, 0.96) !important;
    color: #92400e !important;
}

.automatic-course-review-card__course--offered-deselected {
    border: 1px solid rgba(220, 38, 38, 0.42);
    background: rgba(254, 226, 226, 0.96) !important;
    color: #991b1b !important;
}

.automatic-course-review-card__meta {
    margin-left: 6px;
    font-weight: 900;
}

.students-timetable-v2-selected-courses-card__title {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
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

.students-timetable-v2-selected-courses-card__summary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-left: auto;
}

.students-timetable-v2-selected-courses-card__course {
    cursor: pointer;
    font-weight: 400;
}

.students-timetable-v2-selected-courses-card__course--passive {
    cursor: default;
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

.students-timetable-v2-selected-courses-card__course--offered-deselected {
    border: 1px solid rgba(220, 38, 38, 0.42);
    background: rgba(254, 226, 226, 0.96) !important;
    color: #991b1b !important;
}

.students-timetable-v2-selected-courses-card__meta {
    margin-left: 6px;
    font-weight: 900;
}

.automatic-course-review-card__footer {
    min-height: 0;
    padding: 4px 20px 18px;
    color: rgba(15, 23, 42, 0.68);
    font-size: 0.82rem;
    font-weight: 850;
}

.automatic-offered-courses-card {
    margin-top: 12px;
    border: 1px solid rgba(14, 165, 233, 0.16);
    background: #ffffff;
}

.automatic-offered-courses-card__title {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 16px 20px 8px;
    color: #0f172a;
    font-size: 1.04rem;
    font-weight: 900;
    line-height: 1.2;
}

.automatic-offered-courses-card__list {
    display: grid;
    gap: 8px;
}

.automatic-offered-courses-card__item {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    border: 1px solid rgba(71, 85, 105, 0.2);
    border-radius: 8px;
    padding: 8px 10px;
    background: #ffffff;
}

.automatic-offered-courses-card__item--toggle {
    cursor: pointer;
}

.automatic-offered-courses-card__item--selected {
    border-color: rgba(22, 163, 74, 0.36);
    background: rgba(220, 252, 231, 0.9);
    box-shadow: inset 0 0 0 1px rgba(22, 163, 74, 0.12);
}

.automatic-offered-courses-card__item--deselected {
    border-color: rgba(220, 38, 38, 0.28);
    background: rgba(254, 242, 242, 0.9);
    color: #7f1d1d;
}

.automatic-offered-courses-card__item--deselected .automatic-offered-courses-card__name {
    color: #7f1d1d;
}

.automatic-offered-courses-card__name {
    color: #0f172a;
    font-weight: 800;
}

.automatic-offered-courses-card__code {
    color: rgba(15, 23, 42, 0.62);
    font-weight: 800;
}

.students-timetable-v2-restart-card__content {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: space-between;
}

.students-timetable-v2-restart-card {
    margin-top: 18px;
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

.students-timetable-v2-selected-courses-card__bulk-button,
.students-timetable-v2-restart-card__primary-button {
    color: #3949AB !important;
    font-weight: 400;
}

.students-timetable-v2-card-column {
    display: flex !important;
    align-self: stretch;
    min-width: 0;
}

.timetable-actions {
    display: flex;
    flex-wrap: nowrap;
    justify-content: flex-end;
    gap: 10px;
    margin: 0 0 20px;
}

.timetable-actions .v-btn {
    flex: 0 0 auto;
    min-width: 0;
}

.timetable-actions :deep(.v-btn__content) {
    white-space: nowrap;
}

.timetable-actions__button {
    overflow: hidden;
    font-weight: 400;
    letter-spacing: 0;
    text-transform: none;
}

.timetable-actions__automatic {
    margin-left: auto;
}

.timetable-actions__personal {
    background: linear-gradient(135deg, #4338ca 0%, #0891b2 100%);
    box-shadow: 0 0 8px rgba(67, 56, 202, 0.28), 0 0 18px rgba(8, 145, 178, 0.16);
}

.timetable-actions__personal:hover {
    box-shadow: 0 0 12px rgba(67, 56, 202, 0.42), 0 0 24px rgba(8, 145, 178, 0.25);
}

.timetable-actions__published {
    background: linear-gradient(135deg, #7c2d12 0%, #ea580c 100%);
    box-shadow: 0 0 8px rgba(234, 88, 12, 0.28), 0 0 18px rgba(124, 45, 18, 0.14);
}

.timetable-actions__published:hover {
    box-shadow: 0 0 12px rgba(234, 88, 12, 0.42), 0 0 24px rgba(124, 45, 18, 0.24);
}

.timetable-actions__stars {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-left: 2px;
}

.timetable-star {
    color: rgba(255, 255, 255, 0.9);
    animation: timetable-star-twinkle 1.8s ease-in-out infinite;
}

.timetable-star--1 {
    animation-delay: 0s;
}

.timetable-star--2 {
    animation-delay: 0.5s;
}

.timetable-star--3 {
    animation-delay: 1.1s;
}

.student-manual-timetable {
    display: grid;
    gap: 16px;
    margin-bottom: 20px;
}

.student-manual-timetable__toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(16, 38, 58, 0.08);
}

.student-manual-timetable__toolbar h3 {
    margin: 0;
}

.student-manual-timetable__toolbar h3 {
    color: #10263a;
    font-size: 1.18rem;
    line-height: 1.2;
}

.student-manual-timetable__toolbar-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}

.student-manual-timetable__view-change {
    border: 1px solid rgba(22, 163, 74, 0.24);
    color: #14532d;
}

.course-choice-restriction-switch {
    display: inline-flex;
    flex: 0 1 auto;
    flex-wrap: wrap;
    height: auto;
    max-width: 100%;
    padding: 3px;
    background: #eef2f7;
    border: 1px solid #dbe4f0;
    border-radius: 10px;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.06);
    gap: 2px;
}

.course-choice-restriction-switch :deep(.v-btn) {
    flex: 1 1 auto;
    min-width: 0;
}

.course-choice-restriction-option {
    min-height: 28px;
    padding: 0 14px;
    border-radius: 7px !important;
    color: #334155;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0;
    text-transform: none;
}

.course-choice-restriction-option :deep(.v-btn__content) {
    white-space: nowrap;
}

.course-choice-restriction-option--active {
    background: #ffffff;
    color: #1d4ed8;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.16);
}

.course-menu-dialog-options {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 14px;
}

.course-choice-panel__semesters {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 10px;
}

.course-choice-semester {
    display: grid;
    gap: 6px;
    min-width: 0;
}

.course-choice-semester__label {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 800;
}

.course-item-chips,
.course-menu-dialog-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.course-menu-dialog-items .course-item-chips {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    align-items: start;
}

.course-menu-dialog-items .course-item-chip {
    justify-content: flex-start;
    width: 100%;
}

.course-item-chip,
.course-menu-dialog-chip {
    font-weight: 650;
}

.course-item-chip--disabled {
    cursor: not-allowed;
    opacity: 0.82;
}

.course-item-chip--disabled :deep(.v-chip__content) {
    pointer-events: none;
}

.course-item-chip__schedule {
    margin-left: 6px;
    color: rgba(var(--v-theme-on-surface), 0.68);
    font-size: 0.72rem;
    font-weight: 700;
}

.course-menu-dialog-chips {
    max-height: 52vh;
    overflow-y: auto;
}

.course-menu-dialog-items {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid rgba(57, 73, 171, 0.16);
}

.student-selected-course-filter-chip {
    min-height: 26px;
    padding-inline: 10px 9px;
    background: #dff3f1 !important;
    color: #00877f !important;
    font-size: 0.76rem;
    font-weight: 400;
}

.student-selected-course-filter-chip :deep(.v-chip__content) {
    gap: 3px;
}

.student-selected-course-filter-chip :deep(.v-chip__close) {
    margin-inline: 4px -2px;
    color: #00877f;
    opacity: 1;
}

.student-selected-course-filter-chip .students-timetable-v2-selected-courses-card__meta {
    margin-left: 0;
    font-weight: 950;
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
    grid-template-columns: repeat(4, minmax(0, 1fr));
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
    border: 1px solid rgba(22, 163, 74, 0.22);
    background: rgba(240, 253, 244, 0.86);
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        background 0.15s ease,
        transform 0.15s ease;
}

.students-timetable-v2-more-adopted-courses-card__course:hover,
.students-timetable-v2-more-adopted-courses-card__course:focus-visible,
.students-timetable-v2-more-adopted-courses-card__course--active {
    border-color: rgba(22, 163, 74, 0.42);
    background: rgba(220, 252, 231, 0.96);
    outline: none;
    transform: translateY(-1px);
}

.students-timetable-v2-more-adopted-courses-card__course-label {
    color: #14532d;
    font-size: 1rem;
    font-weight: 900;
    line-height: 1.2;
    text-align: center;
    overflow-wrap: anywhere;
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

.students-timetable-v2-offered-courses-card__name {
    color: #0f172a;
    font-weight: 800;
}

.student-manual-timetable__layout {
    display: grid;
    grid-template-columns: minmax(260px, 330px) minmax(0, 1fr);
    gap: 16px;
    align-items: start;
}

.student-manual-timetable__courses {
    min-width: 0;
}

.student-manual-timetable__course-section {
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px !important;
    background: #ffffff !important;
    overflow: hidden;
}

.student-manual-timetable__course-section-title {
    min-height: 50px;
    padding: 12px;
}

.student-manual-timetable__course-section-title :deep(.v-expansion-panel-title__overlay) {
    display: none;
}

.student-manual-timetable__course-section-title span {
    flex: 1;
    min-width: 0;
    color: #10263a;
    font-size: 0.9rem;
    font-weight: 850;
}

.student-manual-timetable :deep(.v-expansion-panel-text__wrapper) {
    padding: 0;
}

.student-manual-timetable__course-list {
    display: grid;
}

.student-manual-timetable__course {
    display: grid;
    grid-template-columns: 32px minmax(0, 1fr);
    gap: 8px;
    align-items: center;
    padding: 10px 12px;
    border-top: 1px solid rgba(16, 38, 58, 0.06);
}

.student-manual-timetable__course--disabled {
    opacity: 0.58;
}

.student-manual-timetable__course strong,
.student-manual-timetable__course small {
    display: block;
}

.student-manual-timetable__course strong {
    color: #172d40;
    font-size: 0.9rem;
    line-height: 1.18;
}

.student-manual-timetable__course small {
    margin-top: 2px;
    color: rgba(23, 45, 64, 0.64);
    font-size: 0.72rem;
    font-weight: 700;
}

.student-manual-timetable__preview {
    min-width: 0;
}

.student-manual-timetable__grid-wrap {
    width: 100%;
    overflow-x: auto;
}

.student-manual-timetable__grid {
    --manual-timetable-weekday-count: 5;
    display: grid;
    grid-template-columns: 82px repeat(var(--manual-timetable-weekday-count), minmax(112px, 1fr));
    width: 100%;
    min-width: calc(82px + (112px * var(--manual-timetable-weekday-count)));
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px;
    background: #ffffff;
    overflow: hidden;
}

.student-manual-timetable__corner,
.student-manual-timetable__weekday,
.student-manual-timetable__hour,
.student-manual-timetable__cell {
    border-right: 1px solid rgba(16, 38, 58, 0.07);
    border-bottom: 1px solid rgba(16, 38, 58, 0.07);
}

.student-manual-timetable__corner,
.student-manual-timetable__weekday,
.student-manual-timetable__hour {
    background: #f8fafc;
}

.student-manual-timetable__corner,
.student-manual-timetable__weekday {
    padding: 10px 8px;
    color: #10263a;
    font-size: 0.78rem;
    font-weight: 900;
    text-align: center;
}

.student-manual-timetable__hour {
    display: grid;
    align-content: center;
    gap: 2px;
    min-height: 78px;
    padding: 8px;
    color: #10263a;
}

.student-manual-timetable__hour strong,
.student-manual-timetable__hour span {
    display: block;
}

.student-manual-timetable__hour strong {
    font-size: 0.78rem;
}

.student-manual-timetable__hour span {
    color: rgba(23, 45, 64, 0.62);
    font-size: 0.66rem;
    font-weight: 750;
    line-height: 1.15;
}

.student-manual-timetable__cell {
    display: grid;
    align-content: start;
    gap: 5px;
    min-height: 78px;
    padding: 6px;
    background: #ffffff;
}

.student-manual-timetable__cell--conflict {
    background: #fef2f2;
}

.student-manual-timetable__block {
    display: grid;
    gap: 2px;
    padding: 6px;
    border: 1px solid rgba(15, 118, 110, 0.18);
    border-radius: 6px;
    background: #ecfdf5;
}

.student-manual-timetable__block strong,
.student-manual-timetable__block span,
.student-manual-timetable__block small {
    min-width: 0;
    overflow-wrap: anywhere;
}

.student-manual-timetable__block strong {
    color: #065f46;
    font-size: 0.76rem;
    line-height: 1.12;
}

.student-manual-timetable__block span {
    color: #134e4a;
    font-size: 0.66rem;
    font-weight: 720;
    line-height: 1.16;
}

.student-manual-timetable__block small {
    color: #0f766e;
    font-size: 0.62rem;
    font-weight: 900;
}

.student-manual-timetable__empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 180px;
    padding: 20px;
    border: 1px dashed rgba(16, 38, 58, 0.16);
    border-radius: 8px;
    color: rgba(23, 45, 64, 0.68);
    background: #f8fafc;
    font-size: 0.9rem;
    font-weight: 750;
}

.student-published-timetable {
    display: grid;
    gap: 16px;
    min-width: 0;
}

.student-published-timetable__meta {
    color: rgba(23, 45, 64, 0.72);
    font-size: 0.82rem;
    font-weight: 800;
}

.student-published-timetable__semester,
.student-published-timetable__week {
    display: grid;
    gap: 10px;
    min-width: 0;
}

.student-published-timetable__semester-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.student-published-timetable__semester-title {
    display: grid;
    gap: 2px;
}

.student-published-timetable__semester-head h4 {
    margin: 0;
    color: #10263a;
    font-size: 1rem;
}

.student-published-timetable__semester-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
    margin-left: auto;
}

.student-published-timetable__semester-head span,
.student-published-timetable__week-label {
    color: rgba(23, 45, 64, 0.64);
    font-size: 0.74rem;
    font-weight: 800;
}

.student-published-timetable__actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.student-published-timetable__grid {
    --published-timetable-weekday-count: 5;
    display: grid;
    grid-template-columns: 52px repeat(var(--published-timetable-weekday-count), minmax(86px, 1fr));
    overflow-x: auto;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.9);
}

.student-published-timetable__corner,
.student-published-timetable__weekday,
.student-published-timetable__hour,
.student-published-timetable__cell {
    border-right: 1px solid rgba(16, 38, 58, 0.08);
    border-bottom: 1px solid rgba(16, 38, 58, 0.08);
}

.student-published-timetable__corner,
.student-published-timetable__weekday {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 8px;
    background: rgba(16, 38, 58, 0.06);
    color: #10263a;
    font-size: 0.78rem;
    font-weight: 800;
    text-align: center;
}

.student-published-timetable__hour {
    display: flex;
    align-items: center;
    flex-direction: column;
    justify-content: center;
    gap: 2px;
    min-height: 70px;
    padding: 8px;
    background: rgba(16, 38, 58, 0.06);
    color: #10263a;
}

.student-published-timetable__hour strong {
    font-weight: 900;
    font-size: 0.78rem;
    line-height: 1;
}

.student-published-timetable__hour span {
    color: rgba(23, 45, 64, 0.68);
    font-size: 0.66rem;
    font-weight: 400;
    line-height: 1;
}

.student-published-timetable__cell {
    position: relative;
    min-height: 70px;
    padding: 8px;
    background: rgba(255, 255, 255, 0.74);
}

.student-published-timetable__cell--filled {
    background: rgba(var(--v-theme-success), 0.09);
}

.student-published-timetable__cell--warning {
    background: rgba(254, 243, 199, 0.96);
}

.student-published-timetable__cell--conflict {
    background: rgba(var(--v-theme-error), 0.09);
}

.student-published-timetable__cell--related {
    background: rgba(219, 234, 254, 0.76);
}

.student-published-timetable__cell--additional {
    background: #dbeafe;
}

.student-published-timetable__cell--has-occasional {
    padding-top: 24px;
}

.student-published-timetable__markers {
    position: absolute;
    top: 4px;
    right: 4px;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 3px;
    max-width: calc(100% - 8px);
    pointer-events: none;
}

.student-published-timetable__marker {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    max-width: 100%;
    min-height: 16px;
    padding: 1px 5px;
    border: 1px solid rgba(30, 64, 175, 0.18);
    border-radius: 4px;
    background: #bfdbfe;
    color: #1e3a8a;
    font-size: 0.58rem;
    font-weight: 900;
    line-height: 1;
}

.student-published-timetable__marker--additional {
    border-color: rgba(234, 88, 12, 0.28);
    background: #fed7aa;
    color: #7c2d12;
}

.student-published-timetable__block {
    display: grid;
    gap: 3px;
}

.student-published-timetable__block + .student-published-timetable__block {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed rgba(16, 38, 58, 0.16);
}

.student-published-timetable__block--conflict .student-published-timetable__code {
    color: rgb(var(--v-theme-error));
}

.student-published-timetable__code {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 3px;
    color: rgb(var(--v-theme-success));
    font-size: 0.86rem;
    font-weight: 900;
}

.student-published-timetable__badge {
    display: inline-flex;
    align-items: center;
    min-height: 14px;
    padding: 1px 4px;
    border-radius: 4px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 0.54rem;
    font-weight: 950;
    line-height: 1;
}

.student-published-timetable__badge--additional {
    background: #bfdbfe;
    color: #1d4ed8;
}

.student-published-timetable__details,
.student-published-timetable__distance-learning,
.student-published-timetable__recurrence {
    min-width: 0;
    overflow-wrap: anywhere;
}

.student-published-timetable__details {
    white-space: pre-line;
    color: rgba(23, 45, 64, 0.72);
    font-size: 0.76rem;
    line-height: 1.25;
}

.student-published-timetable__distance-learning {
    color: #92400e;
    font-size: 0.68rem;
    font-weight: 800;
    line-height: 1.25;
}

.student-published-timetable__recurrence {
    color: #166534;
    font-size: 0.68rem;
    font-weight: 800;
    line-height: 1.25;
}

.timetable-date-overview {
    margin-top: 8px;
    padding: 8px 10px;
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 8px;
    background: #ffffff;
    color: #0f172a;
}

.timetable-date-overview__title {
    margin-bottom: 5px;
    font-size: 0.78rem;
    font-weight: 750;
}

.timetable-date-overview__groups,
.timetable-date-overview__group,
.timetable-date-overview__courses,
.timetable-date-overview__course {
    display: grid;
}

.timetable-date-overview__groups {
    gap: 8px;
}

.timetable-date-overview__group {
    gap: 5px;
}

.timetable-date-overview__slot {
    color: #475569;
    font-size: 0.76rem;
    font-weight: 700;
}

.timetable-date-overview__courses {
    gap: 6px;
}

.timetable-date-overview__course {
    gap: 3px;
}

.timetable-date-overview__course-title {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 4px;
    font-size: 0.74rem;
    font-weight: 700;
}

.timetable-date-overview__range {
    color: #64748b;
    font-weight: 650;
}

.timetable-date-overview__dates {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}

.timetable-date-overview__date {
    padding: 1px 5px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #334155;
    font-size: 0.69rem;
    line-height: 1.45;
    white-space: nowrap;
}

@keyframes timetable-star-twinkle {
    0%,
    100% {
        opacity: 0.35;
        transform: scale(0.8);
    }

    50% {
        opacity: 1;
        transform: scale(1.2);
    }
}

@keyframes timetable-automatic-glow {
    0%,
    100% {
        box-shadow: 0 0 8px rgba(37, 99, 235, 0.4), 0 0 20px rgba(37, 99, 235, 0.2);
    }

    50% {
        box-shadow: 0 0 16px rgba(37, 99, 235, 0.7), 0 0 36px rgba(37, 99, 235, 0.35);
    }
}

.summary-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
    align-items: start;
    margin-bottom: 20px;
}

@media (min-width: 900px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 980px) {
    .overview-course-selection {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .students-timetable-v2-more-adopted-courses-card__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 520px) {
    .student-manual-timetable__toolbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .student-manual-timetable__toolbar-actions {
        justify-content: flex-start;
        width: 100%;
    }

    .overview-selected-cards {
        grid-template-columns: 1fr;
    }

    .hero-course-history {
        grid-template-columns: 1fr;
    }

    .overview-course-selection {
        grid-template-columns: 1fr;
    }

    .students-timetable-v2-more-adopted-courses-card__grid {
        grid-template-columns: 1fr;
    }

    .overview-course-selection__title {
        flex-direction: column;
    }

    .automatic-course-review-card__title {
        align-items: flex-start;
        flex-direction: column;
    }

    .automatic-course-review-card__summary {
        margin-left: 0;
    }

    .overview-selection {
        flex-direction: column;
    }
}

@media (max-width: 380px) {
    .lernportal-page {
        padding-right: 0;
        padding-left: 0;
    }

    .content-cover {
        margin-top: 14px;
        gap: 14px;
    }

    .content-card {
        padding: 0 2px;
        border: 0;
        border-radius: 0;
        background: transparent;
        box-shadow: none;
    }

    .content-head,
    .overview-selection,
    .student-manual-timetable__toolbar,
    .student-published-timetable__meta,
    .student-published-timetable__semester-head {
        padding-right: 6px;
        padding-left: 6px;
    }

    .content-head,
    .content-head h2,
    .student-manual-timetable__toolbar h3,
    .student-published-timetable__semester-head h4 {
        color: #f8fafc;
    }

    .content-head .v-icon,
    .student-manual-timetable__toolbar .v-btn,
    .student-manual-timetable__toolbar .v-icon {
        color: #e0f2fe;
    }

    .student-published-timetable__meta,
    .student-published-timetable__semester-head span,
    .student-published-timetable__week-label {
        color: rgba(226, 232, 240, 0.86);
    }

    .student-manual-timetable,
    .student-published-timetable {
        gap: 10px;
    }

    .student-manual-timetable__grid-wrap {
        overflow-x: visible;
    }

    .student-manual-timetable__grid,
    .student-published-timetable__grid {
        grid-template-columns: 30px repeat(var(--manual-timetable-weekday-count, 6), minmax(0, 1fr));
        min-width: 0;
        border-radius: 6px;
    }

    .student-published-timetable__grid {
        grid-template-columns: 30px repeat(var(--published-timetable-weekday-count, 6), minmax(0, 1fr));
        overflow-x: visible;
    }

    .student-manual-timetable__corner,
    .student-manual-timetable__weekday,
    .student-published-timetable__corner,
    .student-published-timetable__weekday {
        padding: 6px 1px;
        font-size: 0.58rem;
        line-height: 1.1;
    }

    .student-manual-timetable__hour,
    .student-published-timetable__hour {
        min-height: 52px;
        padding: 4px 1px;
        text-align: center;
    }

    .student-manual-timetable__hour strong,
    .student-published-timetable__hour strong {
        font-size: 0.58rem;
    }

    .student-manual-timetable__hour span,
    .student-published-timetable__hour span {
        display: none;
    }

    .student-manual-timetable__cell,
    .student-published-timetable__cell {
        gap: 2px;
        min-height: 52px;
        padding: 2px;
    }

    .student-manual-timetable__block,
    .student-published-timetable__marker {
        gap: 1px;
        padding: 3px 2px;
        border-radius: 4px;
    }

    .student-published-timetable__block {
        gap: 1px;
    }

    .student-manual-timetable__block strong,
    .student-published-timetable__code {
        font-size: 0.52rem;
        line-height: 1.05;
    }

    .student-manual-timetable__block span,
    .student-manual-timetable__block small,
    .student-published-timetable__details,
    .student-published-timetable__distance-learning,
    .student-published-timetable__recurrence,
    .student-published-timetable__badge,
    .student-published-timetable__marker {
        font-size: 0.48rem;
        line-height: 1.05;
    }
}

@media (max-width: 980px) {
    .student-manual-timetable__layout {
        grid-template-columns: 1fr;
    }
}

@media (min-width: 521px) and (max-width: 900px) {
    .overview-selected-cards {
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    }
}

.summary-panels :deep(.v-expansion-panel) {
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px !important;
    background: rgba(255, 255, 255, 0.84) !important;
    margin-top: 0 !important;
    overflow: hidden;
}

.summary-head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px;
    border-bottom: 1px solid rgba(16, 38, 58, 0.07);
    min-height: 58px;
}

.summary-head h3 {
    flex: 1;
    margin: 0;
    font-size: 1rem;
    color: #10263a;
}

.summary-panels :deep(.v-expansion-panel-text__wrapper) {
    padding: 0;
}

.course-list {
    display: grid;
}

.course-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(16, 38, 58, 0.06);
}

.course-row:last-child {
    border-bottom: 0;
}

.course-row strong,
.course-row span {
    display: block;
}

.course-row strong {
    color: #172d40;
}

.course-row span {
    margin-top: 2px;
    color: rgba(23, 45, 64, 0.72);
    font-size: 0.86rem;
}

.course-meta {
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
    gap: 6px;
    flex-wrap: wrap;
    min-width: 72px;
}

.empty-state {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 18px 14px;
    color: rgba(23, 45, 64, 0.68);
    font-size: 0.92rem;
}
</style>
