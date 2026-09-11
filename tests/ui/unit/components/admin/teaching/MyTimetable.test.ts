import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { mount } from '@vue/test-utils'
import MyTimetable from '@/pages/admin/teaching/overview/components/MyTimetable.vue'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'

afterEach(() => {
    vi.useRealTimers()
})

describe.each(['list', 'table'])('MyTimetable curriculum indicator in %s view', (viewMode) => {
    it('distinguishes attachment visibility and reacts to individual toggles and refreshed dates', async () => {
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
                    has_shared_curriculum_attachments: true, has_private_curriculum_attachments: false },
                { id: 2, date: '2026-03-03', hours: [1], has_curriculum_assignment: true,
                    has_shared_curriculum_attachments: true, has_private_curriculum_attachments: true },
                { id: 3, date: '2026-03-04', hours: [1], has_curriculum_assignment: true,
                    has_shared_curriculum_attachments: false, has_private_curriculum_attachments: true },
                { id: 4, date: '2026-03-05', hours: [1], has_curriculum_assignment: true,
                    has_shared_curriculum_attachments: false, has_private_curriculum_attachments: false },
            ],
        }] as any
        const wrapper = mount(MyTimetable, {
            global: { stubs: { ItsGridBox: { template: '<div><slot /></div>' }, VDivider: true, VListItemTitle: true } },
        })

        try {
            const sharedLabel = 'Alle Curriculum-Anhänge für Schüler:innen freigegeben'
            const partialLabel = 'Curriculum-Anhänge teilweise für Schüler:innen freigegeben'
            const privateLabel = 'Keine Curriculum-Anhänge für Schüler:innen freigegeben'
            const firstDateCellCount = viewMode === 'table' ? 2 : 1
            const visibilitySelector = '[aria-label*="Curriculum-Anhänge"]'
            expect(wrapper.findAll(`[aria-label="${sharedLabel}"]`)).toHaveLength(firstDateCellCount)
            expect(wrapper.findAll(`[aria-label="${partialLabel}"]`)).toHaveLength(1)
            expect(wrapper.findAll(`[aria-label="${privateLabel}"]`)).toHaveLength(1)
            for (const [label, icon, color] of [
                [sharedLabel, 'mdi-eye', 'success'],
                [partialLabel, 'mdi-eye-outline', 'warning'],
                [privateLabel, 'mdi-eye-off', 'grey'],
            ]) {
                expect(wrapper.get(`[aria-label="${label}"]`).attributes()).toMatchObject({
                    icon, color, title: label, role: 'img', 'aria-hidden': 'false',
                })
            }

            const course = courseStore.courses[0] as any
            course.course_dates = course.course_dates.map((date: any) => ({ ...date, adopted_materials: [] }))
            course.course_dates[0].adopted_materials = [
                { id: 10, attachments: [{ id: 1, student_visible: false }] },
                { id: 11, attachments: [{ id: 2, student_visible: false }] },
            ]
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll(visibilitySelector)).toHaveLength(firstDateCellCount)
            expect(wrapper.findAll(`[aria-label="${privateLabel}"]`)).toHaveLength(firstDateCellCount)

            const materials = course.course_dates[0].adopted_materials
            materials[0].attachments[0].student_visible = true
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll(`[aria-label="${partialLabel}"]`)).toHaveLength(firstDateCellCount)
            materials[1].attachments[0].student_visible = true
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll(`[aria-label="${sharedLabel}"]`)).toHaveLength(firstDateCellCount)

            course.course_dates[0] = { ...course.course_dates[0], adopted_materials: [{ id: 10, attachments: [] }] }
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll(visibilitySelector)).toHaveLength(0)
            expect(wrapper.findAll('[aria-label="Curriculum-Eintrag zugeordnet"]')).toHaveLength(firstDateCellCount)
            course.course_dates[0].adopted_materials = []
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll(visibilitySelector)).toHaveLength(0)
            expect(wrapper.findAll('[aria-label="Curriculum-Eintrag zugeordnet"]')).toHaveLength(0)
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
            'show_students', 'show_infos', 'show_works', 'show_print', 'show_dates',
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
