import { describe, expect, it } from 'vitest'
import AdminApp from '@/pages/admin/App.vue'

describe('Admin app navigation', () => {
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
})
