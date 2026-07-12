import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import CourseDates from '@/pages/admin/teaching/overview/components/CourseDates.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
    },
}))

describe('CourseDates course-specific schema', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('prefers the selected course schema snapshot for semester count', () => {
        const computed = (CourseDates as any).computed
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_schema_id: 'schema-teacher',
                teacher_teaching_schema: {
                    id: 'schema-teacher',
                    grading: { semester_count: 2 },
                },
            },
            teachingStore: {
                schemaById: () => ({ id: 'schema-global', grading: { semester_count: 1 } }),
            },
        }

        ctx.selectedCourseSchema = computed.selectedCourseSchema.call(ctx)

        expect((ctx.selectedCourseSchema as any).id).toBe('schema-teacher')
        expect(computed.semesterCount.call(ctx)).toBe(2)
    })

    it('shows the assigned curriculum in the dates card header', () => {
        const computed = (CourseDates as any).computed
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_curriculum: {
                    id: 10,
                    title: 'Deutsch 6',
                },
            },
        }

        expect(computed.selectedCourseCurriculumTitle.call(ctx)).toBe('Deutsch 6')
        expect(source).toContain('v-if="selectedCourseCurriculumTitle"')
        expect(source).toContain('class="course-date-curriculum-chip"')
        expect(source).toContain('class="course-date-curriculum-inline pl-1 pr-2"')
        expect(source).toContain('class="course-date-curriculum-stack"')
        expect(source).toContain('class="course-date-curriculum-stack__content"')
        expect(source).toContain('class="course-date-curriculum-stack__entry d-flex align-center"')
        expect(source).toContain('courseDateInlineContent(courseDate)')
        expect(source).toContain(':key="`${courseDate.id}-inline-${entryIndex}`"')
        expect(source).toContain('await this.loadSelectedCourseCurriculumDetail()')
        expect(source).not.toContain('mdi-eye-off-outline')
        expect(source).not.toContain('show_dates = false')
    })

    it('shows the semester selector in the course title row and keeps the date range selector compact only', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')
        const titleRowPosition = source.indexOf('class="course-dates-title-row')
        const semesterSelectionPosition = source.indexOf('class="course-date-semester-selection')
        const headerActionsPosition = source.indexOf('<template #header-actions>')

        expect(titleRowPosition).toBeLessThan(semesterSelectionPosition)
        expect(semesterSelectionPosition).toBeLessThan(headerActionsPosition)
        expect(source).toContain('class="course-date-semester-selection d-flex justify-start"')
        expect(source).toContain('<v-btn :value="1" size="small">1. Sem</v-btn>')
        expect(source).toContain('<v-btn :value="2" size="small">2. Sem</v-btn>')
        expect(source).toContain('<v-btn :value="3" size="small">Sem 1+2</v-btn>')
        expect(source.indexOf('course-date-semester-selection')).toBeLessThan(source.indexOf('dateRangeSelection'))
        expect(source).toContain('<v-card v-if="compactStudentView" tile flat color="transparent"')
        expect(source).toContain('<div v-if="compactStudentView" class="ml-auto d-flex">')
        expect(source).not.toContain('!compactStudentView && semesterCount === 2')
    })

    it('renders each date as a full-width row', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')

        expect(source).toContain('.course-dates-grid {\n    padding: 8px;\n    gap: 8px;')
        expect(source).toContain('grid-template-columns: minmax(0, 1fr);')
        expect(source).not.toContain('grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);')
        expect(source).toContain('min-width: 0;')
        expect(source).toContain('width: 100%;')
    })

    it('does not show the obsolete attendance checked state for dates', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')

        expect(source).not.toContain('Anwesenheit geprüft')
        expect(source).not.toContain('isAttendanceChecked')
        expect(source).not.toContain('attendance_checked')
        expect(source).not.toContain('att_checked:1')
    })

    it('renders the hour chips next to the date', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')
        const dateTitlePosition = source.indexOf('class="course-date-title"')
        const hourChipPosition = source.indexOf('<v-chip v-for="h in courseDate.hours"')
        const highlightedDatePosition = source.indexOf('v-if="highlightedDateId === courseDate.id"')

        expect(source).toContain('class="d-flex align-center flex-wrap ga-2"')
        expect(dateTitlePosition).toBeLessThan(hourChipPosition)
        expect(hourChipPosition).toBeLessThan(highlightedDatePosition)
        expect(source).not.toContain('class="course-date-hours')
    })

    it('uses abbreviated German weekday labels', () => {
        const methods = (CourseDates as any).methods

        expect(methods.getWeekday('2026-07-13')).toBe('Mo')
        expect(methods.getWeekday('2026-07-14')).toBe('Di')
    })

    it('renders the date content in the date row', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')
        const hourChipPosition = source.indexOf('<v-chip v-for="h in courseDate.hours"')
        const inlineContentPosition = source.indexOf('class="course-date-inline-content text-body-2 text-medium-emphasis"')
        const dateActionsPosition = source.indexOf('class="course-date-actions')

        expect(hourChipPosition).toBeLessThan(inlineContentPosition)
        expect(inlineContentPosition).toBeLessThan(dateActionsPosition)
        expect(source).toContain('{{ courseDateInlineContent(courseDate) }}')
        expect(source).toContain('v-if="courseDateAdoptedMaterials(courseDate).length"')
        expect(source).not.toContain('v-html="courseDateDisplayHtml(courseDate)"')
    })

    it('renders assigned work chips in the date row', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')
        const hourChipPosition = source.indexOf('<v-chip v-for="h in courseDate.hours"')
        const workChipPosition = source.indexOf('v-for="work in courseWorksForDate(courseDate)"')
        const inlineContentPosition = source.indexOf('class="course-date-inline-content text-body-2 text-medium-emphasis"')

        expect(hourChipPosition).toBeLessThan(workChipPosition)
        expect(workChipPosition).toBeLessThan(inlineContentPosition)
        expect(source).not.toContain('class="course-date-works')
    })

    it('renders the free-day reason chip in the date row', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')
        const hourChipPosition = source.indexOf('<v-chip v-for="h in courseDate.hours"')
        const freeDayChipPosition = source.indexOf('class="course-date-free-reason"')
        const workChipPosition = source.indexOf('v-for="work in courseWorksForDate(courseDate)"')

        expect(hourChipPosition).toBeLessThan(freeDayChipPosition)
        expect(freeDayChipPosition).toBeLessThan(workChipPosition)
        expect(source).not.toContain('class="course-date-free-reason text-caption')
    })

    it('keeps date action buttons visible beside long assigned work labels', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')

        expect(source).toContain('.course-date-left {')
        expect(source).toContain('flex: 1 1 auto;')
        expect(source).toContain('.course-date-work-chip {')
        expect(source).toContain('flex: 0 1 auto;')
        expect(source).toContain('align-self: flex-start;')
    })

    it('does not show a curriculum label when none is assigned', () => {
        const computed = (CourseDates as any).computed
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_curriculum: null,
            },
        }

        expect(computed.selectedCourseCurriculumTitle.call(ctx)).toBe('')
    })

    it('shows all filtered dates in the standalone dates panel', () => {
        const computed = (CourseDates as any).computed
        const dates = [
            { id: 1, date: '2026-01-01' },
            { id: 2, date: '2026-01-08' },
            { id: 3, date: '2026-01-15' },
            { id: 4, date: '2026-01-22' },
            { id: 5, date: '2026-01-29' },
            { id: 6, date: '2026-02-05' },
            { id: 7, date: '2026-02-12' },
        ]
        const ctx: Record<string, unknown> = {
            filteredCourseDates: dates,
            selected_courseDate: { id: 4, date: '2026-01-22' },
            highlightedDateId: 4,
            dateRangeSelection: ['today'],
            compactStudentView: false,
        }

        expect(computed.displayedCourseDates.call(ctx).map((courseDate: Record<string, unknown>) => courseDate.id)).toEqual([1, 2, 3, 4, 5, 6, 7])
    })

    it('does not limit displayed dates to a compact window', () => {
        const computed = (CourseDates as any).computed
        const dates = [
            { id: 1, date: '2026-01-01' },
            { id: 2, date: '2026-01-08' },
            { id: 3, date: '2026-01-15' },
            { id: 4, date: '2026-01-22' },
            { id: 5, date: '2026-01-29' },
            { id: 6, date: '2026-02-05' },
            { id: 7, date: '2026-02-12' },
        ]
        const ctx: Record<string, unknown> = {
            filteredCourseDates: dates,
            selected_courseDate: { id: 4, date: '2026-01-22' },
            highlightedDateId: 4,
            dateRangeSelection: ['today'],
            compactStudentView: true,
        }

        expect(computed.displayedCourseDates.call(ctx).map((courseDate: Record<string, unknown>) => courseDate.id)).toEqual([1, 2, 3, 4, 5, 6, 7])
    })

    it('targets today or the next visible date for highlighting even when dates are unsorted', () => {
        vi.useFakeTimers()
        vi.setSystemTime(new Date('2026-05-16T10:00:00'))

        try {
            const computed = (CourseDates as any).computed
            const methods = (CourseDates as any).methods
            const ctx: Record<string, unknown> = {
                displayedCourseDates: [
                    { id: 3, date: '2026-05-20' },
                    { id: 1, date: '2026-05-13' },
                    { id: 2, date: '2026-05-16' },
                ],
                toDateString: methods.toDateString,
                normalizeDateString: methods.normalizeDateString,
            }

            ctx.highlightedCourseDate = computed.highlightedCourseDate.call(ctx)

            expect(ctx.highlightedCourseDate).toEqual({ id: 2, date: '2026-05-16' })
            expect(computed.highlightedDateId.call(ctx)).toBe(2)

            ctx.displayedCourseDates = [
                { id: 5, date: '2026-05-23' },
                { id: 4, date: '2026-05-20' },
                { id: 1, date: '2026-05-13' },
            ]

            expect(computed.highlightedCourseDate.call(ctx)).toEqual({ id: 4, date: '2026-05-20' })
        } finally {
            vi.useRealTimers()
        }
    })

    it('scrolls the highlighted date into the vertical middle of the screen', () => {
        vi.useFakeTimers()

        try {
            const methods = (CourseDates as any).methods
            const scrollIntoView = vi.fn()
            const ctx = {
                $refs: {
                    highlightedDateItem: [{ $el: { scrollIntoView } }],
                },
                $nextTick: vi.fn((callback) => callback()),
                scrollElementFromRef: methods.scrollElementFromRef,
            }

            methods.scrollToHighlightedDate.call(ctx)
            vi.advanceTimersByTime(150)

            expect(scrollIntoView).toHaveBeenCalledWith({
                behavior: 'smooth',
                block: 'center',
                inline: 'nearest',
            })
        } finally {
            vi.useRealTimers()
        }
    })

    it('shows works assigned to course dates and respects group-specific work dates', () => {
        const methods = (CourseDates as any).methods
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')
        const ctx: Record<string, any> = {
            courseWorks: [
                {
                    id: 1,
                    type: 'GA',
                    title: 'Projekt',
                    is_group_work: true,
                    date_for_all_groups: '2026-05-10',
                    groups: [
                        { date: '2026-05-16', student_ids: [1, 2] },
                        { date: '2026-05-17', student_ids: [3, 4] },
                        { date: '2026-05-16T00:00:00.000000Z', student_ids: [5, 6] },
                    ],
                },
                {
                    id: 2,
                    type: 'SA',
                    title: 'Schularbeit',
                    is_group_work: false,
                    date_for_all_groups: '2026-05-16',
                    groups: [],
                },
                {
                    id: 3,
                    type: 'GA',
                    title: 'Praesentation',
                    is_group_work: true,
                    date_for_all_groups: '2026-05-16',
                    groups: [
                        { student_ids: [1, 2] },
                        { student_ids: [3, 4] },
                    ],
                },
            ],
        }
        Object.assign(ctx, methods)

        const assignments = methods.courseWorksForDate.call(ctx, { date: '2026-05-16' })
        const globalOnlyAssignments = methods.courseWorksForDate.call(ctx, { date: '2026-05-10' })

        expect(source).toContain('courseWorksForDate(courseDate)')
        expect(source).toContain('class="course-date-work-chip cursor-pointer"')
        expect(source).toContain('@click.stop="openCourseWork(work)"')
        expect(source).toContain('prepend-icon="mdi-clipboard-text"')
        expect(assignments.map((assignment: Record<string, any>) => assignment.label)).toEqual([
            'GA: Projekt (Gr. 1, 3)',
            'SA: Schularbeit (Einzelarbeit)',
            'GA: Praesentation (alle Gruppen)',
        ])
        expect(assignments.map((assignment: Record<string, any>) => assignment.id)).toEqual([1, 2, 3])
        expect(assignments.map((assignment: Record<string, any>) => assignment.isGroupWork)).toEqual([true, false, true])
        expect(globalOnlyAssignments).toEqual([])
    })

    it('jumps from a date work chip to the works panel and selects that work', () => {
        const methods = (CourseDates as any).methods
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')
        const replace = vi.fn(() => Promise.resolve())
        const ctx: Record<string, any> = {
            courseWorks: [
                { id: 10, title: 'Andere Arbeit' },
                { id: 23, title: 'Excel' },
            ],
            selected_courseWork: null,
            show_dates: true,
            show_works: false,
            $route: { query: { course: '20', grades: 'sem1,sem2,year', panel: 'dates' } },
            $router: { replace },
        }

        methods.openCourseWork.call(ctx, { id: 23 })

        expect(source).toContain("['selected_course', 'show_dates', 'show_students', 'show_works']")
        expect(ctx.selected_courseWork).toEqual({ id: 23, title: 'Excel' })
        expect(ctx.show_dates).toBe(false)
        expect(ctx.show_works).toBe(true)
        expect(replace).toHaveBeenCalledWith({
            query: { course: '20', grades: 'sem1,sem2,year', panel: 'works', work: '23', return_panel: 'dates' },
        })
    })

    it('keeps all selected dates visible when selecting another date', () => {
        const computed = (CourseDates as any).computed
        const dates = [
            { id: 1, date: '2026-01-01' },
            { id: 2, date: '2026-01-08' },
            { id: 3, date: '2026-01-15' },
            { id: 4, date: '2026-01-22' },
            { id: 5, date: '2026-01-29' },
            { id: 6, date: '2026-02-05' },
            { id: 7, date: '2026-02-12' },
        ]
        const ctx: Record<string, unknown> = {
            filteredCourseDates: dates,
            selected_courseDate: { id: 6, date: '2026-02-05' },
            highlightedDateId: 4,
            dateRangeSelection: ['today'],
            compactStudentView: true,
        }

        expect(computed.displayedCourseDates.call(ctx).map((courseDate: Record<string, unknown>) => courseDate.id)).toEqual([1, 2, 3, 4, 5, 6, 7])
    })

    it('keeps all selected dates visible when no highlighted date exists', () => {
        const computed = (CourseDates as any).computed
        const dates = [
            { id: 1, date: '2026-01-01' },
            { id: 2, date: '2026-01-08' },
            { id: 3, date: '2026-01-15' },
            { id: 4, date: '2026-01-22' },
            { id: 5, date: '2026-01-29' },
            { id: 6, date: '2026-02-05' },
            { id: 7, date: '2026-02-12' },
        ]
        const ctx: Record<string, unknown> = {
            filteredCourseDates: dates,
            selected_courseDate: { id: 4, date: '2026-01-22' },
            highlightedDateId: null,
            dateRangeSelection: ['today'],
            compactStudentView: true,
        }

        expect(computed.displayedCourseDates.call(ctx).map((courseDate: Record<string, unknown>) => courseDate.id)).toEqual([1, 2, 3, 4, 5, 6, 7])
    })

    it('resolves matching curriculum entries for a course date and prioritizes free weeks', () => {
        const methods = (CourseDates as any).methods
        const ctx: Record<string, any> = {
            config: {
                selected_schoolyear: {
                    from: '2025-09-01',
                    until: '2025-09-30',
                },
            },
            selectedCourseCurriculumForContent: {
                free_weeks: ['2025-09-15'],
                topics: [
                    {
                        id: 'topic-all-weeks',
                        title: 'Schreibuebungen',
                        assignment_type: 'all_weeks',
                        week_keys: [],
                        units: [],
                    },
                    {
                        id: 'topic-month',
                        title: 'Monatsprojekt',
                        assignment_type: 'month',
                        month_key: '2025-09',
                        units: [],
                    },
                    {
                        id: 'topic-search',
                        title: 'Suchmaschinen und Internetrecherche - Teil 1',
                        assignment_type: 'none',
                        week_keys: [],
                        units: [
                            {
                                id: 'unit-project',
                                title: 'Projekt: Internetrecherche',
                                assignment_type: 'weeks',
                                week_keys: ['2025-09-08'],
                            },
                        ],
                    },
                ],
            },
        }

        Object.assign(ctx, methods)

        expect(methods.curriculumEntriesForCourseDate.call(ctx, { date: '2025-09-08' }).map((entry: Record<string, any>) => entry.label)).toEqual([
            'Schreibuebungen',
            'Monatsprojekt',
            'Suchmaschinen und Internetrecherche - Teil 1: Projekt: Internetrecherche',
        ])
        expect(methods.curriculumEntriesForCourseDate.call(ctx, { date: '2025-09-15' }).map((entry: Record<string, any>) => entry.label)).toEqual(['Frei'])
    })

    it('normalizes course date content to a single inline line', () => {
        const methods = (CourseDates as any).methods

        expect(methods.courseDateInlineContent.call({}, {
            content: '<p>Algorithmen, Flussdiagramm</p><p>Schleifen, Zählen bis 5.</p>',
        })).toBe('Algorithmen, Flussdiagramm Schleifen, Zählen bis 5.')

        expect(methods.courseDateInlineContent.call({}, { content: '   ' })).toBe('')
    })

    it('enters saving mode before waiting for the date content update request', async () => {
        const methods = (CourseDates as any).methods
        let continueNextTick: (() => void) | null = null
        let resolveUpdate: ((value: unknown) => void) | null = null

        const update = vi.fn(() => new Promise((resolve) => {
            resolveUpdate = resolve
        }))
        const index = vi.fn().mockResolvedValue(true)

        const ctx: Record<string, any> = {
            action: 'edit_course_date_content',
            editing_content_id: 7,
            saving_content_id: null,
            content_drafts: { 7: '<p>Neuer Inhalt</p>' },
            courseDateStore: { update },
            courseStore: { index },
            show_contents: true,
            collapsed_content_ids: [7],
            expanded_content_ids: [],
            isSavingContent: false,
            $nextTick: vi.fn(() => new Promise<void>((resolve) => {
                continueNextTick = resolve
            })),
        }

        const savePromise = methods.saveContent.call(ctx, {
            id: 7,
            date: '2026-04-23',
        })

        expect(ctx.saving_content_id).toBe(7)
        expect(ctx.$nextTick).toHaveBeenCalledTimes(1)
        expect(update).not.toHaveBeenCalled()

        continueNextTick?.()
        await Promise.resolve()

        expect(update).toHaveBeenCalledWith({
            id: 7,
            date: '2026-04-23',
            content: '<p>Neuer Inhalt</p>',
        })

        resolveUpdate?.(true)
        await savePromise

        expect(index).toHaveBeenCalledTimes(1)
        expect(ctx.editing_content_id).toBeNull()
        expect(ctx.action).toBe('')
        expect(ctx.saving_content_id).toBeNull()
        expect(ctx.collapsed_content_ids).toEqual([])
    })

    it('selects all file attachments by default when loading adoptable materials', async () => {
        const methods = (CourseDates as any).methods
        vi.mocked(axios.get).mockResolvedValueOnce({
            data: {
                data: {
                    id: 10,
                    attachments: [
                        { id: 101, attachment_type: 'file', name: 'Arbeitsblatt.pdf' },
                        { id: 102, attachment_type: 'link', name: 'Quelle' },
                    ],
                },
            },
        })

        const ctx: Record<string, any> = {
            selectedCourseCurriculumId: 7,
            adoptDialogCourseDate: { adopted_materials: [] },
            adoptDialogEntry: {
                materials: [{ id: 10, title: 'Arbeitsblatt' }],
            },
            adoptDialogMaterialCards: [],
            adoptDialogMaterialLoading: false,
            adoptDialogSelectedAttachmentIdsByMaterial: {},
        }
        Object.assign(ctx, methods)

        await methods.loadAdoptDialogMaterialCards.call(ctx)

        expect(axios.get).toHaveBeenCalledWith('/api/admin/teaching/curricula/7/materials/cards/10')
        expect(ctx.adoptDialogSelectedAttachmentIdsByMaterial[10]).toEqual([101])
        expect(ctx.adoptDialogMaterialCards).toHaveLength(1)
        expect(ctx.adoptDialogMaterialLoading).toBe(false)
    })

    it('does not mark a curriculum entry as fully adopted until all attachments are adopted', () => {
        const methods = (CourseDates as any).methods
        const ctx: Record<string, any> = {
            adoptDialogMaterialCards: [],
        }
        Object.assign(ctx, methods)

        const courseDate = {
            adopted_materials: [
                {
                    title: 'Quellenarbeit',
                    source_material_card_id: 10,
                    attachments: [
                        { source_material_card_attachment_id: 101 },
                    ],
                },
            ],
        }
        const entry = {
            label: 'Quellenarbeit',
            materials: [
                { id: 10, title: 'Material', attachments_count: 2 },
            ],
        }

        expect(methods.isCurriculumEntryFullyAdopted.call(ctx, courseDate, entry)).toBe(false)

        courseDate.adopted_materials[0].attachments.push({ source_material_card_attachment_id: 102 })

        expect(methods.isCurriculumEntryFullyAdopted.call(ctx, courseDate, entry)).toBe(true)
    })

    it('groups adopted curriculum materials under one curriculum title', () => {
        const methods = (CourseDates as any).methods
        const ctx: Record<string, any> = {}
        Object.assign(ctx, methods)

        const groups = methods.courseDateAdoptedMaterialGroups.call(ctx, {
            adopted_materials: [
                {
                    id: 1,
                    title: 'Suchmaschinen und Internetrecherche - Teil 1: Projekt: Internetrecherche',
                    material_title: 'Word - Einführung',
                    source_material_card_id: 10,
                    type: 'Arbeitsblatt',
                    attachments: [{ id: 101, name: 'Word.pdf' }],
                },
                {
                    id: 2,
                    title: 'Suchmaschinen und Internetrecherche - Teil 1: Projekt: Internetrecherche',
                    material_title: 'Word - Einführung',
                    source_material_card_id: 10,
                    type: 'Arbeitsblatt',
                    attachments: [{ id: 102, name: 'Übung.pdf' }],
                },
                {
                    id: 3,
                    title: 'Suchmaschinen und Internetrecherche - Teil 1: Projekt: Internetrecherche',
                    material_title: 'Browser Grundlagen',
                    source_material_card_id: 11,
                    type: 'Link',
                    attachments: [{ id: 103, name: 'Recherche.pdf' }],
                },
            ],
        })

        expect(groups).toHaveLength(1)
        expect(groups[0].title).toBe('Suchmaschinen und Internetrecherche - Teil 1: Projekt: Internetrecherche')
        expect(groups[0].materials).toHaveLength(2)
        expect(groups[0].materials[0].title).toBe('Word - Einführung')
        expect(groups[0].materials[0].attachments.map((attachment: Record<string, any>) => attachment.name)).toEqual(['Word.pdf', 'Übung.pdf'])
        expect(groups[0].materials[1].title).toBe('Browser Grundlagen')
    })

    it('marks duplicate single adopted material so only the checked curriculum title is rendered', () => {
        const methods = (CourseDates as any).methods
        const ctx: Record<string, any> = {}
        Object.assign(ctx, methods)

        const groups = methods.courseDateAdoptedMaterialGroups.call(ctx, {
            adopted_materials: [
                {
                    id: 1,
                    title: 'Schreibübungen',
                    material_title: 'Schreibübungen',
                    source_material_card_id: 10,
                    type: 'Arbeitsblatt',
                    attachments: [],
                },
            ],
        })

        expect(groups).toHaveLength(1)
        expect(groups[0].title).toBe('Schreibübungen')
        expect(groups[0].duplicateSingleMaterial).toBe(true)
        expect(groups[0].materials[0].title).toBe('Schreibübungen')
    })

    it('selects only missing attachments when reopening a partially adopted material', async () => {
        const methods = (CourseDates as any).methods
        vi.mocked(axios.get).mockResolvedValueOnce({
            data: {
                data: {
                    id: 10,
                    attachments: [
                        { id: 101, attachment_type: 'file', name: 'Bereits übernommen.pdf' },
                        { id: 102, attachment_type: 'file', name: 'Noch offen.pdf' },
                    ],
                },
            },
        })

        const ctx: Record<string, any> = {
            selectedCourseCurriculumId: 7,
            adoptDialogCourseDate: {
                adopted_materials: [
                    {
                        source_material_card_id: 10,
                        attachments: [
                            { source_material_card_attachment_id: 101 },
                        ],
                    },
                ],
            },
            adoptDialogEntry: {
                materials: [{ id: 10, title: 'Arbeitsblatt', attachments_count: 2 }],
            },
            adoptDialogMaterialCards: [],
            adoptDialogMaterialLoading: false,
            adoptDialogSelectedAttachmentIdsByMaterial: {},
            adoptDialogSelectedMaterialIds: [],
        }
        Object.assign(ctx, methods)

        await methods.loadAdoptDialogMaterialCards.call(ctx)

        expect(ctx.adoptDialogSelectedMaterialIds).toEqual([10])
        expect(ctx.adoptDialogSelectedAttachmentIdsByMaterial[10]).toEqual([102])
        expect(methods.isAdoptDialogMaterialFullyAdopted.call(ctx, { id: 10 })).toBe(false)
    })

    it('sends selected attachment ids when adopting curriculum material', async () => {
        const methods = (CourseDates as any).methods
        vi.mocked(axios.post).mockResolvedValueOnce({ data: {} })

        const ctx: Record<string, any> = {
            adoptDialogCourseDate: { id: 15 },
            adoptDialogEntry: {},
            adoptDialogText: 'Quellenarbeit',
            adoptDialogSelectedMaterialIds: [10, 11],
            adoptDialogSelectedAttachmentIdsByMaterial: {
                10: [101],
                11: [],
            },
            adoptSaving: false,
            courseStore: {
                index: vi.fn().mockResolvedValue(true),
            },
            closeAdoptDialog: vi.fn(),
        }

        await methods.confirmAdopt.call(ctx)

        expect(axios.post).toHaveBeenCalledWith('/api/admin/teaching/course_dates/15/adopt-curriculum-content', {
            content: 'Quellenarbeit',
            material_card_ids: [10, 11],
            material_attachment_ids: {
                10: [101],
                11: [],
            },
        })
        expect(ctx.courseStore.index).toHaveBeenCalledTimes(1)
        expect(ctx.closeAdoptDialog).toHaveBeenCalledTimes(1)
        expect(ctx.adoptSaving).toBe(false)
    })
})
