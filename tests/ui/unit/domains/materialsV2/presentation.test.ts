import { describe, expect, it } from 'vitest'
import {
    categoryIcon,
    createActionLabel,
    formatFileSize,
    isDefaultCategory,
    isReminderCategory,
    reminderBadge,
} from '@/domains/materialsV2/presentation'

describe('Materials V2 presentation helpers', () => {
    it('recognizes system categories without depending on casing', () => {
        expect(isReminderCategory(' termine ')).toBe(true)
        expect(isDefaultCategory('LINKS')).toBe(true)
        expect(isDefaultCategory('Biologie')).toBe(false)
    })

    it('maps category actions and file sizes for the UI', () => {
        expect(categoryIcon('Screenshots')).toBe('mdi-monitor-screenshot')
        expect(createActionLabel('Notizen', 'Material hinzufügen')).toBe('Notiz hinzufügen')
        expect(formatFileSize(4096)).toBe('4.0 KB')
    })

    it('calculates reminder badges against an injected local date', () => {
        const today = new Date(2026, 8, 15)

        expect(reminderBadge('2026-09-15', today)).toEqual({ label: 'Heute', color: 'error' })
        expect(reminderBadge('2026-09-16', today)).toEqual({ label: 'Morgen', color: 'warning' })
        expect(reminderBadge('2026-09-20', today)).toBeNull()
    })
})
