import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('admin teaching semester boundary precedence', () => {
    it('prefers the selected schoolyear semester-2 start over the user fallback across overview pages', () => {
        const files = [
            'resources/js/pages/admin/teaching/overview/Overview.vue',
            'resources/js/pages/admin/teaching/more/More.vue',
            'resources/js/pages/admin/teaching/overview/components/CourseStudents.vue',
            'resources/js/pages/admin/teaching/overview/components/CourseStudent.vue',
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
            'resources/js/pages/admin/teaching/overview/components/CourseDates.vue',
            'resources/js/pages/admin/teaching/overview/components/MyTimetable.vue',
        ]

        for (const relativePath of files) {
            const source = readFileSync(resolve(process.cwd(), relativePath), 'utf8')

            expect(source).not.toContain('this.config?.user?.teaching_count_for_semester_2_date || this.config?.selected_schoolyear?.sem_2_start')
            expect(source).not.toContain('this.config?.user?.teaching_count_for_semester_2_date || schoolyear?.sem_2_start')
        }
    })
})
