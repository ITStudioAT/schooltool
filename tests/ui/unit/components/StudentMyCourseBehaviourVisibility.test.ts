import { describe, expect, it } from 'vitest'
import MyCourse from '@/pages/homepage/student/overview/myCourse/MyCourse.vue'

describe('Student MyCourse behaviour visibility', () => {
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

    it('switches back to overview when behaviour tab gets disabled', () => {
        const ctx = {
            currentTab: 'behaviour',
        }

        ;(MyCourse as any).watch.showBehaviourEnabled.call(ctx, false)

        expect(ctx.currentTab).toBe('overview')
    })
})
