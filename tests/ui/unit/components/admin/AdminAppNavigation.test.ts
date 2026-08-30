import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'
import AdminApp from '@/pages/admin/App.vue'
import AdminNavigationDrawer from '@/pages/admin/components/AdminNavigationDrawer.vue'
import { useAdminRouteNavigation } from '@/composables/useAdminRouteNavigation'
import { resolveAdminShellColor, resolveAdminShellTextColor } from '@/helpers/adminShellTheme'

describe('Admin app navigation', () => {
    afterEach(() => {
        vi.useRealTimers()
    })

    it('renders hopper accounts through a Vuetify menu instead of an inline list group', () => {
        const source = readFileSync(join(process.cwd(), 'resources/js/pages/admin/components/AdminNavigationDrawer.vue'), 'utf8')

        expect(source).toContain('<v-menu')
        expect(source).not.toContain('<v-list-group')
    })

    it('resolves the school color with a semantic primary fallback', () => {
        expect(resolveAdminShellColor({ color: '#336699' }, { use_school_color_for_admin_ui: true })).toBe('#336699')
        expect(resolveAdminShellColor({ color: '#336699' }, { use_school_color_for_admin_ui: false })).toBe('primary')
        expect(resolveAdminShellColor({ color: 'red' }, { use_school_color_for_admin_ui: true })).toBe('primary')
        expect(resolveAdminShellColor(null)).toBe('primary')
    })

    it('resolves the same school differently for each user preference', () => {
        const adminShellColor = (AdminApp as any).computed.adminShellColor
        const selectedSchool = { color: '#336699' }

        expect(adminShellColor.call({
            config: {
                selected_school: selectedSchool,
                user: { use_school_color_for_admin_ui: true },
            },
        })).toBe('#336699')
        expect(adminShellColor.call({
            config: {
                selected_school: selectedSchool,
                user: { use_school_color_for_admin_ui: false },
            },
        })).toBe('primary')
    })

    it('chooses readable text for stored school colors and lets Vuetify handle primary', () => {
        expect(resolveAdminShellTextColor('#10263A')).toBe('#FFFFFF')
        expect(resolveAdminShellTextColor('#FBC02D')).toBe('#10263A')
        expect(resolveAdminShellTextColor('primary')).toBeNull()
    })

    it('applies the resolved color to both drawer surfaces', () => {
        const source = readFileSync(join(process.cwd(), 'resources/js/pages/admin/components/AdminNavigationDrawer.vue'), 'utf8')

        expect(source.match(/:color="shellColor"/g)).toHaveLength(2)
    })

    it('treats students timetables moderators as admin shell users', () => {
        const appData = (AdminApp as any).data()

        expect(appData.admins).toContain('studentstimetables_moderator')
        expect(
            (AdminApp as any).computed.isAdminShellVisible.call({
                config: {
                    is_auth: true,
                    roles: ['studentstimetables_moderator'],
                },
                $route: {
                    path: '/admin',
                },
                admins: appData.admins,
                isImpersonating: false,
            }),
        ).toBe(true)
    })

    it('allows super admins and admins to manage the schoolwide schoolyear', () => {
        const canManageSchoolwideSchoolyear = (AdminApp as any).computed.canManageSchoolwideSchoolyear

        expect(canManageSchoolwideSchoolyear.call({ config: { roles: ['super_admin'] } })).toBe(true)
        expect(canManageSchoolwideSchoolyear.call({ config: { roles: ['admin'] } })).toBe(true)
        expect(canManageSchoolwideSchoolyear.call({ config: { roles: ['teacher'] } })).toBe(false)
    })

    it('keeps the restaurant dashboard item active on restaurant subpages', () => {
        const item = {
            title: 'Restaurant',
            to: '/admin/restaurant',
            active_paths: ['/admin/restaurant'],
        }

        const activeCtx = {
            $route: {
                path: '/admin/restaurant/foods',
            },
            normalizeAdminPath(path: string) {
                return path.replace(/\/+$/, '')
            },
        }

        const inactiveCtx = {
            $route: {
                path: '/admin/restaurant',
            },
            normalizeAdminPath(path: string) {
                return path.replace(/\/+$/, '')
            },
        }

        expect((AdminNavigationDrawer as any).methods.isMenuItemActive.call(activeCtx, item)).toBe(true)
        expect((AdminNavigationDrawer as any).methods.isMenuItemActive.call(inactiveCtx, item)).toBe(true)
        expect(
            (AdminNavigationDrawer as any).methods.isMenuItemActive.call(
                {
                $route: {
                    path: '/admin/materials',
                },
                normalizeAdminPath(path: string) {
                    return path.replace(/\/+$/, '')
                },
            },
            item,
        ),
        ).toBe(false)
    })

    it('keeps the teaching dashboard item active on curricula subpages', () => {
        const item = {
            title: 'Unterricht',
            to: '/admin/teaching',
            active_paths: ['/admin/teaching'],
        }

        expect(
            (AdminNavigationDrawer as any).methods.isMenuItemActive.call(
                {
                    $route: {
                        path: '/admin/teaching/curricula',
                    },
                    normalizeAdminPath(path: string) {
                        return path.replace(/\/+$/, '')
                    },
                },
                item,
            ),
        ).toBe(true)

        expect(
            (AdminNavigationDrawer as any).methods.isMenuItemActive.call(
                {
                    $route: {
                        path: '/admin/teaching',
                    },
                    normalizeAdminPath(path: string) {
                        return path.replace(/\/+$/, '')
                    },
                },
                item,
            ),
        ).toBe(true)
    })

    it('passes the clicked menu item to the configured handler', () => {
        const logout = vi.fn()
        const item = { click: 'logout', title: 'Abmelden' }

        ;(AdminApp as any).methods.callItemClick.call({ logout }, item)

        expect(logout).toHaveBeenCalledWith(item)
    })

    it('keeps dashboard menu routes clickable while another page has background loading', () => {
        const disabled = (AdminApp as any).computed.isMenuInteractionDisabled.call({
            is_navigation_locked: false,
            is_loading: 3,
            is_route_navigation_pending: false,
            is_struktur_modus: false,
        })

        expect(disabled).toBe(false)
    })

    it('navigates dashboard menu routes explicitly and clears the pending state', async () => {
        vi.useFakeTimers()

        const push = vi.fn().mockResolvedValue(undefined)
        const routeNavigation = useAdminRouteNavigation({
            getCurrentRoute: () => ({ fullPath: '/admin/restaurant' }),
            router: {
                resolve: vi.fn().mockReturnValue({ fullPath: '/admin/teaching' }),
                push,
            },
            adminStore: { is_loading: 0 },
        })

        const navigation = routeNavigation.navigateMenuRoute('/admin/teaching', false)

        expect(routeNavigation.state.isRouteNavigationPending).toBe(true)
        await navigation

        expect(push).toHaveBeenCalledWith('/admin/teaching')
        expect(routeNavigation.state.isRouteNavigationPending).toBe(false)
    })

    it('clears a pending route navigation lock with its fallback timer', () => {
        vi.useFakeTimers()

        const routeNavigation = useAdminRouteNavigation({
            getCurrentRoute: () => ({ fullPath: '/admin/restaurant' }),
            router: {
                resolve: vi.fn().mockReturnValue({ fullPath: '/admin/teaching' }),
                push: vi.fn(() => new Promise(() => {})),
            },
            adminStore: { is_loading: 0 },
        })

        void routeNavigation.navigateMenuRoute('/admin/teaching', false)
        expect(routeNavigation.state.isRouteNavigationPending).toBe(true)

        vi.advanceTimersByTime(8000)

        expect(routeNavigation.state.isRouteNavigationPending).toBe(false)
    })

    it('balances route loading only for navigations that actually started', async () => {
        vi.useFakeTimers()

        let beforeEachHook: any
        let afterEachHook: any
        const adminStore = { is_loading: 2 }
        const routeNavigation = useAdminRouteNavigation({
            getCurrentRoute: () => ({ fullPath: '/admin' }),
            adminStore,
            router: {
                beforeEach: vi.fn((hook) => {
                    beforeEachHook = hook
                    return vi.fn()
                }),
                afterEach: vi.fn((hook) => {
                    afterEachHook = hook
                    return vi.fn()
                }),
                onError: vi.fn(() => vi.fn()),
            },
        })
        routeNavigation.registerRouteNavigationHooks()

        beforeEachHook(
            { fullPath: '/admin/teaching' },
            { fullPath: '/admin' },
            vi.fn(),
        )
        expect(adminStore.is_loading).toBe(3)
        expect(routeNavigation.state.routeLoadingCount).toBe(1)

        afterEachHook()
        await Promise.resolve()
        await Promise.resolve()
        expect(adminStore.is_loading).toBe(2)
        expect(routeNavigation.state.routeLoadingCount).toBe(0)

        afterEachHook()
        await Promise.resolve()
        await Promise.resolve()
        expect(adminStore.is_loading).toBe(2)
        expect(routeNavigation.state.routeLoadingCount).toBe(0)
    })

    it('clears route loading with a fallback when navigation never finishes', () => {
        vi.useFakeTimers()

        let beforeEachHook: any
        const adminStore = { is_loading: 4 }
        const routeNavigation = useAdminRouteNavigation({
            getCurrentRoute: () => ({ fullPath: '/admin' }),
            adminStore,
            router: {
                beforeEach: vi.fn((hook) => {
                    beforeEachHook = hook
                    return vi.fn()
                }),
                afterEach: vi.fn(() => vi.fn()),
                onError: vi.fn(() => vi.fn()),
            },
        })
        routeNavigation.registerRouteNavigationHooks()

        beforeEachHook(
            { fullPath: '/admin/teaching' },
            { fullPath: '/admin' },
            vi.fn(),
        )
        expect(adminStore.is_loading).toBe(5)

        vi.advanceTimersByTime(15000)

        expect(adminStore.is_loading).toBe(4)
        expect(routeNavigation.state.routeLoadingCount).toBe(0)
        expect(routeNavigation.state.isRouteNavigationPending).toBe(false)
    })

    it('switches to a hopper account from the dashboard menu', async () => {
        const switchHopperAccount = vi.fn().mockResolvedValue(true)
        const loadConfig = vi.fn().mockResolvedValue(true)
        const nextTick = vi.fn().mockResolvedValue(undefined)
        const redirectToPostHopTarget = vi.fn()

        await (AdminApp as any).methods.switchHopperAccount.call(
            {
                schoolStore: { switchHopperAccount },
                adminStore: { loadConfig },
                $route: { fullPath: '/admin/materials?tab=cards' },
                $nextTick: nextTick,
                isMenuInteractionDisabled: false,
                redirectToPostHopTarget,
            },
            { target_user_id: 42 },
        )

        expect(switchHopperAccount).toHaveBeenCalledWith(42)
        expect(loadConfig).toHaveBeenCalledOnce()
        expect(nextTick).toHaveBeenCalledOnce()
        expect(redirectToPostHopTarget).toHaveBeenCalledWith('/admin/materials?tab=cards')
    })

    it('keeps the current route after hopping when the new account still has access', () => {
        const result = (AdminApp as any).methods.resolvePostHopTarget.call(
            {
                $router: {
                    resolve: vi.fn().mockReturnValue({ path: '/admin/settings' }),
                },
                adminStore: {
                    config: {
                        capabilities: {
                            settings: true,
                        },
                    },
                },
            },
            '/admin/settings?tab=profile',
        )

        expect(result).toBe('/admin/settings?tab=profile')
    })

    it('falls back to the dashboard when the current route is no longer allowed after hopping', () => {
        const result = (AdminApp as any).methods.resolvePostHopTarget.call(
            {
                $router: {
                    resolve: vi.fn().mockReturnValue({ path: '/admin/materials' }),
                },
                adminStore: {
                    config: {
                        capabilities: {
                            materials: false,
                        },
                    },
                },
            },
            '/admin/materials?tab=overview',
        )

        expect(result).toBe('/admin')
    })
})
