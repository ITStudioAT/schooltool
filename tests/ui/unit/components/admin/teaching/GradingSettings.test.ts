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

        expect(source).toContain('Jede Arbeit in dieser Kategorie ist verpflichtend')
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

        expect(source).toContain('label="Anzeige der Bewertung der Kategorie"')
        expect(source).toContain("category_form: { name: '', weight: 0, require_all_entries: false, category_evaluation_enabled: false, works: [] }")
        expect(source).toContain('Anzeige der Bewertung der Kategorie')
        expect(source).toContain('category_dialog_open: false')
    })

    it('renders categories as a flat list with a large leading percentage', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/Grading.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="grading-category-item mb-3 pa-3 rounded"')
        expect(source).toContain('class="grading-category-header"')
        expect(source).toContain('class="grading-category-weight"')
        expect(source).toContain(":class=\"{ 'grading-category-weight--invalid': totalCategoryWeight !== 100 }\"")
        expect(source).toContain('class="grading-category-main"')
        expect(source).toContain('class="grading-category-meta"')
        expect(source).toContain('class="grading-category-actions"')
        expect(source).toContain('@click="startEditCategory(index)"')
        expect(source).toContain('class="grading-category-details mt-3 pt-3"')
        expect(source).toContain('.grading-category-weight {')
        expect(source).toContain('.grading-category-weight--invalid {')
        expect(source).toContain('color: rgb(var(--v-theme-error));')
        expect(source).toContain('font-size: 2rem;')
        expect(source).toContain('Wert der Arbeiten')
        expect(source).toContain('.grading-category-header {')
        expect(source).toContain('flex-wrap: wrap;')
        expect(source).toContain('.grading-category-meta {')
        expect(source).toContain('startEditCategory(index) {')
        expect(source).toContain('this.is_editing = true')
        expect(source).toContain("this.editing_section = 'category'")
        expect(source).toContain("this.category_dialog_mode = 'edit'")
        expect(source).not.toContain('{{ category.works.length }} Arbeit(en)')
        expect(source).not.toContain('Zugewiesene Arbeiten (Mittelwert wird berechnet)')
    })

    it('shows semester weighting graphically in view mode when two semesters are configured', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/Grading.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="semester-weight-visual"')
        expect(source).toContain('class="grading-section-heading mb-2"')
        expect(source).toContain('class="semester-weight-legend"')
        expect(source).toContain('class="semester-weight-legend-item semester-weight-legend-item--align-end"')
        expect(source).toContain('<span class="text-body-2 font-weight-medium">{{ data.semester_2_weight }}%</span>')
        expect(source).toContain('<v-chip size="small" color="error" variant="flat">2. Semester</v-chip>')
        expect(source).toContain('v-if="isLocalSemesterWeightEditing"')
        expect(source).toContain('@click="startSemesterWeightEdit"')
        expect(source).toContain('variant="text"')
        expect(source).toContain('@click="startSemesterWeightEdit"')
        expect(source).toContain('v-if="isSemesterWeightEditing" class="d-flex flex-row align-center ga-4"')
        expect(source).toContain('isLocalSemesterWeightEditing() {')
        expect(source).toContain("return this.is_editing && this.editing_section === 'semester_weight'")
        expect(source).toContain('isSemesterWeightEditing() {')
        expect(source).toContain("return this.is_editing && ['global', 'semester_weight'].includes(this.editing_section)")
        expect(source).toContain('aria-label="Gewichtung der Semester"')
        expect(source).toContain('semester-weight-bar__segment semester-weight-bar__segment--first')
        expect(source).toContain('semester-weight-bar__segment semester-weight-bar__segment--second')
        expect(source).toContain('.grading-section-heading {')
        expect(source).toContain('.semester-weight-legend-item--align-end {')
        expect(source).toContain('margin-left: auto;')
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
        expect(source).toContain('@click="startSemesterCountEdit"')
        expect(source).toContain('v-btn-toggle v-if="isSemesterCountEditing"')
        expect(source).toContain('v-if="isLocalSemesterCountEditing"')
        expect(source).toContain('isLocalSemesterCountEditing() {')
        expect(source).toContain("return this.is_editing && this.editing_section === 'semester_count'")
        expect(source).toContain('isSemesterCountEditing() {')
        expect(source).toContain("return this.is_editing && ['global', 'semester_count'].includes(this.editing_section)")
        expect(source).toContain('Gewichtung der Semester')
        expect(source).toContain('Einbeziehung des 1. Semesters für die Jahresnote')
        expect(source).toContain('class="grading-section-heading mb-2"')
        expect(source).toContain('@click="startSemesterInclusionEdit"')
        expect(source).toContain('v-if="isLocalSemesterInclusionEditing"')
        expect(source).toContain('isLocalSemesterInclusionEditing() {')
        expect(source).toContain("return this.is_editing && this.editing_section === 'semester_inclusion'")
        expect(source).toContain('v-radio-group v-if="isSemesterInclusionEditing"')
        expect(source).toContain('isSemesterInclusionEditing() {')
        expect(source).toContain("return this.is_editing && ['global', 'semester_inclusion'].includes(this.editing_section)")
        expect(source).toContain('.grading-config-box {')
    })

    it('keeps semester inclusion editing scoped away from semester weighting', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/Grading.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('startSemesterInclusionEdit() {')
        expect(source).toContain("this.editing_section = 'semester_inclusion'")
        expect(source).toContain('v-if="isLocalSemesterInclusionEditing"')
        expect(source).toContain('v-if="isSemesterInclusionEditing" v-model="data.use_semester_grade_only"')
        expect(source).toContain('v-if="isSemesterWeightEditing" class="d-flex flex-row align-center ga-4"')
        expect(source).toContain("return this.is_editing && ['global', 'semester_weight'].includes(this.editing_section)")
        expect(source).not.toContain("return this.is_editing && ['semester_inclusion', 'semester_weight'].includes(this.editing_section)")
    })

    it('keeps semester count editing scoped to the semester count area', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/Grading.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('startSemesterCountEdit() {')
        expect(source).toContain("this.editing_section = 'semester_count'")
        expect(source).toContain('v-btn-toggle v-if="isSemesterCountEditing"')
        expect(source).toContain('v-if="isLocalSemesterCountEditing"')
        expect(source).toContain('v-if="isSemesterWeightEditing" class="d-flex flex-row align-center ga-4"')
        expect(source).toContain("return this.is_editing && ['global', 'semester_count'].includes(this.editing_section)")
        expect(source).not.toContain("return this.is_editing && ['semester_count', 'semester_weight'].includes(this.editing_section)")
    })

    it('groups the categories per semester section in its own highlighted container', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/Grading.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<div class="grading-categories-section mb-4">')
        expect(source).toContain('<div class="grading-categories-heading mb-4">')
        expect(source).toContain('Kategorien pro Semester')
        expect(source).toContain('prepend-icon="mdi-plus"')
        expect(source).toContain('@click="startAddCategory"')
        expect(source).toContain('.grading-categories-heading {')
        expect(source).toContain('.grading-categories-section {')
        expect(source).toContain('background: rgba(var(--v-theme-primary), 0.05);')
        expect(source).toContain('border: 1px solid rgba(var(--v-theme-primary), 0.2);')
    })

    it('creates and edits categories in a persistent dialog', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/Grading.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<v-dialog v-model="category_dialog_open" persistent max-width="760">')
        expect(source).toContain("category_dialog_mode === 'create' ? 'Kategorie erstellen' : 'Kategorie bearbeiten'")
        expect(source).toContain('saveCategoryDialog() {')
        expect(source).toContain('cancelCategoryDialog() {')
        expect(source).toContain('startAddCategory() {')
        expect(source).toContain('this.category_dialog_open = true')
        expect(source).toContain("this.editing_section = 'category'")
        expect(source).toContain('v-model="category_form.name"')
        expect(source).toContain('v-model="category_form.weight"')
        expect(source).toContain('<v-slider')
        expect(source).toContain(':step="1"')
        expect(source).toContain('{{ category_form.weight }}%')
        expect(source).toContain('Arbeiten in dieser Kategorie')
        expect(source).toContain('v-for="work in teaching_works"')
        expect(source).toContain('isDialogWorkSelected(work.short_name)')
        expect(source).toContain('getDialogWorkFactor(work.short_name)')
        expect(source).toContain('updateDialogWorkSelection(work.short_name, value)')
        expect(source).toContain('setDialogWorkFactor(work.short_name, value)')
        expect(source).toContain('<v-btn :value="33" size="x-small">33%</v-btn>')
        expect(source).toContain('<v-btn :value="66" size="x-small">66%</v-btn>')
        expect(source).toContain('isDialogWorkSelected(shortName) {')
        expect(source).toContain('getDialogWorkFactor(shortName) {')
        expect(source).toContain('updateDialogWorkSelection(shortName, selected) {')
        expect(source).toContain('setDialogWorkFactor(shortName, factor) {')
        expect(source).toContain('.grading-dialog-work-item {')
        expect(source).toContain('return Boolean(this.category_form.name.trim() && this.category_form.weight > 0)')
        expect(source).toContain('@click="saveCategoryDialog"')
        expect(source).not.toContain('v-if="is_adding_category" class="grading-category-item mb-3 pa-3 rounded"')
    })

    it('places the edit pencil in the Benotung header actions', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/Grading.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<template #header-actions>')
        expect(source).toContain('v-btn v-if="is_editing && !isLocalSemesterCountEditing && !isLocalSemesterWeightEditing && !isLocalSemesterInclusionEditing" icon="mdi-check" size="x-small" color="success" variant="flat" :disabled="!isCurrentEditValid" @click="save"')
        expect(source).toContain('v-btn v-if="is_editing && !isLocalSemesterCountEditing && !isLocalSemesterWeightEditing && !isLocalSemesterInclusionEditing" icon="mdi-close" size="x-small" color="warning" variant="flat" @click="exitEditMode"')
        expect(source).not.toContain('v-btn v-if="!is_editing" icon="mdi-pencil" size="x-small" color="primary" variant="flat" @click="startGlobalEdit"')
        expect(source).not.toContain('startGlobalEdit() {')
        expect(source).toContain(':disabled="!isCurrentEditValid"')
        expect(source).not.toContain('<div class="d-flex flex-row align-center justify-end mt-2 ga-2">')
    })
})
