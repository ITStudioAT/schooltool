import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VBtn, VFileInput } from 'vuetify/components'
import WorkEvaluationImport from '@/pages/admin/teaching/overview/components/WorkEvaluationImport.vue'
import WorkImportActions from '@/pages/admin/teaching/overview/components/WorkImportActions.vue'
import WorkDropboxSource from '@/pages/admin/teaching/overview/components/WorkDropboxSource.vue'
import { clearWorkDropboxStates, loadWorkDropbox, workDropboxStates } from '@/helpers/workDropbox'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'

const work = { id: 65, teaching_course_id: 2, title: 'E-Mails' }
const connected = { configured: true, installed: true, connected: true, can_quick_import: true, folder: { id: 'id:workfolder', name: 'Arbeit' }, message: 'Dropbox-Vorschau laden.' }
const preview = { target_work: { title: 'E-Mails', course_title: 'Kurs' }, exercise: {}, rows: [], maximum_minor: 500, can_import: true, hash: 'a'.repeat(64) }

function mountComponent(component: any, props = {}) {
    return mount(component, { props, global: { plugins: [createTestingPinia({ createSpy: vi.fn, initialState: {
        AdminCourseStore: { selected_course: { id: 2 } }, AdminAdminStore: { config: { user: { id: 7 } } },
    } }), createVuetify({ components: { VBtn, VFileInput } })],
    components: { 'v-btn': VBtn, 'v-file-input': VFileInput }, stubs: { 'v-btn': false, VBtn: false, 'v-file-input': false, VFileInput: false } } })
}

afterEach(() => { clearWorkDropboxStates(); vi.restoreAllMocks(); vi.unstubAllGlobals() })

describe('Dropbox Quick-Import', () => {
    it('enables only an owned mapped cloud folder without browser directory APIs', async () => {
        vi.stubGlobal('showDirectoryPicker', undefined)
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { ...connected, can_quick_import: false, folder: null } }) })
        workDropboxStates.set('8:2:65', connected)
        const wrapper = mountComponent(WorkImportActions, { work })
        try {
            await flushPromises()
            const quick = wrapper.findAll('button')[1]
            expect(quick.attributes('disabled')).toBeDefined()
            expect(wrapper.findAll('button')[0].attributes('disabled')).toBeUndefined()
            workDropboxStates.set('7:2:65', connected)
            await flushPromises()
            expect(quick.attributes('disabled')).toBeUndefined()
            await quick.trigger('click')
            expect(wrapper.emitted('open')).toEqual([[work, true]])
        } finally { wrapper.unmount() }
    })

    it('reloads cloud previews and applies only with the reviewed hash after explicit confirmation', async () => {
        const post = vi.fn().mockResolvedValue({ data: { preview, data: work } })
        const get = vi.fn().mockResolvedValue({ data: connected })
        vi.stubGlobal('axios', { get, post })
        vi.stubGlobal('showDirectoryPicker', undefined)
        const wrapper = mountComponent(WorkEvaluationImport)
        try {
            const vm = wrapper.vm as any
            await vm.openImport(work, true)
            expect(post.mock.calls[0][1]).toEqual({})
            expect(vm.json_preview.hash).toBe(preview.hash)
            expect(wrapper.emitted('imported')).toBeUndefined()
            await vm.previewDropbox(false)
            expect(post.mock.calls[1][1]).toEqual({})
            await vm.applyJson()
            expect(post.mock.calls[2][1]).toEqual({ apply: true, hash: preview.hash })
            expect(wrapper.emitted('imported')).toEqual([[work]])
            expect(get.mock.calls.length).toBeGreaterThanOrEqual(3)
        } finally { wrapper.unmount() }
    })

    it('discards a delayed cloud response after changing courses and keeps normal upload available on errors', async () => {
        let resolve: (value: unknown) => void = () => {}
        const post = vi.fn().mockImplementation(() => new Promise(done => { resolve = done }))
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connected }), post })
        const wrapper = mountComponent(WorkEvaluationImport)
        try {
            const vm = wrapper.vm as any
            const pending = vm.openImport(work, true)
            await flushPromises()
            useCourseStore().selected_course = { id: 3 } as any
            await flushPromises()
            resolve({ data: { preview } })
            await pending
            expect(vm.json_preview).toBeNull()
            expect(vm.import_open).toBe(false)
            useCourseStore().selected_course = { id: 2 } as any
            await flushPromises()
            post.mockRejectedValue({ response: { data: { errors: { dropbox: ['Ordner fehlt.'] } } } })
            await vm.openImport(work, true)
            expect(vm.import_error).toBe('Ordner fehlt.')
            expect(vm.import_busy).toBe(false)
            expect(wrapper.text()).toContain('Leistungsfeststellungs- oder Übungsordner auswählen')
            expect(wrapper.emitted('imported')).toBeUndefined()
        } finally { wrapper.unmount() }
    })

    it('browses and assigns a stable Dropbox folder without applying an assessment', async () => {
        const get = vi.fn().mockImplementation(url => Promise.resolve({ data: url.includes('/folders')
            ? { folders: [{ id: 'id:chosen', name: 'Gewählt' }], cursor: null } : { ...connected, folder: null, can_quick_import: false } }))
        const post = vi.fn().mockResolvedValue({ data: { ...connected, folder: { id: 'id:chosen', name: 'Gewählt' } } })
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            await wrapper.findAll('button').find(button => button.text() === 'Dropbox-Ordner zuordnen')!.trigger('click')
            await flushPromises()
            await wrapper.findAll('button').find(button => button.text() === 'Gewählt')!.trigger('click')
            await flushPromises()
            await wrapper.findAll('button').find(button => button.text() === 'Diesen Ordner zuordnen')!.trigger('click')
            await flushPromises()
            expect(post).toHaveBeenCalledTimes(1)
            expect(post.mock.calls[0][1]).toEqual({ folder_id: 'id:chosen' })
            expect(wrapper.emitted('changed')).toHaveLength(1)
            expect(wrapper.emitted('preview')).toBeUndefined()
        } finally { wrapper.unmount() }
    })

    it('cannot restore a stale connection cache after disconnecting', async () => {
        let resolve: (value: unknown) => void = () => {}
        vi.stubGlobal('axios', { get: vi.fn().mockImplementation(() => new Promise(done => { resolve = done })) })
        const pending = loadWorkDropbox('7:2:65', 65)
        clearWorkDropboxStates()
        resolve({ data: connected })
        await pending
        expect(workDropboxStates.has('7:2:65')).toBe(false)
    })
})
