<template>
    <v-sheet rounded="xl" class="curricula-settings pa-6">
        <div class="curricula-settings__header d-flex align-center ga-3 mb-4">
            <v-icon size="28" color="primary">mdi-cog-outline</v-icon>
            <h3 class="curricula-settings__title">Einstellungen</h3>
            <v-spacer />
            <v-btn
                v-if="curriculum"
                icon="mdi-close"
                variant="text"
                rounded="lg"
                density="comfortable"
                @click="$emit('back')" />
        </div>

        <p class="curricula-settings__subtitle mb-4">
            Lege Vorlagen für freie Wochen fest
            <template v-if="curriculum">
                für <span class="curricula-settings__curriculum-title">{{ curriculum.title }}</span>
            </template>:
        </p>

        <template v-if="selectedPanel === null">
            <v-row>
                <v-col cols="12" md="8">
                    <button
                        type="button"
                        class="curricula-settings__entry curricula-settings__entry--primary curricula-settings__entry--compact"
                        @click="openPanel('free-weeks')">
                        <div class="curricula-settings__entry-icon">
                            <v-icon size="28" color="primary">mdi-calendar-range-outline</v-icon>
                        </div>
                        <div class="curricula-settings__entry-content">
                            <div class="curricula-settings__entry-topline">
                                <h4 class="curricula-settings__option-title">Freie Wochen</h4>
                                <v-chip size="small" color="primary" variant="tonal">Vorlage</v-chip>
                            </div>
                            <p class="curricula-settings__option-desc">
                                Wochen auswählen, Ferienbereiche benennen und als allgemeine Vorlage speichern.
                            </p>
                            <div class="curricula-settings__entry-stats">
                                <v-chip size="small" color="success" variant="tonal" class="font-weight-bold">
                                    Vorlage: {{ templateFreeWeeksLabel }}
                                </v-chip>
                                <v-chip
                                    v-if="curriculum"
                                    size="small"
                                    color="primary"
                                    variant="tonal"
                                    class="font-weight-bold">
                                    Curriculum: {{ curriculumFreeWeeksLabel }}
                                </v-chip>
                                <v-chip size="small" color="secondary" variant="tonal">
                                    {{ namedRangeCount }} Titel
                                </v-chip>
                            </div>
                        </div>
                    </button>
                </v-col>
            </v-row>
        </template>

        <template v-else-if="selectedPanel === 'free-weeks'">
            <div class="curricula-settings__detail-toolbar d-flex align-center ga-3 mb-4">
                <v-btn
                    color="primary"
                    variant="flat"
                    rounded="xl"
                    prepend-icon="mdi-arrow-left"
                    class="curricula-settings__back-btn text-none"
                    density="comfortable"
                    @click="closePanel">
                    Zurück zum Menü
                </v-btn>
                <div>
                    <div class="curricula-settings__detail-title">Freie Wochen</div>
                    <div class="curricula-settings__detail-subtitle">
                        September bis Juli. Titel gelten pro zusammenhängendem Wochenblock.
                    </div>
                </div>
                <v-spacer />
                <v-btn
                    color="primary"
                    variant="flat"
                    rounded="xl"
                    prepend-icon="mdi-content-save-outline"
                    :loading="isSavingTemplate"
                    @click="saveTemplate">
                    Template speichern
                </v-btn>
                <v-btn
                    v-if="curriculum"
                    color="success"
                    variant="tonal"
                    rounded="xl"
                    prepend-icon="mdi-book-arrow-right-outline"
                    :loading="isApplyingTemplate"
                    @click="applyTemplateToCurriculum">
                    Auf Curriculum übernehmen
                </v-btn>
            </div>

            <div v-if="isLoadingTemplate" class="curricula-settings__loading text-center py-8">
                <v-progress-circular indeterminate color="primary" size="28" class="mb-2" />
                <div>Vorlage wird geladen...</div>
            </div>

            <template v-else>
                <div class="curricula-settings__detail-summary">
                    <v-chip size="small" color="success" variant="tonal" class="font-weight-bold">
                        Vorlage: {{ templateFreeWeeksLabel }}
                    </v-chip>
                    <v-chip
                        v-if="curriculum"
                        size="small"
                        color="primary"
                        variant="tonal"
                        class="font-weight-bold">
                        Curriculum: {{ curriculumFreeWeeksLabel }}
                    </v-chip>
                    <v-chip size="small" color="secondary" variant="tonal">
                        {{ selectedWeekGroups.length }} Zeiträume
                    </v-chip>
                </div>

                <div class="curricula-settings__month-grid curricula-settings__month-grid--compact">
                    <div
                        v-for="month in allMonths"
                        :key="month.key"
                        class="curricula-settings__month curricula-settings__month--compact">
                        <div class="curricula-settings__month-header">
                            <div class="curricula-settings__month-title">{{ month.name }}</div>
                            <div class="curricula-settings__month-week-meta">
                                <div
                                    class="curricula-settings__month-week-count"
                                    :class="{ 'curricula-settings__month-week-count--overassigned': monthTeachingWeekCountIsOverassigned(month) }">
                                    <span
                                        v-if="monthTeachingWeekCountIsOverassigned(month)"
                                        class="curricula-settings__month-week-warning">!</span>
                                    {{ monthTeachingWeekCountLabel(month) }}
                                </div>
                            </div>
                        </div>

                        <button
                            v-for="week in month.weeks"
                            :key="week.weekKey"
                            type="button"
                            class="curricula-settings__week curricula-settings__week--compact"
                            :class="{
                                'curricula-settings__week--selected': isTemplateWeek(week.weekKey),
                                'curricula-settings__week--titled': Boolean(weekTitleLookup[week.weekKey]),
                                'curricula-settings__week--range-selected': Boolean(selectedRangeWeekLookup[week.weekKey]),
                            }"
                            @click="toggleTemplateWeek(week.weekKey)">
                            <span class="curricula-settings__week-main">
                                <span class="curricula-settings__week-kw">KW {{ week.kw }}</span>
                                <span class="curricula-settings__week-range">{{ week.rangeLabel }}</span>
                            </span>
                            <span
                                v-if="weekTitleLookup[week.weekKey]"
                                class="curricula-settings__week-title">
                                <v-icon size="12" class="curricula-settings__week-title-icon">mdi-label-outline</v-icon>
                                {{ weekTitleLookup[week.weekKey] }}
                            </span>
                        </button>

                        <div v-if="!month.weeks.length" class="curricula-settings__month-empty">
                            Keine Wochen gefunden
                        </div>
                    </div>
                </div>

                <v-sheet rounded="xl" class="curricula-settings__ranges curricula-settings__ranges--compact mt-3 pa-3">
                    <div class="curricula-settings__ranges-title">Titel für freie Zeiträume</div>
                    <p class="curricula-settings__ranges-subtitle">
                        Direkt aufeinanderfolgende freie Wochen werden zu einem Zeitraum zusammengefasst.
                    </p>

                    <div v-if="!selectedWeekGroups.length" class="curricula-settings__ranges-empty">
                        Noch keine freien Wochen ausgewählt.
                    </div>

                    <div v-else class="curricula-settings__range-list">
                        <div
                            v-for="group in selectedWeekGroups"
                            :key="group.key"
                            class="curricula-settings__range-item"
                            :class="{ 'curricula-settings__range-item--selected': selectedRangeKey === group.key }"
                            @click="toggleSelectedRange(group.key)">
                            <div class="curricula-settings__range-meta">
                                <div class="curricula-settings__range-headline">
                                    <div class="curricula-settings__range-label">{{ groupSummaryLabel(group) }}</div>
                                    <div
                                        class="curricula-settings__range-title-pill"
                                        :class="{ 'curricula-settings__range-title-pill--empty': !rangeTitleDrafts[group.key] }">
                                        {{ rangeTitleDrafts[group.key] || 'Noch ohne Titel' }}
                                    </div>
                                </div>
                                <div class="curricula-settings__range-weeks">
                                    {{ group.week_keys.length }} Woche{{ group.week_keys.length === 1 ? '' : 'n' }}
                                </div>
                                <div class="curricula-settings__range-preview">
                                    <span
                                        v-for="weekKey in group.week_keys"
                                        :key="weekKey"
                                        class="curricula-settings__range-preview-chip">
                                        {{ compactWeekLabel(weekKey) }}
                                    </span>
                                </div>
                            </div>
                            <div class="curricula-settings__range-actions" @click.stop>
                                <template v-if="activeRangeEditor === group.key">
                                    <v-text-field
                                        :model-value="rangeTitleDrafts[group.key] || ''"
                                        label="Titel"
                                        density="compact"
                                        variant="outlined"
                                        hide-details
                                        placeholder="z. B. Sommerferien"
                                        class="curricula-settings__range-input"
                                        @update:modelValue="updateRangeTitle(group.key, $event)" />
                                    <v-btn
                                        icon="mdi-check"
                                        color="primary"
                                        variant="tonal"
                                        size="small"
                                        @click="closeRangeEditor" />
                                </template>
                                <template v-else>
                                    <v-btn
                                        icon="mdi-pencil-outline"
                                        color="secondary"
                                        variant="text"
                                        size="small"
                                        @click="openRangeEditor(group.key)" />
                                </template>
                            </div>
                        </div>
                    </div>
                </v-sheet>
            </template>
        </template>
    </v-sheet>
