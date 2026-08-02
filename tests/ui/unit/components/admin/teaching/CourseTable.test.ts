import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import CourseTable from '@/pages/admin/teaching/overview/components/CourseTable.vue'

vi.mock('axios', () => ({
    default: {
        delete: vi.fn(),
        post: vi.fn(),
    },
}))

describe('CourseTable', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('sorts course date rows by date', () => {
        const ctx = {
            selected_course: {
                course_dates: [
                    { id: 3, date: '2026-03-12' },
                    { id: 1, date: '2026-01-08' },
                    { id: 2, date: '2026-01-08' },
                ],
            },
        }

        const rows = (CourseTable as any).computed.sortedCourseDates.call(ctx)

        expect(rows.map((row: { id: number }) => row.id)).toEqual([1, 2, 3])
    })

    it('filters course dates by the selected semester', () => {
        const computed = (CourseTable as any).computed
        const courseDates = [
            { id: 1, date: '2026-01-08' },
            { id: 2, date: '2026-02-09' },
            { id: 3, date: '2026-06-12' },
        ]
        const context = {
            selected_course: { course_dates: courseDates },
            activeSemester: 1,
            semesterTwoStartDate: '2026-02-09',
        }

        expect(computed.sortedCourseDates.call(context).map((courseDate: { id: number }) => courseDate.id))
            .toEqual([1])

        context.activeSemester = 2
        expect(computed.sortedCourseDates.call(context).map((courseDate: { id: number }) => courseDate.id))
            .toEqual([2, 3])

        context.activeSemester = 3
        expect(computed.sortedCourseDates.call(context).map((courseDate: { id: number }) => courseDate.id))
            .toEqual([1, 2, 3])
    })

    it('keeps the course-date scroll signature stable when attendance changes', () => {
        const computed = (CourseTable as any).computed
        const methods = (CourseTable as any).methods
        const context = {
            selected_course: { id: 6 },
            sortedCourseDates: [
                { id: 232, date: '2026-07-10', attendance: {} },
                { id: 233, date: '2026-07-12', attendance: { 10: false } },
            ],
            dateKey: methods.dateKey,
            normalizeDateKey: methods.normalizeDateKey,
        }

        const initialSignature = computed.courseDateScrollSignature.call(context)
        context.sortedCourseDates = [
            { id: 232, date: '2026-07-10', attendance: {} },
            { id: 233, date: '2026-07-12', attendance: {} },
        ]

        expect(computed.courseDateScrollSignature.call(context)).toBe(initialSignature)

        context.sortedCourseDates.push({ id: 234, date: '2026-07-14', attendance: {} })

        expect(computed.courseDateScrollSignature.call(context)).not.toBe(initialSignature)
    })

    it('shows the Curriculum row only when the selected course has an assigned curriculum', () => {
        const computed = (CourseTable as any).computed

        expect(computed.assignedCurriculumId.call({
            selected_course: { teaching_curriculum_id: 3 },
        })).toBe(3)
        expect(computed.assignedCurriculumId.call({
            selected_course: { teaching_curriculum: { id: 3 } },
        })).toBe(3)
        expect(computed.assignedCurriculumId.call({
            selected_course: { teaching_curriculum_id: null, teaching_curriculum: null },
        })).toBeNull()
        expect(computed.hasAssignedCurriculum.call({ assignedCurriculumId: 3 })).toBe(true)
        expect(computed.hasAssignedCurriculum.call({ assignedCurriculumId: null })).toBe(false)
    })

    it('returns unique assigned curriculum content for each course date', () => {
        const methods = (CourseTable as any).methods

        expect(methods.curriculumContentForCourseDate({
            adopted_materials: [
                { title: 'Schreibübungen: Schreibübungen' },
                { title: 'Einleitung/Überblick: Einleitung/Überblick' },
                { title: 'Schreibübungen: Schreibübungen' },
                { title: 'Grundlagen: Operatoren: erweiterte Suche' },
                { title: '  ' },
                null,
            ],
        })).toEqual([
            'Schreibübungen',
            'Einleitung/Überblick',
            'Operatoren: erweiterte Suche',
        ])
        expect(methods.curriculumContentForCourseDate({ adopted_materials: null })).toEqual([])
    })

    it('preserves natural curriculum breaks while allowing long words to wrap', () => {
        const methods = (CourseTable as any).methods

        expect(methods.curriculumContentSegments('Einleitung/Überblick')).toEqual([
            { endsWithSeparator: true, text: 'Einleitung/' },
            { endsWithSeparator: false, text: 'Überblick' },
        ])
        expect(methods.curriculumContentSegments('Sehr lange Schreibübungen')).toEqual([
            { endsWithSeparator: false, text: 'Sehr' },
            { endsWithSeparator: false, text: 'lange' },
            { endsWithSeparator: false, text: 'Schreibübungen' },
        ])
    })

    it('limits displayed curriculum content to three item rows', () => {
        const methods = (CourseTable as any).methods
        const context = {
            curriculumContentForCourseDate: () => ['Eins', 'Zwei', 'Drei', 'Vier'],
        }

        expect(methods.displayedCurriculumContentForCourseDate.call(context, {})).toEqual(['Eins', 'Zwei', 'Drei'])
        expect(methods.hasAdditionalCurriculumContent.call(context, {})).toBe(true)
    })

    it('normalizes every curriculum theme and unit for the persistent dialog', () => {
        const computed = (CourseTable as any).computed

        expect(computed.curriculumDialogTopics.call({
            curriculumDialog: {
                curriculum: {
                    topics: [
                        {
                            id: 'topic-1',
                            title: 'Grundlagen',
                            materials: [{ id: 10, title: 'Themenmaterial' }],
                            units: [
                                {
                                    id: 'unit-1',
                                    title: 'Office 365',
                                    is_exam: false,
                                    materials: [
                                        { id: 10, title: 'Themenmaterial' },
                                        { id: 11, title: 'Einheitenmaterial' },
                                    ],
                                },
                                { id: 'unit-2', title: 'Prüfung', is_exam: true },
                            ],
                        },
                        { id: 'topic-2', title: 'Reserve', units: [] },
                    ],
                },
            },
        })).toEqual([
            {
                key: 'topic-1',
                title: 'Grundlagen',
                units: [
                    {
                        isExam: false,
                        key: 'unit-1',
                        materials: [
                            { id: 10, title: 'Themenmaterial' },
                            { id: 11, title: 'Einheitenmaterial' },
                        ],
                        title: 'Office 365',
                    },
                    {
                        isExam: true,
                        key: 'unit-2',
                        materials: [{ id: 10, title: 'Themenmaterial' }],
                        title: 'Prüfung',
                    },
                ],
            },
            { key: 'topic-2', title: 'Reserve', units: [] },
        ])
    })

    it('loads the assigned curriculum when a Curriculum cell opens its dialog', async () => {
        const methods = (CourseTable as any).methods
        const curriculum = { id: 3, title: 'DGB 1', topics: [] }
        const courseDate = {
            id: 318,
            date: '2026-09-14',
            adopted_materials: [{ title: 'Grundlagen: Office 365' }],
        }
        const show = vi.fn().mockResolvedValue(curriculum)
        const context = {
            assignedCurriculumId: 3,
            curriculumDialog: { courseDate: null, curriculum: null, loading: false, open: false },
            curriculumDialogRequestId: 0,
            curriculumStore: { show },
        }

        await methods.openCurriculumDialog.call(context, courseDate)

        expect(show).toHaveBeenCalledWith(3)
        expect(context.curriculumDialog).toEqual({
            courseDate,
            curriculum,
            loading: false,
            open: true,
        })
    })

    it('closes and clears the Curriculum dialog', () => {
        const methods = (CourseTable as any).methods
        const context = {
            curriculumDialog: { courseDate: { id: 318 }, curriculum: { id: 3 }, loading: true, open: true },
            curriculumDialogRequestId: 4,
        }

        methods.closeCurriculumDialog.call(context)

        expect(context.curriculumDialogRequestId).toBe(5)
        expect(context.curriculumDialog).toEqual({ courseDate: null, curriculum: null, loading: false, open: false })
    })

    it('marks only curriculum units linked with the clicked date', () => {
        const methods = (CourseTable as any).methods
        const context = {
            curriculumDialog: {
                courseDate: {
                    adopted_materials: [
                        { title: 'Grundlagen: Office 365' },
                        { title: 'Schreibübungen: Schreibübungen' },
                    ],
                },
            },
            curriculumUnitAdoptedMaterials: methods.curriculumUnitAdoptedMaterials,
            curriculumUnitTitle: methods.curriculumUnitTitle,
        }

        expect(methods.isCurriculumUnitLinkedToDialogDate.call(
            context,
            { title: 'Grundlagen' },
            { title: 'Office 365' },
        )).toBe(true)
        expect(methods.isCurriculumUnitLinkedToDialogDate.call(
            context,
            { title: 'Anderes Thema' },
            { title: 'Office 365' },
        )).toBe(false)
        expect(methods.isCurriculumUnitLinkedToDialogDate.call(
            context,
            { title: 'Grundlagen' },
            { title: 'E-Mails' },
        )).toBe(false)
    })

    it('links an unlinked curriculum unit with its material cards', async () => {
        const methods = (CourseTable as any).methods
        const updatedCourseDate = {
            id: 318,
            adopted_materials: [{ id: 7, title: 'Grundlagen: Office 365' }],
        }
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                adopted_materials: [{ id: 7 }],
                data: updatedCourseDate,
            },
        })
        const applyCurriculumDialogCourseDate = vi.fn()
        const context = {
            applyCurriculumDialogCourseDate,
            curriculumDialog: { courseDate: { id: 318, adopted_materials: [] } },
            curriculumDialogUnitActionKey: methods.curriculumDialogUnitActionKey,
            curriculumUnitActionKey: null,
            curriculumUnitTitle: methods.curriculumUnitTitle,
            isCurriculumUnitLinkedToDialogDate: () => false,
        }
        const topic = { key: 'topic-1', title: 'Grundlagen' }
        const unit = {
            key: 'unit-1',
            title: 'Office 365',
            materials: [{ id: 11 }, { id: 11 }, { id: 12 }],
        }

        await methods.linkCurriculumUnit.call(context, topic, unit)

        expect(axios.post).toHaveBeenCalledWith(
            '/api/admin/teaching/course_dates/318/adopt-curriculum-content',
            {
                content: 'Grundlagen: Office 365',
                material_card_ids: [11, 12],
            },
        )
        expect(applyCurriculumDialogCourseDate).toHaveBeenCalledWith(updatedCourseDate)
        expect(context.curriculumUnitActionKey).toBeNull()
    })

    it('unlinks every adopted material belonging to the selected curriculum unit', async () => {
        const methods = (CourseTable as any).methods
        const refreshedCourseDate = { id: 318, adopted_materials: [] }
        const index = vi.fn().mockResolvedValue(true)
        const applyCurriculumDialogCourseDate = vi.fn()
        vi.mocked(axios.delete).mockResolvedValue({ data: null })
        const context = {
            applyCurriculumDialogCourseDate,
            curriculumDialog: { courseDate: { id: 318, adopted_materials: [] } },
            curriculumDialogUnitActionKey: methods.curriculumDialogUnitActionKey,
            curriculumUnitActionKey: null,
            curriculumUnitAdoptedMaterials: () => [{ id: 7 }, { id: 8 }],
            courseDateStore: { courseDates: [refreshedCourseDate], index },
            selected_course: { id: 18 },
        }

        await methods.unlinkCurriculumUnit.call(
            context,
            { key: 'topic-1', title: 'Grundlagen' },
            { key: 'unit-1', title: 'Office 365' },
        )

        expect(axios.delete).toHaveBeenCalledTimes(2)
        expect(axios.delete).toHaveBeenNthCalledWith(1, '/api/admin/teaching/course_date_materials/7')
        expect(axios.delete).toHaveBeenNthCalledWith(2, '/api/admin/teaching/course_date_materials/8')
        expect(index).toHaveBeenCalledWith(18)
        expect(applyCurriculumDialogCourseDate).toHaveBeenCalledWith(refreshedCourseDate)
        expect(context.curriculumUnitActionKey).toBeNull()
    })

    it('sorts active students by selected student sort mode', () => {
        const methods = (CourseTable as any).methods
        const ctx: Record<string, unknown> = {
            selected_course: {
                students_info: [
                    { id: 1, last_name: 'Zeller', first_name: 'Max', schoolclass: '2A', canceled_at: null },
                    { id: 2, last_name: 'Berger', first_name: 'Anna', schoolclass: '1B', canceled_at: null },
                    { id: 3, last_name: 'Adler', first_name: 'Eva', schoolclass: '1A', canceled_at: '2026-04-01 10:00:00' },
                    { id: 4, last_name: 'Mayer', first_name: 'Lina', schoolclass: '1A', deleted_at: '2026-04-01 10:00:00' },
                ],
            },
            students_sort_mode: 'class_last_name',
            isStudentCanceled: methods.isStudentCanceled,
            compareStudentsBySelectedSort: methods.compareStudentsBySelectedSort,
            studentClassValue: methods.studentClassValue,
        }

        const rows = (CourseTable as any).computed.sortedSelectedStudents.call(ctx)

        expect(rows.map((row: { id: number }) => row.id)).toEqual([2, 1])
    })

    it('shows last name first and moves first name, sex sign, and class to the second row', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            normalizedStudentSex: methods.normalizedStudentSex,
        }
        const student = {
            last_name: 'Ahlgrimm',
            first_name: 'Paul',
            sex: 'm',
            schoolclass: '2B',
        }

        expect(methods.studentLastName.call(ctx, student)).toBe('Ahlgrimm')
        expect(methods.studentFirstName.call(ctx, student)).toBe('Paul')
        expect(methods.studentSexIcon.call(ctx, student)).toBe('mdi-gender-male')
        expect(methods.studentSexColor.call(ctx, student)).toBe('blue')
        expect(methods.studentClassValue.call(ctx, student)).toBe('2B')
    })

    it('shows the saved student comment as safe plain text', () => {
        const methods = (CourseTable as any).methods

        expect(methods.studentComment.call({}, {
            comment: '<p>Sehr aufmerksam</p><p>Benötigt mehr Übung.</p>',
        })).toBe('Sehr aufmerksam Benötigt mehr Übung.')
        expect(methods.studentComment.call({}, { comment: '<p><br></p>' })).toBe('')
        expect(methods.studentComment.call({}, { comment: null })).toBe('')
    })

    it('builds populated student details for the hover information panel', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            normalizedStudentSex: methods.normalizedStudentSex,
            studentClassValue: methods.studentClassValue,
            studentSexTitle: methods.studentSexTitle,
        }

        expect(methods.studentTooltipDetails.call(ctx, {
            schoolclass: '2B',
            sex: 'm',
            email: 'paul@example.test',
            phone: '0123 456789',
            sem_1_grade: '2',
            sem_2_grade: '',
            behaviour_grade: 'A',
        })).toEqual([
            { label: 'Klasse', value: '2B' },
            { label: 'Geschlecht', value: 'männlich' },
            { label: 'E-Mail', value: 'paul@example.test' },
            { label: 'Telefon', value: '0123 456789' },
            { label: '1. Semester', value: '2' },
            { label: 'Verhalten gesamt', value: 'A' },
        ])
        expect(methods.studentTooltipDetails.call(ctx, {
            email: 'placeholder@example.test',
            email_is_placeholder: true,
        })).toEqual([])
    })

    it('builds separate weekday and date labels for date headers', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            getWeekdayShort: methods.getWeekdayShort,
            formatDateShort: methods.formatDateShort,
        }
        const courseDate = { date: '2026-03-02' }

        expect(methods.courseDateWeekday.call(ctx, courseDate)).toBe('Mo')
        expect(methods.courseDateDateLabel.call(ctx, courseDate)).toBe('02.03.')
    })

    it('detects free and cancelled course dates for green non-instruction columns', () => {
        const methods = (CourseTable as any).methods

        expect(methods.isFreeCourseDate.call({}, { status: ['free'] })).toBe(true)
        expect(methods.isFreeCourseDate.call({}, { status: ['entfaellt'] })).toBe(true)
        expect(methods.isFreeCourseDate.call({}, { status: [] })).toBe(false)
        expect(methods.freeCourseDateReason.call({}, { free_reason: 'Herbstferien' })).toBe('Herbstferien')
        expect(methods.freeCourseDateReason.call({}, { free_reason: '' })).toBe('Frei')
        expect(methods.freeCourseDateReason.call({}, { status: ['entfaellt'] })).toBe('Entfällt')
    })

    it('uses the student free-date background across the complete date column', () => {
        const source = readFileSync(
            resolve(process.cwd(), 'resources/js/pages/admin/teaching/overview/components/CourseTable.vue'),
            'utf8',
        )

        expect(source).toContain('--course-table-free-cell-background: #e8f5e9;')
        expect(source.match(/background: var\(--course-table-free-cell-background\);/gu)).toHaveLength(4)
        expect(source).not.toContain('background: linear-gradient(180deg, #ecfdf3 0%, #dcfce7 100%);')
    })

    it('shows works at their corresponding dates including group-specific dates', () => {
        const methods = (CourseTable as any).methods
        const ctx: Record<string, any> = {
            sortedSelectedStudents: [{ id: 1 }, { id: 2 }, { id: 3 }],
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
            ],
        }
        Object.assign(ctx, methods)

        const assignments = methods.courseWorksForDate.call(ctx, { date: '2026-05-16' })

        expect(assignments.map((assignment: Record<string, any>) => assignment.label)).toEqual([
            'GA: Projekt (Gr. 1, 3)',
            'SA: Schularbeit (Einzelarbeit)',
        ])
        expect(assignments.map((assignment: Record<string, any>) => assignment.scope)).toEqual([
            'Gr. 1, 3',
            'Einzelarbeit',
        ])
        expect(assignments.map((assignment: Record<string, any>) => assignment.affectedStudentCount)).toEqual([4, 3])
        expect(methods.courseWorksForDate.call(ctx, { date: '2026-05-18' })).toEqual([])
    })

    it('moves a work to its later finish date and draws its timeline', () => {
        const methods = (CourseTable as any).methods
        const work = {
            id: 12,
            type: 'SA',
            title: 'Schularbeit',
            is_group_work: false,
            date_for_all_groups: '2026-05-16',
            finish_until_date: '2026-05-20',
            groups: [],
        }
        const ctx: Record<string, any> = {
            courseWorks: [work],
            sortedCourseDates: [
                { date: '2026-05-16' },
                { date: '2026-05-18' },
                { date: '2026-05-20' },
            ],
            sortedSelectedStudents: [{ id: 1 }, { id: 2 }],
        }
        Object.assign(ctx, methods)

        expect(methods.courseWorksForDate.call(ctx, { date: '2026-05-16' })).toEqual([])
        expect(methods.courseWorksForDate.call(ctx, { date: '2026-05-20' }))
            .toMatchObject([{ id: 12, affectedStudentCount: 2 }])
        expect(methods.courseWorkTimelinesForDate.call(ctx, { date: '2026-05-16' }))
            .toMatchObject([{ isStart: true, isMiddle: false, isArrow: false }])
        expect(methods.courseWorkTimelinesForDate.call(ctx, { date: '2026-05-18' }))
            .toMatchObject([{ isStart: false, isMiddle: false, isArrow: true }])
        expect(methods.courseWorkTimelinesForDate.call(ctx, { date: '2026-05-20' }))
            .toEqual([])
    })

    it('keeps timeline markers in the vertical lane of their destination card', () => {
        const computed = (CourseTable as any).computed
        const methods = (CourseTable as any).methods
        const project = {
            id: 15,
            date_for_all_groups: '2026-10-05',
            finish_until_date: '2026-10-12',
            groups: [],
            is_group_work: true,
            title: 'Projekt Zusammenarbeit',
            type: 'A',
        }
        const writingExercise = {
            id: 16,
            date_for_all_groups: '2026-09-21',
            finish_until_date: '2026-10-12',
            groups: [],
            is_group_work: false,
            title: 'Schreibübungen bis Lektion 40',
            type: 'TW',
        }
        const ctx: Record<string, any> = {
            courseWorks: [project, writingExercise],
            sortedCourseDates: [
                { date: '2026-09-21' },
                { date: '2026-10-05' },
                { date: '2026-10-12' },
            ],
            sortedSelectedStudents: [],
        }
        Object.assign(ctx, methods)
        ctx.courseWorkTimelineDestinationIndexes = computed.courseWorkTimelineDestinationIndexes.call(ctx)

        expect([...ctx.courseWorkTimelineDestinationIndexes.entries()]).toEqual([
            ['15', 0],
            ['16', 1],
        ])
        expect(methods.courseWorkTimelinesForDate.call(ctx, { date: '2026-09-21' }))
            .toMatchObject([{ destinationIndex: 1, isStart: true, work: { id: 16 } }])
        expect(methods.courseWorkTimelinesForDate.call(ctx, { date: '2026-10-05' }))
            .toMatchObject([
                { destinationIndex: 0, isStart: true, work: { id: 15 } },
                { destinationIndex: 1, isArrow: true, work: { id: 16 } },
            ])
    })

    it('shows a work timeline only while its start or finish cell is active', () => {
        const methods = (CourseTable as any).methods
        const ctx: Record<string, any> = {
            courseWorks: [{
                id: 12,
                date_for_all_groups: '2026-05-16',
                finish_until_date: '2026-05-20',
            }],
            hoveredCourseWorkTimelineKeys: [],
            sortedCourseDates: [
                { date: '2026-05-16' },
                { date: '2026-05-18' },
                { date: '2026-05-20' },
            ],
        }
        Object.assign(ctx, methods)

        methods.activateCourseWorkTimelinesForDate.call(ctx, { date: '2026-05-16' })
        expect(ctx.hoveredCourseWorkTimelineKeys).toEqual(['work-timeline-12'])
        expect(methods.isCourseWorkTimelineVisible.call(ctx, { key: 'work-timeline-12' })).toBe(true)

        methods.activateCourseWorkTimelinesForDate.call(ctx, { date: '2026-05-18' })
        expect(ctx.hoveredCourseWorkTimelineKeys).toEqual([])

        methods.activateCourseWorkTimelinesForDate.call(ctx, { date: '2026-05-20' })
        expect(ctx.hoveredCourseWorkTimelineKeys).toEqual(['work-timeline-12'])

        methods.clearHoveredCourseWorkTimelines.call(ctx)
        expect(ctx.hoveredCourseWorkTimelineKeys).toEqual([])
    })

    it('keeps a work at its start when its later finish date has no table cell', () => {
        const methods = (CourseTable as any).methods
        const ctx: Record<string, any> = {
            courseWorks: [{
                id: 12,
                type: 'SA',
                is_group_work: false,
                date_for_all_groups: '2026-05-16',
                finish_until_date: '2026-05-19',
                groups: [],
            }],
            sortedCourseDates: [
                { date: '2026-05-16' },
                { date: '2026-05-20' },
            ],
            sortedSelectedStudents: [],
        }
        Object.assign(ctx, methods)

        expect(methods.courseWorksForDate.call(ctx, { date: '2026-05-16' })).toHaveLength(1)
        expect(methods.courseWorkTimelinesForDate.call(ctx, { date: '2026-05-16' })).toEqual([])
    })

    it('renders the arrow one date cell before the finish work', () => {
        const source = readFileSync(
            resolve(process.cwd(), 'resources/js/pages/admin/teaching/overview/components/CourseTable.vue'),
            'utf8',
        )

        expect(source).toContain('class="course-table-work-timelines"')
        expect(source).toContain('class="course-table-work-timeline-card"')
        expect(source).toContain(':style="{ gridRow: timeline.destinationIndex + 1 }"')
        expect(source).toContain("'course-table-work-timeline-card--group': timeline.work.is_group_work")
        expect(source).not.toContain('class="course-table-work-timeline-dot"')
        expect(source).toContain('class="course-table-work-timeline-line"')
        expect(source).toContain('class="course-table-work-timeline-arrow"')
        expect(source).toContain("'course-table-work-timeline--arrow': timeline.isArrow")
        expect(source).toContain("'course-table-work-timeline--visible': isCourseWorkTimelineVisible(timeline)")
        expect(source).toContain('@mouseenter="activateCourseWorkTimelinesForDate(courseDate)"')
        expect(source).toContain('@mouseleave="clearHoveredCourseWorkTimelines"')
        expect(source).toContain('height: 2px;')
        expect(source).toContain('opacity: 0;')
        expect(source).toContain('.course-table-work-timeline--visible .course-table-work-timeline-line,')
        expect(source).toContain('.course-table-work-timeline--visible .course-table-work-timeline-arrow')
        const markerStylesStart = source.indexOf('.course-table-work-timeline-card {')
        const markerStylesEnd = source.indexOf('}', markerStylesStart)

        expect(markerStylesStart).toBeGreaterThan(-1)
        expect(source.slice(markerStylesStart, markerStylesEnd)).toContain('width: 12px;')
        expect(source.slice(markerStylesStart, markerStylesEnd)).not.toContain('opacity:')
        expect(source).toContain('.course-table-work-timeline--start .course-table-work-timeline-line')
        expect(source).not.toContain('.course-table-work-timeline--finish .course-table-work-timeline-line')
    })

    it('aligns the timeline arrow with the work entry height', () => {
        const source = readFileSync(
            resolve(process.cwd(), 'resources/js/pages/admin/teaching/overview/components/CourseTable.vue'),
            'utf8',
        )

        expect(source).toContain('.course-table-work-timelines {')
        const timelineStylesStart = source.indexOf('.course-table-work-timelines {')
        const timelineStylesEnd = source.indexOf('}', timelineStylesStart)

        expect(source.slice(timelineStylesStart, timelineStylesEnd)).toContain('top: 30px;')
        expect(source.slice(timelineStylesStart, timelineStylesEnd)).not.toContain('bottom:')
        expect(source).toContain('position: absolute;')
        expect(source).toContain('left: 50%;')
        expect(source).toContain('translate: -50% 0;')
        expect(source).toContain('--course-table-work-card-height: 66px;')
        expect(source).toMatch(/\.course-table-work-timelines \{[\s\S]*display: grid;[\s\S]*grid-auto-rows: var\(--course-table-work-card-height\);[\s\S]*row-gap: 3px;/u)
        expect(source).toMatch(/\.course-table-work-timeline \{[\s\S]*height: var\(--course-table-work-card-height\);/u)
        expect(source).toMatch(/\.course-table-work-summary \{[\s\S]*height: var\(--course-table-work-card-height\);/u)
    })

    it('keeps the add-work icon before existing works', () => {
        const source = readFileSync(
            resolve(process.cwd(), 'resources/js/pages/admin/teaching/overview/components/CourseTable.vue'),
            'utf8',
        )
        const workListStart = source.indexOf('class="course-table-work-list"')
        const addIconPosition = source.indexOf('class="course-table-work-empty-icon"', workListStart)
        const existingWorksPosition = source.indexOf('v-for="work in courseWorksForDate(courseDate)"', workListStart)

        expect(workListStart).toBeGreaterThan(-1)
        expect(addIconPosition).toBeGreaterThan(workListStart)
        expect(existingWorksPosition).toBeGreaterThan(addIconPosition)
        expect(source.slice(workListStart, addIconPosition)).not.toContain('v-if="!courseWorksForDate(courseDate).length"')
        expect(source).toContain('@click.stop="openNewWorkDialog(courseDate)"')
        expect(source).toContain("'course-table-work-list--has-work': courseWorksForDate(courseDate).length > 0")
        expect(source).toContain('.course-table-work-list--has-work {')
        expect(source).toContain('padding-top: 30px;')
    })

    it('labels work assigned to all groups as group work', () => {
        const methods = (CourseTable as any).methods
        const ctx: Record<string, any> = {}
        Object.assign(ctx, methods)

        const assignment = methods.groupWorkAssignmentForDate.call(ctx, {
            id: 3,
            type: 'TW',
            title: 'Schreibübungen',
            is_group_work: true,
            date_for_all_groups: '2026-05-16',
            groups: [
                { date: '2026-05-16', student_ids: [1, 2] },
                { date: '2026-05-16', student_ids: [3, 4] },
            ],
        }, '2026-05-16')

        expect(assignment.label).toBe('TW: Schreibübungen (Gruppenarbeit)')
        expect(assignment.scope).toBe('Gruppenarbeit')
        expect(assignment.affectedStudentCount).toBe(4)
    })

    it('marks a complete date column with the configured work type color', () => {
        const methods = (CourseTable as any).methods
        const context = {
            tableView: 'entries',
            uses_entry_areas_for_grading_schema: true,
            selected_course: {
                teaching_entry_area: {
                    entry_definitions: [
                        {
                            short_name: 'PÜ',
                            category: 'Benotung',
                            has_table_marking: true,
                            table_marking_color: 'red',
                        },
                        {
                            short_name: 'TW',
                            category: 'Benotung',
                            has_table_marking: false,
                            table_marking_color: null,
                        },
                    ],
                },
            },
            courseWorksForDate: vi.fn().mockReturnValue([{ work: { type: 'PÜ' } }]),
            courseDateColumnMarkingColor: methods.courseDateColumnMarkingColor,
        }

        expect(methods.courseDateColumnMarkingColor.call(context, { date: '2026-10-12' })).toBe('red')
        expect(methods.courseDateColumnMarkingClass.call(context, { date: '2026-10-12' }))
            .toBe('course-table-column--marked-red')

        context.courseWorksForDate.mockReturnValue([{ work: { type: 'TW' } }])
        expect(methods.courseDateColumnMarkingColor.call(context, { date: '2026-09-21' })).toBeNull()

        context.tableView = 'attendance'
        context.courseWorksForDate.mockReturnValue([{ work: { type: 'PÜ' } }])
        expect(methods.courseDateColumnMarkingColor.call(context, { date: '2026-10-12' })).toBe('red')
        expect(methods.courseDateColumnMarkingClass.call(context, { date: '2026-10-12' }))
            .toBe('course-table-column--marked-red')
    })

    it('opens the persistent work dialog for the selected date', () => {
        const methods = (CourseTable as any).methods
        const courseDate = { id: 7, date: '2026-05-16' }
        const ctx = {
            workDialog: {
                courseDate: null,
                open: false,
            },
            cancelDateWorkForm: vi.fn(),
            courseWorksForDate: vi.fn().mockReturnValue([{ id: 12 }]),
            startCreatingDateWork: vi.fn(),
        }

        methods.openWorkDialog.call(ctx, courseDate)

        expect(ctx.cancelDateWorkForm).toHaveBeenCalledTimes(1)
        expect(ctx.workDialog).toEqual({ courseDate, open: true })
        expect(ctx.startCreatingDateWork).not.toHaveBeenCalled()
    })

    it('opens an empty date directly in the new-work form', () => {
        const methods = (CourseTable as any).methods
        const courseDate = { id: 7, date: '2026-05-16' }
        const ctx = {
            workDialog: {
                courseDate: null,
                open: false,
            },
            cancelDateWorkForm: vi.fn(),
            courseWorksForDate: vi.fn().mockReturnValue([]),
            startCreatingDateWork: vi.fn(),
        }

        methods.openWorkDialog.call(ctx, courseDate)

        expect(ctx.workDialog).toEqual({ courseDate, open: true })
        expect(ctx.startCreatingDateWork).toHaveBeenCalledTimes(1)
    })

    it('opens the source work from a student cell entry', () => {
        const methods = (CourseTable as any).methods
        const entry = { teaching_course_work_id: 12, uid: 'course-work-12' }
        const work = { id: 12, title: 'Testarbeit' }
        const courseDate = { id: 7, date: '2026-05-16' }
        const ctx = {
            courseWorkEntrySavingUid: null,
            entryDialog: { courseDate, open: true, student: { id: 22 } },
            courseWorkForCellEntry: vi.fn().mockReturnValue(work),
            closeEntryDialog: vi.fn(),
            openWorkDialog: vi.fn(),
            startEditingDateWork: vi.fn(),
        }

        methods.openCourseWorkFromCellEntry.call(ctx, entry)

        expect(ctx.closeEntryDialog).toHaveBeenCalledTimes(1)
        expect(ctx.openWorkDialog).toHaveBeenCalledWith(courseDate)
        expect(ctx.startEditingDateWork).toHaveBeenCalledWith(work)
    })

    it('opens the new-work form directly when the add icon is used', () => {
        const methods = (CourseTable as any).methods
        const courseDate = { id: 7, date: '2026-05-16' }
        const ctx = {
            workDialog: {
                courseDate: null,
                open: false,
            },
            cancelDateWorkForm: vi.fn(),
            startCreatingDateWork: vi.fn(),
        }

        methods.openNewWorkDialog.call(ctx, courseDate)

        expect(ctx.cancelDateWorkForm).toHaveBeenCalledTimes(1)
        expect(ctx.workDialog).toEqual({ courseDate, open: true })
        expect(ctx.startCreatingDateWork).toHaveBeenCalledTimes(1)
    })

    it('opens the content editor with the selected course date content and cancels it', () => {
        const methods = (CourseTable as any).methods
        const courseDate = { id: 7, date: '2026-05-16', content: '<p>Bruchrechnen</p>' }
        const ctx = {
            contentDialog: {
                content: '',
                courseDate: null,
                open: false,
            },
            contentSaving: false,
            courseDateScrollKey: methods.courseDateScrollKey,
        }

        methods.openContentDialog.call(ctx, courseDate)

        expect(ctx.contentDialog).toEqual({ content: '<p>Bruchrechnen</p>', courseDate, open: true })
        expect(methods.isContentDialogCellSelected.call(ctx, courseDate)).toBe(true)

        methods.closeContentDialog.call(ctx)

        expect(ctx.contentDialog).toEqual({ content: '', courseDate: null, open: false })
    })

    it('moves the content editor between dates and saves changed content first', async () => {
        const methods = (CourseTable as any).methods
        const previousCourseDate = { id: 7, date: '2026-05-09', content: '<p>Vorheriger</p>' }
        const currentCourseDate = { id: 8, date: '2026-05-16', content: '<p>Gespeichert</p>' }
        const nextCourseDate = { id: 9, date: '2026-05-23', content: '<p>Nächster</p>' }
        const ctx: Record<string, any> = {
            contentDialog: {
                content: '<p>Geändert</p>',
                courseDate: currentCourseDate,
                open: true,
            },
            contentDialogCourseDateIndex: 1,
            contentSaving: false,
            openContentDialog: methods.openContentDialog,
            saveContentDialog: vi.fn().mockResolvedValue(true),
            sortedCourseDates: [previousCourseDate, currentCourseDate, nextCourseDate],
        }

        await methods.navigateContentDialogDate.call(ctx, 1)

        expect(ctx.saveContentDialog).toHaveBeenCalledWith(false)
        expect(ctx.contentDialog).toEqual({
            content: '<p>Nächster</p>',
            courseDate: nextCourseDate,
            open: true,
        })

        ctx.contentDialogCourseDateIndex = 2
        await methods.navigateContentDialogDate.call(ctx, -1)

        expect(ctx.saveContentDialog).toHaveBeenCalledTimes(1)
        expect(ctx.contentDialog.courseDate).toBe(currentCourseDate)
    })

    it('detects the previous and next content-editor dates', () => {
        const computed = (CourseTable as any).computed
        const methods = (CourseTable as any).methods
        const courseDates = [
            { id: 7, date: '2026-05-09' },
            { id: 8, date: '2026-05-16' },
            { id: 9, date: '2026-05-23' },
        ]
        const ctx: Record<string, any> = {
            contentDialog: { courseDate: courseDates[1], open: true },
            courseDateScrollKey: methods.courseDateScrollKey,
            sortedCourseDates: courseDates,
        }

        ctx.contentDialogCourseDateIndex = computed.contentDialogCourseDateIndex.call(ctx)

        expect(ctx.contentDialogCourseDateIndex).toBe(1)
        expect(computed.hasPreviousContentDialogDate.call(ctx)).toBe(true)
        expect(computed.hasNextContentDialogDate.call(ctx)).toBe(true)

        ctx.contentDialogCourseDateIndex = 0
        expect(computed.hasPreviousContentDialogDate.call(ctx)).toBe(false)

        ctx.contentDialogCourseDateIndex = 2
        expect(computed.hasNextContentDialogDate.call(ctx)).toBe(false)
    })

    it('copies the linked curriculum content into the Stoff draft', () => {
        const computed = (CourseTable as any).computed
        const methods = (CourseTable as any).methods
        const courseDate = {
            adopted_materials: [
                { title: 'Brüche: Addieren & Subtrahieren' },
                { title: 'Geometrie: Winkel < 90°' },
            ],
        }
        const curriculumContext = {
            contentDialog: { courseDate },
            curriculumContentForCourseDate: methods.curriculumContentForCourseDate,
        }
        const curriculumContent = computed.contentDialogCurriculumContent.call(curriculumContext)
        const ctx: Record<string, any> = {
            contentDialog: {
                content: '<p>Vorhandener Stoff</p>',
                courseDate,
                open: true,
            },
            contentDialogCurriculumContent: curriculumContent,
            contentSaving: false,
            escapeCourseContentText: methods.escapeCourseContentText,
        }

        methods.applyCurriculumContentToContentDialog.call(ctx)

        expect(curriculumContent).toEqual(['Addieren & Subtrahieren', 'Winkel < 90°'])
        expect(ctx.contentDialog.content).toBe(
            '<p>Vorhandener Stoff</p><p>Addieren &amp; Subtrahieren</p><p>Winkel &lt; 90°</p>',
        )
    })

    it('creates a safe plain-text preview from rich Termin content', () => {
        const courseDateContentPreview = (CourseTable as any).methods.courseDateContentPreview

        expect(courseDateContentPreview({
            content: '<p>Erste Zeile &amp; Text</p><p>Zweite <strong>Zeile</strong></p>',
        })).toBe('Erste Zeile & Text Zweite Zeile')
        expect(courseDateContentPreview({ content: null })).toBe('')
    })

    it('preserves supported rich-text formatting and removes unsafe tooltip markup', () => {
        const courseDateFormattedContent = (CourseTable as any).methods.courseDateFormattedContent

        expect(courseDateFormattedContent({
            content: '<h2>Übersicht</h2><p><strong>Fett</strong> und <u>unterstrichen</u></p>'
                + '<script>alert(1)</script><span onclick="alert(2)"><em>Kursiv</em></span>',
        })).toBe('<h2>Übersicht</h2><p><strong>Fett</strong> und <u>unterstrichen</u></p><em>Kursiv</em>')
    })

    it('saves edited course date content and refreshes the selected course', async () => {
        const methods = (CourseTable as any).methods
        const courseDate = { id: 7, date: '2026-05-16', content: null }
        const ctx = {
            contentDialog: {
                content: '<p>Bruchrechnen</p>',
                courseDate,
                open: true,
            },
            contentSaving: false,
            courseDateStore: {
                update: vi.fn().mockResolvedValue({ data: { ...courseDate, content: '<p>Bruchrechnen</p>' } }),
            },
            courseStore: {
                index: vi.fn().mockResolvedValue(true),
            },
            closeContentDialog: vi.fn(),
        }

        expect(await methods.saveContentDialog.call(ctx)).toBe(true)

        expect(ctx.courseDateStore.update).toHaveBeenCalledWith({
            id: 7,
            date: '2026-05-16',
            content: '<p>Bruchrechnen</p>',
        })
        expect(ctx.courseStore.index).toHaveBeenCalledTimes(1)
        expect(ctx.closeContentDialog).toHaveBeenCalledTimes(1)
        expect(ctx.contentSaving).toBe(false)
    })

    it('changes the work date from the dialog header', () => {
        const methods = (CourseTable as any).methods
        const matchingCourseDate = { id: 8, date: '2026-05-23' }
        const ctx: Record<string, any> = {
            sortedCourseDates: [
                { id: 7, date: '2026-05-16' },
                matchingCourseDate,
            ],
            workDialog: {
                courseDate: { id: 7, date: '2026-05-16' },
                open: true,
            },
            workDialogDateEditing: true,
            workDialogForm: {
                date_for_all_groups: '2026-05-16',
                groups: [
                    { date: '2026-05-16', student_ids: [1, 2] },
                    { date: '2026-05-17', student_ids: [3, 4] },
                ],
            },
            dateKey: methods.dateKey,
            normalizeDateKey: methods.normalizeDateKey,
        }

        methods.applyWorkDialogDate.call(ctx, new Date(2026, 4, 23))

        expect(ctx.workDialogForm.date_for_all_groups).toBe('2026-05-23')
        expect(ctx.workDialogForm.groups.map((group: { date: string }) => group.date)).toEqual([
            '2026-05-23',
            '2026-05-23',
        ])
        expect(ctx.workDialog.courseDate).toBe(matchingCourseDate)
        expect(ctx.workDialogDateEditing).toBe(false)
    })

    it('opens date editing when the editable date chip is activated', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            workDialogDateEditing: false,
            workDialogFinishDateEditing: true,
            workDialogFormOpen: true,
            workSaving: false,
        }

        methods.beginWorkDialogDateEditing.call(ctx)

        expect(ctx.workDialogDateEditing).toBe(true)
        expect(ctx.workDialogFinishDateEditing).toBe(false)
    })

    it('opens finish-date editing when its chip is activated', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            workDialogDateEditing: true,
            workDialogFinishDateEditing: false,
            workDialogFormOpen: true,
            workSaving: false,
        }

        methods.beginWorkDialogFinishDateEditing.call(ctx)

        expect(ctx.workDialogDateEditing).toBe(false)
        expect(ctx.workDialogFinishDateEditing).toBe(true)
    })

    it('applies and closes finish-date editing', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            dateKey: methods.dateKey,
            normalizeDateKey: methods.normalizeDateKey,
            workDialogFinishDateEditing: true,
            workDialogForm: {
                finish_until_date: '2026-05-16',
            },
        }

        methods.applyWorkDialogFinishDate.call(ctx, new Date(2026, 4, 23))

        expect(ctx.workDialogForm.finish_until_date).toBe('2026-05-23')
        expect(ctx.workDialogFinishDateEditing).toBe(false)
    })

    it('sets the finish date to null when the date input is cleared', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            dateKey: methods.dateKey,
            normalizeDateKey: methods.normalizeDateKey,
            workDialogFinishDateEditing: true,
            workDialogForm: {
                finish_until_date: '2026-10-12',
            },
        }

        methods.applyWorkDialogFinishDate.call(ctx, null)

        expect(ctx.workDialogForm.finish_until_date).toBeNull()
        expect(ctx.workDialogFinishDateEditing).toBe(false)
        expect(methods.normalizeDateKey.call(ctx, null)).toBe('')
    })

    it('calculates and labels the calendar days between work dates', () => {
        const methods = (CourseTable as any).methods
        const computed = (CourseTable as any).computed
        const dateContext = {
            dateKey: methods.dateKey,
            normalizeDateKey: methods.normalizeDateKey,
        }
        const durationContext = {
            workDialogFormOpen: true,
            workDialogForm: {
                date_for_all_groups: '2026-03-28',
                finish_until_date: '2026-03-30',
            },
            calendarDaysBetween: (startDate: string, finishDate: string) => (
                methods.calendarDaysBetween.call(dateContext, startDate, finishDate)
            ),
        }

        expect(methods.calendarDaysBetween.call(dateContext, '2026-03-28', '2026-03-30')).toBe(2)
        expect(computed.workDialogDurationDays.call(durationContext)).toBe(2)
        expect(computed.workDialogDurationLabel.call({ workDialogDurationDays: 0 })).toBe('0 Tage')
        expect(computed.workDialogDurationLabel.call({ workDialogDurationDays: 1 })).toBe('1 Tag')
        expect(computed.workDialogDurationLabel.call({ workDialogDurationDays: 2 })).toBe('2 Tage')

        const workContext = {
            calendarDaysBetween: (startDate: string, finishDate: string) => (
                methods.calendarDaysBetween.call(dateContext, startDate, finishDate)
            ),
        }

        expect(methods.courseWorkDurationLabel.call(workContext, {
            date_for_all_groups: '2026-03-28',
            finish_until_date: '2026-04-04',
        })).toBe('7 Tage')
    })

    it('edits only the selected work-group date', () => {
        const methods = (CourseTable as any).methods
        const ctx: Record<string, any> = {
            dateKey: methods.dateKey,
            normalizeDateKey: methods.normalizeDateKey,
            workDialogGroupDateEditingIndex: null,
            workDialogGroupDateMenuOpen: false,
            workDialogGroups: [
                { date: '2026-05-16', student_ids: [1, 2] },
                { date: '2026-05-17', student_ids: [3, 4] },
            ],
            workSaving: false,
        }

        methods.beginWorkDialogGroupDateEditing.call(ctx, 1)
        expect(ctx.workDialogGroupDateEditingIndex).toBe(1)
        expect(ctx.workDialogGroupDateMenuOpen).toBe(true)

        methods.applyWorkDialogGroupDate.call(ctx, 1, new Date(2026, 4, 23))
        expect(ctx.workDialogGroups.map((group: { date: string }) => group.date)).toEqual([
            '2026-05-16',
            '2026-05-23',
        ])
        expect(ctx.workDialogGroupDateEditingIndex).toBeNull()
        expect(ctx.workDialogGroupDateMenuOpen).toBe(false)
    })

    it('closes group-date editing when its calendar is dismissed', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            workDialogGroupDateEditingIndex: 1,
            workDialogGroupDateMenuOpen: true,
        }

        methods.handleWorkDialogGroupDateMenu.call(ctx, false)

        expect(ctx.workDialogGroupDateEditingIndex).toBeNull()
        expect(ctx.workDialogGroupDateMenuOpen).toBe(false)
    })

    it('identifies course dates for highlighting in the work date picker', () => {
        const methods = (CourseTable as any).methods
        const workDialogCourseDateKeys = (CourseTable as any).computed.workDialogCourseDateKeys

        expect(workDialogCourseDateKeys.call({
            sortedCourseDates: [
                { date: '2026-05-16' },
                { date: '2026-05-23T00:00:00.000000Z' },
                { date: null },
            ],
            dateKey: methods.dateKey,
            normalizeDateKey: methods.normalizeDateKey,
        })).toEqual(['2026-05-16', '2026-05-23'])
    })

    it('uses grading entries from the area assigned to the course as work types', () => {
        const availableWorkTypes = (CourseTable as any).computed.availableWorkTypes
        const items = availableWorkTypes.call({
            selected_course: {
                teaching_entry_area: {
                    id: 15,
                    entry_definitions: [
                        { short_name: 'A', name: 'Auftrag', category: 'Benotung' },
                        { short_name: 'P', name: 'Prüfung', category: 'Benotung' },
                        { short_name: 'E', name: 'Ermahnung', category: 'Verhalten' },
                    ],
                },
            },
            selectedTeachingSchema: {
                works: [{ short_name: 'SA', name: 'Schularbeit' }],
            },
            uses_entry_areas_for_grading_schema: true,
        })

        expect(items).toEqual([
            { title: 'A - Auftrag', value: 'A' },
            { title: 'P - Prüfung', value: 'P' },
        ])
    })

    it('keeps assessment badge text neutral when the work type marks the column background', () => {
        const cellEntryColor = (CourseTable as any).methods.cellEntryColor

        expect(cellEntryColor.call({}, { kind: 'assessment', type: 'MA' })).toBeNull()
        expect(cellEntryColor.call({}, { kind: 'assessment', type: 'A' })).toBeNull()
        expect(cellEntryColor.call({}, { kind: 'assessment', type: 'X' })).toBeNull()
        expect(cellEntryColor.call({}, { kind: 'notification', type: 'MA' })).toBe('secondary')
    })

    it('keeps legacy schema work types before school year 2026/27', () => {
        const availableWorkTypes = (CourseTable as any).computed.availableWorkTypes
        const items = availableWorkTypes.call({
            selected_course: {
                teaching_entry_area: {
                    id: 15,
                    entry_definitions: [{ short_name: 'A', name: 'Auftrag', category: 'Benotung' }],
                },
            },
            selectedTeachingSchema: {
                works: [{ short_name: 'SA', name: 'Schularbeit' }],
            },
            uses_entry_areas_for_grading_schema: false,
        })

        expect(items).toEqual([{ title: 'SA - Schularbeit', value: 'SA' }])
    })

    it('uses the assigned entry area for manual assessment types and grades', () => {
        const methods = (CourseTable as any).methods
        const availableEntryTypes = (CourseTable as any).computed.availableEntryTypes
        const availableEntryTypeGroups = (CourseTable as any).computed.availableEntryTypeGroups
        const availableEntryGrades = (CourseTable as any).computed.availableEntryGrades
        const context: any = {
            availableWorkTypes: [{ title: 'TW - Tageswiederholung', value: 'TW' }],
            courseWorkGradeConfigurationForType: methods.courseWorkGradeConfigurationForType,
            entryForm: { kind: 'assessment', type: 'TW' },
            selectedEntryTypeCategory: 'Benotung',
            selected_course: {
                teaching_entry_area: {
                    id: 15,
                    entry_definitions: [
                        {
                            short_name: 'TW',
                            name: 'Tageswiederholung',
                            category: 'Benotung',
                            has_properties: true,
                            properties_mode: 'fixed',
                            fixed_properties: ['+', '0', 'F'],
                        },
                        { short_name: 'V', name: 'Verwarnung', category: 'Verhalten' },
                        { short_name: 'M', name: 'Mitteilung', category: 'Weitere' },
                    ],
                },
            },
            selectedTeachingSchema: {
                works: [
                    { short_name: 'MA', name: 'Mitarbeit', grades: [{ grade: '+' }] },
                    { short_name: 'SA', name: 'Schularbeit', grades: [{ grade: '1' }] },
                ],
            },
            uses_entry_areas_for_grading_schema: true,
        }

        context.availableEntryTypeGroups = availableEntryTypeGroups.call(context)

        expect(context.availableEntryTypeGroups).toEqual([
            {
                category: 'Benotung',
                items: [{ title: 'TW - Tageswiederholung', value: 'TW' }],
            },
            {
                category: 'Verhalten',
                items: [{ title: 'V - Verwarnung', value: 'V' }],
            },
            {
                category: 'Weitere',
                items: [{ title: 'M - Mitteilung', value: 'M' }],
            },
        ])
        expect(availableEntryTypes.call(context)).toEqual([
            { title: 'TW - Tageswiederholung', value: 'TW' },
            { title: 'V - Verwarnung', value: 'V' },
            { title: 'M - Mitteilung', value: 'M' },
        ])
        expect(availableEntryGrades.call(context)).toEqual([
            { title: '+', value: '+' },
            { title: '0', value: '0' },
            { title: 'F', value: 'F' },
        ])
    })

    it('styles entry type categories as separate color-coded cards', () => {
        const methods = (CourseTable as any).methods
        const source = readFileSync(
            resolve(process.cwd(), 'resources/js/pages/admin/teaching/overview/components/CourseTable.vue'),
            'utf8',
        )

        expect(methods.entryTypeCategoryColor('Benotung')).toBe('primary')
        expect(methods.entryTypeCategoryColor('Verhalten')).toBe('warning')
        expect(methods.entryTypeCategoryColor('Weitere')).toBe('error')
        expect(source).toContain(':data-category="group.category"')
        expect(source).toContain(':color="entryTypeCategoryColor(group.category)"')
        expect(source).toContain(".course-table-entry-type-row[data-category='Benotung']")
        expect(source).toContain(".course-table-entry-type-row[data-category='Verhalten']")
        expect(source).toContain(".course-table-entry-type-row[data-category='Weitere']")
        expect(source).toContain('background: rgba(var(--v-theme-error), 0.065);')
        expect(source).toContain('border-color: rgba(var(--v-theme-error), 0.3);\n    color: rgb(var(--v-theme-error));')
        expect(source).toContain('color: rgb(var(--v-theme-error));')
        expect(source).toContain('border-left-width: 4px;')
        expect(source).toContain('border-radius: 10px;')
    })

    it('shows only the selected entry type category until the type is deselected', () => {
        const visibleEntryTypeGroups = (CourseTable as any).computed.visibleEntryTypeGroups
        const groups = [
            { category: 'Benotung', items: [{ title: 'MA', value: 'MA' }] },
            { category: 'Verhalten', items: [{ title: 'V', value: 'V' }] },
            { category: 'Weitere', items: [{ title: 'M', value: 'M' }] },
        ]
        const context: any = {
            availableEntryTypeGroups: groups,
            entryForm: { type: 'MA' },
            selectedEntryTypeCategory: 'Benotung',
        }

        expect(visibleEntryTypeGroups.call(context)).toEqual([groups[0]])

        context.entryForm.type = ''
        context.selectedEntryTypeCategory = null

        expect(visibleEntryTypeGroups.call(context)).toEqual(groups)
    })

    it('offers the configured grades for the selected work type', () => {
        const methods = (CourseTable as any).methods
        const availableWorkGradeItems = (CourseTable as any).computed.availableWorkGradeItems
        const items = availableWorkGradeItems.call({
            courseWorkGradeConfigurationForType: methods.courseWorkGradeConfigurationForType,
            selected_course: {},
            selectedTeachingSchema: {
                works: [{
                    short_name: 'SA',
                    grades: [
                        { grade: '1', name: 'Sehr gut' },
                        { grade: '2', name: 'Gut' },
                    ],
                }],
            },
            uses_entry_areas_for_grading_schema: false,
            workDialogForm: { type: 'SA' },
        })

        expect(items).toEqual([
            { title: '1 (Sehr gut)', value: '1' },
            { title: '2 (Gut)', value: '2' },
        ])
    })

    it('uses fixed, free, and disabled grading properties from the assigned entry area', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            selected_course: {
                teaching_entry_area: {
                    id: 15,
                    entry_definitions: [
                        {
                            short_name: 'TW',
                            category: 'Benotung',
                            has_properties: true,
                            properties_mode: 'fixed',
                            fixed_properties: ['++++', '++', '+'],
                        },
                        {
                            short_name: 'PR',
                            category: 'Benotung',
                            has_properties: true,
                            properties_mode: 'free',
                            fixed_properties: [],
                        },
                        {
                            short_name: 'KO',
                            category: 'Benotung',
                            has_properties: false,
                            properties_mode: 'free',
                            fixed_properties: [],
                        },
                    ],
                },
            },
            selectedTeachingSchema: {
                works: [{ short_name: 'TW', grades: [{ grade: 'legacy' }] }],
            },
            uses_entry_areas_for_grading_schema: true,
        }

        expect(methods.courseWorkGradeConfigurationForType.call(ctx, 'TW')).toEqual({
            items: [
                { title: '++++', value: '++++' },
                { title: '++', value: '++' },
                { title: '+', value: '+' },
            ],
            mode: 'fixed',
        })
        expect(methods.courseWorkGradeConfigurationForType.call(ctx, 'PR')).toEqual({
            items: [],
            mode: 'free',
        })
        expect(methods.courseWorkGradeConfigurationForType.call(ctx, 'KO')).toEqual({
            items: [],
            mode: 'none',
        })
    })

    it('uses the saved work type to resolve the grade control in the cell dialog', () => {
        const methods = (CourseTable as any).methods
        const entry = { teaching_course_work_id: 31 }
        const ctx = {
            courseWorkForCellEntry: vi.fn().mockReturnValue({ type: 'TW' }),
            courseWorkGradeConfigurationForType: vi.fn().mockReturnValue({
                items: [{ title: '++++', value: '++++' }],
                mode: 'fixed',
            }),
            courseWorkGradeInputModeForType: methods.courseWorkGradeInputModeForType,
        }

        expect(methods.courseWorkEntryGradeItems.call(ctx, entry)).toEqual([
            { title: '++++', value: '++++' },
        ])
        expect(methods.courseWorkEntryGradeInputMode.call(ctx, entry)).toBe('fixed')
    })

    it('removes stale grades when a work type has no grading properties', () => {
        const methods = (CourseTable as any).methods
        const groups = [{
            comment: 'Weiterhin erhalten',
            grade: '2',
            grades: [{ student_id: 10, grade: '2' }],
            student_ids: [10],
        }]

        expect(methods.courseWorkGroupsForGradeInputMode.call({}, groups, 'none')).toEqual([{
            comment: 'Weiterhin erhalten',
            grade: null,
            grades: [],
            student_ids: [10],
        }])
        expect(methods.courseWorkGroupsForGradeInputMode.call({}, groups, 'free')).toBe(groups)
    })

    it('toggles a fixed work grade when its chip is clicked again', () => {
        const methods = (CourseTable as any).methods

        expect(methods.toggledCourseWorkGrade.call({}, '', '++++')).toBe('++++')
        expect(methods.toggledCourseWorkGrade.call({}, '++++', '++++')).toBe('')
        expect(methods.toggledCourseWorkGrade.call({}, '++', '++++')).toBe('++++')
    })

    it('creates a work for the date selected in the dialog', async () => {
        const methods = (CourseTable as any).methods
        const store = vi.fn().mockResolvedValue({ data: { id: 12 } })
        const cancelDateWorkForm = vi.fn()
        const loadCourseTableData = vi.fn().mockResolvedValue(true)
        const ctx = {
            applySavedCourseWork: methods.applySavedCourseWork,
            canSaveDateWork: true,
            courseWorks: [],
            courseWorkGradeInputModeForType: vi.fn().mockReturnValue('free'),
            courseWorkGroupsForGradeInputMode: methods.courseWorkGroupsForGradeInputMode,
            courseWorkStore: { store },
            cancelDateWorkForm,
            loadCourseTableData,
            selected_course: { id: 20 },
            workDialog: { courseDate: { date: '2026-05-16' }, open: true },
            workDialogForm: {
                date_for_all_groups: '',
                description: 'Kapitel 4',
                finish_until_date: new Date(2026, 4, 20),
                groups: [],
                group_size: null,
                id: null,
                is_group_work: false,
                is_random_groups: false,
                status: [],
                teaching_course_id: 20,
                title: 'Schularbeit',
                type: 'SA',
            },
            workSaving: false,
            dateKey: methods.dateKey,
            normalizeDateKey: methods.normalizeDateKey,
        }

        await methods.saveDateWork.call(ctx)

        expect(store).toHaveBeenCalledWith({
            date_for_all_groups: '2026-05-16',
            description: 'Kapitel 4',
            finish_until_date: '2026-05-20',
            groups: [],
            group_size: null,
            id: null,
            is_group_work: false,
            is_random_groups: false,
            status: [],
            teaching_course_id: 20,
            title: 'Schularbeit',
            type: 'SA',
        })
        expect(cancelDateWorkForm).toHaveBeenCalledTimes(1)
        expect(loadCourseTableData).toHaveBeenCalledWith(20, true)
        expect(ctx.courseWorks).toEqual([{ id: 12 }])
        expect(ctx.workSaving).toBe(false)
    })

    it('defaults the finish-until date to the selected dialog date', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            dateKey: methods.dateKey,
            normalizeDateKey: methods.normalizeDateKey,
            selected_course: { id: 20 },
            workDialog: { courseDate: { date: '2026-05-16' }, open: true },
        }

        const form = methods.emptyDateWorkForm.call(ctx)

        expect(form.date_for_all_groups).toBe('2026-05-16')
        expect(form.finish_until_date).toBe('2026-05-16')
    })

    it('normalizes the finish-until date when a work is opened for editing', () => {
        const methods = (CourseTable as any).methods
        const ctx: Record<string, any> = {
            cloneDateWorkGroups: methods.cloneDateWorkGroups,
            emptyDateWorkForm: vi.fn().mockReturnValue({ groups: [] }),
            isGeneratedEmptyIndividualWorkGroups: vi.fn().mockReturnValue(false),
            normalizeDateKey: methods.normalizeDateKey,
            dateKey: methods.dateKey,
            workDialogForm: {},
        }

        methods.startEditingDateWork.call(ctx, {
            id: 12,
            date_for_all_groups: '2026-05-16',
            finish_until_date: '2026-05-20T00:00:00.000000Z',
            groups: [],
            is_group_work: false,
            status: [],
            title: 'Schularbeit',
            type: 'SA',
        })

        expect(ctx.workDialogForm.finish_until_date).toBe('2026-05-20')
    })

    it('updates a work while preserving its group assignments', async () => {
        const methods = (CourseTable as any).methods
        const update = vi.fn().mockResolvedValue({ data: { id: 12 } })
        const loadCourseTableData = vi.fn().mockResolvedValue(true)
        const groups = [{ date: '2026-05-16', student_ids: [1, 2], grade: '2' }]
        const ctx = {
            applySavedCourseWork: methods.applySavedCourseWork,
            canSaveDateWork: true,
            courseWorks: [{ id: 12, title: 'Alt' }],
            courseWorkGradeInputModeForType: vi.fn().mockReturnValue('free'),
            courseWorkGroupsForGradeInputMode: methods.courseWorkGroupsForGradeInputMode,
            courseWorkStore: { update },
            cancelDateWorkForm: vi.fn(),
            loadCourseTableData,
            selected_course: { id: 20 },
            workDialog: { courseDate: { date: '2026-05-16' }, open: true },
            workDialogForm: {
                date_for_all_groups: '2026-05-16',
                description: 'Überarbeitet',
                groups,
                group_size: 2,
                id: 12,
                is_group_work: true,
                is_random_groups: false,
                status: [],
                teaching_course_id: 20,
                title: 'Projekt',
                type: 'GA',
            },
            workSaving: false,
            dateKey: methods.dateKey,
            normalizeDateKey: methods.normalizeDateKey,
        }

        await methods.saveDateWork.call(ctx)

        expect(update).toHaveBeenCalledWith(expect.objectContaining({
            id: 12,
            date_for_all_groups: '2026-05-16',
            description: 'Überarbeitet',
            groups,
            is_group_work: true,
            title: 'Projekt',
            type: 'GA',
        }))
        expect(loadCourseTableData).toHaveBeenCalledWith(20, true)
        expect(ctx.courseWorks).toEqual([{ id: 12 }])
    })

    it('persists changing a group work to an individual work', async () => {
        const methods = (CourseTable as any).methods
        const update = vi.fn().mockResolvedValue({ data: { id: 12 } })
        const loadCourseTableData = vi.fn().mockResolvedValue(true)
        const groups = [{ date: '2026-05-16', student_ids: [1, 2], grade: '2' }]
        const ctx = {
            applySavedCourseWork: methods.applySavedCourseWork,
            canSaveDateWork: true,
            courseWorks: [{ id: 12, title: 'Alt' }],
            courseWorkGradeInputModeForType: vi.fn().mockReturnValue('free'),
            courseWorkGroupsForGradeInputMode: methods.courseWorkGroupsForGradeInputMode,
            courseWorkStore: { update },
            cancelDateWorkForm: vi.fn(),
            loadCourseTableData,
            selected_course: { id: 20 },
            workDialog: { courseDate: { date: '2026-05-16' }, open: true },
            workDialogForm: {
                date_for_all_groups: '2026-05-16',
                description: 'Überarbeitet',
                groups,
                group_size: 2,
                id: 12,
                is_group_work: false,
                is_random_groups: false,
                status: [],
                teaching_course_id: 20,
                title: 'Projekt',
                type: 'GA',
            },
            workSaving: false,
            dateKey: methods.dateKey,
            normalizeDateKey: methods.normalizeDateKey,
        }

        await methods.saveDateWork.call(ctx)

        expect(update).toHaveBeenCalledWith(expect.objectContaining({
            groups,
            id: 12,
            is_group_work: false,
        }))
        expect(loadCourseTableData).toHaveBeenCalledWith(20, true)
    })

    it('stages a new type until the type edit is confirmed', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            workDialogForm: { id: 12, type: 'A' },
            workDialogTypeDraft: 'A',
            workDialogTypeEditing: true,
            canConfirmDateWorkType: true,
        }

        methods.selectDateWorkType.call(ctx, 'P')

        expect(ctx.workDialogForm.type).toBe('A')
        expect(ctx.workDialogTypeDraft).toBe('P')
        expect(ctx.workDialogTypeEditing).toBe(true)

        methods.confirmDateWorkTypeEditing.call(ctx)

        expect(ctx.workDialogForm.type).toBe('P')
        expect(ctx.workDialogTypeDraft).toBe('')
        expect(ctx.workDialogTypeEditing).toBe(false)
    })

    it('discards the staged type when type editing is cancelled', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            workDialogForm: { id: 12, type: 'A' },
            workDialogTypeDraft: 'P',
            workDialogTypeEditing: true,
        }

        methods.cancelDateWorkTypeEditing.call(ctx)

        expect(ctx.workDialogForm.type).toBe('A')
        expect(ctx.workDialogTypeDraft).toBe('')
        expect(ctx.workDialogTypeEditing).toBe(false)
    })

    it('stages an Arbeitsform change until editing is confirmed', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            canConfirmDateWorkMode: true,
            workDialogForm: { is_group_work: false },
            workDialogModeDraft: false,
            workDialogModeEditing: false,
        }

        methods.beginDateWorkModeEditing.call(ctx)
        ctx.workDialogModeDraft = true

        expect(ctx.workDialogForm.is_group_work).toBe(false)
        expect(ctx.workDialogModeEditing).toBe(true)

        methods.confirmDateWorkModeEditing.call(ctx)

        expect(ctx.workDialogForm.is_group_work).toBe(true)
        expect(ctx.workDialogModeDraft).toBe(false)
        expect(ctx.workDialogModeEditing).toBe(false)
    })

    it('discards the staged Arbeitsform when editing is cancelled', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            workDialogForm: { is_group_work: false },
            workDialogModeDraft: true,
            workDialogModeEditing: true,
        }

        methods.cancelDateWorkModeEditing.call(ctx)

        expect(ctx.workDialogForm.is_group_work).toBe(false)
        expect(ctx.workDialogModeDraft).toBe(false)
        expect(ctx.workDialogModeEditing).toBe(false)
    })

    it('resolves group member names from registered and imported student ids', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            sortedSelectedStudents: [
                { id: 2098, user_id: 1488, first_name: 'Anna', last_name: 'Muster' },
                { id: 2099, user_id: 1489, first_name: 'Ben', last_name: 'Beispiel' },
            ],
            registeredStudentUserId: methods.registeredStudentUserId,
            studentName: methods.studentName,
            workGroupStudentName: methods.workGroupStudentName,
        }

        expect(methods.workGroupStudentNames.call(ctx, { student_ids: [1488, 2099, 9999] })).toEqual([
            'Muster, Anna',
            'Beispiel, Ben',
            'Schüler:in #9999',
        ])
    })

    it('recognizes empty per-student bookkeeping records as generated individual-work groups', () => {
        const methods = (CourseTable as any).methods
        const groups = [1488, 1489, 1490].map((studentId) => ({
            student_ids: [studentId],
            date: '2026-09-14',
            name: null,
            comment: null,
            grade: null,
            grades: [{ student_id: studentId, grade: '' }],
            comments: [{ student_id: studentId, comment: '' }],
            points: [],
        }))

        expect(methods.isGeneratedEmptyIndividualWorkGroups.call({}, groups)).toBe(true)
    })

    it('preserves genuine groups and individual-work records containing assessment data', () => {
        const methods = (CourseTable as any).methods

        expect(methods.isGeneratedEmptyIndividualWorkGroups.call({}, [
            { student_ids: [1488, 1489], name: 'Recherche' },
            { student_ids: [1490, 1491], name: 'Präsentation' },
        ])).toBe(false)
        expect(methods.isGeneratedEmptyIndividualWorkGroups.call({}, [
            {
                student_ids: [1488],
                grades: [{ student_id: 1488, grade: '2' }],
                comments: [{ student_id: 1488, comment: '' }],
                points: [],
            },
            {
                student_ids: [1489],
                grades: [{ student_id: 1489, grade: '' }],
                comments: [{ student_id: 1489, comment: '' }],
                points: [],
            },
        ])).toBe(false)
    })

    it('removes generated individual-work groups when an affected group work is opened', () => {
        const methods = (CourseTable as any).methods
        const generatedGroups = [1488, 1489].map((studentId) => ({
            student_ids: [studentId],
            grades: [{ student_id: studentId, grade: '' }],
            comments: [{ student_id: studentId, comment: '' }],
            points: [],
        }))
        const ctx: Record<string, any> = {
            dateKey: methods.dateKey,
            cloneDateWorkGroups: methods.cloneDateWorkGroups,
            emptyDateWorkForm: vi.fn().mockReturnValue({ groups: [] }),
            isGeneratedEmptyIndividualWorkGroups: methods.isGeneratedEmptyIndividualWorkGroups,
            normalizeDateKey: methods.normalizeDateKey,
            workDialogForm: {},
            workDialogFormOpen: false,
            workDialogTab: 'groups',
            workDialogTypeDraft: 'P',
            workDialogTypeEditing: true,
        }

        methods.startEditingDateWork.call(ctx, {
            id: 56,
            date_for_all_groups: '2026-09-14',
            groups: generatedGroups,
            is_group_work: true,
            status: [],
            title: 'Erstellen einer Idee',
            type: 'A',
        })

        expect(ctx.workDialogForm.groups).toEqual([])
        expect(ctx.workDialogFormOpen).toBe(true)
    })

    it('discards group membership changes when work editing is cancelled', () => {
        const methods = (CourseTable as any).methods
        const work = {
            id: 56,
            date_for_all_groups: '2026-09-14',
            groups: [
                {
                    student_ids: [1488],
                    grades: [{ student_id: 1488, grade: '2' }],
                    comments: [],
                    points: [],
                },
                {
                    student_ids: [1489],
                    grades: [{ student_id: 1489, grade: '1' }],
                    comments: [],
                    points: [],
                },
            ],
            is_group_work: true,
            status: [],
            title: 'Projekt',
            type: 'A',
        }
        const ctx: Record<string, any> = {
            cloneDateWorkGroups: methods.cloneDateWorkGroups,
            closeRandomGroupsDialog: vi.fn(),
            dateKey: methods.dateKey,
            emptyDateWorkForm: vi.fn().mockReturnValue({ groups: [] }),
            isGeneratedEmptyIndividualWorkGroups: vi.fn().mockReturnValue(false),
            normalizeDateKey: methods.normalizeDateKey,
            workDialogForm: {},
            workDialogFormOpen: false,
            workDialogGroupDetails: { groupIndex: null, open: false },
            workDialogTab: 'work',
            workDialogTypeDraft: '',
            workDialogTypeEditing: false,
        }

        methods.startEditingDateWork.call(ctx, work)
        ctx.workDialogGroups = ctx.workDialogForm.groups
        methods.moveWorkGroupStudentToGroup.call(ctx, 0, 1, 1488)
        methods.cancelDateWorkForm.call(ctx)

        expect(work.groups).toEqual([
            {
                student_ids: [1488],
                grades: [{ student_id: 1488, grade: '2' }],
                comments: [],
                points: [],
            },
            {
                student_ids: [1489],
                grades: [{ student_id: 1489, grade: '1' }],
                comments: [],
                points: [],
            },
        ])
        expect(ctx.workDialogForm.groups).toEqual([])
    })

    it('removes generated individual-work groups when switching to group work', () => {
        const watcher = (CourseTable as any).watch['workDialogForm.is_group_work']
        const methods = (CourseTable as any).methods
        const ctx = {
            isGeneratedEmptyIndividualWorkGroups: vi.fn().mockReturnValue(true),
            workDialogForm: { groups: [{ student_ids: [1488] }, { student_ids: [1489] }] },
            workDialogTab: 'work',
        }

        watcher.call(ctx, true, false)

        expect(ctx.isGeneratedEmptyIndividualWorkGroups).toHaveBeenCalledTimes(1)
        expect(ctx.workDialogForm.groups).toEqual([])
        expect(methods.isGeneratedEmptyIndividualWorkGroups).toBeTypeOf('function')
    })

    it('resets a tab that is unavailable after changing the work mode', () => {
        const watcher = (CourseTable as any).watch['workDialogForm.is_group_work']
        const ctx = {
            isGeneratedEmptyIndividualWorkGroups: vi.fn().mockReturnValue(false),
            workDialogForm: { groups: [] },
            workDialogTab: 'students',
        }

        watcher.call(ctx, true, false)
        expect(ctx.workDialogTab).toBe('work')

        ctx.workDialogTab = 'groups'
        watcher.call(ctx, false, true)
        expect(ctx.workDialogTab).toBe('work')
    })

    it('opens the random-group size dialog with the saved size or a default of two', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            maxRandomGroupSize: 29,
            randomGroupsDialog: { groupSize: 2, open: false },
            workDialogForm: { group_size: 4 },
        }

        methods.openRandomGroupsDialog.call(ctx)

        expect(ctx.randomGroupsDialog).toEqual({ groupSize: 4, open: true })

        ctx.workDialogForm.group_size = null
        methods.openRandomGroupsDialog.call(ctx)

        expect(ctx.randomGroupsDialog).toEqual({ groupSize: 2, open: true })
    })

    it('stores a valid random-group size and generates the groups', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            canConfirmRandomGroupSize: true,
            closeRandomGroupsDialog: vi.fn(),
            generateRandomWorkDialogGroups: vi.fn(),
            randomGroupsDialog: { groupSize: 3, open: true },
            workDialogForm: {
                group_size: null,
                groups: [],
                is_random_groups: false,
            },
        }

        methods.confirmRandomGroupSize.call(ctx)

        expect(ctx.workDialogForm).toEqual({
            group_size: 3,
            groups: [],
            is_random_groups: true,
        })
        expect(ctx.generateRandomWorkDialogGroups).toHaveBeenCalledWith(3)
        expect(ctx.closeRandomGroupsDialog).toHaveBeenCalledTimes(1)
    })

    it('creates editable random groups containing every active course student exactly once', () => {
        const methods = (CourseTable as any).methods
        const random = vi.spyOn(Math, 'random').mockReturnValue(0)
        const ctx = {
            randomGroupSizes: methods.randomGroupSizes,
            registeredStudentUserId: methods.registeredStudentUserId,
            sortedSelectedStudents: [
                { id: 101, user_id: 1 },
                { id: 102, user_id: 2 },
                { id: 103, user_id: 3 },
                { id: 104, user_id: 4 },
                { id: 105, user_id: 5 },
            ],
            workDialogForm: {
                date_for_all_groups: '2026-09-14',
                groups: [{ student_ids: [999] }],
            },
        }

        try {
            methods.generateRandomWorkDialogGroups.call(ctx, 2)
        } finally {
            random.mockRestore()
        }

        expect(ctx.workDialogForm.groups.map((group: Record<string, any>) => group.student_ids.length)).toEqual([3, 2])
        expect(ctx.workDialogForm.groups.flatMap((group: Record<string, any>) => group.student_ids).sort()).toEqual([1, 2, 3, 4, 5])
        expect(ctx.workDialogForm.groups).toEqual(expect.arrayContaining([
            expect.objectContaining({
                _is_new: true,
                date: '2026-09-14',
            }),
        ]))
    })

    it('renders work tabs without a clipping window around generated group cards', () => {
        const source = readFileSync(
            resolve('resources/js/pages/admin/teaching/overview/components/CourseTable.vue'),
            'utf8',
        )

        expect(source).not.toContain('<v-window')
        expect(source).not.toContain('<v-window-item')
        expect(source).toContain('<div v-show="workDialogTab === \'work\'">')
        expect(source).toContain('v-show="workDialogTab === \'groups\'"')
    })

    it('accepts only whole random-group sizes up to the active course roster size', () => {
        const canConfirmRandomGroupSize = (CourseTable as any).computed.canConfirmRandomGroupSize

        expect(canConfirmRandomGroupSize.call({ maxRandomGroupSize: 29, randomGroupsDialog: { groupSize: 2 } })).toBe(true)
        expect(canConfirmRandomGroupSize.call({ maxRandomGroupSize: 29, randomGroupsDialog: { groupSize: 29 } })).toBe(true)
        expect(canConfirmRandomGroupSize.call({ maxRandomGroupSize: 29, randomGroupsDialog: { groupSize: 1 } })).toBe(false)
        expect(canConfirmRandomGroupSize.call({ maxRandomGroupSize: 29, randomGroupsDialog: { groupSize: 2.5 } })).toBe(false)
        expect(canConfirmRandomGroupSize.call({ maxRandomGroupSize: 29, randomGroupsDialog: { groupSize: 30 } })).toBe(false)
    })

    it('previews the generated group sizes for the active course roster', () => {
        const computed = (CourseTable as any).computed
        const methods = (CourseTable as any).methods
        const ctx = {
            canConfirmRandomGroupSize: true,
            maxRandomGroupSize: 29,
            randomGroupSizes: methods.randomGroupSizes,
            randomGroupsDialog: { groupSize: 5 },
        }

        expect(methods.randomGroupSizes.call({}, 29, 5)).toEqual([5, 5, 5, 5, 5, 4])
        expect(computed.randomGroupSizePreview.call(ctx)).toBe('5 | 5 | 5 | 5 | 5 | 4')
    })

    it('previews the same no-singleton remainder rule used for random generation', () => {
        const methods = (CourseTable as any).methods

        expect(methods.randomGroupSizes.call({}, 11, 5)).toEqual([6, 5])
    })

    it('shows the saved random-group configuration only while editing a configured work', () => {
        const hasSavedRandomGroupConfiguration = (CourseTable as any).computed.hasSavedRandomGroupConfiguration

        expect(hasSavedRandomGroupConfiguration.call({
            workDialogForm: { id: 56, group_size: 5, is_random_groups: true },
        })).toBe(true)
        expect(hasSavedRandomGroupConfiguration.call({
            workDialogForm: { id: null, group_size: 5, is_random_groups: true },
        })).toBe(false)
        expect(hasSavedRandomGroupConfiguration.call({
            workDialogForm: { id: 56, group_size: 5, is_random_groups: false },
        })).toBe(false)
    })

    it('detects whether course students remain unassigned to a work group', () => {
        const unassignedWorkGroupStudents = (CourseTable as any).computed.unassignedWorkGroupStudents
        const hasUnassignedWorkGroupStudents = (CourseTable as any).computed.hasUnassignedWorkGroupStudents
        const registeredStudentUserId = (CourseTable as any).methods.registeredStudentUserId
        const studentName = (CourseTable as any).methods.studentName
        const sortedSelectedStudents = [
            { id: 2098, user_id: 1488, first_name: 'Anna', last_name: 'Muster' },
            { id: 2099, user_id: 1489, first_name: 'Ben', last_name: 'Beispiel' },
        ]

        const unassignedStudents = unassignedWorkGroupStudents.call({
            registeredStudentUserId,
            sortedSelectedStudents,
            studentName,
            workDialogGroups: [{ student_ids: [1488] }],
        })

        expect(unassignedStudents).toEqual([{ title: 'Beispiel, Ben', value: 1489 }])
        expect(hasUnassignedWorkGroupStudents.call({ unassignedWorkGroupStudents: unassignedStudents })).toBe(true)
        expect(hasUnassignedWorkGroupStudents.call({
            unassignedWorkGroupStudents: [],
        })).toBe(false)
    })

    it('adds an empty group using the work date', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            workDialogForm: {
                date_for_all_groups: '2026-05-16',
                groups: [],
            },
        }

        methods.addWorkDialogGroup.call(ctx)

        expect(ctx.workDialogForm.groups).toEqual([{
            _is_new: true,
            student_ids: [],
            date: '2026-05-16',
            comment: '',
            grade: '',
            grades: {},
            comments: {},
            points: {},
            use_individual_grades: false,
        }])
    })

    it('offers only students not assigned to another group', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            workDialogGroups: [
                { student_ids: [1488] },
                { _is_new: true, student_ids: [1489] },
            ],
            sortedSelectedStudents: [
                { id: 2098, user_id: 1488, first_name: 'Anna', last_name: 'Muster' },
                { id: 2099, user_id: 1489, first_name: 'Ben', last_name: 'Beispiel' },
                { id: 2100, user_id: 1490, first_name: 'Clara', last_name: 'Demo' },
            ],
            registeredStudentUserId: methods.registeredStudentUserId,
            studentName: methods.studentName,
        }

        expect(methods.workGroupStudentItems.call(ctx, 1)).toEqual([
            { title: 'Beispiel, Ben', value: 1489 },
            { title: 'Demo, Clara', value: 1490 },
        ])
    })

    it('formats the current member count for each work group', () => {
        const workGroupMemberCountTitle = (CourseTable as any).methods.workGroupMemberCountTitle

        expect(workGroupMemberCountTitle.call({}, { student_ids: [1488] })).toBe('1 Mitglied')
        expect(workGroupMemberCountTitle.call({}, { student_ids: [1488, 1489, 1490] })).toBe('3 Mitglieder')
        expect(workGroupMemberCountTitle.call({}, {})).toBe('0 Mitglieder')
    })

    it('opens and closes the selected work group details', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            workDialogGroups: [{ student_ids: [1488] }],
            workDialogGroupDetails: { groupIndex: null, open: false },
            workSaving: false,
        }

        methods.openWorkDialogGroupDetails.call(ctx, 0)
        expect(ctx.workDialogGroupDetails).toEqual({ groupIndex: 0, open: true })

        methods.closeWorkDialogGroupDetails.call(ctx)
        expect(ctx.workDialogGroupDetails).toEqual({ groupIndex: null, open: false })
    })

    it('shows every group student with their grade and comment and switches safely to individual grading', () => {
        const methods = (CourseTable as any).methods
        const group = {
            student_ids: [1488, 1489],
            grade: '2',
            grades: [],
            comments: [{ student_id: 1488, comment: 'Sauber' }],
            use_individual_grades: false,
        }
        const ctx = {
            registeredStudentUserId: methods.registeredStudentUserId,
            sortedSelectedStudents: [
                { id: 2098, user_id: 1488, first_name: 'Anna', last_name: 'Muster' },
                { id: 2099, user_id: 1489, first_name: 'Ben', last_name: 'Beispiel' },
            ],
            studentName: methods.studentName,
            workDialogGroups: [group],
            workGroupStudentComment: methods.workGroupStudentComment,
            workGroupStudentGrade: methods.workGroupStudentGrade,
            workGroupStudentName: methods.workGroupStudentName,
        }

        expect(methods.workGroupGradeRows.call(ctx, group)).toEqual([
            { comment: 'Sauber', grade: '2', studentId: 1488, studentName: 'Muster, Anna' },
            { comment: '', grade: '2', studentId: 1489, studentName: 'Beispiel, Ben' },
        ])

        methods.setWorkGroupStudentComment.call(ctx, 0, 1489, 'Mehr Details')
        methods.setWorkGroupStudentGrade.call(ctx, 0, 1489, '1')

        expect(group).toMatchObject({
            grade: '',
            grades: [
                { student_id: 1488, grade: '2' },
                { student_id: 1489, grade: '1' },
            ],
            comments: [
                { student_id: 1488, comment: 'Sauber' },
                { student_id: 1489, comment: 'Mehr Details' },
            ],
            use_individual_grades: true,
        })
    })

    it('builds individual-work grading rows for every active course student', () => {
        const computed = (CourseTable as any).computed
        const methods = (CourseTable as any).methods
        const ctx = {
            registeredStudentUserId: methods.registeredStudentUserId,
            sortedSelectedStudents: [
                { id: 2098, user_id: 1488, first_name: 'Anna', last_name: 'Muster' },
                { id: 2099, user_id: 1489, first_name: 'Ben', last_name: 'Beispiel' },
            ],
            studentName: methods.studentName,
            workDialogGroups: [{
                student_ids: [1488],
                grades: [{ student_id: 1488, grade: '2' }],
                comments: [{ student_id: 1488, comment: 'Sehr sauber' }],
            }],
            workGroupStudentComment: methods.workGroupStudentComment,
            workGroupStudentGrade: methods.workGroupStudentGrade,
        }

        expect(computed.individualWorkGradeRows.call(ctx)).toEqual([
            { comment: 'Sehr sauber', grade: '2', studentId: 1488, studentName: 'Muster, Anna' },
            { comment: '', grade: '', studentId: 1489, studentName: 'Beispiel, Ben' },
        ])
    })

    it('creates a missing individual-work group when its comment or grade changes', () => {
        const methods = (CourseTable as any).methods
        const groups: Array<Record<string, any>> = []
        const ctx = {
            ensureIndividualWorkStudentGroup: methods.ensureIndividualWorkStudentGroup,
            setWorkGroupStudentComment: methods.setWorkGroupStudentComment,
            setWorkGroupStudentGrade: methods.setWorkGroupStudentGrade,
            workDialogForm: {
                date_for_all_groups: '2026-09-21',
                groups,
            },
            workDialogGroups: groups,
            workGroupStudentComment: methods.workGroupStudentComment,
            workGroupStudentGrade: methods.workGroupStudentGrade,
        }

        methods.setIndividualWorkStudentComment.call(ctx, 1488, 'Gut erklärt')
        methods.setIndividualWorkStudentGrade.call(ctx, 1488, '1')

        expect(groups).toEqual([expect.objectContaining({
            student_ids: [1488],
            date: '2026-09-21',
            grade: '',
            grades: [{ student_id: 1488, grade: '1' }],
            comments: [{ student_id: 1488, comment: 'Gut erklärt' }],
            points: [],
            use_individual_grades: true,
        })])
    })

    it('moves a student and individual values between work groups', () => {
        const moveWorkGroupStudentToGroup = (CourseTable as any).methods.moveWorkGroupStudentToGroup
        const workDialogGroups = [
            {
                student_ids: [1488, 1489],
                grades: [{ student_id: 1488, grade: '2' }, { student_id: 1489, grade: '1' }],
                comments: [{ student_id: 1488, comment: 'Gut' }],
                points: [{ student_id: 1488, points: 18 }],
            },
            {
                student_ids: [1490],
                grades: [{ student_id: 1490, grade: '3' }],
                comments: [],
                points: [],
            },
        ]

        expect(moveWorkGroupStudentToGroup.call({ workDialogGroups }, 0, 1, 1488)).toBe(true)
        expect(workDialogGroups[0]).toMatchObject({
            student_ids: [1489],
            grades: [{ student_id: 1489, grade: '1' }],
            comments: [],
            points: [],
        })
        expect(workDialogGroups[1]).toMatchObject({
            student_ids: [1490, 1488],
            grades: [{ student_id: 1490, grade: '3' }, { student_id: 1488, grade: '2' }],
            comments: [{ student_id: 1488, comment: 'Gut' }],
            points: [{ student_id: 1488, points: 18 }],
        })
    })

    it('assigns an unassigned student to a work group', () => {
        const assignUnassignedWorkGroupStudent = (CourseTable as any).methods.assignUnassignedWorkGroupStudent
        const workDialogGroups = [{ student_ids: [1488] }]

        expect(assignUnassignedWorkGroupStudent.call({
            unassignedWorkGroupStudents: [{ title: 'Beispiel, Ben', value: 1489 }],
            workDialogGroups,
        }, 0, 1489)).toBe(true)
        expect(workDialogGroups[0].student_ids).toEqual([1488, 1489])
    })

    it('removes a student and their individual values from a work group', () => {
        const removeWorkGroupStudent = (CourseTable as any).methods.removeWorkGroupStudent
        const workDialogGroups = [{
            student_ids: [1488, 1489],
            grades: [{ student_id: 1488, grade: '2' }, { student_id: 1489, grade: '1' }],
            comments: [{ student_id: 1488, comment: 'Gut' }],
            points: [{ student_id: 1488, points: 18 }],
        }]

        expect(removeWorkGroupStudent.call({ workDialogGroups, workSaving: false }, 0, 1488)).toBe(true)
        expect(workDialogGroups[0]).toMatchObject({
            student_ids: [1489],
            grades: [{ student_id: 1489, grade: '1' }],
            comments: [],
            points: [],
        })
    })

    it('prevents saving while a new group has no students', () => {
        const canSaveDateWork = (CourseTable as any).computed.canSaveDateWork

        expect(canSaveDateWork.call({
            availableWorkTypes: [{ value: 'A' }],
            workDialogForm: { is_group_work: true, type: 'A' },
            workDialogGroups: [{ _is_new: true, student_ids: [] }],
            workDialogModeEditing: false,
            workDialogTypeEditing: false,
            workSaving: false,
        })).toBe(false)
    })

    it('removes a work from the date dialog after confirmation', async () => {
        const methods = (CourseTable as any).methods
        const destroy = vi.fn().mockResolvedValue(true)
        const loadCourseWorks = vi.fn().mockResolvedValue(true)
        const cancelDateWorkForm = vi.fn()
        const work = { id: 12, title: 'Schularbeit' }
        const ctx = {
            courseWorkStore: { destroy },
            deleteWorkDialog: { open: true, work },
            loadCourseWorks,
            cancelDateWorkForm,
            workDeleting: false,
            workDialogForm: { id: 12 },
        }

        await methods.confirmDeleteDateWork.call(ctx)

        expect(destroy).toHaveBeenCalledWith(12)
        expect(loadCourseWorks).toHaveBeenCalledTimes(1)
        expect(cancelDateWorkForm).toHaveBeenCalledTimes(1)
        expect(ctx.deleteWorkDialog).toEqual({ open: false, work: null })
        expect(ctx.workDeleting).toBe(false)
    })

    it('enables attendance markers for non-free dates including future dates', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            isFreeCourseDate: methods.isFreeCourseDate,
        }

        expect(methods.isAttendanceToggleable.call(ctx, { id: 1, date: '2026-03-10', status: [] })).toBe(true)
        expect(methods.isAttendanceToggleable.call(ctx, { id: 2, date: '2026-03-09', status: [] })).toBe(true)
        expect(methods.isAttendanceToggleable.call(ctx, { id: 3, date: '2026-03-11', status: [] })).toBe(true)
        expect(methods.isAttendanceToggleable.call(ctx, { id: 4, date: '2026-03-10', status: ['free'] })).toBe(false)
        expect(methods.isAttendanceToggleable.call(ctx, { id: 5, date: '2026-03-10', status: ['entfaellt'] })).toBe(false)
    })

    it('accepts a fixed table view from the parent panel', () => {
        const viewProp = (CourseTable as any).props.view
        const activeSemesterProp = (CourseTable as any).props.activeSemester

        expect(viewProp.default).toBe('entries')
        expect(viewProp.validator('entries')).toBe(true)
        expect(viewProp.validator('attendance')).toBe(true)
        expect(viewProp.validator('plain')).toBe(false)
        expect(activeSemesterProp.default).toBe(3)
        expect(activeSemesterProp.validator(1)).toBe(true)
        expect(activeSemesterProp.validator(3)).toBe(true)
        expect(activeSemesterProp.validator(4)).toBe(false)
    })

    it('loads, caches, and force-refreshes all entries table data for the selected course', async () => {
        const methods = (CourseTable as any).methods
        const indexTableData = vi.fn()
            .mockResolvedValueOnce({
                data: [{ id: 1 }],
                behaviour_entries: [{ id: 2 }],
                course_works: [{ id: 3 }],
            })
            .mockResolvedValueOnce({
                data: [{ id: 4 }],
                behaviour_entries: [{ id: 5 }],
                course_works: [{ id: 6 }],
            })
        const ctx = {
            selected_course: { id: 16 },
            entryStore: { indexTableData, courseEntries: [] },
            behaviourEntryStore: { courseEntries: [] },
            courseWorkStore: { courseWorks: [] },
            courseTableDataCourseId: null,
            courseTableDataRequestCourseId: null,
            courseTableDataRequestPromise: null,
        }

        expect(await methods.loadCourseTableData.call(ctx, 16)).toBe(true)
        expect(await methods.loadCourseTableData.call(ctx, 16)).toBe(true)

        expect(indexTableData).toHaveBeenCalledTimes(1)
        expect(ctx.entryStore.courseEntries).toEqual([{ id: 1 }])
        expect(ctx.behaviourEntryStore.courseEntries).toEqual([{ id: 2 }])
        expect(ctx.courseWorkStore.courseWorks).toEqual([{ id: 3 }])

        expect(await methods.loadCourseTableData.call(ctx, 16, true)).toBe(true)
        expect(indexTableData).toHaveBeenCalledTimes(2)
        expect(ctx.entryStore.courseEntries).toEqual([{ id: 4 }])
        expect(ctx.behaviourEntryStore.courseEntries).toEqual([{ id: 5 }])
        expect(ctx.courseWorkStore.courseWorks).toEqual([{ id: 6 }])
    })

    it('restores the table view from the parent panel', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            tableView: 'attendance',
        }

        methods.restoreTableView.call(ctx, 'entries')
        expect(ctx.tableView).toBe('entries')

        methods.restoreTableView.call(ctx, 'attendance')
        expect(ctx.tableView).toBe('attendance')

        methods.restoreTableView.call(ctx, undefined)
        expect(ctx.tableView).toBe('entries')
    })

    it('opens a persistent bulk attendance dialog from an eligible date header', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            bulkAttendanceDialog: {
                courseDate: null,
                open: false,
                present: true,
            },
            isAttendanceToggleable: () => true,
        }
        const courseDate = { id: 7, date: '2026-03-09', status: [] }

        methods.openBulkAttendanceDialog.call(ctx, courseDate, false)

        expect(ctx.bulkAttendanceDialog).toEqual({
            courseDate,
            open: true,
            present: false,
        })
    })

    it('opens and closes the cell entry dialog for a student and course date', () => {
        const methods = (CourseTable as any).methods
        const student = { id: 10, first_name: 'Anna', last_name: 'Berger' }
        const courseDate = { id: 7, date: '2026-03-09' }
        const ctx = {
            tableView: 'entries',
            entrySaving: false,
            courseWorkEntrySavingUid: null,
            courseWorkEntryDrafts: {},
            entryDialog: {
                courseDate: null,
                open: false,
                student: null,
            },
            entryForm: {
                description: 'Alt',
                grade: '2',
                type: 'T',
            },
            entryFormOpen: true,
            cancelNewCellEntry() {
                methods.cancelNewCellEntry.call(this)
            },
            resetCourseWorkEntryDrafts: vi.fn(),
        }

        methods.openEntryDialog.call(ctx, student, courseDate)

        expect(ctx.entryDialog).toEqual({
            courseDate,
            open: true,
            student,
        })

        methods.closeEntryDialog.call(ctx)

        expect(ctx.entryDialog).toEqual({
            courseDate: null,
            open: false,
            student: null,
        })
        expect(ctx.entryFormOpen).toBe(false)
    })

    it('does not open the entry dialog while attendance is selected', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            tableView: 'attendance',
            entryDialog: {
                courseDate: null,
                open: false,
                student: null,
            },
        }

        methods.openEntryDialog.call(ctx, { id: 10 }, { id: 7 })

        expect(ctx.entryDialog.open).toBe(false)
    })

    it('shows start, finish, and duration only on the linked work entry card', () => {
        const methods = (CourseTable as any).methods
        const work = {
            date_for_all_groups: '2026-10-10',
            finish_until_date: '2026-10-12',
            id: 16,
            title: 'Projektarbeit',
        }
        const entry = { date: '2026-10-12', teaching_course_work_id: 16 }
        const methodContext = {
            compactCourseDateTitle: ({ date }: { date: string }) => date,
            courseWorkDurationLabel: vi.fn().mockReturnValue('3 Tage'),
            courseWorkForCellEntry: vi.fn().mockReturnValue(work),
            courseWorkGroupForCellEntry: vi.fn().mockReturnValue({ date: '2026-10-09' }),
            dateKey: methods.dateKey,
            normalizeDateKey: methods.normalizeDateKey,
        }
        const period = methods.courseWorkEntryPeriod.call(methodContext, entry)

        expect(period).toEqual({
            durationLabel: '3 Tage',
            finishDateTitle: '2026-10-12',
            isSameDate: false,
            startDateTitle: '2026-10-09',
        })
        expect(methodContext.courseWorkDurationLabel).toHaveBeenCalledWith({
            date_for_all_groups: '2026-10-09',
            finish_until_date: '2026-10-12',
        })
    })

    it('combines matching work start and finish dates without a zero-day duration', () => {
        const methods = (CourseTable as any).methods
        const work = {
            date_for_all_groups: '2026-10-05',
            finish_until_date: '2026-10-05',
            id: 16,
        }
        const methodContext = {
            compactCourseDateTitle: ({ date }: { date: string }) => date,
            courseWorkDurationLabel: vi.fn().mockReturnValue('0 Tage'),
            courseWorkForCellEntry: vi.fn().mockReturnValue(work),
            courseWorkGroupForCellEntry: vi.fn().mockReturnValue(null),
            dateKey: methods.dateKey,
            normalizeDateKey: methods.normalizeDateKey,
        }

        expect(methods.courseWorkEntryPeriod.call(methodContext, {
            date: '2026-10-05',
            teaching_course_work_id: 16,
        })).toEqual({
            durationLabel: '',
            finishDateTitle: '2026-10-05',
            isSameDate: true,
            startDateTitle: '2026-10-05',
        })
        expect(methodContext.courseWorkDurationLabel).not.toHaveBeenCalled()
    })

    it('shows all assessment, behaviour, and notification entries for the selected cell only', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            entryStore: {
                courseEntries: [
                    { id: 1, user_id: 10, date: '2026-03-09', type: 'T', grade: '2' },
                    { id: 2, user_id: 10, date: '2026-03-09T08:00:00', type: 'M', grade: '+' },
                    { id: 3, user_id: 11, date: '2026-03-09', type: 'T', grade: '1' },
                    { id: 4, user_id: 10, date: '2026-03-10', type: 'T', grade: '3' },
                ],
            },
            behaviourEntryStore: {
                courseEntries: [
                    { id: 5, user_id: 10, date: '2026-03-09', kind: 'behaviour', type: 'V' },
                    { id: 6, user_id: 10, date: '2026-03-09', kind: 'notification', type: 'I' },
                    { id: 7, user_id: 11, date: '2026-03-09', kind: 'behaviour', type: 'V' },
                ],
            },
            registeredStudentUserId: methods.registeredStudentUserId,
            normalizeDateKey: methods.normalizeDateKey,
            dateKey: methods.dateKey,
            studentCellEntryDateKey: methods.studentCellEntryDateKey,
        }

        const entries = methods.entriesForCell.call(ctx, { id: 10, user_id: 10 }, { date: '2026-03-09' })

        expect(entries.map((entry: { uid: string }) => entry.uid)).toEqual([
            'assessment-2',
            'assessment-1',
            'behaviour-5',
            'behaviour-6',
        ])
    })

    it('shows work-derived student entries on the end date and falls back to their stored date', () => {
        const methods = (CourseTable as any).methods
        const workEntry = {
            id: 1,
            date: '2026-03-09',
            source: 'course_work',
            teaching_course_work_id: 12,
            type: 'A',
            user_id: 10,
        }
        const work = {
            id: 12,
            date_for_all_groups: '2026-03-09',
            finish_until_date: '2026-03-10',
        }
        const context = {
            behaviourEntryStore: { courseEntries: [] },
            courseWorks: [work],
            entryStore: { courseEntries: [workEntry] },
            sortedCourseDates: [{ date: '2026-03-09' }, { date: '2026-03-10' }],
            courseWorkForCellEntry: methods.courseWorkForCellEntry,
            dateKey: methods.dateKey,
            normalizeDateKey: methods.normalizeDateKey,
            registeredStudentUserId: methods.registeredStudentUserId,
            studentCellEntryDateKey: methods.studentCellEntryDateKey,
        }

        expect(methods.entriesForCell.call(context, { user_id: 10 }, { date: '2026-03-09' })).toEqual([])
        expect(methods.entriesForCell.call(context, { user_id: 10 }, { date: '2026-03-10' }))
            .toEqual([expect.objectContaining({ id: 1, uid: 'assessment-1' })])

        work.finish_until_date = null

        expect(methods.entriesForCell.call(context, { user_id: 10 }, { date: '2026-03-09' }))
            .toEqual([expect.objectContaining({ id: 1, uid: 'assessment-1' })])
    })

    it('separates behaviour and other entries from performance entries in table cells', () => {
        const methods = (CourseTable as any).methods
        const entries = [
            { uid: 'assessment-1', kind: 'assessment', type: 'A' },
            { uid: 'behaviour-2', kind: 'behaviour' },
            { uid: 'behaviour-3', kind: 'notification' },
            { uid: 'assessment-4', kind: 'assessment', type: 'V' },
            { uid: 'assessment-5', kind: 'assessment', type: 'W' },
        ]
        const ctx = {
            entryDefinitionCategory: vi.fn((entry: { type: 'A' | 'V' | 'W' }) => (
                { A: 'Benotung', V: 'Verhalten', W: 'Weitere' }
            )[entry.type]),
            entriesForCell: vi.fn().mockReturnValue(entries),
        }

        expect(methods.supplementaryEntriesForCell.call(ctx, { id: 10 }, { id: 7 }))
            .toEqual([entries[1], entries[2], entries[3], entries[4]])
        expect(methods.performanceEntriesForCell.call(ctx, { id: 10 }, { id: 7 }))
            .toEqual([entries[0]])
    })

    it('builds complete hover information for entries in a student date cell', () => {
        const methods = (CourseTable as any).methods
        const workEntry = {
            uid: 'assessment-1',
            kind: 'assessment',
            source: 'course_work',
            type: 'TW',
        }
        const behaviourEntry = {
            uid: 'behaviour-2',
            kind: 'behaviour',
            type: 'V',
            description: 'Ruhig mitgearbeitet',
        }
        const ctx = {
            cellEntryKindLabel: methods.cellEntryKindLabel,
            cellEntryTypeLabel: vi.fn((entry) => entry.type),
            courseWorkForCellEntry: vi.fn((entry) => entry === workEntry ? {
                title: 'Schreibübungen bis Lektion 40',
                description: 'Bitte sehr genau arbeiten!',
            } : null),
            courseWorkStudentComment: vi.fn().mockReturnValue('Super gemacht'),
            courseWorkStudentGrade: vi.fn().mockReturnValue('+'),
            entriesForCell: vi.fn().mockReturnValue([workEntry, behaviourEntry]),
            registeredStudentUserId: methods.registeredStudentUserId,
        }

        expect(methods.cellEntryHoverItems.call(ctx, { id: 10 }, { id: 7 })).toEqual([
            {
                comment: 'Super gemacht',
                description: 'Bitte sehr genau arbeiten!',
                grade: '+',
                kind: 'Bewertung',
                title: 'Schreibübungen bis Lektion 40',
                type: 'TW',
                uid: 'assessment-1',
            },
            {
                comment: 'Ruhig mitgearbeitet',
                description: '',
                grade: '',
                kind: 'Verhalten',
                title: '',
                type: 'V',
                uid: 'behaviour-2',
            },
        ])
    })

    it('compacts performance entries with the same type into one cell chip', () => {
        const methods = (CourseTable as any).methods
        const entries = [
            { uid: 'assessment-1', kind: 'assessment', type: 'MA', grade: '-', description: 'Aktiv mitgearbeitet' },
            { uid: 'assessment-2', kind: 'assessment', type: 'MA', grade: '+', description: 'Aktiv mitgearbeitet' },
            { uid: 'assessment-3', kind: 'assessment', type: 'MA', effective_grade: '+' },
            { uid: 'assessment-4', kind: 'assessment', type: 'SA', grade: '2', source: 'course_work' },
        ]
        const ctx = {
            courseWorkStudentComment: vi.fn().mockReturnValue('Super gemacht'),
            performanceEntriesForCell: vi.fn().mockReturnValue(entries),
            registeredStudentUserId: methods.registeredStudentUserId,
            sortedCompactEntryGrades: methods.sortedCompactEntryGrades,
        }

        const compactEntries = methods.compactPerformanceEntriesForCell.call(ctx, { id: 10 }, { id: 7 })

        expect(compactEntries).toHaveLength(2)
        expect(compactEntries.map((entry: Record<string, unknown>) => methods.compactCellEntryLabel.call({}, entry)))
            .toEqual(['MA: +, -', 'SA: 2'])
        expect(compactEntries.map((entry: Record<string, unknown>) => entry.description))
            .toEqual(['Aktiv mitgearbeitet', 'Super gemacht'])
    })

    it('builds compact labels for entries rendered inside table cells', () => {
        const methods = (CourseTable as any).methods

        expect(methods.compactCellEntryLabel.call({}, {
            kind: 'assessment',
            type: 'T',
            effective_grade: '2',
        })).toBe('T: 2')
        expect(methods.compactCellEntryLabel.call({}, {
            kind: 'assessment',
            type: 'M',
            grade: '+',
        })).toBe('M: +')
        expect(methods.compactCellEntryLabel.call({}, {
            kind: 'assessment',
            type: 'TW',
        })).toBe('TW: NA')
        expect(methods.compactCellEntryType.call({}, {
            type: 'TW',
        })).toBe('TW')
        expect(methods.compactCellEntryGrade.call({}, {
            effective_grade: '',
            grade: '',
        })).toBe('')
        expect(methods.compactCellEntryLabel.call({}, {
            kind: 'behaviour',
            type: 'V',
        })).toBe('V')
        expect(methods.compactCellEntryLabel.call({}, {
            kind: 'notification',
            type: 'I',
        })).toBe('I')
    })

    it('omits NA for entry types without properties', () => {
        const methods = (CourseTable as any).methods
        const context = {
            entryTypeHasProperties: methods.entryTypeHasProperties,
            selected_course: {
                teaching_entry_area: {
                    id: 15,
                    entry_definitions: [
                        { short_name: 'E', has_properties: false },
                        { short_name: 'MA', has_properties: true },
                    ],
                },
            },
            uses_entry_areas_for_grading_schema: true,
        }

        expect(methods.compactCellEntryLabel.call(context, {
            kind: 'assessment',
            type: 'E',
        })).toBe('E')
        expect(methods.compactCellEntryLabel.call(context, {
            kind: 'assessment',
            type: 'MA',
        })).toBe('MA: NA')
    })

    it('shows an open property chip only for grading entries that expect a property', () => {
        const methods = (CourseTable as any).methods
        const context = {
            entryDefinitionCategory: vi.fn((entry: { type: string }) => ({
                E: 'Verhalten',
                MA: 'Benotung',
                X: 'Benotung',
            })[entry.type]),
            entryTypeHasProperties: vi.fn((entry: { type: string }) => entry.type !== 'X'),
        }

        expect(methods.entryExpectsProperty.call(context, { kind: 'assessment', type: 'MA' })).toBe(true)
        expect(methods.entryExpectsProperty.call(context, { kind: 'assessment', type: 'E' })).toBe(false)
        expect(methods.entryExpectsProperty.call(context, { kind: 'assessment', type: 'X' })).toBe(false)
        expect(methods.entryExpectsProperty.call(context, { kind: 'behaviour', type: 'MA' })).toBe(false)
    })

    it('shows available comments in the entry card list', () => {
        const methods = (CourseTable as any).methods
        const context = {
            courseWorkEntryStudentComment: vi.fn().mockReturnValue('  Sehr gute Ausarbeitung  '),
        }

        expect(methods.cellEntryListComment.call(context, {
            description: '  Gute Mitarbeit  ',
            source: 'manual',
        })).toBe('Gute Mitarbeit')
        expect(methods.cellEntryListComment.call(context, {
            source: 'course_work',
        })).toBe('Sehr gute Ausarbeitung')
        expect(methods.cellEntryListComment.call(context, {})).toBe('')
    })

    it('saves a new entry for the selected student and date', async () => {
        const methods = (CourseTable as any).methods
        const store = vi.fn().mockResolvedValue({ data: { id: 12 } })
        const ctx = {
            canSaveCellEntry: true,
            entrySaving: false,
            entryStore: { store },
            selected_course: { id: 20 },
            selectedEntryTypeCategory: 'Benotung',
            registeredEntryStudentId: 10,
            entryDialog: { courseDate: { date: '2026-03-09' } },
            entryForm: {
                description: 'Gute Mitarbeit',
                grade: '+',
                type: 'M',
            },
            entryFormOpen: true,
            normalizeDateKey: methods.normalizeDateKey,
            dateKey: methods.dateKey,
            cancelNewCellEntry() {
                methods.cancelNewCellEntry.call(this)
            },
        }

        await methods.saveCellEntry.call(ctx)

        expect(store).toHaveBeenCalledWith({
            teaching_course_id: 20,
            user_id: 10,
            type: 'M',
            grade: '+',
            date: '2026-03-09',
            description: 'Gute Mitarbeit',
        })
        expect(ctx.entryFormOpen).toBe(false)
        expect(ctx.entrySaving).toBe(false)
    })

    it('opens manual entries for editing and keeps work-derived entries read-only', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            entryForm: {},
            entryFormOpen: false,
            canModifyCellEntry: methods.canModifyCellEntry,
        }
        const manualEntry = {
            id: 12,
            uid: 'assessment-12',
            kind: 'assessment',
            type: 'M',
            grade: '+',
            description: 'Gute Mitarbeit',
        }

        methods.startEditingCellEntry.call(ctx, manualEntry)

        expect(ctx.entryForm).toEqual({
            description: 'Gute Mitarbeit',
            doneDate: null,
            grade: '+',
            id: 12,
            kind: 'assessment',
            dueDate: null,
            type: 'M',
            uid: 'assessment-12',
        })
        expect(ctx.entryFormOpen).toBe(true)

        ctx.entryFormOpen = false
        methods.startEditingCellEntry.call(ctx, { ...manualEntry, source: 'course_work' })
        expect(ctx.entryFormOpen).toBe(false)
    })

    it('selects entry cards and opens manual entries in edit mode', () => {
        const methods = (CourseTable as any).methods
        const manualEntry = {
            id: 12,
            uid: 'assessment-12',
            kind: 'assessment',
            type: 'M',
        }
        const workEntry = {
            id: 13,
            uid: 'assessment-13',
            kind: 'assessment',
            source: 'course_work',
            type: 'TW',
        }
        const context: Record<string, any> = {
            cancelNewCellEntry: vi.fn(),
            canModifyCellEntry: methods.canModifyCellEntry,
            cellEntries: [manualEntry, workEntry],
            selectedCellEntryUid: null,
            startEditingCellEntry: vi.fn(),
        }

        methods.selectCellEntry.call(context, manualEntry)

        expect(context.selectedCellEntryUid).toBe(manualEntry.uid)
        expect(context.cancelNewCellEntry).toHaveBeenCalledTimes(1)
        expect(context.startEditingCellEntry).toHaveBeenCalledWith(manualEntry)
        expect(methods.isCellEntryExpanded.call(context, manualEntry)).toBe(true)
        expect(methods.isCellEntryExpanded.call(context, workEntry)).toBe(false)

        methods.selectCellEntry.call(context, workEntry)

        expect(context.selectedCellEntryUid).toBe(workEntry.uid)
        expect(context.startEditingCellEntry).toHaveBeenCalledTimes(1)
        expect(methods.isCellEntryExpanded.call(context, workEntry)).toBe(true)
    })

    it('shows only the selected entry while it is open', () => {
        const computed = (CourseTable as any).computed
        const firstEntry = { uid: 'assessment-12' }
        const secondEntry = { uid: 'behaviour-13' }
        const context = {
            cellEntries: [firstEntry, secondEntry],
            selectedCellEntryUid: null,
        }

        expect(computed.visibleCellEntries.call(context)).toEqual([firstEntry, secondEntry])

        context.selectedCellEntryUid = secondEntry.uid

        expect(computed.visibleCellEntries.call(context)).toEqual([secondEntry])

        context.selectedCellEntryUid = 'missing-entry'

        expect(computed.visibleCellEntries.call(context)).toEqual([firstEntry, secondEntry])
    })

    it('keeps entries collapsed until their card is selected', () => {
        const methods = (CourseTable as any).methods
        const entry = { uid: 'assessment-12' }

        expect(methods.isCellEntryExpanded.call({
            cellEntries: [entry],
            selectedCellEntryUid: null,
        }, entry)).toBe(false)
    })

    it('opens and closes an entry from its card', () => {
        const methods = (CourseTable as any).methods
        const entry = { uid: 'assessment-12' }
        const context = {
            cancelNewCellEntry: vi.fn(),
            entryForm: { uid: entry.uid },
            selectedCellEntryUid: entry.uid,
            selectCellEntry: vi.fn(),
        }

        methods.toggleCellEntry.call(context, entry)

        expect(context.cancelNewCellEntry).toHaveBeenCalledTimes(1)
        expect(context.selectedCellEntryUid).toBeNull()

        methods.toggleCellEntry.call(context, entry)

        expect(context.selectCellEntry).toHaveBeenCalledWith(entry)
    })

    it('makes the complete entry card interactive without propagating editor clicks', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/components/CourseTable.vue', 'utf8')
        )

        expect(source).toContain(':data-testid="`course-table-cell-entry-card-${entry.uid}`"\n                                :aria-expanded="isCellEntryExpanded(entry)"')
        expect(source).toContain('v-for="entry in visibleCellEntries"')
        expect(source).toContain(':role="isCellEntryExpanded(entry) ? undefined : \'button\'"')
        expect(source).toContain(':tabindex="isCellEntryExpanded(entry) ? undefined : 0"\n                                @click="toggleCellEntry(entry)"')
        expect(source).toContain('class="course-table-cell-entry-summary d-flex align-center flex-wrap ga-2">')
        expect(source).not.toContain("isCellEntryExpanded(entry) ? 'mdi-chevron-up' : 'mdi-chevron-down'")
        expect(source).toContain(':data-testid="`course-table-cell-work-entry-${entry.uid}`"\n                                    @click.stop>')
        expect(source).toContain(':data-testid="`course-table-cell-entry-edit-form-${entry.uid}`"\n                                    @click.stop>')
        expect(source).toContain('.course-table-cell-entry--selectable,\n.course-table-cell-entry--selected .course-table-cell-entry-summary {')
        expect(source).toContain('.course-table-cell-entry:focus-visible {')
    })

    it('shows complete work details for a work-derived cell entry', () => {
        const methods = (CourseTable as any).methods
        const entry = { teaching_course_work_id: 31, uid: 'assessment-90' }
        const work = {
            id: 31,
            date_for_all_groups: '2026-09-21',
            description: 'Kapitel 1 bis 4',
            is_group_work: true,
            title: 'Teamprojekt',
            type: 'TW',
            groups: [{
                name: 'Recherche',
                student_ids: [10, 11],
                date: '2026-09-22',
                comments: [
                    { student_id: 10, comment: 'Super gemacht' },
                    { student_id: 11, comment: 'Gute Zusammenarbeit' },
                ],
                grades: [{ student_id: 11, grade: '2' }],
            }],
        }
        const ctx = {
            courseWorks: [work],
            registeredEntryStudentId: 10,
            courseWorkForCellEntry: methods.courseWorkForCellEntry,
            courseWorkGroupIndexForStudent: methods.courseWorkGroupIndexForStudent,
            courseWorkGroupForCellEntry: methods.courseWorkGroupForCellEntry,
            courseWorkStudentComment: methods.courseWorkStudentComment,
            courseWorkStudentGrade: methods.courseWorkStudentGrade,
            compactCourseDateTitle: vi.fn().mockReturnValue('Di., 22.09.'),
            workGroupStudentName: vi.fn().mockReturnValue('Huber, Mia'),
            workGroupStudentComment: methods.workGroupStudentComment,
            workGroupStudentGrade: methods.workGroupStudentGrade,
        }

        expect(methods.courseWorkForCellEntry.call(ctx, entry)).toBe(work)
        expect(methods.courseWorkEntryDateTitle.call(ctx, entry)).toBe('Di., 22.09.')
        expect(methods.courseWorkEntryModeTitle.call(ctx, entry)).toBe('Gruppenarbeit')
        expect(methods.courseWorkEntryAssignmentTitle.call(ctx, entry)).toBe('Recherche · 2 Mitglieder')
        expect(methods.courseWorkEntryOtherGroupMembers.call(ctx, entry)).toEqual([
            {
                comment: 'Gute Zusammenarbeit',
                grade: '2',
                id: '11',
                name: 'Huber, Mia',
            },
        ])

        expect(methods.courseWorkStudentComment.call(ctx, entry, 10)).toBe('Super gemacht')

        work.is_group_work = false
        expect(methods.courseWorkEntryModeTitle.call(ctx, entry)).toBe('Einzelarbeit')
        expect(methods.courseWorkEntryAssignmentTitle.call(ctx, entry)).toBe('')
        expect(methods.courseWorkEntryOtherGroupMembers.call(ctx, entry)).toEqual([])
    })

    it('updates one student evaluation while preserving the other group members values', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            cloneDateWorkGroups: methods.cloneDateWorkGroups,
            courseWorkGroupIndexForStudent: methods.courseWorkGroupIndexForStudent,
            registeredEntryStudentId: 10,
            workGroupStudentComment: methods.workGroupStudentComment,
            workGroupStudentGrade: methods.workGroupStudentGrade,
        }
        const work = {
            id: 31,
            date_for_all_groups: '2026-09-21',
            is_group_work: true,
            groups: [{
                student_ids: [10, 11],
                date: '2026-09-21',
                comment: 'Gemeinsamer Kommentar',
                grade: '2',
                comments: [],
                grades: [],
                points: [],
            }],
        }

        const updatedWork = methods.courseWorkWithStudentEvaluation.call(ctx, work, 10, {
            comment: 'Individueller Kommentar',
            grade: '1',
        })

        expect(updatedWork.groups[0]).toMatchObject({
            grade: null,
            grades: [
                { student_id: 10, grade: '1' },
                { student_id: 11, grade: '2' },
            ],
            comments: [
                { student_id: 10, comment: 'Individueller Kommentar' },
                { student_id: 11, comment: 'Gemeinsamer Kommentar' },
            ],
            use_individual_grades: true,
        })
        expect(work.groups[0].grade).toBe('2')
    })

    it('saves a work-derived evaluation through the work and refreshes the table data', async () => {
        const methods = (CourseTable as any).methods
        const entry = { teaching_course_work_id: 31, uid: 'assessment-90' }
        const work = { id: 31 }
        const updatedWork = { id: 31, groups: [] }
        const response = { data: updatedWork }
        const update = vi.fn().mockResolvedValue(response)
        const applySavedCourseWork = vi.fn()
        const loadCourseTableData = vi.fn().mockResolvedValue(true)
        const resetCourseWorkEntryDrafts = vi.fn()
        const ctx = {
            applySavedCourseWork,
            courseWorkEntryDraft: vi.fn().mockReturnValue({ comment: 'Gut', grade: '1' }),
            courseWorkEntryGradeInputMode: vi.fn().mockReturnValue('none'),
            courseWorkEntrySavingUid: null,
            courseWorkForCellEntry: vi.fn().mockReturnValue(work),
            courseWorkWithStudentEvaluation: vi.fn().mockReturnValue(updatedWork),
            courseWorkStore: { update },
            loadCourseTableData,
            registeredEntryStudentId: 10,
            resetCourseWorkEntryDrafts,
            selected_course: { id: 16 },
            selectedCellEntryUid: entry.uid,
        }

        await methods.saveCourseWorkCellEntry.call(ctx, entry)

        expect(ctx.courseWorkWithStudentEvaluation).toHaveBeenCalledWith(work, 10, {
            comment: 'Gut',
            grade: '',
        })
        expect(update).toHaveBeenCalledWith(updatedWork)
        expect(applySavedCourseWork).toHaveBeenCalledWith(response)
        expect(loadCourseTableData).toHaveBeenCalledWith(16, true)
        expect(resetCourseWorkEntryDrafts).toHaveBeenCalledTimes(1)
        expect(ctx.courseWorkEntrySavingUid).toBeNull()
        expect(ctx.selectedCellEntryUid).toBeNull()
    })

    it('updates an assessment entry in the selected cell', async () => {
        const methods = (CourseTable as any).methods
        const update = vi.fn().mockResolvedValue({ data: { id: 12 } })
        const ctx = {
            canSaveCellEntry: true,
            entrySaving: false,
            entryStore: { update },
            selectedEntryTypeCategory: 'Benotung',
            entryDialog: { courseDate: { date: '2026-03-09' } },
            entryForm: {
                description: 'Verbessert',
                doneDate: null,
                grade: '2',
                id: 12,
                kind: 'assessment',
                dueDate: null,
                type: 'T',
                uid: 'assessment-12',
            },
            normalizeDateKey: methods.normalizeDateKey,
            dateKey: methods.dateKey,
            cancelNewCellEntry: vi.fn(),
            selectedCellEntryUid: 'assessment-12',
        }

        await methods.saveCellEntry.call(ctx)

        expect(update).toHaveBeenCalledWith({
            id: 12,
            type: 'T',
            grade: '2',
            date: '2026-03-09',
            description: 'Verbessert',
        })
        expect(ctx.cancelNewCellEntry).toHaveBeenCalledTimes(1)
        expect(ctx.selectedCellEntryUid).toBeNull()
        expect(ctx.entrySaving).toBe(false)
    })

    it('updates behaviour entries without clearing their due and done dates', async () => {
        const methods = (CourseTable as any).methods
        const update = vi.fn().mockResolvedValue({ data: { id: 7 } })
        const ctx = {
            canSaveCellEntry: true,
            entrySaving: false,
            behaviourEntryStore: { update },
            entryDialog: { courseDate: { date: '2026-03-09' } },
            entryForm: {
                description: 'Erledigt',
                doneDate: '2026-03-11',
                grade: '',
                id: 7,
                kind: 'notification',
                dueDate: '2026-03-10',
                type: 'I',
                uid: 'behaviour-7',
            },
            normalizeDateKey: methods.normalizeDateKey,
            dateKey: methods.dateKey,
            cancelNewCellEntry: vi.fn(),
        }

        await methods.saveCellEntry.call(ctx)

        expect(update).toHaveBeenCalledWith({
            id: 7,
            kind: 'notification',
            type: 'I',
            date: '2026-03-09',
            description: 'Erledigt',
            is_due: true,
            due_date: '2026-03-10',
            is_done: true,
            done_date: '2026-03-11',
        })
        expect(ctx.cancelNewCellEntry).toHaveBeenCalledTimes(1)
    })

    it('deletes entries through the store matching their kind', async () => {
        const methods = (CourseTable as any).methods
        const destroyAssessment = vi.fn().mockResolvedValue(true)
        const destroyBehaviour = vi.fn().mockResolvedValue(true)
        const ctx = {
            deleteEntryDialog: {
                entry: { id: 12, uid: 'assessment-12', kind: 'assessment' },
                open: true,
            },
            entryDeleting: false,
            entryForm: { uid: null },
            entryStore: { destroy: destroyAssessment },
            behaviourEntryStore: { destroy: destroyBehaviour },
            canModifyCellEntry: methods.canModifyCellEntry,
            cancelNewCellEntry: vi.fn(),
        }

        await methods.confirmDeleteCellEntry.call(ctx)

        expect(destroyAssessment).toHaveBeenCalledWith(12)
        expect(ctx.deleteEntryDialog.open).toBe(false)

        ctx.deleteEntryDialog = {
            entry: { id: 7, uid: 'behaviour-7', kind: 'behaviour' },
            open: true,
        }
        await methods.confirmDeleteCellEntry.call(ctx)

        expect(destroyBehaviour).toHaveBeenCalledWith(7)
        expect(ctx.entryDeleting).toBe(false)
    })

    it('keeps the entry dialog target cell selected while the dialog is open', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            entryDialog: {
                courseDate: { id: 7 },
                open: true,
                student: { id: 10 },
            },
        }

        expect(methods.isEntryDialogCellSelected.call(ctx, { id: 10 }, { id: 7 })).toBe(true)
        expect(methods.isEntryDialogCellSelected.call(ctx, { id: 11 }, { id: 7 })).toBe(false)
        expect(methods.isEntryDialogCellSelected.call(ctx, { id: 10 }, { id: 8 })).toBe(false)

        ctx.entryDialog.open = false
        expect(methods.isEntryDialogCellSelected.call(ctx, { id: 10 }, { id: 7 })).toBe(false)
    })

    it('builds bulk attendance maps where present clears absences and absent stores all students', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            sortedSelectedStudents: [
                { id: 10 },
                { id: 11 },
                { id: null },
            ],
        }

        expect(methods.bulkAttendanceMap.call(ctx, true)).toEqual({})
        expect(methods.bulkAttendanceMap.call(ctx, false)).toEqual({
            10: false,
            11: false,
        })
    })

    it('confirms and persists bulk attendance for one day', async () => {
        const methods = (CourseTable as any).methods
        const updateStatus = vi.fn().mockResolvedValue({
            id: 7,
            date: '2026-03-09',
            status: [],
            attendance: { 10: false, 11: false },
        })
        const courseDate = { id: 7, date: '2026-03-09', status: [], attendance: {} }
        const ctx: Record<string, unknown> = {
            bulkAttendanceDialog: {
                courseDate,
                open: true,
                present: false,
            },
            bulkAttendanceSaving: false,
            courseDateStore: { updateStatus },
            selected_course: {
                id: 20,
                course_dates: [courseDate],
            },
            selected_courseDate: null,
            sortedSelectedStudents: [
                { id: 10 },
                { id: 11 },
            ],
            applyUpdatedCourseDate: methods.applyUpdatedCourseDate,
            bulkAttendanceMap: methods.bulkAttendanceMap,
            getAttendanceMap: methods.getAttendanceMap,
            sanitizeAttendanceMap: methods.sanitizeAttendanceMap,
            isAttendancePresentValue: methods.isAttendancePresentValue,
            isAttendanceChecked: methods.isAttendanceChecked,
        }

        await methods.confirmBulkAttendance.call(ctx)

        expect(updateStatus).toHaveBeenCalledWith(7, {
            attendance: { 10: false, 11: false },
            attendance_checked: false,
        })
        expect((ctx.selected_course as any).course_dates[0].attendance).toEqual({ 10: false, 11: false })
        expect(ctx.bulkAttendanceDialog).toEqual({
            courseDate: null,
            open: false,
            present: true,
        })
        expect(ctx.bulkAttendanceSaving).toBe(false)
    })

    it('defaults students to present and stores absent students only', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            getAttendanceMap: methods.getAttendanceMap,
            sanitizeAttendanceMap: methods.sanitizeAttendanceMap,
            isAttendancePresentValue: methods.isAttendancePresentValue,
        }

        const attendance = methods.getAttendanceMap.call(ctx, {
            attendance: {
                10: false,
                11: true,
                s_12: '0',
            },
        })

        expect(attendance).toEqual({ 10: false, 12: false })
        expect(methods.isStudentPresentForCourseDate.call(ctx, { id: 10 }, { attendance })).toBe(false)
        expect(methods.isStudentPresentForCourseDate.call(ctx, { id: 11 }, { attendance })).toBe(true)
    })

    it('calculates student presence percentages from eligible course dates', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            sortedCourseDates: [
                { id: 1, attendance: {} },
                { id: 2, attendance: { 10: false } },
                { id: 3, attendance: {} },
                { id: 4, attendance: { 10: false } },
            ],
            isAttendanceToggleable: (courseDate: { id: number }) => courseDate.id !== 4,
            isStudentPresentForCourseDate(student: { id: number }, courseDate: Record<string, unknown>) {
                return methods.isStudentPresentForCourseDate.call(this, student, courseDate)
            },
            getAttendanceMap: methods.getAttendanceMap,
            sanitizeAttendanceMap: methods.sanitizeAttendanceMap,
            isAttendancePresentValue: methods.isAttendancePresentValue,
        }

        expect(methods.studentPresencePercentage.call(ctx, { id: 10 })).toBe(67)
    })

    it('does not calculate a presence percentage without eligible course dates', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            sortedCourseDates: [{ id: 1, attendance: {} }],
            isAttendanceToggleable: () => false,
        }

        expect(methods.studentPresencePercentage.call(ctx, { id: 10 })).toBeNull()
    })

    it('toggles and persists attendance for an eligible table cell', async () => {
        const methods = (CourseTable as any).methods
        const updateStatus = vi.fn().mockResolvedValue({
            id: 7,
            date: '2026-03-09',
            status: [],
            attendance: { 2098: false },
        })
        const ctx: Record<string, unknown> = {
            courseDateStore: { updateStatus },
            savingAttendanceCells: {},
            selected_course: {
                id: 20,
                course_dates: [
                    { id: 7, date: '2026-03-09', status: [], attendance: {} },
                ],
            },
            selected_courseDate: null,
            isAttendanceToggleable: () => true,
            isAttendanceCellSaving: () => false,
            attendanceCellKey: methods.attendanceCellKey,
            getAttendanceMap: methods.getAttendanceMap,
            sanitizeAttendanceMap: methods.sanitizeAttendanceMap,
            isAttendancePresentValue: methods.isAttendancePresentValue,
            isAttendanceChecked: methods.isAttendanceChecked,
            applyUpdatedCourseDate: methods.applyUpdatedCourseDate,
        }

        await methods.toggleStudentAttendance.call(ctx, { id: 2098 }, { id: 7, date: '2026-03-09', status: [], attendance: {} })

        expect(updateStatus).toHaveBeenCalledWith(7, {
            toggle_student_id: 2098,
            attendance_checked: false,
        })
        expect((ctx.selected_course as any).course_dates[0].attendance).toEqual({ 2098: false })
        expect(ctx.savingAttendanceCells).toEqual({})
    })

    it('targets today, the next upcoming date, or the last date for initial horizontal scrolling', () => {
        const methods = (CourseTable as any).methods
        const ctx: Record<string, unknown> = {
            sortedCourseDates: [
                { id: 1, date: '2026-03-02' },
                { id: 2, date: '2026-03-09' },
                { id: 3, date: '2026-03-16' },
            ],
            normalizeDateKey: methods.normalizeDateKey,
            dateKey: methods.dateKey,
        }

        expect(methods.targetInitialScrollCourseDate.call(ctx, new Date(2026, 2, 9))).toEqual({ id: 2, date: '2026-03-09' })
        expect(methods.targetInitialScrollCourseDate.call(ctx, new Date(2026, 2, 10))).toEqual({ id: 3, date: '2026-03-16' })
        expect(methods.targetInitialScrollCourseDate.call(ctx, new Date(2026, 2, 20))).toEqual({ id: 3, date: '2026-03-16' })
    })

    it('opens the course overview pdf in a new tab', () => {
        const methods = (CourseTable as any).methods
        const open = vi.spyOn(window, 'open').mockImplementation(() => null)
        const context = {
            selected_course: { id: 16 },
        }

        methods.openCourseOverviewPdf.call(context)

        expect(open).toHaveBeenCalledWith(
            '/api/admin/teaching/courses/16/overview_pdf',
            '_blank',
            'noopener',
        )

        const source = readFileSync(
            resolve('resources/js/pages/admin/teaching/overview/components/CourseTable.vue'),
            'utf8',
        )

        expect(source).toContain('data-testid="course-table-overview-pdf-button"')
        expect(source).toContain('title="Kursübersicht als PDF öffnen"')
        expect(source).toContain('class="ml-auto"\n                    color="error"')
        expect(source.indexOf('data-testid="course-table-overview-pdf-button"'))
            .toBeGreaterThan(source.indexOf('<v-btn-toggle v-model="selectedSemester"'))
        expect(source).toContain("import { courseOverviewPdf }")
        expect(source).toContain('@click="openCourseOverviewPdf"')

        open.mockRestore()
    })

    it('renders a designed matrix with date columns and student rows', () => {
        const source = readFileSync(
            resolve('resources/js/pages/admin/teaching/overview/components/CourseTable.vue'),
            'utf8',
        )

        expect(source).toContain('data-testid="course-table"')
        expect(source).toContain('ref="courseTableScroll"')
        expect(source).not.toContain('data-testid="course-table-view-card"')
        expect(source).not.toContain('class="course-table-view-tabs"')
        expect(source).not.toContain('@update:model-value="changeTableView"')
        expect(source).toContain("tableView === 'attendance' ? 'Anwesenheiten' : 'Tabelle'")
        expect(source).toContain('data-testid="course-table-semester-selection"')
        expect(source).toContain('class="w-100"')
        expect(source).toContain('<v-btn-toggle v-model="selectedSemester" mandatory')
        expect(source).toContain('<v-btn :value="1" size="small">1. Sem</v-btn>')
        expect(source).toContain('<v-btn :value="2" size="small">2. Sem</v-btn>')
        expect(source).toContain('<v-btn :value="3" size="small">Sem 1+2</v-btn>')
        expect(source).toContain('position: sticky;')
        expect(source).toContain('class="course-table-title-row"')
        expect(source).toContain('class="course-table-work-row"')
        expect(source).toContain('class="course-table-work-label-content"')
        expect(source).toContain('<span>Arbeiten</span>')
        expect(source).toContain("v-if=\"tableView === 'entries' && hasAssignedCurriculum\"")
        expect(source).toContain('class="course-table-curriculum-row"')
        expect(source).toContain('<span>Curriculum</span>')
        expect(source).toContain('v-for="(content, contentIndex) in displayedCurriculumContentForCourseDate(courseDate)"')
        expect(source).toContain('v-if="contentIndex === 2 && hasAdditionalCurriculumContent(courseDate)"')
        expect(source).toContain('@click="openCurriculumDialog(courseDate)"')
        expect(source).toContain('@keydown.enter.prevent="openCurriculumDialog(courseDate)"')
        expect(source).toContain('@keydown.space.prevent="openCurriculumDialog(courseDate)"')
        expect(source).toContain('<v-dialog v-model="curriculumDialog.open" persistent scrollable max-width="720">')
        expect(source).toContain('data-testid="course-table-curriculum-dialog"')
        expect(source).toContain('v-for="(topic, topicIndex) in curriculumDialogTopics"')
        expect(source).toContain('v-for="unit in topic.units"')
        expect(source).toContain("'course-table-curriculum-dialog-unit--linked': isCurriculumUnitLinkedToDialogDate(topic, unit)")
        expect(source).toContain('@click.stop="unlinkCurriculumUnit(topic, unit)"')
        expect(source).toContain('@click.stop="linkCurriculumUnit(topic, unit)"')
        expect(source).toContain('Lösen')
        expect(source).toContain('Verknüpfen')
        expect(source).toContain(':deep(.course-table-curriculum-dialog-unit)')
        expect(source).toContain('min-height: 32px !important;')
        expect(source).toContain('@click="closeCurriculumDialog"')
        expect(source).toContain('v-for="(segment, segmentIndex) in curriculumContentSegments(content)"')
        expect(source).toMatch(/\.course-table-curriculum-content-item \{[\s\S]*-webkit-line-clamp: 3;[\s\S]*display: -webkit-box;[\s\S]*line-height: 13px;[\s\S]*max-height: 39px;[\s\S]*overflow: hidden;/u)
        expect(source).toMatch(/\.course-table-curriculum-content-segment \{[\s\S]*overflow-wrap: anywhere;[\s\S]*white-space: normal;/u)
        expect(source).toContain('class="course-table-content-row"')
        expect(source).toContain('class="course-table-content-label-content"')
        expect(source).toContain('<span>Stoff</span>')
        expect(source.indexOf('class="course-table-work-row"')).toBeLessThan(source.indexOf('class="course-table-curriculum-row"'))
        expect(source.indexOf('class="course-table-curriculum-row"')).toBeLessThan(source.indexOf('class="course-table-content-row"'))
        expect(source.indexOf('class="course-table-content-row"')).toBeLessThan(source.indexOf('class="course-table-row"'))
        expect(source).toContain('@click="openContentDialog(courseDate)"')
        expect(source).toContain('@keydown.enter.prevent="openContentDialog(courseDate)"')
        expect(source).toContain('@keydown.space.prevent="openContentDialog(courseDate)"')
        expect(source).toContain('class="course-table-content-cell-preview"')
        expect(source).toContain('{{ courseDateContentPreview(courseDate) }}')
        expect(source).toContain('v-if="courseDateContentPreview(courseDate)"')
        expect(source).toContain('content-class="course-table-student-tooltip"')
        expect(source).toContain('{{ compactCourseDateTitle(courseDate) }}')
        expect(source).toContain('<span>Inhalt</span>')
        expect(source).toContain('class="course-table-content-tooltip-html"')
        expect(source).toContain('v-html="courseDateFormattedContent(courseDate)"')
        expect(source).toContain('-webkit-line-clamp: 3;')
        expect(source).toContain('line-height: 13px;')
        expect(source).toContain('max-height: 39px;')
        expect(source).toContain('padding: 0 5px;')
        expect(source).toContain('<v-dialog v-model="contentDialog.open" persistent max-width="720">')
        expect(source).toContain('data-testid="course-table-content-dialog"')
        expect(source).toContain('data-testid="course-table-content-dialog-curriculum"')
        expect(source).toContain('data-testid="course-table-content-dialog-previous-date"')
        expect(source).toContain('@click="navigateContentDialogDate(-1)"')
        expect(source).toContain('data-testid="course-table-content-dialog-next-date"')
        expect(source).toContain('@click="navigateContentDialogDate(1)"')
        expect(source).toMatch(/<v-btn\s+color="deep-purple"\s+data-testid="course-table-content-dialog-curriculum-apply"\s+prepend-icon="mdi-content-copy"\s+size="default"/u)
        expect(source).toContain('v-for="content in contentDialogCurriculumContent"')
        expect(source).toContain('In Stoff übernehmen')
        expect(source).toContain('@click="applyCurriculumContentToContentDialog"')
        expect(source).toContain('<ItsRichTextEditor v-model="contentDialog.content" :disabled="contentSaving" />')
        expect(source).toContain('@click="saveContentDialog()"')
        expect(source).not.toContain('Die Inhaltsverwaltung wird hier später ergänzt.')
        expect(source).toContain('mdi-plus-circle-outline')
        expect(source).toContain('v-for="work in courseWorksForDate(courseDate)"')
        expect(source).toContain('class="course-table-work-summary"')
        expect(source).toContain('<span class="course-table-work-summary-title">')
        expect(source).toContain('{{ work.work.title || work.label }}')
        expect(source).toMatch(/\.course-table-work-summary-title \{[\s\S]*font-weight: 400;/u)
        expect(source).toContain('<v-icon size="13">mdi-clipboard-text</v-icon>')
        expect(source).toContain('@click="openWorkDialog(courseDate)"')
        expect(source).toContain('@keydown.enter.prevent="openWorkDialog(courseDate)"')
        expect(source).toContain('<v-dialog v-model="workDialog.open" persistent max-width="720">')
        expect(source).not.toContain('data-testid="course-table-date-create-work"')
        expect(source).not.toContain('course-table-date-edit-work-${assignment.id}')
        expect(source).toContain('course-table-date-delete-work-${assignment.id}')
        expect(source).toContain('class="course-table-date-work-item cursor-pointer"')
        expect(source).toContain(':data-testid="`course-table-date-work-mode-${assignment.work.id}`"')
        expect(source).toContain("assignment.isGroupWork ? 'deep-purple' : 'primary'")
        expect(source).toContain("assignment.isGroupWork ? 'mdi-account-group' : 'mdi-account-outline'")
        expect(source).toContain("{{ assignment.isGroupWork ? 'Gruppenarbeit' : 'Einzelarbeit' }}")
        expect(source).toContain("v-if=\"assignment.isGroupWork && assignment.scope !== 'Gruppenarbeit'\"")
        expect(source).toContain('@click="startEditingDateWork(assignment.work)"')
        expect(source).toContain('@keydown.enter.self.prevent="startEditingDateWork(assignment.work)"')
        expect(source).toContain('@keydown.space.self.prevent="startEditingDateWork(assignment.work)"')
        expect(source).toContain('@click.stop="openDeleteWorkDialog(assignment.work)"')
        expect(source).toContain('<v-dialog v-model="deleteWorkDialog.open" persistent max-width="460">')
        expect(source).toContain('<section v-if="!workDialogFormOpen">')
        expect(source).toContain('<v-card-actions v-if="!workDialogFormOpen">')
        expect(source).toContain('v-model="workDialogTab"')
        expect(source).toContain(':disabled="workDialogTypeEditing || workDialogModeEditing"')
        expect(source).toContain('<v-tab value="work">Arbeit</v-tab>')
        expect(source).toContain('<v-tab v-if="!workDialogForm.is_group_work" value="students">Schüler:innen</v-tab>')
        expect(source).toContain('<v-tab v-if="workDialogForm.is_group_work" value="groups">Gruppen</v-tab>')
        expect(source).toContain('class="course-table-work-tab-panels"')
        expect(source).toContain('<div v-show="workDialogTab === \'work\'">')
        expect(source).toContain('data-testid="course-table-work-students-panel"')
        expect(source).toContain('v-show="workDialogTab === \'students\'"')
        expect(source).toContain('v-for="row in individualWorkGradeRows"')
        expect(source).toContain('setIndividualWorkStudentComment(row.studentId, $event)')
        expect(source).toContain('v-for="item in availableWorkGradeItems"')
        expect(source).toContain('toggledCourseWorkGrade(row.grade, item.value)')
        expect(source).toContain('v-if="workDialogForm.is_group_work"')
        expect(source).toContain('v-show="workDialogTab === \'groups\'"')
        expect(source).toContain('v-for="(group, groupIndex) in workDialogGroups"')
        expect(source).toContain('v-for="student in unassignedWorkGroupStudents"')
        expect(source).toContain('>Nicht zugeordnet</strong>')
        expect(source).toContain('@dragstart="startUnassignedWorkGroupStudentDrag($event, student.value)"')
        expect(source.indexOf('>Nicht zugeordnet</strong>')).toBeLessThan(source.indexOf('v-for="(group, groupIndex) in workDialogGroups"'))
        expect(source).toContain('{{ workGroupMemberCountTitle(group) }}')
        expect(source).toContain('workDialogGroupDateEditingIndex === groupIndex')
        expect(source).toContain('v-model:menu="workDialogGroupDateMenuOpen"')
        expect(source).toContain('@click.stop="beginWorkDialogGroupDateEditing(groupIndex)"')
        expect(source).toContain('@update:menu="handleWorkDialogGroupDateMenu"')
        expect(source).toContain('@update:model-value="applyWorkDialogGroupDate(groupIndex, $event)"')
        expect(source).toContain('label="Gruppendatum"')
        expect(source).toContain('prepend-icon="mdi-calendar-edit"')
        expect(source).toContain('v-for="(studentNameValue, studentIndex) in workGroupStudentNames(group)"')
        expect(source).toContain('class="pa-3 course-table-work-group-card cursor-pointer"')
        expect(source).toContain('@click="openWorkDialogGroupDetails(groupIndex)"')
        expect(source).toContain("'course-table-work-group-card--drop-target':")
        expect(source).toContain('@drop.prevent="dropWorkGroupStudent(groupIndex)"')
        expect(source).toContain('class="course-table-work-group-student-chip"')
        expect(source).toContain('@dragstart="startWorkGroupStudentDrag($event, groupIndex, group.student_ids[studentIndex])"')
        expect(source).toContain('@dragstart.stop="startWorkGroupStudentDrag($event, groupIndex, internalItem.value)"')
        expect(source).toContain('@click:close="removeWorkGroupStudent(groupIndex, group.student_ids[studentIndex])"')
        expect(source).toContain('@click:close="removeWorkGroupStudent(groupIndex, internalItem.value)"')
        expect(source).toContain('@click="addWorkDialogGroup"')
        expect(source).toContain('v-model="group.student_ids"')
        expect(source).toContain(':items="workGroupStudentItems(groupIndex)"')
        expect(source).toContain('@click.stop="removeWorkDialogGroup(groupIndex)"')
        expect(source).toContain('<v-dialog v-model="workDialogGroupDetails.open" persistent max-width="720">')
        expect(source).toContain('v-model="workDialogGroupDetailsGroup.comment"')
        expect(source).toContain('label="Beschreibung"')
        expect(source).toContain('v-for="row in workGroupGradeRows(workDialogGroupDetailsGroup)"')
        expect(source).toContain("v-if=\"availableWorkGradeInputMode === 'fixed'\"")
        expect(source).toContain("v-else-if=\"availableWorkGradeInputMode === 'free'\"")
        expect(source).toContain('Keine Bewertung vorgesehen.')
        expect(source).toContain('toggledCourseWorkGrade(row.grade, item.value)')
        expect(source).toContain('label="Kommentar"')
        expect(source).toContain('setWorkGroupStudentComment(workDialogGroupDetails.groupIndex, row.studentId, $event)')
        expect(source.indexOf(
            'setWorkGroupStudentComment(workDialogGroupDetails.groupIndex, row.studentId, $event)',
        )).toBeLessThan(source.indexOf(
            'setWorkGroupStudentGrade(',
        ))
        expect(source).toContain(
            'grid-template-columns: minmax(0, 1fr) minmax(240px, 2fr) minmax(100px, 130px);',
        )
        expect(source).toContain('data-testid="course-table-date-random-groups"')
        expect(source).not.toContain('data-testid="course-table-date-edit-random-groups"')
        expect(source).toContain('prepend-icon="mdi-shuffle-variant"')
        expect(source).toContain('v-if="hasSavedRandomGroupConfiguration"')
        expect(source).toContain('{{ workDialogForm.group_size }} Mitglieder pro Gruppe')
        expect(source).not.toContain('title="Zufällige Gruppen bearbeiten"')
        expect(source).toContain('@click="openRandomGroupsDialog"')
        expect(source).toContain('v-if="hasUnassignedWorkGroupStudents"')
        expect(source).toContain('>Alles Ok</span>')
        expect(source).toContain('v-model="randomGroupsDialog.open"')
        expect(source).toContain('v-model.number="randomGroupsDialog.groupSize"')
        expect(source).toContain('label="Mitglieder pro Gruppe"')
        expect(source).toContain(':max="maxRandomGroupSize"')
        expect(source).toContain('höchstens ${maxRandomGroupSize} Mitglieder')
        expect(source).toContain('Gruppengrößen:')
        expect(source).toContain('{{ randomGroupSizePreview }}')
        expect(source).toContain('this.generateRandomWorkDialogGroups(groupSize)')
        expect(source).toContain('Erstellen')
        expect(source).toContain('Zufällige Gruppen')
        expect(source).toContain('Neue Gruppe')
        expect(source).toContain('Für diese Gruppenarbeit sind noch keine Gruppen definiert.')
        expect(source).not.toContain("{{ workDialogForm.id ? 'Arbeit bearbeiten' : 'Neue Arbeit erstellen' }}")
        expect(source).toContain('v-if="workDialogForm.id && !workDialogTypeEditing"')
        expect(source).toContain('data-testid="course-table-date-edit-work-type"')
        expect(source).toMatch(/<v-chip\s+data-testid="course-table-date-edit-work-type"/)
        expect(source).not.toMatch(/<v-btn\s+data-testid="course-table-date-edit-work-type"/)
        expect(source).toContain('{{ selectedDateWorkTypeTitle }}')
        expect(source).toContain('@click="beginDateWorkTypeEditing"')
        expect(source).toContain('@click="selectDateWorkType(item.value)"')
        expect(source).toContain('@click="cancelDateWorkTypeEditing">Abbrechen</v-btn>')
        expect(source).toContain('@click="confirmDateWorkTypeEditing">OK</v-btn>')
        expect(source).toContain(':disabled="workSaving || workDialogTypeEditing || workDialogModeEditing"')
        expect(source).toContain('data-testid="course-table-date-edit-work-mode"')
        expect(source).toMatch(/<v-chip\s+data-testid="course-table-date-edit-work-mode"/)
        expect(source).not.toMatch(/<v-btn\s+data-testid="course-table-date-edit-work-mode"/)
        expect(source).toContain('class="course-table-work-meta-row mb-3"')
        expect(source).toContain('workDialogTypeEditing || workDialogModeEditing')
        expect(source).toContain('data-testid="course-table-date-create-work-mode"')
        expect(source).toContain('v-model="workDialogForm.is_group_work"')
        expect(source).toContain('<div v-else-if="!workDialogModeEditing"')
        expect(source).toContain('<div v-if="!workDialogTypeEditing">')
        expect(source).toContain('{{ selectedDateWorkModeTitle }}')
        expect(source).toContain('@click="beginDateWorkModeEditing"')
        expect(source).toContain('v-model="workDialogModeDraft"')
        expect(source).toContain('@click="cancelDateWorkModeEditing">Abbrechen</v-btn>')
        expect(source).toContain('@click="confirmDateWorkModeEditing">OK</v-btn>')
        expect(source).toContain('<v-btn :value="false">Einzelarbeit</v-btn>')
        expect(source).toContain('<v-btn :value="true">Gruppenarbeit</v-btn>')
        expect(source).not.toContain('Gruppen und gruppenspezifische Termine bleiben unverändert.')
        expect(source).toContain('data-testid="course-table-date-edit-work-date"')
        expect(source).toContain('@click="beginWorkDialogDateEditing"')
        expect(source).toContain('@keydown.enter.prevent="beginWorkDialogDateEditing"')
        expect(source).not.toContain('title="Datum ändern"')
        expect(source).toContain('data-testid="course-table-date-work-date-input"')
        expect(source).toContain('@update:model-value="applyWorkDialogDate"')
        expect(source).toContain('<template #day="{ item, props }">')
        expect(source).toContain("workDialogCourseDateKeys.includes(item.isoDate) ? 'primary' : props.color")
        expect(source).toContain('data-testid="course-table-date-edit-work-finish-until"')
        expect(source).toMatch(/<v-chip\s+v-else-if="workDialogFormOpen"\s+data-testid="course-table-date-edit-work-finish-until"/)
        expect(source).toContain('Fertig bis {{ workDialogFinishDateTitle }}')
        expect(source).toContain('data-testid="course-table-date-work-duration"')
        expect(source).toContain('prepend-icon="mdi-timer-sand"')
        expect(source).toContain('{{ workDialogDurationLabel }}')
        expect(source).toContain(':data-testid="`course-table-date-work-start-${assignment.work.id}`"')
        expect(source).toContain('v-if="assignment.work.date_for_all_groups"')
        expect(source).toContain('{{ compactCourseDateTitle({ date: assignment.work.date_for_all_groups }) }}')
        expect(source).toContain(':data-testid="`course-table-date-work-duration-${assignment.work.id}`"')
        expect(source).toContain('{{ courseWorkDurationLabel(assignment.work) }}')
        expect(source).toContain('@click="beginWorkDialogFinishDateEditing"')
        expect(source).toContain('@keydown.enter.prevent="beginWorkDialogFinishDateEditing"')
        expect(source).toContain('data-testid="course-table-date-work-finish-until-input"')
        expect(source).toContain('v-if="workDialogFormOpen && workDialogFinishDateEditing"')
        expect(source).toContain('@update:model-value="applyWorkDialogFinishDate"')
        const workDialogTitleEnd = source.indexOf(
            '</v-card-title>',
            source.indexOf('<v-dialog v-model="workDialog.open"'),
        )
        expect(source.indexOf('data-testid="course-table-date-work-finish-until-input"'))
            .toBeLessThan(workDialogTitleEnd)
        expect(source.indexOf('data-testid="course-table-date-edit-work-date"'))
            .toBeLessThan(source.indexOf('data-testid="course-table-date-work-duration"'))
        expect(source.indexOf('data-testid="course-table-date-work-duration"'))
            .toBeLessThan(source.indexOf('data-testid="course-table-date-edit-work-finish-until"'))
        expect(source).toContain('@click="saveDateWork"')
        expect(source).toContain('@click="confirmDeleteDateWork"')
        expect(source.indexOf('class="course-table-title-row"')).toBeLessThan(source.indexOf('class="course-table-work-row"'))
        expect(source.indexOf('class="course-table-work-row"')).toBeLessThan(source.indexOf('v-for="(student, studentIndex) in sortedSelectedStudents"'))
        expect(source).toContain('class="course-table-date-attendance-actions"')
        expect(source).toContain('class="course-table-date-attendance-action"')
        expect(source).toContain('@click.stop="openBulkAttendanceDialog(courseDate, true)"')
        expect(source).toContain('@click.stop="openBulkAttendanceDialog(courseDate, false)"')
        expect(source).toContain('<v-dialog v-model="bulkAttendanceDialog.open" persistent max-width="460">')
        expect(source).toContain('@click="confirmBulkAttendance"')
        expect(source).toContain(':data-course-date-key="courseDateScrollKey(courseDate)"')
        expect(source).toContain('class="course-table-student-col"')
        expect(source).toContain('{{ sortedSelectedStudents.length }} Schüler:innen')
        expect(source).toContain('class="course-table-date-col"')
        expect(source).toContain("'course-table-date-col--free': isFreeCourseDate(courseDate)")
        expect(source).toContain('v-else-if="studentIndex === 0"')
        expect(source).toContain(':rowspan="sortedSelectedStudents.length"')
        expect(source).toContain('class="course-table-free-reason"')
        expect(source).toContain('.course-table thead th.course-table-date-col--free,')
        expect(source).toContain('vertical-align: top;')
        expect(source).toContain('top: 8px;')
        expect(source).toContain('writing-mode: vertical-rl;')
        expect(source).toContain('text-orientation: mixed;')
        expect(source).toContain('font-size: 1.24rem;')
        expect(source).toContain('letter-spacing: 0.08em;')
        expect(source).toContain('opacity: 0.42;')
        expect(source).toContain('.course-table-entry-cell--free-reason {\n    overflow: hidden;')
        expect(source).toContain('width: 88px;')
        expect(source).toContain('position: absolute;')
        expect(source).toContain('max-height: calc(100% - 16px);')
        expect(source).not.toContain('transform: rotate(90deg);')
        expect(source).toContain('v-for="courseDate in sortedCourseDates"')
        expect(source).toContain('v-for="(student, studentIndex) in sortedSelectedStudents"')
        expect(source).toContain("'course-table-entry-cell--interactive': tableView === 'entries'")
        expect(source).toContain("'course-table-entry-cell--absent': tableView === 'entries' && !isStudentPresentForCourseDate(student, courseDate)")
        expect(source).toContain('.course-table-entry-cell--absent {')
        expect(source).toContain("v-if=\"tableView === 'entries' && !isStudentPresentForCourseDate(student, courseDate)\"")
        expect(source).toContain('class="course-table-entry-cell-absent-marker"')
        expect(source).toContain('aria-label="Abwesend"')
        expect(source).toContain('.course-table-entry-cell-absent-marker {')
        expect(source).toContain('right: 3px;')
        expect(source).toContain('top: 3px;')
        expect(source).not.toContain('background: rgba(var(--v-theme-error), 0.14) !important;')
        expect(source).toContain('data-testid="course-table-entry-cell-badges"')
        expect(source).toContain("v-if=\"tableView === 'entries' && entriesForCell(student, courseDate).length\"")
        expect(source).toContain('data-testid="course-table-entry-cell-supplementary-row"')
        expect(source).toContain('v-for="entry in supplementaryEntriesForCell(student, courseDate)"')
        expect(source).toContain('data-testid="course-table-entry-cell-performance-row"')
        expect(source).toContain('v-for="entry in compactPerformanceEntriesForCell(student, courseDate)"')
        expect(source).toContain('{{ compactCellEntryType(entry) }}<template v-if="entryTypeHasProperties(entry)">:&nbsp;')
        expect(source).toContain("'font-weight-bold': compactCellEntryGrade(entry)")
        expect(source).toContain("'course-table-entry-cell-grade--missing': !compactCellEntryGrade(entry)")
        expect(source).toContain("{{ compactCellEntryGrade(entry) || 'N/A' }}")
        expect(source).toContain('v-if="entryExpectsProperty(entry)"')
        expect(source).not.toContain("{{ compactCellEntryGrade(entry) || 'NA' }}")
        expect(source).toMatch(/\.course-table-entry-cell-badge \{[\s\S]*font-weight: 400;/u)
        expect(source).toMatch(/\.course-table-entry-cell-grade--missing \{[\s\S]*color: rgb\(var\(--v-theme-error\)\);[\s\S]*font-weight: 400;/u)
        expect(source).not.toContain('class="course-table-entry-cell-comment"')
        expect(source).not.toContain('{{ entry.description }}')
        expect(source).toContain('content-class="course-table-entry-tooltip"')
        expect(source).toContain('v-for="detail in cellEntryHoverItems(student, courseDate)"')
        expect(source).toContain('Note: {{ detail.grade }}')
        expect(source).toContain('<span>Kommentar:</span> {{ detail.comment }}')
        expect(source.indexOf('data-testid="course-table-entry-cell-supplementary-row"'))
            .toBeLessThan(source.indexOf('data-testid="course-table-entry-cell-performance-row"'))
        expect(source).not.toContain('{{ compactCellEntryLabel(entry) }}')
        expect(source).toContain("'course-table-entry-cell--selected': isEntryDialogCellSelected(student, courseDate)")
        expect(source).toContain('<v-tooltip')
        expect(source).toContain('activator="parent"')
        expect(source).toContain('content-class="course-table-work-tooltip"')
        expect(source).toContain('v-for="assignment in courseWorksForDate(courseDate)"')
        expect(source).toContain('class="course-table-work-summary-count"')
        expect(source).toContain('{{ work.affectedStudentCount }}')
        expect(source).toContain('class="course-table-work-tooltip-type"')
        expect(source).toContain('color="grey-lighten-2"')
        expect(source).toContain('{{ assignment.scope }}')
        expect(source).toContain('Keine Arbeit eingetragen. Klicken, um eine Arbeit anzulegen.')
        expect(source).toContain('@click="openEntryDialog(student, courseDate)"')
        expect(source).toContain('@keydown.enter.prevent="openEntryDialog(student, courseDate)"')
        expect(source).toContain('<v-dialog v-model="entryDialog.open" persistent max-width="860">')
        expect(source).not.toContain('data-testid="course-table-cell-entry-work-periods"')
        expect(source).toContain('course-table-cell-entry-work-period-${entry.uid}')
        expect(source).toContain("v-if=\"entry.source === 'course_work' && courseWorkEntryPeriod(entry)\"")
        expect(source).toContain("courseWorkForCellEntry(entry).is_group_work ? 'deep-purple' : 'primary'")
        expect(source).toContain("courseWorkForCellEntry(entry).is_group_work ? 'mdi-account-group' : 'mdi-account-outline'")
        expect(source).toContain('{{ courseWorkEntryModeTitle(entry) }}')
        expect(source).toContain('<strong>Beginn/Ende:</strong> {{ courseWorkEntryPeriod(entry).startDateTitle }}')
        expect(source).toContain('<strong>Beginn:</strong> {{ courseWorkEntryPeriod(entry).startDateTitle }}')
        expect(source).toContain('<strong>Ende:</strong> {{ courseWorkEntryPeriod(entry).finishDateTitle }}')
        expect(source).toContain('<strong>Dauer:</strong> {{ courseWorkEntryPeriod(entry).durationLabel }}')
        expect(source).toContain('data-testid="course-table-cell-entry-list"')
        expect(source).toContain('course-table-cell-work-entry-${entry.uid}')
        expect(source).toContain('course-table-cell-work-comment-${entry.uid}')
        expect(source).toContain('course-table-cell-work-grade-${entry.uid}')
        expect(source).toContain('course-table-cell-work-save-${entry.uid}')
        expect(source).toContain('course-table-cell-work-open-${entry.uid}')
        expect(source).toContain("v-if=\"courseWorkEntryGradeInputMode(entry) === 'fixed'\"")
        expect(source).toContain("v-else-if=\"courseWorkEntryGradeInputMode(entry) === 'free'\"")
        expect(source).toContain('Für diesen Eintragstyp ist keine Bewertung vorgesehen.')
        expect(source).toContain('Weitere Gruppenmitglieder')
        expect(source).toContain('v-for="member in courseWorkEntryOtherGroupMembers(entry)"')
        expect(source).toContain('content-class="course-table-group-member-tooltip"')
        expect(source).toContain('<template #activator="{ props }">')
        expect(source).toContain('v-bind="props"')
        expect(source).toContain('{{ member.name }}')
        expect(source).toContain("{{ member.grade || 'Keine Note' }}")
        expect(source).toContain("{{ member.comment || 'Kein Kommentar' }}")
        expect(source).toContain("updateCourseWorkEntryDraft(entry, 'comment', $event)")
        expect(source).toContain('v-for="item in courseWorkEntryGradeItems(entry)"')
        expect(source).toContain('toggledCourseWorkGrade(courseWorkEntryDraft(entry).grade, item.value)')
        expect(source).toContain('@click="saveCourseWorkCellEntry(entry)"')
        expect(source).toContain('@click="openCourseWorkFromCellEntry(entry)"')
        expect(source).toContain('@click="toggleCellEntry(entry)">')
        expect(source).not.toContain('course-table-cell-edit-entry-${entry.uid}')
        expect(source).not.toContain('icon="mdi-pencil"')
        expect(source).toContain('course-table-cell-delete-entry-${entry.uid}')
        expect(source).toContain('@click="openDeleteEntryDialog(entry)"')
        expect(source.indexOf('course-table-cell-delete-entry-${entry.uid}')).toBeGreaterThan(
            source.indexOf('course-table-cell-entry-edit-form-${entry.uid}'),
        )
        expect(source).toContain('course-table-cell-entry-card-${entry.uid}')
        expect(source).toContain('course-table-cell-entry-edit-form-${entry.uid}')
        expect(source).toContain('v-if="cellEntryListComment(entry)"')
        expect(source).toContain('mdi-comment-text-outline')
        expect(source).toContain('.course-table-cell-entry-list-comment {')
        expect(source).toContain("'course-table-cell-entry--selected': selectedCellEntryUid === entry.uid")
        expect(source).toContain('@click="toggleCellEntry(entry)"')
        expect(source).toContain('@keydown.enter.self.prevent="toggleCellEntry(entry)"')
        expect(source).toContain('v-if="isCellEntryExpanded(entry) && entry.source === \'course_work\'')
        expect(source).toContain('v-if="entryFormOpen && entryForm.uid === entry.uid"')
        expect(source).toContain('<section v-if="!entryForm.id">')
        expect(source).toContain('In dieser Zelle sind noch keine Einträge vorhanden.')
        expect(source).toContain('data-testid="course-table-cell-add-entry"')
        expect(source).toContain('v-if="!entryFormOpen && !selectedCellEntryUid"')
        expect(source).toContain('Neuen Eintrag hinzufügen')
        expect(source).toContain('data-testid="course-table-cell-entry-form"')
        expect(source).toContain('<v-card-actions v-if="!entryFormOpen && !selectedCellEntryUid">')
        expect(source).toContain('<v-dialog v-model="deleteEntryDialog.open" persistent max-width="460">')
        expect(source).toContain('@click="confirmDeleteCellEntry"')
        expect(source).toContain('.course-table-entry-cell--interactive:hover,')
        expect(source).toContain('.course-table-entry-cell--selected {')
        expect(source).toContain('class="course-table-attendance-marker"')
        expect(source).toContain("v-if=\"tableView === 'attendance' && isAttendanceToggleable(courseDate)\"")
        expect(source).toContain('@click.stop="toggleStudentAttendance(student, courseDate)"')
        expect(source).toContain('font-weight: 500;')
        expect(source).toContain('class="course-table-student-subline"')
        expect(source).toContain('studentSexIcon(student)')
        expect(source).toContain('studentFirstName(student)')
        expect(source).toContain('class="course-table-student-class"')
        expect(source).toContain('class="course-table-student-name"')
        expect(source).toContain('class="course-table-student-comment"')
        expect(source).toContain('{{ studentComment(student) }}')
        expect(source).toContain('content-class="course-table-student-tooltip"')
        expect(source).toContain('v-for="detail in studentTooltipDetails(student)"')
        expect(source).toContain('{{ studentName(student) }}')
        expect(source).toContain('Keine weiteren Angaben.')
        expect(source).toContain('class="course-table-presence-percentage"')
        expect(source).toContain('studentPresencePercentage(student)')
        expect(source).toContain('justify-content: space-between;')
        expect(source).toContain('text-align: right;')
        expect(source).toContain('class="course-table-date-weekday"')
        expect(source).toContain('class="course-table-date-title"')
        expect(source).toContain('text-align: center;')
        expect(source).toContain('table-layout: auto;')
        expect(source).toContain('white-space: nowrap;')
        expect(source).toContain('.course-table-row:nth-child(even) td,')
        expect(source).toContain('padding: 8px 7px;')
        expect(source).toContain('width: 88px;')
        expect(source).toContain('scrollContainer.scrollLeft = Math.max(')
        expect(source).not.toContain('studentEmailText(student)')
        expect(source).not.toContain('Sem 1:')
        expect(source).not.toContain('course-table-entry-placeholder')
        expect(source).not.toContain('height: 38px;')
        expect(source).not.toContain('height: 72px;')
        expect(source).not.toContain('courseDateStatusItems')
        expect(source).not.toContain('course-table-date-statuses')
        expect(source).not.toContain('emptyCellTitle')
        expect(source).not.toContain('width: 230px;')
        expect(source).not.toContain('width: 190px;')
        expect(source).not.toContain('course-table-section-title')
    })

    it('opens work editing through the work card without a separate pencil action', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/components/CourseTable.vue', 'utf8')
        )

        expect(source).toContain('@click="startEditingDateWork(assignment.work)"')
        expect(source).toContain('{{ assignment.scope }}')
        expect(source).not.toContain('class="text-caption text-medium-emphasis mt-1">{{ assignment.label }}')
        expect(source).not.toContain('course-table-date-edit-work-${assignment.id}')
        expect(source).toContain('course-table-date-delete-work-${assignment.id}')
    })

    it('counts one fixed student column plus one column per course date', () => {
        const ctx = {
            sortedCourseDates: [
                { id: 1 },
                { id: 2 },
                { id: 3 },
            ],
        }

        expect((CourseTable as any).computed.tableColumnCount.call(ctx)).toBe(4)
    })
})
