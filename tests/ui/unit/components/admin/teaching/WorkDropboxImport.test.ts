import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VAlert, VBtn, VFileInput } from 'vuetify/components'
import WorkEvaluationImport from '@/pages/admin/teaching/overview/components/WorkEvaluationImport.vue'
import WorkImportActions from '@/pages/admin/teaching/overview/components/WorkImportActions.vue'
import WorkDropboxSource from '@/pages/admin/teaching/overview/components/WorkDropboxSource.vue'
import { clearWorkDropboxStates, loadWorkDropbox, workDropboxStates } from '@/helpers/workDropbox'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'

const work = { id: 65, teaching_course_id: 2, title: 'E-Mails' }
const connected = { configured: true, installed: true, connected: true, can_quick_import: true, folder: { id: 'id:workfolder', name: 'Arbeit' }, message: 'Dropbox-Vorschau laden.' }
const preview = { target_work: { title: 'E-Mails', course_title: 'Kurs' }, exercise: {}, rows: [], maximum_minor: 500, can_import: true, hash: 'a'.repeat(64) }
const deadlineConflict = { ...preview, can_import: false, deadline_context: {
    requires_review: true, state: 'changed', current_at: '2026-10-15T10:30:00Z',
    source_at: '2026-10-15T09:45:00Z', message: 'Frist erneut prüfen.',
} }

function mountComponent(component: any, props = {}) {
    return mount(component, { props, global: { plugins: [createTestingPinia({ createSpy: vi.fn, initialState: {
        AdminCourseStore: { selected_course: { id: 2 } }, AdminAdminStore: { config: { user: { id: 7 } } },
    } }), createVuetify({ components: { VAlert, VBtn, VFileInput } })],
    components: { 'v-alert': VAlert, 'v-btn': VBtn, 'v-file-input': VFileInput }, stubs: { 'v-alert': false, VAlert: false, 'v-btn': false, VBtn: false, 'v-file-input': false, VFileInput: false } } })
}

afterEach(() => { clearWorkDropboxStates(); vi.restoreAllMocks(); vi.unstubAllGlobals() })

