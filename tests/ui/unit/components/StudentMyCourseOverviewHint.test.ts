import { describe, expect, it } from 'vitest'
import MyCourse from '@/pages/homepage/student/overview/myCourse/MyCourse.vue'

describe('Student MyCourse overview required NA hint', () => {
    it('shows hint when exactly one required entry exists and it is NA', () => {
        const ctx = {
            requiredEntries: [{ is_required_entry: true, grade: 'NA' }],
        }

        const showHint = (MyCourse as any).computed.showMissingRequiredNaHint.call(ctx)

        expect(showHint).toBe(true)
    })

    it('shows hint when exactly one required NA exists among multiple required entries', () => {
        const ctx = {
            requiredEntries: [
                { is_required_entry: true, grade: 'NA' },
                { is_required_entry: true, grade: '2' },
            ],
        }

        const showHint = (MyCourse as any).computed.showMissingRequiredNaHint.call(ctx)

        expect(showHint).toBe(true)
    })

    it('does not show hint when more than one required NA exists', () => {
        const ctx = {
            requiredEntries: [
                { is_required_entry: true, grade: 'NA' },
                { is_required_entry: true, grade: 'NA' },
            ],
        }

        const showHint = (MyCourse as any).computed.showMissingRequiredNaHint.call(ctx)

        expect(showHint).toBe(false)
    })

    it('does not show hint when required entry is not NA', () => {
        const ctx = {
            requiredEntries: [{ is_required_entry: true, grade: '3' }],
        }

        const showHint = (MyCourse as any).computed.showMissingRequiredNaHint.call(ctx)

        expect(showHint).toBe(false)
    })
})
