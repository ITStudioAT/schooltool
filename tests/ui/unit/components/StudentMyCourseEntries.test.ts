import { describe, expect, it } from 'vitest'
import MyCourse from '@/pages/homepage/student/overview/myCourse/MyCourse.vue'

describe('Student MyCourse entry grade chip color', () => {
    it('returns error color for NA grade chips', () => {
        const color = (MyCourse as any).methods.entryGradeChipColor({ grade: 'NA' })

        expect(color).toBe('error')
    })

    it('returns success color for non-NA grades', () => {
        const color = (MyCourse as any).methods.entryGradeChipColor({ grade: '2' })

        expect(color).toBe('success')
    })

    it('returns error color for open grades', () => {
        const color = (MyCourse as any).methods.entryGradeChipColor({ grade: '' })

        expect(color).toBe('error')
    })
})
