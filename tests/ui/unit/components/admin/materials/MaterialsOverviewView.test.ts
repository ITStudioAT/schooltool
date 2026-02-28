import { describe, expect, it } from 'vitest'
import MaterialsOverviewView from '@/pages/admin/materials/components/views/MaterialsOverviewView.vue'

describe('MaterialsOverviewView', () => {
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
})
