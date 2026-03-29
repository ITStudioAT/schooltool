import { describe, expect, it, vi } from 'vitest'
import MenuPlansEntry from '@/pages/admin/restaurant/components/MenuPlansEntry.vue'

describe('MenuPlans entry item editing', () => {
    it('opens entry edit mode with the current menu title, price, and comments', () => {
        const ctx = {
            entryEditDialog: false,
            entryEditTarget: {
                iso: '',
                key: '',
            },
            entryEditForm: {
                menuTitle: '',
                price: '',
                comments: '',
            },
            entriesByDate: {
                '2026-03-24': [
                    {
                        _key: 'entry-1',
                        menu: { id: 17, title: 'Wochenmenü' },
                        menuTitle: 'Wochenmenue Spezial',
                        price: '9.5',
                        comments: 'Bitte ohne Nuesse.',
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
        expect(ctx.entryEditForm.menuTitle).toBe('Wochenmenue Spezial')
        expect(ctx.entryEditForm.price).toBe('9,5')
        expect(ctx.entryEditForm.comments).toBe('Bitte ohne Nuesse.')
        expect(ctx.entryEditDialog).toBe(true)
    })

    it('saves the edited menu title, price, and comments back to the targeted menu-plan entry', () => {
        const ctx = {
            entryEditTarget: {
                iso: '2026-03-24',
                key: 'entry-1',
            },
            entryEditForm: {
                menuTitle: 'Neu A Spezial',
                price: '11,5',
                comments: 'Mit Dessert.',
            },
            entriesByDate: {
                '2026-03-24': [
                    { _key: 'entry-1', menu: { id: 7, title: 'Alt A' }, menuTitle: 'Alt A', price: '', comments: '' },
                    { _key: 'entry-2', menu: { id: 8, title: 'Alt B' }, menuTitle: 'Alt B', price: '8.4', comments: '' },
                ],
            },
            closeEditEntryDialog: vi.fn(),
            normalizeNewMenuPricePayload: (value: string) => (MenuPlansEntry as any).methods.normalizeNewMenuPricePayload.call(ctx, value),
        }

        ;(MenuPlansEntry as any).methods.saveEntryEdit.call(ctx)

        expect(ctx.entriesByDate['2026-03-24'][0].menuTitle).toBe('Neu A Spezial')
        expect(ctx.entriesByDate['2026-03-24'][0].price).toBe('11.5')
        expect(ctx.entriesByDate['2026-03-24'][0].comments).toBe('Mit Dessert.')
        expect(ctx.entriesByDate['2026-03-24'][1].price).toBe('8.4')
        expect(ctx.closeEditEntryDialog).toHaveBeenCalledTimes(1)
    })
})
