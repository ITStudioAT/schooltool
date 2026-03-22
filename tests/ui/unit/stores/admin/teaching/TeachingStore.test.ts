import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('TeachingStore', () => {
    const notifyMock = vi.fn()
    const adminStoreMock = {
        is_loading: 0,
        config: { user: { teaching_active_semester: 1, teaching_count_for_semester_2_date: null } },
    }
    const notificationStoreMock = { notify: notifyMock }
    const axiosMock = {
        get: vi.fn(),
        post: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())

        notifyMock.mockReset()
        adminStoreMock.is_loading = 0
        adminStoreMock.config.user.teaching_active_semester = 1
        adminStoreMock.config.user.teaching_count_for_semester_2_date = null
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)
        globalThis.axios = axiosMock as never
    })

    it('loadSettings stores settings and exposes schema getters', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                settings: {
                    teaching_schemas: [{
                        id: 'schema-1',
                        works: [{ short_name: 'MA' }],
                        grading: {
                            semester_count: 2,
                            category_evaluation_values: [
                                { value: 'Offen', color: '#fb8c00' },
                                { value: 'Bestanden', color: '#43a047' },
                            ],
                            default_category_evaluation_value: 'Bestanden',
                        },
                    }],
                    teaching_behaviour: [{ short_name: 'BZ' }],
                    teaching_notifications: [{ short_name: 'INF' }],
                },
            },
        })

        const store = useTeachingStore()
        const result = await store.loadSettings()

        expect(result).toBe(true)
        expect(store.schemas).toHaveLength(1)
        expect(store.schemaById('schema-1')?.id).toBe('schema-1')
        expect(store.worksForSchema('schema-1')).toEqual([{ short_name: 'MA' }])
        expect(store.gradingForSchema('schema-1')).toEqual({
            semester_count: 2,
            category_evaluation_values: [
                { value: 'Offen', color: '#fb8c00' },
                { value: 'Bestanden', color: '#43a047' },
            ],
            default_category_evaluation_value: 'Bestanden',
        })
        expect(store.categoryEvaluationValueItemsForSchema('schema-1')).toEqual([
            { value: 'Offen', color: '#fb8c00' },
            { value: 'Bestanden', color: '#43a047' },
        ])
        expect(store.categoryEvaluationValuesForSchema('schema-1')).toEqual(['Offen', 'Bestanden'])
        expect(store.categoryEvaluationValueColorForSchema('schema-1', 'Bestanden')).toBe('#43a047')
        expect(store.defaultCategoryEvaluationValueForSchema('schema-1')).toBe('Bestanden')
        expect(store.hasTwoSemesters).toBe(true)
    })

    it('loadSettings returns false, notifies, and resets loading when request fails', async () => {
        axiosMock.get.mockRejectedValue({
            response: {
                status: 500,
                data: { message: 'Server error' },
            },
        })

        const store = useTeachingStore()
        const result = await store.loadSettings()

        expect(result).toBe(false)
        expect(store.settings).toBeNull()
        expect(adminStoreMock.is_loading).toBe(0)
        expect(notifyMock).toHaveBeenCalledWith({
            status: 500,
            message: 'Server error',
            type: 'error',
            timeout: 3000,
        })
    })

    it('search116 supports retry: first failure then successful reload', async () => {
        axiosMock.get
            .mockRejectedValueOnce({
                response: {
                    status: 422,
                    data: { message: 'Ungueltige Suche' },
                },
            })
            .mockResolvedValueOnce({
                data: {
                    data: [{ id: 15, last_name: 'Meier' }],
                    meta: { current_page: 2, total: 1 },
                },
            })

        const store = useTeachingStore()
        ;(store as any).search_string = 'Meier'

        const firstAttempt = await store.search116()
        expect(firstAttempt).toBe(false)
        expect(notifyMock).toHaveBeenCalledWith({
            status: 422,
            message: 'Ungueltige Suche',
            type: 'error',
            timeout: 3000,
        })
        expect(adminStoreMock.is_loading).toBe(0)

        const secondAttempt = await store.search116(2)
        expect(secondAttempt).toBe(true)
        expect(store.import116).toEqual([{ id: 15, last_name: 'Meier' }])
        expect(store.meta).toEqual({ current_page: 2, total: 1 })
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('saveActiveSemester posts payload and updates admin config user', async () => {
        axiosMock.post.mockResolvedValue({ data: {} })

        const store = useTeachingStore()
        await store.saveActiveSemester(3)

        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/teaching/save_active_semester', {
            teaching_active_semester: 3,
        })
        expect(adminStoreMock.config.user.teaching_active_semester).toBe(3)
    })

    it('saveActiveSemester keeps current value and notifies on backend validation error', async () => {
        axiosMock.post.mockRejectedValue({
            response: {
                status: 422,
                data: { message: 'Ungültiges Semester.' },
            },
        })

        const store = useTeachingStore()
        adminStoreMock.config.user.teaching_active_semester = 2

        await store.saveActiveSemester(9)

        expect(adminStoreMock.config.user.teaching_active_semester).toBe(2)
        expect(notifyMock).toHaveBeenCalledWith({
            status: 422,
            message: 'Ungültiges Semester.',
            type: 'error',
            timeout: 3000,
        })
    })

    it('saveSemester2Date stores returned date and sends success notification', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                teaching_count_for_semester_2_date: '2026-02-15',
            },
        })

        const store = useTeachingStore()
        await store.saveSemester2Date('2026-02-15')

        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/teaching/save_semester_2_date', {
            teaching_count_for_semester_2_date: '2026-02-15',
        })
        expect(adminStoreMock.config.user.teaching_count_for_semester_2_date).toBe('2026-02-15')
        expect(notifyMock).toHaveBeenCalledWith({
            message: 'Datum gespeichert.',
            type: 'success',
            timeout: 2000,
        })
    })

    it('saveSemester2Date keeps existing value and notifies on backend error', async () => {
        axiosMock.post.mockRejectedValue({
            response: {
                status: 409,
                data: { message: 'Datum nicht erlaubt.' },
            },
        })

        const store = useTeachingStore()
        adminStoreMock.config.user.teaching_count_for_semester_2_date = '2026-02-10'

        await store.saveSemester2Date('2026-03-10')

        expect(adminStoreMock.config.user.teaching_count_for_semester_2_date).toBe('2026-02-10')
        expect(notifyMock).toHaveBeenCalledWith({
            status: 409,
            message: 'Datum nicht erlaubt.',
            type: 'error',
            timeout: 3000,
        })
        expect(notifyMock).not.toHaveBeenCalledWith(expect.objectContaining({ type: 'success' }))
    })

    it('saveSettings updates state on success and returns false with error notification on backend rejection', async () => {
        const initialSettings = {
            teaching_schemas: [{ id: 'old', name: 'Alt', works: [], grading: {} }],
            teaching_behaviour: [],
            teaching_notifications: [],
        }
        const savedSettings = {
            teaching_schemas: [{ id: 'new', name: 'Neu', works: [], grading: {} }],
            teaching_behaviour: [{ short_name: 'BZ', name: 'Benehmen' }],
            teaching_notifications: [],
        }

        axiosMock.post
            .mockResolvedValueOnce({ data: { settings: savedSettings } })
            .mockRejectedValueOnce({
                response: {
                    status: 409,
                    data: { message: 'Schema conflict' },
                },
            })

        const store = useTeachingStore()
        ;(store as any).settings = initialSettings

        const success = await store.saveSettings({ teaching_schemas: savedSettings.teaching_schemas })
        expect(success).toBe(true)
        expect(store.settings).toEqual(savedSettings)
        expect(notifyMock).toHaveBeenCalledWith({
            message: 'Einstellungen gespeichert.',
            type: 'success',
            timeout: 2000,
        })
        expect(adminStoreMock.is_loading).toBe(0)

        const failed = await store.saveSettings({ teaching_schemas: [] })
        expect(failed).toBe(false)
        expect(store.settings).toEqual(savedSettings)
        expect(notifyMock).toHaveBeenCalledWith({
            status: 409,
            message: 'Schema conflict',
            type: 'error',
            timeout: 3000,
        })
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('search116 handles network errors without response payload gracefully', async () => {
        axiosMock.get.mockRejectedValueOnce(new Error('Network down'))

        const store = useTeachingStore()
        ;(store as any).search_string = 'A'

        const result = await store.search116()
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
