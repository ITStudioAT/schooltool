import { mount } from '@vue/test-utils'
import { expect, it } from 'vitest'
import WorkFinalDeadline from '@/pages/admin/teaching/overview/components/WorkFinalDeadline.vue'
import WorkTimeInput from '@/pages/admin/teaching/overview/components/WorkTimeInput.vue'

const stubs = {
    'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue"><slot /></section>' },
    'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
    'v-date-input': { name: 'DeadlineDateInput', props: ['modelValue'], emits: ['update:modelValue'], template: '<div />' },
    WorkTimeInput: true,
}

it.each([
    ['2026-11-02', '14:30', '2026-11-07', '14:30', { date: '2026-11-07', time: '14:30' }],
    ['2026-11-02', '14:30', '2026-11-02', '10:00', { date: '2026-11-02', time: '10:00' }],
    ['2026-11-02', null, '2026-11-03', '10:00', { date: '2026-11-03', time: '10:00' }],
    ['2026-11-02', '14:30', null, null, { date: null, time: null }],
])('applies one complete pair from the pencil editor', async (date, time, selectedDate, selectedTime, expected) => {
    const wrapper = mount(WorkFinalDeadline, { props: { date, time }, global: { stubs } })
    try {
        await wrapper.get('[aria-label="Endgültige Abgabefrist bearbeiten"]').trigger('click')
        await wrapper.getComponent({ name: 'DeadlineDateInput' }).vm.$emit('update:modelValue', selectedDate)
        await wrapper.getComponent(WorkTimeInput).vm.$emit('update:modelValue', selectedTime)
        expect(wrapper.emitted('change')).toBeUndefined()
        await wrapper.findAll('button').find(button => button.text() === 'Übernehmen')!.trigger('click')
        expect(wrapper.emitted('change')).toEqual([[expected]])
    } finally { wrapper.unmount() }
})

it('keeps the saved pair after invalid input or cancellation', async () => {
    const wrapper = mount(WorkFinalDeadline, { props: { date: '2026-11-02', time: '14:30' }, global: { stubs } })
    try {
        const pencil = wrapper.get('[aria-label="Endgültige Abgabefrist bearbeiten"]')
        await pencil.trigger('click')
        await wrapper.getComponent(WorkTimeInput).vm.$emit('update:modelValue', '25:00')
        expect(wrapper.findAll('button').find(button => button.text() === 'Übernehmen')!.attributes('disabled')).toBeDefined()
        await wrapper.findAll('button').find(button => button.text() === 'Abbrechen')!.trigger('click')
        expect(wrapper.text()).toContain('02.11.2026 · 14:30 Uhr')
        expect(wrapper.emitted('change')).toBeUndefined()
        await pencil.trigger('click')
        expect(wrapper.getComponent(WorkTimeInput).props('modelValue')).toBe('14:30')
    } finally { wrapper.unmount() }
})
