import { describe, expect, it, vi } from 'vitest'
import CoursePrint from '@/pages/admin/teaching/overview/components/CoursePrint.vue'

describe('CoursePrint', () => {
    it('builds the all-students performances pdf url', () => {
        const ctx = {
            selected_course: { id: 5 },
            print_scope: 'all',
            selected_course_student_id: null,
        }

        const url = (CoursePrint as any).methods.performancesPdfUrl.call(ctx)

        expect(url).toBe('/api/admin/teaching/courses/5/performances_pdf')
    })

    it('builds the single-student performances pdf url', () => {
        const ctx = {
            selected_course: { id: 5 },
            print_scope: 'single',
            selected_course_student_id: 17,
        }

        const url = (CoursePrint as any).methods.performancesPdfUrl.call(ctx)

        expect(url).toBe('/api/admin/teaching/courses/5/performances_pdf?course_student_id=17')
    })

    it('enables printing for all students when course students exist', () => {
        const ctx = {
            selected_course: {
                id: 5,
                students_info: [
                    { course_student_id: 11, last_name: 'Alpha', first_name: 'Anna' },
                ],
            },
        }
        ctx.studentOptions = (CoursePrint as any).computed.studentOptions.call(ctx)
        ctx.print_scope = 'all'
        ctx.selected_course_student_id = null

        const canPrint = (CoursePrint as any).computed.canPrint.call(ctx)

        expect(canPrint).toBe(true)
    })

    it('excludes canceled and deleted students from print options', () => {
        const ctx = {
            selected_course: {
                id: 5,
                students_info: [
                    { course_student_id: 11, last_name: 'Alpha', first_name: 'Anna' },
                    { course_student_id: 12, last_name: 'Beta', first_name: 'Berta', canceled_at: '2026-03-01 10:00:00' },
                    { course_student_id: 13, last_name: 'Gamma', first_name: 'Gina', deleted_at: '2026-03-01 10:00:00' },
                ],
            },
        }

        const studentOptions = (CoursePrint as any).computed.studentOptions.call(ctx)

        expect(studentOptions).toEqual([
            { title: 'Alpha, Anna', value: 11 },
        ])
    })

    it('opens the pdf url in a new window', () => {
        const open = vi.spyOn(window, 'open').mockImplementation(() => null)

        const ctx = {
            canPrint: true,
            performancesPdfUrl() {
                return '/api/admin/teaching/courses/5/performances_pdf'
            },
        }

        ;(CoursePrint as any).methods.downloadPerformancesPdf.call(ctx)

        expect(open).toHaveBeenCalledWith('/api/admin/teaching/courses/5/performances_pdf', '_blank', 'noopener')

        open.mockRestore()
    })

    it('uses a single grade semester option for one-semester courses', () => {
        const ctx = {
            selected_course: {
                teacher_teaching_schema: {
                    grading: { semester_count: 1 },
                },
            },
            semesterCount: 1,
            hasTwoSemesters: false,
        }

        expect((CoursePrint as any).computed.semesterCount.call(ctx)).toBe(1)
        expect((CoursePrint as any).computed.gradeSemesterOptions.call(ctx)).toEqual([
            { label: 'Semester', value: '1' },
        ])
    })

    it('opens the one-semester grades pdf with semester 1 selected', () => {
        const open = vi.spyOn(window, 'open').mockImplementation(() => null)

        const ctx = {
            selected_course: { id: 5 },
            canPrintGrades: true,
            grades_semesters: ['1'],
        }

        ;(CoursePrint as any).methods.downloadGradesPdf.call(ctx)

        expect(open).toHaveBeenCalledWith('/api/admin/teaching/courses/5/grades_pdf?semesters=1', '_blank', 'noopener')

        open.mockRestore()
    })
})
