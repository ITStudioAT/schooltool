import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Users from '@/pages/admin/restaurant/components/Users.vue'
import { useRestaurantUserStore } from '@/stores/admin/restaurant/RestaurantUserStore'

vi.mock('@/stores/admin/restaurant/RestaurantUserStore', () => ({
    useRestaurantUserStore: vi.fn(),
}))

function mountUsers(storeOverrides: Record<string, unknown> = {}, routeQuery: Record<string, unknown> = {}) {
    const store = {
        users: [
            {
                id: 1,
                first_name: 'Anna',
                last_name: 'Mittag',
                email: 'anna@example.test',
                schoolclass: '3A',
                phone: '0664 123456',
                import116_id: 42,
                origin_labels: ['Import116', 'Eltern'],
                import116_children: [
                    {
                        name: 'Lena Mittag',
                        email: 'lena.schueler@example.test',
                    },
                    {
                        name: 'Paul Mittag',
                        email: 'paul.schueler@example.test',
                    },
                ],
                has_sepa: true,
                login_at: '26.03.2026  08:30',
                is_verified: true,
                is_confirmed: false,
                is_restaurant_confirmed: false,
                roles: ['lunch_user'],
            },
        ],
        meta: {
            current_page: 1,
            last_page: 3,
            total: 21,
            from: 1,
            to: 10,
            pending_confirmation_total: 4,
        },
        search_string: '',
        only_pending_confirmation: false,
        only_without_sepa: false,
        index: vi.fn().mockResolvedValue(true),
        updateSepa: vi.fn().mockResolvedValue(true),
        confirmUser: vi.fn().mockResolvedValue(true),
        destroyCandidate: vi.fn().mockResolvedValue(true),
        ...storeOverrides,
    }

    vi.mocked(useRestaurantUserStore).mockReturnValue(store as never)

    const wrapper = mount(Users, {
        global: {
            plugins: [
                createTestingPinia({
                    createSpy: vi.fn,
                }),
            ],
            mocks: {
                $route: {
                    query: routeQuery,
                },
                $router: {
                    replace: vi.fn(() => Promise.resolve()),
                },
            },
            stubs: {
                'v-col': { template: '<div><slot /></div>' },
                'v-card': { template: '<div><slot /></div>' },
                'v-card-text': { template: '<div><slot /></div>' },
                'v-card-title': { template: '<div><slot /></div>' },
                'v-card-actions': { template: '<div><slot /></div>' },
                'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
                'v-chip': { template: '<span><slot /></span>' },
                'v-icon': { template: '<i />' },
                'v-alert': { template: '<div><slot /></div>' },
                'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
                'v-pagination': { template: '<div class="v-pagination" />' },
                'v-spacer': { template: '<div />' },
                'v-text-field': {
                    props: ['modelValue'],
                    emits: ['update:modelValue', 'keyup.enter', 'click:clear'],
                    template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                },
                ItsGridBox: {
                    props: ['title'],
                    template: '<section><h3>{{ title }}</h3><slot /><slot name="header-actions" /></section>',
                },
            },
        },
    })

    return { wrapper, store }
}