</template>

<script>
import { useCurriculumStore } from '@/stores/admin/teaching/CurriculumStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

const MONTH_NAMES = [
    'Januar',
    'Februar',
    'März',
    'April',
    'Mai',
    'Juni',
    'Juli',
    'August',
    'September',
    'Oktober',
    'November',
    'Dezember',
]

function resolveSelectedSchoolyearStartYear(adminStore) {
    const rawStart = adminStore.config?.selected_schoolyear?.from
    const startDate = typeof rawStart === 'string' ? new Date(`${rawStart}T00:00:00`) : null

    if (startDate && !Number.isNaN(startDate.getTime())) {
        return startDate.getFullYear()
    }

    return new Date().getFullYear()
}

export default {
    name: 'CurriculaSettings',
    emits: ['back', 'updated'],
    props: {
        curriculum: { type: Object, default: null },
    },
    data() {
        const adminStore = useAdminStore()

        return {
            curriculumStore: useCurriculumStore(),
            adminStore,
            selectedPanel: null,
            selectedYear: resolveSelectedSchoolyearStartYear(adminStore),
            templateWeekKeys: [],
            rangeTitleDrafts: {},
            activeRangeEditor: null,
            selectedRangeKey: null,
            isLoadingTemplate: false,
            isSavingTemplate: false,
            isApplyingTemplate: false,
        }
    },
    computed: {
        allMonths() {
            const today = new Date()
            today.setHours(0, 0, 0, 0)

            return [
                { month: 8, year: this.selectedYear },
                { month: 9, year: this.selectedYear },
                { month: 10, year: this.selectedYear },
                { month: 11, year: this.selectedYear },
                { month: 0, year: this.selectedYear + 1 },
                { month: 1, year: this.selectedYear + 1 },
                { month: 2, year: this.selectedYear + 1 },
                { month: 3, year: this.selectedYear + 1 },
                { month: 4, year: this.selectedYear + 1 },
                { month: 5, year: this.selectedYear + 1 },
                { month: 6, year: this.selectedYear + 1 },
            ].map((entry) => ({
                key: `${entry.year}-${entry.month}`,
                name: MONTH_NAMES[entry.month],
                year: entry.year,
                month: entry.month,
                weeks: this.buildWeeks(entry.year, entry.month, today),
            }))
        },
        selectedWeekGroups() {
            if (!this.templateWeekKeys.length) {
                return []
            }

            const sortedWeekKeys = [...this.templateWeekKeys].sort()
            const groups = []
            let currentGroup = null

            sortedWeekKeys.forEach((weekKey) => {
                const weekDate = this.parseWeekKey(weekKey)
                if (!weekDate) {
                    return
                }

                if (!currentGroup) {
                    currentGroup = {
                        start_week_key: weekKey,
                        end_week_key: weekKey,
                        week_keys: [weekKey],
                    }
                    return
                }

                const previousDate = this.parseWeekKey(currentGroup.end_week_key)
                const dayDifference = previousDate
                    ? Math.round((weekDate.getTime() - previousDate.getTime()) / 86400000)
                    : 0

                if (dayDifference === 7) {
                    currentGroup.end_week_key = weekKey
                    currentGroup.week_keys.push(weekKey)
                    return
                }

                groups.push(this.finalizeWeekGroup(currentGroup))
                currentGroup = {
                    start_week_key: weekKey,
                    end_week_key: weekKey,
                    week_keys: [weekKey],
                }
            })

            if (currentGroup) {
                groups.push(this.finalizeWeekGroup(currentGroup))
            }

            return groups
        },
        namedRangeCount() {
            return this.selectedWeekGroups.filter((group) => String(this.rangeTitleDrafts[group.key] || '').trim() !== '').length
        },
        templateFreeWeeksLabel() {
            return this.freeWeeksLabel(this.templateWeekKeys.length)
        },
        curriculumFreeWeekKeys() {
            return [...new Set(
                (Array.isArray(this.curriculum?.free_weeks) ? this.curriculum.free_weeks : [])
                    .filter(Boolean)
                    .map((weekKey) => this.normalizeWeekAssignmentKey(weekKey))
                    .filter(Boolean)
            )].sort()
        },
        curriculumFreeWeeksLabel() {
            return this.freeWeeksLabel(this.curriculumFreeWeekKeys.length)
        },
        weekTitleLookup() {
            return Object.fromEntries(
                this.selectedWeekGroups.flatMap((group) => {
                    const title = String(this.rangeTitleDrafts[group.key] || '').trim()

                    if (title === '') {
                        return []
                    }

                    return group.week_keys.map((weekKey) => [weekKey, title])
                })
            )
        },
        selectedRangeWeekLookup() {
            const selectedGroup = this.selectedWeekGroups.find((group) => group.key === this.selectedRangeKey)

            if (!selectedGroup) {
                return {}
            }

            return Object.fromEntries(selectedGroup.week_keys.map((weekKey) => [weekKey, true]))
        },
    },
    watch: {
        async selectedYear(nextYear, previousYear) {
            if (nextYear === previousYear) {
                return
            }

            this.remapTemplateStateForSelectedYear()
        },
    },
    async beforeMount() {
        await this.loadTemplate()
    },
    methods: {
        openPanel(panelKey) {
            this.selectedPanel = panelKey
        },
        closePanel() {
            this.selectedPanel = null
        },
        async loadTemplate() {
            this.isLoadingTemplate = true

            try {
                const template = await this.curriculumStore.loadFreeWeeksTemplate()
                this.setTemplateStateFromPayload(template)
            } finally {
                this.isLoadingTemplate = false
            }
        },
        async saveTemplate() {
            this.isSavingTemplate = true

            try {
                const template = await this.curriculumStore.saveFreeWeeksTemplate(this.templatePayload())
                this.setTemplateStateFromPayload(template)
            } finally {
                this.isSavingTemplate = false
            }
        },
        async applyTemplateToCurriculum() {
            if (!this.curriculum) {
                return
            }

            this.isApplyingTemplate = true

            try {
                const updatedCurriculum = await this.curriculumStore.update(this.curriculum.id, {
                    title: this.curriculum.title || '',
                    description: this.curriculum.description || null,
                    semester_count: this.curriculum.semester_count ?? 2,
                    free_weeks: [...this.templateWeekKeys],
                    topics: Array.isArray(this.curriculum.topics) ? this.curriculum.topics : [],
                })

                if (updatedCurriculum) {
                    this.$emit('updated', updatedCurriculum)
                }
            } finally {
                this.isApplyingTemplate = false
            }
        },
        templatePayload() {
            return {
                week_keys: [...this.templateWeekKeys],
                named_ranges: this.selectedWeekGroups
                    .map((group) => ({
                        title: String(this.rangeTitleDrafts[group.key] || '').trim(),
                        start_week_key: group.start_week_key,
                        end_week_key: group.end_week_key,
                    }))
                    .filter((group) => group.title !== ''),
            }
        },
        setTemplateStateFromPayload(template) {
            const weekKeys = [...new Set(
                (Array.isArray(template?.week_keys) ? template.week_keys : [])
                    .filter(Boolean)
                    .map((weekKey) => this.normalizeWeekAssignmentKey(weekKey))
                    .filter(Boolean)
            )].sort()
            const weekLookup = new Set(weekKeys)

            this.templateWeekKeys = weekKeys
            this.rangeTitleDrafts = Object.fromEntries(
                (Array.isArray(template?.named_ranges) ? template.named_ranges : [])
                    .map((range) => {
                        const title = String(range.title || '').trim()
                        const normalizedStartWeekKey = this.normalizeWeekAssignmentKey(range.start_week_key)
                        const normalizedEndWeekKey = this.normalizeWeekAssignmentKey(range.end_week_key)

                        if (title === ''
                            || !normalizedStartWeekKey
                            || !normalizedEndWeekKey
                            || !weekLookup.has(normalizedStartWeekKey)
                            || !weekLookup.has(normalizedEndWeekKey)) {
                            return null
                        }

                        const [startWeekKey, endWeekKey] = normalizedStartWeekKey <= normalizedEndWeekKey
                            ? [normalizedStartWeekKey, normalizedEndWeekKey]
                            : [normalizedEndWeekKey, normalizedStartWeekKey]

                        return [
                            this.rangeKey(startWeekKey, endWeekKey),
                            title,
                        ]
                    })
                    .filter(Boolean)
            )
            this.activeRangeEditor = null
            this.selectedRangeKey = null
        },
        remapTemplateStateForSelectedYear() {
            this.setTemplateStateFromPayload({
                week_keys: [...this.templateWeekKeys],
                named_ranges: this.selectedWeekGroups
                    .map((group) => ({
                        title: String(this.rangeTitleDrafts[group.key] || '').trim(),
                        start_week_key: group.start_week_key,
                        end_week_key: group.end_week_key,
                    }))
                    .filter((group) => group.title !== ''),
            })
        },
        toggleTemplateWeek(weekKey) {
            this.templateWeekKeys = this.isTemplateWeek(weekKey)
                ? this.templateWeekKeys.filter((value) => value !== weekKey)
                : [...this.templateWeekKeys, weekKey].sort()
        },
        isTemplateWeek(weekKey) {
            return this.templateWeekKeys.includes(weekKey)
        },
        monthTeachingWeekCount(month = null) {
            return this.monthTeachingWeeks(month).length
        },
        monthTeachingWeekCountLabel(month = null) {
            const weekCount = this.monthTeachingWeekCount(month)

            if (this.curriculum) {
                const assignedWeekCount = this.monthAssignedTeachingWeekCount(month)

                return weekCount === 1 ? `${assignedWeekCount}/1 Woche` : `${assignedWeekCount}/${weekCount} Wochen`
            }

            return weekCount === 1 ? '1 Woche' : `${weekCount} Wochen`
        },
        monthTeachingWeekCountIsOverassigned(month = null) {
            return Boolean(this.curriculum) && this.monthAssignedTeachingWeekCount(month) > this.monthTeachingWeekCount(month)
        },
        monthTeachingWeeks(month = null) {
            if (!Array.isArray(month?.weeks)) {
                return []
            }

            return month.weeks.filter((week) => (
                week?.weekKey
                && !this.isTemplateFreeTeachingWeek(week)
                && this.weekBelongsToMonth(week, month)
            ))
        },
        monthAssignedTeachingWeekCount(month = null) {
            const monthKey = this.normalizeMonthAssignmentKey(month?.assignmentKey)
            const teachingWeekKeys = this.monthTeachingWeeks(month)
                .map((week) => String(week?.weekKey || '').trim())
                .filter(Boolean)
            const teachingWeekKeySet = new Set(teachingWeekKeys)
            const topics = Array.isArray(this.curriculum?.topics) ? this.curriculum.topics : []
            const assignedWeekKeys = new Set()
            let assignedWeekCount = 0

            if (!monthKey || teachingWeekKeys.length === 0) {
                return 0
            }

            const addAssignmentUsage = (entry = null) => {
                if (!this.assignmentEntryHasTitle(entry)) {
                    return
                }

                if (entry.assignment_type === 'weeks') {
                    this.assignmentEntryWeekKeys(entry).forEach((weekKey) => {
                        if (teachingWeekKeySet.has(weekKey)) {
                            assignedWeekKeys.add(weekKey)
                        }
                    })

                    return
                }

                assignedWeekCount += this.assignmentEntryWeekUsageForMonth(entry, monthKey, teachingWeekKeys.length)
            }

            topics.forEach((topic) => {
                const units = Array.isArray(topic?.units) ? topic.units : []

                if (!this.topicHasUnitAssignmentUsageForMonth(topic, monthKey, teachingWeekKeySet, teachingWeekKeys.length)) {
                    addAssignmentUsage(topic)
                }

                units.forEach((unit) => {
                    addAssignmentUsage(unit)
                })
            })

            return assignedWeekCount + assignedWeekKeys.size
        },
        assignmentEntryWeekUsageForMonth(entry = null, monthKey = null, totalWeekCount = 0) {
            const assignmentType = String(entry?.assignment_type || 'none')

            if (assignmentType === 'all_weeks') {
                return 0
            }

            if (assignmentType !== 'month' || !this.assignmentEntryMonthKeys(entry).includes(monthKey)) {
                return 0
            }

            const monthWeekCount = this.assignmentEntryMonthWeekCount(entry, monthKey)

            if (monthWeekCount === 0) {
                return totalWeekCount
            }

            return Math.min(monthWeekCount, totalWeekCount)
        },
        assignmentEntryMonthWeekCount(entry = null, monthKey = null) {
            const normalizedMonthKey = this.normalizeMonthAssignmentKey(monthKey)
            const monthWeekCounts = entry?.month_week_counts && typeof entry.month_week_counts === 'object'
                ? entry.month_week_counts
                : {}
            const assignedWeekCount = Number.parseInt(monthWeekCounts[normalizedMonthKey] ?? 1, 10)

            return Number.isFinite(assignedWeekCount) && assignedWeekCount >= 0 && assignedWeekCount <= 4
                ? assignedWeekCount
                : 1
        },
        topicHasUnitAssignmentUsageForMonth(topic = null, monthKey = null, teachingWeekKeySet = new Set(), totalWeekCount = 0) {
            return (Array.isArray(topic?.units) ? topic.units : []).some((unit) => (
                this.assignmentEntryHasUsageForMonth(unit, monthKey, teachingWeekKeySet, totalWeekCount)
            ))
        },
        assignmentEntryHasUsageForMonth(entry = null, monthKey = null, teachingWeekKeySet = new Set(), totalWeekCount = 0) {
            if (!this.assignmentEntryHasTitle(entry)) {
                return false
            }

            if (entry.assignment_type === 'weeks') {
                return this.assignmentEntryWeekKeys(entry).some((weekKey) => teachingWeekKeySet.has(weekKey))
            }

            return this.assignmentEntryWeekUsageForMonth(entry, monthKey, totalWeekCount) > 0
        },
        weekHasCurriculumAssignmentsForMonth(week = null, month = null) {
            const weekKey = String(week?.weekKey || '').trim()
            const monthKey = String(month?.assignmentKey || '').trim()
            const topics = Array.isArray(this.curriculum?.topics) ? this.curriculum.topics : []

            if (!weekKey || !monthKey) {
                return false
            }

            return topics.some((topic) => {
                if (!this.assignmentEntryHasTitle(topic)) {
                    return false
                }

                if (this.assignmentEntryMatchesWeek(topic, weekKey, monthKey)) {
                    return true
                }

                return (Array.isArray(topic?.units) ? topic.units : [])
                    .some((unit) => this.assignmentEntryHasTitle(unit) && this.assignmentEntryMatchesWeek(unit, weekKey, monthKey))
            })
        },
        assignmentEntryMatchesWeek(entry = null, weekKey = null, monthKey = null) {
            const assignmentType = String(entry?.assignment_type || 'none')

            return assignmentType === 'all_weeks'
                || (assignmentType === 'month' && this.assignmentEntryMonthKeys(entry).includes(monthKey))
                || (assignmentType === 'weeks' && this.assignmentEntryWeekKeys(entry).includes(weekKey))
        },
        assignmentEntryMonthKeys(entry = null) {
            return [...new Set(
                (Array.isArray(entry?.month_keys) ? entry.month_keys : [entry?.month_key])
                    .filter(Boolean)
                    .map((monthKey) => this.normalizeMonthAssignmentKey(monthKey))
                    .filter(Boolean)
            )].sort()
        },
        assignmentEntryWeekKeys(entry = null) {
            return [...new Set(
                (Array.isArray(entry?.week_keys) ? entry.week_keys : [])
                    .filter(Boolean)
                    .map((weekKey) => this.normalizeWeekAssignmentKey(weekKey))
                    .filter(Boolean)
            )].sort()
        },
        assignmentEntryHasTitle(entry = null) {
            return String(entry?.title || '').trim() !== ''
        },
        isTemplateFreeTeachingWeek(week = null) {
            return this.weekdayKeysForWeek(week).some((weekDayKey) => this.isMonthCountFreeWeek(weekDayKey))
        },
        isMonthCountFreeWeek(weekKey) {
            return this.monthCountFreeWeekKeys().includes(weekKey)
        },
        monthCountFreeWeekKeys() {
            const curriculumWeekKeys = Array.isArray(this.curriculum?.free_weeks)
                ? this.curriculum.free_weeks
                : []

            return [...new Set(
                [...this.templateWeekKeys, ...curriculumWeekKeys]
                    .filter(Boolean)
                    .map((weekKey) => this.normalizeWeekAssignmentKey(weekKey))
                    .filter(Boolean)
            )].sort()
        },
        weekdayKeysForWeek(week = null) {
            const weekStart = this.parseWeekKey(week?.weekKey)

            if (!weekStart) {
                return []
            }

            return Array.from({ length: 5 }, (_, dayOffset) => {
                const date = new Date(weekStart)
                date.setDate(date.getDate() + dayOffset)

                return this.formatDateKey(date)
            })
        },
        weekBelongsToMonth(week = null, month = null) {
            const monthIndex = Number.parseInt(month?.month, 10)
            const year = Number.parseInt(month?.year, 10)
            const weekStart = this.parseWeekKey(week?.weekKey)

            if (!Number.isInteger(monthIndex) || !Number.isInteger(year) || !weekStart) {
                return false
            }

            let matchingWeekdays = 0

            for (let dayOffset = 0; dayOffset < 5; dayOffset++) {
                const date = new Date(weekStart)
                date.setDate(date.getDate() + dayOffset)

                if (date.getMonth() === monthIndex && date.getFullYear() === year) {
                    matchingWeekdays++
                }
            }

            return matchingWeekdays >= 3
        },
        updateRangeTitle(groupKey, value) {
            this.rangeTitleDrafts = {
                ...this.rangeTitleDrafts,
                [groupKey]: String(value || ''),
            }
        },
        openRangeEditor(groupKey) {
            this.activeRangeEditor = groupKey
        },
        closeRangeEditor() {
            this.activeRangeEditor = null
        },
        toggleSelectedRange(groupKey) {
            this.selectedRangeKey = this.selectedRangeKey === groupKey ? null : groupKey
        },
        rangeKey(startWeekKey, endWeekKey) {
            return `${startWeekKey}:${endWeekKey}`
        },
        finalizeWeekGroup(group) {
            return {
                ...group,
                key: this.rangeKey(group.start_week_key, group.end_week_key),
            }
        },
        groupSummaryLabel(group) {
            if (group.week_keys.length === 1) {
                return this.weekLabel(group.start_week_key)
            }

            return `${this.weekLabel(group.start_week_key)} bis ${this.weekLabel(group.end_week_key)}`
        },
        compactWeekLabel(weekKey) {
            const weekStart = this.parseWeekKey(weekKey)
            if (!weekStart) {
                return weekKey
            }

            return `KW ${this.getISOWeek(weekStart)}`
        },
        weekLabel(weekKey) {
            const weekStart = this.parseWeekKey(weekKey)
            if (!weekStart) {
                return weekKey
            }

            const friday = new Date(weekStart)
            friday.setDate(friday.getDate() + 4)

            return `KW ${this.getISOWeek(weekStart)} · ${weekStart.getDate()}.${weekStart.getMonth() + 1}.–${friday.getDate()}.${friday.getMonth() + 1}.`
        },
        freeWeeksLabel(weekCount = 0) {
            const normalizedWeekCount = Number.parseInt(weekCount, 10)

            return normalizedWeekCount === 1 ? '1 freie Woche' : `${normalizedWeekCount} freie Wochen`
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
            const weekStart = this.parseWeekKey(rawWeekKey)

            if (!weekStart) {
                return rawWeekKey
            }

            return this.weekKeyForSelectedYear(this.getISOWeek(weekStart)) ?? rawWeekKey
        },
        normalizeMonthAssignmentKey(monthKey) {
            const rawMonthKey = String(monthKey).trim()
            const match = rawMonthKey.match(/^(?:\d{4}-)?(\d{2})$/)

            if (!match) {
                return rawMonthKey
            }

            const normalizedMonth = Number.parseInt(match[1], 10)

            if (!Number.isInteger(normalizedMonth) || normalizedMonth < 1 || normalizedMonth > 12) {
                return rawMonthKey
            }

            const year = normalizedMonth >= 9 ? this.selectedYear : this.selectedYear + 1

            return `${year}-${String(normalizedMonth).padStart(2, '0')}`
        },
        buildWeeks(year, monthIndex, today) {
            const firstDayOfMonth = new Date(year, monthIndex, 1)
            const cursor = new Date(firstDayOfMonth)
            const weekStartOffset = (cursor.getDay() + 6) % 7
            cursor.setDate(cursor.getDate() - weekStartOffset)

            const weeks = []

            while (true) {
                const weekStart = new Date(cursor)
                const friday = new Date(weekStart)
                friday.setDate(friday.getDate() + 4)

                let weekHasMonthDay = false
                for (let dayOffset = 0; dayOffset < 7; dayOffset++) {
                    const date = new Date(weekStart)
                    date.setDate(date.getDate() + dayOffset)
                    if (date.getMonth() === monthIndex) {
                        weekHasMonthDay = true
                        break
                    }
                }

                if (!weekHasMonthDay) {
                    break
                }

                weeks.push({
                    kw: this.getISOWeek(weekStart),
                    weekKey: this.formatDateKey(weekStart),
                    isCurrent: today >= weekStart && today <= friday,
                    rangeLabel: `${weekStart.getDate()}.${weekStart.getMonth() + 1}. – ${friday.getDate()}.${friday.getMonth() + 1}.`,
                })

                cursor.setDate(cursor.getDate() + 7)
            }

            return weeks
        },
        formatDateKey(date) {
            const year = date.getFullYear()
            const month = String(date.getMonth() + 1).padStart(2, '0')
            const day = String(date.getDate()).padStart(2, '0')

            return `${year}-${month}-${day}`
        },
        parseWeekKey(weekKey) {
            const date = new Date(`${String(weekKey).trim()}T00:00:00`)

            return Number.isNaN(date.getTime()) ? null : date
        },
        getISOWeek(date) {
            const normalizedDate = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
            normalizedDate.setUTCDate(normalizedDate.getUTCDate() + 4 - (normalizedDate.getUTCDay() || 7))
            const yearStart = new Date(Date.UTC(normalizedDate.getUTCFullYear(), 0, 1))

            return Math.ceil((((normalizedDate - yearStart) / 86400000) + 1) / 7)
        },
    },
}
</script>

