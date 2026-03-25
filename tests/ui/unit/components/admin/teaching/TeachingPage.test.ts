import { beforeEach, describe, expect, it, vi } from 'vitest'
import Teaching from '@/pages/admin/teaching/Teaching.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/admin/teaching/TeachingStore', () => ({
    useTeachingStore: vi.fn(),
}))

vi.mock('@/stores/admin/teaching/CourseStore', () => ({
    useCourseStore: vi.fn(),
}))

vi.mock('@/stores/admin/teaching/SchoolHourStore', () => ({
    useSchoolHourStore: vi.fn(),
}))

describe('Teaching page navigation', () => {
    beforeEach(() => {
        vi.mocked(useAdminStore).mockReset()
        vi.mocked(useTeachingStore).mockReset()
        vi.mocked(useCourseStore).mockReset()
        vi.mocked(useSchoolHourStore).mockReset()
    })

    it('loads teaching settings on beforeMount when missing', async () => {
        const adminStoreMock = { config: {} }
        const courseStoreMock = {
            courses: [],
            index: vi.fn().mockResolvedValue(true),
        }
        const schoolHourStoreMock = {
            school_hours: [],
            index: vi.fn().mockResolvedValue(true),
        }
        const teachingStoreMock = {
            settings: null,
            loadSettings: vi.fn().mockResolvedValue(true),
        }

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useCourseStore).mockReturnValue(courseStoreMock as never)
        vi.mocked(useSchoolHourStore).mockReturnValue(schoolHourStoreMock as never)
        vi.mocked(useTeachingStore).mockReturnValue(teachingStoreMock as never)

        const ctx: Record<string, unknown> = {}
        await (Teaching as any).beforeMount.call(ctx)

        expect(ctx.adminStore).toBe(adminStoreMock)
        expect(teachingStoreMock.loadSettings).toHaveBeenCalledTimes(1)
        expect(courseStoreMock.index).toHaveBeenCalledTimes(1)
        expect(schoolHourStoreMock.index).toHaveBeenCalledTimes(1)
    })

    it('skips loading teaching settings on beforeMount when already present', async () => {
        const adminStoreMock = { config: {} }
        const courseStoreMock = {
            courses: [{ id: 7 }],
            index: vi.fn(),
        }
        const schoolHourStoreMock = {
            school_hours: [{ id: 3 }],
            index: vi.fn(),
        }
        const teachingStoreMock = {
            settings: { teaching_schemas: [{ id: 'existing' }] },
            loadSettings: vi.fn(),
        }

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useCourseStore).mockReturnValue(courseStoreMock as never)
        vi.mocked(useSchoolHourStore).mockReturnValue(schoolHourStoreMock as never)
        vi.mocked(useTeachingStore).mockReturnValue(teachingStoreMock as never)

        const ctx: Record<string, unknown> = {}
        await (Teaching as any).beforeMount.call(ctx)

        expect(ctx.adminStore).toBe(adminStoreMock)
        expect(teachingStoreMock.loadSettings).not.toHaveBeenCalled()
        expect(courseStoreMock.index).not.toHaveBeenCalled()
        expect(schoolHourStoreMock.index).not.toHaveBeenCalled()
    })

    it('builds role-based navigation items', () => {
        const ctx = {
            config: {
                roles: ['teacher'],
                selected_schoolyear: { name: '2025/26' },
            },
            hasAnyRole(requiredRoles: string[]) {
                return (Teaching as any).methods.hasAnyRole.call(this, requiredRoles)
            },
            selectedSchoolyearLabel: '2025/26',
        }

        const items = (Teaching as any).computed.visibleNavigationItems.call(ctx)

        expect(items.map((item: { key: string }) => item.key)).toEqual(['overview', 'settings', 'search', 'schoolyear'])
    })

    it('builds hero chips from selected school context', () => {
        const ctx = {
            selectedSchoolLabel: 'Christian-Doppler-Gymnasium Salzburg',
            selectedSchoolyearLabel: '2025/26',
            courses: [],
            myCourses: [],
            myStudentCount: 0,
            schoolyearStats: null,
        }

        const chips = (Teaching as any).computed.headerChips.call(ctx)

        expect(chips).toEqual([
            { key: 'school', text: 'Christian-Doppler-Gymnasium Salzburg', icon: 'mdi-domain' },
            { key: 'schoolyear', text: '2025/26', icon: 'mdi-calendar-month-outline' },
        ])
    })

    it('opens settings through handleNavigation', () => {
        const routerReplace = vi.fn()
        const ctx = {
            isNavigationLocked: false,
            main_action: 'overview',
            settings_view_key: 0,
            $router: { replace: routerReplace },
            $route: { query: {} },
            navigateTo(section: string) {
                return (Teaching as any).methods.navigateTo.call(this, section)
            },
            openSettings: (Teaching as any).methods.openSettings,
        }

        ;(Teaching as any).methods.handleNavigation.call(ctx, 'settings')

        expect(ctx.main_action).toBe('settings')
        expect(ctx.settings_view_key).toBe(1)
        expect(routerReplace).toHaveBeenCalledWith({ path: '/admin/teaching/settings', query: {} })
    })

    it('does not navigate when controls are locked', () => {
        const routerReplace = vi.fn()
        const ctx = {
            isNavigationLocked: true,
            main_action: 'overview',
            settings_view_key: 0,
            $router: { replace: routerReplace },
            $route: { query: {} },
            navigateTo(section: string) {
                return (Teaching as any).methods.navigateTo.call(this, section)
            },
            openSettings: (Teaching as any).methods.openSettings,
        }

        ;(Teaching as any).methods.handleNavigation.call(ctx, 'search')

        expect(ctx.main_action).toBe('overview')
        expect(ctx.settings_view_key).toBe(0)
        expect(routerReplace).not.toHaveBeenCalled()
    })

    it('switches to non-settings target section when unlocked', () => {
        const routerReplace = vi.fn()
        const ctx = {
            isNavigationLocked: false,
            main_action: 'overview',
            settings_view_key: 0,
            $router: { replace: routerReplace },
            $route: { query: {} },
            navigateTo(section: string) {
                return (Teaching as any).methods.navigateTo.call(this, section)
            },
            openSettings: (Teaching as any).methods.openSettings,
        }

        ;(Teaching as any).methods.handleNavigation.call(ctx, 'search')

        expect(ctx.main_action).toBe('search')
        expect(ctx.settings_view_key).toBe(0)
        expect(routerReplace).toHaveBeenCalledWith({ path: '/admin/teaching/search', query: {} })
    })

    it('keeps the print overview panel as the rightmost item', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/Overview.vue', 'utf8')
        )

        expect(source).toContain("panels.push({ id: 'students', label: 'Schüler:innen', icon: 'mdi-account-group' })")
        expect(source).toContain("panels.push({ id: 'infos', label: 'Infos', icon: 'mdi-information-outline' })")
        expect(source).toContain("panels.push({ id: 'works', label: 'Arbeiten', icon: 'mdi-file-document-edit-outline' })")
        expect(source).toContain("panels.push({ id: 'dates', label: 'Termine', icon: 'mdi-calendar-clock-outline' })")
        expect(source).toContain("panels.push({ id: 'attendance', label: 'Anwesenheit', icon: 'mdi-table' })")
        expect(source).toContain("panels.push({ id: 'performances', label: 'Leistungen', icon: 'mdi-chart-line' })")
        expect(source).toContain("panels.push({ id: 'print', label: 'Druck', icon: 'mdi-printer-outline' })")
        expect(source.indexOf("panels.push({ id: 'performances', label: 'Leistungen', icon: 'mdi-chart-line' })"))
            .toBeLessThan(source.indexOf("panels.push({ id: 'print', label: 'Druck', icon: 'mdi-printer-outline' })"))
        expect(source).toContain("<CoursePrint />")
        expect(source).toContain("const validPanels = ['students', 'infos', 'works', 'print', 'dates', 'attendance', 'performances']")
        expect(source).toContain('v-if="(show_infos || show_dates || show_works || show_print) && action != \'teaching_course_new_or_edit\'"')
    })

    it('keeps the overview panel menu at full width', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/Overview.vue', 'utf8')
        )

        expect(source).toContain('<div class="teaching-overview-toolbar-width">')
        expect(source).toContain('.teaching-overview-toolbar-width {\n    width: 100%;\n}')
        expect(source).not.toContain('toolbarWidthClass')
        expect(source).not.toContain('toolbar-width-xl-')
    })
})
