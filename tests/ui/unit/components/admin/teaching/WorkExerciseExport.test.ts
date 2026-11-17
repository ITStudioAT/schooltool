import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VBtn, VDialog } from 'vuetify/components'
import WorkExerciseExport from '@/pages/admin/teaching/overview/components/WorkExerciseExport.vue'
import { clearWorkDropboxStates, loadWorkExercise, workDropboxStates } from '@/helpers/workDropbox'

const work = { id: 65, teaching_course_id: 2, title: 'E-Mails' }
const course = { id: 2, title: 'INF' }
const originalExecCommand = Object.getOwnPropertyDescriptor(document, 'execCommand')

function mountExport(get = vi.fn(), writeText = vi.fn().mockResolvedValue(undefined), status = vi.fn().mockResolvedValue({ data: { connected: true, module_link: { folder: { id: 'id:module' } }, exercise: { status: 'missing' } } }), currentWork: any = work,
    mcOptions: any = { available: true, tests: [{ id: 9, title: 'Vorhandener MC-Test', catalog_id: 3, catalog_title: 'Grundlagen' }], catalogs: [{ id: 3, title: 'Grundlagen' }], linked_test_id: null },
    moduleCheck = vi.fn().mockResolvedValue({ data: { module_questions: true, assessment_points: 35 } }), realDialog = false) {
    vi.stubGlobal('axios', { get: vi.fn((url, options) => url.endsWith('/dropbox') ? status(url, options)
        : url.endsWith('/exercise-options') && options?.params?.mc_selection === 'template_points' ? moduleCheck(url, options)
        : url.endsWith('/exercise-options') && options?.params?.mc_selection === 'current' ? Promise.resolve({ data: { linked_test_id: mcOptions.linked_test_id ?? null, linked_catalog_id: mcOptions.linked_test_id ? 3 : null } })
        : url.endsWith('/exercise-options') && options?.params?.mc_selection === 'catalogs' ? Promise.resolve({ data: { catalogs: mcOptions.catalogs?.length ? mcOptions.catalogs : [{ id: 3, title: 'Grundlagen', reviewed_question_count: 10 }] } })
        : url.endsWith('/exercise-options') && options?.params?.mc_selection === 'candidates' ? Promise.resolve({ data: { candidates: (mcOptions.tests || []).map((test: any) => ({ question_count: 10, total_points: 10, duration_minutes: 20, ...test })), defaults: { question_count: 10, total_points: 10, duration_minutes: 20 }, available_question_count: mcOptions.available_question_count ?? 10 } })
        : url.endsWith('/exercise-options') ? Promise.resolve({ data: { configuration: { locked: false, maximum_points: 5 },
            linked_mc_points: mcOptions.linked_test_id ? 2 : null, ...mcOptions,
            eligible_test: Object.hasOwn(mcOptions, 'eligible_test') ? mcOptions.eligible_test
                : mcOptions.tests?.find((test: any) => test.id === mcOptions.linked_test_id) || null,
            catalogs: (mcOptions.catalogs || []).map((catalog: any) => ({ reviewed_question_count: 10, ...catalog })) } }) : get(url, options)),
        post: vi.fn((url, payload, options) => url.endsWith('/prepare-exercise') ? Promise.resolve({ data: { linked_test_id: payload.mc_selection === 'no' ? null : (payload.test_id || 9), linked_catalog_id: payload.catalog_id ?? null, action: payload.test_id ? 'reused' : 'created', work: currentWork } }) : status(url, payload, options)) })
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    return mount(WorkExerciseExport, { attachTo: document.body, props: { work: currentWork, course, date: '2026-10-20' }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } }), createVuetify({ components: { VBtn, ...(realDialog ? { VDialog } : {}) } })],
        components: { 'v-btn': VBtn, ...(realDialog ? { 'v-dialog': VDialog } : {}) }, stubs: { 'v-btn': false, VBtn: false,
            'v-text-field': { props: ['modelValue', 'label', 'disabled', 'type', 'errorMessages'], emits: ['update:modelValue'],
                template: '<label>{{ label }}<input :aria-label="label" :type="type" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value === \'\' ? null : type === \'number\' ? Number($event.target.value) : $event.target.value)" /><span>{{ [errorMessages].flat().join(\' \') }}</span></label>' },
            'v-dialog': realDialog ? false : { name: 'TestDialog', props: ['modelValue', 'persistent'], emits: ['update:modelValue'],
                template: '<div v-if="modelValue" role="dialog"><slot /></div>' },
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

it.each([
    { automation_enabled: false },
    { status: { automation_enabled: false } },
    { automation_enabled: false, status: { folder_imported_at: '2026-10-13T12:54:00Z' } },
])('hides rechecking and skips Dropbox preparation for manual work: %j', async manual => {
    const status = vi.fn().mockResolvedValue({ data: linkedExercise })
    vi.stubGlobal('axios', { get: status })
    const wrapper = mount(WorkExerciseExport, { props: { work: { ...work, ...manual }, course, checkProgress: true }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } })],
        stubs: { 'v-checkbox': true, 'v-btn': { template: '<button><slot /></button>' } },
    } })
    try {
        await wrapper.setProps({ checkProgress: true })
        await flushPromises()
        expect(button(wrapper, 'Erneut prüfen')).toBeUndefined()
        expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
        expect(status).not.toHaveBeenCalled()

        await wrapper.setProps({ work: { ...work, ...manual, automation_enabled: true } })
        await flushPromises()
        expect(button(wrapper, 'Erneut prüfen')).toBeDefined()

        await wrapper.setProps({ work: { ...work, ...manual, automation_enabled: false } })
        await flushPromises()
        expect(button(wrapper, 'Erneut prüfen')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('hides the preparation recheck until an automatic work has a module link', async () => {
    const status = vi.fn().mockResolvedValue({ data: { connected: true, module_link: null, exercise: { status: 'missing' } } })
    const wrapper = mountExport(undefined, undefined, status)
    try {
        await flushPromises()
        expect(button(wrapper, 'Erneut prüfen')).toBeUndefined()
        expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
    } finally { wrapper.unmount() }
})

afterEach(() => {
    clearWorkDropboxStates(); vi.restoreAllMocks(); vi.unstubAllGlobals(); document.body.innerHTML = ''
    if (originalExecCommand) Object.defineProperty(document, 'execCommand', originalExecCommand)
    else delete (document as any).execCommand
})

it.each(['available', 'missing', 'error'])('shows the dialog while template points are checked: %s', async outcome => {
    let resolveCheck: (value: unknown) => void = () => {}
    let rejectCheck: (reason: Error) => void = () => {}
    const check = vi.fn().mockImplementation(() => new Promise((resolve, reject) => { resolveCheck = resolve; rejectCheck = reject }))
    const wrapper = mountExport(undefined, undefined, undefined, work, undefined, check)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="dialog"] [role="status"]').text()).toContain('Prüfungsvorlage und Punkte werden geprüft')
        expect(wrapper.find('[role="dialog"] textarea').exists()).toBe(false)
        expect(wrapper.find('[role="dialog"] input').exists()).toBe(false)
        expect(wrapper.find('[role="dialog"] button').exists()).toBe(false)
        if (outcome === 'error') rejectCheck(new Error('Offline'))
        else resolveCheck({ data: { assessment_points: outcome === 'available' ? 35 : null } })
        await flushPromises()
        expect(wrapper.text()).toContain(outcome === 'available' ? '35 Punkte' : outcome === 'error' ? 'Offline' : 'Punkte der Prüfungsvorlage konnten nicht eindeutig bestätigt werden')
        expect(wrapper.text()).not.toContain('Multiple-Choice-Fragen werden geprüft')
    } finally { wrapper.unmount() }
})
it('opens the real dialog while the template check is pending and permits cancellation', async () => {
    const check = vi.fn().mockImplementation(() => new Promise(() => {}))
    vi.stubGlobal('visualViewport', null)
    const wrapper = mountExport(undefined, undefined, undefined, work, undefined, check, true)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await flushPromises()
        expect(document.querySelector('[role="dialog"]')?.textContent).toContain('Punkte werden geprüft')
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
        await flushPromises()
        expect(document.querySelector('[role="dialog"]')).toBeNull()
    } finally { wrapper.unmount() }
})
it('requires a confirmed template before preparing the assignment', async () => {
    const prepare = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text: 'Auftrag ohne MC', context: { include_multiple_choice: false } } } })
    const wrapper = mountExport(prepare, undefined, undefined, work, undefined, vi.fn().mockResolvedValue({ data: { assessment_points: null } }))
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('keydown', { key: 'Enter' })
        await flushPromises()
        await flushPromises()
        expect(wrapper.text()).toContain('Punkte der Prüfungsvorlage konnten nicht eindeutig bestätigt werden')
        expect(button(wrapper, 'Übergabetext kopieren')).toBeUndefined()
        expect(button(wrapper, 'Erneut prüfen')).toBeDefined()
        expect(prepare).not.toHaveBeenCalled()
        expect(axios.post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})
