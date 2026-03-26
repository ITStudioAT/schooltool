import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Users from '@/pages/admin/restaurant/components/Users.vue'
import { useRestaurantUserStore } from '@/stores/admin/restaurant/RestaurantUserStore'

vi.mock('@/stores/admin/restaurant/RestaurantUserStore', () => ({
    useRestaurantUserStore: vi.fn(),
}))

function mountUsers(storeOverrides: Record<string, unknown> = {}) {
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
                roles: ['lunch_user'],
            },
        ],
        meta: {
            current_page: 1,
            last_page: 3,
            total: 21,
            from: 1,
            to: 10,
        },
        search_string: '',
        index: vi.fn().mockResolvedValue(true),
        updateSepa: vi.fn().mockResolvedValue(true),
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
            stubs: {
                'v-col': { template: '<div><slot /></div>' },
                'v-card': { template: '<div><slot /></div>' },
                'v-card-text': { template: '<div><slot /></div>' },
                'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
                'v-chip': { template: '<span><slot /></span>' },
                'v-icon': { template: '<i />' },
                'v-alert': { template: '<div><slot /></div>' },
                'v-pagination': { template: '<div class="v-pagination" />' },
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
        expect(wrapper.text()).toContain('Mittag Anna')
        expect(wrapper.text()).toContain('anna@example.test')
        expect(wrapper.text()).toContain('Import116 #42')
        expect(wrapper.text()).toContain('SEPA')
        expect(wrapper.text()).toContain('Kinder')
        expect(wrapper.text()).toContain('Lena Mittag')
        expect(wrapper.text()).toContain('lena.schueler@example.test')
        expect(wrapper.text()).toContain('SEPA entfernen')
        expect(wrapper.text()).toContain('1 - 10 von 21')
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

    it('toggles the SEPA state for a user from the list', async () => {
        const { wrapper, store } = mountUsers()
        ;(wrapper.vm as any).restaurantUserStore = store

        await (wrapper.vm as any).toggleSepa(store.users[0])

        expect(store.updateSepa).toHaveBeenCalledWith(1, false)
    })
})
