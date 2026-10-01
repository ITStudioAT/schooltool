import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import Bookings from '@/pages/admin/restaurant/components/Bookings.vue'
import { reactive } from 'vue'
import { createTestingPinia } from '@pinia/testing'

const route = reactive({ query: {} as Record<string, string> })
vi.mock('vue-router', () => ({ useRoute: () => route }))

function mountBookings() {
    return mount(Bookings, {
        global: {
            plugins: [createTestingPinia({ createSpy: vi.fn })],
            stubs: {
                'v-col': { template: '<div><slot /></div>' },
                'v-table': { template: '<table><slot /></table>' },
                'v-alert': { template: '<div role="alert"><slot /></div>' },
                'v-progress-linear': true,
                'v-btn': { props: ['to', 'disabled'], template: '<a v-if="to" :href="to"><slot /></a><button v-else :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
                'v-dialog': { props: { modelValue: Boolean, persistent: Boolean }, template: '<div v-if="modelValue" role="dialog" :data-persistent="persistent ? \'true\' : \'false\'"><slot /></div>' },
                'v-card': { template: '<div><slot /></div>' },
                'v-card-title': { template: '<h3><slot /></h3>' },
                'v-card-text': { template: '<div><slot /></div>' },
                'v-card-actions': { template: '<div><slot /></div>' },
                'v-spacer': true,
                ItsGridBox: { props: ['title'], template: '<section><h2>{{ title }}</h2><slot /></section>' },
            },
        },
    })
}

afterEach(() => {
    route.query = {}
    vi.unstubAllGlobals()
})

describe('Restaurant bookings', () => {
    it('requires explicit confirmation, cancels without deletion, and refreshes the count after deletion', async () => {
        route.query = { week_start: '2026-10-05' }
        const remove = vi.fn().mockResolvedValue({})
        vi.stubGlobal('axios', { delete: remove, get: vi.fn().mockResolvedValue({ data: { data: [
            { id: 7, plan_id: 73, entry_id: 19, person: 'Testperson', date: '2026-10-05', menu: 'Testmenü', can_delete: true },
        ], meta: { selected_week: { calendar_week: 41, week_year: 2026, start_date: '2026-10-05', end_date: '2026-10-08' } } } }) })
        const wrapper = mountBookings()
        await flushPromises()
        await wrapper.find('tbody button').trigger('click')
        expect(remove).not.toHaveBeenCalled()
        expect(wrapper.find('[role="dialog"]').attributes('data-persistent')).toBe('true')
        expect(wrapper.find('[role="dialog"]').text()).toContain('Testperson')
        await wrapper.findAll('button').find((button) => button.text() === 'Abbrechen')?.trigger('click')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(remove).not.toHaveBeenCalled()
        await wrapper.find('tbody button').trigger('click')
        await wrapper.findAll('button').find((button) => button.text() === 'Löschen bestätigen')?.trigger('click')
        await flushPromises()
        expect(remove).toHaveBeenCalledWith('/api/admin/restaurant/menu-plans/73/entries/19/bookings/7')
        expect(wrapper.findAll('tbody tr')).toHaveLength(0)
        expect(wrapper.text()).toContain('0 Buchungen')
        expect(route.query.week_start).toBe('2026-10-05')
        wrapper.unmount()
    })

    it('disables billed bookings and keeps a server refusal visible in the confirmation dialog', async () => {
        const remove = vi.fn().mockRejectedValue({ response: { status: 409, data: { message: 'Bereits abgerechnet.' } } })
        vi.stubGlobal('axios', { delete: remove, get: vi.fn().mockResolvedValue({ data: { data: [
            { id: 7, plan_id: 73, entry_id: 19, person: 'Testperson', date: '2026-10-05', menu: 'Testmenü', can_delete: true },
            { id: 8, person: 'Abgerechnete Person', date: '2026-10-05', menu: 'Testmenü', can_delete: false },
        ] } }) })
        const wrapper = mountBookings()
        await flushPromises()
        expect(wrapper.findAll('tbody button')[1].attributes('disabled')).toBeDefined()
        await wrapper.findAll('tbody button')[0].trigger('click')
        await wrapper.findAll('button').find((button) => button.text() === 'Löschen bestätigen')?.trigger('click')
        await flushPromises()
        expect(wrapper.find('[role="dialog"]').text()).toContain('Bereits abgerechnet.')
        expect(wrapper.findAll('tbody tr')).toHaveLength(2)
        expect(wrapper.findAll('button').find((button) => button.text() === 'Löschen bestätigen')?.attributes('disabled')).toBeDefined()
        wrapper.unmount()
    })

    it('loads the selected week and visibly identifies its dates and booking count', async () => {
        route.query = { week_start: '2026-10-05' }
        const get = vi.fn().mockResolvedValue({ data: {
            data: [{ id: 7, person: 'Bauer Anna', date: '2026-10-05', menu: 'Fischmenü' }],
            meta: { selected_week: { calendar_week: 41, week_year: 2026, start_date: '2026-10-05', end_date: '2026-10-08' } },
        } })
        vi.stubGlobal('axios', { get })
        const wrapper = mountBookings()
        await flushPromises()
        expect(get).toHaveBeenCalledWith('/api/admin/restaurant/bookings?week_start=2026-10-05')
        expect(wrapper.text()).toContain('Buchungen · KW 41/2026')
        expect(wrapper.text()).toContain('Mo 05.10.2026 – Do 08.10.2026')
        expect(wrapper.text()).toContain('1 Buchung')
        expect(wrapper.find('a').text()).toBe('Zurück zur Übersicht')
        expect(wrapper.find('a').attributes('href')).toBe('/admin/restaurant')
        wrapper.unmount()
    })

    it('numbers the displayed bookings from one in their displayed order', async () => {
        const get = vi.fn().mockResolvedValue({ data: { data: [
            { id: 42, person: 'Adler Zoe', date: '2026-04-01', menu: 'Gemüsemenü' },
            { id: 7, person: 'Bauer Anna', date: '2026-04-02', menu: 'Fischmenü' },
        ] } })
        vi.stubGlobal('axios', { get })
        const wrapper = mountBookings()
        await flushPromises()
        expect(get).toHaveBeenCalledWith('/api/admin/restaurant/bookings')
        expect(wrapper.findAll('th').map((cell) => cell.text())).toEqual(['Nr.', 'Person', 'Datum', 'Menü', 'Aktion'])
        expect(wrapper.findAll('tbody tr').map((row) => row.findAll('td').map((cell) => cell.text()))).toEqual([
            ['1', 'Adler Zoe', 'Mi 01.04.2026', 'Gemüsemenü', 'LöschenBereits abgerechnet'],
            ['2', 'Bauer Anna', 'Do 02.04.2026', 'Fischmenü', 'LöschenBereits abgerechnet'],
        ])
    })

    it('shows an empty state', async () => {
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { data: [] } }) })
        const wrapper = mountBookings()
        await flushPromises()
        expect(wrapper.text()).toContain('Es sind noch keine Buchungen vorhanden.')
    })

    it('shows a loading failure', async () => {
        vi.stubGlobal('axios', { get: vi.fn().mockRejectedValue(new Error('offline')) })
        const wrapper = mountBookings()
        await flushPromises()
        expect(wrapper.text()).toContain('Die Buchungen konnten nicht geladen werden.')
    })
})
