import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createPinia, mapWritableState, setActivePinia } from 'pinia'
import { defineComponent, nextTick } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import MyTimetable from '@/pages/admin/teaching/overview/components/MyTimetable.vue'
import Teaching from '@/pages/admin/teaching/Teaching.vue'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'
import { useHolidayStore } from '@/stores/admin/teaching/HolidayStore'

vi.mock('@/pages/admin/teaching/overview/components/PersonalAppointments.vue', () => ({ default: { template: '<div />', methods: { open: vi.fn() } } }))

afterEach(() => {
    vi.useRealTimers()
})

describe('MyTimetable automatic week selection', () => {
    it.each([
        { adjacentCourse: 18, adjacentHour: 6, adjacentStatus: [], connects: true },
        { adjacentCourse: 99, adjacentHour: 6, adjacentStatus: [], connects: false },
        { adjacentCourse: 18, adjacentHour: 7, adjacentStatus: [], connects: false },
        { adjacentCourse: 18, adjacentHour: 6, adjacentStatus: ['free'], connects: false },
    ])('connects consecutive course blocks only when their course and status match: %j', ({ adjacentCourse, adjacentHour, adjacentStatus, connects }) => {
        const methods = (MyTimetable as any).methods
        const item = { courseId: 18, status: [] }
        const adjacent = { courseId: adjacentCourse, status: adjacentStatus }
        const context = {
            ...methods,
            getTableCellItems: (_day, hour) => hour === 5 ? [item] : hour === adjacentHour ? [adjacent] : [],
        }
        expect(methods.tableCellsConnect.call(context, '2026-10-12', 5, 6)).toBe(connects)
    })

    it('clears the next-lesson button selection while browsing and restores it on return', async () => {
        const { mountTimetable } = prepareTimetable('2026-03-06T16:00:00Z')
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            const vm = wrapper.vm as any
            vm.rangeSelection = 'next_week'
            expect(vm.rangeSelection).toBe('next_week')
            vm.navigateNext()
            await nextTick()
            expect(vm.rangeSelection).toBeNull()
            vm.navigatePrevious()
            await nextTick()
            expect(vm.rangeSelection).toBe('next_week')
            vm.navigateNext()
            vm.rangeSelection = 'next_week'
            await nextTick()
            expect(vm.offset).toBe(0)
            expect(vm.rangeSelection).toBe('next_week')
            vm.rangeSelection = 'week'
            expect(vm.rangeSelection).toBe('week')
        } finally {
            wrapper.unmount()
            sessionStorage.clear()
        }
    })
    function prepareTimetable(now: string) {
        sessionStorage.clear()
        setActivePinia(createPinia())
        vi.useFakeTimers()
        vi.setSystemTime(new Date(now))
        useAdminStore().config = { user: { id: 7 }, selected_schoolyear: { id: 4 } } as any
        useSchoolHourStore().school_hours = [
            { hour: 5, from: '11:30:00', until: '12:20:00' },
            { hour: 6, from: '12:25:00', until: '13:15:00' },
        ] as any
        const courseStore = useCourseStore()
        courseStore.courses = [{
            id: 18, user_id: 7, title: 'Mathematik', classes: ['2C'],
            course_dates: [
                { id: 1, date: '2026-03-06', hours: [5, 6] },
                { id: 2, date: '2026-03-13', hours: [5, 6], status: ['entfaellt'] },
                { id: 3, date: '2026-03-20', hours: [5, 6], status: ['free'] },
                { id: 4, date: '2026-04-10', hours: [5, 6] },
            ],
        }] as any
        const route = { path: '/admin/teaching', query: {} }
        const mountTimetable = () => mount(MyTimetable, {
            global: {
                mocks: { $route: route },
                stubs: { ItsGridBox: { template: '<div><slot /></div>' }, VDivider: true, VListItemTitle: true },
            },
        })
        return { courseStore, route, mountTimetable }
    }

    it('distinguishes recorded school holidays from other free days regardless of lessons and personal appointments', async () => {
        const { mountTimetable } = prepareTimetable('2026-03-04T12:00:00Z')
        useAdminStore().config.selected_school = { id: 1 } as any
        const holidayStore = useHolidayStore()
        let finishHolidays: () => void
        const loadHolidays = vi.spyOn(holidayStore, 'indexMine').mockImplementation(() => new Promise<boolean>((resolve) => {
            finishHolidays = () => {
                holidayStore.my_holidays = [
                    ...['02', '03', '04', '05', '06'].map(day => ({ date: `2026-03-${day}`, scope: 'school', reason: 'Ferien' })),
                    { date: '2026-03-11', scope: 'teacher', reason: 'Persönlicher freier Tag' },
                ] as any
                resolve(true)
            }
        }))
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            await wrapper.setData({ range: 'week', offset: 0 })
            const holidayDays = () => wrapper.findAll('th[title^="Ferien/Feiertag:"]')
                .map((header) => header.attributes('title'))
            expect(loadHolidays).toHaveBeenCalledOnce()
            expect(holidayDays()).toEqual([])
            finishHolidays!()
            await flushPromises()
            await wrapper.setData({ appointmentDefinitions: [
                { id: 7, kind: 'special_assignment', title: 'Abendgymnasium', date: '2026-03-05', starts_at: '18:00', ends_at: '19:00', repeat_until: null },
                { id: 8, kind: 'supplier_standby', title: 'Bereitschaft', date: '2026-03-03', starts_at: '12:00', ends_at: '13:00', repeat_until: null },
                { id: 9, kind: 'consultation', title: 'Sprechstunde', date: '2026-03-06', starts_at: '12:00', ends_at: '13:00', repeat_until: null },
                { id: 10, kind: 'break_supervision', title: 'Aufsicht', date: '2026-03-04', starts_at: '12:20', ends_at: '12:25', repeat_until: null },
            ] })
            expect(holidayDays()).toEqual(['Ferien/Feiertag: 02.03.', 'Ferien/Feiertag: 03.03.', 'Ferien/Feiertag: 04.03.', 'Ferien/Feiertag: 05.03.', 'Ferien/Feiertag: 06.03.'])
            expect(wrapper.text()).toContain('Abendgymnasium')
            await wrapper.setData({ offset: 1 })
            expect(holidayDays()).toEqual([])
            await wrapper.setData({ offset: 0 })
            holidayStore.my_holidays = []
            await nextTick()
            expect(holidayDays()).toEqual([])
        } finally {
            loadHolidays.mockRestore()
            wrapper.unmount()
            sessionStorage.clear()
        }
    })

    it('marks a completely free non-holiday day without treating partly cancelled lessons or standby as free', async () => {
        const { courseStore, mountTimetable } = prepareTimetable('2026-03-04T12:00:00Z')
        ;(courseStore.courses[0] as any).course_dates = [
            { id: 1, date: '2026-03-02', hours: [5, 6], cancelled_hours: [5, 6] },
            { id: 2, date: '2026-03-03', hours: [5, 6], cancelled_hours: [5] },
            { id: 3, date: '2026-03-04', hours: [5, 6], status: ['free'] },
            { id: 4, date: '2026-03-06', hours: [5, 6] },
        ]
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            await wrapper.setData({ range: 'week', offset: 0, appointmentDefinitions: [
                { id: 7, kind: 'special_assignment', title: 'Abendgymnasium', date: '2026-03-05', starts_at: '18:00', ends_at: '19:00', repeat_until: null },
            ] })
            const freeDays = () => wrapper.findAll('th[title^="Freier Tag:"]').map(header => header.attributes('title'))
            expect(freeDays()).toEqual(['Freier Tag: 02.03.', 'Freier Tag: 04.03.', 'Freier Tag: 05.03.'])
            expect(wrapper.find('th[title^="Ferien/Feiertag:"]').exists()).toBe(false)
            expect(wrapper.text()).toContain('Abendgymnasium')
            await wrapper.setData({ appointmentDefinitions: [
                { id: 8, kind: 'supplier_standby', date: '2026-03-05', starts_at: '12:00', ends_at: '13:00', repeat_until: null },
            ] })
            expect(freeDays()).toEqual(['Freier Tag: 02.03.', 'Freier Tag: 04.03.'])
        } finally {
            wrapper.unmount()
            sessionStorage.clear()
        }
    })

    it.each([[5, 6], [5, 6, 7]])('shows consecutive hours %j as one course block', async (...hours) => {
        const { courseStore, mountTimetable } = prepareTimetable('2026-03-04T12:00:00Z')
        ;(courseStore.courses[0] as any).course_dates[0].hours = hours
        useSchoolHourStore().school_hours = hours.map((hour) => ({ hour, from: `${hour + 6}:30:00`, until: `${hour + 7}:20:00` })) as any
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            expect(wrapper.findAll('.timetable-grid-course').map((item) => item.text())).toEqual(['Mathematik'])
            expect(wrapper.find('.timetable-grid-item--block').element.closest('td')?.getAttribute('rowspan')).toBe(String(hours.length))
            expect(wrapper.find('.timetable-grid-item--block').text()).toContain(`5.–${hours.at(-1)}. Std`)
        } finally {
            wrapper.unmount()
            sessionStorage.clear()
        }
    })

    it.each(['table', 'list'])('shows timed personal appointments without changing courses in the %s view', async (viewMode) => {
        const { courseStore, mountTimetable } = prepareTimetable('2026-03-04T12:00:00Z')
        courseStore.timetable_view_mode = viewMode
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            await wrapper.setData({ appointmentDefinitions: [
                { id: 7, kind: 'lunch_supervision', title: 'Mittagspause', date: '2026-03-06', starts_at: '12:00', ends_at: '12:30', repeat_until: null },
                { id: 8, kind: 'day_care_standby', title: '', date: '2026-03-07', starts_at: '18:00', ends_at: '20:00', repeat_until: null },
                { id: 9, kind: 'special_assignment', title: '', date: '2026-03-06', starts_at: '07:45', ends_at: '10:50', school_hours: [1, 3], time_segments: [{ hour: 1, starts_at: '07:45', ends_at: '08:35' }, { hour: 3, starts_at: '10:00', ends_at: '10:50' }], repeat_until: null },
            ] })
            expect(wrapper.findAll('.personal-appointment-card')).toHaveLength(viewMode === 'table' ? 4 : 3)
            wrapper.findAll('.personal-appointment-card').forEach((card) => {
                expect(card.find('.timetable-attendance-status, .timetable-content-status, .timetable-curriculum-status, .timetable-work-status').exists()).toBe(false)
            })
            expect(wrapper.text()).toContain('Mittagspause')
            expect(wrapper.text()).toContain('18:00–20:00')
            expect(wrapper.text()).toContain('1. Std · 07:45–08:35')
            expect(wrapper.text()).toContain('3. Std · 10:00–10:50')
            expect(wrapper.text()).toContain('Sondereinsatz')
            expect(wrapper.text()).not.toContain('07:45–10:50')
            expect(courseStore.courses[0].course_dates).toHaveLength(4)
            const open = vi.spyOn((wrapper.vm as any).$refs.personalAppointments, 'open')
            await wrapper.findAll('.personal-appointment-card').find((card) => card.text().includes('Sondereinsatz'))!.trigger('click')
            expect(open).toHaveBeenCalledWith(expect.objectContaining({ id: 9, school_hours: [1, 3] }))
            if (viewMode === 'table') expect(wrapper.findAll('th.timetable-day-header-cell').map((day) => day.text())).toContain('Sa07.03.')
        } finally { wrapper.unmount(); sessionStorage.clear() }
    })

    it.each(['table', 'list'])('renders a saved exception only on its own entry and hour in %s view', async (viewMode) => {
        const { courseStore, mountTimetable } = prepareTimetable('2026-03-04T12:00:00Z')
        courseStore.timetable_view_mode = viewMode
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            const definition = { id: 21, kind: 'consultation', title: 'Serie', date: '2026-03-06', repeat_until: '2026-04-10', school_hours: [5, 6], starts_at: '11:30', ends_at: '13:15', time_segments: [{ hour: 5, starts_at: '11:30', ends_at: '12:20' }, { hour: 6, starts_at: '12:25', ends_at: '13:15' }], title_exceptions: { '2026-03-06:6': 'Einzeltext' } }
            await wrapper.setData({ appointmentDefinitions: [definition, { ...definition, id: 22, title_exceptions: {} }] })
            expect(wrapper.findAll('.personal-appointment-card').filter((card) => card.text().includes('Einzeltext'))).toHaveLength(1)
            const open = vi.spyOn((wrapper.vm as any).$refs.personalAppointments, 'open')
            await wrapper.findAll('.personal-appointment-card').find((card) => card.text().includes('Einzeltext'))!.trigger('click')
            expect(open).toHaveBeenCalledWith(expect.objectContaining({ id: 21, occurrenceDate: '2026-03-06', occurrenceHour: 6 }))
            expect(wrapper.text()).toContain('Serie')
        } finally { wrapper.unmount(); sessionStorage.clear() }
    })

    it.each([[[1]], [[1, 2]], [[1, 3]]])('positions selected personal hours %j in their actual cells, joining only consecutive hours', async (hours) => {
        const { mountTimetable } = prepareTimetable('2026-03-04T12:00:00Z')
        const times = [{ hour: 1, from: '07:45', until: '08:35' }, { hour: 2, from: '08:40', until: '09:30' }, { hour: 3, from: '09:35', until: '10:25' }]
        useSchoolHourStore().school_hours = [...times, ...useSchoolHourStore().school_hours] as any
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            await wrapper.setData({ appointmentDefinitions: [{ id: 90, kind: 'supplier_standby', title: 'XSUP', date: '2026-03-03', starts_at: '07:45', ends_at: times[hours.at(-1) - 1].until, school_hours: hours,
                time_segments: times.filter((entry) => hours.includes(entry.hour)).map((entry) => ({ hour: entry.hour, starts_at: entry.from, ends_at: entry.until })), repeat_until: '2026-04-14' }] })
            const firstCell = wrapper.find('td[data-date="2026-03-03"][data-hour="1"]')
            expect(firstCell.text()).toContain('XSUP')
            expect(firstCell.attributes('rowspan')).toBe(hours.includes(2) ? '2' : '1')
            expect(wrapper.text()).not.toContain('Eigene Termine')
            expect(wrapper.text()).not.toContain('Vor Unterricht')
            if (hours.includes(3)) {
                expect(wrapper.find('td[data-date="2026-03-03"][data-hour="2"]').find('.personal-appointment-card').exists()).toBe(false)
                expect(wrapper.find('td[data-date="2026-03-03"][data-hour="3"]').text()).toContain('3. Std · 09:35–10:25')
            }
            const open = vi.spyOn((wrapper.vm as any).$refs.personalAppointments, 'open')
            await firstCell.find('.personal-appointment-card').trigger('click')
            expect(open).toHaveBeenCalledWith(expect.objectContaining({ id: 90, school_hours: hours, repeat_until: '2026-04-14' }))
        } finally { wrapper.unmount(); sessionStorage.clear() }
    })

    it('preserves a merged lesson and positions an overlapping personal appointment at its second hour', async () => {
        const { mountTimetable } = prepareTimetable('2026-03-04T12:00:00Z')
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            await wrapper.setData({ appointmentDefinitions: [{ id: 91, kind: 'break_supervision', title: '', date: '2026-03-06', starts_at: '12:25', ends_at: '13:15', school_hours: [6], time_segments: [{ hour: 6, starts_at: '12:25', ends_at: '13:15' }], repeat_until: null }] })
            const cell = wrapper.find('td[data-date="2026-03-06"][data-hour="5"]')
            expect(cell.attributes('rowspan')).toBe('2')
            expect(cell.findAll('.timetable-grid-course').map((entry) => entry.text())).toEqual(['Mathematik'])
            const card = cell.find('.personal-appointment-card')
            expect(card.text()).toContain('Pausenaufsicht')
            expect((card.element as HTMLElement).style.gridRow).toBe('2 / span 1')
        } finally { wrapper.unmount(); sessionStorage.clear() }
    })

    it('positions free times within the raster while keeping outside times visible', async () => {
        const { mountTimetable } = prepareTimetable('2026-03-04T12:00:00Z')
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            await wrapper.setData({ appointmentDefinitions: [
                { id: 92, kind: 'consultation', title: '', date: '2026-03-06', starts_at: '11:40', ends_at: '12:40', repeat_until: null },
                { id: 93, kind: 'day_care_standby', title: '', date: '2026-03-06', starts_at: '18:00', ends_at: '20:00', repeat_until: null },
            ] })
            const cell = wrapper.find('td[data-date="2026-03-06"][data-hour="5"]')
            expect(cell.text()).toContain('Sprechstunde')
            expect(cell.text()).toContain('11:40–12:40')
            expect(cell.text()).not.toContain('Tagesbetreuung')
            const outside = wrapper.findAll('tr.timetable-gap-row').find((row) => row.text().includes('Tagesbetreuung'))!
            expect(outside.text()).not.toContain('Nach Unterricht')
            expect(outside.text()).toContain('Tagesbetreuung')
            expect(outside.text()).toContain('18:00–20:00')
            expect(outside.text()).not.toContain('Sprechstunde')
            const placement = (wrapper.vm as any).gapAppointmentsOnDay(new Date(2026, 2, 6), 6).find((entry: any) => entry.appointment.id === 93)
            expect(placement.gapMinutes).toBe(285)
        } finally { wrapper.unmount(); sessionStorage.clear() }
    })

    it('places early midday and afternoon free times in chronological additional rows', async () => {
        const { mountTimetable } = prepareTimetable('2026-03-04T12:00:00Z')
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            await wrapper.setData({ appointmentDefinitions: [
                { id: 94, kind: 'standby', title: 'Früh', date: '2026-03-03', starts_at: '07:00', ends_at: '08:00', repeat_until: null },
                { id: 95, kind: 'break_supervision', title: 'Pause', date: '2026-03-03', starts_at: '12:20', ends_at: '12:25', repeat_until: null },
                { id: 96, kind: 'day_care_standby', title: 'Spät', date: '2026-03-03', starts_at: '18:00', ends_at: '20:00', repeat_until: null },
            ] })
            const rows = wrapper.findAll('tbody tr')
            const early = rows.findIndex((row) => row.text().includes('Früh'))
            const hour5 = rows.findIndex((row) => row.attributes('data-hour') === '5')
            const pause = rows.findIndex((row) => row.attributes('data-gap-after') === '5')
            const hour6 = rows.findIndex((row) => row.attributes('data-hour') === '6')
            const late = rows.findIndex((row) => row.text().includes('Spät'))
            expect(early).toBeLessThan(hour5)
            expect(pause).toBeGreaterThan(hour5)
            expect(pause).toBeLessThan(hour6)
            expect(rows[pause].text()).toContain('12:20–12:25')
            expect(late).toBeGreaterThan(hour6)
        } finally { wrapper.unmount(); sessionStorage.clear() }
    })

    it('preserves course rowspans and places pause appointments inside their actual intervening row', async () => {
        const { mountTimetable } = prepareTimetable('2026-03-04T12:00:00Z')
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            await wrapper.setData({ appointmentDefinitions: [{ id: 97, kind: 'break_supervision', title: 'Pausenaufsicht', date: '2026-03-06', starts_at: '12:20', ends_at: '12:25', repeat_until: null }] })
            const cell = wrapper.find('td[data-date="2026-03-06"][data-hour="5"]')
            expect(cell.attributes('rowspan')).toBe('3')
            expect(cell.findAll('.timetable-grid-course')).toHaveLength(1)
            const card = cell.find('.personal-appointment-card')
            expect(card.text()).toContain('12:20–12:25')
            expect((card.element as HTMLElement).style.gridRow).toBe('2 / span 1')
            expect(wrapper.find('tr[data-gap-after="5"]').findAll('td[data-date="2026-03-06"]')).toHaveLength(0)
        } finally { wrapper.unmount(); sessionStorage.clear() }
    })

    it.each([
        { now: '2026-03-04T12:00:00Z', range: 'week', date: '2026-03-06' },
        { now: '2026-03-06T16:00:00Z', range: 'week', date: '2026-03-06' },
        { now: '2026-03-06T22:59:59Z', range: 'week', date: '2026-03-06' },
        { now: '2026-03-06T23:00:00Z', range: 'next_week', date: '2026-04-10' },
        { now: '2026-03-08T23:00:00Z', range: 'next_week', date: '2026-04-10' },
        { now: '2026-04-10T21:59:59Z', range: 'week', date: '2026-04-10' },
        { now: '2026-04-10T22:00:00Z', range: 'next_week', date: null },
    ])('selects $range at $now in Vienna calendar time', async ({ now, range, date }) => {
        const { mountTimetable } = prepareTimetable(now)
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            expect((wrapper.vm as any).range).toBe(range)
            expect((wrapper.vm as any).filteredItems.map((item: any) => item.date)).toEqual(date ? [date] : [])
            if (range === 'week' && !now.startsWith('2026-03-04')) {
                expect(wrapper.find('.day-today').exists()).toBe(true)
                expect(wrapper.find('.timetable-item--upcoming').exists()).toBe(false)
            }
        } finally {
            wrapper.unmount()
            sessionStorage.clear()
        }
    })

    it.each([
        { now: '2026-03-04T12:00:00Z', date: '2026-03-06', label: 'Nächster Unterrichtstag: 06.03.' },
        { now: '2026-03-06T22:59:59Z', date: '2026-03-06', label: 'Heutiger Unterrichtstag: 06.03.' },
        { now: '2026-03-06T23:00:00Z', date: '2026-04-10', label: 'Nächster Unterrichtstag: 10.04.' },
        { now: '2026-03-13T12:00:00Z', date: '2026-04-10', label: 'Nächster Unterrichtstag: 10.04.' },
        { now: '2026-04-10T22:00:00Z', date: null, label: null },
    ])('marks the actual teaching day $date at $now', async ({ now, date, label }) => {
        const { mountTimetable } = prepareTimetable(now)
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            expect((wrapper.vm as any).highlightedTeachingDate).toBe(date)
            const markedHeaders = wrapper.findAll('th[aria-label]')
            expect(markedHeaders.map((header) => header.attributes('aria-label'))).toEqual(label ? [label] : [])
            await wrapper.setData({ offset: -1 })
            expect(wrapper.findAll('th[aria-label]')).toHaveLength(0)
        } finally {
            wrapper.unmount()
            sessionStorage.clear()
        }
    })

    describe.each(['table', 'list'])('lesson time marking in %s view', (viewMode) => {
        it.each([
            { now: '2026-03-04T12:00:00Z', remaining: 2 },
            { now: '2026-03-06T10:29:59Z', remaining: 2 },
            { now: '2026-03-06T10:30:00Z', remaining: 2 },
            { now: '2026-03-06T11:19:59Z', remaining: 2 },
            { now: '2026-03-06T11:20:00Z', remaining: 1 },
            { now: '2026-03-06T11:24:59Z', remaining: 1 },
            { now: '2026-03-06T11:25:00Z', remaining: 1 },
            { now: '2026-03-06T12:14:59Z', remaining: 1 },
            { now: '2026-03-06T12:15:00Z', remaining: 0 },
            { now: '2026-04-10T09:30:00Z', remaining: 2 },
            { now: '2026-04-10T11:15:00Z', remaining: 0 },
        ])('marks only unfinished lessons at $now in Vienna time', async ({ now, remaining }) => {
            const { courseStore, mountTimetable } = prepareTimetable(now)
            courseStore.timetable_view_mode = viewMode
            const wrapper = mountTimetable()
            try {
                await flushPromises()
                expect(wrapper.findAll('.timetable-item--upcoming')).toHaveLength(
                    Number(remaining > 0),
                )
            } finally {
                wrapper.unmount()
                sessionStorage.clear()
            }
        })
    })

    it('removes the final lesson marker at its end while the overview remains open', async () => {
        const { mountTimetable } = prepareTimetable('2026-03-06T12:14:59Z')
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            expect(wrapper.findAll('.timetable-item--upcoming')).toHaveLength(1)
            await vi.advanceTimersByTimeAsync(1000)
            expect(wrapper.findAll('.timetable-item--upcoming')).toHaveLength(0)
            expect(wrapper.get('th[aria-label]').attributes('aria-label')).toBe('Heutiger Unterrichtstag: 06.03.')
        } finally {
            wrapper.unmount()
            sessionStorage.clear()
        }
    })

    it.each([[[5]], [[6]], [[5, 6]]])('keeps cancelled hours %j separate from active blocks and selects only actual teaching days', async (cancelledHours) => {
        const { courseStore, mountTimetable } = prepareTimetable('2026-03-06T10:29:59Z')
        ;(courseStore.courses[0] as any).course_dates[0].cancelled_hours = cancelledHours
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            await wrapper.setData({ range: 'week' })
            expect(wrapper.findAll('.timetable-grid-item.timetable-item--free')).toHaveLength(1)
            expect(wrapper.findAll('.timetable-item--upcoming')).toHaveLength(Number(cancelledHours.length < 2))
            expect(wrapper.find('.timetable-grid-item.timetable-item--free').text()).toContain('Entfallen')
            expect((wrapper.vm as any).highlightedTeachingDate).toBe(cancelledHours.length === 2 ? '2026-04-10' : '2026-03-06')
        } finally {
            wrapper.unmount()
            sessionStorage.clear()
        }
    })

    it('does not invent lesson times when school hours are missing', async () => {
        const { mountTimetable } = prepareTimetable('2026-03-06T10:29:59Z')
        useSchoolHourStore().school_hours = [{ hour: 5 }, { hour: 6 }] as any
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            expect(wrapper.findAll('.timetable-item--upcoming')).toHaveLength(0)
        } finally {
            wrapper.unmount()
            sessionStorage.clear()
        }
    })

    it.each(['frei', 'free', 'entfaellt', 'entfällt', 'entfallen'])('skips %s dates as selection targets while preserving their display', async (status) => {
        const { courseStore, mountTimetable } = prepareTimetable('2026-03-06T16:00:00Z')
        ;(courseStore.courses[0] as any).course_dates[0].status = [status]
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            expect((wrapper.vm as any).range).toBe('next_week')
            expect((wrapper.vm as any).filteredItems[0].date).toBe('2026-04-10')
            expect(wrapper.get('th[aria-label]').attributes('aria-label')).toBe('Nächster Unterrichtstag: 10.04.')
            await wrapper.setData({ range: 'week' })
            expect((wrapper.vm as any).filteredItems[0].date).toBe('2026-03-06')
            expect(wrapper.find('.timetable-item--free').exists()).toBe(true)
            expect(wrapper.find('.timetable-item--upcoming').exists()).toBe(false)
        } finally {
            wrapper.unmount()
            sessionStorage.clear()
        }
    })

    it.each([false, true])('selects after course loading and respects manual selection: %s', async (manualSelection) => {
        const { courseStore, mountTimetable } = prepareTimetable('2026-03-06T16:00:00Z')
        const courses = courseStore.courses
        courseStore.courses = []
        let resolveCourses: (value: boolean) => void
        courseStore.courses_request_promise = new Promise((resolve) => { resolveCourses = resolve })
        const wrapper = mountTimetable()
        try {
            if (manualSelection) await wrapper.setData({ range: 'month' })
            courseStore.courses = courses
            resolveCourses!(true)
            await flushPromises()
            expect((wrapper.vm as any).range).toBe(manualSelection ? 'month' : 'week')
        } finally {
            wrapper.unmount()
            sessionStorage.clear()
        }
    })

    it('expires both saved views and course return state at Vienna midnight', async () => {
        const { courseStore, route, mountTimetable } = prepareTimetable('2026-03-06T22:59:59Z')
        courseStore.rememberTimetableReturn({
            path: route.path, query: route.query, range: 'week', offset: 0,
            viewMode: 'table', left: 0, top: 0, tableLeft: 0, tableTop: 0,
        })
        expect(courseStore.getTimetableReturn(route.path)).not.toBeNull()
        expect(courseStore.getTimetableView(route.path, route.query)).not.toBeNull()
        vi.setSystemTime(new Date('2026-03-06T23:00:00Z'))
        expect(courseStore.getTimetableReturn(route.path)).toBeNull()
        expect(courseStore.getTimetableView(route.path, route.query)).toBeNull()
        const wrapper = mountTimetable()
        try {
            await flushPromises()
            expect((wrapper.vm as any).range).toBe('next_week')
            expect((wrapper.vm as any).filteredItems[0].date).toBe('2026-04-10')
        } finally {
            wrapper.unmount()
            sessionStorage.clear()
        }
    })

    it('rechecks saved view validity when course loading crosses Vienna midnight', async () => {
        const { courseStore, route, mountTimetable } = prepareTimetable('2026-03-06T22:59:59Z')
        courseStore.rememberTimetableView({
            path: route.path, query: route.query, range: 'week', offset: -1,
            viewMode: 'table', left: 0, top: 0, tableLeft: 0, tableTop: 0,
        })
        let resolveCourses: (value: boolean) => void
        courseStore.courses_request_promise = new Promise((resolve) => { resolveCourses = resolve })
        const wrapper = mountTimetable()
        try {
            expect((wrapper.vm as any).offset).toBe(-1)
            vi.setSystemTime(new Date('2026-03-06T23:00:00Z'))
            resolveCourses!(true)
            await flushPromises()
            expect((wrapper.vm as any).range).toBe('next_week')
            expect((wrapper.vm as any).offset).toBe(0)
            expect((wrapper.vm as any).filteredItems[0].date).toBe('2026-04-10')
        } finally {
            wrapper.unmount()
            sessionStorage.clear()
        }
    })
})

