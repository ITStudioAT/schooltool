import { describe, expect, it } from 'vitest'
import MyCourse from '@/pages/homepage/student/overview/myCourse/MyCourse.vue'

describe('Student MyCourse behaviour visibility', () => {
    it('combines modern and legacy behaviour entries in date order without losing colliding ids', () => {
        const legacyEntry = { id: 1, date: '2026-09-14', type: 'E', description: 'Alter Eintrag' }
        const modernEntry = { id: 1, date: '2026-09-21', type: 'D', category: 'Verhalten', comment: 'Neuer Eintrag' }
        const entries = (MyCourse as any).computed.behaviourEntries.call({
            showBehaviourEnabled: true,
            course: { behaviour_entries: [legacyEntry] },
            entryAreaBehaviourEntries: [modernEntry],
        })

        expect(entries).toEqual([modernEntry, legacyEntry])
    })

    it('hides both modern and legacy behaviour while keeping additional entries separate', () => {
        const ctx = {
            showBehaviourEnabled: false,
            course: { behaviour_entries: [{ id: 1 }] },
            entryAreaBehaviourEntries: [{ id: 2, category: 'Verhalten' }],
            additionalEntries: [{ id: 3, category: 'Weitere' }],
        }

        expect((MyCourse as any).computed.behaviourEntries.call(ctx)).toEqual([])
        expect(ctx.additionalEntries).toEqual([{ id: 3, category: 'Weitere' }])
    })

    it('enables behaviour by default when flag is missing', () => {
        const ctx = {
            course: {},
        }

        const enabled = (MyCourse as any).computed.showBehaviourEnabled.call(ctx)

        expect(enabled).toBe(true)
    })

    it('disables behaviour when API flag is false', () => {
        const ctx = {
            course: { show_behaviour: false },
        }

        const enabled = (MyCourse as any).computed.showBehaviourEnabled.call(ctx)

        expect(enabled).toBe(false)
    })

    it('redirects an existing behaviour tab to the common feedback page', () => {
        const ctx = {
            currentTab: 'behaviour',
        }

        ;(MyCourse as any).watch.currentTab.call(ctx, 'behaviour')

        expect(ctx.currentTab).toBe('entries')
    })
})
