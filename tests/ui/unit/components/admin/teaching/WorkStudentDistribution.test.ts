import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import WorkStudentDistribution from '@/pages/admin/teaching/overview/components/WorkStudentDistribution.vue'

afterEach(() => vi.unstubAllGlobals())

const stubs = {
    'v-textarea': true,
    'v-file-input': true,
    'v-checkbox': true,
    CurriculumPdfPreview: true,
    'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
    'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
}

it('deletes a saved paused preparation only after confirmation in the same dialog', async () => {
    const work = { id: 12, title: 'Dateien', local_task_copy: true, student_distribution_version: 'a'.repeat(64),
        status: { student_distribution: { prepared_at: '2026-11-05T10:00:00Z', instructions: 'Aufgabenstellung' } } }
    const deleted = { ...work, status: { student_distribution: { deleted_at: '2026-11-05T11:00:00Z' } } }
    const remove = vi.fn().mockResolvedValue({ data: { data: deleted } })
    const get = vi.fn().mockResolvedValue({ data: { data: work } })
    vi.stubGlobal('axios', { get, delete: remove })
    const wrapper = mount(WorkStudentDistribution, { props: { work }, global: { stubs } })
    const click = (label: string) => wrapper.findAll('button').find(button => button.text() === label)!.trigger('click')
    try {
        await click('Pausierte Aufgaben verwalten')
        await flushPromises()
        await click('Vorbereitung löschen')
        expect(wrapper.findAll('[role="dialog"]')).toHaveLength(1)
        expect(wrapper.text()).toContain('Die Ankündigung verschwindet')
        expect(remove).not.toHaveBeenCalled()
        await click('Abbrechen')
        expect(wrapper.text()).toContain('Jetzt freigeben')
        expect(remove).not.toHaveBeenCalled()
        await click('Vorbereitung löschen')
        await click('Löschen bestätigen')
        await flushPromises()
        expect(remove).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/12/student-distribution/preparation',
            { data: { expected_version: work.student_distribution_version } })
        expect(wrapper.emitted('saved')).toEqual([[deleted]])
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('previews the server-verified shared assignment regardless of its original PDF name', async () => {
    const work = { id: 12, student_distribution_version: 'a'.repeat(64), status: {} }
    const get = vi.fn().mockResolvedValueOnce({ data: { data: work } })
        .mockResolvedValueOnce({ data: { root: { name: 'Angaben' }, shared_assignment: true,
            entries: [{ id: 'id:pdf', type: 'file', name: 'Leistungsfeststellung_Word.pdf' }], cursor: null } })
        .mockResolvedValueOnce({ data: new Blob(['%PDF-1.4 test'], { type: 'application/pdf' }) })
    const post = vi.fn()
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledTimes(3)
        expect(wrapper.text()).toContain('Leistungsfeststellung_Word.pdf')
        expect(wrapper.text()).toContain('Pausiert vorbereiten')
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it.each(['', 'Eigenen Aufgabentext behalten.'])('automatically uses the single prepared PDF and preserves the existing instructions %j', async instructions => {
    const work = { id: 12, student_distribution_version: 'a'.repeat(64), status: { student_distribution: { instructions } } }
    const pdf = new Blob(['%PDF-1.4 verified task'], { type: 'application/pdf' })
    const filename = 'LF_Dateimanagement.pdf'
    const get = vi.fn().mockResolvedValueOnce({ data: { data: work } })
        .mockResolvedValue({ data: pdf, headers: { 'content-disposition': `attachment; filename*=utf-8''${filename}` } })
    const post = vi.fn().mockResolvedValue({ data: { data: work } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work, preparedExercise: true, date: '2026-10-20' }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledTimes(2)
        expect(get.mock.calls[1][1].params).toEqual({ contents: 1, exercise: 1, section: 'Angaben', date: '2026-10-20', download_prepared: 1 })
        expect(wrapper.findAll('button').filter(button => button.text() === filename)).toHaveLength(1)
        expect(wrapper.findAll('button').some(button => button.text() === 'Aufgaben-PDF laden')).toBe(false)
        expect(wrapper.findComponent({ name: 'v-file-input' }).exists()).toBe(false)
        expect(wrapper.getComponent({ name: 'v-textarea' }).attributes('modelvalue')).toBe(instructions || 'Siehe Aufgaben-PDF.')
        expect(post).not.toHaveBeenCalled()
        await wrapper.findAll('button').find(button => button.text() === 'Pausiert vorbereiten')!.trigger('click')
        await vi.waitFor(() => expect(post).toHaveBeenCalledOnce())
        const data = post.mock.calls[0][1] as FormData
        expect(data.get('release_now')).toBe('0')
        expect(data.getAll('files[]').map(file => (file as File).name)).toEqual([filename])
    } finally { wrapper.unmount() }
})

it.each([
    [404, 'Im verknüpften Prüfungsordner ist keine Aufgaben-PDF vorhanden.'],
    [409, 'Im Prüfungsordner sind mehrere passende PDFs vorhanden.'],
])('explains a prepared PDF lookup failure %s without adding a default reference', async (status, message) => {
    const work = { id: 12, student_distribution_version: 'a'.repeat(64) }
    const get = vi.fn().mockResolvedValueOnce({ data: { data: work } }).mockRejectedValueOnce({ response: {
        status, data: new Blob([JSON.stringify({ message })], { type: 'application/json' }),
    } })
    const post = vi.fn()
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work, preparedExercise: true }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain(message)
        expect(wrapper.getComponent({ name: 'v-textarea' }).attributes('modelvalue')).toBe('')
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('checks off real loading steps only after each response and the complete paginated PDF search', async () => {
    const responses = Array.from({ length: 5 }, () => {
        let complete: (value: unknown) => void = () => {}
        const request = new Promise(resolve => { complete = resolve })
        return { request, complete }
    })
    const get = vi.fn()
    for (const response of responses) get.mockReturnValueOnce(response.request)
    const post = vi.fn()
    vi.stubGlobal('axios', { get, post })
    const work = { id: 12, student_distribution_version: 'a'.repeat(64), status: { multiple_choice_tests: { all: { test_id: 4 } } } }
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work }, global: { stubs } })
    const steps = () => wrapper.get('[aria-label="Fortschritt beim Laden"]').findAll('li')
    try {
        await wrapper.get('button').trigger('click')
        expect(steps()[0].text()).toContain('Läuft …')
        expect(steps()[1].text()).toContain('Noch nicht gestartet')
        expect(wrapper.text()).not.toContain('Abgeschlossen')

        responses[0].complete({ data: { data: work } }); await flushPromises()
        expect(steps()[0].text()).toContain('✓')
        expect(steps()[0].text()).toContain('Abgeschlossen')
        expect(steps()[1].text()).toContain('Läuft …')
        expect(steps()[2].text()).toContain('Noch nicht gestartet')

        responses[1].complete({ data: { data: { tests: [{ id: 4, version: 'b'.repeat(64), start_mode: 'individual' }] } } }); await flushPromises()
        expect(steps()[1].text()).toContain('Abgeschlossen')
        expect(steps()[2].text()).toContain('Läuft …')
        responses[2].complete({ data: { root: { name: 'Angaben' }, entries: [{ id: 'id:pdf', type: 'file', name: 'Aufgabenstellung_Dateien.pdf' }], cursor: 'next-page' } }); await flushPromises()
        expect(steps()[2].text()).toContain('Läuft …')
        expect(steps()[3].text()).toContain('Noch nicht gestartet')

        responses[3].complete({ data: { root: { name: 'Angaben' }, entries: [], cursor: null } }); await flushPromises()
        expect(steps()[2].text()).toContain('Abgeschlossen')
        expect(steps()[3].text()).toContain('Läuft …')
        responses[4].complete({ data: new Blob(['%PDF-1.4 test'], { type: 'application/pdf' }) }); await flushPromises()
        expect(steps().every(step => step.text().includes('✓') && step.text().includes('Abgeschlossen'))).toBe(true)
        expect(wrapper.text()).toContain('Vorbereitung geladen')
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it.each(['work', 'mc', 'assignment', 'pdf'])('marks a failed %s load without checking it off and resets retries', async failedStage => {
    const work = { id: 12, student_distribution_version: 'a'.repeat(64), status: { multiple_choice_tests: { all: { test_id: 4 } } } }
    const stages = ['work', 'mc', 'assignment', 'pdf']
    const results = [
        { data: { data: work } },
        { data: { data: { tests: [{ id: 4, version: 'b'.repeat(64), start_mode: 'individual' }] } } },
        { data: { root: { name: 'Angaben' }, entries: [{ id: 'id:pdf', type: 'file', name: 'Aufgabenstellung_Dateien.pdf' }], cursor: null } },
        { data: new Blob(['%PDF-1.4 test'], { type: 'application/pdf' }) },
    ]
    const get = vi.fn()
    for (const [index, result] of results.entries()) {
        if (stages[index] === failedStage) get.mockRejectedValueOnce(new Error('Laden fehlgeschlagen'))
        else get.mockResolvedValueOnce(result)
    }
    const post = vi.fn()
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work }, global: { stubs } })
    const steps = () => wrapper.get('[aria-label="Fortschritt beim Laden"]').findAll('li')
    try {
        await wrapper.get('button').trigger('click'); await flushPromises()
        expect(steps()[stages.indexOf(failedStage)].text()).toContain('Fehlgeschlagen')
        expect(steps()[stages.indexOf(failedStage)].text()).not.toContain('✓')
        expect(wrapper.text()).toContain('Laden mit Fehlern beendet')
        expect(wrapper.text()).not.toContain('Läuft …')
        if (failedStage === 'assignment') expect(steps()[3].text()).toContain('Nicht geladen')
        if (failedStage === 'pdf') expect(steps()[2].text()).toContain('Abgeschlossen')
        if (failedStage === 'work') {
            expect(steps().slice(1).every(step => step.text().includes('Nicht geladen'))).toBe(true)
            get.mockReset().mockResolvedValue({ data: { data: { ...work, local_task_copy: true, status: {} } } })
            await wrapper.findAll('button').find(button => button.text() === 'Erneut laden')!.trigger('click'); await flushPromises()
            expect(steps()[0].text()).toContain('Abgeschlossen')
            expect(wrapper.text()).not.toContain('Fehlgeschlagen')
        }
        if (failedStage === 'mc') {
            expect(steps()[3].text()).toContain('Abgeschlossen')
            let completeRetry: (value: unknown) => void = () => {}
            get.mockReturnValueOnce(new Promise(resolve => { completeRetry = resolve }))
            await wrapper.findAll('button').find(button => button.text() === 'MC-Tests erneut laden')!.trigger('click')
            expect(steps()[1].text()).toContain('Läuft …')
            expect(wrapper.text()).toContain('Vorbereitung wird geladen …')
            completeRetry(results[1]); await flushPromises()
            expect(steps()[1].text()).toContain('Abgeschlossen')
            expect(wrapper.text()).not.toContain('Laden mit Fehlern beendet')
        }
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('prepares a local test copy from its saved PDF without accessing Dropbox or publishing it', async () => {
    const work = { id: 12, local_task_copy: true, student_distribution_version: 'a'.repeat(64), status: {
        student_task_files: [{ id: 'local-pdf', name: 'Aufgabenstellung_E-Mails.pdf' }],
        student_distribution: { instructions: 'Siehe Aufgaben-PDF.', file_ids: ['local-pdf'] },
    } }
    const get = vi.fn().mockResolvedValue({ data: { data: work } })
    const post = vi.fn().mockResolvedValue({ data: { data: work } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledOnce()
        const progress = wrapper.get('[aria-label="Fortschritt beim Laden"]')
        expect(progress.text()).toContain('Kein MC-Test verknüpft')
        expect(progress.text()).toContain('Gespeicherte Aufgaben verwenden')
        expect(progress.text()).toContain('Nicht erforderlich')
        expect(progress.findAll('li').filter(step => step.text().includes('Abgeschlossen'))).toHaveLength(1)
        expect(wrapper.findComponent({ name: 'v-checkbox' }).attributes('label')).toBe('Aufgabenstellung_E-Mails.pdf')
        expect(wrapper.text()).toContain('Pausiert vorbereiten')
        expect(post).not.toHaveBeenCalled()
        await wrapper.findAll('button').find(button => button.text() === 'Pausiert vorbereiten')!.trigger('click')
        await flushPromises()
        expect((post.mock.calls[0][1] as FormData).get('retained_file_ids[]')).toBe('local-pdf')
    } finally { wrapper.unmount() }
})

it('shows the action only after the assignment is ready, while keeping an existing release accessible', async () => {
    const wrapper = mount(WorkStudentDistribution, { props: { work: { id: 12, title: 'Dateien' }, available: false }, global: { stubs } })
    try {
        expect(wrapper.find('button').exists()).toBe(false)
        await wrapper.setProps({ available: true })
        expect(wrapper.get('button').text()).toBe('Im Schülerbereich bereitstellen')
        await wrapper.setProps({ available: false, work: { id: 12, title: 'Dateien', status: {
            student_distribution: { published_at: '2026-10-28T12:00:00Z', student_ids: [] },
        } } })
        expect(wrapper.get('button').text()).toBe('Freigabe verwalten')
    } finally { wrapper.unmount() }
})

it.each(['Angaben', 'Quellunterlagen'])('previews the assignment PDF from %s and returns without publishing', async section => {
    const createObjectURL = vi.fn().mockReturnValue('blob:assignment-preview')
    const revokeObjectURL = vi.fn()
    vi.stubGlobal('URL', Object.assign(class extends URL {}, { createObjectURL, revokeObjectURL }))
    const pdf = new Blob(['%PDF-1.4 test'], { type: 'application/pdf' })
    const get = vi.fn().mockImplementation((_url, options) => {
        if (!options) return Promise.resolve({ data: { data: { id: 12, student_distribution_version: 'a'.repeat(64) } } })
        if (options.params.pdf) return Promise.resolve({ data: pdf })
        return Promise.resolve({ data: { root: { name: section }, entries: [{ id: 'id:task', type: 'file', name: section === 'Quellunterlagen' ? 'Leistungsfeststellung_Dateimanagement.pdf' : 'Aufgabenstellung_E-Mails.pdf' }], cursor: null } })
    })
    const post = vi.fn()
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work: { id: 12, title: 'E-Mails' } }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain(`Aufgabenstellung aus „${section}“`)
        expect(get).toHaveBeenCalledWith('/api/admin/teaching/course_works/12/dropbox/folders', expect.objectContaining({
            params: expect.objectContaining({ contents: 1, exercise: 1, verify_exercise: 1, section: 'Angaben' }),
        }))
        const filename = section === 'Quellunterlagen' ? 'Leistungsfeststellung_Dateimanagement.pdf' : 'Aufgabenstellung_E-Mails.pdf'
        await wrapper.findAll('button').find(button => button.text() === filename)!.trigger('click')
        expect(createObjectURL.mock.calls[0][0].name).toBe(filename)
        expect(wrapper.getComponent({ name: 'CurriculumPdfPreview' }).attributes('src')).toBe('blob:assignment-preview')
        await wrapper.findAll('button').find(button => button.text() === 'Schließen')!.trigger('click')
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:assignment-preview')
        expect(wrapper.text()).toContain('Pausiert vorbereiten')
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('prepares the saved assignment description paused with explicit version protection and no email send', async () => {
    const saved = { id: 12, status: { student_distribution: { published_at: '2026-10-28T12:00:00Z' } } }
    const post = vi.fn().mockResolvedValue({ data: { data: saved } })
    const get = vi.fn().mockResolvedValue({ data: { data: { id: 12, description: '<p>Ordne deine Dateien.</p>', student_distribution_version: 'b'.repeat(64) } } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work: { id: 12, title: 'Dateien', student_distribution_version: 'a'.repeat(64) } }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        await wrapper.findAll('button').find(button => button.text() === 'Pausiert vorbereiten')!.trigger('click')
        await flushPromises()
        expect(post).toHaveBeenCalledOnce()
        expect(post.mock.calls[0][0]).toBe('/api/admin/teaching/course_works/12/student-distribution')
        const data = post.mock.calls[0][1] as FormData
        expect(data.get('publish')).toBe('1')
        expect(data.get('release_now')).toBe('0')
        expect(data.get('instructions')).toBe('Ordne deine Dateien.')
        expect(data.get('expected_version')).toBe('b'.repeat(64))
        expect(wrapper.emitted('saved')?.[0]).toEqual([saved])
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('reopens a paused preparation without Dropbox and requires an explicit release action', async () => {
    const work = { id: 12, student_distribution_version: 'a'.repeat(64), status: {
        student_distribution: { prepared_at: '2026-10-30T08:00:00Z', published_at: null,
            instructions: 'Aufgabe', student_ids: [1], file_ids: ['pdf'] },
        student_task_files: [{ id: 'pdf', name: 'Aufgabe.pdf' }],
    } }
    const get = vi.fn().mockResolvedValue({ data: { data: work } })
    const released = { ...work, status: { ...work.status, student_distribution: { ...work.status.student_distribution,
        published_at: '2026-10-31T08:00:00Z' } } }
    const post = vi.fn().mockResolvedValue({ data: { data: released } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { work, available: false }, global: { stubs } })
    try {
        expect(wrapper.get('button').text()).toBe('Pausierte Aufgaben verwalten')
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledOnce()
        expect(wrapper.text()).toContain('noch nicht für Schüler:innen freigegeben')
        expect(wrapper.text()).toContain('1 Aufgaben-PDF')
        expect(wrapper.findComponent({ name: 'v-textarea' }).exists()).toBe(false)
        expect(post).not.toHaveBeenCalled()
        await wrapper.findAll('button').find(button => button.text() === 'Jetzt freigeben')!.trigger('click')
        await flushPromises()
        const data = post.mock.calls[0][1] as FormData
        expect(data.get('release_now')).toBe('1')
        expect(data.get('retained_file_ids[]')).toBe('pdf')
        expect(wrapper.text()).toContain('Im Schülerbereich freigegeben')
        expect(wrapper.emitted('saved')).toHaveLength(1)
    } finally { wrapper.unmount() }
})

it('allows direct release of a paused former release and returns from editing without using unsaved changes', async () => {
    const work = { id: 12, student_distribution_version: 'a'.repeat(64), status: {
        student_distribution: { published_at: '2026-10-29T08:00:00Z', withdrawn_at: '2026-10-30T08:00:00Z',
            instructions: 'Aufgabe', student_ids: [1], file_ids: ['pdf'] },
        student_task_files: [{ id: 'pdf', name: 'Aufgabe.pdf' }],
    } }
    const get = vi.fn().mockResolvedValue({ data: { data: work } })
    const post = vi.fn().mockResolvedValue({ data: { data: work } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { work, available: false }, global: { stubs } })
    try {
        const click = (label: string) => wrapper.findAll('button').find(button => button.text() === label)!.trigger('click')
        expect(wrapper.get('button').text()).toBe('Pausierte Aufgaben verwalten')
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledOnce()
        expect(wrapper.text()).toContain('Die Aufgabenfreigabe ist pausiert')
        expect(wrapper.findAll('button').map(button => button.text())).toContain('Jetzt freigeben')
        await click('Vorbereitung bearbeiten')
        const editor = wrapper.getComponent({ name: 'v-textarea' })
        expect(editor.attributes('modelvalue')).toBe('Aufgabe')
        editor.vm.$emit('update:modelValue', 'Ungespeicherte Änderung')
        await click('Zurück zur Verwaltung')
        expect(wrapper.findComponent({ name: 'v-textarea' }).exists()).toBe(false)
        expect(post).not.toHaveBeenCalled()
        await click('Jetzt freigeben')
        await flushPromises()
        const data = post.mock.calls[0][1] as FormData
        expect(data.get('release_now')).toBe('1')
        expect(data.get('instructions')).toBe('Aufgabe')
        expect(data.get('retained_file_ids[]')).toBe('pdf')
        expect(wrapper.emitted('saved')).toHaveLength(1)
    } finally { wrapper.unmount() }
})

it('separates test resume, task release and countdown and refreshes stale choices before retrying', async () => {
    const paused = { id: 12, local_task_copy: true, student_distribution_version: 'a'.repeat(64), status: {
        student_distribution: { prepared_at: '2026-10-30T08:00:00Z', instructions: 'Aufgabe', student_ids: [1] },
        multiple_choice_tests: { all: { work_id: 12, test_id: 4 } },
    } }
    const released = { ...paused, student_distribution_version: 'b'.repeat(64), status: { ...paused.status,
        student_distribution: { ...paused.status.student_distribution, published_at: '2026-10-30T10:00:00Z' } } }
    const pausedTest = { id: 4, title: 'Test', status: 'published', start_mode: 'common', paused_at: '2026-10-30T09:00:00Z',
        has_open_attempts: true, management_action: 'resume', version: 'c'.repeat(64) }
    const resumedTest = { ...pausedTest, paused_at: null, management_action: null, version: 'd'.repeat(64) }
    const countdownTest = { ...resumedTest, management_action: 'countdown' }
    const scheduled = { ...resumedTest, starts_at: '2026-10-30T10:01:00Z', ends_at: '2026-10-30T10:08:00Z',
        preparation_locked: true, management_action: 'pause', version: 'e'.repeat(64), pause_version: 'f'.repeat(64) }
    const get = vi.fn().mockResolvedValueOnce({ data: { data: paused } })
        .mockResolvedValueOnce({ data: { data: { tests: [pausedTest] } } })
        .mockResolvedValueOnce({ data: { data: paused } })
        .mockResolvedValueOnce({ data: { data: { tests: [pausedTest] } } })
        .mockResolvedValueOnce({ data: { data: { tests: [countdownTest] } } })
    const post = vi.fn().mockRejectedValueOnce({ response: { status: 409, data: { message: 'Der MC-Test wurde inzwischen geändert.' } } })
        .mockResolvedValueOnce({ data: { data: { tests: [resumedTest] } } })
        .mockResolvedValueOnce({ data: { data: released } })
        .mockResolvedValueOnce({ data: { data: { tests: [scheduled], server_now: '2026-10-30T10:00:30Z' } } })
        .mockResolvedValueOnce({ data: { data: { tests: [{ ...scheduled, paused_at: '2026-10-30T10:01:15Z', management_action: 'resume' }] } } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { work: paused }, global: { stubs } })
    const button = (label: string) => wrapper.findAll('button').find(button => button.text() === label)!
    const click = async (label: string) => { await button(label).trigger('click'); await flushPromises() }
    try {
        await click('Pausierte Aufgaben verwalten')
        expect(post).not.toHaveBeenCalled()
        await click('MC-Test fortsetzen')
        expect(wrapper.text()).toContain('Der MC-Test wurde inzwischen geändert')
        expect(button('Jetzt freigeben').attributes('disabled')).toBeDefined()
        expect(button('MC-Test fortsetzen').attributes('disabled')).toBeDefined()
        await click('Stand neu laden')
        await click('MC-Test fortsetzen')
        expect(post.mock.calls[1]).toEqual(['/api/admin/teaching/course_works/12/student-distribution/test-action', {
            action: 'resume', test_id: 4, expected_version: 'a'.repeat(64), expected_test_version: 'c'.repeat(64),
        }])
        expect(wrapper.text()).toContain('Countdown erst nach Aufgabenfreigabe')
        expect(wrapper.emitted('saved')).toBeUndefined()
        await click('Jetzt freigeben')
        expect(post.mock.calls[2][0]).toBe('/api/admin/teaching/course_works/12/student-distribution')
        expect(wrapper.text()).toContain('Wartet auf den Countdown')
        await click('Gemeinsamen Countdown starten')
        expect(post.mock.calls[3][1]).toEqual({ action: 'countdown', test_id: 4, expected_version: 'b'.repeat(64), expected_test_version: 'd'.repeat(64) })
        expect(wrapper.text()).toContain('Countdown angesetzt')
        expect(button('Vorbereitung bearbeiten')).toBeUndefined()
        expect(wrapper.text()).not.toContain('Gemeinsamen Countdown starten')
        await click('MC-Test pausieren')
        expect(post.mock.calls[4][1]).toMatchObject({ action: 'pause', expected_test_version: 'f'.repeat(64) })
        expect(wrapper.text()).toContain('MC-Test pausiert')
        expect(button('MC-Test fortsetzen')).toBeDefined()
        expect(button('Vorbereitung bearbeiten')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('reloads management after a late save and keeps the editor closed for an externally scheduled countdown', async () => {
    const work = { id: 12, student_distribution_version: 'a'.repeat(64), status: {
        student_distribution: { published_at: '2026-10-30T10:00:00Z', instructions: 'Aufgabe', student_ids: [1] },
        multiple_choice_tests: { all: { work_id: 12, test_id: 4 } },
    } }
    const test = { id: 4, title: 'Gemeinsamer Test', status: 'published', start_mode: 'common', version: 'c'.repeat(64), management_action: 'countdown' }
    const get = vi.fn().mockResolvedValueOnce({ data: { data: work } })
        .mockResolvedValueOnce({ data: { data: { tests: [test] } } }).mockResolvedValueOnce({ data: { data: { tests: [test] } } })
        .mockResolvedValueOnce({ data: { data: work } }).mockResolvedValueOnce({ data: { data: { tests: [{ ...test, preparation_locked: true,
            starts_at: '2026-10-30T10:01:00Z', ends_at: '2026-10-30T10:08:00Z', management_action: 'pause' }], server_now: '2026-10-30T10:01:15Z' } } })
    const post = vi.fn().mockRejectedValue({ response: { status: 409, data: { message: 'Die Vorbereitung ist gesperrt.' } } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { work }, global: { stubs } })
    const click = async (label: string) => { await wrapper.findAll('button').find(button => button.text() === label)!.trigger('click'); await flushPromises() }
    try {
        await click('Freigabe verwalten')
        await click('Vorbereitung bearbeiten')
        expect(wrapper.findComponent({ name: 'v-textarea' }).exists()).toBe(true)
        await click('Freigabe aktualisieren')
        expect(wrapper.text()).toContain('Die Vorbereitung ist gesperrt')
        expect(wrapper.emitted('saved')).toBeUndefined()
        await click('Zurück zur Verwaltung')
        expect(wrapper.text()).toContain('MC-Test läuft')
        expect(wrapper.text()).not.toContain('Vorbereitung bearbeiten')
        expect(wrapper.findComponent({ name: 'v-textarea' }).exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('offers recovery of a legacy paused work with a running test and re-releases only saved content', async () => {
    const work = { id: 12, student_distribution_version: 'a'.repeat(64), status: {
        student_distribution: { published_at: '2026-10-30T10:00:00Z', withdrawn_at: '2026-10-30T10:01:20Z', instructions: 'Aufgabe', student_ids: [1] },
        multiple_choice_tests: { all: { work_id: 12, test_id: 4 } },
    } }
    const released = { ...work, status: { ...work.status, student_distribution: { ...work.status.student_distribution, withdrawn_at: null } } }
    const test = { id: 4, title: 'Gemeinsamer Test', status: 'published', start_mode: 'common', preparation_locked: true, version: 'c'.repeat(64), pause_version: 'd'.repeat(64),
        starts_at: '2026-10-30T10:01:00Z', ends_at: '2026-10-30T10:08:00Z', management_action: 'pause' }
    const paused = { ...test, paused_at: '2026-10-30T10:01:30Z', management_action: 'resume' }
    const get = vi.fn().mockResolvedValueOnce({ data: { data: work } })
        .mockResolvedValueOnce({ data: { data: { tests: [test], server_now: '2026-10-30T10:01:30Z' } } })
        .mockResolvedValue({ data: { data: { tests: [paused], server_now: '2026-10-30T10:01:30Z' } } })
    const post = vi.fn().mockResolvedValueOnce({ data: { data: work } }).mockResolvedValueOnce({ data: { data: released } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { work }, global: { stubs } })
    const click = async (label: string) => { await wrapper.findAll('button').find(button => button.text() === label)!.trigger('click'); await flushPromises() }
    try {
        await click('Pausierte Aufgaben verwalten')
        expect(wrapper.text()).toContain('MC-Test läuft')
        expect(post).not.toHaveBeenCalled()
        await click('MC-Pause nachholen')
        const pause = post.mock.calls[0][1] as FormData
        expect(pause.get('publish')).toBe('0')
        expect(pause.get('expected_test_versions[4]')).toBe('d'.repeat(64))
        expect(wrapper.text()).toContain('MC-Test fortsetzen')
        expect(wrapper.text()).not.toContain('MC-Pause nachholen')
        await click('Jetzt freigeben')
        const release = post.mock.calls[1][1] as FormData
        expect(release.get('release_saved')).toBe('1')
        expect(release.has('instructions')).toBe(false)
        expect(release.has('mc_tests')).toBe(false)
        expect(wrapper.text()).toContain('MC-Test pausiert')
    } finally { wrapper.unmount() }
})

it.each([
    { action: 'reset', label: 'Gesamten Test zurücksetzen', confirm: 'Alle Versuche zurücksetzen', explanation: 'aller 3 Versuche', done: 'Der gesamte MC-Test wurde zurückgesetzt.' },
    { action: 'stop', label: 'Test endgültig beenden', confirm: 'Test jetzt beenden', explanation: 'mit den gespeicherten Antworten', done: 'Der MC-Test wurde endgültig beendet.' },
    { action: 'delete', label: 'Test löschen', confirm: 'MC-Test endgültig löschen', explanation: 'bereits importierte Bewertungen', done: 'Der MC-Test wurde gelöscht.' },
])('confirms $action separately, supports cancellation and updates the management view', async ({ action, label, confirm, explanation, done }) => {
    const work = { id: 12, student_distribution_version: 'a'.repeat(64), status: {
        student_distribution: { published_at: '2026-10-30T10:00:00Z', withdrawn_at: '2026-10-30T10:01:20Z', instructions: 'Aufgabe', student_ids: [1] },
        multiple_choice_tests: { all: { test_id: 4 } },
    } }
    const test = { id: 4, title: 'Gemeinsamer Test', status: 'published', start_mode: 'common', paused_at: '2026-10-30T10:02:00Z',
        lifecycle_actions: ['reset', 'stop', 'delete'], management_action: 'resume', preparation_locked: true, version: 'c'.repeat(64), attempt_count: 3 }
    const deleted = { ...work, status: { student_distribution: work.status.student_distribution }, student_distribution_version: 'b'.repeat(64) }
    const get = vi.fn().mockResolvedValueOnce({ data: { data: work } }).mockResolvedValueOnce({ data: { data: { tests: [test] } } })
    const response = action === 'delete' ? { tests: [], work: deleted } : { tests: [{ ...test,
        ...(action === 'stop' ? { stopped_at: '2026-10-30T10:03:00Z', paused_at: null, management_action: null, lifecycle_actions: ['reset', 'delete'] } : {}),
    }] }
    const post = vi.fn().mockResolvedValue({ data: { data: response } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { work }, global: { stubs } })
    const click = async (text: string) => { await wrapper.findAll('button').find(button => button.text() === text)!.trigger('click'); await flushPromises() }
    try {
        await click('Pausierte Aufgaben verwalten')
        await click(label)
        expect(wrapper.text()).toContain(explanation)
        expect(post).not.toHaveBeenCalled()
        await click('Abbrechen')
        expect(post).not.toHaveBeenCalled()
        expect(wrapper.text()).toContain('Die Aufgabenfreigabe ist pausiert')
        await click(label)
        await click(confirm)
        expect(post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/12/student-distribution/test-action', {
            action, confirmed: true, test_id: 4, expected_version: 'a'.repeat(64), expected_test_version: 'c'.repeat(64),
        })
        expect(wrapper.text()).toContain(done)
        expect(wrapper.text()).toContain('Die Aufgabenfreigabe ist pausiert')
        if (action === 'delete') {
            expect(wrapper.text()).not.toContain('Gemeinsamer Test')
            expect(wrapper.emitted('saved')).toEqual([[deleted]])
        } else {
            expect(wrapper.text()).not.toContain('Vorbereitung bearbeiten')
        }
    } finally { wrapper.unmount() }
})

it('keeps a rejected release open and shows the server error without emitting saved', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { id: 12, description: 'Aufgabe', student_distribution_version: 'a'.repeat(64) } } })
    vi.stubGlobal('axios', { get, post: vi.fn().mockRejectedValue({ response: { status: 409, data: { message: 'Der MC-Test wurde inzwischen geändert.' } } }) })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work: { id: 12, title: 'Dateien', student_distribution_version: 'a'.repeat(64) } }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        await wrapper.findAll('button').find(button => button.text() === 'Pausiert vorbereiten')!.trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="alert"]').text()).toContain('Der MC-Test wurde inzwischen geändert')
        expect(wrapper.get('[role="alert"]').text()).toContain('Aufgabentext bei Bedarf kopieren')
        expect(wrapper.findComponent({ name: 'v-textarea' }).attributes('modelvalue')).toBe('Aufgabe')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
        expect(wrapper.emitted('saved')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('explains that an empty task cannot be published without sending a request', async () => {
    const post = vi.fn()
    const get = vi.fn().mockResolvedValue({ data: { data: { id: 12, student_distribution_version: 'a'.repeat(64) } } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work: { id: 12, title: 'Dateien' } }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        await wrapper.findAll('button').find(button => button.text() === 'Pausiert vorbereiten')!.trigger('click')
        expect(wrapper.get('[role="alert"]').text()).toContain('Aufgabentext eingeben oder ein Aufgaben-PDF auswählen')
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('does not offer publication when loading the current work fails', async () => {
    const post = vi.fn()
    const get = vi.fn().mockRejectedValue(new Error('offline'))
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work: { id: 12, title: 'Dateien' } }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="alert"]').text()).toContain('konnte nicht geladen werden')
        expect(wrapper.text()).toContain('Erneut laden')
        expect(wrapper.text()).not.toContain('Pausiert vorbereiten')
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('offers the assignment PDF from Angaben and submits it with the default text', async () => {
    const originalArrayBuffer = Object.getOwnPropertyDescriptor(Blob.prototype, 'arrayBuffer')
    Object.defineProperty(Blob.prototype, 'arrayBuffer', { configurable: true, value() {
        return new Promise((resolve, reject) => {
            const reader = new FileReader()
            reader.onload = () => resolve(reader.result)
            reader.onerror = () => reject(reader.error)
            reader.readAsArrayBuffer(this)
        })
    } })
    const pdf = new Blob(['%PDF-1.4 test'], { type: 'application/pdf' })
    const work = { id: 12, student_distribution_version: 'b'.repeat(64) }
    const get = vi.fn().mockImplementation((_url, options) => {
        if (!options) return Promise.resolve({ data: { data: work } })
        if (options.params.pdf) return Promise.resolve({ data: pdf })
        return Promise.resolve({ data: { entries: [{ id: 'id:task', type: 'file', name: 'Aufgabenstellung_E-Mails.pdf' }], cursor: null } })
    })
    const post = vi.fn().mockResolvedValue({ data: { data: work } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, date: '2026-10-20', work: { id: 12, title: 'E-Mails' } }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Aufgabenstellung_E-Mails.pdf')
        expect(wrapper.findComponent({ name: 'v-textarea' }).attributes('modelvalue')).toBe('Siehe Aufgaben-PDF.')
        expect(get.mock.calls[1][1].params).toMatchObject({ section: 'Angaben', exercise: 1, date: '2026-10-20' })
        await wrapper.findAll('button').find(button => button.text() === 'Pausiert vorbereiten')!.trigger('click')
        await vi.waitFor(() => expect(post).toHaveBeenCalledOnce())
        const data = post.mock.calls[0][1] as FormData
        expect(data.get('instructions')).toBe('Siehe Aufgaben-PDF.')
        expect((data.get('files[]') as File).name).toBe('Aufgabenstellung_E-Mails.pdf')
        expect(get.mock.calls.filter(call => call[1]?.params?.pdf)).toHaveLength(2)
    } finally {
        wrapper.unmount()
        if (originalArrayBuffer) Object.defineProperty(Blob.prototype, 'arrayBuffer', originalArrayBuffer)
        else Reflect.deleteProperty(Blob.prototype, 'arrayBuffer')
    }
})

it.each(['Angaben', 'Quellunterlagen'])('does not automatically choose between multiple PDFs in %s', async section => {
    const get = vi.fn().mockImplementation((_url, options) => !options
        ? Promise.resolve({ data: { data: { id: 12, student_distribution_version: 'a'.repeat(64) } } })
        : Promise.resolve({ data: { root: { name: section }, entries: [
            { id: 'id:one', type: 'file', name: section === 'Quellunterlagen' ? 'Leistungsfeststellung_A.pdf' : 'Aufgabenstellung_A.pdf' },
            { id: 'id:two', type: 'file', name: 'Aufgabenstellung_B.pdf' },
        ], cursor: null } }))
    vi.stubGlobal('axios', { get, post: vi.fn() })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work: { id: 12, title: 'Dateien' } }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('keine eindeutige Aufgabenstellungs-PDF')
        expect(wrapper.findComponent({ name: 'v-textarea' }).attributes('modelvalue')).toBe('')
        expect(get).toHaveBeenCalledTimes(2)
    } finally { wrapper.unmount() }
})

it('explains a missing assignment section without showing a raw HTTP error or publishing', async () => {
    const get = vi.fn().mockResolvedValueOnce({ data: { data: { id: 12, student_distribution_version: 'a'.repeat(64) } } })
        .mockRejectedValueOnce({ response: { status: 404 }, message: 'Request failed with status code 404' })
    const post = vi.fn()
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work: { id: 12, title: 'Dateien' } }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('kein Aufgabenbereich gefunden')
        expect(wrapper.text()).not.toContain('Request failed')
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('explains a blocked assignment PDF separately from an already published MC test and prevents empty preparation', async () => {
    const work = { id: 12, student_distribution_version: 'a'.repeat(64), status: {
        multiple_choice_tests: { all: { work_id: 12, test_id: 4 } },
    } }
    const get = vi.fn().mockResolvedValueOnce({ data: { data: work } })
        .mockResolvedValueOnce({ data: { data: { tests: [{ id: 4, title: 'MC Dateimanagement', status: 'published',
            start_mode: 'individual', duration_seconds: 420, mode_editable: false, version: 'c'.repeat(64) }] } } })
        .mockRejectedValueOnce({ response: { status: 409, data: { message: 'Mehrere Leistungsfeststellungsordner passen.' } } })
    const post = vi.fn()
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Aufgaben-PDF konnte nicht automatisch geladen werden')
        expect(wrapper.text()).toContain('Mehrere Leistungsfeststellungsordner passen.')
        expect(wrapper.text()).toContain('Bitte ein Aufgaben-PDF auswählen oder einen Aufgabentext eingeben.')
        expect(wrapper.text()).toContain('Bereits freigegeben')
        expect(wrapper.get('fieldset').attributes('disabled')).toBeDefined()
        expect(wrapper.get('input[value="individual"]').element.checked).toBe(true)
        await wrapper.findAll('button').find(button => button.text() === 'Pausiert vorbereiten')!.trigger('click')
        expect(wrapper.get('[role="alert"]').text()).toContain('Aufgabentext eingeben oder ein Aufgaben-PDF auswählen')
        expect(post).not.toHaveBeenCalled()
        expect(wrapper.emitted('saved')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('does not publish if the selected assignment PDF changes after the dialog opens', async () => {
    const originalArrayBuffer = Object.getOwnPropertyDescriptor(Blob.prototype, 'arrayBuffer')
    Object.defineProperty(Blob.prototype, 'arrayBuffer', { configurable: true, value() {
        return new Promise((resolve, reject) => {
            const reader = new FileReader()
            reader.onload = () => resolve(reader.result)
            reader.onerror = () => reject(reader.error)
            reader.readAsArrayBuffer(this)
        })
    } })
    let downloads = 0
    const get = vi.fn().mockImplementation((_url, options) => {
        if (!options) return Promise.resolve({ data: { data: { id: 12, student_distribution_version: 'a'.repeat(64) } } })
        if (options.params.pdf) return Promise.resolve({ data: new Blob([++downloads === 1 ? '%PDF-original' : '%PDF-changed'], { type: 'application/pdf' }) })
        return Promise.resolve({ data: { entries: [{ id: 'id:task', type: 'file', name: 'Aufgabenstellung_A.pdf' }], cursor: null } })
    })
    const post = vi.fn()
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work: { id: 12, title: 'Dateien' } }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        await wrapper.findAll('button').find(button => button.text() === 'Pausiert vorbereiten')!.trigger('click')
        await vi.waitFor(() => expect(wrapper.get('[role="alert"]').text()).toContain('Aufgabenstellung wurde geändert'))
        expect(post).not.toHaveBeenCalled()
        expect(wrapper.findComponent({ name: 'v-textarea' }).attributes('modelvalue')).toBe('Siehe Aufgaben-PDF.')
    } finally {
        wrapper.unmount()
        if (originalArrayBuffer) Object.defineProperty(Blob.prototype, 'arrayBuffer', originalArrayBuffer)
        else Reflect.deleteProperty(Blob.prototype, 'arrayBuffer')
    }
})

it('shows the linked test and sends the chosen start mode when updating an existing release', async () => {
    const work = { id: 12, student_distribution_version: 'b'.repeat(64), status: {
        student_distribution: { published_at: '2026-10-28T12:00:00Z', student_ids: [1], instructions: 'Siehe PDF' },
        multiple_choice_tests: { '2026-10-20': { work_id: 12, test_id: 4 } },
    } }
    const get = vi.fn().mockResolvedValueOnce({ data: { data: work } }).mockResolvedValue({ data: { data: { tests: [{
        id: 4, title: 'MC E-Mails', status: 'draft', start_mode: 'individual', duration_seconds: 420,
        individual_closes_at: '2026-11-03T22:59:00Z', mode_editable: true, version: 'c'.repeat(64),
    }] } } })
    const post = vi.fn().mockResolvedValue({ data: { data: work } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(get.mock.calls[1][0]).toBe('/api/admin/teaching/course_works/12/student-distribution-preview')
        expect(wrapper.text()).toContain('MC E-Mails')
        expect(wrapper.text()).toContain('Noch nicht freigegeben')
        await wrapper.findAll('button').find(button => button.text() === 'Vorbereitung bearbeiten')!.trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('7 Minuten')
        expect(wrapper.text()).toContain('Schlussfrist des Tests')
        await wrapper.get('input[value="common"]').setValue()
        expect(wrapper.text()).toContain('Die Lehrkraft startet den Countdown später.')
        await wrapper.findAll('button').find(button => button.text() === 'Freigabe aktualisieren')!.trigger('click')
        await flushPromises()
        const data = post.mock.calls[0][1] as FormData
        expect(JSON.parse(data.get('mc_tests') as string)).toEqual([{ id: 4, expected_version: 'c'.repeat(64), start_mode: 'common' }])
        expect(data.get('publish')).toBe('1')
    } finally { wrapper.unmount() }
})

it('shows both deadlines for a paused published test and prepares an explicit adoption without releasing it', async () => {
    const work = { id: 12, local_task_copy: true, student_distribution_version: 'a'.repeat(64), status: {
        student_distribution: { prepared_at: '2026-10-28T12:00:00Z', instructions: 'Aufgabe' },
        multiple_choice_tests: { all: { work_id: 12, test_id: 4 } },
    } }
    const get = vi.fn().mockResolvedValueOnce({ data: { data: work } }).mockResolvedValue({ data: { data: { tests: [{
        id: 4, title: 'Dateien', status: 'published', paused_at: '2026-10-29T09:00:00Z', start_mode: 'individual',
        duration_seconds: 420, individual_closes_at: '2026-11-20T23:59:00+01:00', work_closes_at: '2026-11-20T16:00:00+01:00',
        mode_editable: true, deadline_differs: true, can_adopt_final_deadline: true, version: 'c'.repeat(64),
    }] } } })
    const post = vi.fn().mockResolvedValue({ data: { data: work } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.get('h3').text()).toBe('Multiple-Choice-Test')
        await wrapper.findAll('button').find(button => button.text() === 'Vorbereitung bearbeiten')!.trigger('click')
        await flushPromises()
        expect(wrapper.get('fieldset').attributes('disabled')).toBeUndefined()
        expect(wrapper.text()).toContain('23:59 Uhr')
        expect(wrapper.text()).toContain('16:00 Uhr')
        await wrapper.get('input[value="common"]').setValue()
        expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false)
        await wrapper.get('input[value="individual"]').setValue()
        await wrapper.get('input[type="checkbox"]').setValue(true)
        await wrapper.findAll('button').find(button => button.text() === 'Vorbereitung speichern')!.trigger('click')
        await flushPromises()
        const data = post.mock.calls[0][1] as FormData
        expect(data.get('release_now')).toBe('0')
        expect(JSON.parse(data.get('mc_tests') as string)).toEqual([{
            id: 4, expected_version: 'c'.repeat(64), start_mode: 'individual', adopt_final_deadline: true,
        }])
    } finally { wrapper.unmount() }
})

it('keeps updating disabled if linked test details could not be loaded', async () => {
    const work = { id: 12, student_distribution_version: 'a'.repeat(64), status: {
        student_distribution: { published_at: '2026-10-28T12:00:00Z', student_ids: [1], instructions: 'Aufgabe' },
        multiple_choice_tests: { '2026-10-20': { work_id: 12, test_id: 4 } },
    } }
    const get = vi.fn().mockResolvedValueOnce({ data: { data: work } }).mockRejectedValueOnce(new Error('offline'))
    const post = vi.fn().mockResolvedValue({ data: { data: work } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('MC-Tests konnten nicht geladen werden')
        expect(wrapper.findAll('button').find(button => button.text() === 'Vorbereitung bearbeiten')!.attributes('disabled')).toBeDefined()
        expect(wrapper.findComponent({ name: 'v-textarea' }).exists()).toBe(false)
        expect(wrapper.text()).not.toContain('Freigabe pausieren')
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('retains a paused withdrawn test’s locked mode when preparing the updated release', async () => {
    const work = { id: 12, student_distribution_version: 'a'.repeat(64), status: {
        student_distribution: { published_at: '2026-10-28T12:00:00Z', student_ids: [1], instructions: 'Aufgabe' },
        multiple_choice_tests: { all: { work_id: 12, test_id: 4 } },
    } }
    const get = vi.fn().mockResolvedValueOnce({ data: { data: work } }).mockResolvedValue({ data: { data: { tests: [{
        id: 4, title: 'MC E-Mails', status: 'withdrawn', start_mode: 'individual', duration_seconds: 420,
        individual_closes_at: '2026-11-03T22:59:00Z', paused_at: '2026-10-29T09:00:00Z', mode_editable: false, version: 'd'.repeat(64),
    }] } } })
    const post = vi.fn().mockResolvedValue({ data: { data: work } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
        await flushPromises()
        await wrapper.findAll('button').find(button => button.text() === 'Vorbereitung bearbeiten')!.trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Die zurückgenommene Testfreigabe wird mit der Aufgabe erneut erteilt')
        expect(wrapper.text()).toContain('Der Test bleibt pausiert')
        expect(wrapper.get('fieldset').attributes('disabled')).toBeDefined()
        await wrapper.findAll('button').find(button => button.text() === 'Freigabe aktualisieren')!.trigger('click')
        await flushPromises()
        expect(JSON.parse((post.mock.calls[0][1] as FormData).get('mc_tests') as string))
            .toEqual([{ id: 4, expected_version: 'd'.repeat(64), start_mode: 'individual' }])
    } finally { wrapper.unmount() }
})

it('keeps test pause controls out of the release editor', async () => {
    const work = { id: 12, student_distribution_version: 'a'.repeat(64), status: {
        student_distribution: { published_at: '2026-10-28T12:00:00Z', student_ids: [1], instructions: 'Aufgabe' },
        multiple_choice_tests: { all: { work_id: 12, test_id: 4 } },
    } }
    const test = { id: 4, title: 'MC E-Mails', status: 'published', start_mode: 'individual', duration_seconds: 420,
        individual_closes_at: '2026-11-03T22:59:00Z', mode_editable: false, version: 'c'.repeat(64) }
    const get = vi.fn().mockResolvedValueOnce({ data: { data: work } })
        .mockResolvedValueOnce({ data: { data: { tests: [test] } } })
        .mockResolvedValueOnce({ data: { server_now: '2026-10-29T10:00:00Z', attempts: [{ status: 'in_progress', effective_ends_at: '2026-10-29T10:10:00Z' }] } })
        .mockResolvedValueOnce({ data: { data: { tests: [{ ...test, paused_at: '2026-10-29T10:00:00Z', version: 'd'.repeat(64) }] } } })
        .mockResolvedValueOnce({ data: { data: { tests: [test] } } })
        .mockResolvedValueOnce({ data: { server_now: '2026-10-29T10:01:00Z', attempts: [{ status: 'in_progress', effective_ends_at: '2026-10-29T10:11:00Z' }] } })
    const post = vi.fn().mockResolvedValue({ data: {} })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click'); await flushPromises()
        expect(wrapper.text()).not.toContain('MC-Test pausieren')
        expect(wrapper.text()).not.toContain('MC-Test fortsetzen')
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('does not offer pause for a test without a running attempt', async () => {
    const work = { id: 12, student_distribution_version: 'a'.repeat(64), status: {
        student_distribution: { published_at: '2026-10-28T12:00:00Z', student_ids: [1], instructions: 'Aufgabe' },
        multiple_choice_tests: { all: { work_id: 12, test_id: 4 } },
    } }
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValueOnce({ data: { data: work } })
        .mockResolvedValueOnce({ data: { data: { tests: [{ id: 4, title: 'Test', status: 'published', start_mode: 'individual',
            duration_seconds: 420, mode_editable: false, version: 'c'.repeat(64) }] } } })
        .mockResolvedValueOnce({ data: { server_now: '2026-10-29T10:00:00Z', attempts: [{ status: 'not_started' }] } }), post: vi.fn() })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click'); await flushPromises()
        expect(wrapper.text()).not.toContain('MC-Test pausieren')
        expect(wrapper.text()).not.toContain('MC-Test fortsetzen')
    } finally { wrapper.unmount() }
})