describe('Dropbox Quick-Import', () => {
    it.each(['dropbox', 'upload'])('resolves a deadline conflict from %s and rechecks the preview before applying anything', async source => {
        const saved = { ...work, finish_until_date: '2026-10-15', finish_until_time: '11:45' }
        const post = vi.fn().mockResolvedValueOnce({ data: { preview: deadlineConflict } })
            .mockResolvedValueOnce({ data: { preview: { ...preview, hash: 'b'.repeat(64) } } })
        const put = vi.fn().mockResolvedValue({ data: { data: saved } })
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connected }), post, put })
        const wrapper = mountComponent(WorkEvaluationImport)
        try {
            const vm = wrapper.vm as any
            await vm.openImport(work, source === 'dropbox')
            const file = new File(['{}'], 'Schooltool-Bewertungen.json')
            if (source === 'upload') await vm.selectJson({ target: { files: [file] } })
            await flushPromises()
            const dialog = wrapper.findAll('v-dialog').find(dialog => dialog.text().includes('Abgabefrist klären'))!
            expect(dialog.attributes('persistent')).toBeDefined()
            expect(dialog.text()).toContain('15.10.2026, 12:30 Uhr')
            expect(dialog.text()).toContain('15.10.2026, 11:45 Uhr')
            expect(put).not.toHaveBeenCalled()
            await dialog.findAll('button').find(button => button.text() === 'Paketfrist übernehmen')!.trigger('click')
            await flushPromises()
            expect(put).toHaveBeenCalledExactlyOnceWith(expect.stringContaining('/65'), {
                finish_until_date: '2026-10-15', finish_until_time: '11:45',
            })
            expect(post).toHaveBeenCalledTimes(2)
            if (source === 'dropbox') expect(post.mock.calls[1][1]).toEqual({})
            else {
                expect(post.mock.calls[1][1].get('package').name).toBe(file.name)
                expect(post.mock.calls[1][1].has('apply')).toBe(false)
            }
            expect(vm.json_preview.hash).toBe('b'.repeat(64))
            expect(wrapper.text()).not.toContain('Abgabefrist klären')
            expect(wrapper.emitted('deadline-updated')).toEqual([[saved]])
            expect(wrapper.emitted('imported')).toBeUndefined()
            expect(vm.import_summary).toBeNull()
            expect(wrapper.findAll('button').find(button => button.text() === 'Angezeigte Änderungen übernehmen')!.attributes('disabled')).toBeUndefined()
        } finally { wrapper.unmount() }
    })

    it('keeps the stored deadline and cancels the import without saving or applying', async () => {
        const post = vi.fn().mockResolvedValue({ data: { preview: deadlineConflict } })
        const put = vi.fn()
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connected }), post, put })
        const wrapper = mountComponent(WorkEvaluationImport)
        try {
            const vm = wrapper.vm as any
            await vm.openImport(work, true)
            await flushPromises()
            await wrapper.findAll('button').find(button => button.text() === 'Gespeicherte Frist behalten und Import abbrechen')!.trigger('click')
            expect(vm.import_open).toBe(false)
            expect(vm.json_preview).toBeNull()
            expect(put).not.toHaveBeenCalled()
            expect(post).toHaveBeenCalledTimes(1)
            expect(wrapper.emitted('deadline-updated')).toBeUndefined()
            expect(wrapper.emitted('imported')).toBeUndefined()
        } finally { wrapper.unmount() }
    })

    it('keeps the conflict open on a save error and retries without allowing duplicate saves', async () => {
        let resolve: (value: unknown) => void = () => {}
        const post = vi.fn().mockResolvedValueOnce({ data: { preview: deadlineConflict } })
            .mockResolvedValueOnce({ data: { preview: { ...preview, can_import: false } } })
        const put = vi.fn().mockRejectedValueOnce({ response: { data: { message: 'Speichern fehlgeschlagen.' } } })
            .mockImplementationOnce(() => new Promise(done => { resolve = done }))
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connected }), post, put })
        const wrapper = mountComponent(WorkEvaluationImport)
        try {
            const vm = wrapper.vm as any
            await vm.openImport(work, true)
            await vm.acceptPackageDeadline()
            await flushPromises()
            expect(wrapper.text()).toContain('Speichern fehlgeschlagen.')
            expect(wrapper.text()).toContain('Abgabefrist klären')
            expect(post).toHaveBeenCalledTimes(1)
            const pending = vm.acceptPackageDeadline()
            await flushPromises()
            await vm.acceptPackageDeadline()
            vm.cancelDeadlineImport()
            expect(vm.import_open).toBe(true)
            expect(put).toHaveBeenCalledTimes(2)
            resolve({ data: { data: work } })
            await pending
            await flushPromises()
            expect(wrapper.text()).not.toContain('Speichern fehlgeschlagen.')
            expect(vm.json_preview.can_import).toBe(false)
            expect(wrapper.findAll('button').find(button => button.text() === 'Angezeigte Änderungen übernehmen')!.attributes('disabled')).toBeDefined()
            expect(wrapper.emitted('imported')).toBeUndefined()
        } finally { wrapper.unmount() }
    })

    it('ignores a delayed deadline save after switching courses', async () => {
        let resolve: (value: unknown) => void = () => {}
        const post = vi.fn().mockResolvedValue({ data: { preview: deadlineConflict } })
        const put = vi.fn().mockImplementation(() => new Promise(done => { resolve = done }))
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connected }), post, put })
        const wrapper = mountComponent(WorkEvaluationImport)
        try {
            const vm = wrapper.vm as any
            await vm.openImport(work, true)
            const pending = vm.acceptPackageDeadline()
            useCourseStore().selected_course = { id: 3 } as any
            await flushPromises()
            resolve({ data: { data: work } })
            await pending
            expect(vm.import_open).toBe(false)
            expect(vm.json_preview).toBeNull()
            expect(post).toHaveBeenCalledTimes(1)
            expect(wrapper.emitted('deadline-updated')).toBeUndefined()
            expect(wrapper.emitted('imported')).toBeUndefined()
        } finally { wrapper.unmount() }
    })

    it.each([
        ['2026-10-31T23:45:00Z', '2026-11-01', '00:45'],
        ['2026-07-01T22:15:00Z', '2026-07-02', '00:15'],
    ])('saves a missing target deadline using Vienna date and time for %s', async (source, date, time) => {
        const post = vi.fn().mockResolvedValueOnce({ data: { preview: { ...deadlineConflict,
            deadline_context: { requires_review: true, state: 'missing_target', source_at: source } } } })
            .mockResolvedValueOnce({ data: { preview } })
        const put = vi.fn().mockResolvedValue({ data: { data: work } })
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connected }), post, put })
        const wrapper = mountComponent(WorkEvaluationImport)
        try {
            const vm = wrapper.vm as any
            await vm.openImport(work, true)
            await vm.acceptPackageDeadline()
            expect(put).toHaveBeenCalledExactlyOnceWith(expect.any(String), { finish_until_date: date, finish_until_time: time })
        } finally { wrapper.unmount() }
    })

    it('keeps Quick-Import disabled until apply while the initial Dropbox preview stays available', async () => {
        const initial = { ...connected, can_quick_import: false, can_preview: true, has_import: false,
            message: 'Quick-Import erst nach dem ersten erfolgreich übernommenen Import dieser Arbeit.' }
        const get = vi.fn().mockResolvedValue({ data: initial })
        vi.stubGlobal('axios', { get, post: vi.fn().mockResolvedValue({ data: { preview } }) })
        const actions = mountComponent(WorkImportActions, { work })
        const source = mountComponent(WorkDropboxSource, { work, active: true })
        const importer = mountComponent(WorkEvaluationImport)
        try {
            await flushPromises()
            expect(actions.findAll('button')[1].attributes('disabled')).toBeDefined()
            expect(source.findAll('button').find(button => button.text() === 'Dropbox-Vorschau laden')!.attributes('disabled')).toBeUndefined()
            const vm = importer.vm as any
            await vm.openImport(work)
            await vm.previewDropbox(false)
            expect(vm.json_preview.hash).toBe(preview.hash)
            expect(actions.findAll('button')[1].attributes('disabled')).toBeDefined()
            get.mockResolvedValue({ data: { ...initial, can_quick_import: true, has_import: true } })
            await vm.applyJson()
            await flushPromises()
            expect(actions.findAll('button')[1].attributes('disabled')).toBeUndefined()
        } finally { actions.unmount(); source.unmount(); importer.unmount() }
    })
    it.each([
        [false, 'mismatch', '2026-10-15_Informatik_5L2_Gruppe-1'],
        [true, 'mismatch', '2026-10-15_Informatik_5L2_Gruppe-1'],
        [false, 'mismatch', '2026-10-04_E-Mail_DGB-3B-2_Tag'],
        [false, 'unknown', 'Leistungsarbeit'],
    ])('shows a red alert with an exclamation icon for an implausible folder, assigned %s, status %s, name %s', async (assigned, status, name) => {
        const folder = { id: 'id:wrong', name }
        const message = 'Ordner passt nicht zum Kurs INF - 5L1 - 1. Bitte den passenden Dropbox-Ordner wählen!'
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { ...connected,
            folder: assigned ? folder : null, suggested_folder: folder, can_preview: false, can_quick_import: false,
            folder_plausibility: { status, message } } }) })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            const alert = wrapper.get('.work-folder-mismatch')
            expect(alert.attributes('role')).toBe('alert')
            expect(alert.text()).toBe(message)
            expect(alert.findComponent(VAlert).props()).toMatchObject({ type: 'error', icon: 'mdi-alert-circle' })
            expect(wrapper.text()).toContain(folder.name)
            if (assigned) expect(wrapper.findAll('button').find(button => button.text() === 'Dropbox-Vorschau laden')!.attributes('disabled')).toBeDefined()
        } finally { wrapper.unmount() }
    })
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