<style scoped>
.curricula-settings {
    border: 1px solid rgba(99, 102, 241, 0.12);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92));
}

.curricula-settings__title {
    color: #1e293b;
    font-weight: 700;
    font-size: 1.25rem;
}

.curricula-settings__subtitle {
    color: #475569;
    font-size: 0.95rem;
}

.curricula-settings__curriculum-title {
    display: inline-flex;
    max-width: 100%;
    padding: 0.16rem 0.48rem;
    border: 1px solid rgba(37, 99, 235, 0.24);
    border-radius: 999px;
    background: rgba(219, 234, 254, 0.78);
    color: #1d4ed8;
    font-weight: 800;
    line-height: 1.25;
    vertical-align: baseline;
    overflow-wrap: anywhere;
}

.curricula-settings__option,
.curricula-settings__entry,
.curricula-settings__ranges {
    border: 1px solid rgba(99, 102, 241, 0.12);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92));
}

.curricula-settings__entry {
    width: 100%;
    min-height: 100%;
    border-radius: 24px;
    padding: 1.25rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    text-align: left;
    cursor: pointer;
    transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
}

.curricula-settings__entry--compact {
    max-width: 620px;
    min-height: auto;
    padding: 1rem;
    gap: 0.8rem;
}

.curricula-settings__entry:hover {
    transform: translateY(-1px);
    border-color: rgba(37, 99, 235, 0.28);
    box-shadow: 0 10px 24px rgba(37, 99, 235, 0.08);
}

