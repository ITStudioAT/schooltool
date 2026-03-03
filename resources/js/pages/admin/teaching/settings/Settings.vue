<template>
    <v-col cols="12" class="pb-1">
        <section class="teaching-settings-toolbar-secondary">
            <v-btn-toggle
                v-model="mirroredPanelSelection"
                mandatory
                class="teaching-settings-panel-switcher-secondary"
                color="primary"
                divided>
                <v-btn
                    v-for="panel in availablePanels"
                    :key="`mirror-${panel.id}`"
                    class="teaching-settings-toolbar-btn-secondary"
                    :value="panel.id"
                    :prepend-icon="panel.icon">
                    {{ panel.label }}
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

                <v-window-item v-if="showBehaviourEnabled" value="behaviour">
                    <v-row class="w-100 ma-0" dense>
                        <v-col cols="12" class="teaching-settings-panel-col">
                            <Behaviour />
                        </v-col>
                    </v-row>
                </v-window-item>

                <v-window-item value="notifications">
                    <v-row class="w-100 ma-0" dense>
                        <v-col cols="12" class="teaching-settings-panel-col">
                            <Notifications />
                        </v-col>
                    </v-row>
                </v-window-item>

                <v-window-item value="schemas">
                    <v-row class="w-100 ma-0" dense>
                        <v-col cols="12" class="teaching-settings-panel-col">
                            <ItsGridBox variant="overview" color="primary" title="Benotungsschemas" icon="mdi-book-cog-outline" class="w-100">
                                <div class="d-flex flex-wrap align-center ga-2 mt-2">
                                    <v-chip
                                        v-for="schema in schemas"
                                        :key="schema.id"
                                        :color="selected_schema_id === schema.id ? 'primary' : 'secondary'"
                                        variant="flat"
                                        class="cursor-pointer"
                                        @click="selectSchema(schema.id)">
                                        {{ schema.name }}
                                    </v-chip>
                                    <v-btn icon="mdi-plus" size="small" color="primary" variant="tonal" @click="newSchema" />
                                </div>

                                <div v-if="selected_schema_id" class="d-flex flex-row align-center mt-3 ga-2 flex-wrap">
                                    <v-btn v-if="!is_renaming && !selectedSchemaIsStandard" flat tile size="small" color="primary" prepend-icon="mdi-pencil" @click="startRename">Umbenennen</v-btn>
                                    <v-btn
                                        v-if="!is_renaming && !is_deleting && !selectedSchemaIsStandard && !selectedSchemaInUse"
                                        flat
                                        tile
                                        size="small"
                                        color="warning"
                                        prepend-icon="mdi-delete"
                                        @click="is_deleting = true">
                                        Löschen
                                    </v-btn>
                                    <v-btn v-if="is_deleting" flat tile size="small" color="success" prepend-icon="mdi-delete-off" @click="is_deleting = false">Abbruch</v-btn>
                                    <v-btn v-if="is_deleting" flat tile size="small" color="error" prepend-icon="mdi-delete" @click="deleteSchema">Endgültig löschen</v-btn>
                                    <v-spacer />
                                    <v-btn v-if="is_renaming" icon="mdi-check" size="x-small" color="success" variant="flat" @click="saveRename" />
                                    <v-btn v-if="is_renaming" icon="mdi-close" size="x-small" color="warning" variant="flat" @click="is_renaming = false" />
                                </div>

                                <v-text-field v-if="is_renaming" v-model="rename_value" label="Name" density="compact" hide-details autofocus class="mt-2" @keyup.enter="saveRename" />

                                <template v-if="selected_schema_id">
                                    <v-btn-toggle
                                        v-model="active_schema_panel"
                                        mandatory
                                        color="primary"
                                        divided
                                        class="settings-schema-panel-toggles mt-4">
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
                                    </v-btn-toggle>

                                    <v-row class="w-100 ma-0 mt-2" dense>
                                        <v-col cols="12" v-if="isSchemaPanelActive('works')">
                                            <WorksAndGrades :schema-id="selected_schema_id" />
                                        </v-col>
                                        <v-col cols="12" v-if="isSchemaPanelActive('grading')">
                                            <Grading :schema-id="selected_schema_id" />
                                        </v-col>
                                    </v-row>
                                </template>

                                <v-col v-else-if="!schemas.length" cols="12">
                                    <v-alert type="info" variant="tonal">
                                        Noch kein Benotungsschema vorhanden. Erstellen Sie ein neues Schema, um Arbeiten und Benotung zu konfigurieren.
                                    </v-alert>
                                </v-col>
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
import WorksAndGrades from './components/WorksAndGrades.vue'
import Grading from './components/Grading.vue'
import BasicSettings from './components/BasicSettings.vue'
import Behaviour from './components/Behaviour.vue'
import Notifications from './components/Notifications.vue'
import MyHolidays from './components/MyHolidays.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { WorksAndGrades, Grading, BasicSettings, Behaviour, Notifications, MyHolidays, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.teachingStore = useTeachingStore()
        this.courseStore = useCourseStore()
        await Promise.all([this.teachingStore.loadSettings(), this.courseStore.index()])
        if (this.schemas.length) {
            this.selected_schema_id = this.schemas[0].id
        }
    },

    data() {
        return {
            adminStore: null,
            teachingStore: null,
            courseStore: null,
            selected_schema_id: null,
            active_panel: 'behaviour',
            active_schema_panel: 'works',
            is_renaming: false,
            rename_value: '',
            is_deleting: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        ...mapWritableState(useTeachingStore, ['settings']),
        ...mapWritableState(useCourseStore, ['courses']),
        schemas() {
            return this.settings?.teaching_schemas || []
        },
        selectedSchemaInUse() {
            return (this.courses || []).some((c) => c.teaching_schema_id === this.selected_schema_id)
        },
        selectedSchemaIsStandard() {
            const schema = this.schemas.find((s) => s.id === this.selected_schema_id)
            return schema?.name === 'Standard'
        },
        canManageOwnHolidays() {
            const roles = this.config?.roles || []
            return roles.includes('teacher') || roles.includes('admin') || roles.includes('super_admin')
        },
        showBehaviourEnabled() {
            return this.settings?.teaching_show_behaviour !== false
        },
        availablePanels() {
            const panels = [
                { id: 'basic', label: 'Grundeinstellungen', icon: 'mdi-cog-outline' },
                { id: 'notifications', label: 'Verständigungen', icon: 'mdi-bell-outline' },
                { id: 'schemas', label: 'Benotungsschemas', icon: 'mdi-book-cog-outline' },
            ]

            if (this.showBehaviourEnabled) {
                panels.splice(1, 0, { id: 'behaviour', label: 'Verhalten', icon: 'mdi-account-alert-outline' })
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
    },

    methods: {
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
        isSchemaPanelActive(panel) {
            return this.active_schema_panel === panel
        },
        activateSchemaPanel(panel) {
            this.active_schema_panel = panel
        },
        selectSchema(id) {
            this.selected_schema_id = id
            this.is_renaming = false
            this.is_deleting = false
        },

        async newSchema() {
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
            const schemas = this.schemas.map((s) => (s.id === this.selected_schema_id ? { ...s, name: this.rename_value.trim() } : s))
            await this.teachingStore.saveSettings({ teaching_schemas: schemas })
            this.is_renaming = false
        },

        async deleteSchema() {
            const schemas = this.schemas.filter((s) => s.id !== this.selected_schema_id)
            await this.teachingStore.saveSettings({ teaching_schemas: schemas })
            this.selected_schema_id = schemas.length ? schemas[0].id : null
            this.is_deleting = false
        },
    },
}
</script>

<style scoped>
.teaching-settings-toolbar-secondary {
    width: 100%;
    display: flex;
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
    row-gap: 8px;
}

.teaching-settings-toolbar-btn-secondary {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
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
