import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'

describe('Student MyCourse required entry chip', () => {
    it('renders the required-entry chip with dynamic grade color', () => {
        const source = readFileSync('resources/js/pages/homepage/student/overview/myCourse/MyCourse.vue', 'utf8')

        expect(source).toContain('v-if="item.entry.is_required_entry"')
        expect(source).toContain(':color="entryGradeChipColor(item.entry)"')
        expect(source).toContain('Erforderlich')
    })
})
