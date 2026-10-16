import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import axios from 'axios'
import MultipleChoice from '@/pages/homepage/multipleChoice/MultipleChoice.vue'
import { useMultipleChoiceSession } from '@/pages/homepage/multipleChoice/useMultipleChoiceSession'

const { route } = vi.hoisted(() => ({ route: {
    path: '/multiple-choice', fullPath: '/multiple-choice#access=personal-secret',
    hash: '#access=personal-secret', params: {},
} }))
vi.mock('vue-router', () => ({ useRoute: () => route, useRouter: () => ({ push: vi.fn() }) }))
vi.mock('axios', () => ({ default: { get: vi.fn(), put: vi.fn(), post: vi.fn() } }))
vi.mock('@/stores/student/StudentStore', () => ({ useStudentStore: () => ({ user: { id: 42 } }) }))
vi.mock('@/pages/homepage/student/components/StudentNavigationDrawer.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@/actions/App/Http/Controllers/MultipleChoiceParticipantController', () => {
    const endpoint = (suffix = '') => ({ url: (id) => `/api/multiple-choice/attempts/${id}${suffix}` })
    return {
        index: { url: () => '/api/multiple-choice/attempts' },
        access: { url: () => '/api/multiple-choice/access' },
        show: endpoint(), ready: endpoint('/ready'), save: endpoint('/answers'), submit: endpoint('/submit'),
    }
})

const wrappers = []

function fixture(overrides = {}) {
    return {
        id: 7, test_id: 3, title: 'IT-Grundlagen', question_count: 2, total_points: 10,
        duration_seconds: 600, lead_seconds: 30, starts_at: '2026-10-16T08:00:00Z',
        ends_at: '2026-10-16T08:10:00Z', server_now: '2026-10-16T08:01:00Z',
        status: 'in_progress', ready_at: '2026-10-16T07:59:00Z', started_at: '2026-10-16T08:00:00Z', answers: {}, result: null,
        questions: [
            { id: 'q1', prompt: 'Eine richtige Antwort?', type: 'single', options: [{ id: 'a', text: 'Einfach A' }, { id: 'b', text: 'Einfach B' }] },
            { id: 'q2', prompt: 'Mehrere richtige Antworten?', type: 'multiple', options: [{ id: 'c', text: 'Mehrfach C' }, { id: 'd', text: 'Mehrfach D' }] },
        ], ...overrides,
    }
}

function mountSession() {
    let session
    const wrapper = mount(defineComponent({
        setup() {
            session = useMultipleChoiceSession(route)
            return () => h('div')
        },
    }))
    wrappers.push(wrapper)
    return session
}

function deferred() {
    let resolve
    const promise = new Promise(complete => { resolve = complete })
    return { promise, resolve }
}

beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-16T08:01:00Z'))
    window.localStorage.clear()
    route.path = '/multiple-choice'
    route.fullPath = '/multiple-choice#access=personal-secret'
    route.hash = '#access=personal-secret'
    route.params = {}
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture() } })
    vi.mocked(axios.put).mockImplementation(async (_url, body) => ({ data: { attempt: fixture({ answers: body.answers }) } }))
    vi.mocked(axios.post).mockResolvedValue({ data: { attempt: fixture({ status: 'submitted', questions: [] }) } })
})

afterEach(() => {
    wrappers.splice(0).forEach(wrapper => wrapper.unmount())
    vi.useRealTimers()
    vi.resetAllMocks()
    window.localStorage.clear()
})

it('shows the waiting room without questions or results before the shared start', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({
        questions: [], starts_at: '2026-10-16T08:01:30Z', ends_at: '2026-10-16T08:11:30Z', status: 'ready',
    }) } })
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()

    expect(wrapper.text()).toContain('Gleich geht es los.')
    expect(wrapper.find('[role="timer"]').text()).toContain('30')
    expect(wrapper.find('input').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Deine Frageauswertung')
    await vi.advanceTimersByTimeAsync(30000)
    expect(wrapper.find('input').exists()).toBe(false)
    expect(axios.post).not.toHaveBeenCalled()
})

