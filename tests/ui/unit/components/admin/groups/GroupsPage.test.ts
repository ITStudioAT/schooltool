import { describe, expect, it } from 'vitest'
import Groups from '@/pages/admin/groups/Groups.vue'

describe('Groups page header', () => {
    it('builds header chips from school and groups count', () => {
        const ctx = {
            selectedSchoolLabel: 'Christian-Doppler-Gymnasium Salzburg',
            groups: [{ id: 1 }, { id: 2 }, { id: 3 }],
        }

        const chips = (Groups as any).computed.headerChips.call(ctx)

        expect(chips).toEqual([
            { key: 'school', text: 'Christian-Doppler-Gymnasium Salzburg', icon: 'mdi-domain' },
            { key: 'groups', text: '3 Gruppen', icon: 'mdi-account-group-outline' },
        ])
    })

    it('returns reusable active-section metadata for centralized hero', () => {
        const section = (Groups as any).computed.headerActiveSection.call({})

        expect(section).toEqual({
            icon: 'mdi-account-group-outline',
            label: 'Schulgruppen, Materialien, Eigene',
            note: 'Löschen nur ohne Mitglieder möglich.',
        })
    })
})
