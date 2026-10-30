import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter, RouterView } from 'vue-router'
import axios from 'axios'
import MultipleChoiceWorkspace from '@/pages/admin/teachingTools/MultipleChoiceWorkspace.vue'
import QuestionEditor from '@/pages/admin/teachingTools/QuestionEditor.vue'
import { detectImportFormat, questionProblems, viennaDateTime, viennaDateTimeIso } from '@/pages/admin/teachingTools/multipleChoiceTeacher'

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() } }))

it('requires multiple correct answers for a multiple-selection question', () => {
    expect(questionProblems({ prompt: 'Frage', type: 'multiple', options: [{ id: 'a', text: 'A' }, { id: 'b', text: 'B' }], correct_option_ids: ['a'] }))
        .toContain('Bei Mehrfachauswahl mindestens zwei richtige Antworten festlegen.')
})

const button = defineComponent({
    props: ['disabled'],
    setup(props, { slots, attrs }) {
        return () => h('button', { ...attrs, type: attrs.type ?? 'button', disabled: props.disabled }, slots.default?.())
    },
})
const field = defineComponent({
    props: ['modelValue', 'label', 'errorMessages'],
    emits: ['update:modelValue'],
    setup(props, { emit, attrs }) {
        return () => h('div', [h('input', {
            ...attrs,
            'aria-label': props.label,
            value: props.modelValue,
            onInput: (event) => emit('update:modelValue', event.target.value),
        }), props.errorMessages ? h('p', { role: 'alert' }, props.errorMessages) : null])
    },
})
const textarea = defineComponent({
    props: ['modelValue', 'label'],
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        return () => h('textarea', {
            'aria-label': props.label, value: props.modelValue,
            onInput: (event) => emit('update:modelValue', event.target.value),
        })
    },
})
const select = defineComponent({
    props: ['modelValue', 'items', 'itemTitle', 'itemValue', 'label'],
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        return () => h('select', {
            'aria-label': props.label,
            value: props.modelValue,
            onChange: (event) => {
                const selected = props.items.find((item) => String(item[props.itemValue ?? 'value']) === event.target.value)
                emit('update:modelValue', selected?.[props.itemValue ?? 'value'])
            },
        }, [h('option', { value: '' }, 'Auswählen'), ...props.items.map((item) => h('option', { value: item[props.itemValue ?? 'value'] }, item[props.itemTitle ?? 'title']))])
    },
})
const datePicker = defineComponent({
    props: ['modelValue'],
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        return () => h('input', { type: 'date', 'aria-label': 'Datum der Schlussfrist',
            value: props.modelValue instanceof Date ? `${props.modelValue.getFullYear()}-${String(props.modelValue.getMonth() + 1).padStart(2, '0')}-${String(props.modelValue.getDate()).padStart(2, '0')}` : '',
            onInput: event => emit('update:modelValue', new Date(`${event.target.value}T00:00:00`)),
        })
    },
})
const dialog = defineComponent({
    props: ['modelValue'],
    setup(props, { slots }) {
        return () => props.modelValue ? h('div', { role: 'dialog' }, slots.default?.()) : null
    },
})
const wrappers = []
const filePond = defineComponent({
    name: 'FilePond',
    props: ['disabled', 'instantUpload', 'allowProcess', 'allowMultiple', 'server'],
    emits: ['updatefiles'],
    setup() {
        return () => h('div', { class: 'file-pond-stub' })
    },
})

function validQuestion(overrides = {}) {
    return { id: 'question-a', prompt: 'Welche Antwort ist richtig?', type: 'single', options: [{ id: 'a', text: 'Richtig' }, { id: 'b', text: 'Falsch' }], correct_option_ids: ['a'], active: true, reviewed: true, ...overrides }
}

function tenQuestions() {
    return Array.from({ length: 10 }, (_, index) => validQuestion({ id: `question-${index}`, prompt: `Frage ${index}` }))
}

function renderWorkspace(data = {}, monitor = {}, initialLocation = '/admin/teaching-tools/multiple-choice', attach = false) {
    vi.mocked(axios.get).mockImplementation(async (url) => {
        if (String(url).endsWith('/multiple-choice')) {
            return { data: { available: true, catalogs: [], tests: [], ...data } }
        }
        if (String(url).endsWith('/invitations')) {
            return { data: { recipients: [{ attempt_id: 1, student_name: 'Test Person', email: 'test@example.invalid', invited_at: null }] } }
        }
        return { data: { test: { id: 7, catalog_id: 4, title: 'Test A', question_count: 1, total_points: 10, duration_seconds: 600, lead_seconds: 30 }, attempts: [], ...monitor } }
    })
    const history = createMemoryHistory()
    history.replace(initialLocation)
    const router = createRouter({ history, routes: [{ path: '/admin/teaching-tools/multiple-choice', component: MultipleChoiceWorkspace }] })
    const wrapper = mount(RouterView, { ...(attach ? { attachTo: document.body } : {}), global: { plugins: [router], stubs: { FilePond: filePond, 'v-btn': button, 'v-text-field': field, 'v-textarea': textarea, 'v-select': select, 'v-dialog': dialog, 'v-date-picker': datePicker, 'v-sheet': { template: '<div><slot /></div>' } } } })
    wrapper.router = router
    wrappers.push(wrapper)
    return wrapper
}

async function clickText(wrapper, text) {
    const target = wrapper.findAll('button').find((item) => item.text() === text)
    expect(target, `Button ${text} exists`).toBeDefined()
    await target.trigger('click')
    await flushPromises()
}

describe('Test-specific navigation', () => {
    const base = '/admin/teaching-tools/multiple-choice'
    const data = { catalogs: [{ id: 4, title: 'E-Mails', questions: [validQuestion()] }], tests: [{ id: 7, catalog_id: 4, title: 'E-Mails – 3B1', status: 'published' }] }
    const panels = ['Prüfung steuern', 'Zugang und Einladungen', 'Ergebnisse und Lösungen', 'Schülerübersicht', 'Testeinstellungen']

    function assertOnlyPanel(wrapper, label) {
        for (const panel of panels) expect(wrapper.find(`section[aria-label="${panel}"]`).isVisible(), panel).toBe(panel === label)
    }

    it('shows one area at a time and restores the selected submenu through back forward and a fresh URL', async () => {
        const wrapper = renderWorkspace(data, {}, `${base}?catalog=4&view=test&test=7`, true)
        await flushPromises()
        assertOnlyPanel(wrapper, 'Prüfung steuern')
        const submenu = () => wrapper.find('nav[aria-label="Testbereiche"]')
        await submenu().findAll('button')[1].trigger('click')
        await flushPromises()
        assertOnlyPanel(wrapper, 'Zugang und Einladungen')
        expect(wrapper.router.currentRoute.value.query.pane).toBe('access')
        await submenu().findAll('button')[3].trigger('click')
        await flushPromises()
        assertOnlyPanel(wrapper, 'Schülerübersicht')
        await submenu().findAll('button')[4].trigger('click')
        await flushPromises()
        assertOnlyPanel(wrapper, 'Testeinstellungen')
        const settingsUrl = wrapper.router.currentRoute.value.fullPath
        wrapper.router.back()
        await flushPromises()
        assertOnlyPanel(wrapper, 'Schülerübersicht')
        wrapper.router.forward()
        await flushPromises()
        assertOnlyPanel(wrapper, 'Testeinstellungen')
        const reloaded = renderWorkspace(data, {}, settingsUrl, true)
        await flushPromises()
        assertOnlyPanel(reloaded, 'Testeinstellungen')
        expect(axios.post).not.toHaveBeenCalled()
        expect(axios.patch).not.toHaveBeenCalled()
    })

    it('preserves legacy result and attempt links and does not offer a test submenu in the overview', async () => {
        const results = renderWorkspace(data, {}, `${base}?catalog=4&view=results&test=7`, true)
        await flushPromises()
        assertOnlyPanel(results, 'Ergebnisse und Lösungen')
        const attempt = renderWorkspace(data, { attempts: [{ id: 12, status: 'submitted', started_at: '2026-10-19T08:00:00Z', student_name: 'Fixture', score: 10 }] }, `${base}?catalog=4&view=results&test=7&attempt=12`, true)
        await flushPromises()
        assertOnlyPanel(attempt, 'Schülerübersicht')
        await clickText(attempt, 'Schließen')
        assertOnlyPanel(attempt, 'Schülerübersicht')
        expect(attempt.router.currentRoute.value.query.pane).toBe('students')
        const overview = renderWorkspace(data, {}, `${base}?catalog=4&view=tests`)
        await flushPromises()
        expect(overview.find('nav[aria-label="Testbereiche"]').exists()).toBe(false)
    })

    it('retains read-only settings after a started test in the settings area', async () => {
        const wrapper = renderWorkspace(data, { can_edit_settings: false }, `${base}?catalog=4&view=test&test=7&pane=settings`, true)
        await flushPromises()
        assertOnlyPanel(wrapper, 'Testeinstellungen')
        await clickText(wrapper, 'Einstellungen bearbeiten')
        expect(wrapper.find('fieldset.mc-settings-fields').attributes('disabled')).toBeDefined()
        expect(axios.patch).not.toHaveBeenCalled()
    })
})

async function submitCatalogTitle(wrapper) {
    await wrapper.find('form').trigger('submit')
    await flushPromises()
}

async function chooseFile(wrapper, file) {
    wrapper.findComponent({ name: 'FilePond' }).vm.$emit('updatefiles', [{ file }])
    await flushPromises()
}

async function chooseTemplate(wrapper, text) {
    await chooseFile(wrapper, new File([text], 'questions.txt', { type: 'text/plain' }))
}

async function openCatalogTests(wrapper) {
    await clickText(wrapper, 'Katalog öffnen')
    const target = wrapper.findAll('button').find((item) => item.text().startsWith('Tests zu diesem Katalog'))
    await target.trigger('click')
    await flushPromises()
}

async function openExistingTest(wrapper) {
    await openCatalogTests(wrapper)
    await clickText(wrapper, 'Test öffnen')
}

async function editCatalog(wrapper) {
    await clickText(wrapper, 'Katalog öffnen')
    await clickText(wrapper, 'Bearbeiten')
}

async function startNewTest(wrapper) {
    const target = wrapper.findAll('button').find((item) => item.text().startsWith('Tests zu diesem Katalog'))
    await target.trigger('click')
    await flushPromises()
    await clickText(wrapper, 'Neuen Test anlegen')
}

beforeEach(() => {
    vi.mocked(axios.get).mockReset()
    vi.mocked(axios.post).mockReset()
    vi.mocked(axios.patch).mockReset()
    vi.mocked(axios.delete).mockReset()
})
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
    vi.useRealTimers()
})

it('releases an existing draft only after the separate confirmation', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Erstellter Test', status: 'draft', question_count: 10, lead_seconds: 30 }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: tenQuestions() }], tests: [test] }, { test }, '/admin/teaching-tools/multiple-choice?catalog=4&view=test&test=7')
    await flushPromises()
    expect(wrapper.text()).toContain('noch nicht freigegeben')
    expect(wrapper.text()).not.toContain('Countdown starten (30 s)')
    await clickText(wrapper, 'Test freigeben')
    expect(axios.post).not.toHaveBeenCalled()
    await wrapper.find('[role="dialog"]').findAll('button').find(button => button.text() === 'Zurück').trigger('click')
    expect(axios.post).not.toHaveBeenCalled()
    await clickText(wrapper, 'Test freigeben')
    vi.mocked(axios.post).mockResolvedValueOnce({ data: { test: { ...test, status: 'published' } } })
    vi.mocked(axios.get).mockImplementation(async url => ({ data: String(url).endsWith('/multiple-choice')
        ? { available: true, catalogs: [{ id: 4, title: 'Katalog', questions: tenQuestions() }], tests: [{ ...test, status: 'published' }] }
        : { test: { ...test, status: 'published' }, attempts: [] } }))
    await wrapper.find('[role="dialog"]').findAll('button').find(button => button.text() === 'Test freigeben').trigger('click')
    await flushPromises()
    expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/tests/7/publish')
    expect(wrapper.text()).toContain('Countdown starten (30 s)')
})

it('withdraws test access only after confirmation and offers release again', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', status: 'published', start_mode: 'individual', question_count: 10, lead_seconds: 30, duration_seconds: 600, closes_at: '2027-01-15T12:00:00Z' }
    const testData = { catalogs: [{ id: 4, title: 'Katalog', questions: tenQuestions() }], tests: [test] }
    const wrapper = renderWorkspace(testData, { test, attempts: [] }, '/admin/teaching-tools/multiple-choice?catalog=4&view=test&test=7', true)
    await flushPromises()
    await clickText(wrapper, 'Testfreigabe zurücknehmen')
    expect(axios.post).not.toHaveBeenCalled()
    expect(wrapper.get('[role="dialog"]').text()).toContain('Bearbeitungszeiten und Schlussfristen laufen weiter')
    await wrapper.get('[role="dialog"]').findAll('button').find(button => button.text() === 'Abbrechen').trigger('click')
    expect(axios.post).not.toHaveBeenCalled()
    await clickText(wrapper, 'Testfreigabe zurücknehmen')
    const withdrawn = { ...test, status: 'withdrawn' }
    vi.mocked(axios.post).mockResolvedValueOnce({ data: { test: withdrawn, attempts: [] } })
    vi.mocked(axios.get).mockImplementation(async url => ({ data: String(url).endsWith('/multiple-choice')
        ? { available: true, catalogs: testData.catalogs, tests: [withdrawn] }
        : { test: withdrawn, attempts: [] } }))
    await wrapper.get('[role="dialog"]').findAll('button').find(button => button.text() === 'Testfreigabe zurücknehmen').trigger('click')
    await flushPromises()
    expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/tests/7/withdraw')
    expect(wrapper.text()).toContain('Testfreigabe zurückgenommen')
    expect(wrapper.text()).toContain('Test wieder freigeben')
    expect(wrapper.text()).not.toContain('Countdown starten')
    expect(wrapper.text()).not.toContain('Alle pausieren')
    await clickText(wrapper, 'Test wieder freigeben')
    expect(wrapper.get('[role="dialog"]').text()).toContain('Bearbeitungszeiten und Schlussfristen bleiben unverändert')
    expect(wrapper.get('[role="dialog"]').text()).not.toContain('Danach können die Teilnehmer selbst starten')
})

it('keeps a paused test described as paused after withdrawal and on release', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', status: 'published', start_mode: 'individual', paused_at: '2026-10-29T11:00:00Z', question_count: 10, duration_seconds: 600, closes_at: '2027-01-15T12:00:00Z' }
    const data = { catalogs: [{ id: 4, title: 'Katalog', questions: tenQuestions() }], tests: [test] }
    const wrapper = renderWorkspace(data, { test, attempts: [] }, '/admin/teaching-tools/multiple-choice?catalog=4&view=test&test=7', true)
    await flushPromises()
    await clickText(wrapper, 'Testfreigabe zurücknehmen')
    expect(wrapper.get('[role="dialog"]').text()).toContain('Der Test bleibt pausiert und die Zeit steht still.')
    expect(wrapper.get('[role="dialog"]').text()).not.toContain('Fristen laufen weiter')
    const withdrawn = { ...test, status: 'withdrawn' }
    vi.mocked(axios.post).mockResolvedValueOnce({ data: { test: withdrawn, attempts: [] } })
    vi.mocked(axios.get).mockImplementation(async url => ({ data: String(url).endsWith('/multiple-choice')
        ? { available: true, catalogs: data.catalogs, tests: [withdrawn] }
        : { test: withdrawn, attempts: [] } }))
    await wrapper.get('[role="dialog"]').findAll('button').find(button => button.text() === 'Testfreigabe zurücknehmen').trigger('click')
    await flushPromises()
    expect(wrapper.text()).not.toContain('Test fortsetzen')
    await clickText(wrapper, 'Test wieder freigeben')
    expect(wrapper.get('[role="dialog"]').text()).toContain('Der Test bleibt pausiert, bis du ihn fortsetzt.')
})

it.each(['common', 'individual'])('shows the real draft status and previews each saved assignment without requests in %s mode', async startMode => {
    const test = { id: 7, catalog_id: 4, title: 'Entwurf', status: 'draft', start_mode: startMode, question_count: 10, closes_at: '2026-10-22T19:00:00Z' }
    const attempts = [{ id: 1, student_name: 'Alex', questions: tenQuestions(), access_url: null },
        { id: 2, student_name: 'Sam', questions: tenQuestions().map(question => ({ ...question, prompt: `Sam: ${question.prompt}` })), access_url: null }]
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: tenQuestions() }], tests: [test] }, { test, attempts }, '/admin/teaching-tools/multiple-choice?catalog=4&view=test&test=7&pane=students')
    await flushPromises()
    expect(wrapper.findAll('[role="status"]').some(item => item.text().includes('Noch nicht freigegeben'))).toBe(true)
    if (startMode === 'individual') expect(wrapper.text()).toContain('21:00')
    expect(wrapper.find('[aria-label="Persönlichen Test von Alex öffnen"]').exists()).toBe(false)
    vi.mocked(axios.get).mockClear()
    await wrapper.find('[aria-label="Gespeicherte Fragen von Alex ansehen"]').trigger('click')
    let preview = wrapper.find('[role="dialog"]')
    expect(preview.text()).toContain('Schreibgeschützte Lehrervorschau · 10 fest zugeordnete Fragen')
    expect(preview.findAll('article').map(article => article.find('h3').text())).toEqual(tenQuestions().map((question, index) => `${index + 1}. ${question.prompt}`))
    expect(preview.findAll('input, textarea')).toHaveLength(0)
    await preview.findAll('button').find(button => button.text() === 'Schließen').trigger('click')
    await wrapper.find('[aria-label="Gespeicherte Fragen von Sam ansehen"]').trigger('click')
    preview = wrapper.find('[role="dialog"]')
    expect(preview.text()).toContain('Sam: Frage 0')
    for (const method of ['get', 'post', 'patch', 'delete']) expect(axios[method]).not.toHaveBeenCalled()
})

it.each([
    ['2026-10-22T21:00', '2026-10-22T19:00:00.000Z'],
    ['2027-01-15T21:00', '2027-01-15T20:00:00.000Z'],
    ['2027-07-15T21:00', '2027-07-15T19:00:00.000Z'],
    ['2026-10-25T02:30', '2026-10-25T01:30:00.000Z'],
])('converts Vienna wall time %s independently of the device timezone', (wall, instant) => {
    expect(viennaDateTimeIso(wall)).toBe(instant)
    expect(viennaDateTime(instant)).toBe(wall)
})

it.each(['2026-03-29T02:30', '2026-02-30T21:00', 'invalid'])('rejects a nonexistent Vienna time %s', wall => {
    expect(viennaDateTimeIso(wall)).toBe('')
})

it('keeps a selected 21:00 Vienna deadline at 21:00 when reopening and sends its exact UTC instant', async () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-21T10:00:00Z'))
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: tenQuestions() }] })
    await flushPromises()
    await openCatalogTests(wrapper)
    await clickText(wrapper, 'Neuen Test anlegen')
    await chooseFile(wrapper, new File(['Vorname,Nachname,E-Mail\nTest,Person,test@example.invalid'], 'students.csv'))
    vi.mocked(axios.post).mockResolvedValueOnce({ data: { valid: true, rows: [{ status: 'matched' }] } })
    await clickText(wrapper, 'Zuordnung prüfen')
    await wrapper.find('input[name="mc-start-mode-new"][value="individual"]').setValue(true)
    await wrapper.find('[aria-label="Schlussfrist für alle"]').trigger('click')
    await wrapper.find('[aria-label="Datum der Schlussfrist"]').setValue('2026-10-22')
    await wrapper.find('[aria-label="Uhrzeit der Schlussfrist"]').setValue('21:00')
    expect(wrapper.find('[role="dialog"]').text()).toContain('21:00')
    await wrapper.find('[role="dialog"]').find('form').trigger('submit')
    expect(wrapper.find('[aria-label="Schlussfrist für alle"]').text()).toContain('21:00')
    await wrapper.find('[aria-label="Schlussfrist für alle"]').trigger('click')
    expect(wrapper.find('[aria-label="Datum der Schlussfrist"]').element.value).toBe('2026-10-22')
    expect(wrapper.find('[aria-label="Uhrzeit der Schlussfrist"]').element.value).toBe('21:00')
    await wrapper.find('[role="dialog"]').find('form').trigger('submit')
    await clickText(wrapper, 'Test erstellen')
    expect(wrapper.find('[role="dialog"]').text()).toContain('21:00')
    vi.mocked(axios.post).mockResolvedValueOnce({ data: {} })
    await wrapper.find('[role="dialog"]').findAll('button').find(button => button.text() === 'Test erstellen').trigger('click')
    await flushPromises()
    expect(axios.post).toHaveBeenLastCalledWith('/api/admin/multiple-choice/tests', expect.objectContaining({ closes_at: '2026-10-22T19:00:00.000Z' }))
})

