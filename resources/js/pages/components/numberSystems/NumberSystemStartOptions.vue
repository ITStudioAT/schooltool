<template>
    <fieldset class="ns-start-options">
        <legend>Zeit &amp; Abschluss</legend>
        <label class="ns-field"><span>Zeitmessung</span><select v-model="model.timer_mode"><option value="none">Ohne Uhr · in Ruhe spielen</option><option value="elapsed">Stoppuhr · verstrichene Zeit</option><option value="countdown">Countdown · verbleibende Zeit</option></select></label>
        <label v-if="model.timer_mode === 'countdown'" class="ns-field"><span>Countdown in Minuten</span><input :value="model.countdown_minutes ?? model.planned_minutes" type="number" min="1" max="180" required @input="model.countdown_minutes = Number($event.target.value)" /></label>
        <p class="ns-small">Die Uhr beginnt beim gemeinsamen Start. Pausen zählen nicht mit; späterer Beitritt setzt sie nicht zurück. Beim Countdown darf nach Ablauf weitergespielt werden. Umfang und Schwierigkeit bleiben unabhängig.</p>
        <label class="ns-choice"><input v-model="model.ranking_enabled" type="checkbox" />Reihung am Ende anzeigen</label>
        <p class="ns-small">Nur innerhalb dieser Runde: abgeschlossene Abenteuer, dann bestätigte Datenwege; bei Gleichstand zählt mit Uhr die kürzere Zeit. Ohne Uhr zählt keine Geschwindigkeit. Gleiche Ergebnisse teilen einen Rang; Hilfen ziehen nichts ab.</p>
    </fieldset>
</template>
<script setup>
defineProps({ model: { type: Object, required: true } })
</script>
<style scoped>
.ns-start-options { display: grid; gap: 14px; margin: 22px 0; padding: 18px; border: 1px solid #657087; border-radius: 12px; }
.ns-start-options legend { padding: 0 8px; color: #efd18a; font-weight: 700; }
.ns-start-options .ns-choice { align-items: center; }
.ns-start-options input[type=checkbox] { width: 21px; height: 21px; min-height: 21px; padding: 0; accent-color: #e9c777; flex: 0 0 21px; }
</style>
