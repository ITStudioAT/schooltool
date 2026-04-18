import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { NEW_MENU_LABEL } from '@/pages/admin/restaurant/menuLabels'
import MenuPlansEntry from '@/pages/admin/restaurant/components/MenuPlansEntry.vue'
import { useMenuStore } from '@/stores/admin/restaurant/MenuStore'

const axiosMock = {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
}

vi.stubGlobal('axios', axiosMock)

function mountMenuPlansEntry(
    query: Record<string, string> = {},
    freeDays: { free_date: string }[] = [],
    options: {
        menus?: Array<Record<string, unknown>>
        foods?: Array<Record<string, unknown>>
        eatingTimes?: Array<Record<string, unknown>>
        onlineSettings?: Record<string, unknown>
        routerPushImplementation?: () => Promise<unknown> | unknown
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
                                online_settings: options.onlineSettings || {},
                            },
                        },
                    },
                }),
            ],
            mocks: {
                $route: {
                    query,
                },
                $router: {
                    push: vi.fn(options.routerPushImplementation || (() => Promise.resolve())),
                    replace: vi.fn(() => Promise.resolve()),
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
                'v-select': { template: '<select><slot /></select>' },
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

    it('does not render the print button for saved plans in the editor header', () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'edit',
            plan_id: '17',
            start: '2026-03-23',
            end: '2026-03-27',
        })

        expect(wrapper.find('[data-testid="print-menu-plan-button"]').exists()).toBe(false)
    })

    it('keeps save delete and back together in the header action row', () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'edit',
            plan_id: '17',
            start: '2026-03-23',
            end: '2026-03-27',
        })

        const buttons = wrapper.findAll('.mpe-header__actions button')

        expect(buttons).toHaveLength(3)
        expect(buttons[0].text()).toContain('Speichern')
        expect(buttons[1].text()).toContain('L')
        expect(buttons[2].text()).toContain('Zur\u00fcck')
    })

    it('allows deleting a saved menu plan only when no bookings exist', () => {
        const deletableContext = {
            planId: 3,
            hasPlanBookings: false,
            isDeletingPlan: false,
            isSaving: false,
        }

        const blockedContext = {
            planId: 3,
            hasPlanBookings: true,
            isDeletingPlan: false,
            isSaving: false,
        }

        expect((MenuPlansEntry as any).computed.canDeletePlan.call(deletableContext)).toBe(true)
        expect((MenuPlansEntry as any).computed.canDeletePlan.call(blockedContext)).toBe(false)
        expect((MenuPlansEntry as any).computed.deletePlanHint.call(blockedContext)).toContain('Buchungen')
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

        ctx.dayEntries = (iso: string) => (MenuPlansEntry as any).methods.dayEntries.call(ctx, iso)
        ctx.entryBookedMenuCount = (entry: Record<string, unknown>) => (MenuPlansEntry as any).methods.entryBookedMenuCount.call(ctx, entry)
        ctx.isEntryLocked = (entry: Record<string, unknown>) => (MenuPlansEntry as any).methods.isEntryLocked.call(ctx, entry)
        ctx.dayHasLockedEntries = (iso: string) => (MenuPlansEntry as any).methods.dayHasLockedEntries.call(ctx, iso)

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

    it('does not reorder entries on a day that contains booked menus', () => {
        const ctx = {
            entriesByDate: {
                '2026-03-24': [
                    { _key: 'entry-1', menu: { title: 'Suppe' }, bookedMenuCount: 0 },
                    { _key: 'entry-2', menu: { title: 'Pasta' }, bookedMenuCount: 2 },
                ],
            },
        }

        ctx.dayEntries = (iso: string) => (MenuPlansEntry as any).methods.dayEntries.call(ctx, iso)
        ctx.entryBookedMenuCount = (entry: Record<string, unknown>) => (MenuPlansEntry as any).methods.entryBookedMenuCount.call(ctx, entry)
        ctx.isEntryLocked = (entry: Record<string, unknown>) => (MenuPlansEntry as any).methods.isEntryLocked.call(ctx, entry)
        ctx.dayHasLockedEntries = (iso: string) => (MenuPlansEntry as any).methods.dayHasLockedEntries.call(ctx, iso)

        ;(MenuPlansEntry as any).methods.moveEntry.call(ctx, '2026-03-24', 0, 1)

        expect(ctx.entriesByDate['2026-03-24'].map((entry: { _key: string }) => entry._key)).toEqual([
            'entry-1',
            'entry-2',
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
                    { _key: 'entry-1', menu: { id: 7, title: 'Alt A', price: '9.40' }, price: '9.40' },
                    { _key: 'entry-2', menu: { id: 8, title: 'Alt B' } },
                ],
                '2026-03-25': [
                    { _key: 'entry-3', menu: { id: 7, title: 'Alt A', price: '9.40' }, price: '11.00' },
                ],
            },
        }

        ;(MenuPlansEntry as any).methods.applyEditedMenuToEntries.call(ctx, {
            id: 7,
            title: 'Neu A',
            price: '10.20',
            foods: [],
        })

        expect(ctx.entriesByDate['2026-03-24'][0].menu.title).toBe('Neu A')
        expect(ctx.entriesByDate['2026-03-24'][0].price).toBe('10.20')
        expect(ctx.entriesByDate['2026-03-24'][1].menu.title).toBe('Alt B')
        expect(ctx.entriesByDate['2026-03-25'][0].menu.title).toBe('Neu A')
        expect(ctx.entriesByDate['2026-03-25'][0].price).toBe('11.00')
    })

    it('updates an existing menu instead of creating a new one and refreshes matching entry prices', async () => {
        mountMenuPlansEntry({
            mode: 'edit',
            plan_id: '11',
            start: '2026-04-20',
            end: '2026-04-23',
        })

        const menuStore = useMenuStore()
        menuStore.update.mockResolvedValue({
            id: 7,
            title: 'Neu A',
            price: '10.20',
            foods: [],
        })

        const ctx = {
            editingMenuId: 7,
            createMenuForDate: null,
            createMenuDialog: true,
            isCreateMenuFormValid: false,
            isCreatingMenu: false,
            newMenuForm: {
                title: 'Neu A',
                price: '10,2',
                foodIds: [1, 4],
                courseDraft: {
                    categoryId: null,
                    foodId: null,
                },
            },
            entriesByDate: {
                '2026-04-21': [
                    { _key: 'entry-1', menu: { id: 7, title: 'Alt A', price: '9.40' }, price: '9.40' },
                ],
            },
            $refs: {
                createMenuForm: {
                    validate: vi.fn(async () => {
                        ctx.isCreateMenuFormValid = true
                    }),
                },
            },
        }

        ctx.normalizeNewMenuPriceInput = (value: string) => (MenuPlansEntry as any).methods.normalizeNewMenuPriceInput.call(ctx, value)
        ctx.normalizeNewMenuPricePayload = (value: string) => (MenuPlansEntry as any).methods.normalizeNewMenuPricePayload.call(ctx, value)
        ctx.applyEditedMenuToEntries = (updatedMenu: Record<string, unknown>) => (MenuPlansEntry as any).methods.applyEditedMenuToEntries.call(ctx, updatedMenu)
        ctx.addEntryForDay = vi.fn()
        ctx.closeSearch = vi.fn()
        ctx.closeCreateMenuDialog = () => {
            ctx.createMenuDialog = false
        }

        await (MenuPlansEntry as any).methods.saveNewMenu.call(ctx)

        expect(menuStore.update).toHaveBeenCalledWith(7, {
            title: 'Neu A',
            price: '10.2',
            food_ids: [1, 4],
        })
        expect(menuStore.store).not.toHaveBeenCalled()
        expect(ctx.entriesByDate['2026-04-21'][0].menu.price).toBe('10.20')
        expect(ctx.entriesByDate['2026-04-21'][0].price).toBe('10.20')
        expect(ctx.createMenuDialog).toBe(false)
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

    it('marks booked menu entries as locked and disables edit move and delete actions', async () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'edit',
            plan_id: '17',
            start: '2026-03-23',
            end: '2026-03-27',
        })

        ;(wrapper.vm as any).entriesByDate = {
            '2026-03-24': [
                {
                    id: 17,
                    _key: 'locked-entry',
                    menu: { id: 5, title: 'Pasta', foods: [] },
                    menuTitle: 'Pasta',
                    price: '8.50',
                    comments: '',
                    bookedMenuCount: 2,
                    eatingTimeIds: [],
                    eatingTimes: [],
                },
            ],
        }
        await wrapper.vm.$nextTick()

        expect(wrapper.find('[data-testid="locked-menu-entry-2026-03-24-locked-entry"]').text()).toContain('2 Buchungen')
        expect(wrapper.find('[data-testid="move-entry-up-2026-03-24-locked-entry"]').attributes('disabled')).toBeDefined()
        expect(wrapper.find('[data-testid="move-entry-down-2026-03-24-locked-entry"]').attributes('disabled')).toBeDefined()
        expect(wrapper.find('[data-testid="delete-entry-2026-03-24-locked-entry"]').attributes('disabled')).toBeDefined()
        expect(wrapper.find('[data-testid="edit-entry-2026-03-24-locked-entry"]').attributes('disabled')).toBeDefined()
        expect(dayTile(wrapper, '2026-03-24').text()).toContain('gesperrt')
        expect(dayTile(wrapper, '2026-03-24').text()).toContain('gelöscht')
    })

    it('shows the add-booking action only for entries whose week is not billed', async () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'edit',
            plan_id: '17',
            start: '2026-03-23',
            end: '2026-03-27',
        })

        ;(wrapper.vm as any).entriesByDate = {
            '2026-03-24': [
                {
                    id: 17,
                    _key: 'entry-open',
                    menu: { id: 5, title: 'Pasta', foods: [] },
                    menuTitle: 'Pasta',
                    price: '8.50',
                    comments: '',
                    bookedMenuCount: 0,
                    canManageBookings: true,
                    eatingTimeIds: [],
                    eatingTimes: [],
                },
                {
                    id: 18,
                    _key: 'entry-billed',
                    menu: { id: 6, title: 'Suppe', foods: [] },
                    menuTitle: 'Suppe',
                    price: '5.50',
                    comments: '',
                    bookedMenuCount: 0,
                    canManageBookings: false,
                    eatingTimeIds: [],
                    eatingTimes: [],
                },
            ],
        }
        await wrapper.vm.$nextTick()

        expect(wrapper.find('[data-testid="add-booking-2026-03-24-entry-open"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="add-booking-2026-03-24-entry-billed"]').exists()).toBe(false)
    })

    it('prefills the manual booking dialog with the selected entry title first time and quantity', () => {
        const ctx = {
            planId: 17,
            createBookingDialog: false,
            createBookingDialogEntryId: null,
            createBookingDialogEntryTitle: '',
            createBookingDialogEatingTimes: [],
            createBookingForm: {
                userId: null,
                restaurantEatingTimeId: null,
                quantity: 3,
            },
            createBookingUserSearch: 'alt',
            createBookingSearchResults: [{ id: 9 }],
            createBookingSearchLoading: true,
            createBookingSelectedUser: { id: 9 },
            createBookingSearchTimer: null,
            createBookingSaving: false,
            resetCreateBookingState: () => (MenuPlansEntry as any).methods.resetCreateBookingState.call(ctx),
            entryCanManageBookings: (entry: Record<string, unknown>) => (MenuPlansEntry as any).methods.entryCanManageBookings.call(ctx, entry),
        }

        ;(MenuPlansEntry as any).methods.openCreateBookingDialog.call(ctx, {
            id: 23,
            canManageBookings: true,
            menuTitle: 'Ofenkartoffel',
            menu: { title: 'Ofenkartoffel' },
            eatingTimes: [
                { id: 4, eating_time: '12:30:00' },
                { id: 5, eating_time: '13:25:00' },
            ],
        })

        expect(ctx.createBookingDialog).toBe(true)
        expect(ctx.createBookingDialogEntryId).toBe(23)
        expect(ctx.createBookingDialogEntryTitle).toBe('Ofenkartoffel')
        expect(ctx.createBookingDialogEatingTimes).toHaveLength(2)
        expect(ctx.createBookingForm).toEqual({
            userId: null,
            restaurantEatingTimeId: 4,
            quantity: 1,
        })
        expect(ctx.createBookingUserSearch).toBe('')
        expect(ctx.createBookingSearchResults).toEqual([])
        expect(ctx.createBookingSelectedUser).toBe(null)
    })

    it('disables the editor immediately while the back navigation is pending', async () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'edit',
            plan_id: '17',
            start: '2026-03-23',
            end: '2026-03-27',
            return_to: '/admin/restaurant/menu-plans',
            return_week: '2026-04-13',
        }, [], {
            routerPushImplementation: () => new Promise(() => {}),
        })

        await wrapper.find('[data-testid="menu-plan-back-button"]').trigger('click')
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).isNavigatingBack).toBe(true)
        expect(wrapper.find('[data-testid="menu-plan-back-overlay"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="menu-plan-back-button"]').attributes('disabled')).toBeDefined()
        expect(wrapper.find('[data-testid="print-menu-plan-button"]').exists()).toBe(false)
        expect(wrapper.find('[data-testid="delete-menu-plan-button"]').attributes('disabled')).toBeDefined()
    })

    it('renders the bookings dialog with child and ordering email in one column', async () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'edit',
            plan_id: '17',
            start: '2026-03-23',
            end: '2026-03-27',
        })

        ;(wrapper.vm as any).bookingsDialog = true
        ;(wrapper.vm as any).bookingsDialogLoading = false
        ;(wrapper.vm as any).bookingsDialogEntryTitle = 'Ofenkartoffel'
        ;(wrapper.vm as any).bookingsDialogCanDelete = false
        ;(wrapper.vm as any).bookingsDialogData = [
            {
                id: 1,
                user_name: 'petra.mueller.74@outlook.com',
                user_email: 'petra.mueller.74@outlook.com',
                ordered_for: 'Franziska Müller, 1T',
                eating_time: '13:25',
                quantity: 1,
                booked_at: '16.04.2026 19:39',
            },
            {
                id: 2,
                user_name: 'guenther.kron@cdgym.at',
                user_email: 'guenther.kron@cdgym.at',
                ordered_for: 'guenther.kron@cdgym.at',
                eating_time: '12:30:00',
                quantity: 1,
                booked_at: '16.04.2026 19:39',
            },
        ]
        await wrapper.vm.$nextTick()

        const table = wrapper.get('.mpe-bookings-table')
        const headers = table.findAll('th').map((cell) => cell.text().trim())

        expect(headers).toEqual(['Bestellung', 'Essenszeit', 'Anz.', 'Gebucht am'])
        expect(table.text()).toContain('petra.mueller.74@outlook.com')
        expect(table.text()).toContain('Franziska Müller, 1T')
        expect(table.text()).toContain('guenther.kron@cdgym.at')
        expect(table.text()).toContain('13:25')
        expect(table.text()).toContain('12:30')
        expect(table.text()).not.toContain('12:30:00')
        expect(table.text()).toContain('16.04.2026 19:39')
        expect(table.text()).toContain('Gesamt')
        expect(table.text()).not.toContain('Preis')
        expect(table.findAll('.text-caption.text-grey')).toHaveLength(1)
        expect(wrapper.find('[data-testid="delete-booking-1"]').exists()).toBe(false)
    })

    it('shows booking delete controls only when the week is not billed', async () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'edit',
            plan_id: '17',
            start: '2026-03-23',
            end: '2026-03-27',
        })

        ;(wrapper.vm as any).bookingsDialog = true
        ;(wrapper.vm as any).bookingsDialogLoading = false
        ;(wrapper.vm as any).bookingsDialogCanDelete = true
        ;(wrapper.vm as any).bookingsDialogData = [
            {
                id: 7,
                user_name: 'besteller@example.com',
                user_email: 'besteller@example.com',
                ordered_for: 'Kind Beispiel, 2B',
                eating_time: '12:30',
                quantity: 1,
                booked_at: '16.04.2026 19:39',
            },
        ]
        await wrapper.vm.$nextTick()

        const headers = wrapper.get('.mpe-bookings-table').findAll('th').map((cell) => cell.text().trim())

        expect(headers).toEqual(['Bestellung', 'Essenszeit', 'Anz.', 'Gebucht am', ''])
        expect(wrapper.find('[data-testid="delete-booking-7"]').exists()).toBe(true)
    })

    it('opens the booking delete confirmation only when deletion is allowed', () => {
        const deletableContext = {
            bookingsDialogCanDelete: true,
            deleteBookingDialog: false,
            deleteBookingTargetId: null,
        }

        ;(MenuPlansEntry as any).methods.requestDeleteBooking.call(deletableContext, { id: 11 })

        expect(deletableContext.deleteBookingDialog).toBe(true)
        expect(deletableContext.deleteBookingTargetId).toBe(11)

        const blockedContext = {
            bookingsDialogCanDelete: false,
            deleteBookingDialog: false,
            deleteBookingTargetId: null,
        }

        ;(MenuPlansEntry as any).methods.requestDeleteBooking.call(blockedContext, { id: 12 })

        expect(blockedContext.deleteBookingDialog).toBe(false)
        expect(blockedContext.deleteBookingTargetId).toBe(null)
    })

    it('closes the booking delete confirmation after a successful deletion', async () => {
        axiosMock.delete.mockResolvedValueOnce({})

        const ctx = {
            planId: 17,
            bookingsDialogEntryId: 29,
            deleteBookingTargetId: 7,
            deleteBookingDialog: true,
            isDeletingBooking: false,
            bookingsDialogData: [{ id: 7 }, { id: 8 }],
            loadExistingPlan: vi.fn(() => Promise.resolve()),
            cancelDeleteBooking: () => (MenuPlansEntry as any).methods.cancelDeleteBooking.call(ctx),
        }

        await (MenuPlansEntry as any).methods.confirmDeleteBooking.call(ctx)

        expect(axiosMock.delete).toHaveBeenCalledWith('/api/admin/restaurant/menu-plans/17/entries/29/bookings/7')
        expect(ctx.loadExistingPlan).toHaveBeenCalledWith(17)
        expect(ctx.deleteBookingDialog).toBe(false)
        expect(ctx.deleteBookingTargetId).toBe(null)
        expect(ctx.isDeletingBooking).toBe(false)
        expect(ctx.bookingsDialogData).toEqual([{ id: 8 }])
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

    it('shows delete controls only on the first and last plan day', () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'edit',
            plan_id: '1',
            start: '2026-03-23',
            end: '2026-03-27',
        })

        expect(wrapper.find('[data-testid="delete-boundary-day-2026-03-23"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="delete-boundary-day-2026-03-27"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="delete-boundary-day-2026-03-25"]').exists()).toBe(false)
    })

    it('removes the first plan day from the active range and payload', async () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'edit',
            plan_id: '1',
            start: '2026-03-23',
            end: '2026-03-27',
        })

        ;(wrapper.vm as any).entriesByDate = {
            '2026-03-23': [{ _key: 'entry-1', menu: { id: 1, title: 'A' }, menuTitle: '', price: '', comments: '', eatingTimeIds: [] }],
            '2026-03-24': [{ _key: 'entry-2', menu: { id: 2, title: 'B' }, menuTitle: '', price: '', comments: '', eatingTimeIds: [] }],
        }
        ;(wrapper.vm as any).searchStates = {
            '2026-03-23': { open: true, query: 'alt' },
            '2026-03-24': { open: false, query: '' },
        }

        ;(wrapper.vm as any).requestDeleteBoundaryDay('2026-03-23')
        ;(wrapper.vm as any).confirmDeleteBoundaryDay()
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).planDays.map((day: { iso: string }) => day.iso)).toEqual([
            '2026-03-24',
            '2026-03-25',
            '2026-03-26',
            '2026-03-27',
        ])
        expect((wrapper.vm as any).entriesByDate['2026-03-23']).toBeUndefined()
        expect((wrapper.vm as any).searchStates['2026-03-23']).toBeUndefined()
        expect((MenuPlansEntry as any).methods.buildPayload.call(wrapper.vm)).toMatchObject({
            start_date: '2026-03-24',
            end_date: '2026-03-27',
            entries: [
                expect.objectContaining({
                    plan_date: '2026-03-24',
                    menu_id: 2,
                }),
            ],
        })
    })

    it('removes the last plan day from the active range', () => {
        const ctx = {
            activeRangeBounds: {
                start: '2026-03-23',
                end: '2026-03-27',
            },
            rangeBounds: {
                start: '2026-03-23',
                end: '2026-03-27',
            },
            planDays: [
                { iso: '2026-03-23' },
                { iso: '2026-03-24' },
                { iso: '2026-03-25' },
                { iso: '2026-03-26' },
                { iso: '2026-03-27' },
            ],
            deleteBoundaryDayTargetIso: '2026-03-27',
            deleteBoundaryDayDialog: true,
            entriesByDate: {
                '2026-03-26': [{ _key: 'entry-2', menu: { id: 2, title: 'B' }, menuTitle: '', price: '', comments: '', eatingTimeIds: [] }],
                '2026-03-27': [{ _key: 'entry-3', menu: { id: 3, title: 'C' }, menuTitle: '', price: '', comments: '', eatingTimeIds: [] }],
            },
            searchStates: {
                '2026-03-27': { open: true, query: 'weg' },
            },
        }

        ctx.toDate = (iso: string) => (MenuPlansEntry as any).methods.toDate.call(ctx, iso)
        ctx.addDaysIso = (iso: string, days: number) => (MenuPlansEntry as any).methods.addDaysIso.call(ctx, iso, days)
        ctx.dayEntries = (iso: string) => (MenuPlansEntry as any).methods.dayEntries.call(ctx, iso)
        ctx.entryBookedMenuCount = (entry: Record<string, unknown>) => (MenuPlansEntry as any).methods.entryBookedMenuCount.call(ctx, entry)
        ctx.isEntryLocked = (entry: Record<string, unknown>) => (MenuPlansEntry as any).methods.isEntryLocked.call(ctx, entry)
        ctx.dayHasLockedEntries = (iso: string) => (MenuPlansEntry as any).methods.dayHasLockedEntries.call(ctx, iso)
        ctx.canDeleteBoundaryDay = (iso: string) => (MenuPlansEntry as any).methods.canDeleteBoundaryDay.call(ctx, iso)
        ctx.cancelDeleteBoundaryDay = () => (MenuPlansEntry as any).methods.cancelDeleteBoundaryDay.call(ctx)

        ;(MenuPlansEntry as any).methods.confirmDeleteBoundaryDay.call(ctx)

        expect(ctx.activeRangeBounds).toEqual({
            start: '2026-03-23',
            end: '2026-03-26',
        })
        expect(ctx.entriesByDate['2026-03-27']).toBeUndefined()
        expect(ctx.deleteBoundaryDayDialog).toBe(false)
        expect(ctx.deleteBoundaryDayTargetIso).toBe('')
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
            planScheduleForm: {
                visibilityStartMode: 'when_available',
                visibilityStartWeekOffset: 2,
                visibilityStartDayOfWeek: 0,
                visibilityStartTime: '15:00',
                orderStartMode: 'when_available',
                orderStartWeekOffset: 2,
                orderStartDayOfWeek: 0,
                orderStartTime: '15:00',
                orderEndWeekOffset: 1,
                orderEndDayOfWeek: 5,
                orderEndTime: '17:00',
                visibilityEndMode: 'plan_end',
            },
            rangeBounds: {
                start: '2026-03-23',
                end: '2026-03-27',
            },
            isPlanAvailable: true,
            canToggleAvailability: false,
            menuPlanOnlineSettings: () => ({}),
            scheduledPosition: (weekOffset: number, dayOfWeek: number, timeString: string) => (MenuPlansEntry as any).methods.scheduledPosition.call(ctx, weekOffset, dayOfWeek, timeString),
        }

        ctx.resolvedPlanSchedulePayload = () => (MenuPlansEntry as any).methods.resolvedPlanSchedulePayload.call(ctx)

        expect((MenuPlansEntry as any).methods.buildPayload.call(ctx)).toMatchObject({
            title: 'Testplan',
            start_date: '2026-03-23',
            end_date: '2026-03-27',
            is_available: false,
            visibility_start_mode: 'when_available',
            order_start_mode: 'when_available',
            visibility_end_mode: 'plan_end',
            use_individual_schedule_values: false,
            visible_start_at: null,
            visible_end_at: null,
            order_start_at: null,
            order_end_at: null,
            entries: [],
        })
    })

    it('stores the four individual schedule values in the payload when the switch is enabled', () => {
        const ctx = {
            entriesByDate: {},
            planTitle: 'Testplan',
            planScheduleForm: {
                visibilityStartMode: 'when_available',
                visibilityStartWeekOffset: 2,
                visibilityStartDayOfWeek: 0,
                visibilityStartTime: '15:00',
                orderStartMode: 'when_available',
                orderStartWeekOffset: 2,
                orderStartDayOfWeek: 0,
                orderStartTime: '15:00',
                orderEndWeekOffset: 1,
                orderEndDayOfWeek: 5,
                orderEndTime: '17:00',
                visibilityEndMode: 'plan_end',
            },
            individualScheduleForm: {
                visibleStartAt: '2026-03-20T08:30',
                visibleEndAt: '2026-03-27T23:59',
                orderStartAt: '2026-03-21T09:15',
                orderEndAt: '2026-03-26T17:00',
            },
            rangeBounds: {
                start: '2026-03-23',
                end: '2026-03-27',
            },
            isPlanAvailable: true,
            canToggleAvailability: true,
            useIndividualScheduleValues: true,
            menuPlanOnlineSettings: () => ({}),
            scheduledPosition: (weekOffset: number, dayOfWeek: number, timeString: string) => (MenuPlansEntry as any).methods.scheduledPosition.call(ctx, weekOffset, dayOfWeek, timeString),
        }

        ctx.resolvedPlanSchedulePayload = () => (MenuPlansEntry as any).methods.resolvedPlanSchedulePayload.call(ctx)

        expect((MenuPlansEntry as any).methods.buildPayload.call(ctx)).toMatchObject({
            title: 'Testplan',
            start_date: '2026-03-23',
            end_date: '2026-03-27',
            is_available: true,
            use_individual_schedule_values: true,
            visible_start_at: '2026-03-20T08:30',
            visible_end_at: '2026-03-27T23:59',
            order_start_at: '2026-03-21T09:15',
            order_end_at: '2026-03-26T17:00',
            entries: [],
        })
    })

    it('uses the current online settings for existing plans instead of stored schedule rule snapshots', () => {
        const ctx = {
            menuPlanOnlineSettings: () => ({
                visibility_start_mode: 'scheduled',
                visibility_start_week_offset: 1,
                visibility_start_day_of_week: 1,
                visibility_start_time: '08:30',
                order_start_mode: 'scheduled',
                order_start_week_offset: 1,
                order_start_day_of_week: 2,
                order_start_time: '09:15',
                order_end_week_offset: 0,
                order_end_day_of_week: 4,
                order_end_time: '13:45',
                visibility_end_mode: 'week_end',
            }),
        }

        expect((MenuPlansEntry as any).methods.planScheduleFormFromPlan.call(ctx, {
            start_date: '2026-03-30',
            end_date: '2026-04-02',
            visibility_start_mode: 'when_available',
            visibility_start_week_offset: 2,
            visibility_start_day_of_week: 0,
            visibility_start_time: '15:00',
            order_start_mode: 'when_available',
            order_start_week_offset: 2,
            order_start_day_of_week: 0,
            order_start_time: '15:00',
            order_end_week_offset: 1,
            order_end_day_of_week: 5,
            order_end_time: '17:00',
            visibility_end_mode: 'plan_end',
        })).toEqual({
            visibilityStartMode: 'scheduled',
            visibilityStartWeekOffset: 1,
            visibilityStartDayOfWeek: 1,
            visibilityStartTime: '08:30',
            orderStartMode: 'scheduled',
            orderStartWeekOffset: 1,
            orderStartDayOfWeek: 2,
            orderStartTime: '09:15',
            orderEndWeekOffset: 0,
            orderEndDayOfWeek: 4,
            orderEndTime: '13:45',
            visibilityEndMode: 'week_end',
        })
    })

    it('enables individual schedule editing and prefills the four calculated timestamps', async () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'create',
            start: '2026-03-30',
            end: '2026-04-02',
        }, [], {
            onlineSettings: {
                visibility_start_mode: 'scheduled',
                visibility_start_week_offset: 1,
                visibility_start_day_of_week: 1,
                visibility_start_time: '08:30',
                order_start_mode: 'scheduled',
                order_start_week_offset: 1,
                order_start_day_of_week: 2,
                order_start_time: '09:15',
                order_end_week_offset: 0,
                order_end_day_of_week: 4,
                order_end_time: '13:45',
                visibility_end_mode: 'week_end',
            },
        })

        await wrapper.vm.$nextTick()

        expect(wrapper.find('[data-testid="individual-schedule-visibleStartAt"]').exists()).toBe(false)

        await wrapper.get('[data-testid="individual-schedule-toggle"] input').setValue(true)
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).useIndividualScheduleValues).toBe(true)
        expect((wrapper.vm as any).individualScheduleForm).toEqual({
            visibleStartAt: '2026-03-23T08:30',
            visibleEndAt: '2026-04-05T23:59',
            orderStartAt: '2026-03-24T09:15',
            orderEndAt: '2026-04-02T13:45',
        })
        expect((wrapper.get('[data-testid="individual-schedule-visibleStartAt"]').element as HTMLInputElement).value).toBe('2026-03-23T08:30')
        expect((wrapper.get('[data-testid="individual-schedule-visibleEndAt"]').element as HTMLInputElement).value).toBe('2026-04-05T23:59')
        expect((wrapper.get('[data-testid="individual-schedule-orderStartAt"]').element as HTMLInputElement).value).toBe('2026-03-24T09:15')
        expect((wrapper.get('[data-testid="individual-schedule-orderEndAt"]').element as HTMLInputElement).value).toBe('2026-04-02T13:45')
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
