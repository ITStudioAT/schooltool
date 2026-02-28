import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/vue'
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
    'v-btn': { template: '<button type="button" @click="$emit(\'click\')"><slot /></button>' },
    VBtn: { template: '<button type="button" @click="$emit(\'click\')"><slot /></button>' },
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
            expect(screen.getByText('Muster Anna · HTL Graz')).toBeInTheDocument()
        })
        expect(screen.getByText('anna@test.local')).toBeInTheDocument()
        expect(screen.getByText(/Anzeigen, was geteilt wurde/i)).toBeInTheDocument()
        expect(screen.getByText(/^Fach$/i)).toBeInTheDocument()
        expect(screen.getByText(/Mathematik/i)).toBeInTheDocument()
        expect(screen.getByText(/Mathematik - Algebra - Brueche/i)).toBeInTheDocument()
        expect(screen.getByText(/LESEN\/SCHREIBEN/i)).toBeInTheDocument()
        expect(screen.getByText(/^Anzeigen$/i)).toBeInTheDocument()
        expect(screen.queryByText('Bruchrechnen Blatt')).not.toBeInTheDocument()
        expect(screen.queryByText('Arbeitsblatt')).not.toBeInTheDocument()
        expect(screen.queryByText('37')).not.toBeInTheDocument()
        expect(screen.queryByText('In Arbeit')).not.toBeInTheDocument()
        expect(screen.getByText('Merken')).toBeInTheDocument()
        expect(screen.getByText('Mehr')).toBeInTheDocument()
        expect(screen.getByText('2 Freigaben')).toBeInTheDocument()
        expect(screen.getByText('1 Freigabe')).toBeInTheDocument()
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/shares/inbox-users')

        await fireEvent.click(screen.getByRole('button', { name: 'Anzeigen' }))

        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Schließen' })).toBeInTheDocument()
        })
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

        expect(await screen.findByText(/Noch keine eingehenden Freigaben gefunden/i)).toBeInTheDocument()
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
        expect(einfButtons.length).toBe(3)

        await fireEvent.click(einfButtons[0])

        await waitFor(() => {
            expect(screen.getByText('Fächer')).toBeInTheDocument()
        })
        expect(screen.queryByText('Einheiten')).not.toBeInTheDocument()
        expect(screen.getByText('Einheit: Kapitel 1')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Deutsch' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Literatur' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Als Kopie einfächern' }))

        await waitFor(() => {
            expect(axiosMock.post).toHaveBeenNthCalledWith(1, '/api/admin/materials/units', {
                data: {
                    topic_id: 22,
                    name: 'Kapitel 1',
                    allow_duplicate: true,
                },
            })
        })
        expect(axiosMock.post).toHaveBeenNthCalledWith(2, '/api/admin/materials/shares/inbox/material-insert', {
            rule_id: 920,
            material_id: 701,
            target_level: 'unit',
            target_id: 230,
            import_mode: 'copy',
            source_unit_id: 3,
        })
        expect(axiosMock.post).toHaveBeenNthCalledWith(3, '/api/admin/materials/shares/inbox/material-insert', {
                rule_id: 920,
                material_id: 702,
                target_level: 'unit',
                target_id: 230,
                import_mode: 'copy',
                source_unit_id: 3,
            })
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

        await fireEvent.click(screen.getAllByRole('button', { name: 'Einfächern' })[0])

        await waitFor(() => {
            expect(screen.getByText('Fächer')).toBeInTheDocument()
        })

        await fireEvent.click(screen.getByRole('button', { name: 'Deutsch' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Literatur' }))
        await fireEvent.click(screen.getByRole('button', { name: 'Als Link einfächern' }))

        await waitFor(() => {
            expect(axiosMock.post).toHaveBeenNthCalledWith(1, '/api/admin/materials/units', {
                data: {
                    topic_id: 32,
                    name: 'Kapitel 1',
                    allow_duplicate: true,
                },
            })
        })
        expect(axiosMock.post).toHaveBeenNthCalledWith(2, '/api/admin/materials/shares/inbox/material-insert', {
            rule_id: 930,
            material_id: 801,
            target_level: 'unit',
            target_id: 330,
            import_mode: 'link',
            source_unit_id: 3,
        })
    })

    it('topic scope hides subject level and omits "Ohne Einheit" for direct topic materials', async () => {
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

        expect(screen.queryByText('SubjectHiddenOnly')).not.toBeInTheDocument()
        expect(screen.queryByText('Ohne Einheit')).not.toBeInTheDocument()
    })
})
