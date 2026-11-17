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

it('offers the next step for a prepared exam without claiming email readiness', () => {
    const exercise = { preparation: { status: 'ready' }, assignment_status: 'unknown', email_preparation: { status: 'unknown' } }
    expect(workPreparationLabel({ status: 'found', data: { exercise } })).toBe('Aufgaben bereitstellen')
    expect(workPreparationLabel({ status: 'found', data: { exercise: { ...exercise, email_preparation: { status: 'ready' } } } })).toBe('Aufgaben bereitstellen')
    expect(workPreparationLabel({ status: 'found', data: { exercise: { ...exercise, email_dispatch: { status: 'sent' } } } })).toBe('Aufgaben bereitstellen')
    expect(workPreparationLabel({ status: 'found', data: { exercise: { ...exercise, email_preparation: { status: 'unknown', message: 'Beleg widersprüchlich' } } } })).toBe('Aufgaben bereitstellen')
    expect(workPreparationLabel({ status: 'unknown', data: { exercise } })).toBe('LF nicht bestätigt')
    expect(workPreparationLabel({ status: 'missing', data: { exercise } })).toBe('LF erstellen')
})
