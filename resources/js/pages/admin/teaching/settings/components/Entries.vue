<template>
    <ItsGridBox variant="overview" color="primary" title="Bereiche" icon="mdi-layers-triple-outline" class="w-100">
        <template #header-actions>
            <v-btn color="primary" variant="tonal" rounded="lg" prepend-icon="mdi-shape-square-plus" @click="openCreateAreaDialog">Neuer Bereich</v-btn>
        </template>

        <div class="text-body-2 text-medium-emphasis mt-2">Gruppieren Sie Einträge passend zu Schulstufe oder Fach.</div>

        <v-progress-linear v-if="isLoading" indeterminate color="primary" class="mt-4" />

        <div v-if="areas.length" class="entry-area-grid mt-4">
            <template v-for="area in areas" :key="area.id">
                <div class="entry-area-card" :class="{ 'entry-area-card-active': activeAreaId === area.id }">
                    <button type="button" class="entry-area-select" :aria-pressed="activeAreaId === area.id" @click="activeAreaId = area.id">
                        <span class="entry-area-icon"><v-icon icon="mdi-layers-triple-outline" size="24" /></span>
                        <span class="entry-area-content">
                            <span class="entry-area-name">{{ area.name }}</span>
                            <span class="entry-area-count">{{ entryCountForArea(area.id) }} {{ entryCountForArea(area.id) === 1 ? 'Eintrag' : 'Einträge' }}</span>
                        </span>
                        <v-icon v-if="activeAreaId === area.id" class="entry-area-check" icon="mdi-check-circle" color="primary" size="21" />
                    </button>
                    <div class="entry-area-actions">
                        <v-btn prepend-icon="mdi-pencil-outline" size="small" variant="text" @click="openEditAreaDialog(area)">Bearbeiten</v-btn>
                        <v-btn
                            prepend-icon="mdi-delete-outline"
                            size="small"
                            variant="text"
                            color="error"
                            :disabled="entryCountForArea(area.id) > 0"
                            :title="entryCountForArea(area.id) ? 'Zuerst alle Einträge entfernen' : 'Bereich löschen'"
                            @click="openDeleteAreaDialog(area)">
                            Löschen
                        </v-btn>
                    </div>
                </div>
            </template>
        </div>

        <div v-else-if="!isLoading" class="entry-empty-area mt-4">
            <div class="entry-empty-icon"><v-icon icon="mdi-shape-square-plus" size="32" /></div>
            <div>
                <div class="text-subtitle-1 font-weight-bold">Ersten Bereich anlegen</div>
                <div class="text-body-2 text-medium-emphasis">Zum Beispiel Unterstufe, Oberstufe oder Wahlpflichtfach.</div>
            </div>
            <div class="entry-empty-actions">
                <v-btn
                    v-if="previousYearImportOffer"
                    color="primary"
                    variant="tonal"
                    rounded="lg"
                    prepend-icon="mdi-calendar-import"
                    @click="openPreviousYearImport">
                    Aus Vorjahr übernehmen
                </v-btn>
                <v-btn color="primary" variant="flat" rounded="lg" @click="openCreateAreaDialog">Bereich erstellen</v-btn>
            </div>
        </div>

        <v-dialog v-model="previousYearImportDialogOpen" persistent max-width="560">
            <v-card rounded="xl">
                <v-card-title class="dialog-header">
                    <div class="dialog-title-group">
                        <span class="dialog-icon"><v-icon icon="mdi-calendar-import" /></span>
                        <div>
                            <div class="dialog-eyebrow">Bereiche</div>
                            <div>Aus dem Vorjahr übernehmen?</div>
                        </div>
                    </div>
                </v-card-title>
                <v-card-text class="dialog-body">
                    <v-alert type="info" variant="tonal">
                        Für das aktuelle Schuljahr sind noch keine Bereiche vorhanden. Möchten Sie
                        <strong>
                            {{ previousYearImportOffer?.area_count }}
                            {{ previousYearImportOffer?.area_count === 1 ? 'Bereich' : 'Bereiche' }}
                        </strong>
                        mit
                        <strong>
                            {{ previousYearImportOffer?.entry_count }}
                            {{ previousYearImportOffer?.entry_count === 1 ? 'Eintrag' : 'Einträgen' }}
                        </strong>
                        aus dem Schuljahr
                        <strong>{{ previousYearImportOffer?.schoolyear?.label }}</strong>
                        übernehmen?
                    </v-alert>
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" :disabled="isImportingPreviousYear" @click="declinePreviousYearImport">Nein</v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        rounded="lg"
                        prepend-icon="mdi-calendar-import"
                        :loading="isImportingPreviousYear"
                        @click="importPreviousYearAreas">
                        Übernehmen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <template v-if="areas.length">
            <div class="entry-section-header mt-7">
                <div>
                    <div class="text-h6 font-weight-bold">Einträge</div>
                    <div class="text-body-2 text-medium-emphasis">Aktiver Bereich: {{ activeAreaName }}</div>
                </div>
                <div class="entry-section-actions">
                    <v-btn
                        color="primary"
                        variant="tonal"
                        rounded="lg"
                        prepend-icon="mdi-content-copy"
                        :disabled="isLoading || !availableSourceAreas.length"
                        @click="openEntryCopyDialog">
                        Übernehmen
                    </v-btn>
                    <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" :disabled="isLoading" @click="openCreateDialog">Eintrag</v-btn>
                </div>
            </div>

            <v-tabs v-model="activeCategory" class="entry-tabs mt-3" color="primary" show-arrows>
                <v-tab v-for="category in categoryOptions" :key="category" :value="category">{{ category }}</v-tab>
            </v-tabs>

            <v-list class="bg-transparent pa-0 mt-2">
                <v-list-item v-for="entry in filteredEntries" :key="entry.id" class="entry-row mb-2">
                    <div class="entry-row-content">
                        <v-chip class="entry-short-chip" color="primary" variant="flat">{{ entry.short_name }}</v-chip>
                        <span class="entry-name" :title="entry.name">{{ entry.name }}</span>
                        <div v-if="entry.category === 'Benotung'" class="entry-properties">
                            <v-chip v-if="entry.has_properties && entry.properties_mode === 'free'" class="entry-property-chip entry-free-input-chip" color="info" variant="tonal">
                                Freie Eingabe
                            </v-chip>
                            <v-chip v-for="property in entry.fixed_properties" :key="property" class="entry-property-chip" variant="tonal">
                                {{ property }}
                            </v-chip>
                        </div>
                        <div class="entry-actions">
                            <v-btn icon="mdi-pencil-outline" variant="tonal" color="primary" size="small" title="Eintrag bearbeiten" @click="openEditDialog(entry)" />
                            <v-btn icon="mdi-delete-outline" variant="tonal" color="error" size="small" title="Eintrag löschen" @click="openDeleteDialog(entry)" />
                        </div>
                    </div>
                </v-list-item>
            </v-list>

            <v-alert v-if="!isLoading && !filteredEntries.length" type="info" variant="tonal" class="mt-3">
                In diesem Bereich gibt es noch keine Einträge der Kategorie {{ activeCategory }}.
            </v-alert>
        </template>

        <v-dialog v-model="entryCopyDialogOpen" persistent max-width="620">
            <v-card rounded="xl" class="entry-copy-dialog">
                <v-card-title class="dialog-header">
                    <div class="dialog-title-group">
                        <span class="dialog-icon"><v-icon icon="mdi-content-copy" /></span>
                        <div>
                            <div class="dialog-eyebrow">Bereiche</div>
                            <div>Einträge übernehmen</div>
                        </div>
                    </div>
                    <v-btn icon="mdi-close" variant="text" :disabled="isCopyingEntries" @click="closeEntryCopyDialog" />
                </v-card-title>
                <v-card-text class="dialog-body">
                    <v-alert type="info" variant="tonal" class="mb-4">
                        Alle Einträge des Quellbereichs werden nach
                        <strong>{{ activeAreaName }}</strong>
                        kopiert. Der Quellbereich bleibt unverändert.
                    </v-alert>
                    <div class="text-subtitle-2 font-weight-bold mb-2">Quellbereich auswählen</div>
                    <div class="entry-copy-area-grid">
                        <button
                            v-for="area in availableSourceAreas"
                            :key="area.id"
                            type="button"
                            class="entry-copy-area-choice"
                            :class="{ 'entry-copy-area-choice-active': selectedSourceAreaId === area.id }"
                            :aria-pressed="selectedSourceAreaId === area.id"
                            @click="selectedSourceAreaId = area.id">
                            <span class="entry-copy-area-icon"><v-icon icon="mdi-layers-outline" /></span>
                            <span>
                                <strong>{{ area.name }}</strong>
                                <small>{{ entryCountForArea(area.id) }} {{ entryCountForArea(area.id) === 1 ? 'Eintrag' : 'Einträge' }}</small>
                            </span>
                            <v-icon v-if="selectedSourceAreaId === area.id" icon="mdi-check-circle" color="primary" />
                        </button>
                    </div>
                    <div v-if="entryCopyErrors.source_area_id" class="form-error mt-3">{{ entryCopyErrors.source_area_id[0] }}</div>
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" :disabled="isCopyingEntries" @click="closeEntryCopyDialog">Abbrechen</v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        rounded="lg"
                        prepend-icon="mdi-content-copy"
                        :loading="isCopyingEntries"
                        :disabled="!selectedSourceAreaId"
                        @click="copyEntriesFromArea">
                        Alle übernehmen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="areaDialogOpen" persistent max-width="520">
            <v-card tag="form" rounded="xl" class="area-dialog" @submit.prevent="saveArea">
                <v-card-title class="dialog-header">
                    <div class="dialog-title-group">
                        <span class="dialog-icon"><v-icon icon="mdi-layers-edit" /></span>
                        <div>
                            <div class="dialog-eyebrow">Organisation</div>
                            <div>{{ areaDialogTitle }}</div>
                        </div>
                    </div>
                    <v-btn icon="mdi-close" variant="text" :disabled="isSavingArea" @click="closeAreaDialog" />
                </v-card-title>
                <v-card-text class="pt-6">
                    <v-text-field
                        v-model="areaForm.name"
                        label="Name des Bereichs"
                        placeholder="z. B. Unterstufe"
                        variant="outlined"
                        color="primary"
                        maxlength="100"
                        autofocus
                        :error-messages="areaFormErrors.name" />
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" @click="closeAreaDialog">Abbrechen</v-btn>
                    <v-btn type="submit" color="primary" variant="flat" rounded="lg" :loading="isSavingArea" :disabled="!areaForm.name.trim()">Speichern</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="areaDeleteDialogOpen" persistent max-width="480">
            <v-card rounded="xl">
                <v-card-title>Bereich löschen</v-card-title>
                <v-card-text>
                    <v-alert type="warning" variant="tonal">
                        Soll
                        <strong>{{ areaPendingDeletion?.name }}</strong>
                        endgültig gelöscht werden?
                    </v-alert>
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" @click="closeAreaDeleteDialog">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" :loading="isDeletingArea" @click="confirmAreaDelete">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="editDialogOpen" persistent max-width="680">
            <v-card tag="form" rounded="xl" class="entry-dialog" @submit.prevent="saveEntry">
                <v-card-title class="dialog-header">
                    <div class="dialog-title-group">
                        <span class="dialog-icon"><v-icon icon="mdi-playlist-edit" /></span>
                        <div>
                            <div class="dialog-eyebrow">Eintragsvorlage</div>
                            <div>{{ entryDialogTitle }}</div>
                        </div>
                    </div>
                    <v-btn icon="mdi-close" variant="text" :disabled="isSaving" @click="closeEditDialog" />
                </v-card-title>
                <v-card-text class="dialog-body">
                    <section class="form-section">
                        <div class="form-heading">
                            <v-icon icon="mdi-text-box-edit-outline" color="primary" />
                            <strong>Bezeichnung</strong>
                        </div>
                        <v-row dense>
                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model="entryForm.short_name"
                                    label="Kürzel"
                                    maxlength="2"
                                    counter="2"
                                    variant="outlined"
                                    :error-messages="formErrors.short_name"
                                    @update:model-value="normalizeShortName(entryForm)" />
                            </v-col>
                            <v-col cols="12" sm="8">
                                <v-text-field v-model="entryForm.name" label="Name" maxlength="255" variant="outlined" :error-messages="formErrors.name" />
                            </v-col>
                        </v-row>
                    </section>

                    <section v-if="entryForm.category === 'Benotung'" class="form-section">
                        <div class="form-heading">
                            <v-icon icon="mdi-format-color-fill" color="primary" />
                            <strong>Markierung in Tabelle</strong>
                        </div>
                        <v-btn-toggle v-model="entryForm.has_table_marking" mandatory selected-class="choice-selected" class="choice-grid">
                            <v-btn :value="false" class="choice-card" variant="text">
                                <v-icon icon="mdi-close-circle-outline" />
                                <span>Nein</span>
                            </v-btn>
                            <v-btn :value="true" class="choice-card" variant="text">
                                <v-icon icon="mdi-check-circle-outline" />
                                <span>Ja</span>
                            </v-btn>
                        </v-btn-toggle>
                        <v-expand-transition>
                            <div v-if="entryForm.has_table_marking" class="mt-4">
                                <div class="text-caption text-medium-emphasis mb-2">Farbe auswählen</div>
                                <v-btn-toggle
                                    v-model="entryForm.table_marking_color"
                                    mandatory
                                    selected-class="table-marking-color-selected"
                                    class="table-marking-colors"
                                    aria-label="Farbe für die Tabellenmarkierung auswählen">
                                    <v-btn
                                        v-for="colorOption in tableMarkingColors"
                                        :key="colorOption.value"
                                        :value="colorOption.value"
                                        :aria-label="colorOption.label"
                                        :title="colorOption.label"
                                        class="table-marking-color"
                                        variant="text">
                                        <span class="table-marking-swatch" :style="{ backgroundColor: colorOption.swatch }" />
                                        <span>{{ colorOption.label }}</span>
                                    </v-btn>
                                </v-btn-toggle>
                                <div v-if="formErrors.table_marking_color" class="form-error mt-2">{{ formErrors.table_marking_color[0] }}</div>
                            </div>
                        </v-expand-transition>
                    </section>

                    <section v-if="entryForm.category === 'Benotung'" class="form-section">
                        <div class="properties-heading">
                            <div class="form-heading mb-0">
                                <v-icon icon="mdi-tune-variant" color="primary" />
                                <strong>Eigenschaften</strong>
                            </div>
                            <v-switch v-model="entryForm.has_properties" color="primary" hide-details inset />
                        </div>
                        <v-expand-transition>
                            <div v-if="entryForm.has_properties" class="mt-4">
                                <v-btn-toggle v-model="entryForm.properties_mode" mandatory selected-class="choice-selected" class="choice-grid">
                                    <v-btn value="fixed" class="choice-card" variant="text">
                                        <v-icon icon="mdi-format-list-checks" />
                                        <span>Feste Auswahl</span>
                                    </v-btn>
                                    <v-btn value="free" class="choice-card" variant="text">
                                        <v-icon icon="mdi-pencil-outline" />
                                        <span>Freie Eingabe</span>
                                    </v-btn>
                                </v-btn-toggle>
                                <v-combobox
                                    v-if="entryForm.properties_mode === 'fixed'"
                                    v-model="entryForm.fixed_properties"
                                    class="entry-properties-combobox mt-4"
                                    label="Eigenschaften"
                                    variant="outlined"
                                    multiple
                                    chips
                                    closable-chips
                                    clearable
                                    :error-messages="formErrors.fixed_properties" />
                            </div>
                        </v-expand-transition>
                    </section>

                    <section v-else class="form-section">
                        <div class="properties-heading">
                            <div class="form-heading mb-0">
                                <v-icon icon="mdi-bell-outline" color="primary" />
                                <strong>Verständigungen</strong>
                            </div>
                            <v-switch v-model="entryForm.has_notifications" color="primary" hide-details inset aria-label="Verständigungen aktivieren" />
                        </div>
                        <v-expand-transition>
                            <div v-if="entryForm.has_notifications" class="notification-options mt-4">
                                <v-checkbox
                                    v-model="entryForm.notification_recipients"
                                    value="class_teacher"
                                    label="Klassenvorstand"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                    multiple />
                                <v-checkbox
                                    v-model="entryForm.notification_recipients"
                                    value="parents"
                                    label="Eltern"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                    multiple />
                                <v-checkbox
                                    v-model="entryForm.notification_recipients"
                                    value="student"
                                    label="Schüler:in"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                    multiple />
                            </div>
                        </v-expand-transition>
                    </section>
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" @click="closeEditDialog">Abbrechen</v-btn>
                    <v-btn type="submit" color="primary" variant="flat" rounded="lg" :loading="isSaving" :disabled="!canSaveEntry">Speichern</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialogOpen" persistent max-width="480">
            <v-card rounded="xl">
                <v-card-title>Eintrag löschen</v-card-title>
                <v-card-text>
                    <v-alert type="warning" variant="tonal">
                        <strong>{{ entryPendingDeletion?.short_name }} – {{ entryPendingDeletion?.name }}</strong>
                        wirklich löschen?
                    </v-alert>
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" @click="closeDeleteDialog">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" :loading="isDeleting" @click="confirmDelete">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </ItsGridBox>
</template>

