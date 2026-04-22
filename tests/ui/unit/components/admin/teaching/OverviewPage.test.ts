import { describe, expect, it, vi } from 'vitest'
import Overview from '@/pages/admin/teaching/overview/Overview.vue'

describe('Teaching overview controls', () => {
    it('refreshes courses and school hours together', async () => {
        const courseIndex = vi.fn().mockResolvedValue(true)
        const schoolHourIndex = vi.fn().mockResolvedValue(true)
        const ctx = {
            courseStore: { index: courseIndex },
            schoolHourStore: { index: schoolHourIndex },
        }

        await (Overview as any).methods.refreshOverviewData.call(ctx)

        expect(courseIndex).toHaveBeenCalledTimes(1)
        expect(schoolHourIndex).toHaveBeenCalledTimes(1)
    })

    it('resets to students panel defaults when selected course changes', () => {
        const ctx = {
            show_students: false,
            show_infos: true,
            show_works: true,
            show_dates: true,
            show_curriculum: true,
            show_attendance: true,
            show_performances: true,
            show_performances_plus: true,
            _urlPanelRestored: true,
            _lastCourseId: 11,
        }

        ;(Overview as any).watch.selected_course.handler.call(ctx, { id: 22, title: 'Physik' })

        expect(ctx.show_students).toBe(true)
        expect(ctx.show_infos).toBe(false)
        expect(ctx.show_works).toBe(false)
        expect(ctx.show_dates).toBe(false)
        expect(ctx.show_curriculum).toBe(false)
        expect(ctx.show_attendance).toBe(false)
        expect(ctx.show_performances).toBe(false)
    })

    it('maps functionalPanelSelection getter to active panel', () => {
        const ctx = {
            selected_course: { id: 7, title: 'Biologie' },
            show_students: false,
            show_infos: true,
            show_works: false,
            show_dates: false,
            show_attendance: false,
            show_performances: false,
        }

        const active = (Overview as any).computed.functionalPanelSelection.get.call(ctx)

        expect(active).toBe('infos')
    })

    it('toggles mirrored panel flags through functionalPanelSelection setter', () => {
        const ctx = {
            show_students: true,
            show_infos: false,
            show_works: false,
            show_dates: false,
            show_curriculum: false,
            show_attendance: false,
            show_performances: false,
            show_performances_plus: false,
            action_2: 'course_student_view',
            selected_course_student: { id: 99 },
        }

        ;(Overview as any).computed.functionalPanelSelection.set.call(ctx, 'performances')

        expect(ctx.show_students).toBe(false)
        expect(ctx.show_performances).toBe(true)
        expect(ctx.show_infos).toBe(false)
        expect(ctx.show_curriculum).toBe(false)
        ;(Overview as any).computed.functionalPanelSelection.set.call(ctx, null)
        expect(ctx.show_performances).toBe(false)
        expect(ctx.action_2).toBe('')
        expect(ctx.selected_course_student).toBeNull()
    })

    it('activates the curriculum panel through functionalPanelSelection setter', () => {
        const ctx = {
            show_students: true,
            show_infos: false,
            show_works: false,
            show_dates: false,
            show_curriculum: false,
            show_attendance: false,
            show_performances: false,
            show_performances_plus: false,
            action_2: '',
            selected_course_student: null,
        }

        ;(Overview as any).computed.functionalPanelSelection.set.call(ctx, 'curriculum')

        expect(ctx.show_students).toBe(false)
        expect(ctx.show_infos).toBe(false)
        expect(ctx.show_works).toBe(false)
        expect(ctx.show_dates).toBe(false)
        expect(ctx.show_curriculum).toBe(true)
        expect(ctx.show_attendance).toBe(false)
        expect(ctx.show_performances).toBe(false)
        expect(ctx.show_performances_plus).toBe(false)
    })

    it('persists active semester updates when diverging from config value', () => {
        const saveActiveSemester = vi.fn()
        const ctx = {
            config: {
                user: {
                    teaching_active_semester: 1,
                },
            },
            teachingStore: { saveActiveSemester },
        }

        ;(Overview as any).watch.activeSemester.call(ctx, 2)
        ;(Overview as any).watch.activeSemester.call(ctx, 1)

        expect(saveActiveSemester).toHaveBeenCalledTimes(1)
        expect(saveActiveSemester).toHaveBeenCalledWith(2)
    })
})
