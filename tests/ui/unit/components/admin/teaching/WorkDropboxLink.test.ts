import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import WorkDropboxLink from '@/pages/admin/teaching/overview/components/WorkDropboxLink.vue'
import WorkImportActions from '@/pages/admin/teaching/overview/components/WorkImportActions.vue'
import { clearWorkDropboxStates, workDropboxStates } from '@/helpers/workDropbox'
import { useAdminStore } from '@/stores/admin/AdminStore'

vi.mock('vue-filepond/dist/vue-filepond.js', () => ({ default: () => ({
    name: 'FilePond', props: ['beforeAddFile', 'disabled'], emits: ['updatefiles'], template: '<div data-testid="filepond" />',
}) }))

const work = { id: 65, teaching_course_id: 2, title: 'E-Mails' }
const handshake = '{"schema_version":1,"module_id":"a263b873-4f27-4167-90f0-7d94ea0f67fa"}'
const candidate = { folder_id: 'id:module', folder_path: '/Materialien/EMails', handshake_file_id: 'id:handshake' }

function mountLink(post = vi.fn(), inline = false) {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { configured: true, installed: true, connected: true } }), post })
    return mount(WorkDropboxLink, {
        props: { courseId: 2, inline },
        global: { plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } })],
            stubs: { 'v-dialog': { props: ['persistent'], template: '<div><slot /></div>' }, 'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' } },
        },
    })
}

function selectHandshake(wrapper: ReturnType<typeof mountLink>, text = handshake, name = 'handshake.json') {
    wrapper.findComponent({ name: 'FilePond' }).vm.$emit('updatefiles', [{ file: { name, size: text.length, text: async () => text } }])
}

afterEach(() => { clearWorkDropboxStates(); vi.unstubAllGlobals() })

it('uses FilePond for one file and requires explicit linking after the online path is shown', async () => {
    const post = vi.fn().mockResolvedValueOnce({ data: { candidate, proof: 'proof', can_link: true, module_id: 'module', message: 'Online geprüft' } })
        .mockResolvedValueOnce({ data: { module_link: { folder: { id: 'id:module' } }, connected: true, link_preparation: { status: 'initialized' } } })
    const wrapper = mountLink(post)
    await (wrapper.vm as any).openLink(work)
    selectHandshake(wrapper)
    await flushPromises()

    expect(post).toHaveBeenCalledTimes(1)
    expect(post.mock.calls[0][1]).toEqual({ handshake, filename: 'handshake.json', resolve: true })
    expect(wrapper.get('[data-testid="module-folder-path"]').text()).toBe('/Materialien/EMails')
    expect(workDropboxStates.get('7:2:65')?.module_link).toBeUndefined()
    await wrapper.findAll('button').find(button => button.text() === 'Verknüpfen und Prüfungsordner anlegen')!.trigger('click')
    await flushPromises()
    expect(post.mock.calls[1][1]).toEqual({ handshake, filename: 'handshake.json', proof: 'proof' })
    expect(workDropboxStates.get('7:2:65')?.module_link.folder.id).toBe('id:module')
    expect(post.mock.calls.every(([url]) => !url.includes('/import'))).toBe(true)
    wrapper.unmount()
})

