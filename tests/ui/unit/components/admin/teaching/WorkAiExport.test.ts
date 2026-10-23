import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components'
import WorkAiExport from '@/pages/admin/teaching/overview/components/WorkAiExport.vue'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { clearWorkDropboxStates, workDropboxStates } from '@/helpers/workDropbox'

const work = { id: 65, teaching_course_id: 2, title: 'Ungespeicherter Entwurf' }
const text = 'Auftrag für Datenschutz\nÄnne Ćelebić · Béa Beta\n' + 'Vollständige Arbeitsdaten\n'.repeat(100)
const data = { work_id: 65, course_id: 2, roster_complete: true, participants_count: 2, active_participants_count: 2,
    context: { work: { id: 65, title: 'Gespeicherte Datenschutzarbeit' } }, warnings: ['Lokalen Ordner anhand der gespeicherten Dropbox-ID prüfen.'], text }

function mountExport() {
    return mount(WorkAiExport, { props: { work }, attachTo: document.body, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminCourseStore: { selected_course: { id: 2 } }, AdminAdminStore: { config: { user: { id: 7 } } } } }), createVuetify({ components: { VBtn } })],
        components: { 'v-btn': VBtn }, stubs: { 'v-btn': false, VBtn: false },
    } })
}

function button(wrapper: any, title: string) {
    return wrapper.findAll('button').find((item: any) => item.text() === title)!
}

beforeEach(() => { clearWorkDropboxStates(); workDropboxStates.set('7:2:65', { module_link: { folder: { id: 'id:module' } }, folder: null }) })
afterEach(() => { clearWorkDropboxStates(); vi.restoreAllMocks(); vi.unstubAllGlobals(); document.body.innerHTML = '' })

