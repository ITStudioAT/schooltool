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

    it('does not expose curriculum free-week settings', () => {
        const store = useCurriculumStore()

        expect(store).not.toHaveProperty('free_weeks_template')
        expect(store).not.toHaveProperty('loadFreeWeeksTemplate')
        expect(store).not.toHaveProperty('saveFreeWeeksTemplate')
    })

    it('loads curricula without a search parameter', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [],
                meta: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 20,
                    total: 0,
                },
            },
        })

        const store = useCurriculumStore()

        await store.index({ page: 1 })

        expect(store).not.toHaveProperty('search')
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/teaching/curricula', {
            params: {
                page: 1,
                per_page: 20,
            },
        })
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

    it('loads imported curricula', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [
                    {
                        id: 21,
                        title: 'Importiertes Curriculum',
                    },
                ],
            },
        })

        const store = useCurriculumStore()
        const importedCurricula = await store.loadImportedCurricula()

        expect(importedCurricula).toEqual([
            {
                id: 21,
                title: 'Importiertes Curriculum',
            },
        ])
        expect(store.imported_curricula).toEqual([
            {
                id: 21,
                title: 'Importiertes Curriculum',
            },
        ])
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/teaching/imported-curricula')
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('imports a curriculum file and refreshes imported curricula', async () => {
        const formDataAppend = vi.fn()
        const originalFormData = globalThis.FormData
        class FormDataMock {
            append = formDataAppend
        }

        globalThis.FormData = FormDataMock as never

        axiosMock.post.mockResolvedValue({
            data: {
                data: {
                    id: 22,
                    title: 'Importiert',
                },
            },
        })
        axiosMock.get.mockResolvedValue({
            data: {
                data: [
                    {
                        id: 22,
                        title: 'Importiert',
                    },
                ],
            },
        })

        const store = useCurriculumStore()
        const file = { name: 'curriculum.json' }
        const importedCurriculum = await store.importCurriculum(file)

        expect(importedCurriculum).toEqual({
            id: 22,
            title: 'Importiert',
        })
        expect(formDataAppend).toHaveBeenCalledWith('file', file)
        expect(axiosMock.post).toHaveBeenCalledWith(
            '/api/admin/teaching/imported-curricula/import',
            expect.objectContaining({
                append: expect.any(Function),
            })
        )
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/teaching/imported-curricula')
        expect(store.imported_curricula).toEqual([
            {
                id: 22,
                title: 'Importiert',
            },
        ])
        expect(notifyMock).toHaveBeenCalledWith({
            message: 'Curriculum wurde importiert.',
            type: 'success',
            timeout: 2200,
        })

        globalThis.FormData = originalFormData
    })

    it('adopts an imported curriculum as a personal curriculum', async () => {
        const existingImportedCurriculum = {
            id: 31,
            title: 'Importiertes Curriculum',
            adopted_curriculum_id: null,
        }
        axiosMock.post.mockResolvedValue({
            data: {
                data: {
                    id: 31,
                    title: 'Eigenes Curriculum',
                },
            },
        })

        const store = useCurriculumStore()
        store.imported_curricula = [existingImportedCurriculum as never]
        const curriculum = await store.adoptImportedCurriculum(31)

        expect(curriculum).toEqual({
            id: 31,
            title: 'Eigenes Curriculum',
        })
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/teaching/imported-curricula/31/adopt')
        expect(store.imported_curricula).toEqual([
            {
                id: 31,
                title: 'Importiertes Curriculum',
                adopted_curriculum_id: 31,
            },
        ])
        expect(notifyMock).toHaveBeenCalledWith({
            message: 'Importiertes Curriculum wurde als eigenes Curriculum übernommen.',
            type: 'success',
            timeout: 2200,
        })
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('deletes an imported curriculum and removes it from the local list', async () => {
        axiosMock.delete.mockResolvedValue({})

        const store = useCurriculumStore()
        store.imported_curricula = [
            { id: 31, title: 'Import A' } as never,
            { id: 32, title: 'Import B' } as never,
        ]

        const ok = await store.destroyImportedCurriculum(31)

        expect(ok).toBe(true)
        expect(axiosMock.delete).toHaveBeenCalledWith('/api/admin/teaching/imported-curricula/31')
        expect(store.imported_curricula).toEqual([
            { id: 32, title: 'Import B' },
        ])
        expect(notifyMock).toHaveBeenCalledWith({
            message: 'Importiertes Curriculum wurde gelöscht.',
            type: 'success',
            timeout: 2200,
        })
        expect(adminStoreMock.is_loading).toBe(0)
    })

})
