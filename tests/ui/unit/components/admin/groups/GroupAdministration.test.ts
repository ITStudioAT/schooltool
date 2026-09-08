import { createTestingPinia } from '@pinia/testing'
import { flushPromises, mount } from '@vue/test-utils'
import { reactive } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import GroupAdministration from '@/pages/admin/groups/GroupAdministration.vue'

function mountAdministration(roles = ['super_admin'], panel = 'groups_overview') {
    const route = reactive({ query: { panel } })
    const replace = vi.fn(async (location) => {
        if (typeof location === 'object') {
            route.query = location.query
        }
    })
    const wrapper = mount(GroupAdministration, {
        global: {
            plugins: [createTestingPinia({
                createSpy: vi.fn,
                initialState: { AdminAdminStore: { config: { roles } } },
            })],
            mocks: { $route: route, $router: { replace } },
            stubs: {
                AdminSectionHero: true,
                Groups: { props: ['embedded', 'embeddedFilter'], template: '<div data-testid="group-content" :data-embedded="embedded" :data-filter="embeddedFilter" />' },
                'v-container': { template: '<div><slot /></div>' },
                'v-sheet': { template: '<div><slot /></div>' },
                'v-btn': { template: '<button v-bind="$attrs"><slot /></button>' },
                'v-btn-toggle': {
                    emits: ['update:modelValue'],
                    template: '<div @click="$emit(\'update:modelValue\', $event.target.closest(\'button\')?.value)"><slot /></div>',
                },
            },
        },
    })

    return { wrapper, replace }
}

describe('Standalone group administration', () => {
    it('preserves the embedded overview and own-group views when navigating', async () => {
        const { wrapper, replace } = mountAdministration()
        await flushPromises()

        expect(wrapper.get('[data-testid="group-content"]').attributes('data-embedded')).toBe('true')
        expect(wrapper.get('[data-testid="group-content"]').attributes('data-filter')).toBe('')
        await wrapper.get('button[value="groups_own"]').trigger('click')
        await flushPromises()
        expect(replace).toHaveBeenCalledWith({ path: '/admin/groups', query: { panel: 'groups_own' } })
        expect(wrapper.get('[data-testid="group-content"]').attributes('data-filter')).toBe('own')
        expect(wrapper.get('button[value="groups_own"]').attributes('aria-pressed')).toBe('true')

        await wrapper.get('button[value="groups_overview"]').trigger('click')
        await flushPromises()
        expect(wrapper.get('[data-testid="group-content"]').attributes('data-filter')).toBe('')
        wrapper.unmount()
    })

    it.each(['groups_own', 'invalid'])('handles direct panel %s', async (panel) => {
        const { wrapper } = mountAdministration(['super_admin'], panel)
        await flushPromises()

        expect(wrapper.get('[data-testid="group-content"]').attributes('data-filter')).toBe(panel === 'groups_own' ? 'own' : '')
        wrapper.unmount()
    })

    it.each(['admin', 'materials_admin', 'materials_moderator', 'teacher', 'lunch_admin'])('does not mount group contents for %s', async (role) => {
        const { wrapper, replace } = mountAdministration([role])
        await flushPromises()

        expect(wrapper.find('[data-testid="group-content"]').exists()).toBe(false)
        expect(replace).toHaveBeenCalledWith('/admin')
        wrapper.unmount()
    })
})
