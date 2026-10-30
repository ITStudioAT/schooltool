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
        show: endpoint(), activity: endpoint('/activity'), start: endpoint('/start'), ready: endpoint('/ready'), save: endpoint('/answers'), submit: endpoint('/submit'),
    }
})

const wrappers = []
const fullscreenRestores = []

function mockFullscreen({ supported = true, reject = false } = {}) {
    let element = null
    const oldElement = Object.getOwnPropertyDescriptor(document, 'fullscreenElement')
    const oldRequest = Object.getOwnPropertyDescriptor(document.documentElement, 'requestFullscreen')
    Object.defineProperty(document, 'fullscreenElement', { configurable: true, get: () => element })
    const request = vi.fn(async () => {
        if (reject) throw new Error('denied')
        element = document.documentElement
        document.dispatchEvent(new Event('fullscreenchange'))
    })
    Object.defineProperty(document.documentElement, 'requestFullscreen', { configurable: true, value: supported ? request : undefined })
    fullscreenRestores.push(() => {
        if (oldElement) Object.defineProperty(document, 'fullscreenElement', oldElement)
        else delete document.fullscreenElement
        if (oldRequest) Object.defineProperty(document.documentElement, 'requestFullscreen', oldRequest)
        else delete document.documentElement.requestFullscreen
    })
    return { request, exit: () => { element = null; document.dispatchEvent(new Event('fullscreenchange')) } }
}

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
    fullscreenRestores.splice(0).forEach(restore => restore())
    vi.useRealTimers()
    vi.unstubAllGlobals()
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

it('discards unsaved selections when the teacher resets an open attempt', async () => {
    vi.mocked(axios.put).mockRejectedValue(new Error('offline'))
    const session = mountSession()
    await flushPromises()
    session.selectAnswer(session.attempt.value.questions[0], 'a', true)
    await vi.advanceTimersByTimeAsync(350)
    expect(session.hasPending.value).toBe(true)
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({ attempt_version: 1, start_mode: 'individual',
        started_at: null, starts_at: null, ends_at: null, questions: [], answers: {}, status: 'ready' }) } })
    await session.refresh()
    expect(session.hasPending.value).toBe(false)
    expect(session.answers.value).toEqual({})
    expect(window.localStorage.getItem('schooltool:multiple-choice:pending:7')).toBeNull()
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


it('uses a changed common deadline on refresh while preserving saved student answers', async () => {
    const session = mountSession()
    await flushPromises()
    const initialRemaining = session.remainingSeconds.value
    const originalAnswers = { ...session.answers.value }
    vi.mocked(axios.get).mockResolvedValueOnce({ data: { attempt: fixture({ ends_at: '2026-10-16T08:15:00Z' }) } })
    await session.refresh()
    expect(session.remainingSeconds.value).toBe(initialRemaining + 300)
    expect(session.answers.value).toEqual(originalAnswers)
    vi.mocked(axios.get).mockResolvedValueOnce({ data: { attempt: fixture({ status: 'timeout', completed_at: '2026-10-16T08:01:00Z', ends_at: '2026-10-16T08:01:00Z', questions: [], result: { score: 0, percentage: 0 } }) } })
    await session.refresh()
    expect(session.active.value).toBe(false)
    expect(session.finished.value).toBe(true)
})


it('shows a read-only teacher preview without restoring or modifying a student draft', async () => {
    const preview = fixture({ teacher_preview: true, student_name: 'Test Person' })
    window.localStorage.setItem('schooltool:multiple-choice:pending:7', '{"q1":["b"]}')
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: preview } })
    const session = mountSession()
    await flushPromises()
    expect(session.active.value).toBe(false)
    expect(session.hasPending.value).toBe(false)
    await session.markReady()
    session.selectAnswer(preview.questions[0], 'a', true)
    await session.flush()
    await session.submitAttempt()
    expect(axios.post).not.toHaveBeenCalled()
    expect(axios.put).not.toHaveBeenCalled()
    expect(window.localStorage.getItem('schooltool:multiple-choice:pending:7')).toBe('{"q1":["b"]}')
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    expect(wrapper.find('[aria-label="Schreibgeschützte Lehrkraft-Vorschau"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Lehrkraft-Vorschau für Test Person')
    expect(wrapper.text()).toContain('Eine richtige Antwort?')
    expect(wrapper.text()).toContain('Mehrere richtige Antworten?')
    expect(wrapper.findAll('input')).toHaveLength(0)
    expect(wrapper.text()).not.toContain('Abgabe prüfen')
})

