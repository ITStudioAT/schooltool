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

    it('supports category evaluation toggle with default false in new and edited categories', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/Grading.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('label="Kategoriebewertung"')
        expect(source).toContain("new_category: { name: '', weight: '', require_all_entries: false, category_evaluation_enabled: false }")
        expect(source).toContain("edit_category: { name: '', weight: '', require_all_entries: false, category_evaluation_enabled: false }")
        expect(source).toContain('v-if="category.category_evaluation_enabled" size="x-small" color="info" variant="flat"')
    })

    it('uses a wrapped category header layout so overview chips are not clipped', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/Grading.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="grading-category-header"')
        expect(source).toContain('class="grading-category-main"')
        expect(source).toContain('class="grading-category-meta"')
        expect(source).toContain('class="grading-category-actions"')
        expect(source).toContain('.grading-category-header {')
        expect(source).toContain('flex-wrap: wrap;')
        expect(source).toContain('.grading-category-meta {')
    })
})
