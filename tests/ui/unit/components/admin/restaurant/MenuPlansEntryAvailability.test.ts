import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import MenuPlansEntry from '@/pages/admin/restaurant/components/MenuPlansEntry.vue'
import { useMenuPlanStore } from '@/stores/admin/restaurant/MenuPlanStore'

vi.mock('@/stores/admin/restaurant/MenuPlanStore', () => ({
    useMenuPlanStore: vi.fn(),
}))

function createMenuPlanStoreMock() {
    return {
        plans: [],
        isLoaded: true,
        load: vi.fn(),
        show: vi.fn(),
        destroy: vi.fn().mockResolvedValue(true),
        update: vi.fn().mockResolvedValue({
            id: 7,
            start_date: '2026-03-23',
            end_date: '2026-03-23',
            is_available: true,
            use_individual_schedule_values: false,
            visibility_start_mode: 'when_orderable',
            visibility_start_week_offset: null,
            visibility_start_day_of_week: null,
            visibility_start_time: null,
            order_start_mode: 'when_available',
            order_start_week_offset: null,
            order_start_day_of_week: null,
            order_start_time: null,
            order_end_week_offset: 1,
            order_end_day_of_week: 5,
            order_end_time: '17:00',
            visibility_end_mode: 'week_end',
            visible_start_at: null,
            visible_end_at: '2026-03-23T23:59',
            order_start_at: null,
            order_end_at: '2026-03-21T17:00',
        }),
        store: vi.fn().mockResolvedValue({
            id: 7,
            start_date: '2026-03-23',
            end_date: '2026-03-23',
            is_available: true,
            use_individual_schedule_values: false,
            visibility_start_mode: 'when_orderable',
            visibility_start_week_offset: null,
            visibility_start_day_of_week: null,
            visibility_start_time: null,
            order_start_mode: 'when_available',
            order_start_week_offset: null,
            order_start_day_of_week: null,
            order_start_time: null,
            order_end_week_offset: 1,
            order_end_day_of_week: 5,
            order_end_time: '17:00',
            visibility_end_mode: 'week_end',
            visible_start_at: null,
            visible_end_at: '2026-03-23T23:59',
            order_start_at: null,
            order_end_at: '2026-03-21T17:00',
        }),
    }
}

