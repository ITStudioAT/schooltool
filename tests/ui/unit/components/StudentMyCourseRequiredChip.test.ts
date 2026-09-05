import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'

describe('Student MyCourse required entry chip', () => {
    it('omits the required-entry badge and keeps the grade chip', () => {
        const source = readFileSync('resources/js/pages/homepage/student/overview/myCourse/MyCourse.vue', 'utf8')

        expect(source).not.toContain('v-if="entry.is_required_entry"')
        expect(source).toContain('class="entry-grade"')
        expect(source).toContain(':color="entryGradeChipColor(entry)"')
        expect(source).not.toContain('Erforderlich')
    })
})
