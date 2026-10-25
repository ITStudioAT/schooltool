import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import TeachingSimulation from '@/pages/admin/teachingTools/TeachingSimulation.vue'

let wrapper
let api
const work = { id: 86, title: 'E-Mails', course_title: 'DGB · 3B · 1', dates: ['2026-10-16'] }
function sampleRun() {
    return { simulation: true, id: 'test-run', title: 'E-Mails', course_title: work.course_title,
        created_at: '2026-10-24T14:00:00Z', history: [], rows: [{ participant_id: 'user:492',
            person: { first_name: 'Anna', last_name: 'Beispiel', class_name: '3B', email: 'anna@example.test' },
            versions: [{ number: 1, scenario: 'missing_attachment', subject: 'Meine Ausarbeitung', message: 'Testantwort',
                mailpit_id: null, collected_at: null, artifacts: [{ name: 'Ausarbeitung_v1.pdf', path: 'run/file.pdf' }], attachments: [] }] }] }
}
beforeEach(() => {
    api = { get: vi.fn().mockResolvedValue({ data: { works: [work], runs: [] } }), post: vi.fn() }
    vi.stubGlobal('axios', api)
})
afterEach(() => { wrapper?.unmount(); vi.unstubAllGlobals() })
async function render(runs = []) {
    api.get.mockResolvedValueOnce({ data: { works: [work], runs } })
    wrapper = mount(TeachingSimulation, { global: { stubs: { 'v-icon': true } } })
    await flushPromises()
    return wrapper
}
function button(text) { return wrapper.findAll('button').find(item => item.text() === text) }

it('uses a saved work and its date, then shows the actual roster and an intentionally missing attachment', async () => {
    await render()
    expect(api.post).not.toHaveBeenCalled()
    await wrapper.get('[aria-label="Leistungsfeststellung auswählen"]').setValue('86')
    api.post.mockResolvedValueOnce({ data: { data: sampleRun() } })
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(api.post).toHaveBeenCalledWith(expect.stringContaining('/works/86/runs'), { profile: 'mixed', date: '2026-10-16' }, { timeout: 180000 })
    expect(wrapper.text()).toContain('Beispiel, Anna')
    expect(wrapper.text()).toContain('Datei erzeugt, absichtlich nicht angehängt.')
    expect(wrapper.get('a[href*="/file?"]').attributes('href')).toContain('path=run%2Ffile.pdf')
    expect(button('Testabgaben einsammeln').element.disabled).toBe(true)
})

it('sends and collects only the selected run and rejects an unrelated response', async () => {
    const run = sampleRun()
    await render([run])
    const sent = structuredClone(run)
    sent.rows[0].versions[0].mailpit_id = 'mailpit-id'
    api.post.mockResolvedValueOnce({ data: { data: sent } })
    await button('Rückgaben über Mailpit senden').trigger('click')
    await flushPromises()
    expect(api.post.mock.calls[0][0]).toContain('/runs/test-run/send')
    expect(wrapper.text()).toContain('In Mailpit bestätigt')
    expect(button('Testabgaben einsammeln').element.disabled).toBe(false)
    api.post.mockResolvedValueOnce({ data: { data: { ...sent, id: 'other-run' } } })
    await button('Testabgaben einsammeln').trigger('click')
    await flushPromises()
    expect(api.post.mock.calls[1][0]).toContain('/runs/test-run/collect')
    expect(wrapper.get('[role="alert"]').text()).toContain('anderen Durchlauf')
    expect(wrapper.text()).not.toContain('Nachrichten und ihre Anhänge sind gesichert')
})

it('preserves earlier versions while preparing a new unsent version', async () => {
    const run = sampleRun()
    run.rows[0].versions[0].collected_at = '2026-10-24T14:05:00Z'
    run.rows[0].versions[0].mailpit_id = 'first-mailpit-id'
    await render([run])
    expect(button('Testabgaben einsammeln').element.disabled).toBe(false)
    const revised = structuredClone(run)
    revised.rows[0].versions.push({ ...run.rows[0].versions[0], number: 2, scenario: 'good', collected_at: null, mailpit_id: null })
    api.post.mockResolvedValueOnce({ data: { data: revised } })
    await button('Neue Fassung erzeugen').trigger('click')
    await flushPromises()
    expect(api.post).toHaveBeenCalledWith(expect.stringContaining('/runs/test-run/revise'), { participant_id: 'user:492' })
    expect(wrapper.text()).toContain('2 Fassungen erhalten')
    expect(wrapper.text()).toContain('Noch nicht gesendet')
    expect(button('Testabgaben einsammeln').element.disabled).toBe(true)
})

it('keeps collection disabled until every intended return is confirmed and excludes planned non-submissions', async () => {
    const run = sampleRun()
    run.rows[0].versions[0].mailpit_id = 'first-mailpit-id'
    run.rows.push({ ...structuredClone(run.rows[0]), participant_id: 'user:493',
        versions: [{ ...run.rows[0].versions[0], mailpit_id: null }] })
    run.rows.push({ ...structuredClone(run.rows[0]), participant_id: 'user:494',
        versions: [{ ...run.rows[0].versions[0], scenario: 'no_submission', mailpit_id: null }] })
    await render([run])
    expect(button('Testabgaben einsammeln').element.disabled).toBe(true)
    expect(wrapper.text()).toContain('Zuerst alle vorgesehenen Rückgaben über Mailpit senden.')
    await button('Testabgaben einsammeln').trigger('click')
    expect(api.post).not.toHaveBeenCalled()
    const confirmed = structuredClone(run)
    confirmed.rows[1].versions[0].mailpit_id = 'second-mailpit-id'
    let confirmSending
    api.post.mockImplementationOnce(() => new Promise(resolve => { confirmSending = resolve }))
    await button('Rückgaben über Mailpit senden').trigger('click')
    expect(button('Testabgaben einsammeln').element.disabled).toBe(true)
    confirmSending({ data: { data: confirmed } })
    await flushPromises()
    expect(button('Testabgaben einsammeln').element.disabled).toBe(false)
})

it('keeps collection disabled after failed sending', async () => {
    await render([sampleRun()])
    api.post.mockRejectedValueOnce({ response: { data: { message: 'Mailpit hat den Versand abgelehnt.' } } })
    await button('Rückgaben über Mailpit senden').trigger('click')
    await flushPromises()
    expect(button('Testabgaben einsammeln').element.disabled).toBe(true)
    expect(wrapper.get('[role="alert"]').text()).toContain('Versand abgelehnt')
})

it('shows server refusal without presenting a successful test run', async () => {
    await render()
    await wrapper.get('[aria-label="Leistungsfeststellung auswählen"]').setValue('86')
    api.post.mockRejectedValueOnce({ response: { data: { message: 'Mailpit ist gesperrt.' } } })
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toBe('Mailpit ist gesperrt.')
    expect(wrapper.find('table').exists()).toBe(false)
})
