import { describe, expect, it } from 'vitest'
import {
    canonicalTimetableCourseLabel,
    canonicalTimetableModuleName,
    sortTimetableModuleCourses,
} from '@/pages/admin/studentsTimetables/timetableV3/courseLabels'

describe('canonicalTimetableCourseLabel', () => {
    it.each([
        ['GWB2', 'GW2'],
        ['GPB2 - 2A', 'GS2 - 2A'],
        ['GSGPB2 - 4B', 'GS2 - 4B'],
        ['MU2 - 5K', 'ME2 - 5K'],
        ['MEMU2 - 4B', 'ME2 - 4B'],
        ['OKON2 - 3R', 'ÖKO2 - 3R'],
        ['SPA2 - 2F', 'S2 - 2F'],
        ['LET - 1U', 'LPT - 1U'],
        ['Rev2 - EIN', 'Rev2 - EIN'],
    ])('uses the canonical module label for %s', (sourceLabel, expectedLabel) => {
        expect(canonicalTimetableCourseLabel(sourceLabel)).toBe(expectedLabel)
    })

    it('uses the explicit module code for an imported alias with the same module number', () => {
        expect(canonicalTimetableCourseLabel('GWB2-5CK-HOA', 'GW2')).toBe('GW2-5CK-HOA')
        expect(canonicalTimetableCourseLabel('EIN', 'Rev2')).toBe('EIN')
    })

    it('uses the canonical LPT name regardless of stale imported names', () => {
        expect(canonicalTimetableModuleName('Literarisches Praktikum', 'LPT')).toBe(
            'Lern- und Präsentationstechniken',
        )
        expect(canonicalTimetableModuleName('LET', 'LET')).toBe('Lern- und Präsentationstechniken')
        expect(canonicalTimetableModuleName('Deutsch 2', 'D2')).toBe('Deutsch 2')
    })

    it.each([
        ['ET1', 'Ethik 1'],
        ['ETH', 'Ethik'],
        ['Rev2', 'Religion evangelisch 2'],
        ['RIS1', 'Religion Islam 1'],
        ['Rk3', 'Religion katholisch 3'],
        ['Ror2', 'Religion orthodox 2'],
    ])('uses the specific religion name for %s', (moduleCode, expectedName) => {
        expect(canonicalTimetableModuleName('Religion/Ethik', moduleCode)).toBe(expectedName)
    })

    it('preserves the module number on other canonical names', () => {
        expect(canonicalTimetableModuleName('Literarisches Praktikum', 'LPT2')).toBe(
            'Lern- und Präsentationstechniken 2',
        )
    })

    it('sorts module courses alphabetically and naturally without mutating the source', () => {
        const courses = [
            { key: 'course-b', title: 'E2-B' },
            { key: 'course-a10', title: 'E2-A10' },
            { key: 'course-a2', title: 'e2-a2' },
        ]

        expect(sortTimetableModuleCourses(courses, 'E2').map(course => course.key)).toEqual([
            'course-a2',
            'course-a10',
            'course-b',
        ])
        expect(courses.map(course => course.key)).toEqual([
            'course-b',
            'course-a10',
            'course-a2',
        ])
    })
})
