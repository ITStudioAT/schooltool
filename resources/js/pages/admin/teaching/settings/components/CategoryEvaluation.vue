<template>
    <ItsGridBox variant="overview" color="primary" title="Kategoriebewertung" icon="mdi-format-list-bulleted-square" class="w-100" :disabled="action != ''">
        <div class="d-flex flex-row align-center justify-end mt-2 ga-2">
            <v-btn v-if="!is_editing" icon="mdi-pencil" size="x-small" color="primary" variant="flat" @click="startEdit" />
            <v-btn v-if="is_editing" icon="mdi-check" size="x-small" color="success" variant="flat" :disabled="!isValid" @click="save" />
            <v-btn v-if="is_editing" icon="mdi-close" size="x-small" color="warning" variant="flat" @click="cancelEdit" />
        </div>

        <v-card tile flat color="transparent" class="mt-4">
            <v-card-text>
                <div class="text-body-2 text-medium-emphasis mb-4">
                    Diese Werte stehen später in der Auswertung zur manuellen Kategoriebewertung zur Verfügung.
                </div>

                <div v-if="!is_editing">
                    <div v-if="defaultValue" class="mb-4">
                        <v-chip :color="defaultValueColor" variant="flat">
                            Standardwert: {{ defaultValue }}
                        </v-chip>
                    </div>

                    <div v-if="valueItems.length" class="d-flex flex-wrap ga-2">
                        <v-chip
                            v-for="(item, index) in valueItems"
                            :key="`${schemaId}-category-evaluation-${index}-${item.value}`"
                            :color="item.color"
                            :variant="item.value === defaultValue ? 'flat' : 'outlined'">
                            {{ index + 1 }}. {{ item.value }}
                        </v-chip>
                    </div>
                    <v-alert v-else type="info" variant="tonal">
                        Noch keine Werte vorhanden.
                    </v-alert>
                </div>

                <div v-else>
                    <div class="d-flex flex-column ga-3">
                        <div class="category-evaluation-default-box mb-2">
                            <div class="text-caption text-medium-emphasis mb-2">Standardwert</div>
                            <v-select
                                v-model="data.default_value"
                                :items="cleanedItems"
                                item-title="value"
                                item-value="value"
                                label="Standardwert"
                                density="compact"
                                hide-details>
                                <template #selection="{ item }">
                                    <v-chip size="small" :color="item.raw.color" variant="flat">
                                        {{ item.raw.value }}
                                    </v-chip>
                                </template>
                                <template #item="{ props, item }">
                                    <v-list-item v-bind="props" :subtitle="item.raw.color">
                                        <template #prepend>
                                            <span class="color-dot mr-2" :style="colorPreviewStyle(item.raw.color)" />
                                        </template>
                                    </v-list-item>
                                </template>
                            </v-select>
                        </div>

                        <div v-for="(item, index) in data.items" :key="`${schemaId}-category-evaluation-edit-${index}`" class="d-flex flex-wrap align-center ga-2 category-evaluation-edit-row">
                            <v-text-field
                                v-model="data.items[index].value"
                                :label="`Wert ${index + 1}`"
                                density="compact"
                                hide-details
                                class="flex-grow-1"
                                @keyup.enter="addValue" />
                            <v-btn
                                variant="outlined"
                                class="color-picker-trigger"
                                :title="data.items[index]?.color || 'Keine Farbe'"
                                @click="openColorPicker(index)">
                                <span class="color-dot" :style="colorPreviewStyle(data.items[index]?.color)" />
                            </v-btn>
                            <v-text-field
                                v-model="data.items[index].color"
                                label="HEX"
                                placeholder="#1f77b4"
                                density="compact"
                                hide-details
                                class="color-hex-field"
                                @blur="normalizeColorField(index)"
                                @keyup.enter="normalizeColorField(index)" />
                            <v-chip size="small" :color="cleanedItemColor(index)" variant="flat">
                                {{ cleanedItemValue(index) || 'Vorschau' }}
                            </v-chip>
                            <v-btn icon="mdi-delete" size="x-small" color="error" variant="text" @click="removeValue(index)" />
                        </div>
                    </div>

                    <v-divider class="my-4" />

                    <div class="d-flex flex-wrap align-center ga-2">
                        <v-text-field
                            v-model="new_value"
                            label="Neuen Wert hinzufügen"
                            density="compact"
                            hide-details
                            class="flex-grow-1"
                            @keyup.enter="addValue" />
                        <v-btn
                            variant="outlined"
                            class="color-picker-trigger"
                            :title="new_color || 'Keine Farbe'"
                            @click="openColorPicker('new')">
                            <span class="color-dot" :style="colorPreviewStyle(new_color)" />
                        </v-btn>
                        <v-text-field
                            v-model="new_color"
                            label="HEX"
                            placeholder="#1f77b4"
                            density="compact"
                            hide-details
                            class="color-hex-field"
                            @blur="normalizeColorField('new')"
                            @keyup.enter="normalizeColorField('new')" />
                        <v-btn icon="mdi-plus" size="small" color="primary" :disabled="!new_value.trim()" @click="addValue" />
                    </div>

                    <v-alert v-if="!isValid" type="warning" density="compact" variant="tonal" class="mt-4">
                        Mindestens ein Bewertungswert ist erforderlich.
                    </v-alert>
                </div>
            </v-card-text>
        </v-card>
    </ItsGridBox>

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
    teachingCategoryEvaluationColorForValue,
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
        this.initData()
    },

    data() {
        return {
            adminStore: null,
            teachingStore: null,
            is_editing: false,
            data: {
                items: [],
                default_value: '',
            },
            new_value: '',
            new_color: '',
            color_picker_target: 'new',
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action']),
        ...mapWritableState(useTeachingStore, ['settings']),
        valueItems() {
            return this.teachingStore?.categoryEvaluationValueItemsForSchema(this.schemaId) || []
        },
        defaultValue() {
            return this.teachingStore?.defaultCategoryEvaluationValueForSchema(this.schemaId) || ''
        },
        defaultValueColor() {
            return teachingCategoryEvaluationColorForValue(this.valueItems, this.defaultValue, '#43a047')
        },
        cleanedItems() {
            return this.normalizeValueItems(this.data.items)
        },
        cleanedValues() {
            return teachingCategoryEvaluationValueLabels(this.cleanedItems, { fallbackToDefaults: false })
        },
        pickerInputColor() {
            if (this.color_picker_target === 'new') {
                return normalizeTeachingCategoryEvaluationColor(this.new_color, '#4f6fb3')
            }

            return normalizeTeachingCategoryEvaluationColor(this.data.items?.[this.color_picker_target]?.color, '#4f6fb3')
        },
        isValid() {
            return this.cleanedItems.length > 0
        },
    },

    watch: {
        schemaId() {
            this.initData()
            this.cancelEdit()
        },
        cleanedItems(newItems) {
            const newValues = newItems.map((item) => item.value)
            if (!newValues.length) {
                this.data.default_value = ''
                return
            }

            if (!newValues.includes(this.data.default_value)) {
                this.data.default_value = newValues[0]
            }
        },
    },

    methods: {
        normalizeValueItems(values) {
            return normalizeTeachingCategoryEvaluationValueItems(values, { fallbackToDefaults: false })
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

        normalizeColorField(target) {
            if (target === 'new') {
                this.new_color = normalizeTeachingCategoryEvaluationColor(this.new_color) || String(this.new_color || '').trim()
                return
            }

            const index = Number(target)
            if (!Number.isInteger(index) || index < 0 || !this.data.items[index]) {
                return
            }

            this.data.items[index].color = normalizeTeachingCategoryEvaluationColor(this.data.items[index].color) || String(this.data.items[index].color || '').trim()
        },

        openColorPicker(target) {
            this.color_picker_target = target === 'new' ? 'new' : Number(target)
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

            if (this.color_picker_target === 'new') {
                this.new_color = color
                return
            }

            const index = Number(this.color_picker_target)
            if (!Number.isInteger(index) || index < 0 || !this.data.items[index]) {
                return
            }

            this.data.items[index].color = color
        },

        cleanedItemValue(index) {
            const item = this.normalizeValueItems([this.data.items[index]])[0]
            return item?.value || ''
        },

        cleanedItemColor(index) {
            const item = this.normalizeValueItems([this.data.items[index]])[0]
            return item?.color || '#4f6fb3'
        },

        initData() {
            this.data = {
                items: this.valueItems.map((item) => ({ ...item })),
                default_value: this.defaultValue || this.valueItems[0]?.value || '',
            }
            this.new_value = ''
            this.new_color = ''
        },

        startEdit() {
            this.initData()
            this.is_editing = true
        },

        cancelEdit() {
            this.is_editing = false
            this.initData()
        },

        addValue() {
            const trimmedValue = this.new_value.trim()
            if (!trimmedValue || this.cleanedValues.includes(trimmedValue)) {
                this.new_value = ''
                return
            }

            this.data.items = [
                ...this.data.items,
                {
                    value: trimmedValue,
                    color: normalizeTeachingCategoryEvaluationColor(this.new_color, '#4f6fb3'),
                },
            ]
            this.new_value = ''
            this.new_color = ''
        },

        removeValue(index) {
            this.data.items = this.data.items.filter((_, currentIndex) => currentIndex !== index)
        },

        async save() {
            if (!this.isValid) {
                return
            }

            const schemas = [...(this.settings?.teaching_schemas || [])]
            const schemaIndex = schemas.findIndex((schema) => schema.id === this.schemaId)

            if (schemaIndex === -1) {
                return
            }

            const schema = schemas[schemaIndex]
            const grading = {
                ...(schema.grading || {}),
                category_evaluation_values: this.cleanedItems,
                default_category_evaluation_value: this.cleanedValues.includes(this.data.default_value)
                    ? this.data.default_value
                    : (this.cleanedValues[0] || ''),
            }

            schemas[schemaIndex] = {
                ...schema,
                grading,
            }

            const wasSaved = await this.teachingStore.saveSettings({ teaching_schemas: schemas })

            if (!wasSaved) {
                return
            }

            this.is_editing = false
            this.initData()
        },
    },
}
</script>

<style scoped>
.category-evaluation-default-box {
    border: 1px solid rgba(var(--v-theme-success), 0.35);
    border-radius: 12px;
    background: rgba(var(--v-theme-success), 0.06);
    padding: 12px;
}

.category-evaluation-edit-row {
    width: 100%;
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
