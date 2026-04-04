import { createTestingPinia } from '@pinia/testing'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Restaurant from '@/pages/homepage/index/Restaurant.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    },
}))

import axios from 'axios'

const VBtnStub = {
    emits: ['click'],
    props: ['disabled'],
    template: '<button type="button" :disabled="disabled" v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
}

const VDialogStub = {
    props: ['modelValue'],
    template: '<div v-if="modelValue" data-testid="dialog"><slot /></div>',
}

const vuetifyStubs = {
    'router-link': { template: '<a><slot /></a>' },
    'v-app': { template: '<div><slot /></div>' },
    'v-layout': { template: '<div><slot /></div>' },
    'v-main': { template: '<main><slot /></main>' },
    'v-footer': { template: '<footer><slot /></footer>' },
    'v-row': { template: '<div><slot /></div>' },
    'v-col': { template: '<div><slot /></div>' },
    'v-card': { template: '<div><slot /></div>' },
    'v-card-title': { template: '<div><slot /></div>' },
    'v-card-text': { template: '<div><slot /></div>' },
    'v-card-actions': { template: '<div><slot /></div>' },
    'v-icon': { template: '<i><slot /></i>' },
    'v-alert': { template: '<div><slot /></div>' },
    'v-form': { template: '<form><slot /></form>' },
    'v-text-field': { template: '<input />' },
    'v-textarea': { template: '<textarea></textarea>' },
    'v-select': { template: '<select></select>' },
    'v-otp-input': { template: '<input />' },
    'v-overlay': { template: '<div><slot /></div>' },
    'v-spacer': { template: '<span />' },
    'v-btn': VBtnStub,
    'v-dialog': VDialogStub,
    VBtn: VBtnStub,
    VDialog: VDialogStub,
}

