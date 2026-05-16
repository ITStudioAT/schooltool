import { beforeEach, describe, expect, it, vi } from 'vitest'
import Teaching from '@/pages/admin/teaching/Teaching.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolStore } from '@/stores/admin/SchoolStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/admin/SchoolStore', () => ({
    useSchoolStore: vi.fn(),
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
        vi.mocked(useSchoolStore).mockReset()
        vi.mocked(useTeachingStore).mockReset()
        vi.mocked(useCourseStore).mockReset()
        vi.mocked(useSchoolHourStore).mockReset()
    })

    it('loads teaching settings on beforeMount when missing', async () => {
        const adminStoreMock = { config: {} }
        const schoolStoreMock = {
            loadHopperAccounts: vi.fn().mockResolvedValue([]),
        }
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
        vi.mocked(useSchoolStore).mockReturnValue(schoolStoreMock as never)
        vi.mocked(useCourseStore).mockReturnValue(courseStoreMock as never)
        vi.mocked(useSchoolHourStore).mockReturnValue(schoolHourStoreMock as never)
        vi.mocked(useTeachingStore).mockReturnValue(teachingStoreMock as never)

        const ctx: Record<string, unknown> = {
            ensureCourseStore: (Teaching as any).methods.ensureCourseStore,
        }
        await (Teaching as any).beforeMount.call(ctx)

        expect(ctx.adminStore).toBe(adminStoreMock)
        expect(ctx.schoolStore).toBe(schoolStoreMock)
        expect(schoolStoreMock.loadHopperAccounts).toHaveBeenCalledTimes(1)
        expect(teachingStoreMock.loadSettings).toHaveBeenCalledTimes(1)
        expect(courseStoreMock.index).toHaveBeenCalledTimes(1)
        expect(schoolHourStoreMock.index).toHaveBeenCalledTimes(1)
    })

    it('skips loading teaching settings on beforeMount when already present', async () => {
        const adminStoreMock = { config: {} }
        const schoolStoreMock = {
            loadHopperAccounts: vi.fn().mockResolvedValue([]),
        }
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
        vi.mocked(useSchoolStore).mockReturnValue(schoolStoreMock as never)
        vi.mocked(useCourseStore).mockReturnValue(courseStoreMock as never)
        vi.mocked(useSchoolHourStore).mockReturnValue(schoolHourStoreMock as never)
        vi.mocked(useTeachingStore).mockReturnValue(teachingStoreMock as never)

        const ctx: Record<string, unknown> = {
            ensureCourseStore: (Teaching as any).methods.ensureCourseStore,
        }
        await (Teaching as any).beforeMount.call(ctx)

        expect(ctx.adminStore).toBe(adminStoreMock)
        expect(ctx.schoolStore).toBe(schoolStoreMock)
        expect(schoolStoreMock.loadHopperAccounts).toHaveBeenCalledTimes(1)
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

        expect(items.map((item: { key: string }) => item.key)).toEqual(['overview', 'search', 'schoolyear', 'curricula', 'settings'])
    })

    it('shows datensicherung behind settings for teaching admins', () => {
        const ctx = {
            config: {
                roles: ['teaching_admin'],
                selected_schoolyear: { name: '2025/26' },
            },
            hasAnyRole(requiredRoles: string[]) {
                return (Teaching as any).methods.hasAnyRole.call(this, requiredRoles)
            },
            selectedSchoolyearLabel: '2025/26',
        }

        const items = (Teaching as any).computed.visibleNavigationItems.call(ctx)

        expect(items.map((item: { key: string }) => item.key)).toEqual(['overview', 'search', 'schoolyear', 'curricula', 'settings', 'datensicherung'])
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

    it('opens the clean teaching route on the overview instead of a previously selected course', () => {
        const courseStore = {
            selected_course: { id: 9, title: 'Mathematik' },
            selected_course_id: 9,
            selected_course_student: { id: 17 },
            show_students: false,
            show_infos: false,
            show_works: true,
            show_print: false,
            show_dates: false,
            show_curriculum: false,
            show_attendance: false,
            show_performances: false,
            show_performances_plus: false,
        }
        const ctx = {
            _urlRestored: false,
            courseStore,
            selected_courseDate: { id: 44 },
            action_2: 'course_student_view',
            $route: { query: {} },
            ensureCourseStore() {
                return courseStore
            },
            resetTeachingOverviewSelection: (Teaching as any).methods.resetTeachingOverviewSelection,
        }

        ;(Teaching as any).watch.courses.handler.call(ctx, [{ id: 9, title: 'Mathematik' }])

        expect(ctx._urlRestored).toBe(true)
        expect(courseStore.selected_course).toBeNull()
        expect(courseStore.selected_course_id).toBeNull()
        expect(courseStore.selected_course_student).toBeNull()
        expect(courseStore.show_students).toBe(true)
        expect(courseStore.show_works).toBe(false)
        expect(ctx.selected_courseDate).toBeNull()
        expect(ctx.action_2).toBe('')
    })

    it('opens the clean teaching route during client navigation when courses are already cached', () => {
        const courseStore = {
            selected_course: { id: 9, title: 'Mathematik' },
            selected_course_id: 9,
            selected_course_student: { id: 17 },
            show_students: false,
            show_infos: false,
            show_works: true,
            show_print: false,
            show_dates: false,
            show_curriculum: false,
            show_attendance: false,
            show_performances: false,
            show_performances_plus: false,
        }
        const ctx: Record<string, any> = {
            _urlRestored: false,
            courseStore: null,
            selected_courseDate: { id: 44 },
            action_2: 'course_student_view',
            $route: { query: {} },
            ensureCourseStore: (Teaching as any).methods.ensureCourseStore,
            resetTeachingOverviewSelection: (Teaching as any).methods.resetTeachingOverviewSelection,
        }

        vi.mocked(useCourseStore).mockReturnValue(courseStore as never)

        ;(Teaching as any).watch.courses.handler.call(ctx, [{ id: 9, title: 'Mathematik' }])

        expect(ctx.courseStore).toBe(courseStore)
        expect(ctx._urlRestored).toBe(true)
        expect(courseStore.selected_course).toBeNull()
        expect(courseStore.selected_course_id).toBeNull()
        expect(courseStore.selected_course_student).toBeNull()
        expect(courseStore.show_students).toBe(true)
        expect(courseStore.show_works).toBe(false)
        expect(ctx.selected_courseDate).toBeNull()
        expect(ctx.action_2).toBe('')
    })

    it('opens the datensicherung page from navigation', () => {
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

        ;(Teaching as any).methods.handleNavigation.call(ctx, 'datensicherung')

        expect(ctx.main_action).toBe('datensicherung')
        expect(routerReplace).toHaveBeenCalledWith({ path: '/admin/teaching/datensicherung', query: {} })
    })

    it('renders the datensicherung navigation item and component hook', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/Teaching.vue', 'utf8')
        )

        expect(source).toContain("key: 'datensicherung'")
        expect(source).toContain("label: 'Datensicherung'")
        expect(source).toContain('<DataBackup v-if="main_action === \'datensicherung\'" />')
        expect(source).toContain("const DataBackup = defineAsyncComponent(() => import('./backup/DataBackup.vue'))")
    })

    it('renders hopper schools inside the teaching header', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/Teaching.vue', 'utf8')
        )

        expect(source).toContain('<template #chips>')
        expect(source).toContain('Hopper-Schulen')
        expect(source).toContain('v-for="account in hopper_accounts"')
        expect(source).toContain('@click="switchTeachingHopperAccount(account)"')
    })

    it('switches to a teaching hopper account and redirects to teaching', async () => {
        const switchHopperAccount = vi.fn().mockResolvedValue(true)
        const redirectToTeachingAfterHopperSwitch = vi.fn()
        const ctx = {
            isNavigationLocked: false,
            hopper_switching_id: null,
            schoolStore: { switchHopperAccount },
            redirectToTeachingAfterHopperSwitch,
        }

        await (Teaching as any).methods.switchTeachingHopperAccount.call(ctx, { id: 42 })

        expect(ctx.hopper_switching_id).toBeNull()
        expect(switchHopperAccount).toHaveBeenCalledWith(42)
        expect(redirectToTeachingAfterHopperSwitch).toHaveBeenCalledTimes(1)
    })

    it('does not switch hopper accounts while teaching controls are locked', async () => {
        const switchHopperAccount = vi.fn()
        const redirectToTeachingAfterHopperSwitch = vi.fn()
        const ctx = {
            isNavigationLocked: true,
            hopper_switching_id: null,
            schoolStore: { switchHopperAccount },
            redirectToTeachingAfterHopperSwitch,
        }

        await (Teaching as any).methods.switchTeachingHopperAccount.call(ctx, { id: 42 })

        expect(ctx.hopper_switching_id).toBeNull()
        expect(switchHopperAccount).not.toHaveBeenCalled()
        expect(redirectToTeachingAfterHopperSwitch).not.toHaveBeenCalled()
    })

    it('opens a fresh new-course flow from the teaching subnav plus button', () => {
        const routerReplace = vi.fn()
        const ctx = {
            selected_course: { id: 9, title: 'Mathematik' },
            selected_course_id: 9,
            selected_course_student: { id: 77 },
            pending_edit_course_id: 9,
            pending_new_course_token: 2,
            action_2: 'course_student_view',
            action: '',
            $router: { replace: routerReplace },
            $route: { query: { course: '9' } },
            navigateTo(section: string) {
                return (Teaching as any).methods.navigateTo.call(this, section)
            },
        }

        ;(Teaching as any).methods.handleNewCourse.call(ctx)

        expect(ctx.selected_course).toBeNull()
        expect(ctx.selected_course_id).toBeNull()
        expect(ctx.selected_course_student).toBeNull()
        expect(ctx.pending_edit_course_id).toBeNull()
        expect(ctx.pending_new_course_token).toBe(3)
        expect(ctx.action_2).toBe('')
        expect(ctx.main_action).toBe('overview')
        expect(ctx.action).toBe('teaching_course_new_or_edit')
        expect(routerReplace).toHaveBeenCalledWith({ path: '/admin/teaching', query: { course: '9' } })
    })

    it('keeps the print overview panel as the rightmost item', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/Overview.vue', 'utf8')
        )

        expect(source).toContain("panels.push({ id: 'students', label: 'Schüler:innen', icon: 'mdi-account-group' })")
        expect(source).toContain("panels.push({ id: 'infos', label: 'Infos', icon: 'mdi-information-outline' })")
        expect(source).toContain("panels.push({ id: 'works', label: 'Arbeiten', icon: 'mdi-file-document-edit-outline' })")
        expect(source).toContain("panels.push({ id: 'dates', label: 'Termine', icon: 'mdi-calendar-clock-outline' })")
        expect(source).toContain("panels.push({ id: 'curriculum', label: 'Curriculum', icon: 'mdi-book-open-variant' })")
        expect(source).toContain("panels.push({ id: 'attendance', label: 'Anwesenheit', icon: 'mdi-table' })")
        expect(source).toContain("panels.push({ id: 'performances', label: 'Leistungen', icon: 'mdi-chart-line' })")
        expect(source).toContain("panels.push({ id: 'print', label: 'Druck', icon: 'mdi-printer-outline' })")
        expect(source.indexOf("panels.push({ id: 'students', label: 'Schüler:innen', icon: 'mdi-account-group' })"))
            .toBeLessThan(source.indexOf("panels.push({ id: 'dates', label: 'Termine', icon: 'mdi-calendar-clock-outline' })"))
        expect(source.indexOf("panels.push({ id: 'dates', label: 'Termine', icon: 'mdi-calendar-clock-outline' })"))
            .toBeLessThan(source.indexOf("panels.push({ id: 'infos', label: 'Infos', icon: 'mdi-information-outline' })"))
        expect(source.indexOf("panels.push({ id: 'infos', label: 'Infos', icon: 'mdi-information-outline' })"))
            .toBeLessThan(source.indexOf("panels.push({ id: 'works', label: 'Arbeiten', icon: 'mdi-file-document-edit-outline' })"))
        expect(source.indexOf("panels.push({ id: 'works', label: 'Arbeiten', icon: 'mdi-file-document-edit-outline' })"))
            .toBeLessThan(source.indexOf("panels.push({ id: 'curriculum', label: 'Curriculum', icon: 'mdi-book-open-variant' })"))
        expect(source.indexOf("panels.push({ id: 'curriculum', label: 'Curriculum', icon: 'mdi-book-open-variant' })"))
            .toBeLessThan(source.indexOf("panels.push({ id: 'attendance', label: 'Anwesenheit', icon: 'mdi-table' })"))
        expect(source.indexOf("panels.push({ id: 'performances', label: 'Leistungen', icon: 'mdi-chart-line' })"))
            .toBeLessThan(source.indexOf("panels.push({ id: 'print', label: 'Druck', icon: 'mdi-printer-outline' })"))
        expect(source).toContain("<CoursePrint />")
        expect(source).toContain("const validPanels = ['students', 'dates', 'infos', 'works', 'print', 'curriculum', 'attendance', 'performances', 'performances_plus']")
        expect(source).toContain('v-if="selected_course && secondaryOverviewPanelSelection && action != \'teaching_course_new_or_edit\'"')
        expect(source).toContain('v-if="secondaryOverviewPanelSelection === \'curriculum\'" class="mt-n6"')
        expect(source).toContain('data-testid="teaching-curriculum-card"')
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

    it('uses the same overview card column width for students and secondary panels', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/Overview.vue', 'utf8')
        )

        expect(source.match(/class="teaching-overview-card-col"/g)).toHaveLength(2)
        expect(source).toContain(':md="isGradesMode ? 8 : 6"')
        expect(source).toContain(':lg="7"')
        expect(source).toContain(':xl="isGradesMode ? 6 : 4"')
        expect(source).toContain('.teaching-overview-card-col {')
        expect(source).toContain('flex-grow: 0;')
    })

    it('renders course date contents in normal font weight', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/components/CourseDates.vue', 'utf8')
        )

        expect(source).toContain('.content-readonly,')
        expect(source).toContain('.content-readonly :deep(p),')
        expect(source).toContain('.content-readonly :deep(li),')
        expect(source).toContain('font-weight: 400;')
    })

    it('renders the course clear action as a colored tonal button', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/Teaching.vue', 'utf8')
        )

        expect(source).toContain('prepend-icon="mdi-arrow-left"')
        expect(source).toContain('variant="tonal"')
        expect(source).toContain('teaching-subnav__course-btn--overview')
        expect(source).toContain('Übersicht')
        expect(source).not.toContain('icon="mdi-close"\n                    variant="tonal"\n                    color="secondary"\n                    density="compact"')
        expect(source).not.toContain('v-if="selected_course"\n                    size="small"\n                    icon="mdi-close"')
    })

    it('renders the teaching subnav action colors as configured', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/Teaching.vue', 'utf8')
        )

        expect(source).toContain('icon="mdi-pencil-outline"')
        expect(source).toContain('color="primary"')
        expect(source).toContain('title="Fach bearbeiten"')
        expect(source).toContain('icon="mdi-delete-outline"')
        expect(source).toContain('color="warning"')
        expect(source).toContain('title="Fach löschen"')
        expect(source).toContain('icon="mdi-plus"')
        expect(source).toContain('color="success"')
        expect(source).toContain('title="Neues Fach anlegen"')
    })

    it('stacks subnav actions below a two-column course grid on small screens', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/Teaching.vue', 'utf8')
        )

        expect(source).toContain('@media (max-width: 960px)')
        expect(source).toContain('.teaching-subnav__inner {')
        expect(source).toContain('flex-direction: column;')
        expect(source).toContain('.teaching-subnav__courses {')
        expect(source).toContain('display: grid;')
        expect(source).toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        expect(source).toContain('.teaching-subnav__course-btn--overview {')
        expect(source).toContain('grid-column: 1 / -1;')
        expect(source).toContain('.teaching-subnav__actions {')
        expect(source).toContain('margin-left: 0 !important;')
        expect(source).toContain('width: 100%;')
    })

    it('uses higher-contrast classes for teaching navigation buttons', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/Teaching.vue', 'utf8')
        )

        expect(source).toContain(":class=\"main_action === item.key ? 'teaching-nav__button--active' : 'teaching-nav__button--idle'\"")
        expect(source).toContain('.teaching-nav__button--idle {')
        expect(source).toContain('.teaching-nav__button--active {')
    })

    it('uses higher-contrast classes for the overview panel switcher', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/Overview.vue', 'utf8')
        )

        expect(source).toContain('.teaching-overview-panel-switcher {')
        expect(source).toContain('flex-wrap: wrap;')
        expect(source).toContain('@media (max-width: 700px)')
        expect(source).toContain('display: grid !important;')
        expect(source).toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        expect(source).toContain('.teaching-overview-toolbar-btn {')
        expect(source).toContain('width: 100%;')
        expect(source).toContain('.teaching-overview-toolbar-btn :deep(.v-btn__content)')
        expect(source).toContain('.teaching-overview-toolbar-btn.v-btn--selected {')
        expect(source).toContain('.teaching-overview-toolbar-btn:hover {')
    })
})
