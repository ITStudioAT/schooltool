import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import Settings from '@/pages/admin/restaurant/components/Settings.vue'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'

const componentStubs = {
    FreeDays: { template: '<div class="free-days-stub">Freie Tage Inhalt</div>' },
    ItsRichTextEditor: { props: ['modelValue'], template: '<div class="rich-text-editor-stub">{{ modelValue }}</div>' },
    ItsGridBox: { props: ['title'], template: '<div><div class="grid-title">{{ title }}</div><slot name="header-actions" /><slot /></div>' },
    'v-col': { template: '<div><slot /></div>' },
    'v-row': { template: '<div><slot /></div>' },
    'v-btn': { template: '<button v-bind="$attrs"><slot /></button>' },
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
    'v-switch': { props: ['label'], template: '<input :data-label="label" type="checkbox" />' },
    'v-sheet': { template: '<div><slot /></div>' },
    'v-avatar': { template: '<div><slot /></div>' },
    'v-img': { template: '<img />' },
}

function mountSettings(options: { initialState?: Record<string, unknown>, routeQuery?: Record<string, string>, routerReplace?: ReturnType<typeof vi.fn> } = {}) {
    const {
        initialState = {},
        routeQuery = {},
        routerReplace = vi.fn(() => Promise.resolve()),
    } = options

    const wrapper = mount(Settings, {
        global: {
            plugins: [
                createTestingPinia({
                    createSpy: vi.fn,
                    stubActions: false,
                    initialState: {
                        AdminRestaurantStore: {
                            settings: {
                                general_settings: {
                                    service_email: 'service@example.test',
                                    new_users_must_confirm_email: true,
                                    new_users_confirmer_email: 'freigabe@example.test',
                                    user_information_intro_html: '<p>Willkommen im Restaurant.</p>',
                                },
                                can_manage_general_settings: true,
                                categories: [{ id: 1, title: 'Hauptspeise', sort_order: 20, foods_count: 2 }],
                                ingredient_icons: [{ id: 2, title: 'Schwein', sort_order: 10, foods_count: 1, image_url: null }],
                                online_settings: {
                                    order_start_mode: 'scheduled',
                                    order_start_week_offset: 2,
                                    order_start_day_of_week: 0,
                                    order_start_time: '15:00',
                                    order_end_week_offset: 1,
                                    order_end_day_of_week: 5,
                                    order_end_time: '17:00',
                                },
                                can_manage_online_settings: true,
                                ...initialState,
                            },
                        },
                    },
                }),
            ],
            stubs: componentStubs,
            mocks: {
                $route: { query: routeQuery },
                $router: { replace: routerReplace },
            },
        },
    })

    return {
        wrapper,
        routerReplace,
        routeQuery,
    }
}

