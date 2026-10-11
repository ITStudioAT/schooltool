<template>
    <v-dialog v-model="dialog" max-width="520" persistent>
        <v-card rounded="xl">
            <v-card-title>{{ form.id ? 'Termin bearbeiten' : 'Termin hinzufügen' }}</v-card-title>
            <v-card-text>
                <v-alert v-if="loadError" type="warning" variant="tonal" class="mb-3">{{ loadError }}</v-alert>
                <v-alert v-if="saveError" type="error" variant="tonal" class="mb-3">{{ saveError }}</v-alert>
                <v-select v-if="occurrenceDate" v-model="editScope" :items="[{ value: 'occurrence', title: 'Nur dieser Termin' }, { value: 'series', title: 'Alle Termine' }]" label="Änderung gilt für" :disabled="saving" />
                <template v-if="editScope === 'occurrence'">
                    <p class="text-body-2 mb-3">{{ occurrenceDate }} · Nur die Bezeichnung wird geändert.</p>
                    <v-select v-if="originalAppointment?.school_hours?.length" v-model="occurrenceHour" :items="originalAppointment.school_hours.map((hour) => ({ value: hour, title: `${hour}. Stunde` }))" label="Schulstunde" :error-messages="errors.hour" :disabled="saving" />
                    <v-text-field v-model="occurrenceTitle" label="Bezeichnung für diesen Termin" maxlength="120" :error-messages="errors.title" :disabled="saving" />
                    <v-btn variant="text" :disabled="saving" @click="save(true)">Serientext verwenden</v-btn>
                </template>
                <template v-else>
                <v-select v-model="form.kind" :items="personalAppointmentKinds" label="Terminart" :error-messages="errors.kind" :disabled="saving" />
                <v-text-field v-model="form.title" label="Eigene Bezeichnung (optional)" maxlength="120" :error-messages="errors.title" :disabled="saving" />
                <v-text-field v-model="form.date" type="date" label="Datum" :min="schoolyear?.from" :max="schoolyear?.until" :error-messages="errors.date" :disabled="saving" />
                <div class="d-flex flex-wrap ga-1 mb-3">
                    <v-btn size="x-small" variant="tonal" :disabled="saving || !dateShortcuts.from" :title="dateShortcuts.from || 'Schulbeginn ist im ausgewählten Schuljahr nicht hinterlegt.'" @click="setShortcutDate('date', dateShortcuts.from)">Schulbeginn</v-btn>
                    <v-btn size="x-small" variant="tonal" :disabled="saving || !dateShortcuts.semesterStart" :title="dateShortcuts.semesterStart || 'Beginn des 2. Semesters ist im ausgewählten Schuljahr nicht hinterlegt.'" @click="setShortcutDate('date', dateShortcuts.semesterStart)">Beginn 2. Semester</v-btn>
                </div>
                <v-select v-model="timeMode" :items="[{ value: 'free', title: 'Freie Uhrzeit' }, { value: 'school_hours', title: 'Schulstunden auswählen' }]" label="Zeit festlegen" :disabled="saving" />
                <template v-if="timeMode === 'school_hours'">
                    <v-alert v-if="!schoolHourOptions.length" type="warning" variant="tonal" class="mb-3">Für dieses Schuljahr sind keine gültigen Schulstunden verfügbar. Bitte das Schulstundenraster prüfen oder freie Uhrzeiten verwenden.</v-alert>
                    <v-select v-model="form.school_hours" :items="schoolHourOptions" multiple chips label="Schulstunden" :error-messages="errors.school_hours" :disabled="saving || !schoolHourOptions.length" />
                    <p v-if="hasUnavailableHours" class="text-error text-caption">Eine gewählte Schulstunde fehlt oder hat ungültige Zeiten. Bitte neu auswählen oder freie Uhrzeiten verwenden.</p>
                    <div v-for="segment in selectedSchoolHourSegments" :key="segment.hour" class="text-body-2">{{ segment.hour }}. Std · {{ segment.starts_at }}–{{ segment.ends_at }}</div>
                    <p class="text-caption mt-2">Die Zeiten werden aus dem aktuellen Schulstundenraster übernommen. Nur die ausgewählten Stunden sind belegt; Lücken bleiben frei.</p>
                </template>
                <div v-else class="d-flex ga-3">
                    <v-text-field v-model="form.starts_at" type="time" label="Beginn" :error-messages="errors.starts_at" :disabled="saving" />
                    <v-text-field v-model="form.ends_at" type="time" label="Ende" :error-messages="errors.ends_at" :disabled="saving" />
                </div>
                <v-checkbox v-model="form.weekly" label="Wöchentlich wiederholen" :disabled="saving" hide-details />
                <v-text-field v-if="form.weekly" v-model="form.repeat_until" type="date" label="Wiederholen bis einschließlich" :min="form.date" :max="schoolyear?.until" :error-messages="errors.repeat_until" :disabled="saving" />
                <div v-if="form.weekly" class="d-flex flex-wrap ga-1 mb-3">
                    <v-btn size="x-small" variant="tonal" :disabled="saving || !dateShortcuts.until || dateShortcuts.until < form.date" :title="dateShortcuts.until || 'Schulende ist im ausgewählten Schuljahr nicht hinterlegt.'" @click="setShortcutDate('repeat_until', dateShortcuts.until)">Schulende</v-btn>
                    <v-btn size="x-small" variant="tonal" :disabled="saving || !dateShortcuts.semesterEnd" :title="dateShortcuts.semesterEnd || 'Semesterende benötigt ein gültiges Startdatum und gespeicherte Schuljahres- und Semestergrenzen.'" @click="setShortcutDate('repeat_until', dateShortcuts.semesterEnd)">Semesterende</v-btn>
                </div>
                <p v-if="form.id && (form.weekly || originalWeekly)" class="text-caption">Änderungen und Löschen gelten für die gesamte Terminserie.</p>
                </template>
                <div v-if="confirmDelete" class="mt-3" role="alert">
                    <p>{{ originalWeekly ? 'Gesamte Terminserie löschen?' : 'Diesen Einzeltermin löschen?' }}</p>
                    <v-btn color="error" :loading="saving" @click="remove">Löschen bestätigen</v-btn>
                    <v-btn variant="text" :disabled="saving" @click="confirmDelete = false">Behalten</v-btn>
                </div>
            </v-card-text>
            <v-card-actions>
                <v-btn v-if="form.id && editScope !== 'occurrence'" color="error" variant="text" :disabled="saving" @click="confirmDelete = true">Löschen</v-btn>
                <v-spacer />
                <v-btn variant="text" :disabled="saving" @click="dialog = false">Abbrechen</v-btn>
                <v-btn color="primary" :loading="saving" :disabled="!canSave || !!loadError || loading" @click="save()">Speichern</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import axios from 'axios'