.curricula-settings__entry-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(59, 130, 246, 0.12);
    flex: 0 0 auto;
}

.curricula-settings__entry--compact .curricula-settings__entry-icon {
    width: 2.6rem;
    height: 2.6rem;
    border-radius: 15px;
}

.curricula-settings__entry-content {
    min-width: 0;
    flex: 1 1 auto;
}

.curricula-settings__entry-topline,
.curricula-settings__option-topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.35rem;
}

.curricula-settings__entry-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
}

.curricula-settings__entry--compact .curricula-settings__entry-stats {
    margin-top: 0.75rem;
}

.curricula-settings__option-title {
    color: #1e293b;
    font-weight: 600;
    font-size: 0.95rem;
    margin: 0;
}

.curricula-settings__entry--compact .curricula-settings__option-title {
    font-size: 0.9rem;
}

.curricula-settings__option-desc {
    color: #475569;
    font-size: 0.82rem;
    line-height: 1.4;
    margin: 0;
}

.curricula-settings__entry--compact .curricula-settings__option-desc {
    font-size: 0.76rem;
}

.curricula-settings__detail-title {
    color: #1e293b;
    font-weight: 700;
    font-size: 1rem;
}

.curricula-settings__detail-toolbar {
    flex-wrap: wrap;
}

.curricula-settings__back-btn {
    box-shadow: 0 8px 18px rgba(37, 99, 235, 0.18);
}

