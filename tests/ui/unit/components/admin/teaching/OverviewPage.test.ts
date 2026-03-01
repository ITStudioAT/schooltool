import { describe, expect, it } from 'vitest'
import Overview from '@/pages/admin/teaching/overview/Overview.vue'

describe('Teaching overview controls', () => {
    it('returns selected course display including class list', () => {
        const ctx = {
            selected_course: {
                title: 'Mathematik',
                classes: ['5A', '5B'],
            },
        }

        const value = (Overview as any).computed.selectedCourseDisplay.call(ctx)
        expect(value).toBe('Mathematik (5A, 5B)')
    })

    it('resets course context when showing courses again', () => {
        const ctx = {
            show_my_courses: false,
            action_2: 'course_student_view',
            selected_course: { id: 22, title: 'Physik' },
            selected_course_id: 22,
            selected_course_student: { id: 99 },
            show_infos: true,
        }

        ;(Overview as any).methods.toggleMyCourses.call(ctx)

        expect(ctx.show_my_courses).toBe(true)
        expect(ctx.action_2).toBe('')
        expect(ctx.selected_course).toBeNull()
        expect(ctx.selected_course_id).toBeNull()
        expect(ctx.selected_course_student).toBeNull()
        expect(ctx.show_infos).toBe(false)
    })

    it('returns the correct toggle icon state', () => {
        expect((Overview as any).methods.toggleIcon(true)).toBe('mdi-eye')
        expect((Overview as any).methods.toggleIcon(false)).toBe('mdi-eye-off')
    })

    it('hides Meine Fächer and enables Infos when a course is selected', () => {
        const ctx = {
            show_my_courses: true,
            show_infos: false,
        }

        ;(Overview as any).watch.selected_course.call(ctx, { id: 7, title: 'Biologie' })

        expect(ctx.show_my_courses).toBe(false)
        expect(ctx.show_infos).toBe(true)
    })
})
