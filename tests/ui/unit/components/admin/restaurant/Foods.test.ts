import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import Foods from '@/pages/admin/restaurant/components/Foods.vue'
import { useFoodStore } from '@/stores/admin/restaurant/FoodStore'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'

function mountFoods(options: {
    foods?: any[]
    ingredientIcons?: any[]
    allergenOptions?: any[]
} = {}) {
    const pinia = createTestingPinia({
        stubActions: false,
        createSpy: vi.fn,
        initialState: {
            AdminRestaurantFoodStore: {
                foods: options.foods ?? [],
            },
            AdminRestaurantStore: {
                settings: {
                    categories: [],
                    ingredient_icons: options.ingredientIcons ?? [
                        { id: 2, title: 'Schwein', image_url: 'data:image/svg+xml;base64,AAA=' },
                    ],
                    allergen_options: options.allergenOptions ?? [
                        { character: 'A', short_description: 'Glutenhaltiges Getreide' },
                        { character: 'G', short_description: 'Milch oder Laktose' },
                    ],
                    allergen_suggestions: [],
                    user_settings: { restaurant_foods_pagination_number: 12 },
                    can_manage_user_settings: true,
                    stats: {},
                },
            },
        },
    })

    const wrapper = mount(Foods, {
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
                'v-spacer': { template: '<div />' },
                'v-text-field': { template: '<input />' },
                'v-combobox': { template: '<input />' },
                'v-textarea': { template: '<textarea />' },
                'file-pond': { template: '<div class="file-pond"></div>' },
                'v-checkbox': { template: '<input type="checkbox" />' },
                'v-sheet': { template: '<div><slot /></div>' },
                'v-img': { template: '<img v-bind="$attrs" />' },
                'v-pagination': { template: '<div class="v-pagination" v-bind="$attrs"></div>' },
                'v-icon': { template: '<i><slot /></i>' },
                'v-avatar': { template: '<div><slot /></div>' },
                'v-chip': { template: '<div class="v-chip" v-bind="$attrs"><slot /></div>' },
            },
        },
    })

    return {
        pinia,
        wrapper,
        foodStore: useFoodStore(),
        restaurantStore: useRestaurantStore(),
    }
}

