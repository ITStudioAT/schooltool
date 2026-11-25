import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import StudentCourseTasks from '@/pages/homepage/student/overview/myCourse/StudentCourseTasks.vue'
import MyCourse from '@/pages/homepage/student/overview/myCourse/MyCourse.vue'

const push = vi.hoisted(() => ({
    configureEcho: vi.fn(),
    private: vi.fn(),
    listen: vi.fn(),
    leaveChannel: vi.fn(),
}))
vi.mock('@laravel/echo-vue', () => ({
    configureEcho: push.configureEcho,
    echoIsConfigured: () => false,
    echo: () => ({ private: push.private, leaveChannel: push.leaveChannel }),
}))

afterEach(() => vi.unstubAllGlobals())

it('records a deliberate opening before showing the released task instructions', async () => {
    const post = vi.fn().mockResolvedValue({ data: { started: true } })
    const get = vi.fn().mockResolvedValue({ data: { tasks: [{ id: 12, title: 'Dateien', status: 'released',
        task_started: false, can_start_task: true, instructions: 'Ordne die Dateien.' }] } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(StudentCourseTasks, { props: { courseId: 4 }, global: {
        stubs: { 'v-btn': { template: '<button><slot /></button>' } },
    } })
    try {
        await flushPromises()
        expect(post).not.toHaveBeenCalled()
        expect(wrapper.text()).not.toContain('Ordne die Dateien.')
        const button = wrapper.findAll('button').find(item => item.text() === 'Aufgabenstellung öffnen')
        await button!.trigger('click')
        await flushPromises()
        expect(post).toHaveBeenCalledExactlyOnceWith('/api/homepage/student/courses/4/works/12/start')
        expect(wrapper.text()).toContain('Ordne die Dateien.')
    } finally { wrapper.unmount() }
})

it('opens the tasks tab through the course navigation and preserves existing route state', () => {
    const context = { $route: { query: { panel: 'overview', keep: 'value' }, hash: '#course' }, $router: { replace: vi.fn() } }
    const tab = (MyCourse as any).computed.currentTab
    tab.set.call(context, 'tasks')
    expect(context.$router.replace).toHaveBeenCalledWith({ query: { panel: 'tasks', keep: 'value' }, hash: '#course' })
    context.$route.query.panel = 'tasks'
    expect(tab.get.call(context)).toBe('tasks')
})

it('shows assigned task PDFs and personal MC access while keeping instructions as text', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { tasks: [{ id: 12, title: 'Dateien',
        instructions: '<script>private</script>', deadline: { date: '2026-11-05', time: '12:00' },
        files: [{ id: 'pdf', name: 'Aufgabe.pdf', url: '/private/pdf' }], tests: [{ title: 'MC Dateien', url: '/multiple-choice#access=personal' }],
    }] } }) })
    const wrapper = mount(StudentCourseTasks, { props: { courseId: 4 } })
    try {
        await flushPromises()
        for (const text of ['Dateien', 'Freigegeben', '12:00', 'PDF öffnen', 'MC-Test starten', '<script>private</script>']) expect(wrapper.text()).toContain(text)
        expect(wrapper.find('script').exists()).toBe(false)
        expect(wrapper.findAll('[href]').map(link => link.attributes('href'))).toEqual(['/multiple-choice#access=personal'])
        expect(wrapper.text()).toContain('Aufgabe.pdf')
    } finally { wrapper.unmount() }
})

it.each([
    ['not_started', 'MC-Test starten'],
    ['in_progress', 'MC-Test fortsetzen'],
    ['paused', 'MC-Test öffnen'],
    ['finished', 'MC-Test öffnen'],
    ['unavailable', 'MC-Test öffnen'],
])('shows the action for a personal test in state %s', async (state, label) => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { tasks: [{ id: 12, title: 'Dateien', files: [],
        tests: [{ title: 'MC Dateien', url: '/multiple-choice#access=personal', attempt_state: state }] }] } }) })
    const wrapper = mount(StudentCourseTasks, { props: { courseId: 4 } })
    try {
        await flushPromises()
        expect(wrapper.text()).toContain(label)
        expect(wrapper.get('[href]').attributes('href')).toBe('/multiple-choice#access=personal')
    } finally { wrapper.unmount() }
})