describe.each(['list', 'table'])('MyTimetable lesson statuses without attachment indicators in %s view', (viewMode) => {
    it('colors scheduled group work yesterday and today red and tomorrow orange', async () => {
        sessionStorage.clear()
        setActivePinia(createPinia())
        vi.useFakeTimers()
        vi.setSystemTime(new Date('2026-09-29T10:00:00Z'))
        useAdminStore().config = { user: { id: 7 } } as any
        useSchoolHourStore().school_hours = [{ hour: 1 }] as any
        const dates = ['2026-09-28', '2026-09-29', '2026-09-30']
        const store = useCourseStore()
        store.timetable_view_mode = viewMode
        store.courses = [{ id: 28, user_id: 7, title: 'INF 2', course_dates: dates.map((date, index) => ({
            id: index + 1, date, hours: [1], has_curriculum_assignment: true,
        })) }] as any
        const works = useCourseWorkStore()
        works.loadedCourseId = 28
        works.courseWorks = [{ id: 66, teaching_course_id: 28, is_group_work: true,
            date_for_all_groups: '2026-10-01', groups: dates.map((date) => ({ date })) }] as any
        const wrapper = mount(MyTimetable, { global: { stubs: {
            ItsGridBox: { template: '<div><slot /></div>' }, VDivider: true, VListItemTitle: true,
        } } })
        try {
            await wrapper.setData({ range: 'week' })
            const icons = wrapper.findAll('.timetable-work-status')
            expect(icons.map((icon) => icon.attributes('icon'))).toEqual(['mdi-brain', 'mdi-brain', 'mdi-brain'])
            expect(icons.map((icon) => icon.attributes('color'))).toEqual(['error', 'error', 'orange'])
            expect(wrapper.findAll('.timetable-curriculum-status').map((icon) => icon.attributes('color')))
                .toEqual(['deep-purple', 'deep-purple', 'deep-purple'])
        } finally { wrapper.unmount() }
    })

    it.each([
        { label: 'initial mixed summary', complete: true, counts: { present: 11, absent: 1 }, visible: true },
        { label: 'all present summary', complete: true, counts: { present: 12, absent: 0 }, visible: true },
        { label: 'all absent summary', complete: true, counts: { present: 0, absent: 12 }, visible: true },
        { label: 'incomplete summary', complete: false, counts: { present: 11, absent: 1 }, visible: false },
        { label: 'missing counts', complete: true, counts: null, visible: false },
        { label: 'invalid counts', complete: true, counts: { present: -1, absent: 13 }, visible: false },
        { label: 'empty roster', complete: true, counts: { present: 0, absent: 0 }, visible: false },
    ])('shows attendance counts from the initial summary only when complete: $label', async ({ complete, counts, visible }) => {
        sessionStorage.clear()
        setActivePinia(createPinia())
        vi.useFakeTimers()
        vi.setSystemTime(new Date(2026, 8, 29, 12))
        useAdminStore().config = { user: { id: 7 } } as any
        useSchoolHourStore().school_hours = [11, 12, 13].map((hour) => ({ hour })) as any
        const store = useCourseStore()
        store.timetable_view_mode = viewMode
        store.courses = [{ id: 28, user_id: 7, title: 'INF 2', course_dates: [{
            id: 724, date: '2026-09-29', hours: [11, 12, 13], attendance_complete: complete, attendance_counts: counts,
        }] }] as any
        const wrapper = mount(MyTimetable, { global: { stubs: {
            ItsGridBox: { template: '<div><slot /></div>' }, VDivider: true, VListItemTitle: true,
        } } })
        try {
            await wrapper.setData({ range: 'week' })
            expect(wrapper.findAll('.timetable-attendance-counts')).toHaveLength(visible ? 1 : 0)
            if (visible) {
                expect(wrapper.get('.timetable-attendance-counts .text-success').text()).toBe(String(counts?.present))
                expect(wrapper.get('.timetable-attendance-counts .text-error').text()).toBe(String(counts?.absent))
            }
            const date = store.courses[0].course_dates[0] as any
            date.cancelled_hours = [11, 12, 13]
            await nextTick()
            expect(wrapper.find('.timetable-attendance-counts').exists()).toBe(false)
        } finally { wrapper.unmount() }
    })

    it.each([
        { label: 'initial summary date', workCourseId: 99, works: [], scheduled: true },
        { label: 'individual common date', workCourseId: 28, works: [{ date_for_all_groups: '2026-09-29' }], scheduled: true },
        { label: 'other date', workCourseId: 28, works: [{ date_for_all_groups: '2026-10-06' }], scheduled: false },
        { label: 'group explicit date', workCourseId: 28, works: [{ is_group_work: true, groups: [{ date: '2026-09-29' }] }], scheduled: true },
        { label: 'group explicit dates override common date', workCourseId: 28, works: [{ is_group_work: true, date_for_all_groups: '2026-09-29', groups: [{ date: '2026-10-06' }] }], scheduled: false },
        { label: 'group inherits common date', workCourseId: 28, works: [{ is_group_work: true, date_for_all_groups: '2026-09-29', groups: [{ date: '2026-10-06' }, { date: null }] }], scheduled: true },
        { label: 'individual common date remains assigned', workCourseId: 28, works: [{ is_group_work: false, date_for_all_groups: '2026-09-29', groups: [{ date: '2026-10-06' }] }], scheduled: true },
        { label: 'another course work', workCourseId: 28, works: [{ teaching_course_id: 99, date_for_all_groups: '2026-09-29' }], scheduled: false },
        { label: 'deadline and creation date alone', workCourseId: 28, works: [{ finish_until_date: '2026-09-29', created_at: '2026-09-29' }], scheduled: false },
        { label: 'loaded empty list clears stale summary', workCourseId: 28, works: [], scheduled: false },
    ])('shows the brain icon only for a scheduled course work: $label', async ({ workCourseId, works, scheduled }) => {
        sessionStorage.clear()
        setActivePinia(createPinia())
        vi.useFakeTimers()
        vi.setSystemTime(new Date(2026, 8, 29, 12))
        useAdminStore().config = { user: { id: 7 } } as any
        useSchoolHourStore().school_hours = [{ hour: 1 }] as any
        const store = useCourseStore()
        const workStore = useCourseWorkStore()
        workStore.loadedCourseId = workCourseId
        workStore.courseWorks = works as any
        store.timetable_view_mode = viewMode
        store.courses = [{ id: 28, user_id: 7, title: 'INF 2', scheduled_work_dates: ['2026-09-29'], course_dates: [{
            id: 724, date: '2026-09-29', hours: [1], attendance_complete: true,
        }] }] as any
        const wrapper = mount(MyTimetable, { global: { stubs: {
            ItsGridBox: { template: '<div><slot /></div>' }, VDivider: true, VListItemTitle: true,
        } } })
        try {
            await wrapper.setData({ range: 'week' })
            expect(wrapper.findAll('.timetable-work-status')).toHaveLength(scheduled ? 1 : 0)
            if (scheduled) expect(wrapper.get('.timetable-work-status').attributes()).toMatchObject({
                icon: 'mdi-brain', 'aria-label': 'Arbeit vorgesehen', title: 'Arbeit vorgesehen',
            })
            workStore.loadedCourseId = 28
            workStore.courseWorks = [{ id: 66, teaching_course_id: 28, is_group_work: true,
                groups: [{ date: '2026-09-29' }, { date: '2026-10-06' }] }] as any
            await nextTick()
            expect(wrapper.findAll('.timetable-work-status')).toHaveLength(1)
            ;(store.courses[0].course_dates[0] as any).cancelled_hours = [1]
            await nextTick()
            expect(wrapper.find('.timetable-work-status').exists()).toBe(false)
            ;(store.courses[0].course_dates[0] as any).cancelled_hours = []
            workStore.courseWorks = []
            await wrapper.setData({ range: 'week' })
            expect(wrapper.find('.timetable-work-status').exists()).toBe(false)
            expect(wrapper.get('.timetable-attendance-status').attributes('icon')).toBe('mdi-check')
        } finally { wrapper.unmount() }
    })

    it.each([
        { label: 'summary assignment', date: { has_curriculum_assignment: true }, assigned: true },
        { label: 'unassigned summary', date: { has_curriculum_assignment: false }, assigned: false },
        { label: 'adopted curriculum entry', date: { adopted_materials: [{ id: 9, title: 'Thema: Einheit' }] }, assigned: true },
        { label: 'course curriculum alone', date: {}, assigned: false },
        { label: 'empty detail', date: { adopted_materials: [] }, assigned: false },
        { label: 'attachments alone', date: { shared_curriculum_attachments_count: 2 }, assigned: false },
        { label: 'explicit summary overrides stale details', date: { has_curriculum_assignment: false, adopted_materials: [{ id: 9 }] }, assigned: false },
        { label: 'cancelled lesson', date: { has_curriculum_assignment: true, cancelled_hours: [1] }, assigned: false },
    ])('shows the existing course detail Curriculum icon only for a dated assignment: $label', async ({ date, assigned }) => {
        sessionStorage.clear()
        setActivePinia(createPinia())
        vi.useFakeTimers()
        vi.setSystemTime(new Date(2026, 8, 29, 12))
        useAdminStore().config = { user: { id: 7 } } as any
        useSchoolHourStore().school_hours = [{ hour: 1 }] as any
        const store = useCourseStore()
        store.timetable_view_mode = viewMode
        store.courses = [{ id: 28, user_id: 7, title: 'INF 2', teaching_curriculum_id: 9, course_dates: [{
            id: 724, date: '2026-09-29', hours: [1], attendance_complete: true, ...date,
        }] }] as any
        const wrapper = mount(MyTimetable, { global: { stubs: {
            ItsGridBox: { template: '<div><slot /></div>' }, VDivider: true, VListItemTitle: true,
        } } })
        try {
            await wrapper.setData({ range: 'week' })
            expect(wrapper.findAll('.timetable-curriculum-status')).toHaveLength(assigned ? 1 : 0)
            if (assigned) {
                expect(wrapper.get('.timetable-curriculum-status').attributes()).toMatchObject({
                    icon: 'mdi-book-education-outline', color: 'deep-purple',
                    'aria-label': 'Curriculum-Eintrag zugeordnet', title: 'Curriculum-Eintrag zugeordnet',
                })
            }
            const courseDate = store.courses[0].course_dates[0] as any
            courseDate.cancelled_hours = []
            courseDate.has_curriculum_assignment = true
            await nextTick()
            expect(wrapper.findAll('.timetable-curriculum-status')).toHaveLength(1)
            expect(wrapper.get('.timetable-content-status').attributes('color')).toBe('warning')
            ;(useAdminStore().config!.user as any).teaching_curricula_enabled = false
            await nextTick()
            expect(wrapper.findAll('.timetable-curriculum-status')).toHaveLength(0)
            ;(useAdminStore().config!.user as any).teaching_curricula_enabled = true
            ;(store.courses[0] as any).curricula_enabled = false
            await nextTick()
            expect(wrapper.findAll('.timetable-curriculum-status')).toHaveLength(0)
            ;(store.courses[0] as any).curricula_enabled = true
            await nextTick()
            expect(wrapper.findAll('.timetable-curriculum-status')).toHaveLength(1)
            courseDate.has_curriculum_assignment = false
            await nextTick()
            expect(wrapper.findAll('.timetable-curriculum-status')).toHaveLength(0)
            expect(wrapper.get('.timetable-attendance-status').attributes('icon')).toBe('mdi-check')
        } finally { wrapper.unmount() }
    })

    it.each([
        { label: 'manual text', content: 'Dateiverwaltung', entered: true },
        { label: 'rich text', content: '<p><strong>Dateiverwaltung</strong></p>', entered: true },
        { label: 'saved curriculum text', content: '<p>Ordnerstruktur / Dateien benennen</p>', entered: true },
        { label: 'missing content', content: undefined, entered: false },
        { label: 'null content', content: null, entered: false },
        { label: 'empty content', content: '', entered: false },
        { label: 'whitespace', content: ' \n\t ', entered: false },
        { label: 'empty editor', content: '<p><br></p>', entered: false },
        { label: 'HTML whitespace', content: '<p>&nbsp;&#160;</p>', entered: false },
        { label: 'invisible editor content', content: '<p>\u200B</p><!-- empty -->', entered: false },
    ])('shows an independent lesson content icon for $label and reacts to saved content', async ({ content, entered }) => {
        sessionStorage.clear()
        setActivePinia(createPinia())
        vi.useFakeTimers()
        vi.setSystemTime(new Date(2026, 8, 29, 12))
        useAdminStore().config = { user: { id: 7 } } as any
        useSchoolHourStore().school_hours = [11, 12, 13].map((hour) => ({ hour })) as any
        const store = useCourseStore()
        store.timetable_view_mode = viewMode
        store.courses = [{ id: 28, user_id: 7, title: 'INF 2', course_dates: [{
            id: 724, date: '2026-09-29', hours: [11, 12, 13], cancelled_hours: [12], content,
            attendance_complete: true, has_curriculum_assignment: true,
            adopted_materials: [{ title: 'Thema: Nur verknüpfter Curriculum-Inhalt', attachments: [{ id: 1 }] }],
        }] }] as any
        const wrapper = mount(MyTimetable, { global: { stubs: {
            ItsGridBox: { template: '<div><slot /></div>' }, VDivider: true, VListItemTitle: true,
        } } })
        try {
            await wrapper.setData({ range: 'week' })
            const icons = wrapper.findAll('.timetable-content-status')
            expect(icons).toHaveLength(viewMode === 'list' ? 1 : 2)
            icons.forEach((icon) => {
                expect(icon.attributes()).toMatchObject({ icon: 'mdi-text-box-outline', color: entered ? 'success' : 'warning',
                    title: entered ? 'Unterrichtsstoff eingetragen' : 'Unterrichtsstoff noch nicht eingetragen',
                    'aria-label': entered ? 'Unterrichtsstoff eingetragen' : 'Unterrichtsstoff noch nicht eingetragen' })
                expect(icon.element.parentElement?.querySelector('.timetable-attendance-status')).not.toBeNull()
            })
            const date = store.courses[0].course_dates[0] as any
            date.content = '<p>Gespeicherter Stoff</p>'
            await nextTick()
            expect(wrapper.get('.timetable-content-status').attributes('color')).toBe('success')
            date.content = '<p><br></p>'
            await nextTick()
            expect(wrapper.get('.timetable-content-status').attributes('color')).toBe('warning')
            expect(wrapper.get('.timetable-attendance-status').attributes('icon')).toBe('mdi-check')
            expect(wrapper.find('[icon="mdi-eye"], [icon="mdi-eye-off"]').exists()).toBe(false)
        } finally { wrapper.unmount() }
    })

    it.each([
        { label: 'all present', attendance: { s_301: true, s_302: true }, complete: true, present: 2, absent: 0 },
        { label: 'mixed', attendance: { s_301: true, s_302: false }, complete: true, present: 1, absent: 1 },
        { label: 'all absent', attendance: { s_301: false, s_302: false }, complete: true, present: 0, absent: 2 },
        { label: 'encoded states', attendance: { s_301: '1', s_302: '0' }, complete: true, present: 1, absent: 1 },
        { label: 'numeric states', attendance: { '301': 1, '302': 0 }, complete: true, present: 1, absent: 1 },
        { label: 'text states', attendance: { s_301: 'true', s_302: 'false' }, complete: true, present: 1, absent: 1 },
        { label: 'missing cell', attendance: { s_301: true, s_999: true }, complete: false },
        { label: 'empty cell', attendance: { s_301: false, s_302: null }, complete: false },
        { label: 'invalid cell', attendance: { s_301: true, s_302: 'unknown' }, complete: false },
        { label: 'empty map', attendance: {}, complete: false },
    ])('shows INF 2 on 29 September from actual attendance cells: $label', async ({ attendance, complete, present, absent }) => {
        sessionStorage.clear()
        setActivePinia(createPinia())
        vi.useFakeTimers()
        vi.setSystemTime(new Date(2026, 8, 29, 12))
        useAdminStore().config = { user: { id: 7 } } as any
        useSchoolHourStore().school_hours = [11, 12, 13].map((hour) => ({ hour })) as any
        const store = useCourseStore()
        store.timetable_view_mode = viewMode
        store.courses = [{ id: 28, user_id: 7, title: 'INF 2', students_info: [
            { id: 301 }, { id: 302 }, { id: 301 }, { id: 303, canceled_at: '2026-09-01' }, { id: 304, deleted_at: '2026-09-01' },
        ], course_dates: [{ id: 724, date: '2026-09-29', hours: [11, 12, 13], cancelled_hours: [12],
            attendance, attendance_checked: !complete, attendance_complete: !complete }] }] as any
        const wrapper = mount(MyTimetable, { global: { stubs: {
            ItsGridBox: { template: '<div><slot /></div>' }, VDivider: true, VListItemTitle: true,
        } } })
        try {
            await wrapper.setData({ range: 'week' })
            const icons = wrapper.findAll('.timetable-attendance-status')
            expect(icons).toHaveLength(viewMode === 'list' ? 1 : 2)
            icons.forEach((icon) => expect(icon.attributes()).toMatchObject({
                icon: complete ? 'mdi-check' : 'mdi-alert-circle-outline', color: complete ? 'success' : 'warning',
            }))
            const counts = wrapper.findAll('.timetable-attendance-counts')
            expect(counts).toHaveLength(complete ? (viewMode === 'list' ? 1 : 2) : 0)
            counts.forEach((footer) => {
                expect(footer.get('.text-success').text()).toBe(String(present))
                expect(footer.get('.text-error').text()).toBe(String(absent))
            })
            const date = store.courses[0].course_dates[0] as any
            date.attendance = { s_301: true, s_302: false }
            await nextTick()
            expect(wrapper.get('.timetable-attendance-status').attributes('icon')).toBe('mdi-check')
            expect(wrapper.get('.timetable-attendance-counts').attributes('aria-label')).toBe('1 anwesend / 1 abwesend')
            date.attendance.s_302 = null
            await nextTick()
            expect(wrapper.get('.timetable-attendance-status').attributes('icon')).toBe('mdi-alert-circle-outline')
            expect(wrapper.find('.timetable-attendance-counts').exists()).toBe(false)
        } finally { wrapper.unmount() }
    })

    it('keeps lesson navigation and independent attendance, content and curriculum symbols', async () => {
        sessionStorage.clear()
        setActivePinia(createPinia())
        vi.useFakeTimers()
        vi.setSystemTime(new Date(2026, 2, 2, 12))
        useAdminStore().config = { user: { id: 7 } } as any
        useSchoolHourStore().school_hours = [{ hour: 1 }, { hour: 2 }] as any
        const courseStore = useCourseStore()
        courseStore.timetable_view_mode = viewMode
        courseStore.courses = [{
            id: 18, user_id: 7, title: 'Deutsch', classes: ['1A'], teaching_curriculum_id: 9,
            course_dates: [
                { id: 1, date: '2026-03-02', hours: [1, 2], attendance_complete: true,
                    has_curriculum_assignment: true, shared_curriculum_attachments_count: 1, private_curriculum_attachments_count: 2 },
                { id: 2, date: '2026-03-03', hours: [1], has_curriculum_assignment: false },
                { id: 3, date: '2026-03-04', hours: [1], status: ['free'], attendance_checked: true },
                { id: 4, date: '2026-03-05', hours: [1], cancelled_hours: [1], attendance_checked: false },
            ],
        }] as any
        const wrapper = mount(MyTimetable, {
            global: { mocks: { $route: { path: '/admin/teaching', query: {} }, $router: { replace: vi.fn().mockResolvedValue(undefined) } },
                stubs: { ItsGridBox: { template: '<div><slot /></div>' }, VDivider: true, VListItemTitle: true } },
        })
        try {
            await wrapper.setData({ range: 'week' })
            expect(wrapper.text()).toContain('Deutsch')
            expect(wrapper.text()).toContain('1A')
            expect(wrapper.findAll('.timetable-curriculum-status')).toHaveLength(1)
            expect(wrapper.find('[aria-label$="Anhang"], [aria-label$="Anhänge"], [aria-label="Keine Anhänge veröffentlicht"]').exists()).toBe(false)
            expect(wrapper.find('[icon="mdi-eye"], [icon="mdi-eye-off"]').exists()).toBe(false)
            expect(wrapper.text()).not.toMatch(/mdi-circle|mdi-check-circle/)
            expect(wrapper.findAll('.timetable-attendance-status')).toHaveLength(2)
            expect(wrapper.findAll('.timetable-content-status')).toHaveLength(2)
            expect(wrapper.get('[aria-label="Anwesenheit geprüft"]').attributes()).toMatchObject({ icon: 'mdi-check', color: 'success', title: 'Anwesenheit geprüft' })
            expect(wrapper.get('[aria-label="Anwesenheit noch nicht geprüft"]').attributes()).toMatchObject({ icon: 'mdi-alert-circle-outline', color: 'warning', title: 'Anwesenheit noch nicht geprüft' })
            await wrapper.get(viewMode === 'table' ? '.timetable-grid-item' : 'v-list-item').trigger('click')
            expect(courseStore.selected_course_id).toBe(18)
        } finally { wrapper.unmount() }
    })

    it('refreshes curriculum and attendance icons while keeping attachment visibility out of the timetable display', async () => {
        sessionStorage.clear()
        setActivePinia(createPinia())
        vi.useFakeTimers()
        vi.setSystemTime(new Date(2026, 2, 2, 12))
        useAdminStore().config = { user: { id: 7 } } as any
        useSchoolHourStore().school_hours = [{ hour: 1 }] as any
        const courseStore = useCourseStore()
        courseStore.timetable_view_mode = viewMode
        courseStore.courses = [{
            id: 18, user_id: 7, title: 'Deutsch', classes: ['1A'],
            course_dates: [{ id: 1, date: '2026-03-02', hours: [1], adopted_materials: [] }],
        }] as any
        const wrapper = mount(MyTimetable, {
            global: { stubs: { ItsGridBox: { template: '<div><slot /></div>' }, VDivider: true, VListItemTitle: true } },
        })
        try {
            await wrapper.setData({ range: 'week' })
            const before = wrapper.text()
            const date = courseStore.courses[0].course_dates[0] as any
            date.adopted_materials = [{ id: 10, title: 'Einheit', attachments: [
                { id: 1, student_visible: true }, { id: 2, student_visible: false },
            ] }]
            date.attendance_complete = true
            await wrapper.vm.$nextTick()
            expect(wrapper.text()).toBe(before)
            expect(wrapper.findAll('.timetable-attendance-status')).toHaveLength(1)
            expect(wrapper.get('.timetable-attendance-status').attributes('aria-label')).toBe('Anwesenheit geprüft')
            expect(wrapper.findAll('.timetable-curriculum-status')).toHaveLength(1)
        } finally { wrapper.unmount() }
    })
})

