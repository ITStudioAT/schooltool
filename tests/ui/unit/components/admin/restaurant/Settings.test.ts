import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import Settings from '@/pages/admin/restaurant/components/Settings.vue'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'

const componentStubs = {
    ItsGridBox: { props: ['title'], template: '<div><div class="grid-title">{{ title }}</div><slot name="header-actions" /><slot /></div>' },
    'v-col': { template: '<div><slot /></div>' },
    'v-row': { template: '<div><slot /></div>' },
    'v-btn': { template: '<button><slot /></button>' },
    'v-alert': { template: '<div><slot /></div>' },
    'v-list': { template: '<div><slot /></div>' },
    'v-list-item': { template: '<div><slot /><slot name="append" /></div>' },
    'v-dialog': {
        props: ['modelValue', 'persistent'],
        template: '<div v-if="modelValue" :data-persistent="persistent ? \'true\' : \'false\'"><slot /></div>',
    },
    'v-card': { template: '<div><slot /></div>' },
    'v-card-title': { template: '<div><slot /></div>' },
    'v-card-text': { template: '<div><slot /></div>' },
    'v-card-actions': { template: '<div><slot /></div>' },
    'v-spacer': { template: '<div />' },
    'v-divider': { template: '<hr />' },
    'v-form': { template: '<form><slot /></form>' },
    'v-text-field': { props: ['label'], template: '<input :data-label="label" />' },
    'file-pond': { template: '<div class="file-pond"></div>' },
    'v-checkbox': { props: ['label'], template: '<input :data-label="label" />' },
    'v-sheet': { template: '<div><slot /></div>' },
    'v-avatar': { template: '<div><slot /></div>' },
    'v-img': { template: '<img />' },
}

function mountSettings(initialState = {}) {
    return mount(Settings, {
        global: {
            plugins: [
                createTestingPinia({
                    createSpy: vi.fn,
                    stubActions: false,
                    initialState: {
                        AdminRestaurantStore: {
                            settings: {
                                categories: [{ id: 1, title: 'Hauptspeise', sort_order: 20, foods_count: 2 }],
                                ingredient_icons: [{ id: 2, title: 'Schwein', sort_order: 10, foods_count: 1, image_url: null }],
                                ...initialState,
                            },
                        },
                    },
                }),
            ],
            stubs: componentStubs,
        },
    })
}

