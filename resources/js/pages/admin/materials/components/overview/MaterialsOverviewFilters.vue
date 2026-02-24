<template>
    <div class="material-filters-wrap mb-4">
        <div class="filter-section">
            <div class="text-subtitle-2 mb-2">Fach filtern</div>

            <div class="d-flex flex-wrap ga-2">
                <v-badge class="filter-chip-badge" inline :content="badgeCountContent(subjectAllCount)">
                    <v-chip
                        size="small"
                        :variant="hasActiveSubjectFilter ? 'tonal' : 'flat'"
                        :color="hasActiveSubjectFilter ? undefined : 'primary'"
                        :disabled="actionDisabled"
                        @click="clearSubjectFilter()">
                        Alle
                    </v-chip>
                </v-badge>

                <v-badge
                    v-for="subject in subjectFilterOptions"
                    :key="`subject-filter-${subject}`"
                    class="filter-chip-badge"
                    inline
                    :content="badgeCountContent(subjectFilterCount(subject))">
                    <v-chip
                        size="small"
                        color="primary"
                        :variant="isSubjectFilterActive(subject) ? 'flat' : 'tonal'"
                        :disabled="actionDisabled"
                        @click="toggleSubjectFilter(subject)">
                        {{ subject }}
                    </v-chip>
                </v-badge>
            </div>

            <div v-if="hasActiveSubjectFilter" class="subject-dependent-filters mt-3">
                <div class="subject-dependent-filter">
                    <div class="text-subtitle-2 mb-2">Thema filtern</div>

                    <div class="d-flex flex-wrap ga-2">
                        <v-badge class="filter-chip-badge" inline :content="badgeCountContent(topicAllCount)">
                            <v-chip
                                size="small"
                                :variant="hasActiveTopicFilter ? 'tonal' : 'flat'"
                                :color="hasActiveTopicFilter ? undefined : 'primary'"
                                :disabled="actionDisabled"
                                @click="clearTopicFilter()">
                                Alle
                            </v-chip>
                        </v-badge>

                        <v-badge
                            v-for="topic in topicFilterOptions"
                            :key="`topic-filter-${topic}`"
                            class="filter-chip-badge"
                            inline
                            :content="badgeCountContent(topicFilterCount(topic))">
                            <v-chip
                                size="small"
                                color="primary"
                                :variant="isTopicFilterActive(topic) ? 'flat' : 'tonal'"
                                :disabled="actionDisabled"
                                @click="toggleTopicFilter(topic)">
                                {{ topic }}
                            </v-chip>
                        </v-badge>
                    </div>
                </div>

                <div v-if="hasActiveTopicFilter" class="subject-dependent-filter">
                    <div class="text-subtitle-2 mb-2">Bereich filtern</div>

                    <div class="d-flex flex-wrap ga-2">
                        <v-badge class="filter-chip-badge" inline :content="badgeCountContent(unitAllCount)">
                            <v-chip
                                size="small"
                                :variant="hasActiveUnitFilter ? 'tonal' : 'flat'"
                                :color="hasActiveUnitFilter ? undefined : 'primary'"
                                :disabled="actionDisabled"
                                @click="clearUnitFilter()">
                                Alle
                            </v-chip>
                        </v-badge>

                        <v-badge
                            v-for="unit in unitFilterOptions"
                            :key="`unit-filter-${unit}`"
                            class="filter-chip-badge"
                            inline
                            :content="badgeCountContent(unitFilterCount(unit))">
                            <v-chip
                                size="small"
                                color="primary"
                                :variant="isUnitFilterActive(unit) ? 'flat' : 'tonal'"
                                :disabled="actionDisabled"
                                @click="toggleUnitFilter(unit)">
                                {{ unit }}
                            </v-chip>
                        </v-badge>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-end mb-2">
            <v-tooltip location="top">
                <template #activator="{ props }">
                    <v-btn
                        v-bind="props"
                        :icon="showSecondaryFilters ? 'mdi-filter-variant-minus' : 'mdi-filter-variant-plus'"
                        size="small"
                        variant="text"
                        color="primary"
                        :disabled="actionDisabled"
                        @click="toggleSecondaryFilters()" />
                </template>
                <span>
                    {{ showSecondaryFilters ? 'Materialtyp- und Statusfilter ausblenden' : 'Materialtyp- und Statusfilter einblenden' }}
                </span>
            </v-tooltip>
        </div>

        <template v-if="showSecondaryFilters">
            <div class="filter-section">
                <div class="text-subtitle-2 mb-2">Materialtyp filtern</div>

                <div class="d-flex flex-wrap ga-2">
                    <v-chip
                        size="small"
                        :variant="hasActiveTypeFilter ? 'tonal' : 'flat'"
                        :color="hasActiveTypeFilter ? undefined : 'primary'"
                        :disabled="actionDisabled"
                        @click="clearTypeFilter()">
                        Alle
                    </v-chip>

                    <v-chip
                        v-for="typeOption in typeFilterOptions"
                        :key="`type-filter-${typeOption.value}`"
                        size="small"
                        :color="typeColor(typeOption.value) || 'primary'"
                        :variant="isTypeFilterActive(typeOption.value) ? 'flat' : 'tonal'"
                        :disabled="actionDisabled"
                        @click="toggleTypeFilter(typeOption.value)">
                        {{ typeOption.label }}
                    </v-chip>
                </div>
            </div>

            <div class="filter-section">
                <div class="text-subtitle-2 mb-2">Status filtern</div>

                <div class="d-flex flex-wrap ga-2">
                    <v-chip
                        size="small"
                        :variant="hasActiveStatusFilter ? 'tonal' : 'flat'"
                        :color="hasActiveStatusFilter ? undefined : 'primary'"
                        :disabled="actionDisabled"
                        @click="clearStatusFilter()">
                        Alle
                    </v-chip>

                    <v-chip
                        v-for="statusOption in statusFilterOptions"
                        :key="`status-filter-${statusOption.value}`"
                        size="small"
                        :color="statusColor(statusOption.value)"
                        :variant="isStatusFilterActive(statusOption.value) ? 'flat' : 'tonal'"
                        :disabled="actionDisabled"
                        @click="toggleStatusFilter(statusOption.value)">
                        {{ statusOption.label }}
                    </v-chip>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
