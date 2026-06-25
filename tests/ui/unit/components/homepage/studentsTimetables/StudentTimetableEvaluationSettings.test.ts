import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import StudentTimetableEvaluationSettings from '@/pages/homepage/studentsTimetables/components/StudentTimetableEvaluationSettings.vue'
import Overview from '@/pages/homepage/studentsTimetables/overview/Overview.vue'

describe('Student timetable evaluation settings', () => {
    it('keeps student timetable copy free from mojibake', () => {
        const checkedPaths = [
            resolve(process.cwd(), 'resources/js/pages/homepage/studentsTimetables/components/StudentTimetableEvaluationSettings.vue'),
            resolve(process.cwd(), 'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue'),
            resolve(process.cwd(), 'app/Services/StudentsTimetables/RobotTimetableBackendSetupService.php'),
        ]
        const mojibakePattern = /[\u00c2\u00c3\ufffd]|\u00e2[\u0080-\u00bf]/u

        checkedPaths.forEach(filePath => {
            expect(readFileSync(filePath, 'utf8')).not.toMatch(mojibakePattern)
        })
    })

    it('receives proposed courses from the student overview', () => {
        const overviewPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
        )
        const source = readFileSync(overviewPath, 'utf8')

        expect(source).toContain(':proposed-courses="automaticTimetableSelectableCourses"')
        expect(source).toContain(':result-more-course-group-candidates="manualTimetableCourses"')
        expect(source).toContain(':initial-step="automaticTimetableStep"')
        expect(source).toContain(':initial-selected-course-keys="automaticTimetableCourseKeys"')
        expect(source).toContain(':initial-selected-additional-course-keys="automaticTimetableAdditionalCourseKeys"')
        expect(source).toContain(':initial-selected-quality-criterion-keys="[]"')
        expect(source).toContain(':default-quality-criterion-selection="false"')
        expect(source).toContain(':course-sections="automaticTimetableAllCourseSections"')
        expect(source).toContain('automaticTimetableAllCourseSections()')
        expect(source).toContain("String(section?.key || '') === 'additional'")
        expect(source).toContain('this.overview?.additional_courses')
        expect(source).toContain("title: 'Zusätzliche Kurse'")
        expect(source).toContain('@course-selection-change="setAutomaticTimetableCourseKeys"')
        expect(source).toContain('@additional-course-selection-change="setAutomaticTimetableAdditionalCourseKeys"')
        expect(source).toContain('@courses-selected="finishAutomaticTimetable"')
        expect(source).toContain('@quality-criteria-selection-change="setAutomaticTimetableQualityCriterionKeys"')
        expect(source).toContain('@step-change="setAutomaticTimetableStep"')
        expect(source).toContain('automatic_timetable')
        expect(source).toContain('automatic_timetable_courses')
        expect(source).toContain('automatic_timetable_additional_courses')
        expect(source).toContain('automatic_timetable_criteria')
        expect(source).toContain('v-if="timetableActionsVisible"')
        expect(source).toContain('overviewLoading: true')
        expect(source).toContain('timetableActionsVisible()')
        expect(source).toContain('savedTimetableActionsVisible()')
        expect(source).toContain('v-if="savedTimetableActionsVisible"')
        expect(source).toContain('v-if="savedTimetableActionsVisible && hasPublishedTimetable"')
        expect(source).toContain('append-icon="mdi-arrow-right"')
        expect(source).toContain('Weiter')
        expect(source).toContain('font-weight: 400;')
        expect(source).toContain("const noAutomaticTimetableQualityCriteriaValue = '__none'")
        expect(source).toContain("this.setAutomaticTimetableStep('criteria')")
        expect(source).toContain("'criteria', 'courses', 'result'")
    })

    it('renders unselected student overview courses as outlined chips with a white background', () => {
        const overviewPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
        )
        const source = readFileSync(overviewPath, 'utf8')

        expect(source).toContain(":variant=\"overviewCourseItemSelected(course, section.key) ? 'tonal' : 'outlined'\"")
        expect(source).toContain('.overview-course-selection__item--deselected')
        expect(source).toContain('background: #ffffff;')
        expect(source).toContain('border-color: rgba(100, 116, 139, 0.28);')
    })

    it('shows a plain timetable title on the generated result step', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/components/StudentTimetableEvaluationSettings.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toMatch(/currentStep === 'result'[\s\S]*<h2>Stundenplan<\/h2>/u)
    })

    it('opens a manual timetable from the student overview', () => {
        const overviewPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
        )
        const source = readFileSync(overviewPath, 'utf8')

        expect(source).toContain('@click="openPublishedTimetable"')
        expect(source).toContain('@click="openPersonalTimetable"')
        expect(source).toContain('@click="adoptPublishedTimetable"')
        expect(source).toContain('@click="openPersonalTimetableDeleteDialog"')
        expect(source).toContain('@click="deletePersonalTimetable"')
        expect(source).toContain('persistent max-width="460"')
        expect(source).toContain('Mein Stundenplan löschen?')
        expect(source).toContain('Dieser Stundenplan wird nur für dich und dieses Schuljahr gelöscht.')
        expect(source).toContain('this.studentTimetablesStore.deletePersonalTimetable()')
        expect(source).toContain('personalTimetableEmptyVisible')
        expect(source).toContain('Mein Stundenplan ist noch leer.')
        expect(source).toContain('personalTimetableViewChangeVisible')
        expect(source).toContain('Der Stundenplan wurde übernommen. Du siehst jetzt "Mein Stundenplan".')
        expect(source).toContain('closable')
        expect(source).toContain('close-label="Meldung schließen"')
        expect(source).toContain('@click:close="dismissPersonalTimetableViewChangeMessage"')
        expect(source).toContain('Als mein Stundenplan übernehmen')
        expect(source).toContain('class="student-course-choice-panel"')
        expect(source).toContain('<span>Kursauswahl</span>')
        expect(source).toContain('@click="openStudentCoursePickerDialog"')
        expect(source).toContain('v-model="studentCoursePickerDialogOpen" persistent max-width="760"')
        expect(source).toContain('Kurs wählen')
        expect(source).toContain('studentCoursePickerTabs')
        expect(source).toContain('Negative Kurse')
        expect(source).toContain('Vorgesehene Kurse')
        expect(source).toContain('Zusätzliche Kurse')
        expect(source).toContain('Offene Kurse')
        expect(source).toContain('selectedStudentCoursePickerEntryOptions')
        expect(source).toContain('@click="toggleStudentCoursePickerEntry(entryOption)"')
        expect(source).toContain('{{ savedTimetableCourseChips.length }} ausgewählt')
        expect(source).toContain('@click="deselectAllSavedTimetableCourseChips"')
        expect(source).toContain('@click:close="deselectSavedTimetableCourseChip(courseChip)"')
        expect(source).toContain('savedTimetableCourseChips()')
        expect(source).toContain('studentCoursePickerCourseMenus()')
        expect(source).toContain('publishedTimetableCellRawCourses(cell)')
        expect(source).toContain('Löschen')
        expect(source).toContain('Mein Stundenplan')
        expect(source).toContain('Von der Schule gespeicherter Stundenplan')
        expect(source).toContain('hasPublishedTimetable')
        expect(source).toContain('hasPersonalTimetable')
        expect(source).toContain('personal_timetable')
        expect(source).toContain('published_timetable')
        expect(source).toContain('publishedTimetableVisible')
        expect(source).toContain("manual_timetable = 'personal'")
        expect(source).toContain('class="student-published-timetable__grid"')
        expect(source).toContain('class="timetable-date-overview"')
        expect(source).toContain('@media (max-width: 380px)')
        expect(source).toContain('box-shadow: none;')
        expect(source).toContain('color: #f8fafc;')
        expect(source).toContain('grid-template-columns: 30px repeat(var(--published-timetable-weekday-count, 6), minmax(0, 1fr));')
        expect(source).toContain('showManualTimetable')
        expect(source).toContain('manual_timetable')
        expect(source).toContain('manualTimetableSelection()')
        expect(source).toContain('manualSelectedCourseGroups()')
        expect(source).toContain('manualGroupsForCell(weekday.value, hour.value)')
        expect(source).toContain('<div class="student-manual-timetable__corner">Std.</div>')
        expect(source).toContain('.student-manual-timetable__cell--conflict {\n    background: #fef2f2;')
        expect(source).toContain('<v-checkbox-btn')
        expect(source).toContain("return 'Stundenplan'")
        expect(source).toContain('Keine passenden Kurstermine gefunden.')
        expect(source).toContain('showManualTimetable && courseSections.length')
        expect(source).toContain('class="manual-overview-course-card"')
        expect(source).toContain('v-model="expandedManualOverviewCoursePanels"')
        expect(source).toContain('expandedManualOverviewCoursePanels: []')
        expect(source).toContain('<h3>Kurse</h3>')
        expect(source).toContain('courseSectionTotalCount')
    })

    it('opens a published timetable with the saved course group selection', () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const savedCourseGroup = {
            key: 'saved-group',
            course: 'D1',
            weekday: 1,
            hour: 2,
        }
        const otherCourseGroup = {
            key: 'other-group',
            course: 'D1',
            weekday: 2,
            hour: 3,
        }
        const course = {
            key: 'course-1',
            code: 'D1',
            course_groups: [savedCourseGroup, otherCourseGroup],
        }
        const pushedRoutes: unknown[] = []
        const ctx: any = {
            ...methods,
            showManualTimetable: false,
            showEvaluationSettings: true,
            manualTimetableMode: 'manual',
            manualSelectedCourseKeys: [],
            manualSelectedCourseGroupKeys: [],
            overview: {
                published_timetable: {
                    id: 7,
                    active_course_group_keys: ['saved-group'],
                    timetable: {
                        student: '5C · ZADRA Isabella · Semester 5',
                        schoolyear: 'Schuljahr 2025/26',
                        generated_at: '16.06.26, 21:02',
                        weekdays: [
                            { label: 'Mo' },
                            { label: 'Di' },
                        ],
                        semesters: [
                            {
                                label: 'Semester',
                                date_range: '16.02.2026 - 10.07.2026',
                                weeks: [
                                    {
                                        hours: [
                                            {
                                                hour: 10,
                                                from: '17:05',
                                                until: '17:50',
                                                cells: [
                                                    {
                                                        status: 'conflict',
                                                        courses: [
                                                            {
                                                                label: 'D5 - 3R - SHAM',
                                                                details: 'FU · 2-wöchig',
                                                                dates: [
                                                                    '2026-02-24',
                                                                    '2026-03-10',
                                                                    '2026-06-30',
                                                                ],
                                                            },
                                                            {
                                                                label: 'E5 - 3R - HÖF',
                                                                details: '2-wöchig',
                                                                dates: [
                                                                    '2026-02-17',
                                                                    '2026-03-03',
                                                                    '2026-04-14',
                                                                ],
                                                            },
                                                        ],
                                                        markers: [],
                                                    },
                                                ],
                                            },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                },
                manual_timetable: {
                    title: 'Manueller Stundenplan',
                    sections: [
                        {
                            key: 'proposed',
                            items: [course],
                        },
                    ],
                },
            },
            $route: {
                path: '/students-timetables/overview',
                query: {
                    automatic_timetable: 'criteria',
                    automatic_timetable_courses: ['course-1'],
                    automatic_timetable_criteria: ['saturday_free'],
                },
            },
            $router: {
                push(route: unknown) {
                    pushedRoutes.push(route)
                },
            },
            manualCourseKey: methods.manualCourseKey,
            manualCourseGroups: methods.manualCourseGroups,
            manualCourseGroupKey: methods.manualCourseGroupKey,
            applyPublishedTimetableSelection: methods.applyPublishedTimetableSelection,
            applySavedTimetableSelection: methods.applySavedTimetableSelection,
            publishedTimetableCellCourses: methods.publishedTimetableCellCourses,
            get manualTimetableSelection() {
                return computed.manualTimetableSelection.call(ctx)
            },
            get manualTimetableTitle() {
                return computed.manualTimetableTitle.call(ctx)
            },
            get publishedTimetableSelection() {
                return computed.publishedTimetableSelection.call(ctx)
            },
            get hasPublishedTimetable() {
                return computed.hasPublishedTimetable.call(ctx)
            },
            get personalTimetableSelection() {
                return computed.personalTimetableSelection.call(ctx)
            },
            get hasPersonalTimetable() {
                return computed.hasPersonalTimetable.call(ctx)
            },
            get activeSavedTimetableSelection() {
                return computed.activeSavedTimetableSelection.call(ctx)
            },
            get activeSavedTimetableCourseGroupKeys() {
                return computed.activeSavedTimetableCourseGroupKeys.call(ctx)
            },
            get publishedTimetableCourseGroupKeys() {
                return computed.publishedTimetableCourseGroupKeys.call(ctx)
            },
            get publishedTimetablePayload() {
                return computed.publishedTimetablePayload.call(ctx)
            },
            get publishedTimetableVisible() {
                return computed.publishedTimetableVisible.call(ctx)
            },
            get personalTimetableEmptyVisible() {
                return computed.personalTimetableEmptyVisible.call(ctx)
            },
            get publishedTimetableSubtitle() {
                return computed.publishedTimetableSubtitle.call(ctx)
            },
            get publishedTimetableWeekdays() {
                return computed.publishedTimetableWeekdays.call(ctx)
            },
            get publishedTimetableSemesters() {
                return computed.publishedTimetableSemesters.call(ctx)
            },
            get manualTimetableCourseSections() {
                return computed.manualTimetableCourseSections.call(ctx)
            },
            get manualTimetableCourses() {
                return computed.manualTimetableCourses.call(ctx)
            },
            get manualSelectedCourses() {
                return computed.manualSelectedCourses.call(ctx)
            },
        }
        const savedCell = ctx.publishedTimetableSemesters[0].weeks[0].hours[0].cells[0]

        methods.openPublishedTimetable.call(ctx)

        expect(ctx.showManualTimetable).toBe(true)
        expect(ctx.showEvaluationSettings).toBe(false)
        expect(ctx.manualTimetableMode).toBe('published')
        expect(ctx.manualTimetableTitle).toBe('Von der Schule gespeicherter Stundenplan')
        expect(ctx.manualSelectedCourseKeys).toEqual(['course-1'])
        expect(ctx.manualSelectedCourseGroupKeys).toEqual(['saved-group'])
        expect(ctx.publishedTimetableVisible).toBe(true)
        expect(ctx.publishedTimetableSubtitle).toBe('5C · ZADRA Isabella · Semester 5 · Schuljahr 2025/26 · 16.06.26, 21:02')
        expect(ctx.publishedTimetableWeekdays).toEqual([{ label: 'Mo' }, { label: 'Di' }])
        expect(methods.publishedTimetableSemesterWeeks(ctx.publishedTimetableSemesters[0])).toHaveLength(1)
        expect(methods.publishedTimetableHourCells.call(ctx, ctx.publishedTimetableSemesters[0].weeks[0].hours[0]))
            .toHaveLength(2)
        expect(methods.publishedTimetableCellClasses.call(ctx, savedCell))
            .toEqual({
                'student-published-timetable__cell--filled': true,
                'student-published-timetable__cell--warning': true,
                'student-published-timetable__cell--conflict': false,
                'student-published-timetable__cell--related': false,
            })
        expect(methods.publishedTimetableSameSlotGroups.call(ctx, ctx.publishedTimetableSemesters[0].weeks[0]))
            .toEqual([
                {
                    key: '10|17:05|17:50|0|D5 - 3R - SHAM|FU · 2-wöchig|2026-02-24,2026-03-10,2026-06-30|E5 - 3R - HÖF|2-wöchig|2026-02-17,2026-03-03,2026-04-14',
                    title: 'Mo 10. 17:05 - 17:50',
                    courses: [
                        {
                            key: 'D5 - 3R - SHAM|FU · 2-wöchig|2026-02-24,2026-03-10,2026-06-30',
                            title: 'D5 - 3R - SHAM',
                            dateRangeLabel: '24.02. - 30.06.',
                            dateLabels: [
                                'Di, 24.02.2026',
                                'Di, 10.03.2026',
                                'Di, 30.06.2026',
                            ],
                        },
                        {
                            key: 'E5 - 3R - HÖF|2-wöchig|2026-02-17,2026-03-03,2026-04-14',
                            title: 'E5 - 3R - HÖF',
                            dateRangeLabel: '17.02. - 14.04.',
                            dateLabels: [
                                'Di, 17.02.2026',
                                'Di, 03.03.2026',
                                'Di, 14.04.2026',
                            ],
                        },
                    ],
                },
            ])
        expect(computed.manualSelectedCourseGroups.call(ctx)).toEqual([savedCourseGroup])
        expect(pushedRoutes).toEqual([
            {
                path: '/students-timetables/overview',
                query: {
                    manual_timetable: 'published',
                },
            },
        ])
    })

    it('opens a personal timetable with the adopted course group selection', () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const savedCourseGroup = {
            key: 'saved-group',
            course: 'D1',
            weekday: 1,
            hour: 2,
        }
        const course = {
            key: 'course-1',
            code: 'D1',
            course_groups: [savedCourseGroup],
        }
        const pushedRoutes: unknown[] = []
        const ctx: any = {
            ...methods,
            showManualTimetable: false,
            showEvaluationSettings: true,
            manualTimetableMode: 'manual',
            manualSelectedCourseKeys: [],
            manualSelectedCourseGroupKeys: [],
            overview: {
                personal_timetable: {
                    id: 9,
                    active_course_group_keys: ['saved-group'],
                    timetable: {
                        student: '5C · ZADRA Isabella · Semester 5',
                        weekdays: [{ label: 'Mo' }],
                        semesters: [{ label: 'Semester', weeks: [] }],
                    },
                },
                manual_timetable: {
                    sections: [
                        {
                            key: 'proposed',
                            items: [course],
                        },
                    ],
                },
            },
            $route: {
                path: '/students-timetables/overview',
                query: {},
            },
            $router: {
                push(route: unknown) {
                    pushedRoutes.push(route)
                },
            },
            manualCourseKey: methods.manualCourseKey,
            manualCourseGroups: methods.manualCourseGroups,
            manualCourseGroupKey: methods.manualCourseGroupKey,
            applySavedTimetableSelection: methods.applySavedTimetableSelection,
            get manualTimetableSelection() {
                return computed.manualTimetableSelection.call(ctx)
            },
            get manualTimetableTitle() {
                return computed.manualTimetableTitle.call(ctx)
            },
            get personalTimetableSelection() {
                return computed.personalTimetableSelection.call(ctx)
            },
            get hasPersonalTimetable() {
                return computed.hasPersonalTimetable.call(ctx)
            },
            get activeSavedTimetableSelection() {
                return computed.activeSavedTimetableSelection.call(ctx)
            },
            get activeSavedTimetableCourseGroupKeys() {
                return computed.activeSavedTimetableCourseGroupKeys.call(ctx)
            },
            get publishedTimetablePayload() {
                return computed.publishedTimetablePayload.call(ctx)
            },
            get publishedTimetableVisible() {
                return computed.publishedTimetableVisible.call(ctx)
            },
            get personalTimetableEmptyVisible() {
                return computed.personalTimetableEmptyVisible.call(ctx)
            },
            get publishedTimetableSemesters() {
                return computed.publishedTimetableSemesters.call(ctx)
            },
            get manualTimetableCourseSections() {
                return computed.manualTimetableCourseSections.call(ctx)
            },
            get manualTimetableCourses() {
                return computed.manualTimetableCourses.call(ctx)
            },
        }

        methods.openPersonalTimetable.call(ctx)

        expect(ctx.showManualTimetable).toBe(true)
        expect(ctx.showEvaluationSettings).toBe(false)
        expect(ctx.manualTimetableMode).toBe('personal')
        expect(ctx.manualTimetableTitle).toBe('Mein Stundenplan')
        expect(ctx.manualSelectedCourseKeys).toEqual(['course-1'])
        expect(ctx.manualSelectedCourseGroupKeys).toEqual(['saved-group'])
        expect(ctx.publishedTimetableVisible).toBe(true)
        expect(ctx.personalTimetableEmptyVisible).toBe(false)
        expect(pushedRoutes).toEqual([
            {
                path: '/students-timetables/overview',
                query: {
                    manual_timetable: 'personal',
                },
            },
        ])
    })

    it('adopts the published timetable and shows that the view changed to the personal timetable', async () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const savedCourseGroup = {
            key: 'saved-group',
            course: 'D1',
            weekday: 1,
            hour: 2,
        }
        const course = {
            key: 'course-1',
            code: 'D1',
            course_groups: [savedCourseGroup],
        }
        const pushedRoutes: unknown[] = []
        const ctx: any = {
            ...methods,
            showManualTimetable: true,
            showEvaluationSettings: false,
            manualTimetableMode: 'published',
            manualSelectedCourseKeys: [],
            manualSelectedCourseGroupKeys: [],
            personalTimetableSaving: false,
            personalTimetableViewChangedMessageVisible: false,
            overview: {
                published_timetable: {
                    id: 7,
                    active_course_group_keys: ['saved-group'],
                    timetable: {
                        student: '5C · ZADRA Isabella · Semester 5',
                        weekdays: [{ label: 'Mo' }],
                        semesters: [{ label: 'Semester', weeks: [] }],
                    },
                },
                personal_timetable: null,
                manual_timetable: {
                    sections: [
                        {
                            key: 'proposed',
                            items: [course],
                        },
                    ],
                },
            },
            $route: {
                path: '/students-timetables/overview',
                query: {
                    manual_timetable: 'published',
                },
            },
            $router: {
                push(route: unknown) {
                    pushedRoutes.push(route)
                },
            },
            manualCourseKey: methods.manualCourseKey,
            manualCourseGroups: methods.manualCourseGroups,
            manualCourseGroupKey: methods.manualCourseGroupKey,
            applySavedTimetableSelection: methods.applySavedTimetableSelection,
            get manualTimetableSelection() {
                return computed.manualTimetableSelection.call(ctx)
            },
            get manualTimetableTitle() {
                return computed.manualTimetableTitle.call(ctx)
            },
            get publishedTimetableSelection() {
                return computed.publishedTimetableSelection.call(ctx)
            },
            get personalTimetableSelection() {
                return computed.personalTimetableSelection.call(ctx)
            },
            get hasPublishedTimetable() {
                return computed.hasPublishedTimetable.call(ctx)
            },
            get activeSavedTimetableSelection() {
                return computed.activeSavedTimetableSelection.call(ctx)
            },
            get activeSavedTimetableCourseGroupKeys() {
                return computed.activeSavedTimetableCourseGroupKeys.call(ctx)
            },
            get publishedTimetablePayload() {
                return computed.publishedTimetablePayload.call(ctx)
            },
            get publishedTimetableVisible() {
                return computed.publishedTimetableVisible.call(ctx)
            },
            get personalTimetableViewChangeVisible() {
                return computed.personalTimetableViewChangeVisible.call(ctx)
            },
            get publishedTimetableSemesters() {
                return computed.publishedTimetableSemesters.call(ctx)
            },
            get manualTimetableCourseSections() {
                return computed.manualTimetableCourseSections.call(ctx)
            },
            get manualTimetableCourses() {
                return computed.manualTimetableCourses.call(ctx)
            },
        }
        const adoptPublishedTimetable = vi.fn(async () => {
            ctx.overview.personal_timetable = {
                id: 9,
                active_course_group_keys: ['saved-group'],
                timetable: ctx.overview.published_timetable.timetable,
            }

            return true
        })
        ctx.studentTimetablesStore = { adoptPublishedTimetable }

        await methods.adoptPublishedTimetable.call(ctx)

        expect(adoptPublishedTimetable).toHaveBeenCalledOnce()
        expect(ctx.showManualTimetable).toBe(true)
        expect(ctx.showEvaluationSettings).toBe(false)
        expect(ctx.manualTimetableMode).toBe('personal')
        expect(ctx.manualTimetableTitle).toBe('Mein Stundenplan')
        expect(ctx.manualSelectedCourseKeys).toEqual(['course-1'])
        expect(ctx.manualSelectedCourseGroupKeys).toEqual(['saved-group'])
        expect(ctx.personalTimetableViewChangedMessageVisible).toBe(true)
        expect(ctx.personalTimetableViewChangeVisible).toBe(true)
        expect(ctx.personalTimetableSaving).toBe(false)
        methods.dismissPersonalTimetableViewChangeMessage.call(ctx)
        expect(ctx.personalTimetableViewChangedMessageVisible).toBe(false)
        expect(ctx.personalTimetableViewChangeVisible).toBe(false)
        expect(pushedRoutes).toEqual([
            {
                path: '/students-timetables/overview',
                query: {
                    manual_timetable: 'personal',
                },
            },
        ])
    })

    it('opens an empty personal timetable when no personal timetable exists yet', () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const pushedRoutes: unknown[] = []
        const ctx: any = {
            ...methods,
            showManualTimetable: false,
            showEvaluationSettings: true,
            manualTimetableMode: 'manual',
            manualSelectedCourseKeys: [],
            manualSelectedCourseGroupKeys: [],
            overview: {
                personal_timetable: null,
                manual_timetable: {
                    sections: [],
                },
            },
            $route: {
                path: '/students-timetables/overview',
                query: {},
            },
            $router: {
                push(route: unknown) {
                    pushedRoutes.push(route)
                },
            },
            ensureManualTimetableDefaultSelection: methods.ensureManualTimetableDefaultSelection,
            applySavedTimetableSelection: methods.applySavedTimetableSelection,
            get manualTimetableSelection() {
                return computed.manualTimetableSelection.call(ctx)
            },
            get manualTimetableTitle() {
                return computed.manualTimetableTitle.call(ctx)
            },
            get personalTimetableSelection() {
                return computed.personalTimetableSelection.call(ctx)
            },
            get hasPersonalTimetable() {
                return computed.hasPersonalTimetable.call(ctx)
            },
            get activeSavedTimetableSelection() {
                return computed.activeSavedTimetableSelection.call(ctx)
            },
            get activeSavedTimetableCourseGroupKeys() {
                return computed.activeSavedTimetableCourseGroupKeys.call(ctx)
            },
            get publishedTimetablePayload() {
                return computed.publishedTimetablePayload.call(ctx)
            },
            get publishedTimetableVisible() {
                return computed.publishedTimetableVisible.call(ctx)
            },
            get personalTimetableEmptyVisible() {
                return computed.personalTimetableEmptyVisible.call(ctx)
            },
            get publishedTimetableSemesters() {
                return computed.publishedTimetableSemesters.call(ctx)
            },
            get manualTimetableCourseSections() {
                return computed.manualTimetableCourseSections.call(ctx)
            },
            get manualTimetableCourses() {
                return computed.manualTimetableCourses.call(ctx)
            },
        }

        methods.openPersonalTimetable.call(ctx)

        expect(ctx.showManualTimetable).toBe(true)
        expect(ctx.showEvaluationSettings).toBe(false)
        expect(ctx.manualTimetableMode).toBe('personal')
        expect(ctx.manualTimetableTitle).toBe('Mein Stundenplan')
        expect(ctx.hasPersonalTimetable).toBe(false)
        expect(ctx.publishedTimetableVisible).toBe(false)
        expect(ctx.personalTimetableEmptyVisible).toBe(true)
        expect(pushedRoutes).toEqual([
            {
                path: '/students-timetables/overview',
                query: {
                    manual_timetable: 'personal',
                },
            },
        ])
    })

    it('builds manual timetable cells from selected student courses', () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const courseGroup = {
            key: 'group-1',
            course: 'D1',
            display_label: 'D1 - 4A - MUE',
            weekday: 1,
            hour: 11,
            time_from: '16:10',
            time_until: '16:55',
            teacher: 'MUE',
            rooms: ['101'],
        }
        const course = {
            key: 'course-1',
            code: 'D1',
            name: 'Deutsch',
            course_groups: [courseGroup],
        }
        const ctx: any = {
            ...methods,
            manualSelectedCourseKeys: ['course-1'],
            overview: {
                school_hours: [],
                manual_timetable: {
                    sections: [
                        {
                            key: 'proposed',
                            items: [course],
                        },
                    ],
                },
            },
            get manualTimetableSelection() {
                return computed.manualTimetableSelection.call(ctx)
            },
            get manualTimetableCourseSections() {
                return computed.manualTimetableCourseSections.call(ctx)
            },
            get manualTimetableCourses() {
                return computed.manualTimetableCourses.call(ctx)
            },
            get manualSelectedCourses() {
                return computed.manualSelectedCourses.call(ctx)
            },
            get manualSelectedCourseGroups() {
                return computed.manualSelectedCourseGroups.call(ctx)
            },
        }

        expect(computed.manualSelectedCourseGroups.call(ctx)).toEqual([courseGroup])
        expect(computed.manualTimetableHours.call(ctx)).toEqual([
            {
                value: 11,
                hourLabel: '11.',
                timeFrom: '16:10',
                timeUntil: '16:55',
            },
        ])
        expect(methods.manualGroupsForCell.call(ctx, 1, 11)).toEqual([courseGroup])
        expect(methods.manualCourseGroupDetails(courseGroup)).toBe('D1 - 4A - MUE · MUE · 101')
    })

    it('builds the personal timetable course selection chips from the saved timetable courses', () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const ctx: any = {
            ...methods,
            manualTimetableMode: 'personal',
            hiddenSavedTimetableCourseChipKeys: [],
            selectedStudentCoursePickerTab: 'missing',
            selectedStudentCoursePickerMenuKey: '',
            studentCoursePickerDialogOpen: false,
            overview: {
                personal_timetable: {
                    id: 9,
                    timetable: {
                        weekdays: [
                            { label: 'Mo' },
                            { label: 'Di' },
                            { label: 'Do' },
                        ],
                        semesters: [
                            {
                                label: 'Semester',
                                date_range: '16.02.2026 - 10.07.2026',
                                weeks: [
                                    {
                                        hours: [
                                            {
                                                hour: 1,
                                                from: '19:30',
                                                until: '21:10',
                                                cells: [
                                                    {},
                                                    {
                                                        status: 'warning',
                                                        courses: [
                                                            {
                                                                label: 'E5 - 3R - HÖF',
                                                                details: '1w, 2w',
                                                            },
                                                            {
                                                                label: 'D5 - 3R - SHAM',
                                                                details: 'FU · 1w, 2w',
                                                                student_course_type: 'missing',
                                                                student_course_badge: 'Fehlend',
                                                            },
                                                        ],
                                                    },
                                                    {},
                                                ],
                                            },
                                            {
                                                hour: 13,
                                                from: '19:30',
                                                until: '20:15',
                                                cells: [
                                                    {},
                                                    {
                                                        status: 'filled',
                                                        courses: [
                                                            {
                                                                label: 'CH1 - 4F - KOW',
                                                                details: '1w, 2w',
                                                            },
                                                        ],
                                                    },
                                                    {},
                                                ],
                                            },
                                            {
                                                hour: 14,
                                                from: '20:25',
                                                until: '21:10',
                                                cells: [
                                                    {
                                                        status: 'filled',
                                                        courses: [
                                                            {
                                                                label: 'BU2 - 5CK - FUCH',
                                                                details: '1w',
                                                                student_course_type: 'missing',
                                                                student_course_badge: 'Fehlend',
                                                            },
                                                        ],
                                                    },
                                                    {
                                                        status: 'filled',
                                                        courses: [
                                                            {
                                                                label: 'CH1 - 4F - KOW',
                                                                details: '1w, 2w',
                                                            },
                                                        ],
                                                    },
                                                    {},
                                                ],
                                            },
                                            {
                                                hour: 15,
                                                from: '21:10',
                                                until: '21:55',
                                                cells: [
                                                    {
                                                        status: 'filled',
                                                        courses: [
                                                            {
                                                                label: 'BU2 - 5CK - FUCH',
                                                                details: '1w',
                                                                student_course_type: 'missing',
                                                                student_course_badge: 'Fehlend',
                                                            },
                                                        ],
                                                    },
                                                    {},
                                                    {},
                                                ],
                                            },
                                            {
                                                hour: 16,
                                                from: '20:25',
                                                until: '21:55',
                                                cells: [
                                                    {},
                                                    {},
                                                    {
                                                        status: 'filled',
                                                        courses: [
                                                            {
                                                                label: 'E5 - 3R - HÖF',
                                                                details: '1w, 2w',
                                                            },
                                                        ],
                                                    },
                                                ],
                                            },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                },
                manual_timetable: {
                    sections: [
                        {
                            key: 'missing',
                            color: 'error',
                            items: [
                                {
                                    key: 'bu2',
                                    code: 'BU2',
                                    semester: 1,
                                    course_groups: [
                                        {
                                            key: 'bu2-14',
                                            display_label: 'BU2 - 5CK - FUCH',
                                            semester: 1,
                                            weekday: 1,
                                            time_from: '20:25',
                                            time_until: '21:10',
                                            recurrence_label: '1w',
                                            dates: ['2026-02-24', '2026-03-10'],
                                        },
                                        {
                                            key: 'bu2-15',
                                            display_label: 'BU2 - 5CK - FUCH',
                                            semester: 1,
                                            weekday: 1,
                                            time_from: '21:10',
                                            time_until: '21:55',
                                            recurrence_label: '1w',
                                            dates: ['2026-02-24', '2026-03-10'],
                                        },
                                    ],
                                },
                                {
                                    key: 'bu3',
                                    code: 'BU3',
                                    semester: 1,
                                    course_groups: [
                                        {
                                            key: 'bu3-14',
                                            display_label: 'BU3 - 5CK - TEST',
                                            semester: 1,
                                            weekday: 1,
                                            time_from: '20:25',
                                            time_until: '21:10',
                                            recurrence_label: '1w',
                                            dates: ['2026-03-10'],
                                        },
                                    ],
                                },
                                {
                                    key: 'bu4',
                                    code: 'BU4',
                                    semester: 1,
                                    course_groups: [
                                        {
                                            key: 'bu4-14',
                                            display_label: 'BU4 - 5CK - FREE',
                                            semester: 1,
                                            weekday: 1,
                                            time_from: '20:25',
                                            time_until: '21:10',
                                            recurrence_label: '1w',
                                            dates: ['2026-03-17'],
                                        },
                                    ],
                                },
                                {
                                    key: 'd5',
                                    code: 'D5',
                                    semester: 1,
                                    course_groups: [
                                        {
                                            key: 'd5-1',
                                            display_label: 'D5 - 3R - SHAM',
                                            weekday: 2,
                                            time_from: '19:30',
                                            time_until: '21:10',
                                            recurrence_label: '1w, 2w',
                                        },
                                    ],
                                },
                            ],
                        },
                        {
                            key: 'proposed',
                            color: 'primary',
                            items: [
                                {
                                    key: 'ch1',
                                    code: 'CH1',
                                    semester: 1,
                                    course_groups: [
                                        {
                                            key: 'ch1-13',
                                            display_label: 'CH1 - 4F - KOW',
                                            weekday: 2,
                                            time_from: '19:30',
                                            time_until: '20:15',
                                            recurrence_label: '1w, 2w',
                                        },
                                        {
                                            key: 'ch1-14',
                                            display_label: 'CH1 - 4F - KOW',
                                            weekday: 2,
                                            time_from: '20:25',
                                            time_until: '21:10',
                                            recurrence_label: '1w, 2w',
                                        },
                                    ],
                                },
                                {
                                    key: 'e5',
                                    code: 'E5',
                                    semester: 1,
                                    course_groups: [
                                        {
                                            key: 'e5-1',
                                            display_label: 'E5 - 3R - HÖF',
                                            weekday: 2,
                                            time_from: '19:30',
                                            time_until: '21:10',
                                            recurrence_label: '1w, 2w',
                                        },
                                        {
                                            key: 'e5-16',
                                            display_label: 'E5 - 3R - HÖF',
                                            weekday: 4,
                                            time_from: '20:25',
                                            time_until: '21:55',
                                            recurrence_label: '1w, 2w',
                                        },
                                    ],
                                },
                            ],
                        },
                        {
                            key: 'additional',
                            color: 'warning',
                            items: [
                                {
                                    key: 'ph2',
                                    code: 'PH2',
                                    semester: 1,
                                    course_groups: [
                                        {
                                            key: 'ph2-1',
                                            display_label: 'PH2 - 6A - ALT',
                                            weekday: 4,
                                            time_from: '18:45',
                                            time_until: '20:15',
                                            recurrence_label: '1w',
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                },
            },
            get personalTimetableSelection() {
                return computed.personalTimetableSelection.call(ctx)
            },
            get activeSavedTimetableSelection() {
                return computed.activeSavedTimetableSelection.call(ctx)
            },
            get publishedTimetablePayload() {
                return computed.publishedTimetablePayload.call(ctx)
            },
            get publishedTimetableWeekdays() {
                return computed.publishedTimetableWeekdays.call(ctx)
            },
            get publishedTimetableSemesters() {
                return computed.publishedTimetableSemesters.call(ctx)
            },
            get manualTimetableSelection() {
                return computed.manualTimetableSelection.call(ctx)
            },
            get manualTimetableCourseSections() {
                return computed.manualTimetableCourseSections.call(ctx)
            },
            get studentCoursePickerAllCourseGroups() {
                return computed.studentCoursePickerAllCourseGroups.call(ctx)
            },
            get studentCoursePickerAllEntries() {
                return computed.studentCoursePickerAllEntries.call(ctx)
            },
            get savedTimetableCourseChipEntries() {
                return computed.savedTimetableCourseChipEntries.call(ctx)
            },
            get savedTimetableCourseChips() {
                return computed.savedTimetableCourseChips.call(ctx)
            },
            get studentCoursePickerTabs() {
                return computed.studentCoursePickerTabs.call(ctx)
            },
            get studentCoursePickerSemesterContexts() {
                return computed.studentCoursePickerSemesterContexts.call(ctx)
            },
            get studentCoursePickerCourseGroups() {
                return computed.studentCoursePickerCourseGroups.call(ctx)
            },
            get studentCoursePickerCourseMenus() {
                return computed.studentCoursePickerCourseMenus.call(ctx)
            },
            get selectedStudentCoursePickerMenu() {
                return computed.selectedStudentCoursePickerMenu.call(ctx)
            },
            get selectedStudentCoursePickerEntryOptions() {
                return computed.selectedStudentCoursePickerEntryOptions.call(ctx)
            },
            get personalTimetableCourseSelectionVisible() {
                return computed.personalTimetableCourseSelectionVisible.call(ctx)
            },
        }

        expect(ctx.personalTimetableCourseSelectionVisible).toBe(true)
        expect(ctx.savedTimetableCourseChips).toEqual([
            expect.objectContaining({
                key: 'BU2 - 5CK - FUCH',
                label: 'BU2 - 5CK - FUCH · Mo 14.-15 (1w)',
                hasOverlap: false,
                studentCourseType: 'missing',
                studentCourseBadge: 'Fehlend',
            }),
            expect.objectContaining({
                key: 'CH1 - 4F - KOW',
                label: 'CH1 - 4F - KOW · Di 13.-14 (1w, 2w)',
                hasOverlap: false,
            }),
            expect.objectContaining({
                key: 'D5 - 3R - SHAM',
                label: 'D5 - 3R - SHAM · Di 1. (1w, 2w)',
                hasOverlap: true,
                studentCourseType: 'missing',
                studentCourseBadge: 'Fehlend',
            }),
            expect.objectContaining({
                key: 'E5 - 3R - HÖF',
                label: 'E5 - 3R - HÖF · Di 1., Do 16. (1w, 2w)',
                hasOverlap: true,
            }),
        ])
        expect(methods.savedTimetableCourseChipBadgeLabel(ctx.savedTimetableCourseChips[0])).toBe('F')
        expect(ctx.studentCoursePickerTabs).toEqual([
            { value: 'missing', label: 'Negative Kurse' },
            { value: 'proposed', label: 'Vorgesehene Kurse' },
            { value: 'additional', label: 'Zusätzliche Kurse' },
            { value: 'open', label: 'Offene Kurse' },
            { value: 'all', label: 'Alle' },
        ])
        expect(ctx.studentCoursePickerSemesterContexts).toEqual([
            {
                value: 1,
                label: 'Semester',
                dateRangeLabel: '16.02.2026 - 10.07.2026',
            },
        ])
        expect(ctx.studentCoursePickerCourseMenus.map(courseMenu => courseMenu.label)).toEqual(['BU', 'D'])
        expect(ctx.selectedStudentCoursePickerMenu).toBeNull()
        expect(ctx.selectedStudentCoursePickerEntryOptions).toEqual([])

        methods.selectStudentCoursePickerMenu.call(ctx, ctx.studentCoursePickerCourseMenus[0])

        expect(ctx.selectedStudentCoursePickerMenu).toEqual(expect.objectContaining({
            label: 'BU',
            semesterLabel: 'Semester',
            semesterDateRangeLabel: '16.02.2026 - 10.07.2026',
        }))
        expect(ctx.selectedStudentCoursePickerEntryOptions).toEqual([
            expect.objectContaining({
                label: 'BU2 - 5CK - FUCH',
                scheduleLabel: 'Mo 20:25 - 21:55 (1w)',
                isActive: true,
                isDisabled: false,
                color: 'success',
            }),
            expect.objectContaining({
                label: 'BU3 - 5CK - TEST',
                scheduleLabel: 'Mo 20:25 - 21:10 (1w)',
                hasBlockingOverlap: true,
                hasRelatedOverlap: false,
                isActive: false,
                isDisabled: true,
                color: 'error',
            }),
            expect.objectContaining({
                label: 'BU4 - 5CK - FREE',
                scheduleLabel: 'Mo 20:25 - 21:10 (1w)',
                hasBlockingOverlap: false,
                hasRelatedOverlap: true,
                isActive: false,
                isDisabled: false,
                color: 'warning',
            }),
        ])
        expect(methods.studentCoursePickerMenuHasActiveSelection.call(ctx, ctx.selectedStudentCoursePickerMenu)).toBe(true)

        methods.toggleStudentCoursePickerEntry.call(ctx, ctx.selectedStudentCoursePickerEntryOptions[1])

        expect(ctx.hiddenSavedTimetableCourseChipKeys).toEqual([])

        expect(methods.publishedTimetableCellCourses.call(ctx, {
            courses: [
                { label: 'BU2 - 5CK - FUCH' },
                { label: 'E5 - 3R - HÖF' },
            ],
        })).toHaveLength(2)

        methods.deselectSavedTimetableCourseChip.call(ctx, ctx.savedTimetableCourseChips[0])

        expect(ctx.hiddenSavedTimetableCourseChipKeys).toEqual(['BU2 - 5CK - FUCH'])
        expect(methods.publishedTimetableCellCourses.call(ctx, {
            courses: [
                { label: 'BU2 - 5CK - FUCH' },
                { label: 'E5 - 3R - HÖF' },
            ],
        })).toEqual([{ label: 'E5 - 3R - HÖF' }])

        methods.deselectAllSavedTimetableCourseChips.call(ctx)

        expect(ctx.hiddenSavedTimetableCourseChipKeys).toEqual(['BU2 - 5CK - FUCH', 'CH1 - 4F - KOW', 'D5 - 3R - SHAM', 'E5 - 3R - HÖF'])
        expect(ctx.savedTimetableCourseChips).toEqual([])
        expect(ctx.personalTimetableCourseSelectionVisible).toBe(true)

        ctx.selectedStudentCoursePickerTab = 'open'

        expect(ctx.studentCoursePickerCourseMenus.map(courseMenu => courseMenu.label)).toEqual(['BU', 'PH'])

        ctx.selectedStudentCoursePickerTab = 'all'

        expect(ctx.studentCoursePickerCourseMenus.map(courseMenu => courseMenu.label)).toEqual(['BU', 'CH', 'D', 'E', 'PH'])
        expect(ctx.selectedStudentCoursePickerEntryOptions[0]).toEqual(expect.objectContaining({
            courseChipKey: 'BU2 - 5CK - FUCH',
            isActive: false,
        }))

        methods.toggleStudentCoursePickerEntry.call(ctx, ctx.selectedStudentCoursePickerEntryOptions[0])

        expect(ctx.hiddenSavedTimetableCourseChipKeys).toEqual(['CH1 - 4F - KOW', 'D5 - 3R - SHAM', 'E5 - 3R - HÖF'])
    })

    it('keeps manual timetable course selection helpers for the editable manual preview', () => {
        const methods = (Overview as any).methods
        const computed = (Overview as any).computed
        const savedCourseGroup = {
            key: 'group-1',
            course: 'D5',
            display_label: 'D5 - 3R - SHAM',
            weekday: 2,
            hour: 14,
            time_from: '20:25',
            time_until: '21:55',
            recurrence_label: '1w, 2w',
        }
        const course = {
            key: 'course-1',
            code: 'D5',
            course_groups: [savedCourseGroup],
        }
        const ctx: any = {
            ...methods,
            manualTimetableMode: 'manual',
            manualSelectedCourseKeys: ['course-1'],
            manualSelectedCourseGroupKeys: ['group-1'],
            overview: {
                manual_timetable: {
                    sections: [
                        {
                            key: 'missing',
                            items: [course],
                        },
                    ],
                },
            },
            get manualTimetableSelection() {
                return computed.manualTimetableSelection.call(ctx)
            },
            get manualTimetableCourseSections() {
                return computed.manualTimetableCourseSections.call(ctx)
            },
            get manualTimetableCourses() {
                return computed.manualTimetableCourses.call(ctx)
            },
            get manualSelectedCourses() {
                return computed.manualSelectedCourses.call(ctx)
            },
            get manualSelectedCourseGroups() {
                return computed.manualSelectedCourseGroups.call(ctx)
            },
        }

        expect(methods.manualCourseGroupChipLabel.call(ctx, savedCourseGroup))
            .toBe('D5 - 3R - SHAM · Di 20:25 - 21:55 (1w, 2w)')
        expect(methods.manualCourseGroupStudentCourseBadge.call(ctx, savedCourseGroup)).toBe('Fehlend')
    })

    it('shows the imported student religion on the religion card', () => {
        const overviewPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
        )
        const source = readFileSync(overviewPath, 'utf8')

        expect(source).toContain('class="overview-selected-card__meta"')
        expect(source).toContain('grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));')
        expect(source).toContain('width: 100%;')
        expect(source).toContain('this.overview?.selection_items')
        expect(source).toContain('this.overview?.course_sections')
        expect(source).toContain('this.overview?.selection_options?.[optionKey]')
        expect(source).toContain('icon="mdi-pencil"')
        expect(source).toContain('@click="openSelectionDialog(item)"')
        expect(source).toContain('restoreSelectionDefaults')
        expect(source).toContain('class="overview-selection__reset ml-auto"')
        expect(source).toContain('Zurücksetzen')
        expect(source).toContain(':disabled="!hasSelectionOverride"')
        expect(source).toContain('selectionOverridePayload()')
        expect(source).toContain('this.studentTimetablesStore.updateProfileSelection')
        expect(source).toContain('this.studentTimetablesStore.restoreProfileSelection')
        expect(source).toContain('this.overview?.selection_override')
        expect(source).not.toContain('overview-selection__restore')
        expect(source).not.toContain('localStorage')
        expect(source).not.toContain('studentReligionMeta(religion)')
    })

    it('shows the student religion in the overview header', () => {
        const overviewPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
        )
        const source = readFileSync(overviewPath, 'utf8')

        expect(source).toContain('<span v-if="studentReligionLabel" class="hero-badge">{{ studentReligionLabel }}</span>')

        expect(Overview.computed.studentReligionLabel.call({
            overview: {
                student: { religion: 'Rk' },
            },
            selectionItems: [
                { key: 'semester', value: '8. Semester' },
                { key: 'religion', value: 'Ethik', meta: 'Rk' },
            ],
            user: {},
        })).toBe('Rk')

        expect(Overview.computed.studentReligionLabel.call({
            selectionItems: [
                { key: 'religion', value: 'Ethik', meta: 'Religion: Rk' },
            ],
            user: {},
        })).toBe('Rk')

        expect(Overview.computed.studentReligionLabel.call({
            selectionItems: [
                { key: 'religion', value: 'Ethik', meta: '' },
            ],
            user: {},
        })).toBe('Ethik')
    })

    it('shows completed and negative course summaries in the overview header', () => {
        const overviewPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
        )
        const source = readFileSync(overviewPath, 'utf8')

        expect(source).toContain('class="hero-course-history"')
        expect(source).toContain('heroCourseHistorySections')
        expect(source).toContain('Abgeschlossene Kurse')
        expect(source).toContain('Keine abgeschlossenen Kurse gefunden.')
        expect(source).toContain('Negative Kurse')
        expect(source).toContain('Keine negativen Kurse gefunden.')

        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: any = {
            ...methods,
            overview: {
                completed_courses: [
                    { code: 'ETH1', name: 'Ethik', grade: '2' },
                ],
                missing_courses: [
                    { code: 'D1', name: 'Deutsch', grade: '5' },
                ],
                course_sections: [
                    {
                        key: 'completed',
                        items: [
                            { code: 'ETH1', name: 'Ethik', grade: '2' },
                        ],
                    },
                    {
                        key: 'missing',
                        items: [
                            { code: 'D1', name: 'Deutsch', grade: '5' },
                        ],
                    },
                ],
            },
        }

        ctx.courseSections = computed.courseSections.call(ctx)

        expect(computed.heroCourseHistorySections.call(ctx)).toEqual([
            expect.objectContaining({
                key: 'completed',
                title: 'Abgeschlossene Kurse',
                empty: 'Keine abgeschlossenen Kurse gefunden.',
                items: [
                    { code: 'ETH1', name: 'Ethik', grade: '2' },
                ],
            }),
            expect.objectContaining({
                key: 'missing',
                title: 'Negative Kurse',
                empty: 'Keine negativen Kurse gefunden.',
                items: [
                    { code: 'D1', name: 'Deutsch', grade: '5' },
                ],
            }),
        ])
        expect(methods.courseHistoryCourseLabel({ code: 'ETH1', name: 'Ethik' })).toBe('ETH1 - Ethik')
        expect(methods.courseHistoryCourseLabel({ code: 'BU1', name: 'BU1' })).toBe('BU1')
        expect(methods.courseHistoryCourseMeta({ grade: '2' })).toBe('2')
    })

    it('hides the negative course summary in the overview header when no negative courses exist', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: any = {
            ...methods,
            overview: {
                completed_courses: [
                    { code: 'ETH1', name: 'Ethik', grade: '2' },
                ],
                missing_courses: [],
                course_sections: [],
            },
        }

        ctx.courseSections = computed.courseSections.call(ctx)

        expect(computed.heroCourseHistorySections.call(ctx)).toEqual([
            expect.objectContaining({
                key: 'completed',
                title: 'Abgeschlossene Kurse',
            }),
        ])
    })

    it('shows selectable negative planned and additional course cards in the overview data card', () => {
        const overviewPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
        )
        const source = readFileSync(overviewPath, 'utf8')

        expect(source).toContain('class="overview-course-selection"')
        expect(source).toContain('overviewCourseSelectionVisible && !showEvaluationSettings && !showManualTimetable')
        expect(source).toContain('overviewCourseSelectionSections')
        expect(source).toContain('Negative Kurse')
        expect(source).toContain('Vorgesehene Kurse')
        expect(source).toContain('Zusätzliche Kurse')
        expect(source).toContain('Ausgewählt')
        expect(source).toContain('Maximal 10/30')
        expect(source).toContain('setOverviewCourseGroupSelection(section.key, true)')
        expect(source).toContain('toggleOverviewCourseItem(course, section.key)')
        expect(source).toContain("const noAutomaticTimetableCourseValue = '__none'")
        expect(source).toContain('class="overview-course-selection__toolbar"')
        expect(source).toContain('Vorauswahl zurücksetzen')
        expect(source).toContain(':disabled="!automaticTimetableCoursePreselectionResetAvailable"')
        expect(source).toContain('@click="resetAutomaticTimetableCoursePreselection"')
        expect(source).toContain('[`overview-course-selection__item--${section.key}`]: true')
        expect(source).toContain('students-timetable-v2-course-card-title')
        expect(source).toContain('students-timetable-v2-completed-courses__item--planned')
        expect(source).toContain('overviewCourseGroupSourceCourses(courseGroup)')
        expect(source).toContain('overviewCourseGroupAutomaticCourses(courseGroup)')
        expect(source).toContain('canonicalizeAutomaticTimetableCourseQuery()')

        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: any = {
            ...methods,
            $route: { query: {} },
            setAutomaticTimetableCourseKeys(courseKeys: string[]) {
                ctx.automaticTimetableCourseKeys = courseKeys
            },
            overview: {
                course_sections: [
                    {
                        key: 'missing',
                        items: [
                            { key: 'missing-d1', code: 'D1', name: 'Deutsch', hours: 3 },
                        ],
                    },
                    {
                        key: 'proposed',
                        items: [
                            { key: 'planned-m1', code: 'M1', name: 'Mathematik', hours: 4 },
                        ],
                    },
                    {
                        key: 'additional',
                        items: [
                            { key: 'additional-bu2', code: 'BU2', name: 'Biologie', hours: 2 },
                        ],
                    },
                ],
            },
        }

        ctx.courseSections = computed.courseSections.call(ctx)
        ctx.overviewDefaultSelectedCourseKeys = computed.overviewDefaultSelectedCourseKeys.call(ctx)
        ctx.automaticTimetableCourseKeys = computed.automaticTimetableCourseKeys.call(ctx)
        ctx.overviewSelectedCourseLimitItems = computed.overviewSelectedCourseLimitItems.call(ctx)

        expect(ctx.automaticTimetableCourseKeys).toEqual(['missing:D1', 'planned:M1'])
        expect(computed.overviewSelectedCourseLimitSummary.call(ctx)).toEqual({
            count: 2,
            hours: 7,
            countLabel: '2 Kurse',
            hoursLabel: '7 Std.',
        })
        expect(computed.overviewCourseSelectionSections.call(ctx)).toEqual([
            expect.objectContaining({
                key: 'missing',
                selectedItems: [
                    expect.objectContaining({ key: 'missing-d1' }),
                ],
            }),
            expect.objectContaining({
                key: 'planned',
                bulkSelectable: true,
                selectedItems: [
                    expect.objectContaining({ key: 'planned-m1' }),
                ],
            }),
            expect.objectContaining({
                key: 'additional',
                selectable: false,
                selectedItems: [
                    expect.objectContaining({ key: 'additional-bu2' }),
                ],
            }),
        ])

        ctx.$route = { query: { automatic_timetable_courses: '__none' } }
        expect(computed.automaticTimetableCourseKeys.call(ctx)).toEqual([])

        const partialCtx: any = {
            ...methods,
            $route: { query: {} },
            overview: {
                course_sections: [
                    {
                        key: 'missing',
                        items: [],
                    },
                    {
                        key: 'proposed',
                        items: [
                            { key: 'planned-m1', code: 'M1', name: 'Mathematik', hours: 4 },
                        ],
                    },
                    {
                        key: 'additional',
                        items: [],
                    },
                ],
            },
        }

        partialCtx.courseSections = computed.courseSections.call(partialCtx)
        partialCtx.overviewDefaultSelectedCourseKeys = computed.overviewDefaultSelectedCourseKeys.call(partialCtx)
        partialCtx.automaticTimetableCourseKeys = computed.automaticTimetableCourseKeys.call(partialCtx)

        partialCtx.overviewCourseSelectionSections = computed.overviewCourseSelectionSections.call(partialCtx)

        expect(partialCtx.overviewCourseSelectionSections.map(section => section.key)).toEqual(['planned'])
        expect(computed.overviewCourseSelectionVisible.call(partialCtx)).toBe(true)

        const emptyCtx: any = {
            ...methods,
            $route: { query: {} },
            overview: {
                course_sections: [
                    { key: 'missing', items: [] },
                    { key: 'proposed', items: [] },
                    { key: 'additional', items: [] },
                ],
            },
        }

        emptyCtx.courseSections = computed.courseSections.call(emptyCtx)
        emptyCtx.overviewDefaultSelectedCourseKeys = computed.overviewDefaultSelectedCourseKeys.call(emptyCtx)
        emptyCtx.automaticTimetableCourseKeys = computed.automaticTimetableCourseKeys.call(emptyCtx)

        emptyCtx.overviewCourseSelectionSections = computed.overviewCourseSelectionSections.call(emptyCtx)

        expect(emptyCtx.overviewCourseSelectionSections).toEqual([])
        expect(computed.overviewCourseSelectionVisible.call(emptyCtx)).toBe(false)
    })

    it('uses the admin course-limit strategy for student overview default course selection', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: any = {
            ...methods,
            $route: { query: {} },
            setAutomaticTimetableCourseKeys(courseKeys: string[]) {
                ctx.automaticTimetableCourseKeys = courseKeys
            },
            overview: {
                course_sections: [
                    {
                        key: 'missing',
                        items: [
                            { key: 'missing-d1', code: 'D1', name: 'Deutsch 1', hours: 4 },
                        ],
                    },
                    {
                        key: 'proposed',
                        items: [
                            { key: 'planned-bu2', code: 'BU2', name: 'Biologie 2', hours: 2 },
                            { key: 'planned-ch2', code: 'CH2', name: 'Chemie 2', hours: 2 },
                            { key: 'planned-d2', code: 'D2', name: 'Deutsch 2', hours: 3 },
                            { key: 'planned-e2', code: 'E2', name: 'Englisch 2', hours: 3 },
                            { key: 'planned-gs2', code: 'GS2', name: 'Geschichte 2', hours: 2 },
                            { key: 'planned-gw2', code: 'GW2', name: 'Geografie 2', hours: 2 },
                            { key: 'planned-inf2', code: 'INF2', name: 'Informatik 2', hours: 2 },
                            { key: 'planned-kg2', code: 'KG2', name: 'Kunstgeschichte 2', hours: 2 },
                            { key: 'planned-m1', code: 'M1', name: 'Mathematik 1', hours: 4 },
                            { key: 'planned-m2', code: 'M2', name: 'Mathematik 2', hours: 4 },
                            { key: 'planned-me2', code: 'ME2', name: 'Musik 2', hours: 2 },
                            { key: 'planned-ph2', code: 'PH2', name: 'Physik 2', hours: 2 },
                        ],
                    },
                ],
            },
        }

        ctx.courseSections = computed.courseSections.call(ctx)

        expect(computed.overviewDefaultSelectedCourseKeys.call(ctx)).toEqual([
            'missing:D1',
            'planned:BU2',
            'planned:CH2',
            'planned:E2',
            'planned:GS2',
            'planned:GW2',
            'planned:INF2',
            'planned:KG2',
            'planned:M1',
            'planned:ME2',
        ])
    })

    it('marks student overview courses without offers as unavailable and keeps them unselectable', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: any = {
            ...methods,
            $route: { query: {} },
            setAutomaticTimetableCourseKeys(courseKeys: string[]) {
                ctx.automaticTimetableCourseKeys = courseKeys
            },
            overview: {
                course_sections: [
                    {
                        key: 'proposed',
                        items: [
                            { key: 'planned-d5', code: 'D5', name: 'Deutsch 5', hours: 3, course_groups: [] },
                            {
                                key: 'planned-e5',
                                code: 'E5',
                                name: 'Englisch 5',
                                hours: 3,
                                course_groups: [{ key: 'e5-a', course: 'E5', weekday: 1, hour: 1 }],
                            },
                        ],
                    },
                ],
            },
        }

        ctx.courseSections = computed.courseSections.call(ctx)
        ctx.overviewDefaultSelectedCourseKeys = computed.overviewDefaultSelectedCourseKeys.call(ctx)
        ctx.automaticTimetableCourseKeys = computed.automaticTimetableCourseKeys.call(ctx)

        const [unavailableCourse, availableCourse] = methods.overviewCourseGroupItems.call(ctx, 'planned')

        expect(methods.overviewCourseItemUnavailable.call(ctx, unavailableCourse, 'planned')).toBe(true)
        expect(methods.overviewCourseItemColor.call(ctx, unavailableCourse, 'planned')).toBe('error')
        expect(methods.overviewCourseItemSelectionDisabled.call(ctx, unavailableCourse, 'planned')).toBe(true)
        expect(methods.overviewCourseItemSelectionDisabledLabel.call(ctx, unavailableCourse, 'planned')).toBe('Kein angebotener Kurs vorhanden')
        expect(computed.automaticTimetableCourseKeys.call(ctx)).toEqual(['planned:E5'])

        methods.setOverviewCourseGroupSelection.call(ctx, 'planned', true)

        expect(methods.overviewCourseItemSelected.call(ctx, unavailableCourse, 'planned')).toBe(false)
        expect(methods.overviewCourseItemSelected.call(ctx, availableCourse, 'planned')).toBe(true)
    })

    it('marks planned route courses without matching offers as unavailable like the admin selection', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: any = {
            ...methods,
            automaticTimetableInitialRouteCourseKeys: ['planned:VWA'],
            $route: {
                query: {
                    automatic_timetable_courses: ['planned:VWA'],
                },
            },
            overview: {
                manual_timetable: {
                    sections: [
                        {
                            key: 'proposed',
                            items: [
                                {
                                    key: 'planned-e5',
                                    code: 'E5',
                                    name: 'Englisch 5',
                                    course_groups: [{ key: 'e5-a', course: 'E5', weekday: 1, hour: 1 }],
                                },
                            ],
                        },
                    ],
                },
                course_sections: [
                    {
                        key: 'proposed',
                        items: [
                            { key: 'planned-vwa', code: 'VWA', name: 'Vorwissenschaftliche Arbeit', hours: 0 },
                        ],
                    },
                ],
            },
        }

        ctx.courseSections = computed.courseSections.call(ctx)
        ctx.manualTimetableSelection = computed.manualTimetableSelection.call(ctx)
        ctx.manualTimetableCourseSections = computed.manualTimetableCourseSections.call(ctx)
        ctx.manualTimetableCourses = computed.manualTimetableCourses.call(ctx)
        ctx.overviewDefaultSelectedCourseKeys = computed.overviewDefaultSelectedCourseKeys.call(ctx)
        ctx.automaticTimetableCourseKeys = computed.automaticTimetableCourseKeys.call(ctx)

        const [vwaCourse] = methods.overviewCourseGroupItems.call(ctx, 'planned')

        expect(methods.overviewCourseItemUnavailable.call(ctx, vwaCourse, 'planned')).toBe(true)
        expect(methods.overviewCourseItemColor.call(ctx, vwaCourse, 'planned')).toBe('error')
        expect(methods.overviewCourseItemSelectionDisabled.call(ctx, vwaCourse, 'planned')).toBe(true)
        expect(methods.overviewCourseItemSelected.call(ctx, vwaCourse, 'planned')).toBe(false)
        expect(computed.overviewSelectedCourseLimitItems.call(ctx)).toEqual([])
    })

    it('uses automatic proposed courses before legacy proposed course fallbacks', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: any = {
            ...methods,
            $route: { query: {} },
            overview: {
                automatic_course_selection: {
                    sections: [
                        {
                            key: 'proposed',
                            items: [
                                { key: 'automatic-l2', code: 'L2', name: 'Latein 2', hours: 3 },
                            ],
                        },
                    ],
                },
                proposed_courses: [
                    { key: 'fallback-m2', code: 'M2', name: 'Mathematik 2', hours: 4 },
                ],
                course_sections: [
                    {
                        key: 'proposed',
                        items: [
                            { key: 'legacy-d2', code: 'D2', name: 'Deutsch 2', hours: 3 },
                        ],
                    },
                ],
            },
        }

        ctx.courseSections = computed.courseSections.call(ctx)

        expect(methods.overviewCourseGroupItems.call(ctx, 'planned').map((course: Record<string, string>) => course.code))
            .toEqual(['L2'])
    })

    it('selects automatic proposed courses from legacy serialized route keys with compact url keys', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const serializedCourses = [
            '101|4|common|BU2|BU|Biologie 2|BU2',
            '102|4|common|CH1|CH|Chemie 1|CH1',
            '110|5|common|CH2|CH|Chemie 2|CH2',
        ]
        const ctx: any = {
            ...methods,
            automaticTimetableInitialRouteCourseKeys: serializedCourses,
            $route: {
                query: {
                    automatic_timetable_courses: serializedCourses,
                },
            },
            overview: {
                automatic_course_selection: {
                    sections: [
                        {
                            key: 'proposed',
                            items: [
                                { key: serializedCourses[0], code: 'BU2', name: 'Biologie 2', hours: 2 },
                                { key: serializedCourses[1], code: 'CH1', name: 'Chemie 1', hours: 2 },
                                { key: serializedCourses[2], code: 'CH2', name: 'Chemie 2', hours: 2 },
                            ],
                        },
                    ],
                },
                course_sections: [],
            },
        }

        ctx.courseSections = computed.courseSections.call(ctx)
        ctx.automaticTimetableCourseKeys = computed.automaticTimetableCourseKeys.call(ctx)

        const plannedCourseItems = methods.overviewCourseGroupItems.call(ctx, 'planned')
        const selectedCourseItems = computed.overviewSelectedCourseLimitItems.call(ctx)

        expect(ctx.automaticTimetableCourseKeys).toEqual(['planned:BU2', 'planned:CH1', 'planned:CH2'])
        expect(plannedCourseItems.map((course: Record<string, string>) => course.code)).toEqual(['BU2', 'CH1', 'CH2'])
        expect(plannedCourseItems.every((course: Record<string, string>) =>
            methods.overviewCourseItemSelected.call(ctx, course, 'planned'))).toBe(true)
        expect(selectedCourseItems.map((course: Record<string, string>) => course.code)).toEqual(['BU2', 'CH1', 'CH2'])
    })

    it('replaces legacy serialized automatic timetable course urls with compact course keys', () => {
        const methods = (Overview as any).methods
        const serializedCourses = [
            '101|4|common|BU2|BU|Biologie 2|BU2',
            '102|4|common|CH1|CH|Chemie 1|CH1',
        ]
        const replace = vi.fn()
        const ctx: any = {
            ...methods,
            $route: {
                path: '/students-timetables/overview',
                query: {
                    automatic_timetable_courses: serializedCourses,
                    manual_timetable: '1',
                },
            },
            $router: {
                replace,
            },
            overview: {
                automatic_course_selection: {
                    sections: [
                        {
                            key: 'proposed',
                            items: [
                                { key: serializedCourses[0], code: 'BU2', name: 'Biologie 2' },
                                { key: serializedCourses[1], code: 'CH1', name: 'Chemie 1' },
                            ],
                        },
                    ],
                },
                course_sections: [],
            },
        }

        methods.canonicalizeAutomaticTimetableCourseQuery.call(ctx)

        expect(replace).toHaveBeenCalledWith({
            path: '/students-timetables/overview',
            query: {
                automatic_timetable_courses: ['planned:BU2', 'planned:CH1'],
                manual_timetable: '1',
            },
        })
    })

    it('removes automatic course and criteria query values from generated result urls', () => {
        const methods = (Overview as any).methods
        const replace = vi.fn()
        const ctx: any = {
            ...methods,
            $route: {
                path: '/students-timetables/overview',
                query: {
                    automatic_timetable: 'result',
                    automatic_timetable_courses: ['planned:BU2', 'planned:CH1'],
                    automatic_timetable_additional_courses: ['additional:TT'],
                    automatic_timetable_criteria: 'saturday_free',
                    manual_timetable: '1',
                },
            },
            $router: {
                replace,
            },
        }

        methods.canonicalizeAutomaticTimetableCourseQuery.call(ctx)

        expect(replace).toHaveBeenCalledWith({
            path: '/students-timetables/overview',
            query: {
                automatic_timetable: 'result',
                automatic_timetable_additional_courses: ['additional:TT'],
            },
        })
    })

    it('stores automatic timetable additional course selections in the result url', () => {
        const methods = (Overview as any).methods
        const push = vi.fn()
        const ctx: any = {
            ...methods,
            automaticTimetableAdditionalCourseKeys: [],
            $route: {
                path: '/students-timetables/overview',
                query: {
                    automatic_timetable: 'result',
                },
            },
            $router: {
                push,
            },
            overview: {
                automatic_course_selection: { sections: [] },
                course_sections: [],
            },
        }

        expect(methods.automaticTimetableAdditionalCourseKeysFromRoute.call(ctx, {
            automatic_timetable_additional_courses: ['additional:TT', 'planned:M1'],
        })).toEqual(['additional:TT'])

        methods.setAutomaticTimetableAdditionalCourseKeys.call(ctx, ['additional:TT'])

        expect(push).toHaveBeenCalledWith({
            path: '/students-timetables/overview',
            query: {
                automatic_timetable: 'result',
                automatic_timetable_additional_courses: ['additional:TT'],
            },
        })
    })

    it('hydrates explicit automatic timetable route courses into planned overview courses', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const serializedCourses = [
            '26|4|common|BU2|BU|Biologie 2|BU2',
            '27|4|common|CH1|CH|Chemie 1|CH1',
            '24|3|common|R/ET3|R/ET|Religion/Ethik 3|ETH3',
            '21|3|common|GS2|GS|Geschichte 2|GS2',
            '12|2|common|GW2|GW|Geografie 2|GW2',
            '32|4|common|M4|M|Mathematik 4|M4',
            '36|5|common|D5|D|Deutsch 5|D5',
            '37|5|common|E5|E|Englisch 5|E5',
            '23|3|common|M3|M|Mathematik 3|M3',
        ]
        const ctx: any = {
            ...methods,
            automaticTimetableInitialRouteCourseKeys: serializedCourses,
            $route: {
                query: {
                    automatic_timetable_courses: serializedCourses,
                },
            },
            overview: {
                course_sections: [
                    {
                        key: 'missing',
                        items: [],
                    },
                    {
                        key: 'proposed',
                        items: [],
                    },
                    {
                        key: 'additional',
                        items: [],
                    },
                ],
            },
        }

        ctx.courseSections = computed.courseSections.call(ctx)
        ctx.automaticTimetableCourseKeys = computed.automaticTimetableCourseKeys.call(ctx)
        ctx.overviewDefaultSelectedCourseKeys = computed.overviewDefaultSelectedCourseKeys.call(ctx)
        ctx.automaticTimetableCoursePreselectionKeys = computed.automaticTimetableCoursePreselectionKeys.call(ctx)

        const plannedCourseItems = methods.overviewCourseGroupItems.call(ctx, 'planned')
        const selectedCourseItems = computed.overviewSelectedCourseLimitItems.call(ctx)

        expect(plannedCourseItems.map((course: Record<string, string>) => course.key)).toEqual([
            serializedCourses[0],
            serializedCourses[1],
            serializedCourses[6],
            serializedCourses[7],
            serializedCourses[2],
            serializedCourses[3],
            serializedCourses[4],
            serializedCourses[8],
            serializedCourses[5],
        ])
        expect(selectedCourseItems.map((course: Record<string, string>) => course.code)).toEqual([
            'BU2',
            'CH1',
            'D5',
            'E5',
            'ETH3',
            'GS2',
            'GW2',
            'M3',
            'M4',
        ])
        expect(selectedCourseItems.map((course: Record<string, string>) => course.name)).toEqual([
            'Biologie 2',
            'Chemie 1',
            'Deutsch 5',
            'Englisch 5',
            'Religion/Ethik 3',
            'Geschichte 2',
            'Geografie 2',
            'Mathematik 3',
            'Mathematik 4',
        ])
        expect(computed.automaticTimetableCoursePreselectionResetAvailable.call(ctx)).toBe(true)
    })

    it('matches admin grouping for negative and planned automatic timetable courses', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const serializedCourses = [
            '26|4|common|BU2|BU|Biologie 2|BU2',
            '27|4|common|CH1|CH|Chemie 1|CH1',
            '35|5|common|CH2|CH|Chemie 2|CH2',
        ]
        const ctx: any = {
            ...methods,
            automaticTimetableInitialRouteCourseKeys: serializedCourses,
            $route: {
                query: {
                    automatic_timetable_courses: serializedCourses,
                },
            },
            overview: {
                course_sections: [
                    {
                        key: 'missing',
                        items: [
                            { key: 'negative-ch1', code: 'CH1', name: 'Chemie 1', grade: '5', hours: 3 },
                        ],
                    },
                    {
                        key: 'proposed',
                        items: [],
                    },
                    {
                        key: 'additional',
                        items: [],
                    },
                ],
            },
        }

        ctx.courseSections = computed.courseSections.call(ctx)
        ctx.automaticTimetableCourseKeys = computed.automaticTimetableCourseKeys.call(ctx)

        const missingCourseItems = methods.overviewCourseGroupItems.call(ctx, 'missing')
        const plannedCourseItems = methods.overviewCourseGroupItems.call(ctx, 'planned')
        const plannedCodes = plannedCourseItems.map((course: Record<string, string>) => course.code)
        const chemie2Course = plannedCourseItems.find((course: Record<string, string>) => course.code === 'CH2')

        expect(missingCourseItems.map((course: Record<string, string>) => course.code)).toEqual(['CH1'])
        expect(plannedCodes).toEqual(['BU2', 'CH2'])
        expect(plannedCodes).not.toContain('CH1')
        expect(methods.overviewCourseItemDefaultSelected.call(ctx, chemie2Course, 'planned')).toBe(false)
        expect(methods.overviewCourseItemSelectionDisabled.call(ctx, chemie2Course, 'planned')).toBe(false)
        expect(methods.overviewCourseItemColor.call(ctx, chemie2Course, 'planned')).toBe('success')
    })

    it('resets the automatic timetable course preselection to the imported course defaults', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const serializedCourses = [
            '26|4|common|BU2|BU|Biologie 2|BU2',
            '27|4|common|CH1|CH|Chemie 1|CH1',
            '36|5|common|D5|D|Deutsch 5|D5',
        ]
        const push = vi.fn()
        const ctx: any = {
            ...methods,
            automaticTimetableInitialRouteCourseKeys: serializedCourses,
            $route: {
                path: '/students-timetables/overview',
                query: {
                    automatic_timetable: 'courses',
                    automatic_timetable_courses: [serializedCourses[0], serializedCourses[2]],
                    manual_timetable: '1',
                },
            },
            $router: {
                push,
            },
            overview: {
                course_sections: [
                    { key: 'missing', items: [] },
                    { key: 'proposed', items: [] },
                    { key: 'additional', items: [] },
                ],
            },
            get courseSections() {
                return computed.courseSections.call(ctx)
            },
            get automaticTimetableCourseKeys() {
                return computed.automaticTimetableCourseKeys.call(ctx)
            },
            get overviewDefaultSelectedCourseKeys() {
                return computed.overviewDefaultSelectedCourseKeys.call(ctx)
            },
            get automaticTimetableCoursePreselectionKeys() {
                return computed.automaticTimetableCoursePreselectionKeys.call(ctx)
            },
            get automaticTimetableCoursePreselectionResetAvailable() {
                return computed.automaticTimetableCoursePreselectionResetAvailable.call(ctx)
            },
        }

        expect(ctx.automaticTimetableCoursePreselectionResetAvailable).toBe(true)

        methods.resetAutomaticTimetableCoursePreselection.call(ctx)

        expect(push).toHaveBeenCalledWith({
            path: '/students-timetables/overview',
            query: {
                automatic_timetable: 'courses',
                manual_timetable: '1',
            },
        })

        push.mockClear()
        ctx.$route = {
            path: '/students-timetables/overview',
            query: {
                automatic_timetable: 'courses',
                automatic_timetable_courses: serializedCourses,
            },
        }

        expect(ctx.automaticTimetableCoursePreselectionResetAvailable).toBe(false)

        methods.resetAutomaticTimetableCoursePreselection.call(ctx)

        expect(push).not.toHaveBeenCalled()
    })

    it('orders overview courses ascending in all student course lists', () => {
        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: any = {
            ...methods,
            overview: {
                course_sections: [
                    {
                        key: 'missing',
                        items: [
                            { key: 'm2', code: 'M2', semester: 1 },
                            { key: 'd1', code: 'D1', semester: 1 },
                            { key: 'd10', code: 'D10', semester: 1 },
                            { key: 'e1', code: 'E1', semester: 2 },
                        ],
                    },
                    {
                        key: 'additional',
                        items: [
                            { key: 'd2', code: 'D2', semester: 2 },
                            { key: 'e2', code: 'E2', semester: 2 },
                            { key: 'eth2', code: 'ETH2', semester: 2 },
                            { key: 'gs1', code: 'GS1', semester: 2 },
                            { key: 'gw2', code: 'GW2', semester: 2 },
                            { key: 'm2-additional', code: 'M2', semester: 2 },
                            { key: 'bu1', code: 'BU1', semester: 1 },
                            { key: 'ch1', code: 'CH1', semester: 1 },
                            { key: 'ph1', code: 'PH1', semester: 1 },
                            { key: 'pp1', code: 'PP1', semester: 1 },
                        ],
                    },
                ],
                automatic_course_selection: {
                    courses: [
                        { key: 'inf2', code: 'INF2', semester: 1 },
                        { key: 'eth1', code: 'ETH1', semester: 1 },
                    ],
                    sections: [
                        {
                            key: 'planned',
                            items: [
                                { key: 'gw1', code: 'GW1', semester: 1 },
                                { key: 'bu1', code: 'BU1', semester: 1 },
                            ],
                        },
                        {
                            key: 'additional',
                            title: 'Zusätzliche Kurse',
                            items: [
                                { key: 'd2', code: 'D2', semester: 2 },
                                { key: 'e2', code: 'E2', semester: 2 },
                                { key: 'gw2', code: 'GW2', semester: 2 },
                                { key: 'm2-additional', code: 'M2', semester: 2 },
                                { key: 'pp1', code: 'PP1', semester: 1 },
                            ],
                        },
                    ],
                },
                manual_timetable: {
                    sections: [
                        {
                            key: 'manual',
                            items: [
                                { key: 'lpt', code: 'LPT', semester: 1 },
                                { key: 'drei', code: 'DREI', semester: 1 },
                            ],
                        },
                    ],
                },
            },
        }

        ctx.automaticTimetableCourseSelection = computed.automaticTimetableCourseSelection.call(ctx)
        ctx.automaticTimetableCourseSections = computed.automaticTimetableCourseSections.call(ctx)
        ctx.courseSections = computed.courseSections.call(ctx)
        ctx.manualTimetableSelection = computed.manualTimetableSelection.call(ctx)

        expect(ctx.courseSections[0].items.map(course => course.code)).toEqual(['D1', 'D10', 'E1', 'M2'])
        expect(ctx.courseSections[1].items.map(course => course.code)).toEqual([
            'BU1',
            'CH1',
            'D2',
            'E2',
            'ETH2',
            'GS1',
            'GW2',
            'M2',
            'PH1',
            'PP1',
        ])
        expect(computed.automaticTimetableSelectableCourses.call(ctx).map(course => course.code)).toEqual(['ETH1', 'INF2'])
        const automaticCourseSections = computed.automaticTimetableAllCourseSections.call(ctx)

        expect(automaticCourseSections[0].items.map(course => course.code)).toEqual(['BU1', 'GW1'])
        expect(automaticCourseSections.find(section => section.key === 'additional')?.items.map(course => course.code)).toEqual([
            'BU1',
            'CH1',
            'D2',
            'E2',
            'ETH2',
            'GS1',
            'GW2',
            'M2',
            'PH1',
            'PP1',
        ])
        expect(computed.manualTimetableCourseSections.call(ctx)[0].items.map(course => course.code)).toEqual(['DREI', 'LPT'])
    })

    it('shows selected courses and their offered courses in the automatic criteria review card', () => {
        const overviewPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
        )
        const source = readFileSync(overviewPath, 'utf8')

        expect(source).toContain('automaticTimetableCriteriaReviewVisible')
        expect(source).toContain('automaticTimetableCoursesLoadingVisible')
        expect(source).toContain(':initial-deselected-course-group-keys="automaticTimetableDeselectedCourseGroupKeys"')
        expect(source).toContain('Kurse werden geladen')
        expect(source).toContain('Die ausgewählten Kurse und angebotenen Kurse werden vorbereitet.')
        expect(source).toContain('Ausgewählte Kurse')
        expect(source).toContain('Angebotene Kurse')
        expect(source).toContain('automaticCourseBulkSelectionOptions')
        expect(source).toContain('automatic-course-review-card__course--offered-deselected')
        expect(source).toContain('background: rgba(254, 226, 226, 0.96) !important;')
        expect(source).toContain('color: #991b1b !important;')
        expect(source).toContain('selectAutomaticReviewCourse(course)')
        expect(source).toContain('selectedAutomaticReviewOfferedCourseItems')
        expect(source).toContain('continueAutomaticCourseReview')
        expect(source).not.toContain('removeAutomaticSelectedCourseItem(course)')
        expect(source).toContain('Hier können einzelne Kurse (z.B. Fernunterricht) abgewählt werden.')
        expect(source).toContain('Neustart')
        expect(source).toContain('Zurück')
        expect(source).toContain('Weiter')
        expect(source).toContain('showEvaluationSettings && !automaticTimetableCriteriaReviewVisible')
        expect(source).toContain('removeAutomaticTimetableCriteriaQuery()')

        const computed = (Overview as any).computed
        const methods = (Overview as any).methods
        const ctx: any = {
            ...methods,
            showEvaluationSettings: true,
            selectedAutomaticReviewCourseKey: '',
            automaticOfferedCourseSelectionOverrides: {},
            $route: {
                path: '/students-timetables/overview',
                query: {
                    automatic_timetable: 'criteria',
                    automatic_timetable_criteria: 'saturday_free',
                },
            },
            overview: {
                course_sections: [
                    {
                        key: 'proposed',
                        items: [
                            {
                                key: 'planned-fu',
                                code: 'D1',
                                name: 'Deutsch',
                                hours: 4,
                                course_groups: [
                                    { key: 'd1-1', course: 'D1', weekday: 1, hour: 1, teacher: 'AAA', recurrence_interval: 1 },
                                    { key: 'd1-2', course: 'D1', weekday: 1, hour: 2, teacher: 'AAA', recurrence_interval: 1 },
                                ],
                            },
                            {
                                key: 'planned-regular',
                                code: 'M1',
                                name: 'Mathematik',
                                hours: 4,
                                course_groups: [
                                    { key: 'm1-1', recurrence_interval: 1 },
                                    { key: 'm1-2', recurrence_interval: 1 },
                                    { key: 'm1-3', recurrence_interval: 1 },
                                    { key: 'm1-4', recurrence_interval: 1 },
                                ],
                            },
                        ],
                    },
                ],
            },
        }

        ctx.courseSections = computed.courseSections.call(ctx)
        ctx.automaticTimetableStep = computed.automaticTimetableStep.call(ctx)
        ctx.overviewDefaultSelectedCourseKeys = computed.overviewDefaultSelectedCourseKeys.call(ctx)
        ctx.automaticTimetableCourseKeys = computed.automaticTimetableCourseKeys.call(ctx)
        ctx.overviewSelectedCourseLimitItems = computed.overviewSelectedCourseLimitItems.call(ctx)
        Object.defineProperty(ctx, 'automaticSelectedCourseItems', {
            get() {
                return computed.automaticSelectedCourseItems.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'selectedAutomaticReviewCourse', {
            get() {
                return computed.selectedAutomaticReviewCourse.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'selectedAutomaticReviewOfferedCourseItems', {
            get() {
                return computed.selectedAutomaticReviewOfferedCourseItems.call(ctx)
            },
        })

        expect(computed.automaticTimetableCriteriaReviewVisible.call(ctx)).toBe(true)
        expect(computed.automaticSelectedCourseItems.call(ctx)).toEqual([
            expect.objectContaining({
                selectionKey: 'planned:D1',
                label: 'D1',
                meta: '4 Std.',
                distanceLearning: true,
            }),
            expect.objectContaining({
                selectionKey: 'planned:M1',
                label: 'M1',
                meta: '4 Std.',
                distanceLearning: false,
            }),
        ])

        methods.selectAutomaticReviewCourse.call(ctx, { selectionKey: 'planned:D1' })

        expect(ctx.selectedAutomaticReviewCourseKey).toBe('planned:D1')
        expect(computed.selectedAutomaticReviewCourse.call(ctx)).toEqual(expect.objectContaining({
            selectionKey: 'planned:D1',
            label: 'D1',
        }))
        expect(computed.selectedAutomaticReviewOfferedCourseItems.call(ctx)).toEqual([
            expect.objectContaining({
                code: 'D1',
                name: 'D1 - AAA',
                distanceLearning: true,
            }),
        ])

        methods.toggleAutomaticOfferedCourse.call(ctx, ctx.selectedAutomaticReviewOfferedCourseItems[0])

        expect(ctx.automaticOfferedCourseSelectionOverrides).toEqual({
            'planned:D1::d1-1': false,
        })
        expect(computed.automaticTimetableDeselectedCourseGroupKeys.call(ctx)).toEqual(['planned-fu|D1'])

        methods.applyAutomaticCourseBulkSelection.call(ctx, 'all')

        expect(ctx.automaticOfferedCourseSelectionOverrides).toEqual({})
    })

    it('uses a back action instead of a reset action', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/components/StudentTimetableEvaluationSettings.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const resultSource = source.slice(source.indexOf('v-else-if="currentStep === \'result\'" class="student-generated-timetable"'))

        expect(source).toContain('prepend-icon="mdi-arrow-left"')
        expect(source).toContain('@click="handleBack"')
        expect(source).toContain("this.$emit('close')")
        expect(source).toContain('Zurück')
        expect(source).toContain('student-evaluation-settings__close-button')
        expect(source).toContain('prepend-icon="mdi-arrow-left"')
        expect(source).toContain('student-result-course-panels')
        expect(source).toContain('v-if="currentStep === \'courses\'"')
        expect(source).toContain('v-else-if="currentStep === \'result\'" class="student-generated-timetable"')
        expect(source).toContain('student-selected-courses-card')
        expect(source).toContain('Ausgewählte Kurse')
        expect(source).toContain('resultSelectedCourseItems')
        expect(source).toContain('resultSelectedCourseSummary')
        expect(source).toContain(':close-label="`Kurs ${course.label} entfernen`"')
        expect(source).toContain(':closable="!resultMoreCoursesOrOptionsOpen && !resultSelectedCourseRemovalPending"')
        expect(source).toContain(':disabled="generatingTimetable || resultMoreCoursesOrOptionsOpen || resultSelectedCourseRemovalPending"')
        expect(source).toContain('@click:close.stop="removeResultSelectedCourseItem(course)"')
        expect(source).toContain('resultSelectedCourseRemovalPending')
        expect(source).toContain('student-selected-courses-card__course--pending-removal')
        expect(source).toContain('Auswahl geändert. Stundenplan neu erstellen?')
        expect(source).toContain('cancelResultSelectedCourseRemoval')
        expect(source).toContain('applyResultSelectedCourseRemoval')
        expect(source).toContain('resultSelectedCourseItemsAfterPendingRemoval')
        expect(source).toContain('student-generated-timetable__header')
        expect(resultSource).toContain('<span v-if="!generatingTimetable && !resultSelectedCourseRemovalPending" class="student-generated-timetable__toolbar">')
        expect(resultSource.indexOf('student-selected-courses-card')).toBeLessThan(resultSource.indexOf('student-generated-timetable__header'))
        expect(source).toContain('student-generated-timetable__success-strip')
        expect(source).toContain('Die Stundenpläne wurden erfolgreich erstellt.')
        expect(source).toContain('generatedTimetableResultCountItems')
        expect(source).toContain('generatedTimetableCounterLabel')
        expect(source).toContain('student-generated-conflicts')
        expect(source).toContain('generatedTimetableConflictSummaryItems')
        expect(source).toContain('generatedTimetableConflictTitle')
        expect(source).toContain('Überschneidungen')
        expect(resultSource.indexOf('student-generated-timetable__grid')).toBeLessThan(resultSource.indexOf('student-generated-conflicts'))
        expect(source).toContain('student-generated-timetable__number-input')
        expect(source).toContain('commitGeneratedTimetableNumber($event.target.value)')
        expect(source).toContain('prefix="Nr."')
        expect(source).toContain('student-generated-timetable__toolbar')
        expect(source).toContain('@click="toggleResultMoreCourses"')
        expect(source).toContain('@click="toggleResultOptions"')
        expect(source).toContain('@click="resetResultCalculationChanges"')
        expect(source).toContain('student-result-more-courses-card')
        expect(source).toContain('resultMoreCourseItems')
        expect(source).toContain('toggleResultMoreCourseOffers(course)')
        expect(source).toContain('student-result-offered-courses-card')
        expect(source).toContain('selectedResultMoreCourseOfferedCourseItems')
        expect(source).toContain('toggleResultMoreOfferedCourseItem(course)')
        expect(source).toContain('deselectSelectedResultMoreCourseOfferedCourses')
        expect(source).toContain('resultDraftQualityCriterionSelected(criterion)')
        expect(source).toContain('setResultDraftQualityCriterionSelected(criterion, $event)')
        expect(source).toContain('@click="applyResultMoreCourses"')
        expect(source).toContain('@click="applyResultOptions"')
        expect(source).not.toContain('student-evaluation-settings__summary-card')
        expect(source).not.toContain('student-evaluation-settings__summary-title')
        expect(source).toContain('SHOW_GENERATED_CRITERIA = false')
        expect(source).toContain('v-if="generatedCriteriaVisible"')
        expect(source).toContain('color="primary"')
        expect(source).toContain('color="success"')
        expect(source).toContain('readOnlySelectedCourseSections')
        expect(source).toContain('v-for="section in regularCourseSections"')
        expect(source).toContain('additionalCoursePanelVisible')
        expect(source).toContain('.student-evaluation-settings__course-section-card--additional')
        expect(source).toContain('background: #fed7aa')
        expect(source).toContain('border-color: rgba(234, 88, 12, 0.28)')
        expect(source).toContain('setAdditionalCourseSelected(course, $event)')
        expect(source).toContain(':disabled="additionalCourseSelectionLocked || generatingTimetable"')
        expect(source).toContain('Stundenplan erweitern')
        expect(source).toContain('createExtendedAutomaticTimetable')
        expect(source).toContain(':model-value="true"')
        expect(source).toContain('student-generated-criteria__check')
        expect(source).toContain('student-generated-criteria__status')
        expect(source).toContain("'student-generated-criteria__card--reached': generatedQualityCriterionSelected(criterion) && generatedQualityCriterionReached(criterion)")
        expect(source).toContain("generatedQualityCriterionReached(criterion) ? 'mdi-check-circle' : 'mdi-close-circle'")
        expect(source).toContain('<v-checkbox-btn')
        expect(source).toContain(':model-value="generatedQualityCriterionSelected(criterion)"')
        expect(source).toContain('@update:model-value="toggleGeneratedQualityCriterion(criterion)"')
        expect(source).toContain('disabled')
        expect(source).not.toContain('@click="resetChanges"')
    })

    it('initializes selected course keys from the url state', () => {
        const data = (StudentTimetableEvaluationSettings as any).data
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const ctx = {
            initialStep: 'courses',
            initialSelectedCourseKeys: ['course-2'],
            initialSelectedAdditionalCourseKeys: ['additional:course-3'],
            initialSelectedQualityCriterionKeys: ['saturday_free'],
            normalizedStep: methods.normalizedStep,
            normalizedCourseKeys: methods.normalizedCourseKeys,
            normalizedResultBackStep: methods.normalizedResultBackStep,
        }

        expect(data.call(ctx).selectedCourseKeys).toEqual(['course-2'])
        expect(data.call(ctx).selectedAdditionalCourseKeys).toEqual(['additional:course-3'])
        expect(data.call(ctx).selectedQualityCriterionKeys).toEqual(['saturday_free'])
        expect(data.call(ctx).resultBackStep).toBe('courses')
    })

    it('moves from criteria to the result while keeping criteria as the back target', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const proposedCourses = [
            { key: 'course-1', code: 'M1', name: 'Mathematik', semester: 1, hours: 3 },
            { key: 'course-2', code: 'D1', name: 'Deutsch', semester: 1, hours: 2 },
        ]
        const ctx = {
            currentStep: 'criteria',
            proposedCourses,
            selectedCourseKeys: [],
            saveSettings: async () => true,
            ensureDefaultQualityCriterionSelection: () => undefined,
            resetGeneratedTimetableSelection: () => undefined,
            courseSelectionKey: methods.courseSelectionKey,
            courseSelected: methods.courseSelected,
            moveToStep: methods.moveToStep,
            normalizedStep: methods.normalizedStep,
            normalizedResultBackStep: methods.normalizedResultBackStep,
            selectAllProposedCourses: methods.selectAllProposedCourses,
            emitCourseSelectionChange: methods.emitCourseSelectionChange,
            ensureAutomaticTimetableForResultStep: () => undefined,
            resultBackStep: 'criteria',
            get selectedCourses() {
                return proposedCourses.filter(course => methods.courseSelected.call(ctx, course))
            },
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        await methods.continueToNextStep.call(ctx)

        expect(ctx.currentStep).toBe('result')
        expect(ctx.resultBackStep).toBe('criteria')
        expect(ctx.selectedCourseKeys).toEqual(['planned:M1', 'planned:D1'])

        methods.setCourseSelected.call(ctx, proposedCourses[1], false)

        expect(ctx.selectedCourseKeys).toEqual(['planned:M1', 'planned:D1'])

        expect(emitted).toEqual([
            {
                event: 'course-selection-change',
                payload: ['planned:M1', 'planned:D1'],
            },
            {
                event: 'step-change',
                payload: 'result',
            },
        ])
    })

    it('creates an automatic timetable before showing the result step', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const ctx = {
            currentStep: 'courses',
            selectedCourseKeys: ['course-1'],
            selectedCourses: [{ key: 'course-1' }],
            resultBackStep: 'criteria',
            resetGeneratedTimetableSelection: () => undefined,
            createAutomaticTimetable: async () => {
                ctx.currentStep = 'result'
            },
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        await methods.continueToNextStep.call(ctx)

        expect(ctx.currentStep).toBe('result')
        expect(ctx.resultBackStep).toBe('courses')
        expect(emitted).toEqual([])

        await methods.continueToNextStep.call(ctx)

        expect(emitted).toEqual([
            {
                event: 'courses-selected',
                payload: [{ key: 'course-1' }],
            },
        ])
    })

    it('returns from result to the step that opened it', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const ctx = {
            currentStep: 'result',
            resultBackStep: 'criteria',
            selectedCourseKeys: [],
            normalizedStep: methods.normalizedStep,
            normalizedResultBackStep: methods.normalizedResultBackStep,
            moveToStep: methods.moveToStep,
            selectAllProposedCourses: () => undefined,
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        methods.handleBack.call(ctx)

        expect(ctx.currentStep).toBe('criteria')
        expect(emitted).toEqual([
            {
                event: 'step-change',
                payload: 'criteria',
            },
        ])

        ctx.currentStep = 'result'
        ctx.resultBackStep = 'courses'

        methods.handleBack.call(ctx)

        expect(ctx.currentStep).toBe('courses')
        expect(emitted.at(-1)).toEqual({
            event: 'step-change',
            payload: 'courses',
        })
    })

    it('shows selected regular courses as read-only rows in the result step', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const selectedCourse = { key: 'course-1', code: 'M1' }
        const unselectedCourse = { key: 'course-2', code: 'D1' }
        const additionalCourse = { key: 'course-3', code: 'E2' }
        const ctx = {
            selectedCourseKeys: ['course-1'],
            courseSections: [
                {
                    key: 'missing',
                    title: 'Negative Kurse',
                    items: [selectedCourse, unselectedCourse],
                },
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    items: [additionalCourse],
                },
            ],
            courseSelectionKey: methods.courseSelectionKey,
            courseSelectedByDefault: methods.courseSelectedByDefault,
            courseSelected: methods.courseSelected,
            get allCoursesSelectedByDefault() {
                return computed.allCoursesSelectedByDefault.call(ctx)
            },
            get displayedCourseSections() {
                return computed.displayedCourseSections.call(ctx)
            },
            get regularCourseSections() {
                return computed.regularCourseSections.call(ctx)
            },
        }

        expect(computed.readOnlySelectedCourseSections.call(ctx)).toEqual([
            {
                key: 'missing',
                title: 'Negative Kurse',
                items: [selectedCourse],
            },
        ])
    })

    it('summarizes selected courses above the generated timetable result', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            selectedCourseKeys: ['course-1', 'course-2'],
            selectedAdditionalCourseKeys: ['additional:E2'],
            proposedCourses: [
                { key: 'course-1', code: 'D1', hours: 3 },
                { key: 'course-2', code: 'M1', hours: 4 },
                { key: 'course-3', code: 'E1', hours: 2 },
            ],
            courseSections: [
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    items: [{ key: 'course-4', code: 'E2', hours: 2 }],
                },
            ],
            allCoursesSelectedByDefault: false,
            courseSelectionKey: methods.courseSelectionKey,
            courseSelected: methods.courseSelected,
            courseSelectedFromKeys: methods.courseSelectedFromKeys,
            selectionKeyMatchesCourse: methods.selectionKeyMatchesCourse,
            courseSelectionGroup: methods.courseSelectionGroup,
            normalizedCourseSelectionGroupKey: methods.normalizedCourseSelectionGroupKey,
            normalizedCourseCode: methods.normalizedCourseCode,
            normalizedCourseKeys: methods.normalizedCourseKeys,
            backendCourseSelectionKey: methods.backendCourseSelectionKey,
            sortedCourseItems: methods.sortedCourseItems,
            compareCourseItems: methods.compareCourseItems,
            courseSortLabel: methods.courseSortLabel,
            courseHoursNumber: methods.courseHoursNumber,
            resultSelectedCourseItem: methods.resultSelectedCourseItem,
            selectedCoursesForSummary: methods.selectedCoursesForSummary,
            selectedAdditionalCoursesForSummary: methods.selectedAdditionalCoursesForSummary,
            courseItemsSummary: methods.courseItemsSummary,
            uniqueCourseItems: methods.uniqueCourseItems,
            get displayedCourseSections() {
                return computed.displayedCourseSections.call(ctx)
            },
            get additionalCourseSections() {
                return computed.additionalCourseSections.call(ctx)
            },
            get additionalCourses() {
                return computed.additionalCourses.call(ctx)
            },
            get resultSelectedCourses() {
                return computed.resultSelectedCourses.call(ctx)
            },
        }

        expect(computed.resultSelectedCourseItems.call(ctx)).toEqual([
            { selectionKey: 'planned:D1', label: 'D1', meta: '3 Std.' },
            { selectionKey: 'planned:M1', label: 'M1', meta: '4 Std.' },
            { selectionKey: 'additional:E2', label: 'E2', meta: '2 Std.' },
        ])
        expect(computed.resultSelectedCourseSummary.call(ctx)).toEqual({
            count: 3,
            hours: 9,
            countLabel: '3 Kurse',
            hoursLabel: '9 Std.',
        })
    })

    it('removes selected result courses only after confirmation', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const createAutomaticTimetable = vi.fn(async () => undefined)
        const ctx = {
            selectedCourseKeys: ['course-1', 'course-2'],
            selectedAdditionalCourseKeys: ['additional:E2'],
            pendingRemovedSelectedCourseKeys: [],
            proposedCourses: [
                { key: 'course-1', code: 'D1', hours: 3 },
                { key: 'course-2', code: 'M1', hours: 4 },
            ],
            courseSections: [
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    items: [{ key: 'course-3', code: 'E2', hours: 2 }],
                },
            ],
            allCoursesSelectedByDefault: false,
            generatingTimetable: false,
            selectedTimetableType: 'green',
            selectedTimetableNumber: 3,
            courseSelectionKey: methods.courseSelectionKey,
            courseSelected: methods.courseSelected,
            courseSelectedFromKeys: methods.courseSelectedFromKeys,
            selectionKeyMatchesCourse: methods.selectionKeyMatchesCourse,
            courseSelectionGroup: methods.courseSelectionGroup,
            normalizedCourseSelectionGroupKey: methods.normalizedCourseSelectionGroupKey,
            normalizedCourseCode: methods.normalizedCourseCode,
            normalizedCourseKeys: methods.normalizedCourseKeys,
            backendCourseSelectionKey: methods.backendCourseSelectionKey,
            sortedCourseItems: methods.sortedCourseItems,
            compareCourseItems: methods.compareCourseItems,
            courseSortLabel: methods.courseSortLabel,
            courseHoursNumber: methods.courseHoursNumber,
            resultSelectedCourseItem: methods.resultSelectedCourseItem,
            selectedCoursesForSummary: methods.selectedCoursesForSummary,
            selectedAdditionalCoursesForSummary: methods.selectedAdditionalCoursesForSummary,
            uniqueCourseItems: methods.uniqueCourseItems,
            resetGeneratedTimetableSelection: methods.resetGeneratedTimetableSelection,
            emitAdditionalCourseSelectionChange: methods.emitAdditionalCourseSelectionChange,
            createAutomaticTimetable,
            $emit: vi.fn(),
            get displayedCourseSections() {
                return computed.displayedCourseSections.call(ctx)
            },
            get additionalCourseSections() {
                return computed.additionalCourseSections.call(ctx)
            },
            get additionalCourses() {
                return computed.additionalCourses.call(ctx)
            },
            get resultSelectedCourses() {
                return computed.resultSelectedCourses.call(ctx)
            },
            get resultSelectedCourseItems() {
                return computed.resultSelectedCourseItems.call(ctx)
            },
            get resultSelectedCourseItemsAfterPendingRemoval() {
                return computed.resultSelectedCourseItemsAfterPendingRemoval.call(ctx)
            },
            get resultSelectedCourseRemovalPending() {
                return computed.resultSelectedCourseRemovalPending.call(ctx)
            },
        }

        methods.removeResultSelectedCourseItem.call(ctx, { selectionKey: 'planned:D1' })

        expect(ctx.pendingRemovedSelectedCourseKeys).toEqual(['planned:D1'])
        expect(computed.resultSelectedCourseItems.call(ctx).map(course => course.selectionKey)).toEqual(['planned:D1', 'planned:M1', 'additional:E2'])
        expect(computed.resultSelectedCourseItemsAfterPendingRemoval.call(ctx).map(course => course.selectionKey)).toEqual(['planned:M1', 'additional:E2'])
        expect(methods.resultSelectedCoursePendingRemoval.call(ctx, { selectionKey: 'planned:D1' })).toBe(true)
        expect(methods.resultSelectedCoursePendingRemoval.call(ctx, { selectionKey: 'planned:M1' })).toBe(false)

        methods.removeResultSelectedCourseItem.call(ctx, { selectionKey: 'planned:M1' })

        expect(ctx.pendingRemovedSelectedCourseKeys).toEqual(['planned:D1'])

        methods.cancelResultSelectedCourseRemoval.call(ctx)

        expect(ctx.pendingRemovedSelectedCourseKeys).toEqual([])
        expect(computed.resultSelectedCourseItems.call(ctx).map(course => course.selectionKey)).toEqual(['planned:D1', 'planned:M1', 'additional:E2'])
        expect(createAutomaticTimetable).not.toHaveBeenCalled()

        methods.removeResultSelectedCourseItem.call(ctx, { selectionKey: 'additional:E2' })
        await methods.applyResultSelectedCourseRemoval.call(ctx)

        expect(ctx.selectedCourseKeys).toEqual(['planned:D1', 'planned:M1'])
        expect(ctx.selectedAdditionalCourseKeys).toEqual([])
        expect(ctx.pendingRemovedSelectedCourseKeys).toEqual([])
        expect(ctx.selectedTimetableType).toBe(null)
        expect(ctx.selectedTimetableNumber).toBe(1)
        expect(createAutomaticTimetable).toHaveBeenCalledTimes(1)
    })

    it('applies result additional courses only after confirming the draft', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const additionalCourse = {
            key: 'course-3',
            code: 'E2',
            hours: 2,
            course_groups: [
                { class_name: 'E2-A', course: 'E2', hour: 5, teacher: 'AAA', time_from: '10:00:00', time_until: '10:50:00', weekday: 1 },
                { class_name: 'E2-B', course: 'E2', hour: 6, teacher: 'BBB', time_from: '11:00:00', time_until: '11:50:00', weekday: 2 },
            ],
        }
        const createAutomaticTimetable = vi.fn(async () => undefined)
        const ctx: any = {
            generatedTimetable: { slots: {} },
            generatingTimetable: false,
            resultMoreCourseAvailabilityLoading: false,
            resultMoreCoursesVisible: false,
            resultOptionsVisible: false,
            selectedResultMoreCourseKey: '',
            resultDraftAdditionalCourseKeys: [],
            resultMoreOfferedCourseSelectionOverrides: {},
            selectedAdditionalCourseKeys: [],
            deselectedCourseGroupKeys: ['planned|kept'],
            additionalCourses: [additionalCourse],
            schoolHours: [],
            selectedTimetableType: 'green',
            selectedTimetableNumber: 2,
            additionalCourseSelectionChangedAfterTimetable: true,
            additionalCourseSelectionLocked: true,
            courseSelectionKey: methods.courseSelectionKey,
            backendCourseSelectionKey: methods.backendCourseSelectionKey,
            courseSelectionGroup: methods.courseSelectionGroup,
            normalizedCourseSelectionGroupKey: methods.normalizedCourseSelectionGroupKey,
            courseHoursNumber: methods.courseHoursNumber,
            resultSelectedCourseItem: methods.resultSelectedCourseItem,
            resultMoreCourseItem: methods.resultMoreCourseItem,
            resultMoreCourseIsDistanceLearning: methods.resultMoreCourseIsDistanceLearning,
            resultMoreCourseGroupsForSelectedCourse: methods.resultMoreCourseGroupsForSelectedCourse,
            resultMoreOfferedCourseIsDistanceLearning: methods.resultMoreOfferedCourseIsDistanceLearning,
            resultMoreCourseGroupsScheduledWeeklyLoad: methods.resultMoreCourseGroupsScheduledWeeklyLoad,
            resultMoreCourseGroupIsOccasional: methods.resultMoreCourseGroupIsOccasional,
            resultMoreOfferedCourseSelectionOverridesFromDeselectedKeys: methods.resultMoreOfferedCourseSelectionOverridesFromDeselectedKeys,
            resultMoreOfferedCourseItemsForSelectedCourse: methods.resultMoreOfferedCourseItemsForSelectedCourse,
            resultMoreOfferedCourseGroupItem: methods.resultMoreOfferedCourseGroupItem,
            resultMoreOfferedCourseGroupSelectionLabel: methods.resultMoreOfferedCourseGroupSelectionLabel,
            resultMoreCourseGroupKey: methods.resultMoreCourseGroupKey,
            resultMoreOfferedCourseName: methods.resultMoreOfferedCourseName,
            resultMoreCourseGroupScheduleLabel: methods.resultMoreCourseGroupScheduleLabel,
            resultMoreCourseGroupWeekdayLabel: methods.resultMoreCourseGroupWeekdayLabel,
            resultMoreCourseGroupTimeRange: methods.resultMoreCourseGroupTimeRange,
            resultMoreCourseGroupWeekMarker: methods.resultMoreCourseGroupWeekMarker,
            resultMoreOfferedCourseIdentityKey: methods.resultMoreOfferedCourseIdentityKey,
            uniqueResultMoreOfferedCourseItems: methods.uniqueResultMoreOfferedCourseItems,
            resultMoreMergedLabelList: methods.resultMoreMergedLabelList,
            compareResultMoreOfferedCourseItems: methods.compareResultMoreOfferedCourseItems,
            resultMoreOfferedCourseSelected: methods.resultMoreOfferedCourseSelected,
            resultMoreOfferedCourseItemsAnySelected: methods.resultMoreOfferedCourseItemsAnySelected,
            resultDraftAdditionalCourseSelected: methods.resultDraftAdditionalCourseSelected,
            resultMoreDeselectedOfferedCourseGroupKeys: methods.resultMoreDeselectedOfferedCourseGroupKeys,
            resultMoreAllOfferedCourseBackendKeys: methods.resultMoreAllOfferedCourseBackendKeys,
            resultMoreDeselectedCourseGroupKeysForApply: methods.resultMoreDeselectedCourseGroupKeysForApply,
            currentResultMoreDeselectedOfferedCourseGroupKeys: methods.currentResultMoreDeselectedOfferedCourseGroupKeys,
            resultMoreCourseDisabled: () => false,
            ensureResultMoreCourseAvailability: vi.fn(() => Promise.resolve([])),
            generatedTimetableConfiguredSchoolHour: methods.generatedTimetableConfiguredSchoolHour,
            courseGroupWeekInterval: methods.courseGroupWeekInterval,
            courseGroupDates: methods.courseGroupDates,
            weekIntervalFromDates: methods.weekIntervalFromDates,
            dateFromIsoValue: methods.dateFromIsoValue,
            formatTimeValue: methods.formatTimeValue,
            normalizedCourseCode: methods.normalizedCourseCode,
            normalizedCourseKeys: methods.normalizedCourseKeys,
            normalizedCourseKeyListsEqual: methods.normalizedCourseKeyListsEqual,
            setResultDraftAdditionalCourseSelected: methods.setResultDraftAdditionalCourseSelected,
            resetGeneratedTimetableSelection: methods.resetGeneratedTimetableSelection,
            emitAdditionalCourseSelectionChange: methods.emitAdditionalCourseSelectionChange,
            createAutomaticTimetable,
            $emit: vi.fn(),
            get resultMoreCourseItems() {
                return computed.resultMoreCourseItems.call(ctx)
            },
            get selectedResultMoreCourseItem() {
                return computed.selectedResultMoreCourseItem.call(ctx)
            },
            get selectedResultMoreCourseOfferedCourseItems() {
                return computed.selectedResultMoreCourseOfferedCourseItems.call(ctx)
            },
            get resultMoreCoursesUnavailable() {
                return computed.resultMoreCoursesUnavailable.call(ctx)
            },
            get resultAdditionalCoursesChanged() {
                return computed.resultAdditionalCoursesChanged.call(ctx)
            },
        }

        methods.toggleResultMoreCourses.call(ctx)
        methods.toggleResultMoreCourseOffers.call(ctx, ctx.resultMoreCourseItems[0])
        methods.toggleResultMoreOfferedCourseItem.call(ctx, ctx.selectedResultMoreCourseOfferedCourseItems[0])

        expect(ctx.resultMoreCoursesVisible).toBe(true)
        expect(ctx.selectedResultMoreCourseKey).toBe('additional:E2')
        expect(ctx.resultDraftAdditionalCourseKeys).toEqual(['additional:E2'])
        expect(ctx.resultMoreOfferedCourseSelectionOverrides).toEqual({
            'additional:E2::E2|1|5|AAA': false,
        })
        expect(ctx.selectedAdditionalCourseKeys).toEqual([])
        expect(ctx.resultAdditionalCoursesChanged).toBe(true)

        await methods.applyResultMoreCourses.call(ctx)

        expect(ctx.selectedAdditionalCourseKeys).toEqual(['additional:E2'])
        expect(ctx.deselectedCourseGroupKeys).toEqual(['planned|kept', 'course-3|E2-A'])
        expect(ctx.resultMoreCoursesVisible).toBe(false)
        expect(ctx.selectedResultMoreCourseKey).toBe('')
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(false)
        expect(ctx.additionalCourseSelectionLocked).toBe(false)
        expect(ctx.selectedTimetableType).toBe(null)
        expect(ctx.selectedTimetableNumber).toBe(1)
        expect(createAutomaticTimetable).toHaveBeenCalledTimes(1)
    })

    it('uses candidate courses to resolve offered course groups when selected course has no course_groups', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const matchedCourseGroup = {
            key: 'course-group-bu1',
            code: 'BU',
            class_name: 'BU1',
            weekday: 2,
            hour: 4,
        }
        const nonMatchingCourseGroup = {
            key: 'course-group-lu1',
            code: 'L',
            class_name: 'L1',
            weekday: 1,
            hour: 3,
        }
        const selectedCourse = {
            key: 'course-selected',
            code: 'BU1',
            label: 'BU1',
        }

        const ctx: any = {
            resultMoreCourseGroupCandidates: [
                {
                    code: 'BU/BU1',
                    ttCodes: ['BU1'],
                    course_groups: [matchedCourseGroup],
                },
                {
                    code: 'L',
                    course_groups: [nonMatchingCourseGroup],
                },
            ],
            normalizedCourseCode: methods.normalizedCourseCode,
            courseCodeAliases: methods.courseCodeAliases,
            resultMoreCourseAliases: methods.resultMoreCourseAliases,
            resultMoreCourseCoursesMatch: methods.resultMoreCourseCoursesMatch,
            resultMoreCourseGroupMatchesSelectedCourse: methods.resultMoreCourseGroupMatchesSelectedCourse,
            resultMoreCourseGroupCodes: methods.resultMoreCourseGroupCodes,
        }

        const courseGroups = methods.resultMoreCourseGroupsForSelectedCourse.call(ctx, selectedCourse)

        expect(courseGroups).toEqual([matchedCourseGroup])
    })

    it('applies result options only after confirming the draft', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const criterion = { key: 'saturday_free', label: 'Samstag kein Unterricht' }
        const emitted: Array<{ event: string, payload: unknown }> = []
        const createAutomaticTimetable = vi.fn(async () => undefined)
        const ctx: any = {
            generatedTimetable: { slots: {} },
            generatingTimetable: false,
            resultMoreCoursesVisible: true,
            resultOptionsVisible: false,
            selectedResultMoreCourseKey: 'course-3',
            resultMoreOfferedCourseSelectionOverrides: {},
            resultMoreCourseItems: [],
            resultDraftQualityCriterionKeys: [],
            selectedQualityCriterionKeys: [],
            activeGeneratedQualityCriteria: [criterion],
            deselectedCourseGroupKeys: [],
            selectedTimetableType: 'green',
            selectedTimetableNumber: 3,
            normalizedCourseKeys: methods.normalizedCourseKeys,
            normalizedCourseKeyListsEqual: methods.normalizedCourseKeyListsEqual,
            resultMoreOfferedCourseSelectionOverridesFromDeselectedKeys: methods.resultMoreOfferedCourseSelectionOverridesFromDeselectedKeys,
            currentResultMoreDeselectedOfferedCourseGroupKeys: () => [],
            resetGeneratedTimetableSelection: methods.resetGeneratedTimetableSelection,
            emitQualityCriteriaSelectionChange: methods.emitQualityCriteriaSelectionChange,
            createAutomaticTimetable,
            $emit(event: string, payload: unknown) {
                emitted.push({ event, payload })
            },
            get resultOptionsUnavailable() {
                return computed.resultOptionsUnavailable.call(ctx)
            },
            get resultOptionsChanged() {
                return computed.resultOptionsChanged.call(ctx)
            },
        }

        methods.toggleResultOptions.call(ctx)
        methods.setResultDraftQualityCriterionSelected.call(ctx, criterion, true)

        expect(ctx.resultOptionsVisible).toBe(true)
        expect(ctx.resultMoreCoursesVisible).toBe(false)
        expect(ctx.resultDraftQualityCriterionKeys).toEqual(['saturday_free'])
        expect(ctx.selectedQualityCriterionKeys).toEqual([])
        expect(ctx.resultOptionsChanged).toBe(true)

        await methods.applyResultOptions.call(ctx)

        expect(ctx.selectedQualityCriterionKeys).toEqual(['saturday_free'])
        expect(ctx.resultOptionsVisible).toBe(false)
        expect(ctx.selectedTimetableType).toBe(null)
        expect(ctx.selectedTimetableNumber).toBe(1)
        expect(emitted).toEqual([
            {
                event: 'quality-criteria-selection-change',
                payload: ['saturday_free'],
            },
        ])
        expect(createAutomaticTimetable).toHaveBeenCalledTimes(1)
    })

    it('builds automatic timetable payloads with selected result options', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const ctx: any = {
            deselectedCourseGroupKeys: ['planned|D1-A'],
            selectedQualityCriterionKeys: ['saturday_free'],
            selectedTimetableType: 'green',
            selectedTimetableNumber: 3,
            selectedVisibleAdditionalCourseKeys: ['additional-key'],
            selectionOverride: { language: 'L' },
            selectedBackendCourseKeys: () => ['planned-key'],
        }

        const payload = methods.automaticTimetablePayload.call(ctx)

        expect(payload).toMatchObject({
            selected_course_keys: ['planned-key'],
            deselected_course_group_keys: ['planned|D1-A'],
            selected_additional_course_keys: ['additional-key'],
            selected_additional_courses_required: true,
            selected_quality_criterion_keys: ['saturday_free'],
            selected_timetable_type: 'green',
            selected_timetable_number: 3,
            selection: { language: 'L' },
        })
    })

    it('builds public more course availability payloads with candidate courses', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const course = {
            key: 'course-key',
            code: 'E2',
            courseGroup: 'additional',
        }
        const ctx: any = {
            deselectedCourseGroupKeys: [],
            selectedQualityCriterionKeys: ['free_days'],
            selectedTimetableType: 'green',
            selectedTimetableNumber: 3,
            selectedVisibleAdditionalCourseKeys: [],
            selectionOverride: { language: 'L' },
            automaticTimetablePayload: methods.automaticTimetablePayload,
            resultMoreCourseAvailabilityCandidatePayload: methods.resultMoreCourseAvailabilityCandidatePayload,
            resultMoreCourseAvailabilityKey: methods.resultMoreCourseAvailabilityKey,
            selectedBackendCourseKeys: () => ['planned-key'],
            courseSelectionKey: methods.courseSelectionKey,
            backendCourseSelectionKey: methods.backendCourseSelectionKey,
            courseSelectionGroup: methods.courseSelectionGroup,
            normalizedCourseSelectionGroupKey: methods.normalizedCourseSelectionGroupKey,
            normalizedCourseCode: methods.normalizedCourseCode,
            displayedCourseSections: [],
            additionalCourses: [course],
        }

        const payload = methods.automaticTimetableAvailabilityPayload.call(ctx, [course])

        expect(payload).toMatchObject({
            availability_only: true,
            selected_course_keys: ['planned-key'],
            selected_quality_criterion_keys: ['free_days'],
            selected_timetable_type: 'full_green',
            selected_timetable_number: 1,
            candidate_courses: [
                {
                    availability_key: 'additional:E2',
                    course_key: 'course-key',
                    course_group: 'additional',
                },
            ],
        })
    })

    it('resets result calculation changes to the initial admin-style state', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const emitted: Array<{ event: string, payload: unknown }> = []
        const createAutomaticTimetable = vi.fn(async () => undefined)
        const proposedCourses = [
            { key: 'course-1', code: 'D1' },
            { key: 'course-2', code: 'M1' },
        ]
        const ctx: any = {
            generatedTimetable: { slots: {} },
            generatingTimetable: false,
            initialSelectedCourseKeys: [],
            initialSelectedQualityCriterionKeys: [],
            proposedCourses,
            selectedCourseKeys: ['course-1'],
            selectedAdditionalCourseKeys: ['course-3'],
            selectedQualityCriterionKeys: ['saturday_free'],
            pendingRemovedSelectedCourseKeys: ['course-1'],
            resultMoreCoursesVisible: true,
            resultOptionsVisible: true,
            resultDraftAdditionalCourseKeys: ['course-3'],
            resultDraftQualityCriterionKeys: ['saturday_free'],
            selectedTimetableType: 'green',
            selectedTimetableNumber: 4,
            additionalCourseSelectionChangedAfterTimetable: true,
            additionalCourseSelectionLocked: true,
            courseSelectionKey: methods.courseSelectionKey,
            normalizedCourseKeys: methods.normalizedCourseKeys,
            normalizedCourseKeyListsEqual: methods.normalizedCourseKeyListsEqual,
            resetGeneratedTimetableSelection: methods.resetGeneratedTimetableSelection,
            emitAdditionalCourseSelectionChange: methods.emitAdditionalCourseSelectionChange,
            emitCourseSelectionChange: methods.emitCourseSelectionChange,
            emitQualityCriteriaSelectionChange: methods.emitQualityCriteriaSelectionChange,
            createAutomaticTimetable,
            $emit(event: string, payload: unknown) {
                emitted.push({ event, payload })
            },
            get resultInitialSelectedCourseKeys() {
                return computed.resultInitialSelectedCourseKeys.call(ctx)
            },
            get resultCalculationResetAvailable() {
                return computed.resultCalculationResetAvailable.call(ctx)
            },
        }

        expect(ctx.resultCalculationResetAvailable).toBe(true)

        await methods.resetResultCalculationChanges.call(ctx)

        expect(ctx.selectedCourseKeys).toEqual(['planned:D1', 'planned:M1'])
        expect(ctx.selectedAdditionalCourseKeys).toEqual([])
        expect(ctx.selectedQualityCriterionKeys).toEqual([])
        expect(ctx.pendingRemovedSelectedCourseKeys).toEqual([])
        expect(ctx.resultMoreCoursesVisible).toBe(false)
        expect(ctx.resultOptionsVisible).toBe(false)
        expect(ctx.resultDraftAdditionalCourseKeys).toEqual([])
        expect(ctx.resultDraftQualityCriterionKeys).toEqual([])
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(false)
        expect(ctx.additionalCourseSelectionLocked).toBe(false)
        expect(ctx.selectedTimetableType).toBe(null)
        expect(ctx.selectedTimetableNumber).toBe(1)
        expect(emitted).toEqual([
            {
                event: 'additional-course-selection-change',
                payload: [],
            },
            {
                event: 'course-selection-change',
                payload: ['planned:D1', 'planned:M1'],
            },
            {
                event: 'quality-criteria-selection-change',
                payload: [],
            },
        ])
        expect(createAutomaticTimetable).toHaveBeenCalledTimes(1)
    })

    it('keeps result course panels hidden while retaining additional course data', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            ...methods,
            currentStep: 'result',
            generatedTimetable: { slots: {} },
            courseSections: [
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    items: [{ key: 'course-3', code: 'E2' }],
                },
            ],
            get displayedCourseSections() {
                return computed.displayedCourseSections.call(ctx)
            },
            get additionalCourseSections() {
                return computed.additionalCourseSections.call(ctx)
            },
            get additionalCourses() {
                return computed.additionalCourses.call(ctx)
            },
            get hiddenGeneratedAdditionalCourseKeySet() {
                return new Set()
            },
            get readOnlySelectedCourseSections() {
                return []
            },
            get additionalCoursePanelVisible() {
                return computed.additionalCoursePanelVisible.call(ctx)
            },
        }

        expect(computed.additionalCoursePanelVisible.call(ctx)).toBe(true)
        expect(computed.resultCoursePanelsVisible.call(ctx)).toBe(false)
        expect(computed.additionalCourses.call(ctx)).toEqual([{ key: 'course-3', code: 'E2' }])
    })

    it('orders result additional courses ascending', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            ...methods,
            currentStep: 'result',
            generatedTimetable: { slots: {} },
            courseSections: [
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    items: [
                        { key: 'm10', code: 'M10', semester: 1 },
                        { key: 'd1', code: 'D1', semester: 1 },
                        { key: 'm2', code: 'M2', semester: 1 },
                        { key: 'e1', code: 'E1', semester: 2 },
                    ],
                },
            ],
            get displayedCourseSections() {
                return computed.displayedCourseSections.call(ctx)
            },
            get additionalCourseSections() {
                return computed.additionalCourseSections.call(ctx)
            },
            get additionalCourses() {
                return computed.additionalCourses.call(ctx)
            },
            get hiddenGeneratedAdditionalCourseKeySet() {
                return new Set()
            },
        }

        expect(computed.additionalCourses.call(ctx).map(course => course.code)).toEqual(['D1', 'E1', 'M2', 'M10'])
    })

    it('keeps additional courses unchecked when the generated timetable is first shown', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const course = { key: 'course-3', code: 'E2' }
        const ctx = {
            ...methods,
            currentStep: 'result',
            generatedTimetable: { slots: {} },
            selectedAdditionalCourseKeys: [],
            courseSections: [
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    items: [course],
                },
            ],
            courseSelectionKey: methods.courseSelectionKey,
            additionalCourseSelected: methods.additionalCourseSelected,
            get displayedCourseSections() {
                return computed.displayedCourseSections.call(ctx)
            },
            get additionalCourseSections() {
                return computed.additionalCourseSections.call(ctx)
            },
            get additionalCourses() {
                return computed.additionalCourses.call(ctx)
            },
            get hiddenGeneratedAdditionalCourseKeySet() {
                return new Set()
            },
        }

        expect(computed.additionalCoursePanelVisible.call(ctx)).toBe(true)
        expect(methods.additionalCourseSelected.call(ctx, course)).toBe(false)
    })

    it('hides the generated timetable while additional course selection is pending and restores it on back', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const moveToStep = vi.fn()
        const ctx = {
            currentStep: 'result',
            generatedTimetable: { slots: {} },
            selectedAdditionalCourseKeys: [],
            additionalCourseSelectionChangedAfterTimetable: false,
            courseSelectionKey: methods.courseSelectionKey,
            commitAdditionalCourseSelectionChange: methods.commitAdditionalCourseSelectionChange,
            resetAdditionalCourseSelection: methods.resetAdditionalCourseSelection,
            emitAdditionalCourseSelectionChange: methods.emitAdditionalCourseSelectionChange,
            moveToStep,
            $emit: vi.fn(),
        }

        methods.setAdditionalCourseSelected.call(ctx, { key: 'course-3' }, true)

        expect(ctx.selectedAdditionalCourseKeys).toEqual(['course-3'])
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(true)

        methods.handleBack.call(ctx)

        expect(ctx.selectedAdditionalCourseKeys).toEqual([])
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(false)
        expect(ctx.generatedTimetable).toEqual({ slots: {} })
        expect(moveToStep).not.toHaveBeenCalled()
    })

    it('locks additional course checkboxes after extending and unlocks them on back', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const moveToStep = vi.fn()
        const ctx = {
            currentStep: 'result',
            generatedTimetable: { slots: {} },
            selectedAdditionalCourseKeys: ['course-3'],
            additionalCourseSelectionChangedAfterTimetable: true,
            additionalCourseSelectionLocked: false,
            selectedTimetableType: 'green',
            selectedTimetableNumber: 3,
            courseSelectionKey: methods.courseSelectionKey,
            commitAdditionalCourseSelectionChange: methods.commitAdditionalCourseSelectionChange,
            emitAdditionalCourseSelectionChange: methods.emitAdditionalCourseSelectionChange,
            resetGeneratedTimetableSelection: methods.resetGeneratedTimetableSelection,
            moveToStep,
            $emit: vi.fn(),
            async createAutomaticTimetable() {
                this.generatedTimetable = { slots: { extended: true } }
                this.additionalCourseSelectionChangedAfterTimetable = false
            },
        }

        await methods.createExtendedAutomaticTimetable.call(ctx)

        expect(ctx.additionalCourseSelectionLocked).toBe(true)
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(false)
        expect(ctx.selectedAdditionalCourseKeys).toEqual(['course-3'])
        expect(ctx.selectedTimetableType).toBe(null)
        expect(ctx.selectedTimetableNumber).toBe(1)

        methods.setAdditionalCourseSelected.call(ctx, { key: 'course-3' }, false)

        expect(ctx.selectedAdditionalCourseKeys).toEqual(['course-3'])

        methods.handleBack.call(ctx)

        expect(ctx.additionalCourseSelectionLocked).toBe(false)
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(true)
        expect(ctx.selectedAdditionalCourseKeys).toEqual(['course-3'])
        expect(moveToStep).not.toHaveBeenCalled()

        methods.setAdditionalCourseSelected.call(ctx, { key: 'course-3' }, false)

        expect(ctx.selectedAdditionalCourseKeys).toEqual([])
        expect(ctx.additionalCourseSelectionChangedAfterTimetable).toBe(false)
    })

    it('does not change course selection from the result read-only table', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const ctx = {
            currentStep: 'result',
            selectedCourseKeys: ['course-1'],
            courseSelectionKey: methods.courseSelectionKey,
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        methods.setCourseSelected.call(ctx, { key: 'course-1' }, false)

        expect(ctx.selectedCourseKeys).toEqual(['course-1'])
        expect(emitted).toEqual([])
    })

    it('posts selected course keys to the student automatic timetable endpoint', async () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/studentsTimetables/components/StudentTimetableEvaluationSettings.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("'/api/homepage/students-timetables/automatic-timetable'")
        expect(source).toContain('selected_course_keys: overrides.selectedCourseKeys ?? this.selectedBackendCourseKeys()')
        expect(source).toContain('initialDeselectedCourseGroupKeys')
        expect(source).toContain('deselected_course_group_keys: overrides.deselectedCourseGroupKeys ?? this.deselectedCourseGroupKeys')
        expect(source).toContain('selected_additional_course_keys: selectedAdditionalCourseKeys')
        expect(source).toContain('selected_additional_courses_required: selectedAdditionalCourseKeys.length > 0')
        expect(source).toContain('selected_quality_criterion_keys: selectedQualityCriterionKeys')
        expect(source).toContain('selected_timetable_type: overrides.selectedTimetableType ?? this.selectedTimetableType')
        expect(source).toContain('selected_timetable_number: overrides.selectedTimetableNumber ?? this.selectedTimetableNumber')
        expect(source).toContain('selection: overrides.selection ?? this.selectionOverride')
        expect(source).toContain("'/api/homepage/students-timetables/automatic-timetable-availability'")
        expect(source).toContain('availability_only: true')
        expect(source).toContain('candidate_courses: this.resultMoreCourseAvailabilityCandidatePayload(courses)')
        expect(source).toContain('Stundenpläne werden berechnet. Das kann einen Moment dauern.')
        expect(source).toContain('student-generated-timetable__calculation-alert')
        expect(source).toContain('this.schoolHours = response.data?.data?.school_hours || []')
        expect(source).toContain('selectionOverride')
        expect(source).toContain("this.moveToStep('result')")
        expect(source).toContain('ensureAutomaticTimetableForResultStep()')
        expect(source).toContain('canMoveGeneratedTimetable(-1)')
        expect(source).toContain('canMoveGeneratedTimetable(1)')
        expect(source).toContain('generatedTimetableDisplaySlot(weekday.value, hour.value)')
        expect(source).toContain('generatedTimetableOccasionalMarkers(weekday.value, hour.value)')
        expect(source).toContain('generatedSlotDateLabel(block)')
        expect(source).toContain('student-generated-timetable__date')
        expect(source).toContain('student-generated-timetable__time-range')
        expect(source).toContain('student-generated-timetable__badge')
        expect(source).not.toContain('<sup v-if="block.isDistanceLearningCourse" class="student-generated-timetable__badge">FU</sup>')
        expect(source).toContain('v-if="block.isAdditionalCourse"')
        expect(source).toContain('student-generated-timetable__badge--additional')
        expect(source).toContain('Zusatz')
        expect(source).not.toContain('v-if="generatedSlotWeekMarker(block)"')
        expect(source).toContain('generatedTimetableSlotRecurrenceLabel(block)')
        expect(source).toContain('student-generated-timetable__recurrence')
        expect(source).toContain('v-if="block.isDistanceLearningCourse"')
        expect(source).toContain('student-generated-timetable__distance-learning')
        expect(source).toContain('Fernunterricht')
        expect(source).toContain("'student-generated-timetable__cell--additional': generatedTimetableCellHasAdditionalCourse(weekday.value, hour.value)")
        expect(source).toContain('generatedTimetableCellHasAdditionalCourse(weekday, hour)')
        expect(source).toContain('.student-generated-timetable__cell--additional')
        expect(source).toContain('student-generated-criteria__meta-row')
        expect(source).toContain('student-generated-criteria__label')
        expect(source).toContain('student-generated-criteria__controls')
        expect(source).toContain('generatedCriteriaVisible')
        expect(source).toContain('student-generated-criteria__card--additional')
        expect(source).toContain('Zusatzkurse')
        expect(source).toContain('generatedAdditionalCourseAcceptanceLabel()')
        expect(source).toContain('generatedAdditionalCourseAcceptanceIcon()')
        expect(source).toContain('Stundenpläne')
    })

    it('keeps conflicting additional courses visible in the result more-courses panel', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            currentStep: 'result',
            generatedTimetable: { slots: {} },
            selectedAdditionalCourseKeys: ['additional-red', 'additional-green'],
            timetableCounts: {
                conflicting_additional_course_keys: ['additional-red'],
            },
            courseSections: [
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    items: [
                        { key: 'additional-red', code: 'D2' },
                        { key: 'additional-green', code: 'M2' },
                    ],
                },
            ],
            ...methods,
            courseSelectionKey: methods.courseSelectionKey,
            get displayedCourseSections() {
                return computed.displayedCourseSections.call(ctx)
            },
            get additionalCourseSections() {
                return computed.additionalCourseSections.call(ctx)
            },
            get additionalCourses() {
                return computed.additionalCourses.call(ctx)
            },
        }

        expect(computed.additionalCourses.call(ctx)).toEqual([
            { key: 'additional-red', code: 'D2' },
            { key: 'additional-green', code: 'M2' },
        ])
        expect(computed.selectedVisibleAdditionalCourseKeys.call(ctx)).toEqual(['additional-red', 'additional-green'])
    })

    it('offers unselected missing and planned courses in the result more-courses panel', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            proposedCourses: [{ key: 'm1', code: 'M1', courseGroup: 'planned' }],
            selectedCourseKeys: ['planned:M1'],
            selectedAdditionalCourseKeys: ['additional:Z1'],
            courseSections: [
                {
                    key: 'missing',
                    items: [{ key: 'd1', code: 'D1' }],
                },
                {
                    key: 'proposed',
                    items: [
                        { key: 'm1', code: 'M1' },
                        { key: 'e1', code: 'E1' },
                    ],
                },
                {
                    key: 'additional',
                    items: [
                        { key: 'z1', code: 'Z1' },
                        { key: 'gs1', code: 'GS1' },
                    ],
                },
            ],
            ...methods,
            get displayedCourseSections() {
                return computed.displayedCourseSections.call(ctx)
            },
            get regularCourseSections() {
                return computed.regularCourseSections.call(ctx)
            },
            get regularCourses() {
                return computed.regularCourses.call(ctx)
            },
            get additionalCourseSections() {
                return computed.additionalCourseSections.call(ctx)
            },
            get additionalCourses() {
                return computed.additionalCourses.call(ctx)
            },
        }

        const resultMoreCourseItems = computed.resultMoreCourseItems.call(ctx)

        expect(resultMoreCourseItems.map((course: Record<string, string>) => `${course.courseGroup}:${course.code}`))
            .toEqual(['missing:D1', 'planned:E1', 'additional:GS1'])
        expect(resultMoreCourseItems.map((course: Record<string, string>) => course.courseGroupLabel))
            .toEqual(['Fehlend', 'Vorgesehen', 'Zusätzlich'])
    })

    it('applies result more-courses as regular and additional recalculation selections', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const selectedRegularCourse = { selectionKey: 'missing:D1', courseGroup: 'missing' }
        const selectedAdditionalCourse = { selectionKey: 'additional:GS1', courseGroup: 'additional' }
        const ctx = {
            resultAdditionalCoursesChanged: true,
            generatingTimetable: false,
            resultMoreCourseAvailabilityLoading: false,
            resultDraftSelectedCourseKeys: ['planned:M1', 'missing:D1'],
            resultDraftAdditionalCourseKeys: ['additional:GS1'],
            selectedCourseKeys: ['planned:M1'],
            selectedAdditionalCourseKeys: [],
            resultMoreCourseItems: [selectedRegularCourse, selectedAdditionalCourse],
            resultMoreCoursesVisible: true,
            selectedResultMoreCourseKey: 'missing:D1',
            resultMoreOfferedCourseSelectionOverrides: {},
            additionalCourseSelectionChangedAfterTimetable: true,
            additionalCourseSelectionLocked: true,
            normalizedCourseKeys: methods.normalizedCourseKeys,
            resultMoreOfferedCourseItemsAnySelected: vi.fn(() => true),
            resultMoreDeselectedCourseGroupKeysForApply: vi.fn(() => ['missing:D1|D1-A']),
            resultMoreOfferedCourseSelectionOverridesFromDeselectedKeys: vi.fn(() => ({})),
            resetGeneratedTimetableSelection: vi.fn(),
            emitAdditionalCourseSelectionChange: methods.emitAdditionalCourseSelectionChange,
            createAutomaticTimetable: vi.fn(async () => undefined),
            $emit: vi.fn(),
        }

        await methods.applyResultMoreCourses.call(ctx)

        expect(ctx.selectedCourseKeys).toEqual(['planned:M1', 'missing:D1'])
        expect(ctx.selectedAdditionalCourseKeys).toEqual(['additional:GS1'])
        expect(ctx.createAutomaticTimetable).toHaveBeenCalled()
    })

    it('shows current timetable criterion status independently from the checkbox selection', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods

        expect(methods.generatedQualityCriterionReached({ selected_reached: true, count: 0 })).toBe(true)
        expect(methods.generatedQualityCriterionReached({ selected_reached: false, count: 8 })).toBe(false)
        expect(methods.generatedQualityCriterionReached({ count: 3 })).toBe(true)
    })

    it('summarizes selected additional courses next to generated criteria', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const ctx = {
            selectedAdditionalCourseKeys: ['course-3', 'course-4'],
            generatedTimetable: {
                additionalCoursesAccepted: false,
                acceptedAdditionalCourseCount: 1,
                missingAdditionalCourses: [{ key: 'course-4' }],
            },
            generatedAdditionalCoursesAccepted: methods.generatedAdditionalCoursesAccepted,
            generatedMissingAdditionalCourses: methods.generatedMissingAdditionalCourses,
            generatedAcceptedAdditionalCourseCount: methods.generatedAcceptedAdditionalCourseCount,
        }

        expect(methods.generatedAdditionalCourseAcceptanceLabel.call(ctx)).toBe('1 / 2')
        expect(methods.generatedAdditionalCourseAcceptanceIcon.call(ctx)).toBe('mdi-alert-circle')
        expect(methods.generatedAdditionalCourseAcceptanceColor.call(ctx)).toBe('error')

        ctx.generatedTimetable = {
            additionalCoursesAccepted: true,
            acceptedAdditionalCourseCount: 2,
            missingAdditionalCourses: [],
        }

        expect(methods.generatedAdditionalCourseAcceptanceLabel.call(ctx)).toBe('2 / 2')
        expect(methods.generatedAdditionalCourseAcceptanceIcon.call(ctx)).toBe('mdi-check-circle')
        expect(methods.generatedAdditionalCourseAcceptanceColor.call(ctx)).toBe('success')
    })

    it('toggles generated quality criteria and emits url state', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const ctx = {
            currentStep: 'result',
            generatingTimetable: false,
            selectedCourseKeys: ['course-1'],
            selectedQualityCriterionKeys: [],
            emitQualityCriteriaSelectionChange: methods.emitQualityCriteriaSelectionChange,
            resetGeneratedTimetableSelection: methods.resetGeneratedTimetableSelection,
            selectedTimetableType: 'green',
            selectedTimetableNumber: 3,
            createAutomaticTimetable: async () => undefined,
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        await methods.toggleGeneratedQualityCriterion.call(ctx, { key: 'saturday_free' })

        expect(ctx.selectedQualityCriterionKeys).toEqual(['saturday_free'])
        expect(emitted).toEqual([
            {
                event: 'quality-criteria-selection-change',
                payload: ['saturday_free'],
            },
        ])

        await methods.toggleGeneratedQualityCriterion.call(ctx, { key: 'saturday_free' })

        expect(ctx.selectedQualityCriterionKeys).toEqual([])
        expect(emitted).toEqual([
            {
                event: 'quality-criteria-selection-change',
                payload: ['saturday_free'],
            },
            {
                event: 'quality-criteria-selection-change',
                payload: [],
            },
        ])
    })

    it('defaults the first active quality criterion only when url state is not explicit', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const defaultContext = {
            defaultQualityCriterionSelection: true,
            defaultQualityCriterionSelectionApplied: false,
            selectedQualityCriterionKeys: [],
            criteria: [
                { key: 'saturday_free', enabled: true },
                { key: 'early_end', enabled: true },
            ],
            activeQualityCriterionKeys: methods.activeQualityCriterionKeys,
            emitQualityCriteriaSelectionChange: methods.emitQualityCriteriaSelectionChange,
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
        }

        methods.ensureDefaultQualityCriterionSelection.call(defaultContext)

        expect(defaultContext.selectedQualityCriterionKeys).toEqual(['saturday_free'])

        const explicitEmptyContext = {
            ...defaultContext,
            defaultQualityCriterionSelection: false,
            defaultQualityCriterionSelectionApplied: false,
            selectedQualityCriterionKeys: [],
        }

        methods.ensureDefaultQualityCriterionSelection.call(explicitEmptyContext)

        expect(explicitEmptyContext.selectedQualityCriterionKeys).toEqual([])
    })

    it('drops unavailable quality criteria from explicit url state', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const emitted: Array<{ event: string, payload?: unknown }> = []
        const ctx = {
            defaultQualityCriterionSelection: false,
            defaultQualityCriterionSelectionApplied: false,
            selectedQualityCriterionKeys: ['saturday_free', 'existing'],
            resultDraftQualityCriterionKeys: ['saturday_free', 'existing'],
            criteria: [
                { key: 'existing', enabled: true },
                { key: 'disabled', enabled: false },
            ],
            normalizedCourseKeys: methods.normalizedCourseKeys,
            activeQualityCriterionKeys: methods.activeQualityCriterionKeys,
            ensureDefaultQualityCriterionSelection: methods.ensureDefaultQualityCriterionSelection,
            emitQualityCriteriaSelectionChange: methods.emitQualityCriteriaSelectionChange,
            $emit(event: string, payload?: unknown) {
                emitted.push({ event, payload })
            },
            get selectedActiveQualityCriterionKeys() {
                return computed.selectedActiveQualityCriterionKeys.call(ctx)
            },
        }

        expect(ctx.selectedActiveQualityCriterionKeys).toEqual(['existing'])

        methods.syncSelectedQualityCriterionKeys.call(ctx)

        expect(ctx.selectedQualityCriterionKeys).toEqual(['existing'])
        expect(ctx.resultDraftQualityCriterionKeys).toEqual(['existing'])
        expect(emitted).toEqual([
            {
                event: 'quality-criteria-selection-change',
                payload: ['existing'],
            },
        ])
    })

    it('moves within the current generated timetable result bucket with arrow controls', async () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            generatedTimetable: { type: 'green' },
            selectedTimetableType: 'green',
            selectedTimetableNumber: 2,
            selectedQualityCriterionKeys: [],
            criteria: [],
            timetableCounts: {
                full_green_timetable_count: 0,
                green_timetable_count: 2,
                conflict_timetable_count: 1,
            },
            generatedTimetableTypeOrder: methods.generatedTimetableTypeOrder,
            activeQualityCriterionKeys: methods.activeQualityCriterionKeys,
            generatedTimetableCountForType: methods.generatedTimetableCountForType,
            resolveGeneratedTimetableSelection: methods.resolveGeneratedTimetableSelection,
            get selectedActiveQualityCriterionKeys() {
                return computed.selectedActiveQualityCriterionKeys.call(ctx)
            },
            get generatedTimetableNavigationType() {
                return computed.generatedTimetableNavigationType.call(ctx)
            },
            get generatedTimetableTotalCount() {
                return computed.generatedTimetableTotalCount.call(ctx)
            },
            get generatedTimetableAbsoluteNumber() {
                return computed.generatedTimetableAbsoluteNumber.call(ctx)
            },
            get generatedTimetableResultTypeCounterLabel() {
                return computed.generatedTimetableResultTypeCounterLabel.call(ctx)
            },
            canMoveGeneratedTimetable: methods.canMoveGeneratedTimetable,
            createAutomaticTimetable: vi.fn(async () => undefined),
            get generatedTimetableNumberLimit() {
                return computed.generatedTimetableNumberLimit.call(ctx)
            },
        }

        expect(computed.generatedTimetablePositionLabel.call(ctx)).toBe('2 / 2')
        expect(computed.generatedTimetableCounterLabel.call(ctx)).toBe('2 / 2 gültige')
        expect(methods.canMoveGeneratedTimetable.call(ctx, 1)).toBe(false)

        await methods.moveGeneratedTimetable.call(ctx, -1)

        expect(ctx.selectedTimetableType).toBe('green')
        expect(ctx.selectedTimetableNumber).toBe(1)

        ctx.createAutomaticTimetable.mockClear()

        await methods.commitGeneratedTimetableNumber.call(ctx, 2)

        expect(ctx.selectedTimetableType).toBe('green')
        expect(ctx.selectedTimetableNumber).toBe(2)
        expect(ctx.createAutomaticTimetable).toHaveBeenCalledTimes(1)
    })

    it('uses the selected criteria timetable count for the generated timetable counter', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            generatedTimetable: { type: 'green' },
            selectedTimetableType: 'green',
            selectedTimetableNumber: 1,
            selectedQualityCriterionKeys: ['saturday_free'],
            criteria: [
                { key: 'saturday_free', enabled: true },
            ],
            timetableCounts: {
                total_timetable_count: 17,
                green_timetable_count: 12,
                selected_quality_criteria_count: 5,
            },
            activeQualityCriterionKeys: methods.activeQualityCriterionKeys,
            selectedQualityCriteriaTimetableCount: methods.selectedQualityCriteriaTimetableCount,
            generatedTimetableCountForType: methods.generatedTimetableCountForType,
            get selectedActiveQualityCriterionKeys() {
                return computed.selectedActiveQualityCriterionKeys.call(ctx)
            },
            get generatedTimetableNavigationType() {
                return computed.generatedTimetableNavigationType.call(ctx)
            },
            get generatedTimetableTotalCount() {
                return computed.generatedTimetableTotalCount.call(ctx)
            },
            get generatedTimetableAbsoluteNumber() {
                return computed.generatedTimetableAbsoluteNumber.call(ctx)
            },
            get generatedTimetableResultTypeCounterLabel() {
                return computed.generatedTimetableResultTypeCounterLabel.call(ctx)
            },
            get generatedTimetableValidResultCount() {
                return computed.generatedTimetableValidResultCount.call(ctx)
            },
            get generatedTimetableConflictResultCount() {
                return computed.generatedTimetableConflictResultCount.call(ctx)
            },
            get generatedTimetableTotalGeneratedCount() {
                return computed.generatedTimetableTotalGeneratedCount.call(ctx)
            },
        }

        expect(computed.generatedTimetablePositionLabel.call(ctx)).toBe('1 / 5')
        expect(computed.generatedTimetableCounterLabel.call(ctx)).toBe('1 / 5 gültige')
        expect(computed.generatedTimetableTotalCountLabel.call(ctx)).toBe('17 Stundenpläne gesamt')
        expect(computed.generatedTimetableResultCountItems.call(ctx).map(item => item.label)).toEqual([
            '5 gültig',
            '0 Konflikte',
        ])
    })

    it('renders single date overlaps as markers instead of visual conflicts', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const ctx = {
            generatedTimetable: {
                slots: {},
            },
            generatedSlotBlock: methods.generatedSlotBlock,
            generatedSlotBlockIdentity: methods.generatedSlotBlockIdentity,
            generatedSlotBlockIsOccasional: methods.generatedSlotBlockIsOccasional,
            generatedSlotBlocksMatch: methods.generatedSlotBlocksMatch,
            generatedSlotConflictBlocksIncludingRegular: methods.generatedSlotConflictBlocksIncludingRegular,
            generatedSlotDetails: methods.generatedSlotDetails,
            generatedSlotWeekMarker: () => '',
            generatedSlotOccasionalConflictMarkers: methods.generatedSlotOccasionalConflictMarkers,
            generatedSlotVisualConflictBlocks: methods.generatedSlotVisualConflictBlocks,
            generatedTimetableDisplaySlot: methods.generatedTimetableDisplaySlot,
            generatedTimetableSlot: methods.generatedTimetableSlot,
            generatedOccasionalMarkerSourceLabel: methods.generatedOccasionalMarkerSourceLabel,
            generatedOccasionalMarkerLabel: methods.generatedOccasionalMarkerLabel,
            generatedOccasionalMarkerIdentity: methods.generatedOccasionalMarkerIdentity,
            generatedOccasionalMarkerMatchesDisplayedSlot: methods.generatedOccasionalMarkerMatchesDisplayedSlot,
            generatedSlotTitle: methods.generatedSlotTitle,
            generatedSlotDateLabel: methods.generatedSlotDateLabel,
            normalizedGeneratedOccasionalMarkerLabel: methods.normalizedGeneratedOccasionalMarkerLabel,
            courseGroupDates: methods.courseGroupDates,
            dateFromIsoValue: methods.dateFromIsoValue,
            formatShortDateValue: methods.formatShortDateValue,
            uniqueGeneratedSlotBlocks: methods.uniqueGeneratedSlotBlocks,
            uniqueGeneratedTimetableOccasionalMarkers: methods.uniqueGeneratedTimetableOccasionalMarkers,
        }
        const singleDateGroup = {
            recurrence_type: 'single',
            dates_count: 1,
            dates: ['2026-02-17'],
            display_label: 'LPT - 1CK - DREI',
        }
        const weeklyGroup = {
            recurrence_type: 'weekly',
            dates_count: 20,
            dates: ['2026-02-17', '2026-02-24'],
            display_label: 'D1 - 1C - GOS',
        }
        const regularSlot = {
            code: 'D1',
            sourceLabel: 'D1-1C-GOS',
            courseGroup: weeklyGroup,
            conflicts: [
                {
                    code: 'LPT',
                    sourceLabel: 'LPT-1CK-DREI',
                    courseGroup: singleDateGroup,
                    isOccasional: true,
                },
            ],
        }
        const occasionalSlot = {
            code: 'LPT',
            sourceLabel: 'LPT-1CK-DREI',
            courseGroup: singleDateGroup,
            isOccasional: true,
            conflicts: [
                {
                    code: 'M1',
                    sourceLabel: 'M1-1C-MAY',
                    courseGroup: weeklyGroup,
                },
            ],
        }

        ctx.generatedTimetable.slots = {
            '3-14': occasionalSlot,
        }

        expect(methods.generatedSlotVisualConflictBlocks.call(ctx, regularSlot)).toEqual([])
        const occasionalMarkers = methods.generatedSlotOccasionalConflictMarkers.call(ctx, regularSlot)

        expect(occasionalMarkers).toMatchObject([
            {
                code: 'LPT',
                date: '2026-02-17',
            },
        ])
        expect(methods.generatedOccasionalMarkerLabel.call(ctx, occasionalMarkers[0])).toBe('LPT-1CK-DREI 17.02.')
        expect(methods.generatedOccasionalMarkerLabel.call(ctx, {
            code: 'LPT',
            sourceLabel: '',
            date: '2026-02-18',
            courseGroup: singleDateGroup,
        })).toBe('LPT-1CK-DREI 18.02.')
        expect(methods.generatedTimetableDisplaySlot.call(ctx, 3, 14)).toMatchObject({
            code: 'M1',
        })

        occasionalSlot.conflicts = []

        expect(methods.generatedTimetableDisplaySlot.call(ctx, 3, 14)).toMatchObject({
            code: 'LPT',
        })
        expect(methods.generatedSlotTitle.call(ctx, occasionalSlot)).toBe('LPT-1CK-DREI')
        expect(methods.generatedSlotDateLabel.call(ctx, occasionalSlot)).toBe('17.02.')
        expect(methods.generatedTimetableOccasionalMarkers.call(ctx, 3, 14)).toEqual([])
    })

    it('summarizes generated timetable overlaps at the bottom of the result', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx: any = {
            generatedTimetable: {
                slots: {
                    '1-5': {
                        key: 'slot-1',
                        code: 'D1',
                        sourceLabel: 'D1-1C-GOS',
                        courseGroup: {
                            weekday: 1,
                            hour: 5,
                            recurrence_type: 'weekly',
                            dates: ['2026-02-17', '2026-02-24'],
                        },
                        conflicts: [
                            {
                                key: 'conflict-1',
                                code: 'M1',
                                sourceLabel: 'M1-1C-MAY',
                                courseGroup: {
                                    weekday: 1,
                                    hour: 5,
                                    recurrence_type: 'weekly',
                                    dates: ['2026-02-17', '2026-02-24'],
                                },
                            },
                        ],
                    },
                },
            },
            generatedSlotBlock: methods.generatedSlotBlock,
            generatedSlotBlockIdentity: methods.generatedSlotBlockIdentity,
            generatedSlotBlockIsOccasional: methods.generatedSlotBlockIsOccasional,
            generatedSlotBlocksMatch: methods.generatedSlotBlocksMatch,
            generatedSlotConflictBlocksIncludingRegular: methods.generatedSlotConflictBlocksIncludingRegular,
            generatedSlotDetails: methods.generatedSlotDetails,
            generatedSlotTitle: methods.generatedSlotTitle,
            generatedSlotBlockKey: methods.generatedSlotBlockKey,
            generatedTimetableConflictPairIsOccasional: methods.generatedTimetableConflictPairIsOccasional,
            generatedTimetableConflictSummaryLabel: methods.generatedTimetableConflictSummaryLabel,
            generatedTimetableConflictLabel: methods.generatedTimetableConflictLabel,
            generatedTimetableConflictCourseLabel: methods.generatedTimetableConflictCourseLabel,
            generatedTimetableSlotRecurrenceLabel: methods.generatedTimetableSlotRecurrenceLabel,
            generatedTimetableConflictDateLabel: methods.generatedTimetableConflictDateLabel,
            generatedTimetableConflictDateLabels: methods.generatedTimetableConflictDateLabels,
            courseGroupDates: methods.courseGroupDates,
            courseGroupWeekInterval: methods.courseGroupWeekInterval,
            weekIntervalFromDates: methods.weekIntervalFromDates,
            dateFromIsoValue: methods.dateFromIsoValue,
            formatShortDateValue: methods.formatShortDateValue,
            uniqueGeneratedSlotBlocks: methods.uniqueGeneratedSlotBlocks,
            get generatedTimetableConflictPairs() {
                return computed.generatedTimetableConflictPairs.call(ctx)
            },
            get generatedTimetableConflictSeverity() {
                return computed.generatedTimetableConflictSeverity.call(ctx)
            },
        }

        expect(computed.generatedTimetableConflictSummaryItems.call(ctx)).toEqual([
            'D1-1C-GOS überschneidet sich mit M1-1C-MAY.',
        ])
        expect(computed.generatedTimetableConflictSeverity.call(ctx)).toBe('error')
        expect(computed.generatedTimetableConflictTitle.call(ctx)).toBe('Konflikte')

        ctx.generatedTimetable.slots['1-5'].conflicts[0].isOccasional = true
        ctx.generatedTimetable.slots['1-5'].conflicts[0].courseGroup = {
            weekday: 1,
            hour: 5,
            recurrence_type: 'single',
            dates_count: 1,
            dates: ['2026-02-17'],
        }

        expect(computed.generatedTimetableConflictSummaryItems.call(ctx)).toEqual([
            'M1-1C-MAY überschneidet sich mit D1-1C-GOS (17.02.).',
        ])
        expect(computed.generatedTimetableConflictSeverity.call(ctx)).toBe('warning')
        expect(computed.generatedTimetableConflictTitle.call(ctx)).toBe('Überschneidungen')
    })

    it('shows hour times and generated slot badges in the timetable result', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            generatedTimetable: {
                slots: {
                    '1-5': {
                        key: 'slot-1',
                        code: 'M6',
                        sourceLabel: 'M6A',
                        isDistanceLearningCourse: true,
                        courseGroup: {
                            hour: 5,
                            time_from: '11:10:00',
                            time_until: '11:55:00',
                            recurrence_interval: 2,
                        },
                    },
                },
            },
            generatedTimetableSchoolHour: methods.generatedTimetableSchoolHour,
            generatedTimetableConfiguredSchoolHour: methods.generatedTimetableConfiguredSchoolHour,
            generatedTimetableHourTimeFrom: methods.generatedTimetableHourTimeFrom,
            generatedTimetableHourTimeUntil: methods.generatedTimetableHourTimeUntil,
            formatTimeValue: methods.formatTimeValue,
            courseGroupWeekMarkerValue: methods.courseGroupWeekMarkerValue,
            courseGroupWeekInterval: methods.courseGroupWeekInterval,
            courseGroupDates: methods.courseGroupDates,
            weekIntervalFromDates: methods.weekIntervalFromDates,
            dateFromIsoValue: methods.dateFromIsoValue,
            generatedTimetableSlotRecurrenceLabel: methods.generatedTimetableSlotRecurrenceLabel,
            generatedSlotWeekMarker: methods.generatedSlotWeekMarker,
            visibleTimetableEntryWeekMarker: methods.visibleTimetableEntryWeekMarker,
            timetableEntryWeekMarkers: methods.timetableEntryWeekMarkers,
            timetableWeekMarkerEntries: methods.timetableWeekMarkerEntries,
            timetableEntriesShareWeekMarkerContext: methods.timetableEntriesShareWeekMarkerContext,
            timetableEntryCourseContextKey: methods.timetableEntryCourseContextKey,
        }

        expect(computed.generatedTimetableHours.call(ctx)).toEqual([
            {
                value: 5,
                hourLabel: '5.',
                timeFrom: '11:10',
                timeUntil: '11:55',
            },
        ])
        expect(methods.generatedSlotWeekMarker.call(ctx, ctx.generatedTimetable.slots['1-5'])).toBe('2-w')
        expect(methods.generatedTimetableSlotRecurrenceLabel.call(ctx, ctx.generatedTimetable.slots['1-5'])).toBe('2-wöchig')
        expect(ctx.generatedTimetable.slots['1-5'].isDistanceLearningCourse).toBe(true)
    })

    it('marks generated timetable cells with additional courses', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const ctx = {
            generatedTimetable: {
                slots: {
                    '1-5': {
                        key: 'slot-1',
                        code: 'INF2',
                        isAdditionalCourse: true,
                    },
                },
                occasionalAppointments: [
                    {
                        key: 'appointment-1',
                        weekday: 2,
                        hour: 6,
                        code: 'INF2',
                        isAdditionalCourse: true,
                    },
                ],
            },
            generatedTimetableDisplaySlot: methods.generatedTimetableDisplaySlot,
            generatedTimetableSlot: methods.generatedTimetableSlot,
            generatedSlotBlock: methods.generatedSlotBlock,
            generatedSlotDisplayBlocks: methods.generatedSlotDisplayBlocks,
            generatedSlotVisualConflictBlocks: methods.generatedSlotVisualConflictBlocks,
            generatedSlotConflictBlocksIncludingRegular: methods.generatedSlotConflictBlocksIncludingRegular,
            generatedSlotBlockIsOccasional: methods.generatedSlotBlockIsOccasional,
            generatedSlotBlocks: methods.generatedSlotBlocks,
            generatedSlotBlockIdentity: methods.generatedSlotBlockIdentity,
            generatedSlotBlocksMatch: methods.generatedSlotBlocksMatch,
            uniqueGeneratedSlotBlocks: methods.uniqueGeneratedSlotBlocks,
            generatedSlotDetails: methods.generatedSlotDetails,
            generatedTimetableOccasionalMarkers: methods.generatedTimetableOccasionalMarkers,
            occasionalAppointmentSelectedForGeneratedTimetable: methods.occasionalAppointmentSelectedForGeneratedTimetable,
            generatedOccasionalMarkerSourceLabel: methods.generatedOccasionalMarkerSourceLabel,
            generatedOccasionalMarkerIdentity: methods.generatedOccasionalMarkerIdentity,
            generatedOccasionalMarkerMatchesDisplayedSlot: methods.generatedOccasionalMarkerMatchesDisplayedSlot,
            normalizedGeneratedOccasionalMarkerLabel: methods.normalizedGeneratedOccasionalMarkerLabel,
            courseGroupDates: () => [],
            generatedAppointmentWeekMarker: () => '',
            generatedSlotOccasionalConflictMarkers: () => [],
            uniqueGeneratedTimetableOccasionalMarkers: methods.uniqueGeneratedTimetableOccasionalMarkers,
        }

        expect(methods.generatedTimetableCellHasAdditionalCourse.call(ctx, 1, 5)).toBe(true)
        expect(methods.generatedTimetableCellHasAdditionalCourse.call(ctx, 2, 6)).toBe(true)
        expect(methods.generatedTimetableCellHasAdditionalCourse.call(ctx, 3, 7)).toBe(false)
    })

    it('uses configured school hours for generated timetable result times when slot times are missing', () => {
        const methods = (StudentTimetableEvaluationSettings as any).methods
        const computed = (StudentTimetableEvaluationSettings as any).computed
        const ctx = {
            schoolHours: [
                {
                    hour: 5,
                    from: '11:10',
                    until: '11:55',
                },
            ],
            generatedTimetable: {
                slots: {
                    '1-5': {
                        key: 'slot-1',
                        code: 'M6',
                        courseGroup: {
                            hour: 5,
                        },
                    },
                },
            },
            generatedTimetableSchoolHour: methods.generatedTimetableSchoolHour,
            generatedTimetableConfiguredSchoolHour: methods.generatedTimetableConfiguredSchoolHour,
            generatedTimetableHourTimeFrom: methods.generatedTimetableHourTimeFrom,
            generatedTimetableHourTimeUntil: methods.generatedTimetableHourTimeUntil,
            formatTimeValue: methods.formatTimeValue,
        }

        expect(computed.generatedTimetableHours.call(ctx)).toEqual([
            {
                value: 5,
                hourLabel: '5.',
                timeFrom: '11:10',
                timeUntil: '11:55',
            },
        ])
    })
})