describe('MC test folder import through Dropbox', () => {
    const directory = '2026-10-19_Informatik_5A_Gruppe-1'
    const folderPath = '/Materialien/Lerninhalte/' + directory
    const handshake = JSON.stringify({
        schema_version: 1, kind: 'schooltool.exercise_folder',
        exercise_folder_id: '123e4567-e89b-42d3-a456-426614174000',
    })
    const preview = {
        valid: true, directory, path: folderPath, folder_id: 'id:testfolder', file_id: 'id:workexport',
        revision: 'r1', exercise_folder_id: '123e4567-e89b-42d3-a456-426614174000',
        folder_proof: 'verified-folder-proof',
        csv: 'Vorname;Nachname;E-Mail;Kurs;Klasse\nAlex;Beispiel;alex@example.test;INF;5A',
        rows: [{ first_name: 'Alex', last_name: 'Beispiel', email: 'alex@example.test', status: 'matched' }],
        source: { work_title: 'Dateimanagement', course_title: 'INF - 5A - 1', scheduled_date: '2026-10-19' },
    }

    async function prepareFolderImport() {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: tenQuestions() }] })
        await flushPromises()
        await openCatalogTests(wrapper)
        await clickText(wrapper, 'Neuen Test anlegen')
        await wrapper.find('input[name="mc-start-mode-new"][value="common"]').setValue(true)
        vi.mocked(axios.get).mockClear()
        return wrapper
    }

    async function chooseFolderHandshake(wrapper, contents = handshake, filename = 'handshake.json') {
        await clickText(wrapper, 'Test mit Dropbox-Ordner verknüpfen')
        const ponds = wrapper.findAllComponents({ name: 'FilePond' })
        ponds.at(-1).vm.$emit('updatefiles', [{ file: new File([contents], filename, { type: 'application/json' }) }])
        await flushPromises()
    }

    it('resolves the local handshake online and binds only when the test is released', async () => {
        const wrapper = await prepareFolderImport()
        vi.mocked(axios.post).mockResolvedValueOnce({ data: preview })

        await chooseFolderHandshake(wrapper)

        expect(axios.get).not.toHaveBeenCalled()
        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/test-folder/resolve',
            { filename: 'handshake.json', handshake }, expect.objectContaining({ timeout: 120000 }))
        expect(wrapper.find('[data-testid="test-folder-path"]').text()).toBe(folderPath)
        expect(wrapper.find('[aria-label="Dropbox-Testordner zuordnen"]').text()).toContain('1 Schüler in der Vorschau')
        expect(wrapper.find('[aria-label="Testbezeichnung"]').element.value).toBe('Katalog – Test')
        await clickText(wrapper, 'Für diesen Test vormerken')
        expect(wrapper.find('[aria-label="Testbezeichnung"]').element.value).toBe(directory)
        expect(wrapper.find('textarea').exists()).toBe(false)
        expect(wrapper.findAllComponents({ name: 'FilePond' })).toHaveLength(0)
        expect(wrapper.text()).not.toContain('Test mit Dropbox-Ordner verknüpfen')
        expect(wrapper.text()).toContain('Verknüpfung aufheben')
        expect(wrapper.find('[aria-label="Tests vorbereiten"]').text()).toContain(folderPath)
        expect(wrapper.find('[aria-label="Tests vorbereiten"]').text()).not.toContain('Schülerliste prüfen')
        expect(axios.post).toHaveBeenCalledTimes(1)
        await wrapper.find('[aria-label="Testbezeichnung"]').setValue('Eigener Testname')
        await clickText(wrapper, 'Test erstellen')
        const test = { id: 7, catalog_id: 4, title: 'Eigener Testname', start_mode: 'common' }
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { test } })
        vi.mocked(axios.get).mockImplementation(async url => ({ data: String(url).endsWith('/multiple-choice')
            ? { available: true, catalogs: [{ id: 4, title: 'Katalog', questions: tenQuestions() }], tests: [test] }
            : { test, attempts: [] } }))
        await wrapper.find('[role="dialog"]').findAll('button').find(item => item.text() === 'Test erstellen').trigger('click')
        await flushPromises()

        expect(axios.post).toHaveBeenLastCalledWith('/api/admin/multiple-choice/tests',
            expect.objectContaining({ title: 'Eigener Testname', roster_csv: preview.csv, test_folder_proof: 'verified-folder-proof' }))
    })

    it('rejects invalid local handshakes before any online request and keeps the draft', async () => {
        const wrapper = await prepareFolderImport()
        await wrapper.find('[aria-label="Testbezeichnung"]').setValue('Mein Test')

        await chooseFolderHandshake(wrapper, '{"schema_version":2}', 'handshake.json')

        expect(wrapper.find('[aria-label="Dropbox-Testordner zuordnen"]').text()).toContain('gültigen Testordner-Vertrag')
        expect(wrapper.find('[aria-label="Testbezeichnung"]').element.value).toBe('Mein Test')
        expect(axios.post).not.toHaveBeenCalled()
    })

    it('accepts a markdown file and sends only its fingerprint to locate the test folder', async () => {
        const wrapper = await prepareFolderImport()
        const digest = vi.fn().mockResolvedValueOnce(new Uint8Array([1]).buffer)
            .mockResolvedValueOnce(new Uint8Array(32).fill(10).buffer)
        vi.stubGlobal('crypto', { subtle: { digest } })
        try {
            vi.mocked(axios.post).mockResolvedValueOnce({ data: preview })
            await chooseFolderHandshake(wrapper, 'Aufgabe', 'Aufgabe.md')
            expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/test-folder/resolve',
                { filename: 'Aufgabe.md', content_hash: '0a'.repeat(32) }, expect.objectContaining({ timeout: 120000 }))
            expect(wrapper.find('[data-testid="test-folder-path"]').text()).toBe(folderPath)
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('shows online conflicts without changing the test draft', async () => {
        const wrapper = await prepareFolderImport()
        await wrapper.find('[aria-label="Testbezeichnung"]').setValue('Mein Test')
        vi.mocked(axios.post).mockRejectedValueOnce({ response: { status: 409, data: { message: 'Die Testordner-ID ist mehrfach vorhanden.' } } })

        await chooseFolderHandshake(wrapper)

        expect(wrapper.find('[aria-label="Dropbox-Testordner zuordnen"]').text()).toContain('mehrfach vorhanden')
        expect(wrapper.find('[aria-label="Testbezeichnung"]').element.value).toBe('Mein Test')
        expect(wrapper.find('textarea').element.value).toBe('')
        expect(wrapper.findAll('button').find(item => item.text() === 'Für diesen Test vormerken').element.disabled).toBe(true)
    })

    it('shows ambiguous students and prevents publication', async () => {
        const wrapper = await prepareFolderImport()
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { ...preview, valid: false,
            rows: [{ status: 'error' }], errors: ['Schülerkonto nicht eindeutig.'] } })

        await chooseFolderHandshake(wrapper)
        await clickText(wrapper, 'Für diesen Test vormerken')

        expect(wrapper.text()).toContain('Schülerkonto nicht eindeutig.')
        expect(wrapper.findAll('button').find(item => item.text() === 'Test erstellen').element.disabled).toBe(true)
    })

    it('removes the folder and its roster only after disconnecting', async () => {
        const wrapper = await prepareFolderImport()
        vi.mocked(axios.post).mockResolvedValueOnce({ data: preview })
        await chooseFolderHandshake(wrapper)
        await clickText(wrapper, 'Für diesen Test vormerken')
        const csv = 'Vorname;Nachname;E-Mail;Kurs;Klasse\nBen;Beispiel;ben@example.test;INF;5A'

        await clickText(wrapper, 'Verknüpfung aufheben')
        await wrapper.find('textarea').setValue(csv)

        expect(wrapper.text()).toContain('Test mit Dropbox-Ordner verknüpfen')
        expect(wrapper.find('[aria-label="Tests vorbereiten"]').text()).not.toContain(folderPath)
        expect(wrapper.findAll('button').find(item => item.text() === 'Test erstellen').element.disabled).toBe(true)
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { valid: true, rows: [{ status: 'matched' }] } })
        await clickText(wrapper, 'Zuordnung prüfen')
        await clickText(wrapper, 'Test erstellen')
        const test = { id: 7, catalog_id: 4, title: directory, start_mode: 'common' }
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { test } })
        vi.mocked(axios.get).mockImplementation(async url => ({ data: String(url).endsWith('/multiple-choice')
            ? { available: true, catalogs: [{ id: 4, title: 'Katalog', questions: tenQuestions() }], tests: [test] }
            : { test, attempts: [] } }))
        await wrapper.find('[role="dialog"]').findAll('button').find(item => item.text() === 'Test erstellen').trigger('click')
        await flushPromises()

        const createPayload = vi.mocked(axios.post).mock.calls.at(-1)[1]
        expect(createPayload.roster_csv).toBe(csv)
        expect(createPayload).not.toHaveProperty('test_folder_proof')
    })
})

describe('Multiple Choice URL navigation', () => {
    const catalogs = [
        { id: 4, title: 'Netzwerke', questions: [validQuestion()] },
        { id: 5, title: 'Entwurf', questions: [] },
    ]
    const tests = [
        { id: 7, catalog_id: 4, title: 'Test A', question_count: 1, total_points: 10, duration_seconds: 600 },
        { id: 8, catalog_id: 5, title: 'Anderer Katalogtest', question_count: 1, total_points: 10, duration_seconds: 600 },
    ]
    const base = '/admin/teaching-tools/multiple-choice'

    it('cancels creation without saving and starts the next draft with clean settings and roster', async () => {
        const questions = [validQuestion(), validQuestion({ id: 'b' }), validQuestion({ id: 'c' })]
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Netzwerke', questions }], tests: [] })
        await flushPromises()
        await openCatalogTests(wrapper)
        await clickText(wrapper, 'Neuen Test anlegen')
        await wrapper.find('[aria-label="Testbezeichnung"]').setValue('Verworfener Entwurf')
        await wrapper.find('[aria-label="Fragen pro Schüler"]').setValue('1')
        await wrapper.find('[aria-label="Erreichbare Gesamtpunkte"]').setValue('99')
        await wrapper.find('[aria-label="Bearbeitungsdauer in Minuten"]').setValue('5')
        await wrapper.find('input[name="mc-start-mode-new"][value="common"]').setValue(true)
        await wrapper.find('[aria-label="Vorlaufzeit in Sekunden"]').setValue('0')
        await wrapper.find('input[type="checkbox"]').setValue(false)
        await chooseFile(wrapper, new File(['Vorname,Nachname,E-Mail\nTest,Person,test@example.invalid'], 'students.csv'))
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { valid: true, rows: [{ status: 'matched' }] } })
        await clickText(wrapper, 'Zuordnung prüfen')

        await clickText(wrapper, 'Abbrechen')

        expect(wrapper.router.currentRoute.value.query).toEqual({ catalog: '4', view: 'tests' })
        expect(wrapper.text()).toContain('Tests zu „Netzwerke“')
        expect(axios.post).toHaveBeenCalledTimes(1)
        expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/roster-preview', expect.any(Object))
        expect(axios.patch).not.toHaveBeenCalled()
        await clickText(wrapper, 'Neuen Test anlegen')
        expect(wrapper.find('[aria-label="Testbezeichnung"]').element.value).toBe('Netzwerke – Test')
        expect(wrapper.find('[aria-label="Fragen pro Schüler"]').element.value).toBe('10')
        expect(wrapper.find('[aria-label="Erreichbare Gesamtpunkte"]').element.value).toBe('10')
        expect(wrapper.find('[aria-label="Bearbeitungsdauer in Minuten"]').element.value).toBe('20')
        expect(wrapper.find('input[name="mc-start-mode-new"][value="individual"]').element.checked).toBe(true)
        await wrapper.find('input[name="mc-start-mode-new"][value="common"]').setValue(true)
        expect(wrapper.find('[aria-label="Vorlaufzeit in Sekunden"]').element.value).toBe('30')
        expect(wrapper.findAll('input[type="checkbox"]').filter(item => item.attributes('aria-label') !== 'Prüfungsmodus aktivieren').every((item) => item.element.checked)).toBe(true)
        expect(wrapper.find('[aria-label="Prüfungsmodus aktivieren"]').element.checked).toBe(false)
        expect(wrapper.find('textarea').element.value).toBe('')
        expect(wrapper.text()).not.toContain('Ausgewählt:')
        expect(wrapper.findAll('button').find((item) => item.text() === 'Test erstellen').attributes('disabled')).toBeDefined()
        wrapper.router.back()
        await flushPromises()
        expect(wrapper.find('[aria-label="Tests vorbereiten"]').exists()).toBe(false)
    })

    it('does not restore a cancelled roster when its pending file read finishes', async () => {
        const wrapper = renderWorkspace({ catalogs: [catalogs[0]], tests: [] })
        await flushPromises()
        await openCatalogTests(wrapper)
        await clickText(wrapper, 'Neuen Test anlegen')
        let completeRead
        await chooseFile(wrapper, { name: 'slow.csv', text: () => new Promise((resolve) => { completeRead = resolve }) })

        await clickText(wrapper, 'Abbrechen')
        await clickText(wrapper, 'Neuen Test anlegen')
        completeRead('Verworfene Schülerliste')
        await flushPromises()

        expect(wrapper.find('textarea').element.value).toBe('')
        expect(wrapper.text()).not.toContain('Ausgewählt:')
        expect(axios.post).not.toHaveBeenCalled()
    })

    it.each([
        ['?catalog=4', 'Bearbeiten'],
        ['?catalog=4&view=import', 'Fragen aus einer Vorlage'],
        ['?catalog=4&view=tests', 'Tests zu „Netzwerke“'],
        ['?catalog=4&view=new-test', 'Neuen Test vorbereiten'],
        ['?catalog=4&view=test&test=7', 'Countdown starten (30 s)'],
        ['?catalog=4&view=results&test=7', 'Countdown starten (30 s)'],
    ])('restores %s from a fresh direct URL without writes', async (query, expected) => {
        const wrapper = renderWorkspace({ catalogs, tests }, {}, base + query)
        await flushPromises()

        expect(wrapper.text()).toContain(expected)
        expect(wrapper.router.currentRoute.value.fullPath).toBe(base + query)
        expect(wrapper.text()).not.toContain('Anderer Katalogtest')
        expect(axios.post).not.toHaveBeenCalled()
        expect(axios.patch).not.toHaveBeenCalled()
    })

    it('restores an expanded student evaluation from its result URL', async () => {
        const wrapper = renderWorkspace({ catalogs, tests }, { attempts: [{
            id: 12, status: 'submitted', started_at: '2026-10-16T08:01:00Z', student_name: 'Test Person', score: 10,
            question_results: [{ id: 'question-a', prompt: 'Wiederhergestellte Auswertung', correct: true, points: 10 }],
        }] }, base + '?catalog=4&view=results&test=7&attempt=12')
        await flushPromises()

        expect(wrapper.text()).toContain('Wiederhergestellte Auswertung')
        await clickText(wrapper, 'Schließen')
        expect(wrapper.router.currentRoute.value.query.attempt).toBeUndefined()
        expect(wrapper.text()).not.toContain('Wiederhergestellte Auswertung')
        wrapper.router.back()
        await flushPromises()
        expect(wrapper.text()).toContain('Wiederhergestellte Auswertung')
    })

    it('keeps catalog, creation, test and results in browser back and forward history', async () => {
        const wrapper = renderWorkspace({ catalogs: [catalogs[0]], tests: [tests[0]] })
        await flushPromises()
        await openCatalogTests(wrapper)
        expect(wrapper.router.currentRoute.value.query).toEqual({ catalog: '4', view: 'tests' })
        await clickText(wrapper, 'Neuen Test anlegen')
        expect(wrapper.router.currentRoute.value.query).toEqual({ catalog: '4', view: 'new-test' })
        wrapper.router.back()
        await flushPromises()
        expect(wrapper.text()).toContain('Tests zu „Netzwerke“')
        expect(wrapper.find('[aria-label="Tests vorbereiten"]').exists()).toBe(false)
        wrapper.router.forward()
        await flushPromises()
        expect(wrapper.find('[aria-label="Testbezeichnung"]').element.value).toBe('Netzwerke – Test')
        await clickText(wrapper, 'Zur Testübersicht')
        await clickText(wrapper, 'Test öffnen')
        expect(wrapper.router.currentRoute.value.query).toEqual({ catalog: '4', view: 'test', test: '7' })
        expect(wrapper.text()).toContain('Auswertung')
        expect(wrapper.text()).not.toContain('Ergebnisse ansehen')
        wrapper.router.back()
        await flushPromises()
        expect(wrapper.text()).toContain('Tests zu „Netzwerke“')
        wrapper.router.forward()
        await flushPromises()
        expect(wrapper.text()).toContain('Countdown starten (30 s)')
        expect(wrapper.text()).toContain('Auswertung')
    })

    it.each([
        ['?catalog=999', {}, 'Fragenkatalog wurde nicht gefunden'],
        ['?catalog=bad', {}, 'Fragenkatalog wurde nicht gefunden'],
        ['?catalog=4&catalog=5', {}, 'Fragenkatalog wurde nicht gefunden'],
        ['?catalog=4&view=unknown', { catalog: '4' }, 'Diese Ansicht ist nicht verfügbar'],
        ['?catalog=4&view=test&test=999', { catalog: '4', view: 'tests' }, 'Test wurde nicht gefunden'],
        ['?catalog=4&view=results&test=8', { catalog: '4', view: 'tests' }, 'gehört nicht zu diesem Katalog'],
        ['?catalog=4&view=results', { catalog: '4', view: 'tests' }, 'Test wurde nicht gefunden'],
        ['?catalog=5&view=new-test', { catalog: '5', view: 'tests' }, 'Prüfe und speichere zuerst die Fragen'],
    ])('handles invalid or inaccessible navigation %s without writes', async (query, fallback, expected) => {
        const wrapper = renderWorkspace({ catalogs, tests }, {}, base + query)
        await flushPromises()

        expect(wrapper.router.currentRoute.value.query).toEqual(fallback)
        expect(wrapper.text()).toContain(expected)
        expect(wrapper.find('[aria-label="Geöffneter Test"]').exists()).toBe(false)
        expect(axios.post).not.toHaveBeenCalled()
        expect(axios.patch).not.toHaveBeenCalled()
    })

    it('protects unsaved questions during browser navigation and follows the destination only after deliberate discard', async () => {
        const wrapper = renderWorkspace({ catalogs: [catalogs[0]], tests: [] })
        await flushPromises()
        await clickText(wrapper, 'Katalog öffnen')
        const questions = [validQuestion({ prompt: 'Ungespeicherte Frage' })]
        await clickText(wrapper, 'Bearbeiten')
        await wrapper.findComponent(QuestionEditor).vm.$emit('update:questions', questions)

        wrapper.router.back()
        await flushPromises()

        expect(wrapper.router.currentRoute.value.query.catalog).toBe('4')
        expect(wrapper.find('[role="dialog"]').text()).toContain('Ungespeicherte Änderungen')
        await clickText(wrapper, 'Weiterbearbeiten')
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual(questions)
        await wrapper.router.push(base)
        await flushPromises()
        await clickText(wrapper, 'Änderungen verwerfen')
        expect(wrapper.router.currentRoute.value.query).toEqual({})
        expect(wrapper.text()).toContain('Meine Fragenkataloge')
    })

    it('shows a recoverable access error when a linked test is no longer accessible', async () => {
        const wrapper = renderWorkspace({ catalogs: [catalogs[0]], tests: [tests[0]] })
        await flushPromises()
        await openCatalogTests(wrapper)
        vi.mocked(axios.get).mockRejectedValueOnce({ response: { status: 403, data: { message: 'Kein Zugriff auf diesen Test.' } } })

        await clickText(wrapper, 'Test öffnen')

        expect(wrapper.text()).toContain('Kein Zugriff auf diesen Test.')
        expect(wrapper.text()).not.toContain('Ergebnisse ansehen')
        expect(wrapper.text()).not.toContain('Countdown starten')
        await clickText(wrapper, 'Zur Testübersicht')
        expect(wrapper.text()).toContain('Tests zu „Netzwerke“')
        expect(axios.post).not.toHaveBeenCalled()
    })
})

