import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import axios from 'axios'
import PersonalBackupRecovery from '@/pages/admin/teaching/settings/components/PersonalBackupRecovery.vue'

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }))

function renderRecovery() {
    return mount(PersonalBackupRecovery, {
        global: {
            stubs: {
                ItsGridBox: { template: '<section><slot /></section>' },
                'v-alert': { template: '<div role="status"><slot /></div>' },
                'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'v-checkbox': {
                    props: ['modelValue', 'label', 'disabled'],
                    template: '<label><input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.checked)" />{{ label }}</label>',
                },
                'v-autocomplete': {
                    props: ['modelValue', 'items', 'disabled', 'label'],
                    template: '<select :aria-label="label" :value="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', Number($event.target.value))"><option value="">Wählen</option><option v-for="item in items" :key="item.id" :value="item.id">{{ item.label }}</option></select>',
                },
            },
        },
    })
}

describe('Administrative teaching backup recovery', () => {
    beforeEach(() => {
        vi.resetAllMocks()
        vi.mocked(axios.get).mockResolvedValue({ data: {
            data: [{
                id: 7, owner_name: 'Lehrkraft', student_mappings: {}, import_mappings: {},
                missing: {
                    users: [{ id: 9, last_name: 'Alt', first_name: 'Anna', schoolclass: '2A', email: 'anna@example.test' }],
                    imports: [{ id: 8, last_name: 'Alt', first_name: 'Anna', class: '2A', schoolyear_id: 3, student_code: 's123' }],
                },
            }],
            meta: {
                student_options: [{ id: 10, label: 'Anna Alt · 2A · ID 10' }],
                import_options: [{ id: 11, schoolyear_id: 3, label: 'Anna Alt · s123 · ID 11' }, { id: 12, schoolyear_id: 4, label: 'Falsches Schuljahr' }],
            },
        } })
    })

    it('requires identity confirmation and sends only explicit mappings', async () => {
        const wrapper = renderRecovery()
        await flushPromises()
        expect(axios.get).toHaveBeenCalledWith('/api/admin/teaching/personal-backup-recovery')
        expect(wrapper.text()).toContain('bisherige ID 9')
        expect(wrapper.text()).not.toContain('Falsches Schuljahr')
        const approve = wrapper.findAll('button').find(button => button.text() === 'Zuordnung freigeben')!
        expect(approve.attributes('disabled')).toBeDefined()
        await approve.trigger('click')
        expect(axios.post).not.toHaveBeenCalled()
        await wrapper.findAll('select')[0].setValue('10')
        await wrapper.findAll('select')[1].setValue('11')
        await wrapper.find('input[type="checkbox"]').setValue(true)
        vi.mocked(axios.post).mockResolvedValue({ data: { data: { resolved: true } } })
        await approve.trigger('click')
        await flushPromises()

        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/personal-backup-recovery/7/resolve', {
            student_mappings: { 9: 10 }, import_mappings: { 8: 11 }, confirm_identity: true,
        })
        expect(wrapper.text()).toContain('Die Zuordnung wurde freigegeben.')
        wrapper.unmount()
    })

    it('shows an authorization failure while retaining the pending request', async () => {
        const wrapper = renderRecovery()
        await flushPromises()
        await wrapper.find('input[type="checkbox"]').setValue(true)
        vi.mocked(axios.post).mockRejectedValue({ response: { data: { message: 'Keine Berechtigung.' } } })
        await wrapper.findAll('button').find(button => button.text() === 'Zuordnung freigeben')!.trigger('click')
        await flushPromises()

        expect(wrapper.text()).toContain('Keine Berechtigung.')
        expect(wrapper.text()).toContain('bisherige ID 9')
        wrapper.unmount()
    })
})
