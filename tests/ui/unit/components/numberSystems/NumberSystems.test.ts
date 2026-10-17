import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import axios from 'axios'
import NumberSystemPuzzle from '@/pages/components/numberSystems/NumberSystemPuzzle.vue'
import NumberSystemsGame from '@/pages/admin/teachingTools/NumberSystemsGame.vue'
import { useNumberSystemPlayer } from '@/pages/components/numberSystems/useNumberSystemPlayer'

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }))
const wrappers = []
const state = (changes = {}) => ({ id: 7, revision: 0, position: 0, total: 12, solved: false, completed: false,
    mission: { id: 'mission-a', fields: [] }, draft: {}, session: { status: 'running' }, ...changes })
const reply = run => ({ data: { run: JSON.parse(JSON.stringify(run)) } })
function player(context = {}) {
    let api
    wrappers.push(mount(defineComponent({ setup() { api = useNumberSystemPlayer(ref(7), context); return () => h('div') } })))
    return api
}
beforeEach(() => { vi.useFakeTimers(); vi.resetAllMocks(); localStorage.clear() })
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.useRealTimers() })

describe('Number systems interaction and persistence', () => {
    it('signals connection even in a background tab without changing or overwriting a draft', async () => {
        const hidden = vi.spyOn(document, 'hidden', 'get').mockReturnValue(true)
        vi.mocked(axios.get).mockResolvedValue(reply(state({ revision: 5, draft: { answer: 'saved' } })))
        vi.mocked(axios.post).mockResolvedValue({ data: { recorded: true } })
        const api = player(); await flushPromises()
        await vi.advanceTimersByTimeAsync(15000)
        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/number-systems/runs/7/presence')
        expect(api.run.value.revision).toBe(5)
        expect(api.draft.value.answer).toBe('saved')
        expect(api.error.value).toBe('')
        hidden.mockRestore()
    })
    it('keeps two local participant drafts and request identities separate despite sharing browser storage', async () => {
        vi.mocked(axios.get).mockResolvedValue(reply(state()))
        const probeId = '00000000-0000-4000-8000-000000000001'
        const a = player({ probeId, participant: 1 }), b = player({ probeId, participant: 2 })
        await flushPromises()
        a.edit({ answer: 'draft-a' }); b.edit({ answer: 'draft-b' })
        await a.load(); await b.load()
        expect(a.draft.value.answer).toBe('draft-a')
        expect(b.draft.value.answer).toBe('draft-b')
        expect(vi.mocked(axios.get).mock.calls.map(call => call[0])).toEqual([
            `/api/admin/number-systems/local-probes/${probeId}/players/1`,
            `/api/admin/number-systems/local-probes/${probeId}/players/2`,
            `/api/admin/number-systems/local-probes/${probeId}/players/1`,
            `/api/admin/number-systems/local-probes/${probeId}/players/2`,
        ])
        vi.mocked(axios.post).mockImplementation(async (_, payload) => reply(state({ revision: 1, draft: payload.draft })))
        await a.flush()
        expect(vi.mocked(axios.post).mock.lastCall[0]).toBe(`/api/admin/number-systems/local-probes/${probeId}/players/1/run`)
        expect(b.draft.value.answer).toBe('draft-b')
    })
    it('opens a waiting-room team invitation without a cached mission or a connection error', async () => {
        vi.mocked(axios.get).mockResolvedValue(reply(state({ mission: null, session: { status: 'waiting' } })))
        const api = player(); await flushPromises()
        expect(api.run.value.session.status).toBe('waiting')
        expect(api.error.value).toBe('')
        expect(api.canPlay.value).toBe(false)
    })

    it('makes bit switches and selected weights observable draft values', async () => {
        const mission = { id: 'a', fields: [{ name: 'answer', widget: 'bits', width: 8, label: 'Dual' }, { name: 'weights', widget: 'weights', width: 8, label: 'Stellenwerte' }] }
        const wrapper = mount(NumberSystemPuzzle, { props: { mission, modelValue: { answer: '00000000', weights: [] } } })
        wrappers.push(wrapper)
        await wrapper.get('[aria-label="Stellenwert 128"]').trigger('click')
        expect(wrapper.emitted('update:modelValue')[0][0].answer).toBe('10000000')
        await wrapper.findAll('input[type="checkbox"]')[0].setValue(true)
        expect(wrapper.emitted('update:modelValue')[1][0].weights).toEqual([128])
        await wrapper.setProps({ disabled: true })
        expect(wrapper.get('[aria-label="Stellenwert 128"]').attributes('disabled')).toBeDefined()
    })

    it('stores individual nibble selections without pretending incomplete keys are complete', async () => {
        const wrapper = mount(NumberSystemPuzzle, { props: { mission: { id: 'a', fields: [{ name: 'answer', widget: 'nibbles', width: 2, label: 'Hex' }] }, modelValue: {} } })
        wrappers.push(wrapper)
        await wrapper.get('[aria-label="Hexziffer 1"]').setValue('A')
        expect(wrapper.emitted('update:modelValue')[0][0].answer).toBe('A_')
        await wrapper.setProps({ modelValue: { answer: 'A_' } })
        await wrapper.get('[aria-label="Hexziffer 2"]').setValue('F')
        expect(wrapper.emitted('update:modelValue')[1][0].answer).toBe('AF')
    })

    it('preserves recovered conflicting drafts through repeated reloads until deliberate discard', async () => {
        localStorage.setItem('schooltool-number-system-draft-7', JSON.stringify({ mission: 'mission-a', revision: 0, draft: { answer: 'local' } }))
        vi.mocked(axios.get).mockResolvedValue(reply(state({ revision: 1, draft: { answer: 'remote' } })))
        const api = player(); await flushPromises()
        expect(api.conflict.value).toBe(true)
        expect(api.draft.value.answer).toBe('local')
        expect(JSON.parse(localStorage.getItem('schooltool-number-system-draft-7')).revision).toBe(0)
        await api.load()
        expect(api.draft.value.answer).toBe('local')
        expect(api.conflict.value).toBe(true)
        expect(axios.post).not.toHaveBeenCalled()
        await api.load(true)
        expect(api.draft.value.answer).toBe('remote')
        expect(api.dirty.value).toBe(false)
        expect(localStorage.getItem('schooltool-number-system-draft-7')).toBeNull()
    })

    it('saves the draft before submitting an answer and uses the acknowledged revision', async () => {
        vi.mocked(axios.get).mockResolvedValue(reply(state()))
        vi.mocked(axios.post).mockImplementation(async (_, payload) => reply(state({ revision: payload.revision + 1, draft: payload.draft, solved: payload.action === 'answer' })))
        const api = player(); await flushPromises()
        api.edit({ answer: '1010' })
        await api.act('answer')
        const calls = vi.mocked(axios.post).mock.calls
        expect(calls.map(call => call[1].action)).toEqual(['draft', 'answer'])
        expect(calls[1][1]).toMatchObject({ revision: 1, mission_id: 'mission-a', draft: { answer: '1010' } })
        expect(api.run.value.solved).toBe(true)
        expect(api.dirty.value).toBe(false)
    })

    it('retains edits made during an in-flight save', async () => {
        vi.mocked(axios.get).mockResolvedValue(reply(state()))
        let resolveSave
        vi.mocked(axios.post).mockImplementationOnce(() => new Promise(resolve => { resolveSave = resolve }))
        const api = player(); await flushPromises()
        api.edit({ answer: 'first' })
        const saved = api.flush()
        api.edit({ answer: 'second' })
        resolveSave(reply(state({ revision: 1, draft: { answer: 'first' } })))
        await saved
        expect(api.draft.value.answer).toBe('second')
        expect(api.dirty.value).toBe(true)
        expect(JSON.parse(localStorage.getItem('schooltool-number-system-draft-7')).draft.answer).toBe('second')
    })

    it('stops mutations after a stale revision response and keeps the local draft recoverable', async () => {
        vi.mocked(axios.get).mockResolvedValue(reply(state()))
        vi.mocked(axios.post).mockRejectedValue({ response: { status: 409, data: { message: 'Neuerer Spielstand' } } })
        const api = player(); await flushPromises()
        api.edit({ answer: 'local' })
        expect(await api.act('answer')).toBe(false)
        expect(api.conflict.value).toBe(true)
        expect(await api.act('hint')).toBe(false)
        expect(axios.post).toHaveBeenCalledTimes(1)
        expect(localStorage.getItem('schooltool-number-system-draft-7')).toContain('local')
    })

    it('accepts a teacher pause from polling without discarding a local unsaved draft', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce(reply(state({ clock: { mode: 'elapsed', elapsed_seconds: 10, ticking: true } }))).mockResolvedValue(reply(state({ session: { status: 'paused' }, clock: { mode: 'elapsed', elapsed_seconds: 15, ticking: false } })))
        vi.mocked(axios.post).mockRejectedValue(new Error('offline'))
        const api = player(); await flushPromises()
        api.edit({ answer: 'local' })
        await vi.advanceTimersByTimeAsync(3500)
        expect(api.run.value.session.status).toBe('paused')
        expect(api.run.value.clock).toMatchObject({ elapsed_seconds: 15, ticking: false })
        expect(api.canPlay.value).toBe(false)
        expect(api.draft.value.answer).toBe('local')
    })
})