const functionProp = {
    type: Function,
    required: true,
}

export default {
    name: 'MaterialsOverviewFilters',
    props: {
        actionDisabled: {
            type: Boolean,
            default: false,
        },
        badgeCountContent: functionProp,
        subjectAllCount: {
            type: Number,
            default: 0,
        },
        hasActiveSubjectFilter: {
            type: Boolean,
            default: false,
        },
        clearSubjectFilter: functionProp,
        subjectFilterOptions: {
            type: Array,
            default: () => [],
        },
        subjectFilterCount: functionProp,
        isSubjectFilterActive: functionProp,
        toggleSubjectFilter: functionProp,
        topicAllCount: {
            type: Number,
            default: 0,
        },
        hasActiveTopicFilter: {
            type: Boolean,
            default: false,
        },
        clearTopicFilter: functionProp,
        topicFilterOptions: {
            type: Array,
            default: () => [],
        },
        topicFilterCount: functionProp,
        isTopicFilterActive: functionProp,
        toggleTopicFilter: functionProp,
        unitAllCount: {
            type: Number,
            default: 0,
        },
        hasActiveUnitFilter: {
            type: Boolean,
            default: false,
        },
        clearUnitFilter: functionProp,
        unitFilterOptions: {
            type: Array,
            default: () => [],
        },
        unitFilterCount: functionProp,
        isUnitFilterActive: functionProp,
        toggleUnitFilter: functionProp,
        showSecondaryFilters: {
            type: Boolean,
            default: false,
        },
        toggleSecondaryFilters: functionProp,
        hasActiveTypeFilter: {
            type: Boolean,
            default: false,
        },
        clearTypeFilter: functionProp,
        typeFilterOptions: {
            type: Array,
            default: () => [],
        },
        typeColor: functionProp,
        isTypeFilterActive: functionProp,
        toggleTypeFilter: functionProp,
        hasActiveStatusFilter: {
            type: Boolean,
            default: false,
        },
        clearStatusFilter: functionProp,
        statusFilterOptions: {
            type: Array,
            default: () => [],
        },
        statusColor: functionProp,
        isStatusFilterActive: functionProp,
        toggleStatusFilter: functionProp,
    },
}
</script>

<style scoped>
.material-filters-wrap {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 12px;
    align-items: start;
}

.filter-section {
    min-width: 0;
}

.subject-dependent-filters {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    align-items: start;
}

.subject-dependent-filter {
    min-width: 0;
}

.filter-chip-badge {
    display: inline-flex;
}

.filter-chip-badge :deep(.v-badge__badge) {
    top: -12px;
    background: rgba(35, 61, 76, 0.16) !important;
    color: rgba(35, 61, 76, 0.9) !important;
    font-weight: 600;
    box-shadow: none;
}
</style>
