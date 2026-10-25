import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import WorkFolderBrowser from '@/pages/admin/teaching/overview/components/WorkFolderBrowser.vue'
import { clearWorkDropboxStates, workDropboxStates } from '@/helpers/workDropbox'

const work = { id: 65, teaching_course_id: 2, title: 'E-Mails' }
const version = 'a'.repeat(64)
const folder = { id: 'id:root', name: 'Testordner', path: '/Materialien/Testordner' }
const saved = { connected: true, folder, module_link: null, link_version: version, exercise: null }
const child = { id: 'id:child', name: 'Angaben', type: 'folder' }
const file = { id: 'id:pdf', name: 'Aufgabe.pdf', type: 'file', size: 123 }
const listing = { root: folder, current: folder, entries: [file, child], cursor: null, link_version: version }

function mountBrowser(get = vi.fn().mockImplementation((url: string) => Promise.resolve({ data: url.includes('/folders') ? listing : saved })), post = vi.fn(), props = {}) {
    vi.stubGlobal('axios', { get, post })
    workDropboxStates.set('7:2:65', saved)
    return mount(WorkFolderBrowser, { props: { work, ...props }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } })],
        stubs: {
            'v-dialog': { name: 'VDialog', props: { persistent: Boolean }, template: '<section role="dialog"><slot /></section>' },
            'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
            'v-list-item': { props: ['title', 'link', 'disabled'], emits: ['click'], template: '<button v-if="link" :disabled="disabled" @click="$emit(\'click\')">{{ title }}</button><div v-else>{{ title }}</div>' },
        },
    } })
}

afterEach(() => { clearWorkDropboxStates(); vi.unstubAllGlobals() })

it('opens a persistent read-only listing, navigates folders and exposes only unlink and close as mutations', async () => {
    const get = vi.fn().mockResolvedValueOnce({ data: saved }).mockResolvedValueOnce({ data: listing })
        .mockResolvedValueOnce({ data: { ...listing, current: { ...folder, id: child.id, name: child.name }, entries: [file] } })
    const post = vi.fn()
    const wrapper = mountBrowser(get, post)
    await (wrapper.vm as any).open()
    await flushPromises()

    expect(wrapper.getComponent({ name: 'VDialog' }).props('persistent')).toBe(true)
    expect(wrapper.text()).toContain('Aufgabe.pdf')
    expect(wrapper.findAll('input, textarea, [contenteditable="true"]').length).toBe(0)
    await wrapper.findAll('button').find(button => button.text() === 'Angaben')!.trigger('click')
    await flushPromises()
    expect(get.mock.calls[2][1].params).toEqual({ contents: 1, folder_id: child.id })
    expect(wrapper.get('[aria-label="Ordnernavigation"]').text()).toContain('Angaben')
    expect(post).not.toHaveBeenCalled()
    await wrapper.findAll('button').find(button => button.text() === 'Schließen')!.trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    wrapper.unmount()
})

it('uses the existing version-bound unlink and leaves Dropbox files untouched', async () => {
    const post = vi.fn().mockResolvedValue({ data: { connected: true, folder: null, module_link: null, link_version: 'b'.repeat(64) } })
    const wrapper = mountBrowser(undefined, post)
    await (wrapper.vm as any).open()
    await flushPromises()
    await wrapper.findAll('button').find(button => button.text() === 'Verbindung trennen')!.trigger('click')
    await flushPromises()
    expect(post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/dropbox/folder', { unlink: true, link_version: version }, { timeout: 45000 })
    expect(workDropboxStates.get('7:2:65')?.folder).toBeNull()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    wrapper.unmount()
})

it('does not display late responses after switching to another work', async () => {
    let finish: (value: any) => void = () => {}
    const get = vi.fn().mockResolvedValueOnce({ data: saved }).mockImplementationOnce(() => new Promise(resolve => { finish = resolve }))
    const wrapper = mountBrowser(get)
    const opening = (wrapper.vm as any).open()
    await flushPromises()
    await wrapper.setProps({ work: { ...work, id: 66 } })
    finish({ data: listing })
    await opening
    await flushPromises()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Aufgabe.pdf')
    wrapper.unmount()
})

it('loads additional pages and keeps known exercise detection when refreshing the connection', async () => {
    const get = vi.fn().mockResolvedValueOnce({ data: saved }).mockResolvedValueOnce({ data: { ...listing, cursor: 'next' } })
        .mockResolvedValueOnce({ data: { ...listing, entries: [file, { ...file, id: 'id:second', name: 'Zweite.pdf' }] } })
    const wrapper = mountBrowser(get)
    const exercise = { status: 'found', folder: { id: 'id:exercise', name: 'LF' } }
    workDropboxStates.set('7:2:65', { ...saved, exercise })
    await (wrapper.vm as any).open()
    await flushPromises()
    expect(workDropboxStates.get('7:2:65')?.exercise).toEqual(exercise)
    await wrapper.findAll('button').find(button => button.text() === 'Weitere Einträge laden')!.trigger('click')
    await flushPromises()
    expect(get.mock.calls[2][1].params.cursor).toBe('next')
    expect(wrapper.text().match(/Aufgabe.pdf/g)?.length).toBe(1)
    expect(wrapper.text()).toContain('Zweite.pdf')
    wrapper.unmount()
})

it('requests the confirmed exercise folder when opened from the LF name', async () => {
    const get = vi.fn().mockResolvedValueOnce({ data: saved }).mockResolvedValueOnce({ data: listing })
    const wrapper = mountBrowser(get, undefined, { exercise: true, date: '2026-10-23' })
    await (wrapper.vm as any).open()
    expect(get.mock.calls[1][1].params).toEqual({ contents: 1, exercise: 1, date: '2026-10-23' })
    wrapper.unmount()
})

it('opens the learning module explicitly instead of following its exercise automatically', async () => {
    const get = vi.fn().mockResolvedValueOnce({ data: saved }).mockResolvedValueOnce({ data: listing })
    const wrapper = mountBrowser(get, undefined, { module: true })
    await (wrapper.vm as any).open()
    expect(get.mock.calls[0][1].params).toEqual({ include_folder_path: 1 })
    expect(get.mock.calls[1][1].params).toEqual({ contents: 1, module: 1 })
    expect(wrapper.text()).toContain('Lernmodulordner · E-Mails')
    wrapper.unmount()
})

it('passes the displayed group date when opening the ordinary connection', async () => {
    const get = vi.fn().mockResolvedValueOnce({ data: saved }).mockResolvedValueOnce({ data: listing })
    const wrapper = mountBrowser(get, undefined, { date: '2026-10-23' })
    await (wrapper.vm as any).open()
    expect(get.mock.calls[1][1].params).toEqual({ contents: 1, date: '2026-10-23' })
    wrapper.unmount()
})

it('opens a direct section and leaves disconnecting to the connection dialog', async () => {
    const get = vi.fn().mockResolvedValueOnce({ data: saved }).mockResolvedValueOnce({ data: listing })
    const wrapper = mountBrowser(get, undefined, { exercise: true, date: '2026-10-23', section: 'Bewertungskriterien' })
    await (wrapper.vm as any).open()
    expect(get.mock.calls[1][1].params).toEqual({ contents: 1, exercise: 1, date: '2026-10-23', section: 'Bewertungskriterien' })
    expect(wrapper.text()).toContain('Bewertungskriterien · E-Mails')
    expect(wrapper.text()).not.toContain('Verbindung trennen')
    wrapper.unmount()
})
