<template>
    <v-col cols="12">
        <v-card rounded="lg" border class="st-evaluation-settings-card">
            <v-card-title class="st-evaluation-settings-title">
                <div class="st-evaluation-settings-title__label">
                    <v-icon icon="mdi-tune-variant" />
                    Einstellungen
                </div>

                <v-spacer />

                <v-btn
                    color="primary"
                    variant="flat"
                    prepend-icon="mdi-content-save-outline"
                    :loading="saving"
                    :disabled="loading || saving || !isDirty"
                    @click="saveSettings">
                    Speichern
                </v-btn>

                <v-btn
                    v-if="closable"
                    icon="mdi-close"
                    variant="text"
                    density="comfortable"
                    class="ml-2"
                    @click="closeSettings" />
            </v-card-title>

            <v-card-text>
                <v-alert v-if="error" type="error" variant="tonal" density="comfortable" class="mb-3">
                    {{ error }}
                </v-alert>

                <v-alert
                    type="info"
                    variant="tonal"
                    density="comfortable"
                    class="mb-3">
                    Einzeltermine werden nicht berücksichtigt.
                </v-alert>

                <v-skeleton-loader v-if="loading" type="list-item-three-line@5" />

                <div v-else class="st-evaluation-settings-list">
                    <div
                        v-for="(criterion, index) in criteria"
                        :key="criterion.key"
                        class="st-evaluation-settings-item"
                        :class="{ 'st-evaluation-settings-item--disabled': !criterion.enabled }">
                        <div class="st-evaluation-settings-item__priority">
                            {{ index + 1 }}
                        </div>

                        <v-switch
                            v-model="criterion.enabled"
                            color="primary"
                            density="compact"
                            hide-details
                            inset
                            class="st-evaluation-settings-item__switch" />

                        <div class="st-evaluation-settings-item__content">
                            <div class="st-evaluation-settings-item__header">
                                <div>
                                    <div class="st-evaluation-settings-item__label">{{ criterion.label }}</div>
                                    <div class="st-evaluation-settings-item__description">
                                        {{ criterion.description }}
                                    </div>
                                </div>

                                <v-chip
                                    size="small"
                                    :color="criterion.enabled ? 'primary' : 'default'"
                                    variant="tonal">
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
                                class="st-evaluation-settings-item__option" />
                        </div>

                        <div class="st-evaluation-settings-item__actions">
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
            </v-card-text>

            <v-card-actions class="st-evaluation-settings-actions">
                <v-btn
                    variant="tonal"
                    color="primary"
                    prepend-icon="mdi-restore"
                    :disabled="loading || saving || !isDirty"
                    @click="resetChanges">
                    Zurücksetzen
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export default {
    name: 'EvaluationSettings',
    props: {
        closable: { type: Boolean, default: false },
    },
    emits: ['changed', 'close', 'saved'],
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
        ...mapWritableState(useAdminStore, ['config']),
        isDirty() {
            return this.criteriaSignature(this.criteria) !== this.criteriaSignature(this.originalCriteria)
        },
        selectedSchoolyearId() {
            return this.config?.selected_schoolyear?.id || null
        },
    },
    watch: {
        selectedSchoolyearId() {
            this.loadSettings()
        },
        criteria: {
            deep: true,
            handler() {
                this.emitChangedCriteria()
            },
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
                const response = await axios.get('/api/admin/students-timetables/evaluation-settings')
                this.criteria = this.normalizedCriteria(response.data?.data?.criteria || [])
                this.originalCriteria = this.cloneCriteria(this.criteria)
            } catch (error) {
                this.criteria = []
                this.originalCriteria = []
                this.error = error?.response?.data?.message || 'Die Bewertungseinstellungen konnten nicht geladen werden.'
            } finally {
                this.loading = false
            }
        },
        async saveSettings() {
            this.saving = true
            this.error = ''
            this.normalizePriorities()

            try {
                const response = await axios.put('/api/admin/students-timetables/evaluation-settings', {
                    criteria: this.storageCriteria(this.criteria),
                })
                this.criteria = this.normalizedCriteria(response.data?.data?.criteria || [])
                this.originalCriteria = this.cloneCriteria(this.criteria)
                useNotificationStore().notify({
                    message: response.data?.message || 'Bewertungseinstellungen wurden gespeichert.',
                    type: 'success',
                    timeout: 3000,
                })
                this.$emit('saved', this.cloneCriteria(this.criteria))
            } catch (error) {
                this.error = error?.response?.data?.message || 'Die Bewertungseinstellungen konnten nicht gespeichert werden.'
            } finally {
                this.saving = false
            }
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
        closeSettings() {
            this.$emit('changed', this.cloneCriteria(this.originalCriteria))
            this.$emit('close')
        },
        emitChangedCriteria() {
            this.$emit('changed', this.normalizedCriteria(this.criteria))
        },
        normalizePriorities() {
            this.criteria = this.criteria.map((criterion, index) => ({
                ...criterion,
                priority: index + 1,
            }))
        },
        normalizedCriteria(criteria) {
            return this.cloneCriteria(criteria)
                .sort((firstCriterion, secondCriterion) => firstCriterion.priority - secondCriterion.priority)
                .map((criterion, index) => ({
                    ...criterion,
                    enabled: criterion.enabled === true,
                    priority: index + 1,
                    options: Array.isArray(criterion.options) ? criterion.options : [],
                    option: criterion.option || null,
                }))
        },
        storageCriteria(criteria) {
            return criteria.map((criterion, index) => ({
                key: criterion.key,
                enabled: criterion.enabled === true,
                priority: index + 1,
                option: criterion.option || null,
            }))
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
.st-evaluation-settings-card {
    max-width: 1080px;
    border: 1px solid rgba(37, 99, 235, 0.12);
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
}

.st-evaluation-settings-title {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 18px 10px;
}

.st-evaluation-settings-title__label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
}

.st-evaluation-settings-list {
    display: grid;
    gap: 10px;
}

.st-evaluation-settings-item {
    display: grid;
    grid-template-columns: 36px 64px minmax(0, 1fr) 40px;
    align-items: flex-start;
    gap: 10px;
    border: 1px solid rgba(37, 99, 235, 0.14);
    border-radius: 8px;
    padding: 12px;
    background: #f8fbff;
}

.st-evaluation-settings-item--disabled {
    background: #f6f7f9;
}

.st-evaluation-settings-item__priority {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: rgba(37, 99, 235, 0.12);
    color: #1d4ed8;
    font-size: 0.82rem;
    font-weight: 700;
}

.st-evaluation-settings-item__switch {
    margin-top: -6px;
}

.st-evaluation-settings-item__content {
    min-width: 0;
}

.st-evaluation-settings-item__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.st-evaluation-settings-item__label {
    font-weight: 700;
}

.st-evaluation-settings-item__description {
    margin-top: 2px;
    color: rgba(15, 23, 42, 0.68);
    font-size: 0.84rem;
}

.st-evaluation-settings-item__option {
    max-width: 320px;
    margin-top: 10px;
}

.st-evaluation-settings-item__actions {
    display: grid;
    gap: 4px;
}

.st-evaluation-settings-actions {
    justify-content: flex-end;
    padding: 0 18px 16px;
}

@media (max-width: 720px) {
    .st-evaluation-settings-title {
        align-items: flex-start;
        flex-direction: column;
    }

    .st-evaluation-settings-item {
        grid-template-columns: 32px minmax(0, 1fr) 36px;
    }

    .st-evaluation-settings-item__switch {
        grid-column: 2;
        grid-row: 1;
        margin-left: auto;
    }

    .st-evaluation-settings-item__content {
        grid-column: 1 / -1;
    }

    .st-evaluation-settings-item__actions {
        grid-column: 3;
        grid-row: 1;
    }
}
</style>
