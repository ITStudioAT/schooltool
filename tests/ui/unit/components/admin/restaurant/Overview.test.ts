import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import Overview from '@/pages/admin/restaurant/components/Overview.vue'

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
                'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
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
