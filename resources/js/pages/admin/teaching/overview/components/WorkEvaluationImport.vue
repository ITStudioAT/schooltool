<template>
        <v-dialog v-model="evaluation_import_open" persistent max-width="1000">
            <v-card>
                <v-card-title>Auswertungsordner importieren · {{ evaluation_import_work?.title }}</v-card-title>
                <v-card-text>
                    <p class="mb-3">Leistungsfeststellungsordner oder Unterordner „Beurteilungen“ auswählen. Nur die Auswertungen (.md) und gleichnamigen PDFs werden gelesen.</p>
                    <p class="text-caption mb-3">Der Import verwendet die gespeicherte Arbeit. Ungespeicherte Änderungen in dieser Arbeit werden beim Import verworfen.</p>
                    <v-file-input
                        :model-value="evaluation_import_files"
                        webkitdirectory
                        multiple
                        label="Auswertungsordner auswählen"
                        prepend-icon="mdi-folder-open-outline"
                        variant="outlined"
                        hide-details="auto"
                        :clearable="false"
                        :disabled="evaluation_import_busy"
                        @change="selectEvaluationFolder">
                        <template #selection>
                            {{ evaluation_import_files[0]?.webkitRelativePath?.split('/')[0] || 'Ordner' }} · {{ evaluation_import_files.length }} Dateien
                        </template>
                    </v-file-input>
                    <v-alert v-if="evaluation_import_error" type="error" variant="tonal" class="mt-3">{{ evaluation_import_error }}</v-alert>
                    <div v-if="evaluation_import_preview" class="mt-3">
                        <p>{{ evaluation_import_preview.title }} · {{ evaluation_import_preview.date }} · maximal {{ evaluation_import_preview.maximum }} Punkte</p>
                        <p>Gesamt-PDF: {{ evaluation_import_preview.pdf?.name || 'Fehlt – kein Gesamtanhang' }}</p>
                        <p v-if="evaluation_import_preview.pdf && evaluation_import_preview.previous_pdf" class="text-caption">{{ evaluation_import_preview.pdf.sha256 === evaluation_import_preview.previous_pdf.sha256 ? 'Identische Gesamt-PDF bereits vorhanden.' : `Ersetzt: ${evaluation_import_preview.previous_pdf.name}` }}</p>
                        <p class="text-caption mb-3">Der letzte gültige Import ersetzt Punkte, Bewertung, Kommentar und die importierte PDF dieser Person. Die importierte Gesamt-PDF wird ebenfalls ersetzt. Offene Abgaben behalten ihre Werte, andere Anhänge bleiben erhalten; identische PDFs werden nicht doppelt angehängt.</p>
                        <div v-for="(row, index) in evaluation_import_preview.rows" :key="index" class="pa-3 mb-2 border rounded">
                            <strong>{{ row.person }} / {{ row.class }}</strong> · {{ row.status }}
                            <div v-if="row.status === 'Übernehmen'">Punkte: {{ row.previous.points ?? '—' }} → {{ row.points }} · Bewertung: {{ row.previous.grades || '—' }} → {{ row.points }}</div>
                            <div>PDF: {{ row.pdf?.name || 'Fehlt – kein persönlicher Anhang' }}{{ !row.student_id && row.pdf ? ' (wird übersprungen)' : '' }}</div>
                            <div v-if="row.student_id && row.pdf && row.previous_pdf" class="text-caption">{{ row.pdf.sha256 === row.previous_pdf.sha256 ? 'Identische PDF bereits vorhanden.' : `Ersetzt: ${row.previous_pdf.name}` }}</div>
                            <details v-if="row.status === 'Übernehmen'">
                                <summary>Kommentar ersetzen: vorher / nachher</summary>
                                <pre class="evaluation-import-comment">{{ row.previous.comments || '—' }}</pre>
                                <pre class="evaluation-import-comment">{{ row.comment }}</pre>
                            </details>
                        </div>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-btn :disabled="evaluation_import_busy" @click="evaluation_import_open = false">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn color="success" :loading="evaluation_import_busy" :disabled="!evaluation_import_preview?.can_import || evaluation_import_busy" @click="applyEvaluationImport">Angezeigte Änderungen importieren</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
</template>

<script>
import { mapState } from 'pinia'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { importEvaluations } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'

