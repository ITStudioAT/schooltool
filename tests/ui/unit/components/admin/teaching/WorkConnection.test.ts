import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VBtn, VIcon, VList, VListItem, VMenu } from 'vuetify/components'
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

function mountConnection(post = vi.fn(), state: any = saved) {
    vi.stubGlobal('visualViewport', { width: 1024, height: 768, offsetLeft: 0, offsetTop: 0, addEventListener: vi.fn(), removeEventListener: vi.fn() })
    workDropboxStates.set('7:2:65', state)
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    vi.stubGlobal('axios', { post, get: vi.fn().mockResolvedValue({ data: state }) })
    const wrapper = mount(WorkImportActions, { props: { work, linkOnly: true }, attachTo: document.body, global: { components: {
        'v-menu': VMenu, 'v-list': VList, 'v-list-item': VListItem, 'v-btn': VBtn, 'v-icon': VIcon,
    }, stubs: {
        'v-menu': false, VMenu: false, 'v-list': false, VList: false, 'v-list-item': false, VListItem: false,
        'v-btn': false, VBtn: false, 'v-icon': false, VIcon: false,
    }, plugins: [
        createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } }),
        createVuetify({ components: { VBtn, VIcon, VList, VListItem, VMenu } }),
    ] } })
    const options = { global: { plugins: [wrapper.vm.$pinia, createVuetify({ components: { VBtn, VMenu, VList, VListItem } })],
        components: { 'v-btn': VBtn, 'v-menu': VMenu, 'v-list': VList, 'v-list-item': VListItem },
        stubs: { 'v-btn': false, VBtn: false, 'v-menu': false, VMenu: false, 'v-list': false, VList: false, 'v-list-item': false, VListItem: false },
    } }
    const context = mount(WorkContextCopy, { props: { work, course: { id: 2, title: 'INF' } }, ...options })
    const status = mount(WorkFolderStatus, { props: { work, size: 30 }, ...options })
    const originalUnmount = wrapper.unmount.bind(wrapper)
    wrapper.unmount = () => { context.unmount(); status.unmount(); originalUnmount() }
    return { wrapper, context, status, writeText }
}

function button(wrapper: ReturnType<typeof mountConnection>['wrapper'], text: string) {
    return wrapper.findAll('button').find(item => item.text() === text)!
}

async function chooseUnlink(wrapper: ReturnType<typeof mountConnection>['wrapper']) {
    await button(wrapper, 'Verbindung').trigger('click')
    await flushPromises()
    if (workDropboxStates.get('7:2:65')?.module_link) {
        expect(wrapper.getComponent(VList).get('[aria-label="Dropbox-Ordnerpfad"]').text()).toBe(saved.module_link.folder.path)
    }
    await wrapper.getComponent(VListItem).trigger('click')
    await flushPromises()
}

afterEach(() => { clearWorkDropboxStates(); vi.restoreAllMocks(); vi.unstubAllGlobals(); document.body.innerHTML = '' })

it('replaces Verknüpfen only for a saved link and unlinks through the separate menu while updating context and passive status', async () => {
    const post = vi.fn().mockResolvedValue({ data: unlinked })
    const { wrapper, context, status, writeText } = mountConnection(post)
    try {
        await flushPromises()
        expect(button(wrapper, 'Verknüpfen')).toBeUndefined()
        expect(button(wrapper, 'Verbindung')).toBeDefined()
        expect(status.attributes('tabindex')).toBeUndefined()
        expect(status.attributes('color')).toBe('success')
        await context.get('button').trigger('click')
        await flushPromises()
        expect(writeText.mock.calls[0][0]).toContain('Ordner: verknüpft – Dropbox: /Materialien/Lerninhalte/Modul')

        await chooseUnlink(wrapper)

        expect(post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/dropbox/folder', { unlink: true, link_version: saved.link_version }, { timeout: 45000 })
        expect(button(wrapper, 'Verbindung')).toBeUndefined()
        expect(button(wrapper, 'Verknüpfen')).toBeDefined()
        expect(status.attributes('color')).toBe('error')
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
        await chooseUnlink(wrapper)
        expect(workDropboxStates.get('7:2:65')).toBe(saved)
        expect(button(wrapper, 'Verknüpfen')).toBeUndefined()
        expect(wrapper.get('[role="alert"]').text()).toContain('Zuordnung wurde geändert.')
        await context.get('button').trigger('click')
        await flushPromises()
        expect(writeText.mock.calls[0][0]).toContain('Ordner: verknüpft')
    } finally { wrapper.unmount() }
})

it('does not unlink by opening or closing the menu and ignores an uncommitted candidate', async () => {
    const post = vi.fn()
    const { wrapper } = mountConnection(post, { ...unlinked, candidate: saved.module_link.folder })
    try {
        await flushPromises()
        expect(button(wrapper, 'Verknüpfen')).toBeDefined()
        expect(button(wrapper, 'Verbindung')).toBeUndefined()
        workDropboxStates.set('7:2:65', saved)
        await flushPromises()
        await button(wrapper, 'Verbindung').trigger('click')
        await flushPromises()
        expect(post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('supports legacy folder associations and starts unlinked again after a fresh server response', async () => {
    const { wrapper } = mountConnection(vi.fn(), { ...saved, module_link: null, folder: { id: 'id:old', name: 'Altordner', path: '/Altordner' } })
    try {
        await flushPromises()
        expect(button(wrapper, 'Verbindung')).toBeDefined()
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
    const { wrapper } = mountConnection(post)
    try {
        await flushPromises()
        await chooseUnlink(wrapper)
        useAdminStore().config.user.id = 8
        await flushPromises()
        finish({ data: unlinked })
        await flushPromises()
        expect(workDropboxStates.get('7:2:65')).toBe(saved)
        expect(workDropboxStates.get('8:2:65')).not.toBe(unlinked)
    } finally { wrapper.unmount() }
})
