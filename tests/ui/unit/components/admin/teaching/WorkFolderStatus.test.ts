import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import WorkFolderStatus from '@/pages/admin/teaching/overview/components/WorkFolderStatus.vue'
import { clearWorkDropboxStates, workDropboxStates } from '@/helpers/workDropbox'
import { useAdminStore } from '@/stores/admin/AdminStore'

const work = { id: 65, teaching_course_id: 2, title: 'Dateimanagement' }

function mountStatus() {
    return mount(WorkFolderStatus, {
        props: { work },
        global: { plugins: [createTestingPinia({ createSpy: vi.fn, initialState: {
            AdminAdminStore: { config: { user: { id: 7 } } },
        } })] },
    })
}

afterEach(() => {
    clearWorkDropboxStates()
    vi.unstubAllGlobals()
})

it('shows the assigned folder even before the first import and updates when the link changes', async () => {
    workDropboxStates.set('7:2:65', { folder: { id: 'id:folder', name: 'Dateimanagement' }, can_quick_import: false })
    const wrapper = mountStatus()

    expect(wrapper.attributes('color')).toBe('success')
    expect(wrapper.attributes('title')).toBe('Ordner verknüpft: Dateimanagement')

    workDropboxStates.set('7:2:65', { folder: null })
    await flushPromises()
    expect(wrapper.attributes('color')).toBe('error')
    expect(wrapper.attributes('title')).toBe('Kein Ordner mit dieser Arbeit verknüpft')
    wrapper.unmount()
})

it('does not infer a link from an import or a suggested folder', () => {
    workDropboxStates.set('7:2:65', { folder: null, has_import: true, suggested_folder: { id: 'id:other' } })
    const wrapper = mountStatus()

    expect(wrapper.attributes('color')).toBe('error')
    expect(wrapper.attributes('aria-label')).toBe('Kein Ordner mit dieser Arbeit verknüpft')
    wrapper.unmount()
})

it('loads the saved assignment and keeps another user or work separate', async () => {
    const get = vi.fn().mockResolvedValue({ data: { folder: { id: 'id:folder', name: 'Gespeicherter Ordner' } } })
    vi.stubGlobal('axios', { get })
    workDropboxStates.set('7:2:66', { folder: { id: 'id:other', name: 'Andere Arbeit' } })
    workDropboxStates.set('8:2:65', { folder: null })
    const wrapper = mountStatus()

    expect(wrapper.attributes('color')).toBe('grey')
    await flushPromises()
    expect(wrapper.attributes('title')).toBe('Ordner verknüpft: Gespeicherter Ordner')

    useAdminStore().config.user.id = 8
    await flushPromises()
    expect(wrapper.attributes('color')).toBe('error')
    expect(get).toHaveBeenCalledTimes(1)
    wrapper.unmount()
})

it('keeps a failed lookup distinct from a missing folder', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockRejectedValue(new Error('Unavailable')) })
    const wrapper = mountStatus()

    await flushPromises()
    expect(wrapper.attributes('color')).toBe('grey')
    expect(wrapper.attributes('title')).toBe('Ordnerverknüpfung konnte nicht geprüft werden')
    wrapper.unmount()
})
