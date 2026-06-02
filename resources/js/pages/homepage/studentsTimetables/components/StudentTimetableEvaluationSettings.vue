<template>
    <section class="student-evaluation-settings">
        <div class="student-evaluation-settings__title">
            <div class="student-evaluation-settings__title-label">
                <v-icon icon="mdi-auto-fix" />
                <div>
                    <p>Automatischer Stundenplan</p>
                    <h3>Bewertungskriterien</h3>
                </div>
            </div>

            <v-btn
                icon="mdi-close"
                variant="text"
                density="comfortable"
                title="Automatischen Stundenplan schließen"
                @click="$emit('close')" />
        </div>

        <v-alert v-if="error" type="error" variant="tonal" density="comfortable" class="mb-3">
            {{ error }}
        </v-alert>

        <v-skeleton-loader v-if="loading" type="list-item-three-line@5" />

        <div v-else class="student-evaluation-settings__list">
            <div
                v-for="(criterion, index) in criteria"
                :key="criterion.key"
                class="student-evaluation-settings__item"
                :class="{
                    'student-evaluation-settings__item--active': criterion.enabled,
                    'student-evaluation-settings__item--disabled': !criterion.enabled,
                }">
                <div class="student-evaluation-settings__priority">
                    {{ index + 1 }}
                </div>

                <v-switch
                    :model-value="criterion.enabled"
                    color="success"
                    density="compact"
                    hide-details
                    inset
                    class="student-evaluation-settings__switch"
                    @update:model-value="setCriterionEnabled(criterion, $event)" />

                <div class="student-evaluation-settings__content">
                    <div class="student-evaluation-settings__header">
                        <div>
                            <div class="student-evaluation-settings__label">{{ criterion.label }}</div>
                            <div class="student-evaluation-settings__description">
                                {{ criterion.description }}
                            </div>
                        </div>

                        <v-chip size="small" :color="criterion.enabled ? 'success' : 'default'" variant="tonal">
                            {{ criterion.enabled ? 'Aktiv' : 'Inaktiv' }}
                        </v-chip>
                    </div>

                    <v-select
                        v-if="criterion.options.length"
                        v-model="criterion.option"
                        :items="criterion.options"
                        item-title="label"
                        item-value="value"
                        label="Variante"
                        density="compact"
                        variant="outlined"
                        hide-details
                        class="student-evaluation-settings__option" />
                </div>

                <div class="student-evaluation-settings__actions">
                    <v-btn
                        icon="mdi-arrow-up"
                        size="small"
                        variant="text"
                        color="primary"
                        :disabled="index === 0"
                        title="Nach oben"
                        @click="moveCriterion(index, -1)" />
                    <v-btn
                        icon="mdi-arrow-down"
                        size="small"
                        variant="text"
                        color="primary"
                        :disabled="index === criteria.length - 1"
                        title="Nach unten"
                        @click="moveCriterion(index, 1)" />
                </div>
            </div>
        </div>

        <div class="student-evaluation-settings__footer">
            <v-btn
                variant="tonal"
                color="primary"
                prepend-icon="mdi-restore"
                rounded="pill"
                :disabled="loading || saving || !isDirty"
                @click="resetChanges">
                Zurücksetzen
            </v-btn>
            <v-btn
                color="primary"
                variant="flat"
                append-icon="mdi-arrow-right"
                rounded="pill"
                :loading="saving"
                :disabled="loading || saving"
                @click="continueToNextStep">
                Weiter
            </v-btn>
        </div>
    </section>
</template>