it.each([
    ['not_released', 'Noch nicht freigegeben'],
    ['paused', 'Pausiert'],
])('shows safe task details for %s and refreshes the list on return', async (status, label) => {
    const taskData = { id: 12, title: 'Dateien', status, instructions: 'Vertraulich',
        has_assessment: true, has_multiple_choice: true, assignment_kind: 'worksheet',
        dates: ['2026-11-02'], deadline: { date: '2026-11-05', time: null },
        assessment_points: 35, grading_points: 20, assignment_points: 25, mc_points: 10,
        files: [{ id: 'pdf', name: 'Aufgabe.pdf' }],
        tests: [{ title: 'MC Dateien', url: '/multiple-choice#access=personal' }] }
    const get = vi.fn().mockResolvedValueOnce({ data: { tasks: [taskData] } })
        .mockResolvedValueOnce({ data: { tasks: [] } })
    const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    vi.stubGlobal('axios', { get })
    const wrapper = mount(StudentCourseTasks, { props: { courseId: 4 } })
    try {
        await flushPromises()
        const task = wrapper.get('article')
        expect(task.get('h3').text()).toBe('Dateien')
        expect(task.get('.task-status').text()).toBe(label)
        for (const label of ['Leistungsfeststellung', 'Multiple-Choice-Test', 'Durchführung', '2.11.2026', 'Erreichbare Prüfungspunkte', '35 Punkte', 'Wert der Arbeit in Schooltool',
            '20 Punkte', 'Aufgabenblatt · 25 Punkte', 'Multiple-Choice-Test · 10 Punkte', 'Abgabefrist', '5.11.2026']) expect(task.text()).toContain(label)
        for (const text of ['Vertraulich', '23:59', 'Aufgabe.pdf', 'MC Dateien']) expect(task.text()).not.toContain(text)
        expect(task.findAll('button, [href]')).toHaveLength(0)
        window.dispatchEvent(new Event('focus'))
        await flushPromises()
        expect(wrapper.find('article').exists()).toBe(false)
        expect(wrapper.text()).toContain('Noch keine Aufgaben')
    } finally { wrapper.unmount(); visibility.mockRestore() }
})

it('refreshes the saved deadlines and test state when returning to the tasks page', async () => {
    const task = { id: 12, title: 'Dateien', files: [], deadline: { date: '2026-11-05', time: '12:00' },
        tests: [{ title: 'MC Dateien', url: '/multiple-choice#access=personal', attempt_state: 'not_started' }] }
    const get = vi.fn().mockResolvedValueOnce({ data: { tasks: [task] } })
        .mockResolvedValueOnce({ data: { tasks: [{ ...task, deadline: { date: '2026-11-06', time: '19:45' },
            tests: [{ ...task.tests[0], attempt_state: 'in_progress' }] }] } })
    const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    vi.stubGlobal('axios', { get })
    const wrapper = mount(StudentCourseTasks, { props: { courseId: 4 } })
    try {
        await flushPromises()
        expect(wrapper.text()).toContain('MC-Test starten')
        window.dispatchEvent(new Event('focus'))
        await flushPromises()
        expect(wrapper.text()).toContain('MC-Test fortsetzen')
        expect(wrapper.text()).not.toContain('Mitgeteilte Abgabefrist')
        expect(wrapper.text()).toContain('Endgültige Abgabefrist')
        expect(wrapper.text()).toContain('6.11.2026 · 19:45 Uhr')
        expect(get).toHaveBeenCalledTimes(2)
    } finally { wrapper.unmount(); visibility.mockRestore() }
})

