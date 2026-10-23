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

it.each([
    { status: { folder_imported_at: '2026-10-13T12:54:00Z' } },
    { status: { dispatch_logs: [{ sha256: 'c'.repeat(64) }] } },
    { groups: [{ grades: { 101: 0 } }] },
])('does not require a new link for existing work with progress: %j', async progress => {
    const { wrapper, status } = mountConnection(vi.fn(), unlinked, { ...work, ...progress })
    try {
        await flushPromises()
        expect(button(wrapper, 'Verknüpfen')).toBeUndefined()
        expect(status.find('[role="img"]').exists()).toBe(false)
        workDropboxStates.set('7:2:65', saved)
        await flushPromises()
        expect(status.get('[role="img"]').attributes('color')).toBe('success')
        expect(status.attributes('role')).toBe('button')
    } finally { wrapper.unmount() }
})

it('does not require a new link when the server confirms an older import without local progress metadata', async () => {
    const { wrapper, status } = mountConnection(vi.fn(), { ...unlinked, has_import: true })
    try {
        await flushPromises()
        expect(button(wrapper, 'Verknüpfen')).toBeUndefined()
        expect(status.find('[role="img"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('opens the saved folder dialog and unlinks only its work while updating context and status', async () => {
    const post = vi.fn().mockResolvedValue({ data: unlinked })
    const { wrapper, context, status, writeText } = mountConnection(post)
    try {
        await flushPromises()
        expect(button(wrapper, 'Verknüpfen')).toBeUndefined()
        expect(button(wrapper, 'Verbindung')).toBeUndefined()
        expect(button(wrapper, 'Verbindung trennen')).toBeUndefined()
        expect(status.attributes('tabindex')).toBe('0')
        expect(status.get('[role="img"]').attributes('color')).toBe('success')
        await context.get('button').trigger('click')
        await flushPromises()
        expect(writeText.mock.calls[0][0]).toContain('Ordner: verknüpft – Dropbox: /Materialien/Lerninhalte/Modul')

        await chooseUnlink(status)

        expect(post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/dropbox/folder', { unlink: true, link_version: saved.link_version }, { timeout: 45000 })
        expect(button(wrapper, 'Verbindung')).toBeUndefined()
        expect(button(wrapper, 'Verknüpfen')).toBeDefined()
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
        expect(button(wrapper, 'Verknüpfen')).toBeUndefined()
        expect(status.get('[role="alert"]').text()).toContain('Zuordnung wurde geändert.')
        await context.get('button').trigger('click')
        await flushPromises()
        expect(writeText.mock.calls[0][0]).toContain('Ordner: verknüpft')
    } finally { wrapper.unmount() }
})

it('does not unlink merely by opening the dialog and ignores an uncommitted candidate', async () => {
    const post = vi.fn()
    const { wrapper, status } = mountConnection(post, { ...unlinked, candidate: saved.module_link.folder })
    try {
        await flushPromises()
        expect(button(wrapper, 'Verknüpfen')).toBeDefined()
        expect(button(wrapper, 'Verbindung')).toBeUndefined()
        workDropboxStates.set('7:2:65', saved)
        await flushPromises()
        await status.get('[role="button"]').trigger('click')
        await flushPromises()
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('supports legacy folder associations and starts unlinked again after a fresh server response', async () => {
    const { wrapper } = mountConnection(vi.fn(), { ...saved, module_link: null, folder: { id: 'id:old', name: 'Altordner', path: '/Altordner' } })
    try {
        await flushPromises()
        expect(button(wrapper, 'Verbindung')).toBeUndefined()
        expect(button(wrapper, 'Verknüpfen')).toBeUndefined()
        clearWorkDropboxStates()
        vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: unlinked }) })
        wrapper.unmount()
        const fresh = mountConnection(vi.fn(), unlinked).wrapper
        try {
            await flushPromises()
            expect(button(fresh, 'Verknüpfen')).toBeDefined()
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
