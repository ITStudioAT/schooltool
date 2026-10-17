import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter, RouterView } from 'vue-router'
import axios from 'axios'
import MultipleChoiceWorkspace from '@/pages/admin/teachingTools/MultipleChoiceWorkspace.vue'
import QuestionEditor from '@/pages/admin/teachingTools/QuestionEditor.vue'
import { detectImportFormat, questionProblems } from '@/pages/admin/teachingTools/multipleChoiceTeacher'

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() } }))

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

function renderWorkspace(data = {}, monitor = {}, initialLocation = '/admin/teaching-tools/multiple-choice') {
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
    const wrapper = mount(RouterView, { global: { plugins: [router], stubs: { FilePond: filePond, 'v-btn': button, 'v-text-field': field, 'v-select': select, 'v-dialog': dialog, 'v-sheet': { template: '<div><slot /></div>' } } } })
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
})
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
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
        expect(wrapper.find('[aria-label="Fragen pro Schüler"]').element.value).toBe('3')
        expect(wrapper.find('[aria-label="Erreichbare Gesamtpunkte"]').element.value).toBe('10')
        expect(wrapper.find('[aria-label="Bearbeitungsdauer in Minuten"]').element.value).toBe('20')
        expect(wrapper.find('[aria-label="Vorlaufzeit in Sekunden"]').element.value).toBe('60')
        expect(wrapper.findAll('input[type="checkbox"]').every((item) => item.element.checked)).toBe(true)
        expect(wrapper.find('textarea').element.value).toBe('')
        expect(wrapper.text()).not.toContain('Ausgewählt:')
        expect(wrapper.findAll('button').find((item) => item.text() === 'Test freigeben').attributes('disabled')).toBeDefined()
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
        ['?catalog=4&view=results&test=7', 'Ergebnisse zu „Test A“'],
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
        await clickText(wrapper, 'Ergebnisse ansehen')
        expect(wrapper.router.currentRoute.value.query).toEqual({ catalog: '4', view: 'results', test: '7' })
        wrapper.router.back()
        await flushPromises()
        expect(wrapper.text()).toContain('Countdown starten (30 s)')
        expect(wrapper.text()).not.toContain('Ergebnisse zu „Test A“')
        wrapper.router.forward()
        await flushPromises()
        expect(wrapper.text()).toContain('Ergebnisse zu „Test A“')
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
        await clickText(wrapper, 'Ergebnisse ansehen')
        expect(wrapper.text()).toContain('Ergebnisse zu „Test A“')
        expect(wrapper.text()).not.toContain('Einladungen vorbereiten')
        await clickText(wrapper, 'Zurück zum Test')
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
        const catalogs = [{ id: 4, title: 'Netzwerke', questions: [validQuestion()] }]
        const test = { id: 7, catalog_id: 4, title: 'Netzwerke – Test', question_count: 1, total_points: 10, duration_seconds: 1200, lead_seconds: 60 }
        const wrapper = renderWorkspace({ catalogs })
        await flushPromises()
        await openCatalogTests(wrapper)
        await clickText(wrapper, 'Neuen Test anlegen')
        expect(wrapper.find('[aria-label="Gespeicherter Fragenkatalog"]').exists()).toBe(false)
        await chooseFile(wrapper, new File(['Vorname,Nachname,E-Mail\nTest,Person,test@example.invalid'], 'students.csv'))
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { valid: true, rows: [{ status: 'matched' }] } })
        await clickText(wrapper, 'Zuordnung prüfen')
        await clickText(wrapper, 'Test freigeben')
        expect(axios.post).toHaveBeenCalledTimes(1)
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { test } })
        vi.mocked(axios.get).mockImplementation(async (url) => ({ data: String(url).endsWith('/multiple-choice')
            ? { available: true, catalogs, tests: [test] } : { test, attempts: [] } }))

        await wrapper.find('[role="dialog"]').findAll('button').find((item) => item.text() === 'Test freigeben').trigger('click')
        await flushPromises()

        expect(axios.post).toHaveBeenLastCalledWith('/api/admin/multiple-choice/tests', expect.objectContaining({ catalog_id: 4, title: 'Netzwerke – Test' }))
        expect(wrapper.find('[aria-label="Tests vorbereiten"]').exists()).toBe(false)
        expect(wrapper.text()).toContain('Countdown starten (60 s)')
        expect(wrapper.text()).toContain('Ergebnisse ansehen')
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
        await clickText(wrapper, 'Entwurf in Katalog übernehmen')
        expect(axios.post).toHaveBeenCalledTimes(2)
        expect(axios.post).toHaveBeenLastCalledWith('/api/admin/multiple-choice/import-preview', {
            text: JSON.stringify({ questions: [validQuestion({ reviewed: false })] }), format: 'json', generate: false,
        })
        expect(wrapper.text()).toContain('1 Fragen · 1 aktiv und geprüft')
        expect(wrapper.text()).toContain('Vorlage importieren')
        expect(wrapper.text()).toContain('Frage hinzufügen')
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

        await clickText(wrapper, 'Entwurf in Katalog übernehmen')

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
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }] })
        await flushPromises()
        await clickText(wrapper, 'Katalog öffnen')
        await startNewTest(wrapper)
        await wrapper.find('[aria-label="Testbezeichnung"]').setValue('Test A')
        await wrapper.find('[aria-label="Fragen pro Schüler"]').setValue('1')
        await wrapper.find('textarea').setValue('Vorname,Nachname,E-Mail\nTest,Person,test@example.invalid')
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { valid: false, errors: ['Doppelte E-Mail.'], rows: [{ row: 2, first_name: 'Test', last_name: 'Person', email: 'test@example.invalid', status: 'error', errors: ['Mehrdeutig'] }] } })

        await clickText(wrapper, 'Zuordnung prüfen')

        expect(wrapper.text()).toContain('Doppelte E-Mail.')
        expect(wrapper.findAll('button').find((item) => item.text() === 'Test freigeben').attributes('disabled')).toBeDefined()
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { valid: true, rows: [{ row: 2, first_name: 'Test', last_name: 'Person', status: 'matched' }] } })
        await clickText(wrapper, 'Zuordnung prüfen')
        expect(wrapper.findAll('button').find((item) => item.text() === 'Test freigeben').attributes('disabled')).toBeUndefined()
        await clickText(wrapper, 'Test freigeben')
        const summary = wrapper.find('[role="dialog"]').text()
        expect(summary).toContain('Test A')
        expect(summary).toContain('Katalog')
        expect(summary).toContain('1 eindeutig zugeordnet')
        expect(summary).toContain('1 Fragen · 10 Punkte')
        expect(summary).toContain('20 Minuten')
        expect(summary).toContain('60 Sekunden')
        await wrapper.find('textarea').setValue('Geänderte CSV')
        expect(wrapper.findAll('button').find((item) => item.text() === 'Test freigeben').attributes('disabled')).toBeDefined()
    })

    it('only sends invitations after preview and the distinct final confirmation', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [{ id: 7, catalog_id: 4, title: 'Test A' }] })
        await flushPromises()
        await openExistingTest(wrapper)

        await clickText(wrapper, 'Einladungen vorbereiten')

        expect(wrapper.text()).toContain('test@example.invalid')
        expect(axios.post).not.toHaveBeenCalled()
        await clickText(wrapper, '1 Einladungen per E-Mail senden')
        expect(wrapper.find('[role="dialog"]').text()).toContain('echte E-Mails')
        expect(axios.post).not.toHaveBeenCalled()
        vi.mocked(axios.post).mockResolvedValue({ data: { sent: 1, skipped: 0 } })
        await clickText(wrapper, 'E-Mails senden')
        expect(axios.post).toHaveBeenCalledTimes(1)
        expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/invitations')
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
        await clickText(wrapper, 'Entwurf in Katalog übernehmen')
        const questions = [validQuestion()]
        vi.mocked(axios.patch).mockResolvedValueOnce({ data: { catalog: { id: 4, title: 'Katalog', questions } } })

        await clickText(wrapper, 'Fragen speichern')

        expect(axios.patch).toHaveBeenCalledExactlyOnceWith('/api/admin/multiple-choice/catalogs/4', { title: 'Katalog', questions })
        expect(wrapper.find('h2').text()).toBe('Katalog')
        expect(wrapper.find('[aria-label="Bezeichnung des Katalogs"]').exists()).toBe(false)
        expect(wrapper.findComponent(QuestionEditor).props('questions')).toEqual(questions)
        expect(wrapper.text()).not.toContain('Fragen speichern')
        expect(wrapper.text()).toContain('Tests zu diesem Katalog')
        await startNewTest(wrapper)
        expect(wrapper.text()).toContain('Fragenkatalog: Katalog')
        expect(wrapper.find('[aria-label="Fragen pro Schüler"]').element.value).toBe('1')
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
        expect(wrapper.find('[aria-label="Fragen pro Schüler"]').element.value).toBe('1')
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
        const test = { id: 7, catalog_id: 4, title: 'Test A', question_count: 3, total_points: 10, duration_seconds: 600, lead_seconds: 30, starts_at: '2026-10-16T08:00:00Z', ends_at: '2026-10-16T08:10:00Z' }
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }], tests: [test] }, { test, server_now: '2026-10-16T08:11:00Z', attempts: [
            { id: 1, student_name: 'Nicht begonnen', status: 'not_started', started_at: null, score: 0, percentage: 0 },
            { id: 2, student_name: 'Null Punkte', status: 'submitted', started_at: '2026-10-16T08:01:00Z', score: 0, percentage: 0 },
        ] })
        await flushPromises()
        await openExistingTest(wrapper)
        await clickText(wrapper, 'Ergebnisse ansehen')
        const rows = wrapper.findAll('tbody tr')
        expect(rows[0].text()).toContain('Noch nicht begonnen—')
        expect(rows[1].text()).toContain('0 / 10')
        expect(axios.post).not.toHaveBeenCalled()

        await clickText(wrapper, 'Ergebnisse und Lösungen freigeben')

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
