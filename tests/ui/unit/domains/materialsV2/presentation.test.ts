import { describe, expect, it } from 'vitest'
import {
    categoryIcon,
    createActionLabel,
    formatFileSize,
    fuzzyTextMatch,
    isDefaultCategory,
    isFileCategory,
    isReminderCategory,
    reminderBadge,
} from '@/domains/materialsV2/presentation'

describe('Materials V2 presentation helpers', () => {
    it('recognizes system categories without depending on casing', () => {
        expect(isReminderCategory(' termine ')).toBe(true)
        expect(isDefaultCategory('LINKS')).toBe(true)
        expect(isFileCategory(' dateien ')).toBe(true)
        expect(isDefaultCategory('DATEIEN')).toBe(true)
        expect(isDefaultCategory('Biologie')).toBe(false)
    })

    it('maps category actions and file sizes for the UI', () => {
        expect(categoryIcon('Screenshots')).toBe('mdi-monitor-screenshot')
        expect(categoryIcon('Dateien')).toBe('mdi-file-multiple-outline')
        expect(createActionLabel('Dateien', 'Material hinzufügen')).toBe('Dateien hinzufügen')
        expect(createActionLabel('Notizen', 'Material hinzufügen')).toBe('Notiz hinzufügen')
        expect(formatFileSize(4096)).toBe('4.0 KB')
    })

    it('calculates reminder badges against an injected local date', () => {
        const today = new Date(2026, 8, 15)

        expect(reminderBadge('2026-09-15', today)).toEqual({ label: 'Heute', color: 'error' })
        expect(reminderBadge('2026-09-16', today)).toEqual({ label: 'Morgen', color: 'warning' })
        expect(reminderBadge('2026-09-20', today)).toBeNull()
    })

    it('matches cluster names by fragments, normalized characters, and small typing errors', () => {
        expect(fuzzyTextMatch('Wochenplanung', 'woch')).toBe(true)
        expect(fuzzyTextMatch('Wochenplanung', 'Wochenplannung')).toBe(true)
        expect(fuzzyTextMatch('Wochenplanung', 'Wohcen')).toBe(true)
        expect(fuzzyTextMatch('Lernräume', 'lernraume')).toBe(true)
        expect(fuzzyTextMatch('Wochenplanung', 'Medien')).toBe(false)
        expect(fuzzyTextMatch('Wochenplanung', '')).toBe(true)
    })
})