it('shows failed template checks and allows retry before the handoff', async () => {
    let rejectCheck: (reason: Error) => void = () => {}
    const check = vi.fn().mockImplementation(() => new Promise((_resolve, reject) => { rejectCheck = reject }))
    const wrapper = mountExport(undefined, undefined, undefined, work, undefined, check)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('keydown', { key: 'Enter' })
        await flushPromises()
        expect(wrapper.text()).toContain('Punkte werden geprüft')
        rejectCheck(new Error('Offline'))
        await flushPromises()
        expect(wrapper.text()).toContain('Offline')
        expect(button(wrapper, 'Übergabetext kopieren')).toBeUndefined()
        expect(button(wrapper, 'Erneut prüfen')).toBeDefined()
    } finally { wrapper.unmount() }
})

it('retries an unconfirmed template and shows the form only after confirmed points', async () => {
    const check = vi.fn().mockResolvedValueOnce({ data: { assessment_points: null } }).mockResolvedValueOnce({ data: { assessment_points: 30 } })
    const wrapper = mountExport(undefined, undefined, undefined, work, undefined, check)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        const dialog = wrapper.get('[role="dialog"]')
        expect(dialog.text()).toContain('Punkte der Prüfungsvorlage konnten nicht eindeutig bestätigt werden')
        expect(dialog.find('input').exists()).toBe(false)
        await dialog.findAll('button').find(item => item.text() === 'Erneut prüfen')!.trigger('click')
        await flushPromises()
        expect(dialog.text()).toContain('Prüfungsvorlage: 30 Punkte')
        expect(dialog.get('input[aria-label="Multiple-Choice-Teil: Nein"]').exists()).toBe(true)
        expect(check).toHaveBeenCalledTimes(2)
    } finally { wrapper.unmount() }
})

