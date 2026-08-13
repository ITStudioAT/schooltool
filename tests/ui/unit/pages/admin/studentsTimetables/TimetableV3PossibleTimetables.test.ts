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
            props: { allowSaturdayLessons: true, timetables },
        })

        expect((wrapper.vm as any).selectedTimetable.key).toBe('full-green-1')
        expect(wrapper.text()).toContain('Stundenplan 1 von 2')
        expect(wrapper.text()).not.toContain('Einzeltermin-Überschneidung')

        ;(wrapper.vm as any).selectNextTimetable()
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).selectedTimetable.key).toBe('green-1')
        expect(wrapper.text()).toContain('Stundenplan 2 von 2')
        expect(wrapper.text()).toContain('Einzeltermin-Überschneidung')

        ;(wrapper.vm as any).selectNextTimetable()
        expect((wrapper.vm as any).selectedTimetableIndex).toBe(1)

        ;(wrapper.vm as any).selectPreviousTimetable()
        ;(wrapper.vm as any).selectPreviousTimetable()
        expect((wrapper.vm as any).selectedTimetableIndex).toBe(0)
    })

    it('renders every primary, same-slot, and allowed overlap Unterricht', () => {
        const slot = slotFixture('D5', 1, 2, {
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
        expect(wrapper.text()).toContain('17:50–18:35')
        expect(wrapper.text()).toContain('1-wöchig')
        expect(wrapper.text()).not.toContain('Mag. Test · Raum 101')
        expect(wrapper.findAll('.timetable-v3-results__lesson').some(lesson => (
            lesson.attributes('aria-label')?.includes('Mag. Test · Raum 101')
        ))).toBe(true)
        expect(wrapper.findAll('.timetable-v3-results__lesson')).toHaveLength(3)
    })

    it('shows Saturday, fills missing periods, and resets to the first plan when results change', async () => {
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

        ;(wrapper.vm as any).selectNextTimetable()
        expect((wrapper.vm as any).selectedTimetableIndex).toBe(1)

        await wrapper.setProps({
            timetables: [timetableFixture({
                key: 'replacement',
                number: 1,
                slots: { '3-4': slotFixture('F1', 3, 4) },
                type: 'full_green',
            })],
        })

        expect((wrapper.vm as any).selectedTimetableIndex).toBe(0)
        expect((wrapper.vm as any).selectedTimetable.key).toBe('replacement')
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
