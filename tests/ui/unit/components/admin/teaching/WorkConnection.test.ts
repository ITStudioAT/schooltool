import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import WorkImportActions from '@/pages/admin/teaching/overview/components/WorkImportActions.vue'
import WorkContextCopy from '@/pages/admin/teaching/overview/components/WorkContextCopy.vue'
import WorkFolderStatus from '@/pages/admin/teaching/overview/components/WorkFolderStatus.vue'
import { clearWorkDropboxStates, workDropboxStates } from '@/helpers/workDropbox'
import { useAdminStore } from '@/stores/admin/AdminStore'

const work = { id: 65, teaching_course_id: 2, title: 'E-Mails', type: 'A5' }
const saved = { connected: true, folder: null, link_version: 'a'.repeat(64), module_link: {
    module_id: 'a263b873-4f27-4167-90f0-7d94ea0f67fa', folder: { id: 'id:module', name: 'Modul', path: '/Materialien/Lerninhalte/Modul' },
} }
const unlinked = { connected: true, folder: null, module_link: null, link_version: 'b'.repeat(64) }

function mountConnection(post = vi.fn(), state: any = saved, currentWork: any = work) {
    vi.stubGlobal('visualViewport', { width: 1024, height: 768, offsetLeft: 0, offsetTop: 0, addEventListener: vi.fn(), removeEventListener: vi.fn() })
    workDropboxStates.set('7:2:65', state)
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    vi.stubGlobal('axios', { post, get: vi.fn().mockImplementation((url: string) => Promise.resolve({ data: url.includes('/folders')
        ? { root: state.module_link?.folder || state.folder, current: state.module_link?.folder || state.folder, entries: [], cursor: null, link_version: state.link_version } : state })) })
    const stubs = {
        'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
        'v-dialog': { template: '<section role="dialog"><slot /></section>' },
    }
    const wrapper = mount(WorkImportActions, { props: { work: currentWork, linkOnly: true }, attachTo: document.body, global: { stubs,
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } })],
    } })
    const options = { global: { plugins: [wrapper.vm.$pinia], stubs } }
    const context = mount(WorkContextCopy, { props: { work: currentWork, course: { id: 2, title: 'INF' } }, ...options })
    const status = mount(WorkFolderStatus, { props: { work: currentWork, size: 30 }, ...options })
    const originalUnmount = wrapper.unmount.bind(wrapper)
    wrapper.unmount = () => { context.unmount(); status.unmount(); originalUnmount() }
    return { wrapper, context, status, writeText }
}

function button(wrapper: ReturnType<typeof mountConnection>['wrapper'], text: string) {
    return wrapper.findAll('button').find(item => item.text() === text)!
}

async function chooseUnlink(wrapper: ReturnType<typeof mountConnection>['wrapper']) {
    await wrapper.get('[role="button"]').trigger('click')
    await flushPromises()
    if (workDropboxStates.get('7:2:65')?.module_link) {
        expect(wrapper.get('[aria-label="Dropbox-Ordnerpfad"]').text()).toBe(saved.module_link.folder.path)
    }
    await button(wrapper, 'Verbindung trennen').trigger('click')
    await flushPromises()
}

afterEach(() => { clearWorkDropboxStates(); vi.restoreAllMocks(); vi.unstubAllGlobals(); document.body.innerHTML = '' })

