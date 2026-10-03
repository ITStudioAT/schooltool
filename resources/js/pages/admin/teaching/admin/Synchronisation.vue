<template>
    <v-col cols="12">
        <ItsGridBox variant="overview" color="primary" title="Synchronisation" icon="mdi-sync">
            <p class="mb-3">Cloud LIVE → lokale Windows-Installation · {{ preview?.school || 'ausgewählte Schule' }} · alle Schuljahre</p>
            <v-alert type="info" variant="tonal" class="mb-3">
                Zuerst wird der vollständige Unterrichtsumfang geprüft: Kurse, Schülerzuordnungen, Stunden, Bewertungen,
                Arbeiten, Gruppen, Lehrpläne, Dateien, Einstellungen, Klassenleitungen, Benachrichtigungen und Importhistorie.
                Die Cloud wird ausschließlich gelesen. Persönliche Backups und Wiederherstellungshistorien bleiben eigenständig.
            </v-alert>
            <v-alert v-if="!available && !loadingStatus" type="warning" variant="tonal" class="mb-3">
                Diese Funktion ist nur für Superadmins in der lokalen Windows-Installation mit lokaler Datenbank und lokalem Dateispeicher verfügbar.
            </v-alert>
            <v-alert v-if="errorMessage" type="error" class="mb-3">{{ errorMessage }}</v-alert>
            <v-alert v-if="success" type="success" class="mb-3">
                {{ success.message }}
                <a v-if="success.backup" :href="backupRoute.url(success.backup)" class="d-block mt-2">Verschlüsseltes Sicherheitsbackup herunterladen</a>
                <p class="mt-2">Das Backup benötigt den Schlüssel dieser Installation und bleibt auch lokal im privaten Speicher erhalten.</p>
                <v-btn class="mt-2" @click="reload">Unterricht neu laden</v-btn>
            </v-alert>
            <v-btn color="primary" prepend-icon="mdi-cloud-search-outline" :loading="previewing"
                :disabled="!available || busy" @click="loadPreview">1. Umfang und Konflikte prüfen</v-btn>

            <template v-if="preview">
                <p class="my-3">Geprüfter Stand: {{ formatDate(preview.captured_at) }} · Vorschau gültig für {{ preview.expires_in_minutes }} Minuten.</p>
                <p class="mb-2">Schuljahre: {{ preview.schoolyears.map(year => `${year.name} (${year.from} – ${year.until})`).join(', ') }}</p>
                <p class="mb-3">
                    {{ preview.files }} Dateien / {{ formatBytes(preview.file_bytes) }} · {{ preview.local_files }} lokale Dateien gesichert.
                    {{ preview.new_schoolyears }} neue Schuljahre · {{ preview.new_accounts }} neue deaktivierte Konten · {{ preview.new_students }} neue Schülerdatensätze.
                    {{ preview.new_teachers }} neue deaktivierte Lehrerlisteneinträge · {{ preview.updated_teachers }} aktualisierte Lehrerstammdaten.
                    {{ preview.shared.users }} Benutzerzuordnungen · {{ preview.shared.import116 }} Import-116-Datensätze · {{ preview.shared.settings }} Schuleinstellungen / {{ formatBytes(preview.shared.bytes) }}.
                </p>
                <v-table density="compact" class="mb-3">
                    <thead><tr><th>Unterrichtsbereich</th><th>LIVE</th><th>Lokal ersetzt</th><th>Datenmenge</th></tr></thead>
                    <tbody><tr v-for="row in preview.summary" :key="row.table">
                        <td>{{ tableLabel(row.table) }}</td><td>{{ row.live }}</td><td>{{ row.replaced }}</td><td>{{ formatBytes(row.bytes) }}</td>
                    </tr></tbody>
                </v-table>
                <v-alert v-if="preview.conflicts.length" type="error" variant="tonal" class="mb-3">
                    Übernahme gesperrt. Konflikte zuerst beheben und erneut prüfen.
                    <ul class="ml-5"><li v-for="conflict in preview.conflicts" :key="conflict">{{ conflictLabel(conflict) }}</li></ul>
                </v-alert>
                <v-alert v-if="preview.file_warnings?.length" type="warning" variant="tonal" class="mb-3">
                    Ältere Importhistorien enthalten Dateinamen, deren ursprüngliche Quelldatei bereits in LIVE nicht verfügbar ist.
                    Diese Historieneinträge bleiben erhalten; für sie kann keine Datei kopiert werden.
                    <ul class="ml-5"><li v-for="warning in preview.file_warnings" :key="warning">{{ warning }}</li></ul>
                </v-alert>
                <v-alert v-if="preview.updated_schoolyears?.length" type="warning" variant="tonal" class="mb-3">
                    Die folgenden gemeinsamen Schuljahreinstellungen werden aktualisiert; sie gelten auch in anderen Modulen.
                    <ul class="ml-5"><li v-for="year in preview.updated_schoolyears" :key="year.id">
                        Schuljahr {{ year.values.name }}:
                        <span v-for="change in year.changes" :key="change.field" class="d-block">
                            {{ fieldLabel(change.field) }}: {{ change.before ?? 'leer' }} → {{ change.after ?? 'leer' }}
                        </span>
                    </li></ul>
                </v-alert>
                <v-alert v-if="preview.changed_teachers?.length" type="warning" variant="tonal" class="mb-3">
                    Die folgenden gemeinsamen Lehrerstammdaten werden aktualisiert.
                    <ul class="ml-5"><li v-for="teacher in preview.changed_teachers" :key="teacher.id">
                        {{ teacher.email }}:
                        <span v-for="change in teacher.changes" :key="change.field" class="d-block">
                            {{ fieldLabel(change.field) }}: {{ change.before ?? 'leer' }} → {{ change.after ?? 'leer' }}
                        </span>
                    </li></ul>
                </v-alert>
                <v-alert type="warning" variant="tonal" class="mb-3">
                    Der Start ersetzt alle oben gezählten lokalen Unterrichtsdatensätze dieser Schule in allen Schuljahren.
                    Lokale Unterrichtseinträge ohne Gegenstück im geprüften LIVE-Stand entfallen. Alte Dateien bleiben erhalten;
                    Cloud-Dateien werden privat lokal kopiert. Vorher wird der lokale Unterrichtsstand verschlüsselt gesichert.
                    Bestehende Passwörter, Rollen, persönliche Recoverydaten und Daten anderer Schulen bleiben erhalten.
                    Bestehende lokale Schüler-Kontoverknüpfungen bleiben erhalten, wenn LIVE dort keine Zuordnung enthält.
                    Neue Konten erhalten ein zufälliges lokales Kennwort, bleiben deaktiviert und erhalten keine kopierten Berechtigungen.
                </v-alert>
                <v-alert v-if="preview.changed_contacts.length" type="warning" variant="tonal" class="mb-3">
                    {{ preview.changed_contacts.length }} gemeinsame Schülerdatensätze werden aktualisiert. Diese Kontaktdaten
                    können auch Restaurant, Stundenplan und Elternkontakte verwenden. Die Kontozuordnung darf dabei nicht wechseln.
                    <ul class="ml-5"><li v-for="student in preview.changed_contacts" :key="student.id">
                        Schüler {{ student.student_code }} / lokal #{{ student.id }}: {{ student.fields.map(fieldLabel).join(', ') }}
                        <span v-for="change in student.changes || []" :key="change.field" class="d-block">
                            {{ fieldLabel(change.field) }}: {{ change.before ?? 'leer' }} → {{ change.after ?? 'leer' }}
                        </span>
                    </li></ul>
                </v-alert>
                <v-checkbox v-model="replaceConfirmed" :disabled="busy || !preview.token"
                    label="Ich bestätige den vollständigen Ersatz der angezeigten lokalen Unterrichtsdaten aller Schuljahre einschließlich lokaler Einträge, die in LIVE fehlen." />
                <v-checkbox v-model="contactsConfirmed" :disabled="busy || !preview.token"
                    label="Ich bestätige die angezeigten Änderungen an gemeinsamen Schülerdaten, Lehrerstammdaten und Schuljahreinstellungen sowie die neuen deaktivierten Konten." />
                <v-btn color="warning" prepend-icon="mdi-download" :loading="applying"
                    :disabled="!preview.token || !replaceConfirmed || !contactsConfirmed || busy" @click="applyPreview">
                    2. Sicherheitsbackup erstellen und lokal übernehmen
                </v-btn>
            </template>
        </ItsGridBox>
    </v-col>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { status as statusRoute, preview as previewRoute, apply as applyRoute, backup as backupRoute } from '@/routes/teaching/synchronisation'

