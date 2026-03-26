import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { NEW_MENU_LABEL } from '@/pages/admin/restaurant/menuLabels'
import MenuPlansEntry from '@/pages/admin/restaurant/components/MenuPlansEntry.vue'

function mountMenuPlansEntry(
    query: Record<string, string> = {},
    freeDays: { free_date: string }[] = [],
    options: {
        menus?: Array<Record<string, unknown>>
        foods?: Array<Record<string, unknown>>
        eatingTimes?: Array<Record<string, unknown>>
    } = {},
) {
    return mount(MenuPlansEntry, {
        global: {
            plugins: [
                createTestingPinia({
                    createSpy: vi.fn,
                    initialState: {
                        AdminAdminStore: {
                            config: {
                                selected_school: {
                                    long_name: 'Testschule',
                                },
                            },
                        },
                        AdminRestaurantFreeDayStore: {
                            freeDays,
                            pendingSetDates: [],
                            pendingUnsetDates: [],
                            isLoaded: freeDays.length > 0,
                        },
                        AdminRestaurantMenuStore: {
                            menus: options.menus || [],
                        },
                        AdminRestaurantFoodStore: {
                            foods: options.foods || [],
                        },
                        AdminRestaurantEatingTimeStore: {
                            eatingTimes: options.eatingTimes || [],
                            isLoaded: true,
                        },
                        AdminRestaurantStore: {
                            settings: {
                                categories: [],
                                allergen_options: [],
                            },
                        },
                    },
                }),
            ],
            mocks: {
                $route: {
                    query,
                },
            },
            stubs: {
                'v-container': { template: '<div><slot /></div>' },
                'v-row': { template: '<div><slot /></div>' },
                'v-col': { template: '<div><slot /></div>' },
                'v-sheet': { template: '<div><slot /></div>' },
                'v-btn': {
                    template: '<button v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
                },
                'v-icon': { template: '<i><slot /></i>' },
                'v-text-field': { template: '<input />' },
                'v-dialog': { template: '<div><slot /></div>' },
                'v-card': { template: '<div><slot /></div>' },
                'v-card-title': { template: '<div><slot /></div>' },
                'v-card-text': { template: '<div><slot /></div>' },
                'v-card-actions': { template: '<div><slot /></div>' },
                'v-form': { template: '<form><slot /></form>' },
                'v-spacer': { template: '<div />' },
                'v-textarea': { template: '<textarea />' },
                'v-chip': { template: '<div><slot /></div>' },
                'v-alert': { template: '<div><slot /></div>' },
                'v-avatar': { template: '<div><slot /></div>' },
                'v-img': { template: '<img />' },
                AdminSectionHero: { template: '<header />' },
            },
        },
    })
}

function dayTile(wrapper: ReturnType<typeof mountMenuPlansEntry>, iso: string) {
    return wrapper.get(`[data-testid="plan-day-${iso}"]`)
}

function dayAddButtons(wrapper: ReturnType<typeof mountMenuPlansEntry>, iso: string) {
    return dayTile(wrapper, iso).findAll('button').filter((button) => button.text().includes('Men'))
}

