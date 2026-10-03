import { createTestingPinia } from '@pinia/testing'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import Overview from '@/pages/admin/restaurant/components/Overview.vue'
enableAutoUnmount(afterEach)
afterEach(() => vi.useRealTimers())

function mountOverview(stats = {}) {
    const routerPush = vi.fn()

    const wrapper = mount(Overview, {
        global: {
            plugins: [
                createTestingPinia({
                    createSpy: vi.fn,
                    initialState: {
                        AdminRestaurantStore: {
                            settings: {
                                stats: {
                                    foods_count: 12,
                                    menus_count: 4,
                                    lunch_users_count: 27,
                                    lunch_users_pending_confirmation_count: 5,
                                    ...stats,
                                },
                            },
                        },
                    },
                }),
            ],
            stubs: {
                'v-col': { template: '<div><slot /></div>' },
                'v-row': { template: '<div><slot /></div>' },
                'v-btn': { props: ['to', 'disabled'], template: '<button :disabled="disabled" :data-to="JSON.stringify(to)" @click="$emit(\'click\')"><slot /></button>' },
                ItsGridBox: {
                    props: ['title', 'color'],
                    template: '<section :data-color="color"><h3>{{ title }}</h3><slot /></section>',
                },
            },
            mocks: {
                $router: {
                    push: routerPush,
                },
            },
        },
    })

    return { wrapper, routerPush }
}

