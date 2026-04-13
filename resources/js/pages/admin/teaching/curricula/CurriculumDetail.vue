<template>
    <div class="curriculum-detail">
        <div class="curriculum-detail__header mb-4">
            <v-btn
                variant="tonal"
                color="secondary"
                size="small"
                rounded="xl"
                prepend-icon="mdi-arrow-left"
                class="text-none mb-3"
                :disabled="isPageActionLocked"
                @click="$emit('back')">
                Zurück zur Übersicht
            </v-btn>
            <div class="curriculum-detail__title-row">
                <div>
                    <h2 class="curriculum-detail__title">{{ curriculum.title }}</h2>
                    <p v-if="curriculum.description" class="curriculum-detail__desc">{{ curriculum.description }}</p>
                </div>
                <div class="curriculum-detail__meta d-flex align-center ga-2">
                    <v-chip size="small" color="primary" variant="tonal" class="font-weight-bold">
                        {{ curriculum.semester_count ?? 2 }} Semester
                    </v-chip>
                    <v-chip size="small" color="success" variant="tonal" class="font-weight-bold">
                        {{ freeWeeksCount }} freie Wochen
                    </v-chip>
                    <div class="curriculum-detail__year-picker d-flex align-center ga-1">
                        <v-btn
                            icon="mdi-chevron-left"
                            variant="tonal"
                            color="secondary"
                            size="x-small"
                            :disabled="isPageActionLocked"
                            @click="selectedYear--" />
                        <v-chip
                            size="small"
                            variant="tonal"
                            color="primary"
                            class="font-weight-bold px-3">
                            {{ selectedYear }}/{{ selectedYear + 1 }}
                        </v-chip>
                        <v-btn
                            icon="mdi-chevron-right"
                            variant="tonal"
                            color="secondary"
                            size="x-small"
                            :disabled="isPageActionLocked"
                            @click="selectedYear++" />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="(curriculum.semester_count ?? 2) === 1" class="curriculum-detail__semester-picker mb-4">
            <v-sheet rounded="xl" class="curriculum-detail__picker-sheet pa-3">
                <div class="text-body-2 font-weight-medium mb-2" style="color: #cbd5e1">
                    Welches Semester anzeigen?
                </div>
                <v-btn-toggle
                    v-model="selectedHalf"
                    mandatory
                    color="primary"
                    density="comfortable"
                    rounded="lg"
                    :disabled="isPageActionLocked"
                    class="semester-toggle">
                    <v-btn value="first" variant="outlined" class="text-none px-5 semester-toggle__btn">
                        <v-icon size="16" class="mr-1">mdi-weather-snowy</v-icon>
                        Wintersemester (Sep – Feb)
                    </v-btn>
                    <v-btn value="second" variant="outlined" class="text-none px-5 semester-toggle__btn">
                        <v-icon size="16" class="mr-1">mdi-white-balance-sunny</v-icon>
                        Sommersemester (Feb – Jul)
                    </v-btn>
                </v-btn-toggle>
            </v-sheet>
        </div>

        <div class="curriculum-detail__body">
            <div class="curriculum-detail__calendar">
                <div
                    v-for="(month, idx) in visibleMonths"
                    :key="month.key"
                    class="curriculum-detail__month"
                    :class="{
                        'curriculum-detail__month--with-topics': topicsForMonth(month).length > 0,
                        'curriculum-detail__month--with-exams': monthHasExamEntries(month),
                    }"
                    :style="{ '--month-hue': monthHue(idx) }">
                    <div class="curriculum-detail__month-header">
                        <div class="curriculum-detail__month-name">{{ month.name }}</div>
                        <div class="curriculum-detail__month-year">{{ month.year }}</div>
                    </div>
                    <div v-if="topicsForMonth(month).length" class="curriculum-detail__month-topics">
                        <div class="curriculum-detail__month-topics-label">Themen</div>
                        <div class="curriculum-detail__month-topics-text">
                            <span
                                v-for="entry in topicsForMonth(month)"
                                :key="entry.id"
                                class="curriculum-detail__overview-entry"
                                :class="{ 'curriculum-detail__overview-entry--exam': entry.isExam }">
                                <v-icon
                                    v-if="entry.isExam"
                                    size="13"
                                    class="curriculum-detail__overview-entry-icon">
                                    mdi-clipboard-check-outline
                                </v-icon>
                                <span>{{ entry.title }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="curriculum-detail__weeks">
                        <div
                            v-for="(week, wIdx) in month.weeks"
                            :key="wIdx"
                            class="curriculum-detail__week"
                            :class="{
                                'curriculum-detail__week--current': week.isCurrent,
                                'curriculum-detail__week--free': isFreeWeek(week.weekKey),
                                'curriculum-detail__week--with-topics': topicsForWeek(week.weekKey).length > 0,
                                'curriculum-detail__week--with-exams': weekHasExamEntries(week.weekKey),
                                'curriculum-detail__week--topic-selectable': isWeekSelectableForTopic(week.weekKey),
                                'curriculum-detail__week--topic-selected': isWeekAssignedToActiveTopic(week.weekKey),
                            }"
                            @click="handleWeekClick(week.weekKey)">
                            <div class="curriculum-detail__week-number">
                                <span class="curriculum-detail__week-kw">KW</span>
                                <span class="curriculum-detail__week-num">{{ week.kw }}</span>
                            </div>
                            <div class="curriculum-detail__week-days">
                                <div
                                    v-for="day in week.days"
                                    :key="day.date"
                                    class="curriculum-detail__day"
                                    :class="{
                                        'curriculum-detail__day--today': day.isToday,
                                        'curriculum-detail__day--outside': day.outsideMonth,
                                    }">
                                    <span class="curriculum-detail__day-name">{{ day.dayName }}</span>
                                    <span class="curriculum-detail__day-num">{{ day.dayNum }}</span>
                                </div>
                            </div>
                            <div class="curriculum-detail__week-range">
                                {{ week.rangeLabel }}
                            </div>
                            <div v-if="topicsForWeek(week.weekKey).length" class="curriculum-detail__week-topics">
                                <span
                                    v-for="entry in topicsForWeek(week.weekKey)"
                                    :key="entry.id"
                                    class="curriculum-detail__overview-entry"
                                    :class="{ 'curriculum-detail__overview-entry--exam': entry.isExam }">
                                    <v-icon
                                        v-if="entry.isExam"
                                        size="13"
                                        class="curriculum-detail__overview-entry-icon">
                                        mdi-clipboard-check-outline
                                    </v-icon>
                                    <span>{{ entry.title }}</span>
                                </span>
                            </div>
                            <div class="curriculum-detail__week-actions">
                                <v-btn
                                    v-if="isWeekSelectionActive"
                                    :icon="isWeekAssignedToActiveTopic(week.weekKey) ? 'mdi-check-circle' : 'mdi-circle-outline'"
                                    variant="text"
                                    color="primary"
                                    size="x-small"
                                    :disabled="topicSaving || isEditingTopic || isFreeWeek(week.weekKey)"
                                    :title="isFreeWeek(week.weekKey)
                                        ? 'Freie Wochen können keinem Thema zugeordnet werden'
                                        : (isWeekAssignedToActiveTopic(week.weekKey) ? 'Woche vom Thema entfernen' : 'Woche dem Thema zuordnen')"
                                    @click.stop="handleWeekClick(week.weekKey)" />
                                <v-chip
                                    v-if="isFreeWeek(week.weekKey)"
                                    size="x-small"
                                    color="success"
                                    variant="flat"
                                    class="curriculum-detail__week-chip">
                                    frei
                                </v-chip>
                                <v-btn
                                    :icon="isFreeWeek(week.weekKey) ? 'mdi-calendar-remove-outline' : 'mdi-calendar-plus-outline'"
                                    variant="tonal"
                                    color="success"
                                    size="x-small"
                                    :disabled="isPageActionLocked"
                                    :loading="isWeekSaving(week.weekKey)"
                                    :title="isFreeWeek(week.weekKey) ? 'Freie Woche entfernen' : 'Woche als frei markieren'"
                                    @click.stop="toggleFreeWeek(week.weekKey)" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="curriculum-detail__side-card curriculum-detail__side-card--content">
                <div class="curriculum-detail__side-card-inner">
                    <div class="curriculum-detail__side-card-header">
                        <v-icon size="20" color="#a5b4fc" class="mr-2">mdi-text-box-outline</v-icon>
                        Inhalte
                    </div>
                    <div class="curriculum-detail__side-card-body">
                        <div class="curriculum-detail__content-toolbar">
                            <div>
                                <div class="curriculum-detail__content-count">{{ curriculumTopics.length }} Themen</div>
                            </div>
                            <v-btn
                                variant="flat"
                                color="primary"
                                size="small"
                                rounded="lg"
                                prepend-icon="mdi-plus"
                                class="text-none curriculum-detail__content-add-btn"
                                :disabled="isPageActionLocked"
                                @click="openTopicForm()">
                                Thema
                            </v-btn>
                        </div>

                        <div v-if="curriculumTopics.length" class="curriculum-detail__topic-list mt-4">
                            <div
                                v-for="(topic, topicIndex) in curriculumTopics"
                                :key="topic.id"
                                class="curriculum-detail__topic-item">
                                <div class="curriculum-detail__topic-row">
                                    <div class="curriculum-detail__topic-main">
                                        <div class="curriculum-detail__topic-title-row">
                                            <div class="curriculum-detail__topic-title">{{ topic.title }}</div>
                                        </div>
                                        <div class="curriculum-detail__topic-meta-chips">
                                            <v-chip
                                                v-if="shouldShowAssignmentSummaryChip(topic)"
                                                size="x-small"
                                                color="primary"
                                                :variant="assignmentSummaryVariant(topic)"
                                                :class="{
                                                    'curriculum-detail__topic-summary-chip': true,
                                                    'curriculum-detail__topic-meta-chip--interactive': topic.assignment_type !== 'none',
                                                }"
                                                @click.stop="topic.assignment_type !== 'none' && openTopicAssignmentEditor(topic, topic.assignment_type)">
                                                {{ topicAssignmentSummary(topic) }}
                                            </v-chip>
                                            <template v-else-if="topicInheritedAssignmentChips(topic).length">
                                                <v-chip
                                                    v-for="chip in topicInheritedAssignmentChips(topic)"
                                                    :key="chip.key"
                                                    size="x-small"
                                                    :color="chip.color || 'primary'"
                                                    variant="tonal">
                                                    {{ chip.label }}
                                                </v-chip>
                                            </template>
                                            <template v-if="topic.assignment_type === 'month' && topic.month_keys.length">
                                                <v-chip
                                                    v-for="monthKey in topic.month_keys"
                                                    :key="monthKey"
                                                    size="x-small"
                                                    color="primary"
                                                    variant="flat"
                                                    class="curriculum-detail__topic-meta-chip--interactive"
                                                    @click.stop="openTopicAssignmentEditor(topic, 'month')">
                                                    {{ monthChipLabel(monthKey) }}
                                                </v-chip>
                                            </template>
                                            <template v-else-if="topic.assignment_type === 'weeks' && topic.week_keys.length">
                                                <v-chip
                                                    v-for="weekKey in topic.week_keys"
                                                    :key="weekKey"
                                                    size="x-small"
                                                    :color="weekAssignmentChipColor(weekKey)"
                                                    variant="flat"
                                                    class="curriculum-detail__topic-meta-chip--interactive"
                                                    @click.stop="openTopicAssignmentEditor(topic, 'weeks')">
                                                    {{ weekChipLabel(weekKey) }}
                                                </v-chip>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="curriculum-detail__topic-actions">
                                        <v-btn
                                            icon="mdi-arrow-up"
                                            variant="text"
                                            color="secondary"
                                            size="x-small"
                                            :disabled="topicSaving || isPageActionLocked || topicIndex === 0"
                                            title="Nach oben verschieben"
                                            @click="moveTopic(topic.id, -1)" />
                                        <v-btn
                                            icon="mdi-arrow-down"
                                            variant="text"
                                            color="secondary"
                                            size="x-small"
                                            :disabled="topicSaving || isPageActionLocked || topicIndex === curriculumTopics.length - 1"
                                            title="Nach unten verschieben"
                                            @click="moveTopic(topic.id, 1)" />
                                        <v-btn
                                            icon="mdi-calendar-range-outline"
                                            variant="text"
                                            color="primary"
                                            size="x-small"
                                            :disabled="topicSaving || isEditingTopic || isEditingUnit || (activeTopicAssignmentId !== null && !isTopicAssignmentEditorOpen(topic.id))"
                                            :title="isTopicAssignmentEditorOpen(topic.id) ? 'Datumszuordnung schließen' : 'Datumszuordnung bearbeiten'"
                                            @click="toggleTopicAssignmentEditor(topic)" />
                                        <v-btn
                                            icon="mdi-pencil-outline"
                                            variant="text"
                                            color="primary"
                                            size="x-small"
                                            :disabled="topicSaving || isPageActionLocked"
                                            title="Thema bearbeiten"
                                            @click="openTopicForm(topic)" />
                                        <v-btn
                                            icon="mdi-delete-outline"
                                            variant="text"
                                            color="error"
                                            size="x-small"
                                            :disabled="topicSaving || isPageActionLocked"
                                            title="Thema löschen"
                                            @click="promptDeleteTopic(topic)" />
                                    </div>
                                </div>

                                <div
                                    v-if="isTopicAssignmentEditorOpen(topic.id)"
                                    class="curriculum-detail__topic-assignment-panel">
                                    <div class="curriculum-detail__topic-assignment-options">
                                        <v-btn
                                            variant="tonal"
                                            size="x-small"
                                            rounded="lg"
                                            class="text-none"
                                            :color="activeTopicAssignmentType === 'none' ? 'primary' : 'secondary'"
                                            :disabled="topicSaving || isEditingTopic"
                                            @click="activateTopicAssignmentMode(topic, 'none')">
                                            Keine Zuordnung
                                        </v-btn>
                                        <v-btn
                                            :variant="activeTopicAssignmentType === 'all_weeks' ? 'flat' : 'tonal'"
                                            size="x-small"
                                            rounded="lg"
                                            class="text-none"
                                            :color="activeTopicAssignmentType === 'all_weeks' ? 'primary' : 'secondary'"
                                            :disabled="topicSaving || isEditingTopic"
                                            @click="activateTopicAssignmentMode(topic, 'all_weeks')">
                                            {{ curriculumScopeLabel }}
                                        </v-btn>
                                        <v-btn
                                            variant="tonal"
                                            size="x-small"
                                            rounded="lg"
                                            class="text-none"
                                            :color="activeTopicAssignmentType === 'month' ? 'primary' : 'secondary'"
                                            :disabled="topicSaving || isEditingTopic"
                                            @click="activateTopicAssignmentMode(topic, 'month')">
                                            Monate
                                        </v-btn>
                                        <v-btn
                                            variant="tonal"
                                            size="x-small"
                                            rounded="lg"
                                            class="text-none"
                                            :color="activeTopicAssignmentType === 'weeks' ? 'primary' : 'secondary'"
                                            :disabled="topicSaving || isEditingTopic"
                                            @click="activateTopicAssignmentMode(topic, 'weeks')">
                                            Wochen
                                        </v-btn>
                                        <v-btn
                                            variant="text"
                                            size="x-small"
                                            color="secondary"
                                            class="text-none ml-auto"
                                            :disabled="topicSaving || isEditingTopic"
                                            @click="closeTopicAssignmentEditor">
                                            Schließen
                                        </v-btn>
                                    </div>

                                    <div
                                        v-if="activeTopicAssignmentType === 'month'"
                                        class="curriculum-detail__topic-assignment-months">
                                        <v-chip
                                            v-for="month in assignableMonths"
                                            :key="month.assignmentKey"
                                            size="small"
                                            :color="topic.month_keys.includes(month.assignmentKey) ? 'primary' : 'secondary'"
                                            :variant="topic.month_keys.includes(month.assignmentKey) ? 'flat' : 'outlined'"
                                            class="curriculum-detail__assignment-chip"
                                            @click="toggleTopicMonthAssignment(topic, month.assignmentKey)">
                                            {{ month.name }}
                                        </v-chip>
                                    </div>

                                    <div
                                        v-else-if="activeTopicAssignmentType === 'weeks'"
                                        class="curriculum-detail__topic-assignment-weeks">
                                        <div class="curriculum-detail__topic-assignment-hint">
                                            Wochen links im Kalender anklicken, um sie diesem Thema zuzuordnen.
                                        </div>
                                        <div
                                            v-if="topic.week_keys.length"
                                            class="curriculum-detail__assignment-week-list">
                                            <div
                                                v-for="weekKey in topic.week_keys"
                                                :key="weekKey"
                                                class="curriculum-detail__assignment-week-row">
                                                <v-chip
                                                    size="x-small"
                                                    color="primary"
                                                    variant="flat">
                                                    {{ weekChipLabel(weekKey) }}
                                                </v-chip>
                                                <div class="curriculum-detail__assignment-week-controls">
                                                    <v-btn
                                                        icon="mdi-arrow-up"
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        :disabled="topicSaving || !canShiftWeekSequence(weekKey, -1)"
                                                        title="Eine Woche früher verschieben"
                                                        @click="shiftWeekSequence(weekKey, -1, 'Wochensequenz konnte nicht früher verschoben werden.')" />
                                                    <v-btn
                                                        icon="mdi-arrow-down"
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        :disabled="topicSaving || !canShiftWeekSequence(weekKey, 1)"
                                                        title="Eine Woche später verschieben"
                                                        @click="shiftWeekSequence(weekKey, 1, 'Wochensequenz konnte nicht später verschoben werden.')" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="curriculum-detail__unit-section">
                                    <div class="curriculum-detail__unit-toolbar">
                                        <div class="curriculum-detail__unit-count">{{ topic.units.length }} Einheiten</div>
                                        <v-btn
                                            variant="text"
                                            color="primary"
                                            size="x-small"
                                            rounded="lg"
                                            prepend-icon="mdi-plus"
                                            class="text-none"
                                            :disabled="isPageActionLocked"
                                            @click="openUnitForm(topic.id)">
                                            Einheit
                                        </v-btn>
                                    </div>

                                    <div v-if="topic.units.length" class="curriculum-detail__unit-list mt-3">
                                        <div
                                            v-for="(unit, unitIndex) in topic.units"
                                            :key="unit.id"
                                            class="curriculum-detail__unit-item">
                                            <div class="curriculum-detail__topic-row">
                                                    <div class="curriculum-detail__topic-main">
                                                    <div class="curriculum-detail__unit-title">{{ unit.title }}</div>
                                                    <div class="curriculum-detail__topic-meta-chips">
                                                        <v-chip
                                                            v-if="shouldShowAssignmentSummaryChip(unit)"
                                                            size="x-small"
                                                            color="primary"
                                                            :variant="assignmentSummaryVariant(unit)"
                                                            :class="{
                                                                'curriculum-detail__topic-summary-chip': true,
                                                                'curriculum-detail__topic-meta-chip--interactive': unit.assignment_type !== 'none',
                                                            }"
                                                            @click.stop="unit.assignment_type !== 'none' && openUnitAssignmentEditor(topic, unit, unit.assignment_type)">
                                                            {{ topicAssignmentSummary(unit) }}
                                                        </v-chip>
                                                        <template v-if="unit.assignment_type === 'month' && unit.month_keys.length">
                                                            <v-chip
                                                                v-for="monthKey in unit.month_keys"
                                                                :key="monthKey"
                                                                size="x-small"
                                                                color="primary"
                                                                variant="flat"
                                                                class="curriculum-detail__topic-meta-chip--interactive"
                                                                @click.stop="openUnitAssignmentEditor(topic, unit, 'month')">
                                                                {{ monthChipLabel(monthKey) }}
                                                            </v-chip>
                                                        </template>
                                                        <template v-else-if="unit.assignment_type === 'weeks' && unit.week_keys.length">
                                                            <v-chip
                                                                v-for="weekKey in unit.week_keys"
                                                                :key="weekKey"
                                                                size="x-small"
                                                                :color="weekAssignmentChipColor(weekKey)"
                                                                variant="flat"
                                                                class="curriculum-detail__topic-meta-chip--interactive"
                                                                @click.stop="openUnitAssignmentEditor(topic, unit, 'weeks')">
                                                                {{ weekChipLabel(weekKey) }}
                                                            </v-chip>
                                                        </template>
                                                    </div>
                                                </div>
                                                <div class="curriculum-detail__topic-actions">
                                                    <v-btn
                                                        icon="mdi-arrow-up"
                                                        variant="text"
                                                        color="secondary"
                                                        size="x-small"
                                                        :disabled="topicSaving || isPageActionLocked || unitIndex === 0"
                                                        title="Nach oben verschieben"
                                                        @click="moveUnit(topic.id, unit.id, -1)" />
                                                    <v-btn
                                                        icon="mdi-arrow-down"
                                                        variant="text"
                                                        color="secondary"
                                                        size="x-small"
                                                        :disabled="topicSaving || isPageActionLocked || unitIndex === topic.units.length - 1"
                                                        title="Nach unten verschieben"
                                                        @click="moveUnit(topic.id, unit.id, 1)" />
                                                    <v-btn
                                                        icon="mdi-calendar-range-outline"
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        :disabled="topicSaving || isEditingTopic || isEditingUnit || (activeTopicAssignmentId !== null && !isUnitAssignmentEditorOpen(topic.id, unit.id))"
                                                        :title="isUnitAssignmentEditorOpen(topic.id, unit.id) ? 'Datumszuordnung schließen' : 'Datumszuordnung bearbeiten'"
                                                        @click="toggleUnitAssignmentEditor(topic, unit)" />
                                                    <v-btn
                                                        icon="mdi-pencil-outline"
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        :disabled="topicSaving || isPageActionLocked"
                                                        title="Einheit bearbeiten"
                                                        @click="openUnitForm(topic.id, unit)" />
                                                    <v-btn
                                                        icon="mdi-delete-outline"
                                                        variant="text"
                                                        color="error"
                                                        size="x-small"
                                                        :disabled="topicSaving || isPageActionLocked"
                                                        title="Einheit löschen"
                                                        @click="promptDeleteUnit(topic, unit)" />
                                                </div>
                                            </div>

                                            <div
                                                v-if="isUnitAssignmentEditorOpen(topic.id, unit.id)"
                                                class="curriculum-detail__topic-assignment-panel">
                                                <div class="curriculum-detail__topic-assignment-options">
                                                    <v-btn
                                                        variant="tonal"
                                                        size="x-small"
                                                        rounded="lg"
                                                        class="text-none"
                                                        :color="activeTopicAssignmentType === 'none' ? 'primary' : 'secondary'"
                                                        :disabled="topicSaving || isEditingTopic || isEditingUnit"
                                                        @click="activateUnitAssignmentMode(topic, unit, 'none')">
                                                        Keine Zuordnung
                                                    </v-btn>
                                                    <v-btn
                                                        :variant="activeTopicAssignmentType === 'all_weeks' ? 'flat' : 'tonal'"
                                                        size="x-small"
                                                        rounded="lg"
                                                        class="text-none"
                                                        :color="activeTopicAssignmentType === 'all_weeks' ? 'primary' : 'secondary'"
                                                        :disabled="topicSaving || isEditingTopic || isEditingUnit"
                                                        @click="activateUnitAssignmentMode(topic, unit, 'all_weeks')">
                                                        {{ curriculumScopeLabel }}
                                                    </v-btn>
                                                    <v-btn
                                                        variant="tonal"
                                                        size="x-small"
                                                        rounded="lg"
                                                        class="text-none"
                                                        :color="activeTopicAssignmentType === 'month' ? 'primary' : 'secondary'"
                                                        :disabled="topicSaving || isEditingTopic || isEditingUnit"
                                                        @click="activateUnitAssignmentMode(topic, unit, 'month')">
                                                        Monate
                                                    </v-btn>
                                                    <v-btn
                                                        variant="tonal"
                                                        size="x-small"
                                                        rounded="lg"
                                                        class="text-none"
                                                        :color="activeTopicAssignmentType === 'weeks' ? 'primary' : 'secondary'"
                                                        :disabled="topicSaving || isEditingTopic || isEditingUnit"
                                                        @click="activateUnitAssignmentMode(topic, unit, 'weeks')">
                                                        Wochen
                                                    </v-btn>
                                                    <v-btn
                                                        variant="text"
                                                        size="x-small"
                                                        color="secondary"
                                                        class="text-none ml-auto"
                                                        :disabled="topicSaving || isEditingTopic || isEditingUnit"
                                                        @click="closeTopicAssignmentEditor">
                                                        Schließen
                                                    </v-btn>
                                                </div>

                                                <div
                                                    v-if="activeTopicAssignmentType === 'month'"
                                                    class="curriculum-detail__topic-assignment-months">
                                                    <v-chip
                                                        v-for="month in assignableMonths"
                                                        :key="month.assignmentKey"
                                                        size="small"
                                                        :color="unit.month_keys.includes(month.assignmentKey) ? 'primary' : 'secondary'"
                                                        :variant="unit.month_keys.includes(month.assignmentKey) ? 'flat' : 'outlined'"
                                                        class="curriculum-detail__assignment-chip"
                                                        @click="toggleUnitMonthAssignment(topic, unit, month.assignmentKey)">
                                                        {{ month.name }}
                                                    </v-chip>
                                                </div>

                                                <div
                                                    v-else-if="activeTopicAssignmentType === 'weeks'"
                                                    class="curriculum-detail__topic-assignment-weeks">
                                                    <div class="curriculum-detail__topic-assignment-hint">
                                                        Wochen links im Kalender anklicken, um sie dieser Einheit zuzuordnen.
                                                    </div>
                                                    <div
                                                        v-if="unit.week_keys.length"
                                                        class="curriculum-detail__assignment-week-list">
                                                        <div
                                                            v-for="weekKey in unit.week_keys"
                                                            :key="weekKey"
                                                            class="curriculum-detail__assignment-week-row">
                                                            <v-chip
                                                                size="x-small"
                                                                color="primary"
                                                                variant="flat">
                                                                {{ weekChipLabel(weekKey) }}
                                                            </v-chip>
                                                            <div class="curriculum-detail__assignment-week-controls">
                                                                <v-btn
                                                                    icon="mdi-arrow-up"
                                                                    variant="text"
                                                                    color="primary"
                                                                    size="x-small"
                                                                    :disabled="topicSaving || !canShiftWeekSequence(weekKey, -1)"
                                                                    title="Eine Woche früher verschieben"
                                                                    @click="shiftWeekSequence(weekKey, -1, 'Wochensequenz konnte nicht früher verschoben werden.')" />
                                                                <v-btn
                                                                    icon="mdi-arrow-down"
                                                                    variant="text"
                                                                    color="primary"
                                                                    size="x-small"
                                                                    :disabled="topicSaving || !canShiftWeekSequence(weekKey, 1)"
                                                                    title="Eine Woche später verschieben"
                                                                    @click="shiftWeekSequence(weekKey, 1, 'Wochensequenz konnte nicht später verschoben werden.')" />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div v-else class="curriculum-detail__topic-empty mt-4">
                            Noch keine Themen definiert.
                        </div>
                    </div>
                </div>
            </div>

            <div class="curriculum-detail__side-card">
                <div class="curriculum-detail__side-card-inner">
                    <div class="curriculum-detail__side-card-header">
                        <v-icon size="20" color="#a5b4fc" class="mr-2">mdi-book-open-page-variant-outline</v-icon>
                        Lehrpläne
                    </div>
                    <div class="curriculum-detail__side-card-body">
                        <div v-if="docsLoading && !documents.length" class="text-center py-4">
                            <v-progress-circular indeterminate color="primary" size="24" />
                        </div>

                        <div v-if="documents.length" class="lehrplaene__list mb-3">
                            <div
                                v-for="doc in documents"
                                :key="doc.id"
                                class="lehrplaene__item"
                                :class="{ 'lehrplaene__item--active': previewDoc?.id === doc.id, 'lehrplaene__item--clickable': ['upload', 'material'].includes(doc.source_type) }"
                                @click="selectPreview(doc)">
                                <v-icon
                                    size="16"
                                    :color="doc.source_type === 'material' ? '#818cf8' : '#94a3b8'"
                                    class="mr-2 flex-shrink-0">
                                    {{ doc.source_type === 'material' ? 'mdi-package-variant-closed' : 'mdi-file-document-outline' }}
                                </v-icon>
                                <div class="lehrplaene__item-copy">
                                    <span class="lehrplaene__item-name text-truncate">{{ doc.name }}</span>
                                    <span v-if="doc.source_type === 'material'" class="lehrplaene__item-subtitle text-truncate">
                                        {{ doc.selected_attachment_name || 'Anhang auswählen' }}
                                    </span>
                                </div>
                                <v-chip
                                    v-if="doc.source_type === 'material'"
                                    size="x-small"
                                    color="primary"
                                    variant="tonal"
                                    class="ml-1 flex-shrink-0">
                                    Material
                                </v-chip>
                                <v-btn
                                    icon="mdi-close"
                                    variant="text"
                                    size="x-small"
                                    color="error"
                                    class="ml-auto flex-shrink-0"
                                    :disabled="isPageActionLocked"
                                    title="Entfernen"
                                    @click.stop="removeDocument(doc)" />
                            </div>
                        </div>

                        <div v-if="previewDoc" class="lehrplaene__preview mb-3">
                            <div class="lehrplaene__preview-header">
                                <span class="text-truncate">{{ previewDoc.selected_attachment_name || previewDoc.name }}</span>
                                <v-btn
                                    icon="mdi-close"
                                    variant="text"
                                    size="x-small"
                                    color="secondary"
                                    :disabled="isPageActionLocked"
                                    @click="previewDoc = null" />
                            </div>
                            <div class="lehrplaene__preview-body">
                                <iframe
                                    v-if="previewIsPdf && previewUrl"
                                    :src="previewUrl"
                                    class="lehrplaene__preview-iframe" />
                                <img
                                    v-else-if="previewIsImage && previewUrl"
                                    :src="previewUrl"
                                    class="lehrplaene__preview-image" />
                                <div v-else class="text-center py-6">
                                    <v-icon size="40" color="#475569" class="mb-2">mdi-file-document-outline</v-icon>
                                    <div class="text-caption" style="color: #64748b">Vorschau nicht verfügbar.</div>
                                    <v-btn
                                        v-if="previewDownloadUrl"
                                        variant="tonal"
                                        color="primary"
                                        size="small"
                                        rounded="lg"
                                        class="text-none mt-2"
                                        :disabled="isPageActionLocked"
                                        :href="previewDownloadUrl"
                                        target="_blank">
                                        Herunterladen
                                    </v-btn>
                                </div>
                            </div>
                        </div>

                        <div v-if="!documents.length && !docsLoading" class="text-center py-3">
                            <v-icon size="32" color="#475569" class="mb-1">mdi-file-plus-outline</v-icon>
                            <div class="text-caption" style="color: #64748b">Noch keine Lehrpläne hinzugefügt.</div>
                        </div>

                        <v-divider class="my-2" style="border-color: rgba(148,163,184,0.12)" />

                        <div class="lehrplaene__actions">
                            <v-btn
                                v-if="!showUploadOptions"
                                variant="tonal"
                                color="primary"
                                size="small"
                                rounded="lg"
                                block
                                prepend-icon="mdi-plus"
                                class="text-none"
                                :disabled="isPageActionLocked"
                                @click="showUploadOptions = true">
                                Hinzufügen
                            </v-btn>

                            <div v-if="showUploadOptions" class="lehrplaene__upload-options">
                                <FileUpload
                                    :path="`/api/admin/teaching/curricula/${curriculum.id}/documents/upload`"
                                    :file-label="true"
                                    :short-label="true"
                                    :allowed-file-types="['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/*']"
                                    @fileUploadFinished="onFileUploaded"
                                    @uploadStart="docsLoading = true" />

                                <v-btn
                                    v-if="hasMaterialsAccess"
                                    variant="tonal"
                                    color="primary"
                                    size="small"
                                    rounded="lg"
                                    block
                                    prepend-icon="mdi-package-variant-closed"
                                    class="text-none mt-2"
                                    :disabled="isPageActionLocked"
                                    @click="materialDialogOpen = true">
                                    Aus Materialien wählen
                                </v-btn>

                                <v-btn
                                    variant="text"
                                    size="x-small"
                                    color="secondary"
                                    class="text-none mt-1"
                                    block
                                    :disabled="isPageActionLocked"
                                    @click="showUploadOptions = false">
                                    Abbrechen
                                </v-btn>
                            </div>
                        </div>
                    </div>
                </div>

                <v-dialog v-model="contentDeleteDialogOpen" max-width="420" persistent>
                    <v-card rounded="xl">
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                            <v-icon color="error" size="20">mdi-delete-outline</v-icon>
                            {{ contentToDelete?.type === 'unit' ? 'Einheit löschen' : 'Thema löschen' }}
                        </v-card-title>
                        <v-card-text class="px-4 pb-2">
                            <div class="text-body-2" style="color: #475569">
                                Soll {{ contentToDelete?.type === 'unit' ? 'die Einheit' : 'das Thema' }}
                                <strong>{{ contentToDelete?.title }}</strong>
                                wirklich gelöscht werden?
                            </div>
                        </v-card-text>
                        <v-card-actions class="px-4 pb-4">
                            <v-spacer />
                            <v-btn
                                variant="text"
                                color="secondary"
                                :disabled="topicSaving"
                                @click="closeTopicDeleteDialog">
                                Abbrechen
                            </v-btn>
                            <v-btn
                                color="error"
                                variant="flat"
                                :loading="topicSaving"
                                @click="confirmTopicDelete">
                                Löschen
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

                <v-dialog v-model="showTopicForm" max-width="520" persistent>
                    <v-card rounded="xl" class="curriculum-detail__editor-dialog-card">
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                            <v-icon color="primary" size="20">mdi-text-box-edit-outline</v-icon>
                            {{ topicForm.id ? 'Thema bearbeiten' : 'Thema anlegen' }}
                        </v-card-title>
                        <v-card-text class="px-4 pt-2 pb-2">
                            <div class="curriculum-detail__topic-form curriculum-detail__editor-dialog-form">
                                <v-text-field
                                    v-model="topicForm.title"
                                    label="Thema"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                    class="mb-3" />
                                <div v-if="topicFormError" class="curriculum-detail__topic-form-error mb-3">
                                    {{ topicFormError }}
                                </div>
                                <div class="curriculum-detail__topic-form-actions">
                                    <v-btn
                                        variant="flat"
                                        color="primary"
                                        size="small"
                                        rounded="lg"
                                        class="text-none curriculum-detail__topic-save-btn curriculum-detail__editor-dialog-save-btn"
                                        :loading="topicSaving"
                                        @click="saveTopic">
                                        {{ topicForm.id ? 'Thema speichern' : 'Thema anlegen' }}
                                    </v-btn>
                                    <v-btn
                                        variant="text"
                                        color="secondary"
                                        size="small"
                                        class="text-none curriculum-detail__editor-dialog-cancel-btn"
                                        :disabled="topicSaving"
                                        @click="cancelTopicForm">
                                        Abbrechen
                                    </v-btn>
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-dialog>

                <v-dialog v-model="showUnitForm" max-width="520" persistent>
                    <v-card rounded="xl" class="curriculum-detail__editor-dialog-card">
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                            <v-icon color="primary" size="20">mdi-text-box-edit-outline</v-icon>
                            {{ unitForm.id ? 'Einheit bearbeiten' : 'Einheit anlegen' }}
                        </v-card-title>
                        <v-card-text class="px-4 pt-2 pb-2">
                            <div class="curriculum-detail__topic-form curriculum-detail__unit-form curriculum-detail__editor-dialog-form">
                                <v-text-field
                                    v-model="unitForm.title"
                                    label="Einheit"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                    class="mb-3" />
                                <v-checkbox
                                    v-model="unitForm.is_exam"
                                    label="Prüfung"
                                    color="warning"
                                    density="comfortable"
                                    hide-details
                                    class="curriculum-detail__unit-exam-checkbox mb-3" />
                                <div v-if="unitFormError" class="curriculum-detail__topic-form-error mb-3">
                                    {{ unitFormError }}
                                </div>
                                <div class="curriculum-detail__topic-form-actions">
                                    <v-btn
                                        variant="flat"
                                        color="primary"
                                        size="small"
                                        rounded="lg"
                                        class="text-none curriculum-detail__topic-save-btn curriculum-detail__editor-dialog-save-btn"
                                        :loading="topicSaving"
                                        @click="saveUnit">
                                        {{ unitForm.id ? 'Einheit speichern' : 'Einheit anlegen' }}
                                    </v-btn>
                                    <v-btn
                                        variant="text"
                                        color="secondary"
                                        size="small"
                                        class="text-none curriculum-detail__editor-dialog-cancel-btn"
                                        :disabled="topicSaving"
                                        @click="cancelUnitForm">
                                        Abbrechen
                                    </v-btn>
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-dialog>

                <v-dialog v-model="materialDialogOpen" max-width="560" persistent>
                    <v-card rounded="xl">
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                            <v-icon color="primary" size="22">mdi-package-variant-closed</v-icon>
                            Material auswählen
                        </v-card-title>
                        <v-card-text class="px-4">
                            <v-text-field
                                v-model="materialSearch"
                                label="Material suchen..."
                                variant="outlined"
                                density="compact"
                                hide-details
                                clearable
                                prepend-inner-icon="mdi-magnify"
                                class="mb-3"
                                @update:modelValue="searchMaterials" />
                            <div v-if="materialsLoading" class="text-center py-4">
                                <v-progress-circular indeterminate color="primary" size="24" />
                            </div>
                            <v-list v-else-if="materialResults.length" bg-color="transparent" density="compact" class="py-0" style="max-height: 320px; overflow-y: auto">
                                <v-list-item
                                    v-for="card in materialResults"
                                    :key="card.id"
                                    class="lehrplaene__material-item mb-1 px-3"
                                    rounded="lg"
                                    @click="attachMaterial(card)">
                                    <template #prepend>
                                        <v-icon size="18" color="#a5b4fc" class="mr-2">mdi-package-variant-closed</v-icon>
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ card.title }}</v-list-item-title>
                                    <v-list-item-subtitle v-if="card.subject" class="text-caption">{{ card.subject }}</v-list-item-subtitle>
                                </v-list-item>
                            </v-list>
                            <div v-else class="text-center py-4 text-caption" style="color: #64748b">
                                Keine Materialien gefunden.
                            </div>
                        </v-card-text>
                        <v-card-actions class="px-4 pb-4">
                            <v-btn variant="tonal" :disabled="isPageActionLocked" @click="materialDialogOpen = false">Schließen</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

                <v-dialog v-model="materialAttachmentDialogOpen" max-width="640" persistent>
                    <v-card rounded="xl">
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                            <v-icon color="primary" size="22">mdi-paperclip</v-icon>
                            Anhang auswählen
                        </v-card-title>
                        <v-card-text class="px-4 pb-2">
                            <div class="text-body-2 mb-3" style="color: #475569">
                                {{ materialAttachmentDocument?.name || 'Material' }}
                            </div>
                            <div v-if="materialAttachmentLoading" class="text-center py-6">
                                <v-progress-circular indeterminate color="primary" size="24" />
                            </div>
                            <div v-else-if="materialAttachmentError" class="text-caption py-4" style="color: #b91c1c">
                                {{ materialAttachmentError }}
                            </div>
                            <v-list
                                v-else-if="materialAttachmentOptions.length"
                                bg-color="transparent"
                                density="compact"
                                class="py-0"
                                style="max-height: 360px; overflow-y: auto">
                                <v-list-item
                                    v-for="attachment in materialAttachmentOptions"
                                    :key="attachment.id"
                                    class="lehrplaene__material-item lehrplaene__attachment-item mb-1 px-3"
                                    rounded="lg"
                                    :active="selectedMaterialAttachmentId === attachment.id"
                                    :disabled="savingMaterialAttachment"
                                    @click="selectMaterialAttachment(attachment)">
                                    <template #prepend>
                                        <v-icon size="18" color="#a5b4fc" class="mr-2">
                                            {{ attachment.mime_type?.startsWith('image/') ? 'mdi-file-image-outline' : 'mdi-file-document-outline' }}
                                        </v-icon>
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ attachment.name }}</v-list-item-title>
                                    <v-list-item-subtitle class="text-caption">
                                        {{ attachment.mime_type || 'Datei' }}<span v-if="attachment.size_bytes"> · {{ formatBytes(attachment.size_bytes) }}</span>
                                    </v-list-item-subtitle>
                                    <template #append>
                                        <v-icon v-if="selectedMaterialAttachmentId === attachment.id" size="18" color="primary">
                                            mdi-check-circle
                                        </v-icon>
                                    </template>
                                </v-list-item>
                            </v-list>
                            <div v-else class="text-center py-6 text-caption" style="color: #64748b">
                                Dieses Material hat keine auswählbaren Anhänge.
                            </div>
                        </v-card-text>
                        <v-card-actions class="px-4 pb-4">
                            <v-btn variant="tonal" :disabled="savingMaterialAttachment" @click="closeMaterialAttachmentDialog">Schließen</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>
            </div>
        </div>
    </div>
