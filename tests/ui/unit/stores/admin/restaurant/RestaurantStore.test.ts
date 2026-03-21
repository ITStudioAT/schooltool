import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('RestaurantStore', () => {
    const adminStoreMock = { is_loading: 0 }
    const notifyMock = vi.fn()
    const notificationStoreMock = { notify: notifyMock }
    const axiosMock = {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())
        adminStoreMock.is_loading = 0
        notifyMock.mockReset()
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()
        axiosMock.put.mockReset()
        axiosMock.delete.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)

        globalThis.axios = axiosMock as never
    })

    it('loads settings and sorts category data', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                categories: [
                    { id: 2, title: 'Nachspeise', sort_order: 30 },
                    { id: 1, title: 'Vorspeise', sort_order: 10 },
                ],
                ingredient_icons: [
                    { id: 11, title: 'Schwein', sort_order: 10 },
                    { id: 12, title: 'Fisch', sort_order: 50 },
                ],
                allergen_options: [{ character: 'G', short_description: 'Milch oder Laktose' }],
                allergen_suggestions: ['Milch'],
                user_settings: { restaurant_foods_pagination_number: 16 },
                can_manage_user_settings: true,
                stats: { foods_count: 4 },
            },
        })

        const store = useRestaurantStore()
        const result = await store.loadSettings()

        expect(result).toBe(true)
        expect(store.categories.map((category) => category.title)).toEqual(['Vorspeise', 'Nachspeise'])
        expect(store.ingredientIcons.map((icon) => icon.title)).toEqual(['Fisch', 'Schwein'])
        expect(store.allergenOptions).toEqual([{ character: 'G', short_description: 'Milch oder Laktose' }])
        expect(store.userSettings.restaurant_foods_pagination_number).toBe(16)
        expect(store.canManageUserSettings).toBe(true)
        expect(store.stats.foods_count).toBe(4)
    })

    it('updates restaurant user settings locally after save', async () => {
        axiosMock.put.mockResolvedValue({
            data: {
                data: { restaurant_foods_pagination_number: 24 },
            },
        })

        const store = useRestaurantStore()
        store.settings = {
            categories: [],
            ingredient_icons: [],
            allergen_options: [],
            allergen_suggestions: [],
            user_settings: { restaurant_foods_pagination_number: 12 },
            can_manage_user_settings: true,
            stats: {},
        }

        const result = await store.updateUserSettings(24)

        expect(result).toEqual({ restaurant_foods_pagination_number: 24 })
        expect(store.userSettings.restaurant_foods_pagination_number).toBe(24)
        expect(axiosMock.put).toHaveBeenCalledWith('/api/admin/restaurant/user-settings', {
            data: {
                restaurant_foods_pagination_number: 24,
            },
        })
    })

    it('stores category locally after creation', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: { id: 4, title: 'Getraenke', sort_order: 40 },
            },
        })

        const store = useRestaurantStore()
        store.settings = {
            categories: [{ id: 1, title: 'Hauptspeise', sort_order: 20 }],
            ingredient_icons: [],
            allergen_options: [],
            allergen_suggestions: [],
            user_settings: { restaurant_foods_pagination_number: 12 },
            can_manage_user_settings: true,
            stats: {},
        }

        const created = await store.storeCategory({ title: 'Getraenke', sort_order: 40 })

        expect(created).toEqual({ id: 4, title: 'Getraenke', sort_order: 40 })
        expect(store.categories).toHaveLength(2)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/restaurant/categories', { title: 'Getraenke', sort_order: 40 })
    })

    it('removes ingredient icon after deletion', async () => {
        axiosMock.delete.mockResolvedValue({})

        const store = useRestaurantStore()
        store.settings = {
            categories: [],
            ingredient_icons: [{ id: 9, title: 'Schwein', sort_order: 10 }],
            allergen_options: [],
            allergen_suggestions: [],
            user_settings: { restaurant_foods_pagination_number: 12 },
            can_manage_user_settings: true,
            stats: {},
        }

        const result = await store.destroyIngredientIcon(9)

        expect(result).toBe(true)
        expect(store.ingredientIcons).toEqual([])
    })
})
