import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VBtn, VFileInput } from 'vuetify/components'
import WorkEvaluationImport from '@/pages/admin/teaching/overview/components/WorkEvaluationImport.vue'
import WorkImportActions from '@/pages/admin/teaching/overview/components/WorkImportActions.vue'
import * as directories from '@/helpers/workImportDirectory'
import { clearWorkDropboxStates, workDropboxStates } from '@/helpers/workDropbox'

const work = { id: 65, teaching_course_id: 2, title: 'E-Mails' }
const preview = { target_work: { title: 'E-Mails', course_title: 'Kurs' }, exercise: {}, rows: [], maximum_minor: 500, can_import: true, hash: 'a'.repeat(64) }

function mountImport(component = WorkEvaluationImport, props = {}) {
    return mount(component, { props, global: { plugins: [createTestingPinia({ createSpy: vi.fn, initialState: {
        AdminCourseStore: { selected_course: { id: 2 } }, AdminAdminStore: { config: { user: { id: 7 } } },
    } }), createVuetify({ components: { VBtn, VFileInput } })],
        components: { 'v-btn': VBtn, 'v-file-input': VFileInput }, stubs: { 'v-btn': false, VBtn: false, 'v-file-input': false, VFileInput: false },
    } })
}

function directory(name = 'Test') {
    const handle = { name, queryPermission: vi.fn().mockResolvedValue('granted'), requestPermission: vi.fn(),
        getDirectoryHandle: vi.fn().mockRejectedValue(new DOMException('Missing', 'NotFoundError')),
        getFileHandle: vi.fn().mockImplementation(async () => ({ getFile: async () => new File([JSON.stringify({ records: [] })], 'Schooltool-Bewertungen.json') })),
    }
    return handle
}

afterEach(() => { directories.workImportDirectories.clear(); clearWorkDropboxStates(); vi.restoreAllMocks(); vi.unstubAllGlobals() })

