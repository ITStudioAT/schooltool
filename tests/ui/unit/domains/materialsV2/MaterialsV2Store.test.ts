import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from 'axios'
import { useMaterialsV2Store } from '@/stores/admin/materialsV2/MaterialsV2Store'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
    },
}))

function deferredResponse() {
    let resolve = (_value: unknown) => {}
    const promise = new Promise((promiseResolve) => {
        resolve = promiseResolve
    })

    return {
        promise,
        resolve,
    }
}

describe('MaterialsV2Store', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        vi.clearAllMocks()
    })

    it('loads paginated items into shared page state', async () => {
        vi.mocked(axios.get).mockResolvedValue({
            data: {
                data: [{ id: 17, title: 'Arbeitsblatt' }],
                meta: { total: 1, current_page: 2, last_page: 3 },
            },
        })
        const store = useMaterialsV2Store()

        await store.loadItems({
            search: ' Arbeit ',
            category: 'Biologie',
            page: 2,
        })

        expect(axios.get).toHaveBeenCalledWith('/api/admin/materials-v2/items', {
            params: {
                search: 'Arbeit',
                category: 'Biologie',
                page: 2,
                per_page: 18,
            },
        })
        expect(store.items).toEqual([{ id: 17, title: 'Arbeitsblatt' }])
        expect(store.meta.last_page).toBe(3)
        expect(store.loadError).toBe('')
    })

    it('loads all calendar pages into one visible range', async () => {
        vi.mocked(axios.get)
            .mockResolvedValueOnce({
                data: {
                    data: [{ id: 1 }],
                    meta: { total: 2, current_page: 1, last_page: 2 },
                },
            })
            .mockResolvedValueOnce({
                data: {
                    data: [{ id: 2 }],
                    meta: { total: 2, current_page: 2, last_page: 2 },
                },
            })
        const store = useMaterialsV2Store()

        await store.loadItems({
            category: 'Termine',
            calendarRange: {
                start: '2026-09-01',
                end: '2026-09-30',
            },
            reminderCategory: 'Termine',
        })

        expect(store.items.map((item) => item.id)).toEqual([1, 2])
        expect(store.meta).toEqual({ total: 2, current_page: 1, last_page: 1 })
    })

    it('keeps the latest item response when concurrent requests resolve out of order', async () => {
        const olderResponse = deferredResponse()
        const newerResponse = deferredResponse()
        vi.mocked(axios.get)
            .mockReturnValueOnce(olderResponse.promise)
            .mockReturnValueOnce(newerResponse.promise)
        const store = useMaterialsV2Store()

        const olderRequest = store.loadItems({ search: 'alt' })
        const newerRequest = store.loadItems({ search: 'neu' })

        newerResponse.resolve({
            data: {
                data: [{ id: 2, title: 'Neues Ergebnis' }],
                meta: { total: 1, current_page: 1, last_page: 1 },
            },
        })
        await newerRequest

        olderResponse.resolve({
            data: {
                data: [{ id: 1, title: 'Altes Ergebnis' }],
                meta: { total: 1, current_page: 1, last_page: 1 },
            },
        })
        await olderRequest

        expect(store.items).toEqual([{ id: 2, title: 'Neues Ergebnis' }])
        expect(store.loading).toBe(false)
        expect(store.loadError).toBe('')
    })

    it('clears stale items and defines the error state when the current request fails', async () => {
        vi.mocked(axios.get)
            .mockResolvedValueOnce({
                data: {
                    data: [{ id: 1, title: 'Nicht mehr aktuell' }],
                    meta: { total: 1, current_page: 1, last_page: 1 },
                },
            })
            .mockRejectedValueOnce({
                response: {
                    data: {
                        message: 'Suche nicht verfügbar.',
                    },
                },
            })
        const store = useMaterialsV2Store()

        await store.loadItems()
        await store.loadItems({ search: 'neu' })

        expect(store.items).toEqual([])
        expect(store.meta).toEqual({ total: 0, current_page: 1, last_page: 1 })
        expect(store.loadError).toBe('Suche nicht verfügbar.')
        expect(store.loading).toBe(false)
    })

    it('invalidates pending requests and clears page-scoped state on reset', async () => {
        const pendingResponse = deferredResponse()
        vi.mocked(axios.get).mockReturnValueOnce(pendingResponse.promise)
        const store = useMaterialsV2Store()
        store.items = [{ id: 1, title: 'Vorheriges Ergebnis' }]

        const pendingRequest = store.loadItems()
        store.resetPage()
        pendingResponse.resolve({
            data: {
                data: [{ id: 2, title: 'Verspätetes Ergebnis' }],
                meta: { total: 1, current_page: 1, last_page: 1 },
            },
        })
        await pendingRequest

        expect(store.items).toEqual([])
        expect(store.categoryDetails).toEqual([])
        expect(store.meta).toEqual({ total: 0, current_page: 1, last_page: 1 })
        expect(store.loading).toBe(false)
        expect(store.loadError).toBe('')
    })
})
