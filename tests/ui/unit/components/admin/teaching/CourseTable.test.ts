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

    it('opens the persistent work dialog for the selected date', () => {
        const methods = (CourseTable as any).methods
        const courseDate = { id: 7, date: '2026-05-16' }
        const ctx = {
            workDialog: {
                courseDate: null,
                open: false,
            },
            cancelDateWorkForm: vi.fn(),
        }

        methods.openWorkDialog.call(ctx, courseDate)

        expect(ctx.cancelDateWorkForm).toHaveBeenCalledTimes(1)
        expect(ctx.workDialog).toEqual({ courseDate, open: true })
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

    it('only enables attendance markers for non-free dates that are not in the future', () => {
        const methods = (CourseTable as any).methods
        vi.useFakeTimers()
        vi.setSystemTime(new Date(2026, 2, 10))

        try {
            const ctx = {
                isFreeCourseDate: methods.isFreeCourseDate,
                normalizeDateKey: methods.normalizeDateKey,
                dateKey: methods.dateKey,
            }

            expect(methods.isAttendanceToggleable.call(ctx, { id: 1, date: '2026-03-10', status: [] })).toBe(true)
            expect(methods.isAttendanceToggleable.call(ctx, { id: 2, date: '2026-03-09', status: [] })).toBe(true)
            expect(methods.isAttendanceToggleable.call(ctx, { id: 3, date: '2026-03-11', status: [] })).toBe(false)
            expect(methods.isAttendanceToggleable.call(ctx, { id: 4, date: '2026-03-10', status: ['free'] })).toBe(false)
            expect(methods.isAttendanceToggleable.call(ctx, { id: 5, date: '2026-03-10', status: ['entfaellt'] })).toBe(false)
        } finally {
            vi.useRealTimers()
        }
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
            attendance: { 10: false },
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

        await methods.toggleStudentAttendance.call(ctx, { id: 10 }, { id: 7, date: '2026-03-09', status: [], attendance: {} })

        expect(updateStatus).toHaveBeenCalledWith(7, {
            attendance: { 10: false },
            attendance_checked: false,
        })
        expect((ctx.selected_course as any).course_dates[0].attendance).toEqual({ 10: false })
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
        expect(source).toContain('<v-dialog v-model="deleteWorkDialog.open" persistent max-width="460">')
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
