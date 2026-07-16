import { beforeEach, describe, expect, it, vi } from 'vitest'
import Teaching from '@/pages/admin/teaching/Teaching.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolStore } from '@/stores/admin/SchoolStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/admin/SchoolStore', () => ({
    useSchoolStore: vi.fn(),
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
        vi.mocked(useCourseStore).mockReset()
        vi.mocked(useSchoolHourStore).mockReset()
    })

    it('loads only courses and school hours on beforeMount', async () => {
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

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useSchoolStore).mockReturnValue(schoolStoreMock as never)
        vi.mocked(useCourseStore).mockReturnValue(courseStoreMock as never)
        vi.mocked(useSchoolHourStore).mockReturnValue(schoolHourStoreMock as never)

        const ctx: Record<string, unknown> = {
            ensureCourseStore: (Teaching as any).methods.ensureCourseStore,
        }
        await (Teaching as any).beforeMount.call(ctx)

        expect(ctx.adminStore).toBe(adminStoreMock)
        expect(ctx.schoolStore).toBe(schoolStoreMock)
        expect(schoolStoreMock.loadHopperAccounts).not.toHaveBeenCalled()
        expect(courseStoreMock.index).toHaveBeenCalledTimes(1)
        expect(schoolHourStoreMock.index).toHaveBeenCalledTimes(1)
    })

    it('loads independent teaching page data concurrently', async () => {
        let resolveCourses: () => void = () => {}
        const coursesPromise = new Promise<void>((resolve) => {
            resolveCourses = resolve
        })
        const schoolStoreMock = {
            loadHopperAccounts: vi.fn(),
        }
        const courseStoreMock = {
            courses: [],
            index: vi.fn().mockReturnValue(coursesPromise),
        }
        const schoolHourStoreMock = {
            school_hours: [],
            index: vi.fn().mockResolvedValue(true),
        }

        vi.mocked(useAdminStore).mockReturnValue({ config: {} } as never)
        vi.mocked(useSchoolStore).mockReturnValue(schoolStoreMock as never)
        vi.mocked(useCourseStore).mockReturnValue(courseStoreMock as never)
        vi.mocked(useSchoolHourStore).mockReturnValue(schoolHourStoreMock as never)

        const ctx: Record<string, unknown> = {
            ensureCourseStore: (Teaching as any).methods.ensureCourseStore,
        }
        const beforeMountPromise = (Teaching as any).beforeMount.call(ctx)

        expect(schoolStoreMock.loadHopperAccounts).not.toHaveBeenCalled()
        expect(courseStoreMock.index).toHaveBeenCalledTimes(1)
        expect(schoolHourStoreMock.index).toHaveBeenCalledTimes(1)

        resolveCourses()
        await beforeMountPromise
    })

    it('skips loading courses and school hours when already present', async () => {
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

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useSchoolStore).mockReturnValue(schoolStoreMock as never)
        vi.mocked(useCourseStore).mockReturnValue(courseStoreMock as never)
        vi.mocked(useSchoolHourStore).mockReturnValue(schoolHourStoreMock as never)

        const ctx: Record<string, unknown> = {
            ensureCourseStore: (Teaching as any).methods.ensureCourseStore,
        }
        await (Teaching as any).beforeMount.call(ctx)

        expect(ctx.adminStore).toBe(adminStoreMock)
        expect(ctx.schoolStore).toBe(schoolStoreMock)
        expect(schoolStoreMock.loadHopperAccounts).not.toHaveBeenCalled()
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

        expect(items.map((item: { key: string }) => item.key)).toEqual(['overview', 'search', 'schoolyear', 'curricula'])
        expect(items.slice(0, 2).map((item: { label: string }) => item.label)).toEqual(['Unterricht', 'Suche'])
    })

    it('shows the teaching overview in admin navigation without the settings card', () => {
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

        expect(items.map((item: { key: string }) => item.key)).toEqual([
            'overview',
            'search',
            'schoolyear',
            'curricula',
            'datensicherung',
            'testumgebung',
        ])
    })

    it('opens the teaching table URL from the Unterricht navigation item', () => {
        const routerReplace = vi.fn()
        const resetTeachingOverviewSelection = vi.fn()
        const ctx = {
            isNavigationLocked: false,
            main_action: 'search',
            $router: { replace: routerReplace },
            resetTeachingOverviewSelection,
            openTeachingTable: (Teaching as any).methods.openTeachingTable,
        }

        ;(Teaching as any).methods.handleNavigation.call(ctx, 'overview')

        expect(resetTeachingOverviewSelection).toHaveBeenCalledOnce()
        expect(ctx.main_action).toBe('overview')
        expect(routerReplace).toHaveBeenCalledWith({
            path: '/admin/teaching',
            query: { panel: 'table' },
        })
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
        ])
    })

    it('builds the teaching header title with the active schoolyear', () => {
        const schoolyearContext = {
            config: {
                selected_schoolyear: {
                    name: 'Schuljahr 2026/27',
                    from: '2026-09-14',
                    until: '2027-07-09',
                },
            },
        }
        const selectedSchoolyearShortLabel = (Teaching as any).computed.selectedSchoolyearShortLabel.call(schoolyearContext)
        const titleContext = { selectedSchoolyearShortLabel }

        expect(selectedSchoolyearShortLabel).toBe('26/27')
        expect((Teaching as any).computed.teachingHeaderTitle.call(titleContext)).toBe('Lehrerbereich 26/27')
    })

    it('uses the plain teaching header title without a selected schoolyear', () => {
        const selectedSchoolyearShortLabel = (Teaching as any).computed.selectedSchoolyearShortLabel.call({ config: {} })
        const titleContext = { selectedSchoolyearShortLabel }

        expect(selectedSchoolyearShortLabel).toBe('')
        expect((Teaching as any).computed.teachingHeaderTitle.call(titleContext)).toBe('Lehrerbereich')
    })

    it('positions the semester marker within the active schoolyear', () => {
        const context = {
            config: {
                selected_schoolyear: {
                    from: '2026-09-01',
                    until: '2027-07-01',
                    sem_2_start: '2027-02-01',
                },
            },
        }

        const progress = (Teaching as any).computed.schoolyearSemesterStartProgress.call(context)

        expect(progress).toBeCloseTo(50.5, 2)
    })

    it('hides the semester marker when its date is missing or outside the schoolyear', () => {
        const missingDateContext = {
            config: {
                selected_schoolyear: {
                    from: '2026-09-01',
                    until: '2027-07-01',
                },
            },
        }
        const outsideSchoolyearContext = {
            config: {
                selected_schoolyear: {
                    from: '2026-09-01',
                    until: '2027-07-01',
                    sem_2_start: '2027-08-01',
                },
            },
        }

        expect((Teaching as any).computed.schoolyearSemesterStartProgress.call(missingDateContext)).toBeNull()
        expect((Teaching as any).computed.schoolyearSemesterStartProgress.call(outsideSchoolyearContext)).toBeNull()
    })

    it('shows progress for the current first semester', () => {
        const context = {
            config: {
                selected_schoolyear: {
                    from: '2026-09-01',
                    until: '2027-07-01',
                    sem_2_start: '2027-02-01',
                },
            },
            nowTs: new Date(2026, 10, 15).getTime(),
        }

        const currentSemesterStats = (Teaching as any).computed.currentSemesterStats.call(context)
        const label = (Teaching as any).computed.currentSemesterProgressLabel.call({ currentSemesterStats })

        expect(currentSemesterStats).toEqual({ semester: 1, progress: 50 })
        expect(label).toBe('1. Semester: 50% abgeschlossen')
    })

    it('shows progress for the current second semester', () => {
        const context = {
            config: {
                selected_schoolyear: {
                    from: '2026-09-01',
                    until: '2027-07-01',
                    sem_2_start: '2027-02-01',
                },
            },
            nowTs: new Date(2027, 2, 15).getTime(),
        }

        const currentSemesterStats = (Teaching as any).computed.currentSemesterStats.call(context)
        const label = (Teaching as any).computed.currentSemesterProgressLabel.call({ currentSemesterStats })

        expect(currentSemesterStats).toEqual({ semester: 2, progress: 28 })
        expect(label).toBe('2. Semester: 28% abgeschlossen')
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
            show_table: false,
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
            show_table: false,
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

    it('uses the reusable compact header without hopper schools or an overview card', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/Teaching.vue', 'utf8')
        )

        expect(source).toContain('<AdminCompactSectionHero')
        expect(source).toContain(':status-items="headerStatusItems"')
        expect(source).toContain(':progress-label="schoolyearProgressLabel"')
        expect(source).not.toContain('<AdminSectionHero')
        expect(source).not.toContain('Hopper-Schulen')
        expect(source).not.toContain('<template #chips>')
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

    it('mounts the course editor when editing the selected course', () => {
        const navigateTo = vi.fn()
        const ctx = {
            selected_course: { id: 16, title: '2B - DGB' },
            pending_edit_course_id: null,
            action: '',
            navigateTo,
        }

        ;(Teaching as any).methods.handleEditCourse.call(ctx)

        expect(navigateTo).toHaveBeenCalledWith('overview')
        expect(ctx.pending_edit_course_id).toBe(16)
        expect(ctx.action).toBe('teaching_course_new_or_edit')
    })

    it('keeps Anwesenheiten between Tabelle and Termine and print last in the overview panel menu', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/Overview.vue', 'utf8')
        )

        expect(source).toContain("panels.push({ id: 'students', label: 'Schüler:innen', icon: 'mdi-account-group' })")
        expect(source).toContain("panels.push({ id: 'infos', label: 'Infos', icon: 'mdi-information-outline' })")
        expect(source).toContain("panels.push({ id: 'works', label: 'Arbeiten', icon: 'mdi-file-document-edit-outline' })")
        expect(source).toContain("panels.push({ id: 'dates', label: 'Termine', icon: 'mdi-calendar-clock-outline' })")
        expect(source).toContain("panels.push({ id: 'table', label: 'Tabelle', icon: 'mdi-table-large' })")
        expect(source).toContain("panels.push({ id: 'curriculum', label: 'Curriculum', icon: 'mdi-book-open-variant' })")
        expect(source).toContain("panels.push({ id: 'attendance', label: 'Anwesenheiten', icon: 'mdi-account-check' })")
        expect(source).toContain("panels.push({ id: 'performances', label: 'Leistungen', icon: 'mdi-chart-line' })")
        expect(source).toContain("panels.push({ id: 'print', label: 'Druck', icon: 'mdi-printer-outline' })")
        expect(source.indexOf("panels.push({ id: 'table', label: 'Tabelle', icon: 'mdi-table-large' })"))
            .toBeLessThan(source.indexOf("panels.push({ id: 'attendance', label: 'Anwesenheiten', icon: 'mdi-account-check' })"))
        expect(source.indexOf("panels.push({ id: 'attendance', label: 'Anwesenheiten', icon: 'mdi-account-check' })"))
            .toBeLessThan(source.indexOf("panels.push({ id: 'dates', label: 'Termine', icon: 'mdi-calendar-clock-outline' })"))
        expect(source.indexOf("panels.push({ id: 'dates', label: 'Termine', icon: 'mdi-calendar-clock-outline' })"))
            .toBeLessThan(source.indexOf("panels.push({ id: 'students', label: 'Schüler:innen', icon: 'mdi-account-group' })"))
        expect(source.indexOf("panels.push({ id: 'students', label: 'Schüler:innen', icon: 'mdi-account-group' })"))
            .toBeLessThan(source.indexOf("panels.push({ id: 'infos', label: 'Infos', icon: 'mdi-information-outline' })"))
        expect(source.indexOf("panels.push({ id: 'infos', label: 'Infos', icon: 'mdi-information-outline' })"))
            .toBeLessThan(source.indexOf("panels.push({ id: 'works', label: 'Arbeiten', icon: 'mdi-file-document-edit-outline' })"))
        expect(source.indexOf("panels.push({ id: 'works', label: 'Arbeiten', icon: 'mdi-file-document-edit-outline' })"))
            .toBeLessThan(source.indexOf("panels.push({ id: 'curriculum', label: 'Curriculum', icon: 'mdi-book-open-variant' })"))
        expect(source.indexOf("panels.push({ id: 'curriculum', label: 'Curriculum', icon: 'mdi-book-open-variant' })"))
            .toBeLessThan(source.indexOf("panels.push({ id: 'performances', label: 'Leistungen', icon: 'mdi-chart-line' })"))
        expect(source.indexOf("panels.push({ id: 'performances', label: 'Leistungen', icon: 'mdi-chart-line' })"))
            .toBeLessThan(source.indexOf("panels.push({ id: 'print', label: 'Druck', icon: 'mdi-printer-outline' })"))
        expect(source).toContain("<CoursePrint />")
        const validPanels = source.match(/const validPanels = \[([\s\S]*?)\]/)?.[1] || ''
        expect(validPanels).toContain("'table'")
        expect(validPanels).toContain("'attendance'")
        expect(source).toContain('v-if="selected_course && secondaryOverviewPanelSelection && action != \'teaching_course_new_or_edit\'"')
        expect(source).toContain('v-if="secondaryOverviewPanelSelection === \'table\'" class="mt-n6"')
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

    it('lets the students, attendance, dates, and table panels use the full content width', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/Overview.vue', 'utf8')
        )

        expect(source.match(/class="teaching-overview-card-col"/g)).toHaveLength(2)
        expect(source).toContain(':md="selected_course && show_students ? 12 : 8"')
        expect(source).toContain(':lg="selected_course && show_students ? 12 : 7"')
        expect(source).toContain(':xl="selected_course && show_students ? 12 : 6"')
        expect(source).toContain(':md="[\'attendance\', \'dates\', \'table\'].includes(secondaryOverviewPanelSelection) ? 12 : 8"')
        expect(source).toContain(':lg="[\'attendance\', \'dates\', \'table\'].includes(secondaryOverviewPanelSelection) ? 12 : 7"')
        expect(source).toContain(':xl="[\'attendance\', \'dates\', \'table\'].includes(secondaryOverviewPanelSelection) ? 12 : 6"')
        expect(source).not.toContain('isGradesMode')
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

    it('keeps the course list visible with an empty state when no courses exist', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/Teaching.vue', 'utf8')
        )

        expect(source).toContain('v-if="main_action === \'overview\'"')
        expect(source).not.toContain('v-if="courses.length && main_action === \'overview\'"')
        expect(source).toContain('v-if="!courses.length" class="teaching-subnav__empty" role="status"')
        expect(source).toContain('Noch keine Fächer vorhanden.')
        expect(source).toContain('.teaching-subnav__empty {')
    })

    it('shows a problem marker beside course names in the teaching subnav', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/Teaching.vue', 'utf8')
        )

        expect(source).toContain('v-if="courseStore?.courseHasProblems(course)"')
        expect(source).toContain('aria-label="Probleme im Fach">!</span>')
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
