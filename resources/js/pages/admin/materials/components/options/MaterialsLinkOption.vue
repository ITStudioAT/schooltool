<template>
    <v-card class="option-card option-card--link pa-5" rounded="xl" elevation="0">
        <v-icon size="44" class="mb-3 option-icon">mdi-link-variant</v-icon>
        <div class="text-h6 font-weight-bold mb-3">Link einfügen</div>
        <v-text-field
            v-model="linkInput"
            label="Link"
            placeholder="https://..."
            prepend-inner-icon="mdi-web"
            variant="outlined"
            clearable
            hide-details="auto"
            density="comfortable"
            class="mb-4 option-field" />
        <v-btn block size="large" variant="flat" class="option-btn" @click="$emit('link-submit', linkInput)">Link übernehmen</v-btn>
    </v-card>
</template>

<script>
export default {
    name: 'MaterialsLinkOption',
    emits: ['link-submit'],

    data() {
        return {
            linkInput: '',
        }
    },
}
</script>

<style scoped>
.option-card {
    min-height: 320px;
    border: 1px solid rgba(253, 128, 46, 0.25);
    background: rgba(255, 255, 255, 0.7);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.option-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(253, 128, 46, 0.2);
    border-color: rgba(253, 128, 46, 0.45);
}

.option-card--link {
    background: linear-gradient(180deg, rgba(35, 61, 76, 0.08), rgba(255, 255, 255, 0.95));
}

.option-icon {
    color: var(--pumpkin);
}

.option-btn {
    background: linear-gradient(155deg, var(--pumpkin) 0%, var(--pumpkin-light) 100%);
    color: var(--charcoal);
    font-weight: 700;
}

:deep(.option-field .v-field--variant-outlined .v-field__outline) {
    color: rgba(253, 128, 46, 0.45);
}

:deep(.option-field .v-field.v-field--focused .v-field__outline) {
    color: var(--pumpkin);
}
</style>