it('explains an account mismatch instead of calling a valid personal link invalid', async () => {
    const message = 'Dieser Link gehört zu einem anderen Schülerkonto. Bitte melde das andere Konto zuerst ab.'
    vi.mocked(axios.get).mockRejectedValueOnce({ response: { status: 403, data: { message } } })
    const session = mountSession()
    await flushPromises()
    expect(session.error.value).toBe(message)
})


it('removes revoked solutions and then results from an already open student result view on refresh', async () => {
    const completed = fixture({ status: 'submitted', result: { score: 5, percentage: 50, correct_count: 1, solutions_released: true, question_results: [{ question_id: 'q1', correct: false, selected_option_ids: ['b'], correct_option_ids: ['a'] }] } })
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: completed } })
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    expect(wrapper.text()).toContain('Deine Frageauswertung')
    expect(wrapper.text()).toContain('Richtige Antwort')
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: { ...completed, result: { score: 5, percentage: 50, correct_count: 1, solutions_released: false, question_results: [{ question_id: 'q1', correct: false, selected_option_ids: ['b'] }] } } } })
    await vi.advanceTimersByTimeAsync(5000)
    await flushPromises()
    expect(wrapper.text()).toContain('5 / 10 Punkte')
    expect(wrapper.text()).toContain('Deine Frageauswertung')
    expect(wrapper.text()).toContain('Musterlösungen sind noch nicht freigegeben')
    expect(wrapper.text()).not.toContain('Richtige Antwort')
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: { ...completed, result: null } } })
    await vi.advanceTimersByTimeAsync(5000)
    await flushPromises()
    expect(wrapper.text()).not.toContain('5 / 10 Punkte')
    expect(wrapper.text()).toContain('gibt Ergebnisse und Lösungen gesondert frei')
    expect(axios.post).not.toHaveBeenCalled()
    expect(axios.put).not.toHaveBeenCalled()
})


it('shows personal questions and marked answers with results while keeping the solution key hidden', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({
        status: 'submitted', result: { score: 10, percentage: 100, correct_count: 2, solutions_released: false,
            question_results: [{ question_id: 'q1', correct: true, points: 5, selected_option_ids: ['a'] }, { question_id: 'q2', correct: true, points: 5, selected_option_ids: ['c', 'd'] }] },
    }) } })
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    expect(wrapper.text()).toContain('Deine Frageauswertung')
    expect(wrapper.text()).toContain('Eine richtige Antwort?')
    expect(wrapper.text()).toContain('Einfach A')
    expect(wrapper.text()).toContain('Ausgewählt')
    expect(wrapper.text()).toContain('Musterlösungen sind noch nicht freigegeben')
    expect(wrapper.text()).not.toContain('Richtige Antwort')
    expect(wrapper.text()).not.toContain('Nicht richtig')
    expect(wrapper.find('input').exists()).toBe(false)
    expect(axios.post).not.toHaveBeenCalled()
    expect(axios.put).not.toHaveBeenCalled()
})


it('resumes a reopened completed attempt with saved answers and its own additional deadline', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({ status: 'timeout', completed_at: '2026-10-16T08:00:59Z', ends_at: '2026-10-16T08:00:59Z', answers: { q1: ['a'] }, result: { score: 5, percentage: 50, correct_count: 1 } }) } })
    const session = mountSession()
    await flushPromises()
    expect(session.finished.value).toBe(true)
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({ ends_at: '2026-10-16T08:03:00Z', answers: { q1: ['a'] } }) } })
    await session.refresh()
    expect(session.finished.value).toBe(false)
    expect(session.active.value).toBe(true)
    expect(session.remainingSeconds.value).toBe(120)
    expect(session.answers.value).toEqual({ q1: ['a'] })
    expect(axios.put).not.toHaveBeenCalled()
    expect(axios.post).not.toHaveBeenCalled()
})


