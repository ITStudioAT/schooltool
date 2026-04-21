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
                    <v-btn
                        v-if="hasFreeWeeksTemplate"
                        size="small"
                        variant="tonal"
                        color="success"
                        rounded="xl"
                        class="text-none"
                        :loading="isApplyingFreeWeeksTemplate"
                        :disabled="!canApplyFreeWeeksTemplate"
                        @click="applyFreeWeeksTemplate">
                        Freie Tage übernehmen
                    </v-btn>
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
            <v-sheet rounded="xl" class="curriculum-detail__view-toolbar pa-3 mt-3">
                <div class="curriculum-detail__view-toolbar-row">
                    <div class="curriculum-detail__week-view">
                        <div class="curriculum-detail__week-view-label">Wochenansicht</div>
                        <v-btn-toggle
                            v-model="weekDisplayMode"
                            mandatory
                            color="primary"
                            density="compact"
                            rounded="lg"
                            class="curriculum-detail__week-view-toggle">
                            <v-btn value="days" variant="outlined" class="text-none px-3 curriculum-detail__week-view-btn">
                                <v-icon size="15" class="mr-1">mdi-calendar-week</v-icon>
                                Mit Tagen
                            </v-btn>
                            <v-btn value="compact" variant="outlined" class="text-none px-3 curriculum-detail__week-view-btn">
                                <v-icon size="15" class="mr-1">mdi-view-compact-outline</v-icon>
                                Ohne Tage
                            </v-btn>
                        </v-btn-toggle>
                    </div>
                    <div class="curriculum-detail__week-view">
                        <div class="curriculum-detail__week-view-label">Volle Monate</div>
                        <v-btn-toggle
                            v-model="collapseFullMonths"
                            mandatory
                            color="primary"
                            density="compact"
                            rounded="lg"
                            class="curriculum-detail__week-view-toggle">
                            <v-btn :value="false" variant="outlined" class="text-none px-3 curriculum-detail__week-view-btn">
                                <v-icon size="15" class="mr-1">mdi-arrow-expand-vertical</v-icon>
                                Immer zeigen
                            </v-btn>
                            <v-btn :value="true" variant="outlined" class="text-none px-3 curriculum-detail__week-view-btn">
                                <v-icon size="15" class="mr-1">mdi-arrow-collapse-vertical</v-icon>
                                Einklappen
                            </v-btn>
                        </v-btn-toggle>
                    </div>
                    <div class="curriculum-detail__week-view">
                        <div class="curriculum-detail__week-view-label">Lehrpläne</div>
                        <v-btn-toggle
                            v-model="showLehrplaeneCard"
                            mandatory
                            color="primary"
                            density="compact"
                            rounded="lg"
                            class="curriculum-detail__week-view-toggle">
                            <v-btn :value="true" variant="outlined" class="text-none px-3 curriculum-detail__week-view-btn">
                                <v-icon size="15" class="mr-1">mdi-eye-outline</v-icon>
                                Anzeigen
                            </v-btn>
                            <v-btn :value="false" variant="outlined" class="text-none px-3 curriculum-detail__week-view-btn">
                                <v-icon size="15" class="mr-1">mdi-eye-off-outline</v-icon>
                                Ausblenden
                            </v-btn>
                        </v-btn-toggle>
                    </div>
                </div>
            </v-sheet>
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

        <div
            class="curriculum-detail__body"
            :class="{ 'curriculum-detail__body--compact-calendar': isCompactWeekView }">
            <v-sheet ref="calendarScroll" rounded="xl" class="curriculum-detail__calendar-scroll pa-2">
                <div
                    class="curriculum-detail__calendar"
                    :class="{ 'curriculum-detail__calendar--compact': isCompactWeekView }"
                    :style="calendarHighlightStyle">
                    <div
                        v-for="(month, idx) in visibleMonths"
                        :key="month.key"
                        class="curriculum-detail__month"
                        :class="{
                            'curriculum-detail__month--with-topics': topicsForMonth(month).length > 0,
                            'curriculum-detail__month--with-exams': monthHasExamEntries(month),
                            'curriculum-detail__month--topic-selected': isMonthAssignedToHighlightedItem(month),
                            'curriculum-detail__month--collapsed': shouldCollapseMonth(month),
                        }"
                        :data-month-key="month.assignmentKey"
                        :style="{ '--month-hue': monthHue(idx) }">
                        <button
                            type="button"
                            class="curriculum-detail__month-header"
                            @click="toggleMonthCollapse(month)">
                            <div class="curriculum-detail__month-name">{{ month.name }}</div>
                            <div class="curriculum-detail__month-year">{{ month.year }}</div>
                        </button>
                        <div v-if="monthOverviewEntries(month).length" class="curriculum-detail__month-topics">
                            <div v-if="!shouldCollapseMonth(month)" class="curriculum-detail__month-topics-label">Themen</div>
                            <div class="curriculum-detail__month-topics-text">
                                <div
                                    v-for="group in monthOverviewGroups(month)"
                                    :key="group.id"
                                    class="curriculum-detail__month-topic-line">
                                    <template v-if="group.units.length">
                                        <span class="curriculum-detail__month-topic-name">{{ `${group.topicTitle}: ` }}</span>
                                        <span class="curriculum-detail__month-topic-units">
                                            <span
                                                v-for="(unit, unitIndex) in group.units"
                                                :key="unit.id"
                                                class="curriculum-detail__overview-entry"
                                                :class="{ 'curriculum-detail__overview-entry--exam': unit.isExam }">
                                                <v-icon
                                                    v-if="unit.isExam"
                                                    size="13"
                                                    class="curriculum-detail__overview-entry-icon">
                                                    mdi-clipboard-check-outline
                                                </v-icon>
                                                <span class="curriculum-detail__month-topic-unit">
                                                    {{ unit.title }}<span v-if="unitIndex < group.units.length - 1">,</span>
                                                </span>
                                            </span>
                                        </span>
                                    </template>
                                    <span v-else class="curriculum-detail__month-topic-name">{{ group.topicTitle }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="!shouldCollapseMonth(month)" class="curriculum-detail__weeks">
                            <div
                                v-for="(week, wIdx) in month.weeks"
                                :key="wIdx"
                                class="curriculum-detail__week"
                                :class="{
                                    'curriculum-detail__week--current': week.isCurrent,
                                    'curriculum-detail__week--free': isFreeWeek(week.weekKey),
                                    'curriculum-detail__week--with-topics': topicsForWeek(week.weekKey).length > 0,
                                    'curriculum-detail__week--with-exams': weekHasExamEntries(week.weekKey),
                                    'curriculum-detail__week--compact': isCompactWeekView,
                                    'curriculum-detail__week--topic-selectable': isWeekSelectableForTopic(week.weekKey),
                                    'curriculum-detail__week--topic-selected': isWeekAssignedToHighlightedItem(week.weekKey),
                                }"
                                :data-week-key="week.weekKey"
                                @click="handleWeekClick(week.weekKey)">
                                <div v-if="topicsForWeek(week.weekKey).length" class="curriculum-detail__week-status">
                                    <v-icon size="18" color="success" class="curriculum-detail__week-status-icon">
                                        mdi-check-circle
                                    </v-icon>
                                </div>
                                <div class="curriculum-detail__week-number">
                                    <span class="curriculum-detail__week-kw">KW</span>
                                    <span class="curriculum-detail__week-num">{{ week.kw }}</span>
                                </div>
                                <div v-if="showWeekdays" class="curriculum-detail__week-days">
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
                                    <span
                                        class="curriculum-detail__week-range-label"
                                        :class="{ 'curriculum-detail__week-range-label--selected': isWeekAssignedToHighlightedItem(week.weekKey) }">
                                        {{ week.rangeLabel }}
                                    </span>
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
                                        <span class="curriculum-detail__week-topic-label">
                                            <template v-if="entry.unitTitle">
                                                <span v-if="entry.showTopicPrefix" class="curriculum-detail__week-topic-label-topic">{{ `${entry.topicTitle}:` }}</span>
                                                <span
                                                    class="curriculum-detail__week-topic-label-unit"
                                                    :class="{ 'curriculum-detail__week-topic-label-unit--no-prefix': !entry.showTopicPrefix }">
                                                    {{ entry.unitTitle }}
                                                </span>
                                            </template>
                                            <template v-else>
                                                {{ entry.title }}
                                            </template>
                                        </span>
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
            </v-sheet>

            <v-sheet rounded="xl" class="curriculum-detail__side-card curriculum-detail__side-card--content">
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
                                class="curriculum-detail__topic-entry">
                                <div
                                    class="curriculum-detail__topic-item"
                                    :class="{ 'curriculum-detail__topic-item--selected': isTopicSelected(topic.id) }"
                                    :style="{ '--topic-hue': topicHue(topicIndex) }">
                                    <div class="curriculum-detail__topic-row" @click="toggleSelectedTopic(topic.id)">
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
                                                    variant="tonal"
                                                    class="curriculum-detail__topic-meta-chip--interactive"
                                                    @click.stop="openTopicAssignmentEditor(topic, 'month')">
                                                    {{ monthChipLabel(monthKey) }}
                                                </v-chip>
                                            </template>
                                            <template v-else-if="topic.assignment_type === 'weeks' && topic.week_keys.length">
                                                <v-chip
                                                    size="x-small"
                                                    color="primary"
                                                    variant="tonal"
                                                    class="curriculum-detail__topic-meta-chip--interactive"
                                                    @click.stop="openTopicAssignmentEditor(topic, 'weeks')">
                                                    {{ weeksSummaryLabel(topic.week_keys) }}
                                                </v-chip>
                                            </template>
                                        </div>
                                        <div v-if="topic.materials.length" class="curriculum-detail__attached-materials">
                                            <div
                                                v-for="material in topic.materials"
                                                :key="`topic-${topic.id}-material-${material.id}`"
                                                class="curriculum-detail__attached-material">
                                                <div class="curriculum-detail__attached-material-copy">
                                                    <div class="curriculum-detail__attached-material-title">{{ material.title }}</div>
                                                    <div class="curriculum-detail__attached-material-subtitle">
                                                        {{ attachedMaterialSubtitle(material) }}
                                                    </div>
                                                </div>
                                                <div class="curriculum-detail__attached-material-actions">
                                                    <v-btn
                                                        v-if="materialFileAttachmentCount(material) > 0"
                                                        variant="tonal"
                                                        color="primary"
                                                        size="x-small"
                                                        class="text-none curriculum-detail__attached-material-preview-btn"
                                                        :disabled="topicSaving || isPageActionLocked"
                                                        @click.stop="openAttachedMaterialDialog(material)">
                                                        <v-icon size="14" start>mdi-paperclip</v-icon>
                                                        {{ materialAttachmentCountLabel(material) }}
                                                    </v-btn>
                                                    <v-btn
                                                        icon="mdi-close"
                                                        variant="text"
                                                        color="error"
                                                        size="x-small"
                                                        :disabled="topicSaving || isPageActionLocked"
                                                        title="Material entfernen"
                                                        @click.stop="removeTopicMaterial(topic.id, material.id)" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="curriculum-detail__topic-header-actions">
                                        <div class="curriculum-detail__topic-actions" @click.stop>
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
                                            icon="mdi-book-plus-outline"
                                            variant="text"
                                            color="primary"
                                            size="x-small"
                                            :disabled="topicSaving || isPageActionLocked"
                                            title="Materialien hinzufügen"
                                            @click="openContentMaterialDialog({
                                                type: 'topic',
                                                topicId: topic.id,
                                            })" />
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
                                        <div v-if="topic.units.length" class="curriculum-detail__topic-collapse-toggle" @click.stop>
                                            <div
                                                v-if="isTopicCollapsed(topic.id)"
                                                class="curriculum-detail__topic-collapsed-count">
                                                {{ topic.units.length }} Einheiten
                                            </div>
                                            <v-btn
                                                :class="['curriculum-detail__topic-collapse-btn', { 'ml-auto': !isTopicCollapsed(topic.id) }]"
                                                :icon="isTopicCollapsed(topic.id) ? 'mdi-chevron-down' : 'mdi-chevron-up'"
                                                variant="flat"
                                                color="primary"
                                                size="x-small"
                                                :title="isTopicCollapsed(topic.id) ? 'Thema aufklappen' : 'Thema einklappen'"
                                                @click="toggleTopicCollapse(topic.id)" />
                                        </div>
                                    </div>
                                </div>

                                    <div
                                        v-if="!isTopicCollapsed(topic.id) && isTopicAssignmentEditorOpen(topic.id)"
                                        class="curriculum-detail__topic-assignment-panel"
                                        @click.stop>
                                            <div class="curriculum-detail__topic-assignment-options">
                                                <v-btn
                                                    :variant="activeTopicAssignmentType === 'none' ? 'flat' : 'tonal'"
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
                                                    :variant="activeTopicAssignmentType === 'month' ? 'flat' : 'tonal'"
                                                    size="x-small"
                                                    rounded="lg"
                                                    class="text-none"
                                                    :color="activeTopicAssignmentType === 'month' ? 'primary' : 'secondary'"
                                                    :disabled="topicSaving || isEditingTopic"
                                                    @click="activateTopicAssignmentMode(topic, 'month')">
                                                    Monate
                                                </v-btn>
                                                <v-btn
                                                    :variant="activeTopicAssignmentType === 'weeks' ? 'flat' : 'tonal'"
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

                                    <div v-if="!isTopicCollapsed(topic.id)" class="curriculum-detail__unit-section">
                                            <div class="curriculum-detail__unit-toolbar">
                                                <div class="curriculum-detail__unit-summary">
                                                    <div class="curriculum-detail__unit-count">{{ topic.units.length }} Einheiten</div>
                                                    <v-btn
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        rounded="lg"
                                                        class="text-none"
                                                        :disabled="isPageActionLocked || topicSaving || !canDistributeTopicUnits(topic)"
                                                        @click="distributeTopicUnits(topic)">
                                                        Verteilen
                                                    </v-btn>
                                                </div>
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
                                            class="curriculum-detail__unit-item"
                                            :class="{ 'curriculum-detail__unit-item--selected': isUnitSelected(topic.id, unit.id) }"
                                            @click="toggleSelectedUnit(topic.id, unit.id)">
                                            <div class="curriculum-detail__topic-row">
                                                <div class="curriculum-detail__topic-main">
                                                    <div class="curriculum-detail__unit-title-row">
                                                        <div class="curriculum-detail__unit-title">{{ unit.title }}</div>
                                                        <v-chip
                                                            v-if="unit.is_exam"
                                                            size="x-small"
                                                            color="primary"
                                                            variant="tonal"
                                                            class="curriculum-detail__unit-exam-chip">
                                                            Prüfung
                                                        </v-chip>
                                                    </div>
                                                    <div class="curriculum-detail__topic-meta-chips">
                                                        <v-chip
                                                            v-if="shouldShowAssignmentSummaryChip(unit) && unitInheritedWeekKeys(topic, unit).length === 0"
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
                                                                variant="tonal"
                                                                class="curriculum-detail__topic-meta-chip--interactive"
                                                                @click.stop="openUnitAssignmentEditor(topic, unit, 'month')">
                                                                {{ monthChipLabel(monthKey) }}
                                                            </v-chip>
                                                        </template>
                                                        <template v-else-if="unit.assignment_type === 'weeks' && unit.week_keys.length">
                                                            <v-chip
                                                                size="x-small"
                                                                color="primary"
                                                                variant="tonal"
                                                                class="curriculum-detail__topic-meta-chip--interactive"
                                                                @click.stop="openUnitAssignmentEditor(topic, unit, 'weeks')">
                                                                {{ weeksSummaryLabel(unit.week_keys) }}
                                                            </v-chip>
                                                        </template>
                                                        <template v-else-if="unitInheritedWeekKeys(topic, unit).length">
                                                            <v-chip
                                                                size="x-small"
                                                                color="secondary"
                                                                variant="tonal">
                                                                {{ `Über Thema · ${weeksSummaryLabel(unitInheritedWeekKeys(topic, unit))}` }}
                                                            </v-chip>
                                                        </template>
                                                    </div>
                                                    <div v-if="unit.materials.length" class="curriculum-detail__attached-materials curriculum-detail__attached-materials--unit">
                                                        <div
                                                            v-for="material in unit.materials"
                                                            :key="`unit-${unit.id}-material-${material.id}`"
                                                            class="curriculum-detail__attached-material">
                                                            <div class="curriculum-detail__attached-material-copy">
                                                                <div class="curriculum-detail__attached-material-title">{{ material.title }}</div>
                                                                <div class="curriculum-detail__attached-material-subtitle">
                                                                    {{ attachedMaterialSubtitle(material) }}
                                                                </div>
                                                            </div>
                                                            <div class="curriculum-detail__attached-material-actions">
                                                                <v-btn
                                                                    v-if="materialFileAttachmentCount(material) > 0"
                                                                    variant="tonal"
                                                                    color="primary"
                                                                    size="x-small"
                                                                    class="text-none curriculum-detail__attached-material-preview-btn"
                                                                    :disabled="topicSaving || isPageActionLocked"
                                                                    @click.stop="openAttachedMaterialDialog(material)">
                                                                    <v-icon size="14" start>mdi-paperclip</v-icon>
                                                                    {{ materialAttachmentCountLabel(material) }}
                                                                </v-btn>
                                                                <v-btn
                                                                    icon="mdi-close"
                                                                    variant="text"
                                                                    color="error"
                                                                    size="x-small"
                                                                    :disabled="topicSaving || isPageActionLocked"
                                                                    title="Material entfernen"
                                                                    @click.stop="removeUnitMaterial(topic.id, unit.id, material.id)" />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="curriculum-detail__topic-actions" @click.stop>
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
                                                        icon="mdi-book-plus-outline"
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        :disabled="topicSaving || isPageActionLocked"
                                                        title="Materialien hinzufügen"
                                                        @click="openContentMaterialDialog({
                                                            type: 'unit',
                                                            topicId: topic.id,
                                                            unitId: unit.id,
                                                        })" />
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
                                                class="curriculum-detail__topic-assignment-panel"
                                                @click.stop>
                                                <div class="curriculum-detail__topic-assignment-options">
                                                    <v-btn
                                                        :variant="activeTopicAssignmentType === 'none' ? 'flat' : 'tonal'"
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
                                                        :variant="activeTopicAssignmentType === 'month' ? 'flat' : 'tonal'"
                                                        size="x-small"
                                                        rounded="lg"
                                                        class="text-none"
                                                        :color="activeTopicAssignmentType === 'month' ? 'primary' : 'secondary'"
                                                        :disabled="topicSaving || isEditingTopic || isEditingUnit"
                                                        @click="activateUnitAssignmentMode(topic, unit, 'month')">
                                                        Monate
                                                    </v-btn>
                                                    <v-btn
                                                        :variant="activeTopicAssignmentType === 'weeks' ? 'flat' : 'tonal'"
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

                                        <div
                                            v-if="showUnitFormForTopicId === topic.id"
                                            class="curriculum-detail__unit-form mt-3">
                                            <v-btn
                                                v-if="!isEditingUnit"
                                                variant="tonal"
                                                color="secondary"
                                                size="x-small"
                                                rounded="lg"
                                                class="text-none"
                                                :disabled="topicSaving"
                                                @click="cancelUnitForm">
                                                Formular schließen
                                            </v-btn>
                                        </div>

                                        <div v-else-if="!topic.units.length" class="curriculum-detail__unit-empty mt-3">
                                            Noch keine Einheiten angelegt.
                                        </div>
                                        </div>
                                </div>
                            </div>
                        </div>
                        </div>

                        <div v-else class="curriculum-detail__topic-empty mt-4">
                            Noch keine Themen definiert.
                        </div>

                        <div class="curriculum-detail__content-footer">
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
                    </div>
                </div>
            </v-sheet>

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

            <v-dialog v-model="contentMaterialDialogOpen" max-width="980" persistent>
                <v-card rounded="xl" class="curriculum-detail__editor-dialog-card">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                        <v-icon color="primary" size="20">mdi-book-plus-outline</v-icon>
                        Materialien zu {{ contentMaterialDialogLabel }} hinzufügen
                    </v-card-title>
                    <v-card-text class="px-4 pt-2 pb-2">
                        <div class="curriculum-detail__material-dialog">
                            <div class="curriculum-detail__material-dialog-toolbar">
                                <div>
                                    <div class="curriculum-detail__material-dialog-title">{{ contentMaterialDialogTitle }}</div>
                                    <div class="curriculum-detail__material-dialog-subtitle">
                                        Mehrere Materialien können nacheinander hinzugefügt werden.
                                    </div>
                                </div>
                                <div class="curriculum-detail__material-mode-toggle">
                                    <v-btn
                                        :variant="contentMaterialDialogMode === 'search' ? 'flat' : 'tonal'"
                                        color="primary"
                                        size="small"
                                        rounded="lg"
                                        class="text-none"
                                        @click="setContentMaterialDialogMode('search')">
                                        Suche
                                    </v-btn>
                                    <v-btn
                                        :variant="contentMaterialDialogMode === 'workspace' ? 'flat' : 'tonal'"
                                        color="primary"
                                        size="small"
                                        rounded="lg"
                                        class="text-none"
                                        @click="setContentMaterialDialogMode('workspace')">
                                        Arbeitsbereich
                                    </v-btn>
                                </div>
                            </div>

                            <div v-if="contentMaterialDialogMode === 'search'">
                                <v-text-field
                                    v-model="contentMaterialSearch"
                                    label="Material suchen"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    clearable
                                    prepend-inner-icon="mdi-magnify"
                                    class="mb-3"
                                    @update:modelValue="searchContentMaterials" />
                                <div class="curriculum-detail__material-selection-layout">
                                    <div class="curriculum-detail__material-browser-results">
                                        <div class="curriculum-detail__material-browser-heading">Trefferliste</div>
                                        <div v-if="contentMaterialsLoading" class="text-center py-4">
                                            <v-progress-circular indeterminate color="primary" size="24" />
                                        </div>
                                        <v-list
                                            v-else-if="contentMaterialResults.length"
                                            bg-color="transparent"
                                            density="compact"
                                            class="py-0 curriculum-detail__material-results">
                                            <v-list-item
                                                v-for="card in contentMaterialResults"
                                                :key="`search-material-${card.id}`"
                                                class="curriculum-detail__material-result-item mb-1 px-3"
                                                :class="{ 'curriculum-detail__material-result-item--active': contentMaterialPreviewCard?.id === card.id }"
                                                rounded="lg"
                                                @click="selectContentMaterialPreview(card)">
                                                <template #prepend>
                                                    <v-icon size="18" color="#a5b4fc" class="mr-2">mdi-package-variant-closed</v-icon>
                                                </template>
                                                <v-list-item-title class="text-body-2">{{ card.title }}</v-list-item-title>
                                                <v-list-item-subtitle class="text-caption">{{ attachedMaterialSubtitle(card) }}</v-list-item-subtitle>
                                                <template #append>
                                                    <v-chip
                                                        v-if="isContentMaterialAttached(card)"
                                                        size="x-small"
                                                        color="success"
                                                        variant="tonal">
                                                        Hinzugefügt
                                                    </v-chip>
                                                    <v-btn
                                                        v-else
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        class="text-none"
                                                        :disabled="topicSaving"
                                                        @click.stop="attachContentMaterial(card)">
                                                        Hinzufügen
                                                    </v-btn>
                                                </template>
                                            </v-list-item>
                                        </v-list>
                                        <div v-else class="text-center py-4 text-caption curriculum-detail__material-dialog-empty">
                                            Keine Materialien gefunden.
                                        </div>
                                    </div>

                                    <div class="curriculum-detail__material-preview-panel">
                                        <div class="curriculum-detail__material-browser-heading">Anhänge</div>
                                        <div v-if="contentMaterialPreviewCard" class="curriculum-detail__material-preview-copy">
                                            <div class="curriculum-detail__material-preview-title">{{ contentMaterialPreviewCard.title }}</div>
                                            <div class="curriculum-detail__material-preview-subtitle">
                                                {{ attachedMaterialSubtitle(contentMaterialPreviewCard) }}
                                            </div>
                                        </div>
                                        <div v-if="contentMaterialPreviewAttachments.length" class="curriculum-detail__material-preview-list">
                                            <div
                                                v-for="attachment in contentMaterialPreviewAttachments"
                                                :key="`preview-attachment-${attachment.id}`"
                                                class="curriculum-detail__material-preview-list-item"
                                                @click="openContentMaterialPreview(attachment)">
                                                <div class="curriculum-detail__material-preview-list-copy">
                                                    <div class="curriculum-detail__material-preview-list-title">{{ attachment.name }}</div>
                                                    <div class="curriculum-detail__material-preview-list-subtitle">
                                                        {{ attachment.mime_type || 'Datei' }}
                                                    </div>
                                                </div>
                                                <div class="curriculum-detail__material-preview-list-actions">
                                                    <v-btn
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        class="text-none"
                                                        @click.stop="openContentMaterialPreview(attachment)">
                                                        Vorschau
                                                    </v-btn>
                                                    <v-btn
                                                        v-if="attachment.download_url"
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        class="text-none"
                                                        @click.stop="downloadContentMaterialAttachment(attachment)">
                                                        Herunterladen
                                                    </v-btn>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="curriculum-detail__material-preview-empty">
                                            <v-icon size="36" color="#94a3b8" class="mb-2">mdi-paperclip</v-icon>
                                            <div class="text-caption">Material auswählen, um die verbundenen Dateien zu sehen.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="curriculum-detail__material-browser">
                                <div class="curriculum-detail__material-browser-panel">
                                    <div class="curriculum-detail__material-browser-heading">Arbeitsbereich filtern</div>
                                    <div class="curriculum-detail__material-dialog-subtitle mb-3">
                                        Zuerst ein Fach wählen, danach bei Bedarf Thema und Einheit eingrenzen.
                                    </div>

                                    <div class="curriculum-detail__material-filter-grid">
                                        <v-autocomplete
                                            :model-value="selectedContentMaterialSubject"
                                            :items="contentMaterialClassificationTree"
                                            item-title="name"
                                            item-value="id"
                                            label="Fach"
                                            variant="outlined"
                                            density="comfortable"
                                            hide-details
                                            return-object
                                            clearable
                                            :disabled="contentMaterialClassificationLoading"
                                            @update:modelValue="selectContentMaterialSubject" />

                                        <v-autocomplete
                                            :model-value="selectedContentMaterialTopic"
                                            :items="contentMaterialTopicSelectOptions"
                                            item-title="name"
                                            item-value="id"
                                            label="Thema"
                                            variant="outlined"
                                            density="comfortable"
                                            hide-details
                                            return-object
                                            clearable
                                            :disabled="!selectedContentMaterialSubject"
                                            @update:modelValue="selectContentMaterialTopic" />

                                        <v-autocomplete
                                            :model-value="selectedContentMaterialUnit"
                                            :items="contentMaterialUnitSelectOptions"
                                            item-title="name"
                                            item-value="id"
                                            label="Einheit"
                                            variant="outlined"
                                            density="comfortable"
                                            hide-details
                                            return-object
                                            clearable
                                            :disabled="!selectedContentMaterialTopic"
                                            @update:modelValue="selectContentMaterialUnit" />
                                    </div>

                                    <div class="curriculum-detail__material-browser-summary">
                                        <div class="curriculum-detail__material-browser-summary-card">
                                            <div class="curriculum-detail__material-browser-summary-label">Auswahl</div>
                                            <div class="curriculum-detail__material-browser-summary-value">
                                                {{ contentMaterialWorkspaceSelectionLabel }}
                                            </div>
                                        </div>
                                        <div class="curriculum-detail__material-browser-summary-card">
                                            <div class="curriculum-detail__material-browser-summary-label">Treffer</div>
                                            <div class="curriculum-detail__material-browser-summary-value">
                                                {{ contentMaterialWorkspaceResultSummary }}
                                            </div>
                                        </div>
                                        <v-btn
                                            variant="text"
                                            color="secondary"
                                            size="small"
                                            class="text-none curriculum-detail__material-browser-reset"
                                            :disabled="!selectedContentMaterialSubject && !selectedContentMaterialTopic && !selectedContentMaterialUnit"
                                            @click="resetContentMaterialWorkspaceSelection">
                                            Auswahl zurücksetzen
                                        </v-btn>
                                    </div>
                                </div>

                                <div class="curriculum-detail__material-selection-layout">
                                    <div class="curriculum-detail__material-browser-results">
                                        <div class="curriculum-detail__material-browser-heading">Trefferliste</div>
                                        <div v-if="contentMaterialWorkspaceLoading" class="text-center py-4">
                                            <v-progress-circular indeterminate color="primary" size="24" />
                                        </div>
                                        <div v-else-if="contentMaterialWorkspaceError" class="text-caption py-4 curriculum-detail__material-dialog-error">
                                            {{ contentMaterialWorkspaceError }}
                                        </div>
                                        <div
                                            v-else-if="!selectedContentMaterialSubject"
                                            class="text-center py-4 text-caption curriculum-detail__material-dialog-empty">
                                            Bitte zuerst ein Fach auswählen.
                                        </div>
                                        <v-list
                                            v-else-if="contentMaterialWorkspaceResults.length"
                                            bg-color="transparent"
                                            density="compact"
                                            class="py-0 curriculum-detail__material-results">
                                            <v-list-item
                                                v-for="card in contentMaterialWorkspaceResults"
                                                :key="`workspace-material-${card.id}`"
                                                class="curriculum-detail__material-result-item mb-1 px-3"
                                                :class="{ 'curriculum-detail__material-result-item--active': contentMaterialPreviewCard?.id === card.id }"
                                                rounded="lg"
                                                @click="selectContentMaterialPreview(card)">
                                                <template #prepend>
                                                    <v-icon size="18" color="#a5b4fc" class="mr-2">mdi-package-variant-closed</v-icon>
                                                </template>
                                                <v-list-item-title class="text-body-2">{{ card.title }}</v-list-item-title>
                                                <v-list-item-subtitle class="text-caption">{{ attachedMaterialSubtitle(card) }}</v-list-item-subtitle>
                                                <template #append>
                                                    <v-chip
                                                        v-if="isContentMaterialAttached(card)"
                                                        size="x-small"
                                                        color="success"
                                                        variant="tonal">
                                                        Hinzugefügt
                                                    </v-chip>
                                                    <v-btn
                                                        v-else
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        class="text-none"
                                                        :disabled="topicSaving"
                                                        @click.stop="attachContentMaterial(card)">
                                                        Hinzufügen
                                                    </v-btn>
                                                </template>
                                            </v-list-item>
                                        </v-list>
                                        <div v-else class="text-center py-4 text-caption curriculum-detail__material-dialog-empty">
                                            Keine Materialien in dieser Auswahl gefunden.
                                        </div>
                                    </div>

                                    <div class="curriculum-detail__material-preview-panel">
                                        <div class="curriculum-detail__material-browser-heading">Anhänge</div>
                                        <div v-if="contentMaterialPreviewCard" class="curriculum-detail__material-preview-copy">
                                            <div class="curriculum-detail__material-preview-title">{{ contentMaterialPreviewCard.title }}</div>
                                            <div class="curriculum-detail__material-preview-subtitle">
                                                {{ attachedMaterialSubtitle(contentMaterialPreviewCard) }}
                                            </div>
                                        </div>
                                        <div v-if="contentMaterialPreviewAttachments.length" class="curriculum-detail__material-preview-list">
                                            <div
                                                v-for="attachment in contentMaterialPreviewAttachments"
                                                :key="`workspace-preview-attachment-${attachment.id}`"
                                                class="curriculum-detail__material-preview-list-item"
                                                @click="openContentMaterialPreview(attachment)">
                                                <div class="curriculum-detail__material-preview-list-copy">
                                                    <div class="curriculum-detail__material-preview-list-title">{{ attachment.name }}</div>
                                                    <div class="curriculum-detail__material-preview-list-subtitle">
                                                        {{ attachment.mime_type || 'Datei' }}
                                                    </div>
                                                </div>
                                                <div class="curriculum-detail__material-preview-list-actions">
                                                    <v-btn
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        class="text-none"
                                                        @click.stop="openContentMaterialPreview(attachment)">
                                                        Vorschau
                                                    </v-btn>
                                                    <v-btn
                                                        v-if="attachment.download_url"
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        class="text-none"
                                                        @click.stop="downloadContentMaterialAttachment(attachment)">
                                                        Herunterladen
                                                    </v-btn>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="curriculum-detail__material-preview-empty">
                                            <v-icon size="36" color="#94a3b8" class="mb-2">mdi-paperclip</v-icon>
                                            <div class="text-caption">Material auswählen, um die verbundenen Dateien zu sehen.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </v-card-text>
                    <v-card-actions class="px-4 pb-4">
                        <v-spacer />
                        <v-btn
                            variant="text"
                            color="secondary"
                            :disabled="topicSaving"
                            @click="closeContentMaterialDialog">
                            Schließen
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <v-dialog
                v-model="contentMaterialPreviewDialogOpen"
                :fullscreen="contentMaterialPreviewFullscreen"
                :max-width="contentMaterialPreviewFullscreen ? undefined : 1180"
                persistent>
                <v-card
                    :class="contentMaterialPreviewFullscreen
                        ? 'curriculum-detail__fullscreen-preview'
                        : 'curriculum-detail__modal-preview'">
                    <v-card-title class="curriculum-detail__fullscreen-preview-header">
                        <div>
                            <div class="curriculum-detail__fullscreen-preview-title">
                                {{ contentMaterialPreviewAttachment?.name || 'Dateivorschau' }}
                            </div>
                            <div class="curriculum-detail__fullscreen-preview-subtitle">
                                {{ contentMaterialPreviewCard?.title || 'Material' }}
                            </div>
                        </div>
                        <div class="curriculum-detail__fullscreen-preview-actions">
                            <v-btn
                                v-if="contentMaterialPreviewDownloadUrl"
                                prepend-icon="mdi-download"
                                variant="flat"
                                color="primary"
                                rounded="lg"
                                class="text-none"
                                @click="downloadContentMaterialAttachment(contentMaterialPreviewAttachment)">
                                Herunterladen
                            </v-btn>
                            <v-btn
                                prepend-icon="mdi-close"
                                variant="flat"
                                color="error"
                                rounded="lg"
                                class="text-none curriculum-detail__fullscreen-preview-close-btn"
                                @click="closeContentMaterialPreview">
                                Schließen
                            </v-btn>
                        </div>
                    </v-card-title>
                    <v-card-text class="curriculum-detail__fullscreen-preview-body">
                        <iframe
                            v-if="contentMaterialPreviewAttachment && contentMaterialPreviewUsesIframe"
                            :src="contentMaterialPreviewUrl"
                            :class="contentMaterialPreviewFullscreen
                                ? 'curriculum-detail__fullscreen-preview-iframe'
                                : 'curriculum-detail__modal-preview-iframe'" />
                        <img
                            v-else-if="contentMaterialPreviewAttachment && contentMaterialPreviewIsImage"
                            :src="contentMaterialPreviewUrl"
                            :class="contentMaterialPreviewFullscreen
                                ? 'curriculum-detail__fullscreen-preview-image'
                                : 'curriculum-detail__modal-preview-image'" />
                        <div v-else class="curriculum-detail__fullscreen-preview-empty">
                            <v-icon size="42" color="#64748b" class="mb-3">mdi-file-document-outline</v-icon>
                            <div class="text-body-2 mb-3">Für diesen Dateityp ist keine direkte Vorschau verfügbar.</div>
                            <v-btn
                                v-if="contentMaterialPreviewDownloadUrl"
                                variant="flat"
                                color="primary"
                                class="text-none"
                                @click="downloadContentMaterialAttachment(contentMaterialPreviewAttachment)">
                                Herunterladen
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>
            </v-dialog>

            <v-dialog v-model="attachedMaterialDialogOpen" max-width="720" persistent>
                <v-card rounded="xl">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                        <v-icon color="primary" size="22">mdi-paperclip</v-icon>
                        Anhänge des Materials
                    </v-card-title>
                    <v-card-text class="px-4 pb-2">
                        <div class="curriculum-detail__material-preview-copy">
                            <div class="curriculum-detail__material-preview-title">
                                {{ contentMaterialPreviewCard?.title || 'Material' }}
                            </div>
                            <div class="curriculum-detail__material-preview-subtitle">
                                {{ attachedMaterialSubtitle(contentMaterialPreviewCard) }}
                            </div>
                        </div>
                        <div
                            v-if="!attachedMaterialDialogLoading && contentMaterialPreviewAttachments.length"
                            class="text-caption mb-3"
                            style="color: #475569">
                            Für jeden Anhang stehen Vorschau und Download zur Verfügung.
                        </div>
                        <div v-if="attachedMaterialDialogLoading" class="text-center py-6">
                            <v-progress-circular indeterminate color="primary" size="24" />
                        </div>
                        <div v-else-if="attachedMaterialDialogError" class="text-caption py-4" style="color: #b91c1c">
                            {{ attachedMaterialDialogError }}
                        </div>
                        <v-list
                            v-else-if="contentMaterialPreviewAttachments.length"
                            bg-color="transparent"
                            density="compact"
                            class="py-0"
                            style="max-height: 360px; overflow-y: auto">
                            <v-list-item
                                v-for="attachment in contentMaterialPreviewAttachments"
                                :key="`attached-material-attachment-${attachment.id}`"
                                class="lehrplaene__material-item lehrplaene__attachment-item mb-1 px-3"
                                rounded="lg"
                                @click="openContentMaterialPreview(attachment, { fullscreen: false })">
                                <template #prepend>
                                    <v-icon size="18" color="#a5b4fc" class="mr-2">
                                        {{ contentMaterialPreviewAttachmentIcon(attachment) }}
                                    </v-icon>
                                </template>
                                <v-list-item-title class="text-body-2">{{ attachment.name }}</v-list-item-title>
                                <v-list-item-subtitle class="text-caption">
                                    {{ attachment.mime_type || 'Datei' }}<span v-if="attachment.size_bytes"> · {{ formatBytes(attachment.size_bytes) }}</span>
                                </v-list-item-subtitle>
                                <template #append>
                                    <div class="curriculum-detail__material-preview-list-actions">
                                        <v-btn
                                            variant="text"
                                            color="primary"
                                            size="x-small"
                                            class="text-none"
                                            @click.stop="openContentMaterialPreview(attachment, { fullscreen: false })">
                                            Vorschau
                                        </v-btn>
                                        <v-btn
                                            v-if="attachment.download_url"
                                            variant="text"
                                            color="primary"
                                            size="x-small"
                                            class="text-none"
                                            @click.stop="downloadContentMaterialAttachment(attachment)">
                                            Herunterladen
                                        </v-btn>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                        <div v-else class="text-center py-6 text-caption" style="color: #64748b">
                            Dieses Material hat keine Anhänge.
                        </div>
                    </v-card-text>
                    <v-card-actions class="px-4 pb-4">
                        <v-btn variant="tonal" @click="closeAttachedMaterialDialog">Schließen</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <v-sheet v-if="showLehrplaeneCard" rounded="xl" class="curriculum-detail__side-card curriculum-detail__side-card--documents curriculum-detail__side-card--scrollable">
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
                                    v-if="doc.source_type === 'material'"
                                    icon="mdi-paperclip"
                                    variant="text"
                                    size="x-small"
                                    color="primary"
                                    class="ml-1 flex-shrink-0"
                                    :disabled="isPageActionLocked"
                                    title="Anhang auswählen"
                                    @click.stop="openMaterialAttachmentDialog(doc)" />
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
                                    v-if="previewUsesIframe && previewUrl"
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
                                    <v-list-item-subtitle class="text-caption">{{ materialPickerSubtitle(card) }}</v-list-item-subtitle>
                                    <template #append>
                                        <v-chip
                                            v-if="materialFileAttachmentCount(card) > 0"
                                            size="x-small"
                                            color="primary"
                                            variant="tonal">
                                            {{ materialAttachmentCountLabel(card) }}
                                        </v-chip>
                                    </template>
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
                            <div
                                v-if="materialAttachmentOptions.length > 1"
                                class="text-caption mb-3"
                                style="color: #475569">
                                Dieses Material hat mehrere Anhänge. Bitte den Anhang auswählen, der angezeigt werden soll.
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

                <v-dialog v-model="documentDeleteDialogOpen" max-width="420" persistent>
                    <v-card rounded="xl">
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                            <v-icon color="error" size="20">mdi-delete-outline</v-icon>
                            Lehrplan entfernen
                        </v-card-title>
                        <v-card-text class="px-4 pb-2">
                            <div class="text-body-2" style="color: #475569">
                                Soll
                                <strong>{{ documentToDelete?.selected_attachment_name || documentToDelete?.name }}</strong>
                                wirklich aus den Lehrplänen entfernt werden?
                            </div>
                        </v-card-text>
                        <v-card-actions class="px-4 pb-4">
                            <v-spacer />
                            <v-btn
                                variant="text"
                                color="secondary"
                                :disabled="documentDeleteLoading"
                                @click="closeDocumentDeleteDialog">
                                Abbrechen
                            </v-btn>
                            <v-btn
                                color="error"
                                variant="flat"
                                :loading="documentDeleteLoading"
                                @click="confirmDocumentDelete">
                                Entfernen
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>
            </v-sheet>
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

    beforeUnmount() {
        if (this._materialSearchTimer) {
            clearTimeout(this._materialSearchTimer)
        }

        if (this._contentMaterialSearchTimer) {
            clearTimeout(this._contentMaterialSearchTimer)
        }
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
            weekDisplayMode: 'days',
            showLehrplaeneCard: false,
            isApplyingFreeWeeksTemplate: false,
            collapseFullMonths: true,
            topicCollapseStates: {},
            manualMonthCollapseStates: {},
            selectedTopicId: null,
            selectedUnitTopicId: null,
            selectedUnitId: null,
            documents: [],
            docsLoading: false,
            contentMaterialDialogOpen: false,
            contentMaterialDialogMode: 'search',
            contentMaterialTarget: null,
            contentMaterialSearch: '',
            contentMaterialResults: [],
            contentMaterialsLoading: false,
            _contentMaterialSearchTimer: null,
            contentMaterialClassificationTree: [],
            contentMaterialClassificationLoading: false,
            selectedContentMaterialSubject: null,
            selectedContentMaterialTopic: null,
            selectedContentMaterialUnit: null,
            contentMaterialWorkspaceResults: [],
            contentMaterialWorkspaceLoading: false,
            contentMaterialWorkspaceError: null,
            contentMaterialPreviewCard: null,
            contentMaterialPreviewAttachmentId: null,
            contentMaterialPreviewDialogOpen: false,
            contentMaterialPreviewFullscreen: true,
            attachedMaterialDialogOpen: false,
            attachedMaterialDialogLoading: false,
            attachedMaterialDialogError: null,
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
            documentDeleteDialogOpen: false,
            documentDeleteLoading: false,
            documentToDelete: null,
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

    watch: {
        'curriculum.id'() {
            this.topicCollapseStates = {}
            this.manualMonthCollapseStates = {}
        },
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

        previewUsesIframe() {
            return Boolean(this.previewUrl) && !this.previewIsImage
        },

        freeWeekKeys() {
            return [...new Set(
                (Array.isArray(this.curriculum.free_weeks) ? this.curriculum.free_weeks : [])
                    .filter(Boolean)
                    .map((weekKey) => this.normalizeWeekAssignmentKey(weekKey))
                    .filter(Boolean)
            )].sort()
        },

        freeWeeksCount() {
            return this.freeWeekKeys.length
        },

        freeWeeksTemplateWeekKeys() {
            const template = this.config?.user?.teaching_curriculum_free_weeks_template
            const rawWeekKeys = Array.isArray(template?.week_keys) ? template.week_keys : []

            return [...new Set(
                rawWeekKeys
                    .filter(Boolean)
                    .map((weekKey) => this.normalizeWeekAssignmentKey(weekKey))
                    .filter(Boolean)
            )].sort()
        },

        hasFreeWeeksTemplate() {
            return this.freeWeeksTemplateWeekKeys.length > 0
        },

        canApplyFreeWeeksTemplate() {
            return this.hasFreeWeeksTemplate
                && this.freeWeeksCount === 0
                && !this.isApplyingFreeWeeksTemplate
                && !this.isPageActionLocked
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

        showWeekdays() {
            return this.weekDisplayMode === 'days'
        },

        isCompactWeekView() {
            return !this.showWeekdays
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

        selectedTopic() {
            if (!this.selectedTopicId) {
                return null
            }

            return this.curriculumTopics.find((topic) => topic.id === this.selectedTopicId) ?? null
        },

        selectedAssignmentItems() {
            if (this.selectedTopic && this.selectedUnitTopicId === this.selectedTopic.id && this.selectedUnitId) {
                const selectedUnit = this.selectedTopic.units.find((unit) => unit.id === this.selectedUnitId) ?? null

                return selectedUnit ? [selectedUnit] : []
            }

            if (this.selectedTopic && this.isTopicSelected(this.selectedTopic.id)) {
                return [this.selectedTopic, ...this.selectedTopic.units]
            }

            return []
        },

        highlightedAssignmentItems() {
            return this.activeAssignmentItem ? [this.activeAssignmentItem] : this.selectedAssignmentItems
        },

        highlightedTopic() {
            return this.activeTopicAssignmentTopic ?? this.selectedTopic
        },

        highlightedTopicAccentColor() {
            if (!this.highlightedTopic) {
                return null
            }

            const topicIndex = this.curriculumTopics.findIndex((topic) => topic.id === this.highlightedTopic.id)

            if (topicIndex === -1) {
                return null
            }

            return `hsl(${this.topicHue(topicIndex)}, 70%, 48%)`
        },

        calendarHighlightStyle() {
            return this.highlightedTopicAccentColor
                ? { '--calendar-highlight-accent': this.highlightedTopicAccentColor }
                : {}
        },

        activeAssignmentItemLabel() {
            return this.activeTopicAssignmentUnit ? 'Einheit' : 'Thema'
        },

        contentMaterialDialogTarget() {
            if (!this.contentMaterialTarget?.topicId) {
                return null
            }

            if (this.contentMaterialTarget.type === 'unit' && this.contentMaterialTarget.unitId) {
                return this.findUnit(this.contentMaterialTarget.topicId, this.contentMaterialTarget.unitId)
            }

            return this.findTopic(this.contentMaterialTarget.topicId)
        },

        contentMaterialDialogLabel() {
            return this.contentMaterialTarget?.type === 'unit' ? 'der Einheit' : 'dem Thema'
        },

        contentMaterialDialogTitle() {
            return this.contentMaterialDialogTarget?.title || 'Inhalt'
        },

        contentMaterialTopicOptions() {
            return Array.isArray(this.selectedContentMaterialSubject?.topics)
                ? this.selectedContentMaterialSubject.topics
                : []
        },

        contentMaterialTopicSelectOptions() {
            if (!this.selectedContentMaterialSubject) {
                return []
            }

            return [
                { id: null, name: 'Alle Themen' },
                ...this.contentMaterialTopicOptions,
            ]
        },

        contentMaterialUnitOptions() {
            return Array.isArray(this.selectedContentMaterialTopic?.units)
                ? this.selectedContentMaterialTopic.units
                : []
        },

        contentMaterialUnitSelectOptions() {
            if (!this.selectedContentMaterialTopic) {
                return []
            }

            return [
                { id: null, name: 'Alle Einheiten' },
                ...this.contentMaterialUnitOptions,
            ]
        },

        contentMaterialWorkspaceSelectionLabel() {
            if (this.selectedContentMaterialUnit?.name) {
                return `${this.selectedContentMaterialSubject?.name || 'Fach'} · ${this.selectedContentMaterialTopic?.name || 'Thema'} · ${this.selectedContentMaterialUnit.name}`
            }

            if (this.selectedContentMaterialTopic?.name) {
                return `${this.selectedContentMaterialSubject?.name || 'Fach'} · ${this.selectedContentMaterialTopic.name}`
            }

            if (this.selectedContentMaterialSubject?.name) {
                return this.selectedContentMaterialSubject.name
            }

            if (this.contentMaterialClassificationLoading) {
                return 'Arbeitsbereich wird geladen...'
            }

            return 'Bitte zuerst ein Fach auswählen.'
        },

        contentMaterialWorkspaceResultSummary() {
            if (!this.selectedContentMaterialSubject) {
                return 'Noch keine Auswahl'
            }

            if (this.contentMaterialWorkspaceLoading) {
                return 'Wird geladen...'
            }

            return `${this.contentMaterialWorkspaceResults.length} Material${this.contentMaterialWorkspaceResults.length === 1 ? '' : 'ien'}`
        },

        contentMaterialPreviewAttachments() {
            return Array.isArray(this.contentMaterialPreviewCard?.attachments)
                ? this.contentMaterialPreviewCard.attachments
                : []
        },

        contentMaterialPreviewAttachment() {
            if (!this.contentMaterialPreviewAttachments.length || !this.contentMaterialPreviewAttachmentId) {
                return null
            }

            return this.contentMaterialPreviewAttachments.find((attachment) => attachment.id === this.contentMaterialPreviewAttachmentId)
                || null
        },

        contentMaterialPreviewUrl() {
            return this.contentMaterialPreviewAttachment?.preview_url || this.contentMaterialPreviewAttachment?.download_url || null
        },

        contentMaterialPreviewDownloadUrl() {
            return this.contentMaterialPreviewAttachment?.download_url || null
        },

        contentMaterialPreviewIsImage() {
            const mime = String(this.contentMaterialPreviewAttachment?.mime_type || '').toLowerCase()
            return mime.startsWith('image/')
        },

        contentMaterialPreviewUsesIframe() {
            return Boolean(this.contentMaterialPreviewUrl) && !this.contentMaterialPreviewIsImage
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
                    .map((monthKey) => this.normalizeMonthAssignmentKey(monthKey))
                    .filter(Boolean)
            )].sort()
            const weekKeys = [...new Set(
                (Array.isArray(normalizedEntry.week_keys) ? normalizedEntry.week_keys : [])
                    .filter(Boolean)
                    .map((weekKey) => this.normalizeWeekAssignmentKey(weekKey))
                    .filter(Boolean)
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

        normalizeAttachedMaterial(material = null) {
            const normalizedMaterial = material && typeof material === 'object' ? material : {}
            const id = Number(normalizedMaterial.id ?? 0)
            const title = typeof normalizedMaterial.title === 'string' ? normalizedMaterial.title.trim() : ''
            const subject = typeof normalizedMaterial.subject === 'string' ? normalizedMaterial.subject.trim() : ''
            const topic = typeof normalizedMaterial.topic === 'string'
                ? normalizedMaterial.topic.trim()
                : (typeof normalizedMaterial.area === 'string' ? normalizedMaterial.area.trim() : '')
            const unit = typeof normalizedMaterial.unit === 'string' ? normalizedMaterial.unit.trim() : ''
            const type = typeof normalizedMaterial.type === 'string' ? normalizedMaterial.type.trim() : ''
            const status = typeof normalizedMaterial.status === 'string' ? normalizedMaterial.status.trim() : ''
            const attachmentsCount = Number(normalizedMaterial.attachments_count ?? 0)

            return {
                id: Number.isFinite(id) && id > 0 ? id : null,
                title,
                subject,
                topic,
                unit,
                type,
                status,
                attachments_count: Number.isFinite(attachmentsCount) && attachmentsCount > 0 ? attachmentsCount : 0,
                attachments: (Array.isArray(normalizedMaterial.attachments) ? normalizedMaterial.attachments : [])
                    .map((attachment) => this.normalizeMaterialPreviewAttachment(attachment))
                    .filter((attachment) => attachment.id),
            }
        },

        normalizeMaterialPreviewAttachment(attachment = null) {
            const normalizedAttachment = attachment && typeof attachment === 'object' ? attachment : {}
            const id = Number(normalizedAttachment.id ?? 0)
            const mimeType = typeof normalizedAttachment.mime_type === 'string' ? normalizedAttachment.mime_type.trim() : ''
            const sizeBytes = Number(normalizedAttachment.size_bytes ?? 0)

            return {
                id: Number.isFinite(id) && id > 0 ? id : null,
                name: typeof normalizedAttachment.name === 'string' ? normalizedAttachment.name.trim() : 'Anhang',
                mime_type: mimeType,
                size_bytes: Number.isFinite(sizeBytes) && sizeBytes > 0 ? sizeBytes : null,
                preview_url: Number.isFinite(id) && id > 0
                    ? `/api/admin/teaching/curricula/${this.curriculum.id}/materials/attachments/${id}/preview`
                    : '',
                download_url: Number.isFinite(id) && id > 0
                    ? `/api/admin/teaching/curricula/${this.curriculum.id}/materials/attachments/${id}/download`
                    : '',
            }
        },

        normalizeAttachedMaterials(materials = []) {
            const seen = new Set()

            return (Array.isArray(materials) ? materials : [])
                .map((material) => this.normalizeAttachedMaterial(material))
                .filter((material) => {
                    if (!material.id || material.title === '' || seen.has(material.id)) {
                        return false
                    }

                    seen.add(material.id)

                    return true
                })
                .map(({ attachments, ...material }) => material)
        },

        normalizeTopic(topic = null, index = 0) {
            const normalizedTopic = topic && typeof topic === 'object' ? topic : {}
            return {
                ...this.normalizeAssignmentEntry(normalizedTopic, index, 'topic'),
                materials: this.normalizeAttachedMaterials(normalizedTopic.materials),
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
            const checkedWeekKeys = [...new Set(
                (Array.isArray(normalizedUnit.checked_week_keys) ? normalizedUnit.checked_week_keys : [])
                    .filter(Boolean)
                    .map((weekKey) => this.normalizeWeekAssignmentKey(weekKey))
                    .filter(Boolean)
            )].sort()

            return {
                ...this.normalizeAssignmentEntry(normalizedUnit, index, 'unit'),
                is_exam: Boolean(normalizedUnit.is_exam),
                checked_week_keys: checkedWeekKeys,
                materials: this.normalizeAttachedMaterials(normalizedUnit.materials),
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

        buildTopicUnitDistribution(topic) {
            if (!topic || !Array.isArray(topic.units) || topic.units.length === 0) {
                return {
                    units: [],
                    assignedCount: 0,
                }
            }

            const visibleWeekKeys = this.visibleWeekKeys()
            const weekIndexMap = new Map(visibleWeekKeys.map((weekKey, index) => [weekKey, index]))
            const unavailableWeekKeys = new Set([
                ...this.freeWeekKeys,
                ...visibleWeekKeys.filter((weekKey) => this.topicsForWeek(weekKey).length > 0),
            ])
            let nextWeekIndex = -1
            let assignedCount = 0

            const units = topic.units.map((unit) => {
                const nextUnit = this.buildUnitPayload(unit)

                if (nextUnit.assignment_type === 'weeks') {
                    const assignedIndexes = nextUnit.week_keys
                        .map((weekKey) => weekIndexMap.get(weekKey))
                        .filter((weekIndex) => Number.isInteger(weekIndex))

                    if (assignedIndexes.length > 0) {
                        nextWeekIndex = Math.max(nextWeekIndex, ...assignedIndexes)
                    }

                    return nextUnit
                }

                if (nextUnit.assignment_type !== 'none') {
                    return nextUnit
                }

                let candidateIndex = nextWeekIndex + 1

                while (
                    candidateIndex < visibleWeekKeys.length
                    && unavailableWeekKeys.has(visibleWeekKeys[candidateIndex])
                ) {
                    candidateIndex += 1
                }

                if (candidateIndex >= visibleWeekKeys.length) {
                    return nextUnit
                }

                const assignedWeekKey = visibleWeekKeys[candidateIndex]

                unavailableWeekKeys.add(assignedWeekKey)
                nextWeekIndex = candidateIndex
                assignedCount += 1

                return this.applyWeekAssignment(nextUnit, [assignedWeekKey])
            })

            return {
                units,
                assignedCount,
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
            const visibleWeekKeys = this.visibleWeekKeys()
            const currentIndex = visibleWeekKeys.indexOf(weekKey)

            if (currentIndex === -1) {
                return null
            }

            const shiftedIndex = currentIndex + delta
            if (shiftedIndex < 0 || shiftedIndex >= visibleWeekKeys.length) {
                return null
            }

            return visibleWeekKeys[shiftedIndex] ?? null
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

            const visibleWeekKeys = this.visibleWeekKeys()
            const sourceIndex = visibleWeekKeys.indexOf(sourceWeekKey)
            const targetIndex = visibleWeekKeys.indexOf(weekKey)

            if (sourceIndex === -1 || targetIndex === -1) {
                return {
                    effectiveDelta: null,
                    errorMessage: 'Die Wochensequenz kann nicht außerhalb des sichtbaren Zeitraums verschoben werden.',
                }
            }

            return {
                effectiveDelta: targetIndex - sourceIndex,
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

        attachedMaterialSubtitle(material) {
            const parts = [
                typeof material?.subject === 'string' ? material.subject.trim() : '',
                typeof material?.topic === 'string' ? material.topic.trim() : '',
                typeof material?.unit === 'string' ? material.unit.trim() : '',
                typeof material?.type === 'string' ? material.type.trim() : '',
            ].filter(Boolean)

            if (parts.length) {
                return parts.join(' · ')
            }

            return typeof material?.status === 'string' && material.status.trim() !== ''
                ? material.status.trim()
                : 'Material'
        },

        setContentMaterialDialogMode(mode) {
            this.contentMaterialDialogMode = mode

            if (mode === 'search') {
                this.searchContentMaterials(this.contentMaterialSearch)
                return
            }

            this.ensureContentMaterialClassificationTree()
        },

        async openContentMaterialDialog(target) {
            if (this.topicSaving || !target?.topicId) return

            this.contentMaterialTarget = {
                type: target.type === 'unit' ? 'unit' : 'topic',
                topicId: target.topicId,
                unitId: target.type === 'unit' ? target.unitId : null,
            }
            this.contentMaterialDialogOpen = true
            this.contentMaterialDialogMode = 'search'
            this.contentMaterialSearch = ''
            this.contentMaterialResults = []
            this.contentMaterialPreviewCard = null
            this.contentMaterialPreviewAttachmentId = null
            this.contentMaterialPreviewDialogOpen = false
            this.contentMaterialPreviewFullscreen = true

            await this.doSearchContentMaterials('')
        },

        closeContentMaterialDialog() {
            if (this.topicSaving) return

            this.contentMaterialDialogOpen = false
            this.contentMaterialDialogMode = 'search'
            this.contentMaterialTarget = null
            this.contentMaterialSearch = ''
            this.contentMaterialResults = []
            this.selectedContentMaterialSubject = null
            this.selectedContentMaterialTopic = null
            this.selectedContentMaterialUnit = null
            this.contentMaterialWorkspaceResults = []
            this.contentMaterialWorkspaceError = null
            this.contentMaterialPreviewCard = null
            this.contentMaterialPreviewAttachmentId = null
            this.contentMaterialPreviewDialogOpen = false
            this.contentMaterialPreviewFullscreen = true
        },

        searchContentMaterials(value) {
            if (this._contentMaterialSearchTimer) {
                clearTimeout(this._contentMaterialSearchTimer)
            }

            this._contentMaterialSearchTimer = setTimeout(() => {
                this.doSearchContentMaterials(value || '')
            }, 300)
        },

        async doSearchContentMaterials(search) {
            this.contentMaterialsLoading = true

            try {
                const res = await axios.get(`/api/admin/teaching/curricula/${this.curriculum.id}/materials/cards`, {
                    params: { search, per_page: 20 },
                })
                this.contentMaterialResults = Array.isArray(res.data?.data)
                    ? res.data.data.map((card) => this.normalizeAttachedMaterial(card)).filter((card) => card.id)
                    : []
                this.syncContentMaterialPreviewSelection(this.contentMaterialResults)
            } catch {
                this.contentMaterialResults = []
                this.syncContentMaterialPreviewSelection([])
            } finally {
                this.contentMaterialsLoading = false
            }
        },

        async ensureContentMaterialClassificationTree() {
            if (this.contentMaterialClassificationLoading) return

            if (this.contentMaterialClassificationTree.length) {
                return
            }

            this.contentMaterialClassificationLoading = true

            try {
                const res = await axios.get(`/api/admin/teaching/curricula/${this.curriculum.id}/materials/config`)
                this.contentMaterialClassificationTree = Array.isArray(res.data?.classification_tree)
                    ? res.data.classification_tree
                    : []
            } catch {
                this.contentMaterialClassificationTree = []
                this.contentMaterialWorkspaceError = 'Der Arbeitsbereich konnte nicht geladen werden.'
            } finally {
                this.contentMaterialClassificationLoading = false
            }
        },

        async selectContentMaterialSubject(subject) {
            if (!subject?.name) {
                this.resetContentMaterialWorkspaceSelection()
                return
            }

            this.selectedContentMaterialSubject = subject
            this.selectedContentMaterialTopic = null
            this.selectedContentMaterialUnit = null

            await this.loadContentMaterialWorkspaceResults({
                subject: subject?.name || '',
            })
        },

        async selectContentMaterialTopic(topic) {
            if (!this.selectedContentMaterialSubject) {
                return
            }

            this.selectedContentMaterialTopic = topic
            this.selectedContentMaterialUnit = null

            await this.loadContentMaterialWorkspaceResults({
                subject: this.selectedContentMaterialSubject?.name || '',
                topic: topic?.name || '',
            })
        },

        async selectContentMaterialUnit(unit) {
            if (!this.selectedContentMaterialTopic) {
                return
            }

            this.selectedContentMaterialUnit = unit

            await this.loadContentMaterialWorkspaceResults({
                subject: this.selectedContentMaterialSubject?.name || '',
                topic: this.selectedContentMaterialTopic?.name || '',
                unit: unit?.name || '',
            })
        },

        resetContentMaterialWorkspaceSelection() {
            this.selectedContentMaterialSubject = null
            this.selectedContentMaterialTopic = null
            this.selectedContentMaterialUnit = null
            this.contentMaterialWorkspaceResults = []
            this.contentMaterialWorkspaceError = null
            this.contentMaterialPreviewCard = null
            this.contentMaterialPreviewAttachmentId = null
            this.contentMaterialPreviewDialogOpen = false
            this.contentMaterialPreviewFullscreen = true
        },

        syncContentMaterialPreviewSelection(cards = []) {
            const list = Array.isArray(cards) ? cards : []
            if (!list.length) {
                this.contentMaterialPreviewCard = null
                this.contentMaterialPreviewAttachmentId = null
                return
            }

            const nextCard = list.find((card) => card.id === this.contentMaterialPreviewCard?.id) || list[0]
            this.selectContentMaterialPreview(nextCard)
        },

        selectContentMaterialPreview(card) {
            const normalizedCard = this.normalizeAttachedMaterial(card)
            if (!normalizedCard?.id) {
                this.contentMaterialPreviewCard = null
                this.contentMaterialPreviewAttachmentId = null
                return
            }

            this.contentMaterialPreviewCard = normalizedCard
            this.contentMaterialPreviewAttachmentId = null
            this.contentMaterialPreviewDialogOpen = false
            this.contentMaterialPreviewFullscreen = true
        },

        openContentMaterialPreview(attachment, options = {}) {
            const attachmentId = Number(attachment?.id || 0)
            this.contentMaterialPreviewFullscreen = options.fullscreen !== false
            this.contentMaterialPreviewAttachmentId = Number.isFinite(attachmentId) && attachmentId > 0 ? attachmentId : null
            this.contentMaterialPreviewDialogOpen = this.contentMaterialPreviewAttachmentId !== null
        },

        closeContentMaterialPreview() {
            this.contentMaterialPreviewDialogOpen = false
            this.contentMaterialPreviewFullscreen = true
        },

        async fetchCurriculumMaterialCard(materialId) {
            const res = await axios.get(`/api/admin/teaching/curricula/${this.curriculum.id}/materials/cards/${materialId}`)

            return this.normalizeAttachedMaterial(res.data?.data)
        },

        async openAttachedMaterialDialog(material) {
            if (this.isPageActionLocked || this.topicSaving) return

            const normalizedMaterial = this.normalizeAttachedMaterial(material)
            if (!normalizedMaterial?.id) return

            this.attachedMaterialDialogOpen = true
            this.attachedMaterialDialogLoading = true
            this.attachedMaterialDialogError = null
            this.selectContentMaterialPreview(normalizedMaterial)

            try {
                const card = await this.fetchCurriculumMaterialCard(normalizedMaterial.id)
                if (!card?.id) {
                    throw new Error('missing material card')
                }

                this.selectContentMaterialPreview(card)
            } catch {
                this.attachedMaterialDialogError = 'Die Anhänge des Materials konnten nicht geladen werden.'
            } finally {
                this.attachedMaterialDialogLoading = false
            }
        },

        closeAttachedMaterialDialog() {
            this.attachedMaterialDialogOpen = false
            this.attachedMaterialDialogLoading = false
            this.attachedMaterialDialogError = null
            this.contentMaterialPreviewCard = null
            this.contentMaterialPreviewAttachmentId = null
            this.contentMaterialPreviewDialogOpen = false
            this.contentMaterialPreviewFullscreen = true
        },

        async loadContentMaterialWorkspaceResults(filters = {}) {
            const subject = typeof filters.subject === 'string' ? filters.subject.trim() : ''

            if (subject === '') {
                this.contentMaterialWorkspaceResults = []
                this.contentMaterialWorkspaceError = null

                return
            }

            this.contentMaterialWorkspaceLoading = true
            this.contentMaterialWorkspaceError = null

            try {
                const params = {
                    subject,
                    topic: typeof filters.topic === 'string' ? filters.topic.trim() : '',
                    unit: typeof filters.unit === 'string' ? filters.unit.trim() : '',
                    per_page: 20,
                }
                const res = await axios.get(`/api/admin/teaching/curricula/${this.curriculum.id}/materials/cards`, {
                    params,
                })

                this.contentMaterialWorkspaceResults = Array.isArray(res.data?.data)
                    ? res.data.data.map((card) => this.normalizeAttachedMaterial(card)).filter((card) => card.id)
                    : []
                this.syncContentMaterialPreviewSelection(this.contentMaterialWorkspaceResults)
            } catch {
                this.contentMaterialWorkspaceResults = []
                this.contentMaterialWorkspaceError = 'Die Materialien für diese Auswahl konnten nicht geladen werden.'
                this.syncContentMaterialPreviewSelection([])
            } finally {
                this.contentMaterialWorkspaceLoading = false
            }
        },

        currentContentMaterialIds() {
            return (Array.isArray(this.contentMaterialDialogTarget?.materials) ? this.contentMaterialDialogTarget.materials : [])
                .map((material) => Number(material?.id || 0))
                .filter((id) => Number.isFinite(id) && id > 0)
        },

        isContentMaterialAttached(material) {
            const materialId = Number(material?.id || 0)

            return materialId > 0 && this.currentContentMaterialIds().includes(materialId)
        },

        async attachContentMaterial(material) {
            if (!this.contentMaterialTarget?.topicId || this.topicSaving) return

            const normalizedMaterial = this.normalizeAttachedMaterial(material)
            if (!normalizedMaterial.id || normalizedMaterial.title === '') return
            if (this.isContentMaterialAttached(normalizedMaterial)) return

            const targetTopic = this.findTopic(this.contentMaterialTarget.topicId)
            if (!targetTopic) return

            const nextTopics = this.curriculumTopics.map((topic) => {
                if (topic.id !== targetTopic.id) {
                    return this.buildTopicPayload(topic)
                }

                if (this.contentMaterialTarget.type === 'unit' && this.contentMaterialTarget.unitId) {
                    return this.buildTopicPayload(topic, {
                        units: topic.units.map((unit) => (
                            unit.id === this.contentMaterialTarget.unitId
                                ? this.buildUnitPayload(unit, {
                                    materials: [...unit.materials, normalizedMaterial],
                                })
                                : this.buildUnitPayload(unit)
                        )),
                    })
                }

                return this.buildTopicPayload(topic, {
                    materials: [...topic.materials, normalizedMaterial],
                })
            })

            this.topicSaving = true

            try {
                await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Material konnte nicht hinzugefügt werden.')
            } finally {
                this.topicSaving = false
            }
        },

        async removeTopicMaterial(topicId, materialId) {
            if (this.topicSaving || this.isPageActionLocked) return

            const nextTopics = this.curriculumTopics.map((topic) => (
                topic.id === topicId
                    ? this.buildTopicPayload(topic, {
                        materials: topic.materials.filter((material) => material.id !== materialId),
                    })
                    : this.buildTopicPayload(topic)
            ))

            this.topicSaving = true

            try {
                await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Material konnte nicht entfernt werden.')
            } finally {
                this.topicSaving = false
            }
        },

        async removeUnitMaterial(topicId, unitId, materialId) {
            if (this.topicSaving || this.isPageActionLocked) return

            const nextTopics = this.curriculumTopics.map((topic) => {
                if (topic.id !== topicId) {
                    return this.buildTopicPayload(topic)
                }

                return this.buildTopicPayload(topic, {
                    units: topic.units.map((unit) => (
                        unit.id === unitId
                            ? this.buildUnitPayload(unit, {
                                materials: unit.materials.filter((material) => material.id !== materialId),
                            })
                            : this.buildUnitPayload(unit)
                    )),
                })
            })

            this.topicSaving = true

            try {
                await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Material konnte nicht entfernt werden.')
            } finally {
                this.topicSaving = false
            }
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

            this.openTopicAssignmentEditor(topic, this.defaultAssignmentEditorType(topic.assignment_type))
        },

        canDistributeTopicUnits(topic) {
            return this.buildTopicUnitDistribution(topic).assignedCount > 0
        },

        async distributeTopicUnits(topic) {
            if (this.isPageActionLocked || this.topicSaving) {
                return
            }

            const distribution = this.buildTopicUnitDistribution(topic)

            if (!distribution.assignedCount) {
                return
            }

            const nextTopics = this.curriculumTopics.map((entry) => {
                if (entry.id !== topic.id) {
                    return this.buildTopicPayload(entry)
                }

                return this.reconcileTopicUnitAssignments(this.buildTopicPayload(entry, {
                    units: distribution.units,
                }), 'units')
            })

            this.topicSaving = true

            try {
                await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Einheiten konnten nicht verteilt werden.')
            } finally {
                this.topicSaving = false
            }
        },

        toggleUnitAssignmentEditor(topic, unit) {
            if (this.isUnitAssignmentEditorOpen(topic.id, unit.id)) {
                this.closeTopicAssignmentEditor()
                return
            }

            this.openUnitAssignmentEditor(topic, unit, this.defaultAssignmentEditorType(unit.assignment_type))
        },

        defaultAssignmentEditorType(assignmentType) {
            return assignmentType === 'month' ? 'month' : 'weeks'
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
            this.$nextTick(() => this.scrollHighlightedCalendarIntoView())
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
            this.$nextTick(() => this.scrollHighlightedCalendarIntoView())
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

        weeksSummaryLabel(weekKeys) {
            if (!weekKeys || !weekKeys.length) return ''

            const weeks = weekKeys.map((key) => {
                const d = new Date(`${key}T00:00:00`)
                return { date: d, kw: this.getISOWeek(d), month: d.getMonth() }
            }).sort((a, b) => a.date - b.date)

            const groups = []
            let current = { months: new Set([weeks[0].month]), kwStart: weeks[0].kw, kwEnd: weeks[0].kw, lastDate: weeks[0].date }

            for (let i = 1; i < weeks.length; i++) {
                const daysDiff = Math.round((weeks[i].date - current.lastDate) / 86400000)
                if (daysDiff <= 8) {
                    current.kwEnd = weeks[i].kw
                    current.months.add(weeks[i].month)
                    current.lastDate = weeks[i].date
                } else {
                    groups.push(current)
                    current = { months: new Set([weeks[i].month]), kwStart: weeks[i].kw, kwEnd: weeks[i].kw, lastDate: weeks[i].date }
                }
            }
            groups.push(current)

            const schoolYearMonth = (m) => m >= 8 ? m - 8 : m + 4

            return groups.map((g) => {
                const monthNames = [...g.months].sort((a, b) => schoolYearMonth(a) - schoolYearMonth(b)).map((m) => MONTH_NAMES[m])
                const monthPart = monthNames.length > 1
                    ? `${monthNames[0]} – ${monthNames[monthNames.length - 1]}`
                    : monthNames[0]
                const kwPart = g.kwStart === g.kwEnd
                    ? `KW ${g.kwStart}`
                    : `KW ${g.kwStart}–${g.kwEnd}`
                return `${monthPart} (${kwPart})`
            }).join(', ')
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
                return this.weeksSummaryLabel(topic.week_keys)
            }

            return this.curriculumScopeLabel
        },

        assignmentSummaryVariant() {
            return 'tonal'
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

            if (weekKeys.size) {
                chips.push({
                    key: 'weeks-summary',
                    label: this.weeksSummaryLabel([...weekKeys].sort()),
                })
            }

            return chips
        },

        unitInheritedWeekKeys(topic, unit) {
            if (!topic || !unit || unit.assignment_type !== 'none' || topic.assignment_type !== 'weeks') {
                return []
            }

            return [...topic.week_keys]
        },

        effectiveUnitWeekKeys(topic, unit) {
            if (!topic || !unit) {
                return []
            }

            if (unit.assignment_type === 'weeks') {
                return [...unit.week_keys]
            }

            return this.unitInheritedWeekKeys(topic, unit)
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
                    entries.push({
                        id: topic.id,
                        topicId: topic.id,
                        topicTitle: topic.title,
                        title: topic.title,
                        isExam: false,
                    })
                } else if (topic.assignment_type === 'month' && topic.month_keys.includes(monthKey)) {
                    entries.push({
                        id: topic.id,
                        topicId: topic.id,
                        topicTitle: topic.title,
                        title: topic.title,
                        isExam: false,
                    })
                }

                topic.units.forEach((unit) => {
                    if (!unit.title) {
                        return
                    }

                    if (unit.assignment_type === 'all_weeks') {
                        entries.push({
                            id: `${topic.id}-${unit.id}`,
                            topicId: topic.id,
                            topicTitle: topic.title,
                            unitTitle: unit.title,
                            title: this.overviewUnitTitle(topic, unit),
                            isExam: Boolean(unit.is_exam),
                        })
                        return
                    }

                    if (unit.assignment_type === 'month' && unit.month_keys.includes(monthKey)) {
                        entries.push({
                            id: `${topic.id}-${unit.id}`,
                            topicId: topic.id,
                            topicTitle: topic.title,
                            unitTitle: unit.title,
                            title: this.overviewUnitTitle(topic, unit),
                            isExam: Boolean(unit.is_exam),
                        })
                    }
                })

                return entries
            })
        },

        monthHasExamEntries(month) {
            return this.monthOverviewEntries(month).some((entry) => entry.isExam)
        },

        topicsForWeek(weekKey) {
            if (!weekKey) {
                return []
            }

            return this.curriculumTopics.flatMap((topic) => {
                const hasTopicWeekEntry = Boolean(topic.title) && topic.assignment_type === 'weeks' && topic.week_keys.includes(weekKey)
                const unitEntries = []

                topic.units.forEach((unit) => {
                    const effectiveWeekKeys = this.effectiveUnitWeekKeys(topic, unit)

                    if (Boolean(unit.title) && effectiveWeekKeys.includes(weekKey)) {
                        unitEntries.push({
                            id: `${topic.id}-${unit.id}`,
                            topicId: topic.id,
                            unitId: unit.id,
                            topicTitle: topic.title,
                            unitTitle: unit.title,
                            title: this.overviewUnitTitle(topic, unit),
                            isExam: Boolean(unit.is_exam),
                            showTopicPrefix: !hasTopicWeekEntry,
                        })
                    }
                })

                if (hasTopicWeekEntry) {
                    if (unitEntries.length === 0) {
                        return [
                            {
                                id: topic.id,
                                topicId: topic.id,
                                topicTitle: topic.title,
                                title: topic.title,
                                isExam: false,
                            },
                        ]
                    }

                    const mergedUnitTitle = unitEntries.map((entry) => entry.unitTitle).join(', ')

                    return [
                        {
                            id: topic.id,
                            topicId: topic.id,
                            topicTitle: topic.title,
                            unitTitle: mergedUnitTitle,
                            showTopicPrefix: true,
                            title: `${topic.title}: ${mergedUnitTitle}`,
                            isExam: unitEntries.some((entry) => entry.isExam),
                        },
                    ]
                }

                return unitEntries
            })
        },

        weekEntriesForMonth(month) {
            if (!Array.isArray(month?.weeks)) {
                return []
            }

            const entryMap = new Map()

            month.weeks.forEach((week) => {
                this.topicsForWeek(week.weekKey).forEach((entry) => {
                    if (!entryMap.has(entry.id)) {
                        entryMap.set(entry.id, entry)
                    }
                })
            })

            return [...entryMap.values()]
        },

        isMonthFullyAssigned(month) {
            if (!Array.isArray(month?.weeks) || month.weeks.length === 0) {
                return false
            }

            return month.weeks.every((week) => this.isFreeWeek(week.weekKey) || this.topicsForWeek(week.weekKey).length > 0)
        },

        monthCollapseState(monthKey) {
            if (!monthKey || !Object.prototype.hasOwnProperty.call(this.manualMonthCollapseStates, monthKey)) {
                return null
            }

            return Boolean(this.manualMonthCollapseStates[monthKey])
        },

        isMonthFullyAssignedForCurriculum(month, curriculum = this.curriculum) {
            if (!Array.isArray(month?.weeks) || month.weeks.length === 0) {
                return false
            }

            const freeWeekKeys = new Set(Array.isArray(curriculum?.free_weeks) ? curriculum.free_weeks : [])
            const topics = (Array.isArray(curriculum?.topics) ? curriculum.topics : [])
                .map((topic, index) => this.normalizeTopic(topic, index))

            return month.weeks.every((week) => {
                if (freeWeekKeys.has(week.weekKey)) {
                    return true
                }

                return topics.some((topic) => {
                    if (Boolean(topic.title) && topic.assignment_type === 'weeks' && topic.week_keys.includes(week.weekKey)) {
                        return true
                    }

                    return topic.units.some((unit) => (
                        Boolean(unit.title)
                        && this.effectiveUnitWeekKeys(topic, unit).includes(week.weekKey)
                    ))
                })
            })
        },

        closeAssignmentEditorForCompletedMonth(weekKey, curriculum = this.curriculum) {
            if (!this.collapseFullMonths || !weekKey) {
                return
            }

            const month = this.visibleMonths.find((entry) => entry.assignmentKey === this.monthKeyFromWeekKey(weekKey))

            if (!month || !this.isMonthFullyAssignedForCurriculum(month, curriculum)) {
                return
            }

            this.closeTopicAssignmentEditor()
        },

        shouldAutoCollapseMonth(month) {
            if (!this.collapseFullMonths || !this.isMonthFullyAssigned(month)) {
                return false
            }

            return !this.isMonthAssignedToHighlightedItem(month)
        },

        shouldCollapseMonth(month) {
            const monthKey = month?.assignmentKey
            const manualState = this.monthCollapseState(monthKey)

            if (manualState !== null) {
                return manualState
            }

            return this.shouldAutoCollapseMonth(month)
        },

        toggleMonthCollapse(month) {
            const monthKey = month?.assignmentKey

            if (!monthKey) {
                return
            }

            const currentState = this.shouldCollapseMonth(month)
            const nextState = !currentState
            const defaultState = this.shouldAutoCollapseMonth(month)
            const nextCollapseStates = { ...this.manualMonthCollapseStates }

            if (nextState === defaultState) {
                delete nextCollapseStates[monthKey]
            } else {
                nextCollapseStates[monthKey] = nextState
            }

            this.manualMonthCollapseStates = nextCollapseStates
        },

        monthOverviewEntries(month) {
            const monthEntries = this.topicsForMonth(month)

            if (!this.shouldCollapseMonth(month)) {
                return monthEntries
            }

            const entryMap = new Map(monthEntries.map((entry) => [entry.id, entry]))

            this.weekEntriesForMonth(month).forEach((entry) => {
                if (!entryMap.has(entry.id)) {
                    entryMap.set(entry.id, entry)
                }
            })

            return [...entryMap.values()]
        },

        monthOverviewGroups(month) {
            const groups = new Map()

            this.monthOverviewEntries(month).forEach((entry) => {
                const groupId = entry.topicId ?? entry.id
                const topicTitle = entry.topicTitle ?? entry.title
                const currentGroup = groups.get(groupId) ?? {
                    id: groupId,
                    topicTitle,
                    units: [],
                }

                if (entry.unitTitle) {
                    if (!currentGroup.units.some((unit) => unit.id === entry.id)) {
                        currentGroup.units.push({
                            id: entry.id,
                            title: entry.unitTitle,
                            isExam: entry.isExam,
                        })
                    }
                }

                groups.set(groupId, currentGroup)
            })

            return [...groups.values()]
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

        isMonthAssignedToHighlightedItem(month) {
            const monthKey = month?.assignmentKey
            const assignmentItems = this.highlightedAssignmentItems

            if (!monthKey || assignmentItems.length === 0) {
                return false
            }

            return assignmentItems.some((assignmentItem) => {
                if (assignmentItem.assignment_type === 'all_weeks') {
                    return true
                }

                if (assignmentItem.assignment_type === 'month') {
                    return assignmentItem.month_keys.includes(monthKey)
                }

                if (assignmentItem.assignment_type === 'weeks') {
                    return assignmentItem.week_keys.some((weekKey) => this.monthKeyFromWeekKey(weekKey) === monthKey)
                }

                return false
            })
        },

        isWeekAssignedToHighlightedItem(weekKey) {
            const assignmentItems = this.highlightedAssignmentItems

            if (assignmentItems.length === 0) {
                return false
            }

            return assignmentItems.some((assignmentItem) => {
                if (assignmentItem.assignment_type === 'all_weeks') {
                    return true
                }

                if (assignmentItem.assignment_type === 'weeks') {
                    return assignmentItem.week_keys.includes(weekKey)
                }

                if (assignmentItem.assignment_type === 'month') {
                    return assignmentItem.month_keys.includes(this.monthKeyFromWeekKey(weekKey))
                }

                return false
            })
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

        monthKeyForSelectedYear(monthNumber) {
            const normalizedMonth = Number.parseInt(String(monthNumber), 10)

            if (!Number.isInteger(normalizedMonth) || normalizedMonth < 1 || normalizedMonth > 12) {
                return null
            }

            const year = normalizedMonth >= 9 ? this.selectedYear : this.selectedYear + 1

            return `${year}-${String(normalizedMonth).padStart(2, '0')}`
        },

        normalizeMonthAssignmentKey(monthKey) {
            const rawMonthKey = String(monthKey).trim()
            const match = rawMonthKey.match(/^(?:\d{4}-)?(\d{2})$/)

            if (!match) {
                return rawMonthKey
            }

            return this.monthKeyForSelectedYear(match[1]) ?? rawMonthKey
        },

        weekKeyForSelectedYear(isoWeek) {
            const normalizedWeek = Number.parseInt(String(isoWeek), 10)

            if (!Number.isInteger(normalizedWeek) || normalizedWeek < 1 || normalizedWeek > 53) {
                return null
            }

            for (const month of this.allMonths) {
                const matchingWeek = month.weeks.find((week) => week.kw === normalizedWeek)

                if (matchingWeek?.weekKey) {
                    return matchingWeek.weekKey
                }
            }

            return null
        },

        normalizeWeekAssignmentKey(weekKey) {
            const rawWeekKey = String(weekKey).trim()
            const weekStart = new Date(`${rawWeekKey}T00:00:00`)

            if (Number.isNaN(weekStart.getTime())) {
                return rawWeekKey
            }

            return this.weekKeyForSelectedYear(this.getISOWeek(weekStart)) ?? rawWeekKey
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

        topicHue(idx) {
            const base = 200
            return (base + idx * 32) % 360
        },

        calendarScrollElement() {
            const calendarScrollRef = this.$refs.calendarScroll

            if (calendarScrollRef instanceof HTMLElement) {
                return calendarScrollRef
            }

            if (calendarScrollRef?.$el instanceof HTMLElement) {
                return calendarScrollRef.$el
            }

            return null
        },

        firstHighlightedCalendarElement() {
            const calendarScrollElement = this.calendarScrollElement()

            if (!calendarScrollElement) {
                return null
            }

            for (const month of this.visibleMonths) {
                if (!this.isMonthAssignedToHighlightedItem(month)) {
                    continue
                }

                if (!this.shouldCollapseMonth(month)) {
                    const firstAssignedWeek = month.weeks.find((week) => this.isWeekAssignedToHighlightedItem(week.weekKey))

                    if (firstAssignedWeek) {
                        const weekElement = calendarScrollElement.querySelector(`[data-week-key="${firstAssignedWeek.weekKey}"]`)

                        if (weekElement instanceof HTMLElement) {
                            return weekElement
                        }
                    }
                }

                const monthElement = calendarScrollElement.querySelector(`[data-month-key="${month.assignmentKey}"]`)

                if (monthElement instanceof HTMLElement) {
                    return monthElement
                }
            }

            return null
        },

        scrollHighlightedCalendarIntoView() {
            const calendarScrollElement = this.calendarScrollElement()
            const targetElement = this.firstHighlightedCalendarElement()

            if (!calendarScrollElement || !targetElement) {
                return
            }

            const containerRect = calendarScrollElement.getBoundingClientRect()
            const targetRect = targetElement.getBoundingClientRect()
            const topOffset = Math.min(Math.round(containerRect.height * 0.22), 180)
            const top = Math.max(
                0,
                calendarScrollElement.scrollTop + (targetRect.top - containerRect.top) - topOffset,
            )

            if (typeof calendarScrollElement.scrollTo === 'function') {
                calendarScrollElement.scrollTo({
                    top,
                    behavior: 'smooth',
                })
                return
            }

            calendarScrollElement.scrollTop = top
        },

        isTopicSelected(topicId) {
            return this.selectedTopicId === topicId && this.selectedUnitId === null
        },

        isTopicCollapsed(topicId) {
            return Boolean(this.topicCollapseStates?.[topicId])
        },

        toggleTopicCollapse(topicId) {
            if (!topicId) {
                return
            }

            const isCollapsed = this.isTopicCollapsed(topicId)
            const nextTopicCollapseStates = { ...this.topicCollapseStates }

            if (isCollapsed) {
                delete nextTopicCollapseStates[topicId]
                this.topicCollapseStates = nextTopicCollapseStates
                return
            }

            if (this.activeTopicAssignmentId === topicId) {
                this.closeTopicAssignmentEditor()
            }

            if (this.showUnitFormForTopicId === topicId) {
                this.cancelUnitForm()
            }

            nextTopicCollapseStates[topicId] = true
            this.topicCollapseStates = nextTopicCollapseStates
        },

        toggleSelectedTopic(topicId) {
            const shouldDeselectTopic = this.isTopicSelected(topicId)

            this.selectedTopicId = shouldDeselectTopic ? null : topicId
            this.selectedUnitTopicId = null
            this.selectedUnitId = null

            if (!shouldDeselectTopic) {
                this.$nextTick(() => this.scrollHighlightedCalendarIntoView())
            }
        },

        isUnitSelected(topicId, unitId) {
            return this.selectedUnitTopicId === topicId && this.selectedUnitId === unitId
        },

        toggleSelectedUnit(topicId, unitId) {
            const shouldDeselectUnit = this.isUnitSelected(topicId, unitId)

            this.selectedTopicId = shouldDeselectUnit ? null : topicId
            this.selectedUnitTopicId = shouldDeselectUnit ? null : topicId
            this.selectedUnitId = shouldDeselectUnit ? null : unitId

            if (!shouldDeselectUnit) {
                this.$nextTick(() => this.scrollHighlightedCalendarIntoView())
            }
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

        async applyFreeWeeksTemplate() {
            if (!this.canApplyFreeWeeksTemplate) {
                return
            }

            this.isApplyingFreeWeeksTemplate = true

            try {
                await this.persistCurriculum({
                    free_weeks: this.freeWeeksTemplateWeekKeys,
                }, 'Freie Tage konnten nicht übernommen werden.')
            } finally {
                this.isApplyingFreeWeeksTemplate = false
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
                const updatedCurriculum = await this.persistUnitAssignment(this.activeTopicAssignmentTopic.id, this.activeTopicAssignmentUnitId, {
                    assignment_type: 'weeks',
                    month_keys: [],
                    week_keys: nextWeekKeys,
                }, 'Wochenauswahl der Einheit konnte nicht gespeichert werden.')

                this.closeAssignmentEditorForCompletedMonth(weekKey, updatedCurriculum)

                return
            }

            const updatedCurriculum = await this.persistTopicAssignment(this.activeTopicAssignmentTopic.id, {
                assignment_type: 'weeks',
                month_keys: [],
                week_keys: nextWeekKeys,
            }, 'Wochenauswahl konnte nicht gespeichert werden.')

            this.closeAssignmentEditorForCompletedMonth(weekKey, updatedCurriculum)
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
                if (doc.material_card_attachment_id && doc.preview_url) {
                    this.previewDoc = this.previewDoc?.id === doc.id ? null : doc
                    return
                }

                this.openMaterialAttachmentDialog(doc)
                return
            }

            if (doc.source_type !== 'upload') return
            this.previewDoc = this.previewDoc?.id === doc.id ? null : doc
        },

        removeDocument(doc) {
            if (this.isPageActionLocked || !doc) return
            this.documentToDelete = doc
            this.documentDeleteDialogOpen = true
        },

        closeDocumentDeleteDialog() {
            if (this.documentDeleteLoading) return
            this.documentDeleteDialogOpen = false
            this.documentToDelete = null
        },

        async confirmDocumentDelete() {
            if (this.isPageActionLocked) return
            if (!this.documentToDelete?.id) {
                this.closeDocumentDeleteDialog()
                return
            }

            this.documentDeleteLoading = true
            let wasDeleted = false

            try {
                await axios.delete(`/api/admin/teaching/curricula/${this.curriculum.id}/documents/${this.documentToDelete.id}`)
                if (this.previewDoc?.id === this.documentToDelete.id) this.previewDoc = null
                this.documents = this.documents.filter((d) => d.id !== this.documentToDelete.id)
                wasDeleted = true
            } catch {
                // silent
            } finally {
                this.documentDeleteLoading = false
                if (wasDeleted) {
                    this.closeDocumentDeleteDialog()
                }
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
                const attachmentCount = this.materialFileAttachmentCount(card)
                this.materialDialogOpen = false
                this.showUploadOptions = false
                await this.loadDocuments()
                if (attachedDocument?.id) {
                    const nextDocument = this.documents.find((document) => document.id === attachedDocument.id) || attachedDocument
                    if (attachmentCount === 1) {
                        await this.openMaterialAttachmentDialog(nextDocument)
                        if (this.materialAttachmentOptions.length === 1) {
                            await this.selectMaterialAttachment(this.materialAttachmentOptions[0])
                        }
                        return
                    }

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

        contentMaterialPreviewAttachmentIcon(attachment) {
            const mimeType = String(attachment?.mime_type || '').toLowerCase()

            return mimeType.startsWith('image/')
                ? 'mdi-file-image-outline'
                : 'mdi-file-document-outline'
        },

        normalizeDownloadFileName(value) {
            const normalized = String(value || '')
                .trim()
                .replace(/[\\/:*?"<>|]/g, '_')

            return normalized.slice(0, 255) || 'Datei'
        },

        filenameFromContentDisposition(headerValue) {
            const header = String(headerValue || '').trim()
            if (!header) return ''

            const utf8Match = header.match(/filename\*\s*=\s*UTF-8''([^;]+)/i)
            if (utf8Match?.[1]) {
                try {
                    return decodeURIComponent(utf8Match[1]).trim()
                } catch {
                    return String(utf8Match[1]).trim()
                }
            }

            const plainMatch = header.match(/filename\s*=\s*\"?([^\";]+)\"?/i)
            return String(plainMatch?.[1] || '').trim()
        },

        async downloadContentMaterialAttachment(attachment) {
            const downloadUrl = String(attachment?.download_url || '').trim()
            if (downloadUrl === '') {
                useNotificationStore().notify({
                    message: 'Datei ist derzeit nicht verfügbar.',
                    type: 'warning',
                    timeout: 3000,
                })
                return
            }

            try {
                const response = await axios.get(downloadUrl, {
                    responseType: 'blob',
                })

                const disposition = response?.headers?.['content-disposition']
                const serverFileName = this.filenameFromContentDisposition(disposition)
                const fallbackName = String(attachment?.name || '').trim()
                const fileName = this.normalizeDownloadFileName(serverFileName || fallbackName)
                const blob = response?.data instanceof Blob ? response.data : new Blob([response?.data])
                const objectUrl = URL.createObjectURL(blob)
                const link = document.createElement('a')
                link.href = objectUrl
                link.download = fileName
                document.body.appendChild(link)
                link.click()
                link.remove()
                URL.revokeObjectURL(objectUrl)
            } catch (error) {
                useNotificationStore().notify({
                    status: error?.response?.status,
                    message: error?.response?.data?.message || 'Datei konnte nicht heruntergeladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
            }
        },

        materialFileAttachmentCount(card) {
            const count = Number(card?.attachments_count ?? 0)
            return Number.isFinite(count) && count > 0 ? count : 0
        },

        materialAttachmentCountLabel(card) {
            const count = this.materialFileAttachmentCount(card)
            if (count === 1) {
                return '1 Anhang'
            }

            return `${count} Anhänge`
        },

        materialPickerSubtitle(card) {
            const parts = []
            const subject = typeof card?.subject === 'string' ? card.subject.trim() : ''
            const attachmentCount = this.materialFileAttachmentCount(card)

            if (subject !== '') {
                parts.push(subject)
            }

            if (attachmentCount > 0) {
                parts.push(this.materialAttachmentCountLabel(card))
            } else {
                parts.push('Keine Anhänge')
            }

            return parts.join(' · ')
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

.curriculum-detail__meta {
    flex-wrap: wrap;
    justify-content: flex-end;
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

.curriculum-detail__view-toolbar {
    border: 1px solid rgba(99, 102, 241, 0.16);
    background: rgba(15, 23, 42, 0.55);
}

.curriculum-detail__view-toolbar-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 18px;
}

.curriculum-detail__week-view {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.curriculum-detail__week-view-label {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #94a3b8;
}

.curriculum-detail__week-view-btn {
    color: #c7d2fe !important;
    border-color: rgba(148, 163, 184, 0.35) !important;
}

.curriculum-detail__week-view-toggle .v-btn--active.curriculum-detail__week-view-btn {
    color: #fff !important;
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

.curriculum-detail__body--compact-calendar {
    grid-template-columns: auto clamp(420px, 36vw, 640px) minmax(420px, 1fr);
}

/* ---------- Calendar ---------- */
.curriculum-detail__calendar-scroll {
    position: sticky;
    top: 72px;
    align-self: flex-start;
    max-height: calc(100vh - 84px);
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior: contain;
    background: rgba(15, 23, 42, 0.32);
    border: 1px solid rgba(148, 163, 184, 0.12);
}

.curriculum-detail__calendar {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    flex-shrink: 0;
    width: clamp(430px, 29vw, 500px);
}

.curriculum-detail__calendar--compact {
    width: clamp(250px, 18vw, 300px);
}

.curriculum-detail__calendar--compact .curriculum-detail__month {
    width: min(100%, 300px);
}

.curriculum-detail__month {
    --accent: hsl(var(--month-hue), 70%, 42%);
    --accent-dim: hsl(var(--month-hue), 50%, 88%);
    --accent-glow: hsl(var(--month-hue), 70%, 50%);
    --month-tint: hsla(var(--month-hue), 80%, 55%, 0.12);
    border-radius: 20px;
    border: 1px solid hsla(var(--month-hue), 55%, 45%, 0.3);
    background: rgba(255, 255, 255, 0.84);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
    overflow: hidden;
    width: 100%;
    box-sizing: border-box;
}

.curriculum-detail__month-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    width: 100%;
    padding: 14px 20px 8px;
    background:
        linear-gradient(135deg, var(--month-tint), transparent 70%),
        rgba(255, 255, 255, 0.6);
    border: none;
    border-bottom: 1px solid hsla(var(--month-hue), 55%, 45%, 0.28);
    cursor: pointer;
    text-align: left;
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
    color: #475569;
}

.curriculum-detail__month--with-topics {
    box-shadow: 0 0 0 1px rgba(165, 180, 252, 0.08), 0 14px 30px rgba(15, 23, 42, 0.24);
}

.curriculum-detail__month--with-exams {
    box-shadow: 0 0 0 1px rgba(220, 38, 38, 0.22), 0 14px 30px rgba(127, 29, 29, 0.22);
}

.curriculum-detail__month--topic-selected {
    border-color: rgba(79, 70, 229, 0.62);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.28), 0 16px 34px rgba(79, 70, 229, 0.2);
}

.curriculum-detail__month--collapsed .curriculum-detail__month-topics {
    padding-bottom: 14px;
    border-bottom: none;
}

.curriculum-detail__month--collapsed .curriculum-detail__month-topic-units,
.curriculum-detail__month--collapsed .curriculum-detail__month-topic-unit {
    font-weight: 400;
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
    color: #000;
}

.curriculum-detail__month-topics-text {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    color: #000;
    font-size: 0.78rem;
    font-weight: 500;
    line-height: 1.3;
}

.curriculum-detail__month-topic-line {
    display: block;
}

.curriculum-detail__month-topic-name {
    font-weight: 700;
}

.curriculum-detail__month-topic-units {
    font-weight: 500;
}

.curriculum-detail__month-topic-units .curriculum-detail__overview-entry:not(:last-child) {
    margin-right: 0.3rem;
}

.curriculum-detail__month-topic-unit {
    font-weight: 500;
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
    color: #4f46e5;
    text-shadow: none;
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
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.1), transparent 60%),
        linear-gradient(180deg, rgba(238, 242, 255, 0.95), rgba(224, 231, 255, 0.85)) !important;
    border: 1px solid rgba(99, 102, 241, 0.18) !important;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
    transition: background 0.2s, border-color 0.2s;
    position: relative;
    width: 100%;
    box-sizing: border-box;
}

.curriculum-detail__week--compact {
    gap: 8px;
    padding: 6px 8px;
}

.curriculum-detail__week:hover {
    border-color: rgba(99, 102, 241, 0.3) !important;
}

.curriculum-detail__week .curriculum-detail__week-kw,
.curriculum-detail__week .curriculum-detail__day-name {
    color: #475569;
}

.curriculum-detail__week .curriculum-detail__week-num,
.curriculum-detail__week .curriculum-detail__day-num {
    color: #1e293b;
}

.curriculum-detail__week .curriculum-detail__week-range {
    color: #475569 !important;
}

.curriculum-detail__week .curriculum-detail__week-topics {
    color: #1e293b !important;
    text-shadow: none !important;
}

.curriculum-detail__week .curriculum-detail__day {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(99, 102, 241, 0.12);
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
    background: rgba(79, 70, 229, 0.28) !important;
    border-color: rgba(99, 102, 241, 0.72) !important;
    box-shadow: 0 0 0 1px rgba(129, 140, 248, 0.24), 0 0 22px rgba(79, 70, 229, 0.24);
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
    border-color: rgba(96, 165, 250, 0.48);
    box-shadow: 0 0 0 1px rgba(147, 197, 253, 0.14), 0 0 18px rgba(59, 130, 246, 0.16);
}

.curriculum-detail__week-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.curriculum-detail__week-status-icon {
    color: #16a34a !important;
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

.curriculum-detail__week--compact .curriculum-detail__week-range {
    min-width: auto;
}

.curriculum-detail__week--free .curriculum-detail__week-range {
    color: #86efac;
}

.curriculum-detail__week--with-topics .curriculum-detail__week-range {
    color: #bfdbfe;
}

.curriculum-detail__week-range-label {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 0;
    border-radius: 999px;
    transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease, padding 0.18s ease, transform 0.18s ease;
}

.curriculum-detail__week-range-label--selected {
    padding: 0.2rem 0.62rem;
    background: rgba(79, 70, 229, 0.3);
    color: #1e1b4b !important;
    font-weight: 800;
    letter-spacing: 0.02em;
    box-shadow: 0 8px 16px rgba(79, 70, 229, 0.22);
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

.curriculum-detail__week-topic-label {
    display: inline-block;
    padding: 0.24rem 0.38rem;
    border-radius: 0.36rem;
    background: rgba(250, 204, 21, 0.82);
    color: #1f2937;
    line-height: 1;
    vertical-align: middle;
    text-shadow: none;
}

.curriculum-detail__week-topic-label-topic {
    font-weight: 700;
}

.curriculum-detail__week-topic-label-unit {
    margin-left: 0.22rem;
    font-weight: 400;
}

.curriculum-detail__week-topic-label-unit--no-prefix {
    margin-left: 0;
}

.curriculum-detail__week--with-exams {
    border-color: rgba(220, 38, 38, 0.45) !important;
    box-shadow: inset 0 0 0 1px rgba(220, 38, 38, 0.1), 0 8px 18px rgba(127, 29, 29, 0.14);
}

.curriculum-detail__week--with-exams .curriculum-detail__week-topics {
    color: #4f46e5 !important;
    text-shadow: none !important;
}

.curriculum-detail__week--topic-selected,
.curriculum-detail__week--topic-selected:hover {
    border-color: rgba(129, 140, 248, 0.52) !important;
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

.curriculum-detail__side-card--scrollable {
    max-height: calc(100vh - 24px);
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior: contain;
    background: transparent;
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
    border: 1px solid rgba(15, 23, 42, 0.12);
    background:
        radial-gradient(circle at top right, rgba(37, 99, 235, 0.18), transparent 52%),
        linear-gradient(150deg, rgba(255, 255, 255, 0.95), rgba(241, 245, 249, 0.93));
    box-shadow:
        0 10px 24px rgba(15, 23, 42, 0.11),
        inset 0 1px 0 rgba(255, 255, 255, 0.7);
    color: #0f172a;
}

.curriculum-detail__side-card--content .curriculum-detail__side-card-header {
    color: #0f172a;
    border-bottom-color: rgba(30, 41, 59, 0.1);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.5), rgba(219, 234, 254, 0.36));
}

.curriculum-detail__side-card--content {
    width: clamp(420px, 36vw, 640px);
    max-width: 100%;
}

.curriculum-detail__side-card--documents {
    width: 100%;
    min-width: 420px;
    max-width: 100%;
}

.curriculum-detail__side-card--documents .curriculum-detail__side-card-inner {
    border: 1px solid rgba(15, 23, 42, 0.12);
    background:
        radial-gradient(circle at top right, rgba(37, 99, 235, 0.18), transparent 52%),
        linear-gradient(150deg, rgba(255, 255, 255, 0.95), rgba(241, 245, 249, 0.93));
    box-shadow:
        0 10px 24px rgba(15, 23, 42, 0.11),
        inset 0 1px 0 rgba(255, 255, 255, 0.7);
    color: #0f172a;
}

.curriculum-detail__side-card--documents .curriculum-detail__side-card-header {
    color: #0f172a;
    border-bottom-color: rgba(30, 41, 59, 0.1);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.5), rgba(219, 234, 254, 0.36));
}

.curriculum-detail__side-card--documents .curriculum-detail__side-card-body {
    color: #1e293b;
}

.curriculum-detail__side-card--content .curriculum-detail__side-card-body {
    color: #1e293b;
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
    color: #0f172a;
}

.curriculum-detail__content-hint {
    font-size: 0.8rem;
    color: #475569;
    line-height: 1.45;
    margin-top: 4px;
}

.curriculum-detail__content-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

.curriculum-detail__topic-form {
    border-radius: 14px;
    border: 1px solid rgba(15, 23, 42, 0.12);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(241, 245, 249, 0.96));
    padding: 14px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
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

.curriculum-detail__material-dialog {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.curriculum-detail__material-dialog-toolbar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.curriculum-detail__material-dialog-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
}

.curriculum-detail__material-dialog-subtitle {
    font-size: 0.8rem;
    color: #64748b;
    line-height: 1.45;
}

.curriculum-detail__material-dialog-empty {
    color: #64748b;
}

.curriculum-detail__material-dialog-error {
    color: #b91c1c;
}

.curriculum-detail__material-mode-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.curriculum-detail__material-results {
    max-height: 380px;
    overflow-y: auto;
}

.curriculum-detail__material-selection-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(300px, 0.85fr);
    gap: 14px;
}

.curriculum-detail__material-result-item {
    border: 1px solid rgba(99, 102, 241, 0.14);
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.08), transparent 60%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(238, 242, 255, 0.92));
    cursor: pointer;
}

.curriculum-detail__material-result-item--active {
    border-color: rgba(79, 70, 229, 0.38);
    box-shadow: 0 0 0 1px rgba(129, 140, 248, 0.18);
}

.curriculum-detail__material-browser {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.curriculum-detail__material-browser-panel,
.curriculum-detail__material-browser-results {
    padding: 12px;
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(241, 245, 249, 0.94));
}

.curriculum-detail__material-filter-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.curriculum-detail__material-browser-summary {
    display: flex;
    align-items: stretch;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.curriculum-detail__material-browser-summary-card {
    min-width: 180px;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(255, 255, 255, 0.76);
}

.curriculum-detail__material-browser-summary-label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 4px;
}

.curriculum-detail__material-browser-summary-value {
    font-size: 0.88rem;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.4;
}

.curriculum-detail__material-browser-reset {
    margin-left: auto;
    align-self: center;
}

.curriculum-detail__material-preview-panel {
    padding: 12px;
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(241, 245, 249, 0.94));
    display: flex;
    flex-direction: column;
    min-height: 420px;
}

.curriculum-detail__material-preview-copy {
    margin-bottom: 10px;
}

.curriculum-detail__material-preview-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: #0f172a;
}

.curriculum-detail__material-preview-subtitle {
    margin-top: 4px;
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.45;
}

.curriculum-detail__material-preview-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.curriculum-detail__material-preview-list-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(255, 255, 255, 0.82);
    cursor: pointer;
}

.curriculum-detail__material-preview-list-item:hover {
    border-color: rgba(99, 102, 241, 0.28);
    background: rgba(238, 242, 255, 0.92);
}

.curriculum-detail__material-preview-list-copy {
    min-width: 0;
    flex: 1;
}

.curriculum-detail__material-preview-list-title {
    font-size: 0.84rem;
    font-weight: 600;
    color: #1e293b;
}

.curriculum-detail__material-preview-list-subtitle {
    margin-top: 2px;
    font-size: 0.74rem;
    color: #64748b;
}

.curriculum-detail__material-preview-list-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.curriculum-detail__material-preview-empty {
    flex: 1;
    min-height: 220px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #64748b;
    border-radius: 12px;
    border: 1px dashed rgba(148, 163, 184, 0.26);
    background: rgba(248, 250, 252, 0.86);
    padding: 16px;
}

.curriculum-detail__fullscreen-preview {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: rgba(248, 250, 252, 0.98) !important;
}

.curriculum-detail__modal-preview {
    display: flex;
    flex-direction: column;
    min-height: min(78vh, 820px);
    background: rgba(248, 250, 252, 0.98) !important;
}

.curriculum-detail__fullscreen-preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 20px;
    border-bottom: 1px solid rgba(148, 163, 184, 0.18);
}

.curriculum-detail__fullscreen-preview-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

.curriculum-detail__fullscreen-preview-title {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}

.curriculum-detail__fullscreen-preview-subtitle {
    margin-top: 4px;
    font-size: 0.82rem;
    color: #64748b;
}

.curriculum-detail__fullscreen-preview-close-btn {
    font-weight: 700;
    letter-spacing: 0.01em;
    min-width: 130px;
    box-shadow: 0 10px 24px rgba(185, 28, 28, 0.18);
}

.curriculum-detail__fullscreen-preview-body {
    flex: 1;
    padding: 0 !important;
    background: rgba(226, 232, 240, 0.55);
}

.curriculum-detail__fullscreen-preview-iframe,
.curriculum-detail__fullscreen-preview-image {
    width: 100%;
    height: calc(100vh - 82px);
    border: none;
    display: block;
    background: #fff;
}

.curriculum-detail__fullscreen-preview-image {
    object-fit: contain;
}

.curriculum-detail__modal-preview-iframe,
.curriculum-detail__modal-preview-image {
    width: 100%;
    height: min(68vh, 720px);
    border: none;
    display: block;
    background: #fff;
}

.curriculum-detail__modal-preview-image {
    object-fit: contain;
}

.curriculum-detail__fullscreen-preview-empty {
    height: calc(100vh - 82px);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #475569;
    padding: 24px;
}

.curriculum-detail__material-browser-heading {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 10px;
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

.curriculum-detail__topic-entry {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.curriculum-detail__topic-item {
    --topic-accent: hsl(var(--topic-hue, 220), 70%, 48%);
    --topic-tint: hsla(var(--topic-hue, 220), 80%, 55%, 0.12);
    display: flex;
    flex-direction: column;
    flex: 1;
    gap: 0;
    min-width: 0;
    padding: 0;
    border-radius: 12px;
    border: 1px solid hsla(var(--topic-hue, 220), 55%, 45%, 0.3);
    background: rgba(255, 255, 255, 0.84);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
    overflow: hidden;
}

.curriculum-detail__topic-collapse-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
    padding: 4px 12px;
}

.curriculum-detail__topic-collapse-btn {
    border: 1px solid rgba(129, 140, 248, 0.38) !important;
    background: linear-gradient(180deg, rgba(224, 231, 255, 0.98), rgba(199, 210, 254, 0.92)) !important;
    color: #4338ca !important;
    box-shadow: 0 8px 18px rgba(99, 102, 241, 0.18);
}

.curriculum-detail__topic-collapse-btn:hover {
    background: linear-gradient(180deg, rgba(199, 210, 254, 1), rgba(165, 180, 252, 0.94)) !important;
    color: #312e81 !important;
}

.curriculum-detail__topic-item > .curriculum-detail__topic-row {
    padding: 12px 14px 10px;
    cursor: pointer;
    background:
        linear-gradient(135deg, var(--topic-tint), transparent 70%),
        rgba(255, 255, 255, 0.6);
    border-bottom: 1px solid hsla(var(--topic-hue, 220), 55%, 45%, 0.28);
    transition: background 0.15s, box-shadow 0.15s;
}

.curriculum-detail__topic-item--selected {
    border-color: hsla(var(--topic-hue, 220), 70%, 45%, 0.6);
    box-shadow:
        0 0 0 2px hsla(var(--topic-hue, 220), 70%, 55%, 0.35),
        0 6px 14px rgba(15, 23, 42, 0.08);
}

.curriculum-detail__topic-item--selected > .curriculum-detail__topic-row {
    background:
        linear-gradient(135deg, hsla(var(--topic-hue, 220), 80%, 55%, 0.22), hsla(var(--topic-hue, 220), 80%, 55%, 0.08) 70%),
        rgba(255, 255, 255, 0.6);
}

.curriculum-detail__topic-item > .curriculum-detail__topic-row .curriculum-detail__topic-title {
    color: var(--topic-accent);
}

.curriculum-detail__topic-item > .curriculum-detail__unit-section {
    padding: 12px 14px 14px;
    border-top: none;
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
    color: #0f172a;
    line-height: 1.35;
}

.curriculum-detail__topic-collapsed-count {
    font-size: 0.9rem;
    font-weight: 700;
    color: #475569;
    white-space: nowrap;
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
    color: #475569;
}

.curriculum-detail__attached-materials {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 10px;
}

.curriculum-detail__attached-materials--unit {
    margin-top: 8px;
}

.curriculum-detail__attached-material {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 10px;
    border: 1px solid rgba(99, 102, 241, 0.16);
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.08), transparent 60%),
        linear-gradient(180deg, rgba(238, 242, 255, 0.94), rgba(224, 231, 255, 0.82));
}

.curriculum-detail__attached-material-copy {
    min-width: 0;
    flex: 1;
}

.curriculum-detail__attached-material-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.curriculum-detail__attached-material-preview-btn {
    white-space: nowrap;
}

.curriculum-detail__attached-material-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #312e81;
}

.curriculum-detail__attached-material-subtitle {
    font-size: 0.72rem;
    color: #475569;
    margin-top: 2px;
}

.curriculum-detail__topic-actions {
    display: flex;
    align-items: center;
    gap: 2px;
    flex-shrink: 0;
}

.curriculum-detail__topic-header-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
    flex-shrink: 0;
}

.curriculum-detail__unit-section {
    border-top: 1px solid rgba(15, 23, 42, 0.1);
    padding-top: 10px;
}

.curriculum-detail__unit-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.curriculum-detail__unit-summary {
    display: flex;
    align-items: center;
    gap: 8px;
}

.curriculum-detail__unit-count {
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
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
    border: 1px solid rgba(99, 102, 241, 0.18);
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.1), transparent 60%),
        linear-gradient(180deg, rgba(238, 242, 255, 0.95), rgba(224, 231, 255, 0.85));
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
}

.curriculum-detail__unit-item > .curriculum-detail__topic-row {
    transition: background 0.15s, box-shadow 0.15s;
    border-radius: 8px;
}

.curriculum-detail__unit-item--selected {
    border-color: rgba(129, 140, 248, 0.52);
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.18), transparent 60%),
        linear-gradient(180deg, rgba(224, 231, 255, 0.98), rgba(199, 210, 254, 0.94));
    box-shadow:
        0 0 0 1px rgba(165, 180, 252, 0.16),
        0 0 18px rgba(99, 102, 241, 0.16);
}

.curriculum-detail__unit-title {
    font-size: 0.88rem;
    font-weight: 500;
    color: #1e293b;
    line-height: 1.35;
}

.curriculum-detail__unit-title-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.curriculum-detail__unit-exam-chip {
    font-weight: 700;
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

.curriculum-detail__topic-assignment-options :deep(.v-btn) {
    color: #cbd5e1 !important;
}

.curriculum-detail__topic-assignment-options :deep(.v-btn--variant-tonal) {
    background: rgba(148, 163, 184, 0.14) !important;
    border: 1px solid rgba(148, 163, 184, 0.16) !important;
}

.curriculum-detail__topic-assignment-options :deep(.v-btn--variant-flat) {
    color: #eff6ff !important;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.92), rgba(99, 102, 241, 0.82)) !important;
    border: 1px solid rgba(199, 210, 254, 0.42) !important;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.18),
        0 0 0 1px rgba(99, 102, 241, 0.26),
        0 8px 18px rgba(79, 70, 229, 0.28);
}

.curriculum-detail__topic-assignment-options :deep(.v-btn--variant-text) {
    color: #e2e8f0 !important;
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

.curriculum-detail__assignment-week-controls :deep(.v-btn) {
    color: #cbd5e1 !important;
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
    color: #475569;
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
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.1), transparent 60%),
        linear-gradient(180deg, rgba(238, 242, 255, 0.95), rgba(224, 231, 255, 0.85));
    border: 1px solid rgba(99, 102, 241, 0.18);
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
    color: #0f172a;
    font-weight: 500;
}

.lehrplaene__item-subtitle {
    font-size: 0.72rem;
    color: #475569;
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
    border: 1px solid rgba(15, 23, 42, 0.12);
    background: rgba(255, 255, 255, 0.84);
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
}

.lehrplaene__preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 10px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.1);
    font-size: 0.78rem;
    font-weight: 600;
    color: #4338ca;
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
    .curriculum-detail__title-row {
        flex-direction: column;
    }

    .curriculum-detail__meta {
        justify-content: flex-start;
    }

    .curriculum-detail__week-view {
        align-items: flex-start;
        justify-content: flex-start;
        flex-direction: column;
    }

    .curriculum-detail__body {
        grid-template-columns: 1fr;
        width: auto;
    }

    .curriculum-detail__calendar,
    .curriculum-detail__calendar--compact {
        width: 100%;
    }

    .curriculum-detail__side-card--documents {
        min-width: 0;
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
    .curriculum-detail__topic-assignment-options,
    .curriculum-detail__material-dialog-toolbar {
        flex-direction: column;
    }

    .curriculum-detail__material-filter-grid {
        grid-template-columns: 1fr;
    }

    .curriculum-detail__material-selection-layout {
        grid-template-columns: 1fr;
    }

    .curriculum-detail__material-browser-reset {
        margin-left: 0;
        align-self: flex-start;
    }
}
</style>
