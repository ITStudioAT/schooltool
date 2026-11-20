import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Submission from '@/pages/homepage/student/overview/myCourse/StudentWorkSubmission.vue'

afterEach(() => vi.unstubAllGlobals())

const portal = { id: 42, title: 'Unterricht', status: 'released', submission_method: { channel: 'portal' }, can_submit: true }

it('uploads the chosen file to its work, shows the saved receipt and allows withdrawal', async () => {
    const receipt = { id: 'receipt-id', name: 'Lösung.txt', submitted_at: '2026-11-05T08:30:00Z' }
    const post = vi.fn().mockResolvedValue({ data: { files: [receipt] } })
    const withdraw = vi.fn().mockResolvedValue({ data: { files: [] } })
    vi.stubGlobal('axios', { post, delete: withdraw })
    const wrapper = mount(Submission, { props: { task: portal, courseId: 4 }, global: { stubs: { 'v-btn': { template: '<button><slot /></button>' } } } })
    const picker = wrapper.get('input[type=file]')
    Object.defineProperty(picker.element, 'files', { value: [new File(['Antwort'], receipt.name)] })
    await picker.trigger('change')
    expect(post).not.toHaveBeenCalled()
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(post).toHaveBeenCalledWith('/api/homepage/student/courses/4/works/42/submissions', expect.any(FormData))
    expect((post.mock.calls[0][1] as FormData).get('file')).toBeInstanceOf(File)
    expect(wrapper.emitted('saved')?.[0]).toEqual([[receipt]])
    await wrapper.setProps({ task: { ...portal, submission_files: [receipt] } })
    expect(wrapper.text()).toContain('Lösung.txt')
    await wrapper.findAll('button').find(button => button.text() === 'Zurückziehen')!.trigger('click')
    await flushPromises()
    expect(withdraw).toHaveBeenCalledWith('/api/homepage/student/courses/4/works/42/submissions/receipt-id')
})

it('shows the saved email address and offers uploads only while the individual student can submit', async () => {
    const wrapper = mount(Submission, { props: { courseId: 4, task: { ...portal, submission_method: { channel: 'email', email: 'abgabe@example.test' } } } })
    expect(wrapper.get('a').attributes('href')).toBe('mailto:abgabe@example.test?subject=Unterricht')
    expect(wrapper.find('input').exists()).toBe(false)
    await wrapper.setProps({ expired: true })
    expect(wrapper.find('a').exists()).toBe(false)
    expect(wrapper.text()).toContain('abgelaufen')
    await wrapper.setProps({ expired: false })
    for (const state of [{ task: { ...portal, status: 'paused' } }, { task: { ...portal, can_submit: false } }, { task: portal, expired: true }]) {
        await wrapper.setProps(state)
        expect(wrapper.find('input').exists()).toBe(false)
    }
    await wrapper.setProps({ task: { ...portal, status: 'email_delivery' }, expired: false })
    expect(wrapper.get('input[type=file]').exists()).toBe(true)
})
