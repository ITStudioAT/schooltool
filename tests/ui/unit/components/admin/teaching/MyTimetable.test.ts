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

afterEach(() => {
    vi.useRealTimers()
})

describe('MyTimetable automatic week selection', () => {
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
                expect(wrapper.find('.timetable-item--today').exists()).toBe(true)
            }
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
            await wrapper.setData({ range: 'week' })
            expect((wrapper.vm as any).filteredItems[0].date).toBe('2026-03-06')
            expect(wrapper.find('.timetable-item--free').exists()).toBe(true)
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

describe.each(['list', 'table'])('MyTimetable curriculum indicator in %s view', (viewMode) => {
    it('counts published and hidden attachments and reacts to toggles and refreshed dates', async () => {
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
                { id: 1, date: '2026-03-02', hours: [1, 2], has_curriculum_assignment: true,
                    shared_curriculum_attachments_count: 3, private_curriculum_attachments_count: 0 },
                { id: 2, date: '2026-03-03', hours: [1], has_curriculum_assignment: true,
                    shared_curriculum_attachments_count: 1, private_curriculum_attachments_count: 2 },
                { id: 3, date: '2026-03-04', hours: [1], has_curriculum_assignment: true,
                    shared_curriculum_attachments_count: 0, private_curriculum_attachments_count: 3 },
                { id: 4, date: '2026-03-05', hours: [1], has_curriculum_assignment: true,
                    shared_curriculum_attachments_count: 0, private_curriculum_attachments_count: 0 },
                { id: 5, date: '2026-03-06', hours: [1], has_curriculum_assignment: false,
                    shared_curriculum_attachments_count: 8, private_curriculum_attachments_count: 9 },
            ],
        }] as any
        const wrapper = mount(MyTimetable, {
            global: { stubs: { ItsGridBox: { template: '<div><slot /></div>' }, VDivider: true, VListItemTitle: true } },
        })

        try {
            await wrapper.setData({ range: 'week' })
            const firstDateCellCount = viewMode === 'table' ? 2 : 1
            const visibilitySelector = '[aria-label$="Anhang"], [aria-label$="Anhänge"]'
            expect(wrapper.findAll(visibilitySelector)).toHaveLength(firstDateCellCount + 3)
            expect(wrapper.findAll('[aria-label="3 veröffentlichte Anhänge"]')).toHaveLength(firstDateCellCount)
            for (const [label, count, icon, color] of [
                ['3 veröffentlichte Anhänge', '', 'mdi-eye', 'success'],
                ['1 veröffentlichter Anhang', '1', 'mdi-eye', 'success'],
                ['2 verborgene Anhänge', '2', 'mdi-eye-off', 'grey-darken-1'],
                ['3 verborgene Anhänge', '', 'mdi-eye-off', 'grey-darken-1'],
            ]) {
                const indicator = wrapper.get(`[aria-label="${label}"]`)
                expect(indicator.attributes('role')).toBe('img')
                expect(indicator.text()).toBe(count)
                expect(indicator.get('[icon]').attributes()).toMatchObject({ icon, color, 'aria-hidden': 'true' })
            }
            const mixedStatus = wrapper.get('[title="1 veröffentlicht, 2 verborgen"]')
            expect(mixedStatus.findAll('[role="img"]')).toHaveLength(2)
            expect(wrapper.findAll('[title="3 veröffentlicht"]')).toHaveLength(firstDateCellCount)
            expect(wrapper.findAll('[title="3 verborgen"]')).toHaveLength(1)
            const unpublishedFallback = wrapper.get('[aria-label="Keine Anhänge veröffentlicht"]')
            expect(unpublishedFallback.text()).toBe('')
            expect(unpublishedFallback.get('[icon]').attributes()).toMatchObject({ icon: 'mdi-eye-off', color: 'grey-darken-1' })
            expect(wrapper.findAll('[aria-label="Curriculum-Eintrag zugeordnet"]')).toHaveLength(firstDateCellCount + 3)

            const course = courseStore.courses[0] as any
            course.course_dates = course.course_dates.map((date: any) => ({ ...date, adopted_materials: [] }))
            course.course_dates[0].adopted_materials = [
                { id: 10, status: 'in_progress', attachments: [{ id: 1, student_visible: false }] },
                { id: 11, attachments: [{ id: 2, student_visible: false }] },
            ]
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll(visibilitySelector)).toHaveLength(firstDateCellCount)
            expect(wrapper.findAll('[aria-label="2 verborgene Anhänge"]')).toHaveLength(firstDateCellCount)

            const materials = course.course_dates[0].adopted_materials
            materials[0].attachments[0].student_visible = true
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll('[title="1 veröffentlicht, 1 verborgen"]')).toHaveLength(firstDateCellCount)
            expect(wrapper.findAll('[aria-label="1 verborgener Anhang"]')).toHaveLength(firstDateCellCount)
            materials[1].attachments[0].student_visible = true
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll('[aria-label="2 veröffentlichte Anhänge"]')).toHaveLength(firstDateCellCount)
            expect(wrapper.findAll(visibilitySelector)).toHaveLength(firstDateCellCount)

            materials[0].attachments[0].source_teaching_curriculum_document_id = 42
            materials[1].attachments[0].source_teaching_curriculum_document_id = 42
            materials[1].attachments[0].student_visible = false
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll('[aria-label="1 veröffentlichter Anhang"]')).toHaveLength(firstDateCellCount)
            expect(wrapper.findAll(visibilitySelector)).toHaveLength(firstDateCellCount)
            materials[1].attachments[0].source_teaching_curriculum_document_id = 43
            materials[1].attachments[0].student_visible = true

            course.course_dates[0].unadopted_curriculum_attachments_count = 1
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll('[title="2 veröffentlicht, 1 verborgen"]')).toHaveLength(firstDateCellCount)
            course.course_dates[0].unadopted_curriculum_attachments_count = 0

            course.course_dates[0] = { ...course.course_dates[0], adopted_materials: [{ id: 10, attachments: [] }] }
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll(visibilitySelector)).toHaveLength(0)
            expect(wrapper.findAll('[aria-label="Curriculum-Eintrag zugeordnet"]')).toHaveLength(firstDateCellCount)
            expect(wrapper.findAll('[aria-label="Keine Anhänge veröffentlicht"]')).toHaveLength(firstDateCellCount)
            course.course_dates[0].adopted_materials = []
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll(visibilitySelector)).toHaveLength(0)
            expect(wrapper.findAll('[aria-label="Curriculum-Eintrag zugeordnet"]')).toHaveLength(0)
            expect(wrapper.findAll('[aria-label="Keine Anhänge veröffentlicht"]')).toHaveLength(0)
        } finally {
            wrapper.unmount()
        }
    })

    it('marks only assigned dates and reacts to linking and unlinking in loaded details', async () => {
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
                { id: 1, date: '2026-03-02', hours: [1, 2], has_curriculum_assignment: true },
                { id: 2, date: '2026-03-03', hours: [1], has_curriculum_assignment: false },
                { id: 3, date: '2026-03-04', hours: [1], content: 'Manueller Inhalt' },
            ],
        }] as any
        const wrapper = mount(MyTimetable, {
            global: {
                stubs: {
                    ItsGridBox: { template: '<div><slot /></div>' },
                    VDivider: true,
                    VListItemTitle: true,
                },
            },
        })

        try {
            await wrapper.setData({ range: 'week' })
            const indicatorSelector = '[aria-label="Curriculum-Eintrag zugeordnet"]'
            const indicators = wrapper.findAll(indicatorSelector)
            expect(indicators).toHaveLength(viewMode === 'table' ? 2 : 1)
            expect(indicators[0].attributes()).toMatchObject({
                title: 'Curriculum-Eintrag zugeordnet', role: 'img', 'aria-hidden': 'false',
                color: 'green-darken-2', size: '10',
            })
            expect(indicators[0].text()).toBe('mdi-circle')

            const course = courseStore.courses[0] as any
            course.course_dates = course.course_dates.map((date: any) => ({ ...date, adopted_materials: [] }))
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll(indicatorSelector)).toHaveLength(0)

            course.course_dates[1].adopted_materials = [{ id: 5, title: 'Thema: Einheit ohne Datei' }]
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll(indicatorSelector)).toHaveLength(1)

            course.course_dates[1].adopted_materials = []
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll(indicatorSelector)).toHaveLength(0)
        } finally {
            wrapper.unmount()
        }
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
            expect(route.query).toEqual({ grades: 'sem1' })
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
