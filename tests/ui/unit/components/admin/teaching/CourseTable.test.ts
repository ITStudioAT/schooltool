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

    it('detects free course dates for green date columns', () => {
        const methods = (CourseTable as any).methods

        expect(methods.isFreeCourseDate.call({}, { status: ['free'] })).toBe(true)
        expect(methods.isFreeCourseDate.call({}, { status: ['entfaellt'] })).toBe(false)
        expect(methods.isFreeCourseDate.call({}, { status: [] })).toBe(false)
        expect(methods.freeCourseDateReason.call({}, { free_reason: 'Herbstferien' })).toBe('Herbstferien')
        expect(methods.freeCourseDateReason.call({}, { free_reason: '' })).toBe('Frei')
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
        } finally {
            vi.useRealTimers()
        }
    })

    it('toggles attendance marker visibility and stores the view in the URL', () => {
        const methods = (CourseTable as any).methods
        const initialData = (CourseTable as any).data()
        const replace = vi.fn().mockResolvedValue(undefined)
        const ctx = {
            showAttendanceMarkers: initialData.showAttendanceMarkers,
            $route: {
                path: '/admin/teaching',
                query: {
                    course: '5',
                    panel: 'table',
                },
            },
            $router: { replace },
        }

        expect(ctx.showAttendanceMarkers).toBe(true)

        methods.toggleAttendanceMarkers.call(ctx)

        expect(ctx.showAttendanceMarkers).toBe(false)
        expect(replace).toHaveBeenLastCalledWith({
            path: '/admin/teaching',
            query: {
                course: '5',
                panel: 'table',
                view: 'plain',
            },
        })

        methods.toggleAttendanceMarkers.call(ctx)

        expect(ctx.showAttendanceMarkers).toBe(true)
        expect(replace).toHaveBeenLastCalledWith({
            path: '/admin/teaching',
            query: {
                course: '5',
                panel: 'table',
                view: 'attendance',
            },
        })
    })

    it('restores attendance marker visibility from the URL view', () => {
        const methods = (CourseTable as any).methods
        const ctx = {
            showAttendanceMarkers: true,
        }

        methods.restoreAttendanceView.call(ctx, 'plain')
        expect(ctx.showAttendanceMarkers).toBe(false)

        methods.restoreAttendanceView.call(ctx, 'attendance')
        expect(ctx.showAttendanceMarkers).toBe(true)

        methods.restoreAttendanceView.call(ctx, undefined)
        expect(ctx.showAttendanceMarkers).toBe(true)
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
        expect(source).toContain('class="course-table-command-row"')
        expect(source).toContain('class="course-table-command-cell"')
        expect(source).toContain(':colspan="tableColumnCount"')
        expect(source).toContain('class="course-table-command-bar"')
        expect(source).toContain('class="course-table-command-button"')
        expect(source).toContain(":icon=\"showAttendanceMarkers ? 'mdi-account-check' : 'mdi-account-off-outline'\"")
        expect(source).toContain("@click=\"toggleAttendanceMarkers\"")
        expect(source).toContain('justify-content: flex-start;')
        expect(source).toContain('left: 7px;')
        expect(source).toContain('position: sticky;')
        expect(source).toContain('width: fit-content;')
        expect(source).toContain('class="course-table-title-row"')
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
        expect(source).toContain('class="course-table-entry-cell course-table-entry-cell--interactive"')
        expect(source).toContain('data-testid="course-table-entry-cell-badges"')
        expect(source).toContain('v-for="entry in entriesForCell(student, courseDate)"')
        expect(source).toContain('compactCellEntryLabel(entry)')
        expect(source).toContain("'course-table-entry-cell--selected': isEntryDialogCellSelected(student, courseDate)")
        expect(source).toContain('@click="openEntryDialog(student, courseDate)"')
        expect(source).toContain('@keydown.enter.prevent="openEntryDialog(student, courseDate)"')
        expect(source).toContain('<v-dialog v-model="entryDialog.open" persistent max-width="680">')
        expect(source).toContain('data-testid="course-table-cell-entry-list"')
        expect(source).toContain('In dieser Zelle sind noch keine Einträge vorhanden.')
        expect(source).toContain('data-testid="course-table-cell-add-entry"')
        expect(source).toContain('Neuen Eintrag hinzufügen')
        expect(source).toContain('data-testid="course-table-cell-entry-form"')
        expect(source).toContain('.course-table-entry-cell--interactive:hover,')
        expect(source).toContain('.course-table-entry-cell--selected {')
        expect(source).toContain('class="course-table-attendance-marker"')
        expect(source).toContain('v-if="showAttendanceMarkers && isAttendanceToggleable(courseDate)"')
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