it('keeps the folder dialog outside the chain button and treats its path as ordinary text', async () => {
    const post = vi.fn()
    const { wrapper, status } = mountConnection(post)
    try {
        await flushPromises()
        await status.get('[role="button"]').trigger('click')
        const dialog = status.get('[role="dialog"]')
        expect(dialog.element.closest('[role="button"]')).toBeNull()
        const path = dialog.get('[aria-label="Dropbox-Ordnerpfad"]')
        const get = vi.mocked(axios.get)
        const reads = get.mock.calls.length
        await path.trigger('click')
        await path.trigger('keydown.enter')
        expect(get).toHaveBeenCalledTimes(reads)
        expect(post).not.toHaveBeenCalled()
        await button(status, 'Schließen').trigger('click')
        expect(status.find('[role="dialog"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it.each([
    { status: { folder_imported_at: '2026-10-13T12:54:00Z' } },
    { status: { dispatch_logs: [{ sha256: 'c'.repeat(64) }] } },
    { groups: [{ grades: { 101: 0 } }] },
])('offers optional folder linking for existing work without changing completed progress: %j', async progress => {
    const { wrapper, status } = mountConnection(vi.fn(), unlinked, { ...work, ...progress })
    try {
        await flushPromises()
        expect(button(wrapper, 'Lernmodul auswählen')).toBeUndefined()
        expect(button(wrapper, 'Ordner verknüpfen')).toBeDefined()
        await button(wrapper, 'Ordner verknüpfen').trigger('click')
        expect(wrapper.emitted('link')).toEqual([[{ ...work, ...progress }]])
        expect(status.find('[role="img"]').exists()).toBe(false)
        workDropboxStates.set('7:2:65', saved)
        await flushPromises()
        expect(status.get('[role="img"]').attributes('color')).toBe('success')
        expect(status.get('[role="button"]').exists()).toBe(true)
    } finally { wrapper.unmount() }
})

it('offers optional folder linking when the server confirms an older import without local progress metadata', async () => {
    const { wrapper, status } = mountConnection(vi.fn(), { ...unlinked, has_import: true })
    try {
        await flushPromises()
        expect(button(wrapper, 'Lernmodul auswählen')).toBeUndefined()
        expect(button(wrapper, 'Ordner verknüpfen')).toBeDefined()
        expect(status.find('[role="img"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('opens the saved folder dialog and unlinks only its work while updating context and status', async () => {
    const post = vi.fn().mockResolvedValue({ data: unlinked })
    const { wrapper, context, status, writeText } = mountConnection(post)
    try {
        await flushPromises()
        expect(button(wrapper, 'Lernmodul auswählen')).toBeUndefined()
        expect(button(wrapper, 'Verbindung')).toBeUndefined()
        expect(button(wrapper, 'Verbindung trennen')).toBeUndefined()
        expect(status.get('[role="button"]').attributes('tabindex')).toBe('0')
        expect(status.get('[role="img"]').attributes('color')).toBe('success')
        await context.get('button').trigger('click')
        await flushPromises()
        expect(writeText.mock.calls[0][0]).toContain('Ordner: verknüpft – Dropbox: /Materialien/Lerninhalte/Modul')

        await chooseUnlink(status)

        expect(post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/dropbox/folder', { unlink: true, link_version: saved.link_version }, { timeout: 120000 })
        expect(button(wrapper, 'Verbindung')).toBeUndefined()
        expect(button(wrapper, 'Lernmodul auswählen')).toBeDefined()
        expect(status.get('[role="img"]').attributes('color')).toBe('error')
        await context.get('button').trigger('click')
        await flushPromises()
        expect(writeText.mock.calls[1][0]).toContain('Ordner: nicht verknüpft')
        expect(writeText.mock.calls[1][0]).not.toContain('Modul-ID:')
    } finally { wrapper.unmount() }
})

it('keeps a saved assignment and its copied context after a rejected unlink', async () => {
    const post = vi.fn().mockRejectedValue({ response: { data: { message: 'Zuordnung wurde geändert.' } } })
    const { wrapper, context, status, writeText } = mountConnection(post)
    try {
        await flushPromises()
        await chooseUnlink(status)
        expect(workDropboxStates.get('7:2:65')).toBe(saved)
        expect(button(wrapper, 'Lernmodul auswählen')).toBeUndefined()
        expect(status.get('[role="alert"]').text()).toContain('Zuordnung wurde geändert.')
        await context.get('button').trigger('click')
        await flushPromises()
        expect(writeText.mock.calls[0][0]).toContain('Ordner: verknüpft')
    } finally { wrapper.unmount() }
})

it('keeps waiting for an unlink response after 45 seconds', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    const post = vi.fn((_url, _body, { timeout }) => new Promise((resolve, reject) => {
        const deadline = setTimeout(() => reject({ code: 'ECONNABORTED' }), timeout)
        setTimeout(() => {
            clearTimeout(deadline)
            resolve({ data: unlinked })
        }, 52600)
    }))
    const { wrapper, status } = mountConnection(post)
    try {
        await flushPromises()
        await chooseUnlink(status)
        await vi.advanceTimersByTimeAsync(45000)
        expect(status.get('[role="status"]').text()).toContain('Dropbox-Verbindung wird geprüft und getrennt')
        expect(button(status, 'Verbindung trennen').attributes('disabled')).toBeDefined()
        expect(status.find('[role="alert"]').exists()).toBe(false)

        await vi.advanceTimersByTimeAsync(7600)
        expect(status.find('[role="dialog"]').exists()).toBe(false)
        expect(button(wrapper, 'Lernmodul auswählen')).toBeDefined()
        expect(post).toHaveBeenCalledOnce()
    } finally {
        wrapper.unmount()
        vi.useRealTimers()
    }
})

it('adopts a completed unlink on status check when the original response timed out', async () => {
    const post = vi.fn().mockRejectedValue({ code: 'ECONNABORTED' })
    const { wrapper, status } = mountConnection(post)
    try {
        await flushPromises()
        await chooseUnlink(status)
        expect(status.get('[role="alert"]').text()).toContain('Der Server hat noch nicht geantwortet.')
        vi.mocked(axios.get).mockResolvedValueOnce({ data: unlinked })

        await button(status, 'LF-Stand prüfen').trigger('click')
        await flushPromises()

        expect(status.find('[role="dialog"]').exists()).toBe(false)
        expect(button(wrapper, 'Lernmodul auswählen')).toBeDefined()
        expect(post).toHaveBeenCalledOnce()
    } finally { wrapper.unmount() }
})

it('does not unlink merely by opening the dialog and ignores an uncommitted candidate', async () => {
    const post = vi.fn()
    const { wrapper, status } = mountConnection(post, { ...unlinked, candidate: saved.module_link.folder })
    try {
        await flushPromises()
        expect(button(wrapper, 'Lernmodul auswählen')).toBeDefined()
        expect(button(wrapper, 'Verbindung')).toBeUndefined()
        workDropboxStates.set('7:2:65', saved)
        await flushPromises()
        const get = vi.mocked((globalThis as any).axios.get)
        get.mockClear()
        await status.get('[role="button"]').trigger('click')
        await flushPromises()
        expect(status.get('[aria-label="Dropbox-Ordnerpfad"]').text()).toBe(saved.module_link.folder.path)
        expect(get).not.toHaveBeenCalled()
        expect(post).not.toHaveBeenCalled()
        await button(status, 'Schließen').trigger('click')
        expect(status.find('[role="dialog"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('blocks known assessments and only rechecks on request before allowing an explicit unlink', async () => {
    const post = vi.fn().mockResolvedValue({ data: unlinked })
    const { wrapper, status } = mountConnection(post, { ...saved, exercise: { status: 'found', folder: { id: 'id:exam' } } })
    try {
        await flushPromises()
        const get = vi.mocked(axios.get)
        get.mockClear()
        await status.get('[role="button"]').trigger('click')
        expect(get).not.toHaveBeenCalled()
        expect(status.text()).toContain('Zuerst „Leistungsfeststellung löschen“ bestätigen.')
        expect(button(status, 'Verbindung trennen').attributes('disabled')).toBeDefined()
        expect(post).not.toHaveBeenCalled()

        get.mockResolvedValueOnce({ data: { ...saved, unlink_status: { status: 'unknown', message: 'Die LF-Löschung ist noch nicht bestätigt.' } } })
        await button(status, 'LF-Stand prüfen').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/dropbox', { timeout: 120000, params: { include_unlink_status: 1 } })
        expect(status.text()).toContain('Die LF-Löschung ist noch nicht bestätigt.')
        expect(button(status, 'Verbindung trennen').attributes('disabled')).toBeDefined()

        get.mockResolvedValueOnce({ data: { ...saved, unlink_status: { status: 'available' } } })
        await button(status, 'LF-Stand prüfen').trigger('click')
        await flushPromises()
        expect(button(status, 'Verbindung trennen').attributes('disabled')).toBeUndefined()
        expect(post).not.toHaveBeenCalled()
        await button(status, 'Verbindung trennen').trigger('click')
        await flushPromises()
        expect(post).toHaveBeenCalledOnce()
    } finally { wrapper.unmount() }
})

it('supports legacy folder associations and starts unlinked again after a fresh server response', async () => {
    const { wrapper } = mountConnection(vi.fn(), { ...saved, module_link: null, folder: { id: 'id:old', name: 'Altordner', path: '/Altordner' } })
    try {
        await flushPromises()
        expect(button(wrapper, 'Verbindung')).toBeUndefined()
        expect(button(wrapper, 'Lernmodul auswählen')).toBeUndefined()
        clearWorkDropboxStates()
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: unlinked }) })
        wrapper.unmount()
        const fresh = mountConnection(vi.fn(), unlinked).wrapper
        try {
            await flushPromises()
            expect(button(fresh, 'Lernmodul auswählen')).toBeDefined()
            expect(button(fresh, 'Verbindung')).toBeUndefined()
        } finally { fresh.unmount() }
    } finally { if (wrapper.exists()) wrapper.unmount() }
})

it('does not apply a delayed unlink response to a newly selected user', async () => {
    let finish: (value: unknown) => void = () => {}
    const post = vi.fn().mockImplementation(() => new Promise(resolve => { finish = resolve }))
    const { wrapper, status } = mountConnection(post)
    try {
        await flushPromises()
        await chooseUnlink(status)
        useAdminStore().config.user.id = 8
        await flushPromises()
        finish({ data: unlinked })
        await flushPromises()
        expect(workDropboxStates.get('7:2:65')).toBe(saved)
        expect(workDropboxStates.get('8:2:65')).not.toBe(unlinked)
    } finally { wrapper.unmount() }
})


it.each([
    { link_preparation: { status: 'partial' } },
    { exercise: { status: 'missing', reusable: 'empty' } },
])('offers an existing work a folder retry without requesting another handshake', async preparation => {
    const { wrapper } = mountConnection(vi.fn(), { ...saved, ...preparation })
    try {
        await flushPromises()
        expect(button(wrapper, 'Lernmodul auswählen')).toBeUndefined()
        await button(wrapper, 'Prüfungsordner erneut anlegen').trigger('click')
        expect(wrapper.emitted('link')).toEqual([[work]])
    } finally { wrapper.unmount() }
})

it.each([
    { exercise: { status: 'unknown' } },
    { exercise: { status: 'missing', reusable: 'linked' } },
    { exercise: { status: 'found' }, link_preparation: { status: 'partial' } },
])('does not offer folder recreation without a current failure or a missing folder', async status => {
    const { wrapper } = mountConnection(vi.fn(), { ...saved, ...status })
    try {
        await flushPromises()
        expect(button(wrapper, 'Prüfungsordner erneut anlegen')).toBeUndefined()
    } finally { wrapper.unmount() }
})