it('names the round and consequences before deletion and preserves the list when cancelled', async () => {
    let sessions = [{ id: 4, title: 'Eigene Proberunde', status: 'ended', planned_minutes: 30, difficulty: 1, runs_count: 2 }]
    vi.mocked(axios.get).mockImplementation(async () => ({ data: { available: true, courses: [], sessions } }))
    vi.mocked(axios.delete).mockImplementation(async () => { sessions = []; return { data: { deleted: true } } })
    const dialog = defineComponent({ props: ['modelValue'], setup(props, { slots }) { return () => props.modelValue ? h('div', { role: 'dialog' }, slots.default?.()) : null } })
    const wrapper = mount(NumberSystemsGame, { global: { stubs: { 'v-dialog': dialog } } }); wrappers.push(wrapper)
    await flushPromises()
    await wrapper.get('[aria-label="Klassenrunde Eigene Proberunde löschen"]').trigger('click')
    expect(wrapper.get('[role="dialog"]').text()).toContain('„Eigene Proberunde“ löschen?')
    expect(wrapper.get('[role="dialog"]').text()).toContain('2 Spielständen')
    expect(axios.delete).not.toHaveBeenCalled()
    await wrapper.get('[role="dialog"] button[type="button"]').trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Eigene Proberunde')
    await wrapper.get('[aria-label="Klassenrunde Eigene Proberunde löschen"]').trigger('click')
    await wrapper.get('[role="dialog"] form').trigger('submit'); await flushPromises()
    expect(axios.delete).toHaveBeenCalledExactlyOnceWith('/api/admin/number-systems/sessions/4', { data: { confirmation: 'Eigene Proberunde' } })
    expect(wrapper.text()).not.toContain('Eigene Proberunde')
})

