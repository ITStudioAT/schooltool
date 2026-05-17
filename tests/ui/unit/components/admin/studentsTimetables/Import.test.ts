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

        expect(componentSource).toContain('importNavigationItems()')
        expect(componentSource).toContain("label: 'Überblick'")
        expect(componentSource).toContain("label: 'Import'")
        expect(componentSource).toContain("v-if=\"import_action === 'overview'\"")
        expect(componentSource).toContain("v-if=\"import_action === 'import'\"")
        expect(componentSource).toContain('/admin/students-timetables/import/${this.import_action}')
        expect(componentSource).toContain('Hauptdatenbestand')
        expect(componentSource).toContain('student_timetable_entries')
        expect(componentSource).not.toContain('<span>Tabelle</span>')
        expect(componentSource).toContain('Alle Kurse')
        expect(componentSource).toContain('activeDatasetCourses')
        expect(componentSource).toContain('Wochenstd.')
        expect(componentSource).toContain('datasetCourseWeeklyHoursLabel(courseItem)')
        expect(componentSource).toContain('Einzeltermine')
        expect(componentSource).toContain('activeDatasetSingleDateCourses')
        expect(componentSource).toContain('singleDateAppointmentTimeLabel(appointment)')
        expect(componentSource).toContain('label="Aktiv"')
        expect(componentSource).toContain('/api/admin/students-timetables/imports/single-date-appointments')
        expect(componentSource).toContain('single-date-appointment-row--inactive')
        expect(componentSource).toContain('LoadingAnimation')
        expect(componentSource).toContain('single-date-saving-dots')
        expect(componentSource).toContain('Importverlauf')
        expect(componentSource).toContain('<v-expansion-panels')
        expect(componentSource).toContain('Nicht importierte TT-Einträge')
        expect(componentSource).toContain('nicht importiert: 2. Spalte = 0 oder 8. Spalte ohne Kurs')
        expect(componentSource).toContain('importedTtCount(importItem)')
        expect(componentSource).toContain('Löschen')
        expect(componentSource).not.toContain('Unimportieren')
        expect(componentSource).toContain('@click.stop="openDeleteDialog(importItem)"')
        expect(componentSource).toContain('per_page: 100')
    })

    it('calculates imported TT records after skipped invalid records', () => {
        const methods = (Import as any).methods

        expect(methods.importedTtCount({
            sections: { TT: 9 },
            tt_skipped_invalid: 2,
        })).toBe(7)

        expect(methods.importedTtCount({
            sections: { TT: 1 },
            tt_skipped_invalid: 3,
        })).toBe(0)
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

    it('formats main dataset course weekly hours', () => {
        const methods = (Import as any).methods

        expect(methods.datasetCourseWeeklyHoursLabel({ weekly_hours: 4 })).toBe('4')
        expect(methods.datasetCourseWeeklyHoursLabel({ weekly_hours: null })).toBe('-')
        expect(methods.datasetCourseWeeklyHoursLabel({})).toBe('-')
    })

    it('formats single-date appointment labels', () => {
        const methods = (Import as any).methods

        expect(methods.formatDateWithWeekdayLabel('2026-02-17')).toBe('Di, 17.02.2026')
        expect(methods.singleDateAppointmentTimeLabel({
            period: '14',
            starts_at: '20:25',
            ends_at: '21:10',
        })).toBe('14. Std. 20:25-21:10')
        expect(methods.singleDateAppointmentTimeLabel({
            period: '2',
        })).toBe('2. Std.')
    })

    it('syncs and serializes single-date appointment activation', () => {
        const methods = (Import as any).methods
        const appointments = [
            {
                date: '2026-02-17',
                period: '14',
                starts_at: '20:25',
                subject: 'LPT',
                teacher: 'AB',
                entry_ids: [1],
                active: true,
            },
            {
                date: '2026-02-18',
                period: '10',
                starts_at: '17:05',
                subject: 'LPT',
                teacher: 'AB',
                entry_ids: [2],
                active: false,
            },
        ]
        const courseItem = { name: 'LPT-ALT', appointments }
        const firstKey = methods.singleDateAppointmentKey(courseItem, appointments[0])
        const secondKey = methods.singleDateAppointmentKey(courseItem, appointments[1])
        const ctx = {
            activeDatasetSingleDateCourses: [courseItem],
            activeSingleDateAppointmentKeys: [],
            singleDateActivationDatasetSignature: '',
            singleDateAppointmentKey: methods.singleDateAppointmentKey,
            singleDateActivationDatasetStateSignature: `${firstKey}:active|${secondKey}:inactive`,
        }

        methods.syncSingleDateAppointmentActivation.call(ctx)

        expect(ctx.activeSingleDateAppointmentKeys).toEqual([firstKey])

        const payloadCtx = {
            activeDatasetSingleDateCourses: [courseItem],
            activeSingleDateAppointmentKeySet: new Set([firstKey]),
            singleDateAppointmentKey: methods.singleDateAppointmentKey,
        }

        expect(methods.singleDateActivationPayload.call(payloadCtx)).toEqual([
            { entry_ids: [1], active: true },
            { entry_ids: [2], active: false },
        ])
    })
})
