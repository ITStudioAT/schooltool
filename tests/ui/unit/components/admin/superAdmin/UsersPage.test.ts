import { describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import { fireEvent, render, screen, waitFor } from '@testing-library/vue'
import Users from '@/pages/admin/superAdmin/components/Users.vue'
import { useUserStore } from '@/stores/admin/UserStore20'

const vBtnStub = {
    emits: ['click'],
    template: '<button type="button" v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
}

const vuetifyStubs = {
    'v-col': { template: '<div><slot /></div>' },
    VCol: { template: '<div><slot /></div>' },
    'v-btn': vBtnStub,
    VBtn: vBtnStub,
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
        const writeText = vi.fn().mockResolvedValue(undefined)
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText },
        })

        const pinia = createTestingPinia({
            createSpy: vi.fn,
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
                            email_verified_at: '01.04.2026',
                            confirmed_at: '02.04.2026',
                            login_at: '03.04.2026  08:15',
                            login_ip: '127.0.0.1',
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
        })

        render(Users, {
            global: {
                plugins: [pinia],
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
        expect(screen.getByText('E-Mail: 01.04.2026')).toBeInTheDocument()
        expect(screen.getByText('Bestätigt: 02.04.2026')).toBeInTheDocument()
        expect(screen.getByText('Letztes Login: 03.04.2026 08:15')).toBeInTheDocument()
        expect(screen.getByText('Login-IP: 127.0.0.1')).toBeInTheDocument()
        expect(screen.getAllByText('E-Mail: Nein')).toHaveLength(1)
        expect(screen.getAllByText('Bestätigt: Nein')).toHaveLength(1)
        expect(screen.getAllByText('Letztes Login: Nie')).toHaveLength(1)
        expect(screen.getAllByText('Login-IP: -')).toHaveLength(1)
        expect(screen.getByText('Letztes Login: Nie')).not.toHaveClass('is-missing')
        expect(screen.getByText('Login-IP: -')).not.toHaveClass('is-missing')
        expect(screen.queryByText(/^Klasse\s+$/)).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'E-Mail-Adresse kopieren: anna@test.local' }))

        expect(writeText).toHaveBeenCalledWith('anna@test.local')
    })

    it('does not offer locking an active super admin', async () => {
        render(Users, {
            global: {
                plugins: [
                    createTestingPinia({
                        createSpy: vi.fn,
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
                                        is_active: true,
                                        roles: ['super_admin'],
                                    },
                                ],
                                selected_users: [11],
                                meta: { total: 1, current_page: 1 },
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
            expect(screen.getByRole('button', { name: 'Ändern' })).toBeInTheDocument()
        })

        expect(screen.queryByRole('button', { name: 'Sperren' })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Entsperren' })).not.toBeInTheDocument()
    })

    it('marks selected user account status from the actions panel', async () => {
        const pinia = createTestingPinia({
            createSpy: vi.fn,
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
                            id: 12,
                            first_name: 'Ben',
                            last_name: 'Leer',
                            email: 'ben@test.local',
                            is_active: true,
                            roles: [],
                        },
                    ],
                    selected_users: [12],
                    meta: { total: 1, current_page: 1 },
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
        })

        render(Users, {
            global: {
                plugins: [pinia],
                stubs: {
                    ...vuetifyStubs,
                    SearchField: { template: '<div />' },
                    Pagination: { template: '<div />' },
                },
            },
        })

        const userStore = useUserStore()

        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'E-Mail' })).toBeInTheDocument()
        })

        await fireEvent.click(screen.getByRole('button', { name: 'E-Mail' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Bestätigen' }))

        expect(userStore.markAccountStatus).toHaveBeenCalledWith([12], 'email_verified_at')
        expect(userStore.markAccountStatus).toHaveBeenCalledWith([12], 'confirmed_at')
        expect(userStore.index).toHaveBeenCalledWith(1)
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
