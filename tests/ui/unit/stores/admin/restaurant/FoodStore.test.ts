import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useFoodStore } from '@/stores/admin/restaurant/FoodStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('FoodStore', () => {
    const adminStoreMock = { is_loading: 0 }
    const notifyMock = vi.fn()
    const notificationStoreMock = { notify: notifyMock }
    const axiosMock = {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())
        adminStoreMock.is_loading = 0
        notifyMock.mockReset()
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()
        axiosMock.delete.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)

        globalThis.axios = axiosMock as never
    })

    it('loads food list', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [{ id: 1, title: 'Lasagne', category: { title: 'Hauptspeise' } }],
            },
        })

        const store = useFoodStore()
        const result = await store.index()

        expect(result).toBe(true)
        expect(store.foods).toHaveLength(1)
        expect(store.foods[0].title).toBe('Lasagne')
    })

    it('stores new food and keeps foods sorted', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: { id: 5, title: 'Apfelstrudel', category: { title: 'Nachspeise' } },
            },
        })

        const store = useFoodStore()
        store.foods = [{ id: 1, title: 'Zander' }] as never

        const result = await store.store({ title: 'Apfelstrudel' })

        expect(result?.title).toBe('Apfelstrudel')
        expect(store.foods.map((food) => food.title)).toEqual(['Apfelstrudel', 'Zander'])
    })

    it('removes deleted food from state', async () => {
        axiosMock.delete.mockResolvedValue({})

        const store = useFoodStore()
        store.foods = [
            { id: 1, title: 'Suppe' },
            { id: 2, title: 'Kuchen' },
        ] as never

        const result = await store.destroy(1)

        expect(result).toBe(true)
        expect(store.foods.map((food) => food.id)).toEqual([2])
    })
})
