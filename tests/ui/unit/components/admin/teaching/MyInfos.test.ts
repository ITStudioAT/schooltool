import { describe, expect, it } from 'vitest'
import MyInfos from '@/pages/admin/teaching/overview/components/MyInfos.vue'

describe('MyInfos counts', () => {
    it('excludes canceled students from myStudentCount', () => {
        const ctx = {
            myCourses: [
                {
                    students_info: [
                        { id: 1, canceled_at: null },
                        { id: 2, canceled_at: '2026-02-16 12:00:00' },
                    ],
                },
                {
                    students_info: [
                        { id: 1, canceled_at: null },
                        { id: 3, canceled_at: '' },
                    ],
                },
            ],
        }

        const count = (MyInfos as any).computed.myStudentCount.call(ctx)
        expect(count).toBe(2)
    })
})
