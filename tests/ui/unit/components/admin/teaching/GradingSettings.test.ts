import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('Grading settings required entries chip', () => {
    it('uses the localized label and readable chip color', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/Grading.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('Alle erforderlich')
        expect(source).toContain('v-if="category.require_all_entries" size="x-small" color="primary" variant="tonal"')
        expect(source).not.toContain('v-if="category.require_all_entries" size="x-small" color="warning" variant="tonal"')
        expect(source).not.toContain('\n                                    require_all_entries\n')
    })
})
