import { describe, expect, it, vi } from 'vitest'
import SuperAdmin from '@/pages/admin/superAdmin/SuperAdmin.vue'

describe('Super admin page navigation', () => {
    it('shows header only in remaining overview and admin sections', () => {
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: '' })).toBe(true)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'schools' })).toBe(true)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'schoolyears' })).toBe(false)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'roles' })).toBe(false)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'licences' })).toBe(false)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'users' })).toBe(false)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'teachers' })).toBe(true)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'teachers_list' })).toBe(true)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'unknown' })).toBe(false)
    })

    it('uses overview dark background theme only in the remaining requested sections', () => {
        expect((SuperAdmin as any).computed.usesOverviewTheme.call({ main_action: '' })).toBe(true)
        expect((SuperAdmin as any).computed.usesOverviewTheme.call({ main_action: 'schoolyears' })).toBe(false)
        expect((SuperAdmin as any).computed.usesOverviewTheme.call({ main_action: 'roles' })).toBe(false)
        expect((SuperAdmin as any).computed.usesOverviewTheme.call({ main_action: 'licences' })).toBe(false)
        expect((SuperAdmin as any).computed.usesOverviewTheme.call({ main_action: 'users' })).toBe(false)
        expect((SuperAdmin as any).computed.usesOverviewTheme.call({ main_action: 'teachers' })).toBe(true)
        expect((SuperAdmin as any).computed.usesOverviewTheme.call({ main_action: 'teachers_list' })).toBe(true)
    })

    it('builds role-based navigation items for admin role', () => {
        const ctx = {
            config: {
                roles: ['admin'],
            },
            isImpersonating: false,
        }

        const items = (SuperAdmin as any).computed.visibleNavigationItems.call(ctx)

        expect(items.map((item: { key: string }) => item.key)).toEqual(['overview', 'teachers', 'log'])
    })

    it('omits the migrated schoolyears, users, licences, and roles entries from the super admin navigation', () => {
        const ctx = {
            config: {
                roles: ['super_admin'],
            },
            isImpersonating: false,
        }

        const items = (SuperAdmin as any).computed.visibleNavigationItems.call(ctx)

        expect(items.map((item: { key: string }) => item.key)).toEqual([
            'overview',
            'teachers',
            'impersonation',
            'log',
        ])
    })

    it('builds header chips with optional impersonation chip', () => {
        const ctx = {
            config: {
                selected_school: { long_name: 'Christian-Doppler-Gymnasium Salzburg' },
                version: '3.20.19',
            },
            isImpersonating: true,
        }

        const chips = (SuperAdmin as any).computed.headerChips.call(ctx)

        expect(chips).toEqual([
            { key: 'school', text: 'Christian-Doppler-Gymnasium Salzburg', icon: 'mdi-domain' },
            { key: 'version', text: '3.20.19', icon: 'mdi-tag-outline' },
            { key: 'impersonation', text: 'Übernahme aktiv', icon: 'mdi-account-switch', color: 'warning', visible: true },
        ])
    })

    it('marks navigation items as active only for matching targetAction', () => {
        const ctx = {
            main_action: 'teachers',
        }

        const active = (SuperAdmin as any).methods.isNavigationItemActive.call(ctx, { targetAction: 'teachers' })
        const inactive = (SuperAdmin as any).methods.isNavigationItemActive.call(ctx, { action: 'log' })

        expect(active).toBe(true)
        expect(inactive).toBe(false)
    })

    it('keeps the teacher navigation item active for both teacher sub-sections', () => {
        const teachersCtx = {
            main_action: 'teachers',
        }
        const teachersListCtx = {
            main_action: 'teachers_list',
        }

        const teacherActive = (SuperAdmin as any).methods.isNavigationItemActive.call(teachersCtx, { targetAction: 'teachers' })
        const teacherListActive = (SuperAdmin as any).methods.isNavigationItemActive.call(teachersListCtx, { targetAction: 'teachers' })

        expect(teacherActive).toBe(true)
        expect(teacherListActive).toBe(true)
    })

    it('prevents navigation when controls are locked', async () => {
        const openImpersonationDialog = vi.fn()
        const openTeachersOverview = vi.fn()
        const ctx = {
            isNavigationLocked: true,
            main_action: '',
            log_dialog: false,
            openImpersonationDialog,
            openTeachersOverview,
        }

        await (SuperAdmin as any).methods.handleNavigation.call(ctx, { targetAction: 'teachers' })
        await (SuperAdmin as any).methods.handleNavigation.call(ctx, { action: 'log' })

        expect(ctx.main_action).toBe('')
        expect(ctx.log_dialog).toBe(false)
        expect(openImpersonationDialog).not.toHaveBeenCalled()
        expect(openTeachersOverview).not.toHaveBeenCalled()
    })

    it('routes remaining special navigation actions to dedicated handlers', async () => {
        const openImpersonationDialog = vi.fn()
        const push = vi.fn()
        const ctx = {
            isNavigationLocked: false,
            main_action: '',
            log_dialog: false,
            openImpersonationDialog,
            $router: { push },
        }

        await (SuperAdmin as any).methods.handleNavigation.call(ctx, { action: 'impersonation' })
        await (SuperAdmin as any).methods.handleNavigation.call(ctx, { action: 'log' })
        await (SuperAdmin as any).methods.handleNavigation.call(ctx, { targetAction: 'teachers' })
        await (SuperAdmin as any).methods.handleNavigation.call(ctx, { targetAction: '' })

        expect(openImpersonationDialog).toHaveBeenCalledTimes(1)
        expect(push).toHaveBeenNthCalledWith(1, '/admin/super_admin/teachers')
        expect(push).toHaveBeenNthCalledWith(2, '/admin/super_admin')
        expect(ctx.log_dialog).toBe(true)
    })

    it('redirects legacy schoolyears routes to the admin settings destination', () => {
        const replace = vi.fn()
        const ctx = {
            $route: {
                params: { section: 'schoolyears' },
                query: {},
            },
            $router: { replace },
            redirectSchoolyearsToSettings: (SuperAdmin as any).methods.redirectSchoolyearsToSettings,
        }

        ;(SuperAdmin as any).methods.syncFromRoute.call(ctx)

        expect(replace).toHaveBeenCalledWith('/admin/settings?tab=admin')
    })

    it('redirects legacy users routes to the admin settings destination', () => {
        const replace = vi.fn()
        const ctx = {
            $route: {
                params: { section: 'users' },
                query: {},
            },
            $router: { replace },
            redirectUsersToSettings: (SuperAdmin as any).methods.redirectUsersToSettings,
        }

        ;(SuperAdmin as any).methods.syncFromRoute.call(ctx)

        expect(replace).toHaveBeenCalledWith('/admin/settings?tab=admin&panel=users')
    })

    it('redirects legacy licences routes to the settings destination', () => {
        const replace = vi.fn()
        const ctx = {
            $route: {
                params: { section: 'licences' },
                query: { tab: 'schools' },
            },
            $router: { replace },
            redirectLicencesToSettings: (SuperAdmin as any).methods.redirectLicencesToSettings,
        }

        ;(SuperAdmin as any).methods.syncFromRoute.call(ctx)

        expect(replace).toHaveBeenCalledWith('/admin/settings?panel=licence_models&licence_tab=schools')
    })

    it('redirects legacy roles routes to the settings destination', () => {
        const replace = vi.fn()
        const ctx = {
            $route: {
                params: { section: 'roles' },
                query: {},
            },
            $router: { replace },
            redirectRolesToSettings: (SuperAdmin as any).methods.redirectRolesToSettings,
        }

        ;(SuperAdmin as any).methods.syncFromRoute.call(ctx)

        expect(replace).toHaveBeenCalledWith('/admin/settings?panel=roles')
    })

    it('uses homepage redirect target when impersonated user has no admin-capable roles', () => {
        const methods = (SuperAdmin as any).methods
        const ctx = {
            adminAccessRoles: methods.adminAccessRoles,
            targetCanAccessAdmin: methods.targetCanAccessAdmin,
        }

        const path = methods.impersonationTargetPath.call(ctx, { roles: ['user', 'student'] })

        expect(path).toBe('/')
    })

    it('uses admin redirect target when impersonated user has admin-capable roles', () => {
        const methods = (SuperAdmin as any).methods
        const ctx = {
            adminAccessRoles: methods.adminAccessRoles,
            targetCanAccessAdmin: methods.targetCanAccessAdmin,
        }

        const path = methods.impersonationTargetPath.call(ctx, { roles: ['teacher'] })

        expect(path).toBe('/admin')
    })

    it('falls back to admin redirect target when selected user data is unavailable', () => {
        const methods = (SuperAdmin as any).methods
        const ctx = {
            adminAccessRoles: methods.adminAccessRoles,
            targetCanAccessAdmin: methods.targetCanAccessAdmin,
        }

        const path = methods.impersonationTargetPath.call(ctx, null)

        expect(path).toBe('/admin')
    })

    it('startImpersonation redirects to calculated target after successful switch', async () => {
        const redirectAfterImpersonation = vi.fn()
        const closeImpersonationDialog = vi.fn()
        const ctx = {
            selectedImpersonationUserId: 42,
            selectedImpersonationUser: { id: 42, roles: ['student'] },
            adminStore: {
                startImpersonation: vi.fn().mockResolvedValue(true),
            },
            impersonationTargetPath: (SuperAdmin as any).methods.impersonationTargetPath,
            targetCanAccessAdmin: (SuperAdmin as any).methods.targetCanAccessAdmin,
            adminAccessRoles: (SuperAdmin as any).methods.adminAccessRoles,
            closeImpersonationDialog,
            redirectAfterImpersonation,
            action: 'x',
            main_action: 'users',
        }

        await (SuperAdmin as any).methods.startImpersonation.call(ctx)

        expect(ctx.adminStore.startImpersonation).toHaveBeenCalledWith(42)
        expect(closeImpersonationDialog).toHaveBeenCalledTimes(1)
        expect(ctx.action).toBe('')
        expect(ctx.main_action).toBe('')
        expect(redirectAfterImpersonation).toHaveBeenCalledWith('/')
    })
})
