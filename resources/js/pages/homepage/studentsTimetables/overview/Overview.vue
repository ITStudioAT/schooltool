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
                    <div
                        v-for="section in heroCourseHistorySections"
                        :key="section.key"
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
                </div>

                <div class="hero-logout-row">
                    <v-btn class="logout-btn" variant="text" prepend-icon="mdi-logout" @click="handleLogout">Abmelden</v-btn>
                </div>
            </div>
        </section>

        <section class="content-cover">
            <div class="content-card">
                <div class="content-head">
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

                <div class="overview-selection">
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

                <v-expansion-panels
                    v-if="showManualTimetable && courseSections.length"
                    v-model="expandedManualOverviewCoursePanels"
                    class="manual-overview-course-card"
                    flat>
                    <v-expansion-panel value="courses" class="manual-overview-course-card__panel">
                        <v-expansion-panel-title class="manual-overview-course-card__title">
                            <v-icon icon="mdi-book-open-page-variant-outline" size="22" />
                            <h3>Kurse</h3>
                            <v-chip size="small" variant="flat" color="primary">
                                {{ courseSectionTotalCount }}
                            </v-chip>
                        </v-expansion-panel-title>

                        <v-expansion-panel-text>
                            <v-expansion-panels
                                v-model="expandedCourseSections"
                                class="summary-grid summary-panels manual-overview-course-card__sections"
                                multiple
                                flat>
                                <v-expansion-panel
                                    v-for="section in courseSections"
                                    :key="section.key"
                                    :value="section.key"
                                    class="summary-panel">
                                    <v-expansion-panel-title class="summary-head">
                                        <v-icon size="22">{{ section.icon }}</v-icon>
                                        <h3>{{ section.title }}</h3>
                                        <v-chip size="small" variant="flat" :color="section.color">{{ section.items.length }}</v-chip>
                                    </v-expansion-panel-title>

                                    <v-expansion-panel-text>
                                        <div v-if="section.items.length" class="course-list">
                                            <div v-for="course in section.items" :key="courseKey(section.key, course)" class="course-row">
                                                <div>
                                                    <strong>{{ course.code || course.name || '-' }}</strong>
                                                    <span v-if="course.name && course.name !== course.code">{{ course.name }}</span>
                                                </div>
                                                <div class="course-meta">
                                                    <v-chip v-if="courseHoursLabel(course)" size="x-small" color="primary" variant="tonal">
                                                        {{ courseHoursLabel(course) }}
                                                    </v-chip>
                                                    <v-chip v-if="course.semester" size="x-small" variant="tonal">S{{ course.semester }}</v-chip>
                                                    <v-chip v-if="course.grade" size="x-small" color="success" variant="tonal">{{ course.grade }}</v-chip>
                                                    <v-chip v-if="course.branch && course.branch !== 'common'" size="x-small" variant="tonal">{{ course.branch }}</v-chip>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-else class="empty-state">
                                            <v-icon size="20">mdi-information-outline</v-icon>
                                            <span>{{ section.empty }}</span>
                                        </div>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                </v-expansion-panels>

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
                    <v-card
                        v-for="section in overviewCourseSelectionSections"
                        :key="section.key"
                        rounded="lg"
                        variant="flat"
                        class="overview-course-selection__card">
                        <v-card-title class="overview-course-selection__title">
                            <span>{{ section.title }}</span>
                            <span class="overview-course-selection__title-meta">
                                <span class="overview-course-selection__chips">
                                    <v-chip size="x-small" :color="section.color" variant="tonal">
                                        {{ courseItemsSummary(section.selectedItems).countLabel }}
                                    </v-chip>
                                    <v-chip size="x-small" :color="section.color" variant="tonal">
                                        {{ courseItemsSummary(section.selectedItems).hoursLabel }}
                                    </v-chip>
                                </span>
                                <span v-if="section.bulkSelectable" class="overview-course-selection__actions">
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
                            <div class="overview-course-selection__list">
                                <v-chip
                                    v-for="course in section.items"
                                    :key="overviewCourseSelectionKey(course)"
                                    size="small"
                                    :color="overviewCourseItemColor(course, section.key)"
                                    :variant="overviewCourseItemSelected(course, section.key) || overviewCourseItemSelectionDisabled(course, section.key) ? 'tonal' : 'outlined'"
                                    class="overview-course-selection__item"
                                    :class="{
                                        'overview-course-selection__item--selected': overviewCourseItemSelected(course, section.key),
                                        'overview-course-selection__item--deselected': section.selectable && !overviewCourseItemSelected(course, section.key),
                                        'overview-course-selection__item--static': !section.selectable,
                                        'overview-course-selection__item--limit-disabled': overviewCourseItemSelectionDisabled(course, section.key),
                                    }"
                                    :role="section.selectable ? 'button' : undefined"
                                    :aria-pressed="section.selectable ? (overviewCourseItemSelected(course, section.key) ? 'true' : 'false') : undefined"
                                    :aria-disabled="overviewCourseItemSelectionDisabled(course, section.key) ? 'true' : undefined"
                                    :title="overviewCourseItemSelectionDisabledLabel(course, section.key)"
                                    @click="toggleOverviewCourseItem(course, section.key)">
                                    <v-icon v-if="overviewCourseItemSelected(course, section.key)" icon="mdi-check" size="14" />
                                    <span>{{ overviewCourseItemLabel(course) }}</span>
                                    <span v-if="overviewCourseItemMeta(course)" class="overview-course-selection__item-meta">
                                        {{ overviewCourseItemMeta(course) }}
                                    </span>
                                </v-chip>
                            </div>
                        </v-card-text>
                        <v-card-actions class="overview-course-selection__footer">
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

                <div v-if="!showEvaluationSettings && !showManualTimetable" class="timetable-actions">
                    <v-btn
                        class="timetable-actions__button timetable-actions__automatic"
                        variant="flat"
                        prepend-icon="mdi-auto-fix"
                        @click="openAutomaticTimetable">
                        <span>Neuer Stundenplan</span>
                        <span class="timetable-actions__stars" aria-hidden="true">
                            <v-icon icon="mdi-star-four-points" size="10" class="timetable-star timetable-star--1" />
                            <v-icon icon="mdi-star-four-points" size="14" class="timetable-star timetable-star--2" />
                            <v-icon icon="mdi-star-four-points" size="8" class="timetable-star timetable-star--3" />
                        </span>
                    </v-btn>
                    <v-btn
                        class="timetable-actions__button timetable-actions__personal"
                        variant="flat"
                        prepend-icon="mdi-account-calendar"
                        @click="openPersonalTimetable">
                        Mein Stundenplan
                    </v-btn>
                    <v-btn
                        v-if="hasPublishedTimetable"
                        class="timetable-actions__button timetable-actions__published"
                        variant="flat"
                        prepend-icon="mdi-calendar-check"
                        @click="openPublishedTimetable">
                        Von der Schule gespeicherter Stundenplan
                    </v-btn>
                </div>

                <section v-if="showManualTimetable" class="student-manual-timetable">
                    <div class="student-manual-timetable__toolbar">
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
                            <v-btn variant="text" prepend-icon="mdi-arrow-left" @click="closeManualTimetable">Zurück</v-btn>
                            <v-btn
                                v-if="manualTimetableMode === 'personal' && hasPersonalTimetable"
                                color="error"
                                variant="flat"
                                prepend-icon="mdi-trash-can-outline"
                                :disabled="personalTimetableDeleting"
                                @click="openPersonalTimetableDeleteDialog">
                                Löschen
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

                    <div
                        v-if="personalTimetableCourseSelectionVisible"
                        class="student-course-choice-panel">
                        <div class="student-course-choice-panel__header">
                            <div class="student-course-choice-panel__title">
                                <v-icon icon="mdi-format-list-checks" size="18" color="primary" />
                                <span>Kursauswahl</span>
                                <v-tooltip text="Kurs wählen">
                                    <template #activator="{ props }">
                                        <v-btn
                                            v-bind="props"
                                            icon="mdi-plus"
                                            color="primary"
                                            variant="flat"
                                            size="small"
                                            aria-label="Kurs wählen"
                                            @click="openStudentCoursePickerDialog" />
                                    </template>
                                </v-tooltip>
                            </div>
                            <div class="student-course-choice-panel__actions">
                                <v-chip size="x-small" color="primary" variant="tonal">
                                    {{ savedTimetableCourseChips.length }} ausgewählt
                                </v-chip>
                                <v-btn
                                    v-if="savedTimetableCourseChips.length > 0"
                                    size="x-small"
                                    variant="tonal"
                                    color="error"
                                    prepend-icon="mdi-close-circle-outline"
                                    @click="deselectAllSavedTimetableCourseChips">
                                    Alle abwählen
                                </v-btn>
                            </div>
                        </div>

                        <div class="student-selected-course-filter-chips">
                            <v-chip
                                v-for="courseChip in savedTimetableCourseChips"
                                :key="courseChip.key"
                                size="small"
                                :color="courseChip.hasOverlap ? 'warning' : 'success'"
                                variant="tonal"
                                closable
                                class="student-selected-course-filter-chip"
                                @click:close="deselectSavedTimetableCourseChip(courseChip)">
                                <span>{{ courseChip.label }}</span>
                                <span
                                    v-if="courseChip.studentCourseBadge"
                                    :class="[
                                        'student-selected-course-filter-chip__source',
                                        `student-selected-course-filter-chip__source--${courseChip.studentCourseType}`,
                                    ]">
                                    {{ savedTimetableCourseChipBadgeLabel(courseChip) }}
                                </span>
                            </v-chip>
                        </div>
                    </div>

                    <div v-if="publishedTimetableVisible" class="student-published-timetable">
                        <div v-if="publishedTimetableSubtitle" class="student-published-timetable__meta">
                            {{ publishedTimetableSubtitle }}
                        </div>

                        <div
                            v-for="semester in publishedTimetableSemesters"
                            :key="publishedTimetableSemesterKey(semester)"
                            class="student-published-timetable__semester">
                            <div class="student-published-timetable__semester-head">
                                <h4>{{ semester.label || 'Semester' }}</h4>
                                <span v-if="semester.date_range">{{ semester.date_range }}</span>
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
                                            :class="publishedTimetableCellClasses(cell)">
                                            <div
                                                v-for="course in publishedTimetableCellCourses(cell)"
                                                :key="publishedTimetableCourseKey(course)"
                                                class="student-published-timetable__block">
                                                <strong>{{ course.label || '-' }}</strong>
                                                <span v-if="course.details">{{ course.details }}</span>
                                                <small v-if="course.student_course_badge">{{ course.student_course_badge }}</small>
                                            </div>

                                            <div
                                                v-for="marker in publishedTimetableCellMarkers(cell)"
                                                :key="publishedTimetableMarkerKey(marker)"
                                                class="student-published-timetable__marker">
                                                {{ marker.label || marker.title || '-' }}
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
                    v-if="showEvaluationSettings"
                    :initial-step="automaticTimetableStep"
                    :initial-selected-course-keys="automaticTimetableCourseKeys"
                    :initial-selected-quality-criterion-keys="automaticTimetableQualityCriterionKeys"
                    :default-quality-criterion-selection="!automaticTimetableQualityCriteriaSelectionExplicit"
                    :proposed-courses="automaticTimetableSelectableCourses"
                    :course-summary="automaticTimetableCourseSummary"
                    :course-sections="automaticTimetableAllCourseSections"
                    :selection-override="selectionOverridePayload() || {}"
                    @close="closeAutomaticTimetable"
                    @course-selection-change="setAutomaticTimetableCourseKeys"
                    @courses-selected="finishAutomaticTimetable"
                    @quality-criteria-selection-change="setAutomaticTimetableQualityCriterionKeys"
                    @step-change="setAutomaticTimetableStep" />
            </div>
        </section>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useStudentTimetablesUserStore } from '@/stores/studentsTimetables/StudentTimetablesUserStore'
