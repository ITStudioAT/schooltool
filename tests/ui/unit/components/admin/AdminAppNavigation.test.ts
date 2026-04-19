import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import AdminApp from '@/pages/admin/App.vue'

describe('Admin app navigation', () => {
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

    it('passes the clicked menu item to the configured handler', () => {
        const logout = vi.fn()
        const item = { click: 'logout', title: 'Abmelden' }

        ;(AdminApp as any).methods.callItemClick.call({ logout }, item)

        expect(logout).toHaveBeenCalledWith(item)
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
