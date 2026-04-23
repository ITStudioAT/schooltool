<template>
    <!-- BEHAVIOUR OVERVIEW -->
    <ItsGridBox variant="overview" v-if="action !== 'teaching_behaviour_new_or_edit'" color="primary" title="Verhalten" icon="mdi-account-alert" class="w-100" :disabled="action != '' || isSavingBehaviourSettings">
        <template #header-actions>
            <v-btn
                size="small"
                color="warning"
                variant="text"
                prepend-icon="mdi-restore"
                :disabled="isSavingBehaviourSettings"
                @click="openBehaviourResetDialog">
                Reset
            </v-btn>
            <v-btn
                size="small"
                color="primary"
                variant="text"
                prepend-icon="mdi-import"
                :disabled="isSavingBehaviourSettings"
                @click="openBehaviourImportDialog">
                Import
            </v-btn>
        </template>

        <div class="d-flex flex-wrap align-center ga-2 mt-2">
            <span class="text-body-2 text-medium-emphasis">Aktives Schuljahr</span>
            <v-chip size="small" color="primary" variant="tonal">
                {{ activeSchoolyearLabel }}
            </v-chip>
        </div>

        <div class="d-flex align-center justify-space-between mt-4 mb-2 ga-2">
            <div class="text-subtitle-2">Verhaltenseinträge</div>
            <v-btn
                size="small"
                color="primary"
                variant="tonal"
                prepend-icon="mdi-plus"
                :disabled="isSavingBehaviourSettings"
                @click="newEntry">
                Hinzufügen
            </v-btn>
        </div>

        <!-- ALLE EINTRÄGE ANZEIGEN -->
        <v-list density="compact" class="bg-transparent">
            <v-list-item v-for="(entry, index) in behaviour_entries" :key="index" class="px-0">
                <div class="d-flex flex-row align-center justify-space-between w-100">
                    <div class="d-flex align-center ga-2 flex-wrap">
                        <div class="text-body-1 font-weight-medium">{{ entry.short_name }} - {{ entry.name }}</div>
                        <v-chip size="x-small" color="info" variant="tonal">
                            {{ behaviourUsageCountForEntry(entry) }} Einträge
                        </v-chip>
                    </div>
                    <div class="d-flex flex-row align-center ga-1">
                        <v-btn flat tile size="x-small" color="warning" icon="mdi-delete" :disabled="isSavingBehaviourSettings" @click="openDeleteDialog(index)" />
                        <v-btn flat tile size="x-small" color="primary" icon="mdi-pencil" :disabled="isSavingBehaviourSettings" @click="editEntry(index)" />
                    </div>
                </div>
                <v-divider class="mt-2" />
            </v-list-item>
        </v-list>

        <v-alert v-if="!behaviour_entries.length" type="info" variant="tonal" class="mt-2">
            Noch keine Verhaltens-Einträge vorhanden.
        </v-alert>

        <v-dialog v-model="behaviour_import_dialog_open" persistent max-width="560">
            <v-card>
                <v-card-title class="d-flex align-center justify-space-between">
                    <span>Verhalten importieren</span>
                    <v-btn icon="mdi-close" variant="text" @click="closeBehaviourImportDialog" />
                </v-card-title>
                <v-card-text>
                    <v-alert
                        :type="behaviourUsageCount > 0 ? 'warning' : 'info'"
                        variant="tonal"
                        class="mb-4">
                        <div class="font-weight-medium mb-2">
                            {{
                                behaviourUsageCount > 0
                                    ? 'Diese Verhaltenseinträge werden bereits verwendet:'
                                    : 'Diese Verhaltenseinträge werden derzeit nicht verwendet.'
                            }}
                        </div>
                        <div class="d-flex flex-wrap ga-2">
                            <v-chip color="warning" variant="flat" size="small">
                                {{ behaviourUsageCount }} Einträge
                            </v-chip>
                        </div>
                    </v-alert>
                    <div class="text-body-2 text-medium-emphasis">
                        <strong>Wenn Sie Verhaltenseinträge importieren, werden alle bisherigen Verhalten gelöscht!</strong>
                    </div>
                    <div class="text-body-2 mt-3">
                        <strong>{{ behaviourImportLabel }}</strong>
                    </div>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn
                        color="primary"
                        variant="flat"
                        :disabled="!previousSchoolyear"
                        :loading="behaviour_import_loading"
                        @click="importBehaviour">
                        Importieren
                    </v-btn>
                    <v-btn color="primary" variant="text" @click="closeBehaviourImportDialog">Schliessen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="behaviour_reset_dialog_open" persistent max-width="560">
            <v-card>
                <v-card-title class="d-flex align-center justify-space-between">
                    <span>Verhalten zurücksetzen</span>
                    <v-btn icon="mdi-close" variant="text" @click="closeBehaviourResetDialog" />
                </v-card-title>
                <v-card-text>
                    <v-alert
                        :type="behaviourUsageCount > 0 ? 'warning' : 'info'"
                        variant="tonal"
                        class="mb-4">
                        <div class="font-weight-medium mb-2">
                            {{
                                behaviourUsageCount > 0
                                    ? 'Diese Verhaltenseinträge werden bereits verwendet:'
                                    : 'Diese Verhaltenseinträge werden derzeit nicht verwendet.'
                            }}
                        </div>
                        <div class="d-flex flex-wrap ga-2">
                            <v-chip color="warning" variant="flat" size="small">
                                {{ behaviourUsageCount }} Einträge
                            </v-chip>
                        </div>
                    </v-alert>
                    <div class="text-body-2 text-medium-emphasis">
                        <strong>Wenn Sie die Verhaltenseinträge zurücksetzen, werden alle bisherigen Verhalten gelöscht!</strong>
                    </div>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn
                        color="error"
                        variant="flat"
                        :loading="behaviour_reset_loading"
                        @click="resetBehaviour">
                        Reset
                    </v-btn>
                    <v-btn color="primary" variant="text" @click="closeBehaviourResetDialog">Schliessen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="behaviour_delete_dialog_open" persistent max-width="520">
            <v-card>
                <v-card-title class="d-flex align-center justify-space-between">
                    <span>Verhaltenseintrag löschen</span>
                    <v-btn icon="mdi-close" variant="text" @click="closeDeleteDialog" />
                </v-card-title>
                <v-card-text>
                    <v-alert
                        :type="behaviourDeleteUsageCount > 0 ? 'warning' : 'info'"
                        variant="tonal"
                        class="mb-4">
                        <div class="font-weight-medium mb-2">
                            {{
                                behaviourDeleteUsageCount > 0
                                    ? 'Dieser Verhaltenseintrag wird bei Schüler:innen bereits verwendet:'
                                    : 'Dieser Verhaltenseintrag wird bei Schüler:innen derzeit nicht verwendet.'
                            }}
                        </div>
                        <div class="d-flex flex-wrap ga-2">
                            <v-chip :color="behaviourDeleteUsageCount > 0 ? 'warning' : 'info'" variant="flat" size="small">
                                {{ behaviourDeleteUsageCount }} Einträge
                            </v-chip>
                        </div>
                    </v-alert>
                    <div class="text-body-2 text-medium-emphasis">
                        Möchten Sie diesen Verhaltenseintrag wirklich löschen?
                    </div>
                    <div class="text-body-2 text-medium-emphasis mt-3">
                        Beim Löschen werden auch alle betroffenen Verhaltenseinträge der Schüler:innen entfernt.
                    </div>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn color="warning" variant="flat" :loading="behaviour_save_action === 'delete'" :disabled="isSavingBehaviourSettings" @click="confirmDelete">Löschen</v-btn>
                    <v-btn color="primary" variant="text" :disabled="isSavingBehaviourSettings" @click="closeDeleteDialog">Abbrechen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </ItsGridBox>

    <!-- EDIT/NEW ENTRY FORM -->
    <ItsGridBox variant="overview" color="primary" :title="edit_index !== null ? 'Eintrag ändern' : 'Neuer Eintrag'" icon="mdi-account-alert" class="w-100 mt-4" v-if="action == 'teaching_behaviour_new_or_edit'" :disabled="isSavingBehaviourSettings">
        <div class="d-flex flex-row align-center justify-end mt-2 ga-2">
            <v-btn icon="mdi-check" size="x-small" color="success" variant="flat" :loading="behaviour_save_action === 'save'" :disabled="!is_valid || isSavingBehaviourSettings" @click="save" />
            <v-btn icon="mdi-close" size="x-small" color="warning" variant="flat" :disabled="isSavingBehaviourSettings" @click="abort" />
        </div>
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text>
                <v-form ref="form" v-model="is_valid" @submit.prevent="save" class="mb-4">
                    <div class="text-caption text-text">Bitte geben Sie die Felder ein (* = Pflichtfeld)</div>
                    <v-text-field autofocus v-model="data.short_name" label="Kurzzeichen (z. B. E für Ermahnung)*" :rules="[required(), maxLength(10)]" />
                    <v-text-field v-model="data.name" label="Bezeichnung *" :rules="[required(), maxLength(255)]" />
                </v-form>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.teachingStore = useTeachingStore()
        this.schoolyearStore = useSchoolyearStore()
        await Promise.all([this.teachingStore.loadSettings(), this.schoolyearStore.index()])
    },

    data() {
        return {
            adminStore: null,
            teachingStore: null,
            schoolyearStore: null,
            is_valid: false,
            data: {
                short_name: '',
                name: '',
            },
            edit_index: null,
            delete_index: null,
            behaviour_import_dialog_open: false,
            behaviour_reset_dialog_open: false,
            behaviour_delete_dialog_open: false,
            behaviour_import_loading: false,
            behaviour_reset_loading: false,
            behaviour_save_action: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useTeachingStore, ['settings']),
        ...mapWritableState(useSchoolyearStore, ['schoolyears']),
        isSavingBehaviourSettings() {
            return this.behaviour_save_action !== null
        },
        activeSchoolyearLabel() {
            return this.config?.selected_schoolyear?.name || this.config?.selected_schoolyear?.concerns || 'Kein Schuljahr gewählt'
        },
        activeSchoolyearConcern() {
            return this.normalizeSchoolyearConcern(this.config?.selected_schoolyear?.concerns)
        },
        previousSchoolyearConcern() {
            const parsedConcern = this.parseSchoolyearConcern(this.activeSchoolyearConcern)

            if (!parsedConcern) {
                return null
            }

            return `${parsedConcern.startYear - 1}/${String(parsedConcern.endYear - 1).slice(-2)}`
        },
        previousSchoolyear() {
            const previousConcern = this.previousSchoolyearConcern

            if (!previousConcern) {
                return null
            }

            return (this.schoolyears || []).find((schoolyear) => this.normalizeSchoolyearConcern(schoolyear?.concerns) === previousConcern) || null
        },
        behaviourImportLabel() {
            if (!this.previousSchoolyear) {
                return 'Import nicht möglich!'
            }

            return `Import vom Schuljahr: ${this.previousSchoolyear.concerns}`
        },
        behaviourUsageCount() {
            return Number(this.settings?.teaching_behaviour_usage_count || 0)
        },
        behaviour_entries() {
            const entries = this.settings?.teaching_behaviour || []
            return [...entries].sort((a, b) => (a.short_name || '').localeCompare(b.short_name || '', 'de'))
        },
        behaviourUsageCounts() {
            return this.settings?.teaching_behaviour_usage_counts || {}
        },
        deleteEntryDefinition() {
            if (this.delete_index === null) {
                return null
            }

            return this.behaviour_entries[this.delete_index] || null
        },
        behaviourDeleteUsageCount() {
            const shortName = this.deleteEntryDefinition?.short_name

            if (!shortName) {
                return 0
            }

            return Number(this.behaviourUsageCounts?.[shortName] || 0)
        },
    },

    watch: {
        'data.short_name'(val) {
            if (val && val !== val.toUpperCase()) {
                this.data.short_name = val.toUpperCase()
            }
        },
    },

    methods: {
        async runBehaviourSettingsMutation(action, callback) {
            if (this.behaviour_save_action) {
                return false
            }

            this.behaviour_save_action = action
            await this.$nextTick()

            try {
                return await callback()
            } finally {
                this.behaviour_save_action = null
            }
        },
        normalizeSchoolyearConcern(value) {
            if (typeof value !== 'string') {
                return ''
            }

            const match = value.match(/(\d{4})\/(\d{2}|\d{4})/)

            return match ? `${match[1]}/${match[2].slice(-2)}` : ''
        },
        parseSchoolyearConcern(value) {
            const normalizedValue = this.normalizeSchoolyearConcern(value)
            const match = normalizedValue.match(/^(\d{4})\/(\d{2})$/)

            if (!match) {
                return null
            }

            const startYear = Number(match[1])
            const endYear = Number(`${String(startYear).slice(0, 2)}${match[2]}`)

            if (!Number.isFinite(startYear) || !Number.isFinite(endYear)) {
                return null
            }

            return { startYear, endYear }
        },
        openBehaviourImportDialog() {
            this.behaviour_import_dialog_open = true
        },
        closeBehaviourImportDialog() {
            this.behaviour_import_dialog_open = false
        },
        openBehaviourResetDialog() {
            this.behaviour_reset_dialog_open = true
        },
        closeBehaviourResetDialog() {
            this.behaviour_reset_dialog_open = false
        },
        async resetBehaviour() {
            this.behaviour_reset_loading = true

            try {
                const reset = await this.teachingStore.resetBehaviour()

                if (reset) {
                    this.closeBehaviourResetDialog()
                }
            } finally {
                this.behaviour_reset_loading = false
            }
        },
        async importBehaviour() {
            this.behaviour_import_loading = true

            try {
                const imported = await this.teachingStore.importBehaviour()

                if (imported) {
                    this.closeBehaviourImportDialog()
                }
            } finally {
                this.behaviour_import_loading = false
            }
        },
        newEntry() {
            this.data = { short_name: '', name: '' }
            this.edit_index = null
            this.action = 'teaching_behaviour_new_or_edit'
        },

        editEntry(index) {
            const entry = this.behaviour_entries[index]
            this.data = { ...entry }
            this.edit_index = index
            this.action = 'teaching_behaviour_new_or_edit'
        },

        abort() {
            this.action = ''
            this.edit_index = null
        },

        openDeleteDialog(index) {
            this.delete_index = index
            this.behaviour_delete_dialog_open = true
        },

        closeDeleteDialog() {
            this.behaviour_delete_dialog_open = false
            this.delete_index = null
        },
        behaviourUsageCountForEntry(entry) {
            const shortName = entry?.short_name

            if (!shortName) {
                return 0
            }

            return Number(this.behaviourUsageCounts?.[shortName] || 0)
        },

        async save() {
            await this.runBehaviourSettingsMutation('save', async () => {
                const entries = [...this.behaviour_entries]

                if (this.edit_index !== null) {
                    entries[this.edit_index] = { ...this.data }
                } else {
                    entries.push({ ...this.data })
                }

                await this.teachingStore.saveSettings({ teaching_behaviour: entries })
                this.action = ''
                this.edit_index = null
            })
        },

        async confirmDelete() {
            if (this.delete_index === null) {
                return
            }

            await this.runBehaviourSettingsMutation('delete', async () => {
                const entries = [...this.behaviour_entries]
                entries.splice(this.delete_index, 1)

                await this.teachingStore.saveSettings({ teaching_behaviour: entries })
                this.closeDeleteDialog()
            })
        },
    },
}
</script>
