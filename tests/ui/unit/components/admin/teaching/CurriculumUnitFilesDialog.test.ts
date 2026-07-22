import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CurriculumUnitFilesDialog from '@/pages/admin/teaching/curricula/CurriculumUnitFilesDialog.vue'

const FilePondStub = {
    name: 'FilePond',
    props: {
        allowMultiple: Boolean,
        instantUpload: Boolean,
        allowRevert: Boolean,
        disabled: Boolean,
    },
    template: '<div class="file-pond-stub" />',
}

const DialogStub = {
    name: 'VDialog',
    props: {
        modelValue: Boolean,
        persistent: Boolean,
        maxWidth: [String, Number],
    },
    template: '<div><slot /></div>',
}

function mountDialog() {
    return mount(CurriculumUnitFilesDialog, {
        props: {
            modelValue: true,
            curriculumId: 15,
            topicId: 'topic-1',
            unitId: 'unit-1',
            unitTitle: 'Nebensätze',
        },
        global: {
            stubs: {
                FilePond: FilePondStub,
                'v-alert': { template: '<div><slot /></div>' },
                'v-btn': { template: '<button><slot /></button>' },
                'v-card': { template: '<div><slot /></div>' },
                'v-card-actions': { template: '<div><slot /></div>' },
                'v-card-text': { template: '<div><slot /></div>' },
                'v-card-title': { template: '<div><slot /></div>' },
                'v-dialog': DialogStub,
                'v-divider': { template: '<hr />' },
                'v-icon': { template: '<i><slot /></i>' },
                'v-list': { template: '<div><slot /></div>' },
                'v-list-item': { template: '<div><slot /><slot name="append" /></div>' },
                'v-list-item-subtitle': { template: '<div><slot /></div>' },
                'v-list-item-title': { template: '<div><slot /></div>' },
                'v-progress-circular': { template: '<div />' },
                'v-spacer': { template: '<div />' },
            },
        },
    })
}

describe('CurriculumUnitFilesDialog', () => {
    afterEach(() => {
        vi.restoreAllMocks()
        delete (globalThis as any).axios
    })

    it('is persistent and uploads several FilePond files without closing', async () => {
        const endpoint = '/api/admin/teaching/curricula/15/topics/topic-1/units/unit-1/files'
        const getMock = vi.fn().mockResolvedValue({ data: { data: [] } })
        const postMock = vi.fn().mockResolvedValue({ data: { data: [] } })
        ;(globalThis as any).axios = { get: getMock, post: postMock, delete: vi.fn() }

        const wrapper = mountDialog()

        await vi.waitFor(() => expect(getMock).toHaveBeenCalledWith(endpoint))

        const dialogs = wrapper.findAllComponents(DialogStub)
        expect(dialogs[0].props('persistent')).toBe(true)

        const pond = wrapper.getComponent(FilePondStub)
        expect(pond.props('allowMultiple')).toBe(true)
        expect(pond.props('instantUpload')).toBe(false)

        const firstFile = new File(['first'], 'first.pdf', { type: 'application/pdf' })
        const secondFile = new File(['second'], 'second.txt', { type: 'text/plain' })
        ;(wrapper.vm as any).handleFilesUpdate([{ file: firstFile }, { file: secondFile }])
        await (wrapper.vm as any).saveFiles()

        expect(postMock).toHaveBeenCalledOnce()
        expect(postMock.mock.calls[0][0]).toBe(endpoint)

        const formData = postMock.mock.calls[0][1] as FormData
        expect(formData.getAll('files[]')).toHaveLength(2)
        expect(wrapper.emitted('update:modelValue')).toBeUndefined()
        expect(wrapper.emitted('changed')).toEqual([[[]]])
    })

    it('loads saved files and deletes one only after confirmation', async () => {
        const endpoint = '/api/admin/teaching/curricula/15/topics/topic-1/units/unit-1/files'
        const savedFile = {
            id: 8,
            name: 'Plan.pdf',
            mime_type: 'application/pdf',
            size_bytes: 2048,
            preview_url: `${endpoint}/8/preview`,
            download_url: `${endpoint}/8/download`,
        }
        const deleteMock = vi.fn().mockResolvedValue({})
        ;(globalThis as any).axios = {
            get: vi.fn().mockResolvedValue({ data: { data: [savedFile] } }),
            post: vi.fn(),
            delete: deleteMock,
        }

        const wrapper = mountDialog()
        await vi.waitFor(() => expect((wrapper.vm as any).files).toEqual([savedFile]))

        expect(wrapper.text()).toContain('2.0 KB')
        expect(wrapper.text()).not.toContain('application/pdf')

        ;(wrapper.vm as any).fileToDelete = savedFile
        await (wrapper.vm as any).deleteFile()

        expect(deleteMock).toHaveBeenCalledWith(`${endpoint}/8`)
        expect((wrapper.vm as any).files).toEqual([])
        expect((wrapper.vm as any).fileToDelete).toBeNull()
        expect(wrapper.emitted('changed')).toEqual([[[]]])
    })
})
