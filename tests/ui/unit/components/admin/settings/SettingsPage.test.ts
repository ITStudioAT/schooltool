import { readFileSync } from 'node:fs'
import { createTestingPinia } from '@pinia/testing'
import { fireEvent, render, screen, waitFor } from '@testing-library/vue'
import { defineComponent, h, inject, provide, reactive } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it, vi } from 'vitest'
import Settings from '@/pages/admin/settings/Settings.vue'
import StudentsTimetablesTeachers from '@/pages/admin/settings/components/StudentsTimetablesTeachers.vue'

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
    it.each([undefined, 'teachers', 'teaching_admin', 'import', 'holidays', 'school_hours', 'invalid'])('redirects teaching settings panel %s to teaching administration', async (panel) => {
        const replace = vi.fn()

        render(Settings, {
            global: {
                plugins: [createTestingPinia({
                    initialState: {
                        AdminAdminStore: {
                            config: {
                                is_auth: true,
                                roles: ['admin'],
                                selected_school: { long_name: 'Testschule' },
                            },
                        },
                    },
                })],
                mocks: {
                    $route: {
                        fullPath: `/admin/settings?tab=teaching${panel ? `&panel=${panel}` : ''}`,
                        query: { tab: 'teaching', panel },
                    },
                    $router: { replace },
                },
                stubs: {
                    ...vuetifyStubs,
                    TeachingAdmin: { template: '<div>Import, Ferien, Schulstunden</div>' },
                    Teachers: { template: '<div>Teacher accounts</div>' },
                },
            },
        })

        const expectedPanel = ['teachers', 'import', 'holidays', 'school_hours'].includes(panel) ? panel : 'import'
        await waitFor(() => expect(replace).toHaveBeenCalledWith(`/admin/teaching/administration?panel=${expectedPanel}`))
        expect(screen.queryByText('Import, Ferien, Schulstunden')).not.toBeInTheDocument()
        expect(screen.queryByText('Lehrer')).not.toBeInTheDocument()
        expect(screen.queryByText('Teacher accounts')).not.toBeInTheDocument()
    })

    it('builds the updated super-admin sub navigation with grundeinstellungen first', () => {
        const items = (Settings as any).computed.subNavigationItems.call({
            isAdminTab: false,
        })

        expect(items.map((item: { key: string }) => item.key)).toEqual(['general', 'schools', 'licence_models', 'storage_audit', 'roles', 'school_switch', 'user_impersonation', 'preview'])
        expect(items[0]).toMatchObject({
            key: 'general',
            label: 'Grundeinstellungen',
        })
        expect(items[2]).toMatchObject({
            key: 'licence_models',
            label: 'Lizenzen Modelle',
        })
        expect(items[3]).toMatchObject({
            key: 'storage_audit',
            label: 'Speicherprüfung',
        })
        expect(items[4]).toMatchObject({
            key: 'roles',
            label: 'Rollen',
        })
    })

    it('builds the nested licence navigation with both source actions', () => {
        const items = (Settings as any).computed.visibleLicenceNavigationItems.call({})

        expect(items.map((item: { key: string }) => item.key)).toEqual(['overview', 'schools'])
        expect(items.map((item: { label: string }) => item.label)).toEqual(['Alle Lizenzen', 'Lizenzvergaben'])
    })

    it('redirects a legacy register link when settings are already mounted', async () => {
        const replace = vi.fn()
        const route = reactive({ fullPath: '/admin/settings?tab=admin', query: { tab: 'admin' } })

        render(Settings, {
            global: {
                plugins: [createTestingPinia({
                    initialState: {
                        AdminAdminStore: {
                            config: { is_auth: true, roles: ['admin', 'register_admin'] },
                        },
                    },
                })],
                mocks: { $route: route, $router: { replace } },
                stubs: { ...vuetifyStubs, AdminSectionHero: true, Schoolyears: true },
            },
        })

        route.fullPath = '/admin/settings?tab=register'
        route.query.tab = 'register'
        await waitFor(() => expect(replace).toHaveBeenCalledWith('/admin/register_system?panel=users'))
        expect(screen.queryByText('Anmeldetool')).not.toBeInTheDocument()
    })

    it.each([
        ['super_admin', true],
        ['admin', false],
    ])('restricts the preview settings query to super admins (%s)', async (role, allowed) => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [{ path: '/admin/settings', component: Settings }],
        })
        await router.push('/admin/settings?tab=super_admin&panel=preview')
        await router.isReady()

        render(Settings, {
            global: {
                plugins: [
                    router,
                    createTestingPinia({
                        initialState: {
                            AdminAdminStore: { config: { is_auth: true, roles: [role] } },
                        },
                    }),
                ],
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: true,
                    PreviewAccess: { template: '<div>Preview access management</div>' },
                    Schoolyears: { template: '<div>Schoolyears management</div>' },
                },
            },
        })

        if (allowed) {
            await waitFor(() => expect(screen.getByText('Preview access management')).toBeInTheDocument())
            expect(screen.getByText('Vorschau')).toBeInTheDocument()
        } else {
            await waitFor(() => expect(router.currentRoute.value.query.tab).toBe('admin'))
            expect(screen.queryByText('Preview access management')).not.toBeInTheDocument()
            expect(screen.queryByText('Vorschau')).not.toBeInTheDocument()
        }
    })

    it('builds the general sub navigation with only module visibility', () => {
        const items = (Settings as any).computed.generalNavigationItems.call({})

        expect(items.map((item: { key: string }) => item.key)).toEqual(['module_visibility'])
        expect(items[0]).toMatchObject({
            key: 'module_visibility',
            label: 'Sichtbarkeit Modul',
        })
    })

    it.each([
        '/admin/settings?general_panel=licences',
        '/admin/settings?panel=general&general_panel=licences',
        '/admin/settings?panel=licence_models',
    ])('opens the single licence overview from %s', async (url) => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [{ path: '/admin/settings', component: Settings }],
        })
        await router.push(url)
        await router.isReady()

        render(Settings, {
            global: {
                plugins: [
                    router,
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                config: { is_auth: true, roles: ['super_admin'] },
                            },
                        },
                    }),
                ],
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: true,
                    ModuleStatusesCard: { template: '<div>Module visibility</div>' },
                    Licences: { template: '<div>Licences Component</div>' },
                },
            },
        })

        await waitFor(() => {
            expect(router.currentRoute.value.fullPath).toBe('/admin/settings?panel=licence_models')
            expect(screen.getAllByText('Licences Component')).toHaveLength(1)
        })
        expect(screen.queryByText('Lizenzen')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByText('Grundeinstellungen'))
        await waitFor(() => expect(router.currentRoute.value.fullPath).toBe('/admin/settings'))
        expect(screen.getByText('Module visibility')).toBeInTheDocument()
        expect(screen.queryByText('Licences Component')).not.toBeInTheDocument()

        await router.push('/admin/settings?general_panel=licences')
        await waitFor(() => {
            expect(router.currentRoute.value.fullPath).toBe('/admin/settings?panel=licence_models')
            expect(screen.getAllByText('Licences Component')).toHaveLength(1)
        })
    })

    it('builds the admin sub navigation with own-school appearance settings', () => {
        const items = (Settings as any).computed.subNavigationItems.call({
            isAdminTab: true,
        })

        expect(items.map((item: { key: string }) => item.key)).toEqual(['schoolyears', 'schools', 'users', 'school_groups', 'log'])
        expect(items.map((item: { label: string }) => item.label)).toEqual(['Schuljahre', 'Schule', 'Benutzer', 'Schulgruppen', 'Log'])
    })

    it('opens the supplied schools panel URL for an admin', async () => {
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
                                    roles: ['admin'],
                                    selected_school: { id: 7, long_name: 'Testschule', color: '#1976D2' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?panel=schools',
                        query: {
                            panel: 'schools',
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
                },
            },
        })

        await waitFor(() => {
            expect(screen.getByText('Schools Component')).toBeInTheDocument()
        })
        expect(replace).toHaveBeenCalledWith('/admin/settings?tab=admin&panel=schools')
    })

    it('removes register users from the available global settings tabs', () => {
        expect((Settings as any).methods.availableTabKeys(true, true, true, true)).not.toContain('register')
        expect((Settings as any).computed.tabRoleMap.call({})).not.toHaveProperty('register')
        expect((Settings as any).components).not.toHaveProperty('RegisterUsers')
    })

    it('does not expose materials sub navigation in global settings', () => {
        const items = (Settings as any).computed.subNavigationItems.call({
            isAdminTab: false,
            isRegisterTab: false,
            isTeachingTab: false,
            isGroupsTab: false,
            isTutoringTab: false,
        })

        expect(items.map((item: { key: string }) => item.key)).not.toContain('material_settings')
        expect(items.map((item: { key: string }) => item.key)).not.toContain('material_groups')
    })

    it('does not expose groups sub navigation in global settings', () => {
        const items = (Settings as any).computed.subNavigationItems.call({
            isGroupsTab: true,
            isAdminTab: false,
            isRegisterTab: false,
            isTeachingTab: false,
            isMaterialsTab: false,
            isTutoringTab: false,
        })

        expect(items.map((item: { key: string }) => item.key)).not.toContain('groups_overview')
        expect(items.map((item: { key: string }) => item.key)).not.toContain('groups_own')
        expect((Settings as any).computed.showsSubNavigation.call({ main_action: 'groups' })).toBe(false)
    })

    it('does not expose restaurant sub navigation in global settings', () => {
        expect((Settings as any).computed.showsSubNavigation.call({ main_action: 'restaurant' })).toBe(false)
        expect((Settings as any).components).not.toHaveProperty('RestaurantSettings')
    })

    it('shows active controls and teacher roles in the teachers panel', () => {
        const componentSource = readFileSync('resources/js/pages/admin/settings/components/StudentsTimetablesTeachers.vue', 'utf8')
        const storeSource = readFileSync('resources/js/stores/admin/studentsTimetables/TeachersListStore.js', 'utf8')

        expect(componentSource).toContain('Lehrerliste')
        expect(componentSource).toContain('v-for="teacher in teachers"')
        expect(componentSource).toContain("teacher.is_active ? 'Aktiv' : 'Inaktiv'")
        expect(componentSource).toContain('Alle aktiv')
        expect(componentSource).toContain('Alle inaktiv')
        expect(componentSource).toContain('@click="setAllTeachersActive(true)"')
        expect(componentSource).toContain('@click="setAllTeachersActive(false)"')
        expect(componentSource).toContain('@click="openCreateDialog"')
        expect(componentSource).toContain('Hinzufügen')
        expect(componentSource).toContain('@click="openImportDialog"')
        expect(componentSource).toContain('Importieren')
        expect(componentSource).toContain('Nachname/Familienname, Vorname, Email/EMail')
        expect(componentSource).toContain('Optional:')
        expect(componentSource).toContain('Kurz/Kürzel')
        expect(componentSource).toContain('<FileUpload')
        expect(componentSource).toContain(':path="teachersListUploadPath"')
        expect(componentSource).toContain('teachersListApi.upload()')
        expect(componentSource).toContain('teachersListApi.importStatus()')
        expect(componentSource).toContain("window.addEventListener('teachers-list-import-finished'")
        expect(componentSource).toContain("window.removeEventListener('teachers-list-import-finished'")
        expect(componentSource).toContain('cols="12" md="10" lg="8" xl="7"')
        expect(componentSource).not.toContain('class="mx-auto"')
        expect(componentSource).toContain("'Lehrkraft hinzufügen'")
        expect(componentSource).not.toContain('Lehrer: Ja')
        expect(componentSource).toContain('readonly')
        expect(componentSource).not.toContain('Keine TT-Rolle')
        expect(componentSource).toContain('TT-Admin')
        expect(componentSource).toContain('TT-Moderator')
        expect(componentSource).toContain('const nextRole = this.isSelectedTimetableRole(teacher, role) ? null : role')
        expect(componentSource).toContain('const activatedTeacher = await this.teachersListStore.activateTeacher')
        expect(componentSource).toContain('userId = activatedTeacher.user_id')
        expect(componentSource).toContain('@click.stop="toggleTeacherActive(teacher)"')
        expect(componentSource).toContain('@click.stop="openEditDialog(teacher)"')
        expect(componentSource).toContain('icon="mdi-pencil-outline"')
        expect(componentSource).toContain('@click.stop="openRemoveDialog(teacher)"')
        expect(componentSource).toContain('icon="mdi-delete-outline"')
        expect(componentSource).toContain('Aus Lehrerliste entfernen')
        expect(componentSource).toContain('Das Benutzerkonto bleibt erhalten. Die Lehrer- und TT-Rollen werden entfernt.')
        expect(componentSource).toContain('Ein späterer Lehrerliste-Import kann die Lehrkraft erneut hinzufügen.')
        expect(componentSource).toContain('v-if="action_message"')
        expect(componentSource).not.toContain('prepend-icon="mdi-pencil-outline"')
        expect(componentSource).not.toMatch(/>\s*Bearbeiten\s*<\/v-btn>/u)
        expect(componentSource).toContain('@click.stop="copyEmail(teacher)"')
        expect(componentSource).toContain("'mdi-content-copy'")
        expect(componentSource).toContain('E-Mail-Adresse kopieren: ${teacher.email}')
        expect(componentSource).toContain('async copyTextToClipboard(text)')
        expect(componentSource).toContain('await navigator.clipboard.writeText(text)')
        expect(componentSource).toContain('Lehrkraft bearbeiten')
        expect(componentSource).toContain('label="Kürzel"')
        expect(componentSource).toContain('@update:model-value="updateTeacherShort"')
        expect(componentSource).toContain("short: this.editForm.short.trim().toUpperCase()")
        expect(componentSource).toContain('label="Nachname"')
        expect(componentSource).toContain('label="Vorname"')
        expect(componentSource).toContain('label="E-Mail"')
        expect(componentSource).toContain('async saveTeacher()')
        expect(componentSource).not.toContain('@click.stop="toggleTeacherRole(teacher)"')
        expect(storeSource).toContain("@/actions/App/Http/Controllers/Admin/StudentsTimetables/TeacherAccountController")
        expect(storeSource).toContain('activateTeacherAccount.url')
        expect(storeSource).toContain('setTeacherAccountRole.url')
        expect(storeSource).toContain('setTeacherAccountsActiveState.url')
        expect(storeSource).toContain('async setAllActive(isActive)')
        expect(storeSource).toContain('updateImportedTeacherAccount.url')
        expect(storeSource).toContain('updateRegisteredTeacherAccount.url')
        expect(storeSource).toContain('destroy as removeImportedTeacherAccount')
        expect(storeSource).toContain('destroyUser as removeRegisteredTeacherAccount')
        expect(storeSource).toContain('axios.delete(removeImportedTeacherAccount.url(teacher.teacher_id))')
        expect(storeSource).toContain('axios.delete(removeRegisteredTeacherAccount.url(teacher.user_id))')
        expect(storeSource).toContain('async removeTeacher(teacher)')
        expect(storeSource).toContain('store as storeTeacherAccount')
        expect(storeSource).toContain('async createTeacher(values)')
        expect(storeSource).toContain('axios.post(storeTeacherAccount.url(), values)')
        expect(storeSource).toContain('async updateTeacher(teacher, values)')
        expect(storeSource).not.toContain('toggleTeacherAccountTeacherRole.url')
        expect(storeSource).toContain('response.data.data')
    })

    it('copies a teacher email and resets the copied state', async () => {
        vi.useFakeTimers()

        try {
            const context = {
                copiedEmailId: null as number | null,
                copyEmailResetTimeout: null as ReturnType<typeof setTimeout> | null,
                copyTextToClipboard: vi.fn().mockResolvedValue(true),
            }

            await expect((StudentsTimetablesTeachers as any).methods.copyEmail.call(context, {
                id: 42,
                email: ' teacher@example.com ',
            })).resolves.toBe(true)
            expect(context.copyTextToClipboard).toHaveBeenCalledWith('teacher@example.com')
            expect(context.copiedEmailId).toBe(42)

            vi.advanceTimersByTime(1500)

            expect(context.copiedEmailId).toBeNull()
            expect(context.copyEmailResetTimeout).toBeNull()
        } finally {
            vi.useRealTimers()
        }
    })

    it('creates a teacher through the shared teacher form', async () => {
        const context = {
            editingTeacher: null,
            canSaveTeacher: true,
            editForm: {
                short: 'NN',
                last_name: 'Neumann',
                first_name: 'Nora',
                email: 'nora@example.test',
            },
            teachersListStore: {
                createTeacher: vi.fn().mockResolvedValue({ id: 'teacher-42' }),
                updateTeacher: vi.fn(),
            },
            closeEditDialog: vi.fn(),
        }

        await (StudentsTimetablesTeachers as any).methods.saveTeacher.call(context)

        expect(context.teachersListStore.createTeacher).toHaveBeenCalledWith(context.editForm)
        expect(context.teachersListStore.updateTeacher).not.toHaveBeenCalled()
        expect(context.closeEditDialog).toHaveBeenCalledOnce()
    })

    it('closes the removal dialog only after a successful removal', async () => {
        const teacher = { id: 'teacher-42', teacher_id: 42, user_id: null }
        const successContext = {
            removingTeacher: teacher,
            teachersListStore: {
                removeTeacher: vi.fn().mockResolvedValue(true),
            },
            closeRemoveDialog: vi.fn(),
        }

        await (StudentsTimetablesTeachers as any).methods.removeTeacher.call(successContext)

        expect(successContext.teachersListStore.removeTeacher).toHaveBeenCalledWith(teacher)
        expect(successContext.closeRemoveDialog).toHaveBeenCalledOnce()

        const failureContext = {
            removingTeacher: teacher,
            teachersListStore: {
                removeTeacher: vi.fn().mockResolvedValue(false),
            },
            closeRemoveDialog: vi.fn(),
        }

        await (StudentsTimetablesTeachers as any).methods.removeTeacher.call(failureContext)

        expect(failureContext.closeRemoveDialog).not.toHaveBeenCalled()
    })

    it('keeps teacher short codes uppercase while editing', () => {
        const context = {
            editForm: {
                short: '',
            },
        }

        ;(StudentsTimetablesTeachers as any).methods.updateTeacherShort.call(context, 'ab-12')

        expect(context.editForm.short).toBe('AB-12')
    })

    it('refreshes the teachers page after a successful list import', async () => {
        const context = {
            importRunning: true,
            importUploadFinished: false,
            importFinished: false,
            importStatus: null as number | null,
            importMessage: '',
            stopImportStatusPolling: vi.fn(),
            teachersListStore: {
                index: vi.fn().mockResolvedValue(undefined),
            },
        }

        await (StudentsTimetablesTeachers as any).methods.applyImportCompletion.call(context, {
            status: 200,
            message: 'Import abgeschlossen.',
        })

        expect(context.stopImportStatusPolling).toHaveBeenCalledOnce()
        expect(context.importRunning).toBe(false)
        expect(context.importUploadFinished).toBe(true)
        expect(context.importFinished).toBe(true)
        expect(context.importStatus).toBe(200)
        expect(context.importMessage).toBe('Import abgeschlossen.')
        expect(context.teachersListStore.index).toHaveBeenCalledOnce()
    })

    it('removes the groups tab from global settings even for previously authorized users', () => {
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

        expect(allowedItems.map((item: { key: string }) => item.key)).not.toContain('groups')
        expect(deniedItems.map((item: { key: string }) => item.key)).not.toContain('groups')

        const tabRoleMap = (Settings as any).computed.tabRoleMap.call({})
        expect(tabRoleMap).not.toHaveProperty('groups')
    })

    it('excludes groups from the available global settings tabs', () => {
        const methods = (Settings as any).methods

        expect(methods.availableTabKeys(true, true, true, true, true, true, true, true)).not.toContain('groups')
        expect(methods.availableTabKeys(true, true, true, true, true, false, true, true)).not.toContain('groups')
    })

    it.each([
        ['super_admin', true],
        ['admin', true],
        ['materials_admin', true],
        ['materials_moderator', true],
        ['teacher', false],
    ])('allows the legacy materials redirect for %s: %s', (role, allowed) => {
        expect((Settings as any).computed.canAccessMaterialsAdministration.call({
            configuredRoleNames: [role],
        })).toBe(allowed)
    })

    it('removes the materials tab from global settings navigation', () => {
        const items = (Settings as any).computed.navigationItems.call({})
        const tabRoleMap = (Settings as any).computed.tabRoleMap.call({})

        expect(items.map((item: { key: string }) => item.key)).not.toContain('materials')
        expect(tabRoleMap).not.toHaveProperty('materials')
        expect((Settings as any).methods.availableTabKeys(true, true, true, true, true, true, true))
            .not.toContain('materials')
    })

    it('removes the restaurant settings tab even for authorized restaurant users', () => {
        const allowedItems = (Settings as any).computed.navigationItems.call({
            canAccessSuperAdminSettingsTab: false,
            canAccessAdminSettingsTab: false,
            canAccessRegisterSettingsTab: false,
            canAccessMaterialsSettingsTab: false,
            canAccessGroupsSettingsTab: false,
            canAccessRestaurantSettingsTab: true,
        })
        const deniedItems = (Settings as any).computed.navigationItems.call({
            canAccessSuperAdminSettingsTab: false,
            canAccessAdminSettingsTab: false,
            canAccessRegisterSettingsTab: false,
            canAccessMaterialsSettingsTab: false,
            canAccessGroupsSettingsTab: false,
            canAccessRestaurantSettingsTab: false,
        })

        expect(allowedItems.map((item: { key: string }) => item.key)).not.toContain('restaurant')
        expect(deniedItems.map((item: { key: string }) => item.key)).not.toContain('restaurant')

        const tabRoleMap = (Settings as any).computed.tabRoleMap.call({})
        expect(tabRoleMap).not.toHaveProperty('restaurant')
    })

    it('excludes restaurant from available global settings tabs', () => {
        const methods = (Settings as any).methods

        expect(methods.availableTabKeys(true, true, true, true, true, true, true, true)).not.toContain('restaurant')
        expect(methods.availableTabKeys(true, true, true, true, true, true, false, true)).not.toContain('restaurant')
    })

    it('does not expose profile or students timetables management in Settings', () => {
        const methods = (Settings as any).methods
        const computed = (Settings as any).computed
        const componentSource = readFileSync('resources/js/pages/admin/settings/Settings.vue', 'utf8')
        const navigationItems = computed.navigationItems.call({
            canAccessSuperAdminSettingsTab: true,
            canAccessAdminSettingsTab: true,
            canAccessRegisterSettingsTab: true,
            canAccessTutoringSettingsTab: true,
            canAccessTeachingSettingsTab: true,
            canAccessMaterialsSettingsTab: true,
            canAccessGroupsSettingsTab: true,
            canAccessRestaurantSettingsTab: true,
            canAccessProfileTab: true,
        })

        expect(navigationItems.map((item: { key: string }) => item.key)).not.toContain('students_timetables')
        expect(navigationItems.map((item: { key: string }) => item.key)).not.toContain('profile')
        expect(navigationItems.map((item: { key: string }) => item.key)).not.toContain('teaching')
        expect(methods.availableTabKeys(true, true, true, true, true, true, true, true))
            .not.toContain('students_timetables')
        expect(componentSource).not.toContain("label: 'SEPP'")
        expect(componentSource).not.toContain('StudentsTimetablesTeachers')
        expect(componentSource).not.toContain("main_action === 'students_timetables'")
    })

    it('uses the restaurant capability before falling back to restaurant roles', () => {
        const methods = (Settings as any).methods

        expect(methods.canAccessRestaurantSettings(['lunch_admin'], { restaurant: true })).toBe(true)
        expect(methods.canAccessRestaurantSettings(['lunch_admin'], { restaurant: false })).toBe(false)
        expect(methods.canAccessRestaurantSettings(['lunch_admin'], { profile: true })).toBe(false)
        expect(methods.canAccessRestaurantSettings(['lunch_admin'], {})).toBe(true)
    })

    it('redirects the removed students timetables settings URL to the profile', () => {
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
                                    roles: ['studentstimetables_admin'],
                                    capabilities: {
                                        students_timetables: true,
                                        profile: true,
                                    },
                                    selected_school: { long_name: 'Testschule' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=students_timetables',
                        query: {
                            tab: 'students_timetables',
                        },
                    },
                    $router: {
                        replace,
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    Profile: { template: '<div>Profile Component</div>' },
                },
            },
        })

        expect(screen.queryByText('SEPP')).not.toBeInTheDocument()
        expect(screen.queryByText(/StudentsTimetablesTeachers Component/)).not.toBeInTheDocument()
        expect(replace).toHaveBeenCalledWith('/admin/profile')
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

    it('renders the grundeinstellungen view first on the super-admin settings destination', async () => {
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
                    ModuleStatusesCard: { template: '<div>ModuleStatusesCard Component</div>' },
                    Licences: { template: '<div>Licences Component</div>' },
                    LicenceSchools: { template: '<div>LicenceSchools Component</div>' },
                    StorageAudit: { template: '<div>StorageAudit Component</div>' },
                    Roles: { template: '<div>Roles Component</div>' },
                    Log: { template: '<div>Log Component</div>' },
                },
            },
        })

        expect(screen.getAllByText('Grundeinstellungen').length).toBeGreaterThan(0)
        expect(screen.getByRole('navigation', { name: 'Super-Admin Einstellungen' })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Grundeinstellungen Allgemein' })).toHaveAttribute('aria-pressed', 'true')
        expect(container.querySelector('.settings-general-wrap')).not.toBeNull()
        expect(container.querySelector('.settings-general-subnav')).not.toBeNull()
        expect(screen.getByText('Sichtbarkeit Modul')).toBeInTheDocument()
        expect(screen.queryByText('Lizenzen')).not.toBeInTheDocument()
        expect(screen.getByText('ModuleStatusesCard Component')).toBeInTheDocument()
        expect(screen.queryByText('Schools Component')).not.toBeInTheDocument()
        expect(screen.getByText('Lizenzen Modelle')).toBeInTheDocument()

        await fireEvent.click(screen.getByText('Schulen'))

        await waitFor(() => {
            expect(screen.getByText('Schools Component')).toBeInTheDocument()
        })

        await fireEvent.click(screen.getByText('Lizenzen Modelle'))

        await waitFor(() => {
            expect(screen.getByText('Licences Component')).toBeInTheDocument()
        })

        expect(container.querySelector('.settings-licences-wrap')).not.toBeNull()
        expect(screen.getByRole('button', { name: 'Lizenzen Modelle Lizenzverwaltung' })).toHaveAttribute('aria-pressed', 'true')
        expect(screen.getByRole('button', { name: 'Grundeinstellungen Allgemein' })).toHaveAttribute('aria-pressed', 'false')
        expect(screen.getByText('Alle Lizenzen')).toBeInTheDocument()
        expect(screen.getByText('Lizenzvergaben')).toBeInTheDocument()

        await fireEvent.click(screen.getByText('Speicherprüfung'))

        await waitFor(() => {
            expect(screen.getByText('StorageAudit Component')).toBeInTheDocument()
        })

        expect(container.querySelector('.settings-storage-audit-wrap')).not.toBeNull()

        await fireEvent.click(screen.getByText('Lizenzen Modelle'))

        await waitFor(() => {
            expect(screen.getByText('Licences Component')).toBeInTheDocument()
        })

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

    it('includes the module status card on the super-admin general panel source', () => {
        const source = readFileSync('resources/js/pages/admin/settings/Settings.vue', 'utf8')

        expect(source).toContain("<ModuleStatusesCard v-if=\"general_action === 'module_visibility'\" />")
        expect(source).not.toContain("<Licences v-else-if=\"general_action === 'licences'\" />")
        expect(source).toContain("<StorageAudit />")
        expect(source).toContain("const ModuleStatusesCard = defineAsyncComponent(() => import('@/pages/admin/settings/components/ModuleStatusesCard.vue'))")
        expect(source).toContain("const StorageAudit = defineAsyncComponent(() => import('@/pages/admin/superAdmin/components/StorageAudit.vue'))")
        expect(source).toContain('generalNavigationItems')
        expect(source).toContain('Sichtbarkeit Modul')
        expect(source).not.toContain("key: 'licences'")
        expect(source).toContain("key: 'storage_audit'")
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
        expect(screen.getByRole('navigation', { name: 'Admin Einstellungen' })).toHaveClass('settings-section-subnav')
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

    it.each([
        [undefined, 'material_settings'],
        ['material_settings', 'material_settings'],
        ['material_groups', 'material_groups'],
        ['missing', 'material_settings'],
    ])('redirects the legacy materials panel %s into Materials Admin', async (panel, expectedPanel) => {
        const replace = vi.fn()

        render(Settings, {
            global: {
                plugins: [createTestingPinia({
                    initialState: {
                        AdminAdminStore: {
                            config: {
                                is_auth: true,
                                roles: ['materials_admin'],
                                selected_school: { long_name: 'Testschule' },
                            },
                        },
                    },
                })],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=materials',
                        query: { tab: 'materials', panel },
                    },
                    $router: { replace },
                },
                stubs: {
                    ...vuetifyStubs,
                    Groups: { template: '<div>Other groups settings</div>' },
                    MaterialsSettingsView: { template: '<div>Materials Settings Component</div>' },
                },
            },
        })

        await waitFor(() => expect(replace).toHaveBeenCalledWith(
            `/admin/materials-v2?section=admin&panel=${expectedPanel}`,
        ))
        expect(screen.queryByText('Materialien')).not.toBeInTheDocument()
        expect(screen.queryByText('Materialgruppen')).not.toBeInTheDocument()
        expect(screen.queryByText('Materials Settings Component')).not.toBeInTheDocument()
    })

    it('does not redirect ordinary teachers into Materials Admin', () => {
        const replace = vi.fn()

        render(Settings, {
            global: {
                plugins: [createTestingPinia({
                    initialState: {
                        AdminAdminStore: {
                            config: { is_auth: true, roles: ['teacher'] },
                        },
                    },
                })],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=materials',
                        query: { tab: 'materials', panel: 'material_groups' },
                    },
                    $router: { replace },
                },
                stubs: vuetifyStubs,
            },
        })

        expect(replace).toHaveBeenCalledWith('/admin/profile')
        expect(replace).not.toHaveBeenCalledWith(expect.stringContaining('/admin/materials-v2'))
    })

    it.each([
        [undefined, 'groups_overview'],
        ['groups_overview', 'groups_overview'],
        ['groups_own', 'groups_own'],
        ['unknown', 'groups_overview'],
    ])('redirects the former groups panel %s to the standalone page for super admins', async (panel, expectedPanel) => {
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
                                    roles: ['super_admin'],
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
                            panel,
                        },
                    },
                    $router: {
                        replace,
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    Groups: { template: '<div>Groups content</div>' },
                    ModuleStatusesCard: { template: '<div>Module settings</div>' },
                },
            },
        })

        await waitFor(() => expect(replace).toHaveBeenCalledWith(`/admin/groups?panel=${expectedPanel}`))
        expect(container.querySelector('.settings-groups-wrap')).toBeNull()
        expect(screen.queryByText('Groups content')).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Gruppen', exact: true })).not.toBeInTheDocument()
        expect(screen.queryByText('Eigene Gruppen')).not.toBeInTheDocument()
    })

    it.each([
        ['admin', '/admin/settings?tab=admin'],
        ['materials_admin', '/admin/profile'],
        ['materials_moderator', '/admin/profile'],
    ])('keeps %s away from the standalone groups page through legacy links', async (role, expectedTarget) => {
        const replace = vi.fn()

        render(Settings, {
            global: {
                plugins: [createTestingPinia({
                    initialState: {
                        AdminAdminStore: {
                            config: {
                                is_auth: true,
                                roles: [role],
                                capabilities: { groups: true },
                            },
                        },
                    },
                })],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=groups&panel=groups_own',
                        query: { tab: 'groups', panel: 'groups_own' },
                    },
                    $router: { replace },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: true,
                    Schoolyears: { template: '<div>Schoolyears settings</div>' },
                    Groups: { template: '<div>Groups content</div>' },
                },
            },
        })

        await waitFor(() => expect(replace).toHaveBeenCalledWith(expectedTarget))
        expect(replace).not.toHaveBeenCalledWith(expect.stringContaining('/admin/groups'))
        expect(screen.queryByText('Groups content')).not.toBeInTheDocument()
    })

    it.each(['super_admin', 'admin'])('handles legacy groups links inside an already mounted settings page for %s', async (role) => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/admin/settings', component: Settings },
                { path: '/admin/groups', component: { template: '<div>Standalone groups</div>' } },
            ],
        })
        await router.push('/admin/settings?tab=admin')
        await router.isReady()

        render({ template: '<router-view />' }, {
            global: {
                plugins: [router, createTestingPinia({
                    initialState: {
                        AdminAdminStore: {
                            config: { is_auth: true, roles: [role], capabilities: { groups: true } },
                        },
                    },
                })],
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: true,
                    Schoolyears: { template: '<div>Schoolyears settings</div>' },
                },
            },
        })

        await waitFor(() => expect(screen.getByText('Schoolyears settings')).toBeInTheDocument())
        await router.push('/admin/settings?tab=groups&panel=groups_own')

        const expectedTarget = role === 'super_admin'
            ? '/admin/groups?panel=groups_own'
            : '/admin/settings?tab=admin'
        await waitFor(() => expect(router.currentRoute.value.fullPath).toBe(expectedTarget))
        expect(screen.queryByText('Standalone groups') !== null).toBe(role === 'super_admin')
    })

    it('redirects the removed students timetables settings URL for students timetables moderators', () => {
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
                                    roles: ['studentstimetables_moderator'],
                                    capabilities: {
                                        students_timetables: true,
                                    },
                                    selected_school: { long_name: 'Testschule' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=students_timetables',
                        query: {
                            tab: 'students_timetables',
                        },
                    },
                    $router: {
                        replace,
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    Schoolyears: { template: '<div>Schoolyears Component</div>' },
                    Profile: { template: '<div>Profile Component</div>' },
                },
            },
        })

        expect(screen.queryByText('SEPP')).not.toBeInTheDocument()
        expect(screen.queryByText(/StudentsTimetablesTeachers Component/)).not.toBeInTheDocument()
        expect(replace).toHaveBeenCalledWith('/admin/profile')
    })

    it.each([
        [undefined, 'general'],
        ['general', 'general'],
        ['categories', 'categories'],
        ['ingredient-icons', 'ingredient-icons'],
        ['free-days', 'free-days'],
        ['eating-times', 'eating-times'],
        ['users', 'users'],
        ['sepa', 'sepa'],
        ['online', 'online'],
        ['invalid', 'general'],
        [['users', 'online'], 'general'],
    ])('redirects the former restaurant settings panel %s to Restaurant Admin', async (panel, expectedPanel) => {
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
                                    roles: ['lunch_admin'],
                                    capabilities: {
                                        restaurant: true,
                                    },
                                    selected_school: { long_name: 'Testschule' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=restaurant&panel=users',
                        query: {
                            tab: 'restaurant',
                            panel,
                        },
                    },
                    $router: {
                        replace,
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    RestaurantSettings: {
                        props: ['embedded', 'panel'],
                        template: '<div>RestaurantSettings {{ embedded ? "embedded" : "full" }} {{ panel }}</div>',
                    },
                },
            },
        })

        await waitFor(() => expect(replace).toHaveBeenCalledWith(`/admin/restaurant/settings?panel=${expectedPanel}`))
        expect(container.querySelector('.settings-subnav')).toBeNull()
        expect(screen.queryByText('Restaurant')).not.toBeInTheDocument()
        expect(screen.queryByText(/RestaurantSettings embedded/)).not.toBeInTheDocument()
        expect(screen.queryByRole('navigation', { name: 'Restaurant Einstellungen' })).not.toBeInTheDocument()
        expect(container.querySelector('.settings-restaurant-wrap')).toBeNull()
    })

    it('redirects lunch_admin to the restaurant administration from its former settings tab', () => {
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
                                    roles: ['lunch_admin'],
                                    capabilities: {
                                        restaurant: true,
                                    },
                                    selected_school: { long_name: 'Testschule' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=restaurant',
                        query: {
                            tab: 'restaurant',
                        },
                    },
                    $router: {
                        replace,
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    RestaurantSettings: {
                        props: ['embedded', 'panel'],
                        template: '<div>RestaurantSettings {{ embedded ? "embedded" : "full" }} {{ panel }}</div>',
                    },
                    Profile: { template: '<div>Profile Component</div>' },
                },
            },
        })

        expect(screen.queryByText('Restaurant')).not.toBeInTheDocument()
        expect(screen.queryByText('Profil')).not.toBeInTheDocument()
        expect(screen.queryByText('Nachhilfe')).not.toBeInTheDocument()
        expect(screen.queryByText('Unterricht')).not.toBeInTheDocument()
        expect(screen.queryByText('RestaurantSettings embedded general')).not.toBeInTheDocument()
        expect(replace).toHaveBeenCalledWith('/admin/restaurant/settings?panel=general')
    })

    it.each([true, false])('handles a legacy restaurant link from an already mounted settings page with access %s', async (restaurantAccess) => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/admin/settings', component: Settings },
                { path: '/admin/restaurant/settings', component: { template: '<div>Restaurant administration</div>' } },
            ],
        })
        await router.push('/admin/settings?tab=admin')
        await router.isReady()

        render({ template: '<router-view />' }, {
            global: {
                plugins: [router, createTestingPinia({
                    initialState: {
                        AdminAdminStore: {
                            config: {
                                is_auth: true,
                                roles: ['admin'],
                                capabilities: { restaurant: restaurantAccess },
                            },
                        },
                    },
                })],
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: true,
                    Schoolyears: { template: '<div>Schoolyears settings</div>' },
                },
            },
        })

        await waitFor(() => expect(screen.getByText('Schoolyears settings')).toBeInTheDocument())
        await router.push('/admin/settings?tab=restaurant&panel=categories')

        const expectedTarget = restaurantAccess
            ? '/admin/restaurant/settings?panel=categories'
            : '/admin/settings?tab=admin'
        await waitFor(() => expect(router.currentRoute.value.fullPath).toBe(expectedTarget))
        if (restaurantAccess) {
            expect(screen.getByText('Restaurant administration')).toBeInTheDocument()
        } else {
            expect(screen.queryByText('Restaurant administration')).not.toBeInTheDocument()
        }
    })

    it('falls back to profile when lunch_admin opens global settings without a legacy restaurant link', () => {
        const replace = vi.fn()
        render(Settings, {
            global: {
                plugins: [createTestingPinia({
                    initialState: {
                        AdminAdminStore: {
                            config: {
                                is_auth: true,
                                roles: ['lunch_admin'],
                                capabilities: { restaurant: true },
                            },
                        },
                    },
                })],
                mocks: {
                    $route: { fullPath: '/admin/settings', query: {} },
                    $router: { replace },
                },
                stubs: { ...vuetifyStubs, AdminSectionHero: true },
            },
        })

        expect(replace).toHaveBeenCalledWith('/admin/profile')
        expect(screen.queryByText('Restaurant')).not.toBeInTheDocument()
    })

    it('redirects lunch_admin away from restaurant settings when the restaurant capability is disabled', () => {
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
                                    roles: ['lunch_admin'],
                                    capabilities: {
                                        profile: true,
                                        restaurant: false,
                                    },
                                    selected_school: { long_name: 'Testschule' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=restaurant',
                        query: {
                            tab: 'restaurant',
                        },
                    },
                    $router: {
                        replace,
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    RestaurantSettings: {
                        props: ['embedded', 'panel'],
                        template: '<div>RestaurantSettings {{ embedded ? "embedded" : "full" }} {{ panel }}</div>',
                    },
                    Profile: { template: '<div>Profile Component</div>' },
                },
            },
        })

        expect(screen.queryByText('Restaurant')).not.toBeInTheDocument()
        expect(screen.queryByText('RestaurantSettings embedded general')).not.toBeInTheDocument()
        expect(screen.queryByText('Profile Component')).not.toBeInTheDocument()
        expect(replace).toHaveBeenCalledWith('/admin/profile')
    })

    it('redirects lunch_admin away from restaurant settings when restaurant capability is missing', () => {
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
                                    roles: ['lunch_admin'],
                                    capabilities: {
                                        profile: true,
                                    },
                                    selected_school: { long_name: 'Testschule' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=restaurant',
                        query: {
                            tab: 'restaurant',
                        },
                    },
                    $router: {
                        replace,
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    RestaurantSettings: {
                        props: ['embedded', 'panel'],
                        template: '<div>RestaurantSettings {{ embedded ? "embedded" : "full" }} {{ panel }}</div>',
                    },
                    Profile: { template: '<div>Profile Component</div>' },
                },
            },
        })

        expect(screen.queryByText('Restaurant')).not.toBeInTheDocument()
        expect(screen.queryByText('RestaurantSettings embedded general')).not.toBeInTheDocument()
        expect(screen.queryByText('Profile Component')).not.toBeInTheDocument()
        expect(replace).toHaveBeenCalledWith('/admin/profile')
    })

    it('redirects the former profile settings tab to the standalone profile', () => {
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
                                    roles: ['lunch_admin'],
                                    capabilities: {
                                        profile: true,
                                    },
                                    selected_school: { long_name: 'Testschule' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=profile',
                        query: {
                            tab: 'profile',
                        },
                    },
                    $router: {
                        replace,
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    Profile: { template: '<div>Profile Component</div>' },
                },
            },
        })

        expect(screen.queryByText('Profile Component')).not.toBeInTheDocument()
        expect(screen.queryByText('Profil')).not.toBeInTheDocument()
        expect(replace).toHaveBeenCalledWith('/admin/profile')
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

        expect(screen.queryByText('Super-Admin')).not.toBeInTheDocument()
        expect(container.querySelector('.settings-subnav')).toBeNull()
        expect(screen.queryByText('Unterricht')).not.toBeInTheDocument()
        expect(replace).toHaveBeenCalledWith('/admin/profile')
    })

    it('redirects unauthorized users away from the restaurant settings tab', () => {
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
                                    roles: ['teacher'],
                                    selected_school: { long_name: 'Testschule' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: '/admin/settings?tab=restaurant',
                        query: {
                            tab: 'restaurant',
                        },
                    },
                    $router: {
                        replace,
                    },
                },
                stubs: {
                    ...vuetifyStubs,
                    AdminSectionHero: { template: '<div>Admin Hero</div>' },
                    RestaurantSettings: {
                        props: ['embedded', 'panel'],
                        template: '<div>RestaurantSettings {{ embedded ? "embedded" : "full" }} {{ panel }}</div>',
                    },
                },
            },
        })

        expect(replace).toHaveBeenCalledWith('/admin/profile')
    })

    it.each([true, false])('hides the register settings tab regardless of register access: %s', (canAccessRegisterSystem) => {
        const items = (Settings as any).computed.navigationItems.call({
            canAccessSuperAdminSettingsTab: false,
            canAccessAdminSettingsTab: false,
            canAccessRegisterSystem,
            canAccessTutoringSettingsTab: false,
            canAccessTeachingSettingsTab: false,
            canAccessMaterialsSettingsTab: false,
            canAccessGroupsSettingsTab: false,
            canAccessRestaurantSettingsTab: false,
            canAccessProfileTab: true,
        })

        expect(items.map((item: { key: string }) => item.key)).not.toContain('register')
    })

    it.each([
        [undefined, undefined, '/admin/register_system?panel=users'],
        ['users', true, '/admin/register_system?panel=users'],
        ['notifications', true, '/admin/register_system?panel=users'],
        ['users', false, '/admin/profile'],
    ])('redirects the legacy register panel %s with access %s to %s', (panel, canAccessRegister, target) => {
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
                                    capabilities: canAccessRegister === undefined ? {} : { register_system: canAccessRegister },
                                    selected_school: { long_name: 'Testschule' },
                                },
                            },
                        },
                    }),
                ],
                mocks: {
                    $route: {
                        fullPath: `/admin/settings?tab=register${panel ? `&panel=${panel}` : ''}`,
                        query: {
                            tab: 'register',
                            panel,
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

        expect(screen.queryByText('RegisterUsers Component')).not.toBeInTheDocument()
        expect(screen.queryByText('Anmeldetool')).not.toBeInTheDocument()
        expect(screen.queryByText('Benachrichtigungen')).not.toBeInTheDocument()
        expect(screen.queryByText('Vorlagen')).not.toBeInTheDocument()
        expect(replace).toHaveBeenCalledWith(target)
    })
})
