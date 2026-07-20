import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useStudentStore } from '@/stores/student/StudentStore'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/homepage/HomepageStore', () => ({
    useHomepageStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('StudentStore', () => {
    const homepageStoreMock = { is_loading: 0 }
    const notifyMock = vi.fn()
    const notificationStoreMock = { notify: notifyMock }
    const axiosMock = {
        get: vi.fn(),
        post: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())

        homepageStoreMock.is_loading = 0
        notifyMock.mockReset()
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()

        vi.mocked(useHomepageStore).mockReturnValue(homepageStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)

        globalThis.axios = axiosMock as never
    })

    it('loadConfig stores schools and resets loading counter', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                schools: [{ id: 1, long_name: 'E2E School' }],
                config: { schooltool: { teaching_max_schools_shown: 20 } },
            },
        })

        const store = useStudentStore()
        const ok = await store.loadConfig()

        expect(ok).toBe(true)
        expect(store.schools).toEqual([{ id: 1, long_name: 'E2E School' }])
        expect(store.config?.config?.schooltool?.teaching_max_schools_shown).toBe(20)
        expect(homepageStoreMock.is_loading).toBe(0)
    })

    it('loginStepPassword stores response user and data', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                status: 'login_ok',
                user: { id: 12, email: 'student@example.test' },
            },
        })

        const store = useStudentStore()
        const ok = await store.loginStepPassword({
            email: 'student@example.test',
            password: 'password123',
        })

        expect(ok).toBe(true)
        expect(store.user).toEqual({ id: 12, email: 'student@example.test' })
        expect(store.data).toEqual({
            status: 'login_ok',
            user: { id: 12, email: 'student@example.test' },
        })
    })

    it('loginStepParentStudent stores the selected child as a read-only parent viewer', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                status: 'login_ok',
                viewer_type: 'parent',
                user: { id: 24, email: 'child@example.test' },
            },
        })

        const store = useStudentStore()
        const ok = await store.loginStepParentStudent(77)

        expect(ok).toBe(true)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/homepage/student/login_step_parent_student', {
            student_import_id: 77,
        })
        expect(store.viewer_type).toBe('parent')
        expect(store.user).toEqual({ id: 24, email: 'child@example.test' })
    })

    it('loginStepParentStudent reports a network error instead of throwing', async () => {
        axiosMock.post.mockRejectedValue(new Error('network error'))

        const store = useStudentStore()
        const ok = await store.loginStepParentStudent(77)

        expect(ok).toBe(false)
        expect(notifyMock).toHaveBeenCalledWith(
            expect.objectContaining({
                status: null,
                type: 'error',
                message: 'Der Unterrichtsbereich konnte nicht geöffnet werden.',
            }),
        )
        expect(homepageStoreMock.is_loading).toBe(0)
    })

    it('loadParentStudents reloads the eligible children for the verified parent session', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                status: 'select_student',
                school_id: 5,
                selected_student_import_id: 77,
                students: [{ id: 77, name: 'Anna Adler' }, { id: 88, name: 'Berta Bauer' }],
            },
        })

        const store = useStudentStore()
        const ok = await store.loadParentStudents()

        expect(ok).toBe(true)
        expect(axiosMock.get).toHaveBeenCalledWith('/api/homepage/student/parent_students')
        expect(store.data.selected_student_import_id).toBe(77)
        expect(store.data.students).toHaveLength(2)
        expect(homepageStoreMock.is_loading).toBe(0)
    })

    it('getCurrentUser returns false and clears user on request error', async () => {
        axiosMock.get.mockRejectedValue(new Error('network error'))

        const store = useStudentStore()
        store.user = { id: 99 } as never

        const ok = await store.getCurrentUser()

        expect(ok).toBe(false)
        expect(store.user).toBeNull()
    })

    it('logout resets student session state', async () => {
        axiosMock.post.mockResolvedValue({})

        const store = useStudentStore()
        store.user = { id: 1 } as never
        store.data = { status: 'login_ok' }
        store.viewer_type = 'parent'
        store.school = { id: 22 } as never
        store.selected_school_id = 22

        const ok = await store.logout()

        expect(ok).toBe(true)
        expect(store.user).toBeNull()
        expect(store.data).toEqual({})
        expect(store.viewer_type).toBeNull()
        expect(store.school).toBeNull()
        expect(store.selected_school_id).toBeNull()
    })

    it('changePassword notifies on backend validation error', async () => {
        axiosMock.post.mockRejectedValue({
            response: {
                status: 422,
                data: { message: 'Validation failed' },
            },
        })

        const store = useStudentStore()
        const ok = await store.changePassword('new-password-123', 'different')

        expect(ok).toBe(false)
        expect(notifyMock).toHaveBeenCalledWith(
            expect.objectContaining({
                status: 422,
                type: 'error',
                message: 'Validation failed',
            }),
        )
        expect(homepageStoreMock.is_loading).toBe(0)
    })
})
