import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, ref } from 'vue'
import { createTestingPinia } from '@pinia/testing'
import WorkProgressCheck from '@/pages/admin/teaching/overview/components/WorkProgressCheck.vue'
import WorkDeliveryStatus from '@/pages/admin/teaching/overview/components/WorkDeliveryStatus.vue'

afterEach(() => vi.unstubAllGlobals())

function useCheckStorage() {
    const values = new Map<string, string>()
    vi.stubGlobal('localStorage', {
        getItem: (key: string) => values.get(key) ?? null,
        setItem: (key: string, value: string) => values.set(key, value),
    })
}

it('reports and restores a successful collection check for the matching identity', async () => {
    useCheckStorage()
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { data: { id: 64, teaching_course_id: 16 },
        progress: { state: 'done', completed: ['connection', 'folder', 'collection'], skipped: [] } } }) })
    const props = { work: { id: 64, teaching_course_id: 16 }, checkKey: '7:16:64' }
    const options = { props, global: { stubs: { 'v-btn': { template: '<button><slot /></button>' } } } }
    const wrapper = mount(WorkProgressCheck, options)
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        const checked = wrapper.emitted('checked')?.at(-1)?.[0]
        expect(checked).toEqual({ checkKey: '7:16:64', checkedAt: expect.any(String), completed: true })
        wrapper.unmount()

        const reopened = mount(WorkProgressCheck, options)
        try {
            expect(reopened.emitted('checked')?.at(-1)?.[0]).toEqual(checked)
            await reopened.setProps({ work: { id: 65, teaching_course_id: 16 }, checkKey: '7:16:65' })
            expect(reopened.emitted('checked')?.at(-1)?.[0]).toEqual({ checkKey: '7:16:65', checkedAt: null, completed: false })
            await reopened.setProps({ work: props.work, checkKey: null })
            expect(reopened.emitted('checked')?.at(-1)?.[0]).toEqual({ checkKey: null, checkedAt: null, completed: false })
            await reopened.setProps({ checkKey: props.checkKey })
            expect(reopened.emitted('checked')?.at(-1)?.[0]).toEqual(checked)
        } finally { reopened.unmount() }
    } finally { wrapper.unmount() }
})

it('reports a skipped check without claiming the collection was checked', async () => {
    useCheckStorage()
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { data: { id: 64, teaching_course_id: 16 },
        progress: { state: 'done', completed: ['connection', 'folder'], skipped: ['collection'] } } }) })
    const wrapper = mount(WorkProgressCheck, { props: { work: { id: 64, teaching_course_id: 16 }, checkKey: '7:16:64' },
        global: { stubs: { 'v-btn': { template: '<button><slot /></button>' } } } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.emitted('checked')?.at(-1)?.[0]).toEqual({ checkKey: '7:16:64', checkedAt: expect.any(String), completed: false })
    } finally { wrapper.unmount() }
})

