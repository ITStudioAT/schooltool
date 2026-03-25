<template>
    <!-- NOTIFICATIONS OVERVIEW -->
    <ItsGridBox variant="overview" v-if="action !== 'teaching_notifications_new_or_edit'" color="primary" title="Verständigungen" icon="mdi-bell" class="w-100" :disabled="action != ''">
        <template #header-actions>
            <v-btn
                size="small"
                color="primary"
                variant="tonal"
                prepend-icon="mdi-plus"
                @click="newEntry">
                Hinzufügen
            </v-btn>
            <v-btn
                size="small"
                color="warning"
                variant="text"
                prepend-icon="mdi-restore"
                @click="openNotificationsResetDialog">
                Reset
            </v-btn>
            <v-btn
                size="small"
                color="primary"
                variant="text"
                prepend-icon="mdi-import"
                @click="openNotificationsImportDialog">
                Import
            </v-btn>
        </template>

        <div class="d-flex flex-wrap align-center ga-2 mt-2">
            <span class="text-body-2 text-medium-emphasis">Aktives Schuljahr</span>
            <v-chip size="small" color="primary" variant="tonal">
                {{ activeSchoolyearLabel }}
            </v-chip>
        </div>

        <!-- ALLE EINTRÄGE ANZEIGEN -->
        <v-list density="compact" class="bg-transparent">
            <v-list-item v-for="(entry, index) in notification_entries" :key="index" class="px-0">
                <div class="d-flex flex-row align-center justify-space-between w-100">
                    <div class="d-flex align-center ga-2 flex-wrap">
                        <div class="text-body-1 font-weight-medium">{{ entry.short_name }} - {{ entry.name }}</div>
                        <v-chip size="x-small" color="info" variant="tonal">
                            {{ notificationsUsageCountForEntry(entry) }} Einträge
                        </v-chip>
                    </div>
                    <div class="d-flex flex-row align-center ga-1">
                        <v-btn flat tile size="x-small" color="warning" icon="mdi-delete" @click="openDeleteDialog(index)" />
                        <v-btn flat tile size="x-small" color="primary" icon="mdi-pencil" @click="editEntry(index)" />
                    </div>
                </div>
                <v-divider class="mt-2" />
            </v-list-item>
        </v-list>

        <v-alert v-if="!notification_entries.length" type="info" variant="tonal" class="mt-2">
            Noch keine Verständigungs-Einträge vorhanden.
        </v-alert>

        <v-dialog v-model="notifications_import_dialog_open" persistent max-width="560">
            <v-card>
                <v-card-title class="d-flex align-center justify-space-between">
                    <span>Verständigungen importieren</span>
                    <v-btn icon="mdi-close" variant="text" @click="closeNotificationsImportDialog" />
                </v-card-title>
                <v-card-text>
                    <v-alert
                        :type="notificationsUsageCount > 0 ? 'warning' : 'info'"
                        variant="tonal"
                        class="mb-4">
                        <div class="font-weight-medium mb-2">
                            {{
                                notificationsUsageCount > 0
                                    ? 'Diese Verständigungen werden bereits verwendet:'
                                    : 'Diese Verständigungen werden derzeit nicht verwendet.'
                            }}
                        </div>
                        <div class="d-flex flex-wrap ga-2">
                            <v-chip color="warning" variant="flat" size="small">
                                {{ notificationsUsageCount }} Einträge
                            </v-chip>
                        </div>
                    </v-alert>
                    <div class="text-body-2 text-medium-emphasis">
                        <strong>Wenn Sie Verständigungen importieren, werden alle bisherigen Verständigungen gelöscht!</strong>
                    </div>
                    <div class="text-body-2 mt-3">
                        <strong>{{ notificationsImportLabel }}</strong>
                    </div>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn
                        color="primary"
                        variant="flat"
                        :disabled="!previousSchoolyear"
                        :loading="notifications_import_loading"
                        @click="importNotifications">
                        Importieren
                    </v-btn>
                    <v-btn color="primary" variant="text" @click="closeNotificationsImportDialog">Schliessen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="notifications_reset_dialog_open" persistent max-width="560">
            <v-card>
                <v-card-title class="d-flex align-center justify-space-between">
                    <span>Verständigungen zurücksetzen</span>
                    <v-btn icon="mdi-close" variant="text" @click="closeNotificationsResetDialog" />
                </v-card-title>
                <v-card-text>
                    <v-alert
                        :type="notificationsUsageCount > 0 ? 'warning' : 'info'"
                        variant="tonal"
                        class="mb-4">
                        <div class="font-weight-medium mb-2">
                            {{
                                notificationsUsageCount > 0
                                    ? 'Diese Verständigungen werden bereits verwendet:'
                                    : 'Diese Verständigungen werden derzeit nicht verwendet.'
                            }}
                        </div>
                        <div class="d-flex flex-wrap ga-2">
                            <v-chip color="warning" variant="flat" size="small">
                                {{ notificationsUsageCount }} Einträge
                            </v-chip>
                        </div>
                    </v-alert>
                    <div class="text-body-2 text-medium-emphasis">
                        <strong>Wenn Sie die Verständigungen zurücksetzen, werden alle bisherigen Verständigungen gelöscht!</strong>
                    </div>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn
                        color="error"
                        variant="flat"
                        :loading="notifications_reset_loading"
                        @click="resetNotifications">
                        Reset
                    </v-btn>
                    <v-btn color="primary" variant="text" @click="closeNotificationsResetDialog">Schliessen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="notifications_delete_dialog_open" persistent max-width="520">
            <v-card>
                <v-card-title class="d-flex align-center justify-space-between">
                    <span>Verständigung löschen</span>
                    <v-btn icon="mdi-close" variant="text" @click="closeDeleteDialog" />
                </v-card-title>
                <v-card-text>
                    <v-alert
                        :type="notificationsDeleteUsageCount > 0 ? 'warning' : 'info'"
                        variant="tonal"
                        class="mb-4">
                        <div class="font-weight-medium mb-2">
                            {{
                                notificationsDeleteUsageCount > 0
                                    ? 'Diese Verständigung wird bei Schüler:innen bereits verwendet:'
                                    : 'Diese Verständigung wird bei Schüler:innen derzeit nicht verwendet.'
                            }}
                        </div>
                        <div class="d-flex flex-wrap ga-2">
                            <v-chip :color="notificationsDeleteUsageCount > 0 ? 'warning' : 'info'" variant="flat" size="small">
                                {{ notificationsDeleteUsageCount }} Einträge
                            </v-chip>
                        </div>
                    </v-alert>
                    <div class="text-body-2 text-medium-emphasis">
                        Möchten Sie diesen Verständigungs-Eintrag wirklich löschen?
                    </div>
                    <div class="text-body-2 text-medium-emphasis mt-3">
                        Beim Löschen werden auch alle betroffenen Verständigungen der Schüler:innen entfernt.
                    </div>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn color="warning" variant="flat" @click="confirmDelete">Löschen</v-btn>
                    <v-btn color="primary" variant="text" @click="closeDeleteDialog">Abbrechen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </ItsGridBox>

    <!-- EDIT/NEW ENTRY FORM -->
    <ItsGridBox variant="overview" color="primary" :title="edit_index !== null ? 'Eintrag ändern' : 'Neuer Eintrag'" icon="mdi-bell" class="w-100 mt-4" v-if="action == 'teaching_notifications_new_or_edit'">
        <div class="d-flex flex-row align-center justify-end mt-2 ga-2">
            <v-btn icon="mdi-check" size="x-small" color="success" variant="flat" :disabled="!is_valid" @click="save" />
            <v-btn icon="mdi-close" size="x-small" color="warning" variant="flat" @click="abort" />
        </div>
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text>
                <v-form ref="form" v-model="is_valid" @submit.prevent="save" class="mb-4">
                    <div class="text-caption text-text">Bitte geben Sie die Felder ein (* = Pflichtfeld)</div>
                    <v-text-field autofocus v-model="data.short_name" label="Kurzzeichen *" :rules="[required(), maxLength(10)]" />
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
            notifications_import_dialog_open: false,
            notifications_reset_dialog_open: false,
            notifications_delete_dialog_open: false,
            notifications_import_loading: false,
            notifications_reset_loading: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useTeachingStore, ['settings']),
        ...mapWritableState(useSchoolyearStore, ['schoolyears']),
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
        notificationsImportLabel() {
            if (!this.previousSchoolyear) {
                return 'Import nicht möglich!'
            }

            return `Import vom Schuljahr: ${this.previousSchoolyear.concerns}`
        },
        notificationsUsageCount() {
            return Number(this.settings?.teaching_notifications_usage_count || 0)
        },
        notificationsUsageCounts() {
            return this.settings?.teaching_notifications_usage_counts || {}
        },
        notification_entries() {
            const entries = this.settings?.teaching_notifications || []
            return [...entries].sort((a, b) => (a.short_name || '').localeCompare(b.short_name || '', 'de'))
        },
        deleteEntryDefinition() {
            if (this.delete_index === null) {
                return null
            }

            return this.notification_entries[this.delete_index] || null
        },
        notificationsDeleteUsageCount() {
            const shortName = this.deleteEntryDefinition?.short_name

            if (!shortName) {
                return 0
            }

            return Number(this.notificationsUsageCounts?.[shortName] || 0)
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
        openNotificationsImportDialog() {
            this.notifications_import_dialog_open = true
        },
        closeNotificationsImportDialog() {
            this.notifications_import_dialog_open = false
        },
        openNotificationsResetDialog() {
            this.notifications_reset_dialog_open = true
        },
        closeNotificationsResetDialog() {
            this.notifications_reset_dialog_open = false
        },
        async importNotifications() {
            this.notifications_import_loading = true

            try {
                const imported = await this.teachingStore.importNotifications()

                if (imported) {
                    this.closeNotificationsImportDialog()
                }
            } finally {
                this.notifications_import_loading = false
            }
        },
        async resetNotifications() {
            this.notifications_reset_loading = true

            try {
                const reset = await this.teachingStore.resetNotifications()

                if (reset) {
                    this.closeNotificationsResetDialog()
                }
            } finally {
                this.notifications_reset_loading = false
            }
        },
        newEntry() {
            this.data = { short_name: '', name: '' }
            this.edit_index = null
            this.action = 'teaching_notifications_new_or_edit'
        },

        editEntry(index) {
            const entry = this.notification_entries[index]
            this.data = { ...entry }
            this.edit_index = index
            this.action = 'teaching_notifications_new_or_edit'
        },

        abort() {
            this.action = ''
            this.edit_index = null
        },

        openDeleteDialog(index) {
            this.delete_index = index
            this.notifications_delete_dialog_open = true
        },
        closeDeleteDialog() {
            this.notifications_delete_dialog_open = false
            this.delete_index = null
        },
        notificationsUsageCountForEntry(entry) {
            const shortName = entry?.short_name

            if (!shortName) {
                return 0
            }

            return Number(this.notificationsUsageCounts?.[shortName] || 0)
        },
        async save() {
            const entries = [...this.notification_entries]

            if (this.edit_index !== null) {
                entries[this.edit_index] = { ...this.data }
            } else {
                entries.push({ ...this.data })
            }

            await this.teachingStore.saveSettings({ teaching_notifications: entries })
            this.action = ''
            this.edit_index = null
        },
        async confirmDelete() {
            if (this.delete_index === null) {
                return
            }

            const entries = [...this.notification_entries]
            entries.splice(this.delete_index, 1)

            await this.teachingStore.saveSettings({ teaching_notifications: entries })
            this.closeDeleteDialog()
        },
    },
}
</script>