describe.each([
    { label: 'configured school hours', schoolHours: Array.from({ length: 12 }, (_, index) => ({ hour: index + 1 })) },
    { label: 'missing school hours', schoolHours: [] },
])('MyTimetable visible hours with $label', ({ schoolHours }) => {
    it.each([
        { range: 'week', firstHour: 1, expectedStart: 1 },
        { range: 'week', firstHour: 3, expectedStart: 1 },
        { range: 'week', firstHour: 6, expectedStart: 1 },
        { range: 'week', firstHour: 7, expectedStart: 7 },
        { range: 'next_week', firstHour: 6, expectedStart: 1 },
        { range: 'next_week', firstHour: 7, expectedStart: 7 },
        { range: 'today', firstHour: 6, expectedStart: 6 },
    ])('starts $range at $expectedStart when teaching starts in hour $firstHour', ({ range, firstHour, expectedStart }) => {
        const computed = (MyTimetable as any).computed
        const context = {
            range,
            school_hours: schoolHours,
            filteredItems: [
                { date: '2026-03-02', hours: [9, 10] },
                { date: '2026-03-06', hours: [firstHour] },
            ],
        }

        const hours = computed.tableHours.call(context)
        const cells = computed.tableCellItems.call(context)

        expect(hours).toEqual(Array.from({ length: 11 - expectedStart }, (_, index) => expectedStart + index))
        expect(cells['2026-03-02-1']).toBeUndefined()
        expect(cells['2026-03-06-1']).toEqual(firstHour === 1 ? [context.filteredItems[1]] : undefined)
        expect(cells[`2026-03-06-${firstHour}`]).toEqual([context.filteredItems[1]])
    })

    it('ignores earlier teaching outside the displayed week', () => {
        const computed = (MyTimetable as any).computed
        const context: Record<string, any> = {
            range: 'week',
            school_hours: schoolHours,
            currentRangeBounds: () => [new Date(2026, 2, 2), new Date(2026, 2, 8)],
            timetableItems: [
                { dateObj: new Date(2026, 1, 27), hours: [1] },
                { dateObj: new Date(2026, 2, 6), hours: [7, 8] },
                { dateObj: new Date(2026, 2, 9), hours: [6] },
            ],
        }
        context.filteredItems = computed.filteredItems.call(context)

        expect(computed.tableHours.call(context)).toEqual([7, 8])
    })

    it('preserves the existing hour range for an empty week', () => {
        const hours = (MyTimetable as any).computed.tableHours.call({
            range: 'week',
            school_hours: schoolHours,
            filteredItems: [],
        })

        expect(hours).toEqual(schoolHours.map(({ hour }) => hour))
    })
})

