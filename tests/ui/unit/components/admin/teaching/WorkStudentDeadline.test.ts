import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import WorkStudentDeadline from '@/pages/admin/teaching/overview/components/WorkStudentDeadline.vue'

const work = { id: 86, finish_until_date: '2026-10-23', communicated_deadline: null }

function render(currentWork: any = work, post = vi.fn()) {
    vi.stubGlobal('axios', { post })
    const wrapper = mount(WorkStudentDeadline, { props: { work: currentWork }, global: { stubs: {
        'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue" role="dialog"><slot /></div>' },
        'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
        'v-text-field': { props: ['modelValue', 'label', 'disabled'], emits: ['update:modelValue'],
            template: '<input aria-label="Datum" type="date" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
        WorkTimeInput: { props: ['modelValue', 'disabled'], emits: ['update:modelValue'],
            template: '<input aria-label="Uhrzeit" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
        'v-card': { template: '<section><slot /></section>' },
        'v-card-title': { template: '<h2><slot /></h2>' },
        'v-card-text': { template: '<div><slot /></div>' },
        'v-card-actions': { template: '<div><slot /></div>' },
        'v-alert': { template: '<div role="alert"><slot /></div>' },
        'v-spacer': true,
    } } })
    const button = (text: string) => wrapper.findAll('button').find(item => item.text() === text)!
    return { wrapper, button, post }
}

afterEach(() => { vi.unstubAllGlobals(); vi.restoreAllMocks() })

it('sets a dated student deadline and emits the saved work without touching other fields', async () => {
    const saved = { ...work, communicated_deadline: { date: '2026-10-22', time: '10:30', at: '2026-10-22T10:30:00+02:00' } }
    const { wrapper, button, post } = render(work, vi.fn().mockResolvedValue({ data: { data: saved } }))
    try {
        await button('Festlegen').trigger('click')
        await wrapper.get('[aria-label="Datum"]').setValue('2026-10-22')
        await wrapper.get('[aria-label="Uhrzeit"]').setValue('10:30')
        await button('Speichern').trigger('click')
        await flushPromises()
        expect(post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/86/student-deadline', {
            date: '2026-10-22', time: '10:30', expected: { date: null, time: null },
        })
        expect(wrapper.emitted('saved')).toEqual([[{ data: saved }]])
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        await wrapper.setProps({ work: saved })
        expect(wrapper.text()).toContain('22.10.2026 · 10:30')
        expect(button('Ändern')).toBeDefined()
    } finally { wrapper.unmount() }
})

it('allows a date without inventing a time and supports removing it', async () => {
    const saved = { ...work, communicated_deadline: { date: '2026-10-22', time: null, at: null } }
    const post = vi.fn().mockResolvedValueOnce({ data: { data: saved } }).mockResolvedValueOnce({ data: { data: work } })
    const { wrapper, button } = render(work, post)
    try {
        await button('Festlegen').trigger('click')
        await wrapper.get('[aria-label="Datum"]').setValue('2026-10-22')
        await button('Speichern').trigger('click')
        await flushPromises()
        expect(post.mock.calls[0][1].time).toBeNull()
        await wrapper.setProps({ work: saved })
        await button('Ändern').trigger('click')
        await button('Frist entfernen').trigger('click')
        await flushPromises()
        expect(post.mock.calls[1][1]).toEqual({ date: null, time: null, expected: { date: '2026-10-22', time: null } })
    } finally { wrapper.unmount() }
})

it('shows server conflicts without closing the editor or reporting a successful save', async () => {
    const { wrapper, button } = render(work, vi.fn().mockRejectedValue({ response: { data: { message: 'Die Schülerfrist wurde inzwischen geändert.' } } }))
    try {
        await button('Festlegen').trigger('click')
        await button('Speichern').trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="alert"]').text()).toContain('inzwischen geändert')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
        expect(wrapper.emitted('saved')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('does not edit an unsaved work or persist anything when cancelled', async () => {
    const { wrapper, button, post } = render({ ...work, id: null })
    try {
        expect(button('Festlegen').attributes('disabled')).toBeDefined()
        await wrapper.setProps({ work })
        await button('Festlegen').trigger('click')
        await wrapper.get('[aria-label="Datum"]').setValue('2026-10-22')
        await button('Abbrechen').trigger('click')
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})
