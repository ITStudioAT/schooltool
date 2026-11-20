import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, expect, it, vi } from 'vitest'
import WorkQueueProgress from '@/pages/admin/teaching/overview/components/WorkQueueProgress.vue'

const props = { work: { id: 28 }, date: '2026-11-10' }

afterEach(() => {
    vi.unstubAllGlobals()
    vi.useRealTimers()
})

it('shows real queue steps as the worker runs and restores a completed run compactly', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    const steps = ['assessment_documents', 'assessment_verify']
    const get = vi.fn()
        .mockResolvedValueOnce({ data: { run: { state: 'queued', progress: null } } })
        .mockResolvedValueOnce({ data: { run: { state: 'running', progress: {
            state: 'running', steps, active: 'assessment_verify', completed: ['assessment_documents'],
        } } } })
        .mockResolvedValueOnce({ data: { run: { state: 'completed', progress: { state: 'done', steps, completed: steps } } } })
    vi.stubGlobal('axios', { get })
    const wrapper = mount(WorkQueueProgress, { props })
    try {
        await flushPromises()
        expect(wrapper.get('summary').text()).toContain('In der Warteschlange')
        expect(get.mock.calls[0][1].params).toEqual({ progress_only: 1, date: props.date })
        await vi.advanceTimersByTimeAsync(2000)
        await flushPromises()
        expect(wrapper.get('details').attributes('open')).toBeDefined()
        expect(wrapper.findAll('li').map(item => item.text())).toEqual([
            expect.stringContaining('Erledigt'), expect.stringContaining('Wird ausgeführt'),
        ])
        await vi.advanceTimersByTimeAsync(2000)
        await flushPromises()
        expect(wrapper.get('summary').text()).toContain('Abgeschlossen')
        expect(wrapper.get('details').attributes('open')).toBeUndefined()
    } finally { wrapper.unmount() }
    await vi.advanceTimersByTimeAsync(30000)
    expect(get).toHaveBeenCalledTimes(3)
})

it('drops a delayed response when switching work and lets the teacher retry a failed lookup', async () => {
    let finish: (value: unknown) => void = () => {}
    const get = vi.fn().mockImplementationOnce(() => new Promise(resolve => { finish = resolve }))
        .mockRejectedValueOnce(new Error()).mockResolvedValueOnce({ data: { run: null } })
    vi.stubGlobal('axios', { get })
    const wrapper = mount(WorkQueueProgress, { props, global: { stubs: { 'v-btn': { template: '<button><slot /></button>' } } } })
    try {
        await wrapper.setProps({ work: { id: 29 } })
        await flushPromises()
        finish({ data: { run: { state: 'queued' } } })
        await flushPromises()
        expect(wrapper.text()).toContain('Erneut abrufen')
        expect(wrapper.text()).not.toContain('In der Warteschlange')
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.find('aside').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('shows email processing counts and keeps a local test distinct from a real dispatch', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    const batch = { date: props.date, state: 'sending', transport: 'mailpit', withdrawn: false, counts: { queued: 1, accepted: 2 } }
    const get = vi.fn().mockResolvedValueOnce({ data: { run: null, mail: [batch] } })
        .mockResolvedValueOnce({ data: { run: null, mail: [{ ...batch, state: 'partial', withdrawn: true }] } })
    vi.stubGlobal('axios', { get })
    const wrapper = mount(WorkQueueProgress, { props })
    try {
        await flushPromises()
        expect(wrapper.get('summary').text()).toContain('Aufgabenmail im lokalen Test')
        expect(wrapper.get('details').attributes('open')).toBeDefined()
        expect(wrapper.get('ul').text()).toContain('In der Warteschlange · 1')
        expect(wrapper.get('ul').text()).toContain('Vom Versanddienst angenommen · 2')
        await vi.advanceTimersByTimeAsync(2000)
        await flushPromises()
        expect(wrapper.get('summary').text()).toContain('Versand gestoppt')
    } finally { wrapper.unmount() }
})
