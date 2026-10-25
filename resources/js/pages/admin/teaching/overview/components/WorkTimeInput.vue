<template>
    <div class="work-time-input-container">
    <v-text-field
        :model-value="modelValue"
        label="Fertig bis (Uhrzeit)"
        type="text"
        inputmode="numeric"
        placeholder="HH:MM"
        :rules="timeRules"
        clearable
        variant="outlined"
        density="default"
        hide-details="auto"
        class="work-time-input"
        :disabled="disabled"
        @update:model-value="updateTime">
        <template #append-inner>
            <v-btn icon="mdi-clock-outline" variant="text" size="small"
                aria-label="Uhrzeit auswählen" title="Uhrzeit auswählen" :disabled="disabled" @click.stop="openPicker" />
        </template>
    </v-text-field>
    <v-dialog v-model="pickerOpen" max-width="400">
        <v-card v-if="pickerOpen">
            <VTimePicker v-model="draft" format="24hr" title="Uhrzeit auswählen" color="primary" width="360" class="work-time-picker" />
            <v-card-actions class="pa-4">
                <v-btn variant="text" @click="pickerOpen = false">Abbrechen</v-btn>
                <v-spacer />
                <v-btn color="primary" variant="flat" :disabled="!draft || disabled" @click="applyTime">Übernehmen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { VTimePicker } from 'vuetify/components/VTimePicker'

const props = defineProps({
    modelValue: { type: String, default: null },
    disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])
const pickerOpen = ref(false)
const draft = ref(null)
const timeRules = [value => !value || /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(value) || 'Uhrzeit im Format HH:MM eingeben.']
watch(() => props.disabled, disabled => { if (disabled) pickerOpen.value = false })

function openPicker() {
    if (props.disabled) return
    draft.value = props.modelValue || null
    pickerOpen.value = true
}

function updateTime(value) {
    if (!props.disabled) emit('update:modelValue', value)
}

function applyTime() {
    if (props.disabled || !draft.value) return
    emit('update:modelValue', draft.value)
    pickerOpen.value = false
}
</script>

<style scoped>
.work-time-input :deep(.v-field) {
    min-height: 64px;
}

.work-time-input :deep(input) {
    font-size: 1.125rem;
}

.work-time-picker {
    max-width: 100%;
    margin-inline: auto;
}
</style>
