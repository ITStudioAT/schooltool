import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components'
import WorkSubmissionCollection from '@/pages/admin/teaching/overview/components/WorkSubmissionCollection.vue'

const work = { id: 86, teaching_course_id: 16, title: 'E-Mails', status: { student_distribution: { ended_at: '2026-10-16T12:00:00Z' } } }
const response = mode => ({ data: { data: { work_id: 86, course_id: 16, roster_complete: true, text: 'Verbindlicher Einsammelauftrag',
    context: { purpose: 'collect-exercise-submissions', mode, operation: { collect_submissions: true, preserve_existing: true, grade: false, send: false, schooltool_import: false } } } } })
function mountCollection(repeat = false) {
    return mount(WorkSubmissionCollection, { props: { work, repeat, date: '2026-10-16' }, global: { plugins: [createVuetify({ components: { VBtn } })],
        components: { 'v-btn': VBtn }, stubs: { 'v-btn': false, VBtn: false,
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } } } })
}
afterEach(() => vi.unstubAllGlobals())

it.each([
    { published_at: '2026-10-15T12:00:00Z' },
    { published_at: '2026-10-15T12:00:00Z', withdrawn_at: '2026-10-16T11:00:00Z' },
    {},
])('requires the terminal state even after dispatch, manual completion or a pause: %j', async distribution => {
    const get = vi.fn()
    vi.stubGlobal('axios', { get })
    const wrapper = mountCollection()
    try {
        await wrapper.setProps({ work: { ...work, status: {
            task_dispatch_completed: true, student_distribution: distribution,
            assignment_mail: { batches: { all: { transport: 'postmark', recipients: [{ state: 'accepted' }] } } },
        } } })
        expect(wrapper.get('button').element.disabled).toBe(true)
        await wrapper.get('button').trigger('click')
        expect(get).not.toHaveBeenCalled()
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('shows the existing portal originals with a download and without a copy action', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 86, course_id: 16, channel: 'portal',
        files: [{ id: 'one', name: 'Abgabe.pdf', student_name: 'Beispiel', download_url: '/api/submission-download' }] } } })
    vi.stubGlobal('axios', { get })
    const wrapper = mountCollection()
    try {
        await wrapper.setProps({ work: { ...work, groups: [{ date: '2026-10-16', student_ids: [12] }], status: {
            ...work.status, submission_method: { channel: 'portal' }, portal_submissions: [{ student_id: 12, id: 'one' }],
        } } })
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="dialog"]').text()).toContain('Abgabe.pdf')
        expect(wrapper.get('[aria-label="Abgabe.pdf herunterladen"]').attributes('href')).toBe('/api/submission-download')
        expect(wrapper.text()).not.toContain('Auftrag kopieren')
    } finally { wrapper.unmount() }
})

it.each([{ files: [] }, { files: [{ student_id: 13 }] }, { files: [{ student_id: 12, withdrawn_at: '2026-10-16T11:00:00Z' }] }])(
    'does not offer portal collection without a current file for the selected date: %j', async ({ files }) => {
        const wrapper = mountCollection()
        try {
            await wrapper.setProps({ work: { ...work, groups: [
                { date: '2026-10-16', student_ids: [12] }, { date: '2026-10-17', student_ids: [13] },
            ], status: { ...work.status, submission_method: { channel: 'portal' }, portal_submissions: files } } })
            expect(wrapper.find('button').exists()).toBe(false)
        } finally { wrapper.unmount() }
    },
)

it.each([false, true])('generates the correct collection order and shows a check only after copying: %s', async repeat => {
    const mode = repeat ? 'repeat' : 'collect'
    const get = vi.fn().mockResolvedValue(response(mode))
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('axios', { get })
    vi.stubGlobal('navigator', { userAgent: 'Test', clipboard: { writeText } })
    const wrapper = mountCollection(repeat)
    try {
        expect(wrapper.get('button').text()).toBe(repeat ? 'Erneut einsammeln' : 'Abgaben einsammeln')
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(get.mock.calls[0][1].params).toEqual({ purpose: 'submission_collection', collection_mode: mode, date: '2026-10-16' })
        const copy = wrapper.findAll('button').find(button => button.text() === 'Auftrag kopieren')!
        expect(wrapper.text()).not.toContain('Kopiert')
        await copy.trigger('click')
        await flushPromises()
        expect(writeText).toHaveBeenCalledExactlyOnceWith('Verbindlicher Einsammelauftrag')
        expect(copy.text()).toBe('Kopiert')
        expect(wrapper.findAllComponents(VBtn).find(button => button.text() === 'Kopiert')!.props('prependIcon')).toBe('mdi-check')
        expect(get).toHaveBeenCalledOnce()
    } finally { wrapper.unmount() }
})

it('does not claim a copy succeeded when clipboard permission is denied', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue(response('collect')) })
    vi.stubGlobal('navigator', { userAgent: 'Test', clipboard: { writeText: vi.fn().mockRejectedValue(new Error('blocked')) } })
    const wrapper = mountCollection()
    try {
        await wrapper.get('button').trigger('click'); await flushPromises()
        await wrapper.findAll('button').find(button => button.text() === 'Auftrag kopieren')!.trigger('click'); await flushPromises()
        expect(wrapper.text()).not.toContain('Kopiert')
        expect(wrapper.text()).toContain('Strg+C')
    } finally { wrapper.unmount() }
})

it('rejects mismatched work identity and discards a response after switching work', async () => {
    let resolve: (value: unknown) => void = () => {}
    const get = vi.fn().mockReturnValue(new Promise(finish => { resolve = finish }))
    vi.stubGlobal('axios', { get })
    const wrapper = mountCollection()
    try {
        await wrapper.get('button').trigger('click')
        await wrapper.setProps({ work: { ...work, id: 87 } })
        resolve(response('collect')); await flushPromises()
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        get.mockResolvedValue(response('collect'))
        await wrapper.get('button').trigger('click'); await flushPromises()
        expect(wrapper.text()).toContain('nicht eindeutig bestätigt')
        expect(wrapper.find('textarea').exists()).toBe(false)
    } finally { wrapper.unmount() }
})
