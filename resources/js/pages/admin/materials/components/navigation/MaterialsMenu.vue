<template>
    <v-card tile flat color="transparent" class="materials-menu d-flex flex-row flex-wrap ga-2 w-100 mb-5">
        <v-btn
            class="materials-menu-btn"
            :class="{ 'materials-menu-btn--active': modelValue === 'overview' }"
            rounded="pill"
            size="large"
            variant="flat"
            prepend-icon="mdi-home"
            :disabled="disabled"
            @click="$emit('update:modelValue', 'overview')">
            Übersicht
        </v-btn>

        <v-btn
            class="materials-menu-btn"
            :class="{ 'materials-menu-btn--active': modelValue === 'shares' }"
            rounded="pill"
            size="large"
            variant="flat"
            prepend-icon="mdi-share-variant-outline"
            :disabled="disabled"
            @click="$emit('update:modelValue', 'shares')">
            Freigaben
        </v-btn>

        <v-btn
            class="materials-menu-btn"
            :class="{ 'materials-menu-btn--active': modelValue === 'subjects' }"
            rounded="pill"
            size="large"
            variant="flat"
            prepend-icon="mdi-book-education-outline"
            :disabled="disabled"
            @click="$emit('update:modelValue', 'subjects')">
            Fächer
        </v-btn>

        <v-btn
            class="materials-menu-btn"
            :class="{ 'materials-menu-btn--active': modelValue === 'new_material' }"
            rounded="pill"
            size="large"
            variant="flat"
            prepend-icon="mdi-plus-circle-outline"
            :disabled="disabled"
            @click="$emit('update:modelValue', 'new_material')">
            Neues Material
        </v-btn>

        <v-btn
            class="materials-menu-btn"
            :class="{ 'materials-menu-btn--active': modelValue === 'settings' }"
            rounded="pill"
            size="large"
            variant="flat"
            prepend-icon="mdi-cog-outline"
            :disabled="disabled"
            @click="$emit('update:modelValue', 'settings')">
            Einstellungen
        </v-btn>
    </v-card>
</template>

<script>
export default {
    name: 'MaterialsMenu',
    props: {
        modelValue: {
            type: String,
            required: true,
        },
        disabled: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['update:modelValue'],
}
</script>

<style scoped>
.materials-menu-btn {
    min-width: 190px;
    border: 1px solid rgba(253, 128, 46, 0.35);
    background: rgba(248, 239, 231, 0.18);
    color: #f8efe7;
    font-weight: 700;
    letter-spacing: 0.1px;
    transition: transform 0.15s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
}

.materials-menu-btn:hover {
    transform: translateY(-1px);
    border-color: rgba(253, 128, 46, 0.65);
    background: rgba(248, 239, 231, 0.26);
}

.materials-menu-btn--active {
    border-color: rgba(253, 128, 46, 0.95);
    background: linear-gradient(155deg, #fd802e 0%, #ff8f42 100%);
    color: #233d4c;
    box-shadow: 0 8px 20px rgba(253, 128, 46, 0.3);
}

@media (max-width: 640px) {
    .materials-menu-btn {
        flex: 1 1 100%;
        width: 100%;
    }
}
</style>
