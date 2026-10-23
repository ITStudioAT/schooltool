import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VAlert, VBtn, VFileInput, VMenu, VList, VListItem } from 'vuetify/components'
import WorkEvaluationImport from '@/pages/admin/teaching/overview/components/WorkEvaluationImport.vue'
import WorkImportActions from '@/pages/admin/teaching/overview/components/WorkImportActions.vue'
import WorkDropboxSource from '@/pages/admin/teaching/overview/components/WorkDropboxSource.vue'
import { clearWorkDropboxStates, loadWorkDropbox, workDropboxStates } from '@/helpers/workDropbox'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

const work = { id: 65, teaching_course_id: 2, title: 'E-Mails' }
const connected = { configured: true, installed: true, connected: true, can_quick_import: true, folder: { id: 'id:workfolder', name: 'Arbeit', path: '/Arbeit' }, message: 'Dropbox-Vorschau laden.' }
const preview = { target_work: { title: 'E-Mails', course_title: 'Kurs' }, exercise: {}, rows: [], maximum_minor: 500, can_import: true, hash: 'a'.repeat(64) }
const deadlineConflict = { ...preview, can_import: false, deadline_context: {
    requires_review: true, state: 'changed', current_at: '2026-10-15T10:30:00Z',
    source_at: '2026-10-15T09:45:00Z', message: 'Frist erneut prüfen.',
} }

function mountComponent(component: any, props = {}, stubs = {}) {
    vi.stubGlobal('visualViewport', { width: 1024, height: 768, offsetLeft: 0, offsetTop: 0, addEventListener: vi.fn(), removeEventListener: vi.fn() })
    return mount(component, { props, global: { plugins: [createTestingPinia({ createSpy: vi.fn, initialState: {
        AdminCourseStore: { selected_course: { id: 2 } }, AdminAdminStore: { config: { user: { id: 7 } } },
    } }), createVuetify({ components: { VAlert, VBtn, VFileInput, VMenu, VList, VListItem } })],
    components: { 'v-alert': VAlert, 'v-btn': VBtn, 'v-file-input': VFileInput, 'v-menu': VMenu, 'v-list': VList, 'v-list-item': VListItem }, stubs: { 'v-alert': false, VAlert: false, 'v-btn': false, VBtn: false, 'v-file-input': false, VFileInput: false, 'v-menu': false, VMenu: false, 'v-list': false, VList: false, 'v-list-item': false, VListItem: false, ...stubs } } })
}