describe('Work AI export', () => {
    it('shows creation only after module linking and closes its stale export after unlinking', async () => {
        const get = vi.fn().mockResolvedValue({ data: { data } })
        vi.stubGlobal('axios', { get })
        workDropboxStates.set('7:2:65', { folder: null, module_link: null, suggested_folder: { id: 'id:suggestion' } })
        const wrapper = mountExport()
        try {
            await flushPromises()
            expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
            workDropboxStates.set('7:2:65', { folder: null, module_link: { folder: { id: 'id:module' } } })
            await flushPromises()
            await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
            await flushPromises()
            expect(wrapper.find('textarea').exists()).toBe(true)
            workDropboxStates.set('7:2:65', { folder: null, module_link: null })
            await flushPromises()
            expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
            expect(wrapper.find('textarea').exists()).toBe(false)
            expect(get).toHaveBeenCalledTimes(1)
        } finally { wrapper.unmount() }
    })

    it.each([true, false])('uses only a confirmed folder ID to hide the creation action, assigned %s', async assigned => {
        workDropboxStates.set('7:2:65', { module_link: { folder: { id: 'id:module' } }, folder: assigned ? { id: 'id:confirmed', name: 'Arbeit' } : { name: 'Noch keine Zuordnung' },
            suggested_folder: { id: 'id:proposal', name: 'Vorschlag' } })
        const get = vi.fn()
        vi.stubGlobal('axios', { get })
        const wrapper = mountExport()
        try {
            await flushPromises()
            expect(Boolean(button(wrapper, 'Leistungsfeststellung erstellen'))).toBe(!assigned)
            expect(get).not.toHaveBeenCalled()
        } finally { wrapper.unmount() }
    })

    it.each(['unassigned', 'linked', 'assigned', 'missing', 'incomplete', 'failed'])('waits for a confirmed status without flashing the action: %s', async state => {
        clearWorkDropboxStates()
        let resolve: (value: any) => void = () => {}
        let reject: (value: any) => void = () => {}
        const get = vi.fn().mockImplementation(() => new Promise((done, fail) => { resolve = done; reject = fail }))
        vi.stubGlobal('axios', { get })
        const wrapper = mountExport()
        try {
            expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
            if (state === 'failed') reject(new Error('Nicht erreichbar'))
            else resolve({ data: state === 'missing' ? null : (state === 'incomplete' ? {} : { module_link: state === 'linked' || state === 'assigned' ? { folder: { id: 'id:module' } } : null, folder: state === 'assigned' ? { id: 'id:confirmed' } : null }) })
            await flushPromises()
            expect(Boolean(button(wrapper, 'Leistungsfeststellung erstellen'))).toBe(state === 'linked')
            expect(get).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/dropbox', { timeout: 45000 })
        } finally { wrapper.unmount() }
    })

    it('hides immediately after assigning this work and keeps assignments scoped to the user and work', async () => {
        const get = vi.fn()
        vi.stubGlobal('axios', { get })
        workDropboxStates.set('7:2:66', { folder: { id: 'id:other-work' } })
        workDropboxStates.set('8:2:65', { folder: { id: 'id:other-user' } })
        const wrapper = mountExport()
        try {
            expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeDefined()
            workDropboxStates.set('7:2:65', { folder: { id: 'id:just-assigned' } })
            await flushPromises()
            expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
            workDropboxStates.set('7:2:65', { module_link: { folder: { id: 'id:module' } }, folder: null })
            await flushPromises()
            expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeDefined()
            useAdminStore().config.user.id = 8
            await flushPromises()
            expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
            useAdminStore().config.user.id = 7
            await flushPromises()
            expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeDefined()
            await wrapper.setProps({ work: { ...work, id: 66 } })
            await flushPromises()
            expect(button(wrapper, 'Leistungsfeststellung erstellen')).toBeUndefined()
            expect(get).not.toHaveBeenCalled()
        } finally { wrapper.unmount() }
    })

    it('loads the selected saved work freshly and copies the entire rendered export only on click', async () => {
        const get = vi.fn().mockResolvedValue({ data: { data } })
        const writeText = vi.fn().mockResolvedValue(undefined)
        vi.stubGlobal('axios', { get })
        vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
        const wrapper = mountExport()
        try {
            expect(get).not.toHaveBeenCalled()
            expect(button(wrapper, 'Leistungsfeststellung erstellen').attributes()).toMatchObject({ title: 'Leistungsfeststellung erstellen', 'aria-label': 'Leistungsfeststellung erstellen' })
            await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
            await flushPromises()
            expect(get).toHaveBeenCalledWith('/api/admin/teaching/course_works/65/ai-export')
            expect(wrapper.text()).toContain('Gespeicherte Datenschutzarbeit')
            expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe(text)
            expect(writeText).not.toHaveBeenCalled()
            await button(wrapper, 'Auftrag kopieren').trigger('click')
            await flushPromises()
            expect(writeText).toHaveBeenCalledExactlyOnceWith(text)
            expect(wrapper.text()).toContain('Der vollständige Auftrag wurde kopiert')
            await button(wrapper, 'Erneut laden').trigger('click')
            await flushPromises()
            expect(get).toHaveBeenCalledTimes(3)
        } finally { wrapper.unmount() }
    })

    it('refreshes changed course membership before copying instead of copying a previously displayed snapshot', async () => {
        const updated = { ...data, participants_count: 3, active_participants_count: 3, text: text + 'Neu aufgenommen: Person 3\n' }
        const get = vi.fn().mockResolvedValueOnce({ data: { data } }).mockResolvedValueOnce({ data: { data: updated } })
        const writeText = vi.fn().mockResolvedValue(undefined)
        vi.stubGlobal('axios', { get })
        vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
        const wrapper = mountExport()
        try {
            await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
            await flushPromises()
            await button(wrapper, 'Auftrag kopieren').trigger('click')
            await flushPromises()
            expect(writeText).toHaveBeenCalledExactlyOnceWith(updated.text)
            expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe(updated.text)
            expect(wrapper.text()).toContain('3 Personen im Kurs')
        } finally { wrapper.unmount() }
    })

    it.each(['unavailable', 'rejected'])('selects the complete text for manual copying when clipboard is %s', async mode => {
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { data } }) })
        vi.stubGlobal('navigator', { userAgent: 'Test', clipboard: mode === 'rejected' ? { writeText: vi.fn().mockRejectedValue(new Error('Denied')) } : undefined })
        const wrapper = mountExport()
        try {
            await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
            await flushPromises()
            await button(wrapper, 'Auftrag kopieren').trigger('click')
            await flushPromises()
            const textarea = wrapper.get('textarea').element as HTMLTextAreaElement
            expect(textarea.selectionStart).toBe(0)
            expect(textarea.selectionEnd).toBe(text.length)
            expect(wrapper.text()).toContain('Strg+C')
            expect(wrapper.text()).not.toContain('wurde kopiert')
        } finally { wrapper.unmount() }
    })

    it('discards delayed data after a course switch and prevents copying the previous course', async () => {
        let resolve: (value: any) => void = () => {}
        vi.stubGlobal('axios', { get: vi.fn().mockImplementation(() => new Promise(done => { resolve = done })) })
        const wrapper = mountExport()
        try {
            await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
            useCourseStore().selected_course = { id: 3 } as any
            await flushPromises()
            resolve({ data: { data } })
            await flushPromises()
            expect(wrapper.find('textarea').exists()).toBe(false)
            expect(button(wrapper, 'Auftrag kopieren').attributes('disabled')).toBeDefined()
        } finally { wrapper.unmount() }
    })

    it.each([{ roster_complete: false }, { participants_count: 0 }, { course_id: 3 }, { work_id: 66 }])('blocks an incomplete or unrelated response %j', async change => {
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { data: { ...data, ...change } } }) })
        const wrapper = mountExport()
        try {
            await button(wrapper, 'Leistungsfeststellung erstellen').trigger('click')
            await flushPromises()
            expect(wrapper.find('textarea').exists()).toBe(false)
            expect(button(wrapper, 'Auftrag kopieren').attributes('disabled')).toBeDefined()
            expect(wrapper.text()).toContain('nicht vollständig bestätigt')
        } finally { wrapper.unmount() }
    })
})
