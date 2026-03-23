import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import MenuPlans from '@/pages/admin/restaurant/components/MenuPlans.vue'

function mountMenuPlans() {
    const routerPush = vi.fn()

    return mount(MenuPlans, {
        global: {
            mocks: {
                $router: {
                    push: routerPush,
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

describe('Restaurant menu plans component', () => {
    it('starts each displayed week on monday', () => {
        const wrapper = mountMenuPlans()

        const monday = (wrapper.vm as any).startOfWeekIso('2026-03-26')
        expect(monday).toBe('2026-03-23')

        ;(wrapper.vm as any).currentWeekStartIso = monday

        const weekDays = (wrapper.vm as any).weekDaysWithDates
        expect(weekDays).toHaveLength(7)
        expect(weekDays[0].labelShort).toBe('Mo')
        expect(weekDays[0].iso).toBe('2026-03-23')
        expect(weekDays[6].labelShort).toBe('So')
        expect(weekDays[6].iso).toBe('2026-03-29')
    })

    it('creates a start and end selection from day clicks', () => {
        const wrapper = mountMenuPlans()

        ;(wrapper.vm as any).resetSelection()
        ;(wrapper.vm as any).selectDay('2026-04-10')

        expect((wrapper.vm as any).selectedStartIso).toBe('2026-04-10')
        expect((wrapper.vm as any).selectedEndIso).toBe('')

        ;(wrapper.vm as any).selectDay('2026-04-08')

        expect((wrapper.vm as any).selectedStartIso).toBe('2026-04-08')
        expect((wrapper.vm as any).selectedEndIso).toBe('2026-04-10')

        ;(wrapper.vm as any).selectDay('2026-04-15')

        expect((wrapper.vm as any).selectedStartIso).toBe('2026-04-15')
        expect((wrapper.vm as any).selectedEndIso).toBe('')
    })

    it('selects existing menu-plan days only as full connected period', () => {
        const wrapper = mountMenuPlans()

        ;(wrapper.vm as any).resetSelection()
        ;(wrapper.vm as any).selectDay('2026-03-25')

        expect((wrapper.vm as any).selectedStartIso).toBe('2026-03-23')
        expect((wrapper.vm as any).selectedEndIso).toBe('2026-03-27')
    })

    it('shows edit instead of create when an existing menu-plan is selected', async () => {
        const wrapper = mountMenuPlans()

        ;(wrapper.vm as any).selectDay('2026-03-25')
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).isExistingPlanSelection).toBe(true)
        expect(wrapper.text()).toContain('Bearbeiten')
        expect(wrapper.text()).not.toContain('Erstellen')
    })

    it('opens dummy menu-plan screen in create mode from erstellen', () => {
        const wrapper = mountMenuPlans()
        const initialCount = (wrapper.vm as any).menuPlans.length
        const routerPush = (wrapper.vm as any).$router.push

        ;(wrapper.vm as any).selectedStartIso = '2026-04-01'
        ;(wrapper.vm as any).selectedEndIso = '2026-04-03'
        ;(wrapper.vm as any).createPreview()

        expect((wrapper.vm as any).menuPlans).toHaveLength(initialCount)
        expect(routerPush).toHaveBeenCalledWith({
            path: '/admin/menu-plans',
            query: {
                mode: 'create',
                start: '2026-04-01',
                end: '2026-04-03',
            },
        })
    })

    it('opens dummy menu-plan screen in edit mode from bearbeiten', () => {
        const wrapper = mountMenuPlans()
        const routerPush = (wrapper.vm as any).$router.push

        ;(wrapper.vm as any).selectDay('2026-03-25')
        ;(wrapper.vm as any).editSelectedPlan()

        expect(routerPush).toHaveBeenCalledWith({
            path: '/admin/menu-plans',
            query: {
                mode: 'edit',
                plan_id: 'mp-2026-03-23',
                start: '2026-03-23',
                end: '2026-03-27',
            },
        })
    })

    it('renders three stacked weeks and the create action', () => {
        const wrapper = mountMenuPlans()

        expect(wrapper.findAll('[data-testid^="menu-week-"]')).toHaveLength(3)
        expect(wrapper.findAll('[data-testid^="menu-day-"]')).toHaveLength(21)
        expect(wrapper.text()).toContain('Erstellen')
    })

    it('combines existing menu-plan periods into connected start middle and end day classes', () => {
        const wrapper = mountMenuPlans()
        ;(wrapper.vm as any).currentWeekStartIso = '2026-03-23'

        const startDayClasses = (wrapper.vm as any).dayCellClasses('2026-03-23')
        const middleDayClasses = (wrapper.vm as any).dayCellClasses('2026-03-25')
        const endDayClasses = (wrapper.vm as any).dayCellClasses('2026-03-27')

        expect(startDayClasses['has-plan']).toBe(true)
        expect(startDayClasses['has-plan-start']).toBe(true)
        expect(startDayClasses['has-plan-end']).toBe(false)

        expect(middleDayClasses['has-plan']).toBe(true)
        expect(middleDayClasses['has-plan-middle']).toBe(true)

        expect(endDayClasses['has-plan']).toBe(true)
        expect(endDayClasses['has-plan-end']).toBe(true)
        expect(endDayClasses['has-plan-start']).toBe(false)
    })
})
