import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components'
import WorkExerciseExport from '@/pages/admin/teaching/overview/components/WorkExerciseExport.vue'
import { clearWorkDropboxStates, workDropboxStates } from '@/helpers/workDropbox'

const work = { id: 65, teaching_course_id: 2, title: 'E-Mails' }
const course = { id: 2, title: 'INF' }

function mountExport(get = vi.fn(), writeText = vi.fn().mockResolvedValue(undefined), status = vi.fn().mockResolvedValue({ data: { connected: true, module_link: { folder: { id: 'id:module' } }, exercise: { status: 'missing' } } }), currentWork: any = work,
    mcOptions: any = { available: true, tests: [{ id: 9, title: 'Vorhandener MC-Test', catalog_id: 3, catalog_title: 'Grundlagen' }], catalogs: [{ id: 3, title: 'Grundlagen' }], linked_test_id: 9 }) {
    vi.stubGlobal('axios', { get: vi.fn((url, options) => url.endsWith('/dropbox') ? status(url, options)
        : url.endsWith('/exercise-options') ? Promise.resolve({ data: { configuration: { locked: false, maximum_points: 5 },
            linked_mc_points: mcOptions.linked_test_id ? 2 : null, ...mcOptions } }) : get(url, options)),
        post: vi.fn((url, payload, options) => url.endsWith('/prepare-exercise') ? get(url, payload, options) : status(url, payload, options)) })
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    return mount(WorkExerciseExport, { attachTo: document.body, props: { work: currentWork, course, date: '2026-10-20' }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } }), createVuetify({ components: { VBtn } })],
        components: { 'v-btn': VBtn }, stubs: { 'v-btn': false, VBtn: false,
            'v-text-field': { props: ['modelValue', 'label', 'disabled', 'type'], emits: ['update:modelValue'],
                template: '<label>{{ label }}<input :aria-label="label" :type="type" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value === \'\' ? null : Number($event.target.value))" /></label>' },
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
    return wrapper.findAll('button').find(item => item.text() === text || (text === 'Erneut prüfen' && item.text().startsWith(text)))!
}

afterEach(() => { clearWorkDropboxStates(); vi.restoreAllMocks(); vi.unstubAllGlobals(); document.body.innerHTML = '' })