describe('Multiple Choice teacher workflow', () => {
    it('opens existing catalog questions as readable text and enables editing only deliberately', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }] })
        await flushPromises()
        await clickText(wrapper, 'Katalog öffnen')

        const reader = wrapper.findComponent(QuestionEditor)
        expect(reader.props('readonly')).toBe(true)
        expect(reader.findAll('textarea, input, select')).toHaveLength(0)
        expect(reader.text()).toContain('Welche Antwort ist richtig?')
        expect(reader.text()).toContain('Richtig')
        expect(wrapper.findAll('button').some((item) => item.text() === 'Test vorbereiten')).toBe(false)
        await clickText(wrapper, 'Bearbeiten')
        expect(reader.props('readonly')).toBe(false)
        await reader.find('textarea').setValue('Ungespeicherter Fragetext')
        expect(axios.patch).not.toHaveBeenCalled()

        await clickText(wrapper, 'Bearbeitung abbrechen')

        expect(reader.props('readonly')).toBe(true)
        expect(reader.text()).toContain('Welche Antwort ist richtig?')
        expect(reader.text()).not.toContain('Ungespeicherter Fragetext')
        expect(axios.post).not.toHaveBeenCalled()
        expect(axios.patch).not.toHaveBeenCalled()
    })

    it('saves question edits only explicitly and returns to the read-only catalog afterwards', async () => {
        const catalog = { id: 4, title: 'Katalog', questions: [validQuestion()] }
        const wrapper = renderWorkspace({ catalogs: [catalog] })
        await flushPromises()
        await editCatalog(wrapper)
        await wrapper.findComponent(QuestionEditor).find('textarea').setValue('Gespeicherte Änderung')
        expect(axios.patch).not.toHaveBeenCalled()
        const questions = [validQuestion({ prompt: 'Gespeicherte Änderung' })]
        vi.mocked(axios.patch).mockResolvedValueOnce({ data: { catalog: { ...catalog, questions } } })

        await clickText(wrapper, 'Fragen speichern')

        expect(axios.patch).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/catalogs/4', { title: 'Katalog', questions })
        expect(wrapper.findComponent(QuestionEditor).props('readonly')).toBe(true)
        expect(wrapper.text()).toContain('Gespeicherte Änderung')
        expect(wrapper.text()).toContain('Bearbeiten')
    })

    it('reloads the selected catalog into read-only mode and keeps failed saves editable', async () => {
        const catalog = { id: 4, title: 'Katalog', questions: [validQuestion()] }
        const wrapper = renderWorkspace({ catalogs: [catalog] }, {}, '/admin/teaching-tools/multiple-choice?catalog=4')
        await flushPromises()
        await clickText(wrapper, 'Bearbeiten')
        await wrapper.findComponent(QuestionEditor).find('textarea').setValue('Nicht gespeichert')
        vi.mocked(axios.patch).mockRejectedValueOnce({ response: { data: { message: 'Speichern fehlgeschlagen.' } } })
        await clickText(wrapper, 'Fragen speichern')
        expect(wrapper.findComponent(QuestionEditor).props('readonly')).toBe(false)
        expect(wrapper.findComponent(QuestionEditor).find('textarea').element.value).toBe('Nicht gespeichert')

        const reloaded = renderWorkspace({ catalogs: [catalog] }, {}, wrapper.router.currentRoute.value.fullPath)
        await flushPromises()

        expect(reloaded.findComponent(QuestionEditor).props('readonly')).toBe(true)
        expect(reloaded.findComponent(QuestionEditor).findAll('textarea, input, select')).toHaveLength(0)
        expect(reloaded.text()).not.toContain('Nicht gespeichert')
    })

    it('navigates from a catalog through only its own tests to results and back without losing question edits', async () => {
        const wrapper = renderWorkspace({ catalogs: [
            { id: 4, title: 'Netzwerke', questions: [validQuestion()] },
            { id: 5, title: 'E-Mail', questions: [validQuestion()] },
        ], tests: [
            { id: 7, catalog_id: 4, title: 'Test A', question_count: 1, total_points: 10, duration_seconds: 600 },
            { id: 8, catalog_id: 4, title: 'Test B', question_count: 1, total_points: 10, duration_seconds: 600 },
            { id: 9, catalog_id: 5, title: 'Fremder Katalogtest', question_count: 1, total_points: 10, duration_seconds: 600 },
        ] })
        await flushPromises()
        expect(wrapper.findAll('button').some((item) => ['Tests', 'Ergebnisse'].includes(item.text()))).toBe(false)
        expect(axios.get).toHaveBeenCalledTimes(1)
        await wrapper.find('[aria-label="Katalog öffnen: Netzwerke"]').trigger('click')
        await flushPromises()
        const questions = [validQuestion({ prompt: 'Offene Änderung', reviewed: false })]
        await clickText(wrapper, 'Bearbeiten')
        await wrapper.findComponent(QuestionEditor).vm.$emit('update:questions', questions)
        await clickText(wrapper, 'Tests zu diesem Katalog (2)')

        expect(wrapper.text()).toContain('Tests zu „Netzwerke“')
        expect(wrapper.text()).toContain('Test A')
        expect(wrapper.text()).toContain('Test B')
        expect(wrapper.text()).not.toContain('Fremder Katalogtest')
        expect(wrapper.find('[aria-label="Test auswählen"]').exists()).toBe(false)
        expect(wrapper.find('[aria-label="Tests vorbereiten"]').exists()).toBe(false)
        await wrapper.find('[aria-label="Test öffnen: Test A"]').trigger('click')
        await flushPromises()
        expect(axios.get).toHaveBeenLastCalledWith('/api/admin/multiple-choice/tests/7')
        expect(wrapper.text()).toContain('Auswertung')
        expect(wrapper.text()).not.toContain('Ergebnisse ansehen')
        expect(wrapper.text()).toContain('Einladungen vorbereiten')
        await clickText(wrapper, 'Zur Testübersicht')
        expect(wrapper.text()).toContain('Test B')
        expect(wrapper.text()).not.toContain('Ergebnisse ansehen')
        await clickText(wrapper, 'Zum Fragenkatalog')
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual(questions)
        expect(wrapper.text()).toContain('Fragen speichern')
        expect(axios.post).not.toHaveBeenCalled()
        expect(axios.patch).not.toHaveBeenCalled()
    })

    it('offers the test overview for an unfinished catalog without allowing a new test', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Entwurf', questions: [] }] })
        await flushPromises()
        await openCatalogTests(wrapper)

        expect(wrapper.text()).toContain('Zu diesem Fragenkatalog gibt es noch keine Tests.')
        expect(wrapper.text()).toContain('Prüfe und speichere zuerst die Fragen')
        expect(wrapper.findAll('button').find((item) => item.text() === 'Neuen Test anlegen').attributes('disabled')).toBeDefined()
        await clickText(wrapper, 'Zum Fragenkatalog')
        expect(wrapper.find('h2').text()).toBe('Entwurf')
    })

    it('publishes a new test for the opened catalog and opens its waiting room only after confirmation', async () => {
        const catalogs = [{ id: 4, title: 'Netzwerke', questions: tenQuestions() }]
        const test = { id: 7, catalog_id: 4, title: 'Netzwerke – Test', question_count: 1, total_points: 10, duration_seconds: 1200, lead_seconds: 30, status: 'draft' }
        const wrapper = renderWorkspace({ catalogs })
        await flushPromises()
        await openCatalogTests(wrapper)
        await clickText(wrapper, 'Neuen Test anlegen')
        await wrapper.find('input[name="mc-start-mode-new"][value="common"]').setValue(true)
        expect(wrapper.find('[aria-label="Gespeicherter Fragenkatalog"]').exists()).toBe(false)
        await chooseFile(wrapper, new File(['Vorname,Nachname,E-Mail\nTest,Person,test@example.invalid'], 'students.csv'))
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { valid: true, rows: [{ status: 'matched' }] } })
        await clickText(wrapper, 'Zuordnung prüfen')
        await clickText(wrapper, 'Test erstellen')
        expect(axios.post).toHaveBeenCalledTimes(1)
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { test } })
        vi.mocked(axios.get).mockImplementation(async (url) => ({ data: String(url).endsWith('/multiple-choice')
            ? { available: true, catalogs, tests: [test] } : { test, attempts: [] } }))

        await wrapper.find('[role="dialog"]').findAll('button').find((item) => item.text() === 'Test erstellen').trigger('click')
        await flushPromises()

        expect(axios.post).toHaveBeenLastCalledWith('/api/admin/multiple-choice/tests', expect.objectContaining({ catalog_id: 4, title: 'Netzwerke – Test', start_mode: 'common', lead_seconds: 30 }))
        expect(wrapper.find('[aria-label="Tests vorbereiten"]').exists()).toBe(false)
        expect(wrapper.text()).not.toContain('Countdown starten (30 s)')
        expect(wrapper.text()).toContain('Test freigeben')
        expect(wrapper.text()).toContain('noch nicht freigegeben')
        expect(wrapper.text()).toContain('Auswertung')
        expect(wrapper.router.currentRoute.value.query).toEqual({ catalog: '4', view: 'test', test: '7' })
        await clickText(wrapper, 'Zur Testübersicht')
        expect(wrapper.text()).toContain('Netzwerke – Test')
    })

    it('starts with a catalog overview and only opens a catalog deliberately', async () => {
        const wrapper = renderWorkspace({ catalogs: [
            { id: 4, title: 'Netzwerke', questions: [validQuestion()] },
            { id: 5, title: 'E-Mail', questions: [] },
        ] })
        await flushPromises()

        expect(wrapper.text()).toContain('Meine Fragenkataloge')
        expect(wrapper.text()).toContain('Netzwerke')
        expect(wrapper.text()).toContain('E-Mail')
        expect(wrapper.find('[aria-label="Bezeichnung des Katalogs"]').exists()).toBe(false)
        expect(wrapper.find('[aria-label="Fragenkatalog auswählen"]').exists()).toBe(false)
        expect(wrapper.findComponent(QuestionEditor).exists()).toBe(false)
        expect(wrapper.text()).not.toContain('Vorlage importieren')
        expect(axios.post).not.toHaveBeenCalled()

        await wrapper.find('[aria-label="Katalog öffnen: Netzwerke"]').trigger('click')
        await flushPromises()
        expect(wrapper.find('h2').text()).toBe('Netzwerke')
        expect(wrapper.find('[aria-label="Bezeichnung des Katalogs"]').exists()).toBe(false)
        expect(wrapper.text()).not.toContain('Katalog speichern')
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual([validQuestion()])
        await clickText(wrapper, 'Zur Katalogübersicht')
        expect(wrapper.text()).toContain('Meine Fragenkataloge')
        expect(wrapper.findComponent(QuestionEditor).exists()).toBe(false)
        expect(axios.patch).not.toHaveBeenCalled()
    })

    it('offers a quiet empty overview and validates the name before creation or editing', async () => {
        const wrapper = renderWorkspace()
        await flushPromises()
        expect(wrapper.text()).toContain('Noch keine Fragenkataloge')
        expect(wrapper.findAll('input')).toHaveLength(0)
        await clickText(wrapper, 'Neuer Katalog')
        const modal = wrapper.find('[role="dialog"]')
        expect(modal.findAll('input')).toHaveLength(1)
        expect(modal.text()).not.toContain('Vorlage importieren')
        expect(wrapper.findComponent(QuestionEditor).exists()).toBe(false)

        await modal.find('input').setValue('   ')
        await modal.find('form').trigger('submit')
        await flushPromises()
        expect(modal.text()).toContain('Bitte gib eine Bezeichnung ein.')
        await modal.find('input').setValue('A'.repeat(256))
        await modal.find('form').trigger('submit')
        await flushPromises()
        expect(modal.text()).toContain('höchstens 255 Zeichen')
        expect(axios.post).not.toHaveBeenCalled()
        await clickText(wrapper, 'Abbrechen')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(wrapper.findComponent(QuestionEditor).exists()).toBe(false)
        expect(wrapper.text()).toContain('Noch keine Fragenkataloge')
    })

    it('creates the named empty draft first and updates the overview after saving its questions', async () => {
        const wrapper = renderWorkspace()
        await flushPromises()
        await clickText(wrapper, 'Neuer Katalog')
        await wrapper.find('[aria-label="Bezeichnung"]').setValue('  E-Mail  ')
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { catalog: { id: 8, title: 'E-Mail', questions: [] } } })

        await wrapper.find('[role="dialog"] form').trigger('submit')
        await flushPromises()

        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/catalogs', { title: 'E-Mail', questions: [] })
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(wrapper.find('h2').text()).toBe('E-Mail')
        expect(wrapper.find('[aria-label="Bezeichnung des Katalogs"]').exists()).toBe(false)
        await clickText(wrapper, 'Bearbeiten')
        expect(wrapper.text()).toContain('Vorlage importieren')
        expect(wrapper.text()).toContain('Frage hinzufügen')
        expect(wrapper.text()).not.toContain('Test vorbereiten')
        await clickText(wrapper, 'Frage hinzufügen')
        const questions = wrapper.findComponent(QuestionEditor).props('questions')
        expect(questions).toHaveLength(1)
        expect(questions[0].reviewed).toBe(false)
        vi.mocked(axios.patch).mockResolvedValueOnce({ data: { catalog: { id: 8, title: 'E-Mail', questions } } })
        await clickText(wrapper, 'Fragen speichern')
        await clickText(wrapper, 'Zur Katalogübersicht')
        expect(wrapper.text()).toContain('E-Mail')
        expect(wrapper.text()).toContain('1 Frage · 0 aktiv und geprüft')
        await clickText(wrapper, 'Katalog öffnen')
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual(questions)
    })

    it('keeps a failed creation in the dialog without exposing an editor', async () => {
        const wrapper = renderWorkspace()
        await flushPromises()
        await clickText(wrapper, 'Neuer Katalog')
        await wrapper.find('[aria-label="Bezeichnung"]').setValue('E-Mail')
        vi.mocked(axios.post).mockRejectedValueOnce({ response: { data: { message: 'Speichern fehlgeschlagen.' } } })
        await wrapper.find('[role="dialog"] form').trigger('submit')
        await flushPromises()
        expect(wrapper.find('[role="dialog"]').text()).toContain('Speichern fehlgeschlagen.')
        expect(wrapper.find('[aria-label="Bezeichnung"]').element.value).toBe('E-Mail')
        expect(wrapper.findComponent(QuestionEditor).exists()).toBe(false)
    })

    it('preserves saved contents when unsaved edits are deliberately discarded', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }] })
        await flushPromises()
        await editCatalog(wrapper)
        await wrapper.find('[aria-label="Katalog umbenennen"]').trigger('click')
        await wrapper.find('[aria-label="Bezeichnung des Katalogs"]').setValue('Ungespeichert')
        await clickText(wrapper, 'Zur Katalogübersicht')
        expect(wrapper.find('[role="dialog"]').text()).toContain('Ungespeicherte Änderungen')
        await clickText(wrapper, 'Weiterbearbeiten')
        expect(wrapper.find('[aria-label="Bezeichnung des Katalogs"]').element.value).toBe('Ungespeichert')
        await clickText(wrapper, 'Zur Katalogübersicht')
        await clickText(wrapper, 'Änderungen verwerfen')
        await clickText(wrapper, 'Katalog öffnen')
        expect(wrapper.find('h2').text()).toBe('Katalog')
        expect(wrapper.find('[aria-label="Bezeichnung des Katalogs"]').exists()).toBe(false)
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual([validQuestion()])
        expect(axios.patch).not.toHaveBeenCalled()
    })

    it('opens without writes and shows an actionable activation state', async () => {
        const wrapper = renderWorkspace({ available: false, message: 'Die Administration muss Multiple Choice aktivieren.' })
        await flushPromises()

        expect(wrapper.text()).toContain('Die Administration muss Multiple Choice aktivieren.')
        expect(wrapper.find('[aria-label="Fragenkataloge"]').exists()).toBe(false)
        expect(axios.post).not.toHaveBeenCalled()
        expect(axios.patch).not.toHaveBeenCalled()
    })

    it('previews supplied questions directly and confirms them only on deliberate adoption', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [] }] })
        await flushPromises()
        await editCatalog(wrapper)
        vi.mocked(axios.post).mockResolvedValue({ data: { questions: [validQuestion()], errors: [], warnings: ['Bitte Inhalte prüfen.'], valid: true } })
        await clickText(wrapper, 'Vorlage importieren')
        expect(wrapper.find('.mc-import').findAll('input[type="checkbox"]')).toHaveLength(0)
        expect(wrapper.text()).not.toContain('mit KI')
        expect(wrapper.text()).not.toContain('Musterantworten')
        expect(wrapper.text()).not.toContain('Vorlage importieren')
        expect(wrapper.text()).not.toContain('Frage hinzufügen')
        const text = '# 1. Welche Antwort ist richtig?\n- [x] Richtig\n- [ ] Falsch'
        await chooseTemplate(wrapper, text)

        await clickText(wrapper, 'Import prüfen')

        expect(axios.post).toHaveBeenCalledTimes(1)
        expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/import-preview', { text, format: 'markdown', generate: false })
        expect(wrapper.text()).toContain('Bitte Inhalte prüfen.')
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual([validQuestion({ reviewed: false })])
        expect(wrapper.find('.mc-import').findAll('input[type="checkbox"]')).toHaveLength(0)
        expect(wrapper.text()).not.toContain('Aktiv für neue Tests')
        expect(wrapper.text()).not.toContain('Inhalt und Lösungen geprüft')
        expect(wrapper.text()).not.toContain('Vorlage importieren')
        expect(wrapper.text()).not.toContain('Frage hinzufügen')
        vi.mocked(axios.patch).mockResolvedValueOnce({ data: { catalog: { id: 4, title: 'Katalog', questions: [validQuestion()] } } })
        await clickText(wrapper, 'Fragen importieren')
        expect(axios.post).toHaveBeenCalledTimes(2)
        expect(axios.post).toHaveBeenLastCalledWith('/api/admin/multiple-choice/import-preview', {
            text: JSON.stringify({ questions: [validQuestion({ reviewed: false })] }), format: 'json', generate: false,
        })
        expect(wrapper.text()).toContain('1 Fragen · 1 aktiv und geprüft')
        expect(wrapper.text()).toContain('Bearbeiten')
        expect(wrapper.text()).not.toContain('Fragen speichern')
        expect(wrapper.find('.mc-import').exists()).toBe(false)
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual([validQuestion()])
    })

    it('cancels import without losing catalog edits and restores the usual actions', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }] })
        await flushPromises()
        await editCatalog(wrapper)
        const questions = [validQuestion({ prompt: 'Offene Fragenänderung', reviewed: false })]
        await wrapper.findComponent(QuestionEditor).vm.$emit('update:questions', questions)
        await clickText(wrapper, 'Vorlage importieren')
        await chooseTemplate(wrapper, 'Nicht übernommene Vorlage')

        await clickText(wrapper, 'Import abbrechen')

        expect(wrapper.text()).toContain('Vorlage importieren')
        expect(wrapper.text()).toContain('Frage hinzufügen')
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual(questions)
        expect(axios.post).not.toHaveBeenCalled()
        expect(axios.patch).not.toHaveBeenCalled()
        await clickText(wrapper, 'Vorlage importieren')
        expect(wrapper.findAll('button').find((item) => item.text() === 'Import prüfen').attributes('disabled')).toBeDefined()
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual(questions)
    })

    it('keeps import mode focused after a failed preview until it is deliberately cancelled', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [] }] })
        await flushPromises()
        await editCatalog(wrapper)
        await clickText(wrapper, 'Vorlage importieren')
        await chooseTemplate(wrapper, '1. Frage')
        vi.mocked(axios.post).mockRejectedValueOnce({ response: { data: { message: 'Prüfung fehlgeschlagen.' } } })

        await clickText(wrapper, 'Import prüfen')

        expect(wrapper.text()).toContain('Prüfung fehlgeschlagen.')
        expect(wrapper.text()).not.toContain('Vorlage importieren')
        expect(wrapper.text()).not.toContain('Frage hinzufügen')
        await clickText(wrapper, 'Import abbrechen')
        expect(wrapper.text()).toContain('Vorlage importieren')
        expect(wrapper.text()).toContain('Frage hinzufügen')
        expect(wrapper.find('.mc-import').exists()).toBe(false)
    })

    it.each([
        { correct_option_ids: [] },
        { correct_option_ids: ['missing-option'] },
        { options: [{ id: 'a', text: 'Gleich' }, { id: 'b', text: 'Gleich' }] },
    ])('rejects adoption after invalid edits and keeps the catalog unchanged (%j)', async (invalidUpdates) => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [] }] })
        await flushPromises()
        await editCatalog(wrapper)
        await clickText(wrapper, 'Vorlage importieren')
        await chooseTemplate(wrapper, '1. Frage')
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { questions: [validQuestion()], errors: [], warnings: [], valid: true } })
        await clickText(wrapper, 'Import prüfen')
        const invalidQuestions = [validQuestion({ ...invalidUpdates, reviewed: false })]
        await wrapper.findAllComponents(QuestionEditor)[0].vm.$emit('update:questions', invalidQuestions)
        vi.mocked(axios.post).mockResolvedValueOnce({ data: {
            questions: invalidQuestions, errors: { questions: ['Antworten oder Lösungen sind ungültig.'] }, warnings: [], valid: false,
        } })

        await clickText(wrapper, 'Fragen importieren')

        expect(axios.post).toHaveBeenLastCalledWith('/api/admin/multiple-choice/import-preview', {
            text: JSON.stringify({ questions: invalidQuestions }), format: 'json', generate: false,
        })
        expect(wrapper.text()).toContain('Antworten oder Lösungen sind ungültig.')
        expect(wrapper.find('.mc-import').exists()).toBe(true)
        expect(wrapper.findAllComponents(QuestionEditor)[1].props('questions')).toEqual([])
        expect(wrapper.text()).not.toContain('Fragen speichern')
        expect(axios.patch).not.toHaveBeenCalled()
    })

    it('confirms only complete edited questions when saving and preserves untouched and archived drafts', async () => {
        const oldDraft = validQuestion({ id: 'old', reviewed: false })
        const archived = validQuestion({ id: 'archived', active: false, reviewed: false })
        const original = [oldDraft, archived, validQuestion({ id: 'edited' }), validQuestion({ id: 'broken' })]
        const edited = validQuestion({ id: 'edited', prompt: 'Meine bearbeitete Frage', reviewed: false })
        const broken = validQuestion({ id: 'broken', correct_option_ids: [], reviewed: false })
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: original }] })
        await flushPromises()
        await editCatalog(wrapper)
        await wrapper.findComponent(QuestionEditor).vm.$emit('update:questions', [oldDraft, archived, edited, broken])
        const savedQuestions = [oldDraft, archived, { ...edited, reviewed: true }, broken]
        vi.mocked(axios.patch).mockResolvedValueOnce({ data: { catalog: { id: 4, title: 'Katalog', questions: savedQuestions } } })

        await clickText(wrapper, 'Fragen speichern')

        expect(axios.patch).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/catalogs/4', { title: 'Katalog', questions: savedQuestions })
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual(savedQuestions)
        expect(wrapper.text()).not.toContain('Aktiv für neue Tests')
        expect(wrapper.text()).not.toContain('Inhalt und Lösungen geprüft')
    })

    it('blocks release for ambiguous roster and invalidates preview after edits', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: tenQuestions() }] })
        await flushPromises()
        await clickText(wrapper, 'Katalog öffnen')
        await startNewTest(wrapper)
        await wrapper.find('input[name="mc-start-mode-new"][value="common"]').setValue(true)
        await wrapper.find('[aria-label="Testbezeichnung"]').setValue('Test A')
        expect(wrapper.find('[aria-label="Fragen pro Schüler"]').element.value).toBe('10')
        await wrapper.find('textarea').setValue('Vorname,Nachname,E-Mail\nTest,Person,test@example.invalid')
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { valid: false, errors: ['Doppelte E-Mail.'], rows: [{ row: 2, first_name: 'Test', last_name: 'Person', email: 'test@example.invalid', status: 'error', errors: ['Mehrdeutig'] }] } })

        await clickText(wrapper, 'Zuordnung prüfen')

        expect(wrapper.text()).toContain('Doppelte E-Mail.')
        expect(wrapper.findAll('button').find((item) => item.text() === 'Test erstellen').attributes('disabled')).toBeDefined()
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { valid: true, rows: [{ row: 2, first_name: 'Test', last_name: 'Person', status: 'matched' }] } })
        await clickText(wrapper, 'Zuordnung prüfen')
        expect(wrapper.findAll('button').find((item) => item.text() === 'Test erstellen').attributes('disabled')).toBeUndefined()
        await clickText(wrapper, 'Test erstellen')
        const summary = wrapper.find('[role="dialog"]').text()
        expect(summary).toContain('Test A')
        expect(summary).toContain('Katalog')
        expect(summary).toContain('1 eindeutig zugeordnet')
        expect(summary).toContain('10 Fragen · 10 Punkte')
        expect(summary).toContain('20 Minuten')
        expect(summary).toContain('30 Sekunden')
        await wrapper.find('textarea').setValue('Geänderte CSV')
        expect(wrapper.findAll('button').find((item) => item.text() === 'Test erstellen').attributes('disabled')).toBeDefined()
    })

    it('only sends invitations after preview and the distinct final confirmation', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [{ id: 7, catalog_id: 4, title: 'Test A' }] })
        await flushPromises()
        await openExistingTest(wrapper)

        await clickText(wrapper, 'Einladungen vorbereiten')
    await clickText(wrapper, 'Empfänger und E-Mail prüfen')

        expect(wrapper.text()).toContain('test@example.invalid')
        expect(axios.post).not.toHaveBeenCalled()
        expect(wrapper.findAll('button').find((button) => button.text() === '0 Einladungen per E-Mail senden').attributes('disabled')).toBeDefined()
        await wrapper.find('[aria-label="Einladung auswählen: Test Person"]').setValue(true)
        await clickText(wrapper, '1 Einladungen per E-Mail senden')
        expect(wrapper.findAll('[role="dialog"]').some(dialog => dialog.text().includes('echte E-Mails'))).toBe(true)
        expect(axios.post).not.toHaveBeenCalled()
        vi.mocked(axios.post).mockResolvedValue({ data: { sent: 1, skipped: 0 } })
        await clickText(wrapper, 'E-Mails senden')
        expect(axios.post).toHaveBeenCalledTimes(1)
        expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/invitations', { attempt_ids: [1], address_form: 'sie' })
    })

    it('renames explicitly without saving or losing unsaved question changes', async () => {
        const question = validQuestion({ reviewed: false })
        const catalog = { id: 4, title: 'Katalog', questions: [question] }
        const wrapper = renderWorkspace({ catalogs: [catalog] })
        await flushPromises()
        await editCatalog(wrapper)
        await wrapper.findComponent(QuestionEditor).vm.$emit('update:questions', [validQuestion({ prompt: 'Offene Änderung' })])
        await wrapper.find('[aria-label="Katalog umbenennen"]').trigger('click')
        await wrapper.find('[aria-label="Bezeichnung des Katalogs"]').setValue('Überarbeiteter Katalog')
        expect(axios.patch).not.toHaveBeenCalled()
        vi.mocked(axios.patch).mockResolvedValue({ data: { catalog: { ...catalog, title: 'Überarbeiteter Katalog' } } })

        await submitCatalogTitle(wrapper)

        expect(axios.patch).toHaveBeenCalledWith('/api/admin/multiple-choice/catalogs/4', { title: 'Überarbeiteter Katalog', questions: [question] })
        expect(wrapper.find('h2').text()).toBe('Überarbeiteter Katalog')
        expect(wrapper.find('[aria-label="Bezeichnung des Katalogs"]').exists()).toBe(false)
        expect(wrapper.findComponent(QuestionEditor).props('questions')[0].prompt).toBe('Offene Änderung')
        expect(wrapper.text()).toContain('Fragen speichern')
        await clickText(wrapper, 'Zur Katalogübersicht')
        expect(wrapper.find('[role="dialog"]').text()).toContain('Ungespeicherte Änderungen')
        await clickText(wrapper, 'Änderungen verwerfen')
        expect(wrapper.text()).toContain('Überarbeiteter Katalog')
    })

    it('cancels renaming while retaining question edits and saves questions independently of a name draft', async () => {
        const catalog = { id: 4, title: 'Katalog', questions: [validQuestion()] }
        const questions = [validQuestion({ prompt: 'Offene Frage', reviewed: false })]
        const wrapper = renderWorkspace({ catalogs: [catalog] })
        await flushPromises()
        await editCatalog(wrapper)
        await wrapper.findComponent(QuestionEditor).vm.$emit('update:questions', questions)
        await wrapper.find('[aria-label="Katalog umbenennen"]').trigger('click')
        await wrapper.find('[aria-label="Bezeichnung des Katalogs"]').setValue('Verwerfen')

        await clickText(wrapper, 'Abbrechen')

        expect(wrapper.find('h2').text()).toBe('Katalog')
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual(questions)
        expect(axios.patch).not.toHaveBeenCalled()
        await wrapper.find('[aria-label="Katalog umbenennen"]').trigger('click')
        await wrapper.find('[aria-label="Bezeichnung des Katalogs"]').setValue('Noch nicht speichern')
        const savedQuestions = [validQuestion({ prompt: 'Offene Frage', reviewed: true })]
        vi.mocked(axios.patch).mockResolvedValueOnce({ data: { catalog: { ...catalog, questions: savedQuestions } } })
        await clickText(wrapper, 'Fragen speichern')
        expect(axios.patch).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/catalogs/4', { title: 'Katalog', questions: savedQuestions })
        expect(wrapper.find('[aria-label="Bezeichnung des Katalogs"]').element.value).toBe('Noch nicht speichern')
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual(savedQuestions)
        await clickText(wrapper, 'Abbrechen')
        await clickText(wrapper, 'Zur Katalogübersicht')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    })

    it('validates renaming and retains the draft and questions after a failed save', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [] }] })
        await flushPromises()
        await editCatalog(wrapper)
        await wrapper.find('[aria-label="Katalog umbenennen"]').trigger('click')
        const name = wrapper.find('[aria-label="Bezeichnung des Katalogs"]')
        await name.setValue('   ')
        await submitCatalogTitle(wrapper)
        expect(wrapper.text()).toContain('Bitte gib eine Bezeichnung ein.')
        await name.setValue('A'.repeat(256))
        await submitCatalogTitle(wrapper)
        expect(wrapper.text()).toContain('höchstens 255 Zeichen')
        expect(axios.patch).not.toHaveBeenCalled()
        await name.setValue('Neuer Name')
        vi.mocked(axios.patch).mockRejectedValueOnce({ response: { data: { message: 'Speichern fehlgeschlagen.' } } })

        await submitCatalogTitle(wrapper)

        expect(wrapper.text()).toContain('Speichern fehlgeschlagen.')
        expect(name.element.value).toBe('Neuer Name')
        await clickText(wrapper, 'Abbrechen')
        expect(wrapper.find('h2').text()).toBe('Katalog')
    })

    it('closes an unchanged name without another save', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [] }] })
        await flushPromises()
        await editCatalog(wrapper)
        await wrapper.find('[aria-label="Katalog umbenennen"]').trigger('click')
        await wrapper.find('[aria-label="Bezeichnung des Katalogs"]').setValue('  Katalog  ')

        await submitCatalogTitle(wrapper)

        expect(wrapper.find('h2').text()).toBe('Katalog')
        expect(wrapper.find('[aria-label="Bezeichnung des Katalogs"]').exists()).toBe(false)
        expect(axios.patch).not.toHaveBeenCalled()
        expect(wrapper.text()).not.toContain('Fragen speichern')
    })

    it('imports and saves questions without reopening or saving the catalog name', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [] }] })
        await flushPromises()
        await editCatalog(wrapper)
        await clickText(wrapper, 'Vorlage importieren')
        vi.mocked(axios.post).mockResolvedValue({ data: { questions: [validQuestion({ active: false, reviewed: false })], errors: [], warnings: [], valid: true } })
        await chooseTemplate(wrapper, 'Isolierte Importvorlage')
        await clickText(wrapper, 'Import prüfen')
        const questions = [validQuestion()]
        vi.mocked(axios.patch).mockResolvedValueOnce({ data: { catalog: { id: 4, title: 'Katalog', questions } } })

        await clickText(wrapper, 'Fragen importieren')

        expect(axios.patch).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/catalogs/4', { title: 'Katalog', questions })
        expect(wrapper.find('h2').text()).toBe('Katalog')
        expect(wrapper.find('[aria-label="Bezeichnung des Katalogs"]').exists()).toBe(false)
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual(questions)
        expect(wrapper.text()).not.toContain('Fragen speichern')
        expect(wrapper.text()).toContain('Tests zu diesem Katalog')
        await startNewTest(wrapper)
        expect(wrapper.text()).toContain('Fragenkatalog: Katalog')
        expect(wrapper.find('[aria-label="Fragen pro Schüler"]').element.value).toBe('10')
    })

    it('prepares a test from a saved reviewed catalog without publishing or losing unsaved changes', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Netzwerke', questions: [validQuestion()] }] })
        await flushPromises()
        await clickText(wrapper, 'Katalog öffnen')
        expect(wrapper.text()).toContain('Dein Katalog ist bereit.')
        await clickText(wrapper, 'Bearbeiten')
        await wrapper.find('[aria-label="Katalog umbenennen"]').trigger('click')
        await wrapper.find('[aria-label="Bezeichnung des Katalogs"]').setValue('Noch nicht gespeichert')
        expect(wrapper.findAll('button').some((item) => item.text() === 'Test vorbereiten')).toBe(false)
        await clickText(wrapper, 'Abbrechen')

        await startNewTest(wrapper)

        expect(wrapper.text()).toContain('Fragenkatalog: Netzwerke')
        expect(wrapper.find('[aria-label="Fragen pro Schüler"]').element.value).toBe('10')
        expect(wrapper.find('[aria-label="Testbezeichnung"]').element.value).toBe('Netzwerke – Test')
        expect(axios.post).not.toHaveBeenCalled()
        expect(axios.patch).not.toHaveBeenCalled()
    })

    it('requires a deliberate shared countdown start and never starts it on page load', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [{ id: 7, catalog_id: 4, title: 'Test A' }] })
        await flushPromises()
        await openExistingTest(wrapper)
        expect(axios.post).not.toHaveBeenCalled()

        await clickText(wrapper, 'Countdown starten (30 s)')

        expect(wrapper.find('[role="dialog"]').text()).toContain('Später einsteigende Schüler')
        expect(axios.post).not.toHaveBeenCalled()
        vi.mocked(axios.post).mockResolvedValue({ data: {} })
        await clickText(wrapper, 'Countdown starten')
        expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/countdown')
    })

    it('distinguishes not-started attempts from completed zero scores and releases solutions explicitly', async () => {
        const test = { id: 7, catalog_id: 4, title: 'Test A', question_count: 3, total_points: 10, duration_seconds: 600, lead_seconds: 30, results_released_at: '2026-10-16T08:11:00Z', starts_at: '2026-10-16T08:00:00Z', ends_at: '2026-10-16T08:10:00Z' }
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test, server_now: '2026-10-16T08:11:00Z', attempts: [
            { id: 1, student_name: 'Nicht begonnen', status: 'not_started', started_at: null, score: 0, percentage: 0 },
            { id: 2, student_name: 'Null Punkte', status: 'submitted', started_at: '2026-10-16T08:01:00Z', completed_at: '2026-10-16T08:09:00Z', score: 0, percentage: 0 },
        ] })
        await flushPromises()
        await openExistingTest(wrapper)
        const rows = wrapper.findAll('tbody tr')
        expect(rows[0].text()).toContain('Noch nicht begonnen')
        expect(rows[0].findAll('td')[3].text()).toBe('—')
        expect(rows[1].text()).toContain('0 / 10')
        expect(axios.post).not.toHaveBeenCalled()

        await clickText(wrapper, 'Lösungen freigeben')

        expect(wrapper.find('[role="dialog"]').text()).toContain('richtigen Antworten')
        expect(axios.post).not.toHaveBeenCalled()
        vi.mocked(axios.post).mockResolvedValue({ data: {} })
        await clickText(wrapper, 'Freigeben')
        expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/release-results', { solutions: true })
    })
})