it('starts an individual attempt only after confirmation and does not restart it on refresh', async () => {
    const waiting = fixture({ start_mode: 'individual', questions: [], starts_at: null, started_at: null, status: 'not_started', closes_at: '2026-10-16T09:00:00Z', ends_at: '2026-10-16T09:00:00Z' })
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: waiting } })
    vi.mocked(axios.post).mockResolvedValue({ data: { attempt: fixture({ start_mode: 'individual', closes_at: waiting.closes_at }) } })
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    expect(axios.post).not.toHaveBeenCalled()
    await wrapper.findAll('button').find(button => button.text() === 'Test starten').trigger('click')
    await flushPromises()
    expect(wrapper.get('#mc-start-title').text()).toBe('Test jetzt starten?')
    expect(wrapper.get('dialog[aria-labelledby="mc-start-title"]').text()).toContain('10 Minuten')
    expect(axios.post).not.toHaveBeenCalled()
    await wrapper.get('dialog[aria-labelledby="mc-start-title"]').findAll('button').find(button => button.text() === 'Abbrechen').trigger('click')
    expect(axios.post).not.toHaveBeenCalled()
    await wrapper.findAll('button').find(button => button.text() === 'Test starten').trigger('click')
    await flushPromises()
    await wrapper.get('dialog[aria-labelledby="mc-start-title"]').find('form').trigger('submit')
    await flushPromises()
    expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/multiple-choice/attempts/7/start', {}, expect.objectContaining({ headers: { 'X-MC-Access': 'personal-secret' } }))
    expect(wrapper.find('input').exists()).toBe(true)
    expect(wrapper.get('dialog[aria-labelledby="mc-start-title"]').element.open).toBe(false)
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({ start_mode: 'individual', closes_at: waiting.closes_at }) } })
    window.dispatchEvent(new Event('online'))
    await flushPromises()
    expect(axios.post).toHaveBeenCalledTimes(1)
})

it('also requires confirmation when opening an unstarted test from the student account', async () => {
    route.path = '/student/multiple-choice'
    route.fullPath = '/student/multiple-choice'
    route.hash = ''
    const waiting = fixture({ start_mode: 'individual', questions: [], starts_at: null, started_at: null,
        status: 'not_started', closes_at: '2026-10-16T09:00:00Z', ends_at: '2026-10-16T09:00:00Z' })
    vi.mocked(axios.get).mockImplementation(async url => ({ data: url === '/api/multiple-choice/attempts'
        ? { attempts: [waiting] } : { attempt: waiting } }))
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    await wrapper.findAll('button').find(button => button.text() === 'Test öffnen').trigger('click')
    await flushPromises()
    await wrapper.findAll('button').find(button => button.text() === 'Test starten').trigger('click')
    await flushPromises()
    expect(axios.post).not.toHaveBeenCalled()
    vi.mocked(axios.post).mockResolvedValue({ data: { attempt: fixture({ start_mode: 'individual' }) } })
    await wrapper.get('dialog[aria-labelledby="mc-start-title"]').find('form').trigger('submit')
    await flushPromises()
    expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/multiple-choice/attempts/7/start', {}, expect.objectContaining({ headers: {} }))
})

it.each([
    { paused_at: '2026-10-16T08:01:00Z' },
    { status: 'timeout' },
    { id: 8 },
    { start_mode: 'common' },
])('closes a pending start confirmation when the attempt is no longer startable: %o', async overrides => {
    const waiting = fixture({ start_mode: 'individual', questions: [], starts_at: null, started_at: null,
        status: 'not_started', closes_at: '2026-10-16T09:00:00Z', ends_at: '2026-10-16T09:00:00Z' })
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: waiting } })
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    await wrapper.findAll('button').find(button => button.text() === 'Test starten').trigger('click')
    await flushPromises()
    expect(wrapper.get('dialog[aria-labelledby="mc-start-title"]').element.open).toBe(true)
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: { ...waiting, ...overrides } } })
    window.dispatchEvent(new Event('online'))
    await flushPromises()
    expect(wrapper.get('dialog[aria-labelledby="mc-start-title"]').element.open).toBe(false)
    expect(axios.post).not.toHaveBeenCalled()
})

it('closes a pending start confirmation at the deadline', async () => {
    const waiting = fixture({ start_mode: 'individual', questions: [], starts_at: null, started_at: null,
        status: 'not_started', closes_at: '2026-10-16T08:01:02Z', ends_at: '2026-10-16T08:01:02Z' })
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: waiting } })
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    await wrapper.findAll('button').find(button => button.text() === 'Test starten').trigger('click')
    await flushPromises()
    await vi.advanceTimersByTimeAsync(2500)
    expect(wrapper.get('dialog[aria-labelledby="mc-start-title"]').element.open).toBe(false)
    expect(axios.post).not.toHaveBeenCalled()
})

it.each([{ teacher_preview: true }, { paused_at: '2026-10-16T08:01:00Z' }, { status: 'timeout' }, { start_mode: 'common' }])('does not automatically start a protected or shared attempt: %o', async overrides => {
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({ start_mode: 'individual', status: 'not_started', started_at: null, starts_at: null, questions: [], ...overrides }) } })
    wrappers.push(mount(MultipleChoice))
    await flushPromises()
    expect(axios.post).not.toHaveBeenCalled()
})

