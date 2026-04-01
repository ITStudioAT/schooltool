import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useRestaurantUserStore } from '@/stores/admin/restaurant/RestaurantUserStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('RestaurantUserStore', () => {
    const adminStoreMock = { is_loading: 0 }
    const notifyMock = vi.fn()
    const notificationStoreMock = { notify: notifyMock }
    const axiosMock = {
        get: vi.fn(),
        put: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())
        adminStoreMock.is_loading = 0
        notifyMock.mockReset()
        axiosMock.get.mockReset()
        axiosMock.put.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)

        globalThis.axios = axiosMock as never
    })

    it('loads paginated lunch users with the active search string', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [
                    {
                        id: 7,
                        first_name: 'Anna',
                        last_name: 'Mittag',
                        email: 'anna@example.test',
                    },
                ],
                meta: {
                    current_page: 2,
                    last_page: 3,
                    total: 21,
                    from: 11,
                    to: 20,
                },
            },
        })

        const store = useRestaurantUserStore()
        store.search_string = 'Anna'
        store.only_pending_confirmation = true

        const result = await store.index(2)

        expect(result).toBe(true)
        expect(store.users).toHaveLength(1)
        expect(store.meta.total).toBe(21)
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/restaurant/users', {
            params: {
                search_string: 'Anna',
                only_pending_confirmation: 1,
                page: 2,
            },
        })
    })

    it('updates the SEPA status for a restaurant user locally', async () => {
        axiosMock.put.mockResolvedValue({
            data: {
                data: {
                    id: 7,
                    first_name: 'Anna',
                    last_name: 'Mittag',
                    email: 'anna@example.test',
                    has_sepa: false,
                },
            },
        })

        const store = useRestaurantUserStore()
        store.users = [
            {
                id: 7,
                first_name: 'Anna',
                last_name: 'Mittag',
                email: 'anna@example.test',
                has_sepa: true,
            },
        ]

        const result = await store.updateSepa(7, false)

        expect(result).toEqual({
            id: 7,
            first_name: 'Anna',
            last_name: 'Mittag',
            email: 'anna@example.test',
            has_sepa: false,
        })
        expect(store.users[0].has_sepa).toBe(false)
        expect(axiosMock.put).toHaveBeenCalledWith('/api/admin/restaurant/users/7/sepa', {
            data: {
                has_sepa: false,
            },
        })
    })
})
