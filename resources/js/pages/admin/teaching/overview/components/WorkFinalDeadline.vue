<template>
    <div class="d-flex align-center ga-2">
        <div>
            <div class="work-final-deadline__label text-caption">Endgültige Abgabefrist</div>
            <strong :class="`text-${color}`">{{ dateLabel }}<template v-if="date && time"> · {{ time }} Uhr</template></strong>
            <div v-if="pending" class="text-caption text-medium-emphasis">Zum Speichern vorgemerkt</div>
        </div>
        <v-btn icon="mdi-pencil-outline" size="small" variant="text" aria-label="Endgültige Abgabefrist bearbeiten"
            :disabled="disabled" @click="open" />
        <v-dialog v-model="editing" max-width="560">
            <v-card v-if="editing">
                <v-card-title>Endgültige Abgabefrist bearbeiten</v-card-title>
                <v-card-text class="d-flex flex-column ga-4">
                    <v-date-input :model-value="draftDate" label="Datum" variant="outlined" clearable
                        :hide-header="false" :picker-props="{ width: 360 }" @update:model-value="updateDate" />
                    <WorkTimeInput v-model="draftTime" :disabled="!draftDate" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" min-height="44" @click="editing = false">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" min-height="44" :disabled="invalidTime || disabled" @click="apply">Übernehmen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { parseLocalDate } from '@/helpers/date'
import WorkTimeInput from './WorkTimeInput.vue'

const props = defineProps({
    date: { type: [Date, String], default: null },
    time: { type: String, default: null },
    color: { type: String, default: 'primary' },
    disabled: Boolean,
    pending: Boolean,
})
const emit = defineEmits(['change'])
const editing = ref(false)
const draftDate = ref(null)
const draftTime = ref(null)
const invalidTime = computed(() => Boolean(draftTime.value && !/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(draftTime.value)))
const formatter = new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' })
const dateLabel = computed(() => props.date ? formatter.format(parseLocalDate(props.date)) : 'Nicht festgelegt')

function open() {
    draftDate.value = props.date || null
    draftTime.value = props.time || null
    editing.value = true
}

function updateDate(value) {
    draftDate.value = value
    if (!value) draftTime.value = null
}

function apply() {
    if (props.disabled || invalidTime.value) return
    emit('change', { date: draftDate.value || null, time: draftDate.value ? draftTime.value || null : null })
    editing.value = false
}

watch(() => props.disabled, disabled => { if (disabled) editing.value = false })
</script>
