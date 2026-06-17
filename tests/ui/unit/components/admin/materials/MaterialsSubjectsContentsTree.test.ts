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
    'v-checkbox': {
        props: ['modelValue', 'label', 'disabled'],
        emits: ['update:modelValue'],
        template: `
            <label>
                <input
                    type="checkbox"
                    :aria-label="label"
                    :checked="modelValue"
                    :disabled="disabled"
                    @change="$emit('update:modelValue', $event.target.checked)" />
                <span>{{ label }}</span>
            </label>
        `,
    },
    VCheckbox: {
        props: ['modelValue', 'label', 'disabled'],
        emits: ['update:modelValue'],
        template: `
            <label>
                <input
                    type="checkbox"
                    :aria-label="label"
                    :checked="modelValue"
                    :disabled="disabled"
                    @change="$emit('update:modelValue', $event.target.checked)" />
                <span>{{ label }}</span>
            </label>
        `,
    },
}

function renderTree(
    items: any[],
    options: {
        activeWorkspace?: any
        enableCreateButtons?: boolean
        enableRemoveButtons?: boolean
        enableShareButtons?: boolean
        workspaceSelection?: any
        treeKey?: string
        sharedObjectsForMe?: any[]
        archivedSharedObjectsForMe?: any[]
        sharedObjectsForMeLoading?: boolean
        sharedObjectsForMeError?: string
        initiallyCollapseHierarchy?: boolean
    } = {}
) {
    const Host = defineComponent({
        components: { MaterialsSubjectsContentsTree },
        data() {
            return {
                sharedForMeExpanded: false,
                sharedForMeArchiveExpanded: false,
                workspaceStructureExpanded: false,
                expandedSharedItems: {},
            }
        },
        methods: {
            toggleSharedForMeExpanded() {
                this.sharedForMeExpanded = !this.sharedForMeExpanded
            },
            toggleSharedForMeArchiveExpanded() {
                this.sharedForMeArchiveExpanded = !this.sharedForMeArchiveExpanded
            },
            toggleWorkspaceStructureExpanded() {
                this.workspaceStructureExpanded = !this.workspaceStructureExpanded
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
                :active-workspace="activeWorkspace"
                :action-busy="false"
                :enable-share-buttons="enableShareButtons"
                :enable-create-buttons="enableCreateButtons"
                :enable-remove-buttons="enableRemoveButtons"
                :workspace-selection="workspaceSelection"
                :show-share-indicators="false"
                :share-indicator-color-fn="() => ''"
                :status-color-fn="() => 'primary'"
                :status-label-fn="() => 'Entwurf'"
                :subject-group-style-fn="() => ({})"
                :topic-group-style-fn="() => ({})"
                :shared-objects-for-me="sharedObjectsForMe"
                :archived-shared-objects-for-me="archivedSharedObjectsForMe"
                :shared-objects-for-me-loading="sharedObjectsForMeLoading"
                :shared-objects-for-me-error="sharedObjectsForMeError"
                :shared-for-me-expanded="sharedForMeExpanded"
                :shared-for-me-archive-expanded="sharedForMeArchiveExpanded"
                :workspace-structure-expanded="workspaceStructureExpanded"
                :expanded-shared-items="expandedSharedItems"
                :initially-collapse-hierarchy="initiallyCollapseHierarchy"
                :key="treeKey"
                @toggle-shared-for-me-expanded="toggleSharedForMeExpanded"
                @toggle-shared-for-me-archive-expanded="toggleSharedForMeArchiveExpanded"
                @toggle-workspace-structure-expanded="toggleWorkspaceStructureExpanded"
                @toggle-shared-item-expanded="toggleSharedItemExpanded"
                @archive-shared-item="$emit('archive-shared-item', $event)"
                @activate-shared-item="$emit('activate-shared-item', $event)"
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
                @open-shared-insert-draft="$emit('open-shared-insert-draft', $event)"
                @unlink-linked-material="$emit('unlink-linked-material', $event)"
                @unlink-linked-topic="$emit('unlink-linked-topic', $event)"
                @unlink-linked-unit="$emit('unlink-linked-unit', $event)" />
        `,
        props: {
            items: { type: Array, required: true },
            activeWorkspace: { type: Object, default: null },
            enableCreateButtons: { type: Boolean, default: false },
            enableShareButtons: { type: Boolean, default: false },
            enableRemoveButtons: { type: Boolean, default: false },
            workspaceSelection: { type: Object, default: null },
            treeKey: { type: String, default: 'default-tree' },
            sharedObjectsForMe: { type: Array, default: () => [] },
            archivedSharedObjectsForMe: { type: Array, default: () => [] },
            sharedObjectsForMeLoading: { type: Boolean, default: false },
            sharedObjectsForMeError: { type: String, default: '' },
            initiallyCollapseHierarchy: { type: Boolean, default: false },
        },
    })

    return render(Host, {
        props: {
            items,
            activeWorkspace: options.activeWorkspace ?? null,
            enableCreateButtons: options.enableCreateButtons === true,
            enableShareButtons: options.enableShareButtons === true,
            enableRemoveButtons: options.enableRemoveButtons === true,
            workspaceSelection: options.workspaceSelection ?? null,
            treeKey: String(options.treeKey || 'default-tree'),
            sharedObjectsForMe: Array.isArray(options.sharedObjectsForMe) ? options.sharedObjectsForMe : [],
            archivedSharedObjectsForMe: Array.isArray(options.archivedSharedObjectsForMe) ? options.archivedSharedObjectsForMe : [],
            sharedObjectsForMeLoading: options.sharedObjectsForMeLoading === true,
            sharedObjectsForMeError: String(options.sharedObjectsForMeError || ''),
            initiallyCollapseHierarchy: options.initiallyCollapseHierarchy === true,
        },
        global: {
            stubs: vuetifyStubs,
        }
    })
}

async function openWorkspace(): Promise<void> {
    const workspaceToggle = screen.getByRole('button', { name: /workspace/i })

    if (workspaceToggle.getAttribute('aria-expanded') !== 'true') {
        await fireEvent.click(workspaceToggle)
    }
}

describe('MaterialsSubjectsContentsTree', () => {
    beforeEach(() => {
        axiosMock.post.mockReset()
        axiosMock.put.mockReset()
        axiosMock.delete.mockReset()
    })

    it('switches between workspace and shared contents without showing both at once', async () => {
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
                        units: [],
                    },
                ],
            },
        ], {
            sharedObjectsForMe: [
                {
                    ruleId: 77,
                    scopeType: 'all',
                    scopeLabel: 'Workspace',
                    scopeObjectLabel: 'Geteiltes Fach',
                    permission: 'full_access',
                    permissionLabel: 'VOLLZUGRIFF',
                    fromUserLabel: 'Lehrer Eins',
                    hierarchy: [],
                    materialsCount: 3,
                },
            ],
        })

        expect(screen.getByText('Mathematik')).toBeInTheDocument()
        expect(screen.queryByText('Geteiltes Fach')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Fach hinzufügen')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Thema hinzufügen')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Bereich hinzufügen')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))

        expect(screen.queryByText('Mathematik')).not.toBeInTheDocument()
        expect(screen.getByText('Geteiltes Fach')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: /workspace/i }))

        expect(screen.getByText('Mathematik')).toBeInTheDocument()
        expect(screen.queryByText('Geteiltes Fach')).not.toBeInTheDocument()
    })

    it('collapses and expands a workspace subject', async () => {
        renderTree([
            {
                id: 1,
                name: 'Mathematik',
                materials: [
                    {
                        id: 91,
                        title: 'Arbeitsblatt A',
                        attachmentsCount: 0,
                        status: 'inbox',
                    },
                ],
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

        await openWorkspace()

        const toggle = screen.getByRole('button', { name: 'Fach Mathematik ein- oder ausklappen' })

        expect(screen.getByText('Algebra')).toBeInTheDocument()
        expect(screen.getByText('Arbeitsblatt A')).toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.queryByText('Algebra')).not.toBeInTheDocument()
        expect(screen.queryByText('Arbeitsblatt A')).not.toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.getByText('Algebra')).toBeInTheDocument()
        expect(screen.getByText('Arbeitsblatt A')).toBeInTheDocument()
    })

    it('keeps nested workspace hierarchy expanded by default when the component uses its real defaults', async () => {
        renderTree([
            {
                id: 1,
                name: 'Mathematik',
                materials: [
                    {
                        id: 91,
                        title: 'Arbeitsblatt A',
                        attachmentsCount: 0,
                        status: 'inbox',
                    },
                ],
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

        await openWorkspace()

        expect(screen.getByRole('button', { name: 'Fach Mathematik ein- oder ausklappen' })).toBeInTheDocument()
        expect(screen.getByText('Algebra')).toBeInTheDocument()
        expect(screen.getByText('Arbeitsblatt A')).toBeInTheDocument()
    })

    it('collapses and expands a workspace topic', async () => {
        renderTree([
            {
                id: 1,
                name: 'Mathematik',
                materials: [],
                topics: [
                    {
                        id: 11,
                        name: 'Algebra',
                        materials: [
                            {
                                id: 91,
                                title: 'Thema Material',
                                attachmentsCount: 0,
                                status: 'inbox',
                            },
                        ],
                        units: [
                            {
                                id: 21,
                                name: 'Lineare Gleichungen',
                                materials: [],
                            },
                        ],
                    },
                ],
            },
        ])

        await openWorkspace()

        const toggle = screen.getByRole('button', { name: 'Thema Algebra ein- oder ausklappen' })

        expect(screen.getByText('Thema Material')).toBeInTheDocument()
        expect(screen.getByText('Lineare Gleichungen')).toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.queryByText('Thema Material')).not.toBeInTheDocument()
        expect(screen.queryByText('Lineare Gleichungen')).not.toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.getByText('Thema Material')).toBeInTheDocument()
        expect(screen.getByText('Lineare Gleichungen')).toBeInTheDocument()
    })

    it('shows branch material counts on workspace selection buttons', async () => {
        renderTree([
            {
                id: 1,
                name: 'Mathematik',
                materials: [
                    {
                        id: 91,
                        title: 'Arbeitsblatt A',
                        attachmentsCount: 0,
                        status: 'inbox',
                    },
                ],
                topics: [
                    {
                        id: 11,
                        name: 'Algebra',
                        materials: [
                            {
                                id: 92,
                                title: 'Thema Material',
                                attachmentsCount: 0,
                                status: 'inbox',
                            },
                        ],
                        units: [
                            {
                                id: 21,
                                name: 'Lineare Gleichungen',
                                materials: [
                                    {
                                        id: 93,
                                        title: 'Einheitsmaterial',
                                        attachmentsCount: 0,
                                        status: 'inbox',
                                    },
                                ],
                            },
                        ],
                    },
                ],
            },
        ], {
            sharedObjectsForMe: [
                {
                    ruleId: 77,
                    materialsCount: 4,
                },
            ],
            archivedSharedObjectsForMe: [
                {
                    ruleId: 88,
                    materialsCount: 5,
                },
            ],
        })

        expect(within(screen.getByRole('button', { name: 'Workspace' })).getByText('3')).toHaveClass('overview-subjects-material-count')
        expect(within(screen.getByRole('button', { name: 'Für mich geteilt' })).getByText('4')).toHaveClass('overview-subjects-material-count')
        expect(within(screen.getByRole('button', { name: 'Archiv' })).getByText('5')).toHaveClass('overview-subjects-material-count')

        await openWorkspace()

        const subjectButton = screen.getByText('Mathematik').closest('button')
        expect(subjectButton).not.toBeNull()

        expect(within(subjectButton as HTMLButtonElement).getByText('3')).toHaveClass('overview-subjects-material-count')

        await fireEvent.click(subjectButton as HTMLButtonElement)

        const topicButton = screen.getByText('Algebra').closest('button')
        expect(topicButton).not.toBeNull()

        expect(within(topicButton as HTMLButtonElement).getByText('2')).toHaveClass('overview-subjects-material-count')

        await fireEvent.click(topicButton as HTMLButtonElement)

        const unitButton = screen.getByText('Lineare Gleichungen').closest('button')
        expect(unitButton).not.toBeNull()

        expect(within(unitButton as HTMLButtonElement).getByText('1')).toHaveClass('overview-subjects-material-count')
    })

    it('toggles selected workspace subject topic and unit buttons back off on repeated click', async () => {
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
                                id: 21,
                                name: 'Lineare Gleichungen',
                                materials: [],
                            },
                        ],
                    },
                ],
            },
        ])

        await openWorkspace()

        const subjectButton = screen.getByRole('button', { name: /^Mathematik$/i })
        await fireEvent.click(subjectButton)
        expect(subjectButton).toHaveAttribute('variant', 'tonal')
        expect(screen.getByRole('button', { name: /^Algebra$/i })).toBeInTheDocument()

        await fireEvent.click(subjectButton)
        expect(subjectButton).toHaveAttribute('variant', 'outlined')
        expect(screen.queryByRole('button', { name: /^Algebra$/i })).not.toBeInTheDocument()

        await fireEvent.click(subjectButton)
        const topicButton = screen.getByRole('button', { name: /^Algebra$/i })
        await fireEvent.click(topicButton)
        expect(topicButton).toHaveAttribute('variant', 'tonal')
        expect(screen.getByRole('button', { name: /^Lineare Gleichungen$/i })).toBeInTheDocument()

        await fireEvent.click(topicButton)
        expect(topicButton).toHaveAttribute('variant', 'outlined')
        expect(screen.queryByRole('button', { name: /^Lineare Gleichungen$/i })).not.toBeInTheDocument()

        await fireEvent.click(topicButton)
        const unitButton = screen.getByRole('button', { name: /^Lineare Gleichungen$/i })
        await fireEvent.click(unitButton)
        expect(unitButton).toHaveAttribute('variant', 'tonal')
        expect(screen.getByText(/^Einheit:$/i)).toBeInTheDocument()

        await fireEvent.click(unitButton)
        expect(unitButton).toHaveAttribute('variant', 'outlined')
        expect(screen.queryByText(/^Einheit:$/i)).not.toBeInTheDocument()
    })

    it('shows the workspace delete button only when the workspace has content', () => {
        const filledTree = renderTree([
            {
                id: 1,
                name: 'Mathematik',
                materials: [],
                topics: [],
            },
        ], {
            activeWorkspace: {
                id: 50,
                name: 'Workspace',
            },
            enableCreateButtons: true,
        })

        expect(screen.getByTitle('Workspace leeren')).toBeInTheDocument()

        filledTree.unmount()

        renderTree([], {
            activeWorkspace: {
                id: 50,
                name: 'Workspace',
            },
            enableCreateButtons: true,
        })

        expect(screen.queryByTitle('Workspace leeren')).not.toBeInTheDocument()
    })

    it('keeps shared active when local workspace selection changes but shared stays expanded in props', async () => {
        const Host = defineComponent({
            components: { MaterialsSubjectsContentsTree },
            data() {
                return {
                    items: [
                        {
                            id: 1,
                            name: 'Mathematik',
                            materials: [],
                            topics: [
                                {
                                    id: 20,
                                    name: 'Grundlagen',
                                    materials: [],
                                    units: [
                                        {
                                            id: 30,
                                            name: 'Dateien',
                                            materials: [],
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                    sharedObjectsForMe: [
                        {
                            ruleId: 77,
                            scopeType: 'all',
                            scopeLabel: 'Workspace',
                            scopeObjectLabel: 'Geteiltes Fach',
                            permission: 'full_access',
                            permissionLabel: 'VOLLZUGRIFF',
                            fromUserLabel: 'Lehrer Eins',
                            hierarchy: [],
                            materialsCount: 3,
                        },
                    ],
                    sharedForMeExpanded: true,
                    sharedForMeArchiveExpanded: false,
                    workspaceStructureExpanded: false,
                    expandedSharedItems: {},
                }
            },
            methods: {
                forceWorkspaceSelection() {
                    this.$refs.tree?.selectWorkspacePath?.({ subjectId: 1 })
                },
                toggleSharedItemExpanded(key) {
                    const nextExpandedSharedItems = {
                        ...this.expandedSharedItems,
                    }

                    nextExpandedSharedItems[key] = nextExpandedSharedItems[key] !== true
                    this.expandedSharedItems = nextExpandedSharedItems
                },
            },
            template: `
                <div>
                    <button type="button" @click="forceWorkspaceSelection">Workspace wiederherstellen</button>
                    <MaterialsSubjectsContentsTree
                        ref="tree"
                        :items="items"
                        :active-workspace="{ id: 50, name: 'Workspace' }"
                        :action-busy="false"
                        :enable-share-buttons="false"
                        :enable-create-buttons="false"
                        :enable-remove-buttons="false"
                        :show-share-indicators="false"
                        :share-indicator-color-fn="() => ''"
                        :status-color-fn="() => 'primary'"
                        :status-label-fn="() => 'Entwurf'"
                        :subject-group-style-fn="() => ({})"
                        :topic-group-style-fn="() => ({})"
                        :shared-objects-for-me="sharedObjectsForMe"
                        :archived-shared-objects-for-me="[]"
                        :shared-objects-for-me-loading="false"
                        :shared-objects-for-me-error="''"
                        :shared-for-me-expanded="sharedForMeExpanded"
                        :shared-for-me-archive-expanded="sharedForMeArchiveExpanded"
                        :workspace-structure-expanded="workspaceStructureExpanded"
                        :expanded-shared-items="expandedSharedItems"
                        @toggle-shared-for-me-expanded="sharedForMeExpanded = !sharedForMeExpanded"
                        @toggle-shared-for-me-archive-expanded="sharedForMeArchiveExpanded = !sharedForMeArchiveExpanded"
                        @toggle-workspace-structure-expanded="workspaceStructureExpanded = !workspaceStructureExpanded"
                        @toggle-shared-item-expanded="toggleSharedItemExpanded" />
                </div>
            `,
        })

        render(Host, {
            global: {
                stubs: vuetifyStubs,
            },
        })

        expect(screen.queryByText('Mathematik')).not.toBeInTheDocument()
        expect(screen.getByText('Geteiltes Fach')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Workspace wiederherstellen' }))

        expect(screen.getByText('Geteiltes Fach')).toBeInTheDocument()
    })

    it('switches back to workspace when workspace is clicked from a shared refresh state', async () => {
        const Host = defineComponent({
            components: { MaterialsSubjectsContentsTree },
            data() {
                return {
                    items: [
                        {
                            id: 1,
                            name: 'Mathematik',
                            materials: [],
                            topics: [
                                {
                                    id: 20,
                                    name: 'Grundlagen',
                                    materials: [],
                                    units: [
                                        {
                                            id: 30,
                                            name: 'Dateien',
                                            materials: [],
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                    sharedObjectsForMe: [
                        {
                            ruleId: 77,
                            scopeType: 'all',
                            scopeLabel: 'Workspace',
                            scopeObjectLabel: 'Geteiltes Fach',
                            permission: 'full_access',
                            permissionLabel: 'VOLLZUGRIFF',
                            fromUserLabel: 'Lehrer Eins',
                            hierarchy: [],
                            materialsCount: 3,
                        },
                    ],
                    sharedForMeExpanded: true,
                    sharedForMeArchiveExpanded: false,
                    workspaceStructureExpanded: false,
                    expandedSharedItems: {},
                }
            },
            methods: {
                toggleSharedForMeExpanded() {
                    this.sharedForMeExpanded = !this.sharedForMeExpanded
                },
                toggleSharedForMeArchiveExpanded() {
                    this.sharedForMeArchiveExpanded = !this.sharedForMeArchiveExpanded
                },
            },
            template: `
                <MaterialsSubjectsContentsTree
                    :items="items"
                    :active-workspace="{ id: 50, name: 'Workspace' }"
                    :action-busy="false"
                    :enable-share-buttons="false"
                    :enable-create-buttons="false"
                    :enable-remove-buttons="false"
                    :show-share-indicators="false"
                    :share-indicator-color-fn="() => ''"
                    :status-color-fn="() => 'primary'"
                    :status-label-fn="() => 'Entwurf'"
                    :subject-group-style-fn="() => ({})"
                    :topic-group-style-fn="() => ({})"
                    :shared-objects-for-me="sharedObjectsForMe"
                    :archived-shared-objects-for-me="[]"
                    :shared-objects-for-me-loading="false"
                    :shared-objects-for-me-error="''"
                    :shared-for-me-expanded="sharedForMeExpanded"
                    :shared-for-me-archive-expanded="sharedForMeArchiveExpanded"
                    :workspace-structure-expanded="workspaceStructureExpanded"
                    :expanded-shared-items="expandedSharedItems"
                    @toggle-shared-for-me-expanded="toggleSharedForMeExpanded"
                    @toggle-shared-for-me-archive-expanded="toggleSharedForMeArchiveExpanded" />
            `,
        })

        render(Host, {
            global: {
                stubs: vuetifyStubs,
            },
        })

        expect(screen.getByText('Geteiltes Fach')).toBeInTheDocument()
        expect(screen.queryByText('Mathematik')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: /^workspace$/i }))

        expect(screen.queryByText('Geteiltes Fach')).not.toBeInTheDocument()
        expect(screen.getByRole('button', { name: /^Mathematik$/i })).toBeInTheDocument()
    })

    it('shows collapse toggles only for subjects topics and units with child elements', async () => {
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
                                id: 21,
                                name: 'Lineare Gleichungen',
                                materials: [],
                            },
                            {
                                id: 22,
                                name: 'Leere Unit',
                                materials: [],
                            },
                        ],
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
            sharedObjectsForMe: [
                {
                    ruleId: 781,
                    scopeType: 'all',
                    scopeObjectLabel: 'Geteiltes Fach',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Eins',
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
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
                                            name: 'Leere Shared Unit',
                                            materials: [],
                                        },
                                    ],
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
            archivedSharedObjectsForMe: [
                {
                    ruleId: 916,
                    scopeType: 'all',
                    scopeObjectLabel: 'Archiviertes Fach',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Archiv',
                    hierarchy: [
                        {
                            id: 30,
                            name: 'Chemie',
                            materials: [],
                            topics: [
                                {
                                    id: 40,
                                    name: 'Atome',
                                    materials: [],
                                    units: [
                                        {
                                            id: 50,
                                            name: 'Moleküle',
                                            materials: [],
                                        },
                                        {
                                            id: 51,
                                            name: 'Leere Archivunit',
                                            materials: [],
                                        },
                                    ],
                                },
                            ],
                        },
                        {
                            id: 31,
                            name: 'Leeres Archivfach',
                            materials: [],
                            topics: [],
                        },
                    ],
                },
            ],
        })

        await openWorkspace()

        expect(screen.getByRole('button', { name: 'Fach Mathematik ein- oder ausklappen' })).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Fach Biologie ein- oder ausklappen' })).toBeNull()
        expect(screen.getByRole('button', { name: 'Thema Algebra ein- oder ausklappen' })).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Bereich Lineare Gleichungen ein- oder ausklappen' })).toBeNull()
        expect(screen.queryByRole('button', { name: 'Bereich Leere Unit ein- oder ausklappen' })).toBeNull()

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        const sharedItem = screen.getByText('Geteiltes Fach').closest('.overview-shared-item') as HTMLElement
        expect(screen.getByRole('button', { name: 'Fach Informatik ein- oder ausklappen' })).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Fach Leeres Fach ein- oder ausklappen' })).toBeNull()
        expect(within(sharedItem).getByRole('button', { name: 'Thema Algebra ein- oder ausklappen' })).toBeInTheDocument()
        expect(within(sharedItem).queryByRole('button', { name: 'Bereich Leere Shared Unit ein- oder ausklappen' })).toBeNull()

        await fireEvent.click(screen.getByRole('button', { name: /^archiv$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        const archivedItem = screen.getByText('Archiviertes Fach').closest('.overview-shared-item') as HTMLElement
        expect(screen.getByRole('button', { name: 'Fach Chemie ein- oder ausklappen' })).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Fach Leeres Archivfach ein- oder ausklappen' })).toBeNull()
        expect(within(archivedItem).getByRole('button', { name: 'Thema Atome ein- oder ausklappen' })).toBeInTheDocument()
        expect(within(archivedItem).queryByRole('button', { name: 'Bereich Leere Archivunit ein- oder ausklappen' })).toBeNull()
    })

    it('collapses and expands a workspace unit', async () => {
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
                                id: 21,
                                name: 'Lineare Gleichungen',
                                materials: [
                                    {
                                        id: 91,
                                        title: 'Unit Material',
                                        attachmentsCount: 0,
                                        status: 'inbox',
                                    },
                                ],
                            },
                        ],
                    },
                ],
            },
        ])

        await openWorkspace()

        const toggle = screen.getByRole('button', { name: 'Bereich Lineare Gleichungen ein- oder ausklappen' })

        expect(screen.getByText('Unit Material')).toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.queryByText('Unit Material')).not.toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.getByText('Unit Material')).toBeInTheDocument()
    })

    it('shows workspace unit material creation as a plus card', async () => {
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
                        units: [
                            {
                                id: 21,
                                name: 'Lineare Gleichungen',
                                materials: [],
                            },
                        ],
                    },
                ],
            },
        ], {
            enableCreateButtons: true,
        })

        await openWorkspace()
        await fireEvent.click(screen.getByRole('button', { name: 'Mathematik' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Algebra' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Lineare Gleichungen' }))

        const createCard = screen.getByRole('button', { name: 'Neues Material in Bereich anlegen' })

        expect(createCard.closest('.overview-material-card--create')).not.toBeNull()

        await fireEvent.click(createCard)

        const openCreateEvents = emitted('open-create') || []
        expect(openCreateEvents).toHaveLength(1)
        expect(openCreateEvents[0]?.[0]).toMatchObject({
            level: 'unit',
            subject: 'Mathematik',
            topic: 'Algebra',
            unit: 'Lineare Gleichungen',
        })
    })

    it('collapses and expands a shared unit hierarchy', async () => {
        renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 773,
                    scopeType: 'all',
                    scopeObjectLabel: 'Geteilte Unit',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Eins',
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
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
                                            materials: [
                                                {
                                                    id: 101,
                                                    title: 'Geteiltes Unit-Material',
                                                    attachmentsCount: 0,
                                                    status: 'inbox',
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        const toggle = screen.getByRole('button', { name: 'Bereich Lineare Gleichungen ein- oder ausklappen' })

        expect(screen.getByText('Geteiltes Unit-Material')).toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.queryByText('Geteiltes Unit-Material')).not.toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.getByText('Geteiltes Unit-Material')).toBeInTheDocument()
    })

    it('collapses and expands an archived shared unit hierarchy', async () => {
        renderTree([], {
            archivedSharedObjectsForMe: [
                {
                    ruleId: 917,
                    scopeType: 'all',
                    scopeObjectLabel: 'Archivierte Unit',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Archiv',
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
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
                                            materials: [
                                                {
                                                    id: 101,
                                                    title: 'Archiviertes Unit-Material',
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

        await fireEvent.click(screen.getByRole('button', { name: /^archiv$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        const toggle = screen.getByRole('button', { name: 'Bereich Lineare Gleichungen ein- oder ausklappen' })

        expect(screen.getByText('Archiviertes Unit-Material')).toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.queryByText('Archiviertes Unit-Material')).not.toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.getByText('Archiviertes Unit-Material')).toBeInTheDocument()
    })

    it('shows workspace Struktur ändern mode with node actions and emits workspace move refresh event', async () => {
        axiosMock.post.mockResolvedValue({ data: {} })

        const { emitted, rerender } = renderTree([
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

        await openWorkspace()

        expect(screen.getByText('Mathematik')).toBeInTheDocument()
        expect(screen.queryByTitle('Fach nach oben')).not.toBeInTheDocument()
        expect(screen.getByText('Struktur ändern')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))

        expect(screen.getByRole('button', { name: 'Struktur schließen' })).toBeInTheDocument()
        expect(screen.getAllByTitle('Fach hinzufügen').length).toBeGreaterThan(0)
        expect(screen.getAllByTitle('Thema hinzufügen').length).toBeGreaterThan(0)
        expect(screen.queryByTitle(/Neues Material in/i)).not.toBeInTheDocument()
        const mathematikRow = screen.getByText('Mathematik').closest('.overview-subjects-node-row') as HTMLElement
        expect(within(mathematikRow).queryByTitle('Teilen')).toBeNull()
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

    it('shows a Bereich button for each workspace topic and creates a unit for an empty topic', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: {
                    id: 51,
                    name: 'Prismen',
                    topic_id: 12,
                },
            },
        })

        const { emitted, rerender } = renderTree([
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
                                id: 21,
                                name: 'Lineare Gleichungen',
                                materials: [],
                            },
                        ],
                    },
                    {
                        id: 12,
                        name: 'Geometrie',
                        materials: [],
                        units: [],
                    },
                ],
            },
        ], {
            enableCreateButtons: true,
        })

        await openWorkspace()
        await fireEvent.click(screen.getByRole('button', { name: /^Mathematik$/i }))
        await fireEvent.click(screen.getByRole('button', { name: /^Geometrie$/i }))
        await fireEvent.click(screen.getByTitle('Bereich hinzufügen'))

        expect(screen.getByText('Bereich hinzufügen')).toBeInTheDocument()

        const input = screen.getByRole('textbox', { name: 'Titel' })
        await fireEvent.update(input, 'Prismen')
        await fireEvent.keyDown(input, { key: 'Enter', code: 'Enter' })

        expect(axiosMock.post).toHaveBeenCalledTimes(1)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/units', {
            data: {
                topic_id: 12,
                name: 'Prismen',
            },
        })

        const createdEvents = emitted('workspace-node-created') || []
        expect(createdEvents).toHaveLength(1)
        expect((createdEvents[0]?.[0] as any)?.level).toBe('unit')
        expect((createdEvents[0]?.[0] as any)?.nodeId).toBe(51)
        expect((createdEvents[0]?.[0] as any)?.name).toBe('Prismen')
        expect((createdEvents[0]?.[0] as any)?.parentTopicId).toBe(12)

        await rerender({
            items: [
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
                                    id: 21,
                                    name: 'Lineare Gleichungen',
                                    materials: [],
                                },
                            ],
                        },
                        {
                            id: 12,
                            name: 'Geometrie',
                            materials: [],
                            units: [
                                {
                                    id: 51,
                                    name: 'Prismen',
                                    materials: [],
                                },
                            ],
                        },
                    ],
                },
            ],
            activeWorkspace: null,
            enableCreateButtons: true,
            enableShareButtons: false,
            enableRemoveButtons: false,
            sharedObjectsForMe: [],
            archivedSharedObjectsForMe: [],
            sharedObjectsForMeLoading: false,
            sharedObjectsForMeError: '',
            initiallyCollapseHierarchy: false,
        })

        expect(screen.getByRole('button', { name: /^Mathematik$/i })).toHaveAttribute('variant', 'tonal')
        expect(screen.getByRole('button', { name: /^Geometrie$/i })).toHaveAttribute('variant', 'tonal')
        expect(screen.getByRole('button', { name: /^Prismen$/i })).toHaveAttribute('variant', 'tonal')
    })

    it('shows Struktur ändern for an empty workspace and allows adding the first subject', async () => {
        renderTree([], {
            enableCreateButtons: true,
        })

        await openWorkspace()

        expect(screen.getByRole('button', { name: 'Struktur ändern' })).toBeInTheDocument()
        expect(screen.queryByTitle('Fach hinzufügen')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))

        expect(screen.getByRole('button', { name: 'Struktur schließen' })).toBeInTheDocument()
        expect(screen.getAllByTitle('Fach hinzufügen')).toHaveLength(1)
    })

    it('sends workspace subject creation with insert target when Fach button above a subject is used', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: {
                    id: 61,
                    name: 'Biologie',
                },
            },
        })

        const { emitted, rerender } = renderTree([
            {
                id: 1,
                name: 'Mathematik',
                materials: [],
                topics: [],
            },
            {
                id: 2,
                name: 'Biologie Alt',
                materials: [],
                topics: [],
            },
        ], {
            enableCreateButtons: true,
        })

        await openWorkspace()
        await fireEvent.click(screen.getAllByTitle('Fach hinzufügen')[0])

        const input = screen.getByRole('textbox', { name: 'Titel' })
        await fireEvent.update(input, 'Biologie')
        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))

        expect(axiosMock.post).toHaveBeenCalledTimes(1)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/subjects', {
            data: {
                name: 'Biologie',
            },
        })

        const createdEvents = emitted('workspace-node-created') || []
        expect(createdEvents).toHaveLength(1)
        expect((createdEvents[0]?.[0] as any)?.level).toBe('subject')
        expect((createdEvents[0]?.[0] as any)?.nodeId).toBe(61)
        expect((createdEvents[0]?.[0] as any)?.name).toBe('Biologie')

        await rerender({
            items: [
                {
                    id: 1,
                    name: 'Mathematik',
                    materials: [],
                    topics: [],
                },
                {
                    id: 61,
                    name: 'Biologie',
                    materials: [],
                    topics: [],
                },
                {
                    id: 2,
                    name: 'Biologie Alt',
                    materials: [],
                    topics: [],
                },
            ],
            activeWorkspace: null,
            enableCreateButtons: true,
            enableShareButtons: false,
            enableRemoveButtons: false,
            sharedObjectsForMe: [],
            archivedSharedObjectsForMe: [],
            sharedObjectsForMeLoading: false,
            sharedObjectsForMeError: '',
            initiallyCollapseHierarchy: false,
        })

        expect(screen.getByRole('button', { name: /^Biologie$/i })).toHaveAttribute('variant', 'tonal')
    })

    it('keeps the workspace subject open and selects a newly created topic after refresh', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: {
                    id: 71,
                    name: 'Geometrie',
                    subject_id: 1,
                },
            },
        })

        const { emitted, rerender } = renderTree([
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
        ], {
            enableCreateButtons: true,
        })

        await openWorkspace()
        await fireEvent.click(screen.getByRole('button', { name: /^Mathematik$/i }))
        await fireEvent.click(screen.getByTitle('Thema hinzufügen'))

        const input = screen.getByRole('textbox', { name: 'Titel' })
        await fireEvent.update(input, 'Geometrie')
        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))

        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/topics', {
            data: {
                subject_id: 1,
                name: 'Geometrie',
            },
        })

        const createdEvents = emitted('workspace-node-created') || []
        expect(createdEvents).toHaveLength(1)
        expect((createdEvents[0]?.[0] as any)?.level).toBe('topic')
        expect((createdEvents[0]?.[0] as any)?.nodeId).toBe(71)
        expect((createdEvents[0]?.[0] as any)?.name).toBe('Geometrie')
        expect((createdEvents[0]?.[0] as any)?.parentSubjectId).toBe(1)

        await rerender({
            items: [
                {
                    id: 1,
                    name: 'Mathematik',
                    materials: [],
                    topics: [
                        {
                            id: 71,
                            name: 'Geometrie',
                            materials: [],
                            units: [],
                        },
                        {
                            id: 11,
                            name: 'Algebra',
                            materials: [],
                            units: [],
                        },
                    ],
                },
            ],
            activeWorkspace: null,
            enableCreateButtons: true,
            enableShareButtons: false,
            enableRemoveButtons: false,
            sharedObjectsForMe: [],
            archivedSharedObjectsForMe: [],
            sharedObjectsForMeLoading: false,
            sharedObjectsForMeError: '',
            initiallyCollapseHierarchy: false,
        })

        expect(screen.getByRole('button', { name: /^Mathematik$/i })).toHaveAttribute('variant', 'tonal')
        expect(screen.getByRole('button', { name: /^Geometrie$/i })).toHaveAttribute('variant', 'tonal')
    })

    it('keeps the workspace path open and selects a newly created unit after refresh', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: {
                    id: 81,
                    name: 'Einheit 5',
                    topic_id: 11,
                },
            },
        })

        const { emitted, rerender } = renderTree([
            {
                id: 1,
                name: 'Mathematik',
                materials: [],
                topics: [
                    {
                        id: 11,
                        name: 'Bereich',
                        materials: [],
                        units: [
                            {
                                id: 21,
                                name: 'Einheit 1',
                                materials: [],
                            },
                            {
                                id: 22,
                                name: 'Einheit 2',
                                materials: [],
                            },
                        ],
                    },
                ],
            },
        ], {
            enableCreateButtons: true,
        })

        await openWorkspace()
        await fireEvent.click(screen.getByRole('button', { name: /^Mathematik$/i }))
        await fireEvent.click(screen.getByRole('button', { name: /^Bereich$/i }))
        await fireEvent.click(screen.getByTitle('Bereich hinzufügen'))

        const input = screen.getByRole('textbox', { name: 'Titel' })
        await fireEvent.update(input, 'Einheit 5')
        await fireEvent.click(screen.getByRole('button', { name: 'Speichern' }))

        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/units', {
            data: {
                topic_id: 11,
                name: 'Einheit 5',
            },
        })

        const createdEvents = emitted('workspace-node-created') || []
        expect(createdEvents).toHaveLength(1)
        expect((createdEvents[0]?.[0] as any)?.level).toBe('unit')
        expect((createdEvents[0]?.[0] as any)?.nodeId).toBe(81)
        expect((createdEvents[0]?.[0] as any)?.name).toBe('Einheit 5')
        expect((createdEvents[0]?.[0] as any)?.parentTopicId).toBe(11)

        await rerender({
            items: [
                {
                    id: 1,
                    name: 'Mathematik',
                    materials: [],
                    topics: [
                        {
                            id: 11,
                            name: 'Bereich',
                            materials: [],
                            units: [
                                {
                                    id: 21,
                                    name: 'Einheit 1',
                                    materials: [],
                                },
                                {
                                    id: 22,
                                    name: 'Einheit 2',
                                    materials: [],
                                },
                                {
                                    id: 81,
                                    name: 'Einheit 5',
                                    materials: [],
                                },
                            ],
                        },
                    ],
                },
            ],
            activeWorkspace: null,
            enableCreateButtons: true,
            enableShareButtons: false,
            enableRemoveButtons: false,
            sharedObjectsForMe: [],
            archivedSharedObjectsForMe: [],
            sharedObjectsForMeLoading: false,
            sharedObjectsForMeError: '',
            initiallyCollapseHierarchy: false,
        })

        expect(screen.getByRole('button', { name: /^Mathematik$/i })).toHaveAttribute('variant', 'tonal')
        expect(screen.getByRole('button', { name: /^Bereich$/i })).toHaveAttribute('variant', 'tonal')
        expect(screen.getByRole('button', { name: /^Einheit 5$/i })).toHaveAttribute('variant', 'tonal')
    })

    it('restores the workspace path from parent selection after the tree remounts', async () => {
        const { rerender } = renderTree([
            {
                id: 1,
                name: 'Mathematik',
                materials: [],
                topics: [
                    {
                        id: 11,
                        name: 'Bereich',
                        materials: [],
                        units: [
                            {
                                id: 81,
                                name: 'Einheit 5',
                                materials: [],
                            },
                        ],
                    },
                ],
            },
        ], {
            enableCreateButtons: true,
            treeKey: 'tree-a',
        })

        await rerender({
            items: [
                {
                    id: 1,
                    name: 'Mathematik',
                    materials: [],
                    topics: [
                        {
                            id: 11,
                            name: 'Bereich',
                            materials: [],
                            units: [
                                {
                                    id: 81,
                                    name: 'Einheit 5',
                                    materials: [],
                                },
                            ],
                        },
                    ],
                },
            ],
            activeWorkspace: null,
            enableCreateButtons: true,
            enableShareButtons: false,
            enableRemoveButtons: false,
            workspaceSelection: {
                subjectId: 1,
                topicId: 11,
                unitId: 81,
            },
            treeKey: 'tree-b',
            sharedObjectsForMe: [],
            archivedSharedObjectsForMe: [],
            sharedObjectsForMeLoading: false,
            sharedObjectsForMeError: '',
            initiallyCollapseHierarchy: false,
        })

        expect(screen.getByRole('button', { name: /^Mathematik$/i })).toHaveAttribute('variant', 'tonal')
        expect(screen.getByRole('button', { name: /^Bereich$/i })).toHaveAttribute('variant', 'tonal')
        expect(screen.getByRole('button', { name: /^Einheit 5$/i })).toHaveAttribute('variant', 'tonal')
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
                    fromUserEmail: 'lehrer.eins@example.test',
                    fromSchoolLabel: 'CDGym',
                    materialsCount: 3,
                },
            ],
        })

        expect(screen.queryByText('Geteilte Mathematik')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))

        expect(screen.getByText('Geteilte Mathematik')).toBeInTheDocument()
        expect(screen.getByText('Mathematik / Algebra')).toBeInTheDocument()
        expect(screen.getByText(/Von: Lehrer Eins \(lehrer\.eins@example\.test\)/i)).toBeInTheDocument()

        const sharedItem = container.querySelector('.overview-shared-item')
        expect(sharedItem).not.toBeNull()
        expect((sharedItem as HTMLElement).style.flex).toContain('24rem')
        expect((sharedItem as HTMLElement).style.maxWidth).toBe('28rem')
    })

    it('collapses and expands a shared subject hierarchy', async () => {
        renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 771,
                    scopeType: 'all',
                    scopeObjectLabel: 'Geteiltes Fach',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Eins',
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
                            materials: [
                                {
                                    id: 101,
                                    title: 'Geteiltes Material',
                                    attachmentsCount: 0,
                                    status: 'inbox',
                                },
                            ],
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        const toggle = screen.getByRole('button', { name: 'Fach Informatik ein- oder ausklappen' })

        expect(screen.getByText('Algebra')).toBeInTheDocument()
        expect(screen.getByText('Geteiltes Material')).toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.queryByText('Algebra')).not.toBeInTheDocument()
        expect(screen.queryByText('Geteiltes Material')).not.toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.getByText('Algebra')).toBeInTheDocument()
        expect(screen.getByText('Geteiltes Material')).toBeInTheDocument()
    })

    it('collapses and expands a shared topic hierarchy', async () => {
        renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 772,
                    scopeType: 'all',
                    scopeObjectLabel: 'Geteiltes Thema',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Eins',
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
                            materials: [],
                            topics: [
                                {
                                    id: 20,
                                    name: 'Algebra',
                                    materials: [
                                        {
                                            id: 101,
                                            title: 'Geteiltes Topic-Material',
                                            attachmentsCount: 0,
                                            status: 'inbox',
                                        },
                                    ],
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        const toggle = screen.getByRole('button', { name: 'Thema Algebra ein- oder ausklappen' })

        expect(screen.getByText('Geteiltes Topic-Material')).toBeInTheDocument()
        expect(screen.getByText('Lineare Gleichungen')).toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.queryByText('Geteiltes Topic-Material')).not.toBeInTheDocument()
        expect(screen.queryByText('Lineare Gleichungen')).not.toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.getByText('Geteiltes Topic-Material')).toBeInTheDocument()
        expect(screen.getByText('Lineare Gleichungen')).toBeInTheDocument()
    })

    it('shows archived shared objects when Für mich geteilt - Archiv is expanded', async () => {
        renderTree([], {
            archivedSharedObjectsForMe: [
                {
                    ruleId: 881,
                    scopeType: 'topic',
                    scopeObjectLabel: 'Archiviertes Thema',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Archiv',
                    fromUserEmail: 'lehrer.archiv@example.test',
                    fromSchoolLabel: 'CDGym',
                    materialsCount: 2,
                },
            ],
        })

        expect(screen.queryByText('Archiviertes Thema')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: /^archiv$/i }))

        expect(screen.getByText('Archiviertes Thema')).toBeInTheDocument()
        expect(screen.getByText('Thema')).toBeInTheDocument()
        expect(screen.getByText(/Von: Lehrer Archiv \(lehrer\.archiv@example\.test\)/i)).toBeInTheDocument()
    })

    it('shows read-only archived hierarchy preview without structure actions', async () => {
        const { container } = renderTree([], {
            archivedSharedObjectsForMe: [
                {
                    ruleId: 913,
                    scopeType: 'all',
                    scopeObjectLabel: 'Archivierter Workspace',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Drei',
                    materialsCount: 1,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
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
                                            materials: [
                                                {
                                                    id: 501,
                                                    title: 'Archiv Material',
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

        await fireEvent.click(screen.getByRole('button', { name: /für mich geteilt - archiv/i }))
        const archivedCard = container.querySelector('.overview-shared-item') as HTMLElement
        expect(archivedCard).not.toBeNull()
        expect(archivedCard.style.flex).toContain('24rem')
        expect(archivedCard.style.maxWidth).toBe('28rem')
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        expect(screen.getByText('Informatik')).toBeInTheDocument()
        expect(screen.getByText('Algebra')).toBeInTheDocument()
        expect(screen.getByText('Lineare Gleichungen')).toBeInTheDocument()
        expect(screen.getByText('Archiv Material')).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Aktivieren' })).toBeNull()
        expect(archivedCard.style.flex).toContain('100%')
        expect(archivedCard.style.maxWidth).toBe('100%')
        expect(screen.queryByRole('button', { name: 'Struktur ändern' })).toBeNull()
        expect(screen.queryByTitle(/bearbeiten$/i)).toBeNull()
        expect(screen.queryByTitle(/löschen$/i)).toBeNull()
        expect(screen.queryByTitle(/Neues Material in/i)).toBeNull()

        await fireEvent.click(screen.getByRole('button', { name: 'Schließen' }))
        expect(screen.getByRole('button', { name: 'Aktivieren' })).toBeInTheDocument()
    })

    it('collapses and expands an archived shared subject hierarchy', async () => {
        renderTree([], {
            archivedSharedObjectsForMe: [
                {
                    ruleId: 914,
                    scopeType: 'all',
                    scopeObjectLabel: 'Archiviertes Fach',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Archiv',
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
                            materials: [
                                {
                                    id: 101,
                                    title: 'Archiviertes Material',
                                },
                            ],
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

        await fireEvent.click(screen.getByRole('button', { name: /für mich geteilt - archiv/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        const toggle = screen.getByRole('button', { name: 'Fach Informatik ein- oder ausklappen' })

        expect(screen.getByText('Algebra')).toBeInTheDocument()
        expect(screen.getByText('Archiviertes Material')).toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.queryByText('Algebra')).not.toBeInTheDocument()
        expect(screen.queryByText('Archiviertes Material')).not.toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.getByText('Algebra')).toBeInTheDocument()
        expect(screen.getByText('Archiviertes Material')).toBeInTheDocument()
    })

    it('collapses and expands an archived shared topic hierarchy', async () => {
        renderTree([], {
            archivedSharedObjectsForMe: [
                {
                    ruleId: 915,
                    scopeType: 'all',
                    scopeObjectLabel: 'Archiviertes Thema',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Archiv',
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
                            materials: [],
                            topics: [
                                {
                                    id: 20,
                                    name: 'Algebra',
                                    materials: [
                                        {
                                            id: 101,
                                            title: 'Archiviertes Topic-Material',
                                        },
                                    ],
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

        await fireEvent.click(screen.getByRole('button', { name: /für mich geteilt - archiv/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        const toggle = screen.getByRole('button', { name: 'Thema Algebra ein- oder ausklappen' })

        expect(screen.getByText('Archiviertes Topic-Material')).toBeInTheDocument()
        expect(screen.getByText('Lineare Gleichungen')).toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.queryByText('Archiviertes Topic-Material')).not.toBeInTheDocument()
        expect(screen.queryByText('Lineare Gleichungen')).not.toBeInTheDocument()

        await fireEvent.click(toggle)

        expect(screen.getByText('Archiviertes Topic-Material')).toBeInTheDocument()
        expect(screen.getByText('Lineare Gleichungen')).toBeInTheDocument()
    })

    it('emits archive-shared-item when Archivieren is clicked in Für mich geteilt', async () => {
        const { emitted } = renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 911,
                    scopeType: 'all',
                    scopeObjectLabel: 'Aktive Freigabe',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 0,
                    hierarchy: [],
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Archivieren' }))

        const archiveEvents = emitted('archive-shared-item') || []
        expect(archiveEvents).toHaveLength(1)
        expect(archiveEvents[0]?.[0]).toBe(911)
    })

    it('emits activate-shared-item when Aktivieren is clicked in Für mich geteilt - Archiv', async () => {
        const { emitted } = renderTree([], {
            archivedSharedObjectsForMe: [
                {
                    ruleId: 912,
                    scopeType: 'topic',
                    scopeObjectLabel: 'Archivierte Freigabe',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Zwei',
                    materialsCount: 0,
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /für mich geteilt - archiv/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Aktivieren' }))

        const activateEvents = emitted('activate-shared-item') || []
        expect(activateEvents).toHaveLength(1)
        expect(activateEvents[0]?.[0]).toBe(912)
    })

    it('disables all other shared cards while one shared card is expanded', async () => {
        renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 801,
                    scopeType: 'all',
                    scopeObjectLabel: 'Erste Freigabe',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 1,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Mathematik',
                            materials: [],
                            topics: [],
                        },
                    ],
                },
                {
                    ruleId: 802,
                    scopeType: 'all',
                    scopeObjectLabel: 'Zweite Freigabe',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Zwei',
                    materialsCount: 1,
                    hierarchy: [
                        {
                            id: 20,
                            name: 'Biologie',
                            materials: [],
                            topics: [],
                        },
                    ],
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))

        const firstCard = screen.getByText('Erste Freigabe').closest('.overview-shared-item') as HTMLElement
        const secondCard = screen.getByText('Zweite Freigabe').closest('.overview-shared-item') as HTMLElement

        expect(firstCard).not.toBeNull()
        expect(secondCard).not.toBeNull()
        expect(within(secondCard).getByRole('button', { name: 'Anzeigen' })).not.toBeDisabled()

        await fireEvent.click(within(firstCard).getByRole('button', { name: 'Anzeigen' }))

        expect(firstCard.className).toContain('overview-shared-item--expanded')
        expect(secondCard.className).toContain('overview-shared-item--disabled')
        expect(within(secondCard).getByRole('button', { name: 'Archivieren' })).toBeDisabled()
        expect(within(secondCard).getByRole('button', { name: 'Anzeigen' })).toBeDisabled()

        await fireEvent.click(within(firstCard).getByRole('button', { name: 'Schließen' }))

        expect(firstCard.className).not.toContain('overview-shared-item--expanded')
        expect(secondCard.className).not.toContain('overview-shared-item--disabled')
        expect(within(firstCard).getByRole('button', { name: 'Archivieren' })).toBeInTheDocument()
        expect(within(secondCard).getByRole('button', { name: 'Archivieren' })).not.toBeDisabled()
        expect(within(secondCard).getByRole('button', { name: 'Anzeigen' })).not.toBeDisabled()
    })

    it('shows the shared scope type label on card line two', async () => {
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
                    ruleId: 701,
                    scopeType: 'topic',
                    scopeObjectLabel: 'Algebra',
                    scopePathLabel: 'Mathematik / Algebra',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 1,
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))

        expect(screen.getByText('Thema')).toBeInTheDocument()
        expect(screen.queryByText('Mathematik / Algebra')).not.toBeInTheDocument()
    })

    it('shows parent subject as context-only for full-access topic shares', async () => {
        const { container } = renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 702,
                    scopeType: 'topic',
                    scopeObjectLabel: 'Algebra',
                    scopePathLabel: 'Informatik - Algebra - Alle Einheiten',
                    permission: 'full_access',
                    permissionLabel: 'VOLLZUGRIFF',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 2,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
                            materials: [
                                {
                                    id: 100,
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
                                    materials: [],
                                    units: [],
                                },
                            ],
                        },
                    ],
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        const subjectRow = screen.getByText('Informatik').closest('.overview-shared-hierarchy-node') as HTMLElement
        expect(subjectRow).not.toBeNull()
        expect(subjectRow.className).toContain('overview-shared-hierarchy-node--context')
        expect(screen.queryByTitle('Neues Material in Fach anlegen')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))

        expect(screen.getByRole('button', { name: 'Struktur schließen' })).toBeInTheDocument()
        expect(screen.queryByTitle('Fach nach oben')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Fach nach unten')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Fach bearbeiten')).not.toBeInTheDocument()
        expect(screen.getByTitle('Thema bearbeiten')).toBeInTheDocument()
    })

    it('shows parent subject and topic as context-only for full-access unit shares', async () => {
        const { container } = renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 703,
                    scopeType: 'unit',
                    scopeObjectLabel: 'Lineare Gleichungen',
                    scopePathLabel: 'Informatik - Algebra - Lineare Gleichungen',
                    permission: 'full_access',
                    permissionLabel: 'VOLLZUGRIFF',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 2,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
                            materials: [
                                {
                                    id: 100,
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
                                            id: 101,
                                            title: 'Themamaterial',
                                            status: 'inbox',
                                            statusLabel: 'Neu/Idee',
                                            attachmentsCount: 0,
                                        },
                                    ],
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        const sharedItem = container.querySelector('.overview-shared-item') as HTMLElement
        const subjectRow = screen.getByText('Informatik').closest('.overview-shared-hierarchy-node') as HTMLElement
        const topicRow = screen.getByText('Algebra').closest('.overview-shared-hierarchy-node') as HTMLElement
        const unitRow = within(sharedItem).getByTitle('Neues Material in Bereich anlegen').closest('.overview-shared-hierarchy-node') as HTMLElement

        expect(subjectRow).not.toBeNull()
        expect(topicRow).not.toBeNull()
        expect(unitRow).not.toBeNull()

        expect(subjectRow.className).toContain('overview-shared-hierarchy-node--context')
        expect(topicRow.className).toContain('overview-shared-hierarchy-node--context')
        expect(unitRow.className).not.toContain('overview-shared-hierarchy-node--context')

        expect(screen.queryByTitle('Neues Material in Fach anlegen')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Neues Material in Thema anlegen')).not.toBeInTheDocument()
        await fireEvent.click(screen.getByRole('button', { name: 'Informatik' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Grundlagen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Dateien' }))

        expect(screen.getByTitle('Neues Material in Bereich anlegen')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))

        expect(screen.queryByTitle('Fach bearbeiten')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Thema bearbeiten')).not.toBeInTheDocument()
        expect(screen.getByTitle('Bereich bearbeiten')).toBeInTheDocument()
    })

    it('shows subject/topic/unit as context-only for material scoped shares', async () => {
        const { container } = renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 704,
                    scopeType: 'material',
                    scopeObjectLabel: 'Materialkarte A',
                    scopePathLabel: 'Informatik - Algebra - Lineare Gleichungen',
                    permission: 'read_write',
                    permissionLabel: 'LESEN/SCHREIBEN',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 1,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
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
                                            materials: [
                                                {
                                                    id: 401,
                                                    title: 'Materialkarte A',
                                                    status: 'inbox',
                                                    statusLabel: 'Neu/Idee',
                                                    attachmentsCount: 0,
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        const sharedItem = container.querySelector('.overview-shared-item') as HTMLElement
        const subjectRow = screen.getByText('Informatik').closest('.overview-shared-hierarchy-node') as HTMLElement
        const topicRow = screen.getByText('Algebra').closest('.overview-shared-hierarchy-node') as HTMLElement
        const unitRow = screen.getByText('Lineare Gleichungen').closest('.overview-shared-hierarchy-node') as HTMLElement

        expect(sharedItem).not.toBeNull()
        expect(subjectRow.className).toContain('overview-shared-hierarchy-node--context')
        expect(topicRow.className).toContain('overview-shared-hierarchy-node--context')
        expect(unitRow.className).toContain('overview-shared-hierarchy-node--context')
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))

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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))

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

    it('shows Struktur ändern for empty shared read-write workspace and allows adding the first subject', async () => {
        renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 78,
                    scopeType: 'all',
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace B',
                    permission: 'read_write',
                    permissionLabel: 'LESEN/SCHREIBEN',
                    fromUserLabel: 'Lehrer Zwei',
                    materialsCount: 0,
                    hierarchy: [],
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        expect(screen.getByRole('button', { name: 'Struktur ändern' })).toBeInTheDocument()
        expect(screen.queryByTitle('Fach hinzufügen')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))

        expect(screen.getAllByRole('button', { name: 'Fach hinzufügen' })).toHaveLength(1)
        const sharedItem = screen.getByText('Alle Materialien').closest('.overview-shared-item') as HTMLElement
        expect(within(sharedItem).queryByTitle('Teilen')).toBeNull()
    })

    it('renders shared add-material actions inline next to the node title', async () => {
        renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 79,
                    scopeType: 'all',
                    scopeObjectLabel: 'Alle Materialien',
                    scopePathLabel: 'Workspace C',
                    permission: 'read_write',
                    permissionLabel: 'LESEN/SCHREIBEN',
                    fromUserLabel: 'Lehrer Drei',
                    materialsCount: 0,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
                            materials: [],
                            topics: [
                                {
                                    id: 20,
                                    name: 'Grundlagen',
                                    materials: [],
                                    units: [
                                        {
                                            id: 30,
                                            name: 'Dateien',
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Informatik' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Grundlagen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Dateien' }))

        expect(screen.getByTitle('Neues Material in Bereich anlegen')).toBeInTheDocument()
    })

    it('shows add-material action for shared READ/ADD permission', async () => {
        renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 80,
                    scopeType: 'unit',
                    scopeObjectLabel: 'Dateien',
                    scopePathLabel: 'Informatik - Grundlagen - Dateien',
                    permission: 'read_append',
                    permissionLabel: 'LESEN/HINZUFÜGEN',
                    fromUserLabel: 'Lehrer Vier',
                    materialsCount: 0,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
                            materials: [],
                            topics: [
                                {
                                    id: 20,
                                    name: 'Grundlagen',
                                    materials: [],
                                    units: [
                                        {
                                            id: 30,
                                            name: 'Dateien',
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Informatik' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Grundlagen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Dateien' }))

        expect(screen.getByTitle('Neues Material in Bereich anlegen')).toBeInTheDocument()
    })

    it('shows Einordnen buttons on all shared tree levels and emits insert draft payload', async () => {
        const { emitted } = renderTree([
            {
                id: 1,
                name: 'Lokales Fach',
                materials: [],
                topics: [
                    {
                        id: 11,
                        name: 'Lokales Thema',
                        materials: [],
                        units: [],
                    },
                ],
            },
        ], {
            sharedObjectsForMe: [
                {
                    ruleId: 81,
                    scopeType: 'all',
                    scopeObjectLabel: 'Workspace',
                    scopePathLabel: 'Workspace',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Fuenf',
                    materialsCount: 3,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
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
                                    name: 'Digitale Kompetenzen',
                                    materials: [
                                        {
                                            id: 102,
                                            title: 'Themamaterial',
                                            status: 'inbox',
                                            statusLabel: 'Neu/Idee',
                                            attachmentsCount: 0,
                                        },
                                    ],
                                    units: [
                                        {
                                            id: 30,
                                            name: 'EMails',
                                            materials: [
                                                {
                                                    id: 103,
                                                    title: 'Bereichsmaterial',
                                                    status: 'inbox',
                                                    statusLabel: 'Neu/Idee',
                                                    attachmentsCount: 0,
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await fireEvent.click(screen.getByText('Informatik').closest('button') as HTMLButtonElement)
        await fireEvent.click(screen.getByText('Digitale Kompetenzen').closest('button') as HTMLButtonElement)
        await fireEvent.click(screen.getByText('EMails').closest('button') as HTMLButtonElement)

        const insertButtons = screen.getAllByRole('button', { name: 'Einordnen' })
        expect(insertButtons.length).toBeGreaterThanOrEqual(6)

        await fireEvent.click(insertButtons[0])

        const draftEvents = emitted('open-shared-insert-draft') || []
        expect(draftEvents).toHaveLength(1)
        expect((draftEvents[0]?.[0] as any)?.ruleId).toBe(81)
        expect(['workspace', 'subject', 'topic', 'unit', 'material']).toContain((draftEvents[0]?.[0] as any)?.level)
    })

    it('keeps the shared Einordnen entry point visible even when no local target structure exists', async () => {
        renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 82,
                    scopeType: 'all',
                    scopeObjectLabel: 'Workspace',
                    scopePathLabel: 'Workspace',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Sechs',
                    materialsCount: 1,
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
                            materials: [
                                {
                                    id: 201,
                                    title: 'Fachmaterial',
                                    status: 'inbox',
                                    statusLabel: 'Neu/Idee',
                                    attachmentsCount: 0,
                                },
                            ],
                            topics: [
                                {
                                    id: 20,
                                    name: 'Digitale Kompetenzen',
                                    materials: [],
                                    units: [
                                        {
                                            id: 30,
                                            name: 'EMails',
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        const insertButtons = screen.getAllByRole('button', { name: 'Einordnen' })
        expect(insertButtons.length).toBeGreaterThanOrEqual(1)
    })

    it('opens Einordnen drafts for shared topic unit and material cards', async () => {
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
                        units: [
                            {
                                id: 21,
                                name: 'Lineare Gleichungen',
                                materials: [],
                            },
                        ],
                    },
                ],
            },
        ], {
            sharedObjectsForMe: [
                {
                    ruleId: 83,
                    scopeType: 'topic',
                    scopeId: 210,
                    scopeObjectLabel: 'Geteiltes Thema',
                    scopePathLabel: 'Informatik',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Eins',
                    hierarchy: [],
                },
                {
                    ruleId: 84,
                    scopeType: 'unit',
                    scopeId: 310,
                    scopeObjectLabel: 'Geteilter Bereich',
                    scopePathLabel: 'Informatik / Grundlagen',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Eins',
                    hierarchy: [],
                },
                {
                    ruleId: 85,
                    scopeType: 'material',
                    scopeId: 410,
                    scopeObjectLabel: 'Geteiltes Material',
                    scopePathLabel: 'Informatik / Grundlagen / Bereich A',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Eins',
                    hierarchy: [],
                },
            ],
        })

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))

        const topicCard = screen.getByText('Geteiltes Thema').closest('.overview-shared-item')
        const unitCard = screen.getByText('Geteilter Bereich').closest('.overview-shared-item')
        const materialCard = screen.getByText('Geteiltes Material').closest('.overview-shared-item')

        expect(topicCard).not.toBeNull()
        expect(unitCard).not.toBeNull()
        expect(materialCard).not.toBeNull()

        await fireEvent.click(within(topicCard as HTMLElement).getByRole('button', { name: 'Einordnen' }))
        await fireEvent.click(within(unitCard as HTMLElement).getByRole('button', { name: 'Einordnen' }))
        await fireEvent.click(within(materialCard as HTMLElement).getByRole('button', { name: 'Einordnen' }))

        const draftEvents = emitted('open-shared-insert-draft') || []
        expect(draftEvents).toHaveLength(3)
        expect((draftEvents[0]?.[0] as any)).toMatchObject({
            ruleId: 83,
            level: 'topic',
            targetId: 210,
        })
        expect((draftEvents[1]?.[0] as any)).toMatchObject({
            ruleId: 84,
            level: 'unit',
            targetId: 310,
        })
        expect((draftEvents[2]?.[0] as any)).toMatchObject({
            ruleId: 85,
            level: 'material',
            targetId: 410,
        })
    })

    it('opens shared material Einordnen drafts from subject topic and unit material cards', async () => {
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
                        units: [
                            {
                                id: 21,
                                name: 'Lineare Gleichungen',
                                materials: [],
                            },
                        ],
                    },
                ],
            },
        ], {
            sharedObjectsForMe: [
                {
                    ruleId: 86,
                    scopeType: 'all',
                    scopeObjectLabel: 'Teamraum Informatik',
                    scopePathLabel: 'Teamraum Informatik',
                    permission: 'read_only',
                    permissionLabel: 'NUR LESEN',
                    fromUserLabel: 'Lehrer Zwei',
                    hierarchy: [
                        {
                            id: 10,
                            name: 'Informatik',
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
                                    name: 'Digitale Kompetenzen',
                                    materials: [
                                        {
                                            id: 102,
                                            title: 'Themamaterial',
                                            status: 'inbox',
                                            statusLabel: 'Neu/Idee',
                                            attachmentsCount: 0,
                                        },
                                    ],
                                    units: [
                                        {
                                            id: 30,
                                            name: 'EMails',
                                            materials: [
                                                {
                                                    id: 103,
                                                    title: 'Bereichsmaterial',
                                                    status: 'inbox',
                                                    statusLabel: 'Neu/Idee',
                                                    attachmentsCount: 0,
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await fireEvent.click(screen.getByText('Informatik').closest('button') as HTMLButtonElement)
        await fireEvent.click(screen.getByText('Digitale Kompetenzen').closest('button') as HTMLButtonElement)
        await fireEvent.click(screen.getByText('EMails').closest('button') as HTMLButtonElement)

        const subjectMaterialCard = screen.getByText('Fachmaterial').closest('.overview-material-card')
        const topicMaterialCard = screen.getByText('Themamaterial').closest('.overview-material-card')
        const unitMaterialCard = screen.getByText('Bereichsmaterial').closest('.overview-material-card')

        expect(subjectMaterialCard).not.toBeNull()
        expect(topicMaterialCard).not.toBeNull()
        expect(unitMaterialCard).not.toBeNull()

        await fireEvent.click(within(subjectMaterialCard as HTMLElement).getByRole('button', { name: 'Einordnen' }))
        await fireEvent.click(within(topicMaterialCard as HTMLElement).getByRole('button', { name: 'Einordnen' }))
        await fireEvent.click(within(unitMaterialCard as HTMLElement).getByRole('button', { name: 'Einordnen' }))

        const draftEvents = emitted('open-shared-insert-draft') || []
        expect(draftEvents).toHaveLength(3)
        expect((draftEvents[0]?.[0] as any)).toMatchObject({
            ruleId: 86,
            level: 'material',
            targetId: 101,
        })
        expect((draftEvents[1]?.[0] as any)).toMatchObject({
            ruleId: 86,
            level: 'material',
            targetId: 102,
            sourceTopicId: 20,
        })
        expect((draftEvents[2]?.[0] as any)).toMatchObject({
            ruleId: 86,
            level: 'material',
            targetId: 103,
            sourceTopicId: 20,
            sourceUnitId: 30,
        })
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        expect(screen.getByRole('button', { name: 'Struktur ändern' })).toBeInTheDocument()
        expect(screen.queryByTitle('Fach hinzufügen')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Thema hinzufügen')).not.toBeInTheDocument()
        expect(screen.getByText('Fachmaterial')).toBeInTheDocument()
        expect(screen.getByText('Themamaterial')).toBeInTheDocument()
        expect(screen.getAllByTitle(/Neues Material in/i)).toHaveLength(1)
        expect(screen.queryByTitle('Neues Material in Fach anlegen')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Neues Material in Thema anlegen')).not.toBeInTheDocument()
        const sharedItem = container.querySelector('.overview-shared-item') as HTMLElement
        expect(sharedItem).not.toBeNull()
        expect(within(sharedItem).queryByTitle('Teilen')).toBeNull()
        expect(screen.queryByTitle(/bearbeiten$/i)).not.toBeInTheDocument()
        expect(screen.queryByTitle('Fach löschen')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Thema löschen')).not.toBeInTheDocument()
        expect(screen.queryByTitle('Bereich löschen')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByTitle('Neues Material in Bereich anlegen'))

        const openCreateEvents = emitted('open-create') || []
        expect(openCreateEvents).toHaveLength(1)
        expect((openCreateEvents[0]?.[0] as any)?.sharedRuleId).toBe(93)
        expect((openCreateEvents[0]?.[0] as any)?.sharedNodeLevel).toBe('unit')
        expect((openCreateEvents[0]?.[0] as any)?.sharedNodeId).toBe(30)
        expect((openCreateEvents[0]?.[0] as any)?.subject).toBe('Mathematik')
        expect((openCreateEvents[0]?.[0] as any)?.topic).toBe('Leeres Thema')
        expect((openCreateEvents[0]?.[0] as any)?.unit).toBe('Leerer Bereich')

        await fireEvent.click(screen.getByRole('button', { name: 'Struktur ändern' }))

        expect(screen.getByRole('button', { name: 'Struktur schließen' })).toBeInTheDocument()
        expect(screen.getAllByRole('button', { name: 'Fach hinzufügen' })).toHaveLength(3)
        expect(screen.getAllByRole('button', { name: 'Thema hinzufügen' })).toHaveLength(4)
        expect(screen.getAllByRole('button', { name: 'Bereich hinzufügen' })).toHaveLength(3)
        expect(screen.getAllByTitle('Fach hinzufügen')).toHaveLength(3)
        expect(screen.getAllByTitle('Thema hinzufügen')).toHaveLength(4)
        expect(screen.getAllByTitle('Bereich hinzufügen')).toHaveLength(3)
        expect(screen.queryByText('Fachmaterial')).not.toBeInTheDocument()
        expect(screen.queryByText('Themamaterial')).not.toBeInTheDocument()
        expect(screen.queryByTitle(/Neues Material in/i)).not.toBeInTheDocument()
        expect(within(sharedItem).queryByTitle('Teilen')).toBeNull()
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Leeres Fach' }))
        await fireEvent.click(screen.getByTitle('Fach löschen'))

        const dialog = screen.getByText('Wirklich löschen?').closest('div')
        expect(screen.getByText('Fach löschen')).toBeInTheDocument()
        expect(screen.getAllByText('Leeres Fach')).toHaveLength(3)
        expect(dialog).not.toBeNull()

        await fireEvent.click(screen.getByRole('button', { name: 'Löschen' }))

        expect(axiosMock.delete).toHaveBeenCalledTimes(1)
        expect(axiosMock.delete).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/subjects/11', {
            data: {
                rule_id: 95,
                data: {
                    cascade: false,
                },
            },
        })
        expect(screen.queryByText('Wirklich löschen?')).not.toBeInTheDocument()
    })

    it('shows shared delete buttons on every non-empty level and deletes with cascade under full access', async () => {
        axiosMock.delete.mockResolvedValue({
            data: {},
        })

        renderTree([], {
            sharedObjectsForMe: [
                {
                    ruleId: 96,
                    scopeObjectLabel: 'Workspace A',
                    scopePathLabel: 'Workspace A',
                    permission: 'full_access',
                    permissionLabel: 'VOLLZUGRIFF',
                    fromUserLabel: 'Lehrer Eins',
                    materialsCount: 3,
                    hierarchy: [
                        {
                            id: 12,
                            name: 'Informatik',
                            materials: [
                                { id: 201, title: 'Fachmaterial' },
                            ],
                            topics: [
                                {
                                    id: 22,
                                    name: 'Digitale Kompetenzen',
                                    materials: [
                                        { id: 202, title: 'Themenmaterial' },
                                    ],
                                    units: [
                                        {
                                            id: 32,
                                            name: 'E-Mails',
                                            materials: [
                                                { id: 203, title: 'Unitmaterial' },
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

        await fireEvent.click(screen.getByRole('button', { name: /^für mich geteilt$/i }))
        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Informatik' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Digitale Kompetenzen' }))
        await fireEvent.click(screen.getByRole('button', { name: 'E-Mails' }))

        expect(screen.getByTitle('Fach löschen')).toBeInTheDocument()
        expect(screen.getByTitle('Thema löschen')).toBeInTheDocument()
        expect(screen.getByTitle('Bereich löschen')).toBeInTheDocument()

        await fireEvent.click(screen.getByTitle('Thema löschen'))

        expect(screen.getByText('Thema löschen')).toBeInTheDocument()
        expect(screen.getByText('Dieses Element enthält Unterelemente oder Materialien. Diese werden beim Löschen unwiderruflich mitgelöscht.')).toBeInTheDocument()

        const confirmButton = screen.getByRole('button', { name: 'Löschen' })
        expect(confirmButton).toBeDisabled()

        await fireEvent.click(screen.getByLabelText('Ja, alle enthaltenen Elemente und Materialien ebenfalls löschen'))

        expect(confirmButton).not.toBeDisabled()

        await fireEvent.click(confirmButton)

        expect(axiosMock.delete).toHaveBeenCalledTimes(1)
        expect(axiosMock.delete).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/topics/22', {
            data: {
                rule_id: 96,
                data: {
                    cascade: true,
                },
            },
        })
    })

    it('renders linked permission chip on a linked topic', async () => {
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

        await openWorkspace()

        expect(screen.getByText('Algebra')).toBeInTheDocument()
        expect(screen.getByText('NUR LESEN')).toBeInTheDocument()
    })

    it('renders linked permission chip on a linked unit', async () => {
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

        await openWorkspace()

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

        await openWorkspace()

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

        await openWorkspace()

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

        await openWorkspace()

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

        await openWorkspace()

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
