import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/vue'
import axios from 'axios'
import MaterialsSubjectsContentsTree from '@/pages/admin/materials/components/overview/MaterialsSubjectsContentsTree.vue'
import { defineComponent } from 'vue'

vi.mock('axios', () => ({
    default: {
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}))

const axiosMock = vi.mocked(axios, true)

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
    'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
    VDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
    'v-card': { template: '<div><slot /></div>' },
    VCard: { template: '<div><slot /></div>' },
    'v-card-title': { template: '<div><slot /></div>' },
    VCardTitle: { template: '<div><slot /></div>' },
    'v-card-text': { template: '<div><slot /></div>' },
    VCardText: { template: '<div><slot /></div>' },
    'v-card-actions': { template: '<div><slot /></div>' },
    VCardActions: { template: '<div><slot /></div>' },
    'v-text-field': {
        props: ['modelValue', 'label', 'disabled', 'errorMessages'],
        emits: ['update:modelValue', 'blur'],
        computed: {
            normalizedErrorMessages() {
                if (Array.isArray(this.errorMessages)) {
                    return this.errorMessages
                }

                const text = String(this.errorMessages || '').trim()
                return text !== '' ? [text] : []
            },
        },
        template: `
            <div>
                <input
                    :aria-label="label"
                    :value="modelValue"
                    :disabled="disabled"
                    @input="$emit('update:modelValue', $event.target.value)"
                    @blur="$emit('blur', $event)" />
                <div v-for="message in normalizedErrorMessages" :key="message">{{ message }}</div>
            </div>
        `,
    },
    VTextField: {
        props: ['modelValue', 'label', 'disabled', 'errorMessages'],
        emits: ['update:modelValue', 'blur'],
        computed: {
            normalizedErrorMessages() {
                if (Array.isArray(this.errorMessages)) {
                    return this.errorMessages
                }

                const text = String(this.errorMessages || '').trim()
                return text !== '' ? [text] : []
            },
        },
        template: `
            <div>
                <input
                    :aria-label="label"
                    :value="modelValue"
                    :disabled="disabled"
                    @input="$emit('update:modelValue', $event.target.value)"
                    @blur="$emit('blur', $event)" />
                <div v-for="message in normalizedErrorMessages" :key="message">{{ message }}</div>
            </div>
        `,
    },
}

