import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import WorkReleaseDeadline from '@/pages/admin/teaching/overview/components/WorkReleaseDeadline.vue'

afterEach(() => vi.unstubAllGlobals())

it('preserves the draft through invalid time, validation and conflict, then shows a partial sync warning', async () => {
    const work = { id: 42, finish_until_date: '2026-11-18', finish_until_time: '14:00' }
    const latest = { ...work, finish_until_date: '2026-11-19', finish_until_time: null }
    const saved = { ...work, finish_until_date: '2026-11-22', finish_until_time: '15:30' }
    const put = vi.fn()
        .mockRejectedValueOnce({ response: { status: 422, data: { errors: { finish_until_time: ['Ungültige Wiener Ortszeit'] } } } })
        .mockRejectedValueOnce({ response: { status: 409, data: { message: 'Die Abgabefrist wurde inzwischen geändert.' } } })
        .mockResolvedValueOnce({ data: { data: saved, deadline_sync_warning: 'Frist gespeichert. Dropbox-Synchronisierung offen.' } })
    const get = vi.fn().mockResolvedValue({ data: { data: latest } })
    vi.stubGlobal('axios', { get, put })
    const wrapper = mount(WorkReleaseDeadline, { props: { work }, global: { stubs: {
        'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
        'v-text-field': { props: ['modelValue'], emits: ['update:modelValue'],
            template: '<input type="date" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
        WorkTimeInput: { props: ['modelValue'], emits: ['update:modelValue'],
            template: '<input type="text" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
    } } })
    const action = (label: string) => wrapper.findAll('button').find(btn => btn.text() === label)!
    const submit = () => wrapper.get('form').trigger('submit')
    try {
        await action('Abgabefrist bearbeiten').trigger('click')
        await wrapper.get('input[type=date]').setValue('2026-11-22')
        await wrapper.get('input[type=text]').setValue('25:00')
        await submit()
        expect(put).not.toHaveBeenCalled()
        await wrapper.get('input[type=text]').setValue('15:30')
        await submit()
        await flushPromises()
        expect(wrapper.get('[role="alert"]').text()).toContain('Ungültige Wiener Ortszeit')
        await submit()
        await flushPromises()
        expect(action('Abgabefrist speichern').attributes('disabled')).toBeDefined()
        expect(wrapper.get('[role="alert"]').text()).toContain('inzwischen geändert')
        await action('Aktuelle Frist laden').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledOnce()
        expect((wrapper.get('input[type=text]').element as HTMLInputElement).value).toBe('15:30')
        await submit()
        await flushPromises()
        expect(put).toHaveBeenLastCalledWith(expect.stringContaining('/42'), {
            finish_until_date: '2026-11-22', finish_until_time: '15:30', expected_final_deadline: { date: '2026-11-19', time: null },
        })
        expect(wrapper.text()).toContain('Dropbox-Synchronisierung offen')
        expect(wrapper.emitted('saved')).toEqual([[latest], [saved]])
    } finally { wrapper.unmount() }
})
