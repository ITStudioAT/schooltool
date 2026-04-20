import { beforeEach, describe, expect, it, vi } from 'vitest'
import Curricula from '@/pages/admin/teaching/curricula/Curricula.vue'
import { useCurriculumStore } from '@/stores/admin/teaching/CurriculumStore'

vi.mock('@/stores/admin/teaching/CurriculumStore', () => ({
    useCurriculumStore: vi.fn(),
}))

describe('Teaching curricula route sync', () => {
    beforeEach(() => {
        vi.mocked(useCurriculumStore).mockReset()
    })

    it('stores the selected curriculum in the URL query', () => {
        const routerReplace = vi.fn().mockResolvedValue(undefined)
        const ctx = {
            selectedCurriculum: null,
            $route: {
                query: {
                    page: '2',
                    search: 'deutsch',
                },
            },
            $router: {
                replace: routerReplace,
            },
            setCurriculumQuery(curriculumId: number | null) {
                return (Curricula as any).methods.setCurriculumQuery.call(this, curriculumId)
            },
        }

        ;(Curricula as any).methods.openCurriculum.call(ctx, {
            id: 15,
            title: 'Deutsch',
        })

        expect(ctx.selectedCurriculum).toEqual({
            id: 15,
            title: 'Deutsch',
        })
        expect(routerReplace).toHaveBeenCalledWith({
            query: {
                page: '2',
                search: 'deutsch',
                curriculum: '15',
            },
        })
    })

    it('removes the selected curriculum from the URL query when closing', () => {
        const routerReplace = vi.fn().mockResolvedValue(undefined)
        const ctx = {
            selectedCurriculum: {
                id: 15,
                title: 'Deutsch',
            },
            $route: {
                query: {
                    page: '2',
                    curriculum: '15',
                },
            },
            $router: {
                replace: routerReplace,
            },
            setCurriculumQuery(curriculumId: number | null) {
                return (Curricula as any).methods.setCurriculumQuery.call(this, curriculumId)
            },
        }

        ;(Curricula as any).methods.closeCurriculum.call(ctx)

        expect(ctx.selectedCurriculum).toBeNull()
        expect(routerReplace).toHaveBeenCalledWith({
            query: {
                page: '2',
            },
        })
    })

    it('restores the selected curriculum from the route query', async () => {
        const showMock = vi.fn().mockResolvedValue({
            id: 15,
            title: 'Deutsch',
        })
        const ctx = {
            curriculumStore: {
                show: showMock,
            },
            selectedCurriculum: null,
            isResolvingCurriculum: false,
            routeSyncToken: 0,
            setCurriculumQuery: vi.fn(),
        }

        await (Curricula as any).methods.syncSelectedCurriculumFromRoute.call(ctx, '15')

        expect(showMock).toHaveBeenCalledWith(15)
        expect(ctx.selectedCurriculum).toEqual({
            id: 15,
            title: 'Deutsch',
        })
        expect(ctx.isResolvingCurriculum).toBe(false)
        expect(ctx.setCurriculumQuery).not.toHaveBeenCalled()
    })

    it('clears an invalid curriculum query when the curriculum cannot be loaded', async () => {
        const setCurriculumQueryMock = vi.fn()
        const ctx = {
            curriculumStore: {
                show: vi.fn().mockResolvedValue(null),
            },
            selectedCurriculum: {
                id: 15,
                title: 'Deutsch',
            },
            isResolvingCurriculum: false,
            routeSyncToken: 0,
            setCurriculumQuery: setCurriculumQueryMock,
        }

        await (Curricula as any).methods.syncSelectedCurriculumFromRoute.call(ctx, '99')

        expect(ctx.selectedCurriculum).toBeNull()
        expect(setCurriculumQueryMock).toHaveBeenCalledWith(null)
        expect(ctx.isResolvingCurriculum).toBe(false)
    })

    it('returns from print to the selected curriculum without clearing it', () => {
        const routerReplace = vi.fn().mockResolvedValue(undefined)
        const ctx = {
            sub_action: 'print',
            selectedCurriculum: {
                id: 15,
                title: 'Deutsch',
            },
            $route: {
                query: {
                    curriculum: '15',
                    view: 'print',
                    page: '2',
                },
            },
            $router: {
                replace: routerReplace,
            },
            setViewQuery(view: string | null) {
                return (Curricula as any).methods.setViewQuery.call(this, view)
            },
        }

        ;(Curricula as any).methods.returnFromPrint.call(ctx)

        expect(ctx.sub_action).toBe('overview')
        expect(ctx.selectedCurriculum).toEqual({
            id: 15,
            title: 'Deutsch',
        })
        expect(routerReplace).toHaveBeenCalledWith({
            query: {
                curriculum: '15',
                page: '2',
            },
        })
    })
})
