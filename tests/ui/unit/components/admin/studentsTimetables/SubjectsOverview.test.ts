import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import StudentsTimetables from '@/pages/admin/studentsTimetables/StudentsTimetables.vue'
import SubjectsOverview from '@/pages/admin/studentsTimetables/subjectsOverview/SubjectsOverview.vue'
import TimetableV2 from '@/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue'

describe('Students timetable subjects overview', () => {
    it('keeps language alternatives together while splitting shared multi-module courses', () => {
        const methods = (SubjectsOverview as any).methods
        const ctx = {
            ...methods,
        }

        const languageCourses = methods.subjectOverviewCourseItems.call(ctx, [
            {
                id: 1,
                semester: 8,
                branch: 'gymnasial',
                json_code: 'L/F/S2',
                json_subject: 'L/F/S',
                name: 'Latein / Französisch / Spanisch 2',
                hours_per_week: 4,
            },
        ])
        const economyCourses = methods.subjectOverviewCourseItems.call(ctx, [
            {
                id: 2,
                semester: 8,
                branch: 'wirtschaftskundlich',
                json_code: 'ÖKO2/ÖKO3',
                json_subject: 'ÖKO',
                name: 'Ökonomie',
                hours_per_week: 4,
            },
        ])

        expect(languageCourses).toHaveLength(1)
        expect(languageCourses[0].hours_per_week).toBe(4)
        expect(economyCourses.map(course => course.display_code)).toEqual(['ÖKO2', 'ÖKO3'])
        expect(economyCourses.map(course => course.hours_per_week)).toEqual([2, 2])
    })

    it('shows imported separate L F S language rows in the L/F/S subject plan column', () => {
        const methods = (SubjectsOverview as any).methods
        const computed = (SubjectsOverview as any).computed
        const ctx: any = {
            ...methods,
            activeSubjectRows: [
                {
                    id: 1,
                    semester: 2,
                    branch: null,
                    json_code: 'L1',
                    json_subject: 'L',
                    name: 'Latein 1',
                    hours_per_week: 4,
                },
                {
                    id: 2,
                    semester: 2,
                    branch: null,
                    json_code: 'F1',
                    json_subject: 'F',
                    name: 'Französisch 1',
                    hours_per_week: 4,
                },
                {
                    id: 3,
                    semester: 2,
                    branch: null,
                    json_code: 'S1',
                    json_subject: 'S',
                    name: 'Spanisch 1',
                    hours_per_week: 4,
                },
            ],
        }
        ctx.subjectOverviewColumns = computed.subjectOverviewColumns.call(ctx)
        ctx.subjectOverviewSemesters = [2]

        const [row] = computed.subjectOverviewRows.call(ctx)
        const languageCell = row.cells.find(cell => cell.column.key === 'L/F/S')

        expect(languageCell.subjects.map(subject => subject.display_code)).toEqual(['F1/L1/S1*'])
        expect(languageCell.subjects.map(subject => subject.hours_per_week)).toEqual([4])
        expect(row.totals.map(total => total.value)).toEqual(['4'])
    })

    it('marks BE and ME alternatives per branch and counts each branch choice once', () => {
        const methods = (SubjectsOverview as any).methods
        const computed = (SubjectsOverview as any).computed
        const ctx: any = {
            ...methods,
            activeSubjectRows: [
                {
                    id: 1,
                    semester: 7,
                    branch: 'gymnasial',
                    json_code: 'BE1',
                    json_subject: 'BE',
                    name: 'Bildnerische Erziehung 1',
                    hours_per_week: 2,
                },
                {
                    id: 2,
                    semester: 7,
                    branch: 'gymnasial',
                    json_code: 'ME1',
                    json_subject: 'ME',
                    name: 'Musikerziehung 1',
                    hours_per_week: 2,
                },
                {
                    id: 3,
                    semester: 7,
                    branch: 'wirtschaftskundlich',
                    json_code: 'BE1',
                    json_subject: 'BE',
                    name: 'Bildnerische Erziehung 1',
                    hours_per_week: 2,
                },
                {
                    id: 4,
                    semester: 7,
                    branch: 'wirtschaftskundlich',
                    json_code: 'ME1',
                    json_subject: 'ME',
                    name: 'Musikerziehung 1',
                    hours_per_week: 2,
                },
            ],
        }
        ctx.subjectOverviewColumns = computed.subjectOverviewColumns.call(ctx)
        ctx.subjectOverviewSemesters = [7]

        const [row] = computed.subjectOverviewRows.call(ctx)
        const artCells = row.cells.filter(cell => ['BE', 'ME'].includes(cell.column.key))
        const grandTotals = computed.subjectOverviewGrandTotals.call(ctx)

        expect(artCells.flatMap(cell => cell.subjects.map(subject => subject.display_code))).toEqual(['ME1*', 'BE1*'])
        expect(row.totals.map(total => total.value)).toEqual(['2'])
        expect(grandTotals.map(total => total.value)).toEqual(['2'])
    })

    it('adds the subjects overview menu item to the module shell', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/StudentsTimetables.vue',
            'utf8',
        )

        expect(componentSource).toContain("key: 'subjects-overview'")
        expect(componentSource).toContain("key: 'imports'")
        expect(componentSource).toContain("roles: ['super_admin', 'admin', 'studentstimetables_admin', 'studentstimetables_moderator']")
        expect(componentSource).toContain("roles: ['super_admin', 'admin', 'studentstimetables_admin']")
        expect(componentSource).toContain('canAccessNavigationItem(item)')
        expect(componentSource).toContain("label: 'Stundenplan'")
        expect(componentSource).toContain("meta: 'Center'")
        expect(componentSource).toContain("label: 'Stundenplan v2'")
        expect(componentSource).toContain("meta: 'Neu'")
        expect(componentSource).toContain("label: 'Importe'")
        expect(componentSource).toContain("meta: 'Stundenplan'")
        expect(componentSource).toContain("label: 'Fächer'")
        expect(componentSource).toContain("meta: 'Überblick'")
        expect(componentSource).not.toContain("meta: 'Import'")
        expect(componentSource).not.toContain("meta: 'Tagesansicht'")
        expect(componentSource).toContain("'automatic-timetable': AUTOMATIC_TIMETABLE_OVERVIEW_PATH")
        expect(componentSource).toContain("['automatic-timetable', 'imports'].includes(key) ? 'timetable' : key")
        expect(componentSource).not.toContain("handleNavigation('automatic-timetable')")
        expect(componentSource).not.toContain('st-nav__automatic-button')
        expect(componentSource).toContain('AUTOMATIC_TIMETABLE_OVERVIEW_PATH')
        expect(componentSource).toContain('/admin/students-timetables/subjects-overview/subject-plan')
        expect(componentSource).toContain("const mainSectionKeys = ['timetable', 'timetable-v2', 'subjects-overview', 'import']")
        expect(componentSource).toContain("const TIMETABLE_OVERVIEW_PATH = '/admin/students-timetables/timetable/overview'")
        expect(componentSource).toContain("const TIMETABLE_V2_OVERVIEW_PATH = '/admin/students-timetables/timetable-v2/overview'")
        expect(componentSource).toContain("redirectMissingSection()")
        expect(componentSource).toContain("redirectLegacySection(section)")
        expect(componentSource).toContain("this.$router.replace({ path: TIMETABLE_OVERVIEW_PATH })")
        expect(componentSource).not.toContain("this.$router.replace({ path: '/admin/students-timetables' })")
        expect(componentSource).toContain('const AUTOMATIC_TIMETABLE_OVERVIEW_PATH = `${TIMETABLE_OVERVIEW_PATH}/automatic`')
        expect(componentSource).toContain('this.$router.replace({ path: AUTOMATIC_TIMETABLE_OVERVIEW_PATH })')
        expect(componentSource).toContain("activeNavigationKey === item.key")
        expect(componentSource).toContain('timetable: TIMETABLE_OVERVIEW_PATH')
        expect(componentSource).toContain("'timetable-v2': TIMETABLE_V2_OVERVIEW_PATH")
        expect(componentSource).toContain("imports: '/admin/students-timetables/timetable/imports'")
        expect(componentSource).toContain("import('./timetableV2/TimetableV2.vue')")
        expect(componentSource).toContain("import('./subjectsOverview/SubjectsOverview.vue')")
        expect(componentSource).not.toContain("import('./overview/Overview.vue')")
        expect(componentSource).not.toContain("import('./robot/RobotTimetable.vue')")
        expect(componentSource).toContain("<v-col v-if=\"main_action === 'timetable-v2'\" cols=\"12\">")
        expect(componentSource).toContain('<TimetableV2 />')
        expect(componentSource).toContain("Import v-if=\"main_action === 'import'\"")
        expect(componentSource).not.toContain("RobotTimetable v-if=\"main_action === 'robot'\"")
        expect(componentSource).toContain("SubjectsOverview v-if=\"main_action === 'subjects-overview'\"")
        expect(componentSource).not.toContain("Overview v-if=\"main_action === 'overview'\"")
        expect(componentSource.indexOf("key: 'timetable'"))
            .toBeLessThan(componentSource.indexOf("key: 'timetable-v2'"))
        expect(componentSource.indexOf("key: 'timetable-v2'"))
            .toBeLessThan(componentSource.indexOf("key: 'imports'"))
        expect(componentSource.indexOf("key: 'imports'"))
            .toBeLessThan(componentSource.indexOf("key: 'subjects-overview'"))
    })

    it('keeps all automatic timetable steps in the focused automatic shell route', () => {
        const computed = (StudentsTimetables as any).computed
        const ctx: any = {
            $route: {
                path: '/admin/students-timetables/timetable/overview/automatic',
            },
        }

        expect(computed.automaticTimetableRouteActive.call(ctx)).toBe(true)

        ctx.$route.path = '/admin/students-timetables/timetable/overview/automatic/result'

        expect(computed.automaticTimetableRouteActive.call(ctx)).toBe(true)

        ctx.$route.path = '/admin/students-timetables/timetable/overview'

        expect(computed.automaticTimetableRouteActive.call(ctx)).toBe(false)
    })

    it('writes the default module timetable step into the URL', () => {
        const methods = (StudentsTimetables as any).methods
        const replace = vi.fn()
        const ctx: any = {
            $route: {
                params: {},
            },
            $router: {
                replace,
            },
            main_action: 'subjects-overview',
        }

        expect(methods.redirectMissingSection.call(ctx)).toBe(true)
        expect(ctx.main_action).toBe('timetable')
        expect(replace).toHaveBeenCalledWith({ path: '/admin/students-timetables/timetable/overview' })
    })

    it('opens the timetable navigation on the canonical overview URL', () => {
        const methods = (StudentsTimetables as any).methods
        const push = vi.fn()
        const ctx: any = {
            $router: {
                push,
            },
            main_action: 'subjects-overview',
        }

        methods.handleNavigation.call(ctx, 'timetable')

        expect(ctx.main_action).toBe('timetable')
        expect(push).toHaveBeenCalledWith({ path: '/admin/students-timetables/timetable/overview' })
    })

    it('opens the standalone timetable v2 page from the module navigation', () => {
        const methods = (StudentsTimetables as any).methods
        const push = vi.fn()
        const ctx: any = {
            $router: {
                push,
            },
            main_action: 'timetable',
        }

        methods.handleNavigation.call(ctx, 'timetable-v2')

        expect(ctx.main_action).toBe('timetable-v2')
        expect(push).toHaveBeenCalledWith({ path: '/admin/students-timetables/timetable-v2/overview' })
    })

    it('starts the standalone timetable v2 page with student selection cards and dialog', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue',
            'utf8',
        )

        expect(componentSource).toContain('<v-row dense align="stretch">')
        expect(componentSource).toContain('v-if="startCardVisible"')
        expect(componentSource).toContain('<v-card-title>Start</v-card-title>')
        expect(componentSource).toContain('Mit Studierenden')
        expect(componentSource).toContain('Ohne Studierenden')
        expect(componentSource).toContain('@click="startWithStudent"')
        expect(componentSource).toContain('@click="startWithoutStudent"')
        expect(componentSource).toContain('v-if="restartCardVisible"')
        expect(componentSource).toContain('Neustart')
        expect(componentSource).toContain('@click="restartTimetableV2"')
        expect(componentSource).toContain('Weiter')
        expect(componentSource).not.toContain('Automatischer Stundenplan')
        expect(componentSource).toContain('append-icon="mdi-arrow-right"')
        expect(componentSource).toContain('students-timetable-v2-restart-card__automatic-button')
        expect(componentSource).toContain('justify-content: space-between')
        expect(componentSource).toContain('margin-left: auto')
        expect(componentSource).toContain('@click="openAutomaticTimetable"')
        expect(componentSource).toContain('openAutomaticTimetable()')
        expect(componentSource).toContain("this.$router.push({ path: '/admin/students-timetables/timetable/overview/automatic' })")
        expect(componentSource).toContain('v-if="studentCardVisible"')
        expect(componentSource).toContain('v-if="courseCardsVisible"')
        expect(componentSource).toContain('v-if="missingCourseCardVisible"')
        expect(componentSource).toContain('missingCourseCardVisible()')
        expect(componentSource).toContain('courseCardMdColumns()')
        expect(componentSource).toContain('class="students-timetable-v2-row-break"')
        expect(componentSource).toContain('flex-basis: 100%')
        expect(componentSource).toContain('v-if="withoutStudentBackgroundVisible"')
        expect(componentSource).toContain('mdi-account-off-outline')
        expect(componentSource).toContain('<span>Ohne Studierenden</span>')
        expect(componentSource).toContain('students-timetable-v2-without-student-watermark')
        expect(componentSource).toContain('color: rgba(15, 23, 42, 0.12)')
        expect(componentSource).toContain('cols="12" md="6" :offset-md="selectionCardOffsetMd" class="students-timetable-v2-card-column"')
        expect(componentSource).toContain('withoutStudentBackgroundVisible()')
        expect(componentSource).toContain('selectionCardOffsetMd()')
        expect(componentSource).toContain('cols="12" :md="courseCardMdColumns" class="students-timetable-v2-card-column"')
        expect(componentSource).toContain('<v-card-title>Studierende</v-card-title>')
        expect(componentSource).toContain('<v-card-title class="students-timetable-v2-course-card-title">')
        expect(componentSource).toContain('<span>Fehlende Kurse</span>')
        expect(componentSource).toContain('<span>Vorgesehene Kurse</span>')
        expect(componentSource).toContain('<span>Zusätzliche Kurse</span>')
        expect(componentSource).toContain('{{ storedTimetableStudentLabel }}')
        expect(componentSource).toContain('icon="mdi-pencil"')
        expect(componentSource).toContain('icon="mdi-close-circle-outline"')
        expect(componentSource).toContain('@click.stop="openStudentDialog"')
        expect(componentSource).toContain('@click.stop="clearStoredTimetableStudent"')
        expect(componentSource).toContain('Abgeschlossene Kurse')
        expect(componentSource).toContain('Fehlende Kurse')
        expect(componentSource).toContain('storedCompletedCourseItems')
        expect(componentSource).toContain('storedMissingCourseItems')
        expect(componentSource).toContain('storedMissingCourseCardItems')
        expect(componentSource).toContain('storedPlannedCourseItems')
        expect(componentSource).toContain('storedAdditionalCourseItems')
        expect(componentSource).toContain('selectedMissingCourseCardItems')
        expect(componentSource).toContain('selectedPlannedCourseItems')
        expect(componentSource).toContain('selectedAdditionalCourseItems')
        expect(componentSource).toContain('storedMissingCourseCardSummary.countLabel')
        expect(componentSource).toContain('storedMissingCourseCardSummary.hoursLabel')
        expect(componentSource).toContain('hoursMeta')
        expect(componentSource).toContain('subjectRowHoursForCourseCode(code)')
        expect(componentSource).toContain('storedPlannedCourseSummary.countLabel')
        expect(componentSource).toContain('storedPlannedCourseSummary.hoursLabel')
        expect(componentSource).toContain('storedAdditionalCourseSummary.countLabel')
        expect(componentSource).toContain('storedAdditionalCourseSummary.hoursLabel')
        expect(componentSource).toContain('@click="toggleCourseItem(course, \'missing\')"')
        expect(componentSource).toContain('@click="toggleCourseItem(course, \'planned\')"')
        expect(componentSource).toContain('@click="toggleCourseItem(course, \'additional\')"')
        expect(componentSource).toContain('<v-icon v-if="courseItemSelected(course, \'missing\')" icon="mdi-check" size="14" />')
        expect(componentSource).toContain('<v-icon v-if="courseItemSelected(course, \'planned\')" icon="mdi-check" size="14" />')
        expect(componentSource).toContain('<v-icon v-if="courseItemSelected(course, \'additional\')" icon="mdi-check" size="14" />')
        expect(componentSource).toContain('students-timetable-v2-completed-courses__item--deselected')
        expect(componentSource).toContain('studentCompletedCoursesLoading')
        expect(componentSource).toContain('studentCompletedCoursesError')
        expect(componentSource).toContain('loadStoredStudentOverview(this.storedTimetableStudentCode)')
        expect(componentSource).toContain('/api/admin/students-timetables/robot/student-overview')
        expect(componentSource).toContain('studentOverviewSelectionPayload()')
        expect(componentSource).toContain('studentOverviewActiveRequestKey')
        expect(componentSource).toContain('studentOverviewLoadedRequestKey')
        expect(componentSource).toContain('studentOverviewRequestKey(')
        expect(componentSource).toContain('studentOverviewSelectionPayloadForSelection(selection)')
        expect(componentSource).toContain('overviewStudentCourseHistoryFromSummary(overviewSummary)')
        expect(componentSource).toContain('completed: this.completedCourseItemsFromApi(overviewSummary?.completed_courses || [])')
        expect(componentSource).toContain('failed: this.missingCourseItemsFromApi(overviewSummary?.completed_courses || [])')
        expect(componentSource).toContain('missing: this.normalizedOverviewCourseItems(automaticMissingCourses || overviewSummary?.missing_courses || [])')
        expect(componentSource).toContain('planned: this.normalizedOverviewCourseItems(automaticPlannedCourses || overviewSummary?.proposed_courses || [])')
        expect(componentSource).toContain('additional: this.normalizedOverviewCourseItems(overviewSummary?.additional_courses || [])')
        expect(componentSource).toContain('normalizedCompletedCourseItems(courses)')
        expect(componentSource).toContain('normalizedMissingCourseItems(courses)')
        expect(componentSource).toContain('normalizedOverviewCourseItems(courses)')
        expect(componentSource).toContain('uniqueCourseItems(courses)')
        expect(componentSource).toContain('completedCourseGradeIsAccepted(grade)')
        expect(componentSource).toContain('missingCourseGradeIsAccepted(grade)')
        expect(componentSource).toContain("!['5', 'N'].includes(normalizedGrade)")
        expect(componentSource).toContain("['5', 'N'].includes(normalizedGrade)")
        expect(componentSource).toContain('timetableV2SelectionWithCourseDefaults(')
        expect(componentSource).toContain('selectedStudentCourseHistoryDefaults(completedCourses)')
        expect(componentSource).toContain('studentCourseCodeSet(courses)')
        expect(componentSource).toContain('inferredSelectionOptionFromCourseCodes(')
        expect(componentSource).toContain('inferredBranchFromCourseCodes(courseCodes)')
        expect(componentSource).toContain("aliases: ['INF', 'OKO', 'OEKO', 'OEK', 'WIKU', 'BWL', 'RW', 'WR']")
        expect(componentSource).toContain('selectionCourseAliases(value)')
        expect(componentSource).toContain('<v-dialog v-model="studentDialogOpen" persistent max-width="560">')
        expect(componentSource).toContain('Student bearbeiten')
        expect(componentSource).toContain('Student suchen')
        expect(componentSource).toContain('Kein Student')
        expect(componentSource).toContain('filteredStudentResults')
        expect(componentSource).toContain('Keine Schüler gefunden')
        expect(componentSource).toContain('Mindestens 2 Zeichen eingeben')
        expect(componentSource).toContain('updateStudentSelection')
        expect(componentSource).toContain('/api/admin/students-timetables/robot/students')
        expect(componentSource).toContain("const TIMETABLE_STORAGE_KEY_PREFIX = 'students-timetables:overview:last-timetable'")
        expect(componentSource).toContain('storedTimetableStudentLabel()')
        expect(componentSource).toContain('const religion = String(this.storedTimetableStudentContext?.student?.religion || \'\').trim()')
        expect(componentSource).toContain("return [label, religion].filter(Boolean).join(' · ')")
        expect(componentSource).not.toContain('storedTimetableStudentReligionLabel()')
        expect(componentSource).not.toContain('students-timetable-v2-student-context__meta')
        expect(componentSource).toContain('startCardVisible()')
        expect(componentSource).toContain('studentCardVisible()')
        expect(componentSource).toContain('courseCardsVisible()')
        expect(componentSource).toContain('selectionCardVisible()')
        expect(componentSource).toContain('withoutStudentBackgroundVisible()')
        expect(componentSource).toContain('restartCardVisible()')
        expect(componentSource).toContain('subjectRows: []')
        expect(componentSource).toContain('loadSubjectRows()')
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/subjects-overview-settings')")
        expect(componentSource).toContain("timetableStartMode: ''")
        expect(componentSource).toContain("this.timetableStartMode = 'student'")
        expect(componentSource).toContain("this.timetableStartMode = 'without-student'")
        expect(componentSource).toContain('restartTimetableV2()')
        expect(componentSource).toContain('transferredStudentContext')
        expect(componentSource).toContain('transferredStudentContextFromRobotStudent(student)')
        expect(componentSource).toContain('studentOptionTitle(student)')
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/robot/students')")
        expect(componentSource).toContain('<v-card-title>Auswahl</v-card-title>')
        expect(componentSource).toContain('v-if="selectionCardVisible"')
        expect(componentSource).toContain('v-for="item in storedTimetableSelectionSummary"')
        expect(componentSource).toContain('<span>{{ item.label }}</span>')
        expect(componentSource).toContain('v-if="item.options?.length"')
        expect(componentSource).toContain('v-for="option in item.options"')
        expect(componentSource).toContain("selectionOptionSelected(item, option) ? 'success' : 'secondary'")
        expect(componentSource).toContain("selectionOptionSelected(item, option) ? 'flat' : 'outlined'")
        expect(componentSource).toContain('@click="selectTimetableSelectionOption(item.key, option.value)"')
        expect(componentSource).toContain("class=\"students-timetable-v2-selection__value\"")
        expect(componentSource).toContain("'students-timetable-v2-selection__value--unknown': !item.known")
        expect(componentSource).toContain('{{ item.value }}')
        expect(componentSource).toContain('Keine vorgesehenen Kurse gefunden.')
        expect(componentSource).toContain('Kein Student ausgewählt')
        expect(componentSource).toContain('storedTimetableSelectionSummary()')
        expect(componentSource).toContain('const currentSelection = this.storedTimetableStateForSaving()?.selection || {}')
        expect(componentSource).toContain("label: 'Semester'")
        expect(componentSource).toContain('const semesterLabel = this.storedTimetableStudentContext?.student?.semesterLabel')
        expect(componentSource).toContain('options: this.storedTimetableStudentContext ? [] : this.semesterOptions()')
        expect(componentSource).toContain('semesterOptions()')
        expect(componentSource).toContain("label: 'Ethik / Religion'")
        expect(componentSource).toContain('options: this.religionOptionsForSelectedStudent()')
        expect(componentSource).toContain("label: 'Sprache'")
        expect(componentSource).toContain('options: this.languageOptions()')
        expect(componentSource).toContain("label: 'Zweig'")
        expect(componentSource).toContain('options: this.branchOptions()')
        expect(componentSource).toContain("label: 'ME / BE'")
        expect(componentSource).toContain('options: this.artsSubjectOptions()')
        expect(componentSource).toContain('storedTimetableV2Selection()')
        expect(componentSource).toContain('courseSelectionOverrides()')
        expect(componentSource).toContain('timetableV2Selection: {}')
        expect(componentSource).toContain('selectTimetableSelectionOption(key, value)')
        expect(componentSource).toContain('selectionOptionSelected(item, option)')
        expect(componentSource).toContain('courseItemSelected(course, courseGroup)')
        expect(componentSource).toContain('toggleCourseItem(course, courseGroup)')
        expect(componentSource).toContain('courseSelectionKey(course, courseGroup)')
        expect(componentSource).toContain('const timetableV2Selection = { ...this.storedTimetableV2Selection }')
        expect(componentSource).toContain('timetableV2Selection[key] = null')
        expect(componentSource).toContain('timetableV2Selection[key] = value')
        expect(componentSource).toContain('knownSelectionValue(value = null)')
        expect(componentSource).toContain('selectionValueIsKnown(value)')
        expect(componentSource).toContain("return normalizedValue || '--'")
        expect(componentSource).toContain('known: this.selectionValueIsKnown(semesterLabel || semesterValue)')
        expect(componentSource).not.toContain('storedTimetableSelection()')
        expect(componentSource).toContain("title: 'ETH - Ethik'")
        expect(componentSource).toContain('religionOptionsForSelectedStudent()')
        expect(componentSource).toContain('storedTimetableStudentReligion()')
        expect(componentSource).toContain('studentReligionMatchesNoConfession(religion)')
        expect(componentSource).toContain('studentReligionOptionValue(religion)')
        expect(componentSource).toContain('studentReligionOptionAliases()')
        expect(componentSource).toContain("'ob'")
        expect(componentSource).toContain("'ohnebekenntnis'")
        expect(componentSource).toContain("'evangab'")
        expect(componentSource).toContain("'islamisch'")
        expect(componentSource).toContain("'roemkath'")
        expect(componentSource).toContain("'orth'")
        expect(componentSource).toContain("title: 'L - Latein'")
        expect(componentSource).toContain("title: 'Wirtschaftskundlicher Zweig'")
        expect(componentSource).toContain("title: 'ME - Musikerziehung'")
        expect(componentSource).not.toContain('@/pages/admin/studentsTimetables/timetable/Timetable.vue')
        expect(componentSource).not.toContain('@/pages/admin/studentsTimetables/overview/Overview.vue')
    })

    it('shows timetable v2 start state before choosing a student flow', () => {
        const computed = (TimetableV2 as any).computed
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
            timetableStartMode: '',
            storedTimetableStudentContext: null,
            storedTimetableV2Selection: {},
            defaultStoredTimetableState: vi.fn(() => ({
                selection: {
                    religion: 'ETH',
                    language: 'L',
                    branch: 'wirtschaftskundlich',
                    artsSubject: 'ME',
                },
                timetableV2Selection: {},
                transferredStudentContext: null,
            })),
            storedTimetableStateForSaving: vi.fn(() => null),
            saveStoredTimetableState: vi.fn(),
            loadSubjectRows: vi.fn(),
            studentOverviewActiveRequestKey: 'active',
            studentOverviewLoadedRequestKey: 'loaded',
            studentCompletedCoursesLoading: true,
        }
        Object.defineProperty(ctx, 'noStudentSelectedSemester', {
            get() {
                return computed.noStudentSelectedSemester.call(ctx)
            },
        })

        expect(computed.startCardVisible.call(ctx)).toBe(true)
        expect(computed.studentCardVisible.call(ctx)).toBe(false)
        expect(computed.selectionCardVisible.call(ctx)).toBe(false)
        expect(computed.withoutStudentBackgroundVisible.call(ctx)).toBe(false)
        expect(computed.selectionCardOffsetMd.call(ctx)).toBe(0)
        expect(computed.courseCardsVisible.call(ctx)).toBe(false)
        expect(computed.restartCardVisible.call(ctx)).toBe(false)

        methods.startWithStudent.call(ctx)

        expect(ctx.timetableStartMode).toBe('student')
        expect(computed.startCardVisible.call(ctx)).toBe(false)
        expect(computed.studentCardVisible.call(ctx)).toBe(true)
        expect(computed.selectionCardVisible.call(ctx)).toBe(false)
        expect(computed.withoutStudentBackgroundVisible.call(ctx)).toBe(false)
        expect(computed.selectionCardOffsetMd.call(ctx)).toBe(0)
        expect(computed.courseCardsVisible.call(ctx)).toBe(false)
        expect(computed.restartCardVisible.call(ctx)).toBe(true)

        methods.startWithoutStudent.call(ctx)

        expect(ctx.timetableStartMode).toBe('without-student')
        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith({
            selection: {
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            timetableV2Selection: {
                semester: 1,
            },
            transferredStudentContext: null,
        })
        expect(ctx.loadSubjectRows).toHaveBeenCalled()

        ctx.storedTimetableV2Selection = {
            semester: 1,
        }

        expect(computed.startCardVisible.call(ctx)).toBe(false)
        expect(computed.studentCardVisible.call(ctx)).toBe(false)
        expect(computed.selectionCardVisible.call(ctx)).toBe(true)
        expect(computed.withoutStudentBackgroundVisible.call(ctx)).toBe(true)
        expect(computed.selectionCardOffsetMd.call(ctx)).toBe(0)
        expect(computed.courseCardsVisible.call(ctx)).toBe(true)
        expect(computed.restartCardVisible.call(ctx)).toBe(true)

        ctx.storedTimetableV2Selection = {
            semester: 3,
        }

        expect(computed.courseCardsVisible.call(ctx)).toBe(true)

        ctx.storedTimetableStudentContext = { student: { studentCode: '100' } }

        expect(computed.startCardVisible.call(ctx)).toBe(false)
        expect(computed.studentCardVisible.call(ctx)).toBe(true)
        expect(computed.selectionCardVisible.call(ctx)).toBe(true)
        expect(computed.withoutStudentBackgroundVisible.call(ctx)).toBe(false)
        expect(computed.selectionCardOffsetMd.call(ctx)).toBe(0)
        expect(computed.courseCardsVisible.call(ctx)).toBe(true)
        expect(computed.restartCardVisible.call(ctx)).toBe(true)

        methods.clearStoredTimetableStudent.call(ctx)

        expect(ctx.timetableStartMode).toBe('')
        expect(ctx.studentOverviewActiveRequestKey).toBe('')
        expect(ctx.studentOverviewLoadedRequestKey).toBe('')
        expect(ctx.studentCompletedCoursesLoading).toBe(false)

        ctx.timetableStartMode = 'without-student'
        ctx.studentDialogOpen = true
        ctx.studentSearch = 'Schroll'
        ctx.studentSelectionDraft = { studentCode: '100' }
        ctx.studentCompletedCoursesError = 'Fehler'
        ctx.studentCompletedCoursesLoading = true
        ctx.studentOverviewActiveRequestKey = 'active'
        ctx.studentOverviewLoadedRequestKey = 'loaded'

        methods.restartTimetableV2.call(ctx)

        expect(ctx.timetableStartMode).toBe('')
        expect(ctx.studentDialogOpen).toBe(false)
        expect(ctx.studentSearch).toBe('')
        expect(ctx.studentSelectionDraft).toEqual({ studentCode: null })
        expect(ctx.studentCompletedCoursesError).toBe('')
        expect(ctx.studentCompletedCoursesLoading).toBe(false)
        expect(ctx.studentOverviewActiveRequestKey).toBe('')
        expect(ctx.studentOverviewLoadedRequestKey).toBe('')
        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith({
            selection: {
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            timetableV2Selection: {},
            transferredStudentContext: null,
        })
    })

    it('opens the automatic timetable from the timetable v2 bottom menu card', () => {
        const methods = (TimetableV2 as any).methods
        const push = vi.fn()
        const ctx: any = {
            $router: {
                push,
            },
        }

        methods.openAutomaticTimetable.call(ctx)

        expect(push).toHaveBeenCalledWith({ path: '/admin/students-timetables/timetable/overview/automatic' })
    })

    it('makes semester selectable in timetable v2 no-student mode', () => {
        const computed = (TimetableV2 as any).computed
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
            storedTimetableStudentContext: null,
            storedTimetableV2Selection: {
                semester: 3,
            },
        }

        const summary = computed.storedTimetableSelectionSummary.call(ctx)
        const semester = summary.find((item: Record<string, any>) => item.key === 'semester')

        expect(semester.value).toBe('3')
        expect(semester.known).toBe(true)
        expect(semester.options).toHaveLength(8)
        expect(semester.options[0]).toEqual({ title: 'Semester 1', value: 1 })
        expect(semester.options[2]).toEqual({ title: 'Semester 3', value: 3 })
    })

    it('calculates timetable v2 no-student planned and additional courses after selecting a semester', () => {
        const computed = (TimetableV2 as any).computed
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
            storedTimetableStudentContext: null,
            storedTimetableV2Selection: {
                semester: 1,
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            subjectRows: [
                {
                    id: 1,
                    semester: 1,
                    branch: 'common',
                    json_code: 'D1',
                    json_subject: 'D',
                    name: 'Deutsch 1',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 2,
                    semester: 1,
                    branch: 'common',
                    json_code: 'R/ET1',
                    json_subject: 'R/ET',
                    name: 'Religion / Ethik 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 3,
                    semester: 1,
                    branch: 'common',
                    json_code: 'L/F/S1',
                    json_subject: 'L/F/S',
                    name: 'Sprache 1',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 4,
                    semester: 2,
                    branch: 'common',
                    json_code: 'D2',
                    json_subject: 'D',
                    name: 'Deutsch 2',
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 5,
                    semester: 2,
                    branch: 'common',
                    json_code: 'D3',
                    json_subject: 'D',
                    name: 'Deutsch 3',
                    hours_per_week: 3,
                    is_active: true,
                },
            ],
        }
        Object.defineProperty(ctx, 'noStudentSelectedSemester', {
            get() {
                return computed.noStudentSelectedSemester.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'effectiveTimetableV2Selection', {
            get() {
                return computed.effectiveTimetableV2Selection.call(ctx)
            },
        })

        ctx.storedTimetableV2Selection = {
            semester: 1,
        }

        expect(computed.noStudentPlannedCourseItems.call(ctx).map((course: Record<string, string>) => course.label))
            .toEqual(['D1'])
        expect(computed.noStudentAdditionalCourseItems.call(ctx).map((course: Record<string, string>) => course.label))
            .toEqual(['D2'])

        ctx.storedTimetableV2Selection = {
            semester: 1,
            religion: 'ETH',
            language: 'L',
            branch: 'wirtschaftskundlich',
            artsSubject: 'ME',
        }

        expect(computed.noStudentPlannedCourseItems.call(ctx).map((course: Record<string, string>) => course.label))
            .toEqual(['D1', 'ETH1', 'L1'])
        expect(computed.noStudentAdditionalCourseItems.call(ctx).map((course: Record<string, string>) => course.label))
            .toEqual(['D2'])
    })

    it('filters timetable v2 religion options from flexible imported religion values', () => {
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
            storedTimetableStudentContext: null,
        }
        const visibleReligionTitles = (religion: string) => {
            ctx.storedTimetableStudentContext = {
                student: {
                    religion,
                },
            }

            return methods.religionOptionsForSelectedStudent.call(ctx).map(option => option.title)
        }

        expect(visibleReligionTitles('evang. A.B.')).toEqual(['ETH - Ethik', 'Rev - Religion evangelisch'])
        expect(visibleReligionTitles('islam.')).toEqual(['ETH - Ethik', 'Ris - Religion Islam'])
        expect(visibleReligionTitles('röm.-kath.')).toEqual(['ETH - Ethik', 'Rk - Religion katholisch'])
        expect(visibleReligionTitles('orth')).toEqual(['ETH - Ethik', 'Ror - Religion orthodox'])
        expect(visibleReligionTitles('orth.')).toEqual(['ETH - Ethik', 'Ror - Religion orthodox'])
        expect(visibleReligionTitles('griech.-orth.')).toEqual(['ETH - Ethik', 'Ror - Religion orthodox'])
        expect(visibleReligionTitles('o.B.')).toEqual([
            'ETH - Ethik',
            'Rev - Religion evangelisch',
            'Ris - Religion Islam',
            'Rk - Religion katholisch',
            'Ror - Religion orthodox',
        ])
    })

    it('shows only timetable v2 completed courses with accepted grades', () => {
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
        }

        expect(methods.normalizedCompletedCourseItems.call(ctx, [
            { subject: 'L1', grade: '1' },
            { subject: 'F1', grade: '5' },
            { subject: 'ETH1', grade: 'B' },
            { subject: 'Rk1', grade: 'N' },
            { subject: 'BE1', grade: '' },
            { code: 'ME1', meta: '4' },
        ])).toEqual([
            {
                key: 'completed-L1-1',
                code: 'L1',
                label: 'L1',
                meta: '1',
            },
            {
                key: 'completed-ETH1-B',
                code: 'ETH1',
                label: 'ETH1',
                meta: 'B',
            },
            {
                key: 'completed-ME1-4',
                code: 'ME1',
                label: 'ME1',
                meta: '4',
            },
        ])
    })

    it('shows only timetable v2 missing courses with five or n grades', () => {
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
        }

        expect(methods.normalizedMissingCourseItems.call(ctx, [
            { subject: 'L1', grade: '1' },
            { subject: 'F1', grade: '5' },
            { subject: 'ETH1', grade: 'B' },
            { subject: 'Rk1', grade: 'N' },
            { subject: 'BE1', grade: '' },
            { code: 'ME1', meta: '4' },
        ])).toEqual([
            {
                key: 'missing-F1-5',
                code: 'F1',
                label: 'F1',
                meta: '5',
            },
            {
                key: 'missing-Rk1-N',
                code: 'Rk1',
                label: 'Rk1',
                meta: 'N',
            },
        ])
    })

    it('deduplicates timetable v2 course items without failing during render', () => {
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
        }

        expect(methods.uniqueCourseItems.call(ctx, [
            { key: 'ETH2', code: 'ETH2', label: 'ETH2' },
            { key: 'ETH2', code: 'ETH2', label: 'ETH2 duplicate' },
            { code: 'D3', label: 'D3' },
            { code: 'D3', label: 'D3 duplicate' },
            { label: '' },
        ])).toEqual([
            { key: 'ETH2', code: 'ETH2', label: 'ETH2' },
            { code: 'D3', label: 'D3' },
        ])
    })

    it('sorts timetable v2 additional course items ascending', () => {
        const computed = (TimetableV2 as any).computed
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
            storedTimetableStudentContext: {
                courses: {
                    additional: [
                        { code: 'D10', label: 'D10' },
                        { code: 'D2', label: 'D2' },
                        { code: 'BE1', label: 'BE1' },
                        { code: 'D2', label: 'D2 duplicate' },
                    ],
                },
            },
        }

        expect(computed.storedAdditionalCourseItems.call(ctx).map((course: Record<string, string>) => course.label))
            .toEqual(['BE1', 'D2', 'D10'])
    })

    it('summarizes timetable v2 missing, planned, and additional course counts and hours', () => {
        const computed = (TimetableV2 as any).computed
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
            storedTimetableStudentContext: {
                courses: {
                    failed: [
                        { code: 'BU1', label: 'BU1', meta: '5' },
                        { code: 'BU2', label: 'BU2', meta: '5' },
                    ],
                    missing: [
                        { code: 'ETH2', label: 'ETH2', hours: 2 },
                        { code: 'BU1', label: 'BU1', hours: 3 },
                    ],
                    planned: [
                        { code: 'D3', label: 'D3', hours_label: '3 Std.' },
                    ],
                    additional: [
                        { code: 'INF2', label: 'INF2', hours: 2 },
                        { code: 'D10', label: 'D10', meta: '4,5 Std.' },
                    ],
                },
            },
            storedTimetableV2Selection: {},
            subjectRows: [
                {
                    json_code: 'BU2',
                    json_subject: 'BU',
                    hours_per_week: 4,
                    is_active: true,
                },
            ],
        }
        Object.defineProperty(ctx, 'courseSelectionOverrides', {
            get() {
                return computed.courseSelectionOverrides.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'storedPlannedCourseItems', {
            get() {
                return computed.storedPlannedCourseItems.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'storedMissingCourseItems', {
            get() {
                return computed.storedMissingCourseItems.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'storedMissingCourseCardItems', {
            get() {
                return computed.storedMissingCourseCardItems.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'storedAdditionalCourseItems', {
            get() {
                return computed.storedAdditionalCourseItems.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'selectedMissingCourseCardItems', {
            get() {
                return computed.selectedMissingCourseCardItems.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'selectedPlannedCourseItems', {
            get() {
                return computed.selectedPlannedCourseItems.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'selectedAdditionalCourseItems', {
            get() {
                return computed.selectedAdditionalCourseItems.call(ctx)
            },
        })

        expect(computed.storedMissingCourseCardSummary.call(ctx)).toMatchObject({
            count: 2,
            hours: 7,
            countLabel: '2 Kurse',
            hoursLabel: '7 Std.',
        })
        expect(computed.storedMissingCourseCardItems.call(ctx)[0]).toMatchObject({
            code: 'BU1',
            meta: '5',
            hours: 3,
            hoursMeta: '3 Std.',
        })
        expect(computed.storedMissingCourseCardItems.call(ctx)[1]).toMatchObject({
            code: 'BU2',
            meta: '5',
            hours: 4,
            hoursMeta: '4 Std.',
        })
        expect(computed.storedPlannedCourseSummary.call(ctx)).toMatchObject({
            count: 3,
            hours: 8,
            countLabel: '3 Kurse',
            hoursLabel: '8 Std.',
        })
        expect(computed.storedAdditionalCourseSummary.call(ctx)).toMatchObject({
            count: 2,
            hours: 6.5,
            countLabel: '2 Kurse',
            hoursLabel: '6,5 Std.',
        })

        ctx.storedTimetableV2Selection = {
            courseSelections: {
                'missing:BU1': false,
                'planned:D3': false,
                'additional:D10': false,
            },
        }

        expect(computed.storedMissingCourseCardSummary.call(ctx)).toMatchObject({
            count: 1,
            hours: 4,
            countLabel: '1 Kurs',
            hoursLabel: '4 Std.',
        })
        expect(computed.storedPlannedCourseSummary.call(ctx)).toMatchObject({
            count: 2,
            hours: 5,
            countLabel: '2 Kurse',
            hoursLabel: '5 Std.',
        })
        expect(computed.storedAdditionalCourseSummary.call(ctx)).toMatchObject({
            count: 1,
            hours: 2,
            countLabel: '1 Kurs',
            hoursLabel: '2 Std.',
        })
    })

    it('persists timetable v2 course toggle overrides', () => {
        const computed = (TimetableV2 as any).computed
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
            storedTimetableStudentContext: null,
            storedTimetableV2Selection: {
                semester: 1,
            },
            defaultStoredTimetableState: vi.fn(() => ({
                selection: {
                    religion: 'ETH',
                    language: 'L',
                    branch: 'wirtschaftskundlich',
                    artsSubject: 'ME',
                },
                timetableV2Selection: {},
                transferredStudentContext: null,
            })),
            storedTimetableStateForSaving: vi.fn(() => ({
                selection: {
                    religion: 'ETH',
                    language: 'L',
                    branch: 'wirtschaftskundlich',
                    artsSubject: 'ME',
                },
                timetableV2Selection: {
                    semester: 1,
                },
                transferredStudentContext: null,
            })),
            saveStoredTimetableState: vi.fn((state) => {
                ctx.storedTimetableV2Selection = state.timetableV2Selection
            }),
        }
        Object.defineProperty(ctx, 'courseSelectionOverrides', {
            get() {
                return computed.courseSelectionOverrides.call(ctx)
            },
        })

        expect(methods.courseItemSelected.call(ctx, { code: 'D1', label: 'D1' }, 'planned')).toBe(true)

        methods.toggleCourseItem.call(ctx, { code: 'D1', label: 'D1' }, 'planned')

        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: {
                semester: 1,
                courseSelections: {
                    'planned:D1': false,
                },
            },
            transferredStudentContext: null,
        }))
        expect(methods.courseItemSelected.call(ctx, { code: 'D1', label: 'D1' }, 'planned')).toBe(false)

        methods.toggleCourseItem.call(ctx, { code: 'D1', label: 'D1' }, 'planned')

        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: {
                semester: 1,
            },
            transferredStudentContext: null,
        }))
        expect(methods.courseItemSelected.call(ctx, { code: 'D1', label: 'D1' }, 'planned')).toBe(true)
    })

    it('builds timetable v2 course history from the v1 student overview summary', () => {
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
        }

        expect(methods.overviewStudentCourseHistoryFromSummary.call(ctx, {
            completed_courses: [
                { subject: 'ETH1', grade: '1' },
                { subject: 'S1', grade: '5' },
            ],
            automatic_course_selection: {
                sections: [
                    {
                        key: 'missing',
                        items: [{ key: 'ETH2', code: 'ETH2', hours: 2 }],
                    },
                    {
                        key: 'proposed',
                        items: [{ key: 'D3', code: 'D3', hours: 3 }],
                    },
                ],
            },
            additional_courses: [
                { key: 'INF2', code: 'INF2', hours: 2 },
            ],
        })).toEqual({
            completed: [
                {
                    key: 'completed-ETH1-1',
                    code: 'ETH1',
                    label: 'ETH1',
                    meta: '1',
                },
            ],
            failed: [
                {
                    key: 'missing-S1-5',
                    code: 'S1',
                    label: 'S1',
                    meta: '5',
                },
            ],
            missing: [
                {
                    key: 'ETH2',
                    code: 'ETH2',
                    hours: 2,
                    name: '',
                    label: 'ETH2',
                    meta: '2 Std.',
                },
            ],
            planned: [
                {
                    key: 'D3',
                    code: 'D3',
                    hours: 3,
                    name: '',
                    label: 'D3',
                    meta: '3 Std.',
                },
            ],
            additional: [
                {
                    key: 'INF2',
                    code: 'INF2',
                    hours: 2,
                    name: '',
                    label: 'INF2',
                    meta: '2 Std.',
                },
            ],
        })
    })

    it('preselects timetable v2 right card values from completed courses', () => {
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
        }

        expect(methods.timetableV2SelectionWithCourseDefaults.call(ctx, {}, [
            { code: 'ETH1', label: 'ETH1', meta: '1' },
            { code: 'S1', label: 'S1', meta: '2' },
            { code: 'INF2', label: 'INF2', meta: '2' },
            { code: 'BE1', label: 'BE1', meta: '3' },
        ])).toEqual({
            religion: 'ETH',
            language: 'S',
            branch: 'wirtschaftskundlich',
            artsSubject: 'BE',
        })
    })

    it('does not overwrite timetable v2 right card values already selected by the user', () => {
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
        }

        expect(methods.timetableV2SelectionWithCourseDefaults.call(ctx, {
            language: 'L',
            artsSubject: 'ME',
        }, [
            { code: 'ETH1', label: 'ETH1', meta: '1' },
            { code: 'S1', label: 'S1', meta: '2' },
            { code: 'INF2', label: 'INF2', meta: '2' },
            { code: 'BE1', label: 'BE1', meta: '3' },
        ])).toEqual({
            language: 'L',
            artsSubject: 'ME',
            religion: 'ETH',
            branch: 'wirtschaftskundlich',
        })
    })

    it('does not reapply timetable v2 defaults after the user unselects a chip', () => {
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
        }

        expect(methods.timetableV2SelectionWithCourseDefaults.call(ctx, {
            language: null,
        }, [
            { code: 'S1', label: 'S1', meta: '2' },
        ])).toEqual({
            language: null,
        })
    })

    it('splits the subjects area into subject plan, hidden import route, subjects, and mapping pages', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/subjectsOverview/SubjectsOverview.vue',
            'utf8',
        )

        expect(componentSource).toContain('subjectNavigationItems()')
        expect(componentSource).not.toContain("key: 'overview'")
        expect(componentSource).not.toContain("label: 'Übersicht'")
        expect(componentSource).toContain("key: 'subject-plan'")
        expect(componentSource).toContain("label: 'Grafik'")
        expect(componentSource).not.toContain("key: 'import'")
        expect(componentSource).not.toContain("label: 'Import'")
        expect(componentSource).toContain("label: 'Fächer'")
        expect(componentSource).toContain("label: 'Zuordnung'")
        expect(componentSource).toContain("v-if=\"subject_action === 'subject-plan'\"")
        expect(componentSource).toContain("subject_action === 'subject-plan'")
        expect(componentSource).toContain("v-if=\"subject_action === 'import'\"")
        expect(componentSource).toContain("v-if=\"subject_action === 'subjects'\"")
        expect(componentSource).toContain("v-if=\"subject_action === 'mapping'\"")
        expect(componentSource).toContain("'subject-plan'")
        expect(componentSource).toContain('canManageSubjectSettings()')
        expect(componentSource).toContain("['subject-plan', 'import', 'subjects', 'mapping']")
        expect(componentSource).toContain("['subject-plan']")
        expect(componentSource).toContain("return allowedActions.includes(subsection) ? subsection : 'subject-plan'")
        expect(componentSource).toContain('redirectUnauthorizedSubjectRoute()')
        expect(componentSource).toContain('redirectMissingSubjectRoute()')
        expect(componentSource).toContain("this.$router.replace({ path: '/admin/students-timetables/subjects-overview/subject-plan' })")
        expect(componentSource).toContain('handleSubjectNavigation(key)')
        expect(componentSource).toContain('embedded')
        expect(componentSource).toContain("subject_action: this.embedded ? 'subject-plan' : this.normalizedSubjectAction(this.$route.params.subsection)")
        expect(componentSource).toContain('if (this.embedded) {')
        expect(componentSource).toContain('/admin/students-timetables/subjects-overview/${this.subject_action}')
    })

    it('writes the default subject plan step into the URL', () => {
        const methods = (SubjectsOverview as any).methods
        const replace = vi.fn()
        const ctx: any = {
            embedded: false,
            $route: {
                params: {
                    section: 'subjects-overview',
                },
            },
            $router: {
                replace,
            },
            subject_action: 'subjects',
        }

        expect(methods.redirectMissingSubjectRoute.call(ctx)).toBe(true)
        expect(ctx.subject_action).toBe('subject-plan')
        expect(replace).toHaveBeenCalledWith({ path: '/admin/students-timetables/subjects-overview/subject-plan' })
    })

    it('uses FilePond upload for json files', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/subjectsOverview/SubjectsOverview.vue',
            'utf8',
        )

        expect(componentSource).toContain('Fächerübersicht')
        expect(componentSource).toContain('subject-plan-grid')
        expect(componentSource).toContain('subjectOverviewColumns')
        expect(componentSource).toContain('subjectOverviewLayouts')
        expect(componentSource).toContain('subjectOverviewSplitColumnGroups')
        expect(componentSource).toContain('subjectOverviewRows')
        expect(componentSource).toContain('subjectOverviewFooter')
        expect(componentSource).toContain('subjectOverviewGrandTotals')
        expect(componentSource).toContain('subjectOverviewGridStyle(columnGroup)')
        expect(componentSource).toContain('subjectOverviewCellsForColumns(row, columnGroup.columns)')
        expect(componentSource).toContain('subjectOverviewFooterForColumns(columnGroup.columns)')
        expect(componentSource).toContain('subjectOverviewFooterTotals(subjects)')
        expect(componentSource).toContain('subjectOverviewBranchKeys()')
        expect(componentSource).toContain('subjectOverviewTotals(subjects)')
        expect(componentSource).toContain('subjectOverviewSubjectKey(subject = {})')
        expect(componentSource).toContain('subjectOverviewCellClass(cell)')
        expect(componentSource).toContain('uniqueSubjectOverviewSubjects(subjects)')
        expect(componentSource).toContain('uniqueSubjectOverviewTotalSubjects(subjects)')
        expect(componentSource).toContain('isCommonSubject(subject)')
        expect(componentSource).toContain('isBranchSubject(subject)')
        expect(componentSource).toContain('formatSubjectHours(Number(subject.hours_per_week || 0))')
        expect(componentSource).toContain('subjectOverviewHasBranches')
        expect(componentSource).toContain('subjectOverviewHasChoiceSubjects')
        expect(componentSource).toContain('subject-plan-legend')
        expect(componentSource).toContain('subject-plan-choice-note')
        expect(componentSource).toContain('subject-plan-cell--wirtschaftskundlich')
        expect(componentSource).toContain('subject-plan-cell--gymnasial')
        expect(componentSource).toContain('max-width: none')
        expect(componentSource).toContain('minmax(48px, 1fr)')
        expect(componentSource).toContain('@media (max-width: 1279px)')
        expect(componentSource).toContain('@media (max-width: 599px)')
        expect(componentSource).toContain('subject-plan-layout--split')
        expect(componentSource).toContain('subject-plan-layout--desktop')
        expect(componentSource).toContain('<FileUpload')
        expect(componentSource).toContain('/api/admin/students-timetables/subjects-overview-json')
        expect(componentSource).toContain(":allowedFileTypes=\"['application/json']\"")
        expect(componentSource).toContain('loadImports()')
        expect(componentSource).toContain('loadSettings()')
        expect(componentSource).toContain("'config.selected_schoolyear.id'()")
        expect(componentSource).toContain('refreshForSchoolyearChange()')
        expect(componentSource).toContain('this.refreshFilePond++')
        expect(componentSource).toContain('Noch keine JSON-Datei importiert.')
        expect(componentSource).not.toContain('importSubjectCountLabel(importItem.analysis)')
        expect(componentSource).not.toContain('analysis?.subjects_total')
        expect(componentSource).not.toContain('Fächer / ${courseRowsTotal} Kurse')
        expect(componentSource).not.toContain('semester.subjects')
        expect(componentSource).not.toContain('semester.branch_variants?.length')
        expect(componentSource).not.toContain('grid-template-columns: repeat(2, minmax(0, 1fr))')
        expect(componentSource).toContain('flex-direction: column')
        expect(componentSource).not.toContain('grid-template-columns: minmax(0, 1fr)')
        expect(componentSource).toContain('width: 100%')
        expect(componentSource).not.toContain('branchVariantClass(branch.key)')
        expect(componentSource).not.toContain('branch-variant-block--common')
        expect(componentSource).not.toContain('branch-variant-block--wirtschaft')
        expect(componentSource).not.toContain('branch-variant-block--gymnasial')
        expect(componentSource).not.toContain('alternativeDisplay(subject.short_name)')
        expect(componentSource).toContain('subjectOverviewDisplayCode(subject)')
        expect(componentSource).toContain("'subject-plan-cell--multi': cell.subjects.length > 1")
        expect(componentSource).toContain("'subject-plan-item--course': cell.subjects.length > 1")
        expect(componentSource).toContain('subject.display_code || subjectOverviewDisplayCode(subject)')
        expect(componentSource).toContain('subjectOverviewCourseItems(this.uniqueSubjectOverviewSubjects(matchingSubjects))')
        expect(componentSource).toContain('subjectOverviewDisplayCodes(subject)')
        expect(componentSource).toContain('subjectOverviewMergedChoiceSubjects(subjects)')
        expect(componentSource).toContain('subjectOverviewMergeableChoiceGroupForSubject(subject)')
        expect(componentSource).toContain('isSubjectOverviewChoiceSubject(subject)')
        expect(componentSource).toContain('subjectMatchesSubjectOverviewChoiceGroup(subject, group)')
        expect(componentSource).toContain('subjectOverviewChoiceGroups()')
        expect(componentSource).toContain("codes: ['R/ET1']")
        expect(componentSource).toContain("choices: ['Rev', 'Ris', 'Rk', 'Ror', 'ET']")
        expect(componentSource).toContain('subjectOverviewLanguageChoiceGroups()')
        expect(componentSource).toContain('subjectOverviewArtChoiceGroups()')
        expect(componentSource).toContain("return this.subjectOverviewAlternativeChoiceGroups(['BE', 'ME'])")
        expect(componentSource).toContain('alternativeParts(value)')
        expect(componentSource).toContain('subjectNameLines(subject)')
        expect(componentSource).toContain('isAlternativeSubjectCode(subject.json_code, subject.json_subject)')
        expect(componentSource).toContain('isAlternativeSubjectCode(code, abbreviation)')
        expect(componentSource).toContain('explicitModuleNumbers(subject.json_code, subject.json_subject)')
        expect(componentSource).toContain('escapeRegExp(value)')
        expect(componentSource).toContain("subjectSort: {\n                key: 'semester'")
        expect(componentSource).toContain('sortedSubjectRows()')
        expect(componentSource).toContain("sortSubjectRows('semester')")
        expect(componentSource).toContain("sortSubjectRows('branch')")
        expect(componentSource).toContain("sortSubjectRows('name')")
        expect(componentSource).toContain('subjectSortIcon(key)')
        expect(componentSource).toContain('compareSubjectRows(firstSubject, secondSubject, key)')
        expect(componentSource).toContain('subjects-sort-button')
        expect(componentSource).toContain("const suffixSeparator = suffixMatch[1].endsWith(' ') ? ' ' : ''")
        expect(componentSource).toContain('const compactModuleMatch = normalizedValue.match(/^(.*?)([1-9]{2,})$/)')
        expect(componentSource).toContain("compactModuleMatch[2]")
        expect(componentSource).toContain('subjects-settings-table__name-lines')
        expect(componentSource).toContain('Importierte Fächer')
        expect(componentSource).toContain('Fach-Zuordnung')
        expect(componentSource).toContain('/api/admin/students-timetables/subjects-overview-settings')
        expect(componentSource).toContain('/api/admin/students-timetables/subjects-overview-settings/subjects')
        expect(componentSource).toContain('/api/admin/students-timetables/subjects-overview-settings/mappings')
        expect(componentSource).toContain('saveSubjectRows()')
        expect(componentSource).toContain('saveMappings()')
        expect(componentSource).toContain('rawSubjectHours(subjects)')
        expect(componentSource).toContain('subjectOverviewChoiceDuplicateHours(subjects)')
        expect(componentSource).toContain('subject-plan-grand-total')
        expect(componentSource).toContain('.subject-plan-item--course')
        expect(componentSource).toContain('.subject-plan-item--course + .subject-plan-item--course')
        expect(componentSource).toContain('border-top: 1px solid rgba(15, 23, 42, 0.42)')
        expect(componentSource).not.toContain('subject-plan-course-group-label')
        expect(componentSource).not.toContain('subject-plan-course-divider')
    })
})
