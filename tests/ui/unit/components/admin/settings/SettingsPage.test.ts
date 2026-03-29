import { readFileSync } from 'node:fs'
import { createTestingPinia } from '@pinia/testing'
import { fireEvent, render, screen, waitFor } from '@testing-library/vue'
import { defineComponent, h, inject, provide } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import Settings from '@/pages/admin/settings/Settings.vue'

const VBtnToggleStub = defineComponent({
    props: ['modelValue'],
    emits: ['update:modelValue'],
    setup(_props, { slots, emit }) {
        provide('buttonToggleUpdateModel', (value: unknown) => emit('update:modelValue', value))

        return () => h('div', slots.default ? slots.default() : [])
    },
})

const VBtnStub = defineComponent({
    props: ['value'],
    emits: ['click'],
    setup(props, { slots, emit }) {
        const toggleUpdater = inject<(value: unknown) => void>('buttonToggleUpdateModel', undefined)

        const onClick = (event: MouseEvent) => {
            if (toggleUpdater && props.value !== undefined) {
                toggleUpdater(props.value)
            }

            emit('click', event)
        }

        return () => h('button', { type: 'button', onClick }, slots.default ? slots.default() : [])
    },
})

const vuetifyStubs = {
    'v-container': { template: '<div><slot /></div>' },
    VContainer: { template: '<div><slot /></div>' },
    'v-sheet': { template: '<div><slot /></div>' },
    VSheet: { template: '<div><slot /></div>' },
    'v-tabs': { template: '<div><slot /></div>' },
    VTabs: { template: '<div><slot /></div>' },
    'v-tab': { template: '<button type="button"><slot /></button>' },
    VTab: { template: '<button type="button"><slot /></button>' },
    'v-btn': VBtnStub,
    VBtn: VBtnStub,
    'v-btn-toggle': VBtnToggleStub,
    VBtnToggle: VBtnToggleStub,
    'v-icon': { template: '<i><slot /></i>' },
    VIcon: { template: '<i><slot /></i>' },
    'v-row': { template: '<div><slot /></div>' },
    VRow: { template: '<div><slot /></div>' },
    'v-col': { template: '<div><slot /></div>' },
    VCol: { template: '<div><slot /></div>' },
}

