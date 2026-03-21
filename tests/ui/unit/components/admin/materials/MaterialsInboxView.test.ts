import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/vue'
import MaterialsInboxView from '@/pages/admin/materials/components/views/MaterialsInboxView.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
    },
}))

const vuetifyStubs = {
    'v-card': { template: '<div><slot /></div>' },
    VCard: { template: '<div><slot /></div>' },
    'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
    VDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
    'v-card-title': { template: '<div><slot /></div>' },
    VCardTitle: { template: '<div><slot /></div>' },
    'v-card-text': { template: '<div><slot /></div>' },
    VCardText: { template: '<div><slot /></div>' },
    'v-card-actions': { template: '<div><slot /></div>' },
    VCardActions: { template: '<div><slot /></div>' },
    'v-alert': { template: '<div role="alert"><slot /></div>' },
    VAlert: { template: '<div role="alert"><slot /></div>' },
    'v-list': { template: '<div><slot /></div>' },
    VList: { template: '<div><slot /></div>' },
    'v-list-item': { template: '<div><slot /><slot name="append" /></div>' },
    VListItem: { template: '<div><slot /><slot name="append" /></div>' },
    'v-list-item-title': { template: '<div><slot /></div>' },
    VListItemTitle: { template: '<div><slot /></div>' },
    'v-list-item-subtitle': { template: '<div><slot /></div>' },
    VListItemSubtitle: { template: '<div><slot /></div>' },
    'v-btn': {
        emits: ['click'],
        template: '<button type="button" @click="$emit(\'click\', $event)"><slot /></button>',
    },
    VBtn: {
        emits: ['click'],
        template: '<button type="button" @click="$emit(\'click\', $event)"><slot /></button>',
    },
    'v-chip': { template: '<span><slot /></span>' },
    VChip: { template: '<span><slot /></span>' },
    'v-expansion-panels': { template: '<div><slot /></div>' },
    VExpansionPanels: { template: '<div><slot /></div>' },
    'v-expansion-panel': { template: '<div><slot /></div>' },
    VExpansionPanel: { template: '<div><slot /></div>' },
    'v-expansion-panel-title': { template: '<div><slot /></div>' },
    VExpansionPanelTitle: { template: '<div><slot /></div>' },
    'v-expansion-panel-text': { template: '<div><slot /></div>' },
    VExpansionPanelText: { template: '<div><slot /></div>' },
    'v-icon': { template: '<i><slot /></i>' },
    VIcon: { template: '<i><slot /></i>' },
}

function renderMaterialsInboxView() {
    return render(MaterialsInboxView, {
        global: {
            stubs: vuetifyStubs,
        },
    })
}

