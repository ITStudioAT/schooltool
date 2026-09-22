import { describe, expect, it } from 'vitest'
import { resolveAdminRouteAccess, routes } from '../../../../../../resources/routes/admin.js'
import Settings from '@/pages/admin/settings/Settings.vue'

describe('removed tutoring administration', () => {
    it('does not resolve the removed admin route while preserving registration administration', () => {
        expect(resolveAdminRouteAccess('/admin/tutoring')).toBeNull()
        expect(routes.some((route) => route.meta?.capability === 'tutoring')).toBe(false)
        expect(resolveAdminRouteAccess('/admin/register_system')).toEqual({
            public: false,
            capability: 'register_system',
        })
    })

    it('keeps general settings without a tutoring tab even for stale capabilities', () => {
        const navigationItems = (Settings as any).computed.navigationItems.call({
            canAccessSuperAdminSettingsTab: true,
            canAccessAdminSettingsTab: true,
            canAccessTutoringSettingsTab: true,
        })

        expect(navigationItems.map((item: { key: string }) => item.key)).toEqual(['super_admin', 'admin'])
        expect((Settings as any).methods.availableTabKeys(true, true, true)).toEqual([
            'super_admin',
            'admin',
            'teaching',
        ])
    })
})
