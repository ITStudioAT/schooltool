import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'
import AdminApp from '@/pages/admin/App.vue'

describe('Admin app navigation', () => {
    afterEach(() => {
        vi.useRealTimers()
    })

    it('renders hopper accounts through a Vuetify menu instead of an inline list group', () => {
        const source = readFileSync(join(process.cwd(), 'resources/js/pages/admin/App.vue'), 'utf8')

        expect(source).toContain('<v-menu')
        expect(source).not.toContain('<v-list-group')
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

        expect((AdminApp as any).methods.isMenuItemActive.call(activeCtx, item)).toBe(true)
        expect((AdminApp as any).methods.isMenuItemActive.call(inactiveCtx, item)).toBe(true)
        expect(
            (AdminApp as any).methods.isMenuItemActive.call(
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
            (AdminApp as any).methods.isMenuItemActive.call(
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
            (AdminApp as any).methods.isMenuItemActive.call(
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
        const ctx = {
            isMenuInteractionDisabled: false,
            is_route_navigation_pending: false,
            routeNavigationLockFallbackTimer: null,
            $route: { fullPath: '/admin/restaurant' },
            $router: {
                resolve: vi.fn().mockReturnValue({ fullPath: '/admin/teaching' }),
                push,
            },
            startNavigationLock: (AdminApp as any).methods.startNavigationLock,
            clearNavigationLock: (AdminApp as any).methods.clearNavigationLock,
            clearNavigationLockTimers: (AdminApp as any).methods.clearNavigationLockTimers,
        }

        const navigation = (AdminApp as any).methods.navigateMenuRoute.call(ctx, '/admin/teaching')

        expect(ctx.is_route_navigation_pending).toBe(true)
        await navigation

        expect(push).toHaveBeenCalledWith('/admin/teaching')
        expect(ctx.is_route_navigation_pending).toBe(false)
    })

    it('clears a pending route navigation lock with its fallback timer', () => {
        vi.useFakeTimers()

        const ctx = {
            is_route_navigation_pending: false,
            routeNavigationLockFallbackTimer: null,
            clearNavigationLock: (AdminApp as any).methods.clearNavigationLock,
            clearNavigationLockTimers: (AdminApp as any).methods.clearNavigationLockTimers,
        }

        ;(AdminApp as any).methods.startNavigationLock.call(ctx)

        expect(ctx.is_route_navigation_pending).toBe(true)

        vi.advanceTimersByTime(8000)

        expect(ctx.is_route_navigation_pending).toBe(false)
    })

    it('balances route loading only for navigations that actually started', () => {
        vi.useFakeTimers()

        const ctx = {
            adminStore: { is_loading: 2 },
            routeLoadingCount: 0,
            routeLoadingFallbackTimer: null,
            clearRouteLoadingFallbackTimer: (AdminApp as any).methods.clearRouteLoadingFallbackTimer,
        }

        ;(AdminApp as any).methods.beginRouteLoading.call(ctx)
        expect(ctx.adminStore.is_loading).toBe(3)
        expect(ctx.routeLoadingCount).toBe(1)

        ;(AdminApp as any).methods.finishRouteLoading.call(ctx)
        expect(ctx.adminStore.is_loading).toBe(2)
        expect(ctx.routeLoadingCount).toBe(0)

        ;(AdminApp as any).methods.finishRouteLoading.call(ctx)
        expect(ctx.adminStore.is_loading).toBe(2)
        expect(ctx.routeLoadingCount).toBe(0)
    })

    it('clears route loading with a fallback when navigation never finishes', () => {
        vi.useFakeTimers()

        const ctx = {
            adminStore: { is_loading: 4 },
            routeLoadingCount: 0,
            routeLoadingFallbackTimer: null,
            is_route_navigation_pending: true,
            routeNavigationLockFallbackTimer: null,
            clearNavigationLock: (AdminApp as any).methods.clearNavigationLock,
            clearNavigationLockTimers: (AdminApp as any).methods.clearNavigationLockTimers,
            clearRouteLoadingFallbackTimer: (AdminApp as any).methods.clearRouteLoadingFallbackTimer,
            finishAllRouteLoading: (AdminApp as any).methods.finishAllRouteLoading,
        }

        ;(AdminApp as any).methods.beginRouteLoading.call(ctx)
        expect(ctx.adminStore.is_loading).toBe(5)

        vi.advanceTimersByTime(15000)

        expect(ctx.adminStore.is_loading).toBe(4)
        expect(ctx.routeLoadingCount).toBe(0)
        expect(ctx.is_route_navigation_pending).toBe(false)
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
