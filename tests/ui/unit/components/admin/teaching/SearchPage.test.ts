import { beforeEach, describe, expect, it, vi } from 'vitest'
import Search from '@/pages/admin/teaching/search/Search.vue'

const notificationNotifyMock = vi.fn()

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: () => ({
        notify: notificationNotifyMock,
    }),
}))

describe('Teaching search page', () => {
    beforeEach(() => {
        notificationNotifyMock.mockReset()
        vi.unstubAllGlobals()
    })

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

    it('renders copy actions for all displayed email fields', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/search/Search.vue', 'utf8')
        )

        expect(source).toContain('@click.stop="copyEmailToClipboard(item.email)"')
        expect(source).toContain('@click.stop="copyEmailToClipboard(item.mother_email)"')
        expect(source).toContain('@click.stop="copyEmailToClipboard(item.father_email)"')
        expect(source).toContain("message: copied ? 'E-Mail wurde in die Zwischenablage kopiert.' : 'E-Mail konnte nicht kopiert werden.'")
    })

    it('copies email text and shows a success notification', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined)
        vi.stubGlobal('navigator', {
            clipboard: {
                writeText,
            },
        })

        const ctx = {
            copyTextToClipboard: (Search as any).methods.copyTextToClipboard,
        }

        await (Search as any).methods.copyEmailToClipboard.call(ctx, '  student@example.com  ')

        expect(writeText).toHaveBeenCalledWith('student@example.com')
        expect(notificationNotifyMock).toHaveBeenCalledWith({
            message: 'E-Mail wurde in die Zwischenablage kopiert.',
            type: 'success',
            timeout: 2200,
        })
    })
})
