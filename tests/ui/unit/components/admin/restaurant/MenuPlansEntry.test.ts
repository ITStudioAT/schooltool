import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import MenuPlansEntry from '@/pages/admin/restaurant/components/MenuPlansEntry.vue'

function mountMenuPlansEntry(query: Record<string, string> = {}, freeDays: { free_date: string }[] = []) {
    return mount(MenuPlansEntry, {
        global: {
            plugins: [
                createTestingPinia({
                    createSpy: vi.fn,
                    initialState: {
                        AdminAdminStore: {
                            config: {
                                selected_school: {
                                    long_name: 'Testschule',
                                },
                            },
                        },
                        AdminRestaurantFreeDayStore: {
                            freeDays,
                            pendingSetDates: [],
                            pendingUnsetDates: [],
                            isLoaded: freeDays.length > 0,
                        },
                    },
                }),
            ],
            mocks: {
                $route: {
                    query,
                },
            },
            stubs: {
                'v-container': { template: '<div><slot /></div>' },
                'v-row': { template: '<div><slot /></div>' },
                'v-col': { template: '<div><slot /></div>' },
                'v-sheet': { template: '<div><slot /></div>' },
                'v-btn': {
                    template: '<button v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
                },
                AdminSectionHero: { template: '<header />' },
            },
        },
    })
}

describe('MenuPlans entry page', () => {
    it('builds the restaurant back target with remembered week', () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'edit',
            return_to: '/admin/restaurant/menu-plans',
            return_week: '2026-04-13',
        })

        expect((wrapper.vm as any).backTarget).toEqual({
            path: '/admin/restaurant/menu-plans',
            query: {
                week: '2026-04-13',
            },
        })
        expect((wrapper.vm as any).backButtonLabel).toBe('Zurueck zum Plan')
    })

    it('renders each day in the requested range and shows dummy menus for edit mode', () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'edit',
            plan_id: 'mp-2026-03-23',
            start: '2026-03-23',
            end: '2026-03-27',
        })

        expect((wrapper.vm as any).planDays.map((day: { iso: string }) => day.iso)).toEqual([
            '2026-03-23',
            '2026-03-24',
            '2026-03-25',
            '2026-03-26',
            '2026-03-27',
        ])

        expect(wrapper.get('[data-testid="selected-menu-2026-03-23"]').text()).toContain('Pasta Napoli')
        expect(wrapper.get('[data-testid="selected-menu-2026-03-25"]').text()).toContain('Gemuese Curry')
        expect(wrapper.get('[data-testid="add-menu-2026-03-24"]').text()).toContain('Menu hinzufuegen')
        expect(wrapper.get('[data-testid="add-menu-2026-03-26"]').exists()).toBe(true)
        expect(wrapper.get('[data-testid="plan-progress-badge"]').text()).toContain('60%')
    })

    it('assigns a dummy menu when the plus button is clicked', async () => {
        const wrapper = mountMenuPlansEntry({
            mode: 'create',
            start: '2026-03-23',
            end: '2026-03-27',
        })

        await wrapper.get('[data-testid="add-menu-2026-03-24"]').trigger('click')

        expect(wrapper.get('[data-testid="selected-menu-2026-03-24"]').text()).toContain('Gemuese Curry')
        expect((wrapper.vm as any).selectedMenuCount).toBe(1)
    })

    describe('free day handling', () => {
        it('marks a free day tile with the free-day class', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'edit', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-25' }],
            )

            const tile = wrapper.get('[data-testid="plan-day-2026-03-25"]')
            expect(tile.classes()).toContain('mpe-day--free')
        })

        it('does not mark non-free days as free', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'edit', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-25' }],
            )

            const tile = wrapper.get('[data-testid="plan-day-2026-03-23"]')
            expect(tile.classes()).not.toContain('mpe-day--free')
        })

        it('shows the free-day card for a free day and hides the add-menu button', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-25' }],
            )

            expect(wrapper.get('[data-testid="free-day-2026-03-25"]').text()).toContain('Freier Tag')
            expect(wrapper.find('[data-testid="add-menu-2026-03-25"]').exists()).toBe(false)
        })

        it('blocks menu assignment on a free day', async () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-24' }],
            )

            ;(wrapper.vm as any).assignDummyMenu('2026-03-24')
            await wrapper.vm.$nextTick()

            expect((wrapper.vm as any).menuSelectionsByDate['2026-03-24']).toBeUndefined()
        })

        it('still allows menu assignment on non-free days', async () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-24' }],
            )

            ;(wrapper.vm as any).assignDummyMenu('2026-03-23')
            await wrapper.vm.$nextTick()

            expect((wrapper.vm as any).menuSelectionsByDate['2026-03-23']).toBeDefined()
        })

        it('excludes free days from the open day count', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-25' }],
            )

            // 5 days total, 1 free → 4 assignable, 0 menus → openDayCount = 4
            expect((wrapper.vm as any).openDayCount).toBe(4)
        })

        it('excludes free days from the coverage percentage calculation', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'edit', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-25' }],
            )

            // edit seeds menus on even indices: 2026-03-23, 2026-03-25, 2026-03-27
            // free day 2026-03-25 is excluded from selectedMenuCount
            // assignable (non-free): Mon, Tue, Thu, Fri = 4
            // menus on non-free days: Mon, Fri = 2
            // coverage = round(2/4 * 100) = 50%
            expect((wrapper.vm as any).coveragePercent).toBe(50)
        })

        it('skips free days when computing the first open day', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-23' }],
            )

            // 2026-03-23 is free → first open day should be 2026-03-24
            expect((wrapper.vm as any).firstOpenDay?.iso).toBe('2026-03-24')
        })

        it('counts free days in freeDayCount', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
                [{ free_date: '2026-03-24' }, { free_date: '2026-03-26' }],
            )

            expect((wrapper.vm as any).freeDayCount).toBe(2)
        })

        it('returns zero freeDayCount when no free days exist in range', () => {
            const wrapper = mountMenuPlansEntry(
                { mode: 'create', start: '2026-03-23', end: '2026-03-27' },
            )

            expect((wrapper.vm as any).freeDayCount).toBe(0)
        })
    })
})
