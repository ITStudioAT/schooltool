import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import Reports from '@/pages/admin/restaurant/components/Reports.vue'

function mountReports(plans: Array<Record<string, unknown>> = []) {
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
        expect(wrapper.text()).toContain('Bestellliste drucken')

        const printButton = wrapper.findAll('button').find((button) => button.text().includes('Bestellliste drucken'))
        await printButton?.trigger('click')

        expect(openSpy).toHaveBeenCalledWith(
            '/api/admin/restaurant/menu-plans/mp-2026-03-23/print?type=summary',
            '_blank',
            'noopener',
        )

        openSpy.mockRestore()
    })
})
