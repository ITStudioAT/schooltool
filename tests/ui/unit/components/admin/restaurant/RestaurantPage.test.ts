import { beforeEach, describe, expect, it, vi } from 'vitest'
import Restaurant from '@/pages/admin/restaurant/Restaurant.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useFoodStore } from '@/stores/admin/restaurant/FoodStore'
import { useMenuStore } from '@/stores/admin/restaurant/MenuStore'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/admin/restaurant/FoodStore', () => ({
    useFoodStore: vi.fn(),
}))

vi.mock('@/stores/admin/restaurant/MenuStore', () => ({
    useMenuStore: vi.fn(),
}))

vi.mock('@/stores/admin/restaurant/RestaurantStore', () => ({
    useRestaurantStore: vi.fn(),
}))

describe('Restaurant page navigation', () => {
    beforeEach(() => {
        vi.mocked(useAdminStore).mockReset()
        vi.mocked(useFoodStore).mockReset()
        vi.mocked(useMenuStore).mockReset()
        vi.mocked(useRestaurantStore).mockReset()
    })

    it('loads settings, foods, and menus on beforeMount', async () => {
        const adminStoreMock = { config: {} }
        const foodStoreMock = { index: vi.fn().mockResolvedValue(true) }
        const menuStoreMock = { index: vi.fn().mockResolvedValue(true) }
        const restaurantStoreMock = { loadSettings: vi.fn().mockResolvedValue(true) }

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useFoodStore).mockReturnValue(foodStoreMock as never)
        vi.mocked(useMenuStore).mockReturnValue(menuStoreMock as never)
        vi.mocked(useRestaurantStore).mockReturnValue(restaurantStoreMock as never)

        const ctx: Record<string, unknown> = {
            loadPageData() {
                return (Restaurant as any).methods.loadPageData.call(this)
            },
        }
        await (Restaurant as any).beforeMount.call(ctx)

        expect(ctx.adminStore).toBe(adminStoreMock)
        expect(foodStoreMock.index).toHaveBeenCalledTimes(1)
        expect(menuStoreMock.index).toHaveBeenCalledTimes(1)
        expect(restaurantStoreMock.loadSettings).toHaveBeenCalledTimes(1)
    })

    it('builds the navigation items with SEPA administration after users', () => {
        const items = (Restaurant as any).computed.visibleNavigationItems.call({})

        expect(items.map((item: { key: string }) => item.key)).toEqual(['overview', 'foods', 'menus', 'menu-plans', 'reports', 'users', 'sepa'])
    })

    it('builds hero chips from selected school and role context', () => {
        const ctx = {
            selectedSchoolLabel: 'Christian-Doppler-Gymnasium Salzburg',
            selectedRoleLabel: 'admin / lunch_admin',
        }

        const chips = (Restaurant as any).computed.headerChips.call(ctx)

        expect(chips).toEqual([
            { key: 'school', text: 'Christian-Doppler-Gymnasium Salzburg', icon: 'mdi-domain' },
            { key: 'role', text: 'admin / lunch_admin', icon: 'mdi-shield-account' },
        ])
    })

    it('switches to the foods section when unlocked', () => {
        const routerReplace = vi.fn()
        const ctx = {
            isNavigationLocked: false,
            main_action: 'overview',
            $router: { replace: routerReplace },
            navigateTo(section: string) {
                return (Restaurant as any).methods.navigateTo.call(this, section)
            },
        }

        ;(Restaurant as any).methods.handleNavigation.call(ctx, 'foods')

        expect(ctx.main_action).toBe('foods')
        expect(routerReplace).toHaveBeenCalledWith({ path: '/admin/restaurant/foods' })
    })

    it('switches to the menus section when unlocked', () => {
        const routerReplace = vi.fn()
        const ctx = {
            isNavigationLocked: false,
            main_action: 'overview',
            $router: { replace: routerReplace },
            navigateTo(section: string) {
                return (Restaurant as any).methods.navigateTo.call(this, section)
            },
        }

        ;(Restaurant as any).methods.handleNavigation.call(ctx, 'menus')

        expect(ctx.main_action).toBe('menus')
        expect(routerReplace).toHaveBeenCalledWith({ path: '/admin/restaurant/menus' })
    })

    it('switches to the menu plans section when unlocked', () => {
        const routerReplace = vi.fn()
        const ctx = {
            isNavigationLocked: false,
            main_action: 'overview',
            $router: { replace: routerReplace },
            navigateTo(section: string) {
                return (Restaurant as any).methods.navigateTo.call(this, section)
            },
        }

        ;(Restaurant as any).methods.handleNavigation.call(ctx, 'menu-plans')

        expect(ctx.main_action).toBe('menu-plans')
        expect(routerReplace).toHaveBeenCalledWith({ path: '/admin/restaurant/menu-plans' })
    })

    it('refreshes the menu-plans section through the embedded component refresh handler', async () => {
        const refreshData = vi.fn().mockResolvedValue(true)
        const loadPageData = vi.fn().mockResolvedValue(true)
        const ctx = {
            isNavigationLocked: false,
            isRefreshing: false,
            main_action: 'menu-plans',
            $refs: {
                menuPlansSection: {
                    refreshData,
                },
            },
            loadPageData,
        }

        await (Restaurant as any).methods.refreshPageData.call(ctx)

        expect(refreshData).toHaveBeenCalledTimes(1)
        expect(loadPageData).not.toHaveBeenCalled()
    })

    it('switches to the users section when unlocked', () => {
        const routerReplace = vi.fn()
        const ctx = {
            isNavigationLocked: false,
            main_action: 'overview',
            $router: { replace: routerReplace },
            navigateTo(section: string) {
                return (Restaurant as any).methods.navigateTo.call(this, section)
            },
        }

        ;(Restaurant as any).methods.handleNavigation.call(ctx, 'users')

        expect(ctx.main_action).toBe('users')
        expect(routerReplace).toHaveBeenCalledWith({ path: '/admin/restaurant/users' })
    })

    it('switches to the sepa section when unlocked', () => {
        const routerReplace = vi.fn()
        const ctx = {
            isNavigationLocked: false,
            main_action: 'overview',
            $router: { replace: routerReplace },
            navigateTo(section: string) {
                return (Restaurant as any).methods.navigateTo.call(this, section)
            },
        }

        ;(Restaurant as any).methods.handleNavigation.call(ctx, 'sepa')

        expect(ctx.main_action).toBe('sepa')
        expect(routerReplace).toHaveBeenCalledWith({ path: '/admin/restaurant/sepa' })
    })

    it('does not switch sections when locked', () => {
        const routerReplace = vi.fn()
        const ctx = {
            isNavigationLocked: true,
            main_action: 'overview',
            $router: { replace: routerReplace },
            navigateTo(section: string) {
                return (Restaurant as any).methods.navigateTo.call(this, section)
            },
        }

        ;(Restaurant as any).methods.handleNavigation.call(ctx, 'settings')

        expect(ctx.main_action).toBe('overview')
        expect(routerReplace).not.toHaveBeenCalled()
    })
})
