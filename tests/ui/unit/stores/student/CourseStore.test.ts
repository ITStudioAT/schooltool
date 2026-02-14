import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useCourseStore } from '@/stores/student/CourseStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('CourseStore', () => {
    const notifyMock = vi.fn()
    const notificationStoreMock = { notify: notifyMock }
    const axiosMock = {
        get: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())

        notifyMock.mockReset()
        axiosMock.get.mockReset()

        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)
        globalThis.axios = axiosMock as never
    })

    it('getCourses loads courses on success', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                courses: [{ id: 1, title: 'Mathematik' }],
            },
        })

        const store = useCourseStore()
        const ok = await store.getCourses()

        expect(ok).toBe(true)
        expect(store.courses).toEqual([{ id: 1, title: 'Mathematik' }])
    })

    it('getCourse handles 404 with user-facing notification', async () => {
        axiosMock.get.mockRejectedValue({
            response: {
                status: 404,
                data: { message: 'Fach nicht gefunden' },
            },
        })

        const store = useCourseStore()
        store.course = { id: 7 } as never

        const ok = await store.getCourse(999)

        expect(ok).toBe(false)
        expect(store.course).toBeNull()
        expect(notifyMock).toHaveBeenCalledWith(
            expect.objectContaining({
                status: 404,
                type: 'error',
                message: 'Fach nicht gefunden',
            }),
        )
    })

    it('getCourseEntries stores entries and type labels', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                entries: [{ id: 10, type: 'TW' }],
                type_labels: { TW: 'Testarbeit' },
            },
        })

        const store = useCourseStore()
        const ok = await store.getCourseEntries(5)

        expect(ok).toBe(true)
        expect(store.entries).toEqual([{ id: 10, type: 'TW' }])
        expect(store.typeLabels).toEqual({ TW: 'Testarbeit' })
    })

    it('getCourseEntries resets state on error', async () => {
        axiosMock.get.mockRejectedValue({
            response: {
                status: 500,
                data: { message: 'Serverfehler' },
            },
        })

        const store = useCourseStore()
        store.entries = [{ id: 1 }] as never
        store.typeLabels = { TW: 'Testarbeit' }

        const ok = await store.getCourseEntries(123)

        expect(ok).toBe(false)
        expect(store.entries).toEqual([])
        expect(store.typeLabels).toEqual({})
        expect(notifyMock).toHaveBeenCalledWith(
            expect.objectContaining({
                status: 500,
                type: 'error',
            }),
        )
    })
})
