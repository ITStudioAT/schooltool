import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import CategoryEvaluation from '@/pages/admin/teaching/settings/components/CategoryEvaluation.vue'

describe('Teaching category evaluation settings', () => {
    it('renders category evaluation values as a list with header add button, default star, and row actions', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/CategoryEvaluation.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<template #header-actions>')
        expect(source).toContain('prepend-icon="mdi-plus"')
        expect(source).toContain('@click="openCreateDialog"')
        expect(source).toContain('<v-list v-if="valueItems.length" density="comfortable" class="bg-transparent px-0">')
        expect(source).toContain('class="px-0 category-evaluation-list-item"')
        expect(source).toContain(":icon=\"item.value === defaultValue ? 'mdi-star' : 'mdi-star-outline'\"")
        expect(source).toContain('@click="setDefaultValue(item.value)"')
        expect(source).toContain('@click="openEditDialog(index)"')
        expect(source).toContain('@click="openDeleteDialog(index)"')
        expect(source).toContain('Standardwert')
        expect(source).toContain('<v-dialog v-model="item_dialog_open" persistent max-width="560">')
        expect(source).toContain("item_dialog_mode === 'create' ? 'Wert hinzufügen' : 'Wert bearbeiten'")
        expect(source).toContain('<v-dialog v-model="delete_dialog_open" persistent max-width="520">')
        expect(source).toContain('Möchten Sie diesen Wert der Kategoriebewertung wirklich löschen?')
        expect(source).toContain("deleteUsageCount > 0 ? 'warning' : 'info'")
        expect(source).toContain('Dieser Wert wird bereits verwendet:')
        expect(source).toContain('Dieser Wert wird derzeit nicht verwendet.')
        expect(source).toContain('{{ deleteUsageCount }} Einträge')
        expect(source).toContain('Beim Löschen werden auch alle betroffenen Einträge der Kategoriebewertung entfernt.')
        expect(source).toContain('@click="confirmDelete"')
        expect(source).toContain('color="error" variant="flat" :loading="category_evaluation_save_action === \'delete-item\'" :disabled="isSavingCategoryEvaluation" @click="confirmDelete"')
        expect(source).not.toContain('label="Standardwert"')
        expect(source).not.toContain('startEdit() {')
        expect(source).not.toContain('cancelEdit() {')
        expect(source).toContain('runCategoryEvaluationMutation(action, callback) {')
    })

    it('opens create and edit dialogs with the expected form state', () => {
        const methods = (CategoryEvaluation as any).methods
        const ctx: Record<string, any> = {
            item_dialog_mode: 'create',
            item_dialog_open: false,
            edit_index: null,
            valueItems: [
                { value: 'Offen', color: '#fb8c00' },
                { value: 'Bestanden', color: '#43a047' },
            ],
            dialog_form: {
                value: 'Alt',
                color: '#123456',
            },
            resetDialogForm: methods.resetDialogForm,
        }

        methods.openCreateDialog.call(ctx)
        expect(ctx.item_dialog_mode).toBe('create')
        expect(ctx.edit_index).toBeNull()
        expect(ctx.item_dialog_open).toBe(true)
        expect(ctx.dialog_form).toEqual({
            value: '',
            color: '#4f6fb3',
        })

        methods.openEditDialog.call(ctx, 1)
        expect(ctx.item_dialog_mode).toBe('edit')
        expect(ctx.edit_index).toBe(1)
        expect(ctx.item_dialog_open).toBe(true)
        expect(ctx.dialog_form).toEqual({
            value: 'Bestanden',
            color: '#43a047',
        })
    })

    it('saves a new value and makes it default when no default exists yet', async () => {
        const methods = (CategoryEvaluation as any).methods
        const persistCategoryEvaluation = vi.fn().mockResolvedValue(true)
        const closeItemDialog = vi.fn()
        const ctx: Record<string, any> = {
            edit_index: null,
            dialog_form: {
                value: 'Bestanden',
                color: '43a047',
            },
            valueItems: [],
            defaultValue: '',
            isDialogValid: true,
            category_evaluation_save_action: null,
            $nextTick: async () => {},
            persistCategoryEvaluation,
            closeItemDialog,
            runCategoryEvaluationMutation(action: string, callback: () => Promise<unknown>) {
                return methods.runCategoryEvaluationMutation.call(this, action, callback)
            },
        }

        await methods.saveItem.call(ctx)

        expect(persistCategoryEvaluation).toHaveBeenCalledWith([
            { value: 'Bestanden', color: '#43a047' },
        ], 'Bestanden')
        expect(closeItemDialog).toHaveBeenCalled()
    })

    it('updates the default value when the default entry is renamed', async () => {
        const methods = (CategoryEvaluation as any).methods
        const persistCategoryEvaluation = vi.fn().mockResolvedValue(true)
        const closeItemDialog = vi.fn()
        const ctx: Record<string, any> = {
            edit_index: 0,
            dialog_form: {
                value: 'Erledigt',
                color: '#43a047',
            },
            valueItems: [
                { value: 'Bestanden', color: '#43a047' },
                { value: 'Offen', color: '#fb8c00' },
            ],
            defaultValue: 'Bestanden',
            isDialogValid: true,
            category_evaluation_save_action: null,
            $nextTick: async () => {},
            persistCategoryEvaluation,
            closeItemDialog,
            runCategoryEvaluationMutation(action: string, callback: () => Promise<unknown>) {
                return methods.runCategoryEvaluationMutation.call(this, action, callback)
            },
        }

        await methods.saveItem.call(ctx)

        expect(persistCategoryEvaluation).toHaveBeenCalledWith([
            { value: 'Erledigt', color: '#43a047' },
            { value: 'Offen', color: '#fb8c00' },
        ], 'Erledigt')
        expect(closeItemDialog).toHaveBeenCalled()
    })

    it('persists the selected star value as the default', async () => {
        const methods = (CategoryEvaluation as any).methods
        const persistCategoryEvaluation = vi.fn().mockResolvedValue(true)
        const ctx: Record<string, any> = {
            valueItems: [
                { value: 'Offen', color: '#fb8c00' },
                { value: 'Bestanden', color: '#43a047' },
            ],
            defaultValue: 'Offen',
            category_evaluation_save_action: null,
            $nextTick: async () => {},
            persistCategoryEvaluation,
            runCategoryEvaluationMutation(action: string, callback: () => Promise<unknown>) {
                return methods.runCategoryEvaluationMutation.call(this, action, callback)
            },
        }

        await methods.setDefaultValue.call(ctx, 'Bestanden')

        expect(persistCategoryEvaluation).toHaveBeenCalledWith([
            { value: 'Offen', color: '#fb8c00' },
            { value: 'Bestanden', color: '#43a047' },
        ], 'Bestanden')
    })

    it('deletes the selected item via the confirmation dialog and falls back to the next default', async () => {
        const methods = (CategoryEvaluation as any).methods
        const persistCategoryEvaluation = vi.fn().mockResolvedValue(true)
        const closeDeleteDialog = vi.fn()
        const ctx: Record<string, any> = {
            delete_index: 0,
            valueItems: [
                { value: 'Offen', color: '#fb8c00' },
                { value: 'Bestanden', color: '#43a047' },
            ],
            defaultValue: 'Offen',
            deleteEntryDefinition: { value: 'Offen', color: '#fb8c00' },
            category_evaluation_save_action: null,
            $nextTick: async () => {},
            persistCategoryEvaluation,
            closeDeleteDialog,
            runCategoryEvaluationMutation(action: string, callback: () => Promise<unknown>) {
                return methods.runCategoryEvaluationMutation.call(this, action, callback)
            },
        }

        await methods.confirmDelete.call(ctx)

        expect(persistCategoryEvaluation).toHaveBeenCalledWith([
            { value: 'Bestanden', color: '#43a047' },
        ], 'Bestanden')
        expect(closeDeleteDialog).toHaveBeenCalled()
    })

    it('computes the used count for the selected delete item', () => {
        const ctx: Record<string, any> = {
            schemaId: 'schema-1',
            settings: {
                teaching_schemas: [
                    {
                        id: 'schema-1',
                        grading: {
                            category_evaluation_usage_counts: {
                                Offen: 3,
                                Bestanden: 1,
                            },
                        },
                    },
                ],
            },
            delete_index: 0,
            valueItems: [
                { value: 'Offen', color: '#fb8c00' },
                { value: 'Bestanden', color: '#43a047' },
            ],
        }

        ctx.deleteEntryDefinition = (CategoryEvaluation as any).computed.deleteEntryDefinition.call(ctx)
        ctx.categoryEvaluationUsageCounts = (CategoryEvaluation as any).computed.categoryEvaluationUsageCounts.call(ctx)

        expect((CategoryEvaluation as any).computed.deleteUsageCount.call(ctx)).toBe(3)
    })

    it('rejects duplicate values in the dialog', () => {
        const ctx: Record<string, any> = {
            item_dialog_mode: 'create',
            edit_index: null,
            dialog_form: {
                value: 'Offen',
                color: '#fb8c00',
            },
            valueItems: [
                { value: 'Offen', color: '#fb8c00' },
                { value: 'Bestanden', color: '#43a047' },
            ],
            hasDuplicateDialogValue: (CategoryEvaluation as any).methods.hasDuplicateDialogValue,
        }

        expect((CategoryEvaluation as any).computed.dialogValueError.call(ctx)).toBe('Dieser Wert ist bereits vorhanden.')
        expect((CategoryEvaluation as any).computed.isDialogValid.call(ctx)).toBe(false)
    })
})