it.each(['sent', 'partial'])('blocks adding MC after a confirmed %s participant dispatch', async (dispatchStatus) => {
    const status = vi.fn().mockResolvedValue({ data: { ...linkedExercise,
        exercise: { ...linkedExercise.exercise, email_dispatch: { status: dispatchStatus, confirmed_count: 1 } } } })
    const wrapper = mountExport(undefined, undefined, status)
    try {
        await flushPromises()
        expect(button(wrapper, 'MC-Fragen einbeziehen').attributes('disabled')).toBeDefined()
        await button(wrapper, 'MC-Fragen einbeziehen').trigger('click')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(axios.post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('can reopen MC configuration for an existing unsent LF and always reloads its point allocation', async () => {
    const status = vi.fn().mockResolvedValue({ data: { ...linkedExercise,
        exercise: { ...linkedExercise.exercise, email_dispatch: { status: 'missing', confirmed_count: 0 } } } })
    const wrapper = mountExport(undefined, undefined, status)
    try {
        await flushPromises()
        for (let opening = 0; opening < 2; opening++) {
            await button(wrapper, 'MC-Fragen einbeziehen').trigger('click')
            await flushPromises()
            expect((wrapper.get('input[type="checkbox"]').element as HTMLInputElement).checked).toBe(true)
            expect(wrapper.text()).toContain('2 Punkte MC + 3 Punkte übrige Aufgaben = 5 Punkte gesamt')
            expect(button(wrapper, 'Übergabetext kopieren').attributes('disabled')).toBeUndefined()
            await button(wrapper, 'Abbrechen').trigger('click')
            await flushPromises()
        }
        expect(axios.post).not.toHaveBeenCalled()
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
    const status = vi.fn().mockResolvedValue({ data: linkedExercise })
    const wrapper = mountExport(get, undefined, status)
    try {
        await wrapper.setProps({ checkProgress: true })
        await flushPromises()
        expect(get).not.toHaveBeenCalled()
        expect(wrapper.findAll('button').filter(button => button.text().startsWith('Erneut prüfen'))).toHaveLength(1)
        await button(wrapper, 'Erneut prüfen').trigger('click')
        await flushPromises()
        expect(axios.post).toHaveBeenCalledWith(expect.stringContaining('/dropbox/refresh'), expect.objectContaining({ date: '2026-10-20' }), expect.any(Object))
        expect(get).toHaveBeenCalledWith(expect.stringContaining('/course_works/65'), { params: { refresh_collection: true } })
        expect(wrapper.emitted('deadline-saved')).toEqual([[{ data: saved }]])
        expect(wrapper.emitted('check-busy')).toEqual([[true], [false]])
        expect(wrapper.findAll('button').filter(button => button.text().startsWith('Erneut prüfen'))).toHaveLength(1)
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
        expect(get).toHaveBeenCalledWith(expect.stringContaining('/course_works/65'), { params: { refresh_collection: true } })
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
        expect(wrapper.findAll('button').filter(item => item.text() === 'Leistungsfeststellung erstellen')).toHaveLength(1)
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Multiple-Choice-Fragen einbeziehen', 'Übergabetext kopieren')
        expect(get).not.toHaveBeenCalled()
        await button(wrapper, 'Abbrechen').trigger('click')
        await wrapper.setProps({ work: { ...work, status: { multiple_choice_tests: { '2026-10-20': { work_id: 65, test_id: 9 } } } } })
        await flushPromises()
        expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeDefined()
    } finally { wrapper.unmount() }
})

it('requires an explicit MC selection and creates a draft only on the handoff action, reusing its request after a retry', async () => {
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
        await wrapper.get('input[aria-label="MC-Anteil an den LF-Gesamtpunkten"]').setValue('6')
        expect(button(wrapper, action).attributes('disabled')).toBeDefined()
        await wrapper.get('input[aria-label="MC-Anteil an den LF-Gesamtpunkten"]').setValue('2')
        expect(wrapper.text()).toContain('2 Punkte MC + 3 Punkte übrige Aufgaben = 5 Punkte gesamt')
        expect(button(wrapper, action).attributes('disabled')).toBeUndefined()
        await button(wrapper, action).trigger('click'); await flushPromises()
        const payload = (axios.post as any).mock.calls[0][1]
        expect(payload).toMatchObject({ mc_mode: 'new', catalog_id: 3, total_points: 10, mc_points: 2, expected_maximum_points: 5, duration_minutes: 20, expected_test_id: null, date: '2026-10-20' })
        await button(wrapper, 'Kopiert').trigger('click'); await flushPromises()
        expect((axios.post as any).mock.calls[1][1]).toEqual(payload)
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
            mc_mode: 'existing', test_id: 9, expected_test_id: 9, mc_points: 2, expected_maximum_points: 5, date: '2026-10-20', request_id: expect.any(String),
        }, { signal: expect.any(AbortSignal) })
        else expect(get).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/ai-export', {
            params: { purpose: 'exercise', include_mc: 0, date: '2026-10-20' }, signal: expect.any(AbortSignal),
        })
        expect(writeText).toHaveBeenCalledExactlyOnceWith(text)
        expect(wrapper.findAll('[role="status"]').some(status => status.text().includes('Übergabetext kopiert'))).toBe(true)
        expect(wrapper.findAllComponents(VBtn).find(item => item.text() === 'Kopiert')?.props()).toMatchObject({ prependIcon: 'mdi-check', color: 'success' })
        expect(button(wrapper, 'Schließen')).toBeDefined()
        expect(button(wrapper, 'Abbrechen')).toBeUndefined()
        expect(wrapper.text()).toContain('3 bestätigte Prüfungskandidaten')
        await wrapper.get('input[type="checkbox"]').setValue(!mc)
        expect(button(wrapper, 'Übergabetext kopieren')).toBeDefined()
        expect(button(wrapper, 'Kopiert')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('provides fully selected plain text when clipboard access fails', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 1, text: 'Vollständiger Übergabetext', context: { include_multiple_choice: false } } } })
    const wrapper = mountExport(get, vi.fn().mockRejectedValue(new Error('Denied')))
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        const field = wrapper.get('textarea').element as HTMLTextAreaElement
        expect(field.value).toBe('Vollständiger Übergabetext')
        expect(field.closest('details')?.open).toBe(true)
        expect(field.selectionStart).toBe(0)
        expect(field.selectionEnd).toBe(field.value.length)
        expect(button(wrapper, 'Kopiert')).toBeUndefined()
        expect(button(wrapper, 'Übergabetext kopieren')).toBeDefined()
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
        }, { timeout: 120000 })
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
        }, { timeout: 120000 })
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