</template>

<script>
import { mapState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import FileUpload from '@/pages/components/FileUpload.vue'

const DAY_NAMES_SHORT = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']
const MONTH_NAMES = [
    'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni',
    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
]
export default {
    name: 'CurriculumDetail',
    components: { FileUpload },
    props: {
        curriculum: { type: Object, required: true },
    },
    emits: ['back', 'updated'],

    mounted() {
        this.loadDocuments()
    },

    data() {
        const adminStore = useAdminStore()
        const sy = adminStore.config?.selected_schoolyear
        let initYear
        if (sy?.from) {
            const y = parseInt(sy.from.substring(0, 4), 10)
            initYear = Number.isFinite(y) ? y : null
        }
        if (!initYear) {
            const now = new Date()
            initYear = now.getMonth() >= 8 ? now.getFullYear() : now.getFullYear() - 1
        }
        return {
            selectedHalf: 'first',
            selectedYear: initYear,
            documents: [],
            docsLoading: false,
            materialDialogOpen: false,
            materialAttachmentDialogOpen: false,
            materialAttachmentDocument: null,
            materialAttachmentOptions: [],
            materialAttachmentLoading: false,
            materialAttachmentError: null,
            savingMaterialAttachment: false,
            selectedMaterialAttachmentId: null,
            materialSearch: '',
            materialResults: [],
            materialsLoading: false,
            _materialSearchTimer: null,
            showUploadOptions: false,
            previewDoc: null,
            savingWeekKeys: [],
            topicSaving: false,
            topicFormError: null,
            showTopicForm: false,
            unitFormError: null,
            showUnitFormForTopicId: null,
            activeTopicAssignmentId: null,
            activeTopicAssignmentUnitId: null,
            activeTopicAssignmentType: null,
            contentDeleteDialogOpen: false,
            contentToDelete: null,
            topicForm: {
                id: null,
                title: '',
            },
            unitForm: {
                topicId: null,
                id: null,
                title: '',
            },
        }
    },

    computed: {
        ...mapState(useAdminStore, ['config']),

        hasMaterialsAccess() {
            const roles = Array.isArray(this.config?.roles) ? this.config.roles : []
            return ['super_admin', 'admin', 'materials_admin', 'materials_moderator'].some((r) => roles.includes(r))
        },

        previewUrl() {
            return this.previewDoc?.preview_url || null
        },

        previewDownloadUrl() {
            return this.previewDoc?.download_url || null
        },

        previewIsPdf() {
            if (!this.previewDoc) return false
            const mime = (this.previewDoc.preview_mime_type || this.previewDoc.mime_type || '').toLowerCase()
            return mime === 'application/pdf'
        },

        previewIsImage() {
            if (!this.previewDoc) return false
            const mime = (this.previewDoc.preview_mime_type || this.previewDoc.mime_type || '').toLowerCase()
            return mime.startsWith('image/')
        },

        freeWeekKeys() {
            return Array.isArray(this.curriculum.free_weeks) ? this.curriculum.free_weeks : []
        },

        freeWeeksCount() {
            return this.freeWeekKeys.length
        },

        curriculumTopics() {
            return (Array.isArray(this.curriculum.topics) ? this.curriculum.topics : [])
                .map((topic, index) => this.normalizeTopic(topic, index))
        },

        curriculumScopeLabel() {
            return (this.curriculum.semester_count ?? 2) === 1 ? 'Ganzes Semester' : 'Ganzes Jahr'
        },

        assignableMonths() {
            return this.visibleMonths.map((month) => ({
                assignmentKey: month.assignmentKey,
                name: `${month.name} ${month.year}`,
            }))
        },

        isEditingTopic() {
            return this.showTopicForm && this.topicForm.id !== null
        },

        isEditingTopicDates() {
            return this.activeTopicAssignmentId !== null && this.activeTopicAssignmentUnitId === null
        },

        isEditingUnit() {
            return this.showUnitFormForTopicId !== null && this.unitForm.id !== null
        },

        isEditingUnitDates() {
            return this.activeTopicAssignmentUnitId !== null
        },

        showUnitForm: {
            get() {
                return this.showUnitFormForTopicId !== null
            },
            set(value) {
                if (!value && this.showUnitFormForTopicId !== null) {
                    this.cancelUnitForm()
                }
            },
        },

        isPageActionLocked() {
            return this.isEditingTopic || this.isEditingUnit || this.activeTopicAssignmentId !== null
        },

        activeTopicAssignmentTopic() {
            if (!this.activeTopicAssignmentId) return null

            return this.curriculumTopics.find((topic) => topic.id === this.activeTopicAssignmentId) ?? null
        },

        activeTopicAssignmentUnit() {
            if (!this.activeTopicAssignmentTopic || !this.activeTopicAssignmentUnitId) return null

            return this.activeTopicAssignmentTopic.units.find((unit) => unit.id === this.activeTopicAssignmentUnitId) ?? null
        },

        activeAssignmentItem() {
            return this.activeTopicAssignmentUnit ?? this.activeTopicAssignmentTopic
        },

        activeAssignmentItemLabel() {
            return this.activeTopicAssignmentUnit ? 'Einheit' : 'Thema'
        },

        isWeekSelectionActive() {
            return this.activeTopicAssignmentType === 'weeks' && this.activeAssignmentItem !== null
        },

        allMonths() {
            const months = []
            const startYear = this.selectedYear
            // September (8) to July (6) next year
            const monthSequence = [
                { m: 8, y: startYear },     // Sep
                { m: 9, y: startYear },      // Okt
                { m: 10, y: startYear },     // Nov
                { m: 11, y: startYear },     // Dez
                { m: 0, y: startYear + 1 },  // Jan
                { m: 1, y: startYear + 1 },  // Feb
                { m: 2, y: startYear + 1 },  // Mar
                { m: 3, y: startYear + 1 },  // Apr
                { m: 4, y: startYear + 1 },  // Mai
                { m: 5, y: startYear + 1 },  // Jun
                { m: 6, y: startYear + 1 },  // Jul
            ]

            const today = new Date()
            today.setHours(0, 0, 0, 0)

            for (const entry of monthSequence) {
                const weeks = this.buildWeeks(entry.y, entry.m, today)
                months.push({
                    key: `${entry.y}-${entry.m}`,
                    assignmentKey: this.formatMonthKey(entry.y, entry.m),
                    name: MONTH_NAMES[entry.m],
                    year: entry.y,
                    month: entry.m,
                    weeks,
                })
            }
            return months
        },

        visibleMonths() {
            const semCount = this.curriculum.semester_count ?? 2
            if (semCount === 2) return this.allMonths

            // 1 semester: first half = Sep-Feb (indices 0-5), second half = Feb-Jul (indices 5-10)
            if (this.selectedHalf === 'first') {
                return this.allMonths.slice(0, 6)
            }
            return this.allMonths.slice(5, 11)
        },

    },

    methods: {
        newTopicForm(topic = null) {
            return {
                id: topic?.id || null,
                title: topic?.title || '',
            }
        },

        newUnitForm(topicId = null, unit = null) {
            return {
                topicId,
                id: unit?.id || null,
                title: unit?.title || '',
                is_exam: Boolean(unit?.is_exam),
            }
        },

        normalizeAssignmentEntry(entry = null, index = 0, prefix = 'entry') {
            const normalizedEntry = entry && typeof entry === 'object' ? entry : {}
            const assignmentType = ['none', 'all_weeks', 'month', 'weeks'].includes(normalizedEntry.assignment_type)
                ? normalizedEntry.assignment_type
                : 'none'
            const monthKeys = [...new Set(
                (
                    Array.isArray(normalizedEntry.month_keys)
                        ? normalizedEntry.month_keys
                        : [normalizedEntry.month_key]
                )
                    .filter(Boolean)
                    .map((monthKey) => String(monthKey).trim())
            )].sort()
            const weekKeys = [...new Set(
                (Array.isArray(normalizedEntry.week_keys) ? normalizedEntry.week_keys : [])
                    .filter(Boolean)
                    .map((weekKey) => String(weekKey).trim())
            )].sort()

            return {
                id: normalizedEntry.id || `${prefix}-${index}`,
                title: typeof normalizedEntry.title === 'string' ? normalizedEntry.title.trim() : '',
                assignment_type: assignmentType,
                month_key: assignmentType === 'month' ? (monthKeys[0] ?? null) : null,
                month_keys: assignmentType === 'month' ? monthKeys : [],
                week_keys: assignmentType === 'weeks' ? weekKeys : [],
            }
        },

        normalizeTopic(topic = null, index = 0) {
            const normalizedTopic = topic && typeof topic === 'object' ? topic : {}
            return {
                ...this.normalizeAssignmentEntry(normalizedTopic, index, 'topic'),
                units: (Array.isArray(normalizedTopic.units) ? normalizedTopic.units : [])
                    .map((unit, unitIndex) => this.normalizeUnit(unit, unitIndex)),
            }
        },

        buildTopicPayload(topic = null, overrides = {}) {
            return this.normalizeTopic({
                ...(topic && typeof topic === 'object' ? topic : {}),
                ...overrides,
            })
        },

        normalizeUnit(unit = null, index = 0) {
            const normalizedUnit = unit && typeof unit === 'object' ? unit : {}

            return {
                ...this.normalizeAssignmentEntry(normalizedUnit, index, 'unit'),
                is_exam: Boolean(normalizedUnit.is_exam),
            }
        },

        buildUnitPayload(unit = null, overrides = {}) {
            return this.normalizeUnit({
                ...(unit && typeof unit === 'object' ? unit : {}),
                ...overrides,
            })
        },

        assignmentState(item = null) {
            const normalizedItem = item && typeof item === 'object' ? item : {}
            const assignmentType = ['none', 'all_weeks', 'month', 'weeks'].includes(normalizedItem.assignment_type)
                ? normalizedItem.assignment_type
                : 'none'

            return {
                assignment_type: assignmentType,
                month_keys: assignmentType === 'month' ? [...normalizedItem.month_keys] : [],
                week_keys: assignmentType === 'weeks' ? [...normalizedItem.week_keys] : [],
            }
        },

        clearAssignment(item = null) {
            return {
                ...(item && typeof item === 'object' ? item : {}),
                assignment_type: 'none',
                month_key: null,
                month_keys: [],
                week_keys: [],
            }
        },

        applyMonthAssignment(item = null, monthKeys = []) {
            const nextMonthKeys = [...new Set((Array.isArray(monthKeys) ? monthKeys : []).filter(Boolean).map((monthKey) => String(monthKey).trim()))].sort()

            return {
                ...(item && typeof item === 'object' ? item : {}),
                assignment_type: 'month',
                month_key: nextMonthKeys[0] ?? null,
                month_keys: nextMonthKeys,
                week_keys: [],
            }
        },

        applyWeekAssignment(item = null, weekKeys = []) {
            const nextWeekKeys = [...new Set((Array.isArray(weekKeys) ? weekKeys : []).filter(Boolean).map((weekKey) => String(weekKey).trim()))].sort()

            return {
                ...(item && typeof item === 'object' ? item : {}),
                assignment_type: 'weeks',
                month_key: null,
                month_keys: [],
                week_keys: nextWeekKeys,
            }
        },

        monthKeyFromWeekKey(weekKey) {
            return String(weekKey).slice(0, 7)
        },

        visibleWeekKeys() {
            return this.visibleMonths.flatMap((month) => (
                Array.isArray(month.weeks)
                    ? month.weeks.map((week) => week.weekKey).filter(Boolean)
                    : []
            ))
        },

        shiftWeekKeyByWeeks(weekKey, delta) {
            const baseDate = new Date(`${weekKey}T00:00:00`)
            if (Number.isNaN(baseDate.getTime())) {
                return null
            }

            const shiftedDate = new Date(baseDate)
            shiftedDate.setDate(shiftedDate.getDate() + (delta * 7))

            return this.formatDateKey(shiftedDate)
        },

        resolveShiftedWeekKey(weekKey, delta, validWeekKeys, freeWeekKeys) {
            if (!weekKey || !Number.isInteger(delta) || delta === 0) {
                return {
                    weekKey,
                    errorMessage: null,
                }
            }

            const stepDirection = delta < 0 ? -1 : 1
            let shiftedWeekKey = this.shiftWeekKeyByWeeks(weekKey, delta)

            if (!shiftedWeekKey || !validWeekKeys.has(shiftedWeekKey)) {
                return {
                    weekKey: null,
                    errorMessage: 'Die Wochensequenz kann nicht außerhalb des sichtbaren Zeitraums verschoben werden.',
                }
            }

            while (freeWeekKeys.has(shiftedWeekKey)) {
                shiftedWeekKey = this.shiftWeekKeyByWeeks(shiftedWeekKey, stepDirection)

                if (!shiftedWeekKey || !validWeekKeys.has(shiftedWeekKey)) {
                    return {
                        weekKey: null,
                        errorMessage: 'Die Wochensequenz kann nicht außerhalb des sichtbaren Zeitraums verschoben werden.',
                    }
                }
            }

            return {
                weekKey: shiftedWeekKey,
                errorMessage: null,
            }
        },

        resolveWeekSequenceDelta(sourceWeekKey, delta, validWeekKeys, freeWeekKeys) {
            const { weekKey, errorMessage } = this.resolveShiftedWeekKey(
                sourceWeekKey,
                delta,
                validWeekKeys,
                freeWeekKeys,
            )

            if (errorMessage || !weekKey) {
                return {
                    effectiveDelta: null,
                    errorMessage,
                }
            }

            const sourceDate = new Date(`${sourceWeekKey}T00:00:00`)
            const targetDate = new Date(`${weekKey}T00:00:00`)

            return {
                effectiveDelta: Math.round((targetDate.getTime() - sourceDate.getTime()) / (7 * 24 * 60 * 60 * 1000)),
                errorMessage: null,
            }
        },

        assignmentStatesOverlap(first, second) {
            if (!first || !second || first.assignment_type === 'none' || second.assignment_type === 'none') {
                return false
            }

            if (first.assignment_type === 'all_weeks' || second.assignment_type === 'all_weeks') {
                return true
            }

            if (first.assignment_type === 'month' && second.assignment_type === 'month') {
                return first.month_keys.some((monthKey) => second.month_keys.includes(monthKey))
            }

            if (first.assignment_type === 'month' && second.assignment_type === 'weeks') {
                return second.week_keys.some((weekKey) => first.month_keys.includes(this.monthKeyFromWeekKey(weekKey)))
            }

            if (first.assignment_type === 'weeks' && second.assignment_type === 'month') {
                return first.week_keys.some((weekKey) => second.month_keys.includes(this.monthKeyFromWeekKey(weekKey)))
            }

            return first.week_keys.some((weekKey) => second.week_keys.includes(weekKey))
        },

        assignmentSequenceEntries() {
            return this.curriculumTopics.flatMap((topic) => {
                const entries = [{
                    topicId: topic.id,
                    unitId: null,
                }]

                topic.units.forEach((unit) => {
                    entries.push({
                        topicId: topic.id,
                        unitId: unit.id,
                    })
                })

                return entries
            })
        },

        buildShiftedTopicsForWeekSequence(sourceWeekKey, delta) {
            const validWeekKeys = new Set(this.visibleWeekKeys())
            const freeWeekKeys = new Set(this.freeWeekKeys)
            const sequenceEntries = this.assignmentSequenceEntries()
            const sourceTopicId = this.activeTopicAssignmentId
            const sourceUnitId = this.activeTopicAssignmentUnitId ?? null
            const sourceSequenceIndex = sequenceEntries.findIndex((entry) => (
                entry.topicId === sourceTopicId && entry.unitId === sourceUnitId
            ))
            const { effectiveDelta, errorMessage: deltaErrorMessage } = this.resolveWeekSequenceDelta(
                sourceWeekKey,
                delta,
                validWeekKeys,
                freeWeekKeys,
            )
            let hasShiftedWeeks = false
            let errorMessage = deltaErrorMessage

            if (sourceSequenceIndex === -1) {
                return {
                    topics: null,
                    errorMessage: 'Die aktive Datumszuordnung konnte nicht gefunden werden.',
                }
            }

            const shiftWeekKeys = (weekKeys, mode = 'all') => {
                const nextWeekKeys = [...new Set((Array.isArray(weekKeys) ? weekKeys : []).filter(Boolean))]
                    .map((weekKey) => {
                        if (
                            errorMessage
                            || mode === 'none'
                            || (mode === 'tail' && weekKey < sourceWeekKey)
                        ) {
                            return weekKey
                        }

                        hasShiftedWeeks = true

                        const shiftedWeek = this.resolveShiftedWeekKey(
                            weekKey,
                            effectiveDelta,
                            validWeekKeys,
                            freeWeekKeys,
                        )

                        if (shiftedWeek.errorMessage || !shiftedWeek.weekKey) {
                            errorMessage = shiftedWeek.errorMessage
                            return weekKey
                        }

                        return shiftedWeek.weekKey
                    })
                    .sort()

                if (!errorMessage && new Set(nextWeekKeys).size !== nextWeekKeys.length) {
                    errorMessage = 'Durch das Verschieben würden Wochen doppelt belegt.'
                }

                return nextWeekKeys
            }

            const nextTopics = this.curriculumTopics.map((topic) => {
                const topicSequenceIndex = sequenceEntries.findIndex((entry) => (
                    entry.topicId === topic.id && entry.unitId === null
                ))
                let nextTopic = this.buildTopicPayload(topic)

                if (nextTopic.assignment_type === 'weeks') {
                    const topicShiftMode = topicSequenceIndex < sourceSequenceIndex
                        ? 'none'
                        : topicSequenceIndex === sourceSequenceIndex
                            ? 'tail'
                            : 'all'

                    nextTopic = this.buildTopicPayload(nextTopic, {
                        week_keys: shiftWeekKeys(nextTopic.week_keys, topicShiftMode),
                    })
                }

                const nextUnits = nextTopic.units.map((unit) => {
                    if (unit.assignment_type !== 'weeks') {
                        return this.buildUnitPayload(unit)
                    }

                    const unitSequenceIndex = sequenceEntries.findIndex((entry) => (
                        entry.topicId === topic.id && entry.unitId === unit.id
                    ))
                    const unitShiftMode = unitSequenceIndex < sourceSequenceIndex
                        ? 'none'
                        : unitSequenceIndex === sourceSequenceIndex
                            ? 'tail'
                            : 'all'

                    return this.buildUnitPayload(unit, {
                        week_keys: shiftWeekKeys(unit.week_keys, unitShiftMode),
                    })
                })

                nextTopic = this.buildTopicPayload(nextTopic, { units: nextUnits })

                if (!errorMessage) {
                    const topicState = this.assignmentState(nextTopic)

                    nextUnits.forEach((unit) => {
                        if (errorMessage) {
                            return
                        }

                        if (this.assignmentStatesOverlap(topicState, this.assignmentState(unit))) {
                            errorMessage = 'Durch das Verschieben würden sich Datumszuordnungen innerhalb eines Themas überschneiden.'
                        }
                    })
                }

                return nextTopic
            })

            if (!errorMessage && !hasShiftedWeeks) {
                errorMessage = 'Für diese Woche gibt es keine nachfolgenden Zuordnungen zum Verschieben.'
            }

            return {
                topics: errorMessage ? null : nextTopics,
                errorMessage,
            }
        },

        canShiftWeekSequence(weekKey, delta) {
            if (
                this.topicSaving
                || !this.isWeekSelectionActive
                || !weekKey
                || !Number.isInteger(delta)
            ) {
                return false
            }

            return !this.buildShiftedTopicsForWeekSequence(weekKey, delta).errorMessage
        },

        async shiftWeekSequence(weekKey, delta, fallbackMessage) {
            if (this.topicSaving || !this.isWeekSelectionActive) {
                return
            }

            const { topics, errorMessage } = this.buildShiftedTopicsForWeekSequence(weekKey, delta)

            if (errorMessage || !topics) {
                useNotificationStore().notify({
                    message: errorMessage || fallbackMessage,
                    type: 'error',
                    timeout: 3000,
                })
                return
            }

            this.topicSaving = true

            try {
                await this.persistCurriculum({
                    topics,
                }, fallbackMessage)
            } finally {
                this.topicSaving = false
            }
        },

        removeAssignmentOverlap(target, blocking) {
            const targetState = this.assignmentState(target)
            const blockingState = this.assignmentState(blocking)

            if (targetState.assignment_type === 'none' || blockingState.assignment_type === 'none') {
                return target
            }

            if (targetState.assignment_type === 'all_weeks' || blockingState.assignment_type === 'all_weeks') {
                return this.clearAssignment(target)
            }

            if (targetState.assignment_type === 'month') {
                const blockingMonths = blockingState.assignment_type === 'month'
                    ? blockingState.month_keys
                    : [...new Set(blockingState.week_keys.map((weekKey) => this.monthKeyFromWeekKey(weekKey)))]
                const remainingMonthKeys = targetState.month_keys.filter((monthKey) => !blockingMonths.includes(monthKey))

                return remainingMonthKeys.length
                    ? this.applyMonthAssignment(target, remainingMonthKeys)
                    : this.clearAssignment(target)
            }

            const remainingWeekKeys = blockingState.assignment_type === 'month'
                ? targetState.week_keys.filter((weekKey) => !blockingState.month_keys.includes(this.monthKeyFromWeekKey(weekKey)))
                : targetState.week_keys.filter((weekKey) => !blockingState.week_keys.includes(weekKey))

            return remainingWeekKeys.length
                ? this.applyWeekAssignment(target, remainingWeekKeys)
                : this.clearAssignment(target)
        },

        reconcileTopicUnitAssignments(topic, winner = null) {
            const normalizedTopic = this.buildTopicPayload(topic)
            const units = normalizedTopic.units.map((unit) => this.buildUnitPayload(unit))

            if (winner === 'topic') {
                return {
                    ...normalizedTopic,
                    units: units.map((unit) => this.removeAssignmentOverlap(unit, normalizedTopic)),
                }
            }

            if (winner === 'units') {
                let nextTopic = normalizedTopic

                units.forEach((unit) => {
                    nextTopic = this.removeAssignmentOverlap(nextTopic, unit)
                })

                return {
                    ...nextTopic,
                    units,
                }
            }

            return {
                ...normalizedTopic,
                units,
            }
        },

        findTopic(topicId) {
            return this.curriculumTopics.find((topic) => topic.id === topicId) ?? null
        },

        findUnit(topicId, unitId) {
            const topic = this.findTopic(topicId)
            if (!topic) return null

            return topic.units.find((unit) => unit.id === unitId) ?? null
        },

        isTopicAssignmentEditorOpen(topicId) {
            return this.activeTopicAssignmentId === topicId && this.activeTopicAssignmentUnitId === null
        },

        isUnitAssignmentEditorOpen(topicId, unitId) {
            return this.activeTopicAssignmentId === topicId && this.activeTopicAssignmentUnitId === unitId
        },

        toggleTopicAssignmentEditor(topic) {
            if (this.isTopicAssignmentEditorOpen(topic.id)) {
                this.closeTopicAssignmentEditor()
                return
            }

            this.openTopicAssignmentEditor(topic)
        },

        toggleUnitAssignmentEditor(topic, unit) {
            if (this.isUnitAssignmentEditorOpen(topic.id, unit.id)) {
                this.closeTopicAssignmentEditor()
                return
            }

            this.openUnitAssignmentEditor(topic, unit)
        },

        openTopicAssignmentEditor(topic, assignmentType = null) {
            if (
                !topic
                || this.topicSaving
                || this.isEditingTopic
                || this.isEditingUnit
                || (this.activeTopicAssignmentId !== null && !this.isTopicAssignmentEditorOpen(topic.id))
            ) {
                return
            }

            this.activeTopicAssignmentId = topic.id
            this.activeTopicAssignmentUnitId = null
            this.activeTopicAssignmentType = assignmentType ?? topic.assignment_type
        },

        openUnitAssignmentEditor(topic, unit, assignmentType = null) {
            if (
                !topic
                || !unit
                || this.topicSaving
                || this.isEditingTopic
                || this.isEditingUnit
                || (this.activeTopicAssignmentId !== null && !this.isUnitAssignmentEditorOpen(topic.id, unit.id))
            ) {
                return
            }

            this.activeTopicAssignmentId = topic.id
            this.activeTopicAssignmentUnitId = unit.id
            this.activeTopicAssignmentType = assignmentType ?? unit.assignment_type
        },

        promptDeleteTopic(topic) {
            if (this.isPageActionLocked || this.topicSaving) return

            this.contentToDelete = {
                type: 'topic',
                title: topic.title,
                topicId: topic.id,
                unitId: null,
            }
            this.contentDeleteDialogOpen = true
        },

        promptDeleteUnit(topic, unit) {
            if (this.isPageActionLocked || this.topicSaving) return

            this.contentToDelete = {
                type: 'unit',
                title: unit.title,
                topicId: topic.id,
                unitId: unit.id,
            }
            this.contentDeleteDialogOpen = true
        },

        closeTopicDeleteDialog() {
            if (this.topicSaving) return

            this.contentDeleteDialogOpen = false
            this.contentToDelete = null
        },

        async confirmTopicDelete() {
            if (!this.contentToDelete) return

            const deleted = this.contentToDelete.type === 'topic'
                ? await this.deleteTopic(this.contentToDelete.topicId)
                : await this.deleteUnit(this.contentToDelete.topicId, this.contentToDelete.unitId)

            if (deleted) {
                this.closeTopicDeleteDialog()
            }
        },

        closeTopicAssignmentEditor() {
            if (this.topicSaving) return

            this.activeTopicAssignmentId = null
            this.activeTopicAssignmentUnitId = null
            this.activeTopicAssignmentType = null
        },

        monthChipLabel(monthKey) {
            const [year, month] = String(monthKey).split('-')
            const monthIndex = Number.parseInt(month, 10) - 1
            const monthName = MONTH_NAMES[monthIndex] ?? monthKey

            return `${monthName} ${year}`
        },

        weekChipLabel(weekKey) {
            const weekStart = new Date(`${weekKey}T00:00:00`)
            if (Number.isNaN(weekStart.getTime())) return weekKey

            const friday = new Date(weekStart)
            friday.setDate(friday.getDate() + 4)

            return `KW ${this.getISOWeek(weekStart)} · ${weekStart.getDate()}.${weekStart.getMonth() + 1}.–${friday.getDate()}.${friday.getMonth() + 1}.`
        },

        topicAssignmentSummary(topic) {
            if (topic.assignment_type === 'none') {
                return 'Keine Zuordnung'
            }

            if (topic.assignment_type === 'month') {
                if (topic.month_keys.length === 1) {
                    return this.monthChipLabel(topic.month_keys[0])
                }

                return `${topic.month_keys.length} Monate`
            }

            if (topic.assignment_type === 'weeks') {
                if (topic.week_keys.length === 1) {
                    return this.weekChipLabel(topic.week_keys[0])
                }

                return `${topic.week_keys.length} Wochen`
            }

            return this.curriculumScopeLabel
        },

        assignmentSummaryVariant(item) {
            return item?.assignment_type === 'all_weeks' ? 'flat' : 'tonal'
        },

        overlappingWeekAssignmentKeys() {
            const counts = new Map()

            this.curriculumTopics.forEach((topic) => {
                if (topic.assignment_type === 'weeks') {
                    topic.week_keys.forEach((weekKey) => {
                        counts.set(weekKey, (counts.get(weekKey) || 0) + 1)
                    })
                }

                topic.units.forEach((unit) => {
                    if (unit.assignment_type !== 'weeks') {
                        return
                    }

                    unit.week_keys.forEach((weekKey) => {
                        counts.set(weekKey, (counts.get(weekKey) || 0) + 1)
                    })
                })
            })

            return new Set(
                [...counts.entries()]
                    .filter(([, count]) => count > 1)
                    .map(([weekKey]) => weekKey),
            )
        },

        weekAssignmentChipColor(weekKey) {
            return this.overlappingWeekAssignmentKeys().has(weekKey) ? 'warning' : 'primary'
        },

        topicInheritedAssignmentChips(topic) {
            if (!topic || topic.assignment_type !== 'none' || !Array.isArray(topic.units)) {
                return []
            }

            const monthKeys = new Set()
            const weekKeys = new Set()
            let hasAllWeeksAssignment = false

            topic.units.forEach((unit) => {
                const assignment = this.assignmentState(unit)

                if (assignment.assignment_type === 'all_weeks') {
                    hasAllWeeksAssignment = true
                    return
                }

                if (assignment.assignment_type === 'month') {
                    assignment.month_keys.forEach((monthKey) => monthKeys.add(monthKey))
                    return
                }

                if (assignment.assignment_type === 'weeks') {
                    assignment.week_keys.forEach((weekKey) => weekKeys.add(weekKey))
                }
            })

            const chips = []

            if (hasAllWeeksAssignment) {
                chips.push({
                    key: 'all-weeks',
                    label: this.curriculumScopeLabel,
                })
            }

            ;[...monthKeys].sort().forEach((monthKey) => {
                chips.push({
                    key: `month-${monthKey}`,
                    label: this.monthChipLabel(monthKey),
                })
            })

            ;[...weekKeys].sort().forEach((weekKey) => {
                chips.push({
                    key: `week-${weekKey}`,
                    label: this.weekChipLabel(weekKey),
                    color: this.weekAssignmentChipColor(weekKey),
                })
            })

            return chips
        },

        shouldShowAssignmentSummaryChip(item) {
            if (!item) {
                return false
            }

            if (item.assignment_type === 'month' || item.assignment_type === 'weeks') {
                return false
            }

            if (item.assignment_type === 'none' && this.topicInheritedAssignmentChips(item).length) {
                return false
            }

            return true
        },

        topicsForMonth(month) {
            const monthKey = month?.assignmentKey
            if (!monthKey) {
                return []
            }

            return this.curriculumTopics.flatMap((topic) => {
                const entries = []

                if (!topic.title) {
                    return entries
                }

                if (topic.assignment_type === 'all_weeks') {
                    entries.push({ id: topic.id, title: topic.title, isExam: false })
                } else if (topic.assignment_type === 'month' && topic.month_keys.includes(monthKey)) {
                    entries.push({ id: topic.id, title: topic.title, isExam: false })
                }

                topic.units.forEach((unit) => {
                    if (!unit.title) {
                        return
                    }

                    if (unit.assignment_type === 'all_weeks') {
                        entries.push({
                            id: `${topic.id}-${unit.id}`,
                            title: this.overviewUnitTitle(topic, unit),
                            isExam: Boolean(unit.is_exam),
                        })
                        return
                    }

                    if (unit.assignment_type === 'month' && unit.month_keys.includes(monthKey)) {
                        entries.push({
                            id: `${topic.id}-${unit.id}`,
                            title: this.overviewUnitTitle(topic, unit),
                            isExam: Boolean(unit.is_exam),
                        })
                    }
                })

                return entries
            })
        },

        monthHasExamEntries(month) {
            return this.topicsForMonth(month).some((entry) => entry.isExam)
        },

        topicsForWeek(weekKey) {
            if (!weekKey) {
                return []
            }

            return this.curriculumTopics.flatMap((topic) => {
                const entries = []

                if (Boolean(topic.title) && topic.assignment_type === 'weeks' && topic.week_keys.includes(weekKey)) {
                    entries.push({ id: topic.id, title: topic.title, isExam: false })
                }

                topic.units.forEach((unit) => {
                    if (Boolean(unit.title) && unit.assignment_type === 'weeks' && unit.week_keys.includes(weekKey)) {
                        entries.push({
                            id: `${topic.id}-${unit.id}`,
                            title: this.overviewUnitTitle(topic, unit),
                            isExam: Boolean(unit.is_exam),
                        })
                    }
                })

                return entries
            })
        },

        weekHasExamEntries(weekKey) {
            return this.topicsForWeek(weekKey).some((entry) => entry.isExam)
        },

        overviewUnitTitle(topic, unit) {
            const topicTitle = topic?.title?.trim()
            const unitTitle = unit?.title?.trim()

            if (!unitTitle) {
                return ''
            }

            if (!topicTitle) {
                return unitTitle
            }

            return `${topicTitle}: ${unitTitle}`
        },

        isWeekAssignedToActiveTopic(weekKey) {
            return this.activeAssignmentItem?.week_keys?.includes(weekKey) ?? false
        },

        isWeekSelectableForTopic(weekKey) {
            return this.isWeekSelectionActive && !this.isFreeWeek(weekKey)
        },

        buildWeeks(year, month, today) {
            const weeks = []
            const firstDay = new Date(year, month, 1)
            const lastDay = new Date(year, month + 1, 0)

            // Find Monday of the week containing the 1st
            let cursor = new Date(firstDay)
            const dow = cursor.getDay()
            const mondayOffset = dow === 0 ? -6 : 1 - dow
            cursor.setDate(cursor.getDate() + mondayOffset)

            while (cursor <= lastDay || cursor.getDay() !== 1) {
                const days = []
                let weekHasMonthDay = false
                const weekStart = new Date(cursor)

                for (let d = 0; d < 7; d++) {
                    const date = new Date(cursor)
                    const inMonth = date.getMonth() === month && date.getFullYear() === year
                    if (inMonth) weekHasMonthDay = true

                    if (d < 5) {
                        days.push({
                            date: date.toISOString().slice(0, 10),
                            dayNum: date.getDate(),
                            dayName: DAY_NAMES_SHORT[d],
                            isToday: date.getTime() === today.getTime(),
                            outsideMonth: !inMonth,
                        })
                    }
                    cursor.setDate(cursor.getDate() + 1)
                }

                if (!weekHasMonthDay) break

                const friday = new Date(weekStart)
                friday.setDate(friday.getDate() + 4)

                const kw = this.getISOWeek(weekStart)
                const isCurrent = today >= weekStart && today <= friday
                const weekKey = this.formatDateKey(weekStart)

                weeks.push({
                    kw,
                    days,
                    isCurrent,
                    weekKey,
                    rangeLabel: `${weekStart.getDate()}.${weekStart.getMonth() + 1}. – ${friday.getDate()}.${friday.getMonth() + 1}.`,
                })
            }

            return weeks
        },

        formatDateKey(date) {
            const year = date.getFullYear()
            const month = String(date.getMonth() + 1).padStart(2, '0')
            const day = String(date.getDate()).padStart(2, '0')

            return `${year}-${month}-${day}`
        },

        formatMonthKey(year, monthIndex) {
            return `${year}-${String(monthIndex + 1).padStart(2, '0')}`
        },

        getISOWeek(date) {
            const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
            d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7))
            const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1))
            return Math.ceil((((d - yearStart) / 86400000) + 1) / 7)
        },

        isFreeWeek(weekKey) {
            return this.freeWeekKeys.includes(weekKey)
        },

        isWeekSaving(weekKey) {
            return this.savingWeekKeys.includes(weekKey)
        },

        monthHue(idx) {
            const base = 220
            return (base + idx * 28) % 360
        },

        buildCurriculumPayload(overrides = {}) {
            return {
                title: this.curriculum.title,
                description: this.curriculum.description,
                semester_count: this.curriculum.semester_count ?? 2,
                free_weeks: this.freeWeekKeys,
                topics: this.curriculumTopics,
                ...overrides,
            }
        },

        async persistCurriculum(overrides, fallbackMessage) {
            try {
                const response = await axios.put(
                    `/api/admin/teaching/curricula/${this.curriculum.id}`,
                    this.buildCurriculumPayload(overrides),
                )

                const updatedCurriculum = response.data?.data || { ...this.curriculum, ...overrides }
                this.$emit('updated', updatedCurriculum)

                return updatedCurriculum
            } catch (error) {
                useNotificationStore().notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || fallbackMessage,
                    type: 'error',
                    timeout: 3000,
                })

                return null
            }
        },

        async persistTopicAssignment(topicId, overrides, fallbackMessage) {
            const nextTopics = this.curriculumTopics.map((topic) => (
                topic.id === topicId
                    ? this.reconcileTopicUnitAssignments(this.buildTopicPayload(topic, overrides), 'topic')
                    : this.buildTopicPayload(topic)
            ))

            this.topicSaving = true

            try {
                return await this.persistCurriculum({
                    topics: nextTopics,
                }, fallbackMessage)
            } finally {
                this.topicSaving = false
            }
        },

        async persistUnitAssignment(topicId, unitId, overrides, fallbackMessage) {
            const nextTopics = this.curriculumTopics.map((topic) => {
                if (topic.id !== topicId) {
                    return this.buildTopicPayload(topic)
                }

                return this.reconcileTopicUnitAssignments(this.buildTopicPayload(topic, {
                    units: topic.units.map((unit) => (
                        unit.id === unitId
                            ? this.buildUnitPayload(unit, overrides)
                            : this.buildUnitPayload(unit)
                    )),
                }), 'units')
            })

            this.topicSaving = true

            try {
                return await this.persistCurriculum({
                    topics: nextTopics,
                }, fallbackMessage)
            } finally {
                this.topicSaving = false
            }
        },

        async toggleFreeWeek(weekKey) {
            if (this.isPageActionLocked) return
            if (this.isWeekSaving(weekKey)) return

            const nextFreeWeeks = this.isFreeWeek(weekKey)
                ? this.freeWeekKeys.filter((value) => value !== weekKey)
                : [...this.freeWeekKeys, weekKey].sort()

            this.savingWeekKeys = [...this.savingWeekKeys, weekKey]

            try {
                await this.persistCurriculum({
                    free_weeks: nextFreeWeeks,
                }, 'Freie Woche konnte nicht gespeichert werden.')
            } finally {
                this.savingWeekKeys = this.savingWeekKeys.filter((value) => value !== weekKey)
            }
        },

        openTopicForm(topic = null) {
            this.topicFormError = null
            this.showTopicForm = true
            this.topicForm = this.newTopicForm(topic)
        },

        cancelTopicForm() {
            if (this.topicSaving) return

            this.topicFormError = null
            this.showTopicForm = false
            this.topicForm = this.newTopicForm()
        },

        openUnitForm(topicId, unit = null) {
            this.unitFormError = null
            this.showUnitFormForTopicId = topicId
            this.unitForm = this.newUnitForm(topicId, unit)
        },

        cancelUnitForm() {
            if (this.topicSaving) return

            this.unitFormError = null
            this.showUnitFormForTopicId = null
            this.unitForm = this.newUnitForm()
        },

        async activateTopicAssignmentMode(topic, assignmentType) {
            if (this.isEditingTopic || this.isEditingUnit) return
            if (this.topicSaving) return

            this.activeTopicAssignmentId = topic.id
            this.activeTopicAssignmentUnitId = null
            this.activeTopicAssignmentType = assignmentType

            if (!['none', 'all_weeks'].includes(assignmentType)) {
                return
            }

            await this.persistTopicAssignment(topic.id, {
                assignment_type: assignmentType,
                month_keys: [],
                week_keys: [],
            }, 'Datumszuordnung konnte nicht gespeichert werden.')
        },

        async activateUnitAssignmentMode(topic, unit, assignmentType) {
            if (this.isEditingTopic || this.isEditingUnit) return
            if (this.topicSaving) return

            this.activeTopicAssignmentId = topic.id
            this.activeTopicAssignmentUnitId = unit.id
            this.activeTopicAssignmentType = assignmentType

            if (!['none', 'all_weeks'].includes(assignmentType)) {
                return
            }

            await this.persistUnitAssignment(topic.id, unit.id, {
                assignment_type: assignmentType,
                month_keys: [],
                week_keys: [],
            }, 'Datumszuordnung der Einheit konnte nicht gespeichert werden.')
        },

        async toggleTopicMonthAssignment(topic, monthKey) {
            if (this.isEditingTopic || this.isEditingUnit) return
            if (this.topicSaving) return

            this.activeTopicAssignmentId = topic.id
            this.activeTopicAssignmentUnitId = null
            this.activeTopicAssignmentType = 'month'

            const monthKeys = topic.assignment_type === 'month'
                ? [...topic.month_keys]
                : []

            const nextMonthKeys = monthKeys.includes(monthKey)
                ? (monthKeys.length === 1 ? monthKeys : monthKeys.filter((value) => value !== monthKey))
                : [...monthKeys, monthKey].sort()

            await this.persistTopicAssignment(topic.id, {
                assignment_type: 'month',
                month_keys: nextMonthKeys,
                week_keys: [],
            }, 'Monatszuordnung konnte nicht gespeichert werden.')
        },

        async toggleUnitMonthAssignment(topic, unit, monthKey) {
            if (this.isEditingTopic || this.isEditingUnit) return
            if (this.topicSaving) return

            this.activeTopicAssignmentId = topic.id
            this.activeTopicAssignmentUnitId = unit.id
            this.activeTopicAssignmentType = 'month'

            const monthKeys = unit.assignment_type === 'month'
                ? [...unit.month_keys]
                : []

            const nextMonthKeys = monthKeys.includes(monthKey)
                ? (monthKeys.length === 1 ? monthKeys : monthKeys.filter((value) => value !== monthKey))
                : [...monthKeys, monthKey].sort()

            await this.persistUnitAssignment(topic.id, unit.id, {
                assignment_type: 'month',
                month_keys: nextMonthKeys,
                week_keys: [],
            }, 'Monatszuordnung der Einheit konnte nicht gespeichert werden.')
        },

        async handleWeekClick(weekKey) {
            if (this.isEditingTopic || this.isEditingUnit) return
            if (!this.isWeekSelectionActive || !this.activeTopicAssignmentTopic || !this.activeAssignmentItem || this.topicSaving) return
            if (this.isFreeWeek(weekKey)) return

            const weekKeys = this.activeAssignmentItem.assignment_type === 'weeks'
                ? [...this.activeAssignmentItem.week_keys]
                : []

            const nextWeekKeys = weekKeys.includes(weekKey)
                ? (weekKeys.length === 1 ? weekKeys : weekKeys.filter((value) => value !== weekKey))
                : [...weekKeys, weekKey].sort()

            if (this.activeTopicAssignmentUnitId) {
                await this.persistUnitAssignment(this.activeTopicAssignmentTopic.id, this.activeTopicAssignmentUnitId, {
                    assignment_type: 'weeks',
                    month_keys: [],
                    week_keys: nextWeekKeys,
                }, 'Wochenauswahl der Einheit konnte nicht gespeichert werden.')

                return
            }

            await this.persistTopicAssignment(this.activeTopicAssignmentTopic.id, {
                assignment_type: 'weeks',
                month_keys: [],
                week_keys: nextWeekKeys,
            }, 'Wochenauswahl konnte nicht gespeichert werden.')
        },

        async saveTopic() {
            this.topicFormError = null

            const title = this.topicForm.title.trim()
            if (!title) {
                this.topicFormError = 'Bitte einen Thementitel eingeben.'
                return
            }

            const topicId = this.topicForm.id || `topic-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
            const existingTopic = this.findTopic(topicId)
            const normalizedTopic = this.buildTopicPayload(existingTopic, {
                id: topicId,
                title,
                assignment_type: existingTopic?.assignment_type ?? 'none',
            })

            const nextTopics = this.curriculumTopics.some((topic) => topic.id === topicId)
                ? this.curriculumTopics.map((topic) => (
                    topic.id === topicId ? normalizedTopic : this.buildTopicPayload(topic)
                ))
                : [...this.curriculumTopics.map((topic) => this.buildTopicPayload(topic)), normalizedTopic]

            this.topicSaving = true

            try {
                const updatedCurriculum = await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Thema konnte nicht gespeichert werden.')

                if (updatedCurriculum) {
                    this.showTopicForm = false
                    this.topicForm = this.newTopicForm()
                }
            } finally {
                this.topicSaving = false
            }
        },

        async saveUnit() {
            this.unitFormError = null

            const title = this.unitForm.title.trim()
            if (!title) {
                this.unitFormError = 'Bitte einen Einheitentitel eingeben.'
                return
            }

            const topic = this.findTopic(this.unitForm.topicId)
            if (!topic) {
                this.unitFormError = 'Das zugehörige Thema wurde nicht gefunden.'
                return
            }

            const unitId = this.unitForm.id || `unit-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
            const existingUnit = this.findUnit(topic.id, unitId)
            const normalizedUnit = this.buildUnitPayload(existingUnit, {
                id: unitId,
                title,
                is_exam: this.unitForm.is_exam,
                assignment_type: existingUnit?.assignment_type ?? 'none',
            })

            const nextTopics = this.curriculumTopics.map((entry) => {
                if (entry.id !== topic.id) {
                    return this.buildTopicPayload(entry)
                }

                const nextUnits = entry.units.some((unit) => unit.id === unitId)
                    ? entry.units.map((unit) => (
                        unit.id === unitId ? normalizedUnit : this.buildUnitPayload(unit)
                    ))
                    : [...entry.units.map((unit) => this.buildUnitPayload(unit)), normalizedUnit]

                return this.buildTopicPayload(entry, {
                    units: nextUnits,
                })
            })

            this.topicSaving = true

            try {
                const updatedCurriculum = await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Einheit konnte nicht gespeichert werden.')

                if (updatedCurriculum) {
                    this.showUnitFormForTopicId = null
                    this.unitForm = this.newUnitForm()
                }
            } finally {
                this.topicSaving = false
            }
        },

        moveArrayItem(items, fromIndex, direction) {
            const targetIndex = fromIndex + direction
            if (fromIndex < 0 || targetIndex < 0 || fromIndex >= items.length || targetIndex >= items.length) {
                return items
            }

            const nextItems = [...items]
            const [movedItem] = nextItems.splice(fromIndex, 1)
            nextItems.splice(targetIndex, 0, movedItem)

            return nextItems
        },

        async moveTopic(topicId, direction) {
            if (this.topicSaving || this.isPageActionLocked) return

            const topicIndex = this.curriculumTopics.findIndex((topic) => topic.id === topicId)
            const nextTopics = this.moveArrayItem(
                this.curriculumTopics.map((topic) => this.buildTopicPayload(topic)),
                topicIndex,
                direction,
            )

            if (nextTopics === this.curriculumTopics || topicIndex === -1) {
                return
            }

            this.topicSaving = true

            try {
                await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Reihenfolge der Themen konnte nicht gespeichert werden.')
            } finally {
                this.topicSaving = false
            }
        },

        async moveUnit(topicId, unitId, direction) {
            if (this.topicSaving || this.isPageActionLocked) return

            const topic = this.findTopic(topicId)
            if (!topic) return

            const unitIndex = topic.units.findIndex((unit) => unit.id === unitId)
            if (unitIndex === -1) return

            const reorderedUnits = this.moveArrayItem(
                topic.units.map((unit) => this.buildUnitPayload(unit)),
                unitIndex,
                direction,
            )

            if (reorderedUnits === topic.units) {
                return
            }

            const nextTopics = this.curriculumTopics.map((entry) => (
                entry.id === topicId
                    ? this.buildTopicPayload(entry, { units: reorderedUnits })
                    : this.buildTopicPayload(entry)
            ))

            this.topicSaving = true

            try {
                await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Reihenfolge der Einheiten konnte nicht gespeichert werden.')
            } finally {
                this.topicSaving = false
            }
        },

        async deleteTopic(topicId) {
            const nextTopics = this.curriculumTopics.filter((topic) => topic.id !== topicId)

            this.topicSaving = true

            try {
                const updatedCurriculum = await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Thema konnte nicht entfernt werden.')

                if (updatedCurriculum && this.topicForm.id === topicId) {
                    this.showTopicForm = false
                    this.topicForm = this.newTopicForm()
                }

                if (updatedCurriculum && this.activeTopicAssignmentId === topicId) {
                    this.activeTopicAssignmentId = null
                    this.activeTopicAssignmentUnitId = null
                    this.activeTopicAssignmentType = null
                }

                return updatedCurriculum !== null
            } finally {
                this.topicSaving = false
            }
        },

        async deleteUnit(topicId, unitId) {
            const nextTopics = this.curriculumTopics.map((topic) => {
                if (topic.id !== topicId) {
                    return this.buildTopicPayload(topic)
                }

                return this.buildTopicPayload(topic, {
                    units: topic.units
                        .filter((unit) => unit.id !== unitId)
                        .map((unit) => this.buildUnitPayload(unit)),
                })
            })

            this.topicSaving = true

            try {
                const updatedCurriculum = await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Einheit konnte nicht entfernt werden.')

                if (updatedCurriculum && this.unitForm.id === unitId) {
                    this.showUnitFormForTopicId = null
                    this.unitForm = this.newUnitForm()
                }

                if (updatedCurriculum && this.activeTopicAssignmentUnitId === unitId) {
                    this.activeTopicAssignmentId = null
                    this.activeTopicAssignmentUnitId = null
                    this.activeTopicAssignmentType = null
                }

                return updatedCurriculum !== null
            } finally {
                this.topicSaving = false
            }
        },

        async loadDocuments() {
            this.docsLoading = true
            try {
                const res = await axios.get(`/api/admin/teaching/curricula/${this.curriculum.id}/documents`)
                this.documents = res.data?.data || []
            } catch {
                this.documents = []
            } finally {
                this.docsLoading = false
            }
        },

        async onFileUploaded() {
            this.showUploadOptions = false
            await this.loadDocuments()
        },

        selectPreview(doc) {
            if (this.isPageActionLocked) return
            if (doc.source_type === 'material') {
                this.openMaterialAttachmentDialog(doc)
                return
            }

            if (doc.source_type !== 'upload') return
            this.previewDoc = this.previewDoc?.id === doc.id ? null : doc
        },

        async removeDocument(doc) {
            if (this.isPageActionLocked) return
            try {
                await axios.delete(`/api/admin/teaching/curricula/${this.curriculum.id}/documents/${doc.id}`)
                if (this.previewDoc?.id === doc.id) this.previewDoc = null
                this.documents = this.documents.filter((d) => d.id !== doc.id)
            } catch {
                // silent
            }
        },

        searchMaterials(value) {
            if (this._materialSearchTimer) clearTimeout(this._materialSearchTimer)
            this._materialSearchTimer = setTimeout(() => {
                this.doSearchMaterials(value || '')
            }, 300)
        },

        async doSearchMaterials(search) {
            this.materialsLoading = true
            try {
                const res = await axios.get('/api/admin/materials/cards', {
                    params: { search, per_page: 20 },
                })
                this.materialResults = res.data?.data || []
            } catch {
                this.materialResults = []
            } finally {
                this.materialsLoading = false
            }
        },

        async attachMaterial(card) {
            if (this.isPageActionLocked) return
            try {
                const res = await axios.post(`/api/admin/teaching/curricula/${this.curriculum.id}/documents/attach-material`, {
                    material_card_id: card.id,
                })
                const attachedDocument = res.data?.data || null
                this.materialDialogOpen = false
                this.showUploadOptions = false
                await this.loadDocuments()
                if (attachedDocument?.id) {
                    const nextDocument = this.documents.find((document) => document.id === attachedDocument.id) || attachedDocument
                    await this.openMaterialAttachmentDialog(nextDocument)
                }
            } catch {
                // silent
            }
        },

        async openMaterialAttachmentDialog(doc) {
            if (this.isPageActionLocked || !doc || doc.source_type !== 'material') return

            this.materialAttachmentDialogOpen = true
            this.materialAttachmentDocument = doc
            this.materialAttachmentOptions = []
            this.materialAttachmentError = null
            this.selectedMaterialAttachmentId = doc.material_card_attachment_id || null
            this.materialAttachmentLoading = true

            try {
                const res = await axios.get(`/api/admin/teaching/curricula/${this.curriculum.id}/documents/${doc.id}/material-attachments`)
                this.materialAttachmentOptions = res.data?.data || []
                this.selectedMaterialAttachmentId = res.data?.meta?.selected_attachment_id ?? doc.material_card_attachment_id ?? null
            } catch {
                this.materialAttachmentOptions = []
                this.materialAttachmentError = 'Die Anhänge des Materials konnten nicht geladen werden.'
            } finally {
                this.materialAttachmentLoading = false
            }
        },

        closeMaterialAttachmentDialog() {
            this.materialAttachmentDialogOpen = false
            this.materialAttachmentDocument = null
            this.materialAttachmentOptions = []
            this.materialAttachmentError = null
            this.selectedMaterialAttachmentId = null
        },

        async selectMaterialAttachment(attachment) {
            if (
                this.isPageActionLocked
                || this.savingMaterialAttachment
                || !this.materialAttachmentDocument
                || !attachment?.id
            ) {
                return
            }

            this.savingMaterialAttachment = true

            try {
                const res = await axios.patch(
                    `/api/admin/teaching/curricula/${this.curriculum.id}/documents/${this.materialAttachmentDocument.id}/material-attachment`,
                    {
                        material_card_attachment_id: attachment.id,
                    },
                )

                const updatedDocument = res.data?.data || null
                if (!updatedDocument) {
                    return
                }

                this.documents = this.documents.map((document) => (
                    document.id === updatedDocument.id ? updatedDocument : document
                ))
                this.previewDoc = updatedDocument
                this.savingMaterialAttachment = false
                this.closeMaterialAttachmentDialog()
            } catch {
                this.materialAttachmentError = 'Der ausgewählte Anhang konnte nicht gespeichert werden.'
            } finally {
                this.savingMaterialAttachment = false
            }
        },

        formatBytes(bytes) {
            const size = Number(bytes)
            if (!Number.isFinite(size) || size <= 0) {
                return ''
            }

            if (size < 1024) {
                return `${size} B`
            }

            const units = ['KB', 'MB', 'GB']
            let value = size / 1024
            let unitIndex = 0

            while (value >= 1024 && unitIndex < units.length - 1) {
                value /= 1024
                unitIndex += 1
            }

            return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[unitIndex]}`
        },
    },
}
</script>

<style scoped>
.curriculum-detail {
    width: 100%;
}

.curriculum-detail__title-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.curriculum-detail__title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #e2e8f0;
    margin: 0;
}

.curriculum-detail__desc {
    font-size: 0.88rem;
    color: #94a3b8;
    margin: 4px 0 0;
}

.curriculum-detail__picker-sheet {
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: rgba(15, 23, 42, 0.7);
}

.semester-toggle__btn {
    color: #c7d2fe !important;
    border-color: rgba(148, 163, 184, 0.35) !important;
}

.semester-toggle .v-btn--active.semester-toggle__btn {
    color: #fff !important;
}

/* ---------- Body layout ---------- */
.curriculum-detail__body {
    display: grid;
    grid-template-columns: auto auto 1fr;
    align-items: start;
    gap: 16px;
}

/* ---------- Calendar ---------- */
.curriculum-detail__calendar {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    flex-shrink: 0;
    width: clamp(430px, 29vw, 500px);
}

.curriculum-detail__month {
    --accent: hsl(var(--month-hue), 65%, 68%);
    --accent-dim: hsl(var(--month-hue), 45%, 22%);
    --accent-glow: hsl(var(--month-hue), 70%, 50%);
    border-radius: 20px;
    border: 1px solid hsl(var(--month-hue), 50%, 30%, 0.35);
    background: linear-gradient(
        135deg,
        hsl(var(--month-hue), 35%, 12%, 0.85) 0%,
        rgba(15, 23, 42, 0.85) 100%
    );
    overflow: hidden;
    backdrop-filter: blur(12px);
    width: 100%;
    box-sizing: border-box;
}

.curriculum-detail__month-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    padding: 14px 20px 8px;
    border-bottom: 1px solid hsl(var(--month-hue), 50%, 30%, 0.25);
}

.curriculum-detail__month-name {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--accent);
    letter-spacing: 0.02em;
}

.curriculum-detail__month-year {
    font-size: 0.76rem;
    font-weight: 600;
    color: #64748b;
}

.curriculum-detail__month--with-topics {
    box-shadow: 0 0 0 1px rgba(165, 180, 252, 0.08), 0 14px 30px rgba(15, 23, 42, 0.24);
}

.curriculum-detail__month--with-exams {
    box-shadow: 0 0 0 1px rgba(251, 191, 36, 0.2), 0 14px 30px rgba(120, 53, 15, 0.22);
}

.curriculum-detail__month-topics {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 12px 18px 4px;
    border-bottom: 1px solid hsl(var(--month-hue), 50%, 30%, 0.18);
}

.curriculum-detail__month-topics-label {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(191, 219, 254, 0.88);
}

.curriculum-detail__month-topics-text {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    column-gap: 10px;
    row-gap: 4px;
    color: #dbeafe;
    font-size: 0.78rem;
    font-weight: 600;
    line-height: 1.3;
}

.curriculum-detail__overview-entry {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-width: 0;
    max-width: 100%;
    color: inherit;
    white-space: normal;
    overflow-wrap: anywhere;
}

.curriculum-detail__overview-entry--exam {
    color: #fde68a;
    text-shadow: 0 0 12px rgba(245, 158, 11, 0.24);
}

.curriculum-detail__overview-entry-icon {
    color: currentColor;
    flex-shrink: 0;
}

/* ---------- Weeks ---------- */
.curriculum-detail__weeks {
    padding: 10px 12px 14px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    width: 100%;
}

.curriculum-detail__week {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 6px 10px;
    border-radius: 12px;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(148, 163, 184, 0.08);
    transition: background 0.2s, border-color 0.2s;
    position: relative;
    width: 100%;
    box-sizing: border-box;
}

.curriculum-detail__week:hover {
    background: rgba(30, 41, 59, 0.7);
    border-color: rgba(148, 163, 184, 0.18);
}

.curriculum-detail__week--topic-selectable {
    cursor: pointer;
    border-color: rgba(129, 140, 248, 0.28);
}

.curriculum-detail__week--topic-selectable:hover {
    background: rgba(49, 46, 129, 0.18);
    border-color: rgba(129, 140, 248, 0.42);
}

.curriculum-detail__week--topic-selected {
    background: rgba(99, 102, 241, 0.18) !important;
    border-color: rgba(129, 140, 248, 0.52) !important;
    box-shadow: 0 0 0 1px rgba(165, 180, 252, 0.16), 0 0 18px rgba(99, 102, 241, 0.16);
}

.curriculum-detail__week--current {
    background: rgba(99, 102, 241, 0.12) !important;
    border-color: rgba(99, 102, 241, 0.35) !important;
    box-shadow: 0 0 16px rgba(99, 102, 241, 0.15);
}

.curriculum-detail__week--free {
    background: rgba(34, 197, 94, 0.12) !important;
    border-color: rgba(34, 197, 94, 0.35) !important;
    box-shadow: 0 0 16px rgba(34, 197, 94, 0.12);
}

.curriculum-detail__week--with-topics {
    border-color: rgba(96, 165, 250, 0.48);
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.2), rgba(30, 41, 59, 0.9) 42%, rgba(15, 23, 42, 0.82));
    box-shadow: 0 0 0 1px rgba(147, 197, 253, 0.14), 0 0 18px rgba(59, 130, 246, 0.16);
}

.curriculum-detail__week--with-topics:hover {
    border-color: rgba(125, 211, 252, 0.58);
    box-shadow: 0 0 0 1px rgba(147, 197, 253, 0.18), 0 0 22px rgba(56, 189, 248, 0.2);
}

/* ---------- Week number ---------- */
.curriculum-detail__week-number {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 32px;
    flex-shrink: 0;
}

.curriculum-detail__week-kw {
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.08em;
}

.curriculum-detail__week-num {
    font-size: 1rem;
    font-weight: 800;
    color: var(--accent);
    line-height: 1;
}

/* ---------- Days grid ---------- */
.curriculum-detail__week-days {
    display: flex;
    gap: 2px;
    flex: 1;
}

.curriculum-detail__day {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 38px;
    border-radius: 10px;
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(148, 163, 184, 0.06);
    transition: all 0.15s;
}

.curriculum-detail__day--outside {
    opacity: 0.2;
}

.curriculum-detail__day--today {
    background: linear-gradient(135deg, #4f46e5, #7c3aed) !important;
    border-color: #818cf8 !important;
    opacity: 1 !important;
    box-shadow: 0 0 14px rgba(99, 102, 241, 0.5);
}

.curriculum-detail__day-name {
    font-size: 0.54rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    line-height: 1;
}

.curriculum-detail__day--today .curriculum-detail__day-name {
    color: #c7d2fe;
}

.curriculum-detail__day-num {
    font-size: 0.82rem;
    font-weight: 700;
    color: #cbd5e1;
    line-height: 1.2;
}

.curriculum-detail__day--today .curriculum-detail__day-num {
    color: #fff;
}

/* ---------- Week range label ---------- */
.curriculum-detail__week-range {
    font-size: 0.7rem;
    color: #475569;
    font-weight: 500;
    white-space: nowrap;
    min-width: 64px;
    text-align: right;
}

.curriculum-detail__week--free .curriculum-detail__week-range {
    color: #86efac;
}

.curriculum-detail__week--with-topics .curriculum-detail__week-range {
    color: #bfdbfe;
}

.curriculum-detail__week-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    margin-left: auto;
}

.curriculum-detail__week-topics {
    order: 4;
    flex-basis: 100%;
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    column-gap: 10px;
    row-gap: 4px;
    margin-top: -2px;
    color: #dbeafe;
    font-size: 0.74rem;
    font-weight: 600;
    line-height: 1.2;
    text-align: left;
}

.curriculum-detail__week--with-topics .curriculum-detail__week-topics {
    color: #eff6ff;
    text-shadow: 0 1px 10px rgba(37, 99, 235, 0.22);
}

.curriculum-detail__week--with-exams {
    border-color: rgba(251, 191, 36, 0.26);
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.68) 0%, rgba(120, 53, 15, 0.18) 100%);
    box-shadow: inset 0 0 0 1px rgba(251, 191, 36, 0.06), 0 8px 18px rgba(120, 53, 15, 0.14);
}

.curriculum-detail__week--with-exams .curriculum-detail__week-topics {
    color: #fef3c7;
    text-shadow: 0 1px 14px rgba(245, 158, 11, 0.28);
}

.curriculum-detail__week-chip {
    font-weight: 700;
    letter-spacing: 0.02em;
}

/* ---------- Side cards ---------- */
.curriculum-detail__side-card {
    position: sticky;
    top: 12px;
}

.curriculum-detail__side-card-inner {
    border-radius: 20px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.85) 0%, rgba(15, 23, 42, 0.85) 100%);
    backdrop-filter: blur(12px);
    min-height: 200px;
}

.curriculum-detail__side-card-header {
    display: flex;
    align-items: center;
    padding: 14px 20px 10px;
    border-bottom: 1px solid rgba(148, 163, 184, 0.12);
    font-size: 1.15rem;
    font-weight: 700;
    color: #e2e8f0;
}

.curriculum-detail__side-card-body {
    padding: 14px 20px;
}

.curriculum-detail__side-card--content .curriculum-detail__side-card-inner {
    border-color: rgba(129, 140, 248, 0.28);
    background:
        radial-gradient(circle at top right, rgba(96, 165, 250, 0.14), transparent 34%),
        linear-gradient(135deg, rgba(30, 41, 59, 0.96) 0%, rgba(17, 24, 39, 0.98) 100%);
    box-shadow: 0 16px 36px rgba(2, 6, 23, 0.34);
}

.curriculum-detail__side-card--content {
    width: clamp(420px, 36vw, 640px);
    max-width: 100%;
}

.curriculum-detail__side-card--content .curriculum-detail__side-card-body {
    color: #e5eefc;
}

.curriculum-detail__content-toolbar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.curriculum-detail__content-count {
    font-size: 0.9rem;
    font-weight: 700;
    color: #e2e8f0;
}

.curriculum-detail__content-hint {
    font-size: 0.8rem;
    color: #bfdbfe;
    line-height: 1.45;
    margin-top: 4px;
}

.curriculum-detail__topic-form {
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.22);
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.92), rgba(15, 23, 42, 0.84));
    padding: 14px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
}

.curriculum-detail__topic-form-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.curriculum-detail__topic-form-error {
    font-size: 0.8rem;
    color: #fecaca;
}

.curriculum-detail__editor-dialog-card {
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%) !important;
    color: #0f172a;
    border: 1px solid rgba(148, 163, 184, 0.22);
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.14);
}

.curriculum-detail__editor-dialog-form {
    border-color: rgba(148, 163, 184, 0.18);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(241, 245, 249, 0.96));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
}

.curriculum-detail__editor-dialog-card :deep(.v-card-title) {
    color: #0f172a;
}

.curriculum-detail__editor-dialog-card :deep(.v-field) {
    background: rgba(255, 255, 255, 0.96);
    border-radius: 12px;
}

.curriculum-detail__editor-dialog-card :deep(.v-field__overlay) {
    background: transparent;
}

.curriculum-detail__editor-dialog-card :deep(.v-field__outline) {
    --v-field-border-opacity: 1;
    color: rgba(148, 163, 184, 0.4);
}

.curriculum-detail__editor-dialog-card :deep(.v-label),
.curriculum-detail__editor-dialog-card :deep(.v-field-label) {
    color: #64748b !important;
    opacity: 1;
}

.curriculum-detail__editor-dialog-card :deep(input),
.curriculum-detail__editor-dialog-card :deep(textarea),
.curriculum-detail__editor-dialog-card :deep(.v-field__input) {
    color: #0f172a !important;
}

.curriculum-detail__editor-dialog-save-btn {
    background: #334155 !important;
    color: #f8fafc !important;
    box-shadow: 0 8px 18px rgba(51, 65, 85, 0.18);
}

.curriculum-detail__editor-dialog-cancel-btn {
    color: #64748b !important;
}

.curriculum-detail__topic-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.curriculum-detail__topic-item {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 12px;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.78));
}

.curriculum-detail__topic-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.curriculum-detail__topic-main {
    min-width: 0;
    flex: 1;
}

.curriculum-detail__topic-title-row {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    flex-wrap: wrap;
}

.curriculum-detail__topic-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #e2e8f0;
    line-height: 1.35;
}

.curriculum-detail__topic-summary-chip {
    font-weight: 600;
}

.curriculum-detail__topic-meta-chip--interactive {
    cursor: pointer;
}

.curriculum-detail__topic-meta-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}

.curriculum-detail__topic-meta-text {
    margin-top: 8px;
    font-size: 0.8rem;
    color: #bfdbfe;
}

.curriculum-detail__topic-actions {
    display: flex;
    align-items: center;
    gap: 2px;
    flex-shrink: 0;
}

.curriculum-detail__unit-section {
    border-top: 1px solid rgba(148, 163, 184, 0.12);
    padding-top: 10px;
}

.curriculum-detail__unit-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.curriculum-detail__unit-count {
    font-size: 0.78rem;
    font-weight: 700;
    color: #94a3b8;
    letter-spacing: 0.02em;
}

.curriculum-detail__unit-form {
    margin-left: 14px;
}

.curriculum-detail__unit-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-left: 14px;
}

.curriculum-detail__unit-item {
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid rgba(148, 163, 184, 0.12);
    background: rgba(15, 23, 42, 0.34);
}

.curriculum-detail__unit-title {
    font-size: 0.88rem;
    font-weight: 500;
    color: #cbd5e1;
    line-height: 1.35;
}

.curriculum-detail__unit-exam-checkbox :deep(.v-selection-control) {
    min-height: 34px;
}

.curriculum-detail__unit-exam-checkbox :deep(.v-label) {
    color: #334155;
    font-weight: 600;
}

.curriculum-detail__unit-empty {
    margin-left: 14px;
    font-size: 0.8rem;
    color: #94a3b8;
}

.curriculum-detail__topic-assignment-panel {
    margin-top: 12px;
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px solid rgba(129, 140, 248, 0.34);
    background:
        linear-gradient(180deg, rgba(49, 46, 129, 0.18), rgba(15, 23, 42, 0.48)),
        rgba(15, 23, 42, 0.72);
    box-shadow:
        inset 0 0 0 1px rgba(165, 180, 252, 0.06),
        0 10px 24px rgba(15, 23, 42, 0.28);
}

.curriculum-detail__topic-assignment-options {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(129, 140, 248, 0.16);
}

.curriculum-detail__topic-assignment-months,
.curriculum-detail__topic-assignment-weeks {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
}

.curriculum-detail__topic-assignment-weeks {
    flex-direction: column;
    align-items: flex-start;
}

.curriculum-detail__assignment-week-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}

.curriculum-detail__assignment-week-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
    flex-wrap: wrap;
}

.curriculum-detail__assignment-week-controls {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.curriculum-detail__topic-assignment-hint {
    font-size: 0.8rem;
    color: #dbeafe;
    font-weight: 600;
    letter-spacing: 0.01em;
    padding: 6px 10px;
    border-radius: 10px;
    background: rgba(59, 130, 246, 0.12);
    border: 1px solid rgba(96, 165, 250, 0.18);
}

.curriculum-detail__assignment-chip {
    cursor: pointer;
}

.curriculum-detail__topic-empty {
    font-size: 0.84rem;
    color: #cbd5e1;
}

.curriculum-detail__content-add-btn,
.curriculum-detail__topic-save-btn {
    color: #eff6ff !important;
    font-weight: 700;
    box-shadow: 0 10px 24px rgba(79, 70, 229, 0.26);
}

.curriculum-detail__side-card--content :deep(.v-field) {
    background: rgba(248, 250, 252, 0.97);
    border-radius: 12px;
}

.curriculum-detail__side-card--content :deep(.v-field__overlay) {
    background: transparent;
}

.curriculum-detail__side-card--content :deep(.v-field__outline) {
    --v-field-border-opacity: 1;
    color: rgba(148, 163, 184, 0.38);
}

.curriculum-detail__side-card--content :deep(.v-label),
.curriculum-detail__side-card--content :deep(.v-field-label) {
    color: #475569 !important;
    opacity: 1;
}

.curriculum-detail__side-card--content :deep(input),
.curriculum-detail__side-card--content :deep(textarea),
.curriculum-detail__side-card--content :deep(.v-field__input),
.curriculum-detail__side-card--content :deep(.v-select__selection-text),
.curriculum-detail__side-card--content :deep(.v-autocomplete__selection) {
    color: #0f172a !important;
}

.curriculum-detail__side-card--content :deep(.v-field__append-inner .v-icon),
.curriculum-detail__side-card--content :deep(.v-field__clearable .v-icon),
.curriculum-detail__side-card--content :deep(.v-select__menu-icon) {
    color: #475569 !important;
}

.curriculum-detail__side-card--content :deep(.v-chip.v-chip--size-small),
.curriculum-detail__side-card--content :deep(.v-chip.v-chip--size-x-small) {
    color: #e2e8f0;
}

/* ---------- Responsive ---------- */
/* ---------- Lehrpläne list ---------- */
.lehrplaene__list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.lehrplaene__item {
    display: flex;
    align-items: center;
    padding: 6px 8px;
    border-radius: 8px;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(148, 163, 184, 0.08);
}

.lehrplaene__item-copy {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.lehrplaene__item-name {
    font-size: 0.82rem;
    color: #cbd5e1;
    font-weight: 500;
}

.lehrplaene__item-subtitle {
    font-size: 0.72rem;
    color: #94a3b8;
}

.lehrplaene__item--clickable {
    cursor: pointer;
}

.lehrplaene__item--clickable:hover {
    background: rgba(99, 102, 241, 0.08);
    border-color: rgba(99, 102, 241, 0.2);
}

.lehrplaene__item--active {
    background: rgba(99, 102, 241, 0.15) !important;
    border-color: rgba(99, 102, 241, 0.35) !important;
}

/* ---------- Preview ---------- */
.lehrplaene__preview {
    border-radius: 12px;
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: rgba(15, 23, 42, 0.6);
    overflow: hidden;
}

.lehrplaene__preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 10px;
    border-bottom: 1px solid rgba(148, 163, 184, 0.1);
    font-size: 0.78rem;
    font-weight: 600;
    color: #a5b4fc;
}

.lehrplaene__preview-body {
    min-height: 200px;
}

.lehrplaene__preview-iframe {
    width: 100%;
    aspect-ratio: 1 / 1.4142;
    border: none;
    background: #fff;
}

.lehrplaene__preview-image {
    width: 100%;
    aspect-ratio: 1 / 1.4142;
    object-fit: contain;
    display: block;
    background: rgba(15, 23, 42, 0.8);
}

.lehrplaene__material-item {
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(99, 102, 241, 0.15);
    cursor: pointer;
}

.lehrplaene__material-item:hover {
    background: rgba(99, 102, 241, 0.12);
}

.lehrplaene__attachment-item.v-list-item--active {
    background: rgba(99, 102, 241, 0.18);
    border-color: rgba(129, 140, 248, 0.34);
}

@media (max-width: 900px) {
    .curriculum-detail__body {
        grid-template-columns: 1fr;
        width: auto;
    }

    .curriculum-detail__side-card {
        position: static;
    }

    .curriculum-detail__side-card--content {
        width: 100%;
    }
}

@media (max-width: 700px) {
    .curriculum-detail__day {
        width: 30px;
        height: 34px;
    }

    .curriculum-detail__week-range {
        display: none;
    }

    .curriculum-detail__month-header {
        padding: 10px 14px 6px;
    }

    .curriculum-detail__week-actions {
        margin-left: auto;
    }

    .curriculum-detail__content-toolbar,
    .curriculum-detail__topic-row,
    .curriculum-detail__topic-assignment-options {
        flex-direction: column;
    }
}
</style>
