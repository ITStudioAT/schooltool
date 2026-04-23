<template>
    <ItsGridBox variant="overview" color="primary" title="Kategoriebewertung" icon="mdi-format-list-bulleted-square" class="w-100" :disabled="action != '' || isSavingCategoryEvaluation">
        <template #header-actions>
            <v-btn size="small" color="primary" variant="text" prepend-icon="mdi-plus" :disabled="isSavingCategoryEvaluation" @click="openCreateDialog">
                Wert
            </v-btn>
        </template>

        <v-card tile flat color="transparent" class="mt-4">
            <v-card-text>
                <div class="text-body-2 text-medium-emphasis mb-4">
                    Diese Werte stehen später in der Auswertung zur manuellen Kategoriebewertung zur Verfügung.
                </div>

                <v-list v-if="valueItems.length" density="comfortable" class="bg-transparent px-0">
                    <v-list-item
                        v-for="(item, index) in valueItems"
                        :key="`${schemaId}-category-evaluation-${index}-${item.value}`"
                        class="px-0 category-evaluation-list-item">
                        <div class="d-flex align-center justify-space-between ga-3 w-100 flex-wrap">
                            <div class="d-flex align-center ga-3 flex-wrap">
                                <span class="color-dot" :style="colorPreviewStyle(item.color)" />
                                <v-chip :color="item.color" :variant="item.value === defaultValue ? 'flat' : 'outlined'">
                                    {{ item.value }}
                                </v-chip>
                                <span v-if="item.value === defaultValue" class="text-caption font-weight-medium text-warning">
                                    Standardwert
                                </span>
                            </div>

                            <div class="d-flex align-center ga-1">
                                <v-btn
                                    :icon="item.value === defaultValue ? 'mdi-star' : 'mdi-star-outline'"
                                    size="x-small"
                                    :color="item.value === defaultValue ? 'warning' : 'secondary'"
                                    variant="text"
                                    :disabled="isSavingCategoryEvaluation"
                                    :loading="category_evaluation_save_action === 'set-default'"
                                    @click="setDefaultValue(item.value)" />
                                <v-btn
                                    icon="mdi-pencil"
                                    size="x-small"
                                    color="primary"
                                    variant="text"
                                    :disabled="isSavingCategoryEvaluation"
                                    @click="openEditDialog(index)" />
                                <v-btn
                                    icon="mdi-delete"
                                    size="x-small"
                                    color="warning"
                                    variant="text"
                                    :disabled="isSavingCategoryEvaluation"
                                    @click="openDeleteDialog(index)" />
                            </div>
                        </div>
                        <v-divider class="mt-3" />
                    </v-list-item>
                </v-list>

                <v-alert v-else type="info" variant="tonal">
                    Noch keine Werte vorhanden.
                </v-alert>
            </v-card-text>
        </v-card>
    </ItsGridBox>

    <v-dialog v-model="item_dialog_open" persistent max-width="560">
        <v-card>
            <v-card-title class="d-flex align-center justify-space-between">
                <span>{{ item_dialog_mode === 'create' ? 'Wert hinzufügen' : 'Wert bearbeiten' }}</span>
                <v-btn icon="mdi-close" variant="text" @click="closeItemDialog" />
            </v-card-title>
            <v-card-text>
                <div class="d-flex flex-column ga-4">
                    <v-text-field
                        v-model="dialog_form.value"
                        label="Wert"
                        density="compact"
                        hide-details="auto"
                        :error-messages="dialogValueError" />

                    <div class="d-flex flex-wrap align-center ga-2">
                        <v-btn
                            variant="outlined"
                            class="color-picker-trigger"
                            :title="dialog_form.color || 'Keine Farbe'"
                            @click="openColorPicker('dialog')">
                            <span class="color-dot" :style="colorPreviewStyle(dialog_form.color)" />
                        </v-btn>
                        <v-text-field
                            v-model="dialog_form.color"
                            label="HEX"
                            placeholder="#1f77b4"
                            density="compact"
                            hide-details
                            class="color-hex-field"
                            @blur="normalizeDialogColor"
                            @keyup.enter="normalizeDialogColor" />
                        <v-chip size="small" :color="dialogPreviewColor" variant="flat">
                            {{ dialog_form.value.trim() || 'Vorschau' }}
                        </v-chip>
                    </div>
                </div>
            </v-card-text>
            <v-card-actions class="justify-end">
                <v-btn color="primary" variant="flat" :loading="category_evaluation_save_action === 'save-item'" :disabled="!isDialogValid || isSavingCategoryEvaluation" @click="saveItem">Speichern</v-btn>
                <v-btn color="primary" variant="text" :disabled="isSavingCategoryEvaluation" @click="closeItemDialog">Abbrechen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="delete_dialog_open" persistent max-width="520">
        <v-card>
            <v-card-title class="d-flex align-center justify-space-between">
                <span>Wert löschen</span>
                <v-btn icon="mdi-close" variant="text" @click="closeDeleteDialog" />
            </v-card-title>
            <v-card-text>
                <v-alert
                    :type="deleteUsageCount > 0 ? 'warning' : 'info'"
                    variant="tonal"
                    class="mb-4">
                    <div class="font-weight-medium mb-2">
                        {{
                            deleteUsageCount > 0
                                ? 'Dieser Wert wird bereits verwendet:'
                                : 'Dieser Wert wird derzeit nicht verwendet.'
                        }}
                    </div>
                    <div class="d-flex flex-wrap ga-2">
                        <v-chip :color="deleteUsageCount > 0 ? 'warning' : 'info'" variant="flat" size="small">
                            {{ deleteUsageCount }} Einträge
                        </v-chip>
                    </div>
                </v-alert>
                <div class="text-body-2 text-medium-emphasis">
                    Möchten Sie diesen Wert der Kategoriebewertung wirklich löschen?
                </div>
                <div class="text-body-2 text-medium-emphasis mt-3">
                    Beim Löschen werden auch alle betroffenen Einträge der Kategoriebewertung entfernt.
                </div>
            </v-card-text>
            <v-card-actions class="justify-end">
                <v-btn color="error" variant="flat" :loading="category_evaluation_save_action === 'delete-item'" :disabled="isSavingCategoryEvaluation" @click="confirmDelete">Löschen</v-btn>
                <v-btn color="primary" variant="text" :disabled="isSavingCategoryEvaluation" @click="closeDeleteDialog">Abbrechen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <input
        ref="colorInput"
        type="color"
        class="d-none"
        :value="pickerInputColor"
        @input="onColorPicked" />
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import {
    normalizeTeachingCategoryEvaluationColor,
    normalizeTeachingCategoryEvaluationValueItems,
    teachingCategoryEvaluationValueLabels,
} from '@/helpers/teachingCategoryEvaluation'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { ItsGridBox },

    props: {
        schemaId: {
            type: String,
            required: true,
        },
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.teachingStore = useTeachingStore()
        await this.teachingStore.loadSettings()
    },

    data() {
        return {
            adminStore: null,
            teachingStore: null,
            item_dialog_open: false,
            item_dialog_mode: 'create',
            delete_dialog_open: false,
            edit_index: null,
            delete_index: null,
            dialog_form: {
                value: '',
                color: '',
            },
            color_picker_target: 'dialog',
            category_evaluation_save_action: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action']),
        ...mapWritableState(useTeachingStore, ['settings']),
        isSavingCategoryEvaluation() {
            return this.category_evaluation_save_action !== null
        },
        valueItems() {
            return this.teachingStore?.categoryEvaluationValueItemsForSchema(this.schemaId) || []
        },
        defaultValue() {
            return this.teachingStore?.defaultCategoryEvaluationValueForSchema(this.schemaId) || ''
        },
        dialogPreviewColor() {
            return normalizeTeachingCategoryEvaluationColor(this.dialog_form.color, '#4f6fb3')
        },
        dialogValueError() {
            const trimmedValue = this.dialog_form.value.trim()

            if (!trimmedValue) {
                return 'Wert ist erforderlich.'
            }

            if (this.hasDuplicateDialogValue(trimmedValue)) {
                return 'Dieser Wert ist bereits vorhanden.'
            }

            return ''
        },
        isDialogValid() {
            return this.dialogValueError === ''
        },
        pickerInputColor() {
            return normalizeTeachingCategoryEvaluationColor(this.dialog_form.color, '#4f6fb3')
        },
        deleteEntryDefinition() {
            if (this.delete_index === null) {
                return null
            }

            return this.valueItems[this.delete_index] || null
        },
        categoryEvaluationUsageCounts() {
            const schema = (this.settings?.teaching_schemas || []).find((entry) => entry.id === this.schemaId)
            return schema?.grading?.category_evaluation_usage_counts || {}
        },
        deleteUsageCount() {
            const value = this.deleteEntryDefinition?.value

            if (!value) {
                return 0
            }

            return Number(this.categoryEvaluationUsageCounts?.[value] || 0)
        },
    },

    watch: {
        schemaId() {
            this.closeItemDialog()
            this.closeDeleteDialog()
        },
    },

    methods: {
        async runCategoryEvaluationMutation(action, callback) {
            if (this.category_evaluation_save_action) {
                return false
            }

            this.category_evaluation_save_action = action
            await this.$nextTick()

            try {
                return await callback()
            } finally {
                this.category_evaluation_save_action = null
            }
        },
        normalizeValueItems(values) {
            return normalizeTeachingCategoryEvaluationValueItems(values, { fallbackToDefaults: false })
        },

        normalizeValueLabels(values) {
            return teachingCategoryEvaluationValueLabels(values, { fallbackToDefaults: false })
        },

        colorPreviewStyle(value) {
            const color = normalizeTeachingCategoryEvaluationColor(value)
            if (!color) {
                return {}
            }

            return {
                backgroundColor: color,
                backgroundImage: 'none',
            }
        },

        resetDialogForm(item = null) {
            this.dialog_form = {
                value: String(item?.value || ''),
                color: normalizeTeachingCategoryEvaluationColor(item?.color, '#4f6fb3'),
            }
        },

        openCreateDialog() {
            this.item_dialog_mode = 'create'
            this.edit_index = null
            this.resetDialogForm()
            this.item_dialog_open = true
        },

        openEditDialog(index) {
            this.item_dialog_mode = 'edit'
            this.edit_index = index
            this.resetDialogForm(this.valueItems[index])
            this.item_dialog_open = true
        },

        closeItemDialog() {
            this.item_dialog_open = false
            this.item_dialog_mode = 'create'
            this.edit_index = null
            this.resetDialogForm()
        },

        openDeleteDialog(index) {
            this.delete_index = index
            this.delete_dialog_open = true
        },

        closeDeleteDialog() {
            this.delete_dialog_open = false
            this.delete_index = null
        },

        hasDuplicateDialogValue(candidateValue) {
            const normalizedCandidate = String(candidateValue || '').trim().toLocaleLowerCase()

            return this.valueItems.some((item, index) => {
                if (this.item_dialog_mode === 'edit' && index === this.edit_index) {
                    return false
                }

                return String(item?.value || '').trim().toLocaleLowerCase() === normalizedCandidate
            })
        },

        normalizeDialogColor() {
            this.dialog_form.color = normalizeTeachingCategoryEvaluationColor(this.dialog_form.color) || String(this.dialog_form.color || '').trim()
        },

        openColorPicker(target) {
            this.color_picker_target = target
            this.$nextTick(() => {
                const colorInput = this.$refs.colorInput
                if (colorInput && typeof colorInput.click === 'function') {
                    colorInput.click()
                }
            })
        },

        onColorPicked(event) {
            const color = normalizeTeachingCategoryEvaluationColor(event?.target?.value)
            if (!color) {
                return
            }

            if (this.color_picker_target === 'dialog') {
                this.dialog_form.color = color
            }
        },

        async persistCategoryEvaluation(items, defaultValue) {
            const schemas = [...(this.settings?.teaching_schemas || [])]
            const schemaIndex = schemas.findIndex((schema) => schema.id === this.schemaId)

            if (schemaIndex === -1) {
                return false
            }

            const cleanedItems = this.normalizeValueItems(items)
            const cleanedValues = this.normalizeValueLabels(cleanedItems)
            const resolvedDefaultValue = cleanedValues.includes(defaultValue) ? defaultValue : (cleanedValues[0] || '')

            const schema = schemas[schemaIndex]
            schemas[schemaIndex] = {
                ...schema,
                grading: {
                    ...(schema.grading || {}),
                    category_evaluation_values: cleanedItems,
                    default_category_evaluation_value: resolvedDefaultValue,
                },
            }

            return await this.teachingStore.saveSettings({ teaching_schemas: schemas })
        },

        async saveItem() {
            if (!this.isDialogValid) {
                return
            }

            await this.runCategoryEvaluationMutation('save-item', async () => {
                const trimmedValue = this.dialog_form.value.trim()
                const updatedItem = {
                    value: trimmedValue,
                    color: normalizeTeachingCategoryEvaluationColor(this.dialog_form.color, '#4f6fb3'),
                }
                const items = this.valueItems.map((item) => ({ ...item }))

                let nextDefaultValue = this.defaultValue

                if (this.edit_index !== null) {
                    const previousValue = items[this.edit_index]?.value || ''
                    items[this.edit_index] = updatedItem

                    if (this.defaultValue === previousValue) {
                        nextDefaultValue = trimmedValue
                    }
                } else {
                    items.push(updatedItem)

                    if (!nextDefaultValue) {
                        nextDefaultValue = trimmedValue
                    }
                }

                const wasSaved = await this.persistCategoryEvaluation(items, nextDefaultValue)

                if (!wasSaved) {
                    return
                }

                this.closeItemDialog()
            })
        },

        async setDefaultValue(value) {
            if (!value || value === this.defaultValue) {
                return
            }

            await this.runCategoryEvaluationMutation('set-default', async () => {
                await this.persistCategoryEvaluation(this.valueItems, value)
            })
        },

        async confirmDelete() {
            if (this.delete_index === null) {
                return
            }

            await this.runCategoryEvaluationMutation('delete-item', async () => {
                const deletedValue = this.deleteEntryDefinition?.value || ''
                const items = this.valueItems.filter((_, index) => index !== this.delete_index)
                const nextDefaultValue = this.defaultValue === deletedValue ? (items[0]?.value || '') : this.defaultValue

                const wasSaved = await this.persistCategoryEvaluation(items, nextDefaultValue)

                if (!wasSaved) {
                    return
                }

                this.closeDeleteDialog()
            })
        },
    },
}
</script>

<style scoped>
.category-evaluation-list-item {
    border-radius: 12px;
}

.color-picker-trigger {
    min-width: 40px;
    width: 40px;
    height: 40px;
    padding: 0;
}

.color-hex-field {
    max-width: 140px;
}

.color-dot {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 1px solid rgba(40, 58, 80, 0.4);
    background-image: repeating-conic-gradient(#d9dce2 0% 25%, #ffffff 0% 50%);
    background-size: 8px 8px;
    background-position: center;
}
</style>