it('updates a visible task and its expiry after a deadline is moved earlier and extended', async () => {
    vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] })
    vi.setSystemTime(new Date('2026-11-06T12:00:00Z'))
    const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    const task = { id: 12, title: 'Dateien', instructions: 'Ordne deine Dateien.', deadline: { date: '2026-11-07', time: '12:30' } }
    const get = vi.fn().mockResolvedValueOnce({ data: { tasks: [task] } })
        .mockResolvedValueOnce({ data: { version: 'earlier', tasks: [{ id: 12, status: 'released', deadline: { date: '2026-11-06', time: '12:30' } }] } })
        .mockResolvedValueOnce({ data: { version: 'later', tasks: [{ id: 12, status: 'released', deadline: { date: '2026-11-06', time: '15:30' } }] } })
        .mockResolvedValueOnce({ data: { version: 'later' } })
    vi.stubGlobal('axios', { get })
    const wrapper = mount(StudentCourseTasks, { props: { courseId: 4 } })
    try {
        await flushPromises()
        expect(wrapper.text()).toContain('7.11.2026 · 12:30 Uhr')
        expect(wrapper.text()).not.toContain('Abgelaufen')
        await vi.advanceTimersByTimeAsync(1999)
        expect(get).toHaveBeenCalledTimes(1)
        await vi.advanceTimersByTimeAsync(1)
        expect(get.mock.calls[1][1].params).toEqual({ view: 'deadlines', version: '' })
        expect(wrapper.text()).toContain('6.11.2026 · 12:30 Uhr')
        expect(wrapper.text()).toContain('Abgelaufen')
        await vi.advanceTimersByTimeAsync(2000)
        expect(get.mock.calls[2][1].params).toEqual({ view: 'deadlines', version: 'earlier' })
        expect(wrapper.text()).toContain('6.11.2026 · 15:30 Uhr')
        expect(wrapper.text()).not.toContain('Abgelaufen')
        await vi.advanceTimersByTimeAsync(2000)
        expect(get.mock.calls[3][1].params).toEqual({ view: 'deadlines', version: 'later' })
        expect(wrapper.text()).toContain('Ordne deine Dateien.')
        await wrapper.setProps({ active: false })
        await vi.advanceTimersByTimeAsync(4000)
        expect(get).toHaveBeenCalledTimes(4)
    } finally {
        wrapper.unmount()
        visibility.mockRestore()
        vi.useRealTimers()
    }
})

it('shows that portal submissions close when the saved Vienna deadline passes in an open task view', async () => {
    vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] })
    vi.setSystemTime(new Date('2026-11-24T11:29:59Z'))
    const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    const task = { id: 12, title: 'Dateien', status: 'released', dates: ['2026-11-20'],
        deadline: { date: '2026-11-24', time: '12:30' }, submission_method: { channel: 'portal' }, can_submit: true,
        submission_files: [{ id: 'receipt-id', name: 'Lösung.txt', submitted_at: '2026-11-24T11:00:00Z' }],
    }
    const get = vi.fn().mockResolvedValueOnce({ data: { tasks: [task] } }).mockResolvedValue({ data: { version: 'unchanged' } })
    vi.stubGlobal('axios', { get })
    const wrapper = mount(StudentCourseTasks, { props: { courseId: 4 } })
    try {
        await flushPromises()
        const submission = wrapper.get('[aria-label="Meine Abgabe"]')
        expect(submission.find('input[type=file]').exists()).toBe(true)
        expect(submission.text()).toContain('Zurückziehen')

        await vi.advanceTimersByTimeAsync(2000)

        expect(submission.text()).toContain('Die Abgabe ist geschlossen.')
        expect(submission.find('input[type=file]').exists()).toBe(false)
        expect(submission.text()).not.toContain('Zurückziehen')
        expect(submission.text()).toContain('Lösung.txt')
        expect(wrapper.get('.task-status').text()).toBe('Freigegeben')
    } finally {
        wrapper.unmount()
        visibility.mockRestore()
        vi.useRealTimers()
    }
})

