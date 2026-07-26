import { nextTick, reactive } from 'vue'

export function useAdminRouteNavigation({ router, getCurrentRoute, adminStore }) {
    const state = reactive({
        isRouteNavigationPending: false,
        routeNavigationLockFallbackTimer: null,
        routeLoadingCount: 0,
        routeLoadingFallbackTimer: null,
        removeRouteBeforeEachHook: null,
        removeRouteAfterEachHook: null,
        removeRouteErrorHook: null,
    })

    function registerRouteNavigationHooks() {
        if (!router) return

        state.removeRouteBeforeEachHook = router.beforeEach((to, from, next) => {
            if (to.fullPath !== from.fullPath) {
                beginRouteLoading()
            }

            next()
        })

        state.removeRouteAfterEachHook = router.afterEach(() => {
            clearNavigationLock()
            nextTick(() => {
                finishRouteLoading()
            })
        })

        state.removeRouteErrorHook = router.onError(() => {
            clearNavigationLock()
            nextTick(() => {
                finishRouteLoading()
            })
        })
    }

    async function navigateMenuRoute(target, isMenuInteractionDisabled) {
        if (isMenuInteractionDisabled) return
        if (!router) return

        const resolvedTarget = router.resolve(target)?.fullPath || ''
        const currentRoute = getCurrentRoute?.()?.fullPath || ''
        if (!resolvedTarget || resolvedTarget === currentRoute) return

        startNavigationLock()

        try {
            await router.push(target)
        } catch (error) {
            console.error('dashboard menu navigation failed:', error)
        } finally {
            clearNavigationLock()
        }
    }

    function startNavigationLock() {
        clearNavigationLockTimers()
        state.isRouteNavigationPending = true
        state.routeNavigationLockFallbackTimer = window.setTimeout(() => {
            clearNavigationLock()
        }, 8000)
    }

    function clearNavigationLock() {
        clearNavigationLockTimers()
        state.isRouteNavigationPending = false
    }

    function clearNavigationLockTimers() {
        if (state.routeNavigationLockFallbackTimer !== null) {
            window.clearTimeout(state.routeNavigationLockFallbackTimer)
            state.routeNavigationLockFallbackTimer = null
        }
    }

    function beginRouteLoading() {
        if (!adminStore) return

        state.routeLoadingCount++
        adminStore.is_loading++
        clearRouteLoadingFallbackTimer()
        state.routeLoadingFallbackTimer = window.setTimeout(() => {
            finishAllRouteLoading()
        }, 15000)
    }

    function finishRouteLoading() {
        clearRouteLoadingFallbackTimer()
        if (state.routeLoadingCount <= 0 || !adminStore) return

        state.routeLoadingCount--
        adminStore.is_loading = Math.max(0, Number(adminStore.is_loading || 0) - 1)
    }

    function finishAllRouteLoading() {
        const count = state.routeLoadingCount
        state.routeLoadingCount = 0
        clearRouteLoadingFallbackTimer()
        clearNavigationLock()
        if (!adminStore || count <= 0) return

        adminStore.is_loading = Math.max(0, Number(adminStore.is_loading || 0) - count)
    }

    function clearRouteLoadingFallbackTimer() {
        if (state.routeLoadingFallbackTimer !== null) {
            window.clearTimeout(state.routeLoadingFallbackTimer)
            state.routeLoadingFallbackTimer = null
        }
    }

    function unregisterRouteNavigationHooks() {
        clearNavigationLockTimers()
        finishAllRouteLoading()

        if (typeof state.removeRouteBeforeEachHook === 'function') {
            state.removeRouteBeforeEachHook()
        }

        if (typeof state.removeRouteAfterEachHook === 'function') {
            state.removeRouteAfterEachHook()
        }

        if (typeof state.removeRouteErrorHook === 'function') {
            state.removeRouteErrorHook()
        }

        state.removeRouteBeforeEachHook = null
        state.removeRouteAfterEachHook = null
        state.removeRouteErrorHook = null
    }

    return {
        state,
        registerRouteNavigationHooks,
        navigateMenuRoute,
        unregisterRouteNavigationHooks,
    }
}
