import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useImport116Store } from '@/stores/admin/teaching/Import116Store'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('Import116Store', () => {
    const adminStoreMock = { is_loading: 0 }
    const notifyMock = vi.fn()
    const notificationStoreMock = { notify: notifyMock }
    const axiosMock = {
        get: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())

        adminStoreMock.is_loading = 0
        notifyMock.mockReset()
        axiosMock.get.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)
        globalThis.axios = axiosMock as never
    })

    it('loadClassStudents loads import116 students and classes', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [{ id: 11, class: '2A' }],
                classes: ['2A', '2B'],
            },
        })

        const store = useImport116Store()
        const result = await store.loadClassStudents(['2A'])

        expect(result).toBe(true)
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/teaching/import116/load_class_students', {
            params: { schoolclasses: ['2A'] },
        })
        expect(store.import116_students).toEqual([{ id: 11, class: '2A' }])
        expect(store.classes).toEqual(['2A', '2B'])
    })

    it('loadClassStudents returns false, notifies, and resets loading on backend error', async () => {
        axiosMock.get.mockRejectedValue({
            response: {
                status: 422,
                data: { message: 'Ungültige Klassenfilter.' },
            },
        })

        const store = useImport116Store()
        const result = await store.loadClassStudents(['?'])

        expect(result).toBe(false)
        expect(notifyMock).toHaveBeenCalledWith({
            status: 422,
            message: 'Ungültige Klassenfilter.',
            type: 'error',
            timeout: 3000,
        })
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('loadClassStudents handles network errors without response payload', async () => {
        axiosMock.get.mockRejectedValue(new Error('Network down'))

        const store = useImport116Store()
        const result = await store.loadClassStudents(['2A'])

        expect(result).toBe(false)
        expect(notifyMock).toHaveBeenCalledWith({
            status: undefined,
            message: 'Fehler passiert.',
            type: 'error',
            timeout: 3000,
        })
        expect(adminStoreMock.is_loading).toBe(0)
    })
})
