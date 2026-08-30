import { createTestingPinia } from '@pinia/testing'
import { fireEvent, render, screen } from '@testing-library/vue'
import { describe, expect, it, vi } from 'vitest'
import Schools from '@/pages/admin/superAdmin/components/Schools.vue'
import { useSchoolStore } from '@/stores/admin/SchoolStore'

const buttonStub = {
    emits: ['click'],
    template: '<button type="button" @click="$emit(\'click\', $event)"><slot /></button>',
}

const colorPickerStub = {
    props: ['modelValue'],
    emits: ['update:modelValue'],
    template: `
        <button
            type="button"
            aria-label="Schulfarbe auswählen"
            @click="$emit('update:modelValue', '#336699')">
            {{ modelValue }}
        </button>
    `,
}

const vuetifyStubs = {
    'v-col': { template: '<div><slot /></div>' },
    VCol: { template: '<div><slot /></div>' },
    'v-row': { template: '<div><slot /></div>' },
    VRow: { template: '<div><slot /></div>' },
    'v-btn': buttonStub,
    VBtn: buttonStub,
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
    'v-card-actions': { template: '<div><slot /></div>' },
    VCardActions: { template: '<div><slot /></div>' },
    'v-form': { template: '<form><slot /></form>' },
    VForm: { template: '<form><slot /></form>' },
    'v-text-field': { props: ['label'], template: '<label>{{ label }}<input /></label>' },
    VTextField: { props: ['label'], template: '<label>{{ label }}<input /></label>' },
    'v-checkbox': { props: ['label'], template: '<label>{{ label }}<input type="checkbox" /></label>' },
    VCheckbox: { props: ['label'], template: '<label>{{ label }}<input type="checkbox" /></label>' },
    'v-alert': { template: '<div><slot /></div>' },
    VAlert: { template: '<div><slot /></div>' },
    'v-color-picker': colorPickerStub,
    VColorPicker: colorPickerStub,
}

describe('School color settings', () => {
    it('lets an admin select a color for their own school', async () => {
        render(Schools, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                action: 'edit_school',
                                config: {
                                    roles: ['admin'],
                                    selected_school: {
                                        id: 7,
                                        long_name: 'Testschule',
                                        short_name: 'TS',
                                        email: 'school@example.com',
                                        logo: 'test-school.png',
                                        color: '#1976D2',
                                        is_selectable: true,
                                    },
                                },
                            },
                            AdminSchoolStore: {
                                schools: [],
                                selected_schools: [],
                                meta: {},
                                data: {
                                    id: 7,
                                    long_name: 'Testschule',
                                    short_name: 'TS',
                                    email: 'school@example.com',
                                    color: '#1976D2',
                                    is_selectable: true,
                                },
                            },
                        },
                    }),
                ],
                stubs: {
                    ...vuetifyStubs,
                    SearchField: { template: '<div />' },
                    Pagination: { template: '<div />' },
                    FileUpload: { template: '<div />' },
                },
            },
        })

        const schoolStore = useSchoolStore()

        expect(screen.getByRole('button', { name: 'Schulfarbe auswählen' })).toHaveTextContent('#1976D2')
        expect(screen.getByRole('img', { name: 'Logo von Testschule' })).toHaveAttribute('src', '/storage/images/logos/test-school.png')
        expect(screen.queryByText('Hinzufügen')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Schulfarbe auswählen' }))

        expect(schoolStore.data.color).toBe('#336699')
    })

    it('reloads admin config after saving so the shell color changes immediately', async () => {
        const context: any = {
            is_uploading: false,
            is_valid: false,
            $refs: {
                form: {
                    validate: vi.fn(async () => {
                        context.is_valid = true
                    }),
                },
            },
            schoolStore: {
                update: vi.fn().mockResolvedValue(true),
                index: vi.fn().mockResolvedValue(true),
            },
            adminStore: {
                loadConfig: vi.fn().mockResolvedValue(true),
            },
            canManageAllSchools: true,
            selected_schools: [7],
            action: 'edit_school',
            data: {
                id: 7,
                color: '#336699',
            },
        }

        await (Schools as any).methods.saveSchool.call(context, context.data)

        expect(context.schoolStore.update).toHaveBeenCalledOnce()
        expect(context.schoolStore.index).toHaveBeenCalledOnce()
        expect(context.adminStore.loadConfig).toHaveBeenCalledOnce()
    })
})
