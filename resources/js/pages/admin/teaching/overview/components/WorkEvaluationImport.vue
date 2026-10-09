<template>
    <v-dialog v-model="import_open" persistent scrollable max-width="850">
        <v-card>
            <v-card-title class="text-wrap">Importieren · {{ import_work?.title }}</v-card-title>
            <v-card-text>
                <p class="mb-3">Leistungsfeststellungs- oder Übungsordner auswählen. Darin werden Schooltool-Bewertungen.json, die referenzierten PDFs und Versandprotokolle geprüft. Zuerst erscheint eine Vorschau; die Übernahme erfolgt separat.</p>
                <p class="text-caption mb-3">Importiert wird die gespeicherte Arbeit. Ungespeicherte Änderungen werden verworfen.</p>
                <WorkDropboxSource :work="import_work" :active="import_open" :busy="import_busy" @preview="previewDropbox(false)" @changed="json_preview = null; import_summary = null" />
                <v-file-input
                    v-if="!directory_access_supported"
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
                    @change="selectJsonFolder">
                    <template #selection><span class="work-import-selection">{{ import_selection }}</span></template>
                </v-file-input>
                <div v-else>
                    <v-btn class="work-import-folder-button" variant="outlined" prepend-icon="mdi-folder-open-outline" :disabled="import_busy" @click="chooseDirectory">Ordner auswählen</v-btn>
                    <p v-if="import_selection" class="work-import-selection mt-2">{{ import_selection }}</p>
                </div>
                <p v-if="import_busy" class="text-caption mt-3">Dateien prüfen und importieren …</p>
                <v-alert v-if="import_error" type="error" variant="tonal" class="mt-3">{{ import_error }}</v-alert>
                <v-btn v-if="import_error && (json_uploads || import_source === 'dropbox')" class="mt-3" variant="outlined"
                    :disabled="import_busy" :loading="import_busy" @click="sendJson(false)">
                    Vorschau erneut laden
                </v-btn>
                <v-alert v-if="import_summary" type="success" variant="tonal" class="mt-3">
                    <div v-for="message in import_summary.messages" :key="message">{{ message }}</div>
                    <div v-if="import_summary.missing?.length" class="text-caption mt-2">Nicht vorhanden (optional): {{ import_summary.missing.join(', ') }}.</div>
                </v-alert>
                <div v-if="json_preview" class="mt-4 json-preview">
                    <p><strong>Ziel:</strong> {{ json_preview.target_work.course_title }} · {{ json_preview.target_work.title }}</p>
                    <p><strong>Quelle:</strong> {{ json_preview.exercise.title }} · {{ json_preview.exercise.subject }} · {{ json_preview.exercise.group }}</p>
                    <p>{{ json_preview.exercise.date }} · Prüfstand: {{ json_preview.exercise.checkpoint }} · Maximum: {{ minorPoints(json_preview.maximum_minor) }}</p>
                    <p v-if="json_preview.exercise.deadline"><strong>Paketfrist:</strong> {{ json_preview.exercise.deadline }}</p>
                    <v-alert v-if="json_preview.deadline_context" :type="json_preview.deadline_context.requires_review ? 'warning' : 'info'" variant="tonal" class="mt-3 mb-3">
                        <strong>Aktuelle Lehrkraftfrist</strong>
                        <p v-if="json_preview.deadline_context.current_at">{{ assessmentTimeText(json_preview.deadline_context.current_at) }} (Europe/Vienna).</p>
                        <p v-else>{{ json_preview.deadline_context.current_date ?? 'Datum fehlt' }} · {{ json_preview.deadline_context.current_time ?? 'Uhrzeit fehlt' }}.</p>
                        <p>{{ json_preview.deadline_context.message }}</p>
                    </v-alert>
                    <div v-if="json_preview.final_download" class="mt-3">
                        <p><strong>Lokaler Abschlussdownload:</strong> {{ assessmentTimeText(json_preview.final_download.download_completed_at) }} · Frist: {{ assessmentTimeText(json_preview.final_download.deadline_at) }}</p>
                        <p>{{ json_preview.final_download.scope }}</p>
                        <p>Ein leerer zugeordneter Bestand wird mit 0 Punkten bewertet: Innerhalb der Frist nicht abgegeben.</p>
                    </div>
                    <p v-if="json_preview.overview_pdf">Gesamtübersicht: {{ json_preview.overview_pdf.filename }} · {{ json_preview.overview_will_replace ? 'wird übernommen' : 'unverändert' }}</p>
                    <p v-if="json_preview.submission_check">Abgabeprüfung: {{ json_preview.submission_check.state === 'complete' ? 'abgeschlossen' : 'noch offen' }} · Prüfstand: {{ assessmentTimeText(json_preview.submission_check.checked_at) }}</p>
                    <v-alert v-if="!json_preview.can_import" type="error" variant="tonal" class="mt-3">Das Paket kann noch nicht übernommen werden. Hinweise zu Frist und Zuordnungen prüfen.</v-alert>
                    <div v-for="dispatch in json_preview.dispatches || []" :key="dispatch.source" class="border rounded pa-3 mt-3">
                        <strong>{{ dispatch.purpose === 'results' ? 'Ergebnisbenachrichtigung' : 'Aufgabenversand' }}</strong>
                        <p>{{ dispatch.source }}</p>
                        <p>{{ dispatch.rows.filter(row => row.accepted).length }} Personen mit bestätigtem Live-Versand</p>
                        <p v-for="row in dispatch.rows" :key="row.person + row.student_id">{{ row.person }} · {{ row.status }}</p>
                    </div>
                    <div v-for="row in json_preview.rows" :key="row.participant_id" class="border rounded pa-3 mt-3">
                        <p><strong>{{ row.identity.first_name }} {{ row.identity.last_name }} / {{ row.identity.class_name }} · {{ row.identity.group_name }}</strong></p>
                        <p v-if="row.target">Zugeordnet: {{ row.target.first_name }} {{ row.target.last_name }} / {{ row.target.class_name }} · {{ row.target.group_name }}</p>
                        <p>{{ row.status }} · {{ changeLabel(row.change) }}</p>
                        <p v-if="row.deadline_requires_review">Fristbezug erneut prüfen; bestehende Bewertung bleibt erhalten.</p>
                        <p>Abgabe: {{ stateLabel(row.submission_state) }} · {{ row.submission_note }}</p>
                        <p>Bewertung: {{ stateLabel(row.evaluation_state) }} · {{ row.evaluation_note }}</p>
                        <p>Abgabe eingesammelt · bisher: {{ assessmentTimeText(row.previous_event_times?.email_collected_at) }} · neu: {{ assessmentTimeText(row.event_times?.email_collected_at) }}</p>
                        <p>Beurteilung abgeschlossen · bisher: {{ assessmentCompletionText(row.previous_event_times?.evaluation_completed_at, row.previous_evaluation_state) }} · neu: {{ assessmentCompletionText(row.event_times?.evaluation_completed_at, row.evaluation_state) }}</p>
                        <p v-if="row.change !== 'unchanged'" class="text-caption">Frühere Fassungen und ihre Zeitnachweise bleiben gespeichert. Unbekannte Zeiten werden nicht aus früheren Fassungen ergänzt.</p>
                        <p>Punkte bisher: {{ row.previous.points ?? row.previous.grades ?? '—' }} · Quelle: {{ minorPoints(row.total_minor) }}</p>
                        <p><strong>{{ row.will_replace ? 'Punkte und Bewertungskommentar werden ersetzt.' : 'Bestehende Bewertung bleibt erhalten.' }}</strong></p>
                        <details>
                            <summary>Kommentare, Kriterien und PDFs</summary>
                            <p class="json-comment">Bisher: {{ row.previous.comments ?? '—' }}</p>
                            <p class="json-comment">Quelle: {{ row.comment }}</p>
                            <p v-for="criterion in row.criteria" :key="criterion.criterion">{{ criterion.criterion }}: {{ minorPoints(criterion.earned_minor) }} / {{ minorPoints(criterion.maximum_minor) }} · {{ stateLabel(criterion.checkability) }} · {{ criterion.reason }}</p>
                            <p v-for="(adjustment, index) in row.adjustments" :key="index">{{ adjustment.label }}: {{ minorPoints(adjustment.amount_minor) }} · {{ adjustment.reason }}</p>
                            <p>PDF bisher: {{ row.previous_pdf?.name ?? '—' }}</p>
                            <p>PDF Quelle: {{ row.pdf?.filename ?? '—' }} · {{ row.will_replace && row.pdf ? 'wird übernommen' : 'bestehendes PDF bleibt erhalten' }}</p>
                        </details>
                    </div>
                </div>
            </v-card-text>
            <v-card-actions class="work-import-actions">
                <v-spacer />
                <v-btn v-if="json_preview" color="primary" :disabled="import_busy || !json_preview.can_import" @click="applyJson">Angezeigte Änderungen übernehmen</v-btn>
                <v-btn :disabled="import_busy" @click="import_open = false">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import { mapState } from 'pinia'