it('uses the template points by default and sends an optional whole or half point target separately', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text: 'Aufgabenblattauftrag', context: { include_multiple_choice: false } } } })
    const wrapper = mountExport(get)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        const target = wrapper.get('input[aria-label="Alternative Gesamtpunkte für das Aufgabenblatt (optional)"]')
        expect(wrapper.text()).toContain('Für das Aufgabenblatt gelten 35 Punkte')
        await target.setValue('20.2')
        expect(wrapper.text()).toContain('Bitte eine nichtnegative ganze oder halbe Punktzahl eingeben')
        expect(button(wrapper, 'Übergabetext kopieren').attributes('disabled')).toBeDefined()
        await target.setValue('20.5')
        expect(wrapper.text()).toContain('Für das Aufgabenblatt gelten 20,5 Punkte')
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledWith('/api/admin/teaching/course_works/65/ai-export', expect.objectContaining({
            params: expect.objectContaining({ template_points_snapshot: 35, template_points_target: 20.5 }),
        }))
    } finally { wrapper.unmount() }
})
it('does not reuse earlier template points after switching work', async () => {
    let resolveFirst: (value: unknown) => void = () => {}
    const check = vi.fn().mockImplementationOnce(() => new Promise(resolve => { resolveFirst = resolve }))
        .mockResolvedValueOnce({ data: { assessment_points: 20 } })
    const wrapper = mountExport(undefined, undefined, undefined, work, undefined, check)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('keydown', { key: 'Enter' })
        await flushPromises()
        await wrapper.setProps({ work: { ...work, id: 66 } })
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('keydown', { key: 'Enter' })
        await flushPromises()
        await flushPromises()
        resolveFirst({ data: { assessment_points: 35 } })
        await flushPromises()
        expect(wrapper.text()).toContain('20 Punkte')
        expect(wrapper.text()).not.toContain('35 Punkte')
    } finally { wrapper.unmount() }
})
it('clears earlier template points while a new check is pending', async () => {
    let resolveSecond: (value: unknown) => void = () => {}
    const check = vi.fn().mockResolvedValueOnce({ data: { assessment_points: 35 } })
        .mockImplementationOnce(() => new Promise(resolve => { resolveSecond = resolve }))
    const wrapper = mountExport(undefined, undefined, undefined, work, undefined, check)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await flushPromises()
        expect(wrapper.text()).toContain('35 Punkte')
        await button(wrapper, 'Abbrechen').trigger('click')
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Punkte werden geprüft')
        expect(wrapper.text()).not.toContain('35 Punkte')
        resolveSecond({ data: { assessment_points: 20 } })
        await flushPromises()
        expect(wrapper.text()).toContain('20 Punkte')
    } finally { wrapper.unmount() }
})
it('discards delayed template points when the linked module changes', async () => {
    let resolveFirst: (value: unknown) => void = () => {}
    const check = vi.fn().mockImplementationOnce(() => new Promise(resolve => { resolveFirst = resolve }))
        .mockResolvedValueOnce({ data: { assessment_points: 20 } })
    const replacement = { connected: true, link_version: 'second', module_link: { folder: { id: 'id:second-module' } }, exercise: { status: 'missing' } }
    const status = vi.fn().mockResolvedValueOnce({ data: { connected: true, module_link: { folder: { id: 'id:module' } }, exercise: { status: 'missing' } } })
        .mockResolvedValue({ data: replacement })
    const wrapper = mountExport(undefined, undefined, status, work, undefined, check)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        workDropboxStates.set('7:2:65', replacement)
        await flushPromises()
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await flushPromises()
        resolveFirst({ data: { assessment_points: 35 } })
        await flushPromises()
        expect(wrapper.text()).toContain('20 Punkte')
        expect(wrapper.text()).not.toContain('35 Punkte')
    } finally { wrapper.unmount() }
})
it.each([false, true])('copies supplementary instructions with and without MC: %s', async includeMc => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text: 'Bestätigter Auftrag', context: { include_multiple_choice: false } } } })
    const writeText = vi.fn().mockResolvedValue(undefined)
    const wrapper = mountExport(get, writeText)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('keydown', { key: 'Enter' })
        await flushPromises()
        await flushPromises()
        expect(button(wrapper, 'Über OpenAI vorbereiten')).toBeUndefined()
        expect(wrapper.text()).not.toContain('Formabzüge für Betreff')
        const instructions = wrapper.get('textarea:not([readonly])')
        await instructions.setValue('Bitte zwei Aufgaben erstellen.')
        if (includeMc) {
            await wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').setValue(true)
            await flushPromises()
            await wrapper.get('select[aria-label="Multiple-Choice-Katalog"]').setValue('3')
        }
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(writeText).toHaveBeenLastCalledWith('Bestätigter Auftrag\n\nErgänzende Vorgaben oder Antworten auf Rückfragen:\nBitte zwei Aufgaben erstellen.')
        expect((wrapper.get('textarea[readonly]').element as HTMLTextAreaElement).value).toBe(
            'Bestätigter Auftrag\n\nErgänzende Vorgaben oder Antworten auf Rückfragen:\nBitte zwei Aufgaben erstellen.')

        await instructions.setValue('Bitte drei Aufgaben erstellen.')
        await button(wrapper, 'Text kopieren').trigger('click')
        await flushPromises()
        expect(writeText).toHaveBeenLastCalledWith('Bestätigter Auftrag\n\nErgänzende Vorgaben oder Antworten auf Rückfragen:\nBitte drei Aufgaben erstellen.')
        expect(get).toHaveBeenCalledTimes(1)
    } finally { wrapper.unmount() }
})

it('includes supplementary instructions in the manual copy fallback', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text: 'Auftrag', context: { include_multiple_choice: false } } } })
    const wrapper = mountExport(get, vi.fn().mockRejectedValue(new Error('Denied')))
    Object.defineProperty(document, 'execCommand', { configurable: true, value: vi.fn(() => false) })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await wrapper.get('textarea:not([readonly])').setValue('Weitere Vorgabe')
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        const field = wrapper.get('textarea[readonly]').element as HTMLTextAreaElement
        expect(field.value).toBe('Auftrag\n\nErgänzende Vorgaben oder Antworten auf Rückfragen:\nWeitere Vorgabe')
        expect(field.selectionEnd).toBe(field.value.length)
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

it('blocks copying when the server rejects a changed test assignment', async () => {
    const get = vi.fn()
    const wrapper = mountExport(get)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await flushPromises()
        vi.mocked(axios.post).mockRejectedValueOnce({ response: { data: { message: 'Testzuordnung wurde inzwischen geändert.' } } })
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Testzuordnung wurde inzwischen geändert.')
        expect(get).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})