it('opens an account assignment through its existing attempt without a personal token', async () => {
    route.path = '/student/multiple-choice'
    route.fullPath = '/student/multiple-choice'
    route.hash = ''
    vi.mocked(axios.get).mockImplementation(async url => ({ data: url === '/api/multiple-choice/attempts'
        ? { attempts: [fixture({ questions: [] })] } : { attempt: fixture({ answers: { q1: ['b'] } }) } }))
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    expect(wrapper.text()).toContain('Deine Tests')
    await wrapper.findAll('button').find(button => button.text() === 'Test öffnen').trigger('click')
    await flushPromises()

    expect(axios.get).toHaveBeenLastCalledWith('/api/multiple-choice/attempts/7', expect.objectContaining({ headers: {} }))
    expect(wrapper.find('input[value="b"]').element.checked).toBe(true)
    expect(axios.post).not.toHaveBeenCalled()
})

it('renders large native single and multiple answer controls and counts answered questions', async () => {
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    expect(wrapper.findAll('input[type="radio"]')).toHaveLength(2)
    expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(0)

    await wrapper.find('input[value="a"]').setValue(true)
    await wrapper.findAll('button').find(button => button.text() === 'Weiter').trigger('click')
    expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(2)
    await wrapper.find('input[value="c"]').setValue(true)
    await wrapper.find('input[value="d"]').setValue(true)
    expect(wrapper.text()).toContain('2 von 2 beantwortet')
    await vi.advanceTimersByTimeAsync(350)
    expect(axios.put).toHaveBeenLastCalledWith('/api/multiple-choice/attempts/7/answers',
        { answers: { q1: ['a'], q2: ['c', 'd'] } }, expect.any(Object))
    expect(wrapper.text()).toContain('Alle Antworten gespeichert.')
})

it('focuses one question at a time and preserves selections with next, back, and overview navigation', async () => {
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    expect(wrapper.findAll('fieldset')).toHaveLength(1)
    expect(wrapper.find('.mc-question-actions').text()).toContain('1 von 2')
    expect(wrapper.findAll('button').find(button => button.text() === 'Zurück').attributes('disabled')).toBeDefined()

    await wrapper.find('input[value="b"]').setValue(true)
    await wrapper.findAll('button').find(button => button.text() === 'Weiter').trigger('click')
    expect(wrapper.findAll('fieldset')).toHaveLength(1)
    expect(wrapper.find('input[value="b"]').exists()).toBe(false)
    expect(wrapper.find('.mc-question-actions').text()).toContain('2 von 2')
    expect(wrapper.findAll('button').some(button => button.text() === 'Abgabe prüfen')).toBe(true)
    await wrapper.find('input[value="c"]').setValue(true)
    await wrapper.findAll('button').find(button => button.text() === 'Zurück').trigger('click')
    expect(wrapper.find('input[value="b"]').element.checked).toBe(true)

    await wrapper.find('.mc-question-nav button[aria-label="Frage 2, beantwortet"]').trigger('click')
    expect(wrapper.find('input[value="c"]').element.checked).toBe(true)
    expect(wrapper.find('.mc-question-nav button[aria-current="step"]').text()).toBe('2')
    expect(axios.post).not.toHaveBeenCalled()
})

it('restores only unsaved selections after reload, retries the same attempt, and never stores its access token', async () => {
    vi.mocked(axios.put).mockRejectedValue(new Error('offline'))
    const initial = mountSession()
    await flushPromises()
    initial.selectAnswer(initial.attempt.value.questions[0], 'a', true)
    await vi.advanceTimersByTimeAsync(350)
    expect(initial.saveError.value).toBe(true)
    expect(window.localStorage.getItem('schooltool:multiple-choice:pending:7')).toBe('{"q1":["a"]}')
    expect(JSON.stringify(window.localStorage)).not.toContain('personal-secret')
    wrappers.pop().unmount()

    vi.mocked(axios.put).mockResolvedValue({ data: { attempt: fixture({ answers: { q1: ['a'] } }) } })
    const restored = mountSession()
    await flushPromises()
    expect(restored.attempt.value.id).toBe(7)
    expect(restored.answers.value.q1).toEqual(['a'])
    expect(restored.hasPending.value).toBe(false)
    expect(window.localStorage.getItem('schooltool:multiple-choice:pending:7')).toBeNull()
    expect(axios.get).toHaveBeenLastCalledWith('/api/multiple-choice/access', expect.objectContaining({ headers: { 'X-MC-Access': 'personal-secret' } }))
})

