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

describe('CurriculumDetail content-only curriculum editor', () => {
    afterEach(() => {
        loadDocumentsSpy.mockClear()
        openMaterialAttachmentDialogSpy.mockClear()
    })

    afterAll(() => {
        loadDocumentsSpy.mockRestore()
        openMaterialAttachmentDialogSpy.mockRestore()
    })

    it('shows topics and their units without the removed calendar assignment UI', () => {
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

        expect(wrapper.find('.curriculum-detail__topic-list').text()).toContain('Grammatik')
        expect(wrapper.find('.curriculum-detail__unit-list').text()).toContain('Satzbau')
        expect(wrapper.find('.curriculum-detail__calendar').exists()).toBe(false)
    })

    it('keeps legacy assignment metadata out of the normalized content model', () => {
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

        const topic = (wrapper.vm as any).curriculumTopics[0]

        expect(topic).toEqual({
            id: 'topic-1',
            title: 'Grundlagen',
            units: [
                {
                    id: 'unit-1',
                    title: 'Am System anmelden, Kennwörter, Schooltool',
                    is_exam: false,
                    materials: [],
                },
            ],
        })
    })

    it('preserves content while discarding obsolete month week counts', () => {
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

        const topic = (wrapper.vm as any).curriculumTopics[0]

        expect(topic.title).toBe('Grundlagen')
        expect(topic.units[0].title).toBe('Am System anmelden')
        expect(topic).not.toHaveProperty('month_week_counts')
        expect(topic.units[0]).not.toHaveProperty('month_week_counts')
    })

    it('reports the current topic and unit totals', () => {
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

        expect((wrapper.vm as any).curriculumTopics).toHaveLength(1)
        expect((wrapper.vm as any).curriculumUnitCount).toBe(0)
        expect(wrapper.find('.curriculum-detail__preview-summary').text()).toBe('1 Themen · 0 Einheiten')
    })

    it('selects and clears a unit from the content hierarchy', async () => {
        const wrapper = mountCurriculumDetail({
            topics: [
                {
                    id: 'topic-1',
                    title: 'Grundlagen',
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Einführung',
                            is_exam: false,
                        },
                    ],
                },
            ],
        })

        const unit = wrapper.find('.curriculum-detail__unit-item')
        await unit.trigger('click')
        await nextTick()

        expect(unit.classes()).toContain('curriculum-detail__unit-item--selected')
        expect((wrapper.vm as any).selectedUnitId).toBe('unit-1')

        await unit.trigger('click')
        await nextTick()

        expect((wrapper.vm as any).selectedUnitId).toBeNull()
    })
})
