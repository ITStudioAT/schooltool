import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import CourseTable from '@/pages/admin/teaching/overview/components/CourseTable.vue'

describe('CourseTable', () => {
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

    it('shows works at their corresponding dates including group-specific dates', () => {
        const methods = (CourseTable as any).methods
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
            ],
        }
        Object.assign(ctx, methods)

        const assignments = methods.courseWorksForDate.call(ctx, { date: '2026-05-16' })

        expect(assignments.map((assignment: Record<string, any>) => assignment.label)).toEqual([
            'GA: Projekt (Gr. 1, 3)',
            'SA: Schularbeit (Einzelarbeit)',
        ])
        expect(methods.courseWorksForDate.call(ctx, { date: '2026-05-18' })).toEqual([])
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
            workDialogFormOpen: true,
            workSaving: false,
        }

        methods.beginWorkDialogDateEditing.call(ctx)

        expect(ctx.workDialogDateEditing).toBe(true)
    })

    it('edits only the selected work-group date', () => {
        const methods = (CourseTable as any).methods
        const ctx: Record<string, any> = {
            dateKey: methods.dateKey,
            normalizeDateKey: methods.normalizeDateKey,
            workDialogGroupDateEditingIndex: null,
            workDialogGroups: [
                { date: '2026-05-16', student_ids: [1, 2] },
                { date: '2026-05-17', student_ids: [3, 4] },
            ],
            workSaving: false,
        }

        methods.beginWorkDialogGroupDateEditing.call(ctx, 1)
        expect(ctx.workDialogGroupDateEditingIndex).toBe(1)

        methods.applyWorkDialogGroupDate.call(ctx, 1, new Date(2026, 4, 23))
        expect(ctx.workDialogGroups.map((group: { date: string }) => group.date)).toEqual([
            '2026-05-16',
            '2026-05-23',
        ])
        expect(ctx.workDialogGroupDateEditingIndex).toBeNull()
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

    it('offers the configured grades for the selected work type', () => {
        const availableWorkGradeItems = (CourseTable as any).computed.availableWorkGradeItems
        const items = availableWorkGradeItems.call({
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

    it('creates a work for the date selected in the dialog', async () => {
        const methods = (CourseTable as any).methods
        const store = vi.fn().mockResolvedValue({ data: { id: 12 } })
        const loadCourseWorks = vi.fn().mockResolvedValue(true)
        const cancelDateWorkForm = vi.fn()
        const ctx = {
            canSaveDateWork: true,
            courseWorkStore: { store },
            loadCourseWorks,
            cancelDateWorkForm,
            selected_course: { id: 20 },
            workDialog: { courseDate: { date: '2026-05-16' }, open: true },
            workDialogForm: {
                date_for_all_groups: '',
                description: 'Kapitel 4',
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
        expect(loadCourseWorks).toHaveBeenCalledTimes(1)
        expect(cancelDateWorkForm).toHaveBeenCalledTimes(1)
        expect(ctx.workSaving).toBe(false)
    })

    it('updates a work while preserving its group assignments', async () => {
        const methods = (CourseTable as any).methods
        const update = vi.fn().mockResolvedValue({ data: { id: 12 } })
        const groups = [{ date: '2026-05-16', student_ids: [1, 2], grade: '2' }]
        const ctx = {
            canSaveDateWork: true,
            courseWorkStore: { update },
            loadCourseWorks: vi.fn().mockResolvedValue(true),
            cancelDateWorkForm: vi.fn(),
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
    })

    it('persists changing a group work to an individual work', async () => {
        const methods = (CourseTable as any).methods
        const update = vi.fn().mockResolvedValue({ data: { id: 12 } })
        const groups = [{ date: '2026-05-16', student_ids: [1, 2], grade: '2' }]
        const ctx = {
            canSaveDateWork: true,
            courseWorkStore: { update },
            loadCourseWorks: vi.fn().mockResolvedValue(true),
            cancelDateWorkForm: vi.fn(),
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

    it('shows every group student with their grade and switches safely to individual grading', () => {
        const methods = (CourseTable as any).methods
        const group = {
            student_ids: [1488, 1489],
            grade: '2',
            grades: [],
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
            workGroupStudentGrade: methods.workGroupStudentGrade,
            workGroupStudentName: methods.workGroupStudentName,
        }

        expect(methods.workGroupGradeRows.call(ctx, group)).toEqual([
            { grade: '2', studentId: 1488, studentName: 'Muster, Anna' },
            { grade: '2', studentId: 1489, studentName: 'Beispiel, Ben' },
        ])

        methods.setWorkGroupStudentGrade.call(ctx, 0, 1489, '1')

        expect(group).toMatchObject({
            grade: '',
            grades: [
                { student_id: 1488, grade: '2' },
                { student_id: 1489, grade: '1' },
            ],
            use_individual_grades: true,
        })
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

    it('switches between attendance and entries and stores the view in the URL', () => {
        const methods = (CourseTable as any).methods
        const initialData = (CourseTable as any).data()
        const replace = vi.fn().mockResolvedValue(undefined)
        const loadCourseEntries = vi.fn().mockResolvedValue(undefined)
        const ctx = {
            tableView: initialData.tableView,
            loadCourseEntries,
            $route: {
                path: '/admin/teaching',
                query: {
                    course: '5',
                    panel: 'table',
                },
            },
            $router: { replace },
        }

        expect(ctx.tableView).toBe('attendance')

        methods.changeTableView.call(ctx, 'entries')

        expect(ctx.tableView).toBe('entries')
        expect(loadCourseEntries).toHaveBeenCalledTimes(1)
        expect(replace).toHaveBeenLastCalledWith({
            path: '/admin/teaching',
            query: {
                course: '5',
                panel: 'table',
                view: 'entries',
            },
        })

        methods.changeTableView.call(ctx, 'attendance')

        expect(ctx.tableView).toBe('attendance')
        expect(replace).toHaveBeenLastCalledWith({
            path: '/admin/teaching',
            query: {
                course: '5',
                panel: 'table',
                view: 'attendance',
            },
        })
    })

    it('restores the table view from the URL', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            tableView: 'attendance',
        }

        methods.restoreTableView.call(ctx, 'entries')
        expect(ctx.tableView).toBe('entries')

        methods.restoreTableView.call(ctx, 'plain')
        expect(ctx.tableView).toBe('entries')

        methods.restoreTableView.call(ctx, 'attendance')
        expect(ctx.tableView).toBe('attendance')

        methods.restoreTableView.call(ctx, undefined)
        expect(ctx.tableView).toBe('attendance')
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
        }

        const entries = methods.entriesForCell.call(ctx, { id: 10, user_id: 10 }, { date: '2026-03-09' })

        expect(entries.map((entry: { uid: string }) => entry.uid)).toEqual([
            'assessment-2',
            'assessment-1',
            'behaviour-5',
            'behaviour-6',
        ])
    })

    it('separates behaviour and other entries from performance entries in table cells', () => {
        const methods = (CourseTable as any).methods
        const entries = [
            { uid: 'assessment-1', kind: 'assessment' },
            { uid: 'behaviour-2', kind: 'behaviour' },
            { uid: 'behaviour-3', kind: 'notification' },
            { uid: 'assessment-4', kind: 'assessment' },
        ]
        const ctx = {
            entriesForCell: vi.fn().mockReturnValue(entries),
        }

        expect(methods.supplementaryEntriesForCell.call(ctx, { id: 10 }, { id: 7 }))
            .toEqual([entries[1], entries[2]])
        expect(methods.performanceEntriesForCell.call(ctx, { id: 10 }, { id: 7 }))
            .toEqual([entries[0], entries[3]])
    })

    it('compacts performance entries with the same type into one cell chip', () => {
        const methods = (CourseTable as any).methods
        const entries = [
            { uid: 'assessment-1', kind: 'assessment', type: 'MA', grade: '-' },
            { uid: 'assessment-2', kind: 'assessment', type: 'MA', grade: '+' },
            { uid: 'assessment-3', kind: 'assessment', type: 'MA', effective_grade: '+' },
            { uid: 'assessment-4', kind: 'assessment', type: 'SA', grade: '2' },
        ]
        const ctx = {
            performanceEntriesForCell: vi.fn().mockReturnValue(entries),
            sortedCompactEntryGrades: methods.sortedCompactEntryGrades,
        }

        const compactEntries = methods.compactPerformanceEntriesForCell.call(ctx, { id: 10 }, { id: 7 })

        expect(compactEntries).toHaveLength(2)
        expect(compactEntries.map((entry: Record<string, unknown>) => methods.compactCellEntryLabel.call({}, entry)))
            .toEqual(['MA: +, -', 'SA: 2'])
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
            kind: 'behaviour',
            type: 'V',
        })).toBe('V')
        expect(methods.compactCellEntryLabel.call({}, {
            kind: 'notification',
            type: 'I',
        })).toBe('I')
    })

    it('saves a new entry for the selected student and date', async () => {
        const methods = (CourseTable as any).methods
        const store = vi.fn().mockResolvedValue({ data: { id: 12 } })
        const ctx = {
            canSaveCellEntry: true,
            entrySaving: false,
            entryStore: { store },
            selected_course: { id: 20 },
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

    it('updates an assessment entry in the selected cell', async () => {
        const methods = (CourseTable as any).methods
        const update = vi.fn().mockResolvedValue({ data: { id: 12 } })
        const ctx = {
            canSaveCellEntry: true,
            entrySaving: false,
            entryStore: { update },
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

    it('renders a designed matrix with date columns and student rows', () => {
        const source = readFileSync(
            resolve('resources/js/pages/admin/teaching/overview/components/CourseTable.vue'),
            'utf8',
        )

        expect(source).toContain('data-testid="course-table"')
        expect(source).toContain('ref="courseTableScroll"')
        expect(source).toContain('data-testid="course-table-view-card"')
        expect(source.indexOf('data-testid="course-table-view-card"')).toBeLessThan(source.indexOf('class="course-table-card"'))
        expect(source).not.toContain('course-table-view-card__label')
        expect(source).not.toContain('Zwischen Anwesenheit und Einträgen wechseln.')
        expect(source).toContain('v-model="tableView"')
        expect(source).toContain('<v-tabs')
        expect(source).toContain('class="course-table-view-tabs"')
        expect(source).toContain('<v-tab value="attendance" prepend-icon="mdi-account-check">')
        expect(source).toContain('<v-tab value="entries" prepend-icon="mdi-format-list-bulleted">')
        expect(source).toContain('</v-tabs>')
        expect(source).toContain('@update:model-value="changeTableView"')
        expect(source).toContain('Anwesenheit')
        expect(source).toContain('Einträge')
        expect(source).toContain('position: sticky;')
        expect(source).toContain('class="course-table-title-row"')
        expect(source).toContain('class="course-table-work-row"')
        expect(source).toContain('class="course-table-work-label-content"')
        expect(source).toContain('<span>Arbeiten</span>')
        expect(source).toContain('mdi-plus-circle-outline')
        expect(source).toContain('v-for="work in courseWorksForDate(courseDate)"')
        expect(source).toContain('class="course-table-work-summary"')
        expect(source).toContain('class="course-table-work-summary-title"')
        expect(source).toContain('{{ work.work.title || work.label }}')
        expect(source).toContain('<v-icon size="13">mdi-clipboard-text</v-icon>')
        expect(source).toContain('@click="openWorkDialog(courseDate)"')
        expect(source).toContain('@keydown.enter.prevent="openWorkDialog(courseDate)"')
        expect(source).toContain('<v-dialog v-model="workDialog.open" persistent max-width="720">')
        expect(source).toContain('data-testid="course-table-date-create-work"')
        expect(source).toContain('course-table-date-edit-work-${assignment.id}')
        expect(source).toContain('course-table-date-delete-work-${assignment.id}')
        expect(source).toContain('class="course-table-date-work-item cursor-pointer"')
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
        expect(source).toContain('<v-tab v-if="workDialogForm.is_group_work" value="groups">Gruppen</v-tab>')
        expect(source).toContain('<v-window v-model="workDialogTab">')
        expect(source).toContain('<v-window-item value="work">')
        expect(source).toContain('<v-window-item v-if="workDialogForm.is_group_work" value="groups">')
        expect(source).toContain('v-for="(group, groupIndex) in workDialogGroups"')
        expect(source).toContain('v-for="student in unassignedWorkGroupStudents"')
        expect(source).toContain('>Nicht zugeordnet</strong>')
        expect(source).toContain('@dragstart="startUnassignedWorkGroupStudentDrag($event, student.value)"')
        expect(source.indexOf('>Nicht zugeordnet</strong>')).toBeLessThan(source.indexOf('v-for="(group, groupIndex) in workDialogGroups"'))
        expect(source).toContain('{{ workGroupMemberCountTitle(group) }}')
        expect(source).toContain('workDialogGroupDateEditingIndex === groupIndex')
        expect(source).toContain('@click.stop="beginWorkDialogGroupDateEditing(groupIndex)"')
        expect(source).toContain('@update:model-value="applyWorkDialogGroupDate(groupIndex, $event)"')
        expect(source).toContain('label="Gruppendatum"')
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
        expect(source).toContain('setWorkGroupStudentGrade(workDialogGroupDetails.groupIndex, row.studentId, $event)')
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
        expect(source).toContain('background: rgba(var(--v-theme-error), 0.14) !important;')
        expect(source).toContain('data-testid="course-table-entry-cell-badges"')
        expect(source).toContain("v-if=\"tableView === 'entries' && entriesForCell(student, courseDate).length\"")
        expect(source).toContain('data-testid="course-table-entry-cell-supplementary-row"')
        expect(source).toContain('v-for="entry in supplementaryEntriesForCell(student, courseDate)"')
        expect(source).toContain('data-testid="course-table-entry-cell-performance-row"')
        expect(source).toContain('v-for="entry in compactPerformanceEntriesForCell(student, courseDate)"')
        expect(source.indexOf('data-testid="course-table-entry-cell-supplementary-row"'))
            .toBeLessThan(source.indexOf('data-testid="course-table-entry-cell-performance-row"'))
        expect(source).toContain('compactCellEntryLabel(entry)')
        expect(source).toContain("'course-table-entry-cell--selected': isEntryDialogCellSelected(student, courseDate)")
        expect(source).toContain('@click="openEntryDialog(student, courseDate)"')
        expect(source).toContain('@keydown.enter.prevent="openEntryDialog(student, courseDate)"')
        expect(source).toContain('<v-dialog v-model="entryDialog.open" persistent max-width="680">')
        expect(source).toContain('data-testid="course-table-cell-entry-list"')
        expect(source).toContain('course-table-cell-edit-entry-${entry.uid}')
        expect(source).toContain('course-table-cell-delete-entry-${entry.uid}')
        expect(source).toContain('@click="startEditingCellEntry(entry)"')
        expect(source).toContain('@click="openDeleteEntryDialog(entry)"')
        expect(source).toContain('In dieser Zelle sind noch keine Einträge vorhanden.')
        expect(source).toContain('data-testid="course-table-cell-add-entry"')
        expect(source).toContain('Neuen Eintrag hinzufügen')
        expect(source).toContain('data-testid="course-table-cell-entry-form"')
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
