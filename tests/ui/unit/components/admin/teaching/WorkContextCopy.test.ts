import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components'
import WorkContextCopy from '@/pages/admin/teaching/overview/components/WorkContextCopy.vue'
import { clearWorkDropboxStates, workDropboxStates } from '@/helpers/workDropbox'

const work = { id: 65, teaching_course_id: 2, type: 'A5', title: 'Beispielarbeit', date_for_all_groups: '2026-10-01',
    description: 'Nicht kopieren', groups: [{ student_ids: [12], grades: [{ student_id: 12, grade: '1' }] }] }
const course = { id: 2, title: 'INF – 5L2 – 1', classes: ['5L2'], students_info: [{ first_name: 'Privater Name' }] }
const moduleLink = { module_id: 'a263b873-4f27-4167-90f0-7d94ea0f67fa',
    folder: { id: 'id:module', name: 'E-Mails  Kopie', path: '/Materialien/Lerninhalte/E-Mails  Kopie' } }

function mountCopy(props = {}) {
    return mount(WorkContextCopy, { props: { work, course, date: '2026-10-15', ...props }, attachTo: document.body,
        global: {
            plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } }), createVuetify({ components: { VBtn } })],
            components: { 'v-btn': VBtn }, stubs: { 'v-btn': false, VBtn: false },
        },
    })
}

afterEach(() => {
    clearWorkDropboxStates()
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
})

it('copies only four context lines with work and course identities and the clicked lesson date', async () => {
    workDropboxStates.set('7:2:65', { folder: { id: 'id:example', name: 'Beispielordner', path: '/Schule/Beispielordner' } })
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    const wrapper = mountCopy()
    try {
        await flushPromises()
        expect(writeText).not.toHaveBeenCalled()
        await wrapper.get('button').trigger('click')
        await flushPromises()

        expect(writeText).toHaveBeenCalledExactlyOnceWith('Arbeit: A5 – Beispielarbeit (ID: 65)\nKurs: INF – 5L2 – 1 (ID: 2)\nDatum: 15.10.2026\nOrdner: verknüpft – Dropbox: /Schule/Beispielordner (Dropbox-ID: id:example)')
        expect(wrapper.get('[role="status"]').text()).toBe('Arbeitskontext kopiert.')
    } finally { wrapper.unmount() }
})

it('marks a missing assignment honestly and ignores another suggested folder', async () => {
    workDropboxStates.set('7:2:65', { folder: null, suggested_folder: { id: 'id:other', name: 'Andere Arbeit' }, has_import: true })
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    const wrapper = mountCopy({ course: { id: 2, title: 'INF', classes: ['5L2'] } })
    try {
        await flushPromises()
        await wrapper.get('button').trigger('click')
        await flushPromises()

        expect(writeText).toHaveBeenCalledExactlyOnceWith('Arbeit: A5 – Beispielarbeit (ID: 65)\nKurs: INF (5L2) (ID: 2)\nDatum: 15.10.2026\nOrdner: nicht verknüpft')
    } finally { wrapper.unmount() }
})

it('updates the copied context immediately after a saved module link and prefers it to an older import folder', async () => {
    workDropboxStates.set('7:2:65', { folder: null, suggested_folder: moduleLink.folder, candidate: moduleLink.folder })
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    const wrapper = mountCopy()
    try {
        await flushPromises()
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(writeText.mock.calls[0][0]).toContain('Ordner: nicht verknüpft')
        expect(writeText.mock.calls[0][0]).not.toContain('Modul-ID:')

        workDropboxStates.set('7:2:65', { folder: { id: 'id:old', name: 'Alter Importordner' }, module_link: moduleLink })
        await flushPromises()
        expect(writeText).toHaveBeenCalledTimes(1)
        expect(wrapper.find('[role="status"]').exists()).toBe(false)
        await wrapper.get('button').trigger('click')
        await flushPromises()

        expect(writeText.mock.calls[1][0]).toBe('Arbeit: A5 – Beispielarbeit (ID: 65)\nKurs: INF – 5L2 – 1 (ID: 2)\nDatum: 15.10.2026\nOrdner: verknüpft – Dropbox: /Materialien/Lerninhalte/E-Mails  Kopie (Dropbox-ID: id:module)\nModul-ID: a263b873-4f27-4167-90f0-7d94ea0f67fa')
    } finally { wrapper.unmount() }
})

