<template>
    <section v-if="state" class="check-progress mt-2" aria-label="Fortschritt der Prüfung" aria-live="polite">
        <strong>{{ state.state === 'done' ? 'Prüfung abgeschlossen' : state.state === 'failed' ? 'Prüfung unterbrochen' : 'Prüfung läuft' }}</strong>
        <ul v-if="state.steps?.length" class="check-progress-list">
            <li v-for="step in state.steps" :key="step" :class="stepClass(step)">
                <span aria-hidden="true" class="check-progress-icon">{{ stepSymbol(step) }}</span>
                <span>{{ labels[step] || step }}</span>
                <span class="check-progress-caption">{{ stepText(step) }}</span>
            </li>
        </ul>
        <span v-else-if="state.state === 'running'" class="text-caption">Prüfung wird gestartet …</span>
        <span v-if="unavailable" class="d-block text-caption mt-1">Der Fortschritt kann momentan nicht angezeigt werden.</span>
    </section>
</template>

<script setup>
const props = defineProps({ state: { type: Object, default: null }, unavailable: Boolean })
const labels = {
    connection: 'Dropbox-Zuordnung prüfen',
    folder: 'Arbeitsordner ermitteln',
    deadline: 'Frist abgleichen',
    verification: 'Aktuellen Status ermitteln',
    collection: 'Arbeitsstand prüfen',
}
function stepClass(step) {
    if (props.state?.completed?.includes(step)) return 'check-progress-done'
    if (props.state?.skipped?.includes(step)) return 'check-progress-pending'
    if (props.state?.active === step) return props.state?.state === 'failed' ? 'check-progress-failed' : 'check-progress-active'
    return 'check-progress-pending'
}
function stepText(step) {
    if (props.state?.completed?.includes(step)) return 'Erledigt'
    if (props.state?.skipped?.includes(step)) return 'Nicht ausgeführt'
    if (props.state?.active === step) return props.state?.state === 'failed' ? 'Nicht abgeschlossen' : 'Wird geprüft'
    return 'Ausstehend'
}
function stepSymbol(step) {
    if (props.state?.completed?.includes(step)) return '✓'
    if (props.state?.active === step && props.state?.state === 'failed') return '!'
    return '•'
}
</script>

<style scoped>
.check-progress { min-width: min(100%, 320px); padding: 12px 14px; border: 1px solid #d8e4f0; border-radius: 10px; background: #f7fafc; color: #233b53; }
.check-progress-list { list-style: none; padding: 0; margin: 8px 0 0; }
.check-progress-list li { display: flex; align-items: center; gap: 8px; padding: 3px 0; font-size: 0.8rem; }
.check-progress-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 20px; width: 20px; height: 20px; border-radius: 50%; background: #e4ebf1; color: #63798c; font-weight: 700; }
.check-progress-done .check-progress-icon { background: #d6f2e3; color: #08783e; }
.check-progress-active .check-progress-icon { background: #d8eaff; color: #1565b8; }
.check-progress-failed .check-progress-icon { background: #fde3e3; color: #b42318; }
.check-progress-caption { margin-left: auto; color: #536d83; font-size: 0.72rem; }
.check-progress-done .check-progress-caption { color: #08783e; }
.check-progress-failed .check-progress-caption { color: #b42318; }
</style>
