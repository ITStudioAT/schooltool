import { describe, expect, it } from 'vitest'
import { resolveAdminRouteAccess } from '../../../../resources/routes/admin.js'

describe('admin route access metadata', () => {
    it('marks public auth routes as public', () => {
        expect(resolveAdminRouteAccess('/admin/login')).toEqual({
            public: true,
            capability: null,
        })
    })

    it('maps teaching subroutes to the teaching capability', () => {
        expect(resolveAdminRouteAccess('/admin/teaching/settings')).toEqual({
            public: false,
            capability: 'teaching',
        })
    })

    it('maps aba nested routes to the aba capability', () => {
        expect(resolveAdminRouteAccess('/admin/aba/ai-settings/seed-report/review')).toEqual({
            public: false,
            capability: 'aba',
        })
    })

    it('returns null for unknown admin routes', () => {
        expect(resolveAdminRouteAccess('/admin/unknown')).toBeNull()
    })
})
