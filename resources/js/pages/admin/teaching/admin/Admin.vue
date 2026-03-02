<template>
    <v-col cols="12" class="pb-1">
        <section class="teaching-admin-toolbar">
            <v-btn-toggle
                v-model="panelSelection"
                mandatory
                class="teaching-admin-panel-switcher"
                color="primary"
                divided>
                <v-btn
                    v-for="panel in availablePanels"
                    :key="panel.id"
                    class="teaching-admin-toolbar-btn"
                    :value="panel.id"
                    :prepend-icon="panel.icon">
                    {{ panel.label }}
                </v-btn>
            </v-btn-toggle>
        </section>
    </v-col>

    <v-col cols="12" md="6" lg="7" xl="4">
        <section class="teaching-admin-content-shell">
            <v-row class="w-100 ma-0" dense>
                <Import116 v-if="isPanelActive('import')" />
                <Holidays v-if="canManageSchoolHolidays && isPanelActive('holidays')" />
                <SchoolHours v-if="canManageSchoolHolidays && isPanelActive('school_hours')" />
                <v-col cols="12" v-if="visiblePanelsCount === 0">
                    <div class="teaching-admin-empty">
                        Kein Bereich aktiv. Aktivieren Sie oben mindestens einen Bereich.
                    </div>
                </v-col>
            </v-row>
        </section>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import Import116 from './import116/Import116.vue'
import Holidays from './holidays/Holidays.vue'
import SchoolHours from './schoolhours/SchoolHours.vue'

export default {
    components: { Import116, Holidays, SchoolHours },

    data() {
        return {
            active_panel: 'import',
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        canManageSchoolHolidays() {
            const roles = this.config?.roles || []
            return roles.includes('admin') || roles.includes('super_admin') || roles.includes('teaching_admin')
        },
        availablePanels() {
            const panels = [{ id: 'import', label: 'Import 116', icon: 'mdi-import' }]
            if (this.canManageSchoolHolidays) {
                panels.push({ id: 'holidays', label: 'Ferien', icon: 'mdi-beach' })
                panels.push({ id: 'school_hours', label: 'Schulstunden', icon: 'mdi-clock-time-four-outline' })
            }
            return panels
        },
        panelSelection: {
            get() {
                const panelExists = this.availablePanels.some((panel) => panel.id === this.active_panel)
                if (!panelExists) {
                    return this.availablePanels[0]?.id || null
                }
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
        visiblePanelsCount() {
            if (this.active_panel === 'import') {
                return 1
            }
            if (this.active_panel === 'holidays' && this.canManageSchoolHolidays) {
                return 1
            }
            if (this.active_panel === 'school_hours' && this.canManageSchoolHolidays) {
                return 1
            }
            return 0
        },
    },

    watch: {
        canManageSchoolHolidays(newValue) {
            if (!newValue && (this.active_panel === 'holidays' || this.active_panel === 'school_hours')) {
                this.active_panel = 'import'
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
    },
}
</script>

<style scoped>
.teaching-admin-toolbar {
    width: 100%;
    display: flex;
    justify-content: flex-start;
    gap: 8px;
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
}

.teaching-admin-panel-switcher {
    width: 100%;
    flex-wrap: wrap;
    row-gap: 8px;
}

.teaching-admin-toolbar-btn {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
}

.teaching-admin-content-shell {
    border-radius: 16px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.66);
    padding: 8px;
}

.teaching-admin-empty {
    border-radius: 14px;
    border: 1px dashed rgba(16, 38, 58, 0.14);
    background: rgba(255, 255, 255, 0.72);
    color: rgba(16, 38, 58, 0.9);
    padding: 14px;
    font-size: 0.88rem;
}

</style>
