import { describe, expect, it } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/vue'
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
        const { container } = renderTree([
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

        const sharedItem = container.querySelector('.overview-shared-item')
        expect(sharedItem).not.toBeNull()
        expect((sharedItem as HTMLElement).style.flex).toContain('24rem')
        expect((sharedItem as HTMLElement).style.maxWidth).toBe('28rem')
    })

    it('renders multiple shared workspaces in a wrapped row instead of full-width cards', async () => {
        const { container } = renderTree([
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
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace A',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 3,
                },
                {
                    ruleId: 78,
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace B',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Zwei',
                    materialsCount: 4,
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /für mich geteilt/i }))

        const sharedItems = Array.from(container.querySelectorAll('.overview-shared-item'))
        expect(sharedItems).toHaveLength(2)
        expect((sharedItems[0] as HTMLElement).style.flex).toContain('24rem')
        expect((sharedItems[1] as HTMLElement).style.maxWidth).toBe('28rem')
        expect(screen.getByText('Workspace A')).toBeInTheDocument()
        expect(screen.getByText('Workspace B')).toBeInTheDocument()
    })

    it('toggles shared workspace hierarchy and emits read-only shared material actions', async () => {
        const { emitted, container } = renderTree([
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
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace A',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 1,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Mathematik',
                            topics: [
                                {
                                    id: 20,
                                    name: 'Algebra',
                                    units: [
                                        {
                                            id: 30,
                                            name: 'Einheit 1',
                                            materials: [
                                                {
                                                    id: 99,
                                                    title: 'Lineare Gleichungen',
                                                    typeLabel: 'Arbeitsblatt',
                                                    status: 'done',
                                                    statusLabel: 'Erledigt',
                                                    attachmentsCount: 2,
                                                },
                                            ],
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /für mich geteilt/i }))

        expect(screen.queryByText('Lineare Gleichungen')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        expect(screen.getByText('Lineare Gleichungen')).toBeInTheDocument()
        expect(screen.getByText('Arbeitsblatt')).toBeInTheDocument()
        expect(screen.getByText('Erledigt')).toBeInTheDocument()
        const sharedItem = container.querySelector('.overview-shared-item') as HTMLElement
        expect(sharedItem).not.toBeNull()
        expect(sharedItem.style.flex).toContain('100%')
        expect(sharedItem.style.maxWidth).toBe('100%')
        expect(sharedItem.className).toContain('overview-shared-item--expanded')
        expect(within(sharedItem).queryByTitle('Teilen')).toBeNull()

        await fireEvent.click(screen.getByRole('button', { name: 'Lineare Gleichungen' }))
        await fireEvent.click(screen.getByRole('button', { name: '2' }))

        const openSharedMaterialEvents = emitted('open-shared-material') || []
        expect(openSharedMaterialEvents).toHaveLength(1)
        expect((openSharedMaterialEvents[0]?.[0] as any)?.ruleId).toBe(77)
        expect((openSharedMaterialEvents[0]?.[0] as any)?.material?.id).toBe(99)

        const openSharedAttachmentEvents = emitted('open-shared-attachments') || []
        expect(openSharedAttachmentEvents).toHaveLength(1)
        expect((openSharedAttachmentEvents[0]?.[0] as any)?.ruleId).toBe(77)
        expect((openSharedAttachmentEvents[0]?.[0] as any)?.material?.id).toBe(99)

        await fireEvent.click(screen.getByRole('button', { name: 'Schließen' }))
        expect(screen.queryByText('Lineare Gleichungen')).not.toBeInTheDocument()
        expect(sharedItem.style.flex).toContain('24rem')
        expect(sharedItem.style.maxWidth).toBe('28rem')
    })

    it('renders empty shared branches and direct materials on subject topic and unit level', async () => {
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
                    ruleId: 91,
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace A',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 3,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Mathematik',
                            materials: [
                                {
                                    id: 101,
                                    title: 'Fachmaterial',
                                    status: 'inbox',
                                    statusLabel: 'Neu/Idee',
                                    attachmentsCount: 0,
                                },
                            ],
                            topics: [
                                {
                                    id: 20,
                                    name: 'Algebra',
                                    materials: [
                                        {
                                            id: 102,
                                            title: 'Themamaterial',
                                            status: 'in_progress',
                                            statusLabel: 'In Arbeit',
                                            attachmentsCount: 1,
                                        },
                                    ],
                                    units: [
                                        {
                                            id: 30,
                                            name: 'Leere Einheit',
                                            materials: [],
                                        },
                                        {
                                            id: 31,
                                            name: 'Einheit 1',
                                            materials: [
                                                {
                                                    id: 103,
                                                    title: 'Einheitsmaterial',
                                                    status: 'done',
                                                    statusLabel: 'Erledigt',
                                                    attachmentsCount: 0,
                                                },
                                            ],
                                        },
                                    ],
                                },
                                {
                                    id: 21,
                                    name: 'Geometrie',
                                    materials: [],
                                    units: [],
                                },
                            ],
                        },
                        {
                            id: 11,
                            name: 'Biologie',
                            materials: [],
                            topics: [],
                        },
                    ],
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /für mich geteilt/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        expect(screen.getByText('Biologie')).toBeInTheDocument()
        expect(screen.getByText('Geometrie')).toBeInTheDocument()
        expect(screen.getByText('Leere Einheit')).toBeInTheDocument()
        expect(screen.getByText('Fachmaterial')).toBeInTheDocument()
        expect(screen.getByText('Themamaterial')).toBeInTheDocument()
        expect(screen.getByText('Einheitsmaterial')).toBeInTheDocument()
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
