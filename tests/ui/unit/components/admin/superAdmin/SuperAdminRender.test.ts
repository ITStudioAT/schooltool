import { createTestingPinia } from '@pinia/testing'
import { render, waitFor } from '@testing-library/vue'
import { defineComponent, h, inject, provide } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import SuperAdmin from '@/pages/admin/superAdmin/SuperAdmin.vue'

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
    'v-row': { template: '<div><slot /></div>' },
    VRow: { template: '<div><slot /></div>' },
    'v-col': { template: '<div><slot /></div>' },
    VCol: { template: '<div><slot /></div>' },
    'v-btn': VBtnStub,
    VBtn: VBtnStub,
    'v-btn-toggle': VBtnToggleStub,
    VBtnToggle: VBtnToggleStub,
    'v-icon': { template: '<i><slot /></i>' },
    VIcon: { template: '<i><slot /></i>' },
    'v-chip': { template: '<span><slot /></span>' },
    VChip: { template: '<span><slot /></span>' },
    'v-card': { template: '<div><slot /></div>' },
    VCard: { template: '<div><slot /></div>' },
    'v-card-text': { template: '<div><slot /></div>' },
    VCardText: { template: '<div><slot /></div>' },
    'v-card-title': { template: '<div><slot /></div>' },
    VCardTitle: { template: '<div><slot /></div>' },
    'v-card-actions': { template: '<div><slot /></div>' },
    VCardActions: { template: '<div><slot /></div>' },
    'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
    VDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
    'v-list': { template: '<div><slot /></div>' },
    VList: { template: '<div><slot /></div>' },
    'v-list-item': { template: '<div><slot name="title" /><slot /></div>' },
    VListItem: { template: '<div><slot name="title" /><slot /></div>' },
    'v-select': { template: '<div />' },
    VSelect: { template: '<div />' },
    'v-text-field': { template: '<input />' },
    VTextField: { template: '<input />' },
    'v-form': { template: '<form><slot /></form>' },
    VForm: { template: '<form><slot /></form>' },
}

describe('Super admin page rendered teacher submenu flow', () => {
    it('redirects the migrated teacher subsection to teaching settings', async () => {
        const replace = vi.fn()

        render(SuperAdmin, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                config: {
                                    roles: ['super_admin'],
                                    selected_school: null,
                                    selected_schoolyear: null,
                                    version: 'test',
                                    impersonation: { is_impersonating: false },
                                },
                                action: '',
                                main_action: '',
                                impersonatable_schools: [],
                                impersonatable_users: [],
                                impersonatable_users_meta: [],
                            },
                        },
                    }),
                ],
                stubs: {
                    ...vuetifyStubs,
                    ActiveSchool: { template: '<div>ActiveSchool Component</div>' },
                    Schools: { template: '<div>Schools Component</div>' },
                    Schoolyears: { template: '<div>Schoolyears Component</div>' },
                    Licences: { template: '<div>Licences Component</div>' },
                    LicenceSchools: { template: '<div>LicenceSchools Component</div>' },
                    Roles: { template: '<div>Roles Component</div>' },
                    Users: { template: '<div>Users Component</div>' },
                    Teachers: { template: '<div>Teachers Component</div>' },
                    TeachersList: { template: '<div>TeachersList Component</div>' },
                    Log: { template: '<div>Log Component</div>' },
                },
                mocks: {
                    $route: {
                        path: '/admin/super_admin/teachers',
                        params: { section: 'teachers' },
                        query: {},
                    },
                    $router: {
                        push: vi.fn(),
                        replace,
                    },
                },
            },
        })

        await waitFor(() => {
            expect(replace).toHaveBeenCalledWith('/admin/settings?tab=teaching&panel=teachers')
        })
    })
})
