<template>
    <v-card :to="to" border rounded="lg" class="teaching-tool-card">
        <v-card-text>
            <slot name="status" />
            <div class="d-flex align-baseline justify-space-between ga-2">
                <component :is="headingTag" :class="{ 'mt-4': $slots.status }">{{ title }}</component>
                <v-icon v-if="to" icon="mdi-arrow-right" color="primary" size="20" aria-hidden="true" />
            </div>
            <slot />
        </v-card-text>
        <v-card-actions v-if="$slots.actions" class="pa-4 pt-0">
            <slot name="actions" />
        </v-card-actions>
    </v-card>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    headingTag: { type: String, default: 'h3' },
    to: { type: String, default: undefined },
})
</script>

<style scoped>
.teaching-tool-card {
    display: flex;
    flex-direction: column;
    min-width: 0;
    height: 100%;
}

.teaching-tool-card :deep(.v-card-text) {
    flex: 1;
}

.teaching-tool-card :is(h2, h3) {
    font-size: 1.08rem;
    font-weight: 700;
    overflow-wrap: anywhere;
}
</style>
