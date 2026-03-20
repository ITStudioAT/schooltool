import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import CourseWorks from '@/pages/admin/teaching/overview/components/CourseWorks.vue'

describe('CourseWorks defaults', () => {
    it('defaults student sort mode to name', () => {
        const data = (CourseWorks as any).data.call({
            emptyWorkForm: () => ({}),
        })

        expect(data.students_sort_mode).toBe('last_name_first_name')
    })
})

describe('CourseWorks title rendering', () => {
    it('renders work title in second line in Arbeiten list', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="text-caption text-medium-emphasis work-type-first-line"')
        expect(source).toContain('class="text-body-2 work-title-second-line"')
        expect(source).not.toContain('class="text-caption text-medium-emphasis work-title-second-line"')
        expect(source).not.toContain('<span v-if="work.title || work.description">– {{ work.title || work.description }}</span>')
    })
})
