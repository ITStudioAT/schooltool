import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { reactive } from 'vue'
import Restaurant from '@/pages/admin/restaurant/Restaurant.vue'
import RestaurantSettings from '@/pages/admin/restaurant/components/Settings.vue'
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

function mountRestaurant(section = 'overview', panel?: string) {
    const route = reactive({ params: { section }, query: panel ? { panel } : {} })
    const routerReplace = vi.fn(async (location) => {
        if (location.path) {
            route.params.section = location.path.split('/')[3] || 'overview'
        }
        route.query = location.query || {}
    })
    const adminStore = reactive({ config: { capabilities: { restaurant: true } }, action: '', action_2: '' })
    vi.mocked(useAdminStore).mockReturnValue(adminStore as never)
    vi.mocked(useFoodStore).mockReturnValue({ index: vi.fn().mockResolvedValue(true) } as never)
    vi.mocked(useMenuStore).mockReturnValue({ index: vi.fn().mockResolvedValue(true) } as never)
    vi.mocked(useRestaurantStore).mockReturnValue({
        settings: {},
        categories: [],
        ingredientIcons: [],
        generalSettings: {},
        canManageGeneralSettings: true,
        loadSettings: vi.fn().mockResolvedValue(true),
    } as never)

    const wrapper = mount({
        ...Restaurant,
        components: { ...Restaurant.components, Settings: RestaurantSettings },
    }, {
        global: {
            mocks: { $route: route, $router: { replace: routerReplace } },
            stubs: {
                AdminSectionHero: true,
                Overview: { template: '<div data-testid="restaurant-overview">Überblick Inhalt</div>' },
                Foods: true,
                Menus: true,
                MenuPlans: true,
                Reports: true,
                RestaurantSepa: true,
                CdgymLegacy: true,
                FreeDays: { template: '<div data-testid="settings-free-days">Freie Tage Inhalt</div>' },
                EatingTimes: { template: '<div data-testid="settings-eating-times">Speisezeiten Inhalt</div>' },
                Users: { template: '<div data-testid="settings-users">Benutzer Inhalt</div>' },
                Sepa: { template: '<div data-testid="settings-sepa">SEPA Inhalt</div>' },
                OnlineSettings: { template: '<div data-testid="settings-online">Online Inhalt</div>' },
                ItsGridBox: { props: ['title'], template: '<div><h2>{{ title }}</h2><slot name="header-actions" /><slot /></div>' },
                ItsRichTextEditor: true,
                FilePond: true,
                'v-container': { template: '<div><slot /></div>' },
                'v-sheet': { template: '<div><slot /></div>' },
                'v-btn': { template: '<button v-bind="$attrs"><slot /></button>' },
                'v-btn-toggle': {
                    emits: ['update:modelValue'],
                    template: '<div @click="$emit(\'update:modelValue\', $event.target.closest(\'button\')?.value)"><slot /></div>',
                },
                'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
                'v-switch': true,
                'v-checkbox': true,
                'v-avatar': true,
                'v-img': true,
            },
        },
    })

    return { wrapper, route, routerReplace, adminStore }
}

