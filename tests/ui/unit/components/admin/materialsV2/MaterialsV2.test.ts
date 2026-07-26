import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
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
    afterEach(() => {
        vi.useRealTimers()
    })

    beforeEach(() => {
        window.localStorage.clear()
        vi.clearAllMocks()
        vi.mocked(axios.get).mockImplementation(async (url) => {
            if (url === '/api/admin/materials-v2/config') {
                return {
                    data: {
                        categories: ['Biologie', 'Mathematik'],
                        category_details: [
                            { name: 'Biologie', items_count: 1 },
                            { name: 'Mathematik', items_count: 0 },
                        ],
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
                            generated_keywords: ['welche'],
                            automatic_tag_suggestions: [
                                {
                                    name: 'Chlorophyll',
                                    score: 9.75,
                                    rank: 1,
                                    language: 'de',
                                    attachment_id: 41,
                                },
                            ],
                            processing_status: 'ready',
                            processed_at: '2026-07-25T12:30:00+02:00',
                            attachments: [
                                {
                                    id: 41,
                                    original_name: 'arbeitsblatt.docx',
                                    mime_type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    size_bytes: 4096,
                                    keyword_extraction_status: 'ready',
                                    keyword_extraction_error: null,
                                    keywords_extracted_at: '2026-07-25T12:30:00+02:00',
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
                    'v-btn-toggle': true,
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
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
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
                category: undefined,
                page: 1,
                per_page: 18,
            },
        })
        expect(axios.get).toHaveBeenCalledWith('/api/admin/materials-v2/config')
        expect(wrapper.text()).toContain('Materialien 2')
        expect(wrapper.text()).toContain('Photosynthese')
        expect(wrapper.text()).toContain('Biologie')
    })

    it('switches between large, standard, and compact persisted material layouts', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
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
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        expect(component.displayMode).toBe('large')
        expect(component.materialColumnProps).toEqual({ cols: 12, md: 6, xl: 4 })
        expect(wrapper.find('.materials-v2-card--large').exists()).toBe(true)
        expect(wrapper.text()).toContain('Groß')
        expect(wrapper.text()).toContain('Standard')
        expect(wrapper.text()).toContain('Kompakt')

        component.items[0].description = null
        component.items[0].user_keywords = ['Lichtreaktion']
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).toContain('Keine Beschreibung')
        expect(wrapper.text()).not.toContain('Deine Suchwörter')
        expect(wrapper.text()).toContain('Lichtreaktion')

        component.displayMode = 'standard'
        await wrapper.vm.$nextTick()

        expect(component.materialColumnProps).toEqual({ cols: 12, sm: 6, lg: 4, xl: 3 })
        expect(wrapper.find('.materials-v2-card--standard').exists()).toBe(true)
        expect(wrapper.text()).not.toContain('Keine Beschreibung')
        expect(wrapper.text()).not.toContain('Deine Suchwörter')
        expect(wrapper.text()).toContain('Lichtreaktion')
        expect(window.localStorage.getItem('materials-v2-display-mode')).toBe('standard')

        component.displayMode = 'compact'
        await wrapper.vm.$nextTick()

        expect(component.materialColumnProps).toEqual({ cols: 12, sm: 6, md: 4, lg: 3, xl: 2 })
        expect(wrapper.find('.materials-v2-card--compact').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-description').exists()).toBe(false)
        expect(wrapper.find('.materials-v2-attachment-row').exists()).toBe(false)
        expect(window.localStorage.getItem('materials-v2-display-mode')).toBe('compact')

        wrapper.unmount()

        const persistedWrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
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
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        expect((persistedWrapper.vm as any).displayMode).toBe('compact')
    })

    it('shows a category selection list and combines the selected category with the search', async () => {
        vi.useFakeTimers()

        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
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
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const categoryItems = wrapper.findAll('.materials-v2-category-item')
        expect(categoryItems).toHaveLength(3)
        expect(categoryItems[0].text()).toContain('Alle Materialien')
        expect(categoryItems[1].text()).toContain('Biologie')
        expect(categoryItems[2].text()).toContain('Mathematik')

        vi.mocked(axios.get).mockClear()
        const component = wrapper.vm as any
        component.search = 'Zellen'
        await categoryItems[1].trigger('click')
        await flushPromises()

        expect(component.selectedCategory).toBe('Biologie')
        expect(axios.get).toHaveBeenCalledWith('/api/admin/materials-v2/items', {
            params: {
                search: 'Zellen',
                category: 'Biologie',
                page: 1,
                per_page: 18,
            },
        })
    })

    it('creates a category from a persistent dialog', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
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
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const createButton = wrapper.find('.materials-v2-category-create-button')
        expect(createButton.exists()).toBe(true)

        await createButton.trigger('click')

        const component = wrapper.vm as any
        const categoryDialog = wrapper.find('.materials-v2-category-dialog')
        expect(component.categoryDialog.open).toBe(true)
        expect(categoryDialog.attributes()).toHaveProperty('persistent')

        component.categoryDialog.name = 'Physik'
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    id: 8,
                    name: 'Physik',
                },
            },
        })

        await component.saveCategory()
        await flushPromises()

        expect(axios.post).toHaveBeenCalledWith('/api/admin/materials-v2/categories', {
            name: 'Physik',
        })
        expect(component.categoryDialog.open).toBe(false)
        expect(notify).toHaveBeenCalledWith({
            message: 'Kategorie gespeichert.',
            type: 'success',
            timeout: 3200,
        })
    })

    it('keeps the category dialog open and warns about duplicate casing', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
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
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.openCategoryDialog()
        component.categoryDialog.name = 'video'
        vi.mocked(axios.post).mockRejectedValueOnce({
            response: {
                status: 409,
                data: {
                    category_conflict: {
                        entered: 'video',
                        existing: 'Video',
                    },
                },
            },
        })

        await component.saveCategory()

        expect(component.categoryDialog.open).toBe(true)
        expect(component.categoryDialog.warning).toBe(
            'Die Kategorie „Video“ existiert bereits. Bitte wähle einen anderen Namen.',
        )
        expect(notify).not.toHaveBeenCalled()
    })

    it('edits a category from its list action', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
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
                    'v-list-item': {
                        template: '<div><slot /><slot name="append" /></div>',
                    },
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const editButtons = wrapper.findAll('.materials-v2-category-edit-button')
        expect(editButtons).toHaveLength(2)

        await editButtons[0].trigger('click')

        const component = wrapper.vm as any
        expect(component.categoryDialog.mode).toBe('edit')
        expect(component.categoryDialog.originalName).toBe('Biologie')
        expect(component.categoryDialog.name).toBe('Biologie')

        component.categoryDialog.name = 'Naturkunde'
        vi.mocked(axios.put).mockResolvedValueOnce({
            data: {
                data: {
                    id: 8,
                    name: 'Naturkunde',
                },
            },
        })

        await component.saveCategory()
        await flushPromises()

        expect(axios.put).toHaveBeenCalledWith('/api/admin/materials-v2/categories', {
            original_name: 'Biologie',
            name: 'Naturkunde',
        })
        expect(component.categoryDialog.open).toBe(false)
        expect(notify).toHaveBeenCalledWith({
            message: 'Kategorie aktualisiert.',
            type: 'success',
            timeout: 3200,
        })
    })

    it('shows item counters and only offers deletion for an empty category', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': {
                        template: '<button v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
                    },
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': {
                        template: '<span v-bind="$attrs"><slot /></span>',
                    },
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': {
                        template: '<div><slot /><slot name="append" /></div>',
                    },
                    'v-list-item-title': {
                        template: '<div><slot /></div>',
                    },
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const counters = wrapper.findAll('.materials-v2-category-item-count')
        const deleteButtons = wrapper.findAll('.materials-v2-category-delete-button')

        expect(counters.map((counter) => counter.text())).toEqual(['1', '0'])
        expect(deleteButtons).toHaveLength(1)

        await deleteButtons[0].trigger('click')

        const component = wrapper.vm as any
        expect(component.categoryDeleteDialog.open).toBe(true)
        expect(component.categoryDeleteDialog.name).toBe('Mathematik')

        vi.mocked(axios.delete).mockResolvedValueOnce({ data: null })
        await component.deleteCategory()
        await flushPromises()

        expect(axios.delete).toHaveBeenCalledWith('/api/admin/materials-v2/categories', {
            data: {
                name: 'Mathematik',
            },
        })
        expect(component.categoryDeleteDialog.open).toBe(false)
        expect(notify).toHaveBeenCalledWith({
            message: 'Kategorie gelöscht.',
            type: 'success',
            timeout: 3200,
        })
    })

    it('opens attachments in an embedded browser preview', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
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
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
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
                    'v-btn-toggle': true,
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
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
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
                    'v-btn-toggle': true,
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
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
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

    it('reloads the first unfiltered page after creating a material', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
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
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.search = 'Altes Material'
        component.selectedCategory = 'Biologie'
        component.page = 3
        await wrapper.vm.$nextTick()
        vi.mocked(axios.get).mockClear()

        component.openCreateDialog()
        component.materialForm.title = 'Neues Material'
        component.materialForm.category = 'Mathematik'
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    id: 2,
                    title: 'Neues Material',
                    category: 'Mathematik',
                },
            },
        })

        await component.saveMaterial()
        await flushPromises()

        expect(component.search).toBe('')
        expect(component.selectedCategory).toBe('__all_categories__')
        expect(component.page).toBe(1)
        expect(axios.get).toHaveBeenCalledWith('/api/admin/materials-v2/items', {
            params: {
                search: undefined,
                category: undefined,
                page: 1,
                per_page: 18,
            },
        })
    })

    it('shows ranked local tags instead of legacy generated words and supports review actions', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': {
                        template: '<div><slot /></div>',
                    },
                    'v-btn': {
                        template: '<button v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
                    },
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': {
                        template: '<div><slot /></div>',
                    },
                    'v-card-title': {
                        template: '<div><slot /></div>',
                    },
                    'v-chip': {
                        template: '<span v-bind="$attrs"><slot /></span>',
                    },
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': {
                        template: '<div><slot /></div>',
                    },
                    'v-dialog': {
                        template: '<div><slot /></div>',
                    },
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': {
                        template: '<div><slot /></div>',
                    },
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        expect(wrapper.text()).toContain('Chlorophyll')
        expect(wrapper.text()).not.toContain('welche')

        const component = wrapper.vm as any
        component.openEditDialog(component.items[0])
        await wrapper.vm.$nextTick()

        expect(wrapper.find('.materials-v2-automatic-tag-review').exists()).toBe(true)
        expect(wrapper.text()).toContain('9.75 Punkte')
        expect(wrapper.text()).toContain('Tags erkannt')

        const suggestion = component.materialDialog.item.automatic_tag_suggestions[0]
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    ...component.materialDialog.item,
                    user_keywords: ['Biologie', 'Chlorophyll'],
                    generated_keywords: [],
                    automatic_tag_suggestions: [],
                },
            },
        })

        await component.convertAutomaticTag(component.materialDialog.item, suggestion)

        expect(axios.post).toHaveBeenCalledWith('/api/admin/materials-v2/items/1/automatic-tags/convert', {
            tag_name: 'Chlorophyll',
        })
        expect(component.materialForm.keywords).toBe('Biologie, Chlorophyll')
        expect(notify).toHaveBeenCalledWith({
            message: 'Tag als eigenes Suchwort übernommen.',
            type: 'success',
            timeout: 3200,
        })
    })

    it('removes one automatic tag and can request a forced recalculation', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
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
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        const item = component.items[0]
        const suggestion = item.automatic_tag_suggestions[0]
        vi.mocked(axios.delete).mockResolvedValueOnce({
            data: {
                data: {
                    ...item,
                    generated_keywords: [],
                    automatic_tag_suggestions: [],
                },
            },
        })

        await component.removeAutomaticTag(item, suggestion)

        expect(axios.delete).toHaveBeenCalledWith('/api/admin/materials-v2/items/1/automatic-tags', {
            data: {
                tag_name: 'Chlorophyll',
            },
        })
        expect(component.items[0].automatic_tag_suggestions).toEqual([])

        vi.mocked(axios.post).mockResolvedValueOnce({ data: { message: 'started' } })
        await component.recalculateAutomaticTags(component.items[0])
        await flushPromises()

        expect(axios.post).toHaveBeenCalledWith('/api/admin/materials-v2/items/1/recalculate-automatic-tags')
        expect(notify).toHaveBeenCalledWith({
            message: 'Automatische Tag-Erkennung gestartet.',
            type: 'info',
            timeout: 3200,
        })
    })
})