describe('Question review and answer editing', () => {
    it('resets a reviewed question after semantic edits and removes stale solutions', async () => {
        const wrapper = mount(QuestionEditor, { props: { questions: [validQuestion()] } })
        wrappers.push(wrapper)

        await wrapper.find('textarea').setValue('Geänderte Frage')

        expect(wrapper.emitted('update:questions')[0][0][0]).toMatchObject({ prompt: 'Geänderte Frage', reviewed: false })
        await wrapper.find('input[aria-label="Antwort 2 ist richtig"]').setValue(true)
        expect(wrapper.emitted('update:questions')[1][0][0].correct_option_ids).toEqual(['b'])
    })

    it('keeps multiple correct options and shows errors without confirmation checkboxes', async () => {
        const wrapper = mount(QuestionEditor, { props: { questions: [validQuestion({ type: 'multiple', reviewed: false })] } })
        wrappers.push(wrapper)

        await wrapper.find('input[aria-label="Antwort 2 ist richtig"]').setValue(true)

        expect(wrapper.emitted('update:questions')[0][0][0].correct_option_ids).toEqual(['a', 'b'])
        await wrapper.setProps({ questions: [validQuestion({ prompt: '', reviewed: false })] })
        expect(wrapper.text()).toContain('Fragetext fehlt.')
        expect(wrapper.text()).not.toContain('Aktiv für neue Tests')
        expect(wrapper.text()).not.toContain('Inhalt und Lösungen geprüft')
        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(0)
    })
})

describe('Automatic template format detection', () => {
    it.each([
        ['questions.JSON', 'application/json', '{"questions":[]}', 'json'],
        ['questions.txt', 'text/plain', '\uFEFF {"questions":[]}', 'json'],
        ['questions.md', '', '[{"prompt":"Frage"}]', 'json'],
        ['questions.md', 'text/markdown', '# 1. Frage\n- [x] Richtig\n- [ ] Falsch', 'markdown'],
        ['questions.TXT', 'application/octet-stream', '1. Frage\n- [x] Richtig', 'markdown'],
        ['questions', 'application/json', '[]', 'json'],
        ['questions', '', '{"questions":[]}', 'json'],
        ['questions', 'application/octet-stream', '# 1. Frage\n- [x] Richtig', 'markdown'],
        ['', '', '[Link](https://example.invalid)\n1. Frage', 'markdown'],
        ['', '', '{"questions":[]}', 'json'],
    ])('detects %s (%s) by metadata and content', (name, type, text, expected) => {
        expect(detectImportFormat(text, { name, type })).toBe(expected)
    })

    it.each([
        ['questions.pdf', 'application/pdf', 'Frage', '.md, .txt, .json'],
        ['questions.csv', 'text/plain', 'Frage', '.md, .txt, .json'],
        ['questions', '', 'Frage', 'nicht eindeutig'],
        ['questions.json', 'application/json', '1. Markdown-Frage', 'kein gültiges JSON'],
        ['questions.txt', 'text/plain', '{"questions":', 'kein gültiges JSON'],
        ['questions.md', '', '', 'Datei ist leer'],
        ['questions.txt', '', 'Binär\u0000daten', 'keine lesbare Textvorlage'],
    ])('explains unusable %s (%s)', (name, type, text, expected) => {
        expect(() => detectImportFormat(text, { name, type })).toThrow(expected)
    })
})

describe('FilePond roster selection', () => {
    const csv = 'Vorname,Nachname,E-Mail,Kurs,Klasse\nTest,Person,test@example.invalid,INF,5A'

    async function openRoster() {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }] })
        await flushPromises()
        await clickText(wrapper, 'Katalog öffnen')
        await startNewTest(wrapper)
        return wrapper
    }

    it('reads a CSV locally and sends its unchanged contents only on deliberate preview', async () => {
        const wrapper = await openRoster()
        expect(wrapper.findComponent({ name: 'FilePond' }).props()).toMatchObject({
            instantUpload: false, allowProcess: false, allowMultiple: false, server: null,
        })

        await chooseFile(wrapper, new File([csv], 'students.csv', { type: 'text/csv' }))

        expect(wrapper.text()).toContain('Ausgewählt: students.csv')
        expect(wrapper.find('textarea').element.value).toBe(csv)
        expect(axios.post).not.toHaveBeenCalled()
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { valid: true, rows: [] } })
        await clickText(wrapper, 'Zuordnung prüfen')
        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/roster-preview', { csv })
    })

    it('invalidates the roster preview and clears the CSV when its file is removed', async () => {
        const wrapper = await openRoster()
        await chooseFile(wrapper, new File([csv], 'students.csv'))
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { valid: true, rows: [{ status: 'matched' }] } })
        await clickText(wrapper, 'Zuordnung prüfen')
        expect(wrapper.text()).toContain('1 eindeutig zugeordnet')

        wrapper.findComponent({ name: 'FilePond' }).vm.$emit('updatefiles', [])
        await flushPromises()

        expect(wrapper.text()).not.toContain('1 eindeutig zugeordnet')
        expect(wrapper.text()).not.toContain('Ausgewählt:')
        expect(wrapper.find('textarea').element.value).toBe('')
        expect(wrapper.findAll('button').find((item) => item.text() === 'Zuordnung prüfen').attributes('disabled')).toBeDefined()
    })

    it('explains unsupported files and failed reads and recovers on CSV replacement', async () => {
        const wrapper = await openRoster()
        await chooseFile(wrapper, new File(['PDF'], 'students.pdf', { type: 'application/pdf' }))
        expect(wrapper.text()).toContain('Bitte wähle eine CSV-Datei (.csv) aus.')
        await chooseFile(wrapper, { name: 'students.csv', text: async () => { throw new Error('Internal read failure') } })
        expect(wrapper.text()).toContain('Die CSV-Datei konnte nicht gelesen werden.')
        expect(wrapper.text()).not.toContain('Internal read failure')
        expect(wrapper.findAll('button').find((item) => item.text() === 'Zuordnung prüfen').attributes('disabled')).toBeDefined()

        await chooseFile(wrapper, new File([csv], 'students.csv'))

        expect(wrapper.text()).not.toContain('Die CSV-Datei konnte nicht gelesen werden.')
        expect(wrapper.find('textarea').element.value).toBe(csv)
        expect(wrapper.findAll('button').find((item) => item.text() === 'Zuordnung prüfen').attributes('disabled')).toBeUndefined()
        expect(axios.post).not.toHaveBeenCalled()
    })

    it('discards an old pending CSV read after removal and replacement', async () => {
        const wrapper = await openRoster()
        let completeRead
        await chooseFile(wrapper, { name: 'slow.csv', text: () => new Promise((resolve) => { completeRead = resolve }) })
        expect(wrapper.findAll('button').find((item) => item.text() === 'Zuordnung prüfen').attributes('disabled')).toBeDefined()
        wrapper.findComponent({ name: 'FilePond' }).vm.$emit('updatefiles', [])
        await flushPromises()
        await chooseFile(wrapper, new File([csv], 'current.csv'))

        completeRead('Veraltete CSV')
        await flushPromises()

        expect(wrapper.find('textarea').element.value).toBe(csv)
        expect(wrapper.text()).toContain('Ausgewählt: current.csv')
        expect(axios.post).not.toHaveBeenCalled()
    })

    it('preserves manual CSV edits when an older file read finishes', async () => {
        const wrapper = await openRoster()
        let completeRead
        await chooseFile(wrapper, { name: 'slow.csv', text: () => new Promise((resolve) => { completeRead = resolve }) })
        await wrapper.find('textarea').setValue(csv)

        completeRead('Veraltete CSV')
        await flushPromises()

        expect(wrapper.find('textarea').element.value).toBe(csv)
        expect(wrapper.text()).not.toContain('Ausgewählt:')
        expect(wrapper.findAll('button').find((item) => item.text() === 'Zuordnung prüfen').attributes('disabled')).toBeUndefined()
    })
})

