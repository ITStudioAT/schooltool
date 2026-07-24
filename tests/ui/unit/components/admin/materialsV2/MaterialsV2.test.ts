import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, shallowMount } from '@vue/test-utils'
import axios from 'axios'
import MaterialsV2 from '@/pages/admin/materialsV2/MaterialsV2.vue'

const notify = vi.fn()

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: () => ({ notify }),
}))

describe('MaterialsV2', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        vi.mocked(axios.get).mockImplementation(async (url) => {
            if (url === '/api/admin/materials-v2/config') {
                return {
                    data: {
                        categories: ['Biologie', 'Mathematik'],
                    },
                }
            }

            return {
                data: {
                    data: [
                        {
                            id: 1,
                            title: 'Photosynthese',
                            category: 'Biologie',
                            description: 'Arbeitsblatt',
                            user_keywords: ['Biologie'],
                            generated_keywords: ['chlorophyll'],
                            processing_status: 'ready',
                            attachments: [
                                {
                                    id: 41,
                                    original_name: 'arbeitsblatt.docx',
                                    mime_type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    size_bytes: 4096,
                                    preview_url: '/api/admin/materials-v2/attachments/41/preview',
                                    download_url: '/api/admin/materials-v2/attachments/41/download',
                                },
                            ],
                        },
                    ],
                    meta: {
                        total: 1,
                        current_page: 1,
                        last_page: 1,
                    },
                },
            }
        })
    })

    it('loads the independent materials v2 endpoint on mount', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })

        await flushPromises()

        expect(axios.get).toHaveBeenCalledWith('/api/admin/materials-v2/items', {
            params: {
                search: undefined,
                page: 1,
                per_page: 18,
            },
        })
        expect(axios.get).toHaveBeenCalledWith('/api/admin/materials-v2/config')
        expect(wrapper.text()).toContain('Materialien 2')
        expect(wrapper.text()).toContain('Photosynthese')
        expect(wrapper.text()).toContain('Biologie')
    })

    it('opens attachments in an embedded browser preview', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })

        await flushPromises()

        expect(wrapper.find('[title="Vorschau"]').exists()).toBe(true)

        await wrapper.find('.materials-v2-attachment-name').trigger('click')

        const previewFrame = wrapper.find('.materials-v2-preview-frame')
        expect(previewFrame.exists()).toBe(true)
        expect(previewFrame.attributes('src')).toBe('/api/admin/materials-v2/attachments/41/preview')
        expect(previewFrame.attributes('title')).toBe('Vorschau: arbeitsblatt.docx')
    })

    it('asks whether to reuse a similar category before saving', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.openCreateDialog()
        component.materialForm.title = 'Zellen'
        component.materialForm.category = 'Biolgie'
        vi.mocked(axios.post).mockRejectedValueOnce({
            response: {
                status: 409,
                data: {
                    category_suggestion: {
                        entered: 'Biolgie',
                        existing: 'Biologie',
                    },
                },
            },
        })

        await component.saveMaterial()

        expect(component.categorySuggestionDialog.open).toBe(true)
        expect(wrapper.text()).toContain('Neue Kategorie anlegen')
        expect(wrapper.text()).toContain('Bestehende übernehmen')

        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    id: 2,
                    title: 'Zellen',
                    category: 'Biologie',
                },
            },
        })

        await component.useSuggestedCategory()

        const savedPayload = vi.mocked(axios.post).mock.calls[1][1] as FormData
        expect(savedPayload.get('category')).toBe('Biologie')
        expect(savedPayload.get('force_new_category')).toBeNull()
    })

    it('can keep the newly typed category after the warning', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.openCreateDialog()
        component.materialForm.title = 'Zellen'
        component.materialForm.category = 'Biolgie'
        component.categorySuggestionDialog.entered = 'Biolgie'
        component.categorySuggestionDialog.existing = 'Biologie'
        component.categorySuggestionDialog.open = true
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    id: 2,
                    title: 'Zellen',
                    category: 'Biolgie',
                },
            },
        })

        await component.keepNewCategory()

        const savedPayload = vi.mocked(axios.post).mock.calls[0][1] as FormData
        expect(savedPayload.get('category')).toBe('Biolgie')
        expect(savedPayload.get('force_new_category')).toBe('1')
    })
})
