<template>
    <details
        :key="importRecord.id"
        @toggle="onToggle"
        class="mb-4"
        data-testid="tt-diagnostics">
        <summary class="font-weight-bold pa-2">
            Betroffene TT-Datensätze und Prüfgründe ({{ importRecord.tt_skipped_invalid }})
        </summary>
        <p v-if="loading" class="pa-2">Prüfgründe werden geladen …</p>
        <v-alert v-else-if="error" type="error" variant="tonal">
            {{ error }}
            <v-btn variant="text" @click="loadDiagnostics">Erneut laden</v-btn>
        </v-alert>
        <template v-else-if="diagnostics?.source_available">
            <p class="pa-2">
                Quelldatei: {{ importRecord.original_filename }}.
                Zeilen zählen ab 1 einschließlich Leerzeilen; Felder sind durch Tabulatoren getrennt
                (Feld 1 = TT). Pro Datensatz werden alle Prüfgründe angezeigt.
            </p>
            <v-data-table
                :headers="headers"
                :items="diagnostics.records"
                item-value="line_number"
                :items-per-page="25"
                :items-per-page-options="[25, 50, 100]"
                items-per-page-text="Datensätze pro Seite"
                page-text="{0}–{1} von {2}"
                no-data-text="In der verfügbaren Quelldatei wurden keine abgelehnten TT-Datensätze gefunden."
                density="compact">
                <template #item.line_number="{ item }">
                    <strong>Zeile {{ item.line_number }}</strong>
                </template>
                <template #item.source_identifier="{ item }">
                    <div>Quellkennung: {{ diagnosticValue(item.source_identifier) }}</div>
                    <div>Datum: {{ diagnosticValue(item.date) }} · Stunde: {{ diagnosticValue(item.period) }}</div>
                    <div>{{ diagnosticValue(item.starts_at) }}–{{ diagnosticValue(item.ends_at) }}</div>
                    <div>Kurs/Klasse: {{ diagnosticValue(item.course) }}</div>
                </template>
                <template #item.errors="{ item }">
                    <div v-for="error in item.errors" :key="error.column" class="py-2">
                        <strong>Feld {{ error.column }}: {{ error.field }}</strong>
                        <div>Wert: <code>{{ diagnosticValue(error.value) }}</code></div>
                        <div>{{ error.reason }}</div>
                        <div>Erwartet: {{ error.expected }}</div>
                    </div>
                </template>
            </v-data-table>
        </template>
        <p v-else-if="diagnostics" class="pa-2">
            Die archivierte Quelldatei ist nicht verfügbar oder nicht lesbar.
            Die gespeicherte Fehleranzahl bleibt bestehen; für genaue Zeilendetails wird die ursprüngliche TXT-Datei benötigt.
        </p>
    </details>
</template>

<script>
import { VDataTable } from 'vuetify/components'
import { show as showTimetableImport } from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/TimetableImportController'

export default {
    name: 'TimetableImportDiagnostics',
    components: { VDataTable },
    props: {
        importRecord: { type: Object, required: true },
    },
    data() {
        return {
            loadedDiagnostics: null,
            loading: false,
            error: '',
            headers: [
                { title: 'Quelldateizeile', key: 'line_number', sortable: false },
                { title: 'Datensatz', key: 'source_identifier', sortable: false },
                { title: 'Feld, Wert und Prüfgrund', key: 'errors', sortable: false },
            ],
        }
    },
    computed: {
        diagnostics() {
            return this.importRecord.tt_diagnostics || this.loadedDiagnostics
        },
    },
    methods: {
        diagnosticValue(value) {
            if (value === null || value === undefined) return '(Feld fehlt)'
            if (value === '') return '(leer)'
            if (String(value).trim() === '') return '(nur Leerzeichen)'
            return String(value)
        },
        onToggle(event) {
            if (event.target.open) this.loadDiagnostics()
        },
        async loadDiagnostics() {
            if (this.loading || this.diagnostics) return

            this.loading = true
            this.error = ''
            try {
                const response = await axios.get(showTimetableImport.url(this.importRecord.id))
                this.loadedDiagnostics = response.data.data.tt_diagnostics
            } catch {
                this.error = 'Die Prüfgründe konnten nicht geladen werden.'
            } finally {
                this.loading = false
            }
        },
    },
}
</script>
