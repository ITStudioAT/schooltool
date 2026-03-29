import { describe, expect, it } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import { render, screen, waitFor } from '@testing-library/vue'
import Users from '@/pages/admin/superAdmin/components/Users.vue'

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
    'v-icon': { template: '<i><slot /></i>' },
    VIcon: { template: '<i><slot /></i>' },
    'v-chip': { template: '<span><slot /></span>' },
    VChip: { template: '<span><slot /></span>' },
    'v-divider': { template: '<hr />' },
    VDivider: { template: '<hr />' },
    'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
    VDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
    'v-card': { template: '<div><slot /></div>' },
    VCard: { template: '<div><slot /></div>' },
    'v-card-text': { template: '<div><slot /></div>' },
    VCardText: { template: '<div><slot /></div>' },
    'v-card-actions': { template: '<div><slot /></div>' },
    VCardActions: { template: '<div><slot /></div>' },
    'v-row': { template: '<div><slot /></div>' },
    VRow: { template: '<div><slot /></div>' },
    'v-form': { template: '<form><slot /></form>' },
    VForm: { template: '<form><slot /></form>' },
    'v-text-field': { template: '<input />' },
    VTextField: { template: '<input />' },
    'v-checkbox': { props: ['label', 'disabled'], template: '<label><input type="checkbox" :disabled="disabled" /><span>{{ label }}</span></label>' },
    VCheckbox: { props: ['label', 'disabled'], template: '<label><input type="checkbox" :disabled="disabled" /><span>{{ label }}</span></label>' },
}

describe('Users roles list item UI helpers', () => {
    it('renders role chips and fallback state in user list items', async () => {
        render(Users, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                action: '',
                                config: {
                                    roles: ['super_admin'],
                                    impersonation: { is_impersonating: false },
                                },
                            },
                            AdminUser20Store: {
                                users: [
                                    {
                                        id: 11,
                                        first_name: 'Anna',
                                        last_name: 'Muster',
                                        email: 'anna@test.local',
                                        schoolclass: '7B',
                                        is_active: true,
                                        roles: ['super_admin', 'teaching_admin'],
                                    },
                                    {
                                        id: 12,
                                        first_name: 'Ben',
                                        last_name: 'Leer',
                                        email: 'ben@test.local',
                                        schoolclass: '   ',
                                        is_active: true,
                                        roles: [],
                                    },
                                ],
                                selected_users: [],
                                meta: { total: 2 },
                                data: {},
                                search_string: '',
                                answer: null,
                                role: '',
                            },
                            AdminRoleStore: {
                                roles: [],
                                selected_role: null,
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

        await waitFor(() => {
            expect(screen.getByText('Super Admin')).toBeInTheDocument()
        })
        expect(screen.getByText('Teaching Admin')).toBeInTheDocument()
        expect(screen.getByText('Keine Rolle')).toBeInTheDocument()
        expect(screen.getByText('Klasse 7B')).toBeInTheDocument()
        expect(screen.queryByText(/^Klasse\s+$/)).not.toBeInTheDocument()
    })

    it('normalizes role names from arrays and comma-separated strings', () => {
        const methods = (Users as any).methods

        expect(methods.normalizeRoleNames(['admin', 'teacher', 'admin'])).toEqual(['admin', 'teacher'])
        expect(methods.normalizeRoleNames('admin, teacher,admin')).toEqual(['admin', 'teacher'])
        expect(methods.normalizeRoleNames(null)).toEqual([])
    })

    it('formats role labels with title case and spaces', () => {
        const methods = (Users as any).methods

        expect(methods.formatRoleLabel('teaching_admin')).toBe('Teaching Admin')
        expect(methods.formatRoleLabel('super_admin')).toBe('Super Admin')
    })

    it('normalizes school classes before rendering them', () => {
        const methods = (Users as any).methods

        expect(methods.normalizedSchoolclass(' 7B ')).toBe('7B')
        expect(methods.normalizedSchoolclass('')).toBe('')
        expect(methods.normalizedSchoolclass(null)).toBe('')
    })

    it('lets admin manage regular roles while keeping super_admin locked', async () => {
        render(Users, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                action: 'edit_user',
                                config: {
                                    roles: ['admin'],
                                    impersonation: { is_impersonating: false },
                                },
                            },
                            AdminUser20Store: {
                                users: [],
                                selected_users: [],
                                meta: { total: 0 },
                                data: {
                                    id: 77,
                                    first_name: 'Admin',
                                    last_name: 'Editor',
                                    email: 'editor@test.local',
                                    roles: [
                                        { name: 'super_admin', checked: true },
                                        { name: 'teacher', checked: false },
                                    ],
                                },
                                search_string: '',
                                answer: null,
                                role: '',
                            },
                            AdminRoleStore: {
                                roles: [],
                                selected_role: null,
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

        await waitFor(() => {
            expect(screen.getByText(/können alle Rollen außer/i)).toBeInTheDocument()
        })

        expect(screen.getByLabelText('teacher')).toBeEnabled()
        expect(screen.getByLabelText('super_admin')).toBeDisabled()
        expect(screen.getAllByRole('checkbox')).toHaveLength(2)
    })

})
