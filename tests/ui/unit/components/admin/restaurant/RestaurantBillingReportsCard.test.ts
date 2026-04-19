import { createTestingPinia } from '@pinia/testing'
import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import RestaurantBillingReportsCard from '@/pages/admin/restaurant/components/RestaurantBillingReportsCard.vue'
import { useRestaurantBillingStore } from '@/stores/admin/restaurant/RestaurantBillingStore'

function mountBillingReportsCard() {
    return mount(RestaurantBillingReportsCard, {
        global: {
            plugins: [
                createTestingPinia({
                    createSpy: vi.fn,
                    initialState: {
                        AdminRestaurantBillingStore: {
                            isLoaded: true,
                            billings: [
                                {
                                    id: 1,
                                    period_label: 'KW 10/2026',
                                    date_range_label: '02.03.2026 - 08.03.2026',
                                    bookings_count: 12,
                                    total_amount: '62.40',
                                    created_at: '09.03.2026 08:00',
                                },
                                {
                                    id: 2,
                                    period_label: 'KW 11/2026',
                                    date_range_label: '09.03.2026 - 15.03.2026',
                                    bookings_count: 9,
                                    total_amount: '46.80',
                                    created_at: '16.03.2026 08:00',
                                },
                                {
                                    id: 3,
                                    period_label: 'KW 12/2026',
                                    date_range_label: '16.03.2026 - 22.03.2026',
                                    bookings_count: 11,
                                    total_amount: '57.20',
                                    created_at: '23.03.2026 08:00',
                                },
                                {
                                    id: 4,
                                    period_label: 'KW 13/2026',
                                    date_range_label: '23.03.2026 - 29.03.2026',
                                    bookings_count: 14,
                                    total_amount: '72.80',
                                    created_at: '30.03.2026 08:00',
                                },
                            ],
                            weeks: [
                                { week_start: '2026-02-09', week_end: '2026-02-15', label: 'KW 07', date_range_label: '09.02.2026 - 15.02.2026', is_billed: true },
                                { week_start: '2026-02-16', week_end: '2026-02-22', label: 'KW 08', date_range_label: '16.02.2026 - 22.02.2026', is_billed: false },
                                { week_start: '2026-02-23', week_end: '2026-03-01', label: 'KW 09', date_range_label: '23.02.2026 - 01.03.2026', is_billed: false },
                                { week_start: '2026-03-02', week_end: '2026-03-08', label: 'KW 10', date_range_label: '02.03.2026 - 08.03.2026', is_billed: false },
                                { week_start: '2026-03-09', week_end: '2026-03-15', label: 'KW 11', date_range_label: '09.03.2026 - 15.03.2026', is_billed: false },
                                { week_start: '2026-03-16', week_end: '2026-03-22', label: 'KW 12', date_range_label: '16.03.2026 - 22.03.2026', is_billed: false },
                                { week_start: '2026-03-23', week_end: '2026-03-29', label: 'KW 13', date_range_label: '23.03.2026 - 29.03.2026', is_billed: false },
                                { week_start: '2026-03-30', week_end: '2026-04-05', label: 'KW 14', date_range_label: '30.03.2026 - 05.04.2026', is_billed: false },
                                { week_start: '2026-04-06', week_end: '2026-04-12', label: 'KW 15', date_range_label: '06.04.2026 - 12.04.2026', is_billed: false },
                            ],
                        },
                    },
                }),
            ],
            stubs: {
                'v-col': { template: '<div><slot /></div>' },
                'v-btn': {
                    props: ['color', 'disabled', 'prependIcon', 'rounded', 'variant'],
                    template: '<button :disabled="disabled" :data-color="color" :data-variant="variant" :data-prepend-icon="prependIcon" :data-rounded="rounded" @click="$emit(\'click\')"><slot /></button>',
                },
                'v-progress-linear': { template: '<div />' },
                'v-alert': { template: '<div><slot /></div>' },
                'v-icon': { template: '<span />' },
                'v-dialog': {
                    props: {
                        modelValue: Boolean,
                        persistent: Boolean,
                    },
                    template: '<div v-if="modelValue" class="dialog-stub" :data-persistent="persistent ? \'true\' : \'false\'"><slot /></div>',
                },
                'v-card': { template: '<div><slot /></div>' },
                'v-card-title': { template: '<div><slot /></div>' },
                'v-card-text': { template: '<div><slot /></div>' },
                'v-card-actions': { template: '<div><slot /></div>' },
                ItsGridBox: {
                    props: ['title'],
                    template: '<section><h3>{{ title }}</h3><slot /></section>',
                },
            },
        },
    })
}

