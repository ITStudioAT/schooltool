import { readFileSync } from 'node:fs'
import { shallowMount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import TimetableV3PossibleTimetables from '@/pages/admin/studentsTimetables/timetableV3/TimetableV3PossibleTimetables.vue'

function timetableFixture({
    key,
    number,
    slots,
    type,
}: {
    key: string
    number: number
    slots: Record<string, unknown>
    type: 'full_green' | 'green'
}) {
    return {
        key,
        metrics: {
            free_days: number,
            gap_count: number - 1,
        },
        number,
        slots,
        statusMessage: type === 'green'
            ? 'Grüner Stundenplan mit Überschneidungen'
            : 'Voller grüner Stundenplan',
        type,
    }
}

function slotFixture(code: string, weekday: number, hour: number, overrides = {}) {
    return {
        code,
        conflicts: [],
        courseGroup: {
            dates: ['2026-08-17', '2026-08-24'],
            ends_at: '18:35',
            hour,
            recurrence_label: '1-wöchig',
            starts_at: '17:50',
            weekday,
        },
        dateRangeLabel: '17.8.–24.8.',
        key: `${code}-${weekday}-${hour}`,
        name: `${code} Name`,
        sameSlotEntries: [],
        sourceLabel: `${code} - Gruppe A`,
        ...overrides,
    }
}

function rangeTimetables(from: number, to: number) {
    return Array.from({ length: (to - from) + 1 }, (_, index) => {
        const number = from + index

        return timetableFixture({
            key: `timetable-${number}`,
            number,
            slots: {},
            type: 'full_green',
        })
    })
}

describe('TimetableV3PossibleTimetables', () => {
    it('keeps backend order and navigates globally even when per-type numbers repeat', async () => {
        const timetables = [
            timetableFixture({
                key: 'full-green-1',
                number: 1,
                slots: { '1-1': slotFixture('D1', 1, 1) },
                type: 'full_green',
            }),
            timetableFixture({
                key: 'green-1',
                number: 1,
                slots: { '6-3': slotFixture('M1', 6, 3) },
                type: 'green',
            }),
        ]
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                allowSaturdayLessons: true,
                selectedIndex: 0,
                timetables,
                totalCount: 2,
            },
        })

        expect((wrapper.vm as any).selectedTimetable.key).toBe('full-green-1')
        expect(wrapper.text()).toContain('Stundenplan 1 von 2')
        expect(wrapper.text()).not.toContain('Einzeltermin-Überschneidung')

        ;(wrapper.vm as any).selectNextTimetable()
        expect(wrapper.emitted('navigate')).toEqual([[1]])

        await wrapper.setProps({ selectedIndex: 1 })
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).selectedTimetable.key).toBe('green-1')
        expect(wrapper.text()).toContain('Stundenplan 2 von 2')
        expect(wrapper.text()).not.toContain('Einzeltermin-Überschneidung')

        ;(wrapper.vm as any).selectNextTimetable()
        expect(wrapper.emitted('navigate')).toEqual([[1]])

        ;(wrapper.vm as any).selectPreviousTimetable()
        expect(wrapper.emitted('navigate')).toEqual([[1], [0]])
    })

    it('can show one selected timetable without selection navigation', () => {
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                navigationVisible: false,
                selectedIndex: 137,
                pageOffset: 100,
                timetables: rangeTimetables(101, 200),
                totalCount: 250,
            },
        })

        expect(wrapper.text()).toContain('Stundenplan 138 von 250')
        expect(wrapper.find('.timetable-v3-results__table').exists()).toBe(true)
        expect(wrapper.find('.timetable-v3-results__navigation').exists()).toBe(false)
    })

    it('overlays manual courses on the full configured timetable and removes them again', async () => {
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                allowSaturdayLessons: true,
                emptyTimetable: true,
                highlightMultipleEntries: true,
                emptyHourRows: [
                    { hour: 9, from: '', until: '' },
                    { hour: 7, from: '14:45:00', until: '15:30:00' },
                    { hour: 8, from: '15:30', until: '16:15' },
                ],
                navigationVisible: false,
                positionVisible: false,
                timetables: [timetableFixture({
                    key: 'stale-result',
                    number: 1,
                    slots: { '1-1': slotFixture('D1', 1, 1) },
                    type: 'full_green',
                })],
            },
        })

        expect(wrapper.find('.timetable-v3-results__table').exists()).toBe(true)
        expect(wrapper.find('.timetable-v3-results__unavailable').exists()).toBe(false)
        expect(wrapper.find('.timetable-v3-results__navigation').exists()).toBe(false)
        expect(wrapper.find('.timetable-v3-results__header h4').text()).toBe('Stundenplan')
        expect(wrapper.text()).not.toContain('Stundenplan 1 von 1')
        expect(wrapper.find('caption').text()).toBe('Leerer Stundenplan')
        expect((wrapper.vm as any).visibleHours).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15])
        expect((wrapper.vm as any).visibleHourRows).toEqual([
            { hour: 1, timeRange: '' },
            { hour: 2, timeRange: '' },
            { hour: 3, timeRange: '' },
            { hour: 4, timeRange: '' },
            { hour: 5, timeRange: '' },
            { hour: 6, timeRange: '' },
            { hour: 7, timeRange: '14:45–15:30' },
            { hour: 8, timeRange: '15:30–16:15' },
            { hour: 9, timeRange: '' },
            { hour: 10, timeRange: '' },
            { hour: 11, timeRange: '' },
            { hour: 12, timeRange: '' },
            { hour: 13, timeRange: '' },
            { hour: 14, timeRange: '' },
            { hour: 15, timeRange: '' },
        ])
        expect(wrapper.findAll('tbody tr')).toHaveLength(15)
        expect(wrapper.findAll('tbody td')).toHaveLength(90)
        expect(wrapper.findAll('.timetable-v3-results__period-time').map(cell => cell.text()))
            .toEqual(['14:45–15:30', '15:30–16:15'])
        expect(wrapper.findAll('.timetable-v3-results__lesson')).toHaveLength(0)
        expect(wrapper.text()).not.toContain('D1')

        await wrapper.setProps({
            manualTimetable: {
                key: 'manual-timetable',
                slots: {
                    '1-10': slotFixture('M1', 1, 10, {
                        sameSlotEntries: [slotFixture('CH1', 1, 10)],
                    }),
                },
            },
        })

        expect(wrapper.find('caption').text()).toBe('Manueller Stundenplan')
        expect((wrapper.vm as any).visibleHours).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15])
        expect(wrapper.findAll('tbody tr')).toHaveLength(15)
        expect(wrapper.findAll('.timetable-v3-results__lesson')).toHaveLength(2)
        expect(wrapper.findAll('.timetable-v3-results__lesson--overlap')).toHaveLength(1)
        expect(wrapper.findAll('[icon="mdi-calendar-alert-outline"]')).toHaveLength(1)
        expect(wrapper.find('[role="status"]').exists()).toBe(false)
        expect(wrapper.text()).not.toContain('Einzeltermin-Überschneidung · erlaubt')
        expect(wrapper.find('.timetable-v3-results__lesson--overlap').text())
            .toContain('Überschneidungen')
        expect(wrapper.text()).toContain('M1')
        expect(wrapper.text()).toContain('CH1')
        expect(wrapper.text()).not.toContain('D1')

        await wrapper.setProps({ manualTimetable: { key: 'manual-timetable', slots: {} } })

        expect(wrapper.find('caption').text()).toBe('Leerer Stundenplan')
        expect(wrapper.findAll('.timetable-v3-results__lesson')).toHaveLength(0)
    })

    it('falls back to an empty timetable from period 1 through period 15', () => {
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: { emptyTimetable: true },
        })

        expect((wrapper.vm as any).visibleHours).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15])
        expect(wrapper.findAll('tbody tr')).toHaveLength(15)
    })

    it('keeps same-cell courses without shared exact dates free of overlap warnings', () => {
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                highlightMultipleEntries: true,
                timetables: [timetableFixture({
                    key: 'disjoint-course-dates',
                    number: 1,
                    slots: {
                        '1-11': slotFixture('E3', 1, 11, {
                            courseGroup: {
                                dates: [
                                    '2026-02-16',
                                    '2026-02-23',
                                    '2026-03-02',
                                    '2026-03-09',
                                    '2026-03-16',
                                    '2026-03-23',
                                    '2026-04-13',
                                    '2026-04-20',
                                ],
                                ends_at: '18:35',
                                hour: 11,
                                starts_at: '17:50',
                                weekday: 1,
                            },
                            sameSlotEntries: [slotFixture('E4', 1, 11, {
                                courseGroup: {
                                    dates: [
                                        '2026-04-27',
                                        '2026-05-04',
                                        '2026-05-11',
                                        '2026-05-18',
                                        '2026-06-01',
                                        '2026-06-08',
                                        '2026-06-15',
                                        '2026-06-22',
                                        '2026-06-29',
                                        '2026-07-06',
                                    ],
                                    ends_at: '18:35',
                                    hour: 11,
                                    starts_at: '17:50',
                                    weekday: 1,
                                },
                                sourceLabel: 'E4 2Q-REIS',
                            })],
                            sourceLabel: 'E3 2Q-REIS',
                        }),
                    },
                    type: 'full_green',
                })],
            },
        })

        expect((wrapper.vm as any).timetableEntriesForCell(1, 11).map((entry: any) => entry.relationship))
            .toEqual(['primary', 'same-slot'])
        expect(wrapper.findAll('.timetable-v3-results__lesson')).toHaveLength(2)
        expect(wrapper.findAll('.timetable-v3-results__lesson--overlap')).toHaveLength(0)
        expect(wrapper.findAll('.timetable-v3-results__lesson-conflict-reference')).toHaveLength(0)
        expect(wrapper.find('.timetable-v3-results__conflict-summary').exists()).toBe(false)
        expect(wrapper.text()).not.toContain('Einzeltermin-Überschneidung')
    })

    it('shows periods 1 through 15 with configured time ranges in the manual editor', () => {
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                emptyHourRows: [
                    { hour: 1, from: '08:00:00', until: '08:50:00' },
                    { hour: 15, from: '21:15:00', until: '22:00:00' },
                ],
                showAllHours: true,
                timetables: [timetableFixture({
                    key: 'manual-editor',
                    number: 1,
                    slots: { '1-10': slotFixture('M1', 1, 10) },
                    type: 'full_green',
                })],
            },
        })

        expect((wrapper.vm as any).visibleHours).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15])
        expect((wrapper.vm as any).visibleHourRows[0]).toEqual({ hour: 1, timeRange: '08:00–08:50' })
        expect((wrapper.vm as any).visibleHourRows[9]).toEqual({ hour: 10, timeRange: '17:50–18:35' })
        expect((wrapper.vm as any).visibleHourRows[14]).toEqual({ hour: 15, timeRange: '21:15–22:00' })
        expect(wrapper.findAll('tbody tr')).toHaveLength(15)
    })

    it('renders every primary, same-slot, and allowed overlap Unterricht', () => {
        const slot = slotFixture('D5', 1, 2, {
            isDistanceLearningCourse: true,
            conflicts: [slotFixture('BU2', 1, 2, {
                courseGroup: {
                    dates: ['2026-09-14'],
                    ends_at: '18:35',
                    hour: 2,
                    recurrence_label: 'Einzeltermin',
                    starts_at: '17:50',
                    weekday: 1,
                },
                isOccasional: true,
            })],
            sameSlotEntries: [slotFixture('CH1', 1, 2, {
                courseGroup: {
                    dates: ['2026-10-12'],
                    ends_at: '18:35',
                    hour: 2,
                    recurrence_label: 'Einzeltermin',
                    starts_at: '17:50',
                    weekday: 1,
                },
            })],
        })
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                timetables: [timetableFixture({
                    key: 'green-1',
                    number: 1,
                    slots: { '1-2': slot },
                    type: 'green',
                })],
            },
        })

        expect(wrapper.text()).toContain('D5')
        expect(wrapper.text()).toContain('CH1')
        expect(wrapper.text()).toContain('BU2')
        expect(wrapper.text()).toContain('Überschneidungen')
        expect(wrapper.text()).toContain('Fernunterricht')
        expect(wrapper.text()).toContain('17:50–18:35')
        expect(wrapper.text()).toContain('1-wöchig')
        expect(wrapper.findAll('.timetable-v3-results__lesson').some(lesson => (
            lesson.attributes('aria-label')?.includes('17:50–18:35')
        ))).toBe(true)
        expect(wrapper.findAll('.timetable-v3-results__lesson')).toHaveLength(3)
    })

    it('numbers timetable conflicts and lists every course date below the timetable', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3PossibleTimetables.vue',
            'utf8',
        )
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                timetables: [timetableFixture({
                    key: 'numbered-conflicts',
                    number: 1,
                    slots: {
                        '1-11': slotFixture('E3', 1, 11, {
                            courseGroup: {
                                dates: ['2026-02-16', '2026-02-23', '2026-03-02'],
                                ends_at: '18:35',
                                hour: 11,
                                starts_at: '17:50',
                                weekday: 1,
                            },
                            conflicts: [slotFixture('E4', 1, 11, {
                                courseGroup: {
                                    dates: ['2026-02-23', '2026-04-27'],
                                    ends_at: '18:35',
                                    hour: 11,
                                    starts_at: '17:50',
                                    weekday: 1,
                                },
                                sourceLabel: 'E4 2Q-REIS',
                            })],
                            sourceLabel: 'E3 2Q-REIS',
                        }),
                        '2-12': slotFixture('D3', 2, 12, {
                            courseGroup: {
                                dates: ['2026-05-05', '2026-05-12'],
                                ends_at: '19:30',
                                hour: 12,
                                starts_at: '18:45',
                                weekday: 2,
                            },
                            conflicts: [slotFixture('M3', 2, 12, {
                                courseGroup: {
                                    dates: ['2026-05-05', '2026-05-12'],
                                    ends_at: '19:30',
                                    hour: 12,
                                    starts_at: '18:45',
                                    weekday: 2,
                                },
                                sourceLabel: 'M3 3A-MAY',
                            })],
                            sourceLabel: 'D3 3A-HUB',
                        }),
                    },
                    type: 'green',
                })],
            },
        })
        const conflictReferences = wrapper.findAll('.timetable-v3-results__lesson-conflict-reference')
        const summaryCourses = wrapper.findAll('.timetable-v3-results__conflict-course')

        expect(conflictReferences.map(reference => reference.text())).toEqual(['1', '2'])
        expect(conflictReferences.map(reference => reference.attributes('aria-label'))).toEqual([
            'Einzeltermin-Überschneidung 1',
            'Überschneidungen 2',
        ])
        expect(wrapper.findAll('.timetable-v3-results__conflict-group')).toHaveLength(2)
        expect(summaryCourses).toHaveLength(4)
        expect(summaryCourses.map(course => course.find('.timetable-v3-results__conflict-counter').text()))
            .toEqual(['!1', '!1', '!2', '!2'])
        expect(summaryCourses[0].text()).toContain('E3 2Q-REIS:16.02., 23.02., 02.03.')
        expect(summaryCourses[1].text()).toContain('E4 2Q-REIS:23.02., 27.04.')
        expect(wrapper.findAll('.timetable-v3-results__conflict-date--overlap').map(date => date.text()))
            .toEqual(['23.02.', '23.02.', '05.05.', '12.05.', '05.05.', '12.05.'])
        expect(wrapper.findAll('.timetable-v3-results__lesson-special').map(label => label.text()))
            .toEqual(['Einzeltermin-Überschneidung', 'Überschneidungen'])
        expect((wrapper.vm as any).lessonEntriesOverlapInTime(
            { courseGroup: { starts_at: '17:50', ends_at: '18:35' } },
            { courseGroup: { starts_at: '18:35', ends_at: '19:20' } },
        )).toBe(false)
        expect(source).toMatch(/\.timetable-v3-results__lesson-conflict-reference\s*\{[\s\S]*?justify-content:\s*flex-end;[\s\S]*?margin-left:\s*auto;[\s\S]*?text-align:\s*right;/u)
        expect(source).toMatch(/\.timetable-v3-results__conflict-group\s*\{[\s\S]*?background:\s*#fffbeb;[\s\S]*?border:\s*1px solid #fcd34d;[\s\S]*?border-left:\s*3px solid #f59e0b;/u)
        expect(source).toMatch(/\.timetable-v3-results__conflict-counter\s*\{[\s\S]*?border:\s*1px solid #d97706;[\s\S]*?border-radius:\s*5px;/u)
        expect(source).toMatch(/\.timetable-v3-results__conflict-date--overlap\s*\{[\s\S]*?color:\s*#b42318;/u)
    })

    it('moves a one-date overlap warning to that course and hides its compact block markers', () => {
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                timetables: [timetableFixture({
                    key: 'one-date-compact-overlap',
                    number: 1,
                    slots: {
                        '3-13': slotFixture('LPT', 3, 13, {
                            courseGroup: {
                                block_label: 'Block',
                                dates: ['2026-02-18'],
                                ends_at: '21:10',
                                hour: 13,
                                is_block: true,
                                is_kompaktunterricht: true,
                                recurrence_label: '',
                                starts_at: '20:25',
                                weekday: 3,
                            },
                            conflicts: [slotFixture('M1', 3, 13, {
                                courseGroup: {
                                    dates: ['2026-02-18', '2026-02-25', '2026-03-04'],
                                    ends_at: '21:10',
                                    hour: 13,
                                    recurrence_label: '1-wöchig',
                                    starts_at: '20:25',
                                    weekday: 3,
                                },
                                sourceLabel: 'M1 1C-MAY',
                            })],
                            sourceLabel: 'LPT 1R-ENNS',
                        }),
                    },
                    type: 'green',
                })],
            },
        })
        const lessons = wrapper.findAll('.timetable-v3-results__lesson')

        expect(lessons).toHaveLength(2)
        expect(lessons[0].text()).toContain('LPT')
        expect(lessons[0].text()).toContain('Einzeltermin-Überschneidung')
        expect(lessons[0].text()).not.toContain('Kompaktunterricht')
        expect(lessons[0].text()).not.toContain('Block')
        expect(lessons[0].classes()).toContain('timetable-v3-results__lesson--overlap')
        expect(lessons[0].find('.timetable-v3-results__lesson-conflict-reference').exists()).toBe(true)
        expect(lessons[1].text()).toContain('M1')
        expect(lessons[1].text()).not.toContain('Einzeltermin-Überschneidung')
        expect(lessons[1].classes()).not.toContain('timetable-v3-results__lesson--overlap')
        expect(lessons[1].find('.timetable-v3-results__lesson-conflict-reference').exists()).toBe(false)
    })

    it('renders imported course aliases with the canonical module code', () => {
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                timetables: [timetableFixture({
                    key: 'canonical-module-labels',
                    number: 1,
                    slots: {
                        '1-2': slotFixture('GW2', 1, 2, {
                            sourceLabel: 'GWB2-5CK-HOA',
                        }),
                        '2-3': slotFixture('LPT', 2, 3, {
                            name: 'Literarisches Praktikum',
                            sourceLabel: 'LET-1U-HER',
                        }),
                    },
                    type: 'full_green',
                })],
            },
        })
        const entry = (wrapper.vm as any).timetableEntriesForCell(1, 2)[0]
        const lptEntry = (wrapper.vm as any).timetableEntriesForCell(2, 3)[0]

        expect(entry.code).toBe('GW2')
        expect(entry.sourceLabel).toBe('GW2-5CK-HOA')
        expect(wrapper.text()).toContain('GW2')
        expect(wrapper.text()).not.toContain('GWB2')
        expect(wrapper.find('.timetable-v3-results__lesson').attributes('aria-label')).not.toContain('GWB2')
        expect(lptEntry.code).toBe('LPT')
        expect(lptEntry.name).toBe('Lern- und Präsentationstechniken')
        expect(lptEntry.sourceLabel).toBe('LPT-1U-HER')
        expect(wrapper.findAll('.timetable-v3-results__lesson')[1].attributes('aria-label'))
            .toContain('Lern- und Präsentationstechniken')
    })

    it('hides the date range for courses covering the whole semester', () => {
        const fullSemesterSlot = slotFixture('D1', 1, 1, {
            courseGroup: {
                dates: ['2026-09-07', '2026-10-19'],
                ends_at: '18:35',
                hour: 1,
                is_full_semester: true,
                recurrence_label: '1-wöchig',
                starts_at: '17:50',
                weekday: 1,
            },
            dateRangeLabel: '7.9.–19.10.',
        })
        const blockSlot = slotFixture('CH1', 2, 2, {
            courseGroup: {
                dates: ['2026-09-30', '2026-10-07'],
                ends_at: '18:35',
                hour: 2,
                is_full_semester: false,
                recurrence_label: '1-wöchig',
                starts_at: '17:50',
                weekday: 2,
            },
            dateRangeLabel: '30.9.–7.10.',
        })
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                timetables: [timetableFixture({
                    key: 'date-ranges',
                    number: 1,
                    slots: {
                        '1-1': fullSemesterSlot,
                        '2-2': blockSlot,
                    },
                    type: 'full_green',
                })],
            },
        })

        expect((wrapper.vm as any).lessonDateLabel(fullSemesterSlot)).toBe('1-wöchig')
        expect((wrapper.vm as any).lessonDateLabel(blockSlot)).toBe('1-wöchig · 30.9.–7.10.')
        expect(wrapper.text()).not.toContain('7.9.–19.10.')
        expect(wrapper.text()).toContain('30.9.–7.10.')
    })

    it('shows Saturday and fills missing periods', () => {
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                allowSaturdayLessons: true,
                timetables: [
                    timetableFixture({
                        key: 'first',
                        number: 1,
                        slots: {
                            '1-1': slotFixture('D1', 1, 1),
                            '6-3': slotFixture('M1', 6, 3),
                        },
                        type: 'full_green',
                    }),
                    timetableFixture({
                        key: 'second',
                        number: 2,
                        slots: { '2-2': slotFixture('E1', 2, 2) },
                        type: 'full_green',
                    }),
                ],
            },
        })

        expect((wrapper.vm as any).visibleWeekdays.map((weekday: any) => weekday.shortTitle))
            .toEqual(['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'])
        expect((wrapper.vm as any).visibleHours).toEqual([1, 2, 3])
    })

    it('shows actual backend times beside each period and separates the timetable grid', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3PossibleTimetables.vue',
            'utf8',
        )
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                timetables: [timetableFixture({
                    key: 'evening',
                    number: 1,
                    slots: {
                        '1-8': slotFixture('D8', 1, 8, {
                            courseGroup: {
                                ends_at: '18:35',
                                hour: 8,
                                starts_at: '17:50',
                                weekday: 1,
                            },
                        }),
                        '2-9': slotFixture('M9', 2, 9, {
                            courseGroup: {
                                ends_at: '19:25',
                                hour: 9,
                                starts_at: '18:40',
                                weekday: 2,
                            },
                        }),
                    },
                    type: 'full_green',
                })],
            },
        })
        const periodCells = wrapper.findAll('.timetable-v3-results__period')

        expect((wrapper.vm as any).visibleHourRows).toEqual([
            { hour: 8, timeRange: '17:50–18:35' },
            { hour: 9, timeRange: '18:40–19:25' },
        ])
        expect(periodCells[0].text()).toContain('8.')
        expect(periodCells[0].find('.timetable-v3-results__period-time').text()).toBe('17:50–18:35')
        expect(periodCells[1].text()).toContain('9.')
        expect(periodCells[1].find('.timetable-v3-results__period-time').text()).toBe('18:40–19:25')
        expect(source).toContain('border: 1px solid #cbd5e1;')
        expect(source).toContain('border-right: 1px solid #dbe4ea;')
        expect(source).toContain('border-bottom: 1px solid #dbe4ea;')
    })

    it('navigates across page boundaries with global positions in both directions', async () => {
        const firstPage = rangeTimetables(1, 100)
        const secondPage = rangeTimetables(101, 200)
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                pageOffset: 0,
                selectedIndex: 99,
                timetables: firstPage,
                totalCount: 500,
            },
        })

        expect((wrapper.vm as any).selectedTimetable.key).toBe('timetable-100')
        expect(wrapper.text()).toContain('Stundenplan 100 von 500')
        ;(wrapper.vm as any).selectNextTimetable()
        expect(wrapper.emitted('navigate')).toEqual([[100]])

        await wrapper.setProps({
            pageOffset: 100,
            selectedIndex: 100,
            timetables: secondPage,
        })

        expect((wrapper.vm as any).selectedTimetable.key).toBe('timetable-101')
        expect(wrapper.text()).toContain('Stundenplan 101 von 500')
        ;(wrapper.vm as any).selectPreviousTimetable()
        expect(wrapper.emitted('navigate')).toEqual([[100], [99]])

        await wrapper.setProps({
            pageOffset: 0,
            selectedIndex: 99,
            timetables: firstPage,
        })

        expect((wrapper.vm as any).selectedTimetable.key).toBe('timetable-100')
        expect(wrapper.text()).toContain('Stundenplan 100 von 500')
    })

    it('keeps the current timetable visible while page navigation stays disabled during loading', () => {
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                error: 'Die nächsten Stundenpläne konnten nicht geladen werden.',
                loading: true,
                loadingDirection: 'next',
                selectedIndex: 99,
                timetables: rangeTimetables(1, 100),
                totalCount: 500,
            },
        })

        ;(wrapper.vm as any).selectNextTimetable()
        ;(wrapper.vm as any).selectPreviousTimetable()

        expect(wrapper.emitted('navigate')).toBeUndefined()
        expect(wrapper.text()).toContain('Die nächsten Stundenpläne konnten nicht geladen werden.')
        expect(wrapper.text()).toContain('Stundenplan 100 von 500')
        expect(wrapper.attributes('aria-busy')).toBe('true')
        expect(wrapper.find('.timetable-v3-results__table').exists()).toBe(true)
        expect(wrapper.find('.timetable-v3-results__page-loading').exists()).toBe(false)
    })

    it('selects a replacement page from the controlled global index', async () => {
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                selectedIndex: 1,
                timetables: rangeTimetables(1, 2),
                totalCount: 2,
            },
        })

        await wrapper.setProps({
            pageOffset: 2,
            selectedIndex: 2,
            timetables: rangeTimetables(3, 3),
            totalCount: 3,
        })

        expect((wrapper.vm as any).selectedTimetableIndex).toBe(0)
        expect((wrapper.vm as any).selectedTimetable.key).toBe('timetable-3')
    })

    it('fails clearly when the result list contains no displayable timetable', () => {
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: { timetables: [null, { slots: [] }] },
        })

        expect(wrapper.text()).toContain('Die Stundenpläne konnten nicht angezeigt werden.')
        expect(wrapper.find('.timetable-v3-results__table').exists()).toBe(false)
        expect(wrapper.find('.timetable-v3-results__navigation').exists()).toBe(false)
    })
})
