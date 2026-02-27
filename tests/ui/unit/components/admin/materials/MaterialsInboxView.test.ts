import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import { render, screen, waitFor } from '@testing-library/vue'
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
        expect(screen.getByText('Öffnen')).toBeInTheDocument()
        expect(screen.getByText('Merken')).toBeInTheDocument()
        expect(screen.getByText('Mehr')).toBeInTheDocument()
        expect(screen.getByText('2 Freigaben')).toBeInTheDocument()
        expect(screen.getByText('1 Freigabe')).toBeInTheDocument()
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/shares/inbox-users')
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
})
