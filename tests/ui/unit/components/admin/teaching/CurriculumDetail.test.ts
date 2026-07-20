import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { createTestingPinia } from '@pinia/testing'
import { afterAll, afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CurriculumDetail from '@/pages/admin/teaching/curricula/CurriculumDetail.vue'

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: () => ({
        notify: vi.fn(),
    }),
}))

const loadDocumentsSpy = vi.spyOn((CurriculumDetail as any).methods, 'loadDocuments').mockResolvedValue(undefined)
const openMaterialAttachmentDialogSpy = vi.spyOn((CurriculumDetail as any).methods, 'openMaterialAttachmentDialog').mockResolvedValue(undefined)

function buildCurriculum(overrides: Record<string, unknown> = {}) {
    return {
        id: 15,
        title: 'Deutsch',
        description: 'Lehrplan',
        is_finished: false,
        semester_count: 2,
        free_weeks: [],
        topics: [],
        ...overrides,
    }
}

function mountCurriculumDetail(
    curriculumOverrides: Record<string, unknown> = {},
    adminConfigOverrides: Record<string, unknown> = {},
) {
    const { selected_schoolyear: selectedSchoolyearOverride, ...configOverrides } = adminConfigOverrides
    const pinia = createTestingPinia({
        stubActions: false,
        createSpy: vi.fn,
        initialState: {
            AdminAdminStore: {
                config: {
                    roles: ['teacher'],
                    selected_schoolyear: {
                        from: '2025-09-01',
                        ...((selectedSchoolyearOverride as Record<string, unknown> | undefined) || {}),
                    },
                    ...configOverrides,
                },
            },
        },
    })

    return mount(CurriculumDetail, {
        props: {
            curriculum: buildCurriculum(curriculumOverrides),
        },
        global: {
            plugins: [pinia],
            stubs: {
                CurriculumPdfPreview: {
                    props: ['documentId', 'src', 'initialPosition'],
                    template: '<div class="curriculum-pdf-preview-stub" :data-src="src" />',
                },
                FileUpload: { template: '<div class="file-upload-stub" />' },
                'v-btn': { template: '<button><slot /></button>' },
                'v-btn-toggle': { template: '<div class="v-btn-toggle"><slot /></div>' },
                'v-card': { template: '<div><slot /></div>' },
                'v-card-actions': { template: '<div><slot /></div>' },
                'v-card-text': { template: '<div><slot /></div>' },
                'v-card-title': { template: '<div><slot /></div>' },
                'v-checkbox': { template: '<input type="checkbox" />' },
                'v-chip': { template: '<span class="v-chip"><slot /></span>' },
                'v-dialog': { template: '<div><slot /></div>' },
                'v-divider': { template: '<hr />' },
                'v-autocomplete': { template: '<div><slot /></div>' },
                'v-form': { template: '<form><slot /></form>' },
                'v-icon': { template: '<i><slot /></i>' },
                'v-list': { template: '<div><slot /></div>' },
                'v-list-item': { props: ['title'], template: '<div>{{ title }}<slot /></div>' },
                'v-list-item-subtitle': { template: '<div><slot /></div>' },
                'v-list-item-title': { template: '<div><slot /></div>' },
                'v-menu': { template: '<div><slot name="activator" :props="{}" /><slot /></div>' },
                'v-progress-circular': { template: '<div />' },
                'v-sheet': { template: '<div v-bind="$attrs"><slot /></div>' },
                'v-spacer': { template: '<div />' },
                'v-select': { template: '<select v-bind="$attrs" />' },
                'v-text-field': { template: '<input />' },
            },
        },
    })
}