.curricula-settings__detail-subtitle {
    color: #64748b;
    font-size: 0.82rem;
}

.curricula-settings__detail-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.curricula-settings__loading,
.curricula-settings__ranges-empty,
.curricula-settings__month-empty {
    color: #64748b;
}

.curricula-settings__month-grid {
    display: grid;
    gap: 1rem;
}

.curricula-settings__month-grid--compact {
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 0.75rem;
}

.curricula-settings__month {
    border: 1px solid rgba(148, 163, 184, 0.24);
    border-radius: 20px;
    padding: 0.9rem;
    background: rgba(255, 255, 255, 0.75);
}

.curricula-settings__month--compact {
    border-radius: 16px;
    padding: 0.7rem;
}

.curricula-settings__month-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.curricula-settings__month-title {
    color: #1e293b;
    font-weight: 700;
    font-size: 0.88rem;
}

.curricula-settings__month-week-meta {
    flex: 0 0 auto;
    margin-left: auto;
    max-width: 58%;
    text-align: right;
}

.curricula-settings__month-week-count {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.12rem 0.42rem;
    border-radius: 999px;
    background: rgba(219, 234, 254, 0.74);
    color: #1d4ed8;
    font-size: 0.68rem;
    font-weight: 800;
    line-height: 1.2;
    white-space: nowrap;
}

