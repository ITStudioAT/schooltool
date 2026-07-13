<template>
    <v-col cols="12" class="pb-1">
        <section class="teaching-settings-toolbar-secondary">
            <v-btn-toggle
                v-model="mirroredPanelSelection"
                mandatory
                class="teaching-settings-panel-switcher-secondary"
                color="primary"
                :disabled="isLocked">
                <v-btn
                    v-for="panel in availablePanels"
                    :key="`mirror-${panel.id}`"
                    class="teaching-settings-toolbar-btn-secondary"
                    :value="panel.id"
                    :prepend-icon="panel.icon">
                    <span class="teaching-settings-toolbar-btn-copy">
                        <span>{{ panel.label }}</span>
                        <span class="teaching-settings-toolbar-btn-meta">{{ activeSchoolyearLabel }}</span>
                    </span>
                </v-btn>
            </v-btn-toggle>
        </section>
    </v-col>

    <v-col cols="12" md="6" lg="7" xl="4">
        <section class="teaching-settings-content-shell">
            <v-window v-model="active_panel" class="w-100" :touch="false">
                <v-window-item value="basic">
                    <v-row class="w-100 ma-0" dense>
                        <v-col cols="12" class="teaching-settings-panel-col">
                            <ItsGridBox variant="overview" color="primary" title="Grundeinstellungen" icon="mdi-cog-outline" class="w-100">
                                <BasicSettings />
                            </ItsGridBox>
                        </v-col>
                    </v-row>
                </v-window-item>

                <v-window-item v-if="usesLegacyTeachingSettings && showBehaviourEnabled" value="behaviour">
                    <v-row class="w-100 ma-0" dense>
                        <v-col cols="12" class="teaching-settings-panel-col">
                            <Behaviour />
                        </v-col>
                    </v-row>
                </v-window-item>

                <v-window-item v-if="usesLegacyTeachingSettings" value="notifications">
                    <v-row class="w-100 ma-0" dense>
                        <v-col cols="12" class="teaching-settings-panel-col">
                            <Notifications />
                        </v-col>
                    </v-row>
                </v-window-item>

                <v-window-item value="entries">
                    <v-row class="w-100 ma-0" dense>
                        <v-col cols="12" class="teaching-settings-panel-col">
                            <Entries :key="config?.selected_schoolyear?.id || 'no-schoolyear'" />
                        </v-col>
                    </v-row>
                </v-window-item>

                <v-window-item v-if="usesLegacyTeachingSettings" value="schemas">
                    <v-row class="w-100 ma-0" dense>
                        <v-col cols="12" class="teaching-settings-panel-col">
                            <ItsGridBox variant="overview" color="primary" title="Benotungsschemas" icon="mdi-book-cog-outline" class="w-100">
                                <template #header-actions>
                                    <div class="d-flex align-center ga-1">
                                        <v-btn
                                            size="small"
                                            color="warning"
                                            variant="text"
                                            prepend-icon="mdi-restore"
                                            :disabled="isLocked || !selected_schema_id"
                                            @click="openSchemaResetDialog">
                                            Reset
                                        </v-btn>
                                        <v-btn
                                            size="small"
                                            color="primary"
                                            variant="text"
                                            prepend-icon="mdi-import"
                                            :disabled="isLocked || !selected_schema_id"
                                            @click="openSchemaImportDialog">
                                            Import
                                        </v-btn>
                                    </div>
                                </template>

                                <div class="d-flex flex-wrap align-center ga-2 mt-2">
                                    <v-chip
                                        v-for="schema in schemas"
                                        :key="schema.id"
                                        :color="selected_schema_id === schema.id ? 'primary' : 'secondary'"
                                        variant="flat"
                                        :disabled="isLocked"
                                        :class="isLocked ? '' : 'cursor-pointer'"
                                        @click="selectSchema(schema.id)">
                                        {{ schema.name }}
                                    </v-chip>
                                    <v-btn icon="mdi-plus" size="small" color="primary" variant="tonal" :loading="schema_settings_saving_action === 'new-schema'" :disabled="isLocked" @click="newSchema" />
                                </div>

                                <div v-if="selected_schema_id" class="d-flex flex-row align-center mt-3 ga-2 flex-wrap">
                                    <v-btn v-if="!is_renaming && !selectedSchemaIsStandard" flat tile size="small" color="primary" prepend-icon="mdi-pencil" :disabled="isLocked" @click="startRename">Umbenennen</v-btn>
                                    <v-btn
                                        v-if="!is_renaming && !is_deleting && !selectedSchemaIsStandard && !selectedSchemaInUse"
                                        flat
                                        tile
                                        size="small"
                                        color="warning"
                                        prepend-icon="mdi-delete"
                                        :disabled="isLocked"
                                        @click="is_deleting = true">
                                        Löschen
                                    </v-btn>
                                    <v-btn v-if="is_deleting" flat tile size="small" color="success" prepend-icon="mdi-delete-off" :disabled="isLocked" @click="is_deleting = false">Abbruch</v-btn>
                                    <v-btn v-if="is_deleting" flat tile size="small" color="error" prepend-icon="mdi-delete" :loading="schema_settings_saving_action === 'delete-schema'" :disabled="isLocked" @click="deleteSchema">Endgültig löschen</v-btn>
                                    <v-spacer />
                                    <v-btn v-if="is_renaming" icon="mdi-check" size="x-small" color="success" variant="flat" :loading="schema_settings_saving_action === 'rename-schema'" :disabled="isLocked" @click="saveRename" />
                                    <v-btn v-if="is_renaming" icon="mdi-close" size="x-small" color="warning" variant="flat" :disabled="isLocked" @click="is_renaming = false" />
                                </div>

                                <v-text-field v-if="is_renaming" v-model="rename_value" label="Name" density="compact" hide-details autofocus class="mt-2" @keyup.enter="saveRename" />

                                <template v-if="selected_schema_id">
                                    <v-btn-toggle
                                        v-model="active_schema_panel"
                                        mandatory
                                        color="primary"
                                        divided
                                        class="settings-schema-panel-toggles mt-4"
                                        :disabled="isLocked">
                                        <v-btn
                                            rounded="pill"
                                            class="teaching-settings-toolbar-btn"
                                            value="works"
                                            prepend-icon="mdi-test-tube">
                                            Arbeiten & Noten
                                        </v-btn>
                                        <v-btn
                                            rounded="pill"
                                            class="teaching-settings-toolbar-btn"
                                            value="grading"
                                            prepend-icon="mdi-calculator-variant-outline">
                                            Benotung
                                        </v-btn>
                                        <v-btn
                                            rounded="pill"
                                            class="teaching-settings-toolbar-btn"
                                            value="category_evaluation"
                                            prepend-icon="mdi-format-list-bulleted-square">
                                            Kategoriebewertung
                                        </v-btn>
                                    </v-btn-toggle>

                                    <v-row class="w-100 ma-0 mt-2" dense>
                                        <v-col cols="12" v-if="isSchemaPanelActive('works')">
                                            <WorksAndGrades :key="`works-${selected_schema_id}-${schema_panel_revision}`" :schema-id="selected_schema_id" />
                                        </v-col>
                                        <v-col cols="12" v-if="isSchemaPanelActive('grading')">
                                            <Grading :key="`grading-${selected_schema_id}-${schema_panel_revision}`" :schema-id="selected_schema_id" />
                                        </v-col>
                                        <v-col cols="12" v-if="isSchemaPanelActive('category_evaluation')">
                                            <CategoryEvaluation :key="`category-evaluation-${selected_schema_id}-${schema_panel_revision}`" :schema-id="selected_schema_id" />
                                        </v-col>
                                    </v-row>
                                </template>

                                <v-col v-else-if="!schemas.length" cols="12">
                                    <v-alert type="info" variant="tonal">
                                        Noch kein Benotungsschema vorhanden. Erstellen Sie ein neues Schema, um Arbeiten und Benotung zu konfigurieren.
                                    </v-alert>
                                </v-col>

                                <v-dialog v-model="schema_import_dialog_open" persistent max-width="560">
                                    <v-card>
                                        <v-card-title class="d-flex align-center justify-space-between">
                                            <span>Benotungsschema importieren</span>
                                            <v-btn icon="mdi-close" variant="text" @click="closeSchemaImportDialog" />
                                        </v-card-title>
                                        <v-card-text>
                                            <v-alert
                                                v-if="selectedSchemaUsageWarningVisible"
                                                type="warning"
                                                variant="tonal"
                                                class="mb-4">
                                                <div class="font-weight-medium mb-2">
                                                    Dieses Benotungsschema wird bereits verwendet.
                                                </div>
                                                <div class="d-flex flex-wrap ga-2">
                                                    <v-chip
                                                        v-for="item in selectedSchemaUsageItems"
                                                        :key="item.label"
                                                        color="warning"
                                                        variant="flat"
                                                        size="small">
                                                        {{ item.count }} {{ item.label }}
                                                    </v-chip>
                                                </div>
                                            </v-alert>
                                            <div class="text-body-2 text-medium-emphasis">
                                                <strong>Wenn Sie ein Benotungsschema importieren, werden alle bisherigen Benotungen gelöscht!</strong>
                                            </div>
                                            <div class="text-body-2 mt-3">
                                                <strong>{{ schoolyearImportLabel }}</strong>
                                            </div>
                                        </v-card-text>
                                        <v-card-actions class="justify-end">
                                            <v-btn
                                                color="primary"
                                                variant="flat"
                                                :disabled="!selected_schema_id || !previousSchoolyear"
                                                :loading="schema_import_loading"
                                                @click="importSchema">
                                                Importieren
                                            </v-btn>
                                            <v-btn color="primary" variant="text" @click="closeSchemaImportDialog">Schliessen</v-btn>
                                        </v-card-actions>
                                    </v-card>
                                </v-dialog>

                                <v-dialog v-model="schema_reset_dialog_open" persistent max-width="560">
                                    <v-card>
                                        <v-card-title class="d-flex align-center justify-space-between">
                                            <span>Benotungsschema zurücksetzen</span>
                                            <v-btn icon="mdi-close" variant="text" @click="closeSchemaResetDialog" />
                                        </v-card-title>
                                        <v-card-text>
                                            <v-alert
                                                v-if="selectedSchemaUsageWarningVisible"
                                                type="warning"
                                                variant="tonal"
                                                class="mb-4">
                                                <div class="font-weight-medium mb-2">
                                                    Dieses Benotungsschema wird bereits verwendet.
                                                </div>
                                                <div class="d-flex flex-wrap ga-2">
                                                    <v-chip
                                                        v-for="item in selectedSchemaUsageItems"
                                                        :key="`reset-${item.label}`"
                                                        color="warning"
                                                        variant="flat"
                                                        size="small">
                                                        {{ item.count }} {{ item.label }}
                                                    </v-chip>
                                                </div>
                                            </v-alert>
                                            <div class="text-body-2 text-medium-emphasis">
                                                <strong>Wenn Sie das Benotungsschema resetten, werden alle Schüler:innen-Benotungen gelöscht!</strong>
                                            </div>
                                        </v-card-text>
                                        <v-card-actions class="justify-end">
                                            <v-btn
                                                color="error"
                                                variant="flat"
                                                :disabled="!selected_schema_id"
                                                :loading="schema_reset_loading"
                                                @click="resetSchema">
                                                Reset
                                            </v-btn>
                                            <v-btn color="primary" variant="text" @click="closeSchemaResetDialog">Schliessen</v-btn>
                                        </v-card-actions>
                                    </v-card>
                                </v-dialog>
                            </ItsGridBox>
                        </v-col>
                    </v-row>
                </v-window-item>

                <v-window-item v-if="canManageOwnHolidays" value="my_holidays">
                    <v-row class="w-100 ma-0" dense>
                        <v-col cols="12" class="teaching-settings-panel-col">
                            <MyHolidays />
                        </v-col>
                    </v-row>
                </v-window-item>
            </v-window>
        </section>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import WorksAndGrades from './components/WorksAndGrades.vue'