describe('Restaurant overview component', () => {
    it('shows independent plan statuses, a live opening countdown, and active direct links for locked plans', async () => {
        vi.useFakeTimers()
        vi.setSystemTime(new Date('2026-10-01T12:00:00+02:00'))
        const plan = { start_date: '2026-10-05', end_date: '2026-10-08', timezone: 'Europe/Vienna', order_end_at: '2026-10-04T16:00:00+02:00', is_available: true }
        const { wrapper } = mountOverview({ menu_plan_weeks: [{
            week_start: '2026-10-05', calendar_week: 41, week_year: 2026, start_date: '2026-10-05', end_date: '2026-10-08', bookings_count: 54,
            plans: [
                { ...plan, id: 73, title: 'Plan A', order_start_at: '2026-10-01T12:01:00+02:00' },
                { ...plan, id: 74, title: 'Plan B', is_available: false, order_start_at: '2026-10-01T12:01:00+02:00' },
                { ...plan, id: 75, title: 'Plan C', order_start_at: null, order_end_at: '2026-10-01T11:00:00+02:00' },
            ],
        }] })
        expect(wrapper.text()).toContain('Öffnet in 0 T 0 Std 1 Min 0 Sek')
        expect(wrapper.text()).toContain('Plan B · Nicht freigegeben')
        expect(wrapper.text()).toContain('Plan C · Buchung geschlossen')
        expect(wrapper.text().match(/Öffnet in/g)).toHaveLength(1)
        const planButtons = wrapper.findAll('button').filter((button) => button.text() === 'Zum Menüplan')
        expect(planButtons).toHaveLength(3)
        expect(planButtons[1].attributes('disabled')).toBeUndefined()
        expect(JSON.parse(planButtons[1].attributes('data-to'))).toEqual({ path: '/admin/menu-plans', query: { mode: 'edit', plan_id: 74, return_to: '/admin/restaurant' } })
        await vi.advanceTimersByTimeAsync(1000)
        expect(wrapper.text()).toContain('Öffnet in 0 T 0 Std 0 Min 59 Sek')
        await vi.advanceTimersByTimeAsync(59000)
        expect(wrapper.text()).toContain('Plan A · Buchung offen')
        expect(wrapper.text()).not.toContain('Öffnet in')
    })

    it('shows each supplied plan week with its dates and number of bookings including zero', () => {
        const { wrapper } = mountOverview({
            booked_menus_count: 999,
            menu_plan_weeks: [
                { week_start: '2026-10-05', calendar_week: 41, week_year: 2026, start_date: '2026-10-05', end_date: '2026-10-08', bookings_count: 54 },
                { week_start: '2026-10-19', calendar_week: 43, week_year: 2026, start_date: '2026-10-19', end_date: '2026-10-22', bookings_count: 0 },
                { week_start: '2027-01-04', calendar_week: 1, week_year: 2027, start_date: '2027-01-04', end_date: '2027-01-07', bookings_count: 1 },
            ],
        })
        const card = wrapper.findAll('section').find((section) => section.find('h3').text() === 'Menüpläne')
        expect(card?.text()).toContain('KW 41/2026')
        expect(card?.text()).toContain('05.10.2026 – 08.10.2026')
        expect(card?.text()).toMatch(/54\s*Buchungen/)
        expect(card?.text()).toContain('KW 43/2026')
        expect(card?.text()).toMatch(/0\s*Buchungen/)
        expect(card?.text()).toContain('KW 1/2027')
        expect(card?.text()).toMatch(/1\s*Buchung/)
        expect(card?.text()).not.toContain('999')
    })

    it('explains when there are no current or upcoming plan weeks', () => {
        const { wrapper } = mountOverview()
        expect(wrapper.text()).toContain('Keine Menüpläne für die aktuelle Woche oder kommende Wochen vorhanden.')
    })

    it.each(['2026-10-05', '2026-10-19'])('opens only the bookings for the selected week %s', async (weekStart) => {
        const { wrapper, routerPush } = mountOverview({ menu_plan_weeks: [
            { week_start: '2026-10-05', calendar_week: 41, week_year: 2026, start_date: '2026-10-05', end_date: '2026-10-08', bookings_count: 54 },
            { week_start: '2026-10-19', calendar_week: 43, week_year: 2026, start_date: '2026-10-19', end_date: '2026-10-22', bookings_count: 0 },
        ] })
        const card = wrapper.findAll('section').find((section) => section.find('h3').text() === 'Menüpläne')
        expect(card?.text()).not.toContain('Zu Menüplänen')
        const buttons = card?.findAll('button').filter((item) => item.text() === 'Buchungen')
        expect(buttons).toHaveLength(2)
        await buttons?.[weekStart === '2026-10-05' ? 0 : 1].trigger('click')
        expect(routerPush).toHaveBeenCalledWith({ path: '/admin/restaurant/bookings', query: { week_start: weekStart } })
    })

    it('shows the overview cards and colors pending confirmations red when needed', () => {
        const { wrapper } = mountOverview()

        expect(wrapper.text()).toContain('Speisen')
        expect(wrapper.text()).toContain('Menüs')
        expect(wrapper.text()).toContain('Benutzer')
        expect(wrapper.text()).toContain('Benutzer zu bestätigen')
        expect(wrapper.text()).toContain('Zu Benutzern')
        expect(wrapper.text()).toContain('Nur zu bestätigen')
        expect(wrapper.text()).toContain('27')
        expect(wrapper.text()).toContain('5')

        const pendingCard = wrapper.findAll('section').find((section) => section.text().includes('Benutzer zu bestätigen'))
        expect(pendingCard?.attributes('data-color')).toBe('error')
    })

    it('keeps the pending confirmation card in the normal color when nothing is pending', () => {
        const { wrapper } = mountOverview({
            lunch_users_pending_confirmation_count: 0,
        })

        const pendingCard = wrapper.findAll('section').find((section) => section.text().includes('Benutzer zu bestätigen'))
        expect(pendingCard?.attributes('data-color')).toBe('primary')
    })

    it('opens the restaurant users page from the benutzer card button', async () => {
        const { wrapper, routerPush } = mountOverview()

        const usersButton = wrapper.findAll('button').find((button) => button.text().includes('Zu Benutzern'))
        await usersButton?.trigger('click')

        expect(routerPush).toHaveBeenCalledWith('/admin/restaurant/users')
    })

    it('opens the restaurant users page with the pending confirmation filter from the card button', async () => {
        const { wrapper, routerPush } = mountOverview()

        const pendingButton = wrapper.findAll('button').find((button) => button.text().includes('Nur zu bestätigen'))
        await pendingButton?.trigger('click')

        expect(routerPush).toHaveBeenCalledWith({
            path: '/admin/restaurant/users',
            query: {
                only_pending_confirmation: '1',
            },
        })
    })
})
