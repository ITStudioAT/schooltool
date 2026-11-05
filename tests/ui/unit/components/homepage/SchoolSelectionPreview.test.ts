import { fireEvent, render, screen } from '@testing-library/vue'
import { describe, expect, it } from 'vitest'
import SchoolSelectionPreview from '@/pages/homepage/index/SchoolSelectionPreview.vue'
import HomepageIndex from '@/pages/homepage/index/Index.vue'

const schools = [
    { id: 5, long_name: 'Abendgymnasium Salzburg', short_name: 'BFB', logo: 'logo_5.png' },
    { id: 2, long_name: 'BG Zaunergasse', short_name: 'BGZ', logo: 'logo_2.png' },
    { id: 1, long_name: 'Christian-Doppler-Gymnasium Salzburg', short_name: 'CDGym', logo: 'logo_1.png' },
]

describe('SchoolSelectionPreview', () => {
    it('uses the real school logos and sends the selected school to the existing flow', async () => {
        const { emitted } = render(SchoolSelectionPreview, { props: { schools } })

        expect(screen.getByRole('img', { name: 'BG Zaunergasse' })).toHaveAttribute('src', '/storage/images/logos/logo_2.png')
        await fireEvent.click(screen.getByRole('button', { name: 'BG Zaunergasse auswählen' }))
        expect(emitted().select).toEqual([[schools[1]]])
    })

    it('finds schools by their abbreviation when the list requires search', async () => {
        render(SchoolSelectionPreview, { props: { schools: [
            ...schools,
            ...Array.from({ length: 4 }, (_, index) => ({ id: 10 + index, long_name: `Weitere Schule ${index}`, short_name: `S${index}`, logo: null })),
        ] }, global: { stubs: { VIcon: true } } })

        await fireEvent.update(screen.getByRole('searchbox'), 'cdgym')
        expect(screen.getByRole('button', { name: 'Christian-Doppler-Gymnasium Salzburg auswählen' })).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'BG Zaunergasse auswählen' })).not.toBeInTheDocument()
        await fireEvent.update(screen.getByRole('searchbox'), 'Unbekannte Schule')
        expect(screen.getByText('Keine Schule für diese Suche gefunden.')).toBeInTheDocument()
    })

    it.each([
        ['selectSchool', true, true],
        ['selectSchool', false, false],
        ['schoolSelected', true, false],
        ['login', true, false],
    ])('limits the design to the entry step %s with switch %s', (step, enabled, expected) => {
        expect((HomepageIndex as any).computed.showNewHomepage.call({ step, homepageDesignEnabled: enabled })).toBe(expected)
    })
})
