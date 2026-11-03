import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import Registers from '@/pages/admin/registerSystem/components/RegisterSystem/Registers.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'

const register = { id: 41, name: 'Come & Discover Tag 2027 - Versuch', bookings_count: 3, dates_count: 2 }
const other = { id: 42, name: 'Anderes Anmeldetool' }
const slot = { template: '<div><slot /></div>' }

function renderRegisters() {
    const pinia = createTestingPinia({ createSpy: vi.fn, initialState: {
        AdminAdminStore: { config: { user: { roles: ['admin'] } }, selected_schoolyear: { id: 1, name: '2026/27' }, selected_register: register, action: '' },
        AdminRegisterStore: { registers: [register, other] },
    } })
    const wrapper = mount(Registers, { global: { plugins: [pinia], stubs: {
        VRow: slot, VCol: slot, VCard: slot, VCardText: slot, VCardTitle: slot, VCardActions: slot,
        VIcon: true, VChip: slot, VSpacer: true, VCheckbox: true, VDivider: true, VForm: slot, ItsRichTextEditor: true,
        'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
        'v-btn': { props: ['disabled', 'loading'], template: '<button :disabled="disabled || loading"><slot /></button>' },
        'v-text-field': { props: ['modelValue', 'label', 'disabled'], emits: ['update:modelValue'],
            template: '<label>{{ label }}<input :aria-label="label" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>' },
    } } })
    return { wrapper, store: useRegisterStore(pinia), admin: useAdminStore(pinia) }
}

afterEach(() => { vi.restoreAllMocks() })

describe('Deleting a complete registration tool', () => {
    it('requires the exact tool name and describes deletion of all bookings and dates', async () => {
        const { wrapper, store } = renderRegisters()
        try {
            await flushPromises()
            await wrapper.get('button[title="Löschen"]').trigger('click')
            const dialog = wrapper.get('[role="dialog"]')
            expect(dialog.text()).toContain('allen zugehörigen Anmeldungen und Terminen')
            const button = dialog.findAll('button').find(item => item.text() === 'Endgültig löschen')!
            expect(button.attributes('disabled')).toBeDefined()
            await (wrapper.vm as any).destroy()
            expect(store.destroy).not.toHaveBeenCalled()
            await dialog.get('input').setValue('Ja')
            expect(button.attributes('disabled')).toBeDefined()
            await dialog.get('input').setValue(register.name)
            expect(button.attributes('disabled')).toBeUndefined()
            vi.mocked(store.destroy).mockResolvedValueOnce(true)
            await button.trigger('click')
            await flushPromises()
            expect(store.destroy).toHaveBeenCalledExactlyOnceWith(register, register.name)
            expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        } finally { wrapper.unmount() }
    })

    it('cancels without deletion and requires a fresh confirmation on reopening', async () => {
        const { wrapper, store } = renderRegisters()
        try {
            await flushPromises()
            await wrapper.get('button[title="Löschen"]').trigger('click')
            await wrapper.get('[role="dialog"] input').setValue(register.name)
            await wrapper.get('[role="dialog"]').findAll('button')[0].trigger('click')
            expect(store.destroy).not.toHaveBeenCalled()
            await wrapper.get('button[title="Löschen"]').trigger('click')
            expect((wrapper.get('[role="dialog"] input').element as HTMLInputElement).value).toBe('')
        } finally { wrapper.unmount() }
    })

    it('keeps the confirmed target stable and blocks duplicate deletion while awaiting the server', async () => {
        const { wrapper, store, admin } = renderRegisters()
        let finish: (value: boolean) => void = () => {}
        vi.mocked(store.destroy).mockImplementationOnce(() => new Promise(resolve => { finish = resolve }))
        try {
            await flushPromises()
            await wrapper.get('button[title="Löschen"]').trigger('click')
            admin.selected_register = other as never
            await wrapper.get('[role="dialog"] input').setValue(register.name)
            const operation = (wrapper.vm as any).destroy()
            await wrapper.vm.$nextTick()
            expect(wrapper.get('[role="dialog"] input').attributes('disabled')).toBeDefined()
            await (wrapper.vm as any).destroy()
            expect(store.destroy).toHaveBeenCalledExactlyOnceWith(register, register.name)
            finish(false)
            await operation
            await flushPromises()
            expect(wrapper.get('[role="dialog"]').text()).toContain(register.name)
            expect(wrapper.get('[role="dialog"] input').attributes('disabled')).toBeUndefined()
            expect(store.destroy).toHaveBeenCalledTimes(1)
        } finally { wrapper.unmount() }
    })
})
