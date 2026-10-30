import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components'
import WorkExerciseExport from '@/pages/admin/teaching/overview/components/WorkExerciseExport.vue'
import { clearWorkDropboxStates, loadWorkExercise, workDropboxStates } from '@/helpers/workDropbox'

const work = { id: 65, teaching_course_id: 2, title: 'E-Mails' }
const course = { id: 2, title: 'INF' }
const originalExecCommand = Object.getOwnPropertyDescriptor(document, 'execCommand')

function mountExport(get = vi.fn(), writeText = vi.fn().mockResolvedValue(undefined), status = vi.fn().mockResolvedValue({ data: { connected: true, module_link: { folder: { id: 'id:module' } }, exercise: { status: 'missing' } } }), currentWork: any = work,
    mcOptions: any = { available: true, tests: [{ id: 9, title: 'Vorhandener MC-Test', catalog_id: 3, catalog_title: 'Grundlagen' }], catalogs: [{ id: 3, title: 'Grundlagen' }], linked_test_id: 9 }) {
    vi.stubGlobal('axios', { get: vi.fn((url, options) => url.endsWith('/dropbox') ? status(url, options)
        : url.endsWith('/exercise-options') ? Promise.resolve({ data: { configuration: { locked: false, maximum_points: 5 },
            linked_mc_points: mcOptions.linked_test_id ? 2 : null, ...mcOptions,
            catalogs: (mcOptions.catalogs || []).map((catalog: any) => ({ reviewed_question_count: 10, ...catalog })) } }) : get(url, options)),
        post: vi.fn((url, payload, options) => url.endsWith('/prepare-exercise') ? get(url, payload, options) : status(url, payload, options)) })
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    return mount(WorkExerciseExport, { attachTo: document.body, props: { work: currentWork, course, date: '2026-10-20' }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } }), createVuetify({ components: { VBtn } })],
        components: { 'v-btn': VBtn }, stubs: { 'v-btn': false, VBtn: false,
            'v-text-field': { props: ['modelValue', 'label', 'disabled', 'type'], emits: ['update:modelValue'],
                template: '<label>{{ label }}<input :aria-label="label" :type="type" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value === \'\' ? null : type === \'number\' ? Number($event.target.value) : $event.target.value)" /></label>' },
            'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue" role="dialog"><slot /></div>' },
            'v-checkbox': { props: ['modelValue', 'label', 'disabled'], emits: ['update:modelValue'],
                template: '<label><input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.checked)" />{{ label }}</label>' },
            'v-radio-group': { props: ['modelValue', 'disabled'], emits: ['update:modelValue'],
                template: '<div><label><input type="radio" name="mode" value="existing" :checked="modelValue === \'existing\'" :disabled="disabled" @change="$emit(\'update:modelValue\', \'existing\')" />Bestehenden MC-Test verwenden</label><label><input type="radio" name="mode" value="new" :checked="modelValue === \'new\'" :disabled="disabled" @change="$emit(\'update:modelValue\', \'new\')" />Neuen MC-Test erstellen</label></div>' },
            'v-select': { props: ['modelValue', 'items', 'label', 'disabled', 'itemTitle'], emits: ['update:modelValue'],
                template: '<label>{{ label }}<select :aria-label="label" :value="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.value === \'\' ? null : Number($event.target.value))"><option value="">Bitte auswählen</option><option v-for="item in items" :key="item.id" :value="item.id">{{ item[itemTitle || \'title\'] }}</option></select></label>' },
        },
    } })
}

function button(wrapper: ReturnType<typeof mountExport>, text: string) {
    return wrapper.findAll('button, [role="button"]').find(item => item.attributes('aria-label') === text || item.text() === text || (text === 'Erneut prüfen' && item.text().startsWith(text)))!
}

afterEach(() => {
    clearWorkDropboxStates(); vi.restoreAllMocks(); vi.unstubAllGlobals(); document.body.innerHTML = ''
    if (originalExecCommand) Object.defineProperty(document, 'execCommand', originalExecCommand)
    else delete (document as any).execCommand
})

