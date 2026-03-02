import { describe, expect, it, vi } from 'vitest'
import SchoolHours from '@/pages/admin/teaching/admin/schoolhours/SchoolHours.vue'

describe('Teaching school hours page', () => {
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
            schoolHourStore: {
                store,
                index,
            },
            show_create_form: true,
            resetCreateForm,
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
})