describe('Restaurant billing reports card', () => {
    it('opens a stored billing pdf from the recent history list', async () => {
        const openSpy = vi.spyOn(window, 'open').mockImplementation(() => null)
        const wrapper = mountBillingReportsCard()

        expect(wrapper.text()).toContain('Abrechnung drucken')
        expect(wrapper.text()).toContain('Kalenderwochen')
        expect(wrapper.text()).toContain('Zeitraum')
        expect(wrapper.text()).toContain('Letzte Abrechnungen')
        expect(wrapper.text().indexOf('Kalenderwochen')).toBeLessThan(wrapper.text().indexOf('Zeitraum'))
        expect(wrapper.text().indexOf('Kalenderwochen')).toBeLessThan(wrapper.text().indexOf('Letzte Abrechnungen'))

        await wrapper.find('[data-testid="restaurant-billing-history-4"]').trigger('click')

        expect(openSpy).toHaveBeenCalledWith(
            '/api/admin/restaurant/billings/4/print',
            '_blank',
            'noopener',
        )

        openSpy.mockRestore()
    })

    it('extends the selection across connected weeks and shows a persistent confirmation dialog', async () => {
        const wrapper = mountBillingReportsCard()

        expect(wrapper.text()).toContain('KW 15')

        await wrapper.find('[data-testid="restaurant-billing-week-2026-03-30"]').trigger('click')

        expect(wrapper.text()).toContain('KW 08-14/2026')
        expect(wrapper.text()).toContain('16.02.2026 - 05.04.2026')

        const createButton = wrapper.findAll('button').find((button) => button.text().includes('Abrechnung drucken'))
        await createButton?.trigger('click')

        const dialog = wrapper.find('.dialog-stub')
        expect(dialog.exists()).toBe(true)
        expect(dialog.attributes('data-persistent')).toBe('true')
        expect(wrapper.text()).toContain('Soll der Zeitraum')
    })

    it('opens a preview pdf for the selected weeks without opening the confirm dialog', async () => {
        const openSpy = vi.spyOn(window, 'open').mockImplementation(() => null)
        const wrapper = mountBillingReportsCard()

        await wrapper.find('[data-testid="restaurant-billing-week-2026-03-30"]').trigger('click')

        const previewButton = wrapper.findAll('button').find((button) => button.text().includes('Abrechnung ansehen'))
        await previewButton?.trigger('click')

        expect(openSpy).toHaveBeenCalledWith(
            '/api/admin/restaurant/billings/preview?weeks%5B%5D=2026-02-16&weeks%5B%5D=2026-02-23&weeks%5B%5D=2026-03-02&weeks%5B%5D=2026-03-09&weeks%5B%5D=2026-03-16&weeks%5B%5D=2026-03-23&weeks%5B%5D=2026-03-30',
            '_blank',
            'noopener',
        )
        expect(wrapper.find('.dialog-stub').exists()).toBe(false)

        openSpy.mockRestore()
    })

    it('renders the preview button with an info tonal style', async () => {
        const wrapper = mountBillingReportsCard()

        await wrapper.find('[data-testid="restaurant-billing-week-2026-03-30"]').trigger('click')

        const previewButton = wrapper.findAll('button').find((button) => button.text().includes('Abrechnung ansehen'))

        expect(previewButton?.attributes('data-color')).toBe('info')
        expect(previewButton?.attributes('data-variant')).toBe('tonal')
        expect(previewButton?.attributes('data-prepend-icon')).toBe('mdi-eye-outline')
    })

    it('hides the preview button after a billing was created until another week is selected', async () => {
        const openSpy = vi.spyOn(window, 'open').mockImplementation(() => null)
        const wrapper = mountBillingReportsCard()
        const billingStore = useRestaurantBillingStore()

        billingStore.create = vi.fn().mockResolvedValue({ id: 99 })

        await wrapper.find('[data-testid="restaurant-billing-week-2026-03-30"]').trigger('click')

        const createButton = wrapper.findAll('button').find((button) => button.text().includes('Abrechnung drucken'))
        await createButton?.trigger('click')

        const confirmButton = wrapper.findAll('button').find((button) => button.text().includes('Jetzt abrechnen'))
        await confirmButton?.trigger('click')
        await flushPromises()

        expect(billingStore.create).toHaveBeenCalledWith({
            weeks: ['2026-02-16', '2026-02-23', '2026-03-02', '2026-03-09', '2026-03-16', '2026-03-23', '2026-03-30'],
        })
        expect(openSpy).toHaveBeenCalledWith('/api/admin/restaurant/billings/99/print', '_blank', 'noopener')
        expect(wrapper.findAll('button').some((button) => button.text().includes('Abrechnung ansehen'))).toBe(false)

        await wrapper.find('[data-testid="restaurant-billing-week-2026-02-16"]').trigger('click')

        expect(wrapper.findAll('button').some((button) => button.text().includes('Abrechnung ansehen'))).toBe(true)

        openSpy.mockRestore()
    })
})
