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

    it('shows semester weighting graphically in view mode when two semesters are configured', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/Grading.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="semester-weight-visual"')
        expect(source).toContain('class="semester-weight-legend"')
        expect(source).toContain('aria-label="Gewichtung der Semester"')
        expect(source).toContain('semester-weight-bar__segment semester-weight-bar__segment--first')
        expect(source).toContain('semester-weight-bar__segment semester-weight-bar__segment--second')
        expect(source).toContain('.semester-weight-bar {')
        expect(source).not.toContain('<div class="text-body-1">1. Semester: {{ data.semester_1_weight }}%</div>')
        expect(source).not.toContain('<div class="text-body-1">2. Semester: {{ data.semester_2_weight }}%</div>')
    })

    it('renders semester count, semester weighting, and first semester calculation as separate areas', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/Grading.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<div class="grading-config-box mb-4">')
        expect(source).toContain('Anzahl der Semester')
        expect(source).toContain('Gewichtung der Semester')
        expect(source).toContain('Berechnung des 1. Semesters für die Jahresnote')
        expect(source).toContain('.grading-config-box {')
    })

    it('places the edit pencil in the Benotung header actions', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/Grading.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<template #header-actions>')
        expect(source).toContain('v-btn v-if="!is_editing" icon="mdi-pencil" size="x-small" color="primary" variant="flat" @click="is_editing = true"')
        expect(source).not.toContain('<div class="d-flex flex-row align-center justify-end mt-2 ga-2">')
    })
})
