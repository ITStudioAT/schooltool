import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import Menus from '@/pages/admin/restaurant/components/Menus.vue'
import { useFoodStore } from '@/stores/admin/restaurant/FoodStore'
import { useMenuStore } from '@/stores/admin/restaurant/MenuStore'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'

function mountMenus(options: {
    menus?: any[]
    foods?: any[]
    categories?: any[]
    userSettings?: Record<string, unknown>
    canManageUserSettings?: boolean
} = {}) {
    const pinia = createTestingPinia({
        stubActions: false,
        createSpy: vi.fn,
        initialState: {
            AdminRestaurantMenuStore: {
                menus: options.menus ?? [],
            },
            AdminRestaurantFoodStore: {
                foods: options.foods ?? [],
            },
            AdminRestaurantStore: {
                settings: {
                    categories: options.categories ?? [
                        { id: 1, title: 'Vorspeise' },
                        { id: 2, title: 'Hauptspeise' },
                    ],
                    ingredient_icons: [],
                    allergen_options: [],
                    allergen_suggestions: [],
                    user_settings: options.userSettings ?? { restaurant_foods_pagination_number: 12 },
                    can_manage_user_settings: options.canManageUserSettings ?? true,
                    stats: {},
                },
            },
        },
    })

    const wrapper = mount(Menus, {
        global: {
            plugins: [pinia],
            stubs: {
                ItsGridBox: { template: '<div><slot name="header-actions" /><slot /></div>' },
                'v-col': { template: '<div><slot /></div>' },
                'v-row': { template: '<div><slot /></div>' },
                'v-alert': { template: '<div><slot /></div>' },
                'v-card': { template: '<div><slot /></div>' },
                'v-card-text': { template: '<div><slot /></div>' },
                'v-card-actions': { template: '<div><slot /></div>' },
                'v-card-title': { template: '<div><slot /></div>' },
                'v-btn': { template: '<button v-bind="$attrs"><slot /></button>' },
                'v-dialog': { template: '<div><slot /></div>' },
                'v-form': { template: '<form><slot /></form>' },
                'v-spacer': { template: '<div />' },
                'v-text-field': { template: '<input />' },
                'v-pagination': { template: '<div class="v-pagination" v-bind="$attrs"></div>' },
                'v-chip': { template: '<div class="v-chip" v-bind="$attrs"><slot /></div>' },
                'v-img': { template: '<img v-bind="$attrs" />' },
            },
        },
    })

    return {
        wrapper,
        foodStore: useFoodStore(),
        menuStore: useMenuStore(),
        restaurantStore: useRestaurantStore(),
    }
}

