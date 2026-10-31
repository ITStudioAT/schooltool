import { describe, expect, it, vi } from 'vitest'
import CourseStudents from '@/pages/admin/teaching/overview/components/CourseStudents.vue'
import axios from 'axios'
import { computed, reactive } from 'vue'

vi.mock('axios', () => ({ default: { get: vi.fn() } }))

describe('CourseStudents presence in the selected period', () => {
    it('defaults attendance display to enabled and follows the personal display preference', () => {
        const visible = (CourseStudents as any).computed.attendanceVisible

        expect(visible.call({ config: {} })).toBe(true)
        expect(visible.call({ config: { user: { teaching_show_attendance: false } } })).toBe(false)
        expect(visible.call({ config: { user: { teaching_show_attendance: true } } })).toBe(true)
    })

    it.each([1, 2])('uses assigned area semester count %s and normalizes unavailable stored filters', (count) => {
        const component = CourseStudents as any
        const state = { selected_course: { semester_count: count }, selectedCourseSchema: { grading: { semester_count: 2 } }, activeSemester: 3 }
        expect(component.computed.semesterCount.call(state)).toBe(count)
        component.watch.semesterCount.handler.call(state, count)
        expect(state.activeSemester).toBe(count === 1 ? 1 : 3)
    })
    function context(overrides = {}) {
        const methods = (CourseStudents as any).methods
        return {
            semesterCount: 2, activeSemester: 1, schoolSem2StartDate: '2026-02-09', countSem2StartDate: null,
            selected_course: { students_info: [{ id: 10 }, { id: 20 }] },
            sortedCourseDates: [
                { id: 1, date: '2026-01-12', attendance: { s_10: true, s_20: null }, attendance_checked: true },
                { id: 2, date: '2026-01-19', attendance: { s_10: false, s_20: null }, attendance_checked: true },
                { id: 3, date: '2026-02-16', attendance: { s_10: true, s_20: false } },
                { id: 4, date: '2026-02-23', attendance: {}, attendance_checked: false },
                { id: 5, date: '2026-01-26', status: ['free'], attendance: { s_10: false } },
                { id: 6, date: '2026-02-02', status: ['entfaellt'], attendance: { s_10: false } },
            ],
            getAttendanceMap: methods.getAttendanceMap, normalizeIndexedAttendance: methods.normalizeIndexedAttendance,
            sanitizeAttendanceMap: methods.sanitizeAttendanceMap, isAttendancePresentValue: methods.isAttendancePresentValue,
            ...overrides,
        }
    }

    it.each([
        [1, { 10: 50, 20: null }],
        [2, { 10: 100, 20: 0 }],
        [3, { 10: 67, 20: 0 }],
    ])('uses known attendance in semester selection %s and preserves unknown people', (semester, expected) => {
        const state = context({ activeSemester: semester })
        expect((CourseStudents as any).computed.studentPresencePercentages.call(state)).toEqual(expected)
    })

    it('shows no percentage for an empty or wholly unchecked period', () => {
        for (const dates of [[], [{ id: 7, date: '2026-01-12', attendance_checked: false, attendance: {} }]]) {
            expect((CourseStudents as any).computed.studentPresencePercentages.call(context({ sortedCourseDates: dates })))
                .toEqual({ 10: null, 20: null })
        }
    })

    it('reuses whole-date confirmation and explicit unknown precedence for legacy attendance', () => {
        const state = context({ sortedCourseDates: [
            { id: 1, date: '2026-01-12', status: ['att_checked:1', 'att:10:0', 'att:20:null'] },
            { id: 2, date: '2026-01-19', status: ['att_checked:1'] },
        ] })
        expect((CourseStudents as any).computed.studentPresencePercentages.call(state)).toEqual({ 10: 50, 20: 100 })
    })
})

