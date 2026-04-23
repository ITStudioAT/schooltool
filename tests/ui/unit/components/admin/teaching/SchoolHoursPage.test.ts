import { describe, expect, it, vi } from 'vitest'
import SchoolHours from '@/pages/admin/teaching/admin/schoolhours/SchoolHours.vue'

describe('Teaching school hours page', () => {
    it('shows the active schoolyear label', () => {
        const ctx = {
            config: {
                selected_schoolyear: {
                    name: 'Schuljahr 2026/27',
                },
            },
        }

        expect((SchoolHours as any).computed.activeSchoolyearLabel.call(ctx)).toBe('Schuljahr 2026/27')
    })

    it('validates create entries before enabling submit', () => {
        const methods = (SchoolHours as any).methods
        const computed = (SchoolHours as any).computed

        const invalidCtx = {
            create_entries: [{ hour: 1, from: '08:00', until: '' }],
            isCreateEntryValid: methods.isCreateEntryValid,
        }

        expect(computed.isCreateFormValid.call(invalidCtx)).toBe(false)

        const validCtx = {
            create_entries: [{ hour: 1, from: '08:00', until: '08:50' }],
            isCreateEntryValid: methods.isCreateEntryValid,
        }

        expect(computed.isCreateFormValid.call(validCtx)).toBe(true)
    })

    it('resets create form to exactly one entry with next suggested hour', () => {
        const methods = (SchoolHours as any).methods
        const ctx: Record<string, unknown> = {
            school_hours: [],
            create_entries: [
                { hour: 1, from: '08:00', until: '08:50' },
                { hour: 2, from: '08:55', until: '09:45' },
            ],
            emptyCreateEntry: methods.emptyCreateEntry,
            nextSuggestedHour: methods.nextSuggestedHour,
        }

        methods.resetCreateForm.call(ctx)

        expect(ctx.create_entries).toHaveLength(1)
        expect(ctx.create_entries[0]).toEqual({ hour: 3, from: '', until: '' })
    })

    it('adds create entries with suggested hour numbers up to max 10', () => {
        const methods = (SchoolHours as any).methods
        const ctx: Record<string, unknown> = {
            school_hours: [],
            create_entries: [{ hour: 1, from: '', until: '' }],
            emptyCreateEntry: methods.emptyCreateEntry,
            nextSuggestedHour: methods.nextSuggestedHour,
        }

        for (let i = 0; i < 20; i++) {
            methods.addCreateEntry.call(ctx)
        }

        expect(ctx.create_entries).toHaveLength(10)
        expect(ctx.create_entries.map((entry: { hour: number }) => entry.hour)).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
    })

    it('submits batch payload and resets when store succeeds', async () => {
        const methods = (SchoolHours as any).methods
        const index = vi.fn().mockResolvedValue(true)
        const store = vi.fn().mockResolvedValue([{ id: 1 }, { id: 2 }])
        const resetCreateForm = vi.fn()

        const ctx: Record<string, unknown> = {
            create_entries: [
                { hour: 1, from: '08:00', until: '08:50' },
                { hour: 2, from: '08:55', until: '09:45' },
            ],
            isCreateFormValid: true,
            school_hours_save_action: null,
            $nextTick: async () => {},
            schoolHourStore: {
                store,
                index,
            },
            show_create_form: true,
            resetCreateForm,
            runSchoolHourMutation(action: string, callback: () => Promise<unknown>) {
                return methods.runSchoolHourMutation.call(this, action, callback)
            },
        }

        await methods.createSchoolHour.call(ctx)

        expect(store).toHaveBeenCalledWith({
            entries: [
                { hour: 1, from: '08:00', until: '08:50' },
                { hour: 2, from: '08:55', until: '09:45' },
            ],
        })
        expect(index).toHaveBeenCalledTimes(1)
        expect(ctx.show_create_form).toBe(false)
        expect(resetCreateForm).toHaveBeenCalledTimes(1)
    })

    it('confirms deletion in a persistent dialog before removing a school hour', async () => {
        const methods = (SchoolHours as any).methods
        const destroy = vi.fn().mockResolvedValue(true)
        const index = vi.fn().mockResolvedValue(true)

        const ctx: Record<string, unknown> = {
            delete_dialog_open: false,
            delete_id: null,
            editing_id: 4,
            edit_data: { hour: 4, from: '10:00', until: '10:50' },
            school_hours_save_action: null,
            $nextTick: async () => {},
            schoolHourStore: {
                destroy,
                index,
            },
            runSchoolHourMutation(action: string, callback: () => Promise<unknown>) {
                return methods.runSchoolHourMutation.call(this, action, callback)
            },
        }

        methods.promptDeleteSchoolHour.call(ctx, 4)

        expect(ctx.delete_dialog_open).toBe(true)
        expect(ctx.delete_id).toBe(4)

        await methods.confirmDeleteSchoolHour.call(ctx)

        expect(destroy).toHaveBeenCalledWith(4)
        expect(index).toHaveBeenCalledTimes(1)
        expect(ctx.editing_id).toBeNull()
        expect(ctx.edit_data).toEqual({ hour: null, from: '', until: '' })
        expect(ctx.delete_dialog_open).toBe(false)
        expect(ctx.delete_id).toBeNull()
    })
})
