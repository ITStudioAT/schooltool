<template>
    <section v-if="state" class="check-progress mt-2" :aria-label="creation ? 'Fortschritt der Erstellung' : deletion ? 'Fortschritt der Löschung' : 'Fortschritt der Prüfung'" aria-live="polite">
        <strong class="check-progress-title">{{ progressTitle }}</strong>
        <span v-if="state.state === 'running' && !state.active && !state.completed?.length" class="check-progress-start text-caption">{{ creation ? 'Erstellung wird gestartet …' : deletion ? 'Löschung wird gestartet …' : 'Prüfung wird gestartet …' }}</span>
        <ul v-if="state.steps?.length" class="check-progress-list">
            <li v-for="step in state.steps" :key="step" :class="stepClass(step)">
                <span aria-hidden="true" class="check-progress-icon">{{ stepSymbol(step) }}</span>
                <span>{{ state.labels?.[step] || labels[step] || step }}</span>
                <span class="check-progress-caption">{{ stepText(step) }}</span>
            </li>
        </ul>
        <p v-if="state.detail" class="text-caption mt-2">{{ state.detail }}</p>
        <span v-if="unavailable" class="d-block text-caption mt-1">Der Fortschritt kann momentan nicht angezeigt werden.</span>
    </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({ state: { type: Object, default: null }, unavailable: Boolean, deletion: Boolean, creation: Boolean })
const progressTitle = computed(() => {
    if (props.state?.state === 'unconfirmed') return 'Aktueller Stand nicht bestätigt'
    if (props.state?.state === 'interrupted') return 'Erstellung unterbrochen'
    const operation = props.creation ? 'Erstellung' : props.deletion ? 'Löschung' : 'Prüfung'
    const status = props.state?.state === 'done' ? 'abgeschlossen' : props.state?.state === 'failed' ? 'unterbrochen' : 'läuft'
    return `${operation} ${status}`
})
const labels = {
    connection: 'Dropbox-Zuordnung prüfen',
    folder: 'Arbeitsordner ermitteln',
    deadline: 'Frist abgleichen',
    verification: 'Aktuellen Status ermitteln',
    collection: 'Arbeitsstand prüfen',
    delete_check: 'Zuordnungen und Daten prüfen',
    delete_folders: 'Prüfungsordner entfernen',
    delete_mc_files: 'MC-Testdateien entfernen',
    delete_records: 'Arbeit und Testdaten entfernen',
    exercise_delete_check: 'Prüfungsordner und Zuordnung prüfen',
    exercise_delete_contents: 'Unterlagen und Kandidatenordner löschen',
    exercise_delete_reset: 'Status auf „nicht angelegt“ zurücksetzen',
    exercise_delete_verify: 'Erhaltenen Prüfungsordner und Verknüpfung bestätigen',
    assessment_check: 'Ziel und Voraussetzungen prüfen',
    assessment_candidates: 'Kandidatinnen und Kandidaten prüfen',
    assessment_assignment: 'Aufgabenblatt prüfen',
    assessment_criteria: 'Beurteilungskriterien prüfen',
    assessment_target: 'Prüfungsziel prüfen',
    assessment_mc: 'MC-Zuordnung prüfen',
    assessment_folder: 'Prüfungsordner und Status anlegen oder übernehmen',
    assessment_documents: 'Unterlagen und Kandidatenordner anlegen oder bestätigen',
    assessment_verify: 'Paket und Status abschließend prüfen',
    mc_deadline: 'Abgabefrist speichern',
    mc_check: 'Katalog, Prüfungsgruppe und Freigabestatus prüfen',
    mc_prepare: 'Test vorbereiten oder vorhandenen Test übernehmen',
    mc_assign: 'Test mit der Arbeit verknüpfen',
}
function stepClass(step) {
    if (props.state?.completed?.includes(step)) return 'check-progress-done'
    if (props.state?.skipped?.includes(step)) return 'check-progress-pending'
    if (props.state?.active === step) {
        if (['unconfirmed', 'interrupted'].includes(props.state?.state)) return 'check-progress-unconfirmed'
        return props.state?.state === 'failed' ? 'check-progress-failed' : 'check-progress-active'
    }
    return 'check-progress-pending'
}
function stepText(step) {
    if (props.state?.completed?.includes(step)) return 'Erledigt'
    if (props.state?.skipped?.includes(step)) return 'Nicht ausgeführt'
    if (props.state?.active === step) {
        if (['unconfirmed', 'interrupted'].includes(props.state?.state)) return 'Stand offen'
        if (props.state?.state === 'failed') return 'Nicht abgeschlossen'
        return props.deletion || props.creation ? 'Wird ausgeführt' : 'Wird geprüft'
    }
    return 'Ausstehend'
}
function stepSymbol(step) {
    if (props.state?.completed?.includes(step)) return '✓'
    if (props.state?.active === step && props.state?.state === 'failed') return '!'
    if (props.state?.active === step && ['unconfirmed', 'interrupted'].includes(props.state?.state)) return '?'
    return '•'
}
</script>

<style scoped>
.check-progress { box-sizing: border-box; display: block; width: min(100%, 480px); min-width: 0; padding: 12px 14px; border: 1px solid #d8e4f0; border-radius: 10px; background: #f7fafc; color: #233b53; }
.check-progress-title { display: block; margin-bottom: 4px; }
.check-progress-start { display: block; margin-top: 4px; color: #536d83; }
.check-progress-list { list-style: none; padding: 0; margin: 8px 0 0; }
.check-progress-list li { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; padding: 4px 0; font-size: 0.8rem; }
.check-progress-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 20px; width: 20px; height: 20px; border-radius: 50%; background: #e4ebf1; color: #63798c; font-weight: 700; }
.check-progress-done .check-progress-icon { background: #d6f2e3; color: #08783e; }
.check-progress-active .check-progress-icon { background: #d8eaff; color: #1565b8; }
.check-progress-failed .check-progress-icon { background: #fde3e3; color: #b42318; }
.check-progress-unconfirmed .check-progress-icon { background: #fff1d6; color: #7c4e0c; }
.check-progress-caption { margin-left: auto; color: #536d83; font-size: 0.72rem; }
.check-progress-done .check-progress-caption { color: #08783e; }
.check-progress-failed .check-progress-caption { color: #b42318; }
</style>