export default {
    emits: ['imported'],
    data() {
        return {
            evaluation_import_open: false,
            evaluation_import_work: null,
            evaluation_import_preview: null,
            evaluation_import_payload: null,
            evaluation_import_error: '',
            evaluation_import_busy: false,
            evaluation_import_files: [],
        }
    },
    computed: {
        ...mapState(useCourseStore, ['selected_course']),
    },
    watch: {
        'selected_course.id'() {
            this.evaluation_import_open = false
            this.evaluation_import_preview = null
            this.evaluation_import_payload = null
        },
    },
    methods: {
        isSelectedCourseWork(work) {
            return Boolean(work?.id && this.selected_course?.id
                && String(work.teaching_course_id) === String(this.selected_course.id))
        },
        openEvaluationImport(work) {
            if (!this.isSelectedCourseWork(work)) return
            this.evaluation_import_files = []
            this.evaluation_import_work = work
            this.evaluation_import_preview = null
            this.evaluation_import_payload = null
            this.evaluation_import_error = ''
            this.evaluation_import_open = true
        },
        async selectEvaluationFolder(event) {
            this.evaluation_import_preview = null
            this.evaluation_import_payload = null
            this.evaluation_import_error = ''
            const work = this.evaluation_import_work
            const selectedFiles = Array.from(event.target.files || [])
            if (!selectedFiles.length || !this.isSelectedCourseWork(work)) return
            this.evaluation_import_files = selectedFiles
            this.evaluation_import_busy = true
            try {
                const files = selectedFiles.filter(file => {
                    const parts = (file.webkitRelativePath || file._relativePath || '').split('/')
                    return parts.at(-2)?.toLowerCase() === 'beurteilungen'
                        && /^(?:(Beurteilung_|Gesamtuebersicht_Beurteilungen_).+|Gesamtübersicht)\.(md|pdf)$/i.test(file.name)
                }).sort((left, right) => left.name.localeCompare(right.name))
                const reports = files.filter(file => /\.md$/i.test(file.name))
                const pdfs = files.filter(file => /\.pdf$/i.test(file.name))
                if (!reports.length) throw new Error('Keine Markdown-Auswertungen im Unterordner „Beurteilungen“ gefunden.')
                if (reports.length > 100 || pdfs.length > 20 || files.reduce((sum, file) => sum + file.size, 0) > 6 * 1024 * 1024) {
                    throw new Error('Maximal 100 Markdown-Dateien, 20 PDFs und insgesamt 6 MB pro Import auswählen.')
                }
                const documents = await Promise.all(reports.map(async file => ({ name: file.name, text: await file.text() })))
                const payload = new FormData()
                payload.append('reports', JSON.stringify(documents))
                for (const file of pdfs) payload.append('pdfs[]', file, file.name)
                const response = await axios.post(importEvaluations.url(work.id), payload)
                if (!this.evaluation_import_open || this.evaluation_import_work !== work || !this.isSelectedCourseWork(work)) return
                this.evaluation_import_payload = payload
                this.evaluation_import_preview = response.data.preview
            } catch (error) {
                this.evaluation_import_error = error.response?.data?.message || error.message || 'Importvorschau konnte nicht erstellt werden.'
            } finally {
                this.evaluation_import_busy = false
                event.target.value = ''
            }
        },
        async applyEvaluationImport() {
            const work = this.evaluation_import_work
            if (!this.evaluation_import_preview?.can_import || this.evaluation_import_busy || !this.isSelectedCourseWork(work)) return
            this.evaluation_import_busy = true
            this.evaluation_import_error = ''
            try {
                this.evaluation_import_payload.set('apply', '1')
                this.evaluation_import_payload.set('hash', this.evaluation_import_preview.hash)
                const response = await axios.post(importEvaluations.url(work.id), this.evaluation_import_payload)
                this.evaluation_import_open = false
                if (this.isSelectedCourseWork(work)) {
                    this.$emit('imported', response.data.data)
                }
            } catch (error) {
                this.evaluation_import_preview = null
                this.evaluation_import_error = error.response?.data?.message || 'Import fehlgeschlagen. Ordner erneut für eine aktuelle Vorschau auswählen.'
            } finally {
                this.evaluation_import_busy = false
            }
        },
    },
}
</script>

<style scoped>
.evaluation-import-comment {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    font: inherit;
}
</style>
