import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import Bookings from '@/pages/admin/restaurant/components/Bookings.vue'

function mountBookings() {
    return mount(Bookings, {
        global: {
            stubs: {
                'v-col': { template: '<div><slot /></div>' },
                'v-table': { template: '<table><slot /></table>' },
                'v-alert': { template: '<div role="alert"><slot /></div>' },
                'v-progress-linear': true,
                ItsGridBox: { template: '<section><slot /></section>' },
            },
        },
    })
}

afterEach(() => vi.unstubAllGlobals())

describe('Restaurant bookings', () => {
    it('numbers the displayed bookings from one in their displayed order', async () => {
        const get = vi.fn().mockResolvedValue({ data: { data: [
            { id: 42, person: 'Adler Zoe', date: '2026-04-01', menu: 'Gemüsemenü' },
            { id: 7, person: 'Bauer Anna', date: '2026-04-02', menu: 'Fischmenü' },
        ] } })
        vi.stubGlobal('axios', { get })
        const wrapper = mountBookings()
        await flushPromises()
        expect(get).toHaveBeenCalledWith('/api/admin/restaurant/bookings')
        expect(wrapper.findAll('th').map((cell) => cell.text())).toEqual(['Nr.', 'Person', 'Datum', 'Menü'])
        expect(wrapper.findAll('tbody tr').map((row) => row.findAll('td').map((cell) => cell.text()))).toEqual([
            ['1', 'Adler Zoe', 'Mi 01.04.2026', 'Gemüsemenü'],
            ['2', 'Bauer Anna', 'Do 02.04.2026', 'Fischmenü'],
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
