<template>
    <v-col cols="12" class="teaching-admin-school-hours-col">
        <ItsGridBox variant="overview" color="primary" title="Schulstunden" subtitle="Stundenzeiten pro Schuljahr verwalten" icon="mdi-clock-time-four-outline" class="w-100" :disabled="isSavingSchoolHours">
            <div class="d-flex flex-wrap align-center ga-2 mb-3">
                <span class="text-body-2 text-medium-emphasis">Aktives Schuljahr</span>
                <v-chip size="small" color="primary" variant="tonal">
                    {{ activeSchoolyearLabel }}
                </v-chip>
            </div>
            <div class="d-flex align-center justify-space-between ga-2">
                <div class="text-subtitle-2">Schulstunden erstellen</div>
                <v-btn
                    :icon="show_create_form ? 'mdi-close' : 'mdi-plus'"
                    size="small"
                    color="primary"
                    variant="tonal"
                    :disabled="isSavingSchoolHours"
                    @click="toggleCreateForm" />
            </div>

            <v-expand-transition>
                <div v-if="show_create_form" class="mt-3 school-hours-form-wrap">
                    <v-form @submit.prevent="createSchoolHour">
                        <div class="d-flex align-center justify-space-between mb-2">
                            <div class="text-caption text-medium-emphasis">{{ create_entries.length }} / 10 Einträge</div>
                            <v-btn
                                icon="mdi-plus"
                                size="x-small"
                                color="primary"
                                variant="tonal"
                                :disabled="create_entries.length >= 10 || isSavingSchoolHours"
                                @click="addCreateEntry" />
                        </div>

                        <div v-for="(entry, index) in create_entries" :key="`create-entry-${index}`" class="mb-2">
                            <v-row class="ma-0" dense>
                                <v-col cols="12" md="3">
                                <v-text-field
                                    v-model.number="entry.hour"
                                    type="number"
                                    min="1"
                                    max="20"
                                    label="Stunde"
                                    hide-details="auto" />
                            </v-col>
                                <v-col cols="12" md="4">
                                    <v-text-field
                                        v-model="entry.from"
                                        type="time"
                                        label="Von"
                                        hide-details="auto" />
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-text-field
                                        v-model="entry.until"
                                        type="time"
                                        label="Bis"
                                        hide-details="auto" />
                                </v-col>
                                <v-col cols="12" md="1" class="d-flex align-center justify-end">
                                    <v-btn
                                        icon="mdi-delete"
                                        size="x-small"
                                        color="error"
                                        variant="tonal"
                                        :disabled="create_entries.length <= 1 || isSavingSchoolHours"
                                        @click="removeCreateEntry(index)" />
                                </v-col>
                            </v-row>
                        </div>

                        <div class="d-flex flex-row align-center justify-end mt-3">
                            <v-btn color="success" flat tile type="submit" :loading="school_hours_save_action === 'create'" :disabled="!isCreateFormValid || isSavingSchoolHours">Schulstunde erstellen</v-btn>
                        </div>
                    </v-form>
                </div>
            </v-expand-transition>

            <v-divider class="my-4" />

            <div class="text-subtitle-2 mb-2 text-start school-hours-section-title">Erfasste Schulstunden</div>
            <v-list density="compact" class="school-hours-list">
                <v-list-item v-for="schoolHour in school_hours" :key="schoolHour.id" class="px-0 school-hours-list-item">
                    <div v-if="editing_id === schoolHour.id" class="w-100">
                        <v-row class="ma-0" dense>
                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model.number="edit_data.hour"
                                    type="number"
                                    min="1"
                                    max="20"
                                    label="Stunde"
                                    hide-details="auto" />
                            </v-col>
                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model="edit_data.from"
                                    type="time"
                                    label="Von"
                                    hide-details="auto" />
                            </v-col>
                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model="edit_data.until"
                                    type="time"
                                    label="Bis"
                                    hide-details="auto" />
                            </v-col>
                            <v-col cols="12" md="3" class="d-flex align-center justify-end ga-2">
                                <v-btn color="success" size="small" variant="flat" icon="mdi-content-save-outline" :loading="school_hours_save_action === 'edit'" :disabled="isSavingSchoolHours" @click="saveEditSchoolHour" />
                                <v-btn color="warning" size="small" variant="tonal" icon="mdi-close" :disabled="isSavingSchoolHours" @click="cancelEdit" />
                            </v-col>
                        </v-row>
                    </div>

                    <div v-else class="d-flex align-center justify-space-between ga-2 w-100 school-hour-row">
                        <div class="d-flex align-center ga-2">
                            <v-chip size="small" color="primary" variant="tonal" class="school-hour-chip">Stunde {{ schoolHour.hour }}</v-chip>
                            <v-chip size="small" color="secondary" variant="outlined" class="school-hour-time-chip">{{ formatTime(schoolHour.from) }} - {{ formatTime(schoolHour.until) }}</v-chip>
                        </div>
                        <div class="d-flex align-center ga-1">
                            <v-btn
                                icon="mdi-pencil"
                                size="x-small"
                                color="primary"
                                variant="tonal"
                                :disabled="isSavingSchoolHours"
                                @click="startEdit(schoolHour)" />
                            <v-btn
                                icon="mdi-delete"
                                size="x-small"
                                color="warning"
                                variant="tonal"
                                :disabled="isSavingSchoolHours"
                                @click="promptDeleteSchoolHour(schoolHour.id)" />
                        </div>
                    </div>
                </v-list-item>

                <v-list-item v-if="!school_hours_loaded" class="px-0">
                    <div class="w-100 py-3">
                        <v-progress-linear indeterminate color="primary" rounded />
                        <div class="text-caption text-medium-emphasis mt-2">Schulstunden werden geladen.</div>
                    </div>
                </v-list-item>

                <v-list-item v-else-if="!school_hours?.length" class="px-0">
                    <v-alert type="warning" variant="tonal" rounded="lg" class="w-100">
                        <div class="font-weight-bold">Keine Schulstunden erfasst.</div>
                        <div class="text-body-2 mt-1">
                            {{ previousYearImportDescription }}
                        </div>
                        <v-btn
                            class="mt-3"
                            color="primary"
                            variant="flat"
                            prepend-icon="mdi-calendar-import"
                            :loading="school_hours_save_action === 'import'"
                            :disabled="!previous_year_import || isSavingSchoolHours"
                            @click="importPreviousYearSchoolHours">
                            Aus dem Vorjahr übernehmen
                        </v-btn>
                    </v-alert>
                </v-list-item>
            </v-list>

            <v-dialog v-model="delete_dialog_open" persistent max-width="420">
                <v-card rounded="xl">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                        <v-icon color="error" size="20">mdi-delete-outline</v-icon>
                        Schulstunde löschen
                    </v-card-title>
                    <v-card-text class="px-4">
                        Soll diese Schulstunde wirklich gelöscht werden?
                    </v-card-text>
                    <v-card-actions class="px-4 pb-4">
                        <v-btn variant="tonal" :disabled="isSavingSchoolHours" @click="cancelDeleteSchoolHour">Abbrechen</v-btn>
                        <v-spacer />
                        <v-btn color="error" variant="flat" :loading="school_hours_save_action === 'delete'" :disabled="isSavingSchoolHours" @click="confirmDeleteSchoolHour">Löschen</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolHourStore = useSchoolHourStore()
        await this.loadSchoolHoursIfNeeded()
    },

    data() {
        return {
            adminStore: null,
            schoolHourStore: null,
            show_create_form: false,
            editing_id: null,
            delete_dialog_open: false,
            delete_id: null,
            create_entries: [this.emptyCreateEntry(1)],
            edit_data: {
                hour: null,
                from: '',
                until: '',
            },
            school_hours_save_action: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        ...mapWritableState(useSchoolHourStore, ['school_hours', 'school_hours_loaded', 'previous_year_import']),
        isSavingSchoolHours() {
            return this.school_hours_save_action !== null
        },
        activeSchoolyearLabel() {
            return this.config?.selected_schoolyear?.name || this.config?.selected_schoolyear?.concerns || 'Kein Schuljahr gewählt'
        },
        isCreateFormValid() {
            return this.create_entries.every((entry) => this.isCreateEntryValid(entry))
        },
        previousYearImportDescription() {
            if (!this.previous_year_import) {
                return 'Im vorherigen Schuljahr wurden keine Schulstunden gefunden.'
            }

            const count = Number(this.previous_year_import.count || 0)
            const schoolyear = this.previous_year_import.schoolyear?.label || 'dem vorherigen Schuljahr'

            return `${count} ${count === 1 ? 'Schulstunde' : 'Schulstunden'} aus ${schoolyear} können übernommen werden.`
        },
    },

    methods: {
        async loadSchoolHoursIfNeeded() {
            if (this.school_hours_loaded) {
                return true
            }

            return this.schoolHourStore.index()
        },
        async runSchoolHourMutation(action, callback) {
            if (this.school_hours_save_action) {
                return false
            }

            this.school_hours_save_action = action
            await this.$nextTick()

            try {
                return await callback()
            } finally {
                this.school_hours_save_action = null
            }
        },
        emptyCreateEntry(hour = null) {
            return {
                hour,
                from: '',
                until: '',
            }
        },
        nextSuggestedHour() {
            const usedHours = [
                ...(this.school_hours || []).map((item) => Number(item.hour)),
                ...this.create_entries.map((entry) => Number(entry.hour)),
            ].filter((hour) => Number.isInteger(hour) && hour >= 1 && hour <= 20)

            for (let hour = 1; hour <= 20; hour++) {
                if (!usedHours.includes(hour)) {
                    return hour
                }
            }

            return null
        },
        formatTime(value) {
            if (!value) {
                return ''
            }
            return String(value).slice(0, 5)
        },
        isCreateEntryValid(entry) {
            const hour = Number(entry?.hour)
            const from = String(entry?.from || '')
            const until = String(entry?.until || '')

            if (!Number.isInteger(hour) || hour < 1 || hour > 20) {
                return false
            }

            if (!/^\d{2}:\d{2}$/.test(from) || !/^\d{2}:\d{2}$/.test(until)) {
                return false
            }

            return until > from
        },
        resetCreateForm() {
            this.create_entries = [this.emptyCreateEntry(this.nextSuggestedHour())]
        },
        addCreateEntry() {
            if (this.create_entries.length >= 10) {
                return
            }
            this.create_entries.push(this.emptyCreateEntry(this.nextSuggestedHour()))
        },
        removeCreateEntry(index) {
            if (this.create_entries.length <= 1) {
                return
            }
            this.create_entries.splice(index, 1)
        },
        toggleCreateForm() {
            if (this.isSavingSchoolHours) {
                return
            }
            this.show_create_form = !this.show_create_form
            if (!this.show_create_form) {
                this.resetCreateForm()
            }
        },
        async createSchoolHour() {
            if (!this.isCreateFormValid) {
                return
            }

            await this.runSchoolHourMutation('create', async () => {
                const payload = {
                    entries: this.create_entries.map((entry) => ({
                        hour: Number(entry.hour),
                        from: entry.from || null,
                        until: entry.until || null,
                    })),
                }
                const created = await this.schoolHourStore.store(payload)
                if (!created) {
                    return
                }

                await this.schoolHourStore.index()
                this.show_create_form = false
                this.resetCreateForm()
            })
        },
        async importPreviousYearSchoolHours() {
            if (!this.previous_year_import) {
                return
            }

            await this.runSchoolHourMutation('import', async () => {
                const imported = await this.schoolHourStore.importPreviousYear()
                if (!imported) {
                    return
                }

                this.show_create_form = false
                this.resetCreateForm()
            })
        },
        startEdit(schoolHour) {
            this.editing_id = schoolHour.id
            this.edit_data = {
                hour: schoolHour.hour,
                from: this.formatTime(schoolHour.from),
                until: this.formatTime(schoolHour.until),
            }
        },
        promptDeleteSchoolHour(id) {
            this.delete_id = id
            this.delete_dialog_open = true
        },
        cancelDeleteSchoolHour() {
            if (this.isSavingSchoolHours) {
                return
            }
            this.delete_dialog_open = false
            this.delete_id = null
        },
        cancelEdit() {
            if (this.isSavingSchoolHours) {
                return
            }
            this.editing_id = null
            this.edit_data = {
                hour: null,
                from: '',
                until: '',
            }
        },
        async saveEditSchoolHour() {
            if (!this.editing_id) {
                return
            }

            await this.runSchoolHourMutation('edit', async () => {
                const payload = {
                    hour: Number(this.edit_data.hour),
                    from: this.edit_data.from || null,
                    until: this.edit_data.until || null,
                }
                const updated = await this.schoolHourStore.update(this.editing_id, payload)
                if (!updated) {
                    return
                }

                await this.schoolHourStore.index()
                this.editing_id = null
                this.edit_data = {
                    hour: null,
                    from: '',
                    until: '',
                }
            })
        },
        async confirmDeleteSchoolHour() {
            const id = this.delete_id
            if (!id) {
                return
            }

            await this.runSchoolHourMutation('delete', async () => {
                const deleted = await this.schoolHourStore.destroy(id)
                if (!deleted) {
                    return
                }

                await this.schoolHourStore.index()
                if (this.editing_id === id) {
                    this.editing_id = null
                    this.edit_data = {
                        hour: null,
                        from: '',
                        until: '',
                    }
                }
                this.delete_dialog_open = false
                this.delete_id = null
            })
        },
    },
}
</script>

<style scoped>
.school-hours-list {
    background: transparent;
    text-align: left;
}

.school-hours-form-wrap {
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.1);
    background: rgba(255, 255, 255, 0.72);
    padding: 10px;
}

.school-hours-list-item {
    border-radius: 10px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: rgba(255, 255, 255, 0.78);
    margin-bottom: 6px;
    padding: 0 6px;
}

.school-hour-row {
    justify-content: space-between;
    align-items: center;
}

.school-hour-chip,
.school-hour-time-chip {
    font-size: 0.88rem;
    font-weight: 600;
}

.school-hours-list :deep(.v-list-item__content) {
    text-align: left;
}

.school-hours-section-title {
    font-size: 1rem;
    font-weight: 650;
}
</style>