it('lets a teacher prepare a separate new round and start pause resume and end it', async () => {
    const session = { id: 2, title: 'Signal Null', planned_minutes: 30, difficulty: 3, mode: 'teams', status: 'waiting', join_code: 'ABC1234567' }
    const monitor = () => ({ session: { ...session }, enrolled: 20, runs: [] })
    vi.mocked(axios.get).mockImplementation(async url => ({ data: String(url).endsWith('/sessions/2') ? monitor() : { available: true, courses: [{ id: 8, title: 'Testkurs' }], sessions: [] } }))
    vi.mocked(axios.post).mockImplementation(async (_, payload) => {
        if (payload.action) { session.status = ({ start: 'running', pause: 'paused', resume: 'running', end: 'ended' })[payload.action]; return { data: monitor() } }
        return { data: { session } }
    })
    const dialog = defineComponent({ props: ['modelValue'], setup(props, { slots }) { return () => props.modelValue ? h('div', { role: 'dialog' }, slots.default?.()) : null } })
    const wrapper = mount(NumberSystemsGame, { global: { stubs: { 'v-dialog': dialog } } }); wrappers.push(wrapper)
    await flushPromises()
    async function click(text) { const button = wrapper.findAll('button').find(button => button.text() === text); expect(button, text).toBeDefined(); await button.trigger('click'); await flushPromises() }
    await click('Neues Abenteuer')
    await wrapper.get('select').setValue('8')
    await wrapper.findAll('select')[1].setValue('30')
    await wrapper.findAll('select')[2].setValue('3')
    await wrapper.findAll('select')[3].setValue('teams')
    await wrapper.get('form').trigger('submit'); await flushPromises()
    expect(vi.mocked(axios.post).mock.calls[0][1]).toMatchObject({ course_id: 8, planned_minutes: 30, difficulty: 3, mode: 'teams' })
    await click('Klassenrunde starten')
    await click('Runde pausieren')
    await click('Runde fortsetzen')
    await click('Runde beenden')
    await wrapper.get('form').trigger('submit'); await flushPromises()
    expect(vi.mocked(axios.post).mock.calls.slice(1).map(call => call[1].action)).toEqual(['start', 'pause', 'resume', 'end'])
    expect(wrapper.text()).toContain('Beendet')
})