describe('MaterialsInboxView', () => {
    const axiosMock = axios as any

    beforeEach(() => {
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()
    })

    it('renders users who shared something with me', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [
                    {
                        id: 11,
                        label: 'Muster Anna',
                        school_label: 'HTL Graz',
                        email: 'anna@test.local',
                        shared_rules_count: 2,
                        shared_items: [
                            {
                                rule_id: 101,
                                scope_label: 'Fach',
                                scope_object_label: 'Mathematik',
                                scope_path_label: 'Mathematik - Algebra - Brueche',
                                permission: 'read_write',
                                permission_label: 'LESEN/SCHREIBEN',
                                hierarchy: [
                                    {
                                        id: 1,
                                        name: 'Mathematik',
                                        topics: [
                                            {
                                                id: 2,
                                                name: 'Algebra',
                                                units: [
                                                    {
                                                        id: 3,
                                                        name: 'Brueche',
                                                        materials: [
                                                            {
                                                                id: 4,
                                                                title: 'Bruchrechnen Blatt',
                                                                icon: 'mdi-file-document-outline',
                                                                type_label: 'Arbeitsblatt',
                                                                type_color: '#1f6f8b',
                                                                attachments_count: 37,
                                                                status: 'in_progress',
                                                                status_label: 'In Arbeit',
                                                                status_color: '#f9a825',
                                                            },
                                                        ],
                                                    },
                                                ],
                                            },
                                        ],
                                    },
                                ],
                                updated_at: '2026-02-26T09:30:00+00:00',
                            },
                        ],
                    },
                    { id: 12, label: 'Beispiel Ben', email: 'ben@test.local', shared_rules_count: 1 },
                ],
                meta: { needs_migration: false },
            },
        })

        renderMaterialsInboxView()

        await waitFor(() => {
            expect(screen.getByText(/Muster Anna/i)).toBeInTheDocument()
        })
        expect(screen.getByText(/HTL Graz/i)).toBeInTheDocument()
        expect(screen.getByText('anna@test.local')).toBeInTheDocument()
        expect(screen.getByText(/Anzeigen, was geteilt wurde/i)).toBeInTheDocument()
        expect(screen.getByText(/^Fach$/i)).toBeInTheDocument()
        expect(screen.getByText(/^Mathematik$/i)).toBeInTheDocument()
        expect(screen.getByText(/Mathematik - Algebra - Brueche/i)).toBeInTheDocument()
        expect(screen.getByText(/LESEN\/SCHREIBEN/i)).toBeInTheDocument()
        expect(screen.getByText(/^Anzeigen$/i)).toBeInTheDocument()
        expect(screen.queryByText('Bruchrechnen Blatt')).not.toBeInTheDocument()
        expect(screen.queryByText('Arbeitsblatt')).not.toBeInTheDocument()
        expect(screen.queryByText('37')).not.toBeInTheDocument()
        expect(screen.queryByText('In Arbeit')).not.toBeInTheDocument()
        expect(screen.getByText('Merken')).toBeInTheDocument()
        expect(screen.getByText('Mehr')).toBeInTheDocument()
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/shares/inbox-users')

        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Schließen' })).toBeInTheDocument()
        })
        expect(screen.getByRole('button', { name: 'Hierarchie schließen' })).toBeInTheDocument()
        expect(screen.getByText('Bruchrechnen Blatt')).toBeInTheDocument()
        expect(screen.getByText('Arbeitsblatt')).toBeInTheDocument()
        expect(screen.getByText('37')).toBeInTheDocument()
        expect(screen.getByText('In Arbeit')).toBeInTheDocument()
    })

    it('renders migration warning when share tables are missing', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [],
                meta: { needs_migration: true },
            },
        })

        renderMaterialsInboxView()

        expect(await screen.findByText(/Freigaben-Tabellen sind noch nicht vorhanden/i)).toBeInTheDocument()
    })

    it('renders empty state when no incoming shares exist', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [],
                meta: { needs_migration: false },
            },
        })

        renderMaterialsInboxView()

        expect(screen.getByText(/eingehenden Freigaben gefunden/i)).toBeInTheDocument()
    })

    it('renders error state when inbox loading fails', async () => {
        axiosMock.get.mockRejectedValue({
            response: { data: { message: 'Inbox kaputt' } },
        })

        renderMaterialsInboxView()

        expect(await screen.findByText('Inbox kaputt')).toBeInTheDocument()
    })

    it('filters inbox entries by new and imported materials', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [
                    {
                        id: 61,
                        label: 'Filter User',
                        school_label: 'BG Test',
                        email: 'filter@test.local',
                        shared_rules_count: 2,
                        shared_items: [
                            {
                                rule_id: 1001,
                                scope_type: 'material',
                                scope_label: 'Material',
                                scope_object_label: 'Neues Material',
                                scope_path_label: 'Mathematik - Thema - Unit',
                                permission: 'read_only',
                                permission_label: 'NUR LESEN',
                                is_imported: false,
                                hierarchy: [],
                                updated_at: '',
                            },
                            {
                                rule_id: 1002,
                                scope_type: 'material',
                                scope_label: 'Material',
                                scope_object_label: 'Eingefächertes Material',
                                scope_path_label: 'Deutsch - Thema - Unit',
                                permission: 'read_only',
                                permission_label: 'NUR LESEN',
                                is_imported: true,
                                hierarchy: [],
                                updated_at: '',
                            },
                        ],
                    },
                ],
                meta: { needs_migration: false },
            },
        })

        renderMaterialsInboxView()

        await waitFor(() => {
            expect(screen.getByText('Neues Material')).toBeInTheDocument()
        })
        expect(screen.getByText('Eingefächertes Material')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Neue Materialien' }))
        expect(screen.getByText('Neues Material')).toBeInTheDocument()
        expect(screen.queryByText('Eingefächertes Material')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Eingefächerte Materialien' }))
        expect(screen.queryByText('Neues Material')).not.toBeInTheDocument()
        expect(screen.getByText('Eingefächertes Material')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Alle' }))
        expect(screen.getByText('Neues Material')).toBeInTheDocument()
        expect(screen.getByText('Eingefächertes Material')).toBeInTheDocument()
    })

    it('archives and restores an item between inbox and Archiv filter', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [
                    {
                        id: 91,
                        label: 'Archiv User',
                        email: 'archiv@test.local',
                        shared_rules_count: 2,
                        shared_items: [
                            {
                                rule_id: 2001,
                                scope_type: 'material',
                                scope_label: 'Material',
                                scope_object_label: 'Archivierbares Material',
                                scope_path_label: 'Mathematik - Thema - Unit',
                                permission: 'read_only',
                                permission_label: 'NUR LESEN',
                                is_imported: false,
                                is_archived: false,
                                hierarchy: [],
                                updated_at: '',
                            },
                            {
                                rule_id: 2002,
                                scope_type: 'material',
                                scope_label: 'Material',
                                scope_object_label: 'Schon im Archiv',
                                scope_path_label: 'Deutsch - Thema - Unit',
                                permission: 'read_only',
                                permission_label: 'NUR LESEN',
                                is_imported: false,
                                is_archived: true,
                                hierarchy: [],
                                updated_at: '',
                            },
                        ],
                    },
                ],
                meta: { needs_migration: false },
            },
        })
        axiosMock.post.mockImplementation((url: string, payload: any) => {
            if (url === '/api/admin/materials/shares/inbox/archive') {
                return Promise.resolve({
                    data: {
                        message: 'Freigabe archiviert.',
                        data: { rule_id: Number(payload?.rule_id || 0), is_archived: true },
                    },
                })
            }
            if (url === '/api/admin/materials/shares/inbox/unarchive') {
                return Promise.resolve({
                    data: {
                        message: 'Freigabe wurde zurück in den Posteingang verschoben.',
                        data: { rule_id: Number(payload?.rule_id || 0), is_archived: false },
                    },
                })
            }
            return Promise.reject(new Error(`Unexpected URL: ${url}`))
        })

        renderMaterialsInboxView()

        await waitFor(() => {
            expect(screen.getByText('Archivierbares Material')).toBeInTheDocument()
        })
        expect(screen.queryByText('Schon im Archiv')).not.toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Archivieren' })).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Archivieren' }))
        await waitFor(() => {
            expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/archive', { rule_id: 2001 })
        })
        expect(screen.queryByText('Archivierbares Material')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Archiv' }))
        expect(screen.getByText('Archivierbares Material')).toBeInTheDocument()
        expect(screen.getByText('Schon im Archiv')).toBeInTheDocument()
        expect(screen.getAllByRole('button', { name: 'Wiederherstellen' }).length).toBe(2)
        expect(screen.queryByRole('button', { name: 'Einfächern' })).not.toBeInTheDocument()

        await fireEvent.click(screen.getAllByRole('button', { name: 'Wiederherstellen' })[0])
        await waitFor(() => {
            expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/unarchive', { rule_id: 2001 })
        })
        expect(screen.queryByText('Archivierbares Material')).not.toBeInTheDocument()
        expect(screen.getByText('Schon im Archiv')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Alle' }))
        expect(screen.getByText('Archivierbares Material')).toBeInTheDocument()
        expect(screen.queryByText('Schon im Archiv')).not.toBeInTheDocument()
    })

    it('supports workspace-level einfächern by copying all shared materials', async () => {
        let inboxUsersCalls = 0
        axiosMock.get.mockImplementation((url: string) => {
            if (url === '/api/admin/materials/shares/inbox-users') {
                inboxUsersCalls += 1
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 41,
                                label: 'Workspace Source',
                                email: 'workspace@test.local',
                                shared_rules_count: 1,
                                shared_items: [
                                    {
                                        rule_id: 777,
                                        scope_type: 'all',
                                        scope_label: 'Workspace',
                                        scope_object_label: 'Gesamter Workspace',
                                        scope_path_label: 'Alle Fächer - Alle Themen - Alle Einheiten',
                                        permission: 'read_only',
                                        permission_label: 'NUR LESEN',
                                        is_imported: false,
                                        hierarchy: [
                                            {
                                                id: 1,
                                                name: 'Mathematik',
                                                topics: [
                                                    {
                                                        id: 2,
                                                        name: 'Algebra',
                                                        units: [
                                                            {
                                                                id: 3,
                                                                name: 'Brüche',
                                                                materials: [
                                                                    { id: 901, title: 'Material A' },
                                                                    { id: 902, title: 'Material B' },
                                                                ],
                                                            },
                                                        ],
                                                    },
                                                ],
                                            },
                                        ],
                                        updated_at: '',
                                    },
                                ],
                            },
                        ],
                        meta: { needs_migration: false },
                    },
                })
            }
            return Promise.reject(new Error('unexpected url'))
        })
        axiosMock.post.mockResolvedValue({
            data: {
                message: 'Material als Original eingefügt.',
                data: { id: 123, title: 'Imported', attachments_count: 0 },
            },
        })

        renderMaterialsInboxView()

        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Einfächern' })).toBeInTheDocument()
        })
        expect(screen.getByRole('button', { name: 'Anzeigen' })).toBeInTheDocument()
        const actionButtons = screen.getAllByRole('button')
            .filter((button) => ['Anzeigen', 'Einfächern'].includes((button.textContent || '').trim()))
        expect(actionButtons[0]).toHaveTextContent('Anzeigen')
        expect(actionButtons[1]).toHaveTextContent('Einfächern')
        expect(screen.queryByText('Merken')).not.toBeInTheDocument()
        expect(screen.queryByText('Mehr')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Einfächern' }))

        await waitFor(() => {
            expect(axiosMock.post).toHaveBeenCalledTimes(2)
        })
        expect(axiosMock.post).toHaveBeenNthCalledWith(1, '/api/admin/materials/shares/inbox/material-original-copy', {
            rule_id: 777,
            material_id: 901,
        })
        expect(axiosMock.post).toHaveBeenNthCalledWith(2, '/api/admin/materials/shares/inbox/material-original-copy', {
            rule_id: 777,
            material_id: 902,
        })
        await waitFor(() => {
            expect(inboxUsersCalls).toBe(2)
        })
    })

    it('does not render hierarchy card toggle for material scope items', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                message: 'Material eingefächert.',
                data: { id: 88, title: 'Arbeitsblatt 1', attachments_count: 2 },
            },
        })

        axiosMock.get.mockImplementation((url: string) => {
            if (url === '/api/admin/materials/shares/inbox-users') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 21,
                                label: 'Muster Max',
                                email: 'max@test.local',
                                shared_rules_count: 1,
                                shared_items: [
                                    {
                                        rule_id: 501,
                                        scope_type: 'material',
                                        scope_label: 'Material',
                                        scope_object_label: 'Arbeitsblatt 1',
                                        scope_path_label: 'Mathematik - Algebra - Brueche',
                                        permission: 'read_only',
                                        permission_label: 'NUR LESEN',
                                        is_imported: true,
                                        hierarchy: [
                                            {
                                                id: 1,
                                                name: 'Mathematik',
                                                topics: [
                                                    {
                                                        id: 2,
                                                        name: 'Algebra',
                                                        units: [
                                                            {
                                                                id: 3,
                                                                name: 'Brueche',
                                                                materials: [
                                                                    {
                                                                        id: 501,
                                                                        title: 'Arbeitsblatt 1',
                                                                        icon: 'mdi-link-variant',
                                                                        type_label: 'Link',
                                                                        type_color: '#1f6f8b',
                                                                        attachments_count: 2,
                                                                        status: 'done',
                                                                        status_label: 'ok',
                                                                        status_color: '#2e7d32',
                                                                    },
                                                                ],
                                                            },
                                                        ],
                                                    },
                                                ],
                                            },
                                        ],
                                        updated_at: '2026-02-26T09:30:00+00:00',
                                    },
                                ],
                            },
                        ],
                        meta: { needs_migration: false },
                    },
                })
            }
            if (url === '/api/admin/materials/config') {
                return Promise.resolve({
                    data: {
                        classification_tree: [
                            {
                                id: 11,
                                name: 'Mathematik',
                                topics: [
                                    {
                                        id: 12,
                                        name: 'Algebra',
                                        units: [
                                            { id: 13, name: 'Brueche' },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                })
            }
            return Promise.reject(new Error('unexpected url'))
        })

        renderMaterialsInboxView()

        await waitFor(() => {
            expect(screen.getByText('Arbeitsblatt 1')).toBeInTheDocument()
        })

        expect(screen.queryByRole('button', { name: 'Anzeigen' })).not.toBeInTheDocument()
        expect(screen.queryByText('Merken')).not.toBeInTheDocument()
        expect(screen.queryByText('Mehr')).not.toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Einfächern' })).toBeInTheDocument()
        expect(screen.getByText('Link')).toBeInTheDocument()
        expect(screen.getByText('2')).toBeInTheDocument()
        expect(screen.getByText('ok')).toBeInTheDocument()
        expect(screen.getByText('Eingefächert')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Einfächern' }))

        await waitFor(() => {
            expect(screen.getByText('Fächer')).toBeInTheDocument()
        })
        expect(screen.getByText('Themen')).toBeInTheDocument()
        expect(screen.getByText('Einheiten')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Mathematik' }))
        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Als Kopie einfächern' })).toBeInTheDocument()
        })
        expect(screen.getByRole('button', { name: 'Als Link einfächern' })).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Algebra' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Brueche' }))
        expect(screen.getByRole('button', { name: 'Als Kopie einfächern' })).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Als Kopie einfächern' }))
        await waitFor(() => {
            expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/material-insert', {
                rule_id: 501,
                material_id: 501,
                target_level: 'unit',
                target_id: 13,
                import_mode: 'copy',
            })
        })
    })

    it('shows original option in einfächern dialog when no subjects exist', async () => {
        axiosMock.get.mockImplementation((url: string) => {
            if (url === '/api/admin/materials/shares/inbox-users') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 41,
                                label: 'No Subject User',
                                email: 'nosubject@test.local',
                                shared_rules_count: 1,
                                shared_items: [
                                    {
                                        rule_id: 901,
                                        scope_type: 'material',
                                        scope_label: 'Material',
                                        scope_object_label: 'Dokument A',
                                        scope_path_label: 'Pfad',
                                        permission: 'read_only',
                                        permission_label: 'NUR LESEN',
                                        hierarchy: [
                                            {
                                                id: 1,
                                                name: 'Fach',
                                                topics: [{ id: 2, name: 'Thema', units: [{ id: 3, name: 'Unit', materials: [{ id: 7, title: 'Dokument A' }] }] }],
                                            },
                                        ],
                                        updated_at: '',
                                    },
                                ],
                            },
                        ],
                        meta: { needs_migration: false },
                    },
                })
            }
            if (url === '/api/admin/materials/config') {
                return Promise.resolve({ data: { classification_tree: [] } })
            }
            return Promise.reject(new Error('unexpected url'))
        })
        axiosMock.post.mockResolvedValue({
            data: {
                message: 'Material als Original eingefügt.',
                data: { id: 77, title: 'Dokument A', attachments_count: 0 },
            },
        })

        renderMaterialsInboxView()

        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Einfächern' })).toBeInTheDocument()
        })

        await fireEvent.click(screen.getByRole('button', { name: 'Einfächern' }))

        await waitFor(() => {
            expect(screen.getByText(/Es sind noch keine Fächer vorhanden/i)).toBeInTheDocument()
        })
        expect(screen.getByRole('button', { name: 'Als Original einfügen' })).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Als Original einfügen' }))
        await waitFor(() => {
            expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/material-original-copy', {
                rule_id: 901,
                material_id: 7,
            })
        })
    })

    it('supports unit-level einfächern for all materials inside the selected unit', async () => {
        axiosMock.get.mockImplementation((url: string) => {
            if (url === '/api/admin/materials/shares/inbox-users') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 52,
                                label: 'Unit Source',
                                email: 'unit@test.local',
                                shared_rules_count: 1,
                                shared_items: [
                                    {
                                        rule_id: 920,
                                        scope_type: 'unit',
                                        scope_label: 'Einheit',
                                        scope_object_label: 'Kapitel 1',
                                        scope_path_label: 'Mathematik - Algebra - Kapitel 1',
                                        permission: 'read_only',
                                        permission_label: 'NUR LESEN',
                                        hierarchy: [
                                            {
                                                id: 1,
                                                name: 'Mathematik',
                                                topics: [
                                                    {
                                                        id: 2,
                                                        name: 'Algebra',
                                                        units: [
                                                            {
                                                                id: 3,
                                                                name: 'Kapitel 1',
                                                                materials: [
                                                                    { id: 701, title: 'Material A' },
                                                                    { id: 702, title: 'Material B' },
                                                                ],
                                                            },
                                                        ],
                                                    },
                                                ],
                                            },
                                        ],
                                        updated_at: '',
                                    },
                                ],
                            },
                        ],
                        meta: { needs_migration: false },
                    },
                })
            }
            if (url === '/api/admin/materials/config') {
                return Promise.resolve({
                    data: {
                        classification_tree: [
                            {
                                id: 21,
                                name: 'Deutsch',
                                topics: [
                                    { id: 22, name: 'Literatur', units: [{ id: 23, name: 'Kapitel 1' }] },
                                ],
                            },
                        ],
                    },
                })
            }
            return Promise.reject(new Error('unexpected url'))
        })
        axiosMock.post.mockImplementation((url: string) => {
            if (url === '/api/admin/materials/units') {
                return Promise.resolve({
                    data: {
                        data: { id: 230, topic_id: 22, name: 'Kapitel 1' },
                    },
                })
            }
            if (url === '/api/admin/materials/shares/inbox/material-insert') {
                return Promise.resolve({
                    data: {
                        message: 'Material eingefächert.',
                        data: { id: 4001, title: 'Neu', attachments_count: 0 },
                    },
                })
            }
            return Promise.reject(new Error('unexpected post url'))
        })

        renderMaterialsInboxView()

        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Anzeigen' })).toBeInTheDocument()
        })

        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await waitFor(() => {
            expect(screen.getByText('Material A')).toBeInTheDocument()
        })

        const einfButtons = screen.getAllByRole('button', { name: 'Einfächern' })
        expect(einfButtons.length).toBeGreaterThanOrEqual(3)

        const unitHead = document.querySelector('.inbox-hierarchy-unit-head')
        expect(unitHead).not.toBeNull()
        await fireEvent.click(within(unitHead as HTMLElement).getByRole('button', { name: 'Einfächern' }))

        await waitFor(() => {
            expect(screen.getByText('Fächer')).toBeInTheDocument()
        })
        expect(screen.queryByText('Einheiten')).not.toBeInTheDocument()
        expect(screen.getByText('Einheit: Kapitel 1')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Deutsch' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Literatur' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Als Kopie einfächern' }))
        await waitFor(() => {
            const calls = axiosMock.post.mock.calls as Array<[string, Record<string, any>]>
            expect(calls.some(([url, payload]) =>
                url === '/api/admin/materials/units'
                && payload?.data?.topic_id === 22
                && payload?.data?.name === 'Kapitel 1'
                && payload?.data?.allow_duplicate === true
            )).toBe(true)
        })
        await waitFor(() => {
            const insertCalls = (axiosMock.post.mock.calls as Array<[string, Record<string, any>]>)
                .filter(([url]) => url === '/api/admin/materials/shares/inbox/material-insert')
                .map(([, payload]) => payload)
            expect(insertCalls).toHaveLength(2)
            expect(insertCalls.map((payload) => Number(payload?.material_id || 0)).sort((a, b) => a - b)).toEqual([701, 702])
        })
    })

    it('supports unit-level einfächern as link and forwards link import mode', async () => {
        axiosMock.get.mockImplementation((url: string) => {
            if (url === '/api/admin/materials/shares/inbox-users') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 53,
                                label: 'Unit Source',
                                email: 'unit-link@test.local',
                                shared_rules_count: 1,
                                shared_items: [
                                    {
                                        rule_id: 930,
                                        scope_type: 'unit',
                                        scope_label: 'Einheit',
                                        scope_object_label: 'Kapitel 1',
                                        scope_path_label: 'Mathematik - Algebra - Kapitel 1',
                                        permission: 'read_only',
                                        permission_label: 'NUR LESEN',
                                        hierarchy: [
                                            {
                                                id: 1,
                                                name: 'Mathematik',
                                                topics: [
                                                    {
                                                        id: 2,
                                                        name: 'Algebra',
                                                        units: [
                                                            {
                                                                id: 3,
                                                                name: 'Kapitel 1',
                                                                materials: [{ id: 801, title: 'Material Link' }],
                                                            },
                                                        ],
                                                    },
                                                ],
                                            },
                                        ],
                                        updated_at: '',
                                    },
                                ],
                            },
                        ],
                        meta: { needs_migration: false },
                    },
                })
            }
            if (url === '/api/admin/materials/config') {
                return Promise.resolve({
                    data: {
                        classification_tree: [
                            {
                                id: 31,
                                name: 'Deutsch',
                                topics: [{ id: 32, name: 'Literatur', units: [] }],
                            },
                        ],
                    },
                })
            }
            return Promise.reject(new Error('unexpected url'))
        })
        axiosMock.post.mockImplementation((url: string) => {
            if (url === '/api/admin/materials/units') {
                return Promise.resolve({
                    data: {
                        data: { id: 330, topic_id: 32, name: 'Kapitel 1' },
                    },
                })
            }
            if (url === '/api/admin/materials/shares/inbox/material-insert') {
                return Promise.resolve({
                    data: {
                        message: 'Material als Link eingefächert.',
                        data: { id: 5001, title: 'Material Link', attachments_count: 0 },
                    },
                })
            }
            return Promise.reject(new Error('unexpected post url'))
        })

        renderMaterialsInboxView()

        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Anzeigen' })).toBeInTheDocument()
        })

        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await waitFor(() => {
            expect(screen.getByText('Material Link')).toBeInTheDocument()
        })

        const unitHead = document.querySelector('.inbox-hierarchy-unit-head')
        expect(unitHead).not.toBeNull()
        await fireEvent.click(within(unitHead as HTMLElement).getByRole('button', { name: 'Einfächern' }))

        await waitFor(() => {
            expect(screen.getByText('Fächer')).toBeInTheDocument()
        })

        await fireEvent.click(screen.getByRole('button', { name: 'Deutsch' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Literatur' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Als Link einfächern' }))
        await waitFor(() => {
            expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/units', {
                data: {
                    topic_id: 32,
                    name: 'Kapitel 1',
                    allow_duplicate: true,
                },
            })
        })
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/material-insert', {
            rule_id: 930,
            material_id: 801,
            target_level: 'unit',
            target_id: 330,
            import_mode: 'link',
            source_unit_id: 3,
        })
    })

    it('keeps opened hierarchy cards open after einfächern reloads the inbox', async () => {
        let inboxUsersCalls = 0
        axiosMock.get.mockImplementation((url: string) => {
            if (url === '/api/admin/materials/shares/inbox-users') {
                inboxUsersCalls += 1
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 71,
                                label: 'Reload User',
                                email: 'reload@test.local',
                                shared_rules_count: 1,
                                shared_items: [
                                    {
                                        rule_id: 955,
                                        scope_type: 'unit',
                                        scope_label: 'Einheit',
                                        scope_object_label: 'Kapitel A',
                                        scope_path_label: 'Informatik - Grundlagen - Kapitel A',
                                        permission: 'read_only',
                                        permission_label: 'NUR LESEN',
                                        hierarchy: [
                                            {
                                                id: 41,
                                                name: 'Informatik',
                                                topics: [
                                                    {
                                                        id: 42,
                                                        name: 'Grundlagen',
                                                        units: [
                                                            {
                                                                id: 43,
                                                                name: 'Kapitel A',
                                                                materials: [{ id: 901, title: 'Material Reload' }],
                                                            },
                                                        ],
                                                    },
                                                ],
                                            },
                                        ],
                                        updated_at: '',
                                    },
                                ],
                            },
                        ],
                        meta: { needs_migration: false },
                    },
                })
            }
            if (url === '/api/admin/materials/config') {
                return Promise.resolve({
                    data: {
                        classification_tree: [
                            {
                                id: 51,
                                name: 'Deutsch',
                                topics: [
                                    {
                                        id: 52,
                                        name: 'Literatur',
                                        units: [{ id: 53, name: 'Kapitel A' }],
                                    },
                                ],
                            },
                        ],
                    },
                })
            }
            return Promise.reject(new Error('unexpected url'))
        })
        axiosMock.post.mockResolvedValue({
            data: {
                message: 'Material eingefächert.',
                data: { id: 9901, title: 'Material Reload', attachments_count: 0 },
            },
        })

        renderMaterialsInboxView()

        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Anzeigen' })).toBeInTheDocument()
        })

        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Schließen' })).toBeInTheDocument()
        })
        expect(screen.getByText('Material Reload')).toBeInTheDocument()

        const fanoutButtons = screen.getAllByRole('button', { name: 'Einfächern' })
        await fireEvent.click(fanoutButtons[fanoutButtons.length - 1])

        await waitFor(() => {
            expect(screen.getByText('Fächer')).toBeInTheDocument()
        })

        await fireEvent.click(screen.getByRole('button', { name: 'Deutsch' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Literatur' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Kapitel A' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Als Kopie einfächern' }))
        await waitFor(() => {
            expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/material-insert', {
                rule_id: 955,
                material_id: 901,
                target_level: 'unit',
                target_id: 53,
                import_mode: 'copy',
            })
        })
        await waitFor(() => {
            expect(inboxUsersCalls).toBe(2)
        })
        await waitFor(() => {
            expect(screen.getAllByRole('button', { name: 'Schließen' }).length).toBeGreaterThan(0)
        })
        expect(screen.queryByRole('button', { name: 'Anzeigen' })).not.toBeInTheDocument()
        expect(screen.getAllByText('Material Reload').length).toBeGreaterThan(0)
    })

    it('supports topic-level bulk einfächern and forwards source_topic_id for link mode', async () => {
        axiosMock.get.mockImplementation((url: string) => {
            if (url === '/api/admin/materials/shares/inbox-users') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 81,
                                label: 'Topic Source',
                                email: 'topic-bulk@test.local',
                                shared_rules_count: 1,
                                shared_items: [
                                    {
                                        rule_id: 980,
                                        scope_type: 'topic',
                                        scope_label: 'Thema',
                                        scope_object_label: 'Algebra',
                                        scope_path_label: 'Mathematik - Algebra - Alle Einheiten',
                                        permission: 'read_write',
                                        permission_label: 'LESEN/SCHREIBEN',
                                        hierarchy: [
                                            {
                                                id: 11,
                                                name: 'Mathematik',
                                                topics: [
                                                    {
                                                        id: 2,
                                                        name: 'Algebra',
                                                        units: [
                                                            {
                                                                id: 301,
                                                                name: 'Kapitel 1',
                                                                materials: [
                                                                    { id: 701, title: 'Material A' },
                                                                    { id: 702, title: 'Material B' },
                                                                ],
                                                            },
                                                            {
                                                                id: 0,
                                                                name: 'Ohne Einheit',
                                                                materials: [{ id: 703, title: 'Direkt am Topic' }],
                                                            },
                                                        ],
                                                    },
                                                ],
                                            },
                                        ],
                                        updated_at: '',
                                    },
                                ],
                            },
                        ],
                        meta: { needs_migration: false },
                    },
                })
            }
            if (url === '/api/admin/materials/config') {
                return Promise.resolve({
                    data: {
                        classification_tree: [
                            {
                                id: 21,
                                name: 'Deutsch',
                                topics: [],
                            },
                        ],
                    },
                })
            }
            return Promise.reject(new Error('unexpected url'))
        })

        axiosMock.post.mockImplementation((url: string) => {
            if (url === '/api/admin/materials/topics') {
                return Promise.resolve({
                    data: {
                        data: { id: 220, subject_id: 21, name: 'Algebra' },
                    },
                })
            }
            if (url === '/api/admin/materials/units') {
                return Promise.resolve({
                    data: {
                        data: { id: 230, topic_id: 220, name: 'Kapitel 1' },
                    },
                })
            }
            if (url === '/api/admin/materials/shares/inbox/material-insert') {
                return Promise.resolve({
                    data: {
                        message: 'Material als Link eingefächert.',
                        data: { id: 9100, title: 'Neu', attachments_count: 0 },
                    },
                })
            }
            return Promise.reject(new Error('unexpected post url'))
        })

        renderMaterialsInboxView()

        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Anzeigen' })).toBeInTheDocument()
        })

        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await waitFor(() => {
            expect(screen.getByText('Material A')).toBeInTheDocument()
        })

        const topicHead = document.querySelector('.inbox-hierarchy-unit-head.inbox-hierarchy-level-topic')
        expect(topicHead).not.toBeNull()
        await fireEvent.click(within(topicHead as HTMLElement).getByRole('button', { name: 'Einfächern' }))
        await waitFor(() => {
            expect(screen.getByText('Thema einordnen')).toBeInTheDocument()
        })
        expect(
            screen.getByText((_, element) =>
                String(element?.tagName || '').toLowerCase() === 'p'
                && String(element?.textContent || '').includes('Soll das Thema')
            )
        ).toBeInTheDocument()
        expect(screen.getByText('Wähle das Zielfach im Workspace. Das legt fest, wohin das Thema eingeordnet wird.')).toBeInTheDocument()
        expect(screen.getByText('Zielfach im Workspace')).toBeInTheDocument()
        expect(screen.queryByText('Themen')).not.toBeInTheDocument()
        expect(screen.queryByText('Einheiten')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Deutsch' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Als Link einfächern' }))
        await waitFor(() => {
            const calls = axiosMock.post.mock.calls as Array<[string, Record<string, any>]>
            expect(calls.some(([url, payload]) =>
                url === '/api/admin/materials/units'
                && payload?.data?.topic_id === 220
                && payload?.data?.name === 'Kapitel 1'
                && payload?.data?.allow_duplicate === true
            )).toBe(true)

            const insertCalls = calls
                .filter(([url]) => url === '/api/admin/materials/shares/inbox/material-insert')
                .map(([, payload]) => payload)
            expect(insertCalls).toHaveLength(3)
            expect(insertCalls.some((payload) =>
                Number(payload?.material_id || 0) === 701
                && String(payload?.import_mode || '') === 'link'
                && Number(payload?.source_topic_id || 0) === 2
                && Number(payload?.source_unit_id || 0) === 301
            )).toBe(true)
            expect(insertCalls.some((payload) =>
                Number(payload?.material_id || 0) === 702
                && String(payload?.import_mode || '') === 'link'
                && Number(payload?.source_topic_id || 0) === 2
                && Number(payload?.source_unit_id || 0) === 301
            )).toBe(true)
            expect(insertCalls.some((payload) =>
                Number(payload?.material_id || 0) === 703
                && String(payload?.import_mode || '') === 'link'
                && Number(payload?.source_topic_id || 0) === 2
                && Number(payload?.target_id || 0) === 220
                && String(payload?.target_level || '') === 'topic'
            )).toBe(true)
        })
    })

    it('opens the topic einordnen dialog from the standard subject hierarchy topic button', async () => {
        axiosMock.get.mockImplementation((url: string) => {
            if (url === '/api/admin/materials/shares/inbox-users') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 91,
                                label: 'Hierarchy Source',
                                email: 'hierarchy-topic@test.local',
                                shared_rules_count: 1,
                                shared_items: [
                                    {
                                        rule_id: 981,
                                        scope_type: 'subject',
                                        scope_label: 'Fach',
                                        scope_object_label: 'Medienbildung',
                                        scope_path_label: 'Medienbildung - Digitale Kompetenzen',
                                        permission: 'read_write',
                                        permission_label: 'LESEN/SCHREIBEN',
                                        hierarchy: [
                                            {
                                                id: 51,
                                                name: 'Medienbildung',
                                                topics: [
                                                    {
                                                        id: 52,
                                                        name: 'Digitale Kompetenzen',
                                                        units: [
                                                            {
                                                                id: 0,
                                                                name: 'Ohne Einheit',
                                                                materials: [{ id: 801, title: 'Material X' }],
                                                            },
                                                        ],
                                                    },
                                                ],
                                            },
                                        ],
                                        updated_at: '',
                                    },
                                ],
                            },
                        ],
                        meta: { needs_migration: false },
                    },
                })
            }
            if (url === '/api/admin/materials/config') {
                return Promise.resolve({
                    data: {
                        classification_tree: [
                            {
                                id: 61,
                                name: 'Informatik',
                                topics: [],
                            },
                        ],
                    },
                })
            }
            return Promise.reject(new Error('unexpected url'))
        })

        renderMaterialsInboxView()

        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Anzeigen' })).toBeInTheDocument()
        })

        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))
        await waitFor(() => {
            expect(screen.getByText('Digitale Kompetenzen')).toBeInTheDocument()
        })

        const hierarchyTopicButton = screen.getAllByRole('button', { name: 'Einfächern' })[1]
        await fireEvent.click(hierarchyTopicButton)

        await waitFor(() => {
            expect(screen.getByText('Thema einordnen')).toBeInTheDocument()
        })
        expect(screen.getByText('Zielfach im Workspace')).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Informatik' })).toBeInTheDocument()
    })

    it('topic scope shows subject line and omits "Ohne Einheit" for direct topic materials', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [
                    {
                        id: 31,
                        label: 'Topic User',
                        email: 'topic@test.local',
                        shared_rules_count: 1,
                        shared_items: [
                            {
                                rule_id: 601,
                                scope_type: 'topic',
                                scope_label: 'Thema',
                                scope_object_label: 'Algebra',
                                scope_path_label: 'X - Algebra - Alle Einheiten',
                                permission: 'read_only',
                                permission_label: 'NUR LESEN',
                                hierarchy: [
                                    {
                                        id: 1,
                                        name: 'SubjectHiddenOnly',
                                        topics: [
                                            {
                                                id: 2,
                                                name: 'Algebra',
                                                units: [
                                                    {
                                                        id: 0,
                                                        name: 'Ohne Einheit',
                                                        materials: [
                                                            { id: 44, title: 'Direkt am Topic' },
                                                        ],
                                                    },
                                                ],
                                            },
                                        ],
                                    },
                                ],
                                updated_at: '',
                            },
                        ],
                    },
                ],
                meta: { needs_migration: false },
            },
        })

        renderMaterialsInboxView()

        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Anzeigen' })).toBeInTheDocument()
        })

        expect(screen.queryByText('Direkt am Topic')).not.toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        await waitFor(() => {
            expect(screen.getByText('Direkt am Topic')).toBeInTheDocument()
        })

        expect(screen.getByText('SubjectHiddenOnly')).toBeInTheDocument()
        expect(screen.queryByText('Ohne Einheit')).not.toBeInTheDocument()
    })
})