describe('FilePond template selection', () => {
    async function openImport() {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [] }] })
        await flushPromises()
        await editCatalog(wrapper)
        await clickText(wrapper, 'Vorlage importieren')
        return wrapper
    }

    const handshake = '{"schema_version":1,"module_id":"a263b873-4f27-4167-90f0-7d94ea0f67fa"}'
    const source = { module_id: 'a263b873-4f27-4167-90f0-7d94ea0f67fa', source_file_id: 'id:source', source_revision: 'r1',
        source_path: '/Materialien/Lerninhalte/Modul/Multiple Choice Fragen/Fragen.md' }
    const sourceResult = { module: { module_id: source.module_id, folder_path: '/Materialien/Lerninhalte/Modul' },
        sources: [{ id: 'id:source', name: 'Fragen.md' }], module_source: source, source_proof: 'verified-source', text: '### 1. Vorhandene Frage', format: 'markdown',
        preview: { valid: true, questions: [validQuestion()], errors: [], warnings: [] } }

    it.each(['success', 'failure', 'cancel'])('shows ongoing Dropbox work immediately and clears it on %s', async outcome => {
        const wrapper = await openImport()
        let finish
        let fail
        vi.mocked(axios.post).mockImplementationOnce(() => new Promise((resolve, reject) => { finish = resolve; fail = reject }))
        await chooseFile(wrapper, new File([handshake], 'handshake.json'))
        expect(wrapper.get('[data-testid="template-loading"]').text()).toContain('Lernmodul wird in Dropbox gesucht')
        expect(axios.patch).not.toHaveBeenCalled()
        if (outcome === 'cancel') {
            await clickText(wrapper, 'Import abbrechen')
            finish({ data: sourceResult })
        } else if (outcome === 'failure') {
            fail({ response: { data: { message: 'Quelle fehlt' } } })
        } else {
            finish({ data: sourceResult })
        }
        await flushPromises()
        expect(wrapper.find('[data-testid="template-loading"]').exists()).toBe(false)
        if (outcome === 'failure') expect(wrapper.text()).toContain('Quelle fehlt')
        if (outcome === 'cancel') expect(wrapper.text()).not.toContain('Noch nicht im Katalog gespeichert')
        if (outcome === 'success') expect(wrapper.text()).toContain('Noch nicht im Katalog gespeichert')
    })

    it('accepts handshake.json in the existing template dropzone and stages existing questions without generation or automatic catalog saving', async () => {
        const wrapper = await openImport()
        vi.mocked(axios.post).mockResolvedValueOnce({ data: sourceResult })

        await chooseFile(wrapper, new File([handshake], 'handshake.json', { type: '' }))

        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/import-preview', {
            filename: 'handshake.json', handshake, catalog_id: 4, source_id: null, generate: false,
        })
        expect(wrapper.text()).toContain(source.source_path)
        expect(wrapper.text()).toContain('Noch nicht im Katalog gespeichert')
        expect(wrapper.findAllComponents(QuestionEditor)[0].props('questions')[0].correct_option_ids).toEqual(['a'])
        expect(wrapper.findAllComponents(QuestionEditor)[1].props('questions')).toEqual([])
        expect(axios.patch).not.toHaveBeenCalled()

        vi.mocked(axios.post).mockResolvedValueOnce({ data: sourceResult.preview })
        vi.mocked(axios.patch).mockResolvedValueOnce({ data: { catalog: { id: 4, title: 'Katalog', questions: [validQuestion()], module_sources: [source] } } })
        expect(wrapper.text()).not.toContain('Entwurf in Katalog übernehmen')
        expect(wrapper.text()).not.toContain('Übernehmen und speichern')
        await clickText(wrapper, 'Fragen importieren')
        expect(axios.patch).toHaveBeenCalledTimes(1)
        expect(axios.patch).toHaveBeenCalledWith('/api/admin/multiple-choice/catalogs/4', expect.objectContaining({ module_source_proofs: ['verified-source'] }))
        expect(wrapper.text()).toContain(source.source_path)
        expect(wrapper.text()).not.toContain('Fragen speichern')
    })

    it('keeps the source preview and current catalog unchanged after a failed direct import save', async () => {
        const wrapper = await openImport()
        vi.mocked(axios.post).mockResolvedValueOnce({ data: sourceResult })
        await chooseFile(wrapper, new File([handshake], 'handshake.json'))
        vi.mocked(axios.post).mockResolvedValueOnce({ data: sourceResult.preview })
        vi.mocked(axios.patch).mockRejectedValueOnce({ response: { data: { message: 'Speichern fehlgeschlagen' } } })
        await clickText(wrapper, 'Fragen importieren')

        expect(axios.patch).toHaveBeenCalledTimes(1)
        expect(wrapper.text()).toContain('Speichern fehlgeschlagen')
        expect(wrapper.text()).toContain('Vorschau: 1 Fragen')
        expect(wrapper.findAllComponents(QuestionEditor)[1].props('questions')).toEqual([])
        expect(wrapper.text()).toContain('Fragen importieren')
        expect(wrapper.text()).not.toContain('Fragen importiert und gespeichert')
    })

    it('rejects invalid handshake contents locally and retains existing catalog questions after a failed or cancelled source lookup', async () => {
        const wrapper = await openImport()
        await chooseFile(wrapper, new File(['{"schema_version":2,"module_id":"invalid"}'], 'Handshake.json'))
        expect(axios.post).not.toHaveBeenCalled()
        expect(wrapper.text()).toContain('eine unterstützte Modulkennung')
        vi.mocked(axios.post).mockRejectedValueOnce({ response: { data: { message: 'Quelle fehlt' } } })
        await chooseFile(wrapper, new File([handshake], 'handshake.json'))
        expect(wrapper.text()).toContain('Quelle fehlt')
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual([])
        expect(axios.patch).not.toHaveBeenCalled()
        await clickText(wrapper, 'Import abbrechen')
        expect(axios.patch).not.toHaveBeenCalled()
    })

    it('offers a choice for multiple real templates instead of selecting the first file', async () => {
        const wrapper = await openImport()
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { module: sourceResult.module,
            sources: [{ id: 'id:first', name: 'Erste.md' }, { id: 'id:second', name: 'Zweite.md' }] } })
        await chooseFile(wrapper, new File([handshake], 'handshake.json'))
        expect(wrapper.text()).not.toContain('Vorschau:')
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { ...sourceResult, module_source: { ...source, source_file_id: 'id:second' } } })
        await wrapper.get('select[aria-label="Fragenvorlage auswählen"]').setValue('id:second')
        await flushPromises()
        expect(axios.post).toHaveBeenLastCalledWith('/api/admin/multiple-choice/import-preview', expect.objectContaining({ source_id: 'id:second', generate: false }))
        expect(axios.patch).not.toHaveBeenCalled()
    })

    it.each([
        ['questions.md', 'text/markdown', '# 1. Frage\n- [x] Richtig\n- [ ] Falsch', 'markdown'],
        ['questions.txt', 'text/plain', '{"questions":[]}', 'json'],
        ['questions.json', 'application/json', '{"questions":[]}', 'json'],
    ])('reads %s locally and previews the detected format deliberately', async (name, type, text, format) => {
        const wrapper = await openImport()
        const pond = wrapper.findComponent({ name: 'FilePond' })
        expect(pond.props()).toMatchObject({ instantUpload: false, allowProcess: false, allowMultiple: false, server: null })
        expect(wrapper.find('[aria-label="Format"]').exists()).toBe(false)

        await chooseFile(wrapper, new File([text], name, { type }))

        expect(wrapper.find('.mc-import textarea').exists()).toBe(false)
        expect(wrapper.text()).toContain(`Erkanntes Format: ${format === 'json' ? 'JSON' : 'Markdown / Text'}`)
        expect(axios.post).not.toHaveBeenCalled()
        expect(axios.patch).not.toHaveBeenCalled()
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { questions: [validQuestion()], errors: [], warnings: [], valid: true } })
        await clickText(wrapper, 'Import prüfen')
        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/import-preview', { text, format, generate: false })
        expect(wrapper.findAllComponents(QuestionEditor)[0].props('questions')[0].reviewed).toBe(false)
        expect(wrapper.findAllComponents(QuestionEditor)[1].props('questions')).toEqual([])
    })

    it('detects the format again after replacing the template file', async () => {
        const wrapper = await openImport()
        await chooseTemplate(wrapper, '{"questions":[]}')
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { questions: [], errors: [], warnings: [], valid: true } })

        await clickText(wrapper, 'Import prüfen')

        expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/import-preview', { text: '{"questions":[]}', format: 'json', generate: false })
        await chooseTemplate(wrapper, '1. Markdown-Frage')
        expect(wrapper.text()).toContain('Erkanntes Format: Markdown / Text')
        expect(wrapper.text()).not.toContain('Vorschau:')
    })

    it('clears a stale preview on replacement and explains unsupported files and malformed JSON', async () => {
        const wrapper = await openImport()
        await chooseTemplate(wrapper, '1. Frage')
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { questions: [validQuestion()], errors: [], warnings: [], valid: true } })
        await clickText(wrapper, 'Import prüfen')
        expect(wrapper.text()).toContain('Vorschau: 1 Fragen')

        await chooseFile(wrapper, new File(['PDF'], 'questions.pdf', { type: 'application/pdf' }))

        expect(wrapper.text()).toContain('.md, .txt, .json')
        expect(wrapper.text()).not.toContain('Vorschau:')
        expect(wrapper.findAll('button').find((item) => item.text() === 'Import prüfen').attributes('disabled')).toBeDefined()
        await chooseFile(wrapper, new File(['{"questions":'], 'questions.json', { type: 'application/json' }))
        expect(wrapper.text()).toContain('kein gültiges JSON')
        expect(axios.post).toHaveBeenCalledTimes(1)
        expect(axios.patch).not.toHaveBeenCalled()
    })

    it('rejects invalid UTF-8 and recovers when a valid file replaces it', async () => {
        const wrapper = await openImport()
        await chooseFile(wrapper, new File([new Uint8Array([0xc3, 0x28])], 'questions.txt', { type: 'text/plain' }))
        expect(wrapper.text()).toContain('Bitte speichere die Vorlage als UTF-8')

        await chooseFile(wrapper, new File(['1. Neue Frage'], 'questions.txt', { type: 'text/plain' }))

        expect(wrapper.text()).not.toContain('Bitte speichere die Vorlage als UTF-8')
        expect(wrapper.text()).toContain('Erkanntes Format: Markdown / Text')
        expect(wrapper.findAll('button').find((item) => item.text() === 'Import prüfen').attributes('disabled')).toBeUndefined()
        expect(axios.post).not.toHaveBeenCalled()
    })

    it('explains a failed file read without exposing technical errors', async () => {
        const wrapper = await openImport()

        await chooseFile(wrapper, { name: 'questions.md', type: 'text/markdown', arrayBuffer: async () => { throw new Error('Internal read failure') } })

        expect(wrapper.text()).toContain('Die Datei konnte nicht gelesen werden.')
        expect(wrapper.text()).not.toContain('Internal read failure')
        expect(wrapper.findComponent({ name: 'FilePond' }).props('disabled')).toBe(false)
        expect(axios.post).not.toHaveBeenCalled()
    })

    it('discards an in-flight file read after removal instead of restoring stale content', async () => {
        const wrapper = await openImport()
        let completeRead
        const pending = { name: 'slow.txt', type: 'text/plain', arrayBuffer: () => new Promise((resolve) => { completeRead = resolve }) }
        await chooseFile(wrapper, pending)
        expect(wrapper.findAll('button').find((item) => item.text() === 'Import prüfen').attributes('disabled')).toBeDefined()

        wrapper.findComponent({ name: 'FilePond' }).vm.$emit('updatefiles', [])
        await flushPromises()
        await chooseTemplate(wrapper, 'Frische Vorlage')
        completeRead(new TextEncoder().encode('Veralteter Inhalt').buffer)
        await flushPromises()

        expect(axios.post).not.toHaveBeenCalled()
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { questions: [], errors: [], warnings: [], valid: true } })
        await clickText(wrapper, 'Import prüfen')
        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/import-preview', {
            text: 'Frische Vorlage', format: 'markdown', generate: false,
        })
    })
})

describe('Test management on one screen', () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', question_count: 1, total_points: 10, duration_seconds: 600, lead_seconds: 30, starts_at: '2026-10-18T08:00:00Z', ends_at: '2026-10-18T08:10:00Z' }
    const catalogs = [{ id: 4, title: 'Katalog', questions: [validQuestion()] }]

    it('renames a test explicitly and preserves the old name when cancelled or rejected', async () => {
        const wrapper = renderWorkspace({ catalogs, tests: [test] }, { test })
        await flushPromises()
        await openExistingTest(wrapper)
        await wrapper.find('[aria-label="Test umbenennen"]').trigger('click')
        await flushPromises()
        await wrapper.find('[aria-label="Neue Testbezeichnung"]').setValue('Nicht speichern')
        await clickText(wrapper, 'Abbrechen')
        expect(axios.patch).not.toHaveBeenCalled()
        expect(wrapper.find('h2').text()).toBe('Test A')
        await wrapper.find('[aria-label="Test umbenennen"]').trigger('click')
        await flushPromises()
        await wrapper.find('[aria-label="Neue Testbezeichnung"]').setValue('   ')
        await wrapper.find('[role="dialog"] form').trigger('submit')
        await flushPromises()
        expect(axios.patch).not.toHaveBeenCalled()
        await wrapper.find('[aria-label="Neue Testbezeichnung"]').setValue('Neuer Name')
        vi.mocked(axios.patch).mockRejectedValueOnce({ response: { data: { message: 'Speichern fehlgeschlagen' } } })
        await wrapper.find('[role="dialog"] form').trigger('submit')
        await flushPromises()
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
        expect(wrapper.find('h2').text()).toBe('Test A')
        vi.mocked(axios.patch).mockResolvedValueOnce({ data: { test: { ...test, title: 'Neuer Name' } } })
        await wrapper.find('[role="dialog"] form').trigger('submit')
        await flushPromises()
        expect(axios.patch).toHaveBeenLastCalledWith('/api/admin/multiple-choice/tests/7', { title: 'Neuer Name' })
        expect(wrapper.find('h2').text()).toBe('Neuer Name')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    })

    it('shows elapsed submission times, automatic closure and direct personal links without another results page', async () => {
        const wrapper = renderWorkspace({ catalogs, tests: [test] }, { test, attempts: [
            { id: 1, student_name: 'Früh', status: 'submitted', started_at: '2026-10-18T08:01:00Z', completed_at: '2026-10-18T08:02:05Z', score: 10, percentage: 100, access_url: 'https://school.example/multiple-choice#access=first', question_results: [{ id: 'a', prompt: 'Direkte Auswertung', correct: true, points: 10 }] },
            { id: 2, student_name: 'Zeitende', status: 'timeout', started_at: '2026-10-18T08:00:05Z', completed_at: '2026-10-18T08:10:00Z', score: 0, percentage: 0 },
            { id: 3, student_name: 'Offen', status: 'in_progress', started_at: '2026-10-18T08:00:00Z', completed_at: null },
        ] })
        await flushPromises()
        await openExistingTest(wrapper)
        const rows = wrapper.findAll('tbody tr')
        expect(rows[0].text()).toContain('2 Min. 05 Sek.')
        expect(rows[1].text()).toContain('10 Min. 00 Sek. (automatisch)')
        expect(rows[2].text()).not.toContain('Min.')
        expect(wrapper.find('[href="https://school.example/multiple-choice#access=first"]').attributes('rel')).toBe('noopener noreferrer')
        expect(wrapper.text()).not.toContain('Ergebnisse ansehen')
        await rows[0].findAll('button').find(button => button.text() === 'Fragen ansehen')!.trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Direkte Auswertung')
        expect(wrapper.router.currentRoute.value.query.view).toBe('test')
    })

    it('previews the actual email and sends only selected recipients after confirmation', async () => {
        const wrapper = renderWorkspace({ catalogs, tests: [test] }, { test })
        await flushPromises()
        await openExistingTest(wrapper)
        const recipients = [
            { attempt_id: 1, student_name: 'Anna', email: 'anna@example.invalid', invited_at: null, subject: 'Einladung Test A', html: '<p>Guten Tag Anna</p>' },
            { attempt_id: 2, student_name: 'Bert', email: 'bert@example.invalid', invited_at: null, subject: 'Einladung Test A', html: '<p>Guten Tag Bert</p>' },
            { attempt_id: 3, student_name: 'Schon eingeladen', email: 'sent@example.invalid', invited_at: '2026-10-18T07:00:00Z' },
        ]
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { recipients } })
        await clickText(wrapper, 'Einladungen vorbereiten')
    await clickText(wrapper, 'Empfänger und E-Mail prüfen')
        expect(wrapper.find('[aria-label="Einladung auswählen: Anna"]').element.checked).toBe(false)
        expect(wrapper.find('[aria-label="Einladung auswählen: Schon eingeladen"]').attributes('disabled')).toBeDefined()
        expect(wrapper.find('iframe').attributes('srcdoc')).toBe('<p>Guten Tag Anna</p>')
        expect(wrapper.find('iframe').attributes('sandbox')).toBe('')
        await wrapper.findAll('button').filter((button) => button.text() === 'E-Mail ansehen')[1].trigger('click')
        expect(wrapper.find('iframe').attributes('srcdoc')).toBe('<p>Guten Tag Bert</p>')
        await wrapper.find('[aria-label="Alle noch nicht eingeladenen Schüler auswählen"]').setValue(true)
        expect(wrapper.find('[aria-label="Einladung auswählen: Anna"]').element.checked).toBe(true)
        await wrapper.find('[aria-label="Einladung auswählen: Anna"]').setValue(false)
        await clickText(wrapper, '1 Einladungen per E-Mail senden')
        expect(axios.post).not.toHaveBeenCalled()
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { sent: 1 } })
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { recipients } })
        await clickText(wrapper, 'E-Mails senden')
        expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/invitations', { attempt_ids: [2], address_form: 'sie' })
    })

    it('changes remaining time and closes a running test only after explicit confirmation', async () => {
        const wrapper = renderWorkspace({ catalogs, tests: [test] }, { test, server_now: '2026-10-18T08:02:00Z' })
        await flushPromises()
        await openExistingTest(wrapper)
        await clickText(wrapper, 'Restzeit ändern')
        expect(axios.patch).not.toHaveBeenCalled()
        await wrapper.find('[aria-label="Verbleibende Minuten"]').setValue('2')
        await wrapper.find('[aria-label="Zusätzliche Sekunden"]').setValue('15')
        vi.mocked(axios.patch).mockResolvedValueOnce({ data: {} })
        await wrapper.find('[role="dialog"] form').trigger('submit')
        await flushPromises()
        expect(axios.patch).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/remaining-time', { remaining_seconds: 135 })
        await clickText(wrapper, 'Test vorzeitig schließen')
        expect(axios.post).not.toHaveBeenCalled()
        await clickText(wrapper, 'Abbrechen')
        expect(axios.post).not.toHaveBeenCalled()
        await clickText(wrapper, 'Test vorzeitig schließen')
        vi.mocked(axios.post).mockResolvedValueOnce({ data: {} })
        await clickText(wrapper, 'Jetzt schließen')
        expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/close')
    })

    it('confirms test deletion and returns to the test overview', async () => {
        const wrapper = renderWorkspace({ catalogs, tests: [test] }, { test })
        await flushPromises()
        await openExistingTest(wrapper)
        await clickText(wrapper, 'Test löschen')
        expect(wrapper.find('[role="dialog"]').text()).toContain('Abgaben und Ergebnisse')
        await clickText(wrapper, 'Abbrechen')
        expect(axios.delete).not.toHaveBeenCalled()
        await clickText(wrapper, 'Test löschen')
        vi.mocked(axios.delete).mockResolvedValueOnce({ data: { deleted: true } })
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { available: true, catalogs, tests: [] } })
        await clickText(wrapper, 'Dauerhaft löschen')
        expect(axios.delete).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7')
        expect(wrapper.text()).toContain('Zu diesem Fragenkatalog gibt es noch keine Tests.')
    })
})


