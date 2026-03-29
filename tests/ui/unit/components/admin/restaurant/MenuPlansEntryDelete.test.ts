import { describe, expect, it, vi } from 'vitest'
import MenuPlansEntry from '@/pages/admin/restaurant/components/MenuPlansEntry.vue'

describe('MenuPlans entry item deletion', () => {
    it('opens a persistent delete dialog for the targeted assigned menu entry', () => {
        const ctx = {
            deleteEntryDialog: false,
            deleteEntryTarget: {
                iso: '',
                key: '',
            },
            entriesByDate: {
                '2026-03-24': [
                    {
                        _key: 'entry-1',
                        menu: { id: 17, title: 'Wochenmenü' },
                    },
                ],
            },
            dayEntries: (iso: string) => (MenuPlansEntry as any).methods.dayEntries.call(ctx, iso),
        }

        ;(MenuPlansEntry as any).methods.requestDeleteEntry.call(ctx, '2026-03-24', 'entry-1')

        expect(ctx.deleteEntryTarget).toEqual({
            iso: '2026-03-24',
            key: 'entry-1',
        })
        expect(ctx.deleteEntryDialog).toBe(true)
    })

    it('removes only the targeted assigned menu entry after confirmation', () => {
        const ctx = {
            deleteEntryTarget: {
                iso: '2026-03-24',
                key: 'entry-1',
            },
            entriesByDate: {
                '2026-03-24': [
                    { _key: 'entry-1', menu: { id: 7, title: 'Alt A' } },
                    { _key: 'entry-2', menu: { id: 8, title: 'Alt B' } },
                ],
                '2026-03-25': [
                    { _key: 'entry-3', menu: { id: 9, title: 'Alt C' } },
                ],
            },
            removeEntry: vi.fn(function (iso: string, key: string) {
                return (MenuPlansEntry as any).methods.removeEntry.call(this, iso, key)
            }),
            cancelDeleteEntry: vi.fn(),
        }

        ;(MenuPlansEntry as any).methods.confirmDeleteEntry.call(ctx)

        expect(ctx.removeEntry).toHaveBeenCalledWith('2026-03-24', 'entry-1')
        expect(ctx.entriesByDate['2026-03-24'].map((entry: { _key: string }) => entry._key)).toEqual(['entry-2'])
        expect(ctx.entriesByDate['2026-03-25'].map((entry: { _key: string }) => entry._key)).toEqual(['entry-3'])
        expect(ctx.cancelDeleteEntry).toHaveBeenCalledTimes(1)
    })
})