.curricula-settings__month-week-count--overassigned {
    background: rgba(254, 226, 226, 0.92);
    color: #b91c1c;
}

.curricula-settings__month-week-warning {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 0.9rem;
    height: 0.9rem;
    border-radius: 999px;
    background: #dc2626;
    color: #fff;
    font-size: 0.62rem;
    font-weight: 900;
    line-height: 1;
}

.curricula-settings__week {
    width: 100%;
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 14px;
    background: rgba(248, 250, 252, 0.88);
    display: flex;
    flex-direction: column;
    align-items: stretch;
    justify-content: center;
    gap: 0.5rem;
    text-align: left;
    transition: all 0.16s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.curricula-settings__week--compact {
    padding: 0.38rem 0.5rem;
    margin-bottom: 0.35rem;
}

.curricula-settings__week:hover {
    border-color: rgba(37, 99, 235, 0.32);
    transform: translateY(-1px);
}

.curricula-settings__week--selected {
    border-color: rgba(22, 163, 74, 0.45);
    background: linear-gradient(135deg, rgba(220, 252, 231, 0.96), rgba(240, 253, 244, 0.94));
    box-shadow: 0 8px 20px rgba(22, 163, 74, 0.12);
}

.curricula-settings__week--titled {
    border-color: rgba(37, 99, 235, 0.28);
}

.curricula-settings__week--range-selected {
    border-color: rgba(37, 99, 235, 0.72);
    background: linear-gradient(135deg, rgba(219, 234, 254, 0.98), rgba(239, 246, 255, 0.96));
    box-shadow:
        0 0 0 3px rgba(191, 219, 254, 0.96),
        0 14px 28px rgba(37, 99, 235, 0.16),
        inset 0 0 0 1px rgba(37, 99, 235, 0.12);
    transform: translateY(-1px) scale(1.01);
}

.curricula-settings__week--range-selected::before {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 4px;
    background: linear-gradient(180deg, rgba(29, 78, 216, 0.98), rgba(59, 130, 246, 0.92));
}

.curricula-settings__week--range-selected .curricula-settings__week-kw {
    color: #1d4ed8;
}

.curricula-settings__week--range-selected .curricula-settings__week-range {
    color: #1e3a8a;
    font-weight: 600;
}

.curricula-settings__week--range-selected .curricula-settings__week-title {
    color: #eff6ff;
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.98), rgba(29, 78, 216, 0.92));
    border-color: rgba(29, 78, 216, 0.5);
}