describe('Restaurant settings component', () => {
    it('shows the category dialog as a persistent modal with validated fields', async () => {
        const { wrapper } = mountSettings()

        expect(wrapper.text()).toContain('Allgemein')
        expect(wrapper.text()).toContain('Kategorien')
        expect(wrapper.text()).toContain('Zutaten-Symbole')
        expect(wrapper.text()).toContain('Online')
        expect((wrapper.vm as any).selectedPanel).toBe('general')
        expect(wrapper.findAll('.grid-title')).toHaveLength(1)
        expect(wrapper.find('.grid-title').text()).toBe('Allgemein')
        expect(wrapper.text()).toContain('Allgemeine Restaurant-Einstellungen')
        expect(wrapper.text()).toContain('Service-E-Mail-Adresse')
        expect(wrapper.text()).toContain('service@example.test')
        expect(wrapper.text()).toContain('Neue Benutzer müssen bestätigt werden')
        expect(wrapper.text()).toContain('freigabe@example.test')
        expect(wrapper.find('input[data-label="Service-E-Mail-Adresse"]').exists()).toBe(false)
        expect(wrapper.find('input[data-label="Kategoriename"]').exists()).toBe(false)

        ;(wrapper.vm as any).activatePanel('categories')
        await wrapper.vm.$nextTick()

        ;(wrapper.vm as any).openNewCategory()
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).categoryDialog).toBe(true)
        expect(wrapper.find('input[data-label="Kategoriename"]').exists()).toBe(true)
        expect(wrapper.findAll('input[data-label="Reihenfolge"]')).toHaveLength(1)
        expect(wrapper.text()).toContain('Kategorie speichern')

        ;(wrapper.vm as any).activatePanel('ingredient-icons')
        await wrapper.vm.$nextTick()
        ;(wrapper.vm as any).closeCategoryDialog()
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).selectedPanel).toBe('ingredient-icons')
        expect(wrapper.find('.grid-title').text()).toBe('Zutaten-Symbole')
        expect(wrapper.find('input[data-label="Titel"]').exists()).toBe(false)
        expect(wrapper.find('input[data-label="Bild"]').exists()).toBe(false)
        expect(wrapper.findAll('input[data-label="Reihenfolge"]')).toHaveLength(0)
    })

    it('restores the selected panel from the route query and syncs changes back into the url', async () => {
        const { wrapper, routerReplace, routeQuery } = mountSettings({
            routeQuery: { panel: 'online', foo: 'bar' },
        })

        expect((wrapper.vm as any).selectedPanel).toBe('online')
        expect(wrapper.text()).toContain('Bestellzeitraum')
        expect(wrapper.text()).toContain('Live-Vorschau')
        expect(wrapper.text()).toContain('Bestellzeitraum für Menüpläne')
        expect(wrapper.text()).toContain('Sobald verfügbar')
        expect(wrapper.text()).toContain('Fester Tag')
        expect(wrapper.findAll('button').some((button) => button.text() === 'Sobald verfügbar')).toBe(true)
        expect(wrapper.findAll('button').some((button) => button.text() === 'Fester Tag')).toBe(true)

        ;(wrapper.vm as any).activatePanel('ingredient-icons')
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).selectedPanel).toBe('ingredient-icons')
        expect(routerReplace).toHaveBeenCalledWith({
            query: {
                ...routeQuery,
                panel: 'ingredient-icons',
            },
        })
    })

    it('falls back to categories for invalid route query panels', () => {
        const { wrapper } = mountSettings({
            routeQuery: { panel: 'unknown' },
        })

        expect((wrapper.vm as any).selectedPanel).toBe('general')
    })

    it('shows the general settings panel when selected explicitly', async () => {
        const { wrapper, routerReplace, routeQuery } = mountSettings({
            routeQuery: { panel: 'general', foo: 'bar' },
        })

        expect((wrapper.vm as any).selectedPanel).toBe('general')
        expect(wrapper.find('.grid-title').text()).toBe('Allgemein')
        expect(wrapper.text()).toContain('Allgemeine Restaurant-Einstellungen')
        expect(wrapper.text()).toContain('freigabe@example.test')
        expect(wrapper.text()).toContain('Willkommen im Restaurant.')

        ;(wrapper.vm as any).activatePanel('general')
        await wrapper.vm.$nextTick()

        expect(routerReplace).toHaveBeenCalledWith({
            query: {
                ...routeQuery,
                panel: 'general',
            },
        })
    })

    it('starts editing the general settings after clicking bearbeiten', async () => {
        const { wrapper } = mountSettings()

        expect((wrapper.vm as any).isEditingGeneralSettings).toBe(false)

        ;(wrapper.vm as any).beginGeneralSettingsEdit()
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).isEditingGeneralSettings).toBe(true)
        expect(wrapper.text()).toContain('Abbrechen')
        expect(wrapper.text()).toContain('Allgemeine Einstellungen speichern')
        expect(wrapper.find('input[data-label="Service-E-Mail-Adresse"]').exists()).toBe(true)
        expect(wrapper.find('input[data-label="E-Mail-Adresse für Bestätigung"]').exists()).toBe(true)
    })

    it('shows additional icon actions in the header while general editing is active', async () => {
        const { wrapper } = mountSettings()

        ;(wrapper.vm as any).beginGeneralSettingsEdit()
        await wrapper.vm.$nextTick()

        expect(wrapper.find('[data-testid="general-settings-cancel-icon"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="general-settings-save-icon"]').exists()).toBe(true)
        expect(wrapper.text()).toContain('Abbrechen')
        expect(wrapper.text()).toContain('Allgemeine Einstellungen speichern')
    })

    it('disables other settings menu buttons while general editing is active', async () => {
        const { wrapper, routerReplace } = mountSettings()

        ;(wrapper.vm as any).beginGeneralSettingsEdit()
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).isPanelNavigationDisabled('general')).toBe(false)
        expect((wrapper.vm as any).isPanelNavigationDisabled('categories')).toBe(true)

        ;(wrapper.vm as any).activatePanel('categories')
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).selectedPanel).toBe('general')
        expect(routerReplace).not.toHaveBeenCalled()
    })

    it('saves general settings through the restaurant store', async () => {
        const store = useRestaurantStore()
        store.updateGeneralSettings = vi.fn().mockResolvedValue({
            service_email: 'neu@example.test',
            new_users_must_confirm_email: true,
            new_users_confirmer_email: 'bestaetigung@example.test',
            user_information_intro_html: '<p>Neu</p>',
        })

        const ctx = {
            isEditingGeneralSettings: true,
            isGeneralFormValid: false,
            canManageGeneralSettings: true,
            generalSettingsForm: {
                service_email: 'neu@example.test',
                new_users_must_confirm_email: true,
                new_users_confirmer_email: 'bestaetigung@example.test',
                user_information_intro_html: '<p>Neu</p>',
            },
            $refs: {
                generalForm: {
                    validate: vi.fn().mockImplementation(() => {
                        ctx.isGeneralFormValid = true
                    }),
                },
            },
            resetGeneralSettingsForm: vi.fn(),
        }

        await (Settings as any).methods.saveGeneralSettings.call(ctx)

        expect(store.updateGeneralSettings).toHaveBeenCalledWith({
            restaurant_service_email: 'neu@example.test',
            restaurant_new_users_must_confirm_email: true,
            restaurant_new_users_confirmer_email: 'bestaetigung@example.test',
            restaurant_user_information_intro_html: '<p>Neu</p>',
        })
        expect(ctx.resetGeneralSettingsForm).toHaveBeenCalled()
        expect(ctx.isEditingGeneralSettings).toBe(false)
    })

    it('opens ingredient icons in a persistent create or edit dialog', async () => {
        const { wrapper } = mountSettings({
            initialState: {
                ingredient_icons: [{ id: 2, title: 'Schwein', sort_order: 10, foods_count: 1, image_url: '/icon.png' }],
            },
        })

        ;(wrapper.vm as any).activatePanel('ingredient-icons')
        ;(wrapper.vm as any).openNewIngredientIcon()
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).ingredientIconDialog).toBe(true)
        expect(wrapper.find('input[data-label="Titel"]').exists()).toBe(true)
        expect(wrapper.find('.file-pond').exists()).toBe(true)
        expect(wrapper.findAll('input[data-label="Reihenfolge"]')).toHaveLength(0)
        expect(wrapper.text()).toContain('Symbol speichern')

        ;(wrapper.vm as any).closeIngredientIconDialog()
        ;(wrapper.vm as any).editIngredientIcon({ id: 2, title: 'Schwein', sort_order: 10, image_url: '/icon.png' })
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).ingredientIconDialog).toBe(true)
        expect(wrapper.text()).toContain('Symbol aktualisieren')
        expect(wrapper.find('input[data-label="Bestehendes Bild entfernen"]').exists()).toBe(true)
    })

    it('validates the category form before saving and closes the dialog after success', async () => {
        const store = useRestaurantStore()
        store.storeCategory = vi.fn().mockResolvedValue({ id: 99, title: 'Neu', sort_order: 0 })
        store.loadSettings = vi.fn().mockResolvedValue(true)

        const ctx = {
            isCategoryFormValid: false,
            categoryEditingId: null,
            categoryDialog: true,
            categoryForm: {
                title: '',
                sort_order: 0,
            },
            $refs: {
                categoryForm: {
                    validate: vi.fn().mockImplementation(() => {
                        ctx.isCategoryFormValid = false
                    }),
                },
            },
            resetCategoryForm() {
                return (Settings as any).methods.resetCategoryForm.call(this)
            },
        }

        await (Settings as any).methods.saveCategory.call(ctx)

        expect(store.storeCategory).not.toHaveBeenCalled()

        ctx.categoryForm.title = 'Neu'
        ctx.$refs.categoryForm = {
            validate: vi.fn().mockImplementation(() => {
                ctx.isCategoryFormValid = true
            }),
        }

        await (Settings as any).methods.saveCategory.call(ctx)

        expect(store.storeCategory).toHaveBeenCalledWith({
            title: 'Neu',
            sort_order: 0,
        })
        expect(store.loadSettings).toHaveBeenCalled()
        expect(ctx.categoryDialog).toBe(false)
        expect(ctx.categoryForm).toEqual({
            title: '',
            sort_order: 0,
        })
    })

    it('confirms category deletion with a persistent dialog', async () => {
        const { wrapper } = mountSettings({
            initialState: {
                categories: [{ id: 7, title: 'Dessert', sort_order: 30, foods_count: 1 }],
                ingredient_icons: [],
            },
        })

        const store = useRestaurantStore()
        store.destroyCategory = vi.fn().mockResolvedValue(true)
        store.loadSettings = vi.fn().mockResolvedValue(true)

        ;(wrapper.vm as any).openCategoryDeleteDialog({ id: 7, title: 'Dessert' })
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).categoryDeleteDialog).toBe(true)
        expect((wrapper.vm as any).pendingDeleteCategory).toEqual({ id: 7, title: 'Dessert' })

        await (wrapper.vm as any).confirmDestroyCategory()

        expect(store.destroyCategory).toHaveBeenCalledWith(7)
        expect(store.loadSettings).toHaveBeenCalled()
        expect((wrapper.vm as any).categoryDeleteDialog).toBe(false)
        expect((wrapper.vm as any).pendingDeleteCategory).toBe(null)
    })

    it('validates the ingredient icon form before saving and closes the dialog after success', async () => {
        const store = useRestaurantStore()
        store.storeIngredientIcon = vi.fn().mockResolvedValue({ id: 4, title: 'Neu', sort_order: 0 })
        store.loadSettings = vi.fn().mockResolvedValue(true)

        const ctx = {
            isIngredientIconFormValid: false,
            ingredientIconEditingId: null,
            ingredientIconDialog: true,
            ingredientIconForm: {
                title: '',
                image: null,
                currentImageUrl: null,
                removeImage: false,
            },
            $refs: {
                ingredientIconFormRef: {
                    validate: vi.fn().mockImplementation(() => {
                        ctx.isIngredientIconFormValid = false
                    }),
                },
            },
            resetIngredientIconForm() {
                return (Settings as any).methods.resetIngredientIconForm.call(this)
            },
        }

        await (Settings as any).methods.saveIngredientIcon.call(ctx)

        expect(store.storeIngredientIcon).not.toHaveBeenCalled()

        ctx.ingredientIconForm.title = 'Neu'
        ctx.$refs.ingredientIconFormRef = {
            validate: vi.fn().mockImplementation(() => {
                ctx.isIngredientIconFormValid = true
            }),
        }

        await (Settings as any).methods.saveIngredientIcon.call(ctx)

        expect(store.storeIngredientIcon).toHaveBeenCalledWith({
            title: 'Neu',
            image: null,
            remove_image: '',
        })
        expect(store.loadSettings).toHaveBeenCalled()
        expect(ctx.ingredientIconDialog).toBe(false)
        expect(ctx.ingredientIconForm).toEqual({
            title: '',
            image: null,
            currentImageUrl: null,
            removeImage: false,
        })
    })

    it('confirms ingredient icon deletion with a persistent dialog', async () => {
        const { wrapper } = mountSettings({
            initialState: {
                ingredient_icons: [{ id: 9, title: 'Schwein', sort_order: 10, foods_count: 1, image_url: null }],
            },
        })

        const store = useRestaurantStore()
        store.destroyIngredientIcon = vi.fn().mockResolvedValue(true)
        store.loadSettings = vi.fn().mockResolvedValue(true)

        ;(wrapper.vm as any).openIngredientIconDeleteDialog({ id: 9, title: 'Schwein' })
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).ingredientIconDeleteDialog).toBe(true)
        expect((wrapper.vm as any).pendingDeleteIngredientIcon).toEqual({ id: 9, title: 'Schwein' })

        await (wrapper.vm as any).confirmDestroyIngredientIcon()

        expect(store.destroyIngredientIcon).toHaveBeenCalledWith(9)
        expect(store.loadSettings).toHaveBeenCalled()
        expect((wrapper.vm as any).ingredientIconDeleteDialog).toBe(false)
        expect((wrapper.vm as any).pendingDeleteIngredientIcon).toBe(null)
    })
})
