import { render, screen, within } from '@testing-library/vue'
import { expect, it } from 'vitest'
import SchooltoolModuleShowcase from '@/pages/homepage/index/SchooltoolModuleShowcase.vue'

it('shows the title and description of all six modules at once', () => {
    const modules = ['Anmeldesystem', 'Lehrertool', 'SEPP', 'Schülerportal', 'Termine', 'Lernmaterialien']
        .map((title, index) => ({ id: `module-${index}`, title, description: `Beispiel für ${title}`, image: `/example-${index}.webp` }))

    render(SchooltoolModuleShowcase, { props: { modules } })

    const cards = within(screen.getByRole('list')).getAllByRole('listitem')
    expect(cards).toHaveLength(6)

    cards.forEach((card, index) => {
        const { title, description, image } = modules[index]
        expect(within(card).getByRole('heading', { name: title, level: 3 })).toBeVisible()
        expect(within(card).getByText(description)).toBeVisible()
        expect(card.querySelector('img')).toHaveAttribute('src', image)
    })
})
