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

    it('filters course dates by the selected semester', () => {
        const computed = (CourseDates as any).computed
        const context = {
            selected_course: {
                course_dates: [
                    { id: 1, date: '2026-01-12' },
                    { id: 2, date: '2026-02-09' },
                    { id: 3, date: '2026-06-15' },
                ],
            },
            semesterCount: 2,
            activeSemester: 1,
            sem2StartDate: '2026-02-09',
        }

        expect(computed.filteredCourseDates.call(context).map((courseDate: { id: number }) => courseDate.id))
            .toEqual([1])

        context.activeSemester = 2
        expect(computed.filteredCourseDates.call(context).map((courseDate: { id: number }) => courseDate.id))
            .toEqual([2, 3])

        context.activeSemester = 3
        expect(computed.filteredCourseDates.call(context).map((courseDate: { id: number }) => courseDate.id))
            .toEqual([1, 2, 3])
    })

    it('shows the Curriculum card beside Termine with assigned and unassigned states', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')
        const curriculumCardStart = source.indexOf('data-testid="course-dates-curriculum-card"')
        const curriculumCardEnd = source.indexOf('<v-dialog v-model="deleteAllDatesDialogOpen"')
        const curriculumCard = source.slice(curriculumCardStart, curriculumCardEnd)

        expect(source).toContain('class="course-dates-panels"')
        expect(source).toContain('class="course-dates-panel"')
        expect(source).toContain('data-testid="course-dates-curriculum-card"')
        expect(source.indexOf('class="course-dates-panel"'))
            .toBeLessThan(source.indexOf('data-testid="course-dates-curriculum-card"'))
        expect(curriculumCard).toContain('Curriculum')
        expect(curriculumCard).toContain('v-if="!selectedCourseCurriculumId"')
        expect(curriculumCard).toContain('Diesem Kurs ist kein Curriculum zugewiesen.')
        expect(curriculumCard).toContain('Curriculum hinzufügen')
        expect(curriculumCard).toContain('@click="openCurriculumAssignmentDialog"')
        expect(curriculumCard).not.toContain('<v-chip')
        expect(curriculumCard).toContain('v-else-if="selectedCourseCurriculumTopics.length"')
        expect(curriculumCard).toContain('in selectedCourseCurriculumTopics')
        expect(curriculumCard).toContain('in topic.units')
        expect(curriculumCard).toContain('Dieses Curriculum enthält keine Inhalte.')
        expect(source).toContain('<v-dialog v-model="curriculumAssignmentDialogOpen" persistent max-width="520">')
        expect(source).toContain('v-model="curriculumAssignmentSelectionId"')
        expect(source).toContain('label="Curriculum auswählen"')
        expect(source).toContain('@click="cancelCurriculumAssignment"')
        expect(source).toContain('@click="saveCurriculumAssignment"')
        expect(source).toContain('courseDateInlineContent(courseDate)')
        expect(source).toContain('loadSelectedCourseCurriculumDetail')
        expect(source).not.toContain('mdi-eye-off-outline')
    })

    it('normalizes assigned curriculum topics and units for the Curriculum card', () => {
        const computed = (CourseDates as any).computed
        const context = {
            selectedCourseCurriculumForContent: {
                topics: [
                    {
                        id: 'topic-1',
                        title: 'Grundlagen',
                        units: [
                            { id: 'unit-1', title: 'Office 365', is_exam: false },
                            { id: 'unit-2', title: 'Prüfung', is_exam: true },
                        ],
                    },
                ],
            },
        }

        expect(computed.selectedCourseCurriculumTopics.call(context)).toEqual([
            {
                key: 'topic-1',
                selectionKey: 'topic:topic-1',
                title: 'Grundlagen',
                units: [
                    {
                        key: 'unit-1',
                        selectionKey: 'unit:topic-1:unit-1',
                        title: 'Office 365',
                        isExam: false,
                    },
                    {
                        key: 'unit-2',
                        selectionKey: 'unit:topic-1:unit-2',
                        title: 'Prüfung',
                        isExam: true,
                    },
                ],
            },
        ])
    })

    it('allows only one curriculum item to be selected at a time', () => {
        const methods = (CourseDates as any).methods
        const context: Record<string, string | null> = { selectedCurriculumItemKey: null }

        methods.selectCurriculumItem.call(context, 'topic:topic-1')
        expect(context.selectedCurriculumItemKey).toBe('topic:topic-1')

        methods.selectCurriculumItem.call(context, 'unit:topic-1:unit-1')
        expect(context.selectedCurriculumItemKey).toBe('unit:topic-1:unit-1')

        methods.selectCurriculumItem.call(context, 'unit:topic-1:unit-1')
        expect(context.selectedCurriculumItemKey).toBeNull()
    })

    it('loads the full assigned curriculum before rendering its topics', async () => {
        const show = vi.fn().mockResolvedValue({
            id: 3,
            title: 'DGB 1',
            topics: [{ id: 'topic-1', title: 'Grundlagen', units: [] }],
        })
        const context: Record<string, any> = {
            selectedCourseCurriculumId: 3,
            selectedCurriculumDetail: null,
            selectedCurriculumDetailLoadingId: null,
            curriculumStore: { show },
        }

        await (CourseDates as any).methods.loadSelectedCourseCurriculumDetail.call(context)

        expect(show).toHaveBeenCalledWith(3)
        expect(context.selectedCurriculumDetail).toEqual({
            id: 3,
            title: 'DGB 1',
            topics: [{ id: 'topic-1', title: 'Grundlagen', units: [] }],
        })
        expect(context.selectedCurriculumDetailLoadingId).toBeNull()
    })

    it('moves the date actions into the Termine row and removes the entry area chip', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')
        const termineRowPosition = source.indexOf('<v-card-title class="text-subtitle-1')
        const countPosition = source.indexOf('{{ displayedCourseDatesCount }}')
        const deletePosition = source.indexOf('title="Alle Termine löschen"')
        const addPosition = source.indexOf('title="Termin hinzufügen"')

        expect(termineRowPosition).toBeLessThan(countPosition)
        expect(countPosition).toBeLessThan(deletePosition)
        expect(deletePosition).toBeLessThan(addPosition)
        expect(source).not.toContain('<template #header-actions>')
        expect(source).not.toContain('selectedCourseEntryAreaName')
        expect(source).not.toContain('course-date-entry-area-chip')
        expect(source).not.toContain('title="Zugewiesener Eintragsbereich"')
    })

    it('shows the semester selector in the new Zeitraum card and keeps the date range selector compact only', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')
        const semesterSelectionPosition = source.indexOf('data-testid="course-dates-semester-selection"')

        expect(semesterSelectionPosition).toBeGreaterThan(-1)
        expect(source).not.toContain('<ItsGridBox')
        expect(source).not.toContain('icon="mdi-calendar"')
        expect(source).not.toContain('<template #title>')
        expect(source).not.toContain('Termine – {{ selected_course.title }}')
        expect(source).not.toContain('selectedCourseClasses')
        expect(source).toContain('class="mt-3 mb-3"')
        expect(source).toContain('data-testid="course-dates-semester-selection"')
        expect(source).toContain('variant="tonal"')
        expect(source).toContain('>Zeitraum:</span>')
        expect(source).toContain('class="d-flex align-center flex-wrap ga-3 px-3 py-2"')
        expect(source).toContain('<v-btn :value="1" size="small">1. Sem</v-btn>')
        expect(source).toContain('<v-btn :value="2" size="small">2. Sem</v-btn>')
        expect(source).toContain('<v-btn :value="3" size="small">Sem 1+2</v-btn>')
        expect(semesterSelectionPosition).toBeLessThan(source.indexOf('dateRangeSelection'))
        expect(source).toContain('<v-card v-if="compactStudentView" tile flat color="transparent"')
        expect(source).toContain('<div v-if="compactStudentView" class="ml-auto d-flex">')
        expect(source).not.toContain('class="course-date-semester-selection')
    })

    it('removes the outer grid row above the Zeitraum selector', () => {
        const datesSource = readFileSync(
            resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'),
            'utf8',
        )

        expect(datesSource).not.toContain('<ItsGridBox')
        expect(datesSource).not.toContain('hide-header')
        expect(datesSource).not.toContain('icon="mdi-calendar"')
        expect(datesSource).toContain(
            '<v-card v-if="selected_course" class="w-100" color="transparent" flat rounded="0" :disabled="isGridDisabled">',
        )
    })

    it('renders each date as a full-width row', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')

        expect(source).toContain('.course-dates-grid {\n    padding: 8px;\n    gap: 8px;')
        expect(source).toContain('grid-template-columns: minmax(0, 1fr);')
        expect(source).not.toContain('grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);')
        expect(source).toContain('min-width: 0;')
        expect(source).toContain('width: 100%;')
    })

    it('confirms and deletes all dates for the selected course', async () => {
        const methods = (CourseDates as any).methods
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')
        const destroyAll = vi.fn().mockResolvedValue(true)
        const index = vi.fn().mockResolvedValue(true)
        const ctx: Record<string, any> = {
            selected_course: { id: 16, course_dates: [{ id: 1 }, { id: 2 }] },
            selected_courseDate: { id: 2 },
            delete_date_id: 2,
            deleteAllDatesDialogOpen: true,
            courseDateStore: { destroyAll },
            courseStore: { index },
            runDateMutation: vi.fn(async (_action, callback) => callback()),
        }

        await methods.confirmDeleteAllDates.call(ctx)

        expect(source).toContain('title="Alle Termine löschen"')
        expect(source).toContain('Sollen wirklich alle {{ totalCourseDatesCount }} Termine des Kurses gelöscht werden?')
        expect(ctx.runDateMutation).toHaveBeenCalledWith('delete-all-dates', expect.any(Function))
        expect(destroyAll).toHaveBeenCalledWith(16)
        expect(index).toHaveBeenCalledTimes(1)
        expect(ctx.selected_courseDate).toBeNull()
        expect(ctx.delete_date_id).toBeNull()
        expect(ctx.deleteAllDatesDialogOpen).toBe(false)
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

    it('uses only the chip to identify the next date', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')
        const methods = (CourseDates as any).methods
        const courseDate = { id: 278 }
        const context = {
            highlightedDateId: 278,
            selected_courseDate: courseDate,
            hasStatus: vi.fn().mockReturnValue(false),
            isDateToday: vi.fn().mockReturnValue(false),
            courseDateRowMarkingColor: vi.fn().mockReturnValue(null),
        }

        expect(methods.courseDateRowClass.call(context, courseDate)).not.toContain('course-date-row--next')
        expect(methods.courseDateRowClass.call(context, courseDate)).toContain('course-date-row--selected')
        expect(methods.courseDateHighlightStyle.call(context, courseDate, 0)).toEqual({})
        expect(source).toContain("{{ isDateToday(courseDate) ? 'Heute' : 'Nächster' }}")
        expect(source).not.toContain('.course-date-row--next')
    })

    it('shows a clear background on the selected date and curriculum item', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')

        expect(source).toContain("classes.push('course-date-row--selected')")
        expect(source).toContain('selectedCurriculumItemKey === topic.selectionKey')
        expect(source).toContain('selectedCurriculumItemKey === unit.selectionKey')
        expect(source).toContain('.course-date-row--selected {')
        expect(source).toContain('.course-curriculum-item--selected {')
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

    it('removes the per-date students button', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')

        expect(source).not.toContain('title="Schülerliste anzeigen"')
        expect(source).not.toContain('@click="switchToStudents(courseDate)"')
        expect(source).not.toContain('switchToStudents(courseDate) {')
    })

    it('uses a configured grading work color for the complete date row', () => {
        const methods = (CourseDates as any).methods
        const courseDate = { id: 7, date: '2026-09-21', status: [] }
        const context = {
            selected_course: {
                teaching_entry_area: {
                    entry_definitions: [
                        {
                            short_name: 'PÜ',
                            category: 'Benotung',
                            has_table_marking: true,
                            table_marking_color: 'purple',
                        },
                    ],
                },
            },
            highlightedDateId: null,
            courseWorksForDate: vi.fn().mockReturnValue([{ type: 'PÜ' }]),
            hasStatus: methods.hasStatus,
            isDateToday: methods.isDateToday,
            courseDateRowMarkingColor: methods.courseDateRowMarkingColor,
        }

        expect(methods.courseDateRowMarkingColor.call(context, courseDate)).toBe('purple')
        expect(methods.courseDateRowClass.call(context, courseDate)).toContain('course-date-row--marked-purple')

        context.courseWorksForDate.mockReturnValue([{ type: 'A' }])
        expect(methods.courseDateRowMarkingColor.call(context, courseDate)).toBeNull()
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

    it('opens the persistent curriculum assignment dialog and loads curricula', async () => {
        const index = vi.fn().mockResolvedValue(true)
        const ctx: Record<string, any> = {
            curriculumStore: { index },
            curriculumAssignmentDialogOpen: false,
            curriculumAssignmentLoading: false,
            curriculumAssignmentSelectionId: 14,
        }

        await (CourseDates as any).methods.openCurriculumAssignmentDialog.call(ctx)

        expect(ctx.curriculumAssignmentDialogOpen).toBe(true)
        expect(ctx.curriculumAssignmentLoading).toBe(false)
        expect(ctx.curriculumAssignmentSelectionId).toBeNull()
        expect(index).toHaveBeenCalledWith({ page: 1, perPage: 250 })
    })

    it('assigns the selected curriculum and closes the dialog', async () => {
        const update = vi.fn().mockResolvedValue({ id: 20, teaching_curriculum_id: 14 })
        const refreshCourseById = vi.fn().mockResolvedValue({ id: 20, teaching_curriculum_id: 14 })
        const selectedCourse = {
            id: 20,
            title: 'Deutsch',
            teaching_schema_id: 3,
            teaching_curriculum_id: null,
        }
        const ctx: Record<string, any> = {
            selected_course: selectedCourse,
            curriculumAssignmentDialogOpen: true,
            curriculumAssignmentSaving: false,
            curriculumAssignmentSelectionId: 14,
            courseStore: { update, refreshCourseById },
        }

        await (CourseDates as any).methods.saveCurriculumAssignment.call(ctx)

        expect(update).toHaveBeenCalledWith({
            ...selectedCourse,
            teaching_curriculum_id: 14,
        })
        expect(refreshCourseById).toHaveBeenCalledWith(20)
        expect(ctx.curriculumAssignmentDialogOpen).toBe(false)
        expect(ctx.curriculumAssignmentSelectionId).toBeNull()
        expect(ctx.curriculumAssignmentSaving).toBe(false)
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

    it('resolves matching legacy curriculum entries without free-week overrides', () => {
        const methods = (CourseDates as any).methods
        const ctx: Record<string, any> = {
            config: {
                selected_schoolyear: {
                    from: '2025-09-01',
                    until: '2025-09-30',
                },
            },
            selectedCourseCurriculumForContent: {
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
        expect(methods.curriculumEntriesForCourseDate.call(ctx, { date: '2025-09-15' }).map((entry: Record<string, any>) => entry.label)).toEqual([
            'Schreibuebungen',
            'Monatsprojekt',
        ])
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
