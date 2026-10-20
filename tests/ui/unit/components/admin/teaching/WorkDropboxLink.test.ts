import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import WorkDropboxLink from '@/pages/admin/teaching/overview/components/WorkDropboxLink.vue'
import { clearWorkDropboxStates, workDropboxStates } from '@/helpers/workDropbox'
import { useAdminStore } from '@/stores/admin/AdminStore'

vi.mock('vue-filepond/dist/vue-filepond.js', () => ({ default: () => ({
    name: 'FilePond', props: ['beforeAddFile', 'disabled'], emits: ['updatefiles'], template: '<div data-testid="filepond" />',
}) }))

const work = { id: 65, teaching_course_id: 2, title: 'E-Mails' }
const handshake = '{"schema_version":1,"module_id":"a263b873-4f27-4167-90f0-7d94ea0f67fa"}'
const candidate = { folder_id: 'id:module', folder_path: '/Materialien/EMails', handshake_file_id: 'id:handshake' }

function mountLink(post = vi.fn()) {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { configured: true, installed: true, connected: true } }), post })
    return mount(WorkDropboxLink, {
        props: { courseId: 2 },
        global: { plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } })],
            stubs: { 'v-dialog': { template: '<div><slot /></div>' }, 'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' } },
        },
    })
}

function selectHandshake(wrapper: ReturnType<typeof mountLink>, text = handshake, name = 'handshake.json') {
    wrapper.findComponent({ name: 'FilePond' }).vm.$emit('updatefiles', [{ file: { name, size: text.length, text: async () => text } }])
}

afterEach(() => { clearWorkDropboxStates(); vi.unstubAllGlobals() })

it('uses FilePond for one file and requires explicit linking after the online path is shown', async () => {
    const post = vi.fn().mockResolvedValueOnce({ data: { candidate, proof: 'proof', can_link: true, module_id: 'module', message: 'Online geprüft' } })
        .mockResolvedValueOnce({ data: { module_link: { folder: { id: 'id:module' } }, connected: true } })
    const wrapper = mountLink(post)
    await (wrapper.vm as any).openLink(work)
    selectHandshake(wrapper)
    await flushPromises()

    expect(post).toHaveBeenCalledTimes(1)
    expect(post.mock.calls[0][1]).toEqual({ handshake, filename: 'handshake.json', resolve: true })
    expect(wrapper.get('[data-testid="module-folder-path"]').text()).toBe('/Materialien/EMails')
    expect(workDropboxStates.get('7:2:65')?.module_link).toBeUndefined()
    await wrapper.findAll('button').find(button => button.text() === 'Verknüpfen')!.trigger('click')
    await flushPromises()
    expect(post.mock.calls[1][1]).toEqual({ handshake, filename: 'handshake.json', proof: 'proof' })
    expect(workDropboxStates.get('7:2:65')?.module_link.folder.id).toBe('id:module')
    expect(post.mock.calls.every(([url]) => !url.includes('/import'))).toBe(true)
    wrapper.unmount()
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
    expect(wrapper.findAll('button').find(button => button.text() === 'Verknüpfen')!.attributes('disabled')).toBeDefined()
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
    await wrapper.findAll('button').find(button => button.text() === 'Verknüpfen')!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Online-Datei geändert')
    expect(wrapper.text()).toContain('Erneut prüfen')
    expect(wrapper.findAll('button').find(button => button.text() === 'Verknüpfen')!.attributes('disabled')).toBeDefined()
    wrapper.unmount()
})