it('confirms the full catalog deletion scope and returns to the catalog overview', async () => {
    const catalogs = [{ id: 4, title: 'Zu löschen', questions: [validQuestion()] }]
    const wrapper = renderWorkspace({ catalogs, tests: [{ id: 7, catalog_id: 4, title: 'Ein Test' }] })
    await flushPromises()
    await clickText(wrapper, 'Katalog öffnen')
    await clickText(wrapper, 'Katalog löschen')
    expect(wrapper.find('[role="dialog"]').text()).toContain('seine 1 zugehörigen Tests')
    expect(axios.delete).not.toHaveBeenCalled()
    vi.mocked(axios.delete).mockResolvedValueOnce({ data: { deleted: true } })
    vi.mocked(axios.get).mockResolvedValueOnce({ data: { available: true, catalogs: [], tests: [] } })
    await clickText(wrapper, 'Dauerhaft löschen')
    expect(axios.delete).toHaveBeenCalledWith('/api/admin/multiple-choice/catalogs/4', { data: { test_ids: [7] } })
    expect(wrapper.text()).toContain('Meine Fragenkataloge')
    expect(wrapper.text()).toContain('Neuer Katalog')
})


it('requires a fresh preview after changing invitation address form and sends the reviewed form', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', question_count: 1, total_points: 10, duration_seconds: 600 }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Netzwerke', questions: [validQuestion()] }], tests: [test] }, { test })
    await flushPromises()
    await openExistingTest(wrapper)
    const defaultGet = vi.mocked(axios.get).getMockImplementation()
    vi.mocked(axios.get).mockImplementation(async (url, config) => {
        if (String(url).endsWith('/invitations')) {
            const form = config?.params?.address_form
            return { data: { address_form: form, recipients: [{ attempt_id: 1, student_name: 'Alex Beispiel', subject: 'Einladung', html: form === 'du' ? '<p>Lieber Alex,</p>' : '<p>Sehr geehrter Herr Beispiel,</p>' }] } }
        }
        return defaultGet(url, config)
    })
    await clickText(wrapper, 'Einladungen vorbereiten')
    await clickText(wrapper, 'Empfänger und E-Mail prüfen')
    expect(wrapper.find('iframe').attributes('srcdoc')).toContain('Sehr geehrter Herr Beispiel')
    await wrapper.find('[aria-label="Einladung auswählen: Alex Beispiel"]').setValue(true)
    await wrapper.find('[aria-label="Ansprache der Einladungen"]').setValue('du')
    expect(wrapper.find('iframe').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('1 Einladungen per E-Mail senden')
    await clickText(wrapper, 'Einladungen vorbereiten')
    await clickText(wrapper, 'Empfänger und E-Mail prüfen')
    expect(wrapper.find('iframe').attributes('srcdoc')).toContain('Lieber Alex')
    expect(wrapper.find('[aria-label="Einladung auswählen: Alex Beispiel"]').element.checked).toBe(false)
    await wrapper.find('[aria-label="Einladung auswählen: Alex Beispiel"]').setValue(true)
    vi.mocked(axios.post).mockResolvedValueOnce({ data: { sent: 1, skipped: 0 } })
    await clickText(wrapper, '1 Einladungen per E-Mail senden')
    await clickText(wrapper, 'E-Mails senden')
    expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/invitations', { attempt_ids: [1], address_form: 'du' })
})


it.each(['results', 'solutions'])('revokes %s only after deliberate confirmation', async (scope) => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', starts_at: '2026-10-16T08:00:00Z', ends_at: '2026-10-16T08:10:00Z', results_released_at: '2026-10-16T08:11:00Z', solutions_released_at: '2026-10-16T08:11:00Z' }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test })
    await flushPromises()
    await openExistingTest(wrapper)
    const label = scope === 'results' ? 'Ergebnisse verbergen' : 'Lösungen verbergen'
    await clickText(wrapper, label)
    expect(axios.delete).not.toHaveBeenCalled()
    await clickText(wrapper, 'Abbrechen')
    expect(axios.delete).not.toHaveBeenCalled()
    await clickText(wrapper, label)
    vi.mocked(axios.delete).mockResolvedValueOnce({ data: {} })
    await clickText(wrapper, 'Freigabe zurücknehmen')
    expect(axios.delete).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/release-results', { data: { scope } })
    expect(wrapper.text()).toContain(scope === 'results' ? 'Ergebnisse und Lösungen sind wieder verborgen.' : 'Lösungen sind wieder verborgen. Punkte bleiben sichtbar.')
})

it('edits a personal invitation without sending, previews escaped text and sends exactly the reviewed content', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A' }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test })
    await flushPromises()
    await openExistingTest(wrapper)
    const defaultGet = vi.mocked(axios.get).getMockImplementation()
    vi.mocked(axios.get).mockImplementation(async (url, config) => String(url).endsWith('/invitations')
        ? { data: { address_form: 'sie', recipients: [{ attempt_id: 1, student_name: 'Alex Beispiel', subject: 'Einladung', body: 'Guten Tag Alex Beispiel,', html: '<div data-mc-message="true"><p>Guten Tag Alex Beispiel,</p></div><a href="https://school.example/personal">Persönlichen Test öffnen</a>' }] } }
        : defaultGet(url, config))
    await clickText(wrapper, 'Einladungen vorbereiten')
    await clickText(wrapper, 'Empfänger und E-Mail prüfen')
    await wrapper.find('[aria-label="Betreff der Einladung"]').setValue('Individuelle Einladung')
    await wrapper.find('[aria-label="Nachrichtentext der Einladung"]').setValue('Hallo Alex,\n\nBitte <img src=x> beachten & mitmachen.')
    expect(axios.post).not.toHaveBeenCalled()
    const html = wrapper.find('iframe').attributes('srcdoc')
    expect(html).toContain('Hallo Alex,', '&lt;img src=x&gt;', '&amp; mitmachen.', 'https://school.example/personal')
    expect(html).not.toContain('<img')
    await wrapper.find('[aria-label="Einladung auswählen: Alex Beispiel"]').setValue(true)
    vi.mocked(axios.post).mockResolvedValueOnce({ data: { sent: 1 } })
    await clickText(wrapper, '1 Einladungen per E-Mail senden')
    await clickText(wrapper, 'E-Mails senden')
    expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/invitations', {
        attempt_ids: [1], address_form: 'sie', messages: [{ attempt_id: 1, subject: 'Individuelle Einladung', body: 'Hallo Alex,\n\nBitte <img src=x> beachten & mitmachen.' }],
    })
})


it('grants an individual extra time only for the deliberately selected person after confirmation', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', starts_at: '2026-10-16T08:00:00Z', ends_at: '2026-10-16T08:10:00Z' }
    const attempts = [
        { id: 1, student_name: 'Alex', status: 'submitted', completed_at: '2026-10-16T08:09:00Z', score: 10 },
        { id: 2, student_name: 'Sam', status: 'timeout', completed_at: '2026-10-16T08:10:00Z', score: 5 },
    ]
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test, attempts, can_reopen_attempts: true, server_now: '2026-10-16T08:11:00Z' })
    await flushPromises()
    await openExistingTest(wrapper)
    expect(wrapper.find('section[aria-label="Schülerübersicht"]').find('input[type="checkbox"]').exists()).toBe(false)
    await clickText(wrapper, 'Wieder öffnen')
    await wrapper.find('[aria-label="Test zum Wiederöffnen auswählen: Alex"]').setValue(true)
    expect(wrapper.find('[role="dialog"]').text()).toContain('Alex')
    expect(wrapper.find('[role="dialog"]').text()).toContain('Nicht ausgewählte Abgaben werden nicht verändert')
    expect(axios.post).not.toHaveBeenCalled()
    await clickText(wrapper, 'Abbrechen')
    expect(axios.post).not.toHaveBeenCalled()
    await clickText(wrapper, 'Wieder öffnen')
    await wrapper.find('[aria-label="Test zum Wiederöffnen auswählen: Alex"]').setValue(true)
    await wrapper.find('[aria-label="Persönliche Zusatzzeit in Minuten"]').setValue('2')
    await wrapper.find('[aria-label="Persönliche zusätzliche Sekunden"]').setValue('15')
    vi.mocked(axios.post).mockResolvedValueOnce({ data: {} })
    await wrapper.find('[role="dialog"] form').trigger('submit')
    await flushPromises()
    expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/attempts/reopen', { attempt_ids: [1], remaining_seconds: 135 })
    expect(axios.post).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Zusatzzeit für 1 ausgewählte Person freigegeben')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
})

it('shows independently running extra time and disables release when no completed attempt exists', async () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-16T08:11:00Z'))
    const test = { id: 7, catalog_id: 4, title: 'Test A', starts_at: '2026-10-16T08:00:00Z', ends_at: '2026-10-16T08:10:00Z' }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test,
        can_reopen_attempts: true, server_now: '2026-10-16T08:11:00Z', attempts: [{ id: 1, student_name: 'Alex', status: 'in_progress', completed_at: null, reopened_until: '2026-10-16T08:13:15Z' }] })
    await flushPromises()
    await openExistingTest(wrapper)
    expect(wrapper.text()).toContain('Persönliche Zusatzzeit läuft')
    expect(wrapper.text()).toContain('Zusatzzeit: 2 Min. 15 Sek.')
    expect(wrapper.text()).toContain('Wieder öffnen')
    expect(wrapper.findAll('button').find(button => button.text() === 'Ergebnisse freigeben').element.disabled).toBe(true)
    expect(axios.post).not.toHaveBeenCalled()
})


it('selects none by default, supports select-all and sends only the visibly confirmed selection for reopening', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', starts_at: '2026-10-16T08:00:00Z', ends_at: '2026-10-16T08:10:00Z' }
    const attempts = [{ id: 1, student_name: 'Alex', status: 'submitted' }, { id: 2, student_name: 'Sam', status: 'timeout' },
        { id: 3, student_name: 'Kim', status: 'in_progress', reopened_until: '2026-10-16T08:15:00Z', effective_ends_at: '2026-10-16T08:15:00Z' },
        { id: 4, student_name: 'Robin', status: 'not_started' }]
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test, attempts, can_reopen_attempts: true, server_now: '2026-10-16T08:11:00Z' })
    await flushPromises()
    await openExistingTest(wrapper)
    await clickText(wrapper, 'Wieder öffnen')
    const all = () => wrapper.find('[aria-label="Alle Schüler zum Wiederöffnen auswählen"]')
    const alex = () => wrapper.find('[aria-label="Test zum Wiederöffnen auswählen: Alex"]')
    const sam = () => wrapper.find('[aria-label="Test zum Wiederöffnen auswählen: Sam"]')
    expect(all().element.checked).toBe(false)
    expect(alex().element.checked).toBe(false)
    expect(sam().element.checked).toBe(false)
    expect(wrapper.findAll('button').find(button => button.text() === 'Zusatzzeit freigeben').element.disabled).toBe(true)
    expect(wrapper.find('[aria-label="Test zum Wiederöffnen auswählen: Kim"]').element.disabled).toBe(true)
    expect(wrapper.find('[aria-label="Test zum Wiederöffnen auswählen: Robin"]').element.disabled).toBe(true)
    await alex().setValue(true)
    expect(all().element.indeterminate).toBe(true)
    await all().setValue(true)
    expect(alex().element.checked).toBe(true)
    expect(sam().element.checked).toBe(true)
    await all().setValue(false)
    expect(alex().element.checked).toBe(false)
    expect(sam().element.checked).toBe(false)
    await sam().setValue(true)
    expect(wrapper.find('[role="dialog"]').text()).toContain('1 von 2 geeigneten Personen ausgewählt')
    expect(axios.post).not.toHaveBeenCalled()
    await clickText(wrapper, 'Abbrechen')
    await clickText(wrapper, 'Wieder öffnen')
    expect(sam().element.checked).toBe(false)
    await all().setValue(true)
    expect(wrapper.find('[role="dialog"]').text()).toContain('Alex')
    expect(wrapper.find('[role="dialog"]').text()).toContain('Sam')
    vi.mocked(axios.post).mockResolvedValueOnce({ data: { reopened: 2 } })
    await wrapper.find('[role="dialog"] form').trigger('submit')
    await flushPromises()
    expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/attempts/reopen', { attempt_ids: [1, 2], remaining_seconds: 300 })
    expect(axios.post).toHaveBeenCalledTimes(1)
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
})


it('copies the personal link and offers a manual fallback if clipboard access fails', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    const previous = Object.getOwnPropertyDescriptor(navigator, 'clipboard')
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText } })
    try {
        const test = { id: 7, catalog_id: 4, title: 'Test A' }
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test, attempts: [{ id: 1, student_name: 'Alex', access_url: 'https://school.example/multiple-choice#access=personal' }] })
        await flushPromises()
        await openExistingTest(wrapper)
        await wrapper.find('[aria-label="Zugangslink von Alex kopieren"]').trigger('click')
        await flushPromises()
        expect(writeText).toHaveBeenCalledWith('https://school.example/multiple-choice#access=personal')
        expect(wrapper.text()).toContain('Zugangslink für Alex kopiert.')
        writeText.mockRejectedValueOnce(new Error('denied'))
        await wrapper.find('[aria-label="Zugangslink von Alex kopieren"]').trigger('click')
        await flushPromises()
        expect(wrapper.find('[aria-label="Persönlicher Zugangslink"]').element.value).toContain('#access=personal')
    } finally {
        if (previous) Object.defineProperty(navigator, 'clipboard', previous)
        else delete navigator.clipboard
    }
})

it('edits only one personal closing deadline and can restore the shared default', async () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-16T08:00:00Z'))
    const test = { id: 7, catalog_id: 4, title: 'Test A', start_mode: 'individual', closes_at: '2026-10-17T09:00:00Z' }
    const attempts = [{ id: 1, student_name: 'Alex', closing_deadline: test.closes_at, closes_at: '2026-10-17T10:00:00Z' }, { id: 2, student_name: 'Sam', closing_deadline: test.closes_at }]
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test, attempts, server_now: '2026-10-16T08:00:00Z' })
    await flushPromises()
    await openExistingTest(wrapper)
    expect(wrapper.text()).not.toContain('Countdown starten')
    await wrapper.find('[aria-label="Schlussfrist für Alex ändern"]').trigger('click')
    await wrapper.find('[aria-label="Persönliche Schlussfrist"]').setValue('2026-10-18T12:00')
    vi.mocked(axios.patch).mockResolvedValue({ data: {} })
    await wrapper.find('[role="dialog"] form').trigger('submit')
    await flushPromises()
    expect(axios.patch).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/attempts/1/closing-deadline', { closes_at: '2026-10-18T10:00:00.000Z' })
    await wrapper.find('[aria-label="Schlussfrist für Alex ändern"]').trigger('click')
    await clickText(wrapper, 'Frist für alle übernehmen')
    expect(axios.patch).toHaveBeenLastCalledWith('/api/admin/multiple-choice/tests/7/attempts/1/closing-deadline', { closes_at: null })
})


it('defaults to personal start and offers a 30-second countdown for common start', async () => {
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }] })
    await flushPromises()
    await clickText(wrapper, 'Katalog öffnen')
    await startNewTest(wrapper)
    expect(wrapper.find('input[name="mc-start-mode-new"][value="individual"]').element.checked).toBe(true)
    expect(wrapper.find('[aria-label="Vorlaufzeit in Sekunden"]').exists()).toBe(false)
    expect(wrapper.find('[aria-label="Schlussfrist für alle"]').exists()).toBe(true)
    expect(wrapper.findAll('input[name="mc-start-mode-new"]')).toHaveLength(2)
    expect(wrapper.text()).toContain('Die Lehrkraft startet den Countdown.')
    expect(wrapper.text()).toContain('Jede Person startet ihre eigene Bearbeitungszeit.')
    expect(wrapper.text()).toContain('Ab dann läuft die Zeit ohne Pause')
    await wrapper.find('input[name="mc-start-mode-new"][value="common"]').setValue(true)
    expect(wrapper.find('[aria-label="Vorlaufzeit in Sekunden"]').element.value).toBe('30')
    expect(wrapper.find('[aria-label="Schlussfrist für alle"]').exists()).toBe(false)
    await wrapper.find('input[name="mc-start-mode-new"][value="individual"]').setValue(true)
    expect(wrapper.find('[aria-label="Schlussfrist für alle"]').exists()).toBe(true)
})


it('shows frequency, current absence, total duration and each interval in the teacher overview', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', exam_mode: true }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test, attempts: [{ id: 1, student_name: 'Alex', activity: { state: 'away', away_count: 2, away_seconds: 75, fullscreen_exit_count: 1, periods: [{ left_at: '2026-10-16T08:01:00Z', returned_at: '2026-10-16T08:02:15Z', seconds: 75, reason: 'hidden' }] } }] })
    await flushPromises()
    await openExistingTest(wrapper)
    expect(wrapper.text()).toContain('Testseite verlassen')
    expect(wrapper.text()).toContain('2 Wechsel · 1 Min. 15 Sek. außerhalb')
    expect(wrapper.text()).toContain('Vollbild verlassen: 1×')
    await wrapper.find('[aria-label="Prüfungsprotokoll von Alex ansehen"]').trigger('click')
    expect(wrapper.text()).toContain('Testseite ausgeblendet')
})

it.each(['common', 'individual', 'extra-time'])('pauses resumes and confirms early closing in the control area for %s timing', async timing => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', start_mode: timing === 'individual' ? 'individual' : 'common',
        starts_at: timing === 'individual' ? null : '2026-10-16T08:00:00Z',
        ends_at: timing === 'extra-time' ? '2026-10-16T07:59:00Z' : '2026-10-16T08:10:00Z', closes_at: '2026-10-16T09:00:00Z' }
    const attempts = [{ id: 1, student_name: 'Alex', status: 'in_progress', started_at: '2026-10-16T08:00:00Z',
        effective_ends_at: '2026-10-16T08:10:00Z', reopened_until: timing === 'extra-time' ? '2026-10-16T08:10:00Z' : null }]
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test, attempts, server_now: '2026-10-16T08:01:00Z' }, '/admin/teaching-tools/multiple-choice?catalog=4&view=test&test=7&pane=control', true)
    await flushPromises()
    const control = () => wrapper.find('section[aria-label="Prüfung steuern"]')
    expect(control().isVisible()).toBe(true)
    expect(control().text()).toContain('Alle pausieren')
    expect(control().text()).toContain('Test vorzeitig schließen')
    const get = vi.mocked(axios.get).getMockImplementation()
    let paused = false
    vi.mocked(axios.get).mockImplementation(async (url, config) => String(url).endsWith('/tests/7') ? { data: { test: { ...test, paused_at: paused ? '2026-10-16T08:01:00Z' : null }, attempts, server_now: '2026-10-16T08:01:00Z' } } : get(url, config))
    vi.mocked(axios.post).mockImplementation(async url => { paused = String(url).endsWith('/pause'); return { data: {} } })
    await clickText(wrapper, 'Alle pausieren')
    expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/pause')
    expect(wrapper.text()).toContain('Test pausiert')
    expect(control().text()).not.toContain('Test vorzeitig schließen')
    await clickText(wrapper, 'Test fortsetzen')
    expect(axios.post).toHaveBeenLastCalledWith('/api/admin/multiple-choice/tests/7/resume')
    await clickText(wrapper, 'Test vorzeitig schließen')
    expect(axios.post).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[role="dialog"]').text()).toContain('bisher gespeicherten Antworten')
    await clickText(wrapper, 'Jetzt schließen')
    expect(axios.post).toHaveBeenLastCalledWith('/api/admin/multiple-choice/tests/7/close')
    expect(axios.post).toHaveBeenCalledTimes(3)
})