it('retains the previous check when the request fails or the response is invalid', async () => {
    useCheckStorage()
    const get = vi.fn().mockResolvedValueOnce({ data: { data: { id: 64, teaching_course_id: 16 },
        progress: { state: 'done', completed: ['collection'], skipped: [] } } })
        .mockRejectedValueOnce(new Error('Verbindung fehlgeschlagen'))
        .mockResolvedValueOnce({ data: { data: { id: 99, teaching_course_id: 16 },
            progress: { state: 'done', completed: ['collection'], skipped: [] } } })
    vi.stubGlobal('axios', { get })
    const wrapper = mount(WorkProgressCheck, { props: { work: { id: 64, teaching_course_id: 16 }, checkKey: '7:16:64' },
        global: { stubs: { 'v-btn': { template: '<button><slot /></button>' } } } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        const checked = wrapper.emitted('checked')?.at(-1)?.[0]
        for (let attempt = 0; attempt < 2; attempt++) {
            await wrapper.get('button').trigger('click')
            await flushPromises()
            expect(wrapper.emitted('checked')?.at(-1)?.[0]).toEqual(checked)
            expect(wrapper.get('[role="alert"]').text()).toBeTruthy()
        }
    } finally { wrapper.unmount() }
})

it('does not apply an in-flight result after switching the work identity', async () => {
    useCheckStorage()
    let finish: (value: unknown) => void = () => {}
    vi.stubGlobal('axios', { get: vi.fn(() => new Promise(resolve => { finish = resolve })) })
    const wrapper = mount(WorkProgressCheck, { props: { work: { id: 64, teaching_course_id: 16 }, checkKey: '7:16:64' },
        global: { stubs: { 'v-btn': { template: '<button><slot /></button>' } } } })
    try {
        await wrapper.get('button').trigger('click')
        await wrapper.setProps({ work: { id: 65, teaching_course_id: 16 }, checkKey: '7:16:65' })
        finish({ data: { data: { id: 64, teaching_course_id: 16 },
            progress: { state: 'done', completed: ['collection'], skipped: [] } } })
        await flushPromises()
        expect(wrapper.emitted('checked')?.at(-1)?.[0]).toEqual({ checkKey: '7:16:65', checkedAt: null, completed: false })
        expect(wrapper.emitted('saved')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it.each([true, false])('refreshes collection evidence explicitly and accepts only the selected work: %s', async matching => {
    const get = vi.fn().mockResolvedValue({ data: { data: { id: matching ? 64 : 86, teaching_course_id: 16,
        collection_run: { completed_at: '2026-10-24T13:46:16Z' } } } })
    vi.stubGlobal('axios', { get })
    const wrapper = mount(WorkProgressCheck, { props: { work: { id: 64, teaching_course_id: 16 } },
        global: { stubs: { 'v-btn': { template: '<button><slot /></button>' } } } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({ params: { refresh_collection: true, progress_id: expect.any(String), stream_progress: true }, onDownloadProgress: expect.any(Function) }))
        if (matching) {
            expect(wrapper.emitted('saved')?.[0]?.[0]).toMatchObject({ data: { id: 64,
                collection_run: { completed_at: '2026-10-24T13:46:16Z' } } })
        } else {
            expect(wrapper.emitted('saved')).toBeUndefined()
            expect(wrapper.get('[role="alert"]').text()).toBe('Arbeitsstand nicht bestätigt.')
        }
    } finally { wrapper.unmount() }
})

it('shows real progress during the collection check and marks skipped work neutrally', async () => {
    let finish: (value: unknown) => void = () => {}
    let notify = (_event: unknown) => {}
    const get = vi.fn((_url: string, options: any) => {
        notify = options.onDownloadProgress
        return new Promise(resolve => { finish = resolve })
    })
    vi.stubGlobal('axios', { get })
    const wrapper = mount(WorkProgressCheck, { props: { work: { id: 64, teaching_course_id: 16 } },
        global: { stubs: { 'v-btn': { template: '<button><slot /></button>' } } } })
    try {
        vi.useFakeTimers()
        await wrapper.get('button').trigger('click')
        const event = { type: 'progress', data: { state: 'running', active: 'collection', steps: ['connection', 'folder', 'collection'], completed: ['connection'], skipped: ['folder'] } }
        notify({ event: { target: { responseText: JSON.stringify(event) + '\n' } } })
        await flushPromises()
        const progress = wrapper.get('[aria-label="Fortschritt der Prüfung"]')
        expect(progress.text()).toContain('Dropbox-Zuordnung prüfenErledigt')
        expect(progress.text()).toContain('Arbeitsordner ermittelnNicht ausgeführt')
        expect(progress.text()).toContain('Arbeitsstand prüfenWird geprüft')
        finish({ data: { data: { id: 64, teaching_course_id: 16 }, progress: { state: 'done', active: null,
            steps: ['connection', 'folder', 'collection'], completed: ['connection', 'collection'], skipped: ['folder'] } } })
        await vi.runAllTicks()
        await flushPromises()
        expect(progress.text()).toContain('Prüfung abgeschlossen')
        expect(progress.text()).toContain('Arbeitsstand prüfenErledigt')
        expect(wrapper.emitted('checked')?.at(-1)?.[0]).toMatchObject({ completed: false })
        await vi.advanceTimersByTimeAsync(2000)
        expect(get).toHaveBeenCalledTimes(1)
    } finally { wrapper.unmount(); vi.useRealTimers() }
})

it('does not show a completed check if the server returns a different work', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { data: { id: 99, teaching_course_id: 16 },
        progress: { state: 'done', steps: ['connection'], completed: ['connection'], skipped: [], active: null } } }) })
    const wrapper = mount(WorkProgressCheck, { props: { work: { id: 64, teaching_course_id: 16 } },
        global: { stubs: { 'v-btn': { template: '<button><slot /></button>' } } } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.get('[aria-label="Fortschritt der Prüfung"]').text()).toContain('Prüfung unterbrochen')
        expect(wrapper.get('[role="alert"]').text()).toContain('Arbeitsstand nicht bestätigt')
    } finally { wrapper.unmount() }
})