describe('CurriculumDetail preview layout', () => {
    it('persists the current PDF position before returning to the overview', () => {
        const methods = (CurriculumDetail as any).methods
        const calls: Array<string> = []
        const context = {
            $emit: (event: string) => calls.push(event),
            $refs: {
                curriculumPdfPreview: {
                    emitCurrentPosition: () => calls.push('position'),
                },
            },
            persistCurriculumDocumentIframePosition: () => calls.push('iframe-position'),
        }

        methods.leaveCurriculum.call(context)

        expect(calls).toEqual(['iframe-position', 'position', 'back'])
    })

    afterEach(() => {
        loadDocumentsSpy.mockClear()
        openMaterialAttachmentDialogSpy.mockClear()
        window.localStorage.clear()
    })

    it('renders numbered topics and units in the imported-preview structure', async () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 1,
                    title: 'Grundlagen',
                    materials: [],
                    units: [
                        { id: 11, title: 'Anmelden', materials: [] },
                        { id: 12, title: 'E-Mails', materials: [] },
                    ],
                },
                {
                    id: 2,
                    title: 'Textverarbeitung',
                    materials: [],
                    units: [
                        { id: 21, title: 'Zeichenformate', materials: [] },
                    ],
                },
            ],
        })

        await wrapper.vm.$nextTick()

        expect(wrapper.find('.curriculum-detail__preview').exists()).toBe(true)
        expect(wrapper.find('.curriculum-detail__preview-summary').text()).toBe('2 Themen · 3 Einheiten')
        expect(wrapper.findAll('.curriculum-detail__topic-item')).toHaveLength(2)
        expect(wrapper.findAll('.curriculum-detail__topic-title').map((topic) => topic.text())).toEqual([
            '1. Grundlagen',
            '2. Textverarbeitung',
        ])
        expect(wrapper.findAll('.curriculum-detail__topic-title-row').map((titleRow) => (
            titleRow.find('.curriculum-detail__topic-unit-count').text()
        ))).toEqual([
            '2 Einheiten',
            '1 Einheit',
        ])
        expect(wrapper.findAll('.curriculum-detail__unit-item')).toHaveLength(3)
        expect(wrapper.findAll('.curriculum-detail__unit-title').map((unit) => unit.text())).toEqual([
            '1.1 Anmelden',
            '1.2 E-Mails',
            '2.1 Zeichenformate',
        ])
        expect(wrapper.text()).not.toContain('Verteilen')
        expect(wrapper.find('.curriculum-detail__side-card--documents').exists()).toBe(true)
    })

    it('prominently styles the back-to-overview action', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/curricula/CurriculumDetail.vue'), 'utf8')

        expect(source).toContain('class="text-none curriculum-detail__back-btn"')
        expect(source).toContain('.curriculum-detail__back-btn {\n    min-height: 44px;')
        expect(source).toContain('.curriculum-detail__back-btn:focus-visible {')
        expect(source).toContain('.curriculum-detail__header-actions :deep(.v-btn) {')
    })

    it('shows and updates the curriculum status marker', async () => {
        const wrapper = mountCurriculumDetail({ id: 15, is_finished: false })
        const originalAxios = (globalThis as any).axios
        const putMock = vi.fn().mockResolvedValue({
            data: {
                data: buildCurriculum({ id: 15, is_finished: true }),
            },
        })

        ;(globalThis as any).axios = { put: putMock }

        try {
            const statusMarker = wrapper.find('.curriculum-detail__status-marker')

            expect(statusMarker.text()).toBe('In Arbeit')

            await statusMarker.trigger('click')

            await vi.waitFor(() => {
                expect(putMock).toHaveBeenCalledWith(
                    '/api/admin/teaching/curricula/15',
                    expect.objectContaining({ is_finished: true }),
                )
            })

            expect(wrapper.emitted('updated')?.[0]?.[0]).toMatchObject({
                id: 15,
                is_finished: true,
            })
        } finally {
            ;(globalThis as any).axios = originalAxios
        }

        const finishedWrapper = mountCurriculumDetail({ is_finished: true })

        expect(finishedWrapper.find('.curriculum-detail__status-marker').text()).toBe('Fertig')
    })

    it('adds clear vertical spacing between curriculum themes', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/curricula/CurriculumDetail.vue'), 'utf8')

        expect(source).toContain('.curriculum-detail__topic-list {\n    display: flex;\n    flex-direction: column;\n    gap: 12px;')
    })

    it('uses the default cursor in the topic title input', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/curricula/CurriculumDetail.vue'), 'utf8')

        expect(source).toContain('@after-enter="focusTopicTitleField"')
        expect(source).toContain('ref="topicTitleField"')
        expect(source).toContain('autofocus')
        expect(source).toContain('class="mb-3 curriculum-detail__topic-title-field"')
        expect(source).toContain(`.curriculum-detail__topic-title-field :deep(input) {
    cursor: default;
}`)
    })

    it('focuses the topic title input after the dialog opens', () => {
        const focus = vi.fn()
        const methods = (CurriculumDetail as any).methods
        const context = {
            $nextTick: (callback: () => void) => callback(),
            $refs: {
                topicTitleField: {
                    $el: {
                        querySelector: () => ({ focus }),
                    },
                },
            },
        }

        methods.focusTopicTitleField.call(context)

        expect(focus).toHaveBeenCalledOnce()
    })

    it('cancels the topic dialog when Escape is pressed in the title input', async () => {
        const wrapper = mountCurriculumDetail()

        await wrapper.setData({
            showTopicForm: true,
            topicForm: {
                id: null,
                title: 'Schreiben',
            },
        })

        await wrapper.find('.curriculum-detail__topic-title-field').trigger('keydown', { key: 'Escape' })

        expect((wrapper.vm as any).showTopicForm).toBe(false)
        expect((wrapper.vm as any).topicForm).toMatchObject({ id: null, title: '' })
    })

    it('resizes the content and document cards with an accessible splitter', async () => {
        const wrapper = mountCurriculumDetail()
        const splitter = wrapper.find('.curriculum-detail__card-splitter')

        expect(splitter.exists()).toBe(true)
        expect(splitter.attributes('role')).toBe('separator')
        expect(splitter.attributes('aria-orientation')).toBe('vertical')
        expect(splitter.attributes('aria-valuenow')).toBe('64')
        expect(wrapper.find('.curriculum-detail__body').attributes('style')).toContain('grid-template-columns: 64fr 36px 36fr')

        await splitter.trigger('keydown', { key: 'ArrowLeft' })

        expect((wrapper.vm as any).curriculumContentWidthPercent).toBe(62)
        expect(splitter.attributes('aria-valuenow')).toBe('62')

        await splitter.trigger('keydown', { key: 'ArrowRight' })
        await splitter.trigger('dblclick')

        expect((wrapper.vm as any).curriculumContentWidthPercent).toBe(64)

        const source = readFileSync(resolve('resources/js/pages/admin/teaching/curricula/CurriculumDetail.vue'), 'utf8')

        expect(source).toContain('@pointerdown="startCurriculumCardResize"')
        expect(source).toContain('touch-action: none;')
        expect(source).toContain('.curriculum-detail__card-splitter {\n        display: none;')
        expect(source).toContain('grid-template-columns: 1fr !important;')
    })

    it('keeps both curriculum cards above their minimum drag width', () => {
        const methods = (CurriculumDetail as any).methods
        const resizeBounds = methods.curriculumCardResizeBounds(1200)
        const context = {
            $refs: {
                curriculumBody: {
                    getBoundingClientRect: () => ({ left: 100, width: 1200 }),
                },
            },
            curriculumCardResizeBounds: methods.curriculumCardResizeBounds,
            curriculumCardResizePointerId: 7,
            curriculumContentWidthPercent: 64,
            isCurriculumCardResizing: true,
        }

        methods.resizeCurriculumCards.call(context, { clientX: -100, pointerId: 7 })
        expect(context.curriculumContentWidthPercent).toBe(resizeBounds.minimumPercent)

        methods.resizeCurriculumCards.call(context, { clientX: 2000, pointerId: 7 })
        expect(context.curriculumContentWidthPercent).toBe(resizeBounds.maximumPercent)
    })

    it('remembers the curriculum card width separately for each user', async () => {
        window.localStorage.setItem('schooltool.admin.teaching.curriculum-card-width.user.42', '58')

        const firstUserWrapper = mountCurriculumDetail({}, { user: { id: 42 } })
        const firstUserSplitter = firstUserWrapper.find('.curriculum-detail__card-splitter')

        expect((firstUserWrapper.vm as any).curriculumContentWidthPercent).toBe(58)

        await firstUserSplitter.trigger('keydown', { key: 'ArrowRight' })

        expect(window.localStorage.getItem('schooltool.admin.teaching.curriculum-card-width.user.42')).toBe('60')

        firstUserWrapper.unmount()

        const restoredWrapper = mountCurriculumDetail({}, { user: { id: 42 } })
        const otherUserWrapper = mountCurriculumDetail({}, { user: { id: 43 } })

        expect((restoredWrapper.vm as any).curriculumContentWidthPercent).toBe(60)
        expect((otherUserWrapper.vm as any).curriculumContentWidthPercent).toBe(64)
    })

    it('remembers the opened curriculum document per user and curriculum', async () => {
        const document = {
            id: 902,
            source_type: 'upload',
            name: 'Deutsch Lehrplan.pdf',
            preview_url: '/api/admin/teaching/curricula/15/documents/902/preview',
        }
        const storageKey = 'schooltool.admin.teaching.curriculum-document-preview.user.42.curriculum.15'
        window.localStorage.setItem(storageKey, '902')

        const wrapper = mountCurriculumDetail({}, { user: { id: 42 } })
        await wrapper.setData({ documents: [document] })

        ;(wrapper.vm as any).restoreCurriculumDocumentPreview()

        expect((wrapper.vm as any).previewDoc).toEqual(document)

        ;(wrapper.vm as any).setCurriculumDocumentPreview(null)

        expect((wrapper.vm as any).previewDoc).toBeNull()
        expect(window.localStorage.getItem(storageKey)).toBeNull()

        ;(wrapper.vm as any).selectPreview(document)

        expect(window.localStorage.getItem(storageKey)).toBe('902')

        const otherCurriculumWrapper = mountCurriculumDetail({ id: 16 }, { user: { id: 42 } })
        await otherCurriculumWrapper.setData({ documents: [document] })

        ;(otherCurriculumWrapper.vm as any).restoreCurriculumDocumentPreview()

        expect((otherCurriculumWrapper.vm as any).previewDoc).toBeNull()
    })

    it('remembers the PDF page position per user, curriculum, and document', async () => {
        const document = {
            id: 902,
            source_type: 'upload',
            name: 'Deutsch Lehrplan.pdf',
            preview_mime_type: 'application/pdf',
            preview_url: '/api/admin/teaching/curricula/15/documents/902/preview',
        }
        const storageKey = 'schooltool.admin.teaching.curriculum-document-position.user.42.curriculum.15.document.902'
        window.localStorage.setItem(storageKey, JSON.stringify({ page: 4, offset: 0.35 }))

        const wrapper = mountCurriculumDetail({}, { user: { id: 42 } })
        await wrapper.setData({ documents: [document] })

        ;(wrapper.vm as any).setCurriculumDocumentPreview(document)
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).curriculumDocumentPreviewPosition).toEqual({ page: 4, offset: 0.35 })
        expect(wrapper.find('.curriculum-pdf-preview-stub').attributes('data-src')).toBe(document.preview_url)

        ;(wrapper.vm as any).persistCurriculumDocumentPreviewPosition({ page: 7, offset: 0.62 })

        expect(JSON.parse(window.localStorage.getItem(storageKey) || 'null')).toEqual({ page: 7, offset: 0.62 })
        expect((wrapper.vm as any).curriculumDocumentPreviewPosition).toEqual({ page: 4, offset: 0.35 })

        ;(wrapper.vm as any).setCurriculumDocumentPreview(null)
        ;(wrapper.vm as any).persistCurriculumDocumentPreviewPosition({ page: 8, offset: 0.2 }, document.id)
        ;(wrapper.vm as any).setCurriculumDocumentPreview(document)

        expect((wrapper.vm as any).curriculumDocumentPreviewPosition).toEqual({ page: 8, offset: 0.2 })
    })

    it('remembers the relative scroll position of same-origin document previews', () => {
        const methods = (CurriculumDetail as any).methods
        const context = {
            $refs: {
                curriculumDocumentIframe: {
                    contentWindow: {
                        document: {
                            body: { scrollHeight: 1980 },
                            documentElement: { clientHeight: 500, scrollHeight: 2000 },
                        },
                        innerHeight: 500,
                        scrollY: 750,
                    },
                },
            },
            curriculumDocumentIframeScrollMetrics: methods.curriculumDocumentIframeScrollMetrics,
        }

        expect(methods.currentCurriculumDocumentIframePosition.call(context)).toEqual({ scrollRatio: 0.5 })
        expect(methods.normalizeCurriculumDocumentPreviewPosition({ scrollRatio: 4 })).toEqual({ scrollRatio: 1 })
    })

    it('restores a same-origin document preview before listening for new scrolling', () => {
        const methods = (CurriculumDetail as any).methods
        const scrollTo = vi.fn()
        const addEventListener = vi.fn()
        const context = {
            $refs: {},
            curriculumDocumentIframeScrollMetrics: () => ({
                iframeWindow: { addEventListener, scrollTo },
                maximumScrollTop: 1500,
                scrollTop: 0,
            }),
            curriculumDocumentPreviewPosition: { scrollRatio: 0.5 },
            detachCurriculumDocumentIframeScrollListener: vi.fn(),
            handleCurriculumDocumentIframeScroll: vi.fn(),
            normalizeCurriculumDocumentPreviewPosition: methods.normalizeCurriculumDocumentPreviewPosition,
            persistCurriculumDocumentIframePosition: vi.fn(),
        }

        methods.restoreCurriculumDocumentIframePosition.call(context)

        expect(scrollTo).toHaveBeenCalledWith(0, 750)
        expect(addEventListener).toHaveBeenCalledWith('scroll', expect.any(Function), { passive: true })
    })

    it('keeps topic actions compact', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/curricula/CurriculumDetail.vue'), 'utf8')

        expect(source).toContain(`.curriculum-detail__topic-actions :deep(.v-btn) {
    width: 28px;
    min-width: 28px;
    height: 28px;`)
    })

    it('uses a square red marker for exam units', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Dateimanagement',
                    materials: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'PÜ: Dateimanagement',
                            is_exam: true,
                            materials: [],
                        },
                    ],
                },
            ],
        })

        const examUnit = wrapper.find('.curriculum-detail__unit-item--exam')
        const examMarker = examUnit.find('.curriculum-detail__unit-exam-chip')

        expect(examUnit.exists()).toBe(true)
        expect(examMarker.text()).toBe('Leistungsfeststellung')
        expect(examMarker.attributes('variant')).toBe('flat')
        expect(examMarker.attributes('color')).toBe('error')
        expect(examMarker.attributes()).toHaveProperty('tile')
        expect(examMarker.attributes('prepend-icon')).toBe('mdi-clipboard-text-outline')

        const source = readFileSync(resolve('resources/js/pages/admin/teaching/curricula/CurriculumDetail.vue'), 'utf8')

        expect(source).toContain(`.curriculum-detail__unit-item--exam > .curriculum-detail__topic-row {
    align-items: center;`)
    })

    it('opens and closes a topic by clicking its card header', async () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 1,
                    title: 'Grundlagen',
                    materials: [],
                    units: [
                        { id: 11, title: 'Anmelden', materials: [] },
                    ],
                },
            ],
        })
        const topicHeader = wrapper.find('.curriculum-detail__topic-item > .curriculum-detail__topic-row')

        expect(topicHeader.attributes('aria-expanded')).toBe('true')
        expect(wrapper.find('.curriculum-detail__unit-section').exists()).toBe(true)

        await topicHeader.trigger('click')

        expect(topicHeader.attributes('aria-expanded')).toBe('false')
        expect(wrapper.find('.curriculum-detail__unit-section').exists()).toBe(false)

        await topicHeader.trigger('click')

        expect(topicHeader.attributes('aria-expanded')).toBe('true')
        expect(wrapper.find('.curriculum-detail__unit-section').exists()).toBe(true)
        expect(wrapper.find('.curriculum-detail__topic-collapse-toggle').exists()).toBe(false)
    })

    it('expands and collapses all themes from the content toolbar', async () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grundlagen',
                    materials: [],
                    units: [{ id: 'unit-1', title: 'Anmelden', materials: [] }],
                },
                {
                    id: 'topic-2',
                    title: 'Textverarbeitung',
                    materials: [],
                    units: [{ id: 'unit-2', title: 'Formatieren', materials: [] }],
                },
            ],
        })
        const expandAllButton = wrapper.find('.curriculum-detail__topic-expand-all-btn')
        const collapseAllButton = wrapper.find('.curriculum-detail__topic-collapse-all-btn')

        expect(expandAllButton.attributes('aria-label')).toBe('Alle Themen ausklappen')
        expect(expandAllButton.attributes('icon')).toBe('mdi-unfold-more-horizontal')
        expect(collapseAllButton.attributes('aria-label')).toBe('Alle Themen einklappen')
        expect(collapseAllButton.attributes('icon')).toBe('mdi-unfold-less-horizontal')
        expect(expandAllButton.attributes()).toHaveProperty('disabled')
        expect(collapseAllButton.attributes()).not.toHaveProperty('disabled')
        expect(wrapper.findAll('.curriculum-detail__unit-section')).toHaveLength(2)

        await collapseAllButton.trigger('click')

        expect((wrapper.vm as any).topicCollapseStates).toEqual({
            'topic-1': true,
            'topic-2': true,
        })
        expect(wrapper.findAll('.curriculum-detail__unit-section')).toHaveLength(0)
        expect(expandAllButton.attributes()).not.toHaveProperty('disabled')
        expect(collapseAllButton.attributes()).toHaveProperty('disabled')

        await expandAllButton.trigger('click')

        expect((wrapper.vm as any).topicCollapseStates).toEqual({})
        expect(wrapper.findAll('.curriculum-detail__unit-section')).toHaveLength(2)
    })

    it('shows content actions in the topic dropdown menu', async () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 1,
                    title: 'Grundlagen',
                    materials: [],
                    units: [],
                },
            ],
        })

        await wrapper.vm.$nextTick()

        const actionMenu = wrapper.find('.curriculum-detail__topic-action-menu')

        expect(actionMenu.exists()).toBe(true)
        expect(actionMenu.text()).not.toContain('Material hinzufügen')
        expect(actionMenu.text()).toContain('Bearbeiten')
        expect(actionMenu.text()).toContain('Löschen')
        expect(actionMenu.text()).toContain('Einheit hinzufügen')
    })

    it('opens the edit dialog when a unit is clicked', async () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grundlagen',
                    materials: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Anmelden',
                            materials: [],
                        },
                    ],
                },
            ],
        })

        await wrapper.find('.curriculum-detail__unit-item').trigger('click')

        expect(wrapper.findAll('.curriculum-detail__unit-item .curriculum-detail__topic-actions button')).toHaveLength(2)
        expect(wrapper.find('[title="Einheit bearbeiten"]').exists()).toBe(false)
        expect(wrapper.find('.curriculum-detail__unit-dialog-material-btn').text()).toBe('Material hinzufügen')
        expect(wrapper.find('.curriculum-detail__unit-dialog-delete-btn').text()).toBe('Einheit löschen')
        expect((wrapper.vm as any).selectedUnitId).toBe('unit-1')
        expect((wrapper.vm as any).showUnitForm).toBe(true)
        expect((wrapper.vm as any).showUnitFormForTopicId).toBe('topic-1')
        expect((wrapper.vm as any).unitForm).toMatchObject({
            id: 'unit-1',
            topicId: 'topic-1',
            title: 'Anmelden',
        })

        const openContentMaterialDialog = vi
            .spyOn(wrapper.vm as any, 'openContentMaterialDialog')
            .mockResolvedValue(undefined)

        await wrapper.find('.curriculum-detail__unit-dialog-material-btn').trigger('click')

        expect(openContentMaterialDialog).toHaveBeenCalledWith({
            type: 'unit',
            topicId: 'topic-1',
            unitId: 'unit-1',
        })

        await wrapper.find('.curriculum-detail__unit-dialog-delete-btn').trigger('click')

        expect((wrapper.vm as any).contentDeleteDialogOpen).toBe(true)
        expect((wrapper.vm as any).contentToDelete).toEqual({
            type: 'unit',
            title: 'Anmelden',
            topicId: 'topic-1',
            unitId: 'unit-1',
        })
    })

    it('submits the unit form with Enter and cancels it with Escape', async () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grundlagen',
                    materials: [],
                    units: [{ id: 'unit-1', title: 'Anmelden', materials: [] }],
                },
            ],
        })

        await wrapper.find('.curriculum-detail__unit-item').trigger('click')

        const persistCurriculum = vi.spyOn(wrapper.vm as any, 'persistCurriculum').mockResolvedValue(null)
        const saveButton = wrapper.find(
            '.curriculum-detail__unit-dialog-form .curriculum-detail__editor-dialog-save-btn',
        )

        expect(saveButton.attributes('type')).toBe('submit')

        await wrapper.find('.curriculum-detail__unit-dialog-form').trigger('submit')

        await vi.waitFor(() => {
            expect(persistCurriculum).toHaveBeenCalledOnce()
        })

        await wrapper.find('.curriculum-detail__unit-dialog-card').trigger('keydown', { key: 'Escape' })

        expect((wrapper.vm as any).showUnitForm).toBe(false)
        expect((wrapper.vm as any).showUnitFormForTopicId).toBeNull()
        expect((wrapper.vm as any).unitForm).toMatchObject({
            id: null,
            topicId: null,
            title: '',
        })
    })
})

