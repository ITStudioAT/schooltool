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
            ? 'Grüner Stundenplan mit Einzeltermin-Überschneidung'
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
            rooms: ['101'],
            starts_at: '17:50',
            teacher: 'Mag. Test',
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
        expect(wrapper.text()).toContain('Einzeltermin-Überschneidung')

        ;(wrapper.vm as any).selectNextTimetable()
        expect(wrapper.emitted('navigate')).toEqual([[1]])

        ;(wrapper.vm as any).selectPreviousTimetable()
        expect(wrapper.emitted('navigate')).toEqual([[1], [0]])
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
        expect(wrapper.text()).toContain('Einzeltermin-Überschneidung')
        expect(wrapper.text()).toContain('Fernunterricht')
        expect(wrapper.text()).toContain('17:50–18:35')
        expect(wrapper.text()).toContain('1-wöchig')
        expect(wrapper.text()).not.toContain('Mag. Test · Raum 101')
        expect(wrapper.findAll('.timetable-v3-results__lesson').some(lesson => (
            lesson.attributes('aria-label')?.includes('Mag. Test · Raum 101')
        ))).toBe(true)
        expect(wrapper.findAll('.timetable-v3-results__lesson')).toHaveLength(3)
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

    it('makes loading the next page range visible while navigation stays disabled', () => {
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
        expect(wrapper.text()).toContain('Stundenpläne 101–200 werden geladen …')
        expect(wrapper.text()).toContain('Der angezeigte Stundenplan wird gleich ersetzt.')
        expect(wrapper.attributes('aria-busy')).toBe('true')
        expect(wrapper.find('.timetable-v3-results__page-loading').attributes()).toMatchObject({
            'aria-atomic': 'true',
            'aria-live': 'polite',
            role: 'status',
        })
    })

    it('names the previous and final partial page ranges while loading', async () => {
        const wrapper = shallowMount(TimetableV3PossibleTimetables, {
            props: {
                loading: true,
                loadingDirection: 'previous',
                pageOffset: 100,
                selectedIndex: 100,
                timetables: rangeTimetables(101, 200),
                totalCount: 450,
            },
        })

        expect(wrapper.text()).toContain('Stundenpläne 1–100 werden geladen …')

        await wrapper.setProps({
            loadingDirection: 'next',
            pageOffset: 300,
            selectedIndex: 399,
            timetables: rangeTimetables(301, 400),
        })

        expect(wrapper.text()).toContain('Stundenpläne 401–450 werden geladen …')
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
