import { beforeEach, describe, expect, it, vi } from 'vitest'
import Restaurant from '@/pages/admin/restaurant/Restaurant.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useFoodStore } from '@/stores/admin/restaurant/FoodStore'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/admin/restaurant/FoodStore', () => ({
    useFoodStore: vi.fn(),
}))

vi.mock('@/stores/admin/restaurant/RestaurantStore', () => ({
    useRestaurantStore: vi.fn(),
}))

describe('Restaurant page navigation', () => {
    beforeEach(() => {
        vi.mocked(useAdminStore).mockReset()
        vi.mocked(useFoodStore).mockReset()
        vi.mocked(useRestaurantStore).mockReset()
    })

    it('loads settings and foods on beforeMount', async () => {
        const adminStoreMock = { config: {} }
        const foodStoreMock = { index: vi.fn().mockResolvedValue(true) }
        const restaurantStoreMock = { loadSettings: vi.fn().mockResolvedValue(true) }

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useFoodStore).mockReturnValue(foodStoreMock as never)
        vi.mocked(useRestaurantStore).mockReturnValue(restaurantStoreMock as never)

        const ctx: Record<string, unknown> = {}
        await (Restaurant as any).beforeMount.call(ctx)

        expect(ctx.adminStore).toBe(adminStoreMock)
        expect(foodStoreMock.index).toHaveBeenCalledTimes(1)
        expect(restaurantStoreMock.loadSettings).toHaveBeenCalledTimes(1)
    })

    it('builds the three navigation items', () => {
        const items = (Restaurant as any).computed.visibleNavigationItems.call({})

        expect(items.map((item: { key: string }) => item.key)).toEqual(['overview', 'foods', 'settings'])
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