describe('Curriculum content copy', () => {
    it('shows content copy instead of PDF printing when the curriculum is empty', () => {
        const emptyCurriculumWrapper = mountCurriculumDetail({ topics: [] })
        const filledCurriculumWrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    units: [],
                },
            ],
        })

        expect(emptyCurriculumWrapper.find('.curriculum-detail__copy-content-btn').exists()).toBe(true)
        expect(emptyCurriculumWrapper.find('.curriculum-detail__print-btn').exists()).toBe(false)
        expect(filledCurriculumWrapper.find('.curriculum-detail__copy-content-btn').exists()).toBe(false)
        expect(filledCurriculumWrapper.find('.curriculum-detail__print-btn').exists()).toBe(true)
    })

    it('loads only other curricula containing content for the persistent copy dialog', async () => {
        const wrapper = mountCurriculumDetail({ id: 6, topics: [] })
        const originalAxios = (globalThis as any).axios
        const getMock = vi.fn().mockResolvedValue({
            data: {
                data: [
                    { id: 6, title: 'DGB 3', topics: [] },
                    {
                        id: 3,
                        title: 'DGB 1',
                        topics: [
                            {
                                id: 'topic-1',
                                title: 'Grundlagen',
                                units: [{ id: 'unit-1', title: 'Anmeldung' }],
                            },
                        ],
                    },
                    { id: 7, title: 'DGB 4', topics: [] },
                ],
            },
        })

        ;(globalThis as any).axios = { get: getMock }

        try {
            await (wrapper.vm as any).openCurriculumContentCopyDialog()

            expect(getMock).toHaveBeenCalledWith('/api/admin/teaching/curricula', {
                params: {
                    page: 1,
                    per_page: 100,
                },
            })
            expect((wrapper.vm as any).curriculumContentCopyDialogOpen).toBe(true)
            expect((wrapper.vm as any).curriculumContentSources.map((curriculum: any) => curriculum.id)).toEqual([3])
            expect((wrapper.vm as any).curriculumContentSourceOptions).toEqual([
                {
                    title: 'DGB 1 (1 Thema · 1 Einheit)',
                    value: 3,
                },
            ])

            const source = readFileSync(resolve('resources/js/pages/admin/teaching/curricula/CurriculumDetail.vue'), 'utf8')
            expect(source).toContain('<v-dialog v-model="curriculumContentCopyDialogOpen" max-width="560" persistent>')
        } finally {
            ;(globalThis as any).axios = originalAxios
        }
    })

    it('copies the selected curriculum content into the empty curriculum', async () => {
        const wrapper = mountCurriculumDetail({ id: 6, title: 'DGB 3', topics: [] })
        const originalAxios = (globalThis as any).axios
        const updatedCurriculum = {
            id: 6,
            title: 'DGB 3',
            topics: [
                {
                    id: 'copied-topic',
                    title: 'Grundlagen',
                    units: [],
                },
            ],
        }
        const postMock = vi.fn().mockResolvedValue({
            data: {
                data: updatedCurriculum,
            },
        })

        ;(globalThis as any).axios = { post: postMock }
        await wrapper.setData({
            curriculumContentCopyDialogOpen: true,
            selectedCurriculumContentSourceId: 3,
        })

        try {
            await (wrapper.vm as any).copyCurriculumContent()

            expect(postMock).toHaveBeenCalledWith('/api/admin/teaching/curricula/6/copy-content', {
                source_curriculum_id: 3,
            })
            expect(wrapper.emitted('updated')?.[0]).toEqual([updatedCurriculum])
            expect((wrapper.vm as any).curriculumContentCopyDialogOpen).toBe(false)
            expect((wrapper.vm as any).selectedCurriculumContentSourceId).toBeNull()
        } finally {
            ;(globalThis as any).axios = originalAxios
        }
    })

    it('copies the selected curriculum content when Enter is pressed', async () => {
        const wrapper = mountCurriculumDetail({ id: 6, title: 'DGB 3', topics: [] })
        const originalAxios = (globalThis as any).axios
        const postMock = vi.fn().mockResolvedValue({
            data: {
                data: {
                    id: 6,
                    title: 'DGB 3',
                    topics: [{ id: 'copied-topic', title: 'Grundlagen', units: [] }],
                },
            },
        })

        ;(globalThis as any).axios = { post: postMock }
        await wrapper.setData({
            curriculumContentCopyDialogOpen: true,
            selectedCurriculumContentSourceId: 3,
        })

        try {
            await wrapper.find('.curriculum-detail__copy-content-dialog-card').trigger('keydown', { key: 'Enter' })

            await vi.waitFor(() => {
                expect(postMock).toHaveBeenCalledWith('/api/admin/teaching/curricula/6/copy-content', {
                    source_curriculum_id: 3,
                })
            })
        } finally {
            ;(globalThis as any).axios = originalAxios
        }
    })

    it('cancels curriculum content copying when Escape is pressed', async () => {
        const wrapper = mountCurriculumDetail({ id: 6, topics: [] })

        await wrapper.setData({
            curriculumContentCopyDialogOpen: true,
            selectedCurriculumContentSourceId: 3,
            curriculumContentCopyError: 'Fehler',
        })

        await wrapper.find('.curriculum-detail__copy-content-dialog-card').trigger('keydown', { key: 'Escape' })

        expect((wrapper.vm as any).curriculumContentCopyDialogOpen).toBe(false)
        expect((wrapper.vm as any).selectedCurriculumContentSourceId).toBeNull()
        expect((wrapper.vm as any).curriculumContentCopyError).toBeNull()
    })
})

