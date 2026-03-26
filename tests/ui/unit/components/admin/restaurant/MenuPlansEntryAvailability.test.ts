import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import MenuPlansEntry from '@/pages/admin/restaurant/components/MenuPlansEntry.vue'

function mountAvailabilityEntryPage(query: Record<string, string> = {}) {
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
                            freeDays: [],
                            pendingSetDates: [],
                            pendingUnsetDates: [],
                            isLoaded: true,
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
                'v-btn': { template: '<button v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>' },
                'v-icon': { template: '<i><slot /></i>' },
                'v-text-field': { template: '<input />' },
                'v-dialog': { template: '<div><slot /></div>' },
                'v-card': { template: '<div><slot /></div>' },
                'v-card-title': { template: '<div><slot /></div>' },
                'v-card-text': { template: '<div><slot /></div>' },
                'v-card-actions': { template: '<div><slot /></div>' },
                'v-form': { template: '<form><slot /></form>' },
                'v-spacer': { template: '<div />' },
                'v-textarea': { template: '<textarea />' },
                'v-chip': { template: '<div><slot /></div>' },
                'v-alert': { template: '<div><slot /></div>' },
                'v-avatar': { template: '<div><slot /></div>' },
                'v-img': { template: '<img />' },
                AdminSectionHero: { template: '<header />' },
            },
        },
    })
}

describe('MenuPlans entry availability status', () => {
    it('renders the availability card only when all assignable days are filled and toggles it into the payload', async () => {
        const wrapper = mountAvailabilityEntryPage({
            mode: 'create',
            start: '2026-03-23',
            end: '2026-03-23',
        })

        expect(wrapper.find('[data-testid="availability-card"]').exists()).toBe(false)

        ;(wrapper.vm as any).entriesByDate = {
            '2026-03-23': [
                {
                    _key: 'entry-1',
                    menu: { id: 7, title: 'Wochenmenue', price: '8.50' },
                    menuTitle: 'Wochenmenue',
                    price: '8.50',
                    comments: '',
                    eatingTimeIds: [],
                },
            ],
        }
        await wrapper.vm.$nextTick()

        const toggle = wrapper.get('[data-testid="availability-toggle"]')

        expect((wrapper.vm as any).openDayCount).toBe(0)
        expect(toggle.attributes('aria-pressed')).toBe('false')

        await toggle.trigger('click')

        expect((wrapper.vm as any).isPlanAvailable).toBe(true)
        expect(toggle.attributes('aria-pressed')).toBe('true')
        expect((wrapper.vm as any).buildPayload().is_available).toBe(true)
    })

    it('resets a previously active availability status as soon as open days exist again', () => {
        const ctx = {
            isPlanAvailable: true,
        }

        ;(MenuPlansEntry as any).watch.openDayCount.call(ctx, 1)

        expect(ctx.isPlanAvailable).toBe(false)
    })
})
