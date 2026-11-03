import { expect, it } from 'vitest'
import { workNeedsPreparation } from '@/helpers/workOverview'

it('keeps historical evidence outside assignment preparation', () => {
    const work = { id: 64, status: { dispatch_notifications: [{ purpose: 'tasks' }] } }
    expect(workNeedsPreparation(work, { has_import: true })).toBe(false)
})

it.each([
    { communicated_deadline_date: '2026-10-22', communicated_deadline_at: null },
    { communicated_deadline_date: '2026-10-22', communicated_deadline_at: '2026-10-22T10:00:00+02:00' },
])('keeps preparation actions available after setting only a student deadline', status => {
    expect(workNeedsPreparation({ id: 86, status }, { has_import: false })).toBe(true)
})

it('keeps previously imported work outside the preparation flow', () => {
    expect(workNeedsPreparation({ id: 86, status: { communicated_deadline_date: '2026-10-22', folder_imported_at: '2026-10-23T12:00:00Z' } },
        { has_import: true })).toBe(false)
})
