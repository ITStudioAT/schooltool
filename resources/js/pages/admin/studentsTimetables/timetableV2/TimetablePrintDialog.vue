<template>
    <v-dialog
        :model-value="modelValue"
        persistent
        max-width="460"
        @update:model-value="$emit('update:modelValue', $event)">
        <v-card rounded="lg">
            <v-card-title class="d-flex align-center ga-2">
                <v-icon icon="mdi-printer-outline" />
                Stundenplan drucken
            </v-card-title>
            <v-card-text>
                <v-checkbox
                    :model-value="singleWeeks"
                    label="Einzelne Wochen drucken"
                    color="primary"
                    density="compact"
                    hide-details
                    @update:model-value="$emit('update:singleWeeks', $event)" />
                <v-checkbox
                    :model-value="courseList"
                    label="Modulliste"
                    color="primary"
                    density="compact"
                    hide-details
                    @update:model-value="$emit('update:courseList', $event)" />
                <v-checkbox
                    :model-value="courseOverview"
                    label="Modulübersicht"
                    color="primary"
                    density="compact"
                    hide-details
                    @update:model-value="$emit('update:courseOverview', $event)" />
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn variant="text" :disabled="exporting" @click="$emit('update:modelValue', false)">
                    Abbrechen
                </v-btn>
                <v-btn
                    color="error"
                    variant="flat"
                    prepend-icon="mdi-printer-outline"
                    :disabled="!canPrint"
                    :loading="exporting"
                    @click="$emit('print')">
                    Drucken
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
defineProps({
    modelValue: {
        type: Boolean,
        required: true,
    },
    singleWeeks: {
        type: Boolean,
        required: true,
    },
    courseList: {
        type: Boolean,
        required: true,
    },
    courseOverview: {
        type: Boolean,
        required: true,
    },
    canPrint: {
        type: Boolean,
        required: true,
    },
    exporting: {
        type: Boolean,
        required: true,
    },
})

defineEmits([
    'update:modelValue',
    'update:singleWeeks',
    'update:courseList',
    'update:courseOverview',
    'print',
])
</script>
