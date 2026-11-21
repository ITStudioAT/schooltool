<template>
    <section class="work-creation-progress" aria-label="Fortschritt beim Anlegen" aria-live="polite">
        <h3>Arbeit wird angelegt</h3>
        <ol>
            <li v-for="(label, index) in visibleSteps" :key="label" :aria-current="index === step ? 'step' : undefined">
                <span>{{ label }}</span>
                <span v-if="index < step" class="text-success">Erledigt</span>
                <span v-else-if="index > step" class="text-medium-emphasis">Ausstehend</span>
                <span v-else-if="status === 'failed'" class="text-error">Nicht abgeschlossen</span>
                <span v-else-if="status === 'waiting'" class="text-medium-emphasis">Wartet</span>
                <strong v-else class="text-primary">Läuft …</strong>
            </li>
        </ol>
    </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    step: { type: Number, default: 0 },
    status: { type: String, default: 'active' },
    withDropbox: Boolean,
})

const steps = [
    'Arbeit speichern',
    'Dropbox-Verbindung prüfen',
    'Lernmodul in Dropbox prüfen',
    'Lernmodul verknüpfen und Prüfungsordner anlegen',
]
const visibleSteps = computed(() => props.withDropbox ? steps : steps.slice(0, 1))
</script>

<style scoped>
.work-creation-progress {
    padding: 12px 16px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 8px;
}

ol {
    margin-top: 8px;
    padding-inline-start: 24px;
}

li {
    padding-block: 4px;
}

li > :last-child {
    margin-inline-start: 8px;
}
</style>