it('selects only finished individual attempts with an open closing deadline and preserves the time confirmation', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', start_mode: 'individual', closes_at: '2026-10-16T09:00:00Z', exam_mode: false }
    const attempts = [
        { id: 1, student_name: 'Alex', status: 'submitted', started_at: '2026-10-16T08:00:00Z', completed_at: '2026-10-16T08:09:00Z' },
        { id: 2, student_name: 'Sam', status: 'in_progress', started_at: '2026-10-16T08:05:00Z', effective_ends_at: '2026-10-16T08:15:00Z' },
        { id: 3, student_name: 'Kim', status: 'not_started' },
        { id: 4, student_name: 'Robin', status: 'timeout', started_at: '2026-10-16T08:00:00Z', completed_at: '2026-10-16T08:10:00Z', closing_deadline: '2026-10-16T08:10:00Z' },
    ]
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] },
        { test, attempts, can_reopen_attempts: true, server_now: '2026-10-16T08:11:00Z' }, '/admin/teaching-tools/multiple-choice?catalog=4&view=test&test=7&pane=control', true)
    await flushPromises()
    expect(wrapper.find('section[aria-label="Prüfung steuern"]').isVisible()).toBe(true)

    await clickText(wrapper, 'Wieder öffnen')

    expect(wrapper.find('[role="dialog"]').text()).toContain('persönliche Schlussfrist')
    for (const name of ['Sam', 'Kim', 'Robin']) expect(wrapper.find(`[aria-label="Test zum Wiederöffnen auswählen: ${name}"]`).element.disabled).toBe(true)
    await wrapper.find('[aria-label="Alle Schüler zum Wiederöffnen auswählen"]').setValue(true)
    expect(wrapper.find('[role="dialog"]').text()).toContain('1 von 1 geeigneten Personen ausgewählt')
    await wrapper.find('[aria-label="Persönliche Zusatzzeit in Minuten"]').setValue('0')
    expect(wrapper.findAll('button').find(button => button.text() === 'Zusatzzeit freigeben').element.disabled).toBe(true)
    await wrapper.find('[aria-label="Persönliche zusätzliche Sekunden"]').setValue('30')
    expect(axios.post).not.toHaveBeenCalled()
    vi.mocked(axios.post).mockResolvedValueOnce({ data: {} })
    await wrapper.find('[role="dialog"] form').trigger('submit')
    await flushPromises()

    expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/attempts/reopen', { attempt_ids: [1], remaining_seconds: 30 })
    expect(axios.patch).not.toHaveBeenCalled()
})

it('keeps exam mode unchanged after completion and makes it available again after a confirmed reopening', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', starts_at: '2026-10-16T08:00:00Z', ends_at: '2026-10-16T08:10:00Z', exam_mode: true }
    const attempts = [{ id: 1, student_name: 'Alex', status: 'submitted', completed_at: '2026-10-16T08:09:00Z' }]
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] },
        { test, attempts, can_reopen_attempts: true, server_now: '2026-10-16T08:11:00Z' })
    await flushPromises()
    await openExistingTest(wrapper)
    const examMode = () => wrapper.findAll('button').find(button => button.text() === 'Prüfungsmodus deaktivieren')
    expect(examMode().element.disabled).toBe(true)
    expect(wrapper.findAll('button').some(button => button.text() === 'Alle pausieren' || button.text() === 'Test vorzeitig schließen')).toBe(false)
    await clickText(wrapper, 'Wieder öffnen')
    await wrapper.find('[aria-label="Alle Schüler zum Wiederöffnen auswählen"]').setValue(true)
    const get = vi.mocked(axios.get).getMockImplementation()
    vi.mocked(axios.get).mockImplementation(async (url, config) => String(url).endsWith('/tests/7') ? { data: { test, attempts: [{ ...attempts[0], status: 'in_progress', completed_at: null, reopened_until: '2026-10-16T08:16:00Z' }], can_reopen_attempts: true, server_now: '2026-10-16T08:11:00Z' } } : get(url, config))
    vi.mocked(axios.post).mockResolvedValueOnce({ data: {} })

    await wrapper.find('[role="dialog"] form').trigger('submit')
    await flushPromises()

    expect(examMode().element.disabled).toBe(false)
    expect(axios.patch).not.toHaveBeenCalled()
    expect(axios.post).toHaveBeenCalledTimes(1)
})

it.each([false, true])('prevents reopening during the common working period or a pause (paused=%s)', async paused => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', starts_at: '2026-10-16T08:00:00Z', ends_at: '2026-10-16T08:10:00Z', paused_at: paused ? '2026-10-16T08:11:00Z' : null }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] },
        { test, attempts: [{ id: 1, student_name: 'Alex', status: 'submitted', completed_at: '2026-10-16T08:01:00Z' }], can_reopen_attempts: true, server_now: '2026-10-16T08:02:00Z' })
    await flushPromises()
    await openExistingTest(wrapper)

    expect(wrapper.findAll('button').find(button => button.text() === 'Wieder öffnen').element.disabled).toBe(true)
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    if (!paused) expect(wrapper.find('section[aria-label="Prüfung steuern"]').text()).toContain('Test vorzeitig schließen')
    expect(axios.post).not.toHaveBeenCalled()
})

it('explains an expired individual closing deadline even when no participant can currently be reopened', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', start_mode: 'individual', closes_at: '2026-10-16T08:10:00Z' }
    const attempts = [{ id: 1, student_name: 'Alex', status: 'submitted', started_at: '2026-10-16T08:00:00Z', completed_at: '2026-10-16T08:09:00Z' }]
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] },
        { test, attempts, can_reopen_attempts: true, server_now: '2026-10-16T08:11:00Z' })
    await flushPromises()
    await openExistingTest(wrapper)

    await clickText(wrapper, 'Wieder öffnen')

    expect(wrapper.find('[role="dialog"]').text()).toContain('Bitte zuerst die persönliche Schlussfrist in der Schülerübersicht verlängern.')
    expect(wrapper.find('[aria-label="Test zum Wiederöffnen auswählen: Alex"]').element.disabled).toBe(true)
    expect(wrapper.findAll('button').find(button => button.text() === 'Zusatzzeit freigeben').element.disabled).toBe(true)
    expect(axios.post).not.toHaveBeenCalled()
})

it('does not submit a reopening selection after its individual closing deadline has elapsed', async () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-16T08:11:00Z'))
    const test = { id: 7, catalog_id: 4, title: 'Test A', start_mode: 'individual', closes_at: '2026-10-16T08:11:01Z' }
    const attempts = [{ id: 1, student_name: 'Alex', status: 'submitted', started_at: '2026-10-16T08:00:00Z', completed_at: '2026-10-16T08:09:00Z' }]
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] },
        { test, attempts, can_reopen_attempts: true, server_now: '2026-10-16T08:11:00Z' })
    await flushPromises()
    await openExistingTest(wrapper)
    await clickText(wrapper, 'Wieder öffnen')
    await wrapper.find('[aria-label="Alle Schüler zum Wiederöffnen auswählen"]').setValue(true)

    await vi.advanceTimersByTimeAsync(1500)
    await wrapper.find('[role="dialog"] form').trigger('submit')
    await flushPromises()

    expect(wrapper.findAll('button').find(button => button.text() === 'Zusatzzeit freigeben').element.disabled).toBe(true)
    expect(wrapper.find('[aria-label="Test zum Wiederöffnen auswählen: Alex"]').element.checked).toBe(false)
    expect(axios.post).not.toHaveBeenCalled()
})


it('labels expired monitoring as timed out and does not call its capped absence period ongoing', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', exam_mode: true }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test, attempts: [{ id: 1, student_name: 'Alex', activity: { state: 'expired', away_count: 1, away_seconds: 60, periods: [{ left_at: '2026-10-16T08:01:00Z', returned_at: null, ended_at: '2026-10-16T08:02:00Z', seconds: 60, reason: 'hidden' }] } }] })
    await flushPromises()
    await openExistingTest(wrapper)
    expect(wrapper.text()).toContain('Zeit abgelaufen')
    expect(wrapper.text()).not.toContain('Testseite verlassen')
    await wrapper.find('[aria-label="Prüfungsprotokoll von Alex ansehen"]').trigger('click')
    expect(wrapper.text()).not.toContain('noch außerhalb')
});



it('keeps invitation options out of the overview until explicitly opened and never sends during preparation', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A' }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test })
    await flushPromises()
    await openExistingTest(wrapper)
    expect(wrapper.find('[aria-label="Ansprache der Einladungen"]').exists()).toBe(false)
    const calls = vi.mocked(axios.get).mock.calls.length
    await clickText(wrapper, 'Einladungen vorbereiten')
    expect(wrapper.find('[role="dialog"]').text()).toContain('Wähle zuerst die Ansprache')
    expect(axios.get).toHaveBeenCalledTimes(calls)
    await wrapper.find('[aria-label="Ansprache der Einladungen"]').setValue('du')
    await clickText(wrapper, 'Empfänger und E-Mail prüfen')
    expect(axios.get).toHaveBeenLastCalledWith('/api/admin/multiple-choice/tests/7/invitations', { params: { address_form: 'du' } })
    expect(axios.post).not.toHaveBeenCalled()
})

it('edits settings before the first start and submits all timing and selection options explicitly', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', question_count: 1, total_points: 10, duration_seconds: 600, lead_seconds: 30, start_mode: 'common', exam_mode: false, balance_types: true, shuffle_questions: true, shuffle_options: true }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test, can_edit_settings: true, question_pool_count: 2 })
    await flushPromises()
    await openExistingTest(wrapper)
    await clickText(wrapper, 'Einstellungen bearbeiten')
    expect(wrapper.find('[aria-label="Testbezeichnung"]').element.value).toBe('Test A')
    await wrapper.find('[aria-label="Testbezeichnung"]').setValue('Geänderter Test')
    await wrapper.find('[aria-label="Bearbeitungszeit in Minuten"]').setValue('15')
    await wrapper.find('input[name="mc-start-mode-settings"][value="individual"]').setValue(true)
    await wrapper.find('[aria-label="Letzter Abgabezeitpunkt"]').trigger('click')
    await wrapper.find('[aria-label="Datum der Schlussfrist"]').setValue('2027-01-15')
    await wrapper.find('[aria-label="Uhrzeit der Schlussfrist"]').setValue('12:30')
    await wrapper.findAll('[role="dialog"]').at(-1).find('form').trigger('submit')
    await flushPromises()
    expect(axios.patch).not.toHaveBeenCalled()
    vi.mocked(axios.patch).mockResolvedValueOnce({ data: { test: { ...test, title: 'Geänderter Test' } } })
    await clickText(wrapper, 'Einstellungen speichern')
    expect(axios.patch).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/settings', expect.objectContaining({ title: 'Geänderter Test', duration_seconds: 900, start_mode: 'individual', closes_at: '2027-01-15T11:30:00.000Z', question_count: 1, total_points: 10 }))
    expect(wrapper.text()).toContain('Testeinstellungen gespeichert.')
})

it('shows protected settings after a test has started', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', question_count: 1, duration_seconds: 600 }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test, can_edit_settings: false })
    await flushPromises()
    await openExistingTest(wrapper)
    await clickText(wrapper, 'Einstellungen bearbeiten')
    expect(wrapper.find('.mc-settings-fields').element.disabled).toBe(true)
    expect(wrapper.find('input[name="mc-start-mode-settings"][value="common"]').element.checked).toBe(true)
    expect(wrapper.find('[aria-label="Countdown in Sekunden"]').element.value).toBe('60')
    expect(wrapper.text()).toContain('Fragen und Bewertungsgrundlagen bleiben geschützt.')
    expect(wrapper.findAll('button').some(button => button.text() === 'Einstellungen speichern')).toBe(false)
    expect(axios.patch).not.toHaveBeenCalled()
})

it('downloads the selected test export without sending invitations or starting the test', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', status: 'published' }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test })
    await flushPromises()
    await openExistingTest(wrapper)
    const packageData = { schema_version: 1, kind: 'schooltool.multiple_choice_links', test: { id: 7 }, participants: [] }
    const originalUrl = URL
    const createObjectURL = vi.fn().mockReturnValue('blob:mc-export')
    const revokeObjectURL = vi.fn()
    let downloadedName = ''
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function () {
        downloadedName = this.download
        expect(this.href).toBe('blob:mc-export')
    })
    vi.stubGlobal('URL', class extends originalUrl {
        static createObjectURL = createObjectURL
        static revokeObjectURL = revokeObjectURL
    })
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    vi.mocked(axios.get).mockResolvedValueOnce({ data: packageData, headers: { 'content-disposition': 'attachment; filename="MC-Test_e-mails-test_2026-10-19_Nr-7.json"' } })
    try {
        await clickText(wrapper, 'Für Leistungsfeststellung exportieren')
        expect(axios.get).toHaveBeenLastCalledWith('/api/admin/multiple-choice/tests/7/exercise-export')
        expect(downloadedName).toBe('MC-Test_e-mails-test_2026-10-19_Nr-7.json')
        const blob = createObjectURL.mock.calls[0][0]
        const text = await new Promise<string>(resolve => {
            const reader = new FileReader()
            reader.onload = () => resolve(String(reader.result))
            reader.readAsText(blob)
        })
        expect(JSON.parse(text)).toEqual(packageData)
        expect(axios.post).not.toHaveBeenCalled()
        expect(wrapper.text()).toContain('Export mit persönlichen Testlinks heruntergeladen')
        expect(document.querySelector('a[download="MC-Test_e-mails-test_2026-10-19_Nr-7.json"]')).toBeNull()
        vi.advanceTimersByTime(1000)
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:mc-export')
    } finally {
        click.mockRestore()
        vi.unstubAllGlobals()
        vi.useRealTimers()
    }
})

it('writes all personal links into the selected local folder without replacing an existing export', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', status: 'published', start_mode: 'individual', starts_at: null, ends_at: null, closes_at: '2027-01-15T12:00:00Z' }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test })
    await flushPromises()
    await openExistingTest(wrapper)
    const packageData = { schema_version: 1, kind: 'schooltool.multiple_choice_links', test: { id: 7 }, participants: [
        { participant_id: 'user:1', access_url: 'https://example.invalid/multiple-choice#access=first' },
        { participant_id: 'user:2', access_url: 'https://example.invalid/multiple-choice#access=second' },
    ] }
    const writable = { write: vi.fn().mockResolvedValue(undefined), close: vi.fn().mockResolvedValue(undefined), abort: vi.fn() }
    const originalFile = { createWritable: vi.fn() }
    const file = { createWritable: vi.fn().mockResolvedValue(writable) }
    const directory = { name: 'Testordner', getFileHandle: vi.fn()
        .mockResolvedValueOnce(originalFile)
        .mockRejectedValueOnce(new DOMException('Missing', 'NotFoundError'))
        .mockResolvedValueOnce(file) }
    const picker = vi.fn().mockImplementation(() => {
        expect(axios.get).not.toHaveBeenCalled()
        return Promise.resolve(directory)
    })
    vi.stubGlobal('showDirectoryPicker', picker)
    vi.mocked(axios.get).mockClear().mockResolvedValueOnce({ data: packageData, headers: { 'content-disposition': 'attachment; filename="MC-Test_Test-A_Nr-7.json"' } })
    try {
        await clickText(wrapper, 'Einmalig lokal speichern')

        expect(picker).toHaveBeenCalledWith({ id: 'schooltool-mc-test-export', mode: 'readwrite' })
        expect(directory.getFileHandle).toHaveBeenLastCalledWith('MC-Test_Test-A_Nr-7_2.json', { create: true })
        expect(originalFile.createWritable).not.toHaveBeenCalled()
        expect(JSON.parse(writable.write.mock.calls[0][0])).toEqual(packageData)
        expect(writable.close).toHaveBeenCalledOnce()
        expect(wrapper.text()).toContain('Testlinks für 2 Schüler im Testordner „Testordner“ gespeichert')
        expect(wrapper.text()).toContain('schon vor dem Teststart')
        expect(axios.post).not.toHaveBeenCalled()
        expect(axios.patch).not.toHaveBeenCalled()
    } finally { vi.unstubAllGlobals() }
})

it.each(['AbortError', 'NotAllowedError'])('does not fetch or write personal links when folder selection fails with %s', async name => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', status: 'published' }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test })
    await flushPromises()
    await openExistingTest(wrapper)
    vi.mocked(axios.get).mockClear()
    vi.stubGlobal('showDirectoryPicker', vi.fn().mockRejectedValue(new DOMException('Denied', name)))
    try {
        await clickText(wrapper, 'Einmalig lokal speichern')

        expect(axios.get).not.toHaveBeenCalled()
        expect(wrapper.text().includes('nicht zum Schreiben freigegeben')).toBe(name === 'NotAllowedError')
        expect(wrapper.text()).not.toContain('Testlinks für')
        expect(axios.post).not.toHaveBeenCalled()
    } finally { vi.unstubAllGlobals() }
})

it('offers the existing download when folder selection is unsupported', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', status: 'published' }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test })
    await flushPromises()
    await openExistingTest(wrapper)
    vi.mocked(axios.get).mockClear()
    vi.stubGlobal('showDirectoryPicker', undefined)
    try {
        await clickText(wrapper, 'Einmalig lokal speichern')

        expect(wrapper.text()).toContain('Browser unterstützt die Ordnerauswahl nicht')
        expect(wrapper.findAll('button').some(button => button.text() === 'Für Leistungsfeststellung exportieren')).toBe(true)
        expect(axios.get).not.toHaveBeenCalled()
    } finally { vi.unstubAllGlobals() }
})

it.each(['request', 'identity', 'write'])('does not claim a saved folder export after a %s failure', async failure => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', status: 'published' }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test })
    await flushPromises()
    await openExistingTest(wrapper)
    const writable = { write: vi.fn().mockRejectedValue(new Error('Disk full')), close: vi.fn(), abort: vi.fn().mockResolvedValue(undefined) }
    const directory = { name: 'Testordner', getFileHandle: vi.fn().mockRejectedValueOnce(new DOMException('Missing', 'NotFoundError')).mockResolvedValue({ createWritable: vi.fn().mockResolvedValue(writable) }), removeEntry: vi.fn().mockResolvedValue(undefined) }
    vi.stubGlobal('showDirectoryPicker', vi.fn().mockResolvedValue(directory))
    if (failure === 'request') vi.mocked(axios.get).mockRejectedValueOnce({ response: { data: { message: 'Zugangslinks sind deaktiviert.' } } })
    else vi.mocked(axios.get).mockResolvedValueOnce({ data: { schema_version: 1, kind: 'schooltool.multiple_choice_links', test: { id: failure === 'identity' ? 8 : 7 }, participants: [] }, headers: {} })
    try {
        await clickText(wrapper, 'Einmalig lokal speichern')

        expect(wrapper.text()).not.toContain('im Testordner „Testordner“ gespeichert')
        expect(axios.post).not.toHaveBeenCalled()
        if (failure === 'write') {
            expect(writable.abort).toHaveBeenCalledOnce()
            expect(directory.removeEntry).toHaveBeenCalledWith('MC-Test_7.json')
            expect(writable.close).not.toHaveBeenCalled()
            expect(wrapper.text()).toContain('nicht vollständig gespeichert')
        } else {
            expect(directory.getFileHandle).not.toHaveBeenCalled()
        }
    } finally { vi.unstubAllGlobals() }
})

it.each([false, true])('allows explicit results and solutions release with completed work while other people remain open, solutions=%s', async solutions => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', start_mode: 'individual', closes_at: '2027-01-15T12:00:00Z',
        results_released_at: solutions ? '2026-10-19T10:00:00Z' : null }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test, server_now: '2026-10-19T10:00:00Z', attempts: [
        { id: 1, student_name: 'Fertig', status: 'submitted', completed_at: '2026-10-19T09:59:00Z' },
        { id: 2, student_name: 'Abwesend', status: 'not_started', started_at: null, completed_at: null, effective_ends_at: '2027-01-15T12:00:00Z' },
        { id: 3, student_name: 'Bearbeitet noch', status: 'in_progress', started_at: '2026-10-19T09:58:00Z', completed_at: null, effective_ends_at: '2026-10-19T10:03:00Z' },
    ] })
    await flushPromises()
    await openExistingTest(wrapper)
    const buttonText = solutions ? 'Lösungen freigeben' : 'Ergebnisse freigeben'

    expect(wrapper.find('[aria-label="Ergebnisse und Lösungen"]').exists()).toBe(true)
    expect(wrapper.findAll('button').find(button => button.text() === buttonText).element.disabled).toBe(false)
    await clickText(wrapper, buttonText)
    expect(wrapper.find('[role="dialog"]').text()).toContain('erst nach der eigenen Abgabe')
    expect(axios.post).not.toHaveBeenCalled()
    vi.mocked(axios.post).mockResolvedValueOnce({ data: {} })
    await clickText(wrapper, 'Freigeben')
    expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/release-results', { solutions })
})

it('keeps results controls visible before any personal test starts', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', start_mode: 'individual', starts_at: null, ends_at: null }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test, attempts: [
        { id: 1, student_name: 'Noch nicht begonnen', status: 'not_started', started_at: null, completed_at: null },
    ] })
    await flushPromises()
    await openExistingTest(wrapper)

    expect(wrapper.find('[aria-label="Ergebnisse und Lösungen"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('sobald eine Abgabe abgeschlossen ist')
    expect(wrapper.findAll('button').find(button => button.text() === 'Ergebnisse freigeben').element.disabled).toBe(true)
    expect(wrapper.findAll('button').find(button => button.text() === 'Einmalig lokal speichern').element.disabled).toBe(false)
    expect(axios.post).not.toHaveBeenCalled()
})