describe('MenuPlans entry page', () => {
    it('uses the shared new-menu label for menu creation', () => {
        expect((MenuPlansEntry as any).data().newMenuLabel).toBe(NEW_MENU_LABEL)
    })

    it('uses Menüplan as the edit headline and exposes a print href for saved plans', () => {
        const ctx = {
            entryMode: 'edit',
            planId: 17,
        }

        expect((MenuPlansEntry as any).computed.entryHeadline.call(ctx)).toBe('Menüplan')
        expect((MenuPlansEntry as any).computed.printHref.call(ctx)).toBe('/api/admin/restaurant/menu-plans/17/print')
    })

    it('extracts a file name from the content-disposition header for pdf downloads', () => {
        const fileName = (MenuPlansEntry as any).methods.fileNameFromContentDisposition(
            'attachment; filename="menu-plan-test.pdf"',
        )

        expect(fileName).toBe('menu-plan-test.pdf')
    })

    it('renders the print button for saved plans', () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'edit',
            plan_id: '17',
            start: '2026-03-23',
            end: '2026-03-27',
        })

        expect(wrapper.find('[data-testid="print-menu-plan-button"]').exists()).toBe(true)
    })

    it('keeps save print and back together in the header action row', () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'edit',
            plan_id: '17',
            start: '2026-03-23',
            end: '2026-03-27',
        })

        const buttons = wrapper.findAll('.mpe-header__actions button')

        expect(buttons).toHaveLength(3)
        expect(buttons[0].text()).toContain('Speichern')
        expect(buttons[2].text()).toContain('Zur\u00fcck')
    })

    it('builds a new menu draft course by course with category first and food second', () => {
        const ctx = {
            newMenuFoodSearch: '',
            newMenuForm: {
                foodIds: [],
                courseDraft: {
                    categoryId: null,
                    foodId: null,
                },
            },
        }

        ctx.resetNewMenuCourseDraft = () => (MenuPlansEntry as any).methods.resetNewMenuCourseDraft.call(ctx)
        ctx.isNewMenuDraftCategorySelected = (categoryId: number) => (MenuPlansEntry as any).methods.isNewMenuDraftCategorySelected.call(ctx, categoryId)
        ctx.isNewMenuDraftFoodSelected = (foodId: number) => (MenuPlansEntry as any).methods.isNewMenuDraftFoodSelected.call(ctx, foodId)
        ctx.isNewMenuFoodAlreadyAssigned = (foodId: number) => (MenuPlansEntry as any).methods.isNewMenuFoodAlreadyAssigned.call(ctx, foodId)

        ;(MenuPlansEntry as any).methods.selectNewMenuDraftCategory.call(ctx, 2)
        expect(ctx.newMenuForm.courseDraft.categoryId).toBe(2)
        expect(ctx.newMenuForm.courseDraft.foodId).toBe(null)

        ;(MenuPlansEntry as any).methods.selectNewMenuDraftFood.call(ctx, 7)
        expect(ctx.newMenuForm.courseDraft.foodId).toBe(7)

        ctx.canAddNewMenuDraftCourse = true
        ;(MenuPlansEntry as any).methods.appendNewMenuDraftCourse.call(ctx)
        expect(ctx.newMenuForm.foodIds).toEqual([7])
        expect(ctx.newMenuForm.courseDraft).toEqual({ categoryId: null, foodId: null })
    })

    it('filters foods for the selected new-menu category by the dialog search field', () => {
        const ctx = {
            newMenuFoodSearch: 'sup',
            newMenuFoodOptions: [
                { id: 1, title: 'Suppe', category: { id: 1 } },
                { id: 2, title: 'Tomatensuppe', category: { id: 1 } },
                { id: 3, title: 'Pasta', category: { id: 2 } },
            ],
            newMenuForm: {
                courseDraft: {
                    categoryId: 1,
                },
            },
        }

        const availableFoodsForNewMenu = (MenuPlansEntry as any).computed.availableFoodsForNewMenu.call(ctx)
        const filteredFoodsForNewMenu = (MenuPlansEntry as any).computed.filteredFoodsForNewMenu.call({
            ...ctx,
            availableFoodsForNewMenu,
        })

        expect(availableFoodsForNewMenu.map((food: { id: number }) => food.id)).toEqual([1, 2])
        expect(filteredFoodsForNewMenu.map((food: { id: number }) => food.id)).toEqual([1, 2])
    })

    it('moves menus within the same day up and down', () => {
        const ctx = {
            entriesByDate: {
                '2026-03-24': [
                    { _key: 'entry-1', menu: { title: 'Suppe' } },
                    { _key: 'entry-2', menu: { title: 'Pasta' } },
                    { _key: 'entry-3', menu: { title: 'Dessert' } },
                ],
            },
        }

        ;(MenuPlansEntry as any).methods.moveEntry.call(ctx, '2026-03-24', 0, 1)
        expect(ctx.entriesByDate['2026-03-24'].map((entry: { _key: string }) => entry._key)).toEqual([
            'entry-2',
            'entry-1',
            'entry-3',
        ])

        ;(MenuPlansEntry as any).methods.moveEntry.call(ctx, '2026-03-24', 2, -1)
        expect(ctx.entriesByDate['2026-03-24'].map((entry: { _key: string }) => entry._key)).toEqual([
            'entry-2',
            'entry-3',
            'entry-1',
        ])
    })

    it('opens edit mode for an assigned menu with the dialog prefilled', () => {
        const ctx = {
            createMenuForDate: '2026-03-24',
            editingMenuId: null,
            newMenuFoodSearch: 'old',
            isCreateMenuFormValid: true,
            createMenuDialog: false,
            sortedFoods: (foods: any[]) => (MenuPlansEntry as any).methods.sortedFoods.call(ctx, foods),
            normalizeNewMenuPriceInput: (value: string) => (MenuPlansEntry as any).methods.normalizeNewMenuPriceInput.call(ctx, value),
        }

        ;(MenuPlansEntry as any).methods.openEditMenuDialog.call(ctx, {
            id: 17,
            title: 'Wochenmenü',
            price: '9.5',
            foods: [
                { id: 4, title: 'Dessert', course_number: 2 },
                { id: 1, title: 'Suppe', course_number: 1 },
            ],
        })

        expect(ctx.createMenuForDate).toBe(null)
        expect(ctx.editingMenuId).toBe(17)
        expect(ctx.newMenuForm.title).toBe('Wochenmenü')
        expect(ctx.newMenuForm.foodIds).toEqual([1, 4])
        expect(ctx.newMenuForm.price).toBe('9,5')
        expect(ctx.newMenuForm.courseDraft).toEqual({ categoryId: null, foodId: null })
        expect(ctx.newMenuFoodSearch).toBe('')
        expect(ctx.isCreateMenuFormValid).toBe(false)
        expect(ctx.createMenuDialog).toBe(true)
    })

    it('opens a preview dialog for an assigned menu', () => {
        const ctx = {
            entriesByDate: {
                '2026-03-24': [
                    {
                        _key: 'entry-1',
                        menu: {
                            id: 17,
                            title: 'Wochenmenue',
                            foods: [{ id: 1, title: 'Suppe', course_number: 1 }],
                        },
                    },
                ],
            },
            entryPreviewDialog: false,
            entryPreviewTarget: {
                iso: '',
                key: '',
            },
            dayEntries: (iso: string) => (MenuPlansEntry as any).methods.dayEntries.call(ctx, iso),
        }

        ;(MenuPlansEntry as any).methods.openEntryPreviewDialog.call(ctx, '2026-03-24', 'entry-1')

        expect(ctx.entryPreviewDialog).toBe(true)
        expect(ctx.entryPreviewTarget).toEqual({
            iso: '2026-03-24',
            key: 'entry-1',
        })
        expect((MenuPlansEntry as any).computed.entryPreviewEntry.call(ctx)?.menu?.title).toBe('Wochenmenue')
    })

    it('closes the preview dialog and clears the selected entry', () => {
        const ctx = {
            entryPreviewDialog: true,
            entryPreviewTarget: {
                iso: '2026-03-24',
                key: 'entry-1',
            },
        }

        ;(MenuPlansEntry as any).methods.closeEntryPreviewDialog.call(ctx)

        expect(ctx.entryPreviewDialog).toBe(false)
        expect(ctx.entryPreviewTarget).toEqual({
            iso: '',
            key: '',
        })
    })

    it('applies an edited menu to all matching entries in the plan', () => {
        const ctx = {
            entriesByDate: {
                '2026-03-24': [
                    { _key: 'entry-1', menu: { id: 7, title: 'Alt A' } },
                    { _key: 'entry-2', menu: { id: 8, title: 'Alt B' } },
                ],
                '2026-03-25': [
                    { _key: 'entry-3', menu: { id: 7, title: 'Alt A' } },
                ],
            },
        }

        ;(MenuPlansEntry as any).methods.applyEditedMenuToEntries.call(ctx, {
            id: 7,
            title: 'Neu A',
            foods: [],
        })

        expect(ctx.entriesByDate['2026-03-24'][0].menu.title).toBe('Neu A')
        expect(ctx.entriesByDate['2026-03-24'][1].menu.title).toBe('Alt B')
        expect(ctx.entriesByDate['2026-03-25'][0].menu.title).toBe('Neu A')
    })

    it('formats allergen labels from restaurant settings and falls back for unknown values', () => {
        const ctx = {
            allergenOptions: [
                { character: 'A', short_description: 'Gluten' },
            ],
        }

        expect((MenuPlansEntry as any).methods.allergenLabel.call(ctx, 'a')).toBe('A - Gluten')
        expect((MenuPlansEntry as any).methods.allergenLabel.call(ctx, 'X')).toBe('X')
    })

    it('builds the restaurant back target with remembered week', () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'edit',
            return_to: '/admin/restaurant/menu-plans',
            return_week: '2026-04-13',
        })

        expect((wrapper.vm as any).backTarget).toEqual({
            path: '/admin/restaurant/menu-plans',
            query: {
                week: '2026-04-13',
            },
        })
    })

    it('renders each day in the requested range and keeps open days ready for menu assignment', () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'create',
            start: '2026-03-23',
            end: '2026-03-27',
        })

        expect((wrapper.vm as any).planDays.map((day: { iso: string }) => day.iso)).toEqual([
            '2026-03-23',
            '2026-03-24',
            '2026-03-25',
            '2026-03-26',
            '2026-03-27',
        ])

        expect(dayAddButtons(wrapper, '2026-03-24')).toHaveLength(1)
        expect(dayAddButtons(wrapper, '2026-03-24')[0].text()).toContain('Men')
        expect(dayAddButtons(wrapper, '2026-03-26')).toHaveLength(1)
        expect(wrapper.get('[data-testid="plan-progress-badge"]').text()).toContain('0%')
    })

    it('adds a menu to a day after opening the search panel', async () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'create',
            start: '2026-03-23',
            end: '2026-03-27',
        }, [], {
            menus: [
                {
                    id: 9,
                    title: 'Gemuese Curry',
                    price: '8.50',
                    foods: [],
                },
            ],
        })

        await dayAddButtons(wrapper, '2026-03-24')[0].trigger('click')
        expect(dayTile(wrapper, '2026-03-24').text()).toContain(NEW_MENU_LABEL)
        expect(dayTile(wrapper, '2026-03-24').text()).toContain('Gemuese Curry')

        await dayTile(wrapper, '2026-03-24').find('.mpe-search-result').trigger('click')
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).dayEntries('2026-03-24')).toHaveLength(1)
        expect(dayTile(wrapper, '2026-03-24').text()).toContain('Gemuese Curry')
        expect((wrapper.vm as any).filledDayCount).toBe(1)
    })

    it('stores availability as false in the payload while open days still exist', () => {
        const ctx = {
            entriesByDate: {},
            planTitle: 'Testplan',
            rangeBounds: {
                start: '2026-03-23',
                end: '2026-03-27',
            },
            isPlanAvailable: true,
            canToggleAvailability: false,
        }

        expect((MenuPlansEntry as any).methods.buildPayload.call(ctx)).toMatchObject({
            title: 'Testplan',
            start_date: '2026-03-23',
            end_date: '2026-03-27',
            is_available: false,
            entries: [],
        })
    })

    it('shows active eating times from stored plan data even before the global list is loaded', async () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'create',
            start: '2026-03-23',
            end: '2026-03-23',
        })

        ;(wrapper.vm as any).entriesByDate = {
            '2026-03-23': [
                {
                    _key: 'entry-1',
                    menu: {
                        id: 7,
                        title: 'Wochenmenue',
                        foods: [],
                    },
                    menuTitle: 'Wochenmenue',
                    price: '8.50',
                    comments: '',
                    eatingTimeIds: [1, 2],
                    eatingTimes: [
                        { id: 1, eating_time: '12:25:00' },
                        { id: 2, eating_time: '13:20:00' },
                    ],
                },
            ],
        }
        await wrapper.vm.$nextTick()

        expect(wrapper.get('[data-testid="entry-active-times-2026-03-23-entry-1"]').text()).toContain('12:25 Uhr')
        expect(wrapper.get('[data-testid="entry-active-times-2026-03-23-entry-1"]').text()).toContain('13:20 Uhr')
    })

    describe('free day handling', () => {
        it('marks a free day tile with the free-day class', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-25' }],
            )

            const tile = wrapper.get('[data-testid="plan-day-2026-03-25"]')
            expect(tile.classes()).toContain('mpe-day--free')
        })

        it('does not mark non-free days as free', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-25' }],
            )

            const tile = wrapper.get('[data-testid="plan-day-2026-03-23"]')
            expect(tile.classes()).not.toContain('mpe-day--free')
        })

        it('shows the free-day card for a free day and hides the add-menu button', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-25' }],
            )

            expect(dayTile(wrapper, '2026-03-25').find('.mpe-free-card').exists()).toBe(true)
            expect(dayTile(wrapper, '2026-03-25').text()).toContain('Freier Tag')
            expect(dayAddButtons(wrapper, '2026-03-25')).toHaveLength(0)
        })

        it('does not render inline search controls on a free day', async () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-24' }],
            )

            await wrapper.vm.$nextTick()

            expect(dayTile(wrapper, '2026-03-24').find('.mpe-search-panel').exists()).toBe(false)
            expect(dayAddButtons(wrapper, '2026-03-24')).toHaveLength(0)
        })

        it('still allows menu assignment on non-free days', async () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-24' }],
            )

            ;(wrapper.vm as any).addEntryForDay('2026-03-23', {
                id: 4,
                title: 'Gemuese Curry',
                price: '8.50',
            })
            await wrapper.vm.$nextTick()

            expect((wrapper.vm as any).dayEntries('2026-03-23')).toHaveLength(1)
            expect(dayTile(wrapper, '2026-03-23').text()).toContain('Gemuese Curry')
        })

        it('excludes free days from the open day count', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-25' }],
            )

            // 5 days total, 1 free → 4 assignable, 0 menus → openDayCount = 4
            expect((wrapper.vm as any).openDayCount).toBe(4)
        })

        it('excludes free days from the coverage percentage calculation', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-25' }],
            )

            ;(wrapper.vm as any).entriesByDate = {
                '2026-03-23': [{ _key: 'entry-1', menu: { id: 1, title: 'A' } }],
                '2026-03-25': [{ _key: 'entry-2', menu: { id: 2, title: 'B' } }],
                '2026-03-27': [{ _key: 'entry-3', menu: { id: 3, title: 'C' } }],
            }

            expect((wrapper.vm as any).coveragePercent).toBe(50)
        })

        it('ignores menus on free days when counting filled days', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-23' }],
            )

            // 2026-03-23 is free → first open day should be 2026-03-24
            ;(wrapper.vm as any).entriesByDate = {
                '2026-03-23': [{ _key: 'entry-1', menu: { id: 1, title: 'A' } }],
                '2026-03-24': [{ _key: 'entry-2', menu: { id: 2, title: 'B' } }],
            }

            expect((wrapper.vm as any).filledDayCount).toBe(1)
            expect((wrapper.vm as any).openDayCount).toBe(3)
        })

        it('counts free days in freeDayCount', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-24' }, { free_date: '2026-03-26' }],
            )

            expect((wrapper.vm as any).freeDayCount).toBe(2)
        })

        it('returns zero freeDayCount when no free days exist in range', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
            )

            expect((wrapper.vm as any).freeDayCount).toBe(0)
        })
    })
})