it('continues the already confirmed creation handshake through resolve and store without a second click', async () => {
    const post = vi.fn().mockResolvedValueOnce({ data: { candidate, proof: 'proof', can_link: true, module_id: 'module' } })
        .mockResolvedValueOnce({ data: { connected: true, module_link: { folder: { id: 'id:module' } }, link_preparation: { status: 'initialized' } } })
    const wrapper = mountLink(post, true)
    try {
        await (wrapper.vm as any).openLink(work, { filename: 'handshake.json', contents: handshake }, true)
        await flushPromises()
        expect(post.mock.calls.map(([, body]) => body)).toEqual([
            { handshake, filename: 'handshake.json', resolve: true },
            { handshake, filename: 'handshake.json', proof: 'proof' },
        ])
        expect(wrapper.emitted('linked')).toHaveLength(1)
        expect(wrapper.find('[data-testid="work-module-inline"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('retries the failed online check and continues the already authorized creation', async () => {
    const post = vi.fn().mockRejectedValueOnce({ response: { data: { message: 'Dropbox ist nicht erreichbar.' } } })
        .mockResolvedValueOnce({ data: { candidate, proof: 'proof', can_link: true } })
        .mockResolvedValueOnce({ data: { connected: true, module_link: { folder: { id: 'id:module' } }, link_preparation: { status: 'initialized' } } })
    const wrapper = mountLink(post, true)
    try {
        await (wrapper.vm as any).openLink(work, { filename: 'handshake.json', contents: handshake }, true)
        await flushPromises()
        expect(wrapper.text()).toContain('Dropbox ist nicht erreichbar.')
        expect(wrapper.findComponent({ name: 'FilePond' }).exists()).toBe(false)
        expect(post).toHaveBeenCalledTimes(1)
        await wrapper.findAll('button').find(button => button.text() === 'Erneut prüfen')!.trigger('click')
        await flushPromises()
        expect(post.mock.calls.map(([, body]) => body)).toEqual([
            { handshake, filename: 'handshake.json', resolve: true },
            { handshake, filename: 'handshake.json', resolve: true },
            { handshake, filename: 'handshake.json', proof: 'proof' },
        ])
        expect(wrapper.emitted('linked')).toHaveLength(1)
    } finally { wrapper.unmount() }
})

it('retries a failed connection check and completes the already authorized creation', async () => {
    const post = vi.fn().mockResolvedValueOnce({ data: { candidate, proof: 'proof', can_link: true } })
        .mockResolvedValueOnce({ data: { connected: true, module_link: { folder: { id: 'id:module' } }, link_preparation: { status: 'initialized' } } })
    const wrapper = mountLink(post, true)
    ;(globalThis as any).axios.get = vi.fn().mockRejectedValueOnce(new Error('Verbindung nicht erreichbar'))
        .mockResolvedValueOnce({ data: { configured: true, installed: true, connected: true } })
    try {
        await (wrapper.vm as any).openLink(work, { filename: 'handshake.json', contents: handshake }, true)
        await flushPromises()
        expect(wrapper.text()).toContain('Verbindung nicht erreichbar')
        expect(post).not.toHaveBeenCalled()
        await wrapper.findAll('button').find(button => button.text() === 'Erneut prüfen')!.trigger('click')
        await flushPromises()
        expect(post.mock.calls.map(([, body]) => body)).toEqual([
            { handshake, filename: 'handshake.json', resolve: true },
            { handshake, filename: 'handshake.json', proof: 'proof' },
        ])
        expect(wrapper.emitted('linked')).toHaveLength(1)
    } finally { wrapper.unmount() }
})

it('rechecks an expired proof and retries the same work without another creation', async () => {
    const post = vi.fn().mockResolvedValueOnce({ data: { candidate, proof: 'old-proof', can_link: true } })
        .mockRejectedValueOnce({ response: { data: { message: 'Online-Datei wurde geändert.' } } })
        .mockResolvedValueOnce({ data: { candidate, proof: 'new-proof', can_link: true } })
        .mockResolvedValueOnce({ data: { connected: true, module_link: { folder: { id: 'id:module' } }, link_preparation: { status: 'initialized' } } })
    const wrapper = mountLink(post, true)
    try {
        await (wrapper.vm as any).openLink(work, { filename: 'handshake.json', contents: handshake }, true)
        await flushPromises()
        expect(wrapper.text()).toContain('Online-Datei wurde geändert.')
        expect(wrapper.emitted('linked')).toBeUndefined()
        await wrapper.findAll('button').find(button => button.text() === 'Erneut prüfen')!.trigger('click')
        await flushPromises()
        expect(post.mock.calls.map(([url, body]) => [url, body])).toEqual([
            ['/api/admin/teaching/course_works/65/dropbox/folder', { handshake, filename: 'handshake.json', resolve: true }],
            ['/api/admin/teaching/course_works/65/dropbox/folder', { handshake, filename: 'handshake.json', proof: 'old-proof' }],
            ['/api/admin/teaching/course_works/65/dropbox/folder', { handshake, filename: 'handshake.json', resolve: true }],
            ['/api/admin/teaching/course_works/65/dropbox/folder', { handshake, filename: 'handshake.json', proof: 'new-proof' }],
        ])
        expect(wrapper.emitted('linked')).toHaveLength(1)
    } finally { wrapper.unmount() }
})

it('never stores without an online proof and offers retry', async () => {
    const post = vi.fn().mockResolvedValue({ data: { candidate, can_link: true, message: 'Prüfung unvollständig' } })
    const wrapper = mountLink(post, true)
    try {
        await (wrapper.vm as any).openLink(work, { filename: 'handshake.json', contents: handshake }, true)
        await flushPromises()
        expect(post).toHaveBeenCalledTimes(1)
        expect(post.mock.calls[0][1]).toEqual({ handshake, filename: 'handshake.json', resolve: true })
        expect(wrapper.findAll('button').some(button => button.text() === 'Erneut prüfen')).toBe(true)
        expect(wrapper.emitted('linked')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('does not apply the prior creation authorization to a replacement file', async () => {
    const post = vi.fn().mockRejectedValueOnce({ response: { data: { message: 'Dropbox ist nicht erreichbar.' } } })
        .mockResolvedValueOnce({ data: { candidate, proof: 'new-proof', can_link: true } })
        .mockResolvedValueOnce({ data: { connected: true, module_link: { folder: { id: 'id:module' } }, link_preparation: { status: 'initialized' } } })
    const wrapper = mountLink(post, true)
    try {
        await (wrapper.vm as any).openLink(work, { filename: 'handshake.json', contents: handshake }, true)
        await flushPromises()
        await wrapper.findAll('button').find(button => button.text() === 'Andere Datei auswählen')!.trigger('click')
        selectHandshake(wrapper)
        await flushPromises()
        expect(post).toHaveBeenCalledTimes(2)
        expect(post.mock.calls[1][1]).toEqual({ handshake, filename: 'handshake.json', resolve: true })
        expect(wrapper.emitted('linked')).toBeUndefined()
        await wrapper.findAll('button').find(button => button.text() === 'Verknüpfen und Prüfungsordner anlegen')!.trigger('click')
        await flushPromises()
        expect(post.mock.calls[2][1]).toEqual({ handshake, filename: 'handshake.json', proof: 'new-proof' })
        expect(wrapper.emitted('linked')).toHaveLength(1)
    } finally { wrapper.unmount() }
})

it.each([
    { status: 'initialized', folder: '/Modul/Prüfung', expected: 'Prüfungsordner angelegt' },
    { status: 'partial', message: 'Lernmodul verknüpft; Durchführungstermin fehlt.', expected: 'Durchführungstermin fehlt' },
])('shows the actual folder outcome after the original creation click: $status', async result => {
    const post = vi.fn().mockResolvedValueOnce({ data: { candidate, proof: 'proof', can_link: true } })
        .mockResolvedValueOnce({ data: { connected: true, module_link: { folder: { id: 'id:module' } }, link_preparation: result } })
    const wrapper = mountLink(post, true)
    try {
        await (wrapper.vm as any).openLink(work, { filename: 'handshake.json', contents: handshake }, true)
        await flushPromises()
        expect(post).toHaveBeenCalledTimes(2)
        expect(wrapper.emitted('link-saved')).toHaveLength(1)
        if (result.status === 'partial') {
            expect(wrapper.text()).toContain(result.expected)
            expect(wrapper.emitted('linked')).toBeUndefined()
            expect(wrapper.findAll('button').some(button => button.text() === 'Prüfungsordner erneut anlegen')).toBe(true)
        } else {
            expect(wrapper.emitted('linked')).toHaveLength(1)
            expect(wrapper.find('[data-testid="work-module-inline"]').exists()).toBe(false)
        }
    } finally { wrapper.unmount() }
})

it('does not store a creation handshake when the online result is ambiguous', async () => {
    const post = vi.fn().mockResolvedValue({ data: { conflict: true, can_link: false, candidates: [candidate], message: 'Mehrere Ordner' } })
    const wrapper = mountLink(post, true)
    try {
        await (wrapper.vm as any).openLink(work, { filename: 'handshake.json', contents: handshake }, true)
        await flushPromises()
        expect(post).toHaveBeenCalledTimes(1)
        expect(post.mock.calls[0][1]).toEqual({ handshake, filename: 'handshake.json', resolve: true })
        expect(wrapper.text()).toContain('Mehrere Ordner')
        expect(wrapper.emitted('linked')).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('checks a previously selected creation handshake and still requires confirmation', async () => {
    const post = vi.fn().mockResolvedValueOnce({ data: { candidate, proof: 'proof', can_link: true, module_id: 'module' } })
        .mockResolvedValueOnce({ data: { connected: true, module_link: { folder: { id: 'id:module' } }, link_preparation: { status: 'initialized' } } })
    const wrapper = mountLink(post)
    await (wrapper.vm as any).openLink({ ...work, groups: [{ grade: '1' }], status: { creation_receipt: { id: 'request' } } }, { filename: 'handshake.json', contents: handshake })
    await flushPromises()
    expect(post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/dropbox/folder',
        { handshake, filename: 'handshake.json', resolve: true }, expect.any(Object))
    expect(wrapper.get('[data-testid="module-folder-path"]').text()).toBe('/Materialien/EMails')
    expect(wrapper.emitted('linked')).toBeUndefined()
    const confirm = wrapper.findAll('button').filter(button => button.text() === 'Verknüpfen und Prüfungsordner anlegen')
    expect(confirm).toHaveLength(1)
    await confirm[0].trigger('click')
    await flushPromises()
    expect(post).toHaveBeenCalledTimes(2)
    expect(post.mock.calls[1][1]).toEqual({ handshake, filename: 'handshake.json', proof: 'proof' })
    expect(wrapper.emitted('linked')).toHaveLength(1)
    wrapper.unmount()
})

it('opens the chooser without promising a link and confirms the shown folder with one click', async () => {
    const post = vi.fn().mockResolvedValueOnce({ data: { candidate, proof: 'proof', can_link: true, module_id: 'module' } })
        .mockResolvedValueOnce({ data: { connected: true, module_link: { folder: { id: 'id:module' } }, link_preparation: { status: 'initialized' } } })
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { configured: true, installed: true, connected: true } }), post })
    const wrapper = mount({ components: { WorkImportActions, WorkDropboxLink }, data: () => ({ work }),
        template: '<div><WorkImportActions :work="work" @link="$refs.dialog.openLink($event)" /><WorkDropboxLink ref="dialog" :course-id="2" /></div>',
    }, { global: { plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } })],
        stubs: { 'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
            'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' } },
    } })
    try {
        await flushPromises()
        const choose = wrapper.findAll('button').find(button => button.text() === 'Lernmodul auswählen')
        expect(choose).toBeDefined()
        expect(wrapper.findAll('button').some(button => button.text() === 'Verknüpfen und Prüfungsordner anlegen')).toBe(false)
        await choose!.trigger('click')
        await flushPromises()
        wrapper.findComponent({ name: 'FilePond' }).vm.$emit('updatefiles', [{ file: { name: 'handshake.json', size: handshake.length, text: async () => handshake } }])
        await flushPromises()
        expect(wrapper.get('[data-testid="module-folder-path"]').text()).toBe('/Materialien/EMails')
        const confirmations = wrapper.findAll('button').filter(button => button.text() === 'Verknüpfen und Prüfungsordner anlegen')
        expect(confirmations).toHaveLength(1)
        await confirmations[0].trigger('click')
        await flushPromises()
        expect(post).toHaveBeenCalledTimes(2)
        expect(post.mock.calls[1][1]).toEqual({ handshake, filename: 'handshake.json', proof: 'proof' })
        expect(workDropboxStates.get('7:2:65')?.module_link.folder.id).toBe('id:module')
    } finally { wrapper.unmount() }
})

it('shows each actual step while the file, online check and save are pending', async () => {
    let finishFile: (value: string) => void = () => {}
    let finishCheck: (value: unknown) => void = () => {}
    let finishSave: (value: unknown) => void = () => {}
    const post = vi.fn().mockImplementationOnce(() => new Promise(resolve => { finishCheck = resolve }))
        .mockImplementationOnce(() => new Promise(resolve => { finishSave = resolve }))
    const wrapper = mountLink(post)
    try {
        await (wrapper.vm as any).openLink(work)
        wrapper.findComponent({ name: 'FilePond' }).vm.$emit('updatefiles', [{ file: {
            name: 'handshake.json', size: handshake.length, text: () => new Promise(resolve => { finishFile = resolve }),
        } }])
        await flushPromises()
        expect(wrapper.get('[aria-label="Fortschritt der Verknüpfung"]').text()).toContain('Handshake-Datei lesen und prüfenLäuft')
        expect(post).not.toHaveBeenCalled()

        finishFile(handshake)
        await flushPromises()
        expect(wrapper.text()).toContain('Handshake-Datei lesen und prüfenErledigt')
        expect(wrapper.text()).toContain('Lernmodul in Dropbox prüfenLäuft')
        expect(wrapper.text()).toContain('Verknüpfung und Prüfungsordner speichernAusstehend')

        finishCheck({ data: { candidate, proof: 'proof', can_link: true } })
        await flushPromises()
        expect(wrapper.text()).toContain('Lernmodul in Dropbox prüfenErledigt')
        expect(wrapper.text()).toContain('Bereit zum Verknüpfen')
        await wrapper.findAll('button').find(button => button.text() === 'Verknüpfen und Prüfungsordner anlegen')!.trigger('click')
        expect(wrapper.text()).toContain('Verknüpfung und Prüfungsordner speichernLäuft')

        finishSave({ data: { module_link: { folder: { id: 'id:module' } }, connected: true, link_preparation: { status: 'initialized' } } })
        await flushPromises()
        expect(wrapper.emitted('linked')).toHaveLength(1)
    } finally { wrapper.unmount() }
})

it('accepts a handshake with a direct Dropbox folder identity', async () => {
    const direct = JSON.stringify({ schema_version: 2, module_id: 'a263b873-4f27-4167-90f0-7d94ea0f67fa', dropbox_folder_id: 'id:module' })
    const post = vi.fn().mockResolvedValue({ data: { candidate, proof: 'proof', can_link: true } })
    const wrapper = mountLink(post)
    try {
        await (wrapper.vm as any).openLink(work)
        selectHandshake(wrapper, direct)
        await flushPromises()

        expect(post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/dropbox/folder',
            { handshake: direct, filename: 'handshake.json', resolve: true }, expect.objectContaining({ timeout: 120000 }))
        expect(wrapper.get('[data-testid="module-folder-path"]').text()).toBe('/Materialien/EMails')
    } finally { wrapper.unmount() }
})

it('rejects unrelated or oversized files before any request and never browses local folders', async () => {
    const post = vi.fn()
    const wrapper = mountLink(post)
    await (wrapper.vm as any).openLink(work)
    const pond = wrapper.findComponent({ name: 'FilePond' })
    const validate = pond.props('beforeAddFile') as (item: unknown) => boolean

    expect(validate({ file: { name: 'Material.pdf', size: 100 } })).toBe(false)
    expect(validate({ file: { name: 'handshake.json', size: 4097 } })).toBe(false)
    expect(validate({ file: { name: 'handshake.json', size: handshake.length } })).toBe(true)
    expect(post).not.toHaveBeenCalled()
    expect(wrapper.find('[webkitdirectory]').exists()).toBe(false)
    wrapper.unmount()
})

it('keeps ambiguous copies visible and disables linking', async () => {
    const post = vi.fn().mockResolvedValue({ data: { conflict: true, candidates: [candidate, { ...candidate, handshake_file_id: 'id:copy', folder_path: '/Kopie' }], message: 'Mehrere Ordner' } })
    const wrapper = mountLink(post)
    await (wrapper.vm as any).openLink(work)
    selectHandshake(wrapper)
    await flushPromises()

    expect(wrapper.text()).toContain('/Kopie')
    expect(wrapper.findAll('button').find(button => button.text() === 'Verknüpfen und Prüfungsordner anlegen')!.attributes('disabled')).toBeDefined()
    expect(post).toHaveBeenCalledTimes(1)
    wrapper.unmount()
})

it('discards a delayed match when the current user changes', async () => {
    let finish: (value: unknown) => void = () => {}
    const post = vi.fn().mockImplementation(() => new Promise(resolve => { finish = resolve }))
    const wrapper = mountLink(post)
    await (wrapper.vm as any).openLink(work)
    selectHandshake(wrapper)
    await flushPromises()
    useAdminStore().config.user.id = 8
    await flushPromises()
    finish({ data: { candidate, proof: 'old', can_link: true } })
    await flushPromises()

    expect(wrapper.find('[data-testid="module-folder-path"]').exists()).toBe(false)
    expect(workDropboxStates.get('8:2:65')).toBeUndefined()
    wrapper.unmount()
})

it('clears a stale confirmation after a rejected save and offers a new online check', async () => {
    const post = vi.fn().mockResolvedValueOnce({ data: { candidate, proof: 'proof', can_link: true } })
        .mockRejectedValueOnce({ response: { data: { message: 'Online-Datei geändert' } } })
    const wrapper = mountLink(post)
    await (wrapper.vm as any).openLink(work)
    selectHandshake(wrapper)
    await flushPromises()
    await wrapper.findAll('button').find(button => button.text() === 'Verknüpfen und Prüfungsordner anlegen')!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Online-Datei geändert')
    expect(wrapper.text()).toContain('Erneut prüfen')
    expect(wrapper.text()).toContain('Verknüpfung und Prüfungsordner speichernNicht abgeschlossen')
    expect(wrapper.findAll('button').find(button => button.text() === 'Verknüpfen und Prüfungsordner anlegen')!.attributes('disabled')).toBeDefined()
    wrapper.unmount()
})

it('opens the online folder picker for historical imports without asking for a new module handshake', async () => {
    const wrapper = mountLink()
    const oldWork = { ...work, status: { folder_imported_at: '2026-10-13T12:54:00Z' } }
    try {
        await (wrapper.vm as any).openLink(oldWork)
        await flushPromises()
        const source = wrapper.findComponent({ name: 'WorkDropboxSource' })
        expect(source.exists()).toBe(true)
        expect(source.props('selectionOnly')).toBe(true)
        expect(wrapper.find('[data-testid="filepond"]').exists()).toBe(false)
        expect(wrapper.text()).toContain('verändert keine Bewertungen')
        expect((globalThis as any).axios.post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('prevents closing a historical link dialog during the final folder operation', async () => {
    const wrapper = mountLink()
    try {
        await (wrapper.vm as any).openLink({ ...work, status: { folder_imported_at: '2026-10-13T12:54:00Z' } })
        await flushPromises()
        const source = wrapper.findComponent({ name: 'WorkDropboxSource' })
        source.vm.$emit('busy', true)
        await flushPromises()
        expect(wrapper.findAll('button').at(-1)!.attributes('disabled')).toBeDefined()
        source.vm.$emit('busy', false)
        await flushPromises()
        expect(wrapper.findAll('button').at(-1)!.attributes('disabled')).toBeUndefined()
    } finally { wrapper.unmount() }
})


it('retries only the folder for a saved module and finishes without another confirmation', async () => {
    const post = vi.fn().mockResolvedValueOnce({ data: { candidate, proof: 'proof', can_link: true } })
        .mockResolvedValueOnce({ data: { connected: true, module_link: { folder: { id: 'id:module', path: '/Materialien/EMails' } },
            link_preparation: { status: 'partial', message: 'Der Prüfungsordner ist noch nicht bestätigt.' } } })
        .mockResolvedValueOnce({ data: { connected: true, module_link: { folder: { id: 'id:module', path: '/Materialien/EMails' } },
            link_preparation: { status: 'initialized' } } })
    const wrapper = mountLink(post, true)
    try {
        await (wrapper.vm as any).openLink(work, { filename: 'handshake.json', contents: handshake }, true)
        await flushPromises()
        expect(wrapper.emitted('link-saved')).toHaveLength(1)
        expect(wrapper.emitted('linked')).toBeUndefined()
        await wrapper.findAll('button').find(button => button.text() === 'Prüfungsordner erneut anlegen')!.trigger('click')
        await flushPromises()
        expect(post.mock.calls[2][1]).toEqual({ retry_preparation: true })
        expect(wrapper.emitted('linked')).toHaveLength(1)
        expect(wrapper.find('[data-testid="work-module-inline"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})


it('opens an already saved module without requesting the handshake again and retries its folder', async () => {
    const state = { connected: true, module_link: { folder: { id: 'id:module', path: '/Materialien/EMails' } } }
    const post = vi.fn().mockResolvedValue({ data: { ...state, link_preparation: { status: 'initialized' } } })
    const wrapper = mountLink(post)
    ;(globalThis as any).axios.get = vi.fn().mockResolvedValue({ data: state })
    try {
        await (wrapper.vm as any).openLink(work)
        await flushPromises()
        expect(wrapper.findComponent({ name: 'FilePond' }).exists()).toBe(false)
        expect(wrapper.text()).toContain('Aktuell verknüpft')
        expect(post).not.toHaveBeenCalled()
        await wrapper.findAll('button').find(button => button.text() === 'Prüfungsordner erneut anlegen')!.trigger('click')
        await flushPromises()
        expect(post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/dropbox/folder',
            { retry_preparation: true }, expect.any(Object))
        expect(wrapper.emitted('linked')).toHaveLength(1)
    } finally { wrapper.unmount() }
})


it.each([
    { exercise: { status: 'found', folder: { id: 'id:exam' } } },
    { exercise: { status: 'missing', reusable: 'linked' } },
])('does not offer a folder retry once its existing status is confirmed: %j', async ({ exercise }) => {
    const linked = { connected: true, module_link: { folder: { id: 'id:module', path: '/Materialien/EMails' } } }
    const post = vi.fn()
    const wrapper = mountLink(post, true)
    ;(globalThis as any).axios.get = vi.fn().mockResolvedValueOnce({ data: linked }).mockResolvedValueOnce({ data: { ...linked, exercise } })
    try {
        await (wrapper.vm as any).openLink(work, null, true)
        await flushPromises()
        expect((globalThis as any).axios.get).toHaveBeenCalledTimes(2)
        expect((globalThis as any).axios.get.mock.calls[1][1].params).toEqual({ include_exercise: 1, refresh: 1 })
        expect(wrapper.findAll('button').some(button => button.text() === 'Prüfungsordner erneut anlegen')).toBe(false)
        expect(wrapper.emitted('linked')).toHaveLength(1)
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it.each([
    { exercise: { status: 'missing' } },
    { exercise: { status: 'missing', reusable: 'empty' } },
    { exercise: { status: 'unknown' } },
])('offers a folder retry when an existing module folder is not confirmed: %j', async ({ exercise }) => {
    const linked = { connected: true, module_link: { folder: { id: 'id:module', path: '/Materialien/EMails' } } }
    const post = vi.fn()
    const wrapper = mountLink(post, true)
    ;(globalThis as any).axios.get = vi.fn().mockResolvedValueOnce({ data: linked }).mockResolvedValueOnce({ data: { ...linked, exercise } })
    try {
        await (wrapper.vm as any).openLink(work, null, true)
        await flushPromises()
        expect(wrapper.text()).toContain('Prüfungsordner ist noch nicht bestätigt')
        expect(wrapper.findAll('button').some(button => button.text() === 'Prüfungsordner erneut anlegen')).toBe(true)
        expect(wrapper.emitted('linked')).toBeUndefined()
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})


it('does not claim a saved module or completed folder when the response has no confirmed link', async () => {
    const post = vi.fn().mockResolvedValueOnce({ data: { candidate, proof: 'proof', can_link: true } })
        .mockResolvedValueOnce({ data: { connected: true, module_link: null, link_preparation: { status: 'initialized' } } })
    const wrapper = mountLink(post, true)
    try {
        await (wrapper.vm as any).openLink(work, { filename: 'handshake.json', contents: handshake }, true)
        await flushPromises()
        expect(wrapper.text()).toContain('Lernmodul-Verknüpfung konnte nicht bestätigt werden')
        expect(wrapper.emitted('link-saved')).toBeUndefined()
        expect(wrapper.emitted('linked')).toBeUndefined()
    } finally { wrapper.unmount() }
})
