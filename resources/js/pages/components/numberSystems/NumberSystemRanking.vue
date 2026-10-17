<template>
    <section v-if="ranking" class="ns-panel ns-stack" style="margin-top:20px" aria-label="Reihung dieser Runde">
        <h2>{{ ranking.final ? 'Abschlussreihung' : 'Reihung zum Abschluss aktiviert' }}</h2>
        <p class="ns-small ns-muted">{{ ranking.rules }}</p>
        <p v-if="!ranking.final" class="ns-small">Die gemeinsame Reihung erscheint, sobald die Lehrkraft die Runde beendet. Bis dahin bleibt euer eigener Fortschritt sichtbar.</p>
        <ol v-else class="ns-ranking-list"><li v-for="row in ranking.entries" :key="row.id" :value="row.rank"><strong>{{ row.label }}{{ row.own ? ' · euer Spielstand' : '' }}</strong><span>{{ row.solved }} / {{ row.total }} bestätigt · {{ row.completed ? 'abgeschlossen' : 'noch offen' }}<template v-if="row.seconds !== null"> · {{ formatGameTime(row.seconds) }}</template></span></li></ol>
    </section>
</template>
<script setup>
import { formatGameTime } from './numberSystemTime'
defineProps({ ranking: Object })
</script>
<style scoped>
.ns-ranking-list { padding-left: 30px; }
.ns-ranking-list li { padding: 10px 4px; }
.ns-ranking-list span { display: block; margin-top: 4px; font-size: .86rem; color: #c7d1df; }
</style>
