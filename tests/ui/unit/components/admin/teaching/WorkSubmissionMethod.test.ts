import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import WorkSubmissionMethod from '@/pages/admin/teaching/overview/components/WorkSubmissionMethod.vue'

afterEach(() => vi.unstubAllGlobals())

it('lets the teacher change the return channel independently of task release and preserves the current value on conflict', async () => {
    const method = { channel: 'email', email: 'abgabe@example.test' }
    const saved = { id: 18, status: { submission_method: method } }
    const work = { id: 18, title: 'Übung', status: { submission_method: { channel: 'portal', email: null } } }
    const put = vi.fn().mockRejectedValueOnce({ response: { data: { message: 'Die Arbeit wurde inzwischen geändert.' } } }).mockResolvedValueOnce({ data: { data: saved } })
    vi.stubGlobal('axios', { put })
    const wrapper = mount(WorkSubmissionMethod, { props: { work }, global: { stubs: {
        'v-btn': { template: '<button><slot /></button>' },
        'v-text-field': { props: ['modelValue'], emits: ['update:modelValue'], template: '<input type="email" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
    } } })
    const click = (name: string) => wrapper.findAll('button')
        .find(button => button.attributes('aria-label') === name || button.text() === name)!.trigger('click')
    await click('Abgabeweg verwalten')
    await wrapper.get('input[value=email]').setValue(true)
    await wrapper.get('input[type=email]').setValue(method.email)
    await click('Speichern')
    await flushPromises()
    expect(wrapper.text()).toContain('Die Arbeit wurde inzwischen geändert.')
    expect(wrapper.emitted('saved')).toBeUndefined()
    await click('Speichern')
    await flushPromises()
    expect(put).toHaveBeenCalledWith('/api/admin/teaching/course_works/18/submission-method', { ...method, expected: work.status.submission_method })
    expect(wrapper.emitted('saved')?.[0]).toEqual([saved])
    await wrapper.setProps({ work: saved })
    await click('Abgabeweg verwalten')
    expect(wrapper.text()).not.toContain('Abgabeweg entfernen')
    expect(wrapper.text()).toContain('erst nach „Freigabe beenden“')
    expect(put).toHaveBeenCalledTimes(2)
})

it('shows the current address and locks editing when a stale dialog learns that the work started', async () => {
    const current = { channel: 'email', email: 'abgabe@example.test' }
    const put = vi.fn().mockRejectedValue({ response: { status: 409, data: { submission_lock_reason: 'started', current_submission: current } } })
    vi.stubGlobal('axios', { put })
    const wrapper = mount(WorkSubmissionMethod, { props: { work: { id: 18, title: 'Übung', status: { submission_method: current } } }, global: { stubs: {
        'v-btn': { template: '<button><slot /></button>' },
        'v-text-field': { props: ['modelValue'], template: '<input type="email" :value="modelValue" />' },
    } } })
    await wrapper.get('button').trigger('click')
    await wrapper.get('input[value=portal]').setValue(true)
    await wrapper.findAll('button').find(button => button.text() === 'Speichern')!.trigger('click')
    await flushPromises()
    expect(wrapper.get('fieldset').attributes()).toHaveProperty('disabled')
    expect(wrapper.get('input[type=email]').element.value).toBe(current.email)
    expect(wrapper.text()).toContain('Die Bearbeitung hat bereits begonnen.')
    expect(wrapper.text()).not.toContain('Speichern')
})
