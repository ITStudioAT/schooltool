import { describe, expect, it } from 'vitest'
import router from '../../../../resources/routes/admin.js'

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
})