<script>
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

const tableMarkingColors = [
    { value: 'blue', label: 'Blau', swatch: '#3b82f6' },
    { value: 'green', label: 'Grün', swatch: '#22c55e' },
    { value: 'orange', label: 'Orange', swatch: '#f97316' },
    { value: 'purple', label: 'Violett', swatch: '#8b5cf6' },
    { value: 'red', label: 'Rot', swatch: '#ef4444' },
]

export default {
    components: { ItsGridBox },

    data() {
        return {
            areas: [],
            entries: [],
            courseStore: null,
            activeAreaId: null,
            activeCategory: 'Benotung',
            categoryOptions: ['Benotung', 'Verhalten', 'Weitere'],
            tableMarkingColors,
            editDialogOpen: false,
            deleteDialogOpen: false,
            areaDialogOpen: false,
            areaDeleteDialogOpen: false,
            entryCopyDialogOpen: false,
            previousYearImportDialogOpen: false,
            previousYearImportOffer: null,
            isLoading: false,
            isSaving: false,
            isDeleting: false,
            isSavingArea: false,
            isDeletingArea: false,
            isCopyingEntries: false,
            isImportingPreviousYear: false,
            selectedEntryId: null,
            deleteEntryId: null,
            editingAreaId: null,
            deleteAreaId: null,
            selectedSourceAreaId: null,
            formErrors: {},
            areaFormErrors: {},
            entryCopyErrors: {},
            areaForm: { name: '' },
            entryForm: {
                teaching_entry_area_id: null,
                short_name: '',
                name: '',
                category: 'Benotung',
                has_properties: false,
                properties_mode: 'free',
                fixed_properties: [],
                has_notifications: false,
                notification_recipients: [],
                has_table_marking: false,
                table_marking_color: null,
            },
        }
    },

    computed: {
        filteredEntries() {
            return this.entries.filter((entry) => entry.teaching_entry_area_id === this.activeAreaId && entry.category === this.activeCategory)
        },
        entryDialogTitle() {
            return this.selectedEntryId ? 'Eintrag bearbeiten' : 'Eintrag hinzufügen'
        },
        areaDialogTitle() {
            return this.editingAreaId ? 'Bereich bearbeiten' : 'Neuen Bereich erstellen'
        },
        activeAreaName() {
            return this.areas.find((area) => area.id === this.activeAreaId)?.name || ''
        },
        availableSourceAreas() {
            return this.areas.filter((area) => area.id !== this.activeAreaId && this.entries.some((entry) => entry.teaching_entry_area_id === area.id))
        },
        canSaveEntry() {
            return Boolean(
                String(this.entryForm.short_name).trim() &&
                String(this.entryForm.name).trim() &&
                this.areas.some((area) => area.id === this.entryForm.teaching_entry_area_id) &&
                (this.entryForm.category !== 'Benotung' ||
                    !this.entryForm.has_properties ||
                    this.entryForm.properties_mode !== 'fixed' ||
                    this.entryForm.fixed_properties.length) &&
                (this.entryForm.category !== 'Benotung' ||
                    !this.entryForm.has_table_marking ||
                    tableMarkingColors.some((colorOption) => colorOption.value === this.entryForm.table_marking_color))
            )
        },
        entryPendingDeletion() {
            return this.entries.find((entry) => entry.id === this.deleteEntryId) || null
        },
        areaPendingDeletion() {
            return this.areas.find((area) => area.id === this.deleteAreaId) || null
        },
    },

    beforeMount() {
        this.courseStore = useCourseStore()
        this.loadData()
    },

    methods: {
        async loadData() {
            this.isLoading = true
            try {
                const [areaResponse, entryResponse] = await Promise.all([axios.get('/api/admin/teaching/entry_areas'), axios.get('/api/admin/teaching/entry_definitions')])
                this.areas = areaResponse.data?.data || []
                this.entries = entryResponse.data?.data || []
                this.previousYearImportOffer = areaResponse.data?.meta?.previous_year_import || null
                if (!this.areas.some((area) => area.id === this.activeAreaId)) this.activeAreaId = this.areas[0]?.id || null
            } catch (error) {
                this.notifyError(error)
            } finally {
                this.isLoading = false
            }
        },
        entryCountForArea(areaId) {
            return this.entries.filter((entry) => entry.teaching_entry_area_id === areaId).length
        },
        normalizeShortName(entry) {
            entry.short_name = String(entry.short_name || '')
                .trim()
                .toUpperCase()
                .slice(0, 2)
        },
        createEmptyEntry() {
            return {
                teaching_entry_area_id: this.activeAreaId || this.areas[0]?.id || null,
                short_name: '',
                name: '',
                category: this.activeCategory,
                has_properties: false,
                properties_mode: 'free',
                fixed_properties: [],
                has_notifications: false,
                notification_recipients: [],
                has_table_marking: false,
                table_marking_color: null,
            }
        },
        openCreateDialog() {
            this.selectedEntryId = null
            this.entryForm = this.createEmptyEntry()
            this.formErrors = {}
            this.editDialogOpen = true
        },
        openEditDialog(entry) {
            this.selectedEntryId = entry.id
            this.entryForm = {
                ...entry,
                fixed_properties: [...entry.fixed_properties],
                notification_recipients: [...(entry.notification_recipients || [])],
                has_table_marking: Boolean(entry.has_table_marking),
                table_marking_color: entry.table_marking_color || null,
            }
            this.formErrors = {}
            this.editDialogOpen = true
        },
        closeEditDialog() {
            this.editDialogOpen = false
            this.selectedEntryId = null
            this.formErrors = {}
        },
        async saveEntry() {
            if (!this.canSaveEntry) return
            this.normalizeShortName(this.entryForm)
            const hasProperties = this.entryForm.category === 'Benotung' && this.entryForm.has_properties
            const hasNotifications = this.entryForm.category !== 'Benotung' && this.entryForm.has_notifications
            const hasTableMarking = this.entryForm.category === 'Benotung' && this.entryForm.has_table_marking
            const allowedTableMarkingColors = tableMarkingColors.map((colorOption) => colorOption.value)
            const allowedNotificationRecipients = ['class_teacher', 'parents', 'student']
            const payload = {
                ...this.entryForm,
                name: this.entryForm.name.trim(),
                has_properties: hasProperties,
                fixed_properties:
                    hasProperties && this.entryForm.properties_mode === 'fixed'
                        ? [...new Set(this.entryForm.fixed_properties.map((value) => String(value).trim()).filter(Boolean))]
                        : [],
                properties_mode: hasProperties ? this.entryForm.properties_mode : 'free',
                has_notifications: hasNotifications,
                notification_recipients: hasNotifications
                    ? [...new Set(this.entryForm.notification_recipients.filter((recipient) => allowedNotificationRecipients.includes(recipient)))]
                    : [],
                has_table_marking: hasTableMarking,
                table_marking_color:
                    hasTableMarking && allowedTableMarkingColors.includes(this.entryForm.table_marking_color)
                        ? this.entryForm.table_marking_color
                        : null,
            }
            this.isSaving = true
            this.formErrors = {}
            try {
                const response = this.selectedEntryId
                    ? await axios.put(`/api/admin/teaching/entry_definitions/${this.selectedEntryId}`, payload)
                    : await axios.post('/api/admin/teaching/entry_definitions', payload)
                const saved = response.data.data
                const index = this.entries.findIndex((entry) => entry.id === saved.id)
                if (index === -1) this.entries.push(saved)
                else this.entries.splice(index, 1, saved)
                this.courseStore?.syncEntryDefinition(saved)
                this.activeAreaId = saved.teaching_entry_area_id
                this.activeCategory = saved.category
                this.closeEditDialog()
            } catch (error) {
                this.formErrors = error?.response?.data?.errors || {}
                this.notifyError(error)
            } finally {
                this.isSaving = false
            }
        },
        openDeleteDialog(entry) {
            this.deleteEntryId = entry.id
            this.deleteDialogOpen = true
        },
        closeDeleteDialog() {
            this.deleteDialogOpen = false
            this.deleteEntryId = null
        },
        async confirmDelete() {
            if (!this.deleteEntryId) return
            this.isDeleting = true
            try {
                await axios.delete(`/api/admin/teaching/entry_definitions/${this.deleteEntryId}`)
                this.entries = this.entries.filter((entry) => entry.id !== this.deleteEntryId)
                this.closeDeleteDialog()
            } catch (error) {
                this.notifyError(error)
            } finally {
                this.isDeleting = false
            }
        },
        openEntryCopyDialog() {
            this.selectedSourceAreaId = this.availableSourceAreas[0]?.id || null
            this.entryCopyErrors = {}
            this.entryCopyDialogOpen = true
        },
        closeEntryCopyDialog() {
            this.entryCopyDialogOpen = false
            this.selectedSourceAreaId = null
            this.entryCopyErrors = {}
        },
        async copyEntriesFromArea() {
            if (!this.selectedSourceAreaId || !this.activeAreaId) return

            this.isCopyingEntries = true
            this.entryCopyErrors = {}
            try {
                const response = await axios.post(`/api/admin/teaching/entry_areas/${this.activeAreaId}/entry-copies`, {
                    source_area_id: this.selectedSourceAreaId,
                })
                this.entries.push(...response.data.data)
                this.closeEntryCopyDialog()
                this.notifySuccess(`${response.data.copied_count} Einträge wurden übernommen.`)
            } catch (error) {
                this.entryCopyErrors = error?.response?.data?.errors || {}
                this.notifyError(error)
            } finally {
                this.isCopyingEntries = false
            }
        },
        openPreviousYearImport() {
            if (!this.previousYearImportOffer) return
            this.previousYearImportDialogOpen = true
        },
        declinePreviousYearImport() {
            if (this.isImportingPreviousYear) return
            this.previousYearImportDialogOpen = false
        },
        async importPreviousYearAreas() {
            if (!this.previousYearImportOffer) return

            this.isImportingPreviousYear = true
            try {
                const response = await axios.post('/api/admin/teaching/entry-area-imports')
                this.areas = response.data?.data?.areas || []
                this.entries = response.data?.data?.entries || []
                this.activeAreaId = this.areas[0]?.id || null
                this.previousYearImportOffer = null
                this.previousYearImportDialogOpen = false
                const importedAreaCount = Number(response.data.imported_area_count || 0)
                const importedEntryCount = Number(response.data.imported_entry_count || 0)
                this.notifySuccess(
                    `${importedAreaCount} ${importedAreaCount === 1 ? 'Bereich' : 'Bereiche'} und ${importedEntryCount} ${importedEntryCount === 1 ? 'Eintrag' : 'Einträge'} wurden übernommen.`,
                )
            } catch (error) {
                this.notifyError(error)
            } finally {
                this.isImportingPreviousYear = false
            }
        },
        openCreateAreaDialog() {
            this.editingAreaId = null
            this.areaForm = { name: '' }
            this.areaFormErrors = {}
            this.areaDialogOpen = true
        },
        openEditAreaDialog(area) {
            this.editingAreaId = area.id
            this.areaForm = { name: area.name }
            this.areaFormErrors = {}
            this.areaDialogOpen = true
        },
        closeAreaDialog() {
            this.areaDialogOpen = false
            this.editingAreaId = null
            this.areaFormErrors = {}
        },
        async saveArea() {
            if (!this.areaForm.name.trim()) return
            this.isSavingArea = true
            this.areaFormErrors = {}
            try {
                const payload = { name: this.areaForm.name.trim() }
                const response = this.editingAreaId
                    ? await axios.put(`/api/admin/teaching/entry_areas/${this.editingAreaId}`, payload)
                    : await axios.post('/api/admin/teaching/entry_areas', payload)
                const saved = response.data.data
                const index = this.areas.findIndex((area) => area.id === saved.id)
                if (index === -1) this.areas.push(saved)
                else this.areas.splice(index, 1, saved)
                this.areas.sort((a, b) => a.name.localeCompare(b.name))
                this.activeAreaId = saved.id
                this.closeAreaDialog()
            } catch (error) {
                this.areaFormErrors = error?.response?.data?.errors || {}
                this.notifyError(error)
            } finally {
                this.isSavingArea = false
            }
        },
        openDeleteAreaDialog(area) {
            if (this.entryCountForArea(area.id)) return
            this.deleteAreaId = area.id
            this.areaDeleteDialogOpen = true
        },
        closeAreaDeleteDialog() {
            this.areaDeleteDialogOpen = false
            this.deleteAreaId = null
        },
        async confirmAreaDelete() {
            if (!this.deleteAreaId) return
            this.isDeletingArea = true
            try {
                await axios.delete(`/api/admin/teaching/entry_areas/${this.deleteAreaId}`)
                this.areas = this.areas.filter((area) => area.id !== this.deleteAreaId)
                this.activeAreaId = this.areas[0]?.id || null
                this.closeAreaDeleteDialog()
            } catch (error) {
                this.notifyError(error)
            } finally {
                this.isDeletingArea = false
            }
        },
        notifyError(error) {
            useNotificationStore().notify({ status: error?.response?.status, message: error?.response?.data?.message || 'Fehler passiert.', type: 'error', timeout: 3000 })
        },
        notifySuccess(message) {
            useNotificationStore().notify({ message, type: 'success', timeout: 3000 })
        },
    },
}
</script>

