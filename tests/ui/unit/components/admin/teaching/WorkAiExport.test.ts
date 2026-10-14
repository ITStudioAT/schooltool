import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components'
import WorkAiExport from '@/pages/admin/teaching/overview/components/WorkAiExport.vue'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'

const work = { id: 65, teaching_course_id: 2, title: 'Ungespeicherter Entwurf' }
const text = 'Auftrag für Datenschutz\nÄnne Ćelebić · Béa Beta\n' + 'Vollständige Arbeitsdaten\n'.repeat(100)
const data = { work_id: 65, course_id: 2, roster_complete: true, participants_count: 2, active_participants_count: 2,
    context: { work: { id: 65, title: 'Gespeicherte Datenschutzarbeit' } }, warnings: ['Lokalen Ordner anhand der gespeicherten Dropbox-ID prüfen.'], text }

function mountExport() {
    return mount(WorkAiExport, { props: { work }, attachTo: document.body, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminCourseStore: { selected_course: { id: 2 } } } }), createVuetify({ components: { VBtn } })],
        components: { 'v-btn': VBtn }, stubs: { 'v-btn': false, VBtn: false },
    } })
}

function button(wrapper: any, title: string) {
    return wrapper.findAll('button').find((item: any) => item.text() === title)!
}

afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals(); document.body.innerHTML = '' })

describe('Work AI export', () => {
    it('loads the selected saved work freshly and copies the entire rendered export only on click', async () => {
        const get = vi.fn().mockResolvedValue({ data: { data } })
        const writeText = vi.fn().mockResolvedValue(undefined)
        vi.stubGlobal('axios', { get })
        vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
        const wrapper = mountExport()
        try {
            expect(get).not.toHaveBeenCalled()
            await button(wrapper, 'KI-Export').trigger('click')
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
            await button(wrapper, 'KI-Export').trigger('click')
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
            await button(wrapper, 'KI-Export').trigger('click')
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
            await button(wrapper, 'KI-Export').trigger('click')
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
            await button(wrapper, 'KI-Export').trigger('click')
            await flushPromises()
            expect(wrapper.find('textarea').exists()).toBe(false)
            expect(button(wrapper, 'Auftrag kopieren').attributes('disabled')).toBeDefined()
            expect(wrapper.text()).toContain('nicht vollständig bestätigt')
        } finally { wrapper.unmount() }
    })
})