.curricula-settings__week-main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.curricula-settings__week-kw {
    color: #0f172a;
    font-weight: 700;
    font-size: 0.72rem;
    white-space: nowrap;
}

.curricula-settings__week-range {
    color: #475569;
    font-size: 0.7rem;
}

.curricula-settings__week-title {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    align-self: flex-start;
    max-width: 100%;
    color: #1d4ed8;
    font-size: 0.68rem;
    font-weight: 700;
    line-height: 1.3;
    padding: 0.18rem 0.45rem;
    border-radius: 999px;
    background: rgba(219, 234, 254, 0.96);
    border: 1px solid rgba(96, 165, 250, 0.32);
}

.curricula-settings__week-title-icon {
    flex: 0 0 auto;
}

.curricula-settings__ranges-title {
    color: #1e293b;
    font-weight: 700;
    font-size: 0.8rem;
}

.curricula-settings__ranges-subtitle {
    color: #64748b;
    font-size: 0.68rem;
    margin: 0.12rem 0 0.45rem;
}

.curricula-settings__range-list {
    display: grid;
    gap: 0.4rem;
}

.curricula-settings__range-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.45rem;
    align-items: center;
    padding: 0.5rem 0.65rem;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: rgba(255, 255, 255, 0.78);
    cursor: pointer;
    transition: border-color 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
}

