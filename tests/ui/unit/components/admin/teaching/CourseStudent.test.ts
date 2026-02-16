import { describe, expect, it } from 'vitest'
import CourseStudent from '@/pages/admin/teaching/overview/components/CourseStudent.vue'

describe('CourseStudent date handling', () => {
    it('normalizes timestamp strings using local date parsing (not raw T-split)', () => {
        const methods = (CourseStudent as any).methods
        const ctx = {
            normalizeDateString: methods.normalizeDateString,
            toDateString: methods.toDateString,
        }

        const raw = '2026-02-14T23:00:00.000000Z'
        const normalized = methods.normalizeDateString.call(ctx, raw)
        const expected = methods.toDateString.call(ctx, new Date(raw))

        expect(normalized).toBe(expected)
    })

    it('normalizes ISO date-time strings to stable local input dates', () => {
        const methods = (CourseStudent as any).methods
        const ctx = {
            normalizeDateString: methods.normalizeDateString,
            toDateString: methods.toDateString,
        }

        const inputDate = methods.toInputDate.call(ctx, '2026-02-15T00:00:00.000000Z')

        expect(inputDate).toBeInstanceOf(Date)
        expect(methods.normalizeDateString.call(ctx, inputDate)).toBe('2026-02-15')
    })

    it('fills edit entry date as Date object for v-date-input', () => {
        const methods = (CourseStudent as any).methods
        const ctx: Record<string, unknown> = {
            show_entry_form: false,
            entry_form: null,
            normalizeDateString: methods.normalizeDateString,
            toDateString: methods.toDateString,
            toInputDate: methods.toInputDate,
        }

        methods.editEntry.call(ctx, {
            id: 10,
            type: 'M',
            grade: '2',
            date: '2026-02-15',
            description: 'Test',
        })

        expect((ctx.entry_form as any).date).toBeInstanceOf(Date)
        expect(methods.normalizeDateString.call(ctx, (ctx.entry_form as any).date)).toBe('2026-02-15')
        expect(ctx.show_entry_form).toBe(true)
    })

    it('uses selected lesson date for new entry without day shift', () => {
        const methods = (CourseStudent as any).methods
        const ctx: Record<string, unknown> = {
            selected_courseDate: { date: '2026-02-15' },
            show_entry_form: false,
            entry_form: null,
            normalizeDateString: methods.normalizeDateString,
            toDateString: methods.toDateString,
            toInputDate: methods.toInputDate,
        }
        ctx.emptyEntryForm = function () {
            return methods.emptyEntryForm.call(ctx)
        }

        methods.newEntry.call(ctx)

        expect((ctx.entry_form as any).date).toBeInstanceOf(Date)
        expect(methods.normalizeDateString.call(ctx, (ctx.entry_form as any).date)).toBe('2026-02-15')
        expect(ctx.show_entry_form).toBe(true)
    })

    it('saves created entry with stable YYYY-MM-DD date', async () => {
        const methods = (CourseStudent as any).methods
        let savedPayload: any = null
        const ctx: Record<string, unknown> = {
            selected_course: { id: 77 },
            selected_course_student: { id: 88 },
            entry_form: {
                id: null,
                type: 'M',
                grade: '2',
                date: new Date(2026, 1, 15),
                description: 'Test',
            },
            entryStore: {
                store: async (payload: any) => {
                    savedPayload = payload
                    return true
                },
                update: async () => true,
            },
            normalizeDateString: methods.normalizeDateString,
            toDateString: methods.toDateString,
            loadEntries: async () => true,
            abortEntry: () => true,
        }

        await methods.saveEntry.call(ctx)

        expect(savedPayload).toBeTruthy()
        expect(savedPayload.date).toBe('2026-02-15')
    })
})
