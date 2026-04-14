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
        expect(wrapper.text()).toContain('Immer zeigen')
        expect(wrapper.text()).toContain('Einklappen')
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
        expect(source).toContain('v-model="collapseFullMonths"')
        expect(source).toContain('Volle Monate')
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
        expect(source).toContain(':style="weekAssignmentStyle(week.weekKey)"')
        expect(source).toContain("'curriculum-detail__month--collapsed': shouldCollapseMonth(month)")
        expect(source).toContain('v-if="!shouldCollapseMonth(month)" class="curriculum-detail__weeks"')
        expect(source).toContain('v-if="!shouldCollapseMonth(month)" class="curriculum-detail__month-topics-label"')
        expect(source).toContain('.curriculum-detail__week--with-topics {')
        expect(source).toContain('border-right: 4px solid var(--week-assignment-accent, rgba(79, 70, 229, 0.9)) !important;')
        expect(source).toContain(':style="calendarHighlightStyle"')
        expect(source).toContain("'--calendar-highlight-accent': this.highlightedTopicAccentColor")
        expect(source).toContain("'--week-assignment-accent': accentColor")
        expect(source).toContain('border-right: 4px solid var(--week-assignment-accent, var(--calendar-highlight-accent, rgba(79, 70, 229, 0.9))) !important;')
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
        expect(source).toContain('border-right: 4px solid #4f46e5 !important;')
        expect(source).toContain('.curriculum-detail__week--with-exams:hover {')
        expect(source).toContain('.curriculum-detail__week--with-exams .curriculum-detail__week-topics {')
        expect(source).toContain('.curriculum-detail__month--collapsed .curriculum-detail__month-topics {')
        expect(source).toContain('.curriculum-detail__topic-assignment-options :deep(.v-btn) {')
        expect(source).toContain('color: #cbd5e1 !important;')
        expect(source).toContain('.curriculum-detail__topic-assignment-options :deep(.v-btn--variant-flat) {')
        expect(source).toContain('border: 1px solid rgba(199, 210, 254, 0.42) !important;')
        expect(source).toContain('0 8px 18px rgba(79, 70, 229, 0.28);')
        expect(source).toContain('.curriculum-detail__assignment-week-controls :deep(.v-btn) {')
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

    it('uses the parent topic color for assigned week borders', () => {
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

        expect((wrapper.vm as any).weekAssignmentAccentColor('2025-09-08')).toBe('hsl(200, 70%, 48%)')
        expect((wrapper.vm as any).weekAssignmentAccentColor('2025-09-15')).toBe('hsl(232, 70%, 48%)')
        expect((wrapper.vm as any).weekAssignmentStyle('2025-09-15')).toEqual({
            '--week-assignment-accent': 'hsl(232, 70%, 48%)',
        })
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
})