function renderTree(
    items: any[],
    options: {
        enableCreateButtons?: boolean
        enableRemoveButtons?: boolean
        enableShareButtons?: boolean
        sharedObjectsForMe?: any[]
        sharedObjectsForMeLoading?: boolean
        sharedObjectsForMeError?: string
    } = {}
) {
    const Host = defineComponent({
        components: { MaterialsSubjectsContentsTree },
        data() {
            return {
                sharedForMeExpanded: false,
                expandedSharedItems: {},
            }
        },
        methods: {
            toggleSharedForMeExpanded() {
                this.sharedForMeExpanded = !this.sharedForMeExpanded
            },
            toggleSharedItemExpanded(ruleId: number) {
                const key = `shared-item-${Number(ruleId || 0)}`
                this.expandedSharedItems = {
                    ...this.expandedSharedItems,
                    [key]: this.expandedSharedItems[key] !== true,
                }
            },
        },
        template: `
            <MaterialsSubjectsContentsTree
                :items="items"
                :action-busy="false"
                :enable-share-buttons="enableShareButtons"
                :enable-create-buttons="enableCreateButtons"
                :enable-remove-buttons="enableRemoveButtons"
                :show-share-indicators="false"
                :share-indicator-color-fn="() => ''"
                :status-color-fn="() => 'primary'"
                :status-label-fn="() => 'Entwurf'"
                :subject-group-style-fn="() => ({})"
                :topic-group-style-fn="() => ({})"
                :shared-objects-for-me="sharedObjectsForMe"
                :shared-objects-for-me-loading="sharedObjectsForMeLoading"
                :shared-objects-for-me-error="sharedObjectsForMeError"
                :shared-for-me-expanded="sharedForMeExpanded"
                :expanded-shared-items="expandedSharedItems"
                @toggle-shared-for-me-expanded="toggleSharedForMeExpanded"
                @toggle-shared-item-expanded="toggleSharedItemExpanded"
                @shared-node-created="$emit('shared-node-created', $event)"
                @shared-node-moved="$emit('shared-node-moved', $event)"
                @workspace-node-created="$emit('workspace-node-created', $event)"
                @workspace-node-renamed="$emit('workspace-node-renamed', $event)"
                @workspace-node-deleted="$emit('workspace-node-deleted', $event)"
                @workspace-node-moved="$emit('workspace-node-moved', $event)"
                @open-material="$emit('open-material', $event)"
                @open-share="$emit('open-share', $event)"
                @open-create="$emit('open-create', $event)"
                @open-attachments="$emit('open-attachments', $event)"
                @open-shared-material="$emit('open-shared-material', $event)"
                @open-shared-attachments="$emit('open-shared-attachments', $event)"
                @unlink-linked-material="$emit('unlink-linked-material', $event)"
                @unlink-linked-topic="$emit('unlink-linked-topic', $event)"
                @unlink-linked-unit="$emit('unlink-linked-unit', $event)" />
        `,
        props: {
            items: { type: Array, required: true },
            enableCreateButtons: { type: Boolean, default: false },
            enableShareButtons: { type: Boolean, default: false },
            enableRemoveButtons: { type: Boolean, default: false },
            sharedObjectsForMe: { type: Array, default: () => [] },
            sharedObjectsForMeLoading: { type: Boolean, default: false },
            sharedObjectsForMeError: { type: String, default: '' },
        },
    })

    return render(Host, {
        props: {
            items,
            enableCreateButtons: options.enableCreateButtons === true,
            enableShareButtons: options.enableShareButtons === true,
            enableRemoveButtons: options.enableRemoveButtons === true,
            sharedObjectsForMe: Array.isArray(options.sharedObjectsForMe) ? options.sharedObjectsForMe : [],
            sharedObjectsForMeLoading: options.sharedObjectsForMeLoading === true,
            sharedObjectsForMeError: String(options.sharedObjectsForMeError || ''),
        },
        global: {
            stubs: vuetifyStubs,
        }
    })
}