describe('Restaurant foods component', () => {
    it('adds and removes configured allergens from the form', () => {
        const ctx = {
            form: {
                allergens: [],
            },
            allergenOptions: [
                { character: 'A', short_description: 'Glutenhaltiges Getreide' },
                { character: 'G', short_description: 'Milch oder Laktose' },
            ],
        }

        ctx.isSelectedAllergen = (character: string) => (Foods as any).methods.isSelectedAllergen.call(ctx, character)

        ;(Foods as any).methods.toggleAllergen.call(ctx, 'A')
        expect(ctx.form.allergens).toEqual(['A'])

        ;(Foods as any).methods.toggleAllergen.call(ctx, 'G')
        expect(ctx.form.allergens).toEqual(['A', 'G'])

        ;(Foods as any).methods.toggleAllergen.call(ctx, 'A')
        expect(ctx.form.allergens).toEqual(['G'])
    })

    it('formats configured allergen labels for display', () => {
        const ctx = {
            allergenOptions: [
                { character: 'G', short_description: 'Milch oder Laktose' },
            ],
        }

        expect((Foods as any).methods.allergenLabel.call(ctx, 'G')).toBe('G - Milch oder Laktose')
        expect((Foods as any).methods.allergenLabel.call(ctx, 'Freitext')).toBe('Freitext')
    })

    it('formats card prices with two decimals and normalizes form prices to one decimal place', () => {
        const ctx = {
            form: {
                price: '4,44',
            },
        }

        ctx.hasPrice = (price: string) => (Foods as any).methods.hasPrice.call(ctx, price)
        ctx.normalizePriceInput = (value: string) => (Foods as any).methods.normalizePriceInput.call(ctx, value)

        expect((Foods as any).methods.hasPrice.call(ctx, '')).toBe(false)
        expect((Foods as any).methods.hasPrice.call(ctx, '4.4')).toBe(true)
        expect((Foods as any).methods.formatCardPrice.call(ctx, '4.4')).toBe('4,40 EUR')
        expect((Foods as any).methods.formatCardPrice.call(ctx, '')).toBe('')
        expect((Foods as any).methods.formatPrice.call(ctx, '4.4')).toBe('4,4 EUR')
        expect((Foods as any).methods.normalizePriceInput.call(ctx, '4,44')).toBe('4,4')
        expect((Foods as any).methods.normalizePricePayload.call(ctx, '4,44')).toBe('4.4')

        ;(Foods as any).methods.normalizePriceField.call(ctx)
        expect(ctx.form.price).toBe('4,4')
    })

    it('adds and removes ingredient icon ids from the form', () => {
        const ctx = {
            form: {
                ingredientIconIds: [],
            },
        }

        ctx.isSelectedIngredientIcon = (iconId: number) => (Foods as any).methods.isSelectedIngredientIcon.call(ctx, iconId)

        ;(Foods as any).methods.toggleIngredientIcon.call(ctx, 3)
        expect(ctx.form.ingredientIconIds).toEqual([3])

        ;(Foods as any).methods.toggleIngredientIcon.call(ctx, 5)
        expect(ctx.form.ingredientIconIds).toEqual([3, 5])

        ;(Foods as any).methods.toggleIngredientIcon.call(ctx, 3)
        expect(ctx.form.ingredientIconIds).toEqual([5])
    })

    it('stores the selected PQINA file in the form state', () => {
        const file = new File(['image'], 'meal.png', { type: 'image/png' })
        const ctx = {
            form: {
                foodImage: null,
                removeFoodImage: true,
            },
        }

        ;(Foods as any).methods.updateFoodImageFiles.call(ctx, [{ file }])

        expect(ctx.form.foodImage).toBe(file)
        expect(ctx.form.removeFoodImage).toBe(false)
    })

    it('opens a delete confirmation before removing a food', async () => {
        const { wrapper, foodStore, restaurantStore } = mountFoods()
        const food = { id: 9, title: 'Pasta' }

        foodStore.destroy = vi.fn().mockResolvedValue(true)
        restaurantStore.loadSettings = vi.fn().mockResolvedValue(undefined)

        ;(wrapper.vm as any).requestDeleteFood(food)
        expect((wrapper.vm as any).deleteDialog).toBe(true)
        expect((wrapper.vm as any).pendingDeleteFood).toEqual(food)

        await (wrapper.vm as any).confirmDeleteFood()

        expect(foodStore.destroy).toHaveBeenCalledWith(9)
        expect(restaurantStore.loadSettings).toHaveBeenCalled()
        expect((wrapper.vm as any).deleteDialog).toBe(false)
        expect((wrapper.vm as any).pendingDeleteFood).toBe(null)
    })

    it('renders the overview card with title first, optional price, and inline image constraints', () => {
        const { wrapper } = mountFoods({
            foods: [
                {
                    id: 1,
                    title: 'Apfelstrudel',
                    description: 'Mit Vanillesauce',
                    price: '4.5',
                    food_image_url: '/img/apfelstrudel.png',
                    category: { title: 'Dessert' },
                    allergens: ['A'],
                    ingredient_icons: [],
                },
                {
                    id: 2,
                    title: 'Gemüsesuppe',
                    description: '',
                    price: '',
                    food_image_url: null,
                    category: null,
                    allergens: [],
                    ingredient_icons: [],
                },
            ],
        })

        const text = wrapper.text()

        expect(text).toContain('Apfelstrudel')
        expect(text).toContain('4,50 EUR')
        expect(text).not.toContain('Preis offen')
        expect(wrapper.find('.food-card__inline-image').exists()).toBe(true)
        expect(wrapper.find('.food-card__inline-image-element').attributes('max-width')).toBe('120')
        expect(wrapper.find('.food-card__inline-image-element').attributes('max-height')).toBe('60')
    })

    it('uses a more visible refresh button style in the header', () => {
        const { wrapper } = mountFoods()
        const refreshButton = wrapper.find('button[prepend-icon="mdi-refresh"]')

        expect(refreshButton.exists()).toBe(true)
        expect(refreshButton.attributes('color')).toBe('warning')
        expect(refreshButton.attributes('variant')).toBe('flat')
    })

    it('paginates the food cards and saves the user page size from the dialog', async () => {
        const foods = Array.from({ length: 13 }, (_, index) => ({
            id: index + 1,
            title: `Speise ${index + 1}`,
            description: '',
            price: '',
            food_image_url: null,
            category: { title: 'Kategorie' },
            allergens: [],
            ingredient_icons: [],
        }))

        const { wrapper, restaurantStore } = mountFoods({ foods })
        restaurantStore.updateUserSettings = vi.fn().mockResolvedValue({
            restaurant_foods_pagination_number: 5,
        })

        expect((wrapper.vm as any).paginatedFoods).toHaveLength(12)
        expect((wrapper.vm as any).pageCount).toBe(2)
        expect((wrapper.vm as any).paginationSummary).toBe('1 - 12 von 13')
        expect(wrapper.find('.food-pagination__selector').exists()).toBe(true)
        expect(wrapper.find('.food-pagination__counter').exists()).toBe(true)

        ;(wrapper.vm as any).openPaginationDialog()
        expect((wrapper.vm as any).paginationDialog).toBe(true)

        ;(wrapper.vm as any).paginationDialogValue = '24'
        await (wrapper.vm as any).savePaginationDialog()

        expect(restaurantStore.updateUserSettings).toHaveBeenCalledWith(24)
        expect((wrapper.vm as any).paginationDialog).toBe(false)
    })

    it('switches to a compact card view with title and category only', async () => {
        const { wrapper } = mountFoods({
            foods: [
                {
                    id: 1,
                    title: 'Apfelstrudel',
                    description: 'Mit Vanillesauce',
                    price: '4.5',
                    food_image_url: '/img/apfelstrudel.png',
                    category: { title: 'Dessert' },
                    allergens: ['A'],
                    ingredient_icons: [
                        { id: 2, title: 'Schwein', image_url: 'data:image/svg+xml;base64,AAA=' },
                    ],
                },
            ],
        })

        expect(wrapper.text()).toContain('4,50 EUR')
        expect(wrapper.find('.food-card__inline-image').exists()).toBe(true)

        await (wrapper.vm as any).toggleCardView()
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).isCompactView).toBe(true)
        expect(wrapper.text()).toContain('Apfelstrudel')
        expect(wrapper.text()).toContain('Dessert')
        expect(wrapper.text()).not.toContain('4,50 EUR')
        expect(wrapper.text()).not.toContain('Mit Vanillesauce')
        expect(wrapper.find('.food-card__inline-image').exists()).toBe(false)
        expect(wrapper.find('.food-card--compact').exists()).toBe(true)
        expect(wrapper.find('.food-card__actions--compact').exists()).toBe(true)
    })

    it('renders allergen and ingredient icon selector chips in wrapping rows', () => {
        const { wrapper } = mountFoods()

        expect(wrapper.find('.allergen-chip-list').exists()).toBe(true)
        expect(wrapper.findAll('.allergen-chip')).toHaveLength(2)
        expect(wrapper.find('.allergen-chip-list').classes()).toContain('flex-wrap')
        expect(wrapper.find('.allergen-chip__code').exists()).toBe(true)
        expect(wrapper.find('.allergen-chip__label').exists()).toBe(true)
        expect(wrapper.find('.ingredient-icon-chip-list').classes()).toContain('flex-wrap')
        expect(wrapper.findAll('.ingredient-icon-chip')).toHaveLength(1)
        expect(wrapper.find('.ingredient-icon-chip__label').text()).toBe('Schwein')
        expect(wrapper.find('.file-pond').exists()).toBe(true)
    })
})