<script>
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export default {
    emits: ['close', 'saved'],

    data() {
        return {
            loading: false,
            saving: false,
            criteria: [],
            originalCriteria: [],
            error: '',
        }
    },

    computed: {
        isDirty() {
            return this.criteriaSignature(this.criteria) !== this.criteriaSignature(this.originalCriteria)
        },
    },

    mounted() {
        this.loadSettings()
    },

    methods: {
        async loadSettings() {
            this.loading = true
            this.error = ''

            try {
                const response = await axios.get('/api/homepage/students-timetables/evaluation-settings')
                this.criteria = this.normalizedCriteria(response.data?.data?.criteria || [])
                this.originalCriteria = this.cloneCriteria(this.criteria)
            } catch (error) {
                this.criteria = []
                this.originalCriteria = []
                this.error = error?.response?.data?.message || 'Die Bewertungskriterien konnten nicht geladen werden.'
            } finally {
                this.loading = false
            }
        },

        async saveSettings() {
            this.saving = true
            this.error = ''
            this.normalizePriorities()

            try {
                const response = await axios.put('/api/homepage/students-timetables/evaluation-settings', {
                    criteria: this.storageCriteria(this.criteria),
                })
                this.criteria = this.normalizedCriteria(response.data?.data?.criteria || [])
                this.originalCriteria = this.cloneCriteria(this.criteria)
                useNotificationStore().notify({
                    message: response.data?.message || 'Bewertungskriterien wurden gespeichert.',
                    type: 'success',
                    timeout: 3000,
                })
                this.$emit('saved', this.cloneCriteria(this.criteria))
            } catch (error) {
                this.error = error?.response?.data?.message || 'Die Bewertungskriterien konnten nicht gespeichert werden.'
            } finally {
                this.saving = false
            }
        },

        async continueToNextStep() {
            await this.saveSettings()
        },

        setCriterionEnabled(criterion, enabled) {
            this.criteria = this.criteria.map((currentCriterion) => {
                if (currentCriterion.key === criterion.key) {
                    return {
                        ...currentCriterion,
                        enabled: enabled === true,
                    }
                }

                if (enabled === true && this.oppositeDistanceLearningPreferenceKey(criterion.key) === currentCriterion.key) {
                    return {
                        ...currentCriterion,
                        enabled: false,
                    }
                }

                return currentCriterion
            })
        },

        moveCriterion(index, direction) {
            const targetIndex = index + direction

            if (targetIndex < 0 || targetIndex >= this.criteria.length) {
                return
            }

            const criteria = [...this.criteria]
            const currentCriterion = criteria[index]
            criteria[index] = criteria[targetIndex]
            criteria[targetIndex] = currentCriterion
            this.criteria = criteria
            this.normalizePriorities()
        },

        resetChanges() {
            this.criteria = this.cloneCriteria(this.originalCriteria)
        },

        normalizePriorities() {
            this.criteria = this.criteria.map((criterion, index) => ({
                ...criterion,
                priority: index + 1,
            }))
        },

        normalizedCriteria(criteria) {
            const normalizedCriteria = this.cloneCriteria(criteria)
                .sort((firstCriterion, secondCriterion) => firstCriterion.priority - secondCriterion.priority)
                .map((criterion, index) => ({
                    ...criterion,
                    enabled: criterion.enabled === true,
                    priority: index + 1,
                    options: Array.isArray(criterion.options) ? criterion.options : [],
                    option: criterion.option || null,
                }))

            return this.withExclusiveDistanceLearningPreference(normalizedCriteria)
        },

        storageCriteria(criteria) {
            return this.withExclusiveDistanceLearningPreference(criteria).map((criterion, index) => ({
                key: criterion.key,
                enabled: criterion.enabled === true,
                priority: index + 1,
                option: criterion.option || null,
            }))
        },

        withExclusiveDistanceLearningPreference(criteria) {
            const preferenceKeys = ['prefer_distance_learning', 'avoid_distance_learning']
            const enabledPreferenceKey = (criteria || [])
                .filter(criterion => preferenceKeys.includes(criterion.key) && criterion.enabled === true)
                .sort((firstCriterion, secondCriterion) => firstCriterion.priority - secondCriterion.priority)
                .map(criterion => criterion.key)
                .shift()

            if (!enabledPreferenceKey) {
                return criteria
            }

            return (criteria || []).map(criterion => preferenceKeys.includes(criterion.key)
                ? {
                    ...criterion,
                    enabled: criterion.key === enabledPreferenceKey,
                }
                : criterion)
        },

        oppositeDistanceLearningPreferenceKey(key) {
            if (key === 'prefer_distance_learning') return 'avoid_distance_learning'
            if (key === 'avoid_distance_learning') return 'prefer_distance_learning'

            return ''
        },

        cloneCriteria(criteria) {
            return JSON.parse(JSON.stringify(criteria || []))
        },

        criteriaSignature(criteria) {
            return JSON.stringify(this.storageCriteria(criteria || []))
        },
    },
}
</script>

<style scoped>
.student-evaluation-settings {
    display: grid;
    gap: 14px;
    margin: 0 0 20px;
    padding: 16px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.76);
}

.student-evaluation-settings__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.student-evaluation-settings__title-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    color: #10263a;
}

.student-evaluation-settings__title-label p {
    margin: 0 0 2px;
    color: rgba(23, 45, 64, 0.66);
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
}

.student-evaluation-settings__title-label h3 {
    margin: 0;
    font-size: 1.05rem;
}

.student-evaluation-settings__list {
    display: grid;
    gap: 10px;
}

.student-evaluation-settings__item {
    display: grid;
    grid-template-columns: 36px 64px minmax(0, 1fr) 40px;
    align-items: flex-start;
    gap: 10px;
    padding: 12px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.86);
}

.student-evaluation-settings__item--active {
    border-color: rgba(var(--v-theme-success), 0.34);
    background: linear-gradient(90deg, rgba(var(--v-theme-success), 0.13), rgba(255, 255, 255, 0.92));
    box-shadow: inset 4px 0 0 rgba(var(--v-theme-success), 0.82);
}

.student-evaluation-settings__item--disabled {
    background: rgba(16, 38, 58, 0.035);
}

.student-evaluation-settings__priority {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: rgba(var(--v-theme-primary), 0.12);
    color: rgb(var(--v-theme-primary));
    font-size: 0.82rem;
    font-weight: 800;
}

.student-evaluation-settings__item--active .student-evaluation-settings__priority {
    background: rgba(var(--v-theme-success), 0.16);
    color: rgb(var(--v-theme-success));
}

.student-evaluation-settings__switch {
    margin-top: -6px;
}

.student-evaluation-settings__content {
    min-width: 0;
}

.student-evaluation-settings__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.student-evaluation-settings__label {
    color: #172d40;
    font-weight: 800;
}

.student-evaluation-settings__description {
    margin-top: 2px;
    color: rgba(23, 45, 64, 0.68);
    font-size: 0.86rem;
}

.student-evaluation-settings__option {
    max-width: 320px;
    margin-top: 10px;
}

.student-evaluation-settings__actions {
    display: grid;
    gap: 4px;
}

.student-evaluation-settings__footer {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: flex-end;
}

@media (max-width: 720px) {
    .student-evaluation-settings__title {
        align-items: stretch;
        flex-direction: column;
    }

    .student-evaluation-settings__item {
        grid-template-columns: 32px minmax(0, 1fr) 36px;
    }

    .student-evaluation-settings__switch {
        grid-column: 2;
        grid-row: 1;
        margin-left: auto;
    }

    .student-evaluation-settings__content {
        grid-column: 1 / -1;
    }

    .student-evaluation-settings__actions {
        grid-column: 3;
        grid-row: 1;
    }

    .student-evaluation-settings__footer .v-btn {
        flex: 1 1 180px;
    }
}
</style>
