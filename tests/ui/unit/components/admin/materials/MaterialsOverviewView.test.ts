import { describe, expect, it, vi } from 'vitest'
import MaterialsOverviewView from '@/pages/admin/materials/components/views/MaterialsOverviewView.vue'

describe('MaterialsOverviewView', () => {
    it('keeps linked topic metadata from classification tree in subjects overview', () => {
        const methods = (MaterialsOverviewView as any)?.methods || {}
        const vm: any = {
            ...methods,
            typeOptions: [],
            statusOptions: [],
            defaultStatusValue: 'inbox',
            classificationTree: [
                {
                    id: 10,
                    name: 'Deutsch',
                    topics: [
                        {
                            id: 20,
                            name: 'Literatur',
                            is_linked: true,
                            linked_permission: 'read_only',
                            linked_permission_label: 'NUR LESEN',
                            units: [],
                        },
                    ],
                },
            ],
        }

        const items = methods.buildSubjectsContentsOverviewItems.call(vm, [], {})
        expect(items).toHaveLength(1)

        const topic = items[0]?.topics?.[0]
        expect(topic).toBeTruthy()
        expect(topic.isLinked).toBe(true)
        expect(topic.linkedPermission).toBe('read_only')
        expect(topic.linkedPermissionLabel).toBe('NUR LESEN')
    })

    it('keeps duplicate unit names separated by unit_id in subjects overview', () => {
        const methods = (MaterialsOverviewView as any)?.methods || {}
        const vm: any = {
            ...methods,
            typeOptions: [],
            statusOptions: [],
            defaultStatusValue: 'inbox',
            classificationTree: [
                {
                    id: 10,
                    name: 'Deutsch',
                    topics: [
                        {
                            id: 20,
                            name: 'Literatur',
                            units: [
                                { id: 100, name: 'Informatik', is_linked: true, linked_permission: 'read_only', linked_permission_label: 'NUR LESEN' },
                                { id: 101, name: 'Informatik', is_linked: true, linked_permission: 'read_only', linked_permission_label: 'NUR LESEN' },
                            ],
                        },
                    ],
                },
            ],
        }

        const cards = [
            {
                id: 501,
                title: 'Material A',
                type: '',
                status: 'inbox',
                attachments: [],
                is_linked: true,
                linked_permission: 'read_only',
                linked_permission_label: 'NUR LESEN',
                classifications: [
                    {
                        id: 9001,
                        subject_id: 10,
                        topic_id: 20,
                        unit_id: 100,
                        subject: 'Deutsch',
                        topic: 'Literatur',
                        unit: 'Informatik',
                    },
                ],
            },
            {
                id: 502,
                title: 'Material B',
                type: '',
                status: 'inbox',
                attachments: [],
                is_linked: true,
                linked_permission: 'read_only',
                linked_permission_label: 'NUR LESEN',
                classifications: [
                    {
                        id: 9002,
                        subject_id: 10,
                        topic_id: 20,
                        unit_id: 101,
                        subject: 'Deutsch',
                        topic: 'Literatur',
                        unit: 'Informatik',
                    },
                ],
            },
        ]

        const items = methods.buildSubjectsContentsOverviewItems.call(vm, cards, {})
        expect(Array.isArray(items)).toBe(true)
        expect(items).toHaveLength(1)

        const topic = items[0]?.topics?.[0]
        expect(topic).toBeTruthy()
        expect(Array.isArray(topic.units)).toBe(true)
        expect(topic.units).toHaveLength(2)

        const unitsById = new Map(topic.units.map((unit: any) => [Number(unit?.id || 0), unit]))
        const firstUnit = unitsById.get(100)
        const secondUnit = unitsById.get(101)

        expect(firstUnit).toBeTruthy()
        expect(secondUnit).toBeTruthy()
        expect(firstUnit.materials).toHaveLength(1)
        expect(secondUnit.materials).toHaveLength(1)
        expect(Number(firstUnit.materials[0]?.id || 0)).toBe(501)
        expect(Number(secondUnit.materials[0]?.id || 0)).toBe(502)
    })

    it('hides source toggle and forces workspace source when overview mode is forced', () => {
        const methods = (MaterialsOverviewView as any)?.methods || {}
        const computed = (MaterialsOverviewView as any)?.computed || {}
        const vm: any = {
            ...methods,
            forcedOverviewMode: 'subjects_contents',
            overviewViewMode: 'subjects_contents',
            isSubjectsContentsOverview: true,
            subjectsContentsSource: 'shared',
        }

        expect(computed.showSubjectsContentsSourceToggle.call(vm)).toBe(false)
        expect(computed.isWorkspaceSubjectsContentsSource.call(vm)).toBe(true)
    })

    it('switches subjects source and loads shared/workspace data', () => {
        const methods = (MaterialsOverviewView as any)?.methods || {}
        const loadSubjectsContentsOverview = vi.fn()
        const loadSharedObjectsForMe = vi.fn()
        const vm: any = {
            ...methods,
            forcedOverviewMode: '',
            subjectsContentsSource: 'workspace',
            isSubjectsContentsOverview: true,
            loadSubjectsContentsOverview,
            loadSharedObjectsForMe,
        }

        methods.setSubjectsContentsSource.call(vm, 'shared')
        expect(vm.subjectsContentsSource).toBe('shared')
        expect(loadSharedObjectsForMe).toHaveBeenCalledTimes(1)
        expect(loadSubjectsContentsOverview).not.toHaveBeenCalled()

        methods.setSubjectsContentsSource.call(vm, 'workspace')
        expect(vm.subjectsContentsSource).toBe('workspace')
        expect(loadSubjectsContentsOverview).toHaveBeenCalledWith({ force: true })
    })

    it('resets subjects source to workspace when switching to subjects overview mode', () => {
        const methods = (MaterialsOverviewView as any)?.methods || {}
        const loadSubjectsContentsOverview = vi.fn()
        const vm: any = {
            ...methods,
            forcedOverviewMode: '',
            overviewViewMode: 'list',
            subjectsContentsSource: 'shared',
            loadSubjectsContentsOverview,
        }

        methods.setOverviewMode.call(vm, 'subjects_contents')

        expect(vm.overviewViewMode).toBe('subjects_contents')
        expect(vm.subjectsContentsSource).toBe('workspace')
        expect(loadSubjectsContentsOverview).toHaveBeenCalledWith({ force: true })
    })

    it('normalizes and sorts shared objects from inbox users response', () => {
        const methods = (MaterialsOverviewView as any)?.methods || {}
        const vm: any = { ...methods }
        const cards = methods.normalizeSharedObjectsForMeResponse.call(vm, [
            {
                label: 'Lehrer Eins',
                school_label: 'CDGym',
                shared_items: [
                    {
                        rule_id: 10,
                        scope_type: 'subject',
                        scope_label: 'Fach',
                        scope_object_label: 'Mathematik',
                        scope_path_label: 'Mathematik',
                        permission: 'read_write',
                        permission_label: 'LESEN/SCHREIBEN',
                        is_archived: false,
                        updated_at: '2026-03-01T10:00:00+00:00',
                    },
                    {
                        rule_id: 11,
                        is_archived: true,
                    },
                ],
            },
            {
                label: 'Lehrer Zwei',
                school_label: 'Abendgymnasium',
                shared_items: [
                    {
                        rule_id: 20,
                        scope_type: 'all',
                        scope_label: 'Workspace',
                        scope_object_label: 'Alle Materialien',
                        permission: 'read_only',
                        permission_label: 'NUR LESEN',
                        is_archived: false,
                        updated_at: '2026-03-03T08:30:00+00:00',
                    },
                ],
            },
        ])

        expect(Array.isArray(cards)).toBe(true)
        expect(cards).toHaveLength(2)
        expect(cards[0].ruleId).toBe(20)
        expect(cards[0].fromUserLabel).toBe('Lehrer Zwei')
        expect(cards[1].ruleId).toBe(10)
        expect(cards[1].scopeLabel).toBe('Fach')
    })
})