it('does not retry a failed start automatically and allows another explicit confirmation', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({ start_mode: 'individual', status: 'not_started', started_at: null, starts_at: null, questions: [] }) } })
    vi.mocked(axios.post).mockRejectedValue(new Error('Offline'))
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    await wrapper.findAll('button').find(button => button.text() === 'Test starten').trigger('click')
    await flushPromises()
    await wrapper.get('dialog[aria-labelledby="mc-start-title"]').find('form').trigger('submit')
    await flushPromises()
    window.dispatchEvent(new Event('online'))
    await vi.advanceTimersByTimeAsync(4000)
    await flushPromises()
    expect(axios.post).toHaveBeenCalledTimes(1)
    expect(wrapper.get('dialog[aria-labelledby="mc-start-title"]').text()).toContain('Start erneut versuchen')
    vi.mocked(axios.post).mockResolvedValueOnce({ data: { attempt: fixture({ start_mode: 'individual' }) } })
    await wrapper.get('dialog[aria-labelledby="mc-start-title"]').find('form').trigger('submit')
    await flushPromises()
    expect(axios.post).toHaveBeenCalledTimes(2)
    expect(wrapper.find('fieldset').exists()).toBe(true)
})

it('freezes the current question, selected answers and remaining clock during a teacher pause and resumes in place', async () => {
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    await wrapper.findAll('button').find(button => button.text() === 'Weiter').trigger('click')
    await flushPromises()
    const paused = fixture({ paused_at: '2026-10-16T08:01:00Z', answers: { q1: ['a'], q2: ['c'] } })
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: paused } })
    window.dispatchEvent(new Event('online'))
    await flushPromises()
    expect(wrapper.text()).toContain('Der Test ist pausiert.')
    expect(wrapper.find('legend').text()).toContain('Frage 2')
    expect(wrapper.find('input[value="c"]').element.checked).toBe(true)
    expect(wrapper.find('fieldset').element.disabled).toBe(true)
    expect(wrapper.find('[role="timer"]').text()).toContain('9:00')
    await vi.advanceTimersByTimeAsync(30000)
    expect(wrapper.find('[role="timer"]').text()).toContain('9:00')
    expect(axios.put).not.toHaveBeenCalled()
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({ ends_at: '2026-10-16T08:10:30Z', server_now: '2026-10-16T08:01:30Z', answers: paused.answers }) } })
    window.dispatchEvent(new Event('online'))
    await flushPromises()
    expect(wrapper.text()).not.toContain('Der Test ist pausiert.')
    expect(wrapper.find('legend').text()).toContain('Frage 2')
    expect(wrapper.find('input[value="c"]').element.checked).toBe(true)
    expect(wrapper.find('fieldset').element.disabled).toBe(false)
    expect(wrapper.find('[role="timer"]').text()).toContain('9:00')
})

it('records focus changes in exam mode without submitting or changing the running test and enters fullscreen only on click', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }))
    const focused = vi.spyOn(document, 'hasFocus').mockReturnValue(true)
    const { request: requestFullscreen } = mockFullscreen()
    try {
        vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({ exam_mode: true }) } })
        const wrapper = mount(MultipleChoice)
        wrappers.push(wrapper)
        await flushPromises()
        expect(wrapper.text()).toContain('Prüfungsmodus')
        expect(requestFullscreen).not.toHaveBeenCalled()
        expect(wrapper.find('input').exists()).toBe(false)
        await wrapper.findAll('button').find(button => button.text() === 'Vollbild starten').trigger('click')
        await flushPromises()
        expect(requestFullscreen).toHaveBeenCalledOnce()
        const end = wrapper.find('[role="timer"]').text()
        focused.mockReturnValue(false)
        window.dispatchEvent(new Event('blur'))
        await flushPromises()
        const record = vi.mocked(axios.post).mock.calls.find((call) => String(call[0]).endsWith('/activity') && call[1].events.some(event => !event.focused))
        expect(record).toBeDefined()
        expect(vi.mocked(axios.post).mock.calls.some(call => String(call[0]).endsWith('/submit'))).toBe(false)
        expect(wrapper.find('[role="timer"]').text()).toBe(end)
        expect(wrapper.find('input').element.disabled).toBe(false)

    } finally {
        focused.mockRestore()

    }
})


