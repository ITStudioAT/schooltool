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
    const post = vi.fn().mockResolvedValue({ data: { data: work } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { work, available: false }, global: { stubs } })
    try {
        expect(wrapper.get('button').text()).toBe('Pausierte Aufgaben verwalten')
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledOnce()
        expect(wrapper.text()).toContain('neue MC-Starts sind gesperrt')
        expect(wrapper.findAll('button').map(button => button.text())).toContain('Vorbereitung speichern')
        expect(post).not.toHaveBeenCalled()
        await wrapper.findAll('button').find(button => button.text() === 'Jetzt freigeben')!.trigger('click')
        await flushPromises()
        const data = post.mock.calls[0][1] as FormData
        expect(data.get('release_now')).toBe('1')
        expect(data.get('retained_file_ids[]')).toBe('pdf')
        expect(wrapper.emitted('saved')).toHaveLength(1)
    } finally { wrapper.unmount() }
})

it('prepares a withdrawn task paused again instead of immediately releasing it', async () => {
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
        expect(wrapper.get('button').text()).toBe('Erneut pausiert vorbereiten')
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledOnce()
        expect(wrapper.text()).toContain('Die Aufgabenfreigabe wurde zurückgenommen')
        expect(wrapper.findAll('button').map(button => button.text())).not.toContain('Jetzt freigeben')
        await wrapper.findAll('button').find(button => button.text() === 'Pausiert vorbereiten')!.trigger('click')
        await flushPromises()
        const data = post.mock.calls[0][1] as FormData
        expect(data.get('release_now')).toBe('0')
        expect(data.get('retained_file_ids[]')).toBe('pdf')
        expect(wrapper.emitted('saved')).toHaveLength(1)
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
    const get = vi.fn().mockResolvedValueOnce({ data: { data: work } }).mockResolvedValueOnce({ data: { data: { tests: [{
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
        expect(wrapper.text()).toContain('7 Minuten')
        expect(wrapper.text()).toContain('Schlussfrist:')
        await wrapper.get('input[value="common"]').setValue()
        expect(wrapper.text()).toContain('Die Lehrkraft startet den Countdown später.')
        await wrapper.findAll('button').find(button => button.text() === 'Freigabe aktualisieren')!.trigger('click')
        await flushPromises()
        const data = post.mock.calls[0][1] as FormData
        expect(JSON.parse(data.get('mc_tests') as string)).toEqual([{ id: 4, expected_version: 'c'.repeat(64), start_mode: 'common' }])
        expect(data.get('publish')).toBe('1')
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
        expect(wrapper.text()).toContain('MC-Testfreigabe bestehen')
        expect(wrapper.findAll('button').find(button => button.text() === 'Freigabe aktualisieren')!.attributes('disabled')).toBeDefined()
        expect(wrapper.text()).not.toContain('Freigabe zurücknehmen')
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('retains a paused withdrawn test’s locked mode when preparing the updated release', async () => {
    const work = { id: 12, student_distribution_version: 'a'.repeat(64), status: {
        student_distribution: { published_at: '2026-10-28T12:00:00Z', student_ids: [1], instructions: 'Aufgabe' },
        multiple_choice_tests: { all: { work_id: 12, test_id: 4 } },
    } }
    const get = vi.fn().mockResolvedValueOnce({ data: { data: work } }).mockResolvedValueOnce({ data: { data: { tests: [{
        id: 4, title: 'MC E-Mails', status: 'withdrawn', start_mode: 'individual', duration_seconds: 420,
        individual_closes_at: '2026-11-03T22:59:00Z', paused_at: '2026-10-29T09:00:00Z', mode_editable: false, version: 'd'.repeat(64),
    }] } } })
    const post = vi.fn().mockResolvedValue({ data: { data: work } })
    vi.stubGlobal('axios', { get, post })
    const wrapper = mount(WorkStudentDistribution, { props: { available: true, work }, global: { stubs } })
    try {
        await wrapper.get('button').trigger('click')
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