it('offers both variants from the red icon and waits for a concrete form decision before calling OpenAI', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text: 'Bestätigter Auftrag', context: { include_multiple_choice: false } },
        generation: { ready: true, id: 'generation-1', assignment_markdown: '# Aufgaben\nAufgabe prüfen.',
            criteria_markdown: '# Kriterien\n5 Punkte.', criteria: [{ title: 'Aufgabe', points: 5, description: 'Prüfen' }],
            sources: [{ id: 'id:source', path_lower: '/modul/assessment template/vorlage.md', revision: 'r1' }], warnings: [] } } })
    const wrapper = mountExport(get)
    try {
        await flushPromises()
        expect(wrapper.findAll('button').some(item => item.text() === 'Leistungsfeststellung erstellen')).toBe(false)
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('keydown', { key: 'Enter' })
        expect(button(wrapper, 'Übergabetext kopieren')).toBeDefined()
        expect(button(wrapper, 'Über OpenAI vorbereiten').attributes('disabled')).toBeDefined()
        expect(get).not.toHaveBeenCalled()
        await wrapper.get('select[aria-label="Formabzüge für Betreff, Anrede, Inhalt und Signatur?"]').setValue('0')
        await button(wrapper, 'Über OpenAI vorbereiten').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledWith('/api/admin/teaching/course_works/65/prepare-exercise', expect.objectContaining({
            execution: 'openai', email_form_policy: 'Keine Formabzüge für Betreff, Anrede, Inhalt und Signatur.', expected_test_id: null,
        }), expect.any(Object))
        expect(navigator.clipboard.writeText).not.toHaveBeenCalled()
        expect(wrapper.text()).toContain('Aufgabe prüfen.')
        expect(button(wrapper, 'Paket in Dropbox anlegen')).toBeDefined()
        expect(axios.post).toHaveBeenCalledTimes(1)
        await wrapper.get('select[aria-label="Formabzüge für Betreff, Anrede, Inhalt und Signatur?"]').setValue('2')
        expect(button(wrapper, 'Paket in Dropbox anlegen')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('writes only the reviewed generation on explicit confirmation and reports the verified folder', async () => {
    const preview = { data: { data: { work_id: 65, course_id: 2, roster_complete: true, participants_count: 1,
        text: 'Auftrag', context: { include_multiple_choice: false } }, generation: { id: 'generation-1', ready: true,
        assignment_markdown: 'Aufgabe', criteria_markdown: 'Kriterien', sources: [], warnings: [] } } }
    const folder = { id: 'id:created', name: 'Prüfung', path: '/modul/leistungsfeststellung/pruefung' }
    let created = false
    const status = vi.fn((url) => {
        if (url.endsWith('/store-exercise')) {
            created = true
            return Promise.resolve({ data: { data: { status: 'created', folder } } })
        }
        return Promise.resolve({ data: { connected: true, module_link: { folder: { id: 'id:module' } },
            exercise: created ? { status: 'found', assignment_status: 'ready', folder } : { status: 'missing' } } })
    })
    const wrapper = mountExport(vi.fn().mockResolvedValue(preview), undefined, status)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await wrapper.get('select[aria-label="Formabzüge für Betreff, Anrede, Inhalt und Signatur?"]').setValue('0')
        await button(wrapper, 'Über OpenAI vorbereiten').trigger('click')
        await flushPromises()
        await button(wrapper, 'Paket in Dropbox anlegen').trigger('click')
        await flushPromises()
        expect(axios.post).toHaveBeenCalledWith('/api/admin/teaching/course_works/65/store-exercise',
            { generation_id: 'generation-1', approved: true }, expect.any(Object))
        expect(wrapper.text()).toContain('Materialpaket in Dropbox bestätigt: /modul/leistungsfeststellung/pruefung')
        expect(button(wrapper, 'Paket in Dropbox anlegen')).toBeUndefined()
        expect(wrapper.emitted('exercise-status')?.at(-1)?.[0]).toMatchObject({ status: 'found', assignmentStatus: 'ready', folder })
    } finally { wrapper.unmount() }
})

it('keeps API clarification questions open without a Dropbox action', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text: 'Auftrag', context: { include_multiple_choice: false } },
        generation: { ready: false, questions: ['Welcher Formabzug gilt?'], warnings: [] } } })
    const wrapper = mountExport(get)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await wrapper.get('select[aria-label="Formabzüge für Betreff, Anrede, Inhalt und Signatur?"]').setValue('0')
        await button(wrapper, 'Über OpenAI vorbereiten').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Welcher Formabzug gilt?')
        expect(button(wrapper, 'Paket in Dropbox anlegen')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it.each(['sent', 'partial'])('does not offer a separate MC button after a confirmed %s participant dispatch', async (dispatchStatus) => {
    const status = vi.fn().mockResolvedValue({ data: { ...linkedExercise,
        exercise: { ...linkedExercise.exercise, email_dispatch: { status: dispatchStatus, confirmed_count: 1 } } } })
    const wrapper = mountExport(undefined, undefined, status)
    try {
        await flushPromises()
        expect(wrapper.findAll('button').some(item => item.text() === 'MC-Fragen einbeziehen')).toBe(false)
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(axios.post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('shows the linked MC test in an existing LF without a separate configuration button', async () => {
    const status = vi.fn().mockResolvedValue({ data: { ...linkedExercise,
        exercise: { ...linkedExercise.exercise, email_dispatch: { status: 'missing', confirmed_count: 0 } } } })
    const linkedWork = { ...work, status: { multiple_choice_tests: { '2026-10-20': { work_id: 65, test_id: 9 } } },
        multiple_choice_test_titles: { '9': 'Word – Dateimanagement' } }
    const wrapper = mountExport(undefined, undefined, status, linkedWork)
    try {
        await flushPromises()
        expect(wrapper.text()).toContain('MC-Test: Word – Dateimanagement')
        expect(wrapper.text()).not.toContain('Nr. 9')
        expect(wrapper.findAll('button').some(item => item.text() === 'MC-Fragen einbeziehen')).toBe(false)
        expect(axios.post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('shows only the MC test for the selected date even after the work has progressed', async () => {
    const linkedWork = { ...work, status: { folder_imported_at: '2026-10-13T12:54:00Z', multiple_choice_tests: {
        '2026-10-20': { work_id: 65, test_id: 9 },
        '2026-10-21': { work_id: 65, test_id: 10 },
    } }, multiple_choice_test_titles: { '9': 'Test A', '10': 'Test B' } }
    const wrapper = mountExport(undefined, undefined, undefined, linkedWork)
    try {
        await flushPromises()
        expect(wrapper.text()).toContain('MC-Test: Test A')
        expect(wrapper.text()).not.toContain('Test B')
        await wrapper.setProps({ date: '2026-10-21' })
        expect(wrapper.text()).toContain('MC-Test: Test B')
        expect(wrapper.text()).not.toContain('Test A')
    } finally { wrapper.unmount() }
})

it('blocks MC copying when the server detects a dispatch after the displayed folder check', async () => {
    const wrapper = mountExport(undefined, undefined, undefined, work, { available: true,
        tests: [{ id: 9, title: 'Test', catalog_id: 3 }], catalogs: [], linked_test_id: 9,
        configuration: { locked: true, maximum_points: 5, message: 'Aufgabenversand bereits begonnen.' } })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await wrapper.get('input[type="checkbox"]').setValue(true)
        await flushPromises()
        expect(wrapper.text()).toContain('Aufgabenversand bereits begonnen.')
        expect(button(wrapper, 'Übergabetext kopieren').attributes('disabled')).toBeDefined()
        expect(axios.post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('shows question catalogs and filters existing tests without silently changing the selected test', async () => {
    const get = vi.fn()
    const wrapper = mountExport(get, undefined, undefined, work, { available: true,
        tests: [
            { id: 9, title: 'Test A', status: 'draft', catalog_id: 3, catalog_title: 'Grundlagen' },
            { id: 10, title: 'Test B', status: 'published', catalog_id: 4, catalog_title: 'Netzwerke' },
        ], catalogs: [{ id: 3, title: 'Grundlagen' }], linked_test_id: 9 })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await wrapper.get('input[type="checkbox"]').setValue(true)
        await flushPromises()
        const catalogs = wrapper.get('select[aria-label="Fragenkatalog filtern"]')
        const tests = wrapper.get('select[aria-label="MC-Test auswählen"]')
        expect(catalogs.findAll('option').map(option => option.text())).toContain('Netzwerke')
        expect((catalogs.element as HTMLSelectElement).value).toBe('3')
        expect(tests.text()).toContain('Test A · Grundlagen')
        expect(tests.text()).not.toContain('Test B')
        expect(wrapper.text()).toContain('Fragenkatalog: Grundlagen')
        await catalogs.setValue('4')
        expect(tests.text()).toContain('Test B · Netzwerke')
        expect(tests.text()).not.toContain('Test A')
        expect((tests.element as HTMLSelectElement).value).toBe('')
        expect(button(wrapper, 'Übergabetext kopieren').attributes('disabled')).toBeDefined()
        await tests.setValue('10')
        expect(wrapper.text()).toContain('Fragenkatalog: Netzwerke')
        expect(button(wrapper, 'Übergabetext kopieren').attributes('disabled')).toBeUndefined()
        await catalogs.setValue('')
        expect(tests.text()).toContain('Test A · Grundlagen')
        expect(tests.text()).toContain('Test B · Netzwerke')
        expect((tests.element as HTMLSelectElement).value).toBe('10')
        expect(get).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('uses one explicit recheck for both folder and workflow status without refreshing status on mount', async () => {
    const saved = { ...work, simulation_collection: { simulation: true, complete: true } }
    const get = vi.fn().mockResolvedValue({ data: { data: saved } })
    const status = vi.fn().mockResolvedValueOnce({ data: linkedExercise }).mockResolvedValueOnce({ data: { ...linkedExercise, saved_work: saved } })
    const wrapper = mountExport(get, undefined, status)
    try {
        await wrapper.setProps({ checkProgress: true })
        await flushPromises()
        expect(get).not.toHaveBeenCalled()
        expect(wrapper.findAll('button').filter(button => button.text().startsWith('Erneut prüfen'))).toHaveLength(1)
        await button(wrapper, 'Erneut prüfen').trigger('click')
        await flushPromises()
        expect(axios.post).toHaveBeenCalledWith(expect.stringContaining('/dropbox/refresh'), expect.objectContaining({ date: '2026-10-20', refresh_collection: true }), expect.any(Object))
        expect(get).not.toHaveBeenCalled()
        expect(wrapper.emitted('deadline-saved')).toEqual([[{ data: saved }]])
        expect(wrapper.emitted('check-busy')).toEqual([[true], [false]])
        expect(wrapper.findAll('button').filter(button => button.text().startsWith('Erneut prüfen'))).toHaveLength(1)
    } finally { wrapper.unmount() }
})

it('keeps the saved deadline visible when the combined collection check fails', async () => {
    const saved = { ...work, finish_until_date: '2026-11-13' }
    const status = vi.fn().mockResolvedValueOnce({ data: linkedExercise }).mockResolvedValueOnce({ data: {
        ...linkedExercise, saved_work: saved, collection_error: { message: 'Einsammelprüfung fehlgeschlagen.' },
    } })
    const wrapper = mountExport(vi.fn(), undefined, status)
    try {
        await wrapper.setProps({ checkProgress: true }); await flushPromises()
        await button(wrapper, 'Erneut prüfen').trigger('click'); await flushPromises()
        expect(wrapper.emitted('deadline-saved')).toEqual([[{ data: saved }]])
        expect(wrapper.text()).toContain('Einsammelprüfung fehlgeschlagen.')
        expect(wrapper.emitted('exercise-status').at(-1)[0].status).toBe('unknown')
    } finally { wrapper.unmount() }
})

it('retains one status recheck for historical work without reopening its folder preparation', async () => {
    const saved = { ...work, status: { folder_imported_at: '2026-10-13T12:54:00Z' } }
    const get = vi.fn().mockResolvedValue({ data: { data: saved } })
    const status = vi.fn()
    vi.stubGlobal('axios', { get })
    const wrapper = mount(WorkExerciseExport, { props: { work: saved, course, checkProgress: true }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn })], stubs: { 'v-checkbox': true, 'v-btn': { template: '<button><slot /></button>' } },
    } })
    try {
        await wrapper.setProps({ checkProgress: true })
        await flushPromises()
        expect(wrapper.findAll('button').filter(button => button.text().startsWith('Erneut prüfen'))).toHaveLength(1)
        await button(wrapper, 'Erneut prüfen').trigger('click'); await flushPromises()
        expect(get).toHaveBeenCalledWith(expect.stringContaining('/course_works/65'), expect.objectContaining({ params: { refresh_collection: true, progress_id: expect.any(String), stream_progress: true }, onDownloadProgress: expect.any(Function) }))
        expect(wrapper.emitted('deadline-saved')).toEqual([[{ data: saved }]])
        expect(status).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('retains the last confirmed icon state while a fresh check is pending', async () => {
    let finish
    const preparation = { status: 'ready', prepared_at: '2026-10-24T16:00:00Z' }
    const confirmed = { ...linkedExercise, exercise: { ...linkedExercise.exercise, email_preparation: preparation } }
    const status = vi.fn().mockResolvedValueOnce({ data: confirmed })
        .mockImplementationOnce(() => new Promise(resolve => { finish = resolve }))
    const wrapper = mountExport(undefined, undefined, status)
    try {
        await flushPromises()
        await button(wrapper, 'Erneut prüfen').trigger('click')
        expect(wrapper.emitted('exercise-status').at(-1)[0]).toMatchObject({ status: 'found', folder: confirmed.exercise.folder,
            emailPreparation: preparation, checking: true })
        expect(wrapper.emitted('check-busy').at(-1)).toEqual([true])
        finish({ data: confirmed }); await flushPromises()
        expect(wrapper.emitted('check-busy').at(-1)).toEqual([false])
    } finally { wrapper.unmount() }
})

it('restores the confirmed exercise status on first and repeated opening after release', async () => {
    const confirmed = { ...linkedExercise, exercise: { ...linkedExercise.exercise, assignment_status: 'ready' } }
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: confirmed }) })
    await loadWorkExercise('7:2:65', work, '2026-10-20')
    clearWorkDropboxStates(false)
    const released = { ...work, status: { student_distribution: { published_at: '2026-10-29T08:00:00Z', student_ids: [1] } } }
    const wrapper = mountExport(undefined, undefined, vi.fn(), released)
    try {
        expect(wrapper.emitted('exercise-status')?.at(-1)?.[0]).toMatchObject({ status: 'found', assignmentStatus: 'ready', folder: confirmed.exercise.folder })
        await wrapper.setProps({ active: false })
        await wrapper.setProps({ active: true })
        expect(wrapper.emitted('exercise-status')?.at(-1)?.[0]).toMatchObject({ status: 'found', assignmentStatus: 'ready', folder: confirmed.exercise.folder })
    } finally { wrapper.unmount() }
})

it('does not restart exercise preparation for an existing imported work', async () => {
    const status = vi.fn()
    const wrapper = mountExport(vi.fn(), vi.fn(), status, { ...work, status: { folder_imported_at: '2026-10-13T12:54:00Z' } })
    try {
        await flushPromises()
        expect(wrapper.find('.exercise-action').exists()).toBe(false)
        expect(status).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('opens a short MC choice dialog with MC off and cancels without loading or copying a handoff', async () => {
    const get = vi.fn()
    const writeText = vi.fn()
    const wrapper = mountExport(get, writeText)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        expect((wrapper.get('input[type="checkbox"]').element as HTMLInputElement).checked).toBe(false)
        await wrapper.get('input[type="checkbox"]').setValue(true)
        await button(wrapper, 'Abbrechen').trigger('click')
        expect(get).not.toHaveBeenCalled()
        expect(writeText).not.toHaveBeenCalled()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        expect((wrapper.get('input[type="checkbox"]').element as HTMLInputElement).checked).toBe(false)
    } finally { wrapper.unmount() }
})

it.each([false, true])('saves the changed final deadline before preparing the exercise (MC: %s)', async includeMc => {
    const savedWork = { ...work, finish_until_date: '2026-10-25', finish_until_time: '13:30' }
    const status = vi.fn((url: string) => Promise.resolve({ data: url.endsWith('/refresh')
        ? { connected: true, module_link: { folder: { id: 'id:module' } }, exercise: { status: 'missing' }, saved_work: savedWork }
        : { connected: true, module_link: { folder: { id: 'id:module' } }, exercise: { status: 'missing' } } }))
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true, participants_count: 3,
        text: 'Auftrag mit neuer Frist', context: { include_multiple_choice: includeMc, ...(includeMc ? { multiple_choice_test: { test_id: 10 } } : {}) } } } })
    const wrapper = mountExport(get, undefined, status, { ...work, finish_until_date: '2026-10-20', finish_until_time: null },
        { available: true, tests: [], catalogs: [{ id: 3, title: 'Grundlagen' }], linked_test_id: null })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        expect((wrapper.get('input[aria-label="Endgültige Abgabefrist"]').element as HTMLInputElement).value).toBe('2026-10-20')
        await wrapper.get('input[aria-label="Endgültige Abgabefrist"]').setValue('2026-10-25')
        await wrapper.get('input[aria-label="Uhrzeit (optional)"]').setValue('13:30')
        if (includeMc) {
            await wrapper.get('input[type="checkbox"]').setValue(true)
            await flushPromises()
            await wrapper.get('select[aria-label="Fragenkatalog auswählen"]').setValue('3')
        }
        await button(wrapper, includeMc ? 'MC-Test erstellen und Übergabetext kopieren' : 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(axios.post).toHaveBeenCalledWith('/api/admin/teaching/course_works/65/dropbox/refresh', expect.objectContaining({
            finish_until_date: '2026-10-25', finish_until_time: '13:30', expected: { date: '2026-10-20', time: null },
        }), { timeout: 120000 })
        expect(wrapper.emitted('deadline-saved')).toEqual([[{ data: savedWork }]])
        expect(get).toHaveBeenCalledTimes(1)
        expect(navigator.clipboard.writeText).toHaveBeenCalledWith('Auftrag mit neuer Frist')
        await wrapper.setProps({ work: savedWork })
        await flushPromises()
        expect(wrapper.get('[role="dialog"]').text()).toContain('Übergabetext kopiert')
    } finally { wrapper.unmount() }
})

it('does not prepare an exercise if saving the deadline fails', async () => {
    const status = vi.fn((url: string) => url.endsWith('/refresh')
        ? Promise.reject({ response: { data: { message: 'Die Frist wurde inzwischen geändert.' } } })
        : Promise.resolve({ data: { connected: true, module_link: { folder: { id: 'id:module' } }, exercise: { status: 'missing' } } }))
    const get = vi.fn()
    const wrapper = mountExport(get, undefined, status, { ...work, finish_until_date: '2026-10-20' })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await wrapper.get('input[aria-label="Endgültige Abgabefrist"]').setValue('2026-10-25')
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Die Frist wurde inzwischen geändert.')
        expect(get).not.toHaveBeenCalled()
        expect(navigator.clipboard.writeText).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('opens the same MC dialog immediately after linking without saving the work first', async () => {
    const get = vi.fn()
    const status = vi.fn().mockResolvedValueOnce({ data: { connected: true, module_link: null, exercise: { status: 'missing' } } })
        .mockResolvedValue({ data: { connected: true, module_link: { folder: { id: 'id:just-linked' } }, exercise: { status: 'missing' } } })
    const wrapper = mountExport(get, undefined, status)
    try {
        await flushPromises()
        expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
        workDropboxStates.set('7:2:65', { connected: true, module_link: { folder: { id: 'id:just-linked' } }, folder: null, link_version: 'new-link' })
        await flushPromises()
        expect(wrapper.findAll('[role="button"][aria-label="Leistungsfeststellung erstellen"]')).toHaveLength(1)
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Multiple-Choice-Fragen einbeziehen', 'Übergabetext kopieren')
        expect(get).not.toHaveBeenCalled()
        await button(wrapper, 'Abbrechen').trigger('click')
        await wrapper.setProps({ work: { ...work, status: { multiple_choice_tests: { '2026-10-20': { work_id: 65, test_id: 9 } } } } })
        await flushPromises()
        expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeDefined()
    } finally { wrapper.unmount() }
})

it('requires an explicit MC selection and creates a draft only once while repeated copying reuses the prepared text', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true, participants_count: 3,
        text: 'Auftrag mit neuem MC-Test', context: { include_multiple_choice: true, multiple_choice_test: { test_id: 10 } } } } })
    const wrapper = mountExport(get, undefined, undefined, work, { available: true, tests: [], catalogs: [{ id: 3, title: 'Grundlagen' }], linked_test_id: null })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await wrapper.get('input[type="checkbox"]').setValue(true)
        await flushPromises()
        const action = 'MC-Test erstellen und Übergabetext kopieren'
        expect(button(wrapper, action).attributes('disabled')).toBeDefined()
        expect(axios.post).not.toHaveBeenCalled()
        await wrapper.get('select[aria-label="Fragenkatalog auswählen"]').setValue('3')
        expect(wrapper.findAll('input[type="number"]')).toHaveLength(4)
        expect((wrapper.get('input[aria-label="Anzahl der Fragen"]').element as HTMLInputElement).value).toBe('10')
        expect((wrapper.get('input[aria-label="MC-Bearbeitungszeit in Minuten"]').element as HTMLInputElement).value).toBe('7')
        expect((wrapper.get('input[aria-label="MC-Punkte"]').element as HTMLInputElement).value).toBe('2')
        expect(wrapper.find('input[aria-label="Maximale MC-Punkte"]').exists()).toBe(false)
        await wrapper.get('input[aria-label="Anzahl der Fragen"]').setValue('11')
        expect(button(wrapper, action).attributes('disabled')).toBeDefined()
        await wrapper.get('input[aria-label="Anzahl der Fragen"]').setValue('7')
        await wrapper.get('input[aria-label="MC-Punkte"]').setValue('0')
        expect(button(wrapper, action).attributes('disabled')).toBeDefined()
        await wrapper.get('input[aria-label="MC-Punkte"]').setValue('2')
        expect(wrapper.text()).toContain('2 Punkte MC + 3 Punkte übrige Aufgaben = 5 Punkte gesamt')
        expect(button(wrapper, action).attributes('disabled')).toBeUndefined()
        await button(wrapper, action).trigger('click'); await flushPromises()
        const payload = (axios.post as any).mock.calls[0][1]
        expect(payload).toMatchObject({ mc_mode: 'new', catalog_id: 3, question_count: 7, total_points: 2, mc_points: 2, other_points: 3, expected_maximum_points: 5, duration_minutes: 7, expected_test_id: null, date: '2026-10-20' })
        await button(wrapper, 'Kopiert').trigger('click'); await flushPromises()
        expect(axios.post).toHaveBeenCalledTimes(1)
        expect(navigator.clipboard.writeText).toHaveBeenCalledTimes(2)
    } finally { wrapper.unmount() }
})

it('allows 30 points for the remaining tasks and shows the different Schooltool scale', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true, participants_count: 3,
        text: 'Auftrag mit Punkten', context: { include_multiple_choice: true, multiple_choice_test: { test_id: 10 } } } } })
    const wrapper = mountExport(get, undefined, undefined, work, { available: true, tests: [], catalogs: [{ id: 3, title: 'Netzwerke' }], linked_test_id: null,
        configuration: { locked: false, maximum_points: 15 } })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await wrapper.get('input[type="checkbox"]').setValue(true)
        await flushPromises()
        await wrapper.get('select[aria-label="Fragenkatalog auswählen"]').setValue('3')
        await wrapper.get('input[aria-label="MC-Punkte"]').setValue('5')
        await wrapper.get('input[aria-label="Punkte übrige Aufgaben"]').setValue('30')
        expect(wrapper.text()).toContain('5 Punkte MC + 30 Punkte übrige Aufgaben = 35 Punkte gesamt')
        expect(wrapper.text()).toContain('Bewertungsskala in Schooltool beträgt 15 Punkte')
        await button(wrapper, 'MC-Test erstellen und Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(axios.post).toHaveBeenCalledWith(expect.stringContaining('/prepare-exercise'),
            expect.objectContaining({ mc_points: 5, other_points: 30, expected_maximum_points: 15 }), expect.any(Object))
    } finally { wrapper.unmount() }
})