it('shows an existing test assignment independently of template points', async () => {
    const check = vi.fn().mockResolvedValue({ data: { assessment_points: 30 } })
    const wrapper = mountExport(undefined, undefined, undefined, work, { tests: [{ id: 9, title: 'Zugeordneter Test' }], linked_test_id: 9 }, check)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await flushPromises()
        expect(wrapper.text()).toContain('Prüfungsvorlage: 30 Punkte')
        expect((wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').element as HTMLInputElement).checked).toBe(true)
        expect((wrapper.get('select[aria-label="Multiple-Choice-Katalog"]').element as HTMLSelectElement).value).toBe('3')
        expect(wrapper.find('input[aria-label="MC-Punkte"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})
it('uses one explicit read-only recheck without refreshing status on mount', async () => {
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
        expect(axios.post).toHaveBeenCalledWith(expect.stringContaining('/dropbox/refresh'), expect.objectContaining({ date: '2026-10-20', status_only: true }), expect.any(Object))
        expect(get).not.toHaveBeenCalled()
        expect(wrapper.emitted('deadline-saved')).toBeUndefined()
        expect(wrapper.emitted('check-busy')).toEqual([[true], [false]])
        expect(wrapper.findAll('button').filter(button => button.text().startsWith('Erneut prüfen'))).toHaveLength(1)
    } finally { wrapper.unmount() }
})

it('rechecks the exam folder after the saved work title changes', async () => {
    const renamed = { ...work, title: 'Neuer Titel' }
    const status = vi.fn().mockResolvedValueOnce({ data: linkedExercise }).mockResolvedValueOnce({ data: {
        ...linkedExercise, exercise: { ...linkedExercise.exercise, folder: { id: 'id:exercise', name: '2026-10-20 – Gruppe – Neuer Titel' } },
    } })
    const wrapper = mountExport(undefined, undefined, status)
    try {
        await flushPromises()
        await wrapper.setProps({ work: renamed })
        await flushPromises()
        expect(axios.post).toHaveBeenCalledWith(expect.stringContaining('/dropbox/refresh'), expect.objectContaining({ status_only: true }), expect.any(Object))
        expect(wrapper.emitted('exercise-status')?.at(-1)?.[0]).toMatchObject({ status: 'found', folder: { name: '2026-10-20 – Gruppe – Neuer Titel' } })
    } finally { wrapper.unmount() }
})

it('reports an unexpected collection error without overwriting the current deadline', async () => {
    const saved = { ...work, finish_until_date: '2026-11-13' }
    const status = vi.fn().mockResolvedValueOnce({ data: linkedExercise }).mockResolvedValueOnce({ data: {
        ...linkedExercise, saved_work: saved, collection_error: { message: 'Einsammelprüfung fehlgeschlagen.' },
    } })
    const wrapper = mountExport(vi.fn(), undefined, status)
    try {
        await wrapper.setProps({ checkProgress: true }); await flushPromises()
        await button(wrapper, 'Erneut prüfen').trigger('click'); await flushPromises()
        expect(wrapper.emitted('deadline-saved')).toBeUndefined()
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

it('clears the previous icon state while a fresh check is pending', async () => {
    let finish
    const preparation = { status: 'ready', prepared_at: '2026-10-24T16:00:00Z' }
    const confirmed = { ...linkedExercise, exercise: { ...linkedExercise.exercise, email_preparation: preparation } }
    const status = vi.fn().mockResolvedValueOnce({ data: confirmed })
        .mockImplementationOnce(() => new Promise(resolve => { finish = resolve }))
    const wrapper = mountExport(undefined, undefined, status)
    try {
        await flushPromises()
        await button(wrapper, 'Erneut prüfen').trigger('click')
        expect(wrapper.emitted('exercise-status').at(-1)[0]).toMatchObject({ status: 'checking', folder: null,
            emailPreparation: null, checking: true })
        expect(wrapper.emitted('check-busy').at(-1)).toEqual([true])
        finish({ data: confirmed }); await flushPromises()
        expect(wrapper.emitted('check-busy').at(-1)).toEqual([false])
    } finally { wrapper.unmount() }
})

it('displays confirmed preparation points and clears them when the next check fails', async () => {
    const preparation = { status: 'ready', components: { aufgabenblatt: { included: true, points: 35 },
        multiple_choice: { included: false, points: null } }, total_points: 35 }
    const confirmed = { ...linkedExercise, exercise: { ...linkedExercise.exercise, preparation } }
    const status = vi.fn().mockResolvedValueOnce({ data: confirmed }).mockRejectedValueOnce(new Error('offline'))
    const wrapper = mountExport(undefined, undefined, status)
    try {
        await flushPromises()
        expect(wrapper.text()).toContain('Prüfungsordner vorbereitet')
        expect(wrapper.text()).toContain('Gesamt 35 Punkte')
        expect(wrapper.emitted('exercise-status').at(-1)[0].preparation).toEqual(preparation)
        await button(wrapper, 'Erneut prüfen').trigger('click')
        await flushPromises()
        expect(wrapper.text()).not.toContain('Prüfungsordner vorbereitet')
        expect(wrapper.emitted('exercise-status').at(-1)[0]).toMatchObject({ status: 'unknown', preparation: null })
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
        await flushPromises()
        expect((wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').element as HTMLInputElement).checked).toBe(false)
        await wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').setValue(true)
        await button(wrapper, 'Abbrechen').trigger('click')
        expect(get).not.toHaveBeenCalled()
        expect(writeText).not.toHaveBeenCalled()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        expect((wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').element as HTMLInputElement).checked).toBe(false)
    } finally { wrapper.unmount() }
})

it.each([false, true])('saves the changed final deadline before preparing the exercise (MC: %s)', async includeMc => {
    const savedWork = { ...work, finish_until_date: '2026-10-25', finish_until_time: '13:30' }
    const status = vi.fn((url: string) => Promise.resolve({ data: url.endsWith('/refresh')
        ? { connected: true, module_link: { folder: { id: 'id:module' } }, exercise: { status: 'missing' }, saved_work: savedWork }
        : { connected: true, module_link: { folder: { id: 'id:module' } }, exercise: { status: 'missing' } } }))
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true, participants_count: 3,
        text: 'Auftrag mit neuer Frist', context: { include_multiple_choice: false } } } })
    const wrapper = mountExport(get, undefined, status, { ...work, finish_until_date: '2026-10-20', finish_until_time: null },
        { available: true, tests: [{ id: 9, title: 'Test' }], catalogs: [], linked_test_id: null })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        expect((wrapper.get('input[aria-label="Endgültige Abgabefrist"]').element as HTMLInputElement).value).toBe('2026-10-20')
        await wrapper.get('input[aria-label="Endgültige Abgabefrist"]').setValue('2026-10-25')
        await wrapper.get('input[aria-label="Uhrzeit (optional)"]').setValue('13:30')
        if (includeMc) {
            await wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').setValue(true)
            await flushPromises()
            await wrapper.get('select[aria-label="Multiple-Choice-Katalog"]').setValue('3')
        }
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(axios.post).toHaveBeenCalledWith('/api/admin/teaching/course_works/65/dropbox/refresh', expect.objectContaining({
            finish_until_date: '2026-10-25', finish_until_time: '13:30', expected: { date: '2026-10-20', time: null },
        }), { timeout: 120000 })
        expect(wrapper.emitted('deadline-saved')?.[0]).toEqual([{ data: savedWork }])
        expect(get).toHaveBeenCalledTimes(1)
        expect(navigator.clipboard.writeText).toHaveBeenCalledWith('Auftrag mit neuer Frist')
        expect((wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').element as HTMLInputElement).checked).toBe(includeMc)
        await wrapper.setProps({ work: savedWork })
        await flushPromises()
        expect(wrapper.get('[role="dialog"]').text()).toContain('Übergabetext kopiert')
        expect((wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').element as HTMLInputElement).checked).toBe(includeMc)
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
        await flushPromises()
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
        await flushPromises()
        expect(wrapper.get('[role="dialog"]').text()).toContain('Multiple-Choice-Teil')
        expect(get).not.toHaveBeenCalled()
        await button(wrapper, 'Abbrechen').trigger('click')
        await wrapper.setProps({ work: { ...work, status: { multiple_choice_tests: { '2026-10-20': { work_id: 65, test_id: 9 } } } } })
        await flushPromises()
        expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeDefined()
    } finally { wrapper.unmount() }
})

it('offers to prepare a new test from a chosen catalog when no test exists', async () => {
    const wrapper = mountExport(undefined, undefined, undefined, work, { tests: [], linked_test_id: null })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await flushPromises()
        await wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').setValue(true)
        await flushPromises()
        expect(button(wrapper, 'Übergabetext kopieren').attributes('disabled')).toBeDefined()
        await wrapper.get('select[aria-label="Multiple-Choice-Katalog"]').setValue('3')
        await flushPromises()
        expect(wrapper.text()).toContain('Für diese Durchführung wird ein neuer Test aus dem gewählten Katalog vorbereitet')
        expect((wrapper.get('input[aria-label="Fragenanzahl"]').element as HTMLInputElement).value).toBe('10')
        expect((wrapper.get('input[aria-label="Testpunkte"]').element as HTMLInputElement).value).toBe('10')
        expect((wrapper.get('input[aria-label="Bearbeitungszeit (Minuten)"]').element as HTMLInputElement).value).toBe('20')
        expect(button(wrapper, 'Übergabetext kopieren').attributes('disabled')).toBeUndefined()
        expect(axios.post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('requests exactly one new test from the selected catalog and does not repeat creation when copying again', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text: 'Aufgabenblattauftrag', context: { include_multiple_choice: false } } } })
    const wrapper = mountExport(get, undefined, undefined, work, { tests: [], linked_test_id: null })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').setValue(true)
        await flushPromises()
        await wrapper.get('select[aria-label="Multiple-Choice-Katalog"]').setValue('3')
        await flushPromises()
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(axios.post).toHaveBeenCalledWith('/api/admin/teaching/course_works/65/prepare-exercise',
            { mc_selection: 'yes', catalog_id: 3, date: '2026-10-20', test_id: null, expected_test_id: null,
                question_count: 10, total_points: 10, duration_minutes: 20 }, expect.objectContaining({ signal: expect.any(AbortSignal) }))
        expect(wrapper.emitted('deadline-saved')?.at(-1)?.[0]).toEqual({ data: work })
        await button(wrapper, 'Kopiert').trigger('click')
        await flushPromises()
        expect(axios.post).toHaveBeenCalledTimes(1)
    } finally { wrapper.unmount() }
})

it('keeps custom MC settings across catalogue checks and submits them only within the catalogue limit', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text: 'Aufgabenblattauftrag', context: { include_multiple_choice: false } } } })
    const wrapper = mountExport(get, undefined, undefined, work, { tests: [], available_question_count: 8, linked_test_id: null })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').setValue(true)
        await flushPromises()
        const catalog = wrapper.get('select[aria-label="Multiple-Choice-Katalog"]')
        await catalog.setValue('3')
        await flushPromises()
        expect(wrapper.text()).toContain('Bitte eine ganze Zahl von 1 bis 8 eingeben.')
        expect(button(wrapper, 'Übergabetext kopieren').attributes('disabled')).toBeDefined()
        expect(axios.post).not.toHaveBeenCalled()
        await wrapper.get('input[aria-label="Fragenanzahl"]').setValue('7')
        await wrapper.get('input[aria-label="Testpunkte"]').setValue('12.5')
        await wrapper.get('input[aria-label="Bearbeitungszeit (Minuten)"]').setValue('25')
        await catalog.setValue('')
        await catalog.setValue('3')
        await flushPromises()
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/prepare-exercise',
            expect.objectContaining({ test_id: null, question_count: 7, total_points: 12.5, duration_minutes: 25 }),
            expect.objectContaining({ signal: expect.any(AbortSignal) }))
        expect(get.mock.calls[0][1].params).not.toHaveProperty('total_points')
    } finally { wrapper.unmount() }
})

it.each(['success', 'context change'])('starts the clipboard request on the first click before a slow handoff: %s', async outcome => {
    let resolveExport!: (value: unknown) => void
    const get = vi.fn(() => new Promise(resolve => { resolveExport = resolve }))
    const wrapper = mountExport(get)
    const consumed = vi.fn()
    vi.stubGlobal('ClipboardItem', class {
        representations: Record<string, Promise<Blob>>
        constructor(representations: Record<string, Promise<Blob>>) { this.representations = representations }
    })
    const write = vi.fn(async ([item]) => consumed(await item.representations['text/plain']))
    Object.assign(navigator.clipboard, { write })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(write).toHaveBeenCalledOnce()
        expect(write.mock.invocationCallOrder[0]).toBeLessThan(get.mock.invocationCallOrder[0])
        expect(consumed).not.toHaveBeenCalled()
        if (outcome === 'context change') await wrapper.setProps({ date: '2026-10-21' })
        resolveExport({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
            participants_count: 1, text: 'Bestätigter Auftrag', context: { include_multiple_choice: false } } } })
        await flushPromises()
        if (outcome === 'context change') {
            expect(consumed).not.toHaveBeenCalled()
            expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        } else {
            expect(consumed).toHaveBeenCalledWith(expect.objectContaining({ type: 'text/plain', size: new Blob(['Bestätigter Auftrag']).size }))
            expect(button(wrapper, 'Kopiert')).toBeDefined()
        }
        expect(navigator.clipboard.writeText).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('retries a denied clipboard write directly without repeating any server changes', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text: 'Bestätigter Auftrag', context: { include_multiple_choice: false } } } })
    const denied = new DOMException('Denied', 'NotAllowedError')
    const writeText = vi.fn().mockRejectedValue(denied)
    const wrapper = mountExport(get, writeText)
    vi.stubGlobal('ClipboardItem', class {})
    Object.assign(navigator.clipboard, { write: vi.fn().mockRejectedValue(denied) })
    const legacyCopy = vi.fn().mockReturnValueOnce(false).mockReturnValueOnce(true)
    Object.defineProperty(document, 'execCommand', { configurable: true, value: legacyCopy })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Automatisches Kopieren war nicht möglich')
        expect(button(wrapper, 'Kopiert')).toBeUndefined()
        await button(wrapper, 'Text kopieren').trigger('click')
        await flushPromises()
        expect(button(wrapper, 'Kopiert')).toBeDefined()
        expect(legacyCopy).toHaveBeenCalledTimes(2)
        expect(writeText).toHaveBeenCalledTimes(1)
        expect(axios.post).toHaveBeenCalledTimes(1)
        expect(get).toHaveBeenCalledTimes(1)
    } finally { wrapper.unmount() }
})

