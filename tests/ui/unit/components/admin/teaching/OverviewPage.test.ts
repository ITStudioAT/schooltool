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

    it('resets course context when showing courses again', () => {
        const ctx = {
            show_my_courses: false,
            action_2: 'course_student_view',
            selected_course: { id: 22, title: 'Physik' },
            selected_course_id: 22,
            selected_course_student: { id: 99 },
            show_infos: true,
        }

        ;(Overview as any).methods.toggleMyCourses.call(ctx)

        expect(ctx.show_my_courses).toBe(true)
        expect(ctx.action_2).toBe('')
        expect(ctx.selected_course).toBeNull()
        expect(ctx.selected_course_id).toBeNull()
        expect(ctx.selected_course_student).toBeNull()
        expect(ctx.show_infos).toBe(false)
    })

    it('hides Meine Fächer and enables Infos when a course is selected', () => {
        const ctx = {
            show_my_courses: true,
            show_infos: false,
        }

        ;(Overview as any).watch.selected_course.call(ctx, { id: 7, title: 'Biologie' })

        expect(ctx.show_my_courses).toBe(false)
        expect(ctx.show_infos).toBe(true)
    })

    it('toggles mirrored submenu flags for a selected course', () => {
        const ctx = {
            isControlLocked: false,
            selected_course: { id: 5 },
            show_students: false,
            show_infos: false,
            show_works: false,
            show_dates: false,
            toggleMyCourses: vi.fn(),
        }

        ;(Overview as any).methods.toggleFunctionalPanel.call(ctx, 'students')
        ;(Overview as any).methods.toggleFunctionalPanel.call(ctx, 'infos')
        ;(Overview as any).methods.toggleFunctionalPanel.call(ctx, 'works')
        ;(Overview as any).methods.toggleFunctionalPanel.call(ctx, 'dates')

        expect(ctx.show_students).toBe(true)
        expect(ctx.show_infos).toBe(true)
        expect(ctx.show_works).toBe(true)
        expect(ctx.show_dates).toBe(true)
    })

    it('syncs new submenu selection by toggling only changed panels', () => {
        const toggleFunctionalPanel = vi.fn()
        const ctx = {
            functionalPanels: [{ id: 'my_courses' }, { id: 'students' }, { id: 'infos' }],
            functionalPanelSelection: ['my_courses'],
            toggleFunctionalPanel,
        }

        ;(Overview as any).methods.syncFunctionalPanelSelection.call(ctx, ['students', 'infos'])

        expect(toggleFunctionalPanel).toHaveBeenCalledTimes(3)
        expect(toggleFunctionalPanel).toHaveBeenCalledWith('my_courses')
        expect(toggleFunctionalPanel).toHaveBeenCalledWith('students')
        expect(toggleFunctionalPanel).toHaveBeenCalledWith('infos')
    })
})
