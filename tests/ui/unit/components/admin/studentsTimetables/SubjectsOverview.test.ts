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

    it('adds the subjects overview menu item to the module shell', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/StudentsTimetables.vue',
            'utf8',
        )

        expect(componentSource).toContain("key: 'subjects-overview'")
        expect(componentSource).toContain("key: 'robot'")
        expect(componentSource).toContain("label: 'Stundenplan'")
        expect(componentSource).toContain("label: 'Roboter'")
        expect(componentSource).toContain("meta: 'Stundenplan'")
        expect(componentSource).toContain("label: 'Fächer'")
        expect(componentSource).toContain("meta: 'Überblick'")
        expect(componentSource).not.toContain("meta: 'Import'")
        expect(componentSource).not.toContain("meta: 'Tagesansicht'")
        expect(componentSource).toContain('/admin/students-timetables/robot')
        expect(componentSource).toContain('/admin/students-timetables/subjects-overview/subject-plan')
        expect(componentSource).toContain("const mainSectionKeys = ['timetable', 'subjects-overview', 'import', 'robot']")
        expect(componentSource).toContain("redirectLegacyOverviewSection(section)")
        expect(componentSource).toContain("this.$router.replace({ path: '/admin/students-timetables/timetable/overview' })")
        expect(componentSource).toContain("import('./robot/RobotTimetable.vue')")
        expect(componentSource).toContain("import('./subjectsOverview/SubjectsOverview.vue')")
        expect(componentSource).not.toContain("import('./overview/Overview.vue')")
        expect(componentSource).toContain("Import v-if=\"main_action === 'import'\"")
        expect(componentSource).toContain("RobotTimetable v-if=\"main_action === 'robot'\"")
        expect(componentSource).toContain("SubjectsOverview v-if=\"main_action === 'subjects-overview'\"")
        expect(componentSource).not.toContain("Overview v-if=\"main_action === 'overview'\"")
        expect(componentSource.indexOf("key: 'timetable'"))
            .toBeLessThan(componentSource.indexOf("key: 'subjects-overview'"))
        expect(componentSource.indexOf("key: 'subjects-overview'"))
            .toBeLessThan(componentSource.indexOf("key: 'robot'"))
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
        expect(componentSource).toContain("const allowedActions = ['subject-plan', 'import', 'subjects', 'mapping']")
        expect(componentSource).toContain("return allowedActions.includes(subsection) ? subsection : 'subject-plan'")
        expect(componentSource).toContain('handleSubjectNavigation(key)')
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
        expect(componentSource).toContain('isSubjectOverviewChoiceSubject(subject)')
        expect(componentSource).toContain('subjectMatchesSubjectOverviewChoiceGroup(subject, group)')
        expect(componentSource).toContain('subjectOverviewChoiceGroups()')
        expect(componentSource).toContain("codes: ['R/ET1']")
        expect(componentSource).toContain("choices: ['Rev', 'Ris', 'Rk', 'Ror', 'ET']")
        expect(componentSource).toContain("codes: ['BE1', 'ME1']")
        expect(componentSource).toContain("codes: ['BE2', 'ME2']")
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
        expect(componentSource).toContain('.subject-plan-item--course')
        expect(componentSource).toContain('.subject-plan-item--course + .subject-plan-item--course')
        expect(componentSource).toContain('border-top: 1px solid rgba(15, 23, 42, 0.42)')
        expect(componentSource).not.toContain('subject-plan-course-group-label')
        expect(componentSource).not.toContain('subject-plan-course-divider')
    })
})
