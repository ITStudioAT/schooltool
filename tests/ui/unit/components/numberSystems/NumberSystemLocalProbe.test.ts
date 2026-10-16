import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import axios from 'axios'
import LocalProbe from '@/pages/admin/teachingTools/NumberSystemLocalProbe.vue'
vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }))
vi.mock('@/pages/components/numberSystems/NumberSystemPlayer.vue', () => ({ default: { props: ['runId', 'probeId', 'participant'], template: '<div data-test="probe-player">{{ probeId }} · {{ participant }} · {{ runId }}</div>' } }))
const wrappers = []
const id = '00000000-0000-4000-8000-000000000001'
beforeEach(() => { vi.useFakeTimers(); vi.resetAllMocks() })
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.useRealTimers() })
async function render(query = '') {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/admin/teaching-tools/games/number-systems/local/:probe', component: LocalProbe }, { path: '/admin/teaching-tools/games/number-systems', component: { template: '<div />' } }] })
    await router.push(`/admin/teaching-tools/games/number-systems/local/${id}${query}`); await router.isReady()
    const wrapper = mount(LocalProbe, { global: { plugins: [router] } }); wrappers.push(wrapper); await flushPromises()
    return { wrapper, router }
}
it('joins with the identity from its own URL and clears old team codes when another invitation is opened', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { label: 'Testperson B', short: true, session: { mode: 'teams', status: 'waiting' }, run: null } })
    vi.mocked(axios.post).mockResolvedValue({ data: { run: { id: 12 } } })
    const { wrapper, router } = await render('?participant=2&code=P-11111111&team_code=P1234567')
    expect(wrapper.get('input[maxlength="12"]').element.value).toBe('P1234567')
    await router.push(`/admin/teaching-tools/games/number-systems/local/${id}?participant=2&code=P-22222222`); await flushPromises()
    expect(wrapper.find('input[maxlength="12"]').exists()).toBe(false)
    await wrapper.get('input[value="existing"]').setValue(true)
    expect(wrapper.get('input[maxlength="12"]').element.value).toBe('')
    await wrapper.get('input[maxlength="12"]').setValue('P7654321')
    await wrapper.get('form').trigger('submit'); await flushPromises()
    expect(vi.mocked(axios.post).mock.lastCall).toEqual([`/api/admin/number-systems/local-probes/${id}/players/2/join`, { code: 'P-22222222', team_name: null, team_code: 'P7654321' }])
    expect(wrapper.get('[data-test="probe-player"]').text()).toBe(`${id} · 2 · 12`)
})
it('teacher controls do not let an older polling response undo a pause', async () => {
    const response = status => ({ data: { short: true, session: { status, join_code: 'P-11111111' }, players: [], runs: [] } })
    let resolvePoll
    vi.mocked(axios.get).mockResolvedValueOnce(response('running')).mockImplementationOnce(() => new Promise(resolve => { resolvePoll = resolve }))
    vi.mocked(axios.post).mockResolvedValue(response('paused'))
    const { wrapper } = await render()
    await vi.advanceTimersByTimeAsync(3000)
    await wrapper.findAll('button').find(button => button.text() === 'Proberunde pausieren').trigger('click'); await flushPromises()
    resolvePoll(response('running')); await flushPromises()
    expect(wrapper.text()).toContain('Pausiert')
    expect(wrapper.findAll('button').some(button => button.text() === 'Proberunde fortsetzen')).toBe(true)
})