describe.skip('CurriculumDetail removed calendar behavior', () => {
    afterEach(() => {
        loadDocumentsSpy.mockClear()
        openMaterialAttachmentDialogSpy.mockClear()
    })

    afterAll(() => {
        loadDocumentsSpy.mockRestore()
        openMaterialAttachmentDialogSpy.mockRestore()
    })

    it('switches between weekday and compact week cards', async () => {
        const wrapper = mountCurriculumDetail()

        expect(loadDocumentsSpy).toHaveBeenCalledTimes(1)
        expect(wrapper.text()).toContain('Mit Tagen')
        expect(wrapper.text()).toContain('Ohne Tage')
        expect(wrapper.text()).toContain('Immer zeigen')
        expect(wrapper.text()).toContain('Einklappen')
        expect(wrapper.vm.showWeekdays).toBe(true)
        expect((wrapper.vm as any).collapseFullMonths).toBe(true)
        expect(wrapper.findAll('.curriculum-detail__week-days').length).toBeGreaterThan(0)
        expect(wrapper.find('.curriculum-detail__calendar-scroll').exists()).toBe(true)
        expect(wrapper.find('.curriculum-detail__side-card--content').exists()).toBe(true)
        expect(wrapper.find('.curriculum-detail__side-card--content.curriculum-detail__side-card--scrollable').exists()).toBe(false)
        expect(wrapper.find('.curriculum-detail__side-card--documents.curriculum-detail__side-card--scrollable').exists()).toBe(true)
        expect(wrapper.findAll('button').filter((button) => button.text().trim() === 'Thema')).toHaveLength(2)
        expect(wrapper.find('.curriculum-detail__calendar').classes()).not.toContain('curriculum-detail__calendar--compact')

        await wrapper.setData({ weekDisplayMode: 'compact' })

        expect(wrapper.vm.showWeekdays).toBe(false)
        expect(wrapper.findAll('.curriculum-detail__week-days')).toHaveLength(0)
        expect(wrapper.find('.curriculum-detail__body').classes()).toContain('curriculum-detail__body--compact-calendar')
        expect(wrapper.find('.curriculum-detail__calendar').classes()).toContain('curriculum-detail__calendar--compact')
        expect(wrapper.find('.curriculum-detail__week').classes()).toContain('curriculum-detail__week--compact')
    })

    it('keeps the compact width rules in the component source', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/curricula/CurriculumDetail.vue'), 'utf8')

        expect(source).toContain("v-if=\"showWeekdays\"")
        expect(source).toContain("'curriculum-detail__body--compact-calendar': isCompactWeekView")
        expect(source).toContain('class="curriculum-detail__calendar-scroll pa-2"')
        expect(source).toContain('ref="calendarScroll"')
        expect(source).toContain(':data-month-key="month.assignmentKey"')
        expect(source).toContain(':data-week-key="week.weekKey"')
        expect(source).toContain("'curriculum-detail__calendar--compact': isCompactWeekView")
        expect(source).toContain('v-model="collapseFullMonths"')
        expect(source).toContain('Volle Monate')
        expect(source).toContain('Freie Tage übernehmen')
        expect(source).toContain('v-if="hasFreeWeeksTemplate"')
        expect(source).toContain(':disabled="!canApplyFreeWeeksTemplate"')
        expect(source).toContain('@click="applyFreeWeeksTemplate"')
        expect(source).toContain('Curriculum exportieren')
        expect(source).toContain('@click="exportCurriculum"')
        expect(source).toContain("/export/json")
        expect(source).toContain('PDF drucken')
        expect(source).toContain('@click="printCurriculumPdf"')
        expect(source).toContain("/export/pdf")
        expect(source).toContain('class="curriculum-detail__side-card curriculum-detail__side-card--documents curriculum-detail__side-card--scrollable"')
        expect(source).toContain('class="curriculum-detail__side-card curriculum-detail__side-card--content"')
        expect(source).toContain('class="curriculum-detail__content-footer"')
        expect(source).not.toContain('>Verteilen')
        expect(source).toContain('.curriculum-detail__calendar-scroll {')
        expect(source).toContain('.curriculum-detail__side-card--scrollable {')
        expect(source).toContain('overflow-y: auto;')
        expect(source).toContain('.curriculum-detail__body--compact-calendar {')
        expect(source).toContain('grid-template-columns: auto clamp(420px, 36vw, 640px) minmax(420px, 1fr);')
        expect(source).toContain(".curriculum-detail__calendar--compact {")
        expect(source).toContain('width: clamp(250px, 18vw, 300px);')
        expect(source).toContain('.curriculum-detail__calendar--compact .curriculum-detail__month {')
        expect(source).toContain('class="curriculum-detail__week-status"')
        expect(source).toContain('class="curriculum-detail__week-status-icon"')
        expect(source).toContain('mdi-check-circle')
        expect(source).toContain('class="curriculum-detail__topic-entry"')
        expect(source).toContain(":aria-expanded=\"topic.units.length ? !isTopicCollapsed(topic.id) : undefined\"")
        expect(source).toContain('@click="topic.units.length && toggleTopicCollapse(topic.id)"')
        expect(source).not.toContain('class="curriculum-detail__topic-collapse-toggle"')
        expect(source).toContain('v-if="!isTopicCollapsed(topic.id)"')
        expect(source).toContain("'curriculum-detail__month--collapsed': shouldCollapseMonth(month)")
        expect(source).toContain('@click="toggleMonthCollapse(month)"')
        expect(source).toContain('v-if="!shouldCollapseMonth(month)" class="curriculum-detail__weeks"')
        expect(source).toContain('v-if="!shouldCollapseMonth(month)" class="curriculum-detail__month-topics-label"')
        expect(source).toContain('.curriculum-detail__week--with-topics {')
        expect(source).toContain(':style="calendarHighlightStyle"')
        expect(source).toContain("'--calendar-highlight-accent': this.highlightedTopicAccentColor")
        expect(source).toContain('.curriculum-detail__week-status-icon {')
        expect(source).toContain('color: #16a34a !important;')
        expect(source).toContain('class="curriculum-detail__week-topic-label"')
        expect(source).toContain('class="curriculum-detail__week-topic-label-topic"')
        expect(source).toContain('class="curriculum-detail__week-topic-label-unit"')
        expect(source).toContain('.curriculum-detail__week-topic-label {')
        expect(source).toContain('display: inline-block;')
        expect(source).toContain('background: rgba(250, 204, 21, 0.82);')
        expect(source).toContain('line-height: 1;')
        expect(source).toContain('.curriculum-detail__week-topic-label-topic {')
        expect(source).toContain('.curriculum-detail__week-topic-label-unit {')
        expect(source).toContain('.curriculum-detail__overview-entry--exam {')
        expect(source).toContain('color: #4f46e5;')
        expect(source).toContain('v-for="group in monthOverviewGroups(month)"')
        expect(source).toContain('class="curriculum-detail__month-topic-line"')
        expect(source).toContain('.curriculum-detail__month-topic-name {')
        expect(source).toContain('.curriculum-detail__month-topic-units .curriculum-detail__overview-entry:not(:last-child) {')
        expect(source).toContain('margin-right: 0.3rem;')
        expect(source).toContain('class="curriculum-detail__unit-title-row"')
        expect(source).toContain('class="curriculum-detail__unit-exam-chip"')
        expect(source).toContain('Leistungsfeststellung')
        expect(source).toContain("'curriculum-detail__unit-item--exam': unit.is_exam")
        expect(source).toContain('.curriculum-detail__unit-item--exam {')
        expect(source).toContain('rgba(245, 158, 11')
        expect(source).toContain('.curriculum-detail__month-topic-unit {')
        expect(source).toContain('font-weight: 500;')
        expect(source).toContain('.curriculum-detail__unit-item--selected {')
        expect(source).toContain('border-color: rgba(129, 140, 248, 0.52);')
        expect(source).toContain('linear-gradient(180deg, rgba(224, 231, 255, 0.98), rgba(199, 210, 254, 0.94));')
        expect(source).toContain('0 0 22px rgba(79, 70, 229, 0.24);')
        expect(source).toContain('border-color: rgba(79, 70, 229, 0.62);')
        expect(source).toContain('background: rgba(79, 70, 229, 0.3);')
        expect(source).toContain('color: #1e1b4b !important;')
        expect(source).toContain('@click="openSelectedUnitForm(topic.id, unit)"')
        expect(source).toContain('class="curriculum-detail__topic-actions" @click.stop')
        expect(source).toContain('.curriculum-detail__topic-entry {')
        expect(source).toContain('justify-content: space-between;')
        expect(source).toContain('align-self: center;')
        expect(source).toContain('flex-shrink: 0;')
        expect(source).toContain('width: 100%;')
        expect(source).toContain('.curriculum-detail__topic-item > .curriculum-detail__topic-row--collapsible {')
        expect(source).toContain('class="curriculum-detail__topic-assignment-panel"')
        expect(source).toContain('@click.stop>')
        expect(source).toContain(":variant=\"activeTopicAssignmentType === 'weeks' ? 'flat' : 'tonal'\"")
        expect(source).toContain(":variant=\"activeTopicAssignmentType === 'month' ? 'flat' : 'tonal'\"")
        expect(source).toContain(":variant=\"activeTopicAssignmentType === 'none' ? 'flat' : 'tonal'\"")
        expect(source).toContain('.curriculum-detail__week--with-topics:hover {')
        expect(source).toContain('.curriculum-detail__week--with-exams {')
        expect(source).toContain('.curriculum-detail__week--with-exams .curriculum-detail__week-topics {')
        expect(source).toContain('.curriculum-detail__month--collapsed .curriculum-detail__month-topics {')
        expect(source).toContain('.curriculum-detail__month--collapsed .curriculum-detail__month-topic-unit {')
        expect(source).toContain('.curriculum-detail__topic-assignment-options :deep(.v-btn) {')
        expect(source).toContain('color: #cbd5e1 !important;')
        expect(source).toContain('.curriculum-detail__topic-assignment-options :deep(.v-btn--variant-flat) {')
        expect(source).toContain('border: 1px solid rgba(199, 210, 254, 0.42) !important;')
        expect(source).toContain('0 8px 18px rgba(79, 70, 229, 0.28);')
        expect(source).toContain('.curriculum-detail__assignment-week-controls :deep(.v-btn) {')
        expect(source).toContain('scrollHighlightedCalendarIntoView() {')
        expect(source).toContain('const topOffset = Math.min(Math.round(containerRect.height * 0.22), 180)')
        expect(source).toContain('this.$nextTick(() => this.scrollHighlightedCalendarIntoView())')
        expect(source).toContain('this.activeTopicAssignmentType = assignmentType ?? topic.assignment_type')
        expect(source).toContain('this.activeTopicAssignmentType = assignmentType ?? unit.assignment_type')
        expect(source.lastIndexOf('.curriculum-detail__week--topic-selected,')).toBeGreaterThan(source.indexOf('.curriculum-detail__week--with-exams {'))
        expect(source).not.toContain('.curriculum-detail__body--compact-calendar .curriculum-detail__side-card--content {')
    })

    it('allows selecting and deselecting units independently inside a topic', async () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Satzbau',
                            is_exam: false,
                            assignment_type: 'none',
                            month_keys: [],
                            week_keys: [],
                        },
                    ],
                },
            ],
        })

        const unitItem = wrapper.find('.curriculum-detail__unit-item')
        const topicItem = wrapper.find('.curriculum-detail__topic-item')

        expect(unitItem.exists()).toBe(true)
        expect(topicItem.exists()).toBe(true)
        expect(topicItem.classes()).not.toContain('curriculum-detail__topic-item--selected')
        expect(unitItem.classes()).not.toContain('curriculum-detail__unit-item--selected')

        await unitItem.trigger('click')

        expect((wrapper.vm as any).selectedTopicId).toBe('topic-1')
        expect((wrapper.vm as any).selectedUnitTopicId).toBe('topic-1')
        expect((wrapper.vm as any).selectedUnitId).toBe('unit-1')
        expect(topicItem.classes()).not.toContain('curriculum-detail__topic-item--selected')
        expect(unitItem.classes()).toContain('curriculum-detail__unit-item--selected')

        await unitItem.trigger('click')

        expect((wrapper.vm as any).selectedTopicId).toBeNull()
        expect((wrapper.vm as any).selectedUnitTopicId).toBeNull()
        expect((wrapper.vm as any).selectedUnitId).toBeNull()
        expect(topicItem.classes()).not.toContain('curriculum-detail__topic-item--selected')
        expect(unitItem.classes()).not.toContain('curriculum-detail__unit-item--selected')
    })

    it('opens the topic editor without toggling topic selection', async () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [],
                },
            ],
        })

        const topicItem = wrapper.find('.curriculum-detail__topic-item')
        const topicActionButtons = wrapper.findAll('.curriculum-detail__topic-actions button')

        expect(topicItem.exists()).toBe(true)
        expect(topicActionButtons).toHaveLength(6)
        expect((wrapper.vm as any).selectedTopicId).toBeNull()
        expect((wrapper.vm as any).showTopicForm).toBe(false)

        await topicActionButtons[4].trigger('click')

        expect((wrapper.vm as any).showTopicForm).toBe(true)
        expect((wrapper.vm as any).topicForm).toMatchObject({
            id: 'topic-1',
            title: 'Grammatik',
        })
        expect((wrapper.vm as any).selectedTopicId).toBeNull()
        expect(topicItem.classes()).not.toContain('curriculum-detail__topic-item--selected')
    })

    it('collapses a topic from its card header without selecting it', async () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Satzbau',
                            is_exam: false,
                            assignment_type: 'none',
                            month_keys: [],
                            week_keys: [],
                        },
                    ],
                },
            ],
        })

        const topicItem = wrapper.find('.curriculum-detail__topic-item')
        const topicHeader = topicItem.find('.curriculum-detail__topic-row')

        expect(topicItem.find('.curriculum-detail__unit-section').exists()).toBe(true)
        expect((wrapper.vm as any).isTopicCollapsed('topic-1')).toBe(false)

        await topicHeader.trigger('click')

        expect((wrapper.vm as any).isTopicCollapsed('topic-1')).toBe(true)
        expect((wrapper.vm as any).selectedTopicId).toBeNull()
        expect(topicItem.find('.curriculum-detail__unit-section').exists()).toBe(false)

        await topicHeader.trigger('click')

        expect((wrapper.vm as any).isTopicCollapsed('topic-1')).toBe(false)
        expect(topicItem.find('.curriculum-detail__unit-section').exists()).toBe(true)
    })

    it('hides the external collapse toggle for topics without units', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [],
                },
            ],
        })

        expect(wrapper.find('.curriculum-detail__topic-collapse-toggle').exists()).toBe(false)
    })

    it('shows the unit count in the topic title row', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Satzbau',
                            is_exam: false,
                            assignment_type: 'none',
                            month_keys: [],
                            week_keys: [],
                        },
                        {
                            id: 'unit-2',
                            title: 'Nomen',
                            is_exam: false,
                            assignment_type: 'none',
                            month_keys: [],
                            week_keys: [],
                        },
                    ],
                },
            ],
        })

        const titleRow = wrapper.find('.curriculum-detail__topic-title-row')

        expect(titleRow.find('.curriculum-detail__topic-unit-count').text()).toBe('2 Einheiten')
        expect(wrapper.find('.curriculum-detail__topic-collapse-toggle .curriculum-detail__topic-unit-count').exists()).toBe(false)
    })

    it('renders the topic editor dialog while the Lehrpläne card is visible', async () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [],
                },
            ],
        })

        expect(wrapper.find('.curriculum-detail__side-card--documents').exists()).toBe(true)
        expect(wrapper.text()).not.toContain('Thema bearbeiten')

        await wrapper.setData({
            showTopicForm: true,
            topicForm: {
                id: 'topic-1',
                title: 'Grammatik',
            },
        })

        expect(wrapper.text()).toContain('Thema bearbeiten')
        expect(wrapper.text()).toContain('Thema speichern')
    })

    it('creates a topic with Enter and cancels the dialog with Escape', async () => {
        const wrapper = mountCurriculumDetail()
        const saveTopicSpy = vi.spyOn(wrapper.vm as any, 'saveTopic').mockResolvedValue(undefined)

        await wrapper.setData({
            showTopicForm: true,
            topicForm: {
                id: null,
                title: 'Schreiben',
            },
        })

        const topicForm = wrapper.find('form.curriculum-detail__topic-form')

        expect(topicForm.exists()).toBe(true)
        expect(topicForm.find('button[type="submit"]').text()).toContain('Thema anlegen')

        await topicForm.trigger('submit')

        expect(saveTopicSpy).toHaveBeenCalledOnce()

        await topicForm.trigger('keydown', { key: 'Escape' })

        expect((wrapper.vm as any).showTopicForm).toBe(false)
        expect((wrapper.vm as any).topicForm).toMatchObject({ id: null, title: '' })
    })

    it('does not open the material selector for topics', async () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    materials: [],
                    units: [],
                },
            ],
        })

        await (wrapper.vm as any).openContentMaterialDialog({
            type: 'topic',
            topicId: 'topic-1',
        })

        expect((wrapper.vm as any).contentMaterialDialogOpen).toBe(false)
        expect((wrapper.vm as any).contentMaterialTarget).toBeNull()
    })

    it('loads the workspace tree through curriculum material endpoints', async () => {
        const wrapper = mountCurriculumDetail()
        const originalAxios = (globalThis as any).axios
        const getMock = vi.fn().mockResolvedValue({
            data: {
                classification_tree: [
                    {
                        id: 11,
                        name: 'Deutsch',
                        topics: [
                            {
                                id: 21,
                                name: 'Grammatik',
                                units: [
                                    {
                                        id: 31,
                                        name: 'Nebensätze',
                                    },
                                ],
                            },
                        ],
                    },
                ],
            },
        })
        ;(globalThis as any).axios = {
            get: getMock,
        }

        try {
            await (wrapper.vm as any).ensureContentMaterialClassificationTree()

            expect(getMock).toHaveBeenCalledWith('/api/admin/teaching/curricula/15/materials/config')
            expect((wrapper.vm as any).contentMaterialClassificationTree).toEqual([
                expect.objectContaining({
                    name: 'Deutsch',
                    topics: [
                        expect.objectContaining({
                            name: 'Grammatik',
                            units: [
                                expect.objectContaining({
                                    name: 'Nebensätze',
                                }),
                            ],
                        }),
                    ],
                }),
            ])
        } finally {
            ;(globalThis as any).axios = originalAxios
        }
    })

    it('rewrites preview attachment urls to curriculum material routes', () => {
        const wrapper = mountCurriculumDetail()

        const normalizedMaterial = (wrapper.vm as any).normalizeAttachedMaterial({
            id: 77,
            title: 'Nebensätze Arbeitsblatt',
            subject: 'Deutsch',
            area: 'Grammatik',
            unit: 'Nebensätze',
            attachments_count: 1,
            attachments: [
                {
                    id: 501,
                    name: 'Nebensaetze.pdf',
                    mime_type: 'application/pdf',
                    preview_url: '/api/admin/materials/attachments/501/preview',
                    download_url: '/api/admin/materials/attachments/501/download',
                },
            ],
        })

        expect(normalizedMaterial.attachments).toEqual([
            expect.objectContaining({
                id: 501,
                name: 'Nebensaetze.pdf',
                preview_url: '/api/admin/teaching/curricula/15/materials/attachments/501/preview',
                download_url: '/api/admin/teaching/curricula/15/materials/attachments/501/download',
            }),
        ])
    })

    it('opens content material previews in fullscreen by default', () => {
        const wrapper = mountCurriculumDetail()
        const material = (wrapper.vm as any).normalizeAttachedMaterial({
            id: 77,
            title: 'Nebensätze Arbeitsblatt',
            subject: 'Deutsch',
            area: 'Grammatik',
            unit: 'Nebensätze',
            attachments_count: 2,
            attachments: [
                {
                    id: 501,
                    name: 'Nebensaetze.pdf',
                    mime_type: 'application/pdf',
                },
                {
                    id: 502,
                    name: 'Nebensaetze.png',
                    mime_type: 'image/png',
                },
            ],
        })

        ;(wrapper.vm as any).selectContentMaterialPreview(material)

        expect((wrapper.vm as any).contentMaterialPreviewAttachment).toBeNull()
        expect((wrapper.vm as any).contentMaterialPreviewDialogOpen).toBe(false)

        ;(wrapper.vm as any).openContentMaterialPreview(material.attachments[1])

        expect((wrapper.vm as any).contentMaterialPreviewDialogOpen).toBe(true)
        expect((wrapper.vm as any).contentMaterialPreviewFullscreen).toBe(true)
        expect((wrapper.vm as any).contentMaterialPreviewAttachment).toEqual(
            expect.objectContaining({
                id: 502,
                name: 'Nebensaetze.png',
            }),
        )
        expect((wrapper.vm as any).contentMaterialPreviewIsImage).toBe(true)
    })

    it('shows download actions for content material attachments', async () => {
        const wrapper = mountCurriculumDetail()
        const material = (wrapper.vm as any).normalizeAttachedMaterial({
            id: 77,
            title: 'Nebensätze Arbeitsblatt',
            subject: 'Deutsch',
            area: 'Grammatik',
            unit: 'Nebensätze',
            attachments_count: 1,
            attachments: [
                {
                    id: 501,
                    name: 'Nebensaetze.pdf',
                    mime_type: 'application/pdf',
                    preview_url: '/api/admin/materials/attachments/501/preview',
                    download_url: '/api/admin/materials/attachments/501/download',
                },
            ],
        })

        await wrapper.setData({
            contentMaterialDialogOpen: true,
            contentMaterialPreviewCard: material,
        })

        const attachmentButtons = wrapper.findAll('button').map((button) => button.text().trim())

        expect(attachmentButtons).toContain('Vorschau')
        expect(attachmentButtons).toContain('Herunterladen')
    })

    it('keeps the selected content material attachment downloadable in fullscreen preview', async () => {
        const wrapper = mountCurriculumDetail()
        const material = (wrapper.vm as any).normalizeAttachedMaterial({
            id: 77,
            title: 'Nebensätze Arbeitsblatt',
            subject: 'Deutsch',
            area: 'Grammatik',
            unit: 'Nebensätze',
            attachments_count: 1,
            attachments: [
                {
                    id: 501,
                    name: 'Nebensaetze.pdf',
                    mime_type: 'application/pdf',
                    preview_url: '/api/admin/materials/attachments/501/preview',
                    download_url: '/api/admin/materials/attachments/501/download',
                },
            ],
        })

        await wrapper.setData({
            contentMaterialPreviewCard: material,
            contentMaterialPreviewAttachmentId: 501,
            contentMaterialPreviewDialogOpen: true,
        })

        expect((wrapper.vm as any).contentMaterialPreviewDownloadUrl).toBe('/api/admin/teaching/curricula/15/materials/attachments/501/download')
        expect(wrapper.findAll('button').map((button) => button.text().trim())).toContain('Herunterladen')
    })

    it('downloads content material attachments via axios instead of navigating to the route', async () => {
        const wrapper = mountCurriculumDetail()
        const originalAxios = (globalThis as any).axios
        const getMock = vi.fn().mockResolvedValue({
            data: new Blob(['pdf-content'], { type: 'application/pdf' }),
            headers: {
                'content-disposition': 'attachment; filename="Lehrplan.pdf"',
            },
        })
        const originalCreateObjectURL = URL.createObjectURL
        const originalRevokeObjectURL = URL.revokeObjectURL
        const originalCreateElement = document.createElement.bind(document)
        const createObjectURLMock = vi.fn(() => 'blob:preview')
        const revokeObjectURLMock = vi.fn()
        const linkClick = vi.fn()
        let createdLink: HTMLAnchorElement | null = null

        const createElementSpy = vi.spyOn(document, 'createElement').mockImplementation(((tagName: string) => {
            const element = originalCreateElement(tagName)
            if (tagName.toLowerCase() === 'a') {
                createdLink = element as HTMLAnchorElement
                ;(createdLink as any).click = linkClick
            }

            return element
        }) as typeof document.createElement)

        ;(globalThis as any).axios = { get: getMock }
        URL.createObjectURL = createObjectURLMock
        URL.revokeObjectURL = revokeObjectURLMock

        try {
            await (wrapper.vm as any).downloadContentMaterialAttachment({
                id: 501,
                name: 'Fallback.pdf',
                download_url: '/api/admin/teaching/curricula/15/materials/attachments/501/download',
            })

            expect(getMock).toHaveBeenCalledWith('/api/admin/teaching/curricula/15/materials/attachments/501/download', {
                responseType: 'blob',
            })
            expect(createObjectURLMock).toHaveBeenCalledTimes(1)
            expect(createdLink?.download).toBe('Lehrplan.pdf')
            expect(linkClick).toHaveBeenCalledTimes(1)
            expect(revokeObjectURLMock).toHaveBeenCalledWith('blob:preview')
        } finally {
            ;(globalThis as any).axios = originalAxios
            URL.createObjectURL = originalCreateObjectURL
            URL.revokeObjectURL = originalRevokeObjectURL
            createElementSpy.mockRestore()
        }
    })

    it('passes the selected shared source when loading content material results', async () => {
        const wrapper = mountCurriculumDetail()
        const originalAxios = (globalThis as any).axios
        const getMock = vi.fn().mockResolvedValue({
            data: {
                data: [],
            },
        })

        ;(globalThis as any).axios = { get: getMock }

        try {
            await wrapper.setData({
                contentMaterialDialogMode: 'shared',
                selectedContentMaterialSource: {
                    user_id: 42,
                    user_name: 'Quelle',
                },
            })

            await (wrapper.vm as any).loadContentMaterialWorkspaceResults({
                subject: 'Mathematik',
                topic: 'Stundenplan',
                unit: '',
            })

            expect(getMock).toHaveBeenCalledWith('/api/admin/teaching/curricula/15/materials/cards', {
                params: {
                    subject: 'Mathematik',
                    topic: 'Stundenplan',
                    unit: '',
                    per_page: 20,
                    shared_only: 1,
                    source_user_id: 42,
                },
            })
        } finally {
            ;(globalThis as any).axios = originalAxios
        }
    })

    it('downloads the curriculum export json via axios instead of navigating to the route', async () => {
        const wrapper = mountCurriculumDetail({
            title: 'Deutsch 5A',
        })
        const originalAxios = (globalThis as any).axios
        const getMock = vi.fn().mockResolvedValue({
            data: new Blob(['{"curriculum_key":"abc"}'], { type: 'application/json' }),
            headers: {
                'content-disposition': 'attachment; filename="Curriculum_Deutsch_5A.json"',
            },
        })
        const originalCreateObjectURL = URL.createObjectURL
        const originalRevokeObjectURL = URL.revokeObjectURL
        const originalCreateElement = document.createElement.bind(document)
        const createObjectURLMock = vi.fn(() => 'blob:curriculum-export')
        const revokeObjectURLMock = vi.fn()
        const linkClick = vi.fn()
        let createdLink: HTMLAnchorElement | null = null

        const createElementSpy = vi.spyOn(document, 'createElement').mockImplementation(((tagName: string) => {
            const element = originalCreateElement(tagName)
            if (tagName.toLowerCase() === 'a') {
                createdLink = element as HTMLAnchorElement
                ;(createdLink as any).click = linkClick
            }

            return element
        }) as typeof document.createElement)

        ;(globalThis as any).axios = { get: getMock }
        URL.createObjectURL = createObjectURLMock
        URL.revokeObjectURL = revokeObjectURLMock

        try {
            await (wrapper.vm as any).exportCurriculum()

            expect(getMock).toHaveBeenCalledWith('/api/admin/teaching/curricula/15/export/json', {
                responseType: 'blob',
            })
            expect(createObjectURLMock).toHaveBeenCalledTimes(1)
            expect(createdLink?.download).toBe('Curriculum_Deutsch_5A.json')
            expect(linkClick).toHaveBeenCalledTimes(1)
            expect(revokeObjectURLMock).toHaveBeenCalledWith('blob:curriculum-export')
            expect((wrapper.vm as any).isExportingCurriculum).toBe(false)
        } finally {
            ;(globalThis as any).axios = originalAxios
            URL.createObjectURL = originalCreateObjectURL
            URL.revokeObjectURL = originalRevokeObjectURL
            createElementSpy.mockRestore()
        }
    })

    it('creates and downloads a clear curriculum PDF from the detail page', async () => {
        const wrapper = mountCurriculumDetail({
            title: 'Deutsch 5A',
        })
        const originalAxios = (globalThis as any).axios
        const response = {
            data: new Blob(['pdf'], { type: 'application/pdf' }),
            headers: {
                'content-disposition': 'attachment; filename="Curriculum_Deutsch_5A.pdf"',
            },
        }
        const getMock = vi.fn().mockResolvedValue(response)
        const downloadSpy = vi.spyOn(wrapper.vm as any, 'downloadCurriculumResponse').mockImplementation(() => {})

        ;(globalThis as any).axios = { get: getMock }

        try {
            await (wrapper.vm as any).printCurriculumPdf()

            expect(getMock).toHaveBeenCalledWith('/api/admin/teaching/curricula/15/export/pdf', {
                responseType: 'blob',
            })
            expect(downloadSpy).toHaveBeenCalledWith(response, 'Curriculum_Deutsch 5A.pdf', 'application/pdf')
            expect((wrapper.vm as any).isPrintingCurriculum).toBe(false)
        } finally {
            ;(globalThis as any).axios = originalAxios
        }
    })

    it('does not render legacy topic materials in the overview', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    materials: [
                        {
                            id: 77,
                            title: 'Nebensätze Arbeitsblatt',
                            subject: 'Deutsch',
                            topic: 'Grammatik',
                            unit: 'Nebensätze',
                            attachments_count: 2,
                        },
                    ],
                    units: [],
                },
            ],
        })

        const materialActions = wrapper.find('.curriculum-detail__attached-material-actions')

        expect(materialActions.exists()).toBe(false)
    })

    it('loads attachments for a linked material from the curriculum overview', async () => {
        const wrapper = mountCurriculumDetail()
        const originalAxios = (globalThis as any).axios
        const getMock = vi.fn().mockResolvedValue({
            data: {
                data: {
                    id: 77,
                    title: 'Nebensätze Arbeitsblatt',
                    subject: 'Deutsch',
                    area: 'Grammatik',
                    unit: 'Nebensätze',
                    attachments_count: 2,
                    attachments: [
                        {
                            id: 501,
                            name: 'Teil A.pdf',
                            mime_type: 'application/pdf',
                            size_bytes: 1024,
                        },
                        {
                            id: 502,
                            name: 'Teil B.png',
                            mime_type: 'image/png',
                            size_bytes: 2048,
                        },
                    ],
                },
            },
        })
        ;(globalThis as any).axios = {
            get: getMock,
        }

        try {
            await (wrapper.vm as any).openAttachedMaterialDialog({
                id: 77,
                title: 'Nebensätze Arbeitsblatt',
                subject: 'Deutsch',
                topic: 'Grammatik',
                unit: 'Nebensätze',
                attachments_count: 2,
            })

            expect(getMock).toHaveBeenCalledWith('/api/admin/teaching/curricula/15/materials/cards/77')
            expect((wrapper.vm as any).attachedMaterialDialogOpen).toBe(true)
            expect((wrapper.vm as any).attachedMaterialDialogLoading).toBe(false)
            expect((wrapper.vm as any).contentMaterialPreviewAttachments).toEqual([
                expect.objectContaining({
                    id: 501,
                    name: 'Teil A.pdf',
                    preview_url: '/api/admin/teaching/curricula/15/materials/attachments/501/preview',
                    download_url: '/api/admin/teaching/curricula/15/materials/attachments/501/download',
                }),
                expect.objectContaining({
                    id: 502,
                    name: 'Teil B.png',
                }),
            ])
            expect(wrapper.text()).toContain('Für jeden Anhang stehen Vorschau und Download zur Verfügung.')
            expect(wrapper.findAll('button').map((button) => button.text().trim())).toEqual(
                expect.arrayContaining(['Vorschau', 'Herunterladen']),
            )

            ;(wrapper.vm as any).openContentMaterialPreview((wrapper.vm as any).contentMaterialPreviewAttachments[1], { fullscreen: false })

            expect((wrapper.vm as any).contentMaterialPreviewDialogOpen).toBe(true)
            expect((wrapper.vm as any).contentMaterialPreviewFullscreen).toBe(false)
            expect((wrapper.vm as any).contentMaterialPreviewIsImage).toBe(true)
        } finally {
            ;(globalThis as any).axios = originalAxios
        }
    })

    it('scrolls the calendar to the assigned week when selecting a unit', async () => {
        const scrollTo = vi.fn()
        const originalScrollTo = HTMLElement.prototype.scrollTo

        Object.defineProperty(HTMLElement.prototype, 'scrollTo', {
            configurable: true,
            value: scrollTo,
        })

        try {
            const wrapper = mountCurriculumDetail({
                topics: [
                    {
                        id: 'topic-1',
                        title: 'Grammatik',
                        assignment_type: 'none',
                        month_keys: [],
                        week_keys: [],
                        units: [
                            {
                                id: 'unit-1',
                                title: 'Satzbau',
                                is_exam: false,
                                assignment_type: 'weeks',
                                month_keys: [],
                                week_keys: ['2025-09-15'],
                            },
                        ],
                    },
                ],
            })

            const unitItem = wrapper.find('.curriculum-detail__unit-item')

            expect(unitItem.exists()).toBe(true)

            await unitItem.trigger('click')
            await wrapper.vm.$nextTick()

            expect(scrollTo).toHaveBeenCalledTimes(1)
            expect(scrollTo).toHaveBeenCalledWith({
                top: expect.any(Number),
                behavior: 'smooth',
            })
        } finally {
            Object.defineProperty(HTMLElement.prototype, 'scrollTo', {
                configurable: true,
                value: originalScrollTo,
            })
        }
    })

    it('opens unit date assignment editing without forcing a calendar scroll', async () => {
        const scrollTo = vi.fn()
        const originalScrollTo = HTMLElement.prototype.scrollTo

        Object.defineProperty(HTMLElement.prototype, 'scrollTo', {
            configurable: true,
            value: scrollTo,
        })

        try {
            const wrapper = mountCurriculumDetail({
                topics: [
                    {
                        id: 'topic-1',
                        title: 'Grammatik',
                        assignment_type: 'none',
                        month_keys: [],
                        week_keys: [],
                        units: [
                            {
                                id: 'unit-1',
                                title: 'Satzbau',
                                is_exam: false,
                                assignment_type: 'weeks',
                                month_keys: [],
                                week_keys: ['2025-09-15'],
                            },
                        ],
                    },
                ],
            })

            const topic = (wrapper.vm as any).curriculumTopics[0]
            const unit = topic.units[0]

            ;(wrapper.vm as any).openUnitAssignmentEditor(topic, unit, 'weeks')
            await wrapper.vm.$nextTick()

            expect((wrapper.vm as any).activeTopicAssignmentId).toBe('topic-1')
            expect((wrapper.vm as any).activeTopicAssignmentUnitId).toBe('unit-1')
            expect((wrapper.vm as any).activeTopicAssignmentType).toBe('weeks')
            expect(scrollTo).not.toHaveBeenCalled()
        } finally {
            Object.defineProperty(HTMLElement.prototype, 'scrollTo', {
                configurable: true,
                value: originalScrollTo,
            })
        }
    })

    it('keeps whole-year assignment mode when opening the date editor', () => {
        const methods = (CurriculumDetail as any).methods

        expect(methods.defaultAssignmentEditorType('all_weeks')).toBe('all_weeks')
        expect(methods.defaultAssignmentEditorType('month')).toBe('month')
        expect(methods.defaultAssignmentEditorType('weeks')).toBe('none')
        expect(methods.defaultAssignmentEditorType('none')).toBe('none')
    })

    it('opens the unit calendar icon without entering week-selection mode for existing week assignments', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Satzbau',
                            is_exam: false,
                            assignment_type: 'weeks',
                            month_keys: [],
                            week_keys: ['2025-09-15'],
                        },
                    ],
                },
            ],
        })
        const topic = (wrapper.vm as any).curriculumTopics[0]
        const unit = topic.units[0]

        ;(wrapper.vm as any).toggleUnitAssignmentEditor(topic, unit)

        expect((wrapper.vm as any).activeTopicAssignmentId).toBe('topic-1')
        expect((wrapper.vm as any).activeTopicAssignmentUnitId).toBe('unit-1')
        expect((wrapper.vm as any).activeTopicAssignmentType).toBe('none')
        expect((wrapper.vm as any).isWeekSelectionActive).toBe(false)
    })

    it('does not highlight the whole calendar when editing whole-year assignments', () => {
        const methods = (CurriculumDetail as any).methods
        const ctx: Record<string, any> = {
            activeTopicAssignmentId: 'topic-1',
            activeTopicAssignmentType: 'all_weeks',
            highlightedAssignmentItems: [
                {
                    assignment_type: 'all_weeks',
                    month_keys: [],
                    week_keys: [],
                },
            ],
        }

        Object.assign(ctx, methods)

        expect(methods.shouldSuppressActiveAssignmentCalendarHighlight.call(ctx)).toBe(true)
        expect(methods.isMonthAssignedToHighlightedItem.call(ctx, { assignmentKey: '2025-09' })).toBe(false)
        expect(methods.isWeekAssignedToHighlightedItem.call(ctx, '2025-09-15')).toBe(false)
    })

    it('shows a Leistungsfeststellung marker for exam units in the Inhalte card', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Dateimanagement',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'PÜ: Dateimanagement',
                            is_exam: true,
                            assignment_type: 'none',
                            month_keys: [],
                            week_keys: [],
                        },
                        {
                            id: 'unit-2',
                            title: 'Übung',
                            is_exam: false,
                            assignment_type: 'none',
                            month_keys: [],
                            week_keys: [],
                        },
                    ],
                },
            ],
        })

        const unitItems = wrapper.findAll('.curriculum-detail__unit-item')

        expect(unitItems).toHaveLength(2)
        expect(unitItems[0].text()).toContain('Leistungsfeststellung')
        expect(unitItems[0].find('.curriculum-detail__unit-exam-chip').exists()).toBe(true)
        expect(unitItems[0].classes()).toContain('curriculum-detail__unit-item--exam')
        expect(unitItems[1].text()).not.toContain('Leistungsfeststellung')
        expect(unitItems[1].find('.curriculum-detail__unit-exam-chip').exists()).toBe(false)
        expect(unitItems[1].classes()).not.toContain('curriculum-detail__unit-item--exam')
    })

    it('distributes unassigned units to the next visible weeks while skipping free and occupied weeks', async () => {
        const wrapper = mountCurriculumDetail({
            free_weeks: ['2025-09-22'],
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Nomen',
                            is_exam: false,
                            assignment_type: 'weeks',
                            month_keys: [],
                            week_keys: ['2025-09-08'],
                        },
                        {
                            id: 'unit-2',
                            title: 'Verben',
                            is_exam: false,
                            assignment_type: 'none',
                            month_keys: [],
                            week_keys: [],
                        },
                        {
                            id: 'unit-3',
                            title: 'Satzbau',
                            is_exam: false,
                            assignment_type: 'none',
                            month_keys: [],
                            week_keys: [],
                        },
                    ],
                },
                {
                    id: 'topic-2',
                    title: 'Literatur',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-4',
                            title: 'Textanalyse',
                            is_exam: false,
                            assignment_type: 'weeks',
                            month_keys: [],
                            week_keys: ['2025-09-15'],
                        },
                    ],
                },
            ],
        })
        const persistCurriculumMock = vi.spyOn(wrapper.vm as any, 'persistCurriculum').mockResolvedValue({
            id: 15,
        })
        const topic = (wrapper.vm as any).curriculumTopics[0]

        try {
            expect((wrapper.vm as any).canDistributeTopicUnits(topic)).toBe(true)

            await (wrapper.vm as any).distributeTopicUnits(topic)

            expect(persistCurriculumMock).toHaveBeenCalledTimes(1)
            expect(persistCurriculumMock).toHaveBeenCalledWith({
                topics: expect.arrayContaining([
                    expect.objectContaining({
                        id: 'topic-1',
                        units: expect.arrayContaining([
                            expect.objectContaining({
                                id: 'unit-1',
                                assignment_type: 'weeks',
                                week_keys: ['2025-09-08'],
                            }),
                            expect.objectContaining({
                                id: 'unit-2',
                                assignment_type: 'weeks',
                                week_keys: ['2025-09-29'],
                            }),
                            expect.objectContaining({
                                id: 'unit-3',
                                assignment_type: 'weeks',
                                week_keys: ['2025-10-06'],
                            }),
                        ]),
                    }),
                ]),
            }, 'Einheiten konnten nicht verteilt werden.')
            expect((wrapper.vm as any).topicSaving).toBe(false)
        } finally {
            persistCurriculumMock.mockRestore()
        }
    })

    it('opens Datumszuordnung bearbeiten in the current assignment mode before week selection is requested', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Satzbau',
                            is_exam: false,
                            assignment_type: 'none',
                            month_keys: [],
                            week_keys: [],
                        },
                    ],
                },
            ],
        })

        const topic = (wrapper.vm as any).curriculumTopics[0]
        const unit = topic.units[0]

        ;(wrapper.vm as any).toggleTopicAssignmentEditor(topic)

        expect((wrapper.vm as any).activeTopicAssignmentType).toBe('none')
        expect((wrapper.vm as any).isWeekSelectionActive).toBe(false)

        ;(wrapper.vm as any).closeTopicAssignmentEditor()
        ;(wrapper.vm as any).toggleUnitAssignmentEditor(topic, unit)

        expect((wrapper.vm as any).activeTopicAssignmentType).toBe('none')
        expect((wrapper.vm as any).isWeekSelectionActive).toBe(false)

        ;(wrapper.vm as any).activateUnitAssignmentMode(topic, unit, 'weeks')

        expect((wrapper.vm as any).activeTopicAssignmentType).toBe('weeks')
        expect((wrapper.vm as any).isWeekSelectionActive).toBe(true)
    })

    it('highlights the assigned month and week area when selecting a topic or unit', async () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'month',
                    month_keys: ['2025-09'],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Satzbau',
                            is_exam: false,
                            assignment_type: 'weeks',
                            month_keys: [],
                            week_keys: ['2025-09-15'],
                        },
                    ],
                },
            ],
        })

        const septemberMonth = wrapper.findAll('.curriculum-detail__month')[0]
        const septemberWeeks = septemberMonth.findAll('.curriculum-detail__week')
        const topicRow = wrapper.find('.curriculum-detail__topic-item .curriculum-detail__topic-row')
        const unitRow = wrapper.find('.curriculum-detail__unit-item .curriculum-detail__topic-row')

        expect(septemberMonth.exists()).toBe(true)
        expect(septemberWeeks).toHaveLength(5)
        expect(septemberMonth.classes()).not.toContain('curriculum-detail__month--topic-selected')
        expect(septemberWeeks[2].classes()).not.toContain('curriculum-detail__week--topic-selected')

        await topicRow.trigger('click')

        expect(septemberMonth.classes()).toContain('curriculum-detail__month--topic-selected')
        expect(septemberWeeks[0].classes()).toContain('curriculum-detail__week--topic-selected')
        expect(septemberWeeks[4].classes()).toContain('curriculum-detail__week--topic-selected')
        expect(septemberWeeks[0].find('.curriculum-detail__week-range-label').classes()).toContain('curriculum-detail__week-range-label--selected')

        await unitRow.trigger('click')

        expect(septemberMonth.classes()).toContain('curriculum-detail__month--topic-selected')
        expect(septemberWeeks[0].classes()).not.toContain('curriculum-detail__week--topic-selected')
        expect(septemberWeeks[2].classes()).toContain('curriculum-detail__week--topic-selected')
        expect(septemberWeeks[4].classes()).not.toContain('curriculum-detail__week--topic-selected')
        expect(septemberWeeks[0].find('.curriculum-detail__week-range-label').classes()).not.toContain('curriculum-detail__week-range-label--selected')
        expect(septemberWeeks[2].find('.curriculum-detail__week-range-label').classes()).toContain('curriculum-detail__week-range-label--selected')
    })

    it('includes unit assignments when highlighting a selected topic', async () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Satzbau',
                            is_exam: false,
                            assignment_type: 'weeks',
                            month_keys: [],
                            week_keys: ['2025-09-15'],
                        },
                    ],
                },
            ],
        })

        const septemberMonth = wrapper.findAll('.curriculum-detail__month')[0]
        const septemberWeeks = septemberMonth.findAll('.curriculum-detail__week')
        const topicRow = wrapper.find('.curriculum-detail__topic-item .curriculum-detail__topic-row')

        await topicRow.trigger('click')

        expect(septemberMonth.classes()).toContain('curriculum-detail__month--topic-selected')
        expect(septemberWeeks[0].classes()).not.toContain('curriculum-detail__week--topic-selected')
        expect(septemberWeeks[2].classes()).toContain('curriculum-detail__week--topic-selected')
        expect(septemberWeeks[4].classes()).not.toContain('curriculum-detail__week--topic-selected')
    })

    it('shows a green check icon for assigned weeks', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'weeks',
                    month_keys: [],
                    week_keys: ['2025-09-08'],
                    units: [],
                },
                {
                    id: 'topic-2',
                    title: 'Rechtschreibung',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Kommasetzung',
                            is_exam: false,
                            assignment_type: 'weeks',
                            month_keys: [],
                            week_keys: ['2025-09-15'],
                        },
                    ],
                },
            ],
        })

        const firstAssignedWeek = wrapper.find('[data-week-key="2025-09-08"]')
        const secondAssignedWeek = wrapper.find('[data-week-key="2025-09-15"]')
        const unassignedWeek = wrapper.find('[data-week-key="2025-09-22"]')

        expect(firstAssignedWeek.find('.curriculum-detail__week-status').exists()).toBe(true)
        expect(firstAssignedWeek.text()).toContain('mdi-check-circle')
        expect(secondAssignedWeek.find('.curriculum-detail__week-status').exists()).toBe(true)
        expect(unassignedWeek.find('.curriculum-detail__week-status').exists()).toBe(false)
    })

    it('renders inherited units as one merged week entry when a topic is assigned to a week', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Einleitung',
                    assignment_type: 'weeks',
                    month_keys: [],
                    week_keys: ['2025-09-08'],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Begrüßung, Complience',
                            is_exam: false,
                            assignment_type: 'none',
                            month_keys: [],
                            week_keys: [],
                            checked_week_keys: [],
                        },
                        {
                            id: 'unit-2',
                            title: 'Stoffübersicht, Beurteilung',
                            is_exam: false,
                            assignment_type: 'none',
                            month_keys: [],
                            week_keys: [],
                            checked_week_keys: [],
                        },
                    ],
                },
            ],
        })

        expect((wrapper.vm as any).topicsForWeek('2025-09-08')).toEqual(expect.arrayContaining([
            expect.objectContaining({
                topicId: 'topic-1',
            }),
        ]))
        expect(wrapper.find('.curriculum-detail__unit-item').text()).toContain('Über Thema')
        expect(wrapper.find('.curriculum-detail__unit-item').text()).not.toContain('Keine Zuordnung')

        const assignedWeek = wrapper.find('[data-week-key="2025-09-08"]')
        expect(assignedWeek.findAll('.curriculum-detail__overview-entry')).toHaveLength(1)
        expect(assignedWeek.text()).toContain('Einleitung:')
        expect(assignedWeek.text()).toContain('Complience, Stoff')
        expect(assignedWeek.text()).not.toContain('EinleitungEinleitung:')

        const topicPart = assignedWeek.find('.curriculum-detail__week-topic-label-topic')
        const unitPart = assignedWeek.find('.curriculum-detail__week-topic-label-unit')
        expect(topicPart.exists()).toBe(true)
        expect(topicPart.text()).toBe('Einleitung:')
        expect(unitPart.exists()).toBe(true)
        expect(unitPart.text()).toContain('Complience, Stoff')
    })

    it('wraps assigned week topic text in a marker-style label', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'weeks',
                    month_keys: [],
                    week_keys: ['2025-09-08'],
                    units: [],
                },
            ],
        })

        const assignedWeek = wrapper.find('[data-week-key="2025-09-08"]')
        const topicLabel = assignedWeek.find('.curriculum-detail__week-topic-label')

        expect(topicLabel.exists()).toBe(true)
        expect(topicLabel.text()).toBe('Grammatik')
    })

    it('renders week topic text bold and unit text normal inside the marker label', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grundlagen',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Office 365',
                            is_exam: false,
                            assignment_type: 'weeks',
                            month_keys: [],
                            week_keys: ['2025-09-08'],
                        },
                    ],
                },
            ],
        })

        const assignedWeek = wrapper.find('[data-week-key="2025-09-08"]')
        const topicPart = assignedWeek.find('.curriculum-detail__week-topic-label-topic')
        const unitPart = assignedWeek.find('.curriculum-detail__week-topic-label-unit')

        expect(topicPart.exists()).toBe(true)
        expect(topicPart.text()).toBe('Grundlagen:')
        expect(unitPart.exists()).toBe(true)
        expect(unitPart.text()).toBe('Office 365')
    })

    it('groups month overview entries by topic and renders units inline', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Textverarbeitung',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Einführung in Word',
                            is_exam: false,
                            assignment_type: 'month',
                            month_keys: ['2025-10'],
                            week_keys: [],
                        },
                        {
                            id: 'unit-2',
                            title: 'Zeichenformate',
                            is_exam: false,
                            assignment_type: 'month',
                            month_keys: ['2025-10'],
                            week_keys: [],
                        },
                        {
                            id: 'unit-3',
                            title: 'Absatzformate',
                            is_exam: false,
                            assignment_type: 'month',
                            month_keys: ['2025-10'],
                            week_keys: [],
                        },
                    ],
                },
            ],
        })

        const octoberMonth = wrapper.findAll('.curriculum-detail__month')[1]
        const lines = octoberMonth.findAll('.curriculum-detail__month-topic-line')

        expect(lines).toHaveLength(1)
        expect(lines[0].find('.curriculum-detail__month-topic-name').text()).toBe('Textverarbeitung:')
        expect(lines[0].findAll('.curriculum-detail__month-topic-unit')).toHaveLength(3)
        expect(lines[0].find('.curriculum-detail__month-topic-unit').text()).toBe('Einführung in Word,')
        expect(lines[0].find('.curriculum-detail__month-topic-units').text()).toContain('Einführung in Word,Zeichenformate,Absatzformate')
    })

    it('collapses fully assigned months when enabled', async () => {
        const wrapper = mountCurriculumDetail({
            free_weeks: ['2025-09-29'],
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Satzbau',
                            is_exam: false,
                            assignment_type: 'weeks',
                            month_keys: [],
                            week_keys: ['2025-09-01', '2025-09-08', '2025-09-15', '2025-09-22'],
                        },
                    ],
                },
                {
                    id: 'topic-2',
                    title: 'Lesen',
                    assignment_type: 'weeks',
                    month_keys: [],
                    week_keys: ['2025-10-06'],
                    units: [],
                },
            ],
        })

        await wrapper.setData({ collapseFullMonths: false })

        const monthsBeforeCollapse = wrapper.findAll('.curriculum-detail__month')

        expect(monthsBeforeCollapse[0].findAll('.curriculum-detail__week')).toHaveLength(5)
        expect(monthsBeforeCollapse[0].classes()).not.toContain('curriculum-detail__month--collapsed')
        expect(monthsBeforeCollapse[0].find('.curriculum-detail__month-topics').exists()).toBe(false)

        await wrapper.setData({ collapseFullMonths: true })

        const monthsAfterCollapse = wrapper.findAll('.curriculum-detail__month')

        expect(monthsAfterCollapse[0].classes()).toContain('curriculum-detail__month--collapsed')
        expect(monthsAfterCollapse[0].find('.curriculum-detail__weeks').exists()).toBe(false)
        expect(monthsAfterCollapse[0].find('.curriculum-detail__month-topics').exists()).toBe(true)
        expect(monthsAfterCollapse[0].find('.curriculum-detail__month-topics-label').exists()).toBe(false)
        expect(monthsAfterCollapse[0].find('.curriculum-detail__month-topic-name').text()).toBe('Grammatik:')
        expect(monthsAfterCollapse[0].find('.curriculum-detail__month-topic-units').text()).toContain('Satzbau')
        expect(monthsAfterCollapse[1].classes()).not.toContain('curriculum-detail__month--collapsed')
        expect(monthsAfterCollapse[1].find('.curriculum-detail__weeks').exists()).toBe(true)
    })

    it('opens a collapsed assigned month when a matching unit is selected', async () => {
        const wrapper = mountCurriculumDetail({
            free_weeks: ['2025-09-29'],
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Satzbau',
                            is_exam: false,
                            assignment_type: 'weeks',
                            month_keys: [],
                            week_keys: ['2025-09-01', '2025-09-08', '2025-09-15', '2025-09-22'],
                        },
                    ],
                },
            ],
        })

        const septemberMonthBeforeSelection = wrapper.findAll('.curriculum-detail__month')[0]

        expect(septemberMonthBeforeSelection.classes()).toContain('curriculum-detail__month--collapsed')
        expect(septemberMonthBeforeSelection.find('.curriculum-detail__weeks').exists()).toBe(false)

        await (wrapper.vm as any).toggleSelectedUnit('topic-1', 'unit-1')
        await wrapper.vm.$nextTick()

        const septemberMonthAfterSelection = wrapper.findAll('.curriculum-detail__month')[0]

        expect(septemberMonthAfterSelection.classes()).not.toContain('curriculum-detail__month--collapsed')
        expect(septemberMonthAfterSelection.classes()).toContain('curriculum-detail__month--topic-selected')
        expect(septemberMonthAfterSelection.find('.curriculum-detail__weeks').exists()).toBe(true)
        expect(septemberMonthAfterSelection.findAll('.curriculum-detail__week')[0].classes()).toContain('curriculum-detail__week--topic-selected')
    })

    it('toggles a month collapsed state from its header', async () => {
        const wrapper = mountCurriculumDetail({
            free_weeks: ['2025-09-29'],
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'none',
                    month_keys: [],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Satzbau',
                            is_exam: false,
                            assignment_type: 'weeks',
                            month_keys: [],
                            week_keys: ['2025-09-01', '2025-09-08', '2025-09-15', '2025-09-22'],
                        },
                    ],
                },
            ],
        })

        const septemberMonth = wrapper.findAll('.curriculum-detail__month')[0]
        const septemberHeader = septemberMonth.find('.curriculum-detail__month-header')

        expect(septemberMonth.classes()).toContain('curriculum-detail__month--collapsed')
        expect(septemberMonth.find('.curriculum-detail__weeks').exists()).toBe(false)

        await septemberHeader.trigger('click')

        expect(septemberMonth.classes()).not.toContain('curriculum-detail__month--collapsed')
        expect(septemberMonth.find('.curriculum-detail__weeks').exists()).toBe(true)

        await septemberHeader.trigger('click')

        expect(septemberMonth.classes()).toContain('curriculum-detail__month--collapsed')
        expect(septemberMonth.find('.curriculum-detail__weeks').exists()).toBe(false)
    })

    it('collapses a fully assigned month immediately after the last week is assigned', async () => {
        const initialTopics = [
            {
                id: 'topic-1',
                title: 'Grammatik',
                assignment_type: 'none',
                month_keys: [],
                week_keys: [],
                units: [
                    {
                        id: 'unit-1',
                        title: 'Satzbau',
                        is_exam: false,
                        assignment_type: 'weeks',
                        month_keys: [],
                        week_keys: ['2025-09-01', '2025-09-08', '2025-09-15', '2025-09-22'],
                    },
                ],
            },
        ]
        const updatedTopics = [
            {
                ...initialTopics[0],
                units: [
                    {
                        ...initialTopics[0].units[0],
                        week_keys: ['2025-09-01', '2025-09-08', '2025-09-15', '2025-09-22', '2025-09-29'],
                    },
                ],
            },
        ]
        const wrapper = mountCurriculumDetail({
            topics: initialTopics,
        })
        const persistUnitAssignmentMock = vi.spyOn(wrapper.vm as any, 'persistUnitAssignment').mockImplementation(async () => {
            await wrapper.setProps({
                curriculum: buildCurriculum({
                    topics: updatedTopics,
                }),
            })

            return buildCurriculum({
                topics: updatedTopics,
            })
        })

        try {
            const septemberMonthBeforeClick = wrapper.findAll('.curriculum-detail__month')[0]

            expect(septemberMonthBeforeClick.classes()).not.toContain('curriculum-detail__month--collapsed')

            await wrapper.setData({
                activeTopicAssignmentId: 'topic-1',
                activeTopicAssignmentUnitId: 'unit-1',
                activeTopicAssignmentType: 'weeks',
            })

            await (wrapper.vm as any).handleWeekClick('2025-09-29')
            await wrapper.vm.$nextTick()

            const septemberMonthAfterClick = wrapper.findAll('.curriculum-detail__month')[0]

            expect(persistUnitAssignmentMock).toHaveBeenCalledTimes(1)
            expect((wrapper.vm as any).activeTopicAssignmentId).toBeNull()
            expect((wrapper.vm as any).activeTopicAssignmentUnitId).toBeNull()
            expect((wrapper.vm as any).activeTopicAssignmentType).toBeNull()
            expect(septemberMonthAfterClick.classes()).toContain('curriculum-detail__month--collapsed')
            expect(septemberMonthAfterClick.find('.curriculum-detail__weeks').exists()).toBe(false)
        } finally {
            persistUnitAssignmentMock.mockRestore()
        }
    })

    it('opens selected material attachments directly in the preview frame', async () => {
        const wrapper = mountCurriculumDetail()

        const selectedMaterialDocument = {
            id: 901,
            source_type: 'material',
            name: 'Lehrplan aus Materialien',
            material_card_attachment_id: 55,
            selected_attachment_name: 'Lehrplan.docx',
            preview_mime_type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            preview_url: '/api/admin/teaching/curricula/15/documents/901/preview',
            download_url: '/api/admin/teaching/curricula/15/documents/901/download',
        }

        const unselectedMaterialDocument = {
            id: 902,
            source_type: 'material',
            name: 'Noch ohne Anhang',
            material_card_attachment_id: null,
            selected_attachment_name: null,
            preview_mime_type: null,
            preview_url: null,
            download_url: null,
        }

        await wrapper.setData({
            documents: [selectedMaterialDocument, unselectedMaterialDocument],
        })

        ;(wrapper.vm as any).selectPreview(selectedMaterialDocument)
        await wrapper.vm.$nextTick()

        expect(openMaterialAttachmentDialogSpy).not.toHaveBeenCalled()
        expect((wrapper.vm as any).previewDoc).toEqual(selectedMaterialDocument)
        expect((wrapper.vm as any).previewUsesIframe).toBe(true)
        expect(wrapper.find('.curriculum-pdf-preview-stub').attributes('data-src')).toBe(selectedMaterialDocument.preview_url)

        ;(wrapper.vm as any).selectPreview(unselectedMaterialDocument)

        expect(openMaterialAttachmentDialogSpy).toHaveBeenCalledWith(unselectedMaterialDocument)
    })

    it('requires confirmation before removing a curriculum document', async () => {
        const wrapper = mountCurriculumDetail()
        const originalAxios = (globalThis as any).axios
        const deleteMock = vi.fn().mockResolvedValue({ data: null })
        ;(globalThis as any).axios = {
            delete: deleteMock,
        }

        const document = {
            id: 903,
            source_type: 'upload',
            name: 'Deutsch Lehrplan.pdf',
            preview_url: '/api/admin/teaching/curricula/15/documents/903/preview',
            download_url: '/api/admin/teaching/curricula/15/documents/903/download',
        }

        try {
            await wrapper.setData({
                documents: [document],
                previewDoc: document,
            })

            ;(wrapper.vm as any).removeDocument(document)

            expect(deleteMock).not.toHaveBeenCalled()
            expect((wrapper.vm as any).documentDeleteDialogOpen).toBe(true)
            expect((wrapper.vm as any).documentToDelete).toEqual(document)

            await (wrapper.vm as any).confirmDocumentDelete()

            expect(deleteMock).toHaveBeenCalledWith('/api/admin/teaching/curricula/15/documents/903')
            expect((wrapper.vm as any).documentDeleteDialogOpen).toBe(false)
            expect((wrapper.vm as any).documentToDelete).toBeNull()
            expect((wrapper.vm as any).previewDoc).toBeNull()
            expect((wrapper.vm as any).documents).toEqual([])
        } finally {
            ;(globalThis as any).axios = originalAxios
        }
    })

    it('shows attachment counts and a selection hint for materials with multiple files', async () => {
        const wrapper = mountCurriculumDetail()
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/curricula/CurriculumDetail.vue'), 'utf8')

        await wrapper.setData({
            materialDialogOpen: true,
            materialResults: [
                {
                    id: 77,
                    title: 'Biologie Lehrplan',
                    subject: 'Biologie',
                    attachments_count: 3,
                },
            ],
            materialAttachmentDialogOpen: true,
            materialAttachmentDocument: {
                id: 77,
                name: 'Biologie Lehrplan',
            },
            materialAttachmentOptions: [
                { id: 1, name: 'Teil A.pdf', mime_type: 'application/pdf', size_bytes: 10 },
                { id: 2, name: 'Teil B.pdf', mime_type: 'application/pdf', size_bytes: 10 },
            ],
            materialAttachmentLoading: false,
            materialAttachmentError: null,
        })

        expect((wrapper.vm as any).materialFileAttachmentCount({ attachments_count: 3 })).toBe(3)
        expect((wrapper.vm as any).materialAttachmentCountLabel({ attachments_count: 3 })).toBe('3 Anhänge')
        expect((wrapper.vm as any).materialPickerSubtitle({
            subject: 'Biologie',
            attachments_count: 3,
        })).toBe('Biologie · 3 Anhänge')
        expect(source).toContain('Dieses Material hat mehrere Anhänge. Bitte den Anhang auswählen, der angezeigt werden soll.')
    })

    it('remaps topic and unit assignments to the selected year by month and kw', async () => {
        const wrapper = mountCurriculumDetail({
            free_weeks: ['2025-09-15'],
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'month',
                    month_keys: ['2025-09'],
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Satzbau',
                            is_exam: false,
                            assignment_type: 'weeks',
                            month_keys: [],
                            week_keys: ['2025-09-08'],
                            checked_week_keys: ['2025-09-08'],
                        },
                    ],
                },
            ],
        })

        await wrapper.setData({ selectedYear: 2026 })

        const remappedTopic = (wrapper.vm as any).curriculumTopics[0]
        const remappedUnit = remappedTopic.units[0]
        const expectedWeekKey = (wrapper.vm as any).allMonths
            .flatMap((month: { weeks: Array<{ kw: number, weekKey: string }> }) => month.weeks)
            .find((week: { kw: number, weekKey: string }) => week.kw === (wrapper.vm as any).getISOWeek(new Date('2025-09-08T00:00:00')))
            ?.weekKey
        const expectedFreeWeekKey = (wrapper.vm as any).allMonths
            .flatMap((month: { weeks: Array<{ kw: number, weekKey: string }> }) => month.weeks)
            .find((week: { kw: number, weekKey: string }) => week.kw === (wrapper.vm as any).getISOWeek(new Date('2025-09-15T00:00:00')))
            ?.weekKey

        expect(remappedTopic.month_keys).toEqual(['2026-09'])
        expect(remappedTopic.month_key).toBe('2026-09')
        expect(remappedUnit.week_keys).toEqual([expectedWeekKey])
        expect(remappedUnit.checked_week_keys).toEqual([expectedWeekKey])
        expect((wrapper.vm as any).freeWeekKeys).toEqual([expectedFreeWeekKey])
        expect((wrapper.vm as any).isFreeWeek(expectedFreeWeekKey)).toBe(true)
        expect(expectedWeekKey).toBeTruthy()
        expect(expectedFreeWeekKey).toBeTruthy()
        expect(remappedUnit.week_keys[0]).not.toBe('2025-09-08')
        expect((wrapper.vm as any).freeWeekKeys[0]).not.toBe('2025-09-15')
    })

    it('shows the free-days template action only when a user template exists and disables it for curricula with free weeks', async () => {
        const wrapper = mountCurriculumDetail(
            {},
            {
                user: {
                    teaching_curriculum_free_weeks_template: {
                        week_keys: ['2025-09-15'],
                        named_ranges: [],
                    },
                },
            },
        )

        expect(wrapper.text()).toContain('Freie Tage übernehmen')
        expect((wrapper.vm as any).hasFreeWeeksTemplate).toBe(true)
        expect((wrapper.vm as any).canApplyFreeWeeksTemplate).toBe(true)

        await wrapper.setProps({
            curriculum: buildCurriculum({
                free_weeks: ['2025-09-15'],
            }),
        })

        expect((wrapper.vm as any).freeWeeksCount).toBe(1)
        expect((wrapper.vm as any).canApplyFreeWeeksTemplate).toBe(false)
    })

    it('applies the user free-days template to the selected year', async () => {
        const wrapper = mountCurriculumDetail(
            {},
            {
                user: {
                    teaching_curriculum_free_weeks_template: {
                        week_keys: ['2025-09-15'],
                        named_ranges: [],
                    },
                },
            },
        )

        await wrapper.setData({ selectedYear: 2026 })

        const expectedWeekKey = (wrapper.vm as any).allMonths
            .flatMap((month: { weeks: Array<{ kw: number, weekKey: string }> }) => month.weeks)
            .find((week: { kw: number, weekKey: string }) => week.kw === (wrapper.vm as any).getISOWeek(new Date('2025-09-15T00:00:00')))
            ?.weekKey

        const persistCurriculumMock = vi.spyOn((wrapper.vm as any), 'persistCurriculum')
            .mockResolvedValue({ ...buildCurriculum(), free_weeks: [expectedWeekKey] })

        try {
            await (wrapper.vm as any).applyFreeWeeksTemplate()

            expect(persistCurriculumMock).toHaveBeenCalledWith({
                free_weeks: [expectedWeekKey],
            }, 'Freie Tage konnten nicht übernommen werden.')
            expect((wrapper.vm as any).isApplyingFreeWeeksTemplate).toBe(false)
        } finally {
            persistCurriculumMock.mockRestore()
        }
    })
})