<style scoped>
.entry-section-header,
.entry-section-actions,
.entry-row-content,
.dialog-header,
.dialog-title-group,
.dialog-actions,
.properties-heading {
    display: flex;
    align-items: center;
}
.entry-section-header,
.dialog-header,
.properties-heading {
    justify-content: space-between;
    gap: 16px;
}
.entry-section-actions {
    gap: 8px;
}
.entry-area-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    width: 100%;
}
.entry-area-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    width: 100%;
    min-width: 0;
    overflow: hidden;
    border: 1px solid rgba(var(--v-border-color), 0.2);
    border-radius: 18px;
    background: rgb(var(--v-theme-surface));
    color: inherit;
    box-shadow: 0 6px 20px rgba(20, 32, 60, 0.06);
    transition: 0.2s ease;
}
.entry-area-select {
    position: relative;
    display: flex;
    align-items: center;
    gap: 13px;
    width: 100%;
    padding: 18px 16px;
    border: 0;
    background: transparent;
    color: inherit;
    text-align: left;
    cursor: pointer;
}
.entry-area-card:hover {
    transform: translateY(-2px);
    border-color: rgba(var(--v-theme-primary), 0.35);
    box-shadow: 0 10px 28px rgba(var(--v-theme-primary), 0.12);
}
.entry-area-card-active {
    border-color: rgb(var(--v-theme-primary));
    background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.12), rgba(var(--v-theme-primary), 0.03));
}
.entry-area-icon,
.dialog-icon,
.entry-empty-icon {
    display: grid;
    place-items: center;
    flex: 0 0 46px;
    height: 46px;
    border-radius: 14px;
    color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.12);
}
.entry-area-content {
    display: flex;
    flex-direction: column;
    min-width: 85px;
    flex: 1;
}
.entry-area-name {
    font-weight: 750;
    font-size: 0.98rem;
}
.entry-area-count {
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-size: 0.78rem;
}
.entry-area-actions {
    display: flex;
    justify-content: flex-end;
    gap: 2px;
    padding: 7px 9px;
    border-top: 1px solid rgba(var(--v-border-color), 0.12);
    background: rgba(var(--v-theme-on-surface), 0.018);
}
.entry-area-check {
    position: absolute;
    top: 9px;
    right: 9px;
    background: rgb(var(--v-theme-surface));
    border-radius: 50%;
}
.entry-empty-area {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 24px;
    border: 1px dashed rgba(var(--v-theme-primary), 0.35);
    border-radius: 20px;
    background: rgba(var(--v-theme-primary), 0.04);
}
.entry-empty-area > div:nth-child(2) {
    flex: 1;
}
.entry-empty-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}
.entry-tabs {
    border-bottom: 1px solid rgba(var(--v-border-color), 0.15);
}
.entry-row {
    border: 1px solid rgba(var(--v-border-color), 0.16);
    border-radius: 16px !important;
    background: rgb(var(--v-theme-surface));
    box-shadow: 0 4px 16px rgba(20, 32, 60, 0.04);
}
.entry-row-content {
    width: 100%;
    min-width: 0;
    gap: 14px;
    flex-wrap: nowrap;
}
.entry-short-chip {
    min-width: 50px;
    height: 38px !important;
    justify-content: center;
    font-size: 0.95rem;
    font-weight: 800;
}
.entry-name {
    min-width: 150px;
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 650;
}
.entry-properties {
    display: flex;
    gap: 8px;
    flex: 1;
    min-width: 0;
    overflow-x: auto;
}
.entry-property-chip {
    height: 36px !important;
    font-size: 0.9rem;
    font-weight: 650;
}
.entry-free-input-chip {
    letter-spacing: 0.01em;
}
.entry-copy-area-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}
.entry-copy-area-choice {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 74px;
    padding: 13px;
    border: 1px solid rgba(var(--v-border-color), 0.2);
    border-radius: 15px;
    background: rgb(var(--v-theme-surface));
    color: inherit;
    text-align: left;
    cursor: pointer;
    transition: 0.2s ease;
}
.entry-copy-area-choice:hover,
.entry-copy-area-choice-active {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.09);
}
.entry-copy-area-choice > span:nth-child(2) {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}
.entry-copy-area-choice small {
    color: rgba(var(--v-theme-on-surface), 0.62);
}
.entry-copy-area-icon {
    display: grid;
    place-items: center;
    flex: 0 0 40px;
    height: 40px;
    border-radius: 12px;
    color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.11);
}
.entry-properties-combobox :deep(.v-field__input) {
    min-height: 78px;
    gap: 8px;
    padding-top: 20px;
    padding-bottom: 10px;
}
.entry-properties-combobox :deep(.v-chip) {
    height: 42px !important;
    padding-inline: 14px !important;
    font-size: 1rem;
    font-weight: 700;
}
.entry-properties-combobox :deep(.v-chip__close) {
    margin-inline-start: 8px;
    font-size: 20px;
}
.entry-actions {
    display: flex;
    gap: 8px;
    margin-left: auto;
}
.dialog-header {
    padding: 22px 24px;
    border-bottom: 1px solid rgba(var(--v-border-color), 0.12);
    font-weight: 750;
}
.dialog-title-group {
    gap: 13px;
}
.dialog-eyebrow {
    color: rgb(var(--v-theme-primary));
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.dialog-body {
    padding: 24px;
    background: rgba(var(--v-theme-primary), 0.025);
}
.dialog-actions {
    justify-content: flex-end;
    gap: 8px;
    padding: 16px 24px 22px;
}
.form-section {
    padding: 18px;
    margin-bottom: 15px;
    border: 1px solid rgba(var(--v-border-color), 0.15);
    border-radius: 16px;
    background: rgb(var(--v-theme-surface));
}
.form-heading {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 16px;
}
.choice-grid {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
    height: auto !important;
    gap: 10px;
}
.notification-options {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}
.choice-card {
    display: flex !important;
    align-items: center;
    justify-content: flex-start !important;
    gap: 10px;
    min-height: 58px;
    height: auto !important;
    padding: 12px 14px !important;
    border: 1px solid rgba(var(--v-border-color), 0.2) !important;
    border-radius: 14px !important;
    text-transform: none !important;
}
.choice-selected {
    border-color: rgb(var(--v-theme-primary)) !important;
    color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.1) !important;
}
.table-marking-colors {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(88px, 1fr));
    width: 100%;
    height: auto !important;
    gap: 8px;
}
.table-marking-color {
    display: flex !important;
    flex-direction: column;
    gap: 5px;
    min-width: 0 !important;
    min-height: 62px;
    border: 2px solid transparent !important;
    border-radius: 12px !important;
    text-transform: none !important;
}
.table-marking-color-selected {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.08) !important;
}
.table-marking-swatch {
    width: 28px;
    height: 28px;
    border: 2px solid rgba(var(--v-theme-on-surface), 0.14);
    border-radius: 50%;
    box-shadow: 0 2px 7px rgba(20, 32, 60, 0.18);
}
.form-error {
    margin-top: 8px;
    color: rgb(var(--v-theme-error));
    font-size: 0.8rem;
}
@media (max-width: 700px) {
    .entry-section-header,
    .entry-empty-area {
        align-items: stretch;
        flex-direction: column;
    }
    .entry-section-actions {
        flex-wrap: wrap;
    }
    .entry-area-grid {
        grid-template-columns: 1fr;
    }
    .entry-name {
        min-width: 100px;
    }
    .entry-properties {
        display: none;
    }
    .choice-grid,
    .notification-options,
    .entry-copy-area-grid {
        grid-template-columns: 1fr;
    }
}
</style>
