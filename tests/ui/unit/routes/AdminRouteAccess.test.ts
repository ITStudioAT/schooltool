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

    it('maps students timetable v2 routes to the students timetables capability', () => {
        expect(resolveAdminRouteAccess('/admin/students-timetables/timetable-v2/overview')).toEqual({
            public: false,
            capability: 'students_timetables',
        })
    })

    it('maps the independent materials v2 route to its own capability', () => {
        expect(resolveAdminRouteAccess('/admin/materials-v2')).toEqual({
            public: false,
            capability: 'materials_v2',
        })
    })

    it('treats removed aba legacy routes as unknown', () => {
        expect(resolveAdminRouteAccess('/admin/aba/results/1')).toBeNull()
        expect(resolveAdminRouteAccess('/admin/aba/ai-settings/seed-report/review')).toBeNull()
    })

    it('maps aba detail routes to the aba capability', () => {
        expect(resolveAdminRouteAccess('/admin/aba/details/42')).toEqual({
            public: false,
            capability: 'aba',
        })
    })

    it('normalizes trailing slashes for known admin routes', () => {
        expect(resolveAdminRouteAccess('/admin/')).toEqual({
            public: false,
            capability: 'home',
        })
    })

    it('returns null for unknown admin routes', () => {
        expect(resolveAdminRouteAccess('/admin/unknown')).toBeNull()
    })

    it('protects the standalone groups page with its dedicated capability', () => {
        expect(resolveAdminRouteAccess('/admin/groups')).toEqual({
            public: false,
            capability: 'groups',
        })
    })

    it('treats the legacy super admin route as a redirect without capability guard', () => {
        expect(resolveAdminRouteAccess('/admin/super_admin/teachers')).toEqual({
            public: false,
            capability: null,
        })
    })
})