it('uses the saved binding for a new test even when the previously linked test is no longer selectable', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true, participants_count: 3,
        text: 'Auftrag mit Ersatztest', context: { include_multiple_choice: true, multiple_choice_test: { test_id: 10 } } } } })
    const wrapper = mountExport(get, undefined, undefined, work, { available: true, tests: [],
        catalogs: [{ id: 3, title: 'Grundlagen' }], linked_test_id: null, expected_test_id: 6 })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await wrapper.get('input[type="checkbox"]').setValue(true)
        await flushPromises()
        await wrapper.get('select[aria-label="Fragenkatalog auswählen"]').setValue('3')
        await wrapper.get('input[aria-label="MC-Punkte"]').setValue('2')
        await button(wrapper, 'MC-Test erstellen und Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(axios.post).toHaveBeenCalledWith(expect.stringContaining('/prepare-exercise'),
            expect.objectContaining({ expected_test_id: 6, total_points: 2, mc_points: 2 }), expect.any(Object))
        expect(wrapper.text()).toContain('Übergabetext kopiert')
    } finally { wrapper.unmount() }
})

it('keeps MC copying disabled when the MC options cannot be loaded or the feature is unavailable', async () => {
    const wrapper = mountExport(undefined, undefined, undefined, work, { available: false, tests: [], catalogs: [], linked_test_id: null })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await wrapper.get('input[type="checkbox"]').setValue(true); await flushPromises()
        expect(button(wrapper, 'Übergabetext kopieren').attributes('disabled')).toBeDefined()
        expect(axios.post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it.each([false, true])('loads a fresh candidate export and copies only the chosen MC handoff (%s)', async mc => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    const text = `Verwende den Skill create-exercise. MC-Prüfungsteil: ${mc ? 'Ja' : 'Nein'}.`
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 3, text, context: { include_multiple_choice: mc, ...(mc ? { multiple_choice_test: { test_id: 9 } } : {}) } } } })
    const wrapper = mountExport(get, writeText)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        if (mc) await wrapper.get('input[type="checkbox"]').setValue(true)
        await flushPromises()
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        if (mc) expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/prepare-exercise', {
            mc_mode: 'existing', test_id: 9, expected_test_id: 9, mc_points: 2, other_points: 3, expected_maximum_points: 5, date: '2026-10-20', request_id: expect.any(String),
        }, { signal: expect.any(AbortSignal) })
        else expect(get).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/ai-export', {
            params: { purpose: 'exercise', include_mc: 0, date: '2026-10-20' }, signal: expect.any(AbortSignal),
        })
        expect(writeText).toHaveBeenCalledExactlyOnceWith(text)
        expect(wrapper.findAll('[role="status"]').some(status => status.text().includes('Übergabetext kopiert'))).toBe(true)
        expect(wrapper.findAllComponents(VBtn).find(item => item.text() === 'Kopiert')?.props()).toMatchObject({ prependIcon: 'mdi-check', color: 'success' })
        expect(button(wrapper, 'Ende')).toBeDefined()
        expect(button(wrapper, 'Abbrechen')).toBeUndefined()
        expect(wrapper.text()).toContain('3 bestätigte Prüfungskandidaten')
        await wrapper.get('input[type="checkbox"]').setValue(!mc)
        expect(button(wrapper, 'Übergabetext kopieren')).toBeDefined()
        expect(button(wrapper, 'Kopiert')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it.each([false, 'throws'])('keeps a completed handoff and selected text when both clipboard methods fail: %s', async legacyResult => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text: 'Vollständiger Übergabetext', context: { include_multiple_choice: false } } } })
    const wrapper = mountExport(get, vi.fn().mockRejectedValue(new Error('Denied')))
    const legacyCopy = vi.fn(() => {
        if (legacyResult === 'throws') throw new Error('Denied')
        return false
    })
    Object.defineProperty(document, 'execCommand', { configurable: true, value: legacyCopy })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        const field = wrapper.get('textarea[aria-label="Leistungsfeststellungsauftrag zum Kopieren"]').element as HTMLTextAreaElement
        expect(field.value).toBe('Vollständiger Übergabetext')
        expect(field.closest('details')?.open).toBe(true)
        expect(field.selectionStart).toBe(0)
        expect(field.selectionEnd).toBe(field.value.length)
        expect(button(wrapper, 'Kopiert')).toBeUndefined()
        expect(button(wrapper, 'Text kopieren')).toBeDefined()
        expect(button(wrapper, 'Ende')).toBeDefined()
        expect(button(wrapper, 'Abbrechen')).toBeUndefined()
        expect(wrapper.text()).toContain('Der Browser blockiert das Kopieren')
        expect(legacyCopy).toHaveBeenCalledWith('copy')
        legacyCopy.mockImplementation(() => true)
        await button(wrapper, 'Text kopieren').trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="status"][type="success"]').text()).toContain('in der Zwischenablage')
        expect(get).toHaveBeenCalledTimes(1)
    } finally { wrapper.unmount() }
})

