import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import StudentCourseTasks from '@/pages/homepage/student/overview/myCourse/StudentCourseTasks.vue'
import MyCourse from '@/pages/homepage/student/overview/myCourse/MyCourse.vue'

afterEach(() => vi.unstubAllGlobals())

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
        for (const text of ['Dateien', '12:00', 'PDF öffnen', 'MC-Test starten', '<script>private</script>']) expect(wrapper.text()).toContain(text)
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

it('refreshes the test state when returning to the tasks page', async () => {
    const task = { id: 12, title: 'Dateien', files: [], tests: [{ title: 'MC Dateien', url: '/multiple-choice#access=personal', attempt_state: 'not_started' }] }
    const get = vi.fn().mockResolvedValueOnce({ data: { tasks: [task] } })
        .mockResolvedValueOnce({ data: { tasks: [{ ...task, tests: [{ ...task.tests[0], attempt_state: 'in_progress' }] }] } })
    const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    vi.stubGlobal('axios', { get })
    const wrapper = mount(StudentCourseTasks, { props: { courseId: 4 } })
    try {
        await flushPromises()
        expect(wrapper.text()).toContain('MC-Test starten')
        window.dispatchEvent(new Event('focus'))
        await flushPromises()
        expect(wrapper.text()).toContain('MC-Test fortsetzen')
        expect(get).toHaveBeenCalledTimes(2)
    } finally { wrapper.unmount(); visibility.mockRestore() }
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