it('updates an already ended task when a personal upload window becomes available and expires', async () => {
    vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] })
    vi.setSystemTime(new Date('2026-11-24T11:45:00Z'))
    const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    const task = { id: 12, title: 'Dateien', status: 'ended', deadline: { date: '2026-11-24', time: '12:30' },
        submission_method: { channel: 'portal' }, can_submit: false }
    const reopened = { ...task, submission_deadline_at: '2026-11-24T12:00:00+00:00', can_submit: true }
    const get = vi.fn().mockResolvedValueOnce({ data: { tasks: [task] } })
        .mockResolvedValueOnce({ data: { tasks: [reopened], version: 'reopened' } })
        .mockResolvedValueOnce({ data: { tasks: [reopened] } })
        .mockResolvedValue({ data: { version: 'reopened' } })
    vi.stubGlobal('axios', { get })
    const wrapper = mount(StudentCourseTasks, { props: { courseId: 4 } })
    try {
        await flushPromises()
        const submission = wrapper.get('[aria-label="Meine Abgabe"]')
        expect(submission.text()).toContain('Die Abgabe ist geschlossen.')
        await vi.advanceTimersByTimeAsync(2000)
        expect(submission.text()).toContain('Persönliche Upload-Frist: 24.11.2026 um 13:00 Uhr')
        expect(submission.find('input[type=file]').exists()).toBe(true)
        expect(wrapper.get('.task-status').text()).toBe('Freigabe beendet')

        vi.setSystemTime(new Date('2026-11-24T12:00:01Z'))
        await vi.advanceTimersByTimeAsync(2000)
        expect(submission.text()).toContain('Die Abgabe ist geschlossen.')
        expect(submission.find('input[type=file]').exists()).toBe(false)
    } finally {
        wrapper.unmount()
        visibility.mockRestore()
        vi.useRealTimers()
    }
})

it('refreshes the deadline and linked test action on a private signal before the fallback timer', async () => {
    vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] })
    const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    vi.stubGlobal('schooltoolEchoEnvironment', { VITE_BROADCAST_CONNECTION: 'pusher', VITE_PUSHER_APP_KEY: 'test-key', VITE_PUSHER_APP_CLUSTER: 'eu' })
    const task = { id: 12, title: 'Dateien', deadline: { date: '2026-11-07', time: '16:00' },
        tests: [{ title: 'MC Dateien', url: '/multiple-choice#access=personal', attempt_state: 'in_progress' }] }
    const deadline = { date: '2026-11-07', time: '15:00' }
    const get = vi.fn()
        .mockResolvedValueOnce({ data: { tasks: [task] }, headers: { 'x-schooltool-deadline-channel': 'user.123' } })
        .mockResolvedValueOnce({ data: { version: 'new', tasks: [{ id: 12, status: 'released', deadline }] } })
        .mockResolvedValueOnce({ data: { tasks: [{ ...task, deadline, tests: [{ ...task.tests[0], attempt_state: 'finished' }] }] },
            headers: { 'x-schooltool-deadline-channel': 'user.123' } })
    const post = vi.fn().mockResolvedValue({ data: { auth: 'local-signature' } })
    vi.stubGlobal('axios', { get, post })
    push.private.mockReturnValue({ listen: push.listen })
    const wrapper = mount(StudentCourseTasks, { props: { courseId: 4 } })
    try {
        await flushPromises()
        expect(wrapper.text()).toContain('MC-Test fortsetzen')
        expect(push.private).toHaveBeenCalledWith('user.123')
        expect(push.listen).toHaveBeenCalledWith('.course-work.deadline-changed', expect.any(Function))
        const authorized = vi.fn()
        push.configureEcho.mock.calls[0][0].channelAuthorization.customHandler({ socketId: '123.456', channelName: 'private-user.123' }, authorized)
        await flushPromises()
        expect(post).toHaveBeenCalledWith('/broadcasting/auth', { socket_id: '123.456', channel_name: 'private-user.123' }, { timeout: 3000 })
        expect(authorized).toHaveBeenCalledWith(null, { auth: 'local-signature' })
        await vi.advanceTimersByTimeAsync(100)
        expect(get).toHaveBeenCalledTimes(1)
        push.listen.mock.calls[0][1]()
        expect(get).toHaveBeenCalledTimes(2)
        await flushPromises()
        expect(wrapper.text()).toContain('7.11.2026 · 15:00 Uhr')
        expect(wrapper.text()).toContain('MC-Test öffnen')
        expect(wrapper.text()).not.toContain('MC-Test fortsetzen')
        expect(get).toHaveBeenCalledTimes(3)
        await wrapper.setProps({ active: false })
        expect(push.leaveChannel).toHaveBeenCalledWith('private-user.123')
    } finally {
        wrapper.unmount()
        vi.useRealTimers()
        visibility.mockRestore()
    }
})

