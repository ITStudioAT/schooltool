import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'

describe('Student MyCourse required entry chip', () => {
    it('renders a red "Erforderlich" chip for required entries', () => {
        const source = readFileSync('resources/js/pages/homepage/student/overview/myCourse/MyCourse.vue', 'utf8')

        expect(source).toContain('v-if="item.entry.is_required_entry"')
        expect(source).toContain('color="error"')
        expect(source).toContain('Erforderlich')
    })
})
