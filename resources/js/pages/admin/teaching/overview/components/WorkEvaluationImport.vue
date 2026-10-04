<template>
    <v-dialog v-model="import_open" persistent max-width="850">
        <v-card>
            <v-card-title class="text-wrap">Importieren · {{ import_work?.title }}</v-card-title>
            <v-card-text>
                <p class="mb-3">Leistungsfeststellungs- oder Übungsordner auswählen. Auswertungen, Aufgabenversand und Ergebnisbenachrichtigungen werden automatisch geprüft und gemeinsam importiert.</p>
                <p class="text-caption mb-3">Importiert wird die gespeicherte Arbeit. Ungespeicherte Änderungen werden verworfen.</p>
                <v-file-input
                    v-model="import_files"
                    webkitdirectory
                    multiple
                    label="Leistungsfeststellungs- oder Übungsordner auswählen"
                    prepend-icon="mdi-folder-open-outline"
                    variant="outlined"
                    hide-details="auto"
                    :clearable="false"
                    :disabled="import_busy"
                    :loading="import_busy"
                    @change="selectFolder">
                    <template #selection><span class="work-import-selection">{{ import_selection }}</span></template>
                </v-file-input>
                <p v-if="import_busy" class="text-caption mt-3">Dateien prüfen und importieren …</p>
                <v-alert v-if="import_error" type="error" variant="tonal" class="mt-3">{{ import_error }}</v-alert>
                <v-alert v-if="import_summary" type="success" variant="tonal" class="mt-3">
                    <div v-for="message in import_summary.messages" :key="message">{{ message }}</div>
                    <div v-if="import_summary.missing?.length" class="text-caption mt-2">Nicht vorhanden (optional): {{ import_summary.missing.join(', ') }}.</div>
                </v-alert>
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn :disabled="import_busy" @click="import_open = false">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import { mapState } from 'pinia'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { importFolder } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'

export default {
    emits: ['imported'],
    data() {
        return { import_open: false, import_work: null, import_busy: false, import_summary: null, import_error: '', import_selection: '', import_files: [] }
    },
    computed: {
        ...mapState(useCourseStore, ['selected_course']),
    },
    watch: {
        'selected_course.id'() {
            this.import_open = false
            this.import_summary = null
            this.import_selection = ''
        },
    },
    methods: {
        isSelectedCourseWork(work) {
            return Boolean(work?.id && this.selected_course?.id
                && String(work.teaching_course_id) === String(this.selected_course.id))
        },
        openImport(work) {
            if (!this.isSelectedCourseWork(work) || this.import_busy) return
            this.import_work = work
            this.import_error = ''
            this.import_summary = null
            this.import_selection = ''
            this.import_files = []
            this.import_open = true
        },
        async selectFolder(event) {
            const work = this.import_work
            const selected = Array.from(event.target.files || [])
            if (!selected.length || this.import_busy || !this.isSelectedCourseWork(work)) return
            this.import_busy = true
            this.import_error = ''
            this.import_summary = null
            try {
                const relativePath = file => file.webkitRelativePath || file._relativePath || ''
                const folder = relativePath(selected[0]).split('/')[0]
                if (!folder || selected.some(file => relativePath(file).split('/')[0] !== folder)) {
                    throw new Error('Genau einen Leistungsfeststellungs- oder Übungsordner auswählen.')
                }
                const files = selected.filter(file => {
                    const source = relativePath(file).slice(folder.length + 1)
                    return /^Beurteilungen\/((?:(?:Beurteilung_|Gesamtuebersicht_Beurteilungen_).+|Gesamtübersicht|[^/_]+_[^/_]+)\.(?:md|pdf))$/i.test(source)
                        || /^Versand\/(?:Aufgaben|Ergebnisse)\/Versand_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\/Versandprotokoll\.txt$/i.test(source)
                }).sort((first, second) => relativePath(first).localeCompare(relativePath(second)))
                const pdfs = files.filter(file => /\.pdf$/i.test(file.name))
                const texts = files.filter(file => !/\.pdf$/i.test(file.name))
                this.import_selection = folder + ' · ' + files.length + ' Importdateien'
                if (pdfs.length > 20 || texts.length > 130 || files.reduce((sum, file) => sum + file.size, 0) > 6 * 1024 * 1024) {
                    throw new Error('Maximal 100 Auswertungen, 30 Versandprotokolle, 20 PDFs und insgesamt 6 MB pro Import auswählen.')
                }
                const documents = await Promise.all(texts.map(async file => {
                    try {
                        return { path: relativePath(file), text: new TextDecoder('utf-8', { fatal: true, ignoreBOM: true }).decode(await file.arrayBuffer()) }
                    } catch {
                        throw new Error(file.name + ': Textdatei kann nicht als UTF-8 gelesen werden.')
                    }
                }))
                const encodedDocuments = JSON.stringify(documents)
                if (new TextEncoder().encode(encodedDocuments).length + pdfs.reduce((sum, file) => sum + file.size, 0) > 6 * 1024 * 1024) {
                    throw new Error('Dateiauswahl überschreitet 6 MB.')
                }
                const payload = new FormData()
                payload.append('folder', folder)
                payload.append('documents', encodedDocuments)
                payload.append('pdf_paths', JSON.stringify(pdfs.map(relativePath)))
                for (const file of pdfs) payload.append('pdfs[]', file, file.name)
                const response = await axios.post(importFolder.url(work.id), payload)
                if (!this.import_open || this.import_work !== work || !this.isSelectedCourseWork(work)) return
                this.import_summary = response.data.summary
                this.$emit('imported', response.data.data)
            } catch (error) {
                this.import_error = Object.values(error.response?.data?.errors || {}).flat().join(' ')
                    || error.response?.data?.message || error.message || 'Import fehlgeschlagen. Keine Dateien wurden übernommen.'
            } finally {
                this.import_busy = false
                event.target.value = ''
            }
        },
    },
}
</script>

<style scoped>
.work-import-selection {
    line-height: 1.35;
    overflow-wrap: anywhere;
    white-space: normal;
}
</style>