const adminStore = useAdminStore()
const available = ref(false)
const loadingStatus = ref(true)
const previewing = ref(false)
const applying = ref(false)
const busy = computed(() => previewing.value || applying.value)
const preview = ref(null)
const replaceConfirmed = ref(false)
const contactsConfirmed = ref(false)
const errorMessage = ref('')
const success = ref(null)

function tableLabel(table) {
    const labels = {
        teaching_curricula: 'Lehrpläne', teaching_curriculum_documents: 'Lehrplandokumente',
        teaching_imported_curricula: 'Importierte Lehrpläne', teaching_schemas: 'Leistungsschemas',
        teaching_entry_areas: 'Leistungsbereiche', teaching_entry_grading_parts: 'Beurteilungsteile',
        teaching_entry_definitions: 'Eintragsarten', teaching_holidays: 'Ferien und freie Tage',
        teaching_school_hours: 'Schulstunden', teaching_class_heads: 'Klassenleitungen',
        teaching_class_head_emails: 'E-Mail-Adressen der Klassenleitungen', teaching_courses: 'Fächer und Kurse',
        teaching_course_students: 'Kursteilnahmen und Zeugnisnoten', teaching_course_dates: 'Unterrichtsstunden und Anwesenheit',
        teaching_course_date_materials: 'Unterrichtsmaterialien', teaching_course_date_material_attachments: 'Materialanhänge',
        teaching_course_works: 'Arbeiten und Aufgaben', teaching_course_work_group_students: 'Arbeitsgruppen und Einzelbewertungen',
        teaching_course_student_entries: 'Leistungseinträge', teaching_course_student_entry_notifications: 'Eintragsbenachrichtigungen',
        teaching_course_behaviour_entries: 'Verhaltens- und Erinnerungseinträge',
        teaching_course_student_category_evaluations: 'Kategoriebeurteilungen',
        import116_runs: 'Schülerimporthistorie', import116_run_changes: 'Änderungen aus Schülerimporten',
        user_groups: 'Kurskontaktgruppen', user_group_members: 'Mitglieder der Kurskontaktgruppen',
    }
    return labels[table] || 'Unterrichtsdaten'
}