it('opens an assigned PDF using the authenticated request and closes its preview', async () => {
    const createObjectURL = vi.fn().mockReturnValue('blob:task-pdf')
    const revokeObjectURL = vi.fn()
    vi.stubGlobal('URL', Object.assign(class extends URL {}, { createObjectURL, revokeObjectURL }))
    const pdf = new Blob(['%PDF-1.4 test'], { type: 'application/pdf' })
    const get = vi.fn().mockResolvedValueOnce({ data: { tasks: [{ id: 12, title: 'Dateien', files: [{ id: 'pdf', name: 'Aufgabe.pdf' }], tests: [] }] } })
        .mockResolvedValueOnce({ data: pdf })
    vi.stubGlobal('axios', { get })
    const stubs = {
        'v-btn': { template: '<button><slot /></button>' },
        'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
        CurriculumPdfPreview: true,
    }
    const wrapper = mount(StudentCourseTasks, { props: { courseId: 4 }, global: { stubs } })
    try {
        await flushPromises()
        await wrapper.findAll('button').find(button => button.text().includes('Aufgabe.pdf'))!.trigger('click')
        await flushPromises()
        expect(get.mock.calls[1]).toEqual(['/api/homepage/student/courses/4/works/12/tasks/pdf', { responseType: 'blob' }])
        expect(createObjectURL).toHaveBeenCalledWith(pdf)
        expect(wrapper.getComponent({ name: 'CurriculumPdfPreview' }).attributes('src')).toBe('blob:task-pdf')
        await wrapper.findAll('button').find(button => button.text() === 'Schließen')!.trigger('click')
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:task-pdf')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('shows a helpful error when an assigned PDF cannot be loaded', async () => {
    const get = vi.fn().mockResolvedValueOnce({ data: { tasks: [{ id: 12, title: 'Dateien', files: [{ id: 'pdf', name: 'Aufgabe.pdf' }], tests: [] }] } })
        .mockRejectedValueOnce(new Error('forbidden'))
    vi.stubGlobal('axios', { get })
    const stubs = {
        'v-btn': { template: '<button><slot /></button>' },
        'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
        CurriculumPdfPreview: true,
    }
    const wrapper = mount(StudentCourseTasks, { props: { courseId: 4 }, global: { stubs } })
    try {
        await flushPromises()
        await wrapper.findAll('button').find(button => button.text().includes('Aufgabe.pdf'))!.trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="alert"]').text()).toContain('Aufgaben-PDF konnte nicht geöffnet werden')
        expect(wrapper.findComponent({ name: 'CurriculumPdfPreview' }).exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('loads the updated tasks and their deadlines from empty and failed lists with Aktualisieren', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    const task = { id: 12, title: 'Neu bereitgestellt', deadline: { date: '2026-11-14', time: '10:45' } }
    const get = vi.fn().mockResolvedValueOnce({ data: { tasks: [] } })
        .mockRejectedValueOnce(new Error('failed'))
        .mockResolvedValueOnce({ data: { tasks: [task] } })
    vi.stubGlobal('axios', { get })
    const wrapper = mount(StudentCourseTasks, { props: { courseId: 4 } })
    try {
        await flushPromises()
        expect(wrapper.text()).toContain('Noch keine Aufgaben')
        const reload = wrapper.get('.task-heading-actions v-btn')
        expect(reload.text()).toBe('Aktualisieren')
        await reload.trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="alert"]').text()).toContain('konnten nicht geladen werden')
        expect(reload.text()).toBe('Wird aktualisiert …')
        expect(reload.attributes('aria-busy')).toBe('true')
        await vi.advanceTimersByTimeAsync(650)
        expect(reload.text()).toBe('Aktualisieren')
        expect(reload.attributes('aria-busy')).toBe('false')
        await reload.trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Neu bereitgestellt')
        expect(wrapper.text()).toContain('14.11.2026 · 10:45 Uhr')
        expect(wrapper.find('[role="alert"]').exists()).toBe(false)
        expect(get.mock.calls.map(([url]) => url)).toEqual(Array(3).fill('/api/homepage/student/courses/4/tasks'))
    } finally { wrapper.unmount(); vi.useRealTimers() }
})

it('ignores a stale automatic response and coalesces repeated clicks during a manual reload', async () => {
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] })
    const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    let resolvePoll: (response: object) => void = () => {}
    let resolveManual: (response: object) => void = () => {}
    const task = { id: 12, title: 'Dateien', deadline: { date: '2026-11-13', time: '12:00' } }
    const get = vi.fn().mockResolvedValueOnce({ data: { tasks: [task] } })
        .mockImplementationOnce(() => new Promise(resolve => { resolvePoll = resolve }))
        .mockImplementationOnce(() => new Promise(resolve => { resolveManual = resolve }))
    vi.stubGlobal('axios', { get })
    const wrapper = mount(StudentCourseTasks, { props: { courseId: 4 } })
    try {
        await flushPromises()
        await vi.advanceTimersByTimeAsync(2000)
        const reload = wrapper.get('.task-heading-actions v-btn')
        await reload.trigger('click')
        await reload.trigger('click')
        expect(get).toHaveBeenCalledTimes(3)
        expect(get.mock.calls[1][1].signal.aborted).toBe(true)
        resolveManual({ data: { tasks: [{ ...task, deadline: { date: '2026-11-14', time: '18:30' } }] } })
        await flushPromises()
        resolvePoll({ data: { version: 'stale', tasks: [{ ...task, status: 'released' }] } })
        await flushPromises()
        expect(wrapper.text()).toContain('14.11.2026 · 18:30 Uhr')
        expect(wrapper.text()).not.toContain('13.11.2026')
    } finally {
        wrapper.unmount()
        visibility.mockRestore()
        vi.useRealTimers()
    }
})

it('clears a previous courses tasks before loading another course and offers retry on failure', async () => {
    const get = vi.fn().mockResolvedValueOnce({ data: { tasks: [{ id: 1, title: 'Altes Fach', files: [], tests: [] }] } })
        .mockRejectedValueOnce(new Error('failed')).mockResolvedValue({ data: { tasks: [] } })
    vi.stubGlobal('axios', { get })
    const wrapper = mount(StudentCourseTasks, { props: { courseId: 4 } })
    try {
        await flushPromises()
        expect(wrapper.text()).toContain('Altes Fach')
        await wrapper.setProps({ courseId: 5 })
        await flushPromises()
        expect(wrapper.text()).not.toContain('Altes Fach')
        expect(wrapper.get('[role="alert"]').text()).toContain('konnten nicht geladen werden')
        await wrapper.get('v-btn').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Hier findest du die Aufgaben')
    } finally { wrapper.unmount() }
})
