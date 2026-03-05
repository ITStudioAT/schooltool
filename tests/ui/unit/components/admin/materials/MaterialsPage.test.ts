import { beforeEach, describe, expect, it, vi } from 'vitest'
import Materials from '@/pages/admin/materials/Materials.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

describe('Materials page navigation', () => {
    beforeEach(() => {
        vi.mocked(useAdminStore).mockReset()
    })

    it('accepts only enabled main actions from route query', () => {
        const method = (Materials as any).methods.applyRouteSelection

        const ctxAllowed: any = {
            $route: { path: '/admin/materials', query: { main_action: 'subjects' } },
            main_action: 'overview',
        }
        method.call(ctxAllowed)
        expect(ctxAllowed.main_action).toBe('subjects')

        const ctxBlocked: any = {
            $route: { path: '/admin/materials', query: { main_action: 'shares' } },
            main_action: 'overview',
        }
        method.call(ctxBlocked)
        expect(ctxBlocked.main_action).toBe('overview')
    })

    it('initializes admin store and applies route selection in beforeMount', () => {
        const adminStoreMock = { is_navigation_locked: false }
        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)

        const ctx: any = {
            $route: { path: '/admin/materials', query: { main_action: 'inbox' } },
            main_action: 'overview',
            isMenuLocked: false,
            applyRouteSelection() {
                return (Materials as any).methods.applyRouteSelection.call(this)
            },
            setMenuLocked(value: boolean) {
                return (Materials as any).methods.setMenuLocked.call(this, value)
            },
        }

        ;(Materials as any).beforeMount.call(ctx)

        expect(ctx.adminStore).toBe(adminStoreMock)
        expect(ctx.main_action).toBe('overview')
        expect(ctx.isMenuLocked).toBe(false)
    })
})