.curricula-settings__range-item:hover {
    border-color: rgba(37, 99, 235, 0.24);
}

.curricula-settings__range-item--selected {
    border-color: rgba(37, 99, 235, 0.38);
    background: linear-gradient(180deg, rgba(239, 246, 255, 0.96), rgba(255, 255, 255, 0.92));
    box-shadow:
        0 0 0 2px rgba(219, 234, 254, 0.92),
        0 10px 22px rgba(37, 99, 235, 0.08);
}

.curricula-settings__range-meta {
    min-width: 0;
}

.curricula-settings__range-headline {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.55rem;
}

.curricula-settings__range-label {
    color: #1e293b;
    font-size: 0.78rem;
    font-weight: 600;
}

.curricula-settings__range-title-pill {
    flex: 0 0 auto;
    max-width: 100%;
    color: #1d4ed8;
    font-size: 0.64rem;
    font-weight: 700;
    line-height: 1.3;
    padding: 0.12rem 0.38rem;
    border-radius: 999px;
    background: rgba(219, 234, 254, 0.96);
    border: 1px solid rgba(96, 165, 250, 0.3);
}

.curricula-settings__range-title-pill--empty {
    color: #64748b;
    background: rgba(241, 245, 249, 0.96);
    border-color: rgba(148, 163, 184, 0.24);
}

.curricula-settings__range-weeks {
    color: #64748b;
    font-size: 0.66rem;
    margin-top: 0.08rem;
}

.curricula-settings__range-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 0.22rem;
    margin-top: 0.24rem;
}

.curricula-settings__range-preview-chip {
    color: #334155;
    font-size: 0.58rem;
    font-weight: 700;
    line-height: 1.2;
    padding: 0.1rem 0.28rem;
    border-radius: 999px;
    background: rgba(241, 245, 249, 0.92);
    border: 1px solid rgba(148, 163, 184, 0.22);
}

.curricula-settings__range-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.25rem;
}

.curricula-settings__range-input {
    min-width: 180px;
}

.curricula-settings__ranges--compact {
    max-width: 760px;
}

@media (max-width: 900px) {
    .curricula-settings__range-item {
        grid-template-columns: minmax(0, 1fr);
        align-items: stretch;
    }

    .curricula-settings__range-actions {
        justify-content: flex-start;
    }
}
</style>
