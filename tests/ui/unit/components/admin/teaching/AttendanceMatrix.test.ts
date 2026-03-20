import { describe, expect, it } from 'vitest'
import AttendanceMatrix from '@/pages/admin/teaching/more/components/AttendanceMatrix.vue'

describe('AttendanceMatrix defaults', () => {
    it('defaults student sort mode to name', () => {
        const data = (AttendanceMatrix as any).data.call({})

        expect(data.sortMode).toBe('last_name_first_name')
    })
})
