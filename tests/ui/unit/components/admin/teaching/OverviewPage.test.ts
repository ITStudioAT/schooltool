import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { shallowMount } from '@vue/test-utils'
import { nextTick } from 'vue'
import Overview from '@/pages/admin/teaching/overview/Overview.vue'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'

describe('Teaching overview controls', () => {
    it('keeps the timetable grid rows stable before and after clearing a selected course', async () => {
        setActivePinia(createPinia())
        const courseStore = useCourseStore()
        const wrapper = shallowMount({
            ...Overview,
            components: {
                ...(Overview as any).components,
                MyTimetable: { name: 'MyTimetable', template: '<div />' },
                CourseStudents: { name: 'CourseStudents', template: '<div />' },
            },
        }, {
            global: {
                mocks: { $route: { query: { panel: 'students' } }, $router: { replace: vi.fn().mockResolvedValue(undefined) } },
                stubs: {
                    'v-row': { template: '<div class="test-grid-row"><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' },
                    'v-dialog': true,
                    'v-autocomplete': true,
                },
            },
        })
        try {
            expect(courseStore.show_students).toBe(true)
            expect(wrapper.findAll('.test-grid-row')).toHaveLength(1)
            expect(wrapper.findComponent({ name: 'CourseStudents' }).exists()).toBe(false)

            courseStore.selected_course = { id: 18, details_loaded: true, course_dates: [] } as any
            await nextTick()
            expect(wrapper.findComponent({ name: 'CourseStudents' }).exists()).toBe(true)

            courseStore.selected_course = null
            courseStore.show_students = false
            await nextTick()
            expect(wrapper.findAll('.test-grid-row')).toHaveLength(1)
            expect(wrapper.findComponent({ name: 'CourseStudents' }).exists()).toBe(false)
        } finally {
            wrapper.unmount()
        }
    })

    it.each([7, null])('saves curriculum assignment %s without clearing adopted lesson content', async (curriculumId) => {
        const course = { id: 18, teaching_curriculum_id: 4, course_dates: [{ id: 2, adopted_materials: [{ id: 9 }] }] }
        const update = vi.fn().mockResolvedValue(true)
        const refreshCourseById = vi.fn().mockResolvedValue(true)
        const ctx = {
            selected_course: course, curriculumSaveLoading: false, curriculumEditMode: true,
            normalizedCurriculumSelectionId: curriculumId, selectedCourseCurriculumId: curriculumId,
            curriculumSelectionId: curriculumId, courseStore: { update, refreshCourseById },
        }
        await (Overview as any).methods.saveCurriculumAssignment.call(ctx)
        expect(update).toHaveBeenCalledWith({ ...course, teaching_curriculum_id: curriculumId })
        expect(refreshCourseById).toHaveBeenCalledWith(18)
        expect(ctx.curriculumEditMode).toBe(false)
        expect(ctx.curriculumSaveLoading).toBe(false)
    })

    it('keeps curriculum selection open when saving fails', async () => {
        const ctx = {
            selected_course: { id: 18 }, curriculumSaveLoading: false, curriculumEditMode: true,
            normalizedCurriculumSelectionId: 7,
            courseStore: { update: vi.fn().mockResolvedValue(false), refreshCourseById: vi.fn() },
        }
        await (Overview as any).methods.saveCurriculumAssignment.call(ctx)
        expect(ctx.curriculumEditMode).toBe(true)
        expect(ctx.courseStore.refreshCourseById).not.toHaveBeenCalled()
    })

    it('opens curriculum selection with the current assignment', async () => {
        const ctx = {
            selected_course: { id: 18 }, selectedCourseCurriculumId: 4,
            loadCurricula: vi.fn().mockResolvedValue(true), curriculumEditMode: false,
            loadCurriculumPreview: vi.fn().mockResolvedValue(undefined),
            curriculumSelectionId: null,
        }
        await (Overview as any).methods.startCurriculumEdit.call(ctx)
        expect(ctx.loadCurricula).toHaveBeenCalledOnce()
        expect(ctx.curriculumEditMode).toBe(true)
        expect(ctx.curriculumSelectionId).toBe(4)
        expect(ctx.loadCurriculumPreview).toHaveBeenCalledOnce()
    })

    it('loads the full selected curriculum for the assignment dialog', async () => {
        const curriculum = { id: 4, title: 'DGB 3', topics: [{ title: 'Topic', units: [{ title: 'Unit' }] }] }
        const context = {
            curriculumPreviewRequestId: 0, normalizedCurriculumSelectionId: 4,
            selected_course: { id: 18 }, curriculumEditMode: true,
            curriculumPreview: null, curriculumPreviewLoading: false,
            curriculumStore: { show: vi.fn().mockResolvedValue(curriculum) },
        }
        await (Overview as any).methods.loadCurriculumPreview.call(context)
        expect(context.curriculumStore.show).toHaveBeenCalledWith(4)
        expect(context.curriculumPreview).toEqual(curriculum)
        expect(context.curriculumPreviewLoading).toBe(false)
    })

    it('ignores a previous preview response after selecting another curriculum', async () => {
        let resolveOld: (value: unknown) => void = () => {}
        const context = {
            curriculumPreviewRequestId: 0, normalizedCurriculumSelectionId: 4,
            selected_course: { id: 18 }, curriculumEditMode: true,
            curriculumPreview: null, curriculumPreviewLoading: false,
            curriculumStore: { show: vi.fn()
                .mockImplementationOnce(() => new Promise(resolve => { resolveOld = resolve }))
                .mockResolvedValueOnce({ id: 5, topics: [] }) },
        }
        const oldRequest = (Overview as any).methods.loadCurriculumPreview.call(context)
        context.normalizedCurriculumSelectionId = 5
        await (Overview as any).methods.loadCurriculumPreview.call(context)
        resolveOld({ id: 4 })
        await oldRequest
        expect(context.curriculumPreview).toEqual({ id: 5, topics: [] })
    })

    it('renders all selected curriculum topics and units inside the scrollable dialog', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/Overview.vue'), 'utf8')
        expect(source).toContain('persistent scrollable max-width="800"')
        expect(source).toContain('@update:model-value="loadCurriculumPreview"')
        expect(source).toContain('v-for="(topic, topicIndex) in curriculumPreview.topics || []"')
        expect(source).toContain('v-for="(unit, unitIndex) in topic.units || []"')
        expect(source).toContain('v-for="material in unit.materials || []"')
    })

    it('lazy loads inactive panels and mounts the course editor only when opened', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/Overview.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("const CourseTable = defineAsyncComponent(() => import('./components/CourseTable.vue'))")
        expect(source).toContain("const CourseWorks = defineAsyncComponent(() => import('./components/CourseWorks.vue'))")
        expect(source).not.toContain('AttendanceMatrix')
        expect(source).toContain('v-if="action === \'teaching_course_new_or_edit\'" class="d-none"')
        expect(source).not.toContain("import CourseWorks from './components/CourseWorks.vue'")
        expect(source).not.toContain('style="display:none"')
    })

    it('refreshes courses and school hours together', async () => {
        let resolveCourses: () => void = () => {}
        const coursePromise = new Promise<void>((resolve) => {
            resolveCourses = resolve
        })
        const courseIndex = vi.fn().mockReturnValue(coursePromise)
        const schoolHourIndex = vi.fn().mockResolvedValue(true)
        const ctx = {
            courseStore: { index: courseIndex },
            schoolHourStore: { index: schoolHourIndex },
        }

        const refreshPromise = (Overview as any).methods.refreshOverviewData.call(ctx)

        expect(courseIndex).toHaveBeenCalledTimes(1)
        expect(schoolHourIndex).toHaveBeenCalledTimes(1)

        resolveCourses()
        await refreshPromise
    })

    it('loads full course details when a summary is selected', async () => {
        const loadCourseDetails = vi.fn().mockResolvedValue({ id: 22, details_loaded: true })
        const ctx = {
            courseStore: { loadCourseDetails },
        }

        await (Overview as any).watch.selected_course.handler.call(ctx, {
            id: 22,
            title: 'Physik',
            details_loaded: false,
        })

        expect(loadCourseDetails).toHaveBeenCalledOnce()
        expect(loadCourseDetails).toHaveBeenCalledWith(22)
    })

    it('resets to the table panel when selected course changes', () => {
        const ctx = {
            show_students: false,
            show_infos: true,
            show_works: true,
            show_dates: true,
            show_table: true,
            show_curriculum: true,
            show_attendance: true,
            show_performances: true,
            show_performances_plus: true,
            _urlPanelRestored: true,
            _lastCourseId: 11,
        }

        ;(Overview as any).watch.selected_course.handler.call(ctx, {
            id: 22,
            title: 'Physik',
            details_loaded: true,
        })

        expect(ctx.show_students).toBe(false)
        expect(ctx.show_infos).toBe(false)
        expect(ctx.show_works).toBe(false)
        expect(ctx.show_dates).toBe(false)
        expect(ctx.show_table).toBe(true)
        expect(ctx.show_curriculum).toBe(false)
        expect(ctx.show_attendance).toBe(false)
        expect(ctx.show_performances).toBe(false)
    })

    it('moves the legacy table attendance view to the attendance panel', () => {
        const routerReplace = vi.fn().mockResolvedValue(undefined)
        const ctx = {
            selectedCourseCurriculumId: null,
            curriculumSelectionId: null,
            curriculumEditMode: false,
            show_students: true,
            show_infos: false,
            show_works: false,
            show_print: false,
            show_dates: false,
            show_table: false,
            show_curriculum: false,
            show_attendance: false,
            show_performances: false,
            show_performances_plus: false,
            _urlPanelRestored: false,
            _lastCourseId: null,
            $route: {
                path: '/admin/teaching',
                query: { course: '16', panel: 'table', view: 'attendance' },
            },
            $router: { replace: routerReplace },
        }

        ;(Overview as any).watch.selected_course.handler.call(ctx, {
            id: 16,
            title: 'Deutsch',
            details_loaded: true,
        })

        expect(ctx.show_students).toBe(false)
        expect(ctx.show_table).toBe(false)
        expect(ctx.show_attendance).toBe(true)
        expect(ctx.show_performances).toBe(false)
        expect(routerReplace).toHaveBeenCalledWith({
            path: '/admin/teaching',
            query: { course: '16', panel: 'attendance' },
        })
    })

    it('maps functionalPanelSelection getter to active panel', () => {
        const ctx = {
            selected_course: { id: 7, title: 'Biologie' },
            show_students: false,
            show_infos: true,
            show_works: false,
            show_dates: false,
            show_table: false,
            show_attendance: false,
            show_performances: false,
        }

        const active = (Overview as any).computed.functionalPanelSelection.get.call(ctx)

        expect(active).toBe('infos')
    })

    it('marks the dates and table menu items when the selected course has no dates', () => {
        const computed = (Overview as any).computed
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/Overview.vue'), 'utf8')

        expect(computed.selectedCourseHasNoDates.call({
            selected_course: { course_dates: [] },
        })).toBe(true)
        expect(computed.selectedCourseHasNoDates.call({
            selected_course: { course_dates: [{ id: 1 }] },
        })).toBe(false)
        expect(computed.selectedCourseHasNoDates.call({
            selected_course: {},
        })).toBe(false)
        expect(source).toContain("['dates', 'table'].includes(panel.id) && selectedCourseHasNoDates")
        expect(source).toContain('aria-label="Keine Termine vorhanden">!</span>')
    })

    it('toggles mirrored panel flags through functionalPanelSelection setter', () => {
        const ctx = {
            show_students: true,
            show_infos: false,
            show_works: false,
            show_dates: false,
            show_table: false,
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
        expect(ctx.show_table).toBe(false)
        expect(ctx.show_curriculum).toBe(false)
        ;(Overview as any).computed.functionalPanelSelection.set.call(ctx, null)
        expect(ctx.show_performances).toBe(false)
        expect(ctx.action_2).toBe('')
        expect(ctx.selected_course_student).toBeNull()
    })

    it('opens attendance as its own panel and removes the old table view query', () => {
        const routerReplace = vi.fn().mockResolvedValue(undefined)
        const ctx = {
            show_students: true,
            show_infos: false,
            show_works: false,
            show_print: false,
            show_dates: false,
            show_table: true,
            show_curriculum: false,
            show_attendance: false,
            show_performances: false,
            show_performances_plus: false,
            action_2: '',
            selected_course_student: null,
            $route: {
                path: '/admin/teaching',
                query: { course: '16', panel: 'table', view: 'attendance' },
            },
            $router: { replace: routerReplace },
        }

        ;(Overview as any).computed.functionalPanelSelection.set.call(ctx, 'attendance')

        expect(ctx.show_table).toBe(false)
        expect(ctx.show_attendance).toBe(true)
        expect(routerReplace).toHaveBeenCalledWith({
            path: '/admin/teaching',
            query: { course: '16', panel: 'attendance' },
        })
    })

    it('redirects legacy curriculum panel links to students', () => {
        const routerReplace = vi.fn().mockResolvedValue(undefined)
        const ctx = {
            selectedCourseCurriculumId: null,
            curriculumSelectionId: null,
            curriculumEditMode: false,
            show_students: true,
            show_infos: false,
            show_works: false,
            show_dates: false,
            show_table: false,
            show_curriculum: false,
            show_attendance: false,
            show_performances: false,
            show_performances_plus: false,
            _urlPanelRestored: false,
            _lastCourseId: null,
            $route: {
                path: '/admin/teaching',
                query: { course: '18', panel: 'curriculum', date: '318' },
            },
            $router: { replace: routerReplace },
        }

        ;(Overview as any).watch.selected_course.handler.call(ctx, {
            id: 18,
            title: 'Deutsch',
            details_loaded: true,
        })

        expect(ctx.show_students).toBe(true)
        expect(ctx.show_infos).toBe(false)
        expect(ctx.show_works).toBe(false)
        expect(ctx.show_dates).toBe(false)
        expect(ctx.show_curriculum).toBe(false)
        expect(ctx.show_attendance).toBe(false)
        expect(ctx.show_performances).toBe(false)
        expect(ctx.show_performances_plus).toBe(false)
        expect(routerReplace).toHaveBeenCalledWith({
            path: '/admin/teaching',
            query: { course: '18', panel: 'students', date: '318' },
        })
    })

    it('does not render dates as a secondary panel while students is active', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/Overview.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const computed = (Overview as any).computed
        const ctx: Record<string, unknown> = {
            selected_course: { id: 20 },
            show_students: true,
            show_infos: false,
            show_works: false,
            show_print: false,
            show_dates: true,
            show_table: false,
            show_curriculum: false,
            show_attendance: false,
            show_performances: false,
            show_performances_plus: false,
            isGradesMode: false,
        }

        ctx.functionalPanelSelection = computed.functionalPanelSelection.get.call(ctx)
        ctx.secondaryOverviewPanelSelection = computed.secondaryOverviewPanelSelection.call(ctx)

        expect(ctx.functionalPanelSelection).toBe('students')
        expect(ctx.secondaryOverviewPanelSelection).toBeNull()
        expect(source).not.toContain('<CourseDates compact-student-view />')
        expect(source).not.toContain('<v-row v-if="show_students">')
        expect(source).toContain(':md="[\'attendance\', \'dates\', \'table\'].includes(secondaryOverviewPanelSelection) ? 12 : 8"')
        expect(source).toContain('v-if="secondaryOverviewPanelSelection === \'dates\'" class="mt-n6"')
        expect(source).toContain('v-if="secondaryOverviewPanelSelection === \'table\'" class="mt-n6"')
        expect(source).toContain('view="entries"')
        expect(source).toContain('view="attendance"')
        expect(source).toContain(':active-semester="activeSemester"')
        expect(source).toContain(':semester-two-start-date="sem2StartDate"')
    })

    it.skip('aligns removed course-date curriculum scheduling by week', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: Record<string, any> = {
            config: {
                selected_schoolyear: {
                    from: '2025-09-01',
                    until: '2025-09-30',
                },
            },
            selected_course: {
                course_dates: [
                    { id: 1, date: '2025-09-03', hours: [3], content: '<p>Projekt: Internetrecherche</p>', status: ['free'] },
                ],
                teaching_curriculum: {
                    id: 10,
                    free_weeks: ['2025-09-15'],
                    topics: [
                        {
                            id: 'topic-1',
                            title: 'Grammatik',
                            assignment_type: 'weeks',
                            week_keys: ['2025-09-01'],
                            units: [
                                {
                                    id: 'unit-1',
                                    title: 'Satzglieder',
                                    assignment_type: 'none',
                                    is_exam: true,
                                },
                            ],
                        },
                    ],
                },
            },
            selectedCourseCurriculumId: 10,
            selectedCurriculumDetail: null,
        }

        Object.assign(ctx, methods)
        ctx.selectedCourseCurriculum = computed.selectedCourseCurriculum.call(ctx)
        ctx.selectedCourseCurriculumForSync = computed.selectedCourseCurriculumForSync.call(ctx)
        ctx.curriculumWeekEntries = computed.curriculumWeekEntries.call(ctx)

        const rows = computed.curriculumSyncRows.call(ctx)

        expect(rows).toHaveLength(2)
        expect(rows[0].weekKey).toBe('2025-09-01')
        expect(rows[0].courseDates).toHaveLength(1)
        expect(methods.isFreeCourseDateForSync.call(ctx, rows[0].courseDates[0])).toBe(true)
        expect(methods.courseDateSyncColor.call(ctx, rows[0].courseDates[0])).toBe('success')
        expect(methods.courseDateSyncVariant.call(ctx, rows[0].courseDates[0])).toBe('flat')
        expect(methods.courseDateContentText.call(ctx, rows[0].courseDates[0])).toBe('Projekt: Internetrecherche')
        expect(rows[0].curriculumEntries[0].label).toBe('Grammatik')
        expect(rows[0].curriculumEntries[1].topicLabel).toBe('Grammatik')
        expect(rows[0].curriculumEntries[1].unitLabel).toBe('Satzglieder')
        expect(rows[0].curriculumEntries[1].isExam).toBe(true)
        expect(rows[0].curriculumEntries[1].color).toBe('warning')
        expect(rows[1].weekKey).toBe('2025-09-15')
        expect(rows[1].courseDates).toHaveLength(0)
        expect(rows[1].curriculumEntries[0].label).toBe('Frei')
    })

    it.skip('allows long removed sync chip labels to wrap', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/Overview.vue'), 'utf8')

        expect(source).toContain('class="curriculum-sync-chip"')
        expect(source).toContain('class="curriculum-sync-chip__text"')
        expect(source).toContain('class="curriculum-sync-chip__content"')
        expect(source).toContain('class="curriculum-sync-chip__free-label"')
        expect(source).toContain('courseDateContentText(courseDate)')
        expect(source).toContain('isFreeCourseDateForSync(courseDate)')
        expect(source).toContain('class="curriculum-sync-chip__topic"')
        expect(source).toContain('entry.topicLabel && entry.unitLabel')
        expect(source).toContain('class="curriculum-sync-chip__exam-icon"')
        expect(source).toContain('class="curriculum-sync-entry-actions"')
        expect(source).toContain('icon="mdi-file-alert-outline"')
        expect(source).toContain('icon="mdi-arrow-up"')
        expect(source).toContain('icon="mdi-arrow-down"')
        expect(source).toContain('icon="mdi-arrow-up-bold-box-outline"')
        expect(source).toContain('icon="mdi-arrow-down-bold-box-outline"')
        expect(source).toContain('align-items: center;')
        expect(source).toContain('border-radius: 8px !important;')
        expect(source).toContain('.curriculum-sync-chip :deep(.v-chip__content)')
        expect(source).toContain('display: inline;')
        expect(source).toContain('overflow-wrap: anywhere;')
        expect(source).toContain('white-space: normal;')
    })

    it.skip('hides removed curriculum content entries on free curriculum weeks', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: Record<string, any> = {
            config: {
                selected_schoolyear: {
                    from: '2025-09-01',
                    until: '2025-09-30',
                },
            },
            selected_course: {
                course_dates: [],
                teaching_curriculum: {
                    id: 10,
                    free_weeks: ['2025-09-15'],
                    topics: [
                        {
                            id: 'topic-1',
                            title: 'Schreibübungen',
                            assignment_type: 'all_weeks',
                            week_keys: [],
                            units: [],
                        },
                    ],
                },
            },
            selectedCourseCurriculumId: 10,
            selectedCurriculumDetail: null,
        }

        Object.assign(ctx, methods)
        ctx.selectedCourseCurriculum = computed.selectedCourseCurriculum.call(ctx)
        ctx.selectedCourseCurriculumForSync = computed.selectedCourseCurriculumForSync.call(ctx)
        ctx.curriculumWeekEntries = computed.curriculumWeekEntries.call(ctx)

        const rows = computed.curriculumSyncRows.call(ctx)
        const freeRow = rows.find((row: Record<string, any>) => row.weekKey === '2025-09-15')

        expect(freeRow?.curriculumEntries).toHaveLength(1)
        expect(freeRow?.curriculumEntries[0].label).toBe('Frei')
        expect(freeRow?.curriculumEntries.some((entry: Record<string, any>) => entry.label === 'Schreibübungen')).toBe(false)
    })

    it.skip('moves removed curriculum scheduling entries with following entries by week', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: Record<string, any> = {
            config: {
                selected_schoolyear: {
                    from: '2025-09-01',
                    until: '2025-09-30',
                },
            },
            selected_course: {
                course_dates: [],
                teaching_curriculum: {
                    id: 10,
                    title: 'Medien',
                    semester_count: 2,
                    free_weeks: [],
                    topics: [
                        {
                            id: 'topic-1',
                            title: 'Recherche',
                            assignment_type: 'weeks',
                            week_keys: ['2025-09-08'],
                            units: [],
                        },
                        {
                            id: 'topic-2',
                            title: 'Praesentation',
                            assignment_type: 'weeks',
                            week_keys: ['2025-09-15'],
                            units: [],
                        },
                    ],
                },
            },
            selectedCourseCurriculumId: 10,
            selectedCurriculumDetail: null,
        }

        Object.assign(ctx, methods)
        ctx.selectedCourseCurriculum = computed.selectedCourseCurriculum.call(ctx)
        ctx.selectedCourseCurriculumForSync = computed.selectedCourseCurriculumForSync.call(ctx)
        ctx.curriculumWeekEntries = computed.curriculumWeekEntries.call(ctx)

        const selectedEntry = ctx.curriculumWeekEntries.find((entry: Record<string, any>) => entry.label === 'Recherche')

        expect(methods.canMoveCurriculumEntry.call(ctx, selectedEntry, 1, true)).toBe(true)
        expect(methods.curriculumEntriesToMove.call(ctx, selectedEntry, 1, true).map((entry: Record<string, any>) => entry.label))
            .toEqual(['Recherche', 'Praesentation'])

        const shiftedTopics = methods.shiftCurriculumTopics.call(
            ctx,
            methods.curriculumEntriesToMove.call(ctx, selectedEntry, 1, true),
            1,
        )

        expect(shiftedTopics[0].week_keys).toEqual(['2025-09-15'])
        expect(shiftedTopics[1].week_keys).toEqual(['2025-09-22'])
    })

    it.skip('allows moving removed curriculum entries up to the previous non-free week', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: Record<string, any> = {
            config: {
                selected_schoolyear: {
                    from: '2025-08-18',
                    until: '2025-09-30',
                },
            },
            selected_course: {
                course_dates: [],
                teaching_curriculum: {
                    id: 10,
                    title: 'Medien',
                    semester_count: 2,
                    free_weeks: ['2025-08-25', '2025-09-01'],
                    topics: [
                        {
                            id: 'topic-1',
                            title: 'Recherche',
                            assignment_type: 'weeks',
                            week_keys: ['2025-09-08'],
                            units: [],
                        },
                    ],
                },
            },
            selectedCourseCurriculumId: 10,
            selectedCurriculumDetail: null,
        }

        Object.assign(ctx, methods)
        ctx.selectedCourseCurriculum = computed.selectedCourseCurriculum.call(ctx)
        ctx.selectedCourseCurriculumForSync = computed.selectedCourseCurriculumForSync.call(ctx)
        ctx.curriculumWeekEntries = computed.curriculumWeekEntries.call(ctx)

        const selectedEntry = ctx.curriculumWeekEntries.find((entry: Record<string, any>) => entry.label === 'Recherche')

        expect(methods.canMoveCurriculumEntry.call(ctx, selectedEntry, -1, false)).toBe(true)

        const shiftedTopics = methods.shiftCurriculumTopics.call(
            ctx,
            methods.curriculumEntriesToMove.call(ctx, selectedEntry, -1, false),
            -1,
        )

        expect(shiftedTopics[0].week_keys).toEqual(['2025-08-18'])
    })

    it.skip('allows moving removed curriculum entries down to the next non-free week', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: Record<string, any> = {
            config: {
                selected_schoolyear: {
                    from: '2026-03-16',
                    until: '2026-04-30',
                },
            },
            selected_course: {
                course_dates: [],
                teaching_curriculum: {
                    id: 10,
                    title: 'Medien',
                    semester_count: 2,
                    free_weeks: ['2026-03-30'],
                    topics: [
                        {
                            id: 'topic-all-year',
                            title: 'Schreibuebungen',
                            assignment_type: 'all_weeks',
                            week_keys: [],
                            units: [],
                        },
                        {
                            id: 'topic-search',
                            title: 'Suchmaschinen und Internetrecherche - Teil 1',
                            assignment_type: 'none',
                            week_keys: [],
                            units: [
                                {
                                    id: 'unit-project',
                                    title: 'Projekt: Internetrecherche',
                                    assignment_type: 'weeks',
                                    week_keys: ['2026-03-23'],
                                    is_exam: true,
                                },
                            ],
                        },
                    ],
                },
            },
            selectedCourseCurriculumId: 10,
            selectedCurriculumDetail: null,
        }

        Object.assign(ctx, methods)
        ctx.selectedCourseCurriculum = computed.selectedCourseCurriculum.call(ctx)
        ctx.selectedCourseCurriculumForSync = computed.selectedCourseCurriculumForSync.call(ctx)
        ctx.curriculumWeekEntries = computed.curriculumWeekEntries.call(ctx)

        const selectedEntry = ctx.curriculumWeekEntries.find((entry: Record<string, any>) => (
            entry.unitLabel === 'Projekt: Internetrecherche'
            && entry.weekKey === '2026-03-23'
        ))

        expect(methods.canMoveCurriculumEntry.call(ctx, selectedEntry, 1, false)).toBe(true)

        const shiftedTopics = methods.shiftCurriculumTopics.call(
            ctx,
            methods.curriculumEntriesToMove.call(ctx, selectedEntry, 1, false),
            1,
        )

        expect(shiftedTopics[1].units[0].week_keys).toEqual(['2026-04-06'])
    })

    it.skip('blocks moving removed curriculum entries up when the previous week is occupied', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: Record<string, any> = {
            config: {
                selected_schoolyear: {
                    from: '2025-09-01',
                    until: '2025-09-30',
                },
            },
            selected_course: {
                course_dates: [],
                teaching_curriculum: {
                    id: 10,
                    title: 'Medien',
                    semester_count: 2,
                    free_weeks: [],
                    topics: [
                        {
                            id: 'topic-1',
                            title: 'Grundlagen',
                            assignment_type: 'weeks',
                            week_keys: ['2025-09-01'],
                            units: [],
                        },
                        {
                            id: 'topic-2',
                            title: 'Recherche',
                            assignment_type: 'weeks',
                            week_keys: ['2025-09-08'],
                            units: [],
                        },
                    ],
                },
            },
            selectedCourseCurriculumId: 10,
            selectedCurriculumDetail: null,
        }

        Object.assign(ctx, methods)
        ctx.selectedCourseCurriculum = computed.selectedCourseCurriculum.call(ctx)
        ctx.selectedCourseCurriculumForSync = computed.selectedCourseCurriculumForSync.call(ctx)
        ctx.curriculumWeekEntries = computed.curriculumWeekEntries.call(ctx)

        const selectedEntry = ctx.curriculumWeekEntries.find((entry: Record<string, any>) => entry.label === 'Recherche')

        expect(methods.canMoveCurriculumEntry.call(ctx, selectedEntry, -1, false)).toBe(false)
    })

    it.skip('allows moving removed curriculum entries through free weeks and assignments', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: Record<string, any> = {
            config: {
                selected_schoolyear: {
                    from: '2025-09-01',
                    until: '2025-09-30',
                },
            },
            selected_course: {
                course_dates: [],
                teaching_curriculum: {
                    id: 10,
                    title: 'Medien',
                    semester_count: 2,
                    free_weeks: ['2025-09-08'],
                    topics: [
                        {
                            id: 'topic-all-year',
                            title: 'Schreibübungen',
                            assignment_type: 'all_weeks',
                            week_keys: [],
                            units: [],
                        },
                        {
                            id: 'topic-month',
                            title: 'Monatsprojekt',
                            assignment_type: 'month',
                            month_key: '2025-09',
                            units: [],
                        },
                        {
                            id: 'topic-week',
                            title: 'Recherche',
                            assignment_type: 'weeks',
                            week_keys: ['2025-09-15'],
                            units: [],
                        },
                    ],
                },
            },
            selectedCourseCurriculumId: 10,
            selectedCurriculumDetail: null,
        }

        Object.assign(ctx, methods)
        ctx.selectedCourseCurriculum = computed.selectedCourseCurriculum.call(ctx)
        ctx.selectedCourseCurriculumForSync = computed.selectedCourseCurriculumForSync.call(ctx)
        ctx.curriculumWeekEntries = computed.curriculumWeekEntries.call(ctx)

        const selectedEntry = ctx.curriculumWeekEntries.find((entry: Record<string, any>) => entry.label === 'Recherche')

        expect(ctx.curriculumWeekEntries.some((entry: Record<string, any>) => (
            entry.weekKey === '2025-09-01'
            && ['Schreibübungen', 'Monatsprojekt'].includes(entry.label)
            && methods.isBlockingCurriculumMoveEntry.call(ctx, entry) === false
        ))).toBe(true)
        expect(methods.canMoveCurriculumEntry.call(ctx, selectedEntry, -1, false)).toBe(true)

        const shiftedTopics = methods.shiftCurriculumTopics.call(
            ctx,
            methods.curriculumEntriesToMove.call(ctx, selectedEntry, -1, false),
            -1,
        )

        expect(shiftedTopics[2].week_keys).toEqual(['2025-09-01'])
    })

    it.skip('moves the removed clicked scheduling occurrence across weeks', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: Record<string, any> = {
            config: {
                selected_schoolyear: {
                    from: '2026-03-16',
                    until: '2026-04-30',
                },
            },
            selected_course: {
                course_dates: [],
                teaching_curriculum: {
                    id: 10,
                    title: 'Medien',
                    semester_count: 2,
                    free_weeks: ['2026-03-30'],
                    topics: [
                        {
                            id: 'topic-all-year',
                            title: 'Schreibübungen',
                            assignment_type: 'all_weeks',
                            week_keys: [],
                            units: [],
                        },
                        {
                            id: 'topic-search',
                            title: 'Suchmaschinen und Internetrecherche - Teil 1',
                            assignment_type: 'none',
                            week_keys: [],
                            units: [
                                {
                                    id: 'unit-project',
                                    title: 'Projekt: Internetrecherche',
                                    assignment_type: 'weeks',
                                    week_keys: ['2026-03-23', '2026-04-06'],
                                    is_exam: true,
                                },
                            ],
                        },
                    ],
                },
            },
            selectedCourseCurriculumId: 10,
            selectedCurriculumDetail: null,
        }

        Object.assign(ctx, methods)
        ctx.selectedCourseCurriculum = computed.selectedCourseCurriculum.call(ctx)
        ctx.selectedCourseCurriculumForSync = computed.selectedCourseCurriculumForSync.call(ctx)
        ctx.curriculumWeekEntries = computed.curriculumWeekEntries.call(ctx)

        const firstProjectEntry = ctx.curriculumWeekEntries.find((entry: Record<string, any>) => (
            entry.unitLabel === 'Projekt: Internetrecherche'
            && entry.weekKey === '2026-03-23'
        ))
        const secondProjectEntry = ctx.curriculumWeekEntries.find((entry: Record<string, any>) => (
            entry.unitLabel === 'Projekt: Internetrecherche'
            && entry.weekKey === '2026-04-06'
        ))

        expect(methods.canMoveCurriculumEntry.call(ctx, firstProjectEntry, 1, false)).toBe(false)
        expect(methods.canMoveCurriculumEntry.call(ctx, secondProjectEntry, 1, false)).toBe(true)

        const shiftedTopics = methods.shiftCurriculumTopics.call(
            ctx,
            methods.curriculumEntriesToMove.call(ctx, secondProjectEntry, 1, false),
            1,
        )

        expect(shiftedTopics[1].units[0].week_keys).toEqual(['2026-03-23', '2026-04-13'])
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
