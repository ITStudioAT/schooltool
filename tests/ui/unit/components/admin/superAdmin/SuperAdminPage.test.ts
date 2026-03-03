import { describe, expect, it, vi } from 'vitest'
import SuperAdmin from '@/pages/admin/superAdmin/SuperAdmin.vue'

describe('Super admin page navigation', () => {
    it('shows header in overview and core admin sections', () => {
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: '' })).toBe(true)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'schools' })).toBe(true)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'schoolyears' })).toBe(true)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'licences' })).toBe(true)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'roles' })).toBe(true)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'users' })).toBe(true)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'teachers' })).toBe(true)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'teachers_list' })).toBe(true)
        expect((SuperAdmin as any).computed.shouldShowHeader.call({ main_action: 'unknown' })).toBe(false)
    })

    it('uses overview dark background theme in requested sections', () => {
        expect((SuperAdmin as any).computed.usesOverviewTheme.call({ main_action: '' })).toBe(true)
        expect((SuperAdmin as any).computed.usesOverviewTheme.call({ main_action: 'licences' })).toBe(true)
        expect((SuperAdmin as any).computed.usesOverviewTheme.call({ main_action: 'roles' })).toBe(true)
        expect((SuperAdmin as any).computed.usesOverviewTheme.call({ main_action: 'users' })).toBe(true)
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

        expect(items.map((item: { key: string }) => item.key)).toEqual(['overview', 'schoolyears', 'users', 'teachers', 'log', 'horizon'])
    })

    it('builds full navigation items for super admin role', () => {
        const ctx = {
            config: {
                roles: ['super_admin'],
            },
            isImpersonating: false,
        }

        const items = (SuperAdmin as any).computed.visibleNavigationItems.call(ctx)

        expect(items.map((item: { key: string }) => item.key)).toEqual([
            'overview',
            'schools',
            'schoolyears',
            'licences',
            'roles',
            'users',
            'teachers',
            'impersonation',
            'log',
            'horizon',
        ])
    })

    it('marks navigation items as active only for matching targetAction', () => {
        const ctx = {
            main_action: 'users',
        }

        const active = (SuperAdmin as any).methods.isNavigationItemActive.call(ctx, { targetAction: 'users' })
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
        const moveToHorizon = vi.fn()
        const openLicencesOverview = vi.fn()
        const openTeachersOverview = vi.fn()
        const ctx = {
            isNavigationLocked: true,
            main_action: '',
            log_dialog: false,
            openImpersonationDialog,
            moveToHorizon,
            openLicencesOverview,
            openTeachersOverview,
        }

        await (SuperAdmin as any).methods.handleNavigation.call(ctx, { targetAction: 'users' })
        await (SuperAdmin as any).methods.handleNavigation.call(ctx, { action: 'log' })

        expect(ctx.main_action).toBe('')
        expect(ctx.log_dialog).toBe(false)
        expect(openImpersonationDialog).not.toHaveBeenCalled()
        expect(moveToHorizon).not.toHaveBeenCalled()
        expect(openLicencesOverview).not.toHaveBeenCalled()
        expect(openTeachersOverview).not.toHaveBeenCalled()
    })

    it('routes special navigation actions to dedicated handlers', async () => {
        const openImpersonationDialog = vi.fn()
        const moveToHorizon = vi.fn()
        const openLicencesOverview = vi.fn()
        const openTeachersOverview = vi.fn()
        const ctx = {
            isNavigationLocked: false,
            main_action: '',
            log_dialog: false,
            openImpersonationDialog,
            moveToHorizon,
            openLicencesOverview,
            openTeachersOverview,
        }

        await (SuperAdmin as any).methods.handleNavigation.call(ctx, { action: 'impersonation' })
        await (SuperAdmin as any).methods.handleNavigation.call(ctx, { action: 'horizon' })
        await (SuperAdmin as any).methods.handleNavigation.call(ctx, { action: 'log' })
        await (SuperAdmin as any).methods.handleNavigation.call(ctx, { targetAction: 'licences' })
        await (SuperAdmin as any).methods.handleNavigation.call(ctx, { targetAction: 'teachers' })
        await (SuperAdmin as any).methods.handleNavigation.call(ctx, { targetAction: 'users' })

        expect(openImpersonationDialog).toHaveBeenCalledTimes(1)
        expect(moveToHorizon).toHaveBeenCalledTimes(1)
        expect(openLicencesOverview).toHaveBeenCalledTimes(1)
        expect(openTeachersOverview).toHaveBeenCalledTimes(1)
        expect(ctx.log_dialog).toBe(true)
        expect(ctx.main_action).toBe('users')
    })
})