it('reports export failures without creating a download or sending mail', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', status: 'published' }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test })
    await flushPromises()
    await openExistingTest(wrapper)
    vi.mocked(axios.get).mockRejectedValueOnce({ response: { data: { message: 'Teilnehmerdaten prüfen.' } } })

    await clickText(wrapper, 'Für Leistungsfeststellung exportieren')

    expect(wrapper.text()).toContain('Teilnehmerdaten prüfen.')
    expect(axios.post).not.toHaveBeenCalled()
    expect(document.querySelector('a[download]')).toBeNull()
})

it.each([false, true])('switches personal links explicitly and blocks invitations when disabled=%s', async disabled => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', access_links_disabled_at: disabled ? '2026-10-18T12:00:00Z' : null }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test })
    await flushPromises()
    await openExistingTest(wrapper)
    expect(wrapper.findAll('button').find(button => button.text() === 'Einladungen vorbereiten').element.disabled).toBe(disabled)
    expect(wrapper.findAll('button').find(button => button.text() === 'Für Leistungsfeststellung exportieren').element.disabled).toBe(disabled)
    expect(wrapper.findAll('button').find(button => button.text() === 'Einmalig lokal speichern').element.disabled).toBe(disabled)
    vi.mocked(axios.patch).mockResolvedValueOnce({ data: { test } })
    await clickText(wrapper, disabled ? 'Zugangslinks aktivieren' : 'Zugangslinks deaktivieren')
    expect(axios.patch).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/access-links', { enabled: disabled })
})

it('keeps a completed exam protocol visible after monitoring is disabled', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', exam_mode: false }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test, attempts: [{ id: 1, student_name: 'Alex', status: 'submitted', completed_at: '2026-10-16T08:15:00Z', activity: { state: 'finished', away_count: 2, away_seconds: 42, periods: [] } }] })
    await flushPromises()
    await openExistingTest(wrapper)
    expect(wrapper.text()).toContain('Prüfungsprotokoll')
    await wrapper.find('[aria-label="Prüfungsprotokoll von Alex ansehen"]').trigger('click')
    expect(wrapper.text()).toContain('Prüfungsprotokoll · Alex')
    expect(wrapper.text()).toContain('2 Wechsel')
})

it('retains edited settings and explains a server rejection when a pupil starts concurrently', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Test A', question_count: 1, duration_seconds: 600 }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test, can_edit_settings: true })
    await flushPromises()
    await openExistingTest(wrapper)
    await clickText(wrapper, 'Einstellungen bearbeiten')
    await wrapper.find('[aria-label="Testbezeichnung"]').setValue('Mein Entwurf')
    vi.mocked(axios.patch).mockRejectedValueOnce({ response: { status: 422, data: { message: 'Ein Versuch wurde inzwischen gestartet.' } } })
    await clickText(wrapper, 'Einstellungen speichern')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
    expect(wrapper.find('[aria-label="Testbezeichnung"]').element.value).toBe('Mein Entwurf')
    expect(wrapper.find('[role="dialog"]').text()).toContain('Ein Versuch wurde inzwischen gestartet.')
})

it('lists only the selected schoolyear by default and keeps unassigned legacy tests accessible', async () => {
    const test = { id: 7, catalog_id: 4, title: 'Aktueller Test' }
    const legacy = { id: 9, catalog_id: 4, title: 'Unzugeordneter Test' }
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test], unassigned_tests: [legacy], schoolyear: { id: 2, name: '2026/27' } }, { test: legacy })
    await flushPromises()
    await openCatalogTests(wrapper)
    expect(wrapper.text()).toContain('2026/27')
    expect(wrapper.text()).toContain('Aktueller Test')
    expect(wrapper.text()).not.toContain('Unzugeordneter Test')
    await wrapper.find('input[type="checkbox"]').setValue(true)
    expect(wrapper.text()).toContain('Unzugeordneter Test')
    expect(wrapper.text()).not.toContain('Aktueller Test')
    await clickText(wrapper, 'Test öffnen')
    expect(wrapper.text()).toContain('Unzugeordneter Test')
})

it('suggests tomorrow at 18:00 and applies the deadline only with OK while cancel preserves the confirmed date', async () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-18T10:00:00'))
    const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }] })
    await flushPromises()
    await clickText(wrapper, 'Katalog öffnen')
    await startNewTest(wrapper)
    await wrapper.find('input[name="mc-start-mode-new"][value="individual"]').setValue(true)
    const deadline = () => wrapper.find('[aria-label="Schlussfrist für alle"]')
    expect(deadline().text()).toContain('Datum und Uhrzeit auswählen')
    await deadline().trigger('click')
    expect(wrapper.find('[aria-label="Datum der Schlussfrist"]').element.value).toBe('2026-10-19')
    expect(wrapper.find('[aria-label="Uhrzeit der Schlussfrist"]').element.value).toBe('18:00')
    expect(axios.post).not.toHaveBeenCalled()
    expect(wrapper.findAll('button').find(button => button.text() === 'OK').element.disabled).toBe(false)
    await wrapper.findAll('[role="dialog"]').at(-1).find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('[aria-label="Datum der Schlussfrist"]').exists()).toBe(false)
    expect(deadline().text()).toContain('19.10.26')
    expect(deadline().text()).toContain('18:00')
    const confirmed = deadline().text()
    await deadline().trigger('click')
    await wrapper.find('[aria-label="Datum der Schlussfrist"]').setValue('2026-10-20')
    await wrapper.find('[aria-label="Uhrzeit der Schlussfrist"]').setValue('09:15')
    await wrapper.findAll('[role="dialog"]').at(-1).findAll('button').find(button => button.text() === 'Abbrechen').trigger('click')
    expect(deadline().text()).toBe(confirmed)
    await deadline().trigger('click')
    expect(wrapper.find('[aria-label="Datum der Schlussfrist"]').element.value).toBe('2026-10-19')
    await wrapper.find('[aria-label="Datum der Schlussfrist"]').setValue('2026-10-17')
    expect(wrapper.findAll('button').find(button => button.text() === 'OK').element.disabled).toBe(true)
    expect(wrapper.text()).toContain('Die Schlussfrist muss in der Zukunft liegen.')
    expect(axios.post).not.toHaveBeenCalled()
})

describe('Linked questions template sync', () => {
    const sourcePath = '/Materialien/Lerninhalte/E-Mails/Multiple Choice Fragen/Fragen.md'
    const connection = {
        status: 'connected', source_path: sourcePath, source_file_id: 'id:source', source_revision: 'r1',
    }
    const linkedCatalog = {
        id: 4, title: 'E-Mails', questions: [validQuestion()], template_connection: connection,
        template_version: 'catalog-v1',
    }

    it('shows a Question Summary preview and writes only after confirmation', async () => {
        const wrapper = renderWorkspace({ catalogs: [linkedCatalog] })
        await flushPromises()
        await clickText(wrapper, 'Katalog öffnen')
        expect(wrapper.text()).toContain('Question Summary')
        expect(axios.post).not.toHaveBeenCalled()

        const path = '/Materialien/Lerninhalte/E-Mails/Multiple Choice Fragen/Question Summary/Question Summary_E-Mails.md'
        vi.mocked(axios.post).mockResolvedValueOnce({ data: {
            path, action: 'create', question_count: 2, incomplete_count: 1,
            content: '1. Office\\-365\\-Anmeldung\n- [x] Posteingang', can_apply: true, token: 'summary-token',
            rows: [
                { number: 1, type: 'Einfachauswahl', prompt: 'Office-365-Anmeldung', answers: ['Posteingang'], active: true, incomplete: false },
                { number: 2, type: 'Mehrfachauswahl', prompt: 'Zeile eins\nZeile zwei <script>', answers: ['Erste Antwort', 'Zweite\nAntwort'], active: false, incomplete: true },
            ],
            warning: 'Die Zusammenfassung wird als Markdown gespeichert.',
        } })
        await clickText(wrapper, 'Question Summary')
        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/catalogs/4/question-summary-preview')
        const dialog = wrapper.find('[aria-label="Vorschau der Question Summary"]')
        expect(dialog.text()).toContain(path)
        expect(dialog.find('[role="list"]').text()).toContain('Office-365-Anmeldung')
        expect(dialog.find('[role="list"]').text()).toContain('Posteingang')
        expect(dialog.find('[role="list"]').text()).toContain('Zweite\nAntwort')
        expect(dialog.find('[role="list"]').text()).toContain('Unvollständige Frage')
        expect(dialog.find('[role="list"]').text()).toContain('Inaktiv')
        expect(dialog.text()).not.toContain('Office\\-365')
        expect(dialog.find('pre').exists()).toBe(false)
        expect(dialog.find('script').exists()).toBe(false)
        expect(dialog.text()).toContain('Die Zusammenfassung wird neu angelegt.')

        vi.mocked(axios.post).mockResolvedValueOnce({ data: {
            path, revision: 'r2', action: 'create', message: 'Die Question Summary wurde in Dropbox gespeichert.',
        } })
        await clickText(wrapper, 'In Dropbox speichern')
        expect(axios.post).toHaveBeenLastCalledWith('/api/admin/multiple-choice/catalogs/4/question-summary-apply', { token: 'summary-token' })
        expect(wrapper.text()).toContain('Die Question Summary wurde in Dropbox gespeichert.')
    })

    it('shows the verified path and previews both directions before applying the edited catalog', async () => {
        const wrapper = renderWorkspace({ catalogs: [linkedCatalog] })
        await flushPromises()
        await clickText(wrapper, 'Katalog öffnen')

        expect(wrapper.find('[aria-label="Verbundene Fragenvorlage"]').text()).toContain('Vorlage verbunden')
        expect(wrapper.find('[aria-label="Verbundene Fragenvorlage"]').text()).toContain(sourcePath)
        expect(wrapper.text()).toContain('Vorlage synchronisieren')
        const editButton = wrapper.findAll('button').find(button => button.text() === 'Bearbeiten')
        const syncButton = wrapper.findAll('button').find(button => button.text() === 'Vorlage synchronisieren')
        expect(syncButton.element.parentElement).toBe(editButton.element.parentElement)
        expect(wrapper.text()).not.toContain('Vorlage importieren')
        expect(axios.post).not.toHaveBeenCalled()

        await clickText(wrapper, 'Bearbeiten')
        await clickText(wrapper, 'Frage hinzufügen')
        const draftQuestions = wrapper.findComponent(QuestionEditor).props('questions')
        expect(draftQuestions).toHaveLength(2)
        const changedBefore = validQuestion({ prompt: 'Welche Lösung gilt?', options: [
            { id: 'a', text: 'Erster Weg' }, { id: 'b', text: 'Zweiter Weg' },
        ], correct_option_ids: ['a'] })
        const changedAfter = { ...changedBefore, correct_option_ids: ['b'] }
        const preview = {
            token: 'review-token', source_path: sourcePath, initial_connection: false, can_apply: true, conflicts: [],
            warnings: ['Eine vorhandene PDF wird nicht aktualisiert.'],
            catalog_changes: { added: [{ id: 'new-in-source', prompt: 'Aus Dropbox' }],
                modified: [{ id: 'changed', prompt: 'Welche Lösung gilt?', before: changedBefore, after: changedAfter }], removed: [] },
            source_changes: { added: [{ id: 'new-in-schooltool', prompt: 'Aus Schooltool' }], modified: [], removed: [] },
        }
        vi.mocked(axios.post).mockResolvedValueOnce({ data: preview })
        await clickText(wrapper, 'Vorlage synchronisieren')

        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/catalogs/4/sync-preview', {
            expected_version: 'catalog-v1', title: 'E-Mails', questions: draftQuestions,
        })
        const dialog = wrapper.find('[aria-label="Vorschau der Vorlagensynchronisierung"]')
        expect(dialog.text()).toContain('In Schooltool übernehmen')
        expect(dialog.text()).toContain('Aus Dropbox')
        expect(dialog.text()).toContain('In Dropbox übernehmen')
        expect(dialog.text()).toContain('Aus Schooltool')
        expect(dialog.text()).toContain('Bisher')
        expect(dialog.text()).toContain('Nach dem Abgleich')
        expect(dialog.text()).toContain('Richtig: Erster Weg')
        expect(dialog.text()).toContain('Richtig: Zweiter Weg')
        expect(dialog.text()).toContain('Eine vorhandene PDF wird nicht aktualisiert.')
        expect(axios.patch).not.toHaveBeenCalled()
        expect(axios.delete).not.toHaveBeenCalled()

        const syncedCatalog = { ...linkedCatalog, questions: draftQuestions, template_connection: { ...connection, source_revision: 'r2' } }
        vi.mocked(axios.post).mockResolvedValueOnce({ data: {
            catalog: syncedCatalog, message: 'Abgleich abgeschlossen.',
            warnings: ['Eine vorhandene PDF wird nicht aktualisiert.'],
        } })
        await clickText(wrapper, 'Abgleich bestätigen')

        expect(axios.post).toHaveBeenLastCalledWith('/api/admin/multiple-choice/catalogs/4/sync-apply', { token: 'review-token' })
        expect(wrapper.find('[aria-label="Vorschau der Vorlagensynchronisierung"]').exists()).toBe(false)
        expect(wrapper.find('[aria-label="Verbundene Fragenvorlage"]').text()).toContain(sourcePath)
        expect(wrapper.text()).toContain('Abgleich abgeschlossen.')
        expect(wrapper.text()).toContain('Eine vorhandene PDF wird nicht aktualisiert.')
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual(draftQuestions)
    })

    it('shows conflicts without offering an apply action', async () => {
        const wrapper = renderWorkspace({ catalogs: [linkedCatalog] })
        await flushPromises()
        await clickText(wrapper, 'Katalog öffnen')
        vi.mocked(axios.post).mockResolvedValueOnce({ data: {
            token: null, source_path: sourcePath, initial_connection: false, can_apply: false,
            conflicts: ['Die Frage wurde an beiden Orten geändert.'],
            catalog_changes: { added: [], modified: [], removed: [] },
            source_changes: { added: [], modified: [], removed: [] },
        } })
        await clickText(wrapper, 'Vorlage synchronisieren')

        const dialog = wrapper.find('[aria-label="Vorschau der Vorlagensynchronisierung"]')
        expect(dialog.text()).toContain('Die Frage wurde an beiden Orten geändert.')
        expect(dialog.text()).toContain('Keine Änderungen.')
        expect(dialog.text()).not.toContain('Abgleich bestätigen')
        expect(axios.post).toHaveBeenCalledTimes(1)
        await clickText(wrapper, 'Schließen')
        expect(wrapper.find('[aria-label="Vorschau der Vorlagensynchronisierung"]').exists()).toBe(false)
    })

    it('requires confirmation to disconnect and preserves the local catalog', async () => {
        const wrapper = renderWorkspace({ catalogs: [linkedCatalog] })
        await flushPromises()
        await clickText(wrapper, 'Katalog öffnen')
        await clickText(wrapper, 'Verbindung auflösen')
        expect(axios.delete).not.toHaveBeenCalled()
        expect(wrapper.find('[role="dialog"]').text()).toContain('Fragenkatalog und die Dropbox-Vorlage bleiben erhalten')
        vi.mocked(axios.delete).mockResolvedValueOnce({ data: {
            catalog: { id: 4, title: 'E-Mails', questions: [validQuestion()], template_connection: null },
        } })
        await wrapper.find('[role="dialog"]').findAll('button').find(button => button.text() === 'Verbindung auflösen').trigger('click')
        await flushPromises()
        expect(axios.delete).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/catalogs/4/template-connection')
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual([validQuestion()])
        expect(wrapper.find('[aria-label="Verbundene Fragenvorlage"]').exists()).toBe(false)
        await clickText(wrapper, 'Bearbeiten')
        expect(wrapper.text()).toContain('Vorlage importieren')
    })

    it('labels a historical import as unconfirmed until the first reviewed sync', async () => {
        const wrapper = renderWorkspace({ catalogs: [{
            ...linkedCatalog,
            template_connection: { ...connection, status: 'needs_confirmation' },
        }] })
        await flushPromises()
        await clickText(wrapper, 'Katalog öffnen')
        expect(wrapper.text()).toContain('Vorlage noch nicht bestätigt')
        vi.mocked(axios.post).mockResolvedValueOnce({ data: {
            token: 'initial-token', source_path: sourcePath, initial_connection: true, can_apply: true, conflicts: [],
            catalog_changes: { added: [], modified: [], removed: [] },
            source_changes: { added: [], modified: [], removed: [] },
        } })
        await clickText(wrapper, 'Vorlage synchronisieren')
        expect(wrapper.find('[aria-label="Vorschau der Vorlagensynchronisierung"]').text())
            .toContain('Beim ersten Abgleich')
    })
    it('prevents a stale editor from overwriting a synchronized catalog', async () => {
        const versionedCatalog = { ...linkedCatalog, template_version: 'catalog-v1' }
        const wrapper = renderWorkspace({ catalogs: [versionedCatalog] })
        await flushPromises()
        await editCatalog(wrapper)
        const changedQuestions = [validQuestion({ prompt: 'Lokal geändert' })]
        await wrapper.findComponent(QuestionEditor).vm.$emit('update:questions', changedQuestions)
        vi.mocked(axios.patch).mockResolvedValueOnce({ data: {
            catalog: { ...versionedCatalog, questions: changedQuestions, template_version: 'catalog-v2' },
        } })
        await clickText(wrapper, 'Fragen speichern')

        expect(axios.patch).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/catalogs/4',
            expect.objectContaining({ expected_version: 'catalog-v1' }))
    })

    it('keeps a rejected apply visible and disables reuse of its stale confirmation', async () => {
        const wrapper = renderWorkspace({ catalogs: [linkedCatalog] })
        await flushPromises()
        await clickText(wrapper, 'Katalog öffnen')
        vi.mocked(axios.post).mockResolvedValueOnce({ data: {
            token: 'stale-token', source_path: sourcePath, initial_connection: false, can_apply: true, conflicts: [],
            catalog_changes: { added: [], modified: [], removed: [] },
            source_changes: { added: [{ id: 'local', prompt: 'Lokal neu' }], modified: [], removed: [] },
        } })
        await clickText(wrapper, 'Vorlage synchronisieren')
        vi.mocked(axios.post).mockRejectedValueOnce({ response: { status: 409, data: { message: 'Die Vorlage wurde inzwischen geändert. Bitte erneut prüfen.' } } })
        await clickText(wrapper, 'Abgleich bestätigen')

        const dialog = wrapper.find('[aria-label="Vorschau der Vorlagensynchronisierung"]')
        expect(dialog.text()).toContain('Die Vorlage wurde inzwischen geändert. Bitte erneut prüfen.')
        expect(dialog.findAll('button').find(button => button.text() === 'Abgleich bestätigen').attributes('disabled')).toBeDefined()
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual([validQuestion()])
        expect(axios.post).toHaveBeenCalledTimes(2)
    })
    it('offers an explicitly confirmed recovery unlink without changing questions', async () => {
        const wrapper = renderWorkspace({ catalogs: [linkedCatalog] })
        await flushPromises()
        await clickText(wrapper, 'Katalog öffnen')
        vi.mocked(axios.post).mockResolvedValueOnce({ data: {
            token: null, source_path: sourcePath, initial_connection: false, resuming: true,
            can_apply: false, can_disconnect: true, recovery_token: 'recovery-token',
            conflicts: ['Die Vorlage wurde nach dem unterbrochenen Schreiben verändert.'],
            catalog_changes: { added: [], modified: [], removed: [] },
            source_changes: { added: [], modified: [], removed: [] },
        } })
        await clickText(wrapper, 'Vorlage synchronisieren')
        expect(wrapper.find('[aria-label="Vorschau der Vorlagensynchronisierung"]').text())
            .toContain('Ein unterbrochener Abgleich wird fortgesetzt.')
        await clickText(wrapper, 'Abgleich beenden und Verbindung lösen')
        expect(axios.delete).not.toHaveBeenCalled()
        expect(wrapper.find('[role="dialog"]').text()).toContain('Katalogfragen und Dropbox-Datei bleiben erhalten')

        vi.mocked(axios.delete).mockResolvedValueOnce({ data: {
            catalog: { id: 4, title: 'E-Mails', questions: [validQuestion()], template_connection: null },
        } })
        await clickText(wrapper, 'Abgleich beenden und Verbindung lösen')
        expect(axios.delete).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/catalogs/4/template-connection', {
            data: { recovery_token: 'recovery-token' },
        })
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual([validQuestion()])
        expect(wrapper.find('[aria-label="Verbundene Fragenvorlage"]').exists()).toBe(false)
    })
})
