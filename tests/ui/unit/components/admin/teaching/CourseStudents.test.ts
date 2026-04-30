import { describe, expect, it, vi } from 'vitest'
import CourseStudents from '@/pages/admin/teaching/overview/components/CourseStudents.vue'

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

    it('places canceled students at the bottom of the list', () => {
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

        expect(sorted.map((s: { id: number }) => s.id)).toEqual([2, 3, 1, 4])

        const canceledFlags = sorted.map((s: { canceled_at?: string | null }) => !!s.canceled_at)
        expect(canceledFlags).toEqual([false, false, true, true])
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

    it('does not render the old per-student pdf button anymore', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/components/CourseStudents.vue', 'utf8')
        )

        expect(source).not.toContain('mdi-file-pdf-box')
        expect(source).not.toContain('studentPerformancePdfUrl')
        expect(source).toContain('studentEmailText(student)')
        expect(source).toContain('studentLastLoginText(student)')
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
})