describe('CourseStudents account switching', () => {
    it.each([
        [['super_admin'], false, true],
        [['teacher'], false, false],
        [['admin'], false, false],
        [['super_admin'], true, false],
        [null, false, false],
    ])('allows account switching for roles %s while impersonating %s: %s', (roles, isImpersonating, allowed) => {
        expect((CourseStudents as any).computed.canImpersonateStudents.call({
            config: { roles, impersonation: { is_impersonating: isImpersonating } },
        })).toBe(allowed)
    })

    function context(overrides = {}) {
        return {
            canImpersonateStudents: true,
            selected_course: { id: 17 },
            impersonatingStudentId: null,
            hasUnsavedAttendanceChanges: false,
            show_bulk_entry: false,
            students_sort_mode: 'class_last_name',
            students_view_mode: 'students',
            $route: { path: '/admin/teaching/overview', query: { course: '17', panel: 'students', grades: 'sem1' }, hash: '#students' },
            $router: { resolve: vi.fn().mockReturnValue({ href: '/admin/teaching/overview?course=17&panel=students&grades=sem1&student_sort=class_last_name#students' }) },
            adminStore: { startImpersonation: vi.fn().mockResolvedValue(true) },
            ...overrides,
        }
    }

    it('switches to the linked user and opens the selected course in a new document', async () => {
        const state = context()
        const location = { href: '/admin/teaching?panel=students&course=17' }
        vi.stubGlobal('window', { location })
        try {
            await (CourseStudents as any).methods.impersonateStudent.call(state, { id: 901, user_id: 42 })
            expect(state.$router.resolve).toHaveBeenCalledWith({
                path: '/admin/teaching/overview', query: { course: '17', panel: 'students', grades: 'sem1', student_sort: 'class_last_name', student_view: 'students' }, hash: '#students',
            })
            expect(state.adminStore.startImpersonation).toHaveBeenCalledWith(42, {
                teachingCourseId: 17, returnUrl: '/admin/teaching/overview?course=17&panel=students&grades=sem1&student_sort=class_last_name#students',
            })
            expect(location.href).toBe('/student/course/17')
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it.each([
        { canImpersonateStudents: false },
        { impersonatingStudentId: 43 },
        { hasUnsavedAttendanceChanges: true },
        { show_bulk_entry: true },
    ])('preserves the current session when switching is unavailable: %s', async (overrides) => {
        const state = context(overrides)
        await (CourseStudents as any).methods.impersonateStudent.call(state, { id: 901, user_id: 42 })
        expect(state.adminStore.startImpersonation).not.toHaveBeenCalled()
    })

    it('keeps the selected lesson and day overview in the return target', async () => {
        const state = context({ students_view_mode: 'day_overview', selectedCourseDateForCourse: { id: 55 },
            adminStore: { startImpersonation: vi.fn().mockResolvedValue(false) } })
        await (CourseStudents as any).methods.impersonateStudent.call(state, { user_id: 42 })
        expect(state.$router.resolve).toHaveBeenCalledWith(expect.objectContaining({ query: {
            course: '17', panel: 'students', grades: 'sem1', student_sort: 'class_last_name', student_view: 'day_overview', date: '55',
        } }))
    })

    it('never uses an import ID as the account ID', async () => {
        const state = context()
        await (CourseStudents as any).methods.impersonateStudent.call(state, { id: 901, user_id: null })
        expect(state.adminStore.startImpersonation).not.toHaveBeenCalled()
    })

    it('stays in the teacher view and releases the loading state when the server rejects the switch', async () => {
        const state = context({ adminStore: { startImpersonation: vi.fn().mockResolvedValue(false) } })
        const location = { href: '/admin/teaching?panel=students&course=17' }
        vi.stubGlobal('window', { location })
        try {
            await (CourseStudents as any).methods.impersonateStudent.call(state, { id: 901, user_id: 42 })
            expect(location.href).toBe('/admin/teaching?panel=students&course=17')
            expect(state.impersonatingStudentId).toBeNull()
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('prevents duplicate switches while the first request is pending', async () => {
        let complete: (success: boolean) => void
        const startImpersonation = vi.fn(() => new Promise<boolean>((resolve) => { complete = resolve }))
        const state = context({ adminStore: { startImpersonation } })
        const firstRequest = (CourseStudents as any).methods.impersonateStudent.call(state, { user_id: 42 })
        await (CourseStudents as any).methods.impersonateStudent.call(state, { user_id: 43 })
        expect(startImpersonation).toHaveBeenCalledTimes(1)
        complete!(false)
        await firstRequest
        expect(state.impersonatingStudentId).toBeNull()
    })
})

describe('CourseStudents complete performance loading', () => {
    it('restores each course preference on course changes and fresh settings loads', () => {
        const context = reactive({
            selected_course: { id: 17 },
            settings: { teaching_show_calculated_grade: true, teaching_show_calculated_grade_by_course: { 17: false, 18: true } },
        })
        const visible = computed(() => (CourseStudents as any).computed.showCalculatedGrade.call(context))
        expect(visible.value).toBe(false)
        context.selected_course = { id: 18 }
        expect(visible.value).toBe(true)
        context.selected_course = { id: 17 }
        expect(visible.value).toBe(false)
        context.settings = JSON.parse(JSON.stringify(context.settings))
        expect(visible.value).toBe(false)
    })

    it.each([true, false])('uses the previous preference %s only for courses without a saved choice', (value) => {
        const context = { selected_course: { id: 17 }, settings: { teaching_show_calculated_grade: value } }
        expect((CourseStudents as any).computed.showCalculatedGrade.call(context)).toBe(value)
    })

    it.each([[false, true], [true, false]])('persists calculated note visibility from %s to %s', async (previous, value) => {
        const saveSettings = vi.fn().mockResolvedValue(true)
        const context = { selected_course: { id: 17 }, settings: {}, showCalculatedGrade: previous,
            savingCalculatedGradePreference: false, teachingStore: { saveSettings } }
        await (CourseStudents as any).methods.toggleCalculatedGrade.call(context)
        expect(saveSettings).toHaveBeenCalledWith({ teaching_course_id: 17, teaching_show_calculated_grade: value }, { notifySuccess: false })
        expect(context.savingCalculatedGradePreference).toBe(false)
    })

    it('retains the calculated note preference when saving fails', async () => {
        const context = { selected_course: { id: 17 }, settings: {}, showCalculatedGrade: true, savingCalculatedGradePreference: false,
            teachingStore: { saveSettings: vi.fn().mockResolvedValue(false) } }
        await (CourseStudents as any).methods.toggleCalculatedGrade.call(context)
        expect(context.showCalculatedGrade).toBe(true)
        expect(context.savingCalculatedGradePreference).toBe(false)
    })

    it('keeps the newly selected course visible when the previous course finishes saving', async () => {
        let complete: () => void
        const context: any = reactive({ selected_course: { id: 17 }, savingCalculatedGradePreference: false,
            settings: { teaching_show_calculated_grade_by_course: { 17: false, 18: false } },
            teachingStore: { saveSettings: vi.fn(() => new Promise<boolean>((resolve) => {
                complete = () => {
                    context.settings = { teaching_show_calculated_grade_by_course: { 17: true, 18: false } }
                    resolve(true)
                }
            })) },
        })
        Object.defineProperty(context, 'showCalculatedGrade', {
            get: () => (CourseStudents as any).computed.showCalculatedGrade.call(context),
        })
        const pending = (CourseStudents as any).methods.toggleCalculatedGrade.call(context)
        context.selected_course = { id: 18 }
        complete!()
        await pending
        expect(context.showCalculatedGrade).toBe(false)
        context.selected_course = { id: 17 }
        expect(context.showCalculatedGrade).toBe(true)
    })

    it.each([{ selected_course: null, settings: {} }, { selected_course: { id: 17 }, settings: null }])(
        'does not save without a selected course and loaded settings', async (state) => {
            const saveSettings = vi.fn()
            await (CourseStudents as any).methods.toggleCalculatedGrade.call({ ...state, teachingStore: { saveSettings } })
            expect(saveSettings).not.toHaveBeenCalled()
        },
    )
    it.each([18, 99])('loads the shared grading report and rejects a mismatched course %s', async (reportCourseId) => {
        const report = { course_id: reportCourseId, schoolyear_id: 3, students: [] }
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { data: report } })
        const context: any = {
            performanceRequestId: 0, usesNewBulkEntryDefinitions: true,
            config: { selected_schoolyear: { id: 3 } },
            behaviourEntryStore: { indexByCourse: vi.fn().mockResolvedValue(true) },
            entryStore: { indexByCourse: vi.fn().mockResolvedValue(true) },
            courseWorkStore: { index: vi.fn().mockResolvedValue(true) },
            categoryEvaluationStore: { indexByCourse: vi.fn().mockResolvedValue(true) },
        }
        await (CourseStudents as any).methods.loadStudentPerformance.call(context, 18)
        expect(axios.get).toHaveBeenLastCalledWith(expect.stringMatching(/18.*semester=3/))
        expect(context.performanceLoadFailed).toBe(reportCourseId !== 18)
        if (reportCourseId === 18) expect(context.performanceData.gradingReport).toEqual(report)
    })
    it.each(['6,25', '0', 'NA'])('saves bulk points %s as a normalized value', async (grade) => {
        const methods = (CourseStudents as any).methods
        const store = vi.fn().mockResolvedValue(true)
        const context = {
            ...methods, usesNewBulkEntryDefinitions: true,
            selected_course: { id: 1, students_info: [{ id: 10 }] },
            bulk_entry_form: { type: 'M', grade, student_ids: [10], date: '2026-09-13', description: '' },
            bulkGradeInputMode: 'points', bulkMaximumPoints: 12.5,
            bulkSpecialGradeItems: [{ value: 'NA' }], entryStore: { store },
            cancelBulkEntry: vi.fn(),
        }
        await methods.saveBulkEntry.call(context)
        expect(store).toHaveBeenCalledWith(expect.objectContaining({ grade: grade.replace(',', '.') }))
    })

    it.each([
        ['0', true], ['12,5', true], ['12.5', true], ['6,25', true],
        ['12.51', false], ['-1', false], ['Infinity', false], ['NaN', false],
        ['1,2,3', false], ['Text', false], ['1e1', false],
    ])('validates points %s against the configured decimal maximum', (value, valid) => {
        const methods = (CourseStudents as any).methods
        const context = { ...methods, bulkGradeInputMode: 'points', bulkMaximumPoints: 12.5, bulkGradeHint: 'Punkte 0 bis 12,5' }
        expect(context.validateBulkGrade(value) === true).toBe(valid)
    })

    it.each(['fixed', 'free', 'plus', 'plus_minus', 'points'])('offers and validates selected bulk specials for %s', (mode) => {
        const component = CourseStudents as any
        const definition: any = { short_name: 'M', has_properties: true, properties_mode: mode }
        const context: any = {
            ...component.methods, usesNewBulkEntryDefinitions: true,
            bulk_entry_form: { type: 'M' }, bulkGradeInputMode: mode,
            teachingWorks: [definition],
        }
        context.bulkSpecialGradeItems = component.computed.bulkSpecialGradeItems.call(context)
        expect(context.bulkSpecialGradeItems.map((item) => item.value)).toEqual(['NA', 'VL', 'F'])
        for (const code of ['NA', 'VL', 'F']) expect(context.validateBulkGrade(code)).toBe(true)
        definition.enabled_special_properties = ['F']
        context.bulkSpecialGradeItems = component.computed.bulkSpecialGradeItems.call(context)
        expect(context.validateBulkGrade('F')).toBe(true)
        expect(context.validateBulkGrade('VL')).not.toBe(true)
        definition.enabled_special_properties = []
        context.bulkSpecialGradeItems = component.computed.bulkSpecialGradeItems.call(context)
        expect(context.bulkSpecialGradeItems).toEqual([])
        expect(context.validateBulkGrade('F')).not.toBe(true)
    })

    it.each([
        ['plus', '+++', true],
        ['plus', '--', false],
        ['plus_minus', '+++', true],
        ['plus_minus', '---', true],
        ['plus_minus', '+-', false],
        ['plus_minus', 'gut', false],
        ['plus', '+'.repeat(50), true],
        ['plus', '+'.repeat(51), false],
    ])('validates bulk %s value %s', (mode, grade, valid) => {
        const methods = (CourseStudents as any).methods
        const context = { bulkGradeInputMode: mode, bulkGradeHint: 'Ungültiger Wert' }
        expect(methods.validateBulkGrade.call(context, grade) === true).toBe(valid)
    })

    it('does not submit invalid plus-only bulk values', async () => {
        const methods = (CourseStudents as any).methods
        const store = vi.fn()
        const context = {
            ...methods,
            bulk_entry_saving: false,
            selected_course: { id: 1, students_info: [{ id: 10 }] },
            bulk_entry_form: { student_ids: [10], grade: '--' },
            bulkGradeInputMode: 'plus',
            bulkGradeHint: 'Nur Pluszeichen',
            entryStore: { store },
        }
        await methods.saveBulkEntry.call(context)
        expect(store).not.toHaveBeenCalled()
    })

    it('skips performance requests for the evaluations list', async () => {
        const index = vi.fn()
        const context = { showPerformances: false, performanceLoading: true, entryStore: { index }, behaviourEntryStore: { index }, courseWorkStore: { index }, categoryEvaluationStore: { index } }
        await (CourseStudents as any).methods.loadStudentPerformance.call(context, 18)
        expect(index).not.toHaveBeenCalled()
        expect(context.performanceLoading).toBe(false)
        expect((CourseStudents as any).props.showPerformances.default).toBe(true)
    })

    it('keeps the three local card states through normalization and legacy serialization', () => {
        const methods = (CourseStudents as any).methods
        const context = {
            ...methods,
            canEditAttendance: true,
            selected_course: { students_info: [{ id: 10 }] },
            selectedCourseDateForCourse: { id: 1, attendance: {}, attendance_checked: true },
            attendanceCheckedForSelectedDate: true,
            selected_courseDate: null,
        }
        for (const expected of [null, false, true, null]) {
            context.toggleStudentPresence({ id: 10 })
            context.selectedCourseDateForCourse = JSON.parse(JSON.stringify(context.selected_courseDate))
            expect(context.studentAttendanceStateForSelectedDate(10)).toBe(expected)
        }
        const states = { 10: null, 11: true, 12: false }
        expect(context.buildStatusWithAttendanceMeta(['pruefung'], states, true)).toEqual([
            'pruefung', 'att:10:null', 'att:11:1', 'att:12:0', 'att_checked:1',
        ])
        context.selected_course.students_info = [{ id: 10 }, { id: 11 }, { id: 12 }]
        expect(context.getAttendanceMap({ status: context.buildStatusWithAttendanceMeta([], states, true) })).toEqual(states)
    })

    it('leaves unchecked card attendance blank and keeps explicit absence and checked presence', () => {
        const methods = (CourseStudents as any).methods
        const ctx = {
            ...methods,
            selectedCourseDateForCourse: { id: 1, attendance: {} as Record<string, boolean | null> },
            selected_course: { students_info: [{ id: 10 }] },
            presence_by_student: {} as Record<string, boolean>,
            attendanceCheckedForSelectedDate: false,
        }

        expect(ctx.studentAttendanceStateForSelectedDate(10)).toBeNull()
        ctx.selectedCourseDateForCourse.attendance.s_10 = false
        expect(ctx.studentAttendanceStateForSelectedDate(10)).toBe(false)
        ctx.selectedCourseDateForCourse.attendance.s_10 = true
        expect(ctx.studentAttendanceStateForSelectedDate(10)).toBe(true)
        ctx.attendanceCheckedForSelectedDate = true
        expect(ctx.studentAttendanceStateForSelectedDate(10)).toBe(true)
        ctx.attendanceCheckedForSelectedDate = false
        ctx.selectedCourseDateForCourse.attendance.s_10 = null
        expect(ctx.studentAttendanceStateForSelectedDate(10)).toBeNull()
    })

    it('invalidates cached performances and reloads course selection on schoolyear changes', () => {
        const context = { performanceRequestId: 4, performanceData: { entries: [{ teaching_course_id: 18 }] }, performanceLoading: false, courseStore: { index: vi.fn() } }
        ;(CourseStudents as any).watch['config.selected_schoolyear.id'].call(context, 4, 3)
        expect(context.performanceRequestId).toBe(5)
        expect(context.performanceData).toEqual({ entries: [], behaviourEntries: [], works: [], evaluations: [], gradingReport: null })
        expect(context.performanceLoading).toBe(true)
        expect(context.courseStore.index).toHaveBeenCalledOnce()
    })

    it('uses course ownership for star schoolyears and suppresses stale-year reminder indicators', () => {
        const student = { id: 12, user_id: 12, stars: [{ date: '2025-08-31' }, { date: '2026-09-01' }, { date: null }, { date: '2026-01-01' }] }
        const context = { activeSemester: 3, countSem2StartDate: '2026-02-09', selected_course: { schoolyear_id: 3 }, config: { selected_schoolyear: { id: 3, from: '2025-09-01', until: '2026-08-31' } } }
        const filtered = (CourseStudents as any).methods.studentForSelectedSemester.call(context, student)
        expect(filtered.stars).toEqual(student.stars)
        context.config.selected_schoolyear.id = 4
        const stale = (CourseStudents as any).methods.studentForSelectedSemester.call(context, student)
        expect(stale.stars).toEqual([])
        expect(stale.user_id).toBeNull()
    })

    it.each([1, 2, 3])('filters the existing star badges for semester %s without changing student data', (activeSemester) => {
        const student = { id: 12, stars: [{ date: '2026-02-08' }, { date: '2026-02-09' }, { date: null }] }
        const filtered = (CourseStudents as any).methods.studentForSelectedSemester.call({
            activeSemester, countSem2StartDate: '2026-02-09', selected_course: { schoolyear_id: 3 },
            config: { selected_schoolyear: { id: 3, from: '2025-09-01', until: '2026-08-31' } },
        }, student)
        expect(filtered.stars).toHaveLength(activeSemester === 3 ? 3 : 1)
        expect(student.stars).toHaveLength(3)
        expect(filtered.stars.some((star) => star.date === '2026-02-09')).toBe(activeSemester !== 1)
        expect(filtered.stars.some((star) => star.date === '2026-02-08')).toBe(activeSemester !== 2)
    })

    it.each([[1, 2], [2, 0], [3, 2]])('keeps the two course stars awarded before teaching started in semester %s', (activeSemester, expectedStars) => {
        const student = { id: 492, user_id: 492, stars: [{ id: 'one', date: '2026-09-05' }, { id: 'two', date: '2026-09-05' }] }
        const context = {
            activeSemester, countSem2StartDate: '2027-02-15', selected_course: { id: 18, schoolyear_id: 2 },
            config: { selected_schoolyear: { id: 2, from: '2026-09-14', until: '2027-07-09' } },
        }
        const filtered = (CourseStudents as any).methods.studentForSelectedSemester.call(context, student)
        expect(filtered.stars).toHaveLength(expectedStars)
        context.selected_course.schoolyear_id = 1
        expect((CourseStudents as any).methods.studentForSelectedSemester.call(context, student).stars).toEqual([])
    })

    it('reloads performance after leaving a bulk action', () => {
        const context = { selected_course: { id: 18 }, loadStudentPerformance: vi.fn() }
        ;(CourseStudents as any).watch.show_bulk_entry.call(context, false, true)
        expect(context.loadStudentPerformance).toHaveBeenCalledWith(18)
    })

    it('keeps legacy schema definitions when the course has no assigned entry area', () => {
        const context = { uses_entry_areas_for_grading_schema: true, selected_course: { id: 18 } }
        expect((CourseStudents as any).computed.usesNewBulkEntryDefinitions.call(context)).toBe(false)
    })

    it('keeps the new course snapshot when an old request overwrites the shared store later', async () => {
        let finishOldRequest: (() => void) | undefined
        const context: any = {
            performanceRequestId: 0,
            behaviourEntryStore: { indexByCourse: vi.fn().mockResolvedValue(true) },
            courseWorkStore: { index: vi.fn().mockResolvedValue(true) },
            categoryEvaluationStore: { indexByCourse: vi.fn().mockResolvedValue(true) },
            entryStore: {
                courseEntries: [],
                indexByCourse: vi.fn((courseId) => {
                    if (courseId === 18) return new Promise((resolve) => {
                        finishOldRequest = () => {
                            context.entryStore.courseEntries = [{ teaching_course_id: 18 }]
                            resolve(true)
                        }
                    })
                    context.entryStore.courseEntries = [{ teaching_course_id: 19 }]
                    return Promise.resolve(true)
                }),
            },
        }
        const oldRequest = (CourseStudents as any).methods.loadStudentPerformance.call(context, 18)
        await (CourseStudents as any).methods.loadStudentPerformance.call(context, 19)
        finishOldRequest?.()
        await oldRequest
        expect(context.performanceData.entries).toEqual([{ teaching_course_id: 19 }])
        expect(context.performanceLoadFailed).toBe(false)
    })

    it('loads all course records and evaluations without restricting the semester or student', async () => {
        const context = {
            performanceRequestId: 0,
            performanceLoading: false,
            performanceLoadFailed: false,
            behaviourEntryStore: { indexByCourse: vi.fn().mockResolvedValue(true) },
            entryStore: { indexByCourse: vi.fn().mockResolvedValue(true) },
            courseWorkStore: { index: vi.fn().mockResolvedValue(true) },
            categoryEvaluationStore: { indexByCourse: vi.fn().mockResolvedValue(true) },
        }
        await (CourseStudents as any).methods.loadStudentPerformance.call(context, 18)
        expect(context.entryStore.indexByCourse).toHaveBeenCalledWith(18)
        expect(context.courseWorkStore.index).toHaveBeenCalledWith(18)
        expect(context.behaviourEntryStore.indexByCourse).toHaveBeenCalledWith(18)
        expect(context.categoryEvaluationStore.indexByCourse).toHaveBeenCalledWith(18)
        expect(context.performanceLoading).toBe(false)
        expect(context.performanceLoadFailed).toBe(false)
    })

    it('marks partially failed data loading instead of showing an empty performance record', async () => {
        const context = {
            performanceRequestId: 0,
            performanceLoading: false,
            performanceLoadFailed: false,
            behaviourEntryStore: { indexByCourse: vi.fn().mockResolvedValue(true) },
            entryStore: { indexByCourse: vi.fn().mockResolvedValue(true) },
            courseWorkStore: { index: vi.fn().mockRejectedValue(new Error('Offline')) },
            categoryEvaluationStore: { indexByCourse: vi.fn().mockResolvedValue(false) },
        }
        await (CourseStudents as any).methods.loadStudentPerformance.call(context, 18)
        expect(context.performanceLoading).toBe(false)
        expect(context.performanceLoadFailed).toBe(true)
    })
})

describe('CourseStudents sorting', () => {
    it('defaults student sort mode to name', () => {
        const data = (CourseStudents as any).data.call({
            emptyBulkEntryForm: () => ({}),
        })

        expect(data.students_sort_mode).toBe('last_name_first_name')
    })

    it('does not show the bulk entry saving state by default', () => {
        const data = (CourseStudents as any).data.call({
            emptyBulkEntryForm: () => ({}),
        })

        expect(data.bulk_entry_saving).toBe(false)
    })

    it('does not include canceled students in the overview list', () => {
        const ctx = {
            selected_course: {
                students_info: [
                    { id: 1, last_name: 'Alpha', first_name: 'A', schoolclass: '1A', canceled_at: '2026-02-15 10:00:00' },
                    { id: 2, last_name: 'Beta', first_name: 'B', schoolclass: '1A', canceled_at: null },
                    { id: 3, last_name: 'Gamma', first_name: 'C', schoolclass: '1A', canceled_at: null },
                    { id: 4, last_name: 'Delta', first_name: 'D', schoolclass: '1A', canceled_at: '2026-02-16 10:00:00' },
                ],
            },
            isStudentCanceled(student: { canceled_at?: string | null }) {
                return !!student?.canceled_at
            },
            compareStudentsBySelectedSort(a: { last_name?: string | null; first_name?: string | null }, b: { last_name?: string | null; first_name?: string | null }) {
                const byLastName = (a.last_name || '').localeCompare(b.last_name || '', 'de', { sensitivity: 'base' })
                if (byLastName !== 0) return byLastName
                return (a.first_name || '').localeCompare(b.first_name || '', 'de', { sensitivity: 'base' })
            },
        }

        const sorted = (CourseStudents as any).computed.sortedSelectedStudents.call(ctx)

        expect(sorted.map((s: { id: number }) => s.id)).toEqual([2, 3])
        expect(sorted.every((s: { canceled_at?: string | null }) => !s.canceled_at)).toBe(true)
    })

    it('counts only non-canceled students in activeStudentsCount', () => {
        const ctx = {
            selected_course: {
                students_info: [
                    { id: 1, canceled_at: null },
                    { id: 2, canceled_at: '2026-02-16 10:00:00' },
                    { id: 3, canceled_at: '' },
                ],
            },
            isStudentCanceled(student: { canceled_at?: string | null }) {
                return !!student?.canceled_at
            },
        }

        const count = (CourseStudents as any).computed.activeStudentsCount.call(ctx)
        expect(count).toBe(2)
    })

    it('returns canceled name class for canceled students', () => {
        const ctx = {
            isStudentCanceled(student: { canceled_at?: string | null }) {
                return !!student?.canceled_at
            },
        }

        const canceledClass = (CourseStudents as any).methods.studentNameClass.call(ctx, { canceled_at: '2026-02-16 10:00:00' })
        const activeClass = (CourseStudents as any).methods.studentNameClass.call(ctx, { canceled_at: null })

        expect(canceledClass).toBe('student-name--canceled')
        expect(activeClass).toBe('')
    })

    it('builds secondary student detail lines for email and last login', () => {
        const methods = (CourseStudents as any).methods

        expect(methods.studentEmailText.call({}, { email: 'student@example.test' })).toBe('student@example.test')
        expect(methods.studentEmailText.call({}, { email: '   ' })).toBe('')
        expect(methods.studentLastLoginText.call({}, { login_at: '24.03.2026  08:15' })).toBe('24.03.2026  08:15')
        expect(methods.studentLastLoginText.call({}, { login_at: null })).toBe('')
    })

    it('formats birth details and uses the table gender symbols beside the name', () => {
        const methods = (CourseStudents as any).methods
        const context = {
            formatDate: methods.formatDate,
            normalizedStudentSex: methods.normalizedStudentSex,
        }

        expect(methods.studentBirthDetails.call(context, { birth_date: '2011-05-20', age: 15 }))
            .toBe('20.05.2011 · 15 Jahre')
        expect(methods.studentBirthDetails.call(context, { birth_date: '2011-05-20', age: null }))
            .toBe('20.05.2011')
        expect(methods.studentSexIcon.call(context, { sex: 'm' })).toBe('mdi-gender-male')
        expect(methods.studentSexColor.call(context, { sex: 'm' })).toBe('blue')
        expect(methods.studentSexIcon.call(context, { sex: 'w' })).toBe('mdi-gender-female')
        expect(methods.studentSexColor.call(context, { sex: 'w' })).toBe('pink')
    })

    it('renders age and last login only when their course settings are enabled', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/components/CourseStudents.vue', 'utf8')
        )

        expect(source).toContain('selected_course?.teaching_show_student_age && studentBirthDetails(student)')
        expect(source).toContain('selected_course?.teaching_show_student_last_login && studentLastLoginText(student)')
        expect(source.indexOf('studentBirthDetails(student)')).toBeGreaterThan(source.indexOf('studentEmailText(student)'))
        expect(source.indexOf('studentBirthDetails(student)')).toBeLessThan(source.indexOf('studentLastLoginText(student)'))
        expect(source).toContain('v-if="studentSexIcon(student)"')
    })

    it('does not render the old per-student pdf button anymore', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/components/CourseStudents.vue', 'utf8')
        )

        expect(source).not.toContain('mdi-file-pdf-box')
        expect(source).not.toContain('studentPerformancePdfUrl')
        expect(source).toContain('studentEmailText(student)')
        expect(source).toContain('studentLastLoginText(student)')
    })

    it('renders clickable student indicators directly in the name line and day overview', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/components/CourseStudents.vue', 'utf8')
        )

        expect(source).toContain('class="student-name-line"')
        expect(source).toContain('<CourseStudentIndicators :student="studentForSelectedSemester(student)"')
        expect(source.indexOf('<CourseStudentIndicators :student="studentForSelectedSemester(student)"')).toBeGreaterThan(source.indexOf('class="student-name-line"'))
        expect(source.indexOf('<CourseStudentIndicators :student="studentForSelectedSemester(student)"')).toBeLessThan(source.indexOf('studentEmailText(student)'))
        expect(source).toContain('<CourseStudentIndicators :student="studentForSelectedSemester(item.student)"')
        expect(source).toContain('@select="$refs.studentNotes.open(student, $event)"')
    })

    it('keeps student cards and day overview rows from opening student details', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/components/CourseStudents.vue', 'utf8')
        )
        expect(source).not.toContain('@click="openStudent')
        expect(source).not.toContain('student-list-item--clickable')
        expect(source.match(/:link="false"/g)).toHaveLength(2)
        expect((CourseStudents as any).methods.openStudent).toBeUndefined()
    })

    it('retains explicit bulk selection and attendance controls on non-navigating cards', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/components/CourseStudents.vue', 'utf8')
        )
        expect(source).toContain('v-model="bulk_entry_form.student_ids"')
        expect(source).toContain('@click.stop="toggleStudentPresence(student)"')
        expect(source).not.toContain('openStudentFromCard')
    })

    it('shows the bulk entry button loading state before saving entries', async () => {
        const methods = (CourseStudents as any).methods
        let continueNextTick: (() => void) | null = null
        const store = vi.fn().mockResolvedValue(true)
        const cancelBulkEntry = vi.fn()
        const ctx: Record<string, any> = {
            bulk_entry_saving: false,
            selected_course: {
                id: 8,
                students_info: [{ id: 11 }, { id: 12 }],
            },
            bulk_entry_form: {
                student_ids: [11],
                type: 'MA',
                grade: '1',
                date: '2026-04-30',
                description: 'Aktive Mitarbeit',
            },
            entryStore: { store },
            cancelBulkEntry,
            $nextTick: vi.fn(() => new Promise<void>((resolve) => {
                continueNextTick = resolve
            })),
        }

        const promise = methods.saveBulkEntry.call(ctx)

        expect(ctx.bulk_entry_saving).toBe(true)
        expect(ctx.$nextTick).toHaveBeenCalledTimes(1)
        expect(store).not.toHaveBeenCalled()

        continueNextTick?.()
        await promise

        expect(store).toHaveBeenCalledWith({
            teaching_course_id: 8,
            user_id: 11,
            type: 'MA',
            grade: '1',
            date: '2026-04-30',
            description: 'Aktive Mitarbeit',
        })
        expect(cancelBulkEntry).toHaveBeenCalledTimes(1)
        expect(ctx.bulk_entry_saving).toBe(false)
    })
})

