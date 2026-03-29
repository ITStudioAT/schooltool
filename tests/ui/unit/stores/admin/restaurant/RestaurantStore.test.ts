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
                general_settings: {
                    service_email: 'service@example.test',
                    new_users_must_confirm_email: true,
                    new_users_confirmer_email: 'freigabe@example.test',
                    user_information_intro_html: '<p>Info</p>',
                },
                can_manage_general_settings: true,
                user_settings: { restaurant_foods_pagination_number: 16 },
                can_manage_user_settings: true,
                online_settings: {
                    visibility_start_mode: 'when_orderable',
                    visibility_start_week_offset: 2,
                    visibility_start_day_of_week: 0,
                    visibility_start_time: '14:00',
                    order_start_mode: 'scheduled',
                    order_start_week_offset: 2,
                    order_start_day_of_week: 0,
                    order_start_time: '15:00',
                    order_end_week_offset: 1,
                    order_end_day_of_week: 5,
                    order_end_time: '17:00',
                    visibility_end_mode: 'plan_end',
                },
                can_manage_online_settings: true,
                stats: { foods_count: 4 },
            },
        })

        const store = useRestaurantStore()
        const result = await store.loadSettings()

        expect(result).toBe(true)
        expect(store.categories.map((category) => category.title)).toEqual(['Vorspeise', 'Nachspeise'])
        expect(store.ingredientIcons.map((icon) => icon.title)).toEqual(['Fisch', 'Schwein'])
        expect(store.allergenOptions).toEqual([{ character: 'G', short_description: 'Milch oder Laktose' }])
        expect(store.generalSettings.service_email).toBe('service@example.test')
        expect(store.generalSettings.new_users_confirmer_email).toBe('freigabe@example.test')
        expect(store.canManageGeneralSettings).toBe(true)
        expect(store.userSettings.restaurant_foods_pagination_number).toBe(16)
        expect(store.canManageUserSettings).toBe(true)
        expect(store.onlineSettings.visibility_start_mode).toBe('when_orderable')
        expect(store.onlineSettings.order_start_mode).toBe('scheduled')
        expect(store.canManageOnlineSettings).toBe(true)
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

    it('updates restaurant general settings locally after save', async () => {
        axiosMock.put.mockResolvedValue({
            data: {
                data: {
                    service_email: 'service@example.test',
                    new_users_must_confirm_email: true,
                    new_users_confirmer_email: 'freigabe@example.test',
                    user_information_intro_html: '<p>Hinweis</p>',
                },
            },
        })

        const store = useRestaurantStore()
        store.settings = {
            categories: [],
            ingredient_icons: [],
            allergen_options: [],
            allergen_suggestions: [],
            general_settings: {
                service_email: '',
                new_users_must_confirm_email: false,
                new_users_confirmer_email: '',
                user_information_intro_html: '',
            },
            can_manage_general_settings: true,
            user_settings: { restaurant_foods_pagination_number: 12 },
            can_manage_user_settings: true,
            online_settings: {},
            can_manage_online_settings: true,
            stats: {},
        }

        const payload = {
            restaurant_service_email: 'service@example.test',
            restaurant_new_users_must_confirm_email: true,
            restaurant_new_users_confirmer_email: 'freigabe@example.test',
            restaurant_user_information_intro_html: '<p>Hinweis</p>',
        }

        const result = await store.updateGeneralSettings(payload)

        expect(result).toEqual({
            service_email: 'service@example.test',
            new_users_must_confirm_email: true,
            new_users_confirmer_email: 'freigabe@example.test',
            user_information_intro_html: '<p>Hinweis</p>',
        })
        expect(store.generalSettings.service_email).toBe('service@example.test')
        expect(store.generalSettings.new_users_must_confirm_email).toBe(true)
        expect(store.generalSettings.new_users_confirmer_email).toBe('freigabe@example.test')
        expect(axiosMock.put).toHaveBeenCalledWith('/api/admin/restaurant/general-settings', {
            data: payload,
        })
    })

    it('updates restaurant online settings locally after save', async () => {
        axiosMock.put.mockResolvedValue({
            data: {
                data: {
                    visibility_start_mode: 'when_orderable',
                    visibility_start_week_offset: 2,
                    visibility_start_day_of_week: 0,
                    visibility_start_time: '14:00',
                    order_start_mode: 'scheduled',
                    order_start_week_offset: 2,
                    order_start_day_of_week: 0,
                    order_start_time: '15:00',
                    order_end_week_offset: 1,
                    order_end_day_of_week: 5,
                    order_end_time: '17:00',
                    visibility_end_mode: 'week_end',
                },
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
            online_settings: {
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
            },
            can_manage_online_settings: true,
            stats: {},
        }

        const payload = {
            visibility_start_mode: 'when_orderable',
            visibility_start_week_offset: 2,
            visibility_start_day_of_week: 0,
            visibility_start_time: '14:00',
            order_start_mode: 'scheduled',
            order_start_week_offset: 2,
            order_start_day_of_week: 0,
            order_start_time: '15:00',
            order_end_week_offset: 1,
            order_end_day_of_week: 5,
            order_end_time: '17:00',
            visibility_end_mode: 'week_end',
        }

        const result = await store.updateOnlineSettings(payload)

        expect(result).toEqual(payload)
        expect(store.onlineSettings.visibility_start_mode).toBe('when_orderable')
        expect(store.onlineSettings.order_start_mode).toBe('scheduled')
        expect(store.onlineSettings.visibility_end_mode).toBe('week_end')
        expect(axiosMock.put).toHaveBeenCalledWith('/api/admin/restaurant/online-settings', {
            data: payload,
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
            online_settings: {},
            can_manage_online_settings: true,
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
