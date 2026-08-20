import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import axios from 'axios'
import { describe, expect, it, vi } from 'vitest'
import HomepageApp from '@/pages/homepage/App.vue'
import OverviewV2 from '@/pages/homepage/studentsTimetables/overviewV2/OverviewV2.vue'

const overviewV2Path = resolve(
    process.cwd(),
    'resources/js/pages/homepage/studentsTimetables/overviewV2/OverviewV2.vue',
)
const homepageRoutesPath = resolve(process.cwd(), 'resources/routes/homepage.js')

describe('Student timetables overview V2 preparation', () => {
    it('does not show the homepage loading dots over student timetable routes', () => {
        const overlayVisible = HomepageApp.computed.globalLoadingOverlayVisible

        expect(overlayVisible.call({
            is_loading: 1,
            $route: { path: '/students-timetables/create/adoption' },
        })).toBe(false)
        expect(overlayVisible.call({
            is_loading: 1,
            $route: { path: '/homepage/profile' },
        })).toBe(true)
        expect(overlayVisible.call({
            is_loading: 0,
            $route: { path: '/homepage/profile' },
        })).toBe(false)
    })

    it('shows a local loader until the complete timetable page is initialized', () => {
        const source = readFileSync(overviewV2Path, 'utf8')
        const initialState = OverviewV2.data.call({})

        expect(initialState.pageLoading).toBe(true)
        expect(source).toContain('v-if="pageLoading"')
        expect(source).toContain('class="overview-v2-page-loader"')
        expect(source).toContain('<LoadingAnimation class="overview-v2-page-loader__dots" />')
        expect(source).toContain("import LoadingAnimation from '@/pages/components/LoadingAnimation.vue'")
        expect(source).not.toContain('<v-progress-circular indeterminate rounded color="primary" size="56" width="5" />')
        expect(source).toContain('<template v-else>')
        expect(source).toContain('this.pageLoading = true')
        expect(source).toContain('this.pageLoading = false')
    })

    it('registers the user-side overview V2 route', () => {
        const source = readFileSync(homepageRoutesPath, 'utf8')

        expect(source).toContain("const StudentTimetablesOverviewV2 = () => import('@/pages/homepage/studentsTimetables/overviewV2/OverviewV2.vue')")
        expect(source).toContain("{ path: '/students-timetables/overview', component: StudentTimetablesOverviewV2 }")
        expect(source).toContain("{ path: '/students-timetables/overview-v1', component: StudentTimetablesOverview }")
        expect(source).toContain("{ path: '/students-timetables/overview-v2', component: StudentTimetablesOverviewV2 }")
        expect(source).toContain("{ path: '/students-timetables/create', component: StudentTimetablesOverviewV2 }")
        expect(source).toContain("{ path: '/students-timetables/create/results', component: StudentTimetablesOverviewV2 }")
        expect(source).toContain("{ path: '/students-timetables/create/adoption', component: StudentTimetablesOverviewV2 }")
        expect(source).toContain("if (to.path.startsWith('/students-timetables/'))")
        expect(source).toContain('return { left: 0, top: 0 }')
    })

    it('keeps the V2 page behind the existing student timetable authentication flow', () => {
        const source = readFileSync(overviewV2Path, 'utf8')

        expect(source).toContain('<StudentTimetablesNavigationDrawer v-model="showDrawer" current-route="overview" />')
        expect(source).toContain('this.studentTimetablesStore = useStudentTimetablesUserStore()')
        expect(source).toContain('const isAuthenticated = await this.studentTimetablesStore.getCurrentUser()')
        expect(source).toContain("this.$router.push('/homepage/students-timetables')")
        expect(source).toContain('await this.studentTimetablesStore.loadOverview()')
    })

    it('shows the admin-style student information card below the welcome header', () => {
        const source = readFileSync(overviewV2Path, 'utf8')

        expect(source).toContain('.students-timetables-overview-v2-page > .hero {')
        expect(source).toContain('max-width: none;')
        expect(source).not.toContain('<div class="hero-card">')
        expect(source).toContain('class="overview-v2-panel"')
        expect(source).toContain('class="overview-v2-navigation"')
        expect(source).toContain('class="overview-v2-introduction"')
        expect(source).toContain('class="overview-v2-current-selection"')
        expect(source).not.toContain('Aktuelle Auswahl')
        expect(source).not.toContain('overview-v2-current-selection__label')
        expect(source).toContain("{{ currentSelectionClass || '–' }} · {{ currentSelectionFullName }}")
        expect(source).toContain('· {{ currentSelectionReligion }}')
        expect(source).toContain('currentSelectionSexPresentation.icon')
        expect(source).toContain('{{ currentSelectionEmail }}')
        expect(source).not.toContain('copyCurrentSelectionEmail')
        expect(source).not.toContain('mdi-content-copy')
        expect(source).toContain('background: linear-gradient(135deg, #eef2ff 0%, #ffffff 52%, #f5f3ff 100%);')
        expect(source).not.toContain('overview-v2-facts')
        expect(source).not.toContain('overview-v2-details')
        expect(source).not.toContain('overview-v2-information-card')
        expect(source).not.toContain('weekdayLabel')
        expect(source).not.toContain('dateLabel')
        expect(source).not.toContain('user.schoolclass')
        expect(source).not.toContain('studentReligionLabel')
        expect(source).not.toContain('heroCourseHistorySections')
        expect(source).not.toContain('Abgeschlossene Kurse')
        expect(source).not.toContain('Negative Kurse')
        expect(source).not.toContain('heroTimetableSelectionSummary')
        expect(source).not.toContain('Stundenplanauswahl')
        expect(source).not.toContain('Zur Startseite')
        expect(source).not.toContain('Alle Termine, Kurse und persönlichen Einstellungen in einer übersichtlichen Ansicht.')
        expect(source).not.toContain('Vorbereitung')
        expect(source).not.toContain('class="overview-v2-student"')
        expect(source).not.toContain('userInitials')
        expect(source).not.toContain('{{ user.first_name }} {{ user.last_name }}')
        expect(source).not.toContain('Bisherige Übersicht')
        expect(source).not.toContain("$router.push('/students-timetables/overview-v1')")
    })

    it('offers the same student and study information actions as the admin selection card', () => {
        const source = readFileSync(overviewV2Path, 'utf8')

        expect(source).toContain('class="overview-v2-student-info-actions"')
        expect(source).toContain('open-on-hover')
        expect(source).toContain('icon="mdi-information-outline"')
        expect(source).toContain('aria-label="Informationen zum Studierenden anzeigen"')
        expect(source).toContain('@click="openStudentInfoDialog"')
        expect(source).toContain('Studierenden-Information')
        expect(source).toContain('icon="mdi-school-outline"')
        expect(source).toContain('aria-label="Informationen zum Studium anzeigen"')
        expect(source).toContain('@click="openStudyInfoDialog"')
        expect(source).toContain('Informationen zum Studium')
        expect(source).toContain('<v-dialog v-model="studentInfoDialogOpen" max-width="620" persistent>')
        expect(source).toContain('<v-dialog v-model="studyInfoDialogOpen" max-width="960" persistent scrollable>')
        expect(source).toContain('{{ studentInformationInstructionType }}')
        expect(source).toContain('{{ studentInformationSubjectPlan }}')
        expect(source).toContain('{{ studentInformationSemesterLabel }}')
        expect(source).not.toContain('studentInformationSchoolLevel')
        expect(source).toContain('v-for="item in studentInformationCalculationItems"')
        expect(source).toContain('v-for="group in studentStudyModuleGroups"')
        expect(source).toContain('this.overview?.student_information || null')
        expect(source).toContain('this.studyInfoDialogOpen = false')
        expect(source).toContain('this.studentInfoDialogOpen = false')
    })

    it('uses the canonical numbered module names from admin V3', () => {
        expect(OverviewV2.methods.moduleDisplayName({
            code: 'Rev2',
            name: 'Religion 2',
        })).toBe('Religion evangelisch 2')
        expect(OverviewV2.methods.moduleDisplayName({
            code: 'Rk3',
            name: 'Religion 3',
        })).toBe('Religion katholisch 3')
        expect(OverviewV2.methods.moduleDisplayName({
            code: 'D2',
            name: 'Deutsch 2',
        })).toBe('Deutsch 2')
    })

    it('lets the student privately edit the compact study selection card', () => {
        const source = readFileSync(overviewV2Path, 'utf8')

        expect(source).toContain('class="overview-v2-compact-planning-card"')
        expect(source).toContain('aria-labelledby="overview-v2-compact-planning-title"')
        expect(source).toContain('icon="mdi-tune-variant"')
        expect(source).toContain('Studienauswahl')
        expect(source).toContain('v-for="field in studentPlanningSelectionFields"')
        expect(source).toContain('{{ field.label }}')
        expect(source).toContain('{{ field.value }}')
        expect(source).toContain('icon="mdi-pencil"')
        expect(source).toContain('@click="openStudentSelectionDialog(field)"')
        expect(source).toContain('this.studentInformation?.selection_fields')
        expect(source).toContain('<v-dialog v-model="studentSelectionDialogOpen" max-width="460" persistent>')
        expect(source).toContain('v-model="studentSelectionDraftValue"')
        expect(source).toContain('<v-chip-group')
        expect(source).toContain('v-for="option in studentSelectionDraftOptions"')
        expect(source).toContain('class="overview-v2-selection-option"')
        expect(source).toContain('{{ option.title }}')
        expect(source).not.toContain('<v-select')
        expect(source).not.toMatch(/<v-chip-group[\s\S]*?\smandatory(?:\s|>)/)
        expect(source).toContain('this.studentSelectionDraftValue === null')
        expect(source).toContain('this.studentSelectionDraftValue === undefined')
        expect(source).toContain('[this.studentSelectionDraftKey]: this.studentSelectionDraftValue ?? null')
        expect(source).toContain('@click="saveStudentSelection"')
        expect(source).toContain('this.studentTimetablesStore.updateProfileSelection(selection)')
        expect(source).toContain('this.studentTimetablesStore.restoreProfileSelection()')
        expect(source).toContain('this.overview?.selection_override || {}')
        expect(source).not.toContain('/api/admin/')
        expect(source).not.toContain('localStorage')
        expect(source).toContain('Zurücksetzen')
        expect(source).toContain('grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));')
    })

    it('offers three timetable starting cards with availability-aware navigation', () => {
        const source = readFileSync(overviewV2Path, 'utf8')

        expect(source).toContain('Stundenplanerstellung')
        expect(source).toContain('Wie möchtest du beginnen?')
        expect(source).toContain('v-for="option in timetableStartOptions"')
        expect(source).toContain("title: 'Leerer Stundenplan'")
        expect(source).toContain("title: 'Mein gespeicherter Stundenplan'")
        expect(source).toContain("title: 'Stundenplan der Lehrperson'")
        expect(source).toContain(':disabled="!option.available"')
        expect(source).toContain('Stundenplan der Lehrperson verfügbar${publishedTimetableName')
        expect(source).toContain('? ` · Nr. ${publishedTimetableName}`')
        expect(source).not.toContain('class="overview-v2-timetable-start-number"')
        expect(source).toContain('this.overview?.personal_timetable?.id')
        expect(source).toContain('this.overview?.personal_timetable?.adopted_at')
        expect(source).toContain('this.overview?.published_timetable?.id')
        expect(source).toContain('grid-template-columns: repeat(3, minmax(0, 1fr));')

        const personalTimetableSavedAtLabel = OverviewV2.computed.personalTimetableSavedAtLabel.call({
            overview: {
                personal_timetable: {
                    adopted_at: '2026-08-20T12:34:00+02:00',
                },
            },
        })

        expect(personalTimetableSavedAtLabel).toBe('20.08.2026, 12:34')

        const startOptions = OverviewV2.computed.timetableStartOptions.call({
            hasPersonalTimetable: true,
            hasPublishedTimetable: true,
            personalTimetableSavedAtLabel,
            overview: {
                published_timetable: {
                    id: 42,
                    name: '26ABC',
                },
            },
        })

        expect(startOptions[2]).toMatchObject({
            key: 'published',
            timetableName: '26ABC',
            availabilityLabel: 'Stundenplan der Lehrperson verfügbar · Nr. 26ABC',
        })
        expect(startOptions[1]).toMatchObject({
            key: 'personal',
            availabilityLabel: 'Gespeichert am 20.08.2026, 12:34 Uhr',
        })

        const pushedRoutes: unknown[] = []
        let resetCount = 0
        const context = {
            timetableStartOptions: [
                { key: 'empty', available: true },
                { key: 'personal', available: true },
                { key: 'published', available: true },
            ],
            initializedSources: [] as string[],
            initializeSavedTimetableAdoption(source: string) {
                this.initializedSources.push(source)
            },
            resetStudentTimetablePlanning() {
                resetCount += 1
            },
            $router: {
                push(route: unknown) {
                    pushedRoutes.push(route)
                },
            },
        }

        OverviewV2.methods.startTimetableFrom.call(context, 'empty')
        OverviewV2.methods.startTimetableFrom.call(context, 'personal')
        OverviewV2.methods.startTimetableFrom.call(context, 'published')

        expect(pushedRoutes).toEqual([
            '/students-timetables/create',
            {
                path: '/students-timetables/create/adoption',
                query: { manual_timetable: 'personal' },
            },
            {
                path: '/students-timetables/create/adoption',
                query: { manual_timetable: 'published' },
            },
        ])
        expect(context.initializedSources).toEqual(['personal', 'published'])
        expect(resetCount).toBe(1)
    })

    it('loads saved and teacher timetables into the manual editor', () => {
        const source = readFileSync(overviewV2Path, 'utf8')
        const savedCourse = {
            key: 'course-d1',
            keys: ['course-d1'],
            timetable_entries: [{
                key: 'entry-d1',
                weekday: 1,
                hour: 3,
                module_code: 'D1',
            }],
        }
        const savedModule = {
            code: 'D1',
            selection_key: 'previous:D1',
            courses: [savedCourse],
        }

        expect(source).toContain("path: '/students-timetables/create/adoption'")
        expect(source).toContain('this.initializeSavedTimetableAdoption(source)')
        expect(source).toContain('savedTimetableForManualEditor(savedTimetable.timetable, source)')

        const context = {
            activeManualModuleGroupKey: 'current',
            adoptionRemovedCourseKeys: ['old-entry'],
            mainModuleSelectionGroups: [],
            manualCatalogCourses: [savedCourse],
            manualPendingCourseKeys: ['pending-entry'],
            manualSelectedCourseKeys: ['old-course'],
            moduleSelectionGroups: [{ modules: [savedModule] }],
            overview: {
                published_timetable: {
                    id: 42,
                    active_course_group_keys: [],
                    state: {
                        moduleSelection: {
                            selectedCourseKeys: ['entry-d1'],
                            selectedKeys: ['previous:D1'],
                        },
                        manualTimetableDraft: {
                            removedCourseKeys: [],
                            selectedCourseKeys: [],
                        },
                    },
                    timetable: {
                        weekdays: [{ label: 'Montag' }, { label: 'Dienstag' }],
                        semesters: [{
                            weeks: [{
                                hours: [{
                                    hour: 7,
                                    from: '14:30',
                                    until: '15:15',
                                    cells: [
                                        { courses: [] },
                                        { courses: [{ identifier: 'D1-3R-MAY', label: 'DEUTSCH' }] },
                                    ],
                                }],
                            }],
                        }],
                    },
                },
            },
            savedTimetableAdoptionBase: null,
            savedTimetableAdoptionCourseKeys: ['old-course'],
            savedTimetableAdoptionModuleKeys: ['old-module'],
            timetableCalculationError: 'Alter Fehler',
            timetableCalculationStatus: 'idle',
        }

        const initialized = OverviewV2.methods.initializeSavedTimetableAdoption.call(context, 'published')

        expect(initialized).toBe(true)
        expect(context).toMatchObject({
            activeManualModuleGroupKey: '',
            adoptionRemovedCourseKeys: [],
            manualPendingCourseKeys: [],
            manualSelectedCourseKeys: [],
            savedTimetableAdoptionBase: {
                key: 'saved-published-timetable',
                slots: {
                    '2-7': expect.objectContaining({ code: 'D1' }),
                },
            },
            savedTimetableAdoptionCourseKeys: ['course-d1'],
            savedTimetableAdoptionModuleKeys: ['previous:D1'],
            timetableCalculationError: '',
            timetableCalculationStatus: 'success',
        })
        expect(context.savedTimetableAdoptionBase.slots).not.toHaveProperty('1-3')

        expect(OverviewV2.computed.savedTimetableAdoptionModules.call({
            mainModuleSelectionGroups: [],
            moduleSelectionGroups: [{ modules: [savedModule] }],
            savedTimetableAdoptionModuleKeys: ['previous:D1'],
        })).toEqual([savedModule])

        expect(source).toContain('if (initialized) await this.restoreManualTimetableDraft()')
    })

    it('uses the published timetable identity to persist additional courses across reloads', async () => {
        const fingerprint = 'ae960c7ed4a4012413c66eb1e187515aadb8bfc13acf1eda53eb88fa8b054f51'
        const put = vi.spyOn(axios, 'put').mockResolvedValueOnce({ data: { data: {} } })
        const context = {
            savedTimetableAdoptionSource: 'published',
            overview: {
                published_timetable: {
                    state: {
                        manualTimetableDraft: {
                            fingerprint,
                            timetableIndex: 0,
                            timetableKey: 'backend-full_green-1',
                        },
                    },
                },
            },
            $route: { query: { manual_timetable: 'published' } },
            adoptionRemovedCourseKeys: [],
            isTimetableAdoptionPage: true,
            manualCatalogCourses: [{ key: 'course-inf1', keys: ['course-inf1'] }],
            manualPendingCourseKeys: [],
            manualSelectedCourseKeys: ['course-inf1'],
            manualTimetableDraftRouteSelection: OverviewV2.methods.manualTimetableDraftRouteSelection,
            manualTimetableDraftSaveQueue: null,
        }

        expect(OverviewV2.methods.manualTimetableDraftRouteSelection.call(context)).toEqual({
            fingerprint,
            timetableIndex: 0,
            timetableKey: 'backend-full_green-1',
            workspaceId: 'ae960c7e-d4a4-4124-83c6-6eb1e187515b',
        })

        expect(await OverviewV2.methods.saveManualTimetableDraft.call(context)).toBe(true)
        expect(put).toHaveBeenCalledWith(
            '/api/homepage/students-timetables/timetable-v3/state',
            {
                workspace_id: 'ae960c7e-d4a4-4124-83c6-6eb1e187515b',
                manual_timetable_draft: {
                    source: 'automatic',
                    fingerprint,
                    timetable_key: 'backend-full_green-1',
                    timetable_index: 0,
                    selected_course_keys: ['course-inf1'],
                    removed_course_keys: [],
                },
            },
        )

        put.mockRestore()

        const get = vi.spyOn(axios, 'get').mockResolvedValueOnce({
            data: {
                data: {
                    manual_timetable_draft: {
                        source: 'automatic',
                        fingerprint,
                        timetableKey: 'backend-full_green-1',
                        timetableIndex: 0,
                        selectedCourseKeys: ['course-inf1'],
                        removedCourseKeys: [],
                    },
                },
            },
        })

        context.manualSelectedCourseKeys = []

        expect(await OverviewV2.methods.restoreManualTimetableDraft.call(context)).toBe(true)
        expect(context.manualSelectedCourseKeys).toEqual(['course-inf1'])

        get.mockRestore()
    })

    it('clears the complete timetable planning state before starting empty', () => {
        const context = {
            activeModuleGroupKey: 'current',
            moduleCourseDialogModule: { selection_key: 'module-d1' },
            moduleCoursesDialogOpen: true,
            moduleSelectionLimitMessage: 'Limit erreicht',
            scheduleCreationMode: 'automatic',
            selectedCourseKeys: ['course-d1'],
            selectedModuleKeys: ['module-d1'],
            timetableCalculationCheckedCombinationCount: 14,
            timetableCalculationCombinationCount: 20,
            timetableCalculationError: 'Fehler',
            timetableCalculationProgressPercent: 70,
            timetableCalculationProgressPhase: 'checking',
            timetableCalculationRequestId: 3,
            timetableCalculationResult: { fingerprint: 'existing' },
            timetableCalculationStatus: 'success',
            timetableFilters: { include_saturday: true, free_days: 2 },
            timetablePageError: 'Seitenfehler',
            timetablePageLoading: true,
            timetablePageLoadingDirection: 'next',
            timetablePageRequestId: 5,
            timetableSelectedIndex: 12,
            timetableWorkspaceId: 'existing-workspace',
        }

        OverviewV2.methods.resetStudentTimetablePlanning.call(context)

        expect(context).toMatchObject({
            activeModuleGroupKey: '',
            moduleCourseDialogModule: null,
            moduleCoursesDialogOpen: false,
            moduleSelectionLimitMessage: '',
            scheduleCreationMode: null,
            selectedCourseKeys: [],
            selectedModuleKeys: [],
            timetableCalculationCheckedCombinationCount: 0,
            timetableCalculationCombinationCount: 0,
            timetableCalculationError: '',
            timetableCalculationProgressPercent: 0,
            timetableCalculationProgressPhase: 'preparing',
            timetableCalculationRequestId: 4,
            timetableCalculationResult: null,
            timetableCalculationStatus: 'idle',
            timetableFilters: { include_saturday: true, free_days: null },
            timetablePageError: '',
            timetablePageLoading: false,
            timetablePageLoadingDirection: '',
            timetablePageRequestId: 6,
            timetableSelectedIndex: 0,
            timetableWorkspaceId: '',
        })
    })

    it('repeats the student information card and both information actions throughout planning', () => {
        const source = readFileSync(overviewV2Path, 'utf8')

        expect(source).toContain('<template v-if="isTimetablePlanningPage">')
        expect(source).toContain("return this.$route.path === '/students-timetables/create/adoption'")
        expect(source).toContain('|| this.isTimetableAdoptionPage')
        expect(source).toContain('class="overview-v2-current-selection overview-v2-current-selection--creation"')
        expect(source).toContain("'overview-v2-current-selection--results': !isTimetableCreationModePage")
        expect(source).toContain('.overview-v2-current-selection--creation {')
        expect(source).toMatch(
            /\.overview-v2-current-selection--creation\s*\{[^}]*margin:\s*clamp\(24px, 3vw, 42px\) clamp\(24px, 4vw, 58px\) 0;/,
        )
        expect(source).toMatch(
            /\.overview-v2-current-selection--results\s*\{[^}]*margin:\s*clamp\(22px, 3vw, 40px\) clamp\(22px, 3vw, 40px\) 0;/,
        )
        expect(source).toMatch(
            /\.overview-v2-creation-mode-page\s*\{[^}]*padding:\s*16px clamp\(24px, 4vw, 58px\) clamp\(34px, 5vw, 64px\);/,
        )
        expect(source).toMatch(
            /\.overview-v2-results-page\s*\{[^}]*padding:\s*16px clamp\(22px, 3vw, 40px\) clamp\(22px, 3vw, 40px\);/,
        )
        expect(source.match(/@click="openStudentInfoDialog"/g)).toHaveLength(2)
        expect(source.match(/@click="openStudyInfoDialog"/g)).toHaveLength(2)
        expect(source).toContain(':key="`creation-student-info-${item.key}`"')
        expect(source).toContain(':key="`creation-student-study-hover-${group.key}`"')
    })

    it('shows the current study selection read-only on creation and results', () => {
        const source = readFileSync(overviewV2Path, 'utf8')
        const readOnlyCard = source.match(
            /<section\s+v-if="[\s\S]*?isTimetablePlanningPage && studentPlanningSelectionFields\.length[\s\S]*?class="overview-v2-compact-planning-card overview-v2-compact-planning-card--planning-readonly"[\s\S]*?<\/section>/,
        )?.[0] || ''
        const studentCardPosition = source.indexOf(
            'class="overview-v2-current-selection overview-v2-current-selection--creation"',
        )
        const readOnlyCardPosition = source.indexOf(
            'class="overview-v2-compact-planning-card overview-v2-compact-planning-card--planning-readonly"',
        )
        const resultsPagePosition = source.indexOf(
            '<div v-else-if="isTimetableResultsPage" class="overview-v2-results-page">',
        )

        expect(readOnlyCard).toContain('aria-readonly="true"')
        expect(readOnlyCard).toContain('Studienauswahl')
        expect(readOnlyCard).toContain('v-for="field in studentPlanningSelectionFields"')
        expect(readOnlyCard).toContain(':key="`planning-study-selection-${field.key}`"')
        expect(readOnlyCard).toContain(
            "'overview-v2-compact-planning-card--results-readonly': !isTimetableCreationModePage",
        )
        expect(readOnlyCard).toContain('overview-v2-compact-planning-item--readonly')
        expect(readOnlyCard).not.toContain('@click')
        expect(readOnlyCard).not.toContain('mdi-pencil')
        expect(readOnlyCard).not.toContain('Zurücksetzen')
        expect(source).toContain('.overview-v2-compact-planning-card--results-readonly {')
        expect(readOnlyCardPosition).toBeGreaterThan(studentCardPosition)
        expect(resultsPagePosition).toBeGreaterThan(readOnlyCardPosition)
    })

    it('offers the admin-style planning restart on creation, results, and adoption', () => {
        const source = readFileSync(overviewV2Path, 'utf8')

        expect(source.match(/class="overview-v2-creation-mode-restart"/g)).toHaveLength(3)
        expect(source.match(/prepend-icon="mdi-restart"/g)).toHaveLength(3)
        expect(source.match(/@click="restartStudentTimetablePlanning"/g)).toHaveLength(3)
        expect(source.match(/\s+Neustart\s+/g)).toHaveLength(3)
        expect(source.match(/overview-v2-creation-mode-actions--split/g)).toHaveLength(4)
        expect(source).toContain('.overview-v2-creation-mode-actions--split {')

        let resetCount = 0
        const replacedRoutes: string[] = []
        const context = {
            timetableCalculationStatus: 'success',
            timetablePageLoading: false,
            resetStudentTimetablePlanning() {
                resetCount += 1
            },
            $router: {
                replace(route: string) {
                    replacedRoutes.push(route)
                },
            },
        }

        OverviewV2.methods.restartStudentTimetablePlanning.call(context)

        expect(resetCount).toBe(1)
        expect(replacedRoutes).toEqual(['/students-timetables/overview'])

        context.timetableCalculationStatus = 'calculating'
        OverviewV2.methods.restartStudentTimetablePlanning.call(context)

        expect(resetCount).toBe(1)
        expect(replacedRoutes).toHaveLength(1)
    })

    it('opens the student manual-adoption workspace with both catalogs and the timetable', async () => {
        const source = readFileSync(overviewV2Path, 'utf8')
        const adoptionStart = source.indexOf(
            '<div v-else-if="isTimetableAdoptionPage" class="overview-v2-adoption-page">',
        )
        const adoptionEnd = source.indexOf('<div v-else class="overview-v2-introduction">', adoptionStart)
        const adoptionPage = source.slice(adoptionStart, adoptionEnd)

        expect(adoptionStart).toBeGreaterThan(-1)
        expect(adoptionEnd).toBeGreaterThan(adoptionStart)
        expect(adoptionPage).toContain('timetable-v3__adoption-card--manual')
        expect(adoptionPage).toContain('Manueller Stundenplan')
        expect(adoptionPage).toContain('v-if="publishedTimetableAdoptionName"')
        expect(adoptionPage).toContain('Nr. {{ publishedTimetableAdoptionName }}')
        expect(adoptionPage).toContain('v-if="personalTimetableAdoptionSavedAtLabel"')
        expect(adoptionPage).toContain('Zuletzt gespeichert: {{ personalTimetableAdoptionSavedAtLabel }} Uhr')
        expect(adoptionPage).toContain('Ausgewählte Module')
        expect(adoptionPage).toContain('v-for="module in adoptionSelectedModules"')
        expect(adoptionPage).toContain('closable')
        expect(adoptionPage).toContain('close-icon="mdi-close-circle"')
        expect(adoptionPage).toContain('@click:close.stop="removeAdoptionModule(module)"')
        expect(adoptionPage).toContain('{{ adoptionSelectedModuleHoursLabel }} Std.')
        expect(adoptionPage).toContain('Studierenden Module')
        expect(adoptionPage).toContain('Alle Module')
        expect(adoptionPage).toContain('v-for="group in visibleManualModuleCatalogGroups"')
        expect(adoptionPage).toContain('@click="openManualModuleCoursesDialog(module)"')
        expect(source).toContain('v-for="overlapLabel in courseScheduleRowOverlapLabels(course, scheduleRow)"')
        expect(source).toContain('class="overview-v2-module-course-overlap"')
        expect(source).toMatch(
            /\.overview-v2-module-course-overlap\s*\{[\s\S]*?color:\s*#b42318;[\s\S]*?font-weight:\s*400;/,
        )
        expect(adoptionPage).toContain('manualModuleGroupNotIntended(group)')
        expect(adoptionPage).toContain('module.is_intended_for_selection === false')
        expect(adoptionPage.match(/Nicht vorgesehen!/g)).toHaveLength(2)
        expect(adoptionPage).toContain('class="overview-v2-adoption-timetable"')
        expect(adoptionPage).toContain(':timetables="[adoptionDisplayedTimetable]"')
        expect(adoptionPage).toContain(':navigation-visible="false"')
        expect(adoptionPage).toContain(':position-visible="false"')
        expect(adoptionPage).toContain('class="timetable-v3__adoption-pdf-button"')
        expect(adoptionPage).toContain('prepend-icon="mdi-file-pdf-box"')
        expect(adoptionPage).toContain(':loading="pdfExporting"')
        expect(adoptionPage).toContain('@click="downloadManualTimetablePdf"')
        expect(adoptionPage).toContain('PDF')
        expect(adoptionPage).toContain('class="timetable-v3__adoption-save-button"')
        expect(adoptionPage).toContain('prepend-icon="mdi-content-save-outline"')
        expect(adoptionPage).toContain('@click="savePersonalTimetable"')
        expect(adoptionPage).toContain('Speichern')
        expect(adoptionPage).toContain('@click="restartStudentTimetablePlanning"')
        expect(adoptionPage).toContain('Neustart')
        expect(adoptionPage).toContain('@click="goBackToTimetableResults"')
        expect(adoptionPage).toContain('Zurück')
        expect(source).toContain('if (this.isTimetableResultsPage || this.isTimetableAdoptionPage)')
        expect(source).toContain("path: '/students-timetables/create/adoption'")
        expect(source).toContain('timetable_index: String(timetableIndex)')
        expect(source).toContain('timetable_key: timetableKey')
        expect(source).toContain('overviewPdf as downloadStudentTimetableOverviewPdf')
        expect(source).toContain('downloadStudentTimetableOverviewPdf.url()')
        expect(OverviewV2.data.call({}).pdfExporting).toBe(false)

        const manualTimetablePdfPayload = OverviewV2.methods.manualTimetablePdfPayload.call({
            adoptionSelectedModuleCount: 2,
            currentSelectionClass: '1C',
            currentSelectionFullName: 'PABINGER Elena',
            personalTimetablePayload: vi.fn().mockReturnValue({
                title: 'Stundenplan',
                weekdays: [{ label: 'Montag' }],
                semesters: [],
            }),
            studentPlanningSelectionFields: [
                { label: 'Ethik / Religion', value: 'Ethik' },
                { label: 'Sprache', value: 'Französisch' },
            ],
        })

        expect(manualTimetablePdfPayload).toMatchObject({
            manual_cover: true,
            title: 'Stundenplan',
            subtitle: '2 Module',
            student: '1C · PABINGER Elena',
            study_selections: [
                { label: 'Ethik / Religion', value: 'Ethik' },
                { label: 'Sprache', value: 'Französisch' },
            ],
            print_options: {
                single_weeks: false,
                course_list: false,
                course_overview: false,
            },
        })

        expect(OverviewV2.computed.personalTimetableAdoptionSavedAtLabel.call({
            personalTimetableSavedInEditor: false,
            personalTimetableSavedAtLabel: '20.08.2026, 12:34',
            savedTimetableAdoptionSource: 'personal',
        })).toBe('20.08.2026, 12:34')
        expect(OverviewV2.computed.personalTimetableAdoptionSavedAtLabel.call({
            personalTimetableSavedInEditor: false,
            personalTimetableSavedAtLabel: '20.08.2026, 12:34',
            savedTimetableAdoptionSource: 'published',
        })).toBe('')
        expect(OverviewV2.computed.personalTimetableAdoptionSavedAtLabel.call({
            personalTimetableSavedInEditor: true,
            personalTimetableSavedAtLabel: '20.08.2026, 12:35',
            savedTimetableAdoptionSource: 'published',
        })).toBe('20.08.2026, 12:35')
        expect(OverviewV2.computed.publishedTimetableAdoptionName.call({
            overview: { published_timetable: { name: '26ABC' } },
            savedTimetableAdoptionSource: 'published',
        })).toBe('26ABC')
        expect(OverviewV2.computed.publishedTimetableAdoptionName.call({
            overview: { published_timetable: { name: '26ABC' } },
            savedTimetableAdoptionSource: 'personal',
        })).toBe('')

        const pushedRoutes: unknown[] = []
        const restoreManualTimetableDraft = vi.fn().mockResolvedValue(true)
        const context = {
            selectedTimetableResult: { key: 'selected-timetable', slots: {} },
            timetableCalculationResult: { fingerprint: 'a'.repeat(64) },
            timetableCalculationStatus: 'success',
            timetablePageLoading: false,
            timetableSelectedIndex: 12,
            timetableWorkspaceId: 'de7c2a59-fae1-4a61-a2d9-edbe82dc8ee4',
            restoreManualTimetableDraft,
            $router: {
                async push(route: unknown) {
                    pushedRoutes.push(route)
                },
            },
        }

        await OverviewV2.methods.openStudentTimetableAdoption.call(context)

        expect(pushedRoutes).toEqual([{
            path: '/students-timetables/create/adoption',
            query: {
                workspace_id: 'de7c2a59-fae1-4a61-a2d9-edbe82dc8ee4',
                fingerprint: 'a'.repeat(64),
                timetable_index: '12',
                timetable_key: 'selected-timetable',
            },
        }])
        expect(restoreManualTimetableDraft).toHaveBeenCalledOnce()
    })

    it('returns from adoption to the restored timetable results', () => {
        const pushedRoutes: unknown[] = []
        const context = {
            timetableCalculationResult: { fingerprint: 'a'.repeat(64) },
            timetableWorkspaceId: 'de7c2a59-fae1-4a61-a2d9-edbe82dc8ee4',
            $route: { query: {} },
            $router: {
                push(route: unknown) {
                    pushedRoutes.push(route)
                },
            },
        }

        OverviewV2.methods.goBackToTimetableResults.call(context)

        expect(pushedRoutes).toEqual([{
            path: '/students-timetables/create/results',
            query: {
                workspace_id: 'de7c2a59-fae1-4a61-a2d9-edbe82dc8ee4',
                fingerprint: 'a'.repeat(64),
            },
        }])
    })

    it('saves the displayed manual timetable as the student personal version', async () => {
        const storedPayloads: unknown[] = []
        const context = {
            adoptionDisplayedTimetable: {
                key: 'student-manual-timetable',
                slots: {
                    '1-3': {
                        key: 'rev2-entry',
                        code: 'Rev2',
                        name: 'Religion 2',
                        sourceLabel: 'Rev2 - 3R - HUB',
                        courseGroup: {
                            key: 'rev2-course',
                            module_code: 'Rev2',
                            course_title: 'Religion 2',
                            display_label: 'Rev2 - 3R - HUB',
                            starts_at: '09:50',
                            ends_at: '10:35',
                            recurrence_label: '1-wöchig',
                            recurrence_interval: 1,
                            dates: ['2026-09-07'],
                        },
                        conflicts: [],
                        sameSlotEntries: [],
                    },
                },
            },
            adoptionPlacedCourseKeys: ['rev2-course'],
            personalTimetableSavedInEditor: false,
            personalTimetableSaving: false,
            personalTimetableSchoolHours: [{ hour: 3, from: '09:50', until: '10:35' }],
            personalTimetablePayload: OverviewV2.methods.personalTimetablePayload,
            studentTimetablesStore: {
                async savePersonalTimetable(payload: unknown) {
                    storedPayloads.push(payload)

                    return true
                },
            },
        }

        const saved = await OverviewV2.methods.savePersonalTimetable.call(context)

        expect(saved).toBe(true)
        expect(context.personalTimetableSavedInEditor).toBe(true)
        expect(context.personalTimetableSaving).toBe(false)
        expect(storedPayloads).toEqual([{
            timetable: {
                title: 'Stundenplan',
                weekdays: [
                    { label: 'Montag' },
                    { label: 'Dienstag' },
                    { label: 'Mittwoch' },
                    { label: 'Donnerstag' },
                    { label: 'Freitag' },
                ],
                semesters: [{
                    label: 'Stundenplan',
                    date_range: '',
                    weeks: [{
                        label: '',
                        hours: [{
                            hour: 3,
                            from: '09:50',
                            until: '10:35',
                            cells: expect.any(Array),
                        }],
                    }],
                }],
            },
            state: {
                source: 'student-timetable-v2-manual',
                activeCourseGroupFilterKeys: ['rev2-course'],
            },
        }])

        const savedTimetable = storedPayloads[0] as {
            timetable: { semesters: Array<{ weeks: Array<{ hours: Array<{ cells: Array<Record<string, unknown>> }> }> }> }
        }
        const mondayCell = savedTimetable.timetable.semesters[0].weeks[0].hours[0].cells[0] as {
            status: string
            courses: Array<{ label: string, identifier: string }>
        }

        expect(mondayCell.status).toBe('filled')
        expect(mondayCell.courses).toEqual([expect.objectContaining({
            label: 'RELIGION EVANGELISCH 2',
            identifier: 'Rev2-3R-HUB',
        })])
    })

    it('copies the admin automatic module and course selection workflow', () => {
        const source = readFileSync(overviewV2Path, 'utf8')

        expect(source).toContain("this.$route.path === '/students-timetables/create'")
        expect(source).toContain('Stundenplanerstellung')
        expect(source).toContain('Soll der Stundenplan automatisch oder manuell erzeugt werden?')
        expect(source).toContain('Welche Module sollen zur Stundenplanerstellung berücksichtigt werden?')
        expect(source).toContain('@change="chooseScheduleCreationMode(\'automatic\')"')
        expect(source).toContain("{{ scheduleCreationMode === 'automatic' ? 'Ausgewählt' : 'Automatisch wählen' }}")
        expect(source).toContain('Manuell öffnen')
        expect(source).not.toContain('Manuell erstellen')
        expect(source).toContain('overview-v2-creation-mode-card--selected')
        expect(source).toContain('overview-v2-creation-mode-options--automatic-selected')
        expect(source).toContain('Empfohlen')
        expect(source).toContain('grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);')
        expect(source).toContain('Ausgewählte Module')
        expect(source).toContain('{{ selectedModuleCount }}/{{ maximumSelectedModules }} Module')
        expect(source).toContain('{{ selectedModuleHoursLabel }}/{{ maximumSelectedModuleHours }} Std.')
        expect(source).toContain('Maximal {{ maximumSelectedModules }} Module und')
        expect(source).toContain('Keine Module ausgewählt.')
        const scheduleCreateButton = source.match(
            /<v-btn\s+class="overview-v2-schedule-create-button"[\s\S]*?<\/v-btn>/,
        )?.[0]

        expect(scheduleCreateButton).toContain('Stundenplan erstellen')
        expect(scheduleCreateButton).toContain('@click.prevent.stop="openAutomaticTimetableResults"')
        expect(scheduleCreateButton).not.toContain('readonly')
        expect(scheduleCreateButton).not.toContain('to=')
        expect(scheduleCreateButton).not.toContain('href=')
        expect(source).toContain('v-for="group in moduleSelectionGroups"')
        expect(source).toContain('@click="toggleModuleGroup(group)"')
        expect(source).toContain('@click="selectAllModulesInGroup(activeModuleSelectionGroup)"')
        expect(source).toContain('@click="deselectAllModulesInGroup(activeModuleSelectionGroup)"')
        expect(source).toContain('v-for="module in activeModuleSelectionGroup.modules"')
        expect(source).toContain('@click="openModuleCoursesDialog(module)"')
        expect(source).toContain('<v-dialog v-model="moduleCoursesDialogOpen" max-width="820" persistent scrollable>')
        expect(source).toContain('v-for="course in moduleCourseDialogCourses"')
        expect(source).toContain('@click="toggleDisplayedModuleCourse(course)"')
        expect(source).toContain('von {{ moduleCourseDialogCourses.length }} Unterrichten ausgewählt')
        expect(source).toContain('Bestätigen')
        expect(source).toContain('class="overview-v2-creation-mode-kickers"')
        expect(source).toMatch(
            /\.overview-v2-creation-mode-copy\s*\{[\s\S]*?grid-template-rows:\s*27px auto auto;/,
        )
        expect(source).toMatch(
            /\.overview-v2-creation-mode-kickers\s*\{[\s\S]*?justify-content:\s*flex-end;/,
        )
        expect(source).toMatch(
            /\.overview-v2-creation-mode-recommendation\s*\{[\s\S]*?background:\s*linear-gradient\(135deg, #fef08a, #facc15\);/,
        )
        expect(source).toMatch(
            /class="overview-v2-creation-mode-card overview-v2-creation-mode-card--manual"\s+type="button"\s+disabled>/,
        )
        expect(source).not.toContain('automatic_timetable')
        expect(source).not.toContain("manual_timetable: '1'")
        expect(source).not.toContain('/api/admin/')

        const creationModeContext = { scheduleCreationMode: null }

        OverviewV2.methods.chooseScheduleCreationMode.call(creationModeContext, 'automatic')
        OverviewV2.methods.chooseScheduleCreationMode.call(creationModeContext, 'manual')

        expect(creationModeContext.scheduleCreationMode).toBe('automatic')

        const groupContext = { activeModuleGroupKey: '' }
        const moduleGroup = { key: 'current' }

        OverviewV2.methods.toggleModuleGroup.call(groupContext, moduleGroup)
        expect(groupContext.activeModuleGroupKey).toBe('current')
        OverviewV2.methods.toggleModuleGroup.call(groupContext, moduleGroup)
        expect(groupContext.activeModuleGroupKey).toBe('')

        const course = { key: 'course-d1', keys: ['course-d1'] }
        const module = { selection_key: 'module-d1', code: 'D1', hours: 2, courses: [course] }
        const courseContext = {
            moduleCourseDialogModule: module,
            moduleCourseDialogCourses: module.courses,
            moduleSelectionGroups: [{ key: 'current', modules: [module] }],
            moduleSelectionLimitMessage: '',
            selectedCourseKeys: [] as string[],
            selectedModuleKeys: [] as string[],
            moduleCourseSelected: OverviewV2.methods.moduleCourseSelected,
        }

        OverviewV2.methods.toggleModuleCourse.call(courseContext, course)
        expect(courseContext.selectedModuleKeys).toEqual(['module-d1'])
        expect(courseContext.selectedCourseKeys).toEqual(['course-d1'])
        OverviewV2.methods.toggleModuleCourse.call(courseContext, course)
        expect(courseContext.selectedModuleKeys).toEqual([])
        expect(courseContext.selectedCourseKeys).toEqual([])

        const creationWorkspaceEnd = source.indexOf('</section>', source.indexOf('overview-v2-creation-mode-workspace'))
        const backButtonPosition = source.indexOf('class="overview-v2-creation-mode-back"')

        expect(backButtonPosition).toBeGreaterThan(creationWorkspaceEnd)
        expect(source).toContain(
            'class="overview-v2-creation-mode-actions overview-v2-creation-mode-actions--split"',
        )
        expect(source).toMatch(
            /class="overview-v2-creation-mode-back"\s+size="large"\s+color="primary"\s+variant="outlined"\s+prepend-icon="mdi-arrow-left"/,
        )
        expect(source).toMatch(
            /\.overview-v2-creation-mode-actions\s*\{[^}]*justify-content:\s*flex-end;[^}]*padding-top:\s*24px;/,
        )
        expect(source).not.toContain('min-height: calc(100vh - 84px);')
        expect(source).not.toMatch(
            /\.overview-v2-creation-mode-actions\s*\{[^}]*margin-top:\s*auto;/,
        )
    })

    it('keeps manual course choices pending until Verplanen commits and persists them', async () => {
        const course = {
            key: 'course-d1',
            timetable_entries: [{ key: 'entry-d1', weekday: 1, hour: 3 }],
        }
        const context = {
            manualPendingCourseKeys: ['course-d1'],
            manualSelectedCourseKeys: [] as string[],
            moduleCourseDialogCourses: [course],
            moduleCoursesDialogOpen: true,
            saveManualTimetableDraft: vi.fn().mockResolvedValue(true),
            closeModuleCoursesDialog() {
                this.manualPendingCourseKeys = []
                this.moduleCoursesDialogOpen = false
            },
        }

        expect(context.manualSelectedCourseKeys).toEqual([])

        await OverviewV2.methods.planManualModuleCourses.call(context)

        expect(context.manualSelectedCourseKeys).toEqual(['course-d1'])
        expect(context.manualPendingCourseKeys).toEqual([])
        expect(context.moduleCoursesDialogOpen).toBe(false)
        expect(context.saveManualTimetableDraft).toHaveBeenCalledOnce()
    })

    it('removes and persists a complete module from the displayed manual timetable', async () => {
        const removedModule = {
            selection_key: 'previous:Rev2',
            courses: [
                { key: 'course-rev2', keys: ['course-rev2', 'course-rev2-alias'] },
            ],
        }
        const context = {
            adoptionRemovedCourseKeys: ['course-existing'],
            manualPendingCourseKeys: ['course-rev2'],
            manualSelectedCourseKeys: ['course-rev2', 'course-keep'],
            savedTimetableAdoptionBase: { slots: {} },
            savedTimetableAdoptionCourseKeys: [],
            savedTimetableAdoptionModuleKeys: [],
            savedTimetableEntryKeysForModule: OverviewV2.methods.savedTimetableEntryKeysForModule,
            saveManualTimetableDraft: vi.fn().mockResolvedValue(true),
        }

        await OverviewV2.methods.removeAdoptionModule.call(context, removedModule)

        expect(context.manualSelectedCourseKeys).toEqual(['course-keep'])
        expect(context.manualPendingCourseKeys).toEqual([])
        expect(context.adoptionRemovedCourseKeys).toEqual([
            'course-existing',
            'course-rev2',
            'course-rev2-alias',
        ])
        expect(context.saveManualTimetableDraft).toHaveBeenCalledOnce()

        const filteredTimetable = OverviewV2.computed.adoptionBaseTimetable.call({
            adoptionRemovedCourseKeys: context.adoptionRemovedCourseKeys,
            selectedTimetableResult: {
                key: 'selected-timetable',
                slots: {
                    '1-3': {
                        key: 'entry-rev2',
                        courseGroup: { key: 'course-rev2' },
                        conflicts: [],
                        sameSlotEntries: [],
                    },
                    '2-4': {
                        key: 'entry-keep',
                        courseGroup: { key: 'course-keep' },
                        conflicts: [],
                        sameSlotEntries: [],
                    },
                },
            },
        })

        expect(filteredTimetable.slots).not.toHaveProperty('1-3')
        expect(filteredTimetable.slots).toHaveProperty('2-4')
    })

    it('restores only valid manual course keys for the exact student workspace timetable', async () => {
        const workspaceId = 'de7c2a59-fae1-4a61-a2d9-edbe82dc8ee4'
        const fingerprint = 'd'.repeat(64)
        const get = vi.spyOn(axios, 'get').mockResolvedValueOnce({
            data: {
                data: {
                    manual_timetable_draft: {
                        source: 'automatic',
                        fingerprint,
                        timetableKey: 'student-plan-9',
                        timetableIndex: 8,
                        selectedCourseKeys: ['course-rev2-alias', 'invalid-course'],
                        removedCourseKeys: ['course-d1', 'invalid-course'],
                    },
                },
            },
        })
        const context = {
            $route: {
                query: {
                    workspace_id: workspaceId,
                    fingerprint,
                    timetable_index: '8',
                    timetable_key: 'student-plan-9',
                },
            },
            adoptionRemovedCourseKeys: ['stale-removed'],
            isTimetableAdoptionPage: true,
            manualCatalogCourses: [
                { key: 'course-rev2', keys: ['course-rev2', 'course-rev2-alias'] },
                { key: 'course-d1', keys: ['course-d1'] },
            ],
            manualPendingCourseKeys: ['pending-course'],
            manualSelectedCourseKeys: ['stale-course'],
            manualTimetableDraftRouteSelection: OverviewV2.methods.manualTimetableDraftRouteSelection,
        }

        const restored = await OverviewV2.methods.restoreManualTimetableDraft.call(context)

        expect(restored).toBe(true)
        expect(get).toHaveBeenCalledWith(expect.stringContaining(
            `/api/homepage/students-timetables/timetable-v3/state?workspace_id=${workspaceId}`,
        ))
        expect(context.manualSelectedCourseKeys).toEqual(['course-rev2', 'course-rev2-alias'])
        expect(context.manualPendingCourseKeys).toEqual([])
        expect(context.adoptionRemovedCourseKeys).toEqual(['course-d1'])

        get.mockRestore()
    })

    it('queues the current committed manual draft for workspace persistence', async () => {
        const workspaceId = 'de7c2a59-fae1-4a61-a2d9-edbe82dc8ee4'
        const fingerprint = 'e'.repeat(64)
        const put = vi.spyOn(axios, 'put').mockResolvedValueOnce({ data: { data: {} } })
        const context = {
            $route: {
                query: {
                    workspace_id: workspaceId,
                    fingerprint,
                    timetable_index: '4',
                    timetable_key: 'student-plan-5',
                },
            },
            adoptionRemovedCourseKeys: ['course-d1'],
            isTimetableAdoptionPage: true,
            manualSelectedCourseKeys: ['course-rev2'],
            manualTimetableDraftSaveQueue: null,
            manualTimetableDraftRouteSelection: OverviewV2.methods.manualTimetableDraftRouteSelection,
        }

        const saved = await OverviewV2.methods.saveManualTimetableDraft.call(context)

        expect(saved).toBe(true)
        expect(put).toHaveBeenCalledWith(
            '/api/homepage/students-timetables/timetable-v3/state',
            {
                workspace_id: workspaceId,
                manual_timetable_draft: {
                    source: 'automatic',
                    fingerprint,
                    timetable_key: 'student-plan-5',
                    timetable_index: 4,
                    selected_course_keys: ['course-rev2'],
                    removed_course_keys: ['course-d1'],
                },
            },
        )

        put.mockRestore()
    })

    it('shows overlapping courses below only the affected manual-dialog schedule row', () => {
        const methods = OverviewV2.methods
        const candidateCourse = {
            key: 'd1-a',
            keys: ['d1-a', 'd1-b'],
            display_schedule_rows: [
                {
                    label: 'Montag · 17:50–18:35 · 1-wöchig',
                    entry_keys: ['d1-a'],
                },
                {
                    label: 'Dienstag · 17:50–18:35 · 1-wöchig',
                    entry_keys: ['d1-b'],
                },
            ],
            timetable_entries: [
                {
                    key: 'd1-a',
                    weekday: 1,
                    hour: 10,
                    starts_at: '17:50',
                    ends_at: '18:35',
                    dates: ['2026-09-07', '2026-09-14'],
                    module_code: 'D1',
                    display_label: 'D1 5RU-HUB',
                },
                {
                    key: 'd1-b',
                    weekday: 2,
                    hour: 10,
                    starts_at: '17:50',
                    ends_at: '18:35',
                    dates: ['2026-09-08'],
                    module_code: 'D1',
                    display_label: 'D1 5RU-HUB',
                },
            ],
        }
        const differentDateCourse = {
            key: 'm1-a',
            keys: ['m1-a'],
            title: 'M1 5RU-MAY',
            timetable_entries: [
                {
                    key: 'm1-a',
                    weekday: 1,
                    hour: 10,
                    starts_at: '17:50',
                    ends_at: '18:35',
                    dates: ['2026-09-21'],
                    module_code: 'M1',
                    display_label: 'M1 5RU-MAY',
                },
            ],
        }
        const stateOnlyCourse = {
            key: 'gs2-a',
            keys: ['gs2-a'],
            title: 'GS2 3C-WEL',
            timetable_entries: [
                {
                    key: 'gs2-a',
                    weekday: 1,
                    hour: 10,
                    starts_at: '18:00',
                    ends_at: '18:30',
                    dates: ['2026-09-14'],
                    module_code: 'GS2',
                    display_label: 'GS2 3C-WEL',
                },
            ],
        }
        const context = {
            moduleCoursesDialogManual: true,
            adoptionBaseTimetable: {
                slots: {
                    '1-10': {
                        key: 'saved-published-1',
                        sourceLabel: 'GEOGRAFIE 2',
                        courseGroup: {
                            key: 'saved-published-1',
                            display_label: 'GW2-5CK-HOA',
                            module_code: 'GW2',
                            weekday: 1,
                            hour: 10,
                            starts_at: '18:00',
                            ends_at: '18:30',
                            dates: ['2026-09-14'],
                        },
                    },
                },
            },
            manualSelectedCourseKeys: ['m1-a'],
            adoptionPlacedCourseKeys: ['eth3-a', 'm1-a', 'gs2-a'],
            manualPendingCourseKeys: [],
            selectedCourseKeys: [],
            manualCatalogCourses: [candidateCourse, differentDateCourse, stateOnlyCourse],
        }
        const scheduleRows = methods.courseScheduleRows(candidateCourse)

        expect(scheduleRows).toHaveLength(2)
        expect(methods.courseScheduleRowOverlapLabels.call(context, candidateCourse, scheduleRows[0]))
            .toEqual(['GW2-5CK-HOA'])
        expect(methods.courseScheduleRowOverlapLabels.call(context, candidateCourse, scheduleRows[1]))
            .toEqual([])
    })

    it('marks a complete all-modules group as not intended only when every module is excluded', () => {
        const groupNotIntended = OverviewV2.methods.manualModuleGroupNotIntended

        expect(groupNotIntended({ modules: [] })).toBe(false)
        expect(groupNotIntended({
            modules: [
                { is_intended_for_selection: false },
                { is_intended_for_selection: false },
            ],
        })).toBe(true)
        expect(groupNotIntended({
            modules: [
                { is_intended_for_selection: false },
                { is_intended_for_selection: true },
            ],
        })).toBe(false)
    })

    it('calculates and pages through student-scoped V3 timetable results', () => {
        const source = readFileSync(overviewV2Path, 'utf8')

        expect(source).toContain("this.$route.path === '/students-timetables/create/results'")
        expect(source).toContain('updateStudentTimetableV3Timetable.url()')
        expect(source).toContain('showStudentTimetableV3Timetable.url({')
        expect(source).toContain("'X-Timetable-Progress': 'stream'")
        expect(source).toContain('await consumeTimetableCalculationStream(response')
        expect(source).toContain("path: '/students-timetables/create/results'")
        expect(source).toContain('<TimetableV3PossibleTimetables')
        expect(source).toContain('@navigate="selectTimetable"')
        expect(source).toContain("updateTimetableFilter('include_saturday', $event)")
        expect(source).toContain("updateTimetableFilter('free_days', $event)")
        expect(source).toContain('Math.floor(targetIndex / TIMETABLES_PER_PAGE) + 1')
        expect(source).toContain('workspace_id: workspaceId')
        expect(source).toContain('fingerprint: activeFingerprint')
        expect(source).toContain('class="timetable-v3__creation-summary-cards"')
        expect(source).toContain('timetable-v3__creation-summary-card--automatic')
        expect(source).toContain('timetable-v3__creation-summary-card--manual')
        expect(source).toContain('timetable-v3__creation-summary-card--options')
        expect(source).toContain('class="timetable-v3__calculation-led-progress"')
        expect(source).toContain('v-for="segment in timetableCalculationProgressSegments"')
        expect(source).toContain('Array.from({ length: 20 }')
        expect(source).not.toContain('<v-progress-linear')
        expect(source).toContain('this.timetableCalculationResult?.modules')
        expect(source).toContain('Konfliktvarianten ausgeschlossen')
        expect(source).toContain('Stundenpläne werden mit den gewählten Optionen neu geladen …')
        expect(source).toContain('{{ timetableFilterOptionCountLabels.includeSaturday }}')
        expect(source).toContain('{{ timetableFilterOptionCountLabels.excludeSaturday }}')

        const manualTimetableButton = source.match(
            /<v-btn\s+class="timetable-v3__manual-timetable-button"[\s\S]*?<\/v-btn>/,
        )?.[0]

        expect(manualTimetableButton).toContain('Stundenplan übernehmen')
        expect(manualTimetableButton).toContain('@click="openStudentTimetableAdoption"')
        expect(manualTimetableButton).not.toContain('readonly')
        expect(source).not.toContain('/api/admin/')
        expect(source).not.toContain('student_code:')
        expect(source).not.toContain('planning_mode:')
    })
})