it('recognizes a returned visible tab immediately even while document focus is still false and preserves that state on heartbeat', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }))
    const focused = vi.spyOn(document, 'hasFocus').mockReturnValue(true)
    const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    try {
        vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({ exam_mode: true }) } })
        const wrapper = mount(MultipleChoice)
        wrappers.push(wrapper)
        await flushPromises()
        focused.mockReturnValue(false)
        visibility.mockReturnValue('hidden')
        window.dispatchEvent(new Event('blur'))
        document.dispatchEvent(new Event('visibilitychange'))
        await flushPromises()
        visibility.mockReturnValue('visible')
        document.dispatchEvent(new Event('visibilitychange'))
        await flushPromises()
        const latest = () => vi.mocked(axios.post).mock.calls.filter(call => String(call[0]).endsWith('/activity')).at(-1)[1].events.at(-1)
        expect(latest()).toMatchObject({ visible: true, focused: true })
        expect(document.hasFocus()).toBe(false)
        await vi.advanceTimersByTimeAsync(10000)
        expect(latest()).toMatchObject({ visible: true, focused: true })
        window.dispatchEvent(new Event('blur'))
        await flushPromises()
        expect(latest()).toMatchObject({ visible: true, focused: false })
        window.dispatchEvent(new Event('focus'))
        await flushPromises()
        expect(latest()).toMatchObject({ visible: true, focused: true })
        expect(vi.mocked(axios.post).mock.calls.some(call => String(call[0]).endsWith('/submit'))).toBe(false)
    } finally {
        focused.mockRestore()
        visibility.mockRestore()
    }
})


it('requires actual fullscreen before rendering questions and restores the same question and answers after exiting and reentering', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }))
    const fullscreen = mockFullscreen()
    const value = fixture({ exam_mode: true, answers: { q1: ['a'], q2: ['c'] } })
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: value } })
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    expect(wrapper.text()).toContain('Vollbild ist für diesen Test erforderlich.')
    expect(wrapper.text()).not.toContain('Eine richtige Antwort?')
    expect(wrapper.find('fieldset').exists()).toBe(false)
    expect(wrapper.find('input').exists()).toBe(false)
    expect(fullscreen.request).not.toHaveBeenCalled()
    await wrapper.findAll('button').find(button => button.text() === 'Vollbild starten').trigger('click')
    await flushPromises()
    expect(wrapper.find('input[value="a"]').element.checked).toBe(true)
    await wrapper.findAll('button').find(button => button.text() === 'Weiter').trigger('click')
    await flushPromises()
    expect(wrapper.find('legend').text()).toContain('Frage 2')
    fullscreen.exit()
    await flushPromises()
    expect(wrapper.find('fieldset').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Mehrere richtige Antworten?')
    await vi.advanceTimersByTimeAsync(5000)
    expect(vi.mocked(axios.post).mock.calls.some(call => String(call[0]).endsWith('/submit'))).toBe(false)
    expect(axios.put).not.toHaveBeenCalled()
    await wrapper.findAll('button').find(button => button.text() === 'Vollbild starten').trigger('click')
    await flushPromises()
    expect(wrapper.find('legend').text()).toContain('Frage 2')
    expect(wrapper.find('input[value="c"]').element.checked).toBe(true)
    expect(wrapper.find('[role="timer"]').text()).toContain('8:55')
})

it.each([{ supported: false, reject: false }, { supported: true, reject: true }])('does not expose questions when fullscreen is unavailable or rejected: %o', async (settings) => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }))
    mockFullscreen(settings)
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({ exam_mode: true }) } })
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    const button = wrapper.findAll('button').find(button => button.text() === 'Vollbild starten')
    if (settings.supported) {
        await button.trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Vollbild konnte nicht gestartet werden.')
    } else {
        expect(button).toBeUndefined()
        expect(wrapper.text()).toContain('Dieser Browser unterstützt den benötigten Vollbildmodus nicht.')
    }
    expect(wrapper.find('fieldset').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Eine richtige Antwort?')
})

