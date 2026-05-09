import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import AttendanceMatrix from '@/pages/admin/teaching/more/components/AttendanceMatrix.vue'

describe('AttendanceMatrix defaults', () => {
    it('defaults student sort mode to name', () => {
        const data = (AttendanceMatrix as any).data.call({})

        expect(data.sortMode).toBe('last_name_first_name')
    })

    it('keeps date columns compact with minimal horizontal padding', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/more/components/AttendanceMatrix.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="attendance-date-col"')
        expect(source).toContain(":class=\"['attendance-date-col', attendanceCellClass(student.id, courseDate)]\"")
        expect(source).toContain('width: max-content;')
        expect(source).toContain('min-width: max-content;')
        expect(source).toContain('.attendance-date-col {')
        expect(source).toContain('width: 1%;')
        expect(source).toContain('padding-right: 2px !important;')
        expect(source).toContain('padding-left: 2px !important;')
    })
})
