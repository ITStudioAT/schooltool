import { fireEvent, render, screen } from '@testing-library/vue'
import { expect, it } from 'vitest'
import SchooltoolModuleShowcase from '@/pages/homepage/index/SchooltoolModuleShowcase.vue'

it('keeps exactly one module preview open when a selection has six entries', async () => {
    const modules = ['Anmeldesystem', 'Lehrertool', 'SEPP', 'Schülerportal', 'Termine', 'Lernmaterialien']
        .map((title, index) => ({ id: `module-${index}`, title, description: `Beispiel für ${title}`, motif: 'books' }))

    render(SchooltoolModuleShowcase, { props: { modules } })

    expect(screen.getAllByRole('button')).toHaveLength(6)
    expect(screen.getByRole('region', { name: 'Anmeldesystem' })).toBeVisible()

    const lastChoice = screen.getByRole('button', { name: 'Lernmaterialien' })
    await fireEvent.click(lastChoice)

    const preview = screen.getByRole('region', { name: 'Lernmaterialien' })
    expect(preview).toBeVisible()
    expect(preview).toHaveTextContent('Beispiel für Lernmaterialien')
    expect(lastChoice).toHaveAttribute('aria-expanded', 'true')
    expect(lastChoice).toHaveAttribute('aria-controls', preview.id)
    expect(screen.queryByRole('region', { name: 'Anmeldesystem' })).not.toBeInTheDocument()
})