it('requires fullscreen and a separate confirmation before starting an individual exam', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }))
    const fullscreen = mockFullscreen()
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({ exam_mode: true, start_mode: 'individual', status: 'not_started', started_at: null, starts_at: null, questions: [] }) } })
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    expect(wrapper.findAll('button').some(button => button.text() === 'Test jetzt starten')).toBe(false)
    expect(axios.post).not.toHaveBeenCalled()
    vi.mocked(axios.post).mockResolvedValue({ data: { attempt: fixture({ exam_mode: true, start_mode: 'individual' }) } })
    await wrapper.findAll('button').find(button => button.text() === 'Vollbild starten').trigger('click')
    await flushPromises()
    expect(axios.post).not.toHaveBeenCalled()
    await wrapper.findAll('button').find(button => button.text() === 'Test starten').trigger('click')
    await flushPromises()
    fullscreen.exit()
    await flushPromises()
    expect(wrapper.get('dialog[aria-labelledby="mc-start-title"]').element.open).toBe(false)
    expect(axios.post).not.toHaveBeenCalled()
    await wrapper.findAll('button').find(button => button.text() === 'Vollbild starten').trigger('click')
    await flushPromises()
    await wrapper.findAll('button').find(button => button.text() === 'Test starten').trigger('click')
    await flushPromises()
    await wrapper.get('dialog[aria-labelledby="mc-start-title"]').find('form').trigger('submit')
    await flushPromises()
    expect(axios.post).toHaveBeenCalledWith('/api/multiple-choice/attempts/7/start', {}, expect.any(Object))
    expect(wrapper.find('input').exists()).toBe(true)
    fullscreen.exit()
    await flushPromises()
    expect(wrapper.findAll('button').some(button => button.text() === 'Test jetzt starten')).toBe(false)
    expect(wrapper.text()).toContain('Vollbild ist für diesen Test erforderlich.')
})


it('waits for Retry-After after a throttled reconnect instead of retrying on every poll or click', async () => {
    vi.mocked(axios.get).mockRejectedValueOnce({ response: { status: 429, headers: { 'retry-after': '6' }, data: { message: 'Too Many Attempts.' } } })
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    expect(wrapper.text()).toContain('Wir verbinden dich nach der Wartezeit automatisch erneut.')
    expect(wrapper.text()).not.toContain('Too Many Attempts')
    expect(wrapper.findAll('button').find(button => button.text().includes('Automatisch erneut verbinden')).element.disabled).toBe(true)
    window.dispatchEvent(new Event('online'))
    window.dispatchEvent(new Event('online'))
    await flushPromises()
    await vi.advanceTimersByTimeAsync(5000)
    expect(axios.get).toHaveBeenCalledOnce()
    await vi.advanceTimersByTimeAsync(1250)
    expect(axios.get).toHaveBeenCalledTimes(2)
    expect(wrapper.find('fieldset').exists()).toBe(true)
})

it('buffers exam events during server throttling and retries them only after the requested delay', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }))
    vi.mocked(axios.get).mockResolvedValue({ data: { attempt: fixture({ exam_mode: true }) } })
    vi.mocked(axios.post).mockRejectedValueOnce({ response: { status: 429, headers: { 'retry-after': '25' } } })
    const wrapper = mount(MultipleChoice)
    wrappers.push(wrapper)
    await flushPromises()
    expect(axios.post).toHaveBeenCalledOnce()
    window.dispatchEvent(new Event('blur'))
    window.dispatchEvent(new Event('focus'))
    await flushPromises()
    await vi.advanceTimersByTimeAsync(24000)
    expect(axios.post).toHaveBeenCalledOnce()
    await vi.advanceTimersByTimeAsync(6500)
    expect(axios.post).toHaveBeenCalledTimes(2)
    expect(vi.mocked(axios.post).mock.calls[1][1].events.length).toBeGreaterThan(2)
    expect(vi.mocked(axios.post).mock.calls.some(call => String(call[0]).endsWith('/submit'))).toBe(false)
})


it('hides questions after a link is revoked, preserves unsaved answers and reconnects only on demand', async () => {
    const session = mountSession()
    await flushPromises()
    session.selectAnswer(session.attempt.value.questions[0], 'a', true)
    vi.mocked(axios.get).mockRejectedValueOnce({ response: { status: 403, data: { message: 'Der persönliche Zugangslink wurde deaktiviert.' } } })
    await session.refresh()
    expect(session.attempt.value).toBeNull()
    expect(session.answers.value).toEqual({})
    expect(window.localStorage.getItem('schooltool:multiple-choice:pending:7')).toBe('{"q1":["a"]}')
    const calls = vi.mocked(axios.get).mock.calls.length
    await vi.advanceTimersByTimeAsync(12000)
    window.dispatchEvent(new Event('online'))
    await flushPromises()
    expect(axios.get).toHaveBeenCalledTimes(calls)
    await session.refresh(true)
    expect(session.attempt.value.id).toBe(7)
    expect(session.answers.value.q1).toEqual(['a'])
})
