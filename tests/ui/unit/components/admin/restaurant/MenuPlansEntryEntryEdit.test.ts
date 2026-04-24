import { setActivePinia } from 'pinia'
import { createTestingPinia } from '@pinia/testing'
import { describe, expect, it, vi } from 'vitest'
import MenuPlansEntry from '@/pages/admin/restaurant/components/MenuPlansEntry.vue'
import { useFoodStore } from '@/stores/admin/restaurant/FoodStore'
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
                foods: [],
                isFoodSearchOpen: false,
                foodSearch: '',
                pendingFoodId: null,
                selectedFoodId: null,
                isEditing: true,
            },
            entriesByDate: {
                '2026-03-24': [
                    {
                        _key: 'entry-1',
                        menu: { id: 17, title: 'Wochenmenü' },
                        foods: null,
                        hasFoodsSnapshot: false,
                        menuTitle: 'Wochenmenue Spezial',
                        price: '9.5',
                        comments: 'Bitte ohne Nuesse.',
                    },
                ],
            },
            dayEntries: (iso: string) => (MenuPlansEntry as any).methods.dayEntries.call(ctx, iso),
            findEntry: (iso: string, key: string) => (MenuPlansEntry as any).methods.findEntry.call(ctx, iso, key),
            sortedFoods: (foods: any[]) => (MenuPlansEntry as any).methods.sortedFoods.call(ctx, foods),
            entryFoods: (entry: any) => (MenuPlansEntry as any).methods.entryFoods.call(ctx, entry),
            cloneFoodForEntryEdit: (food: any, index: number) => (MenuPlansEntry as any).methods.cloneFoodForEntryEdit.call(ctx, food, index),
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
        expect(ctx.entryEditForm.foods).toEqual([])
        expect(ctx.entryEditForm.selectedFoodId).toBeNull()
        expect(ctx.entryEditForm.isEditing).toBe(true)
        expect(ctx.entryEditDialog).toBe(true)
    })

    it('saves edited foods only on the menu plan snapshot', async () => {
        setActivePinia(createTestingPinia({
            createSpy: vi.fn,
            initialState: {
                AdminRestaurantMenuStore: {
                    menus: [],
                },
            },
        }))

        const menuStore = useMenuStore()

        const ctx = {
            entryEditTarget: {
                iso: '2026-03-24',
                key: 'entry-1',
            },
            entryEditForm: {
                menuTitle: 'Neu A Spezial',
                price: '11,5',
                foods: [{ id: 3, title: 'Salat Spezial', description: 'Frisch', price: '2,5', course_number: 1 }],
                isFoodSearchOpen: false,
                foodSearch: '',
                pendingFoodId: null,
                selectedFoodId: 3,
                isEditing: true,
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
            newMenuFoodOptions: [
                { id: 2, title: 'Suppe', course_number: 1 },
                { id: 3, title: 'Salat', course_number: 1 },
            ],
            dayEntries: (iso: string) => (MenuPlansEntry as any).methods.dayEntries.call(ctx, iso),
            sortedFoods: (foods: any[]) => (MenuPlansEntry as any).methods.sortedFoods.call(ctx, foods),
            entryFoods: (entry: any) => (MenuPlansEntry as any).methods.entryFoods.call(ctx, entry),
            applyEditedMenuToEntries: (menu: Record<string, unknown>) => (MenuPlansEntry as any).methods.applyEditedMenuToEntries.call(ctx, menu),
            findEntry: (iso: string, key: string) => (MenuPlansEntry as any).methods.findEntry.call(ctx, iso, key),
            normalizeNewMenuPriceInput: (value: string) => (MenuPlansEntry as any).methods.normalizeNewMenuPriceInput.call(ctx, value),
            normalizeNewMenuPricePayload: (value: string) => (MenuPlansEntry as any).methods.normalizeNewMenuPricePayload.call(ctx, value),
            isEntryLocked: () => false,
        }

        await (MenuPlansEntry as any).methods.saveEntryEdit.call(ctx, 'plan')

        expect(menuStore.update).not.toHaveBeenCalled()
        expect(ctx.entriesByDate['2026-03-24'][0].menuTitle).toBe('Neu A Spezial')
        expect(ctx.entriesByDate['2026-03-24'][0].price).toBe('11.5')
        expect(ctx.entriesByDate['2026-03-24'][0].comments).toBe('')
        expect(ctx.entriesByDate['2026-03-24'][0].foods.map((food: any) => food.id)).toEqual([3])
        expect(ctx.entriesByDate['2026-03-24'][0].foods[0].title).toBe('Salat Spezial')
        expect(ctx.entriesByDate['2026-03-24'][0].foods[0].price).toBe('2.5')
        expect(ctx.entriesByDate['2026-03-24'][0].hasFoodsSnapshot).toBe(true)
        expect(ctx.closeEditEntryDialog).toHaveBeenCalledTimes(1)
    })

    it('saves edited foods forever by updating base foods and the base menu', async () => {
        setActivePinia(createTestingPinia({
            createSpy: vi.fn,
            initialState: {
                AdminRestaurantMenuStore: {
                    menus: [],
                },
                AdminRestaurantFoodStore: {
                    foods: [],
                },
            },
        }))

        const menuStore = useMenuStore()
        const foodStore = useFoodStore()

        foodStore.update.mockResolvedValue({ id: 3, title: 'Salat Spezial' })
        menuStore.update.mockResolvedValue({
            id: 7,
            title: 'Neu A Spezial',
            price: '11.5',
            foods: [
                { id: 3, title: 'Salat Spezial', course_number: 1 },
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
                foods: [{ id: 3, title: 'Salat Spezial', description: 'Frisch', price: '2,5', course_number: 1, allergens: ['M'], category: { id: 4, title: 'Salat' }, ingredient_icons: [{ id: 9, title: 'Vegan' }] }],
                isFoodSearchOpen: false,
                foodSearch: '',
                pendingFoodId: null,
                selectedFoodId: 3,
                isEditing: true,
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
                ],
            },
            closeEditEntryDialog: vi.fn(),
            dayEntries: (iso: string) => (MenuPlansEntry as any).methods.dayEntries.call(ctx, iso),
            sortedFoods: (foods: any[]) => (MenuPlansEntry as any).methods.sortedFoods.call(ctx, foods),
            entryFoods: (entry: any) => (MenuPlansEntry as any).methods.entryFoods.call(ctx, entry),
            entryEditFoodPayload: (food: any) => (MenuPlansEntry as any).methods.entryEditFoodPayload.call(ctx, food),
            applyEditedMenuToEntries: (menu: Record<string, unknown>) => (MenuPlansEntry as any).methods.applyEditedMenuToEntries.call(ctx, menu),
            findEntry: (iso: string, key: string) => (MenuPlansEntry as any).methods.findEntry.call(ctx, iso, key),
            normalizeNewMenuPriceInput: (value: string) => (MenuPlansEntry as any).methods.normalizeNewMenuPriceInput.call(ctx, value),
            normalizeNewMenuPricePayload: (value: string) => (MenuPlansEntry as any).methods.normalizeNewMenuPricePayload.call(ctx, value),
            isEntryLocked: () => false,
        }

        await (MenuPlansEntry as any).methods.saveEntryEdit.call(ctx, 'base')

        expect(foodStore.update).toHaveBeenCalledWith(3, {
            title: 'Salat Spezial',
            description: 'Frisch',
            category_id: 4,
            allergens: ['M'],
            ingredient_icon_ids: [9],
            price: '2.5',
        })
        expect(menuStore.update).toHaveBeenCalledWith(7, {
            title: 'Neu A Spezial',
            price: '11.5',
            food_ids: [3],
        })
        expect(ctx.entriesByDate['2026-03-24'][0].hasFoodsSnapshot).toBe(false)
        expect(ctx.entriesByDate['2026-03-24'][0].foods).toBeNull()
    })

    it('selects a searched food first and only adds it after clicking Hinzufügen', () => {
        const ctx = {
            entryEditForm: {
                foods: [{ id: 2, title: 'Suppe', course_number: 1 }],
                isFoodSearchOpen: false,
                foodSearch: 'Sal',
                pendingFoodId: null,
                selectedFoodId: 2,
                isEditing: true,
            },
            newMenuFoodOptions: [
                { id: 2, title: 'Suppe', course_number: 1 },
                { id: 3, title: 'Salat', course_number: 1 },
            ],
            filteredEntryEditFoodOptions: [],
            pendingEntryEditFood: null,
            cloneFoodForEntryEdit: (food: any, index: number) => (MenuPlansEntry as any).methods.cloneFoodForEntryEdit.call(ctx, food, index),
            selectEntryEditFood: (foodId: number) => (MenuPlansEntry as any).methods.selectEntryEditFood.call(ctx, foodId),
            addEntryEditFood: (food: any) => (MenuPlansEntry as any).methods.addEntryEditFood.call(ctx, food),
            closeEntryEditFoodSearch: () => (MenuPlansEntry as any).methods.closeEntryEditFoodSearch.call(ctx),
        }

        Object.defineProperty(ctx, 'filteredEntryEditFoodOptions', {
            get: () => (MenuPlansEntry as any).computed.filteredEntryEditFoodOptions.call(ctx),
        })
        Object.defineProperty(ctx, 'pendingEntryEditFood', {
            get: () => (MenuPlansEntry as any).computed.pendingEntryEditFood.call(ctx),
        })

        ;(MenuPlansEntry as any).methods.openEntryEditFoodSearch.call(ctx)
        expect(ctx.entryEditForm.isFoodSearchOpen).toBe(true)

        ;(MenuPlansEntry as any).methods.selectPendingEntryEditFood.call(ctx, 3)

        expect(ctx.entryEditForm.pendingFoodId).toBe(3)
        expect(ctx.entryEditForm.foods.map((food: any) => food.id)).toEqual([2])

        ;(MenuPlansEntry as any).methods.addPendingEntryEditFood.call(ctx)

        expect(ctx.entryEditForm.foods.map((food: any) => food.id)).toEqual([2, 3])
        expect(ctx.entryEditForm.selectedFoodId).toBe(3)
        expect(ctx.entryEditForm.pendingFoodId).toBeNull()
        expect(ctx.entryEditForm.isFoodSearchOpen).toBe(false)
        expect(ctx.entryEditForm.foodSearch).toBe('')
    })
})
