import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import Import from '@/pages/admin/studentsTimetables/import/Import.vue'

describe('Students timetable import', () => {
    it('does not show the obsolete replacement warning', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/import/Import.vue',
            'utf8',
        )

        expect(componentSource).not.toContain('Ein neuer Import ersetzt die bestehende Datei.')
    })

    it('shows main dataset metadata and expandable import history', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/import/Import.vue',
            'utf8',
        )

        expect(componentSource).toContain('Hauptdatenbestand')
        expect(componentSource).toContain('student_timetable_entries')
        expect(componentSource).not.toContain('<span>Tabelle</span>')
        expect(componentSource).toContain('Alle Kurse')
        expect(componentSource).toContain('activeDatasetCourses')
        expect(componentSource).toContain('Importverlauf')
        expect(componentSource).toContain('<v-expansion-panels')
        expect(componentSource).toContain('Löschen')
        expect(componentSource).not.toContain('Unimportieren')
        expect(componentSource).toContain('@click.stop="openDeleteDialog(importItem)"')
        expect(componentSource).toContain('per_page: 100')
    })

    it('formats import date ranges', () => {
        const methods = (Import as any).methods

        expect(methods.dateRangeLabel({
            tt_first_date: '2026-02-16',
            tt_last_date: '2026-07-11',
        })).toBe('2026-02-16 - 2026-07-11')

        expect(methods.dateRangeLabel({
            tt_first_date: '2026-02-16',
            tt_last_date: '2026-02-16',
        })).toBe('2026-02-16')

        expect(methods.dateRangeLabel({})).toBe('Kein Zeitraum')
    })

    it('formats main dataset date ranges', () => {
        const methods = (Import as any).methods

        expect(methods.datasetDateRangeLabel({
            first_date: '2026-02-16',
            last_date: '2026-07-11',
        })).toBe('2026-02-16 - 2026-07-11')

        expect(methods.datasetDateRangeLabel({
            first_date: '2026-02-16',
            last_date: '2026-02-16',
        })).toBe('2026-02-16')

        expect(methods.datasetDateRangeLabel({})).toBe('Kein Zeitraum')
    })

    it('formats main dataset course date ranges', () => {
        const methods = (Import as any).methods

        expect(methods.datasetCourseDateRangeLabel({
            first_date: '2026-02-16',
            last_date: '2026-07-11',
        })).toBe('2026-02-16 - 2026-07-11')

        expect(methods.datasetCourseDateRangeLabel({
            first_date: '2026-02-16',
            last_date: '2026-02-16',
        })).toBe('2026-02-16')

        expect(methods.datasetCourseDateRangeLabel({})).toBe('Kein Zeitraum')
    })
})
