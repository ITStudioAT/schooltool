<template>
    <v-card class="create-form-card pa-5 pa-md-6" rounded="xl" elevation="0">
        <div class="text-h6 font-weight-bold mb-2">Neues Material anlegen</div>
        <div class="text-body-2 form-subline mb-4">Gib einen Titel ein, dann kann gespeichert werden.</div>

        <v-text-field
            :model-value="title"
            label="Titel"
            placeholder="z. B. Bruchrechnen Arbeitsblatt"
            variant="outlined"
            density="comfortable"
            clearable
            autofocus
            hide-details="auto"
            @update:modelValue="$emit('update:title', $event)" />

        <v-textarea
            :model-value="description"
            label="Beschreibung (optional)"
            placeholder="Kurze Beschreibung"
            variant="outlined"
            density="comfortable"
            rows="3"
            auto-grow
            hide-details="auto"
            class="mt-3"
            @update:modelValue="$emit('update:description', $event)" />

        <div class="d-flex flex-wrap justify-end ga-2 mt-5">
            <v-btn variant="text" @click="$emit('cancel')">Abbrechen</v-btn>
            <v-btn color="primary" variant="flat" :disabled="!canSave || isSaving" @click="$emit('save')">Speichern</v-btn>
        </div>
    </v-card>
</template>

<script>
export default {
    name: 'MaterialsCreateInlineForm',
    props: {
        title: {
            type: String,
            default: '',
        },
        description: {
            type: String,
            default: '',
        },
        isSaving: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['update:title', 'update:description', 'save', 'cancel'],
    computed: {
        canSave() {
            return String(this.title || '').trim().length > 0
        },
    },
}
</script>

<style scoped>
.create-form-card {
    border: 1px solid rgba(253, 128, 46, 0.35);
    background: rgba(255, 255, 255, 0.82);
}

.form-subline {
    color: #314d5d;
}
</style>