it('restores the persisted module link from the authorized endpoint on a fresh mount without querying the legacy import path', async () => {
    const get = vi.fn().mockResolvedValue({ data: { folder: { id: 'id:old', name: 'Alter Importordner' }, module_link: moduleLink } })
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('axios', { get })
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    const wrapper = mountCopy()
    try {
        await flushPromises()
        await wrapper.get('button').trigger('click')
        await flushPromises()

        expect(get).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/dropbox', { timeout: 45000 })
        expect(writeText.mock.calls[0][0]).toContain('Ordner: verknüpft – Dropbox: /Materialien/Lerninhalte/E-Mails  Kopie')
        expect(writeText.mock.calls[0][0]).toContain(`Modul-ID: ${moduleLink.module_id}`)
        expect(writeText.mock.calls[0][0]).not.toContain('Alter Importordner')
    } finally { wrapper.unmount() }
})

it('does not overwrite a newly saved module link with a delayed legacy path response', async () => {
    let finish: (value: unknown) => void = () => {}
    const get = vi.fn().mockImplementation(() => new Promise(resolve => { finish = resolve }))
    const writeText = vi.fn().mockResolvedValue(undefined)
    workDropboxStates.set('7:2:65', { folder: { id: 'id:old', name: 'Alter Importordner' } })
    vi.stubGlobal('axios', { get })
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    const wrapper = mountCopy()
    try {
        await flushPromises()
        workDropboxStates.set('7:2:65', { folder: null, module_link: moduleLink })
        finish({ data: { folder: { id: 'id:old', name: 'Alter Importordner', path: '/Veraltet' } } })
        await flushPromises()
        await wrapper.get('button').trigger('click')
        await flushPromises()

        expect(workDropboxStates.get('7:2:65')?.module_link).toBe(moduleLink)
        expect(writeText.mock.calls[0][0]).toContain('Ordner: verknüpft – Dropbox: /Materialien/Lerninhalte/E-Mails  Kopie')
        expect(writeText.mock.calls[0][0]).not.toContain('/Veraltet')
    } finally { wrapper.unmount() }
})

it('also refreshes manually selectable text only after a saved link and keeps cancellation or failed linking unlinked', async () => {
    workDropboxStates.set('7:2:65', { folder: null })
    const writeText = vi.fn().mockRejectedValue(new Error('Denied'))
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    const wrapper = mountCopy()
    try {
        await flushPromises()
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.get('textarea').element.value).toContain('Ordner: nicht verknüpft')

        workDropboxStates.set('7:2:65', { folder: null, candidate: moduleLink.folder, conflict: true })
        await flushPromises()
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.get('textarea').element.value).toContain('Ordner: nicht verknüpft')

        workDropboxStates.set('7:2:65', { folder: null, module_link: moduleLink })
        await flushPromises()
        expect(wrapper.find('textarea').exists()).toBe(false)
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.get('textarea').element.value).toContain('Ordner: verknüpft – Dropbox: /Materialien/Lerninhalte/E-Mails  Kopie')
    } finally { wrapper.unmount() }
})

it.each([true, false])('reads an existing folder path when available (%s) and otherwise keeps the confirmed name and ID', async available => {
    workDropboxStates.set('7:2:65', { folder: { id: 'id:example', name: 'Beispielordner' } })
    const get = available
        ? vi.fn().mockResolvedValue({ data: { folder: { id: 'id:example', name: 'Beispielordner', path: '/Schule/Beispielordner' } } })
        : vi.fn().mockRejectedValue(new Error('Unavailable'))
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('axios', { get })
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    const wrapper = mountCopy()
    try {
        await flushPromises()
        await wrapper.get('button').trigger('click')
        await flushPromises()

        expect(get).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/dropbox', { timeout: 45000, params: { include_folder_path: 1 } })
        expect(writeText.mock.calls[0][0].split('\n')[3]).toBe(available
            ? 'Ordner: verknüpft – Dropbox: /Schule/Beispielordner (Dropbox-ID: id:example)'
            : 'Ordner: verknüpft – Beispielordner (Dropbox-ID: id:example)')
    } finally { wrapper.unmount() }
})

