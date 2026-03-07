import { describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/vue'
import MaterialsSubjectsContentsTree from '@/pages/admin/materials/components/overview/MaterialsSubjectsContentsTree.vue'

const vuetifyStubs = {
    'v-btn': {
        emits: ['click'],
        template: '<button type="button" v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
    },
    VBtn: {
        emits: ['click'],
        template: '<button type="button" v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
    },
    'v-chip': { template: '<span><slot /></span>' },
    VChip: { template: '<span><slot /></span>' },
    'v-icon': { template: '<i><slot /></i>' },
    VIcon: { template: '<i><slot /></i>' },
}

function renderTree(
    items: any[],
    options: {
        enableRemoveButtons?: boolean
        enableShareButtons?: boolean
        sharedObjectsForMe?: any[]
        sharedObjectsForMeLoading?: boolean
        sharedObjectsForMeError?: string
    } = {}
) {
    return render(MaterialsSubjectsContentsTree, {
        props: {
            items,
            actionBusy: false,
            enableShareButtons: options.enableShareButtons === true,
            enableCreateButtons: false,
            enableRemoveButtons: options.enableRemoveButtons === true,
            showShareIndicators: false,
            shareIndicatorColorFn: () => '',
            statusColorFn: () => 'primary',
            statusLabelFn: () => 'Entwurf',
            subjectGroupStyleFn: () => ({}),
            topicGroupStyleFn: () => ({}),
            sharedObjectsForMe: Array.isArray(options.sharedObjectsForMe) ? options.sharedObjectsForMe : [],
            sharedObjectsForMeLoading: options.sharedObjectsForMeLoading === true,
            sharedObjectsForMeError: String(options.sharedObjectsForMeError || ''),
        },
        global: {
            stubs: vuetifyStubs,
        },
    })
}

describe('MaterialsSubjectsContentsTree', () => {
    it('collapses and expands the workspace contents', async () => {
        const { container } = renderTree([
            {
                id: 1,
                name: 'Mathematik',
                materials: [],
                topics: [],
            },
        ])

        expect(screen.getByText('Mathematik')).toBeInTheDocument()
        expect(container.querySelector('.overview-shared-row--spaced')).not.toBeNull()

        await fireEvent.click(screen.getByRole('button', { name: /workspace/i }))

        expect(screen.queryByText('Mathematik')).not.toBeInTheDocument()
        expect(container.querySelector('.overview-shared-row--spaced')).toBeNull()

        await fireEvent.click(screen.getByRole('button', { name: /workspace/i }))

        expect(screen.getByText('Mathematik')).toBeInTheDocument()
        expect(container.querySelector('.overview-shared-row--spaced')).not.toBeNull()
    })

    it('shows shared objects when Für mich geteilt is expanded', async () => {
        renderTree([
            {
                id: 1,
                name: 'Mathematik',
                materials: [],
                topics: [],
            },
        ], {
            sharedObjectsForMe: [
                {
                    ruleId: 77,
                    scopeObjectLabel: 'Geteilte Mathematik',
                    scopePathLabel: 'Mathematik / Algebra',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Eins',
                    fromSchoolLabel: 'CDGym',
                    materialsCount: 3,
                },
            ],
        })

        expect(screen.queryByText('Geteilte Mathematik')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: /für mich geteilt/i }))

        expect(screen.getByText('Geteilte Mathematik')).toBeInTheDocument()
        expect(screen.getByText('Mathematik / Algebra')).toBeInTheDocument()
        expect(screen.getByText(/Von: Lehrer Eins/i)).toBeInTheDocument()
    })

    it('renders linked permission chip on a linked topic', () => {
        renderTree([
            {
                id: 1,
                name: 'Mathematik',
                materials: [],
                topics: [
                    {
                        id: 11,
                        name: 'Algebra',
                        isLinked: true,
                        linkedPermission: 'read_only',
                        linkedPermissionLabel: '',
                        materials: [],
                        units: [],
                    },
                ],
            },
        ])

        expect(screen.getByText('Algebra')).toBeInTheDocument()
        expect(screen.getByText('NUR LESEN')).toBeInTheDocument()
    })

    it('renders linked permission chip on a linked unit', () => {
        renderTree([
            {
                id: 1,
                name: 'Mathematik',
                materials: [],
                topics: [
                    {
                        id: 11,
                        name: 'Algebra',
                        materials: [],
                        units: [
                            {
                                id: 111,
                                name: 'Brueche',
                                isLinked: true,
                                linkedPermission: 'read_write',
                                linkedPermissionLabel: '',
                                materials: [],
                            },
                        ],
                    },
                ],
            },
        ])

        expect(screen.getByText('Brueche')).toBeInTheDocument()
        expect(screen.getByText('LESEN/SCHREIBEN')).toBeInTheDocument()
    })

    it('emits unlink-linked-unit when the linked unit button is clicked', async () => {
        const { emitted } = renderTree(
            [
                {
                    id: 1,
                    name: 'Mathematik',
                    materials: [],
                    topics: [
                        {
                            id: 11,
                            name: 'Algebra',
                            materials: [],
                            units: [
                                {
                                    id: 111,
                                    name: 'Brueche',
                                    isLinked: true,
                                    linkedPermission: 'read_only',
                                    linkedPermissionLabel: 'NUR LESEN',
                                    materials: [],
                                },
                            ],
                        },
                    ],
                },
            ],
            { enableRemoveButtons: true }
        )

        await fireEvent.click(screen.getAllByText('Link entfernen')[0])

        const events = emitted('unlink-linked-unit') || []
        expect(events.length).toBe(1)
        expect((events[0]?.[0] as any)?.id).toBe(111)
    })

    it('emits unlink-linked-topic when the linked topic button is clicked', async () => {
        const { emitted } = renderTree(
            [
                {
                    id: 1,
                    name: 'Mathematik',
                    materials: [],
                    topics: [
                        {
                            id: 11,
                            name: 'Algebra',
                            isLinked: true,
                            linkedPermission: 'read_write',
                            linkedPermissionLabel: 'LESEN/SCHREIBEN',
                            materials: [],
                            units: [],
                        },
                    ],
                },
            ],
            { enableRemoveButtons: true }
        )

        await fireEvent.click(screen.getByText('Link entfernen'))

        const events = emitted('unlink-linked-topic') || []
        expect(events.length).toBe(1)
        expect((events[0]?.[0] as any)?.id).toBe(11)
    })

    it('emits open-share also when share actions are disabled (dummy handled by parent)', async () => {
        const { emitted } = renderTree([
            {
                id: 1,
                name: 'Mathematik',
                materials: [],
                topics: [],
            },
        ])

        const shareButtons = screen.getAllByTitle('Teilen')
        await fireEvent.click(shareButtons[0])
        await fireEvent.click(shareButtons[1])

        const events = emitted('open-share') || []
        expect(events.length).toBe(2)
        expect((events[0]?.[0] as any)?.level).toBe('all')
        expect((events[1]?.[0] as any)?.level).toBe('subject')
    })

    it('emits open-share when share actions are enabled', async () => {
        const { emitted } = renderTree(
            [
                {
                    id: 1,
                    name: 'Mathematik',
                    materials: [],
                    topics: [],
                },
            ],
            { enableShareButtons: true }
        )

        const shareButtons = screen.getAllByTitle('Teilen')
        await fireEvent.click(shareButtons[1])

        const events = emitted('open-share') || []
        expect(events.length).toBe(1)
        expect((events[0]?.[0] as any)?.level).toBe('subject')
        expect((events[0]?.[0] as any)?.id).toBe(1)
    })

    it('emits workspace share payload from the workspace teilen symbol', async () => {
        const { emitted } = renderTree(
            [
                {
                    id: 1,
                    name: 'Mathematik',
                    materials: [],
                    topics: [],
                },
            ],
            { enableShareButtons: true }
        )

        const shareButtons = screen.getAllByTitle('Teilen')
        await fireEvent.click(shareButtons[0])

        const events = emitted('open-share') || []
        expect(events.length).toBe(1)
        expect((events[0]?.[0] as any)?.level).toBe('all')
        expect((events[0]?.[0] as any)?.label).toBe('Workspace')
    })
})