function mountImportFlow() {
    const button = { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' }
    const alert = { template: '<div role="alert"><slot /></div>' }
    return mountComponent(WorkEvaluationImport, {}, { 'v-btn': button, VBtn: button, 'v-alert': alert, VAlert: alert })
}

afterEach(() => { clearWorkDropboxStates(); vi.restoreAllMocks(); vi.unstubAllGlobals() })

describe('Dropbox Quick-Import', () => {
    it.each([
        ['unavailable', { can_quick_import: false }],
        ['not configured', { configured: false, can_quick_import: false }],
        ['not installed', { installed: false, can_quick_import: false }],
        ['disconnected', { connected: false, can_quick_import: false }],
        ['unmapped folder', { folder: null, can_quick_import: false }],
        ['first import missing', { has_import: false, can_quick_import: false }],
        ['non-boolean permission', { can_quick_import: 'true' }],
    ])('hides Quick-Import without a hint for %s and offers linking only without a saved folder', async (_, state) => {
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { ...connected, ...state } }) })
        const wrapper = mountComponent(WorkImportActions, { work })
        try {
            expect(wrapper.find('.work-quick-import').exists()).toBe(false)
            await flushPromises()
            expect(wrapper.find('.work-quick-import').exists()).toBe(false)
            expect(wrapper.find('[role="status"]').exists()).toBe(false)
            expect(wrapper.findAll('button').map(button => button.text())).toEqual(
                ({ ...connected, ...state }.folder?.id) ? [] : ['Verknüpfen'],
            )
            if (!({ ...connected, ...state }.folder?.id)) await wrapper.get('button').trigger('click')
            expect(wrapper.emitted('link')).toEqual(({ ...connected, ...state }.folder?.id) ? undefined : [[work]])
            expect(wrapper.emitted('open')).toBeUndefined()
        } finally { wrapper.unmount() }
    })

    it('does not flash Quick-Import while loading and hides it after a failed refresh', async () => {
        let resolveStatus: (value: unknown) => void = () => {}
        const get = vi.fn().mockImplementationOnce(() => new Promise(resolve => { resolveStatus = resolve }))
            .mockRejectedValueOnce(new Error('Dropbox unavailable'))
        vi.stubGlobal('axios', { get })
        const wrapper = mountComponent(WorkImportActions, { work })
        try {
            expect(wrapper.find('.work-quick-import').exists()).toBe(false)
            await flushPromises()
            expect(wrapper.findAll('button').map(button => button.text())).toEqual(['Verknüpfen'])
            resolveStatus({ data: connected })
            await flushPromises()
            expect(wrapper.find('.work-quick-import').exists()).toBe(true)
            await wrapper.setProps({ work: { ...work, status: { folder_imported_at: '2026-10-16T20:00:00Z' } } })
            expect(wrapper.find('.work-quick-import').exists()).toBe(false)
            await flushPromises()
            expect(wrapper.find('.work-quick-import').exists()).toBe(false)
            expect(wrapper.findAll('button').map(button => button.text())).toEqual([])
        } finally { wrapper.unmount() }
    })

    it('uses only the current work and user status and ignores delayed responses from a prior work', async () => {
        let resolvePrevious: (value: unknown) => void = () => {}
        let resolveCurrent: (value: unknown) => void = () => {}
        const get = vi.fn().mockImplementationOnce(() => new Promise(resolve => { resolvePrevious = resolve }))
            .mockImplementationOnce(() => new Promise(resolve => { resolveCurrent = resolve }))
            .mockResolvedValueOnce({ data: { ...connected, can_quick_import: false } })
        vi.stubGlobal('axios', { get })
        workDropboxStates.set('8:2:65', connected)
        const wrapper = mountComponent(WorkImportActions, { work })
        const nextWork = { ...work, id: 66 }
        try {
            await wrapper.setProps({ work: nextWork })
            expect(wrapper.find('.work-quick-import').exists()).toBe(false)
            resolvePrevious({ data: connected })
            await flushPromises()
            expect(wrapper.find('.work-quick-import').exists()).toBe(false)
            resolveCurrent({ data: connected })
            await flushPromises()
            await wrapper.get('.work-quick-import').trigger('click')
            expect(wrapper.emitted('open')).toEqual([[nextWork, true]])
            useAdminStore().config.user.id = 9
            await flushPromises()
            expect(wrapper.find('.work-quick-import').exists()).toBe(false)
        } finally { wrapper.unmount() }
    })

    it('hides Quick-Import with automation off and disables an available action only while its caller is busy', async () => {
        const get = vi.fn().mockResolvedValue({ data: connected })
        vi.stubGlobal('axios', { get })
        const wrapper = mountComponent(WorkImportActions, { work, disabled: true })
        try {
            await flushPromises()
            expect(wrapper.get('.work-quick-import').attributes('disabled')).toBeDefined()
            await wrapper.setProps({ disabled: false })
            expect(wrapper.get('.work-quick-import').attributes('disabled')).toBeUndefined()
            await wrapper.setProps({ work: { ...work, automation_enabled: false } })
            expect(wrapper.find('.work-quick-import').exists()).toBe(false)
        } finally { wrapper.unmount() }
    })

    it.each(['folder', 'files', 'folder with preparation'])('keeps the original identity export separate when previewing local %s without applying', async entry => {
        const post = vi.fn().mockResolvedValue({ data: { preview } })
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connected }), post })
        const wrapper = mountComponent(WorkEvaluationImport)
        try {
            const vm = wrapper.vm as any
            vm.openImport(work)
            const packageText = JSON.stringify({ records: [], overview_pdf: null })
            const assessment = new File([packageText], 'Schooltool-Bewertungen.json')
            Object.defineProperty(assessment, 'arrayBuffer', { value: async () => new TextEncoder().encode(packageText).buffer })
            Object.defineProperty(assessment, 'webkitRelativePath', { value: 'Arbeit/Beurteilungen/Schooltool-Bewertungen.json' })
            const identityExport = new File(['{"kind":"schooltool.ai-work-export"}'], 'Schooltool_Arbeitsdaten.json')
            Object.defineProperty(identityExport, 'webkitRelativePath', { value: 'Arbeit/Schooltool_Arbeitsdaten.json' })
            const files = [assessment, identityExport]
            if (entry === 'folder with preparation') {
                const preparation = new File(['{}'], 'Schooltool-Versandvorbereitung.json')
                Object.defineProperty(preparation, 'webkitRelativePath', { value: 'Arbeit/Schooltool-Versandvorbereitung.json' })
                Object.defineProperty(preparation, 'arrayBuffer', { value: async () => new TextEncoder().encode('{}').buffer })
                files.push(preparation)
            }
            if (entry.startsWith('folder')) await vm.selectJsonFolder({ target: { files, value: '' } })
            else await vm.selectJson({ target: { files } })
            await flushPromises()
            expect(vm.import_error).toBe('')
            expect(post).toHaveBeenCalledTimes(1)
            const payload = post.mock.calls[0][1] as FormData
            expect((payload.get('package') as File).name).toBe(assessment.name)
            expect((payload.get('identity_export') as File).name).toBe(identityExport.name)
            if (entry === 'folder with preparation') expect(payload.get('documents')).toContain('Schooltool-Versandvorbereitung.json')
            else expect(payload.get('documents')).toBeNull()
            expect(payload.has('apply')).toBe(false)
            expect(vm.json_preview.hash).toBe(preview.hash)
            expect(wrapper.emitted('imported')).toBeUndefined()
        } finally { wrapper.unmount() }
    })

    it('rejects ambiguous identity exports before sending any local preview', async () => {
        const post = vi.fn()
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connected }), post })
        const wrapper = mountComponent(WorkEvaluationImport)
        try {
            const vm = wrapper.vm as any
            vm.openImport(work)
            await vm.selectJson({ target: { files: [new File(['{}'], 'Schooltool-Bewertungen.json'),
                new File(['{}'], 'Schooltool_Arbeitsdaten.json'), new File(['{}'], 'Schooltool_Arbeitsdaten.json')] } })
            expect(vm.import_error).toContain('ursprünglichen Schooltool_Arbeitsdaten.json-Export')
            expect(post).not.toHaveBeenCalled()
        } finally { wrapper.unmount() }
    })

    it('reads a missing cached folder path only in the active dialog and preserves the actual path across cache refreshes', async () => {
        const legacy = { ...connected, folder: { id: 'id:workfolder', name: 'Arbeit' } }
        const full = { ...legacy.folder, path: '/schule2020_2027/Materialien/Tatsächlicher Ordner/Arbeit' }
        let resolvePath: (value: unknown) => void = () => {}
        const get = vi.fn().mockImplementation(url => url.includes('include_folder_path=1')
            ? new Promise(done => { resolvePath = done }) : Promise.resolve({ data: legacy }))
        vi.stubGlobal('axios', { get })
        workDropboxStates.set('7:2:65', legacy)
        const wrapper = mountComponent(WorkDropboxSource, { work, active: false })
        try {
            await flushPromises()
            expect(get).not.toHaveBeenCalled()
            await wrapper.setProps({ active: true })
            await flushPromises()
            expect(wrapper.get('[data-testid="dropbox-bound-folder"] strong').text()).toBe('Arbeit')
            expect(wrapper.get('.work-dropbox-bound-path').text()).toBe('Vollständigen Pfad laden …')
            expect(get.mock.calls.filter(([url]) => url.includes('include_folder_path=1'))).toHaveLength(1)
            resolvePath({ data: { ...legacy, folder: full } })
            await flushPromises()
            expect(wrapper.get('.work-dropbox-bound-path').text()).toBe(full.path)
            workDropboxStates.set('7:2:65', { ...legacy })
            await flushPromises()
            expect(wrapper.get('.work-dropbox-bound-path').text()).toBe(full.path)
            expect(get.mock.calls.filter(([url]) => url.includes('include_folder_path=1'))).toHaveLength(1)
        } finally { wrapper.unmount() }
    })

    it('uses an already confirmed assigned path without requesting Dropbox metadata or suggestions', async () => {
        const get = vi.fn().mockResolvedValue({ data: connected })
        vi.stubGlobal('axios', { get })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            expect(wrapper.get('[data-testid="dropbox-bound-folder"] strong').text()).toBe('Arbeit')
            expect(wrapper.get('.work-dropbox-bound-path').text()).toBe('/Arbeit')
            expect(get).toHaveBeenCalledTimes(1)
            expect(get.mock.calls[0][0]).not.toContain('include_folder_path').not.toContain('suggest=')
        } finally { wrapper.unmount() }
    })

    it.each([['normal', false], ['quick', false], ['quick', true]])('keeps the %s preview independent from path metadata, metadata failure %s', async (entry, failPath) => {
        const legacy = { ...connected, folder: { id: 'id:workfolder', name: 'Arbeit' } }
        const full = { ...legacy.folder, path: '/schule2020_2027/Materialien/Tatsächlich/Arbeit' }
        let resolvePath: (value: unknown) => void = () => {}
        let rejectPath: (reason: unknown) => void = () => {}
        let resolvePreview: (value: unknown) => void = () => {}
        const get = vi.fn().mockImplementation(url => url.includes('include_folder_path=1')
            ? new Promise((resolve, reject) => { resolvePath = resolve; rejectPath = reject }) : Promise.resolve({ data: legacy }))
        const post = vi.fn().mockImplementation(() => new Promise(resolve => { resolvePreview = resolve }))
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountImportFlow()
        try {
            const importer = wrapper.vm as any
            const pending = importer.openImport(work, entry === 'quick')
            await flushPromises()
            expect(wrapper.get('.work-dropbox-bound-path').text()).toBe('Vollständigen Pfad laden …')
            if (entry === 'normal') {
                await wrapper.findAll('button').find(button => button.text() === 'Dropbox-Vorschau laden')!.trigger('click')
                await flushPromises()
            }
            expect(post).toHaveBeenCalledExactlyOnceWith(expect.stringContaining('/65/dropbox/import'), {})
            resolvePreview({ data: { preview } })
            await pending
            await flushPromises()
            expect(importer.json_preview.hash).toBe(preview.hash)
            expect(wrapper.get('.work-dropbox-bound-path').text()).toBe('Vollständigen Pfad laden …')
            if (failPath) {
                rejectPath({ response: { data: { message: 'Dropbox-Pfad derzeit nicht verfügbar.' } } })
                await flushPromises()
                expect(wrapper.get('.work-dropbox-bound-path').text()).toBe('Vollständiger Pfad nicht verfügbar.')
                expect(wrapper.text()).toContain('Dropbox-Pfad derzeit nicht verfügbar.')
                expect(get.mock.calls.filter(([url]) => url.includes('include_folder_path=1'))).toHaveLength(1)
                await wrapper.findAll('button').find(button => button.text() === 'Pfad erneut laden')!.trigger('click')
                await flushPromises()
            }
            resolvePath({ data: { ...legacy, folder: full } })
            await flushPromises()
            expect(wrapper.get('.work-dropbox-bound-path').text()).toBe(full.path)
            expect(importer.json_preview.hash).toBe(preview.hash)
            expect(importer.import_error).toBe('')
            expect(post).toHaveBeenCalledTimes(1)
            expect(get.mock.calls.filter(([url]) => url.includes('suggest=1'))).toHaveLength(0)
            expect(wrapper.emitted('imported')).toBeUndefined()
        } finally { wrapper.unmount() }
    })

    it.each(['work switch', 'dialog close'])('cancels a pending path request after %s and ignores its late metadata', async change => {
        const legacy = { ...connected, folder: { id: 'id:workfolder', name: 'Arbeit' } }
        const other = { ...connected, folder: { id: 'id:other', name: 'Andere Arbeit', path: '/Materialien/Andere Arbeit' } }
        let resolvePath: (value: unknown) => void = () => {}
        const get = vi.fn().mockImplementation(url => url.includes('include_folder_path=1')
            ? new Promise(resolve => { resolvePath = resolve }) : Promise.resolve({ data: url.includes('/66/') ? other : legacy }))
        const post = vi.fn()
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            const request = get.mock.calls.find(([url]) => url.includes('include_folder_path=1'))!
            if (change === 'work switch') await wrapper.setProps({ work: { ...work, id: 66 } })
            else await wrapper.setProps({ active: false })
            await flushPromises()
            expect(request[1].signal.aborted).toBe(true)
            resolvePath({ data: { ...legacy, folder: { ...legacy.folder, path: '/Veralteter/Metadatenpfad' } } })
            await flushPromises()
            expect(wrapper.text()).not.toContain('/Veralteter/Metadatenpfad')
            if (change === 'work switch') expect(wrapper.get('.work-dropbox-bound-path').text()).toBe(other.folder.path)
            expect(post).not.toHaveBeenCalled()
        } finally { wrapper.unmount() }
    })

    it('binds the visible unique proposal and loads one read-only parent preview with one explicit action', async () => {
        const target = { id: 'id:matching', name: '2026-10-16_Informatik_1A_1F',
            path: '/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/dateimanagement/Leistungsfeststellung/2026-10-16_Informatik_1A_1F' }
        let assigned = false
        let resolveBinding: (value: unknown) => void = () => {}
        let resolvePreview: (value: unknown) => void = () => {}
        let importer: any
        const get = vi.fn().mockImplementation(url => Promise.resolve({ data: url.includes('suggest=1')
            ? { suggested_folder: target, suggestions: [target] }
            : { ...connected, folder: assigned ? { id: target.id, name: target.name } : null, can_preview: assigned, can_quick_import: false } }))
        const post = vi.fn().mockImplementation(url => {
            if (url.endsWith('/folder')) return new Promise(done => { resolveBinding = done })
            expect(importer.json_preview).toBeNull()
            expect(importer.import_summary).toBeNull()
            return new Promise(done => { resolvePreview = done })
        })
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountImportFlow()
        try {
            importer = wrapper.vm as any
            importer.openImport(work)
            await flushPromises()
            const proposal = wrapper.get('[data-testid="dropbox-folder-proposal"]')
            expect(proposal.text()).toContain('Vorschlag zuordnen und Vorschau laden')
            expect(proposal.get('.work-dropbox-suggestion-path').text()).toBe(target.path)
            expect(wrapper.find('.work-dropbox-path').exists()).toBe(false)
            expect(post).not.toHaveBeenCalled()
            importer.json_preview = { ...preview, hash: 'old-preview' }
            importer.import_summary = { messages: ['Alter Prüfstand'] }
            await wrapper.vm.$nextTick()
            await wrapper.findAll('button').find(button => button.text() === 'Vorschlag zuordnen und Vorschau laden')!.trigger('click')
            await flushPromises()
            expect(post).toHaveBeenCalledExactlyOnceWith(expect.stringContaining('/65/dropbox/folder'), { folder_id: target.id })
            expect(wrapper.find('.work-dropbox-path').exists()).toBe(false)
            assigned = true
            resolveBinding({ data: { ...connected, folder: { id: target.id, name: target.name }, can_preview: true, can_quick_import: false } })
            await flushPromises()
            expect(post).toHaveBeenCalledTimes(2)
            expect(post.mock.calls[1]).toEqual([expect.stringContaining('/65/dropbox/import'), {}])
            expect(get.mock.calls.filter(([url]) => url.includes('suggest=1'))).toHaveLength(1)
            expect(get.mock.calls.filter(([url]) => url.includes('folder_id='))).toHaveLength(0)
            expect(importer.json_preview).toBeNull()
            expect(wrapper.findComponent(WorkDropboxSource).emitted('changed')).toHaveLength(1)
            expect(wrapper.findComponent(WorkDropboxSource).emitted('preview')).toHaveLength(1)
            resolvePreview({ data: { preview } })
            await flushPromises()
            expect(importer.json_preview.hash).toBe(preview.hash)
            expect(wrapper.get('.work-dropbox-bound-path').text()).toBe(target.path)
            expect(get.mock.calls.filter(([url]) => url.includes('include_folder_path=1'))).toHaveLength(0)
            expect(post.mock.calls.every(([, payload]) => !payload.apply)).toBe(true)
            expect(wrapper.emitted('imported')).toBeUndefined()
        } finally { wrapper.unmount() }
    })

    it('does not request a parent preview when binding the proposed folder fails', async () => {
        const target = { id: 'id:matching', name: 'Arbeitsordner', path: '/Materialien/Arbeitsordner' }
        const get = vi.fn().mockImplementation(url => Promise.resolve({ data: url.includes('suggest=1')
            ? { suggested_folder: target, suggestions: [target] } : { ...connected, folder: null } }))
        const post = vi.fn().mockRejectedValue({ response: { data: { message: 'Zuordnung fehlgeschlagen.' } } })
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountImportFlow()
        try {
            ;(wrapper.vm as any).openImport(work)
            await flushPromises()
            await wrapper.findAll('button').find(button => button.text() === 'Vorschlag zuordnen und Vorschau laden')!.trigger('click')
            await flushPromises()
            expect(post).toHaveBeenCalledExactlyOnceWith(expect.stringContaining('/folder'), { folder_id: target.id })
            expect(wrapper.text()).toContain('Zuordnung fehlgeschlagen.')
            expect(wrapper.findComponent(WorkDropboxSource).emitted('preview')).toBeUndefined()
            expect(workDropboxStates.get('7:2:65')?.folder).toBeNull()
            expect((wrapper.vm as any).json_preview).toBeNull()
            expect(wrapper.emitted('imported')).toBeUndefined()
        } finally { wrapper.unmount() }
    })

    it.each(['work switch', 'dialog close'])('ignores a late binding response after %s without requesting a preview', async change => {
        const target = { id: 'id:matching', name: 'Arbeitsordner', path: '/Materialien/Arbeitsordner' }
        let resolveBinding: (value: unknown) => void = () => {}
        const get = vi.fn().mockImplementation(url => Promise.resolve({ data: url.includes('suggest=1')
            ? { suggested_folder: target, suggestions: [target] } : { ...connected, folder: null } }))
        const post = vi.fn().mockImplementation(() => new Promise(done => { resolveBinding = done }))
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountImportFlow()
        try {
            const importer = wrapper.vm as any
            importer.openImport(work)
            await flushPromises()
            await wrapper.findAll('button').find(button => button.text() === 'Vorschlag zuordnen und Vorschau laden')!.trigger('click')
            await flushPromises()
            if (change === 'work switch') importer.openImport({ ...work, id: 66 })
            else importer.import_open = false
            await flushPromises()
            resolveBinding({ data: { ...connected, folder: target } })
            await flushPromises()
            expect(post).toHaveBeenCalledTimes(1)
            expect(post.mock.calls[0][0]).toContain('/65/dropbox/folder')
            expect(wrapper.findComponent(WorkDropboxSource).emitted('preview')).toBeUndefined()
            expect(wrapper.findComponent(WorkDropboxSource).emitted('changed')).toBeUndefined()
            expect(workDropboxStates.get(change === 'work switch' ? '7:2:66' : '7:2:65')?.folder).toBeNull()
            expect(importer.json_preview).toBeNull()
            expect(wrapper.emitted('imported')).toBeUndefined()
        } finally { wrapper.unmount() }
    })

    it.each([false, true])('keeps one-click binding unavailable for ambiguous or incomplete proposals, missing path %s', async missingPath => {
        const candidates = [{ id: 'id:first', name: 'Kandidat 1', path: '/Materialien/Kandidat 1' },
            { id: 'id:second', name: 'Kandidat 2', path: '/Materialien/Kandidat 2' }]
        const get = vi.fn().mockResolvedValueOnce({ data: { ...connected, folder: null } })
            .mockResolvedValueOnce({ data: missingPath ? { suggested_folder: { id: 'id:first', name: 'Kandidat 1' }, suggestions: [] }
                : { suggested_folder: null, suggestions: candidates } })
            .mockResolvedValue({ data: { folders: [], cursor: null } })
        const post = vi.fn()
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            expect(wrapper.text()).not.toContain('Vorschlag zuordnen und Vorschau laden')
            if (!missingPath) {
                await wrapper.findAll('button').find(button => button.text() === 'Kandidat 1')!.trigger('click')
                await flushPromises()
                await wrapper.findAll('button').find(button => button.text() === 'Ordnerauswahl schließen')!.trigger('click')
            }
            expect(wrapper.text()).not.toContain('Vorschlag zuordnen und Vorschau laden')
            expect(post).not.toHaveBeenCalled()
        } finally { wrapper.unmount() }
    })

    it('shows the full discovery result on dialog opening and keeps it while loading only its children on selection', async () => {
        let resolveSearch: (value: unknown) => void = () => {}
        let resolveListing: (value: unknown) => void = () => {}
        const path = '/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/dateimanagement/Leistungsfeststellung/2026-10-16_Informatik_1A_1F'
        const target = { id: 'id:VVgOdhU8r9sAAAAAAF5xVg', name: '2026-10-16_Informatik_1A_1F', path }
        const get = vi.fn().mockResolvedValueOnce({ data: { ...connected, folder: null, suggested_folder: { name: 'Veraltete Auswahl' } } })
            .mockImplementationOnce(() => new Promise(done => { resolveSearch = done }))
            .mockImplementationOnce(() => new Promise(done => { resolveListing = done }))
        const post = vi.fn()
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: false })
        try {
            await flushPromises()
            expect(get).not.toHaveBeenCalled()
            await wrapper.setProps({ active: true })
            await flushPromises()
            expect(wrapper.get('[role="status"]').text()).toContain('Passenden Importordner suchen …')
            expect(wrapper.find('.work-dropbox-path').exists()).toBe(false)
            expect(wrapper.text()).not.toContain('Veraltete Auswahl').not.toContain('Diesen Ordner zuordnen')
            resolveSearch({ data: { suggested_folder: target, suggestions: [target], message: 'Passender Ordner gefunden.' } })
            await flushPromises()
            expect(wrapper.find('[data-testid="dropbox-folder-search"]').exists()).toBe(false)
            expect(wrapper.text()).toContain(target.name).toContain('Noch nicht zugeordnet')
            expect(wrapper.get('.work-dropbox-suggestion-path').text()).toBe(path)
            await wrapper.findAll('button').find(button => button.text() === 'Ordner manuell wählen')!.trigger('click')
            await flushPromises()
            expect(wrapper.find('[data-testid="dropbox-folder-search"]').exists()).toBe(false)
            expect(wrapper.text()).toContain('Unterordner laden …').not.toContain('Passenden Importordner suchen')
            expect(wrapper.get('.work-dropbox-path').text()).toBe('Dropbox / ' + path.split('/').filter(Boolean).join(' / '))
            expect(get.mock.calls[2][0]).toContain('folder_id=id%3AVVgOdhU8r9sAAAAAAF5xVg').not.toContain('suggest=')
            resolveListing({ data: { folders: [{ id: 'id:child', name: 'Beurteilungen' }], cursor: null } })
            await flushPromises()
            expect(wrapper.get('.work-dropbox-path').text()).toContain('Lerninhalte / IT_Grundlagen / dateimanagement / Leistungsfeststellung / 2026-10-16_Informatik_1A_1F')
            expect(wrapper.text()).toContain('Beurteilungen')
            expect(get.mock.calls.filter(([url]) => url.includes('suggest=1'))).toHaveLength(1)
            expect(post).not.toHaveBeenCalled()
        } finally { wrapper.unmount() }
    })

    it('removes the prepared proposal when its Dropbox connection is disconnected', async () => {
        const target = { id: 'id:found', name: 'Alter Vorschlag', path: '/Alter/Dropbox/Pfad' }
        const get = vi.fn().mockResolvedValueOnce({ data: { ...connected, folder: null } })
            .mockResolvedValueOnce({ data: { suggested_folder: target, suggestions: [target] } })
        const disconnect = vi.fn().mockResolvedValue({ data: { ...connected, connected: false, folder: null } })
        vi.stubGlobal('axios', { get, delete: disconnect })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            expect(wrapper.text()).toContain(target.path)
            await wrapper.findAll('button').find(button => button.text() === 'Verbindung in Schooltool entfernen')!.trigger('click')
            await flushPromises()
            expect(wrapper.text()).not.toContain(target.name).not.toContain(target.path)
            expect(wrapper.text()).toContain('Dropbox verbinden')
        } finally { wrapper.unmount() }
    })

    it('reuses a recently found suggestion when the import dialog is mounted again', async () => {
        const get = vi.fn().mockImplementation(url => Promise.resolve({ data: url.includes('suggest=1')
            ? { suggested_folder: { id: 'id:found', name: 'Gefundener Arbeitsordner' }, suggestions: [] }
            : { ...connected, folder: null } }))
        vi.stubGlobal('axios', { get })
        const first = mountComponent(WorkDropboxSource, { work, active: true })
        await flushPromises()
        first.unmount()
        const reopened = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            expect(reopened.text()).toContain('Gefundener Arbeitsordner')
            expect(get.mock.calls.filter(([url]) => url.includes('suggest=1'))).toHaveLength(1)
        } finally { reopened.unmount() }
    })

    it.each(['error', 'timeout'])('ends discovery after a search %s and keeps manual selection available', async scenario => {
        let rejectSearch: (reason: unknown) => void = () => {}
        const get = vi.fn().mockResolvedValueOnce({ data: { ...connected, folder: null } })
            .mockImplementationOnce(() => new Promise((resolve, reject) => { rejectSearch = reject }))
            .mockResolvedValue({ data: { folders: [{ id: 'id:manual', name: 'Manuelle Auswahl' }], cursor: null } })
        const post = vi.fn()
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            expect(wrapper.find('[data-testid="dropbox-folder-search"]').exists()).toBe(true)
            rejectSearch(scenario === 'timeout' ? { code: 'ECONNABORTED' } : { response: { data: { message: 'Suche nicht verfügbar.' } } })
            await flushPromises()
            expect(wrapper.find('[data-testid="dropbox-folder-search"]').exists()).toBe(false)
            expect(wrapper.text()).toContain('Ordnersuche fehlgeschlagen').toContain('manuell auswählen')
            expect(wrapper.text()).toContain(scenario === 'timeout' ? 'zu lange gedauert' : 'Suche nicht verfügbar.')
            await wrapper.findAll('button').find(button => button.text() === 'Ordner manuell wählen')!.trigger('click')
            await flushPromises()
            expect(wrapper.text()).toContain('Manuelle Auswahl')
            expect(post).not.toHaveBeenCalled()
        } finally { wrapper.unmount() }
    })

    it('cancels discovery on closing and ignores its late reply while a reopened dialog searches again', async () => {
        let resolveOld: (value: unknown) => void = () => {}
        let resolveNew: (value: unknown) => void = () => {}
        const get = vi.fn().mockResolvedValueOnce({ data: { ...connected, folder: null } })
            .mockImplementationOnce(() => new Promise(done => { resolveOld = done }))
            .mockResolvedValueOnce({ data: { ...connected, folder: null } })
            .mockImplementationOnce(() => new Promise(done => { resolveNew = done }))
        vi.stubGlobal('axios', { get })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            const signal = get.mock.calls[1][1].signal
            await wrapper.findAll('button').find(button => button.text() === 'Abbrechen')!.trigger('click')
            expect(signal.aborted).toBe(true)
            await wrapper.setProps({ active: false })
            await wrapper.setProps({ active: true })
            await flushPromises()
            resolveOld({ data: { suggested_folder: { id: 'id:old', name: 'Veralteter Vorschlag' }, suggestions: [] } })
            await flushPromises()
            expect(wrapper.find('[data-testid="dropbox-folder-search"]').exists()).toBe(true)
            expect(wrapper.text()).not.toContain('Veralteter Vorschlag')
            resolveNew({ data: { suggested_folder: { id: 'id:new', name: 'Aktueller Vorschlag' }, suggestions: [] } })
            await flushPromises()
            expect(wrapper.text()).toContain('Aktueller Vorschlag').not.toContain('Veralteter Vorschlag')
        } finally { wrapper.unmount() }
    })

    it.each(['assigned', 'suggested'])('shows the full metadata path for an %s folder and after manual navigation', async mode => {
        const path = '/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/dateimanagement/Leistungsfeststellung/2026-10-16_Informatik_1A_1F'
        const target = { id: 'id:matching', name: '2026-10-16_Informatik_1A_1F' }
        const child = { id: 'id:child', name: 'Beurteilungen', path: path + '/Beurteilungen' }
        const get = vi.fn().mockImplementation(url => {
            if (!url.includes('/folders')) return Promise.resolve({ data: { ...connected, folder: mode === 'assigned' ? target : null } })
            if (url.includes('suggest=1')) return Promise.resolve({ data: { suggested_folder: { ...target, path }, suggestions: [], start_folder: null } })
            if (url.includes('folder_id=id%3Achild')) return Promise.resolve({ data: { folders: [], current: child, parent: { ...target, path } } })
            return Promise.resolve({ data: { folders: [child], current: { ...target, path }, parent: null } })
        })
        const post = vi.fn()
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            await wrapper.findAll('button').find(button => button.text() === (mode === 'assigned' ? 'Dropbox-Ordner ändern' : 'Ordner manuell wählen'))!.trigger('click')
            await flushPromises()
            expect(wrapper.get('.work-dropbox-path').text()).toBe('Dropbox / ' + path.split('/').filter(Boolean).join(' / '))
            await wrapper.findAll('button').find(button => button.text() === 'Beurteilungen')!.trigger('click')
            await flushPromises()
            expect(wrapper.get('.work-dropbox-path').text()).toBe('Dropbox / ' + child.path.split('/').filter(Boolean).join(' / '))
            await wrapper.findAll('button').find(button => button.text() === 'Übergeordneter Ordner')!.trigger('click')
            await flushPromises()
            expect(wrapper.get('.work-dropbox-path').text()).toContain('schule2020_2027 / Materialien / Lerninhalte / IT_Grundlagen / dateimanagement / Leistungsfeststellung / 2026-10-16_Informatik_1A_1F')
            expect(post).not.toHaveBeenCalled()
        } finally { wrapper.unmount() }
    })

    it('does not invent parent folders when older metadata has no full path', async () => {
        const get = vi.fn().mockResolvedValueOnce({ data: { ...connected, folder: { id: 'id:workfolder', name: 'Arbeit' } } })
            .mockResolvedValue({ data: { folders: [], parent: null } })
        vi.stubGlobal('axios', { get })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            await wrapper.findAll('button').find(button => button.text() === 'Dropbox-Ordner ändern')!.trigger('click')
            await flushPromises()
            expect(wrapper.get('.work-dropbox-path').text()).toBe('Dropbox / Arbeit · Vollständiger Pfad nicht verfügbar.')
        } finally { wrapper.unmount() }
    })

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

    it('hides Quick-Import until apply while the initial Dropbox preview stays available', async () => {
        const initial = { ...connected, can_quick_import: false, can_preview: true, has_import: false,
            message: 'Quick-Import erst nach dem ersten erfolgreich übernommenen Import dieser Arbeit.' }
        const get = vi.fn().mockResolvedValue({ data: initial })
        vi.stubGlobal('axios', { get, post: vi.fn().mockResolvedValue({ data: { preview } }) })
        const actions = mountComponent(WorkImportActions, { work })
        const source = mountComponent(WorkDropboxSource, { work, active: true })
        const importer = mountComponent(WorkEvaluationImport)
        try {
            await flushPromises()
            expect(actions.find('.work-quick-import').exists()).toBe(false)
            expect(source.findAll('button').find(button => button.text() === 'Dropbox-Vorschau laden')!.attributes('disabled')).toBeUndefined()
            const vm = importer.vm as any
            await vm.openImport(work)
            await vm.previewDropbox(false)
            expect(vm.json_preview.hash).toBe(preview.hash)
            expect(actions.find('.work-quick-import').exists()).toBe(false)
            get.mockResolvedValue({ data: { ...initial, can_quick_import: true, has_import: true } })
            await vm.applyJson()
            await flushPromises()
            expect(actions.get('.work-quick-import').attributes('disabled')).toBeUndefined()
        } finally { actions.unmount(); source.unmount(); importer.unmount() }
    })
    it.each([
        [false, 'mismatch', '2026-10-15_Informatik_5L2_Gruppe-1'],
        [true, 'mismatch', '2026-10-15_Informatik_5L2_Gruppe-1'],
        [false, 'mismatch', '2026-10-04_E-Mail_DGB-3B-2_Tag'],
    ])('shows a red alert with an exclamation icon for an implausible folder, assigned %s, status %s, name %s', async (assigned, status, name) => {
        const folder = { id: 'id:wrong', name }
        const message = 'Ordner passt nicht zum Kurs INF - 5L1 - 1. Bitte den passenden Dropbox-Ordner wählen!'
        vi.stubGlobal('axios', { get: vi.fn().mockImplementation(url => Promise.resolve({ data: url.includes('suggest=1') ? { suggestions: [], suggested_folder: null } : { ...connected,
            folder: assigned ? folder : null, suggested_folder: folder, can_preview: false, can_quick_import: false,
            folder_plausibility: { status, message } } })) })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            const alert = wrapper.get('.work-folder-mismatch')
            expect(alert.attributes('role')).toBe('alert')
            expect(alert.text()).toBe(message)
            expect(alert.findComponent(VAlert).props()).toMatchObject({ type: 'error', icon: 'mdi-alert-circle' })
            if (assigned) expect(wrapper.text()).toContain(folder.name)
            else expect(wrapper.text()).not.toContain(folder.name)
            if (assigned) expect(wrapper.findAll('button').find(button => button.text() === 'Dropbox-Vorschau laden')!.attributes('disabled')).toBeDefined()
        } finally { wrapper.unmount() }
    })
    it('keeps an unstructured folder preview available with a neutral course hint', async () => {
        const message = 'Der Ordnername enthält keine eindeutige Kursangabe. Die Zuordnung wird anhand der Importdateien geprüft.'
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { ...connected,
            folder_plausibility: { status: 'unknown', message } } }) })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            expect(wrapper.find('[role="alert"]').exists()).toBe(false)
            expect(wrapper.findAll('[role="status"]').some(hint => hint.text() === message)).toBe(true)
            const button = wrapper.findAll('button').find(button => button.text() === 'Dropbox-Vorschau laden')!
            expect(button.attributes('disabled')).toBeUndefined()
            await button.trigger('click')
            expect(wrapper.emitted('preview')).toHaveLength(1)
        } finally { wrapper.unmount() }
    })
    it('opens the work own saved folder without looking up suggestions and preserves parent browsing', async () => {
        const suggestion = { id: 'id:last-folder', name: 'Zuletzt gewählt', path: '/Zuletzt gewählt' }
        const get = vi.fn().mockResolvedValueOnce({ data: { ...connected, folder: suggestion,
            suggested_folder: suggestion } })
            .mockResolvedValueOnce({ data: { folders: [], cursor: null, parent: { id: 'id:parent', name: 'Materialien' } } })
            .mockResolvedValueOnce({ data: { folders: [], cursor: null, parent: null } })
        const post = vi.fn()
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            const change = wrapper.findAll('button').find(button => button.text() === 'Dropbox-Ordner ändern')!
            await change.trigger('click')
            await flushPromises()
            expect(get.mock.calls[1][0]).toContain('folder_id=id%3Alast-folder')
            expect(get.mock.calls[1][0]).not.toContain('suggest=')
            expect(wrapper.text()).toContain('Dropbox / Zuletzt gewählt')
            expect(wrapper.text()).toContain('Diesen Ordner zuordnen')
            expect(post).not.toHaveBeenCalled()
            await wrapper.findAll('button').find(button => button.text() === 'Übergeordneter Ordner')!.trigger('click')
            await flushPromises()
            expect(get.mock.calls[2][0]).toContain('folder_id=id%3Aparent')
            expect(wrapper.text()).toContain('Dropbox / Materialien')
        } finally { wrapper.unmount() }
    })
    it('looks up a matching suggestion on dialog opening and opens it on selection without repeating the search or saving', async () => {
        const suggestion = { id: 'id:matching', name: '2026-10-16_Informatik_1A_1F', path: '/Materialien/2026-10-16_Informatik_1A_1F' }
        const state = { ...connected, folder: null, suggested_folder: { id: 'id:old', name: 'Andere Arbeit' } }
        const get = vi.fn().mockResolvedValueOnce({ data: state })
            .mockResolvedValueOnce({ data: { suggested_folder: suggestion, suggestions: [suggestion],
                start_folder: { id: 'id:learning', name: 'Lerninhalte' }, message: 'Passender Ordner vorgeschlagen.' } })
            .mockResolvedValueOnce({ data: { folders: [], cursor: null } })
        const post = vi.fn().mockResolvedValue({ data: { ...connected, folder: suggestion } })
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            expect(get).toHaveBeenCalledTimes(2)
            expect(get.mock.calls[1][0]).toContain('suggest=1')
            expect(wrapper.find('.work-dropbox-path').exists()).toBe(false)
            expect(wrapper.text()).not.toContain('Andere Arbeit')
            await wrapper.findAll('button').find(button => button.text() === 'Ordner manuell wählen')!.trigger('click')
            await flushPromises()
            expect(get.mock.calls[1][0]).toContain('suggest=1')
            expect(get.mock.calls[2][0]).toContain('folder_id=id%3Amatching')
            expect(wrapper.text()).toContain(`Vorschlag: ${suggestion.name}`).toContain('ausdrücklich zuordnen')
            expect(workDropboxStates.get('7:2:65')?.folder).toBeNull()
            expect(post).not.toHaveBeenCalled()
            await wrapper.findAll('button').find(button => button.text() === 'Diesen Ordner zuordnen')!.trigger('click')
            await flushPromises()
            expect(post).toHaveBeenCalledExactlyOnceWith(expect.any(String), { folder_id: suggestion.id })
            expect(workDropboxStates.get('7:2:65')?.folder).toEqual(suggestion)
        } finally { wrapper.unmount() }
    })
    it.each([1, 2])('shows %s uncertain candidates with their full paths in the dialog and opens one only on click', async count => {
        const candidates = [{ id: 'id:first', name: 'Arbeit 1', path: '/Lerninhalte/IT/Arbeit 1', reason: 'Kurs passt; Termin unklar.' },
            { id: 'id:second', name: 'Arbeit 2', path: '/Lerninhalte/IT/Arbeit 2', reason: 'Titel passt; Kurs unklar.' }].slice(0, count)
        const get = vi.fn().mockResolvedValueOnce({ data: { ...connected, folder: null } })
            .mockResolvedValueOnce({ data: { suggested_folder: null, suggestions: candidates,
                start_folder: { id: 'id:learning', name: 'Lerninhalte' }, message: 'Bitte den passenden Vorschlag prüfen.' } })
            .mockResolvedValue({ data: { folders: [], cursor: null } })
        const post = vi.fn()
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            expect(wrapper.text()).toContain('Mögliche Ordner · Vorschläge').toContain(candidates[0].path).toContain(candidates[0].reason)
            for (const candidate of candidates) expect(wrapper.text()).toContain(candidate.path)
            expect(wrapper.find('.work-dropbox-path').exists()).toBe(false)
            await wrapper.findAll('button').find(button => button.text() === candidates[0].name)!.trigger('click')
            await flushPromises()
            expect(get.mock.calls[2][0]).toContain('folder_id=id%3Afirst')
            expect(get.mock.calls.filter(([url]) => url.includes('suggest=1'))).toHaveLength(1)
            expect(wrapper.get('.work-dropbox-path').text()).toBe('Dropbox / Lerninhalte / IT / Arbeit 1')
            expect(post).not.toHaveBeenCalled()
            expect(workDropboxStates.get('7:2:65')?.folder).toBeNull()
        } finally { wrapper.unmount() }
    })
    it.each(['no match', 'legacy response', 'lookup error'])('keeps manual browsing available after %s', async scenario => {
        const get = vi.fn().mockResolvedValueOnce({ data: { ...connected, folder: null } })
        if (scenario === 'lookup error') get.mockRejectedValueOnce({ response: { data: { message: 'Suche derzeit nicht möglich.' } } })
        else get.mockResolvedValueOnce({ data: scenario === 'legacy response' ? { folders: [], cursor: null }
            : { suggested_folder: null, suggestions: [], start_folder: { id: 'id:learning', name: 'Lerninhalte' }, message: 'Kein passender Ordner gefunden.' } })
        get.mockResolvedValue({ data: { folders: [{ id: 'id:manual', name: 'Manuell wählen' }], cursor: null } })
        vi.stubGlobal('axios', { get, post: vi.fn() })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            await wrapper.findAll('button').find(button => button.text() === 'Ordner manuell wählen')!.trigger('click')
            await flushPromises()
            expect(get).toHaveBeenCalledTimes(3)
            expect(get.mock.calls[2][0].includes('folder_id=id%3Alearning')).toBe(scenario === 'no match')
            expect(wrapper.findAll('button').find(button => button.text() === 'Manuell wählen')?.attributes('disabled')).toBeUndefined()
            if (scenario === 'lookup error') expect(wrapper.text()).toContain('Suche derzeit nicht möglich.').toContain('manuell auswählen')
            if (scenario === 'no match') {
                await wrapper.findAll('button').find(button => button.text() === 'Dropbox-Hauptordner')!.trigger('click')
                await flushPromises()
                expect(get.mock.calls[3][0]).not.toContain('folder_id=')
            }
        } finally { wrapper.unmount() }
    })
    it('ignores delayed suggestions after switching to another work', async () => {
        let resolve: (value: unknown) => void = () => {}
        const get = vi.fn().mockResolvedValueOnce({ data: { ...connected, folder: null } })
            .mockImplementationOnce(() => new Promise(done => { resolve = done }))
            .mockResolvedValue({ data: { ...connected, folder: { id: 'id:other', name: 'Andere Arbeit', path: '/Andere Arbeit' } } })
        vi.stubGlobal('axios', { get, post: vi.fn() })
        const wrapper = mountComponent(WorkDropboxSource, { work, active: true })
        try {
            await flushPromises()
            expect(wrapper.get('[data-testid="dropbox-folder-search"]').text()).toContain('Passenden Importordner suchen')
            await wrapper.setProps({ work: { ...work, id: 66 } })
            await flushPromises()
            resolve({ data: { suggested_folder: { id: 'id:stale', name: 'Veralteter Vorschlag' }, suggestions: [], message: 'Veraltete Suche' } })
            await flushPromises()
            expect(wrapper.text()).not.toContain('Veralteter Vorschlag').not.toContain('Veraltete Suche')
            expect(get).toHaveBeenCalledTimes(3)
            expect(workDropboxStates.get('7:2:66')?.folder?.id).toBe('id:other')
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
    it.each([false, true])('previews the authoritative deadline without offering to restore a stale package deadline (stale: %s)', async stale => {
        const post = vi.fn().mockResolvedValue({ data: { preview: { ...preview, exercise: null, dispatches: [], can_import: !stale,
            deadline_context: stale ? { requires_review: true, current_at: '2026-10-15T12:55:00Z', source_at: '2026-10-15T14:00:00Z' } : null,
            deadline_update: { at: '2026-10-15T14:55:00+02:00' } } } })
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: connected }), post })
        const wrapper = mountComponent(WorkEvaluationImport)
        try {
            const vm = wrapper.vm as any
            await vm.openImport(work)
            const file = new File(['{}'], 'Anlage_und_Quellen.json')
            Object.defineProperty(file, 'webkitRelativePath', { value: 'Arbeit/Anlage_und_Quellen.json' })
            await vm.selectJsonFolder({ target: { files: [file], value: '' } })
            await flushPromises()
            expect(post).toHaveBeenCalledTimes(1)
            expect(post.mock.calls[0][1].get('documents')).toContain('Anlage_und_Quellen.json')
            expect(post.mock.calls[0][1].get('apply')).not.toBe('1')
            expect(wrapper.text()).toContain('Prüfungsschluss wird aus dem Arbeitsordner übernommen:')
            expect(wrapper.text()).toContain('14:55')
            expect(vm.deadline_conflict).toBeNull()
            expect(wrapper.text()).not.toContain('Paketfrist übernehmen')
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
            expect(wrapper.find('.work-quick-import').exists()).toBe(false)
            expect(wrapper.findAll('button')[0].attributes('disabled')).toBeUndefined()
            workDropboxStates.set('7:2:65', connected)
            await flushPromises()
            expect(wrapper.get('.work-quick-import').attributes('disabled')).toBeUndefined()
            await wrapper.get('.work-quick-import').trigger('click')
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
            await wrapper.findAll('button').find(button => button.text() === 'Ordner manuell wählen')!.trigger('click')
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
