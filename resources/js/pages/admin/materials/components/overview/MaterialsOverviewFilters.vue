<template>
    <div class="materials-overview-filters-card mb-4">
        <div class="filter-section">
            <div class="d-flex flex-wrap align-center ga-2">
                <div class="text-subtitle-2 font-weight-medium materials-overview-filters-label">Fächer:</div>

                <v-btn
                    size="default"
                    :variant="hasActiveSubjectFilter ? 'outlined' : 'tonal'"
                    :color="hasActiveSubjectFilter ? undefined : 'primary'"
                    :disabled="actionDisabled"
                    class="materials-overview-filter-btn"
                    @click="clearSubjectFilter()">
                    <span class="materials-overview-filter-btn__content">
                        Alle
                        <span class="overview-subjects-material-count overview-subjects-material-count--button materials-overview-filter-count" aria-hidden="true">
                            {{ badgeCountContent(subjectAllCount) }}
                        </span>
                    </span>
                </v-btn>

                <v-btn
                    v-for="subject in subjectFilterOptions"
                    :key="`subject-filter-${subject}`"
                    size="default"
                    :variant="isSubjectFilterActive(subject) ? 'tonal' : 'outlined'"
                    :color="isSubjectFilterActive(subject) ? 'primary' : undefined"
                    :disabled="actionDisabled"
                    class="materials-overview-filter-btn"
                    @click="toggleSubjectFilter(subject)">
                    <span class="materials-overview-filter-btn__content">
                        {{ subject }}
                        <span class="overview-subjects-material-count overview-subjects-material-count--button materials-overview-filter-count" aria-hidden="true">
                            {{ badgeCountContent(subjectFilterCount(subject)) }}
                        </span>
                    </span>
                </v-btn>
            </div>

            <div v-if="hasActiveSubjectFilter" class="subject-dependent-filters mt-3">
                <div class="subject-dependent-filter-row">
                    <div class="text-subtitle-2 font-weight-medium materials-overview-filters-label">Thema:</div>

                    <div class="d-flex flex-wrap ga-2">
                        <v-btn
                            size="default"
                            :variant="hasActiveTopicFilter ? 'outlined' : 'tonal'"
                            :color="hasActiveTopicFilter ? undefined : 'primary'"
                            :disabled="actionDisabled"
                            class="materials-overview-filter-btn"
                            @click="clearTopicFilter()">
                            <span class="materials-overview-filter-btn__content">
                                Alle
                                <span class="overview-subjects-material-count overview-subjects-material-count--button materials-overview-filter-count" aria-hidden="true">
                                    {{ badgeCountContent(topicAllCount) }}
                                </span>
                            </span>
                        </v-btn>

                        <v-btn
                            v-for="topic in topicFilterOptions"
                            :key="`topic-filter-${topic}`"
                            size="default"
                            :variant="isTopicFilterActive(topic) ? 'tonal' : 'outlined'"
                            :color="isTopicFilterActive(topic) ? 'primary' : undefined"
                            :disabled="actionDisabled"
                            class="materials-overview-filter-btn"
                            @click="toggleTopicFilter(topic)">
                            <span class="materials-overview-filter-btn__content">
                                {{ topic }}
                                <span class="overview-subjects-material-count overview-subjects-material-count--button materials-overview-filter-count" aria-hidden="true">
                                    {{ badgeCountContent(topicFilterCount(topic)) }}
                                </span>
                            </span>
                        </v-btn>
                    </div>
                </div>

                <div v-if="hasActiveTopicFilter" class="subject-dependent-filter">
                    <div class="d-flex flex-wrap align-center ga-2">
                        <div class="text-subtitle-2 font-weight-medium materials-overview-filters-label">Bereich:</div>

                        <div class="d-flex flex-wrap ga-2">
                            <v-btn
                                size="default"
                                :variant="hasActiveUnitFilter ? 'outlined' : 'tonal'"
                                :color="hasActiveUnitFilter ? undefined : 'primary'"
                                :disabled="actionDisabled"
                                class="materials-overview-filter-btn"
                                @click="clearUnitFilter()">
                                <span class="materials-overview-filter-btn__content">
                                    Alle
                                    <span class="overview-subjects-material-count overview-subjects-material-count--button materials-overview-filter-count" aria-hidden="true">
                                        {{ badgeCountContent(unitAllCount) }}
                                    </span>
                                </span>
                            </v-btn>

                            <v-btn
                                v-for="unit in unitFilterOptions"
                                :key="`unit-filter-${unit}`"
                                size="default"
                                :variant="isUnitFilterActive(unit) ? 'tonal' : 'outlined'"
                                :color="isUnitFilterActive(unit) ? 'primary' : undefined"
                                :disabled="actionDisabled"
                                class="materials-overview-filter-btn"
                                @click="toggleUnitFilter(unit)">
                                <span class="materials-overview-filter-btn__content">
                                    {{ unit }}
                                    <span class="overview-subjects-material-count overview-subjects-material-count--button materials-overview-filter-count" aria-hidden="true">
                                        {{ badgeCountContent(unitFilterCount(unit)) }}
                                    </span>
                                </span>
                            </v-btn>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
    },
}
</script>

<style scoped>
.materials-overview-filters-card {
    background: rgba(var(--v-theme-info), 0.12);
    border-top: 2px solid rgba(var(--v-theme-info), 0.28);
    border-radius: 8px;
    padding: 8px 12px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 12px;
    align-items: start;
}

.filter-section {
    min-width: 0;
}

.materials-overview-filters-label {
    flex: 0 0 auto;
}

.materials-overview-filter-btn {
    text-transform: none;
    letter-spacing: 0.01em;
    font-weight: 400;
    font-size: 0.82rem !important;
}

.materials-overview-filter-btn :deep(.v-btn__content) {
    display: inline-flex;
    align-items: center;
}

.materials-overview-filter-btn__content {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.materials-overview-filter-count {
    font-size: 0.68rem;
    min-width: 1.2rem;
    height: 1.2rem;
    padding: 0 0.3rem;
}

.subject-dependent-filters {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.subject-dependent-filter-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 12px;
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
