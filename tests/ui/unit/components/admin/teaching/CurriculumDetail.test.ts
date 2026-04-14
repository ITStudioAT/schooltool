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
    })

    afterAll(() => {
        loadDocumentsSpy.mockRestore()
    })

    it('switches between weekday and compact week cards', async () => {
        const wrapper = mountCurriculumDetail()

        expect(loadDocumentsSpy).toHaveBeenCalledTimes(1)
        expect(wrapper.text()).toContain('Mit Tagen')
        expect(wrapper.text()).toContain('Ohne Tage')
        expect(wrapper.vm.showWeekdays).toBe(true)
        expect(wrapper.findAll('.curriculum-detail__week-days').length).toBeGreaterThan(0)
        expect(wrapper.find('.curriculum-detail__calendar-scroll').exists()).toBe(true)
        expect(wrapper.find('.curriculum-detail__side-card--content').exists()).toBe(true)
        expect(wrapper.find('.curriculum-detail__side-card--content.curriculum-detail__side-card--scrollable').exists()).toBe(false)
        expect(wrapper.find('.curriculum-detail__side-card--documents.curriculum-detail__side-card--scrollable').exists()).toBe(true)
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
        expect(source).toContain("'curriculum-detail__calendar--compact': isCompactWeekView")
        expect(source).toContain('class="curriculum-detail__side-card curriculum-detail__side-card--documents curriculum-detail__side-card--scrollable"')
        expect(source).toContain('class="curriculum-detail__side-card curriculum-detail__side-card--content"')
        expect(source).toContain('.curriculum-detail__calendar-scroll {')
        expect(source).toContain('.curriculum-detail__side-card--scrollable {')
        expect(source).toContain('overflow-y: auto;')
        expect(source).toContain('.curriculum-detail__body--compact-calendar {')
        expect(source).toContain('grid-template-columns: auto clamp(420px, 36vw, 640px) minmax(420px, 1fr);')
        expect(source).toContain(".curriculum-detail__calendar--compact {")
        expect(source).toContain('width: clamp(250px, 18vw, 300px);')
        expect(source).toContain('.curriculum-detail__calendar--compact .curriculum-detail__month {')
        expect(source).toContain('.curriculum-detail__week--with-topics {')
        expect(source).toContain('border-right: 4px solid rgba(79, 70, 229, 0.9) !important;')
        expect(source).toContain('.curriculum-detail__week--with-topics:hover {')
        expect(source).toContain('.curriculum-detail__week--with-exams {')
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

        const unitRow = wrapper.find('.curriculum-detail__unit-item .curriculum-detail__topic-row')
        const topicItem = wrapper.find('.curriculum-detail__topic-item')

        expect(unitRow.exists()).toBe(true)
        expect(topicItem.exists()).toBe(true)
        expect(topicItem.classes()).not.toContain('curriculum-detail__topic-item--selected')
        expect(wrapper.find('.curriculum-detail__unit-item').classes()).not.toContain('curriculum-detail__unit-item--selected')

        await unitRow.trigger('click')

        expect((wrapper.vm as any).selectedTopicId).toBe('topic-1')
        expect((wrapper.vm as any).selectedUnitTopicId).toBe('topic-1')
        expect((wrapper.vm as any).selectedUnitId).toBe('unit-1')
        expect(topicItem.classes()).not.toContain('curriculum-detail__topic-item--selected')
        expect(wrapper.find('.curriculum-detail__unit-item').classes()).toContain('curriculum-detail__unit-item--selected')

        await unitRow.trigger('click')

        expect((wrapper.vm as any).selectedTopicId).toBe('topic-1')
        expect((wrapper.vm as any).selectedUnitTopicId).toBeNull()
        expect((wrapper.vm as any).selectedUnitId).toBeNull()
        expect(wrapper.find('.curriculum-detail__unit-item').classes()).not.toContain('curriculum-detail__unit-item--selected')
    })
})