describe('Restaurant users component', () => {
    beforeEach(() => {
        vi.mocked(useRestaurantUserStore).mockReset()
    })

    it('loads lunch users on mount and renders the current user list', async () => {
        const { wrapper, store } = mountUsers()

        expect(store.index).toHaveBeenCalledWith()
        expect(wrapper.text()).toContain('Restaurant-Benutzer suchen')
        expect(wrapper.text()).toContain('Benutzer zu bestätigen')
        expect(wrapper.text()).toContain('4')
        expect(wrapper.text()).toContain('Nur zu bestätigen')
        expect(wrapper.text()).toContain('Alle')
        expect(wrapper.text()).toContain('ohne SEPA')
        expect(wrapper.text()).toContain('Mittag Anna')
        expect(wrapper.text()).toContain('anna@example.test')
        expect(wrapper.text()).toContain('Import116 #42')
        expect(wrapper.text()).toContain('Herkunft')
        expect(wrapper.text()).toContain('Import116')
        expect(wrapper.text()).toContain('Eltern')
        expect(wrapper.text()).toContain('SEPA')
        expect(wrapper.text()).toContain('Kinder')
        expect(wrapper.text()).toContain('Lena Mittag')
        expect(wrapper.text()).toContain('lena.schueler@example.test')
        expect(wrapper.text()).toContain('Restaurant offen')
        expect(wrapper.text()).toContain('SEPA entfernen')
        expect(wrapper.text()).toContain('1 - 10 von 21')
    })

    it('reads the pending confirmation filter from the route on mount', async () => {
        const { wrapper, store } = mountUsers({}, {
            only_pending_confirmation: '1',
        })

        expect(store.only_pending_confirmation).toBe(true)
        expect(store.index).toHaveBeenCalledWith()
        expect(wrapper.text()).toContain('Filter aktiv')
        expect(wrapper.text()).toContain('Es werden nur Benutzer angezeigt, die noch bestätigt werden müssen.')
        expect(wrapper.text()).toContain('Filter aufheben')
    })

    it('reads the without-sepa filter from the route on mount', async () => {
        const { store } = mountUsers({}, {
            only_without_sepa: '1',
        })

        expect(store.only_without_sepa).toBe(true)
        expect(store.index).toHaveBeenCalledWith()
    })

    it('applies a search and reloads the first page', async () => {
        const { wrapper, store } = mountUsers()
        ;(wrapper.vm as any).restaurantUserStore = store
        ;(wrapper.vm as any).searchDraft = 'Anna'

        await (wrapper.vm as any).applySearch()

        expect(store.search_string).toBe('Anna')
        expect(store.index).toHaveBeenCalledWith(1)
    })

    it('loads the selected page through the pagination handler', async () => {
        const { wrapper, store } = mountUsers()
        ;(wrapper.vm as any).restaurantUserStore = store

        await (wrapper.vm as any).handlePageChange(3)

        expect(store.index).toHaveBeenCalledWith(3)
    })

    it('switches to the pending confirmation filter and reloads the first page', async () => {
        const { wrapper, store } = mountUsers()
        ;(wrapper.vm as any).restaurantUserStore = store

        await (wrapper.vm as any).setPendingConfirmationFilter(true)

        expect(store.only_pending_confirmation).toBe(true)
        expect(store.index).toHaveBeenCalledWith(1)
    })

    it('switches to the without-sepa filter and reloads the first page', async () => {
        const { wrapper, store } = mountUsers()
        ;(wrapper.vm as any).restaurantUserStore = store

        await (wrapper.vm as any).setSepaFilter(true)

        expect(store.only_without_sepa).toBe(true)
        expect(store.index).toHaveBeenCalledWith(1)
    })

    it('lets users clear the active pending confirmation banner filter', async () => {
        const { wrapper, store } = mountUsers({
            only_pending_confirmation: true,
        }, {
            only_pending_confirmation: '1',
        })
        ;(wrapper.vm as any).restaurantUserStore = store

        await (wrapper.vm as any).setPendingConfirmationFilter(false)

        expect(store.only_pending_confirmation).toBe(false)
        expect(store.index).toHaveBeenCalledWith(1)
    })

    it('opens a persistent confirmation dialog before removing SEPA', async () => {
        const { wrapper, store } = mountUsers()
        ;(wrapper.vm as any).restaurantUserStore = store

        await (wrapper.vm as any).toggleSepa(store.users[0])

        expect(store.updateSepa).not.toHaveBeenCalled()
        expect((wrapper.vm as any).sepaDialog).toBe(true)
        expect((wrapper.vm as any).pendingSepaRemovalUser?.id).toBe(1)
        expect(wrapper.text()).toContain('SEPA wirklich entfernen?')
        expect(wrapper.text()).toContain('Dabei werden auch die gespeicherten SEPA-Lastschriftmandate für diesen Benutzer gelöscht.')
    })

    it('confirms the SEPA removal and reloads the current page', async () => {
        const { wrapper, store } = mountUsers()
        ;(wrapper.vm as any).restaurantUserStore = store

        await (wrapper.vm as any).toggleSepa(store.users[0])
        await (wrapper.vm as any).confirmSepaRemoval()

        expect(store.updateSepa).toHaveBeenCalledWith(1, false)
        expect(store.index).toHaveBeenCalledWith(1)
        expect((wrapper.vm as any).sepaDialog).toBe(false)
        expect((wrapper.vm as any).pendingSepaRemovalUser).toBeNull()
    })

    it('confirms a pending restaurant user from the list', async () => {
        const { wrapper, store } = mountUsers({
            users: [
                {
                    id: 1,
                    first_name: 'Anna',
                    last_name: 'Mittag',
                    email: 'anna@example.test',
                    has_sepa: false,
                    is_verified: true,
                    is_confirmed: false,
                    is_restaurant_confirmed: false,
                    roles: ['lunch_candidate'],
                },
            ],
        })
        ;(wrapper.vm as any).restaurantUserStore = store

        await (wrapper.vm as any).confirmRestaurantUser(store.users[0])

        expect(store.confirmUser).toHaveBeenCalledWith(1)
    })

    it('deletes a pending restaurant candidate and reloads the page', async () => {
        const { wrapper, store } = mountUsers({
            users: [
                {
                    id: 1,
                    first_name: 'Anna',
                    last_name: 'Mittag',
                    email: 'anna@example.test',
                    has_sepa: false,
                    is_verified: true,
                    is_confirmed: false,
                    is_restaurant_confirmed: false,
                    roles: ['lunch_candidate'],
                },
            ],
            meta: {
                current_page: 2,
                last_page: 2,
                total: 11,
                from: 11,
                to: 11,
                pending_confirmation_total: 1,
            },
        })
        ;(wrapper.vm as any).restaurantUserStore = store
        ;(wrapper.vm as any).pendingDeleteUser = store.users[0]

        await (wrapper.vm as any).confirmDeleteCandidate()

        expect(store.destroyCandidate).toHaveBeenCalledWith(1)
        expect(store.index).toHaveBeenCalledWith(1)
    })
})
