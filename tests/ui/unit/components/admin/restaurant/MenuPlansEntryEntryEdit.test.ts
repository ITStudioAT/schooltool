import { describe, expect, it, vi } from 'vitest'
import MenuPlansEntry from '@/pages/admin/restaurant/components/MenuPlansEntry.vue'

describe('MenuPlans entry item editing', () => {
    it('opens entry edit mode with the current overridden price', () => {
        const ctx = {
            entryEditDialog: false,
            entryEditTarget: {
                iso: '',
                key: '',
            },
            entryEditForm: {
                priceOverride: '',
            },
            entriesByDate: {
                '2026-03-24': [
                    {
                        _key: 'entry-1',
                        menu: { id: 17, title: 'Wochenmenü' },
                        priceOverride: '9.5',
                    },
                ],
            },
            dayEntries: (iso: string) => (MenuPlansEntry as any).methods.dayEntries.call(ctx, iso),
            normalizeNewMenuPriceInput: (value: string) => (MenuPlansEntry as any).methods.normalizeNewMenuPriceInput.call(ctx, value),
        }

        ;(MenuPlansEntry as any).methods.openEditEntryDialog.call(ctx, '2026-03-24', 'entry-1')

        expect(ctx.entryEditTarget).toEqual({
            iso: '2026-03-24',
            key: 'entry-1',
        })
        expect(ctx.entryEditForm.priceOverride).toBe('9,5')
        expect(ctx.entryEditDialog).toBe(true)
    })

    it('saves the edited overridden price back to the targeted menu-plan entry', () => {
        const ctx = {
            entryEditTarget: {
                iso: '2026-03-24',
                key: 'entry-1',
            },
            entryEditForm: {
                priceOverride: '11,5',
            },
            entriesByDate: {
                '2026-03-24': [
                    { _key: 'entry-1', menu: { id: 7, title: 'Alt A' }, priceOverride: '' },
                    { _key: 'entry-2', menu: { id: 8, title: 'Alt B' }, priceOverride: '8.4' },
                ],
            },
            closeEditEntryDialog: vi.fn(),
            normalizeNewMenuPricePayload: (value: string) => (MenuPlansEntry as any).methods.normalizeNewMenuPricePayload.call(ctx, value),
        }

        ;(MenuPlansEntry as any).methods.saveEntryEdit.call(ctx)

        expect(ctx.entriesByDate['2026-03-24'][0].priceOverride).toBe('11.5')
        expect(ctx.entriesByDate['2026-03-24'][1].priceOverride).toBe('8.4')
        expect(ctx.closeEditEntryDialog).toHaveBeenCalledTimes(1)
    })
})
