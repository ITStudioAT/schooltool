import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components'
import { createTestingPinia } from '@pinia/testing'
import WorkSubmissionCollection from '@/pages/admin/teaching/overview/components/WorkSubmissionCollection.vue'

const work = { id: 86, teaching_course_id: 16, title: 'E-Mails' }
const response = mode => ({ data: { data: { work_id: 86, course_id: 16, roster_complete: true, text: 'Verbindlicher Einsammelauftrag',
    context: { purpose: 'collect-exercise-submissions', mode, operation: { collect_submissions: true, preserve_existing: true, grade: false, send: false, schooltool_import: false } } } } })
function mountCollection(repeat = false) {
    return mount(WorkSubmissionCollection, { props: { work, repeat, date: '2026-10-16', dispatch: { status: 'sent', confirmed_count: 1 } }, global: { plugins: [createTestingPinia({ createSpy: vi.fn }), createVuetify({ components: { VBtn } })],
        components: { 'v-btn': VBtn }, stubs: { 'v-btn': false, VBtn: false,
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } } } })
}
afterEach(() => vi.unstubAllGlobals())

it('blocks the collection action until a task email has been sent', async () => {
    const get = vi.fn()
    vi.stubGlobal('axios', { get })
    const wrapper = mountCollection()
    try {
        await wrapper.setProps({ dispatch: { status: 'missing', confirmed_count: 0 } })
        expect(wrapper.get('button').element.disabled).toBe(true)
        await wrapper.get('button').trigger('click')
        expect(get).not.toHaveBeenCalled()
        await wrapper.setProps({ dispatch: { status: 'partial', confirmed_count: 1 } })
        expect(wrapper.get('button').element.disabled).toBe(false)
    } finally { wrapper.unmount() }
})

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