it('serializes saves and preserves an edit made while the previous answer is being saved', async () => {
    const firstSave = deferred()
    vi.mocked(axios.put).mockImplementationOnce(() => firstSave.promise)
    const session = mountSession()
    await flushPromises()
    const question = session.attempt.value.questions[0]
    session.selectAnswer(question, 'a', true)
    await vi.advanceTimersByTimeAsync(350)
    session.selectAnswer(question, 'b', true)
    await vi.advanceTimersByTimeAsync(350)
    expect(axios.put).toHaveBeenCalledTimes(1)
    firstSave.resolve({ data: { attempt: fixture({ answers: { q1: ['a'] } }) } })
    await flushPromises()
    expect(axios.put).toHaveBeenCalledTimes(2)
    expect(axios.put).toHaveBeenLastCalledWith(expect.any(String), { answers: { q1: ['b'] } }, expect.any(Object))
    expect(session.answers.value.q1).toEqual(['b'])
    expect(session.hasPending.value).toBe(false)
})

it('ignores a poll that began before a newer answer was acknowledged', async () => {
    const session = mountSession()
    await flushPromises()
    const stalePoll = deferred()
    vi.mocked(axios.get).mockImplementationOnce(() => stalePoll.promise)
    const polling = session.refresh()
    session.selectAnswer(session.attempt.value.questions[0], 'b', true)
    await vi.advanceTimersByTimeAsync(350)
    stalePoll.resolve({ data: { attempt: fixture({ answers: { q1: ['a'] } }) } })
    await polling
    expect(session.answers.value.q1).toEqual(['b'])
})

it('uses the server clock offset and refuses edits or submission after the fixed end', async () => {
    vi.setSystemTime(new Date('2026-10-16T10:30:00Z'))
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({ server_now: '2026-10-16T08:09:59Z' }) } })
    const session = mountSession()
    await flushPromises()
    expect(session.remainingSeconds.value).toBe(1)
    const question = session.attempt.value.questions[0]
    await vi.advanceTimersByTimeAsync(1100)
    expect(session.active.value).toBe(false)
    session.selectAnswer(question, 'a', true)
    await session.submitAttempt()
    expect(axios.put).not.toHaveBeenCalled()
    expect(axios.post).not.toHaveBeenCalled()
})

it('does not submit while offline answers remain unsaved and allows retry after reconnection', async () => {
    const session = mountSession()
    await flushPromises()
    session.selectAnswer(session.attempt.value.questions[0], 'a', true)
    vi.mocked(axios.put).mockRejectedValue(new Error('offline'))
    expect(await session.submitAttempt()).toBe(false)
    expect(axios.post).not.toHaveBeenCalled()
    expect(session.error.value).toContain('noch nicht gespeichert')

    vi.mocked(axios.put).mockResolvedValue({ data: { attempt: fixture({ answers: { q1: ['a'] } }) } })
    expect(await session.submitAttempt()).toBe(true)
    expect(axios.post).toHaveBeenCalledTimes(1)
    expect(session.finished.value).toBe(true)
})

it('shows a released zero score as a completed result and does not invent unreleased solutions', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({
        status: 'submitted', questions: [], result: { score: 0, percentage: 0, correct_count: 0 },
    }) } })
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    expect(wrapper.text()).toContain('0 / 10 Punkte')
    expect(wrapper.find('input').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Richtige Antworten:')
    expect(wrapper.text()).not.toContain('Nicht begonnen')
})

it('marks an unopened expired attempt as not started without turning null results into zero points', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({
        status: 'timeout', started_at: null, completed_at: '2026-10-16T08:10:00Z', questions: [],
        result: { score: null, percentage: null, correct_count: null },
    }) } })
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    expect(wrapper.find('#mc-finished-title').text()).toBe('Nicht begonnen')
    expect(wrapper.text()).toContain('Du hast diesen Test nicht begonnen.')
    expect(wrapper.text()).not.toContain('0 / 10 Punkte')
    expect(wrapper.find('.mc-result').exists()).toBe(false)
})

it('keeps a started attempt with a zero timeout score distinct from an unopened attempt', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({
        status: 'timeout', questions: [], result: { score: 0, percentage: 0, correct_count: 0 },
    }) } })
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    expect(wrapper.text()).toContain('0 / 10 Punkte')
    expect(wrapper.find('#mc-finished-title').text()).toBe('Die Bearbeitungszeit ist vorbei.')
    expect(wrapper.text()).not.toContain('Nicht begonnen')
})
