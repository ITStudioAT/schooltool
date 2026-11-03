import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { defineComponent, h } from 'vue'
import axios from 'axios'
import LocalProbe from '@/pages/admin/teachingTools/NumberSystemLocalProbe.vue'
vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }))
vi.mock('@/pages/components/numberSystems/NumberSystemPlayer.vue', () => ({ default: { props: ['runId', 'probeId', 'participant'], template: '<div data-test="probe-player">{{ probeId }} · {{ participant }} · {{ runId }}</div>' } }))
const wrappers = []
const id = '00000000-0000-4000-8000-000000000001'
const response = (changes = {}) => ({ data: { session: { title: 'Mein Zahlentraining', mode: 'solo', status: 'running' }, run: { id: 12 }, ...changes } })
beforeEach(() => { vi.useFakeTimers(); vi.resetAllMocks() })
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.useRealTimers() })
async function render(query = '') {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/admin/teaching-tools/games/number-systems/local/:probe', component: LocalProbe }, { path: '/admin/teaching-tools/games/number-systems', component: { template: '<div />' } }] })
    await router.push(`/admin/teaching-tools/games/number-systems/local/${id}${query}`); await router.isReady()
    const dialog = defineComponent({ props: ['modelValue'], setup(props, { slots }) { return () => props.modelValue ? h('div', { role: 'dialog' }, slots.default?.()) : null } })
    const wrapper = mount(LocalProbe, { global: { plugins: [router], stubs: { 'v-dialog': dialog } } }); wrappers.push(wrapper); await flushPromises()
    return { wrapper, router }
}
it('opens the saved self play directly and lets the student view own its refreshes', async () => {
    vi.mocked(axios.get).mockResolvedValue(response())
    const { wrapper } = await render()
    expect(axios.get).toHaveBeenCalledExactlyOnceWith(`/api/admin/number-systems/local-probes/${id}/players/1`)
    expect(wrapper.get('[data-test="probe-player"]').text()).toBe(`${id} · 1 · 12`)
    expect(wrapper.text()).not.toMatch(/Testpersonen|Kurscode|Teamcode|Fenster für|SQLite|Kennung/)
    expect(axios.post).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(30000)
    expect(axios.get).toHaveBeenCalledTimes(1)
})
it('offers one self play action for an older unjoined probe without identity or code forms', async () => {
    const session = { title: 'Alte Proberunde', mode: 'teams', status: 'waiting', join_code: 'P-11111111' }
    vi.mocked(axios.get).mockResolvedValueOnce(response({ session, run: null })).mockResolvedValue(response())
    vi.mocked(axios.post).mockResolvedValue({ data: {} })
    const { wrapper } = await render('?participant=2&code=IGNORED&team_code=IGNORED')
    expect(wrapper.find('input').exists()).toBe(false)
    await wrapper.findAll('button').find(button => button.text() === 'Jetzt selbst spielen').trigger('click'); await flushPromises()
    expect(vi.mocked(axios.post).mock.calls).toEqual([
        [`/api/admin/number-systems/local-probes/${id}/players/1/join`, { code: 'P-11111111', team_name: 'Mein Probespiel' }],
        [`/api/admin/number-systems/local-probes/${id}/control`, { action: 'start' }],
    ])
    expect(wrapper.get('[data-test="probe-player"]').text()).toBe(`${id} · 1 · 12`)
})
it('resumes an older paused saved round without creating or joining another game', async () => {
    vi.mocked(axios.get).mockResolvedValueOnce(response({ session: { title: 'Pausierte Runde', status: 'paused' } })).mockResolvedValue(response())
    vi.mocked(axios.post).mockResolvedValue({ data: {} })
    const { wrapper } = await render()
    await wrapper.findAll('button').find(button => button.text() === 'Weiterspielen').trigger('click'); await flushPromises()
    expect(axios.post).toHaveBeenCalledExactlyOnceWith(`/api/admin/number-systems/local-probes/${id}/control`, { action: 'resume' })
    expect(wrapper.get('[data-test="probe-player"]').text()).toBe(`${id} · 1 · 12`)
})
it('ignores a late response from a different saved adventure', async () => {
    let resolveOld
    vi.mocked(axios.get).mockImplementationOnce(() => new Promise(resolve => { resolveOld = resolve })).mockResolvedValue(response({ run: { id: 21 } }))
    const { wrapper, router } = await render()
    const next = '00000000-0000-4000-8000-000000000002'
    await router.push(`/admin/teaching-tools/games/number-systems/local/${next}`); await flushPromises()
    resolveOld(response()); await flushPromises()
    expect(wrapper.get('[data-test="probe-player"]').text()).toBe(`${next} · 1 · 21`)
})
it('names the saved adventure and deletes its progress only after confirmation', async () => {
    vi.mocked(axios.get).mockResolvedValue(response())
    vi.mocked(axios.delete).mockResolvedValue({ data: { removed: true } })
    const { wrapper, router } = await render()
    const open = () => wrapper.findAll('button').find(button => button.text() === 'Proberunde löschen').trigger('click')
    await open()
    expect(wrapper.get('[role="dialog"]').text()).toContain('„Mein Zahlentraining“ löschen?')
    expect(wrapper.get('[role="dialog"]').text()).toContain('Fortschritt')
    expect(axios.delete).not.toHaveBeenCalled()
    await wrapper.get('[role="dialog"] button[type="button"]').trigger('click')
    expect(axios.delete).not.toHaveBeenCalled()
    await open()
    await wrapper.get('[role="dialog"] form').trigger('submit'); await flushPromises()
    expect(axios.delete).toHaveBeenCalledExactlyOnceWith(`/api/admin/number-systems/local-probes/${id}`)
    expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/games/number-systems')
})
