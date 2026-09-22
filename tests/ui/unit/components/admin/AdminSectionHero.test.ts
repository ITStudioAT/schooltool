import { createTestingPinia } from '@pinia/testing'
import { render, screen } from '@testing-library/vue'
import { describe, expect, it } from 'vitest'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'

const vuetifyStubs = {
    'v-sheet': { template: '<div><slot /></div>' },
    VSheet: { template: '<div><slot /></div>' },
    'v-row': { template: '<div><slot /></div>' },
    VRow: { template: '<div><slot /></div>' },
    'v-col': { template: '<div><slot /></div>' },
    VCol: { template: '<div><slot /></div>' },
    'v-card': { template: '<div><slot /></div>' },
    VCard: { template: '<div><slot /></div>' },
    'v-card-text': { template: '<div><slot /></div>' },
    VCardText: { template: '<div><slot /></div>' },
    'v-icon': { template: '<i><slot /></i>' },
    VIcon: { template: '<i><slot /></i>' },
    'v-chip': { template: '<span><slot /></span>' },
    VChip: { template: '<span><slot /></span>' },
}

describe('AdminSectionHero', () => {
    it('renders user chip and visible chips from props', () => {
        render(AdminSectionHero, {
            props: {
                eyebrow: 'Unterricht',
                title: 'Lehrbereich',
                activeSection: {
                    icon: 'mdi-home',
                    label: 'Uebersicht',
                    note: 'Aktuelle Ansicht',
                },
                chips: [
                    { key: 'school', text: 'Schule A', icon: 'mdi-domain' },
                    { key: 'hidden', text: 'Nicht sichtbar', visible: false },
                    { key: 'empty', text: '   ' },
                ],
                showCurrentUserChip: true,
            },
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                config: {
                                    user: {
                                        first_name: 'Guenther',
                                        last_name: 'Kron',
                                        email: 'guenther.kron@cdgym.at',
                                    },
                                },
                            },
                        },
                    }),
                ],
                stubs: vuetifyStubs,
            },
        })

        expect(screen.getByText('Benutzer: Guenther Kron - guenther.kron@cdgym.at')).toBeInTheDocument()
        expect(screen.getByText('Schule A')).toBeInTheDocument()
        expect(screen.queryByText('Nicht sichtbar')).not.toBeInTheDocument()
        expect(screen.getByText('Uebersicht')).toBeInTheDocument()
    })

    it('hides the user chip when disabled', () => {
        render(AdminSectionHero, {
            props: {
                eyebrow: 'Verwaltung',
                title: 'Benutzerverwaltung',
                activeSection: {
                    icon: 'mdi-home',
                    label: 'Uebersicht',
                    note: 'Aktuelle Ansicht',
                },
                chips: [],
                showCurrentUserChip: false,
            },
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                config: {
                                    user: {
                                        first_name: 'Max',
                                        last_name: 'Muster',
                                        email: 'max@example.com',
                                    },
                                },
                            },
                        },
                    }),
                ],
                stubs: vuetifyStubs,
            },
        })

        expect(screen.queryByText(/Benutzer:/)).not.toBeInTheDocument()
    })
})