it('requires an explicit choice when multiple matching tests exist', async () => {
    const options = { tests: [{ id: 9, title: 'Test 1' }, { id: 10, title: 'Test 2' }], linked_test_id: null }
    const wrapper = mountExport(undefined, undefined, undefined, work, options)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').setValue(true)
        await flushPromises()
        await wrapper.get('select[aria-label="Multiple-Choice-Katalog"]').setValue('3')
        await flushPromises()
        expect(button(wrapper, 'Übergabetext kopieren').attributes('disabled')).toBeDefined()
        await wrapper.get('select[aria-label="Passender Multiple-Choice-Test"]').setValue('10')
        expect(button(wrapper, 'Übergabetext kopieren').attributes('disabled')).toBeUndefined()
        expect(wrapper.text()).toContain('Test: 10 Fragen, 10 Punkte, 20 Minuten.')
        expect(wrapper.find('input[aria-label="Fragenanzahl"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})
it('does not offer catalogue creation or MC points in the assessment dialog', async () => {
    const check = vi.fn().mockResolvedValue({ data: { assessment_points: 35, module_catalog: { count: 40, fingerprint: 'a'.repeat(64) } } })
    const wrapper = mountExport(undefined, undefined, undefined, work, { tests: [], linked_test_id: null }, check)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await flushPromises()
        await wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').setValue(true)
        await flushPromises()
        expect(wrapper.text()).toContain('Prüfungsvorlage: 35 Punkte')
        expect(wrapper.text()).not.toContain('Fragenkatalog: 40')
        expect(wrapper.find('input[aria-label="MC-Punkte"]').exists()).toBe(false)
        expect(button(wrapper, 'Übergabetext kopieren').attributes('disabled')).toBeDefined()
        expect(axios.post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})
it.each([35, 30])('keeps template points separate from an existing test on a prepared exam: %s', async points => {
    const prepared = { ...linkedExercise, exercise: { ...linkedExercise.exercise, preparation: { status: 'ready',
        components: { aufgabenblatt: { included: true, points: 35 }, multiple_choice: { included: false, points: null } }, total_points: 35 } } }
    const status = vi.fn().mockResolvedValue({ data: prepared })
    const check = vi.fn().mockResolvedValue({ data: { assessment_points: points } })
    const wrapper = mountExport(undefined, undefined, status, work, { tests: [{ id: 9, title: 'Test' }], linked_test_id: 9 }, check)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung vorbereitet').trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="dialog"]').text()).toContain(`Prüfungsvorlage: ${points} Punkte`)
        expect((wrapper.get('select[aria-label="Multiple-Choice-Katalog"]').element as HTMLSelectElement).value).toBe('3')
        expect(wrapper.find('input[aria-label="MC-Punkte"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})
it('does not prefill an MC point allocation in a test assignment', async () => {
    const wrapper = mountExport(undefined, undefined, undefined, work, { tests: [{ id: 9, title: 'Test' }], linked_test_id: 9 })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await flushPromises()
        expect(wrapper.find('input[aria-label="MC-Punkte"]').exists()).toBe(false)
        expect(wrapper.find('input[aria-label="Punkte übrige Aufgaben"]').exists()).toBe(false)
        expect((wrapper.get('select[aria-label="Multiple-Choice-Katalog"]').element as HTMLSelectElement).value).toBe('3')
    } finally { wrapper.unmount() }
})
it('does not silently replace a previously linked test missing from the list', async () => {
    const wrapper = mountExport(undefined, undefined, undefined, work, { tests: [], linked_test_id: 6 })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await flushPromises()
        expect(wrapper.text()).toContain('Der zugeordnete Test ist nicht mehr verfügbar')
        expect(button(wrapper, 'Übergabetext kopieren').attributes('disabled')).toBeDefined()
        expect(axios.post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})
it('allows the handoff without MC when no test is available', async () => {
    const wrapper = mountExport(undefined, undefined, undefined, work, { tests: [], linked_test_id: null })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await flushPromises()
        expect((wrapper.get('input[aria-label="Multiple-Choice-Teil: Nein"]').element as HTMLInputElement).checked).toBe(true)
        expect(wrapper.text()).toContain('35 Punkte')
        expect(button(wrapper, 'Übergabetext kopieren').attributes('disabled')).toBeUndefined()
        expect(axios.post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})
it.each([false, true])('loads a fresh candidate export after confirming the chosen test assignment (%s)', async mc => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    const text = 'Aufgabenblatt und Beurteilung; keine persönlichen MC-PDFs.'
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 3, text, context: { include_multiple_choice: false } } } })
    const wrapper = mountExport(get, writeText)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await flushPromises()
        if (mc) {
            await wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').setValue(true)
            await flushPromises()
            await wrapper.get('select[aria-label="Multiple-Choice-Katalog"]').setValue('3')
        }
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(axios.post).toHaveBeenCalledWith('/api/admin/teaching/course_works/65/prepare-exercise', {
            mc_selection: mc ? 'yes' : 'no', test_id: mc ? 9 : null, ...(mc ? { catalog_id: 3 } : {}), expected_test_id: null, date: '2026-10-20',
        }, { signal: expect.any(AbortSignal) })
        expect(get).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/ai-export', {
            params: { purpose: 'exercise', include_mc: 0, template_points_snapshot: 35, date: '2026-10-20' }, signal: expect.any(AbortSignal),
        })
        expect(writeText).toHaveBeenCalledExactlyOnceWith(text)
        expect(wrapper.findAllComponents(VBtn).find(item => item.text() === 'Kopiert')?.props()).toMatchObject({ prependIcon: 'mdi-check', color: 'success' })
        expect(wrapper.text()).toContain('3 bestätigte Prüfungskandidaten')
        await wrapper.get(mc ? 'input[aria-label="Multiple-Choice-Teil: Nein"]' : 'input[aria-label="Multiple-Choice-Teil: Ja"]').setValue(true)
        expect(button(wrapper, 'Übergabetext kopieren')).toBeDefined()
    } finally { wrapper.unmount() }
})
it.each([[false, false], [true, false], [false, 'throws'], [true, 'throws']])('keeps the chosen test state in the manual copy helper: %s, %s', async (includeMc, legacyResult) => {
    const text = 'Aufgabenblatt und Beurteilung; keine persönlichen MC-PDFs.'
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text, context: { include_multiple_choice: false } } } })
    const wrapper = mountExport(get, vi.fn().mockRejectedValue(new Error('Denied')))
    const legacyCopy = vi.fn(() => { if (legacyResult === 'throws') throw new Error('Denied'); return false })
    Object.defineProperty(document, 'execCommand', { configurable: true, value: legacyCopy })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await flushPromises()
        if (includeMc) {
            await wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').setValue(true)
            await flushPromises()
            await wrapper.get('select[aria-label="Multiple-Choice-Katalog"]').setValue('3')
        }
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        const field = wrapper.get('textarea[aria-label="Leistungsfeststellungsauftrag zum Kopieren"]').element as HTMLTextAreaElement
        expect(field.value).toBe(text)
        expect((wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').element as HTMLInputElement).checked).toBe(includeMc)
        expect(field.closest('details')?.open).toBe(true)
        expect(field.selectionStart).toBe(0)
        expect(field.selectionEnd).toBe(field.value.length)
        expect(wrapper.text()).toContain('Automatisches Kopieren war nicht möglich')
        legacyCopy.mockImplementation(() => true)
        await button(wrapper, 'Text kopieren').trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="status"][type="success"]').text()).toContain('in der Zwischenablage')
        expect(get).toHaveBeenCalledTimes(1)
    } finally { wrapper.unmount() }
})
it('copies through the legacy method and retries without creating a test', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text: 'Aufgabenblattauftrag', context: { include_multiple_choice: false } } } })
    const wrapper = mountExport(get, vi.fn().mockRejectedValue(new Error('Denied')))
    const legacyCopy = vi.fn(() => true)
    Object.defineProperty(document, 'execCommand', { configurable: true, value: legacyCopy })
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await flushPromises()
        await wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').setValue(true)
        await flushPromises()
        await wrapper.get('select[aria-label="Multiple-Choice-Katalog"]').setValue('3')
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="status"][type="success"]').text()).toContain('in der Zwischenablage')
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
        await flushPromises()
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
        await flushPromises()
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
        expect(wrapper.get('[aria-label="Leistungsfeststellungsordner"]').text()).toBe(`LF: ${linkedExercise.exercise.folder.name}`)
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
            progress_id: expect.any(String), stream_progress: true, status_only: true,
        }, expect.objectContaining({ timeout: 120000, onDownloadProgress: expect.any(Function) }))
        status.mockResolvedValue({ data: { connected: true, module_link: null, exercise: { status: 'missing', folder: null } } })
        workDropboxStates.set('7:2:65', { connected: true, module_link: null, link_version: 'unlinked' })
        await flushPromises()
        expect(wrapper.find('[aria-label="Leistungsfeststellungsordner"]').exists()).toBe(false)
        expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('checks without saving the edited internal deadline or other form changes', async () => {
    const saved = { ...work, finish_until_date: '2026-10-23', finish_until_time: null }
    const dispatch = { status: 'sent', confirmed_count: 15, expected_count: 15, transport: 'mailpit' }
    const status = vi.fn().mockResolvedValueOnce({ data: linkedExercise }).mockResolvedValueOnce({
        data: { ...linkedExercise, exercise: { ...linkedExercise.exercise, email_dispatch: dispatch }, saved_work: saved },
    })
    const wrapper = mountExport(undefined, undefined, status, { ...work, finish_until_date: '2026-10-23', finish_until_time: null })
    try {
        await wrapper.setProps({ deadlineDraft: { date: '2026-10-24', time: '12:30' } })
        await flushPromises()
        await button(wrapper, 'Erneut prüfen').trigger('click')
        await flushPromises()
        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/dropbox/refresh', {
            date: '2026-10-20', finish_until_date: '2026-10-23', finish_until_time: null,
            expected: { date: '2026-10-23', time: null },
            progress_id: expect.any(String), stream_progress: true, status_only: true,
        }, expect.objectContaining({ timeout: 120000, onDownloadProgress: expect.any(Function) }))
        expect(wrapper.emitted('deadline-saved')).toBeUndefined()
        expect(wrapper.emitted('exercise-status')?.at(-1)?.[0]).toMatchObject({ status: 'found', emailDispatch: dispatch })
        expect(wrapper.emitted('check-busy')).toEqual([[true], [false]])
        expect(wrapper.text()).not.toContain('Interne Endfrist übernommen.')
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

it.each([false, true])('opens the corrected dialog immediately for a new or prepared exam (%s)', async prepared => {
    let finish: (value: unknown) => void = () => {}
    const templateCheck = vi.fn().mockImplementation(() => new Promise(resolve => { finish = resolve }))
    const status = vi.fn().mockResolvedValue({ data: { connected: true, module_link: { folder: { id: 'id:module' } },
        exercise: prepared ? { status: 'found', folder: { id: 'id:exercise', name: 'Prüfung' }, preparation: { status: 'ready',
            components: { aufgabenblatt: { points: 35 }, multiple_choice: { included: false, points: null } }, total_points: 35 } } : { status: 'missing' } } })
    const wrapper = mountExport(undefined, undefined, status, work, undefined, templateCheck)
    try {
        await flushPromises()
        await button(wrapper, prepared ? 'Leistungsfeststellung vorbereitet' : 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        const dialog = wrapper.get('[role="dialog"]')
        const loadingStatus = dialog.get('[role="status"]')
        expect(loadingStatus.text()).toContain('Punkte werden geprüft')
        expect(loadingStatus.attributes('aria-live')).toBe('polite')
        expect(document.activeElement).toBe(loadingStatus.element)
        expect(dialog.find('input').exists()).toBe(false)
        expect(dialog.find('textarea').exists()).toBe(false)
        expect(dialog.find('button').exists()).toBe(false)
        expect(wrapper.text()).not.toContain('Multiple-Choice-Fragen werden geprüft')
        finish({ data: { assessment_points: 35 } })
        await flushPromises()
        expect(wrapper.get('[role="dialog"]').text()).toContain('35 Punkte')
        expect(axios.get).not.toHaveBeenCalledWith(expect.anything(), expect.objectContaining({ params: expect.objectContaining({ check_module_questions: 1 }) }))
        expect(axios.get).not.toHaveBeenCalledWith(expect.anything(), expect.objectContaining({ params: expect.objectContaining({ mc_selection: 'tests' }) }))
    } finally { wrapper.unmount() }
})

it('assigns one selected existing test independently of the exam handoff', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text: 'Aufgabenblattauftrag', context: { include_multiple_choice: false } } } })
    const wrapper = mountExport(get)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await flushPromises()
        await wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').setValue(true)
        await flushPromises()
        await wrapper.get('select[aria-label="Multiple-Choice-Katalog"]').setValue('3')
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(axios.post).toHaveBeenCalledWith('/api/admin/teaching/course_works/65/prepare-exercise',
            { mc_selection: 'yes', date: '2026-10-20', catalog_id: 3, test_id: 9, expected_test_id: null }, expect.objectContaining({ signal: expect.any(AbortSignal) }))
        expect(wrapper.emitted('deadline-saved')?.at(-1)?.[0]).toEqual({ data: work })
        expect(get).toHaveBeenCalledWith('/api/admin/teaching/course_works/65/ai-export', expect.objectContaining({ params: {
            purpose: 'exercise', include_mc: 0, date: '2026-10-20', template_points_snapshot: 35 } }))
    } finally { wrapper.unmount() }
})

it('removes only the previous test assignment when the choice changes to no', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text: 'Aufgabenblattauftrag', context: { include_multiple_choice: false } } } })
    const options = { linked_test_id: 9, tests: [{ id: 9, title: 'Bestehender Test' }] }
    const wrapper = mountExport(get, undefined, undefined, work, options)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await flushPromises()
        await flushPromises()
        expect((wrapper.get('input[aria-label="Multiple-Choice-Teil: Ja"]').element as HTMLInputElement).checked).toBe(true)
        await wrapper.get('input[aria-label="Multiple-Choice-Teil: Nein"]').setValue(true)
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(axios.post).toHaveBeenCalledWith('/api/admin/teaching/course_works/65/prepare-exercise',
            { mc_selection: 'no', date: '2026-10-20', test_id: null, expected_test_id: 9 }, expect.objectContaining({ signal: expect.any(AbortSignal) }))
        expect(get).toHaveBeenCalledOnce()
    } finally { wrapper.unmount() }
})
