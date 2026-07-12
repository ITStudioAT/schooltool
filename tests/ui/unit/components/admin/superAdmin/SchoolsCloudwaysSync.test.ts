import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import Schools from '@/pages/admin/superAdmin/components/Schools.vue'

describe('Schools Cloudways synchronization', () => {
    it('only resolves one selected school', () => {
        const selectedSchool = (Schools as any).computed.selectedSchool

        expect(selectedSchool.call({ selected_schools: [], schools: [] })).toBeNull()
        expect(selectedSchool.call({
            selected_schools: [2],
            schools: [{ id: 1, long_name: 'One' }, { id: 2, long_name: 'Two' }],
        })).toEqual({ id: 2, long_name: 'Two' })
    })

    it('requires the exact school name as confirmation', () => {
        const matches = (Schools as any).computed.cloudwaysSyncConfirmationMatches

        expect(matches.call({ cloudways_sync_confirmation: 'School A', selectedSchool: { long_name: 'School A' } })).toBe(true)
        expect(matches.call({ cloudways_sync_confirmation: 'school a', selectedSchool: { long_name: 'School A' } })).toBe(false)
    })

    it('opens the persistent dialog only after a successful preview', async () => {
        const previewCloudwaysSchoolSynchronization = vi.fn().mockResolvedValue({ rows: 20, tables: 4 })
        const context: any = {
            selectedSchool: { id: 7, long_name: 'School A' },
            schoolStore: { previewCloudwaysSchoolSynchronization },
            cloudways_sync_confirmation: 'old',
            cloudways_sync_preview: null,
            cloudways_sync_result: { rows: 1 },
            cloudways_sync_dialog: false,
        }

        await (Schools as any).methods.openCloudwaysSyncDialog.call(context)

        expect(previewCloudwaysSchoolSynchronization).toHaveBeenCalledWith(7)
        expect(context.cloudways_sync_dialog).toBe(true)
        expect(context.cloudways_sync_confirmation).toBe('')
        expect(context.cloudways_sync_preview).toEqual({ rows: 20, tables: 4 })
        expect(context.cloudways_sync_result).toBeNull()
    })

    it('keeps the dialog locked while synchronization runs and reports success', async () => {
        const synchronizeCloudwaysSchool = vi.fn().mockResolvedValue({ rows: 120, tables: 8 })
        const index = vi.fn().mockResolvedValue(true)
        const context: any = {
            selectedSchool: { id: 7, long_name: 'School A' },
            cloudwaysSyncConfirmationMatches: true,
            cloudways_sync_confirmation: 'School A',
            cloudways_sync_result: null,
            is_synchronizing: false,
            schoolStore: { synchronizeCloudwaysSchool, index },
        }

        await (Schools as any).methods.synchronizeCloudwaysSchool.call(context)

        expect(synchronizeCloudwaysSchool).toHaveBeenCalledWith(7, 'School A')
        expect(index).toHaveBeenCalledOnce()
        expect(context.cloudways_sync_result).toEqual({ rows: 120, tables: 8 })
        expect(context.is_synchronizing).toBe(false)
    })

    it('uses a persistent dialog and warns that files are not downloaded', () => {
        const source = readFileSync('resources/js/pages/admin/superAdmin/components/Schools.vue', 'utf8')

        expect(source).toContain('v-model="cloudways_sync_dialog" persistent')
        expect(source).toContain('Dateien, Anhänge, Bilder, Logos, PDFs und Importdateien')
        expect(source).toContain('Lokale Schuldaten ersetzen')
    })
})