describe('Quick-Import reuses only a proven per-work directory and always previews', () => {
    it('absorbs disabled button clicks inside an actionable work card and routes enabled Quick-Import to its preview', async () => {
        vi.stubGlobal('showDirectoryPicker', vi.fn())
        vi.stubGlobal('indexedDB', {})
        workDropboxStates.set('7:2:65', { can_quick_import: false })
        const wrapper = mountImport(WorkImportActions, { work })
        const cardClick = vi.fn()
        const card = document.createElement('div')
        card.addEventListener('click', cardClick)
        card.appendChild(wrapper.element.parentElement || wrapper.element)
        try {
            await flushPromises()
            const quick = wrapper.findAll('button')[1]
            // Disabled Vuetify buttons pass pointer hits to their enclosing element.
            quick.element.parentElement!.dispatchEvent(new MouseEvent('click', { bubbles: true }))
            expect(cardClick).not.toHaveBeenCalled()
            expect(wrapper.emitted('open')).toBeUndefined()
            workDropboxStates.set('7:2:65', { can_quick_import: true })
            await flushPromises()
            await quick.trigger('click')
            expect(wrapper.emitted('open')).toEqual([[work, true]])
            expect(cardClick).not.toHaveBeenCalled()
        } finally { wrapper.unmount() }
    })

    it('normal import always asks for a folder and never replaces saved access after a blocked preview or failed apply', async () => {
        const previous = directory('Previous')
        const selected = directory('New')
        const picker = vi.fn().mockResolvedValue(selected)
        vi.stubGlobal('showDirectoryPicker', picker)
        vi.stubGlobal('indexedDB', {})
        const save = vi.spyOn(directories, 'saveWorkImportDirectory').mockResolvedValue()
        const post = vi.fn().mockResolvedValueOnce({ data: { preview: { ...preview, can_import: false } } })
            .mockRejectedValueOnce(new Error('Rejected'))
        vi.stubGlobal('axios', { post })
        directories.workImportDirectories.set('7:2:65', previous)
        const wrapper = mountImport()
        try {
            const vm = wrapper.vm as any
            vm.openImport(work)
            expect(picker).not.toHaveBeenCalled()
            await vm.chooseDirectory()
            expect(picker).toHaveBeenCalledWith({ mode: 'read', id: 'schooltool-work-import' })
            expect(save).not.toHaveBeenCalled()
            await vm.applyJson()
            expect(post).toHaveBeenCalledTimes(1)
            vm.json_preview = preview
            await vm.applyJson()
            expect(save).not.toHaveBeenCalled()
            expect(directories.workImportDirectories.get('7:2:65')).toBe(previous)
        } finally { wrapper.unmount() }
    })

    it('honors a renewed read permission request and disables reuse in unsupported browsers', async () => {
        const handle = directory()
        handle.queryPermission.mockResolvedValue('prompt')
        handle.requestPermission.mockResolvedValue('granted')
        vi.stubGlobal('showDirectoryPicker', vi.fn())
        vi.stubGlobal('indexedDB', {})
        vi.stubGlobal('axios', { post: vi.fn().mockResolvedValue({ data: { preview } }) })
        directories.workImportDirectories.set('7:2:65', handle)
        const wrapper = mountImport()
        try {
            ;(wrapper.vm as any).openImport(work)
            await (wrapper.vm as any).reuseDirectory()
            expect(handle.requestPermission).toHaveBeenCalledWith({ mode: 'read' })
            expect((wrapper.vm as any).json_preview.can_import).toBe(true)
            vi.stubGlobal('showDirectoryPicker', undefined)
            const actions = mountImport(WorkImportActions, { work })
            try {
                expect(actions.findAll('button')[1].attributes('disabled')).toBeDefined()
                expect(actions.findAll('button')[1].attributes('title')).toContain('Dropbox')
            } finally { actions.unmount() }
        } finally { wrapper.unmount() }
    })

    it('stores the browser handle durably and restores it after the in-memory cache is cleared', async () => {
        const saved = new Map()
        const database = { close: vi.fn(), transaction: vi.fn().mockImplementation(() => {
            const transaction: any = { objectStore: () => ({
                put: (handle: unknown, key: string) => { saved.set(key, handle); queueMicrotask(() => transaction.oncomplete()) },
                get: (key: string) => {
                    const request: any = { result: saved.get(key) }
                    queueMicrotask(() => request.onsuccess())
                    return request
                },
            }) }
            return transaction
        }) }
        vi.stubGlobal('showDirectoryPicker', vi.fn())
        vi.stubGlobal('indexedDB', { open: () => {
            const request: any = { result: database }
            queueMicrotask(() => request.onsuccess())
            return request
        } })
        const handle = directory()
        await directories.saveWorkImportDirectory('7:2:65', handle)
        directories.workImportDirectories.clear()
        expect(await directories.loadWorkImportDirectory('7:2:65')).toBe(handle)
        expect(await directories.loadWorkImportDirectory('7:2:66')).toBeNull()
        expect(database.close).toHaveBeenCalledTimes(3)
    })

    it('reads fresh files on each click without opening a picker or applying, then saves only after explicit apply', async () => {
        const handle = directory()
        const picker = vi.fn()
        vi.stubGlobal('showDirectoryPicker', picker)
        vi.stubGlobal('indexedDB', {})
        const save = vi.spyOn(directories, 'saveWorkImportDirectory').mockResolvedValue()
        const post = vi.fn().mockResolvedValue({ data: { preview, data: work } })
        vi.stubGlobal('axios', { post })
        directories.workImportDirectories.set('7:2:65', handle)
        const wrapper = mountImport()
        try {
            const vm = wrapper.vm as any
            vm.openImport(work)
            await vm.reuseDirectory()
            expect(post).toHaveBeenCalledTimes(1)
            expect(post.mock.calls[0][1].has('apply')).toBe(false)
            expect(picker).not.toHaveBeenCalled()
            expect(save).not.toHaveBeenCalled()
            expect(vm.json_preview.can_import).toBe(true)
            expect(wrapper.emitted('imported')).toBeUndefined()
            vm.openImport(work)
            await vm.reuseDirectory()
            expect(handle.getFileHandle).toHaveBeenCalledTimes(2)
            expect(post.mock.calls[0][1].get('package')).not.toBe(post.mock.calls[1][1].get('package'))
            await vm.applyJson()
            expect(post.mock.calls[2][1].get('apply')).toBe('1')
            expect(post.mock.calls[2][1].get('hash')).toBe(preview.hash)
            expect(save).toHaveBeenCalledWith('7:2:65', handle)
        } finally { wrapper.unmount() }
    })

    it('never reuses a different work or user and shows normal selection when no folder is saved', async () => {
        vi.stubGlobal('showDirectoryPicker', vi.fn())
        vi.stubGlobal('indexedDB', {})
        const post = vi.fn()
        vi.stubGlobal('axios', { post })
        directories.workImportDirectories.set('7:2:66', directory('Other work'))
        directories.workImportDirectories.set('8:2:65', directory('Other user'))
        directories.workImportDirectories.set('7:2:65', null)
        const wrapper = mountImport()
        const actions = mountImport(WorkImportActions, { work })
        try {
            ;(wrapper.vm as any).openImport(work)
            await (wrapper.vm as any).reuseDirectory()
            await flushPromises()
            expect(wrapper.text()).toContain('Für diese Arbeit ist noch kein Ordner gespeichert')
            expect(post).not.toHaveBeenCalled()
            const quick = actions.findAll('button').find(button => button.text().includes('Quick-Import'))!
            expect(quick.attributes('disabled')).toBeDefined()
            expect(actions.find('[role="status"]').text()).toContain('Dropbox')
            const normal = actions.findAll('button').find(button => button.text().includes('Importieren'))!
            expect(normal.attributes('disabled')).toBeUndefined()
        } finally { wrapper.unmount(); actions.unmount() }
    })

    it.each(['denied', 'missing', 'invalid package'])('keeps the picker available after %s and never applies', async scenario => {
        const handle = directory()
        if (scenario === 'denied') { handle.queryPermission.mockResolvedValue('prompt'); handle.requestPermission.mockResolvedValue('denied') }
        if (scenario === 'missing') handle.getFileHandle.mockRejectedValue(new DOMException('Gone', 'NotFoundError'))
        if (scenario === 'invalid package') handle.getFileHandle.mockResolvedValue({ getFile: async () => new File(['invalid'], 'Schooltool-Bewertungen.json') })
        vi.stubGlobal('showDirectoryPicker', vi.fn())
        vi.stubGlobal('indexedDB', {})
        const post = vi.fn()
        vi.stubGlobal('axios', { post })
        directories.workImportDirectories.set('7:2:65', handle)
        const wrapper = mountImport()
        try {
            ;(wrapper.vm as any).openImport(work)
            await (wrapper.vm as any).reuseDirectory()
            expect((wrapper.vm as any).import_error).not.toBe('')
            expect((wrapper.vm as any).import_busy).toBe(false)
            expect(wrapper.text()).toContain('Leistungsfeststellungs- oder Übungsordner auswählen')
            expect(post).not.toHaveBeenCalled()
        } finally { wrapper.unmount() }
    })
})
