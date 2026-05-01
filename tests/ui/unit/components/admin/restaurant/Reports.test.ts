import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import Reports from '@/pages/admin/restaurant/components/Reports.vue'

function mountReports(plans: Array<Record<string, unknown>> = [], onlineSettings: Record<string, unknown> = {}) {
    return mount(Reports, {
        global: {
            plugins: [
                createTestingPinia({
                    createSpy: vi.fn,
                    initialState: {
                        AdminRestaurantMenuPlanStore: {
                            plans,
                            isLoaded: true,
                        },
                        AdminRestaurantStore: {
                            settings: {
                                online_settings: {
                                    visibility_start_mode: 'when_available',
                                    visibility_start_week_offset: 2,
                                    visibility_start_day_of_week: 0,
                                    visibility_start_time: '15:00',
                                    order_start_mode: 'when_available',
                                    order_start_week_offset: 2,
                                    order_start_day_of_week: 0,
                                    order_start_time: '15:00',
                                    order_end_week_offset: 0,
                                    order_end_day_of_week: 4,
                                    order_end_time: '09:00',
                                    visibility_end_mode: 'plan_end',
                                    ...onlineSettings,
                                },
                            },
                        },
                    },
                }),
            ],
            stubs: {
                'v-col': { template: '<div><slot /></div>' },
                'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
                'v-progress-linear': { template: '<div />' },
                'v-alert': { template: '<div><slot /></div>' },
                'v-icon': { template: '<span />' },
                RestaurantBillingReportsCard: { template: '<div class="billing-card-stub" />' },
                ItsGridBox: {
                    props: ['title'],
                    template: '<section><h3>{{ title }}</h3><slot /></section>',
                },
            },
        },
    })
}

describe('Restaurant reports component', () => {
    afterEach(() => {
        vi.useRealTimers()
    })

    it('opens the summary print pdf for the selected menu plan', async () => {
        const openSpy = vi.spyOn(window, 'open').mockImplementation(() => null)
        const wrapper = mountReports([
            {
                id: 'mp-2026-03-23',
                start_date: '2026-03-23',
                end_date: '2026-03-27',
                is_available: true,
                entries: [
                    {
                        id: 1,
                        plan_date: '2026-03-24',
                        booked_menu_count: 5,
                    },
                ],
            },
        ])

        await wrapper.vm.$nextTick()

        expect(wrapper.text()).toContain('Menüsummen drucken')
        expect(wrapper.text()).toContain('5 Bestellungen')
        const printButtons = wrapper.findAll('button').filter((button) => button.text().includes('Bestellliste drucken'))

        expect(printButtons).toHaveLength(2)

        await printButtons[0]?.trigger('click')

        expect(openSpy).toHaveBeenCalledWith(
            '/api/admin/restaurant/menu-plans/mp-2026-03-23/print?type=summary',
            '_blank',
            'noopener',
        )

        openSpy.mockRestore()
    })

    it('shows menu plans newest first', async () => {
        const wrapper = mountReports([
            {
                id: 'kw-18',
                title: 'Menüplan KW 18',
                start_date: '2026-04-27',
                end_date: '2026-04-30',
                is_available: true,
                entries: [],
            },
            {
                id: 'kw-19',
                title: 'Menüplan KW 19',
                start_date: '2026-05-04',
                end_date: '2026-05-07',
                is_available: true,
                entries: [],
            },
        ])

        await wrapper.vm.$nextTick()

        const text = wrapper.text()

        expect(text.indexOf('Menüplan KW 19')).toBeLessThan(text.indexOf('Menüplan KW 18'))
    })

    it('uses the current online settings for report orderability instead of stale plan schedule values', async () => {
        vi.useFakeTimers()
        vi.setSystemTime(new Date('2026-04-30T17:30:00+02:00'))

        const wrapper = mountReports([
            {
                id: 15,
                title: 'Menüplan KW 19',
                start_date: '2026-05-04',
                end_date: '2026-05-07',
                is_available: true,
                order_start_mode: 'when_available',
                order_end_week_offset: 1,
                order_end_day_of_week: 5,
                order_end_time: '17:00',
                visibility_end_mode: 'plan_end',
                entries: [],
            },
        ], {
            visibility_start_mode: 'when_orderable',
            order_start_mode: 'scheduled',
            order_start_week_offset: 2,
            order_start_day_of_week: 0,
            order_start_time: '15:00',
            order_end_week_offset: 1,
            order_end_day_of_week: 4,
            order_end_time: '16:00',
            visibility_end_mode: 'week_end',
        })

        await wrapper.vm.$nextTick()

        expect(wrapper.text()).toContain('Menüplan KW 19')
        expect(wrapper.text()).toContain('Nicht mehr bestellbar')
    })
})
