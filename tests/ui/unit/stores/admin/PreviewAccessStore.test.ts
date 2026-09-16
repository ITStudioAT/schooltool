import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { usePreviewAccessStore } from '@/stores/admin/PreviewAccessStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: () => ({ notify: vi.fn() }),
}))

const initialData = {
    enabled: false,
    users: [{ id: 7, allowed: false, eligible: true }],
}

describe('Preview access management store', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        globalThis.axios = { get: vi.fn(), put: vi.fn() } as never
        vi.spyOn(useAdminStore(), 'loadConfig').mockResolvedValue({})
    })

    it('loads the authoritative preview settings', async () => {
        const store = usePreviewAccessStore()
        vi.mocked(globalThis.axios.get).mockResolvedValue({ data: { data: initialData } })

        expect(await store.load()).toBe(true)
        expect(globalThis.axios.get).toHaveBeenCalledWith('/api/admin/feature-preview')
        expect(store.data).toEqual(initialData)
        expect(store.is_loading).toBe(false)
    })

    it.each([
        ['settings', '/api/admin/feature-preview/settings', { enabled: true }],
        ['user', '/api/admin/feature-preview/users/7', { allowed: true }],
    ])('saves %s and applies the server response', async (operation, url, payload) => {
        const store = usePreviewAccessStore()
        store.data = initialData
        const savedData = { enabled: true, users: [{ id: 7, allowed: true, eligible: true }] }
        vi.mocked(globalThis.axios.put).mockResolvedValue({ data: { data: savedData } })

        const saved = operation === 'settings' ? await store.saveEnabled(true) : await store.saveUser(7, true)

        expect(saved).toBe(true)
        expect(globalThis.axios.put).toHaveBeenCalledWith(url, payload)
        expect(store.data).toEqual(savedData)
        expect(store.is_saving).toBe(false)
        expect(useAdminStore().loadConfig).toHaveBeenCalledOnce()
    })

    it('keeps existing permissions after a failed save and allows a retry', async () => {
        const store = usePreviewAccessStore()
        store.data = initialData
        vi.mocked(globalThis.axios.put).mockRejectedValue({ response: { data: { message: 'Kein Zugriff.' } } })

        expect(await store.saveUser(7, true)).toBe(false)
        expect(store.data).toEqual(initialData)
        expect(store.error).toBe('Kein Zugriff.')
        expect(store.is_saving).toBe(false)
        expect(useAdminStore().loadConfig).not.toHaveBeenCalled()
    })

    it('does not send concurrent permission changes', async () => {
        const store = usePreviewAccessStore()
        store.is_saving = true

        expect(await store.saveUser(7, true)).toBe(false)
        expect(globalThis.axios.put).not.toHaveBeenCalled()
    })

    it('keeps a successful save when refreshing the navigation fails', async () => {
        const store = usePreviewAccessStore()
        const savedData = { ...initialData, enabled: true }
        vi.mocked(globalThis.axios.put).mockResolvedValue({ data: { data: savedData } })
        vi.mocked(useAdminStore().loadConfig).mockRejectedValue(new Error('offline'))

        expect(await store.saveEnabled(true)).toBe(true)
        expect(store.data).toEqual(savedData)
        expect(store.error).toContain('ist gespeichert')
        expect(store.is_saving).toBe(false)
    })

    it('requests a reload when navigation refresh reports an HTTP failure after saving', async () => {
        const store = usePreviewAccessStore()
        const savedData = { ...initialData, enabled: true }
        vi.mocked(globalThis.axios.put).mockResolvedValue({ data: { data: savedData } })
        vi.mocked(useAdminStore().loadConfig).mockResolvedValue(false)

        expect(await store.saveEnabled(true)).toBe(true)
        expect(store.data).toEqual(savedData)
        expect(store.error).toContain('Bitte laden Sie die Seite neu')
        expect(store.is_saving).toBe(false)
    })

    it('reports network loading failures without losing the previous settings', async () => {
        const store = usePreviewAccessStore()
        store.data = initialData
        vi.mocked(globalThis.axios.get).mockRejectedValue(new Error('offline'))

        expect(await store.load()).toBe(false)
        expect(store.error).toContain('nicht geladen')
        expect(store.data).toEqual(initialData)
        expect(store.is_loading).toBe(false)
    })
})
