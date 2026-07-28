import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import MenuPlans from '@/pages/admin/restaurant/components/MenuPlans.vue'
import { useMenuPlanStore } from '@/stores/admin/restaurant/MenuPlanStore'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'

vi.mock('@/stores/admin/restaurant/MenuPlanStore', () => ({
    useMenuPlanStore: vi.fn(),
}))

vi.mock('@/stores/admin/restaurant/RestaurantStore', () => ({
    useRestaurantStore: vi.fn(),
}))

function mountMenuPlansWithFreeDays(freeDays: string[] = []) {
    globalThis.axios = {
        get: vi.fn((url: string, config?: { params?: { year?: number } }) => {
            if (url !== '/api/admin/restaurant/free-days') {
                return Promise.resolve({ data: { data: [] } })
            }

            const requestedYear = Number(config?.params?.year || 0)

            return Promise.resolve({
                data: {
                    data: freeDays
                        .filter((date) => Number(String(date).slice(0, 4)) === requestedYear)
                        .map((freeDate) => ({ free_date: freeDate })),
                },
            })
        }),
    } as never

    vi.mocked(useMenuPlanStore).mockReturnValue({
        plans: [],
        isLoaded: true,
        load: vi.fn(),
        findPlanForDay: vi.fn(() => null),
        planCountForDay: vi.fn(() => 0),
        bookedMenuCountForDay: vi.fn(() => 0),
    } as never)

    vi.mocked(useRestaurantStore).mockReturnValue({
        settings: {
            online_settings: {
                order_start_mode: 'when_available',
                order_start_week_offset: 2,
                order_start_day_of_week: 0,
                order_start_time: '15:00',
                order_end_week_offset: 0,
                order_end_day_of_week: 5,
                order_end_time: '17:00',
            },
        },
        onlineSettings: {
            order_start_mode: 'when_available',
            order_start_week_offset: 2,
            order_start_day_of_week: 0,
            order_start_time: '15:00',
            order_end_week_offset: 0,
            order_end_day_of_week: 5,
            order_end_time: '17:00',
        },
        loadSettings: vi.fn(),
    } as never)

    return mount(MenuPlans, {
        global: {
            mocks: {
                $route: {
                    query: {
                        week: '2026-03-23',
                    },
                },
                $router: {
                    push: vi.fn(),
                },
            },
            stubs: {
                'v-col': { template: '<div><slot /></div>' },
                'v-sheet': { template: '<div><slot /></div>' },
                'v-row': { template: '<div><slot /></div>' },
                'v-btn': { template: '<button v-bind="$attrs" @click="$emit(\'click\')"><slot /></button>' },
                'v-icon': { template: '<i><slot /></i>' },
                'v-alert': { template: '<div><slot /></div>' },
            },
        },
    })
}

describe('Restaurant menu plans free days', () => {
    it('shows restaurant free days in the calendar and legend', async () => {
        const wrapper = mountMenuPlansWithFreeDays(['2026-03-24'])

        await flushPromises()

        expect(wrapper.find('[data-testid="free-day-marker-2026-03-24"]').exists()).toBe(true)
        expect((wrapper.vm as any).dayCellClasses('2026-03-24')['is-free-day']).toBe(true)
        expect(wrapper.text()).toContain('Freier Tag')
    })
})
