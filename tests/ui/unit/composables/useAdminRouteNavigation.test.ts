import { afterEach, describe, expect, it, vi } from 'vitest'
import { useAdminRouteNavigation } from '@/composables/useAdminRouteNavigation'

describe('useAdminRouteNavigation', () => {
    afterEach(() => {
        vi.useRealTimers()
    })

    it('releases route loading when the app is unmounted during navigation', () => {
        vi.useFakeTimers()

        let beforeEachHook: ((to: Record<string, string>, from: Record<string, string>, next: () => void) => void) | null =
            null
        const removeBeforeEachHook = vi.fn()
        const removeAfterEachHook = vi.fn()
        const removeErrorHook = vi.fn()
        const router = {
            beforeEach: vi.fn((hook) => {
                beforeEachHook = hook
                return removeBeforeEachHook
            }),
            afterEach: vi.fn(() => removeAfterEachHook),
            onError: vi.fn(() => removeErrorHook),
        }
        const adminStore = { is_loading: 2 }
        const routeNavigation = useAdminRouteNavigation({
            router,
            getCurrentRoute: () => ({ fullPath: '/admin' }),
            adminStore,
        })

        routeNavigation.registerRouteNavigationHooks()
        beforeEachHook?.({ fullPath: '/admin/teaching' }, { fullPath: '/admin' }, vi.fn())

        expect(adminStore.is_loading).toBe(3)
        expect(routeNavigation.state.routeLoadingCount).toBe(1)

        routeNavigation.unregisterRouteNavigationHooks()

        expect(adminStore.is_loading).toBe(2)
        expect(routeNavigation.state.routeLoadingCount).toBe(0)
        expect(routeNavigation.state.routeLoadingFallbackTimer).toBeNull()
        expect(removeBeforeEachHook).toHaveBeenCalledOnce()
        expect(removeAfterEachHook).toHaveBeenCalledOnce()
        expect(removeErrorHook).toHaveBeenCalledOnce()
    })
})