describe('homepage restaurant booking dialog', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        vi.mocked(axios.get).mockImplementation((url: string) => {
            if (url === '/api/homepage/restaurant/menu-plans') {
                return Promise.resolve({
                    data: {
                        plans: [
                            {
                                id: 10,
                                title: 'Plan',
                                is_orderable: true,
                                entries: [
                                    {
                                        id: 77,
                                        plan_date: '2026-04-10',
                                        menu_title: 'Pasta',
                                        price: '6.50',
                                        eating_times: [
                                            { id: 5, eating_time: '11:30:00' },
                                            { id: 6, eating_time: '12:15:00' },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                })
            }

            if (url === '/api/homepage/restaurant/bookings') {
                return Promise.resolve({
                    data: {
                        bookings: [],
                    },
                })
            }

            return Promise.resolve({
                data: {
                    options: [],
                    is_import116_parent: false,
                    self_name: 'Test User',
                    booking_defaults: {
                        recipients: [],
                        single_recipient_customized: false,
                    },
                },
            })
        })

        vi.mocked(axios.post).mockResolvedValue({ data: { message: 'ok' } })
        vi.mocked(axios.delete).mockResolvedValue({ data: { message: 'Buchung erfolgreich storniert.' } })
        globalThis.axios = axios as never
    })

    it('opens the booking dialog when the buchen button is clicked', async () => {
        const wrapper = mount(Restaurant, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            HomepageStore: {
                                config: {
                                    auth_check: true,
                                    auth_user: {
                                        id: 9,
                                        email: 'restaurant@example.test',
                                        first_name: 'Test',
                                        last_name: 'User',
                                        import116_parent: false,
                                    },
                                    school: {
                                        id: 3,
                                        short_name: 'CDGym',
                                        long_name: 'CDGym',
                                    },
                                    restaurant: {
                                        user_information_intro_html: '',
                                        orderable_menu_plans_count: 1,
                                        visible_menu_plans_count: 1,
                                    },
                                },
                                schools: [{ short_name: 'CDGym', long_name: 'CDGym' }],
                            },
                        },
                    }),
                ],
                stubs: vuetifyStubs,
                mocks: {
                    $route: { query: { school: 'CDGym' } },
                    $router: { replace: vi.fn() },
                },
            },
        })

        await flushPromises()

        const buchenButton = wrapper.findAll('button').find((button) => button.text() === 'Buchen')

        expect(buchenButton).toBeTruthy()

        await buchenButton!.trigger('click')
        await flushPromises()

        const eatingTimeOptions = wrapper.findAll('.booking-time-picker__option')
        let quantityOptions = wrapper.findAll('.booking-quantity-picker__option')
        const moreQuantityButton = wrapper.find('.booking-quantity-picker__more')
        const dialogBuchenButton = wrapper.findAll('button').filter((button) => button.text() === 'Buchen').at(-1)

        expect((wrapper.vm as any).showBookingDialog).toBe(true)
        expect((wrapper.vm as any).selectedMenuEntry?.id).toBe(77)
        expect((wrapper.vm as any).bookingData.restaurant_menu_plan_entry_id).toBe(77)
        expect((wrapper.vm as any).bookingData.restaurant_eating_time_id).toBeNull()
        expect((wrapper.vm as any).bookingData.quantity).toBe(1)
        expect((wrapper.vm as any).bookingData.recipients).toEqual([
            { name: 'Test User', type: 'self', import116_id: null },
        ])
        expect((wrapper.vm as any).bookingRecipientOverride).toBe(false)
        expect(eatingTimeOptions).toHaveLength(2)
        expect(quantityOptions).toHaveLength(1)
        expect(quantityOptions[0].text()).toBe('1')
        expect(moreQuantityButton.exists()).toBe(true)
        expect(dialogBuchenButton).toBeTruthy()
        expect(dialogBuchenButton!.attributes('disabled')).toBeDefined()

        await moreQuantityButton.trigger('click')
        await flushPromises()

        quantityOptions = wrapper.findAll('.booking-quantity-picker__option')

        expect(quantityOptions).toHaveLength(4)

        await quantityOptions[2].trigger('click')
        await flushPromises()

        expect((wrapper.vm as any).bookingData.quantity).toBe(3)
        expect((wrapper.vm as any).bookingData.recipients).toEqual([
            { name: 'Test User', type: 'self', import116_id: null },
            { name: '', type: 'other_person', import116_id: null },
            { name: '', type: 'other_person', import116_id: null },
        ])

        await eatingTimeOptions[0].trigger('click')
        await flushPromises()

        expect((wrapper.vm as any).bookingData.restaurant_eating_time_id).toBe(5)
        expect(dialogBuchenButton!.element.hasAttribute('disabled')).toBe(true)

        await quantityOptions[0].trigger('click')
        await flushPromises()

        expect((wrapper.vm as any).bookingData.quantity).toBe(1)
        expect((wrapper.vm as any).bookingData.recipients).toEqual([
            { name: 'Test User', type: 'self', import116_id: null },
        ])
        expect(dialogBuchenButton!.element.hasAttribute('disabled')).toBe(false)
        expect(wrapper.text()).toContain('Menü buchen')
    })

    it('opens the restaurant overview pdf download for the current school', async () => {
        const openSpy = vi.spyOn(window, 'open').mockImplementation(() => null as never)

        const wrapper = mount(Restaurant, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            HomepageStore: {
                                config: {
                                    auth_check: true,
                                    auth_user: {
                                        id: 9,
                                        email: 'restaurant@example.test',
                                        first_name: 'Test',
                                        last_name: 'User',
                                    },
                                    school: {
                                        id: 3,
                                        short_name: 'CDGym',
                                        long_name: 'CDGym',
                                    },
                                    restaurant: {
                                        user_information_intro_html: '',
                                        orderable_menu_plans_count: 1,
                                        visible_menu_plans_count: 1,
                                    },
                                },
                                schools: [{ short_name: 'CDGym', long_name: 'CDGym' }],
                            },
                        },
                    }),
                ],
                stubs: vuetifyStubs,
                mocks: {
                    $route: { query: { school: 'CDGym' } },
                    $router: { replace: vi.fn() },
                },
            },
        })

        await flushPromises()

        const printButton = wrapper.findAll('button').find((button) => button.text() === 'PDF drucken')

        expect(printButton).toBeTruthy()

        await printButton!.trigger('click')

        expect(openSpy).toHaveBeenCalledWith('/api/homepage/restaurant/print?school=CDGym', '_blank', 'noopener')

        openSpy.mockRestore()
    })

    it('shows booked quantities inline and allows inline cancellation', async () => {
        vi.mocked(axios.get).mockImplementation((url: string) => {
            if (url === '/api/homepage/restaurant/menu-plans') {
                return Promise.resolve({
                    data: {
                        plans: [
                            {
                                id: 10,
                                title: 'Plan',
                                is_orderable: true,
                                entries: [
                                    {
                                        id: 77,
                                        plan_date: '2026-04-10',
                                        menu_title: 'Pasta',
                                        price: '6.50',
                                        eating_times: [
                                            { id: 5, eating_time: '11:30:00' },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                })
            }

            if (url === '/api/homepage/restaurant/bookings') {
                return Promise.resolve({
                    data: {
                        bookings: [
                            {
                                id: 901,
                                menu_plan_entry_id: 77,
                                menu_title: 'Aloo Gobi',
                                plan_date: '2026-04-03T00:00:00.000000Z',
                                quantity: 3,
                                eating_time: '12:30:00',
                                child_name: 'Elmina',
                                recipients: [
                                    { name: 'Elmina Salihović', type: 'child', import116_id: 11 },
                                    { name: 'Allen Salihović', type: 'child', import116_id: 12 },
                                    { name: 'Sepp', type: 'other_person', import116_id: null },
                                ],
                                can_cancel: true,
                            },
                            {
                                id: 902,
                                menu_plan_entry_id: 88,
                                menu_title: 'Aloo Gobi',
                                plan_date: '2026-04-03T00:00:00.000000Z',
                                quantity: 2,
                                eating_time: '13:20:00',
                                child_name: 'Allen',
                                recipients: [
                                    { name: 'Elmina Salihović', type: 'child', import116_id: 11 },
                                    { name: 'Allen Salihović', type: 'child', import116_id: 12 },
                                ],
                                can_cancel: true,
                            },
                        ],
                    },
                })
            }

            return Promise.resolve({ data: { options: [] } })
        })

        const wrapper = mount(Restaurant, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            HomepageStore: {
                                config: {
                                    auth_check: true,
                                    auth_user: {
                                        id: 9,
                                        email: 'restaurant@example.test',
                                        first_name: 'Test',
                                        last_name: 'User',
                                        import116_parent: false,
                                    },
                                    school: {
                                        id: 3,
                                        short_name: 'CDGym',
                                        long_name: 'CDGym',
                                    },
                                    restaurant: {
                                        user_information_intro_html: '',
                                        orderable_menu_plans_count: 1,
                                        visible_menu_plans_count: 1,
                                    },
                                },
                                schools: [{ short_name: 'CDGym', long_name: 'CDGym' }],
                            },
                        },
                    }),
                ],
                stubs: vuetifyStubs,
                mocks: {
                    $route: { query: { school: 'CDGym' } },
                    $router: { replace: vi.fn() },
                },
            },
        })

        await flushPromises()

        const authBookings = wrapper.find('.restaurant-auth-bookings')
        const authBookingCards = authBookings.findAll('.restaurant-auth-bookings__item')
        const authBookingMenuTitles = authBookings.findAll('.restaurant-auth-bookings__entry-title')
        const authBookingRows = authBookings.findAll('.restaurant-auth-bookings__entry')
        const todaysBookingCard = authBookings.find('.restaurant-auth-bookings__item--today')

        expect(authBookings.exists()).toBe(true)
        expect(authBookingCards).toHaveLength(1)
        expect(authBookingMenuTitles).toHaveLength(1)
        expect(authBookingRows).toHaveLength(2)
        expect(todaysBookingCard.exists()).toBe(true)
        expect(authBookings.text()).toContain('Bereits gebucht')
        expect(authBookings.text()).toContain('Fr, 03.04.')
        expect(authBookings.text()).toContain('Heute')
        expect(authBookings.text()).toContain('Aloo Gobi')
        expect(authBookings.text()).toContain('3x um 12:30 Uhr · Elmina Salihović, Allen Salihović, Sepp')
        expect(authBookings.text()).toContain('2x um 13:20 Uhr · Elmina Salihović, Allen Salihović')
        expect(authBookings.text()).not.toContain('Invalid Date')

        expect(wrapper.text()).toContain('Gebucht: 3x')
        expect(wrapper.text()).toContain('Elmina Salihović')

        const cancelButton = authBookings.findAll('button').find((button) => button.text() === 'Stornieren')

        expect(cancelButton).toBeTruthy()

        await cancelButton!.trigger('click')
        await flushPromises()

        expect(wrapper.text()).toContain('Buchung stornieren?')
        expect(axios.delete).not.toHaveBeenCalled()

        const confirmCancelButton = wrapper.findAll('button').find((button) => button.text() === 'Ja, stornieren')

        expect(confirmCancelButton).toBeTruthy()

        await confirmCancelButton!.trigger('click')
        await flushPromises()

        expect(axios.delete).toHaveBeenCalledWith('/api/homepage/restaurant/bookings/901')
    })

    it('lets import116 parents switch the single-menu child with one click', async () => {
        vi.mocked(axios.get).mockImplementation((url: string) => {
            if (url === '/api/homepage/restaurant/menu-plans') {
                return Promise.resolve({
                    data: {
                        plans: [
                            {
                                id: 10,
                                title: 'Plan',
                                is_orderable: true,
                                entries: [
                                    {
                                        id: 77,
                                        plan_date: '2026-04-10',
                                        menu_title: 'Pasta',
                                        price: '6.50',
                                        eating_times: [
                                            { id: 5, eating_time: '11:30:00' },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                })
            }

            if (url === '/api/homepage/restaurant/bookings') {
                return Promise.resolve({ data: { bookings: [] } })
            }

            return Promise.resolve({
                data: {
                    options: [
                        { id: 11, name: 'Anna Beispiel' },
                        { id: 12, name: 'Ben Beispiel' },
                    ],
                    is_import116_parent: true,
                    self_name: 'Eva Beispiel',
                    booking_defaults: {
                        recipients: [],
                        single_recipient_customized: false,
                    },
                },
            })
        })

        const wrapper = mount(Restaurant, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            HomepageStore: {
                                config: {
                                    auth_check: true,
                                    auth_user: {
                                        id: 9,
                                        email: 'parent@example.test',
                                        first_name: 'Eva',
                                        last_name: 'Beispiel',
                                    },
                                    school: {
                                        id: 3,
                                        short_name: 'CDGym',
                                        long_name: 'CDGym',
                                    },
                                    restaurant: {
                                        user_information_intro_html: '',
                                        orderable_menu_plans_count: 1,
                                        visible_menu_plans_count: 1,
                                    },
                                },
                                schools: [{ short_name: 'CDGym', long_name: 'CDGym' }],
                            },
                        },
                    }),
                ],
                stubs: vuetifyStubs,
                mocks: {
                    $route: { query: { school: 'CDGym' } },
                    $router: { replace: vi.fn() },
                },
            },
        })

        await flushPromises()

        const buchenButton = wrapper.findAll('button').find((button) => button.text() === 'Buchen')

        await buchenButton!.trigger('click')
        await flushPromises()

        expect((wrapper.vm as any).bookingData.recipients).toEqual([
            { name: 'Anna Beispiel', type: 'child', import116_id: 11 },
        ])

        const childButtons = wrapper.findAll('.booking-child-picker__option')
        expect(childButtons).toHaveLength(2)

        await childButtons[1].trigger('click')
        await flushPromises()

        expect((wrapper.vm as any).bookingData.recipients).toEqual([
            { name: 'Ben Beispiel', type: 'child', import116_id: 12 },
        ])
        expect((wrapper.vm as any).bookingRecipientOverride).toBe(false)
        expect(wrapper.text()).toContain('Standardmäßig für Ben Beispiel')

        const overrideCheckbox = wrapper.find('.booking-recipient-toggle input')
        await overrideCheckbox.setValue(true)
        await flushPromises()

        expect((wrapper.vm as any).bookingData.recipients).toEqual([
            { name: '', type: 'other_person', import116_id: null },
        ])
        expect(wrapper.findAll('.booking-child-picker__option').some((button) => button.classes().includes('booking-child-picker__option--active'))).toBe(false)

        const recipientInput = wrapper.find('#booking-recipient-0')
        await recipientInput.setValue('Onkel Peter')
        await flushPromises()

        expect((wrapper.vm as any).bookingData.recipients).toEqual([
            { name: 'Onkel Peter', type: 'other_person', import116_id: null },
        ])
        expect(wrapper.findAll('.booking-child-picker__option').some((button) => button.classes().includes('booking-child-picker__option--active'))).toBe(false)

        const moreQuantityButton = wrapper.find('.booking-quantity-picker__more')
        await moreQuantityButton.trigger('click')
        await flushPromises()

        const quantityOptions = wrapper.findAll('.booking-quantity-picker__option')
        await quantityOptions[2].trigger('click')
        await flushPromises()

        expect((wrapper.vm as any).bookingData.recipients).toEqual([
            { name: 'Onkel Peter', type: 'other_person', import116_id: null },
            { name: 'Ben Beispiel', type: 'child', import116_id: 12 },
            { name: '', type: 'other_person', import116_id: null },
        ])
    })

    it('resets parent multi-menu recipients to child order after switching the single child', async () => {
        vi.mocked(axios.get).mockImplementation((url: string) => {
            if (url === '/api/homepage/restaurant/menu-plans') {
                return Promise.resolve({
                    data: {
                        plans: [
                            {
                                id: 10,
                                title: 'Plan',
                                is_orderable: true,
                                entries: [
                                    {
                                        id: 77,
                                        plan_date: '2026-04-10',
                                        menu_title: 'Pasta',
                                        price: '6.50',
                                        eating_times: [
                                            { id: 5, eating_time: '11:30:00' },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                })
            }

            if (url === '/api/homepage/restaurant/bookings') {
                return Promise.resolve({ data: { bookings: [] } })
            }

            return Promise.resolve({
                data: {
                    options: [
                        { id: 11, name: 'Elmina Salihović' },
                        { id: 12, name: 'Allen Salihović' },
                    ],
                    is_import116_parent: true,
                    self_name: 'Lejla Salihović',
                    booking_defaults: {
                        recipients: [],
                        single_recipient_customized: false,
                    },
                },
            })
        })

        const wrapper = mount(Restaurant, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            HomepageStore: {
                                config: {
                                    auth_check: true,
                                    auth_user: {
                                        id: 9,
                                        email: 'parent@example.test',
                                        first_name: 'Lejla',
                                        last_name: 'Salihović',
                                    },
                                    school: {
                                        id: 3,
                                        short_name: 'CDGym',
                                        long_name: 'CDGym',
                                    },
                                    restaurant: {
                                        user_information_intro_html: '',
                                        orderable_menu_plans_count: 1,
                                        visible_menu_plans_count: 1,
                                    },
                                },
                                schools: [{ short_name: 'CDGym', long_name: 'CDGym' }],
                            },
                        },
                    }),
                ],
                stubs: vuetifyStubs,
                mocks: {
                    $route: { query: { school: 'CDGym' } },
                    $router: { replace: vi.fn() },
                },
            },
        })

        await flushPromises()

        const buchenButton = wrapper.findAll('button').find((button) => button.text() === 'Buchen')

        await buchenButton!.trigger('click')
        await flushPromises()

        const childButtons = wrapper.findAll('.booking-child-picker__option')

        await childButtons[1].trigger('click')
        await flushPromises()

        expect((wrapper.vm as any).bookingData.recipients).toEqual([
            { name: 'Allen Salihović', type: 'child', import116_id: 12 },
        ])

        const moreQuantityButton = wrapper.find('.booking-quantity-picker__more')
        await moreQuantityButton.trigger('click')
        await flushPromises()

        const quantityOptions = wrapper.findAll('.booking-quantity-picker__option')
        await quantityOptions[1].trigger('click')
        await flushPromises()

        expect((wrapper.vm as any).bookingData.recipients).toEqual([
            { name: 'Elmina Salihović', type: 'child', import116_id: 11 },
            { name: 'Allen Salihović', type: 'child', import116_id: 12 },
        ])
    })
})
