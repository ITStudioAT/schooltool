import { describe, expect, it, vi } from 'vitest'
import Search from '@/pages/admin/teaching/search/Search.vue'

describe('Teaching search page', () => {
    it('builds result summary from current results', () => {
        expect((Search as any).computed.resultSummary.call({ import116: [] })).toBe('Keine Treffer')
        expect((Search as any).computed.resultSummary.call({ import116: [{ id: 1 }] })).toBe('1 Treffer')
        expect((Search as any).computed.resultSummary.call({ import116: [{ id: 1 }, { id: 2 }, { id: 3 }] })).toBe('3 Treffer')
    })

    it('builds search label from query string', () => {
        expect((Search as any).computed.searchMetaLabel.call({ search_string: '' })).toBe('Ohne Filter')
        expect((Search as any).computed.searchMetaLabel.call({ search_string: '  3B  ' })).toBe('Filter: "3B"')
    })

    it('triggers search and clear-search actions', () => {
        const search116 = vi.fn()
        const ctx: Record<string, unknown> = {
            teachingStore: { search116 },
            search_string: 'Meier',
            selected_import116: [11, 22],
        }
        ctx.search = function () {
            return (Search as any).methods.search.call(ctx)
        }

        ;(Search as any).methods.search.call(ctx)
        expect(search116).toHaveBeenCalledTimes(1)
        expect(ctx.selected_import116).toEqual([])

        ctx.selected_import116 = [33]
        ;(Search as any).methods.clearSearch.call(ctx)
        expect(ctx.search_string).toBe('')
        expect(ctx.selected_import116).toEqual([])
        expect(search116).toHaveBeenCalledTimes(2)
        expect(search116).toHaveBeenLastCalledWith(1)
    })
})
