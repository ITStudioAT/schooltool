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
    workDropboxStates.set('7:2:65', { folder: { id: 'id:example', name: 'Beispielordner' } })
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    const wrapper = mountCopy()
    try {
        await flushPromises()
        expect(writeText).not.toHaveBeenCalled()
        await wrapper.get('button').trigger('click')
        await flushPromises()

        expect(writeText).toHaveBeenCalledExactlyOnceWith('Arbeit: A5 – Beispielarbeit (ID: 65)\nKurs: INF – 5L2 – 1 (ID: 2)\nDatum: 15.10.2026\nOrdner: Beispielordner (Dropbox-ID: id:example; kein Weblink gespeichert)')
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
