import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WorkTimeInput from '@/pages/admin/teaching/overview/components/WorkTimeInput.vue'

function mountTime(props = {}) {
    return mount(WorkTimeInput, { props, global: { stubs: {
        VTimePicker: true,
        'v-text-field': { name: 'VTextField', props: ['modelValue'], template: '<div><slot name="append-inner" /></div>' },
        'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
    } } })
}

describe('Work deadline time selection', () => {
    it('keeps the saved time on cancel and applies only the explicitly selected time', async () => {
        const wrapper = mountTime({ modelValue: '16:00' })
        try {
            await wrapper.get('[aria-label="Uhrzeit auswählen"]').trigger('click')
            expect(wrapper.getComponent({ name: 'VTimePicker' }).props('modelValue')).toBe('16:00')
            wrapper.getComponent({ name: 'VTimePicker' }).vm.$emit('update:modelValue', '18:45')
            await wrapper.findAll('v-btn').find(button => button.text() === 'Abbrechen')!.trigger('click')
            expect(wrapper.emitted('update:modelValue')).toBeUndefined()

            await wrapper.get('[aria-label="Uhrzeit auswählen"]').trigger('click')
            expect(wrapper.getComponent({ name: 'VTimePicker' }).props('modelValue')).toBe('16:00')
            wrapper.getComponent({ name: 'VTimePicker' }).vm.$emit('update:modelValue', '09:35')
            await wrapper.findAll('v-btn').find(button => button.text() === 'Übernehmen')!.trigger('click')
            expect(wrapper.emitted('update:modelValue')).toEqual([['09:35']])
        } finally { wrapper.unmount() }
    })

    it('forwards keyboard edits and clearing without adding a default time', async () => {
        const wrapper = mountTime({ modelValue: null })
        try {
            await wrapper.get('[aria-label="Uhrzeit auswählen"]').trigger('click')
            expect(wrapper.getComponent({ name: 'VTimePicker' }).props('modelValue')).toBeNull()
            await wrapper.findAll('v-btn').find(button => button.text() === 'Übernehmen')!.trigger('click')
            expect(wrapper.emitted('update:modelValue')).toBeUndefined()
            wrapper.getComponent({ name: 'VTextField' }).vm.$emit('update:modelValue', '08:15')
            wrapper.getComponent({ name: 'VTextField' }).vm.$emit('update:modelValue', null)
            expect(wrapper.emitted('update:modelValue')).toEqual([['08:15'], [null]])
        } finally { wrapper.unmount() }
    })

    it('does not open the picker while the work is being saved', async () => {
        const wrapper = mountTime({ modelValue: '16:00', disabled: true })
        try {
            await wrapper.get('[aria-label="Uhrzeit auswählen"]').trigger('click')
            expect(wrapper.findComponent({ name: 'VTimePicker' }).exists()).toBe(false)
            expect(wrapper.emitted('update:modelValue')).toBeUndefined()
        } finally { wrapper.unmount() }
    })
})