describe('MyTimetable time range labels', () => {
    it('labels the next teaching range as the next lesson', () => {
        const source = readFileSync(
            resolve(process.cwd(), 'resources/js/pages/admin/teaching/overview/components/MyTimetable.vue'),
            'utf8',
        )

        expect(source).toContain('<v-btn :value="RANGE_NEXT_WEEK">Nächster Unterricht</v-btn>')
        expect(source).not.toContain('<v-btn :value="RANGE_NEXT_WEEK">Nächste Woche</v-btn>')
    })

    it('builds a time range label from school hour definitions', () => {
        const computed = (MyTimetable as any).computed
        const methods = (MyTimetable as any).methods

        const ctx: Record<string, unknown> = {
            myCourses: [
                {
                    id: 1,
                    title: 'Digitale Grundbildung',
                    classes: ['2B'],
                    course_dates: [
                        { id: 11, date: '2026-03-02', hours: [1, 2], status: [] },
                    ],
                },
            ],
            school_hours: [
                { hour: 1, from: '07:45:00', until: '08:35:00' },
                { hour: 2, from: '08:40:00', until: '09:30:00' },
            ],
            formatHoursTimeRange: methods.formatHoursTimeRange,
            formatTimeValue: methods.formatTimeValue,
            attendanceIsComplete: methods.attendanceIsComplete,
            attendanceCounts: methods.attendanceCounts,
            hasLessonContent: methods.hasLessonContent,
            scheduledWorkDatesForCourse: methods.scheduledWorkDatesForCourse,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)

        const items = computed.timetableItems.call(ctx)

        expect(items).toHaveLength(1)
        expect(items[0].timeRangeLabel).toBe('07:45 - 09:30')
    })

    it('falls back to "-" when school hour data is missing', () => {
        const computed = (MyTimetable as any).computed
        const methods = (MyTimetable as any).methods

        const ctx: Record<string, unknown> = {
            myCourses: [
                {
                    id: 2,
                    title: 'Informatik',
                    classes: ['5A'],
                    course_dates: [
                        { id: 12, date: '2026-03-03', hours: [5], status: [] },
                    ],
                },
            ],
            school_hours: [
                { hour: 1, from: '07:45:00', until: '08:35:00' },
            ],
            formatHoursTimeRange: methods.formatHoursTimeRange,
            formatTimeValue: methods.formatTimeValue,
            attendanceIsComplete: methods.attendanceIsComplete,
            attendanceCounts: methods.attendanceCounts,
            hasLessonContent: methods.hasLessonContent,
            scheduledWorkDatesForCourse: methods.scheduledWorkDatesForCourse,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)

        const items = computed.timetableItems.call(ctx)

        expect(items).toHaveLength(1)
        expect(items[0].timeRangeLabel).toBe('-')
    })

    it('uses the next week containing a course date for the next-week range', () => {
        vi.useFakeTimers()
        vi.setSystemTime(new Date(2026, 2, 4, 12))

        const methods = (MyTimetable as any).methods
        const ctx = {
            range: 'next_week',
            offset: 0,
            timetableItems: [
                { dateObj: new Date(2026, 2, 5) },
                { dateObj: new Date(2026, 2, 23) },
            ],
            normalizeDay: methods.normalizeDay,
            startOfWeek: methods.startOfWeek,
            endOfWeek: methods.endOfWeek,
            nextCourseWeekStart: methods.nextCourseWeekStart,
            hasFreeStatus: methods.hasFreeStatus,
        }

        const [from, until] = methods.currentRangeBounds.call(ctx)

        expect(from).toEqual(new Date(2026, 2, 23))
        expect(until).toEqual(new Date(2026, 2, 29))
    })

    it('renders the time-range chip in timetable rows', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/MyTimetable.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('{{ item.timeRangeLabel }}')
        expect(source).toContain('variant="outlined" color="primary">{{ item.timeRangeLabel }}</v-chip>')
        expect(source).toContain('v-if="!isToday(item)"')
    })

    it('treats entfaellt status as free-like status', () => {
        const hasFreeStatus = (MyTimetable as any).methods.hasFreeStatus.call({}, { status: ['entfaellt'] })

        expect(hasFreeStatus).toBe(true)
    })

    it('returns free row class for entfaellt status', () => {
        const ctx = {
            hasExamStatus: (MyTimetable as any).methods.hasExamStatus,
            hasFreeStatus: (MyTimetable as any).methods.hasFreeStatus,
        }
        const rowClass = (MyTimetable as any).methods.getStatusClass.call(ctx, { status: ['entfaellt'] })

        expect(rowClass).toEqual(['timetable-item--free'])
    })

    it('keeps both exam and free classes when statuses overlap', () => {
        const ctx = {
            hasExamStatus: (MyTimetable as any).methods.hasExamStatus,
            hasFreeStatus: (MyTimetable as any).methods.hasFreeStatus,
        }
        const rowClass = (MyTimetable as any).methods.getStatusClass.call(ctx, { status: ['pruefung', 'free'] })

        expect(rowClass).toEqual(['timetable-item--exam', 'timetable-item--free'])
    })

    it('contains a combined exam and free style override in the source', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/MyTimetable.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('.timetable-item--exam.timetable-item--free')
        expect(source).toContain('.timetable-grid-item.timetable-item--exam.timetable-item--free')
    })

    it.each([
        { selectedCourseId: null, panel: 'table', view: undefined },
        { selectedCourseId: 16, panel: 'table', view: 'attendance' },
        { selectedCourseId: 17, panel: 'works', view: undefined },
    ])('opens timetable courses on the table panel from $panel with course $selectedCourseId', ({ selectedCourseId, panel, view }) => {
        const pinia = createPinia()
        setActivePinia(pinia)
        const courseStore = useCourseStore()
        const course = { id: 16, title: 'DGB1', classes: ['3B'], details_loaded: true }
        courseStore.courses = [course]
        courseStore.selected_course = selectedCourseId ? { id: selectedCourseId } : null
        const inactivePanels = [
            'show_students', 'show_infos', 'show_works', 'show_print', 'show_lists', 'show_dates',
            'show_curriculum', 'show_attendance', 'show_performances', 'show_performances_plus',
        ]
        inactivePanels.forEach((key) => { courseStore[key] = true })
        courseStore.show_table = false
        const replace = vi.fn().mockResolvedValue(undefined)
        const context: Record<string, any> = {
            $pinia: pinia,
            $route: { query: { course: '17', panel, view, date: '44', work: '55', grades: 'sem1' } },
            $router: { replace },
        }
        Object.entries((MyTimetable as any).computed).forEach(([key, computed]: [string, any]) => {
            if (computed.get && computed.set) {
                Object.defineProperty(context, key, {
                    get: computed.get.bind(context),
                    set: computed.set.bind(context),
                })
            }
        })
        context.selected_courseDate = { id: 44 }
        context.selected_course_student = { id: 3 }
        context.action_2 = 'student_detail'

        ;(MyTimetable as any).methods.openCourse.call(context, { courseId: 16, dateId: 44 })

        expect(courseStore.selected_course.id).toBe(16)
        expect(courseStore.selected_course_id).toBe(16)
        expect(courseStore.selected_course_student).toBeNull()
        expect(context.selected_courseDate).toBeNull()
        expect(context.action_2).toBe('')
        expect(courseStore.show_table).toBe(true)
        inactivePanels.forEach((key) => { expect(courseStore[key]).toBe(false) })
        expect(replace).toHaveBeenCalledWith({
            query: { course: '16', panel: 'table', grades: 'sem1' },
        })
    })
})

