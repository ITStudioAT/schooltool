import { expect, it } from 'vitest'
import { workPreparationLabel } from '@/helpers/workOverview'

it('reports a missing assignment in an identified folder before suggesting email preparation', () => {
    expect(workPreparationLabel({ status: 'found', data: { exercise: { assignment_status: 'missing', email_preparation: { status: 'missing' } } } }))
        .toBe('Aufgabenstellung fehlt')
})

it('preserves confirmed dispatch status for an existing assignment', () => {
    expect(workPreparationLabel({ status: 'found', data: { exercise: { assignment_status: 'ready', email_dispatch: { status: 'sent' } } } }))
        .toBe('Abgaben einsammeln')
})
