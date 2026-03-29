import { createTestingPinia } from '@pinia/testing'
import { fireEvent, render, screen } from '@testing-library/vue'
import { describe, expect, it } from 'vitest'
import Roles from '@/pages/admin/superAdmin/components/Roles.vue'
import { useSuperAdminRoleStore } from '@/stores/admin/SuperAdminRoleStore'

const vuetifyStubs = {
    'v-col': { template: '<div><slot /></div>' },
    VCol: { template: '<div><slot /></div>' },
    'v-btn': {
        emits: ['click'],
        template: '<button type="button" @click="$emit(\'click\', $event)"><slot /></button>',
    },
    VBtn: {
        emits: ['click'],
        template: '<button type="button" @click="$emit(\'click\', $event)"><slot /></button>',
    },
    'v-list': { template: '<div><slot /></div>' },
    VList: { template: '<div><slot /></div>' },
    'v-list-item': { template: '<div><slot name="title" /><slot /></div>' },
    VListItem: { template: '<div><slot name="title" /><slot /></div>' },
    'v-divider': { template: '<hr />' },
    VDivider: { template: '<hr />' },
    'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
    VDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
    'v-card': { template: '<div><slot /></div>' },
    VCard: { template: '<div><slot /></div>' },
    'v-card-text': { template: '<div><slot /></div>' },
    VCardText: { template: '<div><slot /></div>' },
    'v-row': { template: '<div><slot /></div>' },
    VRow: { template: '<div><slot /></div>' },
    'v-form': { template: '<form><slot /></form>' },
    VForm: { template: '<form><slot /></form>' },
    'v-text-field': { props: ['label'], template: '<label><span>{{ label }}</span><input /></label>' },
    VTextField: { props: ['label'], template: '<label><span>{{ label }}</span><input /></label>' },
    'v-checkbox': { props: ['label'], template: '<label><input type="checkbox" /><span>{{ label }}</span></label>' },
    VCheckbox: { props: ['label'], template: '<label><input type="checkbox" /><span>{{ label }}</span></label>' },
    'v-switch': {
        props: ['modelValue', 'label'],
        emits: ['update:modelValue'],
        template: '<label><button type="button" @click="$emit(\'update:modelValue\', !modelValue)">{{ label }}</button></label>',
    },
    VSwitch: {
        props: ['modelValue', 'label'],
        emits: ['update:modelValue'],
        template: '<label><button type="button" @click="$emit(\'update:modelValue\', !modelValue)">{{ label }}</button></label>',
    },
}

describe('Roles admin access flag UI', () => {
    it('shows the admin shell badge for roles with is_admin', () => {
        render(Roles, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                action: '',
                            },
                            SuperAdminRoleStore: {
                                roles: [
                                    { id: 1, name: 'custom_admin_shell', is_admin: true },
                                    { id: 2, name: 'simple_role', is_admin: false },
                                ],
                                selected_roles: [],
                                meta: { total: 2 },
                                search_string: '',
                                data: {},
                            },
                        },
                    }),
                ],
                stubs: {
                    ...vuetifyStubs,
                    SearchField: { template: '<div />' },
                    Pagination: { template: '<div />' },
                },
            },
        })

        expect(screen.getByText('custom_admin_shell')).toBeInTheDocument()
        expect(screen.getByText('simple_role')).toBeInTheDocument()
        expect(screen.getByText('Zugang zu /admin')).toBeInTheDocument()
    })

    it('shows the admin access checkbox and explanation in the role dialog', () => {
        render(Roles, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                action: 'edit_role',
                            },
                            SuperAdminRoleStore: {
                                roles: [],
                                selected_roles: [],
                                meta: { total: 0 },
                                search_string: '',
                                data: { id: 7, name: 'custom_admin_shell', is_admin: true },
                            },
                        },
                    }),
                ],
                stubs: {
                    ...vuetifyStubs,
                    SearchField: { template: '<div />' },
                    Pagination: { template: '<div />' },
                },
            },
        })

        expect(screen.getByText('Admin-Zugang (/admin)')).toBeInTheDocument()
        expect(screen.getByText(/dürfen Benutzer mit dieser Rolle den Adminbereich unter/i)).toBeInTheDocument()
    })

    it('toggles is_admin directly from the list', async () => {
        render(Roles, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                action: '',
                            },
                            SuperAdminRoleStore: {
                                roles: [
                                    { id: 1, name: 'custom_admin_shell', is_admin: true },
                                ],
                                selected_roles: [],
                                meta: { total: 1 },
                                search_string: '',
                                data: {},
                            },
                        },
                    }),
                ],
                stubs: {
                    ...vuetifyStubs,
                    SearchField: { template: '<div />' },
                    Pagination: { template: '<div />' },
                },
            },
        })

        const roleStore = useSuperAdminRoleStore()

        await fireEvent.click(screen.getByRole('button', { name: 'aktiv' }))

        expect(roleStore.update).toHaveBeenCalledWith({
            id: 1,
            name: 'custom_admin_shell',
            is_admin: false,
        })
    })
})
