import { describe, expect, it, vi } from 'vitest'
import HopperSchools from '@/pages/admin/profile/components/HopperSchools.vue'

describe('HopperSchools', () => {
    it('redirects to a full page reload after switching accounts', async () => {
        const switchHopperAccount = vi.fn().mockResolvedValue(true)
        const nextConfig = { capabilities: { teaching: true } }
        const loadConfig = vi.fn().mockResolvedValue(nextConfig)
        const nextTick = vi.fn().mockResolvedValue(undefined)
        const redirectToPostHopTarget = vi.fn()

        await (HopperSchools as any).methods.switchAccount.call(
            {
                schoolStore: { switchHopperAccount },
                adminStore: { loadConfig },
                $route: { fullPath: '/admin/teaching' },
                $nextTick: nextTick,
                redirectToPostHopTarget,
            },
            { id: 17 },
        )

        expect(switchHopperAccount).toHaveBeenCalledWith(17)
        expect(loadConfig).toHaveBeenCalledWith({ navigationOnly: true })
        expect(nextTick).not.toHaveBeenCalled()
        expect(redirectToPostHopTarget).toHaveBeenCalledWith('/admin/teaching', nextConfig)
    })

    it('falls back to the dashboard when the preserved route is no longer allowed', () => {
        const result = (HopperSchools as any).methods.resolvePostHopTarget.call(
            {
                $router: {
                    resolve: vi.fn().mockReturnValue({ path: '/admin/teaching' }),
                },
                adminStore: {
                    config: {
                        capabilities: {
                            teaching: false,
                        },
                    },
                },
            },
            '/admin/teaching',
        )

        expect(result).toBe('/admin')
    })
})