describe('CourseStudents selected date label', () => {
    it('formats selected date label in compact form', () => {
        const computed = (CourseStudents as any).computed
        const methods = (CourseStudents as any).methods

        const ctx: Record<string, unknown> = {
            selectedCourseDateForCourse: {
                date: '2026-03-02',
                hours: [6, 5],
            },
            school_hours: [
                { hour: 5, from: '11:50:00', until: '12:40:00' },
                { hour: 6, from: '12:45:00', until: '13:35:00' },
            ],
            getWeekdayShort: methods.getWeekdayShort,
            formatDateShort: methods.formatDateShort,
            formatCourseDateHoursCompact: methods.formatCourseDateHoursCompact,
            formatCourseDateTimeRange: methods.formatCourseDateTimeRange,
            formatTimeShort: methods.formatTimeShort,
        }
        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)

        const label = computed.selectedCourseDateLabel.call(ctx)

        expect(label).toBe('Mo, 02.03. - 5.-6. Std (11:50-13:35)')
    })

    it('formats discontinuous hours as compact groups', () => {
        const methods = (CourseStudents as any).methods

        const label = methods.formatCourseDateHoursCompact.call({}, [2, 4, 5])

        expect(label).toBe('2. Std, 4.-5. Std')
    })
})

describe('CourseStudents attendance editing', () => {
    it.each([
        ['2025/26', true],
        ['2026/27', false],
        ['2027/28', false],
    ])('allows attendance editing for %s: %s', (concerns, expected) => {
        const canEditAttendance = (CourseStudents as any).computed.canEditAttendance

        expect(canEditAttendance.call({
            config: { selected_schoolyear: { concerns } },
        })).toBe(expected)
    })

    it('recognizes a school year embedded in its name', () => {
        const canEditAttendance = (CourseStudents as any).computed.canEditAttendance

        expect(canEditAttendance.call({
            config: { selected_schoolyear: { name: 'Schuljahr 2026/27' } },
        })).toBe(false)
    })

    it('removes the attendance actions from the student panel from 2026/27 onward', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/components/CourseStudents.vue', 'utf8')
        )

        expect(source).toContain('v-if="attendanceVisible && canEditAttendance && selectedCourseDateForCourse && !isDayOverviewMode && !show_bulk_entry"')
        expect(source).toContain('v-if="attendanceVisible && canEditAttendance && selectedCourseDateForCourse"')
    })

    it('does not toggle one student when attendance editing is disabled', () => {
        const getAttendanceMap = vi.fn()

        ;(CourseStudents as any).methods.toggleStudentPresence.call({
            canEditAttendance: false,
            selectedCourseDateForCourse: { id: 277 },
            getAttendanceMap,
        }, { id: 16 })

        expect(getAttendanceMap).not.toHaveBeenCalled()
    })

    it('does not check attendance when attendance editing is disabled', async () => {
        const persistAttendance = vi.fn()

        await (CourseStudents as any).methods.toggleAttendanceChecked.call({
            canEditAttendance: false,
            selectedCourseDateForCourse: { id: 277 },
            savingAttendance: false,
            persistAttendance,
        })

        expect(persistAttendance).not.toHaveBeenCalled()
    })
})

