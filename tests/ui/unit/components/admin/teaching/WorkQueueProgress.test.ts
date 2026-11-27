import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, expect, it, vi } from 'vitest'
import { workAssessmentActivity } from '@/helpers/workAssessmentRun'
import WorkQueueProgress from '@/pages/admin/teaching/overview/components/WorkQueueProgress.vue'

const props = { work: { id: 28 }, date: '2026-11-10' }

afterEach(() => {
    vi.unstubAllGlobals()
    vi.useRealTimers()
})

it('hands the saved portal release to the open work when status polling confirms its end', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    const work = { id: 29, teaching_course_id: 4, status: {
        submission_method: { channel: 'portal' },
        student_distribution: { published_at: '2026-11-09T10:00:00Z' },
    } }
    const completed = { ...work, status: { ...work.status, student_distribution: {
        ...work.status.student_distribution, ended_at: '2026-11-10T10:00:00Z',
    } } }
    const get = vi.fn()
        .mockResolvedValueOnce({ data: { run: null, completed_portal_work: { ...completed, teaching_course_id: 5 } } })
        .mockResolvedValue({ data: { run: null, completed_portal_work: completed } })
    vi.stubGlobal('axios', { get })
    const wrapper = mount(WorkQueueProgress, { props: { ...props, work } })
    try {
        await flushPromises()
        expect(get.mock.calls[0][1].params).toMatchObject({ include_portal_completion: 1 })
        expect(wrapper.emitted('portal-completed')).toBeUndefined()
        await vi.advanceTimersByTimeAsync(15000)
        expect(wrapper.emitted('portal-completed')).toEqual([[completed]])
        await wrapper.setProps({ work: completed })
        await vi.advanceTimersByTimeAsync(15000)
        expect(get.mock.calls.at(-1)?.[1].params).not.toHaveProperty('include_portal_completion')
        expect(wrapper.emitted('portal-completed')).toHaveLength(1)
    } finally { wrapper.unmount() }
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


it('restores a queued work, keeps it locked across errors and reopening, then releases it on completion', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    const queued = { id: 'active-assessment', date: props.date, state: 'queued', queued_at: '2026-11-10T10:00:00.000Z' }
    const work = { id: 57, teaching_course_id: 27, assessment_run: queued }
    const run = { ...queued, state: 'running', progress: { state: 'running', active: 'assessment_verify',
        detail: 'Dateien bestätigen', steps: ['assessment_verify'], completed: [] } }
    const get = vi.fn().mockRejectedValueOnce(new Error('network'))
        .mockResolvedValueOnce({ data: { run, activity: run } })
        .mockResolvedValueOnce({ data: { run: null, activity: null } })
        .mockResolvedValueOnce({ data: { run: null, activity: { ...queued, state: 'completed' } } })
    vi.stubGlobal('axios', { get })
    const statusProps = { work, userId: 7, date: '2026-11-11' }
    expect(workAssessmentActivity(7, work)?.state).toBe('queued')
    const background = mount(WorkQueueProgress, { props: { ...statusProps, monitorOnly: true } })
    await flushPromises()
    expect(workAssessmentActivity(7, work)?.state).toBe('queued')
    background.unmount()

    const reopened = mount(WorkQueueProgress, { props: statusProps })
    try {
        await flushPromises()
        expect(reopened.get('summary').text()).toContain('Läuft')
        expect(reopened.text()).toContain('Dateien bestätigen')
        expect(workAssessmentActivity(7, work)?.state).toBe('running')
        expect(workAssessmentActivity(7, { id: 58, teaching_course_id: 27 })).toBeNull()
        await vi.advanceTimersByTimeAsync(2000)
        expect(workAssessmentActivity(7, work)?.state).toBe('running')
        await vi.advanceTimersByTimeAsync(2000)
        expect(workAssessmentActivity(7, work)).toBeNull()
    } finally { reopened.unmount() }
})
