import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useMaterialCardStore } from '@/stores/admin/materials/MaterialCardStore'

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: () => ({
        notify: vi.fn(),
    }),
}))

describe('Material card store snapshots', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        globalThis.axios = {
            get: vi.fn(),
        } as never
    })

    it('reuses the loaded first page when fetching all card pages for the same filters', async () => {
        const store = useMaterialCardStore()
        store.filters = {
            search: '',
            status: '',
            subject: '',
            topic: '',
            area: '',
            unit: '',
            type: '',
        }
        store.cards = [
            { id: 1, title: 'Seite 1' },
        ]
        store.meta = {
            current_page: 1,
            last_page: 2,
        }

        vi.mocked(globalThis.axios.get).mockResolvedValueOnce({
            data: {
                data: [
                    { id: 2, title: 'Seite 2' },
                ],
                meta: {
                    current_page: 2,
                    last_page: 2,
                },
            },
        } as never)

        const result = await store.fetchAllCardsPages({})

        expect(globalThis.axios.get).toHaveBeenCalledTimes(1)
        expect(globalThis.axios.get).toHaveBeenCalledWith('/api/admin/materials/cards', {
            params: {
                page: 2,
            },
        })
        expect(result.cards.map((card) => card.id)).toEqual([1, 2])
        expect(result.meta).toEqual({
            current_page: 2,
            last_page: 2,
        })
    })
})