function fieldLabel(field) {
    const labels = {
        name: 'Name', concerns: 'Bezeichnung', sem_2_start: 'Beginn des 2. Semesters', is_active: 'Aktivstatus',
        from: 'Beginn', until: 'Ende',
        school_id: 'Schule', schoolyear_id: 'Schuljahr', student_code: 'Schülerkennzahl', user_id: 'Schülerkonto',
        import_user_id: 'Importverantwortlicher', first_name: 'Vorname', last_name: 'Nachname', email: 'E-Mail',
        short: 'Kürzel',
        class: 'Klasse', school_level: 'Schulstufe', attendance_year: 'Besuchsjahr', religion: 'Religion',
        sex: 'Geschlecht', birth_date: 'Geburtsdatum', phone_1: 'Telefon 1', phone_2: 'Telefon 2',
        mother_name: 'Name der Mutter', mother_email: 'E-Mail der Mutter', mother_phone_1: 'Telefon der Mutter 1',
        mother_phone_2: 'Telefon der Mutter 2', father_name: 'Name des Vaters', father_email: 'E-Mail des Vaters',
        father_phone_1: 'Telefon des Vaters 1', father_phone_2: 'Telefon des Vaters 2',
    }
    return labels[field] || 'Schülerstammdaten'
}

function conflictLabel(conflict) {
    return conflict
        .replace(/\b(teaching_[a-z_]+|import116_runs|import116_run_changes|user_groups|user_group_members)\b/g, tableLabel)
        .replace(/material_card_attachments/g, 'Materialanhänge')
        .replace(/material_cards/g, 'Materialkarten')
}

onMounted(async () => {
    try {
        available.value = (await axios.get(statusRoute.url())).data.available
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Verfügbarkeit konnte nicht geprüft werden.'
    } finally {
        loadingStatus.value = false
    }
})

function formatBytes(bytes) {
    return `${(bytes / 1024 / 1024).toLocaleString('de-AT', { maximumFractionDigits: 2 })} MiB`
}

function formatDate(value) {
    return new Date(value).toLocaleString('de-AT')
}

function reload() {
    window.location.reload()
}

async function loadPreview() {
    if (busy.value) {
        return
    }
    previewing.value = true
    adminStore.action = 'teaching_synchronisation'
    preview.value = null
    success.value = null
    replaceConfirmed.value = false
    contactsConfirmed.value = false
    errorMessage.value = ''
    try {
        preview.value = (await axios.post(previewRoute.url(), {}, { timeout: 660000 })).data.data
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Cloud-Unterrichtsstand konnte nicht geprüft werden.'
    } finally {
        adminStore.action = ''
        previewing.value = false
    }
}

async function applyPreview() {
    if (busy.value || !preview.value?.token || !replaceConfirmed.value || !contactsConfirmed.value) {
        return
    }
    applying.value = true
    adminStore.action = 'teaching_synchronisation'
    errorMessage.value = ''
    try {
        success.value = (await axios.post(applyRoute.url(), {
            token: preview.value.token, replace_confirmed: true, contacts_confirmed: true,
        }, { timeout: 660000 })).data
        preview.value = null
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Übernahme fehlgeschlagen; bitte erneut prüfen.'
        if (error.response?.data?.conflicts) {
            preview.value.conflicts = error.response.data.conflicts
        }
        preview.value.token = null
        replaceConfirmed.value = false
        contactsConfirmed.value = false
    } finally {
        adminStore.action = ''
        applying.value = false
    }
}
</script>
