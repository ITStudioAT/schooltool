import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components'
import WorkGradingAction from '@/pages/admin/teaching/overview/components/WorkGradingAction.vue'

const work = { id: 86, teaching_course_id: 16, title: 'E-Mails', simulation_collection: { run_id: 'simulation-run' } }
const response = mode => ({ data: { data: { work_id: 86, course_id: 16, roster_complete: true, text: 'Geprüfter Bewertungsauftrag',
    context: { purpose: 'grade-exercise', mode, operation: { grade: true, preserve_existing: true, send: false, schooltool_import: false, simulation: true },
        simulation_source: { simulation: true, run_id: 'simulation-run', work_id: 86, course_id: 16 } } } } })
function render(props = {}) {
    return mount(WorkGradingAction, { props: { work, date: '2026-10-16', ...props }, global: {
        plugins: [createVuetify({ components: { VBtn } })], components: { 'v-btn': VBtn },
        stubs: { 'v-btn': false, VBtn: false, 'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } },
    } })
}
afterEach(() => vi.unstubAllGlobals())

it.each([false, true])('prepares the matching grading order without claiming completed evaluation: repeat=%s', async repeat => {
    const get = vi.fn().mockResolvedValue(response(repeat ? 'repeat' : 'grade'))
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('axios', { get })
    vi.stubGlobal('navigator', { userAgent: 'Test', clipboard: { writeText } })
    const wrapper = render({ repeat })
    try {
        expect(get).not.toHaveBeenCalled()
        expect(wrapper.get('button').text()).toBe(repeat ? 'Erneut bewerten' : 'Bewerten')
        await wrapper.get('button').trigger('click'); await flushPromises()
        expect(get.mock.calls[0][1].params).toEqual({ purpose: 'evaluation', evaluation_mode: repeat ? 'repeat' : 'grade', date: '2026-10-16' })
        expect(wrapper.text()).toContain('eingesammelten Testabgaben')
        expect(wrapper.text()).not.toContain('Kopiert')
        const copy = wrapper.findAll('button').find(button => button.text() === 'Auftrag kopieren')
        await copy.trigger('click'); await flushPromises()
        expect(writeText).toHaveBeenCalledExactlyOnceWith('Geprüfter Bewertungsauftrag')
        expect(copy.text()).toBe('Kopiert')
    } finally { wrapper.unmount() }
})

it.each(['work', 'simulation', 'scope'])('rejects mismatched grading data: %s', fault => {
    const data = response('grade')
    if (fault === 'work') data.data.data.work_id = 87
    if (fault === 'simulation') data.data.data.context.simulation_source.run_id = 'other-run'
    if (fault === 'scope') data.data.data.context.operation.schooltool_import = true
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue(data) })
    const wrapper = render()
    return (async () => {
        try {
            await wrapper.get('button').trigger('click'); await flushPromises()
            expect(wrapper.get('[role="alert"]').text()).toContain('nicht eindeutig')
            expect(wrapper.find('textarea').exists()).toBe(false)
        } finally { wrapper.unmount() }
    })()
})

it('supports manual copying and rejects delayed responses after switching work', async () => {
    const get = vi.fn().mockResolvedValue(response('grade'))
    vi.stubGlobal('axios', { get })
    vi.stubGlobal('navigator', { userAgent: 'Test', clipboard: { writeText: vi.fn().mockRejectedValue(new Error('blocked')) } })
    const wrapper = render()
    try {
        await wrapper.get('button').trigger('click'); await flushPromises()
        await wrapper.findAll('button').find(button => button.text() === 'Auftrag kopieren').trigger('click'); await flushPromises()
        expect(wrapper.text()).toContain('Strg+C')
        expect(wrapper.get('details').attributes('open')).toBeDefined()
        await wrapper.findAll('button').find(button => button.text() === 'Schließen').trigger('click'); await flushPromises()
        let finish
        get.mockImplementationOnce(() => new Promise(resolve => { finish = resolve }))
        await wrapper.get('button').trigger('click')
        await wrapper.setProps({ work: { ...work, id: 87 } })
        finish(response('grade')); await flushPromises()
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})