import Grading from './components/Grading.vue'
import CategoryEvaluation from './components/CategoryEvaluation.vue'
import BasicSettings from './components/BasicSettings.vue'
import Behaviour from './components/Behaviour.vue'
import Entries from './components/Entries.vue'
import Notifications from './components/Notifications.vue'
import MyHolidays from './components/MyHolidays.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { WorksAndGrades, Grading, CategoryEvaluation, BasicSettings, Behaviour, Entries, Notifications, MyHolidays, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.teachingStore = useTeachingStore()
        this.courseStore = useCourseStore()
        this.schoolyearStore = useSchoolyearStore()
        await this.teachingStore.loadSettings()

        if (this.usesLegacyTeachingSettings) {
            await Promise.all([this.courseStore.index(), this.schoolyearStore.index()])
        }

        if (this.usesLegacyTeachingSettings && this.schemas.length) {
            this.selected_schema_id = this.schemas[0].id
        }

        this.ensureActivePanelAvailable()
    },

    data() {
        return {
            adminStore: null,
            teachingStore: null,
            courseStore: null,
            schoolyearStore: null,
            selected_schema_id: null,
            active_panel: this.$route?.query?.panel || 'behaviour',
            active_schema_panel: 'works',
            is_renaming: false,
            rename_value: '',
            is_deleting: false,
            schema_import_dialog_open: false,
            schema_reset_dialog_open: false,
            schema_import_loading: false,
            schema_reset_loading: false,
            schema_panel_revision: 0,
            schema_settings_saving_action: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action']),
        isLocked() {
            return this.action === 'teaching_work_new_or_edit' || this.schema_settings_saving_action !== null
        },
        ...mapWritableState(useTeachingStore, ['settings']),
        ...mapWritableState(useCourseStore, ['courses']),
        ...mapWritableState(useSchoolyearStore, ['schoolyears']),
        schemas() {
            return this.settings?.teaching_schemas || []
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
        schoolyearImportLabel() {
            if (!this.previousSchoolyear) {
                return 'Import nicht möglich!'
            }

            return `Import vom Schuljahr: ${this.previousSchoolyear.concerns}`
        },
        selectedSchema() {
            return this.schemas.find((schema) => schema.id === this.selected_schema_id) || null
        },
        selectedSchemaInUse() {
            return (this.courses || []).some((c) => c.teaching_schema_id === this.selected_schema_id)
        },
        selectedSchemaCourseUsageCount() {
            return (this.courses || []).filter((course) => course.teaching_schema_id === this.selected_schema_id).length
        },
        selectedSchemaWorkCount() {
            return this.selectedSchema?.works?.length || 0
        },
        selectedSchemaCategoryCount() {
            return this.selectedSchema?.grading?.categories?.length || 0
        },
        selectedSchemaUsageItems() {
            return [
                { count: this.selectedSchemaCourseUsageCount, label: 'Kurse' },
                { count: this.selectedSchemaWorkCount, label: 'Arbeiten' },
                { count: this.selectedSchemaCategoryCount, label: 'Kategorien' },
            ].filter((item) => item.count > 0)
        },
        selectedSchemaUsageWarningVisible() {
            return this.selectedSchemaUsageItems.length > 0
        },
        selectedSchemaIsStandard() {
            const schema = this.schemas.find((s) => s.id === this.selected_schema_id)
            return schema?.name === 'Standard'
        },
        canManageOwnHolidays() {
            const roles = this.config?.roles || []
            return roles.includes('teacher') || roles.includes('admin') || roles.includes('super_admin')
        },
        activeSchoolyearLabel() {
            return this.config?.selected_schoolyear?.name || this.config?.selected_schoolyear?.concerns || 'Kein Schuljahr gewählt'
        },
        showBehaviourEnabled() {
            return this.settings?.teaching_show_behaviour !== false
        },
        usesLegacyTeachingSettings() {
            const schoolyear = this.parseSchoolyearConcern(this.activeSchoolyearConcern)

            return !schoolyear || schoolyear.startYear <= 2025
        },
        availablePanels() {
            const panels = [
                { id: 'basic', label: 'Grundeinstellungen', icon: 'mdi-cog-outline' },
                { id: 'entries', label: 'Einträge', icon: 'mdi-format-list-bulleted-type' },
            ]

            if (this.usesLegacyTeachingSettings) {
                if (this.showBehaviourEnabled) {
                    panels.push({ id: 'behaviour', label: 'Verhalten', icon: 'mdi-account-alert-outline' })
                }

                panels.push(
                    { id: 'notifications', label: 'Verständigungen', icon: 'mdi-bell-outline' },
                    { id: 'schemas', label: 'Benotungsschemas', icon: 'mdi-book-cog-outline' },
                )
            }

            if (this.canManageOwnHolidays) {
                panels.push({ id: 'my_holidays', label: 'Eigene freie Tage', icon: 'mdi-calendar-heart' })
            }

            return panels
        },
        mirroredPanelSelection: {
            get() {
                return this.active_panel
            },
            set(value) {
                const selectedPanel = Array.isArray(value) ? value[0] : value
                if (!selectedPanel) {
                    return
                }
                this.activatePanel(selectedPanel)
            },
        },
    },

    watch: {
        active_panel(newPanel) {
            if (this.$route?.query?.panel !== newPanel) {
                this.$router.replace({ path: this.$route.path, query: { panel: newPanel } }).catch(() => {})
            }
        },
        canManageOwnHolidays(newValue) {
            if (!newValue && this.active_panel === 'my_holidays') {
                this.active_panel = this.showBehaviourEnabled ? 'behaviour' : 'basic'
            }
        },
        'settings.teaching_show_behaviour'(newValue) {
            if (newValue === false && this.active_panel === 'behaviour') {
                this.active_panel = 'basic'
            }
        },
        usesLegacyTeachingSettings() {
            this.ensureActivePanelAvailable()
        },
    },

    methods: {
        async runSchemaSettingsMutation(action, callback) {
            if (this.schema_settings_saving_action) {
                return false
            }

            this.schema_settings_saving_action = action
            await this.$nextTick()

            try {
                return await callback()
            } finally {
                this.schema_settings_saving_action = null
            }
        },
        isPanelActive(panel) {
            return this.active_panel === panel
        },
        activatePanel(panel) {
            const panelExists = this.availablePanels.some((availablePanel) => availablePanel.id === panel)
            if (!panelExists) {
                return
            }
            this.active_panel = panel
        },
        ensureActivePanelAvailable() {
            const activePanelIsAvailable = this.availablePanels.some((panel) => panel.id === this.active_panel)

            if (activePanelIsAvailable) {
                return
            }

            this.active_panel = this.availablePanels.some((panel) => panel.id === 'entries') ? 'entries' : 'basic'
        },
        isSchemaPanelActive(panel) {
            return this.active_schema_panel === panel
        },
        activateSchemaPanel(panel) {
            this.active_schema_panel = panel
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
        openSchemaImportDialog() {
            this.schema_import_dialog_open = true
        },
        closeSchemaImportDialog() {
            this.schema_import_dialog_open = false
        },
        openSchemaResetDialog() {
            this.schema_reset_dialog_open = true
        },
        closeSchemaResetDialog() {
            this.schema_reset_dialog_open = false
        },
        async importSchema() {
            this.schema_import_loading = true

            try {
                const imported = await this.teachingStore.importSchema(this.selected_schema_id)

                if (imported) {
                    this.refreshSelectedSchemaPanels()
                    this.closeSchemaImportDialog()
                }
            } finally {
                this.schema_import_loading = false
            }
        },
        async resetSchema() {
            this.schema_reset_loading = true

            try {
                const reset = await this.teachingStore.resetSchema(this.selected_schema_id)

                if (reset) {
                    this.refreshSelectedSchemaPanels()
                    this.closeSchemaResetDialog()
                }
            } finally {
                this.schema_reset_loading = false
            }
        },
        refreshSelectedSchemaPanels() {
            this.schema_panel_revision += 1
        },
        selectSchema(id) {
            this.selected_schema_id = id
            this.is_renaming = false
            this.is_deleting = false
        },

        async newSchema() {
            await this.runSchemaSettingsMutation('new-schema', async () => {
                const standard = this.schemas.find((s) => s.name === 'Standard')
                const schemas = [...this.schemas]
                const newId = crypto.randomUUID()
                schemas.push({
                    id: newId,
                    name: 'Neues Schema',
                    works: JSON.parse(JSON.stringify(standard?.works || [])),
                    grading: JSON.parse(JSON.stringify(standard?.grading || {})),
                })
                await this.teachingStore.saveSettings({ teaching_schemas: schemas })
                this.selected_schema_id = newId
            })
        },

        startRename() {
            const schema = this.schemas.find((s) => s.id === this.selected_schema_id)
            if (!schema) return
            this.rename_value = schema.name
            this.is_renaming = true
            this.is_deleting = false
        },

        async saveRename() {
            if (!this.rename_value.trim()) return
            await this.runSchemaSettingsMutation('rename-schema', async () => {
                const schemas = this.schemas.map((s) => (s.id === this.selected_schema_id ? { ...s, name: this.rename_value.trim() } : s))
                await this.teachingStore.saveSettings({ teaching_schemas: schemas })
                this.is_renaming = false
            })
        },

        async deleteSchema() {
            await this.runSchemaSettingsMutation('delete-schema', async () => {
                const schemas = this.schemas.filter((s) => s.id !== this.selected_schema_id)
                await this.teachingStore.saveSettings({ teaching_schemas: schemas })
                this.selected_schema_id = schemas.length ? schemas[0].id : null
                this.is_deleting = false
            })
        },
    },
}
</script>

<style scoped>
.teaching-settings-toolbar-secondary {
    width: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    gap: 8px;
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
}

.teaching-settings-toolbar-btn {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
}

.teaching-settings-panel-switcher-secondary {
    width: 100%;
    flex-wrap: wrap;
    row-gap: 6px;
    height: auto !important;
}

.teaching-settings-toolbar-btn-secondary {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
    height: auto !important;
    min-height: 56px !important;
}

.teaching-settings-toolbar-btn-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.15;
    gap: 4px;
}

.teaching-settings-toolbar-btn-meta {
    color: rgba(255, 255, 255, 0.98);
    font-size: 0.76rem;
    font-weight: 700;
    background: rgba(15, 23, 42, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    padding: 2px 8px;
}

.teaching-settings-content-shell {
    border-radius: 16px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.66);
    padding: 8px;
}

.settings-schema-panel-toggles {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    border-radius: 14px;
    border: 1px solid rgba(16, 38, 58, 0.09);
    background: rgba(255, 255, 255, 0.76);
    padding: 10px;
}
</style>