import StudentTimetableEvaluationSettings from '../components/StudentTimetableEvaluationSettings.vue'
import StudentTimetablesNavigationDrawer from '../components/StudentTimetablesNavigationDrawer.vue'
import '../../../../../css/student.css'

const noAutomaticTimetableCourseValue = '__none'
const noAutomaticTimetableQualityCriteriaValue = '__none'
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
            showDrawer: false,
            showEvaluationSettings: false,
            showManualTimetable: false,
            expandedCourseSections: [],
            expandedManualOverviewCoursePanels: [],
            manualExpandedCourseSections: ['missing', 'proposed'],
            manualSelectedCourseKeys: [],
            manualSelectedCourseGroupKeys: [],
            manualTimetableMode: 'manual',
            personalTimetableDeleteDialogOpen: false,
            personalTimetableDeleting: false,
            personalTimetableSaving: false,
            personalTimetableViewChangedMessageVisible: false,
            hiddenSavedTimetableCourseChipKeys: [],
            studentCoursePickerDialogOpen: false,
            selectedStudentCoursePickerTab: 'missing',
            selectedStudentCoursePickerMenuKey: '',
            selectionOverride: {},
            selectionDialogOpen: false,
            selectionDraftKey: '',
            selectionDraftLabel: '',
            selectionDraftValue: null,
        }
    },

    computed: {
        ...mapWritableState(useStudentTimetablesUserStore, ['user', 'overview']),

        automaticTimetableStep() {
            const step = String(this.$route.query.automatic_timetable || '')

            return this.isAutomaticTimetableStep(step) ? step : 'criteria'
        },
        automaticTimetableCourseKeys() {
            const courseKeys = this.$route.query.automatic_timetable_courses
            const courseKeyList = Array.isArray(courseKeys) ? courseKeys : [courseKeys]

            if (courseKeyList.includes(noAutomaticTimetableCourseValue)) {
                return []
            }

            const selectedCourseKeys = courseKeyList
                .map(courseKey => String(courseKey || ''))
                .filter(courseKey => courseKey !== noAutomaticTimetableCourseValue)
                .filter(Boolean)

            return selectedCourseKeys.length || Object.prototype.hasOwnProperty.call(this.$route.query, 'automatic_timetable_courses')
                ? selectedCourseKeys
                : this.overviewDefaultSelectedCourseKeys
        },
        automaticTimetableQualityCriterionKeys() {
            const criterionKeys = this.$route.query.automatic_timetable_criteria
            const criterionKeyList = Array.isArray(criterionKeys) ? criterionKeys : [criterionKeys]

            if (criterionKeyList.includes(noAutomaticTimetableQualityCriteriaValue)) {
                return []
            }

            return criterionKeyList
                .map(criterionKey => String(criterionKey || ''))
                .filter(criterionKey => criterionKey !== noAutomaticTimetableQualityCriteriaValue)
                .filter(Boolean)
        },
        automaticTimetableQualityCriteriaSelectionExplicit() {
            return Object.prototype.hasOwnProperty.call(this.$route.query, 'automatic_timetable_criteria')
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
            return Array.isArray(this.overview?.course_sections) ? this.overview.course_sections : []
        },
        overviewCourseSelectionSections() {
            const sections = [
                {
                    key: 'missing',
                    title: 'Negative Kurse',
                    color: 'error',
                    items: this.overviewCourseGroupItems('missing'),
                    selectedItems: this.overviewSelectedCourseItemsForGroup('missing'),
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
            const selectedCourseKeys = []
            const defaultCourseItems = [
                ...this.overviewCourseGroupItems('missing').map(course => ({ course, courseGroup: 'missing' })),
                ...this.overviewCourseGroupItems('planned').map(course => ({ course, courseGroup: 'planned' })),
            ]

            defaultCourseItems.forEach(({ course, courseGroup }) => {
                if (!this.overviewCourseItemDefaultSelected(course, courseGroup)) {
                    return
                }

                if (this.overviewCourseSelectionWouldExceedLimit(course, courseGroup, selectedCourseKeys)) {
                    return
                }

                selectedCourseKeys.push(this.overviewCourseSelectionKey(course))
            })

            return selectedCourseKeys
        },
        overviewSelectedCourseLimitItems() {
            return this.overviewSelectedCourseItemsForKeys(this.automaticTimetableCourseKeys)
        },
        overviewSelectedCourseLimitSummary() {
            return this.courseItemsSummary(this.overviewSelectedCourseLimitItems)
        },
        overviewSelectedCourseLimitReached() {
            return this.overviewSelectedCourseLimitSummary.count >= 10 || this.overviewSelectedCourseLimitSummary.hours >= 30
        },
        heroCourseHistorySections() {
            return [
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
        },
        courseSectionTotalCount() {
            return this.courseSections.reduce((courseCount, section) => (
                courseCount + (Array.isArray(section?.items) ? section.items.length : 0)
            ), 0)
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
            const automaticSectionKeys = this.automaticTimetableCourseSections
                .map(section => String(section?.key || ''))
                .filter(Boolean)
            const additionalCourseSections = this.courseSections
                .filter(section => String(section?.key || '') === 'additional')
                .filter(section => !automaticSectionKeys.includes(String(section?.key || '')))
            const additionalCourses = Array.isArray(this.overview?.additional_courses)
                ? this.overview.additional_courses
                : []
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
                ...this.automaticTimetableCourseSections,
                ...additionalCourseSections,
                ...syntheticAdditionalCourseSections,
            ]
        },
        automaticTimetableSelectableCourses() {
            return Array.isArray(this.automaticTimetableCourseSelection.courses)
                ? this.automaticTimetableCourseSelection.courses
                : []
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

            return this.savedTimetableCourseChipEntries
                .filter(courseChip => !hiddenCourseChipKeys.has(courseChip.key))
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
                    scheduleLabel: this.studentCoursePickerEntryScheduleLabel(entry),
                }))
        },
        studentCoursePickerCourseMenus() {
            const courseMenus = new Map()

            this.studentCoursePickerCourseGroups.forEach((courseGroup) => {
                const semester = Number(courseGroup?.semester || 1)
                const semesterContext = this.studentCoursePickerSemesterContext(semester)
                const label = this.studentCoursePickerMenuLabel(courseGroup)
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
                .map(courseMenu => ({
                    ...courseMenu,
                    entries: Array.from(courseMenu.entriesByLabel.values())
                        .map(entry => ({
                            ...entry,
                            courseGroupKeys: [...new Set(entry.courseGroupKeys)],
                            courseChipKey: this.studentCoursePickerEntryCourseChipKey(entry),
                            scheduleLabel: this.studentCoursePickerEntryScheduleLabel(entry),
                        }))
                        .sort((leftEntry, rightEntry) => (
                            leftEntry.label.localeCompare(rightEntry.label, 'de-AT', {
                                numeric: true,
                                sensitivity: 'base',
                            })
                        )),
                }))
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
            return Array.isArray(this.manualTimetableSelection.sections)
                ? this.manualTimetableSelection.sections
                : []
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
            await this.studentTimetablesStore.loadOverview()
            this.syncSelectionOverrideFromOverview()
            this.applyCurrentManualTimetableSelection()
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

            if (this.$route.query.automatic_timetable === step) {
                return
            }

            const query = { ...this.$route.query }
            delete query.manual_timetable
            query.automatic_timetable = step

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        setAutomaticTimetableCourseKeys(courseKeys) {
            const selectedCourseKeys = Array.isArray(courseKeys)
                ? courseKeys.map(courseKey => String(courseKey || '')).filter(Boolean)
                : []

            const currentCourseKeys = this.automaticTimetableCourseKeys
            if (JSON.stringify(currentCourseKeys) === JSON.stringify(selectedCourseKeys)) {
                return
            }

            const query = { ...this.$route.query }

            if (selectedCourseKeys.length) {
                query.automatic_timetable_courses = selectedCourseKeys
            } else {
                query.automatic_timetable_courses = noAutomaticTimetableCourseValue
            }

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        setAutomaticTimetableQualityCriterionKeys(criterionKeys) {
            const selectedCriterionKeys = Array.isArray(criterionKeys)
                ? criterionKeys.map(criterionKey => String(criterionKey || '')).filter(Boolean)
                : []

            const currentCriterionKeys = this.automaticTimetableQualityCriterionKeys
            if (
                JSON.stringify(currentCriterionKeys) === JSON.stringify(selectedCriterionKeys)
                && (selectedCriterionKeys.length || this.automaticTimetableQualityCriteriaSelectionExplicit)
            ) {
                return
            }

            const query = { ...this.$route.query }

            if (selectedCriterionKeys.length) {
                query.automatic_timetable_criteria = selectedCriterionKeys
            } else {
                query.automatic_timetable_criteria = noAutomaticTimetableQualityCriteriaValue
            }

            this.$router.push({
                path: this.$route.path,
                query,
            })
        },
        clearAutomaticTimetableStep() {
            if (
                !this.$route.query.automatic_timetable
                && !this.$route.query.automatic_timetable_courses
                && !this.$route.query.automatic_timetable_criteria
            ) {
                return
            }

            const query = { ...this.$route.query }
            delete query.automatic_timetable
            delete query.automatic_timetable_courses
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

                this.showManualTimetable = true
                this.showEvaluationSettings = false
                this.manualTimetableMode = 'personal'
                this.applySavedTimetableSelection()
                this.personalTimetableViewChangedMessageVisible = true

                const query = { ...this.$route.query }
                delete query.automatic_timetable
                delete query.automatic_timetable_courses
                delete query.automatic_timetable_criteria
                query.manual_timetable = 'personal'

                this.$router.push({
                    path: this.$route.path,
                    query,
                })
            } finally {
                this.personalTimetableSaving = false
            }
        },
        dismissPersonalTimetableViewChangeMessage() {
            this.personalTimetableViewChangedMessageVisible = false
        },
        deselectSavedTimetableCourseChip(courseChip) {
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

            if (!courseChipKey) {
                return
            }

            if (entryOption.isActive) {
                this.deselectSavedTimetableCourseChip({ key: courseChipKey })
                return
            }

            this.hiddenSavedTimetableCourseChipKeys = this.hiddenSavedTimetableCourseChipKeys
                .filter(hiddenCourseChipKey => hiddenCourseChipKey !== courseChipKey)
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
                    courseGroup?.time_from
                    || courseGroup?.from
                    || this.configuredSchoolHour(courseGroup?.hour)?.from,
                ),
                until: this.formatTimeValue(
                    courseGroup?.time_until
                    || courseGroup?.until
                    || this.configuredSchoolHour(courseGroup?.hour)?.until,
                ),
            }
        },
        studentCoursePickerCourseGroupMatchesTab(courseGroup) {
            const tab = String(this.selectedStudentCoursePickerTab || 'proposed')
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
        studentCoursePickerEntryLabel(courseGroup) {
            return courseGroup?.display_label || this.manualCourseGroupLabel(courseGroup)
        },
        studentCoursePickerEntryActive(entry) {
            const courseChipKey = String(entry?.courseChipKey || '')

            if (!courseChipKey) {
                return false
            }

            return !this.hiddenSavedTimetableCourseChipKeys.includes(courseChipKey)
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
        savedTimetableCourseChipEntryByTitle(title) {
            const normalizedTitle = String(title || '').trim().toLocaleUpperCase('de-AT')

            if (!normalizedTitle) {
                return null
            }

            return this.savedTimetableCourseChipEntries.find(courseChip => (
                String(courseChip?.title || '').trim().toLocaleUpperCase('de-AT') === normalizedTitle
            )) || null
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
                const from = String(courseGroup?.time_from || '')
                const until = String(courseGroup?.time_until || '')

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
        publishedTimetableCellCourses(cell) {
            const courses = this.publishedTimetableCellRawCourses(cell)

            if (this.manualTimetableMode !== 'personal' || !this.hiddenSavedTimetableCourseChipKeys.length) {
                return courses
            }

            const hiddenCourseChipKeys = new Set(this.hiddenSavedTimetableCourseChipKeys)

            return courses.filter(course => !hiddenCourseChipKeys.has(this.savedTimetableCourseChipKey(course)))
        },
        publishedTimetableCellRawCourses(cell) {
            return Array.isArray(cell?.courses) ? cell.courses : []
        },
        publishedTimetableCellMarkers(cell) {
            return Array.isArray(cell?.markers) ? cell.markers : []
        },
        publishedTimetableCellClasses(cell) {
            const courses = this.publishedTimetableCellCourses(cell)
            const hasMultipleCourses = courses.length > 1

            return {
                'student-published-timetable__cell--filled': courses.length > 0,
                'student-published-timetable__cell--warning': hasMultipleCourses || String(cell?.status || '') === 'warning',
                'student-published-timetable__cell--conflict': !hasMultipleCourses && String(cell?.status || '') === 'conflict',
                'student-published-timetable__cell--related': !hasMultipleCourses && String(cell?.status || '') === 'related',
            }
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
                courseGroup?.weekday || '',
                courseGroup?.hour || '',
                courseGroup?.teacher || '',
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
                courseGroup?.time_from,
                courseGroup?.time_until,
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
            const rooms = Array.isArray(courseGroup?.rooms) ? courseGroup.rooms.join(', ') : ''

            return [
                courseGroup?.display_label,
                courseGroup?.teacher,
                rooms,
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
                courseGroup?.time_from
                || courseGroup?.from
                || this.configuredSchoolHour(hour ?? courseGroup?.hour)?.from,
            )
        },
        manualTimetableHourTimeUntil(courseGroup, hour = null) {
            return this.formatTimeValue(
                courseGroup?.time_until
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
            const sectionKey = courseGroup === 'planned' ? 'proposed' : courseGroup
            const fallbackCourses = this.overviewCourseGroupFallbackCourses(courseGroup)

            return this.sortedCourseItems(this.uniqueCourseItems(
                this.courseHistoryItems(sectionKey, fallbackCourses).map(course => this.normalizedOverviewCourseItem(course))
            ))
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
        normalizedOverviewCourseItem(course) {
            const code = String(course?.code || '').trim()
            const name = String(course?.name || '').trim()
            const label = String(course?.label || code || name || '').trim()
            const hours = this.courseHoursNumber(course)
            const meta = String(course?.hoursMeta || course?.meta || course?.hours_label || (hours ? `${this.formatHours(hours)} Std.` : '') || course?.grade || '').trim()

            return {
                ...course,
                key: String(course?.key || [code, name, course?.semester || '', hours || ''].join('|')).trim(),
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
                const key = this.overviewCourseSelectionKey(course)

                if (key && !courseItemsByKey.has(key)) {
                    courseItemsByKey.set(key, course)
                }
            })

            return Array.from(courseItemsByKey.values())
        },
        sortedCourseItems(courses) {
            return [...(Array.isArray(courses) ? courses : [])].sort((firstCourse, secondCourse) => {
                const firstSemester = Number(firstCourse?.semester || 0)
                const secondSemester = Number(secondCourse?.semester || 0)

                if (firstSemester !== secondSemester) {
                    return firstSemester - secondSemester
                }

                return this.overviewCourseItemLabel(firstCourse).localeCompare(this.overviewCourseItemLabel(secondCourse), 'de-AT', {
                    sensitivity: 'base',
                })
            })
        },
        overviewCourseSelectionKey(course) {
            return String(course?.key || [
                course?.code || '',
                course?.name || '',
                course?.semester || '',
                course?.hours ?? course?.hours_per_week ?? '',
            ].join('|')).trim()
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
            if (this.overviewCourseItemSelectionDisabled(course, courseGroup)) {
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

            return this.automaticTimetableCourseKeys.includes(this.overviewCourseSelectionKey(course))
        },
        overviewSelectedCourseItemsForGroup(courseGroup) {
            return this.overviewCourseGroupItems(courseGroup)
                .filter(course => this.overviewCourseItemSelected(course, courseGroup))
        },
        overviewSelectedCourseItemsForKeys(courseKeys) {
            const selectedCourseKeys = new Set((Array.isArray(courseKeys) ? courseKeys : []).map(courseKey => String(courseKey || '')))

            return [
                ...this.overviewCourseGroupItems('missing'),
                ...this.overviewCourseGroupItems('planned'),
            ].filter(course => selectedCourseKeys.has(this.overviewCourseSelectionKey(course)))
        },
        overviewCourseGroupAllSelected(courseGroup) {
            const courseItems = this.overviewCourseGroupItems(courseGroup)

            return courseItems.length > 0 && courseItems.every(course => this.overviewCourseItemSelected(course, courseGroup))
        },
        overviewCourseGroupNoneSelected(courseGroup) {
            const courseItems = this.overviewCourseGroupItems(courseGroup)

            return courseItems.length > 0 && courseItems.every(course => !this.overviewCourseItemSelected(course, courseGroup))
        },
        overviewCourseGroupSelectionWouldExceedLimit(courseGroup) {
            const courseItems = this.overviewCourseGroupItems(courseGroup)
            const selectedCourseKeys = [...this.automaticTimetableCourseKeys]

            return courseItems
                .filter(course => !selectedCourseKeys.includes(this.overviewCourseSelectionKey(course)))
                .some((course) => {
                    if (this.overviewCourseSelectionWouldExceedLimit(course, courseGroup, selectedCourseKeys)) {
                        return true
                    }

                    selectedCourseKeys.push(this.overviewCourseSelectionKey(course))

                    return false
                })
        },
        setOverviewCourseGroupSelection(courseGroup, selected) {
            const selectedCourseKeys = [...this.automaticTimetableCourseKeys]

            this.overviewCourseGroupItems(courseGroup).forEach((course) => {
                const courseKey = this.overviewCourseSelectionKey(course)

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

            const courseKey = this.overviewCourseSelectionKey(course)

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

            const courseKey = this.overviewCourseSelectionKey(course)

            if (courseKey && selectedCourseKeys.includes(courseKey)) {
                return false
            }

            if (!this.overviewCourseItemDefaultSelectable(course, courseGroup)) {
                return true
            }

            return this.overviewCourseSelectionWouldExceedLimit(course, courseGroup, selectedCourseKeys)
        },
        overviewCourseItemSelectionDisabledLabel(course, courseGroup) {
            if (!this.overviewCourseItemSelectionDisabled(course, courseGroup)) {
                return undefined
            }

            if (!this.overviewCourseItemDefaultSelectable(course, courseGroup)) {
                return 'Durch Kursreihenfolge gesperrt'
            }

            return 'Maximum von 10 Kursen oder 30 Stunden erreicht'
        },
        overviewCourseSelectionWouldExceedLimit(course, courseGroup, selectedCourseKeys = this.automaticTimetableCourseKeys) {
            if (!['missing', 'planned'].includes(courseGroup)) {
                return false
            }

            const courseKey = this.overviewCourseSelectionKey(course)

            if (!courseKey || selectedCourseKeys.includes(courseKey)) {
                return false
            }

            const selectedCourseItems = this.overviewSelectedCourseItemsForKeys(selectedCourseKeys)
            const selectedCourseHours = selectedCourseItems.reduce((hours, courseItem) => hours + this.courseHoursNumber(courseItem), 0)

            return selectedCourseItems.length + 1 > 10
                || selectedCourseHours + this.courseHoursNumber(course) > 30
        },
        overviewCourseItemDefaultSelected(course, courseGroup) {
            return ['missing', 'planned'].includes(courseGroup)
                && this.overviewCourseItemDefaultSelectable(course, courseGroup)
        },
        overviewCourseItemDefaultSelectable(course, courseGroup) {
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
        courseModuleNumber(course) {
            const module = Number(this.courseCodeModuleParts(course?.code || course?.label || course?.name || '').module || 0)

            return Number.isFinite(module) && module > 0 ? module : 0
        },
        courseBaseCode(course) {
            const parts = this.courseCodeModuleParts(course?.code || course?.label || course?.name || '')

            return parts.module ? parts.base : this.normalizedCourseCode(course?.code || course?.label || course?.name || '')
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

            return [
                String(course?.code || '').trim(),
                String(course?.name || '').trim(),
            ].filter(Boolean).join(' - ')
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
    grid-template-columns: repeat(2, minmax(0, 1fr));
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
    grid-template-columns: repeat(5, minmax(128px, 1fr));
    gap: 8px;
    min-width: 0;
}

.overview-selected-card {
    min-width: 0;
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
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 18px;
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
    border-radius: 999px;
    font-weight: 760;
}

.overview-course-selection__item span {
    min-width: 0;
    overflow-wrap: anywhere;
}

.overview-course-selection__item--deselected {
    background: rgba(255, 255, 255, 0.86);
}

.overview-course-selection__item--static {
    cursor: default;
}

.overview-course-selection__item--limit-disabled {
    cursor: not-allowed;
    border-color: rgba(14, 116, 144, 0.42);
    background: rgba(236, 254, 255, 0.96);
    color: #155e75;
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

.manual-overview-course-card {
    margin: -4px 0 18px;
}

.manual-overview-course-card :deep(.v-expansion-panel) {
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px !important;
    background: rgba(255, 255, 255, 0.84) !important;
    overflow: hidden;
}

.manual-overview-course-card__title {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 56px;
    padding: 14px;
}

.manual-overview-course-card__title h3 {
    flex: 1;
    margin: 0;
    color: #10263a;
    font-size: 1rem;
}

.manual-overview-course-card :deep(.v-expansion-panel-text__wrapper) {
    padding: 0;
}

.manual-overview-course-card__sections {
    margin: 0;
    padding: 12px;
}

.timetable-actions {
    display: flex;
    flex-wrap: nowrap;
    gap: 10px;
    margin: 0 0 20px;
}

.timetable-actions .v-btn {
    flex: 1 1 0;
    min-width: 0;
    min-height: 44px;
}

.timetable-actions :deep(.v-btn__content) {
    white-space: normal;
}

.timetable-actions__button {
    overflow: hidden;
    border-radius: 8px;
    color: #ffffff;
    font-weight: 850;
    letter-spacing: 0;
    text-transform: none;
}

.timetable-actions__automatic {
    background: linear-gradient(135deg, #1d4ed8 0%, #6366f1 100%);
    box-shadow: 0 0 8px rgba(37, 99, 235, 0.4), 0 0 20px rgba(37, 99, 235, 0.2);
    animation: timetable-automatic-glow 2s ease-in-out infinite;
}

.timetable-actions__automatic:hover {
    animation: none;
    box-shadow: 0 0 12px rgba(37, 99, 235, 0.6), 0 0 28px rgba(37, 99, 235, 0.35);
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

.student-course-choice-panel {
    display: grid;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid rgba(57, 73, 171, 0.16);
    border-radius: 8px;
    background: #f8fafc;
}

.student-course-choice-panel__header,
.student-course-choice-panel__title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.student-course-choice-panel__header {
    justify-content: space-between;
}

.student-course-choice-panel__title {
    color: #172554;
    font-size: 0.86rem;
    font-weight: 800;
}

.student-course-choice-panel__actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
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

.student-selected-course-filter-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding-top: 2px;
}

.student-selected-course-filter-chip {
    font-weight: 650;
}

.student-selected-course-filter-chip__source {
    margin-left: 6px;
    padding: 0 5px;
    border-radius: 999px;
    font-size: 0.62rem;
    font-weight: 850;
    line-height: 1.25;
}

.student-selected-course-filter-chip__source--missing {
    background: rgba(251, 146, 60, 0.24);
    color: #9a3412;
}

.student-selected-course-filter-chip__source--additional {
    background: rgba(14, 165, 233, 0.22);
    color: #0369a1;
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
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
}

.student-published-timetable__semester-head h4 {
    margin: 0;
    color: #10263a;
    font-size: 1rem;
}

.student-published-timetable__semester-head span,
.student-published-timetable__week-label {
    color: rgba(23, 45, 64, 0.64);
    font-size: 0.74rem;
    font-weight: 800;
}

.student-published-timetable__grid {
    --published-timetable-weekday-count: 5;
    display: grid;
    grid-template-columns: 82px repeat(var(--published-timetable-weekday-count), minmax(112px, 1fr));
    width: 100%;
    overflow-x: auto;
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px;
    background: #ffffff;
}

.student-published-timetable__corner,
.student-published-timetable__weekday,
.student-published-timetable__hour,
.student-published-timetable__cell {
    border-right: 1px solid rgba(16, 38, 58, 0.07);
    border-bottom: 1px solid rgba(16, 38, 58, 0.07);
}

.student-published-timetable__corner,
.student-published-timetable__weekday,
.student-published-timetable__hour {
    background: #f8fafc;
}

.student-published-timetable__corner,
.student-published-timetable__weekday {
    padding: 10px 8px;
    color: #10263a;
    font-size: 0.78rem;
    font-weight: 900;
    text-align: center;
}

.student-published-timetable__hour {
    display: grid;
    align-content: center;
    gap: 2px;
    min-height: 78px;
    padding: 8px;
    color: #10263a;
}

.student-published-timetable__hour strong,
.student-published-timetable__hour span {
    display: block;
}

.student-published-timetable__hour strong {
    font-size: 0.78rem;
}

.student-published-timetable__hour span {
    color: rgba(23, 45, 64, 0.62);
    font-size: 0.66rem;
    font-weight: 750;
    line-height: 1.15;
}

.student-published-timetable__cell {
    display: grid;
    align-content: start;
    gap: 5px;
    min-height: 78px;
    padding: 6px;
    background: #ffffff;
}

.student-published-timetable__cell--warning {
    background: #fed7aa;
    color: #7c2d12;
}

.student-published-timetable__cell--conflict {
    background: #fef2f2;
}

.student-published-timetable__cell--related {
    background: #eff6ff;
}

.student-published-timetable__block,
.student-published-timetable__marker {
    display: grid;
    gap: 2px;
    padding: 6px;
    border: 1px solid rgba(15, 118, 110, 0.18);
    border-radius: 6px;
    background: #ecfdf5;
}

.student-published-timetable__marker {
    border-color: rgba(234, 88, 12, 0.22);
    color: #9a3412;
    background: #ffedd5;
    font-size: 0.7rem;
    font-weight: 850;
}

.student-published-timetable__block strong,
.student-published-timetable__block span,
.student-published-timetable__block small {
    min-width: 0;
    overflow-wrap: anywhere;
}

.student-published-timetable__block strong {
    color: #065f46;
    font-size: 0.76rem;
    line-height: 1.12;
}

.student-published-timetable__block span {
    color: #134e4a;
    font-size: 0.66rem;
    font-weight: 720;
    line-height: 1.16;
}

.student-published-timetable__block small {
    color: #0f766e;
    font-size: 0.62rem;
    font-weight: 900;
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

    .overview-course-selection__title {
        flex-direction: column;
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
    .manual-overview-course-card,
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

    .manual-overview-course-card__title h3,
    .manual-overview-course-card__title .v-icon {
        color: #0f172a;
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
    .student-published-timetable__block,
    .student-published-timetable__marker {
        gap: 1px;
        padding: 3px 2px;
        border-radius: 4px;
    }

    .student-manual-timetable__block strong,
    .student-published-timetable__block strong {
        font-size: 0.52rem;
        line-height: 1.05;
    }

    .student-manual-timetable__block span,
    .student-manual-timetable__block small,
    .student-published-timetable__block span,
    .student-published-timetable__block small,
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
        grid-template-columns: repeat(2, minmax(0, 1fr));
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