describe('Restaurant page navigation', () => {
    beforeEach(() => {
        vi.mocked(useAdminStore).mockReset()
        vi.mocked(useFoodStore).mockReset()
        vi.mocked(useMenuStore).mockReset()
        vi.mocked(useRestaurantStore).mockReset()
    })

    it('replaces the restaurant menu with all settings panels and returns to the restaurant', async () => {
        const { wrapper, route, routerReplace } = mountRestaurant()
        await flushPromises()

        const adminButton = wrapper.get('[data-testid="restaurant-admin"]')
        expect(adminButton.get('.restaurant-nav__button-title').text()).toBe('Admin')
        expect(adminButton.get('.restaurant-nav__button-meta').text()).toBe('Einstellungen')
        expect(adminButton.classes()).toEqual(wrapper.get('[data-testid="restaurant-nav-menus"]').classes())
        expect(adminButton.attributes('aria-pressed')).toBe('false')
        expect(adminButton.attributes('size')).toBeUndefined()
        await wrapper.get('[data-testid="restaurant-admin"]').trigger('click')
        await flushPromises()

        expect(route.params.section).toBe('settings')
        expect(wrapper.find('[data-testid="restaurant-nav-foods"]').exists()).toBe(false)
        expect(wrapper.find('[data-testid="restaurant-settings-nav"]').exists()).toBe(true)
        expect(wrapper.find('.restaurant-content').exists()).toBe(false)
        expect(wrapper.find('[data-testid="restaurant-overview"]').exists()).toBe(false)
        expect(wrapper.get('[data-testid="restaurant-back"] .restaurant-nav__button-title').text()).toBe('Restaurant')
        expect(wrapper.get('h2').text()).toBe('Allgemein')

        const panels = [
            ['Allgemein', 'general', 'h2', 'Allgemein'],
            ['Kategorien', 'categories', 'h2', 'Kategorien'],
            ['Zutaten-Symbole', 'ingredient-icons', 'h2', 'Zutaten-Symbole'],
            ['Freie Tage', 'free-days', '[data-testid="settings-free-days"]', 'Freie Tage Inhalt'],
            ['Speisezeiten', 'eating-times', '[data-testid="settings-eating-times"]', 'Speisezeiten Inhalt'],
            ['Benutzer', 'users', '[data-testid="settings-users"]', 'Benutzer Inhalt'],
            ['SEPA', 'sepa', '[data-testid="settings-sepa"]', 'SEPA Inhalt'],
            ['Online', 'online', '[data-testid="settings-online"]', 'Online Inhalt'],
        ]
        expect(wrapper.findAll('[data-testid="restaurant-settings-nav"] .restaurant-nav__buttons .restaurant-nav__button-title').map((title) => title.text())).toEqual(panels.map(([label]) => label))

        for (const [, panel, selector, content] of panels) {
            await wrapper.get(`[data-testid="restaurant-settings-${panel}"]`).trigger('click')
            await flushPromises()

            expect(route.params.section).toBe('settings')
            expect(route.query.panel).toBe(panel)
            expect(wrapper.get(selector).text()).toBe(content)
            expect(wrapper.get(`[data-testid="restaurant-settings-${panel}"]`).attributes('aria-pressed')).toBe('true')
            expect(wrapper.findAll('[data-testid="restaurant-settings-nav"] [aria-pressed="true"]')).toHaveLength(1)
        }

        await wrapper.get('[data-testid="restaurant-back"]').trigger('click')
        await flushPromises()

        expect(route.params.section).toBe('overview')
        expect(route.query).toEqual({})
        expect(wrapper.find('[data-testid="restaurant-settings-nav"]').exists()).toBe(false)
        expect(wrapper.get('[data-testid="restaurant-overview"]').text()).toBe('Überblick Inhalt')
        expect(wrapper.find('[data-testid="restaurant-nav-foods"]').exists()).toBe(true)
        expect(routerReplace.mock.calls.every(([location]) => !location.path || location.path.startsWith('/admin/restaurant'))).toBe(true)
        wrapper.unmount()
    })

    it('restores the admin menu and selected settings content from the restaurant route', async () => {
        const { wrapper } = mountRestaurant('settings', 'online')
        await flushPromises()

        expect(wrapper.find('[data-testid="restaurant-nav-foods"]').exists()).toBe(false)
        expect(wrapper.get('[data-testid="settings-online"]').text()).toBe('Online Inhalt')
        expect(wrapper.get('[data-testid="restaurant-back"] .restaurant-nav__button-title').text()).toBe('Restaurant')
        wrapper.unmount()
    })

    it('locks Admin during restaurant actions and Restaurant during general settings editing', async () => {
        const { wrapper, adminStore, route } = mountRestaurant()
        await flushPromises()

        adminStore.action = 'edit'
        await wrapper.vm.$nextTick()
        expect(wrapper.get('[data-testid="restaurant-admin"]').attributes('disabled')).toBeDefined()
        await wrapper.get('[data-testid="restaurant-admin"]').trigger('click')
        expect(route.params.section).toBe('overview')

        adminStore.action = ''
        await wrapper.vm.$nextTick()
        await wrapper.get('[data-testid="restaurant-admin"]').trigger('click')
        await flushPromises()
        await wrapper.findAll('button').find((button) => button.text() === 'Bearbeiten')!.trigger('click')
        expect(wrapper.get('[data-testid="restaurant-back"]').attributes('disabled')).toBeDefined()
        expect(wrapper.get('[data-testid="restaurant-settings-categories"]').attributes('disabled')).toBeDefined()
        await wrapper.get('[data-testid="restaurant-settings-categories"]').trigger('click')
        expect(route.query.panel).not.toBe('categories')
        await wrapper.get('[data-testid="restaurant-back"]').trigger('click')
        expect(route.params.section).toBe('settings')

        await wrapper.get('[data-testid="general-settings-cancel-icon"]').trigger('click')
        expect(wrapper.get('[data-testid="restaurant-back"]').attributes('disabled')).toBeUndefined()
        wrapper.unmount()
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
            ensureAllowedSection: vi.fn(),
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

    it('adds the old CDGYM version item only for the Christian-Doppler-Gymnasium school', () => {
        const cdgymItems = (Restaurant as any).computed.visibleNavigationItems.call({
            isCdgymSchool: true,
        })
        const otherSchoolItems = (Restaurant as any).computed.visibleNavigationItems.call({
            isCdgymSchool: false,
        })

        expect(cdgymItems.map((item: { key: string }) => item.key)).toContain('cdgym')
        expect(cdgymItems.find((item: { key: string }) => item.key === 'cdgym')).toMatchObject({
            label: 'Alte Version, Cdgym',
            meta: 'cdgym.info',
        })
        expect(otherSchoolItems.map((item: { key: string }) => item.key)).not.toContain('cdgym')
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

    it('switches to the cdgym section when unlocked', () => {
        const routerReplace = vi.fn()
        const ctx = {
            isNavigationLocked: false,
            main_action: 'overview',
            $router: { replace: routerReplace },
            navigateTo(section: string) {
                return (Restaurant as any).methods.navigateTo.call(this, section)
            },
        }

        ;(Restaurant as any).methods.handleNavigation.call(ctx, 'cdgym')

        expect(ctx.main_action).toBe('cdgym')
        expect(routerReplace).toHaveBeenCalledWith({ path: '/admin/restaurant/cdgym' })
    })

    it.each(['foods', 'menus', 'menu-plans', 'reports', 'users', 'sepa', 'cdgym', 'settings'])('does not switch to %s when locked', (section) => {
        const routerReplace = vi.fn()
        const ctx = {
            isNavigationLocked: true,
            main_action: 'overview',
            $router: { replace: routerReplace },
            navigateTo(section: string) {
                return (Restaurant as any).methods.navigateTo.call(this, section)
            },
        }

        ;(Restaurant as any).methods.handleNavigation.call(ctx, section)

        expect(ctx.main_action).toBe('overview')
        expect(routerReplace).not.toHaveBeenCalled()
    })
})