it('copies automatically through the legacy method and allows a copy retry without creating another MC test', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text: 'Vollständiger MC-Auftrag', context: { include_multiple_choice: true, multiple_choice_test: { test_id: 9 } } } } })
    const wrapper = mountExport(get, vi.fn().mockRejectedValue(new Error('Denied')))
    const legacyCopy = vi.fn(() => {
        expect((document.activeElement as HTMLTextAreaElement).value).toBe('Vollständiger MC-Auftrag')
        return true
    })
    Object.defineProperty(document, 'execCommand', { configurable: true, value: legacyCopy })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await wrapper.get('input[type="checkbox"]').setValue(true)
        await flushPromises()
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="status"][type="success"]').text()).toContain('in der Zwischenablage')
        expect(button(wrapper, 'Abbrechen')).toBeUndefined()
        await button(wrapper, 'Kopiert').trigger('click')
        await flushPromises()
        expect(legacyCopy).toHaveBeenCalledTimes(2)
        expect(axios.post).toHaveBeenCalledTimes(1)
        await button(wrapper, 'Ende').trigger('click')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('does not export an unsaved work or copy an unconfirmed roster', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: false,
        participants_count: 0, text: 'invalid', context: { include_multiple_choice: false } } } })
    const writeText = vi.fn()
    const wrapper = mountExport(get, writeText)
    try {
        await wrapper.setProps({ work: { ...work, id: null } })
        expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
        await wrapper.setProps({ work })
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(writeText).not.toHaveBeenCalled()
        expect(wrapper.get('[role="alert"]').text()).toContain('nicht vollständig bestätigt')
    } finally { wrapper.unmount() }
})

