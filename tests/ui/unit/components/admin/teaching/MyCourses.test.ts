import { describe, expect, it } from 'vitest'
import MyCourses from '@/pages/admin/teaching/overview/components/MyCourses.vue'

describe('MyCourses counts', () => {
    it('defaults student sort mode to name', () => {
        const data = (MyCourses as any).data.call({})

        expect(data.students_sort_mode).toBe('last_name_first_name')
    })

    it('counts only non-canceled students in activeSelectedStudentsCount', () => {
        const ctx = {
            data: {
                students_info: [
                    { id: 1, canceled_at: null },
                    { id: 2, canceled_at: '2026-02-16 12:00:00' },
                    { id: 3, canceled_at: '' },
                ],
            },
            isStudentCanceled(student: { canceled_at?: string | null }) {
                return !!student?.canceled_at
            },
        }

        const count = (MyCourses as any).computed.activeSelectedStudentsCount.call(ctx)
        expect(count).toBe(2)
    })

    it('returns canceled name class for canceled students', () => {
        const ctx = {
            isStudentCanceled(student: { canceled_at?: string | null }) {
                return !!student?.canceled_at
            },
        }

        const canceledClass = (MyCourses as any).methods.studentNameClass.call(ctx, { canceled_at: '2026-02-16 12:00:00' })
        const activeClass = (MyCourses as any).methods.studentNameClass.call(ctx, { canceled_at: null })

        expect(canceledClass).toBe('student-name--canceled')
        expect(activeClass).toBe('')
    })

    it('normalizes and joins classes for readable labels', () => {
        const ctx = {
            courseClasses: (MyCourses as any).methods.courseClasses,
        }
        const course = {
            classes: [' 2B ', '', '5A', null],
        }

        const classes = (MyCourses as any).methods.courseClasses.call(ctx, course)
        const text = (MyCourses as any).methods.courseClassesText.call(ctx, course)

        expect(classes).toEqual(['2B', '5A'])
        expect(text).toBe('2B, 5A')
    })

    it('does not duplicate the primary class in secondary class chips', () => {
        const ctx = {
            courseClasses: (MyCourses as any).methods.courseClasses,
        }
        const course = {
            classes: ['7A-BU', '7A-DG', '7S'],
        }

        const primaryClass = (MyCourses as any).methods.primaryCourseClass.call(ctx, course)
        const secondaryClasses = (MyCourses as any).methods.secondaryCourseClasses.call(ctx, course)
        const countLabel = (MyCourses as any).methods.courseClassesCountLabel.call(ctx, course)

        expect(primaryClass).toBe('7A-BU')
        expect(secondaryClasses).toEqual(['7A-DG', '7S'])
        expect(secondaryClasses).not.toContain(primaryClass)
        expect(countLabel).toBe('3 Klassen')
    })

    it('toggles between classic and alternative course view', () => {
        const ctx = {
            courses_view_variant: 'v1',
            setCoursesViewVariant(variant: string) {
                this.courses_view_variant = variant
            },
        }

        ;(MyCourses as any).methods.toggleCoursesViewVariant.call(ctx)
        expect(ctx.courses_view_variant).toBe('v2')

        ;(MyCourses as any).methods.toggleCoursesViewVariant.call(ctx)
        expect(ctx.courses_view_variant).toBe('v1')
    })
})
