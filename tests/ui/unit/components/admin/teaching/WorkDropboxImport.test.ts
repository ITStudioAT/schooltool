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
    it.each([true, false])('starts a new folder selection at the saved suggestion with an existing binding %s', async assigned => {
        const suggestion = { id: 'id:last-folder', name: 'Zuletzt gewählt' }
        const get = vi.fn().mockResolvedValueOnce({ data: { ...connected, folder: assigned ? suggestion : null,
            suggested_folder: suggestion, can_quick_import: assigned } })
            .mockResolvedValueOnce({ data: { folders: [], cursor: null, parent: { id: 'id:parent', name: 'Materialien' } } })
            .mockResolvedValueOnce({ data: { folders: [], cursor: null, parent: null } })
        const post = vi.fn()
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            const change = wrapper.findAll('button').find(button => button.text() === (assigned ? 'Dropbox-Ordner ändern' : 'Dropbox-Ordner zuordnen'))!
            await change.trigger('click')
            await flushPromises()
            expect(get.mock.calls[1][0]).toContain('folder_id=id%3Alast-folder')
            expect(wrapper.text()).toContain('Dropbox / Zuletzt gewählt')
            expect(wrapper.text()).toContain('Diesen Ordner zuordnen')
            expect(post).not.toHaveBeenCalled()
            await wrapper.findAll('button').find(button => button.text() === 'Übergeordneter Ordner')!.trigger('click')
            await flushPromises()
            expect(get.mock.calls[2][0]).toContain('folder_id=id%3Aparent')
            expect(wrapper.text()).toContain('Dropbox / Materialien')
        } finally { wrapper.unmount() }
    })
    it('keeps compact person summaries readable and opens blocked identities while apply remains disabled', async () => {
        const row = { participant_id: 'p1', student_id: 12, identity: { first_name: 'Ada', last_name: 'Alpha', class_name: '1A', group_name: 'INF 2' },
            status: 'Aktualisierung', previous: { points: 2 }, total_minor: 200, change: 'updated', criteria: [], adjustments: [],
            submission_state: 'received', evaluation_state: 'complete', will_replace: true }
        const post = vi.fn().mockResolvedValue({ data: { preview: { ...preview, can_import: false,
            rows: [row, { ...row, participant_id: 'p2', student_id: null, will_replace: false, identity: { ...row.identity, first_name: 'Bea' }, status: 'Person nicht eindeutig – Import gesperrt' }] } } })
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connected }), post })
        const wrapper = mountComponent(WorkEvaluationImport)
        try {
            await (wrapper.vm as any).openImport(work, true)
            await flushPromises()
            const people = wrapper.findAll('.work-import-person')
            expect(people).toHaveLength(2)
            expect(people[0].get('summary').text()).toContain('Ada Alpha / 1A')
            expect(people[0].get('summary').text()).toContain('Aktualisierung')
            expect(people[0].get('summary').text()).toContain('Punkte 2 → 2,00')
            expect((people[0].element as HTMLDetailsElement).open).toBe(false)
            expect(people[1].get('summary').text()).toContain('Import gesperrt')
            expect(people[1].get('summary').text()).toContain('Punkte 2 bleiben erhalten')
            expect((people[1].element as HTMLDetailsElement).open).toBe(true)
            expect(wrapper.findAll('button').find(button => button.text() === 'Angezeigte Änderungen übernehmen')?.attributes('disabled')).toBeDefined()
            expect(wrapper.text()).toContain('Ungespeicherte Änderungen werden verworfen')
            expect(post).toHaveBeenCalledTimes(1)
            expect(wrapper.emitted('imported')).toBeUndefined()
        } finally { wrapper.unmount() }
    })
    it('previews a provision-only local folder and displays the actual release without implying a send', async () => {
        const post = vi.fn().mockResolvedValue({ data: { preview: { ...preview, exercise: null, dispatches: [],
            provisions: [{ source: 'Schooltool-Bereitstellung.json', exercise: { title: 'Geschichte' }, roster_matches: true,
                rows: [{ participant_id: 'p1', identity: { first_name: 'Ada', last_name: 'Alpha' }, status: 'Zugeordnet',
                    events: [{ event_id: 'e1', method: 'download_release', available_at: '2026-10-04T08:00:00Z' }] }] }] } } })
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connected }), post })
        const wrapper = mountComponent(WorkEvaluationImport)
        try {
            const vm = wrapper.vm as any
            await vm.openImport(work)
            const file = new File(['{}'], 'Schooltool-Bereitstellung.json')
            Object.defineProperty(file, 'webkitRelativePath', { value: 'Arbeit/Schooltool-Bereitstellung.json' })
            await vm.selectJsonFolder({ target: { files: [file], value: '' } })
            await flushPromises()
            expect(post).toHaveBeenCalledTimes(1)
            expect(post.mock.calls[0][1].has('package')).toBe(false)
            expect(post.mock.calls[0][1].get('documents')).toContain('Schooltool-Bereitstellung.json')
            expect(wrapper.text()).toContain('Bereitstellung: Geschichte')
            expect(wrapper.text()).toContain('Zum Download bereitgestellt: 04.10.2026')
            expect(wrapper.text()).toContain('kein individueller Download oder Empfang bestätigt')
            expect(wrapper.text()).not.toContain('Personen mit bestätigtem Live-Versand')
            expect(wrapper.emitted('imported')).toBeUndefined()
        } finally { wrapper.unmount() }
    })
    it('sorts Dropbox folders across loaded pages with German names and numbers', async () => {
        const get = vi.fn().mockResolvedValueOnce({ data: connected })
            .mockResolvedValueOnce({ data: { folders: [{ id: 'z', name: 'Zebra' }, { id: 'a10', name: 'Arbeit 10' }], cursor: 'next' } })
            .mockResolvedValueOnce({ data: { folders: [{ id: 'a2', name: 'Arbeit 2' }, { id: 'o', name: 'Österreich' }], cursor: null } })
        vi.stubGlobal('axios', { get })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            await (wrapper.vm as any).loadFolders()
            await (wrapper.vm as any).loadFolders(true)
            expect((wrapper.vm as any).folders.map(folder => folder.name)).toEqual(['Arbeit 2', 'Arbeit 10', 'Österreich', 'Zebra'])
        } finally { wrapper.unmount() }
    })

    it('previews a local preparation without a grading JSON and shows its intended time', async () => {
        const post = vi.fn().mockResolvedValue({ data: { preview: { ...preview, exercise: null,
            dispatches: [{ source: 'Schooltool-Vorbereitung.json', purpose: 'tasks', rows: [{ person: 'Ada', status: 'Vorbereitet', planned_at: '2026-10-13T06:40:00Z' }] }] } } })
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connected }), post })
        const wrapper = mountComponent(WorkEvaluationImport)
        try {
            const vm = wrapper.vm as any
            await vm.openImport(work)
            const file = new File(['{}'], 'Schooltool-Vorbereitung.json')
            Object.defineProperty(file, 'webkitRelativePath', { value: 'Arbeit/Schooltool-Vorbereitung.json' })
            await vm.selectJsonFolder({ target: { files: [file], value: '' } })
            await flushPromises()
            expect(post).toHaveBeenCalledTimes(1)
            expect(post.mock.calls[0][1].has('package')).toBe(false)
            expect(post.mock.calls[0][1].get('documents')).toContain('Schooltool-Vorbereitung.json')
            expect(wrapper.text()).toContain('keine Bewertungen enthalten')
            expect(wrapper.text()).toContain('08:40')
            expect(wrapper.emitted('imported')).toBeUndefined()
        } finally { wrapper.unmount() }
    })
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
