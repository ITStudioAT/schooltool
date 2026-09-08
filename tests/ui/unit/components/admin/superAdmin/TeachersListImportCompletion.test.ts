import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import Teachers from '@/pages/admin/superAdmin/components/Teachers.vue'
import TeacherAdministration from '@/pages/admin/teaching/admin/TeacherAdministration.vue'
import TeachersListImportDialog from '@/pages/admin/superAdmin/components/TeachersListImportDialog.vue'
import { useTeacherStore } from '@/stores/admin/TeacherStore'
import { useTeachersListStore } from '@/stores/admin/TeachersListStore'

let wrapper

beforeEach(() => {
    setActivePinia(createPinia())
})

afterEach(() => {
    wrapper?.unmount()
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
})

function deferred() {
    let resolve
    const promise = new Promise<void>((resolvePromise) => {
        resolve = resolvePromise
    })

    return { promise, resolve }
}

describe('Teacher import completion refresh', () => {
    it.each(['event', 'polling'])('refreshes both lists before enabling Fertig via %s', async (completionSource) => {
        const teacherStore = useTeacherStore()
        const teachersListStore = useTeachersListStore()
        const registeredRefresh = deferred()
        const importedRefresh = deferred()
        const registeredIndex = vi.spyOn(teacherStore, 'index').mockResolvedValue(true)
        const importedIndex = vi.spyOn(teachersListStore, 'index').mockImplementation(async () => {
            await importedRefresh.promise
            teachersListStore.teachers = [{ id: 2, last_name: 'New', first_name: 'Teacher' }]
            return true
        })

        const router = createRouter({
            history: createMemoryHistory(),
            routes: [{ path: '/admin/teaching/administration', component: TeacherAdministration }],
        })
        await router.push('/admin/teaching/administration?panel=teachers')
        wrapper = mount(TeacherAdministration, {
            global: {
                plugins: [router],
                stubs: {
                    FileUpload: true,
                    SearchField: true,
                    Pagination: true,
                    'v-divider': true,
                    'v-list-item': { template: '<div><slot name="title" /></div>' },
                },
            },
        })
        await flushPromises()
        const teachers = wrapper.findComponent(Teachers)
        expect(teachers.props('hideBackButton')).toBe(true)
        expect(teachers.text()).not.toContain('Zur Übersicht')
        await teachers.setData({ importDialog: true })
        registeredIndex.mockClear().mockImplementation(async () => {
            await registeredRefresh.promise
            teacherStore.teachers = [{ id: 1, last_name: 'Imported', first_name: 'Teacher', roles: ['teacher'] }]
            return true
        })
        teacherStore.selected_teachers = [99]
        teachersListStore.selected_teachers = [98]

        const dialog = wrapper.findComponent(TeachersListImportDialog)
        const payload = { state: 'finished', status: 200, message: 'Lehrerliste importiert.' }
        const get = vi.fn().mockResolvedValue({ data: payload })
        vi.stubGlobal('axios', { get })
        dialog.vm.importStarted()

        if (completionSource === 'event') {
            window.dispatchEvent(new CustomEvent('teachers-list-import-finished', { detail: payload }))
        } else {
            dialog.vm.fileUploadFinished()
        }
        await flushPromises()

        expect(importedIndex).toHaveBeenCalledOnce()
        expect(registeredIndex).toHaveBeenCalledOnce()
        expect(teacherStore.selected_teachers).toEqual([])
        expect(teachersListStore.selected_teachers).toEqual([])
        expect(dialog.text()).not.toContain('Fertig')
        expect(dialog.vm.is_import_running).toBe(true)

        // A late upload callback and duplicate completion must not restart the refresh.
        dialog.vm.fileUploadFinished()
        window.dispatchEvent(new CustomEvent('teachers-list-import-finished', { detail: payload }))
        await flushPromises()
        expect(get).toHaveBeenCalledTimes(completionSource === 'polling' ? 1 : 0)
        expect(importedIndex).toHaveBeenCalledOnce()
        expect(registeredIndex).toHaveBeenCalledOnce()

        importedRefresh.resolve()
        await flushPromises()
        expect(dialog.text()).not.toContain('Fertig')

        registeredRefresh.resolve()
        await flushPromises()
        expect(dialog.vm.is_import_running).toBe(false)
        expect(dialog.text()).toContain('Fertig')
        expect(wrapper.text()).toContain('Imported Teacher')
        expect(teachersListStore.teachers[0].last_name).toBe('New')
        expect(dialog.emitted('imported')).toHaveLength(1)
    })

    it('does not refresh either list after a failed import', async () => {
        const registeredIndex = vi.spyOn(useTeacherStore(), 'index').mockResolvedValue(true)
        const importedIndex = vi.spyOn(useTeachersListStore(), 'index').mockResolvedValue(true)
        wrapper = mount(TeachersListImportDialog, {
            props: { modelValue: true },
            global: { stubs: { FileUpload: true } },
        })
        wrapper.vm.importStarted()

        window.dispatchEvent(new CustomEvent('teachers-list-import-finished', {
            detail: { status: 500, message: 'Import fehlgeschlagen.' },
        }))
        await flushPromises()

        expect(registeredIndex).not.toHaveBeenCalled()
        expect(importedIndex).not.toHaveBeenCalled()
        expect(wrapper.text()).toContain('Import fehlgeschlagen.')
        expect(wrapper.text()).toContain('Fertig')
        expect(wrapper.emitted('imported')).toBeUndefined()
    })

    it('waits for both refreshes and explains recovery when one request fails', async () => {
        const importedRefresh = deferred()
        vi.spyOn(useTeacherStore(), 'index').mockRejectedValue(new Error('Network error'))
        vi.spyOn(useTeachersListStore(), 'index').mockReturnValue(importedRefresh.promise)
        wrapper = mount(TeachersListImportDialog, {
            props: { modelValue: true },
            global: { stubs: { FileUpload: true } },
        })
        wrapper.vm.importStarted()

        window.dispatchEvent(new CustomEvent('teachers-list-import-finished', {
            detail: { status: 200, message: 'Lehrerliste importiert.' },
        }))
        await flushPromises()
        expect(wrapper.text()).not.toContain('Fertig')

        importedRefresh.resolve()
        await flushPromises()
        expect(wrapper.vm.is_import_running).toBe(false)
        expect(wrapper.text()).toContain('Fertig')
        expect(wrapper.text()).toContain('Bitte laden Sie die Seite neu.')
    })
})
