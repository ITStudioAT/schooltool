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
                                        id: 33,
                                        name: 'Append Linked Unit',
                                        is_linked: true,
                                        linked_permission: 'read_append',
                                        linked_permission_label: 'LESEN/HINZUFÜGEN',
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
    it('marks linked read-only and append-only units as non-editable', () => {
        const ctx = buildContext()
        const units = ctx.subjectTreeItems[0].topics[0].units
        const readOnlyUnit = units.find((entry: any) => Number(entry.id) === 30)
        const writableLinkedUnit = units.find((entry: any) => Number(entry.id) === 31)
        const appendLinkedUnit = units.find((entry: any) => Number(entry.id) === 33)
        const manualUnit = units.find((entry: any) => Number(entry.id) === 32)

        expect(readOnlyUnit.isLinked).toBe(true)
        expect(readOnlyUnit.linkedPermission).toBe('read_only')
        expect(ctx.canEditUnitNode(readOnlyUnit)).toBe(false)
        expect(ctx.canEditUnitNode(appendLinkedUnit)).toBe(false)
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

    it('can edit lookup by id matches read-only link permissions', () => {
        const ctx = buildContext()

        expect(ctx.canEditUnitById(30)).toBe(false)
        expect(ctx.canEditUnitById(31)).toBe(true)
        expect(ctx.canEditUnitById(32)).toBe(true)
        expect(ctx.canEditUnitById(9999)).toBe(false)
    })

    it('blocks reclassify dialog open for linked read-only units', () => {
        const ctx = buildContext()
        const subject = ctx.subjectTreeItems[0]
        const topic = subject.topics[0]
        const readOnlyUnit = topic.units.find((entry: any) => Number(entry.id) === 30)
        ctx.reclassifyDialog = {
            open: false,
            kind: '',
            mode: '',
            sourceId: null,
            sourceName: '',
            sourceSubjectId: null,
            sourceTopicId: null,
            targetSubjectId: null,
            targetTopicId: null,
            newSubjectName: '',
            newTopicName: '',
        }

        methods.openReclassifyDialogForUnit.call(ctx, subject, topic, readOnlyUnit)
        expect(ctx.reclassifyDialog.open).toBe(false)
        expect(ctx.reclassifyDialog.sourceId).toBeNull()
    })

    it('moves linked read-only units inside topic ordering', async () => {
        const ctx = buildContext()
        const topic = ctx.subjectTreeItems[0].topics[0]
        const readOnlyUnit = topic.units.find((entry: any) => Number(entry.id) === 30)
        ctx.materialCardStore.moveUnit = vi.fn(async () => true)
        ctx.withSubjectCatalogSaving = vi.fn(async (task: Function) => task())

        await methods.moveUnit.call(ctx, readOnlyUnit, 'down')

        expect(ctx.materialCardStore.moveUnit).toHaveBeenCalledWith(30, 'down')
        expect(ctx.withSubjectCatalogSaving).toHaveBeenCalledTimes(1)
    })

    it('confirmDeleteEntry blocks deletion for linked read-only units', async () => {
        const ctx = buildContext()
        ctx.deleteConfirmDialog = {
            open: true,
            kind: 'unit',
            id: 30,
            label: 'ReadOnly Unit',
        }
        ctx.materialCardStore.deleteUnit = vi.fn(async () => true)
        ctx.withSubjectCatalogSaving = vi.fn(async (task: Function) => task())
        ctx.cancelDeleteConfirm = vi.fn()
        ctx.resetSubjectCatalogEditor = vi.fn()

        await methods.confirmDeleteEntry.call(ctx)

        expect(ctx.materialCardStore.deleteUnit).not.toHaveBeenCalled()
        expect(ctx.withSubjectCatalogSaving).not.toHaveBeenCalled()
        expect(ctx.cancelDeleteConfirm).not.toHaveBeenCalled()
    })

    it('saveSubjectCatalogEditor blocks rename for linked read-only units', async () => {
        const ctx = buildContext()
        ctx.canSaveSubjectCatalogEditor = true
        ctx.subjectCatalogEditor = {
            mode: 'rename_unit',
            name: 'Renamed ReadOnly',
            subjectId: null,
            topicId: null,
            unitId: 30,
            parentLabel: '',
        }
        ctx.materialCardStore.updateUnit = vi.fn(async () => true)
        ctx.withSubjectCatalogSaving = vi.fn(async (task: Function) => task())
        ctx.resetSubjectCatalogEditor = vi.fn()

        await methods.saveSubjectCatalogEditor.call(ctx)

        expect(ctx.materialCardStore.updateUnit).not.toHaveBeenCalled()
        expect(ctx.resetSubjectCatalogEditor).not.toHaveBeenCalled()
    })

    it('saveSubjectCatalogEditor allows rename for writable linked units', async () => {
        const ctx = buildContext()
        ctx.canSaveSubjectCatalogEditor = true
        ctx.subjectCatalogEditor = {
            mode: 'rename_unit',
            name: 'Renamed Writable',
            subjectId: null,
            topicId: null,
            unitId: 31,
            parentLabel: '',
        }
        ctx.materialCardStore.updateUnit = vi.fn(async () => true)
        ctx.withSubjectCatalogSaving = vi.fn(async (task: Function) => task())
        ctx.resetSubjectCatalogEditor = vi.fn()

        await methods.saveSubjectCatalogEditor.call(ctx)

        expect(ctx.materialCardStore.updateUnit).toHaveBeenCalledWith(31, 'Renamed Writable')
        expect(ctx.resetSubjectCatalogEditor).toHaveBeenCalledTimes(1)
    })

    it('openReclassifyDialogForUnit allows writable linked units', () => {
        const ctx = buildContext()
        const subject = ctx.subjectTreeItems[0]
        const topic = subject.topics[0]
        const writableLinkedUnit = topic.units.find((entry: any) => Number(entry.id) === 31)
        Object.defineProperty(ctx, 'reclassifyTargetTopicItems', {
            configurable: true,
            enumerable: true,
            get: () => [{ id: 20, name: 'Grundlagen' }],
        })

        methods.openReclassifyDialogForUnit.call(ctx, subject, topic, writableLinkedUnit)

        expect(ctx.reclassifyDialog.open).toBe(true)
        expect(ctx.reclassifyDialog.kind).toBe('unit')
        expect(ctx.reclassifyDialog.sourceId).toBe(31)
        expect(ctx.reclassifyDialog.sourceSubjectId).toBe(10)
        expect(ctx.reclassifyDialog.sourceTopicId).toBe(20)
    })

    it('confirmDeleteEntry allows deletion for writable linked units', async () => {
        const ctx = buildContext()
        ctx.deleteConfirmDialog = {
            open: true,
            kind: 'unit',
            id: 31,
            label: 'Writable Linked Unit',
        }
        ctx.materialCardStore.deleteUnit = vi.fn(async () => true)
        ctx.withSubjectCatalogSaving = vi.fn(async (task: Function) => task())
        ctx.cancelDeleteConfirm = vi.fn()
        ctx.resetSubjectCatalogEditor = vi.fn()

        await methods.confirmDeleteEntry.call(ctx)

        expect(ctx.materialCardStore.deleteUnit).toHaveBeenCalledWith(31)
        expect(ctx.withSubjectCatalogSaving).toHaveBeenCalledTimes(1)
        expect(ctx.cancelDeleteConfirm).toHaveBeenCalledTimes(1)
    })
})
