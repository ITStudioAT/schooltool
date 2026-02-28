import { describe, expect, it, vi } from 'vitest'
import MaterialsSettingsView from '@/pages/admin/materials/components/views/MaterialsSettingsView.vue'

const component = MaterialsSettingsView as any
const methods = component.methods
const computed = component.computed

function buildContext() {
    const ctx: any = {
        materialCardStore: {
            config: {
                classification_tree: [
                    {
                        id: 10,
                        name: 'Informatik',
                        topics: [
                            {
                                id: 20,
                                name: 'Grundlagen',
                                units: [
                                    {
                                        id: 30,
                                        name: 'ReadOnly Unit',
                                        is_linked: true,
                                        linked_permission: 'read_only',
                                        linked_permission_label: 'NUR LESEN',
                                    },
                                    {
                                        id: 31,
                                        name: 'Writable Linked Unit',
                                        is_linked: true,
                                        linked_permission: 'read_write',
                                        linked_permission_label: 'LESEN/SCHREIBEN',
                                    },
                                    {
                                        id: 32,
                                        name: 'Manual Unit',
                                        is_linked: false,
                                    },
                                ],
                            },
                        ],
                    },
                ],
            },
        },
        isSavingSubjectCatalog: false,
        subjectCatalogEditor: {
            mode: '',
            name: '',
            subjectId: null,
            topicId: null,
            unitId: null,
            parentLabel: '',
        },
        deleteConfirmDialog: {
            open: false,
            kind: '',
            id: null,
            label: '',
        },
        focusSubjectCatalogEditorInput: vi.fn(),
    }

    ctx.normalizeTreeName = methods.normalizeTreeName.bind(ctx)
    ctx.normalizeLinkedPermission = methods.normalizeLinkedPermission.bind(ctx)
    ctx.findUnitNodeById = methods.findUnitNodeById.bind(ctx)
    ctx.isUnitReadOnlyLinked = methods.isUnitReadOnlyLinked.bind(ctx)
    ctx.canEditUnitNode = methods.canEditUnitNode.bind(ctx)
    ctx.canEditUnitById = methods.canEditUnitById.bind(ctx)
    ctx.subjectTreeItems = computed.subjectTreeItems.call(ctx)

    return ctx
}

describe('MaterialsSettingsView unit edit guards', () => {
    it('marks linked read-only units as non-editable', () => {
        const ctx = buildContext()
        const units = ctx.subjectTreeItems[0].topics[0].units
        const readOnlyUnit = units.find((entry: any) => Number(entry.id) === 30)
        const writableLinkedUnit = units.find((entry: any) => Number(entry.id) === 31)
        const manualUnit = units.find((entry: any) => Number(entry.id) === 32)

        expect(readOnlyUnit.isLinked).toBe(true)
        expect(readOnlyUnit.linkedPermission).toBe('read_only')
        expect(ctx.canEditUnitNode(readOnlyUnit)).toBe(false)
        expect(ctx.canEditUnitNode(writableLinkedUnit)).toBe(true)
        expect(ctx.canEditUnitNode(manualUnit)).toBe(true)
    })

    it('blocks rename action for linked read-only units', () => {
        const ctx = buildContext()
        const units = ctx.subjectTreeItems[0].topics[0].units
        const readOnlyUnit = units.find((entry: any) => Number(entry.id) === 30)
        const writableLinkedUnit = units.find((entry: any) => Number(entry.id) === 31)

        methods.startRenameUnit.call(ctx, readOnlyUnit)
        expect(ctx.subjectCatalogEditor.mode).toBe('')
        expect(ctx.focusSubjectCatalogEditorInput).not.toHaveBeenCalled()

        methods.startRenameUnit.call(ctx, writableLinkedUnit)
        expect(ctx.subjectCatalogEditor.mode).toBe('rename_unit')
        expect(ctx.subjectCatalogEditor.unitId).toBe(31)
        expect(ctx.focusSubjectCatalogEditorInput).toHaveBeenCalled()
    })

    it('blocks delete dialog for linked read-only units', () => {
        const ctx = buildContext()
        const units = ctx.subjectTreeItems[0].topics[0].units
        const readOnlyUnit = units.find((entry: any) => Number(entry.id) === 30)
        const writableLinkedUnit = units.find((entry: any) => Number(entry.id) === 31)

        methods.openDeleteConfirm.call(ctx, 'unit', readOnlyUnit)
        expect(ctx.deleteConfirmDialog.open).toBe(false)

        methods.openDeleteConfirm.call(ctx, 'unit', writableLinkedUnit)
        expect(ctx.deleteConfirmDialog.open).toBe(true)
        expect(ctx.deleteConfirmDialog.kind).toBe('unit')
        expect(ctx.deleteConfirmDialog.id).toBe(31)
    })

    it('still allows moving linked read-only units within a topic', () => {
        const ctx = buildContext()
        const topic = ctx.subjectTreeItems[0].topics[0]
        const units = topic.units
        const readOnlyUnit = units.find((entry: any) => Number(entry.id) === 30)

        expect(ctx.canEditUnitNode(readOnlyUnit)).toBe(false)
        expect(methods.canMoveUnitDown.call(ctx, topic, readOnlyUnit, 0)).toBe(true)
    })
})
