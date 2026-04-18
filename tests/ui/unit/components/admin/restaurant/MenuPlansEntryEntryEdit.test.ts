import { setActivePinia } from 'pinia'
import { createTestingPinia } from '@pinia/testing'
import { describe, expect, it, vi } from 'vitest'
import MenuPlansEntry from '@/pages/admin/restaurant/components/MenuPlansEntry.vue'
import { useMenuStore } from '@/stores/admin/restaurant/MenuStore'

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
            findEntry: (iso: string, key: string) => (MenuPlansEntry as any).methods.findEntry.call(ctx, iso, key),
            isEntryLocked: () => false,
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

    it('saves the edited menu title and price back to the menu store and keeps plan comments local', async () => {
        setActivePinia(createTestingPinia({
            createSpy: vi.fn,
            initialState: {
                AdminRestaurantMenuStore: {
                    menus: [],
                },
            },
        }))

        const menuStore = useMenuStore()
        menuStore.update.mockResolvedValue({
            id: 7,
            title: 'Neu A Spezial',
            price: '11.5',
            foods: [
                { id: 2, title: 'Suppe', course_number: 1 },
            ],
        })

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
                    {
                        _key: 'entry-1',
                        menu: {
                            id: 7,
                            title: 'Alt A',
                            price: '8.9',
                            foods: [{ id: 2, title: 'Suppe', course_number: 1 }],
                        },
                        menuTitle: 'Alt A',
                        price: '',
                        comments: '',
                    },
                    {
                        _key: 'entry-1b',
                        menu: {
                            id: 7,
                            title: 'Alt A',
                            price: '8.9',
                            foods: [{ id: 2, title: 'Suppe', course_number: 1 }],
                        },
                        menuTitle: '',
                        price: '',
                        comments: '',
                    },
                    { _key: 'entry-2', menu: { id: 8, title: 'Alt B' }, menuTitle: 'Alt B', price: '8.4', comments: '' },
                ],
            },
            closeEditEntryDialog: vi.fn(),
            dayEntries: (iso: string) => (MenuPlansEntry as any).methods.dayEntries.call(ctx, iso),
            sortedFoods: (foods: any[]) => (MenuPlansEntry as any).methods.sortedFoods.call(ctx, foods),
            applyEditedMenuToEntries: (menu: Record<string, unknown>) => (MenuPlansEntry as any).methods.applyEditedMenuToEntries.call(ctx, menu),
            findEntry: (iso: string, key: string) => (MenuPlansEntry as any).methods.findEntry.call(ctx, iso, key),
            normalizeNewMenuPriceInput: (value: string) => (MenuPlansEntry as any).methods.normalizeNewMenuPriceInput.call(ctx, value),
            normalizeNewMenuPricePayload: (value: string) => (MenuPlansEntry as any).methods.normalizeNewMenuPricePayload.call(ctx, value),
            isEntryLocked: () => false,
        }

        await (MenuPlansEntry as any).methods.saveEntryEdit.call(ctx)

        expect(menuStore.update).toHaveBeenCalledWith(7, {
            title: 'Neu A Spezial',
            price: '11.5',
            food_ids: [2],
        })
        expect(ctx.entriesByDate['2026-03-24'][0].menuTitle).toBe('Neu A Spezial')
        expect(ctx.entriesByDate['2026-03-24'][0].price).toBe('11.5')
        expect(ctx.entriesByDate['2026-03-24'][0].comments).toBe('Mit Dessert.')
        expect(ctx.entriesByDate['2026-03-24'][1].menu.title).toBe('Neu A Spezial')
        expect(ctx.entriesByDate['2026-03-24'][1].menuTitle).toBe('')
        expect(ctx.entriesByDate['2026-03-24'][1].price).toBe('11.5')
        expect(ctx.entriesByDate['2026-03-24'][2].price).toBe('8.4')
        expect(ctx.closeEditEntryDialog).toHaveBeenCalledTimes(1)
    })
})
