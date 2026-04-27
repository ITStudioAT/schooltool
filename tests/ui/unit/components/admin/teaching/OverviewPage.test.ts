import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
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

    it('aligns course dates and curriculum assignments by week', () => {
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

    it('allows long sync chip labels to wrap', () => {
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

    it('hides curriculum content entries on free curriculum weeks', () => {
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

    it('moves selected curriculum entries with following entries by week', () => {
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

    it('allows moving curriculum entries up to the previous non-free week', () => {
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

    it('allows moving curriculum entries down to the next non-free week', () => {
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

    it('blocks moving curriculum entries up when the previous week is occupied', () => {
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

    it('allows moving curriculum entries up through free weeks and all-year/month assignments', () => {
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

    it('moves the clicked occurrence when a unit is assigned to multiple weeks', () => {
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
