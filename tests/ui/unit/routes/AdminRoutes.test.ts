import { describe, expect, it } from 'vitest'
import router, { routes } from '../../../../resources/routes/admin.js'

describe('admin routes', () => {
    it('registers the restaurant menu plans route', () => {
        const resolvedRoute = router.resolve('/admin/restaurant/menu-plans')

        expect(resolvedRoute.matched).toHaveLength(1)
        expect(resolvedRoute.matched[0]?.path).toBe('/admin/restaurant/:section?')
    })

    it('registers the standalone menu plans route', () => {
        const resolvedRoute = router.resolve('/admin/menu-plans')

        expect(resolvedRoute.matched).toHaveLength(1)
        expect(resolvedRoute.matched[0]?.path).toBe('/admin/menu-plans')
    })

    it('redirects the legacy super admin route to the admin dashboard', () => {
        const legacyRoute = routes.find((route) => route.path === '/admin/super_admin/:section?')

        expect(legacyRoute?.redirect).toBe('/admin')
    })

    it('does not register removed aba legacy routes', () => {
        expect(routes.some((route) => route.path === '/admin/aba/results/:abaId')).toBe(false)
        expect(routes.some((route) => route.path === '/admin/aba/ai-settings')).toBe(false)
    })

    it('registers the aba detail route', () => {
        expect(routes.some((route) => route.path === '/admin/aba/details/:abaId')).toBe(true)
    })
})
