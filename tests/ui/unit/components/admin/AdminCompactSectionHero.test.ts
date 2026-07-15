import { createTestingPinia } from '@pinia/testing'
import { render, screen } from '@testing-library/vue'
import { describe, expect, it } from 'vitest'
import AdminCompactSectionHero from '@/pages/admin/components/AdminCompactSectionHero.vue'

const vuetifyStubs = {
    'v-sheet': { template: '<div><slot /></div>' },
    VSheet: { template: '<div><slot /></div>' },
    'v-chip': { template: '<span><slot /></span>' },
    VChip: { template: '<span><slot /></span>' },
    'v-icon': { template: '<i><slot /></i>' },
    VIcon: { template: '<i><slot /></i>' },
    'v-progress-linear': { template: '<div><slot /></div>' },
    VProgressLinear: { template: '<div><slot /></div>' },
}

describe('AdminCompactSectionHero', () => {
    it('renders compact header chips and second-row status', () => {
        render(AdminCompactSectionHero, {
            props: {
                eyebrow: 'Unterricht',
                title: 'Lehrbereich und Kurssteuerung',
                chips: [
                    { key: 'school', text: 'Schule A', icon: 'mdi-domain' },
                    { key: 'hidden', text: 'Nicht sichtbar', visible: false },
                ],
                statusItems: [
                    { key: 'time', text: 'Mittwoch, 15.07.2026, 09:30:00', icon: 'mdi-clock-outline' },
                ],
                progress: 64,
                progressLabel: 'Schuljahr: 64% abgeschlossen',
                progressSecondaryLabel: '2. Semester: 29% abgeschlossen',
                progressMarker: 52.5,
                progressMarkerLabel: 'Beginn des 2. Semesters',
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

        expect(screen.getByRole('heading', { name: 'Lehrbereich und Kurssteuerung' })).toBeInTheDocument()
        expect(screen.getByText('Benutzer: Guenther Kron - guenther.kron@cdgym.at')).toBeInTheDocument()
        expect(screen.getByText('Schule A')).toBeInTheDocument()
        expect(screen.queryByText('Nicht sichtbar')).not.toBeInTheDocument()
        expect(screen.getByText('Mittwoch, 15.07.2026, 09:30:00')).toBeInTheDocument()
        expect(screen.getByText('Schuljahr: 64% abgeschlossen')).toBeInTheDocument()
        expect(screen.getByText('2. Semester: 29% abgeschlossen')).toBeInTheDocument()
        expect(screen.getByLabelText('Beginn des 2. Semesters')).toHaveStyle({ left: '52.5%' })
    })

    it('clamps the progress value to the supported range', () => {
        expect((AdminCompactSectionHero as any).computed.normalizedProgress.call({ progress: 140 })).toBe(100)
        expect((AdminCompactSectionHero as any).computed.normalizedProgress.call({ progress: -10 })).toBe(0)
        expect((AdminCompactSectionHero as any).computed.normalizedProgress.call({ progress: null })).toBeNull()
    })

    it('clamps the optional progress marker to the supported range', () => {
        expect((AdminCompactSectionHero as any).computed.normalizedProgressMarker.call({ progressMarker: 140 })).toBe(100)
        expect((AdminCompactSectionHero as any).computed.normalizedProgressMarker.call({ progressMarker: -10 })).toBe(0)
        expect((AdminCompactSectionHero as any).computed.normalizedProgressMarker.call({ progressMarker: null })).toBeNull()
    })

    it('uses normal font weight for the progress label', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/components/AdminCompactSectionHero.vue', 'utf8')
        )

        expect(source).toMatch(/\.admin-compact-section-hero__progress-label\s*\{[^}]*font-weight:\s*400;/s)
    })
})