import { index, store, update, destroy, updateOccurrence } from '@/actions/App/Http/Controllers/Admin/Teaching/PersonalAppointmentController'
import { applicationDate } from '@/helpers/date'
import { personalAppointmentDateShortcuts, personalAppointmentKinds } from '@/helpers/teachingPersonalAppointments'

export default {
    props: { schoolyear: { type: Object, default: null }, schoolHours: { type: Array, default: () => [] }, contextKey: { type: String, required: true } },
    emits: ['loaded', 'load-error'],
    data() {
        return { personalAppointmentKinds, loadedAppointments: [], dialog: false, form: {}, timeMode: 'free', originalWeekly: false, saving: false, loading: false, loadError: '', saveError: '', errors: {}, confirmDelete: false, requestId: 0, editScope: 'series', originalAppointment: null, occurrenceDate: null, occurrenceHour: null, occurrenceTitle: '' }
    },
    computed: {
        appointments() { return this.loadedAppointments || [] },
        dateShortcuts() {
            return personalAppointmentDateShortcuts(this.schoolyear, this.form.date)
        },
        canSave() {
            if (this.editScope === 'occurrence') return !!this.occurrenceDate && (!this.originalAppointment?.school_hours?.length || !!this.occurrenceHour)
            const validTime = this.timeMode === 'school_hours'
                ? this.selectedSchoolHourSegments.length > 0 && !this.hasUnavailableHours
                : !!this.form.starts_at && !!this.form.ends_at && this.form.ends_at > this.form.starts_at
            return !!this.form.kind && !!this.form.date && validTime
                && (!this.form.weekly || (!!this.form.repeat_until && this.form.repeat_until >= this.form.date))
        },
        schoolHourOptions() {
            const clock = /^(?:[01]\d|2[0-3]):[0-5]\d$/
            return this.schoolHours.filter((entry) => {
                const start = entry.from?.slice(0, 5)
                const end = entry.until?.slice(0, 5)
                return Number.isInteger(Number(entry.hour)) && Number(entry.hour) >= 1 && Number(entry.hour) <= 20
                    && this.schoolHours.filter((other) => Number(other.hour) === Number(entry.hour)).length === 1
                    && clock.test(start) && clock.test(end) && end > start
            }).map((entry) => ({ value: Number(entry.hour), title: `${entry.hour}. Std · ${entry.from.slice(0, 5)}–${entry.until.slice(0, 5)}`, starts_at: entry.from.slice(0, 5), ends_at: entry.until.slice(0, 5) }))
        },
        selectedSchoolHourSegments() {
            return (this.form.school_hours || []).map((hour) => this.schoolHourOptions.find((entry) => entry.value === Number(hour)))
                .filter(Boolean).map((entry) => ({ hour: entry.value, starts_at: entry.starts_at, ends_at: entry.ends_at }))
                .sort((left, right) => left.starts_at.localeCompare(right.starts_at))
        },
        hasUnavailableHours() {
            const segments = this.selectedSchoolHourSegments
            return segments.length !== (this.form.school_hours || []).length
                || segments.some((segment, index) => index > 0 && segment.starts_at < segments[index - 1].ends_at)
        },
    },
    watch: {
        contextKey: { immediate: true, handler() { this.dialog = false; this.load() } },
        editScope() { this.confirmDelete = false },
        occurrenceHour() { this.restoreOccurrenceTitle() },
    },
    beforeUnmount() { this.requestId++ },
    methods: {
        setShortcutDate(field, date) {
            if (this.saving || !date || (field === 'repeat_until' && (!this.form.weekly || date < this.form.date))) return
            this.form[field] = date
            this.errors = {}
        },
        open(appointment = null) {
            this.occurrenceDate = appointment?.occurrenceDate || null
            this.originalAppointment = appointment ? ((this.appointments || []).find((entry) => entry.id === appointment.id) || appointment) : null
            this.occurrenceHour = appointment?.occurrenceHour ?? (appointment?.school_hours?.length === 1 ? appointment.school_hours[0] : null)
            this.editScope = this.occurrenceDate ? 'occurrence' : 'series'
            appointment = this.originalAppointment
            this.form = appointment ? { ...appointment, school_hours: [...(appointment.school_hours || [])], weekly: !!appointment.repeat_until } : {
                kind: 'supplier_standby', title: '', date: applicationDate(), starts_at: '', ends_at: '', school_hours: [], weekly: false, repeat_until: '',
            }
            this.timeMode = this.form.school_hours.length ? 'school_hours' : 'free'
            this.originalWeekly = !!appointment?.repeat_until
            this.errors = {}
            this.saveError = ''
            this.confirmDelete = false
            this.dialog = true
            this.restoreOccurrenceTitle()
        },
        restoreOccurrenceTitle() {
            const appointment = this.originalAppointment
            const key = `${this.occurrenceDate}:${this.occurrenceHour ?? 'all'}`
            this.occurrenceTitle = Object.hasOwn(appointment?.title_exceptions || {}, key) ? appointment.title_exceptions[key] || '' : appointment?.title || ''
        },
        async load() {
            const requestId = ++this.requestId
            this.loadedAppointments = []
            this.$emit('loaded', [])
            this.loadError = ''
            this.loading = false
            this.$emit('load-error', '')
            if (!this.schoolyear?.id) return
            this.loading = true
            try {
                const response = await axios.get(index.url())
                if (requestId === this.requestId) {
                    this.loadedAppointments = response.data.data
                    this.$emit('loaded', response.data.data)
                }
            } catch (error) {
                if (requestId === this.requestId) {
                    this.loadError = error.response?.status === 503 ? 'Persönliche Termine sind noch nicht freigeschaltet.' : 'Persönliche Termine konnten nicht geladen werden. Bitte die Ansicht neu laden.'
                    this.$emit('load-error', this.loadError)
                }
            } finally {
                if (requestId === this.requestId) this.loading = false
            }
        },
        async save(reset = false) {
            if (!this.canSave || this.saving || this.loadError || this.loading) return
            const context = this.contextKey
            this.saving = true
            this.errors = {}
            this.saveError = ''
            const usesSchoolHours = this.timeMode === 'school_hours'
            const payload = { kind: this.form.kind, title: this.form.title || null, date: this.form.date, school_hours: usesSchoolHours ? this.form.school_hours : [], starts_at: usesSchoolHours ? null : this.form.starts_at, ends_at: usesSchoolHours ? null : this.form.ends_at, weekly: this.form.weekly, repeat_until: this.form.weekly ? this.form.repeat_until : null }
            try {
                if (this.editScope === 'occurrence') await axios.put(updateOccurrence.url(this.form.id), { date: this.occurrenceDate, hour: this.occurrenceHour, title: this.occurrenceTitle || null, reset })
                else if (this.form.id) await axios.put(update.url(this.form.id), payload)
                else await axios.post(store.url(), payload)
                if (context !== this.contextKey) return
                this.dialog = false
                await this.load()
            } catch (error) {
                if (context === this.contextKey) {
                    this.errors = error.response?.data?.errors || {}
                    this.saveError = 'Termin konnte nicht gespeichert werden. Bitte die Angaben prüfen.'
                }
            } finally { this.saving = false }
        },
        async remove() {
            if (this.editScope === 'occurrence' || !this.confirmDelete || !this.form.id || this.saving) return
            const context = this.contextKey
            this.saving = true
            this.saveError = ''
            try {
                await axios.delete(destroy.url(this.form.id))
                if (context !== this.contextKey) return
                this.dialog = false
                await this.load()
            } catch {
                if (context === this.contextKey) this.saveError = 'Termin konnte nicht gelöscht werden.'
            } finally { this.saving = false }
        },
    },
}
</script>
