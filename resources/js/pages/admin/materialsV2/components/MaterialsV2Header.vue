<template>
    <header class="materials-v2-header">
        <div class="materials-v2-brand">
            <img
                v-if="schoolLogoSrc"
                class="materials-v2-school-logo"
                :src="schoolLogoSrc"
                :alt="`${schoolName} Logo`" />
            <div v-else class="materials-v2-school-logo-placeholder">
                <v-icon size="22">mdi-school-outline</v-icon>
            </div>
            <div class="materials-v2-brand-copy">
                <h1 class="materials-v2-title">Materialien</h1>
                <p class="materials-v2-school-name">{{ schoolName }}</p>
            </div>
        </div>

        <v-text-field
            :model-value="search"
            class="materials-v2-header-search"
            variant="solo"
            density="compact"
            flat
            rounded="lg"
            clearable
            hide-details
            autocomplete="off"
            prepend-inner-icon="mdi-magnify"
            placeholder="Was suchst du? Wortteile und kleine Tippfehler sind erlaubt …"
            :loading="loading"
            @update:model-value="$emit('update:search', $event)"
            @click:clear="$emit('clear')" />
    </header>
</template>

<script setup>
defineProps({
    schoolLogoSrc: {
        type: String,
        default: '',
    },
    schoolName: {
        type: String,
        required: true,
    },
    search: {
        type: String,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
})

defineEmits(['update:search', 'clear'])
</script>

<style scoped>
.materials-v2-header {
    display: grid;
    min-height: 64px;
    padding: 9px 22px;
    align-items: center;
    grid-template-columns: minmax(190px, 270px) minmax(260px, 640px);
    justify-content: space-between;
    gap: 1.25rem;
    border-bottom: 1px solid rgba(23, 45, 59, 0.08);
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(18px);
}

.materials-v2-brand {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.65rem;
}

.materials-v2-school-logo,
.materials-v2-school-logo-placeholder {
    width: 36px;
    height: 36px;
    flex: 0 0 36px;
}

.materials-v2-school-logo {
    object-fit: contain;
}

.materials-v2-school-logo-placeholder {
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: rgba(var(--v-theme-primary), 0.08);
    color: rgb(var(--v-theme-primary));
}

.materials-v2-brand-copy {
    min-width: 0;
}

.materials-v2-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 800;
    letter-spacing: -0.015em;
    line-height: 1.15;
}

.materials-v2-school-name {
    margin: 0.15rem 0 0;
    overflow: hidden;
    color: var(--materials-v2-muted);
    font-size: 0.7rem;
    line-height: 1.15;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.materials-v2-header-search {
    width: 100%;
    max-width: 640px;
    justify-self: center;
}

.materials-v2-header-search :deep(.v-field) {
    min-height: 42px;
    border: 1px solid rgba(23, 45, 59, 0.12);
    background: rgba(255, 255, 255, 0.96);
    box-shadow: none;
    font-size: 0.88rem;
    transition: border-color 180ms ease, box-shadow 180ms ease;
}

.materials-v2-header-search :deep(.v-field--focused) {
    border-color: var(--materials-v2-accent);
    box-shadow: 0 0 0 4px rgba(255, 122, 50, 0.1);
}

@media (max-width: 900px) {
    .materials-v2-header {
        min-height: auto;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 0.65rem 1rem;
    }

    .materials-v2-header-search {
        max-width: none;
        grid-column: 1 / -1;
        grid-row: 2;
    }
}

@media (max-width: 700px) {
    .materials-v2-header {
        padding: 10px 12px;
    }
}
</style>
