import { createTestingPinia } from '@pinia/testing'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Restaurant from '@/pages/homepage/index/Restaurant.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
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

            return Promise.resolve({ data: { options: [] } })
        })

        vi.mocked(axios.post).mockResolvedValue({ data: { message: 'ok' } })
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

        await eatingTimeOptions[0].trigger('click')
        await flushPromises()

        expect((wrapper.vm as any).bookingData.restaurant_eating_time_id).toBe(5)
        expect(dialogBuchenButton!.attributes('disabled')).toBeUndefined()
        expect(wrapper.text()).toContain('Menü buchen')
    })
})