it('updates the Unterricht collection indicator and simulator time after an explicit recheck', async () => {
    const initial = { id: 86, teaching_course_id: 16, title: 'E-Mails', groups: [{ student_ids: [492, 493] }], status: {
        email_preparation_completed: true,
        dispatch_logs: [{ origin: 'dispatch_import', purpose: 'tasks', sha256: 'a'.repeat(64) }],
        dispatch_notifications: [492, 493].map(studentId => ({ origin: 'dispatch_import', purpose: 'tasks',
            student_id: studentId, sent_at: '2026-10-24T12:00:00Z', log_sha256: 'a'.repeat(64) })),
    } }
    const refreshed = { ...initial, simulation_collection: { origin: 'mailpit_simulation', simulation: true,
        run_id: 'selected-simulation', work_id: 86, course_id: 16, student_ids: [492, 493],
        collected_count: 1, non_submission_count: 1, processed_count: 2, complete: true,
        completed_at: '2026-10-24T15:35:00Z', last_collected_at: '2026-10-24T15:35:00Z',
        evaluation: { simulation: true, run_id: 'selected-simulation', completed_count: 1, partial_count: 1, open_count: 0,
            rows: [{ student_id: 492, person: 'Person Eins', state: 'complete', result: '4,5 von 5 Punkten' },
                { student_id: 493, person: 'Person Zwei', state: 'partial', result: '2,5 von 5 Punkten (vorläufig) · Offen: PDF-Inhalt' }] } } }
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { data: refreshed } }) })
    const page = defineComponent({ components: { WorkProgressCheck, WorkDeliveryStatus },
        setup() { return { work: ref(initial) } },
        template: '<WorkProgressCheck :work="work" @saved="work = $event.data" /><WorkDeliveryStatus :work="work" />' })
    const wrapper = mount(page, { global: {
        plugins: [createTestingPinia({ createSpy: vi.fn })],
        stubs: { 'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' } },
    } })
    try {
        await flushPromises()
        expect(wrapper.get('[data-workflow-stage="collection"]').attributes('data-workflow-state')).toBe('open')
        expect(wrapper.get('[data-workflow-stage="collection"]').element.disabled).toBe(false)
        await wrapper.findAll('button').find(button => button.text().includes('Erneut prüfen')).trigger('click')
        await flushPromises()
        expect(wrapper.get('[data-workflow-stage="collection"]').attributes('data-workflow-state')).toBe('complete')
        expect(wrapper.get('[data-workflow-stage="evaluation"]').attributes('data-workflow-state')).toBe('partial')
        expect(wrapper.get('[data-workflow-stage="collection"]').element.disabled).toBe(false)
        await wrapper.get('[data-workflow-stage="collection"]').trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('abgeschlossen · Simulation')
        const students = wrapper.get('[aria-label="Abgabestatus der Schülerinnen und Schüler"]').findAll('li')
        expect(students).toHaveLength(2)
        for (const student of students) {
            expect(student.text()).toContain('AbgabeUngeklärt')
            expect(student.text()).toContain('MC-TestKein MC-Test')
        }
        expect(wrapper.get('[role="dialog"]').text()).toContain('Simulator: 24.10.2026, 17:35 Uhr')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Simulator öffnen')
        expect(wrapper.get('[role="dialog"]').text()).not.toContain('Erneut einsammeln')
        await wrapper.get('[data-workflow-stage="evaluation"]').trigger('click')
        expect(wrapper.get('[aria-label="Testbewertungen"]').text()).toContain('Person Eins')
        expect(wrapper.get('[aria-label="Testbewertungen"]').text()).toContain('4,5 von 5 Punkten')
        expect(wrapper.get('[aria-label="Testbewertungen"]').text()).toContain('2,5 von 5 Punkten (vorläufig)')
        expect(wrapper.get('[role="dialog"]').text()).toContain('1 abgeschlossen, 1 teilweise bewertet, 0 offen')
    } finally { wrapper.unmount() }
})