describe('MyTimetable return navigation', () => {
    function prepareNavigation(viewMode = 'table', { preserveSession = false } = {}) {
        if (!preserveSession) sessionStorage.clear()
        setActivePinia(createPinia())
        vi.useFakeTimers()
        vi.setSystemTime(new Date(2026, 2, 2, 12))
        useAdminStore().config = { user: { id: 7 }, selected_schoolyear: { id: 4 } } as any
        useSchoolHourStore().school_hours = [{ hour: 14 }, { hour: 15 }] as any
        const courseStore = useCourseStore()
        courseStore.timetable_view_mode = viewMode
        courseStore.courses = [{
            id: 18, user_id: 7, title: 'Mathematik', classes: ['2C'], details_loaded: true,
            course_dates: [{ id: 1, date: '2026-03-09', hours: [14, 15] }],
        }] as any
        const route = { path: '/admin/teaching', query: { grades: 'sem1', filter: 'my-courses' } }
        const replace = vi.fn().mockImplementation(({ query }) => {
            route.query = query
            return Promise.resolve()
        })
        const navigation = defineComponent({
            components: { MyTimetable },
            computed: {
                ...mapWritableState(useCourseStore, ['selected_course', 'selected_course_id']),
                ...mapWritableState(useCourseDateStore, ['selected_courseDate']),
            },
            methods: { handleCourseClear: (Teaching as any).methods.handleCourseClear },
            template: '<MyTimetable v-if="!selected_course" /><button v-else @click="handleCourseClear">Übersicht</button>',
        })
        const mountNavigation = () => mount(navigation, {
            attachTo: document.body,
            global: {
                mocks: { $route: route, $router: { replace } },
                stubs: { ItsGridBox: { template: '<div><slot /></div>' }, VDivider: true, VListItemTitle: true },
            },
        })
        return { courseStore, route, replace, mountNavigation }
    }

    it.each(['table', 'list'])('restores the %s range and position after clicking a course and Übersicht', async (viewMode) => {
        const { courseStore, route, mountNavigation } = prepareNavigation(viewMode)
        const scrollTo = vi.spyOn(window, 'scrollTo').mockImplementation(() => {})
        const wrapper = mountNavigation()
        try {
            const timetable = wrapper.getComponent(MyTimetable)
            await timetable.setData({ range: 'week', offset: 1 })
            const dateRangeLabel = (timetable.vm as any).dateRangeLabel
            const table = timetable.find('.timetable-table-wrapper')
            if (table.exists()) (table.element as HTMLElement).scrollLeft = 175
            vi.stubGlobal('scrollX', 12)
            vi.stubGlobal('scrollY', 846)

            await timetable.get(viewMode === 'table' ? '.timetable-grid-item' : 'v-list-item').trigger('click')

            expect(wrapper.findComponent(MyTimetable).exists()).toBe(false)
            expect(courseStore.selected_course_id).toBe(18)
            expect(route.query).toMatchObject({ course: '18', panel: 'table' })
            vi.stubGlobal('scrollX', 0)
            vi.stubGlobal('scrollY', 0)
            courseStore.timetable_view_mode = viewMode === 'table' ? 'list' : 'table'
            await wrapper.get('button').trigger('click')
            await nextTick()

            const restored = wrapper.getComponent(MyTimetable)
            expect(courseStore.selected_course).toBeNull()
            expect(route.query).toEqual({ grades: 'sem1', filter: 'my-courses' })
            expect((restored.vm as any).range).toBe('week')
            expect((restored.vm as any).offset).toBe(1)
            expect((restored.vm as any).dateRangeLabel).toBe(dateRangeLabel)
            expect((restored.vm as any).activeViewMode).toBe(viewMode)
            if (viewMode === 'table') {
                expect((restored.get('.timetable-table-wrapper').element as HTMLElement).scrollLeft).toBe(175)
            }
            expect(scrollTo).toHaveBeenCalledExactlyOnceWith({ left: 12, top: 846, behavior: 'instant' })
            expect(courseStore.timetable_return_state).toBeNull()
        } finally {
            wrapper.unmount()
            scrollTo.mockRestore()
            vi.unstubAllGlobals()
        }
    })

    it.each(['direct', 'different user', 'different schoolyear', 'different path'])('does not restore a stale position for %s entry', async (entry) => {
        const { courseStore, route, mountNavigation } = prepareNavigation()
        if (entry !== 'direct') {
            courseStore.rememberTimetableReturn({ path: route.path, range: 'month', offset: 3, top: 900, left: 0 })
            if (entry === 'different user') (useAdminStore().config as any).user.id = 8
            if (entry === 'different schoolyear') (useAdminStore().config as any).selected_schoolyear.id = 5
            if (entry === 'different path') route.path = '/admin/teaching/overview'
        }
        const scrollTo = vi.spyOn(window, 'scrollTo').mockImplementation(() => {})
        const wrapper = mountNavigation()
        try {
            await nextTick()
            const timetable = wrapper.getComponent(MyTimetable)
            expect((timetable.vm as any).range).toBe('next_week')
            expect((timetable.vm as any).offset).toBe(0)
            expect(scrollTo).not.toHaveBeenCalled()
            expect(courseStore.timetable_return_state).toBeNull()
        } finally {
            wrapper.unmount()
            scrollTo.mockRestore()
        }
    })

    it('clears the pending return when leaving the teaching page or overview section', () => {
        const { courseStore } = prepareNavigation()
        courseStore.timetable_return_state = { top: 900 }
        ;(Teaching as any).watch.main_action.call({}, 'settings')
        expect(courseStore.timetable_return_state).toBeNull()
        courseStore.timetable_return_state = { top: 900 }
        ;(Teaching as any).unmounted.call({ nowTimer: null })
        expect(courseStore.timetable_return_state).toBeNull()
    })

    it('returns a directly opened course to the default overview without a scroll jump', async () => {
        const { courseStore, route, mountNavigation } = prepareNavigation()
        courseStore.selected_course = courseStore.courses[0]
        courseStore.selected_course_id = 18
        route.query = { course: '18', panel: 'table', grades: 'sem1' } as any
        const scrollTo = vi.spyOn(window, 'scrollTo').mockImplementation(() => {})
        const wrapper = mountNavigation()
        try {
            await wrapper.get('button').trigger('click')
            await nextTick()
            expect(route.query).toEqual({})
            expect((wrapper.getComponent(MyTimetable).vm as any).range).toBe('next_week')
            expect((wrapper.getComponent(MyTimetable).vm as any).offset).toBe(0)
            expect(scrollTo).not.toHaveBeenCalled()
        } finally {
            wrapper.unmount()
            scrollTo.mockRestore()
        }
    })

    it.each(['table', 'list'])('rehydrates %s state and scroll after a reload and asynchronous data loading', async (viewMode) => {
        const initial = prepareNavigation(viewMode)
        const original = initial.mountNavigation()
        const scrollTo = vi.spyOn(window, 'scrollTo').mockImplementation(() => {})
        let reloaded: ReturnType<typeof mount> | null = null
        try {
            await flushPromises()
            const timetable = original.getComponent(MyTimetable)
            await timetable.setData({ range: 'week', offset: 1 })
            const expectedLabel = (timetable.vm as any).dateRangeLabel
            if (viewMode === 'table') {
                const table = timetable.get('.timetable-table-wrapper').element as HTMLElement
                table.scrollLeft = 180
                table.dispatchEvent(new Event('scroll'))
            }
            vi.stubGlobal('scrollX', 15)
            vi.stubGlobal('scrollY', 920)
            window.dispatchEvent(new Event('scroll'))
            window.dispatchEvent(new Event('pagehide'))
            original.unmount()

            vi.stubGlobal('scrollX', 0)
            vi.stubGlobal('scrollY', 0)
            const refreshed = prepareNavigation('table', { preserveSession: true })
            const courses = refreshed.courseStore.courses
            refreshed.courseStore.courses = []
            let finishCourses = () => {}
            refreshed.courseStore.courses_request_promise = new Promise<void>((resolve) => {
                finishCourses = () => {
                    refreshed.courseStore.courses = courses
                    resolve()
                }
            })
            const schoolHours = useSchoolHourStore().school_hours
            useSchoolHourStore().school_hours = []
            let finishSchoolHours = () => {}
            vi.spyOn(useSchoolHourStore(), 'index').mockImplementation(() => new Promise<void>((resolve) => {
                finishSchoolHours = () => {
                    useSchoolHourStore().school_hours = schoolHours
                    resolve()
                }
            }))
            reloaded = refreshed.mountNavigation()
            await flushPromises()
            const restored = reloaded.getComponent(MyTimetable)
            expect((restored.vm as any).offset).toBe(1)
            expect((restored.vm as any).activeViewMode).toBe(viewMode)
            expect(scrollTo).not.toHaveBeenCalled()
            expect(refreshed.courseStore.getTimetableView(refreshed.route.path, refreshed.route.query)?.top).toBe(920)

            finishCourses()
            await flushPromises()
            expect(scrollTo).not.toHaveBeenCalled()
            finishSchoolHours()
            await flushPromises()

            expect((restored.vm as any).dateRangeLabel).toBe(expectedLabel)
            expect(refreshed.route.query).toEqual({ grades: 'sem1', filter: 'my-courses' })
            expect(scrollTo).toHaveBeenCalledExactlyOnceWith({ left: 15, top: 920, behavior: 'instant' })
            if (viewMode === 'table') {
                expect((restored.get('.timetable-table-wrapper').element as HTMLElement).scrollLeft).toBe(180)
            }
        } finally {
            if (original.exists()) original.unmount()
            reloaded?.unmount()
            scrollTo.mockRestore()
            vi.unstubAllGlobals()
            sessionStorage.clear()
        }
    })

    it.each(['query', 'user', 'schoolyear', 'path', 'day', 'corrupt'])('ignores persisted overview state for mismatched %s', async (mismatch) => {
        const initial = prepareNavigation()
        initial.courseStore.rememberTimetableView({
            path: initial.route.path, query: initial.route.query, range: 'month', offset: 2,
            viewMode: 'list', top: 900, left: 0, tableLeft: 200, tableTop: 0,
        })
        const refreshed = prepareNavigation('table', { preserveSession: true })
        if (mismatch === 'query') refreshed.route.query = { filter: 'different' } as any
        if (mismatch === 'user') (useAdminStore().config as any).user.id = 99
        if (mismatch === 'schoolyear') (useAdminStore().config as any).selected_schoolyear.id = 99
        if (mismatch === 'path') refreshed.route.path = '/admin/teaching/overview'
        if (mismatch === 'day') vi.setSystemTime(new Date(2026, 2, 3, 12))
        if (mismatch === 'corrupt') sessionStorage.setItem('schooltool:teaching:timetable', '{broken')
        const scrollTo = vi.spyOn(window, 'scrollTo').mockImplementation(() => {})
        const wrapper = refreshed.mountNavigation()
        try {
            await flushPromises()
            expect((wrapper.getComponent(MyTimetable).vm as any).range).toBe('next_week')
            expect((wrapper.getComponent(MyTimetable).vm as any).offset).toBe(0)
            expect(scrollTo).not.toHaveBeenCalled()
        } finally {
            wrapper.unmount()
            scrollTo.mockRestore()
            sessionStorage.clear()
        }
    })

    it('preserves the saved position when loading the timetable fails', async () => {
        const { courseStore, route, mountNavigation } = prepareNavigation()
        courseStore.rememberTimetableView({
            path: route.path, query: route.query, range: 'week', offset: 1,
            viewMode: 'table', top: 900, left: 0, tableLeft: 200, tableTop: 0,
        })
        courseStore.courses_request_promise = Promise.resolve(false)
        const scrollTo = vi.spyOn(window, 'scrollTo').mockImplementation(() => {})
        const wrapper = mountNavigation()
        try {
            await flushPromises()
            window.dispatchEvent(new Event('scroll'))
            window.dispatchEvent(new Event('pagehide'))
            expect(scrollTo).not.toHaveBeenCalled()
            expect(courseStore.getTimetableView(route.path, route.query)?.top).toBe(900)
        } finally {
            wrapper.unmount()
            scrollTo.mockRestore()
            sessionStorage.clear()
        }
    })
})