describe('Admin settings page', () => {
    it('builds the updated super-admin sub navigation with licence models and roles', () => {
        const items = (Settings as any).computed.subNavigationItems.call({
            isAdminTab: false,
        })

        expect(items.map((item: { key: string }) => item.key)).toEqual(['schools', 'licence_models', 'roles', 'school_switch', 'user_impersonation'])
        expect(items[1]).toMatchObject({
            key: 'licence_models',
            label: 'Lizenzen Modelle',
        })
        expect(items[2]).toMatchObject({
            key: 'roles',
            label: 'Rollen',
        })
    })

    it('builds the nested licence navigation with both source actions', () => {
        const items = (Settings as any).computed.visibleLicenceNavigationItems.call({})

        expect(items.map((item: { key: string }) => item.key)).toEqual(['overview', 'schools'])
        expect(items.map((item: { label: string }) => item.label)).toEqual(['Alle Lizenzen', 'Lizenzvergaben'])
    })

    it('builds the admin sub navigation with schoolyears and remaining placeholders', () => {
        const items = (Settings as any).computed.subNavigationItems.call({
            isAdminTab: true,
        })

        expect(items.map((item: { key: string }) => item.key)).toEqual(['schoolyears', 'users', 'school_groups', 'log'])
        expect(items.map((item: { label: string }) => item.label)).toEqual(['Schuljahre', 'Benutzer', 'Schulgruppen', 'Log'])
    })

    it('builds the register sub navigation with users only', () => {
        const items = (Settings as any).computed.subNavigationItems.call({
            isRegisterTab: true,
            isAdminTab: false,
        })

        expect(items.map((item: { key: string }) => item.key)).toEqual(['users'])
        expect(items.map((item: { label: string }) => item.label)).toEqual(['Benutzer'])
    })

    it('builds the materials sub navigation with settings first and material groups second', () => {
        const items = (Settings as any).computed.subNavigationItems.call({
            isMaterialsTab: true,
            isAdminTab: false,
            isRegisterTab: false,
            isTeachingTab: false,
            isGroupsTab: false,
            isTutoringTab: false,
        })

        expect(items.map((item: { key: string }) => item.key)).toEqual(['material_settings', 'material_groups'])
        expect(items.map((item: { label: string }) => item.label)).toEqual(['Einstellungen', 'Materialgruppen'])
    })

    it('builds the groups sub navigation with overview and own groups', () => {
        const items = (Settings as any).computed.subNavigationItems.call({
            isGroupsTab: true,
            isAdminTab: false,
            isRegisterTab: false,
            isTeachingTab: false,
            isMaterialsTab: false,
            isTutoringTab: false,
        })

        expect(items.map((item: { key: string }) => item.key)).toEqual(['groups_overview', 'groups_own'])
        expect(items.map((item: { label: string }) => item.label)).toEqual(['Überblick', 'Eigene Gruppen'])
    })

    it('shows the groups settings tab only for super_admin, admin, materials_admin, and materials_moderator', () => {
        const allowedItems = (Settings as any).computed.navigationItems.call({
            canAccessSuperAdminSettingsTab: false,
            canAccessAdminSettingsTab: false,
            canAccessRegisterSettingsTab: false,
            canAccessMaterialsSettingsTab: false,
            canAccessGroupsSettingsTab: true,
        })
        const deniedItems = (Settings as any).computed.navigationItems.call({
            canAccessSuperAdminSettingsTab: false,
            canAccessAdminSettingsTab: false,
            canAccessRegisterSettingsTab: false,
            canAccessMaterialsSettingsTab: false,
            canAccessGroupsSettingsTab: false,
        })

        expect(allowedItems.map((item: { key: string }) => item.key)).toContain('groups')
        expect(deniedItems.map((item: { key: string }) => item.key)).not.toContain('groups')

        const tabRoleMap = (Settings as any).computed.tabRoleMap.call({})
        expect(tabRoleMap.groups).toEqual(['super_admin', 'admin', 'materials_admin', 'materials_moderator'])
    })

    it('includes the groups settings tab in available tabs only for authorized roles', () => {
        const methods = (Settings as any).methods

        expect(methods.availableTabKeys(true, true, true, true, true)).toContain('groups')
        expect(methods.availableTabKeys(true, true, true, true, false)).not.toContain('groups')
    })

    it('shows the materials settings tab only for super_admin, admin, materials_admin, and materials_moderator', () => {
        const allowedItems = (Settings as any).computed.navigationItems.call({
            canAccessSuperAdminSettingsTab: false,
            canAccessAdminSettingsTab: false,
            canAccessRegisterSettingsTab: false,
            canAccessMaterialsSettingsTab: true,
            canAccessGroupsSettingsTab: false,
        })
        const deniedItems = (Settings as any).computed.navigationItems.call({
            canAccessSuperAdminSettingsTab: false,
            canAccessAdminSettingsTab: false,
            canAccessRegisterSettingsTab: false,
            canAccessMaterialsSettingsTab: false,
            canAccessGroupsSettingsTab: false,
        })

        expect(allowedItems.map((item: { key: string }) => item.key)).toContain('materials')
        expect(deniedItems.map((item: { key: string }) => item.key)).not.toContain('materials')

        const tabRoleMap = (Settings as any).computed.tabRoleMap.call({})
        expect(tabRoleMap.materials).toEqual(['super_admin', 'admin', 'materials_admin', 'materials_moderator'])
    })

    it('includes the materials settings tab in available tabs only for authorized roles', () => {
        const methods = (Settings as any).methods

        expect(methods.availableTabKeys(true, true, true, true, true)).toContain('materials')
        expect(methods.availableTabKeys(true, true, true, false, true)).not.toContain('materials')
    })

    it('shows the top-level admin and super-admin tabs only for allowed roles', () => {
        const allowedItems = (Settings as any).computed.navigationItems.call({
            canAccessSuperAdminSettingsTab: true,
            canAccessAdminSettingsTab: true,
        })
        const adminOnlyItems = (Settings as any).computed.navigationItems.call({
            canAccessSuperAdminSettingsTab: false,
            canAccessAdminSettingsTab: true,
        })
        const deniedItems = (Settings as any).computed.navigationItems.call({
            canAccessSuperAdminSettingsTab: false,
            canAccessAdminSettingsTab: false,
        })

        expect(allowedItems.map((item: { key: string }) => item.key)).toContain('super_admin')
        expect(allowedItems.map((item: { key: string }) => item.key)).toContain('admin')
        expect(adminOnlyItems.map((item: { key: string }) => item.key)).not.toContain('super_admin')
        expect(adminOnlyItems.map((item: { key: string }) => item.key)).toContain('admin')
        expect(deniedItems.map((item: { key: string }) => item.key)).not.toContain('super_admin')
        expect(deniedItems.map((item: { key: string }) => item.key)).not.toContain('admin')
    })

    it('renders the overtaken licence and role views from the super-admin settings destination', async () => {
        const { container } = render(Settings, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                config: {
                                    is_auth: true,
                                    roles: ['super_admin'],
                                    selected_school: { long_name: 'Testschule' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings',
                        query: {},
                    },
                    $router: {
                        replace: () => {},
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    Schools: { template: '<div>Schools Component</div>' },
                    Schoolyears: { template: '<div>Schoolyears Component</div>' },
                    Users: { template: '<div>Users Component</div>' },
                    Licences: { template: '<div>Licences Component</div>' },
                    LicenceSchools: { template: '<div>LicenceSchools Component</div>' },
                    Roles: { template: '<div>Roles Component</div>' },
                    Log: { template: '<div>Log Component</div>' },
                },
            },
        })

        expect(screen.getByText('Schools Component')).toBeInTheDocument()
        expect(screen.getByText('Lizenzen Modelle')).toBeInTheDocument()

        await fireEvent.click(screen.getByText('Lizenzen Modelle'))

        await waitFor(() => {
            expect(screen.getByText('Licences Component')).toBeInTheDocument()
        })

        expect(container.querySelector('.settings-licences-wrap')).not.toBeNull()
        expect(screen.getByText('Alle Lizenzen')).toBeInTheDocument()
        expect(screen.getByText('Lizenzvergaben')).toBeInTheDocument()

        await fireEvent.click(screen.getByText('Lizenzvergaben'))

        await waitFor(() => {
            expect(screen.getByText('LicenceSchools Component')).toBeInTheDocument()
        })

        await fireEvent.click(screen.getByText('Rollen'))

        await waitFor(() => {
            expect(screen.getByText('Roles Component')).toBeInTheDocument()
        })

        expect(container.querySelector('.settings-roles-wrap')).not.toBeNull()
    })

    it('renders the overtaken schoolyears and users views on the admin settings tab', async () => {
        const GroupsStub = defineComponent({
            props: ['embedded', 'embeddedFilter'],
            template: '<div>Groups Component {{ embedded ? "embedded" : "full" }} {{ embeddedFilter }}</div>',
        })

        const { container } = render(Settings, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                config: {
                                    is_auth: true,
                                    roles: ['admin'],
                                    selected_school: { long_name: 'Testschule' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=admin',
                        query: {
                            tab: 'admin',
                        },
                    },
                    $router: {
                        replace: () => {},
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    Schools: { template: '<div>Schools Component</div>' },
                    Schoolyears: { template: '<div>Schoolyears Component</div>' },
                    Users: { template: '<div>Users Component</div>' },
                    Groups: GroupsStub,
                    Licences: { template: '<div>Licences Component</div>' },
                    LicenceSchools: { template: '<div>LicenceSchools Component</div>' },
                    Roles: { template: '<div>Roles Component</div>' },
                    Log: { template: '<div>Log Component</div>' },
                },
            },
        })

        expect(container.querySelector('.settings-subnav')).not.toBeNull()
        expect(container.querySelector('.settings-schoolyears-wrap')).not.toBeNull()
        expect(screen.getAllByText('Schuljahre').length).toBeGreaterThan(0)
        expect(screen.getByText('Schoolyears Component')).toBeInTheDocument()
        expect(screen.queryByText('Schools Component')).not.toBeInTheDocument()
        expect(screen.queryByText('Licences Component')).not.toBeInTheDocument()
        expect(screen.queryByText('Roles Component')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByText('Benutzer'))

        await waitFor(() => {
            expect(screen.getByText('Users Component')).toBeInTheDocument()
        })

        expect(container.querySelector('.settings-users-wrap')).not.toBeNull()
        expect(screen.queryByText('Schoolyears Component')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByText('Schulgruppen'))

        await waitFor(() => {
            expect(screen.getByText('Groups Component embedded school')).toBeInTheDocument()
        })

        expect(container.querySelector('.settings-groups-wrap')).not.toBeNull()
        expect(screen.queryByText('Users Component')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByText('Log'))

        await waitFor(() => {
            expect(screen.getByText('Log Component')).toBeInTheDocument()
        })

        expect(container.querySelector('.settings-log-wrap')).not.toBeNull()
        expect(screen.queryByText('Users Component')).not.toBeInTheDocument()
    })

    it('renders the materials settings tab with Einstellungen first and Materialgruppen second', async () => {
        const GroupsStub = defineComponent({
            props: ['embedded', 'embeddedFilter'],
            template: '<div>Groups Component {{ embedded ? "embedded" : "full" }} {{ embeddedFilter }}</div>',
        })

        const { container } = render(Settings, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                config: {
                                    is_auth: true,
                                    roles: ['materials_admin'],
                                    selected_school: { long_name: 'Testschule' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=materials',
                        query: {
                            tab: 'materials',
                        },
                    },
                    $router: {
                        replace: () => {},
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    Groups: GroupsStub,
                    MaterialsSettingsView: { template: '<div>Materials Settings Component</div>' },
                },
            },
        })

        expect(container.querySelector('.settings-subnav')).not.toBeNull()
        expect(screen.getByText('Einstellungen')).toBeInTheDocument()
        expect(screen.getAllByText('Materialgruppen').length).toBeGreaterThan(0)

        await waitFor(() => {
            expect(screen.getByText('Materials Settings Component')).toBeInTheDocument()
        })

        expect(container.querySelector('.settings-materials-wrap')).not.toBeNull()

        await fireEvent.click(screen.getByText('Materialgruppen'))

        await waitFor(() => {
            expect(screen.getByText('Groups Component embedded materials')).toBeInTheDocument()
        })

        expect(container.querySelector('.settings-groups-wrap')).not.toBeNull()

        const source = readFileSync('resources/js/pages/admin/settings/Settings.vue', 'utf8')
        expect(source).toContain('.settings-materials-wrap {')
        expect(source).toContain('width: 520px;')
        expect(source).toContain('margin: 0;')
    })

    it('renders the groups settings tab with overview and own-groups sub navigation', async () => {
        const GroupsStub = defineComponent({
            props: ['embedded', 'embeddedFilter'],
            template: '<div>Groups Component {{ embedded ? "embedded" : "full" }} {{ embeddedFilter || "overview" }}</div>',
        })

        const { container } = render(Settings, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                config: {
                                    is_auth: true,
                                    roles: ['admin'],
                                    selected_school: { long_name: 'Testschule' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=groups',
                        query: {
                            tab: 'groups',
                        },
                    },
                    $router: {
                        replace: () => {},
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    Groups: GroupsStub,
                },
            },
        })

        await waitFor(() => {
            expect(screen.getByText('Groups Component embedded overview')).toBeInTheDocument()
        })

        expect(container.querySelector('.settings-groups-wrap')).not.toBeNull()
        expect(container.querySelector('.settings-subnav')).not.toBeNull()
        expect(screen.getByText('Überblick')).toBeInTheDocument()
        expect(screen.getByText('Eigene Gruppen')).toBeInTheDocument()

        await fireEvent.click(screen.getByText('Eigene Gruppen'))

        await waitFor(() => {
            expect(screen.getByText('Groups Component embedded own')).toBeInTheDocument()
        })
    })

    it('redirects unauthorized users away from the admin settings tab and hides super-admin', () => {
        const replace = vi.fn()
        const { container } = render(Settings, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                config: {
                                    is_auth: true,
                                    roles: ['teacher'],
                                    selected_school: { long_name: 'Testschule' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=admin',
                        query: {
                            tab: 'admin',
                        },
                    },
                    $router: {
                        replace,
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    Schools: { template: '<div>Schools Component</div>' },
                    Schoolyears: { template: '<div>Schoolyears Component</div>' },
                    Users: { template: '<div>Users Component</div>' },
                    Licences: { template: '<div>Licences Component</div>' },
                    LicenceSchools: { template: '<div>LicenceSchools Component</div>' },
                    Roles: { template: '<div>Roles Component</div>' },
                    Log: { template: '<div>Log Component</div>' },
                },
            },
        })

        expect(screen.queryByText('Admin')).not.toBeInTheDocument()
        expect(screen.queryByText('Super-Admin')).not.toBeInTheDocument()
        expect(container.querySelector('.settings-subnav')).toBeNull()
        expect(screen.getAllByText('Nachhilfe').length).toBeGreaterThan(0)
        expect(replace).toHaveBeenCalledWith('/admin/settings?tab=tutoring')
    })

    it('redirects removed register panels back to the register users settings view', () => {
        const replace = vi.fn()

        render(Settings, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                config: {
                                    is_auth: true,
                                    roles: ['register_admin'],
                                    selected_school: { long_name: 'Testschule' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=register&panel=notifications',
                        query: {
                            tab: 'register',
                            panel: 'notifications',
                        },
                    },
                    $router: {
                        replace,
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    RegisterUsers: { template: '<div>RegisterUsers Component</div>' },
                },
            },
        })

        expect(screen.getByText('RegisterUsers Component')).toBeInTheDocument()
        expect(screen.queryByText('Benachrichtigungen')).not.toBeInTheDocument()
        expect(screen.queryByText('Vorlagen')).not.toBeInTheDocument()
        expect(replace).toHaveBeenCalledWith('/admin/settings?tab=register')
    })
})