describe('MaterialsSubjectsContentsTree', () => {
    beforeEach(() => {
        axiosMock.post.mockReset()
        axiosMock.put.mockReset()
        axiosMock.delete.mockReset()
    })

    it('collapses and expands the workspace contents', async () => {
        const { container } = renderTree([
            {
                id: 1,
                name: 'Mathematik',
                materials: [],
                topics: [
                    {
                        id: 11,
                        name: 'Algebra',
                        materials: [],
                        units: [],
                    },
                ],
            },
        ])

        expect(screen.getByText('Mathematik')).toBeInTheDocument()
        expect(screen.queryByTitle('Fach hinzufügen')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Thema hinzufügen')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Bereich hinzufügen')).not.toBeInTheDocument()
        expect(container.querySelector('.overview-shared-row--spaced')).not.toBeNull()

        await fireEvent.click(screen.getByRole('button', { name: /workspace/i }))

        expect(screen.queryByText('Mathematik')).not.toBeInTheDocument()
        expect(container.querySelector('.overview-shared-row--spaced')).toBeNull()

        await fireEvent.click(screen.getByRole('button', { name: /workspace/i }))

        expect(screen.getByText('Mathematik')).toBeInTheDocument()
        expect(container.querySelector('.overview-shared-row--spaced')).not.toBeNull()
    })

    it('shows workspace Struktur ändern mode with node actions and emits workspace move refresh event', async () => {
        axiosMock.post.mockResolvedValue({ data: {} })

        const { emitted } = renderTree([
            {
                id: 1,
                name: 'Mathematik',
                materials: [],
                topics: [
                    {
                        id: 11,
                        name: 'Algebra',
                        materials: [],
                        units: [],
                    },
                    {
                        id: 12,
                        name: 'Geometrie',
                        materials: [],
                        units: [],
                    },
                ],
            },
            {
                id: 2,
                name: 'Biologie',
                materials: [],
                topics: [],
            },
        ], {
            enableCreateButtons: true,
        })

        expect(screen.getByText('Mathematik')).toBeInTheDocument()
        expect(screen.queryByTitle('Fach nach oben')).not.toBeInTheDocument()
        expect(screen.getByText('Struktur ändern')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))

        expect(screen.getByRole('button', { name: 'Fach/Themen schließen' })).toBeInTheDocument()
        expect(screen.getAllByTitle('Fach hinzufügen').length).toBeGreaterThan(0)
        expect(screen.getAllByTitle('Thema hinzufügen').length).toBeGreaterThan(0)
        expect(screen.queryByTitle(/Neues Material in/i)).not.toBeInTheDocument()
        const mathematikRow = screen.getByText('Mathematik').closest('.overview-subjects-node-row') as HTMLElement
        expect(within(mathematikRow).getByTitle('Fach nach oben')).toBeDisabled()
        expect(within(mathematikRow).getByTitle('Fach nach unten')).not.toBeDisabled()

        const algebraRow = screen.getByText('Algebra').closest('.overview-subjects-node-row') as HTMLElement
        await fireEvent.click(within(algebraRow).getByTitle('Thema nach unten'))

        expect(axiosMock.post).toHaveBeenCalledTimes(1)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/topics/11/move', {
            data: {
                direction: 'down',
            },
        })

        const movedEvents = emitted('workspace-node-moved') || []
        expect(movedEvents).toHaveLength(1)
        expect((movedEvents[0]?.[0] as any)?.level).toBe('topic')
        expect((movedEvents[0]?.[0] as any)?.nodeId).toBe(11)
        expect((movedEvents[0]?.[0] as any)?.direction).toBe('down')
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

    it('shows full-access structure preview buttons, edit actions and delete actions only for empty shared branches', async () => {
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
                    ruleId: 93,
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace A',
                    permission: 'full_access',
                    permissionLabel: 'VOLLZUGRIFF',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 1,
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
                                    name: 'Leeres Thema',
                                    materials: [],
                                    units: [
                                        {
                                            id: 30,
                                            name: 'Leerer Bereich',
                                            materials: [],
                                        },
                                    ],
                                },
                                {
                                    id: 21,
                                    name: 'Thema mit Material',
                                    materials: [
                                        {
                                            id: 102,
                                            title: 'Themamaterial',
                                            status: 'done',
                                            statusLabel: 'Erledigt',
                                            attachmentsCount: 0,
                                        },
                                    ],
                                    units: [],
                                },
                            ],
                        },
                        {
                            id: 11,
                            name: 'Leeres Fach',
                            materials: [],
                            topics: [],
                        },
                    ],
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /für mich geteilt/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        expect(screen.getByRole('button', { name: 'Struktur ändern' })).toBeInTheDocument()
        expect(screen.queryByTitle('Fach hinzufügen')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Thema hinzufügen')).not.toBeInTheDocument()
        expect(screen.getByText('Fachmaterial')).toBeInTheDocument()
        expect(screen.getByText('Themamaterial')).toBeInTheDocument()
        expect(screen.getAllByTitle(/Neues Material in/i)).toHaveLength(5)
        expect(screen.getAllByTitle('Neues Material in Fach anlegen')).toHaveLength(2)
        const sharedItem = container.querySelector('.overview-shared-item') as HTMLElement
        expect(sharedItem).not.toBeNull()
        expect(within(sharedItem).getAllByTitle('Teilen')).toHaveLength(8)
        expect(screen.queryByTitle(/bearbeiten$/i)).not.toBeInTheDocument()
        expect(screen.queryByTitle('Fach löschen')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Thema löschen')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Bereich löschen')).not.toBeInTheDocument()

        await fireEvent.click(within(sharedItem).getAllByTitle('Teilen')[0])
        await fireEvent.click(within(screen.getByText('Leeres Fach').closest('.overview-shared-hierarchy-node') as HTMLElement).getByTitle('Teilen'))
        await fireEvent.click(within(screen.getByText('Fachmaterial').closest('.overview-subjects-material-item') as HTMLElement).getByTitle('Teilen'))

        const openShareEvents = emitted('open-share') || []
        expect(openShareEvents).toHaveLength(3)
        expect((openShareEvents[0]?.[0] as any)?.level).toBe('all')
        expect((openShareEvents[0]?.[0] as any)?.id).toBeNull()
        expect((openShareEvents[1]?.[0] as any)?.level).toBe('subject')
        expect((openShareEvents[1]?.[0] as any)?.id).toBe(11)
        expect((openShareEvents[2]?.[0] as any)?.level).toBe('material')
        expect((openShareEvents[2]?.[0] as any)?.id).toBe(101)

        await fireEvent.click(screen.getAllByTitle('Neues Material in Thema anlegen')[0])

        const openCreateEvents = emitted('open-create') || []
        expect(openCreateEvents).toHaveLength(1)
        expect((openCreateEvents[0]?.[0] as any)?.sharedRuleId).toBe(93)
        expect((openCreateEvents[0]?.[0] as any)?.sharedNodeLevel).toBe('topic')
        expect((openCreateEvents[0]?.[0] as any)?.sharedNodeId).toBe(20)
        expect((openCreateEvents[0]?.[0] as any)?.subject).toBe('Mathematik')
        expect((openCreateEvents[0]?.[0] as any)?.topic).toBe('Leeres Thema')

        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))

        expect(screen.getByRole('button', { name: 'Fach/Themen schließen' })).toBeInTheDocument()
        expect(screen.getAllByRole('button', { name: 'Fach hinzufügen' })).toHaveLength(3)
        expect(screen.getAllByRole('button', { name: 'Thema hinzufügen' })).toHaveLength(4)
        expect(screen.getAllByRole('button', { name: 'Bereich hinzufügen' })).toHaveLength(3)
        expect(screen.getAllByTitle('Fach hinzufügen')).toHaveLength(3)
        expect(screen.getAllByTitle('Thema hinzufügen')).toHaveLength(4)
        expect(screen.getAllByTitle('Bereich hinzufügen')).toHaveLength(3)
        expect(screen.queryByText('Fachmaterial')).not.toBeInTheDocument()
        expect(screen.queryByText('Themamaterial')).not.toBeInTheDocument()
        expect(screen.queryByTitle(/Neues Material in/i)).not.toBeInTheDocument()
        expect(within(sharedItem).getAllByTitle('Teilen')).toHaveLength(6)
        expect(screen.getAllByTitle(/bearbeiten$/i)).toHaveLength(5)

        const deleteButtons = screen.getAllByTitle(/löschen$/i)
        expect(deleteButtons).toHaveLength(3)
    })

    it('shows shared move buttons in structure mode and emits moved refresh event', async () => {
        axiosMock.post.mockResolvedValue({ data: {} })

        const { emitted } = renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 102,
                    scopeType: 'all',
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace A',
                    permission: 'full_access',
                    permissionLabel: 'VOLLZUGRIFF',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 0,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Mathematik',
                            materials: [],
                            topics: [
                                {
                                    id: 20,
                                    name: 'Algebra',
                                    materials: [],
                                    units: [
                                        {
                                            id: 30,
                                            name: 'Lineare Gleichungen',
                                            materials: [],
                                        },
                                        {
                                            id: 31,
                                            name: 'Quadratische Gleichungen',
                                            materials: [],
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
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))

        expect(screen.getByTitle('Fach nach oben')).toBeInTheDocument()
        expect(screen.getByTitle('Thema nach unten')).toBeInTheDocument()
        expect(screen.getAllByTitle('Bereich nach oben')).toHaveLength(2)

        await fireEvent.click(screen.getAllByTitle('Bereich nach oben')[1])

        expect(axiosMock.post).toHaveBeenCalledTimes(1)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/units/31/move', {
            rule_id: 102,
            data: {
                direction: 'up',
            },
        })

        const movedEvents = emitted('shared-node-moved') || []
        expect(movedEvents).toHaveLength(1)
        expect((movedEvents[0]?.[0] as any)?.ruleId).toBe(102)
        expect((movedEvents[0]?.[0] as any)?.level).toBe('unit')
        expect((movedEvents[0]?.[0] as any)?.nodeId).toBe(31)
        expect((movedEvents[0]?.[0] as any)?.direction).toBe('up')
    })

    it('disables shared move buttons when a node is already at top or bottom position', async () => {
        renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 103,
                    scopeType: 'all',
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace A',
                    permission: 'full_access',
                    permissionLabel: 'VOLLZUGRIFF',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 0,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Mathematik',
                            materials: [],
                            topics: [
                                {
                                    id: 20,
                                    name: 'Algebra',
                                    materials: [],
                                    units: [
                                        {
                                            id: 30,
                                            name: 'Einheit A',
                                            materials: [],
                                        },
                                        {
                                            id: 31,
                                            name: 'Einheit B',
                                            materials: [],
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
        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))

        const mathematikRow = screen.getByText('Mathematik').closest('.overview-shared-hierarchy-node') as HTMLElement
        const biologieRow = screen.getByText('Biologie').closest('.overview-shared-hierarchy-node') as HTMLElement
        const algebraRow = screen.getByText('Algebra').closest('.overview-shared-hierarchy-node') as HTMLElement
        const geometrieRow = screen.getByText('Geometrie').closest('.overview-shared-hierarchy-node') as HTMLElement
        const einheitARow = screen.getByText('Einheit A').closest('.overview-shared-hierarchy-node') as HTMLElement
        const einheitBRow = screen.getByText('Einheit B').closest('.overview-shared-hierarchy-node') as HTMLElement

        expect(within(mathematikRow).getByTitle('Fach nach oben')).toBeDisabled()
        expect(within(mathematikRow).getByTitle('Fach nach unten')).not.toBeDisabled()
        expect(within(biologieRow).getByTitle('Fach nach oben')).not.toBeDisabled()
        expect(within(biologieRow).getByTitle('Fach nach unten')).toBeDisabled()

        expect(within(algebraRow).getByTitle('Thema nach oben')).toBeDisabled()
        expect(within(algebraRow).getByTitle('Thema nach unten')).not.toBeDisabled()
        expect(within(geometrieRow).getByTitle('Thema nach oben')).not.toBeDisabled()
        expect(within(geometrieRow).getByTitle('Thema nach unten')).toBeDisabled()

        expect(within(einheitARow).getByTitle('Bereich nach oben')).toBeDisabled()
        expect(within(einheitARow).getByTitle('Bereich nach unten')).not.toBeDisabled()
        expect(within(einheitBRow).getByTitle('Bereich nach oben')).not.toBeDisabled()
        expect(within(einheitBRow).getByTitle('Bereich nach unten')).toBeDisabled()
    })

    it('opens a persistent shared create-subject dialog, validates the title, and emits a refresh event after save', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: {
                    id: 77,
                    name: 'Biologie',
                    workspace_id: 5,
                },
            },
        })

        const { emitted } = renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 96,
                    scopeType: 'all',
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace A',
                    permission: 'full_access',
                    permissionLabel: 'VOLLZUGRIFF',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 0,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Mathematik',
                            materials: [],
                            topics: [],
                        },
                    ],
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /für mich geteilt/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))
        await fireEvent.click(screen.getAllByTitle('Fach hinzufügen')[0])

        expect(screen.getByText('Fach hinzufügen')).toBeInTheDocument()

        const input = screen.getByRole('textbox', { name: 'Titel' })

        await fireEvent.update(input, '')
        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))

        expect(screen.getByText('Es muss etwas eingegeben werden.')).toBeInTheDocument()

        await fireEvent.update(input, 'x'.repeat(256))
        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))

        expect(screen.getByText('Die Eingabe ist zu lang (max. 255 Zeichen)')).toBeInTheDocument()

        await fireEvent.update(input, 'Biologie')
        await fireEvent.keyDown(input, { key: 'Enter', code: 'Enter' })

        expect(axiosMock.post).toHaveBeenCalledTimes(1)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/subjects', {
            rule_id: 96,
            data: {
                name: 'Biologie',
                before_subject_id: 10,
            },
        })
        expect(screen.queryByText('Fach hinzufügen')).not.toBeInTheDocument()

        const createdEvents = emitted('shared-node-created') || []
        expect(createdEvents).toHaveLength(1)
        expect((createdEvents[0]?.[0] as any)?.ruleId).toBe(96)
        expect((createdEvents[0]?.[0] as any)?.level).toBe('subject')
        expect((createdEvents[0]?.[0] as any)?.nodeId).toBe(77)
        expect((createdEvents[0]?.[0] as any)?.name).toBe('Biologie')
    })

    it('sends shared subject creation without insert target when bottom Fach button is used', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: {
                    id: 78,
                    name: 'Biologie',
                    workspace_id: 5,
                },
            },
        })

        renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 97,
                    scopeType: 'all',
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace A',
                    permission: 'full_access',
                    permissionLabel: 'VOLLZUGRIFF',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 0,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Mathematik',
                            materials: [],
                            topics: [],
                        },
                    ],
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /für mich geteilt/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))
        await fireEvent.click(screen.getAllByTitle('Fach hinzufügen')[1])

        const input = screen.getByRole('textbox', { name: 'Titel' })
        await fireEvent.update(input, 'Biologie')
        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))

        expect(axiosMock.post).toHaveBeenCalledTimes(1)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/subjects', {
            rule_id: 97,
            data: {
                name: 'Biologie',
            },
        })
    })

    it('opens a persistent shared create-topic dialog and inserts before selected topic', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: {
                    id: 88,
                    name: 'Analysis',
                    subject_id: 10,
                },
            },
        })

        const { emitted } = renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 98,
                    scopeType: 'all',
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace A',
                    permission: 'full_access',
                    permissionLabel: 'VOLLZUGRIFF',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 0,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Mathematik',
                            materials: [],
                            topics: [
                                {
                                    id: 20,
                                    name: 'Algebra',
                                    materials: [],
                                    units: [],
                                },
                            ],
                        },
                    ],
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /für mich geteilt/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))
        await fireEvent.click(screen.getAllByTitle('Thema hinzufügen')[0])

        expect(screen.getByText('Thema hinzufügen')).toBeInTheDocument()

        const input = screen.getByRole('textbox', { name: 'Titel' })
        await fireEvent.update(input, '')
        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))
        expect(screen.getByText('Es muss etwas eingegeben werden.')).toBeInTheDocument()

        await fireEvent.update(input, 'Analysis')
        await fireEvent.keyDown(input, { key: 'Enter', code: 'Enter' })

        expect(axiosMock.post).toHaveBeenCalledTimes(1)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/topics', {
            rule_id: 98,
            data: {
                subject_id: 10,
                name: 'Analysis',
                before_topic_id: 20,
            },
        })
        expect(screen.queryByText('Thema hinzufügen')).not.toBeInTheDocument()

        const createdEvents = emitted('shared-node-created') || []
        expect(createdEvents).toHaveLength(1)
        expect((createdEvents[0]?.[0] as any)?.ruleId).toBe(98)
        expect((createdEvents[0]?.[0] as any)?.level).toBe('topic')
        expect((createdEvents[0]?.[0] as any)?.nodeId).toBe(88)
        expect((createdEvents[0]?.[0] as any)?.name).toBe('Analysis')
        expect((createdEvents[0]?.[0] as any)?.parentSubjectId).toBe(10)
    })

    it('creates shared topic without insert target when bottom Thema button is used', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: {
                    id: 89,
                    name: 'Geometrie',
                    subject_id: 10,
                },
            },
        })

        renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 99,
                    scopeType: 'all',
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace A',
                    permission: 'full_access',
                    permissionLabel: 'VOLLZUGRIFF',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 0,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Mathematik',
                            materials: [],
                            topics: [
                                {
                                    id: 20,
                                    name: 'Algebra',
                                    materials: [],
                                    units: [],
                                },
                            ],
                        },
                    ],
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /für mich geteilt/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))
        await fireEvent.click(screen.getAllByTitle('Thema hinzufügen')[1])

        const input = screen.getByRole('textbox', { name: 'Titel' })
        await fireEvent.update(input, 'Geometrie')
        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))

        expect(axiosMock.post).toHaveBeenCalledTimes(1)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/topics', {
            rule_id: 99,
            data: {
                subject_id: 10,
                name: 'Geometrie',
            },
        })
    })

    it('opens a persistent shared create-unit dialog and inserts before selected unit', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: {
                    id: 108,
                    name: 'Wurzeln',
                    topic_id: 20,
                },
            },
        })

        const { emitted } = renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 100,
                    scopeType: 'all',
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace A',
                    permission: 'full_access',
                    permissionLabel: 'VOLLZUGRIFF',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 0,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Mathematik',
                            materials: [],
                            topics: [
                                {
                                    id: 20,
                                    name: 'Algebra',
                                    materials: [],
                                    units: [
                                        {
                                            id: 30,
                                            name: 'Lineare Gleichungen',
                                            materials: [],
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
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))
        await fireEvent.click(screen.getAllByTitle('Bereich hinzufügen')[0])

        expect(screen.getByText('Bereich hinzufügen')).toBeInTheDocument()

        const input = screen.getByRole('textbox', { name: 'Titel' })
        await fireEvent.update(input, '')
        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))
        expect(screen.getByText('Es muss etwas eingegeben werden.')).toBeInTheDocument()

        await fireEvent.update(input, 'Wurzeln')
        await fireEvent.keyDown(input, { key: 'Enter', code: 'Enter' })

        expect(axiosMock.post).toHaveBeenCalledTimes(1)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/units', {
            rule_id: 100,
            data: {
                topic_id: 20,
                name: 'Wurzeln',
                before_unit_id: 30,
            },
        })
        expect(screen.queryByText('Bereich hinzufügen')).not.toBeInTheDocument()

        const createdEvents = emitted('shared-node-created') || []
        expect(createdEvents).toHaveLength(1)
        expect((createdEvents[0]?.[0] as any)?.ruleId).toBe(100)
        expect((createdEvents[0]?.[0] as any)?.level).toBe('unit')
        expect((createdEvents[0]?.[0] as any)?.nodeId).toBe(108)
        expect((createdEvents[0]?.[0] as any)?.name).toBe('Wurzeln')
        expect((createdEvents[0]?.[0] as any)?.parentTopicId).toBe(20)
    })

    it('creates shared unit without insert target when bottom Bereich button is used', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: {
                    id: 109,
                    name: 'Kurvendiskussion',
                    topic_id: 20,
                },
            },
        })

        renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 101,
                    scopeType: 'all',
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace A',
                    permission: 'full_access',
                    permissionLabel: 'VOLLZUGRIFF',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 0,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Mathematik',
                            materials: [],
                            topics: [
                                {
                                    id: 20,
                                    name: 'Algebra',
                                    materials: [],
                                    units: [
                                        {
                                            id: 30,
                                            name: 'Lineare Gleichungen',
                                            materials: [],
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
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))
        await fireEvent.click(screen.getAllByTitle('Bereich hinzufügen')[1])

        const input = screen.getByRole('textbox', { name: 'Titel' })
        await fireEvent.update(input, 'Kurvendiskussion')
        await fireEvent.keyDown(input, { key: 'Enter', code: 'Enter' })

        expect(axiosMock.post).toHaveBeenCalledTimes(1)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/units', {
            rule_id: 101,
            data: {
                topic_id: 20,
                name: 'Kurvendiskussion',
            },
        })
    })

    it('opens a persistent shared rename dialog, validates the title, and updates the visible name', async () => {
        axiosMock.put.mockResolvedValue({
            data: {
                data: {
                    id: 10,
                    name: 'Neue Mathematik',
                },
            },
        })

        renderTree([
            {
                id: 1,
                name: 'Lokales Fach',
                materials: [],
                topics: [],
            },
        ], {
            sharedObjectsForMe: [
                {
                    ruleId: 94,
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace A',
                    permission: 'full_access',
                    permissionLabel: 'VOLLZUGRIFF',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 0,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Mathematik',
                            materials: [],
                            topics: [],
                        },
                    ],
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /für mich geteilt/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))
        await fireEvent.click(screen.getByTitle('Fach bearbeiten'))

        expect(screen.getByText('Fach umbenennen')).toBeInTheDocument()

        const input = screen.getByRole('textbox', { name: 'Titel' })

        await fireEvent.update(input, '')
        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))

        expect(screen.getByText('Es muss etwas eingegeben werden.')).toBeInTheDocument()
        expect(screen.getByText('Fach umbenennen')).toBeInTheDocument()

        await fireEvent.update(input, 'x'.repeat(256))
        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))

        expect(screen.getByText('Die Eingabe ist zu lang (max. 255 Zeichen)')).toBeInTheDocument()
        expect(screen.getByText('Fach umbenennen')).toBeInTheDocument()

        await fireEvent.update(input, 'Neue Mathematik')
        await fireEvent.keyDown(input, { key: 'Enter', code: 'Enter' })

        expect(axiosMock.put).toHaveBeenCalledTimes(1)
        expect(axiosMock.put).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/subjects/10', {
            rule_id: 94,
            data: {
                name: 'Neue Mathematik',
            },
        })
        expect(screen.queryByText('Fach umbenennen')).not.toBeInTheDocument()
        expect(screen.getByText('Neue Mathematik')).toBeInTheDocument()
    })

    it('opens a persistent shared delete dialog and deletes the original empty node', async () => {
        axiosMock.delete.mockResolvedValue({
            data: {},
        })

        renderTree([
            {
                id: 1,
                name: 'Lokales Fach',
                materials: [],
                topics: [],
            },
        ], {
            sharedObjectsForMe: [
                {
                    ruleId: 95,
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace A',
                    permission: 'full_access',
                    permissionLabel: 'VOLLZUGRIFF',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 0,
                    hierarchy: [
                        {
                            id: 11,
                            name: 'Leeres Fach',
                            materials: [],
                            topics: [],
                        },
                    ],
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /für mich geteilt/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))
        await fireEvent.click(screen.getByTitle('Fach löschen'))

        const dialog = screen.getByText('Wirklich löschen? Das ist nur möglich, wenn keine Materialien zugeordnet sind.').closest('div')
        expect(screen.getByText('Fach löschen')).toBeInTheDocument()
        expect(screen.getAllByText('Leeres Fach')).toHaveLength(2)
        expect(dialog).not.toBeNull()

        await fireEvent.click(screen.getByRole('button', { name: 'Löschen' }))

        expect(axiosMock.delete).toHaveBeenCalledTimes(1)
        expect(axiosMock.delete).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/subjects/11', {
            data: {
                rule_id: 95,
            },
        })
        expect(screen.queryByText('Wirklich löschen? Das ist nur möglich, wenn keine Materialien zugeordnet sind.')).not.toBeInTheDocument()
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
