import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useCourseStudentCategoryEvaluationStore } from '@/stores/admin/teaching/CourseStudentCategoryEvaluationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('CourseStudentCategoryEvaluationStore', () => {
    const adminStoreMock = { is_loading: 0 }
    const notifyMock = vi.fn()
    const notificationStoreMock = { notify: notifyMock }
    const axiosMock = {
        get: vi.fn(),
        post: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())

        adminStoreMock.is_loading = 0
        notifyMock.mockReset()
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)
        globalThis.axios = axiosMock as never
    })

    it('indexByCourse loads evaluations for course and semester', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [{ id: 3, user_id: 9, category_name: 'Projekt', value: 'Bestanden' }],
            },
        })

        const store = useCourseStudentCategoryEvaluationStore()
        const result = await store.indexByCourse(55, 2)

        expect(result).toBe(true)
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/teaching/course_student_category_evaluations', {
            params: { course_id: 55, semester: 2, user_id: null },
        })
        expect(store.evaluations).toEqual([{ id: 3, user_id: 9, category_name: 'Projekt', value: 'Bestanden' }])
    })

    it('store upserts an evaluation in local state', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: {
                    id: 8,
                    teaching_course_id: 12,
                    user_id: 9,
                    semester: 1,
                    category_name: 'Projekt',
                    value: '1',
                },
            },
        })

        const store = useCourseStudentCategoryEvaluationStore()
        store.evaluations = [{
            id: 4,
            teaching_course_id: 12,
            user_id: 9,
            semester: 1,
            category_name: 'Projekt',
            value: 'Offen',
        }] as never

        const result = await store.store({
            teaching_course_id: 12,
            user_id: 9,
            semester: 1,
            category_name: 'Projekt',
            value: '1',
        } as never)

        expect(result).toEqual({
            data: {
                id: 8,
                teaching_course_id: 12,
                user_id: 9,
                semester: 1,
                category_name: 'Projekt',
                value: '1',
            },
        })
        expect(store.evaluations).toEqual([{
            id: 8,
            teaching_course_id: 12,
            user_id: 9,
            semester: 1,
            category_name: 'Projekt',
            value: '1',
        }])
    })
})
