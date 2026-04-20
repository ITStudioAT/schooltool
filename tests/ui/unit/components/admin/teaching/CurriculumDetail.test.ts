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
        semester_count: 2,
        free_weeks: [],
        topics: [],
        ...overrides,
    }
}

function mountCurriculumDetail(curriculumOverrides: Record<string, unknown> = {}) {
    const pinia = createTestingPinia({
        stubActions: false,
        createSpy: vi.fn,
        initialState: {
            AdminAdminStore: {
                config: {
                    roles: ['teacher'],
                    selected_schoolyear: {
                        from: '2025-09-01',
                    },
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
                'v-icon': { template: '<i><slot /></i>' },
                'v-list': { template: '<div><slot /></div>' },
                'v-list-item': { template: '<div><slot /></div>' },
                'v-list-item-subtitle': { template: '<div><slot /></div>' },
                'v-list-item-title': { template: '<div><slot /></div>' },
                'v-progress-circular': { template: '<div />' },
                'v-sheet': { template: '<div v-bind="$attrs"><slot /></div>' },
                'v-spacer': { template: '<div />' },
                'v-text-field': { template: '<input />' },
            },
        },
    })
}

describe('CurriculumDetail week card view mode', () => {
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
        expect((wrapper.vm as any).showLehrplaeneCard).toBe(false)
        expect(wrapper.findAll('.curriculum-detail__week-days').length).toBeGreaterThan(0)
        expect(wrapper.find('.curriculum-detail__calendar-scroll').exists()).toBe(true)
        expect(wrapper.find('.curriculum-detail__side-card--content').exists()).toBe(true)
        expect(wrapper.find('.curriculum-detail__side-card--content.curriculum-detail__side-card--scrollable').exists()).toBe(false)
        expect(wrapper.find('.curriculum-detail__side-card--documents.curriculum-detail__side-card--scrollable').exists()).toBe(false)
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
        expect(source).toContain('class="curriculum-detail__side-card curriculum-detail__side-card--documents curriculum-detail__side-card--scrollable"')
        expect(source).toContain('class="curriculum-detail__side-card curriculum-detail__side-card--content"')
        expect(source).toContain('class="curriculum-detail__content-footer"')
        expect(source).toContain('class="curriculum-detail__unit-summary"')
        expect(source).toContain('Verteilen')
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
        expect(source).toContain("'curriculum-detail__month--collapsed': shouldCollapseMonth(month)")
        expect(source).toContain('@click="toggleMonthCollapse(month)"')
        expect(source).toContain('v-if="!shouldCollapseMonth(month)" class="curriculum-detail__weeks"')
        expect(source).toContain('v-if="!shouldCollapseMonth(month)" class="curriculum-detail__month-topics-label"')
        expect(source).toContain('.curriculum-detail__week--with-topics {')
        expect(source).toContain(':style="calendarHighlightStyle"')
        expect(source).toContain("'--calendar-highlight-accent': this.highlightedTopicAccentColor")
        expect(source).toContain('.curriculum-detail__week-status-icon {')
        expect(source).toContain('color: #16a34a !important;')
        expect(source).toContain('.curriculum-detail__overview-entry--exam {')
        expect(source).toContain('color: #4f46e5;')
        expect(source).toContain('v-for="group in monthOverviewGroups(month)"')
        expect(source).toContain('class="curriculum-detail__month-topic-line"')
        expect(source).toContain('.curriculum-detail__month-topic-name {')
        expect(source).toContain('.curriculum-detail__month-topic-units .curriculum-detail__overview-entry:not(:last-child) {')
        expect(source).toContain('margin-right: 0.3rem;')
        expect(source).toContain('class="curriculum-detail__unit-title-row"')
        expect(source).toContain('class="curriculum-detail__unit-exam-chip"')
        expect(source).toContain('Prüfung')
        expect(source).toContain('.curriculum-detail__month-topic-unit {')
        expect(source).toContain('font-weight: 500;')
        expect(source).toContain('.curriculum-detail__unit-item--selected {')
        expect(source).toContain('border-color: rgba(129, 140, 248, 0.52);')
        expect(source).toContain('linear-gradient(180deg, rgba(224, 231, 255, 0.98), rgba(199, 210, 254, 0.94));')
        expect(source).toContain('0 0 18px rgba(99, 102, 241, 0.16);')
        expect(source).toContain('@click="toggleSelectedUnit(topic.id, unit.id)"')
        expect(source).toContain('class="curriculum-detail__topic-actions" @click.stop')
        expect(source).toContain('class="curriculum-detail__topic-assignment-panel"')
        expect(source).toContain('@click.stop>')
        expect(source).toContain(":variant=\"activeTopicAssignmentType === 'weeks' ? 'flat' : 'tonal'\"")
        expect(source).toContain(":variant=\"activeTopicAssignmentType === 'month' ? 'flat' : 'tonal'\"")
        expect(source).toContain(":variant=\"activeTopicAssignmentType === 'none' ? 'flat' : 'tonal'\"")
        expect(source).toContain('.curriculum-detail__week--with-topics:hover {')
        expect(source).toContain('.curriculum-detail__week--with-exams {')
        expect(source).toContain('.curriculum-detail__week--with-exams .curriculum-detail__week-topics {')
        expect(source).toContain('.curriculum-detail__month--collapsed .curriculum-detail__month-topics {')
        expect(source).toContain('.curriculum-detail__topic-assignment-options :deep(.v-btn) {')
        expect(source).toContain('color: #cbd5e1 !important;')
        expect(source).toContain('.curriculum-detail__topic-assignment-options :deep(.v-btn--variant-flat) {')
        expect(source).toContain('border: 1px solid rgba(199, 210, 254, 0.42) !important;')
        expect(source).toContain('0 8px 18px rgba(79, 70, 229, 0.28);')
        expect(source).toContain('.curriculum-detail__assignment-week-controls :deep(.v-btn) {')
        expect(source).toContain('scrollHighlightedCalendarIntoView() {')
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

    it('renders the topic editor dialog even when the Lehrpläne card is hidden', async () => {
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

        expect((wrapper.vm as any).showLehrplaeneCard).toBe(false)
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

    it('adds a material to a topic without closing the selector dialog', async () => {
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
        const persistCurriculumMock = vi.spyOn(wrapper.vm as any, 'persistCurriculum').mockResolvedValue({
            id: 15,
        })

        try {
            await wrapper.setData({
                contentMaterialDialogOpen: true,
                contentMaterialTarget: {
                    type: 'topic',
                    topicId: 'topic-1',
                    unitId: null,
                },
            })

            await (wrapper.vm as any).attachContentMaterial({
                id: 77,
                title: 'Nebensätze Arbeitsblatt',
                subject: 'Deutsch',
                area: 'Grammatik',
                unit: 'Nebensätze',
                type: 'Arbeitsblatt',
                status: 'done',
                attachments_count: 2,
            })

            expect(persistCurriculumMock).toHaveBeenCalledTimes(1)
            expect(persistCurriculumMock).toHaveBeenCalledWith({
                topics: expect.arrayContaining([
                    expect.objectContaining({
                        id: 'topic-1',
                        materials: [
                            expect.objectContaining({
                                id: 77,
                                title: 'Nebensätze Arbeitsblatt',
                                subject: 'Deutsch',
                                topic: 'Grammatik',
                                unit: 'Nebensätze',
                            }),
                        ],
                    }),
                ]),
            }, 'Material konnte nicht hinzugefügt werden.')
            const persistedMaterial = persistCurriculumMock.mock.calls[0]?.[0]?.topics?.[0]?.materials?.[0]
            expect(persistedMaterial).not.toHaveProperty('attachments')
            expect((wrapper.vm as any).contentMaterialDialogOpen).toBe(true)
            expect((wrapper.vm as any).topicSaving).toBe(false)
        } finally {
            persistCurriculumMock.mockRestore()
        }
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

    it('opens the selected material attachment only in the fullscreen preview dialog', () => {
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
        expect((wrapper.vm as any).contentMaterialPreviewAttachment).toEqual(
            expect.objectContaining({
                id: 502,
                name: 'Nebensaetze.png',
            }),
        )
        expect((wrapper.vm as any).contentMaterialPreviewIsImage).toBe(true)
    })

    it('shows attachment access for linked topic materials in the overview', () => {
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

        expect(materialActions.exists()).toBe(true)
        expect(materialActions.text()).toContain('2 Anhänge')
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

            ;(wrapper.vm as any).openContentMaterialPreview((wrapper.vm as any).contentMaterialPreviewAttachments[1])

            expect((wrapper.vm as any).contentMaterialPreviewDialogOpen).toBe(true)
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

    it('scrolls the calendar to the assigned week when opening unit date assignment editing', async () => {
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

    it('shows an exam marker for units marked as Prüfung in the Inhalte card', () => {
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
        expect(unitItems[0].text()).toContain('Prüfung')
        expect(unitItems[0].find('.curriculum-detail__unit-exam-chip').exists()).toBe(true)
        expect(unitItems[1].text()).not.toContain('Prüfung')
        expect(unitItems[1].find('.curriculum-detail__unit-exam-chip').exists()).toBe(false)
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

    it('opens Datumszuordnung bearbeiten in week mode so week selection is possible', () => {
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

        expect((wrapper.vm as any).activeTopicAssignmentType).toBe('weeks')
        expect((wrapper.vm as any).isWeekSelectionActive).toBe(true)

        ;(wrapper.vm as any).closeTopicAssignmentEditor()
        ;(wrapper.vm as any).toggleUnitAssignmentEditor(topic, unit)

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

        await unitRow.trigger('click')

        expect(septemberMonth.classes()).toContain('curriculum-detail__month--topic-selected')
        expect(septemberWeeks[0].classes()).not.toContain('curriculum-detail__week--topic-selected')
        expect(septemberWeeks[2].classes()).toContain('curriculum-detail__week--topic-selected')
        expect(septemberWeeks[4].classes()).not.toContain('curriculum-detail__week--topic-selected')
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
            showLehrplaeneCard: true,
        })

        ;(wrapper.vm as any).selectPreview(selectedMaterialDocument)
        await wrapper.vm.$nextTick()

        expect(openMaterialAttachmentDialogSpy).not.toHaveBeenCalled()
        expect((wrapper.vm as any).previewDoc).toEqual(selectedMaterialDocument)
        expect((wrapper.vm as any).previewUsesIframe).toBe(true)
        expect(wrapper.find('.lehrplaene__preview-iframe').attributes('src')).toBe(selectedMaterialDocument.preview_url)

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
})
