import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import Teachers from '@/pages/admin/superAdmin/components/Teachers.vue'
import { useTeacherStore } from '@/stores/admin/TeacherStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { exportCsv } from '@/actions/App/Http/Controllers/Admin/TeacherController'

let wrapper

beforeEach(() => {
    setActivePinia(createPinia())
    vi.spyOn(useTeacherStore(), 'index').mockResolvedValue(true)
})

afterEach(() => {
    wrapper?.unmount()
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
})

async function mountTeachers() {
    wrapper = mount(Teachers, {
        global: {
            stubs: {
                SearchField: true, Pagination: true, TeachersListImportDialog: true,
                'v-divider': true,
                'v-btn': { props: ['disabled', 'loading'], template: '<button :disabled="disabled"><slot /></button>' },
                'v-list-item': { template: '<div><slot name="title" /></div>' },
            },
        },
    })
    await flushPromises()
    return wrapper.findAll('button').find((button) => button.text() === 'Exportieren')!
}

describe('Teacher CSV export', () => {
    it('downloads all teachers independently of selection and search and cleans the temporary URL', async () => {
        const get = vi.fn().mockResolvedValue({ data: new Blob(['csv']), headers: { 'content-disposition': 'attachment; filename=Lehrer_Schule_5.csv' } })
        vi.stubGlobal('axios', { get })
        const create = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:teachers-test')
        const revoke = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {})
        const downloads: { filename: string, attached: boolean }[] = []
        vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function () {
            downloads.push({ filename: this.download, attached: this.isConnected })
        })
        const button = await mountTeachers()
        useTeacherStore().search_string = 'no match'
        useTeacherStore().selected_teachers = [999]
        await button.trigger('click')
        await flushPromises()

        expect(get).toHaveBeenCalledWith(exportCsv.url(), { responseType: 'blob' })
        expect(downloads).toEqual([{ filename: 'Lehrer_Schule_5.csv', attached: true }])
        expect(create).toHaveBeenCalledOnce()
        expect(revoke).toHaveBeenCalledWith('blob:teachers-test')
        expect(document.querySelector('a[href="blob:teachers-test"]')).toBeNull()
        expect(wrapper.vm.exporting).toBe(false)
    })

    it('reports export failures and enables retry', async () => {
        vi.stubGlobal('axios', { get: vi.fn().mockRejectedValue({ response: { status: 403 } }) })
        const notify = vi.spyOn(useNotificationStore(), 'notify').mockImplementation(() => {})
        const button = await mountTeachers()
        await button.trigger('click')
        await flushPromises()

        expect(notify).toHaveBeenCalledWith(expect.objectContaining({ status: 403, type: 'error' }))
        expect(wrapper.vm.exporting).toBe(false)
        expect(button.attributes('disabled')).toBeUndefined()
    })
})