describe('CourseStudents course-specific schema', () => {
    it('prefers the selected course schema snapshot for works and semester count', () => {
        const computed = (CourseStudents as any).computed
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_schema_id: 'schema-teacher',
                teacher_teaching_schema: {
                    id: 'schema-teacher',
                    works: [{ short_name: 'MA', name: 'Mitarbeit' }],
                    grading: { semester_count: 2 },
                },
            },
            teachingStore: {
                schemaById: () => ({ id: 'schema-global', works: [{ short_name: 'AK', name: 'Auftrag' }], grading: { semester_count: 1 } }),
            },
        }

        ctx.selectedCourseSchema = computed.selectedCourseSchema.call(ctx)

        expect((ctx.selectedCourseSchema as any).id).toBe('schema-teacher')
        expect(computed.teachingWorks.call(ctx)).toEqual([{ short_name: 'MA', name: 'Mitarbeit' }])
        expect(computed.semesterCount.call(ctx)).toBe(2)
    })

    it('uses the assigned entry-area definitions for the new bulk action options', () => {
        const computed = (CourseStudents as any).computed
        const context: Record<string, any> = {
            uses_entry_areas_for_grading_schema: true,
            selected_course: {
                teaching_entry_area: {
                    id: 4,
                    entry_definitions: [
                        {
                            category: 'Benotung',
                            short_name: 'MÜ',
                            name: 'Mündliche Übung',
                            has_properties: true,
                            properties_mode: 'fixed',
                            fixed_properties: ['Sehr gut', 'Gut'],
                        },
                        { category: 'Verhalten', short_name: 'ZV', name: 'Zuverlässigkeit' },
                    ],
                },
                teacher_teaching_schema: {
                    works: [{ short_name: 'ALT', name: 'Alte Option', grades: [{ grade: '1' }] }],
                },
            },
            bulk_entry_form: { type: 'MÜ', grade: '' },
        }

        context.selectedCourseSchema = computed.selectedCourseSchema.call(context)
        context.teachingWorks = computed.teachingWorks.call(context)
        context.usesNewBulkEntryDefinitions = computed.usesNewBulkEntryDefinitions.call(context)

        expect(context.teachingWorks.map((definition: { short_name: string }) => definition.short_name)).toEqual(['MÜ'])
        expect(computed.workTypeItems.call(context)).toEqual([{ title: 'MÜ - Mündliche Übung', value: 'MÜ' }])
        expect(computed.gradeItemsForType.call(context)).toEqual([
            { title: 'Sehr gut', value: 'Sehr gut' },
            { title: 'Gut', value: 'Gut' },
        ])
        expect(computed.bulkGradeInputMode.call(context)).toBe('fixed')
        expect(computed.bulkGradeLabel.call(context)).toBe('Eigenschaft')
    })

    it('allows a new bulk entry without a property when its definition has none', () => {
        const computed = (CourseStudents as any).computed
        const context: Record<string, any> = {
            hasStudents: true,
            usesNewBulkEntryDefinitions: true,
            teachingWorks: [{ short_name: 'OK', has_properties: false }],
            bulk_entry_form: {
                student_ids: [11],
                type: 'OK',
                grade: '',
                description: '',
            },
        }

        context.bulkGradeInputMode = computed.bulkGradeInputMode.call(context)

        expect(context.bulkGradeInputMode).toBe('none')
        expect(computed.bulkEntryEnabled.call(context)).toBe(true)
    })
})
