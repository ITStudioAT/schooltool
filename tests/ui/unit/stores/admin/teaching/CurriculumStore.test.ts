import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useCurriculumStore } from '@/stores/admin/teaching/CurriculumStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('Admin Teaching CurriculumStore', () => {
    const adminStoreMock: Record<string, any> = { is_loading: 0 }
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
        adminStoreMock.config = undefined
        notifyMock.mockReset()
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()
        axiosMock.put.mockReset()
        axiosMock.delete.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)

        globalThis.axios = axiosMock as never
    })

    it('loads a single curriculum by id', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: {
                    id: 15,
                    title: 'Deutsch',
                },
            },
        })

        const store = useCurriculumStore()
        const curriculum = await store.show(15)

        expect(curriculum).toEqual({
            id: 15,
            title: 'Deutsch',
        })
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/teaching/curricula/15')
        expect(adminStoreMock.is_loading).toBe(0)
        expect(notifyMock).not.toHaveBeenCalled()
    })

    it('returns null and notifies when loading a curriculum fails', async () => {
        axiosMock.get.mockRejectedValue({
            response: {
                status: 404,
                data: {
                    message: 'Nicht gefunden.',
                },
            },
        })

        const store = useCurriculumStore()
        const curriculum = await store.show(99)

        expect(curriculum).toBeNull()
        expect(notifyMock).toHaveBeenCalledWith({
            status: 404,
            message: 'Nicht gefunden.',
            type: 'error',
            timeout: 3000,
        })
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('loads the free weeks template', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: {
                    week_keys: ['2026-09-08', '2027-01-18'],
                    named_ranges: [
                        {
                            title: 'Weihnachtsferien',
                            start_week_key: '2027-01-18',
                            end_week_key: '2027-01-18',
                        },
                    ],
                },
            },
        })

        const store = useCurriculumStore()
        const template = await store.loadFreeWeeksTemplate()

        expect(template).toEqual({
            week_keys: ['2026-09-08', '2027-01-18'],
            named_ranges: [
                {
                    title: 'Weihnachtsferien',
                    start_week_key: '2027-01-18',
                    end_week_key: '2027-01-18',
                },
            ],
        })
        expect(store.free_weeks_template).toEqual({
            week_keys: ['2026-09-08', '2027-01-18'],
            named_ranges: [
                {
                    title: 'Weihnachtsferien',
                    start_week_key: '2027-01-18',
                    end_week_key: '2027-01-18',
                },
            ],
        })
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/teaching/curricula/free-weeks-template')
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('saves the free weeks template and updates admin config', async () => {
        adminStoreMock.config = { user: {} }
        axiosMock.put.mockResolvedValue({
            data: {
                data: {
                    week_keys: ['2026-09-08', '2027-01-18'],
                    named_ranges: [
                        {
                            title: 'Weihnachtsferien',
                            start_week_key: '2027-01-18',
                            end_week_key: '2027-01-18',
                        },
                    ],
                },
            },
        })

        const store = useCurriculumStore()
        const template = await store.saveFreeWeeksTemplate({
            week_keys: ['2026-09-08', '2027-01-18'],
            named_ranges: [
                {
                    title: 'Weihnachtsferien',
                    start_week_key: '2027-01-18',
                    end_week_key: '2027-01-18',
                },
            ],
        })

        expect(template).toEqual({
            week_keys: ['2026-09-08', '2027-01-18'],
            named_ranges: [
                {
                    title: 'Weihnachtsferien',
                    start_week_key: '2027-01-18',
                    end_week_key: '2027-01-18',
                },
            ],
        })
        expect(store.free_weeks_template).toEqual({
            week_keys: ['2026-09-08', '2027-01-18'],
            named_ranges: [
                {
                    title: 'Weihnachtsferien',
                    start_week_key: '2027-01-18',
                    end_week_key: '2027-01-18',
                },
            ],
        })
        expect(axiosMock.put).toHaveBeenCalledWith('/api/admin/teaching/curricula/free-weeks-template', {
            free_weeks_template: {
                week_keys: ['2026-09-08', '2027-01-18'],
                named_ranges: [
                    {
                        title: 'Weihnachtsferien',
                        start_week_key: '2027-01-18',
                        end_week_key: '2027-01-18',
                    },
                ],
            },
        })
        expect(adminStoreMock.config.user.teaching_curriculum_free_weeks_template).toEqual({
            week_keys: ['2026-09-08', '2027-01-18'],
            named_ranges: [
                {
                    title: 'Weihnachtsferien',
                    start_week_key: '2027-01-18',
                    end_week_key: '2027-01-18',
                },
            ],
        })
        expect(notifyMock).toHaveBeenCalledWith({
            message: 'Vorlage gespeichert.',
            type: 'success',
            timeout: 2200,
        })
        expect(adminStoreMock.is_loading).toBe(0)
    })
})
