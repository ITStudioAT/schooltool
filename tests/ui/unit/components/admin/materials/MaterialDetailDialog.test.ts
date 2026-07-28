import { describe, expect, it, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import MaterialDetailDialog from '@/pages/admin/materials/components/overview/dialogs/MaterialDetailDialog.vue'

const functionProp = vi.fn()
const FilePondStub = {
    props: {
        allowMultiple: Boolean,
        instantUpload: Boolean,
    },
    template: '<div class="file-pond-stub" />',
}

describe('MaterialDetailDialog', () => {
    it('opens a file pond dialog and replaces the attachment with a dropped file', async () => {
        const attachment = {
            id: 12,
            attachment_type: 'file',
            name: 'test_excel_grundlagen',
            download_url: '/download',
            preview_url: '/preview',
        }
        const replaceAttachment = vi.fn().mockResolvedValue(true)
        const wrapper = shallowMount(MaterialDetailDialog, {
            props: {
                modelValue: true,
                card: {
                    id: 3,
                    title: 'Excel Grundlagen',
                    attachments: [attachment],
                    updated_at: '2026-05-31 18:11:00',
                },
                closeDialogFn: functionProp,
                statusColorFn: functionProp,
                statusLabelFn: () => 'ok',
                classificationLabelsFn: () => [],
                detailAttachmentsFn: (card: { attachments: unknown[] }) => card.attachments,
                attachmentDisplayNameFn: (item: { name: string }) => item.name,
                copyAttachmentChipToClipboardFn: functionProp,
                attachmentTypeLabelFn: () => 'Excel-Datei (xlsx)',
                attachmentSizeBytesFn: () => 11776,
                formatBytesFn: () => '11.5 KB',
                attachmentSourceUrlFn: () => '',
                previewFn: functionProp,
                attachmentDownloadedAtLabelFn: () => '',
                isPreviewingAttachmentFn: () => false,
                previewAttachmentFn: functionProp,
                isDownloadingAttachmentFn: () => false,
                downloadAttachmentFn: functionProp,
                canReplaceAttachmentFn: () => true,
                isReplacingAttachmentFn: () => false,
                replaceAttachmentFn: replaceAttachment,
                isWordDocumentAttachmentFn: () => false,
                isOpeningWordAttachmentFn: () => false,
                openAttachmentInWordFn: functionProp,
                isEditableTextAttachmentFn: () => false,
                downloadAttachmentDocxFn: functionProp,
                formatDateTimeFn: () => '31.05.2026, 18:11',
                startDeleteFlowFn: functionProp,
                resetDeleteFlowFn: functionProp,
                confirmDeleteFn: functionProp,
                openEditFn: functionProp,
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    FilePond: FilePondStub,
                },
            },
        })

        const updateButton = wrapper.get('[title="Aktualisieren"]')
        await updateButton.trigger('click')

        expect((wrapper.vm as any).replacementDialogOpen).toBe(true)

        const pond = wrapper.getComponent(FilePondStub)
        expect(pond.props('instantUpload')).toBe(false)
        expect(pond.props('allowMultiple')).toBe(false)

        const file = new File(['new workbook'], 'excel-neu.xlsx', {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        })

        ;(wrapper.vm as any).handleReplacementFileAdded(null, { file })
        await (wrapper.vm as any).confirmReplacement()

        expect(replaceAttachment).toHaveBeenCalledWith(attachment, file)
        expect((wrapper.vm as any).replacementDialogOpen).toBe(false)
    })
})