it('discards a delayed candidate response after switching course', async () => {
    let finish: (value: unknown) => void = () => {}
    const get = vi.fn().mockImplementation(() => new Promise(resolve => { finish = resolve }))
    const writeText = vi.fn()
    const wrapper = mountExport(get, writeText)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await wrapper.setProps({ course: { id: 3, title: 'Anderer Kurs' } })
        finish({ data: { data: { work_id: 65, course_id: 2, roster_complete: true, participants_count: 1,
            text: 'Vorheriger Kurs', context: { include_multiple_choice: false } } } })
        await flushPromises()
        expect(writeText).not.toHaveBeenCalled()
        expect(wrapper.find('textarea').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

const linkedExercise = {
    connected: true, module_link: { module_id: 'module-a', folder: { id: 'id:module-a' } },
    exercise: { status: 'found', folder: { id: 'id:exercise', name: '2026-10-06_E-Mail_Digitale Grundbildung_3B_Gruppe-2' } },
}

it('shows the complete externally confirmed folder name instead of creation', async () => {
    const status = vi.fn().mockResolvedValue({ data: linkedExercise })
    const wrapper = mountExport(undefined, undefined, status)
    try {
        await flushPromises()
        expect(wrapper.get('[aria-label="Leistungsfeststellungsordner"]').text()).toBe(linkedExercise.exercise.folder.name)
        expect(wrapper.findAll('button').some(item => item.text() === 'Leistungsfeststellung erstellen')).toBe(false)
        expect(status).toHaveBeenCalledWith('/api/admin/teaching/course_works/65/dropbox', expect.objectContaining({ params: { include_exercise: 1, date: '2026-10-20' } }))
    } finally { wrapper.unmount() }
})

it.each([false, true])('keeps creation unavailable for an unconfirmed exercise (%s)', async linked => {
    const status = vi.fn().mockResolvedValue({ data: linked ? { ...linkedExercise, exercise: { status: 'unknown', folder: null, message: 'Mehrere passende Ordner.' } }
        : { ...linkedExercise, module_link: null } })
    const wrapper = mountExport(undefined, undefined, status)
    try {
        await flushPromises()
        expect(wrapper.find('[aria-label="Leistungsfeststellungsordner"]').exists()).toBe(false)
        expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
        if (linked) expect(wrapper.get('[role="status"]').text()).toContain('Mehrere passende Ordner')
    } finally { wrapper.unmount() }
})

it('reuses the last check on reopening, refreshes explicitly and clears it immediately when unlinked', async () => {
    workDropboxStates.set('7:2:65', { connected: true, module_link: linkedExercise.module_link, folder: null })
    const status = vi.fn().mockResolvedValue({ data: linkedExercise })
    const wrapper = mountExport(undefined, undefined, status)
    try {
        await flushPromises()
        expect(workDropboxStates.get('7:2:65')?.exercise?.folder?.id).toBe('id:exercise')
        await wrapper.setProps({ active: false })
        expect(wrapper.find('[aria-label="Leistungsfeststellungsordner"]').exists()).toBe(false)
        await wrapper.setProps({ active: true })
        await flushPromises()
        expect(status).toHaveBeenCalledTimes(1)
        expect(wrapper.find('[aria-label="Leistungsfeststellungsordner"]').exists()).toBe(true)
        await button(wrapper, 'Erneut prüfen').trigger('click')
        await flushPromises()
        expect(status).toHaveBeenCalledTimes(2)
        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/dropbox/refresh', {
            date: '2026-10-20', finish_until_date: null, finish_until_time: null, expected: { date: null, time: null },
            progress_id: expect.any(String), stream_progress: true,
        }, expect.objectContaining({ timeout: 120000, onDownloadProgress: expect.any(Function) }))
        status.mockResolvedValue({ data: { connected: true, module_link: null, exercise: { status: 'missing', folder: null } } })
        workDropboxStates.set('7:2:65', { connected: true, module_link: null, link_version: 'unlinked' })
        await flushPromises()
        expect(wrapper.find('[aria-label="Leistungsfeststellungsordner"]').exists()).toBe(false)
        expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('saves the edited internal deadline when checking and leaves other form changes out', async () => {
    const saved = { ...work, finish_until_date: '2026-10-24', finish_until_time: '12:30' }
    const dispatch = { status: 'sent', confirmed_count: 15, expected_count: 15, transport: 'mailpit' }
    const status = vi.fn().mockResolvedValueOnce({ data: linkedExercise }).mockResolvedValueOnce({
        data: { ...linkedExercise, exercise: { ...linkedExercise.exercise, email_dispatch: dispatch }, saved_work: saved, deadline_sync: { message: 'Interne Endfrist übernommen.' } },
    })
    const wrapper = mountExport(undefined, undefined, status, { ...work, finish_until_date: '2026-10-23', finish_until_time: null })
    try {
        await wrapper.setProps({ deadlineDraft: { date: '2026-10-24', time: '12:30' } })
        await flushPromises()
        await button(wrapper, 'Erneut prüfen').trigger('click')
        await flushPromises()
        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/dropbox/refresh', {
            date: '2026-10-20', finish_until_date: '2026-10-24', finish_until_time: '12:30',
            expected: { date: '2026-10-23', time: null },
            progress_id: expect.any(String), stream_progress: true,
        }, expect.objectContaining({ timeout: 120000, onDownloadProgress: expect.any(Function) }))
        expect(wrapper.emitted('deadline-saved')).toEqual([[{ data: saved }]])
        expect(wrapper.emitted('exercise-status')?.at(-1)?.[0]).toMatchObject({ status: 'found', emailDispatch: dispatch })
        expect(wrapper.emitted('check-busy')).toEqual([[true], [false]])
        expect(wrapper.text()).toContain('Interne Endfrist übernommen.')
    } finally { wrapper.unmount() }
})

it('reports a synchronization conflict and never publishes it as a saved deadline', async () => {
    const status = vi.fn().mockResolvedValueOnce({ data: linkedExercise }).mockRejectedValueOnce({
        response: { data: { message: 'Die Frist wurde inzwischen geändert.' } },
    })
    const wrapper = mountExport(undefined, undefined, status)
    try {
        await flushPromises()
        await button(wrapper, 'Erneut prüfen').trigger('click')
        await flushPromises()
        expect(wrapper.emitted('deadline-saved')).toBeUndefined()
        expect(wrapper.text()).toContain('Die Frist wurde inzwischen geändert.')
        expect(workDropboxStates.get('7:2:65')?.exercise?.status).toBe('unknown')
        expect(wrapper.emitted('check-busy')).toEqual([[true], [false]])
    } finally { wrapper.unmount() }
})

it('shows actual check progress and the completed server result', async () => {
    let finish: (value: unknown) => void = () => {}
    let notify = (_event: unknown) => {}
    const progress = { state: 'running', active: 'deadline', completed: ['connection', 'folder'],
        steps: ['connection', 'folder', 'deadline', 'verification'] }
    const get = vi.fn().mockResolvedValue({ data: progress })
    const status = vi.fn().mockResolvedValueOnce({ data: linkedExercise })
        .mockImplementationOnce((_url, _body, options) => { notify = options.onDownloadProgress; return new Promise(resolve => { finish = resolve }) })
    const wrapper = mountExport(get, undefined, status)
    try {
        await flushPromises()
        vi.useFakeTimers()
        await button(wrapper, 'Erneut prüfen').trigger('click')
        expect(wrapper.get('[aria-label="Fortschritt der Prüfung"]').text()).toContain('Prüfung wird gestartet')
        notify({ event: { target: { responseText: JSON.stringify({ type: 'progress', data: progress }) + '\n' } } })
        await vi.runAllTicks()
        expect(wrapper.get('[aria-label="Fortschritt der Prüfung"]').text()).toContain('Frist abgleichenWird geprüft')
        expect(wrapper.get('[aria-label="Fortschritt der Prüfung"]').text()).toContain('Arbeitsordner ermittelnErledigt')
        expect(wrapper.get('[aria-label="Fortschritt der Prüfung"]').text()).toContain('Aktuellen Status ermittelnAusstehend')
        const completed = { ...progress, state: 'done', active: null, completed: progress.steps }
        finish({ data: { ...linkedExercise, progress: completed } })
        await vi.runAllTicks()
        await flushPromises()
        expect(wrapper.get('[aria-label="Fortschritt der Prüfung"]').text()).toContain('Prüfung abgeschlossen')
        expect(wrapper.get('[aria-label="Fortschritt der Prüfung"]').text()).toContain('Aktuellen Status ermittelnErledigt')
        expect(get).toHaveBeenCalledTimes(0)
        await vi.advanceTimersByTimeAsync(2000)
        expect(get).toHaveBeenCalledTimes(0)
    } finally { wrapper.unmount(); vi.useRealTimers() }
})

it('stops polling when the check view closes', async () => {
    const get = vi.fn().mockResolvedValue({ data: { state: 'running', steps: ['connection'], completed: [], active: 'connection' } })
    const status = vi.fn().mockResolvedValueOnce({ data: linkedExercise }).mockImplementationOnce(() => new Promise(() => {}))
    const wrapper = mountExport(get, undefined, status)
    try {
        await flushPromises()
        vi.useFakeTimers()
        await button(wrapper, 'Erneut prüfen').trigger('click')
        await vi.advanceTimersByTimeAsync(200)
        expect(get).toHaveBeenCalledTimes(0)
        wrapper.unmount()
        await vi.advanceTimersByTimeAsync(2000)
        expect(get).toHaveBeenCalledTimes(0)
    } finally { wrapper.unmount(); vi.useRealTimers() }
})

it('ignores an old folder response after changing the module and reports failed fresh reads', async () => {
    let finish: (value: unknown) => void = () => {}
    const status = vi.fn().mockImplementationOnce(() => new Promise(resolve => { finish = resolve }))
        .mockRejectedValue(new Error('Offline'))
    const wrapper = mountExport(undefined, undefined, status)
    try {
        workDropboxStates.set('7:2:65', { connected: true, module_link: { module_id: 'module-b', folder: { id: 'id:module-b' } } })
        await flushPromises()
        finish({ data: linkedExercise })
        await flushPromises()
        expect(wrapper.find('[aria-label="Leistungsfeststellungsordner"]').exists()).toBe(false)
        expect(wrapper.get('[role="status"]').text()).toContain('nicht geprüft')
        expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
    } finally { wrapper.unmount() }
})


it.each([false, true])('hides creation while the initial folder check is pending (found: %s)', async found => {
    let finish: (value: unknown) => void = () => {}
    const status = vi.fn().mockImplementation(() => new Promise(resolve => { finish = resolve }))
    const wrapper = mountExport(undefined, undefined, status)
    try {
        expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
        expect(wrapper.get('[role="status"]').text()).toContain('wird geprüft')
        finish({ data: found ? linkedExercise : { connected: false } })
        await flushPromises()
        expect(wrapper.text()).not.toContain('wird geprüft')
        expect(wrapper.find('[aria-label="Leistungsfeststellungsordner"]').exists()).toBe(found)
        expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('keeps creation hidden when an old check finishes before the current module check', async () => {
    const finishes: Array<(value: unknown) => void> = []
    const status = vi.fn().mockImplementation(() => new Promise(resolve => { finishes.push(resolve) }))
    const wrapper = mountExport(undefined, undefined, status)
    try {
        workDropboxStates.set('7:2:65', { connected: true, module_link: { module_id: 'module-b', folder: { id: 'id:module-b' } } })
        await flushPromises()
        expect(status).toHaveBeenCalledTimes(2)
        finishes[0]({ data: linkedExercise })
        await flushPromises()
        expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
        expect(wrapper.get('[role="status"]').text()).toContain('wird geprüft')
        finishes[1]({ data: { connected: false } })
        await flushPromises()
        expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
        expect(wrapper.text()).not.toContain('wird geprüft')
    } finally { wrapper.unmount() }
})

it.each(['found', 'missing', 'unknown'])('publishes the checked exercise state for the matching dialog (%s)', async statusValue => {
    let finish: (value: unknown) => void = () => {}
    const status = vi.fn().mockImplementation(() => new Promise(resolve => { finish = resolve }))
    const wrapper = mountExport(undefined, undefined, status)
    try {
        expect(wrapper.emitted('exercise-status')?.[0][0]).toMatchObject({ userId: 7, workId: 65, courseId: 2, date: '2026-10-20', status: 'checking' })
        finish({ data: statusValue === 'found' ? linkedExercise : { exercise: { status: statusValue } } })
        await flushPromises()
        expect(wrapper.emitted('exercise-status')?.at(-1)?.[0]).toMatchObject({ status: statusValue })
    } finally { wrapper.unmount() }
})
