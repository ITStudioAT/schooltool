import { describe, expect, it } from 'vitest'
import MyCourses from '@/pages/admin/teaching/overview/components/MyCourses.vue'

describe('MyCourses counts', () => {
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
})
