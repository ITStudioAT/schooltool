import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/vue'
import MaterialsInboxView from '@/pages/admin/materials/components/views/MaterialsInboxView.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
    },
}))

const vuetifyStubs = {
    'v-card': { template: '<div><slot /></div>' },
    VCard: { template: '<div><slot /></div>' },
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

    it('does not render hierarchy card toggle for material scope items', async () => {
        axiosMock.get.mockResolvedValue({
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
                                hierarchy: [],
                                updated_at: '2026-02-26T09:30:00+00:00',
                            },
                        ],
                    },
                ],
                meta: { needs_migration: false },
            },
        })

        renderMaterialsInboxView()

        await waitFor(() => {
            expect(screen.getByText('Arbeitsblatt 1')).toBeInTheDocument()
        })

        expect(screen.queryByRole('button', { name: 'Anzeigen' })).not.toBeInTheDocument()
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
