import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import SubjectsOverview from '@/pages/admin/studentsTimetables/subjectsOverview/SubjectsOverview.vue'

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
        expect(componentSource).toContain("label: 'Importe'")
        expect(componentSource).toContain("meta: 'Stundenplan'")
        expect(componentSource).toContain("label: 'Fächer'")
        expect(componentSource).toContain("meta: 'Überblick'")
        expect(componentSource).not.toContain("meta: 'Import'")
        expect(componentSource).not.toContain("meta: 'Tagesansicht'")
        expect(componentSource).toContain('/admin/students-timetables/timetable/overview/automatic')
        expect(componentSource).toContain('/admin/students-timetables/subjects-overview/subject-plan')
        expect(componentSource).toContain("const mainSectionKeys = ['timetable', 'subjects-overview', 'import']")
        expect(componentSource).toContain("redirectLegacySection(section)")
        expect(componentSource).toContain("this.$router.replace({ path: '/admin/students-timetables' })")
        expect(componentSource).toContain("this.$router.replace({ path: '/admin/students-timetables/timetable/overview/automatic' })")
        expect(componentSource).toContain("activeNavigationKey === item.key")
        expect(componentSource).toContain("timetable: '/admin/students-timetables'")
        expect(componentSource).toContain("imports: '/admin/students-timetables/timetable/imports'")
        expect(componentSource).toContain("import('./subjectsOverview/SubjectsOverview.vue')")
        expect(componentSource).not.toContain("import('./overview/Overview.vue')")
        expect(componentSource).not.toContain("import('./robot/RobotTimetable.vue')")
        expect(componentSource).toContain("Import v-if=\"main_action === 'import'\"")
        expect(componentSource).not.toContain("RobotTimetable v-if=\"main_action === 'robot'\"")
        expect(componentSource).toContain("SubjectsOverview v-if=\"main_action === 'subjects-overview'\"")
        expect(componentSource).not.toContain("Overview v-if=\"main_action === 'overview'\"")
        expect(componentSource.indexOf("key: 'timetable'"))
            .toBeLessThan(componentSource.indexOf("key: 'imports'"))
        expect(componentSource.indexOf("key: 'imports'"))
            .toBeLessThan(componentSource.indexOf("key: 'subjects-overview'"))
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
        expect(componentSource).toContain("this.$router.replace({ path: '/admin/students-timetables/subjects-overview/subject-plan' })")
        expect(componentSource).toContain('handleSubjectNavigation(key)')
        expect(componentSource).toContain('embedded')
        expect(componentSource).toContain("subject_action: this.embedded ? 'subject-plan' : this.normalizedSubjectAction(this.$route.params.subsection)")
        expect(componentSource).toContain('if (this.embedded) {')
        expect(componentSource).toContain('/admin/students-timetables/subjects-overview/${this.subject_action}')
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
