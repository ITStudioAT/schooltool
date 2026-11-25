<template>
    <fieldset class="submission-methods" :disabled="disabled || !!lockReason">
        <legend>Wie sollen die Schüler:innen abgeben?</legend>
        <label><input v-model="channel" type="radio" :name="group" value="portal" /> Datei unter „Meine Aufgaben“ hochladen</label>
        <p v-if="portalClosureNotice" class="text-body-2 text-error font-weight-bold mb-2" role="status">{{ portalClosureNotice }}</p>
        <label><input v-model="channel" type="radio" :name="group" value="email" /> Datei per E-Mail schicken</label>
        <v-text-field v-if="channel === 'email'" v-model.trim="email" type="email" maxlength="254"
            label="E-Mail-Adresse für die Abgabe" :disabled="disabled || !!lockReason" class="mt-2" hide-details="auto" />
        <p v-if="lockReason" class="text-body-2 mt-2" role="status">{{ lockMessages[lockReason] }}</p>
    </fieldset>
</template>

<script setup>
import { useId } from 'vue'

defineProps({ disabled: Boolean, lockReason: String, portalClosureNotice: String })
const lockMessages = {
    started: 'Abgabeweg nicht mehr änderbar. Die Bearbeitung hat bereits begonnen.',
    email_sent: 'Abgabeweg nach Aufgabenversand gesperrt. Ein Bearbeitungsbeginn außerhalb von Schooltool ist nicht überprüfbar.',
    email_pending: 'Der Aufgabenversand läuft oder muss noch abgeglichen werden. Der Abgabeweg ist derzeit gesperrt.',
    ended: 'Abgabeweg nicht mehr änderbar. Die Freigabe ist beendet.',
    deadline: 'Der Abgabeweg ist nach Schließung der Portalabgabe gesperrt.',
}
const channel = defineModel('channel', { type: String })
const email = defineModel('email', { type: String })
const group = useId()
</script>

<style scoped>
.submission-methods { padding: 0; border: 0; min-width: 0; }
.submission-methods legend { font-weight: 600; }
.submission-methods label:has(> input[type="radio"]) { display: flex; gap: .6rem; align-items: center; min-height: 44px; cursor: pointer; }
.submission-methods input[type="radio"] { accent-color: rgb(var(--v-theme-primary)); }
</style>
