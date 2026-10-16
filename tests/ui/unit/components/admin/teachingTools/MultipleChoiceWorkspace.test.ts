import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import axios from 'axios'
import MultipleChoiceWorkspace from '@/pages/admin/teachingTools/MultipleChoiceWorkspace.vue'
import QuestionEditor from '@/pages/admin/teachingTools/QuestionEditor.vue'
import { questionProblems } from '@/pages/admin/teachingTools/multipleChoiceTeacher'

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() } }))

it('requires multiple correct answers for a multiple-selection question', () => {
    expect(questionProblems({ prompt: 'Frage', type: 'multiple', options: [{ id: 'a', text: 'A' }, { id: 'b', text: 'B' }], correct_option_ids: ['a'] }))
        .toContain('Bei Mehrfachauswahl mindestens zwei richtige Antworten festlegen.')
})

const button = defineComponent({
    props: ['disabled'],
    setup(props, { slots, attrs }) {
        return () => h('button', { ...attrs, disabled: props.disabled }, slots.default?.())
    },
})
const field = defineComponent({
    props: ['modelValue', 'label'],
    emits: ['update:modelValue'],
    setup(props, { emit, attrs }) {
        return () => h('input', {
            ...attrs,
            'aria-label': props.label,
            value: props.modelValue,
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
const dialog = defineComponent({
    props: ['modelValue'],
    setup(props, { slots }) {
        return () => props.modelValue ? h('div', { role: 'dialog' }, slots.default?.()) : null
    },
})
const wrappers = []

function validQuestion(overrides = {}) {
    return { id: 'question-a', prompt: 'Welche Antwort ist richtig?', type: 'single', options: [{ id: 'a', text: 'Richtig' }, { id: 'b', text: 'Falsch' }], correct_option_ids: ['a'], active: true, reviewed: true, ...overrides }
}

function renderWorkspace(data = {}, monitor = {}) {
    vi.mocked(axios.get).mockImplementation(async (url) => {
        if (String(url).endsWith('/multiple-choice')) {
            return { data: { available: true, catalogs: [], tests: [], ...data } }
        }
        if (String(url).endsWith('/invitations')) {
            return { data: { recipients: [{ attempt_id: 1, student_name: 'Test Person', email: 'test@example.invalid', invited_at: null }] } }
        }
        return { data: { test: { id: 7, title: 'Test A', question_count: 1, total_points: 10, duration_seconds: 600, lead_seconds: 30 }, attempts: [], ...monitor } }
    })
    const wrapper = mount(MultipleChoiceWorkspace, { global: { stubs: { 'v-btn': button, 'v-text-field': field, 'v-select': select, 'v-dialog': dialog, 'v-sheet': { template: '<div><slot /></div>' } } } })
    wrappers.push(wrapper)
    return wrapper
}

async function clickText(wrapper, text) {
    const target = wrapper.findAll('button').find((item) => item.text() === text)
    expect(target, `Button ${text} exists`).toBeDefined()
    await target.trigger('click')
    await flushPromises()
}

beforeEach(() => {
    vi.mocked(axios.get).mockReset()
    vi.mocked(axios.post).mockReset()
    vi.mocked(axios.patch).mockReset()
})
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
})

describe('Multiple Choice teacher workflow', () => {
    it('opens without writes and shows an actionable activation state', async () => {
        const wrapper = renderWorkspace({ available: false, message: 'Die Administration muss Multiple Choice aktivieren.' })
        await flushPromises()

        expect(wrapper.text()).toContain('Die Administration muss Multiple Choice aktivieren.')
        expect(wrapper.find('[aria-label="Fragenkataloge"]').exists()).toBe(false)
        expect(axios.post).not.toHaveBeenCalled()
        expect(axios.patch).not.toHaveBeenCalled()
    })

    it('previews AI questions without saving or marking them as reviewed', async () => {
        const wrapper = renderWorkspace()
        await flushPromises()
        vi.mocked(axios.post).mockResolvedValue({ data: { questions: [validQuestion()], errors: [], warnings: ['Bitte Distraktoren prüfen.'], valid: true } })
        await clickText(wrapper, 'Vorlage importieren')
        await wrapper.find('textarea').setValue('Frage: Was ist ein Bit? Antwort: Eine binäre Ziffer.')
        await wrapper.find('input[type="checkbox"]').setValue(true)

        await clickText(wrapper, 'Entwurf erzeugen')

        expect(axios.post).toHaveBeenCalledTimes(1)
        expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/import-preview', expect.objectContaining({ generate: true, format: 'markdown' }))
        expect(wrapper.text()).toContain('Bitte Distraktoren prüfen.')
        expect(wrapper.findComponent(QuestionEditor).props('questions')[0].reviewed).toBe(false)
        await clickText(wrapper, 'Entwurf in Katalog übernehmen')
        expect(axios.post).toHaveBeenCalledTimes(1)
        expect(wrapper.text()).toContain('1 Fragen · 0 aktiv und geprüft')
    })

    it('blocks release for ambiguous roster and invalidates preview after edits', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Katalog', questions: [validQuestion()] }] })
        await flushPromises()
        await clickText(wrapper, 'Tests')
        await wrapper.find('[aria-label="Testbezeichnung"]').setValue('Test A')
        await wrapper.find('[aria-label="Gespeicherter Fragenkatalog"]').setValue('4')
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
        const wrapper = renderWorkspace({ tests: [{ id: 7, title: 'Test A' }] })
        await flushPromises()
        await clickText(wrapper, 'Tests')

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

    it('saves catalog drafts explicitly and preserves the correct selection', async () => {
        const question = validQuestion({ reviewed: false })
        const catalog = { id: 4, title: 'Katalog', questions: [question] }
        const wrapper = renderWorkspace({ catalogs: [catalog] })
        await flushPromises()
        await wrapper.find('[aria-label="Fragenkatalog auswählen"]').setValue('4')
        await wrapper.find('[aria-label="Bezeichnung des Katalogs"]').setValue('Überarbeiteter Katalog')
        expect(axios.patch).not.toHaveBeenCalled()
        vi.mocked(axios.patch).mockResolvedValue({ data: { catalog: { ...catalog, title: 'Überarbeiteter Katalog' } } })

        await clickText(wrapper, 'Katalog speichern')

        expect(axios.patch).toHaveBeenCalledWith('/api/admin/multiple-choice/catalogs/4', { title: 'Überarbeiteter Katalog', questions: [question] })
        expect(wrapper.text()).toContain('Katalog gespeichert.')
    })

    it('prepares a test from a saved reviewed catalog without publishing or losing unsaved changes', async () => {
        const wrapper = renderWorkspace({ catalogs: [{ id: 4, title: 'Netzwerke', questions: [validQuestion()] }] })
        await flushPromises()
        await wrapper.find('[aria-label="Fragenkatalog auswählen"]').setValue('4')
        expect(wrapper.text()).toContain('Dein Katalog ist bereit.')
        await wrapper.find('[aria-label="Bezeichnung des Katalogs"]').setValue('Noch nicht gespeichert')
        expect(wrapper.findAll('button').some((item) => item.text() === 'Test vorbereiten')).toBe(false)
        await wrapper.find('[aria-label="Bezeichnung des Katalogs"]').setValue('Netzwerke')

        await clickText(wrapper, 'Test vorbereiten')

        expect(wrapper.find('[aria-label="Gespeicherter Fragenkatalog"]').element.value).toBe('4')
        expect(wrapper.find('[aria-label="Fragen pro Schüler"]').element.value).toBe('1')
        expect(wrapper.find('[aria-label="Testbezeichnung"]').element.value).toBe('Netzwerke – Test')
        expect(axios.post).not.toHaveBeenCalled()
        expect(axios.patch).not.toHaveBeenCalled()
    })

    it('requires a deliberate shared countdown start and never starts it on page load', async () => {
        const wrapper = renderWorkspace({ tests: [{ id: 7, title: 'Test A' }] })
        await flushPromises()
        await clickText(wrapper, 'Tests')
        expect(axios.post).not.toHaveBeenCalled()

        await clickText(wrapper, 'Countdown starten (30 s)')

        expect(wrapper.find('[role="dialog"]').text()).toContain('Später einsteigende Schüler')
        expect(axios.post).not.toHaveBeenCalled()
        vi.mocked(axios.post).mockResolvedValue({ data: {} })
        await clickText(wrapper, 'Countdown starten')
        expect(axios.post).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/countdown')
    })

    it('distinguishes not-started attempts from completed zero scores and releases solutions explicitly', async () => {
        const test = { id: 7, title: 'Test A', question_count: 3, total_points: 10, duration_seconds: 600, lead_seconds: 30, starts_at: '2026-10-16T08:00:00Z', ends_at: '2026-10-16T08:10:00Z' }
        const wrapper = renderWorkspace({ tests: [test] }, { test, server_now: '2026-10-16T08:11:00Z', attempts: [
            { id: 1, student_name: 'Nicht begonnen', status: 'not_started', started_at: null, score: 0, percentage: 0 },
            { id: 2, student_name: 'Null Punkte', status: 'submitted', started_at: '2026-10-16T08:01:00Z', score: 0, percentage: 0 },
        ] })
        await flushPromises()
        await clickText(wrapper, 'Ergebnisse')
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

    it('keeps multiple correct options and prevents review of incomplete questions', async () => {
        const wrapper = mount(QuestionEditor, { props: { questions: [validQuestion({ type: 'multiple', reviewed: false })] } })
        wrappers.push(wrapper)

        await wrapper.find('input[aria-label="Antwort 2 ist richtig"]').setValue(true)

        expect(wrapper.emitted('update:questions')[0][0][0].correct_option_ids).toEqual(['a', 'b'])
        await wrapper.setProps({ questions: [validQuestion({ prompt: '', reviewed: false })] })
        expect(wrapper.text()).toContain('Fragetext fehlt.')
        const review = wrapper.findAll('.question-editor__flags input')[1]
        expect(review.attributes('disabled')).toBeDefined()
    })
})
