import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components'
import WorkExerciseExport from '@/pages/admin/teaching/overview/components/WorkExerciseExport.vue'
import { clearWorkDropboxStates, workDropboxStates } from '@/helpers/workDropbox'

const work = { id: 65, teaching_course_id: 2, title: 'E-Mails' }
const course = { id: 2, title: 'INF' }

function mountExport(get = vi.fn(), writeText = vi.fn().mockResolvedValue(undefined), status = vi.fn().mockResolvedValue({ data: { connected: false } })) {
    vi.stubGlobal('axios', { get: vi.fn((url, options) => url.endsWith('/dropbox') ? status(url, options) : get(url, options)) })
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    return mount(WorkExerciseExport, { attachTo: document.body, props: { work, course, date: '2026-10-20' }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } }), createVuetify({ components: { VBtn } })],
        components: { 'v-btn': VBtn }, stubs: { 'v-btn': false, VBtn: false,
            'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue" role="dialog"><slot /></div>' },
            'v-checkbox': { props: ['modelValue', 'label', 'disabled'], emits: ['update:modelValue'],
                template: '<label><input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.checked)" />{{ label }}</label>' },
        },
    } })
}

function button(wrapper: ReturnType<typeof mountExport>, text: string) {
    return wrapper.findAll('button').find(item => item.text() === text)!
}

afterEach(() => { clearWorkDropboxStates(); vi.restoreAllMocks(); vi.unstubAllGlobals(); document.body.innerHTML = '' })

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

it.each([false, true])('loads a fresh candidate export and copies only the chosen MC handoff (%s)', async mc => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    const text = `Verwende den Skill create-exercise. MC-Prüfungsteil: ${mc ? 'Ja' : 'Nein'}.`
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        participants_count: 3, text, context: { include_multiple_choice: mc } } } })
    const wrapper = mountExport(get, writeText)
    try {
        await flushPromises()
        await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
        if (mc) await wrapper.get('input[type="checkbox"]').setValue(true)
        await button(wrapper, 'Übergabetext kopieren').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/ai-export', {
            params: { purpose: 'exercise', include_mc: mc ? 1 : 0, date: '2026-10-20' }, signal: expect.any(AbortSignal),
        })
        expect(writeText).toHaveBeenCalledExactlyOnceWith(text)
        expect(wrapper.get('[role="status"]').text()).toContain('Übergabetext kopiert')
        expect(wrapper.text()).toContain('3 bestätigte Prüfungskandidaten')
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
    } finally { wrapper.unmount() }
})

it('does not export an unsaved work or copy an unconfirmed roster', async () => {
    const get = vi.fn().mockResolvedValue({ data: { data: { work_id: 65, course_id: 2, roster_complete: false,
        participants_count: 0, text: 'invalid', context: { include_multiple_choice: false } } } })
    const writeText = vi.fn()
    const wrapper = mountExport(get, writeText)
    try {
        await wrapper.setProps({ work: { ...work, id: null } })
        expect(button(wrapper, 'Leistungsfeststellung erstellen').attributes('disabled')).toBeDefined()
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

it.each([false, true])('keeps export usable when no module or no unambiguous folder is confirmed (%s)', async linked => {
    const status = vi.fn().mockResolvedValue({ data: linked ? { ...linkedExercise, exercise: { status: 'unknown', folder: null, message: 'Mehrere passende Ordner.' } }
        : { ...linkedExercise, module_link: null } })
    const wrapper = mountExport(undefined, undefined, status)
    try {
        await flushPromises()
        expect(wrapper.find('[aria-label="Leistungsfeststellungsordner"]').exists()).toBe(false)
        expect(button(wrapper, 'Leistungsfeststellung erstellen').attributes('disabled')).toBeUndefined()
        if (linked) expect(wrapper.get('[role="status"]').text()).toContain('Mehrere passende Ordner')
    } finally { wrapper.unmount() }
})

it('refreshes the external folder on reopening and clears it immediately when unlinked', async () => {
    const status = vi.fn().mockResolvedValue({ data: linkedExercise })
    const wrapper = mountExport(undefined, undefined, status)
    try {
        await flushPromises()
        await wrapper.setProps({ active: false })
        expect(wrapper.find('[aria-label="Leistungsfeststellungsordner"]').exists()).toBe(false)
        await wrapper.setProps({ active: true })
        await flushPromises()
        expect(status).toHaveBeenCalledTimes(2)
        expect(wrapper.find('[aria-label="Leistungsfeststellungsordner"]').exists()).toBe(true)
        status.mockResolvedValue({ data: { connected: true, module_link: null, exercise: { status: 'missing', folder: null } } })
        workDropboxStates.set('7:2:65', { connected: true, module_link: null, link_version: 'unlinked' })
        await flushPromises()
        expect(wrapper.find('[aria-label="Leistungsfeststellungsordner"]').exists()).toBe(false)
        expect(button(wrapper, 'Leistungsfeststellung erstellen').exists()).toBe(true)
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
        expect(button(wrapper, 'Leistungsfeststellung erstellen').exists()).toBe(true)
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
        expect(Boolean(button(wrapper, 'Leistungsfeststellung erstellen'))).toBe(!found)
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
        expect(button(wrapper, 'Leistungsfeststellung erstellen').exists()).toBe(true)
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
