<template>
    <span v-if="workSimulationCollection(work)" class="work-simulation-badge" :class="{ 'work-simulation-badge--compact': compact }"
        role="note" aria-label="Simulation – lokaler Testlauf" title="Simulation: lokaler Testlauf mit erzeugten Abgaben; echte Bewertungen bleiben unverändert.">
        <v-icon icon="mdi-flask-outline" size="18" aria-hidden="true" />
        <span v-if="!compact">Simulation</span>
    </span>
</template>

<script setup>
import { workSimulationCollection } from '@/helpers/workProgress'

defineProps({ work: { type: Object, default: null }, compact: Boolean })
</script>

<style scoped>
.work-simulation-badge { display: inline-flex; flex-shrink: 0; align-items: center; gap: 4px; padding: 2px 6px; border: 1px solid rgba(var(--v-theme-primary), .28); border-radius: 5px; background: rgba(var(--v-theme-primary), .08); color: rgb(var(--v-theme-primary)); font-size: 11px; font-weight: 700; line-height: 18px; white-space: nowrap; }
.work-simulation-badge--compact { padding: 0 2px; }
</style>
