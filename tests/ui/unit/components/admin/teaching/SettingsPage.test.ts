import { describe, expect, it, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import Settings from '@/pages/admin/teaching/settings/Settings.vue'

describe('Teaching settings page', () => {
    it('computes own-holidays permission from roles', () => {
        const ctx = {
            config: {
                roles: ['teacher'],
            },
        }

        expect((Settings as any).computed.canManageOwnHolidays.call(ctx)).toBe(true)
    })

    it('builds available panels based on role permissions', () => {
        const ctx = {
            canManageOwnHolidays: true,
            showBehaviourEnabled: true,
            usesLegacyTeachingSettings: true,
        }
        const panelsWithPermission = (Settings as any).computed.availablePanels.call(ctx)

        expect(panelsWithPermission.map((panel: { id: string }) => panel.id)).toEqual([
            'basic',
            'entries',
            'behaviour',
            'notifications',
            'schemas',
            'my_holidays',
            'backup',
        ])
    })

    it('removes behaviour panel when show-behaviour setting is disabled', () => {
        const ctx = {
            canManageOwnHolidays: true,
            showBehaviourEnabled: false,
            usesLegacyTeachingSettings: true,
        }
        const panels = (Settings as any).computed.availablePanels.call(ctx)

        expect(panels.map((panel: { id: string }) => panel.id)).toEqual([
            'basic',
            'entries',
            'notifications',
            'schemas',
            'my_holidays',
            'backup',
        ])
    })

    it('shows the active schoolyear label for the toolbar buttons', () => {
        const ctx = {
            config: {
                selected_schoolyear: {
                    name: 'Schuljahr 2025/26',
                    concerns: '2025/26',
                },
            },
        }

        expect((Settings as any).computed.activeSchoolyearLabel.call(ctx)).toBe('Schuljahr 2025/26')
    })

    it('removes legacy settings panels from schoolyear 2026/27 onward', () => {
        const methods = (Settings as any).methods
        const schoolyearContext = {
            activeSchoolyearConcern: '2026/27',
            normalizeSchoolyearConcern: methods.normalizeSchoolyearConcern,
            parseSchoolyearConcern: methods.parseSchoolyearConcern,
        }
        const usesLegacyTeachingSettings = (Settings as any).computed.usesLegacyTeachingSettings.call(schoolyearContext)
        const panels = (Settings as any).computed.availablePanels.call({
            canManageOwnHolidays: true,
            showBehaviourEnabled: true,
            usesLegacyTeachingSettings,
        })

        expect(usesLegacyTeachingSettings).toBe(false)
        expect(panels.map((panel: { id: string }) => panel.id)).toEqual([
            'basic',
            'entries',
            'my_holidays',
            'backup',
        ])
    })

    it('keeps legacy settings panels through schoolyear 2025/26', () => {
        const methods = (Settings as any).methods
        const schoolyearContext = {
            activeSchoolyearConcern: '2025/26',
            normalizeSchoolyearConcern: methods.normalizeSchoolyearConcern,
            parseSchoolyearConcern: methods.parseSchoolyearConcern,
        }

        expect((Settings as any).computed.usesLegacyTeachingSettings.call(schoolyearContext)).toBe(true)
    })

    it('falls back to entries when a legacy settings panel is unavailable', () => {
        const context = {
            active_panel: 'schemas',
            availablePanels: [
                { id: 'basic' },
                { id: 'entries' },
            ],
        }

        ;(Settings as any).methods.ensureActivePanelAvailable.call(context)

        expect(context.active_panel).toBe('entries')
    })

    it('activates only allowed primary panels', () => {
        const ctx = {
            active_panel: 'behaviour',
            canManageOwnHolidays: false,
            availablePanels: [
                { id: 'basic' },
                { id: 'behaviour' },
                { id: 'entries' },
                { id: 'notifications' },
                { id: 'schemas' },
            ],
        }

        ;(Settings as any).methods.activatePanel.call(ctx, 'schemas')
        expect(ctx.active_panel).toBe('schemas')

        ;(Settings as any).methods.activatePanel.call(ctx, 'my_holidays')
        expect(ctx.active_panel).toBe('schemas')
    })

    it('mirrored submenu delegates selection to primary panel activation', () => {
        const methods = (Settings as any).methods
        const ctx = {
            active_panel: 'behaviour',
            availablePanels: [
                { id: 'basic' },
                { id: 'behaviour' },
                { id: 'entries' },
                { id: 'notifications' },
                { id: 'schemas' },
            ],
            activatePanel: methods.activatePanel,
        }

        ;(Settings as any).computed.mirroredPanelSelection.set.call(ctx, 'schemas')
        expect(ctx.active_panel).toBe('schemas')

        ;(Settings as any).computed.mirroredPanelSelection.set.call(ctx, 'my_holidays')
        expect(ctx.active_panel).toBe('schemas')
    })

    it('switches schema sub-panels in exclusive mode', () => {
        const ctx = {
            active_schema_panel: 'works',
        }

        ;(Settings as any).methods.activateSchemaPanel.call(ctx, 'grading')
        expect((Settings as any).methods.isSchemaPanelActive.call(ctx, 'grading')).toBe(true)
        expect((Settings as any).methods.isSchemaPanelActive.call(ctx, 'works')).toBe(false)
    })

    it('supports the category evaluation schema sub-panel', () => {
        const ctx = {
            active_schema_panel: 'works',
        }

        ;(Settings as any).methods.activateSchemaPanel.call(ctx, 'category_evaluation')

        expect((Settings as any).methods.isSchemaPanelActive.call(ctx, 'category_evaluation')).toBe(true)
        expect((Settings as any).methods.isSchemaPanelActive.call(ctx, 'grading')).toBe(false)
    })

    it('falls back to behaviour when own-holidays permission is removed', () => {
        const ctx = {
            active_panel: 'my_holidays',
            showBehaviourEnabled: true,
        }

        ;(Settings as any).watch.canManageOwnHolidays.call(ctx, false)

        expect(ctx.active_panel).toBe('behaviour')
    })

    it('falls back to basic when own-holidays permission is removed and behaviour is disabled', () => {
        const ctx = {
            active_panel: 'my_holidays',
            showBehaviourEnabled: false,
        }

        ;(Settings as any).watch.canManageOwnHolidays.call(ctx, false)

        expect(ctx.active_panel).toBe('basic')
    })

    it('newSchema clones standard schema and selects the newly created schema id', async () => {
        const randomId = 'schema-new-123'
        const randomUuidSpy = vi.spyOn(globalThis.crypto, 'randomUUID').mockReturnValue(randomId)

        const standardWorks = [{ short_name: 'MA', name: 'Mitarbeit' }]
        const standardGrading = { semester_count: 2, categories: [{ name: 'Mitarbeit', weight: 100 }] }
        const saveSettingsMock = vi.fn().mockResolvedValue(true)

        const ctx = {
            schemas: [
                {
                    id: 'schema-standard',
                    name: 'Standard',
                    works: standardWorks,
                    grading: standardGrading,
                },
            ],
            teachingStore: {
                saveSettings: saveSettingsMock,
            },
            selected_schema_id: 'schema-standard',
            schema_settings_saving_action: null,
            $nextTick: async () => {},
            runSchemaSettingsMutation(action: string, callback: () => Promise<unknown>) {
                return (Settings as any).methods.runSchemaSettingsMutation.call(this, action, callback)
            },
        }

        await (Settings as any).methods.newSchema.call(ctx)

        expect(saveSettingsMock).toHaveBeenCalledTimes(1)
        const payload = saveSettingsMock.mock.calls[0][0]
        expect(payload.teaching_schemas).toHaveLength(2)
        expect(payload.teaching_schemas[1]).toMatchObject({
            id: randomId,
            name: 'Neues Schema',
        })
        expect(ctx.selected_schema_id).toBe(randomId)

        standardWorks[0].name = 'Mutated'
        standardGrading.categories[0].name = 'Mutated'
        expect(payload.teaching_schemas[1].works[0].name).toBe('Mitarbeit')
        expect(payload.teaching_schemas[1].grading.categories[0].name).toBe('Mitarbeit')

        randomUuidSpy.mockRestore()
    })

    it('builds schema usage warning counters for the import dialog', () => {
        const ctx = {
            courses: [
                { teaching_schema_id: 'schema-1' },
                { teaching_schema_id: 'schema-2' },
                { teaching_schema_id: 'schema-1' },
            ],
            selected_schema_id: 'schema-1',
            selectedSchema: {
                works: [{ short_name: 'MA' }, { short_name: 'T' }],
                grading: {
                    categories: [{ name: 'Mitarbeit' }],
                },
            },
        }

        ctx.selectedSchemaCourseUsageCount = (Settings as any).computed.selectedSchemaCourseUsageCount.call(ctx)
        ctx.selectedSchemaWorkCount = (Settings as any).computed.selectedSchemaWorkCount.call(ctx)
        ctx.selectedSchemaCategoryCount = (Settings as any).computed.selectedSchemaCategoryCount.call(ctx)

        expect(ctx.selectedSchemaCourseUsageCount).toBe(2)
        expect(ctx.selectedSchemaWorkCount).toBe(2)
        expect(ctx.selectedSchemaCategoryCount).toBe(1)
        expect((Settings as any).computed.selectedSchemaUsageItems.call(ctx)).toEqual([
            { count: 2, label: 'Kurse' },
            { count: 2, label: 'Arbeiten' },
            { count: 1, label: 'Kategorien' },
        ])

        ctx.selectedSchemaUsageItems = (Settings as any).computed.selectedSchemaUsageItems.call(ctx)
        expect((Settings as any).computed.selectedSchemaUsageWarningVisible.call(ctx)).toBe(true)
    })

    it('derives the previous schoolyear import label from concerns', () => {
        const methods = (Settings as any).methods
        const ctx = {
            config: {
                selected_schoolyear: {
                    concerns: '2025/26',
                },
            },
            schoolyears: [
                { id: 1, concerns: '2024/25' },
                { id: 2, concerns: '2025/26' },
            ],
            normalizeSchoolyearConcern: methods.normalizeSchoolyearConcern,
            parseSchoolyearConcern: methods.parseSchoolyearConcern,
        }

        ctx.activeSchoolyearConcern = (Settings as any).computed.activeSchoolyearConcern.call(ctx)
        ctx.previousSchoolyearConcern = (Settings as any).computed.previousSchoolyearConcern.call(ctx)
        ctx.previousSchoolyear = (Settings as any).computed.previousSchoolyear.call(ctx)

        expect(ctx.activeSchoolyearConcern).toBe('2025/26')
        expect(ctx.previousSchoolyearConcern).toBe('2024/25')
        expect(ctx.previousSchoolyear).toEqual({ id: 1, concerns: '2024/25' })
        expect((Settings as any).computed.schoolyearImportLabel.call(ctx)).toBe('Import vom Schuljahr: 2024/25')
    })

    it('shows that import is not possible when no previous schoolyear exists', () => {
        const ctx = {
            previousSchoolyear: null,
        }

        expect((Settings as any).computed.schoolyearImportLabel.call(ctx)).toBe('Import nicht möglich!')
    })

    it('imports the selected schema and closes the dialog on success', async () => {
        const methods = (Settings as any).methods
        const ctx: Record<string, unknown> = {
            selected_schema_id: 'schema-1',
            schema_import_loading: false,
            schema_panel_revision: 0,
            teachingStore: {
                importSchema: async (selectedSchemaId: string) => selectedSchemaId === 'schema-1',
            },
            refreshSelectedSchemaPanels: () => {
                ctx.schema_panel_revision = Number(ctx.schema_panel_revision) + 1
            },
            closeSchemaImportDialog: () => {
                ctx.schema_import_dialog_open = false
            },
            schema_import_dialog_open: true,
        }

        await methods.importSchema.call(ctx)

        expect(ctx.schema_import_loading).toBe(false)
        expect(ctx.schema_panel_revision).toBe(1)
        expect(ctx.schema_import_dialog_open).toBe(false)
    })

    it('resets the selected schema and closes the dialog on success', async () => {
        const methods = (Settings as any).methods
        const ctx: Record<string, unknown> = {
            selected_schema_id: 'schema-1',
            schema_reset_loading: false,
            schema_panel_revision: 0,
            teachingStore: {
                resetSchema: async (selectedSchemaId: string) => selectedSchemaId === 'schema-1',
            },
            refreshSelectedSchemaPanels: () => {
                ctx.schema_panel_revision = Number(ctx.schema_panel_revision) + 1
            },
            closeSchemaResetDialog: () => {
                ctx.schema_reset_dialog_open = false
            },
            schema_reset_dialog_open: true,
        }

        await methods.resetSchema.call(ctx)

        expect(ctx.schema_reset_loading).toBe(false)
        expect(ctx.schema_panel_revision).toBe(1)
        expect(ctx.schema_reset_dialog_open).toBe(false)
    })

    it('adds schema import and reset buttons in the Benotungsschemas header with persistent dialogs', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/Settings.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<template #header-actions>')
        expect(source).toContain('import Entries from \'./components/Entries.vue\'')
        expect(source).toContain('<v-window-item value="entries">')
        expect(source).toContain('<Entries :key="config?.selected_schoolyear?.id || \'no-schoolyear\'" />')
        expect(source).toContain("{ id: 'entries', label: 'Einträge', icon: 'mdi-format-list-bulleted-type' }")
        expect(source).toContain('activeSchoolyearLabel() {')
        expect(source).toContain('<span class="teaching-settings-toolbar-btn-copy">')
        expect(source).toContain("panel.id === 'backup' ? 'Alle Schuljahre' : activeSchoolyearLabel")
        expect(source).toContain('background: rgba(15, 23, 42, 0.35);')
        expect(source).toContain('border: 1px solid rgba(255, 255, 255, 0.18);')
        expect(source).toContain('prepend-icon="mdi-restore"')
        expect(source).toContain('@click="openSchemaResetDialog"')
        expect(source).toContain('prepend-icon="mdi-import"')
        expect(source).toContain('@click="openSchemaImportDialog"')
        expect(source).toContain('<v-dialog v-model="schema_reset_dialog_open" persistent max-width="560">')
        expect(source).toContain('Benotungsschema zurücksetzen')
        expect(source).toContain(":key=\"`reset-${item.label}`\"")
        expect(source).toContain('<strong>Wenn Sie das Benotungsschema resetten, werden alle Schüler:innen-Benotungen gelöscht!</strong>')
        expect(source).toContain(':loading="schema_reset_loading"')
        expect(source).toContain('@click="resetSchema"')
        expect(source).toContain('<v-dialog v-model="schema_import_dialog_open" persistent max-width="560">')
        expect(source).toContain('Benotungsschema importieren')
        expect(source).toContain('v-if="selectedSchemaUsageWarningVisible"')
        expect(source).toContain('Dieses Benotungsschema wird bereits verwendet.')
        expect(source).toContain('{{ item.count }} {{ item.label }}')
        expect(source).toContain('<strong>Wenn Sie ein Benotungsschema importieren, werden alle bisherigen Benotungen gelöscht!</strong>')
        expect(source).toContain('<strong>{{ schoolyearImportLabel }}</strong>')
        expect(source).toContain(':loading="schema_import_loading"')
        expect(source).toContain('@click="importSchema"')
        expect(source).toContain('Importieren')
        expect(source).toContain('return `Import vom Schuljahr: ${this.previousSchoolyear.concerns}`')
        expect(source).toContain("return 'Import nicht möglich!'")
        expect(source).toContain(':key="`works-${selected_schema_id}-${schema_panel_revision}`"')
        expect(source).toContain(':key="`grading-${selected_schema_id}-${schema_panel_revision}`"')
        expect(source).toContain(':key="`category-evaluation-${selected_schema_id}-${schema_panel_revision}`"')
        expect(source).toContain("label: 'Kurse'")
        expect(source).toContain("label: 'Arbeiten'")
        expect(source).toContain("label: 'Kategorien'")
        expect(source).toContain('openSchemaImportDialog() {')
        expect(source).toContain('closeSchemaImportDialog() {')
        expect(source).toContain('openSchemaResetDialog() {')
        expect(source).toContain('closeSchemaResetDialog() {')
        expect(source).toContain('async importSchema() {')
        expect(source).toContain('async resetSchema() {')
        expect(source).toContain('refreshSelectedSchemaPanels() {')
        expect(source).toContain('this.schema_panel_revision += 1')
        expect(source).toContain('schema_import_dialog_open: false')
        expect(source).toContain('schema_reset_dialog_open: false')
        expect(source).toContain('schema_import_loading: false')
        expect(source).toContain('schema_reset_loading: false')
        expect(source).toContain('schema_panel_revision: 0')
    })
})