it('offers selected plain text when clipboard access fails and does not open the work editor', async () => {
    workDropboxStates.set('7:2:65', { folder: null })
    vi.stubGlobal('navigator', { clipboard: { writeText: vi.fn().mockRejectedValue(new Error('Denied')) }, userAgent: 'Test' })
    const wrapper = mountCopy()
    const cardClick = vi.fn()
    wrapper.element.parentElement!.addEventListener('click', cardClick)
    try {
        await flushPromises()
        await wrapper.get('button').trigger('click')
        await flushPromises()

        const textarea = wrapper.get('textarea').element as HTMLTextAreaElement
        expect(textarea.value).toContain('Arbeit: A5 – Beispielarbeit (ID: 65)')
        expect(textarea.selectionStart).toBe(0)
        expect(textarea.selectionEnd).toBe(textarea.value.length)
        expect(wrapper.get('[role="status"]').text()).toContain('Strg+C')
        expect(cardClick).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('blocks copying after a failed folder lookup and supports retry without inventing an unlinked state', async () => {
    const get = vi.fn().mockRejectedValueOnce(new Error('Unavailable')).mockResolvedValueOnce({ data: { folder: null } })
    vi.stubGlobal('axios', { get })
    const wrapper = mountCopy()
    try {
        await flushPromises()
        expect(wrapper.get('button').attributes('disabled')).toBeDefined()
        expect(wrapper.get('[role="alert"]').text()).toContain('konnte nicht geprüft werden')
        await wrapper.findAll('button')[1].trigger('click')
        await flushPromises()
        expect(wrapper.get('button').attributes('disabled')).toBeUndefined()
        expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('refreshes folder metadata on retry even if another component has cached an incomplete result', async () => {
    const get = vi.fn().mockRejectedValueOnce(new Error('Unavailable')).mockResolvedValueOnce({ data: { folder: null, module_link: moduleLink } })
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('axios', { get })
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    const wrapper = mountCopy()
    try {
        await flushPromises()
        workDropboxStates.set('7:2:65', { exercise: { status: 'unknown' } })
        await flushPromises()
        await wrapper.findAll('button').find(button => button.text() === 'Erneut prüfen')!.trigger('click')
        await flushPromises()
        await wrapper.get('button').trigger('click')
        await flushPromises()

        expect(get).toHaveBeenCalledTimes(2)
        expect(wrapper.find('[role="alert"]').exists()).toBe(false)
        expect(writeText.mock.calls[0][0]).toContain('Modul-ID: a263b873-4f27-4167-90f0-7d94ea0f67fa')
    } finally { wrapper.unmount() }
})

it.each([
    [{ response: { status: 401 } }, 'Sitzung ist abgelaufen'],
    [{ response: { status: 403 } }, 'keine Berechtigung'],
    [{ response: { status: 429 } }, 'zu viele Anfragen'],
    [{ code: 'ECONNABORTED' }, 'zu lange gedauert'],
    [{ response: { status: 422, data: { errors: { dropbox: ['Bitte Dropbox erneut verbinden.'] } } } }, 'Bitte Dropbox erneut verbinden.'],
])('explains a failed folder lookup (%j)', async (error, message) => {
    vi.stubGlobal('axios', { get: vi.fn().mockRejectedValue(error) })
    const wrapper = mountCopy()
    try {
        await flushPromises()
        expect(wrapper.get('[role="alert"]').text()).toContain(message)
        expect(wrapper.get('button').attributes('disabled')).toBeDefined()
    } finally { wrapper.unmount() }
})

it('does not copy a work from another course or reuse a context after switching courses', async () => {
    workDropboxStates.set('7:2:65', { folder: null })
    const wrapper = mountCopy()
    try {
        await flushPromises()
        expect(wrapper.get('button').attributes('disabled')).toBeUndefined()
        await wrapper.setProps({ course: { ...course, id: 3 } })
        await flushPromises()
        expect(wrapper.get('button').attributes('disabled')).toBeDefined()
    } finally { wrapper.unmount() }
})
