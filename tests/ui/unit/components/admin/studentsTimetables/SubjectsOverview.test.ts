import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import StudentsTimetables from '@/pages/admin/studentsTimetables/StudentsTimetables.vue'
import SubjectsOverview from '@/pages/admin/studentsTimetables/subjectsOverview/SubjectsOverview.vue'
import TestsV3 from '@/pages/admin/studentsTimetables/testsV3/TestsV3.vue'
import TimetableV2 from '@/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue'
import TtEntries from '@/pages/admin/studentsTimetables/ttEntries/TtEntries.vue'

describe('Students timetable subjects overview', () => {
    it('shows and loads the subject plan for the personal schoolyear', async () => {
        const methods = (SubjectsOverview as any).methods
        const computed = (SubjectsOverview as any).computed
        const get = vi.fn().mockResolvedValue({
            data: {
                data: {
                    subjects: [{ json_code: 'D1' }],
                    mappings: [],
                },
            },
        })
        const applySettings = vi.fn()
        const context: any = {
            applySettings,
            settingsError: '',
            settingsLoading: false,
            studyProgram: 'normalstudium',
            personalSchoolyearRouteOptions: computed.personalSchoolyearRouteOptions.call({}),
        }

        vi.stubGlobal('axios', { get })

        try {
            await methods.loadSettings.call(context)
        } finally {
            vi.unstubAllGlobals()
        }

        expect(computed.personalSchoolyearLabel.call({
            config: {
                selected_schoolyear: {
                    concerns: '2026/27',
                    name: 'Schuljahr 2026/27',
                },
            },
        })).toBe('2026/27')
        expect(get).toHaveBeenCalledWith(
            '/api/admin/students-timetables/subjects-overview-settings/normalstudium?schoolyear_scope=personal',
        )
        expect(applySettings).toHaveBeenCalledWith({
            subjects: [{ json_code: 'D1' }],
            mappings: [],
        })

        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/subjectsOverview/SubjectsOverview.vue',
            'utf8',
        )

        expect(componentSource).toContain('<strong class="text-primary">{{ personalSchoolyearLabel }}</strong>')
        expect(componentSource).not.toContain('Persönliches Schuljahr:')
        expect(componentSource).not.toContain('prepend-icon="mdi-calendar"')
        expect(componentSource).toContain('schoolyear_scope: PERSONAL_SCHOOLYEAR_SCOPE')
    })

    it('uses the same semester, branch, and subject order for both study programs', () => {
        const methods = (SubjectsOverview as any).methods
        const computed = (SubjectsOverview as any).computed
        const sortedCodes = (subjectRows: Array<Record<string, unknown>>) => computed.sortedSubjectRows.call({
            ...methods,
            subjectRows,
            subjectSort: {
                key: 'semester',
                direction: 'asc',
            },
        }).map((subject: { json_code: string }) => subject.json_code)

        expect(sortedCodes([
            { semester: 7, branch: 'wirtschaftskundlich', json_code: 'INF2', sort_index: 1 },
            { semester: 7, branch: 'gymnasial', json_code: 'ME1', sort_index: 2 },
            { semester: 7, branch: null, json_code: 'D7', sort_index: 3 },
            { semester: 7, branch: 'gymnasial', json_code: 'BE1', sort_index: 4 },
        ])).toEqual(['D7', 'BE1', 'ME1', 'INF2'])

        expect(sortedCodes([
            { semester: 4, branch: 'gymnasial', json_code: 'ME1', sort_index: 1 },
            { semester: 4, branch: 'wirtschaftskundlich', json_code: 'INF2', sort_index: 2 },
            { semester: 4, branch: 'gymnasial', json_code: 'BE1', sort_index: 3 },
            { semester: 4, branch: null, json_code: 'D6', sort_index: 4 },
        ])).toEqual(['D6', 'BE1', 'ME1', 'INF2'])
    })

    it('shows the missing subject-plan prompt across the whole students-timetables module', async () => {
        const methods = (StudentsTimetables as any).methods
        const computed = (StudentsTimetables as any).computed
        const previousSchoolyear = {
            id: 24,
            name: '2025/26',
            subject_rows_count: 151,
            normal_subject_rows_count: 75,
            compact_subject_rows_count: 76,
            mappings_count: 5,
        }
        const get = vi.fn().mockResolvedValue({
            data: {
                data: {
                    previous_schoolyear: previousSchoolyear,
                },
            },
        })
        const context: any = {
            ...methods,
            ...(StudentsTimetables as any).data(),
            canManageStudentsTimetables: true,
            personalSchoolyearRouteOptions: computed.personalSchoolyearRouteOptions.call({}),
        }

        Object.defineProperty(context, 'shouldOfferSubjectPlanCarryForward', {
            get() {
                return computed.shouldOfferSubjectPlanCarryForward.call(context)
            },
        })

        vi.stubGlobal('axios', { get })

        try {
            await methods.loadSubjectPlanCarryForwardStatus.call(context)
        } finally {
            vi.unstubAllGlobals()
        }

        expect(get).toHaveBeenCalledWith(
            '/api/admin/students-timetables/subjects-overview-settings?schoolyear_scope=personal',
        )
        expect(context.subjectPlanPreviousSchoolyear).toEqual(previousSchoolyear)
        expect(context.shouldOfferSubjectPlanCarryForward).toBe(true)
        expect(context.subjectPlanCarryForwardDialog).toBe(true)

        const moduleSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/StudentsTimetables.vue',
            'utf8',
        )
        const subjectsOverviewSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/subjectsOverview/SubjectsOverview.vue',
            'utf8',
        )
        const ttEntriesSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/ttEntries/TtEntries.vue',
            'utf8',
        )

        expect(moduleSource).toContain('Der Soll-/Fachplan für {{ personalSchoolyearLabel }} fehlt.')
        expect(moduleSource).toContain('{{ subjectPlanPreviousSchoolyear.normal_subject_rows_count }} Normalstudium')
        expect(moduleSource).toContain('{{ subjectPlanPreviousSchoolyear.compact_subject_rows_count }} Kompaktstudium')
        expect(moduleSource).toContain('v-model="subjectPlanCarryForwardDialog"')
        expect(moduleSource).toContain('Später')
        expect(moduleSource).toContain('Jetzt übernehmen')
        expect(moduleSource).toContain(':key="subjectPlanRevision"')
        expect(subjectsOverviewSource).not.toContain('@click="carryForwardSubjectPlan"')
        expect(ttEntriesSource).not.toContain('subjectPlanCarryForwardDialog')
    })

    it('carries both study programs and mappings forward in one module-wide request', async () => {
        const methods = (StudentsTimetables as any).methods
        const computed = (StudentsTimetables as any).computed
        const post = vi.fn().mockResolvedValue({
            data: {
                message: '151 Fachzeilen und 5 Zuordnungen aus 2025/26 übernommen.',
            },
        })
        const loadSubjectPlanCarryForwardStatus = vi.fn().mockResolvedValue(undefined)
        const context: any = {
            ...methods,
            canManageStudentsTimetables: true,
            loadSubjectPlanCarryForwardStatus,
            subjectPlanCarryForwardDialog: true,
            subjectPlanCarryForwardError: '',
            subjectPlanCarryForwardLoading: false,
            subjectPlanCarryForwardMessage: '',
            subjectPlanRevision: 0,
            personalSchoolyearRouteOptions: computed.personalSchoolyearRouteOptions.call({}),
            subjectPlanPreviousSchoolyear: {
                id: 24,
                name: '2025/26',
                subject_rows_count: 151,
                normal_subject_rows_count: 75,
                compact_subject_rows_count: 76,
                mappings_count: 5,
            },
        }

        vi.stubGlobal('axios', { post })

        try {
            await methods.carryForwardSubjectPlan.call(context)
        } finally {
            vi.unstubAllGlobals()
        }

        expect(post).toHaveBeenCalledWith(
            '/api/admin/students-timetables/subjects-overview-settings/carry-forward?schoolyear_scope=personal',
        )
        expect(loadSubjectPlanCarryForwardStatus).toHaveBeenCalledOnce()
        expect(context.subjectPlanCarryForwardMessage).toBe('151 Fachzeilen und 5 Zuordnungen aus 2025/26 übernommen.')
        expect(context.subjectPlanRevision).toBe(1)
        expect(context.subjectPlanCarryForwardDialog).toBe(false)
        expect(context.subjectPlanCarryForwardLoading).toBe(false)
    })

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

        expect(languageCell.subjects.map(subject => subject.display_code)).toEqual(['L1/F1/S1*'])
        expect(languageCell.subjects.map(subject => subject.hours_per_week)).toEqual([4])
        expect(row.totals.map(total => total.value)).toEqual(['4'])
    })

    it('fills all Normalstudium semesters in Grafik v2 from subjects and rules', () => {
        const methods = (SubjectsOverview as any).methods
        const computed = (SubjectsOverview as any).computed
        const context: any = {
            ...methods,
            studyProgram: 'normalstudium',
            subjectRulesVersion: 1,
            activeSubjectRows: [
                {
                    stable_key: 'd1',
                    semester: 1,
                    branch: null,
                    json_code: 'D1',
                    json_subject: 'D',
                    name: 'Deutsch 1',
                    hours_per_week: 3,
                },
                {
                    stable_key: 'religion-1',
                    semester: 1,
                    branch: null,
                    json_code: 'R/ET1',
                    json_subject: 'R/ET',
                    name: 'Religion/Ethik 1',
                    hours_per_week: 2,
                },
                {
                    stable_key: 'e2',
                    semester: 2,
                    branch: null,
                    json_code: 'E2',
                    json_subject: 'E',
                    name: 'Englisch 2',
                    hours_per_week: 4,
                },
                {
                    stable_key: 'm8',
                    semester: 8,
                    branch: null,
                    json_code: 'M8',
                    json_subject: 'M',
                    name: 'Mathematik 8',
                    hours_per_week: 2,
                },
            ],
            subjectRules: [
                {
                    stable_key: 'religion-rule',
                    selection_key: 'religion',
                    is_active: true,
                    options: [
                        {
                            value: 'ETH',
                            course_code_prefix: 'ETH',
                            subject_keys: ['religion-1'],
                        },
                        {
                            value: 'Rk',
                            course_code_prefix: 'Rk',
                            subject_keys: ['religion-1'],
                        },
                    ],
                },
                {
                    stable_key: 'branch-rule',
                    selection_key: 'branch',
                    is_active: true,
                    options: [
                        {
                            value: 'wirtschaftskundlich',
                            course_code_prefix: '',
                            subject_keys: ['wiku-subject'],
                        },
                        {
                            value: 'gymnasial',
                            course_code_prefix: '',
                            subject_keys: ['gym-subject'],
                        },
                    ],
                },
            ],
            subjectOverviewV2Columns: [
                { key: 'R/ETH', subjectKeys: ['R/ET', 'R', 'ET', 'ETH'] },
                { key: 'D', subjectKeys: ['D'] },
                { key: 'E', subjectKeys: ['E'] },
                { key: 'M', subjectKeys: ['M'] },
            ],
            subjectOverviewV2Semesters: [1, 2, 3, 4, 5, 6, 7, 8],
        }
        context.subjectOverviewV2PopulatedSubjects = computed.subjectOverviewV2PopulatedSubjects.call(context)

        const rows = computed.subjectOverviewV2Rows.call(context)
        const religionCell = rows[0].cells.find(cell => cell.column.key === 'R/ETH')
        const secondSemester = rows[1]
        const eighthSemester = rows[7]
        const footer = computed.subjectOverviewV2Footer.call(context)

        expect(context.subjectOverviewV2PopulatedSubjects.map(subject => subject.json_code))
            .toEqual(['D1', 'R/ET1', 'E2', 'M8'])
        expect(computed.subjectOverviewV2PopulatedSubjects.call({
            ...context,
            studyProgram: 'kompaktstudium',
        }).map(subject => subject.json_code)).toEqual(['D1', 'R/ET1', 'E2'])
        expect(religionCell.display_code).toBe('R1 | ETH1 *')
        expect(religionCell.hours).toBe('2')
        expect(methods.subjectOverviewCellClass.call(context, religionCell)).toBe('subject-plan-cell--filled')
        expect(rows[0].totals.map(total => total.value)).toEqual(['5'])
        expect(secondSemester.cells.find(cell => cell.column.key === 'E').display_code).toBe('E2')
        expect(secondSemester.cells.find(cell => cell.column.key === 'E').hours).toBe('4')
        expect(secondSemester.totals.map(total => total.value)).toEqual(['4'])
        expect(eighthSemester.cells.find(cell => cell.column.key === 'M').display_code).toBe('M8')
        expect(eighthSemester.totals.map(total => total.value)).toEqual(['2'])
        expect(footer.map(column => column.totals.map(total => total.value))).toEqual([['2'], ['3'], ['4'], ['2']])
        expect(computed.subjectOverviewV2GrandTotals.call(context).map(total => total.value)).toEqual(['11'])
        expect(methods.subjectOverviewV2SubjectBranch.call(context, { stable_key: 'wiku-subject', branch: null }))
            .toBe('wirtschaftskundlich')
        expect(methods.subjectOverviewCellClass.call(context, {
            subjects: [{ stable_key: 'wiku-subject' }],
            branches: ['wirtschaftskundlich'],
        })).toBe('subject-plan-cell--wirtschaftskundlich')
        expect(methods.subjectOverviewCellClass.call(context, {
            subjects: [{ stable_key: 'gym-subject' }],
            branches: ['gymnasial'],
        })).toBe('subject-plan-cell--gymnasial')
        const branchCell = methods.subjectOverviewV2Cell.call(context, [
            {
                stable_key: 'gym-subject',
                semester: 7,
                branch: 'gymnasial',
                json_code: 'BE1',
                json_subject: 'BE',
                hours_per_week: 2,
            },
            {
                stable_key: 'wiku-subject',
                semester: 7,
                branch: 'wirtschaftskundlich',
                json_code: 'BE1',
                json_subject: 'BE',
                hours_per_week: 2,
            },
        ], {
            key: 'BE',
            subjectKeys: ['BE'],
        })

        expect(branchCell.groups.map(group => [group.branch, group.class])).toEqual([
            ['common', 'subject-plan-cell--filled'],
        ])
        expect(branchCell.display_code).toBe('BE1')
        expect(branchCell.hours).toBe('2')
        expect(methods.subjectOverviewCellClass.call(context, branchCell)).toBe('subject-plan-cell--filled')

        const differentBranchCell = methods.subjectOverviewV2Cell.call(context, [
            {
                stable_key: 'gym-subject',
                semester: 7,
                branch: 'gymnasial',
                json_code: 'BE1',
                json_subject: 'BE',
                hours_per_week: 3,
            },
            {
                stable_key: 'wiku-subject',
                semester: 7,
                branch: 'wirtschaftskundlich',
                json_code: 'BE1',
                json_subject: 'BE',
                hours_per_week: 2,
            },
        ], {
            key: 'BE',
            subjectKeys: ['BE'],
        })

        expect(differentBranchCell.groups.map(group => [group.branch, group.class])).toEqual([
            ['wirtschaftskundlich', 'subject-plan-cell--wirtschaftskundlich'],
            ['gymnasial', 'subject-plan-cell--gymnasial'],
        ])
        expect(methods.subjectOverviewV2SubjectsForColumn.call(context, [
            { semester: 2, json_subject: 'F', json_code: 'F1' },
            { semester: 2, json_subject: 'S', json_code: 'S1' },
            { semester: 2, json_subject: 'L', json_code: 'L1' },
        ], {
            subjectKeys: ['L/F/S', 'L', 'F', 'S'],
        }).map(subject => subject.json_code)).toEqual(['L1', 'F1', 'S1'])
    })

    it('fills all Kompaktstudium semesters in Grafik v2 and combines modules from the stored rules', () => {
        const methods = (SubjectsOverview as any).methods
        const computed = (SubjectsOverview as any).computed
        const subject = (
            stableKey: string,
            semester: number,
            code: string,
            subjectKey: string,
            hours: number,
            branch: string | null = null,
        ) => ({
            stable_key: stableKey,
            semester,
            branch,
            json_code: code,
            json_subject: subjectKey,
            name: code,
            hours_per_week: hours,
        })
        const activeSubjectRows = [
            subject('d2', 1, 'D2', 'D', 1.5),
            subject('d3', 1, 'D3', 'D', 1.5),
            subject('r1', 1, 'R1', 'R', 1),
            subject('et1', 1, 'ET1', 'ET', 1),
            subject('l1', 1, 'L1', 'L', 2),
            subject('f1', 1, 'F1', 'F', 2),
            subject('s1', 1, 'S1', 'S', 2),
            subject('d7', 5, 'D7', 'D', 2),
            subject('d8', 5, 'D8', 'D', 2),
            subject('l6', 5, 'L6', 'L', 1.5, 'gymnasial'),
            subject('l7', 5, 'L7', 'L', 1.5, 'gymnasial'),
            subject('f6', 5, 'F6', 'F', 1.5, 'gymnasial'),
            subject('f7', 5, 'F7', 'F', 1.5, 'gymnasial'),
            subject('s6', 5, 'S6', 'S', 1.5, 'gymnasial'),
            subject('s7', 5, 'S7', 'S', 1.5, 'gymnasial'),
            subject('inf3', 5, 'INF3', 'INF', 1.5, 'wirtschaftskundlich'),
            subject('outside-compact-plan', 6, 'D9', 'D', 9),
        ]
        const context: any = {
            ...methods,
            studyProgram: 'kompaktstudium',
            subjectRulesVersion: 1,
            activeSubjectRows,
            subjectRules: [
                {
                    stable_key: 'branch-rule',
                    selection_key: 'branch',
                    is_active: true,
                    options: [
                        { value: 'wirtschaftskundlich', subject_keys: ['inf3'] },
                        { value: 'gymnasial', subject_keys: ['l6', 'l7', 'f6', 'f7', 's6', 's7'] },
                    ],
                },
                {
                    stable_key: 'language-rule',
                    selection_key: 'language',
                    is_active: true,
                    options: [
                        { value: 'L', label: 'L - Latein', subject_keys: ['l1', 'l6', 'l7'] },
                        { value: 'F', label: 'F - Französisch', subject_keys: ['f1', 'f6', 'f7'] },
                        { value: 'S', label: 'S - Spanisch', subject_keys: ['s1', 's6', 's7'] },
                    ],
                },
                {
                    stable_key: 'religion-rule',
                    selection_key: 'religion',
                    is_active: true,
                    options: [
                        { value: 'Rk', course_code_prefix: 'Rk', subject_keys: ['r1'] },
                        { value: 'ETH', course_code_prefix: 'ETH', subject_keys: ['et1'] },
                    ],
                },
            ],
        }

        context.subjectOverviewColumns = computed.subjectOverviewColumns.call(context)
        context.subjectOverviewV2Columns = computed.subjectOverviewV2Columns.call(context)
        context.subjectOverviewV2Semesters = computed.subjectOverviewV2Semesters.call(context)
        context.subjectOverviewV2PopulatedSubjects = computed.subjectOverviewV2PopulatedSubjects.call(context)

        const rows = computed.subjectOverviewV2Rows.call(context)
        const firstSemester = rows[0]
        const fifthSemester = rows[4]
        const firstSemesterLanguage = firstSemester.cells.find(cell => cell.column.key === 'L/F/S')
        const firstSemesterReligion = firstSemester.cells.find(cell => cell.column.key === 'R/ETH')
        const fifthSemesterLanguage = fifthSemester.cells.find(cell => cell.column.key === 'L/F/S')
        const fifthSemesterGerman = fifthSemester.cells.find(cell => cell.column.key === 'D')
        const footer = computed.subjectOverviewV2Footer.call(context)

        expect(context.subjectOverviewV2Semesters).toEqual([1, 2, 3, 4, 5])
        expect(context.subjectOverviewV2PopulatedSubjects).toHaveLength(16)
        expect(rows).toHaveLength(5)
        expect(firstSemester.cells.find(cell => cell.column.key === 'D')).toMatchObject({
            display_code: 'D2+3',
            hours: '3',
        })
        expect(firstSemesterLanguage).toMatchObject({
            display_code: 'L1 | F1 | S1 *',
            hours: '2',
        })
        expect(firstSemesterReligion).toMatchObject({
            display_code: 'R1 | ETH1 *',
            hours: '1',
        })
        expect(firstSemester.totals.map(total => total.value)).toEqual(['6'])
        expect(fifthSemesterLanguage).toMatchObject({
            display_code: 'L6+7 | F6+7 | S6+7 *',
            hours: '3',
        })
        expect(methods.subjectOverviewCellClass.call(context, fifthSemesterLanguage))
            .toBe('subject-plan-cell--gymnasial')
        expect(fifthSemesterGerman).toMatchObject({
            display_code: 'D7+8',
            hours: '4',
        })
        expect(fifthSemester.totals.map(total => [total.value, total.class])).toEqual([
            ['5,5', 'subject-plan-total--wirtschaftskundlich'],
            ['7', 'subject-plan-total--gymnasial'],
        ])
        expect(footer.find(column => column.key === 'D').totals.map(total => total.value)).toEqual(['7'])
        expect(computed.subjectOverviewV2GrandTotals.call(context).map(total => [total.value, total.class])).toEqual([
            ['11,5', 'subject-plan-total--wirtschaftskundlich'],
            ['13', 'subject-plan-total--gymnasial'],
        ])
    })

    it('describes the branch rules below Grafik v2 and keeps different BE1 semantics separated', () => {
        const methods = (SubjectsOverview as any).methods
        const computed = (SubjectsOverview as any).computed
        const subject = (branch: string | null, code: string, semester: number) => ({
            stable_key: `${branch || 'common'}-${code}`,
            semester,
            branch,
            json_code: code,
            json_subject: code.replace(/\d+$/u, ''),
            name: code,
            hours_per_week: 2,
        })
        const activeSubjectRows = [
            subject(null, 'L5', 6),
            subject(null, 'F5', 6),
            subject(null, 'S5', 6),
            subject('wirtschaftskundlich', 'BE1', 7),
            subject('wirtschaftskundlich', 'ME1', 7),
            subject('wirtschaftskundlich', 'INF2', 7),
            subject('wirtschaftskundlich', 'INF3', 8),
            subject('wirtschaftskundlich', 'ÖKO1', 7),
            subject('wirtschaftskundlich', 'ÖKO2', 8),
            subject('wirtschaftskundlich', 'ÖKO3', 8),
            subject('gymnasial', 'BE1', 7),
            subject('gymnasial', 'ME1', 7),
            subject('gymnasial', 'BE2', 8),
            subject('gymnasial', 'ME2', 8),
            subject('gymnasial', 'L7', 8),
            subject('gymnasial', 'F7', 8),
            subject('gymnasial', 'S7', 8),
        ]
        const subjectKeys = (branch: string | null, codes: string[]) => codes.map(code => `${branch || 'common'}-${code}`)
        const context: any = {
            ...methods,
            subjectRulesVersion: 1,
            activeSubjectRows,
            subjectRules: [
                {
                    stable_key: 'branch-rule',
                    selection_key: 'branch',
                    is_active: true,
                    options: [
                        {
                            value: 'wirtschaftskundlich',
                            subject_keys: activeSubjectRows
                                .filter(item => item.branch === 'wirtschaftskundlich')
                                .map(item => item.stable_key),
                        },
                        {
                            value: 'gymnasial',
                            subject_keys: activeSubjectRows
                                .filter(item => item.branch === 'gymnasial')
                                .map(item => item.stable_key),
                        },
                    ],
                },
                {
                    stable_key: 'language-rule',
                    selection_key: 'language',
                    is_active: true,
                    options: [
                        { value: 'L', label: 'L - Latein', subject_keys: [...subjectKeys(null, ['L5']), ...subjectKeys('gymnasial', ['L7'])] },
                        { value: 'F', label: 'F - Französisch', subject_keys: [...subjectKeys(null, ['F5']), ...subjectKeys('gymnasial', ['F7'])] },
                        { value: 'S', label: 'S - Spanisch', subject_keys: [...subjectKeys(null, ['S5']), ...subjectKeys('gymnasial', ['S7'])] },
                    ],
                },
                {
                    stable_key: 'arts-rule',
                    selection_key: 'arts_subject',
                    is_active: true,
                    options: [
                        { value: 'BE', subject_keys: [...subjectKeys('wirtschaftskundlich', ['BE1']), ...subjectKeys('gymnasial', ['BE2'])] },
                        { value: 'ME', subject_keys: [...subjectKeys('wirtschaftskundlich', ['ME1']), ...subjectKeys('gymnasial', ['ME2'])] },
                    ],
                },
            ],
        }
        const legendItems = computed.subjectOverviewV2BranchLegendItems.call(context)
        const impactCards = computed.subjectRuleImpactCards.call(context)
        const be1Cell = methods.subjectOverviewV2Cell.call(
            context,
            activeSubjectRows.filter(item => item.json_code === 'BE1'),
            { key: 'BE', subjectKeys: ['BE'] },
        )

        expect(legendItems).toEqual([
            {
                branch: 'wirtschaftskundlich',
                label: 'Wirtschaftskundlicher Zweig',
                lines: [
                    'Latein, Französisch oder Spanisch bis zum Modul 5',
                    'BE1 oder ME1 / INF2 und INF3 / ÖKO1, ÖKO2 und ÖKO3',
                ],
            },
            {
                branch: 'gymnasial',
                label: 'Gymnasialer Zweig',
                lines: [
                    'Latein, Französisch oder Spanisch bis zum Modul 7',
                    'BE1 und ME1 / BE2 oder ME2',
                ],
            },
        ])
        expect(impactCards).toEqual([
            {
                branch: 'wirtschaftskundlich',
                label: 'Wirtschaftskundlicher Zweig',
                required: [
                    'INF2 und INF3',
                    'ÖKO1, ÖKO2 und ÖKO3',
                ],
                choices: [
                    {
                        label: 'Sprache',
                        description: 'Latein, Französisch oder Spanisch bis zum Modul 5',
                    },
                    {
                        label: 'Künstlerisches Fach',
                        description: 'BE1 oder ME1',
                    },
                ],
            },
            {
                branch: 'gymnasial',
                label: 'Gymnasialer Zweig',
                required: ['BE1 und ME1'],
                choices: [
                    {
                        label: 'Sprache',
                        description: 'Latein, Französisch oder Spanisch bis zum Modul 7',
                    },
                    {
                        label: 'Künstlerisches Fach',
                        description: 'BE2 oder ME2',
                    },
                ],
            },
        ])
        expect(be1Cell.groups.map(group => [group.branch, group.display_code, group.class])).toEqual([
            ['wirtschaftskundlich', 'BE1 *', 'subject-plan-cell--wirtschaftskundlich'],
            ['gymnasial', 'BE1', 'subject-plan-cell--gymnasial'],
        ])

        context.subjectRules
            .find(rule => rule.selection_key === 'arts_subject')
            .options.find(option => option.value === 'BE')
            .subject_keys = subjectKeys('wirtschaftskundlich', ['BE1'])

        expect(computed.subjectRuleImpactCards.call(context)[1]).toEqual({
            branch: 'gymnasial',
            label: 'Gymnasialer Zweig',
            required: [
                'BE1 und ME1',
                'BE2 und ME2',
            ],
            choices: [
                {
                    label: 'Sprache',
                    description: 'Latein, Französisch oder Spanisch bis zum Modul 7',
                },
            ],
        })
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

    it('keeps the module shell navigation focused on the configured timetable version', () => {
        const computed = (StudentsTimetables as any).computed
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/StudentsTimetables.vue',
            'utf8',
        )
        const testsV3Source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/testsV3/TestsV3.vue',
            'utf8',
        )

        expect(componentSource).not.toContain("key: 'timetable'")
        expect(componentSource).toContain("key: 'subjects-overview'")
        expect(componentSource).toContain("key: 'tt-entries'")
        expect(componentSource).toContain("key: 'imports'")
        expect(componentSource).toContain("roles: ['super_admin', 'admin', 'studentstimetables_admin', 'studentstimetables_moderator']")
        expect(componentSource).toContain("roles: ['super_admin', 'admin', 'studentstimetables_admin']")
        expect(componentSource).toContain('canAccessNavigationItem(item)')
        expect(componentSource).not.toContain("meta: 'Center'")
        expect(componentSource).toContain("label: 'Stundenplan v2'")
        expect(componentSource).toContain("label: 'Stundenplan v3'")
        expect(componentSource).toContain("label: 'Tests v3'")
        expect(testsV3Source).toContain('Tests für Stundenplan Version 3')
        expect(componentSource).toContain("meta: 'Stabil'")
        expect(componentSource).toContain("meta: 'Entwicklung'")
        expect(componentSource).toContain("label: 'TT-Einträge'")
        expect(componentSource).toContain("meta: 'Kurse'")
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
        expect(componentSource).toContain('/admin/students-timetables/subjects-overview/subject-plan-v2')
        expect(componentSource).toContain("const mainSectionKeys = ['timetable', 'timetable-v2', 'timetable-v3', 'tt-entries', 'tests-v3', 'subjects-overview', 'import']")
        expect(componentSource).toContain("const TIMETABLE_OVERVIEW_PATH = '/admin/students-timetables/timetable/overview'")
        expect(componentSource).toContain("const TIMETABLE_V2_OVERVIEW_PATH = '/admin/students-timetables/timetable-v2/overview'")
        expect(componentSource).toContain("const TIMETABLE_V3_OVERVIEW_PATH = '/admin/students-timetables/timetable-v3/overview'")
        expect(componentSource).toContain("const TT_ENTRIES_OVERVIEW_PATH = '/admin/students-timetables/tt-entries/overview'")
        expect(componentSource).toContain("const TESTS_V3_STUDENTS_PATH = '/admin/students-timetables/tests-v3/students'")
        expect(componentSource).toContain("redirectMissingSection()")
        expect(componentSource).toContain("redirectLegacySection(section)")
        expect(componentSource).toContain("this.$router.replace({ path: TIMETABLE_OVERVIEW_PATH })")
        expect(componentSource).toContain("this.$router.replace({ path: this.activeTimetablePath })")
        expect(componentSource).not.toContain("this.$router.replace({ path: '/admin/students-timetables' })")
        expect(componentSource).toContain('const AUTOMATIC_TIMETABLE_OVERVIEW_PATH = `${TIMETABLE_OVERVIEW_PATH}/automatic`')
        expect(componentSource).toContain('this.$router.replace({ path: AUTOMATIC_TIMETABLE_OVERVIEW_PATH })')
        expect(componentSource).toContain("activeNavigationKey === item.key")
        expect(componentSource).toContain('timetable: TIMETABLE_OVERVIEW_PATH')
        expect(componentSource).toContain("'timetable-v2': TIMETABLE_V2_OVERVIEW_PATH")
        expect(componentSource).toContain("'timetable-v3': TIMETABLE_V3_OVERVIEW_PATH")
        expect(componentSource).toContain("'tt-entries': TT_ENTRIES_OVERVIEW_PATH")
        expect(componentSource).toContain("'tests-v3': TESTS_V3_STUDENTS_PATH")
        expect(componentSource).toContain("imports: '/admin/students-timetables/timetable/imports'")
        expect(componentSource).toContain("import('./timetableV2/TimetableV2.vue')")
        expect(componentSource).toContain("import('./timetableV3/TimetableV3.vue')")
        expect(componentSource).toContain("import('./testsV3/TestsV3.vue')")
        expect(componentSource).toContain("import('./ttEntries/TtEntries.vue')")
        expect(componentSource).toContain("import('./subjectsOverview/SubjectsOverview.vue')")
        expect(componentSource).not.toContain("import('./overview/Overview.vue')")
        expect(componentSource).not.toContain("import('./robot/RobotTimetable.vue')")
        expect(componentSource).toContain("<v-col v-if=\"main_action === 'timetable-v2'\" cols=\"12\">")
        expect(componentSource).toContain('<TimetableV2 />')
        expect(componentSource).toContain("<v-col v-if=\"main_action === 'timetable-v3'\" cols=\"12\">")
        expect(componentSource).toContain('<TimetableV3 />')
        expect(componentSource).toContain("<TestsV3 v-if=\"main_action === 'tests-v3'\" />")
        expect(componentSource).toContain("<v-col v-if=\"main_action === 'tt-entries'\" cols=\"12\">")
        expect(componentSource).toContain('<TtEntries />')
        expect(componentSource).toContain("Import v-if=\"main_action === 'import'\"")
        expect(componentSource).not.toContain("RobotTimetable v-if=\"main_action === 'robot'\"")
        expect(componentSource).toContain("SubjectsOverview v-if=\"main_action === 'subjects-overview'\"")
        expect(componentSource).not.toContain("Overview v-if=\"main_action === 'overview'\"")
        expect(componentSource).not.toContain('students-timetables-hero__schoolyear')
        expect(componentSource).not.toContain('useSchoolyearStore')
        expect(componentSource).not.toContain('switchSchoolyear')
        expect(componentSource.indexOf("key: 'timetable-v3'"))
            .toBeLessThan(componentSource.indexOf("key: 'tests-v3'"))
        expect(componentSource.indexOf("key: 'tests-v3'"))
            .toBeLessThan(componentSource.indexOf("key: 'tt-entries'"))
        expect(componentSource.indexOf("key: 'tt-entries'"))
            .toBeLessThan(componentSource.indexOf("key: 'imports'"))
        expect(componentSource.indexOf("key: 'imports'"))
            .toBeLessThan(componentSource.indexOf("key: 'subjects-overview'"))
        expect(componentSource.indexOf("key: 'subjects-overview'"))
            .toBeLessThan(componentSource.indexOf("key: 'timetable-v2'"))
        expect(computed.allNavigationItems.call({}).at(-1)).toMatchObject({
            key: 'timetable-v2',
            label: 'Stundenplan v2',
        })
        expect(componentSource).not.toContain('st-nav__button--legacy')
    })

    it('uses timetable v3 when no school-specific default is configured', () => {
        const computed = (StudentsTimetables as any).computed
        const data = (StudentsTimetables as any).data()
        const ctx: any = {
            config: {
                students_timetables: {},
            },
        }

        expect(data.main_action).toBe('timetable-v3')
        expect(computed.activeTimetableVersion.call(ctx)).toBe('v3')

        ctx.config.students_timetables.admin_version = 'v2'

        expect(computed.activeTimetableVersion.call(ctx)).toBe('v2')
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

    it('writes the configured default timetable step into the URL', () => {
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
            activeTimetableKey: 'timetable-v3',
            activeTimetablePath: '/admin/students-timetables/timetable-v3/overview',
        }

        expect(methods.redirectMissingSection.call(ctx)).toBe(true)
        expect(ctx.main_action).toBe('timetable-v3')
        expect(replace).toHaveBeenCalledWith({ path: '/admin/students-timetables/timetable-v3/overview' })
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

    it('opens the standalone timetable v3 page from the module navigation', () => {
        const methods = (StudentsTimetables as any).methods
        const push = vi.fn()
        const ctx: any = {
            $router: {
                push,
            },
            main_action: 'timetable-v2',
        }

        methods.handleNavigation.call(ctx, 'timetable-v3')

        expect(ctx.main_action).toBe('timetable-v3')
        expect(push).toHaveBeenCalledWith({ path: '/admin/students-timetables/timetable-v3/overview' })
    })

    it('opens the timetable v3 tests page on the students subsection', () => {
        const methods = (StudentsTimetables as any).methods
        const push = vi.fn()
        const ctx: any = {
            $router: { push },
            main_action: 'timetable-v2',
        }

        methods.handleNavigation.call(ctx, 'tests-v3')

        expect(ctx.main_action).toBe('tests-v3')
        expect(push).toHaveBeenCalledWith({ path: '/admin/students-timetables/tests-v3/students' })
    })

    it('provides the students selection and carries it into the tests subsection', () => {
        const computed = (TestsV3 as any).computed
        const methods = (TestsV3 as any).methods
        const push = vi.fn()
        const replace = vi.fn()
        const context: any = {
            ...methods,
            config: {
                selected_schoolyear: {
                    concerns: '2026/27',
                    name: 'Schuljahr 2026/27',
                },
            },
            $route: {
                params: {
                    section: 'tests-v3',
                    subsection: 'overview',
                },
            },
            $router: { push, replace },
            testsV3Action: 'students',
            selectedStudentKeys: ['1001'],
        }

        expect(computed.testsV3NavigationItems.call(context)).toEqual([
            {
                key: 'students',
                label: 'Studierende',
                icon: 'mdi-account-school-outline',
            },
            {
                key: 'tests',
                label: 'Tests',
                icon: 'mdi-test-tube',
            },
        ])
        expect(computed.personalSchoolyearLabel.call(context)).toBe('2026/27')
        expect(methods.redirectInvalidTestsV3Route.call(context)).toBe(true)
        expect(replace).toHaveBeenCalledWith({ path: '/admin/students-timetables/tests-v3/students' })

        methods.handleTestsV3Navigation.call(context, 'tests')

        expect(context.testsV3Action).toBe('tests')
        expect(context.selectedStudentKeys).toEqual(['1001'])
        expect(push).toHaveBeenCalledWith({ path: '/admin/students-timetables/tests-v3/tests' })

        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/testsV3/TestsV3.vue',
            'utf8',
        )

        expect(componentSource).toContain('Tests für Stundenplan Version 3')
        expect(componentSource).toContain(
            '<strong class="tests-v3-page__schoolyear text-primary">{{ personalSchoolyearLabel }}</strong>',
        )
        expect(componentSource).toContain("v-if=\"testsV3Action === 'students'\"")
        expect(componentSource).toContain("v-else-if=\"testsV3Action === 'tests'\"")
        expect(componentSource).toContain('Module nach Semester')
        expect(componentSource).not.toContain('Soll-Module nach Semester')
        expect(componentSource).toContain('{{ semester.semester }}. Semester')
        expect(componentSource).toContain('{{ module.code }}')
        expect(componentSource).toContain('Normalstudium')
        expect(componentSource).toContain('Kompaktstudium')
        expect(componentSource).toContain('v-model="expandedStudyPlans"')
        expect(componentSource).toContain('<v-expansion-panel')
        expect(componentSource).toContain('multiple')
        expect(componentSource).toContain('expandedStudyPlans: []')
        expect(componentSource.indexOf('class="tests-v3-study-plans"'))
            .toBeLessThan(componentSource.indexOf('class="tests-v3-selection"'))
        expect(componentSource).toContain('Ausgewählte Studierende')
        expect(componentSource).toContain('{{ selectedStudents.length }} Studierende für die Tests übernommen')
        expect(componentSource).toContain('v-for="student in selectedStudents"')
        expect(componentSource).toContain('class="tests-v3-students__table tests-v3-selection__table"')
        const selectedStudentsTableClassIndex = componentSource.indexOf(
            'class="tests-v3-students__table tests-v3-selection__table"',
        )
        const selectedStudentsTableStartIndex = componentSource.lastIndexOf('<v-table', selectedStudentsTableClassIndex)
        const selectedStudentsTableEndIndex = componentSource.indexOf('</v-table>', selectedStudentsTableClassIndex)
        const selectedStudentsTableSource = componentSource.slice(
            selectedStudentsTableStartIndex,
            selectedStudentsTableEndIndex + 10,
        )

        expect(selectedStudentsTableStartIndex).toBeGreaterThan(-1)
        expect(selectedStudentsTableEndIndex).toBeGreaterThan(-1)
        expect(selectedStudentsTableSource).not.toContain('>Befreit/Bestanden</th>')
        expect(selectedStudentsTableSource).not.toContain('>Negativ</th>')
        expect(selectedStudentsTableSource).not.toContain("studentCourseResultItems(student, 'completed')")
        expect(selectedStudentsTableSource).not.toContain("studentCourseResultItems(student, 'negative')")
        expect(selectedStudentsTableSource).not.toContain('tests-v3-selection__study-plan')
        expect(selectedStudentsTableSource).toContain('<td colspan="5" class="tests-v3-selection__module-test">')
        expect(selectedStudentsTableSource).toContain('aria-label="Teststatus"')
        expect(selectedStudentsTableSource).toContain('v-bind="studentV3TestStatusPresentation(student)"')
        expect(selectedStudentsTableSource).not.toContain('icon="mdi-checkbox-marked"')
        expect(componentSource).toContain("'tests-v3-students__row--selected'")
        const runTestsButtonClassIndex = componentSource.indexOf('class="tests-v3-selection__run-button"')
        const runTestsButtonStartIndex = componentSource.lastIndexOf('<v-btn', runTestsButtonClassIndex)
        const runTestsButtonEndIndex = componentSource.indexOf('</v-btn>', runTestsButtonClassIndex)
        const runTestsButtonSource = componentSource.slice(runTestsButtonStartIndex, runTestsButtonEndIndex + 8)

        expect(runTestsButtonClassIndex).toBeGreaterThan(-1)
        expect(runTestsButtonStartIndex).toBeGreaterThan(-1)
        expect(runTestsButtonEndIndex).toBeGreaterThan(-1)
        expect(runTestsButtonSource).toContain('Run Tests')
        expect(runTestsButtonSource).toContain('@click="runTests"')
        expect(runTestsButtonSource).toContain(':loading="studentV3TestsRunning"')
        expect(runTestsButtonSource).not.toContain('href=')
        expect(runTestsButtonSource).not.toContain('to=')
        expect(componentSource).toContain('data-testid="student-v3-test-summary-dialog"')
        expect(componentSource).toContain('v-model="studentV3TestSummaryDialog"')
        expect(componentSource).toContain('persistent')
        expect(componentSource).toContain('Testzusammenfassung')
        expect(componentSource).toContain('Schließen')
        expect(componentSource).toContain('V3-Modultest')
        expect(componentSource).not.toContain('class="tests-v3-selection__study-plan-row"')
        expect(componentSource).toContain('class="tests-v3-selection__module-test-row"')
        expect(componentSource).toContain('<td colspan="5" class="tests-v3-selection__module-test">')
        expect(componentSource).not.toContain('<th class="tests-v3-selection__module-test-column">')
        expect(componentSource).toContain('runV3StudentModuleTests')
        expect(componentSource).toContain('axios.post(runV3StudentModuleTests.url()')
        expect(componentSource).toContain('STUDENT_V3_TEST_BATCH_SIZE = 10')
        expect(componentSource).toContain(':model-value="studentV3TestProgressPercentage"')
        expect(componentSource).toContain('{{ runningStudentV3TestCount }} in Bearbeitung')
        expect(componentSource).toContain("{ key: 'finished', label: 'Abgeschlossene', color: 'success' }")
        expect(componentSource).toContain(
            "v-if=\"['finished', 'negative', 'previous', 'current', 'additional'].includes(group.key)\"",
        )
        expect(componentSource).toContain(
            "const STUDENT_V3_TEST_COMPARISON_GROUP_KEYS = ['finished', 'negative', 'previous', 'current', 'additional']",
        )
        expect(componentSource).toContain("studentModuleGroupMatches(student, group.key) ? 'OK' : 'FAIL'")
        expect(componentSource).toContain('class="tests-v3-selection__module-match"')
        expect(componentSource).toContain('class="tests-v3-selection__module-comparison"')
        expect(componentSource).toContain('class="tests-v3-selection__module-comparison-divider"')
        expect(componentSource).toContain('class="tests-v3-selection__module-comparison-result"')
        expect(componentSource).toContain('Soll-Module')
        expect(componentSource).toContain('Abweichende Module')
        expect(componentSource).toContain(
            '<template v-if="studentModuleGroupMismatches(student, group.key).length">',
        )
        expect(componentSource).not.toContain(
            'v-if="!studentModuleGroupMismatches(student, group.key).length"',
        )
        expect(componentSource)
            .toMatch(/\.tests-v3-selection__module-group\s*\{[^}]*display:\s*flex;[^}]*flex-direction:\s*column;/s)
        expect(componentSource)
            .toMatch(/\.tests-v3-selection__module-comparison-result\s*\{[^}]*margin-top:\s*auto;/s)
        expect(componentSource).toContain('<template v-if="module.grade"> (<strong')
        expect(componentSource).toContain("group.key === 'finished'")
        expect(componentSource).toContain("'tests-v3-students__course-result-grade--completed'")
        expect(componentSource).toContain("'tests-v3-students__course-result-grade--negative'")
        expect(componentSource).toContain('{{ module.grade }}</strong>)</template>')
        expect(componentSource).toContain('studentModuleGroupMismatches(student, group.key)')
        expect(componentSource.indexOf('class="tests-v3-selection__module-comparison-divider"'))
            .toBeLessThan(componentSource.indexOf('class="tests-v3-selection__module-comparison-result"'))
        expect(componentSource).toContain("{ key: 'negative', label: 'Negative', color: 'error' }")
        expect(componentSource).toContain("{ key: 'previous', label: 'Frühere', color: 'warning' }")
        expect(componentSource).toContain("{ key: 'current', label: 'Aktuelle', color: 'primary' }")
        expect(componentSource).toContain("{ key: 'additional', label: 'Zusätzliche', color: 'info' }")
        expect(componentSource).toContain('Alle auswählen')
        expect(componentSource).toContain('Keine auswählen')
        expect(componentSource).toContain('v-for="studentClass in studentClasses"')
        expect(componentSource).toContain('@click="toggleStudentClassSelection(studentClass)"')
        expect(componentSource).toContain('<v-checkbox-btn')
        expect(componentSource).toContain('<th class="tests-v3-students__class-column">Klasse</th>')
        expect(componentSource).toContain('<th class="tests-v3-students__name-column">Name</th>')
        expect(componentSource).toContain('Studienauswahl')
        expect(componentSource).toContain('<th class="tests-v3-students__semester-column">Semester</th>')
        expect(componentSource.indexOf('Studienauswahl'))
            .toBeLessThan(componentSource.indexOf('<th class="tests-v3-students__semester-column">Semester</th>'))
        expect(componentSource.indexOf('<th class="tests-v3-students__semester-column">Semester</th>'))
            .toBeLessThan(componentSource.indexOf('Befreit/Bestanden'))
        expect(componentSource).toContain('Befreit/Bestanden')
        expect(componentSource).toContain('Negativ')
        expect(componentSource).toContain("studentCourseResultItems(student, 'completed')")
        expect(componentSource).toContain("studentCourseResultItems(student, 'negative')")
        expect(componentSource).toContain('<td class="tests-v3-students__selection-column">')
        expect(componentSource).toContain('table-layout: fixed')
        expect(componentSource).toContain('padding-inline: 10px !important')
        expect(componentSource).toContain('overflow-wrap: anywhere')
        expect(componentSource).toMatch(/\.tests-v3-students__selection-column\s*\{[^}]*width:\s*5%;/s)
        expect(componentSource).toMatch(/\.tests-v3-students__class-column\s*\{[^}]*width:\s*5%;/s)
        expect(componentSource).toMatch(/\.tests-v3-students__name-column\s*\{[^}]*width:\s*21%;/s)
        expect(componentSource).toMatch(/\.tests-v3-students__study-selection-column\s*\{[^}]*width:\s*13%;/s)
        expect(componentSource).toMatch(/\.tests-v3-students__semester-column\s*\{[^}]*width:\s*8%;/s)
        expect(componentSource).toMatch(/\.tests-v3-students__course-results-column\s*\{[^}]*width:\s*24%;/s)
        expect(componentSource).toMatch(/\.tests-v3-students__course-results\s*\{[^}]*font-weight:\s*400;/s)
        expect(componentSource).toMatch(/\.tests-v3-students__course-result-grade\s*\{[^}]*font-weight:\s*700;/s)
        expect(componentSource).toMatch(/\.tests-v3-students__course-result-grade--completed\s*\{[^}]*--v-theme-success/s)
        expect(componentSource).toMatch(/\.tests-v3-students__course-result-grade--negative\s*\{[^}]*--v-theme-error/s)
        expect(componentSource).toContain('robotStudents as loadRobotStudents')
        expect(componentSource).toContain('studentReligionLabel(student)')
        expect(componentSource).toContain('studentSexPresentation(student)')
        expect(componentSource).toContain('mdi-gender-male')
        expect(componentSource).toContain('mdi-gender-female')
        expect(componentSource).toContain('Falsche Daten – Test übersprungen')
        expect(componentSource).toContain('studentDataQualityIssues(student)')
        expect(componentSource).toContain("status: 'invalid_data'")
        expect(componentSource).toContain('Nicht getestete Datensätze')
    })

    it('loads and groups both personal-schoolyear study plans by semester', async () => {
        const computed = (TestsV3 as any).computed
        const methods = (TestsV3 as any).methods
        const get = vi.fn((url: string) => Promise.resolve({
            data: {
                data: {
                    subjects: url.includes('/kompaktstudium')
                        ? [
                            { semester: 1, json_code: 'D2', name: 'Deutsch 2', is_active: true },
                            {
                                semester: 3,
                                json_code: 'ÖKO2',
                                name: 'Ökonomie und Ökologie 2',
                                branch: 'wirtschaftskundlich',
                                is_active: true,
                            },
                        ]
                        : [
                            { semester: 1, json_code: 'D1', name: 'Deutsch 1', is_active: true },
                            { semester: 2, json_code: 'M2', name: 'Mathematik 2', is_active: true },
                            { semester: 2, json_code: 'OLD', name: 'Inaktiv', is_active: false },
                        ],
                },
            },
        }))
        const context: any = {
            studyPlanRows: {
                normalstudium: [],
                kompaktstudium: [],
            },
            studyPlansLoading: false,
            studyPlansLoaded: false,
            studyPlansError: '',
        }

        vi.stubGlobal('axios', { get })

        try {
            await methods.loadStudyPlans.call(context)
        } finally {
            vi.unstubAllGlobals()
        }

        expect(get).toHaveBeenCalledTimes(2)
        expect(get.mock.calls.map(([url]) => url)).toEqual([
            '/api/admin/students-timetables/subjects-overview-settings/normalstudium?schoolyear_scope=personal&subjects_only=1',
            '/api/admin/students-timetables/subjects-overview-settings/kompaktstudium?schoolyear_scope=personal&subjects_only=1',
        ])
        expect(context.studyPlansLoaded).toBe(true)
        expect(context.studyPlansError).toBe('')

        const studyPlanSections = computed.studyPlanSections.call(context)

        expect(studyPlanSections.map((studyPlan: any) => studyPlan.label)).toEqual([
            'Normalstudium',
            'Kompaktstudium',
        ])
        expect(studyPlanSections[1]).toMatchObject({
            icon: 'mdi-calendar-collapse-horizontal-outline',
        })
        expect(studyPlanSections[0].semesters.map((semester: any) => [
            semester.semester,
            semester.modules.map((module: any) => module.code),
        ])).toEqual([
            [1, ['D1']],
            [2, ['M2']],
        ])
        expect(studyPlanSections[1].semesters[1].modules[0]).toMatchObject({
            code: 'ÖKO2',
            branchLabel: 'WIKU',
        })

        const studentStudyPlanContext: any = {
            ...methods,
            studyPlanSections,
        }

        expect(methods.studentExpectedModules.call(studentStudyPlanContext, {
            student_code: '1001',
            expected_modules: [
                { code: 'E1', name: 'Englisch 1', semester: 1 },
                { code: 'BU2', name: 'Biologie 2', semester: 2 },
            ],
        })).toEqual([
            expect.objectContaining({ code: 'E1', semester: 1 }),
            expect.objectContaining({ code: 'BU2', semester: 2 }),
        ])
        expect(methods.studentExpectedModules.call(studentStudyPlanContext, {
            student_code: '1001',
        })).toBeNull()
    })

    it('runs the existing v3 module calculation for selected students in one batch request', async () => {
        const methods = (TestsV3 as any).methods
        const students = [
            {
                student_code: '1001',
                class: '1A',
                semester: 2,
                last_name: 'Auer',
                first_name: 'Anna',
                course_results: {
                    completed: [
                        { code: 'm1', grade: '2' },
                        { code: 'D1', grade: 'B' },
                    ],
                    negative: [{ code: 'e1', grade: '5' }],
                },
                expected_modules: [
                    { code: 'BU1', name: 'Biologie 1', semester: 1 },
                    { code: 'D2', name: 'Deutsch 2', semester: 2 },
                ],
                expected_additional_modules: [
                    { code: 'PH1', name: 'Physik 1', semester: 3 },
                ],
            },
            {
                student_code: '2002',
                class: '1A',
                semester: 1,
                last_name: 'Bauer',
                first_name: 'Berta',
                course_results: { completed: [], negative: [] },
                expected_modules: [{ code: 'D1', name: 'Deutsch 1', semester: 1 }],
                expected_additional_modules: [],
            },
        ]
        const firstStudentGroups = [
            {
                key: 'finished',
                count: 2,
                modules: [
                    { code: 'D1', name: 'Deutsch 1' },
                    { code: 'M1', name: 'Mathematik 1' },
                ],
            },
            { key: 'negative', count: 1, modules: [{ code: 'E1', name: 'Englisch 1' }] },
            { key: 'previous', count: 1, modules: [{ code: 'BU1', name: 'Biologie 1' }] },
            { key: 'current', count: 1, modules: [{ code: 'D2', name: 'Deutsch 2' }] },
            { key: 'additional', count: 1, modules: [{ code: 'PH1', name: 'Physik 1' }] },
        ]
        const secondStudentGroups = [
            { key: 'finished', count: 0, modules: [] },
            { key: 'negative', count: 0, modules: [] },
            { key: 'previous', count: 0, modules: [] },
            { key: 'current', count: 1, modules: [{ code: 'D1', name: 'Deutsch 1' }] },
            { key: 'additional', count: 0, modules: [] },
        ]
        let resolveBatchRequest: (value: unknown) => void = () => {}
        const batchRequest = new Promise((resolve) => {
            resolveBatchRequest = resolve
        })
        const post = vi.fn(() => batchRequest)
        const context: any = {
            ...methods,
            selectedStudents: students,
            studentV3TestResults: {},
            studentV3TestsRunning: false,
            studentV3TestSummaryDialog: true,
        }

        vi.stubGlobal('axios', { post })

        try {
            const testsPromise = methods.runTests.call(context)

            await vi.waitFor(() => expect(post).toHaveBeenCalledTimes(1))
            expect(context.studentV3TestResults['1001'].status).toBe('running')
            expect(context.studentV3TestResults['2002'].status).toBe('running')
            expect(context.studentV3TestSummaryDialog).toBe(false)

            resolveBatchRequest({
                data: {
                    data: [
                        { student_code: '1001', module_selection_groups: firstStudentGroups },
                        { student_code: '2002', module_selection_groups: secondStudentGroups },
                    ],
                },
            })

            await testsPromise
            expect(context.studentV3TestResults['1001'].status).toBe('complete')
            expect(context.studentV3TestResults['2002'].status).toBe('complete')
            expect(context.studentV3TestResults['1001'].groups.map((group: any) => [
                group.key,
                group.count,
                group.modules.map((module: any) => module.code),
            ])).toEqual([
                ['finished', 2, ['D1', 'M1']],
                ['negative', 1, ['E1']],
                ['previous', 1, ['BU1']],
                ['current', 1, ['D2']],
                ['additional', 1, ['PH1']],
            ])
            expect(methods.studentFinishedModulesMatch.call(context, students[0])).toBe(true)
            expect(methods.studentNegativeModulesMatch.call(context, students[0])).toBe(true)
            expect(methods.studentModuleGroupExpectedModules.call(context, students[0], 'finished')).toEqual([
                expect.objectContaining({ code: 'D1', grade: 'B' }),
                expect.objectContaining({ code: 'm1', grade: '2' }),
            ])
            expect(methods.studentModuleGroupMismatches.call(context, students[0], 'finished')).toEqual([])
            expect(methods.studentModuleGroupExpectedModules.call(context, students[0], 'negative')).toEqual([
                expect.objectContaining({ code: 'e1', grade: '5' }),
            ])
            expect(methods.studentModuleGroupMismatches.call(context, students[0], 'negative')).toEqual([])
            expect(methods.studentModuleGroupExpectedModules.call(context, {
                ...students[0],
                course_results: {
                    completed: [
                        ...students[0].course_results.completed,
                        { code: 'E1', grade: '2' },
                    ],
                    negative: [
                        { code: 'e1', grade: '5' },
                        { code: 'BU1', grade: '5' },
                    ],
                },
            }, 'negative')).toEqual([
                expect.objectContaining({ code: 'BU1', grade: '5' }),
            ])
            expect(methods.studentModuleGroupExpectedModules.call(context, students[0], 'previous')).toEqual([
                expect.objectContaining({ code: 'BU1', title: 'Biologie 1' }),
            ])
            expect(methods.studentModuleGroupExpectedModules.call(context, students[0], 'current')).toEqual([
                expect.objectContaining({ code: 'D2', title: 'Deutsch 2' }),
            ])
            expect(methods.studentModuleGroupExpectedModules.call(context, students[0], 'additional')).toEqual([
                expect.objectContaining({ code: 'PH1', title: 'Physik 1' }),
            ])
            expect(methods.studentModuleGroupMatches.call(context, students[0], 'previous')).toBe(true)
            expect(methods.studentModuleGroupMatches.call(context, students[0], 'current')).toBe(true)
            expect(methods.studentModuleGroupMatches.call(context, students[0], 'additional')).toBe(true)
            expect(methods.studentModuleGroupMismatches.call(context, students[0], 'previous')).toEqual([])
            expect(methods.studentModuleGroupMismatches.call(context, students[0], 'current')).toEqual([])
            expect(methods.studentModuleGroupMismatches.call(context, students[0], 'additional')).toEqual([])
            expect(methods.studentModuleGroupMismatches.call(context, {
                ...students[0],
                course_results: {
                    completed: [
                        { code: 'D1', grade: 'B' },
                        { code: 'BU1', grade: '2' },
                    ],
                    negative: students[0].course_results.negative,
                },
            }, 'finished')).toEqual([
                expect.objectContaining({ code: 'BU1', title: 'Fehlt im V3-Ergebnis' }),
                expect.objectContaining({ code: 'M1', title: 'Zusätzlich im V3-Ergebnis: Mathematik 1' }),
            ])
            expect(methods.studentFinishedModulesMatch.call(context, {
                ...students[0],
                course_results: {
                    completed: [{ code: 'D1', grade: 'B' }],
                    negative: [{ code: 'E1', grade: '5' }],
                },
            })).toBe(false)
            expect(methods.studentNegativeModulesMatch.call(context, {
                ...students[0],
                course_results: {
                    completed: students[0].course_results.completed,
                    negative: [{ code: 'BU1', grade: '5' }],
                },
            })).toBe(false)

        } finally {
            vi.unstubAllGlobals()
        }

        expect(context.studentV3TestResults['2002'].status).toBe('complete')
        expect(methods.studentFinishedModulesMatch.call(context, students[1])).toBe(true)
        expect(methods.studentNegativeModulesMatch.call(context, students[1])).toBe(true)
        expect(methods.studentModuleGroupMatches.call(context, students[1], 'additional')).toBe(true)
        expect(context.studentV3TestsRunning).toBe(false)
        expect(context.studentV3TestSummaryDialog).toBe(true)
        expect(post).toHaveBeenCalledWith(
            '/api/admin/students-timetables/timetable-v3/student-information',
            { student_codes: ['1001', '2002'] },
        )
        expect(() => methods.normalizedStudentV3TestGroups.call(context, firstStudentGroups.slice(0, 4)))
            .toThrow('Die V3-Gruppe additional fehlt.')
        expect(() => methods.normalizedStudentV3TestGroups.call(context, [
            { ...firstStudentGroups[0], count: 99 },
            ...firstStudentGroups.slice(1),
        ])).toThrow('Der V3-Count für finished ist inkonsistent.')
    })

    it('summarizes passed, mismatching, and errored timetable v3 tests for the completion dialog', () => {
        const computed = (TestsV3 as any).computed
        const methods = (TestsV3 as any).methods
        const emptyGroups = [
            { key: 'finished', count: 0, modules: [] },
            { key: 'negative', count: 0, modules: [] },
            { key: 'previous', count: 0, modules: [] },
            { key: 'current', count: 0, modules: [] },
            { key: 'additional', count: 0, modules: [] },
        ]
        const selectedStudents = [
            {
                student_code: '1001',
                class: '1A',
                last_name: 'Auer',
                first_name: 'Anna',
                semester: 1,
                course_results: { completed: [], negative: [] },
                expected_modules: [],
                expected_additional_modules: [],
            },
            {
                student_code: '2002',
                class: '1B',
                last_name: 'Bauer',
                first_name: 'Berta',
                semester: 1,
                course_results: { completed: [], negative: [] },
                expected_modules: [{ code: 'D1', name: 'Deutsch 1', semester: 1 }],
                expected_additional_modules: [],
            },
            {
                student_code: '3003',
                class: '1C',
                last_name: 'Celik',
                first_name: 'Cem',
                semester: 1,
                course_results: { completed: [], negative: [] },
                expected_modules: [],
                expected_additional_modules: [],
            },
        ]
        const context: any = {
            ...methods,
            selectedStudents,
            studentV3TestResults: {
                1001: { status: 'complete', message: '', groups: emptyGroups },
                2002: { status: 'complete', message: '', groups: emptyGroups },
                3003: { status: 'error', message: 'V3-Modulberechnung fehlgeschlagen.', groups: [] },
            },
        }

        context.completedStudentV3TestCount = computed.completedStudentV3TestCount.call(context)
        context.failedStudentV3TestCount = computed.failedStudentV3TestCount.call(context)
        context.invalidStudentV3TestCount = computed.invalidStudentV3TestCount.call(context)
        context.testedStudentV3TestCount = computed.testedStudentV3TestCount.call(context)

        expect(context.completedStudentV3TestCount).toBe(3)
        expect(context.failedStudentV3TestCount).toBe(2)
        expect(context.invalidStudentV3TestCount).toBe(0)
        expect(context.testedStudentV3TestCount).toBe(3)
        expect(computed.passedStudentV3TestCount.call(context)).toBe(1)
        expect(computed.failedStudentV3TestSummaries.call(context)).toEqual([
            {
                key: '2002',
                classLabel: '1B',
                studentName: 'Bauer Berta',
                message: 'Abweichungen: Aktuelle',
            },
            {
                key: '3003',
                classLabel: '1C',
                studentName: 'Celik Cem',
                message: 'V3-Modulberechnung fehlgeschlagen.',
            },
        ])
    })

    it('updates progress between small sequential timetable v3 test batches', async () => {
        const computed = (TestsV3 as any).computed
        const methods = (TestsV3 as any).methods
        const students = Array.from({ length: 21 }, (_, index) => ({
            student_code: String(index + 1).padStart(4, '0'),
        }))
        const emptyGroups = [
            { key: 'finished', count: 0, modules: [] },
            { key: 'negative', count: 0, modules: [] },
            { key: 'previous', count: 0, modules: [] },
            { key: 'current', count: 0, modules: [] },
            { key: 'additional', count: 0, modules: [] },
        ]
        const batchResolvers: Array<(value: unknown) => void> = []
        const post = vi.fn(() => new Promise(resolve => batchResolvers.push(resolve)))
        const context: any = {
            ...methods,
            selectedStudents: students,
            studentV3TestResults: {},
            studentV3TestsRunning: false,
            studentV3TestSummaryDialog: true,
        }
        const resolveBatch = (batchIndex: number, batchStudents: any[]) => {
            batchResolvers[batchIndex]({
                data: {
                    data: batchStudents.map(student => ({
                        student_code: student.student_code,
                        module_selection_groups: emptyGroups,
                    })),
                },
            })
        }
        const updateProgressCounts = () => {
            context.completedStudentV3TestCount = computed.completedStudentV3TestCount.call(context)
            context.runningStudentV3TestCount = computed.runningStudentV3TestCount.call(context)
        }

        vi.stubGlobal('axios', { post })

        try {
            const testsPromise = methods.runTests.call(context)

            await vi.waitFor(() => expect(post).toHaveBeenCalledTimes(1))
            updateProgressCounts()
            expect(post.mock.calls[0][1].student_codes).toHaveLength(10)
            expect(context.completedStudentV3TestCount).toBe(0)
            expect(context.runningStudentV3TestCount).toBe(10)
            expect(computed.studentV3TestProgressPercentage.call(context)).toBe(0)

            resolveBatch(0, students.slice(0, 10))
            await vi.waitFor(() => expect(post).toHaveBeenCalledTimes(2))
            updateProgressCounts()
            expect(post.mock.calls[1][1].student_codes).toHaveLength(10)
            expect(context.completedStudentV3TestCount).toBe(10)
            expect(context.runningStudentV3TestCount).toBe(10)
            expect(computed.studentV3TestProgressPercentage.call(context)).toBe(48)

            resolveBatch(1, students.slice(10, 20))
            await vi.waitFor(() => expect(post).toHaveBeenCalledTimes(3))
            updateProgressCounts()
            expect(post.mock.calls[2][1].student_codes).toHaveLength(1)
            expect(context.completedStudentV3TestCount).toBe(20)
            expect(context.runningStudentV3TestCount).toBe(1)
            expect(computed.studentV3TestProgressPercentage.call(context)).toBe(95)

            resolveBatch(2, students.slice(20))
            await testsPromise
            updateProgressCounts()
            expect(context.completedStudentV3TestCount).toBe(21)
            expect(context.runningStudentV3TestCount).toBe(0)
            expect(computed.studentV3TestProgressPercentage.call(context)).toBe(100)
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('skips wrong student data when running timetable v3 batches', async () => {
        const methods = (TestsV3 as any).methods
        const computed = (TestsV3 as any).computed
        const emptyGroups = [
            { key: 'finished', count: 0, modules: [] },
            { key: 'negative', count: 0, modules: [] },
            { key: 'previous', count: 0, modules: [] },
            { key: 'current', count: 0, modules: [] },
            { key: 'additional', count: 0, modules: [] },
        ]
        const invalidStudent = {
            student_code: 'invalid',
            data_quality_issues: [
                'Falscher Datensatz: Im Kompaktstudium ist die Schulstufe 10_1 nicht zulässig.',
            ],
        }
        const validStudent = { student_code: 'valid', data_quality_issues: [] }
        const post = vi.fn().mockResolvedValue({
            data: {
                data: [{
                    student_code: 'valid',
                    module_selection_groups: emptyGroups,
                }],
            },
        })
        const context: any = {
            ...methods,
            studentV3TestResults: {},
        }

        vi.stubGlobal('axios', { post })

        try {
            await methods.runStudentV3TestBatch.call(context, [invalidStudent, validStudent])
        } finally {
            vi.unstubAllGlobals()
        }

        expect(post).toHaveBeenCalledOnce()
        expect(post.mock.calls[0][1]).toEqual({ student_codes: ['valid'] })
        expect(context.studentV3TestResults.invalid).toEqual({
            status: 'invalid_data',
            message: 'Falscher Datensatz: Im Kompaktstudium ist die Schulstufe 10_1 nicht zulässig.',
            groups: [],
        })
        expect(methods.studentV3TestStatusPresentation.call(context, invalidStudent)).toMatchObject({
            icon: 'mdi-database-alert',
            color: 'error',
            'aria-label': 'Falsche Daten – Test übersprungen',
            'data-test-status': 'invalid-data',
        })
        expect(context.studentV3TestResults.valid.status).toBe('complete')

        context.selectedStudents = [invalidStudent, validStudent]
        context.completedStudentV3TestCount = computed.completedStudentV3TestCount.call(context)
        context.invalidStudentV3TestCount = computed.invalidStudentV3TestCount.call(context)

        expect(context.completedStudentV3TestCount).toBe(2)
        expect(context.invalidStudentV3TestCount).toBe(1)
        expect(computed.testedStudentV3TestCount.call(context)).toBe(1)
    })

    it('keeps unresolved semester defaults in the current module comparison', () => {
        const methods = (TestsV3 as any).methods
        const student = {
            student_code: '50112620250130',
            semester: 1,
            course_results: {
                completed: [],
                negative: [
                    { code: 'D2', grade: 'N' },
                    { code: 'D3', grade: 'N' },
                ],
            },
            expected_modules: [
                { code: 'D1', name: 'Deutsch 1', semester: 1 },
                { code: 'INF1', name: 'Informatik 1', semester: 1 },
                { code: 'LPT', name: 'Lern- Präsentationstechnik', semester: 1 },
            ],
            expected_additional_modules: [
                { code: 'D4', name: 'Deutsch 4', semester: 4 },
            ],
        }
        const context: any = {
            ...methods,
            studentV3TestResults: {
                50112620250130: {
                    status: 'complete',
                    groups: [
                        {
                            key: 'current',
                            modules: [
                                { code: 'D1', name: 'Deutsch 1' },
                                { code: 'INF1', name: 'Informatik 1' },
                                { code: 'LPT1', name: 'Lern- Präsentationstechnik' },
                            ],
                        },
                        { key: 'additional', modules: [{ code: 'D4', name: 'Deutsch 4' }] },
                    ],
                },
            },
        }

        expect(methods.studentModuleGroupExpectedModules.call(context, student, 'current'))
            .toEqual([
                expect.objectContaining({ code: 'D1' }),
                expect.objectContaining({ code: 'INF1' }),
                expect.objectContaining({ code: 'LPT' }),
            ])
        expect(methods.studentModuleGroupExpectedModules.call(context, student, 'additional'))
            .toEqual([expect.objectContaining({ code: 'D4' })])
        expect(methods.normalizedStudentModuleComparisonCode.call(context, 'LPT')).toBe('LPT')
        expect(methods.normalizedStudentModuleComparisonCode.call(context, 'LPT1')).toBe('LPT')
        expect(methods.normalizedStudentModuleComparisonCode.call(context, 'LET1')).toBe('LPT')
        expect(methods.normalizedStudentModuleComparisonCode.call(context, 'LPT2')).toBe('LPT2')
        expect(methods.studentModuleGroupMatches.call(context, student, 'current')).toBe(true)
        expect(methods.studentModuleGroupMismatches.call(context, student, 'current')).toEqual([])
    })

    it('compares semester defaults and progression modules only when their payload is available', () => {
        const methods = (TestsV3 as any).methods
        const context: any = { ...methods }
        const semesterDefaultsOnly = {
            student_code: 'semester-defaults',
            semester: 1,
            expected_modules: [],
        }
        const progressionOnly = {
            student_code: 'progression',
            semester: 1,
            expected_additional_modules: [],
        }

        expect(methods.studentModuleGroupCanCompare.call(context, semesterDefaultsOnly, 'previous')).toBe(true)
        expect(methods.studentModuleGroupCanCompare.call(context, semesterDefaultsOnly, 'current')).toBe(true)
        expect(methods.studentModuleGroupCanCompare.call(context, semesterDefaultsOnly, 'additional')).toBe(false)
        expect(methods.studentModuleGroupCanCompare.call(context, progressionOnly, 'previous')).toBe(false)
        expect(methods.studentModuleGroupCanCompare.call(context, progressionOnly, 'current')).toBe(false)
        expect(methods.studentModuleGroupCanCompare.call(context, progressionOnly, 'additional')).toBe(true)
    })

    it('presents the selected student test status before the student', () => {
        const computed = (TestsV3 as any).computed
        const methods = (TestsV3 as any).methods
        const student = { student_code: '1001' }
        const context: any = {
            ...methods,
            selectedStudents: [student],
            studentV3TestResults: {},
            studentModuleGroupMatches: vi.fn(() => true),
        }

        expect(methods.studentV3TestStatusPresentation.call(context, student)).toMatchObject({
            icon: 'mdi-circle-outline',
            color: 'grey-darken-1',
            'aria-label': 'Noch kein Test gestartet',
        })

        context.studentV3TestResults['1001'] = { status: 'pending', groups: [] }
        expect(methods.studentV3TestStatusPresentation.call(context, student)).toMatchObject({
            icon: 'mdi-clock-outline',
            color: 'warning',
            'aria-label': 'Wartet auf Test',
        })

        context.studentV3TestResults['1001'] = { status: 'running', groups: [] }
        expect(methods.studentV3TestStatusPresentation.call(context, student)).toMatchObject({
            icon: 'mdi-progress-clock',
            color: 'primary',
            'aria-label': 'Test läuft',
        })

        context.studentV3TestResults['1001'] = { status: 'complete', groups: [] }
        expect(methods.studentV3TestStatusPresentation.call(context, student)).toMatchObject({
            icon: 'mdi-check-circle',
            color: 'success',
            'aria-label': 'Test gültig',
        })
        expect(computed.failedStudentV3TestCount.call(context)).toBe(0)

        context.studentModuleGroupMatches = vi.fn((_student, groupKey) => groupKey !== 'current')
        expect(methods.studentV3TestStatusPresentation.call(context, student)).toMatchObject({
            icon: 'mdi-alert-circle',
            color: 'error',
            'aria-label': 'Test fehlgeschlagen',
        })
        expect(computed.failedStudentV3TestCount.call(context)).toBe(1)

        context.studentV3TestResults['1001'] = { status: 'error', groups: [] }
        expect(methods.studentV3TestStatusPresentation.call(context, student)).toMatchObject({
            icon: 'mdi-alert-circle',
            color: 'error',
            'aria-label': 'Test fehlgeschlagen',
        })
    })

    it.each(['Rev1', 'Ris1', 'Rk1', 'Ror1'])(
        'treats the specific religion module %s as generic R1 in negative comparisons',
        (specificReligionModule) => {
            const methods = (TestsV3 as any).methods
            const student = {
                student_code: '1001',
                course_results: {
                    negative: [{ code: 'R1', grade: '5' }],
                },
            }
            const context: any = {
                ...methods,
                studentV3TestResults: {
                    1001: {
                        status: 'complete',
                        groups: [
                            {
                                key: 'negative',
                                modules: [{ code: specificReligionModule }],
                            },
                        ],
                    },
                },
            }

            expect(methods.studentNegativeModulesMatch.call(context, student)).toBe(true)
        },
    )

    it('loads, sorts, and selects all or no timetable v3 students', async () => {
        const computed = (TestsV3 as any).computed
        const methods = (TestsV3 as any).methods
        const students = [
            {
                id: 3,
                student_code: '3003',
                class: '2A',
                last_name: 'Zeller',
                first_name: 'Anna',
            },
            {
                id: 2,
                student_code: '2002',
                class: '1B',
                last_name: 'Zorn',
                first_name: 'Berta',
            },
            {
                id: 1,
                student_code: '1001',
                class: '1B',
                last_name: 'Auer',
                first_name: 'Clara',
            },
        ]
        const get = vi.fn().mockResolvedValue({ data: { data: students } })
        const loadContext: any = {
            students: [],
            selectedStudentKeys: [],
            studentsLoading: false,
            studentsError: false,
            studentSelectionKey: methods.studentSelectionKey,
        }

        vi.stubGlobal('axios', { get })

        try {
            await methods.loadStudents.call(loadContext)
        } finally {
            vi.unstubAllGlobals()
        }

        expect(get).toHaveBeenCalledWith('/api/admin/students-timetables/robot/students')
        expect(loadContext.students).toEqual(students)
        expect(loadContext.studentsLoading).toBe(false)
        expect(loadContext.studentsError).toBe(false)

        const selectionContext: any = {
            ...methods,
            students: loadContext.students,
            selectedStudentKeys: [],
        }
        selectionContext.sortedStudents = computed.sortedStudents.call(selectionContext)
        selectionContext.studentSelectionKeys = computed.studentSelectionKeys.call(selectionContext)
        selectionContext.studentClasses = computed.studentClasses.call(selectionContext)

        expect(selectionContext.sortedStudents.map((student: { student_code: string }) => student.student_code))
            .toEqual(['1001', '2002', '3003'])
        expect(selectionContext.studentClasses).toEqual([
            {
                key: '1B',
                label: '1B',
                studentKeys: ['1001', '2002'],
            },
            {
                key: '2A',
                label: '2A',
                studentKeys: ['3003'],
            },
        ])
        expect(methods.studentSexPresentation({ sex: ' M ' })).toEqual({
            icon: 'mdi-gender-male',
            color: 'blue',
            label: 'männlich',
        })
        expect(methods.studentSexPresentation({ sex: 'w' })).toEqual({
            icon: 'mdi-gender-female',
            color: 'pink',
            label: 'weiblich',
        })
        expect(methods.studentSexPresentation({ sex: 'x' })).toBeNull()
        expect(methods.studentSexPresentation({})).toBeNull()
        expect(methods.studentReligionLabel({ religion: ' Rk ' })).toBe('Rk')
        expect(methods.studentReligionLabel({})).toBe('')
        expect(methods.studentStudySelectionLabels({
            study_selection: {
                religion: 'Rev',
                language: 'f',
                branch: 'gymnasial',
                arts_subject: 'be',
            },
        })).toEqual(['REV', 'F', 'GYM', 'BE'])
        expect(methods.studentStudySelectionLabels({
            study_selection: {
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                arts_subject: 'ME',
            },
        })).toEqual(['ETH', 'L', 'WIKU', 'ME'])
        expect(methods.studentStudySelectionLabels({})).toEqual([])
        expect(methods.studentSemesterLabel({
            instruction_type: 'Normalunterricht',
            semester: 5,
            school_level: '11',
            attendance_year: '1',
        })).toBe('N 5')
        expect(methods.studentSchoolLevelLabel({
            school_level: '11',
            attendance_year: '1',
        })).toBe('11_1')
        expect(methods.studentSemesterLabel({
            instruction_type: 'Kompaktunterricht',
            semester: 3,
            school_level: '11-1',
        })).toBe('K 3')
        expect(methods.studentSchoolLevelLabel({ school_level: '11-1' })).toBe('11_1')
        expect(methods.studentSemesterLabel({
            study_program: 'kompaktstudium',
            semester: 2,
            school_level: '09_2',
            attendance_year: '2',
        })).toBe('K 2')
        expect(methods.studentSchoolLevelLabel({
            school_level: '09_2',
            attendance_year: '2',
        })).toBe('09_2')
        expect(methods.studentSemesterLabel({ semester: 4 })).toBe('4')
        expect(methods.studentSemesterLabel({})).toBe('')
        expect(methods.studentSchoolLevelLabel({})).toBe('')
        expect(methods.studentCourseResultLabels({
            course_results: {
                completed: [
                    { code: 'M1', grade: '3', status: 'passed' },
                    { code: 'D1', grade: 'b', status: 'exempt' },
                ],
                negative: [
                    { code: 'E1', grade: '5', status: 'failed' },
                    { code: 'BU1', grade: 'n', status: 'failed' },
                ],
            },
        }, 'completed')).toEqual(['M1 (3)', 'D1 (B)'])
        expect(methods.studentCourseResultLabels({
            course_results: {
                negative: [
                    { code: 'E1', grade: '5', status: 'failed' },
                    { code: 'BU1', grade: 'n', status: 'failed' },
                ],
            },
        }, 'negative')).toEqual(['E1 (5)', 'BU1 (N)'])
        expect(methods.studentCourseResultLabels({}, 'completed')).toEqual([])

        methods.toggleStudentClassSelection.call(selectionContext, selectionContext.studentClasses[0])

        expect(selectionContext.selectedStudentKeys).toEqual(['1001', '2002'])
        expect(methods.isStudentClassSelected.call(selectionContext, selectionContext.studentClasses[0])).toBe(true)

        methods.toggleStudentClassSelection.call(selectionContext, selectionContext.studentClasses[0])

        expect(selectionContext.selectedStudentKeys).toEqual([])

        methods.selectAllStudents.call(selectionContext)

        expect(selectionContext.selectedStudentKeys).toEqual(['1001', '2002', '3003'])
        expect(computed.allStudentsSelected.call(selectionContext)).toBe(true)

        methods.toggleStudentSelection.call(selectionContext, students[1], false)

        expect(selectionContext.selectedStudentKeys).toEqual(['1001', '3003'])
        expect(computed.selectedStudents.call(selectionContext)
            .map((student: { student_code: string }) => student.student_code))
            .toEqual(['1001', '3003'])

        methods.clearStudentSelection.call(selectionContext)

        expect(selectionContext.selectedStudentKeys).toEqual([])
    })

    it('restores the selected timetable v3 students after a refresh', async () => {
        const methods = (TestsV3 as any).methods
        const watch = (TestsV3 as any).watch
        const storageContext: any = {
            ...methods,
            config: {
                selected_schoolyear: { id: 77 },
            },
            students: [],
            selectedStudentKeys: ['1001', '2002', '1001'],
            studentsLoading: false,
            studentsError: false,
        }
        const storageKey = methods.studentSelectionStorageKey.call(storageContext)
        const get = vi.fn().mockResolvedValue({
            data: {
                data: [
                    { id: 1, student_code: '1001' },
                    { id: 2, student_code: '2002' },
                ],
            },
        })

        window.localStorage.clear()
        vi.stubGlobal('axios', { get })

        try {
            methods.persistStudentSelection.call(storageContext)

            expect(JSON.parse(window.localStorage.getItem(storageKey) || '[]'))
                .toEqual(['1001', '2002'])

            window.localStorage.setItem(storageKey, JSON.stringify(['1001', 'missing', '1001']))
            storageContext.selectedStudentKeys = []
            methods.restoreStudentSelection.call(storageContext)

            expect(storageContext.selectedStudentKeys).toEqual(['1001', 'missing'])

            await methods.loadStudents.call(storageContext)
            watch.selectedStudentKeys.call(storageContext)

            expect(storageContext.selectedStudentKeys).toEqual(['1001'])
            expect(JSON.parse(window.localStorage.getItem(storageKey) || '[]')).toEqual(['1001'])
        } finally {
            vi.unstubAllGlobals()
            window.localStorage.clear()
        }

        const mountedContext = {
            testsV3Action: 'tests',
            redirectInvalidTestsV3Route: vi.fn().mockReturnValue(false),
            restoreStudentSelection: vi.fn(),
            loadStudents: vi.fn(),
            loadStudyPlans: vi.fn(),
        }
        const mounted = (TestsV3 as any).mounted

        mounted.call(mountedContext)

        expect(mountedContext.restoreStudentSelection).toHaveBeenCalledOnce()
        expect(mountedContext.loadStudents).toHaveBeenCalledOnce()
        expect(mountedContext.loadStudyPlans).toHaveBeenCalledOnce()
    })

    it('opens the TT entries page from the module navigation', () => {
        const methods = (StudentsTimetables as any).methods
        const push = vi.fn()
        const ctx: any = {
            $router: {
                push,
            },
            main_action: 'timetable-v2',
        }

        methods.handleNavigation.call(ctx, 'tt-entries')

        expect(ctx.main_action).toBe('tt-entries')
        expect(push).toHaveBeenCalledWith({ path: '/admin/students-timetables/tt-entries/overview' })
    })

    it('shows TT entries navigation only to timetable admins', () => {
        const computed = (StudentsTimetables as any).computed
        const methods = (StudentsTimetables as any).methods
        const ctx: any = {
            ...methods,
            configuredRoleNames: ['studentstimetables_admin'],
            activeTimetableKey: 'timetable-v2',
            activeTimetableVersion: 'v2',
        }

        Object.defineProperty(ctx, 'allNavigationItems', {
            get() {
                return computed.allNavigationItems.call(ctx)
            },
        })

        const adminNavigationKeys = computed.navigationItems.call(ctx).map(item => item.key)

        ctx.configuredRoleNames = ['studentstimetables_moderator']

        const moderatorNavigationKeys = computed.navigationItems.call(ctx).map(item => item.key)

        expect(adminNavigationKeys).toContain('tt-entries')
        expect(moderatorNavigationKeys).not.toContain('tt-entries')
        expect(moderatorNavigationKeys).toContain('timetable-v2')
        expect(moderatorNavigationKeys).toContain('timetable-v3')
        expect(moderatorNavigationKeys).toContain('tests-v3')
        expect(moderatorNavigationKeys).toContain('subjects-overview')
    })

    it('redirects non-admins away from the TT entries route', () => {
        const methods = (StudentsTimetables as any).methods
        const replace = vi.fn()
        const ctx: any = {
            $route: {
                params: {
                    section: 'tt-entries',
                },
            },
            $router: {
                replace,
            },
            canManageStudentsTimetables: false,
            main_action: 'tt-entries',
            activeTimetableKey: 'timetable-v2',
            activeTimetablePath: '/admin/students-timetables/timetable-v2/overview',
        }

        methods.redirectUnauthorizedSection.call(ctx)

        expect(ctx.main_action).toBe('timetable-v2')
        expect(replace).toHaveBeenCalledWith({ path: '/admin/students-timetables/timetable-v2/overview' })
    })

    it('groups TT entries meta-courses in one selectable card', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/ttEntries/TtEntries.vue',
            'utf8',
        )
        const computed = (TtEntries as any).computed
        const methods = (TtEntries as any).methods
        const ctx: any = {
            ...methods,
            selectedMetaCourseKey: 'M',
            selectedSubjectRowKey: '',
            schoolHours: [
                {
                    hour: 3,
                    from: '09:50:00',
                    until: '10:40:00',
                },
            ],
            courseGroups: [
                {
                    key: 'm1-offer',
                    title: 'M1-3R-SCHM',
                    display_label: 'M1 - 3R - SCHM',
                    class_name: 'M1-3R-SCHM',
                    semester: 1,
                    weekday: 2,
                    hour: 3,
                    starts_at: '09:50',
                    ends_at: '10:40',
                },
            ],
            subjectMappings: [
                {
                    json_subject: 'ÖKO',
                    tt_subject: 'OKON',
                    is_active: true,
                },
            ],
            subjectRows: [
                {
                    id: 1,
                    json_code: 'M1',
                    json_subject: 'M',
                    name: 'Mathematik 1',
                    semester: 1,
                    branch: null,
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 2,
                    json_code: 'M2',
                    json_subject: 'M',
                    name: 'Mathematik 2',
                    semester: 2,
                    branch: null,
                    hours_per_week: 3,
                    is_active: true,
                },
                {
                    id: 3,
                    json_code: 'INF2',
                    json_subject: 'INF',
                    name: 'Informatik 2',
                    semester: 3,
                    branch: 'wirtschaftskundlich',
                    hours_per_week: 2,
                    is_active: true,
                },
                {
                    id: 4,
                    json_code: 'F1',
                    json_subject: 'F',
                    name: 'Französisch 1',
                    semester: 1,
                    branch: null,
                    hours_per_week: 4,
                    is_active: false,
                },
                {
                    id: 5,
                    json_code: 'ÖKO2',
                    json_subject: 'ÖKO',
                    name: 'Ökonomie 2',
                    semester: 4,
                    branch: 'wirtschaftskundlich',
                    hours_per_week: 4,
                    is_active: true,
                },
            ],
        }

        Object.defineProperty(ctx, 'activeSubjectRows', {
            get() {
                return computed.activeSubjectRows.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'metaCourseItems', {
            get() {
                return computed.metaCourseItems.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'activeSubjectMappings', {
            get() {
                return computed.activeSubjectMappings.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'selectedMetaCourse', {
            get() {
                return computed.selectedMetaCourse.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'selectedMetaCourseRows', {
            get() {
                return computed.selectedMetaCourseRows.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'selectedSubjectRow', {
            get() {
                return computed.selectedSubjectRow.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'selectedSubjectCourseCode', {
            get() {
                return computed.selectedSubjectCourseCode.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'courseGroupsByCourseCode', {
            get() {
                return computed.courseGroupsByCourseCode.call(ctx)
            },
        })

        ctx.selectedSubjectRowKey = methods.subjectRowKey.call(ctx, ctx.subjectRows[0])

        expect(componentSource).toContain('<v-card rounded="lg" class="tt-entries-card">')
        expect(componentSource).toContain('v-for="course in metaCourseItems"')
        expect(componentSource).toContain('@click="selectMetaCourse(course.key)"')
        expect(componentSource).toContain('v-if="selectedMetaCourseKey === course.key"')
        expect(componentSource).toContain('class="tt-entries-card__sub-items"')
        expect(componentSource).toContain('<v-expand-transition>')
        expect(componentSource.indexOf('class="tt-entries-card__meta-course"'))
            .toBeLessThan(componentSource.indexOf('class="tt-entries-card__sub-items"'))
        expect(componentSource).toMatch(/\.tt-entries-card__sub-items\s*\{[\s\S]*?padding-left:\s*24px;/)
        expect(componentSource).toContain('v-for="offer in selectedSubjectOffers"')
        expect(componentSource).toContain('this.ttSubjectForSubject(subject)')
        expect(ctx.metaCourseItems.map(course => course.key)).toEqual(['INF', 'M', 'OEKO'])
        expect(ctx.metaCourseItems.map(course => course.label)).toEqual(['INF', 'M', 'ÖKO'])
        expect(ctx.metaCourseItems.map(course => course.countLabel)).toEqual(['1 Eintrag', '2 Einträge', '1 Eintrag'])
        expect(methods.courseDisplayLabel.call(ctx, 'L/F/S')).toBe('L / F / SPA')
        expect(methods.subjectRowMeta.call(ctx, ctx.subjectRows[2])).toBe('3. Sem. · 2 Std.')
        expect(methods.subjectRowMeta.call(ctx, ctx.subjectRows[2])).not.toContain('wirtschaftskundlich')
        expect(computed.selectedMetaCourseRows.call(ctx).map(subject => subject.json_code)).toEqual(['M1', 'M2'])
        expect(computed.selectedSubjectOffers.call(ctx).map(offer => ({
            name: offer.name,
            scheduleLabel: offer.scheduleLabel,
        }))).toEqual([
            {
                name: 'M1 - 3R - SCHM',
                scheduleLabel: 'Di 3. 09:50-10:40',
            },
        ])
    })

    it('starts the standalone timetable v2 page with student selection cards and dialog', () => {
        const componentSource = [
            readFileSync(
                'resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue',
                'utf8',
            ),
            readFileSync(
                'resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.css',
                'utf8',
            ),
        ].join('\n')

        expect(componentSource).toContain('<v-row v-else dense align="stretch">')
        expect(componentSource).not.toContain('<v-col v-if="courseReviewVisible" cols="12" class="students-timetable-v2-card-column">')
        expect(componentSource).toContain('<TimetableStudentSummary')
        expect(componentSource).toContain(':student-label="courseReviewStudentLabel"')
        expect(componentSource).toContain(':selection-items="courseReviewSelectionSummary"')
        expect(componentSource).toContain('@copy-email="copyCourseReviewStudentEmail"')
        expect(componentSource).toContain('students-timetable-v2-selected-offers-card')
        expect(componentSource).toContain('v-for="group in selectedAdaptedOfferedCourseGroups"')
        expect(componentSource).toContain('v-for="offer in group.offers"')
        expect(componentSource).toContain('@click="toggleOfferedCourseItem(offer)"')
        expect(componentSource).toContain('Keine Angebote ausgewählt.')
        expect(componentSource).toContain('Fernunterricht')
        expect(componentSource).toContain('selectedReviewOfferedCourseItems()')
        expect(componentSource).toContain('offeredCourseItemsForSelectedCourse(course)')
        expect(componentSource).toContain('@click="backToCourseSelection"')
        expect(componentSource).toContain('Zurück')
        expect(componentSource).toContain('v-if="startCardVisible"')
        expect(componentSource).toContain('<v-card-title>Start</v-card-title>')
        expect(componentSource).toContain('Mit Studierenden')
        expect(componentSource).toContain('Ohne Studierenden')
        expect(componentSource).toContain('@click="startWithStudent"')
        expect(componentSource).toContain('@click="startWithoutStudent"')
        expect(componentSource).toContain('v-if="restartCardVisible"')
        expect(componentSource).toContain('Neustart')
        expect(componentSource).toContain('@click="restartTimetableV2"')
        expect(componentSource).toContain('v-if="courseCardsVisible"')
        expect(componentSource).toContain('<CourseSelectionCards')
        expect(componentSource).toContain(':cards="courseSelectionCards"')
        expect(componentSource).toContain('@apply-course-selections="applyDraftCourseSelections"')
        expect(componentSource).toContain('@click="applyCourseLimitPreselection(true)"')
        expect(componentSource).toContain('<v-col v-if="courseCardsVisible && selectedCourseLimitReached" cols="12" class="students-timetable-v2-card-column">')
        expect(componentSource).toContain('icon="mdi-information-outline"')
        expect(componentSource).toContain('Maximum erreicht: Negative Module und Frühere Module dürfen zusammen höchstens 10 Module und 30 Stunden ergeben.')
        expect(componentSource).toContain('Wählen Sie ein Modul ab, um ein anderes Modul auszuwählen.')
        expect(componentSource).toContain('Weiter')
        expect(componentSource).not.toContain('Automatischer Stundenplan')
        expect(componentSource).toContain('append-icon="mdi-arrow-right"')
        expect(componentSource).toContain(':disabled="selectedCourseLimitExceeded"')
        expect(componentSource).toContain('students-timetable-v2-restart-card__automatic-button')
        expect(componentSource).toContain('justify-content: space-between')
        expect(componentSource).toContain('margin-left: auto')
        expect(componentSource).toContain('@click="openCourseReview"')
        expect(componentSource).toContain('openCourseReview()')
        expect(componentSource).toContain('@click="openTimetableCalculation"')
        expect(componentSource).toContain('timetableCalculationVisible')
        expect(componentSource).toContain('Die Stundenpläne werden berechnet.')
        expect(componentSource).toContain('Die Stundenpläne wurden erfolgreich erstellt.')
        expect(componentSource).toContain('timetableCalculationCountLabel')
        expect(componentSource).toContain('/api/admin/students-timetables/robot/backend-timetable')
        expect(componentSource).toContain("this.setTimetableV2Step('course-review')")
        expect(componentSource).toContain("this.setTimetableV2Step('timetable-calculation')")
        expect(componentSource).toContain("this.setTimetableV2Step('selection')")
        expect(componentSource).toContain('selectedCourseSummary()')
        expect(componentSource).toContain('selectedCourseLimitExceeded()')
        expect(componentSource).toContain('if (this.selectedCourseLimitExceeded) return')
        expect(componentSource).toContain('applyCourseLimitPreselection(force = false)')
        expect(componentSource).toContain('courseLimitPreselectionKey')
        expect(componentSource).toContain('courseSelectionsForCourseLimitPreselection(courseSelections = {})')
        expect(componentSource).toContain('courseLimitDuplicateModuleCourseItems(courseSelections = {})')
        expect(componentSource).toContain('courseLimitPreselectionCandidates(courseSelections = {})')
        expect(componentSource).toContain('compareCourseLimitPreselectionItems(firstCourse, secondCourse)')
        expect(componentSource).toContain("const coreCoursePriority = ['L', 'F', 'S', 'E', 'ETH', 'M', 'D'].indexOf(baseCode)")
        expect(componentSource).toContain('v-if="studentCardVisible || selectionCardVisible"')
        expect(componentSource).toContain('v-if="courseCardsVisible"')
        expect(componentSource).toContain('courseCardMdColumns()')
        expect(componentSource).toContain('mdi-account-off-outline')
        expect(componentSource).toContain('withoutStudentBackgroundVisible()')
        expect(componentSource).toContain('selectionCardOffsetMd()')
        expect(componentSource).toContain(':student-label="storedTimetableStudentLabel"')
        expect(componentSource).toContain('@edit-student="openStudentDialog"')
        expect(componentSource).toContain('@remove-student="clearStoredTimetableStudent"')
        expect(componentSource).toContain('Besuchte Module')
        expect(componentSource).toContain('Abgeschlossene Module')
        expect(componentSource).toContain('Negative Module')
        expect(componentSource).toContain('storedCompletedCourseItems')
        expect(componentSource).toContain('storedMissingCourseItems')
        expect(componentSource).toContain('storedMissingCourseCardItems')
        expect(componentSource).toContain('storedPlannedCourseItems')
        expect(componentSource).toContain('storedAdditionalCourseItems')
        expect(componentSource).toContain('selectedMissingCourseCardItems')
        expect(componentSource).toContain('selectedPlannedCourseItems')
        expect(componentSource).toContain('selectedAdditionalCourseItems')
        expect(componentSource).toContain('hoursMeta')
        expect(componentSource).toContain('subjectRowHoursForCourseCode(code)')
        expect(componentSource).toContain('@apply-course-selections="applyDraftCourseSelections"')
        expect(componentSource).toContain('studentCompletedCoursesLoading')
        expect(componentSource).toContain('studentCompletedCoursesError')
        expect(componentSource).toContain('loadStoredStudentOverview(this.storedTimetableStudentCode)')
        expect(componentSource).toContain('/api/admin/students-timetables/timetable-v2-selection-bootstrap')
        expect(componentSource).toContain('studentOverviewSelectionPayload()')
        expect(componentSource).toContain('studentOverviewActiveRequestKey')
        expect(componentSource).toContain('studentOverviewLoadedRequestKey')
        expect(componentSource).toContain('studentOverviewRequestKey(')
        expect(componentSource).toContain('studentOverviewSelectionPayloadForSelection(selection)')
        expect(componentSource).toContain('overviewStudentCourseHistoryFromSummary(overviewSummary)')
        expect(componentSource).toContain('completed: this.completedCourseItemsFromApi(courseHistoryCourses)')
        expect(componentSource).toContain('failed: this.missingCourseItemsFromApi(courseHistoryCourses)')
        expect(componentSource).toContain('missing: this.normalizedOverviewCourseItems(missingCourses)')
        expect(componentSource).toContain('planned: this.sortedCourseItems(')
        expect(componentSource).toContain('this.normalizedOverviewCourseItems(automaticPlannedCourses || overviewSummary?.proposed_courses || [])')
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
        expect(componentSource).toContain('selectedStudentCourseHistoryDefaults(courseHistory)')
        expect(componentSource).toContain('studentCourseCodeSet(courses)')
        expect(componentSource).toContain('inferredSelectionOptionFromCourseCodes(')
        expect(componentSource).toContain('inferredBranchFromCourseCodes(courseCodes)')
        expect(componentSource).toContain("aliases: ['INF']")
        expect(componentSource).toContain("aliases: ['OKO', 'OEKO', 'OEK', 'WIKU', 'BWL', 'RW', 'WR']")
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
        expect(componentSource).toContain("axios.put('/api/admin/students-timetables/timetable-v2-state', { state })")
        expect(componentSource).toContain('storedTimetableStudentLabel()')
        expect(componentSource).toContain('return label')
        expect(componentSource).not.toContain('storedTimetableStudentReligionLabel()')
        expect(componentSource).not.toContain('students-timetable-v2-student-context__meta')
        expect(componentSource).toContain('startCardVisible()')
        expect(componentSource).toContain('studentCardVisible()')
        expect(componentSource).toContain('courseCardsVisible()')
        expect(componentSource).toContain('selectionCardVisible()')
        expect(componentSource).toContain('withoutStudentBackgroundVisible()')
        expect(componentSource).toContain('timetableV2PageLoading()')
        expect(componentSource).toContain('reviewButtonCardVisible()')
        expect(componentSource).toContain('restartCardVisible()')
        expect(componentSource).toContain('studentCompletedCoursesLoading')
        expect(componentSource).toContain('subjectRowsLoading')
        expect(componentSource).toContain('courseGroupsLoading')
        expect(componentSource).toContain('schoolHoursLoading')
        expect(componentSource).toContain('v-if="reviewButtonCardVisible"')
        expect(componentSource).toContain('noStudentCourseSelectionMode()')
        expect(componentSource).toContain('courseGroups: []')
        expect(componentSource).toContain('subjectRows: []')
        expect(componentSource).toContain('loadCourseGroups()')
        expect(componentSource).toContain('loadSchoolHours()')
        expect(componentSource).toContain('loadSubjectRows()')
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/course-groups')")
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/school-hours')")
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/subjects-overview-settings'")
        expect(componentSource.indexOf('this.loadSubjectRows()')).toBeLessThan(
            componentSource.indexOf('this.loadStoredStudentOverview(this.storedTimetableStudentCode)'),
        )
        expect(componentSource.indexOf('this.loadCourseGroups()')).toBeLessThan(
            componentSource.indexOf('this.loadStoredStudentOverview(this.storedTimetableStudentCode)'),
        )
        expect(componentSource.indexOf('this.loadSchoolHours()')).toBeLessThan(
            componentSource.indexOf('this.loadStoredStudentOverview(this.storedTimetableStudentCode)'),
        )
        expect(componentSource).toContain("timetableStartMode: ''")
        expect(componentSource).toContain("this.timetableStartMode = 'student'")
        expect(componentSource).toContain("this.timetableStartMode = 'without-student'")
        expect(componentSource).toContain('restartTimetableV2()')
        expect(componentSource).toContain('transferredStudentContext')
        expect(componentSource).toContain('transferredStudentContextFromRobotStudent(student)')
        expect(componentSource).toContain('studentOptionTitle(student)')
        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/robot/students')")
        expect(componentSource).toContain('<TimetableStudentSummary')
        expect(componentSource).toContain(':selection-visible="selectionCardVisible"')
        expect(componentSource).toContain(':selection-items="storedTimetableSelectionSummary"')
        expect(componentSource).toContain('@select-option="selectTimetableSelectionOption"')
        expect(componentSource).toContain('storedTimetableSelectionSummary()')
        expect(componentSource).toContain('const currentSelection = this.storedTimetableStateForSaving()?.selection || {}')
        expect(componentSource).toContain("label: 'Semester'")
        expect(componentSource).toContain('const semesterLabel = this.storedTimetableStudentContext?.student?.semesterLabel')
        expect(componentSource).toContain('options: this.storedTimetableStudentContext ? [] : this.semesterOptions()')
        expect(componentSource).toContain('semesterOptions()')
        expect(componentSource).toContain("label: 'Ethik / Religion'")
        expect(componentSource).toContain('options: this.shortTimetableSelectionOptions(this.religionOptionsForSelectedStudent())')
        expect(componentSource).toContain("label: 'Sprache'")
        expect(componentSource).toContain('options: this.shortTimetableSelectionOptions(this.languageOptions())')
        expect(componentSource).toContain("label: 'Zweig'")
        expect(componentSource).toContain('options: this.shortTimetableSelectionOptions(this.branchOptions())')
        expect(componentSource).toContain("label: 'ME / BE'")
        expect(componentSource).toContain('options: this.shortTimetableSelectionOptions(this.artsSubjectOptions())')
        expect(componentSource).toContain('storedTimetableV2Selection()')
        expect(componentSource).toContain('courseSelectionOverrides()')
        expect(componentSource).toContain('timetableV2Selection: {}')
        expect(componentSource).toContain('selectTimetableSelectionOption(key, value)')
        expect(componentSource).toContain('courseReviewSemesterSelectionValue(item, timetableV2Selection')
        expect(componentSource).toContain('courseItemSelected(course, courseGroup)')
        expect(componentSource).toContain('courseGroupItems(courseGroup)')
        expect(componentSource).toContain('courseGroupAllSelected(courseGroup)')
        expect(componentSource).toContain('courseGroupNoneSelected(courseGroup)')
        expect(componentSource).toContain('setCourseGroupSelection(courseGroup, selected)')
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

    it('opens the timetable v2 compact review page from the bottom menu card', () => {
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
            selectedCourseLimitExceeded: false,
            timetableV2Step: 'selection',
            persistCourseSelectionsForCourseReview: vi.fn(),
            loadCourseGroups: vi.fn(),
            loadSchoolHours: vi.fn(),
        }

        methods.openCourseReview.call(ctx)

        expect(ctx.timetableV2Step).toBe('course-review')

        methods.backToCourseSelection.call(ctx)

        expect(ctx.timetableV2Step).toBe('selection')
    })

    it('opens the timetable v2 calculation page and posts selected courses', async () => {
        const methods = (TimetableV2 as any).methods
        const computed = (TimetableV2 as any).computed
        const axiosMock = {
            post: vi.fn().mockResolvedValue({
                data: {
                    data: {
                        timetable_variation_count: 2,
                        selected_timetable: { slots: [] },
                    },
                },
            }),
        }
        const previousAxios = (globalThis as any).axios
        const ctx: any = {
            ...methods,
            timetableV2Step: 'course-review',
            timetableCalculationError: '',
            timetableCalculationLoading: false,
            timetableCalculationRequestId: 0,
            timetableCalculationResult: null,
            selectedCourseItems: [
                {
                    key: 'D1',
                    code: 'D1',
                    label: 'D1',
                    selectionKey: 'planned:D1',
                    courseGroup: 'planned',
                },
                {
                    key: 'INF1',
                    code: 'INF1',
                    label: 'INF1',
                    selectionKey: 'additional:INF1',
                    courseGroup: 'additional',
                },
            ],
            selectedMoreCourses: [],
            moreCoursesCardItems: [],
            selectedAdditionalCourseItems: [],
            selectedPlannedCourseItems: [
                {
                    key: 'D1',
                    code: 'D1',
                    label: 'D1',
                    selectionKey: 'planned:D1',
                    courseGroup: 'planned',
                },
            ],
            selectedNegativeCourses: [],
            storedTimetableStudentContext: {
                student: {
                    studentCode: '100',
                    semesterLabel: '1',
                    religion: 'ETH',
                },
            },
            effectiveTimetableV2Selection: {
                semester: 1,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'L',
            },
            schoolHours: [
                { hour: 11 },
                { hour: 12 },
            ],
            courseGroups: [
                {
                    key: 'd1-gos',
                    semester: 1,
                    weekday: 1,
                    hour: 11,
                    title: 'D1',
                    display_label: 'D1 - GOS',
                    course: 'D',
                    subject: 'D',
                    class_name: 'D1-1C-GOS',
                },
            ],
            offeredCourseSelectionOverrides: {},
            selectedReviewCourseItem: {
                key: 'D1',
                code: 'D1',
                label: 'D1',
                selectionKey: 'planned:D1',
                courseGroup: 'planned',
            },
        }
        Object.defineProperty(ctx, 'timetableCalculationVisible', {
            get() {
                return computed.timetableCalculationVisible.call(ctx)
            },
        })

        try {
            (globalThis as any).axios = axiosMock

            await methods.openTimetableCalculation.call(ctx)

            expect(ctx.timetableV2Step).toBe('timetable-calculation')

            expect(axiosMock.post).toHaveBeenCalledWith(
                '/api/admin/students-timetables/robot/backend-timetable',
                expect.objectContaining({
                    selection: {
                        semester: 1,
                        religion: 'ETH',
                        branch: null,
                        artsSubject: null,
                        language: null,
                    },
                    selected_course_keys: ['D1'],
                    selected_additional_course_keys: ['INF1'],
                    deselected_course_group_keys: [],
                    selected_timetable_type: 'full_green',
                    selected_timetable_number: 1,
                }),
            )
            expect(ctx.timetableCalculationLoading).toBe(false)
            expect(ctx.timetableCalculationResult).toMatchObject({
                timetable_variation_count: 2,
            })
            ctx.timetableCalculationDisplayTotalCount = 2
            expect(computed.timetableCalculationCountLabel.call(ctx)).toBe('2 Stundenpläne gesamt')
            ctx.timetableCalculationResult = {
                timetable_variation_count: 1,
            }
            ctx.timetableCalculationDisplayTotalCount = 1
            expect(computed.timetableCalculationCountLabel.call(ctx)).toBe('1 Stundenplan gesamt')
            expect(computed.timetableCalculationVisible.call(ctx)).toBe(true)
        } finally {
            (globalThis as any).axios = previousAxios
        }
    })

    it('keeps timetable v2 navigation cards available while page data is loading', () => {
        const computed = (TimetableV2 as any).computed
        const ctx: any = {
            storedTimetableStudentContext: {
                student: {
                    label: 'Test Student',
                },
            },
            timetableStartMode: 'student',
            timetableV2Step: 'selection',
            studentCompletedCoursesLoading: false,
            subjectRowsLoading: false,
            courseGroupsLoading: false,
            schoolHoursLoading: false,
        }
        Object.defineProperty(ctx, 'courseReviewVisible', {
            get() {
                return computed.courseReviewVisible.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'timetableV2PageLoading', {
            get() {
                return computed.timetableV2PageLoading.call(ctx)
            },
        })

        expect(computed.restartCardVisible.call(ctx)).toBe(true)

        ctx.subjectRowsLoading = true

        expect(computed.timetableV2PageLoading.call(ctx)).toBe(true)
        expect(computed.restartCardVisible.call(ctx)).toBe(false)

        ctx.subjectRowsLoading = false
        ctx.timetableV2Step = 'course-review'
        ctx.courseGroupsLoading = true

        expect(computed.reviewButtonCardVisible.call(ctx)).toBe(true)

        ctx.courseGroupsLoading = false

        expect(computed.reviewButtonCardVisible.call(ctx)).toBe(true)
    })

    it('does not open the timetable v2 compact review page when selected courses exceed the limit', () => {
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            selectedCourseLimitExceeded: true,
            timetableV2Step: 'selection',
        }

        methods.openCourseReview.call(ctx)

        expect(ctx.timetableV2Step).toBe('selection')
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
        Object.defineProperty(ctx, 'storedTimetableSelectionSummary', {
            get() {
                return computed.storedTimetableSelectionSummary.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'storedTimetableStudentLabel', {
            get() {
                return computed.storedTimetableStudentLabel.call(ctx)
            },
        })

        const summary = computed.storedTimetableSelectionSummary.call(ctx)
        const semester = summary.find((item: Record<string, any>) => item.key === 'semester')

        expect(semester.value).toBe('3')
        expect(semester.known).toBe(true)
        expect(semester.options).toHaveLength(8)
        expect(semester.options[0]).toEqual({ title: 'Semester 1', value: 1 })
        expect(semester.options[2]).toEqual({ title: 'Semester 3', value: 3 })

        ctx.storedTimetableV2Selection = {
            semester: 3,
            religion: 'ETH',
            language: 'L',
            branch: 'wirtschaftskundlich',
            artsSubject: 'ME',
        }

        expect(computed.courseReviewSelectionSummary.call(ctx).map((item: Record<string, string>) => `${item.label}: ${item.value}`))
            .toEqual([
                'Semester: Semester 3',
                'Ethik / Religion: ETH',
                'Sprache: L',
                'Zweig: WIKU',
                'ME / BE: ME',
            ])

        ctx.storedTimetableStudentContext = {
            student: {
                label: '1C · GEHMACHER Ella · Semester 1',
                semesterLabel: '1',
                religion: 'ETH',
            },
        }

        expect(computed.courseReviewStudentLabel.call(ctx)).toBe('1C · GEHMACHER Ella · Semester 1')
        expect(computed.courseReviewSelectionSummary.call(ctx)[0]).toMatchObject({
            label: 'Semester',
            value: 'Semester 1',
        })
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

        expect(visibleReligionTitles('evang. A.B.')).toEqual(['ETH - Ethik', 'Rev - Evangelische Religion'])
        expect(visibleReligionTitles('islam.')).toEqual(['ETH - Ethik', 'Ris - Islamische Religion'])
        expect(visibleReligionTitles('röm.-kath.')).toEqual(['ETH - Ethik', 'Rk - Katholische Religion'])
        expect(visibleReligionTitles('orth')).toEqual(['ETH - Ethik', 'Ror - Orthodoxe Religion'])
        expect(visibleReligionTitles('orth.')).toEqual(['ETH - Ethik', 'Ror - Orthodoxe Religion'])
        expect(visibleReligionTitles('griech.-orth.')).toEqual(['ETH - Ethik', 'Ror - Orthodoxe Religion'])
        expect(visibleReligionTitles('o.B.')).toEqual([
            'ETH - Ethik',
            'Rev - Evangelische Religion',
            'Ris - Islamische Religion',
            'Rk - Katholische Religion',
            'Ror - Orthodoxe Religion',
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
        ])).toMatchObject([
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
        ])).toMatchObject([
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
            storedCompletedCourseItems: [],
            storedMissingCourseCardItems: [],
            storedSemesterCourseItems: [],
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
            storedTimetableV2Selection: {
                courseSelections: {
                    'missing:BU1': true,
                    'missing:BU2': true,
                    'planned:BU1': true,
                    'planned:D3': true,
                    'planned:ETH2': true,
                },
            },
            subjectRows: [
                {
                    json_code: 'BU2',
                    json_subject: 'BU',
                    hours_per_week: 4,
                    is_active: true,
                },
            ],
            storedCompletedCourseItems: [],
            storedSemesterCourseItems: [],
        }
        Object.defineProperty(ctx, 'courseSelectionOverrides', {
            get() {
                return methods.courseSelectionOverridesForSelection.call(ctx, ctx.storedTimetableV2Selection)
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
        Object.defineProperty(ctx, 'selectedCompletedCourseItems', {
            get() {
                return []
            },
        })
        Object.defineProperty(ctx, 'selectedSemesterCourseItems', {
            get() {
                return []
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
        Object.defineProperty(ctx, 'selectedCourseSummary', {
            get() {
                return computed.selectedCourseSummary.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'selectedCourseLimitSummary', {
            get() {
                return computed.selectedCourseLimitSummary.call(ctx)
            },
        })

        expect(computed.storedMissingCourseCardSummary.call(ctx)).toMatchObject({
            count: 2,
            hours: 7,
            countLabel: '2 Module',
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
        expect(computed.storedPlannedCourseItems.call(ctx).map((course: Record<string, string>) => course.label))
            .toEqual(['D3', 'ETH2'])
        expect(computed.storedPlannedCourseSummary.call(ctx)).toMatchObject({
            count: 0,
            hours: 0,
            countLabel: '0 Module',
            hoursLabel: '0 Std.',
        })
        expect(computed.storedAdditionalCourseSummary.call(ctx)).toMatchObject({
            count: 0,
            hours: 0,
            countLabel: '0 Module',
            hoursLabel: '0 Std.',
        })
        expect(computed.selectedCourseSummary.call(ctx)).toMatchObject({
            count: 2,
            hours: 7,
            countLabel: '2 Module',
            hoursLabel: '7 Std.',
        })
        expect(computed.selectedCourseItems.call(ctx).map((course: Record<string, string>) => `${course.label} ${course.meta}`))
            .toEqual([
                'BU1 3 Std.',
                'BU2 4 Std.',
            ])
        expect(computed.selectedCourseLimitExceeded.call(ctx)).toBe(false)

        ctx.storedTimetableV2Selection = {
            courseSelections: {
                'missing:BU1': false,
                'planned:D3': false,
                'additional:D10': true,
            },
        }

        expect(computed.storedMissingCourseCardSummary.call(ctx)).toMatchObject({
            count: 0,
            hours: 0,
            countLabel: '0 Module',
            hoursLabel: '0 Std.',
        })
        expect(computed.storedPlannedCourseSummary.call(ctx)).toMatchObject({
            count: 0,
            hours: 0,
            countLabel: '0 Module',
            hoursLabel: '0 Std.',
        })
        expect(computed.storedAdditionalCourseSummary.call(ctx)).toMatchObject({
            count: 0,
            hours: 0,
            countLabel: '0 Module',
            hoursLabel: '0 Std.',
        })
        expect(computed.selectedCourseSummary.call(ctx)).toMatchObject({
            count: 0,
            hours: 0,
            countLabel: '0 Module',
            hoursLabel: '0 Std.',
        })

        ctx.storedTimetableStudentContext.courses.additional = [
            { code: 'D10', label: 'D10', hours: 25 },
        ]
        ctx.storedTimetableV2Selection = {}

        expect(computed.selectedCourseLimitExceeded.call(ctx)).toBe(false)

        ctx.storedTimetableV2Selection = {
            courseSelections: {
                'additional:D10': true,
            },
        }

        expect(computed.selectedCourseLimitExceeded.call(ctx)).toBe(false)
    })

    it('shows imported course groups for the selected review course', () => {
        const computed = (TimetableV2 as any).computed
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
            selectedReviewCourseKey: '',
            selectedCourseItemsClickable: true,
            selectedCourseOfferItemsSelectable: true,
            storedTimetableStudentContext: {
                courses: {
                    failed: [],
                    missing: [],
                    planned: [
                        { code: 'D1', label: 'D1', hours: 2 },
                    ],
                    additional: [],
                },
            },
            storedTimetableV2Selection: {
                semester: 1,
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                courseSelections: {
                    'planned:D1': true,
                },
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
                timetableV2Selection: ctx.storedTimetableV2Selection,
                transferredStudentContext: ctx.storedTimetableStudentContext,
            })),
            saveStoredTimetableState: vi.fn((state) => {
                ctx.storedTimetableV2Selection = state.timetableV2Selection
            }),
            schoolHours: [
                { hour: 2, from: '08:50', until: '09:40' },
                { hour: 3, from: '09:45', until: '10:35' },
                { hour: 4, from: '10:50', until: '11:40' },
                { hour: 5, from: '11:45', until: '12:30' },
            ],
            storedMissingCourseCardItems: [],
            storedCompletedCourseItems: [],
            storedSemesterCourseItems: [],
            storedAdditionalCourseItems: [],
            selectedNegativeCourses: [],
            courseGroups: [
                {
                    key: 'd1-grp-1',
                    semester: 1,
                    weekday: 1,
                    hour: 2,
                    title: 'D1',
                    display_label: 'D1 - Grp1 - KRO',
                    course: 'D',
                    subject: 'D',
                    module_code: '',
                    class_name: 'D1-Grp1-KRO',
                    recurrence_interval: 2,
                    recurrence_label: 'wöchentlich',
                },
                {
                    key: 'd1-grp-2',
                    semester: 1,
                    weekday: 1,
                    hour: 3,
                    title: 'D1',
                    display_label: 'D1 - Grp2 - KRO',
                    course: 'D',
                    subject: 'D',
                    module_code: '',
                    class_name: 'D1-Grp2-KRO',
                    block_label: 'Block',
                    is_block: true,
                    first_date: '2026-03-12',
                    last_date: '2026-05-16',
                },
                {
                    key: 'd1-grp-1-second-slot',
                    semester: 1,
                    weekday: 1,
                    hour: 4,
                    title: 'D1',
                    display_label: 'D1 - Grp1 - KRO',
                    course: 'D',
                    subject: 'D',
                    module_code: '',
                    class_name: 'D1-Grp1-KRO',
                    recurrence_label: 'wöchentlich',
                },
                {
                    key: 'd1-grp-1-third-slot',
                    semester: 1,
                    weekday: 1,
                    hour: 5,
                    title: 'D1',
                    display_label: 'D1 - Grp1 - KRO',
                    course: 'D',
                    subject: 'D',
                    module_code: '',
                    class_name: 'D1-Grp1-KRO',
                    recurrence_label: 'wöchentlich',
                },
                {
                    key: 'd10-grp-1',
                    semester: 1,
                    weekday: 2,
                    hour: 2,
                    title: 'D10',
                    display_label: 'D10 - Grp1 - KRO',
                    course: 'D',
                    subject: 'D',
                    module_code: '',
                    class_name: 'D10-Grp1-KRO',
                    recurrence_label: 'wöchentlich',
                },
            ],
        }
        Object.defineProperty(ctx, 'courseSelectionOverrides', {
            get() {
                return methods.courseSelectionOverridesForSelection.call(ctx, ctx.storedTimetableV2Selection)
            },
        })
        Object.defineProperty(ctx, 'offeredCourseSelectionOverrides', {
            get() {
                return computed.offeredCourseSelectionOverrides.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'storedPlannedCourseItems', {
            get() {
                return computed.storedPlannedCourseItems.call(ctx)
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
        Object.defineProperty(ctx, 'selectedCourseItems', {
            get() {
                return computed.selectedCourseItems.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'selectedReviewCourseItem', {
            get() {
                return computed.selectedReviewCourseItem.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'effectiveTimetableV2Selection', {
            get() {
                return computed.effectiveTimetableV2Selection.call(ctx)
            },
        })

        expect(computed.selectedReviewCourseItem.call(ctx)).toBeNull()

        methods.selectReviewCourse.call(ctx, { selectionKey: 'planned:D1' })

        expect(ctx.selectedReviewCourseKey).toBe('planned:D1')
        expect(computed.selectedReviewOfferedCourseItems.call(ctx).map((course: Record<string, any>) => ({
            code: course.code,
            name: course.name,
            selectionKey: course.selectionKey,
            scheduleLabel: course.scheduleLabel,
            recurrenceLabel: course.recurrenceLabel,
            distanceLearning: course.distanceLearning,
        }))).toEqual([
            {
                code: 'D1',
                name: 'D1 - Grp1 - KRO',
                selectionKey: expect.any(String),
                scheduleLabel: 'Mo 2. 2-wöchig 08:50-09:40, Mo 4.-5. 2-wöchig 10:50-12:30',
                recurrenceLabel: '2-wöchig',
                distanceLearning: false,
            },
            {
                code: 'D1',
                name: 'D1 - Grp2 - KRO',
                selectionKey: expect.any(String),
                scheduleLabel: 'Mo 3. 09:45-10:35',
                recurrenceLabel: '',
                distanceLearning: true,
            },
        ])
        const [firstOfferedCourse, secondOfferedCourse] = computed.selectedReviewOfferedCourseItems.call(ctx)

        expect(firstOfferedCourse.selectionKey).not.toBe(secondOfferedCourse.selectionKey)
        expect(methods.offeredCourseItemsAllDeselected.call(ctx, computed.selectedReviewCourseItem.call(ctx))).toBe(false)
        expect(methods.offeredCourseSelected.call(ctx, firstOfferedCourse)).toBe(true)
        expect(methods.offeredCourseSelected.call(ctx, secondOfferedCourse)).toBe(true)

        const [deselectedFirstOfferedCourse] = computed.selectedReviewOfferedCourseItems.call(ctx)
        methods.toggleOfferedCourseItem.call(ctx, deselectedFirstOfferedCourse)

        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: expect.objectContaining({
                offeredCourseSelections: {
                    [firstOfferedCourse.selectionKey]: false,
                },
            }),
            transferredStudentContext: ctx.storedTimetableStudentContext,
        }))
        expect(methods.offeredCourseSelected.call(ctx, firstOfferedCourse)).toBe(false)
        expect(methods.offeredCourseSelected.call(ctx, secondOfferedCourse)).toBe(true)
        expect(methods.offeredCourseItemsAllDeselected.call(ctx, computed.selectedReviewCourseItem.call(ctx))).toBe(false)

        const [, deselectedSecondOfferedCourse] = computed.selectedReviewOfferedCourseItems.call(ctx)
        methods.toggleOfferedCourseItem.call(ctx, deselectedSecondOfferedCourse)

        expect(methods.offeredCourseSelected.call(ctx, firstOfferedCourse)).toBe(false)
        expect(methods.offeredCourseSelected.call(ctx, secondOfferedCourse)).toBe(false)
        expect(methods.offeredCourseItemsAllDeselected.call(ctx, computed.selectedReviewCourseItem.call(ctx))).toBe(true)

        const [currentlyDeselectedFirstOfferedCourse] = computed.selectedReviewOfferedCourseItems.call(ctx)
        methods.toggleOfferedCourseItem.call(ctx, currentlyDeselectedFirstOfferedCourse)

        expect(methods.offeredCourseSelected.call(ctx, firstOfferedCourse)).toBe(true)
        expect(methods.offeredCourseSelected.call(ctx, secondOfferedCourse)).toBe(false)
        expect(methods.offeredCourseItemsAllDeselected.call(ctx, computed.selectedReviewCourseItem.call(ctx))).toBe(false)

        const [, currentlyDeselectedSecondOfferedCourse] = computed.selectedReviewOfferedCourseItems.call(ctx)
        methods.toggleOfferedCourseItem.call(ctx, currentlyDeselectedSecondOfferedCourse)

        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: expect.objectContaining({
                offeredCourseSelections: {
                    [firstOfferedCourse.selectionKey]: true,
                    [secondOfferedCourse.selectionKey]: true,
                },
            }),
            transferredStudentContext: ctx.storedTimetableStudentContext,
        }))
        expect(methods.offeredCourseSelected.call(ctx, secondOfferedCourse)).toBe(true)
        expect(methods.offeredCourseIsDistanceLearning.call(ctx, {
            scheduleSlots: [
                { weekday: 1, hour: 1, recurrenceInterval: 1 },
                { weekday: 1, hour: 2, recurrenceInterval: 2 },
            ],
        }, { hours: 3 })).toBe(true)
        expect(methods.compactScheduleSlotsLabel.call(ctx, [
            { weekday: 1, hour: 11, from: '17:50', until: '18:35', recurrenceLabel: '1-wöchig' },
            { weekday: 1, hour: 12, from: '18:45', until: '19:30', recurrenceLabel: '2-wöchig' },
            { weekday: 2, hour: 14, from: '20:25', until: '21:10', recurrenceLabel: '1-wöchig' },
            { weekday: 2, hour: 15, from: '21:10', until: '21:55', recurrenceLabel: '2-wöchig' },
        ])).toBe('Mo 11. 1-wöchig 17:50-18:35, Mo 12. 2-wöchig 18:45-19:30, Di 14. 1-wöchig 20:25-21:10, Di 15. 2-wöchig 21:10-21:55')
        expect(methods.offeredCourseGroupLabel.call(ctx, {
            title: 'INF',
            display_label: 'INF - 1 - Grp1 - KROINF',
            class_name: 'INF - 1 - Grp1 - KRO',
        }, 'INF')).toBe('INF - 1 - Grp1 - KRO')
        expect(methods.offeredCourseGroupLabel.call(ctx, {
            title: 'D',
            display_label: 'D - 1 - 1C - GOSD',
            class_name: 'D - 1 - 1C - GOS',
        }, 'D')).toBe('D - 1 - 1C - GOS')
        expect(methods.offeredCourseCodeVisible.call(ctx, {
            code: 'D',
            name: 'D - 1 - 1C - GOS',
        })).toBe(false)
    })

    it('persists timetable v2 course toggle overrides', () => {
        const computed = (TimetableV2 as any).computed
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
            storedTimetableStudentContext: null,
            storedMissingCourseCardItems: [],
            storedCompletedCourseItems: [],
            storedSemesterCourseItems: [],
            storedPlannedCourseItems: [
                { code: 'D1', label: 'D1' },
            ],
            storedAdditionalCourseItems: [
                { code: 'GS1', label: 'GS1' },
            ],
            storedCourseSelectionOverrides: {},
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
                ctx.storedCourseSelectionOverrides = state.timetableV2Selection.courseSelections || {}
            }),
            selectedNegativeCourses: [],
        }
        Object.defineProperty(ctx, 'courseSelectionOverrides', {
            get() {
                return methods.courseSelectionOverridesForSelection.call(ctx, ctx.storedTimetableV2Selection)
            },
        })

        expect(methods.courseItemSelected.call(ctx, { code: 'D1', label: 'D1' }, 'planned')).toBe(false)
        expect(methods.courseItemSelected.call(ctx, { code: 'GS1', label: 'GS1' }, 'additional')).toBe(false)

        methods.toggleCourseItem.call(ctx, { code: 'D1', label: 'D1' }, 'planned')
        methods.applyDraftCourseSelections.call(ctx)

        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: {
                semester: 1,
                courseSelections: {
                    'planned:D1': true,
                },
            },
            transferredStudentContext: null,
        }))
        expect(methods.courseItemSelected.call(ctx, { code: 'D1', label: 'D1' }, 'planned')).toBe(true)

        methods.toggleCourseItem.call(ctx, { code: 'D1', label: 'D1' }, 'planned')
        methods.applyDraftCourseSelections.call(ctx)

        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: {
                semester: 1,
            },
            transferredStudentContext: null,
        }))
        expect(methods.courseItemSelected.call(ctx, { code: 'D1', label: 'D1' }, 'planned')).toBe(false)

        methods.toggleCourseItem.call(ctx, { code: 'GS1', label: 'GS1' }, 'additional')
        methods.applyDraftCourseSelections.call(ctx)

        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: {
                semester: 1,
                courseSelections: {
                    'additional:GS1': true,
                },
            },
            transferredStudentContext: null,
        }))
        expect(methods.courseItemSelected.call(ctx, { code: 'GS1', label: 'GS1' }, 'additional')).toBe(true)

        methods.toggleCourseItem.call(ctx, { code: 'GS1', label: 'GS1' }, 'additional')
        methods.applyDraftCourseSelections.call(ctx)

        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: {
                semester: 1,
            },
            transferredStudentContext: null,
        }))
        expect(methods.courseItemSelected.call(ctx, { code: 'GS1', label: 'GS1' }, 'additional')).toBe(false)
    })

    it('persists timetable v2 course group selection overrides', () => {
        const computed = (TimetableV2 as any).computed
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
            storedTimetableStudentContext: null,
            storedMissingCourseCardItems: [],
            storedCompletedCourseItems: [],
            storedSemesterCourseItems: [],
            storedPlannedCourseItems: [
                { code: 'D1', label: 'D1' },
                { code: 'E1', label: 'E1' },
            ],
            storedAdditionalCourseItems: [
                { code: 'GS1', label: 'GS1' },
            ],
            storedCourseSelectionOverrides: {},
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
                ctx.storedCourseSelectionOverrides = state.timetableV2Selection.courseSelections || {}
            }),
            selectedNegativeCourses: [],
        }
        Object.defineProperty(ctx, 'courseSelectionOverrides', {
            get() {
                return methods.courseSelectionOverridesForSelection.call(ctx, ctx.storedTimetableV2Selection)
            },
        })

        expect(methods.courseGroupAllSelected.call(ctx, 'planned')).toBe(false)
        expect(methods.courseGroupNoneSelected.call(ctx, 'planned')).toBe(true)

        methods.setCourseGroupSelection.call(ctx, 'planned', true)
        methods.applyDraftCourseSelections.call(ctx)

        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: {
                semester: 1,
                courseSelections: {
                    'planned:D1': true,
                    'planned:E1': true,
                },
            },
            transferredStudentContext: null,
        }))
        expect(methods.courseGroupAllSelected.call(ctx, 'planned')).toBe(true)
        expect(methods.courseGroupNoneSelected.call(ctx, 'planned')).toBe(false)

        methods.setCourseGroupSelection.call(ctx, 'planned', false)
        methods.applyDraftCourseSelections.call(ctx)

        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: {
                semester: 1,
            },
            transferredStudentContext: null,
        }))
        expect(methods.courseGroupAllSelected.call(ctx, 'planned')).toBe(false)
        expect(methods.courseGroupNoneSelected.call(ctx, 'planned')).toBe(true)
        expect(methods.courseGroupAllSelected.call(ctx, 'additional')).toBe(false)
        expect(methods.courseGroupNoneSelected.call(ctx, 'additional')).toBe(true)

        methods.setCourseGroupSelection.call(ctx, 'additional', true)
        methods.applyDraftCourseSelections.call(ctx)

        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: {
                semester: 1,
                courseSelections: {
                    'additional:GS1': true,
                },
            },
            transferredStudentContext: null,
        }))
        expect(methods.courseGroupAllSelected.call(ctx, 'additional')).toBe(true)
        expect(methods.courseGroupNoneSelected.call(ctx, 'additional')).toBe(false)

        methods.setCourseGroupSelection.call(ctx, 'additional', false)
        methods.applyDraftCourseSelections.call(ctx)

        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: {
                semester: 1,
            },
            transferredStudentContext: null,
        }))
        expect(methods.courseGroupAllSelected.call(ctx, 'additional')).toBe(false)
        expect(methods.courseGroupNoneSelected.call(ctx, 'additional')).toBe(true)
    })

    it('preselects timetable v2 courses within the course and hour limits', () => {
        const computed = (TimetableV2 as any).computed
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
            courseCardsVisible: true,
            storedTimetableStudentContext: null,
            storedMissingCourseCardItems: [
                { code: 'BU1', label: 'BU1', hours: 10, semester: 1 },
            ],
            storedCompletedCourseItems: [],
            storedSemesterCourseItems: [],
            storedPlannedCourseItems: [
                { code: 'D3', label: 'D3', hours: 4, semester: 3 },
                { code: 'M3', label: 'M3', hours: 3, semester: 3 },
                { code: 'M4', label: 'M4', hours: 3, semester: 4 },
                { code: 'M5', label: 'M5', hours: 3, semester: 5 },
            ],
            storedAdditionalCourseItems: [
                { code: 'GS2', label: 'GS2', hours: 15, semester: 2 },
                { code: 'GS5', label: 'GS5', hours: 1, semester: 5 },
            ],
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
                timetableV2Selection: ctx.storedTimetableV2Selection,
                transferredStudentContext: null,
            })),
            saveStoredTimetableState: vi.fn((state) => {
                ctx.storedTimetableV2Selection = state.timetableV2Selection
            }),
        }
        Object.defineProperty(ctx, 'courseSelectionOverrides', {
            get() {
                return methods.courseSelectionOverridesForSelection.call(ctx, ctx.storedTimetableV2Selection)
            },
        })
        Object.defineProperty(ctx, 'courseLimitPreselectionSignature', {
            get() {
                return computed.courseLimitPreselectionSignature.call(ctx)
            },
        })

        expect(methods.applyCourseLimitPreselection.call(ctx)).toBe(true)

        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: {
                semester: 1,
                courseLimitPreselectionKey: expect.any(String),
            },
            transferredStudentContext: null,
        }))

        expect(methods.applyCourseLimitPreselection.call(ctx)).toBe(false)
        expect(ctx.saveStoredTimetableState).toHaveBeenCalledTimes(1)

        ctx.storedTimetableV2Selection = {
            ...ctx.storedTimetableV2Selection,
            courseSelections: {
                ...ctx.storedTimetableV2Selection.courseSelections,
                'planned:D3': false,
            },
        }

        expect(methods.applyCourseLimitPreselection.call(ctx, true)).toBe(true)

        expect(ctx.saveStoredTimetableState).toHaveBeenLastCalledWith(expect.objectContaining({
            timetableV2Selection: {
                semester: 1,
                courseLimitPreselectionKey: expect.any(String),
            },
            transferredStudentContext: null,
        }))

        expect(methods.compareCourseLimitPreselectionItems.call(ctx, { code: 'GS5' }, { code: 'D5' })).toBeLessThan(0)
        expect(methods.compareCourseLimitPreselectionItems.call(ctx, { code: 'M5' }, { code: 'D5' })).toBeLessThan(0)
    })

    it('keeps additional courses deselected by default', () => {
        const computed = (TimetableV2 as any).computed
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
            timetableStartMode: 'without-student',
            storedTimetableStudentContext: null,
            storedMissingCourseCardItems: [],
            storedCompletedCourseItems: [],
            storedSemesterCourseItems: [],
            storedPlannedCourseItems: [
                { code: 'D1', label: 'D1', hours: 3, semester: 1 },
            ],
            storedAdditionalCourseItems: [
                { code: 'D2', label: 'D2', hours: 3, semester: 2 },
                { code: 'GS2', label: 'GS2', hours: 4, semester: 2 },
            ],
            storedTimetableV2Selection: {
                semester: 1,
            },
        }
        Object.defineProperty(ctx, 'noStudentSelectedSemester', {
            get() {
                return computed.noStudentSelectedSemester.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'courseCardsVisible', {
            get() {
                return computed.courseCardsVisible.call(ctx)
            },
        })
        Object.defineProperty(ctx, 'noStudentCourseSelectionMode', {
            get() {
                return computed.noStudentCourseSelectionMode.call(ctx)
            },
        })

        expect(methods.courseSelectionsForCourseLimitPreselection.call(ctx, {})).toEqual({})
        expect(methods.courseItemSelected.call(ctx, { code: 'D2', label: 'D2' }, 'additional')).toBe(false)
        expect(methods.courseItemSelected.call(ctx, { code: 'D1', label: 'D1' }, 'planned')).toBe(false)
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
        })).toMatchObject({
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
                    semester: null,
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
                    semester: null,
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
                    semester: null,
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
            language: 'SPA',
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

    it('normalizes a cleared timetable v2 language to the completed language course default', () => {
        const methods = (TimetableV2 as any).methods
        const ctx: any = {
            ...methods,
        }

        expect(methods.timetableV2SelectionWithCourseDefaults.call(ctx, {
            language: null,
        }, [
            { code: 'S1', label: 'S1', meta: '2' },
        ])).toEqual({
            language: 'SPA',
        })
    })

    it('splits the subjects area into two graphics, subjects, rules, and mapping pages', () => {
        const methods = (SubjectsOverview as any).methods
        const computed = (SubjectsOverview as any).computed
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/subjectsOverview/SubjectsOverview.vue',
            'utf8',
        )

        expect(computed.subjectNavigationItems.call({ canManageSubjectSettings: false }))
            .toMatchObject([
                { key: 'subject-plan-v2', label: 'Grafik v2' },
                { key: 'subject-plan', label: 'Grafik' },
            ])
        expect(computed.subjectNavigationItems.call({ canManageSubjectSettings: true }))
            .toMatchObject([
                { key: 'subject-plan-v2', label: 'Grafik v2' },
                { key: 'subjects', label: 'Fächer' },
                { key: 'rules', label: 'Regeln' },
                { key: 'mapping', label: 'Zuordnung' },
                { key: 'subject-plan', label: 'Grafik' },
            ])
        expect(methods.normalizedSubjectAction.call({ canManageSubjectSettings: false }, 'subject-plan-v2'))
            .toBe('subject-plan-v2')
        expect(computed.studyProgramLabel.call({ studyProgram: 'normalstudium' })).toBe('Normalstudium')
        expect(computed.studyProgramLabel.call({ studyProgram: 'kompaktstudium' })).toBe('Kompaktstudium')
        expect(computed.subjectOverviewV2Semesters.call({ studyProgram: 'normalstudium' }))
            .toEqual([1, 2, 3, 4, 5, 6, 7, 8])
        expect(computed.subjectOverviewV2Semesters.call({ studyProgram: 'kompaktstudium' }))
            .toEqual([1, 2, 3, 4, 5])
        expect(computed.subjectOverviewV2ShowsHoursAndTotals.call({ studyProgram: 'normalstudium' })).toBe(true)
        expect(computed.subjectOverviewV2ShowsHoursAndTotals.call({ studyProgram: 'kompaktstudium' })).toBe(false)
        expect(computed.subjectOverviewV2GridStyle.call({
            subjectOverviewV2Columns: [{}, {}, {}],
            subjectOverviewV2ShowsHoursAndTotals: false,
        })).toEqual({ '--subject-plan-columns': 4 })

        const subjectOverviewColumnContext = {
            ...methods,
            activeSubjectRows: [],
        }
        const normalSubjectColumns = computed.subjectOverviewColumns.call({
            ...subjectOverviewColumnContext,
            studyProgram: 'normalstudium',
        })
        const compactSubjectColumns = computed.subjectOverviewColumns.call({
            ...subjectOverviewColumnContext,
            studyProgram: 'kompaktstudium',
        })

        expect(normalSubjectColumns.find((column: { key: string }) => column.key === 'LPT/VWA')?.label)
            .toBe('LPT/VWA')
        expect(compactSubjectColumns.find((column: { key: string }) => column.key === 'LPT/VWA')?.label)
            .toBe('VWA')

        const dynamicColumnsContext = {
            activeSubjectRows: [
                { json_subject: 'D', json_code: 'D1', name: 'Deutsch 1' },
                { json_subject: 'E', json_code: 'E1', name: 'Englisch 1' },
                { json_subject: 'D', json_code: 'D2', name: 'Deutsch 2' },
            ],
            subjectOverviewColumns: [
                { key: 'LPT/VWA', label: 'LPT/VWA', subjectKeys: ['LPT', 'VWA'] },
                { key: 'R/ET', label: 'R/ET', subjectKeys: ['R/ET', 'R', 'ET'] },
                { key: 'L/F/S', label: 'L/F/S', subjectKeys: ['L/F/S', 'L', 'F', 'S'] },
                { key: 'D', label: 'D', subjectKeys: ['D'] },
                { key: 'E', label: 'E', subjectKeys: ['E'] },
            ],
            subjectOverviewSubjectKey: methods.subjectOverviewSubjectKey,
            subjectOverviewV2ColumnSubtitle: (column: { label: string }) => column.label,
        }

        expect(computed.subjectOverviewV2Columns.call(dynamicColumnsContext).map((column: { key: string }) => column.key))
            .toEqual(['D', 'E'])
        expect(computed.subjectOverviewV2Columns.call({
            ...dynamicColumnsContext,
            activeSubjectRows: dynamicColumnsContext.activeSubjectRows.filter(subject => subject.json_subject !== 'D'),
        }).map((column: { key: string }) => column.key))
            .toEqual(['E'])
        expect(computed.subjectOverviewV2Columns.call({
            ...dynamicColumnsContext,
            activeSubjectRows: [
                { json_subject: 'D', json_code: 'D1' },
                { json_subject: 'S', json_code: 'S1' },
                { json_subject: 'ET', json_code: 'ET1' },
                { json_subject: 'VWA', json_code: 'VWA' },
                { json_subject: 'NEU', json_code: 'NEU1' },
            ],
        }).map((column: { key: string }) => column.key))
            .toEqual(['LPT/VWA', 'R/ETH', 'L/F/S', 'D', 'NEU'])

        expect(methods.subjectOverviewV2ColumnSubtitle.call({
            activeSubjectRows: [
                {
                    json_subject: 'R/ET',
                    json_code: 'R/ET1',
                    name: 'Religion/Ethik 1',
                },
            ],
            subjectOverviewSubjectKey: methods.subjectOverviewSubjectKey,
        }, {
            label: 'R/ETH',
            subjectKeys: ['R/ET', 'R', 'ET', 'ETH'],
        })).toBe('Religion/Ethik')

        expect(componentSource).toContain('subjectNavigationItems()')
        expect(componentSource).not.toContain("key: 'overview'")
        expect(componentSource).not.toContain("label: 'Übersicht'")
        expect(componentSource).toContain("key: 'subject-plan'")
        expect(componentSource).toContain("label: 'Grafik'")
        expect(componentSource).toContain("key: 'subject-plan-v2'")
        expect(componentSource).toContain("label: 'Grafik v2'")
        expect(componentSource).not.toContain("key: 'import'")
        expect(componentSource).not.toContain("label: 'Import'")
        expect(componentSource).toContain("label: 'Fächer'")
        expect(componentSource).toContain("label: 'Regeln'")
        expect(componentSource).toContain("label: 'Zuordnung'")
        expect(componentSource).toContain("v-if=\"subject_action === 'subject-plan'\"")
        expect(componentSource).toContain("v-if=\"subject_action === 'subject-plan-v2'\"")
        expect(componentSource).toContain("subject_action === 'subject-plan'")
        expect(componentSource).not.toContain("v-if=\"subject_action === 'import'\"")
        expect(componentSource).toContain("v-if=\"subject_action === 'subjects'\"")
        expect(componentSource).toContain("v-if=\"subject_action === 'rules'\"")
        expect(componentSource).toContain("v-if=\"subject_action === 'mapping'\"")
        expect(componentSource).toContain("'subject-plan'")
        expect(componentSource).toContain('canManageSubjectSettings()')
        expect(componentSource).toContain("['subject-plan', 'subject-plan-v2', 'subjects', 'rules', 'mapping']")
        expect(componentSource).toContain("['subject-plan', 'subject-plan-v2']")
        expect(componentSource).toContain('Fächerübersicht v2 · {{ studyProgramLabel }}')
        expect(componentSource).toContain('subject-overview-v2-card__canvas')
        expect(componentSource).toContain('subject-overview-v2-card__footer')
        expect(componentSource).toContain('v-for="column in subjectOverviewV2Columns"')
        expect(componentSource).toContain('v-for="row in subjectOverviewV2Rows"')
        expect(componentSource).toContain(':style="subjectOverviewV2GridStyle"')
        expect(componentSource).toContain('subjectOverviewV2Footer')
        expect(componentSource).toContain('subjectOverviewV2GrandTotals')
        expect(componentSource).toContain('v-if="subjectOverviewV2ShowsHoursAndTotals" class="subject-plan-hours"')
        expect(componentSource).toContain('<template v-if="subjectOverviewV2ShowsHoursAndTotals">')
        expect(componentSource).toContain("'subject-plan-cell--v2-course': cell.subjects.length")
        expect(componentSource).toContain('* wahlweise')
        expect(componentSource).toContain('Wirtschaftskundlicher Zweig')
        expect(componentSource).toContain('Gymnasialer Zweig')
        expect(componentSource).toContain("return allowedActions.includes(subsection) ? subsection : 'subject-plan-v2'")
        expect(componentSource).toContain('redirectUnauthorizedSubjectRoute()')
        expect(componentSource).toContain('redirectRemovedSubjectImportRoute()')
        expect(componentSource).toContain('redirectMissingSubjectRoute()')
        expect(componentSource).toContain("path: '/admin/students-timetables/subjects-overview/subject-plan-v2'")
        expect(componentSource).toContain('handleSubjectNavigation(key)')
        expect(componentSource).toContain('embedded')
        expect(componentSource).toContain("subject_action: this.embedded ? 'subject-plan' : this.normalizedSubjectAction(this.$route.params.subsection)")
        expect(componentSource).toContain('if (this.embedded) {')
        expect(componentSource).toContain('/admin/students-timetables/subjects-overview/${this.subject_action}')
    })

    it('keeps the rule editor focused on one admin-facing rule type', () => {
        const methods = (SubjectsOverview as any).methods
        const computed = (SubjectsOverview as any).computed
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/subjectsOverview/SubjectsOverview.vue',
            'utf8',
        )
        const ctx: any = {
            ...methods,
            subjectRuleSelectionKeys: computed.subjectRuleSelectionKeys.call({}),
            subjectRows: [
                {
                    stable_key: 'language-l6',
                    semester: 7,
                    branch: 'gymnasial',
                    json_code: 'L6',
                },
                {
                    stable_key: 'computer-science-inf2',
                    semester: 7,
                    branch: 'wirtschaftskundlich',
                    json_code: 'INF2',
                },
            ],
        }

        expect(methods.subjectRuleSelectionTitle.call(ctx, 'branch')).toBe('Zweig')
        expect(methods.subjectRuleOptionBranchClass.call(
            ctx,
            { selection_key: 'branch' },
            { value: 'gymnasial' },
        )).toBe('subject-plan-legend-swatch--gymnasial')
        expect(methods.subjectRuleOptionBranchClass.call(
            ctx,
            { selection_key: 'branch' },
            { value: 'wirtschaftskundlich' },
        )).toBe('subject-plan-legend-swatch--wirtschaftskundlich')
        expect(methods.subjectRuleOptionBranchClass.call(
            ctx,
            { selection_key: 'language' },
            { value: 'gymnasial' },
        )).toBeNull()
        expect(methods.subjectRuleSubjectOptions.call(
            ctx,
            { selection_key: 'branch' },
            { value: 'gymnasial' },
        )).toEqual([
            { value: 'language-l6', title: 'Sem. 7 · L6' },
            { value: 'computer-science-inf2', title: 'Sem. 7 · INF2 · Wirtschaftskundlich' },
        ])
        expect(methods.subjectRuleSubjectOptions.call(
            ctx,
            { selection_key: 'language' },
            { value: 'L' },
        )[0].title).toBe('Sem. 7 · L6 · Gymnasial')

        const gymnasialOption = { value: 'gymnasial', subject_keys: ['language-l6'] }

        expect(methods.subjectRuleSelectedSubjectOptions.call(
            ctx,
            { selection_key: 'branch' },
            gymnasialOption,
        )).toEqual([
            { value: 'language-l6', title: 'Sem. 7 · L6' },
        ])
        expect(methods.subjectRuleAvailableSubjectOptions.call(
            ctx,
            { selection_key: 'branch' },
            gymnasialOption,
        )).toEqual([
            { value: 'computer-science-inf2', title: 'Sem. 7 · INF2 · Wirtschaftskundlich' },
        ])
        expect(methods.subjectRuleSelectedSubjectGroups.call(
            ctx,
            { selection_key: 'language' },
            { subject_keys: ['language-l6', 'computer-science-inf2'] },
        )).toEqual([
            {
                branch: 'wirtschaftskundlich',
                label: 'Wirtschaftskundlicher Zweig',
                subjects: [
                    { value: 'computer-science-inf2', title: 'Sem. 7 · INF2' },
                ],
            },
            {
                branch: 'gymnasial',
                label: 'Gymnasialer Zweig',
                subjects: [
                    { value: 'language-l6', title: 'Sem. 7 · L6' },
                ],
            },
        ])
        expect(methods.subjectRuleSelectedSubjectGroups.call(
            {
                ...ctx,
                subjectRows: [
                    {
                        stable_key: 'gym-me2',
                        semester: 8,
                        branch: 'gymnasial',
                        json_code: 'ME2',
                    },
                    {
                        stable_key: 'gym-be2',
                        semester: 8,
                        branch: 'gymnasial',
                        json_code: 'BE2',
                    },
                    {
                        stable_key: 'gym-be1',
                        semester: 7,
                        branch: 'gymnasial',
                        json_code: 'BE1',
                    },
                    {
                        stable_key: 'common-d7',
                        semester: 7,
                        branch: null,
                        json_code: 'D7',
                    },
                ],
            },
            { selection_key: 'branch' },
            { value: 'gymnasial', subject_keys: ['gym-me2', 'gym-be2', 'gym-be1', 'common-d7'] },
        )).toEqual([
            {
                branch: 'gymnasial',
                label: 'Gymnasialer Zweig',
                subjects: [
                    { value: 'gym-be1', title: 'Sem. 7 · BE1 · Gymnasial' },
                    { value: 'gym-be2', title: 'Sem. 8 · BE2 · Gymnasial' },
                    { value: 'common-d7', title: 'Sem. 7 · D7' },
                    { value: 'gym-me2', title: 'Sem. 8 · ME2 · Gymnasial' },
                ],
            },
        ])
        const familyContext: any = {
            ...ctx,
            subjectRows: [
                { stable_key: 'gym-me2', semester: 8, branch: 'gymnasial', json_code: 'ME2' },
                { stable_key: 'gym-l7', semester: 8, branch: 'gymnasial', json_code: 'L7' },
                { stable_key: 'gym-be1', semester: 7, branch: 'gymnasial', json_code: 'BE1' },
                { stable_key: 'gym-s7', semester: 8, branch: 'gymnasial', json_code: 'S7' },
                { stable_key: 'gym-f7', semester: 8, branch: 'gymnasial', json_code: 'F7' },
            ],
        }
        const [familySubjectGroup] = methods.subjectRuleSelectedSubjectGroups.call(
            familyContext,
            { selection_key: 'branch' },
            {
                value: 'gymnasial',
                subject_keys: ['gym-me2', 'gym-l7', 'gym-be1', 'gym-s7', 'gym-f7'],
            },
        )

        expect(methods.subjectRuleSelectedSubjectFamilies.call(
            familyContext,
            { selection_key: 'branch' },
            familySubjectGroup,
        )).toEqual([
            {
                key: 'BE',
                label: 'BE',
                subjects: [
                    { value: 'gym-be1', title: 'Sem. 7 · BE1 · Gymnasial' },
                ],
            },
            {
                key: 'ME',
                label: 'ME',
                subjects: [
                    { value: 'gym-me2', title: 'Sem. 8 · ME2 · Gymnasial' },
                ],
            },
            {
                key: 'languages',
                label: 'Sprachen',
                subjects: [
                    { value: 'gym-f7', title: 'Sem. 8 · F7 · Gymnasial' },
                    { value: 'gym-l7', title: 'Sem. 8 · L7 · Gymnasial' },
                    { value: 'gym-s7', title: 'Sem. 8 · S7 · Gymnasial' },
                ],
            },
        ])

        methods.removeSubjectRuleOptionSubject.call(ctx, gymnasialOption, 'language-l6')
        methods.addSubjectRuleOptionSubject.call(ctx, gymnasialOption, 'computer-science-inf2')
        methods.addSubjectRuleOptionSubject.call(ctx, gymnasialOption, 'computer-science-inf2')

        expect(gymnasialOption.subject_keys).toEqual(['computer-science-inf2'])

        const branchRule = { stable_key: 'branch-rule', selection_key: 'branch' }
        const languageRule = { stable_key: 'language-rule', selection_key: 'language' }
        const editContext: any = {
            ...methods,
            subjectRules: [branchRule, languageRule],
            subjectRulesSnapshot: [],
            subjectRuleOpenPanels: [],
            editingSubjectRuleKey: null,
            rulesEditMode: false,
            settingsMessage: 'Gespeichert',
        }

        methods.startRulesEdit.call(editContext, languageRule)

        expect(editContext.rulesEditMode).toBe(true)
        expect(editContext.editingSubjectRuleKey).toBe('language-rule')
        expect(editContext.subjectRuleOpenPanels).toEqual(['language-rule'])
        expect(methods.isSubjectRuleEditing.call(editContext, branchRule)).toBe(false)
        expect(methods.isSubjectRuleEditing.call(editContext, languageRule)).toBe(true)

        expect(componentSource).not.toContain('label="Regelart"')
        expect(componentSource).toContain('So wirken die Regeln')
        expect(componentSource).toContain('Pflichtfächer gelten gemeinsam.')
        expect(componentSource).toContain('Live-Vorschau – noch nicht gespeichert')
        expect(componentSource).toContain('class="subject-plan-legend-swatch subject-rule-option-heading__swatch"')
        expect(componentSource).toContain(':class="subjectRuleOptionBranchClass(rule, option)"')
        expect(componentSource).toContain('{{ subjectRuleOptionHeading(rule, option) }}')
        expect(componentSource).toContain('{{ subjectRuleOptionImpactLabel(rule) }}')
        expect(componentSource).toContain('subjectRuleSelectedSubjectGroups(rule, option)')
        expect(componentSource).toContain('subjectRuleSelectedSubjectFamilies(rule, subjectGroup)')
        expect(componentSource).toContain('subject-rule-subject-chips')
        expect(componentSource).toContain(':closable="isSubjectRuleEditing(rule)"')
        expect(componentSource).toContain('@click:close="removeSubjectRuleOptionSubject(option, subject.value)"')
        expect(componentSource).toContain('label="Fach / Modul hinzufügen"')
        expect(componentSource).toContain(':items="subjectRuleAvailableSubjectOptions(rule, option)"')
        expect(componentSource).not.toContain('v-model="option.subject_keys"')
        expect(componentSource).toContain('@click="startRulesEdit(rule)"')
        expect(componentSource).toContain('v-model="subjectRuleOpenPanels"')
        expect(componentSource).toContain('isSubjectRuleEditing(rule)')
        expect(componentSource).not.toContain('@click="addSubjectRule"')
        expect(componentSource).not.toContain('addSubjectRule()')
        expect(componentSource).not.toContain('Regel entfernen')
        expect(componentSource).not.toContain('removeSubjectRule(')
        expect(componentSource).not.toContain('v-model="option.value"')
        expect(componentSource).not.toContain('Option entfernen')
        expect(componentSource).not.toContain('addRuleOption(')
        expect(componentSource).not.toContain('removeRuleOption(')
        expect(componentSource).not.toContain('@click="addRuleCondition(rule)"')
        expect(componentSource).not.toContain('addRuleCondition(rule)')
        expect(componentSource).not.toContain('mdi-filter-plus-outline')
        expect(componentSource).not.toContain('<v-text-field v-model="rule.name"')
        expect(componentSource).not.toContain('<v-text-field v-model="rule.label"')
        expect(componentSource).not.toContain('<v-text-field v-model="option.label"')
        expect(componentSource).not.toContain('Code-Präfix (optional)')
        expect(componentSource).not.toContain('{{ rule.selection_key }}')
        expect(componentSource).not.toContain('Eine Option kann mehrere Fächer enthalten.')
        expect(componentSource).not.toContain('Ein Fach kann von mehreren Regeln abhängig sein')
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
                query: {},
            },
            $router: {
                replace,
            },
            subject_action: 'subjects',
        }

        expect(methods.redirectMissingSubjectRoute.call(ctx)).toBe(true)
        expect(ctx.subject_action).toBe('subject-plan-v2')
        expect(replace).toHaveBeenCalledWith({
            path: '/admin/students-timetables/subjects-overview/subject-plan-v2',
            query: { study_program: undefined },
        })
    })

    it('redirects the removed subject import page to the graphic', () => {
        const methods = (SubjectsOverview as any).methods
        const replace = vi.fn()
        const ctx: any = {
            embedded: false,
            studyProgram: 'kompaktstudium',
            subject_action: 'import',
            $route: {
                params: { subsection: 'import' },
                query: {},
            },
            $router: { replace },
        }

        expect(methods.redirectRemovedSubjectImportRoute.call(ctx)).toBe(true)
        expect(ctx.subject_action).toBe('subject-plan-v2')
        expect(replace).toHaveBeenCalledWith({
            path: '/admin/students-timetables/subjects-overview/subject-plan-v2',
            query: { study_program: 'kompaktstudium' },
        })
    })

    it('renders the subject plan without a JSON import workflow', () => {
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
        expect(componentSource).not.toContain('<FileUpload')
        expect(componentSource).not.toContain('subjectUploadRoute')
        expect(componentSource).not.toContain('loadImports()')
        expect(componentSource).toContain('loadSettings()')
        expect(componentSource).toContain("'config.selected_schoolyear.id'()")
        expect(componentSource).toContain('refreshForSchoolyearChange()')
        expect(componentSource).not.toContain('refreshFilePond')
        expect(componentSource).not.toContain('Noch keine JSON-Datei importiert.')
        expect(componentSource).not.toContain('Schul-Unterrichtseinheiten')
        expect(componentSource).not.toContain('Eigenstudium ist nicht enthalten.')
        expect(componentSource).not.toContain('importSubjectCountLabel(importItem.analysis)')
        expect(componentSource).not.toContain('analysis?.subjects_total')
        expect(componentSource).not.toContain('Fächer / ${courseRowsTotal} Kurse')
        expect(componentSource).not.toContain('semester.subjects')
        expect(componentSource).not.toContain('semester.branch_variants?.length')
        expect(componentSource).toContain('.subject-rule-grid')
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
        expect(componentSource).toContain('subjectOverviewMergedCompactModuleSubjects(subjects)')
        expect(componentSource).toContain('subjectOverviewCombinedCompactChoiceCodes(displayCodes)')
        expect(componentSource).toContain('subjectOverviewMergeableChoiceGroupForSubject(subject)')
        expect(componentSource).toContain('isSubjectOverviewChoiceSubject(subject)')
        expect(componentSource).toContain('subjectMatchesSubjectOverviewChoiceGroup(subject, group)')
        expect(componentSource).toContain('subjectOverviewChoiceGroups()')
        expect(componentSource).toContain('subjectOverviewReligionChoiceGroups()')
        expect(componentSource).toContain("choices: ['Rev', 'Ris', 'Rk', 'Ror', 'ET']")
        expect(componentSource).toContain('subjectOverviewLanguageChoiceGroups()')
        expect(componentSource).toContain('subjectOverviewArtChoiceGroups()')
        expect(componentSource).toContain("const groups = this.subjectOverviewAlternativeChoiceGroups(['BE', 'ME'])")
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
        expect(componentSource).not.toContain('Importierte Fächer')
        expect(componentSource).toContain('Fach-Zuordnung')
        expect(componentSource).toContain('subjectSettingsRoute.url(')
        expect(componentSource).toContain('updateSubjectsRoute.url(')
        expect(componentSource).toContain('updateSubjectMappingsRoute.url(')
        expect(componentSource).toContain('this.personalSchoolyearRouteOptions')
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