import { markRaw } from 'vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { readWorkImportDirectory, saveWorkImportDirectory, supportsWorkImportDirectory, workImportDirectories, workImportDirectoryKey } from '@/helpers/workImportDirectory'
import { assessmentTimeText, assessmentCompletionText } from '@/helpers/workAssessmentTimes'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { importFolder, importJson } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { importMethod as importDropbox } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'
import { loadWorkDropbox, workDropboxKey } from '@/helpers/workDropbox'
import WorkDropboxSource from './WorkDropboxSource.vue'

export default {
    components: { WorkDropboxSource },
    emits: ['imported'],
    data() {
        return { import_open: false, import_work: null, import_busy: false, import_summary: null, import_error: '', import_selection: '', import_files: [], import_mode: 'markdown', import_source: 'upload', json_files: [], json_preview: null, json_uploads: null, directory_access_supported: supportsWorkImportDirectory(), pending_directory: null }
    },
    computed: {
        ...mapState(useCourseStore, ['selected_course']),
    },
    watch: {
        'selected_course.id'() {
            this.import_open = false
            this.import_summary = null
            this.import_selection = ''
            this.json_preview = null
            this.json_uploads = null
        },
    },
    methods: {
        assessmentTimeText,
        assessmentCompletionText,
        isSelectedCourseWork(work) {
            return Boolean(work?.id && this.selected_course?.id
                && String(work.teaching_course_id) === String(this.selected_course.id))
        },
        openImport(work, quick = false) {
            if (!this.isSelectedCourseWork(work) || this.import_busy) return
            this.import_work = work
            this.import_error = ''
            this.import_summary = null
            this.import_selection = ''
            this.import_files = []
            this.import_mode = 'markdown'
            this.json_files = []
            this.json_preview = null
            this.json_uploads = null
            this.pending_directory = null
            this.import_source = 'upload'
            this.import_open = true
            if (quick) return this.previewDropbox(false)
        },
        async previewDropbox(apply = false) {
            const work = this.import_work
            if (this.import_busy || !this.isSelectedCourseWork(work)) return
            const hash = this.json_preview?.hash
            if (apply && !this.json_preview?.can_import) return
            this.import_source = 'dropbox'
            this.import_busy = true
            this.import_error = ''
            this.import_summary = null
            this.json_preview = null
            this.json_uploads = null
            this.pending_directory = null
            try {
                const state = await loadWorkDropbox(workDropboxKey(useAdminStore().config?.user?.id, work), work.id, true)
                if (!this.import_open || this.import_work !== work || !this.isSelectedCourseWork(work)) return
                if (!state?.can_quick_import) throw new Error(state?.message || 'Für diese Arbeit ist noch kein Dropbox-Ordner zugeordnet.')
                this.import_selection = 'Dropbox · ' + state.folder.name
                const response = await axios.post(importDropbox.url(work.id), apply ? { apply: true, hash } : {})
                if (!this.import_open || this.import_work !== work || !this.isSelectedCourseWork(work)) return
                if (apply) {
                    this.import_summary = { messages: ['Dropbox-Paket übernommen.', ...(response.data.messages || [])] }
                    this.$emit('imported', response.data.data)
                } else {
                    this.json_preview = response.data.preview
                }
            } catch (error) {
                if (this.import_open && this.import_work === work && this.isSelectedCourseWork(work)) {
                    this.import_error = Object.values(error.response?.data?.errors || {}).flat().join(' ') || error.response?.data?.message || error.message || 'Dropbox-Import fehlgeschlagen. Bitte den normalen Ordnerupload verwenden.'
                }
            } finally {
                this.import_busy = false
            }
        },
        directoryKey(work = this.import_work) {
            return workImportDirectoryKey(useAdminStore().config?.user?.id, work)
        },
        async chooseDirectory() {
            if (this.import_busy || !this.isSelectedCourseWork(this.import_work)) return
            const work = this.import_work
            try {
                const handle = await window.showDirectoryPicker({ mode: 'read', id: 'schooltool-work-import' })
                if (this.import_work !== work || !this.import_open || !this.isSelectedCourseWork(work)) return
                await this.previewDirectory(handle, work)
            } catch (error) {
                if (error.name !== 'AbortError' && this.import_work === work) {
                    this.import_error = 'Ordnerzugriff fehlgeschlagen. Bitte den Ordner erneut auswählen.'
                }
            }
        },
        async reuseDirectory() {
            const work = this.import_work
            const handle = workImportDirectories.get(this.directoryKey(work))
            if (!handle) {
                this.import_error = 'Für diese Arbeit ist noch kein Ordner gespeichert. Bitte den Ordner auswählen.'
                return
            }
            this.import_busy = true
            try {
                const permission = await handle.queryPermission({ mode: 'read' })
                if (permission !== 'granted' && await handle.requestPermission({ mode: 'read' }) !== 'granted') {
                    throw new Error('Ordnerzugriff nicht freigegeben')
                }
                if (this.import_work !== work || !this.import_open || !this.isSelectedCourseWork(work)) return
                await this.previewDirectory(handle, work)
            } catch {
                if (this.import_work === work) this.import_error = 'Der gespeicherte Ordner ist nicht verfügbar oder nicht freigegeben. Bitte den Ordner erneut auswählen.'
            } finally { this.import_busy = false }
        },
        async previewDirectory(handle, work) {
            this.import_busy = true
            try {
                const files = await readWorkImportDirectory(handle)
                if (this.import_work !== work || !this.import_open || !this.isSelectedCourseWork(work)) return
                this.pending_directory = markRaw(handle)
                this.import_busy = false
                if (!files.length) throw new Error('Der gespeicherte Ordner enthält keine Importdateien. Bitte den Ordner erneut auswählen.')
                await this.selectJsonFolder({ target: { files, value: '' } })
            } catch (error) {
                if (this.import_work === work && this.import_open) {
                    this.import_error = ['NotFoundError', 'NotAllowedError'].includes(error.name)
                        ? 'Der gespeicherte Ordner ist nicht verfügbar oder nicht freigegeben. Bitte den Ordner erneut auswählen.'
                        : (error.message || 'Der Ordner konnte nicht gelesen werden. Bitte erneut auswählen.')
                }
            } finally { this.import_busy = false }
        },
        changeMode(mode) {
            this.import_mode = mode
            this.json_preview = null
            this.json_uploads = null
            this.json_files = []
            this.import_files = []
            this.import_summary = null
            this.import_error = ''
        },
        minorPoints(value) {
            if (value === null) return 'offen'
            const absolute = Math.abs(value)
            return `${value < 0 ? '-' : ''}${Math.trunc(absolute / 100)},${String(absolute % 100).padStart(2, '0')}`
        },
        changeLabel(value) {
            return { new: 'Neue Quelle', updated: 'Geänderte Quelle', unchanged: 'Identische Wiederholung' }[value]
        },
        stateLabel(value) {
            return { received: 'Eingegangen', unresolved: 'Ungeklärt', not_received: 'Bestätigte Nichtabgabe', complete: 'Abgeschlossen', open: 'Offen', partial: 'Teilweise bewertet', checkable: 'Prüfbar', uncheckable: 'Nicht prüfbar' }[value] || value
        },
        async selectJsonFolder(event) {
            const work = this.import_work
            const selected = Array.from(event.target.files || [])
            if (!selected.length || this.import_busy || !this.isSelectedCourseWork(work)) return
            this.import_source = 'upload'
            this.json_preview = null
            this.json_uploads = null
            this.import_summary = null
            this.import_error = ''
            this.import_selection = ''
            this.import_busy = true
            try {
                const relativePath = file => file.webkitRelativePath || file._relativePath || ''
                const folder = relativePath(selected[0]).split('/')[0]
                if (!folder || selected.some(file => relativePath(file).split('/')[0] !== folder)) {
                    throw new Error('Genau einen Leistungsfeststellungs- oder Übungsordner auswählen.')
                }
                this.import_selection = folder
                const packages = selected.filter(file => /^[^/]+\/(?:Beurteilungen\/)?Schooltool-Bewertungen\.json$/.test(relativePath(file)))
                if (packages.length !== 1) {
                    throw new Error('Der ausgewählte Ordner muss genau eine Schooltool-Bewertungen.json direkt oder unter Beurteilungen enthalten. Den einzelnen Leistungsfeststellungs- oder Übungsordner auswählen; Markdown-Dateien werden nicht als Bewertungsquelle importiert.')
                }
                const [packageFile] = packages
                if (packageFile.size > 262144) {
                    throw new Error('Schooltool-Bewertungen.json überschreitet 256 KiB.')
                }
                let packageData
                try {
                    packageData = JSON.parse(new TextDecoder('utf-8', { fatal: true }).decode(await packageFile.arrayBuffer()))
                } catch {
                    throw new Error('Schooltool-Bewertungen.json kann nicht als UTF-8-JSON gelesen werden.')
                }
                if (!this.import_open || this.import_work !== work || !this.isSelectedCourseWork(work)) return
                if (!packageData || !Array.isArray(packageData.records)) {
                    throw new Error('Schooltool-Bewertungen.json enthält keine gültige Datensatzliste.')
                }
                const packageDirectory = relativePath(packageFile).slice(0, -packageFile.name.length)
                const references = [packageData.overview_pdf, ...packageData.records.map(record => record?.pdf)].filter(Boolean)
                const pdfs = references.map(reference => {
                    const matches = selected.filter(file => relativePath(file) === packageDirectory + reference.filename && /\.pdf$/i.test(file.name))
                    if (matches.length !== 1) {
                        throw new Error('Referenziertes PDF fehlt oder ist neben der JSON-Datei mehrfach vorhanden: ' + reference.filename)
                    }
                    return matches[0]
                })
                const protocols = selected.filter(file => /^[^/]+\/Versand\/(?:Aufgaben|Ergebnisse)\/Versand_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\/Versandprotokoll\.txt$/i.test(relativePath(file)))
                if (protocols.length > 30 || protocols.some(file => file.size > 1048576)
                    || [packageFile, ...pdfs, ...protocols].reduce((sum, file) => sum + file.size, 0) > 6 * 1024 * 1024) {
                    throw new Error('Maximal 30 Versandprotokolle und insgesamt 6 MiB pro Ordnerimport auswählen.')
                }
                const documents = await Promise.all(protocols.map(async file => ({ path: relativePath(file), text: new TextDecoder('utf-8', { fatal: true }).decode(await file.arrayBuffer()) })))
                if (!this.import_open || this.import_work !== work || !this.isSelectedCourseWork(work)) return
                this.import_selection = folder + ' · ' + (1 + pdfs.length + protocols.length) + ' Importdateien'
                this.import_busy = false
                await this.selectJson({ target: { files: [packageFile, ...pdfs] } }, { folder, documents })
            } catch (error) {
                if (this.import_open && this.import_work === work && this.isSelectedCourseWork(work)) {
                    this.import_error = error.message || 'JSON-Paket im Ordner konnte nicht gelesen werden.'
                }
            } finally {
                this.import_busy = false
                event.target.value = ''
            }
        },
        async selectJson(event, dispatch = {}) {
            const selected = Array.from(event.target.files || [])
            if (!selected.length || this.import_busy || !this.isSelectedCourseWork(this.import_work)) return
            this.json_preview = null
            this.json_uploads = null
            this.import_summary = null
            this.import_error = ''
            const packages = selected.filter(file => /\.json$/i.test(file.name))
            const pdfs = selected.filter(file => /\.pdf$/i.test(file.name))
            if (packages.length !== 1 || packages.length + pdfs.length !== selected.length || pdfs.length > 20 || packages[0].size > 262144 || selected.reduce((sum, file) => sum + file.size, 0) > 6 * 1024 * 1024) {
                this.import_error = 'Genau eine JSON-Datei (maximal 256 KiB) und höchstens 20 referenzierte PDFs auswählen; insgesamt höchstens 6 MiB.'
                return
            }
            this.json_uploads = { package: packages[0], pdfs, ...dispatch }
            await this.sendJson(false)
        },
        async applyJson() {
            if (this.json_preview?.can_import) await this.sendJson(true)
        },
        async sendJson(apply) {
            if (this.import_source === 'dropbox') return this.previewDropbox(apply)
            const work = this.import_work
            if (this.import_busy || !this.json_uploads || !this.isSelectedCourseWork(work)) return
            this.import_busy = true
            this.import_error = ''
            try {
                const payload = new FormData()
                payload.append('package', this.json_uploads.package, this.json_uploads.package.name)
                for (const pdf of this.json_uploads.pdfs) payload.append('pdfs[]', pdf, pdf.name)
                if (this.json_uploads.documents?.length) {
                    payload.append('folder', this.json_uploads.folder)
                    payload.append('documents', JSON.stringify(this.json_uploads.documents))
                }
                if (apply) {
                    payload.append('apply', '1')
                    payload.append('hash', this.json_preview.hash)
                }
                const response = await axios.post(importJson.url(work.id), payload)
                if (!this.import_open || this.import_work !== work || !this.isSelectedCourseWork(work)) return
                if (apply) {
                    if (this.pending_directory) {
                        try { await saveWorkImportDirectory(this.directoryKey(work), this.pending_directory) }
                        catch { this.import_error = 'Import erfolgreich; der Ordnerzugriff konnte nicht für Quick-Import gespeichert werden.' }
                    }
                    if (!this.import_open || this.import_work !== work || !this.isSelectedCourseWork(work)) return
                    this.json_preview = null
                    this.json_uploads = null
                    this.import_summary = { messages: ['JSON-Paket übernommen. Offene und teilweise bewertete Fälle erhalten ihre bisherige Bewertung.', ...(response.data.messages || [])] }
                    this.$emit('imported', response.data.data)
                } else {
                    this.json_preview = response.data.preview
                }
            } catch (error) {
                if (!this.import_open || this.import_work !== work || !this.isSelectedCourseWork(work)) return
                this.json_preview = null
                this.import_error = Object.values(error.response?.data?.errors || {}).flat().join(' ') || error.response?.data?.message || error.message || 'JSON-Import fehlgeschlagen.'
            } finally {
                this.import_busy = false
            }
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
.json-preview { overflow-wrap: anywhere; }
.json-comment { white-space: pre-wrap; }
.work-import-actions { flex-wrap: wrap; }
.work-import-actions :deep(.v-btn__content) { white-space: normal; }
.work-import-folder-button { max-width: 100%; height: auto; min-height: 40px; padding-block: 8px; }
.work-import-folder-button :deep(.v-btn__content) { white-space: normal; }
@media (max-width: 600px) {
    .work-import-actions .v-btn { width: 100%; margin-inline: 0; height: auto; min-height: 40px; padding-block: 8px; }
}
</style>
