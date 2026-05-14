import { createTestingPinia } from '@pinia/testing'
import { afterAll, afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import CurriculumDetail from '@/pages/admin/teaching/curricula/CurriculumDetail.vue'

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: () => ({
        notify: vi.fn(),
    }),
}))

const loadDocumentsSpy = vi.spyOn((CurriculumDetail as any).methods, 'loadDocuments').mockResolvedValue(undefined)
const openMaterialAttachmentDialogSpy = vi.spyOn((CurriculumDetail as any).methods, 'openMaterialAttachmentDialog').mockResolvedValue(undefined)

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
            curriculum: {
                id: 15,
                title: 'Deutsch',
                description: 'Lehrplan',
                semester_count: 2,
                free_weeks: [],
                topics: [],
                ...curriculumOverrides,
            },
        },
        global: {
            plugins: [pinia],
            stubs: {
                FileUpload: { template: '<div />' },
                'v-autocomplete': { template: '<div><slot /></div>' },
                'v-btn': { template: '<button><slot /></button>' },
                'v-btn-toggle': { template: '<div><slot /></div>' },
                'v-card': { template: '<div><slot /></div>' },
                'v-card-actions': { template: '<div><slot /></div>' },
                'v-card-text': { template: '<div><slot /></div>' },
                'v-card-title': { template: '<div><slot /></div>' },
                'v-checkbox': { template: '<input type="checkbox" />' },
                'v-chip': { template: '<span><slot /></span>' },
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

describe('CurriculumDetail month assignment summaries', () => {
    afterEach(() => {
        loadDocumentsSpy.mockClear()
        openMaterialAttachmentDialogSpy.mockClear()
    })

    afterAll(() => {
        loadDocumentsSpy.mockRestore()
        openMaterialAttachmentDialogSpy.mockRestore()
    })

    it('shows week-assigned topics and inherited units in the month cards', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grammatik',
                    assignment_type: 'weeks',
                    month_keys: [],
                    week_keys: ['2025-09-08'],
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

        const septemberMonth = wrapper.findAll('.curriculum-detail__month')[0]

        expect(septemberMonth.find('.curriculum-detail__month-topics').exists()).toBe(true)
        expect(septemberMonth.find('.curriculum-detail__month-topic-name').text()).toBe('Grammatik:')
        expect(septemberMonth.find('.curriculum-detail__month-topic-unit').text()).toBe('Satzbau')
    })

    it('does not duplicate late September week assignments in the October month card', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grundlagen',
                    assignment_type: 'weeks',
                    month_keys: [],
                    week_keys: ['2025-09-29'],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Am System anmelden, Kennwörter, Schooltool',
                            is_exam: false,
                            assignment_type: 'none',
                            month_keys: [],
                            week_keys: [],
                        },
                    ],
                },
            ],
        })

        const [septemberMonth, octoberMonth] = wrapper.findAll('.curriculum-detail__month')

        expect(septemberMonth.text()).toContain('Grundlagen')
        expect(septemberMonth.text()).toContain('Am System anmelden, Kennwörter, Schooltool')
        expect(octoberMonth.text()).not.toContain('Grundlagen')
        expect(octoberMonth.text()).not.toContain('Am System anmelden, Kennwörter, Schooltool')
    })

    it('shows configured week counts for month assignments', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grundlagen',
                    assignment_type: 'month',
                    month_keys: ['2025-09'],
                    month_week_counts: {
                        '2025-09': 0,
                    },
                    week_keys: [],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Am System anmelden',
                            is_exam: false,
                            assignment_type: 'month',
                            month_keys: ['2025-09'],
                            month_week_counts: {
                                '2025-09': 1,
                            },
                            week_keys: [],
                        },
                    ],
                },
            ],
        })

        const septemberMonth = wrapper.findAll('.curriculum-detail__month')[0]

        expect(septemberMonth.text()).toContain('Grundlagen (Ganzer Monat)')
        expect(septemberMonth.text()).toContain('Am System anmelden (1 Woche)')
    })

    it('defaults selected month assignments to one week', () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grundlagen',
                    assignment_type: 'month',
                    month_keys: ['2025-09'],
                    week_keys: [],
                    units: [],
                },
            ],
        })

        const topic = (wrapper.vm as any).curriculumTopics[0]

        expect((wrapper.vm as any).monthWeekCount(topic, '2025-09')).toBe(1)
        expect(wrapper.findAll('.curriculum-detail__month')[0].text()).toContain('Grundlagen (1 Woche)')
    })

    it('keeps week chips in the selected month row', async () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grundlagen',
                    assignment_type: 'month',
                    month_keys: ['2025-09'],
                    week_keys: [],
                    units: [],
                },
            ],
        })

        await wrapper.setData({
            activeTopicAssignmentId: 'topic-1',
            activeTopicAssignmentUnitId: null,
            activeTopicAssignmentType: 'month',
        })
        await nextTick()

        const selectedMonthRow = wrapper.find('.curriculum-detail__assignment-month-row--selected')

        expect(selectedMonthRow.exists()).toBe(true)
        expect(selectedMonthRow.text()).toContain('September 2025')
        expect(selectedMonthRow.text()).toContain('1')
        expect(selectedMonthRow.text()).toContain('2')
        expect(selectedMonthRow.text()).toContain('3')
        expect(selectedMonthRow.text()).toContain('4')
        expect(selectedMonthRow.text()).toContain('Ganzer Monat')
    })
})