describe('Restaurant menus component', () => {
    it('builds a menu course by course with category first and food second', () => {
        const ctx = {
            form: {
                foodIds: [],
                courseDraft: {
                    categoryId: null,
                    foodId: null,
                },
            },
        }

        ctx.resetCourseDraft = () => (Menus as any).methods.resetCourseDraft.call(ctx)
        ctx.isDraftCategorySelected = (categoryId: number) => (Menus as any).methods.isDraftCategorySelected.call(ctx, categoryId)
        ctx.isDraftFoodSelected = (foodId: number) => (Menus as any).methods.isDraftFoodSelected.call(ctx, foodId)
        ctx.isFoodAlreadyAssigned = (foodId: number) => (Menus as any).methods.isFoodAlreadyAssigned.call(ctx, foodId)

        ;(Menus as any).methods.selectDraftCategory.call(ctx, 2)
        expect(ctx.form.courseDraft.categoryId).toBe(2)
        expect(ctx.form.courseDraft.foodId).toBe(null)

        ;(Menus as any).methods.selectDraftFood.call(ctx, 7)
        expect(ctx.form.courseDraft.foodId).toBe(7)

        ctx.canAddDraftCourse = true
        ;(Menus as any).methods.appendDraftCourse.call(ctx)
        expect(ctx.form.foodIds).toEqual([7])
        expect(ctx.form.courseDraft).toEqual({ categoryId: null, foodId: null })

        ;(Menus as any).methods.selectDraftCategory.call(ctx, 1)
        ;(Menus as any).methods.selectDraftFood.call(ctx, 4)
        ;(Menus as any).methods.appendDraftCourse.call(ctx)
        expect(ctx.form.foodIds).toEqual([7, 4])
    })

    it('does not allow assigning the same food twice', () => {
        const ctx = {
            form: {
                foodIds: [7],
                courseDraft: {
                    categoryId: 2,
                    foodId: null,
                },
            },
        }

        ctx.isFoodAlreadyAssigned = (foodId: number) => (Menus as any).methods.isFoodAlreadyAssigned.call(ctx, foodId)
        ctx.isDraftFoodSelected = (foodId: number) => (Menus as any).methods.isDraftFoodSelected.call(ctx, foodId)

        ;(Menus as any).methods.selectDraftFood.call(ctx, 7)
        expect(ctx.form.courseDraft.foodId).toBe(null)
    })

    it('filters foods for the current category by the search field', () => {
        const ctx = {
            foodSearch: 'sup',
            foodOptions: [
                { id: 1, title: 'Suppe', category: { id: 1 } },
                { id: 2, title: 'Tomatensuppe', category: { id: 1 } },
                { id: 3, title: 'Pasta', category: { id: 2 } },
            ],
            form: {
                courseDraft: {
                    categoryId: 1,
                },
            },
        }

        const availableFoodsForDraft = (Menus as any).computed.availableFoodsForDraft.call(ctx)
        const filteredFoodsForDraft = (Menus as any).computed.filteredFoodsForDraft.call({
            ...ctx,
            availableFoodsForDraft,
        })

        expect(availableFoodsForDraft.map((food: { id: number }) => food.id)).toEqual([1, 2])
        expect(filteredFoodsForDraft.map((food: { id: number }) => food.id)).toEqual([1, 2])

        const narrowedFoodsForDraft = (Menus as any).computed.filteredFoodsForDraft.call({
            ...ctx,
            foodSearch: 'tom',
            availableFoodsForDraft,
        })

        expect(narrowedFoodsForDraft.map((food: { id: number }) => food.id)).toEqual([2])
    })

    it('opens edit mode with foods ordered by course number', () => {
        const ctx = {
            dialog: false,
            editingMenuId: null,
            form: {
                title: '',
                foodIds: [],
                price: '',
                courseDraft: {
                    categoryId: 1,
                    foodId: 9,
                },
            },
            orderedMenuFoods: (foods: any[]) => (Menus as any).methods.orderedMenuFoods.call(ctx, foods),
            normalizePriceInput: (value: string) => (Menus as any).methods.normalizePriceInput.call(ctx, value),
        }

        ;(Menus as any).methods.openEditDialog.call(ctx, {
            id: 17,
            title: 'Mittagsmenü',
            price: '9.5',
            foods: [
                { id: 4, title: 'Dessert', course_number: 2 },
                { id: 1, title: 'Suppe', course_number: 1 },
            ],
        })

        expect(ctx.editingMenuId).toBe(17)
        expect(ctx.form.title).toBe('Mittagsmenü')
        expect(ctx.form.foodIds).toEqual([1, 4])
        expect(ctx.form.price).toBe('9,5')
        expect(ctx.form.courseDraft).toEqual({ categoryId: null, foodId: null })
        expect(ctx.dialog).toBe(true)
    })

    it('stores menu payload with ordered food ids and normalized price', async () => {
        const { wrapper, menuStore, restaurantStore } = mountMenus({
            foods: [
                { id: 1, title: 'Suppe', category: { id: 1, title: 'Vorspeise' } },
                { id: 2, title: 'Pasta', category: { id: 2, title: 'Hauptspeise' } },
            ],
        })

        menuStore.store = vi.fn().mockResolvedValue({ id: 10 })
        restaurantStore.loadSettings = vi.fn().mockResolvedValue(undefined)

        ;(wrapper.vm as any).form = {
            title: 'Tagesmenü',
            foodIds: [2, 1],
            price: '8,55',
            courseDraft: {
                categoryId: null,
                foodId: null,
            },
        }

        ;(wrapper.vm as any).$refs.menuForm = {
            validate: vi.fn().mockImplementation(async () => {
                ;(wrapper.vm as any).isMenuFormValid = true
            }),
        }

        await (wrapper.vm as any).saveMenu()

        expect(menuStore.store).toHaveBeenCalledWith({
            title: 'Tagesmenü',
            food_ids: [2, 1],
            price: '8.6',
        })
        expect(restaurantStore.loadSettings).toHaveBeenCalled()
        expect((wrapper.vm as any).dialog).toBe(false)
    })

    it('does not save when the menu title is missing', async () => {
        const { wrapper, menuStore } = mountMenus()

        menuStore.store = vi.fn()

        ;(wrapper.vm as any).form = {
            title: '',
            foodIds: [1],
            price: '8,5',
            courseDraft: {
                categoryId: null,
                foodId: null,
            },
        }

        ;(wrapper.vm as any).$refs.menuForm = {
            validate: vi.fn(),
        }

        await (wrapper.vm as any).saveMenu()

        expect((wrapper.vm as any).$refs.menuForm.validate).toHaveBeenCalled()
        expect(menuStore.store).not.toHaveBeenCalled()
        expect((wrapper.vm as any).isSaving).toBe(false)
    })

    it('opens a persistent delete confirmation before removing a menu', async () => {
        const { wrapper, menuStore, restaurantStore } = mountMenus()
        const menu = { id: 11, title: 'Abendmenü' }

        menuStore.destroy = vi.fn().mockResolvedValue(true)
        restaurantStore.loadSettings = vi.fn().mockResolvedValue(undefined)

        ;(wrapper.vm as any).requestDeleteMenu(menu)
        expect((wrapper.vm as any).deleteDialog).toBe(true)
        expect((wrapper.vm as any).pendingDeleteMenu).toEqual(menu)

        await (wrapper.vm as any).confirmDeleteMenu()

        expect(menuStore.destroy).toHaveBeenCalledWith(11)
        expect(restaurantStore.loadSettings).toHaveBeenCalled()
        expect((wrapper.vm as any).deleteDialog).toBe(false)
        expect((wrapper.vm as any).pendingDeleteMenu).toBe(null)
    })

    it('paginates menu cards and persists the shared page size setting', async () => {
        const menus = Array.from({ length: 14 }, (_, index) => ({
            id: index + 1,
            title: `Menü ${index + 1}`,
            price: '',
            foods: [],
            courses_count: 0,
        }))

        const { wrapper, restaurantStore } = mountMenus({ menus })
        restaurantStore.updateUserSettings = vi.fn().mockResolvedValue({
            restaurant_foods_pagination_number: 5,
        })

        expect((wrapper.vm as any).paginatedMenus).toHaveLength(12)
        expect((wrapper.vm as any).pageCount).toBe(2)
        expect((wrapper.vm as any).paginationSummary).toBe('1 - 12 von 14')
        expect(wrapper.find('.menu-pagination__selector').exists()).toBe(true)
        expect(wrapper.find('.menu-pagination__counter').exists()).toBe(true)

        ;(wrapper.vm as any).openPaginationDialog()
        ;(wrapper.vm as any).paginationDialogValue = '24'
        await (wrapper.vm as any).savePaginationDialog()

        expect(restaurantStore.updateUserSettings).toHaveBeenCalledWith(24)
        expect((wrapper.vm as any).paginationDialog).toBe(false)
    })

    it('filters menus live by the search field across titles and courses', async () => {
        const { wrapper } = mountMenus({
            menus: [
                {
                    id: 1,
                    title: 'Fruehlingsmenue',
                    price: '',
                    courses_count: 2,
                    foods: [
                        { id: 10, title: 'Tomatensuppe', category: { id: 1, title: 'Vorspeise' }, course_number: 1 },
                        { id: 11, title: 'Pasta', category: { id: 2, title: 'Hauptspeise' }, course_number: 2 },
                    ],
                },
                {
                    id: 2,
                    title: 'Desserttraum',
                    price: '',
                    courses_count: 1,
                    foods: [
                        { id: 12, title: 'Apfelstrudel', category: { id: 3, title: 'Dessert' }, course_number: 1 },
                    ],
                },
            ],
        })

        expect((wrapper.vm as any).filteredMenus.map((menu: { id: number }) => menu.id)).toEqual([1, 2])

        ;(wrapper.vm as any).menuSearchQuery = 'apfel'
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).filteredMenus.map((menu: { id: number }) => menu.id)).toEqual([2])
        expect((wrapper.vm as any).paginationSummary).toBe('1 - 1 von 1')

        ;(wrapper.vm as any).menuSearchQuery = 'frueh'
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).filteredMenus.map((menu: { id: number }) => menu.id)).toEqual([1])
    })

    it('renders edit and delete actions inline with the course count and price', () => {
        const { wrapper } = mountMenus({
            menus: [
                {
                    id: 1,
                    title: 'Frühlingsmenü',
                    price: '8.9',
                    courses_count: 2,
                    foods: [],
                },
            ],
        })

        expect(wrapper.find('.menu-card__summary').classes()).toContain('justify-space-between')
        expect(wrapper.find('.menu-card__summary-actions').exists()).toBe(true)
        expect(wrapper.find('.menu-card__summary-actions').classes()).toContain('justify-end')
        expect(wrapper.find('.menu-card').text()).toContain('8,90 EUR')
        expect(wrapper.find('button[icon="mdi-pencil"]').exists()).toBe(true)
        expect(wrapper.find('button[icon="mdi-delete"]').exists()).toBe(true)
        expect(wrapper.find('.menu-card').text()).toContain('2')
        expect(wrapper.findAll('.menu-card-actions')).toHaveLength(0)
    })

    it('supports a dedicated image view that keeps details and shows available food images', async () => {
        const { wrapper } = mountMenus({
            menus: [
                {
                    id: 1,
                    title: 'Frühlingsmenü',
                    price: '8.5',
                    courses_count: 2,
                    foods: [
                        {
                            id: 10,
                            title: 'Tomatensuppe',
                            category: { id: 1, title: 'Vorspeise' },
                            course_number: 1,
                            food_image_url: '/storage/restaurant/foods/tomatensuppe.jpg',
                        },
                        {
                            id: 11,
                            title: 'Pasta',
                            category: { id: 2, title: 'Hauptspeise' },
                            course_number: 2,
                            food_image_url: null,
                        },
                    ],
                },
            ],
        })

        expect((wrapper.vm as any).viewMode).toBe('detail')
        expect(wrapper.find('.menu-course-row__image').exists()).toBe(false)

        ;(wrapper.vm as any).setViewMode('image')
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).isImageView).toBe(true)
        expect(wrapper.find('.menu-card--image').exists()).toBe(true)
        expect(wrapper.find('.menu-course-row__image').exists()).toBe(true)
        expect(wrapper.find('.menu-course-row__image-element').attributes('src')).toBe('/storage/restaurant/foods/tomatensuppe.jpg')
        expect(wrapper.text()).toContain('Tomatensuppe')
        expect(wrapper.text()).toContain('Vorspeise')

        ;(wrapper.vm as any).setViewMode('compact')
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).isCompactView).toBe(true)
        expect(wrapper.find('.menu-course-row__image').exists()).toBe(false)
        expect(wrapper.find('.menu-course-row').exists()).toBe(false)
        expect(wrapper.text()).not.toContain('Tomatensuppe')
        expect(wrapper.text()).not.toContain('Vorspeise')
    })

    it('renders a search field and scroll container for food chips', () => {
        const { wrapper } = mountMenus({
            foods: [
                { id: 1, title: 'Suppe', category: { id: 1, title: 'Vorspeise' } },
                { id: 2, title: 'Pasta', category: { id: 2, title: 'Hauptspeise' } },
            ],
        })

        ;(wrapper.vm as any).form.courseDraft.categoryId = 1
        ;(wrapper.vm as any).foodSearch = 'sup'

        expect(wrapper.find('.menu-food-chip-scroll').exists()).toBe(true)
    })
})