function mountAvailabilityEntryPage(query: Record<string, string> = {}) {
    const menuPlanStoreMock = createMenuPlanStoreMock()

    vi.mocked(useMenuPlanStore).mockReturnValue(menuPlanStoreMock as never)

    const wrapper = mount(MenuPlansEntry, {
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
                        AdminRestaurantStore: {
                            settings: {
                                categories: [],
                                allergen_options: [],
                                online_settings: {
                                    visibility_start_mode: 'when_orderable',
                                    visibility_start_week_offset: 2,
                                    visibility_start_day_of_week: 1,
                                    visibility_start_time: '09:00',
                                    order_start_mode: 'when_available',
                                    order_start_week_offset: 2,
                                    order_start_day_of_week: 1,
                                    order_start_time: '10:00',
                                    order_end_week_offset: 1,
                                    order_end_day_of_week: 5,
                                    order_end_time: '17:00',
                                    visibility_end_mode: 'week_end',
                                },
                            },
                        },
                    },
                }),
            ],
            mocks: {
                $route: {
                    query,
                },
                $router: {
                    push: vi.fn(() => Promise.resolve()),
                    replace: vi.fn(() => Promise.resolve()),
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
                'v-select': { template: '<select><slot /></select>' },
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

    return { wrapper, menuPlanStoreMock }
}

async function settleAvailabilityEntryPage(wrapper: ReturnType<typeof mountAvailabilityEntryPage>['wrapper']) {
    await Promise.resolve()
    await wrapper.vm.$nextTick()
}

describe('MenuPlans entry availability status', () => {
    it('renders the availability card only when all assignable days are filled and toggles it into the payload', async () => {
        const { wrapper } = mountAvailabilityEntryPage({
            mode: 'create',
            start: '2026-03-23',
            end: '2026-03-23',
        })
        await settleAvailabilityEntryPage(wrapper)

        expect(wrapper.find('[data-testid="availability-card"]').exists()).toBe(true)
        expect(wrapper.get('[data-testid="availability-toggle"]').attributes('disabled')).toBeDefined()

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
        expect((wrapper.vm as any).buildPayload()).toMatchObject({
            visibility_start_mode: 'when_orderable',
            order_start_mode: 'when_available',
            visibility_end_mode: 'week_end',
            use_individual_schedule_values: false,
            visible_start_at: null,
            visible_end_at: null,
            order_start_at: null,
            order_end_at: null,
        })
    })

    it('resets a previously active availability status as soon as open days exist again', () => {
        const ctx = {
            isPlanAvailable: true,
        }

        ;(MenuPlansEntry as any).watch.openDayCount.call(ctx, 1)

        expect(ctx.isPlanAvailable).toBe(false)
    })

    it('asks before saving a finished but unreleased menu plan', async () => {
        const { wrapper, menuPlanStoreMock } = mountAvailabilityEntryPage({
            mode: 'create',
            start: '2026-03-23',
            end: '2026-03-23',
        })
        await settleAvailabilityEntryPage(wrapper)

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

        await (wrapper.vm as any).savePlan()
        await wrapper.vm.$nextTick()

        expect(wrapper.find('[data-testid="save-release-dialog"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="save-release-alert"]').exists()).toBe(true)
        expect(wrapper.text()).toContain('Fertig, aber noch nicht freigegeben')
        expect(wrapper.text()).toContain('Menüplan jetzt freigeben?')
        expect(wrapper.text()).toContain('Ohne Freigabe bleibt er für Sichtbarkeit und Bestellungen gesperrt.')
        expect(menuPlanStoreMock.store).not.toHaveBeenCalled()
    })

    it('can save and release a finished plan from the confirmation dialog', async () => {
        const { wrapper, menuPlanStoreMock } = mountAvailabilityEntryPage({
            mode: 'create',
            start: '2026-03-23',
            end: '2026-03-23',
        })
        await settleAvailabilityEntryPage(wrapper)

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

        await (wrapper.vm as any).savePlan()
        await (wrapper.vm as any).confirmSaveAndRelease()

        expect(menuPlanStoreMock.store).toHaveBeenCalledWith(
            expect.objectContaining({
                is_available: true,
                visibility_start_mode: 'when_orderable',
                order_start_mode: 'when_available',
                use_individual_schedule_values: false,
                visible_start_at: null,
                visible_end_at: null,
                order_start_at: null,
                order_end_at: null,
            }),
        )
    })

    it('persists the timing fields immediately when availability is toggled for an existing plan', async () => {
        const { wrapper, menuPlanStoreMock } = mountAvailabilityEntryPage({
            mode: 'edit',
            plan_id: '7',
            start: '2026-03-23',
            end: '2026-03-23',
        })
        await settleAvailabilityEntryPage(wrapper)

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
        ;(wrapper.vm as any).planScheduleForm = {
            visibilityStartMode: 'when_orderable',
            visibilityStartWeekOffset: 2,
            visibilityStartDayOfWeek: 0,
            visibilityStartTime: '15:00',
            orderStartMode: 'when_available',
            orderStartWeekOffset: 2,
            orderStartDayOfWeek: 0,
            orderStartTime: '15:00',
            orderEndWeekOffset: 1,
            orderEndDayOfWeek: 5,
            orderEndTime: '17:00',
            visibilityEndMode: 'week_end',
        }
        await wrapper.vm.$nextTick()

        await wrapper.get('[data-testid="availability-toggle"]').trigger('click')

        expect(menuPlanStoreMock.update).toHaveBeenCalledWith(
            7,
            expect.objectContaining({
                is_available: true,
                visibility_start_mode: 'when_orderable',
                order_start_mode: 'when_available',
                use_individual_schedule_values: false,
                visible_start_at: null,
                visible_end_at: null,
                order_start_at: null,
                order_end_at: null,
            }),
        )
    })

    it('deletes a saved menu plan from the persistent confirmation dialog', async () => {
        const { wrapper, menuPlanStoreMock } = mountAvailabilityEntryPage({
            mode: 'edit',
            plan_id: '7',
            start: '2026-03-23',
            end: '2026-03-27',
            return_to: '/admin/restaurant/menu-plans',
            return_week: '2026-03-30',
        })
        await settleAvailabilityEntryPage(wrapper)

        ;(wrapper.vm as any).hasPlanBookings = false
        await wrapper.vm.$nextTick()

        await wrapper.get('[data-testid="delete-menu-plan-button"]').trigger('click')

        expect((wrapper.vm as any).deletePlanDialog).toBe(true)

        await (wrapper.vm as any).confirmDeletePlan()

        expect(menuPlanStoreMock.destroy).toHaveBeenCalledWith(7)
        expect((wrapper.vm as any).$router.push).toHaveBeenCalledWith({
            path: '/admin/restaurant/menu-plans',
            query: {
                week: '2026-03-30',
            },
        })
    })
})