describe('Restaurant settings component', () => {
    it('shows the category dialog as a persistent modal with validated fields', async () => {
        const wrapper = mountSettings()

        expect(wrapper.text()).toContain('Kategorien')
        expect(wrapper.text()).toContain('Zutaten-Symbole')
        expect((wrapper.vm as any).selectedPanel).toBe('categories')
        expect(wrapper.findAll('.grid-title')).toHaveLength(1)
        expect(wrapper.find('.grid-title').text()).toBe('Kategorien')
        expect(wrapper.find('input[data-label="Kategoriename"]').exists()).toBe(false)

        ;(wrapper.vm as any).openNewCategory()
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).categoryDialog).toBe(true)
        expect(wrapper.find('input[data-label="Kategoriename"]').exists()).toBe(true)
        expect(wrapper.findAll('input[data-label="Reihenfolge"]')).toHaveLength(1)
        expect(wrapper.html()).toContain('data-persistent="true"')
        expect(wrapper.text()).toContain('Kategorie speichern')

        ;(wrapper.vm as any).activatePanel('ingredient-icons')
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).selectedPanel).toBe('ingredient-icons')
        expect(wrapper.find('.grid-title').text()).toBe('Zutaten-Symbole')
        expect(wrapper.find('input[data-label="Titel"]').exists()).toBe(false)
        expect(wrapper.find('input[data-label="Bild"]').exists()).toBe(false)
        expect(wrapper.findAll('input[data-label="Reihenfolge"]')).toHaveLength(0)
    })

    it('opens ingredient icons in a persistent create or edit dialog', async () => {
        const wrapper = mountSettings({
            ingredient_icons: [{ id: 2, title: 'Schwein', sort_order: 10, foods_count: 1, image_url: '/icon.png' }],
        })

        ;(wrapper.vm as any).activatePanel('ingredient-icons')
        ;(wrapper.vm as any).openNewIngredientIcon()
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).ingredientIconDialog).toBe(true)
        expect(wrapper.find('input[data-label="Titel"]').exists()).toBe(true)
        expect(wrapper.find('.file-pond').exists()).toBe(true)
        expect(wrapper.findAll('input[data-label="Reihenfolge"]')).toHaveLength(0)
        expect(wrapper.html()).toContain('data-persistent="true"')
        expect(wrapper.text()).toContain('Symbol speichern')

        ;(wrapper.vm as any).closeIngredientIconDialog()
        ;(wrapper.vm as any).editIngredientIcon({ id: 2, title: 'Schwein', sort_order: 10, image_url: '/icon.png' })
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).ingredientIconDialog).toBe(true)
        expect(wrapper.text()).toContain('Symbol aktualisieren')
        expect(wrapper.find('input[data-label="Bestehendes Bild entfernen"]').exists()).toBe(true)
    })

    it('validates the category form before saving and closes the dialog after success', async () => {
        const wrapper = mountSettings({
            categories: [{ id: 7, title: 'Dessert', sort_order: 30, foods_count: 1 }],
            ingredient_icons: [],
        })

        const store = useRestaurantStore()
        store.storeCategory = vi.fn().mockResolvedValue({ id: 99, title: 'Neu', sort_order: 0 })
        store.loadSettings = vi.fn().mockResolvedValue(true)

        ;(wrapper.vm as any).openNewCategory()
        ;(wrapper.vm as any).$refs.categoryForm = {
            validate: vi.fn().mockImplementation(() => {
                ;(wrapper.vm as any).isCategoryFormValid = false
            }),
        }

        await (wrapper.vm as any).saveCategory()

        expect(store.storeCategory).not.toHaveBeenCalled()

        ;(wrapper.vm as any).categoryForm.title = 'Neu'
        ;(wrapper.vm as any).$refs.categoryForm = {
            validate: vi.fn().mockImplementation(() => {
                ;(wrapper.vm as any).isCategoryFormValid = true
            }),
        }

        await (wrapper.vm as any).saveCategory()

        expect(store.storeCategory).toHaveBeenCalledWith({
            title: 'Neu',
            sort_order: 0,
        })
        expect(store.loadSettings).toHaveBeenCalled()
        expect((wrapper.vm as any).categoryDialog).toBe(false)
        expect((wrapper.vm as any).categoryForm).toEqual({
            title: '',
            sort_order: 0,
        })
    })

    it('confirms category deletion with a persistent dialog', async () => {
        const wrapper = mountSettings({
            categories: [{ id: 7, title: 'Dessert', sort_order: 30, foods_count: 1 }],
            ingredient_icons: [],
        })

        const store = useRestaurantStore()
        store.destroyCategory = vi.fn().mockResolvedValue(true)
        store.loadSettings = vi.fn().mockResolvedValue(true)

        ;(wrapper.vm as any).openCategoryDeleteDialog({ id: 7, title: 'Dessert' })
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).categoryDeleteDialog).toBe(true)
        expect((wrapper.vm as any).pendingDeleteCategory).toEqual({ id: 7, title: 'Dessert' })
        expect(wrapper.html()).toContain('data-persistent="true"')

        await (wrapper.vm as any).confirmDestroyCategory()

        expect(store.destroyCategory).toHaveBeenCalledWith(7)
        expect(store.loadSettings).toHaveBeenCalled()
        expect((wrapper.vm as any).categoryDeleteDialog).toBe(false)
        expect((wrapper.vm as any).pendingDeleteCategory).toBe(null)
    })

    it('validates the ingredient icon form before saving and closes the dialog after success', async () => {
        const wrapper = mountSettings({
            ingredient_icons: [],
        })

        const store = useRestaurantStore()
        store.storeIngredientIcon = vi.fn().mockResolvedValue({ id: 4, title: 'Neu', sort_order: 0 })
        store.loadSettings = vi.fn().mockResolvedValue(true)

        ;(wrapper.vm as any).openNewIngredientIcon()
        ;(wrapper.vm as any).$refs.ingredientIconFormRef = {
            validate: vi.fn().mockImplementation(() => {
                ;(wrapper.vm as any).isIngredientIconFormValid = false
            }),
        }

        await (wrapper.vm as any).saveIngredientIcon()

        expect(store.storeIngredientIcon).not.toHaveBeenCalled()

        ;(wrapper.vm as any).ingredientIconForm.title = 'Neu'
        ;(wrapper.vm as any).$refs.ingredientIconFormRef = {
            validate: vi.fn().mockImplementation(() => {
                ;(wrapper.vm as any).isIngredientIconFormValid = true
            }),
        }

        await (wrapper.vm as any).saveIngredientIcon()

        expect(store.storeIngredientIcon).toHaveBeenCalledWith({
            title: 'Neu',
            image: null,
            remove_image: '',
        })
        expect(store.loadSettings).toHaveBeenCalled()
        expect((wrapper.vm as any).ingredientIconDialog).toBe(false)
        expect((wrapper.vm as any).ingredientIconForm).toEqual({
            title: '',
            image: null,
            currentImageUrl: null,
            removeImage: false,
        })
    })

    it('confirms ingredient icon deletion with a persistent dialog', async () => {
        const wrapper = mountSettings({
            ingredient_icons: [{ id: 9, title: 'Schwein', sort_order: 10, foods_count: 1, image_url: null }],
        })

        const store = useRestaurantStore()
        store.destroyIngredientIcon = vi.fn().mockResolvedValue(true)
        store.loadSettings = vi.fn().mockResolvedValue(true)

        ;(wrapper.vm as any).openIngredientIconDeleteDialog({ id: 9, title: 'Schwein' })
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).ingredientIconDeleteDialog).toBe(true)
        expect((wrapper.vm as any).pendingDeleteIngredientIcon).toEqual({ id: 9, title: 'Schwein' })
        expect(wrapper.html()).toContain('data-persistent="true"')

        await (wrapper.vm as any).confirmDestroyIngredientIcon()

        expect(store.destroyIngredientIcon).toHaveBeenCalledWith(9)
        expect(store.loadSettings).toHaveBeenCalled()
        expect((wrapper.vm as any).ingredientIconDeleteDialog).toBe(false)
        expect((wrapper.vm as any).pendingDeleteIngredientIcon).toBe(null)
    })
})
