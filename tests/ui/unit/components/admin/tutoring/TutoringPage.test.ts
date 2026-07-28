import { describe, expect, it } from 'vitest'
import Tutoring from '@/pages/admin/tutoring/Tutoring.vue'

describe('Tutoring page', () => {
    it('builds hero chips from selected school context', () => {
        const ctx = {
            selectedSchoolLabel: 'Christian-Doppler-Gymnasium Salzburg',
            selectedSchoolyearLabel: '2025/26',
            selectedRoleLabel: 'tutoring_admin / admin',
        }

        const chips = (Tutoring as any).computed.headerChips.call(ctx)

        expect(chips).toEqual([
            { key: 'school', text: 'Christian-Doppler-Gymnasium Salzburg', icon: 'mdi-domain' },
            { key: 'schoolyear', text: '2025/26', icon: 'mdi-calendar-month-outline' },
        ])
    })

    it('shows admin navigation items for tutoring admins', () => {
        const ctx = {
            config: {
                roles: ['tutoring_admin'],
            },
            admins: ['super_admin', 'admin', 'tutoring_admin'],
        }

        const items = (Tutoring as any).computed.visibleNavigationItems.call(ctx)

        expect(items.map((item: { key: string }) => item.key)).toEqual(['overview', 'requests'])
    })
})
